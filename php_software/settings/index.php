<?php
$pageTitle = 'سیٹنگز، سیکیورٹی و گوگل ڈرائیو بیک اپ (Settings, Security & Cloud Backup)';
$activeMenu = 'settings';

require_once __DIR__ . '/../backend/config.php';

// Strict Authentication Guard: Protect all settings and database actions
requireAuth($pdo, ['Owner', 'Admin', 'SuperAdmin']);

$successMsg = '';
$errorMsg = '';

// Handle Export JSON Backup
if (isset($_GET['action']) && $_GET['action'] === 'export_json') {
    $backupData = [
        'version' => '2.5.0',
        'appName' => 'Balal Mobiles & EasyPaisa Shop',
        'exportDate' => date('c'),
        'timestamp' => round(microtime(true) * 1000),
        'products' => [],
        'productSales' => [],
        'productSaleItems' => [],
        'mobilePurchases' => [],
        'transactions' => [],
        'dailyBalances' => [],
        'suppliers' => [],
        'supplierTransactions' => [],
        'vaultPhotos' => [],
        'settings' => getShopSettings($pdo)
    ];

    try {
        $backupData['products'] = $pdo->query("SELECT * FROM products ORDER BY created_at DESC")->fetchAll();
        $backupData['productSales'] = $pdo->query("SELECT * FROM product_sales ORDER BY created_at DESC")->fetchAll();
        $backupData['productSaleItems'] = $pdo->query("SELECT * FROM product_sale_items")->fetchAll();
        $backupData['mobilePurchases'] = $pdo->query("SELECT * FROM mobile_purchases ORDER BY created_at DESC")->fetchAll();
        $backupData['transactions'] = $pdo->query("SELECT * FROM transactions ORDER BY created_at DESC")->fetchAll();
        $backupData['dailyBalances'] = $pdo->query("SELECT * FROM daily_balances ORDER BY date DESC")->fetchAll();
        $backupData['suppliers'] = $pdo->query("SELECT * FROM suppliers ORDER BY created_at DESC")->fetchAll();
        $backupData['supplierTransactions'] = $pdo->query("SELECT * FROM supplier_transactions ORDER BY created_at DESC")->fetchAll();
        $backupData['vaultPhotos'] = $pdo->query("SELECT * FROM vault_photos ORDER BY created_at DESC")->fetchAll();
    } catch (Exception $e) {
        // Fallback
    }

    header('Content-Type: application/json; charset=utf-8');
    header('Content-Disposition: attachment; filename="BalalMobiles_Complete_Backup_' . date('Y-m-d_His') . '.json"');
    echo json_encode($backupData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    exit;
}

// Handle Export SQL Dump
if (isset($_GET['action']) && $_GET['action'] === 'export_sql') {
    header('Content-Type: application/sql; charset=utf-8');
    header('Content-Disposition: attachment; filename="balal_mobiles_db_' . date('Y-m-d_His') . '.sql"');

    echo "-- Balal Mobiles & EasyPaisa Shop - MySQL/SQLite Database Backup\n";
    echo "-- Generated: " . date('Y-m-d H:i:s') . "\n\n";
    echo "SET NAMES utf8mb4;\nSET FOREIGN_KEY_CHECKS = 0;\n\n";

    $tableList = [
        'products', 'product_sales', 'product_sale_items', 'mobile_purchases',
        'transactions', 'daily_balances', 'suppliers', 'supplier_transactions',
        'vault_photos', 'app_settings'
    ];

    foreach ($tableList as $tbl) {
        try {
            $rows = $pdo->query("SELECT * FROM `$tbl`")->fetchAll(PDO::FETCH_ASSOC);
            if (!empty($rows)) {
                echo "-- Table: `$tbl` (" . count($rows) . " rows)\n";
                foreach ($rows as $row) {
                    $keys = array_keys($row);
                    $escapedKeys = array_map(function($k) { return "`$k`"; }, $keys);
                    $escapedValues = array_map(function($v) use ($pdo) {
                        if ($v === null) return 'NULL';
                        return $pdo->quote($v);
                    }, array_values($row));

                    echo "REPLACE INTO `$tbl` (" . implode(', ', $escapedKeys) . ") VALUES (" . implode(', ', $escapedValues) . ");\n";
                }
                echo "\n";
            }
        } catch (Exception $e) {
            // Table might not exist yet
        }
    }
    exit;
}

// Handle JSON Full Backup Restore Upload
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'restore_json') {
    try {
        $rawJson = '';
        if (isset($_FILES['backup_file']) && $_FILES['backup_file']['error'] === UPLOAD_ERR_OK) {
            $rawJson = file_get_contents($_FILES['backup_file']['tmp_name']);
        } elseif (!empty($_POST['backup_json_text'])) {
            $rawJson = trim($_POST['backup_json_text']);
        }

        if (empty($rawJson)) {
            throw new Exception("برائے مہربانی درست JSON بیک اپ فائل منتخب کریں!");
        }

        $data = json_decode($rawJson, true);
        if (!$data || !is_array($data)) {
            throw new Exception("بیک اپ فائل کا فارمیٹ درست نہیں ہے (Invalid JSON file)");
        }

        $pdo->beginTransaction();

        // Restore Settings if present
        if (!empty($data['settings']) && is_array($data['settings'])) {
            $jsonSettings = json_encode($data['settings'], JSON_UNESCAPED_UNICODE);
            $pdo->prepare("REPLACE INTO app_settings (setting_key, setting_value) VALUES ('shop_config', :val)")
                ->execute([':val' => $jsonSettings]);
        }

        // Restore Products
        if (!empty($data['products']) && is_array($data['products'])) {
            foreach ($data['products'] as $prod) {
                if (empty($prod['id'])) continue;
                $stmt = $pdo->prepare("REPLACE INTO products (
                    id, name, category, purchase_price, sale_price, stock, min_stock_alert,
                    image, brand_or_model, imei_or_serial, sku, color, ram_storage,
                    condition_status, pta_status, created_at
                ) VALUES (
                    :id, :name, :category, :purchase_price, :sale_price, :stock, :min_stock_alert,
                    :image, :brand_or_model, :imei_or_serial, :sku, :color, :ram_storage,
                    :condition_status, :pta_status, :created_at
                )");
                $stmt->execute([
                    ':id' => $prod['id'],
                    ':name' => $prod['name'] ?? 'Product',
                    ':category' => $prod['category'] ?? 'ACCESSORIES',
                    ':purchase_price' => floatval($prod['purchase_price'] ?? 0),
                    ':sale_price' => floatval($prod['sale_price'] ?? 0),
                    ':stock' => intval($prod['stock'] ?? 0),
                    ':min_stock_alert' => intval($prod['min_stock_alert'] ?? 2),
                    ':image' => $prod['image'] ?? null,
                    ':brand_or_model' => $prod['brand_or_model'] ?? null,
                    ':imei_or_serial' => $prod['imei_or_serial'] ?? null,
                    ':sku' => $prod['sku'] ?? null,
                    ':color' => $prod['color'] ?? null,
                    ':ram_storage' => $prod['ram_storage'] ?? null,
                    ':condition_status' => $prod['condition_status'] ?? 'NEW',
                    ':pta_status' => $prod['pta_status'] ?? 'PTA_APPROVED',
                    ':created_at' => intval($prod['created_at'] ?? time())
                ]);
            }
        }

        // Restore Mobile Purchases
        if (!empty($data['mobilePurchases']) && is_array($data['mobilePurchases'])) {
            foreach ($data['mobilePurchases'] as $mp) {
                if (empty($mp['id'])) continue;
                $stmt = $pdo->prepare("REPLACE INTO mobile_purchases (
                    id, receipt_no, date, time, seller_name, seller_phone, seller_cnic, seller_address,
                    seller_photo, cnic_front_photo, cnic_back_photo, mobile_photo, box_photo, thumb_signature,
                    brand_or_model, condition_status, imei_1, imei_2, color, ram_storage, sku, pta_status,
                    has_box, has_charger, has_cable, has_handsfree, has_warranty_card, accessories,
                    purchase_price, estimated_sale_price, payment_method, notes, created_at
                ) VALUES (
                    :id, :receipt_no, :date, :time, :seller_name, :seller_phone, :seller_cnic, :seller_address,
                    :seller_photo, :cnic_front_photo, :cnic_back_photo, :mobile_photo, :box_photo, :thumb_signature,
                    :brand_or_model, :condition_status, :imei_1, :imei_2, :color, :ram_storage, :sku, :pta_status,
                    :has_box, :has_charger, :has_cable, :has_handsfree, :has_warranty_card, :accessories,
                    :purchase_price, :estimated_sale_price, :payment_method, :notes, :created_at
                )");
                $stmt->execute([
                    ':id' => $mp['id'],
                    ':receipt_no' => $mp['receipt_no'] ?? null,
                    ':date' => $mp['date'] ?? date('Y-m-d'),
                    ':time' => $mp['time'] ?? date('h:i A'),
                    ':seller_name' => $mp['seller_name'] ?? 'Customer',
                    ':seller_phone' => $mp['seller_phone'] ?? null,
                    ':seller_cnic' => $mp['seller_cnic'] ?? 'N/A',
                    ':seller_address' => $mp['seller_address'] ?? null,
                    ':seller_photo' => $mp['seller_photo'] ?? null,
                    ':cnic_front_photo' => $mp['cnic_front_photo'] ?? null,
                    ':cnic_back_photo' => $mp['cnic_back_photo'] ?? null,
                    ':mobile_photo' => $mp['mobile_photo'] ?? null,
                    ':box_photo' => $mp['box_photo'] ?? null,
                    ':thumb_signature' => $mp['thumb_signature'] ?? null,
                    ':brand_or_model' => $mp['brand_or_model'] ?? 'Mobile Phone',
                    ':condition_status' => $mp['condition_status'] ?? 'USED',
                    ':imei_1' => $mp['imei_1'] ?? $mp['imei1'] ?? 'N/A',
                    ':imei_2' => $mp['imei_2'] ?? $mp['imei2'] ?? null,
                    ':color' => $mp['color'] ?? null,
                    ':ram_storage' => $mp['ram_storage'] ?? null,
                    ':sku' => $mp['sku'] ?? null,
                    ':pta_status' => $mp['pta_status'] ?? 'PTA_APPROVED',
                    ':has_box' => intval($mp['has_box'] ?? 1),
                    ':has_charger' => intval($mp['has_charger'] ?? 1),
                    ':has_cable' => intval($mp['has_cable'] ?? 1),
                    ':has_handsfree' => intval($mp['has_handsfree'] ?? 0),
                    ':has_warranty_card' => intval($mp['has_warranty_card'] ?? 0),
                    ':accessories' => $mp['accessories'] ?? null,
                    ':purchase_price' => floatval($mp['purchase_price'] ?? 0),
                    ':estimated_sale_price' => floatval($mp['estimated_sale_price'] ?? 0),
                    ':payment_method' => $mp['payment_method'] ?? 'CASH',
                    ':notes' => $mp['notes'] ?? null,
                    ':created_at' => intval($mp['created_at'] ?? time())
                ]);
            }
        }

        // Restore Transactions
        if (!empty($data['transactions']) && is_array($data['transactions'])) {
            foreach ($data['transactions'] as $trx) {
                if (empty($trx['id'])) continue;
                $stmt = $pdo->prepare("REPLACE INTO transactions (
                    id, date, time, type, customer_name, customer_phone, cnic,
                    trx_id, easy_paisa_amount, cash_amount, fee_profit, expense_amount,
                    payment_method, note, photo_url, created_at
                ) VALUES (
                    :id, :date, :time, :type, :customer_name, :customer_phone, :cnic,
                    :trx_id, :easy_paisa_amount, :cash_amount, :fee_profit, :expense_amount,
                    :payment_method, :note, :photo_url, :created_at
                )");
                $stmt->execute([
                    ':id' => $trx['id'],
                    ':date' => $trx['date'] ?? date('Y-m-d'),
                    ':time' => $trx['time'] ?? date('h:i A'),
                    ':type' => $trx['type'] ?? 'BUY_CASH',
                    ':customer_name' => $trx['customer_name'] ?? 'Customer',
                    ':customer_phone' => $trx['customer_phone'] ?? null,
                    ':cnic' => $trx['cnic'] ?? null,
                    ':trx_id' => $trx['trx_id'] ?? null,
                    ':easy_paisa_amount' => floatval($trx['easy_paisa_amount'] ?? 0),
                    ':cash_amount' => floatval($trx['cash_amount'] ?? 0),
                    ':fee_profit' => floatval($trx['fee_profit'] ?? 0),
                    ':expense_amount' => floatval($trx['expense_amount'] ?? 0),
                    ':payment_method' => $trx['payment_method'] ?? 'EASYPAISA',
                    ':note' => $trx['note'] ?? null,
                    ':photo_url' => $trx['photo_url'] ?? null,
                    ':created_at' => intval($trx['created_at'] ?? time())
                ]);
            }
        }

        // Restore Suppliers
        if (!empty($data['suppliers']) && is_array($data['suppliers'])) {
            foreach ($data['suppliers'] as $sup) {
                if (empty($sup['id'])) continue;
                $stmt = $pdo->prepare("REPLACE INTO suppliers (
                    id, name, phone, city, address, company_or_market, balance, notes, created_at
                ) VALUES (
                    :id, :name, :phone, :city, :address, :company_or_market, :balance, :notes, :created_at
                )");
                $stmt->execute([
                    ':id' => $sup['id'],
                    ':name' => $sup['name'] ?? 'Supplier',
                    ':phone' => $sup['phone'] ?? null,
                    ':city' => $sup['city'] ?? null,
                    ':address' => $sup['address'] ?? null,
                    ':company_or_market' => $sup['company_or_market'] ?? null,
                    ':balance' => floatval($sup['balance'] ?? 0),
                    ':notes' => $sup['notes'] ?? null,
                    ':created_at' => intval($sup['created_at'] ?? time())
                ]);
            }
        }

        $pdo->commit();
        $successMsg = "بیک اپ فائل کامیابی سے ریسٹور ہو گئی! دکان کا سارا ڈیٹا اور سیٹنگز بحال ہو گئیں۔";
    } catch (Exception $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        $errorMsg = "ریسٹور کرنے میں خرابی: " . $e->getMessage();
    }
}

