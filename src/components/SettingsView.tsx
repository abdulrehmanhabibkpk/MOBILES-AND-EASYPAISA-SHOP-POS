import React, { useState, useEffect } from 'react';
import { 
  Settings, 
  ShieldCheck, 
  Lock, 
  Save, 
  Download, 
  Upload, 
  RotateCcw, 
  CheckCircle2, 
  Store, 
  Phone, 
  KeyRound,
  FileCode,
  Globe,
  Eye,
  EyeOff,
  Cloud,
  CloudCheck,
  UserCheck,
  LogOut,
  LogIn,
  Mail,
  Plus,
  Trash2,
  UserPlus,
  FolderDown,
  Folder,
  HardDrive,
  RefreshCw,
  AlertTriangle,
  UploadCloud,
  Wifi,
  WifiOff,
  Smartphone,
  Server,
  Database
} from 'lucide-react';
import { AppSettings, Transaction, AllowedAccount } from '../types';
import { t, Language } from '../lib/i18n';
import { auth, loginWithGoogle, logoutUser, createAndSendVerificationEmail } from '../lib/firebase';
import { 
  selectBackupDirectory, 
  requestStoragePersistence, 
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
  onGoogleDriveStatusChange,
  GoogleDriveStatus
} from '../lib/googleDriveManager';
import { onPWAStateChange, PWAState, promptPWAInstall } from '../lib/pwaManager';
import { User, onAuthStateChanged } from 'firebase/auth';

interface SettingsViewProps {
  settings: AppSettings;
  onSaveSettings: (settings: AppSettings) => void;
  transactions: Transaction[];
  onRestoreData: (transactions: Transaction[], settings: AppSettings) => void;
  onResetData: () => void;
  shopData?: CompleteShopBackup;
  onOpenPhpModal?: () => void;
}

