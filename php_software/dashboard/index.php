<?php
$pageTitle = 'ڈیش بورڈ (Dashboard)';
$activeMenu = 'dashboard';
require_once __DIR__ . '/../backend/header.php';

$successMsg = '';
$errorMsg = '';
$receiptTrx = null;
$todayDate = date('Y-m-d');
$nowTime = date('h:i A');

// Selected Date Filter (Default to today)
$selectedDate = isset($_GET['date']) && !empty($_GET['date']) ? trim($_GET['date']) : $todayDate;

// -------------------------------------------------------------
// POST HANDLERS FOR DIRECT DASHBOARD ACTIONS
// -------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    
    // 1. New Buy / Sell Transaction
    if ($_POST['action'] === 'save_buy_sell') {
        $direction = trim($_POST['direction'] ?? 'BUY'); // BUY (Cash Out) or SELL (Cash In)
        $channel = trim($_POST['payment_method'] ?? 'EASYPAISA');
        $customerName = trim($_POST['customer_name'] ?? 'کسٹمر');
        if (empty($customerName)) $customerName = 'کسٹمر';
        $customerPhone = trim($_POST['customer_phone'] ?? '');
        $cnic = trim($_POST['cnic'] ?? '');
        $amount = floatval($_POST['amount'] ?? 0);
        $feeProfit = floatval($_POST['fee_profit'] ?? 0);
        $trxCode = trim($_POST['trx_id'] ?? '');
        $notes = trim($_POST['notes'] ?? '');
        $trxDate = trim($_POST['date'] ?? $todayDate);
        $trxTime = trim($_POST['time'] ?? $nowTime);
        $printReceipt = isset($_POST['print_receipt']) && $_POST['print_receipt'] === '1';

        // Set system transaction type
        $type = $direction === 'BUY' ? 'BUY_' . $channel : 'SELL_' . $channel;
        if (!in_array($type, ['BUY_EASYPAISA', 'BUY_CASH', 'SELL_EASYPAISA', 'SELL_CASH', 'BUY_BANK', 'SELL_BANK', 'BUY_JAZZCASH', 'SELL_JAZZCASH', 'BUY_SADAPAY', 'SELL_SADAPAY'])) {
            $type = $direction === 'BUY' ? 'BUY_EASYPAISA' : 'SELL_EASYPAISA';
        }

        // Calculate cash vs digital amount
        $epAmount = $amount;
        $cashAmount = $direction === 'BUY' ? max(0, $amount - $feeProfit) : ($amount + $feeProfit);

        try {
            $id = 'trx-' . uniqid();
            $createdAt = time();

            $stmt = $pdo->prepare("INSERT INTO transactions (
                id, date, time, type, customer_name, customer_phone, cnic,
                trx_id, easy_paisa_amount, cash_amount, fee_profit, expense_amount, payment_method, note, created_at
            ) VALUES (
                :id, :date, :time, :type, :cname, :cphone, :cnic,
                :trx_id, :ep_amt, :cash_amt, :profit, 0, :pmethod, :notes, :created_at
            )");
            $stmt->execute([
                ':id' => $id,
                ':date' => $trxDate,
                ':time' => $trxTime,
                ':type' => $type,
                ':cname' => $customerName,
                ':cphone' => $customerPhone,
                ':cnic' => $cnic,
                ':trx_id' => $trxCode,
                ':ep_amt' => $epAmount,
                ':cash_amt' => $cashAmount,
                ':profit' => $feeProfit,
                ':pmethod' => $channel,
                ':notes' => $notes,
                ':created_at' => $createdAt
            ]);

            $successMsg = ($direction === 'BUY' ? 'کیش آؤٹ (خریداری)' : 'کیش ان (فروخت)') . ' کا نیا اندراج کامیابی سے محفوظ ہو گیا!';
            
            if ($printReceipt) {
                $receiptTrx = [
                    'id' => $id,
                    'direction' => $direction,
                    'type' => $type,
                    'channel' => $channel,
                    'customer_name' => $customerName,
                    'customer_phone' => $customerPhone,
                    'cnic' => $cnic,
                    'amount' => $amount,
                    'fee' => $feeProfit,
                    'cash_amount' => $cashAmount,
                    'trx_id' => $trxCode,
                    'date' => $trxDate,
                    'time' => $trxTime,
                    'notes' => $notes
                ];
            }
        } catch (Exception $e) {
            $errorMsg = 'اندراج محفوظ کرنے میں خرابی: ' . $e->getMessage();
        }
    }

    // 2. Direct Expense Entry
    if ($_POST['action'] === 'save_expense') {
        $expenseAmount = floatval($_POST['expense_amount'] ?? 0);
        $expenseCategory = trim($_POST['category'] ?? 'عام خرچہ');
        $expenseNotes = trim($_POST['notes'] ?? '');
        $expDate = trim($_POST['date'] ?? $todayDate);
        $expTime = trim($_POST['time'] ?? $nowTime);
        $noteCombined = $expenseCategory . ($expenseNotes ? ' - ' . $expenseNotes : '');

        if ($expenseAmount > 0) {
            try {
                $id = 'exp-' . uniqid();
                $stmt = $pdo->prepare("INSERT INTO transactions (
                    id, date, time, type, customer_name, customer_phone,
                    trx_id, easy_paisa_amount, cash_amount, fee_profit, expense_amount, payment_method, note, created_at
                ) VALUES (
                    :id, :date, :time, 'EXPENSE', 'دکان خرچہ', '',
                    '', 0, :cash_amt, 0, :exp_amt, 'CASH', :note, :created_at
                )");
                $stmt->execute([
                    ':id' => $id,
                    ':date' => $expDate,
                    ':time' => $expTime,
                    ':cash_amt' => $expenseAmount,
                    ':exp_amt' => $expenseAmount,
                    ':note' => $noteCombined,
                    ':created_at' => time()
                ]);
                $successMsg = 'دکان کا خرچہ (Rs. ' . number_format($expenseAmount) . ') کامیابی سے درج ہو گیا!';
            } catch (Exception $e) {
                $errorMsg = 'خرچہ درج کرنے میں خرابی: ' . $e->getMessage();
            }
        } else {
            $errorMsg = 'براہ کرم خرچے کی درست رقم درج کریں۔';
        }
    }

    // 3. Opening Balance Save
    if ($_POST['action'] === 'save_opening_balance') {
        $openingDate = trim($_POST['date'] ?? $todayDate);
        $openingCash = floatval($_POST['opening_cash'] ?? 0);
        $openingEp = floatval($_POST['opening_easypaisa'] ?? 0);
        $openingNotes = trim($_POST['notes'] ?? '');

        try {
            $checkStmt = $pdo->prepare("SELECT COUNT(*) FROM daily_balances WHERE date = :date");
            $checkStmt->execute([':date' => $openingDate]);
            $exists = intval($checkStmt->fetchColumn()) > 0;

            if ($exists) {
                $stmt = $pdo->prepare("UPDATE daily_balances SET opening_cash = :cash, opening_easypaisa = :ep, notes = :notes WHERE date = :date");
                $stmt->execute([
                    ':cash' => $openingCash,
                    ':ep' => $openingEp,
                    ':notes' => $openingNotes,
                    ':date' => $openingDate
                ]);
            } else {
                $stmt = $pdo->prepare("INSERT INTO daily_balances (date, opening_cash, opening_easypaisa, notes) VALUES (:date, :cash, :ep, :notes)");
                $stmt->execute([
                    ':date' => $openingDate,
                    ':cash' => $openingCash,
                    ':ep' => $openingEp,
                    ':notes' => $openingNotes
                ]);
            }
            $successMsg = 'تاریخ ' . htmlspecialchars($openingDate) . ' کا صبح کا اوپننگ بیلنس کامیابی سے محفوظ ہو گیا!';
        } catch (Exception $e) {
            $errorMsg = 'اوپننگ بیلنس محفوظ کرنے میں خرابی: ' . $e->getMessage();
        }
    }

    // 4. Delete Entry
    if ($_POST['action'] === 'delete_entry') {
        $delId = trim($_POST['entry_id'] ?? '');
        $tableType = trim($_POST['table_type'] ?? 'transactions');

        if (!empty($delId)) {
            try {
                if ($tableType === 'sales') {
                    $delStmt = $pdo->prepare("DELETE FROM product_sales WHERE id = :id");
                    $delStmt->execute([':id' => $delId]);
                } else {
                    $delStmt = $pdo->prepare("DELETE FROM transactions WHERE id = :id");
                    $delStmt->execute([':id' => $delId]);
                }
                $successMsg = 'اندراج کامیابی سے ڈیلیٹ ہو گیا!';
            } catch (Exception $e) {
                $errorMsg = 'ڈیلیٹ کرنے میں خرابی: ' . $e->getMessage();
            }
        }
    }
}

// -------------------------------------------------------------
// FETCH SYSTEM DATA & METRICS
// -------------------------------------------------------------

// 1. Today Product POS Sales Stats
$salesStats = ['total' => 0, 'profit' => 0, 'count' => 0];
try {
    $salesStmt = $pdo->prepare("SELECT COALESCE(SUM(net_amount), 0) as total, COALESCE(SUM(profit), 0) as profit, COUNT(*) as count FROM product_sales WHERE date = :date");
    $salesStmt->execute([':date' => $selectedDate]);
    $salesStats = $salesStmt->fetch() ?: $salesStats;
} catch (Exception $e) {}

// Total historical sales count
$totalSalesCount = 0;
try {
    $totalSalesCount = $pdo->query("SELECT COUNT(*) as count FROM product_sales")->fetch()['count'] ?? 0;
} catch (Exception $e) {}

// 2. Buy / Sell / Expense Stats for Selected Date
$epBuy = ['ep_vol' => 0, 'cash_vol' => 0, 'fee' => 0, 'count' => 0];
$epSell = ['ep_vol' => 0, 'cash_vol' => 0, 'fee' => 0, 'count' => 0];
$expenses = 0;

try {
    $epBuyStmt = $pdo->prepare("SELECT COALESCE(SUM(easy_paisa_amount), 0) as ep_vol, COALESCE(SUM(cash_amount), 0) as cash_vol, COALESCE(SUM(fee_profit), 0) as fee, COUNT(*) as count FROM transactions WHERE date = :date AND type LIKE 'BUY%'");
    $epBuyStmt->execute([':date' => $selectedDate]);
    $epBuy = $epBuyStmt->fetch() ?: $epBuy;

    $epSellStmt = $pdo->prepare("SELECT COALESCE(SUM(easy_paisa_amount), 0) as ep_vol, COALESCE(SUM(cash_amount), 0) as cash_vol, COALESCE(SUM(fee_profit), 0) as fee, COUNT(*) as count FROM transactions WHERE date = :date AND type LIKE 'SELL%'");
    $epSellStmt->execute([':date' => $selectedDate]);
    $epSell = $epSellStmt->fetch() ?: $epSell;

    $expStmt = $pdo->prepare("SELECT COALESCE(SUM(expense_amount), 0) as total FROM transactions WHERE date = :date AND type IN ('EXPENSE', 'DISCREPANCY_LOSS')");
    $expStmt->execute([':date' => $selectedDate]);
    $expenses = (float)($expStmt->fetch()['total'] ?? 0);
} catch (Exception $e) {}

$totalEpFee = (float)$epBuy['fee'] + (float)$epSell['fee'];
$netTodayProfit = ((float)$salesStats['profit'] + $totalEpFee) - $expenses;

