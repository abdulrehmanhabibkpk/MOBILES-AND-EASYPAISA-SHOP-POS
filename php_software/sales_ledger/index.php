<?php
$pageTitle = 'سیلز اینڈ پرافٹ لیجر (Sales & Profit Ledger)';
$activeMenu = 'sales_ledger';
require_once __DIR__ . '/../backend/header.php';

$successMsg = '';
$errorMsg = '';

// ==========================================================
// 1. Handle Invoice Cancellation / Stock Return
// ==========================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'cancel_sale') {
    $saleId = trim($_POST['sale_id'] ?? '');
    $invoiceNo = trim($_POST['invoice_no'] ?? '');

    if (!empty($saleId)) {
        try {
            $pdo->beginTransaction();

            // Fetch sale record to get items
            $stmtGet = $pdo->prepare("SELECT * FROM product_sales WHERE id = :id OR invoice_no = :inv LIMIT 1");
            $stmtGet->execute([':id' => $saleId, ':inv' => $invoiceNo]);
            $saleRow = $stmtGet->fetch();

            if ($saleRow) {
                $items = json_decode($saleRow['items_json'] ?? '[]', true);
                $updateStockStmt = $pdo->prepare("UPDATE products SET stock = stock + :qty WHERE id = :pid");
                $getProductStmt = $pdo->prepare("SELECT units_json FROM products WHERE id = :pid");
                $updateUnitsStmt = $pdo->prepare("UPDATE products SET units_json = :units WHERE id = :pid");

                // Return each item to stock
                if (is_array($items)) {
                    foreach ($items as $item) {
                        $pid = $item['productId'] ?? ($item['id'] ?? null);
                        $qty = intval($item['quantity'] ?? 1);
                        $selectedUnitId = $item['selectedUnitId'] ?? null;
                        $selectedImei = $item['selectedImei1'] ?? ($item['imei'] ?? '');

                        if ($pid) {
                            $updateStockStmt->execute([':qty' => $qty, ':pid' => $pid]);

                            // Restore IMEI unit status to AVAILABLE if was marked SOLD
                            if (!empty($selectedUnitId) || !empty($selectedImei)) {
                                $getProductStmt->execute([':pid' => $pid]);
                                $pData = $getProductStmt->fetch();
                                if ($pData && !empty($pData['units_json'])) {
                                    $units = json_decode($pData['units_json'], true);
                                    if (is_array($units)) {
                                        $mod = false;
                                        foreach ($units as &$u) {
                                            if ((!empty($selectedUnitId) && isset($u['id']) && $u['id'] == $selectedUnitId) ||
                                                (!empty($selectedImei) && isset($u['imei1']) && $u['imei1'] == $selectedImei)) {
                                                $u['status'] = 'AVAILABLE';
                                                unset($u['soldInvoiceNo']);
                                                unset($u['soldDate']);
                                                $mod = true;
                                                break;
                                            }
                                        }
                                        if ($mod) {
                                            $updateUnitsStmt->execute([
                                                ':units' => json_encode($units, JSON_UNESCAPED_UNICODE),
                                                ':pid' => $pid
                                            ]);
                                        }
                                    }
                                }
                            }
                        }
                    }
                }

                // Delete linked transaction in transactions table
                $trxIdLike = 'trx-pos-' . $saleRow['id'];
                $pdo->prepare("DELETE FROM transactions WHERE id = :tid OR notes LIKE :noteLike")->execute([
                    ':tid' => $trxIdLike,
                    ':noteLike' => "%{$saleRow['invoice_no']}%"
                ]);

                // Delete sale record
                $stmtDel = $pdo->prepare("DELETE FROM product_sales WHERE id = :id");
                $stmtDel->execute([':id' => $saleRow['id']]);

                $pdo->commit();
                $successMsg = "انوائس #{$saleRow['invoice_no']} کامیابی سے منسوخ کر دی گئی ہے اور تمام پروڈکٹس کا اسٹاک واپس بحال ہو چکا ہے!";
            } else {
                $pdo->rollBack();
                $errorMsg = 'مطلوبہ انوائس نہیں مل سکی۔';
            }
        } catch (Exception $e) {
            $pdo->rollBack();
            $errorMsg = 'منسوخی میں خرابی: ' . $e->getMessage();
        }
    }
}

// ==========================================================
// 2. CSV Export Handling
// ==========================================================
if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    $expStartDate = $_GET['start_date'] ?? date('Y-m-d', strtotime('-30 days'));
    $expEndDate = $_GET['end_date'] ?? date('Y-m-d');
    $expPayment = $_GET['payment_method'] ?? 'ALL';
    $expSearch = trim($_GET['q'] ?? '');

    $expSql = "SELECT * FROM product_sales WHERE 1=1";
    $expParams = [];

    if (!empty($expStartDate)) {
        $expSql .= " AND date >= :sdate";
        $expParams[':sdate'] = $expStartDate;
    }
    if (!empty($expEndDate)) {
        $expSql .= " AND date <= :edate";
        $expParams[':edate'] = $expEndDate;
    }
    if ($expPayment !== 'ALL' && !empty($expPayment)) {
        $expSql .= " AND payment_method = :pm";
        $expParams[':pm'] = $expPayment;
    }
    if (!empty($expSearch)) {
        $expSql .= " AND (invoice_no LIKE :q OR customer_name LIKE :q OR customer_phone LIKE :q OR items_json LIKE :q)";
        $expParams[':q'] = "%{$expSearch}%";
    }
    $expSql .= " ORDER BY created_at DESC";

    $stmtExp = $pdo->prepare($expSql);
    $stmtExp->execute($expParams);
    $expRows = $stmtExp->fetchAll();

    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="Sales_Profit_Ledger_' . date('Ymd_His') . '.csv"');
    $output = fopen('php://output', 'w');
    // Add UTF-8 BOM
    fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));

    fputcsv($output, ['انوائس نمبر', 'تاریخ', 'وقت', 'گاہک کا نام', 'فون نمبر', 'ادائیگی کا طریقہ', 'کل لاگت', 'کل بل', 'رعایت', 'خالص فروخت', 'منافع', 'تفصیل اشیاء']);
    foreach ($expRows as $r) {
        $itms = json_decode($r['items_json'] ?? '[]', true);
        $itmStrArr = [];
        if (is_array($itms)) {
            foreach ($itms as $it) {
                $nm = $it['productName'] ?? ($it['name'] ?? 'Item');
                $qty = $it['quantity'] ?? 1;
                $sp = $it['totalSalePrice'] ?? ($it['unitSalePrice'] ?? 0);
                $imei = !empty($it['selectedImei1']) ? " [IMEI: {$it['selectedImei1']}]" : '';
                $itmStrArr[] = "{$nm} (x{$qty}) {$imei} = Rs. {$sp}";
            }
        }
        $itmStr = implode('; ', $itmStrArr);

        fputcsv($output, [
            $r['invoice_no'],
            $r['date'],
            $r['time'],
            $r['customer_name'],
            $r['customer_phone'],
            $r['payment_method'],
            $r['total_purchase_cost'],
            $r['total_amount'],
            $r['discount'],
            $r['net_amount'],
            $r['profit'],
            $itmStr
        ]);
    }
    fclose($output);
    exit;
}