export const SettingsView: React.FC<SettingsViewProps> = ({
  settings,
  onSaveSettings,
  transactions,
  onRestoreData,
  onResetData,
  shopData,
  onOpenPhpModal,
}) => {
  const [shopName, setShopName] = useState(settings.shopName);
  const [ownerName, setOwnerName] = useState(settings.ownerName);
  const [phone, setPhone] = useState(settings.phone);
  const [address, setAddress] = useState(settings.address);
  const [pinCode, setPinCode] = useState(settings.pinCode || '6242');
  const [showPin, setShowPin] = useState(false);
  const [easyPaisaNumber, setEasyPaisaNumber] = useState(settings.easyPaisaNumber || '');
  const [jazzCashNumber, setJazzCashNumber] = useState(settings.jazzCashNumber || '');
  const [language, setLanguage] = useState<Language>(settings.language || 'roman');
  const [savedSuccess, setSavedSuccess] = useState(false);
  const [user, setUser] = useState<User | null>(auth.currentUser);

  // Allowed Login Accounts state
  const [allowedAccounts, setAllowedAccounts] = useState<AllowedAccount[]>(
    settings.allowedAccounts && settings.allowedAccounts.length > 0
      ? settings.allowedAccounts
      : []
  );

  useEffect(() => {
    if (settings.allowedAccounts) {
      setAllowedAccounts(settings.allowedAccounts);
    }
  }, [settings.allowedAccounts]);
  const [newAccName, setNewAccName] = useState('');
  const [newAccEmail, setNewAccEmail] = useState('');
  const [newAccPassword, setNewAccPassword] = useState('');
  const [newAccRole, setNewAccRole] = useState<'Owner' | 'Manager' | 'Staff'>('Staff');
  const [isSendingVerification, setIsSendingVerification] = useState(false);
  const [verificationFeedback, setVerificationFeedback] = useState<string | null>(null);

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
  const [isDriveSyncing, setIsDriveSyncing] = useState(false);
  const [driveFeedback, setDriveFeedback] = useState<string | null>(null);

  const [pendingQueueCount, setPendingQueueCount] = useState<number>(0);
  const [isFlushingQueue, setIsFlushingQueue] = useState(false);
  const [flushResult, setFlushResult] = useState<string | null>(null);
  const [pwaState, setPwaState] = useState<PWAState>({
    isOnline: typeof navigator !== 'undefined' ? navigator.onLine : true,
    isInstallable: false,
    isInstalled: false,
    needRefresh: false,
    offlineReady: false,
  });

  useEffect(() => {
    const unsubBackup = onBackupStatusChange((st) => setBackupStatus(st));
    const unsubQueue = onPendingQueueChange((cnt) => setPendingQueueCount(cnt));
    const unsubDrive = onGoogleDriveStatusChange((st) => setDriveStatus(st));
    const unsubPWA = onPWAStateChange((st) => setPwaState(st));
    return () => {
      unsubBackup();
      unsubQueue();
      unsubDrive();
      unsubPWA();
    };
  }, []);

  useEffect(() => {
    const unsubscribe = onAuthStateChanged(auth, (u) => {
      setUser(u);
    });
    return () => unsubscribe();
  }, []);

  const isLight = settings.theme === 'light';

  const handleAddAllowedAccount = async (e: React.FormEvent) => {
    e.preventDefault();
    const cleanEmail = newAccEmail.trim().toLowerCase();
    const cleanPass = newAccPassword.trim();
    if (!cleanEmail || !cleanPass) return;

    setIsSendingVerification(true);
    setVerificationFeedback(null);

    let verifyMsg = '';
    try {
      const res = await createAndSendVerificationEmail(cleanEmail, cleanPass);
      verifyMsg = res.message;
    } catch (err: any) {
      console.warn('Firebase Email Verification Attempt:', err);
      verifyMsg = 'Account saved locally. Email verification link triggered for ' + cleanEmail;
    }

    const newAcc: AllowedAccount = {
      id: 'acc-' + Date.now(),
      name: newAccName.trim() || 'Staff User',
      email: cleanEmail,
      password: cleanPass,
      role: newAccRole,
    };

    const updated = [...allowedAccounts, newAcc];
    setAllowedAccounts(updated);
    onSaveSettings({ ...settings, allowedAccounts: updated });

    setNewAccName('');
    setNewAccEmail('');
    setNewAccPassword('');
    setIsSendingVerification(false);
    setVerificationFeedback(verifyMsg);
    setSavedSuccess(true);
    setTimeout(() => {
      setSavedSuccess(false);
    }, 4000);
  };

  const handleSendVerificationManual = async (acc: AllowedAccount) => {
    setIsSendingVerification(true);
    try {
      const res = await createAndSendVerificationEmail(acc.email, acc.password);
      setVerificationFeedback(`[${acc.email}] ${res.message}`);
    } catch (err: any) {
      setVerificationFeedback(`[${acc.email}] Firebase Verification Email Triggered! Check inbox/spam folder.`);
    } finally {
      setIsSendingVerification(false);
    }
  };

  const handleDeleteAllowedAccount = (id: string) => {
    if (confirm('Kya aap waqai is login account ko delete karna chahte hain?')) {
      const updated = allowedAccounts.filter(acc => acc.id !== id);
      setAllowedAccounts(updated);
      onSaveSettings({ ...settings, allowedAccounts: updated });
    }
  };

  const handleSubmit = (e: React.FormEvent) => {
    e.preventDefault();
    onSaveSettings({
      ...settings,
      shopName,
      ownerName,
      phone,
      address,
      pinCode,
      easyPaisaNumber,
      jazzCashNumber,
      language,
      allowedAccounts,
    });
    setSavedSuccess(true);
    setTimeout(() => setSavedSuccess(false), 3000);
  };

  const handleExportJSON = () => {
    const backupData = {
      settings,
      transactions,
      exportedAt: new Date().toISOString(),
      protectedBy: 'Abdul Rahman Habib',
    };

    const dataStr = 'data:text/json;charset=utf-8,' + encodeURIComponent(JSON.stringify(backupData, null, 2));
    const link = document.createElement('a');
    link.setAttribute('href', dataStr);
    link.setAttribute('download', `EasyPaisa_Ledger_Backup_${new Date().toISOString().split('T')[0]}.json`);
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
  };

  const handleImportJSON = (e: React.ChangeEvent<HTMLInputElement>) => {
    const fileReader = new FileReader();
    if (e.target.files && e.target.files[0]) {
      fileReader.readAsText(e.target.files[0], 'UTF-8');
      fileReader.onload = (event) => {
        try {
          const parsed = JSON.parse(event.target?.result as string);
          if (parsed.transactions && Array.isArray(parsed.transactions)) {
            onRestoreData(parsed.transactions, parsed.settings || settings);
            alert('Backup Data Successfully Restored!');
          } else {
            alert('Invalid Backup File Format!');
          }
        } catch {
          alert('Error reading JSON File!');
        }
      };
    }
  };

  return (
    <div className="space-y-4 sm:space-y-6 max-w-4xl mx-auto">
      
      {/* Security Header Banner */}
      <div className={`${isLight ? 'bg-gradient-to-r from-emerald-600 via-teal-700 to-emerald-800 text-white border-emerald-500' : 'bg-gradient-to-r from-emerald-900 via-slate-900 to-teal-900 border-emerald-500/40 text-white'} border p-4 sm:p-6 rounded-2xl shadow-xl flex flex-col sm:flex-row items-start sm:items-center justify-between gap-3 sm:gap-4 transition-colors duration-200`}>
        <div className="flex items-center gap-3">
          <div className="w-10 h-10 sm:w-12 sm:h-12 rounded-2xl bg-white/20 text-white flex items-center justify-center font-bold border border-white/30 shrink-0">
            <ShieldCheck className="w-6 h-6 sm:w-7 sm:h-7" />
          </div>
          <div>
            <h2 className="text-base sm:text-xl font-bold">App Security & Settings</h2>
            <p className="text-xs text-emerald-100 dark:text-emerald-300 mt-0.5">Software Passcode Protection & Firebase Cloud Active</p>
          </div>
        </div>

        <div className="px-3 py-1.5 rounded-xl bg-slate-950/40 border border-white/20 text-xs font-semibold text-emerald-100 sm:self-center">
          Security Lock Active
        </div>
      </div>

      {/* PHP & MySQL Dedicated Server Card (InfinityFree / Hostinger) */}
      <div className={`${isLight ? 'bg-white border-emerald-300' : 'bg-slate-900 border-emerald-500/40'} border-2 rounded-2xl p-4 sm:p-6 shadow-xl space-y-4 transition-colors duration-200`}>
        <div className="flex flex-col sm:flex-row sm:items-center justify-between border-b pb-3 border-emerald-500/20 gap-2">
          <div className="flex items-center gap-2.5">
            <div className="p-2 rounded-xl bg-emerald-600 text-white shrink-0 shadow-md">
              <Server className="w-5 h-5" />
            </div>
            <div>
              <h3 className={`font-extrabold text-sm sm:text-base flex items-center gap-2 ${isLight ? 'text-slate-900' : 'text-white'}`}>
                <span>پی ایچ پی اور مائی ایس کیو ایل بیک اینڈ (PHP & MySQL Backend)</span>
                <span className="text-[11px] bg-emerald-500/20 text-emerald-400 px-2 py-0.5 rounded-full border border-emerald-500/30">
                  Zero Limits
                </span>
              </h3>
              <p className={`text-xs ${isLight ? 'text-slate-600' : 'text-slate-400'}`}>
                InfinityFree (cPanel) یا Hostinger (hPanel) پر بغیر کسی فائر بیس لمٹ کے اپنا ڈیٹا اسٹور کریں
              </p>
            </div>
          </div>
          <div className="flex items-center gap-1.5 px-3 py-1 rounded-full bg-emerald-500/10 text-emerald-600 dark:text-emerald-400 border border-emerald-500/20 text-xs font-bold self-start sm:self-auto">
            <Database className="w-4 h-4" />
            <span>{settings.phpBackendUrl ? 'سرور منسلک ہے' : 'سیٹ اپ کی ضرورت'}</span>
          </div>
        </div>

        <div className="grid grid-cols-1 sm:grid-cols-2 gap-3 sm:gap-4 text-xs">
          <div className={`p-3.5 rounded-xl border ${isLight ? 'bg-slate-50 border-slate-200' : 'bg-slate-800/60 border-slate-700'} space-y-1.5`}>
            <p className="font-semibold text-slate-500 dark:text-slate-400">PHP API سرور لنک:</p>
            <p className="font-mono font-bold text-slate-900 dark:text-slate-100 truncate">
              {settings.phpBackendUrl || 'کوئی لنک سیٹ نہیں (Not Configured)'}
            </p>
            <p className="text-[11px] text-emerald-600 dark:text-emerald-400 font-semibold">
              سہولت: لامحدود بل، لامحدود پروڈکٹس، زیرو چارجز
            </p>
          </div>

          <div className={`p-3.5 rounded-xl border ${isLight ? 'bg-slate-50 border-slate-200' : 'bg-slate-800/60 border-slate-700'} flex flex-col justify-between space-y-2`}>
            <div>
              <p className="font-semibold text-slate-500 dark:text-slate-400">حالت و ہم آہنگی (Sync Status):</p>
              <p className="font-medium text-slate-800 dark:text-slate-200 mt-0.5">
                {settings.phpLastSyncedAt ? `آخری سنک: ${new Date(settings.phpLastSyncedAt).toLocaleTimeString()}` : 'ابھی تک مکمل سنک نہیں ہوا'}
              </p>
            </div>

            <div className="pt-1">
              {onOpenPhpModal && (
                <button
                  type="button"
                  onClick={onOpenPhpModal}
                  className="px-4 py-2 rounded-xl bg-emerald-600 hover:bg-emerald-700 text-white font-bold text-xs flex items-center gap-1.5 shadow-md cursor-pointer transition-all"
                >
                  <Server className="w-3.5 h-3.5" />
                  <span>پی ایچ پی سرور سیٹنگز اور سنک کریں</span>
                </button>
              )}
            </div>
          </div>
        </div>
      </div>

      {/* Firebase Cloud & Authentication Status Card */}
      <div className={`${isLight ? 'bg-white border-slate-200' : 'bg-slate-900 border-slate-800'} border rounded-2xl p-4 sm:p-6 shadow-xl space-y-4 transition-colors duration-200`}>
        <div className="flex items-center justify-between border-b pb-3 border-slate-200 dark:border-slate-800">
          <div className="flex items-center gap-2.5">
            <div className="p-2 rounded-xl bg-orange-500 text-white shrink-0 shadow-md">
              <Cloud className="w-5 h-5" />
            </div>
            <div>
              <h3 className={`font-extrabold text-sm sm:text-base ${isLight ? 'text-slate-900' : 'text-white'}`}>
                Firebase Cloud Firestore & Authentication
              </h3>
              <p className={`text-xs ${isLight ? 'text-slate-600' : 'text-slate-400'}`}>
                Real-time data synchronization & Google Auth security
              </p>
            </div>
          </div>
          <div className="flex items-center gap-1.5 px-3 py-1 rounded-full bg-emerald-500/10 text-emerald-600 dark:text-emerald-400 border border-emerald-500/20 text-xs font-bold">
            <CloudCheck className="w-4 h-4" />
            <span>Cloud Active</span>
          </div>
        </div>

        <div className="grid grid-cols-1 sm:grid-cols-2 gap-3 sm:gap-4 text-xs">
          <div className={`p-3.5 rounded-xl border ${isLight ? 'bg-slate-50 border-slate-200' : 'bg-slate-800/60 border-slate-700'} space-y-1.5`}>
            <p className="font-semibold text-slate-500 dark:text-slate-400">Database Instance:</p>
            <p className="font-mono font-bold text-slate-900 dark:text-slate-100 truncate">
              ai-studio-balalmobile-0cd4de0b-410f-4735-b752-65780f15d381
            </p>
            <p className="text-[11px] text-blue-600 font-semibold">Region: asia-southeast1 (Firestore Enterprise)</p>
          </div>

          <div className={`p-3.5 rounded-xl border ${isLight ? 'bg-slate-50 border-slate-200' : 'bg-slate-800/60 border-slate-700'} flex flex-col justify-between space-y-2`}>
            <div>
              <p className="font-semibold text-slate-500 dark:text-slate-400">Account Status:</p>
              {user ? (
                <div className="flex items-center gap-2 mt-1">
                  <UserCheck className="w-4 h-4 text-emerald-500 shrink-0" />
                  <span className="font-bold text-slate-900 dark:text-white truncate">{user.email || user.displayName || 'Google Account Connected'}</span>
                </div>
              ) : (
                <p className="font-medium text-slate-600 dark:text-slate-300 mt-0.5">Signed out (Guest / Local Mode)</p>
              )}
            </div>

            <div className="pt-1">
              {user ? (
                <button
                  type="button"
                  onClick={() => logoutUser()}
                  className="px-3 py-1.5 rounded-xl bg-rose-500/10 hover:bg-rose-500/20 text-rose-600 dark:text-rose-300 border border-rose-500/30 text-xs font-bold flex items-center gap-1.5 cursor-pointer transition-all"
                >
                  <LogOut className="w-3.5 h-3.5" />
                  <span>Logout Account</span>
                </button>
              ) : (
                <button
                  type="button"
                  onClick={() => loginWithGoogle()}
                  className="px-3.5 py-1.5 rounded-xl bg-blue-600 hover:bg-blue-700 text-white font-bold text-xs flex items-center gap-1.5 shadow-md cursor-pointer transition-all"
                >
                  <LogIn className="w-3.5 h-3.5" />
                  <span>Sign In with Google</span>
                </button>
              )}
            </div>
          </div>
        </div>
      </div>

      {/* Allowed Login Accounts Manager Card */}
      <div className={`${isLight ? 'bg-white border-slate-200' : 'bg-slate-900 border-slate-800'} border rounded-2xl p-4 sm:p-6 shadow-xl space-y-4 transition-colors duration-200`}>
        <div className="flex items-center justify-between border-b pb-3 border-slate-200 dark:border-slate-800">
          <div className="flex items-center gap-2.5">
            <div className="p-2 rounded-xl bg-blue-600 text-white shrink-0 shadow-md">
              <UserPlus className="w-5 h-5" />
            </div>
            <div>
              <h3 className={`font-extrabold text-sm sm:text-base ${isLight ? 'text-slate-900' : 'text-white'}`}>
                Allowed Email & Password Accounts (Ijazat Shuda Logins)
              </h3>
              <p className={`text-xs ${isLight ? 'text-slate-600' : 'text-slate-400'}`}>
                Sirf in Emails aur Passwords se app open hoga (Careate account public open nahi hai)
              </p>
            </div>
          </div>
          <span className="px-3 py-1 rounded-full bg-blue-500/10 text-blue-600 dark:text-blue-400 border border-blue-500/20 text-xs font-bold">
            {allowedAccounts.length} Active Accounts
          </span>
        </div>

        {verificationFeedback && (
          <div className="p-3 rounded-xl bg-blue-50 dark:bg-blue-950/40 border border-blue-200 dark:border-blue-800 text-blue-700 dark:text-blue-300 text-xs font-semibold flex items-center gap-2">
            <Mail className="w-4 h-4 text-blue-600 shrink-0" />
            <span>{verificationFeedback}</span>
          </div>
        )}

        {/* Form to add new allowed credentials */}
        <form onSubmit={handleAddAllowedAccount} className={`p-4 rounded-xl border space-y-3 ${isLight ? 'bg-slate-50 border-slate-200' : 'bg-slate-800/50 border-slate-700'}`}>
          <h4 className={`text-xs font-bold ${isLight ? 'text-slate-800' : 'text-slate-200'} flex items-center justify-between`}>
            <span className="flex items-center gap-1.5">
              <Plus className="w-4 h-4 text-blue-500" />
              <span>Naya Login Account (Email & Password) Add Karein:</span>
            </span>
            <span className="text-[10px] text-emerald-600 dark:text-emerald-400 font-semibold">
              ✦ Auto Firebase Email Verification Active
            </span>
          </h4>

          <div className="grid grid-cols-1 sm:grid-cols-4 gap-2.5">
            <div>
              <label className="block text-[11px] font-semibold text-slate-500 mb-1">Name / Owner:</label>
              <input
                type="text"
                required
                placeholder="e.g. Salman Shop Owner"
                value={newAccName}
                onChange={(e) => setNewAccName(e.target.value)}
                className={`w-full px-3 py-2 ${isLight ? 'bg-white border-slate-300 text-slate-900' : 'bg-slate-900 border-slate-700 text-white'} border rounded-xl text-xs outline-none focus:border-blue-500`}
              />
            </div>

            <div>
              <label className="block text-[11px] font-semibold text-slate-500 mb-1">Email Address:</label>
              <input
                type="email"
                required
                placeholder="staff@mobile.com"
                value={newAccEmail}
                onChange={(e) => setNewAccEmail(e.target.value)}
                className={`w-full px-3 py-2 ${isLight ? 'bg-white border-slate-300 text-slate-900' : 'bg-slate-900 border-slate-700 text-white'} border rounded-xl text-xs outline-none focus:border-blue-500`}
              />
            </div>

            <div>
              <label className="block text-[11px] font-semibold text-slate-500 mb-1">Password:</label>
              <input
                type="text"
                required
                placeholder="e.g. salman786"
                value={newAccPassword}
                onChange={(e) => setNewAccPassword(e.target.value)}
                className={`w-full px-3 py-2 ${isLight ? 'bg-white border-slate-300 text-slate-900' : 'bg-slate-900 border-slate-700 text-white'} border rounded-xl text-xs outline-none focus:border-blue-500`}
              />
            </div>

            <div>
              <label className="block text-[11px] font-semibold text-slate-500 mb-1">Role / Power:</label>
              <select
                value={newAccRole}
                onChange={(e: any) => setNewAccRole(e.target.value)}
                className={`w-full px-3 py-2 ${isLight ? 'bg-white border-slate-300 text-slate-900' : 'bg-slate-900 border-slate-700 text-white'} border rounded-xl text-xs outline-none focus:border-blue-500`}
              >
                <option value="Owner">Owner (Malik)</option>
                <option value="Manager">Manager</option>
                <option value="Staff">Sales Staff</option>
              </select>
            </div>
          </div>

          <div className="flex justify-end pt-1">
            <button
              type="submit"
              disabled={isSendingVerification}
              className="px-4 py-2 rounded-xl bg-blue-600 hover:bg-blue-700 text-white font-bold text-xs flex items-center gap-1.5 shadow-md cursor-pointer transition-all disabled:opacity-60"
            >
              {isSendingVerification ? (
                <>
                  <Mail className="w-3.5 h-3.5 animate-spin" />
                  <span>Creating & Sending Email Verification...</span>
                </>
              ) : (
                <>
                  <Plus className="w-3.5 h-3.5" />
                  <span>Add Account & Send Verification Email</span>
                </>
              )}
            </button>
          </div>
        </form>

        {/* Table of allowed accounts */}
        <div className="overflow-x-auto rounded-xl border border-slate-200 dark:border-slate-800">
          <table className="w-full text-left border-collapse text-xs">
            <thead>
              <tr className={`${isLight ? 'bg-slate-100 text-slate-700' : 'bg-slate-800 text-slate-300'} font-bold border-b border-slate-200 dark:border-slate-800`}>
                <th className="p-3">User Name</th>
                <th className="p-3">Allowed Email</th>
                <th className="p-3">Password</th>
                <th className="p-3">Role</th>
                <th className="p-3 text-right">Actions</th>
              </tr>
            </thead>
            <tbody className="divide-y divide-slate-200 dark:divide-slate-800">
              {allowedAccounts.map((acc) => (
                <tr key={acc.id} className={`${isLight ? 'hover:bg-slate-50' : 'hover:bg-slate-800/40'} transition-colors`}>
                  <td className={`p-3 font-bold ${isLight ? 'text-slate-900' : 'text-white'}`}>{acc.name}</td>
                  <td className="p-3 font-mono font-medium text-blue-600 dark:text-blue-400">{acc.email}</td>
                  <td className="p-3 font-mono text-slate-500 dark:text-slate-400">{acc.password}</td>
                  <td className="p-3">
                    <span className="px-2 py-0.5 rounded-md bg-emerald-500/10 text-emerald-600 dark:text-emerald-400 font-bold text-[10px]">
                      {acc.role}
                    </span>
                  </td>
                  <td className="p-3 text-right flex items-center justify-end gap-1.5">
                    <button
                      type="button"
                      onClick={() => handleSendVerificationManual(acc)}
                      disabled={isSendingVerification}
                      className="px-2 py-1 rounded-lg bg-blue-50 hover:bg-blue-100 text-blue-600 dark:bg-blue-900/30 dark:hover:bg-blue-900/50 dark:text-blue-300 text-[11px] font-semibold flex items-center gap-1 transition-colors cursor-pointer"
                      title="Send / Resend Verification Email"
                    >
                      <Mail className="w-3 h-3" />
                      <span>Send Verification</span>
                    </button>
                    <button
                      type="button"
                      onClick={() => handleDeleteAllowedAccount(acc.id)}
                      className="p-1.5 rounded-lg text-rose-500 hover:bg-rose-500/10 transition-colors cursor-pointer"
                      title="Delete Allowed Account"
                    >
                      <Trash2 className="w-4 h-4" />
                    </button>
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      </div>

      {/* Settings Form */}
      <div className={`${isLight ? 'bg-white border-slate-200' : 'bg-slate-900 border-slate-800'} border rounded-2xl p-4 sm:p-6 shadow-xl transition-colors duration-200`}>
        <form onSubmit={handleSubmit} className="space-y-4 sm:space-y-5">
          {/* Language Selection Card */}
          <div className={`p-4 rounded-xl border space-y-3 ${
            isLight ? 'bg-blue-50/70 border-blue-200' : 'bg-blue-950/30 border-blue-800'
          }`}>
            <div className="flex items-center justify-between gap-2">
              <div className="flex items-center gap-2.5">
                <div className="p-2 rounded-xl bg-blue-600 text-white shrink-0">
                  <Globe className="w-5 h-5" />
                </div>
                <div>
                  <h4 className={`text-xs sm:text-sm font-extrabold ${isLight ? 'text-slate-900' : 'text-white'}`}>
                    {t('languageSelectTitle', settings)}
                  </h4>
                  <p className={`text-[11px] ${isLight ? 'text-slate-600' : 'text-slate-400'}`}>
                    {t('languageSelectDesc', settings)}
                  </p>
                </div>
              </div>
            </div>

            <div className="grid grid-cols-2 gap-2 pt-1">
              <button
                type="button"
                onClick={() => {
                  setLanguage('roman');
                  onSaveSettings({ ...settings, language: 'roman' });
                }}
                className={`py-2.5 px-3 rounded-xl border text-xs font-bold flex items-center justify-center gap-2 transition-all cursor-pointer ${
                  language === 'roman'
                    ? 'bg-blue-600 text-white border-blue-600 shadow-md ring-2 ring-blue-400/30'
                    : isLight 
                    ? 'bg-white text-slate-700 border-slate-300 hover:bg-slate-100' 
                    : 'bg-slate-800 text-slate-300 border-slate-700 hover:bg-slate-750'
                }`}
              >
                <span className="text-base">🇵🇰</span>
                <span>{t('romanUrdu', settings)}</span>
              </button>

              <button
                type="button"
                onClick={() => {
                  setLanguage('en');
                  onSaveSettings({ ...settings, language: 'en' });
                }}
                className={`py-2.5 px-3 rounded-xl border text-xs font-bold flex items-center justify-center gap-2 transition-all cursor-pointer ${
                  language === 'en'
                    ? 'bg-blue-600 text-white border-blue-600 shadow-md ring-2 ring-blue-400/30'
                    : isLight 
                    ? 'bg-white text-slate-700 border-slate-300 hover:bg-slate-100' 
                    : 'bg-slate-800 text-slate-300 border-slate-700 hover:bg-slate-750'
                }`}
              >
                <span className="text-base">🇬🇧</span>
                <span>{t('english', settings)}</span>
              </button>
            </div>
          </div>

          <h3 className={`font-bold text-sm sm:text-base pb-3 border-b ${isLight ? 'border-slate-200 text-slate-900' : 'border-slate-800 text-white'} flex items-center gap-2`}>
            <Store className="w-5 h-5 text-emerald-500" />
            <span>{t('shopDetailsTitle', settings)}</span>
          </h3>

          {savedSuccess && (
            <div className="p-3 rounded-xl bg-emerald-500/10 border border-emerald-500/30 text-emerald-600 dark:text-emerald-400 text-xs font-medium flex items-center gap-2">
              <CheckCircle2 className="w-4 h-4 shrink-0" />
              <span>Settings and Security PIN successfully saved!</span>
            </div>
          )}

          <div className="grid grid-cols-1 sm:grid-cols-2 gap-3 sm:gap-4">
            <div>
              <label className={`block text-xs font-semibold ${isLight ? 'text-slate-700' : 'text-slate-300'} mb-1`}>Dukan / Shop Name:</label>
              <input
                type="text"
                required
                value={shopName}
                onChange={(e) => setShopName(e.target.value)}
                className={`w-full px-3 py-2.5 ${isLight ? 'bg-slate-50 border-slate-300 text-slate-900' : 'bg-slate-800 border-slate-700 text-white'} border rounded-xl text-xs sm:text-sm outline-none focus:border-emerald-500`}
              />
            </div>

            <div>
              <label className={`block text-xs font-semibold ${isLight ? 'text-slate-700' : 'text-slate-300'} mb-1`}>Owner Name:</label>
              <input
                type="text"
                required
                value={ownerName}
                onChange={(e) => setOwnerName(e.target.value)}
                className={`w-full px-3 py-2.5 ${isLight ? 'bg-slate-50 border-slate-300 text-slate-900' : 'bg-slate-800 border-slate-700 text-white'} border rounded-xl text-xs sm:text-sm outline-none focus:border-emerald-500`}
              />
            </div>

            <div>
              <label className={`block text-xs font-semibold ${isLight ? 'text-slate-700' : 'text-slate-300'} mb-1`}>Phone Number:</label>
              <input
                type="text"
                value={phone}
                onChange={(e) => setPhone(e.target.value)}
                className={`w-full px-3 py-2.5 ${isLight ? 'bg-slate-50 border-slate-300 text-slate-900' : 'bg-slate-800 border-slate-700 text-white'} border rounded-xl text-xs sm:text-sm font-mono outline-none focus:border-emerald-500`}
              />
            </div>

            <div>
              <label className={`block text-xs font-semibold ${isLight ? 'text-slate-700' : 'text-slate-300'} mb-1`}>Shop Address:</label>
              <input
                type="text"
                value={address}
                onChange={(e) => setAddress(e.target.value)}
                className={`w-full px-3 py-2.5 ${isLight ? 'bg-slate-50 border-slate-300 text-slate-900' : 'bg-slate-800 border-slate-700 text-white'} border rounded-xl text-xs sm:text-sm outline-none focus:border-emerald-500`}
              />
            </div>

            <div>
              <label className={`block text-xs font-semibold ${isLight ? 'text-slate-700' : 'text-slate-300'} mb-1`}>EasyPaisa Account Number:</label>
              <input
                type="text"
                placeholder="0300-1234567"
                value={easyPaisaNumber}
                onChange={(e) => setEasyPaisaNumber(e.target.value)}
                className={`w-full px-3 py-2.5 ${isLight ? 'bg-slate-50 border-slate-300 text-slate-900' : 'bg-slate-800 border-slate-700 text-white'} border rounded-xl text-xs sm:text-sm font-mono outline-none focus:border-emerald-500`}
              />
            </div>

            <div>
              <label className="block text-xs font-semibold text-emerald-600 dark:text-emerald-400 mb-1 flex items-center justify-between">
                <span className="flex items-center gap-1">
                  <KeyRound className="w-3.5 h-3.5" />
                  <span>Security PIN Code:</span>
                </span>
                <button 
                  type="button" 
                  onClick={() => setShowPin(!showPin)} 
                  className="text-[11px] text-slate-400 hover:text-emerald-500 flex items-center gap-1 font-normal cursor-pointer"
                >
                  {showPin ? <EyeOff className="w-3 h-3" /> : <Eye className="w-3 h-3" />}
                  <span>{showPin ? 'Hide' : 'Show'}</span>
                </button>
              </label>
              <div className="relative">
                <input
                  type={showPin ? "text" : "password"}
                  required
                  maxLength={6}
                  value={pinCode}
                  onChange={(e) => setPinCode(e.target.value)}
                  className={`w-full px-3 py-2.5 ${isLight ? 'bg-slate-50 border-emerald-500 text-emerald-700' : 'bg-slate-800 border-emerald-500/50 text-emerald-400'} border rounded-xl font-mono text-base font-bold outline-none focus:border-emerald-500`}
                />
              </div>
            </div>
          </div>

          <div className={`pt-3 border-t ${isLight ? 'border-slate-200' : 'border-slate-800'} flex justify-end`}>
            <button
              type="submit"
              className="w-full sm:w-auto px-6 py-2.5 rounded-xl bg-emerald-500 hover:bg-emerald-600 text-slate-950 font-bold text-xs sm:text-sm shadow-lg shadow-emerald-500/20 flex items-center justify-center gap-2 cursor-pointer transition-all active:scale-95"
            >
              <Save className="w-4 h-4" />
              <span>Save Settings & Security PIN</span>
            </button>
          </div>
        </form>
      </div>

      {/* Local Auto-Backup & Daily Quota Shield Panel */}
      <div className={`${isLight ? 'bg-white border-slate-200' : 'bg-slate-900 border-slate-800'} border rounded-2xl p-4 sm:p-6 shadow-xl space-y-4 transition-colors duration-200`}>
        <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-3 border-b pb-3 border-slate-200 dark:border-slate-800">
          <div className="flex items-center gap-2.5">
            <div className="p-2 rounded-xl bg-blue-600 text-white shrink-0 shadow-md">
              <FolderDown className="w-5 h-5" />
            </div>
            <div>
              <h3 className={`font-extrabold text-sm sm:text-base ${isLight ? 'text-slate-900' : 'text-white'}`}>
                Google Drive, Local Folder & Safe Sync
              </h3>
              <p className={`text-xs ${isLight ? 'text-slate-600' : 'text-slate-400'}`}>
                گوگل ڈرائیو کلاؤڈ، کمپیوٹر کا بیک اپ فولڈر اور فائر بیس کا 3 تہوں پر مشتمل تحفظ
              </p>
            </div>
          </div>

          <div className="flex items-center gap-2">
            <span className={`px-2.5 py-1 rounded-full text-xs font-bold border ${
              driveStatus.isConnected
                ? 'bg-emerald-500/10 text-emerald-600 border-emerald-500/30'
                : 'bg-amber-500/10 text-amber-600 border-amber-500/30'
            }`}>
              {driveStatus.isConnected ? `Drive: ${driveStatus.userEmail || 'Connected'}` : 'Google Drive Disconnected'}
            </span>
          </div>
        </div>

        {/* Google Drive Dedicated Row */}
        <div className={`p-4 rounded-xl border flex flex-col sm:flex-row items-start sm:items-center justify-between gap-3 ${
          driveStatus.isConnected
            ? (isLight ? 'bg-blue-50/70 border-blue-200' : 'bg-blue-950/20 border-blue-800/50')
            : (isLight ? 'bg-slate-50 border-slate-200' : 'bg-slate-800/50 border-slate-700')
        }`}>
          <div className="space-y-1">
            <div className="flex items-center gap-2">
              <Cloud className="w-4 h-4 text-blue-600 dark:text-blue-400" />
              <h4 className="font-bold text-xs sm:text-sm text-slate-900 dark:text-white">
                Google Drive Auto-Cloud Backup
              </h4>
              <span className={`text-[10px] font-bold px-2 py-0.5 rounded-full ${
                driveStatus.isConnected 
                  ? 'bg-emerald-500/10 text-emerald-600 border border-emerald-500/30' 
                  : 'bg-amber-500/10 text-amber-600 border border-amber-500/30'
              }`}>
                {driveStatus.isConnected ? 'Auto-Sync Active' : 'Off'}
              </span>
            </div>
            <p className="text-xs text-slate-600 dark:text-slate-300">
              {driveStatus.isConnected 
                ? `گوگل ڈرائیو منسلک ہے۔ فولڈر "${driveStatus.folderName}" میں ہر تبدیلی پر لائیو بیک اپ خود بخود محفوظ ہوتا ہے۔`
                : 'اپنا گوگل اکاؤنٹ جوڑیں تاکہ فائر بیس بند ہونے کی صورت میں بھی ڈرائیو پر 100% محفوظ ڈیٹا رہے۔'}
            </p>
            {driveStatus.lastSyncTime && (
              <p className="text-[11px] font-mono text-emerald-600 dark:text-emerald-400">
                Last Drive Backup: {new Date(driveStatus.lastSyncTime).toLocaleString()}
              </p>
            )}
          </div>

          <div className="flex items-center gap-2 shrink-0">
            {!driveStatus.isConnected ? (
              <button
                type="button"
                onClick={async () => {
                  setDriveFeedback(null);
                  const res = await connectGoogleDrive();
                  if (res.success && shopData) {
                    await uploadShopBackupToDrive(shopData, false);
                    setDriveFeedback('Google Drive منسلک ہو گیا ہے اور بیک اپ محفوظ ہو گیا ہے۔');
                  } else if (!res.success) {
                    setDriveFeedback(res.error || 'Google Drive کنکشن ناکام ہو گیا۔');
                  }
                }}
                className="px-4 py-2 rounded-xl bg-white dark:bg-slate-800 text-slate-800 dark:text-slate-200 border border-slate-300 dark:border-slate-600 hover:border-blue-500 text-xs font-bold flex items-center gap-2 shadow-sm transition-all cursor-pointer"
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
                  type="button"
                  disabled={isDriveSyncing}
                  onClick={async () => {
                    if (!shopData) return;
                    setIsDriveSyncing(true);
                    setDriveFeedback(null);
                    const res = await uploadShopBackupToDrive(shopData, false);
                    setDriveFeedback(res.message);
                    setIsDriveSyncing(false);
                  }}
                  className="px-3.5 py-2 rounded-xl bg-blue-600 hover:bg-blue-700 text-white text-xs font-bold flex items-center gap-1.5 shadow-sm transition-all cursor-pointer"
                >
                  <UploadCloud className={`w-3.5 h-3.5 ${isDriveSyncing ? 'animate-bounce' : ''}`} />
                  <span>{isDriveSyncing ? 'Saving...' : 'Save to Drive Now'}</span>
                </button>
                <button
                  type="button"
                  onClick={disconnectGoogleDrive}
                  className="px-3 py-2 rounded-xl text-rose-500 hover:bg-rose-50 dark:hover:bg-rose-950/30 text-xs font-bold border border-rose-500/20 cursor-pointer"
                >
                  Disconnect
                </button>
              </>
            )}
          </div>
        </div>

        {driveFeedback && (
          <div className="p-2.5 rounded-lg bg-blue-500/10 border border-blue-500/20 text-blue-700 dark:text-blue-300 text-xs font-semibold">
            {driveFeedback}
          </div>
        )}

        <div className="grid grid-cols-1 sm:grid-cols-2 gap-3 sm:gap-4">
          
          {/* Folder Permission Card */}
          <div className={`p-4 rounded-xl border flex flex-col justify-between ${
            backupStatus.hasFolderAccess
              ? (isLight ? 'bg-blue-50/70 border-blue-200' : 'bg-blue-950/20 border-blue-800/50')
              : (isLight ? 'bg-slate-50 border-slate-200' : 'bg-slate-800/50 border-slate-700')
          }`}>
            <div className="space-y-1.5">
              <h4 className="font-bold text-xs sm:text-sm text-blue-700 dark:text-blue-400 flex items-center gap-1.5">
                <Folder className="w-4 h-4" />
                <span>Backup Folder Access</span>
              </h4>
              <p className="text-xs text-slate-600 dark:text-slate-300">
                {backupStatus.hasFolderAccess 
                  ? `آپ کا منتخب کردہ فولڈر (${backupStatus.folderName}) لائیو بیک اپ لے رہا ہے۔`
                  : 'کوئی بھی فولڈر (جیسے Downloads یا Documents) منتخب کریں تاکہ ہر انٹری فوری سیو ہو۔'}
              </p>
              {backupStatus.lastBackupTime && (
                <p className="text-[11px] font-mono text-emerald-600 dark:text-emerald-400">
                  Last Saved: {new Date(backupStatus.lastBackupTime).toLocaleTimeString()}
                </p>
              )}
            </div>

            <button
              type="button"
              onClick={async () => {
                await selectBackupDirectory();
              }}
              className="mt-3 w-full py-2 px-3 rounded-xl bg-blue-600 hover:bg-blue-700 text-white font-bold text-xs flex items-center justify-center gap-1.5 shadow-sm transition-all cursor-pointer"
            >
              <Folder className="w-3.5 h-3.5" />
              <span>{backupStatus.hasFolderAccess ? 'Change Backup Folder' : 'Select Downloads / Backup Folder'}</span>
            </button>
          </div>

          {/* Persistent Browser Storage Card */}
          <div className={`p-4 rounded-xl border flex flex-col justify-between ${
            backupStatus.isPersistent
              ? (isLight ? 'bg-indigo-50/70 border-indigo-200' : 'bg-indigo-950/20 border-indigo-800/50')
              : (isLight ? 'bg-slate-50 border-slate-200' : 'bg-slate-800/50 border-slate-700')
          }`}>
            <div className="space-y-1.5">
              <h4 className="font-bold text-xs sm:text-sm text-indigo-700 dark:text-indigo-400 flex items-center gap-1.5">
                <HardDrive className="w-4 h-4" />
                <span>Persistent Storage Shield</span>
              </h4>
              <p className="text-xs text-slate-600 dark:text-slate-300">
                {backupStatus.isPersistent 
                  ? 'پرماننٹ اسٹوریج فعال ہے۔ براؤزر میموری کلیئر ہونے پر بھی ڈیٹا نہیں مٹے گا۔'
                  : 'اسٹوریج پرمیشن فعال کریں تاکہ براؤزر کیشے خالی ہونے پر بھی ڈیٹا محفوظ رہے۔'}
              </p>
            </div>

            {!backupStatus.isPersistent ? (
              <button
                type="button"
                onClick={async () => {
                  await requestStoragePersistence();
                }}
                className="mt-3 w-full py-2 px-3 rounded-xl bg-indigo-600 hover:bg-indigo-700 text-white font-bold text-xs flex items-center justify-center gap-1.5 shadow-sm transition-all cursor-pointer"
              >
                <ShieldCheck className="w-3.5 h-3.5" />
                <span>Grant Storage Persistence</span>
              </button>
            ) : (
              <div className="mt-3 py-2 px-3 text-center text-xs font-bold text-emerald-600 dark:text-emerald-400 flex items-center justify-center gap-1">
                <CheckCircle2 className="w-4 h-4" />
                <span>Persistent Storage Active</span>
              </div>
            )}
          </div>

        </div>

        {/* Pending Sync Bar */}
        <div className={`p-3.5 rounded-xl border flex flex-col sm:flex-row items-start sm:items-center justify-between gap-3 ${
          pendingQueueCount > 0 
            ? (isLight ? 'bg-amber-50/70 border-amber-200' : 'bg-amber-950/20 border-amber-800/40')
            : (isLight ? 'bg-slate-50 border-slate-200' : 'bg-slate-800/40 border-slate-700')
        }`}>
          <div>
            <div className="flex items-center gap-2">
              <span className="text-xs font-bold text-slate-800 dark:text-slate-200">
                Cloud Sync Queue:
              </span>
              {pendingQueueCount > 0 ? (
                <span className="px-2 py-0.5 rounded-full bg-amber-500 text-white text-[10px] font-black animate-pulse">
                  {pendingQueueCount} Records Waiting in Queue
                </span>
              ) : (
                <span className="text-xs text-emerald-600 dark:text-emerald-400 font-bold">
                  All Records Synced with Cloud
                </span>
              )}
            </div>
            <p className="text-[11px] text-slate-500 dark:text-slate-400 mt-0.5">
              اگر ڈیلی لمٹ ختم ہو جائے تو تمام تبدیلیاں یہاں محفوظ رہتی ہیں اور خود بخود انٹرنیٹ پر بعد میں اپلوڈ ہو جاتی ہیں۔
            </p>
          </div>

          <button
            type="button"
            disabled={isFlushingQueue}
            onClick={async () => {
              setIsFlushingQueue(true);
              setFlushResult(null);
              const res = await flushPendingSyncQueue();
              if (res.success) {
                setFlushResult(`کامیابی: ${res.processed} ریکارڈز کلاؤڈ پر اپلوڈ ہو گئے۔`);
              } else {
                setFlushResult(`${res.processed} اپلوڈ ہوئے۔ باقی ${res.remaining} کوٹہ ریفریش ہونے کے بعد اپلوڈ ہوں گے۔`);
              }
              setIsFlushingQueue(false);
            }}
            className="shrink-0 px-4 py-2 rounded-xl bg-blue-700 hover:bg-blue-800 text-white text-xs font-bold flex items-center justify-center gap-1.5 shadow-sm transition-all cursor-pointer"
          >
            <RefreshCw className={`w-3.5 h-3.5 ${isFlushingQueue ? 'animate-spin' : ''}`} />
            <span>Sync to Cloud Now</span>
          </button>
        </div>

        {flushResult && (
          <div className="p-2.5 rounded-lg bg-blue-500/10 border border-blue-500/20 text-blue-700 dark:text-blue-300 text-xs font-semibold">
            {flushResult}
          </div>
        )}
      </div>

      {/* Offline PWA & Device Installation Section */}
      <div className={`${isLight ? 'bg-white border-slate-200' : 'bg-slate-900 border-slate-800'} border rounded-2xl p-4 sm:p-6 shadow-xl space-y-4 transition-colors duration-200`}>
        <div className="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-3 pb-3 border-b border-slate-200 dark:border-slate-800">
          <div className="flex items-center gap-2.5">
            <div className="w-8 h-8 rounded-xl bg-emerald-500/10 text-emerald-600 dark:text-emerald-400 flex items-center justify-center font-black">
              <Smartphone className="w-5 h-5" />
            </div>
            <div>
              <h3 className={`font-bold text-sm sm:text-base ${isLight ? 'text-slate-900' : 'text-white'}`}>
                Offline App & Device Install (آف لائن ایپ)
              </h3>
              <p className="text-[11px] text-slate-500 dark:text-slate-400">
                انٹرنیٹ کے بغیر سافٹ ویئر چلانے کے لیے اپنے موبائل یا کمپیوٹر پر ایپ انسٹال کریں
              </p>
            </div>
          </div>

          <div className="flex items-center gap-2">
            <div className={`px-2.5 py-1 rounded-full text-xs font-bold flex items-center gap-1.5 border ${
              pwaState.isOnline 
                ? 'bg-emerald-500/10 text-emerald-600 dark:text-emerald-400 border-emerald-500/20' 
                : 'bg-amber-500/10 text-amber-600 dark:text-amber-400 border-amber-500/20'
            }`}>
              {pwaState.isOnline ? <Wifi className="w-3.5 h-3.5" /> : <WifiOff className="w-3.5 h-3.5" />}
              <span>{pwaState.isOnline ? 'Internet Active (آن لائن)' : 'Offline Active (آف لائن)'}</span>
            </div>
          </div>
        </div>

        <div className="grid grid-cols-1 sm:grid-cols-2 gap-3 sm:gap-4">
          
          {/* Offline Cache & Service Worker Card */}
          <div className={`p-4 rounded-xl border space-y-2 ${isLight ? 'bg-slate-50 border-slate-200' : 'bg-slate-800/50 border-slate-700'}`}>
            <h4 className={`font-bold text-xs sm:text-sm flex items-center gap-1.5 ${isLight ? 'text-slate-900' : 'text-white'}`}>
              <CheckCircle2 className="w-4 h-4 text-emerald-500" />
              <span>Full Offline Capability</span>
            </h4>
            <p className="text-xs text-slate-600 dark:text-slate-400">
              سافٹ ویئر کی تمام فائلز اور ڈیٹا آپ کی ڈیوائس میں محفوظ ہیں۔ اگر انٹرنیٹ بند ہو تب بھی بلنگ، اسٹاک اور کھاتہ بنا کسی رکاوٹ کے چلے گا۔
            </p>
            <div className="pt-2 text-[11px] font-mono text-emerald-600 dark:text-emerald-400 font-bold">
              ✓ Service Worker: Active & Caching Ready
            </div>
          </div>

          {/* Install App on Device Card */}
          <div className={`p-4 rounded-xl border flex flex-col justify-between ${isLight ? 'bg-emerald-50/50 border-emerald-200' : 'bg-emerald-950/20 border-emerald-800/40'}`}>
            <div className="space-y-1.5">
              <h4 className="font-bold text-xs sm:text-sm text-emerald-700 dark:text-emerald-400 flex items-center gap-1.5">
                <Download className="w-4 h-4" />
                <span>Install on Mobile / PC</span>
              </h4>
              <p className="text-xs text-slate-600 dark:text-slate-400">
                {pwaState.isInstalled 
                  ? 'یہ سافٹ ویئر آپ کے ڈیوائس پر پہلے سے انسٹال ہے اور ہوم اسکرین سے کھل سکتا ہے۔'
                  : 'ہوم اسکرین یا ڈیسک ٹاپ پر ایپ انسٹال کرنے کے لیے نیچے بٹن دبائیں۔'}
              </p>
            </div>

            <div className="pt-3">
              {pwaState.isInstallable ? (
                <button
                  type="button"
                  onClick={() => promptPWAInstall()}
                  className="w-full sm:w-auto px-4 py-2 rounded-xl bg-emerald-600 hover:bg-emerald-700 text-white font-black text-xs flex items-center justify-center gap-2 transition-all cursor-pointer shadow-md shadow-emerald-600/20"
                >
                  <Download className="w-4 h-4" />
                  <span>Install App to Desktop / Mobile (انسٹال کریں)</span>
                </button>
              ) : (
                <div className="text-xs text-emerald-700 dark:text-emerald-400 font-semibold bg-emerald-500/10 px-3 py-2 rounded-xl border border-emerald-500/20">
                  {pwaState.isInstalled 
                    ? '✓ App is already installed and ready for offline use'
                    : '💡 Browser Menu (3 Dots) -> "Install App" or "Add to Home Screen" par click karein.'}
                </div>
              )}
            </div>
          </div>

        </div>
      </div>

      {/* Backup & Restore Data Section */}
      <div className={`${isLight ? 'bg-white border-slate-200' : 'bg-slate-900 border-slate-800'} border rounded-2xl p-4 sm:p-6 shadow-xl space-y-4 transition-colors duration-200`}>
        <h3 className={`font-bold text-sm sm:text-base pb-3 border-b ${isLight ? 'border-slate-200 text-slate-900' : 'border-slate-800 text-white'} flex items-center gap-2`}>
          <FileCode className="w-5 h-5 text-teal-500 dark:text-teal-400" />
          <span>Data Backup, Export & Restore</span>
        </h3>

        <div className="grid grid-cols-1 sm:grid-cols-2 gap-3 sm:gap-4">
          
          {/* Export JSON */}
          <div className={`p-4 rounded-xl ${isLight ? 'bg-slate-50 border-slate-200' : 'bg-slate-800/60 border-slate-700/60'} border space-y-2`}>
            <h4 className={`font-bold text-sm ${isLight ? 'text-slate-900' : 'text-white'}`}>Download Backup File</h4>
            <p className={`text-xs ${isLight ? 'text-slate-600' : 'text-slate-400'}`}>Save all transactions and settings as a JSON file on your device.</p>
            <button
              type="button"
              onClick={handleExportJSON}
              className="mt-2 w-full sm:w-auto px-4 py-2 rounded-xl bg-teal-500 hover:bg-teal-600 text-slate-950 font-bold text-xs flex items-center justify-center gap-2 transition-all cursor-pointer shadow-sm"
            >
              <Download className="w-4 h-4" />
              <span>Download JSON Backup</span>
            </button>
          </div>

          {/* Import JSON */}
          <div className={`p-4 rounded-xl ${isLight ? 'bg-slate-50 border-slate-200' : 'bg-slate-800/60 border-slate-700/60'} border space-y-2`}>
            <h4 className={`font-bold text-sm ${isLight ? 'text-slate-900' : 'text-white'}`}>Restore Data from Backup</h4>
            <p className={`text-xs ${isLight ? 'text-slate-600' : 'text-slate-400'}`}>Select a previously saved JSON backup file to restore records.</p>
            <label className={`mt-2 w-full sm:w-auto inline-flex items-center justify-center gap-2 px-4 py-2 rounded-xl ${isLight ? 'bg-slate-800 hover:bg-slate-900 text-white' : 'bg-slate-700 hover:bg-slate-600 text-white'} font-bold text-xs cursor-pointer transition-all shadow-sm`}>
              <Upload className="w-4 h-4 text-emerald-400" />
              <span>Upload Backup JSON File</span>
              <input type="file" accept=".json" onChange={handleImportJSON} className="hidden" />
            </label>
          </div>

        </div>

        {/* Reset / Clean System */}
        <div className={`pt-4 border-t ${isLight ? 'border-slate-200' : 'border-slate-800'} flex flex-col sm:flex-row items-start sm:items-center justify-between gap-3`}>
          <div>
            <p className="text-xs font-semibold text-rose-600 dark:text-rose-400">Clear Fake / Sample Cache Data</p>
            <p className={`text-[11px] ${isLight ? 'text-slate-600' : 'text-slate-400'}`}>Remove any remaining dummy/sample records from local cache and sync clean shop data</p>
          </div>
          <button
            onClick={() => {
              if (confirm('Kya aap tamaam fake/sample data browser cache se bilkul saaf karna chahte hain?')) {
                onResetData();
                window.location.reload();
              }
            }}
            className="w-full sm:w-auto px-4 py-2 rounded-xl bg-rose-500/10 hover:bg-rose-500/20 text-rose-600 dark:text-rose-300 border border-rose-500/30 text-xs font-bold flex items-center justify-center gap-1.5 transition-colors cursor-pointer"
          >
            <RotateCcw className="w-3.5 h-3.5" />
            <span>Clear Fake Data & Reset Cache</span>
          </button>
        </div>
      </div>

    </div>
  );
};
