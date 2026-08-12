import React, { useState } from 'react';
import { X, TrendingDown, CheckCircle2, CreditCard } from 'lucide-react';
import { Transaction, PaymentMethod, AppSettings } from '../types';
import { PAYMENT_CHANNELS, getPaymentChannelInfo } from '../lib/paymentChannels';

interface ExpenseModalProps {
  isOpen: boolean;
  onClose: () => void;
  onSave: (expense: Omit<Transaction, 'id' | 'createdAt'>) => void;
  settings?: AppSettings;
}

export const ExpenseModal: React.FC<ExpenseModalProps> = ({ isOpen, onClose, onSave, settings }) => {
  const isLight = settings?.theme === 'light';
  const isEn = settings?.language === 'en';

  const [expenseAmount, setExpenseAmount] = useState<number | ''>('');
  const [category, setCategory] = useState<'TEA_FOOD' | 'RENT' | 'ELECTRICITY' | 'TAX_LOAD' | 'SHORTAGE_LOSS' | 'OTHER'>('TEA_FOOD');
  const [paymentMethod, setPaymentMethod] = useState<PaymentMethod>('CASH');
  const [notes, setNotes] = useState('');
  const [date] = useState(new Date().toISOString().split('T')[0]);

  if (!isOpen) return null;

  const activeChannel = getPaymentChannelInfo(paymentMethod);

  const handleSubmit = (e: React.FormEvent) => {
    e.preventDefault();
    const amt = typeof expenseAmount === 'number' ? expenseAmount : 0;
    if (amt <= 0) return;

    const categoryNames = {
      TEA_FOOD: isEn ? 'Chai / Food Expense' : 'چائے اور کھانا کا خرچہ',
      RENT: isEn ? 'Shop Rent' : 'دوکان کا کرایہ',
      ELECTRICITY: isEn ? 'Electricity / Utilities' : 'بجلی یا انٹرنیٹ بل',
      TAX_LOAD: isEn ? 'Load & Service Tax' : 'موبائل لوڈ فیس یا ٹیکس',
      SHORTAGE_LOSS: isEn ? 'Cash Shortage / Loss' : 'کیش نقصان یا شارٹیج',
      OTHER: isEn ? 'Other Shop Expense' : 'دیگر دوکان کا خرچہ',
    };

    onSave({
      date,
      time: new Date().toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' }),
      type: 'EXPENSE',
      customerName: categoryNames[category],
      customerPhone: '',
      trxId: `EXP-${Date.now().toString().slice(-6)}`,
      easyPaisaAmount: 0,
      cashAmount: 0,
      feeProfit: 0,
      expenseAmount: amt,
      paymentMethod: paymentMethod,
      notes: notes.trim() || categoryNames[category],
    });

    setExpenseAmount('');
    setNotes('');
    setPaymentMethod('CASH');
    onClose();
  };

  return (
    <div className="fixed inset-0 z-50 flex items-center justify-center bg-slate-950/75 backdrop-blur-sm p-2 sm:p-4 overflow-y-auto animate-fade-in">
      <div className={`w-full max-w-md rounded-2xl shadow-2xl overflow-hidden border my-auto max-h-[92vh] flex flex-col transition-colors ${
        isLight
          ? 'bg-white border-slate-200 text-slate-900 shadow-slate-300/50'
          : 'bg-slate-900 border-slate-800 text-slate-100 ring-1 ring-white/10'
      }`}>
        
        {/* Header */}
        <div className={`px-4 sm:px-6 py-4 border-b flex items-center justify-between shrink-0 ${
          isLight
            ? 'bg-gradient-to-r from-rose-700 to-red-900 text-white border-rose-800'
            : 'bg-gradient-to-r from-rose-950 via-slate-900 to-slate-950 text-white border-slate-800'
        }`}>
          <div className="flex items-center gap-3">
            <div className="w-9 h-9 rounded-xl bg-rose-500/20 text-rose-300 flex items-center justify-center font-black border border-rose-400/30">
              <TrendingDown className="w-5 h-5" />
            </div>
            <div>
              <h3 className="font-extrabold text-white text-sm sm:text-base">
                {isEn ? 'Add Shop Expense (خرچہ کا اندراج)' : 'دوکان کا خرچہ اندراج کریں (Shop Expense)'}
              </h3>
              <p className="text-[11px] sm:text-xs text-rose-200/80">
                {isEn ? 'Record daily shop costs or cash discrepancies' : 'روزمرہ چائے، کرایہ اور دیگر اخراجات درج کریں'}
              </p>
            </div>
          </div>
          <button
            onClick={onClose}
            className="w-8 h-8 rounded-xl bg-white/10 hover:bg-white/20 text-white flex items-center justify-center transition-colors cursor-pointer"
          >
            <X className="w-5 h-5" />
          </button>
        </div>

        {/* Form */}
        <form onSubmit={handleSubmit} className="p-4 sm:p-6 space-y-4 overflow-y-auto">
          <div>
            <label className={`block text-xs font-bold mb-1 ${isLight ? 'text-slate-700' : 'text-slate-300'}`}>
              {isEn ? 'Expense Category:' : 'اخراجات کی قسم:'}
            </label>
            <select
              value={category}
              onChange={(e) => setCategory(e.target.value as any)}
              className={`w-full px-3 py-2.5 rounded-xl text-xs sm:text-sm font-semibold outline-none border cursor-pointer ${
                isLight
                  ? 'bg-slate-50 border-slate-300 text-slate-900 focus:bg-white focus:border-rose-600'
                  : 'bg-slate-800 border-slate-700 text-white focus:border-rose-500'
              }`}
            >
              <option value="TEA_FOOD">☕ Chai, Coffee & Refreshments (چائے / کھانا)</option>
              <option value="RENT">🏪 Shop Rent (دوکان کرایہ)</option>
              <option value="ELECTRICITY">⚡ Electricity & Utility Bill (بجلی/انٹرنیٹ بل)</option>
              <option value="TAX_LOAD">📱 Mobile Load & Bank Charges (لوڈ فیس/ٹیکس)</option>
              <option value="SHORTAGE_LOSS">⚠️ Cash Shortage / Loss (کیش نقصان یا کمی)</option>
              <option value="OTHER">📁 Other Shop Expenses (دیگر متفرق اخراجات)</option>
            </select>
          </div>

          {/* Payment Source / Account Channel */}
          <div className={`p-3 rounded-xl border space-y-2 ${
            isLight ? 'bg-rose-50/50 border-rose-200' : 'bg-slate-800/70 border-slate-750'
          }`}>
            <div className="flex items-center justify-between">
              <label className={`text-xs font-bold flex items-center gap-1.5 ${isLight ? 'text-slate-800' : 'text-slate-200'}`}>
                <CreditCard className="w-4 h-4 text-rose-500" />
                <span>{isEn ? 'Paid From Account/Channel:' : 'ادائیگی کس ذریعے سے کی گئی:'}</span>
              </label>
              <span className={`px-2 py-0.5 rounded-full text-[11px] font-bold border ${activeChannel.badgeBg}`}>
                {activeChannel.emoji} {activeChannel.nameEn}
              </span>
            </div>

            <select
              value={paymentMethod}
              onChange={(e) => setPaymentMethod(e.target.value as PaymentMethod)}
              className={`w-full px-3 py-2 rounded-xl text-xs sm:text-sm font-semibold outline-none cursor-pointer border ${
                isLight
                  ? 'bg-white border-slate-300 text-slate-900 focus:border-rose-600'
                  : 'bg-slate-900 border-slate-700 text-white focus:border-rose-500'
              }`}
            >
              <optgroup label="💵 Cash / Digital Accounts">
                {PAYMENT_CHANNELS.map(c => (
                  <option key={c.id} value={c.id}>
                    {c.emoji} {c.nameEn} ({c.nameUrdu})
                  </option>
                ))}
              </optgroup>
            </select>
          </div>

          <div>
            <label className={`block text-xs font-bold mb-1 ${isLight ? 'text-rose-700' : 'text-rose-400'}`}>
              {isEn ? 'Expense Amount (Rs.): *' : 'اخراجات کی رقم (Rs.): *'}
            </label>
            <input
              type="number"
              required
              min="1"
              placeholder="e.g. 250"
              value={expenseAmount}
              onChange={(e) => setExpenseAmount(e.target.value === '' ? '' : parseFloat(e.target.value))}
              className={`w-full px-3 py-2.5 rounded-xl font-mono text-lg font-black outline-none border ${
                isLight
                  ? 'bg-rose-50/60 border-rose-300 text-rose-900 focus:bg-white focus:border-rose-600'
                  : 'bg-slate-800 border-rose-500/50 text-rose-300 focus:border-rose-400'
              }`}
            />
          </div>

          <div>
            <label className={`block text-xs font-bold mb-1 ${isLight ? 'text-slate-700' : 'text-slate-300'}`}>
              {isEn ? 'Description / Detail:' : 'تفصیل (اختیازی):'}
            </label>
            <input
              type="text"
              placeholder="e.g. Afternoon tea & biscuits"
              value={notes}
              onChange={(e) => setNotes(e.target.value)}
              className={`w-full px-3 py-2.5 rounded-xl text-sm outline-none border ${
                isLight
                  ? 'bg-slate-50 border-slate-300 text-slate-900 focus:bg-white focus:border-rose-600'
                  : 'bg-slate-800 border-slate-700 text-white focus:border-rose-500'
              }`}
            />
          </div>

          <div className={`pt-3 flex items-center justify-end gap-3 border-t ${isLight ? 'border-slate-200' : 'border-slate-800'}`}>
            <button
              type="button"
              onClick={onClose}
              className={`px-4 py-2.5 rounded-xl font-bold text-xs sm:text-sm cursor-pointer border ${
                isLight
                  ? 'bg-slate-100 border-slate-300 text-slate-700 hover:bg-slate-200'
                  : 'bg-slate-800 border-slate-700 text-slate-300 hover:bg-slate-700'
              }`}
            >
              {isEn ? 'Cancel' : 'منسوخ کریں'}
            </button>
            <button
              type="submit"
              className="px-6 py-2.5 rounded-xl bg-rose-600 hover:bg-rose-700 text-white font-black text-xs sm:text-sm shadow-md flex items-center gap-2 cursor-pointer active:scale-95 transition-all"
            >
              <CheckCircle2 className="w-4 h-4" />
              <span>{isEn ? 'Save Expense' : 'اخراجات محفوظ کریں'}</span>
            </button>
          </div>
        </form>

      </div>
    </div>
  );
};

