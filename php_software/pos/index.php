<?php
$activeMenu = 'pos';
require_once __DIR__ . '/../backend/config.php';
$pageTitle = $isUrdu ? 'سیل اینڈ پوائنٹ آف سیل (POS)' : 'POS & Fast Checkout (سیل اینڈ پی او ایس)';
require_once __DIR__ . '/../backend/header.php';

$successMsg = '';
$errorMsg = '';
$printInvoice = null;
$today = date('Y-m-d');
$timeNow = date('H:i:s');

// ==========================================
// 1. Handle POS Checkout Form Submission
// ==========================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'checkout') {
    $customerName = trim($_POST['customer_name'] ?? 'واک ان کسٹمر');
    if (empty($customerName)) $customerName = 'واک ان کسٹمر';
    $customerPhone = trim($_POST['customer_phone'] ?? '');
    $paymentMethod = $_POST['payment_method'] ?? 'CASH';
    $discount = floatval($_POST['discount'] ?? 0);
    $cashReceived = floatval($_POST['cash_received'] ?? 0);
    $notes = trim($_POST['notes'] ?? '');
    $cartData = json_decode($_POST['cart_json'] ?? '[]', true);

    if (empty($cartData)) {
        $errorMsg = 'کارٹ خالی ہے! برائے مہربانی کوئی پروڈکٹ منتخب کریں۔';
    } else {
        try {
            $pdo->beginTransaction();
            $invoiceNo = 'INV-' . strtoupper(substr(md5(uniqid(mt_rand(), true)), 0, 6));
            $totalAmount = 0;
            $totalCost = 0;
            $itemsToStore = [];

            // Prepared statements
            $updateStockStmt = $pdo->prepare("UPDATE products SET stock = GREATEST(0, stock - :qty) WHERE id = :id");
            $getProductStmt = $pdo->prepare("SELECT units_json, stock, category FROM products WHERE id = :id");
            $updateUnitsStmt = $pdo->prepare("UPDATE products SET units_json = :units WHERE id = :id");

            foreach ($cartData as $item) {
                $pid = $item['productId'] ?? $item['id'];
                $qty = intval($item['quantity'] ?? 1);
                $salePrice = floatval($item['unitSalePrice'] ?? $item['salePrice']);
                $purchasePrice = floatval($item['purchasePrice'] ?? 0);
                $lineTotal = $salePrice * $qty;

                $totalAmount += $lineTotal;
                $totalCost += ($purchasePrice * $qty);

                $selectedUnitId = $item['selectedUnitId'] ?? null;
                $selectedImei = $item['selectedImei1'] ?? ($item['imei'] ?? '');
                $selectedColor = $item['selectedColor'] ?? '';
                $selectedRamStorage = $item['selectedRamStorage'] ?? '';

                $itemsToStore[] = [
                    'productId' => $pid,
                    'productName' => $item['name'] ?? $item['productName'],
                    'category' => $item['category'] ?? 'ACCESSORIES',
                    'quantity' => $qty,
                    'purchasePrice' => $purchasePrice,
                    'unitSalePrice' => $salePrice,
                    'totalSalePrice' => $lineTotal,
                    'selectedUnitId' => $selectedUnitId,
                    'selectedImei1' => $selectedImei,
                    'selectedColor' => $selectedColor,
                    'selectedRamStorage' => $selectedRamStorage,
                ];

                // Deduct stock count
                $updateStockStmt->execute([':qty' => $qty, ':id' => $pid]);

                // If specific IMEI unit was sold, mark it as SOLD in units_json
                if (!empty($selectedUnitId) || !empty($selectedImei)) {
                    $getProductStmt->execute([':id' => $pid]);
                    $pRow = $getProductStmt->fetch();
                    if ($pRow && !empty($pRow['units_json'])) {
                        $units = json_decode($pRow['units_json'], true);
                        if (is_array($units)) {
                            $modified = false;
                            foreach ($units as &$u) {
                                if ((!empty($selectedUnitId) && isset($u['id']) && $u['id'] == $selectedUnitId) ||
                                    (!empty($selectedImei) && isset($u['imei1']) && $u['imei1'] == $selectedImei)) {
                                    $u['status'] = 'SOLD';
                                    $u['soldInvoiceNo'] = $invoiceNo;
                                    $u['soldDate'] = $today;
                                    $modified = true;
                                    break;
                                }
                            }
                            if ($modified) {
                                $updateUnitsStmt->execute([
                                    ':units' => json_encode($units, JSON_UNESCAPED_UNICODE),
                                    ':id' => $pid
                                ]);
                            }
                        }
                    }
                }
            }

            $netAmount = max(0, $totalAmount - $discount);
            $profit = $netAmount - $totalCost;
            $saleId = 'sale-' . uniqid();
            $createdAt = round(microtime(true) * 1000);
            $changeDue = max(0, $cashReceived - $netAmount);

            // Insert into product_sales
            $saleSql = "INSERT INTO product_sales (
                id, invoice_no, date, time, customer_name, customer_phone,
                total_amount, discount, net_amount, total_purchase_cost,
                profit, payment_method, paid_amount, due_amount, items_json, notes, created_at
            ) VALUES (
                :id, :invoice_no, :date, :time, :customer_name, :customer_phone,
                :total_amount, :discount, :net_amount, :total_purchase_cost,
                :profit, :payment_method, :paid_amount, :due_amount, :items_json, :notes, :created_at
            )";
            $stmt = $pdo->prepare($saleSql);
            $stmt->execute([
                ':id' => $saleId,
                ':invoice_no' => $invoiceNo,
                ':date' => $today,
                ':time' => $timeNow,
                ':customer_name' => $customerName,
                ':customer_phone' => $customerPhone,
                ':total_amount' => $totalAmount,
                ':discount' => $discount,
                ':net_amount' => $netAmount,
                ':total_purchase_cost' => $totalCost,
                ':profit' => $profit,
                ':payment_method' => $paymentMethod,
                ':paid_amount' => ($paymentMethod === 'CREDIT' ? 0 : ($cashReceived > 0 ? min($cashReceived, $netAmount) : $netAmount)),
                ':due_amount' => ($paymentMethod === 'CREDIT' ? $netAmount : max(0, $netAmount - $cashReceived)),
                ':items_json' => json_encode($itemsToStore, JSON_UNESCAPED_UNICODE),
                ':notes' => $notes,
                ':created_at' => $createdAt
            ]);

            // Record into Cash / EasyPaisa / Credit Register (transactions table)
            $isDigital = in_array($paymentMethod, ['EASYPAISA', 'JAZZCASH', 'BANK']);
            $isCredit = ($paymentMethod === 'CREDIT');
            $trxId = 'trx-pos-' . $saleId;

            if ($isCredit) {
                // Customer Khata entry
                $trxSql = "INSERT INTO transactions (
                    id, date, time, type, customer_name, customer_phone,
                    cash_amount, fee_profit, payment_method, notes, created_at
                ) VALUES (
                    :id, :date, :time, 'EXPENSE', :customer_name, :customer_phone,
                    :cash_amt, 0, 'CREDIT', :notes, :created_at
                )";
                $stmtTrx = $pdo->prepare($trxSql);
                $stmtTrx->execute([
                    ':id' => $trxId,
                    ':date' => $today,
                    ':time' => $timeNow,
                    ':customer_name' => $customerName,
                    ':customer_phone' => $customerPhone,
                    ':cash_amt' => $netAmount,
                    ':notes' => "ادھار سیل (POS Udhar) - رسید #{$invoiceNo}",
                    ':created_at' => $createdAt
                ]);
            } else {
                // Cash or Digital POS sale
                $trxSql = "INSERT INTO transactions (
                    id, date, time, type, customer_name, customer_phone,
                    easy_paisa_amount, cash_amount, fee_profit, payment_method, notes, created_at
                ) VALUES (
                    :id, :date, :time, 'SELL_CASH', :customer_name, :customer_phone,
                    :ep_amt, :cash_amt, :fee_profit, :payment_method, :notes, :created_at
                )";
                $stmtTrx = $pdo->prepare($trxSql);
                $stmtTrx->execute([
                    ':id' => $trxId,
                    ':date' => $today,
                    ':time' => $timeNow,
                    ':customer_name' => $customerName,
                    ':customer_phone' => $customerPhone,
                    ':ep_amt' => $isDigital ? $netAmount : 0,
                    ':cash_amt' => !$isDigital ? $netAmount : 0,
                    ':fee_profit' => $profit,
                    ':payment_method' => $paymentMethod,
                    ':notes' => "POS فروخت #{$invoiceNo}",
                    ':created_at' => $createdAt
                ]);
            }

            $pdo->commit();
            $successMsg = "انوائس #{$invoiceNo} کامیابی سے تیار اور محفوظ ہو گئی!";
            $printInvoice = [
                'invoiceNo' => $invoiceNo,
                'customerName' => $customerName,
                'customerPhone' => $customerPhone,
                'date' => $today,
                'time' => $timeNow,
                'paymentMethod' => $paymentMethod,
                'totalAmount' => $totalAmount,
                'discount' => $discount,
                'netAmount' => $netAmount,
                'cashReceived' => $cashReceived,
                'changeDue' => $changeDue,
                'items' => $itemsToStore
            ];
        } catch (Exception $e) {
            $pdo->rollBack();
            $errorMsg = 'فروخت محفوظ کرنے میں خرابی: ' . $e->getMessage();
        }
    }
}