// 3. Opening Balances for Selected Date
$openingCash = 0;
$openingEp = 0;
try {
    $openStmt = $pdo->prepare("SELECT opening_cash, opening_easypaisa FROM daily_balances WHERE date = :date");
    $openStmt->execute([':date' => $selectedDate]);
    $openRow = $openStmt->fetch();
    if ($openRow) {
        $openingCash = (float)($openRow['opening_cash'] ?? 0);
        $openingEp = (float)($openRow['opening_easypaisa'] ?? 0);
    }
} catch (Exception $e) {}

// 4. Live Running Drawer & Wallet Balances
$currentEpBal = $openingEp;
$currentCashBal = $openingCash;

try {
    $epBalanceQuery = $pdo->query("SELECT 
        (COALESCE(SUM(CASE WHEN type LIKE 'BUY%' THEN easy_paisa_amount ELSE 0 END), 0) - 
         COALESCE(SUM(CASE WHEN type LIKE 'SELL%' THEN easy_paisa_amount ELSE 0 END), 0)) as ep_diff,
        (COALESCE(SUM(CASE WHEN type LIKE 'SELL%' THEN cash_amount ELSE 0 END), 0) - 
         COALESCE(SUM(CASE WHEN type LIKE 'BUY%' THEN cash_amount ELSE 0 END), 0) -
         COALESCE(SUM(expense_amount), 0)) as cash_diff
        FROM transactions")->fetch();

    $currentEpBal += (float)($epBalanceQuery['ep_diff'] ?? 0);
    $currentCashBal += (float)($epBalanceQuery['cash_diff'] ?? 0);

    // Add cash from POS Sales
    $posCashQuery = $pdo->query("SELECT COALESCE(SUM(paid_amount), 0) as total_pos_cash FROM product_sales WHERE payment_method = 'CASH'")->fetch();
    $currentCashBal += (float)($posCashQuery['total_pos_cash'] ?? 0);
} catch (Exception $e) {}

// 5. Stock Inventory Valuation
$stockQuery = ['items' => 0, 'qty' => 0, 'stock_cost' => 0, 'stock_retail' => 0];
$lowStockCount = 0;
try {
    $stockQuery = $pdo->query("SELECT COUNT(*) as items, COALESCE(SUM(stock), 0) as qty, COALESCE(SUM(stock * purchase_price), 0) as stock_cost, COALESCE(SUM(stock * sale_price), 0) as stock_retail FROM products")->fetch() ?: $stockQuery;
    $lowStockCount = (int)($pdo->query("SELECT COUNT(*) as cnt FROM products WHERE stock <= 3")->fetch()['cnt'] ?? 0);
} catch (Exception $e) {}

// 6. Customer Udhar Outstanding
$customerUdhar = 0;
try {
    $customerUdhar = (float)($pdo->query("SELECT COALESCE(SUM(due_amount), 0) as total FROM product_sales WHERE due_amount > 0")->fetch()['total'] ?? 0);
} catch (Exception $e) {}

// 7. Last 7 Days Chart Data
$chartLabels = [];
$chartSales = [];
$chartProfits = [];

for ($i = 6; $i >= 0; $i--) {
    $d = date('Y-m-d', strtotime("-$i days"));
    $dayNameUr = match(date('D', strtotime($d))) {
        'Mon' => 'پیر',
        'Tue' => 'منگل',
        'Wed' => 'بدھ',
        'Thu' => 'جمعرات',
        'Fri' => 'جمعہ',
        'Sat' => 'ہفتہ',
        'Sun' => 'اتوار',
        default => date('D', strtotime($d))
    };
    $chartLabels[] = $dayNameUr . ' (' . date('d M', strtotime($d)) . ')';

    try {
        $stmt = $pdo->prepare("SELECT COALESCE(SUM(net_amount), 0) as sales, COALESCE(SUM(profit), 0) as profit FROM product_sales WHERE date = :d");
        $stmt->execute([':d' => $d]);
        $row = $stmt->fetch();
        $chartSales[] = (float)($row['sales'] ?? 0);
        $chartProfits[] = (float)($row['profit'] ?? 0);
    } catch (Exception $e) {
        $chartSales[] = 0;
        $chartProfits[] = 0;
    }
}

// 8. Today's Feeds
$dayTrxList = [];
$daySalesList = [];
$customersList = [];

try {
    $dayTrxStmt = $pdo->prepare("SELECT * FROM transactions WHERE date = :date ORDER BY created_at DESC LIMIT 60");
    $dayTrxStmt->execute([':date' => $selectedDate]);
    $dayTrxList = $dayTrxStmt->fetchAll() ?: [];

    $daySalesStmt = $pdo->prepare("SELECT * FROM product_sales WHERE date = :date ORDER BY created_at DESC LIMIT 60");
    $daySalesStmt->execute([':date' => $selectedDate]);
    $daySalesList = $daySalesStmt->fetchAll() ?: [];

    $custStmt = $pdo->query("SELECT name, phone FROM customers ORDER BY name ASC LIMIT 100");
    if ($custStmt) {
        $customersList = $custStmt->fetchAll() ?: [];
    }
} catch (Exception $e) {}
?>

<div class="space-y-5 sm:space-y-6">

    <!-- Notification Toasts -->
    <?php if (!empty($successMsg)): ?>
        <div class="p-4 rounded-2xl bg-emerald-500/15 border border-emerald-500/30 text-emerald-300 flex items-center justify-between shadow-lg shadow-emerald-500/10 animate-fade-in">
            <div class="flex items-center gap-3">
                <i data-lucide="check-circle-2" class="w-5 h-5 text-emerald-400 shrink-0"></i>
                <span class="text-xs sm:text-sm font-bold"><?= htmlspecialchars($successMsg) ?></span>
            </div>
            <button onclick="this.parentElement.remove()" class="text-emerald-400 hover:text-white p-1">
                <i data-lucide="x" class="w-4 h-4"></i>
            </button>
        </div>
    <?php endif; ?>

    <?php if (!empty($errorMsg)): ?>
        <div class="p-4 rounded-2xl bg-rose-500/15 border border-rose-500/30 text-rose-300 flex items-center justify-between shadow-lg shadow-rose-500/10 animate-fade-in">
            <div class="flex items-center gap-3">
                <i data-lucide="alert-circle" class="w-5 h-5 text-rose-400 shrink-0"></i>
                <span class="text-xs sm:text-sm font-bold"><?= htmlspecialchars($errorMsg) ?></span>
            </div>
            <button onclick="this.parentElement.remove()" class="text-rose-400 hover:text-white p-1">
                <i data-lucide="x" class="w-4 h-4"></i>
            </button>
        </div>
    <?php endif; ?>

    <!-- Top Welcome & Date Filter Bar -->
    <div class="p-4 sm:p-5 rounded-2xl bg-slate-900 border border-slate-800 shadow-sm flex flex-col lg:flex-row lg:items-center justify-between gap-4">
        <div class="flex items-center gap-3.5">
            <div class="w-12 h-12 rounded-2xl bg-gradient-to-tr from-emerald-600 to-teal-500 text-white flex items-center justify-center font-black shadow-lg shadow-emerald-500/20 shrink-0">
                <i data-lucide="store" class="w-6 h-6"></i>
            </div>
            <div>
                <div class="flex items-center gap-2 flex-wrap">
                    <h2 class="text-lg sm:text-xl font-black text-white">
                        <?= $shopName ?>
                    </h2>
                    <span class="px-2.5 py-0.5 rounded-full text-[10px] font-black bg-emerald-500/10 text-emerald-400 border border-emerald-500/20">
                        پی ایچ پی و ایس کیو ایل لائیو
                    </span>
                </div>
                <p class="text-xs text-slate-400 mt-0.5">
                    مرکزی ڈیش بورڈ: روزانہ سیلز، ایزی پیسہ، کیش دراز اور تمام 12 پورٹلز
                </p>
            </div>
        </div>

        <!-- Action Controls -->
        <div class="flex items-center gap-2 flex-wrap">
            
            <!-- Quick Buy / Sell Button -->
            <button type="button" onclick="openBuySellModal('BUY')" class="px-4 py-2 rounded-xl bg-gradient-to-r from-emerald-600 to-teal-600 hover:from-emerald-500 hover:to-teal-500 text-white text-xs font-black flex items-center gap-2 shadow-lg shadow-emerald-600/25 transition-all cursor-pointer active:scale-95">
                <i data-lucide="plus-circle" class="w-4 h-4"></i>
                <span>+ نیا Buy / Sell اندراج</span>
            </button>

            <!-- Quick Expense Button -->
            <button type="button" onclick="openExpenseModal()" class="px-3.5 py-2 rounded-xl bg-slate-800 hover:bg-slate-700 text-rose-300 border border-rose-500/30 text-xs font-bold flex items-center gap-1.5 transition-all cursor-pointer">
                <i data-lucide="minus-circle" class="w-4 h-4 text-rose-400"></i>
                <span>دکان خرچہ</span>
            </button>

            <!-- Opening Balance Button -->
            <button type="button" onclick="openOpeningModal()" class="px-3.5 py-2 rounded-xl bg-slate-800 hover:bg-slate-700 text-teal-300 border border-teal-500/30 text-xs font-bold flex items-center gap-1.5 transition-all cursor-pointer" title="صبح کا اوپننگ بیلنس">
                <i data-lucide="coins" class="w-4 h-4 text-teal-400"></i>
                <span class="hidden sm:inline">اوپننگ بیلنس</span>
            </button>

            <!-- Date Form -->
            <form method="GET" action="index.php" class="flex items-center gap-1.5">
                <input type="date" name="date" value="<?= htmlspecialchars($selectedDate) ?>" onchange="this.form.submit()" class="px-3 py-1.5 rounded-xl border bg-slate-800 border-slate-700 text-slate-100 text-xs font-mono font-bold outline-none cursor-pointer focus:border-blue-500">
                <button type="button" onclick="window.print()" class="p-2 rounded-xl bg-slate-800 hover:bg-slate-700 text-white text-xs font-bold flex items-center shadow-sm border border-slate-700" title="روزنامچہ پرنٹ کریں">
                    <i data-lucide="printer" class="w-4 h-4 text-emerald-400"></i>
                </button>
            </form>
        </div>
    </div>

    <!-- 6 PRIMARY HIGH-IMPACT METRIC CARDS -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-6 gap-3 sm:gap-4">
        
        <!-- 1. Total Net Profit -->
        <div class="bg-slate-900 border border-slate-800 p-4 rounded-2xl shadow-sm hover:border-emerald-500/40 transition-all group">
            <div class="flex items-center justify-between text-xs font-bold mb-1.5">
                <span class="text-emerald-400 uppercase tracking-wider flex items-center gap-1.5">
                    <i data-lucide="trending-up" class="w-4 h-4 text-emerald-400"></i>
                    خالص منافع (Net Profit)
                </span>
                <span class="px-1.5 py-0.5 rounded-md bg-emerald-500/10 text-emerald-400 text-[10px] font-mono font-black">
                    آج کا
                </span>
            </div>
            <div class="text-xl sm:text-2xl font-black font-mono text-emerald-400 mb-2">
                Rs. <?= number_format($netTodayProfit) ?>
            </div>
            <div class="text-[11px] text-slate-400 pt-2 border-t border-slate-800 flex items-center justify-between">
                <span>POS: <strong class="text-blue-400">Rs. <?= number_format($salesStats['profit']) ?></strong></span>
                <span>فیس: <strong class="text-emerald-400">Rs. <?= number_format($totalEpFee) ?></strong></span>
            </div>
        </div>

        <!-- 2. Product Sales Revenue -->
        <div class="bg-slate-900 border border-slate-800 p-4 rounded-2xl shadow-sm hover:border-blue-500/40 transition-all group">
            <div class="flex items-center justify-between text-xs font-bold mb-1.5">
                <span class="text-blue-400 uppercase tracking-wider flex items-center gap-1.5">
                    <i data-lucide="shopping-cart" class="w-4 h-4 text-blue-400"></i>
                    سامان فروخت (POS Sales)
                </span>
                <span class="px-1.5 py-0.5 rounded-md bg-blue-500/10 text-blue-400 text-[10px] font-mono font-black">
                    <?= number_format($salesStats['count']) ?> بل
                </span>
            </div>
            <div class="text-xl sm:text-2xl font-black font-mono text-blue-400 mb-2">
                Rs. <?= number_format($salesStats['total']) ?>
            </div>
            <div class="text-[11px] text-slate-400 pt-2 border-t border-slate-800 flex items-center justify-between">
                <span>کل بل: <strong><?= number_format($totalSalesCount) ?></strong></span>
                <a href="../sales_ledger/index.php" class="text-blue-400 font-bold hover:underline">لیجر &larr;</a>
            </div>
        </div>

        <!-- 3. Cash in Hand -->
        <div class="bg-slate-900 border border-slate-800 p-4 rounded-2xl shadow-sm hover:border-teal-500/40 transition-all group">
            <div class="flex items-center justify-between text-xs font-bold mb-1.5">
                <span class="text-teal-400 uppercase tracking-wider flex items-center gap-1.5">
                    <i data-lucide="wallet" class="w-4 h-4 text-teal-400"></i>
                    موجودہ کیش دراز
                </span>
                <span class="text-[10px] text-slate-400 font-mono">کیش آن ہینڈ</span>
            </div>
            <div class="text-xl sm:text-2xl font-black font-mono text-white mb-2">
                Rs. <?= number_format($currentCashBal) ?>
            </div>
            <div class="text-[11px] text-slate-400 pt-2 border-t border-slate-800 flex items-center justify-between">
                <span>آمدن: <strong class="text-emerald-400">+<?= number_format($epBuy['cash_vol']) ?></strong></span>
                <span>خرچہ: <strong class="text-rose-400">-<?= number_format($expenses) ?></strong></span>
            </div>
        </div>

        <!-- 4. Digital Account Balance -->
        <div class="bg-slate-900 border border-slate-800 p-4 rounded-2xl shadow-sm hover:border-indigo-500/40 transition-all group">
            <div class="flex items-center justify-between text-xs font-bold mb-1.5">
                <span class="text-indigo-400 uppercase tracking-wider flex items-center gap-1.5">
                    <i data-lucide="zap" class="w-4 h-4 text-indigo-400"></i>
                    ڈیجیٹل / ایزی پیسہ
                </span>
                <span class="text-[10px] text-slate-400 font-mono">آن لائن بیلنس</span>
            </div>
            <div class="text-xl sm:text-2xl font-black font-mono text-indigo-400 mb-2">
                Rs. <?= number_format($currentEpBal) ?>
            </div>
            <div class="text-[11px] text-slate-400 pt-2 border-t border-slate-800 flex items-center justify-between">
                <span>انٹری: <strong class="text-emerald-400">+<?= number_format($epBuy['ep_vol']) ?></strong></span>
                <span>اخراج: <strong class="text-amber-400">-<?= number_format($epSell['ep_vol']) ?></strong></span>
            </div>
        </div>

        <!-- 5. Inventory Stock Value -->
        <div class="bg-slate-900 border border-slate-800 p-4 rounded-2xl shadow-sm hover:border-purple-500/40 transition-all group">
            <div class="flex items-center justify-between text-xs font-bold mb-1.5">
                <span class="text-purple-400 uppercase tracking-wider flex items-center gap-1.5">
                    <i data-lucide="package" class="w-4 h-4 text-purple-400"></i>
                    اسٹاک انوینٹری مالیت
                </span>
                <?php if ($lowStockCount > 0): ?>
                    <span class="px-1.5 py-0.5 rounded-md bg-amber-500/10 text-amber-400 text-[10px] font-mono font-bold animate-pulse">
                        <?= $lowStockCount ?> کم اسٹاک
                    </span>
                <?php else: ?>
                    <span class="text-[10px] text-slate-400 font-mono"><?= number_format($stockQuery['items']) ?> آئٹمز</span>
                <?php endif; ?>
            </div>
            <div class="text-xl sm:text-2xl font-black font-mono text-white mb-2">
                Rs. <?= number_format($stockQuery['stock_cost']) ?>
            </div>
            <div class="text-[11px] text-slate-400 pt-2 border-t border-slate-800 flex items-center justify-between">
                <span><?= number_format($stockQuery['qty']) ?> کل تعداد</span>
                <a href="../inventory/index.php" class="text-purple-400 font-bold hover:underline">اسٹاک &larr;</a>
            </div>
        </div>

        <!-- 6. Customer Udhar / Khata -->
        <div class="bg-slate-900 border border-slate-800 p-4 rounded-2xl shadow-sm hover:border-amber-500/40 transition-all group">
            <div class="flex items-center justify-between text-xs font-bold mb-1.5">
                <span class="text-amber-400 uppercase tracking-wider flex items-center gap-1.5">
                    <i data-lucide="users" class="w-4 h-4 text-amber-400"></i>
                    کسٹمر ادھار کھاتہ
                </span>
                <span class="px-1.5 py-0.5 rounded-md bg-amber-500/10 text-amber-400 text-[10px] font-mono font-bold">
                    واجب الوصول
                </span>
            </div>
            <div class="text-xl sm:text-2xl font-black font-mono text-amber-400 mb-2">
                Rs. <?= number_format($customerUdhar) ?>
            </div>
            <div class="text-[11px] text-slate-400 pt-2 border-t border-slate-800 flex items-center justify-between">
                <span>ادھار بقایا جات</span>
                <a href="../customer_khata/index.php" class="text-amber-400 font-bold hover:underline">کھاتہ &larr;</a>
            </div>
        </div>

    </div>

    <!-- QUICK ACTION SHORTCUTS -->
    <div class="grid grid-cols-2 sm:grid-cols-4 gap-2.5">
        <a href="../pos/index.php" class="p-3.5 rounded-xl bg-emerald-600 hover:bg-emerald-700 text-white font-black text-xs flex items-center justify-center gap-2 shadow-sm transition-all active:scale-95 cursor-pointer">
            <i data-lucide="shopping-cart" class="w-4 h-4 shrink-0"></i>
            <span>نیا سامان بل (POS)</span>
        </a>

        <button type="button" onclick="openBuySellModal('BUY')" class="p-3.5 rounded-xl bg-blue-600 hover:bg-blue-700 text-white font-black text-xs flex items-center justify-center gap-2 shadow-sm transition-all active:scale-95 cursor-pointer">
            <i data-lucide="plus-circle" class="w-4 h-4 shrink-0"></i>
            <span>نیا Buy / Sell اندراج</span>
        </button>

        <button type="button" onclick="openExpenseModal()" class="p-3.5 rounded-xl border border-rose-500/30 bg-slate-900 hover:bg-rose-950/40 text-rose-300 font-black text-xs flex items-center justify-center gap-2 transition-all active:scale-95 cursor-pointer">
            <i data-lucide="minus-circle" class="w-4 h-4 text-rose-400 shrink-0"></i>
            <span>دکان کا خرچہ درج کریں</span>
        </button>

        <a href="../purchase_register/index.php" class="p-3.5 rounded-xl border border-cyan-500/30 bg-slate-900 hover:bg-cyan-950/40 text-cyan-300 font-black text-xs flex items-center justify-center gap-2 transition-all active:scale-95 cursor-pointer">
            <i data-lucide="smartphone" class="w-4 h-4 text-cyan-400 shrink-0"></i>
            <span>موبائل خریداری رجسٹر</span>
        </a>
    </div>

    <!-- ALL 12 CORE SOFTWARE MODULES GRID -->
    <div class="p-4 sm:p-6 rounded-2xl bg-slate-900 border border-slate-800 shadow-sm">
        <div class="flex flex-col sm:flex-row sm:items-center justify-between mb-4 pb-3 border-b border-slate-800 gap-2">
            <div>
                <h3 class="text-sm sm:text-base font-black text-white flex items-center gap-2">
                    <i data-lucide="sparkles" class="w-4 h-4 text-amber-400"></i>
                    <span>سافٹ ویئر کے تمام 12 ماڈیولز (Complete 12 Portals)</span>
                </h3>
                <p class="text-xs text-slate-400 mt-0.5">
                    دکان کے تمام ضروری شعبہ جات تک ایک کلک پر براہ راست رسائی
                </p>
            </div>

            <?php if ($lowStockCount > 0): ?>
                <a href="../inventory/index.php" class="px-3 py-1.5 rounded-xl bg-amber-500/10 text-amber-400 border border-amber-700/50 text-xs font-extrabold flex items-center gap-1.5 animate-pulse cursor-pointer shrink-0">
                    <i data-lucide="alert-triangle" class="w-4 h-4"></i>
                    <span><?= $lowStockCount ?> آئٹمز کا اسٹاک کم ہے!</span>
                </a>
            <?php endif; ?>
        </div>

        <!-- 12 Modules Grid -->
        <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 xl:grid-cols-6 gap-3">
            
            <!-- 1. POS -->
            <a href="../pos/index.php" class="p-3.5 rounded-2xl border border-slate-800 hover:border-blue-500 bg-slate-800/80 hover:bg-slate-800 transition-all duration-200 flex flex-col items-center text-center justify-between min-h-[145px] group">
                <div class="w-11 h-11 rounded-xl bg-gradient-to-br from-blue-600 to-indigo-700 text-white flex items-center justify-center font-bold shadow-md group-hover:scale-105 transition-transform mb-2">
                    <i data-lucide="shopping-cart" class="w-5 h-5"></i>
                </div>
                <div class="space-y-0.5 w-full">
                    <h4 class="font-extrabold text-xs text-white leading-tight">سامان فروخت بل (POS)</h4>
                    <p class="text-[10px] text-slate-400 line-clamp-1">بارکوڈ بلنگ و تھرمل پرنٹ</p>
                </div>
                <span class="mt-2 text-[10px] font-extrabold px-2 py-0.5 rounded-full border bg-blue-500/10 text-blue-400 border-blue-800/40">
                    <?= number_format($salesStats['count']) ?> بل آج
                </span>
            </a>

            <!-- 2. Sales Ledger -->
            <a href="../sales_ledger/index.php" class="p-3.5 rounded-2xl border border-slate-800 hover:border-emerald-500 bg-slate-800/80 hover:bg-slate-800 transition-all duration-200 flex flex-col items-center text-center justify-between min-h-[145px] group">
                <div class="w-11 h-11 rounded-xl bg-gradient-to-br from-emerald-600 to-teal-700 text-white flex items-center justify-center font-bold shadow-md group-hover:scale-105 transition-transform mb-2">
                    <i data-lucide="file-text" class="w-5 h-5"></i>
                </div>
                <div class="space-y-0.5 w-full">
                    <h4 class="font-extrabold text-xs text-white leading-tight">سیلز و منافع کھاتہ</h4>
                    <p class="text-[10px] text-slate-400 line-clamp-1">فروخت کا مکمل ریکارڈ و منافع</p>
                </div>
                <span class="mt-2 text-[10px] font-extrabold px-2 py-0.5 rounded-full border bg-emerald-500/10 text-emerald-400 border-emerald-800/40">
                    <?= number_format($totalSalesCount) ?> کل سیلز
                </span>
            </a>

            <!-- 3. Mobile Buy Register -->
            <a href="../purchase_register/index.php" class="p-3.5 rounded-2xl border border-slate-800 hover:border-cyan-500 bg-slate-800/80 hover:bg-slate-800 transition-all duration-200 flex flex-col items-center text-center justify-between min-h-[145px] group">
                <div class="w-11 h-11 rounded-xl bg-gradient-to-br from-cyan-600 to-blue-700 text-white flex items-center justify-center font-bold shadow-md group-hover:scale-105 transition-transform mb-2">
                    <i data-lucide="smartphone" class="w-5 h-5"></i>
                </div>
                <div class="space-y-0.5 w-full">
                    <h4 class="font-extrabold text-xs text-white leading-tight">موبائل خرید رجسٹر</h4>
                    <p class="text-[10px] text-slate-400 line-clamp-1">نئے و پرانے موبائل خرید و اقرار نامہ</p>
                </div>
                <span class="mt-2 text-[10px] font-extrabold px-2 py-0.5 rounded-full border bg-cyan-500/10 text-cyan-400 border-cyan-800/40">
                    موبائل خرید
                </span>
            </a>

            <!-- 4. Stock Inventory -->
            <a href="../inventory/index.php" class="p-3.5 rounded-2xl border border-slate-800 hover:border-indigo-500 bg-slate-800/80 hover:bg-slate-800 transition-all duration-200 flex flex-col items-center text-center justify-between min-h-[145px] group">
                <div class="w-11 h-11 rounded-xl bg-gradient-to-br from-indigo-600 to-purple-700 text-white flex items-center justify-center font-bold shadow-md group-hover:scale-105 transition-transform mb-2">
                    <i data-lucide="package" class="w-5 h-5"></i>
                </div>
                <div class="space-y-0.5 w-full">
                    <h4 class="font-extrabold text-xs text-white leading-tight">موبائل و سامان اسٹاک</h4>
                    <p class="text-[10px] text-slate-400 line-clamp-1">موبائل، چارجر و پارٹس گنتی</p>
                </div>
                <span class="mt-2 text-[10px] font-extrabold px-2 py-0.5 rounded-full border bg-indigo-500/10 text-indigo-400 border-indigo-800/40">
                    <?= number_format($stockQuery['items']) ?> آئٹمز (<?= number_format($stockQuery['qty']) ?> عدد)
                </span>
            </a>

            <!-- 5. Photo File Vault -->
            <a href="../photo_vault/index.php" class="p-3.5 rounded-2xl border border-slate-800 hover:border-amber-500 bg-slate-800/80 hover:bg-slate-800 transition-all duration-200 flex flex-col items-center text-center justify-between min-h-[145px] group">
                <div class="w-11 h-11 rounded-xl bg-gradient-to-br from-amber-600 to-orange-700 text-white flex items-center justify-center font-bold shadow-md group-hover:scale-105 transition-transform mb-2">
                    <i data-lucide="camera" class="w-5 h-5"></i>
                </div>
                <div class="space-y-0.5 w-full">
                    <h4 class="font-extrabold text-xs text-white leading-tight">شناختی کارڈ و فائل والٹ</h4>
                    <p class="text-[10px] text-slate-400 line-clamp-1">شناختی کارڈ فوٹو و دستاویزات والٹ</p>
                </div>
                <span class="mt-2 text-[10px] font-extrabold px-2 py-0.5 rounded-full border bg-amber-500/10 text-amber-400 border-amber-800/40">
                    محفوظ والٹ
                </span>
            </a>

            <!-- 6. Supplier Khata -->
            <a href="../supplier_khata/index.php" class="p-3.5 rounded-2xl border border-slate-800 hover:border-violet-500 bg-slate-800/80 hover:bg-slate-800 transition-all duration-200 flex flex-col items-center text-center justify-between min-h-[145px] group">
                <div class="w-11 h-11 rounded-xl bg-gradient-to-br from-violet-600 to-indigo-700 text-white flex items-center justify-center font-bold shadow-md group-hover:scale-105 transition-transform mb-2">
                    <i data-lucide="truck" class="w-5 h-5"></i>
                </div>
                <div class="space-y-0.5 w-full">
                    <h4 class="font-extrabold text-xs text-white leading-tight">سپلائر ڈائریکٹری و کھاتہ</h4>
                    <p class="text-[10px] text-slate-400 line-clamp-1">ہول سیلرز اور سپلائر ادھار ریکارڈ</p>
                </div>
                <span class="mt-2 text-[10px] font-extrabold px-2 py-0.5 rounded-full border bg-violet-500/10 text-violet-400 border-violet-800/40">
                    سپلائر کھاتہ
                </span>
            </a>

            <!-- 7. Mobile & IMEI Ledger -->
            <a href="../mobile_ledger/index.php" class="p-3.5 rounded-2xl border border-slate-800 hover:border-sky-500 bg-slate-800/80 hover:bg-slate-800 transition-all duration-200 flex flex-col items-center text-center justify-between min-h-[145px] group">
                <div class="w-11 h-11 rounded-xl bg-gradient-to-br from-sky-600 to-teal-700 text-white flex items-center justify-center font-bold shadow-md group-hover:scale-105 transition-transform mb-2">
                    <i data-lucide="binary" class="w-5 h-5"></i>
                </div>
                <div class="space-y-0.5 w-full">
                    <h4 class="font-extrabold text-xs text-white leading-tight">موبائل و IMEI لیجر</h4>
                    <p class="text-[10px] text-slate-400 line-clamp-1">آئی ایم ای آئی اور سیٹ آمد و رفت</p>
                </div>
                <span class="mt-2 text-[10px] font-extrabold px-2 py-0.5 rounded-full border bg-sky-500/10 text-sky-400 border-sky-800/40">
                    IMEI ٹریکنگ
                </span>
            </a>

            <!-- 8. Barcode Studio -->
            <a href="../barcode_studio/index.php" class="p-3.5 rounded-2xl border border-slate-800 hover:border-pink-500 bg-slate-800/80 hover:bg-slate-800 transition-all duration-200 flex flex-col items-center text-center justify-between min-h-[145px] group">
                <div class="w-11 h-11 rounded-xl bg-gradient-to-br from-pink-600 to-rose-700 text-white flex items-center justify-center font-bold shadow-md group-hover:scale-105 transition-transform mb-2">
                    <i data-lucide="scan-barcode" class="w-5 h-5"></i>
                </div>
                <div class="space-y-0.5 w-full">
                    <h4 class="font-extrabold text-xs text-white leading-tight">بارکوڈ اسٹوڈیو و پرنٹ</h4>
                    <p class="text-[10px] text-slate-400 line-clamp-1">اسٹیکر پرنٹنگ اور بارکوڈ جنریٹر</p>
                </div>
                <span class="mt-2 text-[10px] font-extrabold px-2 py-0.5 rounded-full border bg-pink-500/10 text-pink-400 border-pink-800/40">
                    لیبل پرنٹ
                </span>
            </a>

            <!-- 9. EasyPaisa Ledger -->
            <a href="../easypaisa_ledger/index.php" class="p-3.5 rounded-2xl border border-slate-800 hover:border-emerald-500 bg-slate-800/80 hover:bg-slate-800 transition-all duration-200 flex flex-col items-center text-center justify-between min-h-[145px] group">
                <div class="w-11 h-11 rounded-xl bg-gradient-to-br from-emerald-600 to-green-700 text-white flex items-center justify-center font-bold shadow-md group-hover:scale-105 transition-transform mb-2">
                    <i data-lucide="banknote" class="w-5 h-5"></i>
                </div>
                <div class="space-y-0.5 w-full">
                    <h4 class="font-extrabold text-xs text-white leading-tight">ایزی پیسہ رجسٹر</h4>
                    <p class="text-[10px] text-slate-400 line-clamp-1">کیش آن اور کیش آوٹ ٹرانزیکشنز</p>
                </div>
                <span class="mt-2 text-[10px] font-extrabold px-2 py-0.5 rounded-full border bg-emerald-500/10 text-emerald-400 border-emerald-800/40">
                    Rs. <?= number_format($currentEpBal) ?>
                </span>
            </a>

            <!-- 10. Customer Khata -->
            <a href="../customer_khata/index.php" class="p-3.5 rounded-2xl border border-slate-800 hover:border-teal-500 bg-slate-800/80 hover:bg-slate-800 transition-all duration-200 flex flex-col items-center text-center justify-between min-h-[145px] group">
                <div class="w-11 h-11 rounded-xl bg-gradient-to-br from-teal-600 to-emerald-700 text-white flex items-center justify-center font-bold shadow-md group-hover:scale-105 transition-transform mb-2">
                    <i data-lucide="users" class="w-5 h-5"></i>
                </div>
                <div class="space-y-0.5 w-full">
                    <h4 class="font-extrabold text-xs text-white leading-tight">کسٹمر ادھار کھاتہ</h4>
                    <p class="text-[10px] text-slate-400 line-clamp-1">کسٹمر ڈائریکٹری اور ادھار کھاتہ</p>
                </div>
                <span class="mt-2 text-[10px] font-extrabold px-2 py-0.5 rounded-full border bg-amber-500/10 text-amber-400 border-amber-800/40">
                    <?= $customerUdhar > 0 ? 'Rs. ' . number_format($customerUdhar) . ' ادھار' : 'ادھار کھاتہ' ?>
                </span>
            </a>

            <!-- 11. Reports -->
            <a href="../reports/index.php" class="p-3.5 rounded-2xl border border-slate-800 hover:border-blue-500 bg-slate-800/80 hover:bg-slate-800 transition-all duration-200 flex flex-col items-center text-center justify-between min-h-[145px] group">
                <div class="w-11 h-11 rounded-xl bg-gradient-to-br from-blue-600 to-indigo-700 text-white flex items-center justify-center font-bold shadow-md group-hover:scale-105 transition-transform mb-2">
                    <i data-lucide="bar-chart-3" class="w-5 h-5"></i>
                </div>
                <div class="space-y-0.5 w-full">
                    <h4 class="font-extrabold text-xs text-white leading-tight">رپورٹس و تجزیات</h4>
                    <p class="text-[10px] text-slate-400 line-clamp-1">ماہانہ نفع نقصان و آڈٹ رپورٹ</p>
                </div>
                <span class="mt-2 text-[10px] font-extrabold px-2 py-0.5 rounded-full border bg-blue-500/10 text-blue-400 border-blue-800/40">
                    رپورٹ ڈاؤن لوڈ
                </span>
            </a>

            <!-- 12. Settings -->
            <a href="../settings/index.php" class="p-3.5 rounded-2xl border border-slate-800 hover:border-slate-600 bg-slate-800/80 hover:bg-slate-800 transition-all duration-200 flex flex-col items-center text-center justify-between min-h-[145px] group">
                <div class="w-11 h-11 rounded-xl bg-gradient-to-br from-slate-700 to-slate-900 text-white flex items-center justify-center font-bold shadow-md group-hover:scale-105 transition-transform mb-2">
                    <i data-lucide="settings" class="w-5 h-5"></i>
                </div>
                <div class="space-y-0.5 w-full">
                    <h4 class="font-extrabold text-xs text-white leading-tight">سیٹنگز و سیکیورٹی</h4>
                    <p class="text-[10px] text-slate-400 line-clamp-1">دکان کی معلومات، سیکیورٹی و پن</p>
                </div>
                <span class="mt-2 text-[10px] font-extrabold px-2 py-0.5 rounded-full border bg-slate-500/10 text-slate-400 border-slate-800/40">
                    PIN: <?= htmlspecialchars($settings['pinCode'] ?? '6242') ?>
                </span>
            </a>

        </div>
    </div>

    <!-- DAILY SALES & PROFIT TRENDS GRAPH (CHART.JS) -->
    <div class="p-4 sm:p-5 rounded-2xl bg-slate-900 border border-slate-800 shadow-sm">
        <div class="flex flex-col sm:flex-row sm:items-center justify-between mb-4 gap-2">
            <div>
                <h3 class="text-sm sm:text-base font-black text-white flex items-center gap-2">
                    <i data-lucide="trending-up" class="w-4.5 h-4.5 text-blue-400"></i>
                    <span>پچھلے 7 دنوں کی روزانہ فروخت و منافع کی صورتحال</span>
                </h3>
                <p class="text-[11px] text-slate-400 mt-0.5">
                    کاروباری حجم (Sales Volume) اور خالص منافع (Net Profit) کا روزانہ تصویری چارٹ
                </p>
            </div>
            <div class="flex items-center gap-4 text-xs font-semibold">
                <div class="flex items-center gap-1.5">
                    <span class="w-2.5 h-2.5 rounded-full bg-blue-500"></span>
                    <span class="text-slate-300">کل فروخت (Sales)</span>
                </div>
                <div class="flex items-center gap-1.5">
                    <span class="w-2.5 h-2.5 rounded-full bg-emerald-500"></span>
                    <span class="text-slate-300">خالص منافع (Profit)</span>
                </div>
            </div>
        </div>

        <div class="w-full h-64 sm:h-72">
            <canvas id="salesTrendsChart"></canvas>
        </div>
    </div>

    <!-- LIVE TRANSACTIONS & SALES TABLE FEED -->
    <div class="bg-slate-900 border border-slate-800 rounded-2xl overflow-hidden shadow-sm">
        <div class="p-3.5 sm:p-4 bg-slate-800/60 border-b border-slate-800 flex flex-col sm:flex-row items-stretch sm:items-center justify-between gap-3">
            <div class="flex items-center gap-2">
                <h3 class="font-extrabold text-xs sm:text-sm text-white">
                    آج کے روزنامچہ اندراجات (Today Entries - <?= htmlspecialchars($selectedDate) ?>)
                </h3>
                <span class="px-2 py-0.5 rounded-full text-xs font-mono font-bold bg-slate-800 text-slate-300 border border-slate-700" id="liveEntriesCountBadge">
                    <?= count($dayTrxList) + count($daySalesList) ?> ریکارڈز
                </span>
            </div>

            <!-- Search input in JS -->
            <div class="relative flex-1 sm:w-72">
                <input type="text" id="tableSearchInput" placeholder="گاہک، فون، انوائس یا تفصیل تلاش کریں..." onkeyup="filterLiveTable()" class="w-full pl-3 pr-3 py-1.5 bg-slate-800 border border-slate-700 text-white rounded-xl text-xs outline-none focus:border-blue-500">
            </div>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-right text-xs" id="liveEntriesTable">
                <thead class="bg-slate-950 text-slate-400 uppercase font-bold text-[11px] border-b border-slate-800">
                    <tr>
                        <th class="py-3 px-3 sm:px-4">وقت</th>
                        <th class="py-3 px-3 sm:px-4">قسم</th>
                        <th class="py-3 px-3 sm:px-4">گاہک کا نام</th>
                        <th class="py-3 px-3 sm:px-4">چینل / TRX ID</th>
                        <th class="py-3 px-3 sm:px-4">رقم (حجم)</th>
                        <th class="py-3 px-3 sm:px-4">منافع / فیس</th>
                        <th class="py-3 px-3 sm:px-4 text-center">ایکشن</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-800/60 font-medium text-slate-200">
                    <?php if (empty($daySalesList) && empty($dayTrxList)): ?>
                        <tr id="emptyRow">
                            <td colspan="7" class="py-8 text-center text-slate-500">
                                <p class="text-sm font-bold">منتخب تاریخ (<?= htmlspecialchars($selectedDate) ?>) کا کوئی اندراج نہیں ملا</p>
                                <p class="text-xs mt-1">نیا بل بنانے یا Buy / Sell اندراج کے لیے اوپر والے بٹن استعمال کریں۔</p>
                            </td>
                        </tr>
                    <?php else: ?>
                        <!-- Product POS Sales -->
                        <?php foreach($daySalesList as $s): ?>
                            <tr class="hover:bg-slate-800/40 transition-colors entry-row">
                                <td class="py-3 px-3 sm:px-4 font-mono text-slate-400"><?= htmlspecialchars($s['time'] ?? '12:00') ?></td>
                                <td class="py-3 px-3 sm:px-4">
                                    <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full bg-blue-500/10 text-blue-400 border border-blue-500/20 font-bold text-[11px]">
                                        <i data-lucide="shopping-cart" class="w-3 h-3"></i> POS بل
                                    </span>
                                </td>
                                <td class="py-3 px-3 sm:px-4 font-semibold text-white customer-col">
                                    <?= htmlspecialchars($s['customer_name'] ?? 'Walk-in') ?>
                                    <?php if (!empty($s['customer_phone'])): ?>
                                        <span class="block text-[10px] font-mono text-slate-400"><?= htmlspecialchars($s['customer_phone']) ?></span>
                                    <?php endif; ?>
                                </td>
                                <td class="py-3 px-3 sm:px-4 font-mono text-slate-300 desc-col">
                                    انوائس: <?= htmlspecialchars($s['invoice_no']) ?>
                                    <span class="text-[10px] text-slate-400 block"><?= htmlspecialchars($s['payment_method']) ?></span>
                                </td>
                                <td class="py-3 px-3 sm:px-4 font-mono font-bold text-white">
                                    Rs. <?= number_format($s['net_amount']) ?>
                                </td>
                                <td class="py-3 px-3 sm:px-4 font-mono font-bold text-emerald-400">
                                    +Rs. <?= number_format($s['profit']) ?>
                                </td>
                                <td class="py-3 px-3 sm:px-4 text-center">
                                    <div class="flex items-center justify-center gap-1.5">
                                        <a href="../sales_ledger/index.php" class="p-1.5 rounded-lg bg-slate-800 hover:bg-slate-700 text-blue-400 transition-colors" title="تفصیل دیکھیں">
                                            <i data-lucide="eye" class="w-3.5 h-3.5"></i>
                                        </a>
                                        <form method="POST" action="index.php" onsubmit="return confirm('کیا آپ واقعی یہ سیل ریکارڈ ڈیلیٹ کرنا چاہتے ہیں؟');" class="inline">
                                            <input type="hidden" name="action" value="delete_entry">
                                            <input type="hidden" name="table_type" value="sales">
                                            <input type="hidden" name="entry_id" value="<?= htmlspecialchars($s['id']) ?>">
                                            <button type="submit" class="p-1.5 rounded-lg bg-slate-800 hover:bg-rose-900/60 text-rose-400 transition-colors" title="ڈیلیٹ کریں">
                                                <i data-lucide="trash-2" class="w-3.5 h-3.5"></i>
                                            </button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>

                        <!-- Buy / Sell / Expense Transactions -->
                        <?php foreach($dayTrxList as $t): 
                            $isBuy = str_starts_with($t['type'], 'BUY');
                            $isSell = str_starts_with($t['type'], 'SELL');
                            $isExp = in_array($t['type'], ['EXPENSE', 'DISCREPANCY_LOSS']);
                            $channelName = $t['payment_method'] ?? 'EASYPAISA';
                        ?>
                            <tr class="hover:bg-slate-800/40 transition-colors entry-row">
                                <td class="py-3 px-3 sm:px-4 font-mono text-slate-400"><?= htmlspecialchars($t['time'] ?? '12:00') ?></td>
                                <td class="py-3 px-3 sm:px-4">
                                    <?php if ($isBuy): ?>
                                        <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full bg-emerald-500/10 text-emerald-400 border border-emerald-500/20 font-bold text-[11px]">
                                            <i data-lucide="arrow-down-right" class="w-3 h-3"></i> BUY (کیش آؤٹ)
                                        </span>
                                    <?php elseif ($isSell): ?>
                                        <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full bg-amber-500/10 text-amber-400 border border-amber-500/20 font-bold text-[11px]">
                                            <i data-lucide="arrow-up-right" class="w-3 h-3"></i> SELL (کیش ان)
                                        </span>
                                    <?php else: ?>
                                        <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full bg-rose-500/10 text-rose-400 border border-rose-500/20 font-bold text-[11px]">
                                            <i data-lucide="trending-down" class="w-3 h-3"></i> دکان خرچہ
                                        </span>
                                    <?php endif; ?>
                                </td>
                                <td class="py-3 px-3 sm:px-4 font-semibold text-white customer-col">
                                    <?= htmlspecialchars($t['customer_name'] ?? 'Walk-in') ?>
                                    <?php if (!empty($t['customer_phone'])): ?>
                                        <span class="block text-[10px] font-mono text-slate-400"><?= htmlspecialchars($t['customer_phone']) ?></span>
                                    <?php endif; ?>
                                </td>
                                <td class="py-3 px-3 sm:px-4 font-mono text-slate-300 desc-col">
                                    <span class="font-bold text-slate-200"><?= htmlspecialchars($channelName) ?></span>
                                    <?php if (!empty($t['trx_id'])): ?>
                                        <span class="text-[10px] text-slate-400 block font-mono">ID: <?= htmlspecialchars($t['trx_id']) ?></span>
                                    <?php elseif (!empty($t['note'])): ?>
                                        <span class="text-[10px] text-slate-400 block"><?= htmlspecialchars($t['note']) ?></span>
                                    <?php endif; ?>
                                </td>
                                <td class="py-3 px-3 sm:px-4 font-mono font-bold text-white">
                                    Rs. <?= number_format($t['easy_paisa_amount'] > 0 ? $t['easy_paisa_amount'] : $t['cash_amount']) ?>
                                </td>
                                <td class="py-3 px-3 sm:px-4 font-mono font-bold">
                                    <?php if ($isExp): ?>
                                        <span class="text-rose-400">-Rs. <?= number_format($t['expense_amount']) ?></span>
                                    <?php else: ?>
                                        <span class="text-emerald-400">+Rs. <?= number_format($t['fee_profit']) ?></span>
                                    <?php endif; ?>
                                </td>
                                <td class="py-3 px-3 sm:px-4 text-center">
                                    <div class="flex items-center justify-center gap-1.5">
                                        <?php if (!$isExp): ?>
                                            <button type="button" onclick="previewSlip(<?= htmlspecialchars(json_encode([
                                                'id' => $t['id'],
                                                'direction' => $isBuy ? 'BUY' : 'SELL',
                                                'type' => $t['type'],
                                                'channel' => $channelName,
                                                'customer_name' => $t['customer_name'],
                                                'customer_phone' => $t['customer_phone'],
                                                'cnic' => $t['cnic'] ?? '',
                                                'amount' => (float)$t['easy_paisa_amount'],
                                                'fee' => (float)$t['fee_profit'],
                                                'cash_amount' => (float)$t['cash_amount'],
                                                'trx_id' => $t['trx_id'] ?? '',
                                                'date' => $t['date'],
                                                'time' => $t['time'],
                                                'notes' => $t['note'] ?? ''
                                            ])) ?>)" class="p-1.5 rounded-lg bg-slate-800 hover:bg-emerald-900/60 text-emerald-400 transition-colors" title="رسید پرنٹ کریں">
                                                <i data-lucide="printer" class="w-3.5 h-3.5"></i>
                                            </button>
                                        <?php endif; ?>
                                        <form method="POST" action="index.php" onsubmit="return confirm('کیا آپ واقعی یہ اندراج ڈیلیٹ کرنا چاہتے ہیں؟');" class="inline">
                                            <input type="hidden" name="action" value="delete_entry">
                                            <input type="hidden" name="table_type" value="transactions">
                                            <input type="hidden" name="entry_id" value="<?= htmlspecialchars($t['id']) ?>">
                                            <button type="submit" class="p-1.5 rounded-lg bg-slate-800 hover:bg-rose-900/60 text-rose-400 transition-colors" title="ڈیلیٹ کریں">
                                                <i data-lucide="trash-2" class="w-3.5 h-3.5"></i>
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

