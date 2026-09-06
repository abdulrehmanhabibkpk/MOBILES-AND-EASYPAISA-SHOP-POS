import * as XLSX from 'xlsx';
import { Product, ProductCategory } from '../types';

export interface ParsedStockItem {
  name: string;
  category: ProductCategory;
  brandOrModel: string;
  imeiOrSerial: string;
  sku: string;
  stock: number;
  purchasePrice: number;
  salePrice: number;
  image?: string;
  id?: string;
}

// Map various category inputs to valid ProductCategory
export function normalizeCategory(catStr?: string): ProductCategory {
  if (!catStr) return 'ACCESSORIES';
  const c = String(catStr).trim().toUpperCase();
  if (c.includes('MOB') || c.includes('PHONE') || c.includes('موبائل')) return 'MOBILES';
  if (c.includes('CHARG') || c.includes('چارجر') || c.includes('ADAPTER')) return 'CHARGERS';
  if (c.includes('EAR') || c.includes('AIRPOD') || c.includes('BUDS') || c.includes('HEADPHONE') || c.includes('ہینڈز')) return 'EARPHONES';
  if (c.includes('COVER') || c.includes('CASE') || c.includes('کور')) return 'COVERS';
  if (c.includes('PROTEC') || c.includes('GLASS') || c.includes('گلاس') || c.includes('TEMPERED')) return 'PROTECTORS';
  if (c.includes('CABLE') || c.includes('CORD') || c.includes('کیبل') || c.includes('WIRE')) return 'CABLES';
  if (c.includes('BATT') || c.includes('بیٹری')) return 'BATTERIES';
  return 'ACCESSORIES';
}

/**
 * 1. EXPORT TO EXCEL (.xlsx)
 */
export function exportStockToExcel(products: Product[], shopName: string = 'Shop') {
  const rows = products.map((p, idx) => ({
    'Sr #': idx + 1,
    'Item ID': p.id,
    'Product / Item Name': p.name,
    'Category': p.category,
    'Brand / Model': p.brandOrModel || '',
    'IMEI / Serial / Barcode': p.imeiOrSerial || '',
    'SKU': p.sku || '',
    'Stock Quantity': Number(p.stock) || 0,
    'Purchase Price (Rs)': Number(p.purchasePrice) || 0,
    'Sale Price (Rs)': Number(p.salePrice) || 0,
    'Total Cost Value (Rs)': (Number(p.stock) || 0) * (Number(p.purchasePrice) || 0),
    'Total Sale Value (Rs)': (Number(p.stock) || 0) * (Number(p.salePrice) || 0),
    'Created Date': p.createdAt ? new Date(p.createdAt).toLocaleDateString() : '',
  }));

  const worksheet = XLSX.utils.json_to_sheet(rows);

  // Set column widths
  worksheet['!cols'] = [
    { wch: 6 },  // Sr #
    { wch: 18 }, // Item ID
    { wch: 30 }, // Name
    { wch: 16 }, // Category
    { wch: 20 }, // Brand
    { wch: 22 }, // IMEI
    { wch: 16 }, // SKU
    { wch: 14 }, // Stock
    { wch: 18 }, // Purchase Price
    { wch: 18 }, // Sale Price
    { wch: 20 }, // Total Cost
    { wch: 20 }, // Total Sale
    { wch: 14 }, // Created Date
  ];

  const workbook = XLSX.utils.book_new();
  XLSX.utils.book_append_sheet(workbook, worksheet, 'Stock Inventory');

  const cleanShop = shopName.replace(/[^a-zA-Z0-9_-]/g, '_');
  const dateStr = new Date().toISOString().split('T')[0];
  XLSX.writeFile(workbook, `${cleanShop}_Stock_Inventory_${dateStr}.xlsx`);
}

/**
 * 2. EXPORT TO CSV (.csv)
 */
