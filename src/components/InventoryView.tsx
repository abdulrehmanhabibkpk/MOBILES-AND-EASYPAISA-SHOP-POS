import React, { useState } from 'react';
import { 
  Plus, Search, ShieldAlert, Edit2, Trash2, Image, 
  Package, Sparkles, X, Check, LayoutGrid, List, Camera
} from 'lucide-react';
import { Product, ProductCategory, AppSettings } from '../types';
import { BarcodeScannerModal } from './BarcodeScannerModal';
import { useHardwareBarcodeScanner } from '../lib/useHardwareBarcodeScanner';

interface InventoryViewProps {
  products: Product[];
  onSaveProduct: (product: Omit<Product, 'id' | 'createdAt'>, id?: string) => void;
  onDeleteProduct: (id: string) => void;
  settings: AppSettings;
}

// Preset High Quality Image Options for Quick Selection
const PRESET_IMAGES = [
  { label: 'Mobile Phone', url: 'https://images.unsplash.com/photo-1511707171634-5f897ff02aa9?auto=format&fit=crop&w=400&q=80' },
  { label: 'Fast Charger', url: 'https://images.unsplash.com/photo-1583863788434-e58a36330cf0?auto=format&fit=crop&w=400&q=80' },
  { label: 'Airpods / Earbuds', url: 'https://images.unsplash.com/photo-1600294037681-c80b4cb5b434?auto=format&fit=crop&w=400&q=80' },
  { label: 'Glass Protector', url: 'https://images.unsplash.com/photo-1601784551446-20c9e07cdbdb?auto=format&fit=crop&w=400&q=80' },
  { label: 'Silicone Cover', url: 'https://images.unsplash.com/photo-1541877944-ac82a091518a?auto=format&fit=crop&w=400&q=80' },
  { label: 'Handsfree Earphones', url: 'https://images.unsplash.com/photo-1505740420928-5e560c06d30e?auto=format&fit=crop&w=400&q=80' },
  { label: 'USB Cable', url: 'https://images.unsplash.com/photo-1585338107529-13afc5f02586?auto=format&fit=crop&w=400&q=80' },
  { label: 'Mobile Battery', url: 'https://images.unsplash.com/photo-1619642751034-765dfdf7c58e?auto=format&fit=crop&w=400&q=80' },
];

