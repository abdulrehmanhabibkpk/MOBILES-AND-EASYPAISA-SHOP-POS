import React, { useState } from 'react';
import { X, Smartphone, CheckCircle2, Clock, Copy, Search, Shield, Filter, Sparkles, Hash } from 'lucide-react';
import { Product, ProductUnitItem, AppSettings } from '../types';

interface UnitDetailsModalProps {
  isOpen: boolean;
  onClose: () => void;
  product: Product | null;
  settings: AppSettings;
}

export const UnitDetailsModal: React.FC<UnitDetailsModalProps> = ({
  isOpen,
  onClose,
  product,
  settings,
}) => {
  const [searchTerm, setSearchTerm] = useState('');
  const [copiedText, setCopiedText] = useState<string | null>(null);
  const [filterStatus, setFilterStatus] = useState<'ALL' | 'AVAILABLE' | 'SOLD'>('ALL');

  if (!isOpen || !product) return null;

  const isLight = settings.theme === 'light';
  const units: ProductUnitItem[] = product.units && product.units.length > 0 
    ? product.units 
    : product.imeiOrSerial 
    ? [{
        id: 'unit-legacy-1',
        imei1: product.imeiOrSerial,
        color: product.color,
        storageRam: product.ramStorage,
        condition: product.condition,
        status: product.stock > 0 ? 'AVAILABLE' : 'SOLD'
      }]
    : [];

  const handleCopy = (text: string) => {
    navigator.clipboard.writeText(text);
    setCopiedText(text);
    setTimeout(() => setCopiedText(null), 2000);
  };

  const filteredUnits = units.filter((u) => {
    const matchesSearch =
      (u.imei1 && u.imei1.toLowerCase().includes(searchTerm.toLowerCase())) ||
      (u.imei2 && u.imei2.toLowerCase().includes(searchTerm.toLowerCase())) ||
      (u.color && u.color.toLowerCase().includes(searchTerm.toLowerCase())) ||
      (u.serialNo && u.serialNo.toLowerCase().includes(searchTerm.toLowerCase())) ||
      (u.soldInvoiceNo && u.soldInvoiceNo.toLowerCase().includes(searchTerm.toLowerCase()));

    const matchesStatus = filterStatus === 'ALL' || u.status === filterStatus;
    return matchesSearch && matchesStatus;
  });

  const availableCount = units.filter((u) => u.status === 'AVAILABLE').length;
  const soldCount = units.filter((u) => u.status === 'SOLD').length;

  return (
    <div className="fixed inset-0 z-50 flex items-center justify-center bg-slate-950/80 backdrop-blur-sm p-3 sm:p-4 overflow-y-auto">
      <div className={`w-full max-w-2xl rounded-2xl shadow-2xl border overflow-hidden my-auto max-h-[90vh] flex flex-col ${
        isLight ? 'bg-white border-slate-200 text-slate-800' : 'bg-slate-900 border-slate-700 text-slate-100'
      }`}>
        
        {/* Header */}
        <div className="bg-gradient-to-r from-blue-700 via-indigo-700 to-blue-800 px-5 py-4 text-white flex items-center justify-between shrink-0">
          <div className="flex items-center gap-3">
            <div className="w-10 h-10 rounded-xl bg-white/10 flex items-center justify-center text-white font-bold">
              <Smartphone className="w-5 h-5" />
            </div>
            <div>
              <h3 className="font-extrabold text-base sm:text-lg leading-tight">
                {product.name}
              </h3>
              <p className="text-xs text-blue-200">
                انفرادی فونز اور IMEIs کی لائیو لسٹ (Total Units: {units.length})
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

        {/* Stats & Search Filter Bar */}
        <div className={`p-4 border-b space-y-3 shrink-0 ${isLight ? 'bg-slate-50 border-slate-200' : 'bg-slate-950/50 border-slate-800'}`}>
          <div className="flex flex-wrap items-center justify-between gap-2">
            <div className="flex items-center gap-2">
              <button
                onClick={() => setFilterStatus('ALL')}
                className={`px-3 py-1.5 rounded-xl text-xs font-bold transition-all cursor-pointer ${
                  filterStatus === 'ALL'
                    ? 'bg-blue-600 text-white shadow-sm'
                    : isLight ? 'bg-white border border-slate-200 text-slate-700' : 'bg-slate-800 text-slate-300'
                }`}
              >
                All Units ({units.length})
              </button>
              <button
                onClick={() => setFilterStatus('AVAILABLE')}
                className={`px-3 py-1.5 rounded-xl text-xs font-bold transition-all flex items-center gap-1.5 cursor-pointer ${
                  filterStatus === 'AVAILABLE'
                    ? 'bg-emerald-600 text-white shadow-sm'
                    : isLight ? 'bg-white border border-slate-200 text-emerald-700' : 'bg-slate-800 text-emerald-400'
                }`}
              >
                <CheckCircle2 className="w-3.5 h-3.5" />
                <span>Available ({availableCount})</span>
              </button>
              <button
                onClick={() => setFilterStatus('SOLD')}
                className={`px-3 py-1.5 rounded-xl text-xs font-bold transition-all flex items-center gap-1.5 cursor-pointer ${
                  filterStatus === 'SOLD'
                    ? 'bg-slate-700 text-white shadow-sm'
                    : isLight ? 'bg-white border border-slate-200 text-slate-600' : 'bg-slate-800 text-slate-400'
                }`}
              >
                <Clock className="w-3.5 h-3.5" />
                <span>Sold Out ({soldCount})</span>
              </button>
            </div>

            {product.salePrice > 0 && (
              <div className="text-xs font-extrabold text-blue-700 dark:text-blue-400">
                Rate: Rs. {product.salePrice.toLocaleString()}
              </div>
            )}
          </div>

          <div className="relative">
            <Search className="w-4 h-4 absolute left-3 top-3 text-slate-400" />
            <input
              type="text"
              placeholder="IMEI 1, IMEI 2, Color, ya Invoice Number se search karein..."
              value={searchTerm}
              onChange={(e) => setSearchTerm(e.target.value)}
              className={`w-full pl-9 pr-4 py-2 rounded-xl text-xs border focus:outline-none focus:ring-2 focus:ring-blue-500 ${
                isLight ? 'bg-white border-slate-200 text-slate-800' : 'bg-slate-800 border-slate-700 text-slate-100'
              }`}
            />
          </div>
        </div>

        {/* Units List */}
        <div className="p-4 overflow-y-auto space-y-2.5 flex-1">
          {filteredUnits.length === 0 ? (
            <div className="p-8 text-center text-slate-400">
              <Hash className="w-10 h-10 mx-auto opacity-40 mb-2" />
              <p className="text-xs font-bold">Koi unit ya IMEI nahi mila</p>
              {units.length === 0 && (
                <p className="text-[11px] text-slate-500 mt-1">
                  اس پروڈکٹ کے لیے ابھی تک کوئی انفرادی IMEI رجسٹر نہیں کیا گیا۔ آپ ایڈٹ کر کے IMEI درج کر سکتے ہیں۔
                </p>
              )}
            </div>
          ) : (
            filteredUnits.map((unit, index) => {
              const isAvailable = unit.status === 'AVAILABLE';

              return (
                <div
                  key={unit.id || index}
                  className={`p-3.5 rounded-xl border transition-all ${
                    isAvailable
                      ? isLight
                        ? 'bg-white border-slate-200 shadow-sm hover:border-blue-400'
                        : 'bg-slate-800/80 border-slate-700 hover:border-blue-500'
                      : isLight
                      ? 'bg-slate-100/70 border-slate-200 opacity-75'
                      : 'bg-slate-900/60 border-slate-800 opacity-70'
                  }`}
                >
                  <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-3">
                    
                    {/* Left details */}
                    <div className="space-y-1">
                      <div className="flex items-center gap-2 flex-wrap">
                        <span className="px-2 py-0.5 rounded-md bg-blue-100 text-blue-800 dark:bg-blue-900/50 dark:text-blue-300 font-extrabold text-[11px]">
                          Unit #{index + 1}
                        </span>

                        {unit.color && (
                          <span className="px-2 py-0.5 rounded-md bg-purple-100 text-purple-800 dark:bg-purple-900/50 dark:text-purple-300 font-bold text-[11px] flex items-center gap-1">
                            <span className="w-2 h-2 rounded-full bg-purple-500 inline-block" />
                            <span>{unit.color}</span>
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
                            {unit.condition === 'NEW' ? 'Pin Pack (New)' : 'Used (پرانا)'}
                          </span>
                        )}

                        <span className={`px-2 py-0.5 rounded-md font-bold text-[10px] flex items-center gap-1 ${
                          isAvailable
                            ? 'bg-emerald-500/10 text-emerald-600 border border-emerald-500/20'
                            : 'bg-rose-500/10 text-rose-600 border border-rose-500/20'
                        }`}>
                          {isAvailable ? <CheckCircle2 className="w-3 h-3" /> : <Clock className="w-3 h-3" />}
                          <span>{isAvailable ? 'موجود ہے (Available)' : 'فروخت شدہ (SOLD)'}</span>
                        </span>
                      </div>

                      {/* IMEI Numbers */}
                      <div className="flex flex-wrap items-center gap-3 pt-1 text-xs">
                        {unit.imei1 && (
                          <div className="flex items-center gap-1.5 font-mono">
                            <span className="text-slate-400 font-sans text-[11px]">IMEI 1:</span>
                            <span className="font-extrabold text-blue-700 dark:text-blue-400 bg-blue-50 dark:bg-blue-950/50 px-2 py-0.5 rounded border border-blue-200 dark:border-blue-900/50">
                              {unit.imei1}
                            </span>
                            <button
                              onClick={() => handleCopy(unit.imei1 || '')}
                              className="p-1 text-slate-400 hover:text-blue-600 transition-colors"
                              title="Copy IMEI 1"
                            >
                              <Copy className="w-3 h-3" />
                            </button>
                          </div>
                        )}

                        {unit.imei2 && (
                          <div className="flex items-center gap-1.5 font-mono">
                            <span className="text-slate-400 font-sans text-[11px]">IMEI 2:</span>
                            <span className="font-bold text-slate-700 dark:text-slate-300 bg-slate-100 dark:bg-slate-800 px-2 py-0.5 rounded border border-slate-200 dark:border-slate-700">
                              {unit.imei2}
                            </span>
                            <button
                              onClick={() => handleCopy(unit.imei2 || '')}
                              className="p-1 text-slate-400 hover:text-blue-600 transition-colors"
                              title="Copy IMEI 2"
                            >
                              <Copy className="w-3 h-3" />
                            </button>
                          </div>
                        )}

                        {unit.serialNo && (
                          <div className="flex items-center gap-1.5 font-mono text-[11px]">
                            <span className="text-slate-400">SN:</span>
                            <span className="text-slate-600 dark:text-slate-400">{unit.serialNo}</span>
                          </div>
                        )}
                      </div>
                    </div>

                    {/* Right side info (Sold info if sold) */}
                    {!isAvailable && unit.soldInvoiceNo && (
                      <div className="text-right text-[11px] font-sans shrink-0 border-t sm:border-t-0 sm:border-l pl-0 sm:pl-3 pt-2 sm:pt-0 border-slate-200 dark:border-slate-700">
                        <div className="text-slate-400 font-semibold">Sold on Bill:</div>
                        <div className="font-mono font-bold text-blue-600">{unit.soldInvoiceNo}</div>
                        {unit.soldDate && <div className="text-slate-400 text-[10px]">{unit.soldDate}</div>}
                      </div>
                    )}

                  </div>
                </div>
              );
            })
          )}
        </div>

        {/* Copy Feedback */}
        {copiedText && (
          <div className="px-4 py-2 bg-emerald-600 text-white text-xs font-bold text-center shrink-0 animate-fade-in">
            ✓ IMEI "{copiedText}" clipboard par copy ho gaya!
          </div>
        )}

        {/* Footer */}
        <div className={`p-4 border-t flex justify-end shrink-0 ${isLight ? 'bg-slate-50 border-slate-200' : 'bg-slate-950/50 border-slate-800'}`}>
          <button
            onClick={onClose}
            className="px-5 py-2 rounded-xl bg-slate-700 hover:bg-slate-800 text-white text-xs font-bold transition-all cursor-pointer"
          >
            Close (بند کریں)
          </button>
        </div>

      </div>
    </div>
  );
};
