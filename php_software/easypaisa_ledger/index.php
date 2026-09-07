<?php
$pageTitle = 'ایزی پیسہ و کیش رجسٹر (EasyPaisa & Cash Ledger)';
$activeMenu = 'easypaisa';
require_once __DIR__ . '/../backend/header.php';

$successMsg = '';
$errorMsg = '';
$today = date('Y-m-d');
$nowTime = date('H:i:s');

// -------------------------------------------------------------
// 1. POST Action: Save Daily Opening Balance
// -------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'save_daily_opening') {
    $openingDate = trim($_POST['opening_date'] ?? $today);
    $openingCash = floatval($_POST['opening_cash'] ?? 0);
    $openingEp = floatval($_POST['opening_easypaisa'] ?? 0);
    $openingNotes = trim($_POST['opening_notes'] ?? '');

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

// -------------------------------------------------------------
// 2. POST Action: New Transaction
// -------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'new_transaction') {
    $type = trim($_POST['type'] ?? 'SELL_CASH');
    $channel = trim($_POST['payment_method'] ?? 'EASYPAISA');
    $customerName = trim($_POST['customer_name'] ?? 'کسٹمر');
    if (empty($customerName)) $customerName = 'کسٹمر';
    $customerPhone = trim($_POST['customer_phone'] ?? '');
    $cnic = trim($_POST['cnic'] ?? '');
    $epAmount = floatval($_POST['easy_paisa_amount'] ?? 0);
    $cashAmount = floatval($_POST['cash_amount'] ?? 0);
    $feeProfit = floatval($_POST['fee_profit'] ?? 0);
    $expenseAmount = floatval($_POST['expense_amount'] ?? 0);
    $trxCode = trim($_POST['trx_id'] ?? '');
    $notes = trim($_POST['notes'] ?? '');
    $trxDate = trim($_POST['date'] ?? $today);
    $trxTime = trim($_POST['time'] ?? date('h:i A'));

    // Handle special types
    if ($type === 'EXPENSE') {
        $epAmount = 0;
        $feeProfit = 0;
        if ($expenseAmount <= 0) $expenseAmount = $cashAmount;
    }

    try {
        $id = 'trx-' . uniqid();
        $createdAt = time();

        // Support both SQLite and MySQL table schema
        $stmt = $pdo->prepare("INSERT INTO transactions (
            id, date, time, type, customer_name, customer_phone,
            trx_id, easy_paisa_amount, cash_amount, fee_profit, expense_amount, payment_method, note, created_at
        ) VALUES (
            :id, :date, :time, :type, :cname, :cphone,
            :trx_id, :ep_amt, :cash_amt, :profit, :exp_amt, :pmethod, :notes, :created_at
        )");
        $stmt->execute([
            ':id' => $id,
            ':date' => $trxDate,
            ':time' => $trxTime,
            ':type' => $type,
            ':cname' => $customerName,
            ':cphone' => $customerPhone,
            ':trx_id' => $trxCode,
            ':ep_amt' => $epAmount,
            ':cash_amt' => $cashAmount,
            ':profit' => $feeProfit,
            ':exp_amt' => $expenseAmount,
            ':pmethod' => $channel,
            ':notes' => $notes,
            ':created_at' => $createdAt
        ]);
        $successMsg = 'ایزی پیسہ / کیش ٹرانزیکشن باکامیابی درج ہو گئی!';
    } catch (Exception $e) {
        $errorMsg = 'ٹرانزیکشن اندراج میں خرابی: ' . $e->getMessage();
    }
}

// -------------------------------------------------------------
// 3. POST Action: Edit Transaction
// -------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'edit_transaction') {
    $editId = trim($_POST['edit_id'] ?? '');
    $type = trim($_POST['type'] ?? 'SELL_CASH');
    $channel = trim($_POST['payment_method'] ?? 'EASYPAISA');
    $customerName = trim($_POST['customer_name'] ?? 'کسٹمر');
    $customerPhone = trim($_POST['customer_phone'] ?? '');
    $epAmount = floatval($_POST['easy_paisa_amount'] ?? 0);
    $cashAmount = floatval($_POST['cash_amount'] ?? 0);
    $feeProfit = floatval($_POST['fee_profit'] ?? 0);
    $expenseAmount = floatval($_POST['expense_amount'] ?? 0);
    $trxCode = trim($_POST['trx_id'] ?? '');
    $notes = trim($_POST['notes'] ?? '');
    $trxDate = trim($_POST['date'] ?? $today);
    $trxTime = trim($_POST['time'] ?? date('h:i A'));

    if ($type === 'EXPENSE') {
        $epAmount = 0;
        $feeProfit = 0;
        if ($expenseAmount <= 0) $expenseAmount = $cashAmount;
    }

    try {
        $stmt = $pdo->prepare("UPDATE transactions SET 
            type = :type,
            payment_method = :pmethod,
            customer_name = :cname,
            customer_phone = :cphone,
            easy_paisa_amount = :ep_amt,
            cash_amount = :cash_amt,
            fee_profit = :profit,
            expense_amount = :exp_amt,
            trx_id = :trx_id,
            note = :notes,
            date = :date,
            time = :time
            WHERE id = :id");
        $stmt->execute([
            ':type' => $type,
            ':pmethod' => $channel,
            ':cname' => $customerName,
            ':cphone' => $customerPhone,
            ':ep_amt' => $epAmount,
            ':cash_amt' => $cashAmount,
            ':profit' => $feeProfit,
            ':exp_amt' => $expenseAmount,
            ':trx_id' => $trxCode,
            ':notes' => $notes,
            ':date' => $trxDate,
            ':time' => $trxTime,
            ':id' => $editId
        ]);
        $successMsg = 'ٹرانزیکشن کامیابی سے اپ ڈیٹ ہو گئی!';
    } catch (Exception $e) {
        $errorMsg = 'اپ ڈیٹ میں خرابی: ' . $e->getMessage();
    }
}

// -------------------------------------------------------------
// 4. POST Action: Delete Transaction
// -------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete_transaction') {
    $delId = trim($_POST['del_id'] ?? '');
    try {
        $stmt = $pdo->prepare("DELETE FROM transactions WHERE id = :id");
        $stmt->execute([':id' => $delId]);
        $successMsg = 'ٹرانزیکشن کامیابی سے ڈیلیٹ کر دی گئی!';
    } catch (Exception $e) {
        $errorMsg = 'ڈیلیٹ میں خرابی: ' . $e->getMessage();
    }
}

// -------------------------------------------------------------
// 5. Fetch Daily Opening Balance
// -------------------------------------------------------------
$todayOpening = ['opening_cash' => 0, 'opening_easypaisa' => 0, 'notes' => ''];
try {
    $stmt = $pdo->prepare("SELECT * FROM daily_balances WHERE date = :date LIMIT 1");
    $stmt->execute([':date' => $today]);
    $row = $stmt->fetch();
    if ($row) {
        $todayOpening['opening_cash'] = floatval($row['opening_cash'] ?? 0);
        $todayOpening['opening_easypaisa'] = floatval($row['opening_easypaisa'] ?? 0);
        $todayOpening['notes'] = $row['notes'] ?? '';
    }
} catch (Exception $e) {}

// -------------------------------------------------------------
// 6. Filter & Search Query
// -------------------------------------------------------------
$filterPreset = $_GET['preset'] ?? 'today';
$startDate = $_GET['start_date'] ?? $today;
$endDate = $_GET['end_date'] ?? $today;
$filterType = $_GET['type'] ?? 'ALL';
$filterChannel = $_GET['channel'] ?? 'ALL';
$searchTerm = trim($_GET['search'] ?? '');

if ($filterPreset === 'today') {
    $startDate = $today;
    $endDate = $today;
} elseif ($filterPreset === 'yesterday') {
    $startDate = date('Y-m-d', strtotime('-1 day'));
    $endDate = date('Y-m-d', strtotime('-1 day'));
} elseif ($filterPreset === '7days') {
    $startDate = date('Y-m-d', strtotime('-6 days'));
    $endDate = $today;
} elseif ($filterPreset === 'month') {
    $startDate = date('Y-m-01');
    $endDate = $today;
} elseif ($filterPreset === 'all') {
    $startDate = '2020-01-01';
    $endDate = '2099-12-31';
}

$whereClauses = ["date >= :sdate AND date <= :edate"];
$params = [
    ':sdate' => $startDate,
    ':edate' => $endDate
];

if ($filterType !== 'ALL') {
    if ($filterType === 'CASH_OUT') {
        $whereClauses[] = "type IN ('SELL_CASH', 'BUY_EASYPAISA')";
    } elseif ($filterType === 'CASH_IN') {
        $whereClauses[] = "type IN ('BUY_CASH', 'SELL_EASYPAISA')";
    } elseif ($filterType === 'EXPENSE') {
        $whereClauses[] = "type IN ('EXPENSE', 'DISCREPANCY_LOSS')";
    } else {
        $whereClauses[] = "type = :ftype";
        $params[':ftype'] = $filterType;
    }
}

if ($filterChannel !== 'ALL') {
    if ($filterChannel === 'BANKS') {
        $whereClauses[] = "payment_method LIKE '%BANK%'";
    } elseif ($filterChannel === 'WALLETS') {
        $whereClauses[] = "payment_method IN ('EASYPAISA', 'JAZZCASH', 'SADAPAY', 'NAYAPAY')";
    } else {
        $whereClauses[] = "payment_method = :pchannel";
        $params[':pchannel'] = $filterChannel;
    }
}

if (!empty($searchTerm)) {
    $whereClauses[] = "(customer_name LIKE :sterm OR customer_phone LIKE :sterm OR trx_id LIKE :sterm OR note LIKE :sterm)";
    $params[':sterm'] = '%' . $searchTerm . '%';
}

$whereSql = implode(' AND ', $whereClauses);
$trxQuery = "SELECT * FROM transactions WHERE {$whereSql} ORDER BY date DESC, time DESC, created_at DESC";
$trxStmt = $pdo->prepare($trxQuery);
$trxStmt->execute($params);
$transactions = $trxStmt->fetchAll();

// -------------------------------------------------------------
// 7. Calculate Filtered Stats & Balances
// -------------------------------------------------------------
$filteredStats = [
    'total_cash_out_ep' => 0, // Customer withdrew cash, shop got digital
    'total_cash_in_ep'  => 0, // Customer sent money, shop sent digital
    'total_cash_given'  => 0, // Shop physical cash given out
    'total_cash_taken'  => 0, // Shop physical cash received in
    'total_fees_profit' => 0,
    'total_expenses'    => 0,
    'trx_count'         => count($transactions)
];

foreach ($transactions as $t) {
    $epAmt = floatval($t['easy_paisa_amount'] ?? 0);
    $cashAmt = floatval($t['cash_amount'] ?? 0);
    $fee = floatval($t['fee_profit'] ?? 0);
    $exp = floatval($t['expense_amount'] ?? 0);
    $type = $t['type'];

    if ($type === 'SELL_CASH' || $type === 'BUY_EASYPAISA') {
        // Cash Out / Withdrawal: EP comes IN to wallet, Physical cash goes OUT
        $filteredStats['total_cash_out_ep'] += $epAmt;
        $filteredStats['total_cash_given'] += $cashAmt;
        $filteredStats['total_fees_profit'] += $fee;
    } elseif ($type === 'BUY_CASH' || $type === 'SELL_EASYPAISA' || $type === 'UTILITY_BILL' || $type === 'EASYLOAD') {
        // Cash In / Send Money: EP goes OUT of wallet, Physical cash comes IN
        $filteredStats['total_cash_in_ep'] += $epAmt;
        $filteredStats['total_cash_taken'] += $cashAmt;
        $filteredStats['total_fees_profit'] += $fee;
    } elseif ($type === 'EXPENSE' || $type === 'DISCREPANCY_LOSS') {
        $filteredStats['total_expenses'] += ($exp > 0 ? $exp : $cashAmt);
    }
}

