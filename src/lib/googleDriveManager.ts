import { 
  getDriveCachedToken, 
  setDriveCachedToken, 
  connectGoogleDriveAccount, 
  auth 
} from './firebase';
import { CompleteShopBackup } from './autoBackupManager';

export interface DriveBackupFile {
  id: string;
  name: string;
  modifiedTime: string;
  size?: string;
}

export interface GoogleDriveStatus {
  isConnected: boolean;
  userEmail: string | null;
  userName: string | null;
  userPhoto?: string | null;
  folderId: string | null;
  folderName: string;
  lastSyncTime: number | null;
  lastSyncStatus: 'idle' | 'success' | 'error';
  lastSyncMessage: string | null;
  isSyncing: boolean;
}

const DRIVE_FOLDER_NAME = 'Balal Mobiles Shop Backups';
const LIVE_BACKUP_FILE_NAME = 'balal_mobiles_live_backup.json';
const DRIVE_STORAGE_KEY = 'balal_mobiles_gdrive_meta_v1';

let driveFolderIdCache: string | null = null;
let driveFileIdCache: string | null = null;

let cachedDriveStatus: GoogleDriveStatus = {
  isConnected: false,
  userEmail: null,
  userName: null,
  userPhoto: null,
  folderId: null,
  folderName: DRIVE_FOLDER_NAME,
  lastSyncTime: null,
  lastSyncStatus: 'idle',
  lastSyncMessage: null,
  isSyncing: false,
};

const driveStatusListeners: Array<(status: GoogleDriveStatus) => void> = [];

function notifyDriveStatus() {
  driveStatusListeners.forEach(cb => cb({ ...cachedDriveStatus }));
}

export function onGoogleDriveStatusChange(cb: (status: GoogleDriveStatus) => void) {
  driveStatusListeners.push(cb);
  cb({ ...cachedDriveStatus });
  return () => {
    const idx = driveStatusListeners.indexOf(cb);
    if (idx !== -1) driveStatusListeners.splice(idx, 1);
  };
}

function saveLocalDriveMeta() {
  try {
    localStorage.setItem(DRIVE_STORAGE_KEY, JSON.stringify({
      userEmail: cachedDriveStatus.userEmail,
      userName: cachedDriveStatus.userName,
      userPhoto: cachedDriveStatus.userPhoto,
      folderId: driveFolderIdCache,
      lastSyncTime: cachedDriveStatus.lastSyncTime,
      lastSyncStatus: cachedDriveStatus.lastSyncStatus,
      lastSyncMessage: cachedDriveStatus.lastSyncMessage,
    }));
  } catch (e) {}
}

export function initGoogleDriveManager(): GoogleDriveStatus {
  try {
    const raw = localStorage.getItem(DRIVE_STORAGE_KEY);
    if (raw) {
      const parsed = JSON.parse(raw);
      cachedDriveStatus.userEmail = parsed.userEmail || null;
      cachedDriveStatus.userName = parsed.userName || null;
      cachedDriveStatus.userPhoto = parsed.userPhoto || null;
      cachedDriveStatus.lastSyncTime = parsed.lastSyncTime || null;
      cachedDriveStatus.lastSyncStatus = parsed.lastSyncStatus || 'idle';
      cachedDriveStatus.lastSyncMessage = parsed.lastSyncMessage || null;
      driveFolderIdCache = parsed.folderId || null;
    }
  } catch (e) {}

  // Check if token exists
  const token = getDriveCachedToken();
  cachedDriveStatus.isConnected = !!token;
  notifyDriveStatus();
  return { ...cachedDriveStatus };
}

/**
 * Connect user's Google Drive via OAuth Popup
 */