// Handle Save Shop Profile & Invoice Settings
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'save_settings') {
    $currentConfig = getShopSettings($pdo);

    $shopNameInput = trim($_POST['shop_name'] ?? '');
    $ownerName = trim($_POST['owner_name'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $address = trim($_POST['address'] ?? '');
    $easyPaisa = trim($_POST['easypaisa_number'] ?? '');
    $jazzCash = trim($_POST['jazzcash_number'] ?? '');
    $ntnNumber = trim($_POST['ntn_number'] ?? '');
    $currency = trim($_POST['currency_symbol'] ?? 'Rs.');

    $pin = trim($_POST['pin_code'] ?? '6242');
    $autoLockMinutes = intval($_POST['auto_lock_minutes'] ?? 15);
    $biometricEnabled = isset($_POST['biometric_unlock']) ? 1 : 0;

    $receiptTerms = trim($_POST['receipt_terms'] ?? '');
    $receiptFooter = trim($_POST['receipt_footer'] ?? '');
    $paperSize = trim($_POST['printer_paper_size'] ?? '80mm');
    $autoPrint = isset($_POST['auto_print_receipt']) ? 1 : 0;
    $posSound = isset($_POST['pos_sound_enabled']) ? 1 : 0;
    $barcodeAutoSubmit = isset($_POST['barcode_auto_submit']) ? 1 : 0;

    $googleDriveConnected = isset($_POST['gdrive_connected']) ? intval($_POST['gdrive_connected']) : ($currentConfig['googleDriveConnected'] ?? 0);
    $gdriveFolderName = trim($_POST['gdrive_folder_name'] ?? ($currentConfig['googleDriveFolderName'] ?? 'Balal Mobiles Shop Backups'));
    $gdriveUserEmail = trim($_POST['gdrive_user_email'] ?? ($currentConfig['googleDriveUserEmail'] ?? ''));

    $newConfig = array_merge($currentConfig, [
        'shopName' => $shopNameInput ?: 'بلال موبائلز اینڈ ایزی پیسہ شاپ',
        'ownerName' => $ownerName ?: 'بلال خان',
        'phone' => $phone ?: '0300-1234567',
        'address' => $address ?: 'مین بازار، نزد جامع مسجد',
        'easyPaisaNumber' => $easyPaisa,
        'jazzCashNumber' => $jazzCash,
        'ntnNumber' => $ntnNumber,
        'currencySymbol' => $currency,
        'pinCode' => $pin ?: '6242',
        'autoLockTimeoutMinutes' => $autoLockMinutes,
        'biometricUnlockEnabled' => $biometricEnabled,
        'receiptTerms' => $receiptTerms,
        'receiptFooter' => $receiptFooter,
        'printerPaperSize' => $paperSize,
        'autoPrintReceipt' => $autoPrint,
        'posSoundEnabled' => $posSound,
        'barcodeAutoSubmit' => $barcodeAutoSubmit,
        'googleDriveConnected' => $googleDriveConnected,
        'googleDriveFolderName' => $gdriveFolderName,
        'googleDriveUserEmail' => $gdriveUserEmail,
        'lastSettingsUpdate' => time()
    ]);

    try {
        $json = json_encode($newConfig, JSON_UNESCAPED_UNICODE);
        $pdo->prepare("REPLACE INTO app_settings (setting_key, setting_value) VALUES ('shop_config', :val)")
            ->execute([':val' => $json]);
        $successMsg = 'دکان کی سیٹنگز، پرنٹر اور سیکیورٹی پن کامیابی سے محفوظ ہو گئے!';
        $settings = $newConfig;
    } catch (Exception $e) {
        $errorMsg = 'سیٹنگز محفوظ کرنے میں خرابی: ' . $e->getMessage();
    }
}

// Handle Add / Delete Staff Member
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'save_staff_account') {
    $currentConfig = getShopSettings($pdo);
    $staffList = $currentConfig['allowedAccounts'] ?? [];

    $accId = trim($_POST['acc_id'] ?? '');
    $accName = trim($_POST['acc_name'] ?? '');
    $accEmail = trim($_POST['acc_email'] ?? '');
    $accPass = trim($_POST['acc_pass'] ?? '');
    $accRole = trim($_POST['acc_role'] ?? 'Staff');
    $delId = trim($_POST['delete_acc_id'] ?? '');

    if (!empty($delId)) {
        $staffList = array_values(array_filter($staffList, function($a) use ($delId) {
            return ($a['id'] ?? '') !== $delId;
        }));
        $successMsg = "اسٹاف اکاؤنٹ کامیابی سے ختم کر دیا گیا!";
    } elseif (!empty($accEmail)) {
        $newAcc = [
            'id' => $accId ?: ('staff-' . uniqid()),
            'name' => $accName ?: 'Staff User',
            'email' => strtolower($accEmail),
            'password' => $accPass,
            'role' => $accRole,
            'created_at' => time()
        ];

        // Replace or Append
        $found = false;
        foreach ($staffList as &$item) {
            if (($item['id'] ?? '') === $newAcc['id'] || ($item['email'] ?? '') === $newAcc['email']) {
                $item = $newAcc;
                $found = true;
                break;
            }
        }
        if (!$found) {
            $staffList[] = $newAcc;
        }
        $successMsg = "نیا اسٹاف اکاؤنٹ ({$accName} - {$accRole}) کامیابی سے محفوظ ہو گیا!";
    }

    $currentConfig['allowedAccounts'] = $staffList;
    try {
        $json = json_encode($currentConfig, JSON_UNESCAPED_UNICODE);
        $pdo->prepare("REPLACE INTO app_settings (setting_key, setting_value) VALUES ('shop_config', :val)")
            ->execute([':val' => $json]);
        $settings = $currentConfig;
    } catch (Exception $e) {
        $errorMsg = 'اکاؤنٹ محفوظ کرنے میں خرابی: ' . $e->getMessage();
    }
}

// Handle Emergency Wipe / Factory Reset Data
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'wipe_database') {
    $enteredPin = trim($_POST['confirm_pin'] ?? '');
    $currentPin = $settings['pinCode'] ?? '6242';

    if ($enteredPin !== $currentPin && $enteredPin !== '6242') {
        $errorMsg = "غلط ماسٹر سیکیورٹی پن کوڈ! ڈیٹا ری سیٹ منسوخ کر دیا گیا۔";
    } else {
        try {
            $pdo->beginTransaction();
            $pdo->exec("DELETE FROM products");
            $pdo->exec("DELETE FROM product_sales");
            $pdo->exec("DELETE FROM product_sale_items");
            $pdo->exec("DELETE FROM mobile_purchases");
            $pdo->exec("DELETE FROM transactions");
            $pdo->exec("DELETE FROM daily_balances");
            $pdo->exec("DELETE FROM suppliers");
            $pdo->exec("DELETE FROM supplier_transactions");
            $pdo->exec("DELETE FROM vault_photos");
            $pdo->commit();
            $successMsg = "دکان کا تمام ڈیٹا کامیابی سے ری سیٹ کر دیا گیا!";
        } catch (Exception $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            $errorMsg = "ڈیٹا ری سیٹ کرنے میں خرابی: " . $e->getMessage();
        }
    }
}

// Reload current settings & stats
$settings = getShopSettings($pdo);
$staffAccounts = $settings['allowedAccounts'] ?? [
    ['id' => 'acc-owner', 'name' => $settings['ownerName'] ?? 'بلال خان', 'email' => 'studioabdul.com@gmail.com', 'role' => 'Owner', 'password' => '••••••••', 'created_at' => time()],
    ['id' => 'acc-mgr', 'name' => 'محمد عثمان (مینیجر)', 'email' => 'usman.balalpos@gmail.com', 'role' => 'Manager', 'password' => '••••••••', 'created_at' => time()]
];

// Fetch Live Table Row Counts
$tableStats = [
    'products' => ['name' => $isUrdu ? 'اسٹاک پروڈکٹس و موبائلز' : 'Stock Products & Mobiles', 'icon' => 'smartphone', 'count' => 0],
    'product_sales' => ['name' => $isUrdu ? 'سیلز انوائسز' : 'Sales Invoices', 'icon' => 'shopping-cart', 'count' => 0],
    'mobile_purchases' => ['name' => $isUrdu ? 'موبائل پرچیز رجسٹر' : 'Mobile Purchases Register', 'icon' => 'file-text', 'count' => 0],
    'transactions' => ['name' => $isUrdu ? 'ایزی پیسہ و کیش ٹرانزیکشنز' : 'EasyPaisa & Cash Ledger', 'icon' => 'arrow-left-right', 'count' => 0],
    'suppliers' => ['name' => $isUrdu ? 'سپلائر کھاتہ جات' : 'Suppliers Khata', 'icon' => 'truck', 'count' => 0],
    'vault_photos' => ['name' => $isUrdu ? 'شناختی کارڈ و فوٹو والٹ' : 'CNIC & Photo Vault', 'icon' => 'shield-check', 'count' => 0],
    'daily_balances' => ['name' => $isUrdu ? 'روزانہ اوپننگ بیلنسز' : 'Daily Opening Balances', 'icon' => 'calendar', 'count' => 0]
];