// ==========================================
// 2. Fetch Dashboard & POS Metrics
// ==========================================
$todaySalesAmt = 0;
$todaySalesCount = 0;
$todayProfitAmt = 0;
try {
    $todayMetrics = $pdo->query("SELECT 
        COUNT(*) as total_count,
        COALESCE(SUM(net_amount), 0) as total_sales,
        COALESCE(SUM(profit), 0) as total_profit
        FROM product_sales WHERE date = '$today'")->fetch();
    if ($todayMetrics) {
        $todaySalesCount = intval($todayMetrics['total_count']);
        $todaySalesAmt = floatval($todayMetrics['total_sales']);
        $todayProfitAmt = floatval($todayMetrics['total_profit']);
    }
} catch (Exception $e) {}

// Low stock items count (< 3)
$lowStockCount = 0;
try {
    $lowStockCount = intval($pdo->query("SELECT COUNT(*) FROM products WHERE stock <= 3")->fetchColumn());
} catch (Exception $e) {}

// Fetch active products with stock > 0
$products = [];
try {
    $products = $pdo->query("SELECT * FROM products WHERE stock > 0 ORDER BY name ASC")->fetchAll();
} catch (Exception $e) {
    $errorMsg = 'پروڈکٹس لوڈ کرنے میں مسئلہ: ' . $e->getMessage();
}

// Category counts for quick filter buttons
$categoryCounts = [
    'ALL' => count($products),
    'MOBILES' => 0,
    'CHARGERS' => 0,
    'EARPHONES' => 0,
    'COVERS' => 0,
    'PROTECTORS' => 0,
    'CABLES' => 0,
    'BATTERIES' => 0,
    'OTHER' => 0
];
foreach ($products as $p) {
    $cat = strtoupper($p['category'] ?? 'OTHER');
    if (isset($categoryCounts[$cat])) {
        $categoryCounts[$cat]++;
    } else {
        $categoryCounts['OTHER']++;
    }
}

// Fetch past customers for autocomplete
$pastCustomers = [];
try {
    $custRows = $pdo->query("SELECT DISTINCT customer_name, customer_phone FROM product_sales WHERE customer_name != 'واک ان کسٹمر' AND customer_name != '' LIMIT 30")->fetchAll();
    foreach ($custRows as $cr) {
        $pastCustomers[] = [
            'name' => $cr['customer_name'],
            'phone' => $cr['customer_phone'] ?? ''
        ];
    }
} catch (Exception $e) {}

// Fetch today's recent sales for bottom quick list / reprint
$recentSales = [];
try {
    $recentSales = $pdo->query("SELECT * FROM product_sales WHERE date = '$today' ORDER BY created_at DESC LIMIT 8")->fetchAll();
} catch (Exception $e) {}
?>

<!-- Thermal Receipt Print Styles -->
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
    .print-receipt-container {
        display: block !important;
        width: 80mm !important;
        margin: 0 auto !important;
        padding: 4mm !important;
        color: #000 !important;
    }
}
</style>

<!-- Alert Banners -->
<?php if (!empty($successMsg)): ?>
    <div class="mb-4 p-4 rounded-2xl bg-emerald-500/10 border border-emerald-500/30 text-emerald-300 flex items-center justify-between shadow-lg no-print animate-pulse">
        <div class="flex items-center gap-3">
            <div class="p-2 rounded-xl bg-emerald-500/20 text-emerald-400">
                <i data-lucide="check-circle-2" class="w-6 h-6"></i>
            </div>
            <div>
                <h4 class="font-bold text-sm text-white">فروخت کامیابی سے محفوظ ہو گئی!</h4>
                <p class="text-xs text-emerald-300/80"><?= htmlspecialchars($successMsg) ?></p>
            </div>
        </div>
        <?php if ($printInvoice): ?>
            <div class="flex items-center gap-2">
                <button onclick="openThermalModal()" class="px-4 py-2 bg-emerald-600 hover:bg-emerald-500 text-white rounded-xl text-xs font-bold flex items-center gap-2 shadow-lg transition-transform active:scale-95">
                    <i data-lucide="receipt" class="w-4 h-4"></i>
                    <span>تھرمل رسید دیکھیں</span>
                </button>
                <button onclick="window.print()" class="px-4 py-2 bg-slate-800 hover:bg-slate-700 text-slate-200 rounded-xl text-xs font-bold flex items-center gap-2 border border-slate-700">
                    <i data-lucide="printer" class="w-4 h-4"></i>
                    <span>پرنٹ کریں</span>
                </button>
            </div>
        <?php endif; ?>
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

<!-- ==========================================
     Top POS Metrics Bar
     ========================================== -->
<div class="grid grid-cols-2 lg:grid-cols-4 gap-3 sm:gap-4 mb-6 no-print">
    <!-- Today's POS Sales -->
    <div class="bg-slate-900 border border-slate-800 p-4 rounded-2xl flex items-center justify-between shadow-md hover:border-emerald-500/40 transition-colors">
        <div>
            <span class="text-[11px] sm:text-xs text-slate-400 font-medium">آج کی کل POS فروخت</span>
            <h3 class="text-lg sm:text-2xl font-black text-white mt-1">Rs. <?= number_format($todaySalesAmt) ?></h3>
        </div>
        <div class="w-11 h-11 rounded-xl bg-emerald-500/10 text-emerald-400 flex items-center justify-center border border-emerald-500/20">
            <i data-lucide="shopping-bag" class="w-5 h-5"></i>
        </div>
    </div>

    <!-- Total Invoices Today -->
    <div class="bg-slate-900 border border-slate-800 p-4 rounded-2xl flex items-center justify-between shadow-md hover:border-cyan-500/40 transition-colors">
        <div>
            <span class="text-[11px] sm:text-xs text-slate-400 font-medium">آج کی کل انوائسز</span>
            <h3 class="text-lg sm:text-2xl font-black text-cyan-400 mt-1"><?= $todaySalesCount ?> <span class="text-xs font-normal text-slate-400">انوائسز</span></h3>
        </div>
        <div class="w-11 h-11 rounded-xl bg-cyan-500/10 text-cyan-400 flex items-center justify-center border border-cyan-500/20">
            <i data-lucide="receipt" class="w-5 h-5"></i>
        </div>
    </div>

    <!-- Today's Estimated Profit -->
    <div class="bg-slate-900 border border-slate-800 p-4 rounded-2xl flex items-center justify-between shadow-md hover:border-teal-500/40 transition-colors">
        <div>
            <span class="text-[11px] sm:text-xs text-slate-400 font-medium">آج کا تخمینی منافع</span>
            <h3 class="text-lg sm:text-2xl font-black text-teal-400 mt-1">Rs. <?= number_format($todayProfitAmt) ?></h3>
        </div>
        <div class="w-11 h-11 rounded-xl bg-teal-500/10 text-teal-400 flex items-center justify-center border border-teal-500/20">
            <i data-lucide="trending-up" class="w-5 h-5"></i>
        </div>
    </div>

    <!-- Low Stock Alert -->
    <div class="bg-slate-900 border border-slate-800 p-4 rounded-2xl flex items-center justify-between shadow-md hover:border-amber-500/40 transition-colors">
        <div>
            <span class="text-[11px] sm:text-xs text-slate-400 font-medium">کم اسٹاک والی آئٹمز</span>
            <h3 class="text-lg sm:text-2xl font-black <?= $lowStockCount > 0 ? 'text-amber-400' : 'text-slate-300' ?> mt-1"><?= $lowStockCount ?> <span class="text-xs font-normal text-slate-400">آئٹمز</span></h3>
        </div>
        <div class="w-11 h-11 rounded-xl bg-amber-500/10 text-amber-400 flex items-center justify-center border border-amber-500/20">
            <i data-lucide="alert-circle" class="w-5 h-5"></i>
        </div>
    </div>
</div>

<!-- ==========================================
     Main POS Workspace (Two Columns)
     ========================================== -->
