/**
 * Balal Mobile Shop & EasyPaisa - PHP & MySQL REST Client
 * Handles communication with custom PHP/MySQL backend on InfinityFree or Hostinger
 */

import { Product, ProductSale, Transaction, Supplier, MobilePurchaseRecord, AppSettings, DailyBalance } from '../types';

export interface PhpConnectionResult {
  success: boolean;
  message: string;
  stats?: {
    total_products?: number;
    total_sales?: number;
    total_transactions?: number;
    total_suppliers?: number;
  };
  serverTime?: string;
  phpVersion?: string;
}

export interface SyncPayload {
  products?: Product[];
  productSales?: ProductSale[];
  transactions?: Transaction[];
  suppliers?: Supplier[];
  mobilePurchases?: MobilePurchaseRecord[];
  dailyBalances?: Record<string, DailyBalance>;
  settings?: AppSettings;
}

export interface RemoteDataset {
  products?: Product[];
  productSales?: ProductSale[];
  transactions?: Transaction[];
  suppliers?: Supplier[];
  mobilePurchases?: MobilePurchaseRecord[];
  dailyBalances?: Record<string, DailyBalance>;
  settings?: AppSettings;
}

function cleanBaseUrl(url: string): string {
  let cleaned = (url || '').trim();
  if (!cleaned) {
    if (typeof window !== 'undefined' && window.location.origin) {
      return window.location.origin.replace(/\/+$/, '') + '/api';
    }
    return '';
  }
  
  // Handle relative paths like /api or ./api
  if (cleaned.startsWith('/') || cleaned.startsWith('./')) {
    if (typeof window !== 'undefined' && window.location.origin) {
      const path = cleaned.startsWith('./') ? cleaned.slice(1) : cleaned;
      return (window.location.origin.replace(/\/+$/, '') + path).replace(/\/+$/, '');
    }
  }

  if (!cleaned.startsWith('http://') && !cleaned.startsWith('https://')) {
    // If running in browser and protocol is http, use http, otherwise default to https
    if (typeof window !== 'undefined' && window.location.protocol === 'http:') {
      cleaned = 'http://' + cleaned;
    } else {
      cleaned = 'https://' + cleaned;
    }
  }
  // Remove trailing slash
  cleaned = cleaned.replace(/\/+$/, '');
  return cleaned;
}

/**
 * Test connectivity with user's PHP / MySQL server
 */
export async function testPhpConnection(baseUrl: string): Promise<PhpConnectionResult> {
  if (!baseUrl || !baseUrl.trim()) {
    return { success: false, message: 'Please enter a valid PHP API URL' };
  }

  const base = cleanBaseUrl(baseUrl);
  
  // Try direct health.php first, then /api/health.php, and root if already ending with .php
  const candidateUrls: string[] = [];
  
  // Primary URL
  if (base.endsWith('.php')) {
    candidateUrls.push(base);
  } else if (base.endsWith('/api')) {
    candidateUrls.push(`${base}/health.php`);
    candidateUrls.push(`${base.slice(0, -4)}/health.php`);
  } else {
    candidateUrls.push(`${base}/health.php`);
    candidateUrls.push(`${base}/api/health.php`);
  }

  // Also test http version if https was provided (InfinityFree free domains often don't have SSL by default)
  if (base.startsWith('https://')) {
    const httpBase = 'http://' + base.slice(8);
    if (!httpBase.endsWith('.php')) {
      candidateUrls.push(`${httpBase}/health.php`);
      candidateUrls.push(`${httpBase}/api/health.php`);
    }
  }

  let lastError = '';
  let attemptedUrl = '';

  for (const url of candidateUrls) {
    try {
      attemptedUrl = url;
      const response = await fetch(url, {
        method: 'GET',
        headers: {
          'Accept': 'application/json',
        },
        mode: 'cors'
      });

      if (response.ok) {
        const text = await response.text();
        let json: any;
        try {
          json = JSON.parse(text);
        } catch {
          // If response is HTML, it is likely an InfinityFree bot-check page or a PHP error page
          return {
            success: false,
            message: `سرور نے درست JSON کے بجائے HTML رسپانس دیا ہے۔ InfinityFree مفت ہوسٹنگ پر براہ راست بیرونی API بلاک ہو سکتی ہے یا فائل کا ایڈریس مختلف ہے۔ (رسپانس: ${text.slice(0, 120)}...)`
          };
        }

        if (json.success) {
          return {
            success: true,
            message: json.message || 'Connected to PHP/MySQL successfully!',
            stats: json.data?.stats,
            serverTime: json.data?.server_time,
            phpVersion: json.data?.php_version
          };
        } else {
          return {
            success: false,
            message: json.error || json.hint || 'Database connection error on PHP server. Please check config.php'
          };
        }
      } else {
        lastError = `HTTP ${response.status} ${response.statusText} at ${url}`;
      }
    } catch (err: any) {
      lastError = err?.message || 'Network error / CORS blocked';
    }
  }

  return {
    success: false,
    message: `Connection failed (${lastError}). براہ کرم نیچے دی گئی 4 بنیادی وجوہات چیک کریں۔`
  };
}

