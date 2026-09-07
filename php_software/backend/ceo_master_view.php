<?php
/**
 * LimoMobile CEO-Master Admin Control Suite
 * Enterprise Shop Management, Monthly/Yearly Subscription Fee Collections,
 * Landing Page Announcement Post Publisher & Global SaaS Telemetry.
 */

// Verify SuperAdmin / CEO Access
$isMasterCeo = isCeoAdmin();
$activeSubTab = $_GET['subtab'] ?? 'overview'; // 'overview', 'shops', 'fees', 'posts', 'accounts'

// Fetch SaaS Statistics
$allShops = [];
try {
    $stmt = $pdo->query("SELECT * FROM registered_shops ORDER BY created_at DESC");
    $allShops = $stmt->fetchAll();
} catch (Exception $e) {}

$allFeePayments = [];
try {
    $stmt = $pdo->query("SELECT * FROM shop_fee_payments ORDER BY payment_date DESC, created_at DESC");
    $allFeePayments = $stmt->fetchAll();
} catch (Exception $e) {}

$allLandingPosts = [];
try {
    $stmt = $pdo->query("SELECT * FROM landing_posts ORDER BY is_pinned DESC, created_at DESC");
    $allLandingPosts = $stmt->fetchAll();
} catch (Exception $e) {}

// Calculate KPI Metrics
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
?>

