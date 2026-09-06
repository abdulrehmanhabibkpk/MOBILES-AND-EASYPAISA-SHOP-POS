import React, { useState } from 'react';
import { X, Smartphone, Check, Search, Plus, Camera, Sparkles, Hash, ShieldCheck } from 'lucide-react';
import { Product, ProductUnitItem, AppSettings } from '../types';
import { BarcodeScannerModal } from './BarcodeScannerModal';

interface UnitSelectorModalProps {
  isOpen: boolean;
  onClose: () => void;
  product: Product | null;
  selectedUnitIdsInCart: string[];
  onSelectUnit: (product: Product, unit: ProductUnitItem) => void;
  settings: AppSettings;
}

export const UnitSelectorModal: React.FC<UnitSelectorModalProps> = ({
  isOpen,
  onClose,
  product,
  selectedUnitIdsInCart,
  onSelectUnit,
  settings,
}) => {
  const [searchTerm, setSearchTerm] = useState('');
  const [isScannerOpen, setIsScannerOpen] = useState(false);

  if (!isOpen || !product) return null;

  const isLight = settings.theme === 'light';

  // Get available units
  const allUnits: ProductUnitItem[] = product.units && product.units.length > 0
    ? product.units
    : product.imeiOrSerial
    ? [{
        id: 'unit-1',
        imei1: product.imeiOrSerial,
        color: product.color,
        storageRam: product.ramStorage,
        condition: product.condition,
        status: 'AVAILABLE'
      }]
    : [];

  const availableUnits = allUnits.filter((u) => u.status === 'AVAILABLE');

  const filteredUnits = availableUnits.filter((u) => {
    const term = searchTerm.toLowerCase().trim();
    if (!term) return true;
    return (
      (u.imei1 && u.imei1.toLowerCase().includes(term)) ||
      (u.imei2 && u.imei2.toLowerCase().includes(term)) ||
      (u.color && u.color.toLowerCase().includes(term)) ||
      (u.serialNo && u.serialNo.toLowerCase().includes(term)) ||
      (u.storageRam && u.storageRam.toLowerCase().includes(term))
    );
  });

  const handleScanSuccess = (scannedCode: string) => {
    setIsScannerOpen(false);
    const code = scannedCode.trim().toLowerCase();
    const matchedUnit = availableUnits.find(
      (u) =>
        (u.imei1 && u.imei1.toLowerCase() === code) ||
        (u.imei2 && u.imei2.toLowerCase() === code) ||
        (u.serialNo && u.serialNo.toLowerCase() === code)
    );

    if (matchedUnit) {
      if (selectedUnitIdsInCart.includes(matchedUnit.id)) {
        alert('Yeh IMEI / Unit pehle se cart mein shamil hai!');
      } else {
        onSelectUnit(product, matchedUnit);
        onClose();
      }
    } else {
      setSearchTerm(scannedCode);
    }
  };

  return (
    <div className="fixed inset-0 z-50 flex items-center justify-center bg-slate-950/80 backdrop-blur-sm p-3 sm:p-4 overflow-y-auto">
      <div className={`w-full max-w-xl rounded-2xl shadow-2xl border overflow-hidden my-auto max-h-[90vh] flex flex-col ${
        isLight ? 'bg-white border-slate-200 text-slate-800' : 'bg-slate-900 border-slate-700 text-slate-100'
      }`}>
        
        {/* Header */}
        <div className="bg-gradient-to-r from-emerald-600 to-teal-700 px-5 py-4 text-white flex items-center justify-between shrink-0">
          <div className="flex items-center gap-2.5">
            <div className="w-10 h-10 rounded-xl bg-white/10 flex items-center justify-center text-white font-black">
              <Smartphone className="w-5 h-5" />
            </div>
            <div>
              <h3 className="font-extrabold text-base sm:text-lg leading-tight">
                IMEI / Unit منتخب کریں (Select Phone)
              </h3>
              <p className="text-xs text-emerald-100 font-medium">
                {product.name} - قیمت: Rs. {product.salePrice.toLocaleString()}
              </p>
            </div>
          </div>
          <button
            onClick={onClose}
            className="w-8 h-8 rounded-full bg-white/10 hover:bg-white/20 text-white flex items-center justify-center transition-colors cursor-pointer"
          >
            <X className="w-4 h-4" />
          </button>
        </div>

        {/* Search & Scan Header */}
        <div className={`p-4 border-b space-y-3 shrink-0 ${isLight ? 'bg-slate-50 border-slate-200' : 'bg-slate-950/50 border-slate-800'}`}>
          <div className="flex gap-2">
            <div className="relative flex-1">
              <Search className="w-4 h-4 absolute left-3 top-3 text-slate-400" />
              <input
                type="text"
                autoFocus
                placeholder="IMEI 1, IMEI 2 ya Color se search karein..."
                value={searchTerm}
                onChange={(e) => setSearchTerm(e.target.value)}
                className={`w-full pl-9 pr-4 py-2 rounded-xl text-xs border focus:outline-none focus:ring-2 focus:ring-emerald-500 font-mono ${
                  isLight ? 'bg-white border-slate-200 text-slate-800' : 'bg-slate-800 border-slate-700 text-slate-100'
                }`}
              />
            </div>
            <button
              type="button"
              onClick={() => setIsScannerOpen(true)}
              className="px-3.5 py-2 rounded-xl bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-bold flex items-center gap-1.5 transition-all shadow-sm cursor-pointer shrink-0"
              title="Scan IMEI from Mobile Box"
            >
              <Camera className="w-4 h-4" />
              <span className="hidden sm:inline">Scan IMEI</span>
            </button>
          </div>

          <div className="flex items-center justify-between text-xs text-slate-500 dark:text-slate-400">
            <span>دستیاب فونز (Available Units): <strong className="text-emerald-600 dark:text-emerald-400 font-bold">{availableUnits.length}</strong></span>
            <span>Cart میں شامل: <strong className="text-blue-600 font-bold">{selectedUnitIdsInCart.length}</strong></span>
          </div>
        </div>

        {/* Units List */}
        <div className="p-4 overflow-y-auto space-y-2.5 flex-1">
          {filteredUnits.length === 0 ? (
            <div className="p-8 text-center text-slate-400">
              <Hash className="w-10 h-10 mx-auto opacity-40 mb-2" />
              <p className="text-xs font-bold">Koi Unit ya IMEI match nahi hua</p>
              <p className="text-[11px] text-slate-500 mt-1">
                برائے مہربانی IMEI چیک کریں یا دوسرا لفظ سرچ کریں۔
              </p>
            </div>
          ) : (
            filteredUnits.map((unit, index) => {
              const isInCart = selectedUnitIdsInCart.includes(unit.id);

              return (
                <div
                  key={unit.id || index}
                  className={`p-3.5 rounded-xl border transition-all ${
                    isInCart
                      ? isLight
                        ? 'bg-blue-50/60 border-blue-200 opacity-80'
                        : 'bg-blue-950/30 border-blue-900/60 opacity-80'
                      : isLight
                      ? 'bg-white border-slate-200 hover:border-emerald-500 shadow-sm hover:shadow-md'
                      : 'bg-slate-800 border-slate-700 hover:border-emerald-400'
                  }`}
                >
                  <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-3">
                    
                    <div className="space-y-1.5">
                      <div className="flex items-center gap-2 flex-wrap">
                        <span className="px-2 py-0.5 rounded-md bg-emerald-100 text-emerald-800 dark:bg-emerald-950/60 dark:text-emerald-300 font-extrabold text-[11px]">
                          Unit #{index + 1}
                        </span>

                        {unit.color && (
                          <span className="px-2 py-0.5 rounded-md bg-purple-100 text-purple-800 dark:bg-purple-950/60 dark:text-purple-300 font-bold text-[11px] flex items-center gap-1">
                            <span className="w-2 h-2 rounded-full bg-purple-500 inline-block" />
                            <span>Color: {unit.color}</span>
                          </span>
                        )}

                        {unit.storageRam && (
                          <span className="px-2 py-0.5 rounded-md bg-slate-100 text-slate-700 dark:bg-slate-700 dark:text-slate-300 font-bold text-[11px]">
                            {unit.storageRam}
                          </span>
                        )}

                        {unit.condition && (
                          <span className={`px-2 py-0.5 rounded-md font-bold text-[10px] ${
                            unit.condition === 'NEW'
                              ? 'bg-emerald-100 text-emerald-800 dark:bg-emerald-900/40 dark:text-emerald-400'
                              : 'bg-amber-100 text-amber-800 dark:bg-amber-900/40 dark:text-amber-400'
                          }`}>
                            {unit.condition === 'NEW' ? 'New Pin Pack' : 'Used'}
                          </span>
                        )}
                      </div>

                      {/* IMEI 1 & 2 */}
                      <div className="flex flex-wrap items-center gap-2 text-xs font-mono">
                        {unit.imei1 && (
                          <div className="flex items-center gap-1 bg-emerald-50 dark:bg-emerald-950/40 px-2.5 py-1 rounded-lg border border-emerald-200 dark:border-emerald-800/50">
                            <span className="text-emerald-700 dark:text-emerald-400 font-sans font-semibold text-[10px]">IMEI 1:</span>
                            <span className="font-extrabold text-emerald-900 dark:text-emerald-200 tracking-wider">
                              {unit.imei1}
                            </span>
                          </div>
                        )}

                        {unit.imei2 && (
                          <div className="flex items-center gap-1 bg-slate-100 dark:bg-slate-800/80 px-2.5 py-1 rounded-lg border border-slate-200 dark:border-slate-700">
                            <span className="text-slate-500 font-sans font-semibold text-[10px]">IMEI 2:</span>
                            <span className="font-medium text-slate-700 dark:text-slate-300 tracking-wider">
                              {unit.imei2}
                            </span>
                          </div>
                        )}
                      </div>
                    </div>

                    {/* Action Button */}
                    <div className="shrink-0 flex items-center">
                      {isInCart ? (
                        <div className="px-3 py-1.5 rounded-xl bg-blue-100 text-blue-800 dark:bg-blue-900/50 dark:text-blue-300 font-bold text-xs flex items-center gap-1">
                          <Check className="w-3.5 h-3.5" />
                          <span>Added in Cart</span>
                        </div>
                      ) : (
                        <button
                          type="button"
                          onClick={() => {
                            onSelectUnit(product, unit);
                            onClose();
                          }}
                          className="w-full sm:w-auto px-4 py-2 rounded-xl bg-emerald-600 hover:bg-emerald-700 text-white font-bold text-xs flex items-center justify-center gap-1.5 transition-all shadow-md shadow-emerald-600/20 cursor-pointer"
                        >
                          <Plus className="w-4 h-4" />
                          <span>Select & Add to Bill</span>
                        </button>
                      )}
                    </div>

                  </div>
                </div>
              );
            })
          )}
        </div>

        {/* Footer */}
        <div className={`p-3.5 border-t flex justify-between items-center shrink-0 ${isLight ? 'bg-slate-50 border-slate-200' : 'bg-slate-950/50 border-slate-800'}`}>
          <span className="text-xs text-slate-500">
            بل پر منتخب شدہ فون کا IMEI خود بخود پرنٹ ہو گا۔
          </span>
          <button
            onClick={onClose}
            className="px-4 py-1.5 rounded-xl bg-slate-600 hover:bg-slate-700 text-white text-xs font-semibold cursor-pointer"
          >
            Cancel
          </button>
        </div>

      </div>

      <BarcodeScannerModal
        isOpen={isScannerOpen}
        onClose={() => setIsScannerOpen(false)}
        onScanSuccess={handleScanSuccess}
      />
    </div>
  );
};
