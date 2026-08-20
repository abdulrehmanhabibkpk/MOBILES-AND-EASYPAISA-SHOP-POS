import React, { useState } from 'react';
import { 
  ShieldCheck, 
  Lock, 
  LayoutDashboard, 
  BookOpen, 
  FileSpreadsheet, 
  Users, 
  Settings, 
  Sparkles,
  Sun,
  Moon,
  Menu,
  X,
  ShoppingCart,
  Package,
  Search,
  Smartphone,
  Plus,
  Calculator,
  LogOut,
  QrCode,
  Calendar,
  Wallet
} from 'lucide-react';
import { AppSettings } from '../types';
import { CashCalculatorModal } from './CashCalculatorModal';
import { t } from '../lib/i18n';

export type NavTab = 'dashboard' | 'pos' | 'inventory' | 'purchases' | 'ledger' | 'reports' | 'customers' | 'barcodes' | 'settings';

interface NavbarProps {
  activeTab: NavTab;
  setActiveTab: (tab: NavTab) => void;
  settings: AppSettings;
  selectedDate: string;
  setSelectedDate: (date: string) => void;
  onLock: () => void;
  onToggleTheme: () => void;
  onOpenNewTransaction: () => void;
  onOpenOpeningBalance: () => void;
  onLogout?: () => void;
}

