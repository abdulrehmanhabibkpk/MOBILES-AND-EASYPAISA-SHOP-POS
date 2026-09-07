<?php
$baseDir = '../';
?>
<!-- Sidebar Navigation -->
<aside id="mainSidebar" class="w-72 bg-slate-900 <?= $isUrdu ? 'border-l' : 'border-r' ?> border-slate-800 flex flex-col shrink-0 no-print transition-all duration-300 md:relative fixed inset-y-0 <?= $isUrdu ? 'right-0' : 'left-0' ?> z-50 transform md:translate-x-0 <?= $isUrdu ? 'translate-x-full' : '-translate-x-full' ?>">
    <!-- Brand Header -->
    <div class="p-5 border-b border-slate-800 flex items-center justify-between">
        <div class="flex items-center gap-3">
            <div class="w-10 h-10 rounded-xl bg-gradient-to-tr from-limoblue-600 via-sky-500 to-limogreen-500 flex items-center justify-center text-white font-black text-xl shadow-lg shadow-limoblue-500/25 animate-pulse-glow">
                L
            </div>
            <div class="min-w-0">
                <h2 class="font-black text-white text-base leading-tight truncate tracking-tight"><?= $shopName ?></h2>
                <span class="text-[11px] text-limogreen-400 font-bold"><?= $isUrdu ? 'لیمو موبائل پی او ایس' : 'LimoMobile Smart POS' ?></span>
            </div>
        </div>
        <button id="closeSidebarBtn" class="md:hidden p-1.5 text-slate-400 hover:text-white rounded-lg bg-slate-800">
            <i data-lucide="x" class="w-5 h-5"></i>
        </button>
    </div>

    <!-- Menu Links -->
    <nav class="flex-1 p-3 space-y-1 overflow-y-auto text-sm">
        <a href="../dashboard/index.php" class="flex items-center gap-3 px-3 py-2.5 rounded-xl font-medium transition-all <?= $activeMenu === 'dashboard' ? 'bg-gradient-to-r from-limoblue-600 to-limoblue-700 text-white shadow-lg shadow-limoblue-600/30' : 'text-slate-300 hover:bg-slate-800 hover:text-white' ?>">
            <i data-lucide="layout-dashboard" class="w-5 h-5 shrink-0 <?= $activeMenu === 'dashboard' ? 'text-white' : 'text-limoblue-400' ?>"></i>
            <div class="flex flex-col">
                <span class="font-bold leading-tight"><?= $isUrdu ? 'ڈیش بورڈ' : 'Dashboard' ?></span>
                <span class="text-[10px] opacity-75"><?= $isUrdu ? 'Dashboard Overview' : 'Overview & Daily Stats' ?></span>
            </div>
        </a>

        <a href="../pos/index.php" class="flex items-center gap-3 px-3 py-2.5 rounded-xl font-medium transition-all <?= $activeMenu === 'pos' ? 'bg-gradient-to-r from-limoblue-600 to-limoblue-700 text-white shadow-lg shadow-limoblue-600/30' : 'text-slate-300 hover:bg-slate-800 hover:text-white' ?>">
            <i data-lucide="shopping-cart" class="w-5 h-5 shrink-0 <?= $activeMenu === 'pos' ? 'text-white' : 'text-limogreen-400' ?>"></i>
            <div class="flex flex-col">
                <span class="font-bold leading-tight"><?= $isUrdu ? 'سیل اینڈ پی او ایس' : 'POS & Fast Billing' ?></span>
                <span class="text-[10px] opacity-75"><?= $isUrdu ? 'Point of Sale' : 'Barcode & Quick Invoices' ?></span>
            </div>
        </a>

        <a href="../sales_ledger/index.php" class="flex items-center gap-3 px-3 py-2.5 rounded-xl font-medium transition-all <?= $activeMenu === 'sales_ledger' ? 'bg-gradient-to-r from-limoblue-600 to-limoblue-700 text-white shadow-lg shadow-limoblue-600/30' : 'text-slate-300 hover:bg-slate-800 hover:text-white' ?>">
            <i data-lucide="file-text" class="w-5 h-5 shrink-0 <?= $activeMenu === 'sales_ledger' ? 'text-white' : 'text-limoblue-400' ?>"></i>
            <div class="flex flex-col">
                <span class="font-bold leading-tight"><?= $isUrdu ? 'سیلز اینڈ پرافٹ لیجر' : 'Sales & Profit Ledger' ?></span>
                <span class="text-[10px] opacity-75"><?= $isUrdu ? 'Invoices & Profits' : 'Sales Records & Net Margins' ?></span>
            </div>
        </a>

        <a href="../purchase_register/index.php" class="flex items-center gap-3 px-3 py-2.5 rounded-xl font-medium transition-all <?= $activeMenu === 'purchase_register' ? 'bg-gradient-to-r from-limoblue-600 to-limoblue-700 text-white shadow-lg shadow-limoblue-600/30' : 'text-slate-300 hover:bg-slate-800 hover:text-white' ?>">
            <i data-lucide="smartphone" class="w-5 h-5 shrink-0 <?= $activeMenu === 'purchase_register' ? 'text-white' : 'text-limogreen-400' ?>"></i>
            <div class="flex flex-col">
                <span class="font-bold leading-tight"><?= $isUrdu ? 'موبائل خرید رجسٹر' : 'Used Mobile Purchases' ?></span>
                <span class="text-[10px] opacity-75"><?= $isUrdu ? 'Customer ID & Agreement' : 'CNIC & Purchase Agreement' ?></span>
            </div>
        </a>

        <a href="../inventory/index.php" class="flex items-center gap-3 px-3 py-2.5 rounded-xl font-medium transition-all <?= $activeMenu === 'inventory' ? 'bg-gradient-to-r from-limoblue-600 to-limoblue-700 text-white shadow-lg shadow-limoblue-600/30' : 'text-slate-300 hover:bg-slate-800 hover:text-white' ?>">
            <i data-lucide="package" class="w-5 h-5 shrink-0 <?= $activeMenu === 'inventory' ? 'text-white' : 'text-limoblue-400' ?>"></i>
            <div class="flex flex-col">
                <span class="font-bold leading-tight"><?= $isUrdu ? 'اسٹاک انوینٹری' : 'Stock & Inventory' ?></span>
                <span class="text-[10px] opacity-75"><?= $isUrdu ? 'Products & Accessories' : 'Phones, Accessories & Stock' ?></span>
            </div>
        </a>

        <a href="../mobile_ledger/index.php" class="flex items-center gap-3 px-3 py-2.5 rounded-xl font-medium transition-all <?= $activeMenu === 'mobile_ledger' ? 'bg-gradient-to-r from-limoblue-600 to-limoblue-700 text-white shadow-lg shadow-limoblue-600/30' : 'text-slate-300 hover:bg-slate-800 hover:text-white' ?>">
            <i data-lucide="binary" class="w-5 h-5 shrink-0 <?= $activeMenu === 'mobile_ledger' ? 'text-white' : 'text-limogreen-400' ?>"></i>
            <div class="flex flex-col">
                <span class="font-bold leading-tight"><?= $isUrdu ? 'موبائل IMEI لیجر' : 'IMEI & Device Ledger' ?></span>
                <span class="text-[10px] opacity-75"><?= $isUrdu ? 'Track Stock & Sold IMEIs' : 'IMEI Stock & Warranty Track' ?></span>
            </div>
        </a>

        <a href="../easypaisa_ledger/index.php" class="flex items-center gap-3 px-3 py-2.5 rounded-xl font-medium transition-all <?= $activeMenu === 'easypaisa' ? 'bg-gradient-to-r from-limoblue-600 to-limoblue-700 text-white shadow-lg shadow-limoblue-600/30' : 'text-slate-300 hover:bg-slate-800 hover:text-white' ?>">
            <i data-lucide="banknote" class="w-5 h-5 shrink-0 <?= $activeMenu === 'easypaisa' ? 'text-white' : 'text-limogreen-400' ?>"></i>
            <div class="flex flex-col">
                <span class="font-bold leading-tight"><?= $isUrdu ? 'ایزی پیسہ و کیش رجسٹر' : 'EasyPaisa & Cash Drawer' ?></span>
                <span class="text-[10px] opacity-75"><?= $isUrdu ? 'Cash In / Cash Out' : 'Cash In, Cash Out & Fees' ?></span>
            </div>
        </a>

        <a href="../customer_khata/index.php" class="flex items-center gap-3 px-3 py-2.5 rounded-xl font-medium transition-all <?= $activeMenu === 'customer_khata' ? 'bg-gradient-to-r from-limoblue-600 to-limoblue-700 text-white shadow-lg shadow-limoblue-600/30' : 'text-slate-300 hover:bg-slate-800 hover:text-white' ?>">
            <i data-lucide="users" class="w-5 h-5 shrink-0 <?= $activeMenu === 'customer_khata' ? 'text-white' : 'text-limoblue-400' ?>"></i>
            <div class="flex flex-col">
                <span class="font-bold leading-tight"><?= $isUrdu ? 'کسٹمر کھاتہ و ادھار' : 'Customer Credit Khata' ?></span>
                <span class="text-[10px] opacity-75"><?= $isUrdu ? 'Receivables & WhatsApp' : 'Ledger, Udhaar & WhatsApp' ?></span>
            </div>
        </a>

        <a href="../supplier_khata/index.php" class="flex items-center gap-3 px-3 py-2.5 rounded-xl font-medium transition-all <?= $activeMenu === 'supplier_khata' ? 'bg-gradient-to-r from-limoblue-600 to-limoblue-700 text-white shadow-lg shadow-limoblue-600/30' : 'text-slate-300 hover:bg-slate-800 hover:text-white' ?>">
            <i data-lucide="truck" class="w-5 h-5 shrink-0 <?= $activeMenu === 'supplier_khata' ? 'text-white' : 'text-limogreen-400' ?>"></i>
            <div class="flex flex-col">
                <span class="font-bold leading-tight"><?= $isUrdu ? 'سپلائر ڈائریکٹری و کھاتہ' : 'Supplier Directory & Khata' ?></span>
                <span class="text-[10px] opacity-75"><?= $isUrdu ? 'Payables & Purchases' : 'Wholesalers & Payables' ?></span>
            </div>
        </a>

        <a href="../photo_vault/index.php" class="flex items-center gap-3 px-3 py-2.5 rounded-xl font-medium transition-all <?= $activeMenu === 'photo_vault' ? 'bg-gradient-to-r from-limoblue-600 to-limoblue-700 text-white shadow-lg shadow-limoblue-600/30' : 'text-slate-300 hover:bg-slate-800 hover:text-white' ?>">
            <i data-lucide="camera" class="w-5 h-5 shrink-0 <?= $activeMenu === 'photo_vault' ? 'text-white' : 'text-limoblue-400' ?>"></i>
            <div class="flex flex-col">
                <span class="font-bold leading-tight"><?= $isUrdu ? 'فوٹو والٹ و شناختی کارڈ' : 'Photo Vault & CNIC' ?></span>
                <span class="text-[10px] opacity-75"><?= $isUrdu ? 'Document Archive' : 'ID Photos & Bio Documents' ?></span>
            </div>
        </a>

        <a href="../barcode_studio/index.php" class="flex items-center gap-3 px-3 py-2.5 rounded-xl font-medium transition-all <?= $activeMenu === 'barcode_studio' ? 'bg-gradient-to-r from-limoblue-600 to-limoblue-700 text-white shadow-lg shadow-limoblue-600/30' : 'text-slate-300 hover:bg-slate-800 hover:text-white' ?>">
            <i data-lucide="scan-barcode" class="w-5 h-5 shrink-0 <?= $activeMenu === 'barcode_studio' ? 'text-white' : 'text-limogreen-400' ?>"></i>
            <div class="flex flex-col">
                <span class="font-bold leading-tight"><?= $isUrdu ? 'بارکوڈ اسٹوڈیو و پرنٹ' : 'Barcode Studio & Print' ?></span>
                <span class="text-[10px] opacity-75"><?= $isUrdu ? 'Thermal Label Printing' : 'Thermal & Sticker Printing' ?></span>
            </div>
        </a>

        <a href="../reports/index.php" class="flex items-center gap-3 px-3 py-2.5 rounded-xl font-medium transition-all <?= $activeMenu === 'reports' ? 'bg-gradient-to-r from-limoblue-600 to-limoblue-700 text-white shadow-lg shadow-limoblue-600/30' : 'text-slate-300 hover:bg-slate-800 hover:text-white' ?>">
            <i data-lucide="bar-chart-3" class="w-5 h-5 shrink-0 <?= $activeMenu === 'reports' ? 'text-white' : 'text-limoblue-400' ?>"></i>
            <div class="flex flex-col">
                <span class="font-bold leading-tight"><?= $isUrdu ? 'رپورٹس و تجزیات' : 'Reports & Analytics' ?></span>
                <span class="text-[10px] opacity-75"><?= $isUrdu ? 'Financial Analysis' : 'Profit, Loss & Growth Stats' ?></span>
            </div>
        </a>

        <a href="../gdrive/index.php" class="flex items-center gap-3 px-3 py-2.5 rounded-xl font-medium transition-all <?= $activeMenu === 'gdrive' ? 'bg-gradient-to-r from-limoblue-600 to-limoblue-700 text-white shadow-lg shadow-limoblue-600/30' : 'text-slate-300 hover:bg-slate-800 hover:text-white' ?>">
            <i data-lucide="cloud" class="w-5 h-5 shrink-0 <?= $activeMenu === 'gdrive' ? 'text-white' : 'text-emerald-400' ?>"></i>
            <div class="flex flex-col">
                <span class="font-bold leading-tight"><?= $isUrdu ? 'گوگل ڈرائیو فائلز' : 'Google Drive Cloud' ?></span>
                <span class="text-[10px] opacity-75"><?= $isUrdu ? 'فائلز، فولڈرز و کلاؤڈ بیک اپ' : 'Files, Folders & Backups' ?></span>
            </div>
        </a>

        <a href="../admin/index.php" class="flex items-center gap-3 px-3 py-2.5 rounded-xl font-medium transition-all <?= $activeMenu === 'admin' ? 'bg-gradient-to-r from-limoblue-600 to-limoblue-700 text-white shadow-lg shadow-limoblue-600/30' : 'text-slate-300 hover:bg-slate-800 hover:text-white' ?>">
            <i data-lucide="shield-check" class="w-5 h-5 text-emerald-400 shrink-0"></i>
            <div class="flex flex-col">
                <span class="font-bold leading-tight"><?= $isUrdu ? 'اسٹاف و اختیارات' : 'Staff & Permissions' ?></span>
                <span class="text-[10px] opacity-75"><?= $isUrdu ? 'اسٹاف اکاؤنٹس، پِن اور پرمیشنز' : 'Staff Accounts, PINs & Access' ?></span>
            </div>
        </a>

        <a href="../settings/index.php" class="flex items-center gap-3 px-3 py-2.5 rounded-xl font-medium transition-all <?= $activeMenu === 'settings' ? 'bg-gradient-to-r from-limoblue-600 to-limoblue-700 text-white shadow-lg shadow-limoblue-600/30' : 'text-slate-300 hover:bg-slate-800 hover:text-white' ?>">
            <i data-lucide="settings" class="w-5 h-5 shrink-0 <?= $activeMenu === 'settings' ? 'text-white' : 'text-limogreen-400' ?>"></i>
            <div class="flex flex-col">
                <span class="font-bold leading-tight"><?= $isUrdu ? 'سیٹنگز و بیک اپ' : 'Settings & Backup' ?></span>
                <span class="text-[10px] opacity-75"><?= $isUrdu ? 'Shop Profile & Config' : 'Shop Profile & DB Backup' ?></span>
            </div>
        </a>

        <!-- Extra Modules: Landing Page & Register Shop -->
        <div class="pt-2 mt-2 border-t border-slate-800/80 space-y-1">
            <a href="../landing/index.php" class="flex items-center gap-3 px-3 py-2 rounded-xl text-xs font-bold text-slate-300 hover:bg-slate-800 hover:text-white transition-colors">
                <i data-lucide="globe" class="w-4 h-4 text-limoblue-400 shrink-0"></i>
                <span><?= $isUrdu ? 'تعارفی صفحہ (Landing Page)' : 'Landing Page' ?></span>
            </a>

            <a href="../register_shop/index.php" class="flex items-center gap-3 px-3 py-2 rounded-xl text-xs font-bold text-slate-300 hover:bg-slate-800 hover:text-white transition-colors">
                <i data-lucide="building-2" class="w-4 h-4 text-limogreen-400 shrink-0"></i>
                <span><?= $isUrdu ? 'نئی دکان رجسٹر کریں' : 'Register New Shop' ?></span>
            </a>

            <a href="../logout.php" class="flex items-center gap-3 px-3 py-2 rounded-xl text-xs font-bold text-rose-400 hover:bg-rose-500/10 transition-colors">
                <i data-lucide="log-out" class="w-4 h-4 shrink-0"></i>
                <span><?= $isUrdu ? 'لاگ آؤٹ (Lock POS)' : 'Logout / Lock' ?></span>
            </a>
        </div>
    </nav>
    <div class="p-4 border-t border-slate-800 text-xs text-slate-500 flex flex-col gap-1">
        <div class="flex items-center justify-between">
            <span class="font-bold text-slate-400">LimoMobile POS</span>
            <span class="px-2 py-0.5 rounded bg-slate-800 text-limogreen-400 font-mono text-[10px] font-bold">v3.0 Pro</span>
        </div>
        <?php if (isUserLoggedIn()): $u = getLoggedInUser(); ?>
            <span class="text-[11px] text-limoblue-400 truncate font-semibold">👤 <?= htmlspecialchars($u['name'] ?? 'Owner') ?></span>
        <?php endif; ?>
    </div>
</aside>

