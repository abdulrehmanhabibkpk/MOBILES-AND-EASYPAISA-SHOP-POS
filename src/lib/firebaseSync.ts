import { 
  collection, 
  doc, 
  setDoc, 
  deleteDoc, 
  onSnapshot,
  query,
  orderBy
} from 'firebase/firestore';
import { db, handleFirestoreError, OperationType } from './firebase';
import { Product, ProductSale, Transaction, DailyBalance, AppSettings, MobilePurchaseRecord } from '../types';

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

// 1. PRODUCTS
export function subscribeProducts(
  onUpdate: (products: Product[]) => void,
  onError?: (err: any) => void
) {
  const path = 'products';
  try {
    const q = query(collection(db, path));
    return onSnapshot(q, (snapshot) => {
      const products: Product[] = [];
      snapshot.forEach((docSnap) => {
        products.push(docSnap.data() as Product);
      });
      onUpdate(products);
    }, (error) => {
      if (onError) onError(error);
      handleFirestoreError(error, OperationType.GET, path);
    });
  } catch (error) {
    handleFirestoreError(error, OperationType.GET, path);
    return () => {};
  }
}

export async function saveProductToCloud(product: Product) {
  const path = `products/${product.id}`;
  try {
    await setDoc(doc(db, 'products', product.id), cleanPayload(product), { merge: true });
  } catch (error) {
    handleFirestoreError(error, OperationType.WRITE, path);
  }
}

export async function deleteProductFromCloud(productId: string) {
  const path = `products/${productId}`;
  try {
    await deleteDoc(doc(db, 'products', productId));
  } catch (error) {
    handleFirestoreError(error, OperationType.DELETE, path);
  }
}

// 2. PRODUCT SALES
export function subscribeProductSales(
  onUpdate: (sales: ProductSale[]) => void,
  onError?: (err: any) => void
) {
  const path = 'productSales';
  try {
    const q = query(collection(db, path));
    return onSnapshot(q, (snapshot) => {
      const sales: ProductSale[] = [];
      snapshot.forEach((docSnap) => {
        sales.push(docSnap.data() as ProductSale);
      });
      // Sort newest first
      sales.sort((a, b) => b.createdAt - a.createdAt);
      onUpdate(sales);
    }, (error) => {
      if (onError) onError(error);
      handleFirestoreError(error, OperationType.GET, path);
    });
  } catch (error) {
    handleFirestoreError(error, OperationType.GET, path);
    return () => {};
  }
}

export async function saveProductSaleToCloud(sale: ProductSale) {
  const path = `productSales/${sale.id}`;
  try {
    await setDoc(doc(db, 'productSales', sale.id), cleanPayload(sale), { merge: true });
  } catch (error) {
    handleFirestoreError(error, OperationType.WRITE, path);
  }
}

// 3. TRANSACTIONS
export function subscribeTransactions(
  onUpdate: (transactions: Transaction[]) => void,
  onError?: (err: any) => void
) {
  const path = 'transactions';
  try {
    const q = query(collection(db, path));
    return onSnapshot(q, (snapshot) => {
      const list: Transaction[] = [];
      snapshot.forEach((docSnap) => {
        list.push(docSnap.data() as Transaction);
      });
      list.sort((a, b) => b.createdAt - a.createdAt);
      onUpdate(list);
    }, (error) => {
      if (onError) onError(error);
      handleFirestoreError(error, OperationType.GET, path);
    });
  } catch (error) {
    handleFirestoreError(error, OperationType.GET, path);
    return () => {};
  }
}

export async function saveTransactionToCloud(trx: Transaction) {
  const path = `transactions/${trx.id}`;
  try {
    await setDoc(doc(db, 'transactions', trx.id), cleanPayload(trx), { merge: true });
  } catch (error) {
    handleFirestoreError(error, OperationType.WRITE, path);
  }
}

export async function deleteTransactionFromCloud(trxId: string) {
  const path = `transactions/${trxId}`;
  try {
    await deleteDoc(doc(db, 'transactions', trxId));
  } catch (error) {
    handleFirestoreError(error, OperationType.DELETE, path);
  }
}

// 4. DAILY BALANCES
export function subscribeDailyBalances(
  onUpdate: (balances: Record<string, DailyBalance>) => void,
  onError?: (err: any) => void
) {
  const path = 'dailyBalances';
  try {
    const q = query(collection(db, path));
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
      handleFirestoreError(error, OperationType.GET, path);
    });
  } catch (error) {
    handleFirestoreError(error, OperationType.GET, path);
    return () => {};
  }
}

export async function saveDailyBalanceToCloud(balance: DailyBalance) {
  const path = `dailyBalances/${balance.date}`;
  try {
    await setDoc(doc(db, 'dailyBalances', balance.date), cleanPayload(balance), { merge: true });
  } catch (error) {
    handleFirestoreError(error, OperationType.WRITE, path);
  }
}

// 5. APP SETTINGS
export function subscribeAppSettings(
  onUpdate: (settings: AppSettings) => void,
  onError?: (err: any) => void
) {
  const path = 'appSettings/global';
  try {
    return onSnapshot(doc(db, 'appSettings', 'global'), (docSnap) => {
      if (docSnap.exists()) {
        onUpdate(docSnap.data() as AppSettings);
      }
    }, (error) => {
      if (onError) onError(error);
      handleFirestoreError(error, OperationType.GET, path);
    });
  } catch (error) {
    handleFirestoreError(error, OperationType.GET, path);
    return () => {};
  }
}

export async function saveAppSettingsToCloud(settings: AppSettings) {
  const path = 'appSettings/global';
  try {
    await setDoc(doc(db, 'appSettings', 'global'), cleanPayload(settings), { merge: true });
  } catch (error) {
    handleFirestoreError(error, OperationType.WRITE, path);
  }
}

// 6. MOBILE PURCHASES
export function subscribeMobilePurchases(
  onUpdate: (purchases: MobilePurchaseRecord[]) => void,
  onError?: (err: any) => void
) {
  const path = 'mobilePurchases';
  try {
    const q = query(collection(db, path));
    return onSnapshot(q, (snapshot) => {
      const purchases: MobilePurchaseRecord[] = [];
      snapshot.forEach((docSnap) => {
        purchases.push(docSnap.data() as MobilePurchaseRecord);
      });
      onUpdate(purchases);
    }, (error) => {
      if (onError) onError(error);
      handleFirestoreError(error, OperationType.GET, path);
    });
  } catch (error) {
    handleFirestoreError(error, OperationType.GET, path);
    return () => {};
  }
}

export async function saveMobilePurchaseToCloud(purchase: MobilePurchaseRecord) {
  const path = `mobilePurchases/${purchase.id}`;
  try {
    await setDoc(doc(db, 'mobilePurchases', purchase.id), cleanPayload(purchase), { merge: true });
  } catch (error) {
    handleFirestoreError(error, OperationType.WRITE, path);
  }
}

export async function deleteMobilePurchaseFromCloud(purchaseId: string) {
  const path = `mobilePurchases/${purchaseId}`;
  try {
    await deleteDoc(doc(db, 'mobilePurchases', purchaseId));
  } catch (error) {
    handleFirestoreError(error, OperationType.DELETE, path);
  }
}
