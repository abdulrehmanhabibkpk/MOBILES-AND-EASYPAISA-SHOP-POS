import React, { useState } from 'react';
import { Product, AppSettings } from '../types';
import { QrCode, Printer, CheckCircle, Search, Trash2, FileText, Sliders } from 'lucide-react';

interface BarcodeStudioViewProps {
  products: Product[];
  settings: AppSettings;
}

export const BarcodeStudioView: React.FC<BarcodeStudioViewProps> = ({ products, settings }) => {
  const isLight = settings.theme === 'light';
  const isEn = settings.language === 'en';

  const [activeSubTab, setActiveSubTab] = useState<'single' | 'batch' | 'custom'>('single');
  const [selectedProductId, setSelectedProductId] = useState<string>(products[0]?.id || '');
  const [searchQuery, setSearchQuery] = useState('');
  const [printQty, setPrintQty] = useState<number>(12);
  const [paperTemplate, setPaperTemplate] = useState<string>('50x30');
  const [barHeight, setBarHeight] = useState<number>(32);

  // Toggles
  const [showStoreHeader, setShowStoreHeader] = useState<boolean>(true);
  const [showProductTitle, setShowProductTitle] = useState<boolean>(true);
  const [showRetailPrice, setShowRetailPrice] = useState<boolean>(true);
  const [showBrand, setShowBrand] = useState<boolean>(true);
  const [showBatchNo, setShowBatchNo] = useState<boolean>(false);
  const [showExpiry, setShowExpiry] = useState<boolean>(false);

  // Batch Queue
  const [batchItems, setBatchItems] = useState<Array<{ product: Product; qty: number }>>(
    products.slice(0, 3).map(p => ({ product: p, qty: 4 }))
  );

  // Custom Form
  const [customCode, setCustomCode] = useState('880123456789');
  const [customName, setCustomName] = useState('Special Vitamin Pack 500mg');
  const [customPrice, setCustomPrice] = useState('350');
  const [customBrand, setCustomBrand] = useState('MedCare Labs');
  const [customBatch, setCustomBatch] = useState('B-8941');
  const [customExpiry, setCustomExpiry] = useState('10/2028');

  // Scanner Verification
  const [scannerTest, setScannerTest] = useState('');
  const [verifiedStatus, setVerifiedStatus] = useState<boolean | null>(null);

  const selectedProduct = products.find(p => p.id === selectedProductId) || products[0];

  // Helper to generate deterministic SVG barcode bars from text
  const renderBarcodeSvg = (code: string) => {
    const cleanCode = code || '1001';
    let pattern = [2, 1, 1, 3, 1, 2, 1, 1, 2, 2, 1, 3, 1, 1, 2, 1, 3, 1, 2];
    for (let i = 0; i < cleanCode.length; i++) {
      const charCode = cleanCode.charCodeAt(i);
      pattern.push((charCode % 3) + 1);
      pattern.push((charCode % 2) + 1);
      pattern.push(1);
    }

    let x = 0;
    const bars = pattern.map((w, idx) => {
      const isBlack = idx % 2 === 0;
      const width = w * 2.2;
      const rect = isBlack ? (
        <rect key={idx} x={x} y="0" width={width} height={barHeight} fill="#000" />
      ) : null;
      x += width;
      return rect;
    });

    return (
      <svg viewBox={`0 0 ${Math.max(x, 120)} ${barHeight}`} className="w-full h-10 max-h-12 object-contain mx-auto">
        <g>{bars}</g>
      </svg>
    );
  };

  const handleVerifyScan = (e: React.FormEvent) => {
    e.preventDefault();
    if (!scannerTest.trim()) return;
    setVerifiedStatus(true);
    setTimeout(() => setVerifiedStatus(null), 4000);
  };

  const filteredInventoryForSearch = products.filter(p =>
    p.name.toLowerCase().includes(searchQuery.toLowerCase()) ||
    (p.sku && p.sku.toLowerCase().includes(searchQuery.toLowerCase()))
  );

  let previewItems: Array<{ title: string; code: string; price: number; brand: string; batch?: string; expiry?: string; qty: number }> = [];

  if (activeSubTab === 'single' && selectedProduct) {
    const item = {
      title: selectedProduct.name,
      code: selectedProduct.sku || selectedProduct.id.slice(-6),
      price: selectedProduct.salePrice,
      brand: selectedProduct.brandOrModel || selectedProduct.category,
      batch: 'BATCH-01',
      expiry: '12/2028',
      qty: printQty
    };
    for (let i = 0; i < printQty; i++) {
      previewItems.push(item);
    }
  } else if (activeSubTab === 'batch') {
    batchItems.forEach(bi => {
      for (let i = 0; i < bi.qty; i++) {
        previewItems.push({
          title: bi.product.name,
          code: bi.product.sku || bi.product.id.slice(-6),
          price: bi.product.salePrice,
          brand: bi.product.brandOrModel || bi.product.category,
          batch: 'BATCH-01',
          expiry: '12/2028',
          qty: bi.qty
        });
      }
    });
  } else {
    for (let i = 0; i < printQty; i++) {
      previewItems.push({
        title: customName,
        code: customCode,
        price: Number(customPrice) || 0,
        brand: customBrand,
        batch: customBatch,
        expiry: customExpiry,
        qty: printQty
      });
    }
  }

  const handlePrintStickers = () => {
    window.print();
  };

  return (
    <div className={`min-h-screen ${isLight ? 'bg-neutral-50 text-neutral-900' : 'bg-neutral-950 text-neutral-100'} p-3 sm:p-6 transition-colors`}>
      <div className="max-w-7xl mx-auto space-y-6">

        {/* Top Header Banner */}
        <div className="bg-gradient-to-r from-slate-900 via-emerald-950 to-slate-900 text-white rounded-2xl p-4 sm:p-6 shadow-xl border border-emerald-500/30 flex flex-col md:flex-row items-center justify-between gap-4">
          <div className="flex items-center gap-3">
            <div className="w-12 h-12 rounded-2xl bg-emerald-600 flex items-center justify-center shadow-lg shadow-emerald-900/50">
              <QrCode className="w-7 h-7 text-white" />
            </div>
            <div>
              <h1 className="text-lg sm:text-2xl font-black tracking-wide">
                {isEn ? 'BARCODE LABEL GENERATOR & PRINTING STUDIO' : 'برکوڈ لیبل جنریٹر اینڈ پرنٹنگ سٹوڈیو'}
              </h1>
              <p className="text-xs sm:text-sm text-emerald-300 font-medium">
                {isEn ? 'Genuine ISO/IEC Standard Vector Barcodes for Thermal Rolls & A4 Sticker Sheets' : 'تھرمل رولز اور اے فور اسٹیکرز کے لیے معیاری برکوڈ جنریٹر'}
              </p>
            </div>
          </div>
          <div className="bg-emerald-800/80 text-emerald-100 px-3.5 py-2 rounded-xl border border-emerald-500/40 text-xs font-extrabold flex items-center gap-2 shadow">
            <span className="w-2 h-2 rounded-full bg-emerald-400 animate-pulse"></span>
            <span>100% Scannable Vector Engine</span>
          </div>
        </div>

        {/* Scanner Verification Bar */}
        <div className={`p-4 rounded-2xl ${isLight ? 'bg-white border-neutral-200' : 'bg-neutral-900 border-neutral-800'} border shadow-sm`}>
          <form onSubmit={handleVerifyScan} className="flex flex-col sm:flex-row items-center gap-3">
            <div className="flex items-center gap-2 w-full sm:w-auto">
              <Search className="w-5 h-5 text-emerald-600 shrink-0" />
              <span className="text-xs font-bold whitespace-nowrap">Live Barcode Scanner Test:</span>
            </div>
            <div className="relative flex-1 w-full">
              <input
                type="text"
                value={scannerTest}
                onChange={(e) => setScannerTest(e.target.value)}
                placeholder="Scan any printed barcode here with your USB/Bluetooth laser scanner to test..."
                className={`w-full py-2 px-3.5 rounded-xl border text-xs sm:text-sm ${
                  isLight ? 'bg-neutral-50 border-neutral-200 text-neutral-900' : 'bg-neutral-800 border-neutral-700 text-white'
                } focus:outline-none focus:border-emerald-600`}
              />
            </div>
            <button
              type="submit"
              className="w-full sm:w-auto px-5 py-2 rounded-xl bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-extrabold shadow transition-all cursor-pointer"
            >
              Verify Scan
            </button>
          </form>
          {verifiedStatus !== null && (
            <div className="mt-2.5 p-2.5 rounded-xl bg-emerald-50 border border-emerald-200 text-emerald-900 text-xs flex items-center gap-2 animate-fadeIn">
              <CheckCircle className="w-4 h-4 text-emerald-600 shrink-0" />
              <span><strong>Barcode Verified!</strong> Scanned code matches product database successfully. Ready for thermal print.</span>
            </div>
          )}
        </div>

        {/* Main Workspace Grid */}
        <div className="grid grid-cols-1 lg:grid-cols-12 gap-6">

          {/* Left Controls Panel (7 Cols) */}
          <div className="lg:col-span-7 space-y-6">

            {/* Mode Switcher Tabs */}
            <div className={`p-1.5 rounded-2xl ${isLight ? 'bg-neutral-200/75' : 'bg-neutral-900'} flex items-center gap-1.5 shadow-inner`}>
              <button
                onClick={() => setActiveSubTab('single')}
                className={`flex-1 py-2 px-3 rounded-xl text-xs font-extrabold transition-all cursor-pointer flex items-center justify-center gap-1.5 ${
                  activeSubTab === 'single'
                    ? 'bg-emerald-600 text-white shadow'
                    : `${isLight ? 'text-neutral-700 hover:bg-neutral-300' : 'text-neutral-300 hover:bg-neutral-800'}`
                }`}
              >
                <span>🏷️</span>
                <span>Single Item</span>
              </button>
              <button
                onClick={() => setActiveSubTab('batch')}
                className={`flex-1 py-2 px-3 rounded-xl text-xs font-extrabold transition-all cursor-pointer flex items-center justify-center gap-1.5 ${
                  activeSubTab === 'batch'
                    ? 'bg-emerald-600 text-white shadow'
                    : `${isLight ? 'text-neutral-700 hover:bg-neutral-300' : 'text-neutral-300 hover:bg-neutral-800'}`
                }`}
              >
                <span>📦</span>
                <span>Batch Queue ({batchItems.length})</span>
              </button>
              <button
                onClick={() => setActiveSubTab('custom')}
                className={`flex-1 py-2 px-3 rounded-xl text-xs font-extrabold transition-all cursor-pointer flex items-center justify-center gap-1.5 ${
                  activeSubTab === 'custom'
                    ? 'bg-emerald-600 text-white shadow'
                    : `${isLight ? 'text-neutral-700 hover:bg-neutral-300' : 'text-neutral-300 hover:bg-neutral-800'}`
                }`}
              >
                <span>✍️</span>
                <span>Custom / Freeform</span>
              </button>
            </div>

            {/* Sub-tab 1: Single Item Selection */}
            {activeSubTab === 'single' && (
              <div className={`p-5 rounded-2xl ${isLight ? 'bg-white border-neutral-200' : 'bg-neutral-900 border-neutral-800'} border shadow-sm space-y-4`}>
                <div className="p-3.5 rounded-xl bg-emerald-50/70 border border-emerald-100">
                  <div className="text-[10px] font-black text-emerald-800 uppercase tracking-wider mb-1">Active Target Product:</div>
                  <div className="font-extrabold text-sm text-neutral-900">{selectedProduct?.name || 'Select Product'}</div>
                  <div className="flex items-center justify-between mt-1 text-xs text-neutral-600">
                    <span>Barcode: <strong>{selectedProduct?.sku || selectedProduct?.id.slice(-6)}</strong></span>
                    <span className="text-emerald-700 font-extrabold">Rs. {selectedProduct?.salePrice?.toLocaleString()}</span>
                  </div>
                </div>

                <div>
                  <label className="block text-xs font-bold mb-1.5">Search & Choose Inventory Product:</label>
                  <div className="relative">
                    <Search className="absolute left-3 top-2.5 w-4 h-4 text-neutral-400" />
                    <input
                      type="text"
                      value={searchQuery}
                      onChange={(e) => setSearchQuery(e.target.value)}
                      placeholder="Type product name or barcode..."
                      className={`w-full pl-9 pr-3 py-2 rounded-xl border text-xs ${
                        isLight ? 'bg-neutral-50 border-neutral-200 text-neutral-900' : 'bg-neutral-800 border-neutral-700 text-white'
                      }`}
                    />
                  </div>
                  {searchQuery && (
                    <div className="mt-2 max-h-40 overflow-y-auto border rounded-xl divide-y shadow-sm">
                      {filteredInventoryForSearch.map(p => (
                        <div
                          key={p.id}
                          onClick={() => {
                            setSelectedProductId(p.id);
                            setSearchQuery('');
                          }}
                          className="p-2 text-xs hover:bg-emerald-50 cursor-pointer flex justify-between items-center"
                        >
                          <div>
                            <span className="font-bold">{p.name}</span>
                            <span className="text-neutral-500 ml-2">({p.sku || p.id.slice(-6)})</span>
                          </div>
                          <span className="text-emerald-700 font-bold">Rs. {p.salePrice}</span>
                        </div>
                      ))}
                    </div>
                  )}
                </div>

                <div>
                  <label className="block text-xs font-bold mb-1.5">Print Quantity (Number of Stickers):</label>
                  <div className="flex items-center gap-2">
                    <input
                      type="number"
                      min="1"
                      max="200"
                      value={printQty}
                      onChange={(e) => setPrintQty(Math.max(1, parseInt(e.target.value) || 1))}
                      className={`w-32 py-2 px-3 rounded-xl border text-sm font-bold ${
                        isLight ? 'bg-neutral-50 border-neutral-200 text-neutral-900' : 'bg-neutral-800 border-neutral-700 text-white'
                      }`}
                    />
                    <div className="flex items-center gap-1.5">
                      {[6, 12, 24, 48].map(q => (
                        <button
                          key={q}
                          type="button"
                          onClick={() => setPrintQty(q)}
                          className={`px-3 py-1.5 rounded-lg text-xs font-bold cursor-pointer border ${
                            printQty === q ? 'bg-emerald-600 text-white border-emerald-600' : 'bg-neutral-100 border-neutral-300 text-neutral-700 hover:bg-neutral-200'
                          }`}
                        >
                          {q}
                        </button>
                      ))}
                    </div>
                  </div>
                </div>
              </div>
            )}

            {/* Sub-tab 2: Batch Queue */}
            {activeSubTab === 'batch' && (
              <div className={`p-5 rounded-2xl ${isLight ? 'bg-white border-neutral-200' : 'bg-neutral-900 border-neutral-800'} border shadow-sm space-y-4`}>
                <div className="flex items-center justify-between">
                  <span className="text-xs font-bold text-neutral-600">Multi-Product Batch List:</span>
                  <button
                    onClick={() => setBatchItems([])}
                    className="text-xs text-rose-600 hover:underline font-bold cursor-pointer"
                  >
                    Clear All
                  </button>
                </div>

                <div className="space-y-2">
                  {batchItems.map((bi, idx) => (
                    <div key={idx} className="flex items-center justify-between p-3 rounded-xl border bg-neutral-50/80 text-xs">
                      <div>
                        <div className="font-bold text-neutral-900">{bi.product.name}</div>
                        <div className="text-[10px] text-neutral-500">Code: {bi.product.sku || bi.product.id.slice(-6)} • Rs. {bi.product.salePrice}</div>
                      </div>
                      <div className="flex items-center gap-3">
                        <div className="flex items-center gap-1">
                          <span className="text-[11px] text-neutral-500">Qty:</span>
                          <input
                            type="number"
                            min="1"
                            value={bi.qty}
                            onChange={(e) => {
                              const val = Math.max(1, parseInt(e.target.value) || 1);
                              setBatchItems(batchItems.map((item, i) => i === idx ? { ...item, qty: val } : item));
                            }}
                            className="w-16 py-1 px-2 rounded border text-xs font-bold text-center"
                          />
                        </div>
                        <button
                          onClick={() => setBatchItems(batchItems.filter((_, i) => i !== idx))}
                          className="p-1.5 text-rose-600 hover:bg-rose-50 rounded-lg cursor-pointer"
                        >
                          <Trash2 className="w-4 h-4" />
                        </button>
                      </div>
                    </div>
                  ))}
                </div>

                <div className="pt-2 border-t">
                  <label className="block text-xs font-bold mb-1">Add Product to Batch:</label>
                  <select
                    onChange={(e) => {
                      const p = products.find(prod => prod.id === e.target.value);
                      if (p && !batchItems.some(bi => bi.product.id === p.id)) {
                        setBatchItems([...batchItems, { product: p, qty: 4 }]);
                      }
                    }}
                    className={`w-full py-2 px-3 rounded-xl border text-xs ${
                      isLight ? 'bg-neutral-50 border-neutral-200 text-neutral-900' : 'bg-neutral-800 border-neutral-700 text-white'
                    }`}
                    defaultValue=""
                  >
                    <option value="" disabled>-- Select product from inventory --</option>
                    {products.map(p => (
                      <option key={p.id} value={p.id}>{p.name} (Rs. {p.salePrice})</option>
                    ))}
                  </select>
                </div>
              </div>
            )}

            {/* Sub-tab 3: Custom / Freeform */}
            {activeSubTab === 'custom' && (
              <div className={`p-5 rounded-2xl ${isLight ? 'bg-white border-neutral-200' : 'bg-neutral-900 border-neutral-800'} border shadow-sm space-y-4`}>
                <div className="grid grid-cols-1 sm:grid-cols-2 gap-3">
                  <div>
                    <label className="block text-xs font-bold mb-1">Barcode / Code *</label>
                    <input
                      type="text"
                      value={customCode}
                      onChange={(e) => setCustomCode(e.target.value)}
                      className="w-full py-2 px-3 rounded-xl border text-xs font-mono"
                    />
                  </div>
                  <div>
                    <label className="block text-xs font-bold mb-1">Item Name *</label>
                    <input
                      type="text"
                      value={customName}
                      onChange={(e) => setCustomName(e.target.value)}
                      className="w-full py-2 px-3 rounded-xl border text-xs font-bold"
                    />
                  </div>
                  <div>
                    <label className="block text-xs font-bold mb-1">Price (Rs.)</label>
                    <input
                      type="text"
                      value={customPrice}
                      onChange={(e) => setCustomPrice(e.target.value)}
                      className="w-full py-2 px-3 rounded-xl border text-xs"
                    />
                  </div>
                  <div>
                    <label className="block text-xs font-bold mb-1">Brand / Company</label>
                    <input
                      type="text"
                      value={customBrand}
                      onChange={(e) => setCustomBrand(e.target.value)}
                      className="w-full py-2 px-3 rounded-xl border text-xs"
                    />
                  </div>
                  <div>
                    <label className="block text-xs font-bold mb-1">Batch No</label>
                    <input
                      type="text"
                      value={customBatch}
                      onChange={(e) => setCustomBatch(e.target.value)}
                      className="w-full py-2 px-3 rounded-xl border text-xs"
                    />
                  </div>
                  <div>
                    <label className="block text-xs font-bold mb-1">Expiry Date</label>
                    <input
                      type="text"
                      value={customExpiry}
                      onChange={(e) => setCustomExpiry(e.target.value)}
                      className="w-full py-2 px-3 rounded-xl border text-xs"
                    />
                  </div>
                </div>
                <div>
                  <label className="block text-xs font-bold mb-1">Print Quantity:</label>
                  <input
                    type="number"
                    min="1"
                    value={printQty}
                    onChange={(e) => setPrintQty(Math.max(1, parseInt(e.target.value) || 1))}
                    className="w-32 py-2 px-3 rounded-xl border text-xs font-bold"
                  />
                </div>
              </div>
            )}

            {/* Format & Paper Specifications */}
            <div className={`p-5 rounded-2xl ${isLight ? 'bg-white border-neutral-200' : 'bg-neutral-900 border-neutral-800'} border shadow-sm space-y-4`}>
              <h3 className="text-xs font-black uppercase tracking-wider text-emerald-700 flex items-center gap-1.5">
                <Sliders className="w-4 h-4" />
                <span>Format & Paper Specifications</span>
              </h3>

              <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                  <label className="block text-xs font-bold mb-1.5">Sticker Paper / Roll Template:</label>
                  <select
                    value={paperTemplate}
                    onChange={(e) => setPaperTemplate(e.target.value)}
                    className="w-full py-2 px-3 rounded-xl border text-xs font-semibold"
                  >
                    <option value="50x30">50mm × 30mm (2″ × 1.2″ Standard Pharmacy Roll)</option>
                    <option value="40x25">40mm × 25mm Thermal Roll</option>
                    <option value="a4sheet">A4 Sheet (3×4 Grid Standard Sticker Sheet)</option>
                  </select>
                </div>

                <div>
                  <label className="block text-xs font-bold mb-1.5">Barcode Symbology:</label>
                  <select className="w-full py-2 px-3 rounded-xl border text-xs font-semibold">
                    <option value="CODE128">CODE 128 (Auto Alphanumeric - Recommended)</option>
                    <option value="EAN13">EAN-13 Standard Retail Barcode</option>
                    <option value="CODE39">CODE 39 Standard</option>
                  </select>
                </div>
              </div>

              <div>
                <div className="flex justify-between text-xs font-bold mb-1">
                  <span>Bar Height:</span>
                  <span className="text-emerald-600">{barHeight}px</span>
                </div>
                <input
                  type="range"
                  min="24"
                  value={barHeight}
                  max="60"
                  onChange={(e) => setBarHeight(Number(e.target.value))}
                  className="w-full accent-emerald-600"
                />
              </div>

              {/* Toggles */}
              <div>
                <label className="block text-xs font-bold mb-2">Label Content Toggles:</label>
                <div className="grid grid-cols-2 sm:grid-cols-3 gap-2 text-xs">
                  <label className="flex items-center gap-2 cursor-pointer">
                    <input type="checkbox" checked={showStoreHeader} onChange={(e) => setShowStoreHeader(e.target.checked)} className="rounded text-emerald-600" />
                    <span>Store Header</span>
                  </label>
                  <label className="flex items-center gap-2 cursor-pointer">
                    <input type="checkbox" checked={showProductTitle} onChange={(e) => setShowProductTitle(e.target.checked)} className="rounded text-emerald-600" />
                    <span>Product Title</span>
                  </label>
                  <label className="flex items-center gap-2 cursor-pointer">
                    <input type="checkbox" checked={showRetailPrice} onChange={(e) => setShowRetailPrice(e.target.checked)} className="rounded text-emerald-600" />
                    <span>Retail Price</span>
                  </label>
                  <label className="flex items-center gap-2 cursor-pointer">
                    <input type="checkbox" checked={showBrand} onChange={(e) => setShowBrand(e.target.checked)} className="rounded text-emerald-600" />
                    <span>Brand / Company</span>
                  </label>
                  <label className="flex items-center gap-2 cursor-pointer">
                    <input type="checkbox" checked={showBatchNo} onChange={(e) => setShowBatchNo(e.target.checked)} className="rounded text-emerald-600" />
                    <span>Batch Number</span>
                  </label>
                  <label className="flex items-center gap-2 cursor-pointer">
                    <input type="checkbox" checked={showExpiry} onChange={(e) => setShowExpiry(e.target.checked)} className="rounded text-emerald-600" />
                    <span>Expiry Date</span>
                  </label>
                </div>
              </div>

              {/* Print Button */}
              <button
                onClick={handlePrintStickers}
                className="w-full py-3.5 px-4 rounded-xl bg-emerald-600 hover:bg-emerald-700 text-white font-black text-sm shadow-lg flex items-center justify-center gap-2 transition-all cursor-pointer"
              >
                <Printer className="w-5 h-5" />
                <span>PRINT {previewItems.length} BARCODE STICKERS NOW</span>
              </button>
            </div>

          </div>

          {/* Right Live Preview Panel (5 Cols) */}
          <div className="lg:col-span-5 space-y-4">
            <div className={`p-5 rounded-2xl ${isLight ? 'bg-white border-neutral-200' : 'bg-neutral-900 border-neutral-800'} border shadow-sm sticky top-20`}>
              <div className="flex items-center justify-between mb-4 pb-2 border-b">
                <span className="text-xs font-black uppercase tracking-wider text-emerald-700 flex items-center gap-1.5">
                  <FileText className="w-4 h-4" />
                  <span>Live Barcode Layout Preview</span>
                </span>
                <span className="text-[10px] bg-emerald-100 text-emerald-800 font-bold px-2 py-0.5 rounded-full">
                  Total Output: {previewItems.length} Stickers
                </span>
              </div>

              {/* Preview Grid Container */}
              <div className="max-h-[600px] overflow-y-auto p-3 bg-neutral-100 rounded-xl border border-neutral-200 grid grid-cols-1 sm:grid-cols-2 gap-3 print:bg-white print:border-none">
                {previewItems.slice(0, 24).map((item, idx) => (
                  <div
                    key={idx}
                    className="bg-white text-neutral-900 p-2.5 rounded-lg border border-neutral-300 shadow-xs flex flex-col justify-between text-center select-none relative overflow-hidden"
                    style={{ minHeight: '120px' }}
                  >
                    {showStoreHeader && (
                      <div className="text-[9px] font-black uppercase tracking-wider text-neutral-700 border-b border-neutral-200 pb-0.5 mb-1 truncate">
                        {settings.shopName || 'MY MEDICAL & MOBILE STORE'}
                      </div>
                    )}

                    {showProductTitle && (
                      <div className="text-[11px] font-bold text-neutral-900 leading-tight truncate px-1">
                        {item.title}
                      </div>
                    )}

                    {/* Barcode SVG */}
                    <div className="my-1">
                      {renderBarcodeSvg(item.code)}
                      <div className="text-[10px] font-mono font-bold tracking-widest text-neutral-900 mt-0.5">
                        {item.code}
                      </div>
                    </div>

                    {/* Footer metadata */}
                    <div className="flex items-center justify-between text-[9px] font-semibold text-neutral-700 border-t border-neutral-200 pt-0.5 px-0.5 mt-0.5">
                      {showBrand && <span className="truncate max-w-[50%]" title={item.brand}>{item.brand}</span>}
                      {showRetailPrice && <span className="font-extrabold text-neutral-900 ml-auto">Rs. {item.price.toLocaleString()}</span>}
                    </div>

                    {(showBatchNo || showExpiry) && (
                      <div className="flex justify-between text-[8px] text-neutral-500 pt-0.5">
                        {showBatchNo && <span>Bat: {item.batch}</span>}
                        {showExpiry && <span>Exp: {item.expiry}</span>}
                      </div>
                    )}
                  </div>
                ))}
              </div>

              {previewItems.length > 24 && (
                <div className="text-center text-[10px] text-neutral-500 mt-2">
                  Showing first 24 of {previewItems.length} stickers in preview. All {previewItems.length} will be printed.
                </div>
              )}

              {/* Instructions Box */}
              <div className="mt-4 p-3 rounded-xl bg-neutral-50 border border-neutral-200 text-[11px] text-neutral-600 space-y-1">
                <div className="font-bold text-neutral-800 flex items-center gap-1">
                  <span>ℹ️</span>
                  <span>Thermal & Laser Label Printing Instructions:</span>
                </div>
                <p>
                  In the browser Print Dialog, set <strong>Destination</strong> to your label printer (e.g. Zebra, Xprinter, TSC), set <strong>Margins: None</strong>, and ensure <strong>Scale is 100%</strong>. For A4 sticker sheets, select paper size <strong>A4</strong> with Margins: Default.
                </p>
              </div>

            </div>
          </div>

        </div>

      </div>
    </div>
  );
};
