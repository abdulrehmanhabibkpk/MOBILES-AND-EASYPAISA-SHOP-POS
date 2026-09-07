<?php
$pageTitle = 'موبائل خرید رجسٹر (Mobile Purchase Register)';
$activeMenu = 'purchase_register';
require_once __DIR__ . '/../backend/header.php';

$successMsg = '';
$errorMsg = '';
$viewRecord = null;
$receiptRecord = null;

// Handle Delete Action
if (isset($_GET['delete_id'])) {
    $delId = trim($_GET['delete_id']);
    try {
        $stmt = $pdo->prepare("DELETE FROM mobile_purchases WHERE id = :id");
        $stmt->execute([':id' => $delId]);
        $successMsg = $isUrdu ? "موبائل خریداری ریکارڈ کامیابی سے حذف کر دیا گیا!" : "Mobile purchase record deleted successfully!";
    } catch (Exception $e) {
        $errorMsg = "Error: " . $e->getMessage();
    }
}

// Handle Form Submission (Add or Edit)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'save_purchase') {
    $editId = trim($_POST['edit_id'] ?? '');
    $sellerName = trim($_POST['seller_name'] ?? '');
    $sellerCnic = trim($_POST['seller_cnic'] ?? '');
    $sellerPhone = trim($_POST['seller_phone'] ?? '');
    $sellerAddress = trim($_POST['seller_address'] ?? '');
    
    // Photos (Base64 data URLs)
    $sellerPhoto = $_POST['seller_photo'] ?? '';
    $cnicFrontPhoto = $_POST['cnic_front_photo'] ?? '';
    $cnicBackPhoto = $_POST['cnic_back_photo'] ?? '';
    $mobilePhoto = $_POST['mobile_photo'] ?? '';
    $boxPhoto = $_POST['box_photo'] ?? '';
    $thumbSignature = $_POST['thumb_signature'] ?? '';

    $brandModel = trim($_POST['brand_or_model'] ?? '');
    $conditionStatus = $_POST['condition_status'] ?? 'USED';
    $imei1 = trim($_POST['imei_1'] ?? '');
    $imei2 = trim($_POST['imei_2'] ?? '');
    $color = trim($_POST['color'] ?? '');
    $ramStorage = trim($_POST['ram_storage'] ?? '');
    $sku = trim($_POST['sku'] ?? '');
    $ptaStatus = $_POST['pta_status'] ?? 'PTA_APPROVED';

    $hasBox = isset($_POST['has_box']) ? 1 : 0;
    $hasCharger = isset($_POST['has_charger']) ? 1 : 0;
    $hasCable = isset($_POST['has_cable']) ? 1 : 0;
    $hasHandsfree = isset($_POST['has_handsfree']) ? 1 : 0;
    $hasWarrantyCard = isset($_POST['has_warranty_card']) ? 1 : 0;

    $accList = [];
    if ($hasBox) $accList[] = 'ڈبہ (Box)';
    if ($hasCharger) $accList[] = 'چارجر (Charger)';
    if ($hasCable) $accList[] = 'کیبل (Cable)';
    if ($hasHandsfree) $accList[] = 'ہینڈزفری (Handsfree)';
    if ($hasWarrantyCard) $accList[] = 'وارنٹی/بل (Bill)';
    $accessoriesStr = !empty($accList) ? implode(', ', $accList) : ($isUrdu ? 'صرف موبائل' : 'Only Mobile');

    $purchasePrice = floatval($_POST['purchase_price'] ?? 0);
    $estimatedSalePrice = floatval($_POST['estimated_sale_price'] ?? ($purchasePrice * 1.15));
    $paymentMethod = $_POST['payment_method'] ?? 'CASH';
    $notes = trim($_POST['notes'] ?? '');
    $autoAddToStock = isset($_POST['auto_add_stock']) ? 1 : 0;

    if (empty($sellerName) || empty($brandModel) || empty($imei1) || $purchasePrice <= 0) {
        $errorMsg = $isUrdu 
            ? 'برائے مہربانی لازمی خانے درج کریں (بیچنے والے کا نام، موبائل ماڈل، IMEI 1، اور خریداری رقم)!' 
            : 'Please fill in all required fields (Seller Name, Mobile Model, IMEI 1, and Purchase Price)!';
    } else {
        try {
            $pdo->beginTransaction();
            $today = date('Y-m-d');
            $timeNow = date('h:i A');
            $createdAt = round(microtime(true) * 1000);

            if (!empty($editId)) {
                // UPDATE RECORD
                $sql = "UPDATE mobile_purchases SET 
                    seller_name = :seller_name,
                    seller_phone = :seller_phone,
                    seller_cnic = :seller_cnic,
                    seller_address = :seller_address,
                    seller_photo = :seller_photo,
                    cnic_front_photo = :cnic_front_photo,
                    cnic_back_photo = :cnic_back_photo,
                    mobile_photo = :mobile_photo,
                    box_photo = :box_photo,
                    thumb_signature = :thumb_signature,
                    brand_or_model = :brand_or_model,
                    condition_status = :condition_status,
                    imei_1 = :imei_1,
                    imei_2 = :imei_2,
                    color = :color,
                    ram_storage = :ram_storage,
                    sku = :sku,
                    pta_status = :pta_status,
                    has_box = :has_box,
                    has_charger = :has_charger,
                    has_cable = :has_cable,
                    has_handsfree = :has_handsfree,
                    has_warranty_card = :has_warranty_card,
                    accessories = :accessories,
                    purchase_price = :purchase_price,
                    estimated_sale_price = :estimated_sale_price,
                    payment_method = :payment_method,
                    notes = :notes
                    WHERE id = :id";
                $stmt = $pdo->prepare($sql);
                $stmt->execute([
                    ':id' => $editId,
                    ':seller_name' => $sellerName,
                    ':seller_phone' => $sellerPhone,
                    ':seller_cnic' => $sellerCnic,
                    ':seller_address' => $sellerAddress,
                    ':seller_photo' => $sellerPhoto,
                    ':cnic_front_photo' => $cnicFrontPhoto,
                    ':cnic_back_photo' => $cnicBackPhoto,
                    ':mobile_photo' => $mobilePhoto,
                    ':box_photo' => $boxPhoto,
                    ':thumb_signature' => $thumbSignature,
                    ':brand_or_model' => $brandModel,
                    ':condition_status' => $conditionStatus,
                    ':imei_1' => $imei1,
                    ':imei_2' => $imei2,
                    ':color' => $color,
                    ':ram_storage' => $ramStorage,
                    ':sku' => $sku,
                    ':pta_status' => $ptaStatus,
                    ':has_box' => $hasBox,
                    ':has_charger' => $hasCharger,
                    ':has_cable' => $hasCable,
                    ':has_handsfree' => $hasHandsfree,
                    ':has_warranty_card' => $hasWarrantyCard,
                    ':accessories' => $accessoriesStr,
                    ':purchase_price' => $purchasePrice,
                    ':estimated_sale_price' => $estimatedSalePrice,
                    ':payment_method' => $paymentMethod,
                    ':notes' => $notes
                ]);
                $successMsg = $isUrdu ? "موبائل خریداری ریکارڈ اپڈیٹ ہو گیا!" : "Mobile purchase record updated successfully!";
                $purchaseId = $editId;
            } else {
                // INSERT NEW RECORD
                $purchaseId = 'pur-' . uniqid();
                // Generate serial receipt number
                $countQuery = $pdo->query("SELECT COUNT(*) FROM mobile_purchases")->fetchColumn();
                $receiptNo = 'PUR-' . (1000 + $countQuery + 1);

                $sql = "INSERT INTO mobile_purchases (
                    id, receipt_no, date, time, seller_name, seller_phone, seller_cnic, seller_address,
                    seller_photo, cnic_front_photo, cnic_back_photo, mobile_photo, box_photo, thumb_signature,
                    brand_or_model, condition_status, imei_1, imei_2, color, ram_storage, sku, pta_status,
                    has_box, has_charger, has_cable, has_handsfree, has_warranty_card, accessories,
                    purchase_price, estimated_sale_price, payment_method, notes, created_at
                ) VALUES (
                    :id, :receipt_no, :date, :time, :seller_name, :seller_phone, :seller_cnic, :seller_address,
                    :seller_photo, :cnic_front_photo, :cnic_back_photo, :mobile_photo, :box_photo, :thumb_signature,
                    :brand_or_model, :condition_status, :imei_1, :imei_2, :color, :ram_storage, :sku, :pta_status,
                    :has_box, :has_charger, :has_cable, :has_handsfree, :has_warranty_card, :accessories,
                    :purchase_price, :estimated_sale_price, :payment_method, :notes, :created_at
                )";
                $stmt = $pdo->prepare($sql);
                $stmt->execute([
                    ':id' => $purchaseId,
                    ':receipt_no' => $receiptNo,
                    ':date' => $today,
                    ':time' => $timeNow,
                    ':seller_name' => $sellerName,
                    ':seller_phone' => $sellerPhone,
                    ':seller_cnic' => $sellerCnic,
                    ':seller_address' => $sellerAddress,
                    ':seller_photo' => $sellerPhoto,
                    ':cnic_front_photo' => $cnicFrontPhoto,
                    ':cnic_back_photo' => $cnicBackPhoto,
                    ':mobile_photo' => $mobilePhoto,
                    ':box_photo' => $boxPhoto,
                    ':thumb_signature' => $thumbSignature,
                    ':brand_or_model' => $brandModel,
                    ':condition_status' => $conditionStatus,
                    ':imei_1' => $imei1,
                    ':imei_2' => $imei2,
                    ':color' => $color,
                    ':ram_storage' => $ramStorage,
                    ':sku' => $sku,
                    ':pta_status' => $ptaStatus,
                    ':has_box' => $hasBox,
                    ':has_charger' => $hasCharger,
                    ':has_cable' => $hasCable,
                    ':has_handsfree' => $hasHandsfree,
                    ':has_warranty_card' => $hasWarrantyCard,
                    ':accessories' => $accessoriesStr,
                    ':purchase_price' => $purchasePrice,
                    ':estimated_sale_price' => $estimatedSalePrice,
                    ':payment_method' => $paymentMethod,
                    ':notes' => $notes,
                    ':created_at' => $createdAt
                ]);

                // Auto-Add to POS Stock if checked
                if ($autoAddToStock) {
                    $prodId = 'prod-mob-' . uniqid();
                    $prodName = $brandModel . ($ramStorage ? ' (' . $ramStorage . ')' : '') . ($color ? ' - ' . $color : '');
                    $sqlProd = "INSERT INTO products (
                        id, name, category, purchase_price, sale_price, stock, image,
                        brand_or_model, imei_or_serial, sku, color, ram_storage, condition_status, pta_status, created_at
                    ) VALUES (
                        :id, :name, 'MOBILES', :cost, :sale, 1, :img,
                        :model, :imei, :sku, :color, :ram_storage, :condition_status, :pta_status, :created_at
                    )";
                    $stmtProd = $pdo->prepare($sqlProd);
                    $stmtProd->execute([
                        ':id' => $prodId,
                        ':name' => $prodName,
                        ':cost' => $purchasePrice,
                        ':sale' => $estimatedSalePrice,
                        ':img' => $mobilePhoto ?: ($sellerPhoto ?: ''),
                        ':model' => $brandModel,
                        ':imei' => $imei1,
                        ':sku' => $sku ?: $imei1,
                        ':color' => $color,
                        ':ram_storage' => $ramStorage,
                        ':condition_status' => $conditionStatus,
                        ':pta_status' => $ptaStatus,
                        ':created_at' => $createdAt
                    ]);
                }

                // Log Cash Outflow in Transactions
                $sqlTrx = "INSERT INTO transactions (
                    id, date, time, type, customer_name, customer_phone, cnic,
                    easy_paisa_amount, cash_amount, fee_profit, payment_method, note, photo_url, created_at
                ) VALUES (
                    :id, :date, :time, 'BUY_CASH', :cname, :cphone, :cnic,
                    0, :cost, 0, :pmethod, :note, :photo, :created_at
                )";
                $stmtTrx = $pdo->prepare($sqlTrx);
                $stmtTrx->execute([
                    ':id' => 'trx-pur-' . $purchaseId,
                    ':date' => $today,
                    ':time' => $timeNow,
                    ':cname' => $sellerName,
                    ':cphone' => $sellerPhone,
                    ':cnic' => $sellerCnic,
                    ':cost' => $purchasePrice,
                    ':pmethod' => $paymentMethod,
                    ':note' => "Mobile Buy [{$receiptNo}]: {$brandModel} (IMEI: {$imei1})",
                    ':photo' => $sellerPhoto ?: '',
                    ':created_at' => $createdAt
                ]);

                $successMsg = $isUrdu 
                    ? "موبائل خریداری اور تصاویر کامیابی سے محفوظ ہو گئیں اور اسٹاک میں شامل کر دیا گیا!" 
                    : "Mobile purchase record & photos saved successfully!";
            }

            $pdo->commit();

            // Fetch created record to show receipt modal immediately
            $stmtFetch = $pdo->prepare("SELECT * FROM mobile_purchases WHERE id = :id");
            $stmtFetch->execute([':id' => $purchaseId]);
            $receiptRecord = $stmtFetch->fetch();

        } catch (Exception $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            $errorMsg = "Database Error: " . $e->getMessage();
        }
    }
}