<!-- ============================================================== -->
<!-- 1. MODAL: NEW BUY / SELL ENTRY (مکمل پاکستانی موبائل شاپ انٹری) -->
<!-- ============================================================== -->
<div id="buySellModal" class="fixed inset-0 z-50 bg-black/80 backdrop-blur-sm hidden items-center justify-center p-3 sm:p-4 overflow-y-auto no-print">
    <div class="bg-slate-900 border border-slate-700 rounded-3xl w-full max-w-xl shadow-2xl overflow-hidden animate-scale-in">
        
        <!-- Modal Header -->
        <div class="p-4 sm:p-5 bg-slate-800/80 border-b border-slate-700 flex items-center justify-between">
            <div class="flex items-center gap-3">
                <div id="modalHeaderIcon" class="w-10 h-10 rounded-2xl bg-emerald-600 text-white flex items-center justify-center font-bold shadow-md">
                    <i data-lucide="arrow-down-right" class="w-5 h-5"></i>
                </div>
                <div>
                    <h3 id="modalTitle" class="text-base sm:text-lg font-black text-white">
                        نیا Buy / Sell اندراج (EasyPaisa & Bank Transfer)
                    </h3>
                    <p class="text-xs text-slate-400">
                        آن لائن ٹرانسفر، کیش ادائیگی اور فیس کا خودکار حساب
                    </p>
                </div>
            </div>
            <button type="button" onclick="closeBuySellModal()" class="p-2 rounded-xl text-slate-400 hover:text-white bg-slate-800 hover:bg-slate-700">
                <i data-lucide="x" class="w-5 h-5"></i>
            </button>
        </div>

        <!-- Form Body -->
        <form method="POST" action="index.php" id="buySellForm" class="p-4 sm:p-6 space-y-4">
            <input type="hidden" name="action" value="save_buy_sell">
            <input type="hidden" name="direction" id="formDirection" value="BUY">
            <input type="hidden" name="print_receipt" id="formPrintReceipt" value="0">

            <!-- 1. BIG ACTION CARDS (BUY vs SELL) -->
            <div>
                <label class="block text-xs font-bold text-slate-300 mb-1.5">1. ٹرانزیکشن کی قسم منتخب کریں:</label>
                <div class="grid grid-cols-2 gap-3">
                    <button type="button" onclick="setDirection('BUY')" id="btnDirBuy" class="p-3.5 rounded-2xl border-2 transition-all flex flex-col items-center text-center cursor-pointer bg-emerald-950/40 border-emerald-500 text-emerald-300 shadow-md shadow-emerald-500/10">
                        <div class="flex items-center gap-2 font-black text-sm mb-1">
                            <i data-lucide="arrow-down-right" class="w-4 h-4"></i>
                            <span>BUY (Cash Out)</span>
                        </div>
                        <span class="text-[11px] text-slate-300">آن لائن رقم لی &rarr; کیش دیا</span>
                    </button>

                    <button type="button" onclick="setDirection('SELL')" id="btnDirSell" class="p-3.5 rounded-2xl border-2 transition-all flex flex-col items-center text-center cursor-pointer bg-slate-800/60 border-slate-700 text-slate-400 hover:text-white">
                        <div class="flex items-center gap-2 font-black text-sm mb-1">
                            <i data-lucide="arrow-up-right" class="w-4 h-4"></i>
                            <span>SELL (Cash In)</span>
                        </div>
                        <span class="text-[11px] text-slate-300">کیش لیا &rarr; رقم آن لائن بھیجی</span>
                    </button>
                </div>
            </div>

            <!-- 2. PAYMENT CHANNEL / WALLET SELECTOR -->
            <div>
                <label class="block text-xs font-bold text-slate-300 mb-1.5">2. اکاؤنٹ / والٹ منتخب کریں:</label>
                <div class="grid grid-cols-3 sm:grid-cols-4 gap-2">
                    <?php 
                    $channels = [
                        ['id' => 'EASYPAISA', 'name' => 'EasyPaisa', 'color' => 'emerald'],
                        ['id' => 'JAZZCASH', 'name' => 'JazzCash', 'color' => 'amber'],
                        ['id' => 'SADAPAY', 'name' => 'SadaPay', 'color' => 'teal'],
                        ['id' => 'NAYA_PAY', 'name' => 'NayaPay', 'color' => 'orange'],
                        ['id' => 'MEEZAN_BANK', 'name' => 'Meezan Bank', 'color' => 'blue'],
                        ['id' => 'UBL_BANK', 'name' => 'UBL Bank', 'color' => 'cyan'],
                        ['id' => 'HBL_BANK', 'name' => 'HBL Bank', 'color' => 'green'],
                        ['id' => 'BANK_TRANSFER', 'name' => 'دیگر بینک', 'color' => 'indigo']
                    ];
                    foreach ($channels as $idx => $ch):
                    ?>
                        <label class="relative flex items-center justify-center p-2.5 rounded-xl border border-slate-700 bg-slate-800 hover:bg-slate-750 cursor-pointer text-center transition-all text-xs font-bold text-slate-200 has-[:checked]:border-blue-500 has-[:checked]:bg-blue-600/20 has-[:checked]:text-blue-300">
                            <input type="radio" name="payment_method" value="<?= $ch['id'] ?>" class="sr-only" <?= $idx === 0 ? 'checked' : '' ?>>
                            <span><?= $ch['name'] ?></span>
                        </label>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- 3. AMOUNT & QUICK CHIPS -->
            <div>
                <div class="flex items-center justify-between mb-1.5">
                    <label class="text-xs font-bold text-slate-300">3. ٹرانسفر رقم (Amount in PKR):</label>
                    <span class="text-[11px] font-mono text-emerald-400 font-bold" id="displayAmount">Rs. 0</span>
                </div>
                <div class="relative">
                    <span class="absolute right-3.5 top-2.5 text-slate-400 font-bold text-sm">Rs.</span>
                    <input type="number" id="inputAmount" name="amount" required min="1" step="any" placeholder="رقم درج کریں..." oninput="calculateNet()" class="w-full pl-3 pr-11 py-2.5 bg-slate-800 border border-slate-700 rounded-xl text-white font-mono font-bold text-base outline-none focus:border-blue-500">
                </div>

                <!-- Quick Amount Chips -->
                <div class="flex items-center gap-1.5 flex-wrap mt-2">
                    <?php foreach([500, 1000, 2000, 3000, 5000, 10000, 20000, 25000, 50000] as $amt): ?>
                        <button type="button" onclick="setQuickAmount(<?= $amt ?>)" class="px-2.5 py-1 rounded-lg bg-slate-800 hover:bg-slate-700 text-slate-300 hover:text-white border border-slate-700 text-xs font-mono font-bold transition-all">
                            <?= $amt >= 1000 ? ($amt / 1000) . 'k' : $amt ?>
                        </button>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- 4. FEE / PROFIT & AUTO SLAB -->
            <div>
                <div class="flex items-center justify-between mb-1.5">
                    <label class="text-xs font-bold text-slate-300">4. سروس فیس / منافع (Fee / Profit):</label>
                    <button type="button" onclick="applyAutoFeeSlab()" class="text-[11px] font-bold text-blue-400 hover:underline flex items-center gap-1">
                        <i data-lucide="sparkles" class="w-3 h-3"></i>
                        <span>خودکار مارکیٹ سلیب لگائیں</span>
                    </button>
                </div>
                <input type="number" id="inputFee" name="fee_profit" value="0" min="0" step="any" placeholder="فیس..." oninput="calculateNet()" class="w-full px-3 py-2 bg-slate-800 border border-slate-700 rounded-xl text-white font-mono font-bold text-sm outline-none focus:border-blue-500">

                <!-- Quick Fee Presets -->
                <div class="flex items-center gap-1.5 flex-wrap mt-1.5">
                    <?php foreach([20, 30, 50, 100, 150, 200, 250] as $fee): ?>
                        <button type="button" onclick="setQuickFee(<?= $fee ?>)" class="px-2 py-0.5 rounded-md bg-slate-800 hover:bg-emerald-950 text-emerald-400 border border-slate-700 text-[11px] font-mono font-bold transition-all">
                            Rs. <?= $fee ?>
                        </button>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- 5. LIVE SUMMARY BANNER -->
            <div id="liveSummaryBanner" class="p-3.5 rounded-2xl bg-emerald-950/30 border border-emerald-500/30 flex items-center justify-between">
                <div>
                    <span id="summaryActionLabel" class="text-xs text-slate-300 block">گاہک کو نقد ادائیگی (Cash to Give):</span>
                    <div id="summaryCashVal" class="text-lg sm:text-xl font-black font-mono text-emerald-400">Rs. 0</div>
                </div>
                <div class="text-left border-r border-slate-700 pr-4">
                    <span class="text-[11px] text-slate-400 block">شاپ کا خالص منافع:</span>
                    <div id="summaryFeeVal" class="text-sm font-bold font-mono text-emerald-300">+Rs. 0</div>
                </div>
            </div>

            <!-- 6. CUSTOMER & TRANSACTION DETAILS -->
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 pt-2 border-t border-slate-800">
                <div>
                    <label class="block text-xs font-bold text-slate-300 mb-1">گاہک کا نام:</label>
                    <input type="text" name="customer_name" list="customerSuggestions" placeholder="نام درج کریں..." class="w-full px-3 py-2 bg-slate-800 border border-slate-700 rounded-xl text-white text-xs outline-none focus:border-blue-500">
                    <datalist id="customerSuggestions">
                        <?php foreach($customersList as $cust): ?>
                            <option value="<?= htmlspecialchars($cust['name']) ?>"><?= htmlspecialchars($cust['phone'] ?? '') ?></option>
                        <?php endforeach; ?>
                    </datalist>
                </div>

                <div>
                    <label class="block text-xs font-bold text-slate-300 mb-1">گاہک موبائل نمبر:</label>
                    <input type="text" name="customer_phone" placeholder="03XXXXXXXXX" class="w-full px-3 py-2 bg-slate-800 border border-slate-700 rounded-xl text-white text-xs font-mono outline-none focus:border-blue-500">
                </div>

                <div>
                    <label class="block text-xs font-bold text-slate-300 mb-1">TID / TRX ID / رسید کوڈ (اختیاری):</label>
                    <input type="text" name="trx_id" placeholder="مثلاً: 2849104829" class="w-full px-3 py-2 bg-slate-800 border border-slate-700 rounded-xl text-white text-xs font-mono outline-none focus:border-blue-500">
                </div>

                <div>
                    <label class="block text-xs font-bold text-slate-300 mb-1">شناختی کارڈ نمبر (CNIC):</label>
                    <input type="text" name="cnic" placeholder="35202-XXXXXXX-X" class="w-full px-3 py-2 bg-slate-800 border border-slate-700 rounded-xl text-white text-xs font-mono outline-none focus:border-blue-500">
                </div>
            </div>

            <!-- Date & Time Override -->
            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="block text-xs text-slate-400 mb-1">تاریخ:</label>
                    <input type="date" name="date" value="<?= $todayDate ?>" class="w-full px-3 py-1.5 bg-slate-800 border border-slate-700 rounded-xl text-slate-300 text-xs font-mono outline-none">
                </div>
                <div>
                    <label class="block text-xs text-slate-400 mb-1">وقت:</label>
                    <input type="text" name="time" value="<?= $nowTime ?>" class="w-full px-3 py-1.5 bg-slate-800 border border-slate-700 rounded-xl text-slate-300 text-xs font-mono outline-none">
                </div>
            </div>

            <!-- Notes -->
            <div>
                <input type="text" name="notes" placeholder="اضافی تفصیل یا نوٹ (اختیاری)..." class="w-full px-3 py-2 bg-slate-800 border border-slate-700 rounded-xl text-slate-300 text-xs outline-none">
            </div>

            <!-- Form Action Buttons -->
            <div class="pt-3 border-t border-slate-800 flex items-center justify-end gap-2.5">
                <button type="button" onclick="closeBuySellModal()" class="px-4 py-2.5 rounded-xl bg-slate-800 hover:bg-slate-700 text-slate-300 text-xs font-bold transition-all">
                    منسوخ کریں
                </button>

                <button type="submit" onclick="document.getElementById('formPrintReceipt').value='0'" class="px-5 py-2.5 rounded-xl bg-slate-700 hover:bg-slate-600 text-white text-xs font-black shadow-sm transition-all cursor-pointer">
                    صرف محفوظ کریں
                </button>

                <button type="submit" onclick="document.getElementById('formPrintReceipt').value='1'" class="px-5 py-2.5 rounded-xl bg-emerald-600 hover:bg-emerald-500 text-white text-xs font-black flex items-center gap-1.5 shadow-lg shadow-emerald-600/30 transition-all cursor-pointer">
                    <i data-lucide="printer" class="w-4 h-4"></i>
                    <span>محفوظ و پرنٹ رسید</span>
                </button>
            </div>

        </form>
    </div>
