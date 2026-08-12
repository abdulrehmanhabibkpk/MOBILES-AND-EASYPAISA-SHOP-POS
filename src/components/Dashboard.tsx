import React, { useState } from 'react';
import { 
  TrendingUp, 
  Wallet, 
  Smartphone, 
  PlusCircle, 
  MinusCircle, 
  FileDown, 
  Search, 
  ArrowDownRight, 
  ArrowUpRight, 
  Eye,
  Trash2,
  Calendar,
  Sparkles,
  Zap,
  TrendingDown,
  ShoppingCart,
  Package,
  BookOpen,
  Users,
  FileSpreadsheet,
  Settings,
  ShieldAlert,
  ChevronRight
} from 'lucide-react';
import { Transaction, DailyBalance, AppSettings, Product, ProductSale } from '../types';
import { calculateDayStats } from '../lib/storage';
import { generateDailyClosingPDF } from '../lib/pdf';
import { NavTab } from './Navbar';
import { t } from '../lib/i18n';
import { getPaymentChannelInfo } from '../lib/paymentChannels';

interface DashboardProps {
  transactions: Transaction[];
  dailyBalances: Record<string, DailyBalance>;
  products: Product[];
  productSales: ProductSale[];
  settings: AppSettings;
  onNavigateTab: (tab: NavTab) => void;
  onOpenNewTransaction: () => void;
  onOpenNewExpense: () => void;
  onOpenOpeningBalance: () => void;
  onSelectTransaction: (trx: Transaction) => void;
  onDeleteTransaction: (id: string) => void;
}