// ==========================================================
// 3. Filters & Date Range Computation
// ==========================================================
$preset = $_GET['preset'] ?? '';
$today = date('Y-m-d');
$yesterday = date('Y-m-d', strtotime('-1 day'));

// Default start & end date
if ($preset === 'today') {
    $startDate = $today;
    $endDate = $today;
} elseif ($preset === 'yesterday') {
    $startDate = $yesterday;
    $endDate = $yesterday;
} elseif ($preset === 'week') {
    $startDate = date('Y-m-d', strtotime('-7 days'));
    $endDate = $today;
} elseif ($preset === 'month') {
    $startDate = date('Y-m-01');
    $endDate = $today;
} elseif ($preset === 'all') {
    $startDate = '';
    $endDate = '';
} else {
    // If not preset, check GET or default to this month (1st of month to today)
    $startDate = $_GET['start_date'] ?? date('Y-m-01');
    $endDate = $_GET['end_date'] ?? $today;
}

$paymentFilter = $_GET['payment_method'] ?? 'ALL';
$search = trim($_GET['q'] ?? '');

$sql = "SELECT * FROM product_sales WHERE 1=1";
$params = [];

if (!empty($startDate)) {
    $sql .= " AND date >= :sdate";
    $params[':sdate'] = $startDate;
}
if (!empty($endDate)) {
    $sql .= " AND date <= :edate";
    $params[':edate'] = $endDate;
}
if ($paymentFilter !== 'ALL' && !empty($paymentFilter)) {
    $sql .= " AND payment_method = :pm";
    $params[':pm'] = $paymentFilter;
}
if (!empty($search)) {
    $sql .= " AND (invoice_no LIKE :q OR customer_name LIKE :q OR customer_phone LIKE :q OR items_json LIKE :q)";
    $params[':q'] = "%{$search}%";
}

$sql .= " ORDER BY created_at DESC";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$sales = $stmt->fetchAll();

// ==========================================================
// 4. Totals & Metrics Calculation
// ==========================================================
$totalSalesRevenue = 0;
$totalCost = 0;
$totalNetProfit = 0;
$totalDiscounts = 0;
$totalCreditDue = 0;
$totalBillsCount = count($sales);

// Payment breakdown
$payMethodCounts = [
    'CASH' => 0,
    'EASYPAISA' => 0,
    'JAZZCASH' => 0,
    'BANK' => 0,
    'CREDIT' => 0
];

foreach ($sales as $s) {
    $net = floatval($s['net_amount']);
    $cost = floatval($s['total_purchase_cost']);
    $profit = floatval($s['profit']);
    $discount = floatval($s['discount']);
    $due = floatval($s['due_amount']);

    $totalSalesRevenue += $net;
    $totalCost += $cost;
    $totalNetProfit += $profit;
    $totalDiscounts += $discount;
    $totalCreditDue += $due;

    $pm = strtoupper($s['payment_method'] ?? 'CASH');
    if (isset($payMethodCounts[$pm])) {
        $payMethodCounts[$pm] += $net;
    }
}

$overallProfitMargin = $totalSalesRevenue > 0 ? round(($totalNetProfit / $totalSalesRevenue) * 100, 1) : 0;
?>

<!-- Print Styles for Invoices and Reports -->
<style>
@media print {
    body {
        background-color: #fff !important;
        color: #000 !important;
        font-family: monospace !important;
    }
    .no-print {
        display: none !important;
    }
    .print-only {
        display: block !important;
    }
    .print-receipt-modal {
        position: static !important;
        width: 80mm !important;
        margin: 0 auto !important;
        box-shadow: none !important;
        border: none !important;
    }
}
</style>

<!-- Alert Banners -->
<?php if (!empty($successMsg)): ?>
    <div class="mb-4 p-4 rounded-2xl bg-emerald-500/10 border border-emerald-500/30 text-emerald-300 flex items-center justify-between shadow-lg no-print">
        <div class="flex items-center gap-3">
            <div class="p-2 rounded-xl bg-emerald-500/20 text-emerald-400">
                <i data-lucide="check-circle-2" class="w-6 h-6"></i>
            </div>
            <div>
                <h4 class="font-bold text-sm text-white">کامیابی!</h4>
                <p class="text-xs text-emerald-300/80"><?= htmlspecialchars($successMsg) ?></p>
            </div>
        </div>
        <button onclick="this.parentElement.remove()" class="text-slate-400 hover:text-white text-xs p-1">
            <i data-lucide="x" class="w-4 h-4"></i>
        </button>
    </div>
<?php endif; ?>

<?php if (!empty($errorMsg)): ?>
    <div class="mb-4 p-4 rounded-2xl bg-rose-500/10 border border-rose-500/30 text-rose-300 flex items-center gap-3 shadow-lg no-print">
        <div class="p-2 rounded-xl bg-rose-500/20 text-rose-400">
            <i data-lucide="alert-triangle" class="w-6 h-6"></i>
        </div>
        <div>
            <h4 class="font-bold text-sm text-white">خرابی پیش آگئی</h4>
            <p class="text-xs text-rose-300/80"><?= htmlspecialchars($errorMsg) ?></p>
        </div>
    </div>
<?php endif; ?>

<!-- ==========================================================
     Top Header & Action Buttons
     ========================================================== -->