</div>

<!-- ============================================================== -->
<!-- 2. MODAL: EXPENSE ENTRY (دکان کا خرچہ اندراج) -->
<!-- ============================================================== -->
<div id="expenseModal" class="fixed inset-0 z-50 bg-black/80 backdrop-blur-sm hidden items-center justify-center p-3 sm:p-4 overflow-y-auto no-print">
    <div class="bg-slate-900 border border-slate-700 rounded-3xl w-full max-w-md shadow-2xl overflow-hidden animate-scale-in">
        <div class="p-4 bg-slate-800/80 border-b border-slate-700 flex items-center justify-between">
            <div class="flex items-center gap-2.5">
                <div class="w-9 h-9 rounded-xl bg-rose-600 text-white flex items-center justify-center font-bold">
                    <i data-lucide="minus-circle" class="w-5 h-5"></i>
                </div>
                <h3 class="text-sm sm:text-base font-black text-white">دکان کا خرچہ درج کریں</h3>
            </div>
            <button type="button" onclick="closeExpenseModal()" class="p-1.5 rounded-lg text-slate-400 hover:text-white bg-slate-800">
                <i data-lucide="x" class="w-4 h-4"></i>
            </button>
        </div>

        <form method="POST" action="index.php" class="p-4 sm:p-5 space-y-4">
            <input type="hidden" name="action" value="save_expense">

            <div>
                <label class="block text-xs font-bold text-slate-300 mb-1">خرچے کی رقم (PKR):</label>
                <input type="number" name="expense_amount" required min="1" step="any" placeholder="رقم درج کریں..." class="w-full px-3 py-2.5 bg-slate-800 border border-slate-700 rounded-xl text-white font-mono font-bold text-base outline-none focus:border-rose-500">
            </div>

            <div>
                <label class="block text-xs font-bold text-slate-300 mb-1">خرچے کی قسم / کیٹیگری:</label>
                <div class="grid grid-cols-2 gap-1.5 mb-2">
                    <?php foreach(['چائے و کھانا', 'دکان کرایہ', 'بجلی بل', 'اسٹیشنری و پرنٹ', 'ملازم تنخواہ', 'متفرق خرچہ'] as $cat): ?>
                        <button type="button" onclick="document.getElementById('expCatInput').value='<?= $cat ?>'" class="p-2 rounded-xl bg-slate-800 hover:bg-slate-700 text-slate-300 text-xs font-bold border border-slate-700 text-center">
                            <?= $cat ?>
                        </button>
                    <?php endforeach; ?>
                </div>
                <input type="text" id="expCatInput" name="category" value="چائے و کھانا" class="w-full px-3 py-2 bg-slate-800 border border-slate-700 rounded-xl text-white text-xs outline-none">
            </div>

            <div>
                <label class="block text-xs font-bold text-slate-300 mb-1">اضافی تفصیل یا نوٹ:</label>
                <input type="text" name="notes" placeholder="مثلاً: مہمانوں کی چائے، شاپ صفائی..." class="w-full px-3 py-2 bg-slate-800 border border-slate-700 rounded-xl text-slate-300 text-xs outline-none">
            </div>

            <div class="pt-3 border-t border-slate-800 flex items-center justify-end gap-2">
                <button type="button" onclick="closeExpenseModal()" class="px-4 py-2 rounded-xl bg-slate-800 text-slate-300 text-xs font-bold">
                    منسوخ
                </button>
                <button type="submit" class="px-5 py-2 rounded-xl bg-rose-600 hover:bg-rose-500 text-white text-xs font-black shadow-lg shadow-rose-600/30">
                    خرچہ محفوظ کریں
                </button>
            </div>
        </form>
    </div>
