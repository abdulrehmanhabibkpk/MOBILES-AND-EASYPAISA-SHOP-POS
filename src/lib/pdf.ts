import jsPDF from 'jspdf';
import autoTable from 'jspdf-autotable';
import { Transaction, AppSettings, DailyBalance } from '../types';
import { getPaymentChannelInfo } from './paymentChannels';

export const generateTransactionVoucherPDF = (trx: Transaction, settings: AppSettings) => {
  const doc = new jsPDF();
  const channel = getPaymentChannelInfo(trx.paymentMethod);

  // Header Banner
  doc.setFillColor(16, 185, 129); // Emerald Green
  doc.rect(0, 0, 210, 30, 'F');

  doc.setTextColor(255, 255, 255);
  doc.setFontSize(16);
  doc.setFont('helvetica', 'bold');
  doc.text(settings.shopName || 'Mobiles and EasyPaisa Shop POS', 105, 12, { align: 'center' });

  doc.setFontSize(9);
  doc.setFont('helvetica', 'normal');
  doc.text(`Contact: Umer Ali (${settings.phone || '03319348330'}) | Address: ${settings.address || 'Near Sadeeq e Akbar Masjid GT Road Sarai Saleh'}`, 105, 20, { align: 'center' });

  // Receipt Details Box
  doc.setTextColor(30, 41, 59);
  doc.setFontSize(14);
  doc.setFont('helvetica', 'bold');
  doc.text(`${channel.nameEn.toUpperCase()} TRANSACTION RECEIPT`, 105, 42, { align: 'center' });

  doc.setLineWidth(0.5);
  doc.setDrawColor(203, 213, 225);
  doc.line(15, 46, 195, 46);

  // Table Data
  const typeText = 
    trx.type === 'BUY_EASYPAISA' ? `BUY (${channel.nameEn} In / Cash Out)` :
    trx.type === 'SELL_EASYPAISA' ? `SELL (Cash In / ${channel.nameEn} Out)` :
    trx.type === 'EXPENSE' ? 'EXPENSE / SHOP COST' : trx.type;

  const data = [
    ['Voucher ID / Ref', trx.id],
    ['Date & Time', `${trx.date} at ${trx.time}`],
    ['Transaction Type', typeText],
    ['Payment Channel / Bank', `${channel.nameEn} (${channel.nameUrdu})`],
    ['Customer Name', trx.customerName || 'Walk-in Customer'],
    ['Customer Phone', trx.customerPhone || 'N/A'],
    ['TRX ID / Reference', trx.trxId || 'N/A'],
    [`${channel.nameEn} Amount`, `Rs. ${trx.easyPaisaAmount.toLocaleString()}`],
    ['Cash Amount Handled', `Rs. ${trx.cashAmount.toLocaleString()}`],
    ['Fee / Commission Earned', `Rs. ${trx.feeProfit.toLocaleString()}`],
    ['Remarks / Notes', trx.notes || '-'],
  ];

  autoTable(doc, {
    startY: 52,
    head: [['Field', 'Details']],
    body: data,
    theme: 'striped',
    headStyles: { fillColor: [16, 185, 129], textColor: 255, fontStyle: 'bold' },
    styles: { fontSize: 10, cellPadding: 4 },
    columnStyles: { 0: { fontStyle: 'bold', cellWidth: 60 } },
  });

  const finalY = (doc as any).lastAutoTable.finalY || 150;

  // Signatures
  doc.setFontSize(10);
  doc.text('Customer Signature: __________________', 20, finalY + 30);
  doc.text('Authorized Stamp & Sign: __________________', 110, finalY + 30);

  // Footer
  doc.setFontSize(9);
  doc.setTextColor(100, 116, 139);
  doc.text('Protected Passcode System', 105, 285, { align: 'center' });

  doc.save(`Voucher_${channel.id}_${trx.id}_${trx.date}.pdf`);
};