// -------------------------------------------------------------
// 8. Calculate Overall Live Balances (Today's Drawer & Wallet)
// -------------------------------------------------------------
$allTodayStmt = $pdo->prepare("SELECT * FROM transactions WHERE date = :today");
$allTodayStmt->execute([':today' => $today]);
$todayAllTrx = $allTodayStmt->fetchAll();

$todayInEP = 0;   // Inflows to EP wallet
$todayOutEP = 0;  // Outflows from EP wallet
$todayCashIn = 0; // Cash coming into drawer
$todayCashOut = 0;// Cash given out of drawer
$todayExp = 0;
$todayProfit = 0;

foreach ($todayAllTrx as $t) {
    $epAmt = floatval($t['easy_paisa_amount'] ?? 0);
    $cashAmt = floatval($t['cash_amount'] ?? 0);
    $fee = floatval($t['fee_profit'] ?? 0);
    $exp = floatval($t['expense_amount'] ?? 0);
    $type = $t['type'];

    if ($type === 'SELL_CASH' || $type === 'BUY_EASYPAISA') {
        $todayInEP += $epAmt;
        $todayCashOut += $cashAmt;
        $todayProfit += $fee;
    } elseif ($type === 'BUY_CASH' || $type === 'SELL_EASYPAISA' || $type === 'UTILITY_BILL' || $type === 'EASYLOAD') {
        $todayOutEP += $epAmt;
        $todayCashIn += $cashAmt;
        $todayProfit += $fee;
    } elseif ($type === 'EXPENSE' || $type === 'DISCREPANCY_LOSS') {
        $todayExp += ($exp > 0 ? $exp : $cashAmt);
    }
}

$liveWalletBalance = $todayOpening['opening_easypaisa'] + $todayInEP - $todayOutEP;
$liveCashDrawer = $todayOpening['opening_cash'] + $todayCashIn - $todayCashOut - $todayExp;
$netProfitToday = $todayProfit - $todayExp;

// -------------------------------------------------------------
// 9. Fetch 7-Day Chart Data
// -------------------------------------------------------------
$chartLabels = [];
$chartVolumes = [];
$chartProfits = [];
for ($i = 6; $i >= 0; $i--) {
    $d = date('Y-m-d', strtotime("-{$i} days"));
    $dLabel = date('d M (D)', strtotime($d));
    $chartLabels[] = $dLabel;

    $cStmt = $pdo->prepare("SELECT COALESCE(SUM(easy_paisa_amount), 0) as vol, COALESCE(SUM(fee_profit), 0) as prof FROM transactions WHERE date = :d");
    $cStmt->execute([':d' => $d]);
    $cData = $cStmt->fetch();
    $chartVolumes[] = floatval($cData['vol'] ?? 0);
    $chartProfits[] = floatval($cData['prof'] ?? 0);
}
?>

<!-- Alerts -->
<?php if(!empty($successMsg)): ?>
    <div class="mb-4 p-4 rounded-2xl bg-emerald-500/10 border border-emerald-500/30 text-emerald-300 flex items-center gap-3 shadow-lg animate-fade-in no-print">
        <div class="w-8 h-8 rounded-xl bg-emerald-500/20 flex items-center justify-center text-emerald-400 shrink-0">
            <i data-lucide="check-circle-2" class="w-5 h-5"></i>
        </div>
        <span class="font-bold text-xs sm:text-sm"><?= htmlspecialchars($successMsg) ?></span>
    </div>
<?php endif; ?>

<?php if(!empty($errorMsg)): ?>
    <div class="mb-4 p-4 rounded-2xl bg-rose-500/10 border border-rose-500/30 text-rose-300 flex items-center gap-3 shadow-lg animate-fade-in no-print">
        <div class="w-8 h-8 rounded-xl bg-rose-500/20 flex items-center justify-center text-rose-400 shrink-0">
            <i data-lucide="alert-triangle" class="w-5 h-5"></i>
        </div>
        <span class="font-bold text-xs sm:text-sm"><?= htmlspecialchars($errorMsg) ?></span>
    </div>
<?php endif; ?>

<!-- Top Action Header -->
<div class="bg-slate-900 border border-slate-800 rounded-3xl p-5 mb-6 shadow-xl no-print">
    <div class="flex flex-col lg:flex-row lg:items-center justify-between gap-4">
        <!-- Title & Subtitle -->
        <div class="flex items-center gap-3.5">
            <div class="w-12 h-12 rounded-2xl bg-gradient-to-tr from-teal-600 to-emerald-500 text-white flex items-center justify-center shadow-lg shadow-teal-500/20 shrink-0">
                <i data-lucide="banknote" class="w-6 h-6"></i>
            </div>
            <div>
                <div class="flex items-center gap-2">
                    <h1 class="text-xl sm:text-2xl font-black text-white">ایزی پیسہ و کیش رجسٹر (EasyPaisa Ledger)</h1>
                    <span class="px-2.5 py-0.5 rounded-full text-[11px] font-bold bg-teal-500/10 text-teal-400 border border-teal-500/20">
                        لائیو روزنامچہ
                    </span>
                </div>
                <p class="text-xs text-slate-400 mt-1">
                    ایزی پیسہ، جاز کیش، ساداپے، آن لائن بینک ٹرانسفر، یوٹیلیٹی بلز اور دکان کے نقد کیش کا خودکار حساب کتاب
                </p>
            </div>
        </div>

        <!-- Quick Top Action Buttons -->
        <div class="flex flex-wrap items-center gap-2.5">
            <button onclick="openOpeningBalanceModal()" class="px-3.5 py-2.5 rounded-xl bg-slate-800 hover:bg-slate-700 text-slate-200 border border-slate-700 text-xs font-bold flex items-center gap-2 transition-all cursor-pointer shadow-sm">
                <i data-lucide="sun" class="w-4 h-4 text-amber-400"></i>
                <span>صبح کا اوپننگ بیلنس</span>
            </button>

            <button onclick="exportTableToCSV()" class="px-3.5 py-2.5 rounded-xl bg-slate-800 hover:bg-slate-700 text-slate-200 border border-slate-700 text-xs font-bold flex items-center gap-2 transition-all cursor-pointer shadow-sm">
                <i data-lucide="file-spreadsheet" class="w-4 h-4 text-emerald-400"></i>
                <span>ایکسل ڈاؤنلوڈ (CSV)</span>
            </button>

            <button onclick="window.print()" class="px-3.5 py-2.5 rounded-xl bg-slate-800 hover:bg-slate-700 text-slate-200 border border-slate-700 text-xs font-bold flex items-center gap-2 transition-all cursor-pointer shadow-sm">
                <i data-lucide="printer" class="w-4 h-4 text-sky-400"></i>
                <span>پرنٹ اسٹیٹمنٹ</span>
            </button>

            <button onclick="openNewTrxModal()" class="px-4 py-2.5 rounded-xl bg-gradient-to-r from-emerald-600 to-teal-600 hover:from-emerald-500 hover:to-teal-500 text-white font-black text-xs sm:text-sm flex items-center gap-2 shadow-lg shadow-emerald-600/30 transition-all cursor-pointer active:scale-95">
                <i data-lucide="plus-circle" class="w-4 h-4"></i>
                <span>+ نئی انٹری (New Entry)</span>
            </button>
        </div>
    </div>

    <!-- Morning Opening Reconciliation Banner -->
    <div class="mt-4 pt-4 border-t border-slate-800/80 flex flex-wrap items-center justify-between gap-3 text-xs">
        <div class="flex items-center gap-4 text-slate-400">
            <span class="flex items-center gap-1.5 font-medium">
                <i data-lucide="clock" class="w-4 h-4 text-slate-500"></i>
                آج کی تاریخ: <strong class="text-white font-mono"><?= $today ?></strong>
            </span>
            <span class="hidden sm:inline text-slate-700">|</span>
            <span class="flex items-center gap-1.5">
                <i data-lucide="circle-dot" class="w-3.5 h-3.5 text-teal-400"></i>
                صبح کا اوپننگ ایزی پیسہ: <strong class="text-teal-300 font-mono">Rs. <?= number_format($todayOpening['opening_easypaisa']) ?></strong>
            </span>
            <span class="hidden sm:inline text-slate-700">|</span>
            <span class="flex items-center gap-1.5">
                <i data-lucide="circle-dot" class="w-3.5 h-3.5 text-amber-400"></i>
                صبح کا کیش دراز: <strong class="text-amber-300 font-mono">Rs. <?= number_format($todayOpening['opening_cash']) ?></strong>
            </span>
        </div>
        <button onclick="openOpeningBalanceModal()" class="text-emerald-400 hover:text-emerald-300 text-xs font-bold underline cursor-pointer">
            اوپننگ بیلنس تبدیل کریں
        </button>
    </div>
</div>

