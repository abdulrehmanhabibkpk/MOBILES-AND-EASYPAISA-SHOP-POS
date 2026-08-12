import { Transaction, Expense, DailyBalance, AppSettings, CustomerSummary, Product, ProductSale, MobilePurchaseRecord } from '../types';

const TRANSACTIONS_KEY = 'ep_ledger_transactions_v1';
const EXPENSES_KEY = 'ep_ledger_expenses_v1';
const DAILY_BALANCES_KEY = 'ep_ledger_daily_balances_v1';
const SETTINGS_KEY = 'ep_ledger_settings_v1';
const PRODUCTS_KEY = 'ep_ledger_products_v1';
const PRODUCT_SALES_KEY = 'ep_ledger_product_sales_v1';
const MOBILE_PURCHASES_KEY = 'ep_ledger_mobile_purchases_v1';

export const DEFAULT_SETTINGS: AppSettings = {
  shopName: 'Mobiles and EasyPaisa Shop POS',
  ownerName: 'Umer Ali',
  phone: '03319348330',
  address: 'Near Sadeeq e Akbar Masjid GT Road Sarai Saleh',
  pinCode: '6242',
  isLocked: true,
  theme: 'light',
  language: 'en',
  easyPaisaNumber: '0331-9348330',
  jazzCashNumber: '0331-9348330',
  allowedAccounts: [
    {
      id: 'acc-1',
      email: 'owner@mobile.com',
      password: 'mobile123',
      name: 'Umer Ali Owner',
      role: 'Owner',
    },
    {
      id: 'acc-2',
      email: 'manager@mobile.com',
      password: '123456',
      name: 'Umer Ali Manager',
      role: 'Manager',
    }
  ],
};

// Seed sample mobile phones & accessories inventory
const SAMPLE_PRODUCTS: Product[] = [
  {
    id: 'prod-1',
    name: 'Vivo Y21 (4GB / 64GB)',
    category: 'MOBILES',
    purchasePrice: 32000,
    salePrice: 36500,
    stock: 5,
    image: 'https://images.unsplash.com/photo-1511707171634-5f897ff02aa9?auto=format&fit=crop&w=400&q=80',
    brandOrModel: 'Vivo Y21',
    imeiOrSerial: '358291048291029',
    createdAt: Date.now() - 1000 * 60 * 60 * 24 * 5,
  },
  {
    id: 'prod-2',
    name: 'Samsung Galaxy A14',
    category: 'MOBILES',
    purchasePrice: 38500,
    salePrice: 43000,
    stock: 3,
    image: 'https://images.unsplash.com/photo-1598327105666-5b89351aff97?auto=format&fit=crop&w=400&q=80',
    brandOrModel: 'Samsung A14',
    imeiOrSerial: '351029384729102',
    createdAt: Date.now() - 1000 * 60 * 60 * 24 * 4,
  },
  {
    id: 'prod-3',
    name: 'Samsung 25W Type-C Super Fast Charger',
    category: 'CHARGERS',
    purchasePrice: 650,
    salePrice: 1200,
    stock: 18,
    image: 'https://images.unsplash.com/photo-1583863788434-e58a36330cf0?auto=format&fit=crop&w=400&q=80',
    brandOrModel: 'Samsung 25W',
    createdAt: Date.now() - 1000 * 60 * 60 * 24 * 3,
  },
  {
    id: 'prod-4',
    name: 'Wireless Airpods Pro TWS Bluetooth',
    category: 'EARPHONES',
    purchasePrice: 1100,
    salePrice: 2200,
    stock: 12,
    image: 'https://images.unsplash.com/photo-1600294037681-c80b4cb5b434?auto=format&fit=crop&w=400&q=80',
    brandOrModel: 'Airpods Pro',
    createdAt: Date.now() - 1000 * 60 * 60 * 24 * 3,
  },
  {
    id: 'prod-5',
    name: '9D Full Curved Glass Protector',
    category: 'PROTECTORS',
    purchasePrice: 70,
    salePrice: 250,
    stock: 45,
    image: 'https://images.unsplash.com/photo-1601784551446-20c9e07cdbdb?auto=format&fit=crop&w=400&q=80',
    brandOrModel: 'Universal Glass',
    createdAt: Date.now() - 1000 * 60 * 60 * 24 * 2,
  },
  {
    id: 'prod-6',
    name: 'Transparent Shockproof Silicone Case',
    category: 'COVERS',
    purchasePrice: 80,
    salePrice: 300,
    stock: 25,
    image: 'https://images.unsplash.com/photo-1541877944-ac82a091518a?auto=format&fit=crop&w=400&q=80',
    brandOrModel: 'Universal Case',
    createdAt: Date.now() - 1000 * 60 * 60 * 24 * 2,
  },
  {
    id: 'prod-7',
    name: 'Heavy Bass Handsfree 3.5mm Jack',
    category: 'EARPHONES',
    purchasePrice: 150,
    salePrice: 450,
    stock: 30,
    image: 'https://images.unsplash.com/photo-1505740420928-5e560c06d30e?auto=format&fit=crop&w=400&q=80',
    brandOrModel: 'Heavy Bass',
    createdAt: Date.now() - 1000 * 60 * 60 * 24 * 1,
  },
  {
    id: 'prod-8',
    name: 'Type-C Braided Fast Charging Cable',
    category: 'CABLES',
    purchasePrice: 120,
    salePrice: 350,
    stock: 22,
    image: 'https://images.unsplash.com/photo-1585338107529-13afc5f02586?auto=format&fit=crop&w=400&q=80',
    brandOrModel: 'Braided Type-C',
    createdAt: Date.now() - 1000 * 60 * 60 * 24 * 1,
  }
];