<div class="bg-slate-900 border border-slate-800 p-5 rounded-3xl mb-6 shadow-xl no-print space-y-4">
    <div class="flex flex-col lg:flex-row items-stretch lg:items-center justify-between gap-4">
        <!-- Title & Icon -->
        <div class="flex items-center gap-3.5">
            <div class="w-12 h-12 rounded-2xl bg-gradient-to-br from-emerald-500/20 to-teal-500/10 border border-emerald-500/30 text-emerald-400 flex items-center justify-center font-bold shrink-0 shadow-inner">
                <i data-lucide="receipt" class="w-6 h-6"></i>
            </div>
            <div>
                <h1 class="text-lg sm:text-xl font-black text-white flex items-center gap-2">
                    <span>سیلز اینڈ پرافٹ لیجر</span>
                    <span class="text-xs font-mono font-bold px-2 py-0.5 rounded-full bg-emerald-500/10 text-emerald-400 border border-emerald-500/20">
                        <?= $totalBillsCount ?> انوائسز
                    </span>
                </h1>
                <p class="text-xs text-slate-400 mt-0.5">
                    تمام فروخت شدہ بلوں، خریداری لاگت، خالص منافع، بقایا ادھار اور انوائس پرنٹنگ کا مکمل حساب کتاب
                </p>
            </div>
        </div>

        <!-- Quick Action Buttons -->
        <div class="flex flex-wrap items-center gap-2 shrink-0">
            <!-- New Sale POS Link -->
            <a href="../pos/index.php" class="px-4 py-2.5 bg-emerald-600 hover:bg-emerald-500 text-white rounded-xl text-xs font-bold flex items-center gap-2 shadow-lg transition-all active:scale-95">
                <i data-lucide="plus-circle" class="w-4 h-4"></i>
                <span>نیا سیل بل کاٹیں (POS)</span>
            </a>

            <!-- Export CSV Button -->
            <?php
            $exportUrl = "index.php?export=csv&start_date=" . urlencode($startDate) . "&end_date=" . urlencode($endDate) . "&payment_method=" . urlencode($paymentFilter) . "&q=" . urlencode($search);
            ?>
            <a href="<?= $exportUrl ?>" class="px-4 py-2.5 bg-slate-800 hover:bg-slate-700 text-slate-200 border border-slate-700 rounded-xl text-xs font-bold flex items-center gap-2 transition-all shadow">
                <i data-lucide="download" class="w-4 h-4 text-cyan-400"></i>
                <span>ایکسل / CSV ڈاؤن لوڈ</span>
            </a>

            <!-- Print Page Table Button -->
            <button type="button" onclick="window.print()" class="px-3.5 py-2.5 bg-slate-800 hover:bg-slate-700 text-slate-300 border border-slate-700 rounded-xl text-xs font-bold flex items-center gap-1.5 transition-all">
                <i data-lucide="printer" class="w-4 h-4 text-emerald-400"></i>
                <span>پرنٹ رپورٹ</span>
            </button>
        </div>
    </div>

    <!-- Quick Preset Date Filter Tabs -->
    <div class="flex items-center gap-2 overflow-x-auto pb-1 text-xs border-t border-slate-800/80 pt-3">
        <span class="text-slate-500 font-bold shrink-0 ml-1">فوری تاریخ:</span>
        <a href="index.php?preset=today" class="px-3 py-1.5 rounded-xl font-bold shrink-0 transition-all <?= ($preset === 'today' || ($startDate === $today && $endDate === $today)) ? 'bg-emerald-600 text-white shadow' : 'bg-slate-950 text-slate-400 hover:bg-slate-800 border border-slate-800' ?>">
            آج (Today)
        </a>
        <a href="index.php?preset=yesterday" class="px-3 py-1.5 rounded-xl font-bold shrink-0 transition-all <?= ($preset === 'yesterday') ? 'bg-emerald-600 text-white shadow' : 'bg-slate-950 text-slate-400 hover:bg-slate-800 border border-slate-800' ?>">
            گزرا کل (Yesterday)
        </a>
        <a href="index.php?preset=week" class="px-3 py-1.5 rounded-xl font-bold shrink-0 transition-all <?= ($preset === 'week') ? 'bg-emerald-600 text-white shadow' : 'bg-slate-950 text-slate-400 hover:bg-slate-800 border border-slate-800' ?>">
            آخری 7 دن (Last 7 Days)
        </a>
        <a href="index.php?preset=month" class="px-3 py-1.5 rounded-xl font-bold shrink-0 transition-all <?= ($preset === 'month' || ($startDate === date('Y-m-01') && $endDate === $today)) ? 'bg-emerald-600 text-white shadow' : 'bg-slate-950 text-slate-400 hover:bg-slate-800 border border-slate-800' ?>">
            رواں مہینہ (This Month)
        </a>
        <a href="index.php?preset=all" class="px-3 py-1.5 rounded-xl font-bold shrink-0 transition-all <?= ($preset === 'all' || (empty($startDate) && empty($endDate))) ? 'bg-emerald-600 text-white shadow' : 'bg-slate-950 text-slate-400 hover:bg-slate-800 border border-slate-800' ?>">
            تمام تاریخیں (All Time)
        </a>
    </div>

    <!-- Advanced Custom Filter Form -->
    <form method="GET" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-12 gap-3 pt-2">
        <!-- Start Date (3 cols) -->
        <div class="lg:col-span-3">
            <label class="text-[11px] font-bold text-slate-400 block mb-1">از تاریخ (Start Date)</label>
            <input type="date" name="start_date" value="<?= htmlspecialchars($startDate) ?>" class="w-full bg-slate-950 border border-slate-700 text-white px-3 py-2 rounded-xl text-xs font-mono focus:border-emerald-500 focus:outline-none">
        </div>

        <!-- End Date (3 cols) -->
        <div class="lg:col-span-3">
            <label class="text-[11px] font-bold text-slate-400 block mb-1">تا تاریخ (End Date)</label>
            <input type="date" name="end_date" value="<?= htmlspecialchars($endDate) ?>" class="w-full bg-slate-950 border border-slate-700 text-white px-3 py-2 rounded-xl text-xs font-mono focus:border-emerald-500 focus:outline-none">
        </div>

        <!-- Payment Method Filter (2 cols) -->
        <div class="lg:col-span-2">
            <label class="text-[11px] font-bold text-slate-400 block mb-1">ادائیگی کا طریقہ</label>
            <select name="payment_method" class="w-full bg-slate-950 border border-slate-700 text-white px-3 py-2 rounded-xl text-xs focus:border-emerald-500 focus:outline-none font-medium">
                <option value="ALL" <?= $paymentFilter === 'ALL' ? 'selected' : '' ?>>تمام طریقے</option>
                <option value="CASH" <?= $paymentFilter === 'CASH' ? 'selected' : '' ?>>💵 نقد کیش (CASH)</option>
                <option value="EASYPAISA" <?= $paymentFilter === 'EASYPAISA' ? 'selected' : '' ?>>🟢 ایزی پیسہ</option>
                <option value="JAZZCASH" <?= $paymentFilter === 'JAZZCASH' ? 'selected' : '' ?>>🔴 جاز کیش</option>
                <option value="BANK" <?= $paymentFilter === 'BANK' ? 'selected' : '' ?>>🏦 بینک ٹرانسفر</option>
                <option value="CREDIT" <?= $paymentFilter === 'CREDIT' ? 'selected' : '' ?>>📝 ادھار کھاتہ (Khata)</option>
            </select>
        </div>

        <!-- Search Bar (3 cols) -->
        <div class="lg:col-span-3">
            <label class="text-[11px] font-bold text-slate-400 block mb-1">تلاش (انوائس / کسٹمر / آئٹم / IMEI)</label>
            <div class="relative">
                <input type="text" name="q" value="<?= htmlspecialchars($search) ?>" placeholder="INV-123، بلال، سام سنگ، IMEI..." class="w-full bg-slate-950 border border-slate-700 text-white pr-8 pl-3 py-2 rounded-xl text-xs focus:border-emerald-500 focus:outline-none">
                <i data-lucide="search" class="w-3.5 h-3.5 absolute right-2.5 top-1/2 -translate-y-1/2 text-slate-500 pointer-events-none"></i>
            </div>
        </div>

        <!-- Filter Submit (1 col) -->
        <div class="lg:col-span-1 flex items-end">
            <button type="submit" class="w-full py-2 bg-emerald-600 hover:bg-emerald-500 text-white font-bold text-xs rounded-xl transition-all shadow flex items-center justify-center gap-1">
                <span>فلٹر</span>
            </button>
        </div>
    </form>
</div>

<!-- ==========================================================
     Financial Summary KPIs Cards (6 Key Metrics)
     ========================================================== -->
