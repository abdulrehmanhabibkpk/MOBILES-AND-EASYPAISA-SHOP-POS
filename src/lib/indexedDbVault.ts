import { Product, ProductSale, Transaction, DailyBalance, AppSettings, MobilePurchaseRecord, Supplier } from '../types';

const DB_NAME = 'balal_mobiles_vault_v2';
const DB_VERSION = 1;

const STORES = {
  SALES: 'productSales',
  TRANSACTIONS: 'transactions',
  PRODUCTS: 'products',
  PURCHASES: 'mobilePurchases',
  SUPPLIERS: 'suppliers',
  DAILY_BALANCES: 'dailyBalances',
  SNAPSHOTS: 'snapshots',
} as const;

function openVaultDB(): Promise<IDBDatabase> {
  return new Promise((resolve, reject) => {
    if (typeof indexedDB === 'undefined') {
      return reject(new Error('IndexedDB not supported'));
    }
    const request = indexedDB.open(DB_NAME, DB_VERSION);

    request.onupgradeneeded = (event: IDBVersionChangeEvent) => {
      const db = (event.target as IDBOpenDBRequest).result;
      if (!db.objectStoreNames.contains(STORES.SALES)) {
        db.createObjectStore(STORES.SALES, { keyPath: 'id' });
      }
      if (!db.objectStoreNames.contains(STORES.TRANSACTIONS)) {
        db.createObjectStore(STORES.TRANSACTIONS, { keyPath: 'id' });
      }
      if (!db.objectStoreNames.contains(STORES.PRODUCTS)) {
        db.createObjectStore(STORES.PRODUCTS, { keyPath: 'id' });
      }
      if (!db.objectStoreNames.contains(STORES.PURCHASES)) {
        db.createObjectStore(STORES.PURCHASES, { keyPath: 'id' });
      }
      if (!db.objectStoreNames.contains(STORES.SUPPLIERS)) {
        db.createObjectStore(STORES.SUPPLIERS, { keyPath: 'id' });
      }
      if (!db.objectStoreNames.contains(STORES.DAILY_BALANCES)) {
        db.createObjectStore(STORES.DAILY_BALANCES, { keyPath: 'date' });
      }
      if (!db.objectStoreNames.contains(STORES.SNAPSHOTS)) {
        db.createObjectStore(STORES.SNAPSHOTS, { keyPath: 'id' });
      }
    };

    request.onsuccess = () => resolve(request.result);
    request.onerror = () => reject(request.error);
  });
}

// Generic put / save
async function putItem<T>(storeName: string, item: T): Promise<void> {
  try {
    const db = await openVaultDB();
    const tx = db.transaction(storeName, 'readwrite');
    tx.objectStore(storeName).put(item);
    return new Promise((resolve, reject) => {
      tx.oncomplete = () => resolve();
      tx.onerror = () => reject(tx.error);
    });
  } catch (err) {
    console.warn(`[Vault] Error writing to ${storeName}:`, err);
  }
}

// Generic batch put
async function putAllItems<T>(storeName: string, items: T[]): Promise<void> {
  if (!items || items.length === 0) return;
  try {
    const db = await openVaultDB();
    const tx = db.transaction(storeName, 'readwrite');
    const store = tx.objectStore(storeName);
    items.forEach((item) => store.put(item));
    return new Promise((resolve, reject) => {
      tx.oncomplete = () => resolve();
      tx.onerror = () => reject(tx.error);
    });
  } catch (err) {
    console.warn(`[Vault] Error writing batch to ${storeName}:`, err);
  }
}

// Generic getAll
async function getAllItems<T>(storeName: string): Promise<T[]> {
  try {
    const db = await openVaultDB();
    const tx = db.transaction(storeName, 'readonly');
    const request = tx.objectStore(storeName).getAll();
    return new Promise((resolve) => {
      request.onsuccess = () => resolve((request.result as T[]) || []);
      request.onerror = () => resolve([]);
    });
  } catch (err) {
    console.warn(`[Vault] Error reading from ${storeName}:`, err);
    return [];
  }
}

// Product Sales Vault
export async function vaultSaveSale(sale: ProductSale): Promise<void> {
  await putItem(STORES.SALES, sale);
}

export async function vaultSaveAllSales(sales: ProductSale[]): Promise<void> {
  await putAllItems(STORES.SALES, sales);
}

export async function vaultGetAllSales(): Promise<ProductSale[]> {
  return await getAllItems<ProductSale>(STORES.SALES);
}

// Transactions Vault
export async function vaultSaveTransaction(trx: Transaction): Promise<void> {
  await putItem(STORES.TRANSACTIONS, trx);
}

