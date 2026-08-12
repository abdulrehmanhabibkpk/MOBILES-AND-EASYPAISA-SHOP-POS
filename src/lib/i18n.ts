import { AppSettings } from '../types';

export type Language = 'roman' | 'en';

export const translations = {
  // Navigation & Tabs
  dashboard: { roman: 'ڈیش بورڈ (Dashboard)', en: 'Dashboard' },
  pos: { roman: 'فروخت بل (POS)', en: 'POS (Sales Counter)' },
  inventory: { roman: 'اسٹاک و مال (Inventory)', en: 'Inventory & Stock' },
  ledger: { roman: 'ٹرانزیکشن کھاتہ (Ledger)', en: 'Transactions Ledger' },
  reports: { roman: 'ماہانہ رپورٹ (Reports)', en: 'Reports & Analytics' },
  customers: { roman: 'گاہک کھاتہ (Customers)', en: 'Customer Directory' },
  settings: { roman: 'سیٹنگز (Settings)', en: 'Shop Settings' },

  // Header & Quick Action Buttons
  newEasyPaisa: { roman: '+ EasyPaisa ٹرانزیکشن', en: '+ EasyPaisa Transaction' },
  newExpense: { roman: '+ دکان خرچہ', en: '+ Add Expense' },
  cashCalculator: { roman: 'روزانہ نقد گنتی (Cash Calculator)', en: 'Daily Cash Counter' },
  securityLock: { roman: 'سیکیورٹی لاک (Lock)', en: 'Security Lock' },
  posBtn: { roman: 'فروخت بل (POS)', en: 'POS Counter' },

  // Settings
  languageSelectTitle: { roman: 'سافٹ ویئر کی زبان (App Language)', en: 'App Language Preference' },
  languageSelectDesc: { roman: 'انگلش (English) یا رومن اردو (Roman Urdu) کا انتخاب کریں', en: 'Switch entire system interface between English and Roman Urdu' },
  english: { roman: 'English (انگلش)', en: 'English' },
  romanUrdu: { roman: 'Roman Urdu (رومن اردو)', en: 'Roman Urdu' },

  shopDetailsTitle: { roman: 'دکان کی تفصیلات اور سیٹنگز', en: 'Shop Details & Account Settings' },
  shopNameLabel: { roman: 'دکان / Shop Name:', en: 'Shop Name:' },
  ownerNameLabel: { roman: 'مالک کا نام (Owner Name):', en: 'Owner Name:' },
  phoneLabel: { roman: 'فون نمبر (Phone Number):', en: 'Phone Number:' },
  addressLabel: { roman: 'دکان کا پتہ (Shop Address):', en: 'Shop Address:' },
  easyPaisaNoLabel: { roman: 'ایزی پیسہ اکاؤنٹ نمبر:', en: 'EasyPaisa Account Number:' },
  jazzCashNoLabel: { roman: 'جاز کیش اکاؤنٹ نمبر:', en: 'JazzCash Account Number:' },
  securityPasscodeLabel: { roman: 'سیکیورٹی پاس کوڈ (Passcode):', en: 'Security Passcode:' },
  biometricUnlockTitle: { roman: 'فنگر پرنٹ / فیس آئی ڈی انلاک', en: 'Biometric / Face ID Quick Unlock' },

  saveSettingsBtn: { roman: 'سیٹنگز محفوظ کریں (Save Settings)', en: 'Save Settings' },
  savedSuccess: { roman: 'سیٹنگز کامیابی سے اپ ڈیٹ ہو گئیں!', en: 'Settings updated successfully!' },

  // Backup & Reset
  backupTitle: { roman: 'ڈیٹا بیک اپ اور ریسٹور (Backup & Restore)', en: 'Data Backup & Restore' },
  exportBackupBtn: { roman: 'ڈیٹا ڈاون لوڈ کریں (Export Backup)', en: 'Export Backup JSON' },
  restoreBackupBtn: { roman: 'بیک اپ ریسٹور کریں (Restore File)', en: 'Restore Backup JSON' },
  resetDataBtn: { roman: 'تمام ڈیٹا ری سیٹ کریں (Reset All Data)', en: 'Reset All Local Data' },

  // Dashboard Summary Cards
  currentCash: { roman: 'موجودہ نقد کیش (Cash in Hand)', en: 'Current Cash in Hand' },
  currentEasyPaisa: { roman: 'موجودہ ایزی پیسہ (EasyPaisa Balance)', en: 'Current EasyPaisa Balance' },
  todayNetProfit: { roman: 'آج کا خالص منافع (Today Net Profit)', en: 'Today Net Profit' },
  todayTrxCount: { roman: 'آج کی کل ٹرانزیکشنز', en: 'Today Transactions' },
  buyVolume: { roman: 'ایزی پیسہ خریدا (Buy EP)', en: 'EasyPaisa Received (Buy)' },
  sellVolume: { roman: 'ایزی پیسہ فروخت (Sell EP)', en: 'EasyPaisa Sent (Sell)' },
  todayExpenses: { roman: 'آج کے اخراجات (Expenses)', en: 'Today Expenses' },

  // Quick Action Buttons on Dashboard
  cashOutBuyBtn: { roman: 'کیش آوٹ (Buy EP)', en: 'Cash Out (Buy EP)' },
  sendSellBtn: { roman: 'منی ٹرانسفر (Sell EP)', en: 'Money Transfer (Sell EP)' },
  addExpenseBtn: { roman: 'اخراجات (Expense)', en: 'Add Expense' },
  openingCashBtn: { roman: 'افتتاحی کیش (Opening)', en: 'Opening Balance' },

  // POS & Inventory
  posHeaderTitle: { roman: 'موبائل اور ایکسیسریز سیل کاؤنٹر', en: 'Mobile & Accessories POS Counter' },
  searchProductPlaceholder: { roman: 'سامان کا نام، ماڈل یا برانڈ تلاش کریں...', en: 'Search product by name, model or IMEI...' },
  allCategories: { roman: 'تمام کیٹیگریز', en: 'All Categories' },
  addToCartBtn: { roman: 'کارٹ میں ڈالیں', en: 'Add to Cart' },
  cartTitle: { roman: 'فروخت فہرست (Cart List)', en: 'Current Bill Cart' },
  subtotal: { roman: 'کل رقم (Subtotal):', en: 'Subtotal:' },
  discount: { roman: 'رعایت (Discount):', en: 'Discount:' },
  netPayable: { roman: 'قابل ادا رقم (Net Payable):', en: 'Net Payable:' },
  completeSaleBtn: { roman: 'فروخت بل تیار کریں (Checkout)', en: 'Complete Sale & Print' },
  customerNamePlaceholder: { roman: 'گاہک کا نام (اختیاری)', en: 'Customer Name (Optional)' },
  customerPhonePlaceholder: { roman: 'موبائل نمبر (اختیاری)', en: 'Customer Phone (Optional)' },

  // Inventory Table
  inventoryTitle: { roman: 'اسٹاک و سامان انوینٹری (Stock Inventory)', en: 'Stock Inventory' },
  addNewProductBtn: { roman: '+ نیا سامان شامل کریں', en: '+ Add New Stock Item' },
  productName: { roman: 'سامان کا نام', en: 'Product Name' },
  category: { roman: 'کیٹیگری', en: 'Category' },
  purchasePrice: { roman: 'خرید قیمت', en: 'Purchase Price' },
  salePrice: { roman: 'فروخت قیمت', en: 'Sale Price' },
  stockQty: { roman: 'موجودہ اسٹاک', en: 'Stock Quantity' },
  actions: { roman: 'ایکشن', en: 'Actions' },

  // Reports
  reportsTitle: { roman: 'ماہانہ منافع و نقصان رپورٹ', en: 'Monthly Profit & Loss Analytics' },
  downloadPdfReport: { roman: 'PDF رپورٹ ڈاون لوڈ کریں', en: 'Download PDF Report' },
  grossProfit: { roman: 'کل فیس منافع (Gross Profit)', en: 'Gross Fee Profit' },
  netProfit: { roman: 'خالص منافع (Net Profit)', en: 'Net Profit' },
  totalExpensesLabel: { roman: 'کل اخراجات (Total Expenses)', en: 'Total Expenses' },

  // Customer Ledger
  customerLedgerTitle: { roman: 'گاہک کھاتہ و کسٹمر ڈائریکٹری', en: 'Customer Ledger & Directory' },
  searchCustomerPlaceholder: { roman: 'گاہک کا نام یا فون نمبر تلاش کریں...', en: 'Search customer name or phone...' },
  totalVolume: { roman: 'کل لین دین والیم', en: 'Total Volume' },
  profitEarned: { roman: 'حاصل شدہ منافع', en: 'Profit Earned' },

  // General Labels
  date: { roman: 'تاریخ', en: 'Date' },
  time: { roman: 'وقت', en: 'Time' },
  notes: { roman: 'تفصیل / نوٹس', en: 'Notes' },
  cancelBtn: { roman: 'منسوخ (Cancel)', en: 'Cancel' },
  confirmBtn: { roman: 'تائید (Confirm)', en: 'Confirm' },
  closeBtn: { roman: 'بند کریں (Close)', en: 'Close' },
};

export const getLang = (settings?: AppSettings): Language => {
  return settings?.language === 'roman' ? 'roman' : 'en';
};

export const t = (key: keyof typeof translations, settings?: AppSettings): string => {
  const lang = getLang(settings);
  return translations[key]?.[lang] || translations[key]?.['roman'] || key;
};