<div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-6 gap-3 sm:gap-4 mb-6">
    
    <!-- 1. Total Net Revenue -->
    <div class="bg-slate-900 border border-slate-800 p-4 rounded-2xl shadow-md hover:border-emerald-500/40 transition-colors">
        <div class="flex items-center justify-between text-slate-400 text-xs font-bold mb-1">
            <span>کل خالص فروخت</span>
            <div class="p-1.5 rounded-lg bg-emerald-500/10 text-emerald-400">
                <i data-lucide="dollar-sign" class="w-4 h-4"></i>
            </div>
        </div>
        <h3 class="text-base sm:text-xl font-black text-white mt-1 font-mono">Rs. <?= number_format($totalSalesRevenue) ?></h3>
        <p class="text-[10px] text-slate-500 mt-1"><?= $totalBillsCount ?> کل کاٹے گئے بل</p>
    </div>

    <!-- 2. Total Net Profit -->
    <div class="bg-slate-900 border border-slate-800 p-4 rounded-2xl shadow-md hover:border-teal-500/40 transition-colors">
        <div class="flex items-center justify-between text-slate-400 text-xs font-bold mb-1">
            <span>خالص منافع</span>
            <div class="p-1.5 rounded-lg bg-teal-500/10 text-teal-400">
                <i data-lucide="trending-up" class="w-4 h-4"></i>
            </div>
        </div>
        <h3 class="text-base sm:text-xl font-black text-teal-400 mt-1 font-mono">Rs. <?= number_format($totalNetProfit) ?></h3>
        <p class="text-[10px] text-teal-400/80 mt-1 font-bold">مارجن: <?= $overallProfitMargin ?>%</p>
    </div>

    <!-- 3. Purchase Cost of Goods Sold -->
    <div class="bg-slate-900 border border-slate-800 p-4 rounded-2xl shadow-md hover:border-cyan-500/40 transition-colors">
        <div class="flex items-center justify-between text-slate-400 text-xs font-bold mb-1">
            <span>خریداری لاگت</span>
            <div class="p-1.5 rounded-lg bg-cyan-500/10 text-cyan-400">
                <i data-lucide="shopping-bag" class="w-4 h-4"></i>
            </div>
        </div>
        <h3 class="text-base sm:text-xl font-black text-slate-300 mt-1 font-mono">Rs. <?= number_format($totalCost) ?></h3>
        <p class="text-[10px] text-slate-500 mt-1">خریداری قیمت برائے سامان</p>
    </div>

    <!-- 4. Total Invoices Count -->
    <div class="bg-slate-900 border border-slate-800 p-4 rounded-2xl shadow-md hover:border-purple-500/40 transition-colors">
        <div class="flex items-center justify-between text-slate-400 text-xs font-bold mb-1">
            <span>کل انوائسز</span>
            <div class="p-1.5 rounded-lg bg-purple-500/10 text-purple-400">
                <i data-lucide="file-text" class="w-4 h-4"></i>
            </div>
        </div>
        <h3 class="text-base sm:text-xl font-black text-purple-400 mt-1 font-mono"><?= $totalBillsCount ?></h3>
        <p class="text-[10px] text-slate-500 mt-1">منتخب مدت کے اندر</p>
    </div>

    <!-- 5. Total Discounts Given -->
    <div class="bg-slate-900 border border-slate-800 p-4 rounded-2xl shadow-md hover:border-rose-500/40 transition-colors">
        <div class="flex items-center justify-between text-slate-400 text-xs font-bold mb-1">
            <span>دی گئی رعایت</span>
            <div class="p-1.5 rounded-lg bg-rose-500/10 text-rose-400">
                <i data-lucide="tag" class="w-4 h-4"></i>
            </div>
        </div>
        <h3 class="text-base sm:text-xl font-black text-rose-400 mt-1 font-mono">Rs. <?= number_format($totalDiscounts) ?></h3>
        <p class="text-[10px] text-slate-500 mt-1">ڈسکاؤنٹ رعایت کی رقم</p>
    </div>

    <!-- 6. Unpaid Credit / Due Khata -->
    <div class="bg-slate-900 border border-slate-800 p-4 rounded-2xl shadow-md hover:border-amber-500/40 transition-colors">
        <div class="flex items-center justify-between text-slate-400 text-xs font-bold mb-1">
            <span>بقایا ادھار (کھاتہ)</span>
            <div class="p-1.5 rounded-lg bg-amber-500/10 text-amber-400">
                <i data-lucide="clock" class="w-4 h-4"></i>
            </div>
        </div>
        <h3 class="text-base sm:text-xl font-black <?= $totalCreditDue > 0 ? 'text-amber-400' : 'text-slate-400' ?> mt-1 font-mono">Rs. <?= number_format($totalCreditDue) ?></h3>
        <p class="text-[10px] text-slate-500 mt-1">گاہکوں کے ذمے ادھار</p>
    </div>

</div>

<!-- ==========================================================
     Invoices Ledger Table Section
     ========================================================== -->
