import React, { useState } from 'react';
import { 
  ShoppingCart, Search, Plus, Minus, Trash2, User, Phone, DollarSign, 
  Sparkles, CheckCircle2, ArrowRight, Smartphone, Receipt, ShieldCheck, List, LayoutGrid, Camera,
  Hash, Check
} from 'lucide-react';
import { Product, ProductSale, ProductSaleItem, ProductUnitItem, PaymentMethod, AppSettings } from '../types';
import { PAYMENT_CHANNELS } from '../lib/paymentChannels';
import { BarcodeScannerModal } from './BarcodeScannerModal';
import { UnitSelectorModal } from './UnitSelectorModal';
import { useHardwareBarcodeScanner } from '../lib/useHardwareBarcodeScanner';

interface PosViewProps {
  products: Product[];
  onCompleteSale: (sale: ProductSale, updatedProducts: Product[]) => void;
  settings: AppSettings;
}

interface CartItem extends ProductSaleItem {
  cartItemId: string; // unique ID in cart
  stockAvailable: number;
}

export const PosView: React.FC<PosViewProps> = ({
  products,
  onCompleteSale,
  settings,
}) => {
  const [searchTerm, setSearchTerm] = useState('');
  const [selectedCategory, setSelectedCategory] = useState<string>('ALL');
  const [viewMode, setViewMode] = useState<'list' | 'grid'>('list'); // Default to LIST VIEW

  // Cart state
  const [cart, setCart] = useState<CartItem[]>([]);
  const [customerName, setCustomerName] = useState('Walk-in Customer');
  const [customerPhone, setCustomerPhone] = useState('');
  const [discount, setDiscount] = useState<number | ''>(0);
  const [paymentMethod, setPaymentMethod] = useState<PaymentMethod>('CASH');
  const [isScannerOpen, setIsScannerOpen] = useState(false);

  // Unit Selector Modal state
  const [productForUnitSelection, setProductForUnitSelection] = useState<Product | null>(null);

  const isLight = settings.theme === 'light';

  // Listen for automatic scans from physical USB barcode scanner
  useHardwareBarcodeScanner((scannedCode) => {
    handleBarcodeScanSuccess(scannedCode);
  });

  const categories = [
    { key: 'ALL', label: 'All Items' },
    { key: 'MOBILES', label: 'Mobiles' },
    { key: 'CHARGERS', label: 'Chargers' },
    { key: 'EARPHONES', label: 'Earphones / Airpods' },
    { key: 'COVERS', label: 'Covers' },
    { key: 'PROTECTORS', label: 'Protectors' },
    { key: 'CABLES', label: 'Cables' },
    { key: 'BATTERIES', label: 'Batteries' },
  ];

  // Helper to get selected unit IDs already in cart
  const selectedUnitIdsInCart = cart
    .map((c) => c.selectedUnitId)
    .filter(Boolean) as string[];

  // Trigger add product logic (checks if unit selection is needed)
  const handleProductClick = (product: Product) => {
    if (product.stock <= 0) {
      alert('Yeh product out of stock hai!');
      return;
    }

    const hasUnits = (product.units && product.units.length > 0) || (product.category === 'MOBILES' && product.imeiOrSerial);

    if (hasUnits) {
      // Open Unit / IMEI Selector Modal
      setProductForUnitSelection(product);
    } else {
      // Standard accessory fast cart add
      addStandardProductToCart(product);
    }
  };

  // Add specific Unit (Phone with IMEI / Color) to cart
  const handleSelectUnit = (product: Product, unit: ProductUnitItem) => {
    const newItem: CartItem = {
      cartItemId: `cart-${product.id}-${unit.id || Date.now()}`,
      productId: product.id,
      productName: product.name,
      category: product.category,
      quantity: 1,
      purchasePrice: product.purchasePrice,
      unitSalePrice: product.salePrice,
      totalSalePrice: product.salePrice,
      image: product.image,
      stockAvailable: product.stock,

      selectedUnitId: unit.id,
      selectedImei1: unit.imei1,
      selectedImei2: unit.imei2,
      selectedColor: unit.color || product.color,
      selectedSerial: unit.serialNo || product.sku,
      selectedCondition: unit.condition || product.condition,
      selectedRamStorage: unit.storageRam || product.ramStorage,
    };

    setCart([...cart, newItem]);
  };

  // Standard non-unit accessory add to cart
  const addStandardProductToCart = (product: Product) => {
    const existingIdx = cart.findIndex((item) => item.productId === product.id && !item.selectedUnitId);

    if (existingIdx >= 0) {
      const currentQty = cart[existingIdx].quantity;
      if (currentQty >= product.stock) {
        alert(`Faqat ${product.stock} pcs stock mein dastayab hain!`);
        return;
      }

      const updated = [...cart];
      updated[existingIdx].quantity += 1;
      updated[existingIdx].totalSalePrice = updated[existingIdx].quantity * updated[existingIdx].unitSalePrice;
      setCart(updated);
    } else {
      const newItem: CartItem = {
        cartItemId: `cart-${product.id}-std`,
        productId: product.id,
        productName: product.name,
        category: product.category,
        quantity: 1,
        purchasePrice: product.purchasePrice,
        unitSalePrice: product.salePrice,
        totalSalePrice: product.salePrice,
        image: product.image,
        stockAvailable: product.stock,
      };
      setCart([...cart, newItem]);
    }
  };

  // Adjust cart item quantity (for standard items only)
  const updateQuantity = (cartItemId: string, delta: number) => {
    const updated = cart
      .map((item) => {
        if (item.cartItemId === cartItemId) {
          // If it's a specific IMEI unit, quantity is strictly 1 per IMEI
          if (item.selectedUnitId && delta > 0) {
            alert('Har IMEI aik alag unit hota hai. Mazeed unit shamil karne ke liye doosra IMEI select karein.');
            return item;
          }

          const newQty = item.quantity + delta;
          if (newQty > item.stockAvailable) {
            alert(`Faqat ${item.stockAvailable} pcs stock mein dastayab hain!`);
            return item;
          }
          if (newQty <= 0) return null;

          return {
            ...item,
            quantity: newQty,
            totalSalePrice: newQty * item.unitSalePrice,
          };
        }
        return item;
      })
      .filter(Boolean) as CartItem[];

    setCart(updated);
  };

  const removeFromCart = (cartItemId: string) => {
    setCart(cart.filter((item) => item.cartItemId !== cartItemId));
  };

  // Calculations
  const subtotal = cart.reduce((acc, item) => acc + item.totalSalePrice, 0);
  const totalCost = cart.reduce((acc, item) => acc + item.purchasePrice * item.quantity, 0);
  const discountVal = Number(discount) || 0;
  const netPayable = Math.max(0, subtotal - discountVal);
  const profitMargin = netPayable - totalCost;

  // Checkout
  const handleCheckout = (e: React.FormEvent) => {
    e.preventDefault();
    if (cart.length === 0) {
      alert('Cart khali hai! Koi item add karein.');
      return;
    }

    const invoiceNo = `INV-${Math.floor(1000 + Math.random() * 9000)}`;
    const now = new Date();
    const dateStr = now.toISOString().split('T')[0];

    const saleRecord: ProductSale = {
      id: `sale-${Date.now()}`,
      invoiceNo,
      date: dateStr,
      time: now.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' }),
      customerName: customerName.trim() || 'Walk-in Customer',
      customerPhone: customerPhone.trim(),
      items: cart.map(({ cartItemId, stockAvailable, image, ...rest }) => rest),
      totalAmount: subtotal,
      discount: discountVal,
      netAmount: netPayable,
      totalPurchaseCost: totalCost,
      profit: profitMargin,
      paymentMethod,
      createdAt: Date.now(),
    };

    // Deduct stock from products inventory & mark specific units as SOLD
    const updatedProducts = products.map((prod) => {
      const itemsInCartForThisProd = cart.filter((c) => c.productId === prod.id);
      if (itemsInCartForThisProd.length > 0) {
        const totalQtySold = itemsInCartForThisProd.reduce((sum, item) => sum + item.quantity, 0);
        const soldUnitIds = new Set(itemsInCartForThisProd.map(i => i.selectedUnitId).filter(Boolean));

        // Update units if present
        let updatedUnits = prod.units;
        if (prod.units && prod.units.length > 0) {
          updatedUnits = prod.units.map(unit => {
            if (soldUnitIds.has(unit.id)) {
              return {
                ...unit,
                status: 'SOLD' as const,
                soldInvoiceNo: invoiceNo,
                soldDate: dateStr
              };
            }
            return unit;
          });
        }

        return {
          ...prod,
          stock: Math.max(0, prod.stock - totalQtySold),
          units: updatedUnits,
        };
      }
      return prod;
    });

    onCompleteSale(saleRecord, updatedProducts);

    // Reset cart
    setCart([]);
    setCustomerName('Walk-in Customer');
    setCustomerPhone('');
    setDiscount(0);
  };

  // Filter products
  const availableProducts = products.filter((p) => {
    const term = searchTerm.toLowerCase();
    const matchesSearch =
      p.name.toLowerCase().includes(term) ||
      (p.brandOrModel && p.brandOrModel.toLowerCase().includes(term)) ||
      (p.sku && p.sku.toLowerCase().includes(term)) ||
      (p.imeiOrSerial && p.imeiOrSerial.toLowerCase().includes(term)) ||
      (p.units && p.units.some(u => 
        (u.imei1 && u.imei1.toLowerCase().includes(term)) ||
        (u.imei2 && u.imei2.toLowerCase().includes(term)) ||
        (u.color && u.color.toLowerCase().includes(term))
      ));
      
    const matchesCategory = selectedCategory === 'ALL' || p.category === selectedCategory;
    return matchesSearch && matchesCategory;
  });

  const handleBarcodeScanSuccess = (scannedCode: string) => {
    const code = scannedCode.trim().toLowerCase();
    if (!code) return;

    // 1. First check if scanned code matches a specific unit's IMEI
    for (const prod of products) {
      if (prod.units && prod.units.length > 0) {
        const matchedUnit = prod.units.find(
          (u) =>
            u.status === 'AVAILABLE' &&
            ((u.imei1 && u.imei1.trim().toLowerCase() === code) ||
             (u.imei2 && u.imei2.trim().toLowerCase() === code) ||
             (u.serialNo && u.serialNo.trim().toLowerCase() === code))
        );

        if (matchedUnit) {
          if (selectedUnitIdsInCart.includes(matchedUnit.id)) {
            alert(`Yeh IMEI "${scannedCode}" pehle se cart mein shamil hai!`);
          } else {
            handleSelectUnit(prod, matchedUnit);
          }
          return;
        }
      }
    }

    // 2. Check if scanned code matches product SKU or primary IMEI
    const foundProduct = products.find((p) => 
      (p.sku && p.sku.trim().toLowerCase() === code) ||
      (p.imeiOrSerial && p.imeiOrSerial.trim().toLowerCase() === code)
    );

    if (foundProduct) {
      handleProductClick(foundProduct);
    } else {
      alert(`Product with Barcode / IMEI "${scannedCode}" is not found in stock!`);
    }
  };

  return (
    <div className="grid grid-cols-1 lg:grid-cols-12 gap-5">
      
      {/* Left Column: Product Selection Grid (8 Cols on Desktop) */}
      <div className="lg:col-span-7 xl:col-span-8 space-y-4">
        
        {/* Search & Categories Bar */}
        <div className={`p-4 rounded-2xl border ${isLight ? 'bg-white border-slate-200' : 'bg-slate-900 border-slate-800'}`}>
          <div className="flex flex-col sm:flex-row gap-3 items-center justify-between">
            <div className="flex gap-2 flex-1 w-full">
              <div className="relative flex-1">
                <Search className="w-4 h-4 absolute left-3 top-3 text-slate-400" />
                <input
                  type="text"
                  placeholder="Farokht ke liye product, IMEI ya SKU search karein..."
                  value={searchTerm}
                  onChange={(e) => setSearchTerm(e.target.value)}
                  className={`w-full pl-9 pr-4 py-2 rounded-xl text-xs border focus:outline-none focus:ring-2 focus:ring-blue-500 ${
                    isLight ? 'bg-slate-50 border-slate-200 text-slate-800' : 'bg-slate-800 border-slate-700 text-slate-100'
                  }`}
                />
              </div>
              <button
                type="button"
                onClick={() => setIsScannerOpen(true)}
                className="px-3 bg-blue-600 hover:bg-blue-700 text-white rounded-xl text-xs font-bold flex items-center gap-1.5 transition-all cursor-pointer shadow-sm shrink-0"
                title="Scan Barcode / IMEI via Camera"
              >
                <Camera className="w-4 h-4" />
                <span className="hidden sm:inline">Scan Barcode / IMEI</span>
              </button>
            </div>

            {/* View Mode Switcher */}
            <div className={`flex items-center p-1 rounded-xl border shrink-0 ${isLight ? 'bg-slate-100 border-slate-200' : 'bg-slate-800 border-slate-700'}`}>
              <button
                onClick={() => setViewMode('list')}
                className={`flex items-center gap-1 px-2.5 py-1 rounded-lg text-xs font-bold transition-all cursor-pointer ${
                  viewMode === 'list'
                    ? 'bg-blue-700 text-white shadow-sm'
                    : 'text-slate-500 hover:text-slate-800'
                }`}
                title="List View"
              >
                <List className="w-3.5 h-3.5" />
                <span>List</span>
              </button>
              <button
                onClick={() => setViewMode('grid')}
                className={`flex items-center gap-1 px-2.5 py-1 rounded-lg text-xs font-bold transition-all cursor-pointer ${
                  viewMode === 'grid'
                    ? 'bg-blue-700 text-white shadow-sm'
                    : 'text-slate-500 hover:text-slate-800'
                }`}
                title="Grid View"
              >
                <LayoutGrid className="w-3.5 h-3.5" />
                <span>Grid</span>
              </button>
            </div>
          </div>

          <div className="flex gap-1.5 overflow-x-auto pt-3 pb-1 no-scrollbar">
            {categories.map((cat) => (
              <button
                key={cat.key}
                onClick={() => setSelectedCategory(cat.key)}
                className={`px-3 py-1.5 rounded-xl text-xs font-semibold shrink-0 transition-colors ${
                  selectedCategory === cat.key
                    ? 'bg-blue-700 text-white shadow-sm'
                    : isLight
                    ? 'bg-slate-100 hover:bg-slate-200 text-slate-700'
                    : 'bg-slate-800 hover:bg-slate-700 text-slate-300'
                }`}
              >
                {cat.label}
              </button>
            ))}
          </div>
        </div>

        {/* Catalog List / Grid */}
        <div className="max-h-[620px] overflow-y-auto pr-1">
          {availableProducts.length === 0 ? (
            <div className={`p-8 text-center rounded-2xl border ${isLight ? 'bg-white border-slate-200' : 'bg-slate-900 border-slate-800'}`}>
              <Smartphone className="w-8 h-8 mx-auto text-slate-400 mb-2 opacity-50" />
              <p className="text-xs font-semibold text-slate-500">Koi item nahi mila</p>
            </div>
          ) : viewMode === 'list' ? (
            /* POS PRODUCT LIST VIEW (DEFAULT) */
            <div className={`rounded-2xl border overflow-hidden shadow-sm ${isLight ? 'bg-white border-slate-200' : 'bg-slate-900 border-slate-800'}`}>
              <div className="divide-y divide-slate-100 dark:divide-slate-800">
                {availableProducts.map((p) => {
                  const cartCount = cart.filter((c) => c.productId === p.id).reduce((s, i) => s + i.quantity, 0);
                  const isOutOfStock = p.stock <= 0;
                  const hasUnits = (p.units && p.units.length > 0) || (p.category === 'MOBILES' && p.imeiOrSerial);

                  return (
                    <div
                      key={p.id}
                      onClick={() => !isOutOfStock && handleProductClick(p)}
                      className={`p-3 sm:p-3.5 flex items-center justify-between gap-3 transition-all cursor-pointer ${
                        isOutOfStock
                          ? 'opacity-50 cursor-not-allowed bg-slate-50 dark:bg-slate-900/50'
                          : isLight
                          ? 'hover:bg-blue-50/50'
                          : 'hover:bg-slate-800/60'
                      }`}
                    >
                      <div className="flex items-center gap-3 flex-1 min-w-0">
                        <img
                          src={p.image || 'https://images.unsplash.com/photo-1511707171634-5f897ff02aa9?auto=format&fit=crop&w=400&q=80'}
                          alt={p.name}
                          className="w-12 h-12 rounded-xl object-cover shrink-0 border border-slate-200 dark:border-slate-700"
                          referrerPolicy="no-referrer"
                        />
                        <div className="min-w-0 flex-1">
                          <div className="flex items-center gap-2">
                            <h4 className={`text-xs sm:text-sm font-extrabold truncate ${isLight ? 'text-slate-900' : 'text-white'}`}>
                              {p.name}
                            </h4>
                            {hasUnits && (
                              <span className="px-1.5 py-0.2 rounded bg-emerald-100 text-emerald-800 dark:bg-emerald-950/60 dark:text-emerald-300 font-bold text-[10px] shrink-0 flex items-center gap-0.5">
                                <Smartphone className="w-2.5 h-2.5" />
                                <span>IMEI Unit</span>
                              </span>
                            )}
                          </div>
                          
                          <div className="flex items-center gap-2 text-[11px] text-slate-500 mt-0.5 flex-wrap">
                            <span className="px-1.5 py-0.5 rounded bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-300 font-semibold text-[10px]">
                              {p.category}
                            </span>
                            {p.brandOrModel && <span>• {p.brandOrModel}</span>}
                            {p.color && <span>• {p.color}</span>}
                            {p.ramStorage && <span>• {p.ramStorage}</span>}
                          </div>
                        </div>
                      </div>

                      {/* Price & Stock & Action */}
                      <div className="text-right shrink-0 space-y-1">
                        <div className="font-mono font-extrabold text-blue-700 dark:text-blue-400 text-sm">
                          Rs. {p.salePrice.toLocaleString()}
                        </div>
                        <div className="flex items-center justify-end gap-1.5">
                          <span className={`text-[10px] font-bold px-1.5 py-0.2 rounded ${
                            isOutOfStock
                              ? 'bg-rose-100 text-rose-700'
                              : 'bg-emerald-100 text-emerald-700 dark:bg-emerald-950/50 dark:text-emerald-300'
                          }`}>
                            Stock: {p.stock}
                          </span>

                          {cartCount > 0 && (
                            <span className="text-[10px] font-bold px-1.5 py-0.2 rounded bg-blue-600 text-white">
                              Cart: {cartCount}
                            </span>
                          )}
                        </div>
                      </div>
                    </div>
                  );
                })}
              </div>
            </div>
          ) : (
            /* POS GRID VIEW */
            <div className="grid grid-cols-2 sm:grid-cols-3 xl:grid-cols-4 gap-3">
              {availableProducts.map((p) => {
                const cartCount = cart.filter((c) => c.productId === p.id).reduce((s, i) => s + i.quantity, 0);
                const isOutOfStock = p.stock <= 0;
                const hasUnits = (p.units && p.units.length > 0) || (p.category === 'MOBILES' && p.imeiOrSerial);

                return (
                  <div
                    key={p.id}
                    onClick={() => !isOutOfStock && handleProductClick(p)}
                    className={`rounded-2xl border p-3 flex flex-col justify-between transition-all cursor-pointer ${
                      isOutOfStock
                        ? 'opacity-50 cursor-not-allowed bg-slate-50 dark:bg-slate-900/50'
                        : isLight
                        ? 'bg-white border-slate-200 hover:border-blue-500 hover:shadow-md'
                        : 'bg-slate-900 border-slate-800 hover:border-blue-500'
                    }`}
                  >
                    <div className="space-y-2">
                      <div className="relative h-28 rounded-xl overflow-hidden bg-slate-100">
                        <img
                          src={p.image || 'https://images.unsplash.com/photo-1511707171634-5f897ff02aa9?auto=format&fit=crop&w=400&q=80'}
                          alt={p.name}
                          className="w-full h-full object-cover"
                          referrerPolicy="no-referrer"
                        />
                        <div className="absolute top-1.5 left-1.5">
                          <span className={`px-1.5 py-0.5 rounded text-[10px] font-bold ${
                            isOutOfStock ? 'bg-rose-600 text-white' : 'bg-emerald-600 text-white'
                          }`}>
                            {isOutOfStock ? 'Out' : `${p.stock} pcs`}
                          </span>
                        </div>
                        {hasUnits && (
                          <div className="absolute top-1.5 right-1.5">
                            <span className="px-1.5 py-0.5 rounded bg-blue-900/80 text-white text-[9px] font-bold">
                              IMEI
                            </span>
                          </div>
                        )}
                      </div>

                      <div>
                        <h4 className={`text-xs font-bold truncate ${isLight ? 'text-slate-900' : 'text-white'}`}>
                          {p.name}
                        </h4>
                        <p className="text-[10px] text-slate-400 truncate">{p.category} {p.color ? `• ${p.color}` : ''}</p>
                      </div>
                    </div>

                    <div className="pt-2 mt-2 border-t border-slate-100 dark:border-slate-800 flex items-center justify-between">
                      <span className="font-mono font-extrabold text-xs text-blue-700 dark:text-blue-400">
                        Rs. {p.salePrice.toLocaleString()}
                      </span>
                      {cartCount > 0 ? (
                        <span className="text-[10px] font-bold px-1.5 py-0.5 rounded-full bg-blue-600 text-white">
                          x{cartCount}
                        </span>
                      ) : (
                        <span className="p-1 rounded-lg bg-blue-50 text-blue-700 dark:bg-blue-950 dark:text-blue-300">
                          <Plus className="w-3.5 h-3.5" />
                        </span>
                      )}
                    </div>
                  </div>
                );
              })}
            </div>
          )}
        </div>
      </div>

      {/* Right Column: Checkout Cart & Bill Panel (4-5 Cols on Desktop) */}
      <div className="lg:col-span-5 xl:col-span-4 space-y-4">
        <div className={`p-4 sm:p-5 rounded-2xl border shadow-lg flex flex-col justify-between ${
          isLight ? 'bg-white border-slate-200' : 'bg-slate-900 border-slate-800'
        }`}>
          
          <div>
            {/* Cart Header */}
            <div className="flex items-center justify-between pb-3 border-b border-slate-200 dark:border-slate-800">
              <div className="flex items-center gap-2">
                <ShoppingCart className="w-5 h-5 text-blue-600" />
                <h3 className="font-black text-sm sm:text-base">
                  Sale Cart ({cart.reduce((s, i) => s + i.quantity, 0)} Items)
                </h3>
              </div>
              {cart.length > 0 && (
                <button
                  type="button"
                  onClick={() => setCart([])}
                  className="text-xs text-rose-500 hover:text-rose-700 font-semibold cursor-pointer"
                >
                  Clear All
                </button>
              )}
            </div>

            {/* Cart Items List */}
            <div className="my-3 space-y-2.5 max-h-64 overflow-y-auto pr-1">
              {cart.length === 0 ? (
                <div className="py-10 text-center text-slate-400">
                  <ShoppingCart className="w-8 h-8 mx-auto opacity-30 mb-1" />
                  <p className="text-xs font-semibold">Cart khali hai</p>
                  <p className="text-[10px]">سامان پر کلک کریں تاکہ وہ بل میں شامل ہو جائے</p>
                </div>
              ) : (
                cart.map((item) => (
                  <div
                    key={item.cartItemId}
                    className={`p-2.5 rounded-xl border text-xs space-y-1.5 ${
                      isLight ? 'bg-slate-50 border-slate-200' : 'bg-slate-800/60 border-slate-700'
                    }`}
                  >
                    <div className="flex items-center justify-between gap-2">
                      <div className="font-extrabold truncate text-slate-900 dark:text-white flex-1">
                        {item.productName}
                      </div>
                      <button
                        onClick={() => removeFromCart(item.cartItemId)}
                        className="text-slate-400 hover:text-rose-500 p-0.5 cursor-pointer"
                      >
                        <Trash2 className="w-3.5 h-3.5" />
                      </button>
                    </div>

                    {/* Selected Unit / IMEI Details badge */}
                    {(item.selectedImei1 || item.selectedColor) && (
                      <div className="bg-blue-50 dark:bg-blue-950/50 p-1.5 rounded-lg border border-blue-200 dark:border-blue-900/50 text-[10px] space-y-0.5 font-mono">
                        {item.selectedImei1 && (
                          <div className="flex items-center justify-between text-blue-800 dark:text-blue-300">
                            <span className="font-sans text-slate-500 font-semibold">IMEI 1:</span>
                            <span className="font-black">{item.selectedImei1}</span>
                          </div>
                        )}
                        {item.selectedImei2 && (
                          <div className="flex items-center justify-between text-slate-600 dark:text-slate-400">
                            <span className="font-sans text-slate-500">IMEI 2:</span>
                            <span>{item.selectedImei2}</span>
                          </div>
                        )}
                        {item.selectedColor && (
                          <div className="flex items-center justify-between text-purple-700 dark:text-purple-300 font-sans">
                            <span className="text-slate-500">Color:</span>
                            <span className="font-bold">{item.selectedColor}</span>
                          </div>
                        )}
                      </div>
                    )}

                    {/* Price & Quantity Controls */}
                    <div className="flex items-center justify-between pt-1">
                      <div className="flex items-center gap-1">
                        {!item.selectedUnitId ? (
                          <>
                            <button
                              onClick={() => updateQuantity(item.cartItemId, -1)}
                              className="w-6 h-6 rounded-lg bg-slate-200 dark:bg-slate-700 flex items-center justify-center cursor-pointer hover:bg-slate-300"
                            >
                              <Minus className="w-3 h-3" />
                            </button>
                            <span className="w-6 text-center font-bold text-xs">{item.quantity}</span>
                            <button
                              onClick={() => updateQuantity(item.cartItemId, 1)}
                              className="w-6 h-6 rounded-lg bg-blue-600 text-white flex items-center justify-center cursor-pointer hover:bg-blue-700"
                            >
                              <Plus className="w-3 h-3" />
                            </button>
                          </>
                        ) : (
                          <span className="px-2 py-0.5 rounded bg-emerald-100 text-emerald-800 dark:bg-emerald-950/60 dark:text-emerald-300 text-[10px] font-extrabold">
                            1 Unit Selected
                          </span>
                        )}
                      </div>

                      <div className="font-mono font-extrabold text-blue-700 dark:text-blue-400">
                        Rs. {item.totalSalePrice.toLocaleString()}
                      </div>
                    </div>
                  </div>
                ))
              )}
            </div>

            {/* Customer Details Form */}
            <div className="space-y-2 pt-2 border-t border-slate-200 dark:border-slate-800 text-xs">
              <div className="grid grid-cols-2 gap-2">
                <div>
                  <label className="block text-[11px] font-semibold text-slate-600 dark:text-slate-400 mb-0.5">
                    Customer Name:
                  </label>
                  <input
                    type="text"
                    placeholder="Customer Name"
                    value={customerName}
                    onChange={(e) => setCustomerName(e.target.value)}
                    className={`w-full p-2 rounded-xl border text-xs ${
                      isLight ? 'bg-slate-50 border-slate-200' : 'bg-slate-800 border-slate-700 text-white'
                    }`}
                  />
                </div>
                <div>
                  <label className="block text-[11px] font-semibold text-slate-600 dark:text-slate-400 mb-0.5">
                    Phone / WhatsApp:
                  </label>
                  <input
                    type="text"
                    placeholder="0300-1234567"
                    value={customerPhone}
                    onChange={(e) => setCustomerPhone(e.target.value)}
                    className={`w-full p-2 rounded-xl border text-xs font-mono ${
                      isLight ? 'bg-slate-50 border-slate-200' : 'bg-slate-800 border-slate-700 text-white'
                    }`}
                  />
                </div>
              </div>

              {/* Payment Channel Selector */}
              <div>
                <label className="block text-[11px] font-semibold text-slate-600 dark:text-slate-400 mb-1">
                  Payment Method:
                </label>
                <select
                  value={paymentMethod}
                  onChange={(e) => setPaymentMethod(e.target.value as PaymentMethod)}
                  className={`w-full p-2 rounded-xl border text-xs font-bold ${
                    isLight ? 'bg-slate-50 border-slate-200 text-slate-800' : 'bg-slate-800 border-slate-700 text-white'
                  }`}
                >
                  {PAYMENT_CHANNELS.map((ch) => (
                    <option key={ch.id} value={ch.id}>
                      {ch.emoji} {ch.nameEn} - {ch.nameUrdu}
                    </option>
                  ))}
                </select>
              </div>

              {/* Discount */}
              <div>
                <label className="block text-[11px] font-semibold text-slate-600 dark:text-slate-400 mb-0.5">
                  Discount (رعایت): Rs.
                </label>
                <input
                  type="number"
                  min="0"
                  placeholder="0"
                  value={discount}
                  onChange={(e) => setDiscount(e.target.value === '' ? '' : Number(e.target.value))}
                  className={`w-full p-2 rounded-xl border text-xs font-mono font-bold ${
                    isLight ? 'bg-slate-50 border-slate-200' : 'bg-slate-800 border-slate-700 text-white'
                  }`}
                />
              </div>
            </div>

            {/* Financial Summary */}
            <div className={`mt-3 p-3 rounded-xl border space-y-1.5 text-xs ${
              isLight ? 'bg-blue-50/70 border-blue-200' : 'bg-slate-800 border-slate-700'
            }`}>
              <div className="flex justify-between text-slate-600 dark:text-slate-300">
                <span>Subtotal:</span>
                <span className="font-mono font-bold">Rs. {subtotal.toLocaleString()}</span>
              </div>
              {discountVal > 0 && (
                <div className="flex justify-between text-rose-600 font-bold">
                  <span>Discount:</span>
                  <span className="font-mono">- Rs. {discountVal.toLocaleString()}</span>
                </div>
              )}
              <div className="flex justify-between text-sm font-black text-slate-900 dark:text-white pt-1 border-t border-blue-200 dark:border-slate-700">
                <span>Total Net Bill (کل بل):</span>
                <span className="font-mono text-base text-blue-700 dark:text-blue-400 font-black">
                  Rs. {netPayable.toLocaleString()}
                </span>
              </div>
              <div className="flex justify-between text-[11px] text-emerald-600 dark:text-emerald-400 font-semibold">
                <span>Net Profit on this bill:</span>
                <span className="font-mono font-bold">+ Rs. {profitMargin.toLocaleString()}</span>
              </div>
            </div>

          </div>

          {/* Checkout Button */}
          <button
            type="button"
            disabled={cart.length === 0}
            onClick={handleCheckout}
            className="w-full mt-4 py-3 rounded-xl bg-gradient-to-r from-emerald-600 to-teal-700 hover:from-emerald-700 hover:to-teal-800 text-white font-black text-sm shadow-lg shadow-emerald-600/30 transition-all flex items-center justify-center gap-2 cursor-pointer disabled:opacity-50 disabled:cursor-not-allowed"
          >
            <Receipt className="w-4 h-4" />
            <span>Complete Sale & Print Bill (بل پرنٹ کریں)</span>
          </button>

        </div>
      </div>

      {/* Unit Selector Modal for Multi-IMEI / Colors */}
      <UnitSelectorModal
        isOpen={Boolean(productForUnitSelection)}
        onClose={() => setProductForUnitSelection(null)}
        product={productForUnitSelection}
        selectedUnitIdsInCart={selectedUnitIdsInCart}
        onSelectUnit={handleSelectUnit}
        settings={settings}
      />

      {/* Barcode Camera Scanner */}
      <BarcodeScannerModal
        isOpen={isScannerOpen}
        onClose={() => setIsScannerOpen(false)}
        onScanSuccess={handleBarcodeScanSuccess}
      />

    </div>
  );
};
