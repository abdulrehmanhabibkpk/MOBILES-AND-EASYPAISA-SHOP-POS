import React, { useState } from 'react';
import { BookOpen, Search, Package, Smartphone, ShoppingCart, FileDown, Calendar, TrendingUp } from 'lucide-react';
import { Product, MobilePurchaseRecord, ProductSale, AppSettings } from '../types';
import jsPDF from 'jspdf';
import autoTable from 'jspdf-autotable';

interface InventoryLedgerViewProps {
  products: Product[];
  mobilePurchases: MobilePurchaseRecord[];
  productSales: ProductSale[];
  settings: AppSettings;
}

export const InventoryLedgerView: React.FC<InventoryLedgerViewProps> = ({
  products,
  mobilePurchases,
  productSales,
  settings,
}) => {
  const [searchTerm, setSearchTerm] = useState('');
  const [filterType, setFilterType] = useState<'ALL' | 'MOBILES' | 'PURCHASES' | 'SALES'>('ALL');

  const isLight = settings.theme === 'light';

  // Calculate totals
  const totalStockInvestment = products.reduce((acc, p) => acc + (p.purchasePrice * p.stock), 0);
  const totalMobileInvestment = mobilePurchases.reduce((acc, p) => acc + p.purchasePrice, 0);
  const totalSalesRevenue = productSales.reduce((acc, s) => acc + s.netAmount, 0);
  const totalSalesProfit = productSales.reduce((acc, s) => acc + s.profit, 0);

  // Combine statements into unified ledger feed
  const ledgerItems = [
    ...mobilePurchases.map((p) => ({
      id: p.id,
      date: p.date,
      time: p.time,
      type: 'MOBILE_PURCHASE' as const,
      title: `${p.mobileBrandModel} (${p.condition})`,
      subtitle: `Supplier/Seller: ${p.sellerName} | IMEI: ${p.imei1}`,
      amount: p.purchasePrice,
      badge: 'Mobile Purchase',
      badgeColor: 'bg-emerald-100 text-emerald-800 dark:bg-emerald-950/50 dark:text-emerald-400',
    })),
    ...products.map((pr) => ({
      id: pr.id,
      date: new Date(pr.createdAt).toISOString().split('T')[0],
      time: '00:00',
      type: 'STOCK_ITEM' as const,
      title: pr.name,
      subtitle: `Category: ${pr.category} | Qty: ${pr.stock}`,
      amount: pr.purchasePrice * pr.stock,
      badge: 'Stock Item',
      badgeColor: 'bg-blue-100 text-blue-800 dark:bg-blue-950/50 dark:text-blue-400',
    })),
    ...productSales.map((s) => ({
      id: s.id,
      date: s.date,
      time: s.time,
      type: 'SALE_INVOICE' as const,
      title: `Sale Invoice #${s.invoiceNo} (${s.items.length} items)`,
      subtitle: `Customer: ${s.customerName || 'Walk-in'} | Profit: Rs. ${s.profit.toLocaleString()}`,
      amount: s.netAmount,
      badge: 'Sale',
      badgeColor: 'bg-purple-100 text-purple-800 dark:bg-purple-950/50 dark:text-purple-400',
    })),
  ].sort((a, b) => b.date.localeCompare(a.date));

  const filteredItems = ledgerItems.filter((item) => {
    const matchSearch =
      item.title.toLowerCase().includes(searchTerm.toLowerCase()) ||
      item.subtitle.toLowerCase().includes(searchTerm.toLowerCase()) ||
      item.date.includes(searchTerm);
    if (!matchSearch) return false;
    if (filterType === 'MOBILES' && item.type !== 'STOCK_ITEM' && !item.title.includes('Mobile')) return false;
    if (filterType === 'PURCHASES' && item.type !== 'MOBILE_PURCHASE') return false;
    if (filterType === 'SALES' && item.type !== 'SALE_INVOICE') return false;
    return true;
  });

  const handleDownloadPDF = () => {
    const doc = new jsPDF();
    doc.setFillColor(16, 185, 129);
    doc.rect(0, 0, 210, 32, 'F');
    doc.setTextColor(255, 255, 255);
    doc.setFontSize(15);
    doc.setFont('helvetica', 'bold');
    doc.text(settings.shopName || 'Mobiles and EasyPaisa Shop POS', 105, 12, { align: 'center' });
    doc.setFontSize(11);
    doc.text('MOBILE & INVENTORY STOCK LEDGER STATEMENT', 105, 20, { align: 'center' });
    doc.setFontSize(8);
    doc.text(`Contact: Umer Ali (${settings.phone || '03319348330'})`, 105, 27, { align: 'center' });

    const rows = filteredItems.map((item, idx) => [
      idx + 1,
      `${item.date} ${item.time !== '00:00' ? item.time : ''}`,
      item.badge,
      item.title,
      item.subtitle,
      `Rs. ${item.amount.toLocaleString()}`
    ]);

    autoTable(doc, {
      startY: 40,
      head: [['#', 'Date & Time', 'Type', 'Description', 'Details', 'Amount']],
      body: rows,
      theme: 'striped',
      headStyles: { fillColor: [16, 185, 129], textColor: 255 },
      styles: { fontSize: 8, cellPadding: 3 },
    });

    doc.save('Inventory_Stock_Ledger.pdf');
  };

  return (
    <div className="space-y-4 sm:space-y-6 font-sans">
      
      {/* Header */}
      <div className={`${isLight ? 'bg-white border-slate-200' : 'bg-slate-900 border-slate-800'} border p-4 sm:p-5 rounded-2xl flex flex-col sm:flex-row items-stretch sm:items-center justify-between gap-3 sm:gap-4 shadow-sm`}>
        <div className="flex items-center gap-3">
          <div className="w-10 h-10 rounded-xl bg-emerald-500/10 text-emerald-500 dark:text-emerald-400 flex items-center justify-center font-bold shrink-0">
            <BookOpen className="w-5 h-5" />
          </div>
          <div>
            <h2 className={`text-base sm:text-lg font-bold ${isLight ? 'text-slate-900' : 'text-white'}`}>Mobile & Inventory Stock Ledger</h2>
            <p className={`text-xs ${isLight ? 'text-slate-600' : 'text-slate-400'}`}>Comprehensive statement of all mobile purchases, stock inventory valuation & sales</p>
          </div>
        </div>

        <div className="flex items-center gap-2">
          <div className="relative flex-1 sm:w-64">
            <Search className="w-4 h-4 text-slate-400 absolute left-3 top-2.5" />
            <input
              type="text"
              placeholder="Search items, model, date..."
              value={searchTerm}
              onChange={(e) => setSearchTerm(e.target.value)}
              className={`w-full pl-9 pr-3 py-2 ${isLight ? 'bg-slate-50 border-slate-300 text-slate-900' : 'bg-slate-800 border-slate-700 text-white'} border rounded-xl text-xs outline-none focus:border-emerald-500`}
            />
          </div>
          <button
            onClick={handleDownloadPDF}
            className="flex items-center gap-1.5 px-4 py-2 bg-emerald-600 hover:bg-emerald-700 text-white rounded-xl text-xs font-bold transition-all shadow-md shrink-0 cursor-pointer"
          >
            <FileDown className="w-4 h-4" />
            <span>PDF Statement</span>
          </button>
        </div>
      </div>

      {/* Summary Metrics Cards */}
      <div className="grid grid-cols-2 lg:grid-cols-4 gap-3 sm:gap-4">
        <div className={`p-4 rounded-2xl border ${isLight ? 'bg-white border-slate-200 text-slate-900' : 'bg-slate-900 border-slate-800 text-white'} shadow-sm`}>
          <div className="flex items-center justify-between mb-2">
            <span className="text-xs text-slate-500 font-semibold">Stock Inventory Value</span>
            <Package className="w-4 h-4 text-emerald-500" />
          </div>
          <p className="text-base sm:text-xl font-bold font-mono">Rs. {totalStockInvestment.toLocaleString()}</p>
          <p className="text-[11px] text-slate-500 mt-1">{products.length} Products in Stock</p>
        </div>

        <div className={`p-4 rounded-2xl border ${isLight ? 'bg-white border-slate-200 text-slate-900' : 'bg-slate-900 border-slate-800 text-white'} shadow-sm`}>
          <div className="flex items-center justify-between mb-2">
            <span className="text-xs text-slate-500 font-semibold">Mobile Purchases Cost</span>
            <Smartphone className="w-4 h-4 text-blue-500" />
          </div>
          <p className="text-base sm:text-xl font-bold font-mono">Rs. {totalMobileInvestment.toLocaleString()}</p>
          <p className="text-[11px] text-slate-500 mt-1">{mobilePurchases.length} Mobiles Bought</p>
        </div>

        <div className={`p-4 rounded-2xl border ${isLight ? 'bg-white border-slate-200 text-slate-900' : 'bg-slate-900 border-slate-800 text-white'} shadow-sm`}>
          <div className="flex items-center justify-between mb-2">
            <span className="text-xs text-slate-500 font-semibold">Total Sales Revenue</span>
            <ShoppingCart className="w-4 h-4 text-purple-500" />
          </div>
          <p className="text-base sm:text-xl font-bold font-mono">Rs. {totalSalesRevenue.toLocaleString()}</p>
          <p className="text-[11px] text-slate-500 mt-1">{productSales.length} POS Invoices</p>
        </div>

        <div className={`p-4 rounded-2xl border ${isLight ? 'bg-white border-slate-200 text-slate-900' : 'bg-slate-900 border-slate-800 text-white'} shadow-sm`}>
          <div className="flex items-center justify-between mb-2">
            <span className="text-xs text-slate-500 font-semibold">Total Sales Profit</span>
            <TrendingUp className="w-4 h-4 text-emerald-500" />
          </div>
          <p className="text-base sm:text-xl font-bold font-mono text-emerald-600">Rs. {totalSalesProfit.toLocaleString()}</p>
          <p className="text-[11px] text-slate-500 mt-1">From POS Sales</p>
        </div>
      </div>

      {/* Filter Tabs */}
      <div className="flex items-center gap-2 overflow-x-auto pb-1">
        {(['ALL', 'MOBILES', 'PURCHASES', 'SALES'] as const).map((type) => (
          <button
            key={type}
            onClick={() => setFilterType(type)}
            className={`px-4 py-2 rounded-xl text-xs font-bold transition-all cursor-pointer whitespace-nowrap ${
              filterType === type
                ? 'bg-emerald-600 text-white shadow-md shadow-emerald-900/20'
                : isLight
                  ? 'bg-white border border-slate-200 text-slate-700 hover:bg-slate-50'
                  : 'bg-slate-900 border border-slate-800 text-slate-300 hover:bg-slate-800'
            }`}
          >
            {type === 'ALL' && 'All Ledger Activity'}
            {type === 'MOBILES' && 'Stock Items'}
            {type === 'PURCHASES' && 'Mobile Purchases'}
            {type === 'SALES' && 'POS Sales Invoices'}
          </button>
        ))}
      </div>

      {/* Ledger Feed Table */}
      <div className={`${isLight ? 'bg-white border-slate-200' : 'bg-slate-900 border-slate-800'} border rounded-2xl p-4 sm:p-5 shadow-sm`}>
        <div className="overflow-x-auto">
          <table className="w-full text-left text-xs">
            <thead className={`${isLight ? 'bg-slate-100 text-slate-700' : 'bg-slate-800 text-slate-300'} font-bold`}>
              <tr>
                <th className="p-3">Date & Time</th>
                <th className="p-3">Activity Type</th>
                <th className="p-3">Description / Details</th>
                <th className="p-3">Sub-details</th>
                <th className="p-3 text-right">Amount (PKR)</th>
              </tr>
            </thead>
            <tbody className="divide-y divide-slate-200 dark:divide-slate-800">
              {filteredItems.length === 0 ? (
                <tr>
                  <td colSpan={5} className="text-center py-12 text-slate-500 text-xs">
                    No ledger activity found matching your search.
                  </td>
                </tr>
              ) : (
                filteredItems.map((item) => (
                  <tr key={item.id} className={`${isLight ? 'hover:bg-slate-50' : 'hover:bg-slate-800/40'}`}>
                    <td className="p-3 font-mono text-[11px] text-slate-500 whitespace-nowrap">
                      {item.date} {item.time !== '00:00' && item.time}
                    </td>
                    <td className="p-3">
                      <span className={`px-2 py-1 rounded-lg text-[10px] font-bold ${item.badgeColor}`}>
                        {item.badge}
                      </span>
                    </td>
                    <td className="p-3 font-bold">{item.title}</td>
                    <td className="p-3 text-slate-500 dark:text-slate-400 text-[11px]">{item.subtitle}</td>
                    <td className="p-3 text-right font-bold font-mono text-emerald-600 dark:text-emerald-400">
                      Rs. {item.amount.toLocaleString()}
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