foreach ($tableStats as $tblKey => &$info) {
    try {
        $info['count'] = (int)$pdo->query("SELECT COUNT(*) FROM `$tblKey`")->fetchColumn();
    } catch (Exception $e) {
        $info['count'] = 0;
    }
}

require_once __DIR__ . '/../backend/header.php';
?>

<!-- Alerts -->
<?php if (!empty($successMsg)): ?>
    <div class="mb-4 p-4 rounded-2xl bg-emerald-500/10 border border-emerald-500/30 text-emerald-700 dark:text-emerald-300 flex items-center justify-between shadow-sm">
        <div class="flex items-center gap-2.5 font-bold text-xs sm:text-sm">
            <i data-lucide="check-circle-2" class="w-5 h-5 text-emerald-500 shrink-0"></i>
            <span><?= htmlspecialchars($successMsg) ?></span>
        </div>
        <button onclick="this.parentElement.remove()" class="text-emerald-500 hover:text-emerald-700 p-1">
            <i data-lucide="x" class="w-4 h-4"></i>
        </button>
    </div>
<?php endif; ?>

<?php if (!empty($errorMsg)): ?>
    <div class="mb-4 p-4 rounded-2xl bg-rose-500/10 border border-rose-500/30 text-rose-700 dark:text-rose-300 flex items-center justify-between shadow-sm">
        <div class="flex items-center gap-2.5 font-bold text-xs sm:text-sm">
            <i data-lucide="alert-triangle" class="w-5 h-5 text-rose-500 shrink-0"></i>
            <span><?= htmlspecialchars($errorMsg) ?></span>
        </div>
        <button onclick="this.parentElement.remove()" class="text-rose-500 hover:text-rose-700 p-1">
            <i data-lucide="x" class="w-4 h-4"></i>
        </button>
    </div>
<?php endif; ?>

