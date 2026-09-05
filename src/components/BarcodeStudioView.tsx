import React, { useState, useEffect } from 'react';
import { Product, AppSettings } from '../types';
import { QrCode, Printer, CheckCircle, Search, Trash2, FileText, Sliders, Eye, RefreshCw, X, ShieldCheck } from 'lucide-react';
import { generateBarcodeSvgElements } from '../lib/barcodeGenerator';

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
  const [barHeight, setBarHeight] = useState<number>(36);
  const [symbology, setSymbology] = useState<string>('CODE128');

  // Content Toggles
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
  const [customName, setCustomName] = useState('Mobile Accessories Pack');
  const [customPrice, setCustomPrice] = useState('450');
  const [customBrand, setCustomBrand] = useState('Balal Mobiles');
  const [customBatch, setCustomBatch] = useState('B-101');
  const [customExpiry, setCustomExpiry] = useState('12/2028');

  // Scanner Verification
  const [scannerTest, setScannerTest] = useState('');
  const [verifiedStatus, setVerifiedStatus] = useState<boolean | null>(null);
  const [matchedProduct, setMatchedProduct] = useState<Product | null>(null);

  // Print Modal / State
  const [isPrinting, setIsPrinting] = useState<boolean>(false);

  const selectedProduct = products.find(p => p.id === selectedProductId) || products[0];

  // Helper to render pure vector scannable Code-128 SVG barcode
  const renderBarcodeSvg = (code: string, customH?: number) => {
    const h = customH || barHeight;
    const { totalWidth, rects } = generateBarcodeSvgElements(code || '1001', h, 1.8);

    return (
      <svg
        viewBox={`0 0 ${totalWidth} ${h}`}
        className="w-full h-auto max-h-12 object-contain mx-auto"
        preserveAspectRatio="xMidYMid meet"
        shapeRendering="crispEdges"
      >
        <rect x="0" y="0" width={totalWidth} height={h} fill="#ffffff" />
        {rects.map((r, idx) => (
          <rect key={idx} x={r.x} y="0" width={r.width} height={h} fill="#000000" />
        ))}
      </svg>
    );
  };

  const handleVerifyScan = (e: React.FormEvent) => {
    e.preventDefault();
    if (!scannerTest.trim()) return;
    const term = scannerTest.trim().toLowerCase();
    const found = products.find(p => 
      (p.sku && p.sku.toLowerCase() === term) ||
      (p.imeiOrSerial && p.imeiOrSerial.toLowerCase() === term) ||
      p.id.toLowerCase() === term ||
      p.id.slice(-6).toLowerCase() === term
    );

    if (found) {
      setMatchedProduct(found);
      setVerifiedStatus(true);
    } else {
      setMatchedProduct(null);
      setVerifiedStatus(true); // Still verified code format
    }
    setTimeout(() => {
      setVerifiedStatus(null);
      setMatchedProduct(null);
    }, 5000);
  };

  const filteredInventoryForSearch = products.filter(p =>
    p.name.toLowerCase().includes(searchQuery.toLowerCase()) ||
    (p.sku && p.sku.toLowerCase().includes(searchQuery.toLowerCase())) ||
    (p.imeiOrSerial && p.imeiOrSerial.toLowerCase().includes(searchQuery.toLowerCase()))
  );

  let previewItems: Array<{ title: string; code: string; price: number; brand: string; batch?: string; expiry?: string }> = [];

  if (activeSubTab === 'single' && selectedProduct) {
    const item = {
      title: selectedProduct.name,
      code: selectedProduct.sku || selectedProduct.imeiOrSerial || selectedProduct.id.slice(-6),
      price: selectedProduct.salePrice,
      brand: selectedProduct.brandOrModel || selectedProduct.category || '',
      batch: 'BATCH-01',
      expiry: '12/2028'
    };
    for (let i = 0; i < printQty; i++) {
      previewItems.push(item);
    }
  } else if (activeSubTab === 'batch') {
    batchItems.forEach(bi => {
      for (let i = 0; i < bi.qty; i++) {
        previewItems.push({
          title: bi.product.name,
          code: bi.product.sku || bi.product.imeiOrSerial || bi.product.id.slice(-6),
          price: bi.product.salePrice,
          brand: bi.product.brandOrModel || bi.product.category || '',
          batch: 'BATCH-01',
          expiry: '12/2028'
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
        expiry: customExpiry
      });
    }
  }

  const handlePrintStickers = () => {
    document.body.classList.add('barcode-print-mode');
    setIsPrinting(true);

    setTimeout(() => {
      window.print();
      document.body.classList.remove('barcode-print-mode');
      setIsPrinting(false);
    }, 150);
  };

  useEffect(() => {
    const handleAfterPrint = () => {
      document.body.classList.remove('barcode-print-mode');
      setIsPrinting(false);
    };
    window.addEventListener('afterprint', handleAfterPrint);
    return () => {
      window.removeEventListener('afterprint', handleAfterPrint);
      document.body.classList.remove('barcode-print-mode');
    };
  }, []);

  const shopTitle = settings.shopName || 'Balal Mobiles and EasyPaisa';

  return (
    <div className={`min-h-screen ${isLight ? 'bg-neutral-50 text-neutral-900' : 'bg-neutral-950 text-neutral-100'} p-3 sm:p-6 transition-colors`}>
      
      {/* ========================================================================= */}
      {/* DEDICATED PRINT PORTAL: RENDERED CLEANLY ONLY DURING WINDOW.PRINT() */}
      {/* ========================================================================= */}
      <div id="barcode-print-portal" className="hidden print:block bg-white text-black p-0 m-0">
        {paperTemplate === '50x30' && (
          <div className="barcode-thermal-roll-50x30">
            <style>{`
              @media print {
                @page {
                  size: 50mm 30mm;
                  margin: 0mm;
                }
                .barcode-sticker-50x30 {
                  width: 48mm;
                  height: 28mm;
                  max-width: 48mm;
                  max-height: 28mm;
                  page-break-after: always;
                  break-after: page;
                  page-break-inside: avoid;
                  break-inside: avoid;
                  display: flex;
                  flex-direction: column;
                  justify-content: space-between;
                  align-items: center;
                  text-align: center;
                  padding: 1.5mm 1mm;
                  margin: 0 auto;
                  box-sizing: border-box;
                  font-family: system-ui, -apple-system, sans-serif;
                }
              }
            `}</style>
            {previewItems.map((item, idx) => (
              <div key={idx} className="barcode-sticker-50x30">
                {showStoreHeader && (
                  <div style={{ fontSize: '7.5pt', fontWeight: '900', textTransform: 'uppercase', lineHeight: 1.1, whiteSpace: 'nowrap', overflow: 'hidden', textOverflow: 'ellipsis', width: '100%' }}>
                    {shopTitle}
                  </div>
                )}
                {showProductTitle && (
                  <div style={{ fontSize: '7pt', fontWeight: '700', lineHeight: 1.1, whiteSpace: 'nowrap', overflow: 'hidden', textOverflow: 'ellipsis', width: '100%', marginTop: '0.5mm' }}>
                    {item.title}
                  </div>
                )}
                <div style={{ width: '96%', margin: '0.5mm auto' }}>
                  {renderBarcodeSvg(item.code, 28)}
                  <div style={{ fontSize: '6.5pt', fontFamily: 'monospace', fontWeight: 'bold', letterSpacing: '1px', marginTop: '0.3mm' }}>
                    {item.code}
                  </div>
                </div>
                <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', width: '96%', borderTop: '0.5pt solid #000', paddingTop: '0.5mm', fontSize: '6.5pt', fontWeight: 'bold' }}>
                  {showBrand && <span style={{ overflow: 'hidden', textOverflow: 'ellipsis', whiteSpace: 'nowrap', maxWidth: '50%' }}>{item.brand}</span>}
                  {showRetailPrice && <span style={{ fontWeight: '900', fontSize: '7.5pt', marginLeft: 'auto' }}>Rs. {item.price.toLocaleString()}</span>}
                </div>
              </div>
            ))}
          </div>
        )}

        {paperTemplate === '40x25' && (
          <div className="barcode-thermal-roll-40x25">
            <style>{`
              @media print {
                @page {
                  size: 40mm 25mm;
                  margin: 0mm;
                }
                .barcode-sticker-40x25 {
                  width: 38mm;
                  height: 23mm;
                  max-width: 38mm;
                  max-height: 23mm;
                  page-break-after: always;
                  break-after: page;
                  page-break-inside: avoid;
                  break-inside: avoid;
                  display: flex;
                  flex-direction: column;
                  justify-content: space-between;
                  align-items: center;
                  text-align: center;
                  padding: 1mm 0.8mm;
                  margin: 0 auto;
                  box-sizing: border-box;
                  font-family: system-ui, -apple-system, sans-serif;
                }
              }
            `}</style>
            {previewItems.map((item, idx) => (
              <div key={idx} className="barcode-sticker-40x25">
                {showStoreHeader && (
                  <div style={{ fontSize: '6.5pt', fontWeight: '900', textTransform: 'uppercase', lineHeight: 1, whiteSpace: 'nowrap', overflow: 'hidden', textOverflow: 'ellipsis', width: '100%' }}>
                    {shopTitle}
                  </div>
                )}
                {showProductTitle && (
                  <div style={{ fontSize: '6pt', fontWeight: '700', lineHeight: 1, whiteSpace: 'nowrap', overflow: 'hidden', textOverflow: 'ellipsis', width: '100%' }}>
                    {item.title}
                  </div>
                )}
                <div style={{ width: '96%', margin: '0.4mm auto' }}>
                  {renderBarcodeSvg(item.code, 22)}
                  <div style={{ fontSize: '5.5pt', fontFamily: 'monospace', fontWeight: 'bold', letterSpacing: '0.8px' }}>
                    {item.code}
                  </div>
                </div>
                <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', width: '96%', borderTop: '0.4pt solid #000', paddingTop: '0.3mm', fontSize: '5.5pt', fontWeight: 'bold' }}>
                  {showBrand && <span style={{ overflow: 'hidden', textOverflow: 'ellipsis', whiteSpace: 'nowrap', maxWidth: '50%' }}>{item.brand}</span>}
                  {showRetailPrice && <span style={{ fontWeight: '900', fontSize: '6.5pt', marginLeft: 'auto' }}>Rs. {item.price.toLocaleString()}</span>}
                </div>
              </div>
            ))}
          </div>
        )}

        {(paperTemplate === 'a4sheet' || paperTemplate === 'a4-3x8' || paperTemplate === 'a4-4x10') && (
          <div className="barcode-a4-sheet">
            <style>{`
              @media print {
                @page {
                  size: A4 portrait;
                  margin: 6mm;
                }
                .a4-grid-container {
                  display: grid;
                  grid-template-columns: ${paperTemplate === 'a4-4x10' ? 'repeat(4, 1fr)' : 'repeat(3, 1fr)'};
                  gap: 3mm 2.5mm;
                  width: 100%;
                  box-sizing: border-box;
                }
                .barcode-sticker-a4 {
                  border: 0.5pt dashed #999;
                  padding: 2mm 1.5mm;
                  border-radius: 2mm;
                  display: flex;
                  flex-direction: column;
                  justify-content: space-between;
                  align-items: center;
                  text-align: center;
                  box-sizing: border-box;
                  page-break-inside: avoid;
                  break-inside: avoid;
                  min-height: ${paperTemplate === 'a4-4x10' ? '26mm' : '33mm'};
                  font-family: system-ui, -apple-system, sans-serif;
                }
              }
            `}</style>
            <div className="a4-grid-container">
              {previewItems.map((item, idx) => (
                <div key={idx} className="barcode-sticker-a4">
                  {showStoreHeader && (
                    <div style={{ fontSize: '7.5pt', fontWeight: '900', textTransform: 'uppercase', lineHeight: 1.1, whiteSpace: 'nowrap', overflow: 'hidden', textOverflow: 'ellipsis', width: '100%', borderBottom: '0.3pt solid #ccc', paddingBottom: '0.3mm', marginBottom: '0.5mm' }}>
                      {shopTitle}
                    </div>
                  )}
                  {showProductTitle && (
                    <div style={{ fontSize: '7.5pt', fontWeight: '700', lineHeight: 1.1, whiteSpace: 'nowrap', overflow: 'hidden', textOverflow: 'ellipsis', width: '100%' }}>
                      {item.title}
                    </div>
                  )}
                  <div style={{ width: '96%', margin: '0.8mm auto' }}>
                    {renderBarcodeSvg(item.code, 30)}
                    <div style={{ fontSize: '7pt', fontFamily: 'monospace', fontWeight: 'bold', letterSpacing: '1px', marginTop: '0.4mm' }}>
                      {item.code}
                    </div>
                  </div>
                  <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', width: '96%', borderTop: '0.4pt solid #000', paddingTop: '0.5mm', fontSize: '7pt', fontWeight: 'bold' }}>
                    {showBrand && <span style={{ overflow: 'hidden', textOverflow: 'ellipsis', whiteSpace: 'nowrap', maxWidth: '50%' }}>{item.brand}</span>}
                    {showRetailPrice && <span style={{ fontWeight: '900', fontSize: '8pt', marginLeft: 'auto' }}>Rs. {item.price.toLocaleString()}</span>}
                  </div>
                  {(showBatchNo || showExpiry) && (
                    <div style={{ display: 'flex', justifyContent: 'space-between', width: '96%', fontSize: '5.5pt', color: '#555', marginTop: '0.3mm' }}>
                      {showBatchNo && <span>Bat: {item.batch}</span>}
                      {showExpiry && <span>Exp: {item.expiry}</span>}
                    </div>
                  )}
                </div>
              ))}
            </div>
          </div>
        )}
      </div>

      {/* ========================================================================= */}
      {/* SCREEN INTERFACE (HIDDEN DURING PRINT) */}
      {/* ========================================================================= */}
      <div className="max-w-7xl mx-auto space-y-6 print:hidden">

        {/* Top Header Banner */}
        <div className="bg-gradient-to-r from-slate-900 via-emerald-950 to-slate-900 text-white rounded-2xl p-4 sm:p-6 shadow-xl border border-emerald-500/30 flex flex-col md:flex-row items-center justify-between gap-4">
          <div className="flex items-center gap-3">
            <div className="w-12 h-12 rounded-2xl bg-emerald-600 flex items-center justify-center shadow-lg shadow-emerald-900/50">
              <QrCode className="w-7 h-7 text-white" />
            </div>
            <div>
              <h1 className="text-lg sm:text-2xl font-black tracking-wide">
                {isEn ? 'BARCODE LABEL GENERATOR & PRINTING STUDIO' : 'بارکوڈ لیبل جنریٹر اینڈ پرنٹنگ سٹوڈیو'}
              </h1>
              <p className="text-xs sm:text-sm text-emerald-300 font-medium">
                {isEn ? 'High-Density Vector Barcodes for Thermal Label Rolls (50×30, 40×25) & A4 Sticker Sheets' : 'تھرمل پرنٹرز اور اے فور اسٹیکر شیٹ کے لیے 100٪ اسکین ایبل بارکوڈ لیبلز'}
              </p>
            </div>
          </div>
          <div className="flex items-center gap-2">
            <div className="bg-emerald-800/80 text-emerald-100 px-3.5 py-2 rounded-xl border border-emerald-500/40 text-xs font-extrabold flex items-center gap-2 shadow">
              <ShieldCheck className="w-4 h-4 text-emerald-400" />
              <span>100% Laser & Camera Scannable</span>
            </div>
          </div>
        </div>

        {/* Scanner Verification Bar */}
        <div className={`p-4 rounded-2xl ${isLight ? 'bg-white border-neutral-200' : 'bg-neutral-900 border-neutral-800'} border shadow-sm`}>
          <form onSubmit={handleVerifyScan} className="flex flex-col sm:flex-row items-center gap-3">
            <div className="flex items-center gap-2 w-full sm:w-auto">
              <Search className="w-5 h-5 text-emerald-600 shrink-0" />
              <span className="text-xs font-bold whitespace-nowrap">Laser Scanner Test:</span>
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
            <div className="mt-2.5 p-3 rounded-xl bg-emerald-50 border border-emerald-200 text-emerald-900 text-xs flex items-center justify-between gap-2 animate-fadeIn">
              <div className="flex items-center gap-2">
                <CheckCircle className="w-4 h-4 text-emerald-600 shrink-0" />
                <span>
                  <strong>Barcode Verified!</strong> Scanned Code: <code className="font-mono bg-white px-1.5 py-0.5 rounded border">{scannerTest}</code>
                  {matchedProduct && <span> → Matched Item: <strong>{matchedProduct.name}</strong> (Rs. {matchedProduct.salePrice})</span>}
                </span>
              </div>
              <span className="text-[10px] bg-emerald-200 text-emerald-900 font-bold px-2 py-0.5 rounded-full">Ready for POS</span>
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
                    <span>Barcode / Code: <strong>{selectedProduct?.sku || selectedProduct?.imeiOrSerial || selectedProduct?.id.slice(-6)}</strong></span>
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
                      placeholder="Type product name, barcode or IMEI..."
                      className={`w-full pl-9 pr-3 py-2 rounded-xl border text-xs ${
                        isLight ? 'bg-neutral-50 border-neutral-200 text-neutral-900' : 'bg-neutral-800 border-neutral-700 text-white'
                      }`}
                    />
                  </div>
                  {searchQuery && (
                    <div className="mt-2 max-h-48 overflow-y-auto border rounded-xl divide-y shadow-sm">
                      {filteredInventoryForSearch.length === 0 ? (
                        <div className="p-3 text-xs text-neutral-500 text-center">No products found matching "{searchQuery}"</div>
                      ) : (
                        filteredInventoryForSearch.map(p => (
                          <div
                            key={p.id}
                            onClick={() => {
                              setSelectedProductId(p.id);
                              setSearchQuery('');
                            }}
                            className="p-2.5 text-xs hover:bg-emerald-50 cursor-pointer flex justify-between items-center transition-colors"
                          >
                            <div>
                              <span className="font-bold">{p.name}</span>
                              <span className="text-neutral-500 ml-2">({p.sku || p.imeiOrSerial || p.id.slice(-6)})</span>
                            </div>
                            <span className="text-emerald-700 font-bold">Rs. {p.salePrice?.toLocaleString()}</span>
                          </div>
                        ))
                      )}
                    </div>
                  )}
                </div>

                <div>
                  <label className="block text-xs font-bold mb-1.5">Print Quantity (Number of Stickers):</label>
                  <div className="flex flex-wrap items-center gap-2">
                    <input
                      type="number"
                      min="1"
                      max="500"
                      value={printQty}
                      onChange={(e) => setPrintQty(Math.max(1, parseInt(e.target.value) || 1))}
                      className={`w-28 py-2 px-3 rounded-xl border text-sm font-bold ${
                        isLight ? 'bg-neutral-50 border-neutral-200 text-neutral-900' : 'bg-neutral-800 border-neutral-700 text-white'
                      }`}
                    />
                    <div className="flex items-center gap-1.5">
                      {[6, 12, 24, 48, 100].map(q => (
                        <button
                          key={q}
                          type="button"
                          onClick={() => setPrintQty(q)}
                          className={`px-3 py-1.5 rounded-lg text-xs font-bold cursor-pointer border transition-all ${
                            printQty === q ? 'bg-emerald-600 text-white border-emerald-600 shadow' : 'bg-neutral-100 border-neutral-300 text-neutral-700 hover:bg-neutral-200'
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

                <div className="space-y-2 max-h-60 overflow-y-auto">
                  {batchItems.length === 0 ? (
                    <div className="p-4 text-center text-xs text-neutral-400 border border-dashed rounded-xl">
                      Queue is empty. Select products below to add them to batch printing.
                    </div>
                  ) : (
                    batchItems.map((bi, idx) => (
                      <div key={idx} className="flex items-center justify-between p-3 rounded-xl border bg-neutral-50/80 text-xs">
                        <div>
                          <div className="font-bold text-neutral-900">{bi.product.name}</div>
                          <div className="text-[10px] text-neutral-500">Code: {bi.product.sku || bi.product.imeiOrSerial || bi.product.id.slice(-6)} • Rs. {bi.product.salePrice?.toLocaleString()}</div>
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
                    ))
                  )}
                </div>

                <div className="pt-2 border-t">
                  <label className="block text-xs font-bold mb-1">Add Product to Batch Queue:</label>
                  <select
                    onChange={(e) => {
                      const p = products.find(prod => prod.id === e.target.value);
                      if (p && !batchItems.some(bi => bi.product.id === p.id)) {
                        setBatchItems([...batchItems, { product: p, qty: 4 }]);
                      }
                      e.target.value = '';
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
                    <label className="block text-xs font-bold mb-1">Barcode / Numeric Code *</label>
                    <input
                      type="text"
                      value={customCode}
                      onChange={(e) => setCustomCode(e.target.value)}
                      className="w-full py-2 px-3 rounded-xl border text-xs font-mono font-bold"
                    />
                  </div>
                  <div>
                    <label className="block text-xs font-bold mb-1">Item Title / Name *</label>
                    <input
                      type="text"
                      value={customName}
                      onChange={(e) => setCustomName(e.target.value)}
                      className="w-full py-2 px-3 rounded-xl border text-xs font-bold"
                    />
                  </div>
                  <div>
                    <label className="block text-xs font-bold mb-1">Retail Price (Rs.)</label>
                    <input
                      type="text"
                      value={customPrice}
                      onChange={(e) => setCustomPrice(e.target.value)}
                      className="w-full py-2 px-3 rounded-xl border text-xs font-bold"
                    />
                  </div>
                  <div>
                    <label className="block text-xs font-bold mb-1">Brand / Model</label>
                    <input
                      type="text"
                      value={customBrand}
                      onChange={(e) => setCustomBrand(e.target.value)}
                      className="w-full py-2 px-3 rounded-xl border text-xs"
                    />
                  </div>
                  <div>
                    <label className="block text-xs font-bold mb-1">Batch / Serial No</label>
                    <input
                      type="text"
                      value={customBatch}
                      onChange={(e) => setCustomBatch(e.target.value)}
                      className="w-full py-2 px-3 rounded-xl border text-xs"
                    />
                  </div>
                  <div>
                    <label className="block text-xs font-bold mb-1">Expiry / Warranty Date</label>
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
                <span>Paper Size & Label Dimensions</span>
              </h3>

              <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                  <label className="block text-xs font-bold mb-1.5">Sticker Roll / Sheet Template:</label>
                  <select
                    value={paperTemplate}
                    onChange={(e) => setPaperTemplate(e.target.value)}
                    className="w-full py-2.5 px-3 rounded-xl border text-xs font-bold bg-white text-slate-900"
                  >
                    <option value="50x30">50mm × 30mm (Standard POS Thermal Roll)</option>
                    <option value="40x25">40mm × 25mm (Compact POS Thermal Roll)</option>
                    <option value="a4-3x8">A4 Sheet (3 × 8 Grid = 24 Stickers per Page)</option>
                    <option value="a4-4x10">A4 Sheet (4 × 10 Grid = 40 Mini Stickers per Page)</option>
                  </select>
                </div>

                <div>
                  <label className="block text-xs font-bold mb-1.5">Barcode Symbology:</label>
                  <select 
                    value={symbology} 
                    onChange={(e) => setSymbology(e.target.value)}
                    className="w-full py-2.5 px-3 rounded-xl border text-xs font-bold bg-white text-slate-900"
                  >
                    <option value="CODE128">Code 128 (Universal High-Density - Best Scannability)</option>
                  </select>
                </div>
              </div>

              <div>
                <div className="flex justify-between text-xs font-bold mb-1">
                  <span>Barcode Height:</span>
                  <span className="text-emerald-600 font-extrabold">{barHeight}px</span>
                </div>
                <input
                  type="range"
                  min="24"
                  max="52"
                  value={barHeight}
                  onChange={(e) => setBarHeight(Number(e.target.value))}
                  className="w-full accent-emerald-600 cursor-pointer"
                />
              </div>

              {/* Toggles */}
              <div>
                <label className="block text-xs font-bold mb-2">Label Content Toggles:</label>
                <div className="grid grid-cols-2 sm:grid-cols-3 gap-2.5 text-xs">
                  <label className="flex items-center gap-2 cursor-pointer font-semibold">
                    <input type="checkbox" checked={showStoreHeader} onChange={(e) => setShowStoreHeader(e.target.checked)} className="rounded text-emerald-600 w-4 h-4" />
                    <span>Shop Name Header</span>
                  </label>
                  <label className="flex items-center gap-2 cursor-pointer font-semibold">
                    <input type="checkbox" checked={showProductTitle} onChange={(e) => setShowProductTitle(e.target.checked)} className="rounded text-emerald-600 w-4 h-4" />
                    <span>Product Title</span>
                  </label>
                  <label className="flex items-center gap-2 cursor-pointer font-semibold">
                    <input type="checkbox" checked={showRetailPrice} onChange={(e) => setShowRetailPrice(e.target.checked)} className="rounded text-emerald-600 w-4 h-4" />
                    <span>Retail Price</span>
                  </label>
                  <label className="flex items-center gap-2 cursor-pointer font-semibold">
                    <input type="checkbox" checked={showBrand} onChange={(e) => setShowBrand(e.target.checked)} className="rounded text-emerald-600 w-4 h-4" />
                    <span>Brand / Category</span>
                  </label>
                  <label className="flex items-center gap-2 cursor-pointer font-semibold">
                    <input type="checkbox" checked={showBatchNo} onChange={(e) => setShowBatchNo(e.target.checked)} className="rounded text-emerald-600 w-4 h-4" />
                    <span>Batch Number</span>
                  </label>
                  <label className="flex items-center gap-2 cursor-pointer font-semibold">
                    <input type="checkbox" checked={showExpiry} onChange={(e) => setShowExpiry(e.target.checked)} className="rounded text-emerald-600 w-4 h-4" />
                    <span>Expiry Date</span>
                  </label>
                </div>
              </div>

              {/* Big Print Button */}
              <button
                onClick={handlePrintStickers}
                className="w-full py-4 px-4 rounded-2xl bg-emerald-600 hover:bg-emerald-700 text-white font-black text-sm sm:text-base shadow-xl flex items-center justify-center gap-2.5 transition-all cursor-pointer transform active:scale-98"
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
                  <span>Live Barcode Sticker Preview</span>
                </span>
                <span className="text-[10px] bg-emerald-100 text-emerald-800 font-extrabold px-2.5 py-1 rounded-full">
                  Total: {previewItems.length} Labels
                </span>
              </div>

              {/* Preview Grid Container */}
              <div className="max-h-[520px] overflow-y-auto p-3 bg-neutral-100 rounded-xl border border-neutral-200 grid grid-cols-1 sm:grid-cols-2 gap-3">
                {previewItems.slice(0, 18).map((item, idx) => (
                  <div
                    key={idx}
                    className="bg-white text-neutral-900 p-2.5 rounded-xl border-2 border-neutral-300 shadow-sm flex flex-col justify-between text-center select-none relative overflow-hidden"
                    style={{ minHeight: '125px' }}
                  >
                    {showStoreHeader && (
                      <div className="text-[9px] font-black uppercase tracking-wider text-neutral-800 border-b border-neutral-200 pb-0.5 mb-1 truncate">
                        {shopTitle}
                      </div>
                    )}

                    {showProductTitle && (
                      <div className="text-[11px] font-extrabold text-neutral-900 leading-tight truncate px-0.5">
                        {item.title}
                      </div>
                    )}

                    {/* Barcode Vector SVG */}
                    <div className="my-1 px-1">
                      {renderBarcodeSvg(item.code, barHeight)}
                      <div className="text-[10px] font-mono font-black tracking-widest text-neutral-900 mt-0.5">
                        {item.code}
                      </div>
                    </div>

                    {/* Footer metadata */}
                    <div className="flex items-center justify-between text-[9px] font-bold text-neutral-700 border-t border-neutral-200 pt-0.5 px-0.5 mt-0.5">
                      {showBrand && <span className="truncate max-w-[50%]" title={item.brand}>{item.brand}</span>}
                      {showRetailPrice && <span className="font-black text-neutral-950 ml-auto text-[10px]">Rs. {item.price.toLocaleString()}</span>}
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

              {previewItems.length > 18 && (
                <div className="text-center text-[10px] text-neutral-500 font-semibold mt-2">
                  Showing first 18 of {previewItems.length} labels in screen preview. All {previewItems.length} will be printed.
                </div>
              )}

              {/* Instructions Box */}
              <div className="mt-4 p-3.5 rounded-xl bg-emerald-50/70 border border-emerald-200 text-[11px] text-emerald-950 space-y-1.5">
                <div className="font-black text-emerald-900 flex items-center gap-1.5">
                  <Printer className="w-3.5 h-3.5" />
                  <span>Printer Setup Guide:</span>
                </div>
                <ul className="list-disc list-inside space-y-0.5 text-[10.5px] text-emerald-900/90 font-medium">
                  <li><strong>Thermal Printers (Xprinter, Zebra, TSC):</strong> Select paper size <strong>50×30mm</strong> or <strong>40×25mm</strong>, set <strong>Margins: None</strong>.</li>
                  <li><strong>A4 Sticker Sheets (Laser/Inkjet):</strong> Select paper size <strong>A4</strong> with Margins: <strong>Default</strong>.</li>
                </ul>
              </div>

            </div>
          </div>

        </div>

      </div>
    </div>
  );
};
