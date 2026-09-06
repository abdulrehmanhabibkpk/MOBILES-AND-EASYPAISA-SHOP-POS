import { db, isQuotaExceeded, onQuotaStatusChange } from './firebase';
import { 
  doc, 
  setDoc, 
  deleteDoc, 
  writeBatch 
} from 'firebase/firestore';
import { Product, ProductSale, Transaction, DailyBalance, AppSettings, MobilePurchaseRecord, Supplier } from '../types';

export type SyncActionType = 
  | 'SAVE_PRODUCT'
  | 'DELETE_PRODUCT'
  | 'SAVE_TRANSACTION'
  | 'DELETE_TRANSACTION'
  | 'SAVE_SALE'
  | 'SAVE_BALANCE'
  | 'SAVE_PURCHASE'
  | 'DELETE_PURCHASE'
  | 'SAVE_SUPPLIER'
  | 'DELETE_SUPPLIER'
  | 'SAVE_SETTINGS';

export interface PendingSyncItem {
  id: string;
  action: SyncActionType;
  entityId: string;
  data?: any;
  timestamp: number;
  retryCount: number;
}

const QUEUE_STORAGE_KEY = 'balal_mobiles_pending_sync_queue_v1';
const SHOP_PATH = 'shops/mainShop';

let queueListeners: Array<(count: number, items: PendingSyncItem[]) => void> = [];

export function getPendingQueue(): PendingSyncItem[] {
  try {
    const raw = localStorage.getItem(QUEUE_STORAGE_KEY);
    return raw ? JSON.parse(raw) : [];
  } catch (err) {
    console.error('Error reading pending sync queue:', err);
    return [];
  }
}

export function savePendingQueue(queue: PendingSyncItem[]): void {
  try {
    localStorage.setItem(QUEUE_STORAGE_KEY, JSON.stringify(queue));
    notifyListeners(queue);
  } catch (err) {
    console.error('Error saving pending sync queue:', err);
  }
}

function notifyListeners(queue: PendingSyncItem[]) {
  queueListeners.forEach(cb => cb(queue.length, queue));
}

export function onPendingQueueChange(cb: (count: number, items: PendingSyncItem[]) => void) {
  queueListeners.push(cb);
  cb(getPendingQueue().length, getPendingQueue());
  return () => {
    const idx = queueListeners.indexOf(cb);
    if (idx !== -1) queueListeners.splice(idx, 1);
  };
}

/**
 * Clean helper to strip undefined and oversized fields
 */
function cleanPayload<T extends Record<string, any>>(obj: T): T {
  if (!obj) return obj;
  const result: any = {};
  for (const key of Object.keys(obj)) {
    if (obj[key] !== undefined) {
      result[key] = obj[key];
    }
  }
  return result;
}

/**
 * Enqueue an operation that couldn't immediately sync or needs guaranteed eventual consistency
 */
export function enqueuePendingSync(action: SyncActionType, entityId: string, data?: any): void {
  const queue = getPendingQueue();
  
  // Deduplicate / replace previous pending action on the same entity
  const filtered = queue.filter(item => !(item.action === action && item.entityId === entityId));

  const newItem: PendingSyncItem = {
    id: `sync-${Date.now()}-${Math.random().toString(36).substring(2, 7)}`,
    action,
    entityId,
    data: data ? cleanPayload(data) : undefined,
    timestamp: Date.now(),
    retryCount: 0,
  };

  filtered.push(newItem);
  savePendingQueue(filtered);
  console.log(`[OfflineSyncQueue] Enqueued: ${action} for ${entityId}. Total queued: ${filtered.length}`);
}

/**
 * Try flushing all pending items to Firebase cloud
 */