<div class="bg-slate-900 border border-slate-800 rounded-3xl overflow-hidden shadow-xl">
    
    <!-- Table Header Info Bar -->
    <div class="p-4 sm:p-5 border-b border-slate-800 bg-slate-950/60 flex flex-col sm:flex-row items-start sm:items-center justify-between gap-3">
        <div>
            <h3 class="font-extrabold text-white text-sm sm:text-base flex items-center gap-2">
                <span>فروخت شدہ انوائسز کی تفصیل (Sales Invoices Log)</span>
                <span class="text-xs px-2.5 py-0.5 rounded-full bg-slate-800 text-slate-300 font-mono"><?= $totalBillsCount ?> ریکارڈز</span>
            </h3>
            <p class="text-xs text-slate-400 mt-0.5">
                مدت: <span class="font-mono text-emerald-400"><?= !empty($startDate) ? $startDate : 'آغاز' ?></span> تا <span class="font-mono text-emerald-400"><?= !empty($endDate) ? $endDate : 'آج' ?></span>
                <?php if ($paymentFilter !== 'ALL'): ?> | طریقہ: <span class="font-bold text-cyan-400"><?= htmlspecialchars($paymentFilter) ?></span><?php endif; ?>
            </p>
        </div>

        <div class="flex items-center gap-2 text-xs">
            <span class="text-slate-400 hidden sm:inline">کسی بھی انوائس کو دیکھنے، تھرمل پرنٹ کرنے یا واٹس ایپ بھیجنے کیلئے ایکشنز استعمال کریں</span>
        </div>
    </div>

    <!-- Table Container -->
    <?php if (empty($sales)): ?>
        <div class="p-16 text-center text-slate-400 space-y-3">
            <div class="w-16 h-16 rounded-full bg-slate-800 flex items-center justify-center mx-auto text-slate-600">
                <i data-lucide="receipt" class="w-8 h-8"></i>
            </div>
            <h4 class="text-base font-bold text-white">اس مدت میں کوئی انوائس نہیں ملی</h4>
            <p class="text-xs text-slate-500 max-w-md mx-auto">آپ نے جو تاریخ یا فلٹر منتخب کیا ہے اس میں کوئی ریکارڈ نہیں ہے۔ اوپر سے تاریخ تبدیل کریں یا تمام تاریخیں دیکھیں۔</p>
            <div class="pt-2">
                <a href="index.php?preset=all" class="px-4 py-2 bg-slate-800 hover:bg-slate-700 text-slate-200 text-xs font-bold rounded-xl border border-slate-700 inline-flex items-center gap-1.5">
                    <i data-lucide="rotate-ccw" class="w-3.5 h-3.5"></i>
                    <span>تمام ریکارڈز دیکھیں</span>
                </a>
            </div>
        </div>
    <?php else: ?>
        <div class="overflow-x-auto">
            <table class="w-full text-right text-xs">
                <thead class="bg-slate-950 text-slate-400 border-b border-slate-800 font-bold uppercase text-[11px]">
                    <tr>
                        <th class="p-3.5 pr-4 text-center">#</th>
                        <th class="p-3.5">انوائس نمبر</th>
                        <th class="p-3.5">تاریخ و وقت</th>
                        <th class="p-3.5">گاہک / کسٹمر</th>
                        <th class="p-3.5 min-w-[220px]">فروخت شدہ سامان</th>
                        <th class="p-3.5 text-center">ادائیگی طریقہ</th>
                        <th class="p-3.5 text-left font-mono">کل بل</th>
                        <th class="p-3.5 text-left font-mono">رعایت</th>
                        <th class="p-3.5 text-left font-mono">خالص رقم</th>
                        <th class="p-3.5 text-left font-mono">منافع</th>
                        <th class="p-3.5 text-center no-print min-w-[130px]">ایکشنز</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-800/60">
                    <?php foreach ($sales as $idx => $s): 
                        $items = json_decode($s['items_json'] ?? '[]', true);
                        if (!is_array($items)) $items = [];
                        $net = floatval($s['net_amount']);
                        $profit = floatval($s['profit']);
                        $isCredit = ($s['payment_method'] === 'CREDIT');

                        $saleJson = htmlspecialchars(json_encode([
                            'id' => $s['id'],
                            'invoiceNo' => $s['invoice_no'],
                            'customerName' => $s['customer_name'],
                            'customerPhone' => $s['customer_phone'] ?? '',
                            'date' => $s['date'],
                            'time' => $s['time'],
                            'paymentMethod' => $s['payment_method'],
                            'totalAmount' => floatval($s['total_amount']),
                            'discount' => floatval($s['discount']),
                            'netAmount' => $net,
                            'paidAmount' => floatval($s['paid_amount'] ?? $net),
                            'dueAmount' => floatval($s['due_amount'] ?? 0),
                            'profit' => $profit,
                            'totalCost' => floatval($s['total_purchase_cost']),
                            'notes' => $s['notes'] ?? '',
                            'items' => $items
                        ]), ENT_QUOTES, 'UTF-8');
                    ?>
                        <tr class="hover:bg-slate-800/40 transition-colors group">
                            <!-- Index -->
                            <td class="p-3.5 pr-4 text-center font-mono font-bold text-slate-500"><?= $idx + 1 ?></td>

                            <!-- Invoice No -->
                            <td class="p-3.5 font-mono font-black text-cyan-400">
                                <span class="hover:underline cursor-pointer" onclick="viewInvoiceModal(<?= $saleJson ?>)">
                                    <?= htmlspecialchars($s['invoice_no']) ?>
                                </span>
                            </td>

                            <!-- Date & Time -->
                            <td class="p-3.5 font-mono text-slate-300">
                                <div><?= htmlspecialchars($s['date']) ?></div>
                                <div class="text-[10px] text-slate-500"><?= htmlspecialchars($s['time']) ?></div>
                            </td>

                            <!-- Customer -->
                            <td class="p-3.5">
                                <div class="font-bold text-white"><?= htmlspecialchars($s['customer_name'] ?: 'واک ان کسٹمر') ?></div>
                                <?php if (!empty($s['customer_phone'])): ?>
                                    <div class="text-[10px] font-mono text-slate-400 mt-0.5 flex items-center gap-1">
                                        <i data-lucide="phone" class="w-3 h-3 text-slate-500"></i>
                                        <span><?= htmlspecialchars($s['customer_phone']) ?></span>
                                    </div>
                                <?php endif; ?>
                            </td>

                            <!-- Items Sold -->
                            <td class="p-3.5">
                                <div class="space-y-1 max-w-[280px]">
                                    <?php foreach (array_slice($items, 0, 3) as $it): 
                                        $pName = $it['productName'] ?? ($it['name'] ?? 'آئٹم');
                                        $qty = $it['quantity'] ?? 1;
                                        $imei = $it['selectedImei1'] ?? ($it['imei'] ?? '');
                                    ?>
                                        <div class="text-[11px] text-slate-300 flex items-center justify-between gap-2">
                                            <span class="truncate font-medium">• <?= htmlspecialchars($pName) ?> (x<?= $qty ?>)</span>
                                            <?php if (!empty($imei)): ?>
                                                <span class="text-[9px] font-mono text-cyan-400 bg-cyan-950/60 px-1 py-0.5 rounded shrink-0 border border-cyan-800/40">
                                                    IMEI: <?= htmlspecialchars($imei) ?>
                                                </span>
                                            <?php endif; ?>
                                        </div>
                                    <?php endforeach; ?>
                                    <?php if (count($items) > 3): ?>
                                        <span class="text-[10px] text-emerald-400 font-bold block cursor-pointer hover:underline" onclick="viewInvoiceModal(<?= $saleJson ?>)">
                                            + <?= count($items) - 3 ?> مزید اشیاء...
                                        </span>
                                    <?php endif; ?>
                                </div>
                            </td>

                            <!-- Payment Method Badge -->
                            <td class="p-3.5 text-center">
                                <?php if ($s['payment_method'] === 'CASH'): ?>
                                    <span class="px-2.5 py-0.5 rounded-full text-[10px] font-bold bg-emerald-950 text-emerald-300 border border-emerald-800">
                                        💵 کیش
                                    </span>
                                <?php elseif ($s['payment_method'] === 'EASYPAISA'): ?>
                                    <span class="px-2.5 py-0.5 rounded-full text-[10px] font-bold bg-green-950 text-green-300 border border-green-800">
                                        🟢 ایزی پیسہ
                                    </span>
                                <?php elseif ($s['payment_method'] === 'JAZZCASH'): ?>
                                    <span class="px-2.5 py-0.5 rounded-full text-[10px] font-bold bg-rose-950 text-rose-300 border border-rose-800">
                                        🔴 جاز کیش
                                    </span>
                                <?php elseif ($s['payment_method'] === 'BANK'): ?>
                                    <span class="px-2.5 py-0.5 rounded-full text-[10px] font-bold bg-blue-950 text-blue-300 border border-blue-800">
                                        🏦 بینک
                                    </span>
                                <?php elseif ($s['payment_method'] === 'CREDIT'): ?>
                                    <span class="px-2.5 py-0.5 rounded-full text-[10px] font-bold bg-amber-950 text-amber-300 border border-amber-800">
                                        📝 ادھار
                                    </span>
                                <?php else: ?>
                                    <span class="px-2.5 py-0.5 rounded-full text-[10px] font-bold bg-slate-800 text-slate-300 border border-slate-700">
                                        <?= htmlspecialchars($s['payment_method']) ?>
                                    </span>
                                <?php endif; ?>
                            </td>

                            <!-- Total Gross Amount -->
                            <td class="p-3.5 text-left font-mono text-slate-300">
                                Rs. <?= number_format($s['total_amount']) ?>
                            </td>

                            <!-- Discount -->
                            <td class="p-3.5 text-left font-mono <?= $s['discount'] > 0 ? 'text-rose-400 font-bold' : 'text-slate-500' ?>">
                                <?= $s['discount'] > 0 ? '- Rs. ' . number_format($s['discount']) : '-' ?>
                            </td>

                            <!-- Net Amount -->
                            <td class="p-3.5 text-left font-mono font-black text-white text-sm">
                                Rs. <?= number_format($net) ?>
                            </td>

                            <!-- Profit -->
                            <td class="p-3.5 text-left font-mono font-black text-emerald-400 text-sm">
                                + Rs. <?= number_format($profit) ?>
                            </td>

                            <!-- Actions -->
                            <td class="p-3.5 text-center no-print">
                                <div class="flex items-center justify-center gap-1.5">
                                    <!-- View Details Modal -->
                                    <button type="button" onclick="viewInvoiceModal(<?= $saleJson ?>)" title="انوائس تفصیلات و تھرمل رسید" 
                                            class="p-1.5 bg-slate-800 hover:bg-slate-700 text-cyan-400 hover:text-white rounded-lg border border-slate-700 transition-colors shadow">
                                        <i data-lucide="eye" class="w-3.5 h-3.5"></i>
                                    </button>

                                    <!-- Quick Print 80mm -->
                                    <button type="button" onclick="quickPrintThermal(<?= $saleJson ?>)" title="فوری تھرمل پرنٹ" 
                                            class="p-1.5 bg-slate-800 hover:bg-emerald-600 text-slate-300 hover:text-white rounded-lg border border-slate-700 transition-colors shadow">
                                        <i data-lucide="printer" class="w-3.5 h-3.5"></i>
                                    </button>

                                    <!-- WhatsApp Share -->
                                    <button type="button" onclick="shareWhatsAppInvoice(<?= $saleJson ?>)" title="واٹس ایپ پر رسید بھیجیں" 
                                            class="p-1.5 bg-slate-800 hover:bg-emerald-600 text-emerald-400 hover:text-white rounded-lg border border-slate-700 transition-colors shadow">
                                        <i data-lucide="share-2" class="w-3.5 h-3.5"></i>
                                    </button>

                                    <!-- Cancel / Delete Sale Button -->
                                    <button type="button" onclick="confirmCancelSale('<?= $s['id'] ?>', '<?= $s['invoice_no'] ?>')" title="بل منسوخ کریں اور اسٹاک واپس کریں" 
                                            class="p-1.5 bg-slate-800 hover:bg-rose-600 text-rose-400 hover:text-white rounded-lg border border-slate-700 transition-colors shadow">
                                        <i data-lucide="trash-2" class="w-3.5 h-3.5"></i>
                                    </button>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>

