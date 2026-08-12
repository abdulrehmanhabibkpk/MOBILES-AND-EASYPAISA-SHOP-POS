import React, { useState, useEffect } from 'react';
import { X, ArrowDownRight, ArrowUpRight, DollarSign, User, Phone, Hash, FileText, CheckCircle2, CreditCard } from 'lucide-react';
import { Transaction, TransactionType, PaymentMethod, AppSettings } from '../types';
import { PAYMENT_CHANNELS, getPaymentChannelInfo } from '../lib/paymentChannels';

interface TransactionModalProps {
  isOpen: boolean;
  onClose: () => void;
  onSave: (transaction: Omit<Transaction, 'id' | 'createdAt'>) => void;
  editingTransaction?: Transaction | null;
  settings?: AppSettings;
}

export const TransactionModal: React.FC<TransactionModalProps> = ({
  isOpen,
  onClose,
  onSave,
  editingTransaction,
  settings,
}) => {
  const isLight = settings?.theme === 'light';
  const isEn = settings?.language === 'en';

  const [type, setType] = useState<TransactionType>('BUY_EASYPAISA');
  const [customerName, setCustomerName] = useState('');
  const [customerPhone, setCustomerPhone] = useState('');
  const [trxId, setTrxId] = useState('');
  const [easyPaisaAmount, setEasyPaisaAmount] = useState<number | ''>('');
  const [cashAmount, setCashAmount] = useState<number | ''>('');
  const [feeProfit, setFeeProfit] = useState<number | ''>('');
  const [paymentMethod, setPaymentMethod] = useState<PaymentMethod>('EASYPAISA');
  const [notes, setNotes] = useState('');
  const [date, setDate] = useState(new Date().toISOString().split('T')[0]);
  const [time, setTime] = useState(new Date().toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' }));

  useEffect(() => {
    if (editingTransaction) {
      setType(editingTransaction.type);
      setCustomerName(editingTransaction.customerName);
      setCustomerPhone(editingTransaction.customerPhone || '');
      setTrxId(editingTransaction.trxId || '');
      setEasyPaisaAmount(editingTransaction.easyPaisaAmount);
      setCashAmount(editingTransaction.cashAmount);
      setFeeProfit(editingTransaction.feeProfit);
      setPaymentMethod(editingTransaction.paymentMethod || 'EASYPAISA');
      setNotes(editingTransaction.notes || '');
      setDate(editingTransaction.date);
      setTime(editingTransaction.time);
    } else {
      setType('BUY_EASYPAISA');
      setCustomerName('');
      setCustomerPhone('');
      setTrxId('');
      setEasyPaisaAmount('');
      setCashAmount('');
      setFeeProfit('');
      setPaymentMethod('EASYPAISA');
      setNotes('');
      setDate(new Date().toISOString().split('T')[0]);
      setTime(new Date().toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' }));
    }
  }, [editingTransaction, isOpen]);

  // Selected Channel Info
  const activeChannel = getPaymentChannelInfo(paymentMethod);

  // Auto-calculate logic when EasyPaisa amount changes or Fee changes
  const handleEasyPaisaChange = (val: string) => {
    const num = parseFloat(val) || 0;
    setEasyPaisaAmount(val === '' ? '' : num);

    let calculatedFee = Math.max(20, Math.round(num * 0.02)); // 2% or min 20 PKR
    if (num <= 0) calculatedFee = 0;

    if (feeProfit === '' || typeof feeProfit === 'number') {
      setFeeProfit(calculatedFee);
    }

    if (type === 'BUY_EASYPAISA') {
      setCashAmount(num - calculatedFee);
    } else if (type === 'SELL_EASYPAISA') {
      setCashAmount(num + calculatedFee);
    }
  };

  const handleFeeChange = (val: string) => {
    const fee = parseFloat(val) || 0;
    setFeeProfit(val === '' ? '' : fee);

    const ep = typeof easyPaisaAmount === 'number' ? easyPaisaAmount : 0;
    if (type === 'BUY_EASYPAISA') {
      setCashAmount(ep - fee);
    } else if (type === 'SELL_EASYPAISA') {
      setCashAmount(ep + fee);
    }
  };

  const handleSubmit = (e: React.FormEvent) => {
    e.preventDefault();
    const epAmt = typeof easyPaisaAmount === 'number' ? easyPaisaAmount : 0;
    const cAmt = typeof cashAmount === 'number' ? cashAmount : 0;
    const fee = typeof feeProfit === 'number' ? feeProfit : 0;

    onSave({
      date,
      time,
      type,
      customerName: customerName.trim() || 'Walk-in Customer',
      customerPhone: customerPhone.trim(),
      trxId: trxId.trim() || `TRX-${Math.floor(100000 + Math.random() * 900000)}`,
      easyPaisaAmount: epAmt,
      cashAmount: cAmt,
      feeProfit: fee,
      expenseAmount: 0,
      paymentMethod,
      notes,
    });

    onClose();
  };

  if (!isOpen) return null;

  return (
    <div className="fixed inset-0 z-50 flex items-center justify-center bg-slate-950/70 backdrop-blur-sm p-2 sm:p-4 overflow-y-auto animate-fade-in">
      <div className={`w-full max-w-lg rounded-2xl shadow-2xl overflow-hidden border my-auto max-h-[94vh] flex flex-col transition-colors ${
        isLight
          ? 'bg-white border-slate-200 text-slate-900 shadow-slate-300/50'
          : 'bg-slate-900 border-slate-800 text-slate-100 ring-1 ring-white/10'
      }`}>
        
        {/* Modal Header */}
        <div className={`px-4 sm:px-6 py-4 border-b flex items-center justify-between shrink-0 ${
          isLight
            ? 'bg-gradient-to-r from-blue-900 via-indigo-900 to-slate-900 text-white border-blue-800'
            : 'bg-gradient-to-r from-slate-900 via-slate-850 to-slate-900 text-white border-slate-800'
        }`}>
          <div className="flex items-center gap-3">
            <div className="w-9 h-9 rounded-xl bg-emerald-500/20 text-emerald-300 flex items-center justify-center font-black border border-emerald-400/30">
              <DollarSign className="w-5 h-5" />
            </div>
            <div>
              <h3 className="font-black text-white text-sm sm:text-base tracking-tight">
                {editingTransaction 
                  ? (isEn ? 'Edit Transaction Entry' : 'ٹرانزیکشن ایڈٹ کریں')
                  : (isEn ? 'New Buy / Sell Entry' : 'نیا Buy/Sell اندراج (New Entry)')}
              </h3>
              <p className="text-[11px] sm:text-xs text-blue-200/80">
                {isEn ? 'EasyPaisa, JazzCash, SadaPay & Online Bank Exchange' : 'ایزی پیسہ، جاز کیش اور آن لائن بینک ٹرانسفر'}
              </p>
            </div>
          </div>
          <button
            onClick={onClose}
            className="w-8 h-8 rounded-xl bg-white/10 hover:bg-white/20 text-white flex items-center justify-center transition-colors border border-white/20 cursor-pointer"
          >
            <X className="w-5 h-5" />
          </button>
        </div>

        {/* Form Body */}
        <form onSubmit={handleSubmit} className="p-4 sm:p-6 space-y-4 overflow-y-auto">
          
          {/* Transaction Type Selector (BUY vs SELL) */}
          <div>
            <label className={`block text-xs font-bold mb-2 ${isLight ? 'text-slate-700' : 'text-slate-300'}`}>
              {isEn ? '1. Select Transaction Direction:' : '1. ٹرانزیکشن کی قسم منتخب کریں:'}
            </label>
            <div className="grid grid-cols-2 gap-3">
              <button
                type="button"
                onClick={() => {
                  setType('BUY_EASYPAISA');
                  handleEasyPaisaChange(easyPaisaAmount.toString());
                }}
                className={`p-3.5 rounded-xl border flex flex-col items-center gap-1.5 transition-all text-left cursor-pointer ${
                  type === 'BUY_EASYPAISA'
                    ? isLight
                      ? 'border-emerald-600 bg-emerald-50 text-emerald-950 ring-2 ring-emerald-500/40 shadow-sm'
                      : 'border-emerald-500 bg-emerald-500/15 text-emerald-300 ring-2 ring-emerald-500/30'
                    : isLight
                      ? 'border-slate-200 bg-slate-50 text-slate-600 hover:bg-slate-100 hover:border-slate-300'
                      : 'border-slate-800 bg-slate-800/60 text-slate-400 hover:border-slate-700'
                }`}
              >
                <div className="flex items-center gap-2 font-black text-xs sm:text-sm">
                  <ArrowDownRight className="w-4.5 h-4.5 text-emerald-600 dark:text-emerald-400" />
                  <span>BUY (Cash Out)</span>
                </div>
                <span className={`text-[11px] text-center font-medium ${isLight ? 'text-slate-600' : 'text-slate-300'}`}>
                  {isEn ? 'Customer sends EP/Bank 👉 Shop pays CASH' : 'گاہک سے آن لائن لیا 👉 گاہک کو نقد کیش دیا'}
                </span>
              </button>

              <button
                type="button"
                onClick={() => {
                  setType('SELL_EASYPAISA');
                  handleEasyPaisaChange(easyPaisaAmount.toString());
                }}
                className={`p-3.5 rounded-xl border flex flex-col items-center gap-1.5 transition-all text-left cursor-pointer ${
                  type === 'SELL_EASYPAISA'
                    ? isLight
                      ? 'border-amber-600 bg-amber-50 text-amber-950 ring-2 ring-amber-500/40 shadow-sm'
                      : 'border-amber-500 bg-amber-500/15 text-amber-300 ring-2 ring-amber-500/30'
                    : isLight
                      ? 'border-slate-200 bg-slate-50 text-slate-600 hover:bg-slate-100 hover:border-slate-300'
                      : 'border-slate-800 bg-slate-800/60 text-slate-400 hover:border-slate-700'
                }`}
              >
                <div className="flex items-center gap-2 font-black text-xs sm:text-sm">
                  <ArrowUpRight className="w-4.5 h-4.5 text-amber-600 dark:text-amber-400" />
                  <span>SELL (Cash In)</span>
                </div>
                <span className={`text-[11px] text-center font-medium ${isLight ? 'text-slate-600' : 'text-slate-300'}`}>
                  {isEn ? 'Customer pays CASH 👉 Shop sends EP/Bank' : 'گاہک نے نقد کیش دیا 👉 گاہک کو آن لائن بھیجا'}
                </span>
              </button>
            </div>
          </div>

          {/* Payment Method & Bank Selection Dropdown */}
          <div className={`p-3.5 rounded-xl border space-y-2 ${
            isLight ? 'bg-blue-50/60 border-blue-200' : 'bg-slate-800/70 border-slate-750'
          }`}>
            <div className="flex items-center justify-between">
              <label className={`text-xs font-bold flex items-center gap-1.5 ${isLight ? 'text-slate-900' : 'text-slate-200'}`}>
                <CreditCard className="w-4 h-4 text-blue-600 dark:text-blue-400" />
                <span>{isEn ? '2. Select Account / Bank Channel:' : '2. اکاؤنٹ یا بینک چنیں:'}</span>
              </label>
              <span className={`px-2 py-0.5 rounded-full text-[11px] font-extrabold border ${activeChannel.badgeBg}`}>
                {activeChannel.emoji} {activeChannel.nameEn}
              </span>
            </div>

            <select
              value={paymentMethod}
              onChange={(e) => setPaymentMethod(e.target.value as PaymentMethod)}
              className={`w-full px-3 py-2.5 rounded-xl text-xs sm:text-sm font-semibold outline-none cursor-pointer border ${
                isLight
                  ? 'bg-white border-slate-300 text-slate-900 focus:border-blue-600 focus:ring-1 focus:ring-blue-600'
                  : 'bg-slate-900 border-slate-700 text-white focus:border-blue-500 focus:ring-1 focus:ring-blue-500'
              }`}
            >
              <optgroup label="📱 Digital Wallets & Microfinance">
                {PAYMENT_CHANNELS.filter(c => c.category === 'WALLETS').map(c => (
                  <option key={c.id} value={c.id}>
                    {c.emoji} {c.nameEn} - {c.nameUrdu}
                  </option>
                ))}
              </optgroup>
              <optgroup label="🏛️ Pakistani Commercial & Islamic Banks">
                {PAYMENT_CHANNELS.filter(c => c.category === 'BANKS').map(c => (
                  <option key={c.id} value={c.id}>
                    {c.emoji} {c.nameEn} - {c.nameUrdu}
                  </option>
                ))}
              </optgroup>
              <optgroup label="💵 Physical Cash">
                {PAYMENT_CHANNELS.filter(c => c.category === 'CASH').map(c => (
                  <option key={c.id} value={c.id}>
                    {c.emoji} {c.nameEn} - {c.nameUrdu}
                  </option>
                ))}
              </optgroup>
            </select>
          </div>

          {/* Amount & Fee Profit Inputs */}
          <div className="grid grid-cols-1 sm:grid-cols-2 gap-3">
            <div>
              <label className={`block text-xs font-bold mb-1 ${isLight ? 'text-slate-700' : 'text-slate-300'}`}>
                {isEn ? 'Transfer Amount (Rs.): *' : 'رقم درامد/برامد (Rs.): *'}
              </label>
              <input
                type="number"
                required
                min="1"
                placeholder="e.g. 5000"
                value={easyPaisaAmount}
                onChange={(e) => handleEasyPaisaChange(e.target.value)}
                className={`w-full px-3 py-2.5 rounded-xl font-mono text-base font-extrabold outline-none border ${
                  isLight
                    ? 'bg-slate-50 border-slate-300 text-slate-900 focus:bg-white focus:border-emerald-600'
                    : 'bg-slate-800 border-slate-700 text-white focus:border-emerald-500'
                }`}
              />
            </div>

            <div>
              <label className={`block text-xs font-bold mb-1 ${isLight ? 'text-emerald-700' : 'text-emerald-400'}`}>
                {isEn ? 'Profit / Fee (Rs.): *' : 'فیس / منافع (Rs.): *'}
              </label>
              <input
                type="number"
                required
                min="0"
                placeholder="e.g. 100"
                value={feeProfit}
                onChange={(e) => handleFeeChange(e.target.value)}
                className={`w-full px-3 py-2.5 rounded-xl font-mono text-base font-extrabold outline-none border ${
                  isLight
                    ? 'bg-emerald-50/70 border-emerald-300 text-emerald-900 focus:bg-white focus:border-emerald-600'
                    : 'bg-slate-800 border-emerald-500/50 text-emerald-300 focus:border-emerald-400'
                }`}
              />
            </div>
          </div>

          {/* Cash Amount Paid/Received Auto Summary Card */}
          <div className={`p-3.5 rounded-xl border flex items-center justify-between ${
            isLight
              ? 'bg-slate-50 border-slate-200 text-slate-900'
              : 'bg-slate-800/80 border-slate-750 text-white'
          }`}>
            <div>
              <span className={`text-xs font-semibold ${isLight ? 'text-slate-500' : 'text-slate-400'}`}>
                {type === 'BUY_EASYPAISA' 
                  ? (isEn ? 'Cash Paid to Customer:' : 'گاہک کو ادا شدہ نقد کیش:')
                  : (isEn ? 'Cash Collected from Customer:' : 'گاہک سے وصول شدہ کیش:')}
              </span>
              <p className={`text-lg font-mono font-black mt-0.5 ${isLight ? 'text-slate-900' : 'text-white'}`}>
                Rs. {typeof cashAmount === 'number' ? cashAmount.toLocaleString() : '0'}
              </p>
            </div>
            <div className="text-right">
              <span className={`text-xs font-bold ${isLight ? 'text-emerald-700' : 'text-emerald-400'}`}>
                {isEn ? 'Net Profit:' : 'خالص منافع:'}
              </span>
              <p className="text-lg font-mono font-black text-emerald-600 dark:text-emerald-400 mt-0.5">
                +Rs. {typeof feeProfit === 'number' ? feeProfit.toLocaleString() : '0'}
              </p>
            </div>
          </div>

          {/* Customer Name & Phone */}
          <div className="grid grid-cols-1 sm:grid-cols-2 gap-3">
            <div>
              <label className={`block text-xs font-bold mb-1 flex items-center gap-1 ${isLight ? 'text-slate-700' : 'text-slate-300'}`}>
                <User className="w-3.5 h-3.5 text-slate-400" />
                <span>{isEn ? 'Customer Name:' : 'گاہک کا نام:'}</span>
              </label>
              <input
                type="text"
                placeholder="e.g. Ali Ahmed"
                value={customerName}
                onChange={(e) => setCustomerName(e.target.value)}
                className={`w-full px-3 py-2 rounded-xl text-sm outline-none border ${
                  isLight
                    ? 'bg-slate-50 border-slate-300 text-slate-900 focus:bg-white focus:border-blue-600'
                    : 'bg-slate-800 border-slate-700 text-white focus:border-blue-500'
                }`}
              />
            </div>

            <div>
              <label className={`block text-xs font-bold mb-1 flex items-center gap-1 ${isLight ? 'text-slate-700' : 'text-slate-300'}`}>
                <Phone className="w-3.5 h-3.5 text-slate-400" />
                <span>{isEn ? 'Phone Number:' : 'فون نمبر:'}</span>
              </label>
              <input
                type="text"
                placeholder="03001234567"
                value={customerPhone}
                onChange={(e) => setCustomerPhone(e.target.value)}
                className={`w-full px-3 py-2 rounded-xl text-sm font-mono outline-none border ${
                  isLight
                    ? 'bg-slate-50 border-slate-300 text-slate-900 focus:bg-white focus:border-blue-600'
                    : 'bg-slate-800 border-slate-700 text-white focus:border-blue-500'
                }`}
              />
            </div>
          </div>

          {/* TRX ID & Notes */}
          <div className="grid grid-cols-1 sm:grid-cols-2 gap-3">
            <div>
              <label className={`block text-xs font-bold mb-1 flex items-center gap-1 ${isLight ? 'text-slate-700' : 'text-slate-300'}`}>
                <Hash className="w-3.5 h-3.5 text-slate-400" />
                <span>{isEn ? 'TRX ID / Ref No:' : 'TRX ID یا حوالہ نمبر:'}</span>
              </label>
              <input
                type="text"
                placeholder="e.g. 28491029381"
                value={trxId}
                onChange={(e) => setTrxId(e.target.value)}
                className={`w-full px-3 py-2 rounded-xl text-sm font-mono outline-none border ${
                  isLight
                    ? 'bg-slate-50 border-slate-300 text-slate-900 focus:bg-white focus:border-blue-600'
                    : 'bg-slate-800 border-slate-700 text-white focus:border-blue-500'
                }`}
              />
            </div>

            <div>
              <label className={`block text-xs font-bold mb-1 flex items-center gap-1 ${isLight ? 'text-slate-700' : 'text-slate-300'}`}>
                <FileText className="w-3.5 h-3.5 text-slate-400" />
                <span>{isEn ? 'Notes / Details:' : 'تفصیل (اختیازی):'}</span>
              </label>
              <input
                type="text"
                placeholder="e.g. Regular customer"
                value={notes}
                onChange={(e) => setNotes(e.target.value)}
                className={`w-full px-3 py-2 rounded-xl text-sm outline-none border ${
                  isLight
                    ? 'bg-slate-50 border-slate-300 text-slate-900 focus:bg-white focus:border-blue-600'
                    : 'bg-slate-800 border-slate-700 text-white focus:border-blue-500'
                }`}
              />
            </div>
          </div>

          {/* Action Buttons */}
          <div className={`pt-3 flex items-center justify-end gap-3 border-t ${isLight ? 'border-slate-200' : 'border-slate-800'}`}>
            <button
              type="button"
              onClick={onClose}
              className={`px-4 py-2.5 rounded-xl font-bold text-xs sm:text-sm transition-colors cursor-pointer border ${
                isLight
                  ? 'bg-slate-100 border-slate-300 text-slate-700 hover:bg-slate-200'
                  : 'bg-slate-800 border-slate-700 text-slate-300 hover:bg-slate-700'
              }`}
            >
              {isEn ? 'Cancel' : 'منسوخ کریں'}
            </button>
            <button
              type="submit"
              className="px-6 py-2.5 rounded-xl bg-emerald-600 hover:bg-emerald-700 text-white font-black text-xs sm:text-sm shadow-md transition-all flex items-center gap-2 cursor-pointer active:scale-95"
            >
              <CheckCircle2 className="w-4 h-4" />
              <span>{editingTransaction 
                ? (isEn ? 'Update Entry' : 'تبدیل کریں') 
                : (isEn ? 'Save Transaction' : 'اندراج محفوظ کریں')}</span>
            </button>
          </div>

        </form>
      </div>
    </div>
  );
};

