import React, { useState } from 'react';
import { MobilePurchaseRecord, Product, AppSettings } from '../types';
import { Folder, Download, Eye, Search, Trash2, Image as ImageIcon, ShieldCheck, Smartphone, Package, User, X } from 'lucide-react';

export interface FileItem {
  id: string;
  title: string;
  category: 'SELLER' | 'CNIC_FRONT' | 'CNIC_BACK' | 'MOBILE' | 'INVENTORY';
  url: string;
  date: string;
  refNo: string;
  createdAt: number;
}

interface FileManagerViewProps {
  purchases: MobilePurchaseRecord[];
  products: Product[];
  settings: AppSettings;
}

export const FileManagerView: React.FC<FileManagerViewProps> = ({
  purchases,
  products,
  settings,
}) => {
  const [searchTerm, setSearchTerm] = useState('');
  const [selectedCategory, setSelectedCategory] = useState<string>('ALL');
  const [previewImage, setPreviewImage] = useState<{ url: string; title: string; refNo: string } | null>(null);

  // Extract all photos from purchases and inventory products
  const allFiles: FileItem[] = [];

  purchases.forEach((p) => {
    const dateStr = p.date || new Date().toISOString().split('T')[0];
    if (p.sellerPhoto) {
      allFiles.push({
        id: `${p.id}-seller`,
        title: `${p.sellerName} (Seller Photo)`,
        category: 'SELLER',
        url: p.sellerPhoto,
        date: dateStr,
        refNo: p.receiptNo,
        createdAt: p.createdAt || Date.now(),
      });
    }
    if (p.cnicFrontPhoto) {
      allFiles.push({
        id: `${p.id}-cnic-front`,
        title: `${p.sellerName} (CNIC Front)`,
        category: 'CNIC_FRONT',
        url: p.cnicFrontPhoto,
        date: dateStr,
        refNo: p.receiptNo,
        createdAt: p.createdAt || Date.now(),
      });
    }
    if (p.cnicBackPhoto) {
      allFiles.push({
        id: `${p.id}-cnic-back`,
        title: `${p.sellerName} (CNIC Back)`,
        category: 'CNIC_BACK',
        url: p.cnicBackPhoto,
        date: dateStr,
        refNo: p.receiptNo,
        createdAt: p.createdAt || Date.now(),
      });
    }
    if (p.mobilePhoto) {
      allFiles.push({
        id: `${p.id}-mobile`,
        title: `${p.mobileBrandModel} (Mobile)`,
        category: 'MOBILE',
        url: p.mobilePhoto,
        date: dateStr,
        refNo: p.receiptNo,
        createdAt: p.createdAt || Date.now(),
      });
    }
  });

  products.forEach((prod) => {
    if (prod.image) {
      allFiles.push({
        id: `prod-${prod.id}`,
        title: `${prod.name} (Inventory)`,
        category: 'INVENTORY',
        url: prod.image,
        date: new Date(prod.createdAt || Date.now()).toISOString().split('T')[0],
        refNo: prod.sku || `PROD-${prod.id.slice(0, 5)}`,
        createdAt: prod.createdAt || Date.now(),
      });
    }
  });

  // Filter files
  const filteredFiles = allFiles.filter((file) => {
    const matchesSearch =
      !searchTerm ||
      file.title.toLowerCase().includes(searchTerm.toLowerCase()) ||
      file.refNo.toLowerCase().includes(searchTerm.toLowerCase());
    const matchesCategory =
      selectedCategory === 'ALL' || file.category === selectedCategory;
    return matchesSearch && matchesCategory;
  });

  const handleDownload = (url: string, filename: string) => {
    const a = document.createElement('a');
    a.href = url;
    a.download = `${filename.replace(/[^a-z0-9]/gi, '_').toLowerCase()}.jpg`;
    document.body.appendChild(a);
    a.click();
    document.body.removeChild(a);
  };

  return (
    <div className="space-y-6 pb-20">
      {/* Header Banner */}
      <div className="bg-gradient-to-r from-emerald-900 via-emerald-800 to-teal-900 rounded-3xl p-6 text-white shadow-xl flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4">
        <div>
          <div className="flex items-center gap-2 text-emerald-300 text-xs font-black uppercase tracking-wider mb-1">
            <Folder className="w-4 h-4" />
            <span>Permanent Vault & Archive</span>
          </div>
          <h1 className="text-2xl sm:text-3xl font-black">
            {settings.language === 'en' ? 'Photo File Manager' : 'فوٹو فائل مینیجر'}
          </h1>
          <p className="text-emerald-100/80 text-xs mt-1">
            All uploaded seller photos, CNIC cards, mobile pictures & inventory items are securely archived here.
          </p>
        </div>

        <div className="bg-white/10 backdrop-blur-md px-4 py-3 rounded-2xl border border-white/20 text-center">
          <span className="text-2xl font-black text-emerald-300">{allFiles.length}</span>
          <span className="block text-[10px] text-emerald-100 font-bold uppercase">Total Archived Files</span>
        </div>
      </div>

      {/* Search & Category Filter Bar */}
      <div className="bg-white dark:bg-neutral-900 p-4 rounded-2xl shadow-sm border border-neutral-200 dark:border-neutral-800 flex flex-col sm:flex-row gap-3 items-center justify-between">
        <div className="relative w-full sm:w-80">
          <Search className="absolute left-3.5 top-3 w-4 h-4 text-neutral-400" />
          <input
            type="text"
            value={searchTerm}
            onChange={(e) => setSearchTerm(e.target.value)}
            placeholder="Search by title or reference..."
            className="w-full pl-10 pr-4 py-2 bg-neutral-50 dark:bg-neutral-800 border border-neutral-200 dark:border-neutral-700 rounded-xl text-xs font-medium focus:outline-none focus:border-emerald-600 text-neutral-900 dark:text-neutral-100"
          />
        </div>

        <div className="flex flex-wrap gap-1.5 w-full sm:w-auto">
          {[
            { id: 'ALL', label: 'All Files' },
            { id: 'SELLER', label: 'Sellers' },
            { id: 'CNIC_FRONT', label: 'CNIC Front' },
            { id: 'CNIC_BACK', label: 'CNIC Back' },
            { id: 'MOBILE', label: 'Mobiles' },
            { id: 'INVENTORY', label: 'Inventory' },
          ].map((cat) => (
            <button
              key={cat.id}
              onClick={() => setSelectedCategory(cat.id)}
              className={`px-3 py-1.5 rounded-xl text-xs font-bold transition-all cursor-pointer ${
                selectedCategory === cat.id
                  ? 'bg-emerald-600 text-white shadow-md'
                  : 'bg-neutral-100 dark:bg-neutral-800 text-neutral-600 dark:text-neutral-300 hover:bg-neutral-200 dark:hover:bg-neutral-700'
              }`}
            >
              {cat.label}
            </button>
          ))}
        </div>
      </div>

      {/* Files Grid */}
      {filteredFiles.length === 0 ? (
        <div className="bg-white dark:bg-neutral-900 rounded-3xl p-12 text-center border border-neutral-200 dark:border-neutral-800 shadow-sm space-y-3">
          <div className="w-16 h-16 bg-emerald-50 dark:bg-emerald-950/60 text-emerald-600 dark:text-emerald-400 rounded-2xl flex items-center justify-center mx-auto">
            <ImageIcon className="w-8 h-8" />
          </div>
          <h3 className="text-base font-extrabold text-neutral-800 dark:text-neutral-200">No Photos Found</h3>
          <p className="text-xs text-neutral-500 max-w-sm mx-auto">
            No photos match your current filter or no files have been uploaded yet in purchases or inventory.
          </p>
        </div>
      ) : (
        <div className="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-5 gap-4">
          {filteredFiles.map((file) => (
            <div
              key={file.id}
              className="bg-white dark:bg-neutral-900 rounded-2xl overflow-hidden border border-neutral-200 dark:border-neutral-800 shadow-sm hover:shadow-md transition-all flex flex-col group"
            >
              <div className="relative h-40 bg-neutral-100 dark:bg-neutral-800 overflow-hidden">
                <img
                  src={file.url}
                  alt={file.title}
                  className="w-full h-full object-cover group-hover:scale-105 transition-transform duration-300"
                />
                <div className="absolute top-2 left-2">
                  <span className={`px-2 py-0.5 rounded-md text-[9px] font-black uppercase shadow-sm ${
                    file.category === 'SELLER' ? 'bg-blue-600 text-white' :
                    file.category === 'CNIC_FRONT' ? 'bg-emerald-600 text-white' :
                    file.category === 'CNIC_BACK' ? 'bg-teal-600 text-white' :
                    file.category === 'MOBILE' ? 'bg-purple-600 text-white' : 'bg-amber-600 text-white'
                  }`}>
                    {file.category.replace('_', ' ')}
                  </span>
                </div>
              </div>

              <div className="p-3 flex-1 flex flex-col justify-between space-y-2">
                <div>
                  <h4 className="text-xs font-bold text-neutral-900 dark:text-neutral-100 truncate" title={file.title}>
                    {file.title}
                  </h4>
                  <div className="flex justify-between items-center text-[10px] text-neutral-500 mt-0.5">
                    <span className="font-mono font-bold text-emerald-600">{file.refNo}</span>
                    <span>{file.date}</span>
                  </div>
                </div>

                <div className="flex items-center gap-1.5 pt-1 border-t border-neutral-100 dark:border-neutral-800">
                  <button
                    onClick={() => setPreviewImage({ url: file.url, title: file.title, refNo: file.refNo })}
                    className="flex-1 py-1.5 bg-neutral-100 dark:bg-neutral-800 hover:bg-neutral-200 dark:hover:bg-neutral-700 text-neutral-700 dark:text-neutral-300 text-[11px] font-bold rounded-xl flex items-center justify-center gap-1 transition-colors cursor-pointer"
                  >
                    <Eye className="w-3.5 h-3.5" />
                    <span>View</span>
                  </button>

                  <button
                    onClick={() => handleDownload(file.url, file.title)}
                    className="py-1.5 px-2.5 bg-emerald-50 dark:bg-emerald-950/60 hover:bg-emerald-100 text-emerald-700 dark:text-emerald-400 text-[11px] font-bold rounded-xl flex items-center justify-center gap-1 transition-colors cursor-pointer"
                    title="Download Photo"
                  >
                    <Download className="w-3.5 h-3.5" />
                  </button>
                </div>
              </div>
            </div>
          ))}
        </div>
      )}

      {/* Image Preview Modal */}
      {previewImage && (
        <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/80 backdrop-blur-md p-4">
          <div className="bg-white dark:bg-neutral-900 rounded-3xl max-w-2xl w-full overflow-hidden shadow-2xl border border-neutral-200 dark:border-neutral-800">
            <div className="p-4 bg-neutral-900 text-white flex justify-between items-center">
              <div>
                <h3 className="font-extrabold text-sm">{previewImage.title}</h3>
                <span className="text-[10px] text-neutral-400 font-mono">Ref: {previewImage.refNo}</span>
              </div>
              <button
                onClick={() => setPreviewImage(null)}
                className="w-8 h-8 rounded-full bg-white/20 hover:bg-white/30 text-white flex items-center justify-center transition-colors cursor-pointer"
              >
                <X className="w-4 h-4" />
              </button>
            </div>

            <div className="p-4 bg-neutral-950 flex items-center justify-center max-h-[70vh]">
              <img
                src={previewImage.url}
                alt={previewImage.title}
                className="max-h-[65vh] object-contain rounded-xl"
              />
            </div>

            <div className="p-4 bg-neutral-100 dark:bg-neutral-900 flex justify-end gap-2 border-t border-neutral-200 dark:border-neutral-800">
              <button
                onClick={() => handleDownload(previewImage.url, previewImage.title)}
                className="py-2 px-4 bg-emerald-600 hover:bg-emerald-700 text-white font-bold text-xs rounded-xl flex items-center gap-2 transition-all cursor-pointer shadow-sm"
              >
                <Download className="w-4 h-4" />
                <span>Download Photo</span>
              </button>
              <button
                onClick={() => setPreviewImage(null)}
                className="py-2 px-4 bg-neutral-200 dark:bg-neutral-800 hover:bg-neutral-300 text-neutral-700 dark:text-neutral-300 font-bold text-xs rounded-xl transition-colors cursor-pointer"
              >
                Close
              </button>
            </div>
          </div>
        </div>
      )}
    </div>
  );
};
