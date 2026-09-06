import { 
  collection, 
  doc, 
  setDoc, 
  deleteDoc, 
  onSnapshot,
  query,
  limit,
  writeBatch
} from 'firebase/firestore';
import { db, handleFirestoreError, OperationType, isQuotaExceeded } from './firebase';
import { enqueuePendingSync } from './offlineSyncManager';
import { Product, ProductSale, Transaction, DailyBalance, AppSettings, MobilePurchaseRecord, Supplier } from '../types';

// Helper to sanitize objects for Firestore (ensure no undefined fields)
function cleanPayload<T extends Record<string, any>>(obj: T): T {
  const result: any = {};
  for (const key of Object.keys(obj)) {
    if (obj[key] !== undefined) {
      result[key] = obj[key];
    }
  }
  return result;
}

const SHOP_PATH = 'shops/mainShop';

// 1. PRODUCTS
export function subscribeProducts(
  onUpdate: (products: Product[]) => void,
  onError?: (err: any) => void
) {
  const colPath = `${SHOP_PATH}/products`;
  try {
    const q = query(collection(db, colPath), limit(1000));
    return onSnapshot(q, (snapshot) => {
      const products: Product[] = [];
      snapshot.forEach((docSnap) => {
        products.push(docSnap.data() as Product);
      });
      onUpdate(products);
    }, (error) => {
      if (onError) onError(error);
      handleFirestoreError(error, OperationType.GET, colPath);
    });
  } catch (error) {
    handleFirestoreError(error, OperationType.GET, colPath);
    return () => {};
  }
}

export async function saveProductToCloud(product: Product) {
  const docPath = `${SHOP_PATH}/products/${product.id}`;
  try {
    if (isQuotaExceeded) {
      enqueuePendingSync('SAVE_PRODUCT', product.id, product);
      return;
    }
    await setDoc(doc(db, SHOP_PATH, 'products', product.id), cleanPayload(product), { merge: true });
  } catch (error) {
    enqueuePendingSync('SAVE_PRODUCT', product.id, product);
    handleFirestoreError(error, OperationType.WRITE, docPath);
  }
}

export async function batchSaveProductsToCloud(products: Product[]) {
  if (!products || products.length === 0) return;
  try {
    if (isQuotaExceeded) {
      products.forEach(prod => enqueuePendingSync('SAVE_PRODUCT', prod.id, prod));
      return;
    }
    // Firestore batch limit is 500 ops per commit
    const chunkSize = 400;
    for (let i = 0; i < products.length; i += chunkSize) {
      const chunk = products.slice(i, i + chunkSize);
      const batch = writeBatch(db);
      chunk.forEach((prod) => {
        const ref = doc(db, SHOP_PATH, 'products', prod.id);
        batch.set(ref, cleanPayload(prod), { merge: true });
      });
      await batch.commit();
    }
  } catch (error) {
    products.forEach(prod => enqueuePendingSync('SAVE_PRODUCT', prod.id, prod));
    handleFirestoreError(error, OperationType.WRITE, `${SHOP_PATH}/products`);
  }
}

export async function deleteProductFromCloud(productId: string) {
  const docPath = `${SHOP_PATH}/products/${productId}`;
  try {
    if (isQuotaExceeded) {
      enqueuePendingSync('DELETE_PRODUCT', productId);
      return;
    }
    await deleteDoc(doc(db, SHOP_PATH, 'products', productId));
  } catch (error) {
    enqueuePendingSync('DELETE_PRODUCT', productId);
    handleFirestoreError(error, OperationType.DELETE, docPath);
  }
}

// 2. PRODUCT SALES
export function subscribeProductSales(
  onUpdate: (sales: ProductSale[]) => void,
  onError?: (err: any) => void
) {
  const colPath = `${SHOP_PATH}/productSales`;
  try {
    const q = query(collection(db, colPath), limit(150));
    return onSnapshot(q, (snapshot) => {
      const sales: ProductSale[] = [];
      snapshot.forEach((docSnap) => {
        sales.push(docSnap.data() as ProductSale);
      });
      sales.sort((a, b) => b.createdAt - a.createdAt);
      onUpdate(sales);
    }, (error) => {
      if (onError) onError(error);
      handleFirestoreError(error, OperationType.GET, colPath);
    });
  } catch (error) {
    handleFirestoreError(error, OperationType.GET, colPath);
    return () => {};
  }
}

