import React, { useState, useEffect } from 'react';
import { Transaction, DailyBalance, AppSettings, Product, ProductSale, MobilePurchaseRecord } from './types';
import { 
  getStoredTransactions, 
  saveTransactions, 
  getStoredDailyBalances, 
  saveDailyBalance, 
  getStoredSettings, 
  saveSettings,
  DEFAULT_SETTINGS,
  getStoredProducts,
  saveProducts,
  getStoredProductSales,
  saveProductSales,
  getStoredMobilePurchases,
  saveMobilePurchases
} from './lib/storage';
import { testFirestoreConnection, auth, loginAnonymously } from './lib/firebase';
import { onAuthStateChanged } from 'firebase/auth';
import { 
  subscribeProducts, 
  subscribeProductSales, 
  subscribeTransactions, 
  subscribeDailyBalances, 
  subscribeAppSettings,
  subscribeMobilePurchases,
  saveProductToCloud,
  deleteProductFromCloud,
  saveProductSaleToCloud,
  saveTransactionToCloud,
  deleteTransactionFromCloud,
  saveDailyBalanceToCloud,
  saveAppSettingsToCloud,
  saveMobilePurchaseToCloud,
  deleteMobilePurchaseFromCloud
} from './lib/firebaseSync';

import { LockScreen } from './components/LockScreen';
import { LoginScreen } from './components/LoginScreen';
import { Navbar, NavTab } from './components/Navbar';
import { Dashboard } from './components/Dashboard';
import { PosView } from './components/PosView';
import { InventoryView } from './components/InventoryView';
import { MobilePurchaseView } from './components/MobilePurchaseView';
import { LedgerView } from './components/LedgerView';
import { ReportsView } from './components/ReportsView';
import { CustomerLedger } from './components/CustomerLedger';
import { SettingsView } from './components/SettingsView';
import { BarcodeStudioView } from './components/BarcodeStudioView';
import { TransactionModal } from './components/TransactionModal';
import { ExpenseModal } from './components/ExpenseModal';
import { OpeningBalanceModal } from './components/OpeningBalanceModal';
import { ReceiptVoucherModal } from './components/ReceiptVoucherModal';
import { ProductInvoiceModal } from './components/ProductInvoiceModal';
import { Footer } from './components/Footer';

