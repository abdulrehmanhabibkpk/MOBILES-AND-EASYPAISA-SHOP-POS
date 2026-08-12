import React, { useState } from 'react';
import { X, Calculator, DollarSign, Check, RotateCcw, Copy, Share2 } from 'lucide-react';

interface CashCalculatorModalProps {
  isOpen: boolean;
  onClose: () => void;
  isLight?: boolean;
}

export const CashCalculatorModal: React.FC<CashCalculatorModalProps> = ({
  isOpen,
  onClose,
  isLight = false,
}) => {
  const [notes, setNotes] = useState<Record<number, number>>({
    5000: 0,
    1000: 0,
    500: 0,
    100: 0,
    50: 0,
    20: 0,
    10: 0,
  });

  const [copied, setCopied] = useState(false);

  if (!isOpen) return null;

  const handleNoteChange = (denom: number, countStr: string) => {
    const val = parseInt(countStr, 10);
    setNotes((prev) => ({
      ...prev,
      [denom]: isNaN(val) || val < 0 ? 0 : val,
    }));
  };

  const handleReset = () => {
    setNotes({
      5000: 0,
      1000: 0,
      500: 0,
      100: 0,
      50: 0,
      20: 0,
      10: 0,
    });
  };

  const denominations = [5000, 1000, 500, 100, 50, 20, 10];

  const totalCash = denominations.reduce((sum, d) => sum + d * (notes[d] || 0), 0);
  const totalNotesCount = denominations.reduce((sum, d) => sum + (notes[d] || 0), 0);

  const getSummaryText = () => {
    const lines = denominations
      .filter((d) => notes[d] > 0)
      .map((d) => `Rs.${d} x ${notes[d]} = Rs.${(d * notes[d]).toLocaleString()}`);
    return `💵 *دکان کیش کلوزنگ کیلیکولیٹر (Mobiles and EasyPaisa Shop POS)*\n-------------------------\n${lines.join('\n')}\n-------------------------\n*کل نقد کیش (Total Cash): Rs. ${totalCash.toLocaleString()}*\n*کل نوٹس (Total Notes): ${totalNotesCount}*`;
  };

  const handleCopy = () => {
    navigator.clipboard.writeText(getSummaryText());
    setCopied(true);
    setTimeout(() => setCopied(false), 2000);
  };

  const handleWhatsAppShare = () => {
    const text = encodeURIComponent(getSummaryText());
    window.open(`https://wa.me/?text=${text}`, '_blank');
  };

  return (
    <div className="fixed inset-0 z-50 flex items-center justify-center p-3 sm:p-4 bg-slate-950/80 backdrop-blur-sm animate-fade-in">
      <div className={`w-full max-w-md rounded-2xl shadow-2xl border overflow-hidden flex flex-col max-h-[90vh] ${
        isLight ? 'bg-white border-slate-200 text-slate-900' : 'bg-slate-900 border-slate-800 text-slate-100'
      }`}>
        {/* Header */}
        <div className="p-4 bg-gradient-to-r from-emerald-600 to-teal-600 text-white flex items-center justify-between shrink-0">
          <div className="flex items-center gap-2.5">
            <div className="p-2 rounded-xl bg-white/20 backdrop-blur-md">
              <Calculator className="w-5 h-5 text-white" />
            </div>
            <div>
              <h3 className="font-extrabold text-sm sm:text-base leading-tight">
                روزانہ نقد گنتی کیلیکولیٹر (Cash Counter)
              </h3>
              <p className="text-[11px] text-emerald-100">
                Shop Closing Daily Cash Notes Calculator
              </p>
            </div>
          </div>
          <button
            onClick={onClose}
            className="p-1.5 rounded-lg hover:bg-white/20 text-white transition-colors cursor-pointer"
          >
            <X className="w-5 h-5" />
          </button>
        </div>

        {/* Content Body */}
        <div className="p-4 overflow-y-auto space-y-3 flex-1">
          <div className="grid grid-cols-1 gap-2">
            {denominations.map((denom) => {
              const subtotal = denom * (notes[denom] || 0);
              return (
                <div
                  key={denom}
                  className={`p-2.5 rounded-xl border flex items-center justify-between gap-3 ${
                    isLight ? 'bg-slate-50 border-slate-200' : 'bg-slate-800/60 border-slate-800'
                  }`}
                >
                  <div className="flex items-center gap-2 w-28 shrink-0">
                    <span className="px-2 py-1 rounded-lg bg-emerald-500/10 text-emerald-600 dark:text-emerald-400 font-extrabold text-xs font-mono">
                      Rs. {denom}
                    </span>
                  </div>

                  <div className="flex items-center gap-2 flex-1">
                    <span className="text-xs font-semibold text-slate-400">x</span>
                    <input
                      type="number"
                      min="0"
                      placeholder="0"
                      value={notes[denom] || ''}
                      onChange={(e) => handleNoteChange(denom, e.target.value)}
                      className={`w-full px-3 py-1.5 rounded-lg border text-sm font-bold text-center outline-none transition-all ${
                        isLight
                          ? 'bg-white border-slate-300 focus:border-emerald-500 text-slate-900'
                          : 'bg-slate-900 border-slate-700 focus:border-emerald-500 text-white'
                      }`}
                    />
                  </div>

                  <div className="w-28 text-right font-bold text-xs font-mono text-emerald-600 dark:text-emerald-400 shrink-0">
                    Rs. {subtotal.toLocaleString()}
                  </div>
                </div>
              );
            })}
          </div>
        </div>

        {/* Total & Action Footer */}
        <div className={`p-4 border-t shrink-0 space-y-3 ${
          isLight ? 'bg-slate-50 border-slate-200' : 'bg-slate-950 border-slate-800'
        }`}>
          <div className="p-3 rounded-xl bg-emerald-600 text-white flex items-center justify-between shadow-md">
            <div>
              <p className="text-[11px] font-medium text-emerald-100">کل نقد رقم (Total Cash Amount)</p>
              <p className="text-xl font-black font-mono">Rs. {totalCash.toLocaleString()}</p>
            </div>
            <div className="text-right">
              <p className="text-[11px] font-medium text-emerald-100">کل نوٹ (Total Notes)</p>
              <p className="text-lg font-bold font-mono">{totalNotesCount} Pcs</p>
            </div>
          </div>

          <div className="flex items-center gap-2">
            <button
              onClick={handleReset}
              className="px-3 py-2 rounded-xl border border-slate-300 dark:border-slate-700 hover:bg-slate-200 dark:hover:bg-slate-800 text-xs font-bold flex items-center gap-1.5 transition-colors cursor-pointer"
            >
              <RotateCcw className="w-4 h-4" />
              <span>Reset</span>
            </button>

            <button
              onClick={handleCopy}
              className="flex-1 py-2 px-3 rounded-xl bg-slate-800 hover:bg-slate-700 text-white text-xs font-bold flex items-center justify-center gap-1.5 transition-colors cursor-pointer"
            >
              {copied ? <Check className="w-4 h-4 text-emerald-400" /> : <Copy className="w-4 h-4" />}
              <span>{copied ? 'Copied!' : 'Copy Summary'}</span>
            </button>

            <button
              onClick={handleWhatsAppShare}
              className="flex-1 py-2 px-3 rounded-xl bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-bold flex items-center justify-center gap-1.5 transition-colors cursor-pointer shadow-sm"
            >
              <Share2 className="w-4 h-4" />
              <span>WhatsApp Share</span>
            </button>
          </div>
        </div>
      </div>
    </div>
  );
};