</div>

<!-- ==========================================================
     Modal 1: Detailed Invoice & Thermal Receipt Pop-up
     ========================================================== -->
<div id="invoiceDetailModal" class="fixed inset-0 z-50 bg-black/80 backdrop-blur-sm hidden items-center justify-center p-4 no-print">
    <div class="bg-slate-900 border border-slate-800 w-full max-w-2xl rounded-3xl overflow-hidden shadow-2xl flex flex-col max-h-[90vh]">
        
        <!-- Modal Top Bar -->
        <div class="p-4 border-b border-slate-800 flex items-center justify-between bg-slate-950">
            <div class="flex items-center gap-2.5">
                <div class="p-2 rounded-xl bg-emerald-500/10 text-emerald-400 border border-emerald-500/20">
                    <i data-lucide="receipt" class="w-5 h-5"></i>
                </div>
                <div>
                    <h3 class="font-black text-white text-base" id="modalInvoiceTitle">انوائس #INV-000000</h3>
                    <p class="text-xs text-slate-400" id="modalInvoiceSubtitle">تاریخ و وقت</p>
                </div>
            </div>
            <button onclick="closeInvoiceModal()" class="p-2 text-slate-400 hover:text-white rounded-xl hover:bg-slate-800 transition-colors">
                <i data-lucide="x" class="w-5 h-5"></i>
            </button>
        </div>

        <!-- Modal Body (Thermal Receipt Style Preview) -->
        <div class="p-6 overflow-y-auto space-y-4">
            
            <!-- Receipt Card Container -->
            <div class="bg-white text-black p-5 rounded-2xl shadow-md font-mono text-xs max-w-[360px] mx-auto border border-slate-300" id="printableReceiptBox">
                <!-- Shop Header -->
                <div class="text-center pb-3 border-b border-dashed border-gray-400">
                    <h2 class="text-base font-black uppercase text-gray-900"><?= htmlspecialchars($shopName) ?></h2>
                    <p class="text-[11px] text-gray-600 mt-0.5"><?= htmlspecialchars($settings['address'] ?? 'Main Market, Pakistan') ?></p>
                    <p class="text-[11px] text-gray-600">📞 <?= htmlspecialchars($settings['phone'] ?? '0300-1234567') ?></p>
                    <div class="mt-2 text-[10px] bg-gray-100 py-1 rounded font-bold">
                        *** سیل انوائس رسید (SALE RECEIPT) ***
                    </div>
                </div>

                <!-- Invoice Meta -->
                <div class="py-2.5 border-b border-dashed border-gray-400 space-y-1 text-[11px]">
                    <div class="flex justify-between">
                        <span class="text-gray-500">انوائس #:</span>
                        <span class="font-bold text-gray-900" id="modalInvNoText">-</span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-gray-500">تاریخ و وقت:</span>
                        <span id="modalDateTimeText">-</span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-gray-500">گاہک / کسٹمر:</span>
                        <span class="font-bold" id="modalCustomerText">-</span>
                    </div>
                    <div class="flex justify-between" id="modalPhoneRow">
                        <span class="text-gray-500">فون نمبر:</span>
                        <span id="modalPhoneText">-</span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-gray-500">ادائیگی طریقہ:</span>
                        <span class="font-bold uppercase" id="modalPayMethodText">-</span>
                    </div>
                </div>

                <!-- Items Table -->
                <div class="py-2 border-b border-dashed border-gray-400">
                    <table class="w-full text-right text-[11px]">
                        <thead>
                            <tr class="border-b border-gray-300 text-gray-600">
                                <th class="pb-1 text-right">سامان / آئٹم</th>
                                <th class="pb-1 text-center">تعداد</th>
                                <th class="pb-1 text-left">قیمت</th>
                            </tr>
                        </thead>
                        <tbody id="modalItemsTbody" class="divide-y divide-gray-100">
                            <!-- Injected dynamically -->
                        </tbody>
                    </table>
                </div>

                <!-- Financial Totals -->
                <div class="pt-2 space-y-1 text-[11px]">
                    <div class="flex justify-between">
                        <span class="text-gray-600">کل بل (Total):</span>
                        <span class="font-bold" id="modalSubtotalText">Rs. 0</span>
                    </div>
                    <div class="flex justify-between text-rose-600" id="modalDiscountRow">
                        <span>رعایت (Discount):</span>
                        <span class="font-bold" id="modalDiscountText">- Rs. 0</span>
                    </div>
                    <div class="flex justify-between text-sm font-black border-t border-gray-800 pt-1 text-gray-900">
                        <span>خالص بل (Net):</span>
                        <span id="modalNetText">Rs. 0</span>
                    </div>
                    <div class="flex justify-between text-gray-600" id="modalPaidRow">
                        <span>ادا شدہ رقم:</span>
                        <span id="modalPaidText">Rs. 0</span>
                    </div>
                    <div class="flex justify-between text-amber-700 font-bold" id="modalDueRow">
                        <span>بقایا ادھار:</span>
                        <span id="modalDueText">Rs. 0</span>
                    </div>
                </div>

                <!-- Footer Note -->
                <div class="text-center pt-3 mt-2 border-t border-dashed border-gray-400 text-[10px] text-gray-600 space-y-0.5">
                    <p class="font-bold">تشریف لانے کا بہت شکریہ!</p>
                    <p>خریدا ہوا سامان تبدیل یا واپس نہیں ہوگا سوائے خرابی کے۔</p>
                    <p class="font-mono text-[9px] text-gray-400 mt-1">Software Powered by Balal POS</p>
                </div>
            </div>

            <!-- Profit details visible only to Shop Owner -->
            <div class="bg-slate-950 p-4 rounded-2xl border border-slate-800 text-xs space-y-1.5">
                <div class="flex items-center gap-1.5 text-teal-400 font-bold mb-1">
                    <i data-lucide="lock" class="w-3.5 h-3.5"></i>
                    <span>مالک کیلئے خفیہ معلومات (Shopkeeper Only)</span>
                </div>
                <div class="flex justify-between text-slate-400">
                    <span>خریداری لاگت (Purchase Cost):</span>
                    <span id="modalCostText" class="font-mono text-slate-200">Rs. 0</span>
                </div>
                <div class="flex justify-between text-slate-400">
                    <span>اس بل سے حاصل خالص منافع:</span>
                    <span id="modalProfitText" class="font-mono font-black text-emerald-400 text-sm">Rs. 0</span>
                </div>
            </div>

        </div>

        <!-- Modal Bottom Actions -->
        <div class="p-4 border-t border-slate-800 bg-slate-950 flex flex-wrap items-center justify-between gap-2">
            <button onclick="closeInvoiceModal()" class="px-4 py-2 bg-slate-800 hover:bg-slate-700 text-slate-300 font-bold text-xs rounded-xl">
                بند کریں
            </button>
            <div class="flex items-center gap-2">
                <button type="button" id="modalWhatsAppBtn" class="px-4 py-2 bg-emerald-600 hover:bg-emerald-500 text-white font-bold text-xs rounded-xl flex items-center gap-1.5 shadow">
                    <i data-lucide="share-2" class="w-4 h-4"></i>
                    <span>واٹس ایپ شیئر</span>
                </button>
                <button type="button" onclick="printModalReceipt()" class="px-5 py-2 bg-cyan-600 hover:bg-cyan-500 text-white font-black text-xs rounded-xl flex items-center gap-1.5 shadow-lg">
                    <i data-lucide="printer" class="w-4 h-4"></i>
                    <span>تھرمل رسید پرنٹ کریں</span>
                </button>
            </div>
        </div>

    </div>