<!-- 4 Key Balance Cards (Hero Dashboard) -->
<div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 mb-6 no-print">
    <!-- Card 1: EasyPaisa / Wallets Live Balance -->
    <div class="bg-gradient-to-br from-teal-950/70 via-slate-900 to-slate-900 border border-teal-500/30 p-5 rounded-3xl shadow-xl relative overflow-hidden">
        <div class="absolute -left-4 -bottom-4 w-24 h-24 bg-teal-500/5 rounded-full blur-2xl"></div>
        <div class="flex items-center justify-between mb-2">
            <span class="text-xs text-teal-300 font-bold flex items-center gap-1.5">
                <i data-lucide="smartphone" class="w-4 h-4"></i>
                ایزی پیسہ و والٹس بیلنس
            </span>
            <span class="text-[10px] bg-teal-500/20 text-teal-300 font-mono px-2 py-0.5 rounded-full font-bold">لائیو والٹ</span>
        </div>
        <h3 class="text-2xl sm:text-3xl font-black text-white font-mono mt-1">
            Rs. <?= number_format($liveWalletBalance) ?>
        </h3>
        <div class="mt-3 pt-2.5 border-t border-teal-500/20 text-[11px] text-slate-400 flex items-center justify-between">
            <span>موصول: <strong class="text-emerald-400 font-mono">+<?= number_format($todayInEP) ?></strong></span>
            <span>بھیجا: <strong class="text-rose-400 font-mono">-<?= number_format($todayOutEP) ?></strong></span>
        </div>
    </div>

    <!-- Card 2: Cash-in-Drawer Live Balance -->
    <div class="bg-gradient-to-br from-amber-950/70 via-slate-900 to-slate-900 border border-amber-500/30 p-5 rounded-3xl shadow-xl relative overflow-hidden">
        <div class="absolute -left-4 -bottom-4 w-24 h-24 bg-amber-500/5 rounded-full blur-2xl"></div>
        <div class="flex items-center justify-between mb-2">
            <span class="text-xs text-amber-300 font-bold flex items-center gap-1.5">
                <i data-lucide="archive" class="w-4 h-4"></i>
                دکان کیش دراز (Cash-in-Drawer)
            </span>
            <span class="text-[10px] bg-amber-500/20 text-amber-300 font-mono px-2 py-0.5 rounded-full font-bold">فزیکل نقد</span>
        </div>
        <h3 class="text-2xl sm:text-3xl font-black text-amber-400 font-mono mt-1">
            Rs. <?= number_format($liveCashDrawer) ?>
        </h3>
        <div class="mt-3 pt-2.5 border-t border-amber-500/20 text-[11px] text-slate-400 flex items-center justify-between">
            <span>کیش ان: <strong class="text-emerald-400 font-mono">+<?= number_format($todayCashIn) ?></strong></span>
            <span>کیش آؤٹ: <strong class="text-rose-400 font-mono">-<?= number_format($todayCashOut) ?></strong></span>
        </div>
    </div>

    <!-- Card 3: Today's Total Commission Profit -->
    <div class="bg-gradient-to-br from-emerald-950/70 via-slate-900 to-slate-900 border border-emerald-500/30 p-5 rounded-3xl shadow-xl relative overflow-hidden">
        <div class="absolute -left-4 -bottom-4 w-24 h-24 bg-emerald-500/5 rounded-full blur-2xl"></div>
        <div class="flex items-center justify-between mb-2">
            <span class="text-xs text-emerald-300 font-bold flex items-center gap-1.5">
                <i data-lucide="trending-up" class="w-4 h-4"></i>
                آج کا کل کمیشن / منافع
            </span>
            <span class="text-[10px] bg-emerald-500/20 text-emerald-300 font-mono px-2 py-0.5 rounded-full font-bold">آج کی آمدنی</span>
        </div>
        <h3 class="text-2xl sm:text-3xl font-black text-emerald-400 font-mono mt-1">
            Rs. <?= number_format($todayProfit) ?>
        </h3>
        <div class="mt-3 pt-2.5 border-t border-emerald-500/20 text-[11px] text-slate-400 flex items-center justify-between">
            <span>کل انٹریز: <strong class="text-white font-mono"><?= count($todayAllTrx) ?></strong></span>
            <span>دکان خرچ: <strong class="text-rose-400 font-mono">Rs. <?= number_format($todayExp) ?></strong></span>
        </div>
    </div>

    <!-- Card 4: Net Profit (Commission minus Expenses) -->
    <div class="bg-gradient-to-br from-indigo-950/70 via-slate-900 to-slate-900 border border-indigo-500/30 p-5 rounded-3xl shadow-xl relative overflow-hidden">
        <div class="absolute -left-4 -bottom-4 w-24 h-24 bg-indigo-500/5 rounded-full blur-2xl"></div>
        <div class="flex items-center justify-between mb-2">
            <span class="text-xs text-indigo-300 font-bold flex items-center gap-1.5">
                <i data-lucide="coins" class="w-4 h-4"></i>
                آج کا خالص منافع (Net Profit)
            </span>
            <span class="text-[10px] bg-indigo-500/20 text-indigo-300 font-mono px-2 py-0.5 rounded-full font-bold">صاف بچت</span>
        </div>
        <h3 class="text-2xl sm:text-3xl font-black text-indigo-300 font-mono mt-1">
            Rs. <?= number_format($netProfitToday) ?>
        </h3>
        <div class="mt-3 pt-2.5 border-t border-indigo-500/20 text-[11px] text-slate-400 flex items-center justify-between">
            <span>کمیشن: <strong class="text-emerald-400 font-mono"><?= number_format($todayProfit) ?></strong></span>
            <span>منفی خرچ: <strong class="text-rose-400 font-mono"><?= number_format($todayExp) ?></strong></span>
        </div>
    </div>
</div>

<!-- 7-Day Performance Graph Section (Chart.js) -->
<div class="bg-slate-900 border border-slate-800 rounded-3xl p-5 mb-6 shadow-xl no-print">
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 mb-4 pb-3 border-b border-slate-800">
        <div class="flex items-center gap-2.5">
            <div class="w-9 h-9 rounded-xl bg-teal-500/10 text-teal-400 flex items-center justify-center font-bold">
                <i data-lucide="bar-chart-3" class="w-4 h-4"></i>
            </div>
            <div>
                <h3 class="text-sm sm:text-base font-bold text-white">گزشتہ 7 دنوں کا ٹرانزیکشن والیم اور کمیشن منافع گراف</h3>
                <p class="text-[11px] text-slate-400">ڈیلی ایزی پیسہ ٹرانسفر کا حجم اور حاصل شدہ منافع کی تفصیل</p>
            </div>
        </div>
        <div class="flex items-center gap-4 text-xs">
            <span class="flex items-center gap-1.5 text-slate-300">
                <span class="w-3 h-3 rounded-full bg-teal-500 inline-block"></span>
                ٹرانزیکشن والیم (Rs)
            </span>
            <span class="flex items-center gap-1.5 text-slate-300">
                <span class="w-3 h-3 rounded-full bg-emerald-400 inline-block"></span>
                کمیشن منافع (Rs)
            </span>
        </div>
    </div>
    <div class="h-56 w-full">
        <canvas id="epVolumeChart"></canvas>
    </div>
</div>

<!-- Filters Bar & Search -->
<div class="bg-slate-900 border border-slate-800 rounded-3xl p-5 mb-6 shadow-xl no-print">
    <form method="GET" class="space-y-4">
        <!-- Date Presets & Custom Dates -->
        <div class="flex flex-wrap items-center justify-between gap-3">
            <!-- Preset Pills -->
            <div class="flex flex-wrap items-center gap-1.5 text-xs font-bold">
                <a href="?preset=today" class="px-3 py-1.5 rounded-xl border transition-all <?= $filterPreset === 'today' ? 'bg-emerald-600 text-white border-emerald-500 shadow-md shadow-emerald-600/30' : 'bg-slate-800 text-slate-300 border-slate-700 hover:bg-slate-700' ?>">
                    آج (Today)
                </a>
                <a href="?preset=yesterday" class="px-3 py-1.5 rounded-xl border transition-all <?= $filterPreset === 'yesterday' ? 'bg-emerald-600 text-white border-emerald-500 shadow-md shadow-emerald-600/30' : 'bg-slate-800 text-slate-300 border-slate-700 hover:bg-slate-700' ?>">
                    گزشتہ کل (Yesterday)
                </a>
                <a href="?preset=7days" class="px-3 py-1.5 rounded-xl border transition-all <?= $filterPreset === '7days' ? 'bg-emerald-600 text-white border-emerald-500 shadow-md shadow-emerald-600/30' : 'bg-slate-800 text-slate-300 border-slate-700 hover:bg-slate-700' ?>">
                    گزشتہ 7 دن (7 Days)
                </a>
                <a href="?preset=month" class="px-3 py-1.5 rounded-xl border transition-all <?= $filterPreset === 'month' ? 'bg-emerald-600 text-white border-emerald-500 shadow-md shadow-emerald-600/30' : 'bg-slate-800 text-slate-300 border-slate-700 hover:bg-slate-700' ?>">
                    اس ماہ (This Month)
                </a>
                <a href="?preset=all" class="px-3 py-1.5 rounded-xl border transition-all <?= $filterPreset === 'all' ? 'bg-emerald-600 text-white border-emerald-500 shadow-md shadow-emerald-600/30' : 'bg-slate-800 text-slate-300 border-slate-700 hover:bg-slate-700' ?>">
                    تمام ریکارڈ (All)
                </a>
            </div>

            <!-- Custom Date Inputs -->
            <div class="flex items-center gap-2 text-xs">
                <input type="hidden" name="preset" value="custom">
                <div class="flex items-center gap-1.5 bg-slate-950 border border-slate-700 px-2.5 py-1.5 rounded-xl">
                    <span class="text-slate-400 text-[11px]">از:</span>
                    <input type="date" name="start_date" value="<?= htmlspecialchars($startDate) ?>" class="bg-transparent text-white font-mono text-xs focus:outline-none">
                </div>
                <div class="flex items-center gap-1.5 bg-slate-950 border border-slate-700 px-2.5 py-1.5 rounded-xl">
                    <span class="text-slate-400 text-[11px]">تا:</span>
                    <input type="date" name="end_date" value="<?= htmlspecialchars($endDate) ?>" class="bg-transparent text-white font-mono text-xs focus:outline-none">
                </div>
            </div>
        </div>

        <!-- Secondary Filters (Type, Channel, Search) -->
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3 pt-3 border-t border-slate-800">
            <!-- Type Filter -->
            <div>
                <label class="block text-[11px] font-bold text-slate-400 mb-1">ٹرانزیکشن کی قسم:</label>
                <select name="type" class="w-full bg-slate-950 border border-slate-700 text-white px-3 py-2 rounded-xl text-xs focus:border-emerald-500 focus:outline-none cursor-pointer font-medium">
                    <option value="ALL" <?= $filterType === 'ALL' ? 'selected' : '' ?>>تمام اقسام (All Directions)</option>
                    <option value="CASH_OUT" <?= $filterType === 'CASH_OUT' ? 'selected' : '' ?>>کیش آؤٹ / رقم نکلوائی (Withdrawal)</option>
                    <option value="CASH_IN" <?= $filterType === 'CASH_IN' ? 'selected' : '' ?>>کیش ان / رقم بھیجی (Send Money)</option>
                    <option value="UTILITY_BILL" <?= $filterType === 'UTILITY_BILL' ? 'selected' : '' ?>>یوٹیلیٹی بل ادائیگی (Bills)</option>
                    <option value="EASYLOAD" <?= $filterType === 'EASYLOAD' ? 'selected' : '' ?>>موبائل ایزی لوڈ (Easyload)</option>
                    <option value="EXPENSE" <?= $filterType === 'EXPENSE' ? 'selected' : '' ?>>دکان کا خرچہ (Expenses)</option>
                    <option value="BANK_REFILL" <?= $filterType === 'BANK_REFILL' ? 'selected' : '' ?>>والٹ ری فل (Account Refill)</option>
                </select>
            </div>

            <!-- Channel Filter -->
            <div>
                <label class="block text-[11px] font-bold text-slate-400 mb-1">اکاؤنٹ یا والٹ چینل:</label>
                <select name="channel" class="w-full bg-slate-950 border border-slate-700 text-white px-3 py-2 rounded-xl text-xs focus:border-emerald-500 focus:outline-none cursor-pointer font-medium">
                    <option value="ALL" <?= $filterChannel === 'ALL' ? 'selected' : '' ?>>تمام والٹس و اکاؤنٹس (All)</option>
                    <option value="EASYPAISA" <?= $filterChannel === 'EASYPAISA' ? 'selected' : '' ?>>🟢 ایزی پیسہ (EasyPaisa)</option>
                    <option value="JAZZCASH" <?= $filterChannel === 'JAZZCASH' ? 'selected' : '' ?>>🔴 جاز کیش (JazzCash)</option>
                    <option value="SADAPAY" <?= $filterChannel === 'SADAPAY' ? 'selected' : '' ?>>🔵 ساداپے (SadaPay)</option>
                    <option value="NAYAPAY" <?= $filterChannel === 'NAYAPAY' ? 'selected' : '' ?>>🟠 نیاپے (NayaPay)</option>
                    <option value="BANKS" <?= $filterChannel === 'BANKS' ? 'selected' : '' ?>>🏛️ آن لائن بینکس (Meezan/HBL/UBL)</option>
                    <option value="RAAST" <?= $filterChannel === 'RAAST' ? 'selected' : '' ?>>🟣 راست پیمنٹ (Raast ID)</option>
                </select>
            </div>

            <!-- Search Query -->
            <div>
                <label class="block text-[11px] font-bold text-slate-400 mb-1">گاہک نام، موبائل، یا TID سرچ کریں:</label>
                <div class="relative">
                    <i data-lucide="search" class="w-3.5 h-3.5 text-slate-500 absolute right-3 top-3"></i>
                    <input type="text" name="search" value="<?= htmlspecialchars($searchTerm) ?>" placeholder="محمد عثمان، 0300...، TID" class="w-full bg-slate-950 border border-slate-700 text-white pr-8 pl-3 py-2 rounded-xl text-xs focus:border-emerald-500 focus:outline-none font-medium">
                </div>
            </div>

            <!-- Submit Filter Button -->
            <div class="flex items-end gap-2">
                <button type="submit" class="flex-1 py-2 bg-emerald-600 hover:bg-emerald-500 text-white font-bold text-xs rounded-xl transition-all shadow-md cursor-pointer">
                    فلٹر لگائیں (Apply)
                </button>
                <a href="index.php" class="px-3 py-2 bg-slate-800 hover:bg-slate-700 text-slate-300 font-bold text-xs rounded-xl border border-slate-700 transition-all text-center">
                    ری سیٹ
                </a>
            </div>
        </div>
    </form>
