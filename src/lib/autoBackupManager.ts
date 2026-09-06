import { 
  Transaction, 
  DailyBalance, 
  AppSettings, 
  Product, 
  ProductSale, 
  MobilePurchaseRecord, 
  Supplier 
} from '../types';

export interface CompleteShopBackup {
  version: string;
  appName: string;
  exportDate: string;
  timestamp: number;
  transactions: Transaction[];
  dailyBalances: DailyBalance[];
  products: Product[];
  productSales: ProductSale[];
  mobilePurchases: MobilePurchaseRecord[];
  suppliers: Supplier[];
  settings: AppSettings;
}

export interface BackupStatus {
  isPersistent: boolean;
  hasFolderAccess: boolean;
  folderName: string | null;
  lastBackupTime: number | null;
  lastBackupFileName: string | null;
  isSaving: boolean;
  storageUsageBytes?: number;
  storageQuotaBytes?: number;
}

const IDB_DB_NAME = 'balal_mobiles_storage_v1';
const IDB_STORE_NAME = 'folder_handles';
const HANDLE_KEY = 'auto_backup_folder_handle';
const BACKUP_META_KEY = 'balal_mobiles_backup_meta_v1';

let currentDirHandle: any = null;
let cachedStatus: BackupStatus = {
  isPersistent: false,
  hasFolderAccess: false,
  folderName: null,
  lastBackupTime: null,
  lastBackupFileName: null,
  isSaving: false,
};

const statusListeners: Array<(status: BackupStatus) => void> = [];

// ===================== INDEXEDDB FOR DIR HANDLE =====================
function openHandleDB(): Promise<IDBDatabase> {
  return new Promise((resolve, reject) => {
    if (typeof indexedDB === 'undefined') {
      return reject(new Error('IndexedDB not supported in this environment'));
    }
    const req = indexedDB.open(IDB_DB_NAME, 1);
    req.onupgradeneeded = () => {
      const db = req.result;
      if (!db.objectStoreNames.contains(IDB_STORE_NAME)) {
        db.createObjectStore(IDB_STORE_NAME);
      }
    };
    req.onsuccess = () => resolve(req.result);
    req.onerror = () => reject(req.error);
  });
}

async function saveDirHandleToDB(handle: any): Promise<void> {
  try {
    const db = await openHandleDB();
    const tx = db.transaction(IDB_STORE_NAME, 'readwrite');
    tx.objectStore(IDB_STORE_NAME).put(handle, HANDLE_KEY);
    return new Promise((resolve, reject) => {
      tx.oncomplete = () => resolve();
      tx.onerror = () => reject(tx.error);
    });
  } catch (err) {
    console.warn('Could not save DirectoryHandle to IndexedDB:', err);
  }
}

async function getDirHandleFromDB(): Promise<any> {
  try {
    const db = await openHandleDB();
    const tx = db.transaction(IDB_STORE_NAME, 'readonly');
    const req = tx.objectStore(IDB_STORE_NAME).get(HANDLE_KEY);
    return new Promise((resolve) => {
      req.onsuccess = () => resolve(req.result || null);
      req.onerror = () => resolve(null);
    });
  } catch (err) {
    return null;
  }
}

// ===================== PERSISTENT STORAGE API =====================
export async function requestStoragePersistence(): Promise<boolean> {
  if (typeof navigator !== 'undefined' && navigator.storage && navigator.storage.persist) {
    try {
      const isPersisted = await navigator.storage.persist();
      cachedStatus.isPersistent = isPersisted;
      notifyStatus();
      return isPersisted;
    } catch (e) {
      console.warn('Storage persist request failed:', e);
    }
  }
  return false;
}

export async function checkStoragePersistence(): Promise<boolean> {
  if (typeof navigator !== 'undefined' && navigator.storage && navigator.storage.persisted) {
    try {
      const isPersisted = await navigator.storage.persisted();
      cachedStatus.isPersistent = isPersisted;
      
      if (navigator.storage.estimate) {
        const est = await navigator.storage.estimate();
        cachedStatus.storageUsageBytes = est.usage;
        cachedStatus.storageQuotaBytes = est.quota;
      }
      notifyStatus();
      return isPersisted;
    } catch (e) {
      console.warn('Storage persist check error:', e);
    }
  }
  return false;
}

// ===================== FILE SYSTEM ACCESS API =====================
export async function selectBackupDirectory(): Promise<{ success: boolean; folderName?: string; error?: string }> {
  if (typeof window === 'undefined' || !(window as any).showDirectoryPicker) {
    return { 
      success: false, 
      error: 'File System Access API اس براؤزر میں دستیاب نہیں۔ براہ کرم Chrome، Edge یا جدید براؤزر استعمال کریں۔' 
    };
  }

  try {
    const handle = await (window as any).showDirectoryPicker({
      mode: 'readwrite',
      startIn: 'downloads',
    });

    if (handle) {
      currentDirHandle = handle;
      await saveDirHandleToDB(handle);
      cachedStatus.hasFolderAccess = true;
      cachedStatus.folderName = handle.name || 'Selected Folder';
      saveLocalMeta();
      notifyStatus();
      return { success: true, folderName: handle.name };
    }
  } catch (err: any) {
    if (err.name === 'AbortError') {
      return { success: false, error: 'User cancelled folder selection.' };
    }
    console.error('Error selecting backup folder:', err);
    return { success: false, error: err.message || 'Folder selection failed.' };
  }

  return { success: false, error: 'Could not acquire folder.' };
}

