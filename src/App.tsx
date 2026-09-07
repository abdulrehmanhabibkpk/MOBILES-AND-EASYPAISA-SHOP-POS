import React, { useState, useEffect, useMemo } from 'react';
import {
  Package,
  ShoppingCart,
  Smartphone,
  Users,
  Settings,
  Plus,
  Search,
  Zap,
  Check,
  X,
  Trash2,
  Edit3,
  Printer,
  FileDown,
  Database,
  RefreshCw,
  TrendingUp,
  AlertCircle,
  Tag,
  ArrowUpRight,
  ShieldCheck,
  FileText
} from 'lucide-react';
import { Product, SaleRecord, ShopProfile, CustomerKhata } from './types';

// Default Demo Products
const INITIAL_PRODUCTS: Product[] = [
  {
    id: 'prod-1',
    sku: 'MOB-SAM-S23',
    name: 'Samsung Galaxy S23 Ultra (256GB Phantom Black)',
    category: 'MOBILES',
    brand: 'Samsung',
    model: 'S23 Ultra',
    purchase_price: 215000,
    sale_price: 238000,
    stock: 4,
    imei_or_serial: '358291048291039, 358291048291040',
    condition: 'NEW',
    warranty: '1 Year Official PTA Approved',
    created_at: '2026-03-01'
  },
  {
    id: 'prod-2',
    sku: 'MOB-IPH-14P',
    name: 'Apple iPhone 14 Pro (128GB Deep Purple)',
    category: 'MOBILES',
    brand: 'Apple',
    model: 'iPhone 14 Pro',
    purchase_price: 245000,
    sale_price: 269000,
    stock: 2,
    imei_or_serial: '359102938475829',
    condition: 'NEW',
    warranty: '10 Months Apple Care',
    created_at: '2026-03-02'
  },
  {
    id: 'prod-3',
    sku: 'MOB-RED-N13',
    name: 'Xiaomi Redmi Note 13 (8GB / 256GB Midnight Black)',
    category: 'MOBILES',
    brand: 'Xiaomi',
    model: 'Redmi Note 13',
    purchase_price: 49000,
    sale_price: 54999,
    stock: 9,
    imei_or_serial: '869201928471928, 869201928471929',
    condition: 'NEW',
    warranty: '1 Year Brand Warranty',
    created_at: '2026-03-03'
  },
  {
    id: 'prod-4',
    sku: 'ACC-CHG-65W',
    name: 'Anker PowerPort 65W GaN Fast Charger Type-C',
    category: 'ACCESSORIES',
    brand: 'Anker',
    purchase_price: 4200,
    sale_price: 5800,
    stock: 18,
    warranty: '6 Months Replacement',
    created_at: '2026-03-04'
  },
  {
    id: 'prod-5',
    sku: 'ACC-AIR-ANC',
    name: 'Audionic Airbud 550 Wireless TWS Earbuds ANC',
    category: 'ACCESSORIES',
    brand: 'Audionic',
    purchase_price: 3600,
    sale_price: 4999,
    stock: 12,
    warranty: '1 Year Warranty',
    created_at: '2026-03-05'
  },
  {
    id: 'prod-6',
    sku: 'ACC-GLS-9D',
    name: 'Super D+ 9D Ceramic Matte Screen Protector (Bulk Pack)',
    category: 'ACCESSORIES',
    brand: 'Universal',
    purchase_price: 110,
    sale_price: 350,
    stock: 45,
    created_at: '2026-03-06'
  },
  {
    id: 'prod-7',
    sku: 'SRV-SCR-REP',
    name: 'OLED Display Screen Replacement & Fitting Service',
    category: 'SERVICES',
    purchase_price: 1800,
    sale_price: 3500,
    stock: 99,
    warranty: 'Checking Warranty',
    created_at: '2026-03-07'
  }
];

const INITIAL_SHOP: ShopProfile = {
  shop_name: 'Balal Mobile & Electronic Center',
  owner_name: 'Balal Khan',
  phone: '0300-1234567',
  address: 'Shop # 12, Main Mobile Market, Pakistan',
  currency: 'Rs.',
  footer_message: 'سامان خریدنے کا شکریہ! برائے مہربانی رسید سنبھال کر رکھیں۔'
};

