import { Product, ProductSale, ProductSaleItem, Transaction } from '../types';
import { getStoredProductSales, saveProductSales } from './storage';
import { saveProductSaleToCloud } from './firebaseSync';

const IDB_EMERGENCY_DB = 'balal_mobiles_emergency_vault_v1';
const IDB_SALES_STORE = 'emergency_sales_store';
const IDB_SNAPSHOT_STORE = 'emergency_snapshots';

// ===================== INDEXEDDB EMERGENCY VAULT =====================
function openEmergencyDB(): Promise<IDBDatabase> {
  return new Promise((resolve, reject) => {
    if (typeof indexedDB === 'undefined') {
      return reject(new Error('IndexedDB not supported'));
    }
    const req = indexedDB.open(IDB_EMERGENCY_DB, 1);
    req.onupgradeneeded = () => {
      const db = req.result;
      if (!db.objectStoreNames.contains(IDB_SALES_STORE)) {
        db.createObjectStore(IDB_SALES_STORE, { keyPath: 'id' });
      }
      if (!db.objectStoreNames.contains(IDB_SNAPSHOT_STORE)) {
        db.createObjectStore(IDB_SNAPSHOT_STORE, { keyPath: 'key' });
      }
    };
    req.onsuccess = () => resolve(req.result);
    req.onerror = () => reject(req.error);
  });
}

/**
 * Persist every single sale into ACID-compliant IndexedDB emergency vault
 */
export async function backupEmergencySale(sale: ProductSale): Promise<void> {
  try {
    const db = await openEmergencyDB();
    const tx = db.transaction(IDB_SALES_STORE, 'readwrite');
    tx.objectStore(IDB_SALES_STORE).put(sale);
  } catch (err) {
    console.warn('[DataRecovery] Could not write to emergency IndexedDB vault:', err);
  }
}

/**
 * Read all sales ever saved to IndexedDB emergency vault
 */
export async function getEmergencyVaultSales(): Promise<ProductSale[]> {
  try {
    const db = await openEmergencyDB();
    const tx = db.transaction(IDB_SALES_STORE, 'readonly');
    const req = tx.objectStore(IDB_SALES_STORE).getAll();
    return new Promise((resolve) => {
      req.onsuccess = () => resolve(req.result || []);
      req.onerror = () => resolve([]);
    });
  } catch {
    return [];
  }
}

/**
 * Reconstruct sales from products that have units marked as SOLD,
 * but where the corresponding sales record was not stored.
 */
export function reconstructSalesFromSoldUnits(
  products: Product[],
  existingSales: ProductSale[]
): ProductSale[] {
  const existingInvoices = new Set(existingSales.map((s) => s.invoiceNo));
  const existingSaleIds = new Set(existingSales.map((s) => s.id));
  const recoveredSales: ProductSale[] = [];

  // Group sold units by their invoice number or generate a sale for orphaned sold units
  const soldUnitsByInvoice: Record<
    string,
    { product: Product; unit: NonNullable<Product['units']>[number] }[]
  > = {};

  products.forEach((prod) => {
    if (prod.units && Array.isArray(prod.units)) {
      prod.units.forEach((unit) => {
        if (unit.status === 'SOLD') {
          const invKey = unit.soldInvoiceNo || `INV-RECOVERED-${unit.id}`;
          if (!soldUnitsByInvoice[invKey]) {
            soldUnitsByInvoice[invKey] = [];
          }
          soldUnitsByInvoice[invKey].push({ product: prod, unit });
        }
      });
    }
  });

  // For each invoice group not present in existingSales, reconstruct the sale record
  Object.entries(soldUnitsByInvoice).forEach(([invoiceNo, group]) => {
    if (!existingInvoices.has(invoiceNo)) {
      const firstItem = group[0];
      const dateStr = firstItem.unit.soldDate || new Date().toISOString().split('T')[0];
      const items: ProductSaleItem[] = group.map(({ product, unit }) => ({
        productId: product.id,
        productName: product.name,
        category: product.category,
        quantity: 1,
        purchasePrice: product.purchasePrice,
        unitSalePrice: product.salePrice,
        totalSalePrice: product.salePrice,
        selectedUnitId: unit.id,
        selectedImei1: unit.imei1,
        selectedImei2: unit.imei2,
        selectedColor: unit.color,
        selectedCondition: unit.condition,
        selectedRamStorage: unit.storageRam,
      }));

      const totalAmount = items.reduce((sum, item) => sum + item.totalSalePrice, 0);
      const totalCost = items.reduce((sum, item) => sum + item.purchasePrice, 0);

      const recoveredSale: ProductSale = {
        id: `sale-recovered-${invoiceNo.replace(/[^a-zA-Z0-9]/g, '')}`,
        invoiceNo,
        date: dateStr,
        time: '12:00 PM',
        customerName: 'Recovered Customer',
        customerPhone: '',
        items,
        totalAmount,
        discount: 0,
        netAmount: totalAmount,
        totalPurchaseCost: totalCost,
        profit: totalAmount - totalCost,
        paymentMethod: 'CASH',
        notes: 'Auto-recovered from sold inventory units history',
        createdAt: Date.now() - 3600000,
      };

      if (!existingSaleIds.has(recoveredSale.id)) {
        recoveredSales.push(recoveredSale);
      }
    }
  });

  return recoveredSales;
}

