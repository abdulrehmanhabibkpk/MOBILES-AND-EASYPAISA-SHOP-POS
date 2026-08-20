import { 
  collection, 
  doc, 
  setDoc, 
  deleteDoc, 
  onSnapshot,
  query
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
  userId: string,
  onUpdate: (products: Product[]) => void,
  onError?: (err: any) => void
) {
  if (!userId) return () => {};
  const colPath = `users/${userId}/products`;
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

export async function saveProductToCloud(userId: string, product: Product) {
  if (!userId) return;
  const docPath = `users/${userId}/products/${product.id}`;
  try {
    await setDoc(doc(db, 'users', userId, 'products', product.id), cleanPayload(product), { merge: true });
  } catch (error) {
    handleFirestoreError(error, OperationType.WRITE, docPath);
  }
}

export async function deleteProductFromCloud(userId: string, productId: string) {
  if (!userId) return;
  const docPath = `users/${userId}/products/${productId}`;
  try {
    await deleteDoc(doc(db, 'users', userId, 'products', productId));
  } catch (error) {
    handleFirestoreError(error, OperationType.DELETE, docPath);
  }
}

// 2. PRODUCT SALES
export function subscribeProductSales(
  userId: string,
  onUpdate: (sales: ProductSale[]) => void,
  onError?: (err: any) => void
) {
  if (!userId) return () => {};
  const colPath = `users/${userId}/productSales`;
  try {
    const q = query(collection(db, colPath));
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
      handleFirestoreError(error, OperationType.GET, colPath);
    });
  } catch (error) {
    handleFirestoreError(error, OperationType.GET, colPath);
    return () => {};
  }
}

export async function saveProductSaleToCloud(userId: string, sale: ProductSale) {
  if (!userId) return;
  const docPath = `users/${userId}/productSales/${sale.id}`;
  try {
    await setDoc(doc(db, 'users', userId, 'productSales', sale.id), cleanPayload(sale), { merge: true });
  } catch (error) {
    handleFirestoreError(error, OperationType.WRITE, docPath);
  }
}

// 3. TRANSACTIONS
export function subscribeTransactions(
  userId: string,
  onUpdate: (transactions: Transaction[]) => void,
  onError?: (err: any) => void
) {
  if (!userId) return () => {};
  const colPath = `users/${userId}/transactions`;
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

export async function saveTransactionToCloud(userId: string, trx: Transaction) {
  if (!userId) return;
  const docPath = `users/${userId}/transactions/${trx.id}`;
  try {
    await setDoc(doc(db, 'users', userId, 'transactions', trx.id), cleanPayload(trx), { merge: true });
  } catch (error) {
    handleFirestoreError(error, OperationType.WRITE, docPath);
  }
}

export async function deleteTransactionFromCloud(userId: string, trxId: string) {
  if (!userId) return;
  const docPath = `users/${userId}/transactions/${trxId}`;
  try {
    await deleteDoc(doc(db, 'users', userId, 'transactions', trxId));
  } catch (error) {
    handleFirestoreError(error, OperationType.DELETE, docPath);
  }
}

// 4. DAILY BALANCES
export function subscribeDailyBalances(
  userId: string,
  onUpdate: (balances: Record<string, DailyBalance>) => void,
  onError?: (err: any) => void
) {
  if (!userId) return () => {};
  const colPath = `users/${userId}/dailyBalances`;
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

export async function saveDailyBalanceToCloud(userId: string, balance: DailyBalance) {
  if (!userId) return;
  const docPath = `users/${userId}/dailyBalances/${balance.date}`;
  try {
    await setDoc(doc(db, 'users', userId, 'dailyBalances', balance.date), cleanPayload(balance), { merge: true });
  } catch (error) {
    handleFirestoreError(error, OperationType.WRITE, docPath);
  }
}

// 5. APP SETTINGS
export function subscribeAppSettings(
  userId: string,
  onUpdate: (settings: AppSettings) => void,
  onError?: (err: any) => void
) {
  if (!userId) return () => {};
  const docPath = `users/${userId}/appSettings/global`;
  try {
    return onSnapshot(doc(db, 'users', userId, 'appSettings', 'global'), (docSnap) => {
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

export async function saveAppSettingsToCloud(userId: string, settings: AppSettings) {
  if (!userId) return;
  const docPath = `users/${userId}/appSettings/global`;
  try {
    await setDoc(doc(db, 'users', userId, 'appSettings', 'global'), cleanPayload(settings), { merge: true });
  } catch (error) {
    handleFirestoreError(error, OperationType.WRITE, docPath);
  }
}

// 6. MOBILE PURCHASES
export function subscribeMobilePurchases(
  userId: string,
  onUpdate: (purchases: MobilePurchaseRecord[]) => void,
  onError?: (err: any) => void
) {
  if (!userId) return () => {};
  const colPath = `users/${userId}/mobilePurchases`;
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

export async function saveMobilePurchaseToCloud(userId: string, purchase: MobilePurchaseRecord) {
  if (!userId) return;
  const docPath = `users/${userId}/mobilePurchases/${purchase.id}`;
  try {
    await setDoc(doc(db, 'users', userId, 'mobilePurchases', purchase.id), cleanPayload(purchase), { merge: true });
  } catch (error) {
    handleFirestoreError(error, OperationType.WRITE, docPath);
  }
}

export async function deleteMobilePurchaseFromCloud(userId: string, purchaseId: string) {
  if (!userId) return;
  const docPath = `users/${userId}/mobilePurchases/${purchaseId}`;
  try {
    await deleteDoc(doc(db, 'users', userId, 'mobilePurchases', purchaseId));
  } catch (error) {
    handleFirestoreError(error, OperationType.DELETE, docPath);
  }
}