// Seed sample product sales
const SAMPLE_PRODUCT_SALES: ProductSale[] = [
  {
    id: 'sale-1',
    invoiceNo: 'INV-1001',
    date: new Date().toISOString().split('T')[0],
    time: '10:20 AM',
    customerName: 'Kashif Mehmood',
    customerPhone: '0300-8877665',
    items: [
      {
        productId: 'prod-3',
        productName: 'Samsung 25W Type-C Super Fast Charger',
        category: 'CHARGERS',
        quantity: 1,
        purchasePrice: 650,
        unitSalePrice: 1200,
        totalSalePrice: 1200,
        image: 'https://images.unsplash.com/photo-1583863788434-e58a36330cf0?auto=format&fit=crop&w=400&q=80',
      },
      {
        productId: 'prod-5',
        productName: '9D Full Curved Glass Protector',
        category: 'PROTECTORS',
        quantity: 1,
        purchasePrice: 70,
        unitSalePrice: 250,
        totalSalePrice: 250,
        image: 'https://images.unsplash.com/photo-1601784551446-20c9e07cdbdb?auto=format&fit=crop&w=400&q=80',
      }
    ],
    totalAmount: 1450,
    discount: 50,
    netAmount: 1400,
    totalPurchaseCost: 720,
    profit: 680,
    paymentMethod: 'CASH',
    createdAt: Date.now() - 1000 * 60 * 150,
  }
];