</div>

<!-- Filtered Range Stat Chips -->
<div class="grid grid-cols-2 lg:grid-cols-4 gap-3 mb-6 no-print">
    <div class="bg-slate-900 border border-slate-800 p-3.5 rounded-2xl">
        <span class="text-[11px] text-slate-400 block font-medium">کل رقم بھیجی (Cash In / Outward EP)</span>
        <h4 class="text-lg font-black text-white font-mono mt-1">Rs. <?= number_format($filteredStats['total_cash_in_ep']) ?></h4>
    </div>
    <div class="bg-slate-900 border border-slate-800 p-3.5 rounded-2xl">
        <span class="text-[11px] text-slate-400 block font-medium">کل رقم نکلوائی (Cash Out / Inward EP)</span>
        <h4 class="text-lg font-black text-white font-mono mt-1">Rs. <?= number_format($filteredStats['total_cash_out_ep']) ?></h4>
    </div>
    <div class="bg-slate-900 border border-slate-800 p-3.5 rounded-2xl">
        <span class="text-[11px] text-slate-400 block font-medium">کل کمیشن / فیس منافع</span>
        <h4 class="text-lg font-black text-emerald-400 font-mono mt-1">Rs. <?= number_format($filteredStats['total_fees_profit']) ?></h4>
    </div>
    <div class="bg-slate-900 border border-slate-800 p-3.5 rounded-2xl">
        <span class="text-[11px] text-slate-400 block font-medium">فلٹر شدہ انٹریز تعداد</span>
        <h4 class="text-lg font-black text-teal-400 font-mono mt-1"><?= number_format($filteredStats['trx_count']) ?> ٹرانزیکشنز</h4>
    </div>
</div>

<!-- Main Ledger Transactions Table -->
<div class="bg-slate-900 border border-slate-800 rounded-3xl overflow-hidden shadow-2xl">
    <div class="p-4 sm:p-5 border-b border-slate-800 flex flex-col sm:flex-row sm:items-center justify-between gap-3">
        <div class="flex items-center gap-3">
            <div class="w-8 h-8 rounded-xl bg-teal-500/10 text-teal-400 flex items-center justify-center font-bold">
                <i data-lucide="list-ordered" class="w-4 h-4"></i>
            </div>
            <div>
                <h3 class="text-base font-bold text-white">تفصیلی ٹرانزیکشن رجسٹر (Transactions Ledger)</h3>
                <span class="text-xs text-slate-400">تمام کیش ان، کیش آؤٹ، بلز اور لوڈ کا مکمل کھاتہ</span>
            </div>
        </div>
        <div class="text-xs text-slate-400">
            کل ریکارڈ: <strong class="text-white font-mono"><?= count($transactions) ?></strong>
        </div>
    </div>

    <div class="overflow-x-auto">
        <table id="ledgerTable" class="w-full text-right text-xs">
            <thead class="bg-slate-950 text-slate-400 uppercase text-[11px] font-bold border-b border-slate-800">
                <tr>
                    <th class="py-3.5 px-4">تاریخ و وقت</th>
                    <th class="py-3.5 px-4">قسم (Type)</th>
                    <th class="py-3.5 px-4">والٹ / چینل</th>
                    <th class="py-3.5 px-4">کسٹمر نام و موبائل</th>
                    <th class="py-3.5 px-4">حوالہ / Trx ID</th>
                    <th class="py-3.5 px-4 text-left">والٹ رقم (Transfer)</th>
                    <th class="py-3.5 px-4 text-left">دکان نقد کیش</th>
                    <th class="py-3.5 px-4 text-left">کمیشن / خرچ</th>
                    <th class="py-3.5 px-4 text-center no-print">کارروائی (Action)</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-800/70 font-medium">
                <?php if(empty($transactions)): ?>
                    <tr>
                        <td colspan="9" class="py-12 text-center text-slate-500">
                            <i data-lucide="inbox" class="w-10 h-10 mx-auto mb-2 text-slate-600"></i>
                            <p class="text-sm font-bold text-slate-400">کوئی ٹرانزیکشن ریکارڈ نہیں ملا</p>
                            <p class="text-xs text-slate-600 mt-1">اوپر لگے فلٹرز تبدیل کریں یا نئی ٹرانزیکشن درج کریں۔</p>
                        </td>
                    </tr>
                <?php else: foreach($transactions as $t): 
                    $type = $t['type'];
                    $isCashOut = ($type === 'SELL_CASH' || $type === 'BUY_EASYPAISA');
                    $isCashIn  = ($type === 'BUY_CASH' || $type === 'SELL_EASYPAISA');
                    $isBill    = ($type === 'UTILITY_BILL');
                    $isLoad    = ($type === 'EASYLOAD');
                    $isExpense = ($type === 'EXPENSE' || $type === 'DISCREPANCY_LOSS');
                    $isRefill  = ($type === 'BANK_REFILL');
                    $pmethod   = $t['payment_method'] ?? 'EASYPAISA';

                    // Channel badge style
                    $badgeBg = 'bg-teal-500/10 text-teal-300 border-teal-500/20';
                    $badgeEmoji = '🟢';
                    $channelName = 'ایزی پیسہ';
                    if ($pmethod === 'JAZZCASH') {
                        $badgeBg = 'bg-rose-500/10 text-rose-300 border-rose-500/20';
                        $badgeEmoji = '🔴';
                        $channelName = 'جاز کیش';
                    } elseif ($pmethod === 'SADAPAY') {
                        $badgeBg = 'bg-cyan-500/10 text-cyan-300 border-cyan-500/20';
                        $badgeEmoji = '🔵';
                        $channelName = 'ساداپے';
                    } elseif ($pmethod === 'NAYAPAY') {
                        $badgeBg = 'bg-amber-500/10 text-amber-300 border-amber-500/20';
                        $badgeEmoji = '🟠';
                        $channelName = 'نیاپے';
                    } elseif (strpos($pmethod, 'BANK') !== false) {
                        $badgeBg = 'bg-blue-500/10 text-blue-300 border-blue-500/20';
                        $badgeEmoji = '🏛️';
                        $channelName = 'آن لائن بینک';
                    } elseif ($pmethod === 'RAAST') {
                        $badgeBg = 'bg-purple-500/10 text-purple-300 border-purple-500/20';
                        $badgeEmoji = '🟣';
                        $channelName = 'راست';
                    }
                ?>
                    <tr class="hover:bg-slate-800/40 transition-colors">
                        <!-- Date & Time -->
                        <td class="py-3 px-4 font-mono text-slate-300">
                            <?= htmlspecialchars($t['date']) ?>
                            <span class="text-[10px] block text-slate-500"><?= htmlspecialchars($t['time']) ?></span>
                        </td>

                        <!-- Type Badge -->
                        <td class="py-3 px-4">
                            <?php if($isCashOut): ?>
                                <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-lg bg-emerald-500/10 text-emerald-400 border border-emerald-500/20 font-bold text-[11px]">
                                    <i data-lucide="arrow-down-right" class="w-3 h-3 text-emerald-400"></i>
                                    کیش آؤٹ (نکلوائی)
                                </span>
                            <?php elseif($isCashIn): ?>
                                <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-lg bg-teal-500/10 text-teal-300 border border-teal-500/20 font-bold text-[11px]">
                                    <i data-lucide="arrow-up-right" class="w-3 h-3 text-teal-300"></i>
                                    کیش ان (بھیجی گئی)
                                </span>
                            <?php elseif($isBill): ?>
                                <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-lg bg-indigo-500/10 text-indigo-300 border border-indigo-500/20 font-bold text-[11px]">
                                    <i data-lucide="receipt" class="w-3 h-3 text-indigo-400"></i>
                                    یوٹیلیٹی بل
                                </span>
                            <?php elseif($isLoad): ?>
                                <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-lg bg-sky-500/10 text-sky-300 border border-sky-500/20 font-bold text-[11px]">
                                    <i data-lucide="phone-call" class="w-3 h-3 text-sky-400"></i>
                                    موبائل لوڈ
                                </span>
                            <?php elseif($isRefill): ?>
                                <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-lg bg-purple-500/10 text-purple-300 border border-purple-500/20 font-bold text-[11px]">
                                    <i data-lucide="refresh-cw" class="w-3 h-3 text-purple-400"></i>
                                    والٹ ری فل
                                </span>
                            <?php else: ?>
                                <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-lg bg-rose-500/10 text-rose-400 border border-rose-500/20 font-bold text-[11px]">
                                    <i data-lucide="trending-down" class="w-3 h-3 text-rose-400"></i>
                                    دکان خرچہ
                                </span>
                            <?php endif; ?>
                        </td>

                        <!-- Payment Channel -->
                        <td class="py-3 px-4">
                            <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-lg border font-bold text-[11px] <?= $badgeBg ?>">
                                <span><?= $badgeEmoji ?></span>
                                <span><?= $channelName ?></span>
                            </span>
                        </td>

                        <!-- Customer Details -->
                        <td class="py-3 px-4 font-bold text-white">
                            <?= htmlspecialchars($t['customer_name']) ?>
                            <?php if(!empty($t['customer_phone'])): ?>
                                <span class="block text-[10px] font-mono text-slate-400"><?= htmlspecialchars($t['customer_phone']) ?></span>
                            <?php endif; ?>
                            <?php if(!empty($t['note'])): ?>
                                <span class="block text-[10px] text-slate-500 truncate max-w-[180px] font-normal" title="<?= htmlspecialchars($t['note']) ?>">
                                    📝 <?= htmlspecialchars($t['note']) ?>
                                </span>
                            <?php endif; ?>
                        </td>

                        <!-- TID / Ref -->
                        <td class="py-3 px-4 font-mono font-bold text-slate-300">
                            <?= htmlspecialchars($t['trx_id'] ?: '-') ?>
                        </td>

                        <!-- Digital Transfer Amount -->
                        <td class="py-3 px-4 text-left font-mono font-black text-white">
                            <?php if($isExpense): ?>
                                -
                            <?php else: ?>
                                Rs. <?= number_format($t['easy_paisa_amount']) ?>
                            <?php endif; ?>
                        </td>

                        <!-- Cash Exchange Amount -->
                        <td class="py-3 px-4 text-left font-mono font-bold text-slate-300">
                            <?php if($isExpense): ?>
                                -
                            <?php else: ?>
                                Rs. <?= number_format($t['cash_amount']) ?>
                            <?php endif; ?>
                        </td>

                        <!-- Profit or Expense -->
                        <td class="py-3 px-4 text-left font-mono font-black">
                            <?php if($isExpense): ?>
                                <span class="text-rose-400">-Rs. <?= number_format($t['expense_amount'] ?: $t['cash_amount']) ?></span>
                            <?php else: ?>
                                <span class="text-emerald-400">+Rs. <?= number_format($t['fee_profit']) ?></span>
                            <?php endif; ?>
                        </td>

                        <!-- Actions -->
                        <td class="py-3 px-4 text-center no-print">
                            <div class="flex items-center justify-center gap-1.5">
                                <!-- Print Thermal Slip -->
                                <button 
                                    onclick='printThermalReceipt(<?= json_encode($t) ?>)'
                                    class="p-1.5 rounded-lg bg-slate-800 hover:bg-slate-700 text-teal-400 transition-colors cursor-pointer"
                                    title="تھرمل رسید پرنٹ کریں"
                                >
                                    <i data-lucide="printer" class="w-3.5 h-3.5"></i>
                                </button>

                                <!-- WhatsApp Share -->
                                <?php if(!empty($t['customer_phone'])): ?>
                                    <button 
                                        onclick='shareWhatsAppReceipt(<?= json_encode($t) ?>)'
                                        class="p-1.5 rounded-lg bg-slate-800 hover:bg-emerald-950 text-emerald-400 transition-colors cursor-pointer"
                                        title="واٹس ایپ رسید بھیجیں"
                                    >
                                        <i data-lucide="send" class="w-3.5 h-3.5"></i>
                                    </button>
                                <?php endif; ?>

                                <!-- Edit -->
                                <button 
                                    onclick='openEditModal(<?= json_encode($t) ?>)'
                                    class="p-1.5 rounded-lg bg-slate-800 hover:bg-blue-950 text-sky-400 transition-colors cursor-pointer"
                                    title="ترمیم کریں (Edit)"
                                >
                                    <i data-lucide="edit-3" class="w-3.5 h-3.5"></i>
                                </button>

                                <!-- Delete -->
                                <form method="POST" onsubmit="return confirm('کیا آپ واقعی یہ ٹرانزیکشن ڈیلیٹ کرنا چاہتے ہیں؟');" class="inline">
                                    <input type="hidden" name="action" value="delete_transaction">
                                    <input type="hidden" name="del_id" value="<?= htmlspecialchars($t['id']) ?>">
                                    <button type="submit" class="p-1.5 rounded-lg bg-slate-800 hover:bg-rose-950 text-rose-400 transition-colors cursor-pointer" title="حذف کریں (Delete)">
                                        <i data-lucide="trash-2" class="w-3.5 h-3.5"></i>
                                    </button>
                                </form>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- ============================================================= -->