export default function App() {
  // Navigation
  const [activeTab, setActiveTab] = useState<'inventory' | 'pos' | 'mobiles' | 'khata' | 'php_export' | 'settings'>('inventory');
  const [lang, setLang] = useState<'ur' | 'en'>('ur');

  // Persistence State
  const [products, setProducts] = useState<Product[]>(() => {
    const saved = localStorage.getItem('bm_products');
    return saved ? JSON.parse(saved) : INITIAL_PRODUCTS;
  });

  const [sales, setSales] = useState<SaleRecord[]>(() => {
    const saved = localStorage.getItem('bm_sales');
    return saved ? JSON.parse(saved) : [];
  });

  const [shop, setShop] = useState<ShopProfile>(() => {
    const saved = localStorage.getItem('bm_shop');
    return saved ? JSON.parse(saved) : INITIAL_SHOP;
  });

  // Save changes
  useEffect(() => {
    localStorage.setItem('bm_products', JSON.stringify(products));
  }, [products]);

  useEffect(() => {
    localStorage.setItem('bm_sales', JSON.stringify(sales));
  }, [sales]);

  useEffect(() => {
    localStorage.setItem('bm_shop', JSON.stringify(shop));
  }, [shop]);

  // Inventory Filters
  const [searchQuery, setSearchQuery] = useState('');
  const [categoryFilter, setCategoryFilter] = useState('ALL');
  const [stockAlertFilter, setStockAlertFilter] = useState(false);

  // Inline Quick-Edit State (Table editing state mapping: prodId -> { purchase_price, sale_price, stock })
  const [editingRowId, setEditingRowId] = useState<string | null>(null);
  const [inlineDraft, setInlineDraft] = useState<{ purchase_price: number; sale_price: number; stock: number }>({
    purchase_price: 0,
    sale_price: 0,
    stock: 0
  });

  // Toast Notification State
  const [toast, setToast] = useState<{ message: string; type: 'success' | 'info' | 'error' } | null>(null);
  const showToast = (message: string, type: 'success' | 'info' | 'error' = 'success') => {
    setToast({ message, type });
    setTimeout(() => setToast(null), 3200);
  };

  // Product Add / Full Edit Modal
  const [showProductModal, setShowProductModal] = useState(false);
  const [productForm, setProductForm] = useState<Partial<Product>>({
    name: '',
    category: 'MOBILES',
    purchase_price: 0,
    sale_price: 0,
    stock: 1,
    brand: '',
    model: '',
    imei_or_serial: '',
    warranty: '',
    condition: 'NEW'
  });

  // POS State
  const [cart, setCart] = useState<Array<{ product: Product; quantity: number; unit_price: number; imei: string }>>([]);
  const [posCustomerName, setPosCustomerName] = useState('');
  const [posCustomerPhone, setPosCustomerPhone] = useState('');
  const [posDiscount, setPosDiscount] = useState(0);
  const [posPaidAmount, setPosPaidAmount] = useState(0);
  const [posPaymentMethod, setPosPaymentMethod] = useState<'CASH' | 'EASYPAISA' | 'JAZZCASH' | 'BANK' | 'CREDIT'>('CASH');
  const [posReceiptModal, setPosReceiptModal] = useState<SaleRecord | null>(null);

  // Quick Inline Edit Handlers
  const handleStartQuickEdit = (p: Product) => {
    setEditingRowId(p.id);
    setInlineDraft({
      purchase_price: p.purchase_price,
      sale_price: p.sale_price,
      stock: p.stock
    });
  };

  const handleCancelQuickEdit = () => {
    setEditingRowId(null);
  };

  const handleSaveQuickEdit = (id: string) => {
    if (inlineDraft.sale_price <= 0) {
      showToast('براہ کرم درست فروخت قیمت درج کریں!', 'error');
      return;
    }
    setProducts(prev =>
      prev.map(item =>
        item.id === id
          ? {
              ...item,
              purchase_price: Number(inlineDraft.purchase_price) || 0,
              sale_price: Number(inlineDraft.sale_price) || 0,
              stock: Math.max(0, Number(inlineDraft.stock) || 0)
            }
          : item
      )
    );
    setEditingRowId(null);
    showToast('پروڈکٹ کی قیمت اور اسٹاک فوری طور پر اپڈیٹ ہو گیا!', 'success');
  };

  const handleQuickStockStep = (id: string, delta: number) => {
    setProducts(prev =>
      prev.map(item => {
        if (item.id === id) {
          const newStock = Math.max(0, item.stock + delta);
          return { ...item, stock: newStock };
        }
        return item;
      })
    );
    showToast(`اسٹاک میں ${delta > 0 ? '+1 دانہ بڑھا' : '-1 دانہ گھٹا'} دیا گیا!`, 'info');
  };

  const handleDeleteProduct = (id: string, name: string) => {
    if (confirm(`کیا آپ واقعی "${name}" کو ڈیلیٹ کرنا چاہتے ہیں؟`)) {
      setProducts(prev => prev.filter(p => p.id !== id));
      showToast('پروڈکٹ کامیابی سے ڈیلیٹ ہو گئی!', 'info');
    }
  };

  const handleSaveProductForm = (e: React.FormEvent) => {
    e.preventDefault();
    if (!productForm.name || !productForm.sale_price) {
      showToast('براہ کرم نام اور قیمت درج کریں!', 'error');
      return;
    }

    if (productForm.id) {
      // Edit
      setProducts(prev =>
        prev.map(p => (p.id === productForm.id ? ({ ...p, ...productForm } as Product) : p))
      );
      showToast('پروڈکٹ کامیابی سے اپڈیٹ ہو گئی!', 'success');
    } else {
      // Create
      const newProd: Product = {
        id: 'prod-' + Date.now(),
        sku: productForm.sku || 'SKU-' + Math.floor(1000 + Math.random() * 9000),
        name: productForm.name || '',
        category: productForm.category || 'MOBILES',
        purchase_price: Number(productForm.purchase_price) || 0,
        sale_price: Number(productForm.sale_price) || 0,
        stock: Number(productForm.stock) || 0,
        brand: productForm.brand || '',
        model: productForm.model || '',
        imei_or_serial: productForm.imei_or_serial || '',
        warranty: productForm.warranty || '',
        condition: productForm.condition || 'NEW',
        created_at: new Date().toISOString().split('T')[0]
      };
      setProducts(prev => [newProd, ...prev]);
      showToast('نئی پروڈکٹ کامیابی سے شامل ہو گئی!', 'success');
    }
    setShowProductModal(false);
  };

  // Filtered Products
  const filteredProducts = useMemo(() => {
    return products.filter(p => {
      const matchSearch =
        p.name.toLowerCase().includes(searchQuery.toLowerCase()) ||
        p.sku.toLowerCase().includes(searchQuery.toLowerCase()) ||
        (p.imei_or_serial && p.imei_or_serial.includes(searchQuery)) ||
        (p.brand && p.brand.toLowerCase().includes(searchQuery.toLowerCase()));

      const matchCat = categoryFilter === 'ALL' || p.category === categoryFilter;
      const matchAlert = !stockAlertFilter || p.stock <= 3;
      return matchSearch && matchCat && matchAlert;
    });
  }, [products, searchQuery, categoryFilter, stockAlertFilter]);

  // Inventory Totals
  const totalStockItems = products.reduce((sum, p) => sum + p.stock, 0);
  const totalStockValue = products.reduce((sum, p) => sum + p.stock * p.purchase_price, 0);
  const totalPotentialProfit = products.reduce(
    (sum, p) => sum + p.stock * (p.sale_price - p.purchase_price),
    0
  );
  const lowStockCount = products.filter(p => p.stock <= 3).length;

  // POS Calculations
  const cartSubtotal = cart.reduce((sum, item) => sum + item.quantity * item.unit_price, 0);
  const cartGrandTotal = Math.max(0, cartSubtotal - posDiscount);
  const cartDueAmount = Math.max(0, cartGrandTotal - posPaidAmount);

  const handleAddToCart = (product: Product) => {
    if (product.stock <= 0) {
      showToast('اس پروڈکٹ کا اسٹاک ختم ہے!', 'error');
      return;
    }
    setCart(prev => {
      const existing = prev.find(item => item.product.id === product.id);
      if (existing) {
        if (existing.quantity >= product.stock) {
          showToast(`اسٹاک میں صرف ${product.stock} دستیاب ہیں!`, 'error');
          return prev;
        }
        return prev.map(item =>
          item.product.id === product.id
            ? { ...item, quantity: item.quantity + 1 }
            : item
        );
      }
      return [
        ...prev,
        {
          product,
          quantity: 1,
          unit_price: product.sale_price,
          imei: product.imei_or_serial ? product.imei_or_serial.split(',')[0].trim() : ''
        }
      ];
    });
    setPosPaidAmount(cartGrandTotal + product.sale_price);
  };

  const handleCheckout = () => {
    if (cart.length === 0) {
      showToast('ٹوکری خالی ہے!', 'error');
      return;
    }

    // Deduct stock
    setProducts(prev =>
      prev.map(p => {
        const inCart = cart.find(c => c.product.id === p.id);
        if (inCart) {
          return { ...p, stock: Math.max(0, p.stock - inCart.quantity) };
        }
        return p;
      })
    );

    const saleRecord: SaleRecord = {
      id: 'inv-' + Date.now(),
      invoice_no: 'INV-' + Math.floor(100000 + Math.random() * 900000),
      customer_name: posCustomerName || 'واک ان کسٹمر',
      customer_phone: posCustomerPhone || '—',
      items: cart.map(c => ({
        product_id: c.product.id,
        name: c.product.name,
        quantity: c.quantity,
        unit_price: c.unit_price,
        purchase_price: c.product.purchase_price,
        imei: c.imei,
        total: c.quantity * c.unit_price
      })),
      subtotal: cartSubtotal,
      discount: posDiscount,
      grand_total: cartGrandTotal,
      paid_amount: posPaidAmount,
      due_amount: cartDueAmount,
      payment_method: posPaymentMethod,
      date: new Date().toLocaleString('ur-PK'),
      notes: ''
    };

    setSales(prev => [saleRecord, ...prev]);
    setPosReceiptModal(saleRecord);
    setCart([]);
    setPosDiscount(0);
    setPosPaidAmount(0);
    setPosCustomerName('');
    setPosCustomerPhone('');
    showToast('بل کامیابی سے مکمل اور پرنٹ کیلئے تیار ہے!', 'success');
  };

  return (
    <div className="min-h-screen bg-slate-950 text-slate-100 flex flex-col font-sans" dir="rtl">
      {/* Top Header */}
      <header className="bg-slate-900/90 backdrop-blur-md border-b border-slate-800 sticky top-0 z-40 px-4 py-3 shadow-xl">
        <div className="max-w-7xl mx-auto flex flex-wrap items-center justify-between gap-3">
          {/* Brand */}
          <div className="flex items-center gap-3">
            <div className="w-10 h-10 rounded-2xl bg-gradient-to-tr from-cyan-600 to-blue-600 flex items-center justify-center shadow-lg shadow-cyan-500/20 text-white font-black text-xl">
              📱
            </div>
            <div>
              <div className="flex items-center gap-2">
                <h1 className="text-lg font-black tracking-tight text-white">{shop.shop_name}</h1>
                <span className="bg-emerald-500/10 text-emerald-400 border border-emerald-500/30 text-[10px] font-bold px-2 py-0.5 rounded-full">
                  آن لائن POS
                </span>
              </div>
              <p className="text-xs text-slate-400 font-urdu">{shop.owner_name} • {shop.phone}</p>
            </div>
          </div>

          {/* Navigation Bar */}
          <nav className="flex items-center gap-1.5 bg-slate-950/80 p-1.5 rounded-2xl border border-slate-800 overflow-x-auto max-w-full">
            <button
              id="tab-inventory"
              onClick={() => setActiveTab('inventory')}
              className={`flex items-center gap-2 px-3.5 py-2 rounded-xl text-xs font-bold transition-all whitespace-nowrap ${
                activeTab === 'inventory'
                  ? 'bg-gradient-to-r from-cyan-600 to-blue-600 text-white shadow-md'
                  : 'text-slate-400 hover:text-slate-200 hover:bg-slate-900'
              }`}
            >
              <Package className="w-4 h-4" />
              <span>انوینٹری و ریٹس</span>
            </button>

            <button
              id="tab-pos"
              onClick={() => setActiveTab('pos')}
              className={`flex items-center gap-2 px-3.5 py-2 rounded-xl text-xs font-bold transition-all whitespace-nowrap ${
                activeTab === 'pos'
                  ? 'bg-gradient-to-r from-emerald-600 to-teal-600 text-white shadow-md'
                  : 'text-slate-400 hover:text-slate-200 hover:bg-slate-900'
              }`}
            >
              <ShoppingCart className="w-4 h-4" />
              <span>پوائنٹ آف سیل (POS)</span>
              {cart.length > 0 && (
                <span className="bg-amber-500 text-slate-950 px-1.5 py-0.2 rounded-full text-[10px] font-black">
                  {cart.length}
                </span>
              )}
            </button>

            <button
              id="tab-mobiles"
              onClick={() => setActiveTab('mobiles')}
              className={`flex items-center gap-2 px-3.5 py-2 rounded-xl text-xs font-bold transition-all whitespace-nowrap ${
                activeTab === 'mobiles'
                  ? 'bg-gradient-to-r from-purple-600 to-indigo-600 text-white shadow-md'
                  : 'text-slate-400 hover:text-slate-200 hover:bg-slate-900'
              }`}
            >
              <Smartphone className="w-4 h-4" />
              <span>موبائل IMEI لیجر</span>
            </button>

            <button
              id="tab-khata"
              onClick={() => setActiveTab('khata')}
              className={`flex items-center gap-2 px-3.5 py-2 rounded-xl text-xs font-bold transition-all whitespace-nowrap ${
                activeTab === 'khata'
                  ? 'bg-gradient-to-r from-amber-600 to-orange-600 text-white shadow-md'
                  : 'text-slate-400 hover:text-slate-200 hover:bg-slate-900'
              }`}
            >
              <Users className="w-4 h-4" />
              <span>کسٹمر ادھار کھاتہ</span>
            </button>

            <button
              id="tab-php-export"
              onClick={() => setActiveTab('php_export')}
              className={`flex items-center gap-2 px-3.5 py-2 rounded-xl text-xs font-bold transition-all whitespace-nowrap ${
                activeTab === 'php_export'
                  ? 'bg-slate-800 text-cyan-400 border border-cyan-500/40 shadow-md'
                  : 'text-slate-400 hover:text-slate-200 hover:bg-slate-900'
              }`}
            >
              <Database className="w-4 h-4" />
              <span>PHP / MySQL سورس</span>
            </button>

            <button
              id="tab-settings"
              onClick={() => setActiveTab('settings')}
              className={`flex items-center gap-2 px-3 py-2 rounded-xl text-xs font-bold transition-all whitespace-nowrap ${
                activeTab === 'settings'
                  ? 'bg-slate-800 text-white'
                  : 'text-slate-400 hover:text-slate-200 hover:bg-slate-900'
              }`}
            >
              <Settings className="w-4 h-4" />
            </button>
          </nav>
        </div>
      </header>

      {/* Main Content Area */}
      <main className="flex-1 max-w-7xl w-full mx-auto p-4 md:p-6 space-y-6">
        {/* =========================================================================
            TAB 1: INVENTORY & STOCK WITH INLINE QUICK-EDIT (HIGHLIGHTED FEATURE)
           ========================================================================= */}
        {activeTab === 'inventory' && (
          <div className="space-y-5 animate-in fade-in duration-300">
            {/* Quick Metrics Bar */}
            <div className="grid grid-cols-2 sm:grid-cols-4 gap-3">
              <div className="bg-slate-900/80 border border-slate-800 p-4 rounded-2xl">
                <span className="text-xs text-slate-400 font-bold block mb-1">کل موجود پروڈکٹس</span>
                <div className="flex items-baseline justify-between">
                  <span className="text-2xl font-black font-mono text-white">{products.length}</span>
                  <span className="text-xs text-cyan-400 font-mono font-bold">{totalStockItems} دانہ کل</span>
                </div>
              </div>

              <div className="bg-slate-900/80 border border-slate-800 p-4 rounded-2xl">
                <span className="text-xs text-slate-400 font-bold block mb-1">کل اسٹاک مالیت (لاگت)</span>
                <div className="flex items-baseline justify-between">
                  <span className="text-xl font-black font-mono text-cyan-300">Rs. {totalStockValue.toLocaleString()}</span>
                </div>
              </div>

              <div className="bg-slate-900/80 border border-slate-800 p-4 rounded-2xl">
                <span className="text-xs text-slate-400 font-bold block mb-1">متوقع منافع (مارجن)</span>
                <div className="flex items-baseline justify-between">
                  <span className="text-xl font-black font-mono text-emerald-400">Rs. {totalPotentialProfit.toLocaleString()}</span>
                  <span className="text-[10px] text-emerald-500 font-bold flex items-center">
                    <TrendingUp className="w-3 h-3 ml-0.5" /> منافع
                  </span>
                </div>
              </div>

              <div className="bg-slate-900/80 border border-slate-800 p-4 rounded-2xl">
                <span className="text-xs text-slate-400 font-bold block mb-1">کم اسٹاک الرٹس</span>
                <div className="flex items-baseline justify-between">
                  <span className={`text-2xl font-black font-mono ${lowStockCount > 0 ? 'text-amber-400' : 'text-slate-400'}`}>
                    {lowStockCount}
                  </span>
                  {lowStockCount > 0 && (
                    <button
                      onClick={() => setStockAlertFilter(!stockAlertFilter)}
                      className="text-[11px] text-amber-400 hover:underline font-bold"
                    >
                      {stockAlertFilter ? 'سب دکھائیں' : 'فلٹر کریں'}
                    </button>
                  )}
                </div>
              </div>
            </div>

            {/* Filter & Action Controls */}
            <div className="bg-slate-900 border border-slate-800 p-4 rounded-2xl flex flex-wrap items-center justify-between gap-3">
              {/* Search Bar */}
              <div className="relative flex-1 min-w-[240px]">
                <Search className="w-4 h-4 absolute right-3.5 top-1/2 -translate-y-1/2 text-slate-400" />
                <input
                  type="text"
                  placeholder="پروڈکٹ کا نام، ماڈل، SKU یا IMEI نمبر تلاش کریں..."
                  value={searchQuery}
                  onChange={e => setSearchQuery(e.target.value)}
                  className="w-full bg-slate-950 border border-slate-800 pr-10 pl-4 py-2.5 rounded-xl text-xs text-slate-200 focus:border-cyan-500 outline-none"
                />
              </div>

              {/* Category Filter Pills */}
              <div className="flex items-center gap-1.5 overflow-x-auto py-1">
                {['ALL', 'MOBILES', 'ACCESSORIES', 'PARTS', 'SERVICES'].map(cat => (
                  <button
                    key={cat}
                    onClick={() => setCategoryFilter(cat)}
                    className={`px-3 py-1.5 rounded-xl text-xs font-bold transition-all whitespace-nowrap ${
                      categoryFilter === cat
                        ? 'bg-cyan-500/20 text-cyan-300 border border-cyan-500/40'
                        : 'bg-slate-950 text-slate-400 border border-slate-800 hover:text-slate-200'
                    }`}
                  >
                    {cat === 'ALL'
                      ? 'تمام'
                      : cat === 'MOBILES'
                      ? '📱 موبائلز'
                      : cat === 'ACCESSORIES'
                      ? '🎧 سامان / اسیسریز'
                      : cat === 'PARTS'
                      ? '🔧 پارٹس'
                      : '🛠️ سروسز'}
                  </button>
                ))}
              </div>

              {/* Add Product Button */}
              <button
                id="btn-add-product"
                onClick={() => {
                  setProductForm({
                    name: '',
                    category: 'MOBILES',
                    purchase_price: 0,
                    sale_price: 0,
                    stock: 1,
                    brand: '',
                    model: '',
                    imei_or_serial: '',
                    warranty: '',
                    condition: 'NEW'
                  });
                  setShowProductModal(true);
                }}
                className="bg-gradient-to-r from-cyan-600 to-blue-600 hover:from-cyan-500 hover:to-blue-500 text-white px-4 py-2.5 rounded-xl text-xs font-bold flex items-center gap-2 shadow-lg shadow-cyan-500/20 active:scale-95 transition-all"
              >
                <Plus className="w-4 h-4" />
                <span>نئی پروڈکٹ شامل کریں</span>
              </button>
            </div>

            {/* PRODUCT INVENTORY TABLE WITH INLINE QUICK-EDIT */}
            <div className="bg-slate-900 border border-slate-800 rounded-3xl overflow-hidden shadow-2xl">
              <div className="p-4 border-b border-slate-800 flex items-center justify-between bg-slate-950/60">
                <div className="flex items-center gap-2">
                  <div className="p-1.5 rounded-lg bg-amber-500/10 text-amber-400 border border-amber-500/20">
                    <Zap className="w-4 h-4" />
                  </div>
                  <div>
                    <h3 className="text-sm font-black text-white">انوینٹری ٹیبل و فوری ریٹ/اسٹاک تبدیل</h3>
                    <p className="text-[11px] text-slate-400">
                      بغیر موڈل کھولے کسی بھی پروڈکٹ پر <span className="text-amber-400 font-bold">⚡ فوری ایڈٹ</span> دبائیں اور قیمت یا اسٹاک موقع پر تبدیل کریں
                    </p>
                  </div>
                </div>
                <span className="text-xs text-slate-400 font-mono">{filteredProducts.length} آئٹمز</span>
              </div>

              <div className="overflow-x-auto">
                <table className="w-full text-right text-xs">
                  <thead className="bg-slate-950/80 text-slate-400 font-bold border-b border-slate-800">
                    <tr>
                      <th className="p-3.5 pr-4 text-center font-mono w-12">#</th>
                      <th className="p-3.5">پروڈکٹ کی تفصیل و برانڈ</th>
                      <th className="p-3.5 text-center">کیٹیگری</th>
                      <th className="p-3.5 text-left font-mono">قیمت خرید (Cost)</th>
                      <th className="p-3.5 text-left font-mono">قیمت فروخت (Sale)</th>
                      <th className="p-3.5 text-left font-mono">منافع فی دانہ</th>
                      <th className="p-3.5 text-center">موجودہ اسٹاک</th>
                      <th className="p-3.5 text-left font-mono">کل مالیت</th>
                      <th className="p-3.5 text-center min-w-[170px]">ایکشنز (فوری تبدیل)</th>
                    </tr>
                  </thead>
                  <tbody className="divide-y divide-slate-800/60">
                    {filteredProducts.length === 0 ? (
                      <tr>
                        <td colSpan={9} className="p-8 text-center text-slate-500 text-sm">
                          کوئی پروڈکٹ موجود نہیں یا سرچ کے مطابق نتیجہ نہیں ملا۔
                        </td>
                      </tr>
                    ) : (
                      filteredProducts.map((p, idx) => {
                        const isEditing = editingRowId === p.id;
                        const cost = isEditing ? inlineDraft.purchase_price : p.purchase_price;
                        const sale = isEditing ? inlineDraft.sale_price : p.sale_price;
                        const stock = isEditing ? inlineDraft.stock : p.stock;
                        const profit = sale - cost;
                        const totalVal = stock * cost;

                        return (
                          <tr
                            key={p.id}
                            id={`row-${p.id}`}
                            className={`transition-colors ${
                              isEditing
                                ? 'bg-amber-950/20 border-y border-amber-500/40'
                                : 'hover:bg-slate-800/40'
                            }`}
                          >
                            {/* Index */}
                            <td className="p-3.5 pr-4 text-center font-mono font-bold text-slate-500">
                              {idx + 1}
                            </td>

                            {/* Details */}
                            <td className="p-3.5">
                              <div className="font-bold text-white text-sm flex items-center gap-2">
                                <span>{p.name}</span>
                                {p.warranty && (
                                  <span className="text-[10px] bg-cyan-500/10 text-cyan-400 px-1.5 py-0.5 rounded font-mono">
                                    {p.warranty}
                                  </span>
                                )}
                              </div>
                              <div className="flex items-center gap-2 text-[11px] text-slate-400 mt-0.5 font-mono">
                                <span className="bg-slate-800 px-1.5 py-0.5 rounded text-slate-300">
                                  {p.sku}
                                </span>
                                {p.brand && <span>برانڈ: {p.brand}</span>}
                                {p.imei_or_serial && (
                                  <span className="text-amber-400/90 font-mono text-[10px]">
                                    IMEI: {p.imei_or_serial}
                                  </span>
                                )}
                              </div>
                            </td>

                            {/* Category */}
                            <td className="p-3.5 text-center">
                              <span
                                className={`px-2 py-0.5 rounded-full text-[10px] font-bold ${
                                  p.category === 'MOBILES'
                                    ? 'bg-purple-500/20 text-purple-300 border border-purple-500/40'
                                    : p.category === 'ACCESSORIES'
                                    ? 'bg-blue-500/20 text-blue-300 border border-blue-500/40'
                                    : p.category === 'PARTS'
                                    ? 'bg-amber-500/20 text-amber-300 border border-amber-500/40'
                                    : 'bg-emerald-500/20 text-emerald-300 border border-emerald-500/40'
                                }`}
                              >
                                {p.category}
                              </span>
                            </td>

                            {/* Purchase Price (Cost) */}
                            <td className="p-3.5 text-left font-mono">
                              {isEditing ? (
                                <div className="relative inline-block">
                                  <span className="absolute right-1.5 top-1/2 -translate-y-1/2 text-[10px] text-slate-500 font-bold pointer-events-none">
                                    Rs.
                                  </span>
                                  <input
                                    type="number"
                                    step="any"
                                    value={inlineDraft.purchase_price}
                                    onChange={e =>
                                      setInlineDraft({
                                        ...inlineDraft,
                                        purchase_price: parseFloat(e.target.value) || 0
                                      })
                                    }
                                    onKeyDown={e => e.key === 'Enter' && handleSaveQuickEdit(p.id)}
                                    className="w-24 bg-slate-950 border border-slate-700 focus:border-cyan-500 pr-7 pl-1.5 py-1 rounded text-xs text-slate-200 font-mono font-bold text-left outline-none"
                                  />
                                </div>
                              ) : (
                                <span className="text-slate-400">Rs. {p.purchase_price.toLocaleString()}</span>
                              )}
                            </td>

                            {/* Sale Price */}
                            <td className="p-3.5 text-left font-mono">
                              {isEditing ? (
                                <div className="relative inline-block">
                                  <span className="absolute right-1.5 top-1/2 -translate-y-1/2 text-[10px] text-slate-500 font-bold pointer-events-none">
                                    Rs.
                                  </span>
                                  <input
                                    type="number"
                                    step="any"
                                    value={inlineDraft.sale_price}
                                    autoFocus
                                    onChange={e =>
                                      setInlineDraft({
                                        ...inlineDraft,
                                        sale_price: parseFloat(e.target.value) || 0
                                      })
                                    }
                                    onKeyDown={e => e.key === 'Enter' && handleSaveQuickEdit(p.id)}
                                    className="w-24 bg-slate-950 border border-emerald-500/80 focus:border-emerald-400 pr-7 pl-1.5 py-1 rounded text-xs text-emerald-400 font-mono font-black text-left outline-none"
                                  />
                                </div>
                              ) : (
                                <span className="font-bold text-white">Rs. {p.sale_price.toLocaleString()}</span>
                              )}
                            </td>

                            {/* Profit */}
                            <td className="p-3.5 text-left font-mono font-bold">
                              <span className={profit >= 0 ? 'text-teal-400' : 'text-rose-400'}>
                                {profit >= 0 ? '+' : ''} Rs. {profit.toLocaleString()}
                              </span>
                            </td>

                            {/* Stock */}
                            <td className="p-3.5 text-center">
                              {isEditing ? (
                                <div className="flex items-center justify-center gap-1">
                                  <button
                                    type="button"
                                    onClick={() =>
                                      setInlineDraft({
                                        ...inlineDraft,
                                        stock: Math.max(0, inlineDraft.stock - 1)
                                      })
                                    }
                                    className="w-6 h-6 bg-slate-800 hover:bg-slate-700 text-rose-400 rounded text-xs font-bold border border-slate-700 flex items-center justify-center"
                                  >
                                    -
                                  </button>
                                  <input
                                    type="number"
                                    min="0"
                                    value={inlineDraft.stock}
                                    onChange={e =>
                                      setInlineDraft({
                                        ...inlineDraft,
                                        stock: parseInt(e.target.value) || 0
                                      })
                                    }
                                    onKeyDown={e => e.key === 'Enter' && handleSaveQuickEdit(p.id)}
                                    className="w-14 bg-slate-950 border border-cyan-500/80 focus:border-cyan-400 px-1 py-1 rounded text-xs text-cyan-300 font-mono font-black text-center outline-none"
                                  />
                                  <button
                                    type="button"
                                    onClick={() =>
                                      setInlineDraft({
                                        ...inlineDraft,
                                        stock: inlineDraft.stock + 1
                                      })
                                    }
                                    className="w-6 h-6 bg-slate-800 hover:bg-slate-700 text-emerald-400 rounded text-xs font-bold border border-slate-700 flex items-center justify-center"
                                  >
                                    +
                                  </button>
                                </div>
                              ) : (
                                <span
                                  className={`px-2.5 py-1 rounded-full font-black font-mono text-xs ${
                                    p.stock === 0
                                      ? 'bg-rose-500/20 text-rose-300 border border-rose-500/40'
                                      : p.stock <= 3
                                      ? 'bg-amber-500/20 text-amber-300 border border-amber-500/40'
                                      : 'bg-emerald-500/10 text-emerald-300 border border-emerald-500/20'
                                  }`}
                                >
                                  {p.stock} دانہ
                                </span>
                              )}
                            </td>

                            {/* Total Value */}
                            <td className="p-3.5 text-left font-mono text-slate-300">
                              Rs. {totalVal.toLocaleString()}
                            </td>

                            {/* Actions with Quick-Edit Toggle */}
                            <td className="p-3.5 text-center">
                              {isEditing ? (
                                <div className="flex items-center justify-center gap-1.5">
                                  <button
                                    type="button"
                                    onClick={() => handleSaveQuickEdit(p.id)}
                                    className="px-2.5 py-1 bg-emerald-600 hover:bg-emerald-500 text-white rounded-md text-[11px] font-bold flex items-center gap-1 shadow active:scale-95 transition-all"
                                  >
                                    <Check className="w-3.5 h-3.5" />
                                    <span>محفوظ</span>
                                  </button>
                                  <button
                                    type="button"
                                    onClick={handleCancelQuickEdit}
                                    className="px-2 py-1 bg-slate-800 hover:bg-slate-700 text-slate-300 rounded-md text-[11px] font-bold transition-all"
                                  >
                                    <span>منسوخ</span>
                                  </button>
                                </div>
                              ) : (
                                <div className="flex items-center justify-center gap-1">
                                  {/* INLINE QUICK EDIT TRIGGER */}
                                  <button
                                    type="button"
                                    onClick={() => handleStartQuickEdit(p)}
                                    className="px-2 py-1 bg-amber-500/10 hover:bg-amber-500/20 text-amber-400 rounded-md border border-amber-500/30 flex items-center gap-1 font-bold text-[11px] transition-all hover:scale-105 active:scale-95"
                                    title="فوری ریٹ و اسٹاک تبدیل کریں"
                                  >
                                    <Zap className="w-3.5 h-3.5 text-amber-400" />
                                    <span>فوری ایڈٹ</span>
                                  </button>

                                  {/* Fast + / - Stepper */}
                                  <button
                                    type="button"
                                    onClick={() => handleQuickStockStep(p.id, 1)}
                                    className="w-6 h-6 bg-slate-800 hover:bg-slate-700 text-emerald-400 rounded-md border border-slate-700 font-bold flex items-center justify-center text-xs"
                                    title="1 دانہ بڑھائیں"
                                  >
                                    +
                                  </button>
                                  <button
                                    type="button"
                                    onClick={() => handleQuickStockStep(p.id, -1)}
                                    className="w-6 h-6 bg-slate-800 hover:bg-slate-700 text-rose-400 rounded-md border border-slate-700 font-bold flex items-center justify-center text-xs"
                                    title="1 دانہ گھٹائیں"
                                  >
                                    -
                                  </button>

                                  {/* Full Edit Modal */}
                                  <button
                                    type="button"
                                    onClick={() => {
                                      setProductForm(p);
                                      setShowProductModal(true);
                                    }}
                                    className="p-1 bg-slate-800 hover:bg-slate-700 text-cyan-400 rounded-md border border-slate-700"
                                    title="مکمل تفصیلات ایڈٹ کریں"
                                  >
                                    <Edit3 className="w-3.5 h-3.5" />
                                  </button>

                                  {/* Delete */}
                                  <button
                                    type="button"
                                    onClick={() => handleDeleteProduct(p.id, p.name)}
                                    className="p-1 bg-slate-800 hover:bg-rose-950 text-rose-400 rounded-md border border-slate-700"
                                    title="ڈیلیٹ کریں"
                                  >
                                    <Trash2 className="w-3.5 h-3.5" />
                                  </button>
                                </div>
                              )}
                            </td>
                          </tr>
                        );
                      })
                    )}
                  </tbody>
                </table>
              </div>
            </div>
          </div>
        )}

        {/* =========================================================================
            TAB 2: POINT OF SALE (POS & BILLING)
           ========================================================================= */}
        {activeTab === 'pos' && (
          <div className="grid grid-cols-1 lg:grid-cols-3 gap-6 animate-in fade-in duration-300">
            {/* Left: Product Catalog for POS */}
            <div className="lg:col-span-2 space-y-4">
              <div className="bg-slate-900 border border-slate-800 p-4 rounded-2xl flex items-center gap-3">
                <Search className="w-4 h-4 text-slate-400" />
                <input
                  type="text"
                  placeholder="بل بنانے کیلئے پروڈکٹ یا بارکوڈ اسکین کریں..."
                  value={searchQuery}
                  onChange={e => setSearchQuery(e.target.value)}
                  className="w-full bg-transparent text-xs text-white outline-none"
                />
              </div>

              <div className="grid grid-cols-1 sm:grid-cols-2 gap-3 max-h-[600px] overflow-y-auto pr-1">
                {products
                  .filter(p => p.name.toLowerCase().includes(searchQuery.toLowerCase()) || p.sku.includes(searchQuery))
                  .map(p => (
                    <div
                      key={p.id}
                      onClick={() => handleAddToCart(p)}
                      className={`p-4 rounded-2xl border transition-all cursor-pointer flex flex-col justify-between ${
                        p.stock > 0
                          ? 'bg-slate-900/90 border-slate-800 hover:border-cyan-500/50 hover:bg-slate-850'
                          : 'bg-slate-950/60 border-slate-900 opacity-50 cursor-not-allowed'
                      }`}
                    >
                      <div>
                        <div className="flex items-start justify-between gap-2">
                          <h4 className="font-bold text-white text-xs line-clamp-2">{p.name}</h4>
                          <span className="text-[10px] bg-slate-800 px-1.5 py-0.5 rounded font-mono text-slate-400">
                            {p.sku}
                          </span>
                        </div>
                        {p.imei_or_serial && (
                          <p className="text-[10px] text-amber-400 font-mono mt-1">IMEI: {p.imei_or_serial}</p>
                        )}
                      </div>

                      <div className="mt-3 pt-3 border-t border-slate-800 flex items-center justify-between">
                        <span className="text-sm font-black font-mono text-emerald-400">
                          Rs. {p.sale_price.toLocaleString()}
                        </span>
                        <span
                          className={`text-[10px] font-mono px-2 py-0.5 rounded-full font-bold ${
                            p.stock > 0 ? 'bg-cyan-500/10 text-cyan-300' : 'bg-rose-500/20 text-rose-300'
                          }`}
                        >
                          اسٹاک: {p.stock}
                        </span>
                      </div>
                    </div>
                  ))}
              </div>
            </div>

            {/* Right: Cart & Billing Invoice */}
            <div className="bg-slate-900 border border-slate-800 rounded-3xl p-5 flex flex-col justify-between shadow-2xl h-fit space-y-4">
              <div>
                <div className="flex items-center justify-between pb-3 border-b border-slate-800">
                  <div className="flex items-center gap-2">
                    <ShoppingCart className="w-5 h-5 text-emerald-400" />
                    <h3 className="font-black text-white text-sm">موجودہ بل (Cart)</h3>
                  </div>
                  {cart.length > 0 && (
                    <button
                      onClick={() => setCart([])}
                      className="text-xs text-rose-400 hover:underline"
                    >
                      خالی کریں
                    </button>
                  )}
                </div>

                {/* Customer Details */}
                <div className="grid grid-cols-2 gap-2 my-3">
                  <input
                    type="text"
                    placeholder="کسٹمر کا نام"
                    value={posCustomerName}
                    onChange={e => setPosCustomerName(e.target.value)}
                    className="bg-slate-950 border border-slate-800 px-3 py-1.5 rounded-xl text-xs text-slate-200 focus:border-cyan-500 outline-none"
                  />
                  <input
                    type="text"
                    placeholder="موبائل نمبر"
                    value={posCustomerPhone}
                    onChange={e => setPosCustomerPhone(e.target.value)}
                    className="bg-slate-950 border border-slate-800 px-3 py-1.5 rounded-xl text-xs text-slate-200 focus:border-cyan-500 outline-none"
                  />
                </div>

                {/* Cart Items List */}
                <div className="space-y-2 max-h-56 overflow-y-auto pr-1">
                  {cart.length === 0 ? (
                    <div className="text-center py-8 text-slate-500 text-xs">
                      کارٹ خالی ہے۔ بائیں جانب سے پروڈکٹ پر کلک کریں۔
                    </div>
                  ) : (
                    cart.map((item, idx) => (
                      <div
                        key={idx}
                        className="bg-slate-950 p-2.5 rounded-xl border border-slate-800/80 flex items-center justify-between text-xs"
                      >
                        <div className="flex-1 pr-1">
                          <p className="font-bold text-white line-clamp-1">{item.product.name}</p>
                          <p className="text-[10px] text-slate-400 font-mono">
                            Rs. {item.unit_price.toLocaleString()} × {item.quantity}
                          </p>
                        </div>
                        <div className="flex items-center gap-2">
                          <span className="font-bold font-mono text-emerald-400">
                            Rs. {(item.quantity * item.unit_price).toLocaleString()}
                          </span>
                          <button
                            onClick={() =>
                              setCart(prev => prev.filter((_, i) => i !== idx))
                            }
                            className="text-rose-400 hover:text-rose-300"
                          >
                            <Trash2 className="w-3.5 h-3.5" />
                          </button>
                        </div>
                      </div>
                    ))
                  )}
                </div>
              </div>

              {/* Bill Totals & Checkout */}
              <div className="pt-3 border-t border-slate-800 space-y-2.5">
                <div className="flex justify-between text-xs text-slate-400">
                  <span>سب ٹوٹل:</span>
                  <span className="font-mono text-white">Rs. {cartSubtotal.toLocaleString()}</span>
                </div>

                <div className="flex items-center justify-between text-xs text-slate-400">
                  <span>ڈسکاؤنٹ / رعایت:</span>
                  <input
                    type="number"
                    min="0"
                    value={posDiscount}
                    onChange={e => setPosDiscount(Number(e.target.value) || 0)}
                    className="w-24 bg-slate-950 border border-slate-800 text-right px-2 py-1 rounded text-xs font-mono text-rose-400 outline-none"
                  />
                </div>

                <div className="flex justify-between text-sm font-black text-white pt-2 border-t border-slate-800">
                  <span>کل رقم (Grand Total):</span>
                  <span className="font-mono text-emerald-400 text-base">
                    Rs. {cartGrandTotal.toLocaleString()}
                  </span>
                </div>

                {/* Payment Method */}
                <div className="grid grid-cols-3 gap-1.5 pt-1">
                  {(['CASH', 'EASYPAISA', 'CREDIT'] as const).map(m => (
                    <button
                      key={m}
                      type="button"
                      onClick={() => setPosPaymentMethod(m)}
                      className={`py-1.5 rounded-lg text-[10px] font-bold border ${
                        posPaymentMethod === m
                          ? 'bg-cyan-500/20 border-cyan-500 text-cyan-300'
                          : 'bg-slate-950 border-slate-800 text-slate-400'
                      }`}
                    >
                      {m === 'CASH' ? '💵 نقد (Cash)' : m === 'EASYPAISA' ? '📱 ایزی پیسہ' : '📒 ادھار (Khata)'}
                    </button>
                  ))}
                </div>

                <button
                  id="btn-complete-sale"
                  onClick={handleCheckout}
                  disabled={cart.length === 0}
                  className="w-full bg-gradient-to-r from-emerald-600 to-teal-600 hover:from-emerald-500 hover:to-teal-500 disabled:opacity-40 text-white py-3 rounded-xl font-bold text-xs flex items-center justify-center gap-2 shadow-lg shadow-emerald-500/20 active:scale-95 transition-all mt-2"
                >
                  <Printer className="w-4 h-4" />
                  <span>بل مکمل و رسید پرنٹ کریں</span>
                </button>
              </div>
            </div>
          </div>
        )}

        {/* =========================================================================
            TAB 3: MOBILE & IMEI TRACKER LEDGER
           ========================================================================= */}
        {activeTab === 'mobiles' && (
          <div className="space-y-4 animate-in fade-in duration-300">
            <div className="bg-slate-900 border border-slate-800 p-4 rounded-2xl flex items-center justify-between">
              <div>
                <h3 className="text-sm font-black text-white">موبائل ہینڈسیٹس و IMEI ریکارڈ</h3>
                <p className="text-xs text-slate-400">تمام برانڈز، ماڈلز، وارنٹی اور PTA کی تفصیلات</p>
              </div>
              <span className="text-xs text-purple-400 font-mono font-bold">
                {products.filter(p => p.category === 'MOBILES').length} موبائل ماڈلز رجسٹرڈ
              </span>
            </div>

            <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
              {products
                .filter(p => p.category === 'MOBILES')
                .map(p => (
                  <div key={p.id} className="bg-slate-900 border border-slate-800 p-5 rounded-3xl space-y-3">
                    <div className="flex items-start justify-between">
                      <span className="bg-purple-500/10 text-purple-400 border border-purple-500/30 text-[10px] font-bold px-2 py-0.5 rounded-full">
                        {p.condition || 'NEW'}
                      </span>
                      <span className="font-mono text-xs text-slate-400">{p.sku}</span>
                    </div>

                    <div>
                      <h4 className="font-bold text-white text-sm">{p.name}</h4>
                      <p className="text-xs text-slate-400 mt-1">برانڈ: {p.brand || '—'} | ماڈل: {p.model || '—'}</p>
                    </div>

                    <div className="bg-slate-950 p-3 rounded-2xl border border-slate-800 space-y-1 text-xs">
                      <div className="flex justify-between">
                        <span className="text-slate-500">IMEI / سیریل:</span>
                        <span className="font-mono font-bold text-amber-400">{p.imei_or_serial || '—'}</span>
                      </div>
                      <div className="flex justify-between">
                        <span className="text-slate-500">وارنٹی:</span>
                        <span className="text-cyan-400">{p.warranty || '—'}</span>
                      </div>
                      <div className="flex justify-between">
                        <span className="text-slate-500">موجودہ اسٹاک:</span>
                        <span className="font-bold text-white font-mono">{p.stock} دانہ</span>
                      </div>
                    </div>

                    <div className="pt-2 flex items-center justify-between">
                      <div>
                        <span className="text-[10px] text-slate-500 block">فروخت قیمت</span>
                        <span className="text-base font-black font-mono text-emerald-400">
                          Rs. {p.sale_price.toLocaleString()}
                        </span>
                      </div>
                      <button
                        onClick={() => {
                          setProductForm(p);
                          setShowProductModal(true);
                        }}
                        className="px-3 py-1.5 bg-slate-800 hover:bg-slate-700 text-cyan-400 rounded-xl text-xs font-bold border border-slate-700"
                      >
                        ایڈٹ تفصیل
                      </button>
                    </div>
                  </div>
                ))}
            </div>
          </div>
        )}

        {/* =========================================================================
            TAB 4: CUSTOMER KHATA & SALES HISTORY
           ========================================================================= */}
        {activeTab === 'khata' && (
          <div className="space-y-4 animate-in fade-in duration-300">
            <div className="bg-slate-900 border border-slate-800 p-4 rounded-2xl flex items-center justify-between">
              <div>
                <h3 className="text-sm font-black text-white">کسٹمر سیلز اور کھاتہ ہسٹری</h3>
                <p className="text-xs text-slate-400">تمام فروخت کی رسیدیں اور گاہکوں کے بقایا جات</p>
              </div>
              <span className="text-xs text-emerald-400 font-mono font-bold">{sales.length} انوائسز</span>
            </div>

            <div className="bg-slate-900 border border-slate-800 rounded-3xl overflow-hidden">
              <table className="w-full text-right text-xs">
                <thead className="bg-slate-950 text-slate-400 font-bold border-b border-slate-800">
                  <tr>
                    <th className="p-3.5">انوائس نمبر</th>
                    <th className="p-3.5">کسٹمر کا نام و فون</th>
                    <th className="p-3.5">تاریخ</th>
                    <th className="p-3.5 text-center">سامان کی تعداد</th>
                    <th className="p-3.5 text-left font-mono">کل بل</th>
                    <th className="p-3.5 text-center">طریقہ ادائیگی</th>
                    <th className="p-3.5 text-center">ایکشن</th>
                  </tr>
                </thead>
                <tbody className="divide-y divide-slate-800/60">
                  {sales.length === 0 ? (
                    <tr>
                      <td colSpan={7} className="p-8 text-center text-slate-500">
                        ابھی تک کوئی سیل ریکارڈ موجود نہیں۔ POS سے بل بنائیں۔
                      </td>
                    </tr>
                  ) : (
                    sales.map(s => (
                      <tr key={s.id} className="hover:bg-slate-800/40">
                        <td className="p-3.5 font-mono font-bold text-cyan-400">{s.invoice_no}</td>
                        <td className="p-3.5">
                          <span className="font-bold text-white block">{s.customer_name}</span>
                          <span className="text-[11px] text-slate-400 font-mono">{s.customer_phone}</span>
                        </td>
                        <td className="p-3.5 text-slate-400 text-[11px]">{s.date}</td>
                        <td className="p-3.5 text-center font-mono">{s.items.length} آئٹمز</td>
                        <td className="p-3.5 text-left font-mono font-bold text-emerald-400">
                          Rs. {s.grand_total.toLocaleString()}
                        </td>
                        <td className="p-3.5 text-center">
                          <span className="bg-slate-800 text-slate-300 px-2 py-0.5 rounded text-[10px] font-bold">
                            {s.payment_method}
                          </span>
                        </td>
                        <td className="p-3.5 text-center">
                          <button
                            onClick={() => setPosReceiptModal(s)}
                            className="p-1.5 bg-slate-800 hover:bg-slate-700 text-cyan-400 rounded-lg"
                            title="رسید دیکھیں"
                          >
                            <Printer className="w-3.5 h-3.5" />
                          </button>
                        </td>
                      </tr>
                    ))
                  )}
                </tbody>
              </table>
            </div>
          </div>
        )}

        {/* =========================================================================
            TAB 5: PURE PHP & MYSQL CODEBASE INTEGRATION
           ========================================================================= */}
        {activeTab === 'php_export' && (
          <div className="space-y-5 animate-in fade-in duration-300">
            <div className="bg-gradient-to-r from-blue-950/60 to-slate-900 border border-blue-800/40 p-6 rounded-3xl space-y-4">
              <div className="flex items-center gap-3">
                <div className="p-3 rounded-2xl bg-blue-500/20 text-blue-400 border border-blue-500/30">
                  <Database className="w-6 h-6" />
                </div>
                <div>
                  <h3 className="text-base font-black text-white">
                    پی ایچ پی و مائی ایس کیو ایل سوفٹ ویئر پیکج (Pure PHP / MySQL Architecture)
                  </h3>
                  <p className="text-xs text-slate-300">
                    یہ سافٹ ویئر مکمل طور پر بغیر کسی فریموک کے پریمیم پیور PHP 8 اور PDO / MySQL پر بھی تیار شدہ موجود ہے۔
                  </p>
                </div>
              </div>

              <div className="grid grid-cols-1 md:grid-cols-3 gap-3 pt-2">
                <div className="bg-slate-950/80 p-4 rounded-2xl border border-slate-800">
                  <h4 className="text-xs font-bold text-cyan-400 mb-1">XAMPP / Localhost</h4>
                  <p className="text-[11px] text-slate-400">
                    اپنے کمپیوٹر میں XAMPP میں <code className="text-amber-300 font-mono">htdocs/mobile_shop</code> میں فولڈر رکھ کر فورا چلائیں۔
                  </p>
                </div>

                <div className="bg-slate-950/80 p-4 rounded-2xl border border-slate-800">
                  <h4 className="text-xs font-bold text-emerald-400 mb-1">cPanel / Live Hosting</h4>
                  <p className="text-[11px] text-slate-400">
                    کسی بھی لائیو ہوسٹنگ پر <code className="text-amber-300 font-mono">public_html</code> میں اپلوڈ کر کے <code className="text-amber-300 font-mono">db.sql</code> امپورٹ کریں۔
                  </p>
                </div>

                <div className="bg-slate-950/80 p-4 rounded-2xl border border-slate-800">
                  <h4 className="text-xs font-bold text-purple-400 mb-1">گوگل ڈرائیو بیک اپ</h4>
                  <p className="text-[11px] text-slate-400">
                    خودکار بیک اپ سسٹم روزانہ کا ڈیٹا بیس اور پروڈکٹ انوینٹری کلاؤڈ پر محفوظ کرتا ہے۔
                  </p>
                </div>
              </div>
            </div>

            {/* Folder Structure */}
            <div className="bg-slate-900 border border-slate-800 p-5 rounded-3xl space-y-3">
              <h4 className="text-xs font-bold text-white flex items-center gap-2">
                <FileText className="w-4 h-4 text-cyan-400" />
                <span>ورک اسپیس میں موجود PHP ماڈیولز کی لسٹ (/php_software)</span>
              </h4>
              <div className="bg-slate-950 p-4 rounded-2xl border border-slate-800 font-mono text-xs text-slate-300 space-y-1 max-h-48 overflow-y-auto">
                <div className="text-emerald-400">📁 php_software/inventory/index.php (ان لائن کوئیک ایڈٹ سسٹم)</div>
                <div>📁 php_software/pos/index.php (پوائنٹ آف سیل و بلنگ)</div>
                <div>📁 php_software/backend/db.php (MySQL PDO محفوظ کنکشن)</div>
                <div>📁 php_software/mobile_ledger/index.php (IMEI و ماڈل رجسٹر)</div>
                <div>📁 php_software/customer_khata/index.php (گاہک ادھار کھاتہ)</div>
                <div>📁 php_software/barcode_studio/index.php (بارکوڈ جنریٹر و پرنٹ)</div>
                <div>📄 php_software/db.sql (مکمل ڈیٹا بیس اسکیمہ و ٹیبلز)</div>
              </div>
            </div>
          </div>
        )}

        {/* =========================================================================
            TAB 6: SETTINGS & SHOP PROFILE
           ========================================================================= */}
        {activeTab === 'settings' && (
          <div className="max-w-2xl mx-auto space-y-5 animate-in fade-in duration-300">
            <div className="bg-slate-900 border border-slate-800 p-6 rounded-3xl space-y-4">
              <h3 className="text-base font-black text-white">دکان کی تفصیلات و رسید کی ترتیبات</h3>

              <div className="space-y-3 text-xs">
                <div>
                  <label className="block text-slate-400 mb-1 font-bold">دکان کا نام</label>
                  <input
                    type="text"
                    value={shop.shop_name}
                    onChange={e => setShop({ ...shop, shop_name: e.target.value })}
                    className="w-full bg-slate-950 border border-slate-800 px-3 py-2 rounded-xl text-white outline-none focus:border-cyan-500"
                  />
                </div>

                <div className="grid grid-cols-2 gap-3">
                  <div>
                    <label className="block text-slate-400 mb-1 font-bold">مالک کا نام</label>
                    <input
                      type="text"
                      value={shop.owner_name}
                      onChange={e => setShop({ ...shop, owner_name: e.target.value })}
                      className="w-full bg-slate-950 border border-slate-800 px-3 py-2 rounded-xl text-white outline-none focus:border-cyan-500"
                    />
                  </div>
                  <div>
                    <label className="block text-slate-400 mb-1 font-bold">فون نمبر</label>
                    <input
                      type="text"
                      value={shop.phone}
                      onChange={e => setShop({ ...shop, phone: e.target.value })}
                      className="w-full bg-slate-950 border border-slate-800 px-3 py-2 rounded-xl text-white outline-none focus:border-cyan-500"
                    />
                  </div>
                </div>

                <div>
                  <label className="block text-slate-400 mb-1 font-bold">دکان کا پتہ</label>
                  <input
                    type="text"
                    value={shop.address}
                    onChange={e => setShop({ ...shop, address: e.target.value })}
                    className="w-full bg-slate-950 border border-slate-800 px-3 py-2 rounded-xl text-white outline-none focus:border-cyan-500"
                  />
                </div>

                <div>
                  <label className="block text-slate-400 mb-1 font-bold">رسید کا فوٹر پیغام</label>
                  <input
                    type="text"
                    value={shop.footer_message}
                    onChange={e => setShop({ ...shop, footer_message: e.target.value })}
                    className="w-full bg-slate-950 border border-slate-800 px-3 py-2 rounded-xl text-white outline-none focus:border-cyan-500"
                  />
                </div>
              </div>

              <div className="pt-3 border-t border-slate-800 flex justify-between">
                <button
                  onClick={() => {
                    setProducts(INITIAL_PRODUCTS);
                    showToast('ڈیمو ڈیٹا کامیابی سے ری سیٹ ہو گیا!', 'info');
                  }}
                  className="px-4 py-2 bg-slate-800 hover:bg-slate-700 text-slate-300 rounded-xl text-xs font-bold"
                >
                  ڈیمو ڈیٹا ری سیٹ کریں
                </button>
                <button
                  onClick={() => showToast('ترتیبات کامیابی سے محفوظ ہو گئیں!', 'success')}
                  className="px-5 py-2 bg-emerald-600 hover:bg-emerald-500 text-white rounded-xl text-xs font-bold shadow"
                >
                  ترتیبات محفوظ کریں
                </button>
              </div>
            </div>
          </div>
        )}
      </main>

      {/* =========================================================================
          MODAL: ADD / EDIT PRODUCT
         ========================================================================= */}
      {showProductModal && (
        <div className="fixed inset-0 z-50 bg-black/80 backdrop-blur-sm flex items-center justify-center p-4">
          <div className="bg-slate-900 border border-slate-800 w-full max-w-lg rounded-3xl overflow-hidden shadow-2xl flex flex-col max-h-[90vh]">
            <div className="p-4 border-b border-slate-800 flex items-center justify-between bg-slate-950">
              <h3 className="font-bold text-white text-sm">
                {productForm.id ? 'پروڈکٹ ایڈٹ کریں' : 'نئی پروڈکٹ شامل کریں'}
              </h3>
              <button
                onClick={() => setShowProductModal(false)}
                className="text-slate-400 hover:text-white"
              >
                <X className="w-5 h-5" />
              </button>
            </div>

            <form onSubmit={handleSaveProductForm} className="p-5 overflow-y-auto space-y-4 text-xs">
              <div>
                <label className="block text-slate-400 mb-1 font-bold">پروڈکٹ کا نام *</label>
                <input
                  type="text"
                  required
                  placeholder="مثال: Samsung Galaxy S23 Ultra"
                  value={productForm.name || ''}
                  onChange={e => setProductForm({ ...productForm, name: e.target.value })}
                  className="w-full bg-slate-950 border border-slate-800 px-3 py-2 rounded-xl text-white outline-none focus:border-cyan-500"
                />
              </div>

              <div className="grid grid-cols-2 gap-3">
                <div>
                  <label className="block text-slate-400 mb-1 font-bold">کیٹیگری</label>
                  <select
                    value={productForm.category || 'MOBILES'}
                    onChange={e =>
                      setProductForm({ ...productForm, category: e.target.value as any })
                    }
                    className="w-full bg-slate-950 border border-slate-800 px-3 py-2 rounded-xl text-white outline-none focus:border-cyan-500"
                  >
                    <option value="MOBILES">موبائلز (MOBILES)</option>
                    <option value="ACCESSORIES">اسیسریز (ACCESSORIES)</option>
                    <option value="PARTS">پارٹس (PARTS)</option>
                    <option value="SERVICES">سروسز (SERVICES)</option>
                  </select>
                </div>
                <div>
                  <label className="block text-slate-400 mb-1 font-bold">SKU / کوڈ</label>
                  <input
                    type="text"
                    placeholder="Auto or SKU-1001"
                    value={productForm.sku || ''}
                    onChange={e => setProductForm({ ...productForm, sku: e.target.value })}
                    className="w-full bg-slate-950 border border-slate-800 px-3 py-2 rounded-xl text-white font-mono outline-none focus:border-cyan-500"
                  />
                </div>
              </div>

              <div className="grid grid-cols-3 gap-3">
                <div>
                  <label className="block text-slate-400 mb-1 font-bold">قیمت خرید (Cost)</label>
                  <input
                    type="number"
                    step="any"
                    value={productForm.purchase_price || 0}
                    onChange={e =>
                      setProductForm({
                        ...productForm,
                        purchase_price: parseFloat(e.target.value) || 0
                      })
                    }
                    className="w-full bg-slate-950 border border-slate-800 px-3 py-2 rounded-xl text-white font-mono outline-none focus:border-cyan-500"
                  />
                </div>
                <div>
                  <label className="block text-slate-400 mb-1 font-bold">قیمت فروخت (Sale) *</label>
                  <input
                    type="number"
                    step="any"
                    required
                    value={productForm.sale_price || 0}
                    onChange={e =>
                      setProductForm({
                        ...productForm,
                        sale_price: parseFloat(e.target.value) || 0
                      })
                    }
                    className="w-full bg-slate-950 border border-emerald-500/60 px-3 py-2 rounded-xl text-emerald-400 font-mono font-bold outline-none focus:border-emerald-400"
                  />
                </div>
                <div>
                  <label className="block text-slate-400 mb-1 font-bold">اسٹاک تعداد</label>
                  <input
                    type="number"
                    min="0"
                    value={productForm.stock || 0}
                    onChange={e =>
                      setProductForm({ ...productForm, stock: parseInt(e.target.value) || 0 })
                    }
                    className="w-full bg-slate-950 border border-slate-800 px-3 py-2 rounded-xl text-white font-mono outline-none focus:border-cyan-500"
                  />
                </div>
              </div>

              <div className="grid grid-cols-2 gap-3">
                <div>
                  <label className="block text-slate-400 mb-1 font-bold">برانڈ</label>
                  <input
                    type="text"
                    placeholder="Samsung, Apple, Redmi"
                    value={productForm.brand || ''}
                    onChange={e => setProductForm({ ...productForm, brand: e.target.value })}
                    className="w-full bg-slate-950 border border-slate-800 px-3 py-2 rounded-xl text-white outline-none focus:border-cyan-500"
                  />
                </div>
                <div>
                  <label className="block text-slate-400 mb-1 font-bold">ماڈل / ویریئنٹ</label>
                  <input
                    type="text"
                    placeholder="S23 Ultra / 256GB"
                    value={productForm.model || ''}
                    onChange={e => setProductForm({ ...productForm, model: e.target.value })}
                    className="w-full bg-slate-950 border border-slate-800 px-3 py-2 rounded-xl text-white outline-none focus:border-cyan-500"
                  />
                </div>
              </div>

              <div>
                <label className="block text-slate-400 mb-1 font-bold">IMEI / سیریل نمبرز (کامے سے الگ کریں)</label>
                <input
                  type="text"
                  placeholder="358291048291039, 358291048291040"
                  value={productForm.imei_or_serial || ''}
                  onChange={e =>
                    setProductForm({ ...productForm, imei_or_serial: e.target.value })
                  }
                  className="w-full bg-slate-950 border border-slate-800 px-3 py-2 rounded-xl text-amber-400 font-mono outline-none focus:border-cyan-500"
                />
              </div>

              <div>
                <label className="block text-slate-400 mb-1 font-bold">وارنٹی کی تفصیل</label>
                <input
                  type="text"
                  placeholder="1 Year Brand Warranty / 10 Days Check Warranty"
                  value={productForm.warranty || ''}
                  onChange={e => setProductForm({ ...productForm, warranty: e.target.value })}
                  className="w-full bg-slate-950 border border-slate-800 px-3 py-2 rounded-xl text-white outline-none focus:border-cyan-500"
                />
              </div>

              <div className="pt-3 border-t border-slate-800 flex justify-end gap-2">
                <button
                  type="button"
                  onClick={() => setShowProductModal(false)}
                  className="px-4 py-2 bg-slate-800 text-slate-300 rounded-xl font-bold"
                >
                  منسوخ
                </button>
                <button
                  type="submit"
                  className="px-5 py-2 bg-cyan-600 hover:bg-cyan-500 text-white rounded-xl font-bold shadow"
                >
                  محفوظ کریں
                </button>
              </div>
            </form>
          </div>
        </div>
      )}

      {/* =========================================================================
          MODAL: THERMAL PRINT RECEIPT
         ========================================================================= */}
      {posReceiptModal && (
        <div className="fixed inset-0 z-50 bg-black/80 backdrop-blur-sm flex items-center justify-center p-4">
          <div className="bg-white text-slate-900 w-full max-w-sm rounded-3xl p-6 shadow-2xl space-y-4 font-mono">
            {/* Header */}
            <div className="text-center border-b pb-3 border-dashed border-slate-300">
              <h2 className="text-lg font-black">{shop.shop_name}</h2>
              <p className="text-xs text-slate-600 font-sans">{shop.address}</p>
              <p className="text-xs text-slate-600 font-sans">فون: {shop.phone}</p>
              <p className="text-[11px] text-slate-500 mt-1 font-mono">انوائس: {posReceiptModal.invoice_no}</p>
              <p className="text-[10px] text-slate-400 font-sans">{posReceiptModal.date}</p>
            </div>

            {/* Customer */}
            <div className="text-xs border-b pb-2 border-dashed border-slate-300 font-sans">
              <p>کسٹمر: <b>{posReceiptModal.customer_name}</b></p>
              <p>فون: {posReceiptModal.customer_phone}</p>
            </div>

            {/* Items */}
            <div className="space-y-1.5 text-xs">
              {posReceiptModal.items.map((it, idx) => (
                <div key={idx} className="flex justify-between border-b border-slate-100 pb-1">
                  <div>
                    <span className="font-bold">{it.name}</span>
                    <span className="block text-[10px] text-slate-500">
                      {it.quantity} × Rs. {it.unit_price.toLocaleString()}
                    </span>
                    {it.imei && <span className="text-[9px] text-slate-600 block">IMEI: {it.imei}</span>}
                  </div>
                  <span className="font-bold">Rs. {it.total.toLocaleString()}</span>
                </div>
              ))}
            </div>

            {/* Totals */}
            <div className="border-t border-dashed border-slate-400 pt-2 space-y-1 text-xs">
              <div className="flex justify-between">
                <span>سب ٹوٹل:</span>
                <span>Rs. {posReceiptModal.subtotal.toLocaleString()}</span>
              </div>
              {posReceiptModal.discount > 0 && (
                <div className="flex justify-between text-rose-600">
                  <span>رعایت:</span>
                  <span>- Rs. {posReceiptModal.discount.toLocaleString()}</span>
                </div>
              )}
              <div className="flex justify-between font-black text-sm border-t pt-1 border-slate-200">
                <span>کل ادا شدہ:</span>
                <span>Rs. {posReceiptModal.grand_total.toLocaleString()}</span>
              </div>
            </div>

            {/* Footer */}
            <div className="text-center text-[10px] text-slate-500 pt-2 border-t border-dashed border-slate-300 font-sans">
              <p>{shop.footer_message}</p>
            </div>

            {/* Print & Close */}
            <div className="flex gap-2 pt-2 no-print font-sans">
              <button
                onClick={() => setPosReceiptModal(null)}
                className="flex-1 py-2 bg-slate-200 hover:bg-slate-300 text-slate-800 rounded-xl text-xs font-bold"
              >
                بند کریں
              </button>
              <button
                onClick={() => window.print()}
                className="flex-1 py-2 bg-slate-900 hover:bg-black text-white rounded-xl text-xs font-bold flex items-center justify-center gap-1.5 shadow"
              >
                <Printer className="w-4 h-4" />
                <span>پرنٹ کریں</span>
              </button>
            </div>
          </div>
        </div>
      )}

      {/* =========================================================================
          TOAST NOTIFICATION FLOATING
         ========================================================================= */}
      {toast && (
        <div className="fixed bottom-5 left-5 z-50 flex items-center gap-2 bg-slate-900 border border-slate-700 text-white px-4 py-3 rounded-2xl shadow-2xl animate-in slide-in-from-bottom-5">
          <div
            className={`p-1.5 rounded-xl ${
              toast.type === 'success'
                ? 'bg-emerald-500/20 text-emerald-400'
                : toast.type === 'error'
                ? 'bg-rose-500/20 text-rose-400'
                : 'bg-cyan-500/20 text-cyan-400'
            }`}
          >
            {toast.type === 'success' ? (
              <Check className="w-4 h-4" />
            ) : toast.type === 'error' ? (
              <AlertCircle className="w-4 h-4" />
            ) : (
              <Zap className="w-4 h-4" />
            )}
          </div>
          <span className="text-xs font-bold">{toast.message}</span>
        </div>
      )}
    </div>
  );
}
