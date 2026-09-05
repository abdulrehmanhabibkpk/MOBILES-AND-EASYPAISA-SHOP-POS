import { Transaction, Expense, DailyBalance, AppSettings, CustomerSummary, Product, ProductSale, MobilePurchaseRecord, Supplier } from '../types';

const TRANSACTIONS_KEY = 'ep_ledger_transactions_v1';
const EXPENSES_KEY = 'ep_ledger_expenses_v1';
const DAILY_BALANCES_KEY = 'ep_ledger_daily_balances_v1';
const SETTINGS_KEY = 'ep_ledger_settings_v1';
const PRODUCTS_KEY = 'ep_ledger_products_v1';
const PRODUCT_SALES_KEY = 'ep_ledger_product_sales_v1';
const MOBILE_PURCHASES_KEY = 'ep_ledger_mobile_purchases_v1';
export const SUPPLIERS_KEY = 'balal_mobiles_suppliers';

export const DEFAULT_SETTINGS: AppSettings = {
  shopName: 'Balal Mobiles & EasyPaisa Shop',
  ownerName: '',
  phone: '',
  address: '',
  pinCode: '6242',
  isLocked: true,
  theme: 'light',
  language: 'en',
  easyPaisaNumber: '',
  jazzCashNumber: '',
  allowedAccounts: [
    {
      id: 'acc-1',
      email: 'owner@mobile.com',
      password: 'mobile123',
      name: 'Shop Owner',
      role: 'Owner',
    },
    {
      id: 'acc-2',
      email: 'manager@mobile.com',
      password: '123456',
      name: 'Shop Manager',
      role: 'Manager',
    }
  ],
};

// Purge any fake template / dummy data from browser cache
export function purgeAllFakeSampleData(): void {
  try {
    // 1. Purge fake products
    const prodData = localStorage.getItem(PRODUCTS_KEY);
    if (prodData) {
      try {
        const prods: Product[] = JSON.parse(prodData);
        if (Array.isArray(prods)) {
          const cleanProds = prods.filter(p => 
            !p.id.startsWith('prod-') && 
            !p.name.includes('Vivo Y21') && 
            !p.name.includes('Samsung Galaxy A14') &&
            !p.name.includes('Samsung 25W') &&
            !p.name.includes('Airpods Pro') &&
            !p.name.includes('9D Full Curved') &&
            !p.name.includes('Silicone Case') &&
            !p.name.includes('Heavy Bass') &&
            !p.name.includes('Type-C Braided')
          );
          localStorage.setItem(PRODUCTS_KEY, JSON.stringify(cleanProds));
        }
      } catch {}
    }

    // 2. Purge fake mobile purchases
    const purData = localStorage.getItem(MOBILE_PURCHASES_KEY);
    if (purData) {
      try {
        const purs: MobilePurchaseRecord[] = JSON.parse(purData);
        if (Array.isArray(purs)) {
          const cleanPurs = purs.filter(p => 
            p.id !== 'pur-1001' && 
            p.id !== 'pur-1002' && 
            p.id !== 'pur-1003' && 
            !p.sellerName?.includes('Hassnain Jaleel') && 
            !p.sellerName?.includes('Abdul Rehman') &&
            !p.sellerPhone?.includes('03078382955')
          );
          localStorage.setItem(MOBILE_PURCHASES_KEY, JSON.stringify(cleanPurs));
        }
      } catch {}
    }

    // 3. Purge fake product sales
    const salesData = localStorage.getItem(PRODUCT_SALES_KEY);
    if (salesData) {
      try {
        const sales: ProductSale[] = JSON.parse(salesData);
        if (Array.isArray(sales)) {
          const cleanSales = sales.filter(s => 
            !s.id.startsWith('sale-') && 
            s.invoiceNo !== 'INV-1001' && 
            !s.customerName?.includes('Kashif Mehmood')
          );
          localStorage.setItem(PRODUCT_SALES_KEY, JSON.stringify(cleanSales));
        }
      } catch {}
    }

    // 4. Purge fake suppliers
    const supData = localStorage.getItem(SUPPLIERS_KEY);
    if (supData) {
      try {
        const sups: Supplier[] = JSON.parse(supData);
        if (Array.isArray(sups)) {
          const cleanSups = sups.filter(s => 
            s.id !== 'sup-1' && 
            s.id !== 'sup-2' && 
            !s.name?.includes('Al-Madina') && 
            !s.name?.includes('Master Electronics')
          );
          localStorage.setItem(SUPPLIERS_KEY, JSON.stringify(cleanSups));
        }
      } catch {}
    }

    // 5. Purge fake transactions
    const trxData = localStorage.getItem(TRANSACTIONS_KEY);
    if (trxData) {
      try {
        const trx: Transaction[] = JSON.parse(trxData);
        if (Array.isArray(trx)) {
          const cleanTrx = trx.filter(t => 
            !t.id.startsWith('trx-10') && 
            !t.customerName?.includes('Sample Customer')
          );
          localStorage.setItem(TRANSACTIONS_KEY, JSON.stringify(cleanTrx));
        }
      } catch {}
    }

    // 6. Clean settings if it had fake test names
    const settingsData = localStorage.getItem(SETTINGS_KEY);
    if (settingsData) {
      try {
        const parsed = JSON.parse(settingsData);
        if (parsed.ownerName === 'Umer Ali' && parsed.phone === '03319348330') {
          parsed.ownerName = '';
          parsed.phone = '';
          parsed.address = '';
          localStorage.setItem(SETTINGS_KEY, JSON.stringify(parsed));
        }
      } catch {}
    }
  } catch (err) {
    console.warn('Purge fake data warning:', err);
  }
}