export function exportStockToCSV(products: Product[], shopName: string = 'Shop') {
  const headers = [
    'Sr #',
    'Item ID',
    'Product Name',
    'Category',
    'Brand Model',
    'IMEI / Serial / Barcode',
    'SKU',
    'Stock Quantity',
    'Purchase Price (Rs)',
    'Sale Price (Rs)',
    'Total Cost Value (Rs)',
    'Total Sale Value (Rs)',
    'Created Date'
  ];

  const csvRows: string[] = [];
  csvRows.push(headers.map(h => `"${h.replace(/"/g, '""')}"`).join(','));

  products.forEach((p, idx) => {
    const cost = (Number(p.stock) || 0) * (Number(p.purchasePrice) || 0);
    const saleVal = (Number(p.stock) || 0) * (Number(p.salePrice) || 0);
    const row = [
      idx + 1,
      p.id || '',
      p.name || '',
      p.category || '',
      p.brandOrModel || '',
      p.imeiOrSerial || '',
      p.sku || '',
      Number(p.stock) || 0,
      Number(p.purchasePrice) || 0,
      Number(p.salePrice) || 0,
      cost,
      saleVal,
      p.createdAt ? new Date(p.createdAt).toLocaleDateString() : ''
    ];
    csvRows.push(row.map(val => `"${String(val).replace(/"/g, '""')}"`).join(','));
  });

  // Include UTF-8 BOM so Excel opens Urdu / special characters correctly
  const blob = new Blob(['\uFEFF' + csvRows.join('\r\n')], { type: 'text/csv;charset=utf-8;' });
  const url = URL.createObjectURL(blob);
  const link = document.createElement('a');
  const cleanShop = shopName.replace(/[^a-zA-Z0-9_-]/g, '_');
  const dateStr = new Date().toISOString().split('T')[0];
  link.setAttribute('href', url);
  link.setAttribute('download', `${cleanShop}_Stock_Inventory_${dateStr}.csv`);
  document.body.appendChild(link);
  link.click();
  document.body.removeChild(link);
  URL.revokeObjectURL(url);
}

/**
 * 3. EXPORT TO DATABASE FILE (.db / .json)
 */
export function exportStockToDB(products: Product[], shopName: string = 'Shop') {
  const dbBackup = {
    app: 'Balal Mobiles & EasyPaisa Ledger',
    type: 'STOCK_INVENTORY_DATABASE_BACKUP',
    version: '1.0',
    exportDate: new Date().toISOString(),
    totalProducts: products.length,
    totalStockUnits: products.reduce((sum, p) => sum + (Number(p.stock) || 0), 0),
    totalInventoryCost: products.reduce((sum, p) => sum + ((Number(p.stock) || 0) * (Number(p.purchasePrice) || 0)), 0),
    products: products
  };

  const jsonString = JSON.stringify(dbBackup, null, 2);
  const blob = new Blob([jsonString], { type: 'application/json' });
  const url = URL.createObjectURL(blob);
  const link = document.createElement('a');
  const cleanShop = shopName.replace(/[^a-zA-Z0-9_-]/g, '_');
  const dateStr = new Date().toISOString().split('T')[0];
  link.setAttribute('href', url);
  link.setAttribute('download', `${cleanShop}_Stock_Backup_${dateStr}.db`);
  document.body.appendChild(link);
  link.click();
  document.body.removeChild(link);
  URL.revokeObjectURL(url);
}

/**
 * 4. DOWNLOAD SAMPLE TEMPLATE (Excel or CSV)
 */