/**
 * Bulk Push all local data to PHP / MySQL
 */
export async function pushAllDataToPhp(baseUrl: string, payload: SyncPayload): Promise<{ success: boolean; message: string }> {
  if (!baseUrl || !baseUrl.trim()) {
    return { success: false, message: 'PHP Backend URL is not configured' };
  }

  const base = cleanBaseUrl(baseUrl);
  const syncUrl = base.endsWith('/api') ? `${base}/sync.php` : `${base}/api/sync.php`;

  try {
    const response = await fetch(syncUrl, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'Accept': 'application/json'
      },
      mode: 'cors',
      body: JSON.stringify(payload)
    });

    const json = await response.json();
    if (json.success) {
      return { success: true, message: json.message || 'All records successfully synced to MySQL!' };
    } else {
      return { success: false, message: json.error || 'Server rejected synchronization' };
    }
  } catch (err: any) {
    return { success: false, message: err?.message || 'Failed to connect to PHP server during sync' };
  }
}

/**
 * Pull all data from PHP / MySQL into local app
 */
export async function pullAllDataFromPhp(baseUrl: string): Promise<{ success: boolean; data?: RemoteDataset; message?: string }> {
  if (!baseUrl || !baseUrl.trim()) {
    return { success: false, message: 'PHP Backend URL is not configured' };
  }

  const base = cleanBaseUrl(baseUrl);
  const syncUrl = base.endsWith('/api') ? `${base}/sync.php` : `${base}/api/sync.php`;

  try {
    const response = await fetch(syncUrl, {
      method: 'GET',
      headers: {
        'Accept': 'application/json'
      },
      mode: 'cors'
    });

    const json = await response.json();
    if (json.success && json.data) {
      return { success: true, data: json.data };
    } else {
      return { success: false, message: json.error || 'Failed to fetch data from MySQL' };
    }
  } catch (err: any) {
    return { success: false, message: err?.message || 'Network error fetching data from PHP server' };
  }
}

/**
 * Save single product to PHP backend (non-blocking)
 */
export async function saveProductToPhp(baseUrl: string, product: Product): Promise<void> {
  if (!baseUrl) return;
  const base = cleanBaseUrl(baseUrl);
  const url = base.endsWith('/api') ? `${base}/products.php` : `${base}/api/products.php`;

  try {
    await fetch(url, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      mode: 'cors',
      body: JSON.stringify(product)
    });
  } catch (e) {
    console.warn('PHP Sync (Product) delayed/failed:', e);
  }
}

/**
 * Delete product from PHP backend (non-blocking)
 */
export async function deleteProductFromPhp(baseUrl: string, productId: string): Promise<void> {
  if (!baseUrl) return;
  const base = cleanBaseUrl(baseUrl);
  const url = base.endsWith('/api') ? `${base}/products.php?id=${encodeURIComponent(productId)}` : `${base}/api/products.php?id=${encodeURIComponent(productId)}`;

  try {
    await fetch(url, {
      method: 'DELETE',
      mode: 'cors'
    });
  } catch (e) {
    console.warn('PHP Sync (Delete Product) delayed/failed:', e);
  }
}

/**
 * Save POS sale to PHP backend (non-blocking)
 */
export async function saveSaleToPhp(baseUrl: string, sale: ProductSale): Promise<void> {
  if (!baseUrl) return;
  const base = cleanBaseUrl(baseUrl);
  const url = base.endsWith('/api') ? `${base}/sales.php` : `${base}/api/sales.php`;

  try {
    await fetch(url, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      mode: 'cors',
      body: JSON.stringify(sale)
    });
  } catch (e) {
    console.warn('PHP Sync (Sale) delayed/failed:', e);
  }
}

/**
 * Save transaction to PHP backend (non-blocking)
 */
export async function saveTransactionToPhp(baseUrl: string, trx: Transaction): Promise<void> {
  if (!baseUrl) return;
  const base = cleanBaseUrl(baseUrl);
  const url = base.endsWith('/api') ? `${base}/transactions.php` : `${base}/api/transactions.php`;

  try {
    await fetch(url, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      mode: 'cors',
      body: JSON.stringify(trx)
    });
  } catch (e) {
    console.warn('PHP Sync (Transaction) delayed/failed:', e);
  }
}