<div class="grid grid-cols-1 lg:grid-cols-12 gap-6 no-print">
    
    <!-- LEFT COLUMN: Catalog, Categories, Search, Barcode (7 Cols) -->
    <div class="lg:col-span-7 space-y-4">
        
        <!-- Search, Barcode Scan, and View Toggle Header -->
        <div class="bg-slate-900 border border-slate-800 p-4 rounded-2xl shadow-lg space-y-3">
            <div class="flex flex-col sm:flex-row items-stretch sm:items-center gap-3">
                <!-- Search Box -->
                <div class="relative flex-1">
                    <i data-lucide="search" class="w-5 h-5 absolute right-3.5 top-1/2 transform -translate-y-1/2 text-slate-400 pointer-events-none"></i>
                    <input type="text" id="posSearchInput" 
                           placeholder="پروڈکٹ کا نام، ماڈل، بارکوڈ یا IMEI اسکین یا سرچ کریں... (Enter دبائیں)" 
                           autocomplete="off"
                           class="w-full bg-slate-950 border border-slate-700 text-white pr-11 pl-10 py-2.5 rounded-xl focus:border-emerald-500 focus:ring-1 focus:ring-emerald-500 focus:outline-none text-xs sm:text-sm font-medium transition-all placeholder:text-slate-500">
                    <button id="clearSearchBtn" onclick="clearSearch()" class="hidden absolute left-3 top-1/2 transform -translate-y-1/2 text-slate-400 hover:text-white">
                        <i data-lucide="x" class="w-4 h-4"></i>
                    </button>
                </div>

                <!-- Hardware Barcode Status Badge -->
                <div class="flex items-center gap-2 shrink-0">
                    <div class="flex items-center gap-2 px-3 py-2 bg-slate-950 border border-slate-800 rounded-xl text-xs text-slate-300">
                        <span class="w-2 h-2 rounded-full bg-emerald-400 animate-ping"></span>
                        <i data-lucide="scan-barcode" class="w-4 h-4 text-emerald-400"></i>
                        <span class="hidden sm:inline">بارکوڈ اسکینر:</span>
                        <span class="font-bold text-emerald-400">آن</span>
                    </div>

                    <!-- View Switcher (List vs Grid) -->
                    <div class="flex items-center bg-slate-950 p-1 rounded-xl border border-slate-800">
                        <button type="button" id="btnViewList" onclick="setViewMode('list')" title="لِسٹ ویو (Dense List)" class="p-1.5 rounded-lg text-white bg-slate-800 transition-colors">
                            <i data-lucide="list" class="w-4 h-4"></i>
                        </button>
                        <button type="button" id="btnViewGrid" onclick="setViewMode('grid')" title="گرڈ کارڈ ویو (Grid Cards)" class="p-1.5 rounded-lg text-slate-400 hover:text-white transition-colors">
                            <i data-lucide="layout-grid" class="w-4 h-4"></i>
                        </button>
                    </div>
                </div>
            </div>

            <!-- Category Pills Bar -->
            <div class="flex items-center gap-1.5 overflow-x-auto pb-1 text-xs no-scrollbar">
                <button type="button" onclick="filterCategory('ALL')" class="cat-pill active px-3.5 py-1.5 rounded-xl font-bold bg-emerald-600 text-white shadow-md shrink-0 transition-all flex items-center gap-1.5" data-cat="ALL">
                    <span>تمام آئٹمز</span>
                    <span class="text-[10px] px-1.5 py-0.2 rounded-full bg-emerald-800/80 text-emerald-200"><?= $categoryCounts['ALL'] ?></span>
                </button>
                <button type="button" onclick="filterCategory('MOBILES')" class="cat-pill px-3 py-1.5 rounded-xl font-medium bg-slate-950 text-slate-300 hover:bg-slate-800 border border-slate-800 shrink-0 transition-all flex items-center gap-1.5" data-cat="MOBILES">
                    <span>📱 موبائل فونز</span>
                    <span class="text-[10px] px-1.5 py-0.2 rounded-full bg-slate-800 text-slate-400"><?= $categoryCounts['MOBILES'] ?></span>
                </button>
                <button type="button" onclick="filterCategory('CHARGERS')" class="cat-pill px-3 py-1.5 rounded-xl font-medium bg-slate-950 text-slate-300 hover:bg-slate-800 border border-slate-800 shrink-0 transition-all flex items-center gap-1.5" data-cat="CHARGERS">
                    <span>⚡ چارجرز</span>
                    <span class="text-[10px] px-1.5 py-0.2 rounded-full bg-slate-800 text-slate-400"><?= $categoryCounts['CHARGERS'] ?></span>
                </button>
                <button type="button" onclick="filterCategory('EARPHONES')" class="cat-pill px-3 py-1.5 rounded-xl font-medium bg-slate-950 text-slate-300 hover:bg-slate-800 border border-slate-800 shrink-0 transition-all flex items-center gap-1.5" data-cat="EARPHONES">
                    <span>🎧 ائیر فونز / بڈز</span>
                    <span class="text-[10px] px-1.5 py-0.2 rounded-full bg-slate-800 text-slate-400"><?= $categoryCounts['EARPHONES'] ?></span>
                </button>
                <button type="button" onclick="filterCategory('COVERS')" class="cat-pill px-3 py-1.5 rounded-xl font-medium bg-slate-950 text-slate-300 hover:bg-slate-800 border border-slate-800 shrink-0 transition-all flex items-center gap-1.5" data-cat="COVERS">
                    <span>🛡️ کورز</span>
                    <span class="text-[10px] px-1.5 py-0.2 rounded-full bg-slate-800 text-slate-400"><?= $categoryCounts['COVERS'] ?></span>
                </button>
                <button type="button" onclick="filterCategory('PROTECTORS')" class="cat-pill px-3 py-1.5 rounded-xl font-medium bg-slate-950 text-slate-300 hover:bg-slate-800 border border-slate-800 shrink-0 transition-all flex items-center gap-1.5" data-cat="PROTECTORS">
                    <span>📱 پروٹیکٹرز</span>
                    <span class="text-[10px] px-1.5 py-0.2 rounded-full bg-slate-800 text-slate-400"><?= $categoryCounts['PROTECTORS'] ?></span>
                </button>
                <button type="button" onclick="filterCategory('CABLES')" class="cat-pill px-3 py-1.5 rounded-xl font-medium bg-slate-950 text-slate-300 hover:bg-slate-800 border border-slate-800 shrink-0 transition-all flex items-center gap-1.5" data-cat="CABLES">
                    <span>🔌 کیبلز</span>
                    <span class="text-[10px] px-1.5 py-0.2 rounded-full bg-slate-800 text-slate-400"><?= $categoryCounts['CABLES'] ?></span>
                </button>
                <button type="button" onclick="filterCategory('BATTERIES')" class="cat-pill px-3 py-1.5 rounded-xl font-medium bg-slate-950 text-slate-300 hover:bg-slate-800 border border-slate-800 shrink-0 transition-all flex items-center gap-1.5" data-cat="BATTERIES">
                    <span>🔋 بیٹریاں</span>
                    <span class="text-[10px] px-1.5 py-0.2 rounded-full bg-slate-800 text-slate-400"><?= $categoryCounts['BATTERIES'] ?></span>
                </button>
            </div>
        </div>

        <!-- Products Container (List or Grid View) -->
        <div id="productsWrapper" class="space-y-2 max-h-[620px] overflow-y-auto pr-1">
            <?php if (empty($products)): ?>
                <div class="bg-slate-900 border border-slate-800 rounded-2xl p-12 text-center text-slate-400">
                    <i data-lucide="package-x" class="w-12 h-12 mx-auto mb-3 text-slate-600"></i>
                    <h4 class="text-base font-bold text-white mb-1">کوئی پروڈکٹ موجود نہیں ہے</h4>
                    <p class="text-xs text-slate-500 mb-4">پہلے اسٹاک انوینٹری میں سامان درج کریں تاکہ یہاں فروخت کیا جا سکے</p>
                    <a href="../inventory/index.php" class="inline-flex items-center gap-2 px-4 py-2 bg-emerald-600 hover:bg-emerald-500 text-white text-xs font-bold rounded-xl shadow">
                        <i data-lucide="plus" class="w-4 h-4"></i>
                        <span>اسٹاک شامل کریں</span>
                    </a>
                </div>
            <?php else: ?>
                <?php foreach ($products as $p): 
                    $units = !empty($p['units_json']) ? json_decode($p['units_json'], true) : [];
                    $hasUnits = is_array($units) && count($units) > 0;
                    $isMobile = strtoupper($p['category']) === 'MOBILES';
                    $payloadJson = htmlspecialchars(json_encode([
                        'id' => $p['id'],
                        'name' => $p['name'],
                        'category' => $p['category'],
                        'salePrice' => floatval($p['sale_price']),
                        'purchasePrice' => floatval($p['purchase_price']),
                        'stock' => intval($p['stock']),
                        'brand' => $p['brand_or_model'] ?? '',
                        'imei' => $p['imei_or_serial'] ?? '',
                        'sku' => $p['sku'] ?? '',
                        'color' => $p['color'] ?? '',
                        'ramStorage' => $p['ram_storage'] ?? '',
                        'hasUnits' => $hasUnits,
                        'units' => $units
                    ]), ENT_QUOTES, 'UTF-8');
                ?>
                    <!-- Product Item (Can render in dense list mode or grid mode via CSS) -->
                    <div class="product-item bg-slate-900 hover:bg-slate-850 border border-slate-800 hover:border-emerald-500/50 p-3 sm:p-3.5 rounded-2xl transition-all cursor-pointer select-none group"
                         data-id="<?= $p['id'] ?>"
                         data-name="<?= strtolower(htmlspecialchars($p['name'])) ?>"
                         data-brand="<?= strtolower(htmlspecialchars($p['brand_or_model'] ?? '')) ?>"
                         data-sku="<?= strtolower(htmlspecialchars($p['sku'] ?? '')) ?>"
                         data-imei="<?= strtolower(htmlspecialchars($p['imei_or_serial'] ?? '')) ?>"
                         data-category="<?= strtoupper($p['category']) ?>"
                         onclick="onProductItemClick(<?= $payloadJson ?>)">
                        
                        <!-- List View Row Layout -->
                        <div class="list-layout flex items-center justify-between gap-3">
                            <div class="flex items-center gap-3 min-w-0 flex-1">
                                <!-- Category Icon or Image Thumbnail -->
                                <div class="w-10 h-10 rounded-xl bg-slate-950 border border-slate-800 flex items-center justify-center shrink-0 text-emerald-400 group-hover:scale-105 transition-transform">
                                    <?php if ($isMobile): ?>
                                        <i data-lucide="smartphone" class="w-5 h-5"></i>
                                    <?php elseif (strtoupper($p['category']) === 'CHARGERS'): ?>
                                        <i data-lucide="zap" class="w-5 h-5 text-amber-400"></i>
                                    <?php elseif (strtoupper($p['category']) === 'EARPHONES'): ?>
                                        <i data-lucide="headphones" class="w-5 h-5 text-cyan-400"></i>
                                    <?php else: ?>
                                        <i data-lucide="package" class="w-5 h-5 text-slate-400"></i>
                                    <?php endif; ?>
                                </div>
                                <div class="min-w-0 flex-1">
                                    <div class="flex items-center gap-2">
                                        <h4 class="font-bold text-white text-xs sm:text-sm truncate group-hover:text-emerald-400 transition-colors"><?= htmlspecialchars($p['name']) ?></h4>
                                        <span class="text-[10px] px-2 py-0.5 rounded-md bg-slate-950 text-slate-400 border border-slate-800 shrink-0 font-medium"><?= htmlspecialchars($p['category']) ?></span>
                                    </div>
                                    <div class="flex items-center gap-2 mt-0.5 text-[11px] text-slate-400">
                                        <?php if (!empty($p['brand_or_model'])): ?>
                                            <span><?= htmlspecialchars($p['brand_or_model']) ?></span>
                                        <?php endif; ?>
                                        <?php if (!empty($p['imei_or_serial'])): ?>
                                            <span class="text-cyan-400 font-mono text-[10px] bg-cyan-950/40 px-1.5 py-0.5 rounded border border-cyan-800/40">IMEI: <?= htmlspecialchars($p['imei_or_serial']) ?></span>
                                        <?php endif; ?>
                                        <?php if ($hasUnits): ?>
                                            <span class="text-amber-400 font-bold text-[10px] bg-amber-950/40 px-1.5 py-0.5 rounded border border-amber-800/40">ملٹی IMEI یونٹس (<?= count($units) ?>)</span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>

                            <div class="flex items-center gap-4 shrink-0">
                                <!-- Stock Level Badge -->
                                <div class="text-left">
                                    <span class="text-[10px] block text-slate-500 font-medium">اسٹاک</span>
                                    <span class="text-xs font-bold font-mono <?= $p['stock'] <= 3 ? 'text-amber-400' : 'text-slate-300' ?>"><?= $p['stock'] ?> دانے</span>
                                </div>
                                <!-- Sale Price -->
                                <div class="text-left min-w-[85px]">
                                    <span class="text-[10px] block text-slate-500 font-medium">فروخت قیمت</span>
                                    <span class="text-sm sm:text-base font-black text-emerald-400 font-mono">Rs. <?= number_format($p['sale_price']) ?></span>
                                </div>
                                <!-- Add Button -->
                                <button type="button" class="p-2 rounded-xl bg-slate-950 hover:bg-emerald-600 text-slate-300 hover:text-white border border-slate-800 hover:border-emerald-500 transition-all active:scale-95 shadow">
                                    <i data-lucide="plus" class="w-4 h-4"></i>
                                </button>
                            </div>
                        </div>

                        <!-- Grid View Content Layout (Visible when parent has class .grid-mode) -->
                        <div class="grid-layout hidden flex-col justify-between h-full space-y-3">
                            <div>
                                <div class="flex items-center justify-between mb-2">
                                    <span class="text-[10px] px-2 py-0.5 rounded-full bg-slate-950 text-slate-400 border border-slate-800 font-bold"><?= htmlspecialchars($p['category']) ?></span>
                                    <span class="text-[11px] font-mono font-bold <?= $p['stock'] <= 3 ? 'text-amber-400' : 'text-slate-400' ?>"><?= $p['stock'] ?> باقی</span>
                                </div>
                                <h4 class="font-bold text-white text-xs sm:text-sm line-clamp-2 leading-snug group-hover:text-emerald-400 transition-colors"><?= htmlspecialchars($p['name']) ?></h4>
                                <?php if (!empty($p['brand_or_model'])): ?>
                                    <p class="text-[11px] text-slate-400 mt-1"><?= htmlspecialchars($p['brand_or_model']) ?></p>
                                <?php endif; ?>
                                <?php if (!empty($p['imei_or_serial'])): ?>
                                    <p class="text-[10px] text-cyan-400 font-mono mt-1 truncate">IMEI: <?= htmlspecialchars($p['imei_or_serial']) ?></p>
                                <?php endif; ?>
                            </div>
                            <div class="pt-2 border-t border-slate-800 flex items-center justify-between">
                                <span class="text-sm font-black text-emerald-400 font-mono">Rs. <?= number_format($p['sale_price']) ?></span>
                                <span class="p-1.5 rounded-lg bg-emerald-600/20 text-emerald-400 border border-emerald-500/30 group-hover:bg-emerald-600 group-hover:text-white transition-all">
                                    <i data-lucide="plus" class="w-3.5 h-3.5"></i>
                                </span>
                            </div>
                        </div>

                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

    </div>

    <!-- RIGHT COLUMN: Active Cart & Fast Checkout Panel (5 Cols) -->
    <div class="lg:col-span-5 bg-slate-900 border border-slate-800 rounded-2xl p-4 sm:p-5 shadow-xl flex flex-col justify-between sticky top-20">
        
        <div>
            <!-- Cart Header -->
            <div class="flex items-center justify-between pb-3.5 border-b border-slate-800 mb-3">
                <div class="flex items-center gap-2">
                    <div class="w-8 h-8 rounded-xl bg-emerald-500/10 text-emerald-400 flex items-center justify-center border border-emerald-500/20">
                        <i data-lucide="shopping-cart" class="w-4 h-4"></i>
                    </div>
                    <div>
                        <h3 class="font-bold text-white text-sm sm:text-base flex items-center gap-2">
                            <span>فروخت کارٹ (Sale Cart)</span>
                            <span id="cartCountBadge" class="text-[11px] px-2 py-0.5 rounded-full bg-emerald-600 text-white font-mono">0</span>
                        </h3>
                    </div>
                </div>
                <button type="button" onclick="clearCart()" class="text-xs text-rose-400 hover:text-rose-300 font-medium hover:underline flex items-center gap-1">
                    <i data-lucide="trash-2" class="w-3.5 h-3.5"></i>
                    <span>کارٹ خالی کریں</span>
                </button>
            </div>

            <!-- Cart Items List Container -->
            <div id="cartItemsList" class="space-y-2.5 max-h-[260px] overflow-y-auto pr-1 mb-4">
                <div class="p-8 text-center text-slate-500 bg-slate-950/60 rounded-xl border border-dashed border-slate-800">
                    <i data-lucide="shopping-bag" class="w-10 h-10 mx-auto mb-2 text-slate-700"></i>
                    <p class="text-xs font-bold text-slate-400">کارٹ خالی ہے</p>
                    <p class="text-[11px] text-slate-500 mt-0.5">بائیں طرف سے پروڈکٹ پر کلک کریں یا بارکوڈ اسکین کریں</p>
                </div>
            </div>
        </div>

        <!-- Checkout Form -->
        <form method="POST" id="checkoutForm" onsubmit="return validateCheckout()" class="border-t border-slate-800 pt-3.5 space-y-3">
            <input type="hidden" name="action" value="checkout">
            <input type="hidden" name="cart_json" id="cartJsonInput" value="[]">

            <!-- Customer Details -->
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-2">
                <div>
                    <label class="text-[11px] font-bold text-slate-400 block mb-1">گاہک / کسٹمر کا نام</label>
                    <input type="text" name="customer_name" id="customerNameInput" list="customersDatalist" 
                           placeholder="واک ان کسٹمر" value="واک ان کسٹمر"
                           class="w-full bg-slate-950 border border-slate-700 text-white px-3 py-2 rounded-xl text-xs focus:border-emerald-500 focus:outline-none">
                    <datalist id="customersDatalist">
                        <?php foreach ($pastCustomers as $cust): ?>
                            <option value="<?= htmlspecialchars($cust['name']) ?>"><?= htmlspecialchars($cust['phone']) ?></option>
                        <?php endforeach; ?>
                    </datalist>
                </div>
                <div>
                    <label class="text-[11px] font-bold text-slate-400 block mb-1">موبائل فون نمبر (WhatsApp)</label>
                    <input type="text" name="customer_phone" id="customerPhoneInput" placeholder="03001234567" 
                           class="w-full bg-slate-950 border border-slate-700 text-white px-3 py-2 rounded-xl text-xs focus:border-emerald-500 focus:outline-none font-mono">
                </div>
            </div>

            <!-- Payment Method & Discount -->
            <div class="grid grid-cols-2 gap-2">
                <div>
                    <label class="text-[11px] font-bold text-slate-400 block mb-1">ادائیگی کا طریقہ</label>
                    <select name="payment_method" id="paymentMethodSelect" onchange="onPaymentMethodChange()" 
                            class="w-full bg-slate-950 border border-slate-700 text-white px-3 py-2 rounded-xl text-xs focus:border-emerald-500 focus:outline-none font-medium">
                        <option value="CASH">💵 نقد رقم (Cash in Hand)</option>
                        <option value="EASYPAISA">🟢 ایزی پیسہ (EasyPaisa)</option>
                        <option value="JAZZCASH">🔴 جاز کیش (JazzCash)</option>
                        <option value="BANK">🏦 بینک اکاؤنٹ (Bank Transfer)</option>
                        <option value="CREDIT">📝 ادھار کھاتہ (Khata / Credit)</option>
                    </select>
                </div>
                <div>
                    <label class="text-[11px] font-bold text-slate-400 block mb-1">رعایت / ڈسکاؤنٹ (روپے)</label>
                    <input type="number" name="discount" id="discountInput" value="0" min="0" oninput="calculateTotals()" 
                           class="w-full bg-slate-950 border border-slate-700 text-white px-3 py-2 rounded-xl text-xs focus:border-emerald-500 focus:outline-none font-mono">
                </div>
            </div>

            <!-- Cash Tendered & Change Return Calculator -->
            <div id="cashTenderedSection" class="p-2.5 bg-slate-950/80 rounded-xl border border-slate-800 space-y-2">
                <div class="flex items-center justify-between">
                    <label class="text-[11px] font-bold text-slate-300">گاہک سے وصول شدہ کیش (Cash Received):</label>
                    <div class="w-36">
                        <input type="number" name="cash_received" id="cashReceivedInput" placeholder="0" min="0" oninput="calculateChangeReturn()"
                               class="w-full bg-slate-900 border border-slate-700 text-emerald-400 font-black px-2.5 py-1.5 rounded-lg text-xs text-left font-mono focus:border-emerald-500 focus:outline-none">
                    </div>
                </div>
                <!-- Quick Tender Helper Buttons -->
                <div class="flex items-center gap-1.5 text-[10px]">
                    <span class="text-slate-500">فوری رقم:</span>
                    <button type="button" onclick="setTenderExact()" class="px-2 py-0.5 bg-slate-800 hover:bg-slate-700 text-slate-300 rounded font-medium">برابر بل</button>
                    <button type="button" onclick="addTender(500)" class="px-2 py-0.5 bg-slate-800 hover:bg-slate-700 text-slate-300 rounded font-mono">+500</button>
                    <button type="button" onclick="addTender(1000)" class="px-2 py-0.5 bg-slate-800 hover:bg-slate-700 text-slate-300 rounded font-mono">+1000</button>
                    <button type="button" onclick="addTender(5000)" class="px-2 py-0.5 bg-slate-800 hover:bg-slate-700 text-slate-300 rounded font-mono">+5000</button>
                </div>
                <!-- Change Return Display -->
                <div class="flex items-center justify-between pt-1 border-t border-slate-800/80 text-xs">
                    <span class="text-slate-400">کسٹمر کو بقایا واپسی (Change Return):</span>
                    <span id="changeReturnDisplay" class="font-black text-cyan-400 font-mono text-sm">Rs. 0</span>
                </div>
            </div>

            <!-- Bill Totals Summary -->
            <div class="bg-slate-950 p-3.5 rounded-xl border border-slate-800 space-y-1.5 text-xs">
                <div class="flex justify-between text-slate-400">
                    <span>کل رقم (Subtotal):</span>
                    <span id="subtotalDisplay" class="font-mono text-slate-200">Rs. 0</span>
                </div>
                <div class="flex justify-between text-rose-400">
                    <span>رعایت (Discount):</span>
                    <span id="discountDisplay" class="font-mono">- Rs. 0</span>
                </div>
                <div class="flex justify-between text-white font-black text-base pt-1.5 border-t border-slate-800">
                    <span>خالص بل (Net Payable):</span>
                    <span id="netDisplay" class="text-emerald-400 font-mono">Rs. 0</span>
                </div>
                <div class="flex justify-between text-[11px] text-teal-400 pt-1 border-t border-dashed border-slate-800">
                    <span>متوقع تخمینی منافع (Estimated Profit):</span>
                    <span id="profitDisplay" class="font-mono font-bold">Rs. 0</span>
                </div>
            </div>

            <!-- Complete Sale Button -->
            <button type="submit" id="submitSaleBtn" class="w-full py-3.5 bg-gradient-to-r from-emerald-600 to-teal-600 hover:from-emerald-500 hover:to-teal-500 text-white font-black text-sm rounded-xl shadow-lg transition-all flex items-center justify-center gap-2 active:scale-98">
                <i data-lucide="check-check" class="w-5 h-5"></i>
                <span>فروخت مکمل کریں اور پرنٹ کریں</span>
            </button>
        </form>

    </div>