export async function vaultSaveAllTransactions(trxList: Transaction[]): Promise<void> {
  await putAllItems(STORES.TRANSACTIONS, trxList);
}

export async function vaultGetAllTransactions(): Promise<Transaction[]> {
  return await getAllItems<Transaction>(STORES.TRANSACTIONS);
}

// Products Vault
export async function vaultSaveProduct(prod: Product): Promise<void> {
  await putItem(STORES.PRODUCTS, prod);
}

export async function vaultSaveAllProducts(products: Product[]): Promise<void> {
  await putAllItems(STORES.PRODUCTS, products);
}

export async function vaultGetAllProducts(): Promise<Product[]> {
  return await getAllItems<Product>(STORES.PRODUCTS);
}

// Mobile Purchases Vault
export async function vaultSavePurchase(purchase: MobilePurchaseRecord): Promise<void> {
  await putItem(STORES.PURCHASES, purchase);
}

export async function vaultSaveAllPurchases(purchases: MobilePurchaseRecord[]): Promise<void> {
  await putAllItems(STORES.PURCHASES, purchases);
}

export async function vaultGetAllPurchases(): Promise<MobilePurchaseRecord[]> {
  return await getAllItems<MobilePurchaseRecord>(STORES.PURCHASES);
}

// Suppliers Vault
export async function vaultSaveSupplier(supplier: Supplier): Promise<void> {
  await putItem(STORES.SUPPLIERS, supplier);
}

export async function vaultSaveAllSuppliers(suppliers: Supplier[]): Promise<void> {
  await putAllItems(STORES.SUPPLIERS, suppliers);
}

export async function vaultGetAllSuppliers(): Promise<Supplier[]> {
  return await getAllItems<Supplier>(STORES.SUPPLIERS);
}

// Daily Balances Vault
export async function vaultSaveDailyBalance(balance: DailyBalance): Promise<void> {
  await putItem(STORES.DAILY_BALANCES, balance);
}

export async function vaultSaveAllDailyBalances(balances: DailyBalance[]): Promise<void> {
  await putAllItems(STORES.DAILY_BALANCES, balances);
}

export async function vaultGetAllDailyBalances(): Promise<DailyBalance[]> {
  return await getAllItems<DailyBalance>(STORES.DAILY_BALANCES);
}

// Periodic Full Snapshot in IndexedDB (Saved automatically every 10 seconds or on sale)
export async function vaultSaveEmergencySnapshot(data: {
  transactions: Transaction[];
  products: Product[];
  productSales: ProductSale[];
  mobilePurchases: MobilePurchaseRecord[];
  suppliers: Supplier[];
  dailyBalances: Record<string, DailyBalance>;
  settings: AppSettings;
}): Promise<void> {
  try {
    const snapshotItem = {
      id: 'latest_snapshot',
      timestamp: Date.now(),
      dateStr: new Date().toISOString(),
      ...data,
      dailyBalancesList: Object.values(data.dailyBalances),
    };
    await putItem(STORES.SNAPSHOTS, snapshotItem);

    // Also update individual object stores in parallel
    if (data.productSales && data.productSales.length > 0) {
      await vaultSaveAllSales(data.productSales);
    }
    if (data.transactions && data.transactions.length > 0) {
      await vaultSaveAllTransactions(data.transactions);
    }
    if (data.products && data.products.length > 0) {
      await vaultSaveAllProducts(data.products);
    }
    if (data.mobilePurchases && data.mobilePurchases.length > 0) {
      await vaultSaveAllPurchases(data.mobilePurchases);
    }
    if (data.suppliers && data.suppliers.length > 0) {
      await vaultSaveAllSuppliers(data.suppliers);
    }
  } catch (err) {
    console.warn('[Vault] Emergency snapshot failed:', err);
  }
}

/**
 * Emergency Deep Scanner & Recovery:
 * Scans all browser persistence layers:
 * 1. IndexedDB Vault
 * 2. LocalStorage keys
 * 3. Offline Pending Sync Queue
 * Merges and returns the most complete dataset without losing any record.
 */
