<?php
/**
 * LimoMobile CEO-Master Enterprise Suite & HQ Portal
 * Standalone Headquarters Control Center
 * Multi-Shop Governance, SaaS Fee Ledger, Landing Page Post Publisher & Global Telemetry
 */
require_once __DIR__ . '/../backend/config.php';

$settings = getShopSettings($pdo);
$pageTitle = $isUrdu ? 'سی ای او ماسٹر انٹرپرائز پورٹل' : 'LimoMobile CEO Master Enterprise Portal';

$alertMsg = '';
$alertType = 'success';

// Handle CEO Master Login directly if submitted on this page
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ceo_login_action'])) {
    $loginType = $_POST['login_type'] ?? 'pin';
    if ($loginType === 'pin') {
        $pin = trim($_POST['pin'] ?? '');
        if ($pin === '6242842' || $pin === '6242') {
            $ceoMaster = [
                'id' => 'usr-ceo-01',
                'name' => 'LimoMobile CEO & SuperAdmin',
                'email' => 'admin@limopos.com',
                'role' => 'SuperAdmin',
                'status' => 'ACTIVE',
                'permissions' => ['all', 'ceo', 'superadmin'],
                'shop_name' => 'LimoMobile Central HQ',
                'phone' => '0300-6242842'
            ];
            loginUserSession($ceoMaster, $pdo);
            header("Location: index.php");
            exit();
        } else {
            // Check in DB
            $stmt = $pdo->prepare("SELECT * FROM users WHERE pin_code = :pin AND (role = 'SuperAdmin' OR LOWER(email) = 'admin@limopos.com') LIMIT 1");
            $stmt->execute([':pin' => $pin]);
            $u = $stmt->fetch();
            if ($u) {
                loginUserSession($u, $pdo);
                header("Location: index.php");
                exit();
            }
            $alertMsg = $isUrdu ? 'غلط ماسٹر پن کوڈ! برائے مہربانی درست سی ای او پن درج کریں۔' : 'Invalid Master PIN. Please enter authorized CEO credentials.';
            $alertType = 'error';
        }
    } else {
        $email = strtolower(trim($_POST['email'] ?? ''));
        $password = trim($_POST['password'] ?? '');
        if (($email === 'admin@limopos.com' || $email === 'ceo@limomobile.com') && ($password === '6242842' || $password === 'admin123')) {
            $ceoMaster = [
                'id' => 'usr-ceo-01',
                'name' => 'LimoMobile CEO & SuperAdmin',
                'email' => 'admin@limopos.com',
                'role' => 'SuperAdmin',
                'status' => 'ACTIVE',
                'permissions' => ['all', 'ceo', 'superadmin'],
                'shop_name' => 'LimoMobile Central HQ',
                'phone' => '0300-6242842'
            ];
            loginUserSession($ceoMaster, $pdo);
            header("Location: index.php");
            exit();
        } else {
            $stmt = $pdo->prepare("SELECT * FROM users WHERE LOWER(email) = :email LIMIT 1");
            $stmt->execute([':email' => $email]);
            $u = $stmt->fetch();
            if ($u && ($u['role'] === 'SuperAdmin' || $u['role'] === 'CEO') && password_verify($password, $u['password_hash'])) {
                loginUserSession($u, $pdo);
                header("Location: index.php");
                exit();
            }
            $alertMsg = $isUrdu ? 'غلط ای میل یا پاسورڈ! صرف سی ای او ماسٹر ہی رسائی حاصل کر سکتے ہیں۔' : 'Authentication failed. CEO Master credentials required.';
            $alertType = 'error';
        }
    }
}

// Check if currently authenticated as CEO
$isMasterCeo = isCeoAdmin();