export const generateDailyClosingPDF = (
  dateStr: string,
  stats: any,
  dayTrx: Transaction[],
  settings: AppSettings
) => {
  const doc = new jsPDF();

  // Header Banner
  doc.setFillColor(15, 23, 42); // Slate Dark
  doc.rect(0, 0, 210, 32, 'F');

  doc.setTextColor(255, 255, 255);
  doc.setFontSize(15);
  doc.setFont('helvetica', 'bold');
  doc.text(settings.shopName || 'Mobiles and EasyPaisa Shop POS', 105, 12, { align: 'center' });

  doc.setFontSize(11);
  doc.text(`DAILY CLOSING LEDGER REPORT - DATE: ${dateStr}`, 105, 20, { align: 'center' });

  doc.setFontSize(8);
  doc.setTextColor(203, 213, 225);
  doc.text(`Contact: Umer Ali (${settings.phone || '03319348330'}) | GT Road Sarai Saleh`, 105, 27, { align: 'center' });

  // Summary Metrics Table
  doc.setTextColor(15, 23, 42);
  doc.setFontSize(12);
  doc.setFont('helvetica', 'bold');
  doc.text('Daily Balance Summary', 14, 40);

  const summaryData = [
    [
      `Opening Cash: Rs. ${stats.openingCash.toLocaleString()}`,
      `Opening EasyPaisa: Rs. ${stats.openingEasyPaisa.toLocaleString()}`,
    ],
    [
      `Total Buy Volume: Rs. ${stats.totalBuyEpVolume.toLocaleString()}`,
      `Total Sell Volume: Rs. ${stats.totalSellEpVolume.toLocaleString()}`,
    ],
    [
      `Total Cash Paid Out: Rs. ${stats.totalCashGiven.toLocaleString()}`,
      `Total Cash Collected: Rs. ${stats.totalCashTaken.toLocaleString()}`,
    ],
    [
      `Gross Commission: Rs. ${stats.totalGrossProfit.toLocaleString()}`,
      `Total Expenses/Loss: Rs. ${stats.totalExpenses.toLocaleString()}`,
    ],
    [
      `NET PROFIT (SAF MUNAFA): Rs. ${stats.netProfit.toLocaleString()}`,
      `Total Transactions: ${stats.totalTransactions}`,
    ],
    [
      `Closing Cash in Hand: Rs. ${stats.currentCash.toLocaleString()}`,
      `Closing EasyPaisa Balance: Rs. ${stats.currentEasyPaisa.toLocaleString()}`,
    ],
  ];

  autoTable(doc, {
    startY: 44,
    body: summaryData,
    theme: 'grid',
    styles: { fontSize: 9, fontStyle: 'bold', cellPadding: 3 },
  });

  const summaryY = (doc as any).lastAutoTable.finalY || 100;

  doc.setFontSize(12);
  doc.text(`Transactions Detail for ${dateStr}`, 14, summaryY + 10);

  const tableRows = dayTrx.map((t, idx) => {
    const ch = getPaymentChannelInfo(t.paymentMethod);
    return [
      idx + 1,
      t.time,
      t.type === 'BUY_EASYPAISA' ? 'BUY' : t.type === 'SELL_EASYPAISA' ? 'SELL' : 'EXPENSE',
      ch.nameEn,
      t.customerName || '-',
      t.trxId || '-',
      `Rs. ${t.easyPaisaAmount.toLocaleString()}`,
      `Rs. ${t.cashAmount.toLocaleString()}`,
      `Rs. ${t.type === 'EXPENSE' ? t.expenseAmount : t.feeProfit}`,
    ];
  });

  autoTable(doc, {
    startY: summaryY + 14,
    head: [['#', 'Time', 'Type', 'Channel', 'Customer', 'TRX ID', 'Transfer Amt', 'Cash Amt', 'Profit/Expense']],
    body: tableRows,
    theme: 'striped',
    headStyles: { fillColor: [16, 185, 129], textColor: 255 },
    styles: { fontSize: 8, cellPadding: 2.5 },
  });

  const finalY = (doc as any).lastAutoTable.finalY || 240;

  // Footer & Signatures
  doc.setFontSize(9);
  doc.setTextColor(100, 116, 139);
  doc.text('Prepared by: ________________________', 14, Math.min(finalY + 20, 275));
  doc.text('Verified by: ________________________', 120, Math.min(finalY + 20, 275));

  doc.text('Protected Passcode System | All Rights Reserved', 105, 288, { align: 'center' });

  doc.save(`Daily_Closing_${dateStr}.pdf`);
};

