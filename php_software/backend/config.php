<?php
/**
 * Balal Mobile & EasyPaisa POS - Complete PHP Backend Configuration
 * Compatible with MySQL (InfinityFree, cPanel, Hostinger) and SQLite local fallback
 */
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// -------------------------------------------------------------
// LANGUAGE & LOCALIZATION CONTROLLER (English / اردو)
// -------------------------------------------------------------
if (isset($_GET['lang'])) {
    $reqLang = strtolower(trim($_GET['lang']));
    if (in_array($reqLang, ['en', 'ur'])) {
        $_SESSION['app_lang'] = $reqLang;
        setcookie('app_lang', $reqLang, time() + (86400 * 365), '/');
    }
}
$currentLang = $_SESSION['app_lang'] ?? ($_COOKIE['app_lang'] ?? 'en');
$isUrdu = ($currentLang === 'ur');
$isEnglish = ($currentLang === 'en');

// -------------------------------------------------------------
// THEME CONTROLLER (Light Theme by Default / Dark Theme Toggle)
// -------------------------------------------------------------
if (isset($_GET['theme'])) {
    $reqTheme = strtolower(trim($_GET['theme']));
    if (in_array($reqTheme, ['light', 'dark'])) {
        $_SESSION['app_theme'] = $reqTheme;
        setcookie('app_theme', $reqTheme, time() + (86400 * 365), '/');
    }
}
$currentTheme = $_SESSION['app_theme'] ?? ($_COOKIE['app_theme'] ?? 'light');
$isLight = ($currentTheme === 'light');
$isDark = ($currentTheme === 'dark');

/**
 * Multi-language string helper (Returns English or Urdu with fallback)
 */
function __t($en, $ur = null) {
    global $currentLang;
    if ($ur === null) return $en;
    return ($currentLang === 'ur') ? $ur : $en;
}

$DB_HOST = 'localhost'; // InfinityFree: sqlXXX.epizy.com or sql306.byetcluster.com
$DB_NAME = 'if0_42697574_mobilesshoppos'; // your db name
$DB_USER = 'if0_42697574'; // your db user
$DB_PASS = 'AapKaVpanelPassword'; // your db password
$DB_PORT = 3306;

$pdo = null;