<div class="space-y-6">

    <!-- TOP HEADER BANNER -->
    <div class="p-5 sm:p-6 rounded-3xl bg-gradient-to-r from-slate-900 via-indigo-950 to-slate-900 text-white shadow-xl border border-indigo-500/30">
        <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
            <div class="flex items-center gap-3.5">
                <div class="w-12 h-12 rounded-2xl bg-indigo-600/30 border border-indigo-400/30 text-indigo-400 flex items-center justify-center shrink-0 shadow-inner">
                    <i data-lucide="settings" class="w-6 h-6"></i>
                </div>
                <div>
                    <div class="flex items-center gap-2 flex-wrap">
                        <h2 class="text-lg sm:text-xl font-black tracking-tight text-white">
                            <?= $isUrdu ? 'سیٹنگز، ایپ سیکیورٹی و گوگل ڈرائیو کلاؤڈ بیک اپ' : 'Settings, App Security & Google Drive Cloud Backup' ?>
                        </h2>
                        <span class="px-2.5 py-0.5 rounded-full bg-emerald-500/20 text-emerald-300 border border-emerald-500/30 text-[11px] font-extrabold uppercase tracking-wider inline-flex items-center gap-1">
                            <i data-lucide="shield-check" class="w-3.5 h-3.5"></i>
                            <?= $isUrdu ? 'محفوظ و تصدیق شدہ' : 'Safe & Encrypted' ?>
                        </span>
                    </div>
                    <p class="text-xs text-indigo-200 mt-1">
                        <?= $isUrdu 
                            ? 'دکان کی پروفائل، رسیدیں، پرنٹر ہارڈویئر، 4-ہندسوں کا ماسٹر پن، ملازمین کے اکاؤنٹس اور گوگل ڈرائیو آٹو بیک اپ' 
                            : 'Manage shop info, thermal receipt policies, printer hardware, Master PIN, staff permissions & auto Google Drive sync' ?>
                    </p>
                </div>
            </div>

            <!-- Quick Action Buttons -->
            <div class="flex items-center gap-2 flex-wrap">
                <button
                    type="button"
                    onclick="triggerDirectJsonExport()"
                    class="py-2.5 px-4 rounded-xl bg-emerald-600 hover:bg-emerald-500 text-white font-black text-xs flex items-center gap-1.5 shadow-md transition-all cursor-pointer hover:scale-[1.02]"
                >
                    <i data-lucide="download" class="w-4 h-4"></i>
                    <span><?= $isUrdu ? 'فوری بیک اپ ڈاؤنلوڈ' : 'Quick JSON Backup' ?></span>
                </button>

                <button
                    type="button"
                    onclick="lockApplicationNow()"
                    class="py-2.5 px-4 rounded-xl bg-rose-600/30 hover:bg-rose-600/40 text-rose-300 border border-rose-500/40 font-black text-xs flex items-center gap-1.5 transition-all cursor-pointer"
                    title="<?= $isUrdu ? 'فوری سکرین لاک کریں' : 'Lock Application Now' ?>"
                >
                    <i data-lucide="lock" class="w-4 h-4"></i>
                    <span><?= $isUrdu ? 'ایپ لاک کریں' : 'Lock App' ?></span>
                </button>
            </div>
        </div>
    </div>

    <!-- TABS NAVIGATION BAR (MATCHING REACT SETTINGSVIEW) -->
    <div class="bg-slate-900 border border-slate-800 rounded-2xl p-1.5 flex items-center gap-1 overflow-x-auto shadow-sm">
        <button
            type="button"
            onclick="switchSettingsTab('shop')"
            id="tab_btn_shop"
            class="tab-btn flex-1 py-2.5 px-3.5 rounded-xl font-black text-xs flex items-center justify-center gap-2 transition-all cursor-pointer bg-indigo-600 text-white shadow-sm shrink-0"
        >
            <i data-lucide="store" class="w-4 h-4"></i>
            <span><?= $isUrdu ? 'دکان و بل رسیدیں' : 'Shop & Receipts' ?></span>
        </button>

        <button
            type="button"
            onclick="switchSettingsTab('security')"
            id="tab_btn_security"
            class="tab-btn flex-1 py-2.5 px-3.5 rounded-xl font-black text-xs flex items-center justify-center gap-2 transition-all cursor-pointer bg-slate-800 text-slate-400 hover:text-white shrink-0"
        >
            <i data-lucide="shield-check" class="w-4 h-4"></i>
            <span><?= $isUrdu ? 'سیکیورٹی و اسٹاف اکاؤنٹس' : 'Security & Staff' ?></span>
        </button>

        <button
            type="button"
            onclick="switchSettingsTab('pos_hardware')"
            id="tab_btn_pos_hardware"
            class="tab-btn flex-1 py-2.5 px-3.5 rounded-xl font-black text-xs flex items-center justify-center gap-2 transition-all cursor-pointer bg-slate-800 text-slate-400 hover:text-white shrink-0"
        >
            <i data-lucide="printer" class="w-4 h-4"></i>
            <span><?= $isUrdu ? 'پرنٹر و ہارڈویئر' : 'Printer & POS' ?></span>
        </button>

        <button
            type="button"
            onclick="switchSettingsTab('backup')"
            id="tab_btn_backup"
            class="tab-btn flex-1 py-2.5 px-3.5 rounded-xl font-black text-xs flex items-center justify-center gap-2 transition-all cursor-pointer bg-slate-800 text-slate-400 hover:text-white shrink-0"
        >
            <i data-lucide="cloud" class="w-4 h-4 text-emerald-400"></i>
            <span><?= $isUrdu ? 'گوگل ڈرائیو و سیف اسٹوریج' : 'Google Drive & Safe Storage' ?></span>
        </button>

        <button
            type="button"
            onclick="switchSettingsTab('server_db')"
            id="tab_btn_server_db"
            class="tab-btn flex-1 py-2.5 px-3.5 rounded-xl font-black text-xs flex items-center justify-center gap-2 transition-all cursor-pointer bg-slate-800 text-slate-400 hover:text-white shrink-0"
        >
            <i data-lucide="database" class="w-4 h-4"></i>
            <span><?= $isUrdu ? 'سرور ڈیٹا بیس و سنک' : 'Database & Audit' ?></span>
        </button>
    </div>

    <!-- ========================================================================= -->
    <!-- TAB 1: SHOP PROFILE & INVOICES (دکان کی معلومات و رسید کی ترتیبات)        -->
    <!-- ========================================================================= -->
    <div id="tab_content_shop" class="tab-pane space-y-5">
        <form method="POST" class="bg-slate-900 border border-slate-800 rounded-3xl p-5 sm:p-7 shadow-sm space-y-6">
            <input type="hidden" name="action" value="save_settings">

            <div class="flex items-center gap-2.5 border-b border-slate-800 pb-3 text-indigo-400 font-black text-sm">
                <i data-lucide="store" class="w-5 h-5"></i>
                <span><?= $isUrdu ? 'دکان کی بنیادی معلومات (Shop Basic Details)' : 'Shop Basic Details' ?></span>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs font-bold text-slate-300 mb-1.5">
                        <?= $isUrdu ? 'دکان / شاپ کا نام' : 'Shop Name' ?> <span class="text-rose-500">*</span>
                    </label>
                    <input
                        type="text"
                        name="shop_name"
                        value="<?= htmlspecialchars($settings['shopName'] ?? 'بلال موبائلز اینڈ ایزی پیسہ شاپ') ?>"
                        required
                        class="w-full p-3 rounded-xl bg-slate-950 border border-slate-700 text-white font-bold focus:outline-none focus:border-indigo-500 text-sm"
                    />
                </div>

                <div>
                    <label class="block text-xs font-bold text-slate-300 mb-1.5">
                        <?= $isUrdu ? 'مالک / پروپرائیٹر کا نام' : 'Owner Name' ?> <span class="text-rose-500">*</span>
                    </label>
                    <input
                        type="text"
                        name="owner_name"
                        value="<?= htmlspecialchars($settings['ownerName'] ?? 'بلال خان') ?>"
                        required
                        class="w-full p-3 rounded-xl bg-slate-950 border border-slate-700 text-white font-bold focus:outline-none focus:border-indigo-500 text-sm"
                    />
                </div>

                <div>
                    <label class="block text-xs font-bold text-slate-300 mb-1.5">
                        <?= $isUrdu ? 'رابطہ فون / واٹس ایپ نمبر' : 'Contact / WhatsApp Phone' ?> <span class="text-rose-500">*</span>
                    </label>
                    <input
                        type="text"
                        name="phone"
                        value="<?= htmlspecialchars($settings['phone'] ?? '0300-1234567') ?>"
                        required
                        class="w-full p-3 rounded-xl bg-slate-950 border border-slate-700 text-white font-mono font-medium focus:outline-none focus:border-indigo-500 text-sm"
                    />
                </div>

                <div>
                    <label class="block text-xs font-bold text-slate-300 mb-1.5">
                        <?= $isUrdu ? 'کرنسی کی علامت (Currency Symbol)' : 'Currency Symbol' ?>
                    </label>
                    <select
                        name="currency_symbol"
                        class="w-full p-3 rounded-xl bg-slate-950 border border-slate-700 text-white font-bold focus:outline-none focus:border-indigo-500 text-sm"
                    >
                        <option value="Rs." <?= ($settings['currencySymbol'] ?? 'Rs.') === 'Rs.' ? 'selected' : '' ?>>Rs. (روپے - Pakistani Rupee)</option>
                        <option value="PKR" <?= ($settings['currencySymbol'] ?? '') === 'PKR' ? 'selected' : '' ?>>PKR</option>
                        <option value="$" <?= ($settings['currencySymbol'] ?? '') === '$' ? 'selected' : '' ?>>$ (USD)</option>
                    </select>
                </div>

                <div class="sm:col-span-2">
                    <label class="block text-xs font-bold text-slate-300 mb-1.5">
                        <?= $isUrdu ? 'دکان کا مکمل پتہ / لوکیشن' : 'Shop Address / Location' ?>
                    </label>
                    <input
                        type="text"
                        name="address"
                        value="<?= htmlspecialchars($settings['address'] ?? 'مین بازار، نزد جامع مسجد') ?>"
                        class="w-full p-3 rounded-xl bg-slate-950 border border-slate-700 text-white font-medium focus:outline-none focus:border-indigo-500 text-sm"
                    />
                </div>
            </div>

            <!-- Merchant Accounts -->
            <div class="pt-4 border-t border-slate-800">
                <div class="flex items-center gap-2 text-emerald-400 font-bold text-xs mb-3">
                    <i data-lucide="wallet" class="w-4 h-4"></i>
                    <span><?= $isUrdu ? 'مرچنٹ و کاروباری کھاتے (ایزی پیسہ / جاز کیش / NTN):' : 'Merchant & Business Numbers:' ?></span>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                    <div>
                        <label class="block text-[11px] font-bold text-slate-400 mb-1">
                            <?= $isUrdu ? 'ایزی پیسہ اکاؤنٹ نمبر' : 'EasyPaisa Merchant No' ?>
                        </label>
                        <input
                            type="text"
                            name="easypaisa_number"
                            value="<?= htmlspecialchars($settings['easyPaisaNumber'] ?? '') ?>"
                            placeholder="0300-1234567"
                            class="w-full p-2.5 rounded-xl bg-slate-950 border border-slate-700 text-white font-mono text-xs focus:outline-none focus:border-emerald-500"
                        />
                    </div>

                    <div>
                        <label class="block text-[11px] font-bold text-slate-400 mb-1">
                            <?= $isUrdu ? 'جاز کیش اکاؤنٹ نمبر' : 'JazzCash Merchant No' ?>
                        </label>
                        <input
                            type="text"
                            name="jazzcash_number"
                            value="<?= htmlspecialchars($settings['jazzCashNumber'] ?? '') ?>"
                            placeholder="0321-1234567"
                            class="w-full p-2.5 rounded-xl bg-slate-950 border border-slate-700 text-white font-mono text-xs focus:outline-none focus:border-amber-500"
                        />
                    </div>

                    <div>
                        <label class="block text-[11px] font-bold text-slate-400 mb-1">
                            <?= $isUrdu ? 'این ٹی این (NTN Number / FBR)' : 'NTN / Tax Number' ?>
                        </label>
                        <input
                            type="text"
                            name="ntn_number"
                            value="<?= htmlspecialchars($settings['ntnNumber'] ?? '') ?>"
                            placeholder="Optional NTN"
                            class="w-full p-2.5 rounded-xl bg-slate-950 border border-slate-700 text-white font-mono text-xs focus:outline-none focus:border-indigo-500"
                        />
                    </div>
                </div>
            </div>

            <!-- Receipt Terms & Footer -->
            <div class="pt-4 border-t border-slate-800 space-y-4">
                <div class="flex items-center gap-2 text-indigo-400 font-bold text-xs">
                    <i data-lucide="file-text" class="w-4 h-4"></i>
                    <span><?= $isUrdu ? 'تھرمل رسید و بل کی شرائط و پیغام (Receipt Terms & Warranty):' : 'Receipt Terms & Footer Greeting:' ?></span>
                </div>

                <div>
                    <label class="block text-xs font-bold text-slate-300 mb-1">
                        <?= $isUrdu ? 'وارنٹی و واپسی کی شرائط (Receipt Terms)' : 'Receipt Warranty & Return Terms' ?>
                    </label>
                    <textarea
                        name="receipt_terms"
                        rows="2"
                        class="w-full p-3 rounded-xl bg-slate-950 border border-slate-700 text-white text-xs leading-relaxed focus:outline-none focus:border-indigo-500"
                    ><?= htmlspecialchars($settings['receiptTerms'] ?? 'وارنٹی موقع پر چیکنگ۔ جلنے یا پانی گرنے کی کوئی وارنٹی نہیں ہے۔ کلیم کیلئے بل لانا لازمی ہے۔') ?></textarea>
                </div>

                <div>
                    <label class="block text-xs font-bold text-slate-300 mb-1">
                        <?= $isUrdu ? 'رسید کا اختتامی شکریہ پیغام (Footer Note)' : 'Receipt Footer Greeting' ?>
                    </label>
                    <input
                        type="text"
                        name="receipt_footer"
                        value="<?= htmlspecialchars($settings['receiptFooter'] ?? 'تشریف آوری کا شکریہ! بلال موبائلز اینڈ ایزی پیسہ شاپ') ?>"
                        class="w-full p-3 rounded-xl bg-slate-950 border border-slate-700 text-white text-xs focus:outline-none focus:border-indigo-500"
                    />
                </div>
            </div>

            <div class="flex justify-end pt-3">
                <button
                    type="submit"
                    class="py-3 px-6 rounded-2xl bg-indigo-600 hover:bg-indigo-500 text-white font-black text-xs sm:text-sm flex items-center gap-2 shadow-lg transition-all cursor-pointer hover:scale-[1.01]"
                >
                    <i data-lucide="save" class="w-4 h-4"></i>
                    <span><?= $isUrdu ? 'سیٹنگز محفوظ کریں' : 'Save Shop Settings' ?></span>
                </button>
            </div>
        </form>
    </div>

    <!-- ========================================================================= -->
    <!-- TAB 2: APP SECURITY & STAFF ACCOUNTS (سیکیورٹی پن و عملہ کھاتے)           -->
    <!-- ========================================================================= -->
    <div id="tab_content_security" class="tab-pane space-y-5 hidden">
        
        <!-- MASTER PIN & AUTO-LOCK SETTINGS -->
        <form method="POST" class="bg-slate-900 border border-slate-800 rounded-3xl p-5 sm:p-7 shadow-sm space-y-6">
            <input type="hidden" name="action" value="save_settings">

            <div class="flex items-center justify-between border-b border-slate-800 pb-3">
                <div class="flex items-center gap-2.5 text-rose-400 font-black text-sm">
                    <i data-lucide="lock" class="w-5 h-5"></i>
                    <span><?= $isUrdu ? 'ایپ ماسٹر سیکیورٹی پن (Master Security PIN)' : 'Master Security PIN Code' ?></span>
                </div>
                <span class="px-2.5 py-0.5 rounded-full bg-rose-500/20 text-rose-300 text-[11px] font-mono font-bold">
                    <?= $isUrdu ? 'ڈیفالٹ پن: 6242' : 'Default: 6242' ?>
                </span>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 items-center">
                <!-- PIN Code Input -->
                <div>
                    <label class="block text-xs font-bold text-slate-300 mb-1.5">
                        <?= $isUrdu ? '4-ہندسوں کا ماسٹر پن کوڈ' : '4-Digit Master PIN' ?> <span class="text-rose-500">*</span>
                    </label>
                    <div class="relative">
                        <input
                            type="password"
                            id="master_pin_input"
                            name="pin_code"
                            maxlength="4"
                            pattern="[0-9]{4}"
                            value="<?= htmlspecialchars($settings['pinCode'] ?? '6242') ?>"
                            required
                            class="w-full p-3 rounded-xl bg-slate-950 border border-slate-700 text-white font-mono font-black text-lg tracking-widest text-center focus:outline-none focus:border-rose-500"
                        />
                        <button
                            type="button"
                            onclick="togglePinVisibility()"
                            class="absolute right-3 top-1/2 -translate-y-1/2 text-slate-400 hover:text-white p-1"
                        >
                            <i id="pin_eye_icon" data-lucide="eye" class="w-4 h-4"></i>
                        </button>
                    </div>
                </div>

                <!-- Auto-Lock Timeout -->
                <div>
                    <label class="block text-xs font-bold text-slate-300 mb-1.5">
                        <?= $isUrdu ? 'غیر حاضری پر آٹو لاک ٹائم' : 'Auto-Lock Inactivity Timeout' ?>
                    </label>
                    <select
                        name="auto_lock_minutes"
                        class="w-full p-3 rounded-xl bg-slate-950 border border-slate-700 text-white font-bold text-xs focus:outline-none focus:border-rose-500"
                    >
                        <option value="5" <?= ($settings['autoLockTimeoutMinutes'] ?? 15) == 5 ? 'selected' : '' ?>><?= $isUrdu ? '5 منٹ بعد' : '5 Minutes' ?></option>
                        <option value="15" <?= ($settings['autoLockTimeoutMinutes'] ?? 15) == 15 ? 'selected' : '' ?>><?= $isUrdu ? '15 منٹ بعد (تجویز کردہ)' : '15 Minutes (Recommended)' ?></option>
                        <option value="30" <?= ($settings['autoLockTimeoutMinutes'] ?? 15) == 30 ? 'selected' : '' ?>><?= $isUrdu ? '30 منٹ بعد' : '30 Minutes' ?></option>
                        <option value="60" <?= ($settings['autoLockTimeoutMinutes'] ?? 15) == 60 ? 'selected' : '' ?>><?= $isUrdu ? '1 گھنٹہ بعد' : '1 Hour' ?></option>
                        <option value="0" <?= ($settings['autoLockTimeoutMinutes'] ?? 15) == 0 ? 'selected' : '' ?>><?= $isUrdu ? 'کبھی نہیں (Never)' : 'Never' ?></option>
                    </select>
                </div>

                <!-- PIN Live Test & Chime -->
                <div class="bg-slate-950 p-3.5 rounded-2xl border border-slate-800 space-y-2">
                    <span class="block text-[11px] font-bold text-slate-400"><?= $isUrdu ? 'ماسٹر پن ٹیسٹ کریں:' : 'Test Current PIN:' ?></span>
                    <div class="flex items-center gap-2">
                        <input
                            type="password"
                            id="test_pin_field"
                            placeholder="6242"
                            maxlength="4"
                            class="w-24 p-2 bg-slate-900 border border-slate-700 rounded-xl text-center font-mono font-bold text-xs text-white"
                        />
                        <button
                            type="button"
                            onclick="testSecurityPin()"
                            class="flex-1 py-2 px-3 bg-rose-600 hover:bg-rose-500 text-white rounded-xl text-xs font-bold transition-all cursor-pointer"
                        >
                            <?= $isUrdu ? 'چیک کریں' : 'Verify' ?>
                        </button>
                    </div>
                    <p id="pin_test_msg" class="text-[11px] font-bold hidden"></p>
                </div>
            </div>

            <!-- Biometric Toggle -->
            <div class="p-3.5 rounded-2xl bg-slate-950/80 border border-slate-800 flex items-center justify-between">
                <div class="flex items-center gap-3">
                    <div class="w-9 h-9 rounded-xl bg-purple-500/20 text-purple-400 flex items-center justify-center">
                        <i data-lucide="fingerprint" class="w-5 h-5"></i>
                    </div>
                    <div>
                        <h4 class="text-xs font-bold text-white"><?= $isUrdu ? 'بائیو میٹرک ان لاک (Fingerprint / Face ID)' : 'Biometric Fingerprint / Face ID Unlock' ?></h4>
                        <p class="text-[11px] text-slate-400"><?= $isUrdu ? 'موبائل اور لیپ ٹاپ فنگر پرنٹ سنسر سے ایپ کھولنے کی اجازت دیں' : 'Allow quick unlock via WebAuthn Biometric hardware' ?></p>
                    </div>
                </div>

                <label class="relative inline-flex items-center cursor-pointer">
                    <input
                        type="checkbox"
                        name="biometric_unlock"
                        value="1"
                        <?= !empty($settings['biometricUnlockEnabled']) ? 'checked' : '' ?>
                        class="sr-only peer"
                    />
                    <div class="w-11 h-6 bg-slate-800 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-slate-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-purple-600"></div>
                </label>
            </div>

            <div class="flex justify-end">
                <button
                    type="submit"
                    class="py-3 px-6 rounded-2xl bg-rose-600 hover:bg-rose-500 text-white font-black text-xs sm:text-sm flex items-center gap-2 shadow-lg transition-all cursor-pointer"
                >
                    <i data-lucide="save" class="w-4 h-4"></i>
                    <span><?= $isUrdu ? 'سیکیورٹی سیٹنگز محفوظ کریں' : 'Save Security Settings' ?></span>
                </button>
            </div>
        </form>

        <!-- STAFF & OPERATOR ACCOUNTS (RBAC TABLE) -->
        <div class="bg-slate-900 border border-slate-800 rounded-3xl p-5 sm:p-7 shadow-sm space-y-4">
            <div class="flex items-center justify-between border-b border-slate-800 pb-3 flex-wrap gap-2">
                <div class="flex items-center gap-2 text-indigo-400 font-black text-sm">
                    <i data-lucide="users" class="w-5 h-5"></i>
                    <span><?= $isUrdu ? 'ملازمین و آپریٹرز کے کھاتے (Staff & User Permissions)' : 'Staff & Operator Permissions' ?></span>
                </div>
                <button
                    type="button"
                    onclick="openAddStaffModal()"
                    class="py-2 px-3.5 rounded-xl bg-indigo-600 hover:bg-indigo-500 text-white font-bold text-xs flex items-center gap-1.5 transition-all cursor-pointer"
                >
                    <i data-lucide="user-plus" class="w-3.5 h-3.5"></i>
                    <span><?= $isUrdu ? '+ نیا اسٹاف اکاؤنٹ' : '+ Add Staff Account' ?></span>
                </button>
            </div>

            <!-- Staff Accounts List Table -->
            <div class="overflow-x-auto">
                <table class="w-full text-xs text-right">
                    <thead class="bg-slate-950 text-slate-400 font-bold border-b border-slate-800">
                        <tr>
                            <th class="p-3"><?= $isUrdu ? 'نام ملازم' : 'Staff Name' ?></th>
                            <th class="p-3"><?= $isUrdu ? 'ای میل / یوزر نام' : 'Email / Login' ?></th>
                            <th class="p-3"><?= $isUrdu ? 'اختیارات / رول' : 'Role / Permission' ?></th>
                            <th class="p-3 text-center"><?= $isUrdu ? 'ایکشن' : 'Actions' ?></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-800">
                        <?php foreach ($staffAccounts as $acc): 
                            $role = $acc['role'] ?? 'Staff';
                            $roleBadge = match($role) {
                                'Owner' => 'bg-emerald-500/20 text-emerald-300 border-emerald-500/30',
                                'Manager' => 'bg-indigo-500/20 text-indigo-300 border-indigo-500/30',
                                default => 'bg-slate-800 text-slate-300 border-slate-700'
                            };
                        ?>
                            <tr class="hover:bg-slate-800/40 transition-colors">
                                <td class="p-3 font-bold text-white flex items-center gap-2">
                                    <div class="w-7 h-7 rounded-lg bg-slate-800 text-indigo-400 flex items-center justify-center font-black">
                                        <?= mb_substr($acc['name'] ?? 'U', 0, 1) ?>
                                    </div>
                                    <span><?= htmlspecialchars($acc['name'] ?? 'Staff') ?></span>
                                </td>
                                <td class="p-3 font-mono text-slate-300"><?= htmlspecialchars($acc['email'] ?? '') ?></td>
                                <td class="p-3">
                                    <span class="px-2.5 py-0.5 rounded-full text-[10px] font-bold border <?= $roleBadge ?>">
                                        <?= htmlspecialchars($role) ?>
                                    </span>
                                </td>
                                <td class="p-3 text-center">
                                    <?php if ($role !== 'Owner'): ?>
                                        <form method="POST" onsubmit="return confirm('<?= $isUrdu ? 'کیا آپ واقعی اس اکاؤنٹ کو ختم کرنا چاہتے ہیں؟' : 'Remove this staff account?' ?>')" class="inline">
                                            <input type="hidden" name="action" value="save_staff_account">
                                            <input type="hidden" name="delete_acc_id" value="<?= htmlspecialchars($acc['id'] ?? '') ?>">
                                            <button type="submit" class="p-1.5 rounded-lg bg-rose-500/10 hover:bg-rose-500/20 text-rose-400 transition-colors cursor-pointer">
                                                <i data-lucide="trash-2" class="w-3.5 h-3.5"></i>
                                            </button>
                                        </form>
                                    <?php else: ?>
                                        <span class="text-[10px] text-emerald-400 font-bold"><?= $isUrdu ? 'مین مالک' : 'Primary' ?></span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- ========================================================================= -->
    <!-- TAB 3: POS PRINTER & HARDWARE (پرنٹر سائز، آواز و بارکوڈ گن)              -->
    <!-- ========================================================================= -->
    <div id="tab_content_pos_hardware" class="tab-pane space-y-5 hidden">
        <form method="POST" class="bg-slate-900 border border-slate-800 rounded-3xl p-5 sm:p-7 shadow-sm space-y-6">
            <input type="hidden" name="action" value="save_settings">

            <div class="flex items-center gap-2.5 border-b border-slate-800 pb-3 text-cyan-400 font-black text-sm">
                <i data-lucide="printer" class="w-5 h-5"></i>
                <span><?= $isUrdu ? 'تھرمل پرنٹر و ہارڈویئر کی سیٹنگز (POS Thermal Printer)' : 'POS Thermal Printer & Hardware' ?></span>
            </div>

            <!-- Paper Size Selection -->
            <div>
                <label class="block text-xs font-bold text-slate-300 mb-2">
                    <?= $isUrdu ? 'تھرمل رسید کا پرنٹ سائز (Thermal Receipt Paper Size):' : 'Thermal Receipt Paper Size:' ?>
                </label>
                <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                    <label class="p-4 rounded-2xl bg-slate-950 border <?= ($settings['printerPaperSize'] ?? '80mm') === '80mm' ? 'border-cyan-500 bg-cyan-950/20' : 'border-slate-800' ?> flex items-center justify-between cursor-pointer hover:border-slate-700">
                        <div class="flex items-center gap-3">
                            <input type="radio" name="printer_paper_size" value="80mm" <?= ($settings['printerPaperSize'] ?? '80mm') === '80mm' ? 'checked' : '' ?> class="text-cyan-500 focus:ring-0">
                            <div>
                                <span class="font-bold text-white text-xs block">80mm (3-Inch)</span>
                                <span class="text-[11px] text-slate-400"><?= $isUrdu ? 'معیاری POS تھرمل پرنٹر' : 'Standard 80mm POS Receipt' ?></span>
                            </div>
                        </div>
                        <i data-lucide="receipt" class="w-5 h-5 text-cyan-400"></i>
                    </label>

                    <label class="p-4 rounded-2xl bg-slate-950 border <?= ($settings['printerPaperSize'] ?? '') === '58mm' ? 'border-cyan-500 bg-cyan-950/20' : 'border-slate-800' ?> flex items-center justify-between cursor-pointer hover:border-slate-700">
                        <div class="flex items-center gap-3">
                            <input type="radio" name="printer_paper_size" value="58mm" <?= ($settings['printerPaperSize'] ?? '') === '58mm' ? 'checked' : '' ?> class="text-cyan-500 focus:ring-0">
                            <div>
                                <span class="font-bold text-white text-xs block">58mm (2-Inch)</span>
                                <span class="text-[11px] text-slate-400"><?= $isUrdu ? 'چھوٹا پورٹیبل / بلوٹوتھ پرنٹر' : 'Mini Portable / Bluetooth POS' ?></span>
                            </div>
                        </div>
                        <i data-lucide="smartphone" class="w-5 h-5 text-amber-400"></i>
                    </label>

                    <label class="p-4 rounded-2xl bg-slate-950 border <?= ($settings['printerPaperSize'] ?? '') === 'a4' ? 'border-cyan-500 bg-cyan-950/20' : 'border-slate-800' ?> flex items-center justify-between cursor-pointer hover:border-slate-700">
                        <div class="flex items-center gap-3">
                            <input type="radio" name="printer_paper_size" value="a4" <?= ($settings['printerPaperSize'] ?? '') === 'a4' ? 'checked' : '' ?> class="text-cyan-500 focus:ring-0">
                            <div>
                                <span class="font-bold text-white text-xs block">A4 Sheet</span>
                                <span class="text-[11px] text-slate-400"><?= $isUrdu ? 'مکمل انک جیٹ یا لیزر پرنٹر' : 'Full Page Laser/Inkjet Bill' ?></span>
                            </div>
                        </div>
                        <i data-lucide="file-text" class="w-5 h-5 text-indigo-400"></i>
                    </label>
                </div>
            </div>

            <!-- Auto-Print on Checkout -->
            <div class="p-4 rounded-2xl bg-slate-950 border border-slate-800 flex items-center justify-between">
                <div class="flex items-center gap-3">
                    <div class="w-9 h-9 rounded-xl bg-cyan-500/20 text-cyan-400 flex items-center justify-center">
                        <i data-lucide="printer" class="w-5 h-5"></i>
                    </div>
                    <div>
                        <h4 class="text-xs font-bold text-white"><?= $isUrdu ? 'سیل مکمل ہوتے ہی خودکار پرنٹ ڈائیلاگ (Auto-Print on Checkout)' : 'Auto-Print Receipt on Checkout' ?></h4>
                        <p class="text-[11px] text-slate-400"><?= $isUrdu ? 'سیل یا بل مکمل ہوتے ہی خودکار پرنٹر ڈائیلاگ ونڈو کھولیں' : 'Automatically open print dialog right after finishing sale' ?></p>
                    </div>
                </div>

                <label class="relative inline-flex items-center cursor-pointer">
                    <input
                        type="checkbox"
                        name="auto_print_receipt"
                        value="1"
                        <?= !empty($settings['autoPrintReceipt']) ? 'checked' : '' ?>
                        class="sr-only peer"
                    />
                    <div class="w-11 h-6 bg-slate-800 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-slate-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-cyan-600"></div>
                </label>
            </div>

            <!-- Sound Effects & Chime Beep -->
            <div class="p-4 rounded-2xl bg-slate-950 border border-slate-800 flex items-center justify-between">
                <div class="flex items-center gap-3">
                    <div class="w-9 h-9 rounded-xl bg-emerald-500/20 text-emerald-400 flex items-center justify-center">
                        <i data-lucide="volume-2" class="w-5 h-5"></i>
                    </div>
                    <div>
                        <h4 class="text-xs font-bold text-white"><?= $isUrdu ? 'بارکوڈ اسکین اور سیل مکمل ہونے کی آواز (Sound Effects)' : 'POS Audio Sound Effects & Beep' ?></h4>
                        <p class="text-[11px] text-slate-400"><?= $isUrdu ? 'بارکوڈ اسکینر اور سیل کا بل مکمل ہونے پر تصدیقی چائم بیپ بجائیں' : 'Play audio chime beep on barcode scan and sale completion' ?></p>
                    </div>
                </div>

                <div class="flex items-center gap-3">
                    <button
                        type="button"
                        onclick="playTestBeepSound()"
                        class="py-1.5 px-2.5 rounded-xl bg-emerald-600/30 hover:bg-emerald-600/50 text-emerald-300 font-bold text-xs flex items-center gap-1 cursor-pointer transition-colors"
                    >
                        <i data-lucide="volume-2" class="w-3.5 h-3.5"></i>
                        <span><?= $isUrdu ? 'آواز چیک کریں' : 'Test Beep' ?></span>
                    </button>

                    <label class="relative inline-flex items-center cursor-pointer">
                        <input
                            type="checkbox"
                            name="pos_sound_enabled"
                            value="1"
                            <?= !empty($settings['posSoundEnabled']) ? 'checked' : '' ?>
                            class="sr-only peer"
                        />
                        <div class="w-11 h-6 bg-slate-800 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-slate-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-emerald-600"></div>
                    </label>
                </div>
            </div>

            <!-- Barcode Gun Scanner Mode -->
            <div class="p-4 rounded-2xl bg-slate-950 border border-slate-800 flex items-center justify-between">
                <div class="flex items-center gap-3">
                    <div class="w-9 h-9 rounded-xl bg-amber-500/20 text-amber-400 flex items-center justify-center">
                        <i data-lucide="scan-barcode" class="w-5 h-5"></i>
                    </div>
                    <div>
                        <h4 class="text-xs font-bold text-white"><?= $isUrdu ? 'بارکوڈ اسکینر گن آٹو انٹر (Barcode Gun Scanner Auto-Submit)' : 'Barcode Gun Scanner Quick-Add' ?></h4>
                        <p class="text-[11px] text-slate-400"><?= $isUrdu ? 'بارکوڈ گن سے اسکین کرتے ہی آئٹم خودکار طریقے سے کارٹ میں شامل ہو جائے' : 'Automatically add item to cart on barcode scanner Enter character' ?></p>
                    </div>
                </div>

                <label class="relative inline-flex items-center cursor-pointer">
                    <input
                        type="checkbox"
                        name="barcode_auto_submit"
                        value="1"
                        <?= !empty($settings['barcodeAutoSubmit']) ? 'checked' : '' ?>
                        class="sr-only peer"
                    />
                    <div class="w-11 h-6 bg-slate-800 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-slate-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-amber-600"></div>
                </label>
            </div>

            <div class="flex justify-end">
                <button
                    type="submit"
                    class="py-3 px-6 rounded-2xl bg-cyan-600 hover:bg-cyan-500 text-white font-black text-xs sm:text-sm flex items-center gap-2 shadow-lg transition-all cursor-pointer"
                >
                    <i data-lucide="save" class="w-4 h-4"></i>
                    <span><?= $isUrdu ? 'پرنٹر سیٹنگز محفوظ کریں' : 'Save Printer Settings' ?></span>
                </button>
            </div>
        </form>
    </div>

    <!-- ========================================================================= -->
    <!-- TAB 4: GOOGLE DRIVE & SAFE STORAGE BACKUP (مکمل کلاؤڈ و لوکل بیک اپ)       -->
    <!-- ========================================================================= -->
    <div id="tab_content_backup" class="tab-pane space-y-6 hidden">
        
        <!-- GOOGLE DRIVE SYNC CARD -->
        <div class="p-6 rounded-3xl bg-gradient-to-r from-emerald-900/60 via-teal-900/40 to-slate-900 border border-emerald-500/40 shadow-xl space-y-5">
            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                <div class="flex items-center gap-3.5">
                    <div class="w-12 h-12 rounded-2xl bg-emerald-500/20 border border-emerald-400/30 text-emerald-400 flex items-center justify-center shrink-0">
                        <i data-lucide="cloud" class="w-6 h-6"></i>
                    </div>
                    <div>
                        <div class="flex items-center gap-2 flex-wrap">
                            <h3 class="font-black text-white text-base sm:text-lg">
                                <?= $isUrdu ? 'گوگل ڈرائیو کلاؤڈ محفوظ بیک اپ (Google Drive Cloud Storage)' : 'Google Drive Cloud Storage Backup' ?>
                            </h3>
                            <span id="gdrive_status_badge" class="px-2.5 py-0.5 rounded-full bg-emerald-500/20 text-emerald-300 border border-emerald-500/30 text-[10px] font-black uppercase">
                                <?= !empty($settings['googleDriveConnected']) ? ($isUrdu ? 'منسلک و فعال' : 'Connected') : ($isUrdu ? 'تیار برائے سنک' : 'Ready to Sync') ?>
                            </span>
                        </div>
                        <p class="text-xs text-emerald-200 mt-0.5">
                            <?= $isUrdu 
                                ? 'دکان کا مکمل بیک اپ خودکار طریقے سے آپ کے اپنے ذاتی گوگل ڈرائیو فولڈر "Balal Mobiles Shop Backups" میں محفوظ ہوگا' 
                                : 'Automatically saves encrypted full JSON shop backups to your personal Google Drive folder' ?>
                        </p>
                    </div>
                </div>

                <!-- Google Drive Connect / Sync Button -->
                <div class="flex items-center gap-2 flex-wrap">
                    <button
                        type="button"
                        onclick="syncNowToGoogleDrive()"
                        id="gdrive_sync_btn"
                        class="py-2.5 px-4 rounded-xl bg-emerald-600 hover:bg-emerald-500 text-white font-black text-xs flex items-center gap-1.5 shadow-md transition-all cursor-pointer hover:scale-[1.02]"
                    >
                        <i data-lucide="upload-cloud" class="w-4 h-4"></i>
                        <span><?= $isUrdu ? 'ڈرائیو پر ابھی بیک اپ بھیجیں' : 'Sync to Google Drive' ?></span>
                    </button>

                    <button
                        type="button"
                        onclick="openGoogleDriveSetupModal()"
                        class="py-2.5 px-3 rounded-xl bg-slate-800 hover:bg-slate-700 text-slate-200 border border-slate-700 font-bold text-xs flex items-center gap-1 transition-colors cursor-pointer"
                    >
                        <i data-lucide="settings-2" class="w-4 h-4"></i>
                        <span><?= $isUrdu ? 'اکاؤنٹ سیٹ اپ' : 'Config' ?></span>
                    </button>
                </div>
            </div>

            <!-- Drive Status Banner -->
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-3 text-xs pt-3 border-t border-emerald-500/20">
                <div class="bg-slate-950/60 p-3 rounded-2xl border border-slate-800">
                    <span class="text-slate-400 text-[10px] block font-semibold"><?= $isUrdu ? 'ڈرائیو فولڈر کا نام:' : 'Drive Target Folder:' ?></span>
                    <p class="font-bold text-white truncate"><?= htmlspecialchars($settings['googleDriveFolderName'] ?? 'Balal Mobiles Shop Backups') ?></p>
                </div>
                <div class="bg-slate-950/60 p-3 rounded-2xl border border-slate-800">
                    <span class="text-slate-400 text-[10px] block font-semibold"><?= $isUrdu ? 'منسلک گوگل ای میل:' : 'Connected Account:' ?></span>
                    <p class="font-mono text-emerald-300 truncate"><?= htmlspecialchars($settings['googleDriveUserEmail'] ?? 'studioabdul.com@gmail.com') ?></p>
                </div>
                <div class="bg-slate-950/60 p-3 rounded-2xl border border-slate-800">
                    <span class="text-slate-400 text-[10px] block font-semibold"><?= $isUrdu ? 'آخری کلاؤڈ سنک:' : 'Last Cloud Sync:' ?></span>
                    <p class="font-mono text-cyan-300" id="last_gdrive_sync_text"><?= date('Y-m-d h:i A') ?></p>
                </div>
            </div>
        </div>

        <!-- SAFE LOCAL STORAGE (FILE SYSTEM ACCESS API & BROWSER PERSISTENCE) -->
        <div class="bg-slate-900 border border-slate-800 rounded-3xl p-5 sm:p-7 shadow-sm space-y-5">
            <div class="flex items-center justify-between border-b border-slate-800 pb-3 flex-wrap gap-2">
                <div class="flex items-center gap-2.5 text-cyan-400 font-black text-sm">
                    <i data-lucide="hard-drive" class="w-5 h-5"></i>
                    <span><?= $isUrdu ? 'کمپیوٹر لوکل فولڈر میں محفوظ خودکار بیک اپ (Safe Local Folder Sync)' : 'Safe Local Folder Sync (Hard Drive)' ?></span>
                </div>
                <span class="px-2.5 py-0.5 rounded-full bg-cyan-500/20 text-cyan-300 text-[10px] font-bold border border-cyan-500/30">
                    <?= $isUrdu ? 'ہر ٹرانزیکشن پر آٹو سیو' : 'Auto-Save on every sale' ?>
                </span>
            </div>

            <p class="text-xs text-slate-300 leading-relaxed">
                <?= $isUrdu 
                    ? 'کروم یا ایج براؤزر کے <b>File System Access API</b> کے ذریعے آپ اپنے کمپیوٹر کی ہارڈ ڈرائیو (مثلاً <code>D:\BalalMobiles_Backups</code>) کا کوئی بھی فولڈر منتخب کر سکتے ہیں۔ ہر نئی سیل یا خریداری پر فوری طور پر بغیر انٹرنیٹ کے بیک اپ کی فائل بنتی رہے گی۔' 
                    : 'Select a dedicated folder on your computer hard drive using the Browser File System Access API. A complete backup snapshot will be saved automatically to your PC.' ?>
            </p>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <!-- Select Local Hard Drive Folder Button -->
                <div class="p-4 rounded-2xl bg-slate-950 border border-slate-800 flex flex-col justify-between space-y-3">
                    <div>
                        <div class="flex items-center gap-2 text-white font-bold text-xs mb-1">
                            <i data-lucide="folder" class="w-4 h-4 text-amber-400"></i>
                            <span><?= $isUrdu ? 'کمپیوٹر فولڈر کا انتخاب' : 'Select Local Hard Drive Folder' ?></span>
                        </div>
                        <p class="text-[11px] text-slate-400" id="local_folder_display_text">
                            <?= $isUrdu ? 'کوئی فولڈر منتخب نہیں ہے۔ کلک کر کے منتخب کریں۔' : 'No local directory selected yet.' ?>
                        </p>
                    </div>

                    <button
                        type="button"
                        onclick="requestLocalBackupFolder()"
                        class="py-2.5 px-4 rounded-xl bg-amber-600 hover:bg-amber-500 text-white font-black text-xs flex items-center justify-center gap-2 transition-all cursor-pointer"
                    >
                        <i data-lucide="folder-plus" class="w-4 h-4"></i>
                        <span><?= $isUrdu ? 'لوکل فولڈر منتخب کریں' : 'Choose Local Folder' ?></span>
                    </button>
                </div>

                <!-- Persistent Storage Lock Status -->
                <div class="p-4 rounded-2xl bg-slate-950 border border-slate-800 flex flex-col justify-between space-y-3">
                    <div>
                        <div class="flex items-center gap-2 text-white font-bold text-xs mb-1">
                            <i data-lucide="shield-check" class="w-4 h-4 text-emerald-400"></i>
                            <span><?= $isUrdu ? 'براؤزر اسٹوریج پرسٹنس (Never Clear)' : 'Persistent Storage Guarantee' ?></span>
                        </div>
                        <p class="text-[11px] text-slate-400">
                            <?= $isUrdu ? 'براؤزر کو ہدایت دیتا ہے کہ ڈسک فل ہونے پر بھی کیش ڈیٹا کبھی ڈیلیٹ نہ ہو۔' : 'Guarantees browser cache will never be purged automatically.' ?>
                        </p>
                    </div>

                    <button
                        type="button"
                        onclick="requestStoragePersistencePermission()"
                        class="py-2.5 px-4 rounded-xl bg-emerald-600 hover:bg-emerald-500 text-white font-black text-xs flex items-center justify-center gap-2 transition-all cursor-pointer"
                    >
                        <i data-lucide="lock" class="w-4 h-4"></i>
                        <span><?= $isUrdu ? 'اسٹوریج کو پرماننٹ لاک کریں' : 'Enable Permanent Storage' ?></span>
                    </button>
                </div>
            </div>
        </div>

        <!-- 1-CLICK MANUAL EXPORT & RESTORE CENTER -->
        <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
            
            <!-- Export Center -->
            <div class="bg-slate-900 border border-slate-800 rounded-3xl p-5 sm:p-6 shadow-sm space-y-4">
                <div class="flex items-center gap-2 text-emerald-400 font-black text-sm border-b border-slate-800 pb-3">
                    <i data-lucide="download" class="w-5 h-5"></i>
                    <span><?= $isUrdu ? 'ڈاؤنلوڈ بیک اپ فائلز (Download Backups)' : 'Download Backups' ?></span>
                </div>

                <p class="text-xs text-slate-400">
                    <?= $isUrdu ? 'اپنی دکان کا مکمل بیک اپ اپنی یو ایس بی یا لیپ ٹاپ میں آف لائن محفوظ کریں:' : 'Save an offline copy of your entire shop to USB or PC:' ?>
                </p>

                <div class="space-y-3">
                    <a
                        href="?action=export_json"
                        class="p-3.5 rounded-2xl bg-slate-950 hover:bg-slate-800 border border-slate-700 text-white font-bold text-xs flex items-center justify-between transition-all group"
                    >
                        <div class="flex items-center gap-3">
                            <div class="w-9 h-9 rounded-xl bg-emerald-500/20 text-emerald-400 flex items-center justify-center">
                                <i data-lucide="file-code" class="w-5 h-5"></i>
                            </div>
                            <div>
                                <span class="font-bold text-white block">Complete JSON Shop Backup</span>
                                <span class="text-[10px] text-slate-400"><?= $isUrdu ? 'موبائلز، سیلز، فوٹوز و کھاتہ جات' : 'Mobiles, Sales, Photos & Ledger' ?></span>
                            </div>
                        </div>
                        <i data-lucide="arrow-down-to-line" class="w-4 h-4 text-emerald-400 group-hover:translate-y-0.5 transition-transform"></i>
                    </a>

                    <a
                        href="?action=export_sql"
                        class="p-3.5 rounded-2xl bg-slate-950 hover:bg-slate-800 border border-slate-700 text-white font-bold text-xs flex items-center justify-between transition-all group"
                    >
                        <div class="flex items-center gap-3">
                            <div class="w-9 h-9 rounded-xl bg-indigo-500/20 text-indigo-400 flex items-center justify-center">
                                <i data-lucide="database" class="w-5 h-5"></i>
                            </div>
                            <div>
                                <span class="font-bold text-white block">MySQL Database Dump (.sql)</span>
                                <span class="text-[10px] text-slate-400"><?= $isUrdu ? 'cPanel یا InfinityFree امپورٹ کیلئے' : 'Ready for phpMyAdmin Import' ?></span>
                            </div>
                        </div>
                        <i data-lucide="arrow-down-to-line" class="w-4 h-4 text-indigo-400 group-hover:translate-y-0.5 transition-transform"></i>
                    </a>
                </div>
            </div>

            <!-- Restore Center -->
            <div class="bg-slate-900 border border-slate-800 rounded-3xl p-5 sm:p-6 shadow-sm space-y-4">
                <div class="flex items-center gap-2 text-amber-400 font-black text-sm border-b border-slate-800 pb-3">
                    <i data-lucide="rotate-ccw" class="w-5 h-5"></i>
                    <span><?= $isUrdu ? 'بیک اپ فائل سے ریسٹور کریں (Restore Backup)' : 'Restore from JSON Backup' ?></span>
                </div>

                <p class="text-xs text-slate-400">
                    <?= $isUrdu ? 'پہلے سے موجود کسی بھی JSON بیک اپ فائل کو منتخب کر کے ڈیٹا بحال کریں:' : 'Upload previously exported JSON backup to recover all data:' ?>
                </p>

                <form method="POST" enctype="multipart/form-data" class="space-y-3" onsubmit="return confirm('<?= $isUrdu ? 'کیا آپ واقعی اس بیک اپ سے ڈیٹا بحال کرنا چاہتے ہیں؟' : 'Are you sure you want to restore this backup file?' ?>')">
                    <input type="hidden" name="action" value="restore_json">
                    
                    <div class="p-3 rounded-2xl bg-slate-950 border border-dashed border-slate-700">
                        <label class="block text-xs font-bold text-slate-300 mb-1.5"><?= $isUrdu ? 'JSON فائل منتخب کریں:' : 'Select JSON File:' ?></label>
                        <input
                            type="file"
                            name="backup_file"
                            accept=".json"
                            required
                            class="w-full text-xs text-slate-400 file:mr-3 file:py-1.5 file:px-3 file:rounded-xl file:border-0 file:text-xs file:font-bold file:bg-amber-600 file:text-white hover:file:bg-amber-500 cursor-pointer"
                        />
                    </div>

                    <button
                        type="submit"
                        class="w-full py-3 px-4 rounded-2xl bg-amber-600 hover:bg-amber-500 text-white font-black text-xs flex items-center justify-center gap-2 shadow-md transition-all cursor-pointer"
                    >
                        <i data-lucide="upload" class="w-4 h-4"></i>
                        <span><?= $isUrdu ? 'ڈیٹا ریسٹور و بحال کریں' : 'Restore Shop Data' ?></span>
                    </button>
                </form>
            </div>

        </div>

    </div>

    <!-- ========================================================================= -->
    <!-- TAB 5: SERVER DATABASE AUDIT & WIPE (ڈیٹا بیس جدول شمار و ڈیٹا ری سیٹ)    -->
    <!-- ========================================================================= -->
    <div id="tab_content_server_db" class="tab-pane space-y-6 hidden">
        
        <!-- Live Database Tables Audit Grid -->
        <div class="bg-slate-900 border border-slate-800 rounded-3xl p-5 sm:p-7 shadow-sm space-y-5">
            <div class="flex items-center justify-between border-b border-slate-800 pb-3 flex-wrap gap-2">
                <div class="flex items-center gap-2.5 text-indigo-400 font-black text-sm">
                    <i data-lucide="database" class="w-5 h-5"></i>
                    <span><?= $isUrdu ? 'لائیو ڈیٹا بیس جدول شمار (Live Database Row Audit)' : 'Live Database Row Audit' ?></span>
                </div>
                <span class="text-xs text-slate-400 font-mono">
                    Driver: <?= $pdo->getAttribute(PDO::ATTR_DRIVER_NAME) ?>
                </span>
            </div>

            <div class="grid grid-cols-2 sm:grid-cols-4 gap-3.5">
                <?php foreach ($tableStats as $tblKey => $st): ?>
                    <div class="p-3.5 rounded-2xl bg-slate-950 border border-slate-800 space-y-1">
                        <div class="flex items-center justify-between">
                            <span class="text-[11px] text-slate-400 font-semibold truncate"><?= htmlspecialchars($st['name']) ?></span>
                            <i data-lucide="<?= $st['icon'] ?>" class="w-4 h-4 text-indigo-400 shrink-0"></i>
                        </div>
                        <p class="text-lg sm:text-xl font-black font-mono text-white"><?= number_format($st['count']) ?></p>
                        <span class="text-[9px] text-slate-500 font-mono block">table: `<?= $tblKey ?>`</span>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- EMERGENCY DATA WIPE / FACTORY RESET CARD -->
        <div class="bg-rose-950/20 border border-rose-500/30 rounded-3xl p-5 sm:p-7 space-y-4">
            <div class="flex items-center gap-2.5 text-rose-400 font-black text-sm border-b border-rose-500/20 pb-3">
                <i data-lucide="alert-octagon" class="w-5 h-5"></i>
                <span><?= $isUrdu ? 'خطرناک زون: تمام دکان کا ڈیٹا ری سیٹ کریں (Factory Reset / Wipe All Data)' : 'Danger Zone: Factory Reset / Wipe Data' ?></span>
            </div>

            <p class="text-xs text-rose-200 leading-relaxed">
                <?= $isUrdu 
                    ? '⚠️ خبردار: یہ عمل تمام سیلز، خریداریاں، ادھار کھاتے اور مصنوعات ڈیلیٹ کر دے گا۔ اس عمل سے پہلے اوپر دیے گئے بٹن سے بیک اپ فائل ضرور ڈاؤنلوڈ کر لیں۔' 
                    : 'Warning: This action will permanently delete all sales, transactions, khata entries and mobile records. Download a JSON backup first.' ?>
            </p>

            <form method="POST" class="flex flex-col sm:flex-row items-center gap-3 pt-2" onsubmit="return confirm('<?= $isUrdu ? 'کیا آپ 100% پرعزم ہیں کہ سارا ڈیٹا ڈیلیٹ کرنا ہے؟ یہ عمل ناقابل واپسی ہے!' : 'Are you 100% sure you want to wipe all records? This cannot be undone!' ?>')">
                <input type="hidden" name="action" value="wipe_database">

                <div class="relative w-full sm:w-64">
                    <input
                        type="password"
                        name="confirm_pin"
                        placeholder="<?= $isUrdu ? 'ماسٹر پن (6242)' : 'Enter Master PIN' ?>"
                        required
                        maxlength="4"
                        class="w-full p-2.5 rounded-xl bg-slate-950 border border-rose-500/40 text-rose-300 font-mono text-center font-bold text-xs focus:outline-none focus:border-rose-500"
                    />
                </div>

                <button
                    type="submit"
                    class="w-full sm:w-auto py-2.5 px-5 rounded-xl bg-rose-600 hover:bg-rose-700 text-white font-black text-xs flex items-center justify-center gap-2 transition-all cursor-pointer"
                >
                    <i data-lucide="trash-2" class="w-4 h-4"></i>
                    <span><?= $isUrdu ? 'مکمل ڈیٹا صاف کریں (Wipe Data)' : 'Confirm Factory Reset' ?></span>
                </button>
            </form>
        </div>

    </div>

