import React, { useState, useEffect } from 'react';
import { createPortal } from 'react-dom';
import { MobilePurchaseRecord, AppSettings } from '../types';
import { 
  X, 
  Printer, 
  Share2, 
  CheckCircle2, 
  XCircle, 
  Smartphone, 
  ShieldCheck, 
  User, 
  FileText, 
  FileCheck,
  Calendar,
  Hash,
  MapPin,
  Phone
} from 'lucide-react';

interface SimplePurchaseReceiptModalProps {
  record: MobilePurchaseRecord | null;
  settings: AppSettings;
  onClose: () => void;
}

type CopyMode = 'both' | 'customer' | 'shop';

export const SimplePurchaseReceiptModal: React.FC<SimplePurchaseReceiptModalProps> = ({
  record,
  settings,
  onClose,
}) => {
  const [copyMode, setCopyMode] = useState<CopyMode>('both');
  const [pageLayout, setPageLayout] = useState<'separate' | 'two_in_one'>('separate');

  useEffect(() => {
    // Add document print mode class so index.css does not squish to 80mm
    document.body.classList.add('document-print-mode');
    return () => {
      document.body.classList.remove('document-print-mode');
    };
  }, []);

  if (!record) return null;

  const handlePrint = () => {
    document.body.classList.add('document-print-mode');
    window.print();
  };

  const handleWhatsAppShare = () => {
    const text = `*${settings.shopName || 'Mobile Shop'} - Mobile Purchase Receipt*\n` +
      `Receipt No: ${record.receiptNo}\n` +
      `Date: ${record.date} (${record.time})\n\n` +
      `*Seller Name:* ${record.sellerName}\n` +
      `CNIC No: ${record.sellerCnic}\n` +
      `Phone No: ${record.sellerPhone}\n\n` +
      `*Mobile Specs:*\n` +
      `Model: ${record.mobileBrandModel} (${record.condition === 'NEW' ? 'New (Pin Pack)' : 'Used / 2nd Hand'})\n` +
      `IMEI 1: ${record.imei1}\n` +
      (record.imei2 ? `IMEI 2: ${record.imei2}\n` : '') +
      (record.color ? `Color: ${record.color}\n` : '') +
      `Purchase Price Paid: Rs ${record.purchasePrice.toLocaleString()}\n` +
      `Payment Method: ${record.paymentMethod}\n\n` +
      `*Accessories:* ${[
        record.hasBox ? 'Box' : null,
        record.hasCharger ? 'Charger' : null,
        record.hasCable ? 'Cable' : null,
        record.hasHandsfree ? 'Handsfree' : null,
        record.hasWarrantyCard ? 'Warranty/Bill' : null,
      ].filter(Boolean).join(', ') || 'None'}\n\n` +
      `Shop Address: ${settings.address || 'GT Road'}\n` +
      `Contact: ${settings.phone || '0331-9348330'}`;

    const cleanPhone = record.sellerPhone ? record.sellerPhone.replace(/[^0-9]/g, '') : '';
    const url = cleanPhone 
      ? `https://wa.me/${cleanPhone}?text=${encodeURIComponent(text)}`
      : `https://wa.me/?text=${encodeURIComponent(text)}`;
    window.open(url, '_blank');
  };

  // Reusable Single Copy Component
  const renderReceiptCard = (type: 'customer' | 'shop') => {
    const isCustomer = type === 'customer';

    return (
      <div 
        key={type}
        className={`bg-white text-slate-900 border-2 border-slate-800 rounded-2xl p-5 sm:p-7 space-y-4 print:p-5 print:space-y-3 print:rounded-none print:border-black print:text-black shadow-sm ${
          pageLayout === 'separate' && copyMode === 'both' && isCustomer ? 'print:page-break-after-always' : ''
        }`}
      >
        {/* Top Identification Badge */}
        <div className="flex items-center justify-between border-b-2 border-slate-900 pb-3">
          <div className="flex items-center gap-2">
            <div className={`w-3 h-3 rounded-full ${isCustomer ? 'bg-blue-600' : 'bg-emerald-600'}`} />
            <div>
              <h2 className="text-sm sm:text-base font-black tracking-wider uppercase text-slate-900">
                {isCustomer ? 'CUSTOMER COPY | کسٹمر کاپی' : 'SHOP RECORD COPY | دکان ریکارڈ کاپی'}
              </h2>
              <p className="text-[10px] font-bold text-slate-500">
                {isCustomer ? 'Official Mobile Sale & Payment Acknowledgment Slip' : 'Official Legal Deed & Security Verification Record'}
              </p>
            </div>
          </div>
          <div className="text-right">
            <span className={`px-2.5 py-1 text-[11px] font-black rounded-lg uppercase tracking-wide border ${
              isCustomer 
                ? 'bg-blue-50 text-blue-900 border-blue-300 print:bg-white print:text-black' 
                : 'bg-emerald-50 text-emerald-900 border-emerald-300 print:bg-white print:text-black'
            }`}>
              {isCustomer ? 'Customer Receipt' : 'Deed / File Copy'}
            </span>
          </div>
        </div>

        {/* Shop Header */}
        <div className="text-center space-y-1 pt-1">
          <h1 className="text-xl sm:text-2xl font-black tracking-tight text-slate-900 uppercase">
            {settings.shopName || 'Mobiles & EasyPaisa Shop'}
          </h1>
          <div className="flex flex-wrap justify-center items-center gap-x-3 text-xs font-semibold text-slate-700">
            <span className="flex items-center gap-1">
              <MapPin className="w-3.5 h-3.5 text-slate-500 print:text-black" />
              {settings.address || 'Near Sadeeq e Akbar Masjid GT Road Sarai Saleh'}
            </span>
            <span>•</span>
            <span className="flex items-center gap-1">
              <Phone className="w-3.5 h-3.5 text-slate-500 print:text-black" />
              {settings.ownerName || 'Umer Ali'} ({settings.phone || '0331-9348330'})
            </span>
          </div>
        </div>

        {/* Receipt Meta Bar */}
        <div className="grid grid-cols-2 sm:grid-cols-4 gap-2 text-xs bg-slate-100 p-2.5 rounded-xl border border-slate-200 print:bg-white print:border-black font-semibold">
          <div>
            <span className="text-slate-500 block text-[10px] uppercase">Receipt No:</span>
            <span className="font-mono font-black text-slate-900 text-sm">{record.receiptNo}</span>
          </div>
          <div>
            <span className="text-slate-500 block text-[10px] uppercase">Date & Time:</span>
            <span className="font-bold text-slate-900">{record.date} ({record.time})</span>
          </div>
          <div>
            <span className="text-slate-500 block text-[10px] uppercase">Device Condition:</span>
            <span className="font-extrabold text-slate-900">
              {record.condition === 'NEW' ? 'NEW (Pin Pack)' : 'USED (2nd Hand)'}
            </span>
          </div>
          <div>
            <span className="text-slate-500 block text-[10px] uppercase">Payment Status:</span>
            <span className="font-extrabold text-emerald-700 print:text-black">PAID & CLEARED</span>
          </div>
        </div>

        {/* Seller Info & Photos */}
        <div className="border border-slate-300 rounded-xl p-3.5 space-y-2.5 bg-slate-50/40 print:bg-white print:border-black">
          <div className="flex items-center gap-1.5 text-xs font-black text-slate-800 uppercase border-b border-slate-200 pb-1.5">
            <User className="w-3.5 h-3.5 text-blue-600 print:text-black" />
            <span>SELLER VERIFICATION DETAILS (بیچنے والے کی تفصیل)</span>
          </div>

          <div className="grid grid-cols-1 sm:grid-cols-3 gap-3 items-center">
            {/* Details List */}
            <div className="sm:col-span-2 grid grid-cols-1 sm:grid-cols-2 gap-2 text-xs">
              <div>
                <span className="text-slate-500 font-medium block text-[10px]">Seller Name (نام):</span>
                <span className="font-extrabold text-slate-900 text-sm">{record.sellerName}</span>
              </div>
              <div>
                <span className="text-slate-500 font-medium block text-[10px]">CNIC No (شناختی کارڈ نمبر):</span>
                <span className="font-mono font-black text-slate-900 text-xs tracking-wider">{record.sellerCnic || 'N/A'}</span>
              </div>
              <div>
                <span className="text-slate-500 font-medium block text-[10px]">Phone No (موبائل نمبر):</span>
                <span className="font-mono font-bold text-slate-900 text-xs">{record.sellerPhone || 'N/A'}</span>
              </div>
              <div>
                <span className="text-slate-500 font-medium block text-[10px]">Address (پتہ):</span>
                <span className="font-semibold text-slate-800 text-xs">{record.sellerAddress || 'Sarai Saleh, Haripur'}</span>
              </div>
            </div>

            {/* Photos (Seller & CNIC) - Always rendered on Shop Copy, and compact on Customer Copy */}
            <div className="flex gap-2 justify-center sm:justify-end items-center">
              {record.sellerPhoto ? (
                <div className="text-center">
                  <span className="block text-[8px] font-bold text-slate-500 mb-0.5">Seller Pic</span>
                  <img 
                    src={record.sellerPhoto} 
                    alt="Seller" 
                    className="w-14 h-14 sm:w-16 sm:h-16 object-cover rounded-lg border border-slate-300 shadow-xs"
                  />
                </div>
              ) : (
                <div className="w-14 h-14 sm:w-16 sm:h-16 bg-slate-100 rounded-lg border border-dashed border-slate-300 flex flex-col items-center justify-center text-slate-400 text-[9px] text-center p-1">
                  <User className="w-4 h-4 text-slate-400" />
                  <span>No Photo</span>
                </div>
              )}

              {record.cnicFrontPhoto ? (
                <div className="text-center">
                  <span className="block text-[8px] font-bold text-slate-500 mb-0.5">CNIC Front</span>
                  <img 
                    src={record.cnicFrontPhoto} 
                    alt="CNIC Front" 
                    className="w-18 h-12 sm:w-20 sm:h-13 object-cover rounded-lg border border-slate-300 shadow-xs"
                  />
                </div>
              ) : (
                <div className="w-18 h-12 sm:w-20 sm:h-13 bg-slate-100 rounded-lg border border-dashed border-slate-300 flex flex-col items-center justify-center text-slate-400 text-[9px] text-center p-1">
                  <FileText className="w-4 h-4 text-slate-400" />
                  <span>No CNIC</span>
                </div>
              )}
            </div>
          </div>
        </div>

        {/* Mobile Phone Specifications */}
        <div className="border border-slate-300 rounded-xl p-3.5 space-y-2.5 bg-white print:border-black">
          <div className="flex items-center justify-between border-b border-slate-200 pb-1.5 text-xs font-black text-slate-800 uppercase">
            <div className="flex items-center gap-1.5">
              <Smartphone className="w-3.5 h-3.5 text-emerald-600 print:text-black" />
              <span>MOBILE DEVICE SPECIFICATIONS (موبائل کی تفصیل)</span>
            </div>
            <span className="text-[10px] font-bold text-slate-600">
              {record.color ? `Color: ${record.color}` : ''} {record.ramStorage ? `| ${record.ramStorage}` : ''}
            </span>
          </div>

          <div className="grid grid-cols-1 sm:grid-cols-3 gap-3 items-center">
            <div className="sm:col-span-2 grid grid-cols-1 sm:grid-cols-2 gap-2 text-xs">
              <div>
                <span className="text-slate-500 font-medium block text-[10px]">Brand & Model:</span>
                <span className="font-extrabold text-slate-900 text-sm">{record.mobileBrandModel}</span>
              </div>
              <div>
                <span className="text-slate-500 font-medium block text-[10px]">Condition:</span>
                <span className="font-bold text-slate-900">
                  {record.condition === 'NEW' ? 'Brand New (Pin Pack)' : 'Used / Second Hand'}
                </span>
              </div>
              <div>
                <span className="text-slate-500 font-medium block text-[10px]">IMEI 1:</span>
                <span className="font-mono font-black text-slate-900 text-xs bg-slate-100 px-1.5 py-0.5 rounded inline-block print:bg-white print:p-0">
                  {record.imei1}
                </span>
              </div>
              {record.imei2 && (
                <div>
                  <span className="text-slate-500 font-medium block text-[10px]">IMEI 2:</span>
                  <span className="font-mono font-black text-slate-900 text-xs bg-slate-100 px-1.5 py-0.5 rounded inline-block print:bg-white print:p-0">
                    {record.imei2}
                  </span>
                </div>
              )}
            </div>

            {record.mobilePhoto && (
              <div className="text-center sm:text-right">
                <span className="block text-[8px] font-bold text-slate-500 mb-0.5">Device Photo</span>
                <img 
                  src={record.mobilePhoto} 
                  alt="Mobile Device" 
                  className="w-16 h-16 object-cover rounded-lg border border-slate-300 shadow-xs inline-block"
                />
              </div>
            )}
          </div>
        </div>

        {/* Included Accessories */}
        <div className="border border-slate-300 rounded-xl p-2.5 bg-slate-50/60 print:bg-white print:border-black text-xs">
          <div className="flex flex-wrap items-center justify-between gap-2">
            <span className="font-black text-[11px] text-slate-700 uppercase">ACCESSORIES INCLUDED (ساتھ دیے گئے سامان):</span>
            <div className="flex flex-wrap items-center gap-3">
              <span className={`inline-flex items-center gap-1 font-bold ${record.hasBox ? 'text-emerald-700' : 'text-slate-400 line-through'}`}>
                {record.hasBox ? <CheckCircle2 className="w-3.5 h-3.5 text-emerald-600 print:text-black" /> : <XCircle className="w-3.5 h-3.5" />}
                Box
              </span>
              <span className={`inline-flex items-center gap-1 font-bold ${record.hasCharger ? 'text-emerald-700' : 'text-slate-400 line-through'}`}>
                {record.hasCharger ? <CheckCircle2 className="w-3.5 h-3.5 text-emerald-600 print:text-black" /> : <XCircle className="w-3.5 h-3.5" />}
                Charger
              </span>
              <span className={`inline-flex items-center gap-1 font-bold ${record.hasCable ? 'text-emerald-700' : 'text-slate-400 line-through'}`}>
                {record.hasCable ? <CheckCircle2 className="w-3.5 h-3.5 text-emerald-600 print:text-black" /> : <XCircle className="w-3.5 h-3.5" />}
                Data Cable
              </span>
              <span className={`inline-flex items-center gap-1 font-bold ${record.hasHandsfree ? 'text-emerald-700' : 'text-slate-400 line-through'}`}>
                {record.hasHandsfree ? <CheckCircle2 className="w-3.5 h-3.5 text-emerald-600 print:text-black" /> : <XCircle className="w-3.5 h-3.5" />}
                Handsfree
              </span>
              <span className={`inline-flex items-center gap-1 font-bold ${record.hasWarrantyCard ? 'text-emerald-700' : 'text-slate-400 line-through'}`}>
                {record.hasWarrantyCard ? <CheckCircle2 className="w-3.5 h-3.5 text-emerald-600 print:text-black" /> : <XCircle className="w-3.5 h-3.5" />}
                Bill/Warranty
              </span>
            </div>
          </div>
        </div>

        {/* Purchase Amount & Payment Details */}
        <div className="flex items-center justify-between bg-slate-900 text-white p-3.5 rounded-xl print:bg-white print:text-black print:border-2 print:border-black">
          <div>
            <span className="text-[10px] font-bold uppercase tracking-wider text-slate-300 print:text-black block">
              {isCustomer ? 'TOTAL AMOUNT RECEIVED BY SELLER (کل موصول شدہ رقم)' : 'TOTAL PURCHASE PRICE PAID (کل ادا شدہ رقم)'}
            </span>
            <span className="text-xs font-medium text-slate-300 print:text-black">
              Payment Method: <strong className="text-white print:text-black">{record.paymentMethod}</strong> • Cleared & Received
            </span>
          </div>
          <div className="text-right">
            <span className="text-xl sm:text-2xl font-black font-mono">Rs {record.purchasePrice.toLocaleString()}</span>
          </div>
        </div>

        {/* Legal Affidavit & Verification Declaration */}
        {isCustomer ? (
          // Customer Copy Note
          <div className="p-3 bg-blue-50/70 border border-blue-200 rounded-xl text-[11px] leading-relaxed text-blue-950 font-medium print:bg-white print:border-slate-400 print:text-black">
            <div className="flex items-center gap-1.5 font-bold text-blue-900 mb-0.5 print:text-black">
              <FileCheck className="w-3.5 h-3.5 text-blue-600 print:text-black" />
              <span>PAYMENT CONFIRMATION & SALE ACKNOWLEDGMENT (رسید بیعانہ و ادائیگی):</span>
            </div>
            <p>
              I confirm that I have willingly sold the above device to <strong>{settings.shopName || 'the shop'}</strong> and received the agreed full payment of <strong>Rs {record.purchasePrice.toLocaleString()}</strong> in cash/electronic transfer. No amount remains pending.
            </p>
          </div>
        ) : (
          // Shop Copy Legal Affidavit
          <div className="p-3 bg-amber-50/80 border border-amber-300 rounded-xl text-[11px] leading-relaxed text-amber-950 font-medium print:bg-white print:border-black print:text-black space-y-1">
            <div className="flex items-center gap-1.5 font-black text-amber-900 print:text-black">
              <ShieldCheck className="w-4 h-4 text-amber-600 print:text-black shrink-0" />
              <span>LEGAL SELLER AFFIDAVIT & DEED (بیانِ حلفی برائے ملکیت و فروخت):</span>
            </div>
            <p className="text-justify text-[10px] leading-snug">
              I, the seller named above, solemnly declare under oath that the mobile device mentioned in this receipt is my lawful, personal property. It is not stolen, snatched, tampered, or involved in any police inquiry or criminal act. In case of any dispute or defect of ownership, I will be legally and criminally liable before law enforcement authorities.
            </p>
            <p className="text-right font-urdu text-[11px] text-slate-800 print:text-black leading-relaxed" dir="rtl">
              میں حلفاً اقرار کرتا ہوں کہ مذکورہ بالا موبائل میری ذاتی ملکیت ہے، چوری شدہ یا کسی ناجائز کام کا نہیں۔ کسی بھی قانونی کارروائی یا پوچھ گچھ کی صورت میں، میں خود ذاتی طور پر جوابدہ ہوں گا۔
            </p>
          </div>
        )}

        {/* Signatures & Stamp */}
        <div className="pt-4 grid grid-cols-2 gap-6 text-center text-xs font-bold border-t-2 border-slate-300 print:border-black">
          <div className="space-y-4">
            <p className="text-slate-600 print:text-black">Seller Signature & Thumb (دستخط و انگوٹھا):</p>
            <div className="border-b-2 border-slate-400 w-3/4 mx-auto pt-8 print:border-black" />
            <p className="text-slate-900 font-extrabold">{record.sellerName}</p>
          </div>

          <div className="space-y-4">
            <p className="text-slate-600 print:text-black">Shop Owner Stamp & Signature (مہر و دستخط):</p>
            <div className="border-b-2 border-slate-400 w-3/4 mx-auto pt-8 print:border-black" />
            <p className="text-slate-900 font-extrabold">{settings.ownerName || 'Authorized Signatory'}</p>
          </div>
        </div>

      </div>
    );
  };

  return createPortal(
    <div id="simple-purchase-receipt-portal" className="receipt-portal-root fixed inset-0 z-50 flex items-center justify-center bg-black/75 backdrop-blur-sm p-2 sm:p-4 overflow-y-auto print:p-0 print:m-0 print:bg-white print:static print:inset-auto print:overflow-visible">
      <div className="bg-slate-100 text-slate-900 w-full max-w-3xl rounded-3xl shadow-2xl overflow-hidden border border-slate-200 my-auto print-full-width print:max-w-none print:w-full print:shadow-none print:border-none print:m-0 print:rounded-none print:bg-white">
        
        {/* Header Controls (Screen Only) */}
        <div className="p-3.5 bg-slate-900 text-white flex flex-wrap items-center justify-between gap-2.5 print:hidden">
          <div className="flex items-center gap-2">
            <div className="w-2.5 h-2.5 rounded-full bg-emerald-400 animate-pulse" />
            <div>
              <span className="font-extrabold text-xs sm:text-sm tracking-wide block">Mobile Buy & Purchase Receipts</span>
              <span className="text-[10px] text-slate-400">Customer Copy & Shop Record Copy Generator</span>
            </div>
          </div>

          {/* Copy Selector Tabs */}
          <div className="flex items-center bg-slate-800 p-1 rounded-xl border border-slate-700 text-xs font-bold">
            <button
              onClick={() => setCopyMode('both')}
              className={`px-3 py-1 rounded-lg transition-all cursor-pointer ${
                copyMode === 'both' 
                  ? 'bg-emerald-600 text-white shadow-sm' 
                  : 'text-slate-300 hover:text-white'
              }`}
            >
              Both Copies (2-in-1)
            </button>
            <button
              onClick={() => setCopyMode('customer')}
              className={`px-3 py-1 rounded-lg transition-all cursor-pointer ${
                copyMode === 'customer' 
                  ? 'bg-blue-600 text-white shadow-sm' 
                  : 'text-slate-300 hover:text-white'
              }`}
            >
              Customer Copy
            </button>
            <button
              onClick={() => setCopyMode('shop')}
              className={`px-3 py-1 rounded-lg transition-all cursor-pointer ${
                copyMode === 'shop' 
                  ? 'bg-indigo-600 text-white shadow-sm' 
                  : 'text-slate-300 hover:text-white'
              }`}
            >
              Shop Copy
            </button>
          </div>

          {/* Actions */}
          <div className="flex items-center gap-2">
            {copyMode === 'both' && (
              <select
                value={pageLayout}
                onChange={(e) => setPageLayout(e.target.value as any)}
                className="bg-slate-800 text-slate-200 border border-slate-700 text-xs font-semibold py-1.5 px-2 rounded-xl outline-none cursor-pointer"
                title="Page Layout on Print"
              >
                <option value="separate">Print: 2 Separate Pages</option>
                <option value="two_in_one">Print: 2-in-1 (Single Page)</option>
              </select>
            )}

            <button
              onClick={handleWhatsAppShare}
              className="py-1.5 px-3 rounded-xl bg-emerald-600 hover:bg-emerald-700 text-white font-bold text-xs flex items-center gap-1.5 shadow-sm transition-all cursor-pointer"
              title="Share receipt via WhatsApp"
            >
              <Share2 className="w-3.5 h-3.5" />
              <span className="hidden sm:inline">WhatsApp</span>
            </button>

            <button
              onClick={handlePrint}
              className="py-1.5 px-3.5 rounded-xl bg-blue-600 hover:bg-blue-700 text-white font-bold text-xs flex items-center gap-1.5 shadow-sm transition-all cursor-pointer"
              title="Print receipt"
            >
              <Printer className="w-3.5 h-3.5" />
              <span>Print</span>
            </button>

            <button
              onClick={onClose}
              className="w-8 h-8 rounded-xl bg-slate-800 hover:bg-slate-700 text-slate-300 flex items-center justify-center transition-colors cursor-pointer ml-1"
            >
              <X className="w-4 h-4" />
            </button>
          </div>
        </div>

        {/* PRINTABLE RECEIPT CONTAINER */}
        <div className="p-4 sm:p-6 space-y-6 max-h-[82vh] overflow-y-auto print:max-h-none print:overflow-visible print:p-0 print:space-y-4">
          
          {(copyMode === 'both' || copyMode === 'customer') && (
            <div className={copyMode === 'both' && pageLayout === 'separate' ? 'page-break-after-always mb-8 print:mb-0' : ''}>
              {renderReceiptCard('customer')}
            </div>
          )}

          {copyMode === 'both' && pageLayout === 'two_in_one' && (
            <div className="hidden print:flex items-center gap-2 text-slate-400 py-1">
              <div className="flex-1 border-b border-dashed border-slate-400" />
              <span className="text-[9px] font-mono uppercase">Cut Here (یہاں سے کاٹیں)</span>
              <div className="flex-1 border-b border-dashed border-slate-400" />
            </div>
          )}

          {(copyMode === 'both' || copyMode === 'shop') && (
            <div>
              {renderReceiptCard('shop')}
            </div>
          )}

        </div>

      </div>
    </div>,
    document.body
  );
};
