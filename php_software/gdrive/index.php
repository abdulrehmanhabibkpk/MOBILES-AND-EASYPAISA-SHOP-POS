<?php
require_once __DIR__ . '/../backend/config.php';

// Strict Authentication Guard
requireAuth($pdo);

$pageTitle = $isUrdu ? 'گوگل ڈرائیو کلاؤڈ مینیجر (Google Drive)' : 'Google Drive Cloud Explorer';
$activeMenu = 'gdrive';

// Load Firebase configuration
$firebaseConfigFile = __DIR__ . '/../../firebase-applet-config.json';
$firebaseConfig = [];
if (file_exists($firebaseConfigFile)) {
    $firebaseConfig = json_decode(file_get_contents($firebaseConfigFile), true) ?: [];
}

// Handle Direct PHP Data Export Endpoint for 1-Click Google Drive Cloud Backup
if (isset($_GET['api_action']) && $_GET['api_action'] === 'get_shop_export_payload') {
    header('Content-Type: application/json; charset=utf-8');
    try {
        $exportData = [
            'version' => '3.0-gdrive',
            'appName' => $settings['shopName'] ?? 'LimoMobile POS & EasyPaisa Shop',
            'exportDate' => date('c'),
            'timestamp' => time(),
            'products' => $pdo->query("SELECT * FROM products ORDER BY id DESC")->fetchAll(PDO::FETCH_ASSOC),
            'productSales' => $pdo->query("SELECT * FROM product_sales ORDER BY id DESC")->fetchAll(PDO::FETCH_ASSOC),
            'transactions' => $pdo->query("SELECT * FROM transactions ORDER BY id DESC")->fetchAll(PDO::FETCH_ASSOC),
            'mobilePurchases' => $pdo->query("SELECT * FROM mobile_purchases ORDER BY id DESC")->fetchAll(PDO::FETCH_ASSOC),
            'suppliers' => $pdo->query("SELECT * FROM suppliers ORDER BY id DESC")->fetchAll(PDO::FETCH_ASSOC),
            'customers' => $pdo->query("SELECT * FROM customers ORDER BY id DESC")->fetchAll(PDO::FETCH_ASSOC),
            'customerEntries' => $pdo->query("SELECT * FROM customer_khata_entries ORDER BY id DESC")->fetchAll(PDO::FETCH_ASSOC),
            'dailyBalances' => $pdo->query("SELECT * FROM daily_balances ORDER BY date DESC")->fetchAll(PDO::FETCH_ASSOC),
            'settings' => getShopSettings($pdo),
        ];
        echo json_encode(['success' => true, 'data' => $exportData], JSON_UNESCAPED_UNICODE);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
    exit();
}

// Handle Restore from Google Drive Payload
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['api_action']) && $_POST['api_action'] === 'restore_gdrive_payload') {
    header('Content-Type: application/json; charset=utf-8');
    try {
        $raw = file_get_contents('php://input');
        $payload = json_decode($raw, true);
        if (!$payload || !is_array($payload)) {
            echo json_encode(['success' => false, 'error' => 'Invalid backup payload']);
            exit();
        }

        $pdo->beginTransaction();

        // 1. Restore products
        if (isset($payload['products']) && is_array($payload['products'])) {
            $stmtProd = $pdo->prepare("REPLACE INTO products (id, name, category, purchase_price, sale_price, stock, image, brand_or_model, imei_or_serial, sku, units, created_at) VALUES (:id, :name, :category, :purchase_price, :sale_price, :stock, :image, :brand_or_model, :imei_or_serial, :sku, :units, :created_at)");
            foreach ($payload['products'] as $p) {
                $unitsVal = isset($p['units']) ? (is_array($p['units']) ? json_encode($p['units'], JSON_UNESCAPED_UNICODE) : strval($p['units'])) : null;
                $stmtProd->execute([
                    ':id' => $p['id'] ?? ('prod-' . uniqid()),
                    ':name' => $p['name'] ?? 'Product',
                    ':category' => $p['category'] ?? 'ACCESSORIES',
                    ':purchase_price' => $p['purchase_price'] ?? $p['purchasePrice'] ?? 0,
                    ':sale_price' => $p['sale_price'] ?? $p['salePrice'] ?? 0,
                    ':stock' => $p['stock'] ?? 0,
                    ':image' => $p['image'] ?? null,
                    ':brand_or_model' => $p['brand_or_model'] ?? $p['brandOrModel'] ?? null,
                    ':imei_or_serial' => $p['imei_or_serial'] ?? $p['imeiOrSerial'] ?? null,
                    ':sku' => $p['sku'] ?? null,
                    ':units' => $unitsVal,
                    ':created_at' => $p['created_at'] ?? $p['createdAt'] ?? time()
                ]);
            }
        }

        // 2. Restore transactions
        if (isset($payload['transactions']) && is_array($payload['transactions'])) {
            $stmtTrx = $pdo->prepare("REPLACE INTO transactions (id, type, customer_name, customer_phone, easypaisa_amount, cash_amount, expense_amount, fee_profit, payment_method, notes, date, time, created_at) VALUES (:id, :type, :customer_name, :customer_phone, :easypaisa_amount, :cash_amount, :expense_amount, :fee_profit, :payment_method, :notes, :date, :time, :created_at)");
            foreach ($payload['transactions'] as $t) {
                $stmtTrx->execute([
                    ':id' => $t['id'] ?? ('trx-' . uniqid()),
                    ':type' => $t['type'] ?? 'SELL_CASH',
                    ':customer_name' => $t['customer_name'] ?? $t['customerName'] ?? '',
                    ':customer_phone' => $t['customer_phone'] ?? $t['customerPhone'] ?? '',
                    ':easypaisa_amount' => $t['easypaisa_amount'] ?? $t['easyPaisaAmount'] ?? 0,
                    ':cash_amount' => $t['cash_amount'] ?? $t['cashAmount'] ?? 0,
                    ':expense_amount' => $t['expense_amount'] ?? $t['expenseAmount'] ?? 0,
                    ':fee_profit' => $t['fee_profit'] ?? $t['feeProfit'] ?? 0,
                    ':payment_method' => $t['payment_method'] ?? $t['paymentMethod'] ?? 'CASH',
                    ':notes' => $t['notes'] ?? '',
                    ':date' => $t['date'] ?? date('Y-m-d'),
                    ':time' => $t['time'] ?? date('h:i A'),
                    ':created_at' => $t['created_at'] ?? $t['createdAt'] ?? time()
                ]);
            }
        }

        // 3. Restore Mobile Purchases
        if (isset($payload['mobilePurchases']) && is_array($payload['mobilePurchases'])) {
            $stmtMob = $pdo->prepare("REPLACE INTO mobile_purchases (id, seller_name, seller_cnic, seller_phone, seller_address, mobile_brand_model, imei1, imei2, condition_state, purchase_price, payment_method, notes, agreement_accepted, seller_photo, id_card_front_photo, id_card_back_photo, mobile_photo, date, time, created_at) VALUES (:id, :seller_name, :seller_cnic, :seller_phone, :seller_address, :mobile_brand_model, :imei1, :imei2, :condition_state, :purchase_price, :payment_method, :notes, :agreement_accepted, :seller_photo, :id_card_front_photo, :id_card_back_photo, :mobile_photo, :date, :time, :created_at)");
            foreach ($payload['mobilePurchases'] as $m) {
                $stmtMob->execute([
                    ':id' => $m['id'] ?? ('pur-' . uniqid()),
                    ':seller_name' => $m['seller_name'] ?? $m['sellerName'] ?? '',
                    ':seller_cnic' => $m['seller_cnic'] ?? $m['sellerCnic'] ?? '',
                    ':seller_phone' => $m['seller_phone'] ?? $m['sellerPhone'] ?? '',
                    ':seller_address' => $m['seller_address'] ?? $m['sellerAddress'] ?? '',
                    ':mobile_brand_model' => $m['mobile_brand_model'] ?? $m['mobileBrandModel'] ?? '',
                    ':imei1' => $m['imei1'] ?? '',
                    ':imei2' => $m['imei2'] ?? '',
                    ':condition_state' => $m['condition_state'] ?? $m['condition'] ?? 'USED',
                    ':purchase_price' => $m['purchase_price'] ?? $m['purchasePrice'] ?? 0,
                    ':payment_method' => $m['payment_method'] ?? $m['paymentMethod'] ?? 'CASH',
                    ':notes' => $m['notes'] ?? '',
                    ':agreement_accepted' => !empty($m['agreement_accepted'] ?? $m['agreementAccepted']) ? 1 : 0,
                    ':seller_photo' => $m['seller_photo'] ?? $m['sellerPhoto'] ?? null,
                    ':id_card_front_photo' => $m['id_card_front_photo'] ?? $m['idCardFrontPhoto'] ?? null,
                    ':id_card_back_photo' => $m['id_card_back_photo'] ?? $m['idCardBackPhoto'] ?? null,
                    ':mobile_photo' => $m['mobile_photo'] ?? $m['mobilePhoto'] ?? null,
                    ':date' => $m['date'] ?? date('Y-m-d'),
                    ':time' => $m['time'] ?? date('h:i A'),
                    ':created_at' => $m['created_at'] ?? $m['createdAt'] ?? time()
                ]);
            }
        }

        $pdo->commit();
        echo json_encode(['success' => true, 'message' => 'Shop data successfully restored from Google Drive!']);
    } catch (Exception $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
    exit();
}

require_once __DIR__ . '/../backend/header.php';
?>

<!-- Google Drive Explorer Container -->
<div class="space-y-5 pb-10">

    <!-- Top Hero Banner with Google Drive Status -->
    <div class="p-5 sm:p-7 rounded-3xl bg-gradient-to-r from-emerald-950/70 via-slate-900 to-sky-950/60 border border-emerald-500/30 shadow-xl relative overflow-hidden">
        <div class="absolute -right-10 -bottom-10 w-48 h-48 bg-emerald-500/10 rounded-full blur-3xl pointer-events-none"></div>
        <div class="absolute -left-10 -top-10 w-48 h-48 bg-sky-500/10 rounded-full blur-3xl pointer-events-none"></div>

        <div class="relative z-10 flex flex-col md:flex-row md:items-center justify-between gap-5">
            <div class="flex items-start sm:items-center gap-4">
                <div class="w-14 h-14 rounded-2xl bg-white text-slate-900 flex items-center justify-center shadow-lg p-2.5 shrink-0">
                    <!-- Official Google Drive SVG Logo -->
                    <svg viewBox="0 0 87.3 78" class="w-full h-full">
                        <path d="m6.6 66.85 3.85 6.65c.8 1.4 1.95 2.5 3.3 3.3l13.75-23.8H0c0 1.55.4 3.1 1.2 4.5z" fill="#0066da"/>
                        <path d="m43.65 25-13.75-23.8c-1.35.8-2.5 1.9-3.3 3.3l-25.4 44a9.06 9.06 0 0 0 -1.2 4.5h27.5z" fill="#00ac47"/>
                        <path d="m73.55 76.8c1.35-.8 2.5-1.9 3.3-3.3l1.6-2.75 7.65-13.25c.8-1.4 1.2-2.95 1.2-4.5h-27.502l5.852 11.5z" fill="#ea4335"/>
                        <path d="m43.65 25 13.75-23.8c-1.35-.8-2.9-1.2-4.5-1.2h-18.5c-1.6 0-3.15.45-4.5 1.2z" fill="#00832d"/>
                        <path d="m59.8 53h-32.3l-13.75 23.8c1.35.8 2.9 1.2 4.5 1.2h50.8c1.6 0 3.15-.45 4.5-1.2z" fill="#26842a"/>
                        <path d="m73.4 26.5-12.7-22c-.8-1.4-1.95-2.5-3.3-3.3l-13.75 23.8 16.15 28h27.45c0-1.55-.4-3.1-1.2-4.5z" fill="#ffba00"/>
                    </svg>
                </div>
                <div>
                    <div class="flex items-center gap-2.5 flex-wrap">
                        <h2 class="text-xl sm:text-2xl font-black text-white tracking-tight">
                            <?= $isUrdu ? 'گوگل ڈرائیو فائلز و فولڈرز کلاؤڈ مینیجر' : 'Google Drive Cloud File Explorer' ?>
                        </h2>
                        <span id="gdrive_auth_badge" class="px-3 py-0.5 rounded-full text-xs font-black uppercase tracking-wider bg-slate-800 text-slate-400 border border-slate-700">
                            Checking...
                        </span>
                    </div>
                    <p class="text-xs sm:text-sm text-slate-300 mt-1 leading-relaxed max-w-2xl">
                        <?= $isUrdu 
                            ? 'آپ کے ذاتی گوگل ڈرائیو سے براہ راست مربوط ہے۔ فولڈرز بنائیں، فائلیں اپلوڈ و ڈاؤنلوڈ کریں اور دکان کا مکمل لائیو ڈیٹا محفوظ رکھیں۔' 
                            : 'Direct access to your Google Drive files and folders. Create directories, upload documents, and backup your shop records securely.' ?>
                    </p>
                </div>
            </div>

            <!-- Google Sign-In / Account Action Button -->
            <div id="gdrive_auth_actions" class="flex items-center gap-3 shrink-0">
                <!-- Sign in with Google Button (GSI Material Style) -->
                <button
                    type="button"
                    id="gdrive_login_btn"
                    onclick="handleGoogleDriveSignIn()"
                    class="gsi-material-button bg-white hover:bg-slate-50 text-slate-900 px-4 py-2.5 rounded-2xl font-bold text-xs sm:text-sm flex items-center gap-2.5 shadow-lg shadow-emerald-500/10 hover:shadow-emerald-500/20 transition-all border border-slate-200 cursor-pointer active:scale-95"
                >
                    <svg version="1.1" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 48 48" class="w-4 h-4">
                        <path fill="#EA4335" d="M24 9.5c3.54 0 6.71 1.22 9.21 3.6l6.85-6.85C35.9 2.38 30.47 0 24 0 14.62 0 6.51 5.38 2.56 13.22l7.98 6.19C12.43 13.72 17.74 9.5 24 9.5z"></path>
                        <path fill="#4285F4" d="M46.98 24.55c0-1.57-.15-3.09-.38-4.55H24v9.02h12.94c-.58 2.96-2.26 5.48-4.78 7.18l7.73 6c4.51-4.18 7.09-10.36 7.09-17.65z"></path>
                        <path fill="#FBBC05" d="M10.53 28.59c-.48-1.45-.76-2.99-.76-4.59s.27-3.14.76-4.59l-7.98-6.19C.92 16.46 0 20.12 0 24c0 3.88.92 7.54 2.56 10.78l7.97-6.19z"></path>
                        <path fill="#34A853" d="M24 48c6.48 0 11.93-2.13 15.89-5.81l-7.73-6c-2.15 1.45-4.92 2.3-8.16 2.3-6.26 0-11.57-4.22-13.47-9.91l-7.98 6.19C6.51 42.62 14.62 48 24 48z"></path>
                    </svg>
                    <span><?= $isUrdu ? 'گوگل ڈرائیو کنیکٹ کریں' : 'Connect Google Drive' ?></span>
                </button>

                <!-- Connected Profile Pill -->
                <div id="gdrive_user_profile" class="hidden items-center gap-2 bg-slate-900/90 border border-emerald-500/40 p-1.5 pr-3 rounded-2xl">
                    <img id="gdrive_user_avatar" src="" alt="Avatar" class="w-8 h-8 rounded-xl object-cover bg-slate-800" referrerpolicy="no-referrer">
                    <div class="min-w-0 text-left rtl:text-right">
                        <span id="gdrive_user_name" class="font-bold text-xs text-white block truncate">User</span>
                        <span id="gdrive_user_email" class="text-[10px] text-emerald-400 font-mono block truncate">email@gmail.com</span>
                    </div>
                    <button
                        type="button"
                        onclick="handleGoogleDriveSignOut()"
                        title="<?= $isUrdu ? 'گوگل ڈرائیو منقطع کریں' : 'Disconnect Google Drive' ?>"
                        class="p-1.5 rounded-lg text-slate-400 hover:text-rose-400 hover:bg-rose-500/10 transition-colors ml-1 rtl:mr-1 rtl:ml-0 cursor-pointer"
                    >
                        <i data-lucide="log-out" class="w-4 h-4"></i>
                    </button>
                </div>
            </div>
        </div>

        <!-- Drive Quick Stats & Backup Toolbar -->
        <div class="mt-5 pt-4 border-t border-slate-800 flex flex-wrap items-center justify-between gap-3 text-xs">
            <div class="flex items-center gap-3 text-slate-300">
                <span class="flex items-center gap-1 text-emerald-400 font-bold">
                    <i data-lucide="shield-check" class="w-4 h-4"></i>
                    <span>OAuth 2.0 SSL Encrypted</span>
                </span>
                <span class="hidden sm:inline text-slate-600">•</span>
                <span class="text-slate-400 hidden sm:inline" id="gdrive_quota_info">Storage: Loading...</span>
            </div>

            <!-- Quick Shop Cloud Backup Buttons -->
            <div class="flex items-center gap-2 flex-wrap">
                <button
                    type="button"
                    onclick="backupShopToGoogleDrive()"
                    id="btn_backup_shop"
                    class="py-2 px-3.5 rounded-xl bg-gradient-to-r from-emerald-600 to-teal-600 hover:from-emerald-500 hover:to-teal-500 text-white font-black text-xs flex items-center gap-1.5 shadow-md transition-all cursor-pointer hover:scale-[1.02] active:scale-95"
                >
                    <i data-lucide="cloud-upload" class="w-4 h-4"></i>
                    <span><?= $isUrdu ? 'دکان کا فوری بیک اپ ڈرائیو پر محفوظ کریں' : 'Save Full Shop Backup to Drive' ?></span>
                </button>

                <button
                    type="button"
                    onclick="openCreateFolderModal()"
                    class="py-2 px-3.5 rounded-xl bg-slate-800 hover:bg-slate-700 text-slate-200 border border-slate-700 font-bold text-xs flex items-center gap-1.5 transition-colors cursor-pointer"
                >
                    <i data-lucide="folder-plus" class="w-4 h-4 text-amber-400"></i>
                    <span><?= $isUrdu ? 'نیا فولڈر' : 'New Folder' ?></span>
                </button>

                <button
                    type="button"
                    onclick="openUploadFileModal()"
                    class="py-2 px-3.5 rounded-xl bg-limoblue-600 hover:bg-limoblue-500 text-white font-bold text-xs flex items-center gap-1.5 shadow transition-colors cursor-pointer"
                >
                    <i data-lucide="upload" class="w-4 h-4"></i>
                    <span><?= $isUrdu ? 'فائل اپلوڈ کریں' : 'Upload File' ?></span>
                </button>
            </div>
        </div>
    </div>

    <!-- Google Drive File Browser Card -->
    <div class="bg-slate-900 border border-slate-800 rounded-3xl p-5 sm:p-6 shadow-sm space-y-4">
        
        <!-- Navigation Breadcrumbs & Search Toolbar -->
        <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 pb-3 border-b border-slate-800">
            <!-- Breadcrumb Path -->
            <div class="flex items-center gap-1.5 text-xs sm:text-sm overflow-x-auto py-1 scrollbar-none" id="gdrive_breadcrumbs">
                <button
                    type="button"
                    onclick="navigateToFolder('root', 'My Drive')"
                    class="font-bold text-emerald-400 hover:text-emerald-300 flex items-center gap-1 shrink-0 cursor-pointer"
                >
                    <i data-lucide="hard-drive" class="w-4 h-4"></i>
                    <span>My Drive</span>
                </button>
                <span class="text-slate-600">/</span>
            </div>

            <!-- Search and View Toggle -->
            <div class="flex items-center gap-2.5 justify-between sm:justify-end">
                <div class="relative min-w-[220px] sm:min-w-[280px]">
                    <i data-lucide="search" class="w-4 h-4 text-slate-500 absolute left-3 rtl:right-3 rtl:left-auto top-1/2 -translate-y-1/2"></i>
                    <input
                        type="text"
                        id="gdrive_search_input"
                        placeholder="<?= $isUrdu ? 'ڈرائیو میں تلاش کریں...' : 'Search Google Drive files...' ?>"
                        onkeyup="handleDriveSearch(event)"
                        class="w-full py-2 pl-9 pr-3 rtl:pr-9 rtl:pl-3 bg-slate-950 border border-slate-700 rounded-xl text-xs text-white placeholder:text-slate-500 focus:border-emerald-500 focus:outline-none"
                    />
                </div>

                <button
                    type="button"
                    onclick="refreshCurrentFolder()"
                    title="<?= $isUrdu ? 'ریفریش کریں' : 'Refresh' ?>"
                    class="p-2 rounded-xl bg-slate-800 hover:bg-slate-700 text-slate-300 border border-slate-700 cursor-pointer"
                >
                    <i data-lucide="rotate-cw" class="w-4 h-4" id="refresh_icon"></i>
                </button>

                <!-- Grid / List Toggle -->
                <div class="flex items-center bg-slate-950 p-1 rounded-xl border border-slate-800">
                    <button
                        type="button"
                        id="btn_view_grid"
                        onclick="switchViewMode('grid')"
                        class="p-1.5 rounded-lg text-emerald-400 bg-slate-800 cursor-pointer"
                        title="Grid View"
                    >
                        <i data-lucide="grid" class="w-4 h-4"></i>
                    </button>
                    <button
                        type="button"
                        id="btn_view_list"
                        onclick="switchViewMode('list')"
                        class="p-1.5 rounded-lg text-slate-400 hover:text-white cursor-pointer"
                        title="List View"
                    >
                        <i data-lucide="list" class="w-4 h-4"></i>
                    </button>
                </div>
            </div>
        </div>

        <!-- Files & Folders Container -->
        <div id="gdrive_content_area" class="min-h-[320px] relative">
            <!-- Loading State -->
            <div id="gdrive_loading_state" class="py-16 text-center space-y-3">
                <i data-lucide="loader-2" class="w-8 h-8 text-emerald-400 animate-spin mx-auto"></i>
                <p class="text-xs text-slate-400 font-bold"><?= $isUrdu ? 'گوگل ڈرائیو سے فائلیں لوڈ ہو رہی ہیں...' : 'Connecting and loading Google Drive items...' ?></p>
            </div>

            <!-- Not Signed In State -->
            <div id="gdrive_unauth_state" class="hidden py-16 text-center space-y-4 max-w-md mx-auto">
                <div class="w-16 h-16 rounded-3xl bg-emerald-500/10 border border-emerald-500/20 text-emerald-400 flex items-center justify-center mx-auto">
                    <i data-lucide="cloud-off" class="w-8 h-8"></i>
                </div>
                <div>
                    <h3 class="font-black text-white text-base"><?= $isUrdu ? 'گوگل ڈرائیو کنیکٹ نہیں ہے' : 'Google Drive Disconnected' ?></h3>
                    <p class="text-xs text-slate-400 mt-1">
                        <?= $isUrdu 
                            ? 'اپنی تمام فائلیں، فولڈرز اور کلاؤڈ بیک اپ دیکھنے کے لیے نیچے دیے گئے بٹن پر کلک کر کے گوگل اکاؤنٹ سے لاگ ان کریں۔' 
                            : 'Sign in with your Google account to view, manage files and automate backups in Google Drive.' ?>
                    </p>
                </div>
                <button
                    type="button"
                    onclick="handleGoogleDriveSignIn()"
                    class="py-3 px-6 rounded-2xl bg-emerald-600 hover:bg-emerald-500 text-white font-black text-xs sm:text-sm inline-flex items-center gap-2 shadow-lg transition-all cursor-pointer"
                >
                    <i data-lucide="log-in" class="w-4 h-4"></i>
                    <span><?= $isUrdu ? 'گوگل اکاؤنٹ لاگ ان کریں' : 'Sign in with Google' ?></span>
                </button>
            </div>

            <!-- Items Render View (Grid / List) -->
            <div id="gdrive_items_grid" class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-5 gap-3.5"></div>
            <div id="gdrive_items_list" class="hidden overflow-x-auto">
                <table class="w-full text-left rtl:text-right text-xs">
                    <thead>
                        <tr class="text-slate-400 border-b border-slate-800">
                            <th class="p-3"><?= $isUrdu ? 'نام' : 'Name' ?></th>
                            <th class="p-3"><?= $isUrdu ? 'قسم' : 'Type' ?></th>
                            <th class="p-3"><?= $isUrdu ? 'سائز' : 'Size' ?></th>
                            <th class="p-3"><?= $isUrdu ? 'آخری تبدیلی' : 'Modified' ?></th>
                            <th class="p-3 text-center"><?= $isUrdu ? 'ایکشن' : 'Actions' ?></th>
                        </tr>
                    </thead>
                    <tbody id="gdrive_items_list_tbody" class="divide-y divide-slate-800/60"></tbody>
                </table>
            </div>

            <!-- Empty State -->
            <div id="gdrive_empty_state" class="hidden py-16 text-center space-y-3">
                <i data-lucide="folder-open" class="w-12 h-12 text-slate-600 mx-auto"></i>
                <p class="text-xs text-slate-400 font-bold"><?= $isUrdu ? 'اس فولڈر میں کوئی فائل یا فولڈر موجود نہیں ہے' : 'This Google Drive folder is empty' ?></p>
            </div>
        </div>

    </div>

</div>

<!-- Modal: Create New Folder -->
<div id="createFolderModal" class="fixed inset-0 bg-black/80 backdrop-blur-sm z-50 flex items-center justify-center p-4 hidden">
    <div class="bg-slate-900 border border-slate-800 rounded-3xl p-6 max-w-sm w-full space-y-4 shadow-2xl animate-scale-up">
        <div class="flex items-center justify-between border-b border-slate-800 pb-3">
            <div class="flex items-center gap-2 text-amber-400 font-black text-sm">
                <i data-lucide="folder-plus" class="w-5 h-5"></i>
                <span><?= $isUrdu ? 'نیا گوگل ڈرائیو فولڈر بنائیں' : 'Create Google Drive Folder' ?></span>
            </div>
            <button type="button" onclick="closeCreateFolderModal()" class="text-slate-400 hover:text-white">✕</button>
        </div>

        <div class="space-y-2">
            <label class="block text-xs font-bold text-slate-300"><?= $isUrdu ? 'فولڈر کا نام درج کریں:' : 'Folder Name:' ?></label>
            <input
                type="text"
                id="new_folder_name_input"
                placeholder="e.g. Shop Invoices 2026"
                class="w-full p-3 rounded-xl bg-slate-950 border border-slate-700 text-white text-xs font-bold focus:border-amber-500 focus:outline-none"
            />
        </div>

        <div class="flex justify-end gap-2 pt-2">
            <button type="button" onclick="closeCreateFolderModal()" class="py-2 px-4 bg-slate-800 text-slate-300 rounded-xl text-xs font-bold"><?= $isUrdu ? 'منسوخ' : 'Cancel' ?></button>
            <button type="button" onclick="submitCreateFolder()" id="btn_submit_folder" class="py-2 px-5 bg-amber-600 hover:bg-amber-500 text-white rounded-xl text-xs font-black"><?= $isUrdu ? 'بنائیں' : 'Create' ?></button>
        </div>
    </div>
</div>

<!-- Modal: Upload File to Google Drive -->
<div id="uploadFileModal" class="fixed inset-0 bg-black/80 backdrop-blur-sm z-50 flex items-center justify-center p-4 hidden">
    <div class="bg-slate-900 border border-slate-800 rounded-3xl p-6 max-w-md w-full space-y-4 shadow-2xl animate-scale-up">
        <div class="flex items-center justify-between border-b border-slate-800 pb-3">
            <div class="flex items-center gap-2 text-limoblue-400 font-black text-sm">
                <i data-lucide="upload-cloud" class="w-5 h-5"></i>
                <span><?= $isUrdu ? 'گوگل ڈرائیو میں فائل اپلوڈ کریں' : 'Upload File to Google Drive' ?></span>
            </div>
            <button type="button" onclick="closeUploadFileModal()" class="text-slate-400 hover:text-white">✕</button>
        </div>

        <div class="p-6 rounded-2xl bg-slate-950 border-2 border-dashed border-slate-700 text-center space-y-3 cursor-pointer hover:border-limoblue-500 transition-colors" onclick="document.getElementById('drive_file_input').click()">
            <i data-lucide="file-up" class="w-10 h-10 text-limoblue-400 mx-auto"></i>
            <div>
                <p class="text-xs font-bold text-white"><?= $isUrdu ? 'فائل منتخب کرنے کے لیے کلک کریں' : 'Click to browse file' ?></p>
                <p class="text-[11px] text-slate-400 mt-0.5">Documents, Images, JSON Backups, PDFs, CSV, ZIP</p>
            </div>
            <input type="file" id="drive_file_input" class="hidden" onchange="handleFileSelectedForUpload(event)">
        </div>

        <div id="selected_file_preview" class="hidden p-3 rounded-xl bg-slate-950 border border-slate-800 text-xs text-slate-300">
            <span class="font-bold text-white block" id="selected_file_name">file.ext</span>
            <span class="text-[10px] text-slate-400 font-mono" id="selected_file_size">0 KB</span>
        </div>

        <div class="flex justify-end gap-2 pt-2">
            <button type="button" onclick="closeUploadFileModal()" class="py-2 px-4 bg-slate-800 text-slate-300 rounded-xl text-xs font-bold"><?= $isUrdu ? 'منسوخ' : 'Cancel' ?></button>
            <button type="button" onclick="submitUploadFile()" id="btn_submit_upload" disabled class="py-2 px-5 bg-limoblue-600 disabled:opacity-50 text-white rounded-xl text-xs font-black cursor-pointer"><?= $isUrdu ? 'اپلوڈ کریں' : 'Upload' ?></button>
        </div>
    </div>
</div>

<!-- Modal: Confirm Destructive Action (Mandatory as per workspace skill) -->
<div id="deleteConfirmModal" class="fixed inset-0 bg-black/80 backdrop-blur-sm z-50 flex items-center justify-center p-4 hidden">
    <div class="bg-slate-900 border border-rose-500/40 rounded-3xl p-6 max-w-sm w-full space-y-4 shadow-2xl animate-scale-up">
        <div class="flex items-center gap-2 text-rose-400 font-black text-sm border-b border-rose-500/20 pb-3">
            <i data-lucide="alert-triangle" class="w-5 h-5"></i>
            <span><?= $isUrdu ? 'فائل ڈیلیٹ کرنے کی تصدیق' : 'Confirm Google Drive Deletion' ?></span>
        </div>

        <p class="text-xs text-slate-300 leading-relaxed" id="delete_confirm_message">
            <?= $isUrdu ? 'کیا آپ واقعی اس فائل کو گوگل ڈرائیو سے ڈیلیٹ کرنا چاہتے ہیں؟' : 'Are you sure you want to delete this item from Google Drive? This action cannot be undone.' ?>
        </p>

        <div class="flex justify-end gap-2 pt-2">
            <button type="button" onclick="closeDeleteConfirmModal()" class="py-2 px-4 bg-slate-800 text-slate-300 rounded-xl text-xs font-bold"><?= $isUrdu ? 'منسوخ' : 'Cancel' ?></button>
            <button type="button" onclick="executeDeleteDriveItem()" id="btn_confirm_delete" class="py-2 px-5 bg-rose-600 hover:bg-rose-500 text-white rounded-xl text-xs font-black"><?= $isUrdu ? 'ڈیلیٹ کریں' : 'Delete Item' ?></button>
        </div>
    </div>
</div>

<!-- Firebase SDK & Google Drive Client-Side Authentication -->
<script type="module">
import { initializeApp } from "https://www.gstatic.com/firebasejs/10.8.0/firebase-app.js";
import { getAuth, GoogleAuthProvider, signInWithPopup, signOut, onAuthStateChanged } from "https://www.gstatic.com/firebasejs/10.8.0/firebase-auth.js";

const firebaseConfig = <?= json_encode($firebaseConfig, JSON_UNESCAPED_UNICODE) ?>;
const app = initializeApp(firebaseConfig);
const auth = getAuth(app);

const provider = new GoogleAuthProvider();
provider.addScope('https://www.googleapis.com/auth/drive');
provider.addScope('https://www.googleapis.com/auth/drive.file');

let cachedToken = null;
let currentFolderId = 'root';
let currentFolderName = 'My Drive';
let folderPathStack = [{ id: 'root', name: 'My Drive' }];
let currentFiles = [];
let viewMode = 'grid';
let selectedFileToUpload = null;
let itemToDelete = null;

// Expose functions to window
window.handleGoogleDriveSignIn = async function() {
    try {
        const btn = document.getElementById('gdrive_login_btn');
        if (btn) btn.disabled = true;
        const result = await signInWithPopup(auth, provider);
        const credential = GoogleAuthProvider.credentialFromResult(result);
        if (credential?.accessToken) {
            cachedToken = credential.accessToken;
            sessionStorage.setItem('gdrive_token', cachedToken);
            loadDriveFiles(currentFolderId);
        }
    } catch (err) {
        console.error('Sign-in Error:', err);
        alert('Google Drive لاگ ان میں مسئلہ: ' + (err.message || 'Access Denied'));
    } finally {
        const btn = document.getElementById('gdrive_login_btn');
        if (btn) btn.disabled = false;
    }
};

window.handleGoogleDriveSignOut = async function() {
    if (confirm('<?= $isUrdu ? 'کیا آپ واقعی گوگل ڈرائیو لاگ آؤٹ کرنا چاہتے ہیں؟' : 'Disconnect Google Drive?' ?>')) {
        await signOut(auth);
        cachedToken = null;
        sessionStorage.removeItem('gdrive_token');
        updateAuthUI(null);
    }
};

onAuthStateChanged(auth, async (user) => {
    if (user) {
        updateAuthUI(user);
        const savedToken = sessionStorage.getItem('gdrive_token');
        if (savedToken) {
            cachedToken = savedToken;
            loadDriveFiles(currentFolderId);
        } else {
            // Attempt silent or request sign-in
            document.getElementById('gdrive_loading_state').classList.add('hidden');
            document.getElementById('gdrive_unauth_state').classList.remove('hidden');
        }
    } else {
        updateAuthUI(null);
    }
});

function updateAuthUI(user) {
    const badge = document.getElementById('gdrive_auth_badge');
    const loginBtn = document.getElementById('gdrive_login_btn');
    const profilePill = document.getElementById('gdrive_user_profile');
    const unauthState = document.getElementById('gdrive_unauth_state');
    const loadingState = document.getElementById('gdrive_loading_state');

    if (user && cachedToken) {
        badge.className = 'px-3 py-0.5 rounded-full text-xs font-black uppercase tracking-wider bg-emerald-500/20 text-emerald-300 border border-emerald-500/30';
        badge.innerText = 'Connected';
        loginBtn.classList.add('hidden');
        profilePill.classList.remove('hidden');
        profilePill.classList.add('flex');
        
        document.getElementById('gdrive_user_name').innerText = user.displayName || 'Google User';
        document.getElementById('gdrive_user_email').innerText = user.email || '';
        document.getElementById('gdrive_user_avatar').src = user.photoURL || 'https://images.unsplash.com/photo-1535713875002-d1d0cf377fde?auto=format&fit=crop&w=80&q=80';
        unauthState.classList.add('hidden');
    } else {
        badge.className = 'px-3 py-0.5 rounded-full text-xs font-black uppercase tracking-wider bg-rose-500/20 text-rose-300 border border-rose-500/30';
        badge.innerText = 'Disconnected';
        loginBtn.classList.remove('hidden');
        profilePill.classList.add('hidden');
        profilePill.classList.remove('flex');
        loadingState.classList.add('hidden');
        unauthState.classList.remove('hidden');
        document.getElementById('gdrive_items_grid').innerHTML = '';
        document.getElementById('gdrive_items_list_tbody').innerHTML = '';
    }
}

// Load Files from Google Drive API v3
async function loadDriveFiles(folderId = 'root', searchQuery = '') {
    if (!cachedToken) {
        document.getElementById('gdrive_loading_state').classList.add('hidden');
        document.getElementById('gdrive_unauth_state').classList.remove('hidden');
        return;
    }

    const loadingState = document.getElementById('gdrive_loading_state');
    const emptyState = document.getElementById('gdrive_empty_state');
    const gridView = document.getElementById('gdrive_items_grid');
    const listBody = document.getElementById('gdrive_items_list_tbody');
    const refreshIcon = document.getElementById('refresh_icon');

    loadingState.classList.remove('hidden');
    emptyState.classList.add('hidden');
    if (refreshIcon) refreshIcon.classList.add('animate-spin');

    try {
        let q = `'${folderId}' in parents and trashed = false`;
        if (searchQuery.trim()) {
            q = `name contains '${searchQuery.replace(/'/g, "\\'")}' and trashed = false`;
        }

        const url = `https://www.googleapis.com/drive/v3/files?q=${encodeURIComponent(q)}&orderBy=folder,name&fields=files(id,name,mimeType,size,modifiedTime,webViewLink,webContentLink,thumbnailLink,iconLink)&pageSize=100`;
        const res = await fetch(url, {
            headers: { Authorization: `Bearer ${cachedToken}` }
        });

        if (res.status === 401) {
            // Token expired, re-prompt sign-in
            sessionStorage.removeItem('gdrive_token');
            cachedToken = null;
            updateAuthUI(null);
            return;
        }

        const data = await res.json();
        currentFiles = data.files || [];
        renderFiles(currentFiles);
    } catch (err) {
        console.error('Error loading Google Drive files:', err);
    } finally {
        loadingState.classList.add('hidden');
        if (refreshIcon) refreshIcon.classList.remove('animate-spin');
        if (window.lucide) window.lucide.createIcons();
    }
}

function renderFiles(files) {
    const gridView = document.getElementById('gdrive_items_grid');
    const listBody = document.getElementById('gdrive_items_list_tbody');
    const emptyState = document.getElementById('gdrive_empty_state');

    gridView.innerHTML = '';
    listBody.innerHTML = '';

    if (!files || files.length === 0) {
        emptyState.classList.remove('hidden');
        return;
    }
    emptyState.classList.add('hidden');

    files.forEach(f => {
        const isFolder = f.mimeType === 'application/vnd.google-apps.folder';
        const isJsonBackup = f.name.endsWith('.json');
        const formattedSize = f.size ? formatBytes(f.size) : (isFolder ? 'Folder' : '--');
        const formattedDate = f.modifiedTime ? new Date(f.modifiedTime).toLocaleDateString() : '';

        // 1. Grid Item Card
        const card = document.createElement('div');
        card.className = `p-3.5 rounded-2xl border transition-all duration-200 group flex flex-col justify-between select-none ${
            isFolder 
                ? 'bg-amber-950/20 hover:bg-amber-950/40 border-amber-500/30 cursor-pointer' 
                : 'bg-slate-950/80 hover:bg-slate-800/80 border-slate-800'
        }`;

        let iconHtml = '<i data-lucide="file" class="w-7 h-7 text-slate-400"></i>';
        if (isFolder) iconHtml = '<i data-lucide="folder" class="w-8 h-8 text-amber-400"></i>';
        else if (isJsonBackup) iconHtml = '<i data-lucide="database" class="w-7 h-7 text-emerald-400"></i>';
        else if (f.mimeType.includes('image')) iconHtml = '<i data-lucide="image" class="w-7 h-7 text-sky-400"></i>';
        else if (f.mimeType.includes('pdf')) iconHtml = '<i data-lucide="file-text" class="w-7 h-7 text-rose-400"></i>';

        card.innerHTML = `
            <div class="flex items-start justify-between gap-2 mb-2">
                <div class="p-2 rounded-xl bg-slate-900 border border-slate-800/80 shrink-0">
                    ${iconHtml}
                </div>
                <div class="flex items-center gap-1 opacity-80 group-hover:opacity-100 transition-opacity">
                    ${isFolder ? '' : `
                        <button onclick="downloadDriveFile('${f.id}', '${escapeHtml(f.name)}')" class="p-1 rounded-lg text-slate-400 hover:text-emerald-400 hover:bg-slate-800" title="Download">
                            <i data-lucide="download" class="w-3.5 h-3.5"></i>
                        </button>
                    `}
                    ${isJsonBackup ? `
                        <button onclick="restoreFromDriveBackup('${f.id}', '${escapeHtml(f.name)}')" class="p-1 rounded-lg text-emerald-400 hover:text-emerald-300 hover:bg-emerald-950/40 font-bold text-[10px]" title="Restore to Shop">
                            <i data-lucide="rotate-ccw" class="w-3.5 h-3.5"></i>
                        </button>
                    ` : ''}
                    <button onclick="promptDeleteDriveItem('${f.id}', '${escapeHtml(f.name)}', ${isFolder})" class="p-1 rounded-lg text-slate-400 hover:text-rose-400 hover:bg-slate-800" title="Delete">
                        <i data-lucide="trash-2" class="w-3.5 h-3.5"></i>
                    </button>
                </div>
            </div>
            <div>
                <h4 class="font-bold text-xs text-white truncate group-hover:text-emerald-400 transition-colors" title="${escapeHtml(f.name)}">
                    ${escapeHtml(f.name)}
                </h4>
                <div class="flex items-center justify-between text-[10px] text-slate-400 font-mono mt-1">
                    <span>${formattedSize}</span>
                    <span>${formattedDate}</span>
                </div>
            </div>
        `;

        if (isFolder) {
            card.onclick = (e) => {
                if (e.target.closest('button')) return;
                navigateToFolder(f.id, f.name);
            };
        }
        gridView.appendChild(card);

        // 2. Table Row for List View
        const row = document.createElement('tr');
        row.className = 'hover:bg-slate-800/40 transition-colors';
        row.innerHTML = `
            <td class="p-3 font-bold text-white flex items-center gap-2.5 ${isFolder ? 'cursor-pointer' : ''}">
                ${iconHtml}
                <span class="truncate max-w-[200px] sm:max-w-xs" title="${escapeHtml(f.name)}">${escapeHtml(f.name)}</span>
            </td>
            <td class="p-3 text-slate-400 text-[11px] font-mono">${isFolder ? 'Folder' : (f.mimeType.split('/').pop() || 'File')}</td>
            <td class="p-3 text-slate-300 font-mono text-[11px]">${formattedSize}</td>
            <td class="p-3 text-slate-400 font-mono text-[11px]">${formattedDate}</td>
            <td class="p-3 text-center">
                <div class="flex items-center justify-center gap-1.5">
                    ${isFolder ? `
                        <button onclick="navigateToFolder('${f.id}', '${escapeHtml(f.name)}')" class="p-1.5 rounded-lg bg-amber-500/10 hover:bg-amber-500/20 text-amber-300 text-xs font-bold">Open</button>
                    ` : `
                        <button onclick="downloadDriveFile('${f.id}', '${escapeHtml(f.name)}')" class="p-1.5 rounded-lg bg-emerald-500/10 hover:bg-emerald-500/20 text-emerald-400"><i data-lucide="download" class="w-3.5 h-3.5"></i></button>
                    `}
                    ${isJsonBackup ? `
                        <button onclick="restoreFromDriveBackup('${f.id}', '${escapeHtml(f.name)}')" class="p-1.5 rounded-lg bg-emerald-500/20 hover:bg-emerald-500/30 text-emerald-300 font-bold text-xs flex items-center gap-1">
                            <i data-lucide="rotate-ccw" class="w-3 h-3"></i> Restore
                        </button>
                    ` : ''}
                    <button onclick="promptDeleteDriveItem('${f.id}', '${escapeHtml(f.name)}', ${isFolder})" class="p-1.5 rounded-lg bg-rose-500/10 hover:bg-rose-500/20 text-rose-400"><i data-lucide="trash-2" class="w-3.5 h-3.5"></i></button>
                </div>
            </td>
        `;
        listBody.appendChild(row);
    });

    if (window.lucide) window.lucide.createIcons();
}

// Navigation & Breadcrumbs
window.navigateToFolder = function(folderId, folderName) {
    currentFolderId = folderId;
    currentFolderName = folderName;

    // Adjust stack
    const existingIndex = folderPathStack.findIndex(item => item.id === folderId);
    if (existingIndex !== -1) {
        folderPathStack = folderPathStack.slice(0, existingIndex + 1);
    } else {
        folderPathStack.push({ id: folderId, name: folderName });
    }

    renderBreadcrumbs();
    loadDriveFiles(currentFolderId);
};

function renderBreadcrumbs() {
    const container = document.getElementById('gdrive_breadcrumbs');
    container.innerHTML = '';

    folderPathStack.forEach((item, index) => {
        const isLast = index === folderPathStack.length - 1;
        const btn = document.createElement('button');
        btn.type = 'button';
        btn.className = `font-bold flex items-center gap-1 shrink-0 ${
            isLast ? 'text-white pointer-events-none' : 'text-emerald-400 hover:text-emerald-300 cursor-pointer'
        }`;
        btn.innerHTML = (index === 0 ? '<i data-lucide="hard-drive" class="w-4 h-4"></i> ' : '') + `<span>${escapeHtml(item.name)}</span>`;
        btn.onclick = () => navigateToFolder(item.id, item.name);
        container.appendChild(btn);

        if (!isLast) {
            const separator = document.createElement('span');
            separator.className = 'text-slate-600 px-1';
            separator.innerText = '/';
            container.appendChild(separator);
        }
    });
    if (window.lucide) window.lucide.createIcons();
}

window.refreshCurrentFolder = function() {
    loadDriveFiles(currentFolderId);
};

window.handleDriveSearch = function(e) {
    const query = e.target.value.trim();
    if (e.key === 'Enter' || query.length === 0 || query.length > 2) {
        loadDriveFiles(currentFolderId, query);
    }
};

window.switchViewMode = function(mode) {
    viewMode = mode;
    const gridEl = document.getElementById('gdrive_items_grid');
    const listEl = document.getElementById('gdrive_items_list');
    const btnGrid = document.getElementById('btn_view_grid');
    const btnList = document.getElementById('btn_view_list');

    if (mode === 'grid') {
        gridEl.classList.remove('hidden');
        listEl.classList.add('hidden');
        btnGrid.className = 'p-1.5 rounded-lg text-emerald-400 bg-slate-800 cursor-pointer';
        btnList.className = 'p-1.5 rounded-lg text-slate-400 hover:text-white cursor-pointer';
    } else {
        gridEl.classList.add('hidden');
        listEl.classList.remove('hidden');
        btnGrid.className = 'p-1.5 rounded-lg text-slate-400 hover:text-white cursor-pointer';
        btnList.className = 'p-1.5 rounded-lg text-emerald-400 bg-slate-800 cursor-pointer';
    }
};

// Create New Folder
window.openCreateFolderModal = function() {
    document.getElementById('new_folder_name_input').value = '';
    document.getElementById('createFolderModal').classList.remove('hidden');
};
window.closeCreateFolderModal = function() {
    document.getElementById('createFolderModal').classList.add('hidden');
};
window.submitCreateFolder = async function() {
    const folderName = document.getElementById('new_folder_name_input').value.trim();
    if (!folderName) {
        alert('براہ کرم فولڈر کا نام درج کریں۔');
        return;
    }
    const btn = document.getElementById('btn_submit_folder');
    btn.disabled = true;
    btn.innerText = 'بنا رہا ہے...';

    try {
        const metadata = {
            name: folderName,
            mimeType: 'application/vnd.google-apps.folder',
            parents: [currentFolderId]
        };

        const res = await fetch('https://www.googleapis.com/drive/v3/files', {
            method: 'POST',
            headers: {
                Authorization: `Bearer ${cachedToken}`,
                'Content-Type': 'application/json'
            },
            body: JSON.stringify(metadata)
        });

        if (res.ok) {
            closeCreateFolderModal();
            loadDriveFiles(currentFolderId);
        } else {
            const err = await res.text();
            alert('فولڈر بنانے میں خرابی: ' + err);
        }
    } catch (e) {
        alert('خرابی: ' + e.message);
    } finally {
        btn.disabled = false;
        btn.innerText = 'بنائیں';
    }
};

// Upload File
window.openUploadFileModal = function() {
    selectedFileToUpload = null;
    document.getElementById('selected_file_preview').classList.add('hidden');
    document.getElementById('btn_submit_upload').disabled = true;
    document.getElementById('drive_file_input').value = '';
    document.getElementById('uploadFileModal').classList.remove('hidden');
};
window.closeUploadFileModal = function() {
    document.getElementById('uploadFileModal').classList.add('hidden');
};
window.handleFileSelectedForUpload = function(e) {
    const file = e.target.files[0];
    if (file) {
        selectedFileToUpload = file;
        document.getElementById('selected_file_name').innerText = file.name;
        document.getElementById('selected_file_size').innerText = formatBytes(file.size);
        document.getElementById('selected_file_preview').classList.remove('hidden');
        document.getElementById('btn_submit_upload').disabled = false;
    }
};
window.submitUploadFile = async function() {
    if (!selectedFileToUpload) return;
    const btn = document.getElementById('btn_submit_upload');
    btn.disabled = true;
    btn.innerText = 'اپلوڈ جاری ہے...';

    try {
        const metadata = {
            name: selectedFileToUpload.name,
            mimeType: selectedFileToUpload.type || 'application/octet-stream',
            parents: [currentFolderId]
        };

        const form = new FormData();
        form.append('metadata', new Blob([JSON.stringify(metadata)], { type: 'application/json' }));
        form.append('file', selectedFileToUpload);

        const res = await fetch('https://www.googleapis.com/upload/drive/v3/files?uploadType=multipart', {
            method: 'POST',
            headers: { Authorization: `Bearer ${cachedToken}` },
            body: form
        });

        if (res.ok) {
            closeUploadFileModal();
            loadDriveFiles(currentFolderId);
            alert('فائل کامیابی سے گوگل ڈرائیو میں اپلوڈ ہو گئی!');
        } else {
            const err = await res.text();
            alert('اپلوڈ میں مسئلہ: ' + err);
        }
    } catch (e) {
        alert('اپلوڈ خرابی: ' + e.message);
    } finally {
        btn.disabled = false;
        btn.innerText = 'اپلوڈ کریں';
    }
};

// Download File from Google Drive
window.downloadDriveFile = async function(fileId, fileName) {
    if (!cachedToken) return;
    try {
        const res = await fetch(`https://www.googleapis.com/drive/v3/files/${fileId}?alt=media`, {
            headers: { Authorization: `Bearer ${cachedToken}` }
        });
        if (!res.ok) throw new Error('Download failed with status: ' + res.status);
        const blob = await res.blob();
        const blobUrl = URL.createObjectURL(blob);
        const a = document.createElement('a');
        a.href = blobUrl;
        a.download = fileName;
        document.body.appendChild(a);
        a.click();
        document.body.removeChild(a);
        URL.revokeObjectURL(blobUrl);
    } catch (e) {
        alert('ڈاؤنلوڈ میں خرابی: ' + e.message);
    }
};

// 1-Click Shop Backup to Google Drive
window.backupShopToGoogleDrive = async function() {
    if (!cachedToken) {
        alert('براہ کرم پہلے گوگل ڈرائیو کنیکٹ کریں۔');
        return;
    }

    const btn = document.getElementById('btn_backup_shop');
    const origHtml = btn.innerHTML;
    btn.disabled = true;
    btn.innerHTML = '<i data-lucide="loader-2" class="w-4 h-4 animate-spin"></i> <span>بیک اپ بن رہا ہے...</span>';
    if (window.lucide) window.lucide.createIcons();

    try {
        // Fetch full shop data payload from PHP
        const exportRes = await fetch('index.php?api_action=get_shop_export_payload');
        const exportJson = await exportRes.json();
        if (!exportJson.success || !exportJson.data) {
            throw new Error(exportJson.error || 'Failed to export shop records.');
        }

        const dateStr = new Date().toISOString().split('T')[0];
        const fileName = `LimoMobile_Backup_${dateStr}_${Date.now()}.json`;
        const fileContent = JSON.stringify(exportJson.data, null, 2);

        const metadata = {
            name: fileName,
            mimeType: 'application/json',
            parents: [currentFolderId]
        };

        const form = new FormData();
        form.append('metadata', new Blob([JSON.stringify(metadata)], { type: 'application/json' }));
        form.append('file', new Blob([fileContent], { type: 'application/json' }));

        const uploadRes = await fetch('https://www.googleapis.com/upload/drive/v3/files?uploadType=multipart', {
            method: 'POST',
            headers: { Authorization: `Bearer ${cachedToken}` },
            body: form
        });

        if (uploadRes.ok) {
            loadDriveFiles(currentFolderId);
            alert(`✅ کامیابی: دکان کا مکمل لائیو بیک اپ فائل [${fileName}] گوگل ڈرائیو میں محفوظ ہو گیا!`);
        } else {
            const err = await uploadRes.text();
            alert('گوگل ڈرائیو اپلوڈ میں خرابی: ' + err);
        }
    } catch (e) {
        alert('بیک اپ میں خرابی: ' + e.message);
    } finally {
        btn.disabled = false;
        btn.innerHTML = origHtml;
        if (window.lucide) window.lucide.createIcons();
    }
};

// Restore Shop Database from Google Drive JSON File
window.restoreFromDriveBackup = async function(fileId, fileName) {
    const isConfirmed = confirm(`کیا آپ واقعی اس بیک اپ [${fileName}] کو اپنی دکان پر بحال (Restore) کرنا چاہتے ہیں؟\nاس سے موجودہ اسٹاک اور کھاتہ جات اپ ڈیٹ ہو جائیں گے۔`);
    if (!isConfirmed) return;

    try {
        const downloadRes = await fetch(`https://www.googleapis.com/drive/v3/files/${fileId}?alt=media`, {
            headers: { Authorization: `Bearer ${cachedToken}` }
        });
        if (!downloadRes.ok) throw new Error('Failed to read backup from Drive');

        const backupData = await downloadRes.json();

        // Push payload to PHP database restore endpoint
        const restoreRes = await fetch('index.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: JSON.stringify({
                api_action: 'restore_gdrive_payload',
                ...backupData
            })
        });

        const restoreJson = await restoreRes.json();
        if (restoreJson.success) {
            alert('✅ زبردست! ڈیٹا گوگل ڈرائیو سے بحال ہو گیا ہے۔');
            window.location.href = '../dashboard/index.php';
        } else {
            alert('ریسٹور میں خرابی: ' + restoreJson.error);
        }
    } catch (e) {
        alert('ریسٹور خرابی: ' + e.message);
    }
};