// Fetch Search & Filters
$searchQuery = trim($_GET['q'] ?? '');
$filterCondition = trim($_GET['cond'] ?? 'ALL');

$sqlList = "SELECT * FROM mobile_purchases WHERE 1=1";
$paramsList = [];

if (!empty($searchQuery)) {
    $sqlList .= " AND (
        seller_name LIKE :q 
        OR seller_cnic LIKE :q 
        OR seller_phone LIKE :q 
        OR brand_or_model LIKE :q 
        OR imei_1 LIKE :q 
        OR imei_2 LIKE :q 
        OR receipt_no LIKE :q 
        OR sku LIKE :q
    )";
    $paramsList[':q'] = "%{$searchQuery}%";
}

if ($filterCondition !== 'ALL') {
    $sqlList .= " AND condition_status = :cond";
    $paramsList[':cond'] = $filterCondition;
}

$sqlList .= " ORDER BY created_at DESC LIMIT 100";
$stmtList = $pdo->prepare($sqlList);
$stmtList->execute($paramsList);
$purchases = $stmtList->fetchAll();

// Calculate Stats
$totalCount = $pdo->query("SELECT COUNT(*) FROM mobile_purchases")->fetchColumn() ?: 0;
$totalSpent = $pdo->query("SELECT SUM(purchase_price) FROM mobile_purchases")->fetchColumn() ?: 0;
$usedCount = $pdo->query("SELECT COUNT(*) FROM mobile_purchases WHERE condition_status = 'USED'")->fetchColumn() ?: 0;
$newCount = $pdo->query("SELECT COUNT(*) FROM mobile_purchases WHERE condition_status = 'NEW'")->fetchColumn() ?: 0;
?>

<!-- Alerts -->
<?php if (!empty($successMsg)): ?>
    <div class="mb-4 p-4 rounded-2xl bg-emerald-500/10 border border-emerald-500/30 text-emerald-700 dark:text-emerald-300 flex items-center justify-between shadow-sm">
        <div class="flex items-center gap-2 font-bold text-sm">
            <i data-lucide="check-circle-2" class="w-5 h-5 text-emerald-500 shrink-0"></i>
            <span><?= htmlspecialchars($successMsg) ?></span>
        </div>
        <button onclick="this.parentElement.remove()" class="text-emerald-500 hover:text-emerald-700 p-1">
            <i data-lucide="x" class="w-4 h-4"></i>
        </button>
    </div>
<?php endif; ?>

<?php if (!empty($errorMsg)): ?>
    <div class="mb-4 p-4 rounded-2xl bg-rose-500/10 border border-rose-500/30 text-rose-700 dark:text-rose-300 flex items-center justify-between shadow-sm">
        <div class="flex items-center gap-2 font-bold text-sm">
            <i data-lucide="alert-triangle" class="w-5 h-5 text-rose-500 shrink-0"></i>
            <span><?= htmlspecialchars($errorMsg) ?></span>
        </div>
        <button onclick="this.parentElement.remove()" class="text-rose-500 hover:text-rose-700 p-1">
            <i data-lucide="x" class="w-4 h-4"></i>
        </button>
    </div>
<?php endif; ?>

