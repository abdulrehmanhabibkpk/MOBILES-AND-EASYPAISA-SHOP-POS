import React, { useEffect, useState } from 'react';
import { 
  WifiOff, 
  Wifi, 
  Download, 
  RefreshCw, 
  CheckCircle2, 
  Smartphone, 
  Laptop, 
  X, 
  ShieldCheck 
} from 'lucide-react';
import { 
  PWAState, 
  onPWAStateChange, 
  promptPWAInstall, 
  applyPWAUpdate 
} from '../lib/pwaManager';

export const OfflineIndicatorBanner: React.FC = () => {
  const [pwaState, setPwaState] = useState<PWAState>({
    isOnline: typeof navigator !== 'undefined' ? navigator.onLine : true,
    isInstallable: false,
    isInstalled: false,
    needRefresh: false,
    offlineReady: false,
  });

  const [dismissOfflineNotice, setDismissOfflineNotice] = useState(false);
  const [showInstallPrompt, setShowInstallPrompt] = useState(true);

  useEffect(() => {
    const unsub = onPWAStateChange((state) => {
      setPwaState(state);
      if (state.isOnline) {
        setDismissOfflineNotice(false);
      }
    });
    return unsub;
  }, []);

  return (
    <>
      {/* 1. Offline Mode Alert Banner */}
      {!pwaState.isOnline && !dismissOfflineNotice && (
        <div className="bg-gradient-to-r from-amber-600 via-amber-700 to-orange-600 text-white px-4 py-2 text-xs font-semibold shadow-md flex items-center justify-between gap-3 animate-fade-in sticky top-0 z-50">
          <div className="flex items-center gap-2.5">
            <div className="p-1 rounded-full bg-black/20 shrink-0">
              <WifiOff className="w-4 h-4 animate-pulse text-amber-200" />
            </div>
            <span>
              <strong>آف لائن موڈ (Offline Mode Active):</strong> انٹرنیٹ بند ہے، لیکن سافٹ ویئر بغیر کسی رکاوٹ کے 100% فعال ہے۔ تمام سیلز اور تبدیلیاں لوکل محفوظ ہو رہی ہیں۔
            </span>
          </div>
          <div className="flex items-center gap-2 shrink-0">
            <span className="hidden sm:inline-block px-2 py-0.5 rounded bg-black/20 text-[10px] font-bold text-amber-100">
              Auto-Sync On Reconnect
            </span>
            <button
              onClick={() => setDismissOfflineNotice(true)}
              className="p-1 rounded-md hover:bg-black/20 text-amber-100 hover:text-white transition-colors cursor-pointer"
              title="چھپائیں"
            >
              <X className="w-3.5 h-3.5" />
            </button>
          </div>
        </div>
      )}

      {/* 2. New Version Update Available Banner */}
      {pwaState.needRefresh && (
        <div className="bg-gradient-to-r from-blue-600 to-indigo-600 text-white px-4 py-2 text-xs font-semibold shadow-md flex items-center justify-between gap-3 animate-fade-in sticky top-0 z-50">
          <div className="flex items-center gap-2">
            <RefreshCw className="w-4 h-4 animate-spin text-blue-200" />
            <span>نئی اپڈیٹ دستیاب ہے! تمام نئی تبدیلیاں حاصل کرنے کے لیے ریفریش کریں۔</span>
          </div>
          <button
            onClick={() => applyPWAUpdate()}
            className="px-3 py-1 rounded-lg bg-white text-blue-700 font-bold text-xs hover:bg-blue-50 transition-colors shadow cursor-pointer"
          >
            اپڈیٹ کریں (Refresh Now)
          </button>
        </div>
      )}

      {/* 3. Install App Floating Prompt (if installable and not yet installed) */}
      {pwaState.isInstallable && !pwaState.isInstalled && showInstallPrompt && (
        <div className="fixed bottom-4 right-4 z-40 max-w-sm bg-white dark:bg-slate-900 border border-emerald-500/30 rounded-2xl p-3.5 shadow-2xl flex items-center justify-between gap-3 text-slate-800 dark:text-white animate-bounce-short">
          <div className="flex items-center gap-3">
            <div className="w-10 h-10 rounded-xl bg-emerald-600 text-white flex items-center justify-center shrink-0 shadow-md">
              <Smartphone className="w-5 h-5" />
            </div>
            <div>
              <h4 className="text-xs font-bold flex items-center gap-1.5 text-emerald-600 dark:text-emerald-400">
                <span>ایپ انسٹال کریں (Install POS App)</span>
              </h4>
              <p className="text-[11px] text-slate-500 dark:text-slate-400">
                موبائل یا پی سی پر بغیر انٹرنیٹ آف لائن چلانے کے لیے انسٹال کریں۔
              </p>
            </div>
          </div>
          <div className="flex items-center gap-1.5 shrink-0">
            <button
              onClick={async () => {
                await promptPWAInstall();
              }}
              className="px-3 py-1.5 rounded-xl bg-emerald-600 hover:bg-emerald-700 text-white font-bold text-xs shadow transition-all cursor-pointer flex items-center gap-1"
            >
              <Download className="w-3.5 h-3.5" />
              <span>Install</span>
            </button>
            <button
              onClick={() => setShowInstallPrompt(false)}
              className="p-1.5 rounded-lg text-slate-400 hover:text-slate-600 dark:hover:text-slate-200"
            >
              <X className="w-3.5 h-3.5" />
            </button>
          </div>
        </div>
      )}
    </>
  );
};