let isFlushing = false;
export async function flushPendingSyncQueue(): Promise<{ success: boolean; processed: number; remaining: number }> {
  if (isFlushing) return { success: false, processed: 0, remaining: getPendingQueue().length };
  
  const queue = getPendingQueue();
  if (queue.length === 0) return { success: true, processed: 0, remaining: 0 };

  isFlushing = true;
  console.log(`[OfflineSyncQueue] Starting sync flush of ${queue.length} pending items to Firestore...`);

  const remainingQueue: PendingSyncItem[] = [];
  let processedCount = 0;

  try {
    for (const item of queue) {
      try {
        await executeSyncItem(item);
        processedCount++;
      } catch (err: any) {
        const errMsg = err?.message || String(err);
        const isQuota = errMsg.toLowerCase().includes('quota') || errMsg.toLowerCase().includes('resource-exhausted');
        
        item.retryCount = (item.retryCount || 0) + 1;
        remainingQueue.push(item);

        if (isQuota) {
          console.warn('[OfflineSyncQueue] Daily quota still exceeded. Halting flush until quota refresh.');
          // Push remaining unattempted items
          const unattempted = queue.slice(queue.indexOf(item) + 1);
          remainingQueue.push(...unattempted);
          break;
        }
      }
    }
  } finally {
    savePendingQueue(remainingQueue);
    isFlushing = false;
  }

  console.log(`[OfflineSyncQueue] Flush complete. Uploaded: ${processedCount}, Remaining: ${remainingQueue.length}`);
  return {
    success: remainingQueue.length === 0,
    processed: processedCount,
    remaining: remainingQueue.length,
  };
}

/**
 * Executes a single sync item against Firestore
 */
async function executeSyncItem(item: PendingSyncItem): Promise<void> {
  const { action, entityId, data } = item;

  switch (action) {
    case 'SAVE_PRODUCT':
      await setDoc(doc(db, SHOP_PATH, 'products', entityId), cleanPayload(data), { merge: true });
      break;

    case 'DELETE_PRODUCT':
      await deleteDoc(doc(db, SHOP_PATH, 'products', entityId));
      break;

    case 'SAVE_TRANSACTION':
      await setDoc(doc(db, SHOP_PATH, 'transactions', entityId), cleanPayload(data), { merge: true });
      break;

    case 'DELETE_TRANSACTION':
      await deleteDoc(doc(db, SHOP_PATH, 'transactions', entityId));
      break;

    case 'SAVE_SALE':
      await setDoc(doc(db, SHOP_PATH, 'productSales', entityId), cleanPayload(data), { merge: true });
      break;

    case 'SAVE_BALANCE':
      await setDoc(doc(db, SHOP_PATH, 'dailyBalances', entityId), cleanPayload(data), { merge: true });
      break;

    case 'SAVE_PURCHASE':
      await setDoc(doc(db, SHOP_PATH, 'mobilePurchases', entityId), cleanPayload(data), { merge: true });
      break;

    case 'DELETE_PURCHASE':
      await deleteDoc(doc(db, SHOP_PATH, 'mobilePurchases', entityId));
      break;

    case 'SAVE_SUPPLIER':
      await setDoc(doc(db, SHOP_PATH, 'suppliers', entityId), cleanPayload(data), { merge: true });
      break;

    case 'DELETE_SUPPLIER':
      await deleteDoc(doc(db, SHOP_PATH, 'suppliers', entityId));
      break;

    case 'SAVE_SETTINGS':
      await setDoc(doc(db, SHOP_PATH, 'config', 'appSettings'), cleanPayload(data), { merge: true });
      break;

    default:
      console.warn(`Unknown sync action: ${action}`);
  }
}

// Background auto-retry when online, when quota resets, or on periodic timer
if (typeof window !== 'undefined') {
  window.addEventListener('online', () => {
    console.log('[OfflineSyncQueue] Network online detected. Attempting queue flush...');
    flushPendingSyncQueue();
  });

  // Check every 2 minutes
  setInterval(() => {
    const queue = getPendingQueue();
    if (queue.length > 0 && navigator.onLine) {
      flushPendingSyncQueue();
    }
  }, 120000);
}