export const generateMonthlyReportPDF = (
  monthLabel: string,
  transactions: Transaction[],
  dailyBalances: Record<string, DailyBalance>,
  settings: AppSettings
) => {
  const doc = new jsPDF();

  doc.setFillColor(16, 185, 129);
  doc.rect(0, 0, 210, 32, 'F');

  doc.setTextColor(255, 255, 255);
  doc.setFontSize(15);
  doc.setFont('helvetica', 'bold');
  doc.text(settings.shopName || 'Mobiles and EasyPaisa Shop POS', 105, 12, { align: 'center' });

  doc.setFontSize(11);
  doc.text(`MONTHLY LEDGER STATEMENT - ${monthLabel.toUpperCase()}`, 105, 20, { align: 'center' });

  doc.setFontSize(8);
  doc.text(`Contact: Umer Ali (${settings.phone || '03319348330'}) | GT Road Sarai Saleh`, 105, 27, { align: 'center' });

  let totalBuy = 0;
  let totalSell = 0;
  let totalGrossProfit = 0;
  let totalExpenses = 0;

  transactions.forEach(t => {
    if (t.type === 'BUY_EASYPAISA' || t.type === 'BUY_CASH') {
      totalBuy += t.easyPaisaAmount;
      totalGrossProfit += t.feeProfit;
    } else if (t.type === 'SELL_EASYPAISA' || t.type === 'SELL_CASH') {
      totalSell += t.easyPaisaAmount;
      totalGrossProfit += t.feeProfit;
    } else if (t.type === 'EXPENSE' || t.type === 'DISCREPANCY_LOSS') {
      totalExpenses += t.expenseAmount;
    }
  });

  const netProfit = totalGrossProfit - totalExpenses;

  doc.setTextColor(15, 23, 42);
  doc.setFontSize(10);
  doc.text(`Monthly Total Volume: Rs. ${(totalBuy + totalSell).toLocaleString()} | Gross Fees: Rs. ${totalGrossProfit.toLocaleString()} | Expenses: Rs. ${totalExpenses.toLocaleString()} | NET PROFIT: Rs. ${netProfit.toLocaleString()}`, 14, 40);

  const tableRows = transactions.map((t, i) => {
    const ch = getPaymentChannelInfo(t.paymentMethod);
    return [
      i + 1,
      t.date,
      t.type === 'BUY_EASYPAISA' ? 'BUY' : t.type === 'SELL_EASYPAISA' ? 'SELL' : 'EXPENSE',
      ch.nameEn,
      t.customerName || '-',
      t.trxId || '-',
      `Rs. ${t.easyPaisaAmount.toLocaleString()}`,
      `Rs. ${t.cashAmount.toLocaleString()}`,
      `Rs. ${t.type === 'EXPENSE' ? t.expenseAmount : t.feeProfit}`,
    ];
  });

  autoTable(doc, {
    startY: 46,
    head: [['#', 'Date', 'Type', 'Channel', 'Customer', 'TRX ID', 'Transfer Amt', 'Cash Amt', 'Profit/Loss']],
    body: tableRows,
    theme: 'striped',
    headStyles: { fillColor: [15, 23, 42], textColor: 255 },
    styles: { fontSize: 8, cellPadding: 2 },
  });

  doc.setFontSize(9);
  doc.setTextColor(100, 116, 139);
  doc.text('Protected Passcode System | Multi-Account Accounts Ledger', 105, 288, { align: 'center' });

  doc.save(`Monthly_Ledger_${monthLabel.replace(/\s+/g, '_')}.pdf`);
};
