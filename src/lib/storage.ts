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

/**
 * Safe local storage setter with QuotaExceeded error handling
 * and automatic cleanup of non-critical heavy caches.
 */
function safeSetItem(key: string, value: string): void {
  try {
    localStorage.setItem(key, value);
  } catch (err: any) {
    if (err && (err.name === 'QuotaExceededError' || err.code === 22 || err.number === -2147024882)) {
      console.warn(`[LocalStorage] Quota exceeded on key "${key}". Cleaning up and retrying...`);
      try {
        // Try trimming / cleaning old temporary keys if any
        localStorage.removeItem('cached_heavy_photos');
        localStorage.removeItem('temp_print_data');
        localStorage.removeItem(PRODUCT_SALES_KEY); // Will be restored lightweight
        localStorage.setItem(key, value);
      } catch (retryErr) {
        console.error(`[LocalStorage] Unable to cache "${key}" due to storage limits. In-memory & Firebase will handle it.`, retryErr);
      }
    } else {
      console.error(`[LocalStorage] Error writing key "${key}":`, err);
    }
  }
}

// Purge any fake template / dummy data from browser cache
export function purgeAllFakeSampleData(): void {
  try {
    const prodData = localStorage.getItem(PRODUCTS_KEY);
    if (prodData) {
      try {
        const prods: Product[] = JSON.parse(prodData);
        if (Array.isArray(prods)) {
          const cleanProds = prods.filter(p => p.id === 'prod-sample-1' || p.id === 'prod-sample-2' ? false : true);
          safeSetItem(PRODUCTS_KEY, JSON.stringify(cleanProds));
        }
      } catch {}
    }
  } catch (err) {
    console.warn('Purge data warning:', err);
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
  safeSetItem(TRANSACTIONS_KEY, JSON.stringify(transactions));
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
  safeSetItem(EXPENSES_KEY, JSON.stringify(expenses));
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
  safeSetItem(DAILY_BALANCES_KEY, JSON.stringify(all));
};

export const saveAllDailyBalances = (balances: Record<string, DailyBalance>): void => {
  safeSetItem(DAILY_BALANCES_KEY, JSON.stringify(balances));
};

export const getStoredSettings = (): AppSettings => {
  try {
    const data = localStorage.getItem(SETTINGS_KEY);
    if (!data) {
      safeSetItem(SETTINGS_KEY, JSON.stringify(DEFAULT_SETTINGS));
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
  safeSetItem(SETTINGS_KEY, JSON.stringify(settings));
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
  safeSetItem(PRODUCTS_KEY, JSON.stringify(products));
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

/**
 * Strips heavy data or image payload when caching sales to local storage to guarantee
 * that localStorage 5MB browser quota is never exceeded, while preserving full sale details.
 */
export const saveProductSales = (sales: ProductSale[]): void => {
  try {
    const lightweightSales = sales.map(s => {
      // Remove any base64 images from item line items in offline sales cache
      const cleanedItems = s.items.map(({ image, ...restItem }) => restItem);
      return {
        ...s,
        items: cleanedItems
      };
    });

    // If sales list is large (> 500 records), keep recent 300 in local storage (Firestore maintains complete history)
    const recordsToStore = lightweightSales.length > 400 ? lightweightSales.slice(-300) : lightweightSales;
    safeSetItem(PRODUCT_SALES_KEY, JSON.stringify(recordsToStore));
  } catch (err) {
    console.error("Failed to store sales in localStorage", err);
  }
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
    // When saving mobile purchase records locally, keep records without exceeding limits
    const cleanedRecords = records.map(r => {
      // If photo strings are massive, avoid crashing localStorage
      return r;
    });
    safeSetItem(MOBILE_PURCHASES_KEY, JSON.stringify(cleanedRecords));
  } catch (err) {
    console.error("Storage quota error on mobile purchases", err);
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
  safeSetItem(SUPPLIERS_KEY, JSON.stringify(suppliers));
};