// Run purge on script load
purgeAllFakeSampleData();

export const getStoredTransactions = (): Transaction[] => {
  try {
    const data = localStorage.getItem(TRANSACTIONS_KEY);
    return data ? JSON.parse(data) : [];
  } catch {
    return [];
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
    return data ? JSON.parse(data) : {};
  } catch {
    return {};
  }
};

export const getDailyBalance = (dateStr: string): DailyBalance => {
  const all = getStoredDailyBalances();
  return all[dateStr] || { date: dateStr, openingCash: 0, openingEasyPaisa: 0 };
};

export const saveDailyBalance = (balance: DailyBalance): void => {
  const all = getStoredDailyBalances();
  all[balance.date] = balance;
  localStorage.setItem(DAILY_BALANCES_KEY, JSON.stringify(all));
};

export const saveAllDailyBalances = (balances: Record<string, DailyBalance>): void => {
  localStorage.setItem(DAILY_BALANCES_KEY, JSON.stringify(balances));
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
    if (data) {
      const parsed = JSON.parse(data);
      if (Array.isArray(parsed)) {
        return parsed;
      }
    }
  } catch {}
  return [];
};

export const saveProducts = (products: Product[]): void => {
  localStorage.setItem(PRODUCTS_KEY, JSON.stringify(products));
};

export const getStoredProductSales = (): ProductSale[] => {
  try {
    const data = localStorage.getItem(PRODUCT_SALES_KEY);
    if (data) {
      const parsed = JSON.parse(data);
      if (Array.isArray(parsed)) {
        return parsed;
      }
    }
  } catch {}
  return [];
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

export const getStoredMobilePurchases = (): MobilePurchaseRecord[] => {
  try {
    const data = localStorage.getItem(MOBILE_PURCHASES_KEY);
    if (data) {
      const parsed = JSON.parse(data);
      if (Array.isArray(parsed)) {
        return parsed;
      }
    }
  } catch {}
  return [];
};

export const saveMobilePurchases = (records: MobilePurchaseRecord[]): void => {
  try {
    localStorage.setItem(MOBILE_PURCHASES_KEY, JSON.stringify(records));
  } catch (err) {
    console.error("Storage quota exceeded", err);
  }
};

export const getStoredSuppliers = (): Supplier[] => {
  try {
    const data = localStorage.getItem(SUPPLIERS_KEY);
    if (data) {
      const parsed = JSON.parse(data);
      if (Array.isArray(parsed)) {
        return parsed;
      }
    }
  } catch {}
  return [];
};

export const saveSuppliers = (suppliers: Supplier[]): void => {
  localStorage.setItem(SUPPLIERS_KEY, JSON.stringify(suppliers));
};