</div>

<!-- ============================================================== -->
<!-- 3. MODAL: OPENING BALANCE (صبح کا اوپننگ بیلنس) -->
<!-- ============================================================== -->
<div id="openingModal" class="fixed inset-0 z-50 bg-black/80 backdrop-blur-sm hidden items-center justify-center p-3 sm:p-4 overflow-y-auto no-print">
    <div class="bg-slate-900 border border-slate-700 rounded-3xl w-full max-w-md shadow-2xl overflow-hidden animate-scale-in">
        <div class="p-4 bg-slate-800/80 border-b border-slate-700 flex items-center justify-between">
            <div class="flex items-center gap-2.5">
                <div class="w-9 h-9 rounded-xl bg-teal-600 text-white flex items-center justify-center font-bold">
                    <i data-lucide="coins" class="w-5 h-5"></i>
                </div>
                <h3 class="text-sm sm:text-base font-black text-white">صبح کا اوپننگ بیلنس درج کریں</h3>
            </div>
            <button type="button" onclick="closeOpeningModal()" class="p-1.5 rounded-lg text-slate-400 hover:text-white bg-slate-800">
                <i data-lucide="x" class="w-4 h-4"></i>
            </button>
        </div>

        <form method="POST" action="index.php" class="p-4 sm:p-5 space-y-4">
            <input type="hidden" name="action" value="save_opening_balance">
            <input type="hidden" name="date" value="<?= htmlspecialchars($selectedDate) ?>">

            <div>
                <label class="block text-xs font-bold text-slate-300 mb-1">کیش دراز کا اوپننگ بیلنس (Cash in Drawer):</label>
                <input type="number" name="opening_cash" value="<?= $openingCash ?>" step="any" class="w-full px-3 py-2.5 bg-slate-800 border border-slate-700 rounded-xl text-white font-mono font-bold text-base outline-none focus:border-teal-500">
            </div>

            <div>
                <label class="block text-xs font-bold text-slate-300 mb-1">ایزی پیسہ / بینک والٹ کا اوپننگ بیلنس:</label>
                <input type="number" name="opening_easypaisa" value="<?= $openingEp ?>" step="any" class="w-full px-3 py-2.5 bg-slate-800 border border-slate-700 rounded-xl text-white font-mono font-bold text-base outline-none focus:border-teal-500">
            </div>

            <div>
                <label class="block text-xs font-bold text-slate-300 mb-1">نوٹ:</label>
                <input type="text" name="notes" placeholder="اوپننگ بیلنس نوٹ..." class="w-full px-3 py-2 bg-slate-800 border border-slate-700 rounded-xl text-slate-300 text-xs outline-none">
            </div>

            <div class="pt-3 border-t border-slate-800 flex items-center justify-end gap-2">
                <button type="button" onclick="closeOpeningModal()" class="px-4 py-2 rounded-xl bg-slate-800 text-slate-300 text-xs font-bold">
                    منسوخ
                </button>
                <button type="submit" class="px-5 py-2 rounded-xl bg-teal-600 hover:bg-teal-500 text-white text-xs font-black shadow-lg shadow-teal-600/30">
                    اوپننگ بیلنس محفوظ کریں
                </button>
            </div>
        </form>
    </div>
