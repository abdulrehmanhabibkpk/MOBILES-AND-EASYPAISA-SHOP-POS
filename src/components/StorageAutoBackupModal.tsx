import React, { useState, useEffect } from 'react';
import { 
  FolderDown, HardDrive, ShieldCheck, CheckCircle2, AlertTriangle, 
  CloudUpload, RefreshCw, X, Download, Folder, Check, ArrowRight,
  Shield, Sparkles, Database, Laptop, Info, Cloud, CheckCircle,
  ExternalLink, UploadCloud, RotateCcw, AlertCircle
} from 'lucide-react';
import { 
  selectBackupDirectory, 
  requestStoragePersistence, 
  triggerDirectFileDownload, 
  BackupStatus, 
  onBackupStatusChange,
  CompleteShopBackup
} from '../lib/autoBackupManager';
import { 
  getPendingQueue, 
  flushPendingSyncQueue, 
  onPendingQueueChange 
} from '../lib/offlineSyncManager';
import { 
  connectGoogleDrive, 
  disconnectGoogleDrive, 
  uploadShopBackupToDrive, 
  listGoogleDriveBackups, 
  downloadDriveBackup, 
  onGoogleDriveStatusChange, 
  GoogleDriveStatus, 
  DriveBackupFile 
} from '../lib/googleDriveManager';
import { isQuotaExceeded } from '../lib/firebase';
import { AppSettings, Transaction, DailyBalance, Product, ProductSale, MobilePurchaseRecord, Supplier } from '../types';

interface StorageAutoBackupModalProps {
  isOpen: boolean;
  onClose: () => void;
  settings: AppSettings;
  shopData: CompleteShopBackup;
  onRestoreData?: (restored: CompleteShopBackup) => Promise<void>;
}