export async function emergencyScanAndRecoverAll(): Promise<{
  recoveredSales: ProductSale[];
  recoveredTransactions: Transaction[];
  recoveredProducts: Product[];
  recoveredPurchases: MobilePurchaseRecord[];
  recoveredSuppliers: Supplier[];
  count: number;
}> {
  console.log('[EmergencyRecovery] Starting deep storage scan...');
  
  // 1. Gather all sales from IndexedDB
  const vaultSales = await vaultGetAllSales();
  
  // 2. Gather sales from LocalStorage
  let localSales: ProductSale[] = [];
  try {
    const raw = localStorage.getItem('ep_ledger_product_sales_v1');
    if (raw) localSales = JSON.parse(raw);
  } catch {}

  // 3. Gather sales from Pending Sync Queue
  let queueSales: ProductSale[] = [];
  try {
    const raw = localStorage.getItem('balal_mobiles_pending_sync_queue_v1');
    if (raw) {
      const queue = JSON.parse(raw);
      if (Array.isArray(queue)) {
        queue.forEach((item: any) => {
          if (item.action === 'SAVE_SALE' && item.data) {
            queueSales.push(item.data);
          }
        });
      }
    }
  } catch {}

  // Merge sales by ID
  const salesMap = new Map<string, ProductSale>();
  [...localSales, ...vaultSales, ...queueSales].forEach((s) => {
    if (s && s.id) salesMap.set(s.id, s);
  });
  const allMergedSales = Array.from(salesMap.values()).sort((a, b) => (b.createdAt || 0) - (a.createdAt || 0));

  // Same for Transactions
  const vaultTrx = await vaultGetAllTransactions();
  let localTrx: Transaction[] = [];
  try {
    const raw = localStorage.getItem('ep_ledger_transactions_v1');
    if (raw) localTrx = JSON.parse(raw);
  } catch {}

  let queueTrx: Transaction[] = [];
  try {
    const raw = localStorage.getItem('balal_mobiles_pending_sync_queue_v1');
    if (raw) {
      const queue = JSON.parse(raw);
      if (Array.isArray(queue)) {
        queue.forEach((item: any) => {
          if (item.action === 'SAVE_TRANSACTION' && item.data) {
            queueTrx.push(item.data);
          }
        });
      }
    }
  } catch {}

  const trxMap = new Map<string, Transaction>();
  [...localTrx, ...vaultTrx, ...queueTrx].forEach((t) => {
    if (t && t.id) trxMap.set(t.id, t);
  });
  const allMergedTrx = Array.from(trxMap.values()).sort((a, b) => (b.createdAt || 0) - (a.createdAt || 0));

  // Same for Products
  const vaultProds = await vaultGetAllProducts();
  let localProds: Product[] = [];
  try {
    const raw = localStorage.getItem('ep_ledger_products_v1');
    if (raw) localProds = JSON.parse(raw);
  } catch {}

  const prodMap = new Map<string, Product>();
  [...localProds, ...vaultProds].forEach((p) => {
    if (p && p.id) prodMap.set(p.id, p);
  });
  const allMergedProds = Array.from(prodMap.values());

  // Same for Purchases
  const vaultPurchases = await vaultGetAllPurchases();
  let localPurchases: MobilePurchaseRecord[] = [];
  try {
    const raw = localStorage.getItem('ep_ledger_mobile_purchases_v1');
    if (raw) localPurchases = JSON.parse(raw);
  } catch {}

  const purMap = new Map<string, MobilePurchaseRecord>();
  [...localPurchases, ...vaultPurchases].forEach((p) => {
    if (p && p.id) purMap.set(p.id, p);
  });
  const allMergedPurchases = Array.from(purMap.values());

  // Same for Suppliers
  const vaultSuppliers = await vaultGetAllSuppliers();
  let localSuppliers: Supplier[] = [];
  try {
    const raw = localStorage.getItem('balal_mobiles_suppliers');
    if (raw) localSuppliers = JSON.parse(raw);
  } catch {}

  const supMap = new Map<string, Supplier>();
  [...localSuppliers, ...vaultSuppliers].forEach((s) => {
    if (s && s.id) supMap.set(s.id, s);
  });
  const allMergedSuppliers = Array.from(supMap.values());

  // Re-write to LocalStorage to restore missing records
  if (allMergedSales.length > localSales.length) {
    try {
      localStorage.setItem('ep_ledger_product_sales_v1', JSON.stringify(allMergedSales));
    } catch {}
  }
  if (allMergedTrx.length > localTrx.length) {
    try {
      localStorage.setItem('ep_ledger_transactions_v1', JSON.stringify(allMergedTrx));
    } catch {}
  }
  if (allMergedProds.length > localProds.length) {
    try {
      localStorage.setItem('ep_ledger_products_v1', JSON.stringify(allMergedProds));
    } catch {}
  }

  const totalCount = allMergedSales.length + allMergedTrx.length + allMergedProds.length;
  console.log(`[EmergencyRecovery] Scan complete! Found: ${allMergedSales.length} sales, ${allMergedTrx.length} transactions, ${allMergedProds.length} products.`);

  return {
    recoveredSales: allMergedSales,
    recoveredTransactions: allMergedTrx,
    recoveredProducts: allMergedProds,
    recoveredPurchases: allMergedPurchases,
    recoveredSuppliers: allMergedSuppliers,
    count: totalCount,
  };
}
