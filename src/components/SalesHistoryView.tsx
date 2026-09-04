import React, { useState } from 'react';
import { ProductSale, AppSettings } from '../types';
import { ShoppingCart, Search, FileDown, Eye, Calendar, DollarSign, TrendingUp, Receipt, X } from 'lucide-react';
import jsPDF from 'jspdf';
import autoTable from 'jspdf-autotable';
import { ProductInvoiceModal } from './ProductInvoiceModal';

interface SalesHistoryViewProps {
  productSales: ProductSale[];
  settings: AppSettings;
}

export const SalesHistoryView: React.FC<SalesHistoryViewProps> = ({
  productSales,
  settings,
}) => {
  const [searchTerm, setSearchTerm] = useState('');
  const [startDate, setStartDate] = useState(() => {
    const d = new Date();
    d.setMonth(d.getMonth() - 1); // Default last 1 month
    return d.toISOString().split('T')[0];
  });
  const [endDate, setEndDate] = useState(new Date().toISOString().split('T')[0]);
  const [selectedInvoice, setSelectedInvoice] = useState<ProductSale | null>(null);

  const isLight = settings.theme === 'light';

  // Filter sales by date range and search term
  const filteredSales = productSales.filter((sale) => {
    const inDateRange = sale.date >= startDate && sale.date <= endDate;
    const matchSearch =
      !searchTerm ||
      sale.invoiceNo.toLowerCase().includes(searchTerm.toLowerCase()) ||
      (sale.customerName && sale.customerName.toLowerCase().includes(searchTerm.toLowerCase())) ||
      (sale.customerPhone && sale.customerPhone.includes(searchTerm.toLowerCase())) ||
      sale.items.some(i => i.productName.toLowerCase().includes(searchTerm.toLowerCase()));
    
    return inDateRange && matchSearch;
  }).sort((a, b) => b.createdAt - a.createdAt);

  const totalRevenue = filteredSales.reduce((acc, s) => acc + s.netAmount, 0);
  const totalProfit = filteredSales.reduce((acc, s) => acc + s.profit, 0);
  const totalCost = totalRevenue - totalProfit;
  const totalBills = filteredSales.length;

  const handleDownloadPDF = () => {
    const doc = new jsPDF();
    doc.setFillColor(16, 185, 129); // Emerald header
    doc.rect(0, 0, 210, 36, 'F');
    doc.setTextColor(255, 255, 255);
    doc.setFontSize(15);
    doc.setFont('helvetica', 'bold');
    doc.text(settings.shopName || 'Mobiles and EasyPaisa Shop POS', 105, 12, { align: 'center' });
    doc.setFontSize(11);
    doc.text('SALES & PROFIT STATEMENT LEDGER', 105, 20, { align: 'center' });
    doc.setFontSize(8);
    doc.text(`Period: ${startDate} to ${endDate} | Contact: ${settings.phone || '03319348330'}`, 105, 28, { align: 'center' });

    const rows = filteredSales.map((s, idx) => [
      idx + 1,
      s.invoiceNo,
      `${s.date} ${s.time || ''}`,
      s.customerName || 'Walk-in',
      s.items.map(i => `${i.productName} (x${i.quantity})`).join(', '),
      `Rs. ${s.netAmount.toLocaleString()}`,
      `Rs. ${s.profit.toLocaleString()}`
    ]);

    autoTable(doc, {
      startY: 42,
      head: [['#', 'Invoice No', 'Date & Time', 'Customer', 'Items Sold', 'Net Sale', 'Profit']],
      body: rows,
      theme: 'striped',
      headStyles: { fillColor: [16, 185, 129] },
      styles: { fontSize: 8, cellPadding: 3 },
    });

    const finalY = (doc as any).lastAutoTable.finalY + 10;
    doc.setFontSize(10);
    doc.setFont('helvetica', 'bold');
    doc.setTextColor(30, 41, 59);
    doc.text(`Total Bills: ${totalBills}`, 14, finalY);
    doc.text(`Total Revenue: Rs. ${totalRevenue.toLocaleString()}`, 14, finalY + 6);
    doc.text(`Total Profit: Rs. ${totalProfit.toLocaleString()}`, 14, finalY + 12);

    doc.save(`Sales_Profit_Statement_${startDate}_to_${endDate}.pdf`);
  };

  return (
    <div className="space-y-6 pb-20 font-sans">
      
      {/* Header & Date Range Filter Bar */}
      <div className={`${isLight ? 'bg-white border-slate-200' : 'bg-slate-900 border-slate-800'} border p-4 sm:p-6 rounded-3xl shadow-sm space-y-4`}>
        <div className="flex flex-col sm:flex-row items-stretch sm:items-center justify-between gap-4">
          <div className="flex items-center gap-3">
            <div className="w-12 h-12 rounded-2xl bg-emerald-500/10 text-emerald-600 dark:text-emerald-400 flex items-center justify-center font-bold shrink-0">
              <ShoppingCart className="w-6 h-6" />
            </div>
            <div>
              <h1 className={`text-xl font-black ${isLight ? 'text-slate-900' : 'text-white'}`}>
                {settings.language === 'en' ? 'Sales & Profit Ledger' : 'فروخت اور منافع کا ریکارڈ'}
              </h1>
              <p className={`text-xs ${isLight ? 'text-slate-600' : 'text-slate-400'}`}>
                View all sales bills, invoices, net revenue, and item-wise profit with date-to-date statements.
              </p>
            </div>
          </div>

          <div className="flex items-center gap-2">
            <div className="relative flex-1 sm:w-64">
              <Search className="w-4 h-4 text-slate-400 absolute left-3 top-3" />
              <input
                type="text"
                placeholder="Search invoice, customer, item..."
                value={searchTerm}
                onChange={(e) => setSearchTerm(e.target.value)}
                className={`w-full pl-9 pr-3 py-2.5 ${isLight ? 'bg-slate-50 border-slate-300 text-slate-900' : 'bg-slate-800 border-slate-700 text-white'} border rounded-xl text-xs font-medium outline-none focus:border-emerald-500`}
              />
            </div>
            <button
              onClick={handleDownloadPDF}
              className="flex items-center gap-2 px-4 py-2.5 bg-emerald-600 hover:bg-emerald-700 text-white rounded-xl text-xs font-bold transition-all shadow-md shrink-0 cursor-pointer"
            >
              <FileDown className="w-4 h-4" />
              <span>PDF Statement</span>
            </button>
          </div>
        </div>

        {/* Date Range Filter Row */}
        <div className={`grid grid-cols-1 sm:grid-cols-2 gap-3 pt-4 border-t ${isLight ? 'border-slate-200' : 'border-slate-800'}`}>
          <div>
            <label className={`block text-[11px] font-bold mb-1 ${isLight ? 'text-slate-700' : 'text-slate-400'}`}>Start Date (از تاریخ):</label>
            <input
              type="date"
              value={startDate}
              onChange={(e) => setStartDate(e.target.value)}
              className={`w-full px-3 py-2 ${isLight ? 'bg-slate-50 border-slate-300 text-slate-900' : 'bg-slate-800 border-slate-700 text-white'} border rounded-xl text-xs font-mono outline-none focus:border-emerald-500`}
            />
          </div>
          <div>
            <label className={`block text-[11px] font-bold mb-1 ${isLight ? 'text-slate-700' : 'text-slate-400'}`}>End Date (تا تاریخ):</label>
            <input
              type="date"
              value={endDate}
              onChange={(e) => setEndDate(e.target.value)}
              className={`w-full px-3 py-2 ${isLight ? 'bg-slate-50 border-slate-300 text-slate-900' : 'bg-slate-800 border-slate-700 text-white'} border rounded-xl text-xs font-mono outline-none focus:border-emerald-500`}
            />
          </div>
        </div>
      </div>

      {/* KPI Cards Summary */}
      <div className="grid grid-cols-2 lg:grid-cols-4 gap-3 sm:gap-4">
        <div className={`${isLight ? 'bg-white border-slate-200' : 'bg-slate-900 border-slate-800'} border p-4 rounded-2xl shadow-sm space-y-1`}>
          <div className="flex items-center justify-between text-slate-500 text-xs font-bold">
            <span>Total Sales Revenue</span>
            <DollarSign className="w-4 h-4 text-emerald-600" />
          </div>
          <div className={`text-lg sm:text-xl font-black ${isLight ? 'text-slate-900' : 'text-white'}`}>
            Rs. {totalRevenue.toLocaleString()}
          </div>
          <p className="text-[10px] text-emerald-600 font-bold">{totalBills} Bills in period</p>
        </div>

        <div className={`${isLight ? 'bg-white border-slate-200' : 'bg-slate-900 border-slate-800'} border p-4 rounded-2xl shadow-sm space-y-1`}>
          <div className="flex items-center justify-between text-slate-500 text-xs font-bold">
            <span>Total Profit</span>
            <TrendingUp className="w-4 h-4 text-emerald-600" />
          </div>
          <div className={`text-lg sm:text-xl font-black text-emerald-600`}>
            Rs. {totalProfit.toLocaleString()}
          </div>
          <p className="text-[10px] text-slate-500">Net earnings from items</p>
        </div>

        <div className={`${isLight ? 'bg-white border-slate-200' : 'bg-slate-900 border-slate-800'} border p-4 rounded-2xl shadow-sm space-y-1`}>
          <div className="flex items-center justify-between text-slate-500 text-xs font-bold">
            <span>Total Cost</span>
            <Receipt className="w-4 h-4 text-blue-600" />
          </div>
          <div className={`text-lg sm:text-xl font-black ${isLight ? 'text-slate-900' : 'text-white'}`}>
            Rs. {totalCost.toLocaleString()}
          </div>
          <p className="text-[10px] text-slate-500">Purchase value of items sold</p>
        </div>

        <div className={`${isLight ? 'bg-white border-slate-200' : 'bg-slate-900 border-slate-800'} border p-4 rounded-2xl shadow-sm space-y-1`}>
          <div className="flex items-center justify-between text-slate-500 text-xs font-bold">
            <span>Total Bills Count</span>
            <ShoppingCart className="w-4 h-4 text-purple-600" />
          </div>
          <div className={`text-lg sm:text-xl font-black ${isLight ? 'text-slate-900' : 'text-white'}`}>
            {totalBills}
          </div>
          <p className="text-[10px] text-purple-600 font-bold">Invoices generated</p>
        </div>
      </div>

      {/* Sales Invoices Table */}
      <div className={`${isLight ? 'bg-white border-slate-200' : 'bg-slate-900 border-slate-800'} border rounded-3xl overflow-hidden shadow-sm`}>
        <div className={`p-4 border-b ${isLight ? 'border-slate-200 bg-slate-50' : 'border-slate-800 bg-slate-800/50'} flex justify-between items-center`}>
          <h3 className={`font-extrabold text-xs sm:text-sm uppercase ${isLight ? 'text-slate-800' : 'text-slate-200'}`}>
            Sales Invoices ({filteredSales.length})
          </h3>
          <span className="text-xs font-mono text-slate-500 font-bold">
            {startDate} to {endDate}
          </span>
        </div>

        {filteredSales.length === 0 ? (
          <div className="p-12 text-center space-y-3">
            <ShoppingCart className="w-10 h-10 text-slate-300 mx-auto" />
            <p className="text-sm font-bold text-slate-500">No sales found in this date range.</p>
          </div>
        ) : (
          <div className="overflow-x-auto">
            <table className="w-full text-left text-xs">
              <thead className={`${isLight ? 'bg-slate-100 text-slate-700' : 'bg-slate-800 text-slate-300'} font-black uppercase text-[10px]`}>
                <tr>
                  <th className="p-3">#</th>
                  <th className="p-3">Invoice No</th>
                  <th className="p-3">Date & Time</th>
                  <th className="p-3">Customer</th>
                  <th className="p-3">Items Sold</th>
                  <th className="p-3">Payment</th>
                  <th className="p-3 text-right">Net Amount</th>
                  <th className="p-3 text-right">Profit</th>
                  <th className="p-3 text-center">Action</th>
                </tr>
              </thead>
              <tbody className={`divide-y ${isLight ? 'divide-slate-200 text-slate-800' : 'divide-slate-800 text-slate-200'}`}>
                {filteredSales.map((sale, idx) => (
                  <tr key={sale.id} className={`hover:bg-slate-50 dark:hover:bg-slate-800/60 transition-colors`}>
                    <td className="p-3 font-mono font-bold text-slate-400">{idx + 1}</td>
                    <td className="p-3 font-mono font-extrabold text-emerald-600">{sale.invoiceNo}</td>
                    <td className="p-3 font-mono text-[11px]">{sale.date} <span className="text-slate-400">{sale.time}</span></td>
                    <td className="p-3 font-bold">{sale.customerName || 'Walk-in Customer'}</td>
                    <td className="p-3 max-w-xs truncate" title={sale.items.map(i => `${i.productName} (x${i.quantity})`).join(', ')}>
                      {sale.items.map(i => `${i.productName} (x${i.quantity})`).join(', ')}
                    </td>
                    <td className="p-3">
                      <span className="px-2 py-0.5 bg-slate-100 dark:bg-slate-800 text-slate-700 dark:text-slate-300 font-bold rounded text-[10px]">
                        {sale.paymentMethod}
                      </span>
                    </td>
                    <td className="p-3 text-right font-mono font-black text-slate-900 dark:text-white">
                      Rs. {sale.netAmount.toLocaleString()}
                    </td>
                    <td className="p-3 text-right font-mono font-black text-emerald-600">
                      + Rs. {sale.profit.toLocaleString()}
                    </td>
                    <td className="p-3 text-center">
                      <button
                        onClick={() => setSelectedInvoice(sale)}
                        className="py-1.5 px-3 bg-emerald-50 dark:bg-emerald-950/60 hover:bg-emerald-100 text-emerald-700 dark:text-emerald-400 font-bold text-xs rounded-xl flex items-center justify-center gap-1 mx-auto transition-colors cursor-pointer"
                        title="View Invoice & Print"
                      >
                        <Eye className="w-3.5 h-3.5" />
                        <span>View</span>
                      </button>
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        )}
      </div>

      {/* Invoice Modal */}
      <ProductInvoiceModal
        isOpen={!!selectedInvoice}
        onClose={() => setSelectedInvoice(null)}
        sale={selectedInvoice}
        settings={settings}
      />
    </div>
  );
};
