export type TransactionType = 'BUY_CASH' | 'SELL_CASH' | 'BUY_EASYPAISA' | 'SELL_EASYPAISA' | 'EXPENSE' | 'DISCREPANCY_LOSS';

export type PaymentMethod = 
  | 'EASYPAISA' 
  | 'JAZZCASH' 
  | 'SADAPAY' 
  | 'NAYAPAY' 
  | 'RAAST' 
  | 'MEEZAN_BANK' 
  | 'HBL' 
  | 'UBL' 
  | 'ALLIED_BANK' 
  | 'MCB_BANK' 
  | 'BANK_ALFALAH' 
  | 'CASH' 
  | 'BANK';

export type ProductCategory = 'MOBILES' | 'CHARGERS' | 'EARPHONES' | 'COVERS' | 'PROTECTORS' | 'CABLES' | 'BATTERIES' | 'ACCESSORIES' | 'OTHER';

export interface ProductUnitItem {
  id: string; // unique unit identifier e.g. "unit-1"
  imei1?: string;
  imei2?: string;
  serialNo?: string;
  color?: string;
  storageRam?: string;
  condition?: 'NEW' | 'USED';
  ptaStatus?: 'PTA_APPROVED' | 'NON_PTA' | 'JV' | 'FACTORY_UNLOCK';
  batteryHealth?: string;
  status: 'AVAILABLE' | 'SOLD';
  soldInvoiceNo?: string;
  soldDate?: string;
}

export interface Product {
  id: string;
  name: string;
  category: ProductCategory;
  purchasePrice: number; // Khareed Qeemat
  salePrice: number;     // Farokht Qeemat
  stock: number;         // Stock Tadad
  image?: string;        // Photo URL or Data URL
  brandOrModel?: string; // e.g. Vivo Y21, Samsung 25W
  imeiOrSerial?: string; // Primary IMEI or Serial No
  sku?: string;          // SKU or Barcode Number
  createdAt: number;

  // Individual Units / Multi-IMEI & Multi-Color tracking
  units?: ProductUnitItem[];

  // Dynamic Category Specifications
  color?: string;
  ramStorage?: string;
  condition?: 'NEW' | 'USED';
  ptaStatus?: 'PTA_APPROVED' | 'NON_PTA' | 'JV' | 'FACTORY_UNLOCK';
  warranty?: string;
  batteryHealth?: string;
  wattage?: string;
  portType?: string;
  compatibleModel?: string;
  protectorType?: string;
  cableType?: string;
  batteryCapacity?: string;
}

export interface ProductSaleItem {
  productId: string;
  productName: string;
  category: ProductCategory;
  quantity: number;
  purchasePrice: number;
  unitSalePrice: number;
  totalSalePrice: number;
  image?: string;

  // Specific Unit Sold Details
  selectedUnitId?: string;
  selectedImei1?: string;
  selectedImei2?: string;
  selectedColor?: string;
  selectedSerial?: string;
  selectedCondition?: string;
  selectedRamStorage?: string;
}

export interface ProductSale {
  id: string;
  invoiceNo: string;
  date: string; // YYYY-MM-DD
  time: string; // HH:mm:ss
  customerName: string;
  customerPhone?: string;
  items: ProductSaleItem[];
  totalAmount: number;
  discount: number;
  netAmount: number;
  totalPurchaseCost: number;
  profit: number; // netAmount - totalPurchaseCost
  paymentMethod: PaymentMethod;
  notes?: string;
  createdAt: number;
}

export interface Transaction {
  id: string;
  date: string; // YYYY-MM-DD
  time: string; // HH:mm:ss
  type: TransactionType;
  customerName: string;
  customerPhone?: string;
  trxId?: string; // EasyPaisa TRX ID / Ref No
  
  // Financial amounts
  easyPaisaAmount: number; // Amount handled via EasyPaisa
  cashAmount: number;      // Cash handled
  feeProfit: number;       // Commission / Profit earned on this transaction
  expenseAmount: number;   // Expense or Loss if type is EXPENSE/DISCREPANCY_LOSS
  
  paymentMethod: PaymentMethod;
  notes?: string;
  createdAt: number;
}

export interface Expense {
  id: string;
  date: string;
  category: 'RENT' | 'ELECTRICITY' | 'TEA_FOOD' | 'TAX_LOAD' | 'SHORTAGE_LOSS' | 'OTHER';
  amount: number;
  description: string;
  createdAt: number;
}

export interface DailyBalance {
  date: string; // YYYY-MM-DD
  openingCash: number;
  openingEasyPaisa: number;
  notes?: string;
}

export interface AllowedAccount {
  id: string;
  email: string;
  password: string;
  name: string;
  role: 'Owner' | 'Manager' | 'Staff';
}

export interface AppSettings {
  shopName: string;
  ownerName: string;
  phone: string;
  address: string;
  pinCode: string; // Default: '6242'
  isLocked: boolean;
  theme?: 'dark' | 'light';
  language?: 'roman' | 'en';
  easyPaisaNumber?: string;
  jazzCashNumber?: string;
  biometricUnlockEnabled?: boolean;
  allowedAccounts?: AllowedAccount[];
}

export interface CustomerSummary {
  name: string;
  phone: string;
  totalTransactions: number;
  totalBuyVolume: number;
  totalSellVolume: number;
  totalProfitGenerated: number;
  lastTransactionDate: string;
}

export interface MobilePurchaseRecord {
  id: string;
  receiptNo: string;        // e.g. PUR-1001
  date: string;             // YYYY-MM-DD
  time: string;             // HH:mm AM/PM
  
  // Seller / Supplier Information
  sellerName: string;
  sellerCnic: string;
  sellerPhone: string;
  sellerAddress?: string;
  sellerPhoto?: string;     // Base64 Data URL or Image URL
  cnicFrontPhoto?: string;  // Base64 Data URL
  cnicBackPhoto?: string;   // Base64 Data URL

  // Mobile Specifications
  mobileBrandModel: string; // e.g. Vivo Y21, Samsung Galaxy A14, iPhone 12
  condition: 'NEW' | 'USED'; // نیا / پرانا
  imei1: string;
  imei2?: string;
  color?: string;
  ramStorage?: string;      // e.g. 4GB / 64GB
  mobilePhoto?: string;     // Base64 Data URL or Image URL

  // Included Accessories
  hasBox: boolean;          // ڈبہ
  hasCharger: boolean;      // چارجر
  hasCable: boolean;        // کیبل
  hasHandsfree: boolean;    // ہینڈز فری
  hasWarrantyCard: boolean; // وارنٹی / بل

  // Financial & Remarks
  purchasePrice: number;    // خرید قیمت (PKR)
  paymentMethod: 'CASH' | 'EASYPAISA' | 'JAZZCASH' | 'BANK';
  supplierId?: string;      // Optional supplier link
  sku?: string;             // SKU or Barcode Number
  notes?: string;
  createdAt: number;
}

export interface Supplier {
  id: string;
  name: string;
  phone: string;
  cnic?: string;
  address?: string;
  companyName?: string; // e.g. Lahore Wholesale Market, Oppo Distributor
  openingBalance: number; // Opening balance / Dues
  createdAt: number;
}

export interface SupplierSummary {
  supplier: Supplier;
  totalPurchasedAmount: number;
  totalItemsCount: number;
  lastPurchaseDate: string;
}
