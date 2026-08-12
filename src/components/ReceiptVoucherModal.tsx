import React from 'react';
import { X, Printer, Download, ShieldCheck, CheckCircle, Smartphone, Share2 } from 'lucide-react';
import { Transaction, AppSettings } from '../types';
import { generateTransactionVoucherPDF } from '../lib/pdf';
import { getPaymentChannelInfo } from '../lib/paymentChannels';

interface ReceiptVoucherModalProps {
  isOpen: boolean;
  onClose: () => void;
  transaction: Transaction | null;
  settings: AppSettings;
}

export const ReceiptVoucherModal: React.FC<ReceiptVoucherModalProps> = ({
  isOpen,
  onClose,
  transaction,
  settings,
}) => {
  if (!isOpen || !transaction) return null;

  const handlePrint = () => {
    window.print();
  };

  const handleDownloadPDF = () => {
    generateTransactionVoucherPDF(transaction, settings);
  };

  const handleWhatsAppShare = () => {
    const channel = getPaymentChannelInfo(transaction.paymentMethod);
    const typeTitle = transaction.type === 'BUY_EASYPAISA' 
      ? `BUY (${channel.nameEn} In / Cash Out)` 
      : transaction.type === 'SELL_EASYPAISA' 
      ? `SELL (Cash In / ${channel.nameEn} Out)` 
      : 'EXPENSE';

    const msg = `📲 *${channel.emoji} ${channel.nameEn} ٹرانزیکشن رسید (${channel.nameEn} Receipt)*\n*${settings.shopName || 'Mobiles and EasyPaisa Shop POS'}*\n📍 ${settings.address || 'Near Sadeeq e Akbar Masjid GT Road Sarai Saleh'}\n📞 Contact: Umer Ali (${settings.phone || '03319348330'})\n-------------------------\n*Voucher ID:* ${transaction.id}\n*Channel:* ${channel.nameEn}\n*TRX ID:* ${transaction.trxId || '-'}\n*Date:* ${transaction.date} ${transaction.time}\n*Customer:* ${transaction.customerName || 'Walk-in Customer'}\n*Type:* ${typeTitle}\n-------------------------\n*${channel.nameEn} Amount:* Rs. ${transaction.easyPaisaAmount.toLocaleString()}\n*Cash Amount:* Rs. ${transaction.cashAmount.toLocaleString()}\n*Fee Profit:* Rs. ${transaction.feeProfit.toLocaleString()}\n-------------------------\nProtected Security System`;

    const url = transaction.customerPhone 
      ? `https://wa.me/${transaction.customerPhone.replace(/[^0-9]/g, '')}?text=${encodeURIComponent(msg)}`
      : `https://wa.me/?text=${encodeURIComponent(msg)}`;

    window.open(url, '_blank');
  };

  const channelInfo = getPaymentChannelInfo(transaction.paymentMethod);

  return (
    <div className="fixed inset-0 z-50 flex items-center justify-center bg-slate-950/80 backdrop-blur-sm p-2 sm:p-4 overflow-y-auto print:p-0 print:bg-white print:static print:block">
      <div className="w-full max-w-md bg-white border border-slate-200 rounded-2xl shadow-2xl overflow-hidden text-slate-800 my-auto max-h-[92vh] flex flex-col print:shadow-none print:border-none print:m-0 print:max-h-none print:w-full print:rounded-none">
        
        {/* Top Header - EasyPaisa Theme (Screen View) */}
        <div className="bg-emerald-600 px-4 sm:px-6 py-4 sm:py-5 text-center text-white relative shrink-0 print:hidden">
          <button
            onClick={onClose}
            className="absolute top-3 right-3 w-8 h-8 rounded-full bg-black/20 hover:bg-black/30 text-white flex items-center justify-center transition-colors print:hidden cursor-pointer"
          >
            <X className="w-5 h-5" />
          </button>

          <div className="inline-flex items-center justify-center w-10 h-10 sm:w-12 sm:h-12 rounded-full bg-white/20 mb-1.5">
            <Smartphone className="w-5 h-5 sm:w-6 sm:h-6 text-white" />
          </div>
          <h2 className="font-bold text-base sm:text-lg leading-tight">{settings.shopName || 'Mobiles and EasyPaisa Shop POS'}</h2>
          <p className="text-emerald-100 text-xs mt-0.5">Contact: Umer Ali ({settings.phone || '03319348330'})</p>
          <p className="text-emerald-200 text-[10px] mt-0.5">{settings.address || 'Near Sadeeq e Akbar Masjid GT Road Sarai Saleh'}</p>
          
          <div className="mt-2 inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full bg-slate-950/30 text-emerald-200 text-[10px] sm:text-[11px] font-medium border border-white/20">
            <ShieldCheck className="w-3.5 h-3.5" />
            <span>Protected Security System</span>
          </div>
        </div>

        {/* THERMAL PRINT RECEIPT HEADER DYNAMIC CHANNEL */}
        <div className="hidden print:block text-center font-mono py-2 border-b-2 border-black">
          <h1 className="font-extrabold text-base uppercase tracking-tight">{settings.shopName || 'MOBILES AND EASYPAISA SHOP POS'}</h1>
          <p className="text-xs">{settings.address || 'Near Sadeeq e Akbar Masjid GT Road Sarai Saleh'}</p>
          <p className="text-xs font-semibold">Umer Ali: {settings.phone || '03319348330'}</p>
          <p className="text-[10px] font-bold mt-1">*** {channelInfo.nameEn.toUpperCase()} RECEIPT ***</p>
          <p className="text-[9px]">Protected Passcode System</p>
        </div>

        {/* Voucher Body */}
        <div className="p-4 sm:p-6 space-y-3.5 sm:space-y-4 overflow-y-auto print:p-2 print:space-y-2 print:overflow-visible font-mono">
          
          <div className="text-center pb-3 border-b border-slate-200 print:pb-1.5 print:border-black">
            <span className="inline-flex items-center gap-1 px-3 py-1 rounded-full bg-emerald-50 text-emerald-700 text-xs font-bold border border-emerald-200 print:bg-transparent print:border-none print:p-0 print:text-black">
              <CheckCircle className="w-3.5 h-3.5 print:hidden" />
              <span>TRANSACTION CONFIRMED</span>
            </span>
            <p className="text-xs text-slate-400 mt-2 print:text-black print:mt-1">Voucher ID: <span className="font-mono font-semibold text-slate-700 print:text-black">{transaction.id}</span></p>
            <p className="text-xs text-slate-400 print:text-black">{transaction.date} at {transaction.time}</p>
          </div>

          <div className="space-y-2.5 text-xs print:space-y-1">
            <div className="flex justify-between py-1 border-b border-slate-100 print:border-black">
              <span className="text-slate-500 print:text-black">Type:</span>
              <span className="font-bold text-slate-800 print:text-black">
                {transaction.type === 'BUY_EASYPAISA' 
                  ? `BUY (${channelInfo.nameEn} In / Cash Out)` 
                  : transaction.type === 'SELL_EASYPAISA' 
                  ? `SELL (Cash In / ${channelInfo.nameEn} Out)` 
                  : 'EXPENSE'}
              </span>
            </div>

            <div className="flex justify-between py-1 border-b border-slate-100 print:border-black">
              <span className="text-slate-500 print:text-black">Customer Name:</span>
              <span className="font-semibold text-slate-800 print:text-black">{transaction.customerName || 'Walk-in Customer'}</span>
            </div>

            {transaction.customerPhone && (
              <div className="flex justify-between py-1 border-b border-slate-100 print:border-black">
                <span className="text-slate-500 print:text-black">Phone:</span>
                <span className="font-mono text-slate-800 print:text-black">{transaction.customerPhone}</span>
              </div>
            )}

            <div className="flex justify-between py-1 border-b border-slate-100 print:border-black">
              <span className="text-slate-500 print:text-black">Payment Channel:</span>
              <span className="font-bold text-slate-800 print:text-black">
                {channelInfo.emoji} {channelInfo.nameEn} ({channelInfo.nameUrdu})
              </span>
            </div>

            <div className="flex justify-between py-1 border-b border-slate-100 print:border-black">
              <span className="text-slate-500 print:text-black">TRX ID / Ref:</span>
              <span className="font-mono font-bold text-emerald-700 print:text-black">{transaction.trxId || '-'}</span>
            </div>

            <div className="flex justify-between py-1 border-b border-slate-100 print:border-black">
              <span className="text-slate-500 print:text-black">{channelInfo.nameEn} Amount:</span>
              <span className="font-mono font-bold text-slate-900 text-sm print:text-black print:text-xs">
                Rs. {transaction.easyPaisaAmount.toLocaleString()}
              </span>
            </div>

            <div className="flex justify-between py-1 border-b border-slate-100 print:border-black">
              <span className="text-slate-500 print:text-black">Cash Amount:</span>
              <span className="font-mono font-bold text-slate-900 text-sm print:text-black print:text-xs">
                Rs. {transaction.cashAmount.toLocaleString()}
              </span>
            </div>

            <div className="flex justify-between py-1 border-b border-slate-100 print:border-black">
              <span className="text-slate-500 print:text-black">Fee / Commission:</span>
              <span className="font-mono font-bold text-emerald-600 print:text-black">
                Rs. {transaction.feeProfit.toLocaleString()}
              </span>
            </div>

            {transaction.notes && (
              <div className="py-1">
                <span className="text-slate-500 block mb-0.5 print:text-black">Remarks:</span>
                <p className="p-2 rounded bg-slate-50 text-slate-600 italic print:bg-transparent print:p-0 print:text-black">{transaction.notes}</p>
              </div>
            )}
          </div>

          {/* Footer Note */}
          <div className="pt-3 border-t border-slate-200 text-center text-[11px] text-slate-400 print:border-black print:text-black print:pt-2">
            <p>Shukriya for business with us!</p>
            <p className="font-semibold text-slate-500 mt-0.5 print:text-black">Protected by Abdul Rahman Habib (6242)</p>
          </div>

          {/* Action Buttons */}
          <div className="pt-3 flex items-center justify-between gap-1.5 sm:gap-2 print:hidden">
            <button
              onClick={handleWhatsAppShare}
              className="flex-1 py-2 px-2 rounded-xl bg-emerald-600 hover:bg-emerald-700 text-white font-bold text-xs flex items-center justify-center gap-1 transition-colors shadow-sm"
            >
              <Share2 className="w-4 h-4" />
              <span>WhatsApp</span>
            </button>
            <button
              onClick={handleDownloadPDF}
              className="flex-1 py-2 px-2 rounded-xl bg-blue-700 hover:bg-blue-800 text-white font-bold text-xs flex items-center justify-center gap-1 shadow-md transition-colors"
            >
              <Download className="w-4 h-4" />
              <span>PDF</span>
            </button>
            <button
              onClick={handlePrint}
              className="flex-1 py-2 px-2 rounded-xl bg-slate-100 hover:bg-slate-200 text-slate-700 font-semibold text-xs flex items-center justify-center gap-1 border border-slate-300 transition-colors"
            >
              <Printer className="w-4 h-4" />
              <span>Print</span>
            </button>
          </div>

        </div>

      </div>
    </div>
  );
};