<div class="space-y-5 no-print">

    <!-- TOP HEADER BANNER & STATS (Matching React Component) -->
    <div class="p-5 sm:p-6 rounded-3xl bg-gradient-to-r from-emerald-600 via-teal-600 to-emerald-700 text-white shadow-xl shadow-emerald-600/15 border border-emerald-500/30">
        <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
            <div class="flex items-center gap-3.5">
                <div class="w-12 h-12 rounded-2xl bg-white/20 border border-white/30 text-white flex items-center justify-center shrink-0 shadow-inner">
                    <i data-lucide="smartphone" class="w-6 h-6"></i>
                </div>
                <div>
                    <div class="flex items-center gap-2 flex-wrap">
                        <h2 class="text-lg sm:text-xl font-black tracking-tight text-white">
                            <?= $isUrdu ? 'موبائل خرید رجسٹر و قانونی تصدیق' : 'Mobile Buy & Purchase Register' ?>
                        </h2>
                        <span class="px-2.5 py-0.5 rounded-full bg-white/20 text-white text-[11px] font-extrabold uppercase tracking-wider inline-flex items-center gap-1 border border-white/30">
                            <i data-lucide="shield-check" class="w-3.5 h-3.5"></i>
                            <?= $isUrdu ? 'قانونی بیان حلفی و تصاویر' : 'Legal Affidavit & Photos' ?>
                        </span>
                    </div>
                    <p class="text-xs text-emerald-100 mt-1">
                        <?= $isUrdu 
                            ? 'نئے و استعمال شدہ موبائلز کی خریداری، شناختی کارڈ، کیمرہ فوٹوز، IMEI لاگ، انگوٹھا اور تھرمل رسیدیں' 
                            : 'Record purchases, CNIC details, live camera photos, IMEI scanner, thumb signature & legal thermal receipts' ?>
                    </p>
                </div>
            </div>

            <button
                type="button"
                onclick="openAddModal()"
                class="py-3 px-5 rounded-2xl bg-white text-emerald-900 hover:bg-emerald-50 font-black text-xs sm:text-sm flex items-center justify-center gap-2 shadow-lg transition-all cursor-pointer shrink-0 hover:scale-[1.02]"
            >
                <i data-lucide="plus" class="w-4 h-4 text-emerald-700"></i>
                <span><?= $isUrdu ? '+ نیا موبائل خریداری اندراج' : '+ Add Mobile Purchase' ?></span>
            </button>
        </div>

        <!-- Stats Grid -->
        <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 mt-5 pt-4 border-t border-white/20">
            <div class="bg-white/10 rounded-2xl p-3 border border-white/15 backdrop-blur-sm">
                <p class="text-[11px] text-emerald-100 font-medium"><?= $isUrdu ? 'کل خریدے گئے موبائلز' : 'Total Mobiles Bought' ?></p>
                <p class="text-lg sm:text-xl font-black"><?= number_format($totalCount) ?> <?= $isUrdu ? 'یونٹس' : 'Units' ?></p>
            </div>
            <div class="bg-white/10 rounded-2xl p-3 border border-white/15 backdrop-blur-sm">
                <p class="text-[11px] text-emerald-100 font-medium"><?= $isUrdu ? 'کل ادا شدہ سرمایہ' : 'Total Investment' ?></p>
                <p class="text-lg sm:text-xl font-black font-mono">Rs <?= number_format($totalSpent) ?></p>
            </div>
            <div class="bg-white/10 rounded-2xl p-3 border border-white/15 backdrop-blur-sm">
                <p class="text-[11px] text-emerald-100 font-medium"><?= $isUrdu ? 'سیکنڈ ہینڈ موبائلز' : 'Used Mobiles' ?></p>
                <p class="text-lg sm:text-xl font-black text-amber-200"><?= number_format($usedCount) ?> <?= $isUrdu ? 'یونٹس' : 'Units' ?></p>
            </div>
            <div class="bg-white/10 rounded-2xl p-3 border border-white/15 backdrop-blur-sm">
                <p class="text-[11px] text-emerald-100 font-medium"><?= $isUrdu ? 'نئے ڈبہ پیک' : 'New / Box Pack' ?></p>
                <p class="text-lg sm:text-xl font-black text-emerald-200"><?= number_format($newCount) ?> <?= $isUrdu ? 'یونٹس' : 'Units' ?></p>
            </div>
        </div>
    </div>

    <!-- SEARCH & FILTER BAR -->
    <div class="bg-slate-900/90 border border-slate-800 rounded-2xl p-4 shadow-sm">
        <form method="GET" class="flex flex-col sm:flex-row items-center gap-3">
            <div class="relative flex-1 w-full">
                <i data-lucide="search" class="w-4 h-4 text-slate-400 absolute <?= $isUrdu ? 'right-3' : 'left-3' ?> top-1/2 -translate-y-1/2"></i>
                <input
                    type="text"
                    name="q"
                    value="<?= htmlspecialchars($searchQuery) ?>"
                    placeholder="<?= $isUrdu ? 'نام، CNIC، فون، ماڈل یا IMEI سے تلاش کریں...' : 'Search by seller, CNIC, phone, model or IMEI...' ?>"
                    class="w-full bg-slate-950 border border-slate-700 rounded-xl <?= $isUrdu ? 'pr-9 pl-3' : 'pl-9 pr-3' ?> py-2.5 text-xs font-medium text-white focus:outline-none focus:border-emerald-500"
                />
            </div>

            <!-- Condition Tabs -->
            <div class="flex items-center gap-1.5 w-full sm:w-auto overflow-x-auto">
                <a
                    href="?cond=ALL<?= !empty($searchQuery) ? '&q='.urlencode($searchQuery) : '' ?>"
                    class="px-3.5 py-2 rounded-xl text-xs font-bold transition-all shrink-0 <?= $filterCondition === 'ALL' ? 'bg-emerald-600 text-white shadow-sm' : 'bg-slate-800 text-slate-400 hover:text-white' ?>"
                >
                    <?= $isUrdu ? 'تمام' : 'All' ?> (<?= $totalCount ?>)
                </a>
                <a
                    href="?cond=USED<?= !empty($searchQuery) ? '&q='.urlencode($searchQuery) : '' ?>"
                    class="px-3.5 py-2 rounded-xl text-xs font-bold transition-all shrink-0 <?= $filterCondition === 'USED' ? 'bg-amber-600 text-white shadow-sm' : 'bg-slate-800 text-slate-400 hover:text-white' ?>"
                >
                    <?= $isUrdu ? 'سیکنڈ ہینڈ' : 'Used' ?> (<?= $usedCount ?>)
                </a>
                <a
                    href="?cond=NEW<?= !empty($searchQuery) ? '&q='.urlencode($searchQuery) : '' ?>"
                    class="px-3.5 py-2 rounded-xl text-xs font-bold transition-all shrink-0 <?= $filterCondition === 'NEW' ? 'bg-emerald-600 text-white shadow-sm' : 'bg-slate-800 text-slate-400 hover:text-white' ?>"
                >
                    <?= $isUrdu ? 'نیا ڈبہ پیک' : 'New' ?> (<?= $newCount ?>)
                </a>
            </div>
        </form>
    </div>

    <!-- PURCHASES LIST CARDS GRID -->
    <?php if (empty($purchases)): ?>
        <div class="p-12 text-center bg-slate-900/90 rounded-3xl border border-slate-800 space-y-3">
            <i data-lucide="smartphone" class="w-12 h-12 text-slate-500 mx-auto"></i>
            <h3 class="text-base font-bold text-slate-300"><?= $isUrdu ? 'کوئی خریداری ریکارڈ نہیں ملا' : 'No Mobile Purchase Records Found' ?></h3>
            <p class="text-xs text-slate-500 max-w-sm mx-auto">
                <?= $isUrdu ? 'اوپر دیے گئے بٹن پر کلک کر کے نیا موبائل خرید ریکارڈ اور تصاویر درج کریں۔' : 'Click the button above to add a new mobile purchase record with photos.' ?>
            </p>
        </div>
    <?php else: ?>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <?php foreach ($purchases as $p): 
                $pJson = htmlspecialchars(json_encode($p), ENT_QUOTES, 'UTF-8');
                $isNew = ($p['condition_status'] === 'NEW');
            ?>
                <div class="bg-slate-900 border border-slate-800 rounded-3xl p-5 shadow-sm hover:shadow-md transition-all space-y-3.5 flex flex-col justify-between">
                    
                    <!-- Header -->
                    <div class="flex items-start justify-between border-b border-slate-800 pb-3">
                        <div class="flex items-center gap-3">
                            <div class="w-10 h-10 rounded-2xl bg-emerald-500/10 text-emerald-400 border border-emerald-500/20 flex items-center justify-center font-bold text-xs shrink-0">
                                <?php if (!empty($p['mobile_photo'])): ?>
                                    <img src="<?= htmlspecialchars($p['mobile_photo']) ?>" alt="Phone" class="w-full h-full object-cover rounded-2xl" />
                                <?php else: ?>
                                    <i data-lucide="smartphone" class="w-5 h-5"></i>
                                <?php endif; ?>
                            </div>
                            <div>
                                <h3 class="font-black text-white text-sm sm:text-base leading-tight">
                                    <?= htmlspecialchars($p['brand_or_model']) ?>
                                </h3>
                                <p class="text-[11px] text-slate-400 font-mono mt-0.5">
                                    <?= $p['receipt_no'] ?: 'PUR-'.$p['id'] ?> | <?= $p['date'] ?> (<?= $p['time'] ?>)
                                </p>
                            </div>
                        </div>

                        <span class="px-2.5 py-1 rounded-full text-[10px] font-black uppercase tracking-wider <?= $isNew ? 'bg-emerald-500/20 text-emerald-300 border border-emerald-500/30' : 'bg-amber-500/20 text-amber-300 border border-amber-500/30' ?>">
                            <?= $isNew ? ($isUrdu ? 'نیا ڈبہ پیک' : 'NEW') : ($isUrdu ? 'استعمال شدہ' : 'USED') ?>
                        </span>
                    </div>

                    <!-- Seller & Specs Grid -->
                    <div class="grid grid-cols-2 gap-2.5 text-xs bg-slate-950/60 p-3 rounded-2xl border border-slate-800/80">
                        <div>
                            <span class="text-slate-400 text-[10px] block font-semibold"><?= $isUrdu ? 'بیچنے والا:' : 'Seller Name:' ?></span>
                            <p class="font-black text-slate-200 truncate"><?= htmlspecialchars($p['seller_name']) ?></p>
                        </div>
                        <div>
                            <span class="text-slate-400 text-[10px] block font-semibold"><?= $isUrdu ? 'شناختی کارڈ (CNIC):' : 'CNIC No:' ?></span>
                            <p class="font-mono font-bold text-slate-300"><?= htmlspecialchars($p['seller_cnic'] ?: 'N/A') ?></p>
                        </div>
                        <div>
                            <span class="text-slate-400 text-[10px] block font-semibold"><?= $isUrdu ? 'فون نمبر:' : 'Phone No:' ?></span>
                            <p class="font-mono font-bold text-slate-300"><?= htmlspecialchars($p['seller_phone'] ?: 'N/A') ?></p>
                        </div>
                        <div>
                            <span class="text-slate-400 text-[10px] block font-semibold"><?= $isUrdu ? 'پرائمری IMEI:' : 'IMEI 1:' ?></span>
                            <p class="font-mono font-bold text-cyan-400 text-[11px] truncate"><?= htmlspecialchars($p['imei_1']) ?></p>
                        </div>
                    </div>

                    <!-- Photos Badge / Thumb indicator -->
                    <div class="flex items-center gap-2 flex-wrap">
                        <span class="text-[10px] text-slate-400 font-bold"><?= $isUrdu ? 'محفوظ تصاویر:' : 'Photos Attached:' ?></span>
                        <?php if (!empty($p['seller_photo'])): ?>
                            <span class="px-2 py-0.5 rounded-lg bg-slate-800 text-[10px] font-bold text-emerald-400 border border-slate-700 flex items-center gap-1">
                                <i data-lucide="user" class="w-3 h-3"></i> <?= $isUrdu ? 'کسٹمر' : 'Seller' ?>
                            </span>
                        <?php endif; ?>
                        <?php if (!empty($p['cnic_front_photo']) || !empty($p['cnic_back_photo'])): ?>
                            <span class="px-2 py-0.5 rounded-lg bg-slate-800 text-[10px] font-bold text-cyan-400 border border-slate-700 flex items-center gap-1">
                                <i data-lucide="credit-card" class="w-3 h-3"></i> <?= $isUrdu ? 'شناختی کارڈ' : 'CNIC' ?>
                            </span>
                        <?php endif; ?>
                        <?php if (!empty($p['mobile_photo'])): ?>
                            <span class="px-2 py-0.5 rounded-lg bg-slate-800 text-[10px] font-bold text-amber-400 border border-slate-700 flex items-center gap-1">
                                <i data-lucide="camera" class="w-3 h-3"></i> <?= $isUrdu ? 'موبائل' : 'Phone' ?>
                            </span>
                        <?php endif; ?>
                        <?php if (!empty($p['thumb_signature'])): ?>
                            <span class="px-2 py-0.5 rounded-lg bg-slate-800 text-[10px] font-bold text-purple-400 border border-slate-700 flex items-center gap-1">
                                <i data-lucide="pen-tool" class="w-3 h-3"></i> <?= $isUrdu ? 'دستخط/انگوٹھا' : 'Signature' ?>
                            </span>
                        <?php endif; ?>
                        <?php if (empty($p['seller_photo']) && empty($p['cnic_front_photo']) && empty($p['mobile_photo'])): ?>
                            <span class="text-[10px] text-slate-400 italic"><?= $isUrdu ? 'کوئی تصویر نہیں' : 'No photos' ?></span>
                        <?php endif; ?>
                    </div>

                    <!-- Accessories List -->
                    <div class="flex items-center gap-1.5 text-[10px] font-bold text-slate-400 flex-wrap">
                        <span class="text-slate-400"><?= $isUrdu ? 'اسیسریز:' : 'Accessories:' ?></span>
                        <?= htmlspecialchars($p['accessories'] ?: ($isUrdu ? 'صرف موبائل' : 'Only Phone')) ?>
                    </div>

                    <!-- Price & Actions Footer -->
                    <div class="pt-3 border-t border-slate-800 flex items-center justify-between gap-2 flex-wrap">
                        <div>
                            <span class="text-[10px] text-slate-400 font-semibold block"><?= $isUrdu ? 'ادا شدہ رقم:' : 'Paid Cost:' ?></span>
                            <span class="text-base sm:text-lg font-black font-mono text-emerald-400">
                                Rs <?= number_format($p['purchase_price']) ?>
                            </span>
                        </div>

                        <div class="flex items-center gap-1.5">
                            <!-- Edit Button -->
                            <button
                                type="button"
                                onclick="openEditModal(<?= $pJson ?>)"
                                class="py-1.5 px-2.5 rounded-xl bg-blue-500/10 hover:bg-blue-500/20 text-blue-400 border border-blue-500/30 font-bold text-xs flex items-center gap-1 transition-colors cursor-pointer"
                                title="<?= $isUrdu ? 'ترمیم کریں' : 'Edit Record' ?>"
                            >
                                <i data-lucide="edit-3" class="w-3.5 h-3.5"></i>
                                <span><?= $isUrdu ? 'ترمیم' : 'Edit' ?></span>
                            </button>

                            <!-- View Details Modal Button -->
                            <button
                                type="button"
                                onclick="openDetailsModal(<?= $pJson ?>)"
                                class="py-1.5 px-2.5 rounded-xl bg-slate-800 hover:bg-slate-700 text-slate-200 border border-slate-700 font-bold text-xs flex items-center gap-1 transition-colors cursor-pointer"
                                title="<?= $isUrdu ? 'مکمل تفصیلات و تصاویر دیکھیں' : 'View Full Details & Photos' ?>"
                            >
                                <i data-lucide="eye" class="w-3.5 h-3.5"></i>
                                <span><?= $isUrdu ? 'تفصیل' : 'Details' ?></span>
                            </button>

                            <!-- Print Legal Receipt & Affidavit Button -->
                            <button
                                type="button"
                                onclick="openReceiptModal(<?= $pJson ?>)"
                                class="py-1.5 px-2.5 rounded-xl bg-emerald-600 hover:bg-emerald-500 text-white font-bold text-xs flex items-center gap-1 shadow-sm transition-colors cursor-pointer"
                                title="<?= $isUrdu ? 'قانونی رسید و اقرار نامہ پرنٹ کریں' : 'Print Legal Purchase Receipt' ?>"
                            >
                                <i data-lucide="printer" class="w-3.5 h-3.5"></i>
                                <span><?= $isUrdu ? 'رسید' : 'Receipt' ?></span>
                            </button>

                            <!-- Delete Button -->
                            <a
                                href="?delete_id=<?= urlencode($p['id']) ?>"
                                onclick="return confirm('<?= $isUrdu ? 'کیا آپ واقعی اس موبائل خریداری ریکارڈ کو ڈیلیٹ کرنا چاہتے ہیں؟' : 'Are you sure you want to delete this purchase record?' ?>')"
                                class="p-1.5 rounded-xl bg-rose-500/10 hover:bg-rose-500/20 text-rose-400 border border-rose-500/30 transition-colors cursor-pointer"
                                title="<?= $isUrdu ? 'ڈیلیٹ کریں' : 'Delete Record' ?>"
                            >
                                <i data-lucide="trash-2" class="w-4 h-4"></i>
                            </a>
                        </div>
                    </div>

                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

</div>

