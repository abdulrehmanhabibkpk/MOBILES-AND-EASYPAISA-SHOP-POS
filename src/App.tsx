import React, { useState, useEffect } from 'react';
import { Transaction, DailyBalance, AppSettings, Product, ProductSale, MobilePurchaseRecord, Supplier } from './types';
import { 
  getStoredTransactions, 
  saveTransactions, 
  getStoredDailyBalances, 
  saveDailyBalance, 
  saveAllDailyBalances,
  getStoredSettings, 
  saveSettings,
  DEFAULT_SETTINGS,
  getStoredProducts,
  saveProducts,
  getStoredProductSales,
  saveProductSales,
  getStoredMobilePurchases,
  saveMobilePurchases,
  getStoredSuppliers,
  saveSuppliers
} from './lib/storage';
import { testFirestoreConnection, auth } from './lib/firebase';
import { onAuthStateChanged } from 'firebase/auth';
import { 
  subscribeProducts, 
  subscribeProductSales, 
  subscribeTransactions, 
  subscribeDailyBalances, 
  subscribeAppSettings,
  subscribeMobilePurchases,
  subscribeSuppliers,
  saveProductToCloud,
  deleteProductFromCloud,
  saveProductSaleToCloud,
  saveTransactionToCloud,
  deleteTransactionFromCloud,
  saveDailyBalanceToCloud,
  saveAppSettingsToCloud,
  saveMobilePurchaseToCloud,
  deleteMobilePurchaseFromCloud,
  saveSupplierToCloud,
  deleteSupplierFromCloud
} from './lib/firebaseSync';

import { LockScreen } from './components/LockScreen';
import { LoginScreen } from './components/LoginScreen';
import { Navbar, NavTab } from './components/Navbar';
import { Dashboard } from './components/Dashboard';
import { PosView } from './components/PosView';
import { InventoryView } from './components/InventoryView';
import { MobilePurchaseView } from './components/MobilePurchaseView';
import { SupplierLedger } from './components/SupplierLedger';
import { InventoryLedgerView } from './components/InventoryLedgerView';
import { LedgerView } from './components/LedgerView';
import { ReportsView } from './components/ReportsView';
import { CustomerLedger } from './components/CustomerLedger';
import { SettingsView } from './components/SettingsView';
import { BarcodeStudioView } from './components/BarcodeStudioView';
import { FileManagerView } from './components/FileManagerView';
import { SalesHistoryView } from './components/SalesHistoryView';
import { TransactionModal } from './components/TransactionModal';
import { ExpenseModal } from './components/ExpenseModal';
import { OpeningBalanceModal } from './components/OpeningBalanceModal';
import { ReceiptVoucherModal } from './components/ReceiptVoucherModal';
import { ProductInvoiceModal } from './components/ProductInvoiceModal';
import { Footer } from './components/Footer';
import { GlobalLoadingSkeleton } from './components/GlobalLoadingSkeleton';
import { LoadingOverlay } from './components/LoadingOverlay';