</div>

<!-- ============================================================== -->
<!-- 4. MODAL: THERMAL SLIP & VOUCHER PRINT PREVIEW -->
<!-- ============================================================== -->
<div id="thermalReceiptModal" class="fixed inset-0 z-50 bg-black/85 backdrop-blur-sm hidden items-center justify-center p-3 sm:p-4 overflow-y-auto">
    <div class="bg-white text-black rounded-2xl w-full max-w-sm shadow-2xl overflow-hidden animate-scale-in">
        
        <!-- Slip Header for Screen -->
        <div class="p-3 bg-slate-100 border-b flex items-center justify-between no-print">
            <span class="text-xs font-black text-slate-700 flex items-center gap-1.5">
                <i data-lucide="printer" class="w-4 h-4 text-emerald-600"></i>
                تھرمل رسید واؤچر (58mm / 80mm)
            </span>
            <button type="button" onclick="closeReceiptModal()" class="p-1 text-slate-500 hover:text-black">
                <i data-lucide="x" class="w-4 h-4"></i>
            </button>
        </div>

        <!-- Printable Receipt Content -->
        <div id="printableSlip" class="p-4 sm:p-5 font-mono text-center text-xs space-y-2">
            <div class="border-b border-dashed border-gray-400 pb-2">
                <h2 class="text-base font-black tracking-tight" id="slipShopName"><?= $shopName ?></h2>
                <p class="text-[10px] text-gray-600">موبائل، ایزی پیسہ و بینک ٹرانسفر کاؤنٹر</p>
                <p class="text-[10px] text-gray-500" id="slipPhone"><?= htmlspecialchars($settings['phone'] ?? '0300-1234567') ?></p>
            </div>

            <div class="py-1 border-b border-dashed border-gray-400 text-left text-[11px] space-y-1">
                <div class="flex justify-between">
                    <span class="text-gray-500">قسم:</span>
                    <strong class="font-black" id="slipType">BUY (کیش آؤٹ)</strong>
                </div>
                <div class="flex justify-between">
                    <span class="text-gray-500">چینل:</span>
                    <strong id="slipChannel">EASYPAISA</strong>
                </div>
                <div class="flex justify-between">
                    <span class="text-gray-500">گاہک:</span>
                    <strong id="slipCustomer">Walk-in</strong>
                </div>
                <div class="flex justify-between" id="slipPhoneRow">
                    <span class="text-gray-500">فون:</span>
                    <span id="slipCustomerPhone">-</span>
                </div>
                <div class="flex justify-between" id="slipTrxIdRow">
                    <span class="text-gray-500">TRX ID:</span>
                    <span id="slipTrxId">-</span>
                </div>
                <div class="flex justify-between">
                    <span class="text-gray-500">تاریخ و وقت:</span>
                    <span id="slipDateTime">-</span>
                </div>
            </div>

            <div class="py-2 border-b-2 border-dashed border-black space-y-1.5 text-left text-xs">
                <div class="flex justify-between text-gray-600">
                    <span>ٹرانسفر رقم:</span>
                    <span class="font-bold" id="slipTransferAmount">Rs. 0</span>
                </div>
                <div class="flex justify-between text-gray-600">
                    <span>سروس فیس:</span>
                    <span class="font-bold" id="slipFee">Rs. 0</span>
                </div>
                <div class="flex justify-between text-sm font-black text-black pt-1 border-t border-gray-300">
                    <span id="slipNetLabel">نقد ادائیگی:</span>
                    <span id="slipCashAmount">Rs. 0</span>
                </div>
            </div>

            <div class="pt-2 text-[10px] text-gray-500 space-y-0.5">
                <p>ہم پر اعتماد کا شکریہ!</p>
                <p>کمپیوٹرائزڈ بلال موبائل پی او ایس رسید</p>
            </div>
        </div>

        <!-- Slip Action Buttons -->
        <div class="p-3 bg-slate-100 border-t flex items-center justify-between gap-2 no-print">
            <a id="slipWhatsappBtn" href="#" target="_blank" class="flex-1 py-2 px-3 rounded-xl bg-emerald-600 hover:bg-emerald-700 text-white font-bold text-xs flex items-center justify-center gap-1.5 shadow-sm">
                <i data-lucide="share-2" class="w-3.5 h-3.5"></i>
                <span>واٹس ایپ</span>
            </a>

            <button type="button" onclick="printSlipContent()" class="flex-1 py-2 px-3 rounded-xl bg-blue-600 hover:bg-blue-700 text-white font-bold text-xs flex items-center justify-center gap-1.5 shadow-sm">
                <i data-lucide="printer" class="w-3.5 h-3.5"></i>
                <span>پرنٹ سلپ</span>
            </button>
        </div>

    </div>
