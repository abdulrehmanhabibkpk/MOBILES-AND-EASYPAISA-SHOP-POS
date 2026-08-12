import React, { useState } from 'react';
import { 
  BookOpen, 
  Search, 
  FileDown, 
  Eye, 
  Trash2, 
  ArrowDownRight, 
  ArrowUpRight, 
  TrendingDown,
} from 'lucide-react';
import { Transaction, AppSettings, PaymentMethod } from '../types';
import { generateMonthlyReportPDF } from '../lib/pdf';
import { PAYMENT_CHANNELS, getPaymentChannelInfo } from '../lib/paymentChannels';

interface LedgerViewProps {
  transactions: Transaction[];
  settings: AppSettings;
  onSelectTransaction: (trx: Transaction) => void;
  onDeleteTransaction: (id: string) => void;
}

export const LedgerView: React.FC<LedgerViewProps> = ({
  transactions,
  settings,
  onSelectTransaction,
  onDeleteTransaction,
}) => {
  const [searchTerm, setSearchTerm] = useState('');
  const [typeFilter, setTypeFilter] = useState('ALL');
  const [channelFilter, setChannelFilter] = useState<string>('ALL');
  const [startDate, setStartDate] = useState(() => {
    const d = new Date();
    d.setDate(d.getDate() - 30);
    return d.toISOString().split('T')[0];
  });
  const [endDate, setEndDate] = useState(new Date().toISOString().split('T')[0]);

  const isLight = settings.theme === 'light';
  const isEn = settings.language === 'en';

  // Filter logic
  const filtered = transactions.filter((t) => {
    const inDateRange = t.date >= startDate && t.date <= endDate;
    const term = searchTerm.toLowerCase().trim();
    const matchesSearch =
      !term ||
      t.customerName.toLowerCase().includes(term) ||
      (t.customerPhone && t.customerPhone.toLowerCase().includes(term)) ||
      (t.trxId && t.trxId.toLowerCase().includes(term)) ||
      (t.notes && t.notes.toLowerCase().includes(term)) ||
      (t.id && t.id.toLowerCase().includes(term));

    if (!inDateRange || !matchesSearch) return false;

    if (typeFilter !== 'ALL') {
      if (typeFilter === 'BUY' && !(t.type === 'BUY_EASYPAISA' || t.type === 'BUY_CASH')) return false;
      if (typeFilter === 'SELL' && !(t.type === 'SELL_EASYPAISA' || t.type === 'SELL_CASH')) return false;
      if (typeFilter === 'EXPENSE' && !(t.type === 'EXPENSE' || t.type === 'DISCREPANCY_LOSS')) return false;
    }

    if (channelFilter !== 'ALL') {
      if (channelFilter === 'WALLETS') {
        const info = getPaymentChannelInfo(t.paymentMethod);
        if (info.category !== 'WALLETS') return false;
      } else if (channelFilter === 'BANKS') {
        const info = getPaymentChannelInfo(t.paymentMethod);
        if (info.category !== 'BANKS') return false;
      } else if (t.paymentMethod !== channelFilter) {
        return false;
      }
    }

    return true;
  });

  // Calculate stats for filtered set
  let totalBuyVolume = 0;
  let totalSellVolume = 0;
  let totalGrossProfit = 0;
  let totalExpenses = 0;

  filtered.forEach(t => {
    if (t.type === 'BUY_EASYPAISA' || t.type === 'BUY_CASH') {
      totalBuyVolume += t.easyPaisaAmount;
      totalGrossProfit += t.feeProfit;
    } else if (t.type === 'SELL_EASYPAISA' || t.type === 'SELL_CASH') {
      totalSellVolume += t.easyPaisaAmount;
      totalGrossProfit += t.feeProfit;
    } else if (t.type === 'EXPENSE' || t.type === 'DISCREPANCY_LOSS') {
      totalExpenses += t.expenseAmount;
    }
  });

  const netProfit = totalGrossProfit - totalExpenses;

  const handleDownloadPDF = () => {
    generateMonthlyReportPDF(`${startDate}_to_${endDate}`, filtered, {}, settings);
  };

  return (
    <div className="space-y-4 sm:space-y-6 font-sans">
      
      {/* Header & Filter Controls */}
      <div className={`${isLight ? 'bg-white border-slate-200' : 'bg-slate-900 border-slate-800'} border p-4 sm:p-5 rounded-2xl space-y-4 transition-colors duration-200 shadow-sm`}>
        <div className="flex flex-col sm:flex-row items-stretch sm:items-center justify-between gap-3 sm:gap-4">
          <div className="flex items-center gap-3">
            <div className="w-10 h-10 rounded-xl bg-emerald-500/10 text-emerald-500 dark:text-emerald-400 flex items-center justify-center font-bold shrink-0">
              <BookOpen className="w-5 h-5" />
            </div>
            <div>
              <h2 className={`text-base sm:text-lg font-bold ${isLight ? 'text-slate-900' : 'text-white'}`}>
                {isEn ? 'Roznamcha & Accounts Ledger' : 'مکمل روزنامچہ و کھاتہ لیجر (Accounts Ledger)'}
              </h2>
              <p className={`text-xs ${isLight ? 'text-slate-600' : 'text-slate-400'}`}>
                {isEn ? 'All EasyPaisa, JazzCash, SadaPay & Online Banks Record' : 'تمام ایزی پیسہ، جازکیش، ساداپے اور آن لائن بینک ٹرانزیکشنز'}
              </p>
            </div>
          </div>

          <button
            onClick={handleDownloadPDF}
            className="px-4 py-2.5 rounded-xl bg-emerald-600 hover:bg-emerald-700 text-white font-bold text-xs sm:text-sm flex items-center justify-center gap-2 shadow-md cursor-pointer transition-all active:scale-95"
          >
            <FileDown className="w-4 h-4" />
            <span>{isEn ? 'Export Filtered PDF Report' : 'پی ڈی ایف رپورٹ ڈاؤن لوڈ کریں'}</span>
          </button>
        </div>

        {/* Filter Bar */}
        <div className={`grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-3 pt-3 border-t ${isLight ? 'border-slate-200' : 'border-slate-800'}`}>
          <div>
            <label className={`block text-[11px] font-bold mb-1 ${isLight ? 'text-slate-700' : 'text-slate-400'}`}>Start Date:</label>
            <input
              type="date"
              value={startDate}
              onChange={(e) => setStartDate(e.target.value)}
              className={`w-full px-3 py-2 ${isLight ? 'bg-slate-50 border-slate-300 text-slate-900' : 'bg-slate-800 border-slate-700 text-white'} border rounded-xl text-xs font-mono outline-none focus:border-emerald-500`}
            />
          </div>

          <div>
            <label className={`block text-[11px] font-bold mb-1 ${isLight ? 'text-slate-700' : 'text-slate-400'}`}>End Date:</label>
            <input
              type="date"
              value={endDate}
              onChange={(e) => setEndDate(e.target.value)}
              className={`w-full px-3 py-2 ${isLight ? 'bg-slate-50 border-slate-300 text-slate-900' : 'bg-slate-800 border-slate-700 text-white'} border rounded-xl text-xs font-mono outline-none focus:border-emerald-500`}
            />
          </div>

          <div>
            <label className={`block text-[11px] font-bold mb-1 ${isLight ? 'text-slate-700' : 'text-slate-400'}`}>Transaction Type:</label>
            <select
              value={typeFilter}
              onChange={(e) => setTypeFilter(e.target.value)}
              className={`w-full px-3 py-2 ${isLight ? 'bg-slate-50 border-slate-300 text-slate-900' : 'bg-slate-800 border-slate-700 text-white'} border rounded-xl text-xs outline-none focus:border-emerald-500 cursor-pointer`}
            >
              <option value="ALL">All Directions</option>
              <option value="BUY">BUY (Cash Out)</option>
              <option value="SELL">SELL (Cash In)</option>
              <option value="EXPENSE">Expenses / Shop Costs</option>
            </select>
          </div>

          <div>
            <label className={`block text-[11px] font-bold mb-1 ${isLight ? 'text-slate-700' : 'text-slate-400'}`}>Account / Channel:</label>
            <select
              value={channelFilter}
              onChange={(e) => setChannelFilter(e.target.value)}
              className={`w-full px-3 py-2 ${isLight ? 'bg-slate-50 border-slate-300 text-slate-900' : 'bg-slate-800 border-slate-700 text-white'} border rounded-xl text-xs outline-none focus:border-emerald-500 cursor-pointer`}
            >
              <option value="ALL">All Payment Channels</option>
              <option value="WALLETS">📱 All Mobile Wallets (EasyPaisa/JazzCash/SadaPay)</option>
              <option value="BANKS">🏛️ All Online Banks (Meezan/HBL/UBL/ABL)</option>
              {PAYMENT_CHANNELS.map(c => (
                <option key={c.id} value={c.id}>{c.emoji} {c.nameEn}</option>
              ))}
            </select>
          </div>

          <div>
            <label className={`block text-[11px] font-bold mb-1 ${isLight ? 'text-slate-700' : 'text-slate-400'}`}>
              {isEn ? 'Search Customer / Description / TRX:' : 'نام، تفصیل یا TRX سرچ کریں:'}
            </label>
            <div className="relative">
              <Search className="w-3.5 h-3.5 text-slate-400 absolute left-3 top-2.5" />
              <input
                type="text"
                placeholder={isEn ? "Customer name, description, TRX..." : "گاہک کا نام، تفصیل یا TRX رقم..."}
                value={searchTerm}
                onChange={(e) => setSearchTerm(e.target.value)}
                className={`w-full pl-8 pr-3 py-2 ${isLight ? 'bg-slate-50 border-slate-300 text-slate-900' : 'bg-slate-800 border-slate-700 text-white'} border rounded-xl text-xs outline-none focus:border-emerald-500`}
              />
            </div>
          </div>
        </div>
      </div>

      {/* Filtered Range Summary Cards */}
      <div className="grid grid-cols-2 lg:grid-cols-4 gap-2.5 sm:gap-3">
        <div className={`${isLight ? 'bg-white border-slate-200' : 'bg-slate-900 border-slate-800'} border p-3.5 sm:p-4 rounded-xl transition-colors duration-200`}>
          <span className={`text-[11px] font-bold block ${isLight ? 'text-slate-600' : 'text-slate-400'}`}>Total BUY Volume</span>
          <p className="text-base sm:text-lg font-black font-mono text-emerald-600 dark:text-emerald-400 mt-1">Rs. {totalBuyVolume.toLocaleString()}</p>
        </div>

        <div className={`${isLight ? 'bg-white border-slate-200' : 'bg-slate-900 border-slate-800'} border p-3.5 sm:p-4 rounded-xl transition-colors duration-200`}>
          <span className={`text-[11px] font-bold block ${isLight ? 'text-slate-600' : 'text-slate-400'}`}>Total SELL Volume</span>
          <p className="text-base sm:text-lg font-black font-mono text-amber-600 dark:text-amber-400 mt-1">Rs. {totalSellVolume.toLocaleString()}</p>
        </div>

        <div className={`${isLight ? 'bg-white border-slate-200' : 'bg-slate-900 border-slate-800'} border p-3.5 sm:p-4 rounded-xl transition-colors duration-200`}>
          <span className={`text-[11px] font-bold block ${isLight ? 'text-slate-600' : 'text-slate-400'}`}>Total Expenses</span>
          <p className="text-base sm:text-lg font-black font-mono text-rose-600 dark:text-rose-400 mt-1">Rs. {totalExpenses.toLocaleString()}</p>
        </div>

        <div className={`${isLight ? 'bg-gradient-to-br from-emerald-50 to-white border-emerald-300' : 'bg-gradient-to-br from-emerald-950 to-slate-900 border-emerald-500/30'} border p-3.5 sm:p-4 rounded-xl transition-colors duration-200`}>
          <span className="text-[11px] text-emerald-700 dark:text-emerald-400 font-extrabold block">NET PROFIT IN RANGE</span>
          <p className={`text-base sm:text-lg font-black font-mono mt-1 ${isLight ? 'text-slate-900' : 'text-white'}`}>Rs. {netProfit.toLocaleString()}</p>
        </div>
      </div>

      {/* Ledger Table */}
      <div className={`${isLight ? 'bg-white border-slate-200' : 'bg-slate-900 border-slate-800'} border rounded-2xl overflow-hidden shadow-xl transition-colors duration-200`}>
        <div className="overflow-x-auto">
          <table className="w-full text-left text-xs">
            <thead className={`${isLight ? 'bg-slate-100 text-slate-700' : 'bg-slate-950 text-slate-400'} uppercase font-bold text-[11px] border-b ${isLight ? 'border-slate-200' : 'border-slate-800'}`}>
              <tr>
                <th className="py-3 px-3 sm:px-4">Date & Time</th>
                <th className="py-3 px-3 sm:px-4">Type</th>
                <th className="py-3 px-3 sm:px-4">Account / Channel</th>
                <th className="py-3 px-3 sm:px-4">Customer Name</th>
                <th className="py-3 px-3 sm:px-4">TRX ID / Ref</th>
                <th className="py-3 px-3 sm:px-4 text-right">Transfer Amount</th>
                <th className="py-3 px-3 sm:px-4 text-right">Cash Exchange</th>
                <th className="py-3 px-3 sm:px-4 text-right">Profit / Expense</th>
                <th className="py-3 px-3 sm:px-4 text-center">Action</th>
              </tr>
            </thead>
            <tbody className={`divide-y ${isLight ? 'divide-slate-200' : 'divide-slate-800/60'} font-medium`}>
              {filtered.length === 0 ? (
                <tr>
                  <td colSpan={9} className="py-8 text-center text-slate-500">
                    Koi Record Nahi Mila. Adjust search or date filter.
                  </td>
                </tr>
              ) : (
                filtered.map((t) => {
                  const isBuy = t.type === 'BUY_EASYPAISA' || t.type === 'BUY_CASH';
                  const isSell = t.type === 'SELL_EASYPAISA' || t.type === 'SELL_CASH';
                  const isExpense = t.type === 'EXPENSE' || t.type === 'DISCREPANCY_LOSS';
                  const channel = getPaymentChannelInfo(t.paymentMethod);

                  return (
                    <tr key={t.id} className={`${isLight ? 'hover:bg-slate-50' : 'hover:bg-slate-800/40'} transition-colors`}>
                      <td className={`py-3 px-3 sm:px-4 font-mono ${isLight ? 'text-slate-600' : 'text-slate-400'}`}>
                        {t.date} <span className="text-[10px] block text-slate-500">{t.time}</span>
                      </td>
                      <td className="py-3 px-3 sm:px-4">
                        {isBuy && (
                          <span className={`inline-flex items-center gap-1 px-2 py-0.5 rounded-full ${isLight ? 'bg-emerald-100 text-emerald-800 border-emerald-300' : 'bg-emerald-500/10 text-emerald-400 border-emerald-500/20'} border font-bold text-[11px]`}>
                            <ArrowDownRight className="w-3 h-3 text-emerald-600" /> BUY
                          </span>
                        )}
                        {isSell && (
                          <span className={`inline-flex items-center gap-1 px-2 py-0.5 rounded-full ${isLight ? 'bg-amber-100 text-amber-800 border-amber-300' : 'bg-amber-500/10 text-amber-400 border-amber-500/20'} border font-bold text-[11px]`}>
                            <ArrowUpRight className="w-3 h-3 text-amber-600" /> SELL
                          </span>
                        )}
                        {isExpense && (
                          <span className={`inline-flex items-center gap-1 px-2 py-0.5 rounded-full ${isLight ? 'bg-rose-100 text-rose-800 border-rose-300' : 'bg-rose-500/10 text-rose-400 border-rose-500/20'} border font-bold text-[11px]`}>
                            <TrendingDown className="w-3 h-3 text-rose-600" /> EXPENSE
                          </span>
                        )}
                      </td>
                      <td className="py-3 px-3 sm:px-4">
                        <span className={`inline-flex items-center gap-1 px-2 py-0.5 rounded-lg border text-[11px] font-bold ${channel.badgeBg}`}>
                          {channel.emoji} {channel.nameEn}
                        </span>
                      </td>
                      <td className={`py-3 px-3 sm:px-4 font-bold ${isLight ? 'text-slate-900' : 'text-white'}`}>
                        {t.customerName}
                        {t.customerPhone && <span className={`block text-[10px] font-mono font-normal ${isLight ? 'text-slate-500' : 'text-slate-400'}`}>{t.customerPhone}</span>}
                      </td>
                      <td className={`py-3 px-3 sm:px-4 font-mono font-bold ${isLight ? 'text-slate-700' : 'text-slate-300'}`}>{t.trxId || '-'}</td>
                      <td className={`py-3 px-3 sm:px-4 text-right font-mono font-extrabold ${isLight ? 'text-slate-900' : 'text-white'}`}>
                        {isExpense ? '-' : `Rs. ${t.easyPaisaAmount.toLocaleString()}`}
                      </td>
                      <td className={`py-3 px-3 sm:px-4 text-right font-mono ${isLight ? 'text-slate-700' : 'text-slate-300'}`}>
                        {isExpense ? '-' : `Rs. ${t.cashAmount.toLocaleString()}`}
                      </td>
                      <td className="py-3 px-3 sm:px-4 text-right font-mono font-black">
                        {isExpense ? (
                          <span className="text-rose-600 dark:text-rose-400">-Rs. {t.expenseAmount.toLocaleString()}</span>
                        ) : (
                          <span className="text-emerald-600 dark:text-emerald-400">+Rs. {t.feeProfit.toLocaleString()}</span>
                        )}
                      </td>
                      <td className="py-3 px-3 sm:px-4 text-center">
                        <div className="flex items-center justify-center gap-1">
                          <button
                            onClick={() => onSelectTransaction(t)}
                            className={`p-1.5 rounded-lg ${isLight ? 'bg-slate-100 hover:bg-slate-200 text-emerald-700' : 'bg-slate-800 hover:bg-slate-700 text-emerald-400'} transition-colors cursor-pointer`}
                            title="View Voucher"
                          >
                            <Eye className="w-3.5 h-3.5" />
                          </button>
                          <button
                            onClick={() => onDeleteTransaction(t.id)}
                            className={`p-1.5 rounded-lg ${isLight ? 'bg-slate-100 hover:bg-rose-100 text-rose-600' : 'bg-slate-800 hover:bg-rose-950 text-rose-400'} transition-colors cursor-pointer`}
                            title="Delete"
                          >
                            <Trash2 className="w-3.5 h-3.5" />
                          </button>
                        </div>
                      </td>
                    </tr>
                  );
                })
              )}
            </tbody>
          </table>
        </div>
      </div>

    </div>
  );
};