<!-- MODAL 1: NEW TRANSACTION ENTRY (جامع انٹری فارم)               -->
<!-- ============================================================= -->
<div id="newTrxModal" class="fixed inset-0 z-50 bg-black/80 backdrop-blur-sm hidden flex items-center justify-center p-3 sm:p-4 overflow-y-auto no-print">
    <div class="bg-slate-900 border border-slate-700 rounded-3xl w-full max-w-2xl overflow-hidden shadow-2xl animate-fade-in my-8">
        <!-- Modal Header -->
        <div class="p-4 sm:p-5 bg-gradient-to-r from-teal-900/40 via-slate-900 to-slate-900 border-b border-slate-800 flex items-center justify-between">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-2xl bg-teal-500/20 text-teal-400 flex items-center justify-center">
                    <i data-lucide="plus-circle" class="w-5 h-5"></i>
                </div>
                <div>
                    <h3 class="text-base sm:text-lg font-black text-white">نئی ایزی پیسہ / کیش انٹری</h3>
                    <p class="text-xs text-slate-400">کیش ان، کیش آؤٹ، یوٹیلیٹی بل یا دکان خرچ درج کریں</p>
                </div>
            </div>
            <button onclick="closeNewTrxModal()" class="w-8 h-8 rounded-xl bg-slate-800 hover:bg-slate-700 text-slate-400 hover:text-white flex items-center justify-center cursor-pointer">
                <i data-lucide="x" class="w-4 h-4"></i>
            </button>
        </div>

        <!-- Form Body -->
        <form method="POST" id="newTrxForm" class="p-5 sm:p-6 space-y-4">
            <input type="hidden" name="action" value="new_transaction">
            <input type="hidden" id="entryType" name="type" value="SELL_CASH">

            <!-- Type Tabs -->
            <div>
                <label class="block text-xs font-bold text-slate-300 mb-2">ٹرانزیکشن کیٹیگری منتخب کریں *</label>
                <div class="grid grid-cols-3 sm:grid-cols-6 gap-1.5 text-xs font-bold">
                    <button type="button" onclick="setEntryType('SELL_CASH')" id="tab_SELL_CASH" class="entry-tab-btn py-2 px-1 rounded-xl border text-center transition-all bg-emerald-600 text-white border-emerald-500">
                        کیش آؤٹ (نکلوائی)
                    </button>
                    <button type="button" onclick="setEntryType('BUY_CASH')" id="tab_BUY_CASH" class="entry-tab-btn py-2 px-1 rounded-xl border text-center transition-all bg-slate-800 text-slate-400 border-slate-700 hover:bg-slate-700">
                        کیش ان (بھیجی)
                    </button>
                    <button type="button" onclick="setEntryType('UTILITY_BILL')" id="tab_UTILITY_BILL" class="entry-tab-btn py-2 px-1 rounded-xl border text-center transition-all bg-slate-800 text-slate-400 border-slate-700 hover:bg-slate-700">
                        یوٹیلیٹی بل
                    </button>
                    <button type="button" onclick="setEntryType('EASYLOAD')" id="tab_EASYLOAD" class="entry-tab-btn py-2 px-1 rounded-xl border text-center transition-all bg-slate-800 text-slate-400 border-slate-700 hover:bg-slate-700">
                        موبائل لوڈ
                    </button>
                    <button type="button" onclick="setEntryType('EXPENSE')" id="tab_EXPENSE" class="entry-tab-btn py-2 px-1 rounded-xl border text-center transition-all bg-slate-800 text-slate-400 border-slate-700 hover:bg-slate-700">
                        دکان خرچہ
                    </button>
                    <button type="button" onclick="setEntryType('BANK_REFILL')" id="tab_BANK_REFILL" class="entry-tab-btn py-2 px-1 rounded-xl border text-center transition-all bg-slate-800 text-slate-400 border-slate-700 hover:bg-slate-700">
                        والٹ ری فل
                    </button>
                </div>
            </div>

            <!-- Payment Channel Chips -->
            <div id="channelSection">
                <label class="block text-xs font-bold text-slate-300 mb-2">اکاؤنٹ / والیٹ چینل منتخب کریں *</label>
                <div class="grid grid-cols-3 sm:grid-cols-6 gap-2">
                    <label class="channel-label cursor-pointer flex flex-col items-center p-2 rounded-xl border border-teal-500 bg-teal-500/10 text-teal-300 text-xs font-bold text-center">
                        <input type="radio" name="payment_method" value="EASYPAISA" checked class="hidden channel-radio">
                        <span class="text-base mb-0.5">🟢</span>
                        <span>ایزی پیسہ</span>
                    </label>

                    <label class="channel-label cursor-pointer flex flex-col items-center p-2 rounded-xl border border-slate-700 bg-slate-800/80 text-slate-300 text-xs font-bold text-center hover:bg-slate-700">
                        <input type="radio" name="payment_method" value="JAZZCASH" class="hidden channel-radio">
                        <span class="text-base mb-0.5">🔴</span>
                        <span>جاز کیش</span>
                    </label>

                    <label class="channel-label cursor-pointer flex flex-col items-center p-2 rounded-xl border border-slate-700 bg-slate-800/80 text-slate-300 text-xs font-bold text-center hover:bg-slate-700">
                        <input type="radio" name="payment_method" value="SADAPAY" class="hidden channel-radio">
                        <span class="text-base mb-0.5">🔵</span>
                        <span>ساداپے</span>
                    </label>

                    <label class="channel-label cursor-pointer flex flex-col items-center p-2 rounded-xl border border-slate-700 bg-slate-800/80 text-slate-300 text-xs font-bold text-center hover:bg-slate-700">
                        <input type="radio" name="payment_method" value="NAYAPAY" class="hidden channel-radio">
                        <span class="text-base mb-0.5">🟠</span>
                        <span>نیاپے</span>
                    </label>

                    <label class="channel-label cursor-pointer flex flex-col items-center p-2 rounded-xl border border-slate-700 bg-slate-800/80 text-slate-300 text-xs font-bold text-center hover:bg-slate-700">
                        <input type="radio" name="payment_method" value="BANK" class="hidden channel-radio">
                        <span class="text-base mb-0.5">🏛️</span>
                        <span>آن لائن بینک</span>
                    </label>

                    <label class="channel-label cursor-pointer flex flex-col items-center p-2 rounded-xl border border-slate-700 bg-slate-800/80 text-slate-300 text-xs font-bold text-center hover:bg-slate-700">
                        <input type="radio" name="payment_method" value="RAAST" class="hidden channel-radio">
                        <span class="text-base mb-0.5">🟣</span>
                        <span>راست ID</span>
                    </label>
                </div>
            </div>

            <!-- Customer Details -->
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3" id="customerFields">
                <div>
                    <label class="block text-xs font-bold text-slate-400 mb-1">گاہک کا نام</label>
                    <input type="text" name="customer_name" id="cnameInput" placeholder="محمد عثمان" class="w-full bg-slate-950 border border-slate-700 text-white px-3 py-2.5 rounded-xl text-xs focus:border-teal-500 focus:outline-none">
                </div>
                <div>
                    <label class="block text-xs font-bold text-slate-400 mb-1">موبائل نمبر (WhatsApp)</label>
                    <input type="text" name="customer_phone" id="cphoneInput" placeholder="0300-1234567" class="w-full bg-slate-950 border border-slate-700 text-white px-3 py-2.5 rounded-xl text-xs font-mono focus:border-teal-500 focus:outline-none">
                </div>
            </div>

            <!-- Amounts Grid -->
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                <div id="epAmtWrapper">
                    <label class="block text-xs font-bold text-teal-300 mb-1">والٹ ٹرانسفر رقم (Rs) *</label>
                    <input type="number" step="any" name="easy_paisa_amount" id="epAmountInput" required min="1" placeholder="5000" oninput="calcAmounts()" class="w-full bg-slate-950 border border-teal-500/50 text-white px-3 py-2.5 rounded-xl text-sm font-mono font-black focus:border-teal-400 focus:outline-none">
                </div>

                <div id="cashAmtWrapper">
                    <label class="block text-xs font-bold text-amber-300 mb-1" id="cashAmtLabel">نقد کیش دیا گیا (Rs)</label>
                    <input type="number" step="any" name="cash_amount" id="cashAmountInput" placeholder="4900" class="w-full bg-slate-950 border border-slate-700 text-white px-3 py-2.5 rounded-xl text-sm font-mono font-black focus:border-amber-400 focus:outline-none">
                </div>

                <div id="feeProfitWrapper">
                    <label class="block text-xs font-bold text-emerald-400 mb-1" id="feeProfitLabel">کمیشن / فیس منافع (Rs)</label>
                    <input type="number" step="any" name="fee_profit" id="feeProfitInput" value="100" class="w-full bg-slate-950 border border-emerald-500/40 text-emerald-400 px-3 py-2.5 rounded-xl text-sm font-mono font-black focus:border-emerald-300 focus:outline-none">
                </div>

                <div id="expenseAmtWrapper" class="hidden">
                    <label class="block text-xs font-bold text-rose-400 mb-1">خرچہ رقم (Rs) *</label>
                    <input type="number" step="any" name="expense_amount" id="expenseAmountInput" placeholder="350" class="w-full bg-slate-950 border border-rose-500/40 text-rose-400 px-3 py-2.5 rounded-xl text-sm font-mono font-black focus:border-rose-300 focus:outline-none">
                </div>
            </div>

            <!-- Quick Amount Chips -->
            <div id="quickAmtChips" class="flex flex-wrap items-center gap-1.5">
                <span class="text-[11px] text-slate-500">فوری رقم:</span>
                <button type="button" onclick="setQuickAmount(1000)" class="px-2 py-0.5 rounded-lg bg-slate-800 hover:bg-slate-700 text-slate-300 font-mono text-xs cursor-pointer">1,000</button>
                <button type="button" onclick="setQuickAmount(2000)" class="px-2 py-0.5 rounded-lg bg-slate-800 hover:bg-slate-700 text-slate-300 font-mono text-xs cursor-pointer">2,000</button>
                <button type="button" onclick="setQuickAmount(5000)" class="px-2 py-0.5 rounded-lg bg-slate-800 hover:bg-slate-700 text-slate-300 font-mono text-xs cursor-pointer">5,000</button>
                <button type="button" onclick="setQuickAmount(10000)" class="px-2 py-0.5 rounded-lg bg-slate-800 hover:bg-slate-700 text-slate-300 font-mono text-xs cursor-pointer">10,000</button>
                <button type="button" onclick="setQuickAmount(20000)" class="px-2 py-0.5 rounded-lg bg-slate-800 hover:bg-slate-700 text-slate-300 font-mono text-xs cursor-pointer">20,000</button>
                <button type="button" onclick="setQuickAmount(50000)" class="px-2 py-0.5 rounded-lg bg-slate-800 hover:bg-slate-700 text-slate-300 font-mono text-xs cursor-pointer">50,000</button>
            </div>

            <!-- Trx ID & Date/Time -->
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                <div>
                    <div class="flex items-center justify-between mb-1">
                        <label class="block text-xs font-bold text-slate-400">Trx ID / TID نمبر</label>
                        <button type="button" onclick="generateAutoTID()" class="text-[10px] text-teal-400 hover:underline cursor-pointer">آٹو جنریٹ</button>
                    </div>
                    <input type="text" name="trx_id" id="trxIdInput" placeholder="TID-88410294" class="w-full bg-slate-950 border border-slate-700 text-white px-3 py-2.5 rounded-xl text-xs font-mono font-bold focus:border-teal-500 focus:outline-none">
                </div>
                <div>
                    <label class="block text-xs font-bold text-slate-400 mb-1">تاریخ</label>
                    <input type="date" name="date" value="<?= $today ?>" class="w-full bg-slate-950 border border-slate-700 text-white px-3 py-2.5 rounded-xl text-xs font-mono focus:border-teal-500 focus:outline-none">
                </div>
                <div>
                    <label class="block text-xs font-bold text-slate-400 mb-1">وقت</label>
                    <input type="text" name="time" value="<?= date('h:i A') ?>" class="w-full bg-slate-950 border border-slate-700 text-white px-3 py-2.5 rounded-xl text-xs font-mono focus:border-teal-500 focus:outline-none">
                </div>
            </div>

            <!-- Notes or Bill Company / Consumer Number -->
            <div>
                <label class="block text-xs font-bold text-slate-400 mb-1" id="notesLabel">تفصیل، نوٹ یا بل کنزیومر نمبر</label>
                <input type="text" name="notes" id="notesInput" placeholder="فیملی ٹرانسفر، لیسکو بل کنزیومر: 08112345678900U" class="w-full bg-slate-950 border border-slate-700 text-white px-3 py-2.5 rounded-xl text-xs focus:border-teal-500 focus:outline-none">
            </div>

            <!-- Submit Button -->
            <div class="pt-3 border-t border-slate-800 flex items-center justify-end gap-3">
                <button type="button" onclick="closeNewTrxModal()" class="px-4 py-2.5 rounded-xl bg-slate-800 hover:bg-slate-700 text-slate-300 font-bold text-xs cursor-pointer">
                    منسوخ کریں
                </button>
                <button type="submit" class="px-6 py-2.5 rounded-xl bg-gradient-to-r from-emerald-600 to-teal-600 hover:from-emerald-500 hover:to-teal-500 text-white font-black text-xs sm:text-sm shadow-lg shadow-emerald-600/30 transition-all cursor-pointer">
                    ٹرانزیکشن محفوظ کریں (Save)
                </button>
            </div>
        </form>
    </div>
