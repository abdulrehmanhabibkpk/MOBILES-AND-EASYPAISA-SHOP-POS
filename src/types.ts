export interface Product {
  id: string;
  sku: string;
  name: string;
  category: 'MOBILES' | 'ACCESSORIES' | 'PARTS' | 'SERVICES' | 'OTHER';
  purchase_price: number;
  sale_price: number;
  stock: number;
  min_stock_alert?: number;
  imei_or_serial?: string;
  brand?: string;
  model?: string;
  color?: string;
  condition?: 'NEW' | 'USED' | 'REFURBISHED';
  warranty?: string;
  created_at?: string;
}

export interface SaleItem {
  product_id: string;
  name: string;
  quantity: number;
  unit_price: number;
  purchase_price: number;
  imei?: string;
  total: number;
}

export interface SaleRecord {
  id: string;
  invoice_no: string;
  customer_name: string;
  customer_phone: string;
  items: SaleItem[];
  subtotal: number;
  discount: number;
  grand_total: number;
  paid_amount: number;
  due_amount: number;
  payment_method: 'CASH' | 'EASYPAISA' | 'JAZZCASH' | 'BANK' | 'CREDIT';
  date: string;
  notes?: string;
}

export interface CustomerKhata {
  id: string;
  customer_name: string;
  customer_phone: string;
  total_credit: number;
  total_paid: number;
  balance: number;
  last_transaction: string;
}

export interface ShopProfile {
  shop_name: string;
  owner_name: string;
  phone: string;
  address: string;
  currency: string;
  footer_message: string;
}