export async function connectGoogleDrive(): Promise<{ success: boolean; email?: string; error?: string }> {
  try {
    cachedDriveStatus.isSyncing = true;
    notifyDriveStatus();

    const { user, accessToken } = await connectGoogleDriveAccount();
    
    cachedDriveStatus.isConnected = true;
    cachedDriveStatus.userEmail = user.email || null;
    cachedDriveStatus.userName = user.displayName || null;
    cachedDriveStatus.userPhoto = user.photoURL || null;
    cachedDriveStatus.lastSyncStatus = 'idle';
    cachedDriveStatus.lastSyncMessage = 'Google Drive کامیابی سے منسلک ہو گیا ہے۔';
    
    saveLocalDriveMeta();
    notifyDriveStatus();

    // Find or create backup folder in Google Drive
    await getOrCreateBackupFolder(accessToken);

    return { success: true, email: user.email || undefined };
  } catch (err: any) {
    console.error('Google Drive Connect Error:', err);
    cachedDriveStatus.lastSyncStatus = 'error';
    cachedDriveStatus.lastSyncMessage = err.message || 'Google Drive کنکشن ناکام ہو گیا۔';
    notifyDriveStatus();
    return { success: false, error: err.message || 'Failed to connect Google Drive' };
  } finally {
    cachedDriveStatus.isSyncing = false;
    notifyDriveStatus();
  }
}

/**
 * Disconnect Google Drive
 */
export function disconnectGoogleDrive() {
  setDriveCachedToken(null);
  cachedDriveStatus.isConnected = false;
  cachedDriveStatus.lastSyncStatus = 'idle';
  cachedDriveStatus.lastSyncMessage = 'Google Drive منقطع کر دیا گیا ہے۔';
  driveFolderIdCache = null;
  driveFileIdCache = null;
  saveLocalDriveMeta();
  notifyDriveStatus();
}

/**
 * Find or create backup folder in Google Drive
 */
async function getOrCreateBackupFolder(token: string): Promise<string> {
  if (driveFolderIdCache) return driveFolderIdCache;

  // 1. Search for existing folder
  const query = `name='${DRIVE_FOLDER_NAME}' and mimeType='application/vnd.google-apps.folder' and trashed=false`;
  const searchUrl = `https://www.googleapis.com/drive/v3/files?q=${encodeURIComponent(query)}&fields=files(id,name)`;
  
  const searchRes = await fetch(searchUrl, {
    headers: { Authorization: `Bearer ${token}` }
  });

  if (searchRes.ok) {
    const data = await searchRes.json();
    if (data.files && data.files.length > 0) {
      driveFolderIdCache = data.files[0].id;
      cachedDriveStatus.folderId = driveFolderIdCache;
      saveLocalDriveMeta();
      return driveFolderIdCache!;
    }
  }

  // 2. Create new folder if not found
  const createRes = await fetch('https://www.googleapis.com/drive/v3/files', {
    method: 'POST',
    headers: {
      Authorization: `Bearer ${token}`,
      'Content-Type': 'application/json'
    },
    body: JSON.stringify({
      name: DRIVE_FOLDER_NAME,
      mimeType: 'application/vnd.google-apps.folder',
      description: 'Balal Mobiles and EasyPaisa Shop POS Automated Backups'
    })
  });

  if (!createRes.ok) {
    const errText = await createRes.text();
    throw new Error(`Google Drive folder creation failed: ${errText}`);
  }

  const created = await createRes.json();
  driveFolderIdCache = created.id;
  cachedDriveStatus.folderId = driveFolderIdCache;
  saveLocalDriveMeta();
  return created.id;
}

/**
 * Find existing live backup file ID
 */
async function getLiveBackupFileId(token: string, folderId: string): Promise<string | null> {
  if (driveFileIdCache) return driveFileIdCache;

  const query = `name='${LIVE_BACKUP_FILE_NAME}' and '${folderId}' in parents and trashed=false`;
  const url = `https://www.googleapis.com/drive/v3/files?q=${encodeURIComponent(query)}&fields=files(id,name,modifiedTime)`;
  
  const res = await fetch(url, {
    headers: { Authorization: `Bearer ${token}` }
  });

  if (res.ok) {
    const data = await res.json();
    if (data.files && data.files.length > 0) {
      driveFileIdCache = data.files[0].id;
      return driveFileIdCache;
    }
  }
  return null;
}

