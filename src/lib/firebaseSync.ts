import { 
  collection, 
  doc, 
  setDoc, 
  deleteDoc, 
  onSnapshot,
  query
} from 'firebase/firestore';
import { db, handleFirestoreError, OperationType, isQuotaExceeded } from './firebase';
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
  if (isQuotaExceeded) return () => {};
  const colPath = `${SHOP_PATH}/products`;
  try {
    const q = query(collection(db, colPath));
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
  if (isQuotaExceeded) return;
  const docPath = `${SHOP_PATH}/products/${product.id}`;
  try {
    await setDoc(doc(db, SHOP_PATH, 'products', product.id), cleanPayload(product), { merge: true });
  } catch (error) {
    handleFirestoreError(error, OperationType.WRITE, docPath);
  }
}

export async function deleteProductFromCloud(productId: string) {
  if (isQuotaExceeded) return;
  const docPath = `${SHOP_PATH}/products/${productId}`;
  try {
    await deleteDoc(doc(db, SHOP_PATH, 'products', productId));
  } catch (error) {
    handleFirestoreError(error, OperationType.DELETE, docPath);
  }
}

// 2. PRODUCT SALES
export function subscribeProductSales(
  onUpdate: (sales: ProductSale[]) => void,
  onError?: (err: any) => void
) {
  if (isQuotaExceeded) return () => {};
  const colPath = `${SHOP_PATH}/productSales`;
  try {
    const q = query(collection(db, colPath));
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
  if (isQuotaExceeded) return;
  const docPath = `${SHOP_PATH}/productSales/${sale.id}`;
  try {
    await setDoc(doc(db, SHOP_PATH, 'productSales', sale.id), cleanPayload(sale), { merge: true });
  } catch (error) {
    handleFirestoreError(error, OperationType.WRITE, docPath);
  }
}

// 3. TRANSACTIONS
export function subscribeTransactions(
  onUpdate: (transactions: Transaction[]) => void,
  onError?: (err: any) => void
) {
  if (isQuotaExceeded) return () => {};
  const colPath = `${SHOP_PATH}/transactions`;
  try {
    const q = query(collection(db, colPath));
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
  if (isQuotaExceeded) return;
  const docPath = `${SHOP_PATH}/transactions/${trx.id}`;
  try {
    await setDoc(doc(db, SHOP_PATH, 'transactions', trx.id), cleanPayload(trx), { merge: true });
  } catch (error) {
    handleFirestoreError(error, OperationType.WRITE, docPath);
  }
}

export async function deleteTransactionFromCloud(trxId: string) {
  if (isQuotaExceeded) return;
  const docPath = `${SHOP_PATH}/transactions/${trxId}`;
  try {
    await deleteDoc(doc(db, SHOP_PATH, 'transactions', trxId));
  } catch (error) {
    handleFirestoreError(error, OperationType.DELETE, docPath);
  }
}

// 4. DAILY BALANCES
export function subscribeDailyBalances(
  onUpdate: (balances: Record<string, DailyBalance>) => void,
  onError?: (err: any) => void
) {
  if (isQuotaExceeded) return () => {};
  const colPath = `${SHOP_PATH}/dailyBalances`;
  try {
    const q = query(collection(db, colPath));
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
  if (isQuotaExceeded) return;
  const docPath = `${SHOP_PATH}/dailyBalances/${balance.date}`;
  try {
    await setDoc(doc(db, SHOP_PATH, 'dailyBalances', balance.date), cleanPayload(balance), { merge: true });
  } catch (error) {
    handleFirestoreError(error, OperationType.WRITE, docPath);
  }
}

// 5. APP SETTINGS
export function subscribeAppSettings(
  onUpdate: (settings: AppSettings) => void,
  onError?: (err: any) => void
) {
  if (isQuotaExceeded) return () => {};
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
  if (isQuotaExceeded) return;
  const docPath = `${SHOP_PATH}/appSettings/global`;
  try {
    await setDoc(doc(db, SHOP_PATH, 'appSettings', 'global'), cleanPayload(settings), { merge: true });
  } catch (error) {
    handleFirestoreError(error, OperationType.WRITE, docPath);
  }
}

// 6. MOBILE PURCHASES
export function subscribeMobilePurchases(
  onUpdate: (purchases: MobilePurchaseRecord[]) => void,
  onError?: (err: any) => void
) {
  if (isQuotaExceeded) return () => {};
  const colPath = `${SHOP_PATH}/mobilePurchases`;
  try {
    const q = query(collection(db, colPath));
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
  if (isQuotaExceeded) return;
  const docPath = `${SHOP_PATH}/mobilePurchases/${purchase.id}`;
  try {
    await setDoc(doc(db, SHOP_PATH, 'mobilePurchases', purchase.id), cleanPayload(purchase), { merge: true });
  } catch (error) {
    handleFirestoreError(error, OperationType.WRITE, docPath);
  }
}

export async function deleteMobilePurchaseFromCloud(purchaseId: string) {
  if (isQuotaExceeded) return;
  const docPath = `${SHOP_PATH}/mobilePurchases/${purchaseId}`;
  try {
    await deleteDoc(doc(db, SHOP_PATH, 'mobilePurchases', purchaseId));
  } catch (error) {
    handleFirestoreError(error, OperationType.DELETE, docPath);
  }
}

// 7. SUPPLIERS
export function subscribeSuppliers(
  onUpdate: (suppliers: Supplier[]) => void,
  onError?: (err: any) => void
) {
  if (isQuotaExceeded) return () => {};
  const colPath = `${SHOP_PATH}/suppliers`;
  try {
    const q = query(collection(db, colPath));
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
  if (isQuotaExceeded) return;
  const docPath = `${SHOP_PATH}/suppliers/${supplier.id}`;
  try {
    await setDoc(doc(db, SHOP_PATH, 'suppliers', supplier.id), cleanPayload(supplier), { merge: true });
  } catch (error) {
    handleFirestoreError(error, OperationType.WRITE, docPath);
  }
}

export async function deleteSupplierFromCloud(supplierId: string) {
  if (isQuotaExceeded) return;
  const docPath = `${SHOP_PATH}/suppliers/${supplierId}`;
  try {
    await deleteDoc(doc(db, SHOP_PATH, 'suppliers', supplierId));
  } catch (error) {
    handleFirestoreError(error, OperationType.DELETE, docPath);
  }
}
