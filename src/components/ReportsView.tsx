import React, { useState } from 'react';
import { 
  FileSpreadsheet, 
  FileDown, 
  Download, 
  BarChart3, 
  CreditCard
} from 'lucide-react';
import { 
  ResponsiveContainer, 
  ComposedChart, 
  Bar, 
  Line, 
  XAxis, 
  YAxis, 
  CartesianGrid, 
  Tooltip, 
  Legend
} from 'recharts';
import { Transaction, DailyBalance, AppSettings } from '../types';
import { generateMonthlyReportPDF } from '../lib/pdf';
import { PAYMENT_CHANNELS, getPaymentChannelInfo } from '../lib/paymentChannels';

interface ReportsViewProps {
  transactions: Transaction[];
  dailyBalances: Record<string, DailyBalance>;
  settings: AppSettings;
}

export const ReportsView: React.FC<ReportsViewProps> = ({
  transactions,
  dailyBalances,
  settings,
}) => {
  const [selectedMonth, setSelectedMonth] = useState(() => {
    const d = new Date();
    return `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}`;
  });

  const isLight = settings.theme === 'light';
  const isEn = settings.language === 'en';

  // Filter transactions for selected YYYY-MM
  const monthTrx = transactions.filter((t) => t.date.startsWith(selectedMonth));

  let totalBuyVolume = 0;
  let totalSellVolume = 0;
  let totalGrossProfit = 0;
  let totalExpenses = 0;

  // Channel breakdown tracker
  const channelBreakdown: Record<string, { buyVol: number; sellVol: number; profit: number; count: number }> = {};

  monthTrx.forEach((t) => {
    const ch = t.paymentMethod || 'EASYPAISA';
    if (!channelBreakdown[ch]) {
      channelBreakdown[ch] = { buyVol: 0, sellVol: 0, profit: 0, count: 0 };
    }
    channelBreakdown[ch].count += 1;

    if (t.type === 'BUY_EASYPAISA' || t.type === 'BUY_CASH') {
      totalBuyVolume += t.easyPaisaAmount;
      totalGrossProfit += t.feeProfit;
      channelBreakdown[ch].buyVol += t.easyPaisaAmount;
      channelBreakdown[ch].profit += t.feeProfit;
    } else if (t.type === 'SELL_EASYPAISA' || t.type === 'SELL_CASH') {
      totalSellVolume += t.easyPaisaAmount;
      totalGrossProfit += t.feeProfit;
      channelBreakdown[ch].sellVol += t.easyPaisaAmount;
      channelBreakdown[ch].profit += t.feeProfit;
    } else if (t.type === 'EXPENSE' || t.type === 'DISCREPANCY_LOSS') {
      totalExpenses += t.expenseAmount;
    }
  });

  const netProfit = totalGrossProfit - totalExpenses;

  // Group by Date for Day-wise Breakdown
  const daysMap: Record<string, { count: number; buy: number; sell: number; profit: number; expense: number }> = {};

  monthTrx.forEach((t) => {
    if (!daysMap[t.date]) {
      daysMap[t.date] = { count: 0, buy: 0, sell: 0, profit: 0, expense: 0 };
    }
    daysMap[t.date].count += 1;
    if (t.type === 'BUY_EASYPAISA' || t.type === 'BUY_CASH') {
      daysMap[t.date].buy += t.easyPaisaAmount;
      daysMap[t.date].profit += t.feeProfit;
    } else if (t.type === 'SELL_EASYPAISA' || t.type === 'SELL_CASH') {
      daysMap[t.date].sell += t.easyPaisaAmount;
      daysMap[t.date].profit += t.feeProfit;
    } else if (t.type === 'EXPENSE' || t.type === 'DISCREPANCY_LOSS') {
      daysMap[t.date].expense += t.expenseAmount;
    }
  });

  const dayRows = Object.keys(daysMap).sort().reverse().map((dateStr) => ({
    date: dateStr,
    ...daysMap[dateStr],
    net: daysMap[dateStr].profit - daysMap[dateStr].expense,
  }));

  // Recharts Datasets Preparation
  const [chartViewMode, setChartViewMode] = useState<'daily' | 'monthly'>('daily');

  // Daily trend dataset for selected month
  const chartDataDaily = Object.keys(daysMap)
    .sort()
    .map((dateStr) => {
      const dayData = daysMap[dateStr];
      const dateParts = dateStr.split('-');
      const formattedDate = `${dateParts[2]} ${new Date(parseInt(dateParts[0]), parseInt(dateParts[1]) - 1, parseInt(dateParts[2])).toLocaleString('en', { month: 'short' })}`;
      return {
        label: formattedDate,
        date: dateStr,
        GrossProfit: dayData.profit,
        Expenses: dayData.expense,
        NetProfit: dayData.profit - dayData.expense,
        BuyVolume: dayData.buy,
        SellVolume: dayData.sell,
      };
    });

  // Monthly trend dataset across all historical transactions
  const monthlyAgg: Record<string, { gross: number; exp: number; buy: number; sell: number }> = {};
  transactions.forEach((t) => {
    const mKey = t.date.slice(0, 7); // YYYY-MM
    if (!monthlyAgg[mKey]) {
      monthlyAgg[mKey] = { gross: 0, exp: 0, buy: 0, sell: 0 };
    }
    if (t.type === 'BUY_EASYPAISA' || t.type === 'BUY_CASH') {
      monthlyAgg[mKey].buy += t.easyPaisaAmount;
      monthlyAgg[mKey].gross += t.feeProfit;
    } else if (t.type === 'SELL_EASYPAISA' || t.type === 'SELL_CASH') {
      monthlyAgg[mKey].sell += t.easyPaisaAmount;
      monthlyAgg[mKey].gross += t.feeProfit;
    } else if (t.type === 'EXPENSE' || t.type === 'DISCREPANCY_LOSS') {
      monthlyAgg[mKey].exp += t.expenseAmount;
    }
  });

  const chartDataMonthly = Object.keys(monthlyAgg)
    .sort()
    .map((mKey) => {
      const [year, monthNum] = mKey.split('-');
      const monthLabel = new Date(parseInt(year), parseInt(monthNum) - 1, 1).toLocaleString('en', { month: 'short', year: '2-digit' });
      const item = monthlyAgg[mKey];
      return {
        label: monthLabel,
        date: mKey,
        monthKey: mKey,
        GrossProfit: item.gross,
        Expenses: item.exp,
        NetProfit: item.gross - item.exp,
        BuyVolume: item.buy,
        SellVolume: item.sell,
      };
    });

  const activeChartData = chartViewMode === 'daily' ? chartDataDaily : chartDataMonthly;

  const handleDownloadPDF = () => {
    generateMonthlyReportPDF(selectedMonth, monthTrx, dailyBalances, settings);
  };

  const handleExportCSV = () => {
    const headers = ['ID', 'Date', 'Time', 'Type', 'Channel', 'Customer', 'Phone', 'TRX ID', 'Transfer Amount', 'Cash Amount', 'Fee Profit', 'Expense', 'Notes'];
    const rows = monthTrx.map(t => [
      t.id,
      t.date,
      t.time,
      t.type,
      t.paymentMethod || 'EASYPAISA',
      `"${t.customerName}"`,
      t.customerPhone || '',
      t.trxId || '',
      t.easyPaisaAmount,
      t.cashAmount,
      t.feeProfit,
      t.expenseAmount,
      `"${t.notes || ''}"`
    ]);

    const csvContent = 'data:text/csv;charset=utf-8,' + [headers.join(','), ...rows.map(e => e.join(','))].join('\n');
    const encodedUri = encodeURI(csvContent);
    const link = document.createElement('a');
    link.setAttribute('href', encodedUri);
    link.setAttribute('download', `Monthly_Ledger_${selectedMonth}.csv`);
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
  };

  return (
    <div className="space-y-4 sm:space-y-6 font-sans">
      
      {/* Month Selector Bar */}
      <div className={`${isLight ? 'bg-white border-slate-200' : 'bg-slate-900 border-slate-800'} border p-4 sm:p-5 rounded-2xl flex flex-col sm:flex-row items-stretch sm:items-center justify-between gap-3 sm:gap-4 transition-colors duration-200 shadow-sm`}>
        <div className="flex items-center gap-3">
          <div className="w-10 h-10 rounded-xl bg-teal-500/10 text-teal-500 dark:text-teal-400 flex items-center justify-center font-bold shrink-0">
            <FileSpreadsheet className="w-5 h-5" />
          </div>
          <div>
            <h2 className={`text-base sm:text-lg font-bold ${isLight ? 'text-slate-900' : 'text-white'}`}>
              {isEn ? 'Monthly Business Report & Analytics' : 'ماہانہ رپورٹ اور کاروباری تجزئیے'}
            </h2>
            <p className={`text-xs ${isLight ? 'text-slate-600' : 'text-slate-400'}`}>
              {isEn ? 'Wallets, Bank Transfers, Profits & Shop Costs Breakdown' : 'تمام ایزی پیسہ، جازکیش، آن لائن بینکس کا مجموعی ماہانہ رپورٹ'}
            </p>
          </div>
        </div>

        <div className="flex flex-wrap items-center gap-2 sm:gap-3">
          <input
            type="month"
            value={selectedMonth}
            onChange={(e) => setSelectedMonth(e.target.value)}
            className={`px-3 py-2 ${isLight ? 'bg-slate-50 border-slate-300 text-slate-900' : 'bg-slate-800 border-slate-700 text-white'} border rounded-xl text-xs sm:text-sm font-mono outline-none focus:border-emerald-500 flex-1 sm:flex-none cursor-pointer`}
          />
          <button
            onClick={handleDownloadPDF}
            className="px-3.5 py-2 rounded-xl bg-emerald-600 hover:bg-emerald-700 text-white font-bold text-xs sm:text-sm flex items-center justify-center gap-2 shadow-md cursor-pointer transition-all active:scale-95 shrink-0"
          >
            <FileDown className="w-4 h-4" />
            <span>PDF Report</span>
          </button>
          <button
            onClick={handleExportCSV}
            className={`px-3 py-2 rounded-xl ${isLight ? 'bg-slate-100 hover:bg-slate-200 text-slate-800 border-slate-300' : 'bg-slate-800 hover:bg-slate-700 text-slate-300 border-slate-700'} border text-xs font-semibold flex items-center justify-center gap-1.5 cursor-pointer transition-all shrink-0`}
          >
            <Download className="w-4 h-4 text-teal-500 dark:text-teal-400" />
            <span>CSV</span>
          </button>
        </div>
      </div>

      {/* Monthly Metrics Summary Grid */}
      <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3 sm:gap-4">
        <div className={`${isLight ? 'bg-gradient-to-br from-emerald-50 via-white to-white border-emerald-300' : 'bg-gradient-to-br from-emerald-950/90 to-slate-900 border-emerald-500/40'} border p-4 sm:p-5 rounded-2xl shadow-lg relative overflow-hidden transition-colors duration-200`}>
          <span className="text-xs text-emerald-700 dark:text-emerald-400 font-bold block mb-1">TOTAL MONTHLY NET PROFIT</span>
          <div className={`text-2xl sm:text-3xl font-black font-mono mb-2 ${isLight ? 'text-slate-900' : 'text-white'}`}>
            Rs. {netProfit.toLocaleString()}
          </div>
          <span className={`text-[11px] ${isLight ? 'text-slate-600' : 'text-slate-400'}`}>Month: {selectedMonth}</span>
        </div>

        <div className={`${isLight ? 'bg-white border-slate-200' : 'bg-slate-900 border-slate-800'} border p-4 sm:p-5 rounded-2xl shadow-lg transition-colors duration-200`}>
          <span className={`text-xs font-bold block mb-1 ${isLight ? 'text-slate-700' : 'text-slate-400'}`}>TOTAL BUY VOLUME</span>
          <div className="text-2xl sm:text-3xl font-black font-mono text-emerald-600 dark:text-emerald-400 mb-2">
            Rs. {totalBuyVolume.toLocaleString()}
          </div>
          <span className={`text-[11px] ${isLight ? 'text-slate-500' : 'text-slate-400'}`}>Customer Cash-Outs</span>
        </div>

        <div className={`${isLight ? 'bg-white border-slate-200' : 'bg-slate-900 border-slate-800'} border p-4 sm:p-5 rounded-2xl shadow-lg transition-colors duration-200`}>
          <span className={`text-xs font-bold block mb-1 ${isLight ? 'text-slate-700' : 'text-slate-400'}`}>TOTAL SELL VOLUME</span>
          <div className="text-2xl sm:text-3xl font-black font-mono text-amber-600 dark:text-amber-400 mb-2">
            Rs. {totalSellVolume.toLocaleString()}
          </div>
          <span className={`text-[11px] ${isLight ? 'text-slate-500' : 'text-slate-400'}`}>Online Money Transfers</span>
        </div>

        <div className={`${isLight ? 'bg-white border-slate-200' : 'bg-slate-900 border-slate-800'} border p-4 sm:p-5 rounded-2xl shadow-lg transition-colors duration-200`}>
          <span className={`text-xs font-bold block mb-1 ${isLight ? 'text-slate-700' : 'text-slate-400'}`}>TOTAL MONTHLY EXPENSES</span>
          <div className="text-2xl sm:text-3xl font-black font-mono text-rose-600 dark:text-rose-400 mb-2">
            Rs. {totalExpenses.toLocaleString()}
          </div>
          <span className={`text-[11px] ${isLight ? 'text-slate-500' : 'text-slate-400'}`}>Rent, Bills, Refreshments & Loss</span>
        </div>
      </div>

      {/* Payment Channels Monthly Volume Breakdown Grid */}
      <div className={`${isLight ? 'bg-white border-slate-200' : 'bg-slate-900 border-slate-800'} border p-4 sm:p-5 rounded-2xl space-y-3`}>
        <div className="flex items-center gap-2">
          <CreditCard className="w-5 h-5 text-blue-600 dark:text-blue-400" />
          <h3 className={`font-bold text-sm sm:text-base ${isLight ? 'text-slate-900' : 'text-white'}`}>
            {isEn ? 'Monthly Volume & Profit by Account Channel' : 'اکاؤنٹس و بینکس کے لحاظ سے ماہانہ کاروبار اور فیس'}
          </h3>
        </div>

        <div className="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-6 gap-2.5">
          {PAYMENT_CHANNELS.map(ch => {
            const data = channelBreakdown[ch.id] || { buyVol: 0, sellVol: 0, profit: 0, count: 0 };
            const totalVol = data.buyVol + data.sellVol;
            return (
              <div key={ch.id} className={`p-3 rounded-xl border flex flex-col justify-between ${
                isLight ? 'bg-slate-50 border-slate-200' : 'bg-slate-800/60 border-slate-750'
              }`}>
                <div>
                  <span className={`inline-flex items-center gap-1 px-1.5 py-0.5 rounded text-[10px] font-bold border ${ch.badgeBg}`}>
                    {ch.emoji} {ch.nameEn}
                  </span>
                  <div className="mt-2">
                    <span className="text-[10px] text-slate-500 block font-semibold">Total Volume</span>
                    <p className={`text-xs font-mono font-extrabold ${isLight ? 'text-slate-900' : 'text-white'}`}>
                      Rs. {totalVol.toLocaleString()}
                    </p>
                  </div>
                </div>

                <div className="mt-2 pt-1.5 border-t border-slate-200 dark:border-slate-700 flex justify-between items-center text-[10px]">
                  <span className="text-emerald-600 dark:text-emerald-400 font-bold">Profit:</span>
                  <span className="font-mono font-black text-emerald-600 dark:text-emerald-400">Rs. {data.profit.toLocaleString()}</span>
                </div>
              </div>
            );
          })}
        </div>
      </div>

      {/* Interactive Profit & Loss Recharts Analytics Chart */}
      <div className={`${isLight ? 'bg-white border-slate-200' : 'bg-slate-900 border-slate-800'} border p-4 sm:p-6 rounded-2xl shadow-xl space-y-4 transition-colors duration-200`}>
        <div className="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-3 border-b pb-3 border-slate-200 dark:border-slate-800">
          <div className="flex items-center gap-2.5">
            <div className="p-2 rounded-xl bg-emerald-500/10 text-emerald-600 dark:text-emerald-400">
              <BarChart3 className="w-5 h-5" />
            </div>
            <div>
              <h3 className={`font-bold text-sm sm:text-base ${isLight ? 'text-slate-900' : 'text-white'}`}>
                {chartViewMode === 'daily' ? `Daily Profit & Loss Trend (${selectedMonth})` : 'All Months Profit & Loss Comparison'}
              </h3>
              <p className={`text-xs ${isLight ? 'text-slate-500' : 'text-slate-400'}`}>
                Visualizing gross fees profit, shop expenses, and net profit
              </p>
            </div>
          </div>

          <div className="flex items-center gap-1.5 p-1 rounded-xl bg-slate-100 dark:bg-slate-800 text-xs font-semibold shrink-0 self-end sm:self-auto">
            <button
              onClick={() => setChartViewMode('daily')}
              className={`px-3 py-1.5 rounded-lg transition-all cursor-pointer ${
                chartViewMode === 'daily'
                  ? 'bg-emerald-600 text-white font-bold shadow-sm'
                  : 'text-slate-600 dark:text-slate-400 hover:text-slate-900 dark:hover:text-white'
              }`}
            >
              Daily ({selectedMonth})
            </button>
            <button
              onClick={() => setChartViewMode('monthly')}
              className={`px-3 py-1.5 rounded-lg transition-all cursor-pointer ${
                chartViewMode === 'monthly'
                  ? 'bg-emerald-600 text-white font-bold shadow-sm'
                  : 'text-slate-600 dark:text-slate-400 hover:text-slate-900 dark:hover:text-white'
              }`}
            >
              All Months
            </button>
          </div>
        </div>

        {activeChartData.length === 0 ? (
          <div className="py-12 text-center text-slate-500 text-xs font-medium">
            Is mahine ya view mode ka koi chart data mojood nahi hai.
          </div>
        ) : (
          <div className="h-72 sm:h-80 w-full pt-2">
            <ResponsiveContainer width="100%" height="100%">
              <ComposedChart data={activeChartData} margin={{ top: 10, right: 10, left: -10, bottom: 0 }}>
                <CartesianGrid strokeDasharray="3 3" stroke={isLight ? '#e2e8f0' : '#1e293b'} />
                <XAxis 
                  dataKey="label" 
                  tick={{ fill: isLight ? '#64748b' : '#94a3b8', fontSize: 11 }}
                  stroke={isLight ? '#cbd5e1' : '#334155'}
                />
                <YAxis 
                  tick={{ fill: isLight ? '#64748b' : '#94a3b8', fontSize: 11 }}
                  stroke={isLight ? '#cbd5e1' : '#334155'}
                  tickFormatter={(val) => `Rs.${val}`}
                />
                <Tooltip 
                  contentStyle={{
                    backgroundColor: isLight ? '#ffffff' : '#0f172a',
                    borderColor: isLight ? '#cbd5e1' : '#334155',
                    borderRadius: '0.75rem',
                    boxShadow: '0 10px 15px -3px rgba(0, 0, 0, 0.1)',
                    color: isLight ? '#0f172a' : '#f8fafc',
                    fontSize: '12px',
                    fontWeight: '600'
                  }}
                  formatter={(value: any) => [`Rs. ${Number(value || 0).toLocaleString()}`, '']}
                />
                <Legend 
                  wrapperStyle={{ paddingTop: '10px', fontSize: '12px' }}
                />
                <Bar dataKey="GrossProfit" name="Gross Fee Profit" fill="#10b981" radius={[4, 4, 0, 0]} barSize={20} />
                <Bar dataKey="Expenses" name="Expenses & Loss" fill="#f43f5e" radius={[4, 4, 0, 0]} barSize={20} />
                <Line type="monotone" dataKey="NetProfit" name="Net Profit" stroke="#0ea5e9" strokeWidth={3} dot={{ r: 4, fill: '#0ea5e9' }} />
              </ComposedChart>
            </ResponsiveContainer>
          </div>
        )}
      </div>

      {/* Day-by-Day Summary Table */}
      <div className={`${isLight ? 'bg-white border-slate-200' : 'bg-slate-900 border-slate-800'} border rounded-2xl overflow-hidden shadow-xl transition-colors duration-200`}>
        <div className={`p-4 ${isLight ? 'bg-slate-50 border-slate-200' : 'bg-slate-800/60 border-slate-800'} border-b flex items-center justify-between`}>
          <h3 className={`font-bold text-xs sm:text-sm ${isLight ? 'text-slate-900' : 'text-white'}`}>Day-by-Day Closing Ledger ({selectedMonth})</h3>
          <span className={`text-xs font-mono ${isLight ? 'text-slate-600' : 'text-slate-400'}`}>{dayRows.length} Days Recorded</span>
        </div>

        <div className="overflow-x-auto">
          <table className="w-full text-left text-xs">
            <thead className={`${isLight ? 'bg-slate-100 text-slate-700' : 'bg-slate-950 text-slate-400'} uppercase font-bold text-[11px] border-b ${isLight ? 'border-slate-200' : 'border-slate-800'}`}>
              <tr>
                <th className="py-3 px-3 sm:px-4">Date</th>
                <th className="py-3 px-3 sm:px-4 text-center">TRX Count</th>
                <th className="py-3 px-3 sm:px-4 text-right">Buy Volume</th>
                <th className="py-3 px-3 sm:px-4 text-right">Sell Volume</th>
                <th className="py-3 px-3 sm:px-4 text-right">Gross Fees</th>
                <th className="py-3 px-3 sm:px-4 text-right">Expenses</th>
                <th className="py-3 px-3 sm:px-4 text-right">Day Net Profit</th>
              </tr>
            </thead>
            <tbody className={`divide-y ${isLight ? 'divide-slate-200' : 'divide-slate-800/60'} font-medium`}>
              {dayRows.length === 0 ? (
                <tr>
                  <td colSpan={7} className="py-8 text-center text-slate-500">
                    Is mahine ka koi record mojood nahi hai.
                  </td>
                </tr>
              ) : (
                dayRows.map((r) => (
                  <tr key={r.date} className={`${isLight ? 'hover:bg-slate-50' : 'hover:bg-slate-800/40'} transition-colors`}>
                    <td className={`py-3 px-3 sm:px-4 font-mono font-bold ${isLight ? 'text-slate-900' : 'text-white'}`}>{r.date}</td>
                    <td className="py-3 px-3 sm:px-4 text-center font-mono text-slate-500">{r.count}</td>
                    <td className="py-3 px-3 sm:px-4 text-right font-mono text-emerald-600 dark:text-emerald-400 font-extrabold">Rs. {r.buy.toLocaleString()}</td>
                    <td className="py-3 px-3 sm:px-4 text-right font-mono text-amber-600 dark:text-amber-400 font-extrabold">Rs. {r.sell.toLocaleString()}</td>
                    <td className={`py-3 px-3 sm:px-4 text-right font-mono ${isLight ? 'text-slate-700' : 'text-slate-300'}`}>Rs. {r.profit.toLocaleString()}</td>
                    <td className="py-3 px-3 sm:px-4 text-right font-mono text-rose-600 dark:text-rose-400 font-bold">Rs. {r.expense.toLocaleString()}</td>
                    <td className="py-3 px-3 sm:px-4 text-right font-mono font-black">
                      <span className={r.net >= 0 ? 'text-emerald-600 dark:text-emerald-400' : 'text-rose-600 dark:text-rose-400'}>
                        {r.net >= 0 ? '+' : ''}Rs. {r.net.toLocaleString()}
                      </span>
                    </td>
                  </tr>
                ))
              )}
            </tbody>
          </table>
        </div>
      </div>

    </div>
  );
};

