import React, { useState } from 'react';
import { Users, Search, Phone, ArrowDownRight, ArrowUpRight, Calendar, UserCheck, Eye, FileDown } from 'lucide-react';
import { Transaction, CustomerSummary, AppSettings } from '../types';
import { getCustomerSummaries } from '../lib/storage';
import { generateMonthlyReportPDF } from '../lib/pdf';

interface CustomerLedgerProps {
  transactions: Transaction[];
  settings: AppSettings;
  onSelectTransaction: (trx: Transaction) => void;
}

export const CustomerLedger: React.FC<CustomerLedgerProps> = ({
  transactions,
  settings,
  onSelectTransaction,
}) => {
  const [searchTerm, setSearchTerm] = useState('');
  const [selectedCustomer, setSelectedCustomer] = useState<CustomerSummary | null>(null);

  const isLight = settings.theme === 'light';
  const customerList = getCustomerSummaries(transactions);

  const filteredCustomers = customerList.filter((c) =>
    c.name.toLowerCase().includes(searchTerm.toLowerCase()) ||
    c.phone.includes(searchTerm)
  );

  // If customer selected, filter their transactions
  const customerTransactions = selectedCustomer
    ? transactions.filter((t) => 
        t.customerName.toLowerCase() === selectedCustomer.name.toLowerCase() &&
        (t.type === 'BUY_EASYPAISA' || t.type === 'SELL_EASYPAISA' || t.type === 'BUY_CASH' || t.type === 'SELL_CASH')
      )
    : [];

  const handleDownloadCustomerPDF = () => {
    if (!selectedCustomer) return;
    generateMonthlyReportPDF(`Customer_${selectedCustomer.name}`, customerTransactions, {}, settings);
  };

  return (
    <div className="space-y-4 sm:space-y-6">
      
      {/* Header */}
      <div className={`${isLight ? 'bg-white border-slate-200' : 'bg-slate-900 border-slate-800'} border p-4 sm:p-5 rounded-2xl flex flex-col sm:flex-row items-stretch sm:items-center justify-between gap-3 sm:gap-4 transition-colors duration-200 shadow-sm`}>
        <div className="flex items-center gap-3">
          <div className="w-10 h-10 rounded-xl bg-emerald-500/10 text-emerald-500 dark:text-emerald-400 flex items-center justify-center font-bold shrink-0">
            <Users className="w-5 h-5" />
          </div>
          <div>
            <h2 className={`text-base sm:text-lg font-bold ${isLight ? 'text-slate-900' : 'text-white'}`}>Grahak Khata (Customer Ledger)</h2>
            <p className={`text-xs ${isLight ? 'text-slate-600' : 'text-slate-400'}`}>Track customer-wise EasyPaisa buy/sell volume & profit</p>
          </div>
        </div>

        <div className="relative w-full sm:w-72">
          <Search className="w-4 h-4 text-slate-400 absolute left-3 top-2.5" />
          <input
            type="text"
            placeholder="Search Customer Name or Mobile..."
            value={searchTerm}
            onChange={(e) => setSearchTerm(e.target.value)}
            className={`w-full pl-9 pr-3 py-2 ${isLight ? 'bg-slate-50 border-slate-300 text-slate-900' : 'bg-slate-800 border-slate-700 text-white'} border rounded-xl text-xs outline-none focus:border-emerald-500`}
          />
        </div>
      </div>

      <div className="grid grid-cols-1 lg:grid-cols-3 gap-4 sm:gap-6">
        
        {/* Customer Directory List */}
        <div className={`lg:col-span-1 ${isLight ? 'bg-white border-slate-200' : 'bg-slate-900 border-slate-800'} border rounded-2xl p-4 space-y-3 transition-colors duration-200 shadow-sm`}>
          <h3 className={`font-bold text-xs sm:text-sm pb-2 border-b ${isLight ? 'border-slate-200 text-slate-900' : 'border-slate-800 text-white'} flex items-center justify-between`}>
            <span>Customers List ({filteredCustomers.length})</span>
          </h3>

          <div className="space-y-2 max-h-[450px] overflow-y-auto pr-1">
            {filteredCustomers.length === 0 ? (
              <p className="text-xs text-slate-500 text-center py-6">Koi Customer Record Nahi Mila</p>
            ) : (
              filteredCustomers.map((c, idx) => {
                const isSelected = selectedCustomer?.name === c.name;
                return (
                  <button
                    key={idx}
                    onClick={() => setSelectedCustomer(c)}
                    className={`w-full text-left p-3 rounded-xl border transition-all cursor-pointer ${
                      isSelected
                        ? isLight
                          ? 'bg-emerald-50 border-emerald-500 text-slate-900 shadow-md'
                          : 'bg-emerald-500/20 border-emerald-500 text-white shadow-lg shadow-emerald-950/40'
                        : isLight
                          ? 'bg-slate-50 border-slate-200 hover:border-emerald-300 text-slate-800'
                          : 'bg-slate-800/60 border-slate-800 hover:border-slate-700 text-slate-300'
                    }`}
                  >
                    <div className="flex items-center justify-between mb-1">
                      <span className={`font-bold text-sm ${isLight ? 'text-slate-900' : 'text-white'}`}>{c.name}</span>
                      <span className={`text-[10px] px-2 py-0.5 rounded-full font-mono font-semibold ${isLight ? 'bg-slate-200 text-slate-800' : 'bg-slate-950 text-emerald-400'}`}>
                        {c.totalTransactions} TRX
                      </span>
                    </div>

                    <div className="flex items-center justify-between text-xs text-slate-500 dark:text-slate-400">
                      <span className="font-mono">{c.phone || 'No Phone'}</span>
                      <span className="text-emerald-600 dark:text-emerald-400 font-mono font-bold">+Rs. {c.totalProfitGenerated.toLocaleString()}</span>
                    </div>
                  </button>
                );
              })
            )}
          </div>
        </div>

        {/* Selected Customer Details & History */}
        <div className={`lg:col-span-2 ${isLight ? 'bg-white border-slate-200' : 'bg-slate-900 border-slate-800'} border rounded-2xl p-4 sm:p-5 space-y-4 transition-colors duration-200 shadow-sm`}>
          {!selectedCustomer ? (
            <div className="flex flex-col items-center justify-center py-16 text-slate-500 text-center">
              <UserCheck className="w-12 h-12 mb-3 text-slate-400" />
              <p className={`text-xs sm:text-sm font-semibold ${isLight ? 'text-slate-600' : 'text-slate-400'}`}>Select a customer from the left list to view ledger</p>
            </div>
          ) : (
            <>
              {/* Selected Header */}
              <div className={`flex flex-col sm:flex-row items-start sm:items-center justify-between gap-3 pb-4 border-b ${isLight ? 'border-slate-200' : 'border-slate-800'}`}>
                <div>
                  <h3 className={`text-base sm:text-lg font-bold ${isLight ? 'text-slate-900' : 'text-white'}`}>{selectedCustomer.name}</h3>
                  <p className="text-xs text-slate-500 font-mono flex items-center gap-1 mt-0.5">
                    <Phone className="w-3.5 h-3.5 text-emerald-500" />
                    <span>{selectedCustomer.phone || 'Phone not provided'}</span>
                  </p>
                </div>

                <button
                  onClick={handleDownloadCustomerPDF}
                  className="w-full sm:w-auto px-3.5 py-2 rounded-xl bg-emerald-500 hover:bg-emerald-600 text-slate-950 font-bold text-xs flex items-center justify-center gap-2 cursor-pointer shadow-md"
                >
                  <FileDown className="w-4 h-4" />
                  <span>Statement PDF Download</span>
                </button>
              </div>

              {/* Customer Stat Cards */}
              <div className="grid grid-cols-1 sm:grid-cols-3 gap-2.5 sm:gap-3">
                <div className={`p-3 ${isLight ? 'bg-slate-50 border-slate-200' : 'bg-slate-800/80 border-slate-700/60'} rounded-xl border`}>
                  <span className="text-[10px] text-slate-500 dark:text-slate-400 font-semibold block">Total Buy Volume</span>
                  <p className="text-sm font-mono font-bold text-emerald-600 dark:text-emerald-400 mt-1">Rs. {selectedCustomer.totalBuyVolume.toLocaleString()}</p>
                </div>

                <div className={`p-3 ${isLight ? 'bg-slate-50 border-slate-200' : 'bg-slate-800/80 border-slate-700/60'} rounded-xl border`}>
                  <span className="text-[10px] text-slate-500 dark:text-slate-400 font-semibold block">Total Sell Volume</span>
                  <p className="text-sm font-mono font-bold text-amber-600 dark:text-amber-400 mt-1">Rs. {selectedCustomer.totalSellVolume.toLocaleString()}</p>
                </div>

                <div className={`p-3 ${isLight ? 'bg-slate-50 border-slate-200' : 'bg-slate-800/80 border-slate-700/60'} rounded-xl border`}>
                  <span className="text-[10px] text-slate-500 dark:text-slate-400 font-semibold block">Profit Generated</span>
                  <p className={`text-sm font-mono font-bold mt-1 ${isLight ? 'text-slate-900' : 'text-white'}`}>Rs. {selectedCustomer.totalProfitGenerated.toLocaleString()}</p>
                </div>
              </div>

              {/* Customer Transactions Table */}
              <div className="overflow-x-auto pt-2">
                <table className="w-full text-left text-xs">
                  <thead className={`${isLight ? 'bg-slate-100 text-slate-700' : 'bg-slate-950 text-slate-400'} uppercase font-semibold text-[11px] border-b ${isLight ? 'border-slate-200' : 'border-slate-800'}`}>
                    <tr>
                      <th className="py-2.5 px-3">Date</th>
                      <th className="py-2.5 px-3">Type</th>
                      <th className="py-2.5 px-3">TRX ID</th>
                      <th className="py-2.5 px-3 text-right">EP Amount</th>
                      <th className="py-2.5 px-3 text-right">Profit</th>
                      <th className="py-2.5 px-3 text-center">View</th>
                    </tr>
                  </thead>
                  <tbody className={`divide-y ${isLight ? 'divide-slate-200' : 'divide-slate-800/60'} font-medium`}>
                    {customerTransactions.map((t) => (
                      <tr key={t.id} className={`${isLight ? 'hover:bg-slate-50' : 'hover:bg-slate-800/40'}`}>
                        <td className={`py-2.5 px-3 font-mono ${isLight ? 'text-slate-600' : 'text-slate-400'}`}>{t.date}</td>
                        <td className="py-2.5 px-3">
                          {t.type === 'BUY_EASYPAISA' ? (
                            <span className="text-emerald-600 dark:text-emerald-400 font-bold">BUY</span>
                          ) : (
                            <span className="text-amber-600 dark:text-amber-400 font-bold">SELL</span>
                          )}
                        </td>
                        <td className={`py-2.5 px-3 font-mono ${isLight ? 'text-slate-600' : 'text-slate-400'}`}>{t.trxId || '-'}</td>
                        <td className={`py-2.5 px-3 text-right font-mono ${isLight ? 'text-slate-900' : 'text-white'}`}>Rs. {t.easyPaisaAmount.toLocaleString()}</td>
                        <td className="py-2.5 px-3 text-right font-mono text-emerald-600 dark:text-emerald-400">+Rs. {t.feeProfit.toLocaleString()}</td>
                        <td className="py-2.5 px-3 text-center">
                          <button
                            onClick={() => onSelectTransaction(t)}
                            className={`p-1 rounded ${isLight ? 'bg-slate-100 hover:bg-slate-200 text-emerald-700' : 'bg-slate-800 hover:bg-slate-700 text-emerald-400'}`}
                          >
                            <Eye className="w-3.5 h-3.5" />
                          </button>
                        </td>
                      </tr>
                    ))}
                  </tbody>
                </table>
              </div>
            </>
          )}
        </div>

      </div>

    </div>
  );
};