</div>

<!-- ==========================================
     Recent Invoices Today Section (Quick Reprint)
     ========================================== -->
<div class="mt-8 bg-slate-900 border border-slate-800 rounded-2xl p-5 shadow-lg no-print">
    <div class="flex items-center justify-between mb-4 pb-3 border-b border-slate-800">
        <div class="flex items-center gap-2.5">
            <div class="p-2 rounded-xl bg-cyan-500/10 text-cyan-400 border border-cyan-500/20">
                <i data-lucide="history" class="w-5 h-5"></i>
            </div>
            <div>
                <h3 class="font-bold text-white text-base">آج کی حالیہ فروخت شدہ انوائسز (Recent Invoices)</h3>
                <p class="text-xs text-slate-400">کسی بھی پرانی انوائس کو دوبارہ دیکھنے یا تھرمل رسید پرنٹ کرنے کیلئے کلک کریں</p>
            </div>
        </div>
        <a href="../sales_ledger/index.php" class="text-xs text-emerald-400 hover:underline flex items-center gap-1 font-bold">
            <span>مکمل سیلز لیجر دیکھیں</span>
            <i data-lucide="arrow-left" class="w-3.5 h-3.5"></i>
        </a>
    </div>

    <?php if (empty($recentSales)): ?>
        <p class="text-xs text-slate-500 text-center py-6">آج ابھی تک کوئی سیل انوائس درج نہیں ہوئی ہے</p>
    <?php else: ?>
        <div class="overflow-x-auto">
            <table class="w-full text-right text-xs">
                <thead>
                    <tr class="text-slate-400 border-b border-slate-800">
                        <th class="py-2.5 pr-2">انوائس نمبر</th>
                        <th class="py-2.5">وقت</th>
                        <th class="py-2.5">کسٹمر</th>
                        <th class="py-2.5">فون</th>
                        <th class="py-2.5">ادائیگی طریقہ</th>
                        <th class="py-2.5">کل رقم</th>
                        <th class="py-2.5">منافع</th>
                        <th class="py-2.5 text-center">ایکشن</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-800/60">
                    <?php foreach ($recentSales as $sale): 
                        $saleItems = json_decode($sale['items_json'] ?? '[]', true);
                        $saleDataJson = htmlspecialchars(json_encode([
                            'invoiceNo' => $sale['invoice_no'],
                            'customerName' => $sale['customer_name'],
                            'customerPhone' => $sale['customer_phone'] ?? '',
                            'date' => $sale['date'],
                            'time' => $sale['time'],
                            'paymentMethod' => $sale['payment_method'],
                            'totalAmount' => floatval($sale['total_amount']),
                            'discount' => floatval($sale['discount']),
                            'netAmount' => floatval($sale['net_amount']),
                            'cashReceived' => floatval($sale['paid_amount'] ?? $sale['net_amount']),
                            'changeDue' => 0,
                            'items' => $saleItems
                        ]), ENT_QUOTES, 'UTF-8');
                    ?>
                        <tr class="hover:bg-slate-800/50 transition-colors">
                            <td class="py-2.5 pr-2 font-mono font-bold text-cyan-400"><?= htmlspecialchars($sale['invoice_no']) ?></td>
                            <td class="py-2.5 font-mono text-slate-400"><?= htmlspecialchars($sale['time']) ?></td>
                            <td class="py-2.5 font-bold text-white"><?= htmlspecialchars($sale['customer_name']) ?></td>
                            <td class="py-2.5 font-mono text-slate-400"><?= htmlspecialchars($sale['customer_phone'] ?: '-') ?></td>
                            <td class="py-2.5">
                                <span class="px-2 py-0.5 rounded-full text-[10px] font-bold <?= $sale['payment_method'] === 'CASH' ? 'bg-emerald-950 text-emerald-300 border border-emerald-800' : 'bg-cyan-950 text-cyan-300 border border-cyan-800' ?>">
                                    <?= htmlspecialchars($sale['payment_method']) ?>
                                </span>
                            </td>
                            <td class="py-2.5 font-mono font-bold text-white">Rs. <?= number_format($sale['net_amount']) ?></td>
                            <td class="py-2.5 font-mono font-bold text-teal-400">Rs. <?= number_format($sale['profit']) ?></td>
                            <td class="py-2.5 text-center">
                                <button type="button" onclick="viewHistoricalInvoice(<?= $saleDataJson ?>)" class="px-3 py-1 bg-slate-800 hover:bg-emerald-600 hover:text-white text-slate-300 rounded-lg text-xs font-bold transition-all border border-slate-700 flex items-center gap-1 mx-auto">
                                    <i data-lucide="receipt" class="w-3.5 h-3.5"></i>
                                    <span>رسید دیکھیں</span>
                                </button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<!-- ==========================================
     Modal 1: Multi-Unit / IMEI Selector Modal
     ========================================== -->