export const InventoryView: React.FC<InventoryViewProps> = ({
  products,
  onSaveProduct,
  onDeleteProduct,
  settings,
}) => {
  const [searchTerm, setSearchTerm] = useState('');
  const [selectedCategory, setSelectedCategory] = useState<string>('ALL');
  const [viewMode, setViewMode] = useState<'list' | 'grid'>('list'); // DEFAULT TO LIST VIEW as requested
  
  // Modal state
  const [isModalOpen, setIsModalOpen] = useState(false);
  const [editingId, setEditingId] = useState<string | null>(null);

  // Form states
  const [name, setName] = useState('');
  const [category, setCategory] = useState<ProductCategory>('ACCESSORIES');
  const [purchasePrice, setPurchasePrice] = useState<number | ''>('');
  const [salePrice, setSalePrice] = useState<number | ''>('');
  const [stock, setStock] = useState<number | ''>(10);
  const [image, setImage] = useState<string>('');
  const [brandOrModel, setBrandOrModel] = useState('');
  const [imeiOrSerial, setImeiOrSerial] = useState('');
  const [sku, setSku] = useState('');
  const [isScannerOpen, setIsScannerOpen] = useState(false);

  const isLight = settings.theme === 'light';

  // Listen for automatic scans from any attached physical keyboard-emulating USB barcode scanner
  useHardwareBarcodeScanner((scannedCode) => {
    if (isModalOpen) {
      setSku(scannedCode);
    } else {
      setSearchTerm(scannedCode);
    }
  });

  const categories: { key: string; label: string; urdu: string }[] = [
    { key: 'ALL', label: 'All Items', urdu: 'تمام سامان' },
    { key: 'MOBILES', label: 'Mobiles', urdu: 'موبائل فون' },
    { key: 'CHARGERS', label: 'Chargers', urdu: 'چارجر' },
    { key: 'EARPHONES', label: 'Earphones / Airpods', urdu: 'ہینڈز فری' },
    { key: 'COVERS', label: 'Covers & Cases', urdu: 'کور اور کیس' },
    { key: 'PROTECTORS', label: 'Glass Protectors', urdu: 'گلاس پروٹیکٹر' },
    { key: 'CABLES', label: 'USB Cables', urdu: 'کیبلز' },
    { key: 'BATTERIES', label: 'Batteries', urdu: 'بیٹری' },
    { key: 'OTHER', label: 'Other', urdu: 'دیگر' },
  ];

  const handleOpenAddModal = () => {
    setEditingId(null);
    setName('');
    setCategory('ACCESSORIES');
    setPurchasePrice('');
    setSalePrice('');
    setStock(10);
    setImage(PRESET_IMAGES[0].url);
    setBrandOrModel('');
    setImeiOrSerial('');
    setSku('');
    setIsModalOpen(true);
  };

  const handleOpenEditModal = (p: Product) => {
    setEditingId(p.id);
    setName(p.name);
    setCategory(p.category);
    setPurchasePrice(p.purchasePrice);
    setSalePrice(p.salePrice);
    setStock(p.stock);
    setImage(p.image || PRESET_IMAGES[0].url);
    setBrandOrModel(p.brandOrModel || '');
    setImeiOrSerial(p.imeiOrSerial || '');
    setSku(p.sku || '');
    setIsModalOpen(true);
  };

  const handleImageUpload = (e: React.ChangeEvent<HTMLInputElement>) => {
    const file = e.target.files?.[0];
    if (file) {
      const reader = new FileReader();
      reader.onloadend = () => {
        setImage(reader.result as string);
      };
      reader.readAsDataURL(file);
    }
  };

  const handleSubmit = (e: React.FormEvent) => {
    e.preventDefault();
    if (!name.trim()) {
      alert('Tafseel / Item name daakhil karein');
      return;
    }

    const pur = Number(purchasePrice) || 0;
    const sal = Number(salePrice) || 0;
    const stk = Number(stock) || 0;

    onSaveProduct(
      {
        name: name.trim(),
        category,
        purchasePrice: pur,
        salePrice: sal,
        stock: stk,
        image: image || PRESET_IMAGES[0].url,
        brandOrModel: brandOrModel.trim(),
        imeiOrSerial: imeiOrSerial.trim(),
        sku: sku.trim(),
      },
      editingId || undefined
    );

    setIsModalOpen(false);
  };

  // Filter products
  const filteredProducts = products.filter((p) => {
    const matchesSearch =
      p.name.toLowerCase().includes(searchTerm.toLowerCase()) ||
      (p.brandOrModel && p.brandOrModel.toLowerCase().includes(searchTerm.toLowerCase())) ||
      (p.imeiOrSerial && p.imeiOrSerial.toLowerCase().includes(searchTerm.toLowerCase())) ||
      (p.sku && p.sku.toLowerCase().includes(searchTerm.toLowerCase()));

    const matchesCategory = selectedCategory === 'ALL' || p.category === selectedCategory;

    return matchesSearch && matchesCategory;
  });

  const lowStockCount = products.filter((p) => p.stock <= 3).length;

  return (
    <div className="space-y-5">
      {/* Top Banner Header */}
      <div className={`p-5 rounded-2xl border shadow-sm ${
        isLight ? 'bg-white border-slate-200' : 'bg-slate-900 border-slate-800'
      }`}>
        <div className="flex flex-col md:flex-row md:items-center justify-between gap-4">
          <div className="flex items-center gap-3">
            <div className="w-12 h-12 rounded-xl bg-blue-600 text-white flex items-center justify-center font-bold shadow-md shadow-blue-600/20">
              <Package className="w-6 h-6" />
            </div>
            <div>
              <h1 className={`text-lg sm:text-xl font-extrabold ${isLight ? 'text-slate-900' : 'text-white'}`}>
                موبائل اور ایکسیسریز انوینٹری (Stock Inventory List)
              </h1>
              <p className={`text-xs ${isLight ? 'text-slate-500' : 'text-slate-400'}`}>
                {settings.shopName} - Mobile Phones & Accessories Stock Table
              </p>
            </div>
          </div>

          <div className="flex items-center gap-2">
            {/* View Mode Switcher (List vs Grid) */}
            <div className={`flex items-center p-1 rounded-xl border ${isLight ? 'bg-slate-100 border-slate-200' : 'bg-slate-800 border-slate-700'}`}>
              <button
                onClick={() => setViewMode('list')}
                className={`flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-xs font-bold transition-all cursor-pointer ${
                  viewMode === 'list'
                    ? 'bg-blue-700 text-white shadow-sm'
                    : 'text-slate-500 hover:text-slate-800'
                }`}
                title="List View (فہرست)"
              >
                <List className="w-4 h-4" />
                <span>List View</span>
              </button>
              <button
                onClick={() => setViewMode('grid')}
                className={`flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-xs font-bold transition-all cursor-pointer ${
                  viewMode === 'grid'
                    ? 'bg-blue-700 text-white shadow-sm'
                    : 'text-slate-500 hover:text-slate-800'
                }`}
                title="Grid View"
              >
                <LayoutGrid className="w-4 h-4" />
                <span>Grid</span>
              </button>
            </div>

            {lowStockCount > 0 && (
              <div className="flex items-center gap-1.5 px-3 py-1.5 rounded-xl bg-amber-500/10 text-amber-600 border border-amber-500/30 text-xs font-semibold">
                <ShieldAlert className="w-4 h-4 shrink-0" />
                <span>{lowStockCount} Low Stock</span>
              </div>
            )}

            <button
              onClick={handleOpenAddModal}
              className="py-2.5 px-4 rounded-xl bg-blue-700 hover:bg-blue-800 text-white text-xs font-bold shadow-md shadow-blue-700/20 flex items-center gap-2 transition-all cursor-pointer"
            >
              <Plus className="w-4 h-4" />
              <span>Naya Saman Shamil Karein (+ Item)</span>
            </button>
          </div>
        </div>

        {/* Search & Categories Bar */}
        <div className="mt-4 pt-4 border-t border-slate-200/60 flex flex-col sm:flex-row gap-3">
          <div className="relative flex-1">
            <Search className="w-4 h-4 absolute left-3 top-3 text-slate-400" />
            <input
              type="text"
              placeholder="Saman search karein (Mobile, Charger, IMEI, Model)..."
              value={searchTerm}
              onChange={(e) => setSearchTerm(e.target.value)}
              className={`w-full pl-9 pr-4 py-2 rounded-xl text-xs border focus:outline-none focus:ring-2 focus:ring-blue-500 ${
                isLight ? 'bg-slate-50 border-slate-200 text-slate-800' : 'bg-slate-800 border-slate-700 text-slate-100'
              }`}
            />
          </div>

          {/* Category Chips Scroll */}
          <div className="flex gap-1.5 overflow-x-auto pb-1 no-scrollbar">
            {categories.map((cat) => (
              <button
                key={cat.key}
                onClick={() => setSelectedCategory(cat.key)}
                className={`px-3 py-1.5 rounded-xl text-xs font-semibold shrink-0 transition-colors ${
                  selectedCategory === cat.key
                    ? 'bg-blue-700 text-white shadow-sm'
                    : isLight
                    ? 'bg-slate-100 hover:bg-slate-200 text-slate-700'
                    : 'bg-slate-800 hover:bg-slate-700 text-slate-300'
                }`}
              >
                {cat.label} ({cat.urdu})
              </button>
            ))}
          </div>
        </div>
      </div>

      {/* PRODUCTS DISPLAY SECTION */}
      {filteredProducts.length === 0 ? (
        <div className={`p-10 text-center rounded-2xl border ${isLight ? 'bg-white border-slate-200' : 'bg-slate-900 border-slate-800'}`}>
          <Package className="w-12 h-12 mx-auto text-slate-400 mb-2 opacity-50" />
          <h3 className={`text-base font-bold ${isLight ? 'text-slate-700' : 'text-slate-300'}`}>Koi item nahi mila</h3>
          <p className="text-xs text-slate-400 mt-1">Naya mobile ya accessories item add karne ke liye upar wale button par click karein.</p>
        </div>
      ) : viewMode === 'list' ? (
        /* PRODUCT LIST TABLE VIEW (DEFAULT LIST VIEW) */
        <div className={`rounded-2xl border overflow-hidden shadow-lg ${isLight ? 'bg-white border-slate-200' : 'bg-slate-900 border-slate-800'}`}>
          <div className="overflow-x-auto">
            <table className="w-full text-left text-xs">
              <thead className={`${isLight ? 'bg-slate-100 text-slate-700' : 'bg-slate-950 text-slate-400'} uppercase font-extrabold text-[11px] border-b ${isLight ? 'border-slate-200' : 'border-slate-800'}`}>
                <tr>
                  <th className="py-3 px-4">Saman (Item)</th>
                  <th className="py-3 px-4">Category</th>
                  <th className="py-3 px-4">Brand / IMEI / Model</th>
                  <th className="py-3 px-4 text-center">Stock (تعداد)</th>
                  <th className="py-3 px-4 text-right">Khareed (Purchase)</th>
                  <th className="py-3 px-4 text-right">Farokht (Sale)</th>
                  <th className="py-3 px-4 text-right">Expected Profit</th>
                  <th className="py-3 px-4 text-center">Action</th>
                </tr>
              </thead>
              <tbody className={`divide-y ${isLight ? 'divide-slate-200' : 'divide-slate-800'} font-medium`}>
                {filteredProducts.map((prod) => {
                  const profitMargin = prod.salePrice - prod.purchasePrice;
                  const isLowStock = prod.stock <= 3;

                  return (
                    <tr key={prod.id} className={`${isLight ? 'hover:bg-blue-50/40' : 'hover:bg-slate-800/50'} transition-colors`}>
                      {/* Product Name & Photo */}
                      <td className="py-3 px-4">
                        <div className="flex items-center gap-3">
                          <img
                            src={prod.image || PRESET_IMAGES[0].url}
                            alt={prod.name}
                            className="w-10 h-10 rounded-xl object-cover shrink-0 border border-slate-200"
                            referrerPolicy="no-referrer"
                          />
                          <div>
                            <p className={`font-extrabold text-xs ${isLight ? 'text-slate-900' : 'text-white'}`}>
                              {prod.name}
                            </p>
                            <p className="text-[10px] text-slate-400">ID: {prod.id.slice(-6)}</p>
                          </div>
                        </div>
                      </td>

                      {/* Category */}
                      <td className="py-3 px-4">
                        <span className="px-2.5 py-1 rounded-full bg-blue-100 text-blue-800 font-bold text-[10px]">
                          {prod.category}
                        </span>
                      </td>

                      {/* Brand & IMEI */}
                      <td className="py-3 px-4">
                        <div className="text-xs">
                          {prod.brandOrModel ? (
                            <p className="font-semibold text-slate-700">{prod.brandOrModel}</p>
                          ) : (
                            <p className="text-slate-400">-</p>
                          )}
                          {prod.imeiOrSerial && (
                            <p className="font-mono text-[10px] text-blue-600 bg-blue-50 px-1.5 py-0.5 rounded inline-block mt-0.5">
                              IMEI: {prod.imeiOrSerial}
                            </p>
                          )}
                        </div>
                      </td>

                      {/* Stock Level */}
                      <td className="py-3 px-4 text-center">
                        <span className={`px-2.5 py-1 rounded-full text-xs font-bold ${
                          isLowStock
                            ? 'bg-rose-100 text-rose-800 border border-rose-300 animate-pulse'
                            : 'bg-emerald-100 text-emerald-800 border border-emerald-300'
                        }`}>
                          {prod.stock} Pcs
                        </span>
                      </td>

                      {/* Purchase Price */}
                      <td className="py-3 px-4 text-right font-mono text-slate-600">
                        Rs. {prod.purchasePrice.toLocaleString()}
                      </td>

                      {/* Sale Price */}
                      <td className="py-3 px-4 text-right font-mono font-bold text-blue-700">
                        Rs. {prod.salePrice.toLocaleString()}
                      </td>

                      {/* Profit */}
                      <td className="py-3 px-4 text-right font-mono font-bold text-emerald-600">
                        + Rs. {profitMargin.toLocaleString()}
                      </td>

                      {/* Action */}
                      <td className="py-3 px-4 text-center">
                        <div className="flex items-center justify-center gap-1.5">
                          <button
                            onClick={() => handleOpenEditModal(prod)}
                            className="p-1.5 rounded-lg bg-blue-50 hover:bg-blue-100 text-blue-700 border border-blue-200 transition-colors cursor-pointer"
                            title="Edit Item"
                          >
                            <Edit2 className="w-3.5 h-3.5" />
                          </button>
                          <button
                            onClick={() => {
                              if (confirm(`Kya aap ${prod.name} ko delete karna chahte hain?`)) {
                                onDeleteProduct(prod.id);
                              }
                            }}
                            className="p-1.5 rounded-lg bg-rose-50 hover:bg-rose-100 text-rose-600 border border-rose-200 transition-colors cursor-pointer"
                            title="Delete Item"
                          >
                            <Trash2 className="w-3.5 h-3.5" />
                          </button>
                        </div>
                      </td>

                    </tr>
                  );
                })}
              </tbody>
            </table>
          </div>
        </div>
      ) : (
        /* GRID VIEW OPTION */
        <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-4">
          {filteredProducts.map((prod) => {
            const profitMargin = prod.salePrice - prod.purchasePrice;
            const isLowStock = prod.stock <= 3;

            return (
              <div
                key={prod.id}
                className={`rounded-2xl border transition-all duration-200 hover:shadow-lg flex flex-col overflow-hidden ${
                  isLight ? 'bg-white border-slate-200' : 'bg-slate-900 border-slate-800'
                }`}
              >
                {/* Product Image & Stock Badge */}
                <div className="relative h-44 bg-slate-100 overflow-hidden group">
                  <img
                    src={prod.image || PRESET_IMAGES[0].url}
                    alt={prod.name}
                    className="w-full h-full object-cover group-hover:scale-105 transition-transform duration-300"
                    referrerPolicy="no-referrer"
                  />
                  <div className="absolute top-2 left-2">
                    <span className={`px-2.5 py-1 rounded-full text-[11px] font-bold shadow-md ${
                      isLowStock
                        ? 'bg-rose-500 text-white animate-pulse'
                        : 'bg-emerald-600 text-white'
                    }`}>
                      Stock: {prod.stock} Pcs
                    </span>
                  </div>

                  <div className="absolute top-2 right-2">
                    <span className="px-2 py-0.5 rounded-md bg-slate-900/80 backdrop-blur-md text-white text-[10px] font-medium border border-white/20">
                      {prod.category}
                    </span>
                  </div>
                </div>

                {/* Content */}
                <div className="p-4 flex-1 flex flex-col justify-between space-y-3">
                  <div>
                    <h3 className={`font-bold text-sm leading-snug ${isLight ? 'text-slate-900' : 'text-white'}`}>
                      {prod.name}
                    </h3>
                    {prod.brandOrModel && (
                      <p className="text-[11px] text-slate-500 mt-0.5">Model: {prod.brandOrModel}</p>
                    )}
                    {prod.imeiOrSerial && (
                      <p className="text-[10px] font-mono text-blue-600 bg-blue-50 px-1.5 py-0.5 rounded inline-block mt-1">
                        IMEI: {prod.imeiOrSerial}
                      </p>
                    )}
                  </div>

                  {/* Price Breakdown */}
                  <div className={`p-2.5 rounded-xl border space-y-1.5 text-xs ${
                    isLight ? 'bg-slate-50 border-slate-100' : 'bg-slate-800/60 border-slate-800'
                  }`}>
                    <div className="flex justify-between text-slate-500">
                      <span>Khareed (Purchase):</span>
                      <span className="font-semibold text-slate-700">Rs. {prod.purchasePrice.toLocaleString()}</span>
                    </div>
                    <div className="flex justify-between font-bold text-slate-900">
                      <span>Farokht (Sale):</span>
                      <span className="text-blue-700 text-sm">Rs. {prod.salePrice.toLocaleString()}</span>
                    </div>
                    <div className="flex justify-between text-[11px] text-emerald-600 pt-1 border-t border-slate-200/60">
                      <span>Manafa (Profit):</span>
                      <span className="font-bold">+ Rs. {profitMargin.toLocaleString()}</span>
                    </div>
                  </div>

                  {/* Actions */}
                  <div className="flex items-center gap-2 pt-1 border-t border-slate-100">
                    <button
                      onClick={() => handleOpenEditModal(prod)}
                      className={`flex-1 py-1.5 px-2 rounded-lg text-xs font-semibold border flex items-center justify-center gap-1 transition-colors ${
                        isLight
                          ? 'border-slate-200 text-slate-700 hover:bg-slate-100'
                          : 'border-slate-700 text-slate-300 hover:bg-slate-800'
                      }`}
                    >
                      <Edit2 className="w-3.5 h-3.5" />
                      <span>Edit</span>
                    </button>
                    <button
                      onClick={() => {
                        if (confirm(`Kya aap ${prod.name} ko delete karna chahte hain?`)) {
                          onDeleteProduct(prod.id);
                        }
                      }}
                      className="p-1.5 rounded-lg text-rose-500 hover:bg-rose-50 border border-transparent hover:border-rose-200 transition-colors"
                      title="Delete Product"
                    >
                      <Trash2 className="w-4 h-4" />
                    </button>
                  </div>

                </div>
              </div>
            );
          })}
        </div>
      )}

      {/* Add/Edit Product Modal */}
      {isModalOpen && (
        <div className="fixed inset-0 z-50 flex items-center justify-center bg-slate-950/80 backdrop-blur-sm p-2 sm:p-4 overflow-y-auto">
          <div className={`w-full max-w-lg rounded-2xl shadow-2xl border overflow-hidden my-auto max-h-[92vh] flex flex-col ${
            isLight ? 'bg-white border-slate-200 text-slate-800' : 'bg-slate-900 border-slate-700 text-slate-100'
          }`}>
            {/* Header */}
            <div className="bg-gradient-to-r from-blue-700 to-indigo-800 px-4 sm:px-6 py-4 text-white flex items-center justify-between shrink-0">
              <div className="flex items-center gap-2">
                <Package className="w-5 h-5 text-blue-200" />
                <h3 className="font-bold text-base">
                  {editingId ? 'Product Edit Karein' : 'Naya Stock Entry (New Mobile/Accessory)'}
                </h3>
              </div>
              <button
                onClick={() => setIsModalOpen(false)}
                className="w-7 h-7 rounded-full bg-white/10 hover:bg-white/20 text-white flex items-center justify-center transition-colors"
              >
                <X className="w-4 h-4" />
              </button>
            </div>

            {/* Form */}
            <form onSubmit={handleSubmit} className="p-4 sm:p-6 space-y-4 overflow-y-auto">
              
              <div>
                <label className="block text-xs font-semibold text-slate-700 mb-1">
                  Item Name / Tafseel: *
                </label>
                <input
                  type="text"
                  required
                  placeholder="e.g. Vivo Y21, Samsung 25W Charger, Airpods Pro"
                  value={name}
                  onChange={(e) => setName(e.target.value)}
                  className={`w-full p-2.5 rounded-xl text-xs border focus:ring-2 focus:ring-blue-500 ${
                    isLight ? 'bg-slate-50 border-slate-200' : 'bg-slate-800 border-slate-700 text-white'
                  }`}
                />
              </div>

              <div className="grid grid-cols-1 sm:grid-cols-2 gap-3">
                <div>
                  <label className="block text-xs font-semibold text-slate-700 mb-1">
                    Category:
                  </label>
                  <select
                    value={category}
                    onChange={(e) => setCategory(e.target.value as ProductCategory)}
                    className={`w-full p-2.5 rounded-xl text-xs border focus:ring-2 focus:ring-blue-500 ${
                      isLight ? 'bg-slate-50 border-slate-200' : 'bg-slate-800 border-slate-700 text-white'
                    }`}
                  >
                    <option value="MOBILES">Mobile Phones (موبائل)</option>
                    <option value="CHARGERS">Chargers (چارجر)</option>
                    <option value="EARPHONES">Earphones / Airpods (ہینڈز فری)</option>
                    <option value="COVERS">Covers & Cases (کور)</option>
                    <option value="PROTECTORS">Glass Protectors (گلاس)</option>
                    <option value="CABLES">USB Cables (کیبلز)</option>
                    <option value="BATTERIES">Batteries (بیٹری)</option>
                    <option value="ACCESSORIES">General Accessories</option>
                    <option value="OTHER">Other</option>
                  </select>
                </div>

                <div>
                  <label className="block text-xs font-semibold text-slate-700 mb-1">
                    Stock Quantity (Tadad): *
                  </label>
                  <input
                    type="number"
                    required
                    min="1"
                    placeholder="10"
                    value={stock}
                    onChange={(e) => setStock(e.target.value === '' ? '' : Number(e.target.value))}
                    className={`w-full p-2.5 rounded-xl text-xs border font-semibold focus:ring-2 focus:ring-blue-500 ${
                      isLight ? 'bg-slate-50 border-slate-200' : 'bg-slate-800 border-slate-700 text-white'
                    }`}
                  />
                </div>
              </div>

              {/* Prices Row */}
              <div className="grid grid-cols-1 sm:grid-cols-2 gap-3 p-3 bg-blue-50/60 rounded-xl border border-blue-100">
                <div>
                  <label className="block text-xs font-semibold text-slate-700 mb-1">
                    Khareed Qeemat (Cost): Rs. *
                  </label>
                  <input
                    type="number"
                    required
                    min="0"
                    placeholder="e.g. 500"
                    value={purchasePrice}
                    onChange={(e) => setPurchasePrice(e.target.value === '' ? '' : Number(e.target.value))}
                    className="w-full p-2.5 bg-white border border-slate-200 rounded-xl text-xs font-bold text-slate-800"
                  />
                </div>

                <div>
                  <label className="block text-xs font-semibold text-slate-700 mb-1">
                    Farokht Qeemat (Sale): Rs. *
                  </label>
                  <input
                    type="number"
                    required
                    min="0"
                    placeholder="e.g. 850"
                    value={salePrice}
                    onChange={(e) => setSalePrice(e.target.value === '' ? '' : Number(e.target.value))}
                    className="w-full p-2.5 bg-white border border-slate-200 rounded-xl text-xs font-bold text-blue-700"
                  />
                </div>

                {Number(salePrice) > 0 && Number(purchasePrice) > 0 && (
                  <div className="col-span-full pt-2 border-t border-blue-200 text-xs flex justify-between font-bold text-emerald-700">
                    <span>Expected Profit per item:</span>
                    <span>Rs. {(Number(salePrice) - Number(purchasePrice)).toLocaleString()}</span>
                  </div>
                )}
              </div>

              {/* Model & IMEI */}
              <div className="grid grid-cols-1 sm:grid-cols-2 gap-3">
                <div>
                  <label className="block text-xs font-semibold text-slate-700 mb-1">
                    Brand / Model Name (Optional):
                  </label>
                  <input
                    type="text"
                    placeholder="e.g. Samsung / Infinix"
                    value={brandOrModel}
                    onChange={(e) => setBrandOrModel(e.target.value)}
                    className={`w-full p-2 rounded-xl text-xs border ${
                      isLight ? 'bg-slate-50 border-slate-200' : 'bg-slate-800 border-slate-700 text-white'
                    }`}
                  />
                </div>
                <div>
                  <label className="block text-xs font-semibold text-slate-700 mb-1">
                    IMEI / Serial No (Optional):
                  </label>
                  <input
                    type="text"
                    placeholder="e.g. 35918290182901"
                    value={imeiOrSerial}
                    onChange={(e) => setImeiOrSerial(e.target.value)}
                    className={`w-full p-2 rounded-xl text-xs border font-mono ${
                      isLight ? 'bg-slate-50 border-slate-200' : 'bg-slate-800 border-slate-700 text-white'
                    }`}
                  />
                </div>
              </div>

              {/* SKU & Barcode Scanner Field */}
              <div>
                <label className="block text-xs font-semibold text-slate-700 mb-1">
                  SKU / Barcode Number (Optional):
                </label>
                <div className="flex gap-2">
                  <input
                    type="text"
                    placeholder="e.g. 880609012345"
                    value={sku}
                    onChange={(e) => setSku(e.target.value)}
                    className={`flex-1 p-2.5 rounded-xl text-xs border font-mono ${
                      isLight ? 'bg-slate-50 border-slate-200' : 'bg-slate-800 border-slate-700 text-white'
                    }`}
                  />
                  <button
                    type="button"
                    onClick={() => setIsScannerOpen(true)}
                    className="px-4 bg-blue-600 hover:bg-blue-700 text-white text-xs font-bold rounded-xl flex items-center gap-1.5 transition-all shadow-sm cursor-pointer shrink-0"
                    title="Scan Barcode via Mobile Camera"
                  >
                    <Camera className="w-3.5 h-3.5" />
                    <span>Scan</span>
                  </button>
                </div>
              </div>

              {/* Photo Selector */}
              <div>
                <label className="block text-xs font-semibold text-slate-700 mb-1">
                  Product Image / Photo Selection:
                </label>
                
                {/* Image Preview */}
                <div className="flex items-center gap-3 mb-2">
                  <div className="w-16 h-16 rounded-xl bg-slate-100 border border-slate-200 overflow-hidden shrink-0">
                    <img src={image || PRESET_IMAGES[0].url} alt="Preview" className="w-full h-full object-cover" />
                  </div>
                  <div className="text-xs space-y-1">
                    <p className="font-semibold text-slate-700">Quick Presets / Custom Upload:</p>
                    <label className="cursor-pointer inline-flex items-center gap-1.5 px-3 py-1 rounded-lg bg-blue-50 text-blue-700 hover:bg-blue-100 border border-blue-200 font-semibold text-xs">
                      <Image className="w-3.5 h-3.5" />
                      <span>Upload Photo</span>
                      <input type="file" accept="image/*" className="hidden" onChange={handleImageUpload} />
                    </label>
                  </div>
                </div>

                {/* Preset Thumbnails */}
                <div className="grid grid-cols-4 gap-2">
                  {PRESET_IMAGES.map((preset, idx) => (
                    <button
                      key={idx}
                      type="button"
                      onClick={() => setImage(preset.url)}
                      className={`relative h-12 rounded-xl border overflow-hidden transition-all ${
                        image === preset.url ? 'ring-2 ring-blue-600 border-transparent scale-95' : 'opacity-70 hover:opacity-100'
                      }`}
                    >
                      <img src={preset.url} alt={preset.label} className="w-full h-full object-cover" />
                      {image === preset.url && (
                        <div className="absolute inset-0 bg-blue-600/30 flex items-center justify-center text-white">
                          <Check className="w-4 h-4" />
                        </div>
                      )}
                    </button>
                  ))}
                </div>
              </div>

              {/* Submit */}
              <button
                type="submit"
                className="w-full py-3 rounded-xl bg-blue-700 hover:bg-blue-800 text-white font-bold text-xs shadow-lg shadow-blue-700/20 transition-all flex items-center justify-center gap-2 mt-2 cursor-pointer"
              >
                <Sparkles className="w-4 h-4" />
                <span>{editingId ? 'Changes Save Karein' : 'Item Save Karein'}</span>
              </button>

            </form>
          </div>
        </div>
      )}

      <BarcodeScannerModal
        isOpen={isScannerOpen}
        onClose={() => setIsScannerOpen(false)}
        onScanSuccess={(code) => setSku(code)}
      />

    </div>
  );
};
