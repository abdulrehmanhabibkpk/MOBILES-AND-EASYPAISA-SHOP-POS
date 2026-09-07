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
  let cleaned = url.trim();
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
  
  // Try direct health.php first, then /api/health.php fallback if user supplied root domain
  const candidateUrls = [
    `${base}/health.php`,
    `${base}/api/health.php`,
    base.endsWith('.php') ? base : `${base}/health.php`
  ];

  let lastError = '';

  for (const url of candidateUrls) {
    try {
      const response = await fetch(url, {
        method: 'GET',
        headers: {
          'Accept': 'application/json',
        },
        mode: 'cors'
      });

      if (response.ok) {
        const json = await response.json();
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
            message: json.error || 'Database connection error on PHP server'
          };
        }
      } else {
        lastError = `HTTP ${response.status} ${response.statusText}`;
      }
    } catch (err: any) {
      lastError = err?.message || 'Network error / CORS blocked';
    }
  }

  return {
    success: false,
    message: `Connection failed: ${lastError}. Make sure config.php has correct DB credentials and CORS is enabled.`
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