export const Navbar: React.FC<NavbarProps> = ({
  activeTab,
  setActiveTab,
  settings,
  selectedDate,
  setSelectedDate,
  onLock,
  onToggleTheme,
  onOpenNewTransaction,
  onOpenOpeningBalance,
  onLogout,
}) => {
  const [isDrawerOpen, setIsDrawerOpen] = useState(false);
  const [drawerSearch, setDrawerSearch] = useState('');
  const [isCashCalcOpen, setIsCashCalcOpen] = useState(false);

  const isLight = settings.theme === 'light';

  const isEn = settings.language === 'en';

  const menuItems: { id: NavTab; titleUrdu: string; titleEnglish: string; icon: any }[] = [
    { id: 'dashboard', titleUrdu: 'ڈیش بورڈ', titleEnglish: 'Dashboard', icon: LayoutDashboard },
    { id: 'pos', titleUrdu: 'پوائنٹ آف سیل (POS)', titleEnglish: 'Sell Products / POS', icon: ShoppingCart },
    { id: 'purchases', titleUrdu: 'موبائل خرید رجسٹر (خرید ریکارڈ)', titleEnglish: 'Mobile Buy / Purchase Register', icon: Smartphone },
    { id: 'inventory', titleUrdu: 'موبائل و ایکسیسریز اسٹاک', titleEnglish: 'Stock Inventory', icon: Package },
    { id: 'barcodes', titleUrdu: 'برکوڈ لیبل جنریٹر اسٹوڈیو', titleEnglish: 'Barcode Studio & Printing', icon: QrCode },
    { id: 'ledger', titleUrdu: 'ایزی پیسہ کھاتہ', titleEnglish: 'EasyPaisa Ledger', icon: BookOpen },
    { id: 'customers', titleUrdu: 'گاہک کھاتہ', titleEnglish: 'Customer Khata', icon: Users },
    { id: 'reports', titleUrdu: 'رپورٹس و منافع', titleEnglish: 'Reports & Analytics', icon: FileSpreadsheet },
    { id: 'settings', titleUrdu: 'ترتیبات', titleEnglish: 'Settings & Security', icon: Settings },
  ];

  const filteredMenuItems = menuItems.filter(
    (item) =>
      item.titleUrdu.includes(drawerSearch) ||
      item.titleEnglish.toLowerCase().includes(drawerSearch.toLowerCase())
  );

  return (
    <header className={`${isLight ? 'bg-white border-neutral-200 text-neutral-900' : 'bg-black border-neutral-900 text-neutral-100'} border-b sticky top-0 z-40 shadow-md transition-colors duration-200`}>
      {/* Main Bar - Mobile Optimized without Overlaps */}
      <div className="max-w-7xl mx-auto px-2 sm:px-4 py-2 flex items-center justify-between gap-1.5 sm:gap-3">
        
        {/* Left: Drawer Toggle Button & Shop Logo */}
        <div className="flex items-center gap-1.5 sm:gap-2.5 min-w-0 shrink">
          <button
            onClick={() => setIsDrawerOpen(true)}
            className="w-8 h-8 sm:w-9 sm:h-9 rounded-xl bg-emerald-600 hover:bg-emerald-700 text-white shadow-sm flex items-center justify-center transition-all cursor-pointer shrink-0"
            title="Open Menu Drawer"
          >
            <Menu className="w-4 h-4 sm:w-5 sm:h-5" />
          </button>

          <div className="w-8 h-8 sm:w-9 sm:h-9 rounded-xl bg-emerald-600 text-white flex items-center justify-center font-black shadow-md shrink-0">
            <Smartphone className="w-4 h-4 sm:w-5 sm:h-5" />
          </div>

          <div className="min-w-0 overflow-hidden">
            <h1 className={`text-xs sm:text-base font-extrabold leading-tight truncate ${isLight ? 'text-neutral-900' : 'text-white'}`}>
              {settings.shopName || 'Mobiles and EasyPaisa Shop POS'}
            </h1>
            <p className="text-[9px] sm:text-xs text-emerald-600 font-semibold truncate hidden sm:block">
              {isEn ? 'MOBILES AND EASYPAISA SHOP POS' : 'موبائلز اینڈ ایزی پیسہ شاپ POS'}
            </p>
          </div>
        </div>

        {/* Quick Actions & Utilities - Clean Responsive Layout */}
        <div className="flex items-center gap-1 sm:gap-2 shrink-0">
          
          {/* Daily Cash Notes Calculator Button */}
          <button
            onClick={() => setIsCashCalcOpen(true)}
            className={`w-8 h-8 sm:w-9 sm:h-9 rounded-xl border flex items-center justify-center shadow-sm transition-all cursor-pointer shrink-0 ${
              isLight
                ? 'bg-emerald-50 hover:bg-emerald-100 text-emerald-700 border-emerald-200'
                : 'bg-neutral-800 hover:bg-neutral-700 text-emerald-400 border-emerald-900/40'
            }`}
            title="روزانہ نقد گنتی (Daily Cash Counter)"
          >
            <Calculator className="w-4 h-4" />
          </button>

          {/* Live Theme Toggle Button */}
          <button
            onClick={onToggleTheme}
            className={`w-8 h-8 sm:w-9 sm:h-9 rounded-xl border flex items-center justify-center transition-all cursor-pointer shadow-sm shrink-0 ${
              isLight 
                ? 'bg-neutral-100 hover:bg-neutral-200 text-neutral-800 border-neutral-300' 
                : 'bg-neutral-900 hover:bg-neutral-800 text-amber-300 border-neutral-800'
            }`}
            title="Switch Dark/Light Theme"
          >
            {isLight ? (
              <Moon className="w-4 h-4 text-neutral-700" />
            ) : (
              <Sun className="w-4 h-4 text-amber-300 fill-amber-300" />
            )}
          </button>

          {/* Lock Button */}
          <button
            onClick={onLock}
            className={`w-8 h-8 sm:w-9 sm:h-9 rounded-xl border flex items-center justify-center transition-all cursor-pointer shrink-0 ${
              isLight
                ? 'bg-neutral-100 hover:bg-neutral-200 text-neutral-700 border-neutral-300'
                : 'bg-neutral-900 hover:bg-neutral-800 text-red-400 border-neutral-800'
            }`}
            title="Lock Software"
          >
            <Lock className="w-4 h-4" />
          </button>

          {/* POS Button */}
          <div className="flex items-center gap-1.5 px-2.5 py-1.5 rounded-xl bg-emerald-50 dark:bg-neutral-900 border border-emerald-200 dark:border-neutral-800 text-xs shadow-sm">
            <Calendar className="w-3.5 h-3.5 text-emerald-600 dark:text-emerald-400 shrink-0" />
            <input
              type="date"
              value={selectedDate}
              onChange={(e) => setSelectedDate(e.target.value)}
              className="bg-transparent text-xs font-mono font-bold outline-none cursor-pointer text-emerald-900 dark:text-emerald-300 w-28 sm:w-32"
            />
          </div>

          <button
            onClick={onOpenOpeningBalance}
            className="px-2.5 py-1.5 sm:px-3 sm:py-2 rounded-xl bg-emerald-600 hover:bg-emerald-700 text-white font-extrabold text-[11px] sm:text-xs flex items-center gap-1 shadow-md shadow-emerald-600/20 transition-all cursor-pointer shrink-0"
            title="Set Opening Capital"
          >
            <Wallet className="w-3.5 h-3.5" />
            <span className="hidden lg:inline">{isEn ? 'Set Capital' : 'افتتاحی کیش'}</span>
          </button>

          <button
            onClick={() => setActiveTab('pos')}
            className={`px-2 py-1.5 sm:px-3 sm:py-2 rounded-xl font-bold text-[11px] sm:text-xs flex items-center gap-1 shadow-md transition-all cursor-pointer shrink-0 ${
              isLight
                ? 'bg-emerald-600 hover:bg-emerald-700 text-white border border-emerald-500 shadow-emerald-600/20'
                : 'bg-neutral-900 hover:bg-black text-white border border-emerald-600/40'
            }`}
          >
            <ShoppingCart className={`w-3.5 h-3.5 ${isLight ? 'text-white' : 'text-emerald-400'}`} />
            <span className="hidden sm:inline">{t('posBtn', settings)}</span>
            <span className="sm:hidden">POS</span>
          </button>

          {/* EasyPaisa New Transaction Button */}
          <button
            onClick={onOpenNewTransaction}
            className="px-2 py-1.5 sm:px-3 sm:py-2 rounded-xl bg-teal-600 hover:bg-teal-700 text-white font-bold text-[11px] sm:text-xs flex items-center gap-1 shadow-md shadow-teal-600/20 transition-all cursor-pointer shrink-0"
          >
            <Plus className="w-3.5 h-3.5" />
            <span className="hidden md:inline">{t('newEasyPaisa', settings)}</span>
            <span className="md:hidden">+ EasyPaisa</span>
          </button>
        </div>
      </div>

      {/* Side Menu Drawer matching Light Theme */}
      {isDrawerOpen && (
        <div className="fixed inset-0 z-50 flex">
          {/* Backdrop */}
          <div
            onClick={() => setIsDrawerOpen(false)}
            className="fixed inset-0 bg-black/50 backdrop-blur-sm transition-opacity"
          />

          {/* Drawer Box */}
          <div className={`relative w-80 max-w-[85vw] flex flex-col h-full shadow-2xl z-10 overflow-hidden ${
            isLight ? 'bg-white text-slate-900 border-r border-emerald-200' : 'bg-black text-white border-r border-emerald-900/50'
          }`}>
            
            {/* Drawer Header */}
            <div className={`p-4 flex items-center justify-between ${
              isLight
                ? 'bg-gradient-to-r from-emerald-600 via-teal-600 to-emerald-700 text-white'
                : 'bg-gradient-to-r from-neutral-950 via-neutral-900 to-black border-b border-emerald-900/40 text-white'
            }`}>
              <div className="flex items-center gap-3">
                <div className={`w-10 h-10 rounded-xl flex items-center justify-center font-bold ${
                  isLight ? 'bg-white/20 text-white border border-white/30' : 'bg-emerald-600/20 text-emerald-400 border border-emerald-600/40'
                }`}>
                  <Smartphone className="w-5 h-5" />
                </div>
                <div>
                  <h3 className="font-bold text-sm leading-tight text-white">{settings.shopName || 'Mobiles and EasyPaisa Shop POS'}</h3>
                  <p className={`text-[10px] mt-0.5 ${isLight ? 'text-emerald-100' : 'text-emerald-400'}`}>GT Road Sarai Saleh | 03319348330</p>
                </div>
              </div>

              <button
                onClick={() => setIsDrawerOpen(false)}
                className={`w-8 h-8 rounded-full flex items-center justify-center transition-colors ${
                  isLight ? 'bg-white/20 text-white hover:bg-white/30' : 'bg-neutral-900 hover:bg-emerald-900/50 text-neutral-300'
                }`}
              >
                <X className="w-5 h-5" />
              </button>
            </div>

            {/* Drawer Search Bar */}
            <div className={`p-3 border-b ${isLight ? 'bg-slate-50 border-slate-200' : 'bg-neutral-950 border-emerald-900/30'}`}>
              <div className="relative">
                <Search className="w-4 h-4 absolute left-3 top-2.5 text-emerald-600" />
                <input
                  type="text"
                  placeholder={isEn ? "Search menu..." : "سرچ کریں (Search menu)..."}
                  value={drawerSearch}
                  onChange={(e) => setDrawerSearch(e.target.value)}
                  className={`w-full pl-9 pr-3 py-1.5 rounded-xl text-xs focus:outline-none focus:ring-1 focus:ring-emerald-600 ${
                    isLight
                      ? 'bg-white border border-slate-300 text-slate-900 placeholder-slate-400'
                      : 'bg-neutral-900 border border-neutral-800 text-white placeholder-neutral-500'
                  }`}
                />
              </div>
            </div>

            {/* Menu List */}
            <div className="flex-1 overflow-y-auto p-3 space-y-1.5">
              {filteredMenuItems.map((item) => {
                const Icon = item.icon;
                const isActive = activeTab === item.id;

                return (
                  <button
                    key={item.id}
                    onClick={() => {
                      setActiveTab(item.id);
                      setIsDrawerOpen(false);
                    }}
                    className={`w-full p-2.5 rounded-xl text-left flex items-center justify-between transition-all ${
                      isActive
                        ? 'bg-emerald-600 text-white font-bold shadow-md shadow-emerald-600/20 border border-emerald-500'
                        : isLight
                        ? 'hover:bg-emerald-50 text-slate-700 font-medium'
                        : 'hover:bg-neutral-900 text-neutral-300 font-medium'
                    }`}
                  >
                    <div className="flex items-center gap-3">
                      <Icon className={`w-4 h-4 ${isActive ? 'text-white' : 'text-emerald-600'}`} />
                      <div className="text-left">
                        {isEn ? (
                          <p className="text-xs font-bold leading-tight">{item.titleEnglish}</p>
                        ) : (
                          <>
                            <p className="text-xs font-bold leading-tight">{item.titleUrdu}</p>
                            <p className={`text-[10px] ${isActive ? 'text-emerald-100' : isLight ? 'text-slate-400' : 'text-neutral-400'}`}>
                              {item.titleEnglish}
                            </p>
                          </>
                        )}
                      </div>
                    </div>
                  </button>
                );
              })}
            </div>

            {/* Drawer Footer */}
            <div className={`p-3 text-center text-xs space-y-2 border-t ${
              isLight ? 'bg-slate-50 border-slate-200' : 'bg-neutral-950 border-emerald-900/40'
            }`}>
              <button
                onClick={() => {
                  setIsDrawerOpen(false);
                  setIsCashCalcOpen(true);
                }}
                className="w-full py-2 px-3 rounded-xl bg-emerald-600 hover:bg-emerald-700 text-white font-extrabold text-xs flex items-center justify-center gap-2 shadow-sm transition-all"
              >
                <Calculator className="w-4 h-4" />
                <span>{isEn ? 'Daily Cash Calculator' : 'روزانہ نقد گنتی (Cash Calculator)'}</span>
              </button>

              <p className={`text-[11px] ${isLight ? 'text-emerald-700 font-semibold' : 'text-emerald-400'}`}>
                Protected Security System
              </p>
              <div className="flex justify-center gap-2">
                <button
                  onClick={onToggleTheme}
                  className={`py-1 px-3 rounded-lg text-[11px] font-semibold border ${
                    isLight
                      ? 'bg-white hover:bg-slate-100 text-slate-700 border-slate-300'
                      : 'bg-neutral-900 text-neutral-200 border-neutral-800'
                  }`}
                >
                  {isLight ? 'Dark Mode' : 'Light Mode'}
                </button>
                <button
                  onClick={() => {
                    setIsDrawerOpen(false);
                    onLock();
                  }}
                  className="py-1 px-3 rounded-lg bg-emerald-600 hover:bg-emerald-700 text-white font-bold text-[11px]"
                >
                  Lock App
                </button>
              </div>

              {onLogout && (
                <button
                  onClick={() => {
                    setIsDrawerOpen(false);
                    onLogout();
                  }}
                  className="w-full mt-2 py-2 px-3 rounded-xl bg-rose-600 hover:bg-rose-700 text-white font-black text-xs flex items-center justify-center gap-2 shadow transition-all cursor-pointer"
                >
                  <LogOut className="w-3.5 h-3.5" />
                  <span>{isEn ? 'Log Out Account (لاگ آؤٹ)' : 'لاگ آؤٹ (Log Out)'}</span>
                </button>
              )}
            </div>

          </div>
        </div>
      )}

      {/* Native Mobile Bottom Navigation Bar in Light Emerald Theme */}
      <nav className={`md:hidden fixed bottom-0 left-0 right-0 z-40 border-t shadow-2xl transition-colors duration-200 print:hidden ${
        isLight ? 'bg-white border-neutral-200 text-neutral-800' : 'bg-black border-neutral-900 text-neutral-100'
      }`}>
        <div className="grid grid-cols-5 h-14 items-center justify-between px-1">
          {/* 1. Dashboard */}
          <button
            onClick={() => setActiveTab('dashboard')}
            className={`flex flex-col items-center justify-center py-1 rounded-xl transition-all cursor-pointer ${
              activeTab === 'dashboard'
                ? 'text-emerald-600 font-extrabold'
                : 'text-neutral-500 hover:text-neutral-800'
            }`}
          >
            <div className={`p-1 rounded-xl transition-colors ${activeTab === 'dashboard' ? 'bg-emerald-50 text-emerald-600 dark:bg-emerald-950/60 dark:text-emerald-400' : ''}`}>
              <LayoutDashboard className="w-5 h-5" />
            </div>
            <span className="text-[10px] mt-0.5 leading-none">{settings.language === 'en' ? 'Dashboard' : 'ڈیش بورڈ'}</span>
          </button>

          {/* 2. POS Sale */}
          <button
            onClick={() => setActiveTab('pos')}
            className={`flex flex-col items-center justify-center py-1 rounded-xl transition-all cursor-pointer ${
              activeTab === 'pos'
                ? 'text-emerald-600 font-extrabold'
                : 'text-neutral-500 hover:text-neutral-800'
            }`}
          >
            <div className={`p-1 rounded-xl transition-colors ${activeTab === 'pos' ? 'bg-emerald-50 text-emerald-600 dark:bg-emerald-950/60 dark:text-emerald-400' : ''}`}>
              <ShoppingCart className="w-5 h-5" />
            </div>
            <span className="text-[10px] mt-0.5 leading-none">{settings.language === 'en' ? 'POS' : 'فروخت بل'}</span>
          </button>

          {/* 3. EasyPaisa Ledger */}
          <button
            onClick={() => setActiveTab('ledger')}
            className={`flex flex-col items-center justify-center py-1 rounded-xl transition-all cursor-pointer ${
              activeTab === 'ledger'
                ? 'text-emerald-600 font-extrabold'
                : 'text-neutral-500 hover:text-neutral-800'
            }`}
          >
            <div className={`p-1 rounded-xl transition-colors ${activeTab === 'ledger' ? 'bg-emerald-50 text-emerald-600 dark:bg-emerald-950/60 dark:text-emerald-400' : ''}`}>
              <BookOpen className="w-5 h-5" />
            </div>
            <span className="text-[10px] mt-0.5 leading-none">{settings.language === 'en' ? 'Ledger' : 'ایزی پیسہ'}</span>
          </button>

          {/* 4. Inventory Stock */}
          <button
            onClick={() => setActiveTab('inventory')}
            className={`flex flex-col items-center justify-center py-1 rounded-xl transition-all cursor-pointer ${
              activeTab === 'inventory'
                ? 'text-emerald-600 font-extrabold'
                : 'text-neutral-500 hover:text-neutral-800'
            }`}
          >
            <div className={`p-1 rounded-xl transition-colors ${activeTab === 'inventory' ? 'bg-emerald-50 text-emerald-600 dark:bg-emerald-950/60 dark:text-emerald-400' : ''}`}>
              <Package className="w-5 h-5" />
            </div>
            <span className="text-[10px] mt-0.5 leading-none">{settings.language === 'en' ? 'Stock' : 'اسٹاک'}</span>
          </button>

          {/* 5. Menu Drawer Toggle */}
          <button
            onClick={() => setIsDrawerOpen(true)}
            className="flex flex-col items-center justify-center py-1 rounded-xl text-neutral-500 hover:text-neutral-800 transition-all cursor-pointer"
          >
            <div className="p-1 rounded-xl">
              <Menu className="w-5 h-5 text-emerald-600" />
            </div>
            <span className="text-[10px] mt-0.5 leading-none font-bold text-emerald-600">{settings.language === 'en' ? 'Menu' : 'مینو'}</span>
          </button>
        </div>
      </nav>

      {/* Cash Calculator Modal */}
      <CashCalculatorModal
        isOpen={isCashCalcOpen}
        onClose={() => setIsCashCalcOpen(false)}
        isLight={isLight}
      />

    </header>
  );
};