async function verifyPermission(handle: any, readWrite: boolean = true): Promise<boolean> {
  const options: any = {};
  if (readWrite) options.mode = 'readwrite';

  if ((await handle.queryPermission(options)) === 'granted') {
    return true;
  }
  if ((await handle.requestPermission(options)) === 'granted') {
    return true;
  }
  return false;
}

// ===================== WRITE BACKUP TO FOLDER =====================
export async function writeBackupDataToFolder(data: CompleteShopBackup): Promise<boolean> {
  if (!currentDirHandle) {
    // Try restoring from IndexedDB
    const stored = await getDirHandleFromDB();
    if (stored) {
      currentDirHandle = stored;
      cachedStatus.hasFolderAccess = true;
      cachedStatus.folderName = stored.name || 'Backup Folder';
    }
  }

  if (!currentDirHandle) return false;

  try {
    cachedStatus.isSaving = true;
    notifyStatus();

    const hasPerm = await verifyPermission(currentDirHandle, true);
    if (!hasPerm) {
      cachedStatus.hasFolderAccess = false;
      notifyStatus();
      return false;
    }

    const jsonString = JSON.stringify(data, null, 2);
    const dateStr = new Date().toISOString().split('T')[0];
    
    // 1. Primary Live Backup File
    const mainFileName = 'balal_mobiles_live_backup.json';
    const mainFileHandle = await currentDirHandle.getFileHandle(mainFileName, { create: true });
    const writableMain = await mainFileHandle.createWritable();
    await writableMain.write(jsonString);
    await writableMain.close();

    // 2. Daily Historical Snapshot
    const dailyFileName = `backup_${dateStr}.json`;
    const dailyFileHandle = await currentDirHandle.getFileHandle(dailyFileName, { create: true });
    const writableDaily = await dailyFileHandle.createWritable();
    await writableDaily.write(jsonString);
    await writableDaily.close();

    cachedStatus.lastBackupTime = Date.now();
    cachedStatus.lastBackupFileName = mainFileName;
    cachedStatus.hasFolderAccess = true;
    saveLocalMeta();
    return true;
  } catch (err) {
    console.error('Failed writing backup file to folder:', err);
    return false;
  } finally {
    cachedStatus.isSaving = false;
    notifyStatus();
  }
}

// Fallback direct browser download
export function triggerDirectFileDownload(data: CompleteShopBackup, filename?: string) {
  const dateStr = new Date().toISOString().split('T')[0];
  const name = filename || `balal_mobiles_backup_${dateStr}.json`;
  const jsonString = JSON.stringify(data, null, 2);
  const blob = new Blob([jsonString], { type: 'application/json' });
  const url = URL.createObjectURL(blob);
  const link = document.createElement('a');
  link.href = url;
  link.download = name;
  document.body.appendChild(link);
  link.click();
  document.body.removeChild(link);
  URL.revokeObjectURL(url);

  cachedStatus.lastBackupTime = Date.now();
  cachedStatus.lastBackupFileName = name;
  saveLocalMeta();
  notifyStatus();
}

// ===================== DEBOUNCED AUTO-SAVE =====================
let autoSaveTimer: any = null;
export function scheduleAutoBackup(data: CompleteShopBackup, delayMs: number = 2500) {
  if (autoSaveTimer) clearTimeout(autoSaveTimer);
  
  autoSaveTimer = setTimeout(async () => {
    // 1. Try writing to local folder if permitted
    if (currentDirHandle) {
      await writeBackupDataToFolder(data);
    }
  }, delayMs);
}

// ===================== STATUS NOTIFICATION =====================
function notifyStatus() {
  statusListeners.forEach(cb => cb({ ...cachedStatus }));
}

export function onBackupStatusChange(cb: (status: BackupStatus) => void) {
  statusListeners.push(cb);
  cb({ ...cachedStatus });
  return () => {
    const idx = statusListeners.indexOf(cb);
    if (idx !== -1) statusListeners.splice(idx, 1);
  };
}

function saveLocalMeta() {
  try {
    localStorage.setItem(BACKUP_META_KEY, JSON.stringify({
      hasFolderAccess: cachedStatus.hasFolderAccess,
      folderName: cachedStatus.folderName,
      lastBackupTime: cachedStatus.lastBackupTime,
      lastBackupFileName: cachedStatus.lastBackupFileName,
    }));
  } catch (e) {}
}

export async function initAutoBackupManager(): Promise<BackupStatus> {
  // Load local meta
  try {
    const raw = localStorage.getItem(BACKUP_META_KEY);
    if (raw) {
      const parsed = JSON.parse(raw);
      cachedStatus.folderName = parsed.folderName || null;
      cachedStatus.lastBackupTime = parsed.lastBackupTime || null;
      cachedStatus.lastBackupFileName = parsed.lastBackupFileName || null;
    }
  } catch (e) {}

  // Check persistence
  await checkStoragePersistence();

  // Try recovering handle from IndexedDB
  const storedHandle = await getDirHandleFromDB();
  if (storedHandle) {
    currentDirHandle = storedHandle;
    cachedStatus.hasFolderAccess = true;
    cachedStatus.folderName = storedHandle.name || cachedStatus.folderName || 'Backup Folder';
  }

  notifyStatus();
  return { ...cachedStatus };
}
