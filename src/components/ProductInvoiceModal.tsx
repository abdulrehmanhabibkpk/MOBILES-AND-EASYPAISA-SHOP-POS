import React, { useState } from 'react';
import { X, Printer, Download, Smartphone, CheckCircle2, ShieldCheck, Share2, FileText, Receipt } from 'lucide-react';
import { ProductSale, AppSettings } from '../types';
import jsPDF from 'jspdf';
import autoTable from 'jspdf-autotable';

interface ProductInvoiceModalProps {
  isOpen: boolean;
  onClose: () => void;
  sale: ProductSale | null;
  settings: AppSettings;
}

export const ProductInvoiceModal: React.FC<ProductInvoiceModalProps> = ({
  isOpen,
  onClose,
  sale,
  settings,
}) => {
  const [receiptType, setReceiptType] = useState<'thermal' | 'modern'>('thermal');
  const [thermalSize, setThermalSize] = useState<'85mm' | '83mm' | '58mm'>('85mm');

  if (!isOpen || !sale) return null;

  const handlePrint = () => {
    window.print();
  };

  const handleWhatsAppShare = () => {
    const itemLines = sale.items
      .map((item, idx) => `${idx + 1}. ${item.productName} (x${item.quantity}) = Rs. ${item.totalSalePrice.toLocaleString()}`)
      .join('\n');

    const msg = `🧾 *Sale Bill Receipt*\n*${settings.shopName || 'Mobiles and EasyPaisa Shop POS'}*\n📍 ${settings.address || 'GT Road Sarai Saleh'}\n📞 Contact: ${settings.ownerName || 'Umer Ali'} (${settings.phone || '03319348330'})\n-------------------------\n*Invoice #: ${sale.invoiceNo}*\n*Date:* ${sale.date} ${sale.time}\n*Customer:* ${sale.customerName || 'Walk-in Customer'}\n-------------------------\n${itemLines}\n-------------------------\n*Subtotal:* Rs. ${sale.totalAmount.toLocaleString()}\n${sale.discount > 0 ? `*Discount:* Rs. ${sale.discount.toLocaleString()}\n` : ''}*Net Paid Amount:* Rs. ${sale.netAmount.toLocaleString()}\n*Payment Method:* ${sale.paymentMethod}\n-------------------------\nThank you for shopping with us!`;

    const url = sale.customerPhone 
      ? `https://wa.me/${sale.customerPhone.replace(/[^0-9]/g, '')}?text=${encodeURIComponent(msg)}`
      : `https://wa.me/?text=${encodeURIComponent(msg)}`;

    window.open(url, '_blank');
  };

  const handleDownloadPDF = () => {
    const doc = new jsPDF();

    // Header
    doc.setFillColor(30, 64, 175); // Blue header
    doc.rect(0, 0, 210, 32, 'F');

    doc.setTextColor(255, 255, 255);
    doc.setFontSize(16);
    doc.setFont('helvetica', 'bold');
    doc.text(settings.shopName || 'Mobiles and EasyPaisa Shop POS', 14, 14);

    doc.setFontSize(9);
    doc.setFont('helvetica', 'normal');
    doc.text(`Contact: ${settings.ownerName || 'Umer Ali'} (${settings.phone || '03319348330'}) | Address: ${settings.address || 'GT Road Sarai Saleh'}`, 14, 23);
    doc.text('RETAIL SALE INVOICE RECEIPT', 14, 28);

    // Bill Details Box
    doc.setTextColor(30, 41, 59);
    doc.setFontSize(10);
    doc.setFont('helvetica', 'bold');
    doc.text(`INVOICE #: ${sale.invoiceNo}`, 14, 42);
    doc.text(`Date & Time: ${sale.date} ${sale.time}`, 130, 42);

    doc.setFont('helvetica', 'normal');
    doc.text(`Customer Name: ${sale.customerName || 'Walk-in Customer'}`, 14, 48);
    doc.text(`Customer Phone: ${sale.customerPhone || 'N/A'}`, 14, 54);
    doc.text(`Payment Method: ${sale.paymentMethod}`, 130, 48);

    // Table
    const tableData = sale.items.map((item, idx) => [
      idx + 1,
      item.productName,
      item.quantity,
      `Rs. ${item.unitSalePrice.toLocaleString()}`,
      `Rs. ${item.totalSalePrice.toLocaleString()}`,
    ]);

    autoTable(doc, {
      startY: 60,
      head: [['#', 'Item Description', 'Qty', 'Unit Price', 'Total']],
      body: tableData,
      theme: 'grid',
      headStyles: { fillColor: [30, 64, 175], textColor: [255, 255, 255], fontStyle: 'bold' },
      styles: { fontSize: 9 },
    });

    // Totals
    const finalY = (doc as any).lastAutoTable.finalY + 10;
    doc.setFontSize(10);
    doc.setFont('helvetica', 'normal');
    doc.text(`Subtotal: Rs. ${sale.totalAmount.toLocaleString()}`, 130, finalY);
    if (sale.discount > 0) {
      doc.text(`Discount: -Rs. ${sale.discount.toLocaleString()}`, 130, finalY + 6);
    }

    doc.setFontSize(12);
    doc.setFont('helvetica', 'bold');
    doc.setTextColor(16, 185, 129);
    doc.text(`Net Amount Paid: Rs. ${sale.netAmount.toLocaleString()}`, 130, finalY + (sale.discount > 0 ? 14 : 8));

    // Footer
    doc.setFontSize(9);
    doc.setFont('helvetica', 'italic');
    doc.setTextColor(100, 116, 139);
    doc.text('Thank you for shopping with us!', 105, 280, { align: 'center' });

    doc.save(`Invoice_${sale.invoiceNo}.pdf`);
  };

  return (
    <div className="fixed inset-0 z-50 flex items-center justify-center bg-slate-950/80 backdrop-blur-sm p-2 sm:p-4 overflow-y-auto print:p-0 print:bg-white print:static print:block">
      
      {/* Global Thermal Printer Page Styles when printing */}
      <style>{`
        @media print {
          @page {
            size: ${receiptType === 'thermal' ? `${thermalSize} auto` : 'A4'};
            margin: ${receiptType === 'thermal' ? '0mm' : '10mm'};
          }
          body {
            background: white !important;
            color: black !important;
          }
          .no-print {
            display: none !important;
          }
          .thermal-print-area {
            width: ${receiptType === 'thermal' ? thermalSize : '100%'} !important;
            margin: 0 auto !important;
            padding: ${receiptType === 'thermal' ? (thermalSize === '58mm' ? '1mm' : '4mm') : '0mm'} !important;
          }
        }
      `}</style>

      <div 
        style={receiptType === 'thermal' ? { maxWidth: thermalSize, width: '100%' } : undefined}
        className={`w-full ${receiptType === 'thermal' ? '' : 'max-w-2xl'} bg-white border border-slate-200 rounded-2xl shadow-2xl overflow-hidden text-slate-800 my-auto max-h-[95vh] flex flex-col transition-all duration-300 print:max-h-none print:shadow-none print:border-none print:m-0 print:w-full print:rounded-none thermal-print-area`}
      >
        
        {/* Modal Top Header with Receipt Selector (Screen Only) */}
        <div className="bg-slate-900 text-white p-3 sm:p-4 shrink-0 print:hidden space-y-3">
          <div className="flex items-center justify-between">
            <div className="flex items-center gap-2">
              <div className="w-8 h-8 rounded-xl bg-emerald-500/20 text-emerald-400 border border-emerald-500/30 flex items-center justify-center">
                <Receipt className="w-4 h-4" />
              </div>
              <div>
                <h2 className="font-extrabold text-sm sm:text-base leading-tight">Sale Bill Receipt</h2>
                <p className="text-[11px] text-slate-400">Invoice #: {sale.invoiceNo}</p>
              </div>
            </div>

            <button
              onClick={onClose}
              className="w-8 h-8 rounded-xl bg-white/10 hover:bg-white/20 text-white flex items-center justify-center transition-colors cursor-pointer"
            >
              <X className="w-4 h-4" />
            </button>
          </div>

          {/* TOGGLE SWITCHER BETWEEN THERMAL & MODERN BILL */}
          <div className="grid grid-cols-2 gap-1.5 p-1 bg-slate-800 rounded-xl text-xs font-bold">
            <button
              type="button"
              onClick={() => setReceiptType('thermal')}
              className={`py-2 px-3 rounded-lg flex items-center justify-center gap-2 transition-all cursor-pointer ${
                receiptType === 'thermal'
                  ? 'bg-emerald-600 text-white shadow-md'
                  : 'text-slate-300 hover:text-white hover:bg-slate-700/50'
              }`}
            >
              <Printer className="w-3.5 h-3.5" />
              <span>Thermal Receipt (POS)</span>
            </button>

            <button
              type="button"
              onClick={() => setReceiptType('modern')}
              className={`py-2 px-3 rounded-lg flex items-center justify-center gap-2 transition-all cursor-pointer ${
                receiptType === 'modern'
                  ? 'bg-blue-600 text-white shadow-md'
                  : 'text-slate-300 hover:text-white hover:bg-slate-700/50'
              }`}
            >
              <FileText className="w-3.5 h-3.5" />
              <span>Detailed Modern Invoice</span>
            </button>
          </div>

          {/* THERMAL PAPER SIZE SELECTOR */}
          {receiptType === 'thermal' && (
            <div className="space-y-1 bg-slate-950 p-2.5 rounded-xl border border-slate-800">
              <label className="block text-[10px] font-black uppercase text-emerald-400 tracking-wider">
                Select Thermal Paper Width:
              </label>
              <div className="grid grid-cols-3 gap-1.5 text-[11px] font-bold text-center">
                {(['85mm', '83mm', '58mm'] as const).map((size) => (
                  <button
                    key={size}
                    type="button"
                    onClick={() => setThermalSize(size)}
                    className={`py-1.5 px-2 rounded-lg transition-all cursor-pointer ${
                      thermalSize === size
                        ? 'bg-emerald-600 text-white shadow-md'
                        : 'bg-slate-900 text-slate-300 hover:text-white hover:bg-slate-800'
                    }`}
                  >
                    {size}
                  </button>
                ))}
              </div>
            </div>
          )}
        </div>

        {/* ========================================================= */}
        {/* OPTION 1: THERMAL POS RECEIPT FORMAT (Matching Reference Image) */}
        {/* ========================================================= */}
        {receiptType === 'thermal' && (
          <div className={`font-mono text-slate-900 overflow-y-auto print:p-1 print:overflow-visible ${
            thermalSize === '58mm' ? 'p-2.5 text-[10px] space-y-1.5' : 'p-4 sm:p-5 text-xs space-y-3'
          }`}>
            
            {/* Shop Header */}
            <div className="text-center border-b border-black border-dashed pb-2 space-y-0.5">
              <h1 className={`font-black uppercase tracking-wider text-slate-900 ${
                thermalSize === '58mm' ? 'text-xs' : 'text-sm sm:text-base'
              }`}>
                {settings.shopName || 'MOBILES & EASYPAISA SHOP POS'}
              </h1>
              <p className={thermalSize === '58mm' ? 'text-[9px] leading-tight' : 'text-[11px] font-medium leading-tight'}>
                {settings.address || 'GT Road Sarai Saleh'}
              </p>
              <p className={`font-bold ${thermalSize === '58mm' ? 'text-[10px]' : 'text-xs'}`}>
                PHONE : {settings.phone || '0331-9348330'}
              </p>
              <div className={`inline-block mt-0.5 font-extrabold uppercase tracking-widest border-y border-black py-0.5 px-2 ${
                thermalSize === '58mm' ? 'text-[9px]' : 'text-xs'
              }`}>
                Retail Invoice
              </div>
            </div>

            {/* Receipt Info */}
            <div className={`space-y-0.5 py-1 ${thermalSize === '58mm' ? 'text-[9px]' : 'text-xs'}`}>
              <p><span className="font-bold">Date : </span>{sale.date}, {sale.time}</p>
              <p><span className="font-bold">Customer : </span>{sale.customerName || 'Walk-in Customer'}</p>
              <p><span className="font-bold">Bill No : </span><span className="font-extrabold">{sale.invoiceNo}</span></p>
              <p><span className="font-bold">Payment Mode : </span>{sale.paymentMethod}</p>
            </div>

            {/* Itemized Table */}
            <div className="border-t border-b border-black border-dashed py-1.5">
              <table className={`w-full font-mono ${thermalSize === '58mm' ? 'text-[9px]' : 'text-xs'}`}>
                <thead>
                  <tr className="border-b border-black border-dashed">
                    <th className="text-left font-extrabold pb-1">Item</th>
                    <th className="text-center font-extrabold pb-1">Qty</th>
                    <th className="text-right font-extrabold pb-1">Amt</th>
                  </tr>
                </thead>
                <tbody className="divide-y divide-slate-200 print:divide-slate-400">
                  {sale.items.map((item, idx) => (
                    <tr key={idx} className="align-top">
                      <td className="py-1 font-medium pr-1">{item.productName}</td>
                      <td className="py-1 text-center font-bold">{item.quantity}</td>
                      <td className="py-1 text-right font-bold">Rs {item.totalSalePrice.toLocaleString()}</td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>

            {/* Totals & Net Summary */}
            <div className={`space-y-1 pt-1 ${thermalSize === '58mm' ? 'text-[9px]' : 'text-xs'}`}>
              <div className="flex justify-between font-semibold">
                <span>Sub Total ({sale.items.reduce((s, i) => s + i.quantity, 0)} items)</span>
                <span>Rs {sale.totalAmount.toLocaleString()}</span>
              </div>

              {sale.discount > 0 && (
                <div className="flex justify-between text-rose-700 font-semibold print:text-black">
                  <span>(-) Discount</span>
                  <span>Rs {sale.discount.toLocaleString()}</span>
                </div>
              )}

              <div className={`flex justify-between items-center font-black border-t border-b border-black py-1.5 mt-1 ${
                thermalSize === '58mm' ? 'text-[11px]' : 'text-sm'
              }`}>
                <span>TOTAL</span>
                <span className={thermalSize === '58mm' ? 'text-xs font-extrabold' : 'text-base font-extrabold'}>Rs {sale.netAmount.toLocaleString()}</span>
              </div>

              <div className="flex justify-between pt-0.5 font-semibold text-[10px]">
                <span>Cash Paid :</span>
                <span>Rs {sale.netAmount.toLocaleString()}</span>
              </div>
            </div>

            {/* Footer */}
            <div className="text-center pt-2 border-t border-black border-dashed space-y-0.5">
              <p className="text-[10px] font-black tracking-widest uppercase">E & O.E</p>
              <p className={`font-bold text-slate-700 print:text-black ${thermalSize === '58mm' ? 'text-[8.5px]' : 'text-[10px]'}`}>
                Thank you for shopping with us!
              </p>
              <p className="text-[8px] text-slate-500 print:text-black">
                Mobiles & EasyPaisa Shop POS System
              </p>
            </div>

          </div>
        )}

        {/* ========================================================= */}
        {/* OPTION 2: DETAILED MODERN INVOICE FORMAT (A4 / Full Page) */}
        {/* ========================================================= */}
        {receiptType === 'modern' && (
          <div className="p-5 sm:p-6 space-y-4 font-sans text-slate-900 overflow-y-auto print:p-2 print:overflow-visible">
            
            {/* Modern Header Banner */}
            <div className="bg-gradient-to-r from-blue-700 via-indigo-700 to-blue-800 p-4 rounded-2xl text-white flex justify-between items-center print:bg-none print:text-black print:border-b-2 print:border-black print:rounded-none print:p-0 print:pb-2">
              <div className="space-y-1">
                <div className="flex items-center gap-2">
                  <Smartphone className="w-5 h-5 text-blue-200 print:text-black" />
                  <h1 className="text-lg font-black uppercase tracking-tight text-white print:text-black">
                    {settings.shopName || 'Mobiles and EasyPaisa Shop POS'}
                  </h1>
                </div>
                <p className="text-xs text-blue-100 print:text-black font-medium">
                  {settings.address || 'GT Road Sarai Saleh'} | Phone: {settings.phone || '0331-9348330'}
                </p>
              </div>

              <div className="text-right print:hidden">
                <span className="px-3 py-1 bg-white/20 text-white rounded-full text-xs font-black uppercase tracking-wider border border-white/30">
                  RETAIL INVOICE
                </span>
              </div>
            </div>

            {/* Invoice Meta Grid */}
            <div className="grid grid-cols-2 sm:grid-cols-3 gap-3 text-xs bg-slate-50 p-3.5 rounded-xl border border-slate-200 print:bg-white print:border-black">
              <div>
                <span className="text-slate-500 block font-semibold">Invoice No:</span>
                <span className="font-mono font-black text-blue-700 text-sm print:text-black">{sale.invoiceNo}</span>
              </div>
              <div>
                <span className="text-slate-500 block font-semibold">Date & Time:</span>
                <span className="font-bold text-slate-800">{sale.date} ({sale.time})</span>
              </div>
              <div>
                <span className="text-slate-500 block font-semibold">Customer Name:</span>
                <span className="font-bold text-slate-900">{sale.customerName || 'Walk-in Customer'}</span>
              </div>
              <div>
                <span className="text-slate-500 block font-semibold">Customer Phone:</span>
                <span className="font-mono font-bold text-slate-800">{sale.customerPhone || 'N/A'}</span>
              </div>
              <div>
                <span className="text-slate-500 block font-semibold">Payment Channel:</span>
                <span className="font-bold text-emerald-700 print:text-black">{sale.paymentMethod}</span>
              </div>
            </div>

            {/* Items Table */}
            <div className="border border-slate-200 rounded-xl overflow-hidden print:border-black">
              <table className="w-full text-xs font-sans">
                <thead className="bg-slate-100 text-slate-700 font-extrabold uppercase border-b border-slate-200 print:bg-slate-200 print:border-black">
                  <tr>
                    <th className="py-2.5 px-3 text-left">#</th>
                    <th className="py-2.5 px-3 text-left">Item Description</th>
                    <th className="py-2.5 px-2 text-center">Qty</th>
                    <th className="py-2.5 px-3 text-right">Unit Price</th>
                    <th className="py-2.5 px-3 text-right">Total Price</th>
                  </tr>
                </thead>
                <tbody className="divide-y divide-slate-100 font-medium text-slate-800 print:divide-slate-300">
                  {sale.items.map((item, idx) => (
                    <tr key={idx}>
                      <td className="py-2.5 px-3 text-slate-400 font-bold">{idx + 1}</td>
                      <td className="py-2.5 px-3 font-bold text-slate-900">{item.productName}</td>
                      <td className="py-2.5 px-2 text-center font-bold">{item.quantity}</td>
                      <td className="py-2.5 px-3 text-right font-mono">Rs {item.unitSalePrice.toLocaleString()}</td>
                      <td className="py-2.5 px-3 text-right font-mono font-extrabold text-slate-900">Rs {item.totalSalePrice.toLocaleString()}</td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>

            {/* Financial Summary Box */}
            <div className="flex justify-end pt-2">
              <div className="w-full sm:w-64 bg-slate-50 p-3.5 rounded-xl border border-slate-200 space-y-2 text-xs print:bg-white print:border-black">
                <div className="flex justify-between text-slate-600">
                  <span>Subtotal:</span>
                  <span className="font-bold text-slate-900">Rs {sale.totalAmount.toLocaleString()}</span>
                </div>
                {sale.discount > 0 && (
                  <div className="flex justify-between text-rose-600 font-bold">
                    <span>Discount:</span>
                    <span>- Rs {sale.discount.toLocaleString()}</span>
                  </div>
                )}
                <div className="flex justify-between text-sm font-black text-emerald-700 pt-2 border-t border-slate-300 print:text-black">
                  <span>NET TOTAL PAID:</span>
                  <span className="font-mono text-base font-extrabold">Rs {sale.netAmount.toLocaleString()}</span>
                </div>
              </div>
            </div>

            {/* Terms & Shop Guarantee */}
            <div className="p-3 bg-blue-50/70 border border-blue-200 rounded-xl text-[11px] text-blue-900 font-medium print:bg-white print:border-black print:text-black">
              <div className="flex items-center gap-1.5 font-bold mb-0.5 text-blue-800 print:text-black">
                <ShieldCheck className="w-4 h-4 text-blue-600 print:text-black" />
                <span>Shop Guarantee & Terms:</span>
              </div>
              <p>
                All sold items are checked before dispatch. Electronics and mobiles carry shop warranty as per agreement. Thank you for choosing {settings.shopName || 'our shop'}.
              </p>
            </div>

            {/* Signature Block */}
            <div className="pt-6 grid grid-cols-2 gap-8 text-center text-xs font-bold border-t border-slate-200 print:pt-8 print:border-black">
              <div className="space-y-6">
                <div className="border-b-2 border-slate-400 w-3/4 mx-auto pt-4 print:border-black" />
                <p className="text-slate-700">Customer Signature</p>
              </div>
              <div className="space-y-6">
                <div className="border-b-2 border-slate-400 w-3/4 mx-auto pt-4 print:border-black" />
                <p className="text-slate-800 font-extrabold">{settings.ownerName || 'Shop Owner'} Stamp & Sign</p>
              </div>
            </div>

          </div>
        )}

        {/* Modal Action Buttons (WhatsApp, PDF, Print) */}
        <div className="p-3 sm:p-4 bg-slate-50 border-t border-slate-200 flex items-center gap-2 print:hidden shrink-0">
          <button
            type="button"
            onClick={handleWhatsAppShare}
            className="flex-1 py-2.5 px-3 rounded-xl bg-emerald-600 hover:bg-emerald-700 text-white font-bold text-xs flex items-center justify-center gap-1.5 transition-colors shadow-sm cursor-pointer"
          >
            <Share2 className="w-4 h-4" />
            <span>WhatsApp Share</span>
          </button>

          <button
            type="button"
            onClick={handleDownloadPDF}
            className="flex-1 py-2.5 px-3 rounded-xl bg-slate-200 hover:bg-slate-300 text-slate-800 font-bold text-xs flex items-center justify-center gap-1.5 transition-colors cursor-pointer"
          >
            <Download className="w-4 h-4 text-slate-700" />
            <span>Download PDF</span>
          </button>

          <button
            type="button"
            onClick={handlePrint}
            className="flex-1 py-2.5 px-3 rounded-xl bg-slate-900 hover:bg-black text-white font-extrabold text-xs flex items-center justify-center gap-1.5 transition-colors shadow-md cursor-pointer"
          >
            <Printer className="w-4 h-4 text-emerald-400" />
            <span>Print Receipt</span>
          </button>
        </div>

      </div>
    </div>
  );
};
