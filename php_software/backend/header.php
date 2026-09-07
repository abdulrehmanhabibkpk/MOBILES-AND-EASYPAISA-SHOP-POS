<?php
require_once __DIR__ . '/config.php';

// Strict Authentication Guard: Protect all software views from unauthorized access
requireAuth($pdo);

$settings = getShopSettings($pdo);
$shopName = htmlspecialchars($settings['shopName'] ?? ($isUrdu ? 'لیمو موبائل پی او ایس' : 'LimoMobile POS & EasyPaisa Shop'));
$currentUser = getLoggedInUser();

if (!isset($pageTitle)) {
    $pageTitle = $isUrdu ? 'ڈیش بورڈ (Dashboard)' : 'Dashboard';
}
if (!isset($activeMenu)) {
    $activeMenu = 'dashboard';
}

// Current URL for language & theme toggle links
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
    <title><?= $pageTitle ?> | <?= $shopName ?></title>
    <!-- Tailwind CSS CDN with LimoBlue and LimoGreen Configuration -->
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
                    },
                    animation: {
                        'float-slow': 'floatSlow 4s ease-in-out infinite',
                        'pulse-glow': 'pulseGlow 2s cubic-bezier(0.4, 0, 0.6, 1) infinite',
                        'slide-up': 'slideUpFade 0.4s cubic-bezier(0.16, 1, 0.3, 1) forwards',
                        'shimmer': 'shimmer 2.5s infinite linear'
                    },
                    keyframes: {
                        floatSlow: {
                            '0%, 100%': { transform: 'translateY(0px)' },
                            '50%': { transform: 'translateY(-6px)' }
                        },
                        pulseGlow: {
                            '0%, 100%': { opacity: '1', filter: 'drop-shadow(0 0 8px rgba(14, 165, 233, 0.6))' },
                            '50%': { opacity: '0.75', filter: 'drop-shadow(0 0 16px rgba(132, 204, 22, 0.8))' }
                        },
                        slideUpFade: {
                            '0%': { opacity: '0', transform: 'translateY(12px)' },
                            '100%': { opacity: '1', transform: 'translateY(0)' }
                        },
                        shimmer: {
                            '0%': { backgroundPosition: '-200% 0' },
                            '100%': { backgroundPosition: '200% 0' }
                        }
                    }
                }
            }
        }
    </script>
    <!-- Lucide Icons -->
    <script src="https://unpkg.com/lucide@latest"></script>
    <!-- Chart.js for smooth graphs matching Recharts -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <!-- Google Fonts: Inter & Plus Jakarta Sans -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800;900&family=Inter:wght@400;500;600;700;800;900&family=Noto+Sans+Arabic:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
    <style>
        body {
            font-family: <?= $isUrdu ? "'Noto Sans Arabic', 'Plus Jakarta Sans', 'Inter', sans-serif" : "'Plus Jakarta Sans', 'Inter', -apple-system, sans-serif" ?>;
        }

        /* -------------------------------------------------------------
           LIMO LIGHT THEME (Pristine Slate-50 + Crisp White + Limo Accents)
           ------------------------------------------------------------- */
        html.light body {
            background-color: #f8fafc !important;
            color: #0f172a !important;
        }
        html.light .bg-slate-950 {
            background-color: #f8fafc !important;
        }
        html.light .bg-slate-900,
        html.light .bg-slate-900\/90,
        html.light .bg-slate-850 {
            background-color: #ffffff !important;
            box-shadow: 0 1px 3px 0 rgba(15, 23, 42, 0.05), 0 1px 2px -1px rgba(15, 23, 42, 0.04) !important;
        }
        html.light .bg-slate-800,
        html.light .bg-slate-800\/80,
        html.light .bg-slate-800\/60,
        html.light .bg-slate-800\/40 {
            background-color: #f1f5f9 !important;
        }
        html.light .bg-slate-950\/60,
        html.light .bg-slate-950\/80 {
            background-color: #f8fafc !important;
        }
        html.light .border-slate-800,
        html.light .border-slate-800\/80,
        html.light .border-slate-800\/60,
        html.light .border-slate-700,
        html.light .border-slate-700\/80,
        html.light .border-slate-700\/60 {
            border-color: #e2e8f0 !important;
        }
        html.light .divide-slate-800 > * + *,
        html.light .divide-slate-800\/60 > * + * {
            border-color: #f1f5f9 !important;
        }
        html.light .text-white,
        html.light .text-slate-100,
        html.light .text-slate-200 {
            color: #0f172a !important;
        }
        html.light .text-slate-300 {
            color: #334155 !important;
        }
        html.light .text-slate-400 {
            color: #64748b !important;
        }
        html.light .text-slate-500 {
            color: #94a3b8 !important;
        }
        html.light aside#mainSidebar {
            background-color: #ffffff !important;
            border-color: #e2e8f0 !important;
            box-shadow: 2px 0 12px 0 rgba(15, 23, 42, 0.04) !important;
        }
        html.light header {
            background-color: rgba(255, 255, 255, 0.96) !important;
            border-color: #e2e8f0 !important;
            box-shadow: 0 1px 4px 0 rgba(15, 23, 42, 0.03) !important;
        }
        html.light input:not([type="checkbox"]):not([type="radio"]):not([type="range"]),
        html.light select,
        html.light textarea {
            background-color: #ffffff !important;
            color: #0f172a !important;
            border-color: #cbd5e1 !important;
        }
        html.light input:focus,
        html.light select:focus,
        html.light textarea:focus {
            border-color: #0284c7 !important;
            background-color: #ffffff !important;
            outline: none !important;
            box-shadow: 0 0 0 3px rgba(2, 132, 199, 0.15) !important;
        }
        html.light .hover\:bg-slate-800:hover,
        html.light .hover\:bg-slate-800\/40:hover,
        html.light .hover\:bg-slate-700:hover {
            background-color: #f1f5f9 !important;
            color: #0f172a !important;
        }
        html.light table thead th {
            background-color: #f8fafc !important;
            color: #475569 !important;
            border-color: #e2e8f0 !important;
        }
        html.light table tbody tr:hover {
            background-color: #f8fafc !important;
        }

        /* Limo Gradient Utilities */
        .bg-limo-gradient {
            background: linear-gradient(135deg, #0284c7 0%, #0ea5e9 50%, #84cc16 100%);
        }
        .text-limo-gradient {
            background: linear-gradient(135deg, #0284c7 0%, #0ea5e9 50%, #84cc16 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }

        /* Smooth Animation Transitions */
        .animate-card {
            transition: all 0.25s cubic-bezier(0.16, 1, 0.3, 1);
        }
        .animate-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 25px -5px rgba(2, 132, 199, 0.12), 0 8px 10px -6px rgba(132, 204, 22, 0.08);
        }

        /* Dark Theme Default */
        html.dark body {
            background-color: #090d16;
        }

        @media print {
            .no-print { display: none !important; }
            .print-only { display: block !important; }
        }
    </style>
</head>
<body class="<?= $isLight ? 'bg-slate-50 text-slate-900' : 'bg-slate-950 text-slate-100' ?> min-h-screen flex flex-col md:flex-row antialiased selection:bg-limoblue-500 selection:text-white">
<?php require_once __DIR__ . '/sidebar.php'; ?>
<div class="flex-1 flex flex-col min-w-0 overflow-x-hidden">
    <!-- Top Global Bar -->
    <header class="bg-slate-900/90 backdrop-blur border-b border-slate-800 px-4 sm:px-6 py-3.5 flex items-center justify-between no-print sticky top-0 z-40 transition-colors">
        <div class="flex items-center gap-3">
            <button id="mobileMenuBtn" class="md:hidden p-2 text-slate-400 hover:text-white rounded-xl bg-slate-800 border border-slate-700 transition-transform active:scale-95">
                <i data-lucide="menu" class="w-5 h-5"></i>
            </button>
            <div class="flex items-center gap-2.5">
                <div class="w-2.5 h-2.5 rounded-full bg-limogreen-500 animate-pulse"></div>
                <h1 class="text-base sm:text-lg font-black text-white flex items-center gap-2">
                    <?= $pageTitle ?>
                </h1>
            </div>
        </div>
        <div class="flex items-center gap-2.5 sm:gap-3.5 flex-wrap justify-end">
            
            <!-- Quick Navigation to Landing Page & Register Shop -->
            <a href="../landing/index.php" class="hidden md:inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl border text-xs font-bold text-slate-300 bg-slate-800 border-slate-700 hover:text-white hover:border-limoblue-400 transition-all hover:-translate-y-0.5" title="View Landing Page">
                <i data-lucide="globe" class="w-3.5 h-3.5 text-limoblue-400"></i>
                <span class="hidden lg:inline"><?= $isUrdu ? 'تعارفی صفحہ' : 'Landing Page' ?></span>
            </a>

            <a href="../register_shop/index.php" class="hidden sm:inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl border text-xs font-bold text-limogreen-400 bg-slate-800 border-slate-700 hover:text-limogreen-300 hover:border-limogreen-500 transition-all hover:-translate-y-0.5" title="Register New Shop">
                <i data-lucide="building-2" class="w-3.5 h-3.5"></i>
                <span class="hidden lg:inline"><?= $isUrdu ? 'نئی دکان' : 'Register Shop' ?></span>
            </a>

            <!-- Light / Dark Theme Switcher -->
            <div class="flex items-center bg-slate-800 p-1 rounded-xl border border-slate-700 shadow-inner">
                <a href="<?= $lightUri ?>" class="px-2.5 py-1 rounded-lg text-xs font-bold transition-all flex items-center gap-1.5 <?= $isLight ? 'bg-amber-500 text-slate-950 shadow-sm font-black' : 'text-slate-400 hover:text-white' ?>" title="Switch to Light Theme">
                    <i data-lucide="sun" class="w-3.5 h-3.5"></i>
                    <span class="hidden sm:inline"><?= $isUrdu ? 'لائٹ' : 'Light' ?></span>
                </a>
                <a href="<?= $darkUri ?>" class="px-2.5 py-1 rounded-lg text-xs font-bold transition-all flex items-center gap-1.5 <?= $isDark ? 'bg-slate-900 text-limoblue-400 shadow-sm border border-slate-700' : 'text-slate-400 hover:text-white' ?>" title="Switch to Dark Theme">
                    <i data-lucide="moon" class="w-3.5 h-3.5"></i>
                    <span class="hidden sm:inline"><?= $isUrdu ? 'ڈارک' : 'Dark' ?></span>
                </a>
            </div>

            <!-- Language Switcher Toggle (English / اردو) -->
            <div class="flex items-center bg-slate-800 p-1 rounded-xl border border-slate-700 shadow-inner">
                <a href="<?= $enUri ?>" class="px-2.5 py-1 rounded-lg text-xs font-bold transition-all flex items-center gap-1.5 <?= $isEnglish ? 'bg-gradient-to-r from-limoblue-600 to-limoblue-500 text-white shadow-sm font-black' : 'text-slate-400 hover:text-white' ?>" title="Switch to English">
                    <span>🇬🇧 EN</span>
                </a>
                <a href="<?= $urUri ?>" class="px-2.5 py-1 rounded-lg text-xs font-bold transition-all flex items-center gap-1.5 <?= $isUrdu ? 'bg-gradient-to-r from-limoblue-600 to-limoblue-500 text-white shadow-sm font-black' : 'text-slate-400 hover:text-white' ?>" title="اردو میں تبدیل کریں">
                    <span>🇵🇰 اردو</span>
                </a>
            </div>

            <span class="hidden xl:flex items-center gap-1.5 px-3 py-1 bg-limoblue-500/10 text-limoblue-600 dark:text-limoblue-400 border border-limoblue-500/20 text-xs rounded-full font-bold">
                <i data-lucide="store" class="w-3.5 h-3.5 text-limogreen-500"></i>
                <?= $shopName ?>
            </span>

            <!-- User Auth Status / Quick Lock -->
            <?php if (isUserLoggedIn()): $u = getLoggedInUser(); ?>
                <div class="flex items-center gap-1.5 bg-slate-800 p-1 rounded-xl border border-slate-700 shadow-sm">
                    <a href="../admin/index.php" class="flex items-center gap-1.5 px-2 py-1 rounded-lg text-xs font-bold text-limoblue-400 hover:bg-slate-700/80 transition-colors" title="<?= $isUrdu ? 'ایڈمن پینل کھولیں' : 'Open Admin Panel' ?>">
                        <span class="w-2 h-2 rounded-full bg-limogreen-400 animate-pulse"></span>
                        <span class="max-w-[120px] truncate"><?= htmlspecialchars($u['name'] ?? 'User') ?></span>
                        <span class="px-1.5 py-0.2 rounded bg-limoblue-500/20 text-[10px] font-mono font-black text-limoblue-300"><?= htmlspecialchars($u['role'] ?? 'Owner') ?></span>
                    </a>
                    <a href="../logout.php" class="px-2 py-1 bg-rose-500/10 hover:bg-rose-500/20 text-rose-400 border border-rose-500/30 text-xs rounded-lg font-bold transition-colors flex items-center gap-1" title="<?= $isUrdu ? 'لاگ آؤٹ یا سکرین لاک کریں' : 'Lock screen / Logout' ?>">
                        <i data-lucide="lock" class="w-3.5 h-3.5"></i>
                        <span class="hidden md:inline"><?= $isUrdu ? 'لاک' : 'Lock' ?></span>
                    </a>
                </div>
            <?php else: ?>
                <a href="../login/index.php" class="inline-flex items-center gap-1 px-3 py-1.5 bg-gradient-to-r from-limoblue-600 to-limogreen-600 hover:from-limoblue-500 hover:to-limogreen-500 text-white text-xs rounded-xl font-bold transition-all shadow-sm" title="Login">
                    <i data-lucide="log-in" class="w-3.5 h-3.5"></i>
                    <span><?= $isUrdu ? 'لاگ ان' : 'Login' ?></span>
                </a>
            <?php endif; ?>

            <div class="text-[11px] sm:text-xs text-slate-500 dark:text-slate-400 font-mono bg-slate-800/80 px-2.5 py-1 rounded-xl border border-slate-700/60 hidden sm:block" id="liveClock"></div>
        </div>
    </header>
    <!-- Main Content Area with slideUp animation -->
    <main class="flex-1 p-3 sm:p-5 md:p-6 overflow-y-auto animate-slide-up">

        <?php if (isset($_GET['access_denied'])): ?>
            <div class="mb-4 p-4 rounded-2xl bg-rose-500/10 border border-rose-500/30 text-rose-400 text-xs sm:text-sm font-bold flex items-center justify-between shadow-lg">
                <div class="flex items-center gap-2.5">
                    <i data-lucide="shield-alert" class="w-5 h-5 text-rose-500 shrink-0"></i>
                    <span><?= $isUrdu ? 'محترم صارف! یہ ماڈیول صرف دکان مالک (Owner) یا ایڈمن (Admin) کے لیے مخصوص ہے۔' : 'Access Restricted: This administrative section requires Owner or Admin permissions.' ?></span>
                </div>
                <a href="index.php" class="px-3 py-1 bg-rose-500/20 hover:bg-rose-500/30 text-rose-300 rounded-lg text-xs font-bold transition-colors">✕</a>
            </div>
        <?php endif; ?>

