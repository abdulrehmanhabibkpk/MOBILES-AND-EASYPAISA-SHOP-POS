import React, { useState } from 'react';
import { 
  X, Database, RefreshCw, CheckCircle2, ShieldCheck, 
  Download, AlertTriangle, ArrowRight, Zap, FileSpreadsheet, HardDrive
} from 'lucide-react';
import { ProductSale, Transaction, Product, AppSettings, DailyBalance } from '../types';
import { emergencyScanAndRecoverAll } from '../lib/indexedDbVault';
import { flushPendingSyncQueue } from '../lib/offlineSyncManager';

interface DataRecoveryModalProps {
  isOpen: boolean;
  onClose: () => void;
  productSales: ProductSale[];
  transactions: Transaction[];
  products: Product[];
  settings: AppSettings;
  onDataRestored: (data: {
    sales: ProductSale[];
    transactions: Transaction[];
    products: Product[];
  }) => void;
}

export const DataRecoveryModal: React.FC<DataRecoveryModalProps> = ({
  isOpen,
  onClose,
  productSales,
  transactions,
  products,
  settings,
  onDataRestored,
}) => {
  const [isScanning, setIsScanning] = useState(false);
  const [scanResult, setScanResult] = useState<string | null>(null);

  const isLight = settings.theme === 'light';

  const handleRunDeepScan = async () => {
    setIsScanning(true);
    setScanResult(null);
    try {
      // 1. Flush offline sync queue
      await flushPendingSyncQueue();

      // 2. Scan IndexedDB Vault + LocalStorage + Cache
      const recovered = await emergencyScanAndRecoverAll();

      onDataRestored({
        sales: recovered.recoveredSales,
        transactions: recovered.recoveredTransactions,
        products: recovered.recoveredProducts,
      });

      setScanResult(`کامیابی! گہرے اسکین نے ${recovered.recoveredSales.length} سیلز، ${recovered.recoveredTransactions.length} ٹرانزیکشنز، اور ${recovered.recoveredProducts.length} پروڈکٹس کو بحال (Restore) کر دیا ہے۔`);
    } catch (err: any) {
      setScanResult(`اسکین کے دوران خرابی: ${err?.message || 'نامعلوم مسئلہ'}`);
    } finally {
      setIsScanning(false);
    }
  };

  const handleDownloadFullBackup = () => {
    const backupObj = {
      backupDate: new Date().toISOString(),
      shopName: settings.shopName,
      productSales,
      transactions,
      products,
      settings,
    };
    const blob = new Blob([JSON.stringify(backupObj, null, 2)], { type: 'application/json' });
    const url = URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = `Balal_Mobiles_Complete_Backup_${new Date().toISOString().split('T')[0]}.json`;
    a.click();
    URL.revokeObjectURL(url);
  };

  if (!isOpen) return null;

  return (
    <div className="fixed inset-0 z-50 flex items-center justify-center p-3 sm:p-4 bg-black/75 backdrop-blur-sm animate-in fade-in duration-200">
      <div 
        className={`w-full max-w-lg rounded-2xl border shadow-2xl overflow-hidden transition-all ${
          isLight ? 'bg-white border-slate-200 text-slate-900' : 'bg-slate-900 border-slate-800 text-white'
        }`}
      >
        {/* Header */}
        <div className={`p-4 sm:p-5 border-b flex items-center justify-between gap-3 ${
          isLight ? 'bg-slate-50 border-slate-200' : 'bg-slate-800/80 border-slate-800'
        }`}>
          <div className="flex items-center gap-3">
            <div className="w-10 h-10 rounded-xl bg-emerald-600 text-white flex items-center justify-center shadow-md shadow-emerald-600/20 font-bold">
              <HardDrive className="w-5 h-5" />
            </div>
            <div>
              <h2 className="text-base font-extrabold flex items-center gap-2">
                <span>ڈیٹا پروٹیکشن و خودکار ریکوری</span>
                <span className="text-[10px] px-2 py-0.5 rounded-full bg-emerald-500/10 text-emerald-600 border border-emerald-500/20 font-semibold">
                  Vault v2
                </span>
              </h2>
              <p className={`text-xs ${isLight ? 'text-slate-500' : 'text-slate-400'}`}>
                بجلی جانے یا کمپیوٹر بند ہونے کے بعد سیلز اور ریکارڈز کی بحالی
              </p>
            </div>
          </div>

          <button
            onClick={onClose}
            className={`p-2 rounded-xl transition-colors cursor-pointer ${
              isLight ? 'hover:bg-slate-200 text-slate-500' : 'hover:bg-slate-700 text-slate-400'
            }`}
          >
            <X className="w-5 h-5" />
          </button>
        </div>

        {/* Content */}
        <div className="p-4 sm:p-6 space-y-5">
          {/* Current Loaded Counts */}
          <div className="grid grid-cols-3 gap-2">
            <div className={`p-3 rounded-xl border text-center ${isLight ? 'bg-slate-50 border-slate-200' : 'bg-slate-800/40 border-slate-800'}`}>
              <div className="text-[11px] text-slate-500 font-medium">سیلز ریکارڈز</div>
              <div className="text-lg font-black text-emerald-600">{productSales.length}</div>
            </div>
            <div className={`p-3 rounded-xl border text-center ${isLight ? 'bg-slate-50 border-slate-200' : 'bg-slate-800/40 border-slate-800'}`}>
              <div className="text-[11px] text-slate-500 font-medium">لیجر انٹریز</div>
              <div className="text-lg font-black text-blue-600">{transactions.length}</div>
            </div>
            <div className={`p-3 rounded-xl border text-center ${isLight ? 'bg-slate-50 border-slate-200' : 'bg-slate-800/40 border-slate-800'}`}>
              <div className="text-[11px] text-slate-500 font-medium">اسٹاک آئٹمز</div>
              <div className="text-lg font-black text-purple-600">{products.length}</div>
            </div>
          </div>

          {/* Protection Details */}
          <div className={`p-3.5 rounded-xl border space-y-2 text-xs ${
            isLight ? 'bg-emerald-50/50 border-emerald-200 text-emerald-950' : 'bg-emerald-950/20 border-emerald-800/40 text-emerald-200'
          }`}>
            <div className="flex items-center gap-2 font-bold text-emerald-700 dark:text-emerald-400">
              <ShieldCheck className="w-4 h-4" />
              <span>ہارڈ ڈرائیو پر مستقل محفوظ (Persistent IndexedDB Vault)</span>
            </div>
            <p className="leading-relaxed opacity-90">
              اب آپ کی ہر سیل اور ٹرانزیکشن کمپیوٹر کی ہارڈ ڈرائیو پر براہِ راست محفوظ ہو رہی ہے۔ اگر بجلی بند ہو جائے یا پی سی ری اسٹارٹ ہو، تب بھی کوئی سیل ضائع نہیں ہوگی۔
            </p>
          </div>

          {/* Action button */}
          <div className="space-y-3">
            <button
              onClick={handleRunDeepScan}
              disabled={isScanning}
              className="w-full py-3.5 px-4 rounded-xl bg-emerald-600 hover:bg-emerald-700 text-white font-extrabold text-xs sm:text-sm shadow-lg shadow-emerald-600/25 flex items-center justify-center gap-2 transition-all cursor-pointer disabled:opacity-50"
            >
              <RefreshCw className={`w-4 h-4 ${isScanning ? 'animate-spin' : ''}`} />
              <span>{isScanning ? 'ڈیٹا تلاش اور بحال ہو رہا ہے...' : 'گمشدہ سیلز و ڈیٹا فوری بحال کریں (1-Click Deep Scan)'}</span>
            </button>

            {scanResult && (
              <div className="p-3 rounded-xl bg-blue-500/10 border border-blue-500/30 text-blue-800 dark:text-blue-300 text-xs font-semibold leading-relaxed flex items-center gap-2">
                <CheckCircle2 className="w-4 h-4 text-emerald-500 shrink-0" />
                <span>{scanResult}</span>
              </div>
            )}

            <button
              onClick={handleDownloadFullBackup}
              className={`w-full py-2.5 px-4 rounded-xl border text-xs font-bold flex items-center justify-center gap-2 transition-all cursor-pointer ${
                isLight ? 'border-slate-300 hover:bg-slate-100 text-slate-700' : 'border-slate-700 hover:bg-slate-800 text-slate-300'
              }`}
            >
              <Download className="w-4 h-4" />
              <span>کمپیوٹر میں مکمل بیک اپ فائل ڈاؤن لوڈ کریں (.json)</span>
            </button>
          </div>
        </div>
      </div>
    </div>
  );
};