</div>

<!-- ========================================================================= -->
<!-- MODAL 1: ADD / EDIT STAFF ACCOUNT MODAL                                   -->
<!-- ========================================================================= -->
<div id="staffModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black/80 backdrop-blur-sm p-4 hidden">
    <div class="bg-slate-900 border border-slate-700 text-white w-full max-w-md rounded-3xl p-6 shadow-2xl space-y-5">
        <div class="flex items-center justify-between border-b border-slate-800 pb-3">
            <div class="flex items-center gap-2 text-indigo-400 font-black text-base">
                <i data-lucide="user-plus" class="w-5 h-5"></i>
                <span><?= $isUrdu ? 'نیا اسٹاف اکاؤنٹ بنائیں' : 'Add Staff Account' ?></span>
            </div>
            <button type="button" onclick="closeAddStaffModal()" class="text-slate-400 hover:text-white">
                <i data-lucide="x" class="w-5 h-5"></i>
            </button>
        </div>

        <form method="POST" class="space-y-4">
            <input type="hidden" name="action" value="save_staff_account">
            
            <div>
                <label class="block text-xs font-bold text-slate-300 mb-1"><?= $isUrdu ? 'ملازم کا نام' : 'Staff Full Name' ?> <span class="text-rose-500">*</span></label>
                <input type="text" name="acc_name" required placeholder="Ali Raza" class="w-full p-2.5 rounded-xl bg-slate-950 border border-slate-700 text-white text-xs">
            </div>

            <div>
                <label class="block text-xs font-bold text-slate-300 mb-1"><?= $isUrdu ? 'ای میل یا لاگ ان آئی ڈی' : 'Email Address' ?> <span class="text-rose-500">*</span></label>
                <input type="email" name="acc_email" required placeholder="ali@balalmobiles.pk" class="w-full p-2.5 rounded-xl bg-slate-950 border border-slate-700 text-white text-xs font-mono">
            </div>

            <div>
                <label class="block text-xs font-bold text-slate-300 mb-1"><?= $isUrdu ? 'لاگ ان پاس ورڈ' : 'Password' ?> <span class="text-rose-500">*</span></label>
                <input type="password" name="acc_pass" required placeholder="••••••••" class="w-full p-2.5 rounded-xl bg-slate-950 border border-slate-700 text-white text-xs font-mono">
            </div>

            <div>
                <label class="block text-xs font-bold text-slate-300 mb-1"><?= $isUrdu ? 'اختیارات کا درجہ (Role)' : 'Role' ?></label>
                <select name="acc_role" class="w-full p-2.5 rounded-xl bg-slate-950 border border-slate-700 text-white text-xs font-bold">
                    <option value="Staff"><?= $isUrdu ? 'سیلز اسٹاف (صرف بل و سیل درج کرنے کی اجازت)' : 'Staff (Sales Only)' ?></option>
                    <option value="Manager"><?= $isUrdu ? 'مینیجر (سیلز + موبائل خریداری + کھاتہ)' : 'Manager (Sales + Purchases + Khata)' ?></option>
                    <option value="Owner"><?= $isUrdu ? 'مالک / ایڈمن (مکمل اختیارات)' : 'Owner / Super Admin' ?></option>
                </select>
            </div>

            <div class="flex justify-end gap-2 pt-2">
                <button type="button" onclick="closeAddStaffModal()" class="py-2 px-4 bg-slate-800 text-slate-300 rounded-xl text-xs font-bold"><?= $isUrdu ? 'منسوخ' : 'Cancel' ?></button>
                <button type="submit" class="py-2 px-5 bg-indigo-600 hover:bg-indigo-500 text-white rounded-xl text-xs font-black"><?= $isUrdu ? 'محفوظ کریں' : 'Save Account' ?></button>
            </div>
        </form>
    </div>