// Seed initial sample transactions if empty so user sees realistic data immediately
const SAMPLE_TRANSACTIONS: Transaction[] = [
  {
    id: 'trx-101',
    date: new Date().toISOString().split('T')[0],
    time: '09:30 AM',
    type: 'BUY_EASYPAISA',
    customerName: 'Muhammad Ali',
    customerPhone: '0312-9876543',
    trxId: '28491029381',
    easyPaisaAmount: 5000,
    cashAmount: 4900,
    feeProfit: 100,
    expenseAmount: 0,
    paymentMethod: 'EASYPAISA',
    notes: 'Customer transferred 5000 EasyPaisa, paid 4900 cash (100 fee)',
    createdAt: Date.now() - 1000 * 60 * 180,
  },
  {
    id: 'trx-102',
    date: new Date().toISOString().split('T')[0],
    time: '11:15 AM',
    type: 'SELL_EASYPAISA',
    customerName: 'Usman Ghani',
    customerPhone: '0301-4455667',
    trxId: '28491038472',
    easyPaisaAmount: 10000,
    cashAmount: 10200,
    feeProfit: 200,
    expenseAmount: 0,
    paymentMethod: 'EASYPAISA',
    notes: 'Customer paid 10,200 Cash, sent 10,000 EasyPaisa (200 fee)',
    createdAt: Date.now() - 1000 * 60 * 120,
  },
  {
    id: 'trx-103',
    date: new Date().toISOString().split('T')[0],
    time: '01:45 PM',
    type: 'BUY_EASYPAISA',
    customerName: 'Bilal Khan',
    customerPhone: '0333-1122334',
    trxId: '28491049281',
    easyPaisaAmount: 25000,
    cashAmount: 24500,
    feeProfit: 500,
    expenseAmount: 0,
    paymentMethod: 'EASYPAISA',
    notes: 'Customer cash-out Rs 25,000 (Rs 500 profit)',
    createdAt: Date.now() - 1000 * 60 * 60,
  },
  {
    id: 'trx-104',
    date: new Date().toISOString().split('T')[0],
    time: '03:10 PM',
    type: 'EXPENSE',
    customerName: 'Shop Expense',
    customerPhone: '',
    trxId: '-',
    easyPaisaAmount: 0,
    cashAmount: 0,
    feeProfit: 0,
    expenseAmount: 250,
    paymentMethod: 'CASH',
    notes: 'Tea & Snacks for shop',
    createdAt: Date.now() - 1000 * 60 * 30,
  },
];

export const getStoredTransactions = (): Transaction[] => {
  try {
    const data = localStorage.getItem(TRANSACTIONS_KEY);
    if (!data) {
      localStorage.setItem(TRANSACTIONS_KEY, JSON.stringify(SAMPLE_TRANSACTIONS));
      return SAMPLE_TRANSACTIONS;
    }
    return JSON.parse(data);
  } catch {
    return SAMPLE_TRANSACTIONS;
  }
};

export const saveTransactions = (transactions: Transaction[]): void => {
  localStorage.setItem(TRANSACTIONS_KEY, JSON.stringify(transactions));
};

export const getStoredExpenses = (): Expense[] => {
  try {
    const data = localStorage.getItem(EXPENSES_KEY);
    return data ? JSON.parse(data) : [];
  } catch {
    return [];
  }
};

export const saveExpenses = (expenses: Expense[]): void => {
  localStorage.setItem(EXPENSES_KEY, JSON.stringify(expenses));
};

export const getStoredDailyBalances = (): Record<string, DailyBalance> => {
  try {
    const data = localStorage.getItem(DAILY_BALANCES_KEY);
    return data ? JSON.parse(data) : {
      [new Date().toISOString().split('T')[0]]: {
        date: new Date().toISOString().split('T')[0],
        openingCash: 50000,
        openingEasyPaisa: 100000,
      }
    };
  } catch {
    return {
      [new Date().toISOString().split('T')[0]]: {
        date: new Date().toISOString().split('T')[0],
        openingCash: 50000,
        openingEasyPaisa: 100000,
      }
    };
  }
};

export const saveDailyBalance = (balance: DailyBalance): void => {
  const current = getStoredDailyBalances();
  current[balance.date] = balance;
  localStorage.setItem(DAILY_BALANCES_KEY, JSON.stringify(current));
};

export const getStoredSettings = (): AppSettings => {
  try {
    const data = localStorage.getItem(SETTINGS_KEY);
    if (!data) {
      localStorage.setItem(SETTINGS_KEY, JSON.stringify(DEFAULT_SETTINGS));
      return DEFAULT_SETTINGS;
    }
    const parsed = JSON.parse(data);
    parsed.language = 'en';
    if (!parsed.shopName || parsed.shopName === 'Omer Ali Mobile Shop' || parsed.shopName === 'Omer Ali Mobile' || parsed.shopName === 'Bilal Mobiles and EasyPaisa Shop' || parsed.shopName === 'Balal Mobile Shop') {
      parsed.shopName = DEFAULT_SETTINGS.shopName;
      parsed.ownerName = DEFAULT_SETTINGS.ownerName;
      parsed.phone = DEFAULT_SETTINGS.phone;
      parsed.address = DEFAULT_SETTINGS.address;
      parsed.easyPaisaNumber = DEFAULT_SETTINGS.easyPaisaNumber;
      parsed.jazzCashNumber = DEFAULT_SETTINGS.jazzCashNumber;
      localStorage.setItem(SETTINGS_KEY, JSON.stringify(parsed));
    }
    return { ...DEFAULT_SETTINGS, ...parsed };
  } catch {
    return DEFAULT_SETTINGS;
  }
};

