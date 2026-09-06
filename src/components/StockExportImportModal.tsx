import React, { useState, useRef } from 'react';
import { 
  X, FileSpreadsheet, FileText, Database, Upload, Download, 
  Check, AlertCircle, RefreshCw, FileUp, Sparkles, CheckCircle2,
  HelpCircle, ArrowDownToLine, Package, Layers
} from 'lucide-react';
import { Product, AppSettings } from '../types';
import { 
  exportStockToExcel, 
  exportStockToCSV, 
  exportStockToDB, 
  downloadStockSampleTemplate, 
  parseStockFile, 
  ParsedStockItem 
} from '../lib/stockDataHandler';

interface StockExportImportModalProps {
  isOpen: boolean;
  onClose: () => void;
  products: Product[];
  onImportProducts: (importedItems: ParsedStockItem[], mode: 'merge' | 'replace') => void;
  settings: AppSettings;
}

export const StockExportImportModal: React.FC<StockExportImportModalProps> = ({
  isOpen,
  onClose,
  products,
  onImportProducts,
  settings,
}) => {
  const [activeTab, setActiveTab] = useState<'export' | 'import'>('export');
  const [importMode, setImportMode] = useState<'merge' | 'replace'>('merge');
  
  // File Import States
  const [selectedFile, setSelectedFile] = useState<File | null>(null);
  const [isParsing, setIsParsing] = useState(false);
  const [parseError, setParseError] = useState<string | null>(null);
  const [parsedItems, setParsedItems] = useState<ParsedStockItem[]>([]);
  const [sourceType, setSourceType] = useState<string>('');
  const [isSuccess, setIsSuccess] = useState(false);

  const fileInputRef = useRef<HTMLInputElement>(null);
  const isLight = settings.theme === 'light';

  if (!isOpen) return null;

  // Stats for Export
  const totalStockUnits = products.reduce((acc, p) => acc + (Number(p.stock) || 0), 0);
  const totalCost = products.reduce((acc, p) => acc + ((Number(p.stock) || 0) * (Number(p.purchasePrice) || 0)), 0);
  const totalSaleValue = products.reduce((acc, p) => acc + ((Number(p.stock) || 0) * (Number(p.salePrice) || 0)), 0);

  const handleFileChange = async (e: React.ChangeEvent<HTMLInputElement>) => {
    const file = e.target.files?.[0];
    if (!file) return;

    setSelectedFile(file);
    setIsParsing(true);
    setParseError(null);
    setParsedItems([]);
    setIsSuccess(false);

    try {
      const res = await parseStockFile(file);
      if (!res.success) {
        setParseError(res.error || 'File read karne ma masla pesh aya.');
      } else {
        setParsedItems(res.items);
        setSourceType(res.sourceType);
      }
    } catch (err: any) {
      setParseError(err.message || 'File processing error.');
    } finally {
      setIsParsing(false);
    }
  };

  const handleDragOver = (e: React.DragEvent) => {
    e.preventDefault();
  };

  const handleDrop = async (e: React.DragEvent) => {
    e.preventDefault();
    const file = e.dataTransfer.files?.[0];
    if (!file) return;

    setSelectedFile(file);
    setIsParsing(true);
    setParseError(null);
    setParsedItems([]);
    setIsSuccess(false);

    try {
      const res = await parseStockFile(file);
      if (!res.success) {
        setParseError(res.error || 'File read karne ma masla pesh aya.');
      } else {
        setParsedItems(res.items);
        setSourceType(res.sourceType);
      }
    } catch (err: any) {
      setParseError(err.message || 'File processing error.');
    } finally {
      setIsParsing(false);
    }
  };

  const handleConfirmImport = () => {
    if (parsedItems.length === 0) return;
    
    if (importMode === 'replace') {
      const confirmMsg = `Khabardaar: "Replace All" se aap ka mojooda tamam (${products.length}) stock delete ho kar file wala (${parsedItems.length}) stock save ho jaye ga. Kya aap jari rakhna chahte hain?`;
      if (!confirm(confirmMsg)) return;
    }

    onImportProducts(parsedItems, importMode);
    setIsSuccess(true);
    setTimeout(() => {
      onClose();
    }, 1200);
  };

  const resetImport = () => {
    setSelectedFile(null);
    setParsedItems([]);
    setParseError(null);
    setIsSuccess(false);
    if (fileInputRef.current) {
      fileInputRef.current.value = '';
    }
  };

  return (
    <div className="fixed inset-0 z-50 flex items-center justify-center p-3 sm:p-4 bg-black/70 backdrop-blur-sm animate-fade-in overflow-y-auto">
      <div className={`relative w-full max-w-2xl rounded-2xl border shadow-2xl overflow-hidden my-auto ${
        isLight ? 'bg-white border-slate-200 text-slate-900' : 'bg-slate-900 border-slate-800 text-white'
      }`}>
        
        {/* Header */}
        <div className="px-5 py-4 border-b border-slate-200 dark:border-slate-800 flex items-center justify-between bg-slate-50/50 dark:bg-slate-800/30">
          <div className="flex items-center gap-3">
            <div className="w-10 h-10 rounded-xl bg-blue-600 text-white flex items-center justify-center shadow-md">
              <Layers className="w-5 h-5" />
            </div>
            <div>
              <h2 className="text-base sm:text-lg font-bold">
                Stock Export & Import (اسٹاک بیک اپ و امپورٹ)
              </h2>
              <p className="text-xs text-slate-500 dark:text-slate-400">
                Excel (.xlsx), CSV (.csv), اور Database (.db) فائلز کے ذریعے اسٹاک بیک اپ کریں یا نیا سامان لوڈ کریں
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

        {/* Tab Switcher */}
        <div className="px-5 pt-3 border-b border-slate-200 dark:border-slate-800 flex gap-2">
          <button
            onClick={() => setActiveTab('export')}
            className={`pb-2.5 px-4 text-xs font-bold flex items-center gap-2 border-b-2 transition-all cursor-pointer ${
              activeTab === 'export'
                ? 'border-blue-600 text-blue-600 dark:text-blue-400'
                : 'border-transparent text-slate-500 hover:text-slate-800 dark:hover:text-slate-200'
            }`}
          >
            <Download className="w-4 h-4" />
            <span>Export Stock (اسٹاک ڈاؤن لوڈ / بیک اپ)</span>
          </button>

          <button
            onClick={() => setActiveTab('import')}
            className={`pb-2.5 px-4 text-xs font-bold flex items-center gap-2 border-b-2 transition-all cursor-pointer ${
              activeTab === 'import'
                ? 'border-blue-600 text-blue-600 dark:text-blue-400'
                : 'border-transparent text-slate-500 hover:text-slate-800 dark:hover:text-slate-200'
            }`}
          >
            <Upload className="w-4 h-4" />
            <span>Import Stock (فائل سے اسٹاک شامل کریں)</span>
          </button>
        </div>

        {/* Modal Body */}
        <div className="p-5 max-h-[72vh] overflow-y-auto space-y-4">
          
          {/* ===================== TAB 1: EXPORT ===================== */}
          {activeTab === 'export' && (
            <div className="space-y-4">
              {/* Summary card */}
              <div className={`p-4 rounded-xl border ${
                isLight ? 'bg-blue-50/60 border-blue-100' : 'bg-blue-950/20 border-blue-900/40'
              }`}>
                <div className="grid grid-cols-2 sm:grid-cols-4 gap-3 text-center">
                  <div>
                    <span className="text-[11px] text-slate-500 dark:text-slate-400 block font-medium">Total Products</span>
                    <span className="text-base font-extrabold text-blue-600 dark:text-blue-400">{products.length} Items</span>
                  </div>
                  <div>
                    <span className="text-[11px] text-slate-500 dark:text-slate-400 block font-medium">Total Stock Quantity</span>
                    <span className="text-base font-extrabold text-slate-800 dark:text-slate-100">{totalStockUnits} Qty</span>
                  </div>
                  <div>
                    <span className="text-[11px] text-slate-500 dark:text-slate-400 block font-medium">Total Cost Value</span>
                    <span className="text-base font-extrabold text-slate-800 dark:text-slate-100">Rs. {totalCost.toLocaleString()}</span>
                  </div>
                  <div>
                    <span className="text-[11px] text-slate-500 dark:text-slate-400 block font-medium">Retail Sale Value</span>
                    <span className="text-base font-extrabold text-emerald-600 dark:text-emerald-400">Rs. {totalSaleValue.toLocaleString()}</span>
                  </div>
                </div>
              </div>

              <div className="text-xs text-slate-600 dark:text-slate-300">
                اپنے اسٹاک کا مکمل ڈیٹا نیچے دیے گئے کسی بھی فارمیٹ میں ڈاؤن لوڈ کریں:
              </div>

              {/* Export Buttons Grid */}
              <div className="grid grid-cols-1 sm:grid-cols-3 gap-3">
                {/* 1. EXCEL (.XLSX) */}
                <button
                  onClick={() => exportStockToExcel(products, settings.shopName)}
                  disabled={products.length === 0}
                  className={`p-4 rounded-xl border flex flex-col items-center text-center gap-2 transition-all cursor-pointer ${
                    isLight 
                      ? 'bg-emerald-50 hover:bg-emerald-100/80 border-emerald-200 text-emerald-900' 
                      : 'bg-emerald-950/30 hover:bg-emerald-900/40 border-emerald-800/60 text-emerald-200'
                  } ${products.length === 0 ? 'opacity-50 cursor-not-allowed' : 'hover:shadow-md'}`}
                >
                  <div className="w-10 h-10 rounded-lg bg-emerald-600 text-white flex items-center justify-center font-bold shadow-sm">
                    <FileSpreadsheet className="w-5 h-5" />
                  </div>
                  <div>
                    <h4 className="text-xs font-bold">Excel Sheet (.xlsx)</h4>
                    <p className="text-[10px] text-slate-500 dark:text-slate-400 mt-0.5">
                      ایکسل میں پرنٹ اور فارمولا کے لیے
                    </p>
                  </div>
                  <span className="mt-1 text-[11px] font-bold text-emerald-700 dark:text-emerald-400 flex items-center gap-1">
                    <ArrowDownToLine className="w-3.5 h-3.5" /> Download Excel
                  </span>
                </button>

                {/* 2. CSV (.CSV) */}
                <button
                  onClick={() => exportStockToCSV(products, settings.shopName)}
                  disabled={products.length === 0}
                  className={`p-4 rounded-xl border flex flex-col items-center text-center gap-2 transition-all cursor-pointer ${
                    isLight 
                      ? 'bg-blue-50 hover:bg-blue-100/80 border-blue-200 text-blue-900' 
                      : 'bg-blue-950/30 hover:bg-blue-900/40 border-blue-800/60 text-blue-200'
                  } ${products.length === 0 ? 'opacity-50 cursor-not-allowed' : 'hover:shadow-md'}`}
                >
                  <div className="w-10 h-10 rounded-lg bg-blue-600 text-white flex items-center justify-center font-bold shadow-sm">
                    <FileText className="w-5 h-5" />
                  </div>
                  <div>
                    <h4 className="text-xs font-bold">CSV File (.csv)</h4>
                    <p className="text-[10px] text-slate-500 dark:text-slate-400 mt-0.5">
                      Google Sheets اور یونیورسل سسٹمز
                    </p>
                  </div>
                  <span className="mt-1 text-[11px] font-bold text-blue-700 dark:text-blue-400 flex items-center gap-1">
                    <ArrowDownToLine className="w-3.5 h-3.5" /> Download CSV
                  </span>
                </button>

                {/* 3. DATABASE BACKUP (.DB / .JSON) */}
                <button
                  onClick={() => exportStockToDB(products, settings.shopName)}
                  disabled={products.length === 0}
                  className={`p-4 rounded-xl border flex flex-col items-center text-center gap-2 transition-all cursor-pointer ${
                    isLight 
                      ? 'bg-purple-50 hover:bg-purple-100/80 border-purple-200 text-purple-900' 
                      : 'bg-purple-950/30 hover:bg-purple-900/40 border-purple-800/60 text-purple-200'
                  } ${products.length === 0 ? 'opacity-50 cursor-not-allowed' : 'hover:shadow-md'}`}
                >
                  <div className="w-10 h-10 rounded-lg bg-purple-600 text-white flex items-center justify-center font-bold shadow-sm">
                    <Database className="w-5 h-5" />
                  </div>
                  <div>
                    <h4 className="text-xs font-bold">Database Backup (.db)</h4>
                    <p className="text-[10px] text-slate-500 dark:text-slate-400 mt-0.5">
                      مکمل ڈیٹا بیس بیک اپ
                    </p>
                  </div>
                  <span className="mt-1 text-[11px] font-bold text-purple-700 dark:text-purple-400 flex items-center gap-1">
                    <ArrowDownToLine className="w-3.5 h-3.5" /> Download .db
                  </span>
                </button>
              </div>

              {products.length === 0 && (
                <div className="p-3 bg-amber-500/10 border border-amber-500/30 rounded-xl text-amber-600 text-xs flex items-center gap-2">
                  <AlertCircle className="w-4 h-4 shrink-0" />
                  <span>اسٹاک خالی ہے، ایکسپورٹ کرنے سے پہلے کچھ آئٹمز درج کریں۔</span>
                </div>
              )}
            </div>
          )}

          {/* ===================== TAB 2: IMPORT ===================== */}
          {activeTab === 'import' && (
            <div className="space-y-4">
              
              {/* Sample template banner */}
              <div className={`p-3 rounded-xl border flex flex-col sm:flex-row items-center justify-between gap-3 ${
                isLight ? 'bg-slate-50 border-slate-200' : 'bg-slate-800/50 border-slate-700'
              }`}>
                <div className="flex items-center gap-2.5 text-xs text-slate-600 dark:text-slate-300">
                  <HelpCircle className="w-4 h-4 text-blue-500 shrink-0" />
                  <span>اگر آپ کے پاس پہلے سے فائل نہیں ہے تو نمونہ (Sample Template) ڈاؤن لوڈ کریں:</span>
                </div>
                <div className="flex items-center gap-2 shrink-0">
                  <button
                    onClick={() => downloadStockSampleTemplate('xlsx')}
                    className="px-2.5 py-1.5 rounded-lg bg-emerald-700 hover:bg-emerald-800 text-white text-[11px] font-bold flex items-center gap-1 transition-all cursor-pointer"
                  >
                    <FileSpreadsheet className="w-3.5 h-3.5" /> Sample Excel
                  </button>
                  <button
                    onClick={() => downloadStockSampleTemplate('csv')}
                    className="px-2.5 py-1.5 rounded-lg bg-slate-700 hover:bg-slate-800 text-white text-[11px] font-bold flex items-center gap-1 transition-all cursor-pointer"
                  >
                    <FileText className="w-3.5 h-3.5" /> Sample CSV
                  </button>
                </div>
              </div>

              {/* Upload Drop Zone */}
              {!selectedFile ? (
                <div
                  onDragOver={handleDragOver}
                  onDrop={handleDrop}
                  onClick={() => fileInputRef.current?.click()}
                  className={`border-2 border-dashed rounded-2xl p-6 text-center cursor-pointer transition-all ${
                    isLight 
                      ? 'border-slate-300 hover:border-blue-500 bg-slate-50/50 hover:bg-blue-50/20' 
                      : 'border-slate-700 hover:border-blue-500 bg-slate-800/30 hover:bg-slate-800/60'
                  }`}
                >
                  <input
                    ref={fileInputRef}
                    type="file"
                    accept=".xlsx,.xls,.csv,.db,.json"
                    onChange={handleFileChange}
                    className="hidden"
                  />
                  <div className="w-12 h-12 rounded-2xl bg-blue-100 dark:bg-blue-950/60 text-blue-600 dark:text-blue-400 flex items-center justify-center mx-auto mb-3 shadow-inner">
                    <FileUp className="w-6 h-6" />
                  </div>
                  <h4 className="text-xs sm:text-sm font-bold text-slate-800 dark:text-slate-100">
                    یہاں فائل ڈراپ کریں یا کمپیوٹر سے منتخب کریں
                  </h4>
                  <p className="text-[11px] text-slate-500 dark:text-slate-400 mt-1">
                    Accepts <strong>.xlsx</strong>, <strong>.xls</strong>, <strong>.csv</strong>, or <strong>.db</strong> database files
                  </p>
                </div>
              ) : (
                /* File Selected & Preview */
                <div className="space-y-3">
                  <div className={`p-3 rounded-xl border flex items-center justify-between ${
                    isLight ? 'bg-slate-100 border-slate-200' : 'bg-slate-800 border-slate-700'
                  }`}>
                    <div className="flex items-center gap-3 overflow-hidden">
                      <div className="w-9 h-9 rounded-lg bg-blue-600 text-white flex items-center justify-center font-bold shrink-0">
                        {selectedFile.name.endsWith('.db') || selectedFile.name.endsWith('.json') ? (
                          <Database className="w-4 h-4" />
                        ) : selectedFile.name.endsWith('.csv') ? (
                          <FileText className="w-4 h-4" />
                        ) : (
                          <FileSpreadsheet className="w-4 h-4" />
                        )}
                      </div>
                      <div className="truncate">
                        <div className="text-xs font-bold truncate">{selectedFile.name}</div>
                        <div className="text-[10px] text-slate-500">{(selectedFile.size / 1024).toFixed(1)} KB</div>
                      </div>
                    </div>
                    <button
                      onClick={resetImport}
                      className="px-2.5 py-1 rounded-lg text-xs text-rose-600 hover:bg-rose-50 dark:hover:bg-rose-950/30 transition-colors cursor-pointer"
                    >
                      Change File
                    </button>
                  </div>

                  {isParsing && (
                    <div className="p-4 text-center text-xs text-slate-500 flex items-center justify-center gap-2">
                      <RefreshCw className="w-4 h-4 animate-spin text-blue-600" />
                      <span>فائل سے سامان کا ڈیٹا پڑھا جا رہا ہے...</span>
                    </div>
                  )}

                  {parseError && (
                    <div className="p-3 bg-rose-500/10 border border-rose-500/30 rounded-xl text-rose-600 text-xs flex items-start gap-2">
                      <AlertCircle className="w-4 h-4 shrink-0 mt-0.5" />
                      <div>
                        <strong>Error reading file:</strong>
                        <p className="mt-0.5">{parseError}</p>
                      </div>
                    </div>
                  )}

                  {parsedItems.length > 0 && (
                    <div className="space-y-3">
                      <div className="flex items-center justify-between text-xs">
                        <span className="font-bold text-emerald-600 dark:text-emerald-400 flex items-center gap-1.5">
                          <CheckCircle2 className="w-4 h-4" />
                          <span>{parsedItems.length} Products Found in File</span>
                        </span>

                        <span className="text-[11px] text-slate-500">
                          Total Qty: {parsedItems.reduce((s, i) => s + i.stock, 0)} Units
                        </span>
                      </div>

                      {/* Import Strategy Radio */}
                      <div className={`p-3 rounded-xl border ${
                        isLight ? 'bg-slate-50 border-slate-200' : 'bg-slate-800/40 border-slate-700'
                      }`}>
                        <span className="text-xs font-bold block mb-2 text-slate-700 dark:text-slate-200">
                          Import Mode منتخب کریں:
                        </span>
                        <div className="grid grid-cols-1 sm:grid-cols-2 gap-2">
                          <label className={`p-2.5 rounded-xl border flex items-start gap-2.5 cursor-pointer text-xs transition-all ${
                            importMode === 'merge'
                              ? 'bg-blue-500/10 border-blue-500 text-blue-700 dark:text-blue-300'
                              : 'border-slate-200 dark:border-slate-700 hover:bg-slate-100 dark:hover:bg-slate-800'
                          }`}>
                            <input
                              type="radio"
                              name="importMode"
                              value="merge"
                              checked={importMode === 'merge'}
                              onChange={() => setImportMode('merge')}
                              className="mt-0.5 text-blue-600 focus:ring-blue-500"
                            />
                            <div>
                              <strong className="block">Merge & Update (شامل کریں)</strong>
                              <span className="text-[10px] text-slate-500 dark:text-slate-400">
                                پرانے سامان کے ساتھ نیا سامان شامل ہوگا، موجودہ سامان محفوظ رہے گا۔
                              </span>
                            </div>
                          </label>

                          <label className={`p-2.5 rounded-xl border flex items-start gap-2.5 cursor-pointer text-xs transition-all ${
                            importMode === 'replace'
                              ? 'bg-rose-500/10 border-rose-500 text-rose-700 dark:text-rose-300'
                              : 'border-slate-200 dark:border-slate-700 hover:bg-slate-100 dark:hover:bg-slate-800'
                          }`}>
                            <input
                              type="radio"
                              name="importMode"
                              value="replace"
                              checked={importMode === 'replace'}
                              onChange={() => setImportMode('replace')}
                              className="mt-0.5 text-rose-600 focus:ring-rose-500"
                            />
                            <div>
                              <strong className="block">Replace All (مکمل تبدیل کریں)</strong>
                              <span className="text-[10px] text-slate-500 dark:text-slate-400">
                                پرانا اسٹاک ختم کر کے صرف اس فائل کا ڈیٹا سیو ہوگا۔
                              </span>
                            </div>
                          </label>
                        </div>
                      </div>

                      {/* Items Preview Table */}
                      <div className="border rounded-xl overflow-hidden border-slate-200 dark:border-slate-700 max-h-48 overflow-y-auto">
                        <table className="w-full text-[11px] text-left">
                          <thead className="sticky top-0 bg-slate-100 dark:bg-slate-800 font-bold border-b border-slate-200 dark:border-slate-700 text-slate-600 dark:text-slate-300">
                            <tr>
                              <th className="p-2">#</th>
                              <th className="p-2">Product Name</th>
                              <th className="p-2">Category</th>
                              <th className="p-2">IMEI / Barcode</th>
                              <th className="p-2 text-right">Stock</th>
                              <th className="p-2 text-right">Purchase</th>
                              <th className="p-2 text-right">Sale</th>
                            </tr>
                          </thead>
                          <tbody className="divide-y divide-slate-100 dark:divide-slate-800">
                            {parsedItems.slice(0, 50).map((item, idx) => (
                              <tr key={idx} className="hover:bg-slate-50/50 dark:hover:bg-slate-800/30">
                                <td className="p-2 text-slate-400">{idx + 1}</td>
                                <td className="p-2 font-semibold text-slate-800 dark:text-slate-200 truncate max-w-[150px]">{item.name}</td>
                                <td className="p-2 text-slate-500">{item.category}</td>
                                <td className="p-2 font-mono text-[10px] text-slate-500 truncate max-w-[100px]">{item.imeiOrSerial || '-'}</td>
                                <td className="p-2 text-right font-bold text-blue-600 dark:text-blue-400">{item.stock}</td>
                                <td className="p-2 text-right text-slate-600 dark:text-slate-400">Rs. {item.purchasePrice}</td>
                                <td className="p-2 text-right font-bold text-emerald-600 dark:text-emerald-400">Rs. {item.salePrice}</td>
                              </tr>
                            ))}
                          </tbody>
                        </table>
                        {parsedItems.length > 50 && (
                          <div className="p-2 text-center text-[10px] text-slate-400 bg-slate-50 dark:bg-slate-800/60 border-t border-slate-200 dark:border-slate-700">
                            + {parsedItems.length - 50} more items will be imported
                          </div>
                        )}
                      </div>

                      {/* Action Import Button */}
                      <button
                        onClick={handleConfirmImport}
                        disabled={isSuccess}
                        className={`w-full py-3 rounded-xl text-xs font-bold flex items-center justify-center gap-2 shadow-lg transition-all cursor-pointer ${
                          isSuccess
                            ? 'bg-emerald-600 text-white'
                            : 'bg-blue-600 hover:bg-blue-700 text-white shadow-blue-600/20'
                        }`}
                      >
                        {isSuccess ? (
                          <>
                            <Check className="w-4 h-4" />
                            <span>اسٹاک کامیابی سے امپورٹ ہو گیا!</span>
                          </>
                        ) : (
                          <>
                            <Upload className="w-4 h-4" />
                            <span>Save {parsedItems.length} Products to Stock ({importMode === 'merge' ? 'Merge' : 'Replace'})</span>
                          </>
                        )}
                      </button>
                    </div>
                  )}
                </div>
              )}
            </div>
          )}

        </div>

        {/* Footer */}
        <div className="px-5 py-3 border-t border-slate-200 dark:border-slate-800 bg-slate-50/50 dark:bg-slate-800/30 flex justify-end">
          <button
            onClick={onClose}
            className="px-4 py-2 rounded-xl text-xs font-semibold bg-slate-200 hover:bg-slate-300 dark:bg-slate-800 dark:hover:bg-slate-700 transition-colors cursor-pointer"
          >
            Band Karein (Close)
          </button>
        </div>

      </div>
    </div>
  );
};