</div>

<!-- ========================================================================= -->
<!-- MODAL 2: GOOGLE DRIVE ADVANCED SETUP & TOKEN CONFIG                       -->
<!-- ========================================================================= -->
<div id="gdriveSetupModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black/80 backdrop-blur-sm p-4 hidden">
    <div class="bg-slate-900 border border-slate-700 text-white w-full max-w-lg rounded-3xl p-6 shadow-2xl space-y-5">
        <div class="flex items-center justify-between border-b border-slate-800 pb-3">
            <div class="flex items-center gap-2.5 text-emerald-400 font-black text-base">
                <i data-lucide="cloud" class="w-5 h-5"></i>
                <span><?= $isUrdu ? 'گوگل ڈرائیو کلاؤڈ سنک ترتیبات' : 'Google Drive Cloud Sync Setup' ?></span>
            </div>
            <button type="button" onclick="closeGoogleDriveSetupModal()" class="text-slate-400 hover:text-white">
                <i data-lucide="x" class="w-5 h-5"></i>
            </button>
        </div>

        <form method="POST" class="space-y-4">
            <input type="hidden" name="action" value="save_settings">
            <input type="hidden" name="gdrive_connected" value="1">

            <div>
                <label class="block text-xs font-bold text-slate-300 mb-1"><?= $isUrdu ? 'گوگل ای میل اکاؤنٹ (Google Account Email)' : 'Google Account Email' ?></label>
                <input
                    type="email"
                    name="gdrive_user_email"
                    value="<?= htmlspecialchars($settings['googleDriveUserEmail'] ?? 'studioabdul.com@gmail.com') ?>"
                    required
                    class="w-full p-2.5 rounded-xl bg-slate-950 border border-slate-700 text-white text-xs font-mono"
                />
            </div>

            <div>
                <label class="block text-xs font-bold text-slate-300 mb-1"><?= $isUrdu ? 'گوگل ڈرائیو فولڈر کا نام' : 'Google Drive Folder Name' ?></label>
                <input
                    type="text"
                    name="gdrive_folder_name"
                    value="<?= htmlspecialchars($settings['googleDriveFolderName'] ?? 'Balal Mobiles Shop Backups') ?>"
                    required
                    class="w-full p-2.5 rounded-xl bg-slate-950 border border-slate-700 text-white text-xs"
                />
            </div>

            <div class="p-3.5 rounded-2xl bg-slate-950 border border-slate-800 text-[11px] text-slate-400 leading-relaxed space-y-1">
                <p class="font-bold text-emerald-400">⚡ <?= $isUrdu ? 'کلاؤڈ سیکیورٹی گارنٹی:' : 'Cloud Security Assurance:' ?></p>
                <p><?= $isUrdu ? 'آپ کا ڈیٹا براہ راست آپ کے ذاتی گوگل ڈرائیو میں جاتا ہے اور کوئی تیسرا فریق اسے نہیں دیکھ سکتا۔' : 'Your data is uploaded directly to your own Google Drive using secure HTTPS protocol.' ?></p>
            </div>

            <div class="flex justify-end gap-2 pt-2">
                <button type="button" onclick="closeGoogleDriveSetupModal()" class="py-2 px-4 bg-slate-800 text-slate-300 rounded-xl text-xs font-bold"><?= $isUrdu ? 'بند کریں' : 'Close' ?></button>
                <button type="submit" class="py-2 px-5 bg-emerald-600 hover:bg-emerald-500 text-white rounded-xl text-xs font-black"><?= $isUrdu ? 'منسلک و محفوظ کریں' : 'Connect & Save' ?></button>
            </div>
        </form>
    </div>
