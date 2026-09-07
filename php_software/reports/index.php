<?php
$pageTitle = 'رپورٹس و کاروباری تجزیات (Reports & Analytics)';
$activeMenu = 'reports';
require_once __DIR__ . '/../backend/header.php';

// Date Filter Handling
$preset = $_GET['preset'] ?? 'this_month';
$today = date('Y-m-d');

if ($preset === 'today') {
    $fromDate = $today;
    $toDate = $today;
} elseif ($preset === 'yesterday') {
    $fromDate = date('Y-m-d', strtotime('-1 day'));
    $toDate = $fromDate;
} elseif ($preset === '7days') {
    $fromDate = date('Y-m-d', strtotime('-6 days'));
    $toDate = $today;
} elseif ($preset === 'this_month') {
    $fromDate = date('Y-m-01');
    $toDate = $today;
} elseif ($preset === 'last_month') {
    $fromDate = date('Y-m-01', strtotime('first day of last month'));
    $toDate = date('Y-m-t', strtotime('last month'));
} elseif ($preset === 'this_year') {
    $fromDate = date('Y-01-01');
    $toDate = $today;
} elseif ($preset === 'all') {
    $fromDate = '2020-01-01';
    $toDate = '2099-12-31';
} else {
    $fromDate = $_GET['from'] ?? date('Y-m-01');
    $toDate = $_GET['to'] ?? $today;
}