</div>

<!-- ============================================================= -->
<!-- MODAL 2: EDIT TRANSACTION MODAL                               -->
<!-- ============================================================= -->
<div id="editTrxModal" class="fixed inset-0 z-50 bg-black/80 backdrop-blur-sm hidden flex items-center justify-center p-3 sm:p-4 overflow-y-auto no-print">
    <div class="bg-slate-900 border border-slate-700 rounded-3xl w-full max-w-xl overflow-hidden shadow-2xl animate-fade-in my-8">
        <div class="p-4 bg-slate-850 border-b border-slate-800 flex items-center justify-between">
            <h3 class="text-base font-bold text-white flex items-center gap-2">
                <i data-lucide="edit" class="w-5 h-5 text-sky-400"></i>
                <span>ٹرانزیکشن ریکارڈ میں ترمیم</span>
            </h3>
            <button onclick="closeEditModal()" class="w-8 h-8 rounded-xl bg-slate-800 hover:bg-slate-700 text-slate-400 hover:text-white flex items-center justify-center cursor-pointer">
                <i data-lucide="x" class="w-4 h-4"></i>
            </button>
        </div>
        <form method="POST" class="p-5 space-y-3.5">
            <input type="hidden" name="action" value="edit_transaction">
            <input type="hidden" name="edit_id" id="edit_id">

            <div class="grid grid-cols-2 gap-2">
                <div>
                    <label class="block text-xs text-slate-400 mb-1">قسم (Type)</label>
                    <select name="type" id="edit_type" class="w-full bg-slate-950 border border-slate-700 text-white px-3 py-2 rounded-xl text-xs">
                        <option value="SELL_CASH">کیش آؤٹ (نکلوائی)</option>
                        <option value="BUY_CASH">کیش ان (بھیجا)</option>
                        <option value="UTILITY_BILL">یوٹیلیٹی بل</option>
                        <option value="EASYLOAD">موبائل لوڈ</option>
                        <option value="EXPENSE">دکان خرچہ</option>
                        <option value="BANK_REFILL">والٹ ری فل</option>
                    </select>
                </div>
                <div>
                    <label class="block text-xs text-slate-400 mb-1">چینل</label>
                    <select name="payment_method" id="edit_pmethod" class="w-full bg-slate-950 border border-slate-700 text-white px-3 py-2 rounded-xl text-xs">
                        <option value="EASYPAISA">🟢 ایزی پیسہ</option>
                        <option value="JAZZCASH">🔴 جاز کیش</option>
                        <option value="SADAPAY">🔵 ساداپے</option>
                        <option value="NAYAPAY">🟠 نیاپے</option>
                        <option value="BANK">🏛️ آن لائن بینک</option>
                        <option value="RAAST">🟣 راست</option>
                    </select>
                </div>
            </div>

            <div class="grid grid-cols-2 gap-2">
                <div>
                    <label class="block text-xs text-slate-400 mb-1">کسٹمر کا نام</label>
                    <input type="text" name="customer_name" id="edit_cname" class="w-full bg-slate-950 border border-slate-700 text-white px-3 py-2 rounded-xl text-xs">
                </div>
                <div>
                    <label class="block text-xs text-slate-400 mb-1">موبائل نمبر</label>
                    <input type="text" name="customer_phone" id="edit_cphone" class="w-full bg-slate-950 border border-slate-700 text-white px-3 py-2 rounded-xl text-xs font-mono">
                </div>
            </div>

            <div class="grid grid-cols-3 gap-2">
                <div>
                    <label class="block text-xs text-teal-400 mb-1">والٹ رقم (Rs)</label>
                    <input type="number" step="any" name="easy_paisa_amount" id="edit_epAmt" class="w-full bg-slate-950 border border-slate-700 text-white px-3 py-2 rounded-xl text-xs font-mono font-bold">
                </div>
                <div>
                    <label class="block text-xs text-amber-400 mb-1">کیش رقم (Rs)</label>
                    <input type="number" step="any" name="cash_amount" id="edit_cashAmt" class="w-full bg-slate-950 border border-slate-700 text-white px-3 py-2 rounded-xl text-xs font-mono font-bold">
                </div>
                <div>
                    <label class="block text-xs text-emerald-400 mb-1">کمیشن منافع (Rs)</label>
                    <input type="number" step="any" name="fee_profit" id="edit_profit" class="w-full bg-slate-950 border border-slate-700 text-emerald-400 px-3 py-2 rounded-xl text-xs font-mono font-bold">
                </div>
            </div>

            <div class="grid grid-cols-2 gap-2">
                <div>
                    <label class="block text-xs text-slate-400 mb-1">Trx ID / حوالہ</label>
                    <input type="text" name="trx_id" id="edit_trxId" class="w-full bg-slate-950 border border-slate-700 text-white px-3 py-2 rounded-xl text-xs font-mono">
                </div>
                <div>
                    <label class="block text-xs text-slate-400 mb-1">تاریخ</label>
                    <input type="date" name="date" id="edit_date" class="w-full bg-slate-950 border border-slate-700 text-white px-3 py-2 rounded-xl text-xs font-mono">
                </div>
            </div>

            <div>
                <label class="block text-xs text-slate-400 mb-1">تفصیل / نوٹ</label>
                <input type="text" name="notes" id="edit_notes" class="w-full bg-slate-950 border border-slate-700 text-white px-3 py-2 rounded-xl text-xs">
            </div>

            <div class="pt-3 border-t border-slate-800 flex justify-end gap-2">
                <button type="button" onclick="closeEditModal()" class="px-4 py-2 rounded-xl bg-slate-800 text-slate-300 text-xs font-bold">منسوخ</button>
                <button type="submit" class="px-5 py-2 rounded-xl bg-sky-600 hover:bg-sky-500 text-white text-xs font-bold">اپ ڈیٹ کریں</button>
            </div>
        </form>
    </div>
</div>

