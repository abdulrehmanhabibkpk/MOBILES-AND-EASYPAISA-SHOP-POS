import React, { useState } from 'react';
import { X, Wallet, CheckCircle2 } from 'lucide-react';
import { DailyBalance } from '../types';

interface OpeningBalanceModalProps {
  isOpen: boolean;
  onClose: () => void;
  onSave: (balance: DailyBalance) => void;
  currentOpening: DailyBalance;
}

export const OpeningBalanceModal: React.FC<OpeningBalanceModalProps> = ({
  isOpen,
  onClose,
  onSave,
  currentOpening,
}) => {
  const [openingCash, setOpeningCash] = useState<number | ''>(currentOpening.openingCash || 0);
  const [openingEasyPaisa, setOpeningEasyPaisa] = useState<number | ''>(currentOpening.openingEasyPaisa || 0);
  const [date] = useState(new Date().toISOString().split('T')[0]);

  if (!isOpen) return null;

  const handleSubmit = (e: React.FormEvent) => {
    e.preventDefault();
    onSave({
      date,
      openingCash: typeof openingCash === 'number' ? openingCash : 0,
      openingEasyPaisa: typeof openingEasyPaisa === 'number' ? openingEasyPaisa : 0,
    });
    onClose();
  };

  return (
    <div className="fixed inset-0 z-50 flex items-center justify-center bg-slate-950/80 backdrop-blur-sm p-2 sm:p-4 overflow-y-auto">
      <div className="w-full max-w-md bg-slate-900 border border-slate-700 rounded-2xl shadow-2xl overflow-hidden text-slate-100 my-auto max-h-[92vh] flex flex-col">
        
        {/* Header */}
        <div className="bg-slate-800/90 px-4 sm:px-6 py-3.5 sm:py-4 border-b border-slate-700 flex items-center justify-between shrink-0">
          <div className="flex items-center gap-2">
            <div className="w-8 h-8 rounded-lg bg-teal-500/20 text-teal-400 flex items-center justify-center font-bold">
              <Wallet className="w-5 h-5" />
            </div>
            <div>
              <h3 className="font-bold text-white text-sm sm:text-base">Opening Cash & EasyPaisa Balance</h3>
              <p className="text-[11px] sm:text-xs text-slate-400">Set morning starting capital ({date})</p>
            </div>
          </div>
          <button
            onClick={onClose}
            className="w-8 h-8 rounded-lg bg-slate-700 hover:bg-slate-600 text-slate-300 flex items-center justify-center transition-colors"
          >
            <X className="w-5 h-5" />
          </button>
        </div>

        {/* Form */}
        <form onSubmit={handleSubmit} className="p-4 sm:p-6 space-y-3.5 sm:space-y-4 overflow-y-auto">
          <div>
            <label className="block text-xs font-semibold text-slate-300 mb-1">
              Opening Cash in Hand (Subah Ka Naqad Cash):
            </label>
            <input
              type="number"
              min="0"
              placeholder="e.g. 50000"
              value={openingCash}
              onChange={(e) => setOpeningCash(e.target.value === '' ? '' : parseFloat(e.target.value))}
              className="w-full px-3 py-2.5 bg-slate-800 border border-slate-700 rounded-xl text-emerald-400 font-mono text-lg font-bold outline-none focus:border-teal-500"
            />
          </div>

          <div>
            <label className="block text-xs font-semibold text-slate-300 mb-1">
              Opening EasyPaisa Account Balance (Subah Ka EP Balance):
            </label>
            <input
              type="number"
              min="0"
              placeholder="e.g. 100000"
              value={openingEasyPaisa}
              onChange={(e) => setOpeningEasyPaisa(e.target.value === '' ? '' : parseFloat(e.target.value))}
              className="w-full px-3 py-2.5 bg-slate-800 border border-slate-700 rounded-xl text-teal-300 font-mono text-lg font-bold outline-none focus:border-teal-500"
            />
          </div>

          <div className="pt-2 flex items-center justify-end gap-3 border-t border-slate-800">
            <button
              type="button"
              onClick={onClose}
              className="px-4 py-2 rounded-xl bg-slate-800 hover:bg-slate-700 text-slate-300 font-semibold text-sm"
            >
              Cancel
            </button>
            <button
              type="submit"
              className="px-5 py-2 rounded-xl bg-teal-500 hover:bg-teal-600 text-slate-950 font-bold text-sm shadow-lg shadow-teal-500/20 flex items-center gap-2 cursor-pointer"
            >
              <CheckCircle2 className="w-4 h-4" />
              <span>Save Opening Balance</span>
            </button>
          </div>
        </form>

      </div>
    </div>
  );
};
