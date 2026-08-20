import React, { useState } from 'react';
import { 
  ShoppingCart, Search, Plus, Minus, Trash2, User, Phone, DollarSign, 
  Sparkles, CheckCircle2, ArrowRight, Smartphone, Receipt, ShieldCheck, List, LayoutGrid, Camera
} from 'lucide-react';
import { Product, ProductSale, ProductSaleItem, PaymentMethod, AppSettings } from '../types';
import { PAYMENT_CHANNELS } from '../lib/paymentChannels';
import { BarcodeScannerModal } from './BarcodeScannerModal';
import { useHardwareBarcodeScanner } from '../lib/useHardwareBarcodeScanner';

interface PosViewProps {
  products: Product[];
  onCompleteSale: (sale: ProductSale, updatedProducts: Product[]) => void;
  settings: AppSettings;
}

interface CartItem extends ProductSaleItem {
  stockAvailable: number;
}

export const PosView: React.FC<PosViewProps> = ({
  products,
  onCompleteSale,
  settings,
}) => {
  const [searchTerm, setSearchTerm] = useState('');
  const [selectedCategory, setSelectedCategory] = useState<string>('ALL');
  const [viewMode, setViewMode] = useState<'list' | 'grid'>('list'); // Default to LIST VIEW as requested

  // Cart state
  const [cart, setCart] = useState<CartItem[]>([]);
  const [customerName, setCustomerName] = useState('Walk-in Customer');
  const [customerPhone, setCustomerPhone] = useState('');
  const [discount, setDiscount] = useState<number | ''>(0);
  const [paymentMethod, setPaymentMethod] = useState<PaymentMethod>('CASH');
  const [isScannerOpen, setIsScannerOpen] = useState(false);

  const isLight = settings.theme === 'light';

  // Listen for automatic scans from any attached physical USB barcode scanner
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

  // Add item to cart
  const addToCart = (product: Product) => {
    if (product.stock <= 0) {
      alert('Yeh product out of stock hai!');
      return;
    }

    const existingIdx = cart.findIndex((item) => item.productId === product.id);

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

  // Adjust cart item quantity
  const updateQuantity = (productId: string, delta: number) => {
    const updated = cart
      .map((item) => {
        if (item.productId === productId) {
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

  const removeFromCart = (productId: string) => {
    setCart(cart.filter((item) => item.productId !== productId));
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

    const saleRecord: ProductSale = {
      id: `sale-${Date.now()}`,
      invoiceNo,
      date: now.toISOString().split('T')[0],
      time: now.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' }),
      customerName: customerName.trim() || 'Walk-in Customer',
      customerPhone: customerPhone.trim(),
      items: cart.map(({ stockAvailable, ...rest }) => rest),
      totalAmount: subtotal,
      discount: discountVal,
      netAmount: netPayable,
      totalPurchaseCost: totalCost,
      profit: profitMargin,
      paymentMethod,
      createdAt: Date.now(),
    };

    // Deduct stock from products inventory
    const updatedProducts = products.map((prod) => {
      const cartMatch = cart.find((c) => c.productId === prod.id);
      if (cartMatch) {
        return {
          ...prod,
          stock: Math.max(0, prod.stock - cartMatch.quantity),
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
    const matchesSearch =
      p.name.toLowerCase().includes(searchTerm.toLowerCase()) ||
      (p.brandOrModel && p.brandOrModel.toLowerCase().includes(searchTerm.toLowerCase())) ||
      (p.sku && p.sku.toLowerCase().includes(searchTerm.toLowerCase())) ||
      (p.imeiOrSerial && p.imeiOrSerial.toLowerCase().includes(searchTerm.toLowerCase()));
    const matchesCategory = selectedCategory === 'ALL' || p.category === selectedCategory;

    return matchesSearch && matchesCategory;
  });

  const handleBarcodeScanSuccess = (scannedCode: string) => {
    const code = scannedCode.trim().toLowerCase();
    if (!code) return;

    const foundProduct = products.find((p) => 
      (p.sku && p.sku.trim().toLowerCase() === code) ||
      (p.imeiOrSerial && p.imeiOrSerial.trim().toLowerCase() === code)
    );

    if (foundProduct) {
      addToCart(foundProduct);
    } else {
      alert(`Product with Barcode / SKU "${scannedCode}" is not in stock!`);
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
                  placeholder="Product Farokht ke liye talash karein (Name or SKU)..."
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
                title="Scan Barcode / SKU via Camera"
              >
                <Camera className="w-4 h-4" />
                <span className="hidden sm:inline">Scan Barcode</span>
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
              <div className="divide-y divide-slate-100">
                {availableProducts.map((p) => {
                  const inCart = cart.find((c) => c.productId === p.id);
                  const isOutOfStock = p.stock <= 0;

                  return (
                    <div
                      key={p.id}
                      onClick={() => !isOutOfStock && addToCart(p)}
                      className={`p-3 flex items-center justify-between gap-3 cursor-pointer transition-colors ${
                        inCart
                          ? 'bg-blue-50/70 border-l-4 border-l-blue-600'
                          : isOutOfStock
                          ? 'opacity-50 bg-slate-50 cursor-not-allowed'
                          : isLight
                          ? 'hover:bg-slate-50'
                          : 'hover:bg-slate-800/60'
                      }`}
                    >
                      {/* Product Thumbnail & Details */}
                      <div className="flex items-center gap-3 min-w-0 flex-1">
                        <img
                          src={p.image}
                          alt={p.name}
                          className="w-11 h-11 rounded-xl object-cover shrink-0 border border-slate-200"
                          referrerPolicy="no-referrer"
                        />
                        <div className="min-w-0">
                          <div className="flex items-center gap-2">
                            <h4 className={`font-bold text-xs truncate ${isLight ? 'text-slate-900' : 'text-slate-100'}`}>
                              {p.name}
                            </h4>
                            <span className="px-2 py-0.5 rounded-full bg-slate-100 text-slate-700 text-[10px] font-bold shrink-0">
                              {p.category}
                            </span>
                          </div>
                          <div className="flex items-center gap-3 text-[11px] text-slate-500 mt-0.5">
                            {p.brandOrModel && <span>Model: {p.brandOrModel}</span>}
                            <span className={`font-semibold ${isOutOfStock ? 'text-rose-600' : 'text-slate-600'}`}>
                              Stock: {p.stock} pcs
                            </span>
                          </div>
                        </div>
                      </div>

                      {/* Price & Add to Cart Action */}
                      <div className="flex items-center gap-3 shrink-0">
                        <div className="text-right">
                          <p className="font-extrabold text-sm text-blue-700">Rs. {p.salePrice.toLocaleString()}</p>
                          {inCart && (
                            <span className="text-[10px] font-bold text-emerald-600">
                              {inCart.quantity} in cart
                            </span>
                          )}
                        </div>

                        <button
                          disabled={isOutOfStock}
                          className={`py-1.5 px-3 rounded-xl font-bold text-xs flex items-center gap-1 transition-all ${
                            isOutOfStock
                              ? 'bg-slate-200 text-slate-400 cursor-not-allowed'
                              : inCart
                              ? 'bg-emerald-600 text-white shadow-sm'
                              : 'bg-blue-600 hover:bg-blue-700 text-white shadow-sm'
                          }`}
                        >
                          <Plus className="w-3.5 h-3.5" />
                          <span>{inCart ? 'Add (+1)' : 'Add'}</span>
                        </button>
                      </div>

                    </div>
                  );
                })}
              </div>
            </div>
          ) : (
            /* POS GRID VIEW OPTION */
            <div className="grid grid-cols-2 sm:grid-cols-3 xl:grid-cols-4 gap-3">
              {availableProducts.map((p) => {
                const inCart = cart.find((c) => c.productId === p.id);
                const isOutOfStock = p.stock <= 0;

                return (
                  <div
                    key={p.id}
                    onClick={() => !isOutOfStock && addToCart(p)}
                    className={`rounded-xl border p-2.5 flex flex-col justify-between cursor-pointer transition-all duration-200 hover:shadow-md relative overflow-hidden group ${
                      inCart
                        ? 'ring-2 ring-blue-600 border-transparent bg-blue-50/50'
                        : isOutOfStock
                        ? 'opacity-50 cursor-not-allowed bg-slate-100'
                        : isLight
                        ? 'bg-white border-slate-200 hover:border-blue-300'
                        : 'bg-slate-900 border-slate-800 hover:border-blue-600'
                    }`}
                  >
                    {/* Image */}
                    <div className="h-28 rounded-lg bg-slate-100 overflow-hidden mb-2 relative">
                      <img
                        src={p.image}
                        alt={p.name}
                        className="w-full h-full object-cover group-hover:scale-105 transition-transform"
                        referrerPolicy="no-referrer"
                      />
                      {inCart && (
                        <div className="absolute top-1 right-1 w-6 h-6 rounded-full bg-blue-600 text-white font-bold text-xs flex items-center justify-center shadow-md">
                          {inCart.quantity}
                        </div>
                      )}
                      {isOutOfStock && (
                        <div className="absolute inset-0 bg-slate-950/60 flex items-center justify-center text-white text-[11px] font-bold">
                          Out of Stock
                        </div>
                      )}
                    </div>

                    {/* Details */}
                    <div>
                      <h4 className={`font-bold text-xs line-clamp-2 leading-tight ${isLight ? 'text-slate-900' : 'text-slate-100'}`}>
                        {p.name}
                      </h4>
                      <p className="text-[10px] text-slate-500 mt-0.5">Stock: {p.stock} pcs</p>
                    </div>

                    <div className="mt-2 pt-1.5 border-t border-slate-100 flex items-center justify-between">
                      <span className="font-extrabold text-xs text-blue-700">Rs. {p.salePrice.toLocaleString()}</span>
                      <button
                        disabled={isOutOfStock}
                        className="w-6 h-6 rounded-lg bg-blue-600 hover:bg-blue-700 text-white flex items-center justify-center text-xs font-bold"
                      >
                        +
                      </button>
                    </div>
                  </div>
                );
              })}
            </div>
          )}
        </div>

      </div>

      {/* Right Column: POS Counter & Bill Summary (4 Cols on Desktop) */}
      <div className="lg:col-span-5 xl:col-span-4">
        <div className={`p-4 sm:p-5 rounded-2xl border shadow-lg flex flex-col h-full ${
          isLight ? 'bg-white border-slate-200' : 'bg-slate-900 border-slate-800'
        }`}>
          
          {/* Header */}
          <div className="pb-3 border-b border-slate-200/80 flex items-center justify-between">
            <div className="flex items-center gap-2">
              <div className="w-8 h-8 rounded-lg bg-blue-600 text-white flex items-center justify-center font-bold">
                <ShoppingCart className="w-4 h-4" />
              </div>
              <div>
                <h3 className={`font-bold text-sm ${isLight ? 'text-slate-900' : 'text-white'}`}>
                  فروخت بل (POS Sale Cart)
                </h3>
                <p className="text-[11px] text-slate-500">{cart.length} items added</p>
              </div>
            </div>

            {cart.length > 0 && (
              <button
                onClick={() => setCart([])}
                className="text-[11px] text-rose-600 font-semibold hover:underline"
              >
                Clear Cart
              </button>
            )}
          </div>

          {/* Cart Items List */}
          <div className="flex-1 min-h-[220px] max-h-[280px] overflow-y-auto py-3 space-y-2.5">
            {cart.length === 0 ? (
              <div className="h-full flex flex-col items-center justify-center text-center p-6 text-slate-400">
                <ShoppingCart className="w-10 h-10 mb-2 opacity-30" />
                <p className="text-xs font-medium">Cart khali hai!</p>
                <p className="text-[11px] text-slate-400 mt-0.5">Saman add karne ke liye baayein taraf items par click karein.</p>
              </div>
            ) : (
              cart.map((item) => (
                <div
                  key={item.productId}
                  className={`p-2.5 rounded-xl border flex items-center justify-between gap-2 text-xs ${
                    isLight ? 'bg-slate-50 border-slate-200' : 'bg-slate-800/80 border-slate-700'
                  }`}
                >
                  <div className="flex items-center gap-2 flex-1 min-w-0">
                    {item.image && (
                      <img src={item.image} alt={item.productName} className="w-9 h-9 rounded-lg object-cover shrink-0" />
                    )}
                    <div className="min-w-0">
                      <p className={`font-semibold truncate ${isLight ? 'text-slate-800' : 'text-slate-100'}`}>
                        {item.productName}
                      </p>
                      <p className="text-[10px] text-slate-500">Rs. {item.unitSalePrice.toLocaleString()} / pc</p>
                    </div>
                  </div>

                  {/* Quantity Controller */}
                  <div className="flex items-center gap-1.5 shrink-0">
                    <button
                      onClick={() => updateQuantity(item.productId, -1)}
                      className="w-6 h-6 rounded-md bg-slate-200 hover:bg-slate-300 text-slate-800 font-bold flex items-center justify-center text-xs"
                    >
                      <Minus className="w-3 h-3" />
                    </button>
                    <span className="font-bold text-xs w-5 text-center">{item.quantity}</span>
                    <button
                      onClick={() => updateQuantity(item.productId, 1)}
                      className="w-6 h-6 rounded-md bg-blue-600 hover:bg-blue-700 text-white font-bold flex items-center justify-center text-xs"
                    >
                      <Plus className="w-3 h-3" />
                    </button>
                  </div>

                  <div className="text-right shrink-0 min-w-[60px]">
                    <p className="font-bold text-blue-700">Rs. {item.totalSalePrice.toLocaleString()}</p>
                    <button
                      onClick={() => removeFromCart(item.productId)}
                      className="text-[10px] text-rose-500 hover:underline"
                    >
                      Remove
                    </button>
                  </div>
                </div>
              ))
            )}
          </div>

          {/* Form Controls & Totals */}
          <form onSubmit={handleCheckout} className="pt-3 border-t border-slate-200/80 space-y-3">
            
            <div className="grid grid-cols-2 gap-2 text-xs">
              <div>
                <label className="block text-[11px] font-semibold text-slate-600 mb-0.5">Grahak (Name):</label>
                <input
                  type="text"
                  value={customerName}
                  onChange={(e) => setCustomerName(e.target.value)}
                  placeholder="Customer Name"
                  className={`w-full p-2 rounded-lg border text-xs ${
                    isLight ? 'bg-slate-50 border-slate-200' : 'bg-slate-800 border-slate-700 text-white'
                  }`}
                />
              </div>
              <div>
                <label className="block text-[11px] font-semibold text-slate-600 mb-0.5">Phone (Optional):</label>
                <input
                  type="text"
                  value={customerPhone}
                  onChange={(e) => setCustomerPhone(e.target.value)}
                  placeholder="0300-1234567"
                  className={`w-full p-2 rounded-lg border text-xs ${
                    isLight ? 'bg-slate-50 border-slate-200' : 'bg-slate-800 border-slate-700 text-white'
                  }`}
                />
              </div>
            </div>

            <div className="grid grid-cols-2 gap-2 text-xs">
              <div>
                <label className="block text-[11px] font-semibold text-slate-600 mb-0.5">Payment Method:</label>
                <select
                  value={paymentMethod}
                  onChange={(e) => setPaymentMethod(e.target.value as PaymentMethod)}
                  className={`w-full p-2 rounded-lg border text-xs font-semibold cursor-pointer ${
                    isLight ? 'bg-slate-50 border-slate-200 text-slate-900' : 'bg-slate-800 border-slate-700 text-white'
                  }`}
                >
                  <optgroup label="💵 Physical Cash">
                    {PAYMENT_CHANNELS.filter(c => c.category === 'CASH').map(c => (
                      <option key={c.id} value={c.id}>
                        {c.emoji} {c.nameEn}
                      </option>
                    ))}
                  </optgroup>
                  <optgroup label="📱 Digital Wallets">
                    {PAYMENT_CHANNELS.filter(c => c.category === 'WALLETS').map(c => (
                      <option key={c.id} value={c.id}>
                        {c.emoji} {c.nameEn}
                      </option>
                    ))}
                  </optgroup>
                  <optgroup label="🏛️ Online Banks">
                    {PAYMENT_CHANNELS.filter(c => c.category === 'BANKS').map(c => (
                      <option key={c.id} value={c.id}>
                        {c.emoji} {c.nameEn}
                      </option>
                    ))}
                  </optgroup>
                </select>
              </div>

              <div>
                <label className="block text-[11px] font-semibold text-slate-600 mb-0.5">Discount (ڈسکاؤنٹ):</label>
                <input
                  type="number"
                  min="0"
                  value={discount}
                  onChange={(e) => setDiscount(e.target.value === '' ? '' : Number(e.target.value))}
                  placeholder="0"
                  className={`w-full p-2 rounded-lg border text-xs font-bold ${
                    isLight ? 'bg-slate-50 border-slate-200' : 'bg-slate-800 border-slate-700 text-white'
                  }`}
                />
              </div>
            </div>

            {/* Bill Summary */}
            <div className="p-3 bg-blue-50/80 rounded-xl border border-blue-100 text-xs space-y-1.5">
              <div className="flex justify-between text-slate-600">
                <span>Subtotal:</span>
                <span className="font-semibold">Rs. {subtotal.toLocaleString()}</span>
              </div>
              {discountVal > 0 && (
                <div className="flex justify-between text-rose-600">
                  <span>Discount:</span>
                  <span className="font-semibold">- Rs. {discountVal.toLocaleString()}</span>
                </div>
              )}
              <div className="flex justify-between text-sm font-extrabold text-slate-900 pt-1 border-t border-blue-200">
                <span>Total Payable (کل رقم):</span>
                <span className="text-base text-blue-700">Rs. {netPayable.toLocaleString()}</span>
              </div>
              <div className="flex justify-between text-[11px] font-bold text-emerald-700 pt-1">
                <span>Expected Profit (منافع):</span>
                <span>+ Rs. {profitMargin.toLocaleString()}</span>
              </div>
            </div>

            {/* Checkout Button */}
            <button
              type="submit"
              disabled={cart.length === 0}
              className={`w-full py-3 px-4 rounded-xl font-bold text-xs shadow-lg flex items-center justify-center gap-2 transition-all ${
                cart.length === 0
                  ? 'bg-slate-200 text-slate-400 cursor-not-allowed shadow-none'
                  : 'bg-emerald-600 hover:bg-emerald-700 text-white shadow-emerald-600/30'
              }`}
            >
              <Receipt className="w-4 h-4" />
              <span>فروخت مکمل کریں و بل بنائیں (Complete Sale)</span>
            </button>

          </form>

        </div>
      </div>

      <BarcodeScannerModal
        isOpen={isScannerOpen}
        onClose={() => setIsScannerOpen(false)}
        onScanSuccess={handleBarcodeScanSuccess}
      />

    </div>
  );
};