<!-- CEO Master Admin Dashboard Container -->
<div class="space-y-6" id="ceoMasterSuite">

    <!-- CEO Master Top Header Banner -->
    <div class="p-6 rounded-3xl bg-gradient-to-r from-slate-950 via-slate-900 to-slate-950 border-2 border-amber-500/40 text-white shadow-2xl relative overflow-hidden">
        <div class="absolute -right-12 -bottom-12 w-64 h-64 bg-amber-500/10 rounded-full blur-3xl pointer-events-none"></div>
        <div class="absolute -left-12 -top-12 w-64 h-64 bg-limoblue-500/10 rounded-full blur-3xl pointer-events-none"></div>
        
        <div class="relative flex flex-col lg:flex-row items-start lg:items-center justify-between gap-6">
            <div class="flex items-center gap-4">
                <div class="w-16 h-16 rounded-2xl bg-gradient-to-tr from-amber-500 via-yellow-400 to-limogreen-500 text-slate-950 flex items-center justify-center font-black shadow-xl shadow-amber-500/20 shrink-0">
                    <i data-lucide="crown" class="w-9 h-9"></i>
                </div>
                <div>
                    <div class="flex items-center gap-2.5 flex-wrap">
                        <h2 class="text-2xl sm:text-3xl font-black tracking-tight text-white">
                            <?= $isUrdu ? 'سی ای او ماسٹر کنٹرول پینل' : 'CEO Master Admin Control Suite' ?>
                        </h2>
                        <span class="px-3 py-1 rounded-full bg-amber-500/20 text-amber-300 border border-amber-500/40 text-xs font-mono font-black flex items-center gap-1.5">
                            <span class="w-2 h-2 rounded-full bg-amber-400 animate-pulse"></span>
                            HEADQUARTERS ACCESS
                        </span>
                    </div>
                    <p class="text-xs sm:text-sm text-slate-300 mt-1">
                        <?= $isUrdu ? 'تمام رجسٹرڈ موبائل دکانوں، ماہانہ و سالانہ فیسوں، رینیول الرٹس اور لینڈنگ پیج اعلانات کا مکمل کنٹرول' : 'Centralized multi-shop governance, monthly & yearly fee billing, automatic WhatsApp reminders, and landing page content publishing.' ?>
                    </p>
                </div>
            </div>

            <!-- Quick Action Buttons -->
            <div class="flex flex-wrap items-center gap-2.5">
                <button type="button" onclick="openCeoPostModal()" class="px-4 py-2.5 rounded-xl bg-gradient-to-r from-limoblue-600 to-sky-600 hover:from-limoblue-500 hover:to-sky-500 text-white font-bold text-xs sm:text-sm shadow-lg shadow-limoblue-600/30 flex items-center gap-2 cursor-pointer transition-all">
                    <i data-lucide="megaphone" class="w-4 h-4"></i>
                    <span><?= $isUrdu ? 'نیا پوسٹ / اعلان کریں' : 'New Landing Post' ?></span>
                </button>

                <button type="button" onclick="openCeoFeeModal()" class="px-4 py-2.5 rounded-xl bg-gradient-to-r from-amber-600 to-yellow-600 hover:from-amber-500 hover:to-yellow-500 text-white font-bold text-xs sm:text-sm shadow-lg shadow-amber-600/30 flex items-center gap-2 cursor-pointer transition-all">
                    <i data-lucide="banknote" class="w-4 h-4"></i>
                    <span><?= $isUrdu ? 'فیس وصولی درج کریں' : 'Record Fee Payment' ?></span>
                </button>

                <button type="button" onclick="openCeoShopModal()" class="px-4 py-2.5 rounded-xl bg-gradient-to-r from-limogreen-600 to-emerald-600 hover:from-limogreen-500 hover:to-emerald-500 text-white font-bold text-xs sm:text-sm shadow-lg shadow-limogreen-600/30 flex items-center gap-2 cursor-pointer transition-all">
                    <i data-lucide="plus-circle" class="w-4 h-4"></i>
                    <span><?= $isUrdu ? 'نئی دکان شامل کریں' : 'Add New Shop' ?></span>
                </button>
            </div>
        </div>
    </div>

    <!-- CEO Master Sub-Navigation Tabs -->
    <div class="flex items-center gap-2 p-1.5 rounded-2xl bg-slate-900/90 border border-slate-800 overflow-x-auto text-xs font-bold scrollbar-none">
        <button type="button" onclick="switchCeoTab('overview')" id="btn-ceo-overview" class="ceo-tab-btn px-4 py-2.5 rounded-xl flex items-center gap-2 transition-all cursor-pointer <?= $activeSubTab === 'overview' ? 'bg-amber-500 text-slate-950 font-black shadow-md' : 'text-slate-400 hover:text-white hover:bg-slate-800' ?>">
            <i data-lucide="bar-chart-2" class="w-4 h-4"></i>
            <span><?= $isUrdu ? 'ماسٹر اوور ویو' : 'CEO Overview' ?></span>
        </button>

        <button type="button" onclick="switchCeoTab('shops')" id="btn-ceo-shops" class="ceo-tab-btn px-4 py-2.5 rounded-xl flex items-center gap-2 transition-all cursor-pointer <?= $activeSubTab === 'shops' ? 'bg-amber-500 text-slate-950 font-black shadow-md' : 'text-slate-400 hover:text-white hover:bg-slate-800' ?>">
            <i data-lucide="building-2" class="w-4 h-4"></i>
            <span><?= $isUrdu ? 'تمام دکانیں (' . $totalShopsCount . ')' : 'All Shops (' . $totalShopsCount . ')' ?></span>
        </button>

        <button type="button" onclick="switchCeoTab('fees')" id="btn-ceo-fees" class="ceo-tab-btn px-4 py-2.5 rounded-xl flex items-center gap-2 transition-all cursor-pointer <?= $activeSubTab === 'fees' ? 'bg-amber-500 text-slate-950 font-black shadow-md' : 'text-slate-400 hover:text-white hover:bg-slate-800' ?>">
            <i data-lucide="wallet-cards" class="w-4 h-4"></i>
            <span><?= $isUrdu ? 'فیس و سبسکرپشن' : 'Fee & Subscriptions' ?></span>
        </button>

        <button type="button" onclick="switchCeoTab('posts')" id="btn-ceo-posts" class="ceo-tab-btn px-4 py-2.5 rounded-xl flex items-center gap-2 transition-all cursor-pointer <?= $activeSubTab === 'posts' ? 'bg-amber-500 text-slate-950 font-black shadow-md' : 'text-slate-400 hover:text-white hover:bg-slate-800' ?>">
            <i data-lucide="newspaper" class="w-4 h-4"></i>
            <span><?= $isUrdu ? 'لینڈنگ پیج پوسٹس (' . count($allLandingPosts) . ')' : 'Landing Page Posts (' . count($allLandingPosts) . ')' ?></span>
        </button>

        <button type="button" onclick="switchCeoTab('accounts')" id="btn-ceo-accounts" class="ceo-tab-btn px-4 py-2.5 rounded-xl flex items-center gap-2 transition-all cursor-pointer <?= $activeSubTab === 'accounts' ? 'bg-amber-500 text-slate-950 font-black shadow-md' : 'text-slate-400 hover:text-white hover:bg-slate-800' ?>">
            <i data-lucide="credit-card" class="w-4 h-4"></i>
            <span><?= $isUrdu ? 'بینک و ایزی پیسہ اکاؤنٹس' : 'Payment Collection Info' ?></span>
        </button>
    </div>

    <!-- 1. TAB: CEO OVERVIEW -->
    <div id="ceo-tab-overview" class="ceo-tab-pane space-y-6 <?= $activeSubTab === 'overview' ? '' : 'hidden' ?>">
        <!-- KPI Metrics Grid -->
        <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
            <!-- Metric 1: Total Revenue -->
            <div class="p-5 rounded-2xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 shadow-sm flex items-center gap-4">
                <div class="w-14 h-14 rounded-2xl bg-amber-500/10 text-amber-500 flex items-center justify-center shrink-0">
                    <i data-lucide="badge-dollar-sign" class="w-7 h-7"></i>
                </div>
                <div>
                    <span class="text-xs text-slate-400 font-bold block"><?= $isUrdu ? 'کل موصول فیس آمدنی' : 'Total SaaS Revenue' ?></span>
                    <span class="text-xl sm:text-2xl font-black text-amber-600 dark:text-amber-400">Rs. <?= number_format($totalCollectedRevenue) ?></span>
                    <span class="text-[11px] text-slate-500 block"><?= $isUrdu ? 'اس ماہ: Rs. ' . number_format($monthlyCollectedRevenue) : 'This Month: Rs. ' . number_format($monthlyCollectedRevenue) ?></span>
                </div>
            </div>

            <!-- Metric 2: Total Registered Shops -->
            <div class="p-5 rounded-2xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 shadow-sm flex items-center gap-4">
                <div class="w-14 h-14 rounded-2xl bg-limoblue-500/10 text-limoblue-500 flex items-center justify-center shrink-0">
                    <i data-lucide="building" class="w-7 h-7"></i>
                </div>
                <div>
                    <span class="text-xs text-slate-400 font-bold block"><?= $isUrdu ? 'کل رجسٹرڈ دکانیں' : 'Total Registered Shops' ?></span>
                    <span class="text-xl sm:text-2xl font-black text-slate-900 dark:text-white"><?= $totalShopsCount ?></span>
                    <span class="text-[11px] text-limogreen-500 font-bold block"><?= $activeShopsCount ?> <?= $isUrdu ? 'فعال سبسکرپشن' : 'Active Paid' ?></span>
                </div>
            </div>

            <!-- Metric 3: Due & Overdue Subscriptions -->
            <div class="p-5 rounded-2xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 shadow-sm flex items-center gap-4">
                <div class="w-14 h-14 rounded-2xl bg-rose-500/10 text-rose-500 flex items-center justify-center shrink-0">
                    <i data-lucide="alert-triangle" class="w-7 h-7"></i>
                </div>
                <div>
                    <span class="text-xs text-slate-400 font-bold block"><?= $isUrdu ? 'زیر التواء / اوور ڈیو فیس' : 'Due & Overdue Subscriptions' ?></span>
                    <div class="flex items-center gap-2">
                        <span class="text-xl sm:text-2xl font-black text-rose-600 dark:text-rose-400"><?= $dueShopsCount + $overdueShopsCount ?></span>
                        <span class="text-[10px] px-2 py-0.5 rounded-full bg-rose-500/20 text-rose-500 font-bold font-mono"><?= $overdueShopsCount ?> Overdue</span>
                    </div>
                    <span class="text-[11px] text-slate-500 block"><?= $trialShopsCount ?> <?= $isUrdu ? 'ٹرائل پر' : 'On Free Trial' ?></span>
                </div>
            </div>

            <!-- Metric 4: Landing Posts -->
            <div class="p-5 rounded-2xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 shadow-sm flex items-center gap-4">
                <div class="w-14 h-14 rounded-2xl bg-sky-500/10 text-sky-500 flex items-center justify-center shrink-0">
                    <i data-lucide="megaphone" class="w-7 h-7"></i>
                </div>
                <div>
                    <span class="text-xs text-slate-400 font-bold block"><?= $isUrdu ? 'لینڈنگ پیج اعلانات' : 'Landing Posts Live' ?></span>
                    <span class="text-xl sm:text-2xl font-black text-sky-600 dark:text-sky-400"><?= $publishedPostsCount ?></span>
                    <span class="text-[11px] text-slate-500 block"><?= count($allLandingPosts) ?> <?= $isUrdu ? 'کل پوسٹس' : 'Total Created' ?></span>
                </div>
            </div>
        </div>

        <!-- Two Column Overview: Overdue Alert Watchlist & Recent Payments -->
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
            <!-- Overdue & Due Fee Watchlist -->
            <div class="p-5 rounded-3xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 shadow-sm space-y-4">
                <div class="flex items-center justify-between">
                    <div class="flex items-center gap-2.5">
                        <div class="p-2 rounded-xl bg-rose-500/10 text-rose-500">
                            <i data-lucide="bell-ring" class="w-5 h-5"></i>
                        </div>
                        <div>
                            <h3 class="font-black text-slate-900 dark:text-white text-base">
                                <?= $isUrdu ? 'فیس واجب الادا دکانیں (Due / Overdue)' : 'Subscription Due Watchlist' ?>
                            </h3>
                            <span class="text-xs text-slate-400"><?= $isUrdu ? 'فوری واٹس ایپ ریمائنڈر اور آن لائن فیس وصولی' : 'Quick WhatsApp billing alerts & collection' ?></span>
                        </div>
                    </div>
                    <button type="button" onclick="switchCeoTab('fees')" class="text-xs font-bold text-limoblue-500 hover:underline">
                        <?= $isUrdu ? 'تمام دیکھیں →' : 'View All →' ?>
                    </button>
                </div>

                <div class="space-y-3">
                    <?php 
                    $dueList = array_filter($allShops, fn($s) => in_array($s['subscription_status'] ?? '', ['DUE', 'OVERDUE', 'TRIAL']));
                    if (empty($dueList)):
                    ?>
                        <div class="p-6 text-center text-slate-400 text-xs font-medium">
                            <i data-lucide="check-circle-2" class="w-8 h-8 mx-auto mb-2 text-limogreen-500"></i>
                            <?= $isUrdu ? 'شاندار! تمام دکانوں کی فیسیں کلیئر ہیں۔ کوئی واجب الادا نہیں۔' : 'All shops are active and paid up!' ?>
                        </div>
                    <?php else: ?>
                        <?php foreach (array_slice($dueList, 0, 4) as $s): 
                            $cleanPhone = preg_replace('/[^0-9]/', '', $s['phone'] ?? '');
                            if (str_starts_with($cleanPhone, '0')) $cleanPhone = '92' . substr($cleanPhone, 1);
                            $waMsg = urlencode("السلام علیکم محترم {$s['owner_name']} صاحب ({$s['shop_name']})،\nلیمو موبائل پی او ایس سافٹ ویئر کی فیس (Rs. " . number_format($s['subscription_fee'] ?? 2500) . ") کی تاریخ تجدید ({$s['next_due_date']}) ہے۔ برائے مہربانی اپنا اکاؤنٹ فعال رکھنے کے لیے فیس ایزی پیسہ/بینک پر ادا کریں۔ شکریہ! - LimoMobile HQ");
                        ?>
                            <div class="p-3.5 rounded-2xl bg-slate-50 dark:bg-slate-800/60 border border-slate-200 dark:border-slate-700/60 flex items-center justify-between gap-3">
                                <div>
                                    <div class="flex items-center gap-2">
                                        <h4 class="font-extrabold text-slate-900 dark:text-white text-xs sm:text-sm"><?= htmlspecialchars($s['shop_name']) ?></h4>
                                        <span class="px-2 py-0.5 rounded-full text-[10px] font-mono font-bold <?= ($s['subscription_status'] ?? '') === 'OVERDUE' ? 'bg-rose-500/20 text-rose-500' : 'bg-amber-500/20 text-amber-500' ?>">
                                            <?= $s['subscription_status'] ?? 'DUE' ?>
                                        </span>
                                    </div>
                                    <div class="text-[11px] text-slate-500 dark:text-slate-400 mt-0.5 flex items-center gap-2">
                                        <span>👤 <?= htmlspecialchars($s['owner_name']) ?></span>
                                        <span>•</span>
                                        <span>📅 تاریخ: <?= $s['next_due_date'] ?? 'N/A' ?></span>
                                        <span>•</span>
                                        <span class="font-bold text-amber-500">Rs. <?= number_format($s['subscription_fee'] ?? 2500) ?></span>
                                    </div>
                                </div>

                                <div class="flex items-center gap-1.5 shrink-0">
                                    <a href="https://wa.me/<?= $cleanPhone ?>?text=<?= $waMsg ?>" target="_blank" class="p-2 rounded-xl bg-emerald-500/10 hover:bg-emerald-500 text-emerald-500 hover:text-white transition-colors" title="WhatsApp Reminder">
                                        <i data-lucide="message-square" class="w-4 h-4"></i>
                                    </a>
                                    <button type="button" onclick="quickCollectFee('<?= $s['id'] ?>', '<?= htmlspecialchars($s['shop_name'], ENT_QUOTES) ?>', '<?= htmlspecialchars($s['owner_name'], ENT_QUOTES) ?>', '<?= $s['phone'] ?>', <?= $s['subscription_fee'] ?? 2500 ?>, '<?= $s['subscription_plan'] ?? 'MONTHLY' ?>')" class="px-3 py-1.5 rounded-xl bg-amber-500 hover:bg-amber-600 text-slate-950 font-extrabold text-xs transition-colors flex items-center gap-1">
                                        <i data-lucide="check" class="w-3.5 h-3.5"></i>
                                        <span><?= $isUrdu ? 'وصولی' : 'Collect' ?></span>
                                    </button>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Recent Fee Payment Activity -->
            <div class="p-5 rounded-3xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 shadow-sm space-y-4">
                <div class="flex items-center justify-between">
                    <div class="flex items-center gap-2.5">
                        <div class="p-2 rounded-xl bg-emerald-500/10 text-emerald-500">
                            <i data-lucide="receipt" class="w-5 h-5"></i>
                        </div>
                        <div>
                            <h3 class="font-black text-slate-900 dark:text-white text-base">
                                <?= $isUrdu ? 'حالیہ فیس وصولی ریکارڈز' : 'Recent Fee Collections' ?>
                            </h3>
                            <span class="text-xs text-slate-400"><?= $isUrdu ? 'موصول شدہ ادائیگیوں کی تصدیق' : 'Verified SaaS subscription transactions' ?></span>
                        </div>
                    </div>
                    <button type="button" onclick="switchCeoTab('fees')" class="text-xs font-bold text-limoblue-500 hover:underline">
                        <?= $isUrdu ? 'لیجر دیکھیں →' : 'View Ledger →' ?>
                    </button>
                </div>

                <div class="space-y-3">
                    <?php if (empty($allFeePayments)): ?>
                        <div class="p-6 text-center text-slate-400 text-xs font-medium">
                            <?= $isUrdu ? 'کوئی ادائیگی ریکارڈ موجود نہیں۔' : 'No payments recorded yet.' ?>
                        </div>
                    <?php else: ?>
                        <?php foreach (array_slice($allFeePayments, 0, 4) as $p): ?>
                            <div class="p-3.5 rounded-2xl bg-slate-50 dark:bg-slate-800/60 border border-slate-200 dark:border-slate-700/60 flex items-center justify-between gap-3">
                                <div>
                                    <div class="flex items-center gap-2">
                                        <h4 class="font-extrabold text-slate-900 dark:text-white text-xs sm:text-sm"><?= htmlspecialchars($p['shop_name']) ?></h4>
                                        <span class="px-2 py-0.5 rounded-full text-[10px] font-mono font-bold bg-emerald-500/20 text-emerald-500">
                                            <?= $p['payment_method'] ?? 'EASYPAISA' ?>
                                        </span>
                                    </div>
                                    <div class="text-[11px] text-slate-500 dark:text-slate-400 mt-0.5 flex items-center gap-2">
                                        <span>👤 <?= htmlspecialchars($p['owner_name']) ?></span>
                                        <span>•</span>
                                        <span>📅 <?= $p['payment_date'] ?? '' ?></span>
                                        <span>•</span>
                                        <span>Ref: <?= htmlspecialchars($p['trx_id'] ?? 'TRX-N/A') ?></span>
                                    </div>
                                </div>

                                <div class="text-right">
                                    <span class="text-sm font-black text-emerald-600 dark:text-emerald-400 block">+ Rs. <?= number_format($p['amount']) ?></span>
                                    <span class="text-[10px] text-slate-400 font-mono"><?= $p['plan_type'] ?? 'MONTHLY' ?></span>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- 2. TAB: ALL SHOPS MANAGEMENT -->
    <div id="ceo-tab-shops" class="ceo-tab-pane space-y-4 <?= $activeSubTab === 'shops' ? '' : 'hidden' ?>">
        <div class="p-4 rounded-2xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 flex flex-col md:flex-row items-center justify-between gap-3">
            <div class="relative w-full md:w-80">
                <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none text-slate-400">
                    <i data-lucide="search" class="w-4 h-4"></i>
                </div>
                <input type="text" id="shopSearchInput" onkeyup="filterShopTable()" placeholder="<?= $isUrdu ? 'دکان، مالک، فون یا شہر تلاش کریں...' : 'Search shop, owner, phone or city...' ?>" class="w-full pl-9 pr-3 py-2 rounded-xl text-xs bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 text-slate-900 dark:text-white focus:outline-none focus:border-amber-500">
            </div>

            <div class="flex items-center gap-2 w-full md:w-auto justify-end">
                <select id="shopStatusFilter" onchange="filterShopTable()" class="px-3 py-2 rounded-xl text-xs bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 text-slate-900 dark:text-white font-bold focus:outline-none">
                    <option value="ALL"><?= $isUrdu ? 'تمام اسٹیٹس (All)' : 'All Status' ?></option>
                    <option value="ACTIVE"><?= $isUrdu ? 'فعال (ACTIVE)' : 'Active' ?></option>
                    <option value="DUE"><?= $isUrdu ? 'واجب الادا (DUE)' : 'Due Soon' ?></option>
                    <option value="OVERDUE"><?= $isUrdu ? 'اوور ڈیو (OVERDUE)' : 'Overdue' ?></option>
                    <option value="TRIAL"><?= $isUrdu ? 'ٹرائل (TRIAL)' : 'Trial' ?></option>
                </select>

                <button type="button" onclick="openCeoShopModal()" class="px-4 py-2 rounded-xl bg-amber-500 hover:bg-amber-600 text-slate-950 font-black text-xs flex items-center gap-1.5 cursor-pointer shadow-md transition-all">
                    <i data-lucide="plus" class="w-4 h-4"></i>
                    <span><?= $isUrdu ? 'نئی دکان رجسٹر کریں' : 'Add Shop' ?></span>
                </button>
            </div>
        </div>

        <!-- Shops Data Table -->
        <div class="rounded-2xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 overflow-hidden shadow-sm">
            <div class="overflow-x-auto">
                <table class="w-full text-left text-xs text-slate-600 dark:text-slate-300" id="shopsTable">
                    <thead class="bg-slate-50 dark:bg-slate-800/80 text-slate-500 dark:text-slate-400 font-extrabold uppercase text-[10px] tracking-wider border-b border-slate-200 dark:border-slate-700">
                        <tr>
                            <th class="py-3.5 px-4"><?= $isUrdu ? 'دکان و برانچ' : 'Shop & Branch' ?></th>
                            <th class="py-3.5 px-4"><?= $isUrdu ? 'مالک و رابطہ' : 'Owner & Contact' ?></th>
                            <th class="py-3.5 px-4"><?= $isUrdu ? 'پلان و فیس' : 'Plan & Fee' ?></th>
                            <th class="py-3.5 px-4"><?= $isUrdu ? 'اسٹیٹس' : 'Status' ?></th>
                            <th class="py-3.5 px-4"><?= $isUrdu ? 'تجدید تاریخ' : 'Next Due' ?></th>
                            <th class="py-3.5 px-4"><?= $isUrdu ? 'لائسنس کی' : 'License Key' ?></th>
                            <th class="py-3.5 px-4 text-right"><?= $isUrdu ? 'ایکشنز' : 'Actions' ?></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 dark:divide-slate-800 font-medium">
                        <?php if (empty($allShops)): ?>
                            <tr>
                                <td colspan="7" class="py-8 text-center text-slate-400">
                                    <?= $isUrdu ? 'کوئی دکان رجسٹرڈ نہیں ہے۔' : 'No registered shops found.' ?>
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($allShops as $s): 
                                $cleanPhone = preg_replace('/[^0-9]/', '', $s['phone'] ?? '');
                                if (str_starts_with($cleanPhone, '0')) $cleanPhone = '92' . substr($cleanPhone, 1);
                                $waMsg = urlencode("السلام علیکم محترم {$s['owner_name']} صاحب ({$s['shop_name']})، لیمو موبائل پی او ایس سافٹ ویئر کی طرف سے رابطہ۔ - LimoMobile HQ");
                            ?>
                                <tr class="hover:bg-slate-50/80 dark:hover:bg-slate-800/40 transition-colors shop-row" data-status="<?= $s['subscription_status'] ?? 'ACTIVE' ?>">
                                    <td class="py-3 px-4">
                                        <div class="font-extrabold text-slate-900 dark:text-white text-xs sm:text-sm"><?= htmlspecialchars($s['shop_name']) ?></div>
                                        <div class="text-[11px] text-slate-400 flex items-center gap-1 mt-0.5">
                                            <i data-lucide="map-pin" class="w-3 h-3 text-slate-400"></i>
                                            <span><?= htmlspecialchars($s['city'] ?? 'پاکستان') ?></span>
                                            <span class="text-slate-500">•</span>
                                            <span><?= htmlspecialchars($s['thermal_size'] ?? '80mm') ?></span>
                                        </div>
                                    </td>
                                    <td class="py-3 px-4">
                                        <div class="font-bold text-slate-800 dark:text-slate-200"><?= htmlspecialchars($s['owner_name']) ?></div>
                                        <div class="text-[11px] text-slate-400 flex items-center gap-1 mt-0.5">
                                            <i data-lucide="phone" class="w-3 h-3 text-limogreen-500"></i>
                                            <span class="font-mono"><?= htmlspecialchars($s['phone']) ?></span>
                                        </div>
                                    </td>
                                    <td class="py-3 px-4">
                                        <span class="px-2 py-0.5 rounded font-mono text-[10px] font-bold <?= ($s['subscription_plan'] ?? '') === 'YEARLY' ? 'bg-purple-500/20 text-purple-400 border border-purple-500/30' : 'bg-limoblue-500/20 text-limoblue-400 border border-limoblue-500/30' ?>">
                                            <?= $s['subscription_plan'] ?? 'MONTHLY' ?>
                                        </span>
                                        <div class="text-xs font-black text-amber-500 mt-1">
                                            Rs. <?= number_format($s['subscription_fee'] ?? 2500) ?>
                                        </div>
                                    </td>
                                    <td class="py-3 px-4">
                                        <?php 
                                        $st = $s['subscription_status'] ?? 'ACTIVE';
                                        $stColor = match($st) {
                                            'ACTIVE' => 'bg-emerald-500/20 text-emerald-500 border-emerald-500/30',
                                            'DUE' => 'bg-amber-500/20 text-amber-500 border-amber-500/30',
                                            'OVERDUE' => 'bg-rose-500/20 text-rose-500 border-rose-500/30',
                                            'TRIAL' => 'bg-sky-500/20 text-sky-500 border-sky-500/30',
                                            default => 'bg-slate-500/20 text-slate-400 border-slate-500/30'
                                        };
                                        ?>
                                        <span class="px-2.5 py-1 rounded-full text-[10px] font-mono font-black border <?= $stColor ?>">
                                            <?= $st ?>
                                        </span>
                                    </td>
                                    <td class="py-3 px-4 font-mono text-[11px] text-slate-600 dark:text-slate-300">
                                        <?= $s['next_due_date'] ?? 'N/A' ?>
                                    </td>
                                    <td class="py-3 px-4 font-mono text-[11px] text-slate-400">
                                        <span class="bg-slate-100 dark:bg-slate-800 px-2 py-0.5 rounded border border-slate-200 dark:border-slate-700">
                                            <?= htmlspecialchars($s['license_key'] ?? 'LIMO-PK-' . rand(1000, 9999)) ?>
                                        </span>
                                    </td>
                                    <td class="py-3 px-4 text-right">
                                        <div class="flex items-center justify-end gap-1">
                                            <a href="https://wa.me/<?= $cleanPhone ?>?text=<?= $waMsg ?>" target="_blank" class="p-1.5 rounded-lg bg-emerald-500/10 hover:bg-emerald-500 text-emerald-500 hover:text-white transition-colors" title="WhatsApp Shop Owner">
                                                <i data-lucide="message-circle" class="w-4 h-4"></i>
                                            </a>
                                            <button type="button" onclick="quickCollectFee('<?= $s['id'] ?>', '<?= htmlspecialchars($s['shop_name'], ENT_QUOTES) ?>', '<?= htmlspecialchars($s['owner_name'], ENT_QUOTES) ?>', '<?= $s['phone'] ?>', <?= $s['subscription_fee'] ?? 2500 ?>, '<?= $s['subscription_plan'] ?? 'MONTHLY' ?>')" class="p-1.5 rounded-lg bg-amber-500/10 hover:bg-amber-500 text-amber-500 hover:text-slate-950 transition-colors" title="<?= $isUrdu ? 'فیس وصولی درج کریں' : 'Collect Fee' ?>">
                                                <i data-lucide="wallet" class="w-4 h-4"></i>
                                            </button>
                                            <button type="button" onclick="editCeoShop(<?= htmlspecialchars(json_encode($s), ENT_QUOTES) ?>)" class="p-1.5 rounded-lg bg-limoblue-500/10 hover:bg-limoblue-500 text-limoblue-500 hover:text-white transition-colors" title="<?= $isUrdu ? 'ترمیم کریں' : 'Edit Shop' ?>">
                                                <i data-lucide="edit-3" class="w-4 h-4"></i>
                                            </button>
                                            <form method="POST" class="inline" onsubmit="return confirm('<?= $isUrdu ? 'کیا آپ واقعی اس دکان کو حذف کرنا چاہتے ہیں؟' : 'Are you sure you want to delete this shop record?' ?>')">
                                                <input type="hidden" name="action" value="ceo_delete_shop">
                                                <input type="hidden" name="shop_id" value="<?= $s['id'] ?>">
                                                <button type="submit" class="p-1.5 rounded-lg bg-rose-500/10 hover:bg-rose-500 text-rose-500 hover:text-white transition-colors" title="<?= $isUrdu ? 'حذف کریں' : 'Delete' ?>">
                                                    <i data-lucide="trash-2" class="w-4 h-4"></i>
                                                </button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- 3. TAB: FEE & SUBSCRIPTIONS LEDGER -->
    <div id="ceo-tab-fees" class="ceo-tab-pane space-y-4 <?= $activeSubTab === 'fees' ? '' : 'hidden' ?>">
        <div class="p-4 rounded-2xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 flex flex-col md:flex-row items-center justify-between gap-3">
            <div class="flex items-center gap-2">
                <span class="text-xs font-bold text-slate-400"><?= $isUrdu ? 'کل موصول شدہ فیس:' : 'Total Collected:' ?></span>
                <span class="text-base font-black text-amber-500">Rs. <?= number_format($totalCollectedRevenue) ?></span>
                <span class="text-slate-500">•</span>
                <span class="text-xs text-slate-400"><?= count($allFeePayments) ?> <?= $isUrdu ? 'رسیدیں' : 'Receipts' ?></span>
            </div>

            <div class="flex items-center gap-2">
                <button type="button" onclick="openCeoFeeModal()" class="px-4 py-2 rounded-xl bg-amber-500 hover:bg-amber-600 text-slate-950 font-black text-xs flex items-center gap-1.5 cursor-pointer shadow-md transition-all">
                    <i data-lucide="plus" class="w-4 h-4"></i>
                    <span><?= $isUrdu ? 'نئی فیس وصولی ریکارڈ کریں' : 'Record Payment' ?></span>
                </button>
            </div>
        </div>

        <!-- Payments Ledger Table -->
        <div class="rounded-2xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 overflow-hidden shadow-sm">
            <div class="overflow-x-auto">
                <table class="w-full text-left text-xs text-slate-600 dark:text-slate-300">
                    <thead class="bg-slate-50 dark:bg-slate-800/80 text-slate-500 dark:text-slate-400 font-extrabold uppercase text-[10px] tracking-wider border-b border-slate-200 dark:border-slate-700">
                        <tr>
                            <th class="py-3.5 px-4"><?= $isUrdu ? 'رسید نمبر و تاریخ' : 'Receipt & Date' ?></th>
                            <th class="py-3.5 px-4"><?= $isUrdu ? 'دکان و مالک' : 'Shop & Owner' ?></th>
                            <th class="py-3.5 px-4"><?= $isUrdu ? 'بلنگ دورانیہ' : 'Billing Cycle' ?></th>
                            <th class="py-3.5 px-4"><?= $isUrdu ? 'طریقہ ادائیگی' : 'Payment Method' ?></th>
                            <th class="py-3.5 px-4"><?= $isUrdu ? 'رقم (PKR)' : 'Amount' ?></th>
                            <th class="py-3.5 px-4 text-right"><?= $isUrdu ? 'ایکشنز' : 'Actions' ?></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 dark:divide-slate-800 font-medium">
                        <?php if (empty($allFeePayments)): ?>
                            <tr>
                                <td colspan="6" class="py-8 text-center text-slate-400">
                                    <?= $isUrdu ? 'کوئی ادائیگی ریکارڈ موجود نہیں ہے۔' : 'No payment records found.' ?>
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($allFeePayments as $p): ?>
                                <tr class="hover:bg-slate-50/80 dark:hover:bg-slate-800/40 transition-colors">
                                    <td class="py-3 px-4">
                                        <span class="font-mono font-bold text-amber-500"><?= htmlspecialchars($p['receipt_no'] ?? 'REC-LIMO') ?></span>
                                        <div class="text-[11px] text-slate-400 mt-0.5">📅 <?= $p['payment_date'] ?? '' ?></div>
                                    </td>
                                    <td class="py-3 px-4">
                                        <div class="font-bold text-slate-900 dark:text-white"><?= htmlspecialchars($p['shop_name']) ?></div>
                                        <div class="text-[11px] text-slate-400">👤 <?= htmlspecialchars($p['owner_name']) ?> (<?= htmlspecialchars($p['phone'] ?? '') ?>)</div>
                                    </td>
                                    <td class="py-3 px-4">
                                        <span class="px-2 py-0.5 rounded font-mono text-[10px] font-bold bg-slate-100 dark:bg-slate-800 text-slate-700 dark:text-slate-300">
                                            <?= htmlspecialchars($p['billing_month_year'] ?? 'Monthly') ?>
                                        </span>
                                        <div class="text-[10px] text-slate-400 mt-0.5 font-mono"><?= $p['period_start'] ?? '' ?> → <?= $p['period_end'] ?? '' ?></div>
                                    </td>
                                    <td class="py-3 px-4">
                                        <span class="px-2 py-0.5 rounded font-mono text-[10px] font-bold bg-emerald-500/20 text-emerald-400">
                                            <?= $p['payment_method'] ?? 'EASYPAISA' ?>
                                        </span>
                                        <div class="text-[10px] text-slate-400 mt-0.5 font-mono">Trx: <?= htmlspecialchars($p['trx_id'] ?? 'N/A') ?></div>
                                    </td>
                                    <td class="py-3 px-4">
                                        <span class="text-sm font-black text-emerald-600 dark:text-emerald-400">Rs. <?= number_format($p['amount']) ?></span>
                                    </td>
                                    <td class="py-3 px-4 text-right">
                                        <div class="flex items-center justify-end gap-1">
                                            <button type="button" onclick="printFeeReceipt(<?= htmlspecialchars(json_encode($p), ENT_QUOTES) ?>)" class="p-1.5 rounded-lg bg-slate-100 dark:bg-slate-800 hover:bg-slate-200 text-slate-600 dark:text-slate-300 transition-colors" title="<?= $isUrdu ? 'رسید پرنٹ کریں' : 'Print Receipt' ?>">
                                                <i data-lucide="printer" class="w-4 h-4"></i>
                                            </button>
                                            <form method="POST" class="inline" onsubmit="return confirm('<?= $isUrdu ? 'کیا آپ اس فیس ریکارڈ کو حذف کرنا چاہتے ہیں؟' : 'Delete this payment record?' ?>')">
                                                <input type="hidden" name="action" value="ceo_delete_fee_payment">
                                                <input type="hidden" name="payment_id" value="<?= $p['id'] ?>">
                                                <button type="submit" class="p-1.5 rounded-lg bg-rose-500/10 hover:bg-rose-500 text-rose-500 hover:text-white transition-colors" title="<?= $isUrdu ? 'حذف کریں' : 'Delete' ?>">
                                                    <i data-lucide="trash-2" class="w-4 h-4"></i>
                                                </button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- 4. TAB: LANDING PAGE POSTS MANAGER -->
    <div id="ceo-tab-posts" class="ceo-tab-pane space-y-4 <?= $activeSubTab === 'posts' ? '' : 'hidden' ?>">
        <div class="p-4 rounded-2xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 flex flex-col md:flex-row items-center justify-between gap-3">
            <div>
                <h3 class="font-black text-slate-900 dark:text-white text-base">
                    <?= $isUrdu ? 'لینڈنگ پیج اعلانات و پروموشنل پوسٹس' : 'Landing Page Announcements & Spotlight Posts' ?>
                </h3>
                <span class="text-xs text-slate-400"><?= $isUrdu ? 'یہاں سے آپ لینڈنگ پیج پر نئی اپ ڈیٹس، آفرز اور اعلانات لائیو پوسٹ کر سکتے ہیں' : 'Publish announcements, feature releases, and promotional offers live to the public landing page.' ?></span>
            </div>

            <button type="button" onclick="openCeoPostModal()" class="px-4 py-2 rounded-xl bg-limoblue-600 hover:bg-limoblue-500 text-white font-bold text-xs flex items-center gap-1.5 cursor-pointer shadow-md transition-all">
                <i data-lucide="plus" class="w-4 h-4"></i>
                <span><?= $isUrdu ? 'نیا پوسٹ شائع کریں' : 'Create New Post' ?></span>
            </button>
        </div>

        <!-- Live Posts Grid -->
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
            <?php if (empty($allLandingPosts)): ?>
                <div class="col-span-full p-8 text-center text-slate-400 bg-white dark:bg-slate-900 rounded-2xl border border-slate-200 dark:border-slate-800">
                    <i data-lucide="newspaper" class="w-8 h-8 mx-auto mb-2 text-slate-400"></i>
                    <?= $isUrdu ? 'کوئی پوسٹ شائع نہیں کی گئی۔ اوپر بٹن سے نئی پوسٹ شامل کریں۔' : 'No posts published yet. Create one using the button above!' ?>
                </div>
            <?php else: ?>
                <?php foreach ($allLandingPosts as $post): ?>
                    <div class="rounded-3xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 shadow-sm overflow-hidden flex flex-col justify-between group hover:border-amber-500/50 transition-all">
                        <?php if (!empty($post['image_url'])): ?>
                            <div class="h-44 w-full overflow-hidden relative bg-slate-800">
                                <img src="<?= htmlspecialchars($post['image_url']) ?>" alt="Post Banner" class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-500" referrerpolicy="no-referrer">
                                <div class="absolute top-3 right-3 flex items-center gap-1.5">
                                    <?php if (!empty($post['badge_text'])): ?>
                                        <span class="px-2.5 py-1 rounded-full bg-slate-950/80 backdrop-blur-md text-amber-400 border border-amber-400/30 text-[10px] font-black">
                                            <?= htmlspecialchars($post['badge_text']) ?>
                                        </span>
                                    <?php endif; ?>
                                    <?php if ($post['is_pinned']): ?>
                                        <span class="p-1 rounded-full bg-amber-500 text-slate-950" title="Pinned Post">
                                            <i data-lucide="pin" class="w-3 h-3"></i>
                                        </span>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endif; ?>

                        <div class="p-5 flex-1 flex flex-col justify-between space-y-3">
                            <div>
                                <div class="flex items-center justify-between gap-2 mb-2">
                                    <span class="px-2 py-0.5 rounded text-[10px] font-mono font-bold bg-limoblue-500/20 text-limoblue-400">
                                        <?= htmlspecialchars($post['category'] ?? 'ANNOUNCEMENT') ?>
                                    </span>
                                    <span class="text-[10px] text-slate-400">
                                        📅 <?= date('d M Y', $post['created_at'] ?? time()) ?>
                                    </span>
                                </div>
                                <h4 class="font-black text-slate-900 dark:text-white text-base leading-snug"><?= htmlspecialchars($post['title']) ?></h4>
                                <p class="text-xs text-slate-500 dark:text-slate-400 mt-2 line-clamp-3 leading-relaxed"><?= htmlspecialchars($post['content']) ?></p>
                            </div>

                            <div class="pt-3 border-t border-slate-100 dark:border-slate-800 flex items-center justify-between">
                                <div class="text-[11px] text-slate-400 font-medium">
                                    ✍️ <?= htmlspecialchars($post['author_name'] ?? 'LimoMobile CEO') ?>
                                </div>

                                <div class="flex items-center gap-1">
                                    <button type="button" onclick="editCeoPost(<?= htmlspecialchars(json_encode($post), ENT_QUOTES) ?>)" class="p-1.5 rounded-lg bg-limoblue-500/10 hover:bg-limoblue-500 text-limoblue-500 hover:text-white transition-colors" title="<?= $isUrdu ? 'ترمیم' : 'Edit' ?>">
                                        <i data-lucide="edit-3" class="w-3.5 h-3.5"></i>
                                    </button>
                                    <form method="POST" class="inline" onsubmit="return confirm('<?= $isUrdu ? 'کیا آپ اس پوسٹ کو حذف کرنا چاہتے ہیں؟' : 'Delete this post from landing page?' ?>')">
                                        <input type="hidden" name="action" value="ceo_delete_landing_post">
                                        <input type="hidden" name="post_id" value="<?= $post['id'] ?>">
                                        <button type="submit" class="p-1.5 rounded-lg bg-rose-500/10 hover:bg-rose-500 text-rose-500 hover:text-white transition-colors" title="<?= $isUrdu ? 'حذف' : 'Delete' ?>">
                                            <i data-lucide="trash-2" class="w-3.5 h-3.5"></i>
                                        </button>
                                    </form>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>

    <!-- 5. TAB: PAYMENT COLLECTION ACCOUNTS & SETTINGS -->
    <div id="ceo-tab-accounts" class="ceo-tab-pane space-y-4 <?= $activeSubTab === 'accounts' ? '' : 'hidden' ?>">
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <!-- EasyPaisa & JazzCash Receiving Master Accounts -->
            <div class="p-6 rounded-3xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 shadow-sm space-y-4">
                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 rounded-xl bg-emerald-500/10 text-emerald-500 flex items-center justify-center font-black">
                        <i data-lucide="smartphone" class="w-5 h-5"></i>
                    </div>
                    <div>
                        <h4 class="font-black text-slate-900 dark:text-white text-base"><?= $isUrdu ? 'ایزی پیسہ و جاز کیش ہیڈ کوارٹر اکاؤنٹس' : 'EasyPaisa & JazzCash HQ Wallets' ?></h4>
                        <span class="text-xs text-slate-400"><?= $isUrdu ? 'دکان مالکان اس اکاؤنٹ پر فیس بھیجتے ہیں' : 'Receiving accounts for subscription fee payments' ?></span>
                    </div>
                </div>

                <div class="space-y-3 pt-2">
                    <div class="p-4 rounded-2xl bg-slate-50 dark:bg-slate-800/60 border border-slate-200 dark:border-slate-700/60 flex items-center justify-between">
                        <div>
                            <span class="text-[10px] font-black uppercase tracking-wider text-emerald-500 block">EasyPaisa Account</span>
                            <span class="text-sm font-black font-mono text-slate-900 dark:text-white">0300-6242842</span>
                            <span class="text-xs text-slate-400 block">Title: LimoMobile Master HQ</span>
                        </div>
                        <span class="px-2 py-1 rounded bg-emerald-500/20 text-emerald-400 text-[10px] font-bold font-mono">ACTIVE</span>
                    </div>

                    <div class="p-4 rounded-2xl bg-slate-50 dark:bg-slate-800/60 border border-slate-200 dark:border-slate-700/60 flex items-center justify-between">
                        <div>
                            <span class="text-[10px] font-black uppercase tracking-wider text-amber-500 block">JazzCash Account</span>
                            <span class="text-sm font-black font-mono text-slate-900 dark:text-white">0301-7654321</span>
                            <span class="text-xs text-slate-400 block">Title: LimoMobile POS Services</span>
                        </div>
                        <span class="px-2 py-1 rounded bg-amber-500/20 text-amber-400 text-[10px] font-bold font-mono">ACTIVE</span>
                    </div>
                </div>
            </div>

            <!-- Bank Transfer Account -->
            <div class="p-6 rounded-3xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 shadow-sm space-y-4">
                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 rounded-xl bg-limoblue-500/10 text-limoblue-500 flex items-center justify-center font-black">
                        <i data-lucide="landmark" class="w-5 h-5"></i>
                    </div>
                    <div>
                        <h4 class="font-black text-slate-900 dark:text-white text-base"><?= $isUrdu ? 'میزان بینک سینٹرل اکاؤنٹ' : 'Meezan Bank Central Collection' ?></h4>
                        <span class="text-xs text-slate-400"><?= $isUrdu ? 'سالانہ فیس و ڈائریکٹ بینک ٹرانسفر' : 'Direct IBFT / Online Bank Transfer' ?></span>
                    </div>
                </div>

                <div class="p-4 rounded-2xl bg-slate-50 dark:bg-slate-800/60 border border-slate-200 dark:border-slate-700/60 space-y-2">
                    <div class="flex items-center justify-between">
                        <span class="text-xs text-slate-400 font-bold"><?= $isUrdu ? 'بینک کا نام:' : 'Bank Name:' ?></span>
                        <span class="text-xs font-black text-slate-900 dark:text-white">Meezan Bank Ltd (Hafeez Centre Branch)</span>
                    </div>
                    <div class="flex items-center justify-between">
                        <span class="text-xs text-slate-400 font-bold"><?= $isUrdu ? 'اکاؤنٹ ٹائٹل:' : 'Account Title:' ?></span>
                        <span class="text-xs font-black text-slate-900 dark:text-white">LimoMobile Smart Technologies</span>
                    </div>
                    <div class="flex items-center justify-between">
                        <span class="text-xs text-slate-400 font-bold"><?= $isUrdu ? 'اکاؤنٹ نمبر:' : 'Account No:' ?></span>
                        <span class="text-xs font-mono font-black text-limoblue-500">02990104889210</span>
                    </div>
                    <div class="flex items-center justify-between">
                        <span class="text-xs text-slate-400 font-bold">IBAN:</span>
                        <span class="text-[11px] font-mono font-bold text-slate-400">PK62MEZN0002990104889210</span>
                    </div>
                </div>
            </div>
        </div>
    </div>

