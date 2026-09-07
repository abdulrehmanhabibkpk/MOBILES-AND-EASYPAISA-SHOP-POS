import React, { useState, useEffect } from 'react';
import { 
  PackagePlus, 
  X, 
  Check, 
  Smartphone, 
  Barcode, 
  AlertTriangle, 
  Plus, 
  Trash2, 
  Copy, 
  Search, 
  Sparkles,
  Layers,
  CheckCircle2,
  ListPlus,
  Zap,
  Info
} from 'lucide-react';
import { Product, ProductCategory, ProductUnitItem, AppSettings } from '../types';
import { COLOR_PRESETS, RAM_STORAGE_PRESETS, PTA_STATUS_OPTIONS } from '../lib/mobilePresets';

interface BulkStockAdjustmentModalProps {
  isOpen: boolean;
  onClose: () => void;
  products: Product[];
  onSaveBulkStock: (
    targetProduct: { isExisting: boolean; productId?: string; newProductData?: Omit<Product, 'id' | 'createdAt'> },
    newUnits: ProductUnitItem[],
    addedQuantity: number
  ) => void;
  settings: AppSettings;
  preSelectedProduct?: Product | null;
}

export const BulkStockAdjustmentModal: React.FC<BulkStockAdjustmentModalProps> = ({
  isOpen,
  onClose,
  products,
  onSaveBulkStock,
  settings,
  preSelectedProduct,
}) => {
  const isLight = settings.theme === 'light';
  const isEn = settings.language === 'en';

  // Mode: 'existing' or 'new'
  const [entryMode, setEntryMode] = useState<'existing' | 'new'>(
    preSelectedProduct ? 'existing' : 'new'
  );
  const [selectedProductId, setSelectedProductId] = useState<string>(
    preSelectedProduct?.id || ''
  );
  const [productSearch, setProductSearch] = useState('');

  // Common Product Details (باقی سب سیم ہوگا)
  const [brandOrModel, setBrandOrModel] = useState(preSelectedProduct?.brandOrModel || '');
  const [category, setCategory] = useState<ProductCategory>(preSelectedProduct?.category || 'MOBILES');
  const [purchasePrice, setPurchasePrice] = useState<number | ''>(preSelectedProduct?.purchasePrice || '');
  const [salePrice, setSalePrice] = useState<number | ''>(preSelectedProduct?.salePrice || '');
  const [defaultColor, setDefaultColor] = useState<string>(preSelectedProduct?.color || 'Black');
  const [ramStorage, setRamStorage] = useState<string>(preSelectedProduct?.ramStorage || '4GB / 64GB');
  const [condition, setCondition] = useState<'NEW' | 'USED'>(preSelectedProduct?.condition || 'NEW');
  const [ptaStatus, setPtaStatus] = useState<'PTA_APPROVED' | 'NON_PTA' | 'JV' | 'FACTORY_UNLOCK'>(
    preSelectedProduct?.ptaStatus || 'PTA_APPROVED'
  );
  const [warranty, setWarranty] = useState<string>(preSelectedProduct?.warranty || '1 Year Official Warranty');
  const [sku, setSku] = useState(preSelectedProduct?.sku || '');

  // Quantity of phones to add (e.g., 10 phones)
  const [quantity, setQuantity] = useState<number>(10);

  // IMEI Input Method: 'table' or 'bulkPaste'
  const [imeiInputMethod, setImeiInputMethod] = useState<'table' | 'bulkPaste'>('table');
  const [bulkPasteText, setBulkPasteText] = useState('');

  // List of units (each with unique IMEI)
  const [unitRows, setUnitRows] = useState<
    Array<{
      id: string;
      imei1: string;
      imei2: string;
      color: string;
      serialNo: string;
    }>
  >([]);

  // Duplicate IMEIs warning set
  const [duplicateImeis, setDuplicateImeis] = useState<Set<string>>(new Set());

  // Initialize unit rows when quantity changes
  useEffect(() => {
    const targetCount = Math.max(1, Math.min(100, quantity || 1));
    setUnitRows((prev) => {
      const updated = [...prev];
      if (updated.length < targetCount) {
        for (let i = updated.length; i < targetCount; i++) {
          updated.push({
            id: `unit-${Date.now()}-${i + 1}-${Math.random().toString(36).substring(2, 5)}`,
            imei1: '',
            imei2: '',
            color: defaultColor,
            serialNo: '',
          });
        }
      } else if (updated.length > targetCount) {
        updated.splice(targetCount);
      }
      return updated;
    });
  }, [quantity, defaultColor]);

  // When preSelectedProduct changes or selectedProductId changes
  useEffect(() => {
    if (selectedProductId) {
      const prod = products.find((p) => p.id === selectedProductId);
      if (prod) {
        setBrandOrModel(prod.brandOrModel || prod.name);
        setCategory(prod.category);
        setPurchasePrice(prod.purchasePrice);
        setSalePrice(prod.salePrice);
        setDefaultColor(prod.color || 'Black');
        setRamStorage(prod.ramStorage || '4GB / 64GB');
        setCondition(prod.condition || 'NEW');
        setPtaStatus(prod.ptaStatus || 'PTA_APPROVED');
        setWarranty(prod.warranty || '1 Year Official Warranty');
        setSku(prod.sku || '');
      }
    }
  }, [selectedProductId, products]);

  // Check for duplicate IMEIs
  useEffect(() => {
    const counts: Record<string, number> = {};
    const dupes = new Set<string>();

    unitRows.forEach((row) => {
      const im1 = row.imei1.trim();
      const im2 = row.imei2.trim();
      if (im1) {
        counts[im1] = (counts[im1] || 0) + 1;
        if (counts[im1] > 1) dupes.add(im1);
      }
      if (im2) {
        counts[im2] = (counts[im2] || 0) + 1;
        if (counts[im2] > 1) dupes.add(im2);
      }
    });

    setDuplicateImeis(dupes);
  }, [unitRows]);

  if (!isOpen) return null;

  const updateRowField = (index: number, field: 'imei1' | 'imei2' | 'color' | 'serialNo', value: string) => {
    setUnitRows((prev) => {
      const next = [...prev];
      if (next[index]) {
        next[index] = { ...next[index], [field]: value };
      }
      return next;
    });
  };

  // Process bulk paste of IMEIs
  const handleProcessBulkPaste = () => {
    if (!bulkPasteText.trim()) return;
    const lines = bulkPasteText
      .split(/[\r\n,;\t]+/)
      .map((s) => s.trim())
      .filter(Boolean);

    if (lines.length === 0) return;

    // Adjust quantity if user pasted more
    const newCount = Math.max(lines.length, quantity);
    setQuantity(newCount);

    setUnitRows((prev) => {
      const updated: typeof unitRows = [];
      for (let i = 0; i < newCount; i++) {
        const existing = prev[i];
        updated.push({
          id: existing?.id || `unit-${Date.now()}-${i + 1}`,
          imei1: lines[i] || existing?.imei1 || '',
          imei2: existing?.imei2 || '',
          color: existing?.color || defaultColor,
          serialNo: existing?.serialNo || '',
        });
      }
      return updated;
    });

    setImeiInputMethod('table');
    setBulkPasteText('');
  };

  // Submit Handler
  const handleSubmit = (e: React.FormEvent) => {
    e.preventDefault();

    const pPrice = Number(purchasePrice) || 0;
    const sPrice = Number(salePrice) || 0;

    if (pPrice <= 0 || sPrice <= 0) {
      alert('براہ کرم خرید قیمت اور فروخت قیمت درست درج کریں۔');
      return;
    }

    if (entryMode === 'existing' && !selectedProductId) {
      alert('براہ کرم اسٹاک میں سے پروڈکٹ منتخب کریں۔');
      return;
    }

    if (entryMode === 'new' && !brandOrModel.trim()) {
      alert('براہ کرم فون کا برانڈ یا ماڈل نام درج کریں۔');
      return;
    }

    // Convert unitRows to ProductUnitItem
    const preparedUnits: ProductUnitItem[] = unitRows.map((r, idx) => ({
      id: r.id || `unit-${Date.now()}-${idx + 1}`,
      imei1: r.imei1.trim(),
      imei2: r.imei2.trim() || undefined,
      color: r.color || defaultColor,
      serialNo: r.serialNo.trim() || undefined,
      storageRam: ramStorage,
      condition,
      ptaStatus,
      status: 'AVAILABLE' as const,
    }));

    if (entryMode === 'existing') {
      onSaveBulkStock(
        { isExisting: true, productId: selectedProductId },
        preparedUnits,
        preparedUnits.length
      );
    } else {
      const condLabel = condition === 'NEW' ? 'Pin Pack' : 'Used';
      const ptaLabel = ptaStatus === 'PTA_APPROVED' ? 'PTA' : 'Non-PTA';
      const autoTitle = `${brandOrModel.trim()} (${defaultColor}) - ${condLabel} [${ptaLabel}]`;

      const newProdData: Omit<Product, 'id' | 'createdAt'> = {
        name: autoTitle,
        category,
        purchasePrice: pPrice,
        salePrice: sPrice,
        stock: preparedUnits.length,
        brandOrModel: brandOrModel.trim(),
        color: defaultColor,
        ramStorage,
        condition,
        ptaStatus,
        warranty,
        sku: sku.trim() || (preparedUnits[0]?.imei1 ? preparedUnits[0].imei1 : `SKU-${Date.now()}`),
        imeiOrSerial: preparedUnits[0]?.imei1 || '',
        units: preparedUnits,
      };

      onSaveBulkStock(
        { isExisting: false, newProductData: newProdData },
        preparedUnits,
        preparedUnits.length
      );
    }

    onClose();
  };

  const filteredExistingProducts = products.filter((p) => {
    if (!productSearch) return true;
    const term = productSearch.toLowerCase();
    return (
      p.name.toLowerCase().includes(term) ||
      (p.brandOrModel && p.brandOrModel.toLowerCase().includes(term)) ||
      (p.sku && p.sku.toLowerCase().includes(term))
    );
  });

  const filledImeiCount = unitRows.filter((r) => r.imei1.trim().length > 0).length;

  return (
    <div className="fixed inset-0 z-50 flex items-center justify-center p-3 sm:p-5 bg-black/70 backdrop-blur-sm overflow-y-auto animate-fade-in font-sans">
      <div
        className={`w-full max-w-4xl my-auto rounded-3xl shadow-2xl border overflow-hidden transition-all ${
          isLight ? 'bg-white border-slate-200 text-slate-900' : 'bg-slate-900 border-slate-800 text-white'
        }`}
      >
        {/* Top Header */}
        <div className="bg-gradient-to-r from-emerald-600 via-teal-600 to-indigo-700 p-4 sm:p-6 text-white flex items-center justify-between">
          <div className="flex items-center gap-3">
            <div className="w-12 h-12 rounded-2xl bg-white/15 flex items-center justify-center font-bold text-white shadow-inner">
              <PackagePlus className="w-6 h-6" />
            </div>
            <div>
              <div className="flex items-center gap-2">
                <h2 className="text-xl sm:text-2xl font-black">
                  {isEn ? 'Bulk Stock Adjustment & Multi-IMEI Entry' : 'بلک اسٹاک ایڈجسٹمنٹ و ملٹی IMEI اینٹری'}
                </h2>
                <span className="bg-white/20 text-white text-[11px] font-extrabold px-2.5 py-0.5 rounded-full">
                  10+ Phones at Once
                </span>
              </div>
              <p className="text-emerald-100 text-xs sm:text-sm mt-0.5">
                {isEn
                  ? 'Add multiple phones with unique IMEIs in one single batch (all details shared)'
                  : 'ایک ساتھ 10 یا زیادہ فونز درج کریں — تمام معلومات سیم، ہر فون کا الگ IMEI'}
              </p>
            </div>
          </div>
          <button
            onClick={onClose}
            className="w-9 h-9 rounded-full bg-white/10 hover:bg-white/20 flex items-center justify-center transition-colors text-white"
          >
            <X className="w-5 h-5" />
          </button>
        </div>

        {/* Modal Body */}
        <form onSubmit={handleSubmit} className="p-4 sm:p-6 space-y-5 max-h-[80vh] overflow-y-auto">
          {/* Mode Switcher */}
          <div className="flex flex-col sm:flex-row items-center gap-3 p-1.5 rounded-2xl bg-slate-100 dark:bg-slate-800/80 border border-slate-200 dark:border-slate-700">
            <button
              type="button"
              onClick={() => setEntryMode('existing')}
              className={`flex-1 py-2.5 px-4 rounded-xl font-bold text-sm flex items-center justify-center gap-2 transition-all ${
                entryMode === 'existing'
                  ? 'bg-emerald-600 text-white shadow-md'
                  : 'text-slate-600 dark:text-slate-400 hover:text-slate-900 dark:hover:text-white'
              }`}
            >
              <Layers className="w-4 h-4" />
              <span>
                {isEn ? 'Add to Existing Stock Model' : 'موجودہ ماڈل کا مزید اسٹاک شامل کریں'}
              </span>
            </button>
            <button
              type="button"
              onClick={() => setEntryMode('new')}
              className={`flex-1 py-2.5 px-4 rounded-xl font-bold text-sm flex items-center justify-center gap-2 transition-all ${
                entryMode === 'new'
                  ? 'bg-emerald-600 text-white shadow-md'
                  : 'text-slate-600 dark:text-slate-400 hover:text-slate-900 dark:hover:text-white'
              }`}
            >
              <Plus className="w-4 h-4" />
              <span>
                {isEn ? 'Add Brand New Phone Model' : 'نئے فون ماڈل کا بلک اسٹاک بنائیں'}
              </span>
            </button>
          </div>

          {/* If Existing Product: Dropdown / Search */}
          {entryMode === 'existing' && (
            <div
              className={`p-4 rounded-2xl border ${
                isLight ? 'bg-emerald-50/50 border-emerald-200' : 'bg-emerald-950/20 border-emerald-900/60'
              } space-y-3`}
            >
              <div className="flex items-center justify-between">
                <label className="text-xs font-black uppercase tracking-wider text-emerald-800 dark:text-emerald-300 flex items-center gap-2">
                  <Smartphone className="w-4 h-4" />
                  {isEn ? 'Select Product to Add Stock to:' : 'وہ پراڈکٹ منتخب کریں جس میں نیا اسٹاک شامل کرنا ہے:'}
                </label>
                <span className="text-xs font-semibold text-emerald-700 dark:text-emerald-400">
                  {filteredExistingProducts.length} Available Items
                </span>
              </div>

              <div className="grid grid-cols-1 sm:grid-cols-2 gap-3">
                <div className="relative">
                  <Search className="w-4 h-4 absolute left-3 top-3 text-slate-400" />
                  <input
                    type="text"
                    placeholder={isEn ? 'Search product by name or model...' : 'پراڈکٹ تلاش کریں...'}
                    value={productSearch}
                    onChange={(e) => setProductSearch(e.target.value)}
                    className="w-full pl-9 pr-4 py-2.5 rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900 text-sm font-semibold focus:ring-2 focus:ring-emerald-500 outline-none"
                  />
                </div>

                <select
                  value={selectedProductId}
                  onChange={(e) => setSelectedProductId(e.target.value)}
                  className="w-full px-4 py-2.5 rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900 text-sm font-bold text-slate-800 dark:text-slate-100 focus:ring-2 focus:ring-emerald-500 outline-none"
                >
                  <option value="">-- {isEn ? 'Choose Existing Product' : 'پراڈکٹ چنیں'} --</option>
                  {filteredExistingProducts.map((prod) => (
                    <option key={prod.id} value={prod.id}>
                      {prod.name} (موجودہ اسٹاک: {prod.stock}) - Rs. {prod.salePrice.toLocaleString()}
                    </option>
                  ))}
                </select>
              </div>

              {selectedProductId && (
                <div className="text-xs font-medium text-emerald-800 dark:text-emerald-300 flex items-center gap-2 pt-1">
                  <CheckCircle2 className="w-4 h-4 text-emerald-500" />
                  <span>
                    منتخب پراڈکٹ کا موجودہ اسٹاک:{' '}
                    <strong>{products.find((p) => p.id === selectedProductId)?.stock || 0}</strong>۔ نیا اسٹاک شامل
                    ہونے کے بعد کل اسٹاک:{' '}
                    <strong>
                      {(products.find((p) => p.id === selectedProductId)?.stock || 0) + (Number(quantity) || 0)}
                    </strong>{' '}
                    ہو جائے گا۔
                  </span>
                </div>
              )}
            </div>
          )}

          {/* Section 1: Common Shared Specs (سب فونز کا سیم ڈیٹا) */}
          <div
            className={`p-4 sm:p-5 rounded-2xl border ${
              isLight ? 'bg-slate-50 border-slate-200' : 'bg-slate-800/40 border-slate-700'
            } space-y-4`}
          >
            <div className="flex items-center justify-between border-b pb-2 border-slate-200 dark:border-slate-700">
              <span className="text-xs font-black uppercase tracking-wider text-slate-700 dark:text-slate-300 flex items-center gap-2">
                <Sparkles className="w-4 h-4 text-amber-500" />
                {isEn ? '1. Common Specifications (Shared for all 10 phones)' : '1. مشترکہ تفصیلات (تمام فونز کا سیم ڈیٹا)'}
              </span>
              <span className="text-xs font-bold text-emerald-600 dark:text-emerald-400">
                ایک بار درج کریں، سب پر لاگو ہوگا
              </span>
            </div>

            <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3.5">
              {/* Brand / Model */}
              <div className="sm:col-span-2">
                <label className="block text-xs font-bold mb-1 text-slate-600 dark:text-slate-300">
                  {isEn ? 'Brand & Model (e.g. Nokia 106, Samsung A15)' : 'برانڈ و ماڈل کا نام'} *
                </label>
                <input
                  type="text"
                  required
                  value={brandOrModel}
                  onChange={(e) => setBrandOrModel(e.target.value)}
                  placeholder="مثال: Nokia 106 (2024)"
                  className="w-full px-3.5 py-2 rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900 text-sm font-semibold focus:ring-2 focus:ring-emerald-500 outline-none"
                />
              </div>

              {/* Purchase Price */}
              <div>
                <label className="block text-xs font-bold mb-1 text-slate-600 dark:text-slate-300">
                  {isEn ? 'Purchase Price (فی فون خرید قیمت)' : 'خرید قیمت (فی فون)'} *
                </label>
                <div className="relative">
                  <span className="absolute left-3 top-2 text-xs font-bold text-slate-400">Rs.</span>
                  <input
                    type="number"
                    required
                    min="0"
                    value={purchasePrice}
                    onChange={(e) => setPurchasePrice(e.target.value === '' ? '' : Number(e.target.value))}
                    placeholder="4500"
                    className="w-full pl-9 pr-3.5 py-2 rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900 text-sm font-black font-mono focus:ring-2 focus:ring-emerald-500 outline-none"
                  />
                </div>
              </div>

              {/* Sale Price */}
              <div>
                <label className="block text-xs font-bold mb-1 text-slate-600 dark:text-slate-300">
                  {isEn ? 'Sale Price (فی فون فروخت قیمت)' : 'فروخت قیمت (فی فون)'} *
                </label>
                <div className="relative">
                  <span className="absolute left-3 top-2 text-xs font-bold text-slate-400">Rs.</span>
                  <input
                    type="number"
                    required
                    min="0"
                    value={salePrice}
                    onChange={(e) => setSalePrice(e.target.value === '' ? '' : Number(e.target.value))}
                    placeholder="5200"
                    className="w-full pl-9 pr-3.5 py-2 rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900 text-sm font-black font-mono text-emerald-600 dark:text-emerald-400 focus:ring-2 focus:ring-emerald-500 outline-none"
                  />
                </div>
              </div>

              {/* Default Color */}
              <div>
                <label className="block text-xs font-bold mb-1 text-slate-600 dark:text-slate-300">
                  {isEn ? 'Default Color (بنیادی رنگ)' : 'بنیادی رنگ'}
                </label>
                <select
                  value={defaultColor}
                  onChange={(e) => setDefaultColor(e.target.value)}
                  className="w-full px-3 py-2 rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900 text-sm font-semibold focus:ring-2 focus:ring-emerald-500 outline-none"
                >
                  {COLOR_PRESETS.map((c) => (
                    <option key={c.name} value={c.name}>
                      {c.name}
                    </option>
                  ))}
                </select>
              </div>

              {/* Condition */}
              <div>
                <label className="block text-xs font-bold mb-1 text-slate-600 dark:text-slate-300">
                  {isEn ? 'Condition (حالت)' : 'فون کی حالت'}
                </label>
                <select
                  value={condition}
                  onChange={(e) => setCondition(e.target.value as 'NEW' | 'USED')}
                  className="w-full px-3 py-2 rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900 text-sm font-semibold focus:ring-2 focus:ring-emerald-500 outline-none"
                >
                  <option value="NEW">Pin Pack (نیا ڈبہ بند)</option>
                  <option value="USED">Used (سیکنڈ ہینڈ)</option>
                </select>
              </div>

              {/* PTA Status */}
              <div>
                <label className="block text-xs font-bold mb-1 text-slate-600 dark:text-slate-300">
                  {isEn ? 'PTA Status' : 'پی ٹی اے تصدیق'}
                </label>
                <select
                  value={ptaStatus}
                  onChange={(e) => setPtaStatus(e.target.value as any)}
                  className="w-full px-3 py-2 rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900 text-sm font-semibold focus:ring-2 focus:ring-emerald-500 outline-none"
                >
                  {PTA_STATUS_OPTIONS.map((opt) => (
                    <option key={opt.key} value={opt.key}>
                      {opt.label}
                    </option>
                  ))}
                </select>
              </div>

              {/* RAM / Storage */}
              <div>
                <label className="block text-xs font-bold mb-1 text-slate-600 dark:text-slate-300">
                  {isEn ? 'RAM / Storage / Type' : 'ریم / میموری'}
                </label>
                <input
                  type="text"
                  value={ramStorage}
                  onChange={(e) => setRamStorage(e.target.value)}
                  placeholder="Keypad / 4GB-64GB"
                  className="w-full px-3 py-2 rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900 text-sm font-semibold focus:ring-2 focus:ring-emerald-500 outline-none"
                />
              </div>
            </div>
          </div>

          {/* Section 2: Batch Quantity & Multi-IMEI Entry */}
          <div
            className={`p-4 sm:p-5 rounded-2xl border ${
              isLight ? 'bg-slate-50 border-slate-200' : 'bg-slate-800/40 border-slate-700'
            } space-y-4`}
          >
            {/* Quantity Controller + Tabs for Table vs Bulk Paste */}
            <div className="flex flex-col sm:flex-row items-stretch sm:items-center justify-between gap-4 border-b pb-3 border-slate-200 dark:border-slate-700">
              <div className="flex items-center gap-3">
                <div className="w-9 h-9 rounded-xl bg-blue-500/10 text-blue-600 dark:text-blue-400 flex items-center justify-center font-bold">
                  <Barcode className="w-5 h-5" />
                </div>
                <div>
                  <span className="text-xs font-black uppercase tracking-wider text-slate-700 dark:text-slate-300 block">
                    {isEn ? '2. Phone Units & Unique IMEIs' : '2. فونز کی تعداد اور منفرد IMEI نمبرز'}
                  </span>
                  <span className="text-xs text-slate-500">
                    {filledImeiCount} of {unitRows.length} IMEIs entered
                  </span>
                </div>
              </div>

              {/* Quantity Stepper */}
              <div className="flex items-center gap-2">
                <span className="text-xs font-bold text-slate-600 dark:text-slate-400">
                  {isEn ? 'Total Phones (تعداد):' : 'فونز کی کل تعداد:'}
                </span>
                <div className="flex items-center rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900 overflow-hidden shadow-sm">
                  <button
                    type="button"
                    onClick={() => setQuantity(Math.max(1, quantity - 1))}
                    className="px-3 py-1.5 hover:bg-slate-100 dark:hover:bg-slate-800 font-bold text-slate-600 dark:text-slate-400"
                  >
                    -
                  </button>
                  <input
                    type="number"
                    min="1"
                    max="100"
                    value={quantity}
                    onChange={(e) => setQuantity(Math.max(1, Math.min(100, Number(e.target.value) || 1)))}
                    className="w-14 text-center font-black font-mono text-sm outline-none bg-transparent"
                  />
                  <button
                    type="button"
                    onClick={() => setQuantity(Math.min(100, quantity + 1))}
                    className="px-3 py-1.5 hover:bg-slate-100 dark:hover:bg-slate-800 font-bold text-slate-600 dark:text-slate-400"
                  >
                    +
                  </button>
                </div>

                {/* Quick 5, 10, 20 pills */}
                <div className="flex items-center gap-1">
                  {[5, 10, 15, 20].map((num) => (
                    <button
                      key={num}
                      type="button"
                      onClick={() => setQuantity(num)}
                      className={`px-2 py-1 text-xs font-bold rounded-lg border transition-all ${
                        quantity === num
                          ? 'bg-blue-600 text-white border-blue-600'
                          : 'border-slate-200 dark:border-slate-700 text-slate-600 dark:text-slate-400 hover:bg-slate-100 dark:hover:bg-slate-800'
                      }`}
                    >
                      {num}
                    </button>
                  ))}
                </div>
              </div>
            </div>

            {/* Sub Tabs: Individual Rows vs Quick Paste */}
            <div className="flex items-center gap-2">
              <button
                type="button"
                onClick={() => setImeiInputMethod('table')}
                className={`px-3 py-1.5 rounded-xl text-xs font-bold flex items-center gap-1.5 transition-all ${
                  imeiInputMethod === 'table'
                    ? 'bg-blue-600 text-white shadow-sm'
                    : 'bg-slate-200 dark:bg-slate-800 text-slate-600 dark:text-slate-400 hover:text-slate-900'
                }`}
              >
                <ListPlus className="w-3.5 h-3.5" />
                <span>{isEn ? 'Row-by-Row Table' : 'ہر فون کا الگ خانہ (Row Table)'}</span>
              </button>

              <button
                type="button"
                onClick={() => setImeiInputMethod('bulkPaste')}
                className={`px-3 py-1.5 rounded-xl text-xs font-bold flex items-center gap-1.5 transition-all ${
                  imeiInputMethod === 'bulkPaste'
                    ? 'bg-blue-600 text-white shadow-sm'
                    : 'bg-slate-200 dark:bg-slate-800 text-slate-600 dark:text-slate-400 hover:text-slate-900'
                }`}
              >
                <Copy className="w-3.5 h-3.5" />
                <span>{isEn ? 'Quick Scan / Paste All at Once' : 'ایک ساتھ تمام بارکوڈ اسکین / پیسٹ کریں'}</span>
              </button>
            </div>

            {/* Mode 1: Quick Paste / Continuous Scan */}
            {imeiInputMethod === 'bulkPaste' && (
              <div className="space-y-3 p-4 rounded-2xl bg-blue-50/50 dark:bg-blue-950/20 border border-blue-200 dark:border-blue-900/60">
                <div className="flex items-center justify-between">
                  <span className="text-xs font-bold text-blue-900 dark:text-blue-300 flex items-center gap-1.5">
                    <Info className="w-4 h-4 text-blue-500" />
                    بارکوڈ اسکینر سے ایک کے بعد ایک اسکین کریں یا فہرست یہاں پیسٹ کریں:
                  </span>
                  <span className="text-[11px] font-bold text-blue-700 dark:text-blue-400">
                    ہر لائن پر ایک IMEI درج کریں
                  </span>
                </div>

                <textarea
                  rows={6}
                  value={bulkPasteText}
                  onChange={(e) => setBulkPasteText(e.target.value)}
                  placeholder={`354891092837461\n354891092837462\n354891092837463\n...`}
                  className="w-full p-3 rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900 text-xs font-mono font-bold tracking-wider outline-none focus:ring-2 focus:ring-blue-500"
                />

                <div className="flex items-center justify-between">
                  <span className="text-xs text-slate-500">
                    اسکینر تیزی سے تمام باکسز کو خودکار طریقے سے فِل کر دے گا۔
                  </span>
                  <button
                    type="button"
                    onClick={handleProcessBulkPaste}
                    className="px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white text-xs font-bold rounded-xl shadow-md transition-all flex items-center gap-2"
                  >
                    <Check className="w-4 h-4" />
                    <span>تمام {quantity} فونز پر لاگو کریں</span>
                  </button>
                </div>
              </div>
            )}

            {/* Mode 2: Row-by-Row Table */}
            {imeiInputMethod === 'table' && (
              <div className="space-y-2">
                {duplicateImeis.size > 0 && (
                  <div className="p-3 rounded-xl bg-amber-500/10 border border-amber-500/30 text-amber-700 dark:text-amber-400 text-xs font-bold flex items-center gap-2">
                    <AlertTriangle className="w-4 h-4 shrink-0 text-amber-500" />
                    <span>
                      وارننگ: کچھ IMEI نمبرز ایک سے زیادہ بار درج ہو رہے ہیں ({Array.from(duplicateImeis).join(', ')})!
                    </span>
                  </div>
                )}

                <div className="max-h-72 overflow-y-auto rounded-xl border border-slate-200 dark:border-slate-700 shadow-inner">
                  <table className="w-full text-left text-xs border-collapse">
                    <thead className="sticky top-0 bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-300 font-black uppercase text-[10px] tracking-wider border-b border-slate-200 dark:border-slate-700">
                      <tr>
                        <th className="py-2.5 px-3 w-16">#</th>
                        <th className="py-2.5 px-3">IMEI 1 (Primary / Scan) *</th>
                        <th className="py-2.5 px-3">IMEI 2 (Dual SIM - اختیاری)</th>
                        <th className="py-2.5 px-3 w-32">رنگ (Color)</th>
                        <th className="py-2.5 px-3 w-12 text-center">حالت</th>
                      </tr>
                    </thead>
                    <tbody className="divide-y divide-slate-100 dark:divide-slate-800 bg-white dark:bg-slate-900">
                      {unitRows.map((row, idx) => {
                        const isDupe = row.imei1 && duplicateImeis.has(row.imei1.trim());
                        return (
                          <tr
                            key={row.id}
                            className={`hover:bg-slate-50 dark:hover:bg-slate-800/50 transition-colors ${
                              isDupe ? 'bg-amber-500/5' : ''
                            }`}
                          >
                            <td className="py-2 px-3 font-bold text-slate-500">
                              <span className="w-6 h-6 rounded-full bg-slate-100 dark:bg-slate-800 flex items-center justify-center text-[11px] font-mono">
                                {idx + 1}
                              </span>
                            </td>
                            <td className="py-2 px-3">
                              <div className="relative">
                                <input
                                  type="text"
                                  placeholder={`فون #${idx + 1} کا IMEI 1 درج یا اسکین کریں`}
                                  value={row.imei1}
                                  onChange={(e) => updateRowField(idx, 'imei1', e.target.value)}
                                  className={`w-full px-3 py-1.5 rounded-lg border text-xs font-mono font-bold outline-none transition-all ${
                                    isDupe
                                      ? 'border-amber-500 bg-amber-50/20 text-amber-900 dark:text-amber-300'
                                      : 'border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 focus:border-blue-500'
                                  }`}
                                />
                              </div>
                            </td>
                            <td className="py-2 px-3">
                              <input
                                type="text"
                                placeholder="IMEI 2 (اختیاری)"
                                value={row.imei2}
                                onChange={(e) => updateRowField(idx, 'imei2', e.target.value)}
                                className="w-full px-3 py-1.5 rounded-lg border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 text-xs font-mono outline-none focus:border-blue-500"
                              />
                            </td>
                            <td className="py-2 px-3">
                              <select
                                value={row.color}
                                onChange={(e) => updateRowField(idx, 'color', e.target.value)}
                                className="w-full px-2 py-1.5 rounded-lg border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 text-xs font-semibold outline-none"
                              >
                                {COLOR_PRESETS.map((c) => (
                                  <option key={c.name} value={c.name}>
                                    {c.name}
                                  </option>
                                ))}
                              </select>
                            </td>
                            <td className="py-2 px-3 text-center">
                              {row.imei1.trim() ? (
                                <CheckCircle2 className="w-4 h-4 text-emerald-500 mx-auto" />
                              ) : (
                                <span className="w-2 h-2 rounded-full bg-slate-300 dark:bg-slate-600 block mx-auto" />
                              )}
                            </td>
                          </tr>
                        );
                      })}
                    </tbody>
                  </table>
                </div>
              </div>
            )}
          </div>

          {/* Bottom Summary & Actions */}
          <div
            className={`p-4 rounded-2xl border flex flex-col sm:flex-row items-center justify-between gap-4 ${
              isLight ? 'bg-slate-50 border-slate-200' : 'bg-slate-800/40 border-slate-700'
            }`}
          >
            <div className="text-xs space-y-1 text-center sm:text-left">
              <div className="font-bold text-slate-700 dark:text-slate-300">
                کل شامل ہونے والے فونز:{' '}
                <strong className="text-emerald-600 dark:text-emerald-400 text-sm font-black font-mono">
                  {quantity} Phones
                </strong>{' '}
                ({filledImeiCount} کے IMEI داخل ہوچکے ہیں)
              </div>
              <div className="text-slate-500">
                کل انویسٹمنٹ / خریداری قیمت:{' '}
                <strong className="font-mono text-slate-700 dark:text-slate-300">
                  Rs. {((Number(purchasePrice) || 0) * quantity).toLocaleString()}
                </strong>{' '}
                | متوقع سیل ویلیو:{' '}
                <strong className="font-mono text-blue-600">
                  Rs. {((Number(salePrice) || 0) * quantity).toLocaleString()}
                </strong>
              </div>
            </div>

            <div className="flex items-center gap-3 w-full sm:w-auto">
              <button
                type="button"
                onClick={onClose}
                className="flex-1 sm:flex-none px-5 py-2.5 rounded-xl border border-slate-300 dark:border-slate-700 text-slate-700 dark:text-slate-300 font-bold text-xs hover:bg-slate-100 dark:hover:bg-slate-800 transition-colors"
              >
                {isEn ? 'Cancel' : 'منسوخ'}
              </button>
              <button
                type="submit"
                className="flex-1 sm:flex-none px-6 py-2.5 rounded-xl bg-gradient-to-r from-emerald-600 to-teal-600 hover:from-emerald-700 hover:to-teal-700 text-white font-extrabold text-xs shadow-lg shadow-emerald-500/20 transition-all flex items-center justify-center gap-2"
              >
                <Check className="w-4 h-4" />
                <span>
                  {isEn
                    ? `Confirm & Add ${quantity} Phones to Stock`
                    : `تصدیق کریں اور یہ ${quantity} فون اسٹاک میں شامل کریں`}
                </span>
              </button>
            </div>
          </div>
        </form>
      </div>
    </div>
  );
};