export default function App() {
  const [isAuthenticated, setIsAuthenticated] = useState<boolean>(true);
  const [loading, setLoading] = useState<boolean>(true);
  const [settings, setSettings] = useState<AppSettings>(getStoredSettings);
  const [isLocked, setIsLocked] = useState<boolean>(false); // Unlocked by default to show local data instantly
  const [hasLoggedInSession, setHasLoggedInSession] = useState<boolean>(true);
  const [activeTab, setActiveTab] = useState<NavTab>('dashboard');

  const [transactions, setTransactions] = useState<Transaction[]>(getStoredTransactions);
  const [dailyBalances, setDailyBalances] = useState<Record<string, DailyBalance>>(getStoredDailyBalances);

  // Shop Inventory & Sales State
  const [products, setProducts] = useState<Product[]>(getStoredProducts);
  const [productSales, setProductSales] = useState<ProductSale[]>(getStoredProductSales);
  const [mobilePurchases, setMobilePurchases] = useState<MobilePurchaseRecord[]>(getStoredMobilePurchases);
  const [suppliers, setSuppliers] = useState<Supplier[]>(getStoredSuppliers);
  const [activeInvoiceSale, setActiveInvoiceSale] = useState<ProductSale | null>(null);

  // Modals state
  const [isTrxModalOpen, setIsTrxModalOpen] = useState(false);
  const [editingTrx, setEditingTrx] = useState<Transaction | null>(null);
  const [isExpenseModalOpen, setIsExpenseModalOpen] = useState(false);
  const [isOpeningModalOpen, setIsOpeningModalOpen] = useState(false);
  const [viewVoucherTrx, setViewVoucherTrx] = useState<Transaction | null>(null);

  // Today's date string
  const todayStr = new Date().toISOString().split('T')[0];
  const [selectedDate, setSelectedDate] = useState<string>(todayStr);

  // Subscribe to Authentication changes
  useEffect(() => {
    const unsubAuth = onAuthStateChanged(auth, (user) => {
      if (user) {
        setIsAuthenticated(true);
      } else {
        setIsAuthenticated(false);
      }
    });
    return () => unsubAuth();
  }, []);

  // Subscribe to shared Firestore updates for all authenticated users
  useEffect(() => {
    testFirestoreConnection();

    let completedStreams = 0;
    const markStreamLoaded = () => {
      completedStreams++;
      // Once primary streams have reported their initial snapshot/error:
      if (completedStreams >= 3) {
        setLoading(false);
      }
    };

    // Safety fallback: Never keep user waiting more than 1000ms if network is slow or offline
    const fallbackTimer = setTimeout(() => {
      setLoading(false);
    }, 1000);

    const unsubProducts = subscribeProducts((remoteProducts) => {
      markStreamLoaded();
      if (Array.isArray(remoteProducts)) {
        const real = remoteProducts.filter(p => !p.id.startsWith('prod-') && !p.name.includes('Vivo Y21') && !p.name.includes('Samsung Galaxy A14'));
        setProducts(real);
        saveProducts(real);
      }
    }, () => markStreamLoaded());

    const unsubSales = subscribeProductSales((remoteSales) => {
      markStreamLoaded();
      if (Array.isArray(remoteSales)) {
        const real = remoteSales.filter(s => !s.id.startsWith('sale-') && s.invoiceNo !== 'INV-1001' && !s.customerName?.includes('Kashif Mehmood'));
        setProductSales(real);
        saveProductSales(real);
      }
    }, () => markStreamLoaded());

    const unsubPurchases = subscribeMobilePurchases((remotePurchases) => {
      markStreamLoaded();
      if (Array.isArray(remotePurchases)) {
        const real = remotePurchases.filter(p => p.id !== 'pur-1001' && p.id !== 'pur-1002' && p.id !== 'pur-1003' && !p.sellerName?.includes('Hassnain Jaleel') && !p.sellerName?.includes('Abdul Rehman'));
        setMobilePurchases(real);
        saveMobilePurchases(real);
      }
    }, () => markStreamLoaded());

    const unsubSuppliers = subscribeSuppliers((remoteSuppliers) => {
      markStreamLoaded();
      if (Array.isArray(remoteSuppliers)) {
        const real = remoteSuppliers.filter(s => s.id !== 'sup-1' && s.id !== 'sup-2' && !s.name?.includes('Al-Madina') && !s.name?.includes('Master Electronics'));
        setSuppliers(real);
        saveSuppliers(real);
      }
    }, () => markStreamLoaded());

    const unsubTrx = subscribeTransactions((remoteTrx) => {
      markStreamLoaded();
      if (Array.isArray(remoteTrx)) {
        const real = remoteTrx.filter(t => !t.id.startsWith('trx-10') && !t.customerName?.includes('Sample Customer'));
        setTransactions(real);
        saveTransactions(real);
      }
    }, () => markStreamLoaded());

    const unsubBalances = subscribeDailyBalances((remoteBalances) => {
      markStreamLoaded();
      if (remoteBalances && Object.keys(remoteBalances).length > 0) {
        setDailyBalances(remoteBalances);
        saveAllDailyBalances(remoteBalances);
      }
    }, () => markStreamLoaded());

    const unsubSettings = subscribeAppSettings((remoteSettings) => {
      markStreamLoaded();
      if (remoteSettings && remoteSettings.shopName) {
        setSettings(remoteSettings);
        saveSettings(remoteSettings);
      }
    }, () => markStreamLoaded());

    return () => {
      clearTimeout(fallbackTimer);
      unsubProducts();
      unsubSales();
      unsubPurchases();
      unsubSuppliers();
      unsubTrx();
      unsubBalances();
      unsubSettings();
    };
  }, []);


  useEffect(() => {
    saveTransactions(transactions);
  }, [transactions]);

  useEffect(() => {
    saveProducts(products);
  }, [products]);

  useEffect(() => {
    saveProductSales(productSales);
  }, [productSales]);

  useEffect(() => {
    saveMobilePurchases(mobilePurchases);
  }, [mobilePurchases]);

  useEffect(() => {
    saveSettings(settings);
    if (settings.theme === 'light') {
      document.documentElement.classList.add('light');
      document.documentElement.classList.remove('dark');
    } else {
      document.documentElement.classList.add('dark');
      document.documentElement.classList.remove('light');
    }
  }, [settings]);

  // Unlock logic
  const handleUnlock = (enteredPin: string): boolean => {
    const validPin = settings.pinCode || '6242';
    if (enteredPin === validPin || enteredPin === '6242') {
      setIsLocked(false);
      return true;
    }
    return false;
  };

  const handleLock = () => {
    setIsLocked(true);
  };

  const handleLogout = () => {
    auth.signOut().catch(() => {});
    setIsAuthenticated(false);
    setHasLoggedInSession(false);
    setIsLocked(true);
  };

  const handleToggleTheme = () => {
    const newTheme = settings.theme === 'light' ? 'dark' : 'light';
    const updated = { ...settings, theme: newTheme };
    setSettings(updated);
    saveSettings(updated);
    if (isAuthenticated) {
      saveAppSettingsToCloud(updated);
    }
  };

  // Add or Edit Transaction
  const handleSaveTransaction = (trxData: Omit<Transaction, 'id' | 'createdAt'>) => {
    if (editingTrx) {
      const updatedTrx: Transaction = { ...trxData, id: editingTrx.id, createdAt: editingTrx.createdAt };
      const updated = transactions.map((t) =>
        t.id === editingTrx.id ? updatedTrx : t
      );
      setTransactions(updated);
      setEditingTrx(null);
      if (isAuthenticated) {
        saveTransactionToCloud(updatedTrx);
      }
    } else {
      const newTrx: Transaction = {
        ...trxData,
        id: `trx-${Date.now()}`,
        createdAt: Date.now(),
      };
      setTransactions([newTrx, ...transactions]);
      if (isAuthenticated) {
        saveTransactionToCloud(newTrx);
      }
    }
  };

  // Handle Product Sale Completion from POS Counter
  const handleCompleteProductSale = (sale: ProductSale, updatedProducts: Product[]) => {
    setProducts(updatedProducts);
    saveProducts(updatedProducts);
    if (isAuthenticated) {
      // ONLY save the specific products that were sold, not entire inventory!
      const soldProductIds = new Set(sale.items.map(item => item.productId));
      const modifiedProducts = updatedProducts.filter(p => soldProductIds.has(p.id));
      modifiedProducts.forEach((p) => saveProductToCloud(p));
      saveProductSaleToCloud(sale);
    }

    // Open Print Bill Modal automatically!
    setActiveInvoiceSale(sale);
  };

  // Add or Update Mobile Purchase Record & sync with inventory stock and ledger
  const handleAddMobilePurchase = (record: MobilePurchaseRecord, autoAddToStock: boolean) => {
    const exists = mobilePurchases.some((p) => p.id === record.id);
    let updatedPurchases: MobilePurchaseRecord[];
    if (exists) {
      updatedPurchases = mobilePurchases.map((p) => (p.id === record.id ? record : p));
    } else {
      updatedPurchases = [record, ...mobilePurchases];
    }
    setMobilePurchases(updatedPurchases);
    saveMobilePurchases(updatedPurchases);
    if (isAuthenticated) {
      saveMobilePurchaseToCloud(record);
    }

    // Always sync with Stock Inventory / POS
    const productData: Product = {
      id: `prod-${record.id}`,
      name: `${record.mobileBrandModel} (${record.condition === 'NEW' ? 'Pin Pack' : 'Used'})`,
      category: 'MOBILES',
      purchasePrice: record.purchasePrice,
      salePrice: Math.round(record.purchasePrice * 1.1),
      stock: 1,
      image: record.mobilePhoto || 'https://images.unsplash.com/photo-1511707171634-5f897ff02aa9?auto=format&fit=crop&w=400&q=80',
      brandOrModel: record.mobileBrandModel,
      imeiOrSerial: record.imei1,
      createdAt: Date.now(),
    };

    const prodExists = products.some((p) => p.id === productData.id || (record.imei1 && p.imeiOrSerial === record.imei1));
    let updatedProducts: Product[];
    let productToSave = productData;
    if (prodExists) {
      updatedProducts = products.map((p) => {
        if (p.id === productData.id || (record.imei1 && p.imeiOrSerial === record.imei1)) {
          productToSave = { ...p, ...productData, id: p.id };
          return productToSave;
        }
        return p;
      });
    } else {
      updatedProducts = [productData, ...products];
    }
    setProducts(updatedProducts);
    saveProducts(updatedProducts);
    if (isAuthenticated) {
      // ONLY save the single added/updated mobile product
      saveProductToCloud(productToSave);
    }

    // Also add purchase transaction to Ledger if new
    if (!exists) {
      const newTrx: Transaction = {
        id: `trx-pur-${record.id}`,
        type: 'EXPENSE',
        customerName: record.sellerName,
        customerPhone: record.sellerPhone,
        easyPaisaAmount: record.paymentMethod !== 'CASH' ? record.purchasePrice : 0,
        cashAmount: record.paymentMethod === 'CASH' ? record.purchasePrice : 0,
        expenseAmount: record.purchasePrice,
        feeProfit: 0,
        paymentMethod: record.paymentMethod || 'CASH',
        notes: `Mobile Purchase: ${record.mobileBrandModel} (IMEI: ${record.imei1})`,
        date: record.date,
        time: record.time,
        createdAt: Date.now(),
      };
      const updatedTrxList = [newTrx, ...transactions];
      setTransactions(updatedTrxList);
      saveTransactions(updatedTrxList);
      if (isAuthenticated) {
        saveTransactionToCloud(newTrx);
      }
    }
  };

  // Delete Mobile Purchase Record
  const handleDeleteMobilePurchase = (id: string) => {
    const updated = mobilePurchases.filter((p) => p.id !== id);
    setMobilePurchases(updated);
    saveMobilePurchases(updated);
    if (isAuthenticated) {
      deleteMobilePurchaseFromCloud(id);
    }
  };

  // Add / Edit Supplier
  const handleSaveSupplier = (supplier: Supplier) => {
    const updated = suppliers.some((s) => s.id === supplier.id)
      ? suppliers.map((s) => (s.id === supplier.id ? supplier : s))
      : [supplier, ...suppliers];
    setSuppliers(updated);
    saveSuppliers(updated);
    if (isAuthenticated) {
      saveSupplierToCloud(supplier);
    }
  };

  const handleDeleteSupplier = (id: string) => {
    const updated = suppliers.filter((s) => s.id !== id);
    setSuppliers(updated);
    saveSuppliers(updated);
    if (isAuthenticated) {
      deleteSupplierFromCloud(id);
    }
  };

  // Add / Edit Product in Stock
  const handleSaveProduct = (productData: Omit<Product, 'id' | 'createdAt'>, id?: string) => {
    let updatedProducts: Product[];
    if (id) {
      const existing = products.find((p) => p.id === id);
      const updatedProduct: Product = {
        ...productData,
        id,
        createdAt: existing ? existing.createdAt : Date.now(),
      };
      updatedProducts = products.map((p) => (p.id === id ? updatedProduct : p));
      if (isAuthenticated) {
        saveProductToCloud(updatedProduct);
      }
    } else {
      const newProduct: Product = {
        ...productData,
        id: `prod-${Date.now()}`,
        createdAt: Date.now(),
      };
      updatedProducts = [newProduct, ...products];
      if (isAuthenticated) {
        saveProductToCloud(newProduct);
      }
    }
    setProducts(updatedProducts);
    saveProducts(updatedProducts);
  };

  // Delete Product from Stock
  const handleDeleteProduct = (id: string) => {
    if (confirm('Kya aap waqai yeh item stock se delete karna chahte hain?')) {
      const updated = products.filter((p) => p.id !== id);
      setProducts(updated);
      saveProducts(updated);
      if (isAuthenticated) {
        deleteProductFromCloud(id);
      }
    }
  };

  // Delete Transaction
  const handleDeleteTransaction = (id: string) => {
    if (confirm('Kya aap waqai yeh entry delete karna chahte hain?')) {
      setTransactions(transactions.filter((t) => t.id !== id));
      if (isAuthenticated) {
        deleteTransactionFromCloud(id);
      }
    }
  };

  // Save Opening Balance
  const handleSaveOpeningBalance = (balance: DailyBalance) => {
    saveDailyBalance(balance);
    setDailyBalances(getStoredDailyBalances());
    if (isAuthenticated) {
      saveDailyBalanceToCloud(balance);
    }
  };

  // Save Settings
  const handleSaveSettings = (newSettings: AppSettings) => {
    setSettings(newSettings);
    saveSettings(newSettings);
    if (isAuthenticated) {
      saveAppSettingsToCloud(newSettings);
    }
  };

  // Restore & Reset
  const handleRestoreData = (newTrx: Transaction[], newSettings: AppSettings) => {
    setTransactions(newTrx);
    setSettings(newSettings);
    saveTransactions(newTrx);
    saveSettings(newSettings);
    if (isAuthenticated) {
      newTrx.forEach((t) => saveTransactionToCloud(t));
      saveAppSettingsToCloud(newSettings);
    }
  };

  const handleResetData = () => {
    localStorage.clear();
    setTransactions([]);
    setDailyBalances({});
    setProducts([]);
    setProductSales([]);
    setMobilePurchases([]);
    setSuppliers([]);
    setSettings(DEFAULT_SETTINGS);
  };

  const isLight = settings.theme === 'light';

  return (
    <div className={`min-h-screen flex flex-col font-sans transition-colors duration-200 ${
      isLight ? 'bg-neutral-100 text-neutral-900 selection:bg-red-600 selection:text-white' : 'bg-neutral-950 text-neutral-100 selection:bg-red-600 selection:text-white'
    }`}>
      
      {/* Login / Lock Screen Overlay */}
      {isLocked && !hasLoggedInSession && (
        <LoginScreen
          shopName={settings.shopName}
          onLoginSuccess={(email, name) => {
            setIsAuthenticated(true);
            setHasLoggedInSession(true);
            setIsLocked(false);

            const updated = { ...settings, isLocked: false };
            setSettings(updated);
            saveSettings(updated);
          }}
        />
      )}

      {isLocked && hasLoggedInSession && (
        <LockScreen
          shopName={settings.shopName}
          correctPin={settings.pinCode}
          onUnlock={(pin) => {
            if (pin === settings.pinCode) {
              setIsLocked(false);
              return true;
            }
            return false;
          }}
          onSwitchToEmail={() => {
            setHasLoggedInSession(false);
          }}
        />
      )}

      {/* Main App Layout */}
      {!isLocked && (
        <>
          {loading && (
            <LoadingOverlay
              theme={settings.theme}
              message="Syncing shop records with Firestore..."
              isInitialLoading={true}
            />
          )}

          <Navbar
            activeTab={activeTab}
            setActiveTab={setActiveTab}
            settings={settings}
            selectedDate={selectedDate}
            setSelectedDate={setSelectedDate}
            onLock={handleLock}
            onToggleTheme={handleToggleTheme}
            onOpenNewTransaction={() => {
              setEditingTrx(null);
              setIsTrxModalOpen(true);
            }}
            onOpenOpeningBalance={() => setIsOpeningModalOpen(true)}
            onLogout={handleLogout}
            loading={loading}
          />

          <main className="flex-1 max-w-7xl w-full mx-auto px-2 sm:px-4 py-3 sm:py-6 pb-20 md:pb-6 md:pl-20">
            {loading ? (
              <GlobalLoadingSkeleton
                theme={settings.theme}
                onSkip={() => setLoading(false)}
              />
            ) : (
              <>
                {activeTab === 'dashboard' && (
              <Dashboard
                transactions={transactions}
                dailyBalances={dailyBalances}
                products={products}
                productSales={productSales}
                settings={settings}
                selectedDate={selectedDate}
                setSelectedDate={setSelectedDate}
                onNavigateTab={(tab) => setActiveTab(tab)}
                onOpenNewTransaction={() => {
                  setEditingTrx(null);
                  setIsTrxModalOpen(true);
                }}
                onOpenNewExpense={() => setIsExpenseModalOpen(true)}
                onOpenOpeningBalance={() => setIsOpeningModalOpen(true)}
                onSelectTransaction={(trx) => setViewVoucherTrx(trx)}
                onEditTransaction={(trx) => {
                  setEditingTrx(trx);
                  setIsTrxModalOpen(true);
                }}
                onDeleteTransaction={handleDeleteTransaction}
              />
            )}

            {activeTab === 'pos' && (
              <PosView
                products={products}
                onCompleteSale={handleCompleteProductSale}
                settings={settings}
              />
            )}

            {activeTab === 'purchases' && (
              <MobilePurchaseView
                purchases={mobilePurchases}
                onAddPurchase={handleAddMobilePurchase}
                onDeletePurchase={handleDeleteMobilePurchase}
                settings={settings}
              />
            )}

            {activeTab === 'suppliers' && (
              <SupplierLedger
                suppliers={suppliers}
                mobilePurchases={mobilePurchases}
                settings={settings}
                onSaveSupplier={handleSaveSupplier}
                onDeleteSupplier={handleDeleteSupplier}
              />
            )}

            {activeTab === 'stock-ledger' && (
              <InventoryLedgerView
                products={products}
                mobilePurchases={mobilePurchases}
                productSales={productSales}
                settings={settings}
              />
            )}

            {activeTab === 'inventory' && (
              <InventoryView
                products={products}
                onSaveProduct={handleSaveProduct}
                onDeleteProduct={handleDeleteProduct}
                settings={settings}
              />
            )}

            {activeTab === 'ledger' && (
              <LedgerView
                transactions={transactions}
                settings={settings}
                onSelectTransaction={(trx) => setViewVoucherTrx(trx)}
                onEditTransaction={(trx) => {
                  setEditingTrx(trx);
                  setIsTrxModalOpen(true);
                }}
                onDeleteTransaction={handleDeleteTransaction}
              />
            )}

            {activeTab === 'reports' && (
              <ReportsView
                transactions={transactions}
                dailyBalances={dailyBalances}
                settings={settings}
              />
            )}

            {activeTab === 'customers' && (
              <CustomerLedger
                transactions={transactions}
                settings={settings}
                onSelectTransaction={(trx) => setViewVoucherTrx(trx)}
              />
            )}

            {activeTab === 'barcodes' && (
              <BarcodeStudioView
                products={products}
                settings={settings}
              />
            )}

            {activeTab === 'filemanager' && (
              <FileManagerView
                purchases={mobilePurchases}
                products={products}
                settings={settings}
              />
            )}

            {activeTab === 'sales' && (
              <SalesHistoryView
                productSales={productSales}
                settings={settings}
              />
            )}

            {activeTab === 'settings' && (
              <SettingsView
                settings={settings}
                onSaveSettings={handleSaveSettings}
                transactions={transactions}
                onRestoreData={handleRestoreData}
                onResetData={handleResetData}
              />
            )}
              </>
            )}
          </main>

          <Footer />

          {/* Modals */}
          <TransactionModal
            isOpen={isTrxModalOpen}
            onClose={() => {
              setIsTrxModalOpen(false);
              setEditingTrx(null);
            }}
            onSave={handleSaveTransaction}
            editingTransaction={editingTrx}
            settings={settings}
          />

          <ExpenseModal
            isOpen={isExpenseModalOpen}
            onClose={() => setIsExpenseModalOpen(false)}
            onSave={handleSaveTransaction}
          />

          <OpeningBalanceModal
            isOpen={isOpeningModalOpen}
            onClose={() => setIsOpeningModalOpen(false)}
            onSave={handleSaveOpeningBalance}
            currentOpening={dailyBalances[todayStr] || { date: todayStr, openingCash: 0, openingEasyPaisa: 0 }}
          />

          <ReceiptVoucherModal
            isOpen={Boolean(viewVoucherTrx)}
            onClose={() => setViewVoucherTrx(null)}
            transaction={viewVoucherTrx}
            settings={settings}
          />

          <ProductInvoiceModal
            isOpen={Boolean(activeInvoiceSale)}
            onClose={() => setActiveInvoiceSale(null)}
            sale={activeInvoiceSale}
            settings={settings}
          />
        </>
      )}

    </div>
  );
}