<div id="unitSelectorModal" class="fixed inset-0 z-50 bg-black/80 backdrop-blur-sm hidden items-center justify-center p-4">
    <div class="bg-slate-900 border border-slate-800 rounded-2xl w-full max-w-lg overflow-hidden shadow-2xl animate-in fade-in zoom-in duration-200">
        <div class="p-4 border-b border-slate-800 flex items-center justify-between bg-slate-950/60">
            <div class="flex items-center gap-2">
                <div class="w-8 h-8 rounded-xl bg-cyan-500/10 text-cyan-400 flex items-center justify-center border border-cyan-500/20">
                    <i data-lucide="smartphone" class="w-4 h-4"></i>
                </div>
                <div>
                    <h3 class="font-bold text-white text-sm" id="modalProductName">موبائل یونٹ / IMEI منتخب کریں</h3>
                    <p class="text-[11px] text-slate-400" id="modalProductSubtitle">فروخت کیلئے مطلوبہ IMEI منتخب کریں</p>
                </div>
            </div>
            <button type="button" onclick="closeUnitModal()" class="text-slate-400 hover:text-white p-1 rounded-lg hover:bg-slate-800">
                <i data-lucide="x" class="w-5 h-5"></i>
            </button>
        </div>

        <div class="p-4 max-h-[380px] overflow-y-auto space-y-2" id="modalUnitsListContainer">
            <!-- Dynamically populated via Javascript -->
        </div>

        <div class="p-3 border-t border-slate-800 bg-slate-950/40 flex justify-end">
            <button type="button" onclick="closeUnitModal()" class="px-4 py-2 bg-slate-800 hover:bg-slate-700 text-slate-300 text-xs font-bold rounded-xl">
                بند کریں
            </button>
        </div>
    </div>
</div>

<!-- ==========================================
     Modal 2: Thermal Receipt Preview Modal
     ========================================== -->
