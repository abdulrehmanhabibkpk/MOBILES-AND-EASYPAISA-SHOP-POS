import { AppSettings } from '../types';

export type Language = 'en';

export const translations = {
  // Navigation & Tabs
  dashboard: { en: 'Dashboard' },
  pos: { en: 'POS (Sales Counter)' },
  inventory: { en: 'Inventory & Stock' },
  ledger: { en: 'Transactions Ledger' },
  reports: { en: 'Reports & Analytics' },
  customers: { en: 'Customer Directory' },
  settings: { en: 'Shop Settings' },

  // Header & Quick Action Buttons
  newEasyPaisa: { en: '+ EasyPaisa Transaction' },
  newExpense: { en: '+ Add Expense' },
  cashCalculator: { en: 'Daily Cash Counter' },
  securityLock: { en: 'Security Lock' },
  posBtn: { en: 'POS Counter' },

  // Settings
  languageSelectTitle: { en: 'App Language Preference' },
  languageSelectDesc: { en: 'System interface is set to English' },
  english: { en: 'English' },
  romanUrdu: { en: 'English' },

  shopDetailsTitle: { en: 'Shop Details & Account Settings' },
  shopNameLabel: { en: 'Shop Name:' },
  ownerNameLabel: { en: 'Owner Name:' },
  phoneLabel: { en: 'Phone Number:' },
  addressLabel: { en: 'Shop Address:' },
  easyPaisaNoLabel: { en: 'EasyPaisa Account Number:' },
  jazzCashNoLabel: { en: 'JazzCash Account Number:' },
  securityPasscodeLabel: { en: 'Security Passcode:' },
  biometricUnlockTitle: { en: 'Biometric / Face ID Quick Unlock' },

  saveSettingsBtn: { en: 'Save Settings' },
  savedSuccess: { en: 'Settings updated successfully!' },

  // Backup & Reset
  backupTitle: { en: 'Data Backup & Restore' },
  exportBackupBtn: { en: 'Export Backup JSON' },
  restoreBackupBtn: { en: 'Restore Backup JSON' },
  resetDataBtn: { en: 'Reset All Local Data' },

  // Dashboard Summary Cards
  currentCash: { en: 'Current Cash in Hand' },
  currentEasyPaisa: { en: 'Current EasyPaisa Balance' },
  todayNetProfit: { en: 'Today Net Profit' },
  todayTrxCount: { en: 'Today Transactions' },
  buyVolume: { en: 'EasyPaisa Received (Buy)' },
  sellVolume: { en: 'EasyPaisa Sent (Sell)' },
  todayExpenses: { en: 'Today Expenses' },

  // Quick Action Buttons on Dashboard
  cashOutBuyBtn: { en: 'Cash Out (Buy EP)' },
  sendSellBtn: { en: 'Money Transfer (Sell EP)' },
  addExpenseBtn: { en: 'Add Expense' },
  openingCashBtn: { en: 'Opening Balance' },

  // POS & Inventory
  posHeaderTitle: { en: 'Mobile & Accessories POS Counter' },
  searchProductPlaceholder: { en: 'Search product by name, model or IMEI...' },
  allCategories: { en: 'All Categories' },
  addToCartBtn: { en: 'Add to Cart' },
  cartTitle: { en: 'Current Bill Cart' },
  subtotal: { en: 'Subtotal:' },
  discount: { en: 'Discount:' },
  netPayable: { en: 'Net Payable:' },
  completeSaleBtn: { en: 'Complete Sale & Print' },
  customerNamePlaceholder: { en: 'Customer Name (Optional)' },
  customerPhonePlaceholder: { en: 'Customer Phone (Optional)' },

  // Inventory Table
  inventoryTitle: { en: 'Stock Inventory' },
  addNewProductBtn: { en: '+ Add New Stock Item' },
  productName: { en: 'Product Name' },
  category: { en: 'Category' },
  purchasePrice: { en: 'Purchase Price' },
  salePrice: { en: 'Sale Price' },
  stockQty: { en: 'Stock Quantity' },
  actions: { en: 'Actions' },

  // Reports
  reportsTitle: { en: 'Monthly Profit & Loss Analytics' },
  downloadPdfReport: { en: 'Download PDF Report' },
  grossProfit: { en: 'Gross Fee Profit' },
  netProfit: { en: 'Net Profit' },
  totalExpensesLabel: { en: 'Total Expenses' },

  // Customer Ledger
  customerLedgerTitle: { en: 'Customer Ledger & Directory' },
  searchCustomerPlaceholder: { en: 'Search customer name or phone...' },
  totalVolume: { en: 'Total Volume' },
  profitEarned: { en: 'Profit Earned' },

  // General Labels
  date: { en: 'Date' },
  time: { en: 'Time' },
  notes: { en: 'Notes' },
  cancelBtn: { en: 'Cancel' },
  confirmBtn: { en: 'Confirm' },
  closeBtn: { en: 'Close' },
};

export const getLang = (settings?: AppSettings): Language => {
  return 'en';
};

export const t = (key: keyof typeof translations, settings?: AppSettings): string => {
  return translations[key]?.['en'] || key;
};