// 1. POS Product Sales Stats
$stmtSales = $pdo->prepare("SELECT 
    COUNT(*) as count, 
    COALESCE(SUM(net_amount), 0) as total_sales, 
    COALESCE(SUM(profit), 0) as total_profit 
    FROM product_sales WHERE date BETWEEN :from AND :to");
$stmtSales->execute([':from' => $fromDate, ':to' => $toDate]);
$salesStats = $stmtSales->fetch();

$cogs = $salesStats['total_sales'] - $salesStats['total_profit'];

// 2. EasyPaisa / Wallet Trx Stats
$stmtTrx = $pdo->prepare("SELECT 
    COUNT(*) as count,
    COALESCE(SUM(CASE WHEN type IN ('BUY_EASYPAISA', 'BUY_CASH') THEN easypaisa_amount ELSE 0 END), 0) as buy_volume,
    COALESCE(SUM(CASE WHEN type IN ('SELL_EASYPAISA', 'SELL_CASH') THEN easypaisa_amount ELSE 0 END), 0) as sell_volume,
    COALESCE(SUM(fee_profit), 0) as ep_profit,
    COALESCE(SUM(CASE WHEN type IN ('EXPENSE', 'DISCREPANCY_LOSS') THEN expense_amount ELSE 0 END), 0) as expenses
    FROM transactions WHERE date BETWEEN :from AND :to");
$stmtTrx->execute([':from' => $fromDate, ':to' => $toDate]);
$trxStats = $stmtTrx->fetch();

// 3. Mobile Purchases
$stmtMob = $pdo->prepare("SELECT 
    COUNT(*) as count,
    COALESCE(SUM(purchase_price), 0) as total_invested
    FROM mobile_purchases WHERE date BETWEEN :from AND :to");
$stmtMob->execute([':from' => $fromDate, ':to' => $toDate]);
$mobStats = $stmtMob->fetch();

// 4. Stock Valuation (Real-time Assets)
$stmtStock = $pdo->query("SELECT 
    COALESCE(SUM(stock), 0) as total_units,
    COALESCE(SUM(stock * purchase_price), 0) as cost_val,
    COALESCE(SUM(stock * sale_price), 0) as retail_val
    FROM products");
$stockVal = $stmtStock->fetch();
$potentialStockProfit = $stockVal['retail_val'] - $stockVal['cost_val'];

// Combined Total Calculations
$combinedGrossProfit = $salesStats['total_profit'] + $trxStats['ep_profit'];
$netIncome = $combinedGrossProfit - $trxStats['expenses'];
$totalRevenue = $salesStats['total_sales'] + $trxStats['ep_profit'];
$netMargin = $totalRevenue > 0 
    ? round(($netIncome / $totalRevenue) * 100, 1) 
    : 0;

// 5. Khata Balances
$stmtCust = $pdo->query("SELECT 
    COALESCE(SUM(CASE WHEN balance > 0 THEN balance ELSE 0 END), 0) as total_udhar,
    COALESCE(SUM(CASE WHEN balance < 0 THEN ABS(balance) ELSE 0 END), 0) as total_advance,
    COUNT(CASE WHEN balance > 0 THEN 1 END) as debtors_count
    FROM customers");
$custStats = $stmtCust->fetch();

$stmtSupp = $pdo->query("SELECT COALESCE(SUM(CASE WHEN balance > 0 THEN balance ELSE 0 END), 0) as total_supplier_due FROM suppliers");
$suppStats = $stmtSupp->fetch();

// 6. Top Selling Products
$allSales = $pdo->prepare("SELECT items_json FROM product_sales WHERE date BETWEEN :from AND :to LIMIT 500");
$allSales->execute([':from' => $fromDate, ':to' => $toDate]);
$salesRows = $allSales->fetchAll();

$itemAgg = [];
$catAgg = [];
foreach($salesRows as $row) {
    $items = json_decode($row['items_json'] ?? '[]', true);
    if (is_array($items)) {
        foreach($items as $it) {
            $name = $it['productName'] ?? $it['name'] ?? 'نامعلوم آئٹم';
            $cat = $it['category'] ?? 'OTHER';
            $qty = intval($it['quantity'] ?? 1);
            $price = floatval($it['salePrice'] ?? $it['unitSalePrice'] ?? 0);
            $cost = floatval($it['costPrice'] ?? $it['purchasePrice'] ?? 0);
            
            if (!isset($itemAgg[$name])) {
                $itemAgg[$name] = ['name' => $name, 'category' => $cat, 'qty' => 0, 'revenue' => 0, 'profit' => 0];
            }
            $itemAgg[$name]['qty'] += $qty;
            $itemAgg[$name]['revenue'] += ($price * $qty);
            $itemAgg[$name]['profit'] += (($price - $cost) * $qty);

            if (!isset($catAgg[$cat])) {
                $catAgg[$cat] = ['qty' => 0, 'revenue' => 0, 'profit' => 0];
            }
            $catAgg[$cat]['qty'] += $qty;
            $catAgg[$cat]['revenue'] += ($price * $qty);
            $catAgg[$cat]['profit'] += (($price - $cost) * $qty);
        }
    }
}
uasort($itemAgg, fn($a, $b) => $b['qty'] <=> $a['qty']);
$topItems = array_slice($itemAgg, 0, 10, true);

// 7. Channel Breakdown
$stmtChannels = $pdo->prepare("SELECT 
    payment_method, 
    COUNT(*) as count,
    COALESCE(SUM(fee_profit), 0) as profit,
    COALESCE(SUM(easypaisa_amount), 0) as volume,
    COALESCE(SUM(CASE WHEN type IN ('BUY_EASYPAISA', 'BUY_CASH') THEN easypaisa_amount ELSE 0 END), 0) as buy,
    COALESCE(SUM(CASE WHEN type IN ('SELL_EASYPAISA', 'SELL_CASH') THEN easypaisa_amount ELSE 0 END), 0) as sell
    FROM transactions 
    WHERE date BETWEEN :from AND :to AND type NOT IN ('EXPENSE', 'DISCREPANCY_LOSS')
    GROUP BY payment_method");
$stmtChannels->execute([':from' => $fromDate, ':to' => $toDate]);
$channels = $stmtChannels->fetchAll();

// CSV Export Trigger
if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="Business_Report_' . $fromDate . '_to_' . $toDate . '.csv"');
    $output = fopen('php://output', 'w');
    fputcsv($output, ['Head / Category', 'Amount (PKR)', 'Details']);
    fputcsv($output, ['Gross POS Sales', $salesStats['total_sales'], $salesStats['count'] . ' Invoices']);
    fputcsv($output, ['Cost of Goods Sold (COGS)', $cogs, 'Purchase Cost of Sold Items']);
    fputcsv($output, ['Gross Profit (POS)', $salesStats['total_profit'], '']);
    fputcsv($output, ['EasyPaisa & Commission', $trxStats['ep_profit'], 'Wallets & Transfer Fees']);
    fputcsv($output, ['Shop Expenses', $trxStats['expenses'], 'Bills, Rent & Operating Expenses']);
    fputcsv($output, ['Net Profit', $netIncome, 'Net Margin ' . $netMargin . '%']);
    fputcsv($output, ['Total In-Stock Valuation (Cost)', $stockVal['cost_val'], $stockVal['total_units'] . ' Units in Stock']);
    fputcsv($output, ['Market Customer Khata Due', $custStats['total_udhar'], $custStats['debtors_count'] . ' Debtors']);
    fputcsv($output, ['Supplier Khata Due', $suppStats['total_supplier_due'], 'Payable Liabilities']);
    fclose($output);
    exit;
}
?>

<!-- Header Action Toolbar with Presets & Date Filters -->
<div class="bg-slate-900 border border-slate-800 p-4 sm:p-5 rounded-2xl mb-6 shadow-lg flex flex-col xl:flex-row xl:items-center justify-between gap-4 no-print">
    <div>
        <h2 class="text-lg font-black text-white flex items-center gap-2">
            <i data-lucide="bar-chart-3" class="w-5 h-5 text-emerald-400"></i>
            <span>کاروباری رپورٹس و ایگزیکٹو آڈٹ (Executive Business Audit)</span>
        </h2>
        <p class="text-xs text-slate-400 mt-0.5">سیلز، منافع، ایزی پیسہ فیس، دکان کے اخراجات، اسٹاک مالیت اور ادھار کا مکمل گوشوارہ</p>
    </div>

    <form method="GET" class="flex flex-wrap items-center gap-2">
        
        <!-- Preset Shortcuts -->
        <div class="flex items-center gap-1 p-1 bg-slate-950 rounded-xl border border-slate-800 text-xs font-bold">
            <a href="?preset=today" class="px-2.5 py-1 rounded-lg <?= $preset === 'today' ? 'bg-emerald-600 text-white' : 'text-slate-400 hover:text-white' ?>">آج</a>
            <a href="?preset=yesterday" class="px-2.5 py-1 rounded-lg <?= $preset === 'yesterday' ? 'bg-emerald-600 text-white' : 'text-slate-400 hover:text-white' ?>">کل</a>
            <a href="?preset=7days" class="px-2.5 py-1 rounded-lg <?= $preset === '7days' ? 'bg-emerald-600 text-white' : 'text-slate-400 hover:text-white' ?>">7 دن</a>
            <a href="?preset=this_month" class="px-2.5 py-1 rounded-lg <?= $preset === 'this_month' ? 'bg-emerald-600 text-white' : 'text-slate-400 hover:text-white' ?>">یہ ماہ</a>
            <a href="?preset=last_month" class="px-2.5 py-1 rounded-lg <?= $preset === 'last_month' ? 'bg-emerald-600 text-white' : 'text-slate-400 hover:text-white' ?>">گزشتہ ماہ</a>
            <a href="?preset=all" class="px-2.5 py-1 rounded-lg <?= $preset === 'all' ? 'bg-emerald-600 text-white' : 'text-slate-400 hover:text-white' ?>">سارا</a>
        </div>

        <!-- Custom Date Range -->
        <div class="flex items-center gap-1.5 bg-slate-950 px-2.5 py-1.5 rounded-xl border border-slate-700 text-xs">
            <i data-lucide="calendar" class="w-4 h-4 text-emerald-400"></i>
            <input type="date" name="from" value="<?= htmlspecialchars($fromDate) ?>" class="bg-transparent text-white text-xs font-mono outline-none cursor-pointer">
            <span class="text-slate-500 font-bold">تا</span>
            <input type="date" name="to" value="<?= htmlspecialchars($toDate) ?>" class="bg-transparent text-white text-xs font-mono outline-none cursor-pointer">
        </div>

        <button type="submit" class="px-3.5 py-2 bg-emerald-600 hover:bg-emerald-500 text-white font-bold text-xs rounded-xl shadow cursor-pointer transition">
            دیکھیں
        </button>
        <a href="?from=<?= htmlspecialchars($fromDate) ?>&to=<?= htmlspecialchars($toDate) ?>&export=csv" class="px-3 py-2 bg-slate-800 hover:bg-slate-700 text-slate-300 hover:text-white font-bold text-xs rounded-xl border border-slate-700 flex items-center gap-1.5 transition">
            <i data-lucide="download" class="w-4 h-4 text-cyan-400"></i>
            <span>CSV</span>
        </a>
        <button type="button" onclick="window.print()" class="px-3 py-2 bg-cyan-600 hover:bg-cyan-500 text-white font-bold text-xs rounded-xl shadow flex items-center gap-1.5 transition cursor-pointer">
            <i data-lucide="printer" class="w-4 h-4"></i>
            <span>پرنٹ</span>
        </button>
    </form>
</div>

<!-- 6 Core Executive Metric Cards -->
<div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-6 gap-4 mb-6 no-print">
    
    <!-- Net Profit -->
    <div class="bg-slate-900 border border-slate-800 p-4 rounded-2xl shadow-lg relative overflow-hidden">
        <span class="text-[11px] font-bold text-slate-400 block mb-1">خالص منافع (Net Income)</span>
        <h3 class="text-xl sm:text-2xl font-black <?= $netIncome >= 0 ? 'text-emerald-400' : 'text-rose-400' ?> font-mono">
            Rs. <?= number_format($netIncome) ?>
        </h3>
        <div class="text-[10px] text-slate-400 mt-1 flex items-center justify-between">
            <span>اخراجات منہا شدہ</span>
            <span class="font-bold text-emerald-400 font-mono"><?= $netMargin ?>% Margin</span>
        </div>
        <div class="absolute bottom-0 left-0 right-0 h-1 bg-gradient-to-r from-emerald-500 to-teal-500"></div>
    </div>

    <!-- POS Sales -->
    <div class="bg-slate-900 border border-slate-800 p-4 rounded-2xl shadow-lg relative overflow-hidden">
        <span class="text-[11px] font-bold text-slate-400 block mb-1">پی او ایس سیلز (POS Sales)</span>
        <h3 class="text-xl sm:text-2xl font-black text-white font-mono">
            Rs. <?= number_format($salesStats['total_sales']) ?>
        </h3>
        <div class="text-[10px] text-slate-400 mt-1 flex items-center justify-between">
            <span><?= $salesStats['count'] ?> انوائسز</span>
            <span class="font-bold text-cyan-400">خام منافع: Rs. <?= number_format($salesStats['total_profit']) ?></span>
        </div>
        <div class="absolute bottom-0 left-0 right-0 h-1 bg-gradient-to-r from-cyan-500 to-blue-500"></div>
    </div>

    <!-- EasyPaisa Profit -->
    <div class="bg-slate-900 border border-slate-800 p-4 rounded-2xl shadow-lg relative overflow-hidden">
        <span class="text-[11px] font-bold text-slate-400 block mb-1">والٹس کمیشن (EP Profit)</span>
        <h3 class="text-xl sm:text-2xl font-black text-teal-400 font-mono">
            Rs. <?= number_format($trxStats['ep_profit']) ?>
        </h3>
        <div class="text-[10px] text-slate-400 mt-1 flex items-center justify-between">
            <span>والیوم: Rs. <?= number_format($trxStats['buy_volume'] + $trxStats['sell_volume']) ?></span>
            <span class="text-teal-400 font-bold"><?= $trxStats['count'] ?> ٹرانزیکشنز</span>
        </div>
        <div class="absolute bottom-0 left-0 right-0 h-1 bg-gradient-to-r from-teal-500 to-emerald-500"></div>
    </div>

    <!-- Shop Expenses -->
    <div class="bg-slate-900 border border-slate-800 p-4 rounded-2xl shadow-lg relative overflow-hidden">
        <span class="text-[11px] font-bold text-slate-400 block mb-1">دکان کے اخراجات (Costs)</span>
        <h3 class="text-xl sm:text-2xl font-black text-rose-400 font-mono">
            Rs. <?= number_format($trxStats['expenses']) ?>
        </h3>
        <div class="text-[10px] text-slate-400 mt-1 flex items-center justify-between">
            <span>کرایہ، بل، ملازمین</span>
            <span class="text-rose-400 font-bold">منہا شدہ</span>
        </div>
        <div class="absolute bottom-0 left-0 right-0 h-1 bg-gradient-to-r from-rose-500 to-amber-500"></div>
    </div>

    <!-- In-Stock Assets -->
    <div class="bg-slate-900 border border-slate-800 p-4 rounded-2xl shadow-lg relative overflow-hidden">
        <span class="text-[11px] font-bold text-slate-400 block mb-1">اسٹاک اثاثہ جات (Inventory)</span>
        <h3 class="text-xl sm:text-2xl font-black text-amber-400 font-mono">
            Rs. <?= number_format($stockVal['cost_val']) ?>
        </h3>
        <div class="text-[10px] text-slate-400 mt-1 flex items-center justify-between">
            <span><?= $stockVal['total_units'] ?> کل دانے</span>
            <span class="text-emerald-400 font-bold">+Rs. <?= number_format($potentialStockProfit) ?> متوقع</span>
        </div>
        <div class="absolute bottom-0 left-0 right-0 h-1 bg-gradient-to-r from-amber-500 to-yellow-500"></div>
    </div>

    <!-- Khata Udhar -->
    <div class="bg-slate-900 border border-slate-800 p-4 rounded-2xl shadow-lg relative overflow-hidden">
        <span class="text-[11px] font-bold text-slate-400 block mb-1">مارکیٹ ادھار (Khata Due)</span>
        <h3 class="text-xl sm:text-2xl font-black text-indigo-400 font-mono">
            Rs. <?= number_format($custStats['total_udhar']) ?>
        </h3>
        <div class="text-[10px] text-slate-400 mt-1 flex items-center justify-between">
            <span><?= $custStats['debtors_count'] ?> مقروض گاہک</span>
            <span class="text-amber-400 font-bold">سپلائر: Rs. <?= number_format($suppStats['total_supplier_due']) ?></span>
        </div>
        <div class="absolute bottom-0 left-0 right-0 h-1 bg-gradient-to-r from-indigo-500 to-purple-500"></div>
    </div>

</div>

<!-- 2-Column Section: P&L Statement on Left, Market Khata & Wallets on Right -->
<div class="grid grid-cols-1 lg:grid-cols-12 gap-6 mb-6">
    
    <!-- Left Column: Tabular Income & Expenditure Statement (7 Cols) -->
    <div class="lg:col-span-7 bg-slate-900 border border-slate-800 rounded-2xl p-5 shadow-lg">
        <div class="flex items-center justify-between pb-3 border-b border-slate-800 mb-4">
            <h3 class="font-bold text-white text-sm flex items-center gap-2">
                <i data-lucide="file-spreadsheet" class="w-4 h-4 text-emerald-400"></i>
                <span>ماہانہ نفع و نقصان کا باضابطہ گوشوارہ (Profit & Loss Statement)</span>
            </h3>
            <span class="text-xs font-mono text-emerald-400 font-bold"><?= htmlspecialchars($fromDate) ?> تا <?= htmlspecialchars($toDate) ?></span>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-right text-xs">
                <thead>
                    <tr class="text-slate-400 border-b border-slate-800">
                        <th class="py-2.5">مد / تفصیل (Financial Item)</th>
                        <th class="py-2.5 text-center">حوالہ</th>
                        <th class="py-2.5 text-left">رقم (PKR)</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-800/60 font-sans">
                    <tr class="bg-slate-950/40 font-bold text-cyan-400">
                        <td colspan="3" class="py-2">الف: پی او ایس سیلز آمدن</td>
                    </tr>
                    <tr>
                        <td class="py-2.5 pr-4 text-slate-300">کل پروڈکٹس فروخت رقم (Gross Sales)</td>
                        <td class="py-2.5 text-center text-slate-400"><?= $salesStats['count'] ?> انوائسز</td>
                        <td class="py-2.5 text-left font-mono font-bold text-white">Rs. <?= number_format($salesStats['total_sales']) ?></td>
                    </tr>
                    <tr>
                        <td class="py-2.5 pr-4 text-slate-400">منہا کریں: سامان کی خرید لاگت (COGS)</td>
                        <td class="py-2.5 text-center text-slate-400">خرید لاگت</td>
                        <td class="py-2.5 text-left font-mono text-rose-400">- Rs. <?= number_format($cogs) ?></td>
                    </tr>
                    <tr class="bg-slate-950/80 font-bold">
                        <td class="py-2.5 text-emerald-400">پروڈکٹس خام منافع (POS Gross Profit)</td>
                        <td class="py-2.5 text-center text-emerald-400">مارجن</td>
                        <td class="py-2.5 text-left font-mono font-bold text-emerald-400">Rs. <?= number_format($salesStats['total_profit']) ?></td>
                    </tr>

                    <tr class="bg-slate-950/40 font-bold text-teal-400">
                        <td colspan="3" class="py-2">ب: سروسز و کمیشن آمدن</td>
                    </tr>
                    <tr>
                        <td class="py-2.5 pr-4 text-slate-300">ایزی پیسہ، جاز کیش و آن لائن ٹرانسفر فیس</td>
                        <td class="py-2.5 text-center text-slate-400"><?= $trxStats['count'] ?> ٹرانزیکشنز</td>
                        <td class="py-2.5 text-left font-mono font-bold text-teal-400">Rs. <?= number_format($trxStats['ep_profit']) ?></td>
                    </tr>

                    <tr class="bg-slate-950/40 font-bold text-rose-400">
                        <td colspan="3" class="py-2">ج: دکان کے اخراجات</td>
                    </tr>
                    <tr>
                        <td class="py-2.5 pr-4 text-rose-400">کرایہ، بجلی، تنخواہ و متفرق اخراجات</td>
                        <td class="py-2.5 text-center text-slate-400">دکان خرچ</td>
                        <td class="py-2.5 text-left font-mono font-bold text-rose-400">- Rs. <?= number_format($trxStats['expenses']) ?></td>
                    </tr>

                    <tr class="bg-emerald-500/10 font-black text-sm border-t-2 border-emerald-500">
                        <td class="py-3 text-white">خالص کاروباری منافع (NET PROFIT)</td>
                        <td class="py-3 text-center text-emerald-400 font-mono"><?= $netMargin ?>% Margin</td>
                        <td class="py-3 text-left font-mono text-base <?= $netIncome >= 0 ? 'text-emerald-400' : 'text-rose-400' ?>">
                            Rs. <?= number_format($netIncome) ?>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Right Column: Market Khata & Channel Distribution (5 Cols) -->
    <div class="lg:col-span-5 space-y-6">
        
        <!-- Khata Position Box -->
        <div class="bg-slate-900 border border-slate-800 rounded-2xl p-5 shadow-lg">
            <h3 class="font-bold text-white text-sm flex items-center gap-2 pb-3 border-b border-slate-800 mb-4">
                <i data-lucide="users" class="w-4 h-4 text-rose-400"></i>
                <span>کھاتہ ادھار کی موجودہ صورتحال (Khata Audit)</span>
            </h3>
            <div class="grid grid-cols-2 gap-3">
                <div class="p-3.5 bg-slate-950 rounded-xl border border-slate-800">
                    <span class="text-[11px] text-slate-400 block font-medium">گاہکوں کے ذمے ادھار</span>
                    <span class="text-lg font-black font-mono text-rose-400 mt-1 block">Rs. <?= number_format($custStats['total_udhar']) ?></span>
                    <span class="text-[10px] text-slate-500"><?= $custStats['debtors_count'] ?> گاہکوں سے وصولی باقی</span>
                </div>
                <div class="p-3.5 bg-slate-950 rounded-xl border border-slate-800">
                    <span class="text-[11px] text-slate-400 block font-medium">سپلائرز کے بقایا جات</span>
                    <span class="text-lg font-black font-mono text-amber-400 mt-1 block">Rs. <?= number_format($suppStats['total_supplier_due']) ?></span>
                    <span class="text-[10px] text-slate-500">کمپنیوں کو ادا کرنے ہیں</span>
                </div>
            </div>
        </div>

        <!-- Channel Performance Box -->
        <div class="bg-slate-900 border border-slate-800 rounded-2xl p-5 shadow-lg">
            <h3 class="font-bold text-white text-sm flex items-center gap-2 pb-3 border-b border-slate-800 mb-3">
                <i data-lucide="layers" class="w-4 h-4 text-cyan-400"></i>
                <span>والٹس و ادائیگی چینلز (Payment Channels)</span>
            </h3>
            <div class="space-y-2">
                <?php if(empty($channels)): ?>
                    <p class="text-xs text-slate-500 py-3">اس مدت میں کوئی چینل ٹرانزیکشن موجود نہیں</p>
                <?php else: foreach($channels as $ch): ?>
                    <div class="flex items-center justify-between p-2.5 bg-slate-950 rounded-xl border border-slate-800 text-xs">
                        <div>
                            <span class="font-bold text-white"><?= htmlspecialchars($ch['payment_method'] ?? 'EASYPAISA') ?></span>
                            <span class="text-[10px] text-slate-400 block"><?= $ch['count'] ?> ٹرانزیکشنز</span>
                        </div>
                        <div class="text-right">
                            <span class="font-mono text-slate-300 font-bold block">Rs. <?= number_format($ch['volume']) ?></span>
                            <span class="text-[10px] text-emerald-400 font-bold">+Rs. <?= number_format($ch['profit']) ?> کمیشن</span>
                        </div>
                    </div>
                <?php endforeach; endif; ?>
            </div>
        </div>

    </div>

</div>

<!-- Top Selling Products Ranking -->
<div class="bg-slate-900 border border-slate-800 rounded-2xl p-5 shadow-lg mb-6">
    <h3 class="text-sm font-bold text-white flex items-center gap-2 pb-3 border-b border-slate-800 mb-4">
        <i data-lucide="award" class="w-5 h-5 text-amber-400"></i>
        <span>سب سے زیادہ فروخت ہونے والے پراڈکٹس (Top Selling Products Ranking)</span>
    </h3>
    <div class="overflow-x-auto">
        <table class="w-full text-right text-xs">
            <thead>
                <tr class="text-slate-400 border-b border-slate-800">
                    <th class="py-2.5">#</th>
                    <th class="py-2.5">پراڈکٹ نام</th>
                    <th class="py-2.5">کیٹیگری</th>
                    <th class="py-2.5 text-center">تعداد فروخت</th>
                    <th class="py-2.5">کل فروخت رقم (Revenue)</th>
                    <th class="py-2.5 text-emerald-400">حاصل شدہ منافع</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-800/60 font-sans">
                <?php if(empty($topItems)): ?>
                    <tr><td colspan="6" class="py-6 text-center text-slate-500">اس مدت میں کوئی آئٹم فروخت نہیں ہوا</td></tr>
                <?php else: 
                    $idx = 1;
                    foreach($topItems as $name => $d): ?>
                    <tr class="hover:bg-slate-800/30 transition">
                        <td class="py-3 font-mono text-slate-400 font-bold"><?= $idx++ ?></td>
                        <td class="py-3 font-bold text-white"><?= htmlspecialchars($name) ?></td>
                        <td class="py-3 text-slate-400"><span class="px-2 py-0.5 rounded bg-slate-800 font-mono text-[10px]"><?= htmlspecialchars($d['category']) ?></span></td>
                        <td class="py-3 text-center font-mono font-black text-amber-400"><?= $d['qty'] ?> دانے</td>
                        <td class="py-3 font-mono font-bold text-slate-300">Rs. <?= number_format($d['revenue']) ?></td>
                        <td class="py-3 font-mono font-black text-emerald-400">Rs. <?= number_format($d['profit']) ?></td>
                    </tr>
                <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Print-Only Statement -->
<div class="print-only hidden p-8 bg-white text-black min-h-screen text-right" dir="rtl">
    <div class="text-center pb-4 border-b-2 border-black mb-6">
        <h1 class="text-2xl font-black mb-1">بلال موبائلز اینڈ ایزی پیسہ شاپ</h1>
        <p class="text-sm font-semibold">کاروباری آڈٹ اور نفع و نقصان گوشوارہ (Audit Report)</p>
        <p class="text-xs text-gray-600 mt-1">مدت: <strong><?= htmlspecialchars($fromDate) ?> تا <?= htmlspecialchars($toDate) ?></strong></p>
    </div>

    <table class="w-full text-right text-xs border border-collapse border-black mb-6">
        <thead>
            <tr class="bg-gray-100 border-b border-black font-bold">
                <th class="p-2.5 border border-black">مد / ہیڈ</th>
                <th class="p-2.5 border border-black text-left">رقم (PKR)</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td class="p-2.5 border border-black">کل پی او ایس فروخت رقم (Gross Sales)</td>
                <td class="p-2.5 border border-black text-left font-mono font-bold">Rs. <?= number_format($salesStats['total_sales']) ?></td>
            </tr>
            <tr>
                <td class="p-2.5 border border-black">فروخت شدہ سامان کی لاگت خرید (COGS)</td>
                <td class="p-2.5 border border-black text-left font-mono">Rs. <?= number_format($cogs) ?></td>
            </tr>
            <tr>
                <td class="p-2.5 border border-black font-bold text-emerald-700">پراڈکٹس فروخت سے خام منافع</td>
                <td class="p-2.5 border border-black text-left font-mono font-bold text-emerald-700">Rs. <?= number_format($salesStats['total_profit']) ?></td>
            </tr>
            <tr>
                <td class="p-2.5 border border-black font-bold text-teal-700">ایزی پیسہ و والٹس سروس فیس و کمیشن</td>
                <td class="p-2.5 border border-black text-left font-mono font-bold text-teal-700">Rs. <?= number_format($trxStats['ep_profit']) ?></td>
            </tr>
            <tr>
                <td class="p-2.5 border border-black text-red-600">دکان کے اخراجات (کرایہ، بل، ملازمین)</td>
                <td class="p-2.5 border border-black text-left font-mono text-red-600">Rs. <?= number_format($trxStats['expenses']) ?></td>
            </tr>
            <tr class="bg-gray-200 font-bold border-t-2 border-black">
                <td class="p-3 border border-black font-black text-sm">خالص ماہانہ بچت / منافع (Net Profit)</td>
                <td class="p-3 border border-black text-left font-mono font-black text-sm text-green-700">Rs. <?= number_format($netIncome) ?></td>
            </tr>
        </tbody>
    </table>

    <div class="flex justify-between items-center text-xs mt-16 pt-6 border-t border-gray-400">
        <div>دستخط دکاندار / اکاؤنٹنٹ: _________________________</div>
        <div>دستخط مینیجر / آنر: _________________________</div>
    </div>
</div>

<?php require_once __DIR__ . '/../backend/footer.php'; ?>
