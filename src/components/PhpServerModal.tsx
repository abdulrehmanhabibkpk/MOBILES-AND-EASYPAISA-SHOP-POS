import React, { useState } from 'react';
import { 
  Server, 
  CheckCircle2, 
  AlertTriangle, 
  RefreshCw, 
  Download, 
  ExternalLink, 
  Copy, 
  Database, 
  HardDrive, 
  Globe, 
  Check, 
  X, 
  ShieldCheck,
  Zap,
  UploadCloud,
  FileArchive
} from 'lucide-react';
import { AppSettings, Product, ProductSale, Transaction, Supplier, MobilePurchaseRecord, DailyBalance } from '../types';
import { CompleteShopBackup } from '../lib/autoBackupManager';
import { testPhpConnection, pushAllDataToPhp, pullAllDataFromPhp, PhpConnectionResult } from '../lib/phpApiClient';
import { downloadHostingDeploymentZip } from '../lib/zipExporter';

interface PhpServerModalProps {
  isOpen: boolean;
  onClose: () => void;
  settings: AppSettings;
  onUpdateSettings: (newSettings: AppSettings) => void;
  products: Product[];
  productSales: ProductSale[];
  transactions: Transaction[];
  suppliers: Supplier[];
  mobilePurchases: MobilePurchaseRecord[];
  dailyBalances: Record<string, DailyBalance>;
  onApplyRemoteData?: (data: any) => void;
  onRestoreCompleteBackup?: (backup: CompleteShopBackup) => Promise<void> | void;
}