<!-- ============================================================= -->
<!-- MODAL 3: DAILY OPENING BALANCE MODAL (صبح کا اوپننگ بیلنس)     -->
<!-- ============================================================= -->
<div id="openingBalanceModal" class="fixed inset-0 z-50 bg-black/80 backdrop-blur-sm hidden flex items-center justify-center p-3 sm:p-4 no-print">
    <div class="bg-slate-900 border border-slate-700 rounded-3xl w-full max-w-md overflow-hidden shadow-2xl animate-fade-in">
        <div class="p-4 bg-gradient-to-r from-amber-900/30 to-slate-900 border-b border-slate-800 flex items-center justify-between">
            <h3 class="text-base font-bold text-white flex items-center gap-2">
                <i data-lucide="sun" class="w-5 h-5 text-amber-400"></i>
                <span>صبح کا افتتاحی کیش و ایزی پیسہ بیلنس</span>
            </h3>
            <button onclick="closeOpeningBalanceModal()" class="w-8 h-8 rounded-xl bg-slate-800 hover:bg-slate-700 text-slate-400 hover:text-white flex items-center justify-center cursor-pointer">
                <i data-lucide="x" class="w-4 h-4"></i>
            </button>
        </div>
        <form method="POST" class="p-5 space-y-4">
            <input type="hidden" name="action" value="save_daily_opening">

            <div>
                <label class="block text-xs font-bold text-slate-300 mb-1">تاریخ (Date)</label>
                <input type="date" name="opening_date" value="<?= $today ?>" class="w-full bg-slate-950 border border-slate-700 text-white px-3 py-2.5 rounded-xl text-xs font-mono font-bold">
            </div>

            <div>
                <label class="block text-xs font-bold text-amber-400 mb-1">صبح کا دراز کیش (Morning Cash in Drawer - Rs)</label>
                <input type="number" step="any" name="opening_cash" value="<?= htmlspecialchars($todayOpening['opening_cash']) ?>" required class="w-full bg-slate-950 border border-amber-500/40 text-amber-300 px-3 py-2.5 rounded-xl text-sm font-mono font-bold">
            </div>

            <div>
                <label class="block text-xs font-bold text-teal-400 mb-1">صبح کا ایزی پیسہ و والٹس بیلنس (Opening EP - Rs)</label>
                <input type="number" step="any" name="opening_easypaisa" value="<?= htmlspecialchars($todayOpening['opening_easypaisa']) ?>" required class="w-full bg-slate-950 border border-teal-500/40 text-teal-300 px-3 py-2.5 rounded-xl text-sm font-mono font-bold">
            </div>

            <div>
                <label class="block text-xs font-bold text-slate-400 mb-1">نوٹ یا کمنٹ</label>
                <input type="text" name="opening_notes" value="<?= htmlspecialchars($todayOpening['notes']) ?>" placeholder="صبح دکان کھولنے پر گنتی شدہ کیش" class="w-full bg-slate-950 border border-slate-700 text-white px-3 py-2.5 rounded-xl text-xs">
            </div>

            <div class="pt-3 border-t border-slate-800 flex justify-end gap-2">
                <button type="button" onclick="closeOpeningBalanceModal()" class="px-4 py-2.5 rounded-xl bg-slate-800 text-slate-300 font-bold text-xs">منسوخ</button>
                <button type="submit" class="px-5 py-2.5 rounded-xl bg-gradient-to-r from-amber-600 to-amber-500 text-white font-black text-xs">بیلنس محفوظ کریں</button>
            </div>
        </form>
    </div>
</div>

<!-- ============================================================= -->
<!-- MODAL 4: THERMAL RECEIPT SLIP MODAL (80mm / 58mm پرچی)         -->
<!-- ============================================================= -->
<div id="receiptModal" class="fixed inset-0 z-50 bg-black/85 backdrop-blur-sm hidden flex items-center justify-center p-3 overflow-y-auto no-print">
    <div class="bg-slate-900 border border-slate-700 rounded-3xl w-full max-w-sm overflow-hidden shadow-2xl animate-fade-in my-6">
        <div class="p-3.5 bg-slate-850 border-b border-slate-800 flex items-center justify-between">
            <h4 class="text-xs font-bold text-white flex items-center gap-1.5">
                <i data-lucide="receipt" class="w-4 h-4 text-teal-400"></i>
                <span>تھرمل رسید و واؤچر پریویو</span>
            </h4>
            <button onclick="closeReceiptModal()" class="w-7 h-7 rounded-lg bg-slate-800 hover:bg-slate-700 text-slate-400 hover:text-white flex items-center justify-center">
                <i data-lucide="x" class="w-3.5 h-3.5"></i>
            </button>
        </div>

        <!-- Receipt Slip Visual Content (Ready to print) -->
        <div class="p-4 bg-white text-slate-900 font-mono text-xs select-all" id="printableThermalSlip">
            <div class="text-center pb-3 border-b-2 border-dashed border-slate-400">
                <h2 class="text-base font-black tracking-tight uppercase"><?= $shopName ?></h2>
                <p class="text-[11px] text-slate-700"><?= htmlspecialchars($settings['address'] ?? 'مین بازار، پاکستان') ?></p>
                <p class="text-[11px] font-bold text-slate-800">فون: <?= htmlspecialchars($settings['phone'] ?? '0300-1234567') ?></p>
                <div class="mt-1.5 inline-block bg-slate-100 border border-slate-300 px-2 py-0.5 rounded text-[10px] font-bold">
                    ایزی پیسہ و منی ٹرانسفر رسید
                </div>
            </div>

            <div class="py-2.5 space-y-1 text-[11px] border-b border-dashed border-slate-300">
                <div class="flex justify-between">
                    <span class="text-slate-600">رسید نمبر:</span>
                    <strong id="slipReceiptId" class="font-mono">-</strong>
                </div>
                <div class="flex justify-between">
                    <span class="text-slate-600">تاریخ و وقت:</span>
                    <span id="slipDateTime">-</span>
                </div>
                <div class="flex justify-between">
                    <span class="text-slate-600">ٹرانزیکشن قسم:</span>
                    <strong id="slipType" class="text-slate-900">-</strong>
                </div>
                <div class="flex justify-between">
                    <span class="text-slate-600">والٹ / چینل:</span>
                    <strong id="slipChannel" class="text-slate-900">-</strong>
                </div>
                <div class="flex justify-between">
                    <span class="text-slate-600">کسٹمر نام:</span>
                    <strong id="slipCustomerName" class="text-slate-900">-</strong>
                </div>
                <div class="flex justify-between">
                    <span class="text-slate-600">موبائل نمبر:</span>
                    <span id="slipCustomerPhone" class="font-mono">-</span>
                </div>
                <div class="flex justify-between">
                    <span class="text-slate-600">Trx ID / TID:</span>
                    <strong id="slipTrxId" class="font-mono text-slate-900">-</strong>
                </div>
            </div>

            <div class="py-2.5 space-y-1.5 text-xs border-b-2 border-dashed border-slate-400">
                <div class="flex justify-between">
                    <span class="text-slate-600">ٹرانسفر رقم:</span>
                    <strong class="text-sm font-black" id="slipAmount">Rs. 0</strong>
                </div>
                <div class="flex justify-between">
                    <span class="text-slate-600">سروس فیس / چارجز:</span>
                    <span id="slipFee">Rs. 0</span>
                </div>
                <div class="flex justify-between font-black text-sm pt-1 border-t border-slate-200">
                    <span>کل نقد تبادلہ:</span>
                    <span id="slipCashTotal">Rs. 0</span>
                </div>
            </div>

            <div class="pt-3 text-center text-[10px] space-y-1 text-slate-600">
                <p id="slipNote" class="italic text-slate-700"></p>
                <p class="font-bold text-slate-800">آپ کے تعاون اور اعتماد کا شکریہ!</p>
                <p class="text-[9px] text-slate-500">سافٹ ویئر تیار کردہ: بلال موبائلز پی او ایس سسٹم</p>
            </div>
        </div>

        <div class="p-3 bg-slate-950 border-t border-slate-800 flex items-center justify-between gap-2">
            <button onclick="closeReceiptModal()" class="px-3 py-2 bg-slate-800 hover:bg-slate-700 text-slate-300 text-xs font-bold rounded-xl">بند کریں</button>
            <button onclick="printCurrentSlip()" class="flex-1 py-2 bg-gradient-to-r from-teal-600 to-emerald-600 hover:from-teal-500 hover:to-emerald-500 text-white font-bold text-xs rounded-xl flex items-center justify-center gap-1.5 shadow-md">
                <i data-lucide="printer" class="w-4 h-4"></i>
                <span>پرنٹ سلپ (Print)</span>
            </button>
        </div>
    </div>
</div>