export function downloadStockSampleTemplate(format: 'xlsx' | 'csv') {
  const sampleItems = [
    {
      'Product / Item Name': 'Samsung Galaxy A15 (8GB/128GB)',
      'Category': 'MOBILES',
      'Brand / Model': 'Samsung A15',
      'IMEI / Serial / Barcode': '358941098472910',
      'SKU': 'SAM-A15-BLU',
      'Stock Quantity': 5,
      'Purchase Price (Rs)': 42000,
      'Sale Price (Rs)': 45500,
    },
    {
      'Product / Item Name': 'Faster Fast Charger 25W Type-C',
      'Category': 'CHARGERS',
      'Brand / Model': 'Faster FC-25',
      'IMEI / Serial / Barcode': '8901234567890',
      'SKU': 'FST-CHG-25W',
      'Stock Quantity': 25,
      'Purchase Price (Rs)': 850,
      'Sale Price (Rs)': 1400,
    },
    {
      'Product / Item Name': 'Airpods Pro 2 Wireless Earbuds (ANC)',
      'Category': 'EARPHONES',
      'Brand / Model': 'Apple Clone / TWS',
      'IMEI / Serial / Barcode': '6934123456789',
      'SKU': 'TWS-PRO-2',
      'Stock Quantity': 15,
      'Purchase Price (Rs)': 1600,
      'Sale Price (Rs)': 2500,
    },
    {
      'Product / Item Name': '9D Curved Glass Protector (iPhone 14/15)',
      'Category': 'PROTECTORS',
      'Brand / Model': '9D Glass',
      'IMEI / Serial / Barcode': '',
      'SKU': 'GLS-IPH-14',
      'Stock Quantity': 50,
      'Purchase Price (Rs)': 90,
      'Sale Price (Rs)': 300,
    },
    {
      'Product / Item Name': 'Fast Charging Braided Cable 65W (Type-C to C)',
      'Category': 'CABLES',
      'Brand / Model': 'Remax 65W',
      'IMEI / Serial / Barcode': '8934567890123',
      'SKU': 'CBL-65W-CC',
      'Stock Quantity': 30,
      'Purchase Price (Rs)': 220,
      'Sale Price (Rs)': 500,
    }
  ];

  if (format === 'xlsx') {
    const worksheet = XLSX.utils.json_to_sheet(sampleItems);
    worksheet['!cols'] = [
      { wch: 35 },
      { wch: 16 },
      { wch: 22 },
      { wch: 24 },
      { wch: 18 },
      { wch: 15 },
      { wch: 20 },
      { wch: 20 },
    ];
    const workbook = XLSX.utils.book_new();
    XLSX.utils.book_append_sheet(workbook, worksheet, 'Sample Stock');
    XLSX.writeFile(workbook, 'Stock_Import_Sample_Template.xlsx');
  } else {
    const headers = [
      'Product / Item Name',
      'Category',
      'Brand / Model',
      'IMEI / Serial / Barcode',
      'SKU',
      'Stock Quantity',
      'Purchase Price (Rs)',
      'Sale Price (Rs)'
    ];
    const csvRows = [headers.join(',')];
    sampleItems.forEach(item => {
      csvRows.push([
        `"${item['Product / Item Name']}"`,
        `"${item.Category}"`,
        `"${item['Brand / Model']}"`,
        `"${item['IMEI / Serial / Barcode']}"`,
        `"${item.SKU}"`,
        item['Stock Quantity'],
        item['Purchase Price (Rs)'],
        item['Sale Price (Rs)']
      ].join(','));
    });
    const blob = new Blob(['\uFEFF' + csvRows.join('\r\n')], { type: 'text/csv;charset=utf-8;' });
    const url = URL.createObjectURL(blob);
    const link = document.createElement('a');
    link.href = url;
    link.download = 'Stock_Import_Sample_Template.csv';
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
    URL.revokeObjectURL(url);
  }
}

/**
 * 5. PARSE IMPORTED FILE (.xlsx, .xls, .csv, .db, .json)
 */