export const saveSettings = (settings: AppSettings): void => {
  localStorage.setItem(SETTINGS_KEY, JSON.stringify(settings));
};

export const getStoredProducts = (): Product[] => {
  try {
    const data = localStorage.getItem(PRODUCTS_KEY);
    if (!data) {
      localStorage.setItem(PRODUCTS_KEY, JSON.stringify(SAMPLE_PRODUCTS));
      return SAMPLE_PRODUCTS;
    }
    return JSON.parse(data);
  } catch {
    return SAMPLE_PRODUCTS;
  }
};

export const saveProducts = (products: Product[]): void => {
  localStorage.setItem(PRODUCTS_KEY, JSON.stringify(products));
};

export const getStoredProductSales = (): ProductSale[] => {
  try {
    const data = localStorage.getItem(PRODUCT_SALES_KEY);
    if (!data) {
      localStorage.setItem(PRODUCT_SALES_KEY, JSON.stringify(SAMPLE_PRODUCT_SALES));
      return SAMPLE_PRODUCT_SALES;
    }
    return JSON.parse(data);
  } catch {
    return SAMPLE_PRODUCT_SALES;
  }
};

export const saveProductSales = (sales: ProductSale[]): void => {
  localStorage.setItem(PRODUCT_SALES_KEY, JSON.stringify(sales));
};

// Calculations helper
export const calculateDayStats = (dateStr: string, transactions: Transaction[], dailyBalances: Record<string, DailyBalance>) => {
  const dayTrx = transactions.filter(t => t.date === dateStr);
  const opening = dailyBalances[dateStr] || { date: dateStr, openingCash: 0, openingEasyPaisa: 0 };

  let totalBuyEpVolume = 0;   // EP received by shop
  let totalSellEpVolume = 0;  // EP sent by shop
  let totalCashGiven = 0;     // Cash paid out to customers
  let totalCashTaken = 0;     // Cash collected from customers
  let totalGrossProfit = 0;   // Gross fees earned
  let totalExpenses = 0;      // Expenses / losses

  dayTrx.forEach(t => {
    if (t.type === 'BUY_EASYPAISA' || t.type === 'BUY_CASH') {
      totalBuyEpVolume += t.easyPaisaAmount;
      totalCashGiven += t.cashAmount;
      totalGrossProfit += t.feeProfit;
    } else if (t.type === 'SELL_EASYPAISA' || t.type === 'SELL_CASH') {
      totalSellEpVolume += t.easyPaisaAmount;
      totalCashTaken += t.cashAmount;
      totalGrossProfit += t.feeProfit;
    } else if (t.type === 'EXPENSE' || t.type === 'DISCREPANCY_LOSS') {
      totalExpenses += t.expenseAmount;
    }
  });

  const netProfit = totalGrossProfit - totalExpenses;
  
  // Current Cash in Hand = Opening Cash + Cash Taken - Cash Given - Expenses
  const currentCash = opening.openingCash + totalCashTaken - totalCashGiven - totalExpenses;
  
  // Current EasyPaisa Balance = Opening EP + EP Received (Buy) - EP Sent (Sell)
  const currentEasyPaisa = opening.openingEasyPaisa + totalBuyEpVolume - totalSellEpVolume;

  return {
    date: dateStr,
    openingCash: opening.openingCash,
    openingEasyPaisa: opening.openingEasyPaisa,
    totalTransactions: dayTrx.length,
    totalBuyEpVolume,
    totalSellEpVolume,
    totalCashGiven,
    totalCashTaken,
    totalGrossProfit,
    totalExpenses,
    netProfit,
    currentCash,
    currentEasyPaisa,
  };
};