<!-- ========================================================================= -->
<!-- ADD / EDIT MOBILE PURCHASE MODAL FORM (FULL PHOTOS + WEBCAM + SIGNATURE)  -->
<!-- ========================================================================= -->
<div id="purchaseFormModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black/80 backdrop-blur-sm p-3 sm:p-6 overflow-y-auto hidden">
    <div class="bg-slate-900 text-white w-full max-w-3xl rounded-3xl shadow-2xl overflow-hidden border border-slate-700 my-auto">
        
        <!-- Header -->
        <div class="p-4 sm:p-5 bg-gradient-to-r from-emerald-600 via-teal-600 to-emerald-700 text-white flex items-center justify-between">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-2xl bg-white/20 border border-white/30 text-white flex items-center justify-center font-bold shadow-inner">
                    <i data-lucide="smartphone" class="w-5 h-5"></i>
                </div>
                <div>
                    <h3 id="formModalTitle" class="font-extrabold text-base sm:text-lg">
                        <?= $isUrdu ? 'نیا موبائل خریداری اندراج و تصدیق' : 'Add New Mobile Purchase Record' ?>
                    </h3>
                    <p class="text-xs text-emerald-100">
                        <?= $isUrdu ? 'بیچنے والے کے کوائف، تصاویر، IMEI اور قانونی تصدیق درج کریں' : 'Enter seller details, live camera photos, IMEI and legal verification' ?>
                    </p>
                </div>
            </div>

            <button
                type="button"
                onclick="closeAddModal()"
                class="w-8 h-8 rounded-full bg-white/20 hover:bg-white/30 text-white flex items-center justify-center transition-colors cursor-pointer"
            >
                <i data-lucide="x" class="w-5 h-5"></i>
            </button>
        </div>

        <!-- Form Body -->
        <form id="mobilePurchaseForm" method="POST" class="p-5 sm:p-6 space-y-5 max-h-[82vh] overflow-y-auto text-xs sm:text-sm">
            <input type="hidden" name="action" value="save_purchase">
            <input type="hidden" id="edit_id" name="edit_id" value="">

            <!-- Hidden Photo Base64 Inputs -->
            <input type="hidden" id="input_seller_photo" name="seller_photo" value="">
            <input type="hidden" id="input_cnic_front_photo" name="cnic_front_photo" value="">
            <input type="hidden" id="input_cnic_back_photo" name="cnic_back_photo" value="">
            <input type="hidden" id="input_mobile_photo" name="mobile_photo" value="">
            <input type="hidden" id="input_box_photo" name="box_photo" value="">
            <input type="hidden" id="input_thumb_signature" name="thumb_signature" value="">

            <!-- SECTION 1: SELLER DETAILS & PHOTOS -->
            <div class="space-y-4 p-4 sm:p-5 rounded-2xl bg-slate-950/70 border border-slate-800">
                <div class="flex items-center gap-2 border-b border-slate-800 pb-2 text-emerald-400 font-extrabold text-sm">
                    <i data-lucide="user-check" class="w-4 h-4 text-emerald-400"></i>
                    <span>1. <?= $isUrdu ? 'بیچنے والے (کسٹمر) کے کوائف و تصاویر' : 'Seller (Customer) Details & Photos' ?></span>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3.5">
                    <div>
                        <label class="block text-xs font-bold text-slate-300 mb-1">
                            <?= $isUrdu ? 'بیچنے والے کا مکمل نام' : 'Seller Full Name' ?> <span class="text-rose-500">*</span>
                        </label>
                        <input
                            type="text"
                            name="seller_name"
                            id="f_seller_name"
                            required
                            placeholder="<?= $isUrdu ? 'مثلاً: محمد عثمان' : 'e.g. Muhammad Usman' ?>"
                            class="w-full p-2.5 rounded-xl bg-slate-900 border border-slate-700 text-white font-medium focus:outline-none focus:border-emerald-500"
                        />
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-slate-300 mb-1">
                            <?= $isUrdu ? 'شناختی کارڈ (CNIC Number)' : 'CNIC Number' ?> <span class="text-rose-500">*</span>
                        </label>
                        <input
                            type="text"
                            name="seller_cnic"
                            id="f_seller_cnic"
                            required
                            placeholder="37405-1234567-1"
                            class="w-full p-2.5 rounded-xl bg-slate-900 border border-slate-700 text-white font-mono font-medium focus:outline-none focus:border-emerald-500"
                        />
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-slate-300 mb-1">
                            <?= $isUrdu ? 'موبائل فون نمبر' : 'Mobile / Phone Number' ?> <span class="text-rose-500">*</span>
                        </label>
                        <input
                            type="text"
                            name="seller_phone"
                            id="f_seller_phone"
                            required
                            placeholder="0300-1234567"
                            class="w-full p-2.5 rounded-xl bg-slate-900 border border-slate-700 text-white font-mono font-medium focus:outline-none focus:border-emerald-500"
                        />
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-slate-300 mb-1">
                            <?= $isUrdu ? 'مکمل پتہ / محلہ و شہر' : 'Address / City' ?>
                        </label>
                        <input
                            type="text"
                            name="seller_address"
                            id="f_seller_address"
                            placeholder="<?= $isUrdu ? 'مثلاً: سرائے صالح، ہری پور' : 'e.g. Sarai Saleh, Haripur' ?>"
                            class="w-full p-2.5 rounded-xl bg-slate-900 border border-slate-700 text-white font-medium focus:outline-none focus:border-emerald-500"
                        />
                    </div>
                </div>

                <!-- SELLER & CNIC PHOTOS (FILE UPLOAD + WEBCAM LIVE CAMERA) -->
                <div class="pt-2 border-t border-slate-800/80">
                    <label class="block text-xs font-black text-emerald-400 mb-2.5">
                        📸 <?= $isUrdu ? 'بیچنے والے اور شناختی کارڈ کی تصاویر (Live Camera یا File Upload):' : 'Seller & CNIC Photos (Live Camera or Upload):' ?>
                    </label>

                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-3.5">
                        
                        <!-- 1. Seller Photo -->
                        <div class="bg-slate-900 p-3 rounded-2xl border border-slate-800 flex flex-col justify-between space-y-2">
                            <div>
                                <span class="block text-[11px] font-bold text-slate-300 mb-1.5"><?= $isUrdu ? 'کسٹمر/بیچنے والے کی تصویر' : 'Seller Face Photo' ?></span>
                                <div class="flex items-center gap-1.5">
                                    <label class="flex-1 py-1.5 px-2 bg-slate-800 hover:bg-slate-700 text-slate-200 border border-slate-700 rounded-xl text-[11px] font-bold text-center cursor-pointer transition-colors flex items-center justify-center gap-1">
                                        <i data-lucide="upload" class="w-3.5 h-3.5"></i>
                                        <span><?= $isUrdu ? 'فائل منتخب' : 'Upload' ?></span>
                                        <input type="file" accept="image/*" class="hidden" onchange="handleFileCompress(event, 'seller_photo')" />
                                    </label>
                                    <button type="button" onclick="openWebcamModal('seller_photo')" class="py-1.5 px-2.5 bg-emerald-600 hover:bg-emerald-500 text-white rounded-xl text-[11px] font-bold flex items-center gap-1 transition-colors cursor-pointer" title="Take Live Snap">
                                        <i data-lucide="camera" class="w-3.5 h-3.5"></i>
                                        <span><?= $isUrdu ? 'کیمرہ' : 'Snap' ?></span>
                                    </button>
                                </div>
                            </div>
                            <!-- Preview Box -->
                            <div id="preview_seller_photo" class="w-full h-24 bg-slate-950 rounded-xl border border-dashed border-slate-700 flex items-center justify-center overflow-hidden relative">
                                <span class="text-[10px] text-slate-500"><?= $isUrdu ? 'کوئی تصویر نہیں' : 'No Photo' ?></span>
                            </div>
                        </div>

                        <!-- 2. CNIC Front Image -->
                        <div class="bg-slate-900 p-3 rounded-2xl border border-slate-800 flex flex-col justify-between space-y-2">
                            <div>
                                <span class="block text-[11px] font-bold text-slate-300 mb-1.5"><?= $isUrdu ? 'شناختی کارڈ فرنٹ (Front)' : 'CNIC Front Image' ?></span>
                                <div class="flex items-center gap-1.5">
                                    <label class="flex-1 py-1.5 px-2 bg-slate-800 hover:bg-slate-700 text-slate-200 border border-slate-700 rounded-xl text-[11px] font-bold text-center cursor-pointer transition-colors flex items-center justify-center gap-1">
                                        <i data-lucide="upload" class="w-3.5 h-3.5"></i>
                                        <span><?= $isUrdu ? 'فائل منتخب' : 'Upload' ?></span>
                                        <input type="file" accept="image/*" class="hidden" onchange="handleFileCompress(event, 'cnic_front_photo')" />
                                    </label>
                                    <button type="button" onclick="openWebcamModal('cnic_front_photo')" class="py-1.5 px-2.5 bg-emerald-600 hover:bg-emerald-500 text-white rounded-xl text-[11px] font-bold flex items-center gap-1 transition-colors cursor-pointer" title="Take Live Snap">
                                        <i data-lucide="camera" class="w-3.5 h-3.5"></i>
                                        <span><?= $isUrdu ? 'کیمرہ' : 'Snap' ?></span>
                                    </button>
                                </div>
                            </div>
                            <!-- Preview Box -->
                            <div id="preview_cnic_front_photo" class="w-full h-24 bg-slate-950 rounded-xl border border-dashed border-slate-700 flex items-center justify-center overflow-hidden relative">
                                <span class="text-[10px] text-slate-500"><?= $isUrdu ? 'کوئی تصویر نہیں' : 'No Photo' ?></span>
                            </div>
                        </div>

                        <!-- 3. CNIC Back Image -->
                        <div class="bg-slate-900 p-3 rounded-2xl border border-slate-800 flex flex-col justify-between space-y-2">
                            <div>
                                <span class="block text-[11px] font-bold text-slate-300 mb-1.5"><?= $isUrdu ? 'شناختی کارڈ بیک (Back)' : 'CNIC Back Image' ?></span>
                                <div class="flex items-center gap-1.5">
                                    <label class="flex-1 py-1.5 px-2 bg-slate-800 hover:bg-slate-700 text-slate-200 border border-slate-700 rounded-xl text-[11px] font-bold text-center cursor-pointer transition-colors flex items-center justify-center gap-1">
                                        <i data-lucide="upload" class="w-3.5 h-3.5"></i>
                                        <span><?= $isUrdu ? 'فائل منتخب' : 'Upload' ?></span>
                                        <input type="file" accept="image/*" class="hidden" onchange="handleFileCompress(event, 'cnic_back_photo')" />
                                    </label>
                                    <button type="button" onclick="openWebcamModal('cnic_back_photo')" class="py-1.5 px-2.5 bg-emerald-600 hover:bg-emerald-500 text-white rounded-xl text-[11px] font-bold flex items-center gap-1 transition-colors cursor-pointer" title="Take Live Snap">
                                        <i data-lucide="camera" class="w-3.5 h-3.5"></i>
                                        <span><?= $isUrdu ? 'کیمرہ' : 'Snap' ?></span>
                                    </button>
                                </div>
                            </div>
                            <!-- Preview Box -->
                            <div id="preview_cnic_back_photo" class="w-full h-24 bg-slate-950 rounded-xl border border-dashed border-slate-700 flex items-center justify-center overflow-hidden relative">
                                <span class="text-[10px] text-slate-500"><?= $isUrdu ? 'کوئی تصویر نہیں' : 'No Photo' ?></span>
                            </div>
                        </div>

                    </div>
                </div>

                <!-- DIGITAL THUMB / SIGNATURE PAD -->
                <div class="pt-2 border-t border-slate-800/80">
                    <div class="flex items-center justify-between mb-1.5">
                        <label class="text-xs font-bold text-slate-300 flex items-center gap-1.5">
                            <i data-lucide="pen-tool" class="w-3.5 h-3.5 text-purple-400"></i>
                            <span><?= $isUrdu ? 'بیچنے والے کے ڈیجیٹل دستخط یا انگوٹھا (Digital Signature / Thumb):' : 'Digital Signature / Thumb Impression:' ?></span>
                        </label>
                        <button type="button" onclick="clearSignatureCanvas()" class="text-[11px] text-rose-400 hover:text-rose-300 font-bold">
                            <?= $isUrdu ? 'صاف کریں (Clear)' : 'Clear Pad' ?>
                        </button>
                    </div>
                    <div class="w-full bg-slate-900 border border-slate-700 rounded-2xl overflow-hidden relative">
                        <canvas id="signatureCanvas" width="500" height="110" class="w-full h-[110px] cursor-crosshair bg-slate-950"></canvas>
                        <span class="absolute bottom-1 right-2 text-[10px] text-slate-600 pointer-events-none"><?= $isUrdu ? 'انگلی یا ماؤس سے دستخط کریں' : 'Draw signature with finger or mouse' ?></span>
                    </div>
                </div>

            </div>

            <!-- SECTION 2: MOBILE SPECIFICATIONS & DEVICE PHOTOS -->
            <div class="space-y-4 p-4 sm:p-5 rounded-2xl bg-slate-950/70 border border-slate-800">
                <div class="flex items-center gap-2 border-b border-slate-800 pb-2 text-emerald-400 font-extrabold text-sm">
                    <i data-lucide="smartphone" class="w-4 h-4 text-emerald-400"></i>
                    <span>2. <?= $isUrdu ? 'موبائل فون تفصیلات و تصاویر' : 'Mobile Phone Specifications & Photos' ?></span>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3.5">
                    <div>
                        <label class="block text-xs font-bold text-slate-300 mb-1">
                            <?= $isUrdu ? 'برانڈ و ماڈل' : 'Brand & Model' ?> <span class="text-rose-500">*</span>
                        </label>
                        <input
                            type="text"
                            name="brand_or_model"
                            id="f_brand_or_model"
                            required
                            placeholder="<?= $isUrdu ? 'مثلاً: Vivo Y21 یا iPhone 13 Pro' : 'e.g. Vivo Y21 or Samsung A14' ?>"
                            class="w-full p-2.5 rounded-xl bg-slate-900 border border-slate-700 text-white font-medium focus:outline-none focus:border-emerald-500"
                        />
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-slate-300 mb-1">
                            <?= $isUrdu ? 'حالت (Condition)' : 'Condition' ?> <span class="text-rose-500">*</span>
                        </label>
                        <select
                            name="condition_status"
                            id="f_condition_status"
                            class="w-full p-2.5 rounded-xl bg-slate-900 border border-slate-700 text-white font-bold focus:outline-none focus:border-emerald-500"
                        >
                            <option value="USED"><?= $isUrdu ? 'سیکنڈ ہینڈ (Used / 2nd Hand)' : 'Used / Second Hand' ?></option>
                            <option value="NEW"><?= $isUrdu ? 'نیا ڈبہ پیک (New / Pin Pack)' : 'New / Pin Pack' ?></option>
                        </select>
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-slate-300 mb-1">
                            <?= $isUrdu ? 'پرائمری IMEI نمبر 1' : 'IMEI Number 1' ?> <span class="text-rose-500">*</span>
                        </label>
                        <div class="flex gap-1.5">
                            <input
                                type="text"
                                name="imei_1"
                                id="f_imei_1"
                                required
                                placeholder="862800000000001"
                                class="flex-1 p-2.5 rounded-xl bg-slate-900 border border-slate-700 text-cyan-400 font-mono font-bold focus:outline-none focus:border-emerald-500"
                            />
                            <button
                                type="button"
                                onclick="openScannerGun('f_imei_1')"
                                class="px-3 bg-cyan-600 hover:bg-cyan-500 text-white text-xs font-bold rounded-xl flex items-center gap-1 transition-all cursor-pointer shadow-sm shrink-0"
                                title="Scan Barcode / IMEI"
                            >
                                <i data-lucide="scan" class="w-3.5 h-3.5"></i>
                                <span><?= $isUrdu ? 'اسکین' : 'Scan' ?></span>
                            </button>
                        </div>
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-slate-300 mb-1">
                            <?= $isUrdu ? 'اختیاری IMEI نمبر 2' : 'IMEI Number 2 (Optional)' ?>
                        </label>
                        <input
                            type="text"
                            name="imei_2"
                            id="f_imei_2"
                            placeholder="862800000000002"
                            class="w-full p-2.5 rounded-xl bg-slate-900 border border-slate-700 text-white font-mono font-medium focus:outline-none focus:border-emerald-500"
                        />
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-slate-300 mb-1">
                            <?= $isUrdu ? 'رنگ (Color)' : 'Color' ?>
                        </label>
                        <input
                            type="text"
                            name="color"
                            id="f_color"
                            placeholder="<?= $isUrdu ? 'مثلاً: Black / Blue' : 'e.g. Black, Blue' ?>"
                            class="w-full p-2.5 rounded-xl bg-slate-900 border border-slate-700 text-white font-medium focus:outline-none focus:border-emerald-500"
                        />
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-slate-300 mb-1">
                            <?= $isUrdu ? 'ریم و میموری (RAM / Storage)' : 'RAM & Storage' ?>
                        </label>
                        <input
                            type="text"
                            name="ram_storage"
                            id="f_ram_storage"
                            placeholder="<?= $isUrdu ? 'مثلاً: 4GB / 64GB یا 8GB / 128GB' : 'e.g. 4GB / 64GB' ?>"
                            class="w-full p-2.5 rounded-xl bg-slate-900 border border-slate-700 text-white font-medium focus:outline-none focus:border-emerald-500"
                        />
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-slate-300 mb-1">
                            <?= $isUrdu ? 'پی ٹی اے اسٹیٹس (PTA Status)' : 'PTA Status' ?>
                        </label>
                        <select
                            name="pta_status"
                            id="f_pta_status"
                            class="w-full p-2.5 rounded-xl bg-slate-900 border border-slate-700 text-white font-bold focus:outline-none focus:border-emerald-500"
                        >
                            <option value="PTA_APPROVED"><?= $isUrdu ? 'پی ٹی اے تصدیق شدہ (PTA Approved)' : 'PTA Approved' ?></option>
                            <option value="NON_PTA"><?= $isUrdu ? 'نان پی ٹی اے (Non PTA)' : 'Non PTA' ?></option>
                            <option value="OFFICIAL_WARRANTY"><?= $isUrdu ? 'آفیشل کمپنی وارنٹی' : 'Official Warranty' ?></option>
                        </select>
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-slate-300 mb-1">
                            <?= $isUrdu ? 'بارکوڈ / SKU نمبر' : 'SKU / Barcode' ?>
                        </label>
                        <input
                            type="text"
                            name="sku"
                            id="f_sku"
                            placeholder="<?= $isUrdu ? 'بارکوڈ نمبر' : 'Barcode / SKU' ?>"
                            class="w-full p-2.5 rounded-xl bg-slate-900 border border-slate-700 text-white font-mono font-bold focus:outline-none focus:border-emerald-500"
                        />
                    </div>
                </div>

                <!-- DEVICE & BOX PHOTOS -->
                <div class="pt-2 border-t border-slate-800/80">
                    <label class="block text-xs font-black text-emerald-400 mb-2.5">
                        📱 <?= $isUrdu ? 'موبائل فون و ڈبہ/رسید کی تصاویر:' : 'Device & Box / Bill Photos:' ?>
                    </label>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3.5">
                        
                        <!-- Mobile Photo -->
                        <div class="bg-slate-900 p-3 rounded-2xl border border-slate-800 flex flex-col justify-between space-y-2">
                            <div>
                                <span class="block text-[11px] font-bold text-slate-300 mb-1.5"><?= $isUrdu ? 'موبائل فون فرنٹ/بیک تصویر' : 'Mobile Phone Image' ?></span>
                                <div class="flex items-center gap-1.5">
                                    <label class="flex-1 py-1.5 px-2 bg-slate-800 hover:bg-slate-700 text-slate-200 border border-slate-700 rounded-xl text-[11px] font-bold text-center cursor-pointer transition-colors flex items-center justify-center gap-1">
                                        <i data-lucide="upload" class="w-3.5 h-3.5"></i>
                                        <span><?= $isUrdu ? 'فائل منتخب' : 'Upload' ?></span>
                                        <input type="file" accept="image/*" class="hidden" onchange="handleFileCompress(event, 'mobile_photo')" />
                                    </label>
                                    <button type="button" onclick="openWebcamModal('mobile_photo')" class="py-1.5 px-2.5 bg-emerald-600 hover:bg-emerald-500 text-white rounded-xl text-[11px] font-bold flex items-center gap-1 transition-colors cursor-pointer" title="Take Live Snap">
                                        <i data-lucide="camera" class="w-3.5 h-3.5"></i>
                                        <span><?= $isUrdu ? 'کیمرہ' : 'Snap' ?></span>
                                    </button>
                                </div>
                            </div>
                            <div id="preview_mobile_photo" class="w-full h-24 bg-slate-950 rounded-xl border border-dashed border-slate-700 flex items-center justify-center overflow-hidden relative">
                                <span class="text-[10px] text-slate-500"><?= $isUrdu ? 'کوئی تصویر نہیں' : 'No Photo' ?></span>
                            </div>
                        </div>

                        <!-- Box Photo -->
                        <div class="bg-slate-900 p-3 rounded-2xl border border-slate-800 flex flex-col justify-between space-y-2">
                            <div>
                                <span class="block text-[11px] font-bold text-slate-300 mb-1.5"><?= $isUrdu ? 'موبائل ڈبہ / وارنٹی بل تصویر' : 'Box / Purchase Bill Image' ?></span>
                                <div class="flex items-center gap-1.5">
                                    <label class="flex-1 py-1.5 px-2 bg-slate-800 hover:bg-slate-700 text-slate-200 border border-slate-700 rounded-xl text-[11px] font-bold text-center cursor-pointer transition-colors flex items-center justify-center gap-1">
                                        <i data-lucide="upload" class="w-3.5 h-3.5"></i>
                                        <span><?= $isUrdu ? 'فائل منتخب' : 'Upload' ?></span>
                                        <input type="file" accept="image/*" class="hidden" onchange="handleFileCompress(event, 'box_photo')" />
                                    </label>
                                    <button type="button" onclick="openWebcamModal('box_photo')" class="py-1.5 px-2.5 bg-emerald-600 hover:bg-emerald-500 text-white rounded-xl text-[11px] font-bold flex items-center gap-1 transition-colors cursor-pointer" title="Take Live Snap">
                                        <i data-lucide="camera" class="w-3.5 h-3.5"></i>
                                        <span><?= $isUrdu ? 'کیمرہ' : 'Snap' ?></span>
                                    </button>
                                </div>
                            </div>
                            <div id="preview_box_photo" class="w-full h-24 bg-slate-950 rounded-xl border border-dashed border-slate-700 flex items-center justify-center overflow-hidden relative">
                                <span class="text-[10px] text-slate-500"><?= $isUrdu ? 'کوئی تصویر نہیں' : 'No Photo' ?></span>
                            </div>
                        </div>

                    </div>
                </div>

            </div>

            <!-- SECTION 3: INCLUDED ACCESSORIES CHECKBOXES -->
            <div class="space-y-3 p-4 sm:p-5 rounded-2xl bg-slate-950/70 border border-slate-800">
                <label class="block text-xs font-extrabold text-slate-200 uppercase tracking-wider">
                    📦 <?= $isUrdu ? 'موبائل کے ساتھ موصول ہونے والا سامان (اسیسریز):' : 'Accessories Included With Mobile:' ?>
                </label>
                
                <div class="grid grid-cols-2 sm:grid-cols-5 gap-2.5 pt-1">
                    <label class="flex items-center gap-2 p-2.5 bg-slate-900 rounded-xl border border-slate-700 cursor-pointer text-xs font-bold text-slate-200 hover:border-emerald-500 transition-colors">
                        <input type="checkbox" name="has_box" id="f_has_box" checked class="w-4 h-4 accent-emerald-500 rounded" />
                        <span><?= $isUrdu ? 'اصل ڈبہ (Box)' : 'Box' ?></span>
                    </label>

                    <label class="flex items-center gap-2 p-2.5 bg-slate-900 rounded-xl border border-slate-700 cursor-pointer text-xs font-bold text-slate-200 hover:border-emerald-500 transition-colors">
                        <input type="checkbox" name="has_charger" id="f_has_charger" checked class="w-4 h-4 accent-emerald-500 rounded" />
                        <span><?= $isUrdu ? 'چارجر (Charger)' : 'Charger' ?></span>
                    </label>

                    <label class="flex items-center gap-2 p-2.5 bg-slate-900 rounded-xl border border-slate-700 cursor-pointer text-xs font-bold text-slate-200 hover:border-emerald-500 transition-colors">
                        <input type="checkbox" name="has_cable" id="f_has_cable" checked class="w-4 h-4 accent-emerald-500 rounded" />
                        <span><?= $isUrdu ? 'کیبل (Cable)' : 'Cable' ?></span>
                    </label>

                    <label class="flex items-center gap-2 p-2.5 bg-slate-900 rounded-xl border border-slate-700 cursor-pointer text-xs font-bold text-slate-200 hover:border-emerald-500 transition-colors">
                        <input type="checkbox" name="has_handsfree" id="f_has_handsfree" class="w-4 h-4 accent-emerald-500 rounded" />
                        <span><?= $isUrdu ? 'ہینڈزفری (Handsfree)' : 'Handsfree' ?></span>
                    </label>

                    <label class="flex items-center gap-2 p-2.5 bg-slate-900 rounded-xl border border-slate-700 cursor-pointer text-xs font-bold text-slate-200 hover:border-emerald-500 transition-colors">
                        <input type="checkbox" name="has_warranty_card" id="f_has_warranty_card" class="w-4 h-4 accent-emerald-500 rounded" />
                        <span><?= $isUrdu ? 'وارنٹی / بل' : 'Bill / Warranty' ?></span>
                    </label>
                </div>
            </div>

            <!-- SECTION 4: FINANCIAL & PAYMENT -->
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3.5 p-4 sm:p-5 rounded-2xl bg-emerald-500/10 border border-emerald-500/30">
                <div>
                    <label class="block text-xs font-extrabold text-emerald-400 mb-1">
                        <?= $isUrdu ? 'خریداری قیمت ادا کی گئی (Rs)' : 'Purchase Price Paid (Rs)' ?> <span class="text-rose-500">*</span>
                    </label>
                    <input
                        type="number"
                        name="purchase_price"
                        id="f_purchase_price"
                        required
                        min="1"
                        placeholder="28500"
                        class="w-full p-2.5 rounded-xl bg-slate-900 border border-slate-700 text-emerald-400 font-mono font-black text-base focus:outline-none focus:border-emerald-500"
                    />
                </div>

                <div>
                    <label class="block text-xs font-extrabold text-slate-300 mb-1">
                        <?= $isUrdu ? 'متوقع فروخت قیمت (Estimated Sale Price Rs)' : 'Target / Sale Price (Rs)' ?>
                    </label>
                    <input
                        type="number"
                        name="estimated_sale_price"
                        id="f_estimated_sale_price"
                        placeholder="32000"
                        class="w-full p-2.5 rounded-xl bg-slate-900 border border-slate-700 text-white font-mono font-bold focus:outline-none focus:border-emerald-500"
                    />
                </div>

                <div>
                    <label class="block text-xs font-extrabold text-slate-300 mb-1">
                        <?= $isUrdu ? 'ادائیگی کا طریقہ' : 'Payment Method' ?>
                    </label>
                    <select
                        name="payment_method"
                        id="f_payment_method"
                        class="w-full p-2.5 rounded-xl bg-slate-900 border border-slate-700 text-white font-bold focus:outline-none focus:border-emerald-500"
                    >
                        <option value="CASH"><?= $isUrdu ? 'نقد (Cash)' : 'Cash' ?></option>
                        <option value="EASYPAISA"><?= $isUrdu ? 'ایزی پیسہ (EasyPaisa)' : 'EasyPaisa' ?></option>
                        <option value="JAZZCASH"><?= $isUrdu ? 'جاز کیش (JazzCash)' : 'JazzCash' ?></option>
                        <option value="BANK"><?= $isUrdu ? 'بینک ٹرانسفر (Bank Transfer)' : 'Bank Transfer' ?></option>
                    </select>
                </div>

                <div>
                    <label class="block text-xs font-bold text-slate-300 mb-1">
                        <?= $isUrdu ? 'اضافی نوٹ / نقص یا ریمارکس' : 'Remarks / Notes' ?>
                    </label>
                    <input
                        type="text"
                        name="notes"
                        id="f_notes"
                        placeholder="<?= $isUrdu ? 'مثلاً: ہلکا اسکریچ، تمام بٹنز اوکے...' : 'e.g. Minor scratches, all buttons ok...' ?>"
                        class="w-full p-2.5 rounded-xl bg-slate-900 border border-slate-700 text-white font-medium focus:outline-none focus:border-emerald-500"
                    />
                </div>

                <!-- Auto Add to POS Stock Checkbox -->
                <div class="sm:col-span-2 pt-1">
                    <label class="flex items-center gap-3 p-3.5 rounded-xl bg-slate-900 border border-emerald-500/40 cursor-pointer shadow-sm">
                        <input
                            type="checkbox"
                            name="auto_add_stock"
                            id="f_auto_add_stock"
                            checked
                            class="w-5 h-5 accent-emerald-500 rounded shrink-0"
                        />
                        <div>
                            <span class="font-extrabold text-xs text-emerald-400 block">
                                <?= $isUrdu ? 'خودکار طور پر اس موبائل کو دکان کے POS اسٹاک میں شامل کریں' : 'Auto-add this mobile to Shop Stock Inventory (POS Stock)' ?>
                            </span>
                            <span class="text-[11px] text-slate-400">
                                <?= $isUrdu ? 'یہ فون فوری طور پر سیل لسٹ و بارکوڈ اسکینر پر دستیاب ہو جائے گا۔' : 'When enabled, this phone will automatically appear in POS stock so you can sell it.' ?>
                            </span>
                        </div>
                    </label>
                </div>
            </div>

            <!-- Submit Action Buttons -->
            <div class="pt-2 flex items-center justify-end gap-3">
                <button
                    type="button"
                    onclick="closeAddModal()"
                    class="py-3 px-5 rounded-2xl bg-slate-800 hover:bg-slate-700 text-slate-300 font-bold text-xs sm:text-sm cursor-pointer"
                >
                    <?= $isUrdu ? 'منسوخ کریں' : 'Cancel' ?>
                </button>

                <button
                    type="submit"
                    id="submitBtn"
                    class="py-3 px-6 rounded-2xl bg-gradient-to-r from-emerald-600 to-teal-600 hover:from-emerald-500 hover:to-teal-500 text-white font-black text-xs sm:text-sm shadow-xl shadow-emerald-600/20 flex items-center gap-2 cursor-pointer transition-all"
                >
                    <i data-lucide="check" class="w-4 h-4"></i>
                    <span><?= $isUrdu ? 'محفوظ کریں اور رسید تیار کریں' : 'Save Record & Generate Receipt' ?></span>
                </button>
            </div>

        </form>
    </div>
