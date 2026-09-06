import React, { useState, useMemo } from 'react';
import { MobilePurchaseRecord, Product, AppSettings } from '../types';
import { 
  Folder, Download, Eye, Search, Image as ImageIcon, 
  ShieldCheck, Smartphone, Package, User, X, 
  HardDrive, Sparkles, Filter, ChevronLeft, ChevronRight, CheckCircle2, Zap
} from 'lucide-react';
import { compressImageToDataUrl } from '../lib/imageCompressor';

export interface FileItem {
  id: string;
  title: string;
  category: 'SELLER' | 'CNIC_FRONT' | 'CNIC_BACK' | 'MOBILE' | 'INVENTORY';
  url: string;
  date: string;
  refNo: string;
  createdAt: number;
  sizeKb?: number;
}

interface FileManagerViewProps {
  purchases: MobilePurchaseRecord[];
  products: Product[];
  settings: AppSettings;
}

const ITEMS_PER_PAGE = 24;

export const FileManagerView: React.FC<FileManagerViewProps> = ({
  purchases,
  products,
  settings,
}) => {
  const isEn = settings.language === 'en';
  const isLight = settings.theme === 'light';

  const [searchTerm, setSearchTerm] = useState('');
  const [selectedCategory, setSelectedCategory] = useState<string>('ALL');
  const [previewImage, setPreviewImage] = useState<FileItem | null>(null);
  const [currentPage, setCurrentPage] = useState<number>(1);
  const [isCompressingSelected, setIsCompressingSelected] = useState<boolean>(false);
  const [storageStatusMsg, setStorageStatusMsg] = useState<string | null>(null);

  // Memoize file extraction from in-memory synchronized purchases and products (0 Extra Firestore Reads!)
  const allFiles: FileItem[] = useMemo(() => {
    const files: FileItem[] = [];

    // 1. Purchases Photos (Seller, CNIC Front, CNIC Back, Mobile)
    purchases.forEach((p) => {
      const dateStr = p.date || new Date().toISOString().split('T')[0];
      const created = p.createdAt || Date.now();

      if (p.sellerPhoto) {
        files.push({
          id: `${p.id}-seller`,
          title: `${p.sellerName || 'Customer'} (Seller Photo)`,
          category: 'SELLER',
          url: p.sellerPhoto,
          date: dateStr,
          refNo: p.receiptNo || 'REC-PUR',
          createdAt: created,
          sizeKb: Math.round((p.sellerPhoto.length * 0.75) / 1024)
        });
      }
      if (p.cnicFrontPhoto) {
        files.push({
          id: `${p.id}-cnic-front`,
          title: `${p.sellerName || 'Customer'} (CNIC Front)`,
          category: 'CNIC_FRONT',
          url: p.cnicFrontPhoto,
          date: dateStr,
          refNo: p.receiptNo || 'REC-PUR',
          createdAt: created,
          sizeKb: Math.round((p.cnicFrontPhoto.length * 0.75) / 1024)
        });
      }
      if (p.cnicBackPhoto) {
        files.push({
          id: `${p.id}-cnic-back`,
          title: `${p.sellerName || 'Customer'} (CNIC Back)`,
          category: 'CNIC_BACK',
          url: p.cnicBackPhoto,
          date: dateStr,
          refNo: p.receiptNo || 'REC-PUR',
          createdAt: created,
          sizeKb: Math.round((p.cnicBackPhoto.length * 0.75) / 1024)
        });
      }
      if (p.mobilePhoto) {
        files.push({
          id: `${p.id}-mobile`,
          title: `${p.mobileBrandModel || 'Mobile Device'} (Mobile)`,
          category: 'MOBILE',
          url: p.mobilePhoto,
          date: dateStr,
          refNo: p.receiptNo || 'REC-PUR',
          createdAt: created,
          sizeKb: Math.round((p.mobilePhoto.length * 0.75) / 1024)
        });
      }
    });

    // 2. Inventory Product Photos
    products.forEach((prod) => {
      if (prod.image) {
        files.push({
          id: `prod-${prod.id}`,
          title: `${prod.name} (Stock Item)`,
          category: 'INVENTORY',
          url: prod.image,
          date: new Date(prod.createdAt || Date.now()).toISOString().split('T')[0],
          refNo: prod.sku || `SKU-${prod.id.slice(0, 6)}`,
          createdAt: prod.createdAt || Date.now(),
          sizeKb: Math.round((prod.image.length * 0.75) / 1024)
        });
      }
    });

    // Sort newest first
    return files.sort((a, b) => b.createdAt - a.createdAt);
  }, [purchases, products]);

  // Filter files
  const filteredFiles = useMemo(() => {
    const term = searchTerm.toLowerCase().trim();
    return allFiles.filter((file) => {
      const matchesSearch =
        !term ||
        file.title.toLowerCase().includes(term) ||
        file.refNo.toLowerCase().includes(term) ||
        file.date.includes(term);
      const matchesCategory =
        selectedCategory === 'ALL' || file.category === selectedCategory;
      return matchesSearch && matchesCategory;
    });
  }, [allFiles, searchTerm, selectedCategory]);

  // Paginated files to prevent DOM lag with hundreds of images
  const totalPages = Math.max(1, Math.ceil(filteredFiles.length / ITEMS_PER_PAGE));
  const currentPageSafe = Math.min(currentPage, totalPages);
  const paginatedFiles = useMemo(() => {
    const start = (currentPageSafe - 1) * ITEMS_PER_PAGE;
    return filteredFiles.slice(start, start + ITEMS_PER_PAGE);
  }, [filteredFiles, currentPageSafe]);

  // Calculate total saved storage
  const totalEstStorageKB = useMemo(() => {
    return allFiles.reduce((acc, f) => acc + (f.sizeKb || 40), 0);
  }, [allFiles]);

  const handleDownload = (url: string, filename: string) => {
    const a = document.createElement('a');
    a.href = url;
    a.download = `${filename.replace(/[^a-z0-9]/gi, '_').toLowerCase()}.jpg`;
    document.body.appendChild(a);
    a.click();
    document.body.removeChild(a);
  };

  const handleCategorySelect = (catId: string) => {
    setSelectedCategory(catId);
    setCurrentPage(1);
  };

  return (
    <div className="space-y-6 pb-20 animate-fadeIn">
      {/* ========================================================================= */}
      {/* TOP HERO BANNER & QUOTA SAVING METRICS */}
      {/* ========================================================================= */}
      <div className="bg-gradient-to-r from-slate-900 via-emerald-950 to-slate-900 rounded-3xl p-6 text-white shadow-xl border border-emerald-500/30 flex flex-col md:flex-row justify-between items-start md:items-center gap-5">
        <div className="space-y-1.5 max-w-2xl">
          <div className="flex items-center gap-2 text-emerald-400 text-xs font-black uppercase tracking-wider">
            <Folder className="w-4 h-4 text-emerald-400" />
            <span>{isEn ? 'Zero-Quota Ultra-Fast Vault' : 'زیرو کوٹہ الٹرا فاسٹ والٹ'}</span>
          </div>
          <h1 className="text-2xl sm:text-3xl font-black tracking-tight text-white">
            {isEn ? 'Photo File Manager & Vault' : 'فوٹو فائل مینیجر اور والٹ'}
          </h1>
          <p className="text-emerald-100/90 text-xs sm:text-sm leading-relaxed">
            {isEn 
              ? 'Photos are automatically compressed (~40KB) and cached in local memory. Viewing or searching files uses 0 Firestore Reads & 0 Writes, protecting your 50,000 Reads / 20,000 Writes limit forever.' 
              : 'تصاویر خودکار طور پر کمپریس ہو کر لوکل میموری کیش میں محفوظ رہتی ہیں۔ فائلز دیکھنے اور سرچ کرنے پر زیرو (0) فائر بیس ریڈز استعمال ہوتی ہیں جس سے آپ کا 50 ہزار روزانہ کوٹہ کبھی ختم نہیں ہوگا۔'}
          </p>
        </div>

        {/* Quota Stats Badges */}
        <div className="flex flex-wrap sm:flex-nowrap gap-3 w-full md:w-auto">
          <div className="bg-emerald-900/60 backdrop-blur-md px-4 py-3 rounded-2xl border border-emerald-500/40 text-center flex-1 sm:flex-initial shadow-inner">
            <div className="flex items-center justify-center gap-1 text-emerald-300">
              <Zap className="w-4 h-4" />
              <span className="text-xl font-black text-white">{allFiles.length}</span>
            </div>
            <span className="block text-[10px] text-emerald-200 font-extrabold uppercase mt-0.5">
              {isEn ? 'Archived Photos' : 'کل محفوظ فوٹوز'}
            </span>
          </div>

          <div className="bg-slate-800/80 backdrop-blur-md px-4 py-3 rounded-2xl border border-slate-700 text-center flex-1 sm:flex-initial shadow-inner">
            <div className="flex items-center justify-center gap-1 text-teal-300">
              <HardDrive className="w-4 h-4" />
              <span className="text-xl font-black text-white">
                {totalEstStorageKB > 1024 
                  ? `${(totalEstStorageKB / 1024).toFixed(1)} MB` 
                  : `${totalEstStorageKB} KB`}
              </span>
            </div>
            <span className="block text-[10px] text-teal-200 font-extrabold uppercase mt-0.5">
              {isEn ? 'Optimized Size' : 'کمپریسڈ سائز'}
            </span>
          </div>

          <div className="bg-emerald-800/40 backdrop-blur-md px-4 py-3 rounded-2xl border border-emerald-500/30 text-center flex-1 sm:flex-initial shadow-inner">
            <div className="flex items-center justify-center gap-1 text-emerald-300">
              <ShieldCheck className="w-4 h-4 text-emerald-400" />
              <span className="text-sm font-black text-emerald-300">0 Read/Write</span>
            </div>
            <span className="block text-[10px] text-emerald-200 font-extrabold uppercase mt-0.5">
              {isEn ? 'Cost / Browse' : 'فائل دیکھنے کا خرچہ'}
            </span>
          </div>
        </div>
      </div>

      {/* ========================================================================= */}
      {/* SEARCH, CATEGORIES & FILTER BAR */}
      {/* ========================================================================= */}
      <div className={`p-4 rounded-2xl shadow-sm border ${
        isLight ? 'bg-white border-neutral-200' : 'bg-neutral-900 border-neutral-800'
      } flex flex-col lg:flex-row gap-3 items-center justify-between`}>
        
        {/* Search Input */}
        <div className="relative w-full lg:w-96">
          <Search className="absolute left-3.5 top-3 w-4 h-4 text-neutral-400" />
          <input
            type="text"
            value={searchTerm}
            onChange={(e) => {
              setSearchTerm(e.target.value);
              setCurrentPage(1);
            }}
            placeholder={isEn ? "Search by seller name, mobile model, receipt no, or date..." : "کسٹمر کا نام، موبائل ماڈل، رسید نمبر یا تاریخ سے سرچ کریں..."}
            className={`w-full pl-10 pr-4 py-2.5 rounded-xl text-xs font-medium focus:outline-none focus:border-emerald-600 ${
              isLight ? 'bg-neutral-50 border border-neutral-200 text-neutral-900' : 'bg-neutral-800 border border-neutral-700 text-neutral-100'
            }`}
          />
          {searchTerm && (
            <button 
              onClick={() => setSearchTerm('')}
              className="absolute right-3 top-2.5 text-neutral-400 hover:text-neutral-600 text-xs font-bold"
            >
              ×
            </button>
          )}
        </div>

        {/* Category Pills */}
        <div className="flex flex-wrap gap-1.5 w-full lg:w-auto">
          {[
            { id: 'ALL', label: isEn ? 'All Photos' : 'تمام تصاویر', icon: Folder, count: allFiles.length },
            { id: 'SELLER', label: isEn ? 'Sellers' : 'سیلرز', icon: User, count: allFiles.filter(f => f.category === 'SELLER').length },
            { id: 'CNIC_FRONT', label: isEn ? 'CNIC Front' : 'شناختی کارڈ فرنٹ', icon: ShieldCheck, count: allFiles.filter(f => f.category === 'CNIC_FRONT').length },
            { id: 'CNIC_BACK', label: isEn ? 'CNIC Back' : 'شناختی کارڈ بیک', icon: ShieldCheck, count: allFiles.filter(f => f.category === 'CNIC_BACK').length },
            { id: 'MOBILE', label: isEn ? 'Mobiles' : 'موبائلز', icon: Smartphone, count: allFiles.filter(f => f.category === 'MOBILE').length },
            { id: 'INVENTORY', label: isEn ? 'Inventory' : 'اسٹاک آئٹمز', icon: Package, count: allFiles.filter(f => f.category === 'INVENTORY').length },
          ].map((cat) => {
            const Icon = cat.icon;
            const isSelected = selectedCategory === cat.id;
            return (
              <button
                key={cat.id}
                onClick={() => handleCategorySelect(cat.id)}
                className={`px-3 py-1.5 rounded-xl text-xs font-extrabold transition-all cursor-pointer flex items-center gap-1.5 ${
                  isSelected
                    ? 'bg-emerald-600 text-white shadow-md'
                    : isLight 
                      ? 'bg-neutral-100 text-neutral-700 hover:bg-neutral-200' 
                      : 'bg-neutral-800 text-neutral-300 hover:bg-neutral-700'
                }`}
              >
                <Icon className="w-3.5 h-3.5" />
                <span>{cat.label}</span>
                <span className={`text-[10px] px-1.5 py-0.2 rounded-full font-black ${
                  isSelected ? 'bg-emerald-800 text-emerald-100' : 'bg-neutral-200 dark:bg-neutral-700 text-neutral-600 dark:text-neutral-300'
                }`}>
                  {cat.count}
                </span>
              </button>
            );
          })}
        </div>
      </div>

      {/* ========================================================================= */}
      {/* FILES GALLERY GRID (LAZY-RENDERED WITH CACHED THUMBNAILS) */}
      {/* ========================================================================= */}
      {filteredFiles.length === 0 ? (
        <div className={`rounded-3xl p-12 text-center border shadow-sm space-y-3 ${
          isLight ? 'bg-white border-neutral-200' : 'bg-neutral-900 border-neutral-800'
        }`}>
          <div className="w-16 h-16 bg-emerald-50 dark:bg-emerald-950/60 text-emerald-600 dark:text-emerald-400 rounded-2xl flex items-center justify-center mx-auto shadow-inner">
            <ImageIcon className="w-8 h-8" />
          </div>
          <h3 className="text-base font-extrabold text-neutral-800 dark:text-neutral-200">
            {isEn ? 'No Photos Found' : 'کوئی تصویر نہیں ملی'}
          </h3>
          <p className="text-xs text-neutral-500 max-w-sm mx-auto">
            {isEn 
              ? 'No photos match your current filter or no files have been uploaded yet in mobile purchases or inventory.' 
              : 'آپ کے منتخب کردہ فلٹر کے مطابق کوئی تصویر موجود نہیں ہے یا ابھی تک کوئی موبائل فوٹو اپ لوڈ نہیں ہوئی۔'}
          </p>
        </div>
      ) : (
        <div className="space-y-4">
          <div className="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-6 gap-3.5">
            {paginatedFiles.map((file) => (
              <div
                key={file.id}
                className={`rounded-2xl overflow-hidden border shadow-sm hover:shadow-lg transition-all flex flex-col group ${
                  isLight ? 'bg-white border-neutral-200 hover:border-emerald-500' : 'bg-neutral-900 border-neutral-800 hover:border-emerald-500'
                }`}
              >
                {/* Image Container with Native Lazy Loading */}
                <div 
                  className="relative h-36 bg-neutral-100 dark:bg-neutral-800 overflow-hidden cursor-pointer"
                  onClick={() => setPreviewImage(file)}
                >
                  <img
                    src={file.url}
                    alt={file.title}
                    loading="lazy"
                    decoding="async"
                    className="w-full h-full object-cover group-hover:scale-105 transition-transform duration-300"
                  />
                  <div className="absolute top-2 left-2">
                    <span className={`px-2 py-0.5 rounded-md text-[8.5px] font-black uppercase shadow-md tracking-wider ${
                      file.category === 'SELLER' ? 'bg-blue-600 text-white' :
                      file.category === 'CNIC_FRONT' ? 'bg-emerald-600 text-white' :
                      file.category === 'CNIC_BACK' ? 'bg-teal-600 text-white' :
                      file.category === 'MOBILE' ? 'bg-purple-600 text-white' : 'bg-amber-600 text-white'
                    }`}>
                      {file.category.replace('_', ' ')}
                    </span>
                  </div>

                  {file.sizeKb && (
                    <div className="absolute bottom-1.5 right-1.5 bg-black/60 backdrop-blur-sm text-emerald-300 font-mono text-[9px] font-bold px-1.5 py-0.5 rounded">
                      ~{file.sizeKb} KB
                    </div>
                  )}
                </div>

                {/* File Metadata */}
                <div className="p-2.5 flex-1 flex flex-col justify-between space-y-1.5">
                  <div>
                    <h4 className="text-[11px] font-bold text-neutral-900 dark:text-neutral-100 truncate" title={file.title}>
                      {file.title}
                    </h4>
                    <div className="flex justify-between items-center text-[9.5px] text-neutral-500 mt-0.5">
                      <span className="font-mono font-bold text-emerald-600 truncate max-w-[55%]">{file.refNo}</span>
                      <span className="whitespace-nowrap">{file.date}</span>
                    </div>
                  </div>

                  {/* Actions */}
                  <div className="flex items-center gap-1 pt-1.5 border-t border-neutral-100 dark:border-neutral-800">
                    <button
                      onClick={() => setPreviewImage(file)}
                      className="flex-1 py-1 bg-neutral-100 dark:bg-neutral-800 hover:bg-neutral-200 dark:hover:bg-neutral-700 text-neutral-700 dark:text-neutral-300 text-[10.5px] font-bold rounded-lg flex items-center justify-center gap-1 transition-colors cursor-pointer"
                    >
                      <Eye className="w-3 h-3" />
                      <span>{isEn ? 'View' : 'دیکھیں'}</span>
                    </button>

                    <button
                      onClick={() => handleDownload(file.url, file.title)}
                      className="py-1 px-2 bg-emerald-50 dark:bg-emerald-950/60 hover:bg-emerald-100 text-emerald-700 dark:text-emerald-400 text-[10.5px] font-bold rounded-lg flex items-center justify-center gap-1 transition-colors cursor-pointer"
                      title={isEn ? "Download Original Photo" : "ڈاؤنلوڈ کریں"}
                    >
                      <Download className="w-3 h-3" />
                    </button>
                  </div>
                </div>
              </div>
            ))}
          </div>

          {/* Pagination Controls */}
          {totalPages > 1 && (
            <div className={`p-3.5 rounded-2xl border flex items-center justify-between shadow-sm ${
              isLight ? 'bg-white border-neutral-200' : 'bg-neutral-900 border-neutral-800'
            }`}>
              <div className="text-xs font-semibold text-neutral-500">
                {isEn 
                  ? `Showing ${(currentPageSafe - 1) * ITEMS_PER_PAGE + 1} - ${Math.min(currentPageSafe * ITEMS_PER_PAGE, filteredFiles.length)} of ${filteredFiles.length} photos` 
                  : `کل ${filteredFiles.length} میں سے ${(currentPageSafe - 1) * ITEMS_PER_PAGE + 1} تا ${Math.min(currentPageSafe * ITEMS_PER_PAGE, filteredFiles.length)} تصاویر`}
              </div>

              <div className="flex items-center gap-2">
                <button
                  disabled={currentPageSafe <= 1}
                  onClick={() => setCurrentPage(p => Math.max(1, p - 1))}
                  className={`p-2 rounded-xl text-xs font-bold flex items-center gap-1 border transition-all ${
                    currentPageSafe <= 1 
                      ? 'opacity-40 cursor-not-allowed bg-neutral-100 dark:bg-neutral-800 border-neutral-200' 
                      : 'hover:bg-emerald-50 dark:hover:bg-neutral-800 border-neutral-300 text-neutral-800 dark:text-neutral-200 cursor-pointer'
                  }`}
                >
                  <ChevronLeft className="w-4 h-4" />
                  <span className="hidden sm:inline">{isEn ? 'Previous' : 'پچھلا'}</span>
                </button>

                <span className="text-xs font-black px-3 py-1 bg-emerald-100 dark:bg-emerald-950 text-emerald-800 dark:text-emerald-300 rounded-xl border border-emerald-300 dark:border-emerald-800">
                  {currentPageSafe} / {totalPages}
                </span>

                <button
                  disabled={currentPageSafe >= totalPages}
                  onClick={() => setCurrentPage(p => Math.min(totalPages, p + 1))}
                  className={`p-2 rounded-xl text-xs font-bold flex items-center gap-1 border transition-all ${
                    currentPageSafe >= totalPages 
                      ? 'opacity-40 cursor-not-allowed bg-neutral-100 dark:bg-neutral-800 border-neutral-200' 
                      : 'hover:bg-emerald-50 dark:hover:bg-neutral-800 border-neutral-300 text-neutral-800 dark:text-neutral-200 cursor-pointer'
                  }`}
                >
                  <span className="hidden sm:inline">{isEn ? 'Next' : 'اگلا'}</span>
                  <ChevronRight className="w-4 h-4" />
                </button>
              </div>
            </div>
          )}
        </div>
      )}

      {/* ========================================================================= */}
      {/* HIGH-RES FULL PREVIEW MODAL */}
      {/* ========================================================================= */}
      {previewImage && (
        <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/85 backdrop-blur-md p-3 sm:p-5 animate-fadeIn">
          <div className="bg-neutral-900 rounded-3xl max-w-3xl w-full overflow-hidden shadow-2xl border border-neutral-700 flex flex-col max-h-[92vh]">
            {/* Modal Header */}
            <div className="p-4 bg-slate-950 text-white flex justify-between items-center border-b border-neutral-800">
              <div className="space-y-0.5">
                <div className="flex items-center gap-2">
                  <span className={`px-2 py-0.5 rounded text-[9px] font-black uppercase ${
                    previewImage.category === 'SELLER' ? 'bg-blue-600 text-white' :
                    previewImage.category === 'CNIC_FRONT' ? 'bg-emerald-600 text-white' :
                    previewImage.category === 'CNIC_BACK' ? 'bg-teal-600 text-white' :
                    previewImage.category === 'MOBILE' ? 'bg-purple-600 text-white' : 'bg-amber-600 text-white'
                  }`}>
                    {previewImage.category.replace('_', ' ')}
                  </span>
                  <h3 className="font-extrabold text-sm sm:text-base text-white truncate max-w-sm sm:max-w-md">
                    {previewImage.title}
                  </h3>
                </div>
                <div className="text-[10.5px] text-neutral-400 font-mono flex items-center gap-3">
                  <span>Ref: <strong>{previewImage.refNo}</strong></span>
                  <span>Date: {previewImage.date}</span>
                  {previewImage.sizeKb && <span>Size: ~{previewImage.sizeKb} KB</span>}
                </div>
              </div>
              <button
                onClick={() => setPreviewImage(null)}
                className="w-9 h-9 rounded-full bg-white/10 hover:bg-white/20 text-white flex items-center justify-center transition-colors cursor-pointer"
              >
                <X className="w-5 h-5" />
              </button>
            </div>

            {/* Modal Image Body */}
            <div className="p-4 bg-neutral-950 flex items-center justify-center overflow-auto flex-1">
              <img
                src={previewImage.url}
                alt={previewImage.title}
                className="max-h-[62vh] w-auto max-w-full object-contain rounded-xl shadow-2xl"
              />
            </div>

            {/* Modal Footer */}
            <div className="p-4 bg-neutral-900 flex flex-wrap justify-between items-center gap-3 border-t border-neutral-800">
              <div className="text-[11px] text-emerald-400 flex items-center gap-1.5 font-bold">
                <CheckCircle2 className="w-4 h-4 text-emerald-400" />
                <span>{isEn ? 'Optimized in Memory (Zero Firestore Quota Used)' : 'لوکل میموری سے لوڈ شدہ (فائر بیس کوٹہ مکمل محفوظ)'}</span>
              </div>

              <div className="flex items-center gap-2">
                <button
                  onClick={() => handleDownload(previewImage.url, previewImage.title)}
                  className="py-2 px-4 bg-emerald-600 hover:bg-emerald-700 text-white font-black text-xs rounded-xl flex items-center gap-2 transition-all cursor-pointer shadow-md"
                >
                  <Download className="w-4 h-4" />
                  <span>{isEn ? 'Download HD Photo' : 'ایچ ڈی فوٹو ڈاؤنلوڈ'}</span>
                </button>
                <button
                  onClick={() => setPreviewImage(null)}
                  className="py-2 px-4 bg-neutral-800 hover:bg-neutral-700 text-neutral-300 font-bold text-xs rounded-xl transition-colors cursor-pointer"
                >
                  {isEn ? 'Close' : 'بند کریں'}
                </button>
              </div>
            </div>
          </div>
        </div>
      )}
    </div>
  );
};
