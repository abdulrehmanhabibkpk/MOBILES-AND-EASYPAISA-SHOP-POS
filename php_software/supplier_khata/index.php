<?php
$pageTitle = 'سپلائر ڈائریکٹری و کھاتہ (Supplier Directory & Khata)';
$activeMenu = 'supplier_khata';
require_once __DIR__ . '/../backend/header.php';

$successMsg = '';
$errorMsg = '';

// Handle Actions (Add, Edit, Delete, Pay, Add Bill, Return)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    // 1. Add Supplier
    if ($action === 'add_supplier') {
        $name = trim($_POST['name'] ?? '');
        $company = trim($_POST['company_or_market'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        $city = trim($_POST['city'] ?? '');
        $address = trim($_POST['address'] ?? '');
        $openingBalance = floatval($_POST['opening_balance'] ?? 0);
        $notes = trim($_POST['notes'] ?? '');

        if (!empty($name)) {
            try {
                $id = 'sup-' . uniqid();
                $createdAt = time();
                $stmt = $pdo->prepare("INSERT INTO suppliers (id, name, company_or_market, phone, city, address, balance, notes, created_at) VALUES (:id, :name, :comp, :phone, :city, :address, :bal, :notes, :created_at)");
                $stmt->execute([
                    ':id' => $id,
                    ':name' => $name,
                    ':comp' => $company,
                    ':phone' => $phone,
                    ':city' => $city,
                    ':address' => $address,
                    ':bal' => $openingBalance,
                    ':notes' => $notes,
                    ':created_at' => $createdAt
                ]);

                // If opening balance > 0, record initial transaction
                if ($openingBalance > 0) {
                    $trxId = 'strx-' . uniqid();
                    $pdo->prepare("INSERT INTO supplier_transactions (id, supplier_id, date, time, type, invoice_no, description, amount, balance_after, payment_method, notes, created_at) VALUES (:id, :sup_id, :date, :time, 'BILL', 'OPN-BAL', 'سابقہ پرانا بقایا کھاتہ (Opening Balance)', :amt, :bal_after, 'CREDIT', 'سسٹم میں کھاتہ کھولنے کے وقت پرانا بیلنس', :created_at)")->execute([
                        ':id' => $trxId,
                        ':sup_id' => $id,
                        ':date' => date('Y-m-d'),
                        ':time' => date('h:i A'),
                        ':amt' => $openingBalance,
                        ':bal_after' => $openingBalance,
                        ':created_at' => $createdAt
                    ]);
                }

                $successMsg = "نیا سپلائر \"{$name}\" کامیابی کے ساتھ کھاتہ میں شامل ہو گیا!";
                $_GET['selected'] = $id; // Auto select newly added supplier
            } catch (Exception $e) {
                $errorMsg = 'خرابی: ' . $e->getMessage();
            }
        } else {
            $errorMsg = 'براہ کرم سپلائر کا نام درج کریں۔';
        }
    }

    // 2. Edit Supplier Details
    elseif ($action === 'edit_supplier') {
        $id = trim($_POST['id'] ?? '');
        $name = trim($_POST['name'] ?? '');
        $company = trim($_POST['company_or_market'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        $city = trim($_POST['city'] ?? '');
        $address = trim($_POST['address'] ?? '');
        $notes = trim($_POST['notes'] ?? '');

        if (!empty($id) && !empty($name)) {
            try {
                $stmt = $pdo->prepare("UPDATE suppliers SET name = :name, company_or_market = :comp, phone = :phone, city = :city, address = :address, notes = :notes WHERE id = :id");
                $stmt->execute([
                    ':id' => $id,
                    ':name' => $name,
                    ':comp' => $company,
                    ':phone' => $phone,
                    ':city' => $city,
                    ':address' => $address,
                    ':notes' => $notes
                ]);
                $successMsg = "سپلائر \"{$name}\" کی تفصیلات کامیابی سے اپڈیٹ ہو گئیں۔";
                $_GET['selected'] = $id;
            } catch (Exception $e) {
                $errorMsg = 'خرابی: ' . $e->getMessage();
            }
        }
    }

    // 3. Delete Supplier
    elseif ($action === 'delete_supplier') {
        $id = trim($_POST['id'] ?? '');
        if (!empty($id)) {
            try {
                $pdo->beginTransaction();
                $pdo->prepare("DELETE FROM supplier_transactions WHERE supplier_id = :id")->execute([':id' => $id]);
                $pdo->prepare("DELETE FROM suppliers WHERE id = :id")->execute([':id' => $id]);
                $pdo->commit();
                $successMsg = 'سپلائر اور اس کا تمام ریکارڈ کامیابی سے حذف ہو گیا۔';
                unset($_GET['selected']);
            } catch (Exception $e) {
                $pdo->rollBack();
                $errorMsg = 'خرابی: ' . $e->getMessage();
            }
        }
    }

    // 4. Pay Supplier (ادائیگی درج کریں)
    elseif ($action === 'pay_supplier') {
        $id = trim($_POST['id'] ?? '');
        $payAmount = floatval($_POST['pay_amount'] ?? 0);
        $paymentMethod = trim($_POST['payment_method'] ?? 'CASH');
        $voucherNo = trim($_POST['voucher_no'] ?? '') ?: ('PAY-' . strtoupper(substr(uniqid(), -6)));
        $notes = trim($_POST['notes'] ?? 'سپلائر بل کی ادائیگی');
        $deductCash = isset($_POST['deduct_from_cash']) && $_POST['deduct_from_cash'] === '1';

        if (!empty($id) && $payAmount > 0) {
            try {
                $pdo->beginTransaction();

                // Fetch current supplier
                $supStmt = $pdo->prepare("SELECT * FROM suppliers WHERE id = :id FOR UPDATE");
                $supStmt->execute([':id' => $id]);
                $sup = $supStmt->fetch();

                if (!$sup) {
                    throw new Exception("سپلائر موجود نہیں ہے۔");
                }

                $newBalance = floatval($sup['balance']) - $payAmount;

                // Update supplier balance
                $pdo->prepare("UPDATE suppliers SET balance = :bal WHERE id = :id")->execute([
                    ':bal' => $newBalance,
                    ':id' => $id
                ]);

                // Record Supplier Ledger Transaction
                $nowDate = date('Y-m-d');
                $nowTime = date('h:i A');
                $createdAt = time();
                $trxId = 'strx-' . uniqid();

                $pdo->prepare("INSERT INTO supplier_transactions (id, supplier_id, date, time, type, invoice_no, description, amount, balance_after, payment_method, notes, created_at) VALUES (:id, :sup_id, :date, :time, 'PAYMENT', :inv, :desc, :amt, :bal_after, :method, :notes, :created_at)")->execute([
                    ':id' => $trxId,
                    ':sup_id' => $id,
                    ':date' => $nowDate,
                    ':time' => $nowTime,
                    ':inv' => $voucherNo,
                    ':desc' => "ادائیگی بنام: " . $sup['name'] . " (" . $paymentMethod . ")",
                    ':amt' => $payAmount,
                    ':bal_after' => $newBalance,
                    ':method' => $paymentMethod,
                    ':notes' => $notes,
                    ':created_at' => $createdAt
                ]);

                // Also record in general transactions as EXPENSE / Cash Out if requested
                if ($deductCash) {
                    $pdo->prepare("INSERT INTO transactions (id, type, customer_name, customer_phone, cash_amount, expense_amount, payment_method, trx_id, date, time, note, created_at) VALUES (:id, 'EXPENSE', :cname, :cphone, :camt, :eamt, :pmeth, :tid, :date, :time, :note, :created_at)")->execute([
                        ':id' => 'trx-sup-' . uniqid(),
                        ':cname' => $sup['name'],
                        ':cphone' => $sup['phone'] ?? '',
                        ':camt' => ($paymentMethod === 'CASH' ? $payAmount : 0),
                        ':eamt' => $payAmount,
                        ':pmeth' => $paymentMethod,
                        ':tid' => $voucherNo,
                        ':date' => $nowDate,
                        ':time' => $nowTime,
                        ':note' => "سپلائر کھاتہ ادائیگی - " . $sup['name'] . " (" . $notes . ")",
                        ':created_at' => $createdAt
                    ]);
                }

                $pdo->commit();
                $successMsg = "سپلائر \"{$sup['name']}\" کو Rs. " . number_format($payAmount) . " کی ادائیگی کامیابی سے درج ہو گئی!";
                $_GET['selected'] = $id;
            } catch (Exception $e) {
                $pdo->rollBack();
                $errorMsg = 'خرابی: ' . $e->getMessage();
            }
        } else {
            $errorMsg = 'براہ کرم درست رقم درج کریں۔';
        }
    }

    // 5. Add New Purchase Bill (نیا بل درج کریں)
    elseif ($action === 'add_bill') {
        $id = trim($_POST['id'] ?? '');
        $billAmount = floatval($_POST['bill_amount'] ?? 0);
        $paidNow = floatval($_POST['paid_now'] ?? 0);
        $invoiceNo = trim($_POST['invoice_no'] ?? '') ?: ('INV-' . strtoupper(substr(uniqid(), -6)));
        $billDescription = trim($_POST['description'] ?? 'اسٹاک مال خریداری');
        $paymentMethod = trim($_POST['payment_method'] ?? 'CASH');
        $billDate = trim($_POST['bill_date'] ?? date('Y-m-d'));

        if (!empty($id) && $billAmount > 0) {
            try {
                $pdo->beginTransaction();

                $supStmt = $pdo->prepare("SELECT * FROM suppliers WHERE id = :id FOR UPDATE");
                $supStmt->execute([':id' => $id]);
                $sup = $supStmt->fetch();

                if (!$sup) {
                    throw new Exception("سپلائر موجود نہیں ہے۔");
                }

                // Balance calculation:
                // Previous balance + billAmount - paidNow
                $balanceAfterBill = floatval($sup['balance']) + $billAmount;
                $finalBalance = $balanceAfterBill - $paidNow;

                $nowTime = date('h:i A');
                $createdAt = time();

                // 1) Record BILL transaction
                $pdo->prepare("INSERT INTO supplier_transactions (id, supplier_id, date, time, type, invoice_no, description, amount, balance_after, payment_method, notes, created_at) VALUES (:id, :sup_id, :date, :time, 'BILL', :inv, :desc, :amt, :bal_after, 'CREDIT', :notes, :created_at)")->execute([
                    ':id' => 'strx-b-' . uniqid(),
                    ':sup_id' => $id,
                    ':date' => $billDate,
                    ':time' => $nowTime,
                    ':inv' => $invoiceNo,
                    ':desc' => $billDescription,
                    ':amt' => $billAmount,
                    ':bal_after' => $balanceAfterBill,
                    ':notes' => 'نیا خریداری بل درج ہوا',
                    ':created_at' => $createdAt
                ]);

                // 2) If cash/part paid immediately at bill time, record PAYMENT transaction
                if ($paidNow > 0) {
                    $pdo->prepare("INSERT INTO supplier_transactions (id, supplier_id, date, time, type, invoice_no, description, amount, balance_after, payment_method, notes, created_at) VALUES (:id, :sup_id, :date, :time, 'PAYMENT', :inv, :desc, :amt, :bal_after, :method, :notes, :created_at)")->execute([
                        ':id' => 'strx-p-' . uniqid(),
                        ':sup_id' => $id,
                        ':date' => $billDate,
                        ':time' => $nowTime,
                        ':inv' => $invoiceNo . '-PAY',
                        ':desc' => "بل کے ساتھ فوری ادائیگی (" . $paymentMethod . ")",
                        ':amt' => $paidNow,
                        ':bal_after' => $finalBalance,
                        ':method' => $paymentMethod,
                        ':notes' => "بل #{$invoiceNo} کی موقع پر ادا کردہ رقم",
                        ':created_at' => $createdAt + 1
                    ]);

                    // Add to cash register transactions
                    $pdo->prepare("INSERT INTO transactions (id, type, customer_name, customer_phone, cash_amount, expense_amount, payment_method, trx_id, date, time, note, created_at) VALUES (:id, 'EXPENSE', :cname, :cphone, :camt, :eamt, :pmeth, :tid, :date, :time, :note, :created_at)")->execute([
                        ':id' => 'trx-sup-' . uniqid(),
                        ':cname' => $sup['name'],
                        ':cphone' => $sup['phone'] ?? '',
                        ':camt' => ($paymentMethod === 'CASH' ? $paidNow : 0),
                        ':eamt' => $paidNow,
                        ':pmeth' => $paymentMethod,
                        ':tid' => $invoiceNo,
                        ':date' => $billDate,
                        ':time' => $nowTime,
                        ':note' => "سپلائر بل خریداری کی فوری ادائیگی - " . $sup['name'],
                        ':created_at' => $createdAt
                    ]);
                }

                // Update supplier final balance
                $pdo->prepare("UPDATE suppliers SET balance = :bal WHERE id = :id")->execute([
                    ':bal' => $finalBalance,
                    ':id' => $id
                ]);

                $pdo->commit();
                $successMsg = "بل #{$invoiceNo} (مبلغ Rs. " . number_format($billAmount) . ") کامیابی سے شامل ہو گیا!";
                $_GET['selected'] = $id;
            } catch (Exception $e) {
                $pdo->rollBack();
                $errorMsg = 'خرابی: ' . $e->getMessage();
            }
        }
    }

    // 6. Record Return / Debit Adjustment (مال واپسی یا رعایت)
    elseif ($action === 'add_return') {
        $id = trim($_POST['id'] ?? '');
        $returnAmount = floatval($_POST['return_amount'] ?? 0);
        $returnType = trim($_POST['return_type'] ?? 'RETURN'); // RETURN or DISCOUNT
        $slipNo = trim($_POST['slip_no'] ?? '') ?: ('RET-' . strtoupper(substr(uniqid(), -6)));
        $description = trim($_POST['description'] ?? 'خراب یا ریٹرن مال کی واپسی');

        if (!empty($id) && $returnAmount > 0) {
            try {
                $pdo->beginTransaction();

                $supStmt = $pdo->prepare("SELECT * FROM suppliers WHERE id = :id FOR UPDATE");
                $supStmt->execute([':id' => $id]);
                $sup = $supStmt->fetch();

                if (!$sup) {
                    throw new Exception("سپلائر موجود نہیں ہے۔");
                }

                $newBalance = floatval($sup['balance']) - $returnAmount;
                $nowDate = date('Y-m-d');
                $nowTime = date('h:i A');
                $createdAt = time();

                $pdo->prepare("INSERT INTO supplier_transactions (id, supplier_id, date, time, type, invoice_no, description, amount, balance_after, payment_method, notes, created_at) VALUES (:id, :sup_id, :date, :time, :type, :inv, :desc, :amt, :bal_after, 'ADJUSTMENT', :notes, :created_at)")->execute([
                    ':id' => 'strx-r-' . uniqid(),
                    ':sup_id' => $id,
                    ':date' => $nowDate,
                    ':time' => $nowTime,
                    ':type' => $returnType,
                    ':inv' => $slipNo,
                    ':desc' => $description,
                    ':amt' => $returnAmount,
                    ':bal_after' => $newBalance,
                    ':notes' => ($returnType === 'DISCOUNT' ? 'خصوصی رعایت یا ڈسکاؤنٹ کٹوتی' : 'سپلائر کو مال واپسی / کلیم کٹوتی'),
                    ':created_at' => $createdAt
                ]);

                $pdo->prepare("UPDATE suppliers SET balance = :bal WHERE id = :id")->execute([
                    ':bal' => $newBalance,
                    ':id' => $id
                ]);

                $pdo->commit();
                $successMsg = ($returnType === 'DISCOUNT' ? "ڈسکاؤنٹ کٹوتی" : "مال واپسی") . " کی رقم Rs. " . number_format($returnAmount) . " کھاتے سے کم کر دی گئی!";
                $_GET['selected'] = $id;
            } catch (Exception $e) {
                $pdo->rollBack();
                $errorMsg = 'خرابی: ' . $e->getMessage();
            }
        }
    }
}

// Fetch all suppliers
$search = trim($_GET['search'] ?? '');
$statusFilter = $_GET['status'] ?? 'ALL'; // ALL, DUES, CLEAR, ADVANCE

$query = "SELECT * FROM suppliers WHERE 1=1";
$params = [];

if (!empty($search)) {
    $query .= " AND (name LIKE :s1 OR company_or_market LIKE :s2 OR phone LIKE :s3 OR city LIKE :s4)";
    $params[':s1'] = "%$search%";
    $params[':s2'] = "%$search%";
    $params[':s3'] = "%$search%";
    $params[':s4'] = "%$search%";
}

if ($statusFilter === 'DUES') {
    $query .= " AND balance > 0";
} elseif ($statusFilter === 'CLEAR') {
    $query .= " AND balance = 0";
} elseif ($statusFilter === 'ADVANCE') {
    $query .= " AND balance < 0";
}

$query .= " ORDER BY balance DESC, name ASC";
$stmt = $pdo->prepare($query);
$stmt->execute($params);
$suppliers = $stmt->fetchAll();

// KPIs calculation
$totalSuppliers = count($suppliers);
$totalDues = 0;
$totalClear = 0;
$totalAdvance = 0;

$allSupStmt = $pdo->query("SELECT balance FROM suppliers");
while ($r = $allSupStmt->fetch()) {
    $b = floatval($r['balance']);
    if ($b > 0) $totalDues += $b;
    elseif ($b < 0) $totalAdvance += abs($b);
    else $totalClear++;
}

// Total paid this month
$monthStart = date('Y-m-01');
$paidMonthStmt = $pdo->prepare("SELECT COALESCE(SUM(amount), 0) FROM supplier_transactions WHERE type = 'PAYMENT' AND date >= :mstart");
$paidMonthStmt->execute([':mstart' => $monthStart]);
$totalPaidThisMonth = floatval($paidMonthStmt->fetchColumn());

// Total purchases recorded
$totalPurchasesStmt = $pdo->query("SELECT COALESCE(SUM(amount), 0) FROM supplier_transactions WHERE type = 'BILL'");
$totalPurchasesOverall = floatval($totalPurchasesStmt->fetchColumn());

// Selected supplier for detail view
$selectedId = $_GET['selected'] ?? '';
if (empty($selectedId) && !empty($suppliers)) {
    $selectedId = $suppliers[0]['id'];
}

$selectedSupplier = null;
$supplierTransactions = [];
$supplierPurchasedStock = [];

if (!empty($selectedId)) {
    $sStmt = $pdo->prepare("SELECT * FROM suppliers WHERE id = :id");
    $sStmt->execute([':id' => $selectedId]);
    $selectedSupplier = $sStmt->fetch();

    if ($selectedSupplier) {
        // Fetch transactions for this supplier
        $tStmt = $pdo->prepare("SELECT * FROM supplier_transactions WHERE supplier_id = :id ORDER BY date DESC, created_at DESC");
        $tStmt->execute([':id' => $selectedId]);
        $supplierTransactions = $tStmt->fetchAll();

        // Fetch mobile purchases associated with this supplier name or phone
        $mStmt = $pdo->prepare("SELECT * FROM mobile_purchases WHERE seller_name LIKE :sname OR seller_phone = :sphone ORDER BY purchase_date DESC LIMIT 50");
        $mStmt->execute([
            ':sname' => '%' . $selectedSupplier['name'] . '%',
            ':sphone' => $selectedSupplier['phone'] ?? 'xyz'
        ]);
        $supplierPurchasedStock = $mStmt->fetchAll();
    }
}
?>

<!-- Notification Alerts -->
<?php if (!empty($successMsg)): ?>
    <div class="mb-5 p-4 rounded-2xl bg-emerald-500/10 border border-emerald-500/30 text-emerald-300 flex items-center justify-between gap-3 shadow-lg">
        <div class="flex items-center gap-3">
            <div class="w-8 h-8 rounded-xl bg-emerald-500/20 text-emerald-400 flex items-center justify-center shrink-0">
                <i data-lucide="check-circle" class="w-5 h-5"></i>
            </div>
            <span class="font-bold text-xs sm:text-sm"><?= htmlspecialchars($successMsg) ?></span>
        </div>
        <button onclick="this.parentElement.remove()" class="text-emerald-400 hover:text-white p-1">
            <i data-lucide="x" class="w-4 h-4"></i>
        </button>
    </div>
<?php endif; ?>

<?php if (!empty($errorMsg)): ?>
    <div class="mb-5 p-4 rounded-2xl bg-rose-500/10 border border-rose-500/30 text-rose-300 flex items-center justify-between gap-3 shadow-lg">
        <div class="flex items-center gap-3">
            <div class="w-8 h-8 rounded-xl bg-rose-500/20 text-rose-400 flex items-center justify-center shrink-0">
                <i data-lucide="alert-triangle" class="w-5 h-5"></i>
            </div>
            <span class="font-bold text-xs sm:text-sm"><?= htmlspecialchars($errorMsg) ?></span>
        </div>
        <button onclick="this.parentElement.remove()" class="text-rose-400 hover:text-white p-1">
            <i data-lucide="x" class="w-4 h-4"></i>
        </button>
    </div>
<?php endif; ?>

<!-- Top KPI Summary Cards -->
<div class="grid grid-cols-2 lg:grid-cols-4 gap-3 sm:gap-4 mb-6">
    <!-- Card 1: Total Dues -->
    <div class="bg-slate-900/90 border border-rose-500/30 rounded-2xl p-4 sm:p-5 shadow-lg relative overflow-hidden group">
        <div class="absolute -right-6 -bottom-6 w-24 h-24 bg-rose-500/10 rounded-full blur-xl group-hover:bg-rose-500/20 transition-all"></div>
        <div class="flex items-center justify-between mb-2">
            <span class="text-xs font-bold text-rose-400">کل واجب الادا (Payable Dues)</span>
            <div class="w-8 h-8 rounded-xl bg-rose-500/10 text-rose-400 flex items-center justify-center">
                <i data-lucide="arrow-up-right" class="w-4 h-4"></i>
            </div>
        </div>
        <div class="text-xl sm:text-2xl font-black font-mono text-white tracking-tight">
            Rs. <?= number_format($totalDues) ?>
        </div>
        <div class="text-[11px] text-slate-400 mt-1 flex items-center gap-1">
            <span class="text-rose-400 font-semibold">وینڈرز و ہول سیلرز</span> کو دکان نے ادا کرنا ہے
        </div>
    </div>

    <!-- Card 2: Paid This Month -->
    <div class="bg-slate-900/90 border border-emerald-500/30 rounded-2xl p-4 sm:p-5 shadow-lg relative overflow-hidden group">
        <div class="absolute -right-6 -bottom-6 w-24 h-24 bg-emerald-500/10 rounded-full blur-xl group-hover:bg-emerald-500/20 transition-all"></div>
        <div class="flex items-center justify-between mb-2">
            <span class="text-xs font-bold text-emerald-400">اس ماہ کی ادائیگیاں (Paid)</span>
            <div class="w-8 h-8 rounded-xl bg-emerald-500/10 text-emerald-400 flex items-center justify-center">
                <i data-lucide="check-check" class="w-4 h-4"></i>
            </div>
        </div>
        <div class="text-xl sm:text-2xl font-black font-mono text-emerald-400 tracking-tight">
            Rs. <?= number_format($totalPaidThisMonth) ?>
        </div>
        <div class="text-[11px] text-slate-400 mt-1 flex items-center gap-1">
            <?= date('M Y') ?> کے دوران ادا کردہ رقوم
        </div>
    </div>

    <!-- Card 3: Total Stock Purchases -->
    <div class="bg-slate-900/90 border border-cyan-500/30 rounded-2xl p-4 sm:p-5 shadow-lg relative overflow-hidden group">
        <div class="absolute -right-6 -bottom-6 w-24 h-24 bg-cyan-500/10 rounded-full blur-xl group-hover:bg-cyan-500/20 transition-all"></div>
        <div class="flex items-center justify-between mb-2">
            <span class="text-xs font-bold text-cyan-400">کل خریداری بلز (Purchases)</span>
            <div class="w-8 h-8 rounded-xl bg-cyan-500/10 text-cyan-400 flex items-center justify-center">
                <i data-lucide="shopping-bag" class="w-4 h-4"></i>
            </div>
        </div>
        <div class="text-xl sm:text-2xl font-black font-mono text-white tracking-tight">
            Rs. <?= number_format($totalPurchasesOverall) ?>
        </div>
        <div class="text-[11px] text-slate-400 mt-1 flex items-center gap-1">
            ریکارڈ شدہ تمام ہول سیل انوائسز
        </div>
    </div>

    <!-- Card 4: Total Suppliers -->
    <div class="bg-slate-900/90 border border-indigo-500/30 rounded-2xl p-4 sm:p-5 shadow-lg relative overflow-hidden group">
        <div class="absolute -right-6 -bottom-6 w-24 h-24 bg-indigo-500/10 rounded-full blur-xl group-hover:bg-indigo-500/20 transition-all"></div>
        <div class="flex items-center justify-between mb-2">
            <span class="text-xs font-bold text-indigo-400">کل وینڈرز و سپلائرز</span>
            <div class="w-8 h-8 rounded-xl bg-indigo-500/10 text-indigo-400 flex items-center justify-center">
                <i data-lucide="building-2" class="w-4 h-4"></i>
            </div>
        </div>
        <div class="text-xl sm:text-2xl font-black font-mono text-white tracking-tight">
            <?= count($suppliers) ?> <span class="text-xs text-slate-400 font-normal">رجسٹرڈ کھاتے</span>
        </div>
        <div class="text-[11px] text-slate-400 mt-1 flex items-center gap-1">
            بشمول حفیظ سینٹر، ہال روڈ و کراچی مارکیٹ
        </div>
    </div>
</div>

<!-- Action Bar & Filter Controls -->
<div class="bg-slate-900 border border-slate-800 rounded-2xl p-4 mb-6 shadow-sm flex flex-col md:flex-row items-stretch md:items-center justify-between gap-3">
    <!-- Search and Status Pills -->
    <form method="GET" class="flex flex-wrap items-center gap-2 flex-1">
        <div class="relative flex-1 min-w-[200px]">
            <i data-lucide="search" class="w-4 h-4 text-slate-400 absolute right-3 top-3"></i>
            <input type="text" name="search" value="<?= htmlspecialchars($search) ?>" placeholder="سپلائر نام، فون، مارکیٹ یا شہر سے تلاش کریں..." class="w-full pr-9 pl-4 py-2 bg-slate-950 border border-slate-700 text-white rounded-xl text-xs focus:border-emerald-500 focus:outline-none placeholder-slate-500">
        </div>

        <!-- Filter Select -->
        <select name="status" onchange="this.form.submit()" class="bg-slate-950 border border-slate-700 text-slate-300 text-xs py-2 px-3 rounded-xl focus:border-emerald-500 focus:outline-none">
            <option value="ALL" <?= $statusFilter === 'ALL' ? 'selected' : '' ?>>تمام سپلائرز (All)</option>
            <option value="DUES" <?= $statusFilter === 'DUES' ? 'selected' : '' ?>>صرف واجب الادا کھاتے (Dues > 0)</option>
            <option value="CLEAR" <?= $statusFilter === 'CLEAR' ? 'selected' : '' ?>>صاف / بے باق کھاتے (Balance = 0)</option>
            <option value="ADVANCE" <?= $statusFilter === 'ADVANCE' ? 'selected' : '' ?>>ایڈوانس کھاتے (Advance)</option>
        </select>

        <button type="submit" class="px-4 py-2 bg-slate-800 hover:bg-slate-700 text-white text-xs font-bold rounded-xl transition-all flex items-center gap-1.5">
            <i data-lucide="filter" class="w-3.5 h-3.5"></i>
            <span>فلٹر</span>
        </button>

        <?php if (!empty($search) || $statusFilter !== 'ALL'): ?>
            <a href="index.php" class="px-3 py-2 bg-slate-800/80 hover:bg-slate-700 text-slate-400 hover:text-white text-xs rounded-xl transition-all">
                ری سیٹ
            </a>
        <?php endif; ?>
    </form>

    <!-- Top Action Buttons -->
    <div class="flex items-center gap-2 shrink-0">
        <!-- Add New Supplier Button -->
        <button onclick="openNewSupplierModal()" class="px-4 py-2 bg-emerald-600 hover:bg-emerald-500 text-white font-bold text-xs rounded-xl shadow-lg shadow-emerald-950/40 transition-all flex items-center gap-1.5 cursor-pointer">
            <i data-lucide="user-plus" class="w-4 h-4"></i>
            <span>نیا سپلائر ایڈ کریں</span>
        </button>

        <!-- Export CSV Button -->
        <button onclick="exportSuppliersCSV()" class="px-3 py-2 bg-slate-800 hover:bg-slate-700 text-slate-300 hover:text-white text-xs font-bold rounded-xl transition-all flex items-center gap-1.5 cursor-pointer" title="ایکسل فائل ڈاؤنلوڈ کریں">
            <i data-lucide="file-spreadsheet" class="w-4 h-4 text-emerald-400"></i>
            <span class="hidden sm:inline">CSV ایکسل</span>
        </button>
    </div>
</div>

<!-- Main Split-Screen Workspace (Left: Directory List, Right: Active Khata Statement) -->
<div class="grid grid-cols-1 lg:grid-cols-12 gap-5 items-start">
    
    <!-- LEFT PANEL: Suppliers Directory List (4 Columns) -->
    <div class="lg:col-span-4 bg-slate-900 border border-slate-800 rounded-2xl p-4 shadow-lg space-y-3">
        <div class="flex items-center justify-between pb-3 border-b border-slate-800">
            <div class="flex items-center gap-2">
                <i data-lucide="users" class="w-4 h-4 text-emerald-400"></i>
                <h3 class="font-bold text-white text-sm">سپلائرز لسٹ</h3>
            </div>
            <span class="text-[11px] px-2 py-0.5 rounded-full bg-slate-800 text-slate-400 font-mono font-bold">
                <?= count($suppliers) ?> وینڈرز
            </span>
        </div>

        <div class="space-y-2.5 max-h-[620px] overflow-y-auto pr-1">
            <?php if (empty($suppliers)): ?>
                <div class="text-center py-12 text-slate-500 space-y-3">
                    <i data-lucide="user-x" class="w-10 h-10 mx-auto text-slate-600"></i>
                    <p class="text-xs">کوئی سپلائر نہیں ملا۔</p>
                    <button onclick="openNewSupplierModal()" class="px-3 py-1.5 bg-emerald-600/20 text-emerald-400 hover:bg-emerald-600/30 text-xs rounded-lg font-bold">
                        پہلا سپلائر شامل کریں
                    </button>
                </div>
            <?php else: ?>
                <?php foreach ($suppliers as $s): 
                    $isSelected = ($selectedSupplier && $selectedSupplier['id'] === $s['id']);
                    $bal = floatval($s['balance']);
                    $cleanPhone = preg_replace('/[^0-9]/', '', $s['phone'] ?? '');
                    if (str_starts_with($cleanPhone, '0')) {
                        $waPhone = '92' . substr($cleanPhone, 1);
                    } else {
                        $waPhone = $cleanPhone;
                    }
                ?>
                    <div class="rounded-xl border transition-all p-3.5 <?= $isSelected ? 'bg-slate-800/90 border-emerald-500 shadow-md ring-1 ring-emerald-500/30' : 'bg-slate-950/60 border-slate-800/80 hover:border-slate-700 hover:bg-slate-800/40' ?>">
                        <div class="flex items-start justify-between gap-2 mb-1.5">
                            <a href="?selected=<?= urlencode($s['id']) ?><?= !empty($search) ? '&search=' . urlencode($search) : '' ?><?= $statusFilter !== 'ALL' ? '&status=' . urlencode($statusFilter) : '' ?>" class="block flex-1 group">
                                <h4 class="font-bold text-sm text-white group-hover:text-emerald-400 transition-colors flex items-center gap-1.5">
                                    <span><?= htmlspecialchars($s['name']) ?></span>
                                    <?php if ($isSelected): ?>
                                        <span class="w-2 h-2 rounded-full bg-emerald-400"></span>
                                    <?php endif; ?>
                                </h4>
                                <p class="text-[11px] text-slate-400 flex items-center gap-1 mt-0.5">
                                    <i data-lucide="store" class="w-3 h-3 text-slate-500"></i>
                                    <span><?= htmlspecialchars($s['company_or_market'] ?: ($s['city'] ?: 'ہول سیل سپلائر')) ?></span>
                                </p>
                            </a>

                            <!-- Balance Badge -->
                            <div class="text-left">
                                <div class="text-xs font-black font-mono <?= $bal > 0 ? 'text-rose-400' : ($bal < 0 ? 'text-cyan-400' : 'text-emerald-400') ?>">
                                    Rs. <?= number_format($bal) ?>
                                </div>
                                <span class="text-[9px] font-bold uppercase tracking-wider px-1.5 py-0.5 rounded <?= $bal > 0 ? 'bg-rose-500/10 text-rose-300' : ($bal < 0 ? 'bg-cyan-500/10 text-cyan-300' : 'bg-emerald-500/10 text-emerald-300') ?>">
                                    <?= $bal > 0 ? 'واجب الادا' : ($bal < 0 ? 'ایڈوانس' : 'بے باق') ?>
                                </span>
                            </div>
                        </div>

                        <!-- Contact & Action Footer -->
                        <div class="flex items-center justify-between pt-2 mt-2 border-t border-slate-800/80 text-xs">
                            <div class="flex items-center gap-2">
                                <span class="font-mono text-[11px] text-slate-400 flex items-center gap-1">
                                    <i data-lucide="phone" class="w-3 h-3 text-slate-500"></i>
                                    <?= htmlspecialchars($s['phone'] ?: 'فون نہیں') ?>
                                </span>
                                <?php if (!empty($waPhone)): ?>
                                    <a href="https://wa.me/<?= $waPhone ?>?text=<?= urlencode("السلام علیکم جناب {$s['name']}، بلال موبائل شاپ کے کھاتہ کے مطابق بقایا تفصیلات برائے ملاحظہ۔") ?>" target="_blank" class="p-1 rounded bg-emerald-500/10 hover:bg-emerald-500/20 text-emerald-400" title="واٹس ایپ پر رابطہ کریں">
                                        <i data-lucide="message-circle" class="w-3.5 h-3.5"></i>
                                    </a>
                                <?php endif; ?>
                            </div>

                            <div class="flex items-center gap-1">
                                <button onclick="openPaySupplierModal('<?= htmlspecialchars(addslashes($s['id'])) ?>', '<?= htmlspecialchars(addslashes($s['name'])) ?>', <?= $bal ?>)" class="px-2.5 py-1 bg-emerald-600/20 hover:bg-emerald-600 text-emerald-300 hover:text-white rounded-lg text-[11px] font-bold transition-all flex items-center gap-1 cursor-pointer">
                                    <i data-lucide="credit-card" class="w-3 h-3"></i>
                                    <span>ادائیگی</span>
                                </button>
                                <a href="?selected=<?= urlencode($s['id']) ?>" class="px-2.5 py-1 bg-slate-800 hover:bg-slate-700 text-slate-300 hover:text-white rounded-lg text-[11px] font-bold transition-all">
                                    کھاتہ
                                </a>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>

    <!-- RIGHT PANEL: Selected Supplier Khata Statement & Ledger (8 Columns) -->
    <div class="lg:col-span-8 space-y-5">
        <?php if (!$selectedSupplier): ?>
            <div class="bg-slate-900 border border-slate-800 rounded-2xl p-12 text-center text-slate-500 space-y-4">
                <div class="w-16 h-16 rounded-2xl bg-slate-800 flex items-center justify-center mx-auto text-slate-400">
                    <i data-lucide="building-2" class="w-8 h-8"></i>
                </div>
                <h3 class="text-base font-bold text-white">کسی سپلائر کا انتخاب کریں</h3>
                <p class="text-xs max-w-sm mx-auto text-slate-400">
                    بائیں جانب دی گئی فہرست سے کسی بھی ہول سیلر پر کلک کریں تاکہ اس کا مکمل کھاتہ روزنامچہ، خریداریاں اور ادائیگیاں دیکھی جا سکیں۔
                </p>
                <button onclick="openNewSupplierModal()" class="px-4 py-2 bg-emerald-600 hover:bg-emerald-500 text-white font-bold text-xs rounded-xl shadow-lg transition-all inline-flex items-center gap-2">
                    <i data-lucide="plus" class="w-4 h-4"></i>
                    <span>نیا سپلائر درج کریں</span>
                </button>
            </div>
        <?php else: 
            $curBal = floatval($selectedSupplier['balance']);
            // Compute total purchases from transactions
            $supTotalPurchases = 0;
            $supTotalPaid = 0;
            foreach ($supplierTransactions as $stx) {
                if ($stx['type'] === 'BILL') $supTotalPurchases += floatval($stx['amount']);
                elseif ($stx['type'] === 'PAYMENT') $supTotalPaid += floatval($stx['amount']);
            }
        ?>
            <!-- Supplier Profile Card -->
            <div class="bg-slate-900 border border-slate-800 rounded-2xl p-4 sm:p-5 shadow-lg space-y-4">
                <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-3 pb-4 border-b border-slate-800">
                    <div class="flex items-start gap-3">
                        <div class="w-12 h-12 rounded-2xl bg-emerald-500/10 border border-emerald-500/30 text-emerald-400 flex items-center justify-center font-bold shrink-0 shadow-inner">
                            <i data-lucide="store" class="w-6 h-6"></i>
                        </div>
                        <div>
                            <div class="flex items-center gap-2 flex-wrap">
                                <h2 class="text-lg font-black text-white"><?= htmlspecialchars($selectedSupplier['name']) ?></h2>
                                <?php if (!empty($selectedSupplier['company_or_market'])): ?>
                                    <span class="text-xs px-2.5 py-0.5 rounded-full bg-emerald-500/10 text-emerald-400 border border-emerald-500/20 font-bold">
                                        <?= htmlspecialchars($selectedSupplier['company_or_market']) ?>
                                    </span>
                                <?php endif; ?>
                                <?php if (!empty($selectedSupplier['city'])): ?>
                                    <span class="text-xs px-2 py-0.5 rounded-full bg-slate-800 text-slate-300 font-bold">
                                        📍 <?= htmlspecialchars($selectedSupplier['city']) ?>
                                    </span>
                                <?php endif; ?>
                            </div>
                            <div class="flex items-center gap-3 text-xs text-slate-400 mt-1 flex-wrap">
                                <span class="font-mono flex items-center gap-1">
                                    <i data-lucide="phone" class="w-3.5 h-3.5 text-cyan-400"></i>
                                    <?= htmlspecialchars($selectedSupplier['phone'] ?: 'فون نہیں ہے') ?>
                                </span>
                                <?php if (!empty($selectedSupplier['address'])): ?>
                                    <span class="flex items-center gap-1 text-slate-400">
                                        <i data-lucide="map-pin" class="w-3.5 h-3.5 text-slate-500"></i>
                                        <?= htmlspecialchars($selectedSupplier['address']) ?>
                                    </span>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>

                    <!-- Top Action Buttons for this Supplier -->
                    <div class="flex items-center gap-2 flex-wrap self-end sm:self-center">
                        <button onclick="openPaySupplierModal('<?= htmlspecialchars(addslashes($selectedSupplier['id'])) ?>', '<?= htmlspecialchars(addslashes($selectedSupplier['name'])) ?>', <?= $curBal ?>)" class="px-3.5 py-2 bg-emerald-600 hover:bg-emerald-500 text-white rounded-xl text-xs font-bold transition-all shadow-md flex items-center gap-1.5 cursor-pointer">
                            <i data-lucide="credit-card" class="w-4 h-4"></i>
                            <span>رقم ادا کریں</span>
                        </button>
                        <button onclick="openAddBillModal('<?= htmlspecialchars(addslashes($selectedSupplier['id'])) ?>', '<?= htmlspecialchars(addslashes($selectedSupplier['name'])) ?>')" class="px-3.5 py-2 bg-cyan-600 hover:bg-cyan-500 text-white rounded-xl text-xs font-bold transition-all shadow-md flex items-center gap-1.5 cursor-pointer">
                            <i data-lucide="plus-circle" class="w-4 h-4"></i>
                            <span>نیا بل درج کریں</span>
                        </button>
                        <button onclick="openReturnModal('<?= htmlspecialchars(addslashes($selectedSupplier['id'])) ?>', '<?= htmlspecialchars(addslashes($selectedSupplier['name'])) ?>')" class="px-3 py-2 bg-slate-800 hover:bg-slate-700 text-slate-300 hover:text-white rounded-xl text-xs font-bold transition-all flex items-center gap-1.5 cursor-pointer" title="مال واپسی یا ڈسکاؤنٹ کٹوتی">
                            <i data-lucide="undo-2" class="w-3.5 h-3.5 text-amber-400"></i>
                            <span>مال واپسی</span>
                        </button>
                    </div>
                </div>

                <!-- 3 Metric Blocks -->
                <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                    <div class="p-3.5 rounded-xl bg-slate-950/70 border border-slate-800">
                        <span class="text-[11px] text-slate-400 block mb-0.5">کل خریدا گیا مال (Purchased)</span>
                        <span class="text-base font-black font-mono text-white">Rs. <?= number_format($supTotalPurchases) ?></span>
                    </div>
                    <div class="p-3.5 rounded-xl bg-slate-950/70 border border-slate-800">
                        <span class="text-[11px] text-slate-400 block mb-0.5">کل ادا شدہ رقم (Total Paid)</span>
                        <span class="text-base font-black font-mono text-emerald-400">Rs. <?= number_format($supTotalPaid) ?></span>
                    </div>
                    <div class="p-3.5 rounded-xl border <?= $curBal > 0 ? 'bg-rose-500/10 border-rose-500/30' : ($curBal < 0 ? 'bg-cyan-500/10 border-cyan-500/30' : 'bg-emerald-500/10 border-emerald-500/30') ?>">
                        <span class="text-[11px] font-bold block mb-0.5 <?= $curBal > 0 ? 'text-rose-400' : ($curBal < 0 ? 'text-cyan-400' : 'text-emerald-400') ?>">
                            موجودہ خالص بقایا (Net Balance)
                        </span>
                        <span class="text-lg font-black font-mono <?= $curBal > 0 ? 'text-rose-400' : ($curBal < 0 ? 'text-cyan-400' : 'text-emerald-400') ?>">
                            Rs. <?= number_format($curBal) ?>
                        </span>
                    </div>
                </div>

                <!-- Action Bar & Print & WhatsApp Links -->
                <div class="flex items-center justify-between gap-2 pt-2 border-t border-slate-800/80 text-xs">
                    <div class="flex items-center gap-2">
                        <button onclick="printSupplierStatement()" class="px-3 py-1.5 bg-slate-800 hover:bg-slate-700 text-slate-300 hover:text-white rounded-lg text-xs font-bold transition-all flex items-center gap-1.5 cursor-pointer">
                            <i data-lucide="printer" class="w-3.5 h-3.5 text-cyan-400"></i>
                            <span>لیجر اسٹیٹمنٹ پرنٹ کریں</span>
                        </button>
                        <button onclick="shareOnWhatsApp('<?= htmlspecialchars(addslashes($selectedSupplier['name'])) ?>', '<?= htmlspecialchars(addslashes($selectedSupplier['phone'])) ?>', <?= $curBal ?>, <?= $supTotalPurchases ?>, <?= $supTotalPaid ?>)" class="px-3 py-1.5 bg-emerald-500/10 hover:bg-emerald-500/20 text-emerald-400 rounded-lg text-xs font-bold transition-all flex items-center gap-1.5 cursor-pointer">
                            <i data-lucide="share-2" class="w-3.5 h-3.5"></i>
                            <span>واٹس ایپ بیانیہ</span>
                        </button>
                    </div>

                    <div class="flex items-center gap-2">
                        <button onclick="openEditSupplierModal(<?= htmlspecialchars(json_encode($selectedSupplier)) ?>)" class="p-1.5 bg-slate-800 hover:bg-slate-700 text-slate-300 hover:text-white rounded-lg transition-all" title="سپلائر معلومات تبدیل کریں">
                            <i data-lucide="edit-3" class="w-4 h-4"></i>
                        </button>
                        <form method="POST" onsubmit="return confirm('کیا آپ واقعی سپلائر <?= htmlspecialchars($selectedSupplier['name']) ?> اور اس کا کھاتہ حذف کرنا چاہتے ہیں؟')" class="inline">
                            <input type="hidden" name="action" value="delete_supplier">
                            <input type="hidden" name="id" value="<?= htmlspecialchars($selectedSupplier['id']) ?>">
                            <button type="submit" class="p-1.5 bg-rose-500/10 hover:bg-rose-500/20 text-rose-400 rounded-lg transition-all" title="سپلائر حذف کریں">
                                <i data-lucide="trash-2" class="w-4 h-4"></i>
                            </button>
                        </form>
                    </div>
                </div>
            </div>

            <!-- Complete Ledger Statement Table -->
            <div class="bg-slate-900 border border-slate-800 rounded-2xl overflow-hidden shadow-lg" id="printableKhataArea">
                <!-- Table Header -->
                <div class="p-4 bg-slate-950/80 border-b border-slate-800 flex items-center justify-between">
                    <div class="flex items-center gap-2">
                        <i data-lucide="book-open" class="w-4 h-4 text-emerald-400"></i>
                        <h3 class="font-bold text-white text-sm">روزنامچہ کھاتہ بیانیہ (Ledger Statement)</h3>
                    </div>
                    <span class="text-xs text-slate-400 font-mono">
                        <?= count($supplierTransactions) ?> اندراجات
                    </span>
                </div>

                <div class="overflow-x-auto max-h-[500px] overflow-y-auto">
                    <table class="w-full text-right text-xs">
                        <thead class="bg-slate-950 text-slate-400 border-b border-slate-800 sticky top-0 z-10">
                            <tr>
                                <th class="p-3">تاریخ و وقت</th>
                                <th class="p-3">انوائس / واؤچر #</th>
                                <th class="p-3">تفصیلات و مال کا نام</th>
                                <th class="p-3 text-center">نوعیت</th>
                                <th class="p-3 text-center">ذریعہ ادائیگی</th>
                                <th class="p-3 text-left">ادائیگی / ڈیبٹ (Rs)</th>
                                <th class="p-3 text-left">بل / کریڈٹ (Rs)</th>
                                <th class="p-3 text-left">بقایا (Balance)</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-800/60">
                            <?php if (empty($supplierTransactions)): ?>
                                <tr>
                                    <td colspan="8" class="text-center py-10 text-slate-500 space-y-2">
                                        <i data-lucide="file-text" class="w-8 h-8 mx-auto text-slate-600"></i>
                                        <p class="text-xs">ابھی تک اس سپلائر کے ساتھ کوئی لین دین یا بل درج نہیں ہے۔</p>
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($supplierTransactions as $trx): 
                                    $isPayment = ($trx['type'] === 'PAYMENT' || $trx['type'] === 'RETURN' || $trx['type'] === 'DISCOUNT');
                                    $isBill = ($trx['type'] === 'BILL');
                                ?>
                                    <tr class="hover:bg-slate-800/30 transition-colors">
                                        <td class="p-3 font-mono text-slate-300 whitespace-nowrap">
                                            <?= htmlspecialchars($trx['date']) ?>
                                            <span class="text-[10px] text-slate-500 block"><?= htmlspecialchars($trx['time']) ?></span>
                                        </td>
                                        <td class="p-3 font-mono font-bold text-cyan-400 whitespace-nowrap">
                                            <?= htmlspecialchars($trx['invoice_no'] ?: '—') ?>
                                        </td>
                                        <td class="p-3 text-slate-200 min-w-[200px]">
                                            <div class="font-bold text-white"><?= htmlspecialchars($trx['description']) ?></div>
                                            <?php if (!empty($trx['notes'])): ?>
                                                <span class="text-[11px] text-slate-400"><?= htmlspecialchars($trx['notes']) ?></span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="p-3 text-center whitespace-nowrap">
                                            <?php if ($trx['type'] === 'BILL'): ?>
                                                <span class="px-2 py-0.5 rounded-full bg-cyan-500/10 text-cyan-400 border border-cyan-500/20 text-[10px] font-bold">
                                                    📦 خریداری بل
                                                </span>
                                            <?php elseif ($trx['type'] === 'PAYMENT'): ?>
                                                <span class="px-2 py-0.5 rounded-full bg-emerald-500/10 text-emerald-400 border border-emerald-500/20 text-[10px] font-bold">
                                                    💳 رقم ادائیگی
                                                </span>
                                            <?php elseif ($trx['type'] === 'RETURN'): ?>
                                                <span class="px-2 py-0.5 rounded-full bg-amber-500/10 text-amber-400 border border-amber-500/20 text-[10px] font-bold">
                                                    ↩️ مال واپسی
                                                </span>
                                            <?php else: ?>
                                                <span class="px-2 py-0.5 rounded-full bg-purple-500/10 text-purple-400 border border-purple-500/20 text-[10px] font-bold">
                                                    🏷️ رعایت
                                                </span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="p-3 text-center font-mono text-[11px] text-slate-400 whitespace-nowrap">
                                            <?= htmlspecialchars($trx['payment_method']) ?>
                                        </td>
                                        <!-- Debit / Payment (Paid out) -->
                                        <td class="p-3 text-left font-mono font-bold <?= $isPayment ? 'text-emerald-400' : 'text-slate-600' ?>">
                                            <?= $isPayment ? 'Rs. ' . number_format($trx['amount']) : '—' ?>
                                        </td>
                                        <!-- Credit / Bill (Added to Dues) -->
                                        <td class="p-3 text-left font-mono font-bold <?= $isBill ? 'text-rose-400' : 'text-slate-600' ?>">
                                            <?= $isBill ? 'Rs. ' . number_format($trx['amount']) : '—' ?>
                                        </td>
                                        <!-- Running Balance -->
                                        <td class="p-3 text-left font-mono font-black text-white whitespace-nowrap">
                                            Rs. <?= number_format($trx['balance_after']) ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Mobile Purchase Stock Associated with this Supplier -->
            <?php if (!empty($supplierPurchasedStock)): ?>
                <div class="bg-slate-900 border border-slate-800 rounded-2xl overflow-hidden shadow-lg p-4 space-y-3">
                    <div class="flex items-center justify-between pb-2 border-b border-slate-800">
                        <div class="flex items-center gap-2">
                            <i data-lucide="smartphone" class="w-4 h-4 text-cyan-400"></i>
                            <h3 class="font-bold text-white text-sm">اس سپلائر سے خریدے گئے موبائل فونز (Stock Tracking)</h3>
                        </div>
                        <span class="text-xs text-slate-400 font-mono"><?= count($supplierPurchasedStock) ?> فونز</span>
                    </div>

                    <div class="overflow-x-auto">
                        <table class="w-full text-right text-xs">
                            <thead class="bg-slate-950 text-slate-400">
                                <tr>
                                    <th class="p-2.5">تاریخ</th>
                                    <th class="p-2.5">موبائل ماڈل</th>
                                    <th class="p-2.5">IMEI 1</th>
                                    <th class="p-2.5">کنڈیشن</th>
                                    <th class="p-2.5 text-left">خرید قیمت</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-800">
                                <?php foreach ($supplierPurchasedStock as $mp): ?>
                                    <tr class="hover:bg-slate-800/30">
                                        <td class="p-2.5 font-mono text-slate-400"><?= htmlspecialchars($mp['purchase_date']) ?></td>
                                        <td class="p-2.5 font-bold text-white"><?= htmlspecialchars($mp['brand_or_model']) ?></td>
                                        <td class="p-2.5 font-mono text-cyan-400"><?= htmlspecialchars($mp['imei1']) ?></td>
                                        <td class="p-2.5">
                                            <span class="px-2 py-0.5 rounded text-[10px] font-bold <?= $mp['condition_status'] === 'NEW' ? 'bg-emerald-500/10 text-emerald-400' : 'bg-blue-500/10 text-blue-400' ?>">
                                                <?= htmlspecialchars($mp['condition_status']) ?>
                                            </span>
                                        </td>
                                        <td class="p-2.5 text-left font-mono font-bold text-emerald-400">
                                            Rs. <?= number_format($mp['purchase_price']) ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            <?php endif; ?>

        <?php endif; ?>
    </div>
</div>

<!-- ==================== MODALS ==================== -->

<!-- 1. Modal: Add New Supplier -->
<div id="newSupplierModal" class="hidden fixed inset-0 z-50 bg-black/80 backdrop-blur-sm flex items-center justify-center p-4 overflow-y-auto">
    <div class="bg-slate-900 border border-slate-800 rounded-3xl p-6 max-w-lg w-full shadow-2xl space-y-4">
        <div class="flex items-center justify-between pb-3 border-b border-slate-800">
            <div class="flex items-center gap-2">
                <div class="w-8 h-8 rounded-xl bg-emerald-500/10 text-emerald-400 flex items-center justify-center">
                    <i data-lucide="user-plus" class="w-4 h-4"></i>
                </div>
                <h3 class="font-bold text-white text-base">نیا ہول سیل سپلائر شامل کریں</h3>
            </div>
            <button onclick="closeNewSupplierModal()" class="p-1 text-slate-400 hover:text-white rounded-lg">
                <i data-lucide="x" class="w-5 h-5"></i>
            </button>
        </div>

        <form method="POST" class="space-y-3.5">
            <input type="hidden" name="action" value="add_supplier">

            <div>
                <label class="text-xs font-bold text-slate-400 block mb-1">سپلائر یا دکان کا نام *</label>
                <input type="text" name="name" required placeholder="مثلاً: المدینہ موبائل ہول سیلرز" class="w-full bg-slate-950 border border-slate-700 text-white px-3.5 py-2.5 rounded-xl text-xs focus:border-emerald-500 focus:outline-none placeholder-slate-600">
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                <div>
                    <label class="text-xs font-bold text-slate-400 block mb-1">کمپنی یا مارکیٹ</label>
                    <input type="text" name="company_or_market" placeholder="حفیظ سینٹر / ہال روڈ لاہور" class="w-full bg-slate-950 border border-slate-700 text-white px-3.5 py-2.5 rounded-xl text-xs focus:border-emerald-500 focus:outline-none placeholder-slate-600">
                </div>
                <div>
                    <label class="text-xs font-bold text-slate-400 block mb-1">شہر (City)</label>
                    <input type="text" name="city" placeholder="لاہور / کراچی / راولپنڈی" class="w-full bg-slate-950 border border-slate-700 text-white px-3.5 py-2.5 rounded-xl text-xs focus:border-emerald-500 focus:outline-none placeholder-slate-600">
                </div>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                <div>
                    <label class="text-xs font-bold text-slate-400 block mb-1">رابطہ فون / واٹس ایپ نمبر *</label>
                    <input type="text" name="phone" required placeholder="0300-1234567" class="w-full bg-slate-950 border border-slate-700 text-white px-3.5 py-2.5 rounded-xl text-xs focus:border-emerald-500 focus:outline-none placeholder-slate-600 font-mono">
                </div>
                <div>
                    <label class="text-xs font-bold text-slate-400 block mb-1">سابقہ پرانا بقایا (Opening Balance)</label>
                    <input type="number" name="opening_balance" value="0" min="0" step="any" placeholder="0" class="w-full bg-slate-950 border border-slate-700 text-white px-3.5 py-2.5 rounded-xl text-xs focus:border-emerald-500 focus:outline-none placeholder-slate-600 font-mono">
                </div>
            </div>

            <div>
                <label class="text-xs font-bold text-slate-400 block mb-1">دکان یا گودام کا پتہ (Address)</label>
                <input type="text" name="address" placeholder="شاپ نمبر 14، بیسمنٹ..." class="w-full bg-slate-950 border border-slate-700 text-white px-3.5 py-2.5 rounded-xl text-xs focus:border-emerald-500 focus:outline-none placeholder-slate-600">
            </div>

            <div>
                <label class="text-xs font-bold text-slate-400 block mb-1">خصوصی نوٹس یا پروڈکٹ کیٹگری</label>
                <input type="text" name="notes" placeholder="سام سنگ باکس پیک اور اصل فاسٹ چارجرز ڈیلر..." class="w-full bg-slate-950 border border-slate-700 text-white px-3.5 py-2.5 rounded-xl text-xs focus:border-emerald-500 focus:outline-none placeholder-slate-600">
            </div>

            <div class="pt-3 flex items-center justify-end gap-2 border-t border-slate-800">
                <button type="button" onclick="closeNewSupplierModal()" class="px-4 py-2.5 bg-slate-800 hover:bg-slate-700 text-slate-300 rounded-xl text-xs font-bold transition-all">
                    منسوخ کریں
                </button>
                <button type="submit" class="px-5 py-2.5 bg-emerald-600 hover:bg-emerald-500 text-white rounded-xl text-xs font-bold shadow-lg shadow-emerald-950/40 transition-all">
                    سپلائر محفوظ کریں
                </button>
            </div>
        </form>
    </div>
</div>

<!-- 2. Modal: Pay Supplier (ادائیگی کریں) -->
<div id="paySupplierModal" class="hidden fixed inset-0 z-50 bg-black/80 backdrop-blur-sm flex items-center justify-center p-4 overflow-y-auto">
    <div class="bg-slate-900 border border-slate-800 rounded-3xl p-6 max-w-md w-full shadow-2xl space-y-4">
        <div class="flex items-center justify-between pb-3 border-b border-slate-800">
            <div class="flex items-center gap-2">
                <div class="w-8 h-8 rounded-xl bg-emerald-500/10 text-emerald-400 flex items-center justify-center">
                    <i data-lucide="credit-card" class="w-4 h-4"></i>
                </div>
                <h3 class="font-bold text-white text-base">سپلائر کو رقم ادا کریں</h3>
            </div>
            <button onclick="closePaySupplierModal()" class="p-1 text-slate-400 hover:text-white rounded-lg">
                <i data-lucide="x" class="w-5 h-5"></i>
            </button>
        </div>

        <form method="POST" class="space-y-3.5">
            <input type="hidden" name="action" value="pay_supplier">
            <input type="hidden" name="id" id="paySupplierId">

            <div class="p-3 rounded-xl bg-slate-950 border border-slate-800">
                <span class="text-[11px] text-slate-400 block">سپلائر کا نام</span>
                <span class="text-sm font-bold text-white block mt-0.5" id="paySupplierName">—</span>
                <div class="flex items-center justify-between mt-2 pt-2 border-t border-slate-800/80 text-xs">
                    <span class="text-slate-400">موجودہ واجب الادا بقایا:</span>
                    <span class="font-black font-mono text-rose-400" id="paySupplierBalance">Rs. 0</span>
                </div>
            </div>

            <div>
                <label class="text-xs font-bold text-slate-400 block mb-1">ادائیگی کی رقم (Rs) *</label>
                <input type="number" name="pay_amount" id="payAmountInput" required min="1" step="any" placeholder="ادا کی جانے والی رقم" class="w-full bg-slate-950 border border-emerald-500/60 text-emerald-400 font-mono font-bold text-base px-3.5 py-2.5 rounded-xl focus:border-emerald-500 focus:outline-none">
            </div>

            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="text-xs font-bold text-slate-400 block mb-1">ادائیگی کا ذریعہ *</label>
                    <select name="payment_method" class="w-full bg-slate-950 border border-slate-700 text-white px-3 py-2.5 rounded-xl text-xs focus:border-emerald-500 focus:outline-none">
                        <option value="CASH">💵 کیش دراز (Cash)</option>
                        <option value="BANK">🏦 بینک ٹرانسفر (Bank)</option>
                        <option value="EASYPAISA">📱 ایزی پیسہ (EasyPaisa)</option>
                        <option value="JAZZCASH">📱 جاز کیش (JazzCash)</option>
                        <option value="CHEQUE">📝 چیک (Cheque)</option>
                    </select>
                </div>
                <div>
                    <label class="text-xs font-bold text-slate-400 block mb-1">واؤچر / رسید نمبر</label>
                    <input type="text" name="voucher_no" placeholder="VOU-0012" class="w-full bg-slate-950 border border-slate-700 text-white px-3 py-2.5 rounded-xl text-xs focus:border-emerald-500 focus:outline-none font-mono">
                </div>
            </div>

            <div>
                <label class="text-xs font-bold text-slate-400 block mb-1">تفصیل / ریمارکس</label>
                <input type="text" name="notes" value="سپلائر بل کی ادائیگی" class="w-full bg-slate-950 border border-slate-700 text-white px-3.5 py-2.5 rounded-xl text-xs focus:border-emerald-500 focus:outline-none">
            </div>

            <div class="p-3 rounded-xl bg-slate-950/60 border border-slate-800 flex items-center gap-2">
                <input type="checkbox" name="deduct_from_cash" id="deductCashCheck" value="1" checked class="rounded bg-slate-900 border-slate-700 text-emerald-500 focus:ring-0">
                <label for="deductCashCheck" class="text-xs text-slate-300 cursor-pointer">
                    دکان کے روزانہ کیش رجسٹر اور اخراجات کھاتہ میں بھی درج کریں
                </label>
            </div>

            <div class="pt-3 flex items-center justify-end gap-2 border-t border-slate-800">
                <button type="button" onclick="closePaySupplierModal()" class="px-4 py-2.5 bg-slate-800 hover:bg-slate-700 text-slate-300 rounded-xl text-xs font-bold transition-all">
                    منسوخ
                </button>
                <button type="submit" class="px-5 py-2.5 bg-emerald-600 hover:bg-emerald-500 text-white rounded-xl text-xs font-bold shadow-lg shadow-emerald-950/40 transition-all">
                    ادائیگی تصدیق کریں
                </button>
            </div>
        </form>
    </div>
</div>

<!-- 3. Modal: Add Purchase Bill (نیا بل شامل کریں) -->
<div id="addBillModal" class="hidden fixed inset-0 z-50 bg-black/80 backdrop-blur-sm flex items-center justify-center p-4 overflow-y-auto">
    <div class="bg-slate-900 border border-slate-800 rounded-3xl p-6 max-w-md w-full shadow-2xl space-y-4">
        <div class="flex items-center justify-between pb-3 border-b border-slate-800">
            <div class="flex items-center gap-2">
                <div class="w-8 h-8 rounded-xl bg-cyan-500/10 text-cyan-400 flex items-center justify-center">
                    <i data-lucide="plus-circle" class="w-4 h-4"></i>
                </div>
                <h3 class="font-bold text-white text-base">نیا خریداری بل درج کریں</h3>
            </div>
            <button onclick="closeAddBillModal()" class="p-1 text-slate-400 hover:text-white rounded-lg">
                <i data-lucide="x" class="w-5 h-5"></i>
            </button>
        </div>

        <form method="POST" class="space-y-3.5">
            <input type="hidden" name="action" value="add_bill">
            <input type="hidden" name="id" id="addBillSupplierId">

            <div class="p-3 rounded-xl bg-slate-950 border border-slate-800">
                <span class="text-[11px] text-slate-400 block">سپلائر نام</span>
                <span class="text-sm font-bold text-white block mt-0.5" id="addBillSupplierName">—</span>
            </div>

            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="text-xs font-bold text-slate-400 block mb-1">بل / انوائس نمبر</label>
                    <input type="text" name="invoice_no" placeholder="INV-2024-01" class="w-full bg-slate-950 border border-slate-700 text-white px-3 py-2.5 rounded-xl text-xs focus:border-cyan-500 focus:outline-none font-mono">
                </div>
                <div>
                    <label class="text-xs font-bold text-slate-400 block mb-1">بل کی تاریخ</label>
                    <input type="date" name="bill_date" value="<?= date('Y-m-d') ?>" class="w-full bg-slate-950 border border-slate-700 text-white px-3 py-2.5 rounded-xl text-xs focus:border-cyan-500 focus:outline-none font-mono">
                </div>
            </div>

            <div>
                <label class="text-xs font-bold text-slate-400 block mb-1">کل بل کی رقم (Rs) *</label>
                <input type="number" name="bill_amount" id="billTotalInput" required min="1" step="any" placeholder="بل کی رقم" class="w-full bg-slate-950 border border-cyan-500/60 text-cyan-400 font-mono font-bold text-base px-3.5 py-2.5 rounded-xl focus:border-cyan-500 focus:outline-none">
            </div>

            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="text-xs font-bold text-slate-400 block mb-1">موقع پر ادا کردہ رقم (Paid)</label>
                    <input type="number" name="paid_now" value="0" min="0" step="any" placeholder="0" class="w-full bg-slate-950 border border-slate-700 text-emerald-400 font-mono font-bold px-3 py-2.5 rounded-xl text-xs focus:border-emerald-500 focus:outline-none">
                </div>
                <div>
                    <label class="text-xs font-bold text-slate-400 block mb-1">ادائیگی ذریعہ</label>
                    <select name="payment_method" class="w-full bg-slate-950 border border-slate-700 text-white px-3 py-2.5 rounded-xl text-xs focus:border-cyan-500 focus:outline-none">
                        <option value="CASH">نقد کیش</option>
                        <option value="BANK">بینک ٹرانسفر</option>
                        <option value="EASYPAISA">ایزی پیسہ</option>
                    </select>
                </div>
            </div>

            <div>
                <label class="text-xs font-bold text-slate-400 block mb-1">تفصیل یا خریدی گئی آئٹمز</label>
                <textarea name="description" rows="2" placeholder="سام سنگ A15 (2 عدد)، 25W فاسٹ چارجرز (10 عدد)..." class="w-full bg-slate-950 border border-slate-700 text-white px-3.5 py-2 rounded-xl text-xs focus:border-cyan-500 focus:outline-none placeholder-slate-600"></textarea>
            </div>

            <div class="pt-3 flex items-center justify-end gap-2 border-t border-slate-800">
                <button type="button" onclick="closeAddBillModal()" class="px-4 py-2.5 bg-slate-800 hover:bg-slate-700 text-slate-300 rounded-xl text-xs font-bold transition-all">
                    منسوخ
                </button>
                <button type="submit" class="px-5 py-2.5 bg-cyan-600 hover:bg-cyan-500 text-white rounded-xl text-xs font-bold shadow-lg shadow-cyan-950/40 transition-all">
                    بل کھاتے میں شامل کریں
                </button>
            </div>
        </form>
    </div>
</div>

<!-- 4. Modal: Return / Debit Note (مال واپسی یا رعایت) -->
<div id="returnModal" class="hidden fixed inset-0 z-50 bg-black/80 backdrop-blur-sm flex items-center justify-center p-4 overflow-y-auto">
    <div class="bg-slate-900 border border-slate-800 rounded-3xl p-6 max-w-md w-full shadow-2xl space-y-4">
        <div class="flex items-center justify-between pb-3 border-b border-slate-800">
            <div class="flex items-center gap-2">
                <div class="w-8 h-8 rounded-xl bg-amber-500/10 text-amber-400 flex items-center justify-center">
                    <i data-lucide="undo-2" class="w-4 h-4"></i>
                </div>
                <h3 class="font-bold text-white text-base">مال واپسی یا ڈسکاؤنٹ درج کریں</h3>
            </div>
            <button onclick="closeReturnModal()" class="p-1 text-slate-400 hover:text-white rounded-lg">
                <i data-lucide="x" class="w-5 h-5"></i>
            </button>
        </div>

        <form method="POST" class="space-y-3.5">
            <input type="hidden" name="action" value="add_return">
            <input type="hidden" name="id" id="returnSupplierId">

            <div class="p-3 rounded-xl bg-slate-950 border border-slate-800">
                <span class="text-[11px] text-slate-400 block">سپلائر کا نام</span>
                <span class="text-sm font-bold text-white block mt-0.5" id="returnSupplierName">—</span>
            </div>

            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="text-xs font-bold text-slate-400 block mb-1">اندراج کی نوعیت</label>
                    <select name="return_type" class="w-full bg-slate-950 border border-slate-700 text-white px-3 py-2.5 rounded-xl text-xs focus:border-amber-500 focus:outline-none">
                        <option value="RETURN">↩️ مال واپسی (Stock Return)</option>
                        <option value="DISCOUNT">🏷️ رعایت / کٹوتی (Discount)</option>
                    </select>
                </div>
                <div>
                    <label class="text-xs font-bold text-slate-400 block mb-1">کٹوتی کی رقم (Rs) *</label>
                    <input type="number" name="return_amount" required min="1" step="any" placeholder="رقم درج کریں" class="w-full bg-slate-950 border border-amber-500/60 text-amber-400 font-mono font-bold px-3 py-2.5 rounded-xl text-xs focus:border-amber-500 focus:outline-none">
                </div>
            </div>

            <div>
                <label class="text-xs font-bold text-slate-400 block mb-1">سلپ / ریفرنس نمبر</label>
                <input type="text" name="slip_no" placeholder="RET-441" class="w-full bg-slate-950 border border-slate-700 text-white px-3.5 py-2.5 rounded-xl text-xs focus:border-amber-500 focus:outline-none font-mono">
            </div>

            <div>
                <label class="text-xs font-bold text-slate-400 block mb-1">واپسی کی وجہ یا تفصیل</label>
                <textarea name="description" rows="2" placeholder="خراب ہینڈز فری واپس کیں اور بل سے رقم منہا کرائی..." class="w-full bg-slate-950 border border-slate-700 text-white px-3.5 py-2 rounded-xl text-xs focus:border-amber-500 focus:outline-none placeholder-slate-600"></textarea>
            </div>

            <div class="pt-3 flex items-center justify-end gap-2 border-t border-slate-800">
                <button type="button" onclick="closeReturnModal()" class="px-4 py-2.5 bg-slate-800 hover:bg-slate-700 text-slate-300 rounded-xl text-xs font-bold transition-all">
                    منسوخ
                </button>
                <button type="submit" class="px-5 py-2.5 bg-amber-600 hover:bg-amber-500 text-white rounded-xl text-xs font-bold shadow-lg shadow-amber-950/40 transition-all">
                    کھاتے سے کٹوتی کریں
                </button>
            </div>
        </form>
    </div>
</div>

<!-- 5. Modal: Edit Supplier Details -->
<div id="editSupplierModal" class="hidden fixed inset-0 z-50 bg-black/80 backdrop-blur-sm flex items-center justify-center p-4 overflow-y-auto">
    <div class="bg-slate-900 border border-slate-800 rounded-3xl p-6 max-w-lg w-full shadow-2xl space-y-4">
        <div class="flex items-center justify-between pb-3 border-b border-slate-800">
            <div class="flex items-center gap-2">
                <div class="w-8 h-8 rounded-xl bg-slate-800 text-white flex items-center justify-center">
                    <i data-lucide="edit-3" class="w-4 h-4"></i>
                </div>
                <h3 class="font-bold text-white text-base">سپلائر کی معلومات میں تبدیلی</h3>
            </div>
            <button onclick="closeEditSupplierModal()" class="p-1 text-slate-400 hover:text-white rounded-lg">
                <i data-lucide="x" class="w-5 h-5"></i>
            </button>
        </div>

        <form method="POST" class="space-y-3.5">
            <input type="hidden" name="action" value="edit_supplier">
            <input type="hidden" name="id" id="editSupId">

            <div>
                <label class="text-xs font-bold text-slate-400 block mb-1">سپلائر نام *</label>
                <input type="text" name="name" id="editSupName" required class="w-full bg-slate-950 border border-slate-700 text-white px-3.5 py-2.5 rounded-xl text-xs focus:border-emerald-500 focus:outline-none">
            </div>

            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="text-xs font-bold text-slate-400 block mb-1">کمپنی یا مارکیٹ</label>
                    <input type="text" name="company_or_market" id="editSupCompany" class="w-full bg-slate-950 border border-slate-700 text-white px-3.5 py-2.5 rounded-xl text-xs focus:border-emerald-500 focus:outline-none">
                </div>
                <div>
                    <label class="text-xs font-bold text-slate-400 block mb-1">شہر</label>
                    <input type="text" name="city" id="editSupCity" class="w-full bg-slate-950 border border-slate-700 text-white px-3.5 py-2.5 rounded-xl text-xs focus:border-emerald-500 focus:outline-none">
                </div>
            </div>

            <div>
                <label class="text-xs font-bold text-slate-400 block mb-1">رابطہ فون نمبر *</label>
                <input type="text" name="phone" id="editSupPhone" required class="w-full bg-slate-950 border border-slate-700 text-white px-3.5 py-2.5 rounded-xl text-xs focus:border-emerald-500 focus:outline-none font-mono">
            </div>

            <div>
                <label class="text-xs font-bold text-slate-400 block mb-1">دکان یا گودام کا پتہ</label>
                <input type="text" name="address" id="editSupAddress" class="w-full bg-slate-950 border border-slate-700 text-white px-3.5 py-2.5 rounded-xl text-xs focus:border-emerald-500 focus:outline-none">
            </div>

            <div>
                <label class="text-xs font-bold text-slate-400 block mb-1">خصوصی نوٹس</label>
                <input type="text" name="notes" id="editSupNotes" class="w-full bg-slate-950 border border-slate-700 text-white px-3.5 py-2.5 rounded-xl text-xs focus:border-emerald-500 focus:outline-none">
            </div>

            <div class="pt-3 flex items-center justify-end gap-2 border-t border-slate-800">
                <button type="button" onclick="closeEditSupplierModal()" class="px-4 py-2.5 bg-slate-800 hover:bg-slate-700 text-slate-300 rounded-xl text-xs font-bold transition-all">
                    منسوخ
                </button>
                <button type="submit" class="px-5 py-2.5 bg-emerald-600 hover:bg-emerald-500 text-white rounded-xl text-xs font-bold transition-all">
                    تبدیلیاں محفوظ کریں
                </button>
            </div>
        </form>
    </div>
</div>

<!-- ==================== JAVASCRIPT LOGIC ==================== -->
<script>
function openNewSupplierModal() {
    document.getElementById('newSupplierModal').classList.remove('hidden');
}
function closeNewSupplierModal() {
    document.getElementById('newSupplierModal').classList.add('hidden');
}

function openPaySupplierModal(id, name, balance) {
    document.getElementById('paySupplierId').value = id;
    document.getElementById('paySupplierName').textContent = name;
    document.getElementById('paySupplierBalance').textContent = 'Rs. ' + Number(balance).toLocaleString();
    document.getElementById('payAmountInput').value = balance > 0 ? balance : '';
    document.getElementById('paySupplierModal').classList.remove('hidden');
}
function closePaySupplierModal() {
    document.getElementById('paySupplierModal').classList.add('hidden');
}

function openAddBillModal(id, name) {
    document.getElementById('addBillSupplierId').value = id;
    document.getElementById('addBillSupplierName').textContent = name;
    document.getElementById('addBillModal').classList.remove('hidden');
}
function closeAddBillModal() {
    document.getElementById('addBillModal').classList.add('hidden');
}

function openReturnModal(id, name) {
    document.getElementById('returnSupplierId').value = id;
    document.getElementById('returnSupplierName').textContent = name;
    document.getElementById('returnModal').classList.remove('hidden');
}
function closeReturnModal() {
    document.getElementById('returnModal').classList.add('hidden');
}

function openEditSupplierModal(sup) {
    document.getElementById('editSupId').value = sup.id;
    document.getElementById('editSupName').value = sup.name || '';
    document.getElementById('editSupCompany').value = sup.company_or_market || '';
    document.getElementById('editSupCity').value = sup.city || '';
    document.getElementById('editSupPhone').value = sup.phone || '';
    document.getElementById('editSupAddress').value = sup.address || '';
    document.getElementById('editSupNotes').value = sup.notes || '';
    document.getElementById('editSupplierModal').classList.remove('hidden');
}
function closeEditSupplierModal() {
    document.getElementById('editSupplierModal').classList.add('hidden');
}

// Print Supplier Statement
function printSupplierStatement() {
    window.print();
}

// WhatsApp Share statement
function shareOnWhatsApp(name, phone, balance, purchases, paid) {
    const cleanPhone = phone.replace(/[^0-9]/g, '');
    const waPhone = cleanPhone.startsWith('0') ? '92' + cleanPhone.slice(1) : cleanPhone;
    const msg = `*بلال موبائل شاپ و ایزی پیسہ - سپلائر کھاتہ بیانیہ*\n` +
                `محترم: *${name}*\n` +
                `تاریخ: ${new Date().toLocaleDateString('en-PK')}\n` +
                `------------------------------------\n` +
                `کل خریدا گیا مال: Rs. ${Number(purchases).toLocaleString()}\n` +
                `کل ادا شدہ رقم: Rs. ${Number(paid).toLocaleString()}\n` +
                `خالص موجودہ بقایا: *Rs. ${Number(balance).toLocaleString()}*\n` +
                `------------------------------------\n` +
                `شکریہ، بلال موبائل شاپ`;
    
    const url = `https://wa.me/${waPhone}?text=${encodeURIComponent(msg)}`;
    window.open(url, '_blank');
}

// Export All Suppliers to CSV
function exportSuppliersCSV() {
    const rows = [
        ['Supplier ID', 'Name', 'Company / Market', 'City', 'Phone', 'Payable Balance (Rs)', 'Notes']
    ];

    <?php foreach ($suppliers as $s): ?>
    rows.push([
        <?= json_encode($s['id']) ?>,
        <?= json_encode($s['name']) ?>,
        <?= json_encode($s['company_or_market'] ?? '') ?>,
        <?= json_encode($s['city'] ?? '') ?>,
        <?= json_encode($s['phone'] ?? '') ?>,
        <?= json_encode(floatval($s['balance'])) ?>,
        <?= json_encode($s['notes'] ?? '') ?>
    ]);
    <?php endforeach; ?>

    let csvContent = 'data:text/csv;charset=utf-8,\uFEFF' + rows.map(e => e.map(val => `"${String(val).replace(/"/g, '""')}"`).join(',')).join('\n');
    const encodedUri = encodeURI(csvContent);
    const link = document.createElement('a');
    link.setAttribute('href', encodedUri);
    link.setAttribute('download', `Suppliers_Directory_${new Date().toISOString().slice(0,10)}.csv`);
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
}
</script>

<style>
@media print {
    body * {
        visibility: hidden;
    }
    #printableKhataArea, #printableKhataArea * {
        visibility: visible;
    }
    #printableKhataArea {
        position: absolute;
        left: 0;
        top: 0;
        width: 100%;
        background: white !important;
        color: black !important;
    }
    #printableKhataArea th, #printableKhataArea td {
        color: black !important;
        border-color: #ddd !important;
    }
}
</style>

<?php require_once __DIR__ . '/../backend/footer.php'; ?>