<div id="thermalReceiptModal" class="fixed inset-0 z-50 bg-black/80 backdrop-blur-sm hidden items-center justify-center p-4 no-print">
    <div class="bg-slate-900 border border-slate-800 rounded-2xl w-full max-w-md overflow-hidden shadow-2xl flex flex-col max-h-[90vh]">
        <!-- Modal Top Bar -->
        <div class="p-3.5 border-b border-slate-800 flex items-center justify-between bg-slate-950">
            <div class="flex items-center gap-2">
                <i data-lucide="printer" class="w-4 h-4 text-emerald-400"></i>
                <span class="font-bold text-sm text-white">تھرمل رسید پری ویو (80mm Thermal Receipt)</span>
            </div>
            <button type="button" onclick="closeThermalModal()" class="text-slate-400 hover:text-white p-1 rounded-lg">
                <i data-lucide="x" class="w-5 h-5"></i>
            </button>
        </div>

        <!-- Scrollable Receipt Body (Paper Styling) -->
        <div class="p-4 overflow-y-auto flex-1 bg-slate-950/80">
            <div id="receiptPaper" class="bg-white text-black p-5 rounded-lg shadow-xl text-xs font-mono max-w-[80mm] mx-auto border border-slate-300">
                <!-- Receipt content rendered via Javascript / PHP -->
            </div>
        </div>

        <!-- Modal Action Buttons -->
        <div class="p-3.5 border-t border-slate-800 bg-slate-950 flex items-center justify-between gap-2">
            <button type="button" onclick="sendInvoiceToWhatsApp()" class="flex-1 py-2.5 bg-emerald-700 hover:bg-emerald-600 text-white rounded-xl text-xs font-bold flex items-center justify-center gap-1.5 shadow">
                <i data-lucide="message-circle" class="w-4 h-4"></i>
                <span>واٹس ایپ رسید بھیجیں</span>
            </button>
            <button type="button" onclick="window.print()" class="flex-1 py-2.5 bg-emerald-600 hover:bg-emerald-500 text-white rounded-xl text-xs font-bold flex items-center justify-center gap-1.5 shadow">
                <i data-lucide="printer" class="w-4 h-4"></i>
                <span>پرنٹ کریں (Print)</span>
            </button>
            <button type="button" onclick="closeThermalModal()" class="px-3 py-2.5 bg-slate-800 hover:bg-slate-700 text-slate-300 rounded-xl text-xs font-bold">
                بند کریں
            </button>
        </div>
    </div>
</div>

<!-- Hidden Clean Printable Receipt (Only rendered on paper / print preview) -->
<div id="printReceiptDirectContainer" class="hidden print-receipt-container text-xs font-mono">
    <!-- Populated on print -->
</div>

<!-- ==========================================
     POS Core Client-Side Logic
     ========================================== -->
