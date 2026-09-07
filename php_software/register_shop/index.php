<?php
/**
 * Balal Mobile & EasyPaisa POS - Register New Shop Wizard (PHP)
 * Multi-Step Professional Registration with Automatic Config Initialization
 */
require_once __DIR__ . '/../backend/config.php';
$settings = getShopSettings($pdo);

$success = false;
$errorMessage = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $shopName = trim($_POST['shop_name'] ?? '');
    $ownerName = trim($_POST['owner_name'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $email = trim(strtolower($_POST['email'] ?? ''));
    $password = trim($_POST['password'] ?? '');
    $city = trim($_POST['city'] ?? '');
    $address = trim($_POST['address'] ?? '');
    $pinCode = trim($_POST['pin_code'] ?? '6242');
    $thermalSize = trim($_POST['thermal_size'] ?? '80mm');
    $openingCash = floatval($_POST['opening_cash'] ?? 0);
    $openingEasypaisa = floatval($_POST['opening_easypaisa'] ?? 0);
    $language = trim($_POST['language'] ?? 'ur');
    $theme = trim($_POST['theme'] ?? 'light');

    if (empty($shopName) || empty($ownerName) || empty($phone)) {
        $errorMessage = $isUrdu ? 'برائے مہربانی دکان کا نام، مالک کا نام اور فون نمبر لازمی درج کریں۔' : 'Please fill all required shop details.';
    } else {
        try {
            $shopId = 'shp-' . time();
            $userId = 'usr-' . time();
            $passHash = password_hash(!empty($password) ? $password : 'admin123', PASSWORD_DEFAULT);

            // 1. Insert into registered_shops table
            $stmt = $pdo->prepare("INSERT INTO registered_shops (id, shop_name, owner_name, phone, email, city, address, pin_code, thermal_size, opening_cash, opening_easypaisa, created_at) VALUES (:id, :shop_name, :owner_name, :phone, :email, :city, :address, :pin_code, :thermal_size, :opening_cash, :opening_easypaisa, :created_at)");
            $stmt->execute([
                ':id' => $shopId,
                ':shop_name' => $shopName,
                ':owner_name' => $ownerName,
                ':phone' => $phone,
                ':email' => !empty($email) ? $email : "owner_{$shopId}@balalmobile.com",
                ':city' => $city,
                ':address' => $address,
                ':pin_code' => !empty($pinCode) ? $pinCode : '6242',
                ':thermal_size' => $thermalSize,
                ':opening_cash' => $openingCash,
                ':opening_easypaisa' => $openingEasypaisa,
                ':created_at' => time()
            ]);

            // 2. Insert into users table
            $userEmail = !empty($email) ? $email : "owner_{$shopId}@balalmobile.com";
            $stmtUser = $pdo->prepare("INSERT OR REPLACE INTO users (id, name, email, password_hash, pin_code, role, shop_name, phone, created_at) VALUES (:id, :name, :email, :password_hash, :pin_code, 'Owner', :shop_name, :phone, :created_at)");
            $stmtUser->execute([
                ':id' => $userId,
                ':name' => $ownerName,
                ':email' => $userEmail,
                ':password_hash' => $passHash,
                ':pin_code' => !empty($pinCode) ? $pinCode : '6242',
                ':shop_name' => $shopName,
                ':phone' => $phone,
                ':created_at' => time()
            ]);

            // 3. Save as current active shop_config
            $newSettings = [
                'shopName' => $shopName,
                'ownerName' => $ownerName,
                'phone' => $phone,
                'address' => (!empty($city) ? $city . ', ' : '') . $address,
                'pinCode' => !empty($pinCode) ? $pinCode : '6242',
                'language' => $language,
                'theme' => $theme,
                'thermalSize' => $thermalSize
            ];
            saveShopSettings($pdo, $newSettings);

            // 4. Update today's opening balance
            $today = date('Y-m-d');
            $stmtBal = $pdo->prepare("INSERT OR REPLACE INTO daily_balances (date, opening_cash, opening_easypaisa, notes) VALUES (:date, :opening_cash, :opening_easypaisa, :notes)");
            $stmtBal->execute([
                ':date' => $today,
                ':opening_cash' => $openingCash,
                ':opening_easypaisa' => $openingEasypaisa,
                ':notes' => 'نئی دکان رجسٹریشن کے وقت افتتاحی بیلنس'
            ]);

            // 5. Start user session and redirect to dashboard
            loginUserSession([
                'id' => $userId,
                'name' => $ownerName,
                'email' => $userEmail,
                'role' => 'Owner',
                'shop_name' => $shopName,
                'phone' => $phone
            ]);

            header("Location: ../dashboard/index.php?registered=1");
            exit();

        } catch (Exception $e) {
            $errorMessage = "Registration Error: " . $e->getMessage();
        }
    }
}

// Current URL for language & theme toggle
$currentUri = $_SERVER['REQUEST_URI'] ?? '';
$enUri = str_contains($currentUri, '?') ? (preg_replace('/([?&])lang=[^&]+(&|$)/', '$1', $currentUri) . '&lang=en') : ($currentUri . '?lang=en');
$urUri = str_contains($currentUri, '?') ? (preg_replace('/([?&])lang=[^&]+(&|$)/', '$1', $currentUri) . '&lang=ur') : ($currentUri . '?lang=ur');
$enUri = str_replace('?&', '?', $enUri);
$urUri = str_replace('?&', '?', $urUri);

$lightUri = str_contains($currentUri, '?') ? (preg_replace('/([?&])theme=[^&]+(&|$)/', '$1', $currentUri) . '&theme=light') : ($currentUri . '?theme=light');
$darkUri = str_contains($currentUri, '?') ? (preg_replace('/([?&])theme=[^&]+(&|$)/', '$1', $currentUri) . '&theme=dark') : ($currentUri . '?theme=dark');
$lightUri = str_replace('?&', '?', $lightUri);
$darkUri = str_replace('?&', '?', $darkUri);
?>
<!DOCTYPE html>
<html lang="<?= $currentLang ?>" dir="<?= $isUrdu ? 'rtl' : 'ltr' ?>" class="<?= $currentTheme ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $isUrdu ? 'نئی دکان رجسٹر کریں' : 'Register New Shop' ?> | LimoMobile POS</title>
    <!-- Tailwind CSS CDN with LimoBlue and LimoGreen Theme Configuration -->
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            darkMode: 'class',
            theme: {
                extend: {
                    colors: {
                        limoblue: {
                            50: '#f0f9ff',
                            100: '#e0f2fe',
                            200: '#bae6fd',
                            300: '#7dd3fc',
                            400: '#38bdf8',
                            500: '#0ea5e9',
                            600: '#0284c7',
                            700: '#0369a1',
                            800: '#075985',
                            900: '#0c4a6e',
                            950: '#082f49'
                        },
                        limogreen: {
                            50: '#f7fee7',
                            100: '#ecfccb',
                            200: '#d9f99d',
                            300: '#bef264',
                            400: '#a3e635',
                            500: '#84cc16',
                            600: '#65a30d',
                            700: '#4d7c0f',
                            800: '#3f6212',
                            900: '#365314',
                            950: '#1a2e05'
                        },
                        slate: {
                            850: '#111827',
                            950: '#090d16'
                        }
                    }
                }
            }
        }
    </script>
    <!-- Lucide Icons -->
    <script src="https://unpkg.com/lucide@latest"></script>
    <!-- Google Fonts: Plus Jakarta Sans, Inter & Noto Sans Arabic -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800;900&family=Inter:wght@400;500;600;700;800;900&family=Noto+Sans+Arabic:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
    <style>
        body {
            font-family: <?= $isUrdu ? "'Noto Sans Arabic', 'Plus Jakarta Sans', 'Inter', sans-serif" : "'Plus Jakarta Sans', 'Inter', -apple-system, sans-serif" ?>;
        }
        html.light body {
            background-color: #f8fafc !important;
            color: #0f172a !important;
        }
        html.light .card-bg {
            background-color: #ffffff !important;
            box-shadow: 0 10px 25px -5px rgba(2, 132, 199, 0.08), 0 8px 10px -6px rgba(132, 204, 22, 0.05);
            border-color: #e2e8f0 !important;
        }
        html.dark .card-bg {
            background-color: #0f172a;
            border-color: #1e293b;
        }
    </style>