// Destructive Deletion Confirmation Flow (Strict user confirmation requirement)
window.promptDeleteDriveItem = function(id, name, isFolder) {
    itemToDelete = { id, name, isFolder };
    const msg = isFolder 
        ? `کیا آپ واقعی گوگل ڈرائیو فولڈر "<strong>${name}</strong>" اور اس کے اندر موجود تمام فائلیں ڈیلیٹ کرنا چاہتے ہیں؟`
        : `کیا آپ واقعی فائل "<strong>${name}</strong>" کو گوگل ڈرائیو سے ڈیلیٹ کرنا چاہتے ہیں؟`;
    document.getElementById('delete_confirm_message').innerHTML = msg;
    document.getElementById('deleteConfirmModal').classList.remove('hidden');
};
window.closeDeleteConfirmModal = function() {
    itemToDelete = null;
    document.getElementById('deleteConfirmModal').classList.add('hidden');
};
window.executeDeleteDriveItem = async function() {
    if (!itemToDelete || !cachedToken) return;
    const btn = document.getElementById('btn_confirm_delete');
    btn.disabled = true;
    btn.innerText = 'ڈیلیٹ ہو رہا ہے...';

    try {
        const res = await fetch(`https://www.googleapis.com/drive/v3/files/${itemToDelete.id}`, {
            method: 'DELETE',
            headers: { Authorization: `Bearer ${cachedToken}` }
        });

        if (res.ok || res.status === 204) {
            closeDeleteConfirmModal();
            loadDriveFiles(currentFolderId);
        } else {
            const err = await res.text();
            alert('ڈیلیٹ کرنے میں خرابی: ' + err);
        }
    } catch (e) {
        alert('ڈیلیٹ خرابی: ' + e.message);
    } finally {
        btn.disabled = false;
        btn.innerText = 'ڈیلیٹ کریں';
    }
};

function formatBytes(bytes, decimals = 1) {
    if (bytes === 0 || !bytes) return '0 B';
    const k = 1024;
    const dm = decimals < 0 ? 0 : decimals;
    const sizes = ['B', 'KB', 'MB', 'GB', 'TB'];
    const i = Math.floor(Math.log(bytes) / Math.log(k));
    return parseFloat((bytes / Math.pow(k, i)).toFixed(dm)) + ' ' + sizes[i];
}

function escapeHtml(text) {
    if (!text) return '';
    return String(text)
        .replace(/&/g, "&amp;")
        .replace(/</g, "&lt;")
        .replace(/>/g, "&gt;")
        .replace(/"/g, "&quot;")
        .replace(/'/g, "&#039;");
}
</script>

<?php require_once __DIR__ . '/../backend/footer.php'; ?>