export const PhpServerModal: React.FC<PhpServerModalProps> = ({
  isOpen,
  onClose,
  settings,
  onUpdateSettings,
  products,
  productSales,
  transactions,
  suppliers,
  mobilePurchases,
  dailyBalances,
  onApplyRemoteData,
  onRestoreCompleteBackup
}) => {
  const [urlInput, setUrlInput] = useState(settings.phpBackendUrl || '');
  const [autoSync, setAutoSync] = useState(settings.autoSyncPhp ?? true);
  const [testing, setTesting] = useState(false);
  const [syncing, setSyncing] = useState(false);
  const [pulling, setPulling] = useState(false);
  const [testResult, setTestResult] = useState<PhpConnectionResult | null>(null);
  const [statusMessage, setStatusMessage] = useState<{ type: 'success' | 'error' | 'info'; text: string } | null>(null);
  const [activeGuideTab, setActiveGuideTab] = useState<'infinity' | 'hostinger' | 'files'>('infinity');
  const [copiedText, setCopiedText] = useState<string | null>(null);
  const [downloadingZip, setDownloadingZip] = useState(false);

  if (!isOpen) return null;

  const handleDownloadDeployZip = async () => {
    setDownloadingZip(true);
    setStatusMessage({ type: 'info', text: 'ہوسٹنگ کے لیے ریڈی ٹو اپلوڈ زپ فائل تیار ہو رہی ہے...' });
    try {
      const ok = await downloadHostingDeploymentZip();
      if (ok) {
        setStatusMessage({ 
          type: 'success', 
          text: '✅ زپ فائل (balal_pos_hosting_ready.zip) ڈاؤن لوڈ ہو گئی ہے! اسے کھول کر اپنی ہوسٹنگ (htdocs) پر اپلوڈ کریں۔' 
        });
      } else {
        setStatusMessage({ type: 'error', text: 'زپ فائل بنانے میں دشواری پیش آئی۔' });
      }
    } catch (e) {
      setStatusMessage({ type: 'error', text: 'زپ بنانے میں ایرر آیا۔' });
    } finally {
      setDownloadingZip(false);
    }
  };

  const handleCopy = (text: string, label: string) => {
    navigator.clipboard.writeText(text);
    setCopiedText(label);
    setTimeout(() => setCopiedText(null), 2500);
  };

  const handleTestConnection = async () => {
    if (!urlInput.trim()) {
      setStatusMessage({ type: 'error', text: 'براہ کرم پہلے اپنے PHP سرور کا URL درج کریں۔' });
      return;
    }
    setTesting(true);
    setStatusMessage(null);
    setTestResult(null);

    const result = await testPhpConnection(urlInput);
    setTesting(false);
    setTestResult(result);

    if (result.success) {
      setStatusMessage({ type: 'success', text: 'سرور اور MySQL ڈیٹا بیس کامیابی کے ساتھ منسلک ہو گئے ہیں!' });
      // Auto save the working URL to settings
      onUpdateSettings({
        ...settings,
        phpBackendUrl: urlInput.trim(),
        autoSyncPhp: autoSync
      });
    } else {
      setStatusMessage({ type: 'error', text: result.message });
    }
  };

  const handleSaveSettings = () => {
    onUpdateSettings({
      ...settings,
      phpBackendUrl: urlInput.trim(),
      autoSyncPhp: autoSync
    });
    setStatusMessage({ type: 'success', text: 'سیٹنگز کامیابی سے محفوظ ہو گئیں۔' });
    setTimeout(() => onClose(), 1000);
  };

  const handleSyncToMysql = async () => {
    if (!urlInput.trim()) {
      setStatusMessage({ type: 'error', text: 'براہ کرم پہلے PHP سرور کا URL درج کریں۔' });
      return;
    }

    setSyncing(true);
    setStatusMessage({ type: 'info', text: 'ڈیٹا مائی ایس کیو ایل میں منتقل ہو رہا ہے...' });

    const payload = {
      products,
      productSales,
      transactions,
      suppliers,
      mobilePurchases,
      dailyBalances,
      settings: {
        ...settings,
        phpBackendUrl: urlInput.trim(),
        autoSyncPhp: autoSync
      }
    };

    const res = await pushAllDataToPhp(urlInput, payload);
    setSyncing(false);

    if (res.success) {
      const now = Date.now();
      onUpdateSettings({
        ...settings,
        phpBackendUrl: urlInput.trim(),
        autoSyncPhp: autoSync,
        phpLastSyncedAt: now
      });
      setStatusMessage({ 
        type: 'success', 
        text: `کامیابی: سارا ڈیٹا (${products.length} پراڈکٹس، ${productSales.length} سیلز، ${transactions.length} ٹرانزیکشنز) مائی ایس کیو ایل میں محفوظ ہو گیا!` 
      });
    } else {
      setStatusMessage({ type: 'error', text: res.message });
    }
  };

  const handlePullFromMysql = async () => {
    if (!urlInput.trim()) {
      setStatusMessage({ type: 'error', text: 'براہ کرم پہلے PHP سرور کا URL درج کریں۔' });
      return;
    }

    if (!window.confirm('کیا آپ واقعی اپنے سرور کے MySQL ڈیٹا بیس سے تمام ڈیٹا لوڈ کرنا چاہتے ہیں؟ موجودہ لوکل ڈیٹا سرور ڈیٹا سے اپ ڈیٹ ہو جائے گا۔')) {
      return;
    }

    setPulling(true);
    setStatusMessage({ type: 'info', text: 'سرور سے ڈیٹا لوڈ ہو رہا ہے...' });

    const res = await pullAllDataFromPhp(urlInput);
    setPulling(false);

    if (res.success && res.data) {
      if (onApplyRemoteData) {
        onApplyRemoteData(res.data);
      }
      setStatusMessage({ 
        type: 'success', 
        text: 'سرور سے تمام ریکارڈز کامیابی سے موصول اور اپ ڈیٹ ہو گئے!' 
      });
    } else {
      setStatusMessage({ type: 'error', text: res.message || 'سرور سے ڈیٹا موصول نہیں ہو سکا۔' });
    }
  };

  return (
    <div className="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/70 backdrop-blur-sm overflow-y-auto">
      <div className="bg-slate-900 border border-emerald-500/30 w-full max-w-3xl rounded-2xl shadow-2xl overflow-hidden my-8 flex flex-col text-slate-100">
        
        {/* Header */}
        <div className="bg-gradient-to-r from-emerald-950 via-slate-900 to-cyan-950 border-b border-emerald-500/20 p-5 flex items-center justify-between">
          <div className="flex items-center gap-3">
            <div className="w-10 h-10 rounded-xl bg-emerald-500/20 border border-emerald-500/30 flex items-center justify-center text-emerald-400">
              <Server className="w-6 h-6" />
            </div>
            <div>
              <h2 className="text-xl font-bold flex items-center gap-2">
                <span>پی ایچ پی اور مائی ایس کیو ایل بیک اینڈ</span>
                <span className="text-xs bg-emerald-500/20 text-emerald-400 px-2 py-0.5 rounded-full border border-emerald-500/30 font-mono">
                  PHP / MySQL
                </span>
              </h2>
              <p className="text-xs text-slate-400">
                InfinityFree (cPanel) یا Hostinger (hPanel) پر بغیر کسی لمٹ کے اپنا ڈیٹا محفوظ کریں
              </p>
            </div>
          </div>
          <button 
            onClick={onClose}
            className="p-2 rounded-lg text-slate-400 hover:text-white hover:bg-slate-800 transition-colors"
          >
            <X className="w-5 h-5" />
          </button>
        </div>

        {/* Modal Body */}
        <div className="p-6 space-y-6 max-h-[75vh] overflow-y-auto">

          {/* Direct Download ZIP for InfinityFree / Hostinger */}
          <div className="bg-gradient-to-r from-emerald-950 via-slate-900 to-cyan-950 border-2 border-emerald-500/50 rounded-2xl p-5 shadow-xl flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4">
            <div className="space-y-1">
              <div className="flex items-center gap-2">
                <span className="p-2 rounded-lg bg-emerald-500/20 text-emerald-300 border border-emerald-500/30">
                  <FileArchive className="w-6 h-6" />
                </span>
                <div>
                  <h3 className="text-base font-bold text-emerald-300 flex items-center gap-2">
                    <span>ہوسٹنگ پر اپلوڈ کرنے والی تیار زپ فائل</span>
                    <span className="text-[10px] bg-emerald-500/30 text-emerald-200 px-2 py-0.5 rounded-full border border-emerald-500/40">
                      1-Click Ready ZIP
                    </span>
                  </h3>
                  <p className="text-xs text-slate-300">
                    اس زپ فائل کے اندر <strong className="text-white">index.html, assets, api, config.php, db.sql</strong> سب تیار موجود ہے۔
                  </p>
                </div>
              </div>
            </div>

            <button
              type="button"
              onClick={handleDownloadDeployZip}
              disabled={downloadingZip}
              className="w-full sm:w-auto px-5 py-3 bg-gradient-to-r from-emerald-500 to-teal-500 hover:from-emerald-400 hover:to-teal-400 text-slate-950 font-bold text-sm rounded-xl flex items-center justify-center gap-2 transition-all shadow-lg hover:shadow-emerald-500/20 disabled:opacity-50 shrink-0 cursor-pointer"
            >
              <Download className={`w-5 h-5 ${downloadingZip ? 'animate-bounce' : ''}`} />
              <span>{downloadingZip ? 'فائل تیار ہو رہی ہے...' : 'تیار زپ فائل ڈاؤن لوڈ کریں (.zip)'}</span>
            </button>
          </div>

          {/* Quota Freedom Callout */}
          <div className="bg-gradient-to-r from-emerald-900/30 to-teal-900/20 border border-emerald-500/30 rounded-xl p-4 flex items-start gap-3">
            <ShieldCheck className="w-6 h-6 text-emerald-400 shrink-0 mt-0.5" />
            <div className="text-sm space-y-1">
              <p className="font-semibold text-emerald-200">فائر بیس کی روزانہ لمٹ کا مکمل خاتمہ!</p>
              <p className="text-xs text-slate-300 leading-relaxed">
                اب فائر بیس کی 50,000 ریڈز والی حد لاگو نہیں ہوگی۔ آپ کے اپنے پی ایچ پی سرور پر 
                <strong className="text-white"> لامحدود پراڈکٹس، لامحدود بل اور موبائل خرید و فروخت</strong> بغیر کسی فیس یا رکاوٹ کے چلے گی۔
              </p>
            </div>
          </div>

          {/* Connection URL Input */}
          <div className="space-y-3 bg-slate-800/60 p-4 rounded-xl border border-slate-700">
            <label className="block text-sm font-semibold text-slate-200">
              پی ایچ پی سرور API کا مکمل لنک (PHP Backend API URL):
            </label>
            <div className="flex flex-col sm:flex-row gap-2">
              <div className="relative flex-1">
                <Globe className="w-5 h-5 text-slate-400 absolute left-3 top-1/2 -translate-y-1/2" />
                <input
                  type="url"
                  value={urlInput}
                  onChange={(e) => setUrlInput(e.target.value)}
                  placeholder="https://your-domain.infinityfreeapp.com/api"
                  className="w-full bg-slate-900 border border-slate-700 focus:border-emerald-500 rounded-lg pl-10 pr-3 py-2.5 text-sm text-white placeholder:text-slate-500 focus:outline-none"
                />
              </div>
              <button
                type="button"
                onClick={handleTestConnection}
                disabled={testing}
                className="px-4 py-2.5 bg-emerald-600 hover:bg-emerald-500 disabled:opacity-50 text-white font-medium text-sm rounded-lg flex items-center justify-center gap-2 transition-all shadow-md shrink-0"
              >
                <RefreshCw className={`w-4 h-4 ${testing ? 'animate-spin' : ''}`} />
                <span>{testing ? 'ٹیسٹ ہو رہا ہے...' : 'ٹیسٹ کنکشن'}</span>
              </button>
            </div>
            <div className="flex flex-wrap items-center justify-between gap-2 text-xs text-slate-400">
              <p>
                مثال: <code className="text-emerald-300">/api</code> یا <code className="text-emerald-300">https://myshop.com/api</code>
              </p>
              <button
                type="button"
                onClick={() => {
                  setUrlInput('/api');
                }}
                className="text-xs px-2.5 py-1 bg-slate-800 hover:bg-slate-700 text-cyan-300 rounded border border-slate-700 transition-colors"
                title="اگر آپ نے پورا سافٹ ویئر اپنی ہوسٹنگ پر اپلوڈ کیا ہے تو /api استعمال کریں"
              >
                ⚡ اسی ہوسٹنگ کا خودکار لوکل لنک لگائیں (/api)
              </button>
            </div>

            {/* Auto-Sync Checkbox */}
            <div className="pt-2 border-t border-slate-700/60 flex items-center justify-between">
              <label className="flex items-center gap-2 cursor-pointer text-xs text-slate-300">
                <input
                  type="checkbox"
                  checked={autoSync}
                  onChange={(e) => setAutoSync(e.target.checked)}
                  className="rounded border-slate-600 text-emerald-500 focus:ring-emerald-500 h-4 w-4 bg-slate-900"
                />
                <span>جب بھی بل یا سامان ایڈ ہو خود بخود پی ایچ پی سرور پر سنک ہو جائے (Auto-Sync)</span>
              </label>
              {settings.phpLastSyncedAt && (
                <span className="text-[11px] text-slate-400">
                  آخری سنک: {new Date(settings.phpLastSyncedAt).toLocaleTimeString()}
                </span>
              )}
            </div>
          </div>

          {/* Test Result Box */}
          {testResult && (
            <div className={`p-4 rounded-xl border ${testResult.success ? 'bg-emerald-950/40 border-emerald-500/40' : 'bg-red-950/40 border-red-500/40'} space-y-3`}>
              <div className="flex items-start gap-2.5">
                {testResult.success ? (
                  <CheckCircle2 className="w-5 h-5 text-emerald-400 shrink-0 mt-0.5" />
                ) : (
                  <AlertTriangle className="w-5 h-5 text-red-400 shrink-0 mt-0.5" />
                )}
                <div className="space-y-1">
                  <span className={`text-sm font-semibold block ${testResult.success ? 'text-emerald-300' : 'text-red-300'}`}>
                    {testResult.message}
                  </span>
                  {!testResult.success && urlInput && (
                    <div className="pt-1 flex flex-wrap gap-2 text-xs">
                      <a
                        href={urlInput.trim().endsWith('.php') ? urlInput.trim() : `${urlInput.trim().replace(/\/+$/, '')}/health.php`}
                        target="_blank"
                        rel="noreferrer"
                        className="px-2.5 py-1 rounded bg-slate-800 hover:bg-slate-700 text-cyan-300 border border-slate-700 flex items-center gap-1 underline"
                      >
                        <ExternalLink className="w-3.5 h-3.5" />
                        <span>براؤزر کے نئے ٹیب میں براہ راست لنک کھول کر چیک کریں</span>
                      </a>
                    </div>
                  )}
                </div>
              </div>

              {!testResult.success && (
                <div className="bg-slate-900/80 p-3 rounded-lg border border-red-900/40 text-[11px] text-slate-300 space-y-1.5">
                  <span className="font-bold text-amber-300 block">⚠️ خرابی حل کرنے کے 4 آسان نکات:</span>
                  <p><strong>1. براؤزر میں ٹیسٹ:</strong> اوپر والے نیلے لنک پر کلک کریں۔ اگر وہاں <code className="text-emerald-400">"status":"HEALTHY"</code> آتا ہے لیکن یہاں نہیں آتا، تو اس کا مطلب ہے کہ مفت ہوسٹنگ (InfinityFree) بیرونی API کو بلاک کر رہی ہے۔</p>
                  <p><strong>2. SSL / HTTPS:</strong> اگر آپ کے ڈومین پر SSL نہیں ہے تو <code className="text-amber-300">https://</code> کے بجائے <code className="text-amber-300">http://</code> لکھ کر ٹیسٹ کریں۔</p>
                  <p><strong>3. فولڈر چیک کریں:</strong> اگر فائلیں سیدھی <code className="text-cyan-300">htdocs</code> میں ہیں تو ایڈریس <code className="text-cyan-300">http://limopos.xo.je/</code> ہوگا (بغیر <code className="text-cyan-300">/api</code> کے)۔</p>
                  <p><strong>4. config.php:</strong> ڈیٹا بیس ہوسٹ (جیسے <code className="text-indigo-300">sql205.epizy.com</code>)، یوزر نیم اور پاس ورڈ درست درج کریں۔</p>
                </div>
              )}

              {testResult.success && testResult.stats && (
                <div className="grid grid-cols-2 sm:grid-cols-4 gap-2 pt-2 text-xs">
                  <div className="bg-slate-800/80 p-2 rounded-lg border border-slate-700 text-center">
                    <span className="text-slate-400 block">پراڈکٹس:</span>
                    <span className="text-sm font-bold text-emerald-400">{testResult.stats.total_products ?? 0}</span>
                  </div>
                  <div className="bg-slate-800/80 p-2 rounded-lg border border-slate-700 text-center">
                    <span className="text-slate-400 block">سیلز ریکارڈ:</span>
                    <span className="text-sm font-bold text-cyan-400">{testResult.stats.total_sales ?? 0}</span>
                  </div>
                  <div className="bg-slate-800/80 p-2 rounded-lg border border-slate-700 text-center">
                    <span className="text-slate-400 block">کیش لیجر:</span>
                    <span className="text-sm font-bold text-amber-400">{testResult.stats.total_transactions ?? 0}</span>
                  </div>
                  <div className="bg-slate-800/80 p-2 rounded-lg border border-slate-700 text-center">
                    <span className="text-slate-400 block">سپلائرز:</span>
                    <span className="text-sm font-bold text-purple-400">{testResult.stats.total_suppliers ?? 0}</span>
                  </div>
                </div>
              )}
            </div>
          )}

          {/* Status Message Notification */}
          {statusMessage && (
            <div className={`p-3 rounded-lg text-xs font-medium ${
              statusMessage.type === 'success' ? 'bg-emerald-950/60 text-emerald-300 border border-emerald-500/30' :
              statusMessage.type === 'error' ? 'bg-rose-950/60 text-rose-300 border border-rose-500/30' :
              'bg-blue-950/60 text-blue-300 border border-blue-500/30'
            }`}>
              {statusMessage.text}
            </div>
          )}

          {/* Sync & Backup Action Buttons */}
          <div className="grid grid-cols-1 sm:grid-cols-2 gap-3">
            <button
              type="button"
              onClick={handleSyncToMysql}
              disabled={syncing || testing}
              className="py-3 px-4 bg-emerald-600/90 hover:bg-emerald-500 text-white rounded-xl font-semibold text-sm flex items-center justify-center gap-2 shadow-lg transition-all disabled:opacity-50 cursor-pointer"
            >
              <Database className={`w-4 h-4 ${syncing ? 'animate-bounce' : ''}`} />
              <span>{syncing ? 'منتقلی جاری ہے...' : 'تمام لوکل ڈیٹا MySQL میں سنک کریں'}</span>
            </button>

            <button
              type="button"
              onClick={handlePullFromMysql}
              disabled={pulling || testing}
              className="py-3 px-4 bg-slate-800 hover:bg-slate-700 border border-slate-700 text-slate-200 rounded-xl font-semibold text-sm flex items-center justify-center gap-2 transition-all disabled:opacity-50 cursor-pointer"
            >
              <Download className={`w-4 h-4 ${pulling ? 'animate-spin' : ''}`} />
              <span>{pulling ? 'لوڈ ہو رہا ہے...' : 'سرور سے ڈیٹا ایپ میں لائیں (Restore)'}</span>
            </button>
          </div>

          {/* Direct Backup File (.json) Import to MySQL & App */}
          <div className="bg-slate-800/40 border border-slate-700/60 rounded-xl p-3.5 flex flex-col sm:flex-row items-start sm:items-center justify-between gap-3">
            <div>
              <span className="text-xs font-bold text-slate-200 block">
                کیا آپ کے پاس سافٹ ویئر کی بیک اپ فائل موجود ہے؟
              </span>
              <span className="text-[11px] text-slate-400">
                یہاں سے JSON بیک اپ فائل منتخب کریں تاکہ وہ ایپ اور پی ایچ پی سرور دونوں میں بحال ہو جائے
              </span>
            </div>

            <label className="shrink-0 px-3.5 py-2 rounded-lg bg-indigo-600 hover:bg-indigo-500 text-white text-xs font-bold flex items-center gap-1.5 transition-all cursor-pointer shadow-md">
              <UploadCloud className="w-4 h-4" />
              <span>بیک اپ فائل اپلوڈ کریں (.json)</span>
              <input
                type="file"
                accept=".json"
                className="hidden"
                onChange={(e) => {
                  const file = e.target.files?.[0];
                  if (file) {
                    const reader = new FileReader();
                    reader.onload = async (ev) => {
                      try {
                        const parsed = JSON.parse(ev.target?.result as string);
                        if (parsed && onRestoreCompleteBackup) {
                          await onRestoreCompleteBackup(parsed);
                          setStatusMessage({
                            type: 'success',
                            text: `بیک اپ فائل (${file.name}) سے تمام ڈیٹا ایپ اور پی ایچ پی سرور میں کامیابی سے بحال ہو گیا ہے!`
                          });
                        } else {
                          setStatusMessage({ type: 'error', text: 'فائل کا فارمیٹ درست نہیں ہے۔' });
                        }
                      } catch (err) {
                        setStatusMessage({ type: 'error', text: 'فائل پڑھنے میں غلطی پیش آئی۔' });
                      }
                    };
                    reader.readAsText(file);
                  }
                }}
              />
            </label>
          </div>

          {/* Setup Guide Section */}
          <div className="border border-slate-800 rounded-xl overflow-hidden bg-slate-950/40">
            <div className="flex border-b border-slate-800 bg-slate-900/60 text-xs font-semibold">
              <button
                type="button"
                onClick={() => setActiveGuideTab('infinity')}
                className={`flex-1 py-2.5 px-3 border-b-2 text-center transition-colors ${
                  activeGuideTab === 'infinity' 
                    ? 'border-emerald-500 text-emerald-400 bg-slate-800/40' 
                    : 'border-transparent text-slate-400 hover:text-slate-200'
                }`}
              >
                InfinityFree (cPanel) گائیڈ
              </button>
              <button
                type="button"
                onClick={() => setActiveGuideTab('hostinger')}
                className={`flex-1 py-2.5 px-3 border-b-2 text-center transition-colors ${
                  activeGuideTab === 'hostinger' 
                    ? 'border-emerald-500 text-emerald-400 bg-slate-800/40' 
                    : 'border-transparent text-slate-400 hover:text-slate-200'
                }`}
              >
                Hostinger (hPanel) گائیڈ
              </button>
              <button
                type="button"
                onClick={() => setActiveGuideTab('files')}
                className={`flex-1 py-2.5 px-3 border-b-2 text-center transition-colors ${
                  activeGuideTab === 'files' 
                    ? 'border-emerald-500 text-emerald-400 bg-slate-800/40' 
                    : 'border-transparent text-slate-400 hover:text-slate-200'
                }`}
              >
                پی ایچ پی فائلیں
              </button>
            </div>

            <div className="p-4 text-xs text-slate-300 space-y-3 leading-relaxed">
              {activeGuideTab === 'infinity' && (
                <div className="space-y-2.5">
                  <h4 className="font-bold text-emerald-400 text-sm">InfinityFree (مفت ہوسٹنگ) پر سیٹ اپ کرنے کا طریقہ:</h4>
                  <ol className="list-decimal list-inside space-y-1.5 text-slate-300">
                    <li>اپنے <strong className="text-white">InfinityFree vPanel</strong> میں جائیں اور <strong>"MySQL Databases"</strong> پر کلک کر کے نیا ڈیٹا بیس بنائیں (مثلاً <code className="text-emerald-300">balal_db</code>)۔</li>
                    <li>کنٹرول پینل سے <strong>phpMyAdmin</strong> کھولیں، ڈیٹا بیس پر کلک کریں، اور اوپر <strong>"Import"</strong> میں جا کر <code className="text-amber-300">php-backend/db.sql</code> فائل امپورٹ کر دیں۔</li>
                    <li>اپنے فولڈر میں موجود <code className="text-amber-300">config.php</code> فائل میں اپنا MySQL Host (مثلاً <code className="text-slate-200">sql205.epizy.com</code>)، یوزر نیم، پاس ورڈ اور ڈیٹا بیس کا نام درج کر کے محفوظ کریں۔</li>
                    <li>InfinityFree کے <strong>File Manager</strong> میں <code className="text-slate-200">htdocs/api</code> فولڈر بنا کر <code className="text-slate-200">php-backend</code> کی تمام فائلیں اپلوڈ کر دیں۔</li>
                    <li>اوپر والے باکس میں اپنا لنک درج کریں (مثلاً <code className="text-emerald-300">http://yourdomain.infinityfreeapp.com/api</code>) اور <strong>ٹیسٹ کنکشن</strong> دبائیں!</li>
                  </ol>
                </div>
              )}

              {activeGuideTab === 'hostinger' && (
                <div className="space-y-2.5">
                  <h4 className="font-bold text-emerald-400 text-sm">Hostinger (hPanel) پر سیٹ اپ کرنے کا طریقہ:</h4>
                  <ol className="list-decimal list-inside space-y-1.5 text-slate-300">
                    <li>اپنے <strong className="text-white">Hostinger hPanel</strong> میں جا کر <strong>Databases -&gt; Management</strong> سے نیا MySQL ڈیٹا بیس اور یوزر بنائیں اور پاس ورڈ محفوظ کر لیں۔</li>
                    <li><strong>phpMyAdmin</strong> کھولیں اور <code className="text-amber-300">db.sql</code> فائل کو امپورٹ کریں۔</li>
                    <li><strong>File Manager</strong> میں <code className="text-slate-200">public_html/api</code> فولڈر کے اندر فائلیں اپلوڈ کریں اور <code className="text-slate-200">config.php</code> میں اپنی Hostinger ڈیٹا بیس معلومات لکھیں۔</li>
                    <li>اوپر والے باکس میں اپنا ڈومین API لنک ڈالیں (مثلاً <code className="text-emerald-300">https://yourdomain.com/api</code>) اور ٹیسٹ کریں۔</li>
                  </ol>
                </div>
              )}

              {activeGuideTab === 'files' && (
                <div className="space-y-3">
                  <p className="text-slate-300">
                    ہم نے آپ کے پروجیکٹ کے اندر ایک مکمل، تیار شدہ فولڈر <code className="text-emerald-300 font-mono">/php-backend/</code> بنا دیا ہے، جس میں یہ فائلیں شامل ہیں:
                  </p>
                  <div className="bg-slate-900 p-3 rounded-lg border border-slate-800 font-mono text-[11px] text-slate-300 space-y-1">
                    <div>📁 php-backend/</div>
                    <div className="pl-4">├── 📄 db.sql (تمام ٹیبلز کا ڈیٹا بیس اسٹرکچر)</div>
                    <div className="pl-4">├── 📄 config.php (ڈیٹا بیس کنکشن اور سیٹنگز)</div>
                    <div className="pl-4">├── 📄 .htaccess (اپاچی سرور کنفیگریشن)</div>
                    <div className="pl-4">├── 📄 INSTRUCTIONS_URDU.md (اردو سیٹ اپ گائیڈ)</div>
                    <div className="pl-4">└── 📁 api/</div>
                    <div className="pl-8">├── 📄 health.php (کنکشن ٹیسٹ)</div>
                    <div className="pl-8">├── 📄 sync.php (مکمل دو طرفہ سنک)</div>
                    <div className="pl-8">├── 📄 products.php (انوینٹری اور موبائل اسٹاک)</div>
                    <div className="pl-8">├── 📄 sales.php (پی او ایس بل اور اسٹاک کٹوتی)</div>
                    <div className="pl-8">└── 📄 transactions.php (کیش اور ایزی پیسہ لیجر)</div>
                  </div>
                </div>
              )}
            </div>
          </div>

        </div>

        {/* Footer */}
        <div className="bg-slate-950 p-4 border-t border-slate-800 flex items-center justify-between">
          <div className="text-xs text-slate-400">
            {urlInput.trim() ? (
              <span className="flex items-center gap-1.5 text-emerald-400">
                <span className="w-2 h-2 rounded-full bg-emerald-500 animate-pulse"></span>
                پی ایچ پی بیک اینڈ کنفیگرڈ ہے
              </span>
            ) : (
              <span className="text-slate-500">کنکشن کا انتظار ہے</span>
            )}
          </div>
          <div className="flex gap-2">
            <button
              type="button"
              onClick={onClose}
              className="px-4 py-2 rounded-lg text-xs font-semibold text-slate-300 hover:bg-slate-800 transition-colors"
            >
              بند کریں
            </button>
            <button
              type="button"
              onClick={handleSaveSettings}
              className="px-5 py-2 rounded-lg text-xs font-semibold bg-emerald-600 hover:bg-emerald-500 text-white transition-colors shadow-md"
            >
              سیٹنگز محفوظ کریں
            </button>
          </div>
        </div>

      </div>
    </div>
  );
};