</div>

<!-- ============================================================== -->
<!-- JAVASCRIPT: CONTROLLER FOR MODALS, AUTO SLAB & LIVE TABLE -->
<!-- ============================================================== -->
<script>
// State
let currentDirection = 'BUY';

function openBuySellModal(dir = 'BUY') {
    setDirection(dir);
    document.getElementById('buySellModal').classList.remove('hidden');
    document.getElementById('buySellModal').classList.add('flex');
    setTimeout(() => {
        const input = document.getElementById('inputAmount');
        if (input) input.focus();
        lucide.createIcons();
    }, 100);
}

function closeBuySellModal() {
    document.getElementById('buySellModal').classList.add('hidden');
    document.getElementById('buySellModal').classList.remove('flex');
}

function openExpenseModal() {
    document.getElementById('expenseModal').classList.remove('hidden');
    document.getElementById('expenseModal').classList.add('flex');
    setTimeout(() => lucide.createIcons(), 100);
}

function closeExpenseModal() {
    document.getElementById('expenseModal').classList.add('hidden');
    document.getElementById('expenseModal').classList.remove('flex');
}

function openOpeningModal() {
    document.getElementById('openingModal').classList.remove('hidden');
    document.getElementById('openingModal').classList.add('flex');
    setTimeout(() => lucide.createIcons(), 100);
}

