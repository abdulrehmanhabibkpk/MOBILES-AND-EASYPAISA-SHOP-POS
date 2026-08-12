import React from 'react';
import { MobilePurchaseRecord, AppSettings } from '../types';
import { X, Printer, Share2, CheckCircle2, XCircle, Smartphone, ShieldCheck, User } from 'lucide-react';

interface SimplePurchaseReceiptModalProps {
  record: MobilePurchaseRecord | null;
  settings: AppSettings;
  onClose: () => void;
}

export const SimplePurchaseReceiptModal: React.FC<SimplePurchaseReceiptModalProps> = ({
  record,
  settings,
  onClose,
}) => {
  if (!record) return null;

  const handlePrint = () => {
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
      `Model: ${record.mobileBrandModel} (${record.condition === 'NEW' ? 'New (Pin Pack)' : 'Used / Second Hand'})\n` +
      `IMEI 1: ${record.imei1}\n` +
      (record.imei2 ? `IMEI 2: ${record.imei2}\n` : '') +
      (record.color ? `Color: ${record.color}\n` : '') +
      `Purchase Price: Rs ${record.purchasePrice.toLocaleString()}\n\n` +
      `*Accessories Included:* ${[
        record.hasBox ? 'Box' : null,
        record.hasCharger ? 'Charger' : null,
        record.hasCable ? 'Cable' : null,
        record.hasHandsfree ? 'Handsfree' : null,
        record.hasWarrantyCard ? 'Warranty Card / Bill' : null,
      ].filter(Boolean).join(', ') || 'None'}\n\n` +
      `Thank you! ${settings.phone}`;

    const url = `https://wa.me/?text=${encodeURIComponent(text)}`;
    window.open(url, '_blank');
  };

  return (
    <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/70 backdrop-blur-sm p-2 sm:p-4 overflow-y-auto">
      <div className="bg-white text-slate-900 w-full max-w-xl rounded-2xl shadow-2xl overflow-hidden border border-slate-200 my-auto print:max-w-none print:w-full print:shadow-none print:border-none print:m-0 print:rounded-none">
        
        {/* Header Control Buttons (Hidden on Print) */}
        <div className="p-3 bg-slate-100 border-b border-slate-200 flex items-center justify-between print:hidden">
          <div className="flex items-center gap-2">
            <span className="w-3 h-3 rounded-full bg-emerald-500 animate-pulse" />
            <span className="font-extrabold text-xs text-slate-700">Mobile Purchase Receipt</span>
          </div>

          <div className="flex items-center gap-2">
            <button
              onClick={handleWhatsAppShare}
              className="py-1.5 px-3 rounded-xl bg-emerald-600 hover:bg-emerald-700 text-white font-bold text-xs flex items-center gap-1.5 shadow-sm transition-all cursor-pointer"
            >
              <Share2 className="w-3.5 h-3.5" />
              <span>WhatsApp Share</span>
            </button>

            <button
              onClick={handlePrint}
              className="py-1.5 px-3 rounded-xl bg-slate-800 hover:bg-slate-900 text-white font-bold text-xs flex items-center gap-1.5 shadow-sm transition-all cursor-pointer"
            >
              <Printer className="w-3.5 h-3.5" />
              <span>Print Receipt</span>
            </button>

            <button
              onClick={onClose}
              className="w-8 h-8 rounded-xl bg-slate-200 hover:bg-slate-300 text-slate-700 flex items-center justify-center transition-colors cursor-pointer"
            >
              <X className="w-4 h-4" />
            </button>
          </div>
        </div>

        {/* PRINTABLE RECEIPT CONTENT */}
        <div className="p-5 sm:p-7 space-y-5 print:p-4 print:space-y-4 text-slate-900 font-sans">
          
          {/* Shop Header */}
          <div className="border-b-2 border-slate-900 pb-4 text-center space-y-1">
            <div className="flex justify-center items-center gap-2">
              <Smartphone className="w-6 h-6 text-emerald-600 print:text-black" />
              <h1 className="text-xl sm:text-2xl font-black tracking-tight text-slate-900 uppercase">
                {settings.shopName || 'Mobiles & Accessories Shop'}
              </h1>
            </div>
            <p className="text-xs font-bold text-slate-700">
              {settings.address || 'GT Road, Haripur'} | Contact: {settings.phone || '0331-9348330'}
            </p>
            <div className="inline-block mt-2 px-4 py-1 bg-slate-900 text-white text-xs font-black rounded-full uppercase tracking-wider print:border print:border-black print:bg-white print:text-black">
              MOBILE PURCHASE & SALE DEED RECEIPT
            </div>
          </div>

          {/* Receipt Info Bar */}
          <div className="flex justify-between items-center text-xs font-bold bg-slate-100 p-2.5 rounded-xl border border-slate-200 print:bg-white print:border-black">
            <div>
              <span className="text-slate-500">Receipt No: </span>
              <span className="text-slate-900 font-mono text-sm font-black">{record.receiptNo}</span>
            </div>
            <div>
              <span className="text-slate-500">Date & Time: </span>
              <span className="text-slate-900">{record.date} ({record.time})</span>
            </div>
          </div>

          {/* Seller Information Box */}
          <div className="border border-slate-300 rounded-xl p-3.5 space-y-2 bg-slate-50/50 print:bg-white print:border-black">
            <div className="flex items-center gap-2 border-b border-slate-200 pb-2 text-xs font-black text-slate-800 uppercase">
              <User className="w-4 h-4 text-emerald-600 print:text-black" />
              <span>SELLER INFORMATION</span>
            </div>

            <div className="grid grid-cols-1 sm:grid-cols-2 gap-3 text-xs">
              <div>
                <span className="text-slate-500 font-semibold block">Seller Name:</span>
                <span className="font-extrabold text-sm text-slate-900">{record.sellerName}</span>
              </div>
              <div>
                <span className="text-slate-500 font-semibold block">CNIC No:</span>
                <span className="font-mono font-bold text-sm text-slate-900 tracking-wider">{record.sellerCnic || 'N/A'}</span>
              </div>
              <div>
                <span className="text-slate-500 font-semibold block">Mobile / Phone No:</span>
                <span className="font-mono font-bold text-slate-900">{record.sellerPhone || 'N/A'}</span>
              </div>
              <div>
                <span className="text-slate-500 font-semibold block">Address:</span>
                <span className="font-medium text-slate-900">{record.sellerAddress || 'Haripur'}</span>
              </div>
            </div>
          </div>

          {/* Mobile Phone Specifications */}
          <div className="border border-slate-300 rounded-xl p-3.5 space-y-2.5 bg-white print:border-black">
            <div className="flex items-center justify-between border-b border-slate-200 pb-2 text-xs font-black text-slate-800 uppercase">
              <div className="flex items-center gap-2">
                <Smartphone className="w-4 h-4 text-emerald-600 print:text-black" />
                <span>MOBILE SPECIFICATIONS</span>
              </div>
              <span className={`px-2 py-0.5 rounded text-[10px] font-black uppercase border ${
                record.condition === 'NEW' 
                  ? 'bg-emerald-100 text-emerald-800 border-emerald-300 print:bg-white print:text-black' 
                  : 'bg-amber-100 text-amber-800 border-amber-300 print:bg-white print:text-black'
              }`}>
                {record.condition === 'NEW' ? 'NEW / PIN PACK' : 'USED / SECOND HAND'}
              </span>
            </div>

            <div className="grid grid-cols-1 sm:grid-cols-2 gap-3 text-xs">
              <div>
                <span className="text-slate-500 font-semibold block">Brand & Model:</span>
                <span className="font-extrabold text-slate-900 text-sm">{record.mobileBrandModel}</span>
              </div>
              <div>
                <span className="text-slate-500 font-semibold block">Color / RAM & Storage:</span>
                <span className="font-bold text-slate-900">{record.color || 'N/A'} {record.ramStorage ? `(${record.ramStorage})` : ''}</span>
              </div>
              <div>
                <span className="text-slate-500 font-semibold block">IMEI No 1:</span>
                <span className="font-mono font-extrabold text-slate-900 text-xs bg-slate-100 px-2 py-0.5 rounded inline-block print:bg-white print:p-0">
                  {record.imei1}
                </span>
              </div>
              {record.imei2 && (
                <div>
                  <span className="text-slate-500 font-semibold block">IMEI No 2:</span>
                  <span className="font-mono font-extrabold text-slate-900 text-xs bg-slate-100 px-2 py-0.5 rounded inline-block print:bg-white print:p-0">
                    {record.imei2}
                  </span>
                </div>
              )}
            </div>
          </div>

          {/* Included Accessories */}
          <div className="border border-slate-300 rounded-xl p-3 bg-slate-50/60 print:bg-white print:border-black">
            <span className="text-xs font-black text-slate-700 block mb-2 uppercase">ACCESSORIES INCLUDED:</span>
            <div className="grid grid-cols-3 sm:grid-cols-5 gap-2 text-xs">
              <div className="flex items-center gap-1.5 font-bold">
                {record.hasBox ? <CheckCircle2 className="w-4 h-4 text-emerald-600 print:text-black" /> : <XCircle className="w-4 h-4 text-slate-300 print:text-black" />}
                <span>Box</span>
              </div>
              <div className="flex items-center gap-1.5 font-bold">
                {record.hasCharger ? <CheckCircle2 className="w-4 h-4 text-emerald-600 print:text-black" /> : <XCircle className="w-4 h-4 text-slate-300 print:text-black" />}
                <span>Charger</span>
              </div>
              <div className="flex items-center gap-1.5 font-bold">
                {record.hasCable ? <CheckCircle2 className="w-4 h-4 text-emerald-600 print:text-black" /> : <XCircle className="w-4 h-4 text-slate-300 print:text-black" />}
                <span>Cable</span>
              </div>
              <div className="flex items-center gap-1.5 font-bold">
                {record.hasHandsfree ? <CheckCircle2 className="w-4 h-4 text-emerald-600 print:text-black" /> : <XCircle className="w-4 h-4 text-slate-300 print:text-black" />}
                <span>Handsfree</span>
              </div>
              <div className="flex items-center gap-1.5 font-bold">
                {record.hasWarrantyCard ? <CheckCircle2 className="w-4 h-4 text-emerald-600 print:text-black" /> : <XCircle className="w-4 h-4 text-slate-300 print:text-black" />}
                <span>Warranty/Bill</span>
              </div>
            </div>
          </div>

          {/* Price Summary */}
          <div className="flex items-center justify-between bg-emerald-600 text-white p-4 rounded-xl shadow-sm print:bg-white print:text-black print:border-2 print:border-black">
            <div>
              <span className="text-xs font-medium text-emerald-100 block print:text-slate-700">TOTAL PURCHASE PRICE</span>
              <span className="text-xs font-bold text-emerald-200 print:text-black">Payment Method: {record.paymentMethod}</span>
            </div>
            <div className="text-right">
              <span className="text-2xl font-black font-mono">Rs {record.purchasePrice.toLocaleString()}</span>
            </div>
          </div>

          {/* Legal Seller Affidavit & Declaration */}
          <div className="p-3 bg-amber-50 border border-amber-200 rounded-xl text-[11px] leading-relaxed text-amber-950 font-medium print:bg-white print:border-slate-400 print:text-black">
            <div className="flex items-center gap-1.5 font-bold text-amber-900 mb-1 print:text-black">
              <ShieldCheck className="w-4 h-4 text-amber-600 shrink-0 print:text-black" />
              <span>LEGAL SELLER DECLARATION:</span>
            </div>
            <p className="text-justify">
              I hereby declare and affirm that the mobile phone specified above is my lawful personal property, free from any encumbrance or unlawful possession. I am selling this device to the shop with full consent. In case of any legal defect or dispute regarding ownership, I shall be solely responsible.
            </p>
          </div>

          {/* Signatures & Stamp Block */}
          <div className="pt-6 grid grid-cols-2 gap-8 text-center text-xs font-bold border-t border-slate-300 print:pt-8 print:border-black">
            <div className="space-y-8">
              <p className="text-slate-600">Seller Signature & Thumb Impression:</p>
              <div className="border-b-2 border-slate-400 w-3/4 mx-auto pt-4 print:border-black" />
              <p className="text-slate-800 font-extrabold">{record.sellerName}</p>
            </div>
            
            <div className="space-y-8">
              <p className="text-slate-600">Shop Owner Signature & Stamp:</p>
              <div className="border-b-2 border-slate-400 w-3/4 mx-auto pt-4 print:border-black" />
              <p className="text-slate-800 font-extrabold">{settings.ownerName || 'Shop Owner'}</p>
            </div>
          </div>

        </div>

      </div>
    </div>
  );
};