export const getCustomerSummaries = (transactions: Transaction[]): CustomerSummary[] => {
  const map: Record<string, CustomerSummary> = {};

  transactions.forEach(t => {
    if (!t.customerName || t.type === 'EXPENSE' || t.type === 'DISCREPANCY_LOSS') return;
    const key = (t.customerName + '_' + (t.customerPhone || '')).toLowerCase();

    if (!map[key]) {
      map[key] = {
        name: t.customerName,
        phone: t.customerPhone || 'N/A',
        totalTransactions: 0,
        totalBuyVolume: 0,
        totalSellVolume: 0,
        totalProfitGenerated: 0,
        lastTransactionDate: t.date,
      };
    }

    map[key].totalTransactions += 1;
    map[key].totalProfitGenerated += t.feeProfit;
    if (t.type === 'BUY_EASYPAISA' || t.type === 'BUY_CASH') {
      map[key].totalBuyVolume += t.easyPaisaAmount;
    } else {
      map[key].totalSellVolume += t.easyPaisaAmount;
    }

    if (t.date > map[key].lastTransactionDate) {
      map[key].lastTransactionDate = t.date;
    }
  });

  return Object.values(map);
};

// Sample Mobile Purchase Records
const SAMPLE_MOBILE_PURCHASES: MobilePurchaseRecord[] = [
  {
    id: 'pur-1001',
    receiptNo: 'PUR-1001',
    date: new Date().toISOString().split('T')[0],
    time: '02:30 PM',
    sellerName: 'Muhammad Hamza',
    sellerCnic: '37405-1234567-1',
    sellerPhone: '0300-5544332',
    sellerAddress: 'Sarai Saleh, Haripur',
    mobileBrandModel: 'Vivo Y21 (4GB / 64GB)',
    condition: 'USED',
    imei1: '862019048392019',
    imei2: '862019048392020',
    color: 'Diamond Blue',
    ramStorage: '4GB / 64GB',
    hasBox: true,
    hasCharger: true,
    hasCable: true,
    hasHandsfree: false,
    hasWarrantyCard: true,
    purchasePrice: 28500,
    paymentMethod: 'CASH',
    notes: 'Slight scratch on back cover. All buttons fully working.',
    createdAt: Date.now() - 1000 * 60 * 60 * 5,
  },
  {
    id: 'pur-1002',
    receiptNo: 'PUR-1002',
    date: new Date().toISOString().split('T')[0],
    time: '04:15 PM',
    sellerName: 'Shahid Khan',
    sellerCnic: '13101-9876543-3',
    sellerPhone: '0333-9988771',
    sellerAddress: 'GT Road, Haripur',
    mobileBrandModel: 'Samsung Galaxy A14 (Box Pack)',
    condition: 'NEW',
    imei1: '359102938475819',
    imei2: '359102938475820',
    color: 'Black',
    ramStorage: '6GB / 128GB',
    hasBox: true,
    hasCharger: true,
    hasCable: true,
    hasHandsfree: true,
    hasWarrantyCard: true,
    purchasePrice: 36000,
    paymentMethod: 'EASYPAISA',
    notes: 'Brand new pin pack mobile bought from wholesale seller.',
    createdAt: Date.now() - 1000 * 60 * 60 * 2,
  }
];

export const getStoredMobilePurchases = (): MobilePurchaseRecord[] => {
  try {
    const data = localStorage.getItem(MOBILE_PURCHASES_KEY);
    if (!data) {
      localStorage.setItem(MOBILE_PURCHASES_KEY, JSON.stringify(SAMPLE_MOBILE_PURCHASES));
      return SAMPLE_MOBILE_PURCHASES;
    }
    return JSON.parse(data);
  } catch {
    return SAMPLE_MOBILE_PURCHASES;
  }
};

export const saveMobilePurchases = (records: MobilePurchaseRecord[]): void => {
  localStorage.setItem(MOBILE_PURCHASES_KEY, JSON.stringify(records));
};