export async function saveProductSaleToCloud(sale: ProductSale) {
  const docPath = `${SHOP_PATH}/productSales/${sale.id}`;
  try {
    if (isQuotaExceeded) {
      enqueuePendingSync('SAVE_SALE', sale.id, sale);
      return;
    }
    await setDoc(doc(db, SHOP_PATH, 'productSales', sale.id), cleanPayload(sale), { merge: true });
  } catch (error) {
    enqueuePendingSync('SAVE_SALE', sale.id, sale);
    handleFirestoreError(error, OperationType.WRITE, docPath);
  }
}

// 3. TRANSACTIONS
export function subscribeTransactions(
  onUpdate: (transactions: Transaction[]) => void,
  onError?: (err: any) => void
) {
  const colPath = `${SHOP_PATH}/transactions`;
  try {
    const q = query(collection(db, colPath), limit(150));
    return onSnapshot(q, (snapshot) => {
      const list: Transaction[] = [];
      snapshot.forEach((docSnap) => {
        list.push(docSnap.data() as Transaction);
      });
      list.sort((a, b) => b.createdAt - a.createdAt);
      onUpdate(list);
    }, (error) => {
      if (onError) onError(error);
      handleFirestoreError(error, OperationType.GET, colPath);
    });
  } catch (error) {
    handleFirestoreError(error, OperationType.GET, colPath);
    return () => {};
  }
}

export async function saveTransactionToCloud(trx: Transaction) {
  const docPath = `${SHOP_PATH}/transactions/${trx.id}`;
  try {
    if (isQuotaExceeded) {
      enqueuePendingSync('SAVE_TRANSACTION', trx.id, trx);
      return;
    }
    await setDoc(doc(db, SHOP_PATH, 'transactions', trx.id), cleanPayload(trx), { merge: true });
  } catch (error) {
    enqueuePendingSync('SAVE_TRANSACTION', trx.id, trx);
    handleFirestoreError(error, OperationType.WRITE, docPath);
  }
}

export async function deleteTransactionFromCloud(trxId: string) {
  const docPath = `${SHOP_PATH}/transactions/${trxId}`;
  try {
    if (isQuotaExceeded) {
      enqueuePendingSync('DELETE_TRANSACTION', trxId);
      return;
    }
    await deleteDoc(doc(db, SHOP_PATH, 'transactions', trxId));
  } catch (error) {
    enqueuePendingSync('DELETE_TRANSACTION', trxId);
    handleFirestoreError(error, OperationType.DELETE, docPath);
  }
}

// 4. DAILY BALANCES
export function subscribeDailyBalances(
  onUpdate: (balances: Record<string, DailyBalance>) => void,
  onError?: (err: any) => void
) {
  const colPath = `${SHOP_PATH}/dailyBalances`;
  try {
    const q = query(collection(db, colPath), limit(60));
    return onSnapshot(q, (snapshot) => {
      const map: Record<string, DailyBalance> = {};
      snapshot.forEach((docSnap) => {
        const data = docSnap.data() as DailyBalance;
        if (data && data.date) {
          map[data.date] = data;
        }
      });
      onUpdate(map);
    }, (error) => {
      if (onError) onError(error);
      handleFirestoreError(error, OperationType.GET, colPath);
    });
  } catch (error) {
    handleFirestoreError(error, OperationType.GET, colPath);
    return () => {};
  }
}

export async function saveDailyBalanceToCloud(balance: DailyBalance) {
  const docPath = `${SHOP_PATH}/dailyBalances/${balance.date}`;
  try {
    if (isQuotaExceeded) {
      enqueuePendingSync('SAVE_BALANCE', balance.date, balance);
      return;
    }
    await setDoc(doc(db, SHOP_PATH, 'dailyBalances', balance.date), cleanPayload(balance), { merge: true });
  } catch (error) {
    enqueuePendingSync('SAVE_BALANCE', balance.date, balance);
    handleFirestoreError(error, OperationType.WRITE, docPath);
  }
}

// 5. APP SETTINGS
export function subscribeAppSettings(
  onUpdate: (settings: AppSettings) => void,
  onError?: (err: any) => void
) {
  const docPath = `${SHOP_PATH}/appSettings/global`;
  try {
    return onSnapshot(doc(db, SHOP_PATH, 'appSettings', 'global'), (docSnap) => {
      if (docSnap.exists()) {
        onUpdate(docSnap.data() as AppSettings);
      }
    }, (error) => {
      if (onError) onError(error);
      handleFirestoreError(error, OperationType.GET, docPath);
    });
  } catch (error) {
    handleFirestoreError(error, OperationType.GET, docPath);
    return () => {};
  }
}