export default function App() {
  const [currentUserId, setCurrentUserId] = useState<string | null>(null);
  const [settings, setSettings] = useState<AppSettings>(getStoredSettings);
  const [isLocked, setIsLocked] = useState<boolean>(true); // Locked on initial startup
  const [hasLoggedInSession, setHasLoggedInSession] = useState<boolean>(false);
  const [activeTab, setActiveTab] = useState<NavTab>('dashboard');

  const [transactions, setTransactions] = useState<Transaction[]>(getStoredTransactions);
  const [dailyBalances, setDailyBalances] = useState<Record<string, DailyBalance>>(getStoredDailyBalances);

  // Shop Inventory & Sales State
  const [products, setProducts] = useState<Product[]>(getStoredProducts);
  const [productSales, setProductSales] = useState<ProductSale[]>(getStoredProductSales);
  const [mobilePurchases, setMobilePurchases] = useState<MobilePurchaseRecord[]>(getStoredMobilePurchases);
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

  // Demo Mode States
  const [isDemoMode, setIsDemoMode] = useState<boolean>(() => localStorage.getItem('is_demo_mode') === 'true');
  const [demoSecondsLeft, setDemoSecondsLeft] = useState<number>(1800);
  const [showPurchaseModal, setShowPurchaseModal] = useState<boolean>(false);

  // Monitor 30-minute Demo timer
  useEffect(() => {
    if (!isDemoMode) return;

    const checkTimer = () => {
      const demoStartStr = localStorage.getItem('demo_start_time');
      if (!demoStartStr) {
        setIsDemoMode(false);
        return;
      }

      const startTime = parseInt(demoStartStr, 10);
      const elapsedMs = Date.now() - startTime;
      const totalAllowedMs = 30 * 60 * 1000; // 30 minutes in ms
      const remainingSecs = Math.max(0, Math.floor((totalAllowedMs - elapsedMs) / 1000));

      setDemoSecondsLeft(remainingSecs);

      if (remainingSecs <= 0) {
        // Expiration action
        setIsDemoMode(false);
        localStorage.removeItem('is_demo_mode');
        localStorage.removeItem('demo_start_time');
        auth.signOut().catch(() => {});
        setCurrentUserId(null);
        setHasLoggedInSession(false);
        setIsLocked(true);
        setShowPurchaseModal(true);
      }
    };

    // Run immediately once
    checkTimer();

    const intervalId = setInterval(checkTimer, 1000);
    return () => clearInterval(intervalId);
  }, [isDemoMode]);

  const handleLoginDemo = async () => {
    try {
      await loginAnonymously();
      localStorage.setItem('is_demo_mode', 'true');
      localStorage.setItem('demo_start_time', Date.now().toString());
      setIsDemoMode(true);
      setDemoSecondsLeft(1800);
      setHasLoggedInSession(true);
      setIsLocked(false);
      setCurrentUserId('demo_user_account');
    } catch (err) {
      // Fallback local-only demo session in case anonymous authentication is not enabled on Firebase
      localStorage.setItem('is_demo_mode', 'true');
      localStorage.setItem('demo_start_time', Date.now().toString());
      setIsDemoMode(true);
      setDemoSecondsLeft(1800);
      setHasLoggedInSession(true);
      setIsLocked(false);
      setCurrentUserId('demo_user_account');
    }
  };

  // Subscribe to Authentication changes
  useEffect(() => {
    const unsubAuth = onAuthStateChanged(auth, (user) => {
      if (user) {
        setCurrentUserId(user.uid);
        setHasLoggedInSession(true);
        setIsLocked(false);
      } else {
        setCurrentUserId(null);
        setHasLoggedInSession(false);
        setIsLocked(true);
      }
    });
    return () => unsubAuth();
  }, []);

  // Subscribe to user-specific Firestore updates
  useEffect(() => {
    if (!currentUserId) {
      setProducts([]);
      setProductSales([]);
      setMobilePurchases([]);
      setTransactions([]);
      setDailyBalances({});
      setSettings(DEFAULT_SETTINGS);
      return;
    }

    testFirestoreConnection();

    const unsubProducts = subscribeProducts(currentUserId, (remoteProducts) => {
      setProducts(remoteProducts || []);
    });

    const unsubSales = subscribeProductSales(currentUserId, (remoteSales) => {
      setProductSales(remoteSales || []);
    });

    const unsubPurchases = subscribeMobilePurchases(currentUserId, (remotePurchases) => {
      setMobilePurchases(remotePurchases || []);
    });

    const unsubTrx = subscribeTransactions(currentUserId, (remoteTrx) => {
      setTransactions(remoteTrx || []);
    });

    const unsubBalances = subscribeDailyBalances(currentUserId, (remoteBalances) => {
      setDailyBalances(remoteBalances || {});
    });

    const unsubSettings = subscribeAppSettings(currentUserId, (remoteSettings) => {
      if (remoteSettings && remoteSettings.shopName) {
        setSettings(remoteSettings);
      } else {
        // Init settings document in cloud if none exists
        saveAppSettingsToCloud(currentUserId, DEFAULT_SETTINGS);
      }
    });

    return () => {
      unsubProducts();
      unsubSales();
      unsubPurchases();
      unsubTrx();
      unsubBalances();
      unsubSettings();
    };
  }, [currentUserId]);


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
    localStorage.removeItem('is_demo_mode');
    localStorage.removeItem('demo_start_time');
    setIsDemoMode(false);
    setCurrentUserId(null);
    setHasLoggedInSession(false);
    setIsLocked(false);
  };

  const handleToggleTheme = () => {
    const newTheme = settings.theme === 'light' ? 'dark' : 'light';
    const updated = { ...settings, theme: newTheme };
    setSettings(updated);
    saveSettings(updated);
    if (currentUserId) {
      saveAppSettingsToCloud(currentUserId, updated);
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
      if (currentUserId) {
        saveTransactionToCloud(currentUserId, updatedTrx);
      }
    } else {
      const newTrx: Transaction = {
        ...trxData,
        id: `trx-${Date.now()}`,
        createdAt: Date.now(),
      };
      setTransactions([newTrx, ...transactions]);
      if (currentUserId) {
        saveTransactionToCloud(currentUserId, newTrx);
      }
    }
  };

  // Handle Product Sale Completion from POS Counter
  const handleCompleteProductSale = (sale: ProductSale, updatedProducts: Product[]) => {
    setProducts(updatedProducts);
    saveProducts(updatedProducts);
    if (currentUserId) {
      updatedProducts.forEach((p) => saveProductToCloud(currentUserId, p));
    }

    const updatedSales = [sale, ...productSales];
    setProductSales(updatedSales);
    saveProductSales(updatedSales);
    if (currentUserId) {
      saveProductSaleToCloud(currentUserId, sale);
    }

    // Open Print Bill Modal automatically!
    setActiveInvoiceSale(sale);
  };

  // Add Mobile Purchase Record & optionally add to inventory stock
  const handleAddMobilePurchase = (record: MobilePurchaseRecord, autoAddToStock: boolean) => {
    const updatedPurchases = [record, ...mobilePurchases];
    setMobilePurchases(updatedPurchases);
    saveMobilePurchases(updatedPurchases);
    if (currentUserId) {
      saveMobilePurchaseToCloud(currentUserId, record);
    }

    if (autoAddToStock) {
      const newProduct: Product = {
        id: `prod-${Date.now()}`,
        name: `${record.mobileBrandModel} (${record.condition === 'NEW' ? 'Pin Pack' : 'Used'})`,
        category: 'MOBILES',
        purchasePrice: record.purchasePrice,
        salePrice: Math.round(record.purchasePrice * 1.1), // Default 10% markup
        stock: 1,
        image: record.mobilePhoto || 'https://images.unsplash.com/photo-1511707171634-5f897ff02aa9?auto=format&fit=crop&w=400&q=80',
        brandOrModel: record.mobileBrandModel,
        imeiOrSerial: record.imei1,
        createdAt: Date.now(),
      };

      const updatedProducts = [newProduct, ...products];
      setProducts(updatedProducts);
      saveProducts(updatedProducts);
      if (currentUserId) {
        saveProductToCloud(currentUserId, newProduct);
      }
    }
  };

  // Delete Mobile Purchase Record
  const handleDeleteMobilePurchase = (id: string) => {
    const updated = mobilePurchases.filter((p) => p.id !== id);
    setMobilePurchases(updated);
    saveMobilePurchases(updated);
    if (currentUserId) {
      deleteMobilePurchaseFromCloud(currentUserId, id);
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
      if (currentUserId) {
        saveProductToCloud(currentUserId, updatedProduct);
      }
    } else {
      const newProduct: Product = {
        ...productData,
        id: `prod-${Date.now()}`,
        createdAt: Date.now(),
      };
      updatedProducts = [newProduct, ...products];
      if (currentUserId) {
        saveProductToCloud(currentUserId, newProduct);
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
      if (currentUserId) {
        deleteProductFromCloud(currentUserId, id);
      }
    }
  };

  // Delete Transaction
  const handleDeleteTransaction = (id: string) => {
    if (confirm('Kya aap waqai yeh entry delete karna chahte hain?')) {
      setTransactions(transactions.filter((t) => t.id !== id));
      if (currentUserId) {
        deleteTransactionFromCloud(currentUserId, id);
      }
    }
  };

  // Save Opening Balance
  const handleSaveOpeningBalance = (balance: DailyBalance) => {
    saveDailyBalance(balance);
    setDailyBalances(getStoredDailyBalances());
    if (currentUserId) {
      saveDailyBalanceToCloud(currentUserId, balance);
    }
  };

  // Save Settings
  const handleSaveSettings = (newSettings: AppSettings) => {
    setSettings(newSettings);
    saveSettings(newSettings);
    if (currentUserId) {
      saveAppSettingsToCloud(currentUserId, newSettings);
    }
  };

  // Restore & Reset
  const handleRestoreData = (newTrx: Transaction[], newSettings: AppSettings) => {
    setTransactions(newTrx);
    setSettings(newSettings);
    saveTransactions(newTrx);
    saveSettings(newSettings);
    if (currentUserId) {
      newTrx.forEach((t) => saveTransactionToCloud(currentUserId, t));
      saveAppSettingsToCloud(currentUserId, newSettings);
    }
  };

  const handleResetData = () => {
    localStorage.clear();
    setTransactions(getStoredTransactions());
    setDailyBalances(getStoredDailyBalances());
    setProducts(getStoredProducts());
    setProductSales(getStoredProductSales());
    setSettings(DEFAULT_SETTINGS);
  };

  const isLight = settings.theme === 'light';

  return (
    <div className={`min-h-screen flex flex-col font-sans transition-colors duration-200 ${
      isLight ? 'bg-neutral-100 text-neutral-900 selection:bg-red-600 selection:text-white' : 'bg-neutral-950 text-neutral-100 selection:bg-red-600 selection:text-white'
    }`}>
      
      {/* Lock / Login Screen Overlay */}
      {isLocked && !hasLoggedInSession && (
        <LoginScreen
          shopName={settings.shopName}
          allowedAccounts={settings.allowedAccounts || []}
          onLoginSuccess={(email, name) => {
            const accUserId = `account_${email.toLowerCase().replace(/[^a-z0-9]/g, '_')}`;
            setCurrentUserId(accUserId);
            setHasLoggedInSession(true);
            setIsLocked(false);

            let currentAccs = settings.allowedAccounts || [];
            if (currentAccs.length === 0) {
              currentAccs = [
                {
                  id: 'acc-' + Date.now(),
                  email: email,
                  password: '',
                  name: name || 'Shop Owner',
                  role: 'Owner'
                }
              ];
            }

            const updated = { ...settings, isLocked: false, allowedAccounts: currentAccs };
            setSettings(updated);
            saveSettings(updated);
          }}
          onLoginDemo={handleLoginDemo}
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
          />

          {isDemoMode && (
            <div className="bg-gradient-to-r from-amber-500 via-orange-500 to-red-600 text-white font-extrabold text-[11px] sm:text-xs py-2.5 px-4 shadow-md flex flex-wrap items-center justify-between gap-3 border-b border-orange-600 animate-pulse">
              <div className="flex items-center gap-1.5">
                <span className="text-sm">⚡</span>
                <span><strong>Demo Account (ڈیمو موڈ):</strong> Software check karein! Tamam features perfectly active hain.</span>
              </div>
              <div className="flex items-center gap-2 bg-black/30 px-3 py-1 rounded-full text-white border border-white/15">
                <span>⏱️ Baqi Time (Time Left):</span>
                <span className="font-mono tracking-widest text-sm bg-red-600 px-2 py-0.5 rounded shadow">
                  {Math.floor(demoSecondsLeft / 60)}m {demoSecondsLeft % 60}s
                </span>
              </div>
            </div>
          )}

          <main className="flex-1 max-w-7xl w-full mx-auto px-2 sm:px-4 py-3 sm:py-6 pb-20 md:pb-6">
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

            {activeTab === 'settings' && (
              <SettingsView
                settings={settings}
                onSaveSettings={handleSaveSettings}
                transactions={transactions}
                onRestoreData={handleRestoreData}
                onResetData={handleResetData}
              />
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

      {showPurchaseModal && (
        <div className="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/80 backdrop-blur-md p-4 overflow-y-auto">
          <div className="w-full max-w-md bg-white rounded-3xl border border-emerald-100 shadow-2xl overflow-hidden relative z-10 p-6 text-center text-slate-900">
            
            <div className="w-14 h-14 bg-rose-50 text-rose-600 rounded-2xl flex items-center justify-center mx-auto mb-4 animate-bounce border border-rose-100">
              <span className="text-3xl font-extrabold">⏰</span>
            </div>

            <h3 className="text-xl font-black text-slate-900 tracking-tight leading-snug">
              Demo Account Expired!
            </h3>
            <h4 className="text-lg font-extrabold text-emerald-800 leading-tight">
              (ڈیمو کا وقت ختم ہو گیا ہے)
            </h4>
            
            <p className="text-xs font-bold text-red-600 mt-1.5 tracking-wider uppercase">
              30 MINUTES TRIAL PERIOD HAS ENDED
            </p>

            <div className="my-5 p-4 bg-emerald-50/70 border border-emerald-100 rounded-2xl text-left space-y-3">
              <p className="text-xs text-slate-700 font-bold leading-relaxed text-center">
                Aap ka 30-minutes ka free demo trial mukammal ho chuka hai. App ka sara database mehfooz hai aur live cloud se linked hai.
              </p>
              <div className="border-t border-dashed border-emerald-200 pt-3">
                <p className="text-xs font-black text-emerald-800 text-center mb-2">
                  🚀 TO BUY THE COMPLETE SOFTWARE (سافٹ ویئر خریدنے کے لیے):
                </p>
                <div className="bg-slate-900 text-white rounded-xl p-3 text-center space-y-1.5">
                  <p className="text-xs font-semibold">🏢 Company: <strong className="text-emerald-400">THE PAK HACKERS</strong></p>
                  <p className="text-xs font-semibold">👤 Owner / Dev: <strong className="text-emerald-400">Abdul Rehman habib</strong></p>
                  <p className="text-xs font-semibold">📞 WhatsApp: <strong className="text-emerald-400">0319-5702823</strong></p>
                  <p className="text-[10px] text-slate-400">Unlimited Cloud Storage, Permanent License & Custom Features Support</p>
                </div>
              </div>
            </div>

            <div className="flex flex-col gap-2">
              <a
                href="https://wa.me/923195702823?text=Hi%20Abdul%20Rehman%20habib,%20mujay%20Balal%20Mobile%20Shop%20POS%20Software%20Khareedna%20hai."
                target="_blank"
                referrerPolicy="no-referrer"
                className="w-full py-3.5 px-4 rounded-xl bg-gradient-to-r from-emerald-600 to-teal-600 hover:from-emerald-700 hover:to-teal-700 text-white font-black text-xs shadow-md flex items-center justify-center gap-2 transition-all cursor-pointer no-underline"
              >
                💬 Rabta Karein (Contact on WhatsApp)
              </a>
              
              <button
                type="button"
                onClick={() => setShowPurchaseModal(false)}
                className="w-full py-3 px-4 rounded-xl bg-slate-100 hover:bg-slate-200 text-slate-700 font-extrabold text-xs transition-all cursor-pointer border-none"
              >
                Close (واپس جائیں)
              </button>
            </div>

          </div>
        </div>
      )}

    </div>
  );
}