</div>

<!-- ==========================================================
     Modal 2: Hidden Cancel / Return Sale Confirmation Form
     ========================================================== -->
<form id="cancelSaleForm" method="POST" class="hidden">
    <input type="hidden" name="action" value="cancel_sale">
    <input type="hidden" name="sale_id" id="cancelSaleIdInput">
    <input type="hidden" name="invoice_no" id="cancelInvoiceNoInput">
</form>

<!-- Dedicated Container for Direct Window Printing -->
<div id="quickThermalPrintContainer" class="hidden print-only bg-white text-black p-4 text-xs font-mono max-w-[80mm] mx-auto"></div>

<!-- ==========================================================
     JavaScript Logic: Invoices Modal, Print & WhatsApp
     ========================================================== -->
<script>
let currentModalSale = null;

function viewInvoiceModal(sale) {
    currentModalSale = sale;
    document.getElementById('modalInvoiceTitle').textContent = 'انوائس #' + sale.invoiceNo;
    document.getElementById('modalInvoiceSubtitle').textContent = sale.date + ' ' + sale.time + ' | ' + sale.customerName;

    document.getElementById('modalInvNoText').textContent = sale.invoiceNo;
    document.getElementById('modalDateTimeText').textContent = sale.date + ' ' + sale.time;
    document.getElementById('modalCustomerText').textContent = sale.customerName || 'واک ان کسٹمر';

    if (sale.customerPhone) {
        document.getElementById('modalPhoneRow').style.display = 'flex';
        document.getElementById('modalPhoneText').textContent = sale.customerPhone;
    } else {
        document.getElementById('modalPhoneRow').style.display = 'none';
    }

    document.getElementById('modalPayMethodText').textContent = sale.paymentMethod;

    // Items table
    const tbody = document.getElementById('modalItemsTbody');
    tbody.innerHTML = '';
    sale.items.forEach(it => {
        const name = it.productName || it.name || 'پروڈکٹ';
        const qty = it.quantity || 1;
        const total = it.totalSalePrice || it.unitSalePrice || 0;
        const imei = it.selectedImei1 || it.imei || '';

        const tr = document.createElement('tr');
        tr.className = 'py-1 border-b border-gray-100';
        tr.innerHTML = `
            <td class="py-1 text-right">
                <div class="font-bold text-gray-900">${name}</div>
                ${imei ? '<div class="text-[9px] text-cyan-800 font-mono">IMEI: ' + imei + '</div>' : ''}
            </td>
            <td class="py-1 text-center font-bold text-gray-700">${qty}</td>
            <td class="py-1 text-left font-bold text-gray-900">Rs. ${Number(total).toLocaleString()}</td>
        `;
        tbody.appendChild(tr);
    });

    document.getElementById('modalSubtotalText').textContent = 'Rs. ' + Number(sale.totalAmount).toLocaleString();

    if (sale.discount > 0) {
        document.getElementById('modalDiscountRow').style.display = 'flex';
        document.getElementById('modalDiscountText').textContent = '- Rs. ' + Number(sale.discount).toLocaleString();
    } else {
        document.getElementById('modalDiscountRow').style.display = 'none';
    }

    document.getElementById('modalNetText').textContent = 'Rs. ' + Number(sale.netAmount).toLocaleString();

    if (sale.paymentMethod === 'CREDIT' || sale.dueAmount > 0) {
        document.getElementById('modalPaidRow').style.display = 'flex';
        document.getElementById('modalPaidText').textContent = 'Rs. ' + Number(sale.paidAmount || 0).toLocaleString();
        document.getElementById('modalDueRow').style.display = 'flex';
        document.getElementById('modalDueText').textContent = 'Rs. ' + Number(sale.dueAmount).toLocaleString();
    } else {
        document.getElementById('modalPaidRow').style.display = 'none';
        document.getElementById('modalDueRow').style.display = 'none';
    }

    // Cost & Profit
    document.getElementById('modalCostText').textContent = 'Rs. ' + Number(sale.totalCost || 0).toLocaleString();
    document.getElementById('modalProfitText').textContent = '+ Rs. ' + Number(sale.profit || 0).toLocaleString();

    // WhatsApp Button Action
    document.getElementById('modalWhatsAppBtn').onclick = function() {
        shareWhatsAppInvoice(sale);
    };

    const modal = document.getElementById('invoiceDetailModal');
    modal.classList.remove('hidden');
    modal.classList.add('flex');
}

