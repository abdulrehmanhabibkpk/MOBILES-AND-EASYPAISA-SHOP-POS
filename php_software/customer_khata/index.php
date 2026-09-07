<?php
$pageTitle = 'کسٹمر کھاتہ و ادھار رجسٹر (Customer Khata & Credit Ledger)';
$activeMenu = 'customer_khata';
require_once __DIR__ . '/../backend/header.php';

// Auto-create customers and customer_transactions tables if not existing
try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS customers (
        id VARCHAR(100) NOT NULL PRIMARY KEY,
        name VARCHAR(150) NOT NULL,
        phone VARCHAR(50) NOT NULL,
        cnic VARCHAR(50) NULL,
        address TEXT NULL,
        credit_limit DECIMAL(12, 2) NOT NULL DEFAULT 0.00,
        balance DECIMAL(12, 2) NOT NULL DEFAULT 0.00,
        notes TEXT NULL,
        created_at BIGINT NOT NULL DEFAULT 0
    )");

    $pdo->exec("CREATE TABLE IF NOT EXISTS customer_transactions (
        id VARCHAR(100) NOT NULL PRIMARY KEY,
        customer_id VARCHAR(100) NOT NULL,
        date VARCHAR(20) NOT NULL,
        time VARCHAR(20) NOT NULL,
        type VARCHAR(50) NOT NULL,
        invoice_no VARCHAR(100) NULL,
        description TEXT NULL,
        amount DECIMAL(12, 2) NOT NULL DEFAULT 0.00,
        balance_after DECIMAL(12, 2) NOT NULL DEFAULT 0.00,
        payment_method VARCHAR(50) NOT NULL DEFAULT 'CASH',
        notes TEXT NULL,
        created_at BIGINT NOT NULL DEFAULT 0
    )");

    // Seed default sample customers if table is empty
    $chkCount = $pdo->query("SELECT COUNT(*) FROM customers")->fetchColumn();
    if ($chkCount == 0) {
        $now = time();
        $pdo->exec("INSERT INTO customers (id, name, phone, cnic, address, credit_limit, balance, notes, created_at) VALUES
            ('cust-1', 'محمد عثمان (Usman Khan)', '0300-7654321', '36302-1234567-1', 'محلہ عیدگاہ، ملتان', 30000.00, 8500.00, 'پرانا گاہک، ہر ماہ کی 5 تاریخ کو ادھار صاف کرتا ہے', $now - 864000),
            ('cust-2', 'طارق محمود (Tariq Mehmood)', '0321-9876543', '36302-8877665-3', 'نزد ریلوے روڈ، خانیوال', 50000.00, 14200.00, 'ریڈمی نوٹ 13 موبائل قسط / ادھار', $now - 600000),
            ('cust-3', 'بلال شاہ (Bilal Shah)', '0345-1234567', '36302-5432109-7', 'مین بازار، لودھراں', 20000.00, 0.00, 'تمام ادھار کلئیر ہے', $now - 300000),
            ('cust-4', 'حمزہ علی ڈرائیور (Hamza Ali)', '0312-5556677', '36302-4433221-5', 'شجاع آباد', 15000.00, -1500.00, 'ایڈوانس رقم جمع کروائی ہوئی ہے', $now - 100000)
        ");

        $pdo->exec("INSERT INTO customer_transactions (id, customer_id, date, time, type, invoice_no, description, amount, balance_after, payment_method, notes, created_at) VALUES
            ('chk-1', 'cust-1', '" . date('Y-m-d', strtotime('-4 days')) . "', '11:30 AM', 'UDHAR', NULL, 'سام سنگ 25 واٹ فاسٹ چارجر + ٹائپ سی کیبل اور نقد ادھار', 12000.00, 12000.00, 'CASH', 'دوست کے حوالے سامان لیا', $now - 345600),
            ('chk-2', 'cust-1', '" . date('Y-m-d', strtotime('-2 days')) . "', '04:15 PM', 'VASOOLI', NULL, 'ایزی پیسہ کے ذریعے جزوی رقم ادا کی', 3500.00, 8500.00, 'EASYPAISA', 'TID: 8872194510', $now - 172800),
            ('chk-3', 'cust-2', '" . date('Y-m-d', strtotime('-8 days')) . "', '02:00 PM', 'UDHAR', 'INV-1092', 'ریڈمی نوٹ 13 نیا موبائل - بقایا قسط کھاتہ', 24200.00, 24200.00, 'CASH', 'کل قیمت 44000، 20000 نقد دیا تھا', $now - 691200),
            ('chk-4', 'cust-2', '" . date('Y-m-d', strtotime('-1 days')) . "', '06:45 PM', 'VASOOLI', NULL, 'دکان پر نقد آ کر قسط وصول کروائی', 10000.00, 14200.00, 'CASH', 'رسید جاری کی گئی', $now - 86400)
        ");
    }
} catch (Exception $e) {
    // Ignore schema errors if already present
}

$successMsg = '';
$errorMsg = '';

// Handle CSV Export
if (isset($_GET['action']) && $_GET['action'] === 'export_csv') {
    $cid = $_GET['customer_id'] ?? '';
    if ($cid) {
        $c = $pdo->prepare("SELECT * FROM customers WHERE id = ?");
        $c->execute([$cid]);
        $cust = $c->fetch();
        if ($cust) {
            header('Content-Type: text/csv; charset=utf-8');
            header('Content-Disposition: attachment; filename="Customer_Khata_' . preg_replace('/[^a-zA-Z0-9_-]/', '_', $cust['name']) . '.csv"');
            $output = fopen('php://output', 'w');
            fputcsv($output, ['Shop Name', $shopName]);
            fputcsv($output, ['Customer Name', $cust['name'], 'Phone', $cust['phone'], 'Balance Due', $cust['balance']]);
            fputcsv($output, ['ID', 'Date', 'Time', 'Type', 'Description', 'Method', 'Udhar (Gave)', 'Vasooli (Got)', 'Balance']);

            $st = $pdo->prepare("SELECT * FROM customer_transactions WHERE customer_id = ? ORDER BY created_at ASC");
            $st->execute([$cid]);
            $trxs = $st->fetchAll();
            foreach ($trxs as $t) {
                fputcsv($output, [
                    $t['id'],
                    $t['date'],
                    $t['time'],
                    $t['type'],
                    $t['description'],
                    $t['payment_method'],
                    $t['type'] === 'UDHAR' ? $t['amount'] : 0,
                    $t['type'] === 'VASOOLI' ? $t['amount'] : 0,
                    $t['balance_after']
                ]);
            }
            fclose($output);
            exit;
        }
    }
}

// Handle POST actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    // 1. Add New Customer
    if ($action === 'add_customer') {
        $name = trim($_POST['name'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        $cnic = trim($_POST['cnic'] ?? '');
        $address = trim($_POST['address'] ?? '');
        $creditLimit = floatval($_POST['credit_limit'] ?? 0);
        $openingBalance = floatval($_POST['opening_balance'] ?? 0);
        $notes = trim($_POST['notes'] ?? '');

        if (!empty($name)) {
            try {
                $id = 'cust-' . uniqid();
                $now = time();
                $stmt = $pdo->prepare("INSERT INTO customers (id, name, phone, cnic, address, credit_limit, balance, notes, created_at) VALUES (:id, :name, :phone, :cnic, :address, :clim, :bal, :notes, :created_at)");
                $stmt->execute([
                    ':id' => $id,
                    ':name' => $name,
                    ':phone' => $phone,
                    ':cnic' => $cnic,
                    ':address' => $address,
                    ':clim' => $creditLimit,
                    ':bal' => $openingBalance,
                    ':notes' => $notes,
                    ':created_at' => $now
                ]);

                // If opening balance != 0, log initial entry
                if ($openingBalance != 0) {
                    $trxId = 'chk-' . uniqid();
                    $isUdhar = $openingBalance > 0;
                    $pdo->prepare("INSERT INTO customer_transactions (id, customer_id, date, time, type, invoice_no, description, amount, balance_after, payment_method, notes, created_at) VALUES (:id, :cid, :date, :time, :type, 'OPN-BAL', :desc, :amount, :bal, 'CASH', 'کھاتہ کھولنے کے وقت سابقہ بقایا', :created_at)")->execute([
                        ':id' => $trxId,
                        ':cid' => $id,
                        ':date' => date('Y-m-d'),
                        ':time' => date('h:i A'),
                        ':type' => $isUdhar ? 'UDHAR' : 'VASOOLI',
                        ':desc' => $isUdhar ? 'سابقہ پرانا ادھار بیلنس (Opening Due)' : 'سابقہ ایڈوانس رقم (Opening Advance)',
                        ':amount' => abs($openingBalance),
                        ':bal' => $openingBalance,
                        ':created_at' => $now
                    ]);
                }

                $successMsg = "نیا کسٹمر \"{$name}\" کھاتہ رجسٹر میں کامیابی سے شامل ہو گیا!";
                $_GET['selected'] = $id;
            } catch (Exception $e) {
                $errorMsg = 'خرابی: ' . $e->getMessage();
            }
        } else {
            $errorMsg = 'براہ کرم کسٹمر کا نام لازمی درج کریں۔';
        }
    }

    // 2. Edit Customer
    elseif ($action === 'edit_customer') {
        $id = trim($_POST['id'] ?? '');
        $name = trim($_POST['name'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        $cnic = trim($_POST['cnic'] ?? '');
        $address = trim($_POST['address'] ?? '');
        $creditLimit = floatval($_POST['credit_limit'] ?? 0);
        $notes = trim($_POST['notes'] ?? '');

        if (!empty($id) && !empty($name)) {
            try {
                $stmt = $pdo->prepare("UPDATE customers SET name = :name, phone = :phone, cnic = :cnic, address = :address, credit_limit = :clim, notes = :notes WHERE id = :id");
                $stmt->execute([
                    ':id' => $id,
                    ':name' => $name,
                    ':phone' => $phone,
                    ':cnic' => $cnic,
                    ':address' => $address,
                    ':clim' => $creditLimit,
                    ':notes' => $notes
                ]);
                $successMsg = "کسٹمر \"{$name}\" کی معلومات کامیابی سے تبدیل ہو گئیں۔";
                $_GET['selected'] = $id;
            } catch (Exception $e) {
                $errorMsg = 'خرابی: ' . $e->getMessage();
            }
        }
    }

    // 3. Delete Customer
    elseif ($action === 'delete_customer') {
        $id = trim($_POST['id'] ?? '');
        if (!empty($id)) {
            try {
                $pdo->beginTransaction();
                $pdo->prepare("DELETE FROM customer_transactions WHERE customer_id = :id")->execute([':id' => $id]);
                $pdo->prepare("DELETE FROM customers WHERE id = :id")->execute([':id' => $id]);
                $pdo->commit();
                $successMsg = 'کسٹمر اور اس کی تمام کھاتہ ہسٹری کامیابی سے حذف کر دی گئی ہے۔';
                unset($_GET['selected']);
            } catch (Exception $e) {
                $pdo->rollBack();
                $errorMsg = 'خرابی: ' . $e->getMessage();
            }
        }
    }

    // 4. Give Udhar (ادھار دیا / مال دیا - Debit)
    elseif ($action === 'give_udhar') {
        $customerId = trim($_POST['customer_id'] ?? '');
        $amount = floatval($_POST['amount'] ?? 0);
        $description = trim($_POST['description'] ?? '');
        $invoiceNo = trim($_POST['invoice_no'] ?? '');
        $paymentMethod = trim($_POST['payment_method'] ?? 'CASH');
        $notes = trim($_POST['notes'] ?? '');

        if (!empty($customerId) && $amount > 0) {
            try {
                $pdo->beginTransaction();

                // Get current balance
                $cStmt = $pdo->prepare("SELECT balance, name FROM customers WHERE id = ?");
                $cStmt->execute([$customerId]);
                $cust = $cStmt->fetch();

                if (!$cust) {
                    throw new Exception("کسٹمر نہیں ملا");
                }

                $newBalance = floatval($cust['balance']) + $amount;
                $trxId = 'chk-' . uniqid();
                $now = time();

                $stmt = $pdo->prepare("INSERT INTO customer_transactions (id, customer_id, date, time, type, invoice_no, description, amount, balance_after, payment_method, notes, created_at) VALUES (:id, :cid, :date, :time, 'UDHAR', :inv, :desc, :amount, :bal, :method, :notes, :created_at)");
                $stmt->execute([
                    ':id' => $trxId,
                    ':cid' => $customerId,
                    ':date' => date('Y-m-d'),
                    ':time' => date('h:i A'),
                    ':inv' => $invoiceNo ?: null,
                    ':desc' => $description ?: 'سامان / کیش ادھار دیا',
                    ':amount' => $amount,
                    ':bal' => $newBalance,
                    ':method' => $paymentMethod,
                    ':notes' => $notes,
                    ':created_at' => $now
                ]);

                // Update customer balance
                $pdo->prepare("UPDATE customers SET balance = ? WHERE id = ?")->execute([$newBalance, $customerId]);

                $pdo->commit();
                $successMsg = "Rs. " . number_format($amount) . " کا ادھار \"{$cust['name']}\" کے کھاتہ میں کامیابی سے درج ہو گیا!";
                $_GET['selected'] = $customerId;
            } catch (Exception $e) {
                $pdo->rollBack();
                $errorMsg = 'خرابی: ' . $e->getMessage();
            }
        } else {
            $errorMsg = 'براہ کرم درست رقم درج کریں۔';
        }
    }

    // 5. Receive Payment / Vasooli (وصولی کی / رقم ملی - Credit)
    elseif ($action === 'receive_payment') {
        $customerId = trim($_POST['customer_id'] ?? '');
        $amount = floatval($_POST['amount'] ?? 0);
        $paymentMethod = trim($_POST['payment_method'] ?? 'CASH');
        $trxRef = trim($_POST['trx_ref'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $notes = trim($_POST['notes'] ?? '');

        if (!empty($customerId) && $amount > 0) {
            try {
                $pdo->beginTransaction();

                $cStmt = $pdo->prepare("SELECT balance, name FROM customers WHERE id = ?");
                $cStmt->execute([$customerId]);
                $cust = $cStmt->fetch();

                if (!$cust) {
                    throw new Exception("کسٹمر نہیں ملا");
                }

                $newBalance = floatval($cust['balance']) - $amount;
                $trxId = 'chk-' . uniqid();
                $now = time();

                $descFull = $description ?: ("وصولی رقم بذریعہ " . ($paymentMethod === 'EASYPAISA' ? 'ایزی پیسہ' : ($paymentMethod === 'JAZZCASH' ? 'جاز کیش' : ($paymentMethod === 'BANK' ? 'بینک ٹرانسفر' : 'نقد کیش'))));
                if ($trxRef) {
                    $descFull .= " (TID: {$trxRef})";
                }

                $stmt = $pdo->prepare("INSERT INTO customer_transactions (id, customer_id, date, time, type, invoice_no, description, amount, balance_after, payment_method, notes, created_at) VALUES (:id, :cid, :date, :time, 'VASOOLI', :inv, :desc, :amount, :bal, :method, :notes, :created_at)");
                $stmt->execute([
                    ':id' => $trxId,
                    ':cid' => $customerId,
                    ':date' => date('Y-m-d'),
                    ':time' => date('h:i A'),
                    ':inv' => $trxRef ?: null,
                    ':desc' => $descFull,
                    ':amount' => $amount,
                    ':bal' => $newBalance,
                    ':method' => $paymentMethod,
                    ':notes' => $notes,
                    ':created_at' => $now
                ]);

                // Update customer balance
                $pdo->prepare("UPDATE customers SET balance = ? WHERE id = ?")->execute([$newBalance, $customerId]);

                $pdo->commit();
                $successMsg = "Rs. " . number_format($amount) . " کی وصولی \"{$cust['name']}\" کے کھاتہ میں کامیابی سے درج ہو گئی!";
                $_GET['selected'] = $customerId;
            } catch (Exception $e) {
                $pdo->rollBack();
                $errorMsg = 'خرابی: ' . $e->getMessage();
            }
        } else {
            $errorMsg = 'براہ کرم درست رقم درج کریں۔';
        }
    }

    // 6. Delete Transaction
    elseif ($action === 'delete_transaction') {
        $trxId = trim($_POST['trx_id'] ?? '');
        $customerId = trim($_POST['customer_id'] ?? '');

        if (!empty($trxId) && !empty($customerId)) {
            try {
                $pdo->beginTransaction();

                $tStmt = $pdo->prepare("SELECT * FROM customer_transactions WHERE id = ? AND customer_id = ?");
                $tStmt->execute([$trxId, $customerId]);
                $trx = $tStmt->fetch();

                if ($trx) {
                    // Reverse the effect on customer balance
                    // If it was UDHAR (gave), balance was increased, so now decrease it
                    // If it was VASOOLI (received), balance was decreased, so now increase it
                    $delta = ($trx['type'] === 'UDHAR') ? -$trx['amount'] : $trx['amount'];
                    $pdo->prepare("UPDATE customers SET balance = balance + ? WHERE id = ?")->execute([$delta, $customerId]);
                    $pdo->prepare("DELETE FROM customer_transactions WHERE id = ?")->execute([$trxId]);
                }

                $pdo->commit();
                $successMsg = 'کھاتہ انٹری کامیابی سے حذف کر دی گئی ہے۔';
                $_GET['selected'] = $customerId;
            } catch (Exception $e) {
                $pdo->rollBack();
                $errorMsg = 'خرابی: ' . $e->getMessage();
            }
        }
    }
}

// Fetch Overall Stats
$totalCustomers = $pdo->query("SELECT COUNT(*) FROM customers")->fetchColumn() ?: 0;
$totalMarketUdhar = $pdo->query("SELECT SUM(balance) FROM customers WHERE balance > 0")->fetchColumn() ?: 0;
$totalAdvancePaid = $pdo->query("SELECT SUM(ABS(balance)) FROM customers WHERE balance < 0")->fetchColumn() ?: 0;
$totalDebtors = $pdo->query("SELECT COUNT(*) FROM customers WHERE balance > 0")->fetchColumn() ?: 0;

$todayStr = date('Y-m-d');
$todayCollection = $pdo->query("SELECT SUM(amount) FROM customer_transactions WHERE type = 'VASOOLI' AND date = '{$todayStr}'")->fetchColumn() ?: 0;
$todayUdharGiven = $pdo->query("SELECT SUM(amount) FROM customer_transactions WHERE type = 'UDHAR' AND date = '{$todayStr}'")->fetchColumn() ?: 0;

// Filter and Search logic
$search = trim($_GET['search'] ?? '');
$filter = $_GET['filter'] ?? 'all'; // all, due, advance, clear

$whereClauses = [];
$params = [];

if ($filter === 'due') {
    $whereClauses[] = "balance > 0";
} elseif ($filter === 'advance') {
    $whereClauses[] = "balance < 0";
} elseif ($filter === 'clear') {
    $whereClauses[] = "balance = 0";
}

if (!empty($search)) {
    $whereClauses[] = "(name LIKE :s OR phone LIKE :s OR cnic LIKE :s OR address LIKE :s)";
    $params[':s'] = "%{$search}%";
}

$whereSql = !empty($whereClauses) ? "WHERE " . implode(' AND ', $whereClauses) : "";
$customerQuery = $pdo->prepare("SELECT * FROM customers {$whereSql} ORDER BY balance DESC, created_at DESC");
$customerQuery->execute($params);
$customers = $customerQuery->fetchAll();

// Determine Selected Customer
$selectedId = $_GET['selected'] ?? ($customers[0]['id'] ?? null);
$selectedCustomer = null;
$customerTransactions = [];

if ($selectedId) {
    $sStmt = $pdo->prepare("SELECT * FROM customers WHERE id = ?");
    $sStmt->execute([$selectedId]);
    $selectedCustomer = $sStmt->fetch();

    if ($selectedCustomer) {
        $tStmt = $pdo->prepare("SELECT * FROM customer_transactions WHERE customer_id = ? ORDER BY date DESC, time DESC, created_at DESC");
        $tStmt->execute([$selectedId]);
        $customerTransactions = $tStmt->fetchAll();
    }
}

// Calculate individual stats for selected customer
$custTotalUdhar = 0;
$custTotalVasooli = 0;
if ($selectedCustomer && !empty($customerTransactions)) {
    foreach ($customerTransactions as $tr) {
        if ($tr['type'] === 'UDHAR') $custTotalUdhar += floatval($tr['amount']);
        if ($tr['type'] === 'VASOOLI') $custTotalVasooli += floatval($tr['amount']);
    }
}
?>

<!-- Alert Notification -->
<?php if (!empty($successMsg)): ?>
    <div class="mb-4 p-4 rounded-2xl bg-emerald-500/10 border border-emerald-500/30 text-emerald-300 flex items-center justify-between shadow-lg no-print animate-fade-in">
        <div class="flex items-center gap-2.5">
            <i data-lucide="check-circle" class="w-5 h-5 text-emerald-400"></i>
            <span class="font-bold text-sm"><?= htmlspecialchars($successMsg) ?></span>
        </div>
        <button onclick="this.parentElement.remove()" class="text-emerald-400 hover:text-white"><i data-lucide="x" class="w-4 h-4"></i></button>
    </div>
<?php endif; ?>

<?php if (!empty($errorMsg)): ?>
    <div class="mb-4 p-4 rounded-2xl bg-rose-500/10 border border-rose-500/30 text-rose-300 flex items-center justify-between shadow-lg no-print animate-fade-in">
        <div class="flex items-center gap-2.5">
            <i data-lucide="alert-triangle" class="w-5 h-5 text-rose-400"></i>
            <span class="font-bold text-sm"><?= htmlspecialchars($errorMsg) ?></span>
        </div>
        <button onclick="this.parentElement.remove()" class="text-rose-400 hover:text-white"><i data-lucide="x" class="w-4 h-4"></i></button>
    </div>
<?php endif; ?>

<!-- Top Dashboard KPI Cards -->
<div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 mb-6 no-print">
    <!-- Total Market Udhar -->
    <div class="bg-slate-900 border border-slate-800 hover:border-rose-500/40 rounded-2xl p-4 shadow-lg transition-all relative overflow-hidden group">
        <div class="flex items-center justify-between mb-2">
            <span class="text-xs text-slate-400 font-bold">کل بقایا ادھار (مارکیٹ لینا ہے)</span>
            <div class="w-8 h-8 rounded-xl bg-rose-500/10 text-rose-400 flex items-center justify-center">
                <i data-lucide="arrow-down-left" class="w-4 h-4"></i>
            </div>
        </div>
        <div class="text-2xl font-black text-rose-400 font-mono tracking-tight">
            Rs. <?= number_format($totalMarketUdhar) ?>
        </div>
        <div class="text-[11px] text-slate-400 mt-1 flex items-center gap-1.5">
            <span class="text-rose-400 font-bold"><?= $totalDebtors ?></span> گاہکوں کے ذمے بقایا رقم ہے
        </div>
        <div class="absolute bottom-0 left-0 right-0 h-1 bg-gradient-to-r from-rose-500 to-amber-500 opacity-60"></div>
    </div>

    <!-- Total Customers -->
    <div class="bg-slate-900 border border-slate-800 hover:border-cyan-500/40 rounded-2xl p-4 shadow-lg transition-all relative overflow-hidden group">
        <div class="flex items-center justify-between mb-2">
            <span class="text-xs text-slate-400 font-bold">کل رجسٹرڈ گاہک (Customers)</span>
            <div class="w-8 h-8 rounded-xl bg-cyan-500/10 text-cyan-400 flex items-center justify-center">
                <i data-lucide="users" class="w-4 h-4"></i>
            </div>
        </div>
        <div class="text-2xl font-black text-white font-mono tracking-tight">
            <?= number_format($totalCustomers) ?>
        </div>
        <div class="text-[11px] text-slate-400 mt-1">
            موبائل اور اسیسریز کسٹمر ڈائریکٹری
        </div>
        <div class="absolute bottom-0 left-0 right-0 h-1 bg-gradient-to-r from-cyan-500 to-blue-500 opacity-60"></div>
    </div>

    <!-- Today's Collection (Vasooli) -->
    <div class="bg-slate-900 border border-slate-800 hover:border-emerald-500/40 rounded-2xl p-4 shadow-lg transition-all relative overflow-hidden group">
        <div class="flex items-center justify-between mb-2">
            <span class="text-xs text-slate-400 font-bold">آج کی کل وصولی (Today Recovery)</span>
            <div class="w-8 h-8 rounded-xl bg-emerald-500/10 text-emerald-400 flex items-center justify-center">
                <i data-lucide="check-check" class="w-4 h-4"></i>
            </div>
        </div>
        <div class="text-2xl font-black text-emerald-400 font-mono tracking-tight">
            Rs. <?= number_format($todayCollection) ?>
        </div>
        <div class="text-[11px] text-slate-400 mt-1">
            کیش اور ایزی پیسہ وصول شدہ
        </div>
        <div class="absolute bottom-0 left-0 right-0 h-1 bg-gradient-to-r from-emerald-500 to-teal-500 opacity-60"></div>
    </div>

    <!-- Today's New Udhar Given -->
    <div class="bg-slate-900 border border-slate-800 hover:border-amber-500/40 rounded-2xl p-4 shadow-lg transition-all relative overflow-hidden group">
        <div class="flex items-center justify-between mb-2">
            <span class="text-xs text-slate-400 font-bold">آج کا نیا ادھار (Today Given)</span>
            <div class="w-8 h-8 rounded-xl bg-amber-500/10 text-amber-400 flex items-center justify-center">
                <i data-lucide="arrow-up-right" class="w-4 h-4"></i>
            </div>
        </div>
        <div class="text-2xl font-black text-amber-400 font-mono tracking-tight">
            Rs. <?= number_format($todayUdharGiven) ?>
        </div>
        <div class="text-[11px] text-slate-400 mt-1">
            آج دیا گیا ادھار مال و کیش
        </div>
        <div class="absolute bottom-0 left-0 right-0 h-1 bg-gradient-to-r from-amber-500 to-orange-500 opacity-60"></div>
    </div>
</div>

<!-- Main 2-Column Khata Layout -->
<div class="grid grid-cols-1 lg:grid-cols-12 gap-6 items-start no-print">

    <!-- Left Column: Customer Directory (4.5 Cols) -->
    <div class="lg:col-span-4 bg-slate-900 border border-slate-800 rounded-2xl p-4 shadow-lg space-y-4">
        
        <!-- Header & Add Button -->
        <div class="flex items-center justify-between pb-3 border-b border-slate-800">
            <div>
                <h3 class="font-bold text-white text-sm flex items-center gap-2">
                    <i data-lucide="book-user" class="w-4 h-4 text-emerald-400"></i>
                    <span>کسٹمرز لسٹ (<?= count($customers) ?>)</span>
                </h3>
            </div>
            <button onclick="openModal('addCustomerModal')" class="px-3 py-1.5 bg-emerald-600 hover:bg-emerald-500 text-white rounded-xl text-xs font-bold flex items-center gap-1.5 shadow-lg shadow-emerald-600/30 transition-all cursor-pointer">
                <i data-lucide="user-plus" class="w-3.5 h-3.5"></i>
                <span>+ نیا گاہک</span>
            </button>
        </div>

        <!-- Search Bar -->
        <form method="GET" class="space-y-2">
            <?php if (isset($_GET['selected'])): ?>
                <input type="hidden" name="selected" value="<?= htmlspecialchars($_GET['selected']) ?>">
            <?php endif; ?>
            <div class="relative">
                <i data-lucide="search" class="w-4 h-4 text-slate-400 absolute right-3 top-2.5"></i>
                <input type="text" name="search" value="<?= htmlspecialchars($search) ?>" placeholder="نام، فون، شناختی کارڈ یا پتہ..." class="w-full bg-slate-950 border border-slate-700 text-white pr-9 pl-3 py-2 rounded-xl text-xs focus:border-emerald-500 focus:outline-none placeholder:text-slate-500">
            </div>

            <!-- Filter Tabs -->
            <div class="grid grid-cols-4 gap-1 p-1 bg-slate-950 rounded-xl border border-slate-800 text-[11px] text-center font-bold">
                <a href="?filter=all&search=<?= urlencode($search) ?><?= $selectedId ? '&selected='.$selectedId : '' ?>" class="py-1.5 rounded-lg transition-colors <?= $filter === 'all' ? 'bg-slate-800 text-white' : 'text-slate-400 hover:text-white' ?>">سب</a>
                <a href="?filter=due&search=<?= urlencode($search) ?><?= $selectedId ? '&selected='.$selectedId : '' ?>" class="py-1.5 rounded-lg transition-colors <?= $filter === 'due' ? 'bg-rose-500/20 text-rose-300' : 'text-slate-400 hover:text-rose-400' ?>">بقایا دار</a>
                <a href="?filter=advance&search=<?= urlencode($search) ?><?= $selectedId ? '&selected='.$selectedId : '' ?>" class="py-1.5 rounded-lg transition-colors <?= $filter === 'advance' ? 'bg-blue-500/20 text-blue-300' : 'text-slate-400 hover:text-blue-400' ?>">ایڈوانس</a>
                <a href="?filter=clear&search=<?= urlencode($search) ?><?= $selectedId ? '&selected='.$selectedId : '' ?>" class="py-1.5 rounded-lg transition-colors <?= $filter === 'clear' ? 'bg-emerald-500/20 text-emerald-300' : 'text-slate-400 hover:text-emerald-400' ?>">برابر</a>
            </div>
        </form>

        <!-- Customer List Scroll -->
        <div class="space-y-2 max-h-[600px] overflow-y-auto pr-1">
            <?php if (empty($customers)): ?>
                <div class="text-center py-12 text-slate-500">
                    <i data-lucide="user-x" class="w-10 h-10 mx-auto mb-2 opacity-40"></i>
                    <p class="text-xs">کوئی کسٹمر نہیں ملا</p>
                </div>
            <?php else: foreach ($customers as $c): 
                $isSelected = ($selectedCustomer && $selectedCustomer['id'] === $c['id']);
                $bal = floatval($c['balance']);
                $cleanPhone = preg_replace('/[^0-9]/', '', $c['phone']);
                if (str_starts_with($cleanPhone, '0')) $cleanPhone = '92' . substr($cleanPhone, 1);
            ?>
                <div onclick="window.location.href='?selected=<?= $c['id'] ?>&filter=<?= $filter ?>&search=<?= urlencode($search) ?>'" class="p-3 rounded-xl border transition-all cursor-pointer <?= $isSelected ? 'bg-emerald-500/10 border-emerald-500 shadow-md' : 'bg-slate-950/60 border-slate-800/80 hover:border-slate-700' ?>">
                    <div class="flex items-center justify-between mb-1.5">
                        <div class="font-bold text-sm text-white flex items-center gap-1.5">
                            <span class="w-6 h-6 rounded-full bg-slate-800 text-emerald-400 flex items-center justify-center text-[10px]">
                                <?= mb_substr($c['name'], 0, 1) ?>
                            </span>
                            <span class="truncate max-w-[140px]"><?= htmlspecialchars($c['name']) ?></span>
                        </div>
                        
                        <!-- Balance Badge -->
                        <?php if ($bal > 0): ?>
                            <span class="text-xs font-mono font-bold text-rose-400 bg-rose-500/10 border border-rose-500/20 px-2 py-0.5 rounded-lg">
                                Rs. <?= number_format($bal) ?> <span class="text-[10px]">لینا ہے</span>
                            </span>
                        <?php elseif ($bal < 0): ?>
                            <span class="text-xs font-mono font-bold text-blue-400 bg-blue-500/10 border border-blue-500/20 px-2 py-0.5 rounded-lg">
                                Rs. <?= number_format(abs($bal)) ?> <span class="text-[10px]">ایڈوانس</span>
                            </span>
                        <?php else: ?>
                            <span class="text-[11px] font-bold text-emerald-400 bg-emerald-500/10 border border-emerald-500/20 px-2 py-0.5 rounded-lg">
                                حساب برابر
                            </span>
                        <?php endif; ?>
                    </div>

                    <div class="flex items-center justify-between text-xs text-slate-400">
                        <span class="font-mono text-[11px] flex items-center gap-1">
                            <i data-lucide="phone" class="w-3 h-3 text-slate-500"></i>
                            <?= htmlspecialchars($c['phone'] ?: 'فون نہیں ہے') ?>
                        </span>
                        <?php if (!empty($c['address'])): ?>
                            <span class="text-[10px] text-slate-500 truncate max-w-[110px]"><?= htmlspecialchars($c['address']) ?></span>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; endif; ?>
        </div>
    </div>

    <!-- Right Column: Customer Details, Ledger Timeline & Action Panel (8 Cols) -->
    <div class="lg:col-span-8 space-y-5">
        <?php if (!$selectedCustomer): ?>
            <div class="bg-slate-900 border border-slate-800 rounded-2xl p-12 text-center text-slate-500 shadow-lg">
                <i data-lucide="book-open" class="w-16 h-16 mx-auto mb-3 text-slate-600"></i>
                <h4 class="text-base font-bold text-slate-300">کوئی کسٹمر منتخب نہیں ہوا</h4>
                <p class="text-xs text-slate-500 mt-1">بائیں طرف لسٹ میں سے کسی کسٹمر پر کلک کریں یا نیا کسٹمر شامل کریں</p>
                <button onclick="openModal('addCustomerModal')" class="mt-4 px-4 py-2 bg-emerald-600 hover:bg-emerald-500 text-white rounded-xl text-xs font-bold inline-flex items-center gap-2 shadow-lg">
                    <i data-lucide="user-plus" class="w-4 h-4"></i>
                    <span>نیا کسٹمر کھاتہ کھولیں</span>
                </button>
            </div>
        <?php else: 
            $currBal = floatval($selectedCustomer['balance']);
            $cleanPhone = preg_replace('/[^0-9]/', '', $selectedCustomer['phone']);
            if (str_starts_with($cleanPhone, '0')) $cleanPhone = '92' . substr($cleanPhone, 1);
            
            // Professional Urdu WhatsApp message
            $waMsg = "السلام علیکم! محترم {$selectedCustomer['name']} صاحب،\n{$shopName} کی طرف سے یاد دہانی:\nآپ کے کھاتہ میں کل بقایا ادھار رقم *Rs. " . number_format($currBal) . "* واجب الادا ہے۔\nبرائے مہربانی تشریف لا کر یا ایزی پیسہ کے ذریعے کھاتہ کلئیر فرما دیں۔\nشکریہ! رابطہ: {$settings['phone']}";
            $waUrl = "https://wa.me/{$cleanPhone}?text=" . urlencode($waMsg);
        ?>
            <!-- Customer Profile Card -->
            <div class="bg-slate-900 border border-slate-800 rounded-2xl p-5 shadow-lg space-y-4">
                
                <!-- Card Header -->
                <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 pb-4 border-b border-slate-800">
                    <div>
                        <div class="flex items-center gap-2">
                            <h2 class="text-lg font-black text-white"><?= htmlspecialchars($selectedCustomer['name']) ?></h2>
                            <?php if ($currBal > 0): ?>
                                <span class="px-2 py-0.5 rounded-full bg-rose-500/10 text-rose-400 border border-rose-500/20 text-[10px] font-bold">بقایا دار (Debtor)</span>
                            <?php elseif ($currBal == 0): ?>
                                <span class="px-2 py-0.5 rounded-full bg-emerald-500/10 text-emerald-400 border border-emerald-500/20 text-[10px] font-bold">صاف کھاتہ</span>
                            <?php endif; ?>
                        </div>
                        <div class="flex flex-wrap items-center gap-4 text-xs text-slate-400 mt-1.5">
                            <span class="font-mono flex items-center gap-1 text-emerald-400">
                                <i data-lucide="phone" class="w-3.5 h-3.5"></i>
                                <?= htmlspecialchars($selectedCustomer['phone'] ?: 'N/A') ?>
                            </span>
                            <?php if (!empty($selectedCustomer['cnic'])): ?>
                                <span class="font-mono flex items-center gap-1">
                                    <i data-lucide="credit-card" class="w-3.5 h-3.5 text-cyan-400"></i>
                                    <?= htmlspecialchars($selectedCustomer['cnic']) ?>
                                </span>
                            <?php endif; ?>
                            <?php if (!empty($selectedCustomer['address'])): ?>
                                <span class="flex items-center gap-1 text-slate-300">
                                    <i data-lucide="map-pin" class="w-3.5 h-3.5 text-amber-400"></i>
                                    <?= htmlspecialchars($selectedCustomer['address']) ?>
                                </span>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Actions Bar -->
                    <div class="flex items-center gap-2 flex-wrap">
                        <!-- WhatsApp Reminder -->
                        <?php if (!empty($selectedCustomer['phone'])): ?>
                            <a href="<?= $waUrl ?>" target="_blank" class="px-3 py-2 bg-emerald-600 hover:bg-emerald-500 text-white rounded-xl text-xs font-bold flex items-center gap-1.5 shadow-lg shadow-emerald-600/20 transition-all">
                                <i data-lucide="message-circle" class="w-4 h-4"></i>
                                <span>واٹس ایپ یاد دہانی</span>
                            </a>
                        <?php endif; ?>

                        <!-- Print Statement -->
                        <button onclick="window.print()" class="px-3 py-2 bg-slate-800 hover:bg-slate-700 text-white rounded-xl text-xs font-bold flex items-center gap-1.5 border border-slate-700 transition-all cursor-pointer">
                            <i data-lucide="printer" class="w-4 h-4 text-cyan-400"></i>
                            <span>پرنٹ کھاتہ</span>
                        </button>

                        <!-- CSV Download -->
                        <a href="?action=export_csv&customer_id=<?= $selectedCustomer['id'] ?>" class="px-3 py-2 bg-slate-800 hover:bg-slate-700 text-white rounded-xl text-xs font-bold flex items-center gap-1.5 border border-slate-700 transition-all">
                            <i data-lucide="download" class="w-4 h-4 text-teal-400"></i>
                            <span>CSV</span>
                        </a>

                        <!-- Edit Customer -->
                        <button onclick="openEditCustomerModal()" class="p-2 bg-slate-800 hover:bg-slate-700 text-slate-300 hover:text-white rounded-xl border border-slate-700 cursor-pointer" title="کسٹمر معلومات تبدیل کریں">
                            <i data-lucide="edit" class="w-4 h-4"></i>
                        </button>

                        <!-- Delete Customer -->
                        <form method="POST" onsubmit="return confirm('کیا آپ واقعی یہ کسٹمر اور اس کا مکمل کھاتہ حذف کرنا چاہتے ہیں؟')" class="inline">
                            <input type="hidden" name="action" value="delete_customer">
                            <input type="hidden" name="id" value="<?= $selectedCustomer['id'] ?>">
                            <button type="submit" class="p-2 bg-slate-800 hover:bg-rose-600/30 text-rose-400 rounded-xl border border-slate-700 cursor-pointer" title="حذف کریں">
                                <i data-lucide="trash-2" class="w-4 h-4"></i>
                            </button>
                        </form>
                    </div>
                </div>

                <!-- Financial Stats & Quick Buttons -->
                <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                    <div class="bg-slate-950 p-3.5 rounded-xl border border-slate-800">
                        <span class="text-[11px] text-slate-400 block font-medium">کل ادھار دیا (Total Udhar)</span>
                        <span class="text-base font-black font-mono text-rose-400 mt-0.5 block">Rs. <?= number_format($custTotalUdhar) ?></span>
                    </div>

                    <div class="bg-slate-950 p-3.5 rounded-xl border border-slate-800">
                        <span class="text-[11px] text-slate-400 block font-medium">کل وصولی ہوئی (Total Got)</span>
                        <span class="text-base font-black font-mono text-emerald-400 mt-0.5 block">Rs. <?= number_format($custTotalVasooli) ?></span>
                    </div>

                    <div class="bg-slate-950 p-3.5 rounded-xl border <?= $currBal > 0 ? 'border-rose-500/30 bg-rose-950/10' : ($currBal < 0 ? 'border-blue-500/30 bg-blue-950/10' : 'border-emerald-500/30 bg-emerald-950/10') ?>">
                        <span class="text-[11px] text-slate-400 block font-medium">موجودہ خالص بقایا (Net Balance)</span>
                        <span class="text-base font-black font-mono <?= $currBal > 0 ? 'text-rose-400' : ($currBal < 0 ? 'text-blue-400' : 'text-emerald-400') ?> mt-0.5 block">
                            Rs. <?= number_format(abs($currBal)) ?>
                            <span class="text-[10px] font-normal"><?= $currBal > 0 ? '(لینا ہے)' : ($currBal < 0 ? '(ایڈوانس دینا ہے)' : '(برابر)') ?></span>
                        </span>
                    </div>
                </div>

                <!-- 2 Large Buttons: Udhar Diya & Vasooli Ki -->
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 pt-1">
                    <button onclick="openGiveUdharModal()" class="py-3 px-4 rounded-xl bg-rose-600 hover:bg-rose-500 text-white font-bold text-xs flex items-center justify-center gap-2 shadow-lg shadow-rose-950/40 cursor-pointer transition-all">
                        <i data-lucide="arrow-down-left" class="w-4 h-4"></i>
                        <span>+ ادھار دیا (You Gave / سامان دیا)</span>
                    </button>

                    <button onclick="openReceivePaymentModal()" class="py-3 px-4 rounded-xl bg-emerald-600 hover:bg-emerald-500 text-white font-bold text-xs flex items-center justify-center gap-2 shadow-lg shadow-emerald-950/40 cursor-pointer transition-all">
                        <i data-lucide="arrow-up-right" class="w-4 h-4"></i>
                        <span>+ وصولی کی (You Received / رقم ملی)</span>
                    </button>
                </div>
            </div>

            <!-- Ledger Entries Table -->
            <div class="bg-slate-900 border border-slate-800 rounded-2xl p-4 sm:p-5 shadow-lg space-y-3">
                <div class="flex items-center justify-between pb-2 border-b border-slate-800">
                    <h3 class="font-bold text-white text-sm flex items-center gap-2">
                        <i data-lucide="history" class="w-4 h-4 text-cyan-400"></i>
                        <span>کھاتہ روزنامچہ و ٹرانزیکشن ہسٹری (<?= count($customerTransactions) ?> انٹریز)</span>
                    </h3>
                </div>

                <div class="overflow-x-auto">
                    <table class="w-full text-right text-xs">
                        <thead class="bg-slate-950 text-slate-400 font-semibold border-b border-slate-800">
                            <tr>
                                <th class="p-3">تاریخ و وقت</th>
                                <th class="p-3">تفصیل و سامان</th>
                                <th class="p-3">ذریعہ</th>
                                <th class="p-3 text-rose-400">ادھار دیا (-)</th>
                                <th class="p-3 text-emerald-400">وصولی کی (+)</th>
                                <th class="p-3">بقایا بیلنس</th>
                                <th class="p-3 text-center">ایکشن</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-800">
                            <?php if (empty($customerTransactions)): ?>
                                <tr>
                                    <td colspan="7" class="py-12 text-center text-slate-500">
                                        اس گاہک کی ابھی کوئی ٹرانزیکشن موجود نہیں ہے۔
                                    </td>
                                </tr>
                            <?php else: foreach ($customerTransactions as $tr): 
                                $isUdhar = ($tr['type'] === 'UDHAR');
                            ?>
                                <tr class="hover:bg-slate-800/40 transition-colors">
                                    <td class="p-3 font-mono text-slate-300 whitespace-nowrap">
                                        <?= htmlspecialchars($tr['date']) ?>
                                        <span class="text-[10px] text-slate-500 block"><?= htmlspecialchars($tr['time']) ?></span>
                                    </td>
                                    <td class="p-3 text-white max-w-[220px]">
                                        <div class="font-medium truncate"><?= htmlspecialchars($tr['description']) ?></div>
                                        <?php if (!empty($tr['invoice_no'])): ?>
                                            <span class="text-[10px] font-mono px-1.5 py-0.5 rounded bg-slate-800 text-slate-400">بل: <?= htmlspecialchars($tr['invoice_no']) ?></span>
                                        <?php endif; ?>
                                        <?php if (!empty($tr['notes'])): ?>
                                            <span class="text-[10px] text-slate-400 block italic"><?= htmlspecialchars($tr['notes']) ?></span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="p-3 whitespace-nowrap">
                                        <span class="px-2 py-0.5 rounded-full text-[10px] font-bold <?= $tr['payment_method'] === 'EASYPAISA' ? 'bg-emerald-500/10 text-emerald-400' : ($tr['payment_method'] === 'JAZZCASH' ? 'bg-amber-500/10 text-amber-400' : ($tr['payment_method'] === 'BANK' ? 'bg-blue-500/10 text-blue-400' : 'bg-slate-800 text-slate-300')) ?>">
                                            <?= htmlspecialchars($tr['payment_method']) ?>
                                        </span>
                                    </td>
                                    <td class="p-3 font-mono font-bold text-rose-400 whitespace-nowrap">
                                        <?= $isUdhar ? 'Rs. ' . number_format($tr['amount']) : '-' ?>
                                    </td>
                                    <td class="p-3 font-mono font-bold text-emerald-400 whitespace-nowrap">
                                        <?= !$isUdhar ? 'Rs. ' . number_format($tr['amount']) : '-' ?>
                                    </td>
                                    <td class="p-3 font-mono font-bold text-white whitespace-nowrap">
                                        Rs. <?= number_format($tr['balance_after']) ?>
                                    </td>
                                    <td class="p-3 text-center whitespace-nowrap">
                                        <form method="POST" onsubmit="return confirm('کیا آپ یہ انٹری حذف کرنا چاہتے ہیں؟ اس سے کسٹمر کا بیلنس واپس ایڈجسٹ ہو جائے گا۔')" class="inline">
                                            <input type="hidden" name="action" value="delete_transaction">
                                            <input type="hidden" name="trx_id" value="<?= $tr['id'] ?>">
                                            <input type="hidden" name="customer_id" value="<?= $selectedCustomer['id'] ?>">
                                            <button type="submit" class="p-1 rounded text-slate-500 hover:text-rose-400 hover:bg-slate-800" title="انٹری ڈیلیٹ کریں">
                                                <i data-lucide="trash" class="w-3.5 h-3.5"></i>
                                            </button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

        <?php endif; ?>
    </div>

</div>

<!-- Printable Statement Layout (Only shown during window.print()) -->
<?php if ($selectedCustomer): ?>
<div class="print-only hidden p-8 bg-white text-black min-h-screen text-right" dir="rtl" style="font-family: Arial, sans-serif;">
    <div class="text-center pb-4 border-b-2 border-black mb-6">
        <h1 class="text-2xl font-black mb-1"><?= $shopName ?></h1>
        <p class="text-sm font-semibold">موبائلز، اسیسریز، ایزی پیسہ و کھاتہ رجسٹر</p>
        <p class="text-xs text-gray-600 mt-1"><?= htmlspecialchars($settings['address'] ?? '') ?> | فون: <?= htmlspecialchars($settings['phone'] ?? '') ?></p>
        <div class="inline-block mt-3 px-4 py-1 bg-gray-200 text-black text-xs font-bold rounded-full">
            کسٹمر کھاتہ اسٹیٹمنٹ (Customer Khata Statement)
        </div>
    </div>

    <div class="grid grid-cols-2 gap-4 text-xs mb-6 border p-3 rounded">
        <div>
            <p><strong>کسٹمر کا نام:</strong> <?= htmlspecialchars($selectedCustomer['name']) ?></p>
            <p><strong>موبائل فون:</strong> <?= htmlspecialchars($selectedCustomer['phone']) ?></p>
            <p><strong>شناختی کارڈ:</strong> <?= htmlspecialchars($selectedCustomer['cnic'] ?: 'N/A') ?></p>
        </div>
        <div>
            <p><strong>پرنٹ کی تاریخ:</strong> <?= date('d M Y, h:i A') ?></p>
            <p><strong>مقام / پتہ:</strong> <?= htmlspecialchars($selectedCustomer['address'] ?: 'N/A') ?></p>
            <p class="text-sm font-bold mt-1 text-red-600"><strong>کل بقایا ادھار واجب الادا:</strong> Rs. <?= number_format($currBal) ?></p>
        </div>
    </div>

    <table class="w-full text-right text-xs border border-collapse border-black mb-6">
        <thead>
            <tr class="bg-gray-100 border-b border-black">
                <th class="p-2 border border-black">تاریخ و وقت</th>
                <th class="p-2 border border-black">تفصیل و سامان</th>
                <th class="p-2 border border-black">ذریعہ</th>
                <th class="p-2 border border-black">ادھار دیا (+)</th>
                <th class="p-2 border border-black">وصولی کی (-)</th>
                <th class="p-2 border border-black">بقایا ادھار</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($customerTransactions as $tr): ?>
                <tr class="border-b border-gray-300">
                    <td class="p-2 border border-black font-mono"><?= $tr['date'] ?> <?= $tr['time'] ?></td>
                    <td class="p-2 border border-black"><?= htmlspecialchars($tr['description']) ?></td>
                    <td class="p-2 border border-black"><?= htmlspecialchars($tr['payment_method']) ?></td>
                    <td class="p-2 border border-black font-mono font-bold"><?= $tr['type'] === 'UDHAR' ? 'Rs. ' . number_format($tr['amount']) : '-' ?></td>
                    <td class="p-2 border border-black font-mono font-bold"><?= $tr['type'] === 'VASOOLI' ? 'Rs. ' . number_format($tr['amount']) : '-' ?></td>
                    <td class="p-2 border border-black font-mono font-bold">Rs. <?= number_format($tr['balance_after']) ?></td>
                </tr>
            <?php endforeach; ?>
        </tbody>
        <tfoot>
            <tr class="bg-gray-100 font-bold border-t-2 border-black">
                <td colspan="3" class="p-2 text-left">ٹوٹل سمری:</td>
                <td class="p-2 border border-black">Rs. <?= number_format($custTotalUdhar) ?></td>
                <td class="p-2 border border-black">Rs. <?= number_format($custTotalVasooli) ?></td>
                <td class="p-2 border border-black text-red-600">Rs. <?= number_format($currBal) ?></td>
            </tr>
        </tfoot>
    </table>

    <div class="flex justify-between items-center text-xs mt-12 pt-6 border-t border-gray-400">
        <div>
            <p>دستخط دکاندار / منیجر: _________________________</p>
        </div>
        <div>
            <p>دستخط کسٹمر / وصول کنندہ: _________________________</p>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- ================= MODALS ================= -->

<!-- 1. Add Customer Modal -->
<div id="addCustomerModal" class="fixed inset-0 z-50 bg-black/80 backdrop-blur-sm hidden items-center justify-center p-4">
    <div class="bg-slate-900 border border-slate-800 rounded-2xl w-full max-w-md p-5 shadow-2xl space-y-4">
        <div class="flex items-center justify-between pb-3 border-b border-slate-800">
            <h3 class="font-bold text-white text-base flex items-center gap-2">
                <i data-lucide="user-plus" class="w-5 h-5 text-emerald-400"></i>
                <span>نیا کسٹمر کھاتہ درج کریں</span>
            </h3>
            <button onclick="closeModal('addCustomerModal')" class="text-slate-400 hover:text-white"><i data-lucide="x" class="w-5 h-5"></i></button>
        </div>

        <form method="POST" class="space-y-3">
            <input type="hidden" name="action" value="add_customer">

            <div>
                <label class="text-xs text-slate-400 block mb-1">کسٹمر کا مکمل نام *</label>
                <input type="text" name="name" required placeholder="مثلاً: محمد عثمان ملک" class="w-full bg-slate-950 border border-slate-700 text-white px-3 py-2 rounded-xl text-xs focus:border-emerald-500 focus:outline-none">
            </div>

            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="text-xs text-slate-400 block mb-1">موبائل فون (واٹس ایپ کے لیے) *</label>
                    <input type="text" name="phone" required placeholder="0300-1234567" class="w-full bg-slate-950 border border-slate-700 text-white px-3 py-2 rounded-xl text-xs focus:border-emerald-500 focus:outline-none">
                </div>
                <div>
                    <label class="text-xs text-slate-400 block mb-1">شناختی کارڈ نمبر (اختیاری)</label>
                    <input type="text" name="cnic" placeholder="36302-1234567-1" class="w-full bg-slate-950 border border-slate-700 text-white px-3 py-2 rounded-xl text-xs focus:border-emerald-500 focus:outline-none">
                </div>
            </div>

            <div>
                <label class="text-xs text-slate-400 block mb-1">پتہ یا دکان / محلہ</label>
                <input type="text" name="address" placeholder="محلہ عیدگاہ، ملتان" class="w-full bg-slate-950 border border-slate-700 text-white px-3 py-2 rounded-xl text-xs focus:border-emerald-500 focus:outline-none">
            </div>

            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="text-xs text-slate-400 block mb-1">سابقہ پرانا بیلنس (اگر ہے)</label>
                    <input type="number" name="opening_balance" value="0" placeholder="0" class="w-full bg-slate-950 border border-slate-700 text-amber-400 font-bold px-3 py-2 rounded-xl text-xs focus:border-emerald-500 focus:outline-none">
                </div>
                <div>
                    <label class="text-xs text-slate-400 block mb-1">ادھار کی حد (Credit Limit)</label>
                    <input type="number" name="credit_limit" value="25000" placeholder="25000" class="w-full bg-slate-950 border border-slate-700 text-white px-3 py-2 rounded-xl text-xs focus:border-emerald-500 focus:outline-none">
                </div>
            </div>

            <div>
                <label class="text-xs text-slate-400 block mb-1">اضافی نوٹ / یاد دہانی</label>
                <input type="text" name="notes" placeholder="نوٹ لکھیں..." class="w-full bg-slate-950 border border-slate-700 text-white px-3 py-2 rounded-xl text-xs focus:border-emerald-500 focus:outline-none">
            </div>

            <div class="pt-2 flex items-center justify-end gap-2">
                <button type="button" onclick="closeModal('addCustomerModal')" class="px-4 py-2 bg-slate-800 hover:bg-slate-700 text-slate-300 rounded-xl text-xs font-bold">منسوخ</button>
                <button type="submit" class="px-5 py-2 bg-emerald-600 hover:bg-emerald-500 text-white rounded-xl text-xs font-bold shadow-lg shadow-emerald-600/30">کسٹمر محفوظ کریں</button>
            </div>
        </form>
    </div>
</div>

<!-- 2. Edit Customer Modal -->
<div id="editCustomerModal" class="fixed inset-0 z-50 bg-black/80 backdrop-blur-sm hidden items-center justify-center p-4">
    <div class="bg-slate-900 border border-slate-800 rounded-2xl w-full max-w-md p-5 shadow-2xl space-y-4">
        <div class="flex items-center justify-between pb-3 border-b border-slate-800">
            <h3 class="font-bold text-white text-base flex items-center gap-2">
                <i data-lucide="edit" class="w-5 h-5 text-cyan-400"></i>
                <span>کسٹمر کی معلومات تبدیل کریں</span>
            </h3>
            <button onclick="closeModal('editCustomerModal')" class="text-slate-400 hover:text-white"><i data-lucide="x" class="w-5 h-5"></i></button>
        </div>

        <form method="POST" class="space-y-3">
            <input type="hidden" name="action" value="edit_customer">
            <input type="hidden" name="id" id="edit_id">

            <div>
                <label class="text-xs text-slate-400 block mb-1">کسٹمر کا نام *</label>
                <input type="text" name="name" id="edit_name" required class="w-full bg-slate-950 border border-slate-700 text-white px-3 py-2 rounded-xl text-xs focus:border-emerald-500 focus:outline-none">
            </div>

            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="text-xs text-slate-400 block mb-1">موبائل فون</label>
                    <input type="text" name="phone" id="edit_phone" class="w-full bg-slate-950 border border-slate-700 text-white px-3 py-2 rounded-xl text-xs focus:border-emerald-500 focus:outline-none">
                </div>
                <div>
                    <label class="text-xs text-slate-400 block mb-1">شناختی کارڈ</label>
                    <input type="text" name="cnic" id="edit_cnic" class="w-full bg-slate-950 border border-slate-700 text-white px-3 py-2 rounded-xl text-xs focus:border-emerald-500 focus:outline-none">
                </div>
            </div>

            <div>
                <label class="text-xs text-slate-400 block mb-1">پتہ یا علاقہ</label>
                <input type="text" name="address" id="edit_address" class="w-full bg-slate-950 border border-slate-700 text-white px-3 py-2 rounded-xl text-xs focus:border-emerald-500 focus:outline-none">
            </div>

            <div>
                <label class="text-xs text-slate-400 block mb-1">کریڈٹ لمٹ (Rs)</label>
                <input type="number" name="credit_limit" id="edit_credit_limit" class="w-full bg-slate-950 border border-slate-700 text-white px-3 py-2 rounded-xl text-xs focus:border-emerald-500 focus:outline-none">
            </div>

            <div>
                <label class="text-xs text-slate-400 block mb-1">نوٹ</label>
                <input type="text" name="notes" id="edit_notes" class="w-full bg-slate-950 border border-slate-700 text-white px-3 py-2 rounded-xl text-xs focus:border-emerald-500 focus:outline-none">
            </div>

            <div class="pt-2 flex items-center justify-end gap-2">
                <button type="button" onclick="closeModal('editCustomerModal')" class="px-4 py-2 bg-slate-800 hover:bg-slate-700 text-slate-300 rounded-xl text-xs font-bold">منسوخ</button>
                <button type="submit" class="px-5 py-2 bg-cyan-600 hover:bg-cyan-500 text-white rounded-xl text-xs font-bold shadow-lg">تبدیلیاں محفوظ کریں</button>
            </div>
        </form>
    </div>
</div>

<!-- 3. Give Udhar Modal (ادھار دیا) -->
<div id="giveUdharModal" class="fixed inset-0 z-50 bg-black/80 backdrop-blur-sm hidden items-center justify-center p-4">
    <div class="bg-slate-900 border border-slate-800 rounded-2xl w-full max-w-md p-5 shadow-2xl space-y-4">
        <div class="flex items-center justify-between pb-3 border-b border-slate-800">
            <h3 class="font-bold text-white text-base flex items-center gap-2">
                <i data-lucide="arrow-down-left" class="w-5 h-5 text-rose-400"></i>
                <span>ادھار اندراج (You Gave / سامان دیا)</span>
            </h3>
            <button onclick="closeModal('giveUdharModal')" class="text-slate-400 hover:text-white"><i data-lucide="x" class="w-5 h-5"></i></button>
        </div>

        <form method="POST" class="space-y-3">
            <input type="hidden" name="action" value="give_udhar">
            <input type="hidden" name="customer_id" value="<?= $selectedCustomer['id'] ?? '' ?>">

            <div class="p-3 bg-slate-950 rounded-xl border border-slate-800 flex items-center justify-between text-xs">
                <span class="text-slate-400">گاہک:</span>
                <span class="text-white font-bold"><?= htmlspecialchars($selectedCustomer['name'] ?? '') ?></span>
            </div>

            <div>
                <label class="text-xs text-slate-400 block mb-1">ادھار رقم (Rs) *</label>
                <input type="number" name="amount" required min="1" placeholder="مثلاً: 2500" class="w-full bg-slate-950 border border-slate-700 text-rose-400 text-base font-black px-3 py-2 rounded-xl focus:border-rose-500 focus:outline-none font-mono">
            </div>

            <div>
                <label class="text-xs text-slate-400 block mb-1">سامان یا ادھار کی تفصیل *</label>
                <input type="text" name="description" required placeholder="مثلاً: سام سنگ چارجر + 1000 نقد ادھار" class="w-full bg-slate-950 border border-slate-700 text-white px-3 py-2 rounded-xl text-xs focus:border-rose-500 focus:outline-none">
            </div>

            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="text-xs text-slate-400 block mb-1">بل یا انوائس نمبر (اختیاری)</label>
                    <input type="text" name="invoice_no" placeholder="INV-1092" class="w-full bg-slate-950 border border-slate-700 text-white px-3 py-2 rounded-xl text-xs focus:border-rose-500 focus:outline-none font-mono">
                </div>
                <div>
                    <label class="text-xs text-slate-400 block mb-1">ذریعہ</label>
                    <select name="payment_method" class="w-full bg-slate-950 border border-slate-700 text-white px-3 py-2 rounded-xl text-xs focus:border-rose-500 focus:outline-none">
                        <option value="CASH">نقد (Cash)</option>
                        <option value="EASYPAISA">ایزی پیسہ (EasyPaisa)</option>
                        <option value="JAZZCASH">جاز کیش (JazzCash)</option>
                        <option value="BANK">بینک ٹرانسفر (Bank)</option>
                    </select>
                </div>
            </div>

            <div>
                <label class="text-xs text-slate-400 block mb-1">نوٹ یا حوالہ</label>
                <input type="text" name="notes" placeholder="کوئی خاص نوٹ..." class="w-full bg-slate-950 border border-slate-700 text-white px-3 py-2 rounded-xl text-xs focus:border-rose-500 focus:outline-none">
            </div>

            <div class="pt-2 flex items-center justify-end gap-2">
                <button type="button" onclick="closeModal('giveUdharModal')" class="px-4 py-2 bg-slate-800 hover:bg-slate-700 text-slate-300 rounded-xl text-xs font-bold">منسوخ</button>
                <button type="submit" class="px-5 py-2 bg-rose-600 hover:bg-rose-500 text-white rounded-xl text-xs font-bold shadow-lg shadow-rose-600/30">ادھار درج کریں</button>
            </div>
        </form>
    </div>
</div>

<!-- 4. Receive Payment Modal (وصولی کی) -->
<div id="receivePaymentModal" class="fixed inset-0 z-50 bg-black/80 backdrop-blur-sm hidden items-center justify-center p-4">
    <div class="bg-slate-900 border border-slate-800 rounded-2xl w-full max-w-md p-5 shadow-2xl space-y-4">
        <div class="flex items-center justify-between pb-3 border-b border-slate-800">
            <h3 class="font-bold text-white text-base flex items-center gap-2">
                <i data-lucide="arrow-up-right" class="w-5 h-5 text-emerald-400"></i>
                <span>وصولی اندراج (You Received / رقم ملی)</span>
            </h3>
            <button onclick="closeModal('receivePaymentModal')" class="text-slate-400 hover:text-white"><i data-lucide="x" class="w-5 h-5"></i></button>
        </div>

        <form method="POST" class="space-y-3">
            <input type="hidden" name="action" value="receive_payment">
            <input type="hidden" name="customer_id" value="<?= $selectedCustomer['id'] ?? '' ?>">

            <div class="p-3 bg-slate-950 rounded-xl border border-slate-800 flex items-center justify-between text-xs">
                <span class="text-slate-400">گاہک:</span>
                <span class="text-white font-bold"><?= htmlspecialchars($selectedCustomer['name'] ?? '') ?></span>
                <span class="text-rose-400 font-mono font-bold">بقایا: Rs. <?= number_format($currBal) ?></span>
            </div>

            <div>
                <label class="text-xs text-slate-400 block mb-1">وصول شدہ رقم (Rs) *</label>
                <input type="number" name="amount" required min="1" placeholder="مثلاً: 5000" class="w-full bg-slate-950 border border-slate-700 text-emerald-400 text-base font-black px-3 py-2 rounded-xl focus:border-emerald-500 focus:outline-none font-mono">
            </div>

            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="text-xs text-slate-400 block mb-1">ادائیگی کا طریقہ *</label>
                    <select name="payment_method" class="w-full bg-slate-950 border border-slate-700 text-white px-3 py-2 rounded-xl text-xs focus:border-emerald-500 focus:outline-none">
                        <option value="CASH">نقد کیش (Cash)</option>
                        <option value="EASYPAISA">ایزی پیسہ (EasyPaisa)</option>
                        <option value="JAZZCASH">جاز کیش (JazzCash)</option>
                        <option value="BANK">بینک ٹرانسفر (Bank)</option>
                    </select>
                </div>
                <div>
                    <label class="text-xs text-slate-400 block mb-1">TID یا رسید نمبر (اگر ہے)</label>
                    <input type="text" name="trx_ref" placeholder="TID: 8872194510" class="w-full bg-slate-950 border border-slate-700 text-white px-3 py-2 rounded-xl text-xs focus:border-emerald-500 focus:outline-none font-mono">
                </div>
            </div>

            <div>
                <label class="text-xs text-slate-400 block mb-1">تفصیل</label>
                <input type="text" name="description" placeholder="قسط ادا کی، دکان پر کیش دیا..." class="w-full bg-slate-950 border border-slate-700 text-white px-3 py-2 rounded-xl text-xs focus:border-emerald-500 focus:outline-none">
            </div>

            <div class="pt-2 flex items-center justify-end gap-2">
                <button type="button" onclick="closeModal('receivePaymentModal')" class="px-4 py-2 bg-slate-800 hover:bg-slate-700 text-slate-300 rounded-xl text-xs font-bold">منسوخ</button>
                <button type="submit" class="px-5 py-2 bg-emerald-600 hover:bg-emerald-500 text-white rounded-xl text-xs font-bold shadow-lg shadow-emerald-600/30">وصولی درج کریں</button>
            </div>
        </form>
    </div>
</div>

<script>
function openModal(id) {
    const modal = document.getElementById(id);
    if (modal) {
        modal.classList.remove('hidden');
        modal.classList.add('flex');
    }
}

function closeModal(id) {
    const modal = document.getElementById(id);
    if (modal) {
        modal.classList.add('hidden');
        modal.classList.remove('flex');
    }
}

function openGiveUdharModal() {
    openModal('giveUdharModal');
}

function openReceivePaymentModal() {
    openModal('receivePaymentModal');
}

function openEditCustomerModal() {
    <?php if ($selectedCustomer): ?>
    document.getElementById('edit_id').value = '<?= addslashes($selectedCustomer['id']) ?>';
    document.getElementById('edit_name').value = '<?= addslashes($selectedCustomer['name']) ?>';
    document.getElementById('edit_phone').value = '<?= addslashes($selectedCustomer['phone']) ?>';
    document.getElementById('edit_cnic').value = '<?= addslashes($selectedCustomer['cnic'] ?? '') ?>';
    document.getElementById('edit_address').value = '<?= addslashes($selectedCustomer['address'] ?? '') ?>';
    document.getElementById('edit_credit_limit').value = '<?= addslashes($selectedCustomer['credit_limit'] ?? '0') ?>';
    document.getElementById('edit_notes').value = '<?= addslashes($selectedCustomer['notes'] ?? '') ?>';
    openModal('editCustomerModal');
    <?php endif; ?>
}
</script>

<?php require_once __DIR__ . '/../backend/footer.php'; ?>