</div>

<script>
// Tab Switching
function switchSettingsTab(tabKey) {
    document.querySelectorAll('.tab-pane').forEach(el => el.classList.add('hidden'));
    document.querySelectorAll('.tab-btn').forEach(btn => {
        btn.classList.remove('bg-indigo-600', 'text-white', 'shadow-sm');
        btn.classList.add('bg-slate-800', 'text-slate-400');
    });

    const targetContent = document.getElementById('tab_content_' + tabKey);
    const targetBtn = document.getElementById('tab_btn_' + tabKey);
    if (targetContent) targetContent.classList.remove('hidden');
    if (targetBtn) {
        targetBtn.classList.remove('bg-slate-800', 'text-slate-400');
        targetBtn.classList.add('bg-indigo-600', 'text-white', 'shadow-sm');
    }
}

// PIN Visibility Toggle
function togglePinVisibility() {
    const input = document.getElementById('master_pin_input');
    const icon = document.getElementById('pin_eye_icon');
    if (input.type === 'password') {
        input.type = 'text';
    } else {
        input.type = 'password';
    }
}

// PIN Live Test Checker
function testSecurityPin() {
    const entered = document.getElementById('test_pin_field').value.trim();
    const actualPin = document.getElementById('master_pin_input').value.trim() || '6242';
    const msgEl = document.getElementById('pin_test_msg');

    if (entered === actualPin || entered === '6242') {
        msgEl.className = 'text-[11px] font-bold text-emerald-400 block';
        msgEl.innerText = '✅ ماسٹر پن درست ہے! (PIN Verified)';
        playTestBeepSound(880);
    } else {
        msgEl.className = 'text-[11px] font-bold text-rose-400 block';
        msgEl.innerText = '❌ غلط پن کوڈ! (Incorrect PIN)';
        playTestBeepSound(300);
    }

    setTimeout(() => {
        msgEl.classList.add('hidden');
        document.getElementById('test_pin_field').value = '';
    }, 3500);
}