// Handle CEO Actions if authenticated
if ($isMasterCeo && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    // 1. Add / Register New Shop
    if ($action === 'create_shop') {
        $shopName = trim($_POST['shop_name'] ?? '');
        $ownerName = trim($_POST['owner_name'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $city = trim($_POST['city'] ?? '');
        $address = trim($_POST['address'] ?? '');
        $plan = $_POST['subscription_plan'] ?? 'MONTHLY';
        $fee = floatval($_POST['subscription_fee'] ?? 2500);
        $status = $_POST['subscription_status'] ?? 'ACTIVE';
        $thermalSize = $_POST['thermal_size'] ?? '80mm';
        $pin = trim($_POST['pin_code'] ?? '6242');
        $notes = trim($_POST['notes'] ?? '');

        if (!empty($shopName) && !empty($ownerName) && !empty($phone)) {
            try {
                $shopId = 'shop-' . rand(100, 999);
                $licenseKey = 'LIMO-PK-' . strtoupper(substr(preg_replace('/[^a-zA-Z]/', '', $city ?: 'POS'), 0, 3)) . '-' . rand(1000, 9999);
                $today = date('Y-m-d');
                $nextDue = ($plan === 'YEARLY') ? date('Y-m-d', strtotime('+1 year')) : date('Y-m-d', strtotime('+30 days'));

                $stmt = $pdo->prepare("INSERT INTO registered_shops (id, shop_name, owner_name, phone, email, city, address, pin_code, thermal_size, subscription_plan, subscription_fee, fee_cycle, subscription_status, last_payment_date, next_due_date, license_key, opening_cash, opening_easypaisa, notes, created_at) VALUES (:id, :shop_name, :owner_name, :phone, :email, :city, :address, :pin, :thermal, :plan, :fee, :cycle, :status, :last_pay, :next_due, :lic, 0, 0, :notes, :created_at)");
                $stmt->execute([
                    ':id' => $shopId,
                    ':shop_name' => $shopName,
                    ':owner_name' => $ownerName,
                    ':phone' => $phone,
                    ':email' => $email,
                    ':city' => $city,
                    ':address' => $address,
                    ':pin' => $pin ?: '6242',
                    ':thermal' => $thermalSize,
                    ':plan' => $plan,
                    ':fee' => $fee,
                    ':cycle' => $plan,
                    ':status' => $status,
                    ':last_pay' => $today,
                    ':next_due' => $nextDue,
                    ':lic' => $licenseKey,
                    ':notes' => $notes,
                    ':created_at' => time()
                ]);

                logUserActivity($pdo, 'CEO_SHOP_CREATE', "Registered new SaaS client shop: {$shopName} ({$ownerName}) - Plan: {$plan}");
                $alertMsg = $isUrdu ? "نئی دکان '{$shopName}' کامیابی سے سسٹم میں رجسٹرڈ ہو گئی۔ لائسنس کی: {$licenseKey}" : "Shop '{$shopName}' registered successfully. License: {$licenseKey}";
                $alertType = 'success';
            } catch (Exception $e) {
                $alertMsg = "Error: " . $e->getMessage();
                $alertType = 'error';
            }
        }
    }

    // 2. Edit Shop Details & Subscription
    if ($action === 'edit_shop') {
        $shopId = trim($_POST['shop_id'] ?? '');
        $shopName = trim($_POST['shop_name'] ?? '');
        $ownerName = trim($_POST['owner_name'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $city = trim($_POST['city'] ?? '');
        $address = trim($_POST['address'] ?? '');
        $plan = $_POST['subscription_plan'] ?? 'MONTHLY';
        $fee = floatval($_POST['subscription_fee'] ?? 2500);
        $status = $_POST['subscription_status'] ?? 'ACTIVE';
        $nextDue = trim($_POST['next_due_date'] ?? '');
        $thermalSize = $_POST['thermal_size'] ?? '80mm';
        $licenseKey = trim($_POST['license_key'] ?? '');
        $notes = trim($_POST['notes'] ?? '');

        if (!empty($shopId) && !empty($shopName)) {
            try {
                $stmt = $pdo->prepare("UPDATE registered_shops SET shop_name = :shop_name, owner_name = :owner_name, phone = :phone, email = :email, city = :city, address = :address, subscription_plan = :plan, subscription_fee = :fee, fee_cycle = :cycle, subscription_status = :status, next_due_date = :next_due, thermal_size = :thermal, license_key = :lic, notes = :notes WHERE id = :id");
                $stmt->execute([
                    ':shop_name' => $shopName,
                    ':owner_name' => $ownerName,
                    ':phone' => $phone,
                    ':email' => $email,
                    ':city' => $city,
                    ':address' => $address,
                    ':plan' => $plan,
                    ':fee' => $fee,
                    ':cycle' => $plan,
                    ':status' => $status,
                    ':next_due' => $nextDue,
                    ':thermal' => $thermalSize,
                    ':lic' => $licenseKey,
                    ':notes' => $notes,
                    ':id' => $shopId
                ]);

                logUserActivity($pdo, 'CEO_SHOP_UPDATE', "Updated shop profile & subscription: {$shopName} ({$shopId})");
                $alertMsg = $isUrdu ? "دکان '{$shopName}' کا ریکارڈ و سبسکرپشن کامیابی سے اپ ڈیٹ ہو گئی۔" : "Shop '{$shopName}' updated successfully.";
                $alertType = 'success';
            } catch (Exception $e) {
                $alertMsg = "Error: " . $e->getMessage();
                $alertType = 'error';
            }
        }
    }

    // 3. Delete Shop
    if ($action === 'delete_shop') {
        $shopId = trim($_POST['shop_id'] ?? '');
        $shopName = trim($_POST['shop_name'] ?? 'Shop');
        if (!empty($shopId)) {
            try {
                $stmt = $pdo->prepare("DELETE FROM registered_shops WHERE id = :id");
                $stmt->execute([':id' => $shopId]);
                logUserActivity($pdo, 'CEO_SHOP_DELETE', "Deleted shop profile: {$shopName} ({$shopId})");
                $alertMsg = $isUrdu ? "دکان '{$shopName}' کو کامیابی سے ڈیلیٹ کر دیا گیا۔" : "Shop '{$shopName}' deleted successfully.";
                $alertType = 'success';
            } catch (Exception $e) {
                $alertMsg = "Error: " . $e->getMessage();
                $alertType = 'error';
            }
        }
    }

    // 4. Record Subscription Fee Payment
    if ($action === 'record_fee_payment') {
        $shopId = trim($_POST['shop_id'] ?? '');
        $amount = floatval($_POST['amount'] ?? 0);
        $paymentDate = trim($_POST['payment_date'] ?? date('Y-m-d'));
        $billingMonth = trim($_POST['billing_month_year'] ?? date('M Y'));
        $paymentMethod = $_POST['payment_method'] ?? 'EASYPAISA';
        $trxId = trim($_POST['trx_id'] ?? '');
        $planType = $_POST['plan_type'] ?? 'MONTHLY';
        $notes = trim($_POST['notes'] ?? '');
        $autoRenew = isset($_POST['auto_renew_date']) && $_POST['auto_renew_date'] === '1';

        if (!empty($shopId) && $amount > 0) {
            try {
                // Fetch shop details
                $stmt = $pdo->prepare("SELECT * FROM registered_shops WHERE id = :id LIMIT 1");
                $stmt->execute([':id' => $shopId]);
                $shop = $stmt->fetch();

                $shopName = $shop['shop_name'] ?? 'Registered Shop';
                $ownerName = $shop['owner_name'] ?? '';
                $phone = $shop['phone'] ?? '';

                $paymentId = 'fee-' . rand(1000, 9999);
                $receiptNo = 'REC-LIMO-' . strtoupper(substr(uniqid(), -5));
                $periodStart = $paymentDate;
                $periodEnd = ($planType === 'YEARLY') ? date('Y-m-d', strtotime($paymentDate . ' +1 year')) : date('Y-m-d', strtotime($paymentDate . ' +30 days'));

                $stmt = $pdo->prepare("INSERT INTO shop_fee_payments (id, shop_id, shop_name, owner_name, phone, amount, payment_date, billing_month_year, period_start, period_end, plan_type, payment_method, trx_id, receipt_no, status, recorded_by, notes, created_at) VALUES (:id, :shop_id, :shop_name, :owner_name, :phone, :amount, :pdate, :bmonth, :pstart, :pend, :plan, :method, :trx, :rec, 'PAID', 'CEO Master Admin', :notes, :created_at)");
                $stmt->execute([
                    ':id' => $paymentId,
                    ':shop_id' => $shopId,
                    ':shop_name' => $shopName,
                    ':owner_name' => $ownerName,
                    ':phone' => $phone,
                    ':amount' => $amount,
                    ':pdate' => $paymentDate,
                    ':bmonth' => $billingMonth,
                    ':pstart' => $periodStart,
                    ':pend' => $periodEnd,
                    ':plan' => $planType,
                    ':method' => $paymentMethod,
                    ':trx' => $trxId,
                    ':rec' => $receiptNo,
                    ':notes' => $notes,
                    ':created_at' => time()
                ]);

                // Update shop next due date and status
                if ($autoRenew) {
                    $stmt = $pdo->prepare("UPDATE registered_shops SET subscription_status = 'ACTIVE', last_payment_date = :pdate, next_due_date = :next_due WHERE id = :id");
                    $stmt->execute([
                        ':pdate' => $paymentDate,
                        ':next_due' => $periodEnd,
                        ':id' => $shopId
                    ]);
                }

                logUserActivity($pdo, 'CEO_FEE_COLLECT', "Recorded subscription payment Rs. {$amount} for {$shopName} ({$receiptNo})");
                $alertMsg = $isUrdu ? "فیس وصولی Rs. " . number_format($amount) . " برائے '{$shopName}' کامیابی سے درج ہو گئی۔ رسید نمبر: {$receiptNo}" : "Fee payment Rs. " . number_format($amount) . " recorded. Receipt: {$receiptNo}";
                $alertType = 'success';
            } catch (Exception $e) {
                $alertMsg = "Error: " . $e->getMessage();
                $alertType = 'error';
            }
        }
    }

    // 5. Create / Edit Landing Page Post
    if ($action === 'save_landing_post') {
        $postId = trim($_POST['post_id'] ?? '');
        $title = trim($_POST['title'] ?? '');
        $content = trim($_POST['content'] ?? '');
        $category = $_POST['category'] ?? 'ANNOUNCEMENT';
        $badgeText = trim($_POST['badge_text'] ?? '');
        $imageUrl = trim($_POST['image_url'] ?? '');
        $actionText = trim($_POST['action_text'] ?? '');
        $actionUrl = trim($_POST['action_url'] ?? '');
        $isPinned = isset($_POST['is_pinned']) ? 1 : 0;
        $status = $_POST['status'] ?? 'PUBLISHED';
        $authorName = trim($_POST['author_name'] ?? 'LimoMobile CEO');

        if (!empty($title) && !empty($content)) {
            try {
                if (empty($postId)) {
                    // Create New Post
                    $postId = 'post-' . rand(10, 99) . '-' . time();
                    $stmt = $pdo->prepare("INSERT INTO landing_posts (id, title, content, category, image_url, badge_text, action_text, action_url, is_pinned, status, author_name, likes_count, created_at, updated_at) VALUES (:id, :title, :content, :category, :img, :badge, :atext, :aurl, :pinned, :status, :author, :likes, :created_at, :updated_at)");
                    $stmt->execute([
                        ':id' => $postId,
                        ':title' => $title,
                        ':content' => $content,
                        ':category' => $category,
                        ':img' => $imageUrl,
                        ':badge' => $badgeText,
                        ':atext' => $actionText ?: 'مزید تفصیلات',
                        ':aurl' => $actionUrl ?: '../register_shop/index.php',
                        ':pinned' => $isPinned,
                        ':status' => $status,
                        ':author' => $authorName,
                        ':likes' => rand(15, 60),
                        ':created_at' => time(),
                        ':updated_at' => time()
                    ]);
                    logUserActivity($pdo, 'CEO_POST_CREATE', "Published landing announcement: {$title}");
                    $alertMsg = $isUrdu ? "نیا اعلان / پوسٹ '{$title}' لینڈنگ پیج پر کامیابی سے شائع ہو گیا۔" : "Landing post '{$title}' published successfully.";
                } else {
                    // Update Existing Post
                    $stmt = $pdo->prepare("UPDATE landing_posts SET title = :title, content = :content, category = :category, image_url = :img, badge_text = :badge, action_text = :atext, action_url = :aurl, is_pinned = :pinned, status = :status, author_name = :author, updated_at = :updated_at WHERE id = :id");
                    $stmt->execute([
                        ':title' => $title,
                        ':content' => $content,
                        ':category' => $category,
                        ':img' => $imageUrl,
                        ':badge' => $badgeText,
                        ':atext' => $actionText,
                        ':aurl' => $actionUrl,
                        ':pinned' => $isPinned,
                        ':status' => $status,
                        ':author' => $authorName,
                        ':updated_at' => time(),
                        ':id' => $postId
                    ]);
                    logUserActivity($pdo, 'CEO_POST_UPDATE', "Updated landing announcement: {$title}");
                    $alertMsg = $isUrdu ? "پوسٹ '{$title}' کامیابی سے اپ ڈیٹ ہو گئی۔" : "Landing post '{$title}' updated successfully.";
                }
                $alertType = 'success';
            } catch (Exception $e) {
                $alertMsg = "Error: " . $e->getMessage();
                $alertType = 'error';
            }
        }
    }

    // 6. Delete Landing Post
    if ($action === 'delete_landing_post') {
        $postId = trim($_POST['post_id'] ?? '');
        if (!empty($postId)) {
            try {
                $stmt = $pdo->prepare("DELETE FROM landing_posts WHERE id = :id");
                $stmt->execute([':id' => $postId]);
                logUserActivity($pdo, 'CEO_POST_DELETE', "Deleted landing announcement ID: {$postId}");
                $alertMsg = $isUrdu ? "پوسٹ کو لینڈنگ پیج سے ڈیلیٹ کر دیا گیا۔" : "Post deleted from landing page.";
                $alertType = 'success';
            } catch (Exception $e) {
                $alertMsg = "Error: " . $e->getMessage();
                $alertType = 'error';
            }
        }
    }

    // 7. Update SaaS Receiving Accounts
    if ($action === 'update_payment_accounts') {
        $epTitle = trim($_POST['ep_title'] ?? '');
        $epNumber = trim($_POST['ep_number'] ?? '');
        $jcTitle = trim($_POST['jc_title'] ?? '');
        $jcNumber = trim($_POST['jc_number'] ?? '');
        $bankName = trim($_POST['bank_name'] ?? '');
        $bankIban = trim($_POST['bank_iban'] ?? '');
        $bankTitle = trim($_POST['bank_title'] ?? '');

        $accountsData = [
            'easypaisa' => ['title' => $epTitle, 'number' => $epNumber],
            'jazzcash' => ['title' => $jcTitle, 'number' => $jcNumber],
            'bank' => ['name' => $bankName, 'iban' => $bankIban, 'title' => $bankTitle]
        ];

        saveShopSetting($pdo, 'ceo_payment_accounts', json_encode($accountsData, JSON_UNESCAPED_UNICODE));
        logUserActivity($pdo, 'CEO_SETTINGS_UPDATE', "Updated CEO SaaS receiving payment accounts");
        $alertMsg = $isUrdu ? "فیس وصولی اکاؤنٹس کی تفصیلات کامیابی سے محفوظ ہو گئیں۔" : "Payment accounts updated successfully.";
        $alertType = 'success';
    }
}

// Fetch Master SaaS Data if authenticated
$allShops = [];
$allFeePayments = [];
$allLandingPosts = [];
$allLogs = [];

if ($isMasterCeo) {
    try {
        $stmt = $pdo->query("SELECT * FROM registered_shops ORDER BY created_at DESC");
        $allShops = $stmt->fetchAll();
    } catch (Exception $e) {}

    try {
        $stmt = $pdo->query("SELECT * FROM shop_fee_payments ORDER BY payment_date DESC, created_at DESC");
        $allFeePayments = $stmt->fetchAll();
    } catch (Exception $e) {}

    try {
        $stmt = $pdo->query("SELECT * FROM landing_posts ORDER BY is_pinned DESC, created_at DESC");
        $allLandingPosts = $stmt->fetchAll();
    } catch (Exception $e) {}

    try {
        $stmt = $pdo->query("SELECT * FROM user_activity_logs ORDER BY created_at DESC LIMIT 50");
        $allLogs = $stmt->fetchAll();
    } catch (Exception $e) {}
}

// Metrics
$totalShopsCount = count($allShops);
$activeShopsCount = count(array_filter($allShops, fn($s) => ($s['subscription_status'] ?? 'ACTIVE') === 'ACTIVE'));
$dueShopsCount = count(array_filter($allShops, fn($s) => ($s['subscription_status'] ?? '') === 'DUE'));
$overdueShopsCount = count(array_filter($allShops, fn($s) => ($s['subscription_status'] ?? '') === 'OVERDUE'));
$trialShopsCount = count(array_filter($allShops, fn($s) => ($s['subscription_status'] ?? '') === 'TRIAL'));

$totalCollectedRevenue = array_sum(array_column($allFeePayments, 'amount'));
$monthlyCollectedRevenue = 0;
$currentMonthStr = date('Y-m');
foreach ($allFeePayments as $p) {
    if (str_starts_with($p['payment_date'] ?? '', $currentMonthStr)) {
        $monthlyCollectedRevenue += floatval($p['amount'] ?? 0);
    }
}
$publishedPostsCount = count(array_filter($allLandingPosts, fn($p) => ($p['status'] ?? 'PUBLISHED') === 'PUBLISHED'));

// Payment Accounts
$savedAccountsRaw = getShopSetting($pdo, 'ceo_payment_accounts', '');
$paymentAccounts = !empty($savedAccountsRaw) ? json_decode($savedAccountsRaw, true) : [
    'easypaisa' => ['title' => 'LimoMobile Central', 'number' => '0300-6242842'],
    'jazzcash' => ['title' => 'LimoMobile POS HQ', 'number' => '0301-6242842'],
    'bank' => ['name' => 'Meezan Bank Ltd', 'iban' => 'PK45MEZN0012345678901234', 'title' => 'LimoMobile Technologies']
];

$activeTab = $_GET['tab'] ?? 'overview';
?>
<!DOCTYPE html>
<html lang="<?= $currentLang ?>" dir="<?= $isUrdu ? 'rtl' : 'ltr' ?>" class="<?= $isDark ? 'dark' : '' ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $isUrdu ? 'سی ای او ماسٹر انٹرپرائز سوٹ | LimoMobile' : 'CEO Master Enterprise Suite | LimoMobile' ?></title>
    
    <!-- Tailwind CSS with custom branding -->
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            darkMode: 'class',
            theme: {
                extend: {
                    colors: {
                        limoblue: { 50: '#f0f9ff', 100: '#e0f2fe', 400: '#38bdf8', 500: '#0ea5e9', 600: '#0284c7', 700: '#0369a1', 900: '#0c4a6e' },
                        limogreen: { 50: '#f7fee7', 400: '#a3e635', 500: '#84cc16', 600: '#65a30d', 700: '#4d7c0f' },
                        slate: { 850: '#0f172a', 900: '#0b1120', 950: '#030712' }
                    },
                    fontFamily: {
                        sans: ['Plus Jakarta Sans', 'Noto Nastaliq Urdu', 'sans-serif'],
                        urdu: ['Noto Nastaliq Urdu', 'Jameel Noori Nastaleeq', 'serif']
                    }
                }
            }
        }
    </script>
    
    <!-- Google Fonts & Lucide Icons -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Noto+Nastaliq+Urdu:wght@400;700;900&family=Plus+Jakarta+Sans:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
    <script src="https://unpkg.com/lucide@latest"></script>

    <style>
        body { font-family: 'Plus Jakarta Sans', sans-serif; }
        .urdu-text { font-family: 'Noto Nastaliq Urdu', serif; line-height: 2.1; }
        .glass-header { backdrop-filter: blur(16px); -webkit-backdrop-filter: blur(16px); }
        .scrollbar-none::-webkit-scrollbar { display: none; }
        .scrollbar-none { -ms-overflow-style: none; scrollbar-width: none; }
    </style>
</head>
<body class="<?= $isDark ? 'bg-slate-950 text-slate-100' : 'bg-slate-100 text-slate-900' ?> antialiased min-h-screen flex flex-col selection:bg-amber-500 selection:text-slate-950">

<?php if (!$isMasterCeo): ?>
    <!-- ========================================================================= -->
    <!-- CEO MASTER STANDALONE LOGIN GATEWAY (SHIELDED ACCESS) -->
    <!-- ========================================================================= -->
    <div class="min-h-screen flex items-center justify-center p-4 bg-gradient-to-br from-slate-950 via-slate-900 to-slate-950 relative overflow-hidden">
        <!-- Ambient Decorative Glows -->
        <div class="absolute top-1/4 left-1/4 w-96 h-96 bg-amber-500/10 rounded-full blur-3xl pointer-events-none"></div>
        <div class="absolute bottom-1/4 right-1/4 w-96 h-96 bg-sky-500/10 rounded-full blur-3xl pointer-events-none"></div>

        <div class="w-full max-w-md bg-slate-900/90 border-2 border-amber-500/40 rounded-3xl p-8 shadow-2xl relative z-10 backdrop-blur-xl">
            
            <!-- Logo & Badge -->
            <div class="text-center space-y-3">
                <div class="w-16 h-16 rounded-2xl bg-gradient-to-tr from-amber-500 via-yellow-400 to-limogreen-500 text-slate-950 flex items-center justify-center font-black mx-auto shadow-xl shadow-amber-500/30 animate-pulse">
                    <i data-lucide="crown" class="w-9 h-9"></i>
                </div>
                <div class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full bg-amber-500/20 text-amber-300 border border-amber-500/40 text-xs font-mono font-black">
                    <span class="w-2 h-2 rounded-full bg-amber-400 animate-ping"></span>
                    HEADQUARTERS ONLY
                </div>
                <h1 class="text-2xl font-black text-white tracking-tight">
                    <?= $isUrdu ? 'سی ای او ماسٹر انٹرپرائز لاگ ان' : 'LimoMobile CEO Master Portal' ?>
                </h1>
                <p class="text-xs text-slate-400 leading-relaxed">
                    <?= $isUrdu ? 'تمام رجسٹرڈ شاپس، سبسکرپشن فیسوں اور لینڈنگ پیج اعلانات کا مکمل خود مختار پورٹل' : 'Dedicated command portal for multi-branch network & SaaS operations.' ?>
                </p>
            </div>

            <!-- Error Notification -->
            <?php if (!empty($alertMsg)): ?>
                <div class="mt-4 p-3.5 rounded-xl bg-rose-500/20 border border-rose-500/40 text-rose-300 text-xs font-bold flex items-center gap-2">
                    <i data-lucide="alert-triangle" class="w-4 h-4 shrink-0"></i>
                    <span><?= htmlspecialchars($alertMsg) ?></span>
                </div>
            <?php endif; ?>

            <!-- Login Method Selector -->
            <div class="flex items-center p-1 rounded-xl bg-slate-950 border border-slate-800 text-xs font-bold mt-6">
                <button type="button" onclick="toggleCeoGateMethod('pin')" id="btnCeoPin" class="flex-1 py-2 rounded-lg bg-amber-500 text-slate-950 font-black shadow-md transition-all">
                    <?= $isUrdu ? 'فوری ماسٹر پن (PIN)' : 'Master PIN' ?>
                </button>
                <button type="button" onclick="toggleCeoGateMethod('credentials')" id="btnCeoCred" class="flex-1 py-2 rounded-lg text-slate-400 hover:text-white transition-all">
                    <?= $isUrdu ? 'ای میل و پاسورڈ' : 'Email & Password' ?>
                </button>
            </div>

            <!-- PIN Login Form -->
            <form method="POST" id="formCeoPin" class="mt-6 space-y-4">
                <input type="hidden" name="ceo_login_action" value="1">
                <input type="hidden" name="login_type" value="pin">

                <div>
                    <label class="block text-xs font-bold text-slate-300 mb-2 text-center">
                        <?= $isUrdu ? '7 ہندسوں کا سی ای او ماسٹر سیکیورٹی پن درج کریں:' : 'Enter 7-Digit Master CEO Security PIN:' ?>
                    </label>
                    <input 
                        type="password" 
                        name="pin" 
                        id="ceoPinInput" 
                        maxlength="10" 
                        placeholder="•••••••" 
                        autofocus
                        class="w-full text-center text-2xl tracking-[0.4em] font-mono py-3 rounded-xl bg-slate-950 border-2 border-amber-500/40 text-amber-300 placeholder-slate-600 focus:outline-none focus:border-amber-400 focus:ring-2 focus:ring-amber-500/30"
                    >
                    <div class="flex justify-between items-center mt-2 text-[11px] text-slate-500">
                        <span>Default Master PIN: <strong class="text-amber-400">6242842</strong></span>
                        <button type="button" onclick="document.getElementById('ceoPinInput').value='6242842'" class="text-limoblue-400 hover:underline">Auto-Fill</button>
                    </div>
                </div>

                <button type="submit" class="w-full py-3.5 rounded-xl bg-gradient-to-r from-amber-500 to-yellow-500 hover:from-amber-400 hover:to-yellow-400 text-slate-950 font-black text-sm shadow-xl shadow-amber-500/25 flex items-center justify-center gap-2 cursor-pointer transition-all hover:scale-[1.02]">
                    <i data-lucide="shield-check" class="w-5 h-5"></i>
                    <span><?= $isUrdu ? 'ماسٹر کنٹرول میں داخل ہوں' : 'Unlock CEO Master Suite' ?></span>
                </button>
            </form>

            <!-- Email / Password Form -->
            <form method="POST" id="formCeoCred" class="mt-6 space-y-4 hidden">
                <input type="hidden" name="ceo_login_action" value="1">
                <input type="hidden" name="login_type" value="credentials">

                <div>
                    <label class="block text-xs font-bold text-slate-300 mb-1"><?= $isUrdu ? 'سی ای او ای میل:' : 'CEO Email:' ?></label>
                    <input type="email" name="email" value="admin@limopos.com" class="w-full px-3.5 py-2.5 rounded-xl bg-slate-950 border border-slate-800 text-white text-xs font-mono focus:outline-none focus:border-amber-400">
                </div>

                <div>
                    <label class="block text-xs font-bold text-slate-300 mb-1"><?= $isUrdu ? 'پاسورڈ:' : 'Password:' ?></label>
                    <input type="password" name="password" value="6242842" class="w-full px-3.5 py-2.5 rounded-xl bg-slate-950 border border-slate-800 text-white text-xs font-mono focus:outline-none focus:border-amber-400">
                </div>

                <button type="submit" class="w-full py-3.5 rounded-xl bg-gradient-to-r from-amber-500 to-yellow-500 hover:from-amber-400 hover:to-yellow-400 text-slate-950 font-black text-sm shadow-xl shadow-amber-500/25 flex items-center justify-center gap-2 cursor-pointer transition-all">
                    <i data-lucide="log-in" class="w-5 h-5"></i>
                    <span><?= $isUrdu ? 'سی ای او لاگ ان' : 'Sign In as CEO Master' ?></span>
                </button>
            </form>

            <!-- Back to App Footer Links -->
            <div class="mt-6 pt-4 border-t border-slate-800 flex items-center justify-between text-xs text-slate-400 font-bold">
                <a href="../landing/index.php" class="hover:text-amber-400 flex items-center gap-1 transition-colors">
                    <i data-lucide="arrow-left" class="w-3.5 h-3.5 rtl:rotate-180"></i>
                    <span><?= $isUrdu ? 'تعارفی صفحہ (Landing)' : 'Landing Page' ?></span>
                </a>
                <a href="../login/index.php" class="hover:text-limoblue-400 flex items-center gap-1 transition-colors">
                    <span><?= $isUrdu ? 'دکان POS لاگ ان' : 'Shop POS Login' ?></span>
                    <i data-lucide="shopping-bag" class="w-3.5 h-3.5"></i>
                </a>
            </div>
        </div>
    </div>

    <script>
        function toggleCeoGateMethod(method) {
            const pinForm = document.getElementById('formCeoPin');
            const credForm = document.getElementById('formCeoCred');
            const btnPin = document.getElementById('btnCeoPin');
            const btnCred = document.getElementById('btnCeoCred');

            if (method === 'pin') {
                pinForm.classList.remove('hidden');
                credForm.classList.add('hidden');
                btnPin.className = "flex-1 py-2 rounded-lg bg-amber-500 text-slate-950 font-black shadow-md transition-all";
                btnCred.className = "flex-1 py-2 rounded-lg text-slate-400 hover:text-white transition-all";
            } else {
                pinForm.classList.add('hidden');
                credForm.classList.remove('hidden');
                btnCred.className = "flex-1 py-2 rounded-lg bg-amber-500 text-slate-950 font-black shadow-md transition-all";
                btnPin.className = "flex-1 py-2 rounded-lg text-slate-400 hover:text-white transition-all";
            }
        }
        lucide.createIcons();
    </script>
</body>
</html>
<?php exit(); endif; ?>

<!-- ========================================================================= -->
<!-- AUTHENTICATED CEO MASTER STANDALONE ENTERPRISE SUITE -->
<!-- ========================================================================= -->
<div class="min-h-screen flex flex-col bg-slate-950 text-slate-100">

    <!-- Top Executive Navigation Bar -->
    <header class="sticky top-0 z-40 bg-slate-900/90 border-b border-slate-800 glass-header">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 h-16 flex items-center justify-between gap-4">
            
            <!-- Brand & Headquarters Badge -->
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-xl bg-gradient-to-tr from-amber-500 via-yellow-400 to-emerald-500 text-slate-950 flex items-center justify-center font-black shadow-lg shadow-amber-500/20">
                    <i data-lucide="crown" class="w-6 h-6"></i>
                </div>
                <div>
                    <div class="flex items-center gap-2">
                        <span class="font-black text-white text-base tracking-tight">LimoMobile</span>
                        <span class="px-2 py-0.5 rounded bg-amber-500 text-slate-950 text-[10px] font-black uppercase tracking-wider">CEO Portal</span>
                    </div>
                    <span class="text-[11px] text-slate-400 hidden sm:block"><?= $isUrdu ? 'سینٹرل ہیڈ کوارٹر و ملٹی شاپ مینجمنٹ سوٹ' : 'Central Enterprise Headquarters' ?></span>
                </div>
            </div>

            <!-- Quick Access / Language & Return Buttons -->
            <div class="flex items-center gap-2 sm:gap-3">
                
                <!-- Language Switcher -->
                <a href="?lang=<?= $isUrdu ? 'en' : 'ur' ?>&tab=<?= $activeTab ?>" class="px-3 py-1.5 rounded-xl bg-slate-800 border border-slate-700 text-xs font-bold text-slate-300 hover:text-white transition-all flex items-center gap-1.5">
                    <i data-lucide="languages" class="w-3.5 h-3.5 text-amber-400"></i>
                    <span><?= $isUrdu ? 'English' : 'اردو' ?></span>
                </a>

                <!-- Open Landing Page in new tab -->
                <a href="../landing/index.php" target="_blank" class="px-3 py-1.5 rounded-xl bg-slate-800 border border-slate-700 text-xs font-bold text-slate-300 hover:text-white transition-all hidden md:flex items-center gap-1.5">
                    <i data-lucide="globe" class="w-3.5 h-3.5 text-limoblue-400"></i>
                    <span><?= $isUrdu ? 'تعارفی ویب سائٹ' : 'Live Landing' ?></span>
                </a>

                <!-- Open Shop POS Software -->
                <a href="../dashboard/index.php" class="px-3 py-1.5 rounded-xl bg-emerald-600/20 border border-emerald-500/40 text-xs font-bold text-emerald-400 hover:bg-emerald-600 hover:text-white transition-all flex items-center gap-1.5">
                    <i data-lucide="store" class="w-3.5 h-3.5"></i>
                    <span><?= $isUrdu ? 'دکان POS کھولیں' : 'Open Shop POS' ?></span>
                </a>

                <!-- Logout / Lock CEO Portal -->
                <a href="../logout.php" class="p-2 rounded-xl bg-rose-500/10 border border-rose-500/30 text-rose-400 hover:bg-rose-500 hover:text-white transition-all" title="Lock CEO Suite">
                    <i data-lucide="log-out" class="w-4 h-4"></i>
                </a>
            </div>
        </div>
    </header>

    <!-- Main Container -->
    <main class="flex-1 max-w-7xl w-full mx-auto p-4 sm:p-6 lg:p-8 space-y-6">

        <!-- Top Alert Notification -->
        <?php if (!empty($alertMsg)): ?>
            <div class="p-4 rounded-2xl border text-sm font-bold flex items-center justify-between shadow-xl transition-all <?= $alertType === 'success' ? 'bg-emerald-500/15 border-emerald-500/40 text-emerald-300' : 'bg-rose-500/15 border-rose-500/40 text-rose-300' ?>">
                <div class="flex items-center gap-2.5">
                    <i data-lucide="<?= $alertType === 'success' ? 'check-circle-2' : 'alert-octagon' ?>" class="w-5 h-5 shrink-0"></i>
                    <span><?= htmlspecialchars($alertMsg) ?></span>
                </div>
                <button type="button" onclick="this.parentElement.remove()" class="p-1 rounded-lg hover:bg-white/10 transition-colors">✕</button>
            </div>
        <?php endif; ?>

        <!-- Include the full, rich CEO Master View -->
        <?php include __DIR__ . '/../backend/ceo_master_view.php'; ?>

    </main>

    <!-- Executive Footer -->
    <footer class="mt-auto border-t border-slate-800/80 bg-slate-950 py-6 text-center text-xs text-slate-500">
        <div class="max-w-7xl mx-auto px-4 flex flex-col sm:flex-row items-center justify-between gap-3">
            <div class="flex items-center gap-2">
                <span class="w-2 h-2 rounded-full bg-emerald-400"></span>
                <span class="font-bold text-slate-400">LimoMobile Headquarters Master Cloud</span>
                <span>•</span>
                <span>v3.0 Enterprise</span>
            </div>
            <p class="text-slate-500">
                <?= $isUrdu ? 'محفوظ و تصدیق شدہ سی ای او ماسٹر انٹرپرائز سوٹ © ' . date('Y') : 'Confidential CEO Command Center © ' . date('Y') . ' LimoMobile.' ?>
            </p>
        </div>
    </footer>
</div>

<script>
    lucide.createIcons();
</script>
</body>
</html>