export const Dashboard: React.FC<DashboardProps> = ({
  transactions,
  dailyBalances,
  products,
  productSales,
  settings,
  onNavigateTab,
  onOpenNewTransaction,
  onOpenNewExpense,
  onOpenOpeningBalance,
  onSelectTransaction,
  onDeleteTransaction,
}) => {
  const [selectedDate, setSelectedDate] = useState<string>(new Date().toISOString().split('T')[0]);
  const [searchTerm, setSearchTerm] = useState('');
  const [filterType, setFilterType] = useState<string>('ALL');

  const isLight = settings.theme === 'light';
  const epStats = calculateDayStats(selectedDate, transactions, dailyBalances);

  // Today's Product Sales calculations
  const dayProductSales = productSales.filter((s) => s.date === selectedDate);
  const totalProductRevenue = dayProductSales.reduce((acc, s) => acc + s.netAmount, 0);
  const totalProductProfit = dayProductSales.reduce((acc, s) => acc + s.profit, 0);

  // Total Net Profit = EasyPaisa Profit + Product Sales Profit
  const netTotalProfit = epStats.netProfit + totalProductProfit;

  // Filter today's transactions
  const dayTrx = transactions.filter((t) => t.date === selectedDate);
  const filteredTrx = dayTrx.filter((t) => {
    const matchesSearch =
      t.customerName.toLowerCase().includes(searchTerm.toLowerCase()) ||
      (t.customerPhone && t.customerPhone.includes(searchTerm)) ||
      (t.trxId && t.trxId.toLowerCase().includes(searchTerm.toLowerCase()));

    if (filterType === 'ALL') return matchesSearch;
    if (filterType === 'BUY') return matchesSearch && (t.type === 'BUY_EASYPAISA' || t.type === 'BUY_CASH');
    if (filterType === 'SELL') return matchesSearch && (t.type === 'SELL_EASYPAISA' || t.type === 'SELL_CASH');
    if (filterType === 'EXPENSE') return matchesSearch && (t.type === 'EXPENSE' || t.type === 'DISCREPANCY_LOSS');
    return matchesSearch;
  });

  const handleDownloadClosingPDF = () => {
    generateDailyClosingPDF(selectedDate, epStats, dayTrx, settings);
  };

  const lowStockCount = products.filter((p) => p.stock <= 3).length;

  // Key Modules list inspired by screenshot
  const isEn = settings.language === 'en';

  const modules = [
    {
      id: 'pos',
      title: isEn ? 'POS Sales Counter' : 'پوائنٹ آف سیل (POS)',
      sub: isEn ? 'Mobiles & Accessories Billing' : 'موبائل و ایکسیسریز فروخت بل',
      icon: ShoppingCart,
      color: 'bg-gradient-to-br from-blue-600 to-indigo-700 text-white',
      badge: `${dayProductSales.length} ${isEn ? 'Sales Today' : 'آج کی فروخت'}`,
    },
    {
      id: 'purchases',
      title: isEn ? 'Mobile Buy Register' : 'موبائل خرید رجسٹر',
      sub: isEn ? 'Buy Old & New Mobiles, CNIC Record & Receipts' : 'نئے و پرانے موبائل خرید و سادہ رسید',
      icon: Smartphone,
      color: 'bg-gradient-to-br from-emerald-600 to-teal-700 text-white',
      badge: isEn ? 'Buy Records' : 'موبائل خرید',
    },
    {
      id: 'inventory',
      title: isEn ? 'Stock Inventory' : 'موبائل و ایکسیسریز اسٹاک',
      sub: isEn ? 'Mobiles, Chargers & Accessories' : 'موبائل، چارجر اور پارٹس اسٹاک',
      icon: Package,
      color: 'bg-gradient-to-br from-indigo-600 to-purple-700 text-white',
      badge: `${products.length} ${isEn ? 'Products' : 'آئٹمز'}`,
    },
    {
      id: 'ledger',
      title: isEn ? 'EasyPaisa Ledger' : 'ایزی پیسہ کھاتہ',
      sub: isEn ? 'Cash In & Cash Out Records' : 'کیش آن اور کیش آوٹ ہسٹری',
      icon: BookOpen,
      color: 'bg-gradient-to-br from-emerald-600 to-teal-700 text-white',
      badge: `Rs. ${epStats.currentEasyPaisa.toLocaleString()}`,
    },
    {
      id: 'customers',
      title: isEn ? 'Customer Accounts' : 'گاہک کھاتہ و ڈائریکٹری',
      sub: isEn ? 'Khata Ledger & History' : 'کسٹمر ریکارڈ اور ادھار کھاتہ',
      icon: Users,
      color: 'bg-gradient-to-br from-teal-600 to-emerald-700 text-white',
      badge: isEn ? 'Directory' : 'گاہک کھاتہ',
    },
    {
      id: 'reports',
      title: isEn ? 'Analytics & Reports' : 'رپورٹس و منافع',
      sub: isEn ? 'Monthly P&L & PDF Export' : 'ماہانہ پرافٹ و پی ڈی ایف',
      icon: FileSpreadsheet,
      color: 'bg-gradient-to-br from-sky-600 to-blue-700 text-white',
      badge: isEn ? 'PDF Export' : 'رپورٹ ڈاون لوڈ',
    },
    {
      id: 'settings',
      title: isEn ? 'Shop Settings' : 'ترتیبات و سیکیورٹی',
      sub: isEn ? 'Store Profile & PIN Lock' : 'دکان کی معلومات و سیکیورٹی',
      icon: Settings,
      color: 'bg-gradient-to-br from-amber-600 to-orange-700 text-white',
      badge: 'PIN: 6242',
    },
  ];

  return (
    <div className="space-y-4 sm:space-y-6 animate-fade-in">
      
      {/* Top Banner & Quick Date Controller */}
      <div className={`p-4 sm:p-5 rounded-2xl border shadow-md transition-colors ${
        isLight ? 'bg-gradient-to-r from-emerald-600 via-teal-600 to-emerald-700 text-white border-emerald-500 shadow-emerald-600/15' : 'bg-gradient-to-r from-neutral-950 via-black to-emerald-950 text-white border-emerald-900/60'
      }`}>
        <div className="flex flex-col md:flex-row md:items-center justify-between gap-4">
          
          {/* Shop Title & Subtitle */}
          <div className="flex items-center gap-3.5">
            <div className={`w-12 h-12 rounded-2xl flex items-center justify-center font-black shrink-0 ${
              isLight ? 'bg-white/20 text-white border border-white/30' : 'bg-emerald-600/20 text-emerald-400 border border-emerald-500/30'
            }`}>
              <Smartphone className="w-6 h-6" />
            </div>
            <div>
              <div className="flex items-center gap-2">
                <h2 className="text-base sm:text-xl font-black tracking-tight text-white">
                  {settings.shopName || 'Mobiles and EasyPaisa Shop POS'}
                </h2>
                <span className={`px-2 py-0.5 rounded-full text-[10px] font-bold uppercase tracking-wider hidden sm:inline-flex items-center gap-1 ${
                  isLight ? 'bg-white/20 text-white border border-white/30' : 'bg-emerald-500/20 text-emerald-300 border border-emerald-500/30'
                }`}>
                  <span className="w-1.5 h-1.5 rounded-full bg-white animate-ping" />
                  Live System
                </span>
              </div>
              <p className={`text-xs mt-0.5 ${isLight ? 'text-emerald-100' : 'text-emerald-200/80'}`}>
                {isEn ? 'Mobile Sales, Accessories POS, EasyPaisa Cash-in/Out & Ledger' : 'موبائل سیلز، ایزی پیسہ کیش ان/آوٹ اور دکان کا مکمل کھاتہ'}
              </p>
            </div>
          </div>

          {/* Date Picker & Opening Capital Button */}
          <div className="flex items-center gap-2 sm:gap-3 shrink-0">
            <div className="flex items-center gap-1.5 px-3 py-1.5 rounded-xl bg-white/10 border border-white/15 backdrop-blur-sm">
              <Calendar className="w-4 h-4 text-blue-300" />
              <input
                type="date"
                value={selectedDate}
                onChange={(e) => setSelectedDate(e.target.value)}
                className="bg-transparent text-xs font-mono text-white font-bold outline-none cursor-pointer"
              />
            </div>
            <button
              onClick={onOpenOpeningBalance}
              className="px-3 py-2 rounded-xl bg-emerald-500 hover:bg-emerald-400 text-slate-950 font-extrabold text-xs flex items-center gap-1.5 transition-all shadow-md cursor-pointer shrink-0"
            >
              <Wallet className="w-4 h-4" />
              <span>{isEn ? 'Set Capital' : 'افتتاحی کیش'}</span>
            </button>
          </div>
        </div>
      </div>

      {/* 4 Primary Metric Cards */}
      <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3 sm:gap-4">
        
        {/* Total Net Profit */}
        <div className={`${isLight ? 'bg-white border-slate-200' : 'bg-slate-900 border-slate-800'} border p-4 sm:p-5 rounded-2xl shadow-md relative overflow-hidden transition-all hover:shadow-lg`}>
          <div className="flex items-center justify-between text-xs font-bold mb-2">
            <span className="text-emerald-600 dark:text-emerald-400 uppercase tracking-wider flex items-center gap-1.5">
              <TrendingUp className="w-4 h-4 text-emerald-500" />
              {isEn ? 'Today Net Profit' : 'آج کا خالص منافع'}
            </span>
            <span className="px-2 py-0.5 rounded-full bg-emerald-500/10 text-emerald-600 dark:text-emerald-400 text-[10px] font-extrabold">
              Total Net
            </span>
          </div>
          <div className="text-2xl sm:text-3xl font-black font-mono text-emerald-600 dark:text-emerald-400 mb-2">
            Rs. {netTotalProfit.toLocaleString()}
          </div>
          <div className="flex items-center justify-between text-[11px] text-slate-500 pt-2 border-t border-slate-100 dark:border-slate-800">
            <span>EP Fee: <strong className="text-emerald-600">Rs. {epStats.totalGrossProfit.toLocaleString()}</strong></span>
            <span>POS: <strong className="text-blue-600">Rs. {totalProductProfit.toLocaleString()}</strong></span>
          </div>
        </div>

        {/* Product Sales Revenue */}
        <div className={`${isLight ? 'bg-white border-slate-200' : 'bg-slate-900 border-slate-800'} border p-4 sm:p-5 rounded-2xl shadow-md relative overflow-hidden transition-all hover:shadow-lg`}>
          <div className="flex items-center justify-between text-xs font-bold mb-2">
            <span className="text-blue-600 dark:text-blue-400 uppercase tracking-wider flex items-center gap-1.5">
              <ShoppingCart className="w-4 h-4 text-blue-500" />
              {isEn ? 'Product POS Sales' : 'سامان فروخت بل'}
            </span>
            <span className="px-2 py-0.5 rounded-full bg-blue-500/10 text-blue-600 dark:text-blue-400 text-[10px] font-extrabold">
              {dayProductSales.length} {isEn ? 'Bills' : 'بل'}
            </span>
          </div>
          <div className="text-2xl sm:text-3xl font-black font-mono text-blue-600 dark:text-blue-400 mb-2">
            Rs. {totalProductRevenue.toLocaleString()}
          </div>
          <div className="flex items-center justify-between text-[11px] text-slate-500 pt-2 border-t border-slate-100 dark:border-slate-800">
            <span>{isEn ? 'Mobile & Accessories' : 'موبائل و ایکسیسریز'}</span>
            <button onClick={() => onNavigateTab('pos')} className="text-blue-600 dark:text-blue-400 font-bold hover:underline flex items-center gap-0.5">
              <span>POS</span> <ChevronRight className="w-3 h-3" />
            </button>
          </div>
        </div>

        {/* Cash in Hand */}
        <div className={`${isLight ? 'bg-white border-slate-200' : 'bg-slate-900 border-slate-800'} border p-4 sm:p-5 rounded-2xl shadow-md relative overflow-hidden transition-all hover:shadow-lg`}>
          <div className="flex items-center justify-between text-xs font-bold mb-2">
            <span className="text-teal-600 dark:text-teal-400 uppercase tracking-wider flex items-center gap-1.5">
              <Wallet className="w-4 h-4 text-teal-500" />
              {isEn ? 'Cash In Hand' : 'موجودہ نقد کیش'}
            </span>
            <span className="text-[10px] text-slate-400 font-mono">Open: {epStats.openingCash.toLocaleString()}</span>
          </div>
          <div className="text-2xl sm:text-3xl font-black font-mono text-slate-900 dark:text-white mb-2">
            Rs. {epStats.currentCash.toLocaleString()}
          </div>
          <div className="flex items-center justify-between text-[11px] text-slate-500 pt-2 border-t border-slate-100 dark:border-slate-800">
            <span>Paid: <strong className="text-rose-500">Rs. {epStats.totalCashGiven.toLocaleString()}</strong></span>
            <span>Recv: <strong className="text-emerald-500">Rs. {epStats.totalCashTaken.toLocaleString()}</strong></span>
          </div>
        </div>

        {/* EasyPaisa Net Balance */}
        <div className={`${isLight ? 'bg-white border-slate-200' : 'bg-slate-900 border-slate-800'} border p-4 sm:p-5 rounded-2xl shadow-md relative overflow-hidden transition-all hover:shadow-lg`}>
          <div className="flex items-center justify-between text-xs font-bold mb-2">
            <span className="text-indigo-600 dark:text-indigo-400 uppercase tracking-wider flex items-center gap-1.5">
              <Zap className="w-4 h-4 text-indigo-500" />
              {isEn ? 'EasyPaisa Balance' : 'ایزی پیسہ اکاؤنٹ'}
            </span>
            <span className="text-[10px] text-slate-400 font-mono">Open: {epStats.openingEasyPaisa.toLocaleString()}</span>
          </div>
          <div className="text-2xl sm:text-3xl font-black font-mono text-indigo-600 dark:text-indigo-400 mb-2">
            Rs. {epStats.currentEasyPaisa.toLocaleString()}
          </div>
          <div className="flex items-center justify-between text-[11px] text-slate-500 pt-2 border-t border-slate-100 dark:border-slate-800">
            <span>In: <strong className="text-emerald-500">Rs. {epStats.totalBuyEpVolume.toLocaleString()}</strong></span>
            <span>Out: <strong className="text-amber-500">Rs. {epStats.totalSellEpVolume.toLocaleString()}</strong></span>
          </div>
        </div>

      </div>

      {/* KEY MANAGEMENT MODULES GRID */}
      <div className={`p-4 sm:p-6 rounded-2xl border shadow-sm ${isLight ? 'bg-white border-slate-200' : 'bg-slate-900 border-slate-800'}`}>
        <div className="flex flex-col sm:flex-row sm:items-center justify-between mb-4 pb-3 border-b border-slate-100 dark:border-slate-800 gap-2">
          <div>
            <h3 className={`text-sm sm:text-base font-black ${isLight ? 'text-slate-900' : 'text-white'} flex items-center gap-2`}>
              <Sparkles className="w-4 h-4 text-amber-500" />
              <span>{isEn ? 'Key Shop Management Modules' : 'اہم انتظامی امور (Key Modules)'}</span>
            </h3>
            <p className="text-xs text-slate-500 mt-0.5">
              {isEn ? 'Direct access to point of sale, stock, ledger, khata & reports' : 'دکان کے بنیادی امور، سیلز، انوینٹری اور کھاتہ نیویگیشن'}
            </p>
          </div>

          {lowStockCount > 0 && (
            <button
              onClick={() => onNavigateTab('inventory')}
              className="px-3 py-1.5 rounded-xl bg-amber-500/10 text-amber-600 dark:text-amber-400 border border-amber-300 dark:border-amber-700/50 text-xs font-extrabold flex items-center gap-1.5 animate-pulse cursor-pointer shrink-0"
            >
              <ShieldAlert className="w-4 h-4" />
              <span>{lowStockCount} {isEn ? 'Low Stock Warning!' : 'آئٹم اسٹاک کم ہے!'}</span>
            </button>
          )}
        </div>

        {/* 6 Modules Grid */}
        <div className="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-6 gap-3">
          {modules.map((mod) => {
            const Icon = mod.icon;

            return (
              <div
                key={mod.id}
                onClick={() => onNavigateTab(mod.id as NavTab)}
                className={`p-3.5 rounded-2xl border transition-all duration-200 cursor-pointer hover:shadow-xl hover:-translate-y-1 flex flex-col items-center text-center justify-between min-h-[135px] group ${
                  isLight
                    ? 'bg-slate-50/90 border-slate-200 hover:border-blue-400 hover:bg-blue-50/40'
                    : 'bg-slate-800/80 border-slate-700 hover:border-blue-500 hover:bg-slate-800'
                }`}
              >
                <div className={`w-11 h-11 rounded-xl ${mod.color} flex items-center justify-center font-bold shadow-md group-hover:scale-110 transition-transform mb-2`}>
                  <Icon className="w-5 h-5" />
                </div>

                <div className="space-y-0.5">
                  <h4 className={`font-extrabold text-xs sm:text-sm leading-tight ${isLight ? 'text-slate-900' : 'text-white'}`}>
                    {mod.title}
                  </h4>
                  <p className="text-[10px] text-slate-500 line-clamp-1">
                    {mod.sub}
                  </p>
                </div>

                <span className="mt-2 text-[10px] font-extrabold px-2 py-0.5 rounded-full bg-blue-500/10 text-blue-600 dark:text-blue-400 border border-blue-200 dark:border-blue-800/40">
                  {mod.badge}
                </span>
              </div>
            );
          })}
        </div>
      </div>

      {/* QUICK ACTION BUTTONS TOOLBAR */}
      <div className="grid grid-cols-2 sm:grid-cols-4 gap-2.5">
        <button
          onClick={() => onNavigateTab('pos')}
          className="p-3.5 rounded-xl bg-emerald-600 hover:bg-emerald-700 text-white font-extrabold text-xs flex items-center justify-center gap-2 shadow-md transition-all active:scale-95 cursor-pointer"
        >
          <ShoppingCart className="w-4 h-4 shrink-0" />
          <span>{isEn ? 'New POS Sale' : 'موبائل فروخت (POS)'}</span>
        </button>

        <button
          onClick={onOpenNewTransaction}
          className="p-3.5 rounded-xl bg-blue-700 hover:bg-blue-800 text-white font-extrabold text-xs flex items-center justify-center gap-2 shadow-md transition-all active:scale-95 cursor-pointer"
        >
          <PlusCircle className="w-4 h-4 shrink-0" />
          <span>{isEn ? 'EasyPaisa Entry' : 'ایزی پیسہ ٹرانزیکشن'}</span>
        </button>

        <button
          onClick={onOpenNewExpense}
          className={`p-3.5 rounded-xl border font-extrabold text-xs flex items-center justify-center gap-2 transition-all active:scale-95 cursor-pointer ${
            isLight ? 'bg-white border-rose-300 text-rose-700 hover:bg-rose-50' : 'bg-slate-800 border-rose-500/30 text-rose-300 hover:bg-slate-750'
          }`}
        >
          <MinusCircle className="w-4 h-4 text-rose-500 shrink-0" />
          <span>{isEn ? 'Add Shop Expense' : 'دکان کا خرچہ درج کریں'}</span>
        </button>

        <button
          onClick={handleDownloadClosingPDF}
          className={`p-3.5 rounded-xl border font-extrabold text-xs flex items-center justify-center gap-2 transition-all active:scale-95 cursor-pointer ${
            isLight ? 'bg-white border-slate-300 text-slate-800 hover:bg-slate-100' : 'bg-slate-800 border-slate-700 text-slate-200 hover:bg-slate-750'
          }`}
        >
          <FileDown className="w-4 h-4 text-blue-600 dark:text-blue-400 shrink-0" />
          <span>{isEn ? 'Export Daily Closing' : 'روزنامچہ PDF ڈاون لوڈ'}</span>
        </button>
      </div>

      {/* TODAY'S TRANSACTIONS TABLE SECTION */}
      <div className={`${isLight ? 'bg-white border-slate-200' : 'bg-slate-900 border-slate-800'} border rounded-2xl overflow-hidden shadow-xl transition-colors`}>
        
        {/* Table Top Toolbar */}
        <div className={`p-3.5 sm:p-4 ${isLight ? 'bg-slate-50 border-slate-200' : 'bg-slate-800/60 border-slate-800'} border-b flex flex-col sm:flex-row items-stretch sm:items-center justify-between gap-3`}>
          <div className="flex items-center gap-2">
            <h3 className={`font-extrabold text-xs sm:text-sm ${isLight ? 'text-slate-900' : 'text-white'}`}>
              {isEn ? 'Today EasyPaisa & Cash Entries' : 'آج کے اندارجات (Today Entries)'}
            </h3>
            <span className={`px-2 py-0.5 rounded-full text-xs font-mono font-bold ${isLight ? 'bg-slate-200 text-slate-800' : 'bg-slate-800 text-slate-300'}`}>
              {filteredTrx.length} {isEn ? 'Records' : 'اندراجات'}
            </span>
          </div>

          <div className="flex items-center gap-2 w-full sm:w-auto">
            {/* Search */}
            <div className="relative flex-1 sm:w-64">
              <Search className="w-4 h-4 text-slate-400 absolute left-3 top-2.5" />
              <input
                type="text"
                placeholder={isEn ? 'Search customer or TRX ID...' : 'Grahak ya TRX ID talash karein...'}
                value={searchTerm}
                onChange={(e) => setSearchTerm(e.target.value)}
                className={`w-full pl-9 pr-3 py-1.5 ${isLight ? 'bg-white border-slate-300 text-slate-900' : 'bg-slate-800 border-slate-700 text-white'} border rounded-xl text-xs outline-none focus:border-blue-500`}
              />
            </div>

            {/* Filter Tabs */}
            <select
              value={filterType}
              onChange={(e) => setFilterType(e.target.value)}
              className={`px-2.5 py-1.5 ${isLight ? 'bg-white border-slate-300 text-slate-900' : 'bg-slate-800 border-slate-700 text-white'} border rounded-xl text-xs outline-none focus:border-blue-500 font-semibold`}
            >
              <option value="ALL">All Types</option>
              <option value="BUY">BUY EasyPaisa</option>
              <option value="SELL">SELL EasyPaisa</option>
              <option value="EXPENSE">Expense/Loss</option>
            </select>
          </div>
        </div>

        {/* Transactions List Table */}
        <div className="overflow-x-auto">
          <table className="w-full text-left text-xs">
            <thead className={`${isLight ? 'bg-slate-100 text-slate-700' : 'bg-slate-950 text-slate-400'} uppercase font-bold text-[11px] border-b ${isLight ? 'border-slate-200' : 'border-slate-800'}`}>
              <tr>
                <th className="py-3 px-3 sm:px-4">Time</th>
                <th className="py-3 px-3 sm:px-4">Type</th>
                <th className="py-3 px-3 sm:px-4">Customer Name</th>
                <th className="py-3 px-3 sm:px-4">TRX ID / Ref</th>
                <th className="py-3 px-3 sm:px-4 text-right">EP Amount</th>
                <th className="py-3 px-3 sm:px-4 text-right">Cash Handled</th>
                <th className="py-3 px-3 sm:px-4 text-right">Profit / Fee</th>
                <th className="py-3 px-3 sm:px-4 text-center">Voucher</th>
              </tr>
            </thead>
            <tbody className={`divide-y ${isLight ? 'divide-slate-200' : 'divide-slate-800/60'} font-medium`}>
              {filteredTrx.length === 0 ? (
                <tr>
                  <td colSpan={8} className="py-8 text-center text-slate-500">
                    <p className="text-sm font-bold">{isEn ? `No Entries Found (${selectedDate})` : `کوئی اندراج نہیں ملا (${selectedDate})`}</p>
                    <p className="text-xs mt-1">{isEn ? 'Click the buttons above to add a new transaction or sale.' : 'نیا اندراج کرنے کے لیے اوپر والے بٹن پر کلک کریں۔'}</p>
                  </td>
                </tr>
              ) : (
                filteredTrx.map((t) => {
                  const isBuy = t.type === 'BUY_EASYPAISA' || t.type === 'BUY_CASH';
                  const isSell = t.type === 'SELL_EASYPAISA' || t.type === 'SELL_CASH';
                  const isExpense = t.type === 'EXPENSE' || t.type === 'DISCREPANCY_LOSS';

                  return (
                    <tr key={t.id} className={`${isLight ? 'hover:bg-slate-50' : 'hover:bg-slate-800/40'} transition-colors`}>
                      <td className={`py-3 px-3 sm:px-4 font-mono ${isLight ? 'text-slate-600' : 'text-slate-400'}`}>{t.time}</td>
                      <td className="py-3 px-3 sm:px-4">
                        {isBuy && (
                          <span className={`inline-flex items-center gap-1 px-2 py-0.5 rounded-full ${isLight ? 'bg-emerald-100 text-emerald-800 border-emerald-300' : 'bg-emerald-500/10 text-emerald-400 border-emerald-500/20'} border font-bold text-[11px]`}>
                            <ArrowDownRight className="w-3 h-3" /> BUY EP
                          </span>
                        )}
                        {isSell && (
                          <span className={`inline-flex items-center gap-1 px-2 py-0.5 rounded-full ${isLight ? 'bg-amber-100 text-amber-800 border-amber-300' : 'bg-amber-500/10 text-amber-400 border-amber-500/20'} border font-bold text-[11px]`}>
                            <ArrowUpRight className="w-3 h-3" /> SELL EP
                          </span>
                        )}
                        {isExpense && (
                          <span className={`inline-flex items-center gap-1 px-2 py-0.5 rounded-full ${isLight ? 'bg-rose-100 text-rose-800 border-rose-300' : 'bg-rose-500/10 text-rose-400 border-rose-500/20'} border font-bold text-[11px]`}>
                            <TrendingDown className="w-3 h-3" /> EXPENSE
                          </span>
                        )}
                      </td>
                      <td className={`py-3 px-3 sm:px-4 font-semibold ${isLight ? 'text-slate-900' : 'text-white'}`}>
                        {t.customerName}
                        {t.customerPhone && <span className={`block text-[10px] font-mono ${isLight ? 'text-slate-500' : 'text-slate-400'}`}>{t.customerPhone}</span>}
                      </td>
                      <td className={`py-3 px-3 sm:px-4 font-mono ${isLight ? 'text-slate-600' : 'text-slate-400'}`}>
                        {t.trxId ? (
                          <div className="flex flex-col gap-0.5">
                            <span className="font-bold text-slate-800 dark:text-slate-200">{t.trxId}</span>
                            <span className="text-[10px]">
                              {getPaymentChannelInfo(t.paymentMethod).emoji} {getPaymentChannelInfo(t.paymentMethod).nameEn}
                            </span>
                          </div>
                        ) : (
                          <span>{getPaymentChannelInfo(t.paymentMethod).emoji} {getPaymentChannelInfo(t.paymentMethod).nameEn}</span>
                        )}
                      </td>
                      <td className={`py-3 px-3 sm:px-4 text-right font-mono ${isLight ? 'text-slate-900' : 'text-white'}`}>
                        {isExpense ? '-' : `Rs. ${t.easyPaisaAmount.toLocaleString()}`}
                      </td>
                      <td className={`py-3 px-3 sm:px-4 text-right font-mono ${isLight ? 'text-slate-700' : 'text-slate-300'}`}>
                        {isExpense ? '-' : `Rs. ${t.cashAmount.toLocaleString()}`}
                      </td>
                      <td className="py-3 px-3 sm:px-4 text-right font-mono font-bold">
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
                            className={`p-1.5 rounded-lg ${isLight ? 'bg-slate-100 hover:bg-slate-200 text-blue-700' : 'bg-slate-800 hover:bg-slate-700 text-blue-400'} transition-colors cursor-pointer`}
                            title="View Voucher Receipt"
                          >
                            <Eye className="w-3.5 h-3.5" />
                          </button>
                          <button
                            onClick={() => onDeleteTransaction(t.id)}
                            className={`p-1.5 rounded-lg ${isLight ? 'bg-slate-100 hover:bg-rose-100 text-rose-600' : 'bg-slate-800 hover:bg-rose-950 text-rose-400'} transition-colors cursor-pointer`}
                            title="Delete Entry"
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

