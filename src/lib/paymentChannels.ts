import { PaymentMethod } from '../types';

export interface PaymentChannelOption {
  id: PaymentMethod;
  nameEn: string;
  nameUrdu: string;
  emoji: string;
  badgeBg: string;
  badgeText: string;
  category: 'WALLETS' | 'BANKS' | 'CASH';
}

export const PAYMENT_CHANNELS: PaymentChannelOption[] = [
  // Mobile Wallets & Fintech
  {
    id: 'EASYPAISA',
    nameEn: 'EasyPaisa Wallet',
    nameUrdu: 'ایزی پیسہ (EasyPaisa)',
    emoji: '🟢',
    badgeBg: 'bg-emerald-500/10 border-emerald-500/30 text-emerald-400',
    badgeText: 'text-emerald-400',
    category: 'WALLETS',
  },
  {
    id: 'JAZZCASH',
    nameEn: 'JazzCash Wallet',
    nameUrdu: 'جاز کیش (JazzCash)',
    emoji: '🔴',
    badgeBg: 'bg-rose-500/10 border-rose-500/30 text-rose-400',
    badgeText: 'text-rose-400',
    category: 'WALLETS',
  },
  {
    id: 'SADAPAY',
    nameEn: 'SadaPay Business/User',
    nameUrdu: 'سادا پے (SadaPay)',
    emoji: '🟠',
    badgeBg: 'bg-amber-500/10 border-amber-500/30 text-amber-400',
    badgeText: 'text-amber-400',
    category: 'WALLETS',
  },
  {
    id: 'NAYAPAY',
    nameEn: 'NayaPay Account',
    nameUrdu: 'نیا پے (NayaPay)',
    emoji: '🔵',
    badgeBg: 'bg-blue-500/10 border-blue-500/30 text-blue-400',
    badgeText: 'text-blue-400',
    category: 'WALLETS',
  },
  {
    id: 'RAAST',
    nameEn: 'Raast ID (State Bank)',
    nameUrdu: 'راست فوری ادائیگی (Raast SBP)',
    emoji: '🟣',
    badgeBg: 'bg-purple-500/10 border-purple-500/30 text-purple-400',
    badgeText: 'text-purple-400',
    category: 'WALLETS',
  },

  // Pakistani Online Banks
  {
    id: 'MEEZAN_BANK',
    nameEn: 'Meezan Bank Limited',
    nameUrdu: 'میزان بینک (Meezan Bank)',
    emoji: '🕌',
    badgeBg: 'bg-violet-500/10 border-violet-500/30 text-violet-400',
    badgeText: 'text-violet-400',
    category: 'BANKS',
  },
  {
    id: 'HBL',
    nameEn: 'HBL (Habib Bank)',
    nameUrdu: 'ایچ بی ایل (HBL Bank)',
    emoji: '🏛️',
    badgeBg: 'bg-teal-500/10 border-teal-500/30 text-teal-400',
    badgeText: 'text-teal-400',
    category: 'BANKS',
  },
  {
    id: 'UBL',
    nameEn: 'UBL (United Bank)',
    nameUrdu: 'یو بی ایل (UBL Bank)',
    emoji: '🏦',
    badgeBg: 'bg-sky-500/10 border-sky-500/30 text-sky-400',
    badgeText: 'text-sky-400',
    category: 'BANKS',
  },
  {
    id: 'ALLIED_BANK',
    nameEn: 'Allied Bank (ABL)',
    nameUrdu: 'الائیڈ بینک (ABL)',
    emoji: '🏛️',
    badgeBg: 'bg-cyan-500/10 border-cyan-500/30 text-cyan-400',
    badgeText: 'text-cyan-400',
    category: 'BANKS',
  },
  {
    id: 'MCB_BANK',
    nameEn: 'MCB Bank Limited',
    nameUrdu: 'ایم سی بی بینک (MCB)',
    emoji: '🏦',
    badgeBg: 'bg-orange-500/10 border-orange-500/30 text-orange-400',
    badgeText: 'text-orange-400',
    category: 'BANKS',
  },
  {
    id: 'BANK_ALFALAH',
    nameEn: 'Bank Alfalah',
    nameUrdu: 'بینک الفلاح (Bank Alfalah)',
    emoji: '🏛️',
    badgeBg: 'bg-red-500/10 border-red-500/30 text-red-400',
    badgeText: 'text-red-400',
    category: 'BANKS',
  },
  {
    id: 'BANK',
    nameEn: 'Other Bank Transfer',
    nameUrdu: 'دیگر بینک ٹرانسفر (Other Bank)',
    emoji: '💳',
    badgeBg: 'bg-indigo-500/10 border-indigo-500/30 text-indigo-400',
    badgeText: 'text-indigo-400',
    category: 'BANKS',
  },

  // Cash
  {
    id: 'CASH',
    nameEn: 'Physical Cash',
    nameUrdu: 'نقد کیش (Physical Cash)',
    emoji: '💵',
    badgeBg: 'bg-emerald-500/10 border-emerald-500/30 text-emerald-400',
    badgeText: 'text-emerald-400',
    category: 'CASH',
  },
];

export const getPaymentChannelInfo = (method?: PaymentMethod): PaymentChannelOption => {
  const found = PAYMENT_CHANNELS.find((c) => c.id === method);
  return found || PAYMENT_CHANNELS[0];
};

export const getPaymentChannelDisplay = (method?: PaymentMethod, isEn?: boolean): string => {
  const info = getPaymentChannelInfo(method);
  return `${info.emoji} ${isEn ? info.nameEn : info.nameUrdu}`;
};