export async function saveAppSettingsToCloud(settings: AppSettings) {
  const docPath = `${SHOP_PATH}/appSettings/global`;
  try {
    if (isQuotaExceeded) {
      enqueuePendingSync('SAVE_SETTINGS', 'global', settings);
      return;
    }
    await setDoc(doc(db, SHOP_PATH, 'appSettings', 'global'), cleanPayload(settings), { merge: true });
  } catch (error) {
    enqueuePendingSync('SAVE_SETTINGS', 'global', settings);
    handleFirestoreError(error, OperationType.WRITE, docPath);
  }
}

// 6. MOBILE PURCHASES
export function subscribeMobilePurchases(
  onUpdate: (purchases: MobilePurchaseRecord[]) => void,
  onError?: (err: any) => void
) {
  const colPath = `${SHOP_PATH}/mobilePurchases`;
  try {
    const q = query(collection(db, colPath), limit(150));
    return onSnapshot(q, (snapshot) => {
      const purchases: MobilePurchaseRecord[] = [];
      snapshot.forEach((docSnap) => {
        purchases.push(docSnap.data() as MobilePurchaseRecord);
      });
      onUpdate(purchases);
    }, (error) => {
      if (onError) onError(error);
      handleFirestoreError(error, OperationType.GET, colPath);
    });
  } catch (error) {
    handleFirestoreError(error, OperationType.GET, colPath);
    return () => {};
  }
}

export async function saveMobilePurchaseToCloud(purchase: MobilePurchaseRecord) {
  const docPath = `${SHOP_PATH}/mobilePurchases/${purchase.id}`;
  try {
    if (isQuotaExceeded) {
      enqueuePendingSync('SAVE_PURCHASE', purchase.id, purchase);
      return;
    }
    await setDoc(doc(db, SHOP_PATH, 'mobilePurchases', purchase.id), cleanPayload(purchase), { merge: true });
  } catch (error) {
    enqueuePendingSync('SAVE_PURCHASE', purchase.id, purchase);
    handleFirestoreError(error, OperationType.WRITE, docPath);
  }
}

export async function deleteMobilePurchaseFromCloud(purchaseId: string) {
  const docPath = `${SHOP_PATH}/mobilePurchases/${purchaseId}`;
  try {
    if (isQuotaExceeded) {
      enqueuePendingSync('DELETE_PURCHASE', purchaseId);
      return;
    }
    await deleteDoc(doc(db, SHOP_PATH, 'mobilePurchases', purchaseId));
  } catch (error) {
    enqueuePendingSync('DELETE_PURCHASE', purchaseId);
    handleFirestoreError(error, OperationType.DELETE, docPath);
  }
}

// 7. SUPPLIERS
export function subscribeSuppliers(
  onUpdate: (suppliers: Supplier[]) => void,
  onError?: (err: any) => void
) {
  const colPath = `${SHOP_PATH}/suppliers`;
  try {
    const q = query(collection(db, colPath), limit(100));
    return onSnapshot(q, (snapshot) => {
      const suppliers: Supplier[] = [];
      snapshot.forEach((docSnap) => {
        suppliers.push(docSnap.data() as Supplier);
      });
      onUpdate(suppliers);
    }, (error) => {
      if (onError) onError(error);
      handleFirestoreError(error, OperationType.GET, colPath);
    });
  } catch (error) {
    handleFirestoreError(error, OperationType.GET, colPath);
    return () => {};
  }
}

export async function saveSupplierToCloud(supplier: Supplier) {
  const docPath = `${SHOP_PATH}/suppliers/${supplier.id}`;
  try {
    if (isQuotaExceeded) {
      enqueuePendingSync('SAVE_SUPPLIER', supplier.id, supplier);
      return;
    }
    await setDoc(doc(db, SHOP_PATH, 'suppliers', supplier.id), cleanPayload(supplier), { merge: true });
  } catch (error) {
    enqueuePendingSync('SAVE_SUPPLIER', supplier.id, supplier);
    handleFirestoreError(error, OperationType.WRITE, docPath);
  }
}

export async function deleteSupplierFromCloud(supplierId: string) {
  const docPath = `${SHOP_PATH}/suppliers/${supplierId}`;
  try {
    if (isQuotaExceeded) {
      enqueuePendingSync('DELETE_SUPPLIER', supplierId);
      return;
    }
    await deleteDoc(doc(db, SHOP_PATH, 'suppliers', supplierId));
  } catch (error) {
    enqueuePendingSync('DELETE_SUPPLIER', supplierId);
    handleFirestoreError(error, OperationType.DELETE, docPath);
  }
}