</div>

<!-- ================= MODALS ================= -->

<!-- 1. MODAL: ADD / EDIT SHOP -->
<div id="ceoShopModal" class="fixed inset-y-0 inset-x-0 z-50 bg-black/80 backdrop-blur-sm hidden flex items-center justify-center p-4 overflow-y-auto">
    <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-3xl max-w-xl w-full p-6 shadow-2xl space-y-5 my-8">
        <div class="flex items-center justify-between border-b border-slate-100 dark:border-slate-800 pb-4">
            <div class="flex items-center gap-2.5">
                <div class="w-10 h-10 rounded-xl bg-amber-500/10 text-amber-500 flex items-center justify-center font-black">
                    <i data-lucide="building" class="w-5 h-5"></i>
                </div>
                <h3 class="text-lg font-black text-slate-900 dark:text-white" id="ceoShopModalTitle">
                    <?= $isUrdu ? 'نئی دکان رجسٹر کریں' : 'Register New Shop' ?>
                </h3>
            </div>
            <button type="button" onclick="closeCeoShopModal()" class="p-2 rounded-xl text-slate-400 hover:text-white hover:bg-slate-800 transition-colors">✕</button>
        </div>

        <form method="POST" class="space-y-4">
            <input type="hidden" name="action" id="ceoShopAction" value="ceo_create_shop">
            <input type="hidden" name="shop_id" id="ceoShopId" value="">

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs font-bold mb-1 text-slate-700 dark:text-slate-300"><?= $isUrdu ? 'دکان کا نام (Shop Name)' : 'Shop Name' ?> *</label>
                    <input type="text" name="shop_name" id="ceoShopName" required placeholder="مثال: بلال موبائلز" class="w-full px-3 py-2.5 rounded-xl border text-xs bg-slate-50 dark:bg-slate-800 border-slate-200 dark:border-slate-700 text-slate-900 dark:text-white focus:outline-none focus:border-amber-500">
                </div>

                <div>
                    <label class="block text-xs font-bold mb-1 text-slate-700 dark:text-slate-300"><?= $isUrdu ? 'مالک کا نام (Owner Name)' : 'Owner Name' ?> *</label>
                    <input type="text" name="owner_name" id="ceoShopOwner" required placeholder="محمد بلال خان" class="w-full px-3 py-2.5 rounded-xl border text-xs bg-slate-50 dark:bg-slate-800 border-slate-200 dark:border-slate-700 text-slate-900 dark:text-white focus:outline-none focus:border-amber-500">
                </div>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs font-bold mb-1 text-slate-700 dark:text-slate-300"><?= $isUrdu ? 'فون / واٹس ایپ نمبر' : 'Phone / WhatsApp' ?> *</label>
                    <input type="text" name="phone" id="ceoShopPhone" required placeholder="0300-1234567" class="w-full px-3 py-2.5 rounded-xl border text-xs bg-slate-50 dark:bg-slate-800 border-slate-200 dark:border-slate-700 text-slate-900 dark:text-white focus:outline-none focus:border-amber-500">
                </div>

                <div>
                    <label class="block text-xs font-bold mb-1 text-slate-700 dark:text-slate-300"><?= $isUrdu ? 'شہر (City)' : 'City' ?></label>
                    <input type="text" name="city" id="ceoShopCity" placeholder="لاہور / کراچی / فیصل آباد" class="w-full px-3 py-2.5 rounded-xl border text-xs bg-slate-50 dark:bg-slate-800 border-slate-200 dark:border-slate-700 text-slate-900 dark:text-white focus:outline-none focus:border-amber-500">
                </div>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                <div>
                    <label class="block text-xs font-bold mb-1 text-slate-700 dark:text-slate-300"><?= $isUrdu ? 'پلان (Plan)' : 'Plan' ?></label>
                    <select name="subscription_plan" id="ceoShopPlan" class="w-full px-3 py-2.5 rounded-xl border text-xs bg-slate-50 dark:bg-slate-800 border-slate-200 dark:border-slate-700 text-slate-900 dark:text-white focus:outline-none focus:border-amber-500">
                        <option value="MONTHLY"><?= $isUrdu ? 'ماہانہ (Rs. 2,500)' : 'Monthly (Rs. 2,500)' ?></option>
                        <option value="YEARLY"><?= $isUrdu ? 'سالانہ (Rs. 24,000)' : 'Yearly (Rs. 24,000)' ?></option>
                        <option value="TRIAL"><?= $isUrdu ? 'ٹرائل (Free Trial)' : 'Free Trial' ?></option>
                    </select>
                </div>

                <div>
                    <label class="block text-xs font-bold mb-1 text-slate-700 dark:text-slate-300"><?= $isUrdu ? 'فیس رقم (PKR)' : 'Fee Amount (PKR)' ?></label>
                    <input type="number" name="subscription_fee" id="ceoShopFee" value="2500" class="w-full px-3 py-2.5 rounded-xl border text-xs bg-slate-50 dark:bg-slate-800 border-slate-200 dark:border-slate-700 text-slate-900 dark:text-white focus:outline-none focus:border-amber-500">
                </div>

                <div>
                    <label class="block text-xs font-bold mb-1 text-slate-700 dark:text-slate-300"><?= $isUrdu ? 'اسٹیٹس (Status)' : 'Status' ?></label>
                    <select name="subscription_status" id="ceoShopStatus" class="w-full px-3 py-2.5 rounded-xl border text-xs bg-slate-50 dark:bg-slate-800 border-slate-200 dark:border-slate-700 text-slate-900 dark:text-white focus:outline-none focus:border-amber-500">
                        <option value="ACTIVE">ACTIVE</option>
                        <option value="DUE">DUE</option>
                        <option value="OVERDUE">OVERDUE</option>
                        <option value="TRIAL">TRIAL</option>
                        <option value="SUSPENDED">SUSPENDED</option>
                    </select>
                </div>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs font-bold mb-1 text-slate-700 dark:text-slate-300"><?= $isUrdu ? 'اگلی تاریخ ادائیگی (Next Due)' : 'Next Due Date' ?></label>
                    <input type="date" name="next_due_date" id="ceoShopDueDate" value="<?= date('Y-m-d', strtotime('+30 days')) ?>" class="w-full px-3 py-2.5 rounded-xl border text-xs bg-slate-50 dark:bg-slate-800 border-slate-200 dark:border-slate-700 text-slate-900 dark:text-white focus:outline-none focus:border-amber-500">
                </div>

                <div>
                    <label class="block text-xs font-bold mb-1 text-slate-700 dark:text-slate-300"><?= $isUrdu ? 'پن کوڈ (Owner PIN)' : 'Security PIN' ?></label>
                    <input type="text" name="pin_code" id="ceoShopPin" value="6242" class="w-full px-3 py-2.5 rounded-xl border text-xs bg-slate-50 dark:bg-slate-800 border-slate-200 dark:border-slate-700 text-slate-900 dark:text-white focus:outline-none focus:border-amber-500">
                </div>
            </div>

            <div>
                <label class="block text-xs font-bold mb-1 text-slate-700 dark:text-slate-300"><?= $isUrdu ? 'مکمل پتہ (Address)' : 'Shop Address' ?></label>
                <input type="text" name="address" id="ceoShopAddress" placeholder="دکان نمبر، پلازہ، مارکیٹ کا پتہ" class="w-full px-3 py-2.5 rounded-xl border text-xs bg-slate-50 dark:bg-slate-800 border-slate-200 dark:border-slate-700 text-slate-900 dark:text-white focus:outline-none focus:border-amber-500">
            </div>

            <div class="pt-4 flex items-center justify-end gap-3 border-t border-slate-100 dark:border-slate-800">
                <button type="button" onclick="closeCeoShopModal()" class="px-4 py-2.5 rounded-xl border border-slate-300 dark:border-slate-700 text-slate-700 dark:text-slate-300 font-bold text-xs hover:bg-slate-100 dark:hover:bg-slate-800">
                    <?= $isUrdu ? 'منسوخ' : 'Cancel' ?>
                </button>
                <button type="submit" class="px-5 py-2.5 rounded-xl bg-amber-500 hover:bg-amber-600 text-slate-950 font-black text-xs shadow-lg shadow-amber-500/20">
                    <?= $isUrdu ? 'محفوظ کریں' : 'Save Shop' ?>
                </button>
            </div>
        </form>
    </div>