/**
 * Upload or Update Shop Backup to Google Drive
 */
export async function uploadShopBackupToDrive(
  data: CompleteShopBackup, 
  isAutoSync: boolean = false
): Promise<{ success: boolean; message: string }> {
  const token = getDriveCachedToken();
  if (!token) {
    return { 
      success: false, 
      message: 'Google Drive متصل نہیں ہے۔ برائے مہربانی پہلے Google Drive لاگ ان کریں۔' 
    };
  }

  if (!navigator.onLine) {
    return {
      success: false,
      message: 'انٹرنیٹ کنکشن دستیاب نہیں ہے۔ لوکل بیک اپ محفوظ ہے۔'
    };
  }

  try {
    cachedDriveStatus.isSyncing = true;
    notifyDriveStatus();

    const folderId = await getOrCreateBackupFolder(token);
    const existingFileId = await getLiveBackupFileId(token, folderId);
    const jsonString = JSON.stringify(data, null, 2);

    if (existingFileId) {
      // Direct media PATCH to update existing file
      const updateUrl = `https://www.googleapis.com/upload/drive/v3/files/${existingFileId}?uploadType=media`;
      const updateRes = await fetch(updateUrl, {
        method: 'PATCH',
        headers: {
          Authorization: `Bearer ${token}`,
          'Content-Type': 'application/json',
        },
        body: jsonString,
      });

      if (!updateRes.ok) {
        if (updateRes.status === 401) {
          setDriveCachedToken(null);
          cachedDriveStatus.isConnected = false;
          throw new Error('Google Drive سیشن ختم ہو گیا۔ براہ کرم دوبارہ لاگ ان کریں۔');
        }
        // If file not found in drive, reset cache and create multipart
        driveFileIdCache = null;
        return await createNewDriveBackupFile(token, folderId, jsonString, isAutoSync);
      }
    } else {
      await createNewDriveBackupFile(token, folderId, jsonString, isAutoSync);
    }

    // Optional daily snapshot if manual sync or once a day
    if (!isAutoSync) {
      await createDatedSnapshotInDrive(token, folderId, jsonString);
    }

    const now = Date.now();
    cachedDriveStatus.lastSyncTime = now;
    cachedDriveStatus.lastSyncStatus = 'success';
    cachedDriveStatus.lastSyncMessage = `کامیابی: سارا ڈیٹا Google Drive کے فولڈر [${DRIVE_FOLDER_NAME}] میں کامیابی سے محفوظ ہو گیا ہے۔`;
    saveLocalDriveMeta();
    notifyDriveStatus();

    return { 
      success: true, 
      message: `Google Drive پر ${new Date(now).toLocaleTimeString()} بجے بیک اپ محفوظ ہو گیا۔` 
    };
  } catch (err: any) {
    console.error('Failed uploading to Google Drive:', err);
    cachedDriveStatus.lastSyncStatus = 'error';
    cachedDriveStatus.lastSyncMessage = err.message || 'Google Drive اپلوڈ میں خرابی آئی۔';
    saveLocalDriveMeta();
    notifyDriveStatus();
    return { success: false, message: err.message || 'Drive sync failed' };
  } finally {
    cachedDriveStatus.isSyncing = false;
    notifyDriveStatus();
  }
}

/**
 * Helper: Multipart create new file
 */
async function createNewDriveBackupFile(
  token: string, 
  folderId: string, 
  jsonString: string,
  isAutoSync: boolean
): Promise<{ success: boolean; message: string }> {
  const boundary = '-------balal_drive_boundary_' + Date.now();
  const delimiter = `\r\n--${boundary}\r\n`;
  const closeDelimiter = `\r\n--${boundary}--`;

  const metadata = {
    name: LIVE_BACKUP_FILE_NAME,
    mimeType: 'application/json',
    parents: [folderId],
    description: 'Live Shop Database Snapshot - Balal Mobiles',
  };

  const multipartRequestBody =
    delimiter +
    'Content-Type: application/json; charset=UTF-8\r\n\r\n' +
    JSON.stringify(metadata) +
    delimiter +
    'Content-Type: application/json\r\n\r\n' +
    jsonString +
    closeDelimiter;

  const res = await fetch('https://www.googleapis.com/upload/drive/v3/files?uploadType=multipart', {
    method: 'POST',
    headers: {
      Authorization: `Bearer ${token}`,
      'Content-Type': `multipart/related; boundary=${boundary}`,
    },
    body: multipartRequestBody,
  });

  if (!res.ok) {
    const err = await res.text();
    throw new Error(`Google Drive create file failed: ${err}`);
  }

  const createdFile = await res.json();
  driveFileIdCache = createdFile.id;
  return { success: true, message: 'Created backup file in Google Drive.' };
}