export const StorageAutoBackupModal: React.FC<StorageAutoBackupModalProps> = ({
  isOpen,
  onClose,
  settings,
  shopData,
  onRestoreData,
}) => {
  const [backupStatus, setBackupStatus] = useState<BackupStatus>({
    isPersistent: false,
    hasFolderAccess: false,
    folderName: null,
    lastBackupTime: null,
    lastBackupFileName: null,
    isSaving: false,
  });

  const [driveStatus, setDriveStatus] = useState<GoogleDriveStatus>({
    isConnected: false,
    userEmail: null,
    userName: null,
    userPhoto: null,
    folderId: null,
    folderName: 'Balal Mobiles Shop Backups',
    lastSyncTime: null,
    lastSyncStatus: 'idle',
    lastSyncMessage: null,
    isSyncing: false,
  });

  const [driveFiles, setDriveFiles] = useState<DriveBackupFile[]>([]);
  const [isLoadingFiles, setIsLoadingFiles] = useState(false);
  const [isUploadingToDrive, setIsUploadingToDrive] = useState(false);
  const [driveFeedback, setDriveFeedback] = useState<string | null>(null);

  const [pendingCount, setPendingCount] = useState<number>(0);
  const [isSyncing, setIsSyncing] = useState<boolean>(false);
  const [syncFeedback, setSyncFeedback] = useState<string | null>(null);
  const [folderSelecting, setFolderSelecting] = useState<boolean>(false);
  const [folderError, setFolderError] = useState<string | null>(null);

  // Restore Confirmation state
  const [selectedRestoreFile, setSelectedRestoreFile] = useState<DriveBackupFile | null>(null);
  const [isRestoring, setIsRestoring] = useState<boolean>(false);
  const [restoreFeedback, setRestoreFeedback] = useState<string | null>(null);

  const isLight = settings.theme === 'light';

  useEffect(() => {
    const unsubBackup = onBackupStatusChange((st) => setBackupStatus(st));
    const unsubQueue = onPendingQueueChange((cnt) => setPendingCount(cnt));
    const unsubDrive = onGoogleDriveStatusChange((st) => {
      setDriveStatus(st);
      if (st.isConnected) {
        loadDriveFilesList();
      }
    });

    return () => {
      unsubBackup();
      unsubQueue();
      unsubDrive();
    };
  }, []);

  const loadDriveFilesList = async () => {
    setIsLoadingFiles(true);
    try {
      const files = await listGoogleDriveBackups();
      setDriveFiles(files);
    } catch (e) {
      console.error(e);
    } finally {
      setIsLoadingFiles(false);
    }
  };

  if (!isOpen) return null;

  const handleConnectDrive = async () => {
    setDriveFeedback(null);
    const res = await connectGoogleDrive();
    if (res.success) {
      setDriveFeedback(`Google Drive کامیابی سے جڑ گیا ہے (${res.email})`);
      // Auto trigger first backup
      await handleSaveToGoogleDrive();
      loadDriveFilesList();
    } else {
      setDriveFeedback(res.error || 'Google Drive کنکشن ناکام ہو گیا۔');
    }
  };

  const handleSaveToGoogleDrive = async () => {
    setIsUploadingToDrive(true);
    setDriveFeedback(null);
    try {
      const res = await uploadShopBackupToDrive(shopData, false);
      setDriveFeedback(res.message);
      if (res.success) {
        loadDriveFilesList();
      }
    } catch (err: any) {
      setDriveFeedback(err.message || 'Error saving to Google Drive');
    } finally {
      setIsUploadingToDrive(false);
    }
  };

  const handleConfirmRestoreFromDrive = async () => {
    if (!selectedRestoreFile || !onRestoreData) return;
    setIsRestoring(true);
    setRestoreFeedback(null);
    try {
      const data = await downloadDriveBackup(selectedRestoreFile.id);
      if (data) {
        await onRestoreData(data);
        setRestoreFeedback('کامیابی: گوگل ڈرائیو سے تمام ڈیٹا کامیابی سے بحال (Restore) ہو گیا ہے!');
        setSelectedRestoreFile(null);
      }
    } catch (err: any) {
      setRestoreFeedback(err.message || 'ڈیٹا بحال کرنے میں خرابی پیش آئی۔');
    } finally {
      setIsRestoring(false);
    }
  };

  const handleSelectFolder = async () => {
    setFolderSelecting(true);
    setFolderError(null);
    try {
      const res = await selectBackupDirectory();
      if (!res.success && res.error && res.error !== 'User cancelled folder selection.') {
        setFolderError(res.error);
      }
    } catch (err: any) {
      setFolderError(err.message || 'Folder select error.');
    } finally {
      setFolderSelecting(false);
    }
  };

  const handleEnablePersistence = async () => {
    await requestStoragePersistence();
  };

  const handleForceCloudSync = async () => {
    setIsSyncing(true);
    setSyncFeedback(null);
    try {
      const res = await flushPendingSyncQueue();
      if (res.success) {
        setSyncFeedback(`کامیابی: تمام (${res.processed}) کلاؤڈ ریکارڈز انٹرنیٹ پر اپلوڈ ہو گئے۔`);
      } else {
        setSyncFeedback(`${res.processed} اپلوڈ ہوئے، باقی (${res.remaining}) ڈیلی لمٹ یا کنکشن کے بعد اپلوڈ ہوں گے۔`);
      }
    } catch (err: any) {
      setSyncFeedback(err.message || 'Sync error.');
    } finally {
      setIsSyncing(false);
    }
  };

  const handleDownloadSnapshot = () => {
    triggerDirectFileDownload(shopData);
  };

  const formatTime = (ts: number | null) => {
    if (!ts) return 'ابھی تک نہیں';
    return new Date(ts).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit', second: '2-digit' });
  };

  return (
    <div className="fixed inset-0 z-50 flex items-center justify-center p-3 sm:p-4 bg-black/75 backdrop-blur-sm animate-fade-in overflow-y-auto">
      <div className={`relative w-full max-w-3xl rounded-2xl border shadow-2xl overflow-hidden my-auto ${
        isLight ? 'bg-white border-slate-200 text-slate-900' : 'bg-slate-900 border-slate-800 text-white'
      }`}>
        
        {/* Modal Header */}
        <div className="px-5 py-4 border-b border-slate-200 dark:border-slate-800 flex items-center justify-between bg-gradient-to-r from-blue-600/10 via-indigo-600/10 to-transparent">
          <div className="flex items-center gap-3">
            <div className="w-10 h-10 rounded-xl bg-blue-600 text-white flex items-center justify-center shadow-md shadow-blue-600/20">
              <CloudUpload className="w-5 h-5" />
            </div>
            <div>
              <h2 className="text-base sm:text-lg font-bold flex items-center gap-2">
                <span>Google Drive & Safe Storage (گوگل ڈرائیو اور اسٹوریج تحفظ)</span>
                {driveStatus.isConnected && (
                  <span className="px-2 py-0.5 rounded-full bg-emerald-500/10 text-emerald-600 dark:text-emerald-400 border border-emerald-500/30 text-[10px] font-extrabold flex items-center gap-1">
                    <Check className="w-3 h-3" /> Drive Connected
                  </span>
                )}
              </h2>
              <p className="text-xs text-slate-500 dark:text-slate-400">
                گوگل ڈرائیو، فائر بیس کلاؤڈ اور لوکل ڈسک کے ذریعے ڈیٹا کے ضیاع کا 100% مکمل خاتمہ
              </p>
            </div>
          </div>
          <button
            onClick={onClose}
            className="p-2 rounded-xl text-slate-400 hover:text-slate-600 hover:bg-slate-100 dark:hover:bg-slate-800 transition-colors cursor-pointer"
          >
            <X className="w-5 h-5" />
          </button>
        </div>

        {/* Modal Body */}
        <div className="p-5 max-h-[75vh] overflow-y-auto space-y-4">
          
          {/* Tri-Shield Guarantee Banner */}
          <div className={`p-4 rounded-xl border flex items-start gap-3.5 ${
            isLight ? 'bg-emerald-50/80 border-emerald-200 text-emerald-950' : 'bg-emerald-950/20 border-emerald-800/60 text-emerald-200'
          }`}>
            <ShieldCheck className="w-7 h-7 text-emerald-600 shrink-0 mt-0.5" />
            <div className="text-xs space-y-1">
              <h4 className="font-bold text-emerald-900 dark:text-emerald-300 text-sm">
                3 تہوں پر مشتمل محفوظ ڈیٹا سسٹم (Zero Data Loss Architecture)
              </h4>
              <p className="text-slate-600 dark:text-slate-300 leading-relaxed">
                <strong>1. گوگل ڈرائیو:</strong> سارا ڈیٹا آپ کے ذاتی گوگل اکاؤنٹ کے ڈرائیو فولڈر میں خود بخود محفوظ ہوتا ہے۔ اگر فائر بیس کا کوٹہ یا سرور آف بھی ہو تو ڈرائیو پر محفوظ رہے گا۔<br />
                <strong>2. لوکل بیک اپ فولڈر:</strong> بغیر انٹرنیٹ بھی ہر ٹرانزیکشن کمپیوٹر کے فولڈر میں فوری محفوظ ہوتی ہے۔<br />
                <strong>3. فائر بیس کلاؤڈ:</strong> لائیو رئیل ٹائم ڈیٹا بیس سنکرونائزیشن۔
              </p>
            </div>
          </div>

          {/* 1. GOOGLE DRIVE SECTION */}
          <div className={`p-4 rounded-xl border ${
            driveStatus.isConnected
              ? (isLight ? 'bg-blue-50/50 border-blue-200' : 'bg-blue-950/20 border-blue-800/50')
              : (isLight ? 'bg-slate-50 border-slate-200' : 'bg-slate-800/40 border-slate-700')
          }`}>
            <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-3 pb-3 border-b border-slate-200 dark:border-slate-800">
              <div className="flex items-center gap-3">
                <div className="w-9 h-9 rounded-xl bg-gradient-to-tr from-yellow-500 via-green-500 to-blue-500 p-0.5 shrink-0 shadow-sm">
                  <div className="w-full h-full bg-white dark:bg-slate-900 rounded-[10px] flex items-center justify-center">
                    <Cloud className="w-5 h-5 text-blue-600" />
                  </div>
                </div>
                <div>
                  <div className="flex items-center gap-2">
                    <h3 className="text-sm font-bold text-slate-900 dark:text-white">
                      Google Drive Auto-Backup (گوگل ڈرائیو کلاؤڈ)
                    </h3>
                    <span className={`text-[10px] font-bold px-2 py-0.5 rounded-full ${
                      driveStatus.isConnected
                        ? 'bg-emerald-500/10 text-emerald-600 border border-emerald-500/30'
                        : 'bg-amber-500/10 text-amber-600 border border-amber-500/30'
                    }`}>
                      {driveStatus.isConnected ? 'Connected' : 'Not Connected'}
                    </span>
                  </div>
                  <p className="text-xs text-slate-500 dark:text-slate-400">
                    {driveStatus.isConnected 
                      ? `اکاؤنٹ: ${driveStatus.userEmail || driveStatus.userName} • فولڈر: ${driveStatus.folderName}`
                      : 'گوگل ڈرائیو جوڑیں تاکہ ہر تبدیلی آپ کے گوگل اکاؤنٹ پر خود بخود محفوظ ہو۔'}
                  </p>
                </div>
              </div>

              {/* Action Buttons for Drive */}
              <div className="flex items-center gap-2">
                {!driveStatus.isConnected ? (
                  <button
                    onClick={handleConnectDrive}
                    disabled={driveStatus.isSyncing}
                    className="px-4 py-2 rounded-xl bg-white dark:bg-slate-800 text-slate-700 dark:text-slate-200 border border-slate-300 dark:border-slate-600 hover:border-blue-500 font-bold text-xs flex items-center gap-2 shadow-sm transition-all cursor-pointer"
                  >
                    <svg className="w-4 h-4" viewBox="0 0 48 48">
                      <path fill="#EA4335" d="M24 9.5c3.54 0 6.71 1.22 9.21 3.6l6.85-6.85C35.9 2.38 30.47 0 24 0 14.62 0 6.51 5.38 2.56 13.22l7.98 6.19C12.43 13.72 17.74 9.5 24 9.5z" />
                      <path fill="#4285F4" d="M46.98 24.55c0-1.57-.15-3.09-.38-4.55H24v9.02h12.94c-.58 2.96-2.26 5.48-4.78 7.18l7.73 6c4.51-4.18 7.09-10.36 7.09-17.65z" />
                      <path fill="#FBBC05" d="M10.53 28.59c-.48-1.45-.76-2.99-.76-4.59s.27-3.14.76-4.59l-7.98-6.19C.92 16.46 0 20.12 0 24c0 3.88.92 7.54 2.56 10.78l7.97-6.19z" />
                      <path fill="#34A853" d="M24 48c6.48 0 11.93-2.13 15.89-5.81l-7.73-6c-2.15 1.45-4.92 2.3-8.16 2.3-6.26 0-11.57-4.22-13.47-9.91l-7.98 6.19C6.51 42.62 14.62 48 24 48z" />
                    </svg>
                    <span>Connect Google Drive</span>
                  </button>
                ) : (
                  <>
                    <button
                      onClick={handleSaveToGoogleDrive}
                      disabled={isUploadingToDrive}
                      className="px-3.5 py-2 rounded-xl bg-blue-600 hover:bg-blue-700 text-white font-bold text-xs flex items-center gap-1.5 shadow-sm transition-all cursor-pointer"
                    >
                      <UploadCloud className={`w-3.5 h-3.5 ${isUploadingToDrive ? 'animate-bounce' : ''}`} />
                      <span>{isUploadingToDrive ? 'Uploading...' : 'Save to Drive Now'}</span>
                    </button>
                    <button
                      onClick={disconnectGoogleDrive}
                      className="p-2 rounded-xl text-slate-400 hover:text-rose-500 hover:bg-rose-50 dark:hover:bg-rose-950/30 transition-colors"
                      title="Disconnect Google Drive"
                    >
                      <X className="w-4 h-4" />
                    </button>
                  </>
                )}
              </div>
            </div>

            {driveFeedback && (
              <div className="mt-3 p-2.5 rounded-lg bg-blue-500/10 border border-blue-500/20 text-blue-700 dark:text-blue-300 text-xs font-semibold">
                {driveFeedback}
              </div>
            )}

            {/* List of Drive Backups & Restore Option */}
            {driveStatus.isConnected && (
              <div className="mt-3 space-y-2">
                <div className="flex items-center justify-between text-xs">
                  <span className="font-bold text-slate-700 dark:text-slate-300 flex items-center gap-1.5">
                    <Database className="w-3.5 h-3.5 text-blue-500" />
                    <span>گوگل ڈرائیو پر محفوظ شدہ بیک اپ فائلیں (Drive Backups List):</span>
                  </span>
                  <button
                    onClick={loadDriveFilesList}
                    className="text-blue-600 hover:underline flex items-center gap-1 text-[11px]"
                  >
                    <RefreshCw className={`w-3 h-3 ${isLoadingFiles ? 'animate-spin' : ''}`} />
                    <span>ریفریش فہرست</span>
                  </button>
                </div>

                {driveFiles.length === 0 ? (
                  <div className="p-3 text-center text-xs text-slate-400 bg-slate-100/50 dark:bg-slate-800/30 rounded-xl">
                    گوگل ڈرائیو پر ابھی تک کوئی بیک اپ فائل نہیں ہے۔ اوپر "Save to Drive Now" پر کلک کریں۔
                  </div>
                ) : (
                  <div className="max-h-36 overflow-y-auto space-y-1.5 pr-1">
                    {driveFiles.map((file) => (
                      <div
                        key={file.id}
                        className="p-2.5 rounded-lg border border-slate-200 dark:border-slate-800 bg-white/70 dark:bg-slate-800/60 flex items-center justify-between gap-2 text-xs"
                      >
                        <div className="truncate">
                          <span className="font-bold text-slate-800 dark:text-slate-200 block truncate">
                            {file.name}
                          </span>
                          <span className="text-[10px] text-slate-400">
                            آخری اپڈیٹ: {new Date(file.modifiedTime).toLocaleString()}
                          </span>
                        </div>

                        {onRestoreData && (
                          <button
                            onClick={() => setSelectedRestoreFile(file)}
                            className="shrink-0 px-2.5 py-1 rounded-md bg-amber-500/10 hover:bg-amber-500/20 text-amber-600 dark:text-amber-400 font-bold text-[11px] flex items-center gap-1 transition-colors cursor-pointer"
                          >
                            <RotateCcw className="w-3 h-3" />
                            <span>Restore (بحال کریں)</span>
                          </button>
                        )}
                      </div>
                    ))}
                  </div>
                )}
              </div>
            )}
          </div>

          {/* Restore Confirmation Dialog Modal */}
          {selectedRestoreFile && (
            <div className="p-4 rounded-xl border border-amber-500/40 bg-amber-500/10 space-y-3 animate-fade-in">
              <div className="flex items-start gap-2.5">
                <AlertCircle className="w-5 h-5 text-amber-600 shrink-0 mt-0.5" />
                <div>
                  <h4 className="text-xs font-bold text-amber-800 dark:text-amber-300">
                    کیا آپ واقعی گوگل ڈرائیو سے ڈیٹا بحال (Restore) کرنا چاہتے ہیں؟
                  </h4>
                  <p className="text-[11px] text-slate-600 dark:text-slate-300 mt-0.5">
                    فائل: <strong>{selectedRestoreFile.name}</strong> ({new Date(selectedRestoreFile.modifiedTime).toLocaleString()}).<br />
                    بحال کرنے سے موجودہ ڈیٹا اس بیک اپ کے مطابق اوور رائٹ ہو جائے گا۔
                  </p>
                </div>
              </div>

              <div className="flex items-center justify-end gap-2">
                <button
                  onClick={() => setSelectedRestoreFile(null)}
                  className="px-3 py-1.5 rounded-lg text-xs font-semibold text-slate-600 hover:bg-slate-200 dark:text-slate-300 dark:hover:bg-slate-700"
                >
                  منسوخ کریں (Cancel)
                </button>
                <button
                  onClick={handleConfirmRestoreFromDrive}
                  disabled={isRestoring}
                  className="px-4 py-1.5 rounded-lg bg-amber-600 hover:bg-amber-700 text-white text-xs font-bold flex items-center gap-1.5 cursor-pointer shadow-sm"
                >
                  {isRestoring ? <RefreshCw className="w-3.5 h-3.5 animate-spin" /> : <RotateCcw className="w-3.5 h-3.5" />}
                  <span>ہاں، ڈرائیو سے ڈیٹا بحال کریں (Confirm Restore)</span>
                </button>
              </div>
            </div>
          )}

          {restoreFeedback && (
            <div className="p-3 rounded-xl bg-emerald-500/10 border border-emerald-500/30 text-emerald-700 dark:text-emerald-300 text-xs font-bold">
              {restoreFeedback}
            </div>
          )}

          {/* 2. LOCAL FOLDER & STORAGE PERSISTENCE */}
          <div className="grid grid-cols-1 sm:grid-cols-2 gap-3">
            
            {/* Local Folder Auto-Save Status */}
            <div className={`p-4 rounded-xl border flex flex-col justify-between ${
              backupStatus.hasFolderAccess
                ? (isLight ? 'bg-blue-50/60 border-blue-200' : 'bg-blue-950/20 border-blue-800/50')
                : (isLight ? 'bg-slate-50 border-slate-200' : 'bg-slate-800/40 border-slate-700')
            }`}>
              <div>
                <div className="flex items-center justify-between mb-2">
                  <span className="text-xs font-bold flex items-center gap-1.5 text-blue-600 dark:text-blue-400">
                    <Folder className="w-4 h-4" />
                    <span>Downloads / Backup Folder</span>
                  </span>
                  <span className={`text-[10px] font-bold px-2 py-0.5 rounded-full ${
                    backupStatus.hasFolderAccess 
                      ? 'bg-emerald-500/10 text-emerald-600 border border-emerald-500/30' 
                      : 'bg-amber-500/10 text-amber-600 border border-amber-500/30'
                  }`}>
                    {backupStatus.hasFolderAccess ? 'Connected' : 'Not Selected'}
                  </span>
                </div>

                <p className="text-xs text-slate-600 dark:text-slate-300 mb-2">
                  {backupStatus.hasFolderAccess ? (
                    <>
                      <strong>Folder:</strong> <span className="font-mono text-blue-600 dark:text-blue-400">{backupStatus.folderName}</span>
                      <br />
                      <strong>Last Auto-Save:</strong> {formatTime(backupStatus.lastBackupTime)}
                    </>
                  ) : (
                    'اپنے کمپیوٹر کا کوئی بھی فولڈر منتخب کریں تاکہ بغیر نیٹ ورک بھی فائل لائیو محفوظ ہو۔'
                  )}
                </p>
              </div>

              <button
                onClick={handleSelectFolder}
                disabled={folderSelecting}
                className="mt-2 w-full py-2 px-3 rounded-lg bg-blue-600 hover:bg-blue-700 text-white text-xs font-bold flex items-center justify-center gap-1.5 shadow-sm transition-all cursor-pointer"
              >
                {folderSelecting ? (
                  <RefreshCw className="w-3.5 h-3.5 animate-spin" />
                ) : (
                  <Folder className="w-3.5 h-3.5" />
                )}
                <span>{backupStatus.hasFolderAccess ? 'Change Folder (فولڈر تبدیل کریں)' : 'Select Folder (فولڈر منتخب کریں)'}</span>
              </button>
            </div>

            {/* Permanent Browser Storage */}
            <div className={`p-4 rounded-xl border flex flex-col justify-between ${
              backupStatus.isPersistent
                ? (isLight ? 'bg-indigo-50/60 border-indigo-200' : 'bg-indigo-950/20 border-indigo-800/50')
                : (isLight ? 'bg-slate-50 border-slate-200' : 'bg-slate-800/40 border-slate-700')
            }`}>
              <div>
                <div className="flex items-center justify-between mb-2">
                  <span className="text-xs font-bold flex items-center gap-1.5 text-indigo-600 dark:text-indigo-400">
                    <HardDrive className="w-4 h-4" />
                    <span>Persistent Browser Storage</span>
                  </span>
                  <span className={`text-[10px] font-bold px-2 py-0.5 rounded-full ${
                    backupStatus.isPersistent 
                      ? 'bg-emerald-500/10 text-emerald-600 border border-emerald-500/30' 
                      : 'bg-slate-500/10 text-slate-500 border border-slate-500/30'
                  }`}>
                    {backupStatus.isPersistent ? 'Persisted' : 'Standard'}
                  </span>
                </div>

                <p className="text-xs text-slate-600 dark:text-slate-300 mb-2">
                  {backupStatus.isPersistent 
                    ? 'براؤزر اسٹوریج پرماننٹ ہے، ڈسک فل ہونے پر بھی ڈیٹا نہیں مٹے گا۔'
                    : 'اسٹوریج پرمیشن فعال کریں تاکہ براؤزر کیشے کلیئر ہونے پر بھی ڈیٹا محفوظ رہے۔'}
                </p>
              </div>

              {!backupStatus.isPersistent ? (
                <button
                  onClick={handleEnablePersistence}
                  className="mt-2 w-full py-2 px-3 rounded-lg bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-bold flex items-center justify-center gap-1.5 shadow-sm transition-all cursor-pointer"
                >
                  <Shield className="w-3.5 h-3.5" />
                  <span>Enable Persistent Storage</span>
                </button>
              ) : (
                <div className="mt-2 py-2 px-3 text-center text-xs font-bold text-emerald-600 dark:text-emerald-400 flex items-center justify-center gap-1">
                  <CheckCircle2 className="w-4 h-4" />
                  <span>پرماننٹ اسٹوریج فعال ہے</span>
                </div>
              )}
            </div>

          </div>

          {folderError && (
            <div className="p-3 rounded-xl bg-rose-500/10 border border-rose-500/30 text-rose-600 text-xs flex items-center gap-2">
              <AlertTriangle className="w-4 h-4 shrink-0" />
              <span>{folderError}</span>
            </div>
          )}

          {/* Cloud Sync & Pending Queue Status */}
          <div className={`p-4 rounded-xl border ${
            pendingCount > 0
              ? (isLight ? 'bg-amber-50/70 border-amber-200' : 'bg-amber-950/20 border-amber-800/40')
              : (isLight ? 'bg-slate-50 border-slate-200' : 'bg-slate-800/30 border-slate-700')
          }`}>
            <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-3">
              <div>
                <div className="flex items-center gap-2">
                  <CloudUpload className="w-4 h-4 text-blue-600 dark:text-blue-400" />
                  <h4 className="text-xs font-bold text-slate-800 dark:text-slate-100">
                    Cloud Synchronization & Offline Queue
                  </h4>
                  {pendingCount > 0 ? (
                    <span className="px-2 py-0.5 rounded-full bg-amber-500/10 text-amber-600 border border-amber-500/30 text-[10px] font-bold animate-pulse">
                      {pendingCount} Items Queued
                    </span>
                  ) : (
                    <span className="px-2 py-0.5 rounded-full bg-emerald-500/10 text-emerald-600 border border-emerald-500/30 text-[10px] font-bold">
                      All Synced
                    </span>
                  )}
                </div>
                <p className="text-[11px] text-slate-500 dark:text-slate-400 mt-1">
                  {pendingCount > 0 
                    ? `فی الحال ${pendingCount} ریکارڈز آف لائن کیو میں ہیں۔ جیسے ہی انٹرنیٹ یا کلاؤڈ کوٹہ ریفریش ہوگا خودبخود انٹرنیٹ پر اپلوڈ ہو جائیں گے۔`
                    : 'تمام لوکل ریکارڈز فائر بیس اور کلاؤڈ کے ساتھ ہم آہنگ ہیں۔'}
                </p>
              </div>

              <button
                onClick={handleForceCloudSync}
                disabled={isSyncing}
                className="shrink-0 px-3.5 py-2 rounded-xl bg-blue-700 hover:bg-blue-800 text-white text-xs font-bold flex items-center justify-center gap-1.5 shadow-sm transition-all cursor-pointer"
              >
                <RefreshCw className={`w-3.5 h-3.5 ${isSyncing ? 'animate-spin' : ''}`} />
                <span>Sync to Cloud Now (ابھی اپلوڈ کریں)</span>
              </button>
            </div>

            {syncFeedback && (
              <div className="mt-3 p-2.5 rounded-lg bg-blue-500/10 border border-blue-500/20 text-blue-700 dark:text-blue-300 text-xs font-semibold">
                {syncFeedback}
              </div>
            )}
          </div>

          {/* Quick Manual Snapshot Download */}
          <div className={`p-3 rounded-xl border flex items-center justify-between ${
            isLight ? 'bg-slate-50 border-slate-200' : 'bg-slate-800/40 border-slate-700'
          }`}>
            <div className="flex items-center gap-2.5">
              <Database className="w-4 h-4 text-purple-600" />
              <div>
                <span className="text-xs font-bold block text-slate-800 dark:text-slate-200">
                  Manual Backup File (.json)
                </span>
                <span className="text-[10px] text-slate-500">
                  کسی بھی وقت اپنے پورے شاپ کا مکمل بیک اپ سنگل کلک پر ڈاؤن لوڈ کریں
                </span>
              </div>
            </div>

            <button
              onClick={handleDownloadSnapshot}
              className="px-3 py-1.5 rounded-lg bg-purple-600 hover:bg-purple-700 text-white text-xs font-bold flex items-center gap-1 transition-all cursor-pointer"
            >
              <Download className="w-3.5 h-3.5" />
              <span>Download Backup</span>
            </button>
          </div>

        </div>

        {/* Modal Footer */}
        <div className="px-5 py-3 border-t border-slate-200 dark:border-slate-800 bg-slate-50/50 dark:bg-slate-800/30 flex items-center justify-between">
          <span className="text-[11px] text-slate-500 flex items-center gap-1">
            <Info className="w-3.5 h-3.5 text-blue-500" />
            <span>گوگل ڈرائیو اور لوکل فولڈر پر بیک اپ خود بخود محفوظ ہوتا ہے۔</span>
          </span>

          <button
            onClick={onClose}
            className="px-5 py-2 rounded-xl text-xs font-bold bg-blue-600 hover:bg-blue-700 text-white transition-all cursor-pointer shadow-md shadow-blue-600/20"
          >
            Theek Hai (OK Done)
          </button>
        </div>

      </div>
    </div>
  );
};