// Function to initialize SQLite tables if local testing without MySQL
function initSqliteTables($pdo) {
    $pdo->exec("CREATE TABLE IF NOT EXISTS products (
        id TEXT PRIMARY KEY,
        name TEXT NOT NULL,
        category TEXT DEFAULT 'MOBILES',
        purchase_price REAL DEFAULT 0,
        sale_price REAL DEFAULT 0,
        stock INTEGER DEFAULT 0,
        image TEXT,
        brand_or_model TEXT,
        imei_or_serial TEXT,
        sku TEXT,
        color TEXT,
        ram_storage TEXT,
        condition_status TEXT DEFAULT 'NEW',
        pta_status TEXT DEFAULT 'PTA_APPROVED',
        battery_health TEXT,
        warranty TEXT,
        wattage TEXT,
        port_type TEXT,
        compatible_model TEXT,
        protector_type TEXT,
        cable_type TEXT,
        battery_capacity TEXT,
        units_json TEXT,
        created_at INTEGER DEFAULT 0,
        updated_at TEXT
    )");

    $pdo->exec("CREATE TABLE IF NOT EXISTS product_sales (
        id TEXT PRIMARY KEY,
        invoice_no TEXT NOT NULL,
        date TEXT NOT NULL,
        time TEXT NOT NULL,
        customer_name TEXT DEFAULT 'Walk-in Customer',
        customer_phone TEXT,
        total_amount REAL DEFAULT 0,
        discount REAL DEFAULT 0,
        net_amount REAL DEFAULT 0,
        total_purchase_cost REAL DEFAULT 0,
        profit REAL DEFAULT 0,
        payment_method TEXT DEFAULT 'CASH',
        paid_amount REAL DEFAULT 0,
        due_amount REAL DEFAULT 0,
        items_json TEXT,
        cashier_name TEXT,
        notes TEXT,
        created_at INTEGER DEFAULT 0
    )");

    $pdo->exec("CREATE TABLE IF NOT EXISTS transactions (
        id TEXT PRIMARY KEY,
        type TEXT NOT NULL,
        customer_name TEXT DEFAULT 'Walk-in Customer',
        customer_phone TEXT,
        cnic TEXT,
        easy_paisa_amount REAL DEFAULT 0,
        cash_amount REAL DEFAULT 0,
        fee_profit REAL DEFAULT 0,
        expense_amount REAL DEFAULT 0,
        payment_method TEXT DEFAULT 'EASYPAISA',
        trx_id TEXT,
        date TEXT NOT NULL,
        time TEXT NOT NULL,
        note TEXT,
        photo_url TEXT,
        created_at INTEGER DEFAULT 0
    )");

    $pdo->exec("CREATE TABLE IF NOT EXISTS suppliers (
        id TEXT PRIMARY KEY,
        name TEXT NOT NULL,
        phone TEXT,
        city TEXT,
        address TEXT,
        company_or_market TEXT,
        balance REAL DEFAULT 0,
        notes TEXT,
        invoices_json TEXT,
        created_at INTEGER DEFAULT 0
    )");

    $pdo->exec("CREATE TABLE IF NOT EXISTS mobile_purchases (
        id TEXT PRIMARY KEY,
        receipt_no TEXT,
        date TEXT NOT NULL,
        time TEXT NOT NULL,
        seller_name TEXT NOT NULL,
        seller_phone TEXT,
        seller_cnic TEXT NOT NULL,
        seller_address TEXT,
        seller_photo TEXT,
        cnic_front_photo TEXT,
        cnic_back_photo TEXT,
        mobile_photo TEXT,
        box_photo TEXT,
        thumb_signature TEXT,
        brand_or_model TEXT NOT NULL,
        condition_status TEXT DEFAULT 'USED',
        imei_1 TEXT NOT NULL,
        imei_2 TEXT,
        color TEXT,
        ram_storage TEXT,
        sku TEXT,
        pta_status TEXT DEFAULT 'PTA_APPROVED',
        has_box INTEGER DEFAULT 1,
        has_charger INTEGER DEFAULT 1,
        has_cable INTEGER DEFAULT 1,
        has_handsfree INTEGER DEFAULT 0,
        has_warranty_card INTEGER DEFAULT 0,
        accessories TEXT,
        purchase_price REAL DEFAULT 0,
        estimated_sale_price REAL DEFAULT 0,
        payment_method TEXT DEFAULT 'CASH',
        notes TEXT,
        created_at INTEGER DEFAULT 0
    )");

    // Auto-migrate columns if table already existed with older schema
    $purColumns = [
        "receipt_no TEXT",
        "date TEXT",
        "time TEXT",
        "seller_name TEXT",
        "seller_phone TEXT",
        "seller_cnic TEXT",
        "seller_address TEXT",
        "seller_photo TEXT",
        "cnic_front_photo TEXT",
        "cnic_back_photo TEXT",
        "mobile_photo TEXT",
        "box_photo TEXT",
        "thumb_signature TEXT",
        "brand_or_model TEXT",
        "condition_status TEXT DEFAULT 'USED'",
        "imei_1 TEXT",
        "imei_2 TEXT",
        "color TEXT",
        "ram_storage TEXT",
        "sku TEXT",
        "pta_status TEXT DEFAULT 'PTA_APPROVED'",
        "has_box INTEGER DEFAULT 1",
        "has_charger INTEGER DEFAULT 1",
        "has_cable INTEGER DEFAULT 1",
        "has_handsfree INTEGER DEFAULT 0",
        "has_warranty_card INTEGER DEFAULT 0",
        "accessories TEXT",
        "purchase_price REAL DEFAULT 0",
        "estimated_sale_price REAL DEFAULT 0",
        "payment_method TEXT DEFAULT 'CASH'",
        "notes TEXT"
    ];
    foreach ($purColumns as $colDef) {
        try {
            $pdo->exec("ALTER TABLE mobile_purchases ADD COLUMN $colDef");
        } catch (Exception $e) {
            // Column already exists, safe to ignore
        }
    }

    $pdo->exec("CREATE TABLE IF NOT EXISTS supplier_transactions (
        id TEXT PRIMARY KEY,
        supplier_id TEXT NOT NULL,
        date TEXT NOT NULL,
        time TEXT NOT NULL,
        type TEXT NOT NULL,
        invoice_no TEXT,
        description TEXT,
        amount REAL NOT NULL DEFAULT 0,
        balance_after REAL NOT NULL DEFAULT 0,
        payment_method TEXT DEFAULT 'CASH',
        notes TEXT,
        created_at INTEGER DEFAULT 0
    )");

    $pdo->exec("CREATE TABLE IF NOT EXISTS vault_photos (
        id TEXT PRIMARY KEY,
        title TEXT NOT NULL,
        category TEXT NOT NULL,
        ref_no TEXT,
        imei TEXT,
        customer_name TEXT,
        phone TEXT,
        cnic TEXT,
        filename TEXT NOT NULL,
        file_path TEXT NOT NULL,
        file_size_kb REAL DEFAULT 0,
        notes TEXT,
        created_at INTEGER DEFAULT 0
    )");

    $pdo->exec("CREATE TABLE IF NOT EXISTS daily_balances (
        date TEXT PRIMARY KEY,
        opening_cash REAL DEFAULT 0,
        opening_easypaisa REAL DEFAULT 0,
        notes TEXT
    )");

    $pdo->exec("CREATE TABLE IF NOT EXISTS app_settings (
        setting_key TEXT PRIMARY KEY,
        setting_value TEXT NOT NULL
    )");

    $pdo->exec("CREATE TABLE IF NOT EXISTS users (
        id TEXT PRIMARY KEY,
        name TEXT NOT NULL,
        email TEXT UNIQUE NOT NULL,
        password_hash TEXT NOT NULL,
        pin_code TEXT DEFAULT '6242',
        role TEXT DEFAULT 'Owner',
        status TEXT DEFAULT 'ACTIVE',
        permissions TEXT DEFAULT '[]',
        shop_name TEXT,
        phone TEXT,
        last_login INTEGER DEFAULT 0,
        created_at INTEGER DEFAULT 0
    )");

    // Auto-migrate user table columns if missing
    $userCols = [
        "status TEXT DEFAULT 'ACTIVE'",
        "permissions TEXT DEFAULT '[]'",
        "last_login INTEGER DEFAULT 0"
    ];
    foreach ($userCols as $uc) {
        try {
            $pdo->exec("ALTER TABLE users ADD COLUMN $uc");
        } catch (Exception $e) {}
    }

    $pdo->exec("CREATE TABLE IF NOT EXISTS user_activity_logs (
        id TEXT PRIMARY KEY,
        user_id TEXT,
        user_name TEXT,
        role TEXT,
        action TEXT NOT NULL,
        details TEXT,
        ip_address TEXT,
        created_at INTEGER DEFAULT 0
    )");

    $pdo->exec("CREATE TABLE IF NOT EXISTS registered_shops (
        id TEXT PRIMARY KEY,
        shop_name TEXT NOT NULL,
        owner_name TEXT NOT NULL,
        phone TEXT NOT NULL,
        email TEXT,
        city TEXT,
        address TEXT,
        pin_code TEXT DEFAULT '6242',
        thermal_size TEXT DEFAULT '80mm',
        subscription_plan TEXT DEFAULT 'MONTHLY',
        subscription_fee REAL DEFAULT 2500,
        fee_cycle TEXT DEFAULT 'MONTHLY',
        subscription_status TEXT DEFAULT 'ACTIVE',
        last_payment_date TEXT,
        next_due_date TEXT,
        license_key TEXT,
        opening_cash REAL DEFAULT 0,
        opening_easypaisa REAL DEFAULT 0,
        notes TEXT,
        created_at INTEGER DEFAULT 0
    )");

    // Auto-migrate registered_shops columns if missing
    $shopCols = [
        "subscription_plan TEXT DEFAULT 'MONTHLY'",
        "subscription_fee REAL DEFAULT 2500",
        "fee_cycle TEXT DEFAULT 'MONTHLY'",
        "subscription_status TEXT DEFAULT 'ACTIVE'",
        "last_payment_date TEXT",
        "next_due_date TEXT",
        "license_key TEXT",
        "notes TEXT"
    ];
    foreach ($shopCols as $sc) {
        try {
            $pdo->exec("ALTER TABLE registered_shops ADD COLUMN $sc");
        } catch (Exception $e) {}
    }

    // Table for Shop Fee Collections & Subscriptions
    $pdo->exec("CREATE TABLE IF NOT EXISTS shop_fee_payments (
        id TEXT PRIMARY KEY,
        shop_id TEXT NOT NULL,
        shop_name TEXT NOT NULL,
        owner_name TEXT NOT NULL,
        phone TEXT,
        amount REAL NOT NULL DEFAULT 0,
        payment_date TEXT NOT NULL,
        billing_month_year TEXT,
        period_start TEXT,
        period_end TEXT,
        plan_type TEXT DEFAULT 'MONTHLY',
        payment_method TEXT DEFAULT 'EASYPAISA',
        trx_id TEXT,
        receipt_no TEXT,
        status TEXT DEFAULT 'PAID',
        recorded_by TEXT DEFAULT 'CEO Master',
        notes TEXT,
        created_at INTEGER DEFAULT 0
    )");

    // Table for Landing Page Announcements, Feature Spotlights & Posts
    $pdo->exec("CREATE TABLE IF NOT EXISTS landing_posts (
        id TEXT PRIMARY KEY,
        title TEXT NOT NULL,
        content TEXT NOT NULL,
        category TEXT DEFAULT 'ANNOUNCEMENT',
        image_url TEXT,
        badge_text TEXT,
        action_text TEXT,
        action_url TEXT,
        is_pinned INTEGER DEFAULT 0,
        status TEXT DEFAULT 'PUBLISHED',
        author_name TEXT DEFAULT 'LimoMobile CEO',
        likes_count INTEGER DEFAULT 0,
        created_at INTEGER DEFAULT 0,
        updated_at INTEGER DEFAULT 0
    )");

    // Seed default admin and staff users if users table is empty OR master admin missing
    $masterExists = $pdo->query("SELECT COUNT(*) FROM users WHERE LOWER(email) = 'admin@limopos.com'")->fetchColumn();
    $now = time();
    $masterPassHash = password_hash('6242842', PASSWORD_DEFAULT);

    if (intval($masterExists) === 0) {
        try {
            $pdo->exec("INSERT INTO users (id, name, email, password_hash, pin_code, role, status, permissions, shop_name, phone, last_login, created_at) VALUES 
            ('usr-ceo-01', 'LimoMobile CEO & SuperAdmin', 'admin@limopos.com', '{$masterPassHash}', '6242', 'SuperAdmin', 'ACTIVE', '[\"all\",\"ceo\",\"superadmin\"]', 'LimoMobile Central HQ', '0300-6242842', {$now}, {$now})");
        } catch (Exception $e) {}
    }

    $userCount = $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
    if (intval($userCount) <= 1) {
        $defaultPass = password_hash('admin123', PASSWORD_DEFAULT);
        $cashierPass = password_hash('cashier123', PASSWORD_DEFAULT);
        $managerPass = password_hash('manager123', PASSWORD_DEFAULT);

        $pdo->exec("INSERT OR IGNORE INTO users (id, name, email, password_hash, pin_code, role, status, permissions, shop_name, phone, last_login, created_at) VALUES 
        ('usr-admin-01', 'LimoMobile Admin (Owner)', 'admin@limomobile.com', '{$defaultPass}', '6242', 'Owner', 'ACTIVE', '[\"all\"]', 'LimoMobile POS & EasyPaisa Shop', '0300-1234567', {$now}, {$now}),
        ('usr-mgr-01', 'Aamir Shahzad (Store Manager)', 'manager@limomobile.com', '{$managerPass}', '1122', 'Manager', 'ACTIVE', '[\"pos\",\"inventory\",\"purchases\",\"khata\",\"easypaisa\",\"reports\"]', 'LimoMobile POS & EasyPaisa Shop', '0302-8877665', {$now}, " . ($now - 86400) . "),
        ('usr-csh-01', 'Hamza Tariq (POS Cashier)', 'cashier@limomobile.com', '{$cashierPass}', '3344', 'Cashier', 'ACTIVE', '[\"pos\",\"easypaisa\",\"barcode\"]', 'LimoMobile POS & EasyPaisa Shop', '0345-1122334', {$now}, " . ($now - 3600) . ")");

        // Seed initial login logs
        $pdo->exec("INSERT OR IGNORE INTO user_activity_logs (id, user_id, user_name, role, action, details, ip_address, created_at) VALUES
        ('log-001', 'usr-ceo-01', 'CEO Master Admin', 'SuperAdmin', 'MASTER_LOGIN', 'CEO Master SuperAdmin system verified', '127.0.0.1', {$now}),
        ('log-002', 'usr-admin-01', 'LimoMobile Admin', 'Owner', 'LOGIN', 'Admin login via PIN/Email', '127.0.0.1', {$now}),
        ('log-003', 'usr-mgr-01', 'Aamir Shahzad', 'Manager', 'STOCK_UPDATE', 'Added 3x Samsung Galaxy A15 stock', '127.0.0.1', " . ($now - 7200) . ")");
    }

    // Seed sample registered shops if empty
    $shopCount = $pdo->query("SELECT COUNT(*) FROM registered_shops")->fetchColumn();
    if (intval($shopCount) === 0) {
        $todayStr = date('Y-m-d');
        $nextMonth = date('Y-m-d', strtotime('+30 days'));
        $nextYear = date('Y-m-d', strtotime('+365 days'));
        $overdueDate = date('Y-m-d', strtotime('-5 days'));

        $pdo->exec("INSERT INTO registered_shops (id, shop_name, owner_name, phone, email, city, address, pin_code, thermal_size, subscription_plan, subscription_fee, fee_cycle, subscription_status, last_payment_date, next_due_date, license_key, opening_cash, opening_easypaisa, notes, created_at) VALUES
        ('shop-101', 'بلال موبائلز اینڈ ایزی پیسہ فرنچائز', 'بلال احمد خان', '0300-1234567', 'bilal@limopos.com', 'لاہور', 'دکان نمبر 12، حفیظ سینٹر، مین بلیوارڈ، گلبرگ 3، لاہور', '6242', '80mm', 'YEARLY', 24000.00, 'YEARLY', 'ACTIVE', '{$todayStr}', '{$nextYear}', 'LIMO-PK-LHR-9921', 45000.00, 85000.00, 'سالانہ پیکیج فعال - ہیڈ برانچ', " . (time() - 2592000) . "),
        ('shop-102', 'المدینہ سیلولر اینڈ الیکٹرانکس', 'محمد عثمان غنی', '0301-7654321', 'usman@almadina.pk', 'فیصل آباد', 'کوچہ چناب مارکیٹ، کچہری بازار، فیصل آباد', '1122', '80mm', 'MONTHLY', 2500.00, 'MONTHLY', 'ACTIVE', '{$todayStr}', '{$nextMonth}', 'LIMO-PK-FSD-3341', 25000.00, 40000.00, 'ماہانہ سبسکرپشن باقاعدہ پیڈ', " . (time() - 1728000) . "),
        ('shop-103', 'کراچی اسمارٹ فونز اینڈ ٹیلی کام', 'کامران اختر صدیقی', '0321-9988776', 'kamran@khi-smart.com', 'کراچی', 'شاپ نمبر 45، کلفٹن موبائل زون، کراچی', '3344', '58mm', 'MONTHLY', 2500.00, 'MONTHLY', 'DUE', '" . date('Y-m-d', strtotime('-25 days')) . "', '" . date('Y-m-d', strtotime('+3 days')) . "', 'LIMO-PK-KHI-8812', 30000.00, 60000.00, 'فیس تاریخ قریب ہے - واٹس ایپ الرٹ بھیجا گیا', " . (time() - 5184000) . "),
        ('shop-104', 'ثناء اللہ موبائل پوائنٹ و ایزی لوڈ', 'ثناء اللہ چوہدری', '0345-5544332', 'sanaullah@gmail.com', 'راولپنڈی', 'سنگاپور پلازہ، بینک روڈ، صدر راولپنڈی', '7788', '80mm', 'MONTHLY', 2500.00, 'MONTHLY', 'OVERDUE', '" . date('Y-m-d', strtotime('-40 days')) . "', '{$overdueDate}', 'LIMO-PK-RWP-5509', 15000.00, 20000.00, 'پچھلے ماہ کا بقایا - فیس جمع نہیں کروائی گئی', " . (time() - 7776000) . "),
        ('shop-105', 'خیبر موبائل کیئر اینڈ لیب', 'ارشد خان شنواری', '0333-8899001', 'arshad@kpk-mobiles.com', 'پشاور', 'خیبر بازار، کارخانو مارکیٹ، پشاور', '9900', '80mm', 'TRIAL', 0.00, 'TRIAL', 'TRIAL', '', '" . date('Y-m-d', strtotime('+10 days')) . "', 'LIMO-PK-TRL-1004', 10000.00, 15000.00, '14 روزہ فری ٹرائل پر چل رہا ہے', " . (time() - 345600) . ")");
    }

    // Seed sample fee payment records if empty
    $feeCount = $pdo->query("SELECT COUNT(*) FROM shop_fee_payments")->fetchColumn();
    if (intval($feeCount) === 0) {
        $todayStr = date('Y-m-d');
        $prevMonth = date('Y-m-d', strtotime('-1 month'));
        $pdo->exec("INSERT INTO shop_fee_payments (id, shop_id, shop_name, owner_name, phone, amount, payment_date, billing_month_year, period_start, period_end, plan_type, payment_method, trx_id, receipt_no, status, recorded_by, notes, created_at) VALUES
        ('fee-1001', 'shop-101', 'بلال موبائلز اینڈ ایزی پیسہ فرنچائز', 'بلال احمد خان', '0300-1234567', 24000.00, '{$todayStr}', 'Yearly 2026-2027', '{$todayStr}', '" . date('Y-m-d', strtotime('+1 year')) . "', 'YEARLY', 'BANK', 'TRX-MEZN-99120', 'REC-LIMO-001', 'PAID', 'CEO Master Admin', 'میزان بینک آن لائن ٹرانسفر - سالانہ فیس تصدیق شدہ', " . time() . "),
        ('fee-1002', 'shop-102', 'المدینہ سیلولر اینڈ الیکٹرانکس', 'محمد عثمان غنی', '0301-7654321', 2500.00, '{$todayStr}', '" . date('M Y') . "', '{$todayStr}', '" . date('Y-m-d', strtotime('+30 days')) . "', 'MONTHLY', 'EASYPAISA', 'TID-88319024', 'REC-LIMO-002', 'PAID', 'CEO Master Admin', 'ایزی پیسہ مرچنٹ اکاؤنٹ وصولی', " . (time() - 3600) . "),
        ('fee-1003', 'shop-103', 'کراچی اسمارٹ فونز اینڈ ٹیلی کام', 'کامران اختر صدیقی', '0321-9988776', 2500.00, '{$prevMonth}', '" . date('M Y', strtotime('-1 month')) . "', '{$prevMonth}', '" . date('Y-m-d', strtotime('-1 month +30 days')) . "', 'MONTHLY', 'JAZZCASH', 'JC-5501928', 'REC-LIMO-003', 'PAID', 'CEO Master Admin', 'جازکیش سے وصول شدہ', " . (time() - 2592000) . ")");
    }

    // Seed sample landing posts if empty
    $postCount = $pdo->query("SELECT COUNT(*) FROM landing_posts")->fetchColumn();
    if (intval($postCount) === 0) {
        $pdo->exec("INSERT INTO landing_posts (id, title, content, category, image_url, badge_text, action_text, action_url, is_pinned, status, author_name, likes_count, created_at, updated_at) VALUES
        ('post-01', 'لیمو موبائل سافٹ ویئر v2.5 کا شاندار نیا ورژن ریلیز!', 'نئے ورژن میں موبائل خریداری قانونی اقرار نامہ بمعہ کیمرہ فوٹو والٹ، ایزی پیسہ ریئل ٹائم کمیشن کیلکولیٹر اور 80mm تھرمل بارکوڈ پرنٹنگ کی مکمل سہولت شامل کر دی گئی ہے۔', 'ANNOUNCEMENT', 'https://images.unsplash.com/photo-1556742049-0a67c5574f73?w=800&auto=format&fit=crop&q=80', 'تازہ ترین ورژن 2.5', 'نیا اکاؤنٹ بنائیں', '../register_shop/index.php', 1, 'PUBLISHED', 'LimoMobile CEO', 142, " . time() . ", " . time() . "),
        ('post-02', 'موبائل شاپ مالکان کے لیے خصوصی سالانہ ڈسکاؤنٹ آفر!', 'صرف 24,000 روپے میں 12 ماہ کی فل فیچر سبسکرپشن حاصل کریں اور 2 ماہ کی فیس مفت پائیں۔ واٹس ایپ کسٹمر کھاتہ الرٹس اور لائف ٹائم ڈیٹا بیک اپ شامل ہے۔', 'OFFER', 'https://images.unsplash.com/photo-1512941937669-90a1b58e7e9c?w=800&auto=format&fit=crop&q=80', 'محدود وقت آفر', 'ڈسکاؤنٹ حاصل کریں', '../register_shop/index.php', 1, 'PUBLISHED', 'LimoMobile CEO', 89, " . (time() - 86400) . ", " . (time() - 86400) . "),
        ('post-03', 'نیا سیکیورٹی اپ ڈیٹ: شناختی کارڈ اور ڈبل IMEI ویریفکیشن', 'پرانے فون کی خریداری کے وقت چوری شدہ یا بلاک شدہ سیٹس کی روک تھام کے لیے ڈبل IMEI تصدیق اور لائیو کیمرہ شناختی کارڈ اسکینگ ماڈیول فعال ہو چکا ہے۔', 'FEATURE', 'https://images.unsplash.com/photo-1563986768609-322da13575f3?w=800&auto=format&fit=crop&q=80', 'سیکیورٹی الرٹ', 'مزید تفصیلات', '#features', 0, 'PUBLISHED', 'LimoMobile CEO', 64, " . (time() - 172800) . ", " . (time() - 172800) . ")");
    }

    // Seed sample products if SQLite table is empty
    $prodCount = $pdo->query("SELECT COUNT(*) FROM products")->fetchColumn();
    if (intval($prodCount) === 0) {
        $pdo->exec("INSERT INTO products (id, name, category, purchase_price, sale_price, stock, brand_or_model, imei_or_serial, sku, color, ram_storage, units_json, created_at) VALUES
        ('prod-samsung-a15', 'Samsung Galaxy A15 (6GB / 128GB)', 'MOBILES', 35500.00, 38500.00, 3, 'Samsung', '356789123456781', 'SAM-A15-BLK', 'Black', '6GB/128GB', '[{\"id\":\"u1\",\"imei1\":\"356789123456781\",\"imei2\":\"356789123456782\",\"color\":\"Black\",\"storageRam\":\"6GB/128GB\",\"condition\":\"Box Pack (New)\",\"status\":\"AVAILABLE\"},{\"id\":\"u2\",\"imei1\":\"356789123456783\",\"imei2\":\"356789123456784\",\"color\":\"Blue\",\"storageRam\":\"6GB/128GB\",\"condition\":\"Box Pack (New)\",\"status\":\"AVAILABLE\"},{\"id\":\"u3\",\"imei1\":\"356789123456785\",\"imei2\":\"356789123456786\",\"color\":\"Silver\",\"storageRam\":\"6GB/128GB\",\"condition\":\"Box Pack (New)\",\"status\":\"AVAILABLE\"}]', 1700000001),
        ('prod-redmi-note13', 'Redmi Note 13 (8GB / 256GB)', 'MOBILES', 41500.00, 44999.00, 2, 'Xiaomi / Redmi', '864231098765431', 'RED-N13-GRN', 'Midnight Black', '8GB/256GB', '[{\"id\":\"u4\",\"imei1\":\"864231098765431\",\"imei2\":\"864231098765432\",\"color\":\"Black\",\"storageRam\":\"8GB/256GB\",\"condition\":\"Box Pack (New)\",\"status\":\"AVAILABLE\"},{\"id\":\"u5\",\"imei1\":\"864231098765433\",\"imei2\":\"864231098765434\",\"color\":\"Green\",\"storageRam\":\"8GB/256GB\",\"condition\":\"Box Pack (New)\",\"status\":\"AVAILABLE\"}]', 1700000002),
        ('prod-infinix-hot40', 'Infinix Hot 40 Pro (8GB / 256GB)', 'MOBILES', 32000.00, 34999.00, 4, 'Infinix', '351234987654321', 'INF-H40-GLD', 'Gold / Black', '8GB/256GB', '[{\"id\":\"u6\",\"imei1\":\"351234987654321\",\"imei2\":\"351234987654322\",\"color\":\"Starlit Black\",\"storageRam\":\"8GB/256GB\",\"condition\":\"Box Pack (New)\",\"status\":\"AVAILABLE\"}]', 1700000003),
        ('prod-charger-25w', 'Samsung 25W Type-C Super Fast Adapter', 'CHARGERS', 850.00, 1450.00, 24, 'Samsung', '', 'CHG-25W-SAM', 'White', '', '[]', 1700000004),
        ('prod-charger-33w', 'Xiaomi / Realme 33W Fast Dart Charger + Cable', 'CHARGERS', 1100.00, 1850.00, 16, 'Xiaomi', '', 'CHG-33W-XIA', 'White', '', '[]', 1700000005),
        ('prod-ronin-r9', 'Ronin R-9 Crystal Clear Bass Handsfree', 'EARPHONES', 350.00, 650.00, 35, 'Ronin', '', 'HF-RONIN-R9', 'Black', '', '[]', 1700000006),
        ('prod-airbuds-tws', 'Audionic Airbud 425 Wireless Bluetooth TWS', 'EARPHONES', 2400.00, 3300.00, 8, 'Audionic', '', 'TWS-AUD-425', 'White', '', '[]', 1700000007),
        ('prod-cable-65w', '6A 65W Braided Fast Type-C Data Cable', 'CABLES', 180.00, 450.00, 40, 'Faster', '', 'CAB-6A-FAST', 'Black/Red', '', '[]', 1700000008),
        ('prod-glass-9d', '9D Curved Full Edge Matte Glass Protector', 'PROTECTORS', 90.00, 300.00, 50, 'Universal', '', 'GLS-9D-MAT', 'Clear', '', '[]', 1700000009),
        ('prod-cover-silicone', 'Premium Silicone Shockproof Camera Protection Cover', 'COVERS', 180.00, 450.00, 30, 'Generic', '', 'CVR-SIL-SHK', 'Assorted', '', '[]', 1700000010)");
    }

    // Seed sample sales if SQLite product_sales table is empty
    $salesCount = $pdo->query("SELECT COUNT(*) FROM product_sales")->fetchColumn();
    if (intval($salesCount) === 0) {
        $pdo->exec("INSERT INTO product_sales (id, invoice_no, date, time, customer_name, customer_phone, total_amount, discount, net_amount, total_purchase_cost, profit, payment_method, paid_amount, due_amount, items_json, notes, created_at) VALUES
        ('sale-demo-001', 'INV-100241', '" . date('Y-m-d') . "', '11:30 AM', 'محمد عثمان', '0301-2345678', 40000.00, 500.00, 39500.00, 35500.00, 4000.00, 'CASH', 39500.00, 0.00, '[{\"productId\":\"prod-samsung-a15\",\"productName\":\"Samsung Galaxy A15 (6GB / 128GB)\",\"quantity\":1,\"unitPurchasePrice\":35500,\"unitSalePrice\":38500,\"totalSalePrice\":38500,\"selectedImei1\":\"356789123456781\",\"selectedColor\":\"Black\"},{\"productId\":\"prod-glass-9d\",\"productName\":\"9D Curved Full Edge Matte Glass Protector\",\"quantity\":1,\"unitPurchasePrice\":90,\"unitSalePrice\":300,\"totalSalePrice\":300},{\"productId\":\"prod-cover-silicone\",\"productName\":\"Premium Silicone Shockproof Camera Protection Cover\",\"quantity\":1,\"unitPurchasePrice\":180,\"unitSalePrice\":450,\"totalSalePrice\":450}]', 'Box pack with glass protector and back cover', " . time() . "),
        ('sale-demo-002', 'INV-100242', '" . date('Y-m-d') . "', '01:15 PM', 'طارق محمود', '0345-9876543', 3300.00, 100.00, 3200.00, 2400.00, 800.00, 'EASYPAISA', 3200.00, 0.00, '[{\"productId\":\"prod-airbuds-tws\",\"productName\":\"Audionic Airbud 425 Wireless Bluetooth TWS\",\"quantity\":1,\"unitPurchasePrice\":2400,\"unitSalePrice\":3300,\"totalSalePrice\":3300}]', 'Paid via EasyPaisa', " . time() . "),
        ('sale-demo-003', 'INV-100243', '" . date('Y-m-d', strtotime('-1 day')) . "', '04:45 PM', 'حاجی رشید احمد', '0321-5551234', 45000.00, 1000.00, 44000.00, 41500.00, 2500.00, 'BANK', 44000.00, 0.00, '[{\"productId\":\"prod-redmi-note13\",\"productName\":\"Redmi Note 13 (8GB / 256GB)\",\"quantity\":1,\"unitPurchasePrice\":41500,\"unitSalePrice\":44999,\"totalSalePrice\":44999,\"selectedImei1\":\"864231098765431\",\"selectedColor\":\"Midnight Black\"}]', 'Bank Transfer received', " . (time() - 86400) . "),
        ('sale-demo-004', 'INV-100244', '" . date('Y-m-d', strtotime('-2 days')) . "', '06:20 PM', 'وقار علی خان', '0333-7778899', 3750.00, 150.00, 3600.00, 2130.00, 1470.00, 'CASH', 3600.00, 0.00, '[{\"productId\":\"prod-charger-25w\",\"productName\":\"Samsung 25W Type-C Super Fast Adapter\",\"quantity\":1,\"unitPurchasePrice\":850,\"unitSalePrice\":1450,\"totalSalePrice\":1450},{\"productId\":\"prod-ronin-r9\",\"productName\":\"Ronin R-9 Crystal Clear Bass Handsfree\",\"quantity\":1,\"unitPurchasePrice\":350,\"unitSalePrice\":650,\"totalSalePrice\":650},{\"productId\":\"prod-cable-65w\",\"productName\":\"6A 65W Braided Fast Type-C Data Cable\",\"quantity\":1,\"unitPurchasePrice\":180,\"unitSalePrice\":450,\"totalSalePrice\":450}]', 'Original fast charger combo', " . (time() - 172800) . ")");
    }

    // Seed sample vault photos if vault_photos table is empty
    $vaultCount = $pdo->query("SELECT COUNT(*) FROM vault_photos")->fetchColumn();
    if (intval($vaultCount) === 0) {
        $pdo->exec("INSERT INTO vault_photos (id, title, category, ref_no, imei, customer_name, phone, cnic, filename, file_path, file_size_kb, notes, created_at) VALUES
        ('v-001', 'محمد عثمان - شناختی کارڈ فرنٹ', 'CNIC_FRONT', 'PUR-2024-0091', '356789123456781', 'محمد عثمان', '0301-2345678', '35202-1234567-1', 'sample_cnic_front.svg', 'uploads/sample_cnic_front.svg', 42.5, 'اصل سمارٹ شناختی کارڈ فرنٹ ویریفکیشن', " . (time() - 3600) . "),
        ('v-002', 'محمد عثمان - شناختی کارڈ بیک (پتہ)', 'CNIC_BACK', 'PUR-2024-0091', '356789123456781', 'محمد عثمان', '0301-2345678', '35202-1234567-1', 'sample_cnic_back.svg', 'uploads/sample_cnic_back.svg', 38.2, 'شناختی کارڈ بیک سائیڈ بارکوڈ مع رہائشی پتہ', " . (time() - 3500) . "),
        ('v-003', 'محمد عثمان - سیلر فوٹو (دکان کیمرہ)', 'SELLER', 'PUR-2024-0091', '356789123456781', 'محمد عثمان', '0301-2345678', '35202-1234567-1', 'sample_seller_photo.svg', 'uploads/sample_seller_photo.svg', 45.0, 'موبائل فروخت کے وقت لائیو چہرہ تصویر', " . (time() - 3400) . "),
        ('v-004', 'Samsung Galaxy A15 - IMEI اسٹیکر و باڈی', 'MOBILE', 'PUR-2024-0091', '356789123456781', 'محمد عثمان', '0301-2345678', '35202-1234567-1', 'sample_mobile_imei.svg', 'uploads/sample_mobile_imei.svg', 52.8, 'فون کنڈیشن 10/10 اور بیک IMEI اسٹیکر چیک', " . (time() - 3300) . "),
        ('v-005', 'Redmi Note 13 - 1 سالہ وارنٹی رسید سلپ', 'RECEIPT', 'REC-2024-8841', '864231098765431', 'حاجی رشید احمد', '0321-5551234', '35201-9876543-3', 'sample_warranty_slip.svg', 'uploads/sample_warranty_slip.svg', 36.4, 'آفیشل کمپنی وارنٹی کلیم سلپ مع شاپ مہر', " . (time() - 86400) . ")");
    }

    // Seed sample suppliers if suppliers table is empty
    $supCount = $pdo->query("SELECT COUNT(*) FROM suppliers")->fetchColumn();
    if (intval($supCount) === 0) {
        $pdo->exec("INSERT INTO suppliers (id, name, phone, city, address, company_or_market, balance, notes, created_at) VALUES
        ('sup-001', 'المدینہ موبائل ہول سیلرز', '0300-4455667', 'لاہور', 'دکان نمبر 14، بیسمنٹ، حفیظ سینٹر، گلبرگ 3، لاہور', 'حفیظ سینٹر لاہور', 85000.00, 'سام سنگ، ریڈمی، اور انفینکس کے باکس پیک اصل ڈیلر', " . (time() - 259200) . "),
        ('sup-002', 'کراچی سیلولر و ڈسٹری بیوٹرز', '0321-9876543', 'کراچی', 'شاپ نمبر 38، پہلی منزل، اسٹار سٹی مال، صدر، کراچی', 'صدر موبائل مارکیٹ کراچی', 24500.00, 'اصل فاسٹ چارجرز، بیٹریاں، ہینڈز فری اور ایل سی ڈی پینلز', " . (time() - 432000) . "),
        ('sup-003', 'بلال امپورٹرز و ٹریڈرز', '0333-5566778', 'لاہور', 'پلازہ نمبر 5، شاہ عالم مارکیٹ، ہال روڈ، لاہور', 'ہال روڈ الیکٹرانکس مارکیٹ', 12800.00, 'رونن ہینڈز فری، 9D گلاس پروٹیکٹرز، ٹائپ سی کیبلز اور بیک کورز', " . (time() - 345600) . "),
        ('sup-004', 'ثناء اللہ موبائل زون', '0345-1239876', 'راولپنڈی', 'سنگاپور پلازہ، بینک روڈ، صدر، راولپنڈی', 'صدر راولپنڈی', 0.00, 'آئی فون و اینڈرائیڈ استعمال شدہ تصدیق شدہ فونز', " . (time() - 518400) . ")");

        $pdo->exec("INSERT INTO supplier_transactions (id, supplier_id, date, time, type, invoice_no, description, amount, balance_after, payment_method, notes, created_at) VALUES
        ('strx-101', 'sup-001', '" . date('Y-m-d', strtotime('-3 days')) . "', '02:30 PM', 'BILL', 'INV-HF-8801', 'Samsung Galaxy A15 (3 عدد) اور Redmi Note 13 (2 عدد) باکس پیک لاٹ', 125000.00, 125000.00, 'CREDIT', 'نیا خریداری بل درج ہوا', " . (time() - 259000) . "),
        ('strx-102', 'sup-001', '" . date('Y-m-d', strtotime('-1 day')) . "', '05:15 PM', 'PAYMENT', 'TRX-BNK-991', 'بینک الحبیب سے براہ راست اکاؤنٹ ٹرانسفر ادائیگی', 40000.00, 85000.00, 'BANK', 'آن لائن ٹرانسفر تصدیق شدہ', " . (time() - 86400) . "),
        ('strx-103', 'sup-002', '" . date('Y-m-d', strtotime('-5 days')) . "', '11:20 AM', 'BILL', 'INV-KC-4011', 'Samsung 25W Fast Adapters (20 عدد) اور 33W Fast Chargers', 54500.00, 54500.00, 'CREDIT', 'لیدر پارسل بل', " . (time() - 431000) . "),
        ('strx-104', 'sup-002', '" . date('Y-m-d', strtotime('-2 days')) . "', '04:45 PM', 'PAYMENT', 'VOU-CSH-332', 'کیش دراز سے نقد ادائیگی بذریعہ رائیڈر بلٹی کلیرنس', 30000.00, 24500.00, 'CASH', 'رسید واؤچر پر مہر لگوائی گئی', " . (time() - 172800) . "),
        ('strx-105', 'sup-003', '" . date('Y-m-d', strtotime('-4 days')) . "', '01:00 PM', 'BILL', 'INV-HR-3209', 'Ronin R-9 ہینڈز فری (35 عدد) اور 9D گلاس پروٹیکٹرز (50 عدد)', 22800.00, 22800.00, 'CREDIT', 'اسیسریز پیکٹ وصول ہوا', " . (time() - 345000) . "),
        ('strx-106', 'sup-003', '" . date('Y-m-d') . "', '12:10 PM', 'PAYMENT', 'EP-9021841', 'ایزی پیسہ مرچنٹ اکاؤنٹ سے فوری ادائیگی', 10000.00, 12800.00, 'EASYPAISA', 'TID: 90218412351', " . time() . "),
        ('strx-107', 'sup-004', '" . date('Y-m-d', strtotime('-6 days')) . "', '03:30 PM', 'BILL', 'INV-RP-1102', 'Infinix Hot 40 Pro (2 عدد) اسٹاک خریداری', 65000.00, 65000.00, 'CREDIT', 'ہول سیل لاٹ', " . (time() - 518000) . "),
        ('strx-108', 'sup-004', '" . date('Y-m-d', strtotime('-2 days')) . "', '06:00 PM', 'PAYMENT', 'BNK-MEZN-771', 'مکمل بل کی کلیرنس بذریعہ میزان بینک آن لائن', 65000.00, 0.00, 'BANK', 'کھاتہ مکمل نل / صاف ہو گیا', " . (time() - 172000) . ")");
    }

    // Seed sample daily balance if empty
    $dailyCount = $pdo->query("SELECT COUNT(*) FROM daily_balances")->fetchColumn();
    if (intval($dailyCount) === 0) {
        $today = date('Y-m-d');
        $pdo->exec("INSERT INTO daily_balances (date, opening_cash, opening_easypaisa, notes) VALUES 
        ('{$today}', 45000.00, 85000.00, 'صبح کا افتتاحی کیش دراز اور ایزی پیسہ بیلنس')");
    }

    // Seed sample transactions if empty
    $trxCount = $pdo->query("SELECT COUNT(*) FROM transactions")->fetchColumn();
    if (intval($trxCount) === 0) {
        $today = date('Y-m-d');
        $yesterday = date('Y-m-d', strtotime('-1 day'));
        $twoDaysAgo = date('Y-m-d', strtotime('-2 days'));

        $pdo->exec("INSERT INTO transactions (id, date, time, type, customer_name, customer_phone, trx_id, easy_paisa_amount, cash_amount, fee_profit, expense_amount, payment_method, note, created_at) VALUES
        ('trx-101', '{$today}', '10:30 AM', 'BUY_CASH', 'محمد راشد', '0345-8899112', 'TID-90218841', 5000.00, 5060.00, 60.00, 0.00, 'EASYPAISA', 'گاؤں رقم بھیجی (Send Money)', " . (time() - 14400) . "),
        ('trx-102', '{$today}', '11:15 AM', 'SELL_CASH', 'علی رضا', '0302-7711223', 'TID-88319024', 10000.00, 9800.00, 200.00, 0.00, 'EASYPAISA', 'رقم نکلوائی (Cash Out)', " . (time() - 11000) . "),
        ('trx-103', '{$today}', '12:45 PM', 'BUY_CASH', 'عثمان غنی', '0321-4455667', 'JC-4410291', 15000.00, 15150.00, 150.00, 0.00, 'JAZZCASH', 'جازکیش فیملی ٹرانسفر', " . (time() - 7200) . "),
        ('trx-104', '{$today}', '02:10 PM', 'EXPENSE', 'دکان چائے و صفائی خرچ', '', 'EXP-TEA-01', 0.00, 350.00, 0.00, 350.00, 'CASH', 'شام کی چائے، بسکٹ اور دکان جھاڑو', " . (time() - 4000) . "),
        ('trx-105', '{$today}', '03:30 PM', 'SELL_CASH', 'بلال احمد', '0333-1122334', 'SADA-88192', 25000.00, 24750.00, 250.00, 0.00, 'SADAPAY', 'ساداپے سے کیش وصولی', " . (time() - 1800) . "),
        ('trx-106', '{$yesterday}', '01:20 PM', 'BUY_CASH', 'حاجی نذیر احمد', '0300-5544332', 'BILL-EP-7719', 8450.00, 8480.00, 30.00, 0.00, 'EASYPAISA', 'لیسکو بجلی بل ادائیگی (Consumer: 08112345678900U)', " . (time() - 95000) . "),
        ('trx-107', '{$yesterday}', '04:50 PM', 'SELL_CASH', 'ندیم اختر', '0315-6677889', 'TID-1002934', 4000.00, 3920.00, 80.00, 0.00, 'EASYPAISA', 'ایزی پیسہ کیش آؤٹ', " . (time() - 83000) . "),
        ('trx-108', '{$twoDaysAgo}', '05:00 PM', 'BUY_CASH', 'کامران خان', '0312-9988776', 'LOAD-ZONG-01', 1000.00, 1000.00, 35.00, 0.00, 'EASYPAISA', 'زونگ سپر کارڈ ایزی لوڈ', " . (time() - 180000) . ")");
    }
}

// Attempt MySQL connection first
if ($DB_PASS !== 'AapKaVpanelPassword' || $DB_HOST !== 'localhost') {
    try {
        $dsn = "mysql:host={$DB_HOST};port={$DB_PORT};dbname={$DB_NAME};charset=utf8mb4";
        $pdo = new PDO($dsn, $DB_USER, $DB_PASS, [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
            PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci"
        ]);
    } catch (PDOException $e) {
        $pdo = null;
    }
}

// If MySQL is not available, fallback to SQLite
if (!$pdo) {
    try {
        $sqliteFile = __DIR__ . '/local_store.sqlite';
        $pdo = new PDO("sqlite:" . $sqliteFile, null, null, [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
        ]);
        initSqliteTables($pdo);
    } catch (Exception $e) {
        die("Database error: " . $e->getMessage());
    }
}

// Function to fetch all shop settings as array
function getShopSettings($pdo) {
    try {
        $stmt = $pdo->query("SELECT setting_value FROM app_settings WHERE setting_key = 'shop_config'");
        $row = $stmt->fetch();
        if ($row && !empty($row['setting_value'])) {
            return json_decode($row['setting_value'], true);
        }
    } catch (Exception $e) {}
    return [
        'shopName' => 'LimoMobile POS & EasyPaisa Shop',
        'ownerName' => 'LimoMobile Admin',
        'phone' => '0300-1234567',
        'address' => 'Main Market, Mobile Plaza, Pakistan',
        'pinCode' => '6242',
        'language' => 'en',
        'theme' => 'light'
    ];
}

// Function to fetch a single shop setting by key
function getShopSetting($pdo, $key, $default = '') {
    if (!$pdo) return $default;
    try {
        $stmt = $pdo->prepare("SELECT setting_value FROM app_settings WHERE setting_key = :k LIMIT 1");
        $stmt->execute([':k' => $key]);
        $val = $stmt->fetchColumn();
        if ($val !== false && $val !== null) {
            return $val;
        }
    } catch (Exception $e) {}
    return $default;
}

// Save a single setting by key
function saveShopSetting($pdo, $key, $value) {
    if (!$pdo) return false;
    try {
        $valStr = is_array($value) ? json_encode($value, JSON_UNESCAPED_UNICODE) : strval($value);
        $stmt = $pdo->prepare("INSERT INTO app_settings (setting_key, setting_value) VALUES (:k, :val) ON CONFLICT(setting_key) DO UPDATE SET setting_value = :val");
        return $stmt->execute([':k' => $key, ':val' => $valStr]);
    } catch (Exception $e) {
        try {
            // MySQL fallback syntax (REPLACE INTO)
            $valStr = is_array($value) ? json_encode($value, JSON_UNESCAPED_UNICODE) : strval($value);
            $stmt = $pdo->prepare("REPLACE INTO app_settings (setting_key, setting_value) VALUES (:k, :val)");
            return $stmt->execute([':k' => $key, ':val' => $valStr]);
        } catch (Exception $ex) {
            return false;
        }
    }
}

// Save all shop settings array
function saveShopSettings($pdo, $settingsArray) {
    return saveShopSetting($pdo, 'shop_config', $settingsArray);
}

// User Activity Logger
function logUserActivity($pdo, $action, $details = '', $userId = null, $userName = null, $role = null) {
    if (!$pdo) return;
    try {
        if (!$userId && isset($_SESSION['user'])) {
            $userId = $_SESSION['user']['id'] ?? 'guest';
            $userName = $_SESSION['user']['name'] ?? 'Guest';
            $role = $_SESSION['user']['role'] ?? 'Staff';
        }
        $logId = 'log-' . microtime(true) . '-' . rand(100, 999);
        $ip = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
        $stmt = $pdo->prepare("INSERT INTO user_activity_logs (id, user_id, user_name, role, action, details, ip_address, created_at) VALUES (:id, :user_id, :user_name, :role, :action, :details, :ip, :created_at)");
        $stmt->execute([
            ':id' => $logId,
            ':user_id' => $userId ?? 'unknown',
            ':user_name' => $userName ?? 'Unknown',
            ':role' => $role ?? 'Staff',
            ':action' => strtoupper($action),
            ':details' => $details,
            ':ip' => $ip,
            ':created_at' => time()
        ]);
    } catch (Exception $e) {
        // Logging error should not break app
    }
}

// User Authentication Helpers
function isUserLoggedIn() {
    return isset($_SESSION['user']) && !empty($_SESSION['user']['id']);
}

function getLoggedInUser() {
    return $_SESSION['user'] ?? null;
}

function isCeoAdmin() {
    if (!isUserLoggedIn()) return false;
    $user = $_SESSION['user'] ?? [];
    $role = $user['role'] ?? '';
    $email = strtolower($user['email'] ?? '');
    return ($role === 'SuperAdmin' || $role === 'CEO' || $email === 'admin@limopos.com' || in_array('ceo', $user['permissions'] ?? []));
}

function hasUserPermission($permissionName) {
    if (!isUserLoggedIn()) return false;
    $role = $_SESSION['user']['role'] ?? 'Staff';
    if ($role === 'Owner' || $role === 'SuperAdmin' || $role === 'Admin') {
        return true;
    }
    $permissions = $_SESSION['user']['permissions'] ?? [];
    if (in_array('all', $permissions)) return true;
    return in_array($permissionName, $permissions);
}

function loginUserSession($userData, $pdo = null) {
    if (session_status() === PHP_SESSION_ACTIVE) {
        session_regenerate_id(true);
    }
    $perms = $userData['permissions'] ?? '[]';
    if (is_string($perms)) {
        $decoded = json_decode($perms, true);
        $perms = is_array($decoded) ? $decoded : ['pos', 'inventory', 'easypaisa'];
    }
    if ($userData['role'] === 'Owner' || $userData['role'] === 'Admin') {
        $perms = ['all', 'pos', 'inventory', 'purchases', 'khata', 'easypaisa', 'reports', 'settings', 'admin'];
    }
    if ($userData['role'] === 'SuperAdmin' || $userData['role'] === 'CEO' || strtolower($userData['email'] ?? '') === 'admin@limopos.com') {
        $perms = ['all', 'ceo', 'superadmin', 'admin', 'pos', 'inventory', 'purchases', 'khata', 'easypaisa', 'reports', 'settings'];
    }

    $_SESSION['user'] = [
        'id' => $userData['id'],
        'name' => $userData['name'],
        'email' => $userData['email'],
        'role' => $userData['role'] ?? 'Owner',
        'status' => $userData['status'] ?? 'ACTIVE',
        'permissions' => $perms,
        'shop_name' => $userData['shop_name'] ?? 'LimoMobile POS & EasyPaisa Shop',
        'phone' => $userData['phone'] ?? '',
        'login_time' => time(),
        'last_activity' => time()
    ];

    if ($pdo) {
        try {
            $stmt = $pdo->prepare("UPDATE users SET last_login = :now WHERE id = :id");
            $stmt->execute([':now' => time(), ':id' => $userData['id']]);
            logUserActivity($pdo, 'LOGIN', "Logged in successfully ({$userData['role']})", $userData['id'], $userData['name'], $userData['role']);
        } catch (Exception $e) {}
    }
}

function logoutUserSession($pdo = null) {
    if ($pdo && isset($_SESSION['user'])) {
        logUserActivity($pdo, 'LOGOUT', "User logged out", $_SESSION['user']['id'], $_SESSION['user']['name'], $_SESSION['user']['role']);
    }
    unset($_SESSION['user']);
    if (session_status() === PHP_SESSION_ACTIVE) {
        session_destroy();
    }
}

/**
 * Strict Route Protection Guard
 * If user is not logged in, immediately and irrevocably redirect to Login page
 */
function requireAuth($pdo = null, $requiredRole = null, $requiredPermission = null) {
    if (!isUserLoggedIn()) {
        $currUri = $_SERVER['REQUEST_URI'] ?? '';
        // Determine correct login path relative to current execution context
        $loginUrl = "../login/index.php?auth_required=1";
        if (basename(getcwd()) === 'php_software' || basename(__DIR__) === 'php_software') {
            $loginUrl = "login/index.php?auth_required=1";
        }
        if (!empty($currUri) && !str_contains($currUri, 'login') && !str_contains($currUri, 'logout')) {
            $loginUrl .= "&return=" . urlencode($currUri);
        }
        
        // Triple-enforced redirection (HTTP Header + Meta Refresh + JS Redirect)
        if (!headers_sent()) {
            header("Location: " . $loginUrl);
        }
        echo "<!DOCTYPE html><html><head><meta http-equiv='refresh' content='0;url={$loginUrl}'><script>window.location.replace('{$loginUrl}');</script></head><body style='font-family:sans-serif;padding:40px;text-align:center;'><h3>Authentication Required</h3><p>Redirecting to login portal...</p><p><a href='{$loginUrl}'>Click here if not redirected</a></p></body></html>";
        exit();
    }

    // Verify if user is still active in database
    if ($pdo && isset($_SESSION['user']['id'])) {
        try {
            $stmt = $pdo->prepare("SELECT status, role, permissions FROM users WHERE id = :id LIMIT 1");
            $stmt->execute([':id' => $_SESSION['user']['id']]);
            $u = $stmt->fetch();
            if ($u) {
                if (isset($u['status']) && strtoupper($u['status']) === 'SUSPENDED') {
                    logoutUserSession($pdo);
                    $suspendedUrl = "../login/index.php?error=suspended";
                    if (!headers_sent()) header("Location: " . $suspendedUrl);
                    echo "<script>window.location.replace('{$suspendedUrl}');</script>";
                    exit();
                }
                // Refresh role and permissions in session
                $_SESSION['user']['role'] = $u['role'];
                if (!empty($u['permissions'])) {
                    $decoded = json_decode($u['permissions'], true);
                    if (is_array($decoded)) {
                        $_SESSION['user']['permissions'] = $decoded;
                    }
                }
            }
        } catch (Exception $e) {}
    }

    // Check specific role requirement if given
    if ($requiredRole !== null) {
        $userRole = $_SESSION['user']['role'] ?? 'Staff';
        $allowed = false;
        if ($userRole === 'SuperAdmin' || $userRole === 'CEO') {
            $allowed = true;
        } elseif (is_array($requiredRole)) {
            $allowed = in_array($userRole, $requiredRole) || $userRole === 'Owner';
        } else {
            $allowed = ($userRole === $requiredRole || $userRole === 'Owner');
        }

        if (!$allowed) {
            $deniedUrl = "../dashboard/index.php?access_denied=1";
            if (!headers_sent()) header("Location: " . $deniedUrl);
            echo "<script>window.location.replace('{$deniedUrl}');</script>";
            exit();
        }
    }

    // Check specific permission if given
    if ($requiredPermission !== null && !hasUserPermission($requiredPermission)) {
        $deniedUrl = "../dashboard/index.php?access_denied=1&missing_perm=" . urlencode($requiredPermission);
        if (!headers_sent()) header("Location: " . $deniedUrl);
        echo "<script>window.location.replace('{$deniedUrl}');</script>";
        exit();
    }
}

/**
 * Helper to enforce permission on specific sub-views or actions
 */
function requirePermission($permissionName, $pdo = null) {
    requireAuth($pdo, null, $permissionName);
}