</div>

<!-- 2. MODAL: RECORD / COLLECT FEE PAYMENT -->
<div id="ceoFeeModal" class="fixed inset-y-0 inset-x-0 z-50 bg-black/80 backdrop-blur-sm hidden flex items-center justify-center p-4 overflow-y-auto">
    <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-3xl max-w-lg w-full p-6 shadow-2xl space-y-5 my-8">
        <div class="flex items-center justify-between border-b border-slate-100 dark:border-slate-800 pb-4">
            <div class="flex items-center gap-2.5">
                <div class="w-10 h-10 rounded-xl bg-emerald-500/10 text-emerald-500 flex items-center justify-center font-black">
                    <i data-lucide="wallet" class="w-5 h-5"></i>
                </div>
                <h3 class="text-lg font-black text-slate-900 dark:text-white">
                    <?= $isUrdu ? 'فیس وصولی ریکارڈ درج کریں' : 'Record SaaS Fee Payment' ?>
                </h3>
            </div>
            <button type="button" onclick="closeCeoFeeModal()" class="p-2 rounded-xl text-slate-400 hover:text-white hover:bg-slate-800 transition-colors">✕</button>
        </div>

        <form method="POST" class="space-y-4">
            <input type="hidden" name="action" value="ceo_record_fee_payment">

            <div>
                <label class="block text-xs font-bold mb-1 text-slate-700 dark:text-slate-300"><?= $isUrdu ? 'دکان منتخب کریں (Select Shop)' : 'Select Shop' ?> *</label>
                <select name="shop_id" id="ceoFeeShopSelect" onchange="onFeeShopChange(this)" required class="w-full px-3 py-2.5 rounded-xl border text-xs bg-slate-50 dark:bg-slate-800 border-slate-200 dark:border-slate-700 text-slate-900 dark:text-white focus:outline-none focus:border-emerald-500">
                    <option value=""><?= $isUrdu ? '-- برائے مہربانی دکان منتخب کریں --' : '-- Select Registered Shop --' ?></option>
                    <?php foreach ($allShops as $s): ?>
                        <option value="<?= $s['id'] ?>" data-name="<?= htmlspecialchars($s['shop_name']) ?>" data-owner="<?= htmlspecialchars($s['owner_name']) ?>" data-phone="<?= $s['phone'] ?>" data-fee="<?= $s['subscription_fee'] ?? 2500 ?>" data-plan="<?= $s['subscription_plan'] ?? 'MONTHLY' ?>">
                            <?= htmlspecialchars($s['shop_name']) ?> (<?= htmlspecialchars($s['owner_name']) ?> - <?= $s['city'] ?? '' ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <input type="hidden" name="shop_name" id="ceoFeeShopName" value="">
            <input type="hidden" name="owner_name" id="ceoFeeOwnerName" value="">
            <input type="hidden" name="phone" id="ceoFeePhone" value="">

            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs font-bold mb-1 text-slate-700 dark:text-slate-300"><?= $isUrdu ? 'وصولی رقم (PKR)' : 'Amount (PKR)' ?> *</label>
                    <input type="number" name="amount" id="ceoFeeAmount" value="2500" required class="w-full px-3 py-2.5 rounded-xl border text-xs bg-slate-50 dark:bg-slate-800 border-slate-200 dark:border-slate-700 text-slate-900 dark:text-white font-black focus:outline-none focus:border-emerald-500">
                </div>

                <div>
                    <label class="block text-xs font-bold mb-1 text-slate-700 dark:text-slate-300"><?= $isUrdu ? 'تاریخ ادائیگی' : 'Payment Date' ?> *</label>
                    <input type="date" name="payment_date" id="ceoFeeDate" value="<?= date('Y-m-d') ?>" required class="w-full px-3 py-2.5 rounded-xl border text-xs bg-slate-50 dark:bg-slate-800 border-slate-200 dark:border-slate-700 text-slate-900 dark:text-white focus:outline-none focus:border-emerald-500">
                </div>
            </div>

            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs font-bold mb-1 text-slate-700 dark:text-slate-300"><?= $isUrdu ? 'پلان سائیکل' : 'Plan Cycle' ?></label>
                    <select name="plan_type" id="ceoFeePlanType" class="w-full px-3 py-2.5 rounded-xl border text-xs bg-slate-50 dark:bg-slate-800 border-slate-200 dark:border-slate-700 text-slate-900 dark:text-white focus:outline-none focus:border-emerald-500">
                        <option value="MONTHLY"><?= $isUrdu ? 'ماہانہ (30 دن رینیول)' : 'Monthly (+30 Days)' ?></option>
                        <option value="YEARLY"><?= $isUrdu ? 'سالانہ (365 دن رینیول)' : 'Yearly (+365 Days)' ?></option>
                    </select>
                </div>

                <div>
                    <label class="block text-xs font-bold mb-1 text-slate-700 dark:text-slate-300"><?= $isUrdu ? 'طریقہ وصولی' : 'Payment Method' ?></label>
                    <select name="payment_method" id="ceoFeeMethod" class="w-full px-3 py-2.5 rounded-xl border text-xs bg-slate-50 dark:bg-slate-800 border-slate-200 dark:border-slate-700 text-slate-900 dark:text-white focus:outline-none focus:border-emerald-500">
                        <option value="EASYPAISA">EasyPaisa</option>
                        <option value="JAZZCASH">JazzCash</option>
                        <option value="BANK">Bank Transfer (IBFT)</option>
                        <option value="CASH">Direct Cash</option>
                    </select>
                </div>
            </div>

            <div>
                <label class="block text-xs font-bold mb-1 text-slate-700 dark:text-slate-300"><?= $isUrdu ? 'ٹرانزیکشن آئی ڈی / ریفرنس نمبر' : 'Transaction ID / Ref #' ?></label>
                <input type="text" name="trx_id" id="ceoFeeTrxId" placeholder="مثال: TID-88319024" class="w-full px-3 py-2.5 rounded-xl border text-xs bg-slate-50 dark:bg-slate-800 border-slate-200 dark:border-slate-700 text-slate-900 dark:text-white font-mono focus:outline-none focus:border-emerald-500">
            </div>

            <div>
                <label class="block text-xs font-bold mb-1 text-slate-700 dark:text-slate-300"><?= $isUrdu ? 'اضافی نوٹ / تفصیلات' : 'Notes / Remarks' ?></label>
                <input type="text" name="notes" id="ceoFeeNotes" placeholder="ماہانہ سبسکرپشن فیس موصول شدہ" class="w-full px-3 py-2.5 rounded-xl border text-xs bg-slate-50 dark:bg-slate-800 border-slate-200 dark:border-slate-700 text-slate-900 dark:text-white focus:outline-none focus:border-emerald-500">
            </div>

            <div class="pt-4 flex items-center justify-end gap-3 border-t border-slate-100 dark:border-slate-800">
                <button type="button" onclick="closeCeoFeeModal()" class="px-4 py-2.5 rounded-xl border border-slate-300 dark:border-slate-700 text-slate-700 dark:text-slate-300 font-bold text-xs hover:bg-slate-100 dark:hover:bg-slate-800">
                    <?= $isUrdu ? 'منسوخ' : 'Cancel' ?>
                </button>
                <button type="submit" class="px-5 py-2.5 rounded-xl bg-emerald-600 hover:bg-emerald-500 text-white font-black text-xs shadow-lg shadow-emerald-600/30">
                    <?= $isUrdu ? 'وصولی کنفرم کریں' : 'Confirm Payment & Renew' ?>
                </button>
            </div>
        </form>
    </div>
</div>

<!-- 3. MODAL: CREATE / EDIT LANDING POST -->
<div id="ceoPostModal" class="fixed inset-y-0 inset-x-0 z-50 bg-black/80 backdrop-blur-sm hidden flex items-center justify-center p-4 overflow-y-auto">
    <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-3xl max-w-xl w-full p-6 shadow-2xl space-y-5 my-8">
        <div class="flex items-center justify-between border-b border-slate-100 dark:border-slate-800 pb-4">
            <div class="flex items-center gap-2.5">
                <div class="w-10 h-10 rounded-xl bg-limoblue-500/10 text-limoblue-500 flex items-center justify-center font-black">
                    <i data-lucide="megaphone" class="w-5 h-5"></i>
                </div>
                <h3 class="text-lg font-black text-slate-900 dark:text-white" id="ceoPostModalTitle">
                    <?= $isUrdu ? 'لینڈنگ پیج پر نئی پوسٹ / اعلان کریں' : 'Publish Landing Page Post' ?>
                </h3>
            </div>
            <button type="button" onclick="closeCeoPostModal()" class="p-2 rounded-xl text-slate-400 hover:text-white hover:bg-slate-800 transition-colors">✕</button>
        </div>

        <form method="POST" class="space-y-4">
            <input type="hidden" name="action" id="ceoPostAction" value="ceo_create_landing_post">
            <input type="hidden" name="post_id" id="ceoPostId" value="">

            <div>
                <label class="block text-xs font-bold mb-1 text-slate-700 dark:text-slate-300"><?= $isUrdu ? 'پوسٹ عنوان (Title)' : 'Post Title' ?> *</label>
                <input type="text" name="title" id="ceoPostTitle" required placeholder="مثال: لیمو موبائل سافٹ ویئر v2.5 کا نیا ورژن ریلیز!" class="w-full px-3 py-2.5 rounded-xl border text-xs bg-slate-50 dark:bg-slate-800 border-slate-200 dark:border-slate-700 text-slate-900 dark:text-white font-extrabold focus:outline-none focus:border-limoblue-500">
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs font-bold mb-1 text-slate-700 dark:text-slate-300"><?= $isUrdu ? 'کیٹیگری (Category)' : 'Category' ?></label>
                    <select name="category" id="ceoPostCategory" class="w-full px-3 py-2.5 rounded-xl border text-xs bg-slate-50 dark:bg-slate-800 border-slate-200 dark:border-slate-700 text-slate-900 dark:text-white focus:outline-none focus:border-limoblue-500">
                        <option value="ANNOUNCEMENT"><?= $isUrdu ? 'اعلان (Announcement)' : 'Announcement' ?></option>
                        <option value="OFFER"><?= $isUrdu ? 'ڈسکاؤنٹ آفر (Special Offer)' : 'Special Offer' ?></option>
                        <option value="FEATURE"><?= $isUrdu ? 'نیا فیچر (New Feature)' : 'New Feature' ?></option>
                        <option value="UPDATE"><?= $isUrdu ? 'سافٹ ویئر اپ ڈیٹ (Update)' : 'Software Update' ?></option>
                        <option value="NOTICE"><?= $isUrdu ? 'سیکیورٹی نوٹس (Notice)' : 'Important Notice' ?></option>
                    </select>
                </div>

                <div>
                    <label class="block text-xs font-bold mb-1 text-slate-700 dark:text-slate-300"><?= $isUrdu ? 'بیج ٹیگ (Badge Tag)' : 'Badge Tag (e.g. New v2.5)' ?></label>
                    <input type="text" name="badge_text" id="ceoPostBadge" placeholder="نیا ورژن 2.5 / 50% ڈسکاؤنٹ" class="w-full px-3 py-2.5 rounded-xl border text-xs bg-slate-50 dark:bg-slate-800 border-slate-200 dark:border-slate-700 text-slate-900 dark:text-white focus:outline-none focus:border-limoblue-500">
                </div>
            </div>

            <div>
                <label class="block text-xs font-bold mb-1 text-slate-700 dark:text-slate-300"><?= $isUrdu ? 'بینر تصویر کا لنک (Image URL)' : 'Banner Image URL' ?></label>
                <input type="url" name="image_url" id="ceoPostImage" placeholder="https://images.unsplash.com/photo-..." class="w-full px-3 py-2.5 rounded-xl border text-xs bg-slate-50 dark:bg-slate-800 border-slate-200 dark:border-slate-700 text-slate-900 dark:text-white font-mono focus:outline-none focus:border-limoblue-500">
                <span class="text-[10px] text-slate-400 mt-1 block"><?= $isUrdu ? 'کوئی بھی تصویر یا Unsplash کا لنک لگا سکتے ہیں' : 'Provide any image or Unsplash banner photo link' ?></span>
            </div>

            <div>
                <label class="block text-xs font-bold mb-1 text-slate-700 dark:text-slate-300"><?= $isUrdu ? 'پوسٹ کی مکمل تفصیلات (Post Content)' : 'Post Content / Details' ?> *</label>
                <textarea name="content" id="ceoPostContent" rows="4" required placeholder="نئے فیچرز اور تفصیلات درج کریں..." class="w-full px-3 py-2.5 rounded-xl border text-xs bg-slate-50 dark:bg-slate-800 border-slate-200 dark:border-slate-700 text-slate-900 dark:text-white focus:outline-none focus:border-limoblue-500 leading-relaxed"></textarea>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs font-bold mb-1 text-slate-700 dark:text-slate-300"><?= $isUrdu ? 'بٹن کا متن (Button Text)' : 'Action Button Text' ?></label>
                    <input type="text" name="action_text" id="ceoPostActionText" placeholder="نیا اکاؤنٹ بنائیں / چیک کریں" class="w-full px-3 py-2.5 rounded-xl border text-xs bg-slate-50 dark:bg-slate-800 border-slate-200 dark:border-slate-700 text-slate-900 dark:text-white focus:outline-none focus:border-limoblue-500">
                </div>

                <div>
                    <label class="block text-xs font-bold mb-1 text-slate-700 dark:text-slate-300"><?= $isUrdu ? 'بٹن کا لنک (Button Link)' : 'Action Button URL' ?></label>
                    <input type="text" name="action_url" id="ceoPostActionUrl" value="../register_shop/index.php" class="w-full px-3 py-2.5 rounded-xl border text-xs bg-slate-50 dark:bg-slate-800 border-slate-200 dark:border-slate-700 text-slate-900 dark:text-white font-mono focus:outline-none focus:border-limoblue-500">
                </div>
            </div>

            <div class="flex items-center gap-4 pt-2">
                <label class="flex items-center gap-2 cursor-pointer text-xs font-bold text-slate-700 dark:text-slate-300">
                    <input type="checkbox" name="is_pinned" id="ceoPostPinned" value="1" class="rounded text-amber-500 focus:ring-amber-400">
                    <span>📌 <?= $isUrdu ? 'سب سے اوپر پن کریں (Featured Pin)' : 'Pin to Top (Featured)' ?></span>
                </label>
            </div>

            <div class="pt-4 flex items-center justify-end gap-3 border-t border-slate-100 dark:border-slate-800">
                <button type="button" onclick="closeCeoPostModal()" class="px-4 py-2.5 rounded-xl border border-slate-300 dark:border-slate-700 text-slate-700 dark:text-slate-300 font-bold text-xs hover:bg-slate-100 dark:hover:bg-slate-800">
                    <?= $isUrdu ? 'منسوخ' : 'Cancel' ?>
                </button>
                <button type="submit" class="px-5 py-2.5 rounded-xl bg-limoblue-600 hover:bg-limoblue-500 text-white font-black text-xs shadow-lg shadow-limoblue-600/30">
                    <?= $isUrdu ? 'پوسٹ شائع کریں' : 'Publish to Landing Page' ?>
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Fee Receipt Printable Hidden Container -->
<div id="feeReceiptPrintArea" class="hidden print:block p-8 max-w-xl mx-auto font-sans text-slate-900">
    <div class="border-4 border-slate-900 p-6 rounded-2xl space-y-4">
        <div class="text-center border-b-2 border-slate-900 pb-4">
            <h1 class="text-2xl font-black tracking-tight">LIMOMOBILE SMART POS</h1>
            <p class="text-xs font-bold uppercase tracking-widest text-slate-600">Central SaaS Headquarters • Official Fee Receipt</p>
            <p class="text-xs mt-1">Helpline / WhatsApp: +92 300 6242842</p>
        </div>

        <div class="grid grid-cols-2 text-xs py-2 border-b border-slate-300">
            <div><strong>Receipt No:</strong> <span id="prReceiptNo"></span></div>
            <div class="text-right"><strong>Date:</strong> <span id="prDate"></span></div>
            <div><strong>Shop:</strong> <span id="prShopName"></span></div>
            <div class="text-right"><strong>Owner:</strong> <span id="prOwner"></span></div>
            <div><strong>Plan Cycle:</strong> <span id="prPlan"></span></div>
            <div class="text-right"><strong>Payment Method:</strong> <span id="prMethod"></span></div>
        </div>

        <div class="py-4 text-center">
            <span class="text-xs uppercase font-bold text-slate-500">Total Subscription Fee Paid</span>
            <div class="text-3xl font-black mt-1" id="prAmount"></div>
            <span class="text-xs font-mono text-slate-500 mt-1 block">Transaction Ref: <span id="prTrx"></span></span>
        </div>

        <div class="text-center text-[10px] text-slate-500 border-t border-slate-300 pt-3">
            Thank you for using LimoMobile Smart POS Software. This is a computer-generated verified payment receipt.
        </div>
    </div>
</div>

<script>
    function switchCeoTab(tabKey) {
        document.querySelectorAll('.ceo-tab-pane').forEach(el => el.classList.add('hidden'));
        document.querySelectorAll('.ceo-tab-btn').forEach(btn => {
            btn.className = "ceo-tab-btn px-4 py-2.5 rounded-xl flex items-center gap-2 transition-all cursor-pointer text-slate-400 hover:text-white hover:bg-slate-800";
        });

        const targetPane = document.getElementById('ceo-tab-' + tabKey);
        const targetBtn = document.getElementById('btn-ceo-' + tabKey);
        if (targetPane) targetPane.classList.remove('hidden');
        if (targetBtn) {
            targetBtn.className = "ceo-tab-btn px-4 py-2.5 rounded-xl flex items-center gap-2 transition-all cursor-pointer bg-amber-500 text-slate-950 font-black shadow-md";
        }
    }

    function openCeoShopModal() {
        document.getElementById('ceoShopAction').value = 'ceo_create_shop';
        document.getElementById('ceoShopId').value = '';
        document.getElementById('ceoShopModalTitle').innerText = '<?= $isUrdu ? "نئی دکان رجسٹر کریں" : "Register New Shop" ?>';
        document.getElementById('ceoShopName').value = '';
        document.getElementById('ceoShopOwner').value = '';
        document.getElementById('ceoShopPhone').value = '';
        document.getElementById('ceoShopCity').value = '';
        document.getElementById('ceoShopAddress').value = '';
        document.getElementById('ceoShopModal').classList.remove('hidden');
    }

    function closeCeoShopModal() {
        document.getElementById('ceoShopModal').classList.add('hidden');
    }

    function editCeoShop(shop) {
        document.getElementById('ceoShopAction').value = 'ceo_edit_shop';
        document.getElementById('ceoShopId').value = shop.id || '';
        document.getElementById('ceoShopModalTitle').innerText = '<?= $isUrdu ? "دکان ڈیٹا و فیس سیٹنگز تبدیل کریں" : "Edit Shop & Subscription" ?>';
        document.getElementById('ceoShopName').value = shop.shop_name || '';
        document.getElementById('ceoShopOwner').value = shop.owner_name || '';
        document.getElementById('ceoShopPhone').value = shop.phone || '';
        document.getElementById('ceoShopCity').value = shop.city || '';
        document.getElementById('ceoShopAddress').value = shop.address || '';
        document.getElementById('ceoShopPlan').value = shop.subscription_plan || 'MONTHLY';
        document.getElementById('ceoShopFee').value = shop.subscription_fee || 2500;
        document.getElementById('ceoShopStatus').value = shop.subscription_status || 'ACTIVE';
        document.getElementById('ceoShopDueDate').value = shop.next_due_date || '';
        document.getElementById('ceoShopPin').value = shop.pin_code || '6242';
        document.getElementById('ceoShopModal').classList.remove('hidden');
    }

    function openCeoFeeModal() {
        document.getElementById('ceoFeeModal').classList.remove('hidden');
    }

    function closeCeoFeeModal() {
        document.getElementById('ceoFeeModal').classList.add('hidden');
    }

    function onFeeShopChange(sel) {
        const opt = sel.options[sel.selectedIndex];
        if (opt && opt.value) {
            document.getElementById('ceoFeeShopName').value = opt.getAttribute('data-name') || '';
            document.getElementById('ceoFeeOwnerName').value = opt.getAttribute('data-owner') || '';
            document.getElementById('ceoFeePhone').value = opt.getAttribute('data-phone') || '';
            document.getElementById('ceoFeeAmount').value = opt.getAttribute('data-fee') || '2500';
            document.getElementById('ceoFeePlanType').value = opt.getAttribute('data-plan') || 'MONTHLY';
        }
    }

    function quickCollectFee(shopId, shopName, ownerName, phone, fee, plan) {
        openCeoFeeModal();
        const sel = document.getElementById('ceoFeeShopSelect');
        sel.value = shopId;
        document.getElementById('ceoFeeShopName').value = shopName;
        document.getElementById('ceoFeeOwnerName').value = ownerName;
        document.getElementById('ceoFeePhone').value = phone;
        document.getElementById('ceoFeeAmount').value = fee;
        document.getElementById('ceoFeePlanType').value = plan;
    }

    function openCeoPostModal() {
        document.getElementById('ceoPostAction').value = 'ceo_create_landing_post';
        document.getElementById('ceoPostId').value = '';
        document.getElementById('ceoPostModalTitle').innerText = '<?= $isUrdu ? "لینڈنگ پیج پر نئی پوسٹ / اعلان کریں" : "Publish Landing Page Post" ?>';
        document.getElementById('ceoPostTitle').value = '';
        document.getElementById('ceoPostBadge').value = '';
        document.getElementById('ceoPostImage').value = '';
        document.getElementById('ceoPostContent').value = '';
        document.getElementById('ceoPostActionText').value = 'نیا اکاؤنٹ بنائیں';
        document.getElementById('ceoPostActionUrl').value = '../register_shop/index.php';
        document.getElementById('ceoPostPinned').checked = false;
        document.getElementById('ceoPostModal').classList.remove('hidden');
    }

    function closeCeoPostModal() {
        document.getElementById('ceoPostModal').classList.add('hidden');
    }

    function editCeoPost(post) {
        document.getElementById('ceoPostAction').value = 'ceo_edit_landing_post';
        document.getElementById('ceoPostId').value = post.id || '';
        document.getElementById('ceoPostModalTitle').innerText = '<?= $isUrdu ? "پوسٹ میں ترمیم کریں" : "Edit Landing Post" ?>';
        document.getElementById('ceoPostTitle').value = post.title || '';
        document.getElementById('ceoPostCategory').value = post.category || 'ANNOUNCEMENT';
        document.getElementById('ceoPostBadge').value = post.badge_text || '';
        document.getElementById('ceoPostImage').value = post.image_url || '';
        document.getElementById('ceoPostContent').value = post.content || '';
        document.getElementById('ceoPostActionText').value = post.action_text || '';
        document.getElementById('ceoPostActionUrl').value = post.action_url || '';
        document.getElementById('ceoPostPinned').checked = post.is_pinned == 1;
        document.getElementById('ceoPostModal').classList.remove('hidden');
    }

    function filterShopTable() {
        const query = (document.getElementById('shopSearchInput').value || '').toLowerCase();
        const status = document.getElementById('shopStatusFilter').value;
        const rows = document.querySelectorAll('.shop-row');

        rows.forEach(row => {
            const text = row.innerText.toLowerCase();
            const rowStatus = row.getAttribute('data-status') || 'ACTIVE';
            const matchesQuery = text.includes(query);
            const matchesStatus = (status === 'ALL' || rowStatus === status);

            if (matchesQuery && matchesStatus) {
                row.style.display = '';
            } else {
                row.style.display = 'none';
            }
        });
    }

    function printFeeReceipt(receipt) {
        document.getElementById('prReceiptNo').innerText = receipt.receipt_no || 'REC-LIMO';
        document.getElementById('prDate').innerText = receipt.payment_date || '';
        document.getElementById('prShopName').innerText = receipt.shop_name || '';
        document.getElementById('prOwner').innerText = receipt.owner_name || '';
        document.getElementById('prPlan').innerText = receipt.plan_type || 'MONTHLY';
        document.getElementById('prMethod').innerText = receipt.payment_method || 'EASYPAISA';
        document.getElementById('prAmount').innerText = 'Rs. ' + Number(receipt.amount || 0).toLocaleString();
        document.getElementById('prTrx').innerText = receipt.trx_id || 'N/A';
        window.print();
    }
</script>