</div>

<!-- ========================================================================= -->
<!-- WEBCAM LIVE CAMERA MODAL (Instant Photo Capture for Customer/CNIC/Device)  -->
<!-- ========================================================================= -->
<div id="webcamModal" class="fixed inset-0 z-[60] flex items-center justify-center bg-black/90 backdrop-blur-md p-3 sm:p-6 hidden">
    <div class="bg-slate-900 text-white w-full max-w-md rounded-3xl shadow-2xl overflow-hidden border border-slate-700 space-y-3 p-4 sm:p-5">
        <div class="flex items-center justify-between border-b border-slate-800 pb-3">
            <h4 class="font-extrabold text-sm flex items-center gap-2 text-emerald-400">
                <i data-lucide="camera" class="w-4 h-4"></i>
                <span><?= $isUrdu ? 'لائیو کیمرہ اسنیپ (Webcam Capture)' : 'Live Camera Snap' ?></span>
            </h4>
            <button type="button" onclick="closeWebcamModal()" class="text-slate-400 hover:text-white p-1">
                <i data-lucide="x" class="w-5 h-5"></i>
            </button>
        </div>

        <!-- Video Stream Preview -->
        <div class="w-full aspect-video bg-black rounded-2xl overflow-hidden relative border border-slate-800 flex items-center justify-center">
            <video id="webcamVideo" autoplay playsinline class="w-full h-full object-cover"></video>
            <div class="absolute inset-0 border-2 border-dashed border-emerald-500/50 rounded-2xl pointer-events-none m-3"></div>
        </div>

        <div class="flex items-center justify-between gap-3 pt-2">
            <button type="button" onclick="closeWebcamModal()" class="py-2.5 px-4 bg-slate-800 hover:bg-slate-700 text-slate-300 rounded-xl text-xs font-bold">
                <?= $isUrdu ? 'بند کریں' : 'Cancel' ?>
            </button>
            <button type="button" onclick="captureWebcamSnap()" class="flex-1 py-2.5 px-4 bg-emerald-600 hover:bg-emerald-500 text-white rounded-xl text-xs font-black flex items-center justify-center gap-2 shadow-lg">
                <i data-lucide="aperture" class="w-4 h-4"></i>
                <span><?= $isUrdu ? 'تصویر لیں (Capture Snap)' : 'Capture Snap' ?></span>
            </button>
        </div>
    </div>