/**
 * Helper: Create dated historical snapshot
 */
async function createDatedSnapshotInDrive(token: string, folderId: string, jsonString: string): Promise<void> {
  try {
    const dateStr = new Date().toISOString().split('T')[0];
    const snapshotName = `backup_${dateStr}_snapshot.json`;

    const boundary = '-------balal_snap_' + Date.now();
    const delimiter = `\r\n--${boundary}\r\n`;
    const closeDelimiter = `\r\n--${boundary}--`;

    const metadata = {
      name: snapshotName,
      mimeType: 'application/json',
      parents: [folderId],
      description: `Daily snapshot taken on ${dateStr}`,
    };

    const multipart =
      delimiter +
      'Content-Type: application/json; charset=UTF-8\r\n\r\n' +
      JSON.stringify(metadata) +
      delimiter +
      'Content-Type: application/json\r\n\r\n' +
      jsonString +
      closeDelimiter;

    await fetch('https://www.googleapis.com/upload/drive/v3/files?uploadType=multipart', {
      method: 'POST',
      headers: {
        Authorization: `Bearer ${token}`,
        'Content-Type': `multipart/related; boundary=${boundary}`,
      },
      body: multipart,
    });
  } catch (e) {
    console.warn('Could not create dated snapshot in Drive:', e);
  }
}

/**
 * List all backup files in the Google Drive folder
 */
export async function listGoogleDriveBackups(): Promise<DriveBackupFile[]> {
  const token = getDriveCachedToken();
  if (!token) return [];

  try {
    const folderId = await getOrCreateBackupFolder(token);
    const query = `'${folderId}' in parents and trashed=false`;
    const url = `https://www.googleapis.com/drive/v3/files?q=${encodeURIComponent(query)}&orderBy=modifiedTime desc&fields=files(id,name,modifiedTime,size)`;

    const res = await fetch(url, {
      headers: { Authorization: `Bearer ${token}` }
    });

    if (res.ok) {
      const data = await res.json();
      return data.files || [];
    }
  } catch (err) {
    console.error('Error listing Drive backups:', err);
  }
  return [];
}

/**
 * Download a backup file from Google Drive and return the parsed shop data
 */
export async function downloadDriveBackup(fileId: string): Promise<CompleteShopBackup | null> {
  const token = getDriveCachedToken();
  if (!token) {
    throw new Error('Google Drive access token missing. Please sign in with Google Drive.');
  }

  const url = `https://www.googleapis.com/drive/v3/files/${fileId}?alt=media`;
  const res = await fetch(url, {
    headers: { Authorization: `Bearer ${token}` }
  });

  if (!res.ok) {
    const err = await res.text();
    throw new Error(`Failed to download backup from Google Drive: ${err}`);
  }

  const data: CompleteShopBackup = await res.json();
  return data;
}

/**
 * Debounced automatic background sync to Google Drive
 */
let driveAutoSyncTimer: any = null;
export function scheduleGoogleDriveAutoBackup(data: CompleteShopBackup, delayMs: number = 3500) {
  if (driveAutoSyncTimer) clearTimeout(driveAutoSyncTimer);

  driveAutoSyncTimer = setTimeout(async () => {
    const token = getDriveCachedToken();
    if (token && navigator.onLine) {
      await uploadShopBackupToDrive(data, true);
    }
  }, delayMs);
}