</head>
<body class="<?= $isLight ? 'bg-slate-100 text-slate-900' : 'bg-slate-950 text-slate-100' ?> min-h-screen flex flex-col justify-between p-4 sm:p-6 antialiased selection:bg-limoblue-500 selection:text-white">

    <!-- Top Bar -->
    <div class="w-full max-w-4xl mx-auto flex items-center justify-between pb-4">
        <a href="../landing/index.php" class="flex items-center gap-2 text-xs sm:text-sm font-bold <?= $isLight ? 'text-slate-600 hover:text-limoblue-700' : 'text-slate-400 hover:text-limoblue-400' ?> transition-colors">
            <i data-lucide="arrow-left" class="w-4 h-4"></i>
            <span><?= $isUrdu ? 'سافٹ ویئر تعارفی صفحہ' : 'Landing Page' ?></span>
        </a>

        <div class="flex items-center gap-2">
            <!-- Theme Switcher -->
            <a href="<?= $isLight ? $darkUri : $lightUri ?>" class="p-2 rounded-xl border <?= $isLight ? 'bg-white border-slate-200 text-slate-700' : 'bg-slate-900 border-slate-800 text-amber-400' ?>" title="Toggle Theme">
                <i data-lucide="<?= $isLight ? 'moon' : 'sun' ?>" class="w-4 h-4"></i>
            </a>

            <!-- Language Switcher -->
            <a href="<?= $isUrdu ? $enUri : $urUri ?>" class="px-2.5 py-1.5 rounded-xl border text-xs font-bold <?= $isLight ? 'bg-white border-slate-200 text-slate-700' : 'bg-slate-900 border-slate-800 text-slate-300' ?> hover:border-limoblue-500 transition-colors">
                <span><?= $isUrdu ? '🇬🇧 English' : '🇵🇰 اردو' ?></span>
            </a>
        </div>
    </div>

    <!-- Registration Wizard Container -->
    <div class="w-full max-w-3xl mx-auto my-auto">
        <div class="card-bg rounded-3xl border p-6 sm:p-10 relative shadow-2xl overflow-hidden">
            
            <!-- Wizard Header -->
            <div class="text-center mb-8">
                <div class="w-16 h-16 rounded-2xl bg-gradient-to-tr from-limoblue-600 via-sky-500 to-limogreen-500 text-white flex items-center justify-center mx-auto mb-3 shadow-lg shadow-limoblue-500/25 border border-limoblue-400/20">
                    <i data-lucide="building-2" class="w-8 h-8"></i>
                </div>
                <h2 class="text-2xl sm:text-3xl font-black <?= $isLight ? 'text-slate-900' : 'text-white' ?>">
                    <?= $isUrdu ? 'نئی دکان کا اندراج (Register New Shop)' : 'Register New Mobile Shop' ?>
                </h2>
                <p class="text-xs sm:text-sm text-limogreen-500 font-bold mt-1">
                    <?= $isUrdu ? 'صرف 2 آسان مراحل میں اپنا پی او ایس اور کھاتہ سیٹ اپ کریں' : 'Quick 2-Step Shop Setup & Instant Launch' ?>
                </p>
            </div>

            <?php if (!empty($errorMessage)): ?>
                <div class="p-3.5 rounded-xl bg-rose-500/10 border border-rose-500/30 text-rose-500 text-xs font-bold mb-6 flex items-center gap-2">
                    <i data-lucide="alert-circle" class="w-4 h-4 shrink-0"></i>
                    <span><?= htmlspecialchars($errorMessage) ?></span>
                </div>
            <?php endif; ?>

            <!-- Step Progress Indicator -->
            <div class="flex items-center justify-center gap-4 mb-8">
                <div class="flex items-center gap-2">
                    <div id="step1Indicator" class="w-8 h-8 rounded-full bg-limoblue-600 text-white font-black text-xs flex items-center justify-center shadow-md">1</div>
                    <span class="text-xs font-bold <?= $isLight ? 'text-slate-800' : 'text-white' ?>"><?= $isUrdu ? 'دکان و رابطہ معلومات' : 'Shop & Contact' ?></span>
                </div>
                <div class="w-12 h-0.5 bg-slate-700"></div>
                <div class="flex items-center gap-2">
                    <div id="step2Indicator" class="w-8 h-8 rounded-full bg-slate-700 text-slate-400 font-black text-xs flex items-center justify-center">2</div>
                    <span class="text-xs font-bold text-slate-400"><?= $isUrdu ? 'سیکیورٹی و ابتدائی کیش' : 'Security & Balances' ?></span>
                </div>
            </div>

            <!-- Form -->
            <form method="POST" id="registerShopForm" class="space-y-6">

                <!-- STEP 1 CONTENT -->
                <div id="step1Content" class="space-y-4">
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <!-- Shop Name -->
                        <div>
                            <label class="block text-xs font-bold mb-1.5 <?= $isLight ? 'text-slate-700' : 'text-slate-300' ?>">
                                <?= $isUrdu ? 'دکان کا نام (Shop Name) *' : 'Shop Name *' ?>
                            </label>
                            <div class="relative">
                                <div class="absolute inset-y-0 <?= $isUrdu ? 'right-0 pr-3.5' : 'left-0 pl-3.5' ?> flex items-center pointer-events-none text-slate-400">
                                    <i data-lucide="store" class="w-4 h-4"></i>
                                </div>
                                <input
                                    type="text"
                                    name="shop_name"
                                    id="shopNameInput"
                                    placeholder="<?= $isUrdu ? 'مثال: لیمو موبائلز اینڈ کمیونیکیشن' : 'e.g. Limo Mobiles & POS' ?>"
                                    required
                                    class="w-full <?= $isUrdu ? 'pr-10 pl-3' : 'pl-10 pr-3' ?> py-2.5 rounded-xl border text-sm <?= $isLight ? 'bg-white border-slate-300 text-slate-900' : 'bg-slate-900 border-slate-700 text-white' ?> focus:outline-none focus:border-limoblue-500 font-semibold"
                                />
                            </div>
                        </div>

                        <!-- Owner Name -->
                        <div>
                            <label class="block text-xs font-bold mb-1.5 <?= $isLight ? 'text-slate-700' : 'text-slate-300' ?>">
                                <?= $isUrdu ? 'مالک کا نام (Owner Name) *' : 'Owner Name *' ?>
                            </label>
                            <div class="relative">
                                <div class="absolute inset-y-0 <?= $isUrdu ? 'right-0 pr-3.5' : 'left-0 pl-3.5' ?> flex items-center pointer-events-none text-slate-400">
                                    <i data-lucide="user" class="w-4 h-4"></i>
                                </div>
                                <input
                                    type="text"
                                    name="owner_name"
                                    placeholder="<?= $isUrdu ? 'محمد بلال خان' : 'Muhammad Bilal' ?>"
                                    required
                                    class="w-full <?= $isUrdu ? 'pr-10 pl-3' : 'pl-10 pr-3' ?> py-2.5 rounded-xl border text-sm <?= $isLight ? 'bg-white border-slate-300 text-slate-900' : 'bg-slate-900 border-slate-700 text-white' ?> focus:outline-none focus:border-limoblue-500 font-semibold"
                                />
                            </div>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <!-- Phone / WhatsApp -->
                        <div>
                            <label class="block text-xs font-bold mb-1.5 <?= $isLight ? 'text-slate-700' : 'text-slate-300' ?>">
                                <?= $isUrdu ? 'موبائل / واٹس ایپ نمبر *' : 'WhatsApp / Phone Number *' ?>
                            </label>
                            <div class="relative">
                                <div class="absolute inset-y-0 <?= $isUrdu ? 'right-0 pr-3.5' : 'left-0 pl-3.5' ?> flex items-center pointer-events-none text-slate-400">
                                    <i data-lucide="phone" class="w-4 h-4"></i>
                                </div>
                                <input
                                    type="tel"
                                    name="phone"
                                    placeholder="0300-1234567"
                                    required
                                    class="w-full <?= $isUrdu ? 'pr-10 pl-3' : 'pl-10 pr-3' ?> py-2.5 rounded-xl border text-sm <?= $isLight ? 'bg-white border-slate-300 text-slate-900' : 'bg-slate-900 border-slate-700 text-white' ?> focus:outline-none focus:border-limoblue-500 font-mono font-semibold"
                                />
                            </div>
                        </div>

                        <!-- City -->
                        <div>
                            <label class="block text-xs font-bold mb-1.5 <?= $isLight ? 'text-slate-700' : 'text-slate-300' ?>">
                                <?= $isUrdu ? 'شہر / مارکیٹ (City)' : 'City / Location' ?>
                            </label>
                            <div class="relative">
                                <div class="absolute inset-y-0 <?= $isUrdu ? 'right-0 pr-3.5' : 'left-0 pl-3.5' ?> flex items-center pointer-events-none text-slate-400">
                                    <i data-lucide="map-pin" class="w-4 h-4"></i>
                                </div>
                                <input
                                    type="text"
                                    name="city"
                                    placeholder="<?= $isUrdu ? 'لاہور / راولپنڈی / فیصل آباد' : 'Lahore, Karachi, etc.' ?>"
                                    class="w-full <?= $isUrdu ? 'pr-10 pl-3' : 'pl-10 pr-3' ?> py-2.5 rounded-xl border text-sm <?= $isLight ? 'bg-white border-slate-300 text-slate-900' : 'bg-slate-900 border-slate-700 text-white' ?> focus:outline-none focus:border-limoblue-500"
                                />
                            </div>
                        </div>
                    </div>

                    <!-- Shop Address -->
                    <div>
                        <label class="block text-xs font-bold mb-1.5 <?= $isLight ? 'text-slate-700' : 'text-slate-300' ?>">
                            <?= $isUrdu ? 'دکان کا مکمل پتہ (انوائس اور رسید پر پرنٹ کے لیے)' : 'Full Shop Address (for receipts)' ?>
                        </label>
                        <input
                            type="text"
                            name="address"
                            placeholder="<?= $isUrdu ? 'دکان نمبر 12، موبائل پلازہ، مین بازار' : 'Shop #12, Mobile Plaza, Main Market' ?>"
                            class="w-full px-3.5 py-2.5 rounded-xl border text-sm <?= $isLight ? 'bg-white border-slate-300 text-slate-900' : 'bg-slate-900 border-slate-700 text-white' ?> focus:outline-none focus:border-limoblue-500"
                        />
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <!-- Email -->
                        <div>
                            <label class="block text-xs font-bold mb-1.5 <?= $isLight ? 'text-slate-700' : 'text-slate-300' ?>">
                                <?= $isUrdu ? 'لاگ ان ای میل (Login Email)' : 'Login Email' ?>
                            </label>
                            <input
                                type="email"
                                name="email"
                                placeholder="owner@myshop.com"
                                class="w-full px-3.5 py-2.5 rounded-xl border text-sm <?= $isLight ? 'bg-white border-slate-300 text-slate-900' : 'bg-slate-900 border-slate-700 text-white' ?> focus:outline-none focus:border-limoblue-500"
                            />
                        </div>

                        <!-- Password -->
                        <div>
                            <label class="block text-xs font-bold mb-1.5 <?= $isLight ? 'text-slate-700' : 'text-slate-300' ?>">
                                <?= $isUrdu ? 'پاسورڈ (Login Password)' : 'Login Password' ?>
                            </label>
                            <input
                                type="password"
                                name="password"
                                placeholder="••••••••"
                                class="w-full px-3.5 py-2.5 rounded-xl border text-sm <?= $isLight ? 'bg-white border-slate-300 text-slate-900' : 'bg-slate-900 border-slate-700 text-white' ?> focus:outline-none focus:border-limoblue-500"
                            />
                        </div>
                    </div>

                    <!-- Step 1 Next Button -->
                    <div class="pt-4 flex justify-end">
                        <button type="button" onclick="goToStep(2)" class="py-3 px-8 rounded-xl bg-gradient-to-r from-limoblue-600 to-limogreen-600 hover:from-limoblue-500 hover:to-limogreen-500 text-white font-extrabold text-sm shadow-lg shadow-limoblue-600/30 flex items-center gap-2 cursor-pointer transition-all hover:-translate-y-0.5">
                            <span><?= $isUrdu ? 'اگلا مرحلہ: سیکیورٹی و بیلنس' : 'Next: Security & Balances' ?></span>
                            <i data-lucide="arrow-right" class="w-4 h-4"></i>
                        </button>
                    </div>
                </div>

                <!-- STEP 2 CONTENT (Hidden initially) -->
                <div id="step2Content" class="space-y-5 hidden">
                    
                    <!-- PIN Code -->
                    <div class="p-4 rounded-2xl border <?= $isLight ? 'bg-slate-50 border-slate-200' : 'bg-slate-900/60 border-slate-800' ?>">
                        <label class="block text-xs font-bold mb-1 <?= $isLight ? 'text-slate-800' : 'text-white' ?>">
                            <?= $isUrdu ? 'روزانہ اسکرین لاک پن کوڈ (Daily Lock PIN - 4 Digits)' : 'Daily Screen Lock PIN (4 Digits)' ?>
                        </label>
                        <p class="text-[11px] text-slate-400 mb-2">
                            <?= $isUrdu ? 'یہ پن روزانہ دکان کیش دراز اور سافٹ ویئر کو فوری ان لاک کرنے کے لیے استعمال ہوگا' : 'Used to lock and quickly unlock your POS screen during daily shop operations' ?>
                        </p>
                        <input
                            type="text"
                            name="pin_code"
                            value="6242"
                            maxlength="6"
                            class="w-40 px-3.5 py-2.5 rounded-xl border text-center text-lg font-mono font-black tracking-widest <?= $isLight ? 'bg-white border-slate-300 text-slate-900' : 'bg-slate-900 border-slate-700 text-white' ?> focus:outline-none focus:border-limoblue-500"
                        />
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <!-- Initial Cash in Drawer -->
                        <div class="p-4 rounded-2xl border <?= $isLight ? 'bg-slate-50 border-slate-200' : 'bg-slate-900/60 border-slate-800' ?>">
                            <label class="block text-xs font-bold mb-1 <?= $isLight ? 'text-slate-800' : 'text-white' ?>">
                                <?= $isUrdu ? 'دکان دراز میں افتتاحی کیش (Opening Cash)' : 'Opening Cash in Drawer (Rs.)' ?>
                            </label>
                            <input
                                type="number"
                                name="opening_cash"
                                value="45000"
                                step="any"
                                class="w-full px-3.5 py-2.5 rounded-xl border text-sm font-mono font-bold <?= $isLight ? 'bg-white border-slate-300 text-slate-900' : 'bg-slate-900 border-slate-700 text-white' ?> focus:outline-none focus:border-limoblue-500"
                            />
                        </div>

                        <!-- Initial EasyPaisa Balance -->
                        <div class="p-4 rounded-2xl border <?= $isLight ? 'bg-slate-50 border-slate-200' : 'bg-slate-900/60 border-slate-800' ?>">
                            <label class="block text-xs font-bold mb-1 <?= $isLight ? 'text-slate-800' : 'text-white' ?>">
                                <?= $isUrdu ? 'افتتاحی ایزی پیسہ / جازکیش بیلنس' : 'Opening EasyPaisa Balance (Rs.)' ?>
                            </label>
                            <input
                                type="number"
                                name="opening_easypaisa"
                                value="85000"
                                step="any"
                                class="w-full px-3.5 py-2.5 rounded-xl border text-sm font-mono font-bold <?= $isLight ? 'bg-white border-slate-300 text-slate-900' : 'bg-slate-900 border-slate-700 text-white' ?> focus:outline-none focus:border-limoblue-500"
                            />
                        </div>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <!-- Thermal Receipt Size -->
                        <div>
                            <label class="block text-xs font-bold mb-1.5 <?= $isLight ? 'text-slate-700' : 'text-slate-300' ?>">
                                <?= $isUrdu ? 'تھرمل پرنٹر سائز (Printer Size)' : 'Thermal Receipt Size' ?>
                            </label>
                            <select name="thermal_size" class="w-full px-3.5 py-2.5 rounded-xl border text-sm <?= $isLight ? 'bg-white border-slate-300 text-slate-900' : 'bg-slate-900 border-slate-700 text-white' ?>">
                                <option value="80mm">80mm (معیاری بڑا تھرمل پرنٹر)</option>
                                <option value="58mm">58mm (چھوٹا پورٹیبل تھرمل پرنٹر)</option>
                            </select>
                        </div>

                        <!-- Default Language -->
                        <div>
                            <label class="block text-xs font-bold mb-1.5 <?= $isLight ? 'text-slate-700' : 'text-slate-300' ?>">
                                <?= $isUrdu ? 'پسندیدہ زبان (Language)' : 'Default Language' ?>
                            </label>
                            <select name="language" class="w-full px-3.5 py-2.5 rounded-xl border text-sm <?= $isLight ? 'bg-white border-slate-300 text-slate-900' : 'bg-slate-900 border-slate-700 text-white' ?>">
                                <option value="ur">🇵🇰 اردو (Urdu)</option>
                                <option value="en">🇬🇧 English</option>
                            </select>
                        </div>
                    </div>

                    <!-- Step 2 Navigation Actions -->
                    <div class="pt-4 flex items-center justify-between">
                        <button type="button" onclick="goToStep(1)" class="py-3 px-6 rounded-xl border <?= $isLight ? 'border-slate-300 bg-white hover:bg-slate-50 text-slate-700' : 'border-slate-700 bg-slate-800 hover:bg-slate-700 text-white' ?> font-bold text-sm flex items-center gap-2 cursor-pointer transition-all">
                            <i data-lucide="arrow-left" class="w-4 h-4"></i>
                            <span><?= $isUrdu ? 'پچھلا مرحلہ' : 'Back' ?></span>
                        </button>

                        <button type="submit" class="py-3.5 px-8 rounded-xl bg-gradient-to-r from-limoblue-600 to-limogreen-600 hover:from-limoblue-500 hover:to-limogreen-500 text-white font-extrabold text-sm shadow-xl shadow-limoblue-600/40 flex items-center gap-2 cursor-pointer transition-all hover:-translate-y-0.5">
                            <i data-lucide="check-circle-2" class="w-5 h-5"></i>
                            <span><?= $isUrdu ? 'دکان رجسٹر کریں اور پی او ایس شروع کریں' : 'Register Shop & Launch POS' ?></span>
                        </button>
                    </div>
                </div>

            </form>

            <!-- Back to Login -->
            <div class="mt-8 text-center pt-4 border-t <?= $isLight ? 'border-slate-200' : 'border-slate-800' ?>">
                <span class="text-xs text-slate-400"><?= $isUrdu ? 'پہلے سے دکان رجسٹرڈ ہے؟' : 'Already have a registered shop?' ?></span>
                <a href="../login/index.php" class="text-xs font-bold text-limoblue-500 hover:underline mr-1 ml-1">
                    <?= $isUrdu ? 'لاگ ان کریں (Login Here)' : 'Login here' ?>
                </a>
            </div>

        </div>
    </div>

    <!-- Footer Note -->
    <div class="w-full text-center text-xs text-slate-500 pt-6">
        <span>LimoMobile POS & EasyPaisa Cloud Engine v2.5</span>
    </div>

    <!-- Wizard Scripts -->
    <script>
        lucide.createIcons();

        function goToStep(step) {
            const step1Content = document.getElementById('step1Content');
            const step2Content = document.getElementById('step2Content');
            const step1Ind = document.getElementById('step1Indicator');
            const step2Ind = document.getElementById('step2Indicator');

            if (step === 2) {
                const shopName = document.getElementById('shopNameInput').value.trim();
                if (!shopName) {
                    alert('<?= $isUrdu ? "برائے مہربانی دکان کا نام درج کریں۔" : "Please enter shop name." ?>');
                    document.getElementById('shopNameInput').focus();
                    return;
                }
                step1Content.classList.add('hidden');
                step2Content.classList.remove('hidden');
                step2Ind.className = "w-8 h-8 rounded-full bg-limoblue-600 text-white font-black text-xs flex items-center justify-center shadow-md";
            } else {
                step2Content.classList.add('hidden');
                step1Content.classList.remove('hidden');
                step2Ind.className = "w-8 h-8 rounded-full bg-slate-700 text-slate-400 font-black text-xs flex items-center justify-center";
            }
        }
    </script>
</body>
</html>