</div>

<!-- ========================================================================= -->
<!-- VIEW FULL DETAILS & HIGH-RES PHOTOS MODAL                                  -->
<!-- ========================================================================= -->
<div id="detailsModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black/80 backdrop-blur-sm p-3 sm:p-6 overflow-y-auto hidden">
    <div class="bg-slate-900 text-white w-full max-w-2xl rounded-3xl shadow-2xl overflow-hidden border border-slate-700 my-auto">
        <div class="p-4 sm:p-5 bg-gradient-to-r from-slate-900 via-slate-800 to-slate-900 border-b border-slate-800 flex items-center justify-between">
            <div class="flex items-center gap-2.5">
                <div class="w-9 h-9 rounded-xl bg-emerald-500/20 text-emerald-400 flex items-center justify-center font-bold">
                    <i data-lucide="eye" class="w-4 h-4"></i>
                </div>
                <h3 id="detModalTitle" class="font-extrabold text-base">
                    <?= $isUrdu ? 'موبائل خریداری و کوائف تفصیل' : 'Mobile Purchase Details' ?>
                </h3>
            </div>
            <button type="button" onclick="closeDetailsModal()" class="text-slate-400 hover:text-white p-1">
                <i data-lucide="x" class="w-5 h-5"></i>
            </button>
        </div>

        <div id="detailsModalContent" class="p-5 sm:p-6 space-y-4 max-h-[80vh] overflow-y-auto text-xs sm:text-sm">
            <!-- Dynamic Content Injected by JS -->
        </div>

        <div class="p-4 bg-slate-950 border-t border-slate-800 flex items-center justify-end gap-3">
            <button type="button" onclick="closeDetailsModal()" class="py-2.5 px-5 bg-slate-800 hover:bg-slate-700 text-slate-300 font-bold text-xs rounded-xl">
                <?= $isUrdu ? 'بند کریں' : 'Close' ?>
            </button>
            <button type="button" id="detPrintBtn" class="py-2.5 px-5 bg-emerald-600 hover:bg-emerald-500 text-white font-bold text-xs rounded-xl flex items-center gap-2 shadow">
                <i data-lucide="printer" class="w-3.5 h-3.5"></i>
                <span><?= $isUrdu ? 'قانونی رسید پرنٹ کریں' : 'Print Receipt' ?></span>
            </button>
        </div>
    </div>
</div>

<!-- ========================================================================= -->
<!-- PRINTABLE LEGAL RECEIPT & AFFIDAVIT MODAL (THERMAL + A4 + WHATSAPP)        -->
<!-- ========================================================================= -->
<div id="receiptModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black/80 backdrop-blur-sm p-3 sm:p-6 overflow-y-auto hidden">
    <div class="bg-slate-900 text-white w-full max-w-3xl rounded-3xl shadow-2xl overflow-hidden border border-slate-700 my-auto flex flex-col max-h-[92vh]">
        
        <!-- Controls Top Header -->
        <div class="p-4 sm:p-5 bg-slate-950 border-b border-slate-800 flex items-center justify-between gap-3 flex-wrap no-print">
            <div class="flex items-center gap-2.5">
                <div class="w-9 h-9 rounded-xl bg-emerald-500/20 text-emerald-400 flex items-center justify-center">
                    <i data-lucide="printer" class="w-4 h-4"></i>
                </div>
                <div>
                    <h3 class="font-extrabold text-sm sm:text-base text-white">
                        <?= $isUrdu ? 'قانونی خرید رسید و اقرار نامہ' : 'Mobile Purchase Legal Receipt' ?>
                    </h3>
                    <p class="text-[11px] text-slate-400"><?= $isUrdu ? 'کسٹمر کاپی، دکان کاپی اور بیان حلفی' : 'Customer copy, shop copy & legal affidavit' ?></p>
                </div>
            </div>

            <div class="flex items-center gap-2">
                <!-- WhatsApp Share -->
                <button
                    type="button"
                    onclick="shareReceiptWhatsApp()"
                    class="py-2 px-3 bg-emerald-700 hover:bg-emerald-600 text-white rounded-xl text-xs font-bold flex items-center gap-1.5 transition-colors cursor-pointer shadow-sm"
                    title="Share on WhatsApp"
                >
                    <i data-lucide="share-2" class="w-3.5 h-3.5"></i>
                    <span>WhatsApp</span>
                </button>

                <!-- Direct Print -->
                <button
                    type="button"
                    onclick="printReceipt()"
                    class="py-2 px-4 bg-emerald-600 hover:bg-emerald-500 text-white rounded-xl text-xs font-black flex items-center gap-1.5 transition-all cursor-pointer shadow-lg shadow-emerald-600/20"
                >
                    <i data-lucide="printer" class="w-3.5 h-3.5"></i>
                    <span><?= $isUrdu ? 'پرنٹ کریں (Print)' : 'Print Receipt' ?></span>
                </button>

                <button
                    type="button"
                    onclick="closeReceiptModal()"
                    class="w-8 h-8 rounded-full bg-slate-800 hover:bg-slate-700 text-slate-300 flex items-center justify-center"
                >
                    <i data-lucide="x" class="w-4 h-4"></i>
                </button>
            </div>
        </div>

        <!-- Receipt Content Wrapper -->
        <div class="p-4 sm:p-6 overflow-y-auto flex-1 bg-slate-800/60">
            <div id="receiptPrintArea" class="bg-white text-slate-900 rounded-2xl p-6 sm:p-8 space-y-6 max-w-2xl mx-auto shadow-md border border-slate-300 font-sans">
                <!-- Dynamically Rendered Receipt Cards -->
            </div>
        </div>

    </div>
</div>

<!-- ========================================================================= -->
<!-- HIDDEN HIGH QUALITY PRINT ENGINE (Activated on window.print())             -->
<!-- ========================================================================= -->
<div id="realPrintContainer" class="hidden print-only bg-white text-black font-sans leading-relaxed">
    <!-- Injected dynamically before print -->
</div>