function closeInvoiceModal() {
    const modal = document.getElementById('invoiceDetailModal');
    modal.classList.remove('flex');
    modal.classList.add('hidden');
}

function printModalReceipt() {
    if (!currentModalSale) return;
    quickPrintThermal(currentModalSale);
}

function quickPrintThermal(sale) {
    const container = document.getElementById('quickThermalPrintContainer');
    let itemsHtml = '';
    sale.items.forEach(it => {
        const name = it.productName || it.name || 'پروڈکٹ';
        const qty = it.quantity || 1;
        const total = it.totalSalePrice || it.unitSalePrice || 0;
        const imei = it.selectedImei1 || it.imei || '';

        itemsHtml += `
            <tr style="border-bottom: 1px dashed #000;">
                <td style="padding: 2px 0; text-align: right;">
                    <strong>${name}</strong>
                    ${imei ? '<br><span style="font-size: 9px;">IMEI: '+imei+'</span>' : ''}
                </td>
                <td style="padding: 2px 0; text-align: center;">${qty}</td>
                <td style="padding: 2px 0; text-align: left;">Rs. ${Number(total).toLocaleString()}</td>
            </tr>
        `;
    });

    container.innerHTML = `
        <div style="text-align: center; border-bottom: 1px dashed #000; padding-bottom: 5px; margin-bottom: 5px;">
            <h2 style="font-size: 14px; font-weight: bold; margin: 0;"><?= htmlspecialchars($shopName) ?></h2>
            <p style="margin: 2px 0; font-size: 10px;"><?= htmlspecialchars($settings['address'] ?? '') ?></p>
            <p style="margin: 2px 0; font-size: 10px;">فون: <?= htmlspecialchars($settings['phone'] ?? '') ?></p>
            <p style="font-size: 11px; font-weight: bold; margin-top: 4px;">*** سیل رسید ***</p>
        </div>
        <div style="font-size: 10px; border-bottom: 1px dashed #000; padding-bottom: 4px; margin-bottom: 5px;">
            <div><strong>انوائس #:</strong> ${sale.invoiceNo}</div>
            <div><strong>تاریخ:</strong> ${sale.date} ${sale.time}</div>
            <div><strong>گاہک:</strong> ${sale.customerName || 'واک ان'}</div>
            ${sale.customerPhone ? '<div><strong>فون:</strong> '+sale.customerPhone+'</div>' : ''}
            <div><strong>ادائیگی:</strong> ${sale.paymentMethod}</div>
        </div>
        <table style="width: 100%; font-size: 10px; margin-bottom: 6px;">
            <thead>
                <tr style="border-bottom: 1px solid #000;">
                    <th style="text-align: right;">آئٹم</th>
                    <th style="text-align: center;">تعداد</th>
                    <th style="text-align: left;">رقم</th>
                </tr>
            </thead>
            <tbody>
                ${itemsHtml}
            </tbody>
        </table>
        <div style="border-top: 1px dashed #000; padding-top: 4px; font-size: 10px;">
            <div style="display: flex; justify-content: space-between;">
                <span>کل بل:</span>
                <span>Rs. ${Number(sale.totalAmount).toLocaleString()}</span>
            </div>
            ${sale.discount > 0 ? `
            <div style="display: flex; justify-content: space-between; color: red;">
                <span>رعایت:</span>
                <span>- Rs. ${Number(sale.discount).toLocaleString()}</span>
            </div>` : ''}
            <div style="display: flex; justify-content: space-between; font-size: 12px; font-weight: bold; margin-top: 3px; border-top: 1px solid #000; padding-top: 2px;">
                <span>خالص بل:</span>
                <span>Rs. ${Number(sale.netAmount).toLocaleString()}</span>
            </div>
        </div>
        <div style="text-align: center; font-size: 9px; margin-top: 10px; border-top: 1px dashed #000; padding-top: 5px;">
            <p style="margin: 0;">تشریف لانے کا شکریہ!</p>
            <p style="margin: 0;">Software Powered by Balal POS</p>
        </div>
    `;

    window.print();
}

function shareWhatsAppInvoice(sale) {
    const itemLines = sale.items.map((it, idx) => {
        const name = it.productName || it.name || 'آئٹم';
        const qty = it.quantity || 1;
        const total = it.totalSalePrice || it.unitSalePrice || 0;
        const imei = it.selectedImei1 || it.imei || '';
        return `${idx + 1}. *${name}* (x${qty})${imei ? ` [IMEI: ${imei}]` : ''} = Rs. ${Number(total).toLocaleString()}`;
    }).join('\n');

    const msg = `🧾 *سیل رسید (Sale Invoice)*\n*<?= htmlspecialchars($shopName) ?>*\n📍 <?= htmlspecialchars($settings['address'] ?? '') ?>\n📞 <?= htmlspecialchars($settings['phone'] ?? '') ?>\n-------------------------\n*انوائس #:* ${sale.invoiceNo}\n*تاریخ:* ${sale.date} ${sale.time}\n*گاہک:* ${sale.customerName || 'واک ان کسٹمر'}\n-------------------------\n${itemLines}\n-------------------------\n*کل بل:* Rs. ${Number(sale.totalAmount).toLocaleString()}\n${sale.discount > 0 ? `*رعایت:* Rs. ${Number(sale.discount).toLocaleString()}\n` : ''}*خالص رقم:* Rs. ${Number(sale.netAmount).toLocaleString()}\n*ادائیگی طریقہ:* ${sale.paymentMethod}\n-------------------------\nتشریف لانے کا بہت شکریہ!`;

    const cleanPhone = sale.customerPhone ? sale.customerPhone.replace(/[^0-9]/g, '') : '';
    let waUrl = '';
    if (cleanPhone && cleanPhone.length >= 10) {
        // Format for Pakistani numbers (0300 -> 92300)
        let intlPhone = cleanPhone;
        if (intlPhone.startsWith('0')) {
            intlPhone = '92' + intlPhone.substring(1);
        }
        waUrl = `https://wa.me/${intlPhone}?text=${encodeURIComponent(msg)}`;
    } else {
        waUrl = `https://wa.me/?text=${encodeURIComponent(msg)}`;
    }

    window.open(waUrl, '_blank');
}

function confirmCancelSale(saleId, invoiceNo) {
    if (confirm(`کیا آپ واقعی انوائس #${invoiceNo} منسوخ کرنا چاہتے ہیں؟\n\nاس سے اس بل کا تمام اسٹاک اور IMEI خودکار طور پر واپس دکان کے اسٹاک میں جمع ہو جائے گا۔`)) {
        document.getElementById('cancelSaleIdInput').value = saleId;
        document.getElementById('cancelInvoiceNoInput').value = invoiceNo;
        document.getElementById('cancelSaleForm').submit();
    }
}
</script>

<?php require_once __DIR__ . '/../backend/footer.php'; ?>
