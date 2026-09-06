export interface ColorOption {
  name: string;
  nameUrdu: string;
  hex: string;
  border?: string;
}

export const COLOR_PRESETS: ColorOption[] = [
  { name: 'Black', nameUrdu: 'سیاہ (Black)', hex: '#0f172a' },
  { name: 'Midnight Blue', nameUrdu: 'مڈ نائٹ بلیو', hex: '#1e3a8a' },
  { name: 'Sky Blue', nameUrdu: 'لائٹ / اسکائی بلیو', hex: '#38bdf8' },
  { name: 'Pearl White', nameUrdu: 'سفید (White)', hex: '#f8fafc', border: '#cbd5e1' },
  { name: 'Silver / Titanium', nameUrdu: 'سلور / ٹائٹینیم', hex: '#94a3b8' },
  { name: 'Gold / Champagne', nameUrdu: 'سنہرا (Gold)', hex: '#d97706' },
  { name: 'Rose Gold', nameUrdu: 'روز گولڈ', hex: '#f43f5e' },
  { name: 'Emerald Green', nameUrdu: 'زمردی سبز', hex: '#059669' },
  { name: 'Deep Purple', nameUrdu: 'جامنی (Purple)', hex: '#7c3aed' },
  { name: 'Crimson Red', nameUrdu: 'سرخ (Red)', hex: '#dc2626' },
  { name: 'Space Grey', nameUrdu: 'اسپیس گرے', hex: '#475569' },
  { name: 'Sunset Orange', nameUrdu: 'نارنجی (Orange)', hex: '#ea580c' },
];

export const RAM_STORAGE_PRESETS = [
  '3GB / 32GB',
  '4GB / 64GB',
  '6GB / 128GB',
  '8GB / 128GB',
  '8GB / 256GB',
  '12GB / 256GB',
  '12GB / 512GB',
  '16GB / 512GB',
  '1TB',
  'Keypad Phone',
];

export const PTA_STATUS_OPTIONS = [
  { key: 'PTA_APPROVED', label: 'PTA Approved', urdu: 'پی ٹی اے منظور شدہ', badgeClass: 'bg-emerald-100 text-emerald-800 border-emerald-300' },
  { key: 'NON_PTA', label: 'Non-PTA', urdu: 'نان پی ٹی اے', badgeClass: 'bg-amber-100 text-amber-800 border-amber-300' },
  { key: 'JV', label: 'JV Sim Lock', urdu: 'جے وی سم لاک', badgeClass: 'bg-rose-100 text-rose-800 border-rose-300' },
  { key: 'FACTORY_UNLOCK', label: 'Factory Unlocked', urdu: 'فیکٹری ان لاک', badgeClass: 'bg-blue-100 text-blue-800 border-blue-300' },
];

export const WARRANTY_OPTIONS = [
  '1 Year Official Brand Warranty',
  'Official Warranty (Remaining Months)',
  '7 Days Checking Warranty',
  '30 Days Shop Warranty',
  'No Warranty (As Is)',
];

export const CHARGER_WATTAGE_PRESETS = [
  '18W Fast Charge',
  '25W Super Fast',
  '33W Turbo',
  '45W Super Fast 2.0',
  '65W GaN / PD',
  '67W Turbo Charge',
  '120W HyperCharge',
];

export const PROTECTOR_TYPE_PRESETS = [
  '9D / 11D Matte Gaming Glass',
  '21D Super Clear HD Glass',
  'Privacy Anti-Spy Dark Screen',
  'UV Curved Tempered Glass',
  'Flexible Ceramic Anti-Break',
  'Camera Lens Protector',
];

export const COVER_TYPE_PRESETS = [
  'Silicone Soft Matte Case',
  'Transparent Clear Case',
  'Leather Pouch / Wallet Flip',
  'Armor Shockproof Heavy',
  'MagSafe Magnetic Case',
  'Carbon Fiber Texture',
];