/**
 * Scan pending sync queue in localStorage for any unsynced sales
 */
export function recoverSalesFromSyncQueue(existingSales: ProductSale[]): ProductSale[] {
  const existingSaleIds = new Set(existingSales.map((s) => s.id));
  const recovered: ProductSale[] = [];

  try {
    const rawQueue = localStorage.getItem('balal_mobiles_pending_sync_queue_v1');
    if (rawQueue) {
      const queue = JSON.parse(rawQueue);
      if (Array.isArray(queue)) {
        queue.forEach((item: any) => {
          if (item.action === 'SAVE_SALE' && item.data && item.data.id) {
            if (!existingSaleIds.has(item.data.id)) {
              recovered.push(item.data as ProductSale);
              existingSaleIds.add(item.data.id);
            }
          }
        });
      }
    }
  } catch (e) {
    console.warn('[DataRecovery] Error checking sync queue:', e);
  }

  return recovered;
}

/**
 * Master recovery function to be called on startup or on user request.
 * Merges local storage, IndexedDB emergency vault, pending sync queue,
 * and sold inventory units to guarantee ZERO data loss.
 */
export async function runCompleteDataRecovery(
  products: Product[],
  currentSales: ProductSale[]
): Promise<{ recoveredCount: number; allSales: ProductSale[] }> {
  const salesMap = new Map<string, ProductSale>();

  // 1. Existing in-memory sales
  currentSales.forEach((s) => salesMap.set(s.id, s));

  // 2. LocalStorage sales
  const localSales = getStoredProductSales();
  localSales.forEach((s) => {
    if (!salesMap.has(s.id)) salesMap.set(s.id, s);
  });

  // 3. Emergency IndexedDB vault
  const vaultSales = await getEmergencyVaultSales();
  vaultSales.forEach((s) => {
    if (!salesMap.has(s.id)) salesMap.set(s.id, s);
  });

  // 4. Pending Sync Queue
  const queueSales = recoverSalesFromSyncQueue(Array.from(salesMap.values()));
  queueSales.forEach((s) => {
    if (!salesMap.has(s.id)) salesMap.set(s.id, s);
  });

  // 5. Reconstructed from SOLD inventory units
  const unitReconstructed = reconstructSalesFromSoldUnits(products, Array.from(salesMap.values()));
  unitReconstructed.forEach((s) => {
    if (!salesMap.has(s.id)) salesMap.set(s.id, s);
  });

  const mergedSales = Array.from(salesMap.values()).sort((a, b) => b.createdAt - a.createdAt);
  const recoveredCount = mergedSales.length - currentSales.length;

  if (recoveredCount > 0 || mergedSales.length > localSales.length) {
    saveProductSales(mergedSales);
    // Also push recovered sales to cloud so Firestore has them
    mergedSales.forEach((sale) => {
      saveProductSaleToCloud(sale);
      backupEmergencySale(sale);
    });
    console.log(`[DataRecovery] Recovered ${recoveredCount} missing sales! Total: ${mergedSales.length}`);
  }

  return {
    recoveredCount: Math.max(0, recoveredCount),
    allSales: mergedSales,
  };
}