export async function parseStockFile(file: File): Promise<{
  success: boolean;
  items: ParsedStockItem[];
  error?: string;
  sourceType: 'excel' | 'csv' | 'db';
}> {
  try {
    const fileName = file.name.toLowerCase();

    // 1. If JSON or .db file
    if (fileName.endsWith('.db') || fileName.endsWith('.json')) {
      const text = await file.text();
      const parsed = JSON.parse(text);
      let rawList: any[] = [];

      if (Array.isArray(parsed)) {
        rawList = parsed;
      } else if (parsed && Array.isArray(parsed.products)) {
        rawList = parsed.products;
      } else if (parsed && typeof parsed === 'object') {
        // Find any array property inside
        const arr = Object.values(parsed).find(v => Array.isArray(v));
        if (arr && Array.isArray(arr)) {
          rawList = arr;
        }
      }

      if (rawList.length === 0) {
        return { success: false, items: [], error: 'Database / JSON file ma koi valid product records nahi mile.', sourceType: 'db' };
      }

      const items: ParsedStockItem[] = rawList.map((p) => ({
        id: p.id,
        name: p.name || p.title || p.productName || 'Unnamed Item',
        category: normalizeCategory(p.category),
        brandOrModel: p.brandOrModel || p.brand || p.model || '',
        imeiOrSerial: p.imeiOrSerial || p.imei || p.barcode || p.serial || '',
        sku: p.sku || p.code || '',
        stock: Math.max(0, Number(p.stock) || 0),
        purchasePrice: Math.max(0, Number(p.purchasePrice) || Number(p.costPrice) || 0),
        salePrice: Math.max(0, Number(p.salePrice) || Number(p.price) || 0),
        image: p.image || undefined,
      })).filter(item => item.name.trim().length > 0);

      return { success: true, items, sourceType: 'db' };
    }

    // 2. If Excel (.xlsx, .xls) or CSV (.csv)
    const buffer = await file.arrayBuffer();
    const workbook = XLSX.read(buffer, { type: 'array' });
    const firstSheetName = workbook.SheetNames[0];
    if (!firstSheetName) {
      return { success: false, items: [], error: 'Spreadsheet sheet khali hai.', sourceType: fileName.endsWith('.csv') ? 'csv' : 'excel' };
    }

    const worksheet = workbook.Sheets[firstSheetName];
    const rawRows = XLSX.utils.sheet_to_json<Record<string, any>>(worksheet, { defval: '' });

    if (!rawRows || rawRows.length === 0) {
      return { success: false, items: [], error: 'File ma koi data rows nahi mile.', sourceType: fileName.endsWith('.csv') ? 'csv' : 'excel' };
    }

    const items: ParsedStockItem[] = [];

    rawRows.forEach((row) => {
      // Find value by checking variations of possible header names
      const findVal = (...keys: string[]) => {
        for (const k of Object.keys(row)) {
          const lowerK = k.toLowerCase().replace(/[^a-z0-9]/g, '');
          for (const search of keys) {
            const lowerSearch = search.toLowerCase().replace(/[^a-z0-9]/g, '');
            if (lowerK === lowerSearch || lowerK.includes(lowerSearch)) {
              return row[k];
            }
          }
        }
        return '';
      };

      const name = String(findVal('productname', 'itemname', 'name', 'product', 'item', 'tafseel', 'title', 'description') || '').trim();
      if (!name) return; // Skip empty row

      const categoryStr = String(findVal('category', 'type', 'qisam') || '');
      const brandOrModel = String(findVal('brandmodel', 'brand', 'model', 'company') || '').trim();
      const imeiOrSerial = String(findVal('imeiserialbarcode', 'imei', 'barcode', 'serial', 'imeinumber', 'barcodeno') || '').trim();
      const sku = String(findVal('sku', 'code', 'itemcode', 'productcode') || '').trim();

      const rawStock = findVal('stockquantity', 'stock', 'qty', 'quantity', 'tadad', 'units');
      const rawPurchase = findVal('purchaseprice', 'purchase', 'cost', 'kharid', 'buyprice', 'buyingprice');
      const rawSale = findVal('saleprice', 'sale', 'price', 'farokht', 'sellingprice', 'retailprice', 'rate');
      const id = String(findVal('itemid', 'id', 'productid') || '').trim();

      const cleanNum = (val: any) => {
        if (typeof val === 'number') return val;
        if (!val) return 0;
        const cleaned = String(val).replace(/[^0-9.-]/g, '');
        return Number(cleaned) || 0;
      };

      items.push({
        id: id || undefined,
        name,
        category: normalizeCategory(categoryStr),
        brandOrModel,
        imeiOrSerial,
        sku,
        stock: cleanNum(rawStock),
        purchasePrice: cleanNum(rawPurchase),
        salePrice: cleanNum(rawSale),
      });
    });

    if (items.length === 0) {
      return { 
        success: false, 
        items: [], 
        error: 'File se koi valid items parse nahi ho sakay. Baraye meharbani columns check karein: Name, Category, Stock, Purchase Price, Sale Price.', 
        sourceType: fileName.endsWith('.csv') ? 'csv' : 'excel' 
      };
    }

    return { 
      success: true, 
      items, 
      sourceType: fileName.endsWith('.csv') ? 'csv' : 'excel' 
    };
  } catch (err: any) {
    console.error('File parsing error:', err);
    return { success: false, items: [], error: err.message || 'File read karne ma masla pesh aya.', sourceType: 'excel' };
  }
}