<!-- ========================================================================= -->
<!-- JAVASCRIPT CONTROLLER (Image Compressor, Signature, Webcam, Print)        -->
<!-- ========================================================================= -->
<script>
    let activeWebcamTarget = null;
    let webcamStream = null;
    let currentActiveRecord = null;

    // -------------------------------------------------------------
    // CLIENT-SIDE IMAGE COMPRESSOR (Ensures ~40-60KB WebP/JPEG)
    // -------------------------------------------------------------
    function compressImageFile(file, maxWidth = 800, quality = 0.72) {
        return new Promise((resolve, reject) => {
            const reader = new FileReader();
            reader.readAsDataURL(file);
            reader.onload = (event) => {
                const img = new Image();
                img.src = event.target.result;
                img.onload = () => {
                    let width = img.width;
                    let height = img.height;

                    if (width > maxWidth) {
                        height = Math.round((height * maxWidth) / width);
                        width = maxWidth;
                    }

                    const canvas = document.createElement('canvas');
                    canvas.width = width;
                    canvas.height = height;
                    const ctx = canvas.getContext('2d');
                    ctx.drawImage(img, 0, 0, width, height);
                    const compressedDataUrl = canvas.toDataURL('image/jpeg', quality);
                    resolve(compressedDataUrl);
                };
                img.onerror = (err) => reject(err);
            };
            reader.onerror = (err) => reject(err);
        });
    }

    async function handleFileCompress(event, targetKey) {
        const file = event.target.files[0];
        if (!file) return;

        try {
            const compressed = await compressImageFile(file, 800, 0.75);
            setPhotoTarget(targetKey, compressed);
        } catch (err) {
            console.error("Compression error:", err);
            alert("Error processing photo: " + err.message);
        }
    }

    function setPhotoTarget(targetKey, dataUrl) {
        const inputEl = document.getElementById('input_' + targetKey);
        const prevEl = document.getElementById('preview_' + targetKey);
        if (inputEl) inputEl.value = dataUrl || '';
        if (prevEl) {
            if (dataUrl) {
                prevEl.innerHTML = `
                    <img src="${dataUrl}" class="w-full h-full object-cover" />
                    <button type="button" onclick="setPhotoTarget('${targetKey}', '')" class="absolute top-1 right-1 bg-rose-600 hover:bg-rose-700 text-white rounded-full p-1 shadow">
                        <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                    </button>
                `;
            } else {
                prevEl.innerHTML = `<span class="text-[10px] text-slate-500"><?= $isUrdu ? 'کوئی تصویر نہیں' : 'No Photo' ?></span>`;
            }
        }
    }

    // -------------------------------------------------------------
    // WEBCAM LIVE CAMERA CAPTURE CONTROLLER
    // -------------------------------------------------------------
    async function openWebcamModal(targetKey) {
        activeWebcamTarget = targetKey;
        const modal = document.getElementById('webcamModal');
        const video = document.getElementById('webcamVideo');
        modal.classList.remove('hidden');

        try {
            webcamStream = await navigator.mediaDevices.getUserMedia({
                video: { width: { ideal: 1280 }, height: { ideal: 720 }, facingMode: 'environment' }
            });
            video.srcObject = webcamStream;
        } catch (err) {
            console.error("Webcam access error:", err);
            alert("Camera access denied or unavailable: " + err.message);
            closeWebcamModal();
        }
    }

    function closeWebcamModal() {
        const modal = document.getElementById('webcamModal');
        modal.classList.add('hidden');
        if (webcamStream) {
            webcamStream.getTracks().forEach(track => track.stop());
            webcamStream = null;
        }
    }

    function captureWebcamSnap() {
        const video = document.getElementById('webcamVideo');
        if (!video || !activeWebcamTarget) return;

        const canvas = document.createElement('canvas');
        canvas.width = video.videoWidth || 640;
        canvas.height = video.videoHeight || 480;
        const ctx = canvas.getContext('2d');
        ctx.drawImage(video, 0, 0, canvas.width, canvas.height);
        const dataUrl = canvas.toDataURL('image/jpeg', 0.75);

        setPhotoTarget(activeWebcamTarget, dataUrl);
        closeWebcamModal();
    }

    // -------------------------------------------------------------
    // SIGNATURE PAD CANVAS
    // -------------------------------------------------------------
    const canvas = document.getElementById('signatureCanvas');
    const ctx = canvas.getContext('2d');
    let isDrawing = false;

    ctx.strokeStyle = '#10b981';
    ctx.lineWidth = 2.5;
    ctx.lineCap = 'round';
    ctx.lineJoin = 'round';

    function getCanvasPos(e) {
        const rect = canvas.getBoundingClientRect();
        const clientX = e.touches ? e.touches[0].clientX : e.clientX;
        const clientY = e.touches ? e.touches[0].clientY : e.clientY;
        return {
            x: (clientX - rect.left) * (canvas.width / rect.width),
            y: (clientY - rect.top) * (canvas.height / rect.height)
        };
    }

    canvas.addEventListener('mousedown', (e) => {
        isDrawing = true;
        const pos = getCanvasPos(e);
        ctx.beginPath();
        ctx.moveTo(pos.x, pos.y);
    });
    canvas.addEventListener('mousemove', (e) => {
        if (!isDrawing) return;
        const pos = getCanvasPos(e);
        ctx.lineTo(pos.x, pos.y);
        ctx.stroke();
    });
    window.addEventListener('mouseup', () => {
        if (isDrawing) {
            isDrawing = false;
            saveSignatureToHiddenInput();
        }
    });

    canvas.addEventListener('touchstart', (e) => {
        e.preventDefault();
        isDrawing = true;
        const pos = getCanvasPos(e);
        ctx.beginPath();
        ctx.moveTo(pos.x, pos.y);
    }, { passive: false });
    canvas.addEventListener('touchmove', (e) => {
        if (!isDrawing) return;
        e.preventDefault();
        const pos = getCanvasPos(e);
        ctx.lineTo(pos.x, pos.y);
        ctx.stroke();
    }, { passive: false });
    window.addEventListener('touchend', () => {
        if (isDrawing) {
            isDrawing = false;
            saveSignatureToHiddenInput();
        }
    });

    function clearSignatureCanvas() {
        ctx.clearRect(0, 0, canvas.width, canvas.height);
        document.getElementById('input_thumb_signature').value = '';
    }

    function saveSignatureToHiddenInput() {
        const dataUrl = canvas.toDataURL('image/png');
        document.getElementById('input_thumb_signature').value = dataUrl;
    }

    // -------------------------------------------------------------
    // MODAL OPEN / CLOSE CONTROLLER
    // -------------------------------------------------------------
    function openAddModal() {
        document.getElementById('formModalTitle').innerText = '<?= $isUrdu ? "نیا موبائل خریداری اندراج و تصدیق" : "Add New Mobile Purchase Record" ?>';
        document.getElementById('mobilePurchaseForm').reset();
        document.getElementById('edit_id').value = '';
        
        ['seller_photo', 'cnic_front_photo', 'cnic_back_photo', 'mobile_photo', 'box_photo'].forEach(k => setPhotoTarget(k, ''));
        clearSignatureCanvas();
        document.getElementById('f_auto_add_stock').checked = true;

        document.getElementById('purchaseFormModal').classList.remove('hidden');
        lucide.createIcons();
    }

    function openEditModal(record) {
        document.getElementById('formModalTitle').innerText = '<?= $isUrdu ? "ترمیم موبائل خریداری ریکارڈ" : "Edit Mobile Purchase Record" ?>';
        document.getElementById('edit_id').value = record.id;

        document.getElementById('f_seller_name').value = record.seller_name || '';
        document.getElementById('f_seller_cnic').value = record.seller_cnic || '';
        document.getElementById('f_seller_phone').value = record.seller_phone || '';
        document.getElementById('f_seller_address').value = record.seller_address || '';

        document.getElementById('f_brand_or_model').value = record.brand_or_model || '';
        document.getElementById('f_condition_status').value = record.condition_status || 'USED';
        document.getElementById('f_imei_1').value = record.imei_1 || '';
        document.getElementById('f_imei_2').value = record.imei_2 || '';
        document.getElementById('f_color').value = record.color || '';
        document.getElementById('f_ram_storage').value = record.ram_storage || '';
        document.getElementById('f_pta_status').value = record.pta_status || 'PTA_APPROVED';
        document.getElementById('f_sku').value = record.sku || '';

        document.getElementById('f_has_box').checked = record.has_box == 1;
        document.getElementById('f_has_charger').checked = record.has_charger == 1;
        document.getElementById('f_has_cable').checked = record.has_cable == 1;
        document.getElementById('f_has_handsfree').checked = record.has_handsfree == 1;
        document.getElementById('f_has_warranty_card').checked = record.has_warranty_card == 1;

        document.getElementById('f_purchase_price').value = record.purchase_price || '';
        document.getElementById('f_estimated_sale_price').value = record.estimated_sale_price || '';
        document.getElementById('f_payment_method').value = record.payment_method || 'CASH';
        document.getElementById('f_notes').value = record.notes || '';
        document.getElementById('f_auto_add_stock').checked = false;

        // Photos
        setPhotoTarget('seller_photo', record.seller_photo || '');
        setPhotoTarget('cnic_front_photo', record.cnic_front_photo || '');
        setPhotoTarget('cnic_back_photo', record.cnic_back_photo || '');
        setPhotoTarget('mobile_photo', record.mobile_photo || '');
        setPhotoTarget('box_photo', record.box_photo || '');

        // Load thumb signature if exists
        clearSignatureCanvas();
        if (record.thumb_signature) {
            document.getElementById('input_thumb_signature').value = record.thumb_signature;
            const img = new Image();
            img.src = record.thumb_signature;
            img.onload = () => ctx.drawImage(img, 0, 0);
        }

        document.getElementById('purchaseFormModal').classList.remove('hidden');
        lucide.createIcons();
    }

    function closeAddModal() {
        document.getElementById('purchaseFormModal').classList.add('hidden');
    }

    // -------------------------------------------------------------
    // DETAILS MODAL
    // -------------------------------------------------------------
    function openDetailsModal(record) {
        currentActiveRecord = record;
        const titleEl = document.getElementById('detModalTitle');
        const contentEl = document.getElementById('detailsModalContent');
        const printBtn = document.getElementById('detPrintBtn');

        titleEl.innerText = `${record.brand_or_model} - ${record.seller_name}`;
        printBtn.onclick = () => {
            closeDetailsModal();
            openReceiptModal(record);
        };

        const isNew = (record.condition_status === 'NEW');

        contentEl.innerHTML = `
            <!-- Specs & Seller Grid -->
            <div class="grid grid-cols-2 gap-3 bg-slate-950 p-4 rounded-2xl border border-slate-800">
                <div>
                    <span class="text-slate-400 text-[10px] block font-bold"><?= $isUrdu ? 'بیچنے والا' : 'Seller Name' ?></span>
                    <p class="font-extrabold text-white text-sm">${record.seller_name}</p>
                </div>
                <div>
                    <span class="text-slate-400 text-[10px] block font-bold"><?= $isUrdu ? 'شناختی کارڈ' : 'CNIC Number' ?></span>
                    <p class="font-mono font-bold text-slate-200">${record.seller_cnic || 'N/A'}</p>
                </div>
                <div>
                    <span class="text-slate-400 text-[10px] block font-bold"><?= $isUrdu ? 'فون نمبر' : 'Phone Number' ?></span>
                    <p class="font-mono font-bold text-slate-200">${record.seller_phone || 'N/A'}</p>
                </div>
                <div>
                    <span class="text-slate-400 text-[10px] block font-bold"><?= $isUrdu ? 'پتہ / رہائش' : 'Address' ?></span>
                    <p class="font-medium text-slate-300">${record.seller_address || 'N/A'}</p>
                </div>
                <div class="col-span-2 pt-2 border-t border-slate-800 grid grid-cols-2 gap-3">
                    <div>
                        <span class="text-slate-400 text-[10px] block font-bold"><?= $isUrdu ? 'ماڈل و حالت' : 'Model & Condition' ?></span>
                        <p class="font-black text-emerald-400">${record.brand_or_model} (${isNew ? 'NEW' : 'USED'})</p>
                    </div>
                    <div>
                        <span class="text-slate-400 text-[10px] block font-bold"><?= $isUrdu ? 'خریداری قیمت' : 'Purchase Cost' ?></span>
                        <p class="font-mono font-black text-base text-emerald-400">Rs ${Number(record.purchase_price).toLocaleString()}</p>
                    </div>
                    <div>
                        <span class="text-slate-400 text-[10px] block font-bold"><?= $isUrdu ? 'IMEI 1' : 'IMEI 1' ?></span>
                        <p class="font-mono font-bold text-cyan-400">${record.ime_1 || record.imei_1}</p>
                    </div>
                    <div>
                        <span class="text-slate-400 text-[10px] block font-bold"><?= $isUrdu ? 'IMEI 2' : 'IMEI 2' ?></span>
                        <p class="font-mono font-bold text-slate-300">${record.imei_2 || 'N/A'}</p>
                    </div>
                </div>
            </div>

            <!-- Photos Gallery -->
            <div class="space-y-2">
                <h4 class="font-bold text-xs text-slate-300"><?= $isUrdu ? 'محفوظ شدہ ہائی ریزولوشن تصاویر:' : 'Attached Photos Gallery:' ?></h4>
                <div class="grid grid-cols-2 sm:grid-cols-3 gap-3">
                    ${record.seller_photo ? `
                        <div class="bg-slate-950 p-2 rounded-xl border border-slate-800 text-center">
                            <span class="text-[10px] font-bold text-slate-400 block mb-1"><?= $isUrdu ? 'کسٹمر تصویر' : 'Seller Photo' ?></span>
                            <img src="${record.seller_photo}" class="w-full h-28 object-cover rounded-lg border border-slate-700" />
                        </div>
                    ` : ''}
                    ${record.cnic_front_photo ? `
                        <div class="bg-slate-950 p-2 rounded-xl border border-slate-800 text-center">
                            <span class="text-[10px] font-bold text-slate-400 block mb-1"><?= $isUrdu ? 'شناختی کارڈ فرنٹ' : 'CNIC Front' ?></span>
                            <img src="${record.cnic_front_photo}" class="w-full h-28 object-cover rounded-lg border border-slate-700" />
                        </div>
                    ` : ''}
                    ${record.cnic_back_photo ? `
                        <div class="bg-slate-950 p-2 rounded-xl border border-slate-800 text-center">
                            <span class="text-[10px] font-bold text-slate-400 block mb-1"><?= $isUrdu ? 'شناختی کارڈ بیک' : 'CNIC Back' ?></span>
                            <img src="${record.cnic_back_photo}" class="w-full h-28 object-cover rounded-lg border border-slate-700" />
                        </div>
                    ` : ''}
                    ${record.mobile_photo ? `
                        <div class="bg-slate-950 p-2 rounded-xl border border-slate-800 text-center">
                            <span class="text-[10px] font-bold text-slate-400 block mb-1"><?= $isUrdu ? 'موبائل فون' : 'Mobile Photo' ?></span>
                            <img src="${record.mobile_photo}" class="w-full h-28 object-cover rounded-lg border border-slate-700" />
                        </div>
                    ` : ''}
                    ${record.box_photo ? `
                        <div class="bg-slate-950 p-2 rounded-xl border border-slate-800 text-center">
                            <span class="text-[10px] font-bold text-slate-400 block mb-1"><?= $isUrdu ? 'ڈبہ / بل' : 'Box / Bill' ?></span>
                            <img src="${record.box_photo}" class="w-full h-28 object-cover rounded-lg border border-slate-700" />
                        </div>
                    ` : ''}
                    ${record.thumb_signature ? `
                        <div class="bg-slate-950 p-2 rounded-xl border border-slate-800 text-center">
                            <span class="text-[10px] font-bold text-slate-400 block mb-1"><?= $isUrdu ? 'دستخط / انگوٹھا' : 'Thumb Signature' ?></span>
                            <img src="${record.thumb_signature}" class="w-full h-28 object-contain rounded-lg border border-slate-700 bg-slate-900" />
                        </div>
                    ` : ''}
                </div>
            </div>
        `;

        document.getElementById('detailsModal').classList.remove('hidden');
        lucide.createIcons();
    }

    function closeDetailsModal() {
        document.getElementById('detailsModal').classList.add('hidden');
    }

    // -------------------------------------------------------------
    // PRINTABLE RECEIPT & LEGAL AFFIDAVIT GENERATOR
    // -------------------------------------------------------------
    function openReceiptModal(record) {
        currentActiveRecord = record;
        const printArea = document.getElementById('receiptPrintArea');
        const shopName = "<?= addslashes($shopName) ?>";
        const shopPhone = "<?= addslashes($settings['phone'] ?? '0331-9348330') ?>";
        const shopAddress = "<?= addslashes($settings['address'] ?? 'مین بازار روڈ') ?>";

        const recHtml = `
            <!-- CUSTOMER COPY / SHOP COPY -->
            <div class="border-2 border-black rounded-2xl p-6 space-y-4 text-black">
                
                <!-- Shop Header -->
                <div class="flex items-center justify-between border-b-2 border-black pb-3">
                    <div>
                        <h2 class="text-xl font-black uppercase tracking-tight">${shopName}</h2>
                        <p class="text-xs text-slate-700">${shopAddress} | فون: ${shopPhone}</p>
                    </div>
                    <div class="text-right">
                        <span class="px-3 py-1 bg-black text-white text-xs font-black rounded-lg uppercase tracking-wider block">
                            <?= $isUrdu ? 'خرید رسید و قانونی اقرار نامہ' : 'PURCHASE RECEIPT & AFFIDAVIT' ?>
                        </span>
                        <span class="text-xs font-mono font-bold text-slate-800 mt-1 block">
                            No: ${record.receipt_no || 'PUR-'+record.id}
                        </span>
                    </div>
                </div>

                <!-- Date & Seller / Mobile Specs -->
                <div class="grid grid-cols-2 gap-4 text-xs">
                    <div class="space-y-1 bg-slate-50 p-3 rounded-xl border border-slate-300">
                        <span class="font-extrabold text-slate-900 block border-b border-slate-200 pb-1"><?= $isUrdu ? 'بیچنے والے کے کوائف (Seller Info):' : 'Seller Information:' ?></span>
                        <p><strong><?= $isUrdu ? 'نام:' : 'Name:' ?></strong> ${record.seller_name}</p>
                        <p><strong><?= $isUrdu ? 'شناختی کارڈ:' : 'CNIC:' ?></strong> ${record.seller_cnic || 'N/A'}</p>
                        <p><strong><?= $isUrdu ? 'فون:' : 'Phone:' ?></strong> ${record.seller_phone || 'N/A'}</p>
                        <p><strong><?= $isUrdu ? 'پتہ:' : 'Address:' ?></strong> ${record.seller_address || 'N/A'}</p>
                    </div>

                    <div class="space-y-1 bg-slate-50 p-3 rounded-xl border border-slate-300">
                        <span class="font-extrabold text-slate-900 block border-b border-slate-200 pb-1"><?= $isUrdu ? 'موبائل تفصیلات (Device Specs):' : 'Mobile Specifications:' ?></span>
                        <p><strong><?= $isUrdu ? 'ماڈل:' : 'Model:' ?></strong> ${record.brand_or_model} (${record.condition_status === 'NEW' ? 'NEW' : 'USED'})</p>
                        <p><strong>IMEI 1:</strong> <span class="font-mono font-bold">${record.imei_1}</span></p>
                        ${record.imei_2 ? `<p><strong>IMEI 2:</strong> <span class="font-mono">${record.imei_2}</span></p>` : ''}
                        <p><strong><?= $isUrdu ? 'سامان:' : 'Accessories:' ?></strong> ${record.accessories || 'صرف موبائل'}</p>
                    </div>
                </div>

                <!-- Financial Statement -->
                <div class="flex items-center justify-between p-3.5 bg-emerald-50 rounded-xl border-2 border-emerald-800 text-xs">
                    <div>
                        <span class="font-bold text-emerald-950 block"><?= $isUrdu ? 'خریداری رقم بمبلغ وصول پائی:' : 'Total Purchase Price Paid:' ?></span>
                        <span class="text-lg font-black font-mono text-emerald-900">Rs ${Number(record.purchase_price).toLocaleString()}</span>
                    </div>
                    <div class="text-right">
                        <span class="text-xs text-slate-700 block"><?= $isUrdu ? 'تاریخ و وقت:' : 'Date & Time:' ?></span>
                        <span class="font-mono font-bold text-slate-900">${record.date} (${record.time})</span>
                    </div>
                </div>

                <!-- Legal Affidavit Declaration in Urdu -->
                <div class="p-3 bg-amber-50/70 rounded-xl border border-amber-300 text-[11px] leading-relaxed text-slate-900 text-right font-medium">
                    <p class="font-bold underline mb-1">قانونی بیان حلفی و اقرار نامہ برائے فروخت موبائل:</p>
                    <p>
                        منکہ مسمی <strong>${record.seller_name}</strong> شناختی کارڈ نمبر <strong>${record.seller_cnic}</strong> بقائمی ہوش و حواس اقرار کرتا ہوں کہ مذکورہ بالا موبائل میرے ذاتی استعمال میں تھا اور یہ کسی بھی قسم کی چوری، واردات یا غیر قانونی سرگرمی میں ملوث نہیں ہے۔ میں نے یہ موبائل بمبلغ <strong>Rs ${Number(record.purchase_price).toLocaleString()}</strong> وصول پا کر <strong>${shopName}</strong> کو فروخت کر دیا ہے۔ آئندہ کسی بھی قسم کی قانونی یا پولیس کارروائی کی تمام تر ذمہ داری مجھ پر عائد ہوگی۔
                    </p>
                </div>

                <!-- Signature Footer -->
                <div class="grid grid-cols-2 gap-8 pt-4 border-t-2 border-black">
                    <div class="text-center">
                        <p class="font-bold text-xs"><?= $isUrdu ? 'دستخط / انگوٹھا فروخت کنندہ (بیچنے والا)' : 'Seller Signature / Thumb' ?></p>
                        ${record.thumb_signature ? `
                            <img src="${record.thumb_signature}" class="h-14 mx-auto object-contain mt-1" />
                        ` : `
                            <div class="w-40 h-12 border border-dashed border-black mx-auto mt-2 rounded"></div>
                        `}
                    </div>
                    <div class="text-center">
                        <p class="font-bold text-xs"><?= $isUrdu ? 'دستخط و مہر دکاندار (خریدار)' : 'Shop Seal & Signature' ?></p>
                        <div class="w-40 h-12 border border-dashed border-black mx-auto mt-2 rounded flex items-center justify-center text-[10px] text-slate-400">
                            مہر دکان
                        </div>
                    </div>
                </div>

            </div>
        `;

        printArea.innerHTML = recHtml;
        document.getElementById('receiptModal').classList.remove('hidden');
        lucide.createIcons();
    }

    function closeReceiptModal() {
        document.getElementById('receiptModal').classList.add('hidden');
    }

    function printReceipt() {
        const printArea = document.getElementById('receiptPrintArea');
        const realContainer = document.getElementById('realPrintContainer');
        realContainer.innerHTML = printArea.innerHTML;
        window.print();
    }

    function shareReceiptWhatsApp() {
        if (!currentActiveRecord) return;
        const shopName = "<?= addslashes($shopName) ?>";
        const shopPhone = "<?= addslashes($settings['phone'] ?? '0331-9348330') ?>";

        const text = `*${shopName} - موبائل خریداری رسید*\n` +
            `رسید نمبر: ${currentActiveRecord.receipt_no || currentActiveRecord.id}\n` +
            `تاریخ: ${currentActiveRecord.date} (${currentActiveRecord.time})\n\n` +
            `*بیچنے والا:* ${currentActiveRecord.seller_name}\n` +
            `شناختی کارڈ: ${currentActiveRecord.seller_cnic}\n` +
            `فون: ${currentActiveRecord.seller_phone}\n\n` +
            `*موبائل تفصیل:* ${currentActiveRecord.brand_or_model} (${currentActiveRecord.condition_status === 'NEW' ? 'نیا ڈبہ پیک' : 'سیکنڈ ہینڈ'})\n` +
            `IMEI 1: ${currentActiveRecord.imei_1}\n` +
            `خریداری قیمت: Rs ${Number(currentActiveRecord.purchase_price).toLocaleString()}\n\n` +
            `رابطہ: ${shopPhone}`;

        const cleanPhone = currentActiveRecord.seller_phone ? currentActiveRecord.seller_phone.replace(/[^0-9]/g, '') : '';
        const url = cleanPhone ? `https://wa.me/${cleanPhone}?text=${encodeURIComponent(text)}` : `https://wa.me/?text=${encodeURIComponent(text)}`;
        window.open(url, '_blank');
    }

    function openScannerGun(targetInputId) {
        const imei = prompt("<?= $isUrdu ? 'بارکوڈ اسکینر گن سے اسکین کریں یا 15 ہندسوں کا IMEI درج کریں:' : 'Scan with barcode gun or type 15-digit IMEI:' ?>");
        if (imei && imei.trim()) {
            document.getElementById(targetInputId).value = imei.trim();
        }
    }

    // Auto trigger receipt if newly created
    <?php if ($receiptRecord): ?>
        window.addEventListener('DOMContentLoaded', () => {
            openReceiptModal(<?= json_encode($receiptRecord) ?>);
        });
    <?php endif; ?>
</script>

<?php require_once __DIR__ . '/../backend/footer.php'; ?>