<script>
    // Audio Beep generator via Web Audio API (No external sound files required)
    const audioCtx = new (window.AudioContext || window.webkitAudioContext)();
    function playBeep(freq = 900, duration = 80) {
        try {
            if (audioCtx.state === 'suspended') {
                audioCtx.resume();
            }
            const osc = audioCtx.createOscillator();
            const gain = audioCtx.createGain();
            osc.type = 'sine';
            osc.frequency.value = freq;
            gain.gain.setValueAtTime(0.15, audioCtx.currentTime);
            gain.gain.exponentialRampToValueAtTime(0.001, audioCtx.currentTime + duration / 1000);
            osc.connect(gain);
            gain.connect(audioCtx.destination);
            osc.start();
            osc.stop(audioCtx.currentTime + duration / 1000);
        } catch (e) {}
    }

    // State
    let cart = [];
    let currentSelectedProductForUnits = null;
    let currentActiveInvoiceForReceipt = <?= json_encode($printInvoice, JSON_UNESCAPED_UNICODE) ?>;
    let activeCategory = 'ALL';
    let activeViewMode = 'list';

    // Initialize POS
    document.addEventListener('DOMContentLoaded', function() {
        lucide.createIcons();
        if (currentActiveInvoiceForReceipt) {
            openThermalModal(currentActiveInvoiceForReceipt);
        }
    });

    // Toggle List / Grid View
    function setViewMode(mode) {
        activeViewMode = mode;
        const wrapper = document.getElementById('productsWrapper');
        const btnList = document.getElementById('btnViewList');
        const btnGrid = document.getElementById('btnViewGrid');

        if (mode === 'grid') {
            wrapper.className = 'grid grid-cols-2 sm:grid-cols-3 gap-3 max-h-[620px] overflow-y-auto pr-1';
            document.querySelectorAll('.product-item').forEach(item => {
                item.querySelector('.list-layout').classList.add('hidden');
                item.querySelector('.grid-layout').classList.remove('hidden');
                item.querySelector('.grid-layout').classList.add('flex');
            });
            btnGrid.classList.add('bg-slate-800', 'text-white');
            btnGrid.classList.remove('text-slate-400');
            btnList.classList.remove('bg-slate-800', 'text-white');
            btnList.classList.add('text-slate-400');
        } else {
            wrapper.className = 'space-y-2 max-h-[620px] overflow-y-auto pr-1';
            document.querySelectorAll('.product-item').forEach(item => {
                item.querySelector('.list-layout').classList.remove('hidden');
                item.querySelector('.grid-layout').classList.add('hidden');
                item.querySelector('.grid-layout').classList.remove('flex');
            });
            btnList.classList.add('bg-slate-800', 'text-white');
            btnList.classList.remove('text-slate-400');
            btnGrid.classList.remove('bg-slate-800', 'text-white');
            btnGrid.classList.add('text-slate-400');
        }
    }

    // Category Filter
    function filterCategory(cat) {
        activeCategory = cat;
        document.querySelectorAll('.cat-pill').forEach(btn => {
            if (btn.getAttribute('data-cat') === cat) {
                btn.className = 'cat-pill active px-3.5 py-1.5 rounded-xl font-bold bg-emerald-600 text-white shadow-md shrink-0 transition-all flex items-center gap-1.5';
            } else {
                btn.className = 'cat-pill px-3 py-1.5 rounded-xl font-medium bg-slate-950 text-slate-300 hover:bg-slate-800 border border-slate-800 shrink-0 transition-all flex items-center gap-1.5';
            }
        });
        filterProductCards();
    }

    // Product Search & Filter
    function filterProductCards() {
        const query = (document.getElementById('posSearchInput').value || '').toLowerCase().trim();
        const clearBtn = document.getElementById('clearSearchBtn');
        if (query) {
            clearBtn.classList.remove('hidden');
        } else {
            clearBtn.classList.add('hidden');
        }

        const items = document.querySelectorAll('.product-item');
        items.forEach(item => {
            const itemCat = item.getAttribute('data-category');
            const name = item.getAttribute('data-name');
            const brand = item.getAttribute('data-brand');
            const sku = item.getAttribute('data-sku');
            const imei = item.getAttribute('data-imei');

            const matchesCategory = (activeCategory === 'ALL' || itemCat === activeCategory);
            const matchesQuery = !query || name.includes(query) || brand.includes(query) || sku.includes(query) || imei.includes(query);

            if (matchesCategory && matchesQuery) {
                item.style.display = '';
            } else {
                item.style.display = 'none';
            }
        });
    }

    document.getElementById('posSearchInput').addEventListener('input', filterProductCards);

    // Enter Key Handler on Search Input (Auto-adds product if exact barcode / 1 match found)
    document.getElementById('posSearchInput').addEventListener('keydown', function(e) {
        if (e.key === 'Enter') {
            e.preventDefault();
            const visibleItems = Array.from(document.querySelectorAll('.product-item')).filter(el => el.style.display !== 'none');
            if (visibleItems.length === 1) {
                visibleItems[0].click();
                clearSearch();
            } else if (visibleItems.length > 1) {
                // If an item has exact barcode/SKU/IMEI match, click that one
                const query = this.value.trim().toLowerCase();
                const exactMatch = visibleItems.find(el => el.getAttribute('data-sku') === query || el.getAttribute('data-imei') === query);
                if (exactMatch) {
                    exactMatch.click();
                    clearSearch();
                }
            }
        }
    });

    function clearSearch() {
        const input = document.getElementById('posSearchInput');
        input.value = '';
        filterProductCards();
        input.focus();
    }

    // Product Click Handler
    function onProductItemClick(product) {
        if (product.stock <= 0) {
            alert('یہ پروڈکٹ آؤٹ آف اسٹاک ہے!');
            return;
        }

        const hasUnits = (product.units && product.units.length > 0) || (product.category === 'MOBILES' && product.imei);
        if (hasUnits) {
            openUnitModal(product);
        } else {
            addStandardProductToCart(product);
        }
    }

    // Standard Non-Unit Accessory Add to Cart
    function addStandardProductToCart(product) {
        playBeep(1100, 70);
        const existing = cart.find(item => item.productId === product.id && !item.selectedUnitId);

        if (existing) {
            if (existing.quantity >= product.stock) {
                alert(`اسٹاک میں صرف ${product.stock} دانے دستیاب ہیں!`);
                return;
            }
            existing.quantity++;
            existing.totalSalePrice = existing.quantity * existing.unitSalePrice;
        } else {
            cart.push({
                cartItemId: 'cart-' + product.id + '-' + Date.now(),
                productId: product.id,
                name: product.name,
                category: product.category,
                quantity: 1,
                purchasePrice: product.purchasePrice,
                unitSalePrice: product.salePrice,
                totalSalePrice: product.salePrice,
                stockAvailable: product.stock,
                selectedUnitId: null,
                selectedImei1: product.imei || '',
                selectedColor: product.color || '',
                selectedRamStorage: product.ramStorage || ''
            });
        }
        renderCart();
    }

    // Multi-Unit / IMEI Modal Logic
    function openUnitModal(product) {
        currentSelectedProductForUnits = product;
        document.getElementById('modalProductName').innerText = product.name;
        document.getElementById('modalProductSubtitle').innerText = (product.brand || '') + ' - فی یونٹ قیمت: Rs. ' + product.salePrice.toLocaleString();

        const container = document.getElementById('modalUnitsListContainer');
        const units = product.units && product.units.length > 0 ? product.units : [
            {
                id: 'unit-single',
                imei1: product.imei || 'N/A',
                color: product.color || 'Standard',
                storageRam: product.ramStorage || '',
                condition: 'New / Used',
                status: 'AVAILABLE'
            }
        ];

        let html = '';
        units.forEach((u, idx) => {
            const isAlreadyInCart = cart.some(c => c.selectedUnitId === u.id || (c.selectedImei1 && c.selectedImei1 === u.imei1));
            const isSold = u.status === 'SOLD';

            html += `
            <div class="p-3 rounded-xl border ${isSold ? 'bg-slate-950/40 border-slate-800 opacity-60' : (isAlreadyInCart ? 'bg-emerald-950/20 border-emerald-800/40' : 'bg-slate-950 hover:bg-slate-850 border-slate-800')} flex items-center justify-between">
                <div>
                    <div class="flex items-center gap-2">
                        <span class="text-xs font-bold text-white font-mono">IMEI 1: ${u.imei1 || 'N/A'}</span>
                        ${u.imei2 ? `<span class="text-[10px] text-slate-400 font-mono">/ IMEI 2: ${u.imei2}</span>` : ''}
                    </div>
                    <div class="flex items-center gap-2 mt-1 text-[11px] text-slate-400">
                        ${u.color ? `<span class="px-1.5 py-0.5 rounded bg-slate-800 text-slate-300">${u.color}</span>` : ''}
                        ${u.storageRam ? `<span class="px-1.5 py-0.5 rounded bg-slate-800 text-slate-300 font-mono">${u.storageRam}</span>` : ''}
                        ${u.condition ? `<span class="px-1.5 py-0.5 rounded bg-slate-800 text-slate-300">${u.condition}</span>` : ''}
                    </div>
                </div>
                <div>
                    ${isSold ? `
                        <span class="px-2.5 py-1 rounded-lg bg-rose-950 text-rose-400 text-[10px] font-bold border border-rose-800/40">فروخت شدہ</span>
                    ` : (isAlreadyInCart ? `
                        <span class="px-2.5 py-1 rounded-lg bg-emerald-950 text-emerald-300 text-[10px] font-bold border border-emerald-800/40">کارٹ میں شامل ہے</span>
                    ` : `
                        <button type="button" onclick='selectUnitToCart(${JSON.stringify(u)})' class="px-3 py-1.5 bg-emerald-600 hover:bg-emerald-500 text-white rounded-lg text-xs font-bold shadow">
                            منتخب کریں
                        </button>
                    `)}
                </div>
            </div>`;
        });

        container.innerHTML = html;
        document.getElementById('unitSelectorModal').classList.remove('hidden');
        document.getElementById('unitSelectorModal').classList.add('flex');
    }

    function selectUnitToCart(unit) {
        if (!currentSelectedProductForUnits) return;
        playBeep(1250, 80);

        cart.push({
            cartItemId: 'cart-' + currentSelectedProductForUnits.id + '-' + (unit.id || Date.now()),
            productId: currentSelectedProductForUnits.id,
            name: currentSelectedProductForUnits.name,
            category: currentSelectedProductForUnits.category,
            quantity: 1,
            purchasePrice: currentSelectedProductForUnits.purchasePrice,
            unitSalePrice: currentSelectedProductForUnits.salePrice,
            totalSalePrice: currentSelectedProductForUnits.salePrice,
            stockAvailable: currentSelectedProductForUnits.stock,
            selectedUnitId: unit.id || ('unit-' + Date.now()),
            selectedImei1: unit.imei1 || currentSelectedProductForUnits.imei,
            selectedColor: unit.color || currentSelectedProductForUnits.color,
            selectedRamStorage: unit.storageRam || currentSelectedProductForUnits.ramStorage
        });

        closeUnitModal();
        renderCart();
    }

    function closeUnitModal() {
        document.getElementById('unitSelectorModal').classList.add('hidden');
        document.getElementById('unitSelectorModal').classList.remove('flex');
        currentSelectedProductForUnits = null;
    }

    // Change Item Quantity
    function changeQty(cartItemId, delta) {
        const item = cart.find(c => c.cartItemId === cartItemId);
        if (!item) return;

        // If specific IMEI unit, quantity cannot exceed 1
        if (item.selectedUnitId && delta > 0) {
            alert('ہر IMEI موبائل ایک الگ یونٹ ہوتا ہے۔ نیا یونٹ شامل کرنے کیلئے اگلا IMEI منتخب کریں۔');
            return;
        }

        const newQty = item.quantity + delta;
        if (newQty <= 0) {
            removeFromCart(cartItemId);
            return;
        }
        if (newQty > item.stockAvailable) {
            alert(`اسٹاک میں صرف ${item.stockAvailable} دانے دستیاب ہیں!`);
            return;
        }

        playBeep(800, 50);
        item.quantity = newQty;
        item.totalSalePrice = newQty * item.unitSalePrice;
        renderCart();
    }

    function removeFromCart(cartItemId) {
        cart = cart.filter(c => c.cartItemId !== cartItemId);
        renderCart();
    }

    function clearCart() {
        if (cart.length === 0) return;
        if (confirm('کیا آپ واقعی کارٹ خالی کرنا چاہتے ہیں؟')) {
            cart = [];
            renderCart();
        }
    }

    // Render Cart HTML
    function renderCart() {
        const container = document.getElementById('cartItemsList');
        const countBadge = document.getElementById('cartCountBadge');
        countBadge.innerText = cart.reduce((sum, item) => sum + item.quantity, 0);

        if (cart.length === 0) {
            container.innerHTML = `
                <div class="p-8 text-center text-slate-500 bg-slate-950/60 rounded-xl border border-dashed border-slate-800">
                    <i data-lucide="shopping-bag" class="w-10 h-10 mx-auto mb-2 text-slate-700"></i>
                    <p class="text-xs font-bold text-slate-400">کارٹ خالی ہے</p>
                    <p class="text-[11px] text-slate-500 mt-0.5">بائیں طرف سے پروڈکٹ پر کلک کریں یا بارکوڈ اسکین کریں</p>
                </div>`;
            lucide.createIcons();
            calculateTotals();
            return;
        }

        let html = '';
        cart.forEach(item => {
            html += `
            <div class="p-2.5 bg-slate-950 rounded-xl border border-slate-800 flex items-center justify-between text-xs gap-2">
                <div class="flex-1 min-w-0 pr-1">
                    <h5 class="font-bold text-white truncate text-xs">${item.name}</h5>
                    <div class="flex items-center gap-1.5 mt-0.5 text-[10px]">
                        <span class="text-emerald-400 font-mono font-bold">Rs. ${item.unitSalePrice.toLocaleString()}</span>
                        ${item.selectedImei1 ? `<span class="text-cyan-400 font-mono bg-cyan-950/60 px-1 rounded border border-cyan-800/40">IMEI: ${item.selectedImei1}</span>` : ''}
                    </div>
                </div>
                <div class="flex items-center gap-2 shrink-0">
                    <div class="flex items-center border border-slate-700 rounded-lg overflow-hidden bg-slate-900">
                        <button type="button" onclick="changeQty('${item.cartItemId}', -1)" class="px-2 py-1 text-slate-400 hover:text-white">-</button>
                        <span class="px-2.5 py-1 text-white font-bold font-mono text-xs">${item.quantity}</span>
                        <button type="button" onclick="changeQty('${item.cartItemId}', 1)" class="px-2 py-1 text-slate-400 hover:text-white">+</button>
                    </div>
                    <span class="w-16 text-left font-mono font-bold text-white text-xs">Rs. ${item.totalSalePrice.toLocaleString()}</span>
                    <button type="button" onclick="removeFromCart('${item.cartItemId}')" class="text-rose-400 hover:text-rose-300 p-1" title="حذف کریں">
                        <i data-lucide="trash-2" class="w-4 h-4"></i>
                    </button>
                </div>
            </div>`;
        });

        container.innerHTML = html;
        lucide.createIcons();
        calculateTotals();
    }

    // Bill Calculations
    function calculateTotals() {
        let subtotal = 0;
        let totalCost = 0;

        cart.forEach(item => {
            subtotal += item.totalSalePrice;
            totalCost += (item.purchasePrice * item.quantity);
        });

        const discountInput = document.getElementById('discountInput');
        const discount = parseFloat(discountInput.value) || 0;
        const net = Math.max(0, subtotal - discount);
        const profit = net - totalCost;

        document.getElementById('subtotalDisplay').innerText = 'Rs. ' + subtotal.toLocaleString();
        document.getElementById('discountDisplay').innerText = '- Rs. ' + discount.toLocaleString();
        document.getElementById('netDisplay').innerText = 'Rs. ' + net.toLocaleString();
        document.getElementById('profitDisplay').innerText = 'Rs. ' + profit.toLocaleString();
        document.getElementById('cartJsonInput').value = JSON.stringify(cart);

        calculateChangeReturn();
    }

    // Change Return & Quick Tender Helper
    function calculateChangeReturn() {
        let subtotal = cart.reduce((sum, item) => sum + item.totalSalePrice, 0);
        const discount = parseFloat(document.getElementById('discountInput').value) || 0;
        const net = Math.max(0, subtotal - discount);

        const cashRecInput = document.getElementById('cashReceivedInput');
        const cashRec = parseFloat(cashRecInput.value) || 0;
        const change = Math.max(0, cashRec - net);

        document.getElementById('changeReturnDisplay').innerText = 'Rs. ' + change.toLocaleString();
    }

    function setTenderExact() {
        let subtotal = cart.reduce((sum, item) => sum + item.totalSalePrice, 0);
        const discount = parseFloat(document.getElementById('discountInput').value) || 0;
        const net = Math.max(0, subtotal - discount);
        document.getElementById('cashReceivedInput').value = net;
        calculateChangeReturn();
    }

    function addTender(amt) {
        const input = document.getElementById('cashReceivedInput');
        const curr = parseFloat(input.value) || 0;
        input.value = curr + amt;
        calculateChangeReturn();
    }

    function onPaymentMethodChange() {
        const method = document.getElementById('paymentMethodSelect').value;
        const cashSection = document.getElementById('cashTenderedSection');
        if (method === 'CREDIT') {
            cashSection.classList.add('opacity-40', 'pointer-events-none');
            document.getElementById('cashReceivedInput').value = '0';
        } else {
            cashSection.classList.remove('opacity-40', 'pointer-events-none');
        }
        calculateChangeReturn();
    }

    function validateCheckout() {
        if (cart.length === 0) {
            alert('کارٹ خالی ہے! برائے مہربانی کوئی پروڈکٹ ایڈ کریں۔');
            return false;
        }
        const method = document.getElementById('paymentMethodSelect').value;
        const cname = document.getElementById('customerNameInput').value.trim();
        if (method === 'CREDIT' && (!cname || cname === 'واک ان کسٹمر')) {
            alert('ادھار کھاتہ (Credit) کی فروخت کیلئے کسٹمر کا اصل نام لازمی درج کریں!');
            return false;
        }
        return true;
    }

    // ==========================================
    // Thermal Receipt Modal & Printing
    // ==========================================
    function openThermalModal(invoiceData = null) {
        const inv = invoiceData || currentActiveInvoiceForReceipt;
        if (!inv) return;
        currentActiveInvoiceForReceipt = inv;

        const container = document.getElementById('receiptPaper');
        const directPrintContainer = document.getElementById('printReceiptDirectContainer');

        let itemsHtml = '';
        let itemsDirectHtml = '';

        (inv.items || []).forEach(item => {
            const name = item.productName || item.name || '';
            const imei = item.selectedImei1 || item.imei || '';
            const qty = item.quantity || 1;
            const price = item.unitSalePrice || item.salePrice || 0;
            const total = item.totalSalePrice || item.totalPrice || (qty * price);

            const row = `
                <tr style="border-bottom: 1px dashed #ccc;">
                    <td style="text-align: right; padding: 4px 0;">
                        ${name}
                        ${imei ? `<br><span style="font-size: 9px; color: #555;">IMEI: ${imei}</span>` : ''}
                    </td>
                    <td style="text-align: center; padding: 4px 0;">${qty}</td>
                    <td style="text-align: left; padding: 4px 0;">${price.toLocaleString()}</td>
                    <td style="text-align: left; padding: 4px 0; font-weight: bold;">${total.toLocaleString()}</td>
                </tr>`;
            itemsHtml += row;
            itemsDirectHtml += row;
        });

        const receiptTemplate = `
            <div style="text-align: center; border-bottom: 1px dashed #000; padding-bottom: 8px; margin-bottom: 8px;">
                <p style="font-size: 10px; margin: 0;">بِسْمِ اللهِ الرَّحْمٰنِ الرَّحِيْمِ</p>
                <h2 style="font-size: 16px; font-weight: 900; margin: 4px 0;"><?= $shopName ?></h2>
                <p style="font-size: 10px; margin: 0;"><?= htmlspecialchars($settings['address'] ?? '') ?></p>
                <p style="font-size: 11px; font-weight: bold; margin: 2px 0;">رابطہ: <?= htmlspecialchars($settings['phone'] ?? '') ?></p>
                <div style="margin-top: 6px; border: 1px solid #000; padding: 2px; font-size: 12px; font-weight: bold; letter-spacing: 1px;">
                    انوائس نمبر: ${inv.invoiceNo}
                </div>
                <p style="font-size: 10px; margin-top: 4px;">تاریخ: ${inv.date} | وقت: ${inv.time}</p>
            </div>

            <div style="border-bottom: 1px dashed #000; padding-bottom: 6px; margin-bottom: 6px; font-size: 11px;">
                <p style="margin: 2px 0;"><strong>کسٹمر:</strong> ${inv.customerName || 'واک ان کسٹمر'}</p>
                ${inv.customerPhone ? `<p style="margin: 2px 0;"><strong>فون نمبر:</strong> ${inv.customerPhone}</p>` : ''}
                <p style="margin: 2px 0;"><strong>ادائیگی طریقہ:</strong> ${inv.paymentMethod}</p>
            </div>

            <table style="width: 100%; border-collapse: collapse; font-size: 11px; margin-bottom: 8px;">
                <thead>
                    <tr style="border-bottom: 1px solid #000;">
                        <th style="text-align: right; padding: 3px 0;">تفصیل آئٹم</th>
                        <th style="text-align: center; padding: 3px 0;">تعداد</th>
                        <th style="text-align: left; padding: 3px 0;">ریٹ</th>
                        <th style="text-align: left; padding: 3px 0;">ٹوٹل</th>
                    </tr>
                </thead>
                <tbody>
                    ${itemsHtml}
                </tbody>
            </table>

            <div style="border-top: 1px solid #000; padding-top: 6px; font-size: 11px;">
                <div style="display: flex; justify-content: space-between; margin: 2px 0;">
                    <span>سب ٹوٹل:</span>
                    <span>Rs. ${Number(inv.totalAmount || 0).toLocaleString()}</span>
                </div>
                ${inv.discount > 0 ? `
                <div style="display: flex; justify-content: space-between; margin: 2px 0; color: #d00;">
                    <span>رعایت / ڈسکاؤنٹ:</span>
                    <span>- Rs. ${Number(inv.discount).toLocaleString()}</span>
                </div>` : ''}
                <div style="display: flex; justify-content: space-between; margin: 4px 0; font-size: 14px; font-weight: 900; border-top: 1px dashed #000; padding-top: 4px;">
                    <span>خالص واجب الادا رقم:</span>
                    <span>Rs. ${Number(inv.netAmount || 0).toLocaleString()}</span>
                </div>
                ${inv.cashReceived > 0 ? `
                <div style="display: flex; justify-content: space-between; margin: 2px 0;">
                    <span>وصول شدہ کیش:</span>
                    <span>Rs. ${Number(inv.cashReceived).toLocaleString()}</span>
                </div>` : ''}
                ${inv.changeDue > 0 ? `
                <div style="display: flex; justify-content: space-between; margin: 2px 0; font-weight: bold;">
                    <span>بقایا کسٹمر کو واپسی:</span>
                    <span>Rs. ${Number(inv.changeDue).toLocaleString()}</span>
                </div>` : ''}
            </div>

            <div style="text-align: center; margin-top: 12px; border-top: 1px dashed #000; padding-top: 8px; font-size: 9px; line-height: 1.4;">
                <p style="font-weight: bold; margin: 0;">وارنٹی کلیم کیلئے رسید اور بکس لانا لازمی ہے</p>
                <p style="margin: 2px 0;">خریدا ہوا سامان کسٹمر کی تسلی کے بعد واپس یا تبدیل نہیں ہوگا</p>
                <p style="margin-top: 4px; font-weight: bold;">تشریف لانے کا شکریہ! (Software by Balal POS)</p>
            </div>
        `;

        container.innerHTML = receiptTemplate;
        directPrintContainer.innerHTML = receiptTemplate;

        document.getElementById('thermalReceiptModal').classList.remove('hidden');
        document.getElementById('thermalReceiptModal').classList.add('flex');
    }

    function closeThermalModal() {
        document.getElementById('thermalReceiptModal').classList.add('hidden');
        document.getElementById('thermalReceiptModal').classList.remove('flex');
    }

    function viewHistoricalInvoice(invoiceData) {
        openThermalModal(invoiceData);
    }

    // Send WhatsApp Text Receipt to Customer
    function sendInvoiceToWhatsApp() {
        if (!currentActiveInvoiceForReceipt) return;
        const inv = currentActiveInvoiceForReceipt;

        let cleanPhone = (inv.customerPhone || '').replace(/[^0-9]/g, '');
        if (cleanPhone.startsWith('0')) {
            cleanPhone = '92' + cleanPhone.substring(1);
        } else if (!cleanPhone.startsWith('92') && cleanPhone.length === 10) {
            cleanPhone = '92' + cleanPhone;
        }

        let itemsText = '';
        (inv.items || []).forEach((it, i) => {
            const name = it.productName || it.name;
            const qty = it.quantity || 1;
            const price = it.unitSalePrice || it.salePrice;
            const imei = it.selectedImei1 || it.imei || '';
            itemsText += `\n${i + 1}. ${name} (${qty} x Rs.${price})${imei ? ` [IMEI: ${imei}]` : ''}`;
        });

        const msg = `*<?= $shopName ?>*\n` +
                    `*انوائس رسید نمبر:* ${inv.invoiceNo}\n` +
                    `*تاریخ:* ${inv.date} ${inv.time}\n` +
                    `*کسٹمر:* ${inv.customerName}\n` +
                    `--------------------------------\n` +
                    `*آئٹمز:*${itemsText}\n` +
                    `--------------------------------\n` +
                    `*کل رقم:* Rs. ${Number(inv.totalAmount).toLocaleString()}\n` +
                    (inv.discount > 0 ? `*رعایت:* Rs. ${Number(inv.discount).toLocaleString()}\n` : '') +
                    `*نیٹ بل:* Rs. ${Number(inv.netAmount).toLocaleString()}\n` +
                    `*ادائیگی طریقہ:* ${inv.paymentMethod}\n` +
                    `--------------------------------\n` +
                    `خریداری کا شکریہ!`;

        const url = `https://api.whatsapp.com/send?phone=${cleanPhone}&text=${encodeURIComponent(msg)}`;
        window.open(url, '_blank');
    }
</script>

<?php require_once __DIR__ . '/../backend/footer.php'; ?>