<!-- Scripts for Dynamic Interactivity -->
<script>
    // Initialize Chart.js
    const ctx = document.getElementById('epVolumeChart').getContext('2d');
    new Chart(ctx, {
        type: 'bar',
        data: {
            labels: <?= json_encode($chartLabels) ?>,
            datasets: [
                {
                    label: 'ٹرانزیکشن والیم (Volume PKR)',
                    data: <?= json_encode($chartVolumes) ?>,
                    backgroundColor: 'rgba(20, 184, 166, 0.7)',
                    borderColor: '#14b8a6',
                    borderWidth: 1.5,
                    borderRadius: 8,
                    yAxisID: 'y'
                },
                {
                    label: 'کمیشن منافع (Profit PKR)',
                    data: <?= json_encode($chartProfits) ?>,
                    backgroundColor: 'rgba(52, 211, 153, 0.9)',
                    borderColor: '#10b981',
                    borderWidth: 1.5,
                    borderRadius: 8,
                    type: 'line',
                    yAxisID: 'y1'
                }
            ]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            interaction: { mode: 'index', intersect: false },
            scales: {
                x: {
                    grid: { color: 'rgba(255, 255, 255, 0.05)' },
                    ticks: { color: '#94a3b8', font: { size: 11 } }
                },
                y: {
                    type: 'linear',
                    display: true,
                    position: 'left',
                    grid: { color: 'rgba(255, 255, 255, 0.05)' },
                    ticks: { color: '#14b8a6', font: { size: 10 } }
                },
                y1: {
                    type: 'linear',
                    display: true,
                    position: 'right',
                    grid: { drawOnChartArea: false },
                    ticks: { color: '#34d399', font: { size: 10 } }
                }
            },
            plugins: {
                legend: { display: false }
            }
        }
    });

    // Modal Handlers
    function openNewTrxModal() {
        document.getElementById('newTrxModal').classList.remove('hidden');
    }
    function closeNewTrxModal() {
        document.getElementById('newTrxModal').classList.add('hidden');
    }

    function openOpeningBalanceModal() {
        document.getElementById('openingBalanceModal').classList.remove('hidden');
    }
    function closeOpeningBalanceModal() {
        document.getElementById('openingBalanceModal').classList.add('hidden');
    }

    function closeReceiptModal() {
        document.getElementById('receiptModal').classList.add('hidden');
    }

    // Tab Switching for Entry Form
    function setEntryType(type) {
        document.getElementById('entryType').value = type;
        document.querySelectorAll('.entry-tab-btn').forEach(btn => {
            btn.className = 'entry-tab-btn py-2 px-1 rounded-xl border text-center transition-all bg-slate-800 text-slate-400 border-slate-700 hover:bg-slate-700';
        });
        const activeBtn = document.getElementById('tab_' + type);
        if (activeBtn) {
            activeBtn.className = 'entry-tab-btn py-2 px-1 rounded-xl border text-center transition-all bg-emerald-600 text-white border-emerald-500 shadow-md';
        }

        const epWrapper = document.getElementById('epAmtWrapper');
        const cashWrapper = document.getElementById('cashAmtWrapper');
        const feeWrapper = document.getElementById('feeProfitWrapper');
        const expWrapper = document.getElementById('expenseAmtWrapper');
        const customerFields = document.getElementById('customerFields');
        const notesLabel = document.getElementById('notesLabel');
        const cashAmtLabel = document.getElementById('cashAmtLabel');

        if (type === 'EXPENSE') {
            epWrapper.classList.add('hidden');
            cashWrapper.classList.add('hidden');
            feeWrapper.classList.add('hidden');
            expWrapper.classList.remove('hidden');
            notesLabel.innerText = 'خرچے کی نوعیت (مثلاً: چائے، کھانا، بل، کرایہ)';
            document.getElementById('cnameInput').value = 'دکان کا خرچہ';
        } else if (type === 'UTILITY_BILL') {
            epWrapper.classList.remove('hidden');
            cashWrapper.classList.remove('hidden');
            feeWrapper.classList.remove('hidden');
            expWrapper.classList.add('hidden');
            cashAmtLabel.innerText = 'گاہک سے نقد وصولی (Rs)';
            notesLabel.innerText = 'ادارہ و کنزیومر نمبر (مثلاً: LESCO 08112345678900U)';
            document.getElementById('feeProfitInput').value = 30;
        } else if (type === 'EASYLOAD') {
            epWrapper.classList.remove('hidden');
            cashWrapper.classList.remove('hidden');
            feeWrapper.classList.remove('hidden');
            expWrapper.classList.add('hidden');
            cashAmtLabel.innerText = 'گاہک سے نقد رقم (Rs)';
            notesLabel.innerText = 'نیٹ ورک و پیکیج (Jazz, Zong, Telenor, Ufone)';
        } else if (type === 'BUY_CASH') {
            // Send Money: Customer gives cash, Shop sends EP
            epWrapper.classList.remove('hidden');
            cashWrapper.classList.remove('hidden');
            feeWrapper.classList.remove('hidden');
            expWrapper.classList.add('hidden');
            cashAmtLabel.innerText = 'گاہک سے نقد وصول شدہ رقم (Rs)';
            notesLabel.innerText = 'نوٹ یا ٹرانسفر وجہ (مثلاً: فیملی خرچ)';
        } else {
            // Cash Out: Customer gives EP, Shop pays cash
            epWrapper.classList.remove('hidden');
            cashWrapper.classList.remove('hidden');
            feeWrapper.classList.remove('hidden');
            expWrapper.classList.add('hidden');
            cashAmtLabel.innerText = 'گاہک کو نقد ادائیگی (Rs)';
            notesLabel.innerText = 'نوٹ یا ٹرانسفر تفصیل';
        }
        calcAmounts();
    }

    // Auto-calculate Fee and Cash
    function calcAmounts() {
        const type = document.getElementById('entryType').value;
        const epVal = parseFloat(document.getElementById('epAmountInput').value) || 0;
        let fee = parseFloat(document.getElementById('feeProfitInput').value);

        if (type === 'SELL_CASH') {
            // Cash Out (نکلوائی): e.g. 20 PKR per 1,000
            if (isNaN(fee) || fee <= 0) {
                fee = Math.max(20, Math.round(epVal * 0.02));
                document.getElementById('feeProfitInput').value = fee;
            }
            document.getElementById('cashAmountInput').value = Math.max(0, epVal - fee);
        } else if (type === 'BUY_CASH') {
            // Send Money (بھیجا): customer pays transfer amount + fee
            if (isNaN(fee) || fee <= 0) {
                fee = Math.max(20, Math.round(epVal * 0.015));
                document.getElementById('feeProfitInput').value = fee;
            }
            document.getElementById('cashAmountInput').value = epVal + fee;
        } else if (type === 'UTILITY_BILL') {
            document.getElementById('cashAmountInput').value = epVal + (fee || 30);
        } else if (type === 'EASYLOAD') {
            document.getElementById('cashAmountInput').value = epVal;
            if (isNaN(fee) || fee <= 0) {
                document.getElementById('feeProfitInput').value = Math.round(epVal * 0.03);
            }
        }
    }

    function setQuickAmount(amt) {
        document.getElementById('epAmountInput').value = amt;
        calcAmounts();
    }

    function generateAutoTID() {
        const randomNum = Math.floor(10000000 + Math.random() * 90000000);
        document.getElementById('trxIdInput').value = 'TID-' + randomNum;
    }

    // Radio Channel Highlight
    document.querySelectorAll('.channel-radio').forEach(r => {
        r.addEventListener('change', function() {
            document.querySelectorAll('.channel-label').forEach(l => {
                l.className = 'channel-label cursor-pointer flex flex-col items-center p-2 rounded-xl border border-slate-700 bg-slate-800/80 text-slate-300 text-xs font-bold text-center hover:bg-slate-700';
            });
            this.closest('label').className = 'channel-label cursor-pointer flex flex-col items-center p-2 rounded-xl border border-teal-500 bg-teal-500/10 text-teal-300 text-xs font-bold text-center';
        });
    });

    // Thermal Slip Trigger
    function printThermalReceipt(trx) {
        document.getElementById('slipReceiptId').innerText = trx.id;
        document.getElementById('slipDateTime').innerText = trx.date + ' ' + (trx.time || '');
        document.getElementById('slipCustomerName').innerText = trx.customer_name || 'Walk-in Customer';
        document.getElementById('slipCustomerPhone').innerText = trx.customer_phone || '-';
        document.getElementById('slipTrxId').innerText = trx.trx_id || '-';
        document.getElementById('slipChannel').innerText = trx.payment_method || 'EasyPaisa';
        document.getElementById('slipAmount').innerText = 'Rs. ' + Number(trx.easy_paisa_amount || 0).toLocaleString();
        document.getElementById('slipFee').innerText = 'Rs. ' + Number(trx.fee_profit || 0).toLocaleString();
        document.getElementById('slipCashTotal').innerText = 'Rs. ' + Number(trx.cash_amount || 0).toLocaleString();
        document.getElementById('slipNote').innerText = trx.note ? ('نوٹ: ' + trx.note) : '';

        let typeText = 'رقم نکلوائی (Cash Out)';
        if (trx.type === 'BUY_CASH' || trx.type === 'SELL_EASYPAISA') typeText = 'رقم بھیجی (Send Money)';
        if (trx.type === 'UTILITY_BILL') typeText = 'یوٹیلیٹی بل ادائیگی';
        if (trx.type === 'EASYLOAD') typeText = 'موبائل ایزی لوڈ';
        if (trx.type === 'EXPENSE') typeText = 'دکان خرچہ';
        document.getElementById('slipType').innerText = typeText;

        document.getElementById('receiptModal').classList.remove('hidden');
    }

    function printCurrentSlip() {
        window.print();
    }

    // WhatsApp Direct Receipt Share
    function shareWhatsAppReceipt(trx) {
        if (!trx.customer_phone) return;
        let phone = trx.customer_phone.replace(/[^0-9]/g, '');
        if (phone.startsWith('03')) phone = '92' + phone.substring(1);

        let typeUrdu = trx.type === 'BUY_CASH' ? 'رقم بھیجی (Send Money)' : 'رقم نکلوائی (Cash Out)';
        let msg = `*${"<?= $shopName ?>"}: ٹرانزیکشن رسید*\n` +
                  `------------------------------\n` +
                  `👤 کسٹمر نام: ${trx.customer_name}\n` +
                  `📱 ٹائپ: ${typeUrdu}\n` +
                  `💳 والٹ: ${trx.payment_method}\n` +
                  `🔢 حوالہ / Trx ID: ${trx.trx_id || '-'}\n` +
                  `💰 والٹ رقم: Rs. ${Number(trx.easy_paisa_amount).toLocaleString()}\n` +
                  `💵 کیش رقم: Rs. ${Number(trx.cash_amount).toLocaleString()}\n` +
                  `📅 تاریخ و وقت: ${trx.date} ${trx.time}\n` +
                  `------------------------------\n` +
                  `تشریف آوری کا شکریہ!`;

        const url = `https://wa.me/${phone}?text=${encodeURIComponent(msg)}`;
        window.open(url, '_blank');
    }

    // Edit Modal Trigger
    function openEditModal(trx) {
        document.getElementById('edit_id').value = trx.id;
        document.getElementById('edit_type').value = trx.type;
        document.getElementById('edit_pmethod').value = trx.payment_method || 'EASYPAISA';
        document.getElementById('edit_cname').value = trx.customer_name || '';
        document.getElementById('edit_cphone').value = trx.customer_phone || '';
        document.getElementById('edit_epAmt').value = trx.easy_paisa_amount || 0;
        document.getElementById('edit_cashAmt').value = trx.cash_amount || 0;
        document.getElementById('edit_profit').value = trx.fee_profit || 0;
        document.getElementById('edit_trxId').value = trx.trx_id || '';
        document.getElementById('edit_date').value = trx.date || '';
        document.getElementById('edit_notes').value = trx.note || '';

        document.getElementById('editTrxModal').classList.remove('hidden');
    }
    function closeEditModal() {
        document.getElementById('editTrxModal').classList.add('hidden');
    }

    // CSV Export
    function exportTableToCSV() {
        let csv = '\uFEFF'; // UTF-8 BOM
        csv += 'تاریخ,وقت,قسم,چینل,گاہک کا نام,موبائل نمبر,حوالہ Trx ID,والٹ رقم,نقد کیش,کمیشن منافع,نوٹ\n';
        
        const rows = document.querySelectorAll('#ledgerTable tbody tr');
        rows.forEach(r => {
            const cols = r.querySelectorAll('td');
            if (cols.length < 8) return;
            let date = cols[0].innerText.replace(/\n/g, ' ').trim();
            let type = cols[1].innerText.trim();
            let channel = cols[2].innerText.trim();
            let customer = cols[3].innerText.replace(/\n/g, ' ').trim();
            let trxId = cols[4].innerText.trim();
            let epAmt = cols[5].innerText.replace(/[^0-9.]/g, '').trim();
            let cashAmt = cols[6].innerText.replace(/[^0-9.]/g, '').trim();
            let profit = cols[7].innerText.replace(/[^0-9.]/g, '').trim();

            csv += `"${date}","${type}","${channel}","${customer}","${trxId}","${epAmt}","${cashAmt}","${profit}"\n`;
        });

        const blob = new Blob([csv], { type: 'text/csv;charset=utf-8;' });
        const link = document.createElement('a');
        link.href = URL.createObjectURL(blob);
        link.setAttribute('download', `EasyPaisa_Ledger_${new Date().toISOString().slice(0,10)}.csv`);
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);
    }
</script>

<!-- Print Only Thermal Style for Slip Printing -->
<style>
@media print {
    body * {
        visibility: hidden;
    }
    #printableThermalSlip, #printableThermalSlip * {
        visibility: visible;
    }
    #printableThermalSlip {
        position: absolute;
        left: 0;
        top: 0;
        width: 80mm;
        margin: 0;
        padding: 8px;
        background: white !important;
        color: black !important;
    }
}
</style>

<?php require_once __DIR__ . '/../backend/footer.php'; ?>