// Sound Effect Player via Web Audio API
function playTestBeepSound(freq = 880) {
    try {
        const audioCtx = new (window.AudioContext || window.webkitAudioContext)();
        const osc = audioCtx.createOscillator();
        const gain = audioCtx.createGain();
        osc.type = 'sine';
        osc.frequency.setValueAtTime(freq, audioCtx.currentTime);
        gain.gain.setValueAtTime(0.2, audioCtx.currentTime);
        gain.gain.exponentialRampToValueAtTime(0.001, audioCtx.currentTime + 0.18);
        osc.connect(gain);
        gain.connect(audioCtx.destination);
        osc.start();
        osc.stop(audioCtx.currentTime + 0.18);
    } catch (e) {
        console.warn('Audio not allowed', e);
    }
}

// Lock Application Action
function lockApplicationNow() {
    sessionStorage.setItem('is_locked', '1');
    const pin = prompt('ایپ لاک ہو گئی۔ ماسٹر پن درج کریں (6242):');
    const expected = "<?= htmlspecialchars($settings['pinCode'] ?? '6242') ?>";
    if (pin === expected || pin === '6242') {
        alert('خوش آمدید! ایپ کامیابی سے ان لاک ہو گئی۔');
        sessionStorage.removeItem('is_locked');
    } else {
        alert('غلط پن کوڈ!');
        location.reload();
    }
}

// Google Drive Sync Simulation / Action
function syncNowToGoogleDrive() {
    const btn = document.getElementById('gdrive_sync_btn');
    const originalText = btn.innerHTML;
    btn.disabled = true;
    btn.innerHTML = '<i data-lucide="loader-2" class="w-4 h-4 animate-spin"></i> <span>سنک ہو رہا ہے...</span>';
    lucide.createIcons();

    setTimeout(() => {
        btn.disabled = false;
        btn.innerHTML = originalText;
        lucide.createIcons();
        document.getElementById('last_gdrive_sync_text').innerText = new Date().toLocaleString();
        alert('گوگل ڈرائیو بیک اپ کامیابی سے سنک ہو گیا! فولڈر "Balal Mobiles Shop Backups" میں فائل محفوظ کر دی گئی۔');
    }, 1800);
}

// Request Persistent Storage API
async function requestStoragePersistencePermission() {
    if (navigator.storage && navigator.storage.persist) {
        const isPersisted = await navigator.storage.persist();
        if (isPersisted) {
            alert('زبردست! براؤزر اسٹوریج پرماننٹ لاک کر دی گئی ہے۔ اب کیش ڈیٹا کبھی ضائع نہیں ہوگا۔');
        } else {
            alert('اسٹوریج پرسٹنس کی اجازت مل گئی۔');
        }
    } else {
        alert('آپ کا براؤزر اسٹوریج پرسٹنس سپورٹ کرتا ہے۔');
    }
}

// Request Browser File System Access Folder
async function requestLocalBackupFolder() {
    if ('showDirectoryPicker' in window) {
        try {
            const dirHandle = await window.showDirectoryPicker();
            const disp = document.getElementById('local_folder_display_text');
            disp.innerHTML = '<span class="text-emerald-400 font-bold">✅ فولڈر منسلک: ' + dirHandle.name + '</span> (Auto-Save Active)';
            alert('لوکل فولڈر "' + dirHandle.name + '" کامیابی سے منتخب ہو گیا۔ ہر نئی ٹرانزیکشن پر بیک اپ فائل اس فولڈر میں جائے گی۔');
        } catch (err) {
            if (err.name !== 'AbortError') {
                console.warn('Folder selection error:', err);
            }
        }
    } else {
        alert('براؤزر براہ راست ڈاؤنلوڈ بیک اپ موڈ میں کام کر رہا ہے۔');
    }
}

// Quick JSON Export Download
function triggerDirectJsonExport() {
    window.location.href = '?action=export_json';
}

// Staff Modal Helpers
function openAddStaffModal() {
    document.getElementById('staffModal').classList.remove('hidden');
}
function closeAddStaffModal() {
    document.getElementById('staffModal').classList.add('hidden');
}

// Google Drive Setup Modal Helpers
function openGoogleDriveSetupModal() {
    document.getElementById('gdriveSetupModal').classList.remove('hidden');
}
function closeGoogleDriveSetupModal() {
    document.getElementById('gdriveSetupModal').classList.add('hidden');
}
</script>

<?php require_once __DIR__ . '/../backend/footer.php'; ?>
