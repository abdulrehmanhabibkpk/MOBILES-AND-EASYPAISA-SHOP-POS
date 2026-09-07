<?php
/**
 * Balal Mobile & EasyPaisa POS - Professional Landing Page
 * Modern, High-Contrast, Bilingual (Urdu & English) Showcase
 */
require_once __DIR__ . '/../backend/config.php';
$settings = getShopSettings($pdo);
$shopName = htmlspecialchars($settings['shopName'] ?? ($isUrdu ? 'بلال موبائلز اینڈ ایزی پیسہ شاپ' : 'Balal Mobiles & EasyPaisa POS'));
$ownerName = htmlspecialchars($settings['ownerName'] ?? 'بلال خان');
$phone = htmlspecialchars($settings['phone'] ?? '0300-1234567');
$address = htmlspecialchars($settings['address'] ?? 'مین مارکیٹ');

$pageTitle = $isUrdu ? 'تعارفی صفحہ و خصوصیات' : 'Product Landing & Features';

// Fetch Published Landing Posts from CEO-Master Publisher
$landingPosts = [];
try {
    $stmt = $pdo->query("SELECT * FROM landing_posts WHERE status = 'PUBLISHED' ORDER BY is_pinned DESC, created_at DESC");
    $landingPosts = $stmt->fetchAll();
} catch (Exception $e) {}

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
    <title><?= $shopName ?> - <?= $isUrdu ? 'مکمل موبائل شاپ پی او ایس و کھاتہ سافٹ ویئر' : 'Complete Mobile POS & EasyPaisa Khata Software' ?></title>
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
                    },
                    animation: {
                        'float-slow': 'floatSlow 4s ease-in-out infinite',
                        'pulse-glow': 'pulseGlow 2s cubic-bezier(0.4, 0, 0.6, 1) infinite',
                        'slide-up': 'slideUpFade 0.4s cubic-bezier(0.16, 1, 0.3, 1) forwards'
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
        html.light .bg-slate-900,
        html.light .bg-slate-950,
        html.light .bg-slate-850 {
            background-color: #ffffff !important;
            box-shadow: 0 4px 6px -1px rgba(15, 23, 42, 0.04), 0 2px 4px -2px rgba(15, 23, 42, 0.04);
        }
        html.light .bg-slate-800 {
            background-color: #f1f5f9 !important;
        }
        html.light .border-slate-800,
        html.light .border-slate-700 {
            border-color: #e2e8f0 !important;
        }
        html.light .text-white,
        html.light .text-slate-100 {
            color: #0f172a !important;
        }
        html.light .text-slate-300,
        html.light .text-slate-400 {
            color: #475569 !important;
        }
        html.dark body {
            background-color: #090d16;
            color: #f8fafc;
        }

        /* Limo Theme Helpers */
        .bg-limo-gradient {
            background: linear-gradient(135deg, #0284c7 0%, #0ea5e9 50%, #84cc16 100%);
        }
        .text-limo-gradient {
            background: linear-gradient(135deg, #0284c7 0%, #0ea5e9 50%, #84cc16 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }
        .animate-card {
            transition: all 0.25s cubic-bezier(0.16, 1, 0.3, 1);
        }
        .animate-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 12px 25px -5px rgba(2, 132, 199, 0.12), 0 8px 10px -6px rgba(132, 204, 22, 0.08);
        }
    </style>
</head>
<body class="<?= $isLight ? 'bg-slate-50 text-slate-900' : 'bg-slate-950 text-slate-100' ?> antialiased selection:bg-limoblue-500 selection:text-white min-h-screen flex flex-col">

    <!-- Top Floating Navigation Bar -->
    <header class="sticky top-0 z-50 backdrop-blur-md <?= $isLight ? 'bg-white/90 border-slate-200 shadow-sm' : 'bg-slate-950/80 border-slate-800' ?> border-b px-4 sm:px-8 py-3.5 transition-all">
        <div class="max-w-7xl mx-auto flex items-center justify-between">
            <!-- Brand Logo -->
            <a href="index.php" class="flex items-center gap-3 group">
                <div class="w-11 h-11 rounded-2xl bg-gradient-to-tr from-limoblue-600 via-sky-500 to-limogreen-500 flex items-center justify-center text-white font-black text-xl shadow-lg shadow-limoblue-500/25 group-hover:scale-105 transition-transform animate-pulse-glow">
                    <i data-lucide="smartphone" class="w-6 h-6"></i>
                </div>
                <div>
                    <h1 class="font-extrabold text-base sm:text-lg leading-tight tracking-tight <?= $isLight ? 'text-slate-900' : 'text-white' ?>">
                        <?= $shopName ?>
                    </h1>
                    <span class="text-xs text-limogreen-500 font-bold">
                        <?= $isUrdu ? 'موبائل شاپ و ایزی پیسہ پی او ایس' : 'LimoMobile POS & Khata Pro' ?>
                    </span>
                </div>
            </a>

            <!-- Center Navigation Links (Desktop) -->
            <nav class="hidden lg:flex items-center gap-6 text-sm font-bold">
                <a href="#features" class="<?= $isLight ? 'text-slate-600 hover:text-limoblue-600' : 'text-slate-300 hover:text-limoblue-400' ?> transition-colors">
                    <?= $isUrdu ? 'اہم خصوصیات' : 'Features' ?>
                </a>
                <a href="#mobile-purchase" class="<?= $isLight ? 'text-slate-600 hover:text-limoblue-600' : 'text-slate-300 hover:text-limoblue-400' ?> transition-colors">
                    <?= $isUrdu ? 'موبائل خرید اقرار نامہ' : 'Purchase Agreement' ?>
                </a>
                <a href="#easypaisa" class="<?= $isLight ? 'text-slate-600 hover:text-limoblue-600' : 'text-slate-300 hover:text-limoblue-400' ?> transition-colors">
                    <?= $isUrdu ? 'ایزی پیسہ لیجر' : 'EasyPaisa Ledger' ?>
                </a>
                <a href="#faq" class="<?= $isLight ? 'text-slate-600 hover:text-limoblue-600' : 'text-slate-300 hover:text-limoblue-400' ?> transition-colors">
                    <?= $isUrdu ? 'عام سوالات' : 'FAQ' ?>
                </a>
            </nav>

            <!-- Right Controls: Theme/Language Toggle & CTA Buttons -->
            <div class="flex items-center gap-2.5 sm:gap-3">
                <!-- Theme Toggle -->
                <a href="<?= $isLight ? $darkUri : $lightUri ?>" class="p-2 rounded-xl border <?= $isLight ? 'bg-slate-100 border-slate-200 text-slate-700' : 'bg-slate-900 border-slate-800 text-amber-400' ?> hover:scale-105 transition-all" title="<?= $isLight ? 'Switch to Dark Mode' : 'Switch to Light Mode' ?>">
                    <i data-lucide="<?= $isLight ? 'moon' : 'sun' ?>" class="w-4 h-4"></i>
                </a>

                <!-- Language Toggle -->
                <a href="<?= $isUrdu ? $enUri : $urUri ?>" class="px-2.5 py-1.5 rounded-xl border text-xs font-bold <?= $isLight ? 'bg-slate-100 border-slate-200 text-slate-700' : 'bg-slate-900 border-slate-800 text-slate-300' ?> hover:border-limoblue-500 transition-all flex items-center gap-1.5">
                    <span><?= $isUrdu ? '🇬🇧 EN' : '🇵🇰 اردو' ?></span>
                </a>

                <!-- Register Shop Button -->
                <a href="../register_shop/index.php" class="hidden sm:inline-flex items-center gap-2 px-3.5 py-2 rounded-xl bg-slate-800 hover:bg-slate-700 text-limogreen-400 font-bold text-xs border border-limogreen-500/30 shadow-sm transition-all hover:-translate-y-0.5">
                    <i data-lucide="building-2" class="w-4 h-4"></i>
                    <span><?= $isUrdu ? 'نئی دکان رجسٹر کریں' : 'Register Shop' ?></span>
                </a>

                <!-- Login / Launch POS CTA -->
                <a href="../login/index.php" class="inline-flex items-center gap-2 px-4 py-2 rounded-xl bg-gradient-to-r from-limoblue-600 to-limogreen-600 hover:from-limoblue-500 hover:to-limogreen-500 text-white font-extrabold text-xs sm:text-sm shadow-lg shadow-limoblue-600/30 hover:shadow-limoblue-600/50 transition-all hover:-translate-y-0.5">
                    <i data-lucide="log-in" class="w-4 h-4"></i>
                    <span><?= $isUrdu ? 'لاگ ان / سافٹ ویئر' : 'Login / Launch' ?></span>
                </a>
            </div>
        </div>
    </header>

    <!-- Hero Section -->
    <section class="relative pt-12 pb-20 px-4 sm:px-6 lg:px-8 overflow-hidden">
        <!-- Background Glow Accent with LimoBlue & LimoGreen -->
        <div class="absolute top-1/4 left-1/2 -translate-x-1/2 -translate-y-1/2 w-[650px] h-[350px] bg-gradient-to-r from-limoblue-500/15 to-limogreen-500/10 blur-[130px] pointer-events-none rounded-full"></div>

        <div class="max-w-6xl mx-auto text-center relative z-10 animate-slide-up">
            <!-- Pill Announcement -->
            <div class="inline-flex items-center gap-2 px-4 py-1.5 rounded-full border <?= $isLight ? 'bg-limoblue-50 border-limoblue-200 text-limoblue-800' : 'bg-limoblue-950/60 border-limoblue-800/60 text-limoblue-300' ?> text-xs font-bold mb-6 shadow-sm">
                <span class="w-2.5 h-2.5 rounded-full bg-limogreen-500 animate-pulse"></span>
                <span><?= $isUrdu ? 'موبائل شاپس اور ایزی پیسہ شاپس کا آل اِن ون مکمل نظام' : 'All-in-One Smart POS & Digital Ledger for Mobile Retailers' ?></span>
            </div>

            <!-- Main Headline -->
            <h1 class="text-3xl sm:text-5xl lg:text-6xl font-black tracking-tight leading-tight sm:leading-tight mb-6 <?= $isLight ? 'text-slate-900' : 'text-white' ?>">
                <?= $isUrdu ? 'موبائل فروخت، خریداری اقرار نامہ، ایزی پیسہ لیجر اور بارکوڈز کا' : 'Fast Thermal Billing, Second-Hand Mobile Agreements,' ?>
                <br class="hidden sm:inline" />
                <span class="text-limo-gradient font-black">
                    <?= $isUrdu ? 'جدید ترین ڈیجیٹل پی او ایس' : '& EasyPaisa Cash Register System' ?>
                </span>
            </h1>

            <!-- Subtitle -->
            <p class="max-w-3xl mx-auto text-base sm:text-xl <?= $isLight ? 'text-slate-600' : 'text-slate-300' ?> leading-relaxed mb-10">
                <?= $isUrdu ? 'دکان کی تمام سیلز، پرانا موبائل خریدتے وقت قانونی اسٹامپ پیپر رسید، گاہک شناختی کارڈ فوٹو والٹ، ایزی پیسہ کیش ان/آؤٹ اور کسٹمر ادھار کھاتہ ایک کلک پر مینج کریں۔' : 'Designed specifically for mobile shop owners with Urdu receipt printing, CNIC photo capture, legal purchase agreements, EasyPaisa ledger, and thermal barcodes.' ?>
            </p>

            <!-- Call to Actions -->
            <div class="flex flex-col sm:flex-row items-center justify-center gap-4 max-w-md mx-auto mb-14">
                <a href="../dashboard/index.php" class="w-full sm:w-auto px-8 py-4 rounded-2xl bg-gradient-to-r from-limoblue-600 to-limogreen-600 hover:from-limoblue-500 hover:to-limogreen-500 text-white font-black text-base shadow-xl shadow-limoblue-600/30 hover:scale-105 transition-all flex items-center justify-center gap-3">
                    <i data-lucide="layout-dashboard" class="w-5 h-5"></i>
                    <span><?= $isUrdu ? 'ڈیش بورڈ کھولیں' : 'Launch Dashboard' ?></span>
                </a>

                <a href="../register_shop/index.php" class="w-full sm:w-auto px-6 py-4 rounded-2xl <?= $isLight ? 'bg-white border-slate-300 hover:bg-slate-50 text-slate-800 hover:border-limoblue-400' : 'bg-slate-900 border-slate-700 hover:bg-slate-850 text-white hover:border-limoblue-500' ?> border font-extrabold text-base shadow-lg transition-all flex items-center justify-center gap-2.5">
                    <i data-lucide="building-2" class="w-5 h-5 text-limogreen-500"></i>
                    <span><?= $isUrdu ? 'نئی دکان رجسٹر کریں' : 'Register New Shop' ?></span>
                </a>
            </div>

            <!-- Highlight Metrics Ribbon -->
            <div class="grid grid-cols-2 md:grid-cols-4 gap-4 max-w-4xl mx-auto text-center">
                <div class="p-4 rounded-2xl border animate-card <?= $isLight ? 'bg-white border-slate-200 shadow-sm' : 'bg-slate-900/60 border-slate-800' ?>">
                    <div class="text-2xl sm:text-3xl font-black text-limoblue-500 mb-1">100%</div>
                    <div class="text-xs font-bold <?= $isLight ? 'text-slate-600' : 'text-slate-400' ?>">
                        <?= $isUrdu ? 'آف لائن اور کلاؤڈ سنک' : 'Offline & Cloud Sync' ?>
                    </div>
                </div>

                <div class="p-4 rounded-2xl border animate-card <?= $isLight ? 'bg-white border-slate-200 shadow-sm' : 'bg-slate-900/60 border-slate-800' ?>">
                    <div class="text-2xl sm:text-3xl font-black text-limogreen-500 mb-1">58 & 80mm</div>
                    <div class="text-xs font-bold <?= $isLight ? 'text-slate-600' : 'text-slate-400' ?>">
                        <?= $isUrdu ? 'فوری تھرمل رسید پرنٹنگ' : 'Thermal Bill Print' ?>
                    </div>
                </div>

                <div class="p-4 rounded-2xl border animate-card <?= $isLight ? 'bg-white border-slate-200 shadow-sm' : 'bg-slate-900/60 border-slate-800' ?>">
                    <div class="text-2xl sm:text-3xl font-black text-limoblue-500 mb-1">1-Click</div>
                    <div class="text-xs font-bold <?= $isLight ? 'text-slate-600' : 'text-slate-400' ?>">
                        <?= $isUrdu ? 'واٹس ایپ ادھار یاد دہانی' : 'WhatsApp Khata Alerts' ?>
                    </div>
                </div>

                <div class="p-4 rounded-2xl border animate-card <?= $isLight ? 'bg-white border-slate-200 shadow-sm' : 'bg-slate-900/60 border-slate-800' ?>">
                    <div class="text-2xl sm:text-3xl font-black text-limogreen-500 mb-1">0% Loss</div>
                    <div class="text-xs font-bold <?= $isLight ? 'text-slate-600' : 'text-slate-400' ?>">
                        <?= $isUrdu ? 'شناختی کارڈ و IMEI ریکارڈ' : 'CNIC & IMEI Vault' ?>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- ========================================================================= -->
    <!-- DYNAMIC CEO ANNOUNCEMENTS & LIVE UPDATES SECTION -->
    <!-- ========================================================================= -->
    <?php if (!empty($landingPosts)): ?>
    <section id="announcements" class="py-16 px-4 sm:px-6 lg:px-8 <?= $isLight ? 'bg-gradient-to-b from-white via-sky-50/40 to-slate-50 border-b border-slate-200' : 'bg-gradient-to-b from-slate-950 via-slate-900/60 to-slate-950 border-b border-slate-800' ?>">
        <div class="max-w-7xl mx-auto">
            <!-- Section Header -->
            <div class="text-center max-w-3xl mx-auto mb-12">
                <div class="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-gradient-to-r from-amber-500/15 via-limoblue-500/15 to-limogreen-500/15 border border-amber-500/30 text-amber-600 dark:text-amber-400 text-xs font-black mb-3">
                    <i data-lucide="sparkles" class="w-3.5 h-3.5 animate-pulse"></i>
                    <span><?= $isUrdu ? 'سی ای او ماسٹر اپ ڈیٹس و اعلانات' : 'Official CEO Announcements & Releases' ?></span>
                </div>
                <h2 class="text-2xl sm:text-4xl font-black tracking-tight <?= $isLight ? 'text-slate-900' : 'text-white' ?>">
                    <?= $isUrdu ? 'تازہ ترین خبریں، نئے فیچرز اور خصوصی آفرز' : 'Latest News, Feature Updates & Exclusive Offers' ?>
                </h2>
                <p class="text-sm sm:text-base mt-2 <?= $isLight ? 'text-slate-600' : 'text-slate-400' ?>">
                    <?= $isUrdu ? 'لیمو موبائل ٹیم کی جانب سے لائیو اپ ڈیٹس جو آپ کے کاروبار کو مزید تیز اور منافع بخش بنائیں' : 'Direct broadcast updates from LimoMobile CEO to keep your mobile retail shop ahead.' ?>
                </p>
            </div>

            <!-- Posts Grid -->
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                <?php foreach ($landingPosts as $post): ?>
                    <?php
                    $isPinned = !empty($post['is_pinned']);
                    $category = $post['category'] ?? 'ANNOUNCEMENT';
                    $badgeText = !empty($post['badge_text']) ? $post['badge_text'] : ($category === 'FEATURE' ? 'نیا فیچر' : ($category === 'OFFER' ? 'خصوصی آفر' : 'اہم اعلان'));
                    $imgUrl = !empty($post['image_url']) ? $post['image_url'] : '';
                    $actionUrl = !empty($post['action_url']) ? $post['action_url'] : '../register_shop/index.php';
                    $actionText = !empty($post['action_text']) ? $post['action_text'] : ($isUrdu ? 'مزید معلومات' : 'Learn More');
                    $dateStr = !empty($post['created_at']) ? date('d M Y', $post['created_at']) : date('d M Y');
                    ?>
                    <article class="rounded-3xl border transition-all duration-300 flex flex-col overflow-hidden group <?= $isPinned ? ($isLight ? 'bg-gradient-to-br from-white via-amber-50/20 to-white border-amber-400 shadow-xl shadow-amber-500/10' : 'bg-gradient-to-br from-slate-900 via-amber-950/20 to-slate-900 border-amber-500/50 shadow-xl shadow-amber-500/10') : ($isLight ? 'bg-white border-slate-200 hover:border-limoblue-400 hover:shadow-xl' : 'bg-slate-900 border-slate-800 hover:border-limoblue-500/50 hover:shadow-xl') ?>">
                        
                        <!-- Post Top Image / Media Showcase -->
                        <?php if (!empty($imgUrl)): ?>
                            <div class="relative w-full h-48 overflow-hidden bg-slate-900">
                                <img src="<?= htmlspecialchars($imgUrl) ?>" alt="<?= htmlspecialchars($post['title'] ?? '') ?>" class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-500" onerror="this.parentElement.style.display='none'" />
                                <div class="absolute inset-0 bg-gradient-to-t from-slate-950/70 via-transparent to-transparent"></div>
                                
                                <div class="absolute top-3 <?= $isUrdu ? 'right-3' : 'left-3' ?> flex items-center gap-1.5">
                                    <span class="px-3 py-1 rounded-full text-xs font-black backdrop-blur-md shadow-md bg-amber-500 text-slate-950">
                                        <?= htmlspecialchars($badgeText) ?>
                                    </span>
                                    <?php if ($isPinned): ?>
                                        <span class="p-1 rounded-full bg-rose-500 text-white text-xs shadow-md" title="Pinned Announcement">
                                            <i data-lucide="pin" class="w-3.5 h-3.5"></i>
                                        </span>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php else: ?>
                            <!-- Fallback Header Ribbon when no image -->
                            <div class="p-5 pb-0 flex items-center justify-between">
                                <span class="px-3 py-1 rounded-full text-xs font-black <?= $category === 'OFFER' ? 'bg-limogreen-500/20 text-limogreen-600 dark:text-limogreen-400 border border-limogreen-500/30' : ($category === 'FEATURE' ? 'bg-limoblue-500/20 text-limoblue-600 dark:text-limoblue-400 border border-limoblue-500/30' : 'bg-amber-500/20 text-amber-600 dark:text-amber-400 border border-amber-500/30') ?>">
                                    <?= htmlspecialchars($badgeText) ?>
                                </span>
                                <?php if ($isPinned): ?>
                                    <span class="inline-flex items-center gap-1 text-xs font-bold text-amber-500">
                                        <i data-lucide="pin" class="w-3.5 h-3.5"></i>
                                        <span><?= $isUrdu ? 'پن پوسٹ' : 'Pinned' ?></span>
                                    </span>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>

                        <!-- Post Body Content -->
                        <div class="p-6 flex-1 flex flex-col justify-between space-y-4">
                            <div>
                                <div class="flex items-center gap-2 text-xs text-slate-400 font-bold mb-2">
                                    <span class="flex items-center gap-1">
                                        <i data-lucide="calendar" class="w-3.5 h-3.5"></i>
                                        <?= $dateStr ?>
                                    </span>
                                    <span>•</span>
                                    <span class="flex items-center gap-1 text-amber-500">
                                        <i data-lucide="shield-check" class="w-3.5 h-3.5"></i>
                                        <?= htmlspecialchars($post['author_name'] ?? 'LimoMobile CEO') ?>
                                    </span>
                                </div>

                                <h3 class="text-lg sm:text-xl font-black leading-snug group-hover:text-limoblue-500 transition-colors <?= $isLight ? 'text-slate-900' : 'text-white' ?>">
                                    <?= htmlspecialchars($post['title'] ?? '') ?>
                                </h3>

                                <div class="text-sm mt-3 leading-relaxed <?= $isLight ? 'text-slate-600' : 'text-slate-300' ?>">
                                    <?= nl2br(htmlspecialchars($post['content'] ?? '')) ?>
                                </div>
                            </div>

                            <!-- Footer Action Button -->
                            <div class="pt-4 border-t border-slate-100 dark:border-slate-800 flex items-center justify-between gap-3">
                                <a href="<?= htmlspecialchars($actionUrl) ?>" class="inline-flex items-center gap-2 text-xs sm:text-sm font-black text-limoblue-600 dark:text-limoblue-400 hover:text-limoblue-500 group-hover:translate-x-1 rtl:group-hover:-translate-x-1 transition-all">
                                    <span><?= htmlspecialchars($actionText) ?></span>
                                    <i data-lucide="arrow-right" class="w-4 h-4 rtl:rotate-180"></i>
                                </a>

                                <div class="flex items-center gap-1 text-xs text-slate-400">
                                    <i data-lucide="heart" class="w-3.5 h-3.5 text-rose-500 fill-rose-500/20"></i>
                                    <span><?= intval($post['likes_count'] ?? 15) ?></span>
                                </div>
                            </div>
                        </div>
                    </article>
                <?php endforeach; ?>
            </div>
        </div>
    </section>
    <?php endif; ?>

    <!-- Live Feature Preview Cards (Visual Mockup Grid) -->
    <section id="features" class="py-16 px-4 sm:px-6 lg:px-8 <?= $isLight ? 'bg-slate-100/70 border-y border-slate-200' : 'bg-slate-900/40 border-y border-slate-800' ?>">
        <div class="max-w-7xl mx-auto">
            <!-- Section Header -->
            <div class="text-center max-w-3xl mx-auto mb-14">
                <span class="text-xs font-black uppercase tracking-wider text-limoblue-600 dark:text-limoblue-400 bg-limoblue-500/10 px-3 py-1 rounded-full border border-limoblue-500/20">
                    <?= $isUrdu ? 'طاقتور ماڈیولز' : 'Powerful POS Modules' ?>
                </span>
                <h2 class="text-2xl sm:text-4xl font-black tracking-tight mt-3 mb-4 <?= $isLight ? 'text-slate-900' : 'text-white' ?>">
                    <?= $isUrdu ? 'ایک سافٹ ویئر، موبائل کاروبار کی تمام ضروریات کا حل' : 'Everything Your Mobile & EasyPaisa Business Demands' ?>
                </h2>
                <p class="text-sm sm:text-base <?= $isLight ? 'text-slate-600' : 'text-slate-400' ?>">
                    <?= $isUrdu ? 'کوئی پیچیدہ سیٹنگز نہیں۔ آسان اردو اور انگریزی انٹرفیس، جو کسی بھی موبائل شاپ پر پہلے ہی دن سے فوری چلتا ہے۔' : 'Streamlined workflow tailored for mobile retailers, repair labs, EasyPaisa shops, and franchise agents.' ?>
                </p>
            </div>

            <!-- Features 6-Box Grid -->
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">

                <!-- Feature 1: POS & Fast Thermal Billing -->
                <div class="p-6 rounded-3xl border animate-card <?= $isLight ? 'bg-white border-slate-200 hover:shadow-lg' : 'bg-slate-900 border-slate-800 hover:border-limoblue-500/50' ?> group">
                    <div class="w-12 h-12 rounded-2xl bg-limoblue-500/10 text-limoblue-500 flex items-center justify-center mb-5 group-hover:scale-110 transition-transform">
                        <i data-lucide="shopping-cart" class="w-6 h-6"></i>
                    </div>
                    <h3 class="text-lg font-bold mb-2 <?= $isLight ? 'text-slate-900' : 'text-white' ?>">
                        <?= $isUrdu ? 'فاسٹ پی او ایس و تھرمل بلنگ' : 'Fast POS & Thermal Billing' ?>
                    </h3>
                    <p class="text-sm <?= $isLight ? 'text-slate-600' : 'text-slate-400' ?> leading-relaxed mb-4">
                        <?= $isUrdu ? 'بارکوڈ اسکینر سے سیکنڈوں میں پروڈکٹ شامل کریں، کسٹمر کو نقد، ادھار، یا ایزی پیسہ پر بل بنا کر 58mm/80mm تھرمل رسید فراہم کریں۔' : 'Quick product lookups, barcode scanning, instant discounts, multi-payment tenders, and crystal clear thermal receipts.' ?>
                    </p>
                    <a href="../pos/index.php" class="inline-flex items-center gap-1.5 text-xs font-bold text-limoblue-500 hover:text-limoblue-400">
                        <span><?= $isUrdu ? 'پی او ایس چیک کریں' : 'Explore POS' ?></span>
                        <i data-lucide="arrow-right" class="w-3.5 h-3.5"></i>
                    </a>
                </div>

                <!-- Feature 2: Second-hand Mobile Purchase Agreement -->
                <div class="p-6 rounded-3xl border animate-card <?= $isLight ? 'bg-white border-slate-200 hover:shadow-lg' : 'bg-slate-900 border-slate-800 hover:border-limogreen-500/50' ?> group" id="mobile-purchase">
                    <div class="w-12 h-12 rounded-2xl bg-limogreen-500/10 text-limogreen-500 flex items-center justify-center mb-5 group-hover:scale-110 transition-transform">
                        <i data-lucide="smartphone" class="w-6 h-6"></i>
                    </div>
                    <h3 class="text-lg font-bold mb-2 <?= $isLight ? 'text-slate-900' : 'text-white' ?>">
                        <?= $isUrdu ? 'موبائل خریداری و قانونی اقرار نامہ' : 'Mobile Purchase Register & Agreement' ?>
                    </h3>
                    <p class="text-sm <?= $isLight ? 'text-slate-600' : 'text-slate-400' ?> leading-relaxed mb-4">
                        <?= $isUrdu ? 'پرانا فون خریدتے وقت سیلر کا شناختی کارڈ، کیمرہ سے چہرہ تصویر، فون و باکس تصاویر محفوظ کریں اور قانونی اردو اقرار نامہ پرنٹ کریں۔' : 'Complete legal protection when buying used phones. Capture seller CNIC, photo, IMEI, condition, and print Urdu legal affidavits.' ?>
                    </p>
                    <a href="../purchase_register/index.php" class="inline-flex items-center gap-1.5 text-xs font-bold text-limogreen-500 hover:text-limogreen-400">
                        <span><?= $isUrdu ? 'خرید رجسٹر دیکھیں' : 'View Purchase Register' ?></span>
                        <i data-lucide="arrow-right" class="w-3.5 h-3.5"></i>
                    </a>
                </div>

                <!-- Feature 3: EasyPaisa & Cash Register -->
                <div class="p-6 rounded-3xl border animate-card <?= $isLight ? 'bg-white border-slate-200 hover:shadow-lg' : 'bg-slate-900 border-slate-800 hover:border-limoblue-500/50' ?> group" id="easypaisa">
                    <div class="w-12 h-12 rounded-2xl bg-limoblue-500/10 text-limoblue-500 flex items-center justify-center mb-5 group-hover:scale-110 transition-transform">
                        <i data-lucide="banknote" class="w-6 h-6"></i>
                    </div>
                    <h3 class="text-lg font-bold mb-2 <?= $isLight ? 'text-slate-900' : 'text-white' ?>">
                        <?= $isUrdu ? 'ایزی پیسہ و جازکیش کیش رجسٹر' : 'EasyPaisa & Cash Register' ?>
                    </h3>
                    <p class="text-sm <?= $isLight ? 'text-slate-600' : 'text-slate-400' ?> leading-relaxed mb-4">
                        <?= $isUrdu ? 'کیش ان، کیش آؤٹ، بل ادائیگیاں، اور دکان دراز کا روزانہ افتتاح و اختتام کیش آڈٹ۔ کٹوتی کمیشن اور حقیقی منافع کا فوری حساب۔' : 'Track Cash In, Cash Out, Utility Bills, fee profits, and daily drawer cash balance reconciliation with 100% accuracy.' ?>
                    </p>
                    <a href="../easypaisa_ledger/index.php" class="inline-flex items-center gap-1.5 text-xs font-bold text-limoblue-500 hover:text-limoblue-400">
                        <span><?= $isUrdu ? 'لیجر کھولیں' : 'Open EasyPaisa Ledger' ?></span>
                        <i data-lucide="arrow-right" class="w-3.5 h-3.5"></i>
                    </a>
                </div>

                <!-- Feature 4: Stock & Mobile IMEI Tracker -->
                <div class="p-6 rounded-3xl border animate-card <?= $isLight ? 'bg-white border-slate-200 hover:shadow-lg' : 'bg-slate-900 border-slate-800 hover:border-limogreen-500/50' ?> group">
                    <div class="w-12 h-12 rounded-2xl bg-limogreen-500/10 text-limogreen-500 flex items-center justify-center mb-5 group-hover:scale-110 transition-transform">
                        <i data-lucide="package" class="w-6 h-6"></i>
                    </div>
                    <h3 class="text-lg font-bold mb-2 <?= $isLight ? 'text-slate-900' : 'text-white' ?>">
                        <?= $isUrdu ? 'اسٹاک انوینٹری و موبائل IMEI ٹریکر' : 'Inventory & IMEI Tracker' ?>
                    </h3>
                    <p class="text-sm <?= $isLight ? 'text-slate-600' : 'text-slate-400' ?> leading-relaxed mb-4">
                        <?= $isUrdu ? 'موبائل فونز، چارجرز، ہینڈز فری، گلاس پروٹیکٹرز کا ریئل ٹائم اسٹاک، کم اسٹاک الرٹس، اور IMEI کے حساب سے مکمل ہسٹری۔' : 'Manage box packs, used sets, accessories, low stock warning triggers, and lookup exact mobile warranty history by IMEI.' ?>
                    </p>
                    <a href="../inventory/index.php" class="inline-flex items-center gap-1.5 text-xs font-bold text-limogreen-500 hover:text-limogreen-400">
                        <span><?= $isUrdu ? 'اسٹاک مینیجر' : 'Stock Manager' ?></span>
                        <i data-lucide="arrow-right" class="w-3.5 h-3.5"></i>
                    </a>
                </div>

                <!-- Feature 5: Customer Khata & WhatsApp Reminders -->
                <div class="p-6 rounded-3xl border animate-card <?= $isLight ? 'bg-white border-slate-200 hover:shadow-lg' : 'bg-slate-900 border-slate-800 hover:border-limoblue-500/50' ?> group">
                    <div class="w-12 h-12 rounded-2xl bg-limoblue-500/10 text-limoblue-500 flex items-center justify-center mb-5 group-hover:scale-110 transition-transform">
                        <i data-lucide="users" class="w-6 h-6"></i>
                    </div>
                    <h3 class="text-lg font-bold mb-2 <?= $isLight ? 'text-slate-900' : 'text-white' ?>">
                        <?= $isUrdu ? 'کسٹمر کھاتہ و واٹس ایپ ادھار یاد دہانی' : 'Customer Khata & WhatsApp Alerts' ?>
                    </h3>
                    <p class="text-sm <?= $isLight ? 'text-slate-600' : 'text-slate-400' ?> leading-relaxed mb-4">
                        <?= $isUrdu ? 'گاہکوں کا ادھار اور وصولیاں محفوظ کریں۔ ایک کلک پر گاہک کے واٹس ایپ پر اردو میں شائستہ بقایا یاد دہانی اور کھاتہ سلپ بھیجیں۔' : 'Credit sales management with instant WhatsApp balance reminders, payment receipts, and comprehensive PDF customer statements.' ?>
                    </p>
                    <a href="../customer_khata/index.php" class="inline-flex items-center gap-1.5 text-xs font-bold text-limoblue-500 hover:text-limoblue-400">
                        <span><?= $isUrdu ? 'کسٹمر کھاتہ کھولیں' : 'Customer Khata' ?></span>
                        <i data-lucide="arrow-right" class="w-3.5 h-3.5"></i>
                    </a>
                </div>

                <!-- Feature 6: Barcode Label Studio -->
                <div class="p-6 rounded-3xl border animate-card <?= $isLight ? 'bg-white border-slate-200 hover:shadow-lg' : 'bg-slate-900 border-slate-800 hover:border-limogreen-500/50' ?> group">
                    <div class="w-12 h-12 rounded-2xl bg-limogreen-500/10 text-limogreen-500 flex items-center justify-center mb-5 group-hover:scale-110 transition-transform">
                        <i data-lucide="scan-barcode" class="w-6 h-6"></i>
                    </div>
                    <h3 class="text-lg font-bold mb-2 <?= $isLight ? 'text-slate-900' : 'text-white' ?>">
                        <?= $isUrdu ? 'تھرمل بارکوڈ اسٹوڈیو و پرنٹنگ' : 'Barcode Studio & Thermal Labels' ?>
                    </h3>
                    <p class="text-sm <?= $isLight ? 'text-slate-600' : 'text-slate-400' ?> leading-relaxed mb-4">
                        <?= $isUrdu ? 'اسیسریز اور موبائلز کے لیے کسٹم بارکوڈ اسٹیکرز بنائیں جس میں دکان کا نام، پروڈکٹ، قیمت اور بارکوڈ کوڈ واضح پرنٹ ہو۔' : 'Design and print professional thermal price tags & barcodes for mobile covers, chargers, and box-packed devices.' ?>
                    </p>
                    <a href="../barcode_studio/index.php" class="inline-flex items-center gap-1.5 text-xs font-bold text-limogreen-500 hover:text-limogreen-400">
                        <span><?= $isUrdu ? 'بارکوڈ اسٹوڈیو' : 'Barcode Studio' ?></span>
                        <i data-lucide="arrow-right" class="w-3.5 h-3.5"></i>
                    </a>
                </div>

            </div>
        </div>
    </section>

    <!-- Why Choose Section: Comparison Table -->
    <section class="py-16 px-4 sm:px-6 lg:px-8">
        <div class="max-w-5xl mx-auto">
            <div class="text-center max-w-2xl mx-auto mb-12">
                <h2 class="text-2xl sm:text-3xl font-black mb-3 <?= $isLight ? 'text-slate-900' : 'text-white' ?>">
                    <?= $isUrdu ? 'عام روایتی سافٹ ویئر بمقابلہ لیمو موبائل پی او ایس' : 'Traditional Software vs. LimoMobile POS' ?>
                </h2>
                <p class="text-sm <?= $isLight ? 'text-slate-600' : 'text-slate-400' ?>">
                    <?= $isUrdu ? 'وہ تمام ضروری فیچرز جو کسی دوسرے سافٹ ویئر میں نہیں ملتیں' : 'Engineered specifically for the real-world operational challenges of mobile shop owners.' ?>
                </p>
            </div>

            <div class="overflow-x-auto rounded-3xl border <?= $isLight ? 'bg-white border-slate-200 shadow-sm' : 'bg-slate-900 border-slate-800' ?>">
                <table class="w-full text-left text-sm">
                    <thead>
                        <tr class="border-b <?= $isLight ? 'bg-slate-50 border-slate-200 text-slate-700' : 'bg-slate-850 border-slate-800 text-slate-300' ?>">
                            <th class="p-4 font-bold"><?= $isUrdu ? 'فیچر / سہولت' : 'Feature' ?></th>
                            <th class="p-4 font-bold text-limoblue-500 dark:text-limoblue-400 text-center">⭐ <?= $shopName ?></th>
                            <th class="p-4 font-bold text-slate-400 text-center"><?= $isUrdu ? 'عام پرانے سافٹ ویئر' : 'Other POS Software' ?></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y <?= $isLight ? 'divide-slate-200' : 'divide-slate-800' ?>">
                        <tr>
                            <td class="p-4 font-semibold <?= $isLight ? 'text-slate-900' : 'text-white' ?>">
                                <?= $isUrdu ? 'موبائل خریداری قانونی اردو اقرار نامہ و انگوٹھا' : 'Used Mobile Purchase Legal Agreement' ?>
                            </td>
                            <td class="p-4 text-center text-limogreen-500 font-black">✓ <?= $isUrdu ? 'مکمل شامل ہے' : 'Yes Included' ?></td>
                            <td class="p-4 text-center text-rose-500 font-bold">✗ <?= $isUrdu ? 'دستیاب نہیں' : 'Not Available' ?></td>
                        </tr>
                        <tr>
                            <td class="p-4 font-semibold <?= $isLight ? 'text-slate-900' : 'text-white' ?>">
                                <?= $isUrdu ? 'گاہک کا شناختی کارڈ اور چہرہ فوٹو والٹ' : 'CNIC & Face Photo Vault' ?>
                            </td>
                            <td class="p-4 text-center text-limogreen-500 font-black">✓ <?= $isUrdu ? 'شامل ہے' : 'Yes Included' ?></td>
                            <td class="p-4 text-center text-rose-500 font-bold">✗ <?= $isUrdu ? 'دستیاب نہیں' : 'No' ?></td>
                        </tr>
                        <tr>
                            <td class="p-4 font-semibold <?= $isLight ? 'text-slate-900' : 'text-white' ?>">
                                <?= $isUrdu ? 'ایزی پیسہ و جازکیش کیش ان / کیش آؤٹ لیجر' : 'EasyPaisa & JazzCash Integrated Ledger' ?>
                            </td>
                            <td class="p-4 text-center text-limogreen-500 font-black">✓ <?= $isUrdu ? 'شامل ہے' : 'Yes' ?></td>
                            <td class="p-4 text-center text-rose-500 font-bold">✗ <?= $isUrdu ? 'الگ کاپی پر لکھنا پڑتا ہے' : 'Manual Register' ?></td>
                        </tr>
                        <tr>
                            <td class="p-4 font-semibold <?= $isLight ? 'text-slate-900' : 'text-white' ?>">
                                <?= $isUrdu ? '1-کلک واٹس ایپ ادھار رسید' : '1-Click WhatsApp Khata Receipt' ?>
                            </td>
                            <td class="p-4 text-center text-limogreen-500 font-black">✓ <?= $isUrdu ? 'فوری سینڈ' : 'Instant 1-Click' ?></td>
                            <td class="p-4 text-center text-rose-500 font-bold">✗ <?= $isUrdu ? 'دستیاب نہیں' : 'No' ?></td>
                        </tr>
                        <tr>
                            <td class="p-4 font-semibold <?= $isLight ? 'text-slate-900' : 'text-white' ?>">
                                <?= $isUrdu ? 'موبائل IMEI و وارنٹی ٹریکر' : 'Mobile IMEI & Warranty History' ?>
                            </td>
                            <td class="p-4 text-center text-limogreen-500 font-black">✓ <?= $isUrdu ? 'مکمل ٹریکنگ' : 'Full IMEI Tracking' ?></td>
                            <td class="p-4 text-center text-slate-400 font-medium"><?= $isUrdu ? 'صرف سادہ مقدار' : 'Basic Count Only' ?></td>
                        </tr>
                        <tr>
                            <td class="p-4 font-semibold <?= $isLight ? 'text-slate-900' : 'text-white' ?>">
                                <?= $isUrdu ? 'بارکوڈ اسٹیکر پرنٹر اسٹوڈیو' : 'Thermal Barcode Sticker Studio' ?>
                            </td>
                            <td class="p-4 text-center text-limogreen-500 font-black">✓ <?= $isUrdu ? 'ان بلٹ اسٹوڈیو' : 'Built-in' ?></td>
                            <td class="p-4 text-center text-rose-500 font-bold">✗ <?= $isUrdu ? 'اضافی مہنگا سافٹ ویئر' : 'Expensive Add-on' ?></td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </section>

    <!-- FAQ Section -->
    <section id="faq" class="py-16 px-4 sm:px-6 lg:px-8 <?= $isLight ? 'bg-slate-100/70 border-t border-slate-200' : 'bg-slate-900/40 border-t border-slate-800' ?>">
        <div class="max-w-4xl mx-auto">
            <div class="text-center mb-12">
                <h2 class="text-2xl sm:text-3xl font-black mb-3 <?= $isLight ? 'text-slate-900' : 'text-white' ?>">
                    <?= $isUrdu ? 'اکثر پوچھے جانے والے سوالات (FAQ)' : 'Frequently Asked Questions' ?>
                </h2>
                <p class="text-sm <?= $isLight ? 'text-slate-600' : 'text-slate-400' ?>">
                    <?= $isUrdu ? 'اگر آپ کے پاس کوئی اور سوال ہو تو بلا جھجھک رابطہ کریں' : 'Quick answers to common questions about installation, printing, and security.' ?>
                </p>
            </div>

            <div class="space-y-4">
                <details class="p-5 rounded-2xl border transition-all hover:border-limoblue-300 dark:hover:border-limoblue-700 <?= $isLight ? 'bg-white border-slate-200' : 'bg-slate-900 border-slate-800' ?> group cursor-pointer">
                    <summary class="font-bold text-base <?= $isLight ? 'text-slate-900' : 'text-white' ?> flex items-center justify-between list-none">
                        <span><?= $isUrdu ? 'کیا یہ سافٹ ویئر انٹرنیٹ کے بغیر بھی کام کرتا ہے؟' : 'Does this software work without an active internet connection?' ?></span>
                        <i data-lucide="chevron-down" class="w-5 h-5 text-limoblue-500 transition-transform group-open:rotate-180"></i>
                    </summary>
                    <p class="mt-3 text-sm <?= $isLight ? 'text-slate-600' : 'text-slate-400' ?> leading-relaxed">
                        <?= $isUrdu ? 'جی ہاں! یہ مکمل PWA اور لوکل ڈیٹا بیس سپورٹ کے ساتھ بنایا گیا ہے۔ انٹرنیٹ نہ ہونے کی صورت میں بھی سیل، بارکوڈ پرنٹنگ اور لیجر آسانی سے چلتے ہیں اور انٹرنیٹ آنے پر خودکار بیک اپ ہو جاتا ہے۔' : 'Yes! It features offline storage architecture and automatic synchronization when an internet connection becomes available.' ?>
                    </p>
                </details>

                <details class="p-5 rounded-2xl border transition-all hover:border-limoblue-300 dark:hover:border-limoblue-700 <?= $isLight ? 'bg-white border-slate-200' : 'bg-slate-900 border-slate-800' ?> group cursor-pointer">
                    <summary class="font-bold text-base <?= $isLight ? 'text-slate-900' : 'text-white' ?> flex items-center justify-between list-none">
                        <span><?= $isUrdu ? 'تھرمل پرنٹر کے ساتھ رسید کیسے پرنٹ ہوگی؟' : 'How does thermal invoice printing work?' ?></span>
                        <i data-lucide="chevron-down" class="w-5 h-5 text-limogreen-500 transition-transform group-open:rotate-180"></i>
                    </summary>
                    <p class="mt-3 text-sm <?= $isLight ? 'text-slate-600' : 'text-slate-400' ?> leading-relaxed">
                        <?= $isUrdu ? 'سافٹ ویئر تمام 58mm اور 80mm تھرمل پرنٹرز (USB, Bluetooth, WiFi) کو سپورٹ کرتا ہے۔ سیل مکمل ہوتے ہی 1 سیکنڈ میں خودکار بل پرنٹ ہو جاتا ہے۔' : 'It supports all standard 58mm and 80mm ESC/POS thermal printers via USB, Bluetooth, and network drivers.' ?>
                    </p>
                </details>

                <details class="p-5 rounded-2xl border transition-all hover:border-limoblue-300 dark:hover:border-limoblue-700 <?= $isLight ? 'bg-white border-slate-200' : 'bg-slate-900 border-slate-800' ?> group cursor-pointer">
                    <summary class="font-bold text-base <?= $isLight ? 'text-slate-900' : 'text-white' ?> flex items-center justify-between list-none">
                        <span><?= $isUrdu ? 'کیا پرانے موبائل کی خریداری کا اقرار نامہ قانونی طور پر محفوظ ہے؟' : 'Is the mobile purchase agreement legally valid?' ?></span>
                        <i data-lucide="chevron-down" class="w-5 h-5 text-limoblue-500 transition-transform group-open:rotate-180"></i>
                    </summary>
                    <p class="mt-3 text-sm <?= $isLight ? 'text-slate-600' : 'text-slate-400' ?> leading-relaxed">
                        <?= $isUrdu ? 'جی ہاں! اقرار نامہ میں فروخت کنندہ کا شناختی کارڈ، مکمل رہائشی پتہ، موبائل کے دونوں IMEI، چوری شدہ نہ ہونے کا حلف نامہ اور دستخط/انگوٹھا کا کالم شامل ہوتا ہے جو قانونی تحفظ فراہم کرتا ہے۔' : 'Yes, the Urdu affidavit captures full seller identity, IMEI declaration, warranty checklist, and signature/thumbprint spaces.' ?>
                    </p>
                </details>

                <details class="p-5 rounded-2xl border transition-all hover:border-limoblue-300 dark:hover:border-limoblue-700 <?= $isLight ? 'bg-white border-slate-200' : 'bg-slate-900 border-slate-800' ?> group cursor-pointer">
                    <summary class="font-bold text-base <?= $isLight ? 'text-slate-900' : 'text-white' ?> flex items-center justify-between list-none">
                        <span><?= $isUrdu ? 'ہم نئی دکان کا نام اور سیٹنگز کیسے تبدیل کر سکتے ہیں؟' : 'How can we change shop name, owner name, and PIN?' ?></span>
                        <i data-lucide="chevron-down" class="w-5 h-5 text-limogreen-500 transition-transform group-open:rotate-180"></i>
                    </summary>
                    <p class="mt-3 text-sm <?= $isLight ? 'text-slate-600' : 'text-slate-400' ?> leading-relaxed">
                        <?= $isUrdu ? 'آپ "نئی دکان رجسٹر کریں" پر کلک کر کے فوری نئی شاپ کنفیگر کر سکتے ہیں یا سافٹ ویئر کے اندر "سیٹنگز" میں جا کر دکان کا نام، رابطہ فون اور پن کوڈ تبدیل کر سکتے ہیں۔' : 'Use the Register Shop button or go into Settings to update your business branding, contact numbers, and security PIN code.' ?>
                    </p>
                </details>
            </div>
        </div>
    </section>

    <!-- Bottom Call To Action Banner with Limo Colors -->
    <section class="py-16 px-4 sm:px-6 lg:px-8 relative overflow-hidden">
        <div class="max-w-5xl mx-auto rounded-3xl p-8 sm:p-12 text-center bg-gradient-to-r from-limoblue-800 via-limoblue-700 to-limogreen-800 text-white shadow-2xl relative shadow-limoblue-900/30">
            <h2 class="text-2xl sm:text-4xl font-black mb-4">
                <?= $isUrdu ? 'آج ہی اپنے موبائل کاروبار کو مکمل ڈیجیٹل اور محفوظ بنائیں' : 'Upgrade Your Mobile Business Management Today' ?>
            </h2>
            <p class="text-limoblue-100 max-w-2xl mx-auto mb-8 text-sm sm:text-base">
                <?= $isUrdu ? 'کسی بھی وقت، کہیں بھی، تیز ترین سیلز اور کسٹمر کھاتہ مینجمنٹ شروع کریں۔' : 'Ready to streamline your billing, khata, and second-hand phone purchases? Get started in seconds.' ?>
            </p>
            <div class="flex flex-col sm:flex-row items-center justify-center gap-4">
                <a href="../register_shop/index.php" class="w-full sm:w-auto px-8 py-3.5 rounded-2xl bg-white hover:bg-slate-100 text-limoblue-950 font-black text-sm shadow-lg transition-transform hover:scale-105 flex items-center justify-center gap-2">
                    <i data-lucide="building-2" class="w-4 h-4 text-limoblue-600"></i>
                    <span><?= $isUrdu ? 'نئی دکان رجسٹر کریں (مفت شروعات)' : 'Register New Shop' ?></span>
                </a>
                <a href="../login/index.php" class="w-full sm:w-auto px-6 py-3.5 rounded-2xl bg-limoblue-900/80 hover:bg-limoblue-900 border border-limoblue-400/30 text-white font-bold text-sm transition-all flex items-center justify-center gap-2">
                    <i data-lucide="log-in" class="w-4 h-4"></i>
                    <span><?= $isUrdu ? 'لاگ ان کریں (Login to POS)' : 'Login to POS' ?></span>
                </a>
            </div>
        </div>
    </section>

    <!-- Footer -->
    <footer class="mt-auto border-t <?= $isLight ? 'bg-white border-slate-200 text-slate-600' : 'bg-slate-950 border-slate-800 text-slate-400' ?> py-8 px-4 sm:px-8 text-xs">
        <div class="max-w-7xl mx-auto flex flex-col sm:flex-row items-center justify-between gap-4">
            <div class="flex items-center gap-2.5">
                <div class="w-7 h-7 rounded-xl bg-gradient-to-tr from-limoblue-600 to-limogreen-500 flex items-center justify-center text-white font-bold text-xs shadow-sm">
                    <i data-lucide="smartphone" class="w-4 h-4"></i>
                </div>
                <span class="font-extrabold <?= $isLight ? 'text-slate-900' : 'text-white' ?>"><?= $shopName ?></span>
                <span>— <?= $isUrdu ? 'تمام حقوق محفوظ ہیں © 2026' : 'All Rights Reserved © 2026' ?></span>
            </div>

            <div class="flex items-center gap-4 font-semibold flex-wrap">
                <a href="../dashboard/index.php" class="hover:text-limoblue-500 transition-colors"><?= $isUrdu ? 'ڈیش بورڈ' : 'Dashboard' ?></a>
                <a href="../login/index.php" class="hover:text-limoblue-500 transition-colors"><?= $isUrdu ? 'لاگ ان' : 'Login' ?></a>
                <a href="../register_shop/index.php" class="hover:text-limoblue-500 transition-colors"><?= $isUrdu ? 'نئی دکان' : 'Register Shop' ?></a>
                <a href="../pos/index.php" class="hover:text-limoblue-500 transition-colors"><?= $isUrdu ? 'پی او ایس' : 'POS' ?></a>
                <a href="../ceo/index.php" class="text-amber-500/80 hover:text-amber-400 flex items-center gap-1 transition-colors" title="CEO Headquarters Portal">
                    <i data-lucide="crown" class="w-3.5 h-3.5"></i>
                    <span><?= $isUrdu ? 'سی ای او پورٹل' : 'CEO Portal' ?></span>
                </a>
            </div>
        </div>
    </footer>

    <!-- Initialize Lucide Icons -->
    <script>
        lucide.createIcons();
    </script>
</body>
</html>