function closeOpeningModal() {
    document.getElementById('openingModal').classList.add('hidden');
    document.getElementById('openingModal').classList.remove('flex');
}

function closeReceiptModal() {
    document.getElementById('thermalReceiptModal').classList.add('hidden');
    document.getElementById('thermalReceiptModal').classList.remove('flex');
}

function setDirection(dir) {
    currentDirection = dir;
    document.getElementById('formDirection').value = dir;

    const btnBuy = document.getElementById('btnDirBuy');
    const btnSell = document.getElementById('btnDirSell');
    const headerIcon = document.getElementById('modalHeaderIcon');
    const summaryLabel = document.getElementById('summaryActionLabel');

    if (dir === 'BUY') {
        btnBuy.className = 'p-3.5 rounded-2xl border-2 transition-all flex flex-col items-center text-center cursor-pointer bg-emerald-950/40 border-emerald-500 text-emerald-300 shadow-md shadow-emerald-500/10';
        btnSell.className = 'p-3.5 rounded-2xl border-2 transition-all flex flex-col items-center text-center cursor-pointer bg-slate-800/60 border-slate-700 text-slate-400 hover:text-white';
        headerIcon.className = 'w-10 h-10 rounded-2xl bg-emerald-600 text-white flex items-center justify-center font-bold shadow-md';
        summaryLabel.innerText = 'گاہک کو نقد ادائیگی (Cash to Give):';
    } else {
        btnSell.className = 'p-3.5 rounded-2xl border-2 transition-all flex flex-col items-center text-center cursor-pointer bg-amber-950/40 border-amber-500 text-amber-300 shadow-md shadow-amber-500/10';
        btnBuy.className = 'p-3.5 rounded-2xl border-2 transition-all flex flex-col items-center text-center cursor-pointer bg-slate-800/60 border-slate-700 text-slate-400 hover:text-white';
        headerIcon.className = 'w-10 h-10 rounded-2xl bg-amber-600 text-white flex items-center justify-center font-bold shadow-md';
        summaryLabel.innerText = 'گاہک سے وصول شدہ نقد رقم (Cash to Receive):';
    }
    calculateNet();
    lucide.createIcons();
}

function setQuickAmount(amt) {
    document.getElementById('inputAmount').value = amt;
    applyAutoFeeSlab();
    calculateNet();
}

function setQuickFee(fee) {
    document.getElementById('inputFee').value = fee;
    calculateNet();
}

// Pakistani Standard Auto Fee Tier Calculator
function applyAutoFeeSlab() {
    const amt = parseFloat(document.getElementById('inputAmount').value) || 0;
    let fee = 0;
    if (amt <= 0) fee = 0;
    else if (amt <= 1000) fee = 20;
    else if (amt <= 2500) fee = 30;
    else if (amt <= 4000) fee = 40;
    else if (amt <= 6000) fee = 60;
    else if (amt <= 8000) fee = 80;
    else if (amt <= 10000) fee = 100;
    else if (amt <= 15000) fee = 150;
    else if (amt <= 20000) fee = 200;
    else if (amt <= 25000) fee = 250;
    else if (amt <= 30000) fee = 300;
    else if (amt <= 40000) fee = 350;
    else if (amt <= 50000) fee = 400;
    else fee = Math.round(amt * 0.008);

    document.getElementById('inputFee').value = fee;
    calculateNet();
}

function calculateNet() {
    const amt = parseFloat(document.getElementById('inputAmount').value) || 0;
    const fee = parseFloat(document.getElementById('inputFee').value) || 0;

    let netCash = 0;
    if (currentDirection === 'BUY') {
        netCash = Math.max(0, amt - fee);
    } else {
        netCash = amt + fee;
    }

    document.getElementById('displayAmount').innerText = 'Rs. ' + amt.toLocaleString();
    document.getElementById('summaryCashVal').innerText = 'Rs. ' + netCash.toLocaleString();
    document.getElementById('summaryFeeVal').innerText = '+Rs. ' + fee.toLocaleString();
}

// Preview Receipt Slip Modal
function previewSlip(trx) {
    document.getElementById('slipType').innerText = trx.direction === 'BUY' ? 'BUY (کیش آؤٹ)' : 'SELL (کیش ان)';
    document.getElementById('slipChannel').innerText = trx.channel || 'EASYPAISA';
    document.getElementById('slipCustomer').innerText = trx.customer_name || 'Walk-in';
    document.getElementById('slipCustomerPhone').innerText = trx.customer_phone || '-';
    document.getElementById('slipTrxId').innerText = trx.trx_id || '-';
    document.getElementById('slipDateTime').innerText = (trx.date || '') + ' ' + (trx.time || '');
    document.getElementById('slipTransferAmount').innerText = 'Rs. ' + (trx.amount || 0).toLocaleString();
    document.getElementById('slipFee').innerText = 'Rs. ' + (trx.fee || 0).toLocaleString();
    document.getElementById('slipNetLabel').innerText = trx.direction === 'BUY' ? 'نقد ادائیگی:' : 'نقد وصولی:';
    document.getElementById('slipCashAmount').innerText = 'Rs. ' + (trx.cash_amount || 0).toLocaleString();

    // WhatsApp Message
    const msg = `*${document.getElementById('slipShopName').innerText}*\nٹرانزیکشن رسید:\nنوعیت: ${trx.direction === 'BUY' ? 'BUY (کیش آؤٹ)' : 'SELL (کیش ان)'}\nگاہک: ${trx.customer_name}\nرقم: Rs. ${(trx.amount || 0).toLocaleString()}\nفیس: Rs. ${(trx.fee || 0).toLocaleString()}\nکیش رقم: Rs. ${(trx.cash_amount || 0).toLocaleString()}\nTRX ID: ${trx.trx_id || '-'}\nتاریخ: ${trx.date} ${trx.time}`;
    const phone = (trx.customer_phone || '').replace(/[^0-9]/g, '');
    const waUrl = phone.length >= 10 ? `https://wa.me/92${phone.startsWith('0') ? phone.substring(1) : phone}?text=${encodeURIComponent(msg)}` : `https://wa.me/?text=${encodeURIComponent(msg)}`;
    document.getElementById('slipWhatsappBtn').href = waUrl;

    document.getElementById('thermalReceiptModal').classList.remove('hidden');
    document.getElementById('thermalReceiptModal').classList.add('flex');
    setTimeout(() => lucide.createIcons(), 100);
}

function printSlipContent() {
    window.print();
}

// Real-time Table Search and Filter
function filterLiveTable() {
    const query = document.getElementById('tableSearchInput').value.toLowerCase().trim();
    const rows = document.querySelectorAll('.entry-row');
    let visibleCount = 0;

    rows.forEach(row => {
        const text = row.innerText.toLowerCase();
        const matches = text.includes(query);
        row.style.display = matches ? '' : 'none';
        if (matches) visibleCount++;
    });

    const badge = document.getElementById('liveEntriesCountBadge');
    if (badge) badge.innerText = visibleCount + ' ریکارڈز';
}

// Initialize Chart & Auto Open Receipt if Saved with Print
document.addEventListener('DOMContentLoaded', () => {
    // 1. Initialize Chart.js
    const ctx = document.getElementById('salesTrendsChart');
    if (ctx) {
        const labels = <?= json_encode($chartLabels) ?>;
        const salesData = <?= json_encode($chartSales) ?>;
        const profitData = <?= json_encode($chartProfits) ?>;

        new Chart(ctx, {
            type: 'line',
            data: {
                labels: labels,
                datasets: [
                    {
                        label: 'کل فروخت (Sales)',
                        data: salesData,
                        borderColor: '#3b82f6',
                        backgroundColor: 'rgba(59, 130, 246, 0.15)',
                        fill: true,
                        tension: 0.35,
                        borderWidth: 2.5,
                        pointBackgroundColor: '#3b82f6',
                        pointRadius: 4
                    },
                    {
                        label: 'خالص منافع (Profit)',
                        data: profitData,
                        borderColor: '#10b981',
                        backgroundColor: 'rgba(16, 185, 129, 0.15)',
                        fill: true,
                        tension: 0.35,
                        borderWidth: 2.5,
                        pointBackgroundColor: '#10b981',
                        pointRadius: 4
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { display: false },
                    tooltip: {
                        rtl: true,
                        backgroundColor: '#0f172a',
                        titleColor: '#ffffff',
                        bodyColor: '#cbd5e1',
                        borderColor: '#1e293b',
                        borderWidth: 1,
                        padding: 10,
                        callbacks: {
                            label: function(context) {
                                return context.dataset.label + ': Rs. ' + Number(context.raw).toLocaleString();
                            }
                        }
                    }
                },
                scales: {
                    x: {
                        grid: { color: '#1e293b', drawBorder: false },
                        ticks: { color: '#94a3b8', font: { size: 11 } }
                    },
                    y: {
                        grid: { color: '#1e293b', drawBorder: false },
                        ticks: {
                            color: '#94a3b8',
                            font: { size: 10 },
                            callback: function(val) {
                                return 'Rs ' + (val >= 1000 ? (val / 1000) + 'k' : val);
                            }
                        }
                    }
                }
            }
        });
    }

    // 2. Auto-Trigger Thermal Receipt if just saved
    <?php if ($receiptTrx): ?>
        previewSlip(<?= json_encode($receiptTrx) ?>);
    <?php endif; ?>
});
</script>

<?php require_once __DIR__ . '/../backend/footer.php'; ?>
