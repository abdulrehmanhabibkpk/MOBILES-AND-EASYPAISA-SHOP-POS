import React, { useState, useEffect } from 'react';
import { Transaction, DailyBalance, AppSettings, Product, ProductSale, MobilePurchaseRecord, Supplier, ProductUnitItem } from './types';
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
  batchSaveProductsToCloud,
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
import { ParsedStockItem } from './lib/stockDataHandler';
import { BulkStockAdjustmentModal } from './components/BulkStockAdjustmentModal';
import { DataRecoveryModal } from './components/DataRecoveryModal';
import { PhpServerModal } from './components/PhpServerModal';
import { 
  pullAllDataFromPhp, 
  saveProductToPhp, 
  deleteProductFromPhp, 
  saveSaleToPhp, 
  saveTransactionToPhp, 
  RemoteDataset 
} from './lib/phpApiClient';
import { backupEmergencySale, runCompleteDataRecovery } from './lib/dataRecovery';
import { getLocalDateString } from './lib/dateUtils';

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
import { StorageAutoBackupModal } from './components/StorageAutoBackupModal';
import { emergencyScanAndRecoverAll, vaultSaveEmergencySnapshot } from './lib/indexedDbVault';
import { flushPendingSyncQueue } from './lib/offlineSyncManager';
import { 
  initAutoBackupManager, 
  requestStoragePersistence, 
  scheduleAutoBackup, 
  CompleteShopBackup 
} from './lib/autoBackupManager';
import { 
  initGoogleDriveManager, 
  scheduleGoogleDriveAutoBackup 
} from './lib/googleDriveManager';
import { initPWAManager } from './lib/pwaManager';
import { OfflineIndicatorBanner } from './components/OfflineIndicatorBanner';
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
  const [isStorageModalOpen, setIsStorageModalOpen] = useState<boolean>(false);
  const [isRecoveryModalOpen, setIsRecoveryModalOpen] = useState<boolean>(false);
  const [isBulkStockModalOpen, setIsBulkStockModalOpen] = useState<boolean>(false);
  const [bulkStockTargetProduct, setBulkStockTargetProduct] = useState<Product | null>(null);
  const [isPhpModalOpen, setIsPhpModalOpen] = useState<boolean>(false);

  // Today's date string using timezone safe local date
  const todayStr = getLocalDateString();
  const [selectedDate, setSelectedDate] = useState<string>(todayStr);

  // Emergency Startup Scan & Merge: Check IndexedDB Vault + LocalStorage snapshots
  useEffect(() => {
    emergencyScanAndRecoverAll().then((rec) => {
      if (rec.recoveredSales.length > 0) {
        setProductSales((prev) => {
          const map = new Map<string, ProductSale>(prev.map(s => [s.id, s]));
          rec.recoveredSales.forEach(s => map.set(s.id, s));
          const list = (Array.from(map.values()) as ProductSale[]).sort((a, b) => (b.createdAt || 0) - (a.createdAt || 0));
          saveProductSales(list);
          return list;
        });
      }
      if (rec.recoveredTransactions.length > 0) {
        setTransactions((prev) => {
          const map = new Map<string, Transaction>(prev.map(t => [t.id, t]));
          rec.recoveredTransactions.forEach(t => map.set(t.id, t));
          const list = (Array.from(map.values()) as Transaction[]).sort((a, b) => (b.createdAt || 0) - (a.createdAt || 0));
          saveTransactions(list);
          return list;
        });
      }
      if (rec.recoveredProducts.length > 0) {
        setProducts((prev) => {
          const map = new Map<string, Product>(prev.map(p => [p.id, p]));
          rec.recoveredProducts.forEach(p => map.set(p.id, p));
          const list = Array.from(map.values()) as Product[];
          saveProducts(list);
          return list;
        });
      }
    }).catch(console.error);

    // Also attempt to flush any pending offline sync queue to Firestore
    flushPendingSyncQueue().catch(() => {});
  }, []);

  // Continuous background snapshot into IndexedDB Vault for disaster recovery
  useEffect(() => {
    vaultSaveEmergencySnapshot({
      transactions,
      products,
      productSales,
      mobilePurchases,
      suppliers,
      dailyBalances,
      settings,
    }).catch(() => {});
  }, [transactions, products, productSales, mobilePurchases, suppliers, dailyBalances, settings]);

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

  // Apply remote dataset from PHP / MySQL Server
  const handleApplyRemoteData = (data: RemoteDataset) => {
    if (Array.isArray(data.products) && data.products.length > 0) {
      setProducts(data.products);
      saveProducts(data.products);
    }
    if (Array.isArray(data.productSales) && data.productSales.length > 0) {
      setProductSales(data.productSales);
      saveProductSales(data.productSales);
    }
    if (Array.isArray(data.transactions) && data.transactions.length > 0) {
      setTransactions(data.transactions);
      saveTransactions(data.transactions);
    }
    if (Array.isArray(data.suppliers) && data.suppliers.length > 0) {
      setSuppliers(data.suppliers);
      saveSuppliers(data.suppliers);
    }
    if (Array.isArray(data.mobilePurchases) && data.mobilePurchases.length > 0) {
      setMobilePurchases(data.mobilePurchases);
      saveMobilePurchases(data.mobilePurchases);
    }
    if (data.dailyBalances && Object.keys(data.dailyBalances).length > 0) {
      setDailyBalances(data.dailyBalances);
      saveAllDailyBalances(data.dailyBalances);
    }
    if (data.settings && data.settings.shopName) {
      const merged = { ...settings, ...data.settings };
      setSettings(merged);
      saveSettings(merged);
    }
  };

  // Instant zero-wait startup: eliminate Firestore subscription quota blocks
  useEffect(() => {
    setLoading(false);

    // If PHP Backend is connected and autoSync is on, perform non-blocking sync
    if (settings.phpBackendUrl && settings.autoSyncPhp !== false) {
      pullAllDataFromPhp(settings.phpBackendUrl)
        .then((res) => {
          if (res.success && res.data) {
            handleApplyRemoteData(res.data);
          }
        })
        .catch((err) => {
          console.warn('PHP backend initial pull notice:', err);
        });
    }
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

  // Initialize Auto-Backup Manager, Google Drive Manager, PWA Offline Service Worker, and storage persistence
  useEffect(() => {
    initPWAManager();
    initGoogleDriveManager();
    initAutoBackupManager().then((st) => {
      // If user hasn't connected a folder yet, prompt once after a friendly short delay
      const hasPrompted = localStorage.getItem('balal_storage_prompted_v2');
      if (!hasPrompted && !st.hasFolderAccess) {
        const timer = setTimeout(() => {
          setIsStorageModalOpen(true);
          localStorage.setItem('balal_storage_prompted_v2', 'true');
        }, 1200);
        return () => clearTimeout(timer);
      }
    });
    requestStoragePersistence();
  }, []);

  // Continuous live background auto-save to local Downloads/Backup folder and Google Drive
  useEffect(() => {
    const backupData: CompleteShopBackup = {
      version: '1.0.0',
      appName: settings.shopName || 'Balal Mobiles & EasyPaisa Shop',
      exportDate: new Date().toISOString(),
      timestamp: Date.now(),
      transactions,
      dailyBalances: Object.values(dailyBalances),
      products,
      productSales,
      mobilePurchases,
      suppliers,
      settings,
    };
    // 1. Local folder auto-save
    scheduleAutoBackup(backupData, 2000);
    // 2. Google Drive background cloud auto-save
    scheduleGoogleDriveAutoBackup(backupData, 3500);
  }, [transactions, products, productSales, mobilePurchases, suppliers, dailyBalances, settings]);

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
    let savedTrx: Transaction;
    if (editingTrx) {
      const updatedTrx: Transaction = { ...trxData, id: editingTrx.id, createdAt: editingTrx.createdAt };
      const updated = transactions.map((t) =>
        t.id === editingTrx.id ? updatedTrx : t
      );
      setTransactions(updated);
      setEditingTrx(null);
      savedTrx = updatedTrx;
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
      savedTrx = newTrx;
      if (isAuthenticated) {
        saveTransactionToCloud(newTrx);
      }
    }
    if (settings.phpBackendUrl && settings.autoSyncPhp !== false) {
      saveTransactionToPhp(settings.phpBackendUrl, savedTrx).catch((e) => console.warn('PHP trx sync notice:', e));
    }
  };

  // Handle Product Sale Completion from POS Counter
  const handleCompleteProductSale = (sale: ProductSale, updatedProducts: Product[]) => {
    // 1. Immediately record in product sales state & dual-persist to localStorage and IndexedDB Vault
    setProductSales((prev) => {
      const exists = prev.some(s => s.id === sale.id);
      const updated = exists ? prev.map(s => s.id === sale.id ? sale : s) : [sale, ...prev];
      saveProductSales(updated);
      return updated;
    });

    // 2. Immediately update products state & dual-persist to localStorage and IndexedDB Vault
    setProducts(updatedProducts);
    saveProducts(updatedProducts);

    // 3. Immediately save to Cloud (Firestore + Offline Queue)
    const soldProductIds = new Set(sale.items.map(item => item.productId));
    const modifiedProducts = updatedProducts.filter(p => soldProductIds.has(p.id));
    modifiedProducts.forEach((p) => saveProductToCloud(p));
    saveProductSaleToCloud(sale);

    // 4. Also automatically record in Daily Cash Ledger / Transactions so Dashboard balance & profit update immediately!
    const isDigital = sale.paymentMethod === 'EASYPAISA' || sale.paymentMethod === 'JAZZCASH' || sale.paymentMethod === 'BANK';
    const saleTrx: Transaction = {
      id: `trx-pos-${sale.id}`,
      type: 'SELL_CASH',
      customerName: sale.customerName || 'Walk-in Customer (POS)',
      customerPhone: sale.customerPhone || '',
      easyPaisaAmount: isDigital ? sale.netAmount : 0,
      cashAmount: !isDigital ? sale.netAmount : 0,
      expenseAmount: 0,
      feeProfit: sale.profit || 0,
      paymentMethod: sale.paymentMethod || 'CASH',
      notes: `POS Bill #${sale.invoiceNo}: ${sale.items.map(i => `${i.quantity}x ${i.productName}`).join(', ')}`,
      date: sale.date,
      time: sale.time,
      createdAt: sale.createdAt || Date.now(),
    };

    setTransactions((prev) => {
      const exists = prev.some(t => t.id === saleTrx.id);
      const updated = exists ? prev.map(t => t.id === saleTrx.id ? saleTrx : t) : [saleTrx, ...prev];
      saveTransactions(updated);
      return updated;
    });
    saveTransactionToCloud(saleTrx);

    // 5. Open Print Bill Modal automatically!
    setActiveInvoiceSale(sale);

    // 6. Automatically sync to PHP / MySQL backend if configured
    if (settings.phpBackendUrl && settings.autoSyncPhp !== false) {
      saveSaleToPhp(settings.phpBackendUrl, sale).catch((e) => console.warn('PHP sale sync notice:', e));
    }
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
    let savedProduct: Product;
    if (id) {
      const existing = products.find((p) => p.id === id);
      const updatedProduct: Product = {
        ...productData,
        id,
        createdAt: existing ? existing.createdAt : Date.now(),
      };
      updatedProducts = products.map((p) => (p.id === id ? updatedProduct : p));
      savedProduct = updatedProduct;
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
      savedProduct = newProduct;
      if (isAuthenticated) {
        saveProductToCloud(newProduct);
      }
    }
    setProducts(updatedProducts);
    saveProducts(updatedProducts);
    if (settings.phpBackendUrl && settings.autoSyncPhp !== false) {
      saveProductToPhp(settings.phpBackendUrl, savedProduct).catch((e) => console.warn('PHP product sync notice:', e));
    }
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
      if (settings.phpBackendUrl && settings.autoSyncPhp !== false) {
        deleteProductFromPhp(settings.phpBackendUrl, id).catch((e) => console.warn('PHP product delete notice:', e));
      }
    }
  };

  // Bulk Import Products (Excel, CSV, DB)
  const handleImportProducts = (importedItems: ParsedStockItem[], mode: 'merge' | 'replace') => {
    let finalProducts: Product[] = [];
    
    if (mode === 'replace') {
      finalProducts = importedItems.map((item, idx) => ({
        id: item.id || `prod-${Date.now()}-${idx}`,
        name: item.name,
        category: item.category,
        purchasePrice: item.purchasePrice,
        salePrice: item.salePrice,
        stock: item.stock,
        image: item.image || 'https://images.unsplash.com/photo-1511707171634-5f897ff02aa9?auto=format&fit=crop&w=400&q=80',
        brandOrModel: item.brandOrModel || '',
        imeiOrSerial: item.imeiOrSerial || '',
        sku: item.sku || '',
        createdAt: Date.now() + idx,
      }));
    } else {
      const existingMap = new Map<string, Product>();
      products.forEach((p) => {
        existingMap.set(p.id, p);
        if (p.sku) existingMap.set(`sku:${p.sku.toLowerCase()}`, p);
        if (p.imeiOrSerial) existingMap.set(`imei:${p.imeiOrSerial.toLowerCase()}`, p);
        existingMap.set(`name:${p.name.toLowerCase()}`, p);
      });

      const updatedList = [...products];
      importedItems.forEach((item, idx) => {
        let match: Product | undefined;
        if (item.id && existingMap.has(item.id)) match = existingMap.get(item.id);
        else if (item.sku && existingMap.has(`sku:${item.sku.toLowerCase()}`)) match = existingMap.get(`sku:${item.sku.toLowerCase()}`);
        else if (item.imeiOrSerial && existingMap.has(`imei:${item.imeiOrSerial.toLowerCase()}`)) match = existingMap.get(`imei:${item.imeiOrSerial.toLowerCase()}`);
        else if (existingMap.has(`name:${item.name.toLowerCase()}`)) match = existingMap.get(`name:${item.name.toLowerCase()}`);

        if (match) {
          const updatedP: Product = {
            ...match,
            name: item.name,
            category: item.category,
            purchasePrice: item.purchasePrice || match.purchasePrice,
            salePrice: item.salePrice || match.salePrice,
            stock: item.stock,
            brandOrModel: item.brandOrModel || match.brandOrModel,
            imeiOrSerial: item.imeiOrSerial || match.imeiOrSerial,
            sku: item.sku || match.sku,
          };
          const pIndex = updatedList.findIndex(p => p.id === match!.id);
          if (pIndex !== -1) updatedList[pIndex] = updatedP;
        } else {
          const newP: Product = {
            id: item.id || `prod-${Date.now()}-${idx}`,
            name: item.name,
            category: item.category,
            purchasePrice: item.purchasePrice,
            salePrice: item.salePrice,
            stock: item.stock,
            image: item.image || 'https://images.unsplash.com/photo-1511707171634-5f897ff02aa9?auto=format&fit=crop&w=400&q=80',
            brandOrModel: item.brandOrModel || '',
            imeiOrSerial: item.imeiOrSerial || '',
            sku: item.sku || '',
            createdAt: Date.now() + idx,
          };
          updatedList.unshift(newP);
        }
      });
      finalProducts = updatedList;
    }

    setProducts(finalProducts);
    saveProducts(finalProducts);
    if (isAuthenticated) {
      batchSaveProductsToCloud(finalProducts);
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

  // Restore Entire Shop Backup (From Google Drive or Local JSON)
  const handleRestoreCompleteBackup = async (backup: CompleteShopBackup) => {
    if (backup.transactions && Array.isArray(backup.transactions)) {
      setTransactions(backup.transactions);
      saveTransactions(backup.transactions);
      if (isAuthenticated) {
        backup.transactions.forEach((t) => saveTransactionToCloud(t));
      }
    }
    if (backup.products && Array.isArray(backup.products)) {
      setProducts(backup.products);
      saveProducts(backup.products);
      if (isAuthenticated) {
        batchSaveProductsToCloud(backup.products);
      }
    }
    if (backup.productSales && Array.isArray(backup.productSales)) {
      setProductSales(backup.productSales);
      saveProductSales(backup.productSales);
      if (isAuthenticated) {
        backup.productSales.forEach((s) => saveProductSaleToCloud(s));
      }
    }
    if (backup.mobilePurchases && Array.isArray(backup.mobilePurchases)) {
      setMobilePurchases(backup.mobilePurchases);
      saveMobilePurchases(backup.mobilePurchases);
      if (isAuthenticated) {
        backup.mobilePurchases.forEach((p) => saveMobilePurchaseToCloud(p));
      }
    }
    if (backup.suppliers && Array.isArray(backup.suppliers)) {
      setSuppliers(backup.suppliers);
      saveSuppliers(backup.suppliers);
      if (isAuthenticated) {
        backup.suppliers.forEach((sup) => saveSupplierToCloud(sup));
      }
    }
    if (backup.dailyBalances && Array.isArray(backup.dailyBalances)) {
      const balMap: Record<string, DailyBalance> = {};
      backup.dailyBalances.forEach((b) => {
        balMap[b.date] = b;
      });
      setDailyBalances(balMap);
      saveAllDailyBalances(balMap);
      if (isAuthenticated) {
        backup.dailyBalances.forEach((b) => saveDailyBalanceToCloud(b));
      }
    }
    if (backup.settings) {
      setSettings(backup.settings);
      saveSettings(backup.settings);
      if (isAuthenticated) {
        saveAppSettingsToCloud(backup.settings);
      }
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

  // Bulk Stock Adjustment & Multi-IMEI Entry (e.g. 10 Nokia phones at once)
  const handleOpenBulkStock = (product?: Product) => {
    setBulkStockTargetProduct(product || null);
    setIsBulkStockModalOpen(true);
  };

  const handleSaveBulkStock = (
    targetProduct: { isExisting: boolean; productId?: string; newProductData?: Omit<Product, 'id' | 'createdAt'> },
    newUnits: ProductUnitItem[],
    addedQuantity: number
  ) => {
    if (targetProduct.isExisting && targetProduct.productId) {
      const existing = products.find((p) => p.id === targetProduct.productId);
      if (existing) {
        const existingUnits = existing.units || [];
        const mergedUnits = [...newUnits, ...existingUnits];
        const updatedProduct: Product = {
          ...existing,
          stock: existing.stock + addedQuantity,
          units: mergedUnits,
        };
        const updatedList = products.map((p) => (p.id === updatedProduct.id ? updatedProduct : p));
        setProducts(updatedList);
        saveProducts(updatedList);
        saveProductToCloud(updatedProduct);
      }
    } else if (targetProduct.newProductData) {
      const newProduct: Product = {
        ...targetProduct.newProductData,
        id: `prod-${Date.now()}`,
        createdAt: Date.now(),
        stock: addedQuantity,
        units: newUnits,
      };
      const updatedList = [newProduct, ...products];
      setProducts(updatedList);
      saveProducts(updatedList);
      saveProductToCloud(newProduct);
    }
    setIsBulkStockModalOpen(false);
    setBulkStockTargetProduct(null);
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

          {/* Top Offline/Update Banner */}
          <OfflineIndicatorBanner />

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
            onOpenAutoBackupModal={() => setIsStorageModalOpen(true)}
            onOpenDataRecoveryModal={() => setIsRecoveryModalOpen(true)}
            onOpenBulkStockModal={() => setIsBulkStockModalOpen(true)}
            onOpenPhpModal={() => setIsPhpModalOpen(true)}
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
                onOpenDataRecoveryModal={() => setIsRecoveryModalOpen(true)}
                onOpenBulkStockModal={() => handleOpenBulkStock()}
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
                onImportProducts={handleImportProducts}
                onSaveTransaction={handleSaveTransaction}
                onOpenBulkStockModal={handleOpenBulkStock}
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
                onOpenPhpModal={() => setIsPhpModalOpen(true)}
                shopData={{
                  version: '1.0.0',
                  appName: settings.shopName || 'Balal Mobiles & EasyPaisa Shop',
                  exportDate: new Date().toISOString(),
                  timestamp: Date.now(),
                  transactions,
                  dailyBalances: Object.values(dailyBalances),
                  products,
                  productSales,
                  mobilePurchases,
                  suppliers,
                  settings,
                }}
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

          <StorageAutoBackupModal
            isOpen={isStorageModalOpen}
            onClose={() => setIsStorageModalOpen(false)}
            settings={settings}
            onRestoreData={handleRestoreCompleteBackup}
            shopData={{
              version: '1.0.0',
              appName: settings.shopName || 'Balal Mobiles & EasyPaisa Shop',
              exportDate: new Date().toISOString(),
              timestamp: Date.now(),
              transactions,
              dailyBalances: Object.values(dailyBalances),
              products,
              productSales,
              mobilePurchases,
              suppliers,
              settings,
            }}
          />

          <DataRecoveryModal
            isOpen={isRecoveryModalOpen}
            onClose={() => setIsRecoveryModalOpen(false)}
            productSales={productSales}
            transactions={transactions}
            products={products}
            settings={settings}
            onDataRestored={(recovered) => {
              if (recovered.sales && recovered.sales.length > 0) {
                setProductSales((prev) => {
                  const map = new Map<string, ProductSale>(prev.map(s => [s.id, s]));
                  recovered.sales.forEach(s => map.set(s.id, s));
                  const merged = (Array.from(map.values()) as ProductSale[]).sort((a, b) => (b.createdAt || 0) - (a.createdAt || 0));
                  saveProductSales(merged);
                  merged.forEach(s => saveProductSaleToCloud(s));
                  return merged;
                });
              }
              if (recovered.transactions && recovered.transactions.length > 0) {
                setTransactions((prev) => {
                  const map = new Map<string, Transaction>(prev.map(t => [t.id, t]));
                  recovered.transactions.forEach(t => map.set(t.id, t));
                  const merged = (Array.from(map.values()) as Transaction[]).sort((a, b) => (b.createdAt || 0) - (a.createdAt || 0));
                  saveTransactions(merged);
                  merged.forEach(t => saveTransactionToCloud(t));
                  return merged;
                });
              }
              if (recovered.products && recovered.products.length > 0) {
                setProducts((prev) => {
                  const map = new Map<string, Product>(prev.map(p => [p.id, p]));
                  recovered.products.forEach(p => map.set(p.id, p));
                  const merged = Array.from(map.values()) as Product[];
                  saveProducts(merged);
                  batchSaveProductsToCloud(merged);
                  return merged;
                });
              }
            }}
          />

          <BulkStockAdjustmentModal
            isOpen={isBulkStockModalOpen}
            onClose={() => {
              setIsBulkStockModalOpen(false);
              setBulkStockTargetProduct(null);
            }}
            products={products}
            preSelectedProduct={bulkStockTargetProduct}
            onSaveBulkStock={handleSaveBulkStock}
            settings={settings}
          />

          <PhpServerModal
            isOpen={isPhpModalOpen}
            onClose={() => setIsPhpModalOpen(false)}
            settings={settings}
            onUpdateSettings={handleSaveSettings}
            products={products}
            productSales={productSales}
            transactions={transactions}
            suppliers={suppliers}
            mobilePurchases={mobilePurchases}
            dailyBalances={dailyBalances}
            onApplyRemoteData={handleApplyRemoteData}
          />
        </>
      )}

    </div>
  );
}
