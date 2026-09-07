<?php
$pageTitle = 'اسٹاک انوینٹری (Stock Inventory)';
$activeMenu = 'inventory';
require_once __DIR__ . '/../backend/header.php';

$successMsg = '';
$errorMsg = '';

// Categories Definition
$categories = [
    'MOBILES' => ['name' => 'موبائل فونز', 'icon' => 'smartphone'],
    'CHARGERS' => ['name' => 'چارجرز و اڈاپٹرز', 'icon' => 'zap'],
    'EARPHONES' => ['name' => 'ہینڈز فری و ایئربڈز', 'icon' => 'headphones'],
    'CABLES' => ['name' => 'ڈیٹا کیبلز', 'icon' => 'cable'],
    'PROTECTORS' => ['name' => 'گلاس پروٹیکٹرز', 'icon' => 'shield'],
    'COVERS' => ['name' => 'موبائل کوورز', 'icon' => 'layers'],
    'BATTERIES' => ['name' => 'بیٹریز و پاور بینکس', 'icon' => 'battery-charging'],
    'ACCESSORIES' => ['name' => 'دیگر اسیسریز', 'icon' => 'package']
];

// ==========================================================
// 1. Handle Add / Edit / Delete / Quick Stock Form Submissions
// ==========================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    // ADD or EDIT Product
    if ($action === 'save_product') {
        $id = trim($_POST['id'] ?? '');
        $isEdit = !empty($id);

        $name = trim($_POST['name'] ?? '');
        $category = $_POST['category'] ?? 'ACCESSORIES';
        $brandOrModel = trim($_POST['brand_or_model'] ?? '');
        $purchasePrice = floatval($_POST['purchase_price'] ?? 0);
        $salePrice = floatval($_POST['sale_price'] ?? 0);
        $stock = intval($_POST['stock'] ?? 0);
        $sku = trim($_POST['sku'] ?? '');
        $imei = trim($_POST['imei_or_serial'] ?? '');
        $color = trim($_POST['color'] ?? '');
        $ramStorage = trim($_POST['ram_storage'] ?? '');
        $ptaStatus = $_POST['pta_status'] ?? 'PTA_APPROVED';
        $conditionStatus = $_POST['condition_status'] ?? 'NEW';
        $warranty = trim($_POST['warranty'] ?? '');
        $wattage = trim($_POST['wattage'] ?? '');

        // Auto SKU if empty
        if (empty($sku)) {
            $sku = 'SKU-' . strtoupper(substr($category, 0, 3)) . '-' . rand(1000, 9999);
        }

        // Units JSON handling
        $unitsJson = trim($_POST['units_json'] ?? '[]');
        $parsedUnits = json_decode($unitsJson, true);
        if (!is_array($parsedUnits)) {
            $parsedUnits = [];
        }

        // If category is MOBILES and stock > count of units, or single imei provided
        if ($category === 'MOBILES' && empty($parsedUnits) && !empty($imei)) {
            $parsedUnits[] = [
                'id' => 'u-' . uniqid(),
                'imei1' => $imei,
                'imei2' => '',
                'color' => $color ?: 'Black',
                'storageRam' => $ramStorage ?: '4GB/64GB',
                'condition' => ($conditionStatus === 'NEW' ? 'Box Pack (New)' : 'Used'),
                'ptaStatus' => $ptaStatus,
                'status' => 'AVAILABLE'
            ];
        }

        // If units are present for mobiles, synchronize stock count with available units
        if ($category === 'MOBILES' && !empty($parsedUnits)) {
            $availCount = 0;
            foreach ($parsedUnits as $u) {
                if (($u['status'] ?? 'AVAILABLE') === 'AVAILABLE') {
                    $availCount++;
                }
            }
            if ($availCount > 0) {
                $stock = $availCount;
            }
        }

        $unitsJsonString = json_encode($parsedUnits, JSON_UNESCAPED_UNICODE);

        if (empty($name) || $salePrice <= 0) {
            $errorMsg = 'برائے مہربانی پروڈکٹ کا نام اور درست فروخت قیمت درج کریں!';
        } else {
            try {
                if ($isEdit) {
                    $stmt = $pdo->prepare("UPDATE products SET 
                        name = :name,
                        category = :category,
                        brand_or_model = :brand_or_model,
                        purchase_price = :purchase_price,
                        sale_price = :sale_price,
                        stock = :stock,
                        sku = :sku,
                        imei_or_serial = :imei_or_serial,
                        color = :color,
                        ram_storage = :ram_storage,
                        pta_status = :pta_status,
                        condition_status = :condition_status,
                        warranty = :warranty,
                        wattage = :wattage,
                        units_json = :units_json
                        WHERE id = :id");

                    $stmt->execute([
                        ':name' => $name,
                        ':category' => $category,
                        ':brand_or_model' => $brandOrModel,
                        ':purchase_price' => $purchasePrice,
                        ':sale_price' => $salePrice,
                        ':stock' => $stock,
                        ':sku' => $sku,
                        ':imei_or_serial' => $imei,
                        ':color' => $color,
                        ':ram_storage' => $ramStorage,
                        ':pta_status' => $ptaStatus,
                        ':condition_status' => $conditionStatus,
                        ':warranty' => $warranty,
                        ':wattage' => $wattage,
                        ':units_json' => $unitsJsonString,
                        ':id' => $id
                    ]);
                    $successMsg = "پروڈکٹ \"{$name}\" کی تفصیلات کامیابی سے اپڈیٹ ہو گئیں!";
                } else {
                    $newId = 'prod-' . uniqid();
                    $createdAt = time();

                    $stmt = $pdo->prepare("INSERT INTO products (
                        id, name, category, brand_or_model, purchase_price, sale_price, stock,
                        sku, imei_or_serial, color, ram_storage, pta_status, condition_status,
                        warranty, wattage, units_json, created_at
                    ) VALUES (
                        :id, :name, :category, :brand_or_model, :purchase_price, :sale_price, :stock,
                        :sku, :imei_or_serial, :color, :ram_storage, :pta_status, :condition_status,
                        :warranty, :wattage, :units_json, :created_at
                    )");

                    $stmt->execute([
                        ':id' => $newId,
                        ':name' => $name,
                        ':category' => $category,
                        ':brand_or_model' => $brandOrModel,
                        ':purchase_price' => $purchasePrice,
                        ':sale_price' => $salePrice,
                        ':stock' => $stock,
                        ':sku' => $sku,
                        ':imei_or_serial' => $imei,
                        ':color' => $color,
                        ':ram_storage' => $ramStorage,
                        ':pta_status' => $ptaStatus,
                        ':condition_status' => $conditionStatus,
                        ':warranty' => $warranty,
                        ':wattage' => $wattage,
                        ':units_json' => $unitsJsonString,
                        ':created_at' => $createdAt
                    ]);
                    $successMsg = "نئی پروڈکٹ \"{$name}\" اسٹاک میں کامیابی سے شامل کر دی گئی!";
                }
            } catch (Exception $e) {
                $errorMsg = 'ڈیٹا بیس خرابی: ' . $e->getMessage();
            }
        }
    } elseif ($action === 'delete_product') {
        $delId = trim($_POST['id'] ?? '');
        if (!empty($delId)) {
            $pdo->prepare("DELETE FROM products WHERE id = :id")->execute([':id' => $delId]);
            $successMsg = 'پروڈکٹ اسٹاک سے کامیابی کے ساتھ ڈیلیٹ کر دی گئی!';
        }
    } elseif ($action === 'quick_stock') {
        $prodId = trim($_POST['id'] ?? '');
        $delta = intval($_POST['delta'] ?? 0);
        if (!empty($prodId)) {
            $pdo->prepare("UPDATE products SET stock = GREATEST(0, stock + :delta) WHERE id = :id")->execute([
                ':delta' => $delta,
                ':id' => $prodId
            ]);
            $successMsg = 'اسٹاک تعداد کامیابی سے اپڈیٹ ہو گئی!';
        }
    } elseif ($action === 'save_bulk_stock') {
        $entryMode = $_POST['entry_mode'] ?? 'existing';
        $productId = trim($_POST['product_id'] ?? '');
        $brandOrModel = trim($_POST['brand_or_model'] ?? '');
        $category = $_POST['category'] ?? 'MOBILES';
        $purchasePrice = floatval($_POST['purchase_price'] ?? 0);
        $salePrice = floatval($_POST['sale_price'] ?? 0);
        $defaultColor = trim($_POST['default_color'] ?? 'Black');
        $ramStorage = trim($_POST['ram_storage'] ?? '4GB / 64GB');
        $ptaStatus = $_POST['pta_status'] ?? 'PTA_APPROVED';
        $conditionStatus = $_POST['condition_status'] ?? 'NEW';
        $warranty = trim($_POST['warranty'] ?? '');
        $sku = trim($_POST['sku'] ?? '');

        $bulkUnitsJson = trim($_POST['bulk_units_json'] ?? '[]');
        $newUnits = json_decode($bulkUnitsJson, true);
        if (!is_array($newUnits)) {
            $newUnits = [];
        }

        $unitsCount = count($newUnits);

        if ($unitsCount === 0) {
            $errorMsg = 'براہ کرم کم از کم ایک فون کا IMEI یا یونٹ شامل کریں!';
        } elseif ($purchasePrice <= 0 || $salePrice <= 0) {
            $errorMsg = 'براہ کرم فی فون خرید قیمت اور فروخت قیمت درست درج کریں!';
        } elseif ($entryMode === 'existing' && empty($productId)) {
            $errorMsg = 'براہ کرم موجودہ اسٹاک میں سے پروڈکٹ منتخب کریں!';
        } elseif ($entryMode === 'new' && empty($brandOrModel)) {
            $errorMsg = 'براہ کرم نئے فون کا برانڈ یا ماڈل نام درج کریں!';
        } else {
            try {
                if ($entryMode === 'existing') {
                    $stmtFetch = $pdo->prepare("SELECT * FROM products WHERE id = :id");
                    $stmtFetch->execute([':id' => $productId]);
                    $existingProd = $stmtFetch->fetch();

                    if (!$existingProd) {
                        $errorMsg = 'منتخب شدہ پروڈکٹ ڈیٹا بیس میں نہیں ملی!';
                    } else {
                        $existingUnits = json_decode($existingProd['units_json'] ?? '[]', true);
                        if (!is_array($existingUnits)) {
                            $existingUnits = [];
                        }

                        // Merge new units
                        $mergedUnits = array_merge($existingUnits, $newUnits);
                        $availCount = 0;
                        foreach ($mergedUnits as $u) {
                            if (($u['status'] ?? 'AVAILABLE') === 'AVAILABLE') {
                                $availCount++;
                            }
                        }
                        $newStock = ($availCount > 0) ? $availCount : (intval($existingProd['stock']) + $unitsCount);

                        $firstImei = !empty($existingProd['imei_or_serial']) ? $existingProd['imei_or_serial'] : ($newUnits[0]['imei1'] ?? '');

                        $stmtUpdate = $pdo->prepare("UPDATE products SET 
                            stock = :stock,
                            purchase_price = :purchase_price,
                            sale_price = :sale_price,
                            imei_or_serial = :imei_or_serial,
                            units_json = :units_json
                            WHERE id = :id");

                        $stmtUpdate->execute([
                            ':stock' => $newStock,
                            ':purchase_price' => $purchasePrice ?: $existingProd['purchase_price'],
                            ':sale_price' => $salePrice ?: $existingProd['sale_price'],
                            ':imei_or_serial' => $firstImei,
                            ':units_json' => json_encode($mergedUnits, JSON_UNESCAPED_UNICODE),
                            ':id' => $productId
                        ]);

                        $prodName = $existingProd['name'];
                        $successMsg = "پروڈکٹ \"{$prodName}\" میں {$unitsCount} نئے فونز (IMEIs) کامیابی سے شامل کر دیے گئے! کل دستیاب اسٹاک اب {$newStock} دانے ہے۔";
                    }
                } else {
                    // New product creation
                    $condLabel = ($conditionStatus === 'NEW') ? 'Pin Pack' : 'Used';
                    $ptaLabel = ($ptaStatus === 'PTA_APPROVED') ? 'PTA' : 'Non-PTA';
                    $autoTitle = "{$brandOrModel} ({$defaultColor}) - {$condLabel} [{$ptaLabel}]";
                    
                    if (empty($sku)) {
                        $firstImei = $newUnits[0]['imei1'] ?? '';
                        $sku = $firstImei ? $firstImei : ('SKU-MOB-' . rand(1000, 9999));
                    }
                    $firstImei = $newUnits[0]['imei1'] ?? '';

                    $newId = 'prod-' . uniqid();
                    $createdAt = time();

                    $stmtInsert = $pdo->prepare("INSERT INTO products (
                        id, name, category, brand_or_model, purchase_price, sale_price, stock,
                        sku, imei_or_serial, color, ram_storage, pta_status, condition_status,
                        warranty, wattage, units_json, created_at
                    ) VALUES (
                        :id, :name, :category, :brand_or_model, :purchase_price, :sale_price, :stock,
                        :sku, :imei_or_serial, :color, :ram_storage, :pta_status, :condition_status,
                        :warranty, :wattage, :units_json, :created_at
                    )");

                    $stmtInsert->execute([
                        ':id' => $newId,
                        ':name' => $autoTitle,
                        ':category' => 'MOBILES',
                        ':brand_or_model' => $brandOrModel,
                        ':purchase_price' => $purchasePrice,
                        ':sale_price' => $salePrice,
                        ':stock' => $unitsCount,
                        ':sku' => $sku,
                        ':imei_or_serial' => $firstImei,
                        ':color' => $defaultColor,
                        ':ram_storage' => $ramStorage,
                        ':pta_status' => $ptaStatus,
                        ':condition_status' => $conditionStatus,
                        ':warranty' => $warranty,
                        ':wattage' => '',
                        ':units_json' => json_encode($newUnits, JSON_UNESCAPED_UNICODE),
                        ':created_at' => $createdAt
                    ]);

                    $successMsg = "نیا موبائل ماڈل \"{$autoTitle}\" اور {$unitsCount} نئے فونز بلک اسٹاک میں کامیابی سے محفوظ ہو گئے!";
                }
            } catch (Exception $e) {
                $errorMsg = 'بلک اسٹاک محفوظ کرنے میں خرابی: ' . $e->getMessage();
            }
        }
    }
}

// ==========================================================
// 2. CSV Export Handling
// ==========================================================
if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    $expCat = $_GET['cat'] ?? 'all';
    $expSearch = trim($_GET['q'] ?? '');

    $expSql = "SELECT * FROM products WHERE 1=1";
    $expParams = [];
    if ($expCat !== 'all' && !empty($expCat)) {
        $expSql .= " AND category = :cat";
        $expParams[':cat'] = $expCat;
    }
    if (!empty($expSearch)) {
        $expSql .= " AND (name LIKE :q OR brand_or_model LIKE :q OR sku LIKE :q OR imei_or_serial LIKE :q)";
        $expParams[':q'] = "%{$expSearch}%";
    }
    $expSql .= " ORDER BY category ASC, name ASC";

    $stmtExp = $pdo->prepare($expSql);
    $stmtExp->execute($expParams);
    $expProducts = $stmtExp->fetchAll();

    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="Stock_Inventory_' . date('Ymd_His') . '.csv"');
    $out = fopen('php://output', 'w');
    // Add UTF-8 BOM
    fprintf($out, chr(0xEF).chr(0xBB).chr(0xBF));

    fputcsv($out, ['پروڈکٹ کا نام', 'کیٹیگری', 'ماڈل / برانڈ', 'بارکوڈ / SKU', 'IMEI', 'قیمت خرید', 'قیمت فروخت', 'اسٹاک تعداد', 'کل خریداری مالیت', 'کل فروخت مالیت', 'متوقع منافع']);
    foreach ($expProducts as $p) {
        $cVal = $p['purchase_price'] * $p['stock'];
        $sVal = $p['sale_price'] * $p['stock'];
        $pVal = $sVal - $cVal;
        fputcsv($out, [
            $p['name'],
            $categories[$p['category']]['name'] ?? $p['category'],
            $p['brand_or_model'] ?? '',
            $p['sku'] ?? '',
            $p['imei_or_serial'] ?? '',
            $p['purchase_price'],
            $p['sale_price'],
            $p['stock'],
            $cVal,
            $sVal,
            $pVal
        ]);
    }
    fclose($out);
    exit;
}

// ==========================================================
// 3. Filters, Search & Categories Counting
// ==========================================================
$catFilter = $_GET['cat'] ?? 'all';
$stockStatusFilter = $_GET['status'] ?? 'all'; // all, in_stock, low_stock, out_stock
$search = trim($_GET['q'] ?? '');
$viewMode = $_GET['view'] ?? 'list'; // 'list' or 'grid'

// Fetch all counts for categories & overall KPIs
$allProducts = $pdo->query("SELECT * FROM products ORDER BY created_at DESC")->fetchAll();

$totalItemsCount = count($allProducts);
$totalUnitsInStock = 0;
$totalPurchaseValuation = 0;
$totalSaleValuation = 0;
$totalLowStockCount = 0;
$totalOutStockCount = 0;

$catCounts = [];
foreach ($categories as $k => $v) {
    $catCounts[$k] = 0;
}

foreach ($allProducts as $p) {
    $stk = intval($p['stock']);
    $cst = floatval($p['purchase_price']);
    $sle = floatval($p['sale_price']);

    $totalUnitsInStock += $stk;
    $totalPurchaseValuation += ($cst * $stk);
    $totalSaleValuation += ($sle * $stk);

    if ($stk === 0) {
        $totalOutStockCount++;
        $totalLowStockCount++;
    } elseif ($stk <= 3) {
        $totalLowStockCount++;
    }

    if (isset($catCounts[$p['category']])) {
        $catCounts[$p['category']]++;
    }
}

$totalExpectedProfit = $totalSaleValuation - $totalPurchaseValuation;
$overallMarginPercent = $totalSaleValuation > 0 ? round(($totalExpectedProfit / $totalSaleValuation) * 100, 1) : 0;

// Filtered products list
$sql = "SELECT * FROM products WHERE 1=1";
$params = [];

if ($catFilter !== 'all') {
    $sql .= " AND category = :cat";
    $params[':cat'] = $catFilter;
}

if ($stockStatusFilter === 'in_stock') {
    $sql .= " AND stock > 0";
} elseif ($stockStatusFilter === 'low_stock') {
    $sql .= " AND stock <= 3 AND stock > 0";
} elseif ($stockStatusFilter === 'out_stock') {
    $sql .= " AND stock = 0";
}

if (!empty($search)) {
    $sql .= " AND (name LIKE :q OR brand_or_model LIKE :q OR sku LIKE :q OR imei_or_serial LIKE :q OR color LIKE :q)";
    $params[':q'] = "%{$search}%";
}

$sql .= " ORDER BY stock ASC, name ASC";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$filteredProducts = $stmt->fetchAll();
?>

<!-- Print Styles -->
<style>
@media print {
    body { background: #fff !important; color: #000 !important; }
    .no-print { display: none !important; }
    .print-only { display: block !important; }
    table { width: 100% !important; border-collapse: collapse !important; }
    th, td { border: 1px solid #ccc !important; padding: 6px !important; color: #000 !important; }
}
</style>

<!-- Alert Banners -->
<?php if (!empty($successMsg)): ?>
    <div class="mb-4 p-4 rounded-2xl bg-emerald-500/10 border border-emerald-500/30 text-emerald-300 flex items-center justify-between shadow-lg no-print">
        <div class="flex items-center gap-3">
            <div class="p-2 rounded-xl bg-emerald-500/20 text-emerald-400">
                <i data-lucide="check-circle-2" class="w-5 h-5"></i>
            </div>
            <p class="text-xs font-bold"><?= htmlspecialchars($successMsg) ?></p>
        </div>
        <button onclick="this.parentElement.remove()" class="text-slate-400 hover:text-white p-1">
            <i data-lucide="x" class="w-4 h-4"></i>
        </button>
    </div>
<?php endif; ?>

<?php if (!empty($errorMsg)): ?>
    <div class="mb-4 p-4 rounded-2xl bg-rose-500/10 border border-rose-500/30 text-rose-300 flex items-center gap-3 shadow-lg no-print">
        <div class="p-2 rounded-xl bg-rose-500/20 text-rose-400">
            <i data-lucide="alert-triangle" class="w-5 h-5"></i>
        </div>
        <p class="text-xs font-bold"><?= htmlspecialchars($errorMsg) ?></p>
    </div>
<?php endif; ?>

<!-- ==========================================================
     Top Header & Action Bar
     ========================================================== -->
<div class="bg-slate-900 border border-slate-800 p-5 rounded-3xl mb-6 shadow-xl no-print space-y-4">
    <div class="flex flex-col lg:flex-row items-stretch lg:items-center justify-between gap-4">
        <!-- Title & Stats -->
        <div class="flex items-center gap-3.5">
            <div class="w-12 h-12 rounded-2xl bg-gradient-to-br from-cyan-500/20 to-blue-500/10 border border-cyan-500/30 text-cyan-400 flex items-center justify-center font-bold shrink-0 shadow-inner">
                <i data-lucide="boxes" class="w-6 h-6"></i>
            </div>
            <div>
                <h1 class="text-lg sm:text-xl font-black text-white flex items-center gap-2">
                    <span>اسٹاک انوینٹری مینجمنٹ</span>
                    <span class="text-xs font-mono font-bold px-2 py-0.5 rounded-full bg-cyan-500/10 text-cyan-400 border border-cyan-500/20">
                        <?= $totalItemsCount ?> ورائٹی / <?= $totalUnitsInStock ?> دانے
                    </span>
                </h1>
                <p class="text-xs text-slate-400 mt-0.5">
                    موبائل فونز کے IMEI، اسیسریز کا اسٹاک، خریداری قیمت، فروخت ریٹ اور اسٹاک مالیت کا مکمل انتظام
                </p>
            </div>
        </div>

        <!-- Action Buttons -->
        <div class="flex flex-wrap items-center gap-2 shrink-0">
            <!-- Bulk Stock & Multi-IMEI Entry Button (Primary Highlight) -->
            <button type="button" onclick="openBulkStockModal()" class="px-4 py-2.5 bg-gradient-to-r from-teal-600 via-emerald-600 to-indigo-600 hover:from-teal-500 hover:to-indigo-500 text-white rounded-xl text-xs font-black flex items-center gap-2 shadow-lg shadow-emerald-900/30 transition-all active:scale-95 border border-emerald-400/30">
                <i data-lucide="package-plus" class="w-4 h-4"></i>
                <span>بلک اسٹاک و ملٹی IMEI اینٹری</span>
            </button>

            <!-- Add Product Button -->
            <button type="button" onclick="openProductModal()" class="px-4 py-2.5 bg-emerald-600 hover:bg-emerald-500 text-white rounded-xl text-xs font-bold flex items-center gap-2 shadow-lg transition-all active:scale-95">
                <i data-lucide="plus-circle" class="w-4 h-4"></i>
                <span>نئی پروڈکٹ شامل کریں</span>
            </button>

            <!-- Export CSV Button -->
            <?php
            $exportUrl = "index.php?export=csv&cat=" . urlencode($catFilter) . "&q=" . urlencode($search);
            ?>
            <a href="<?= $exportUrl ?>" class="px-3.5 py-2.5 bg-slate-800 hover:bg-slate-700 text-slate-200 border border-slate-700 rounded-xl text-xs font-bold flex items-center gap-2 transition-all shadow">
                <i data-lucide="download" class="w-4 h-4 text-emerald-400"></i>
                <span>ایکسل شیٹ ڈاؤن لوڈ</span>
            </a>

            <!-- Print Stock List -->
            <button type="button" onclick="window.print()" class="px-3.5 py-2.5 bg-slate-800 hover:bg-slate-700 text-slate-300 border border-slate-700 rounded-xl text-xs font-bold flex items-center gap-1.5 transition-all">
                <i data-lucide="printer" class="w-4 h-4 text-cyan-400"></i>
                <span>پرنٹ لسٹ</span>
            </button>
        </div>
    </div>

    <!-- Category Pills Tabs -->
    <div class="flex items-center gap-2 overflow-x-auto pb-1 text-xs border-t border-slate-800/80 pt-3">
        <span class="text-slate-500 font-bold shrink-0 ml-1">کیٹیگری:</span>
        <a href="index.php?cat=all&status=<?= $stockStatusFilter ?>&view=<?= $viewMode ?>" 
           class="px-3 py-1.5 rounded-xl font-bold shrink-0 transition-all flex items-center gap-1.5 <?= $catFilter === 'all' ? 'bg-cyan-600 text-white shadow' : 'bg-slate-950 text-slate-400 hover:bg-slate-800 border border-slate-800' ?>">
            <span>تمام اشیاء</span>
            <span class="text-[10px] px-1.5 py-0.2 rounded-full <?= $catFilter === 'all' ? 'bg-cyan-800 text-white' : 'bg-slate-800 text-slate-400' ?>"><?= $totalItemsCount ?></span>
        </a>

        <?php foreach ($categories as $catKey => $catInfo): ?>
            <a href="index.php?cat=<?= $catKey ?>&status=<?= $stockStatusFilter ?>&view=<?= $viewMode ?>" 
               class="px-3 py-1.5 rounded-xl font-bold shrink-0 transition-all flex items-center gap-1.5 <?= $catFilter === catKey ? 'bg-cyan-600 text-white shadow' : 'bg-slate-950 text-slate-400 hover:bg-slate-800 border border-slate-800' ?>">
                <i data-lucide="<?= $catInfo['icon'] ?>" class="w-3.5 h-3.5"></i>
                <span><?= $catInfo['name'] ?></span>
                <span class="text-[10px] px-1.5 py-0.2 rounded-full <?= $catFilter === $catKey ? 'bg-cyan-800 text-white' : 'bg-slate-800 text-slate-400' ?>"><?= $catCounts[$catKey] ?? 0 ?></span>
            </a>
        <?php endforeach; ?>
    </div>

    <!-- Search & Stock Status Row -->
    <form method="GET" class="grid grid-cols-1 sm:grid-cols-12 gap-3 pt-2">
        <input type="hidden" name="cat" value="<?= htmlspecialchars($catFilter) ?>">
        <input type="hidden" name="view" value="<?= htmlspecialchars($viewMode) ?>">

        <!-- Search Bar (6 cols) -->
        <div class="sm:col-span-6">
            <div class="relative">
                <input type="text" name="q" value="<?= htmlspecialchars($search) ?>" placeholder="پروڈکٹ کا نام، ماڈل، بارکوڈ یا IMEI لکھ کر تلاش کریں..." class="w-full bg-slate-950 border border-slate-700 text-white pr-9 pl-3 py-2 rounded-xl text-xs focus:border-cyan-500 focus:outline-none">
                <i data-lucide="search" class="w-4 h-4 absolute right-3 top-1/2 -translate-y-1/2 text-slate-500 pointer-events-none"></i>
            </div>
        </div>

        <!-- Stock Status (3 cols) -->
        <div class="sm:col-span-3">
            <select name="status" onchange="this.form.submit()" class="w-full bg-slate-950 border border-slate-700 text-white px-3 py-2 rounded-xl text-xs focus:border-cyan-500 focus:outline-none">
                <option value="all" <?= $stockStatusFilter === 'all' ? 'selected' : '' ?>>تمام اسٹاک اسٹیٹس</option>
                <option value="in_stock" <?= $stockStatusFilter === 'in_stock' ? 'selected' : '' ?>>دستیاب اسٹاک (In Stock > 0)</option>
                <option value="low_stock" <?= $stockStatusFilter === 'low_stock' ? 'selected' : '' ?>>⚠️ کم اسٹاک (1 تا 3 دانے)</option>
                <option value="out_stock" <?= $stockStatusFilter === 'out_stock' ? 'selected' : '' ?>>❌ ختم اسٹاک (0 دانے)</option>
            </select>
        </div>

        <!-- View Mode (3 cols) -->
        <div class="sm:col-span-3 flex items-center justify-end gap-2">
            <div class="bg-slate-950 p-1 rounded-xl border border-slate-800 flex items-center">
                <a href="index.php?cat=<?= urlencode($catFilter) ?>&status=<?= urlencode($stockStatusFilter) ?>&q=<?= urlencode($search) ?>&view=list" 
                   class="px-3 py-1.5 rounded-lg text-xs font-bold flex items-center gap-1.5 <?= $viewMode === 'list' ? 'bg-cyan-600 text-white' : 'text-slate-400 hover:text-white' ?>" title="لسٹ ویو">
                    <i data-lucide="list" class="w-3.5 h-3.5"></i>
                    <span>لسٹ</span>
                </a>
                <a href="index.php?cat=<?= urlencode($catFilter) ?>&status=<?= urlencode($stockStatusFilter) ?>&q=<?= urlencode($search) ?>&view=grid" 
                   class="px-3 py-1.5 rounded-lg text-xs font-bold flex items-center gap-1.5 <?= $viewMode === 'grid' ? 'bg-cyan-600 text-white' : 'text-slate-400 hover:text-white' ?>" title="گرڈ کارڈز">
                    <i data-lucide="layout-grid" class="w-3.5 h-3.5"></i>
                    <span>گرڈ</span>
                </a>
            </div>
            <button type="submit" class="px-4 py-2 bg-slate-800 hover:bg-slate-700 text-white font-bold text-xs rounded-xl border border-slate-700">تلاش</button>
        </div>
    </form>
</div>

<!-- ==========================================================
     Inventory Valuation & KPIs Summary (6 Cards)
     ========================================================== -->
<div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-6 gap-3 sm:gap-4 mb-6">
    
    <!-- 1. Total Unique Items -->
    <div class="bg-slate-900 border border-slate-800 p-4 rounded-2xl shadow-md hover:border-cyan-500/40 transition-colors">
        <div class="flex items-center justify-between text-slate-400 text-xs font-bold mb-1">
            <span>کل پروڈکٹس</span>
            <div class="p-1.5 rounded-lg bg-cyan-500/10 text-cyan-400">
                <i data-lucide="package" class="w-4 h-4"></i>
            </div>
        </div>
        <h3 class="text-base sm:text-xl font-black text-white mt-1 font-mono"><?= $totalItemsCount ?> ورائٹی</h3>
        <p class="text-[10px] text-slate-500 mt-1">رجسٹرڈ پروڈکٹس</p>
    </div>

    <!-- 2. Total Units In Stock -->
    <div class="bg-slate-900 border border-slate-800 p-4 rounded-2xl shadow-md hover:border-blue-500/40 transition-colors">
        <div class="flex items-center justify-between text-slate-400 text-xs font-bold mb-1">
            <span>کل اسٹاک تعداد</span>
            <div class="p-1.5 rounded-lg bg-blue-500/10 text-blue-400">
                <i data-lucide="layers" class="w-4 h-4"></i>
            </div>
        </div>
        <h3 class="text-base sm:text-xl font-black text-blue-400 mt-1 font-mono"><?= number_format($totalUnitsInStock) ?> دانے</h3>
        <p class="text-[10px] text-slate-500 mt-1">دکان پر موجود مال</p>
    </div>

    <!-- 3. Total Purchase Cost Valuation -->
    <div class="bg-slate-900 border border-slate-800 p-4 rounded-2xl shadow-md hover:border-indigo-500/40 transition-colors">
        <div class="flex items-center justify-between text-slate-400 text-xs font-bold mb-1">
            <span>خریداری مالیت</span>
            <div class="p-1.5 rounded-lg bg-indigo-500/10 text-indigo-400">
                <i data-lucide="shopping-cart" class="w-4 h-4"></i>
            </div>
        </div>
        <h3 class="text-base sm:text-xl font-black text-slate-300 mt-1 font-mono">Rs. <?= number_format($totalPurchaseValuation) ?></h3>
        <p class="text-[10px] text-slate-500 mt-1">لاگت برائے موجودہ مال</p>
    </div>

    <!-- 4. Total Expected Sale Value -->
    <div class="bg-slate-900 border border-slate-800 p-4 rounded-2xl shadow-md hover:border-emerald-500/40 transition-colors">
        <div class="flex items-center justify-between text-slate-400 text-xs font-bold mb-1">
            <span>فروخت کی مالیت</span>
            <div class="p-1.5 rounded-lg bg-emerald-500/10 text-emerald-400">
                <i data-lucide="dollar-sign" class="w-4 h-4"></i>
            </div>
        </div>
        <h3 class="text-base sm:text-xl font-black text-white mt-1 font-mono">Rs. <?= number_format($totalSaleValuation) ?></h3>
        <p class="text-[10px] text-slate-500 mt-1">متوقع کل فروخت آمدنی</p>
    </div>

    <!-- 5. Potential Profit -->
    <div class="bg-slate-900 border border-slate-800 p-4 rounded-2xl shadow-md hover:border-teal-500/40 transition-colors">
        <div class="flex items-center justify-between text-slate-400 text-xs font-bold mb-1">
            <span>متوقع منافع</span>
            <div class="p-1.5 rounded-lg bg-teal-500/10 text-teal-400">
                <i data-lucide="trending-up" class="w-4 h-4"></i>
            </div>
        </div>
        <h3 class="text-base sm:text-xl font-black text-teal-400 mt-1 font-mono">Rs. <?= number_format($totalExpectedProfit) ?></h3>
        <p class="text-[10px] text-teal-400/80 mt-1 font-bold">مارجن: <?= $overallMarginPercent ?>%</p>
    </div>

    <!-- 6. Low / Out of Stock Alert -->
    <div class="bg-slate-900 border border-slate-800 p-4 rounded-2xl shadow-md hover:border-rose-500/40 transition-colors">
        <div class="flex items-center justify-between text-slate-400 text-xs font-bold mb-1">
            <span>کم اسٹاک الرٹ</span>
            <div class="p-1.5 rounded-lg bg-rose-500/10 text-rose-400">
                <i data-lucide="alert-circle" class="w-4 h-4"></i>
            </div>
        </div>
        <h3 class="text-base sm:text-xl font-black <?= $totalLowStockCount > 0 ? 'text-rose-400' : 'text-slate-400' ?> mt-1 font-mono">
            <?= $totalLowStockCount ?> اشیاء
        </h3>
        <p class="text-[10px] text-slate-500 mt-1"><?= $totalOutStockCount ?> مکمل ختم</p>
    </div>

</div>

<!-- ==========================================================
     Products Display: LIST VIEW or GRID VIEW
     ========================================================== -->
<?php if (empty($filteredProducts)): ?>
    <div class="bg-slate-900 border border-slate-800 rounded-3xl p-16 text-center text-slate-400 space-y-3">
        <div class="w-16 h-16 rounded-full bg-slate-800 flex items-center justify-center mx-auto text-slate-600">
            <i data-lucide="package-search" class="w-8 h-8"></i>
        </div>
        <h4 class="text-base font-bold text-white">کوئی پروڈکٹ نہیں ملی</h4>
        <p class="text-xs text-slate-500 max-w-md mx-auto">آپ کے تلاش کردہ نام، کیٹیگری یا فلٹر سے کوئی آئٹم میچ نہیں ہوا۔ تلاش کلیئر کریں یا نئی پروڈکٹ شامل کریں۔</p>
        <div class="pt-2 flex items-center justify-center gap-2">
            <a href="index.php" class="px-4 py-2 bg-slate-800 hover:bg-slate-700 text-slate-200 text-xs font-bold rounded-xl border border-slate-700 inline-flex items-center gap-1.5">
                <i data-lucide="rotate-ccw" class="w-3.5 h-3.5"></i>
                <span>فلٹر کلیئر کریں</span>
            </a>
            <button type="button" onclick="openProductModal()" class="px-4 py-2 bg-emerald-600 hover:bg-emerald-500 text-white text-xs font-bold rounded-xl inline-flex items-center gap-1.5">
                <i data-lucide="plus" class="w-3.5 h-3.5"></i>
                <span>نئی پروڈکٹ بنائیں</span>
            </button>
        </div>
    </div>
<?php elseif ($viewMode === 'grid'): ?>
    <!-- GRID VIEW -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
        <?php foreach ($filteredProducts as $p): 
            $units = json_decode($p['units_json'] ?? '[]', true);
            if (!is_array($units)) $units = [];
            $stk = intval($p['stock']);
            $profitPerUnit = floatval($p['sale_price']) - floatval($p['purchase_price']);
            $productJson = htmlspecialchars(json_encode($p), ENT_QUOTES, 'UTF-8');
        ?>
            <div class="bg-slate-900 border border-slate-800 rounded-3xl p-4 flex flex-col justify-between hover:border-cyan-500/40 transition-all shadow-lg group">
                <div>
                    <!-- Top badge & Stock pill -->
                    <div class="flex items-center justify-between gap-2 mb-3">
                        <span class="px-2.5 py-0.5 rounded-full text-[10px] font-bold bg-slate-800 text-cyan-300 border border-slate-700 flex items-center gap-1">
                            <i data-lucide="<?= $categories[$p['category']]['icon'] ?? 'package' ?>" class="w-3 h-3"></i>
                            <span><?= $categories[$p['category']]['name'] ?? $p['category'] ?></span>
                        </span>
                        
                        <span class="px-2.5 py-0.5 rounded-full font-bold font-mono text-xs <?= $stk === 0 ? 'bg-rose-500/20 text-rose-300 border border-rose-500/40' : ($stk <= 3 ? 'bg-amber-500/20 text-amber-300 border border-amber-500/40' : 'bg-emerald-500/10 text-emerald-300 border border-emerald-500/20') ?>">
                            اسٹاک: <?= $stk ?>
                        </span>
                    </div>

                    <!-- Product Name & Sub info -->
                    <h3 class="font-bold text-white text-sm group-hover:text-cyan-400 transition-colors line-clamp-2">
                        <?= htmlspecialchars($p['name']) ?>
                    </h3>
                    
                    <div class="text-[11px] text-slate-400 space-y-0.5 mt-2">
                        <?php if (!empty($p['brand_or_model'])): ?>
                            <div>برانڈ / ماڈل: <span class="text-slate-300"><?= htmlspecialchars($p['brand_or_model']) ?></span></div>
                        <?php endif; ?>
                        <?php if (!empty($p['ram_storage']) || !empty($p['color'])): ?>
                            <div>تفصیل: <span class="text-slate-300"><?= htmlspecialchars($p['ram_storage'] ?? '') ?> <?= htmlspecialchars($p['color'] ?? '') ?></span></div>
                        <?php endif; ?>
                        <?php if (!empty($p['sku'])): ?>
                            <div class="font-mono text-[10px] text-slate-500">SKU: <?= htmlspecialchars($p['sku']) ?></div>
                        <?php endif; ?>
                    </div>

                    <!-- Individual IMEI unit badge if mobile -->
                    <?php if ($p['category'] === 'MOBILES' && !empty($units)): ?>
                        <div class="mt-3 pt-2 border-t border-slate-800/80">
                            <button type="button" onclick="viewUnitsModal(<?= $productJson ?>)" class="w-full py-1.5 px-2 bg-slate-950 hover:bg-slate-800 rounded-xl text-[11px] text-cyan-400 font-bold flex items-center justify-between border border-slate-800 transition-colors">
                                <span class="flex items-center gap-1">
                                    <i data-lucide="smartphone" class="w-3.5 h-3.5"></i>
                                    <span>IMEI یونٹس (<?= count($units) ?>)</span>
                                </span>
                                <i data-lucide="chevron-left" class="w-3 h-3"></i>
                            </button>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Price & Bottom Controls -->
                <div class="mt-4 pt-3 border-t border-slate-800">
                    <div class="flex items-center justify-between mb-3">
                        <div>
                            <span class="text-[10px] text-slate-500 block">قیمت فروخت</span>
                            <span class="text-base font-black text-emerald-400 font-mono">Rs. <?= number_format($p['sale_price']) ?></span>
                        </div>
                        <div class="text-left">
                            <span class="text-[10px] text-slate-500 block">خرید: Rs. <?= number_format($p['purchase_price']) ?></span>
                            <span class="text-[11px] font-bold text-teal-400 font-mono">+Rs. <?= number_format($profitPerUnit) ?></span>
                        </div>
                    </div>

                    <!-- Quick Buttons -->
                    <div class="flex items-center justify-between gap-1.5 pt-2 border-t border-slate-800/60">
                        <!-- Stock + / - -->
                        <div class="flex items-center gap-1">
                            <form method="POST" class="inline">
                                <input type="hidden" name="action" value="quick_stock">
                                <input type="hidden" name="id" value="<?= $p['id'] ?>">
                                <input type="hidden" name="delta" value="1">
                                <button type="submit" class="w-7 h-7 bg-slate-800 hover:bg-slate-700 text-emerald-400 rounded-lg border border-slate-700 font-bold flex items-center justify-center text-xs" title="1 دانہ بڑھائیں">+</button>
                            </form>
                            <form method="POST" class="inline">
                                <input type="hidden" name="action" value="quick_stock">
                                <input type="hidden" name="id" value="<?= $p['id'] ?>">
                                <input type="hidden" name="delta" value="-1">
                                <button type="submit" class="w-7 h-7 bg-slate-800 hover:bg-slate-700 text-rose-400 rounded-lg border border-slate-700 font-bold flex items-center justify-center text-xs" title="1 دانہ گھٹائیں">-</button>
                            </form>
                        </div>

                        <!-- Edit & Delete & Bulk -->
                        <div class="flex items-center gap-1">
                            <button type="button" onclick="openBulkForProduct(<?= $productJson ?>)" class="p-1.5 bg-emerald-500/10 hover:bg-emerald-500/20 text-emerald-400 rounded-lg border border-emerald-500/30" title="بلک اسٹاک و ملٹی IMEI شامل کریں">
                                <i data-lucide="package-plus" class="w-3.5 h-3.5"></i>
                            </button>
                            <button type="button" onclick="editProductModal(<?= $productJson ?>)" class="p-1.5 bg-slate-800 hover:bg-slate-700 text-cyan-400 rounded-lg border border-slate-700" title="ایڈٹ کریں">
                                <i data-lucide="edit-3" class="w-3.5 h-3.5"></i>
                            </button>
                            <button type="button" onclick="confirmDeleteProduct('<?= $p['id'] ?>', '<?= htmlspecialchars($p['name'], ENT_QUOTES) ?>')" class="p-1.5 bg-slate-800 hover:bg-rose-950 text-rose-400 rounded-lg border border-slate-700" title="ڈیلیٹ کریں">
                                <i data-lucide="trash-2" class="w-3.5 h-3.5"></i>
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
<?php else: ?>
    <!-- LIST VIEW (Default Dense & High Efficiency Table) -->
    <div class="bg-slate-900 border border-slate-800 rounded-3xl overflow-hidden shadow-xl">
        <div class="overflow-x-auto">
            <table class="w-full text-right text-xs">
                <thead class="bg-slate-950 text-slate-400 border-b border-slate-800 font-bold uppercase text-[11px]">
                    <tr>
                        <th class="p-3.5 pr-4 text-center">#</th>
                        <th class="p-3.5">پروڈکٹ کا نام و تفصیل</th>
                        <th class="p-3.5">کیٹیگری</th>
                        <th class="p-3.5 font-mono">بارکوڈ / SKU / IMEI</th>
                        <th class="p-3.5 text-left font-mono">قیمت خرید</th>
                        <th class="p-3.5 text-left font-mono">قیمت فروخت</th>
                        <th class="p-3.5 text-left font-mono">منافع فی دانہ</th>
                        <th class="p-3.5 text-center">موجودہ اسٹاک</th>
                        <th class="p-3.5 text-left font-mono">کل اسٹاک مالیت</th>
                        <th class="p-3.5 text-center no-print min-w-[140px]">ایکشنز</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-800/60">
                    <?php foreach ($filteredProducts as $idx => $p): 
                        $units = json_decode($p['units_json'] ?? '[]', true);
                        if (!is_array($units)) $units = [];
                        $stk = intval($p['stock']);
                        $cost = floatval($p['purchase_price']);
                        $sale = floatval($p['sale_price']);
                        $profitUnit = $sale - $cost;
                        $totalVal = $stk * $cost;
                        $productJson = htmlspecialchars(json_encode($p), ENT_QUOTES, 'UTF-8');
                    ?>
                        <tr class="hover:bg-slate-800/40 transition-colors group">
                            <!-- Index -->
                            <td class="p-3.5 pr-4 text-center font-mono font-bold text-slate-500"><?= $idx + 1 ?></td>

                            <!-- Product Name -->
                            <td class="p-3.5">
                                <div class="font-bold text-white group-hover:text-cyan-400 transition-colors">
                                    <?= htmlspecialchars($p['name']) ?>
                                </div>
                                <div class="text-[10px] text-slate-400 mt-0.5 space-x-1">
                                    <?php if (!empty($p['brand_or_model'])): ?>
                                        <span>برانڈ: <?= htmlspecialchars($p['brand_or_model']) ?></span>
                                    <?php endif; ?>
                                    <?php if (!empty($p['ram_storage'])): ?>
                                        <span>| <?= htmlspecialchars($p['ram_storage']) ?></span>
                                    <?php endif; ?>
                                    <?php if (!empty($p['color'])): ?>
                                        <span>| رنگ: <?= htmlspecialchars($p['color']) ?></span>
                                    <?php endif; ?>
                                    <?php if ($p['category'] === 'MOBILES' && !empty($units)): ?>
                                        <button type="button" onclick="viewUnitsModal(<?= $productJson ?>)" class="text-cyan-400 hover:underline font-bold inline-flex items-center gap-1 mr-1">
                                            [<?= count($units) ?> IMEI یونٹس دیکھیں]
                                        </button>
                                    <?php endif; ?>
                                </div>
                            </td>

                            <!-- Category -->
                            <td class="p-3.5">
                                <span class="px-2.5 py-0.5 rounded-full text-[10px] font-bold bg-slate-800 text-slate-300 border border-slate-700 inline-flex items-center gap-1">
                                    <i data-lucide="<?= $categories[$p['category']]['icon'] ?? 'package' ?>" class="w-3 h-3 text-cyan-400"></i>
                                    <span><?= $categories[$p['category']]['name'] ?? $p['category'] ?></span>
                                </span>
                            </td>

                            <!-- Barcode / SKU / IMEI -->
                            <td class="p-3.5 font-mono text-cyan-400">
                                <div><?= htmlspecialchars($p['sku'] ?: '—') ?></div>
                                <?php if (!empty($p['imei_or_serial'])): ?>
                                    <div class="text-[9px] text-slate-500">IMEI: <?= htmlspecialchars($p['imei_or_serial']) ?></div>
                                <?php endif; ?>
                            </td>

                            <!-- Purchase Cost -->
                            <td class="p-3.5 text-left font-mono text-slate-400">
                                Rs. <?= number_format($cost) ?>
                            </td>

                            <!-- Sale Price -->
                            <td class="p-3.5 text-left font-mono font-bold text-white">
                                Rs. <?= number_format($sale) ?>
                            </td>

                            <!-- Unit Profit -->
                            <td class="p-3.5 text-left font-mono font-bold text-teal-400">
                                + Rs. <?= number_format($profitUnit) ?>
                            </td>

                            <!-- Current Stock -->
                            <td class="p-3.5 text-center">
                                <span class="px-2.5 py-1 rounded-full font-black font-mono text-xs <?= $stk === 0 ? 'bg-rose-500/20 text-rose-300 border border-rose-500/40' : ($stk <= 3 ? 'bg-amber-500/20 text-amber-300 border border-amber-500/40' : 'bg-emerald-500/10 text-emerald-300 border border-emerald-500/20') ?>">
                                    <?= $stk ?> دانہ
                                </span>
                            </td>

                            <!-- Stock Value -->
                            <td class="p-3.5 text-left font-mono text-slate-300">
                                Rs. <?= number_format($totalVal) ?>
                            </td>

                            <!-- Actions -->
                            <td class="p-3.5 text-center no-print">
                                <div class="flex items-center justify-center gap-1">
                                    <!-- Stock Quick Adjust -->
                                    <form method="POST" class="inline">
                                        <input type="hidden" name="action" value="quick_stock">
                                        <input type="hidden" name="id" value="<?= $p['id'] ?>">
                                        <input type="hidden" name="delta" value="1">
                                        <button type="submit" class="w-6 h-6 bg-slate-800 hover:bg-slate-700 text-emerald-400 rounded-md border border-slate-700 font-bold flex items-center justify-center text-xs" title="1 دانہ بڑھائیں">+</button>
                                    </form>
                                    <form method="POST" class="inline">
                                        <input type="hidden" name="action" value="quick_stock">
                                        <input type="hidden" name="id" value="<?= $p['id'] ?>">
                                        <input type="hidden" name="delta" value="-1">
                                        <button type="submit" class="w-6 h-6 bg-slate-800 hover:bg-slate-700 text-rose-400 rounded-md border border-slate-700 font-bold flex items-center justify-center text-xs" title="1 دانہ گھٹائیں">-</button>
                                    </form>

                                    <!-- Bulk Multi-IMEI -->
                                    <button type="button" onclick="openBulkForProduct(<?= $productJson ?>)" class="p-1 bg-emerald-500/10 hover:bg-emerald-500/20 text-emerald-400 rounded-md border border-emerald-500/30" title="بلک اسٹاک و ملٹی IMEI شامل کریں">
                                        <i data-lucide="package-plus" class="w-3.5 h-3.5"></i>
                                    </button>

                                    <!-- Edit -->
                                    <button type="button" onclick="editProductModal(<?= $productJson ?>)" class="p-1 bg-slate-800 hover:bg-slate-700 text-cyan-400 rounded-md border border-slate-700" title="ایڈٹ کریں">
                                        <i data-lucide="edit-3" class="w-3.5 h-3.5"></i>
                                    </button>

                                    <!-- Delete -->
                                    <button type="button" onclick="confirmDeleteProduct('<?= $p['id'] ?>', '<?= htmlspecialchars($p['name'], ENT_QUOTES) ?>')" class="p-1 bg-slate-800 hover:bg-rose-950 text-rose-400 rounded-md border border-slate-700" title="ڈیلیٹ کریں">
                                        <i data-lucide="trash-2" class="w-3.5 h-3.5"></i>
                                    </button>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
<?php endif; ?>

<!-- ==========================================================
     Modal 1: Add / Edit Product Modal (Multi-field)
     ========================================================== -->
<div id="productModal" class="fixed inset-0 z-50 bg-black/80 backdrop-blur-sm hidden items-center justify-center p-4 no-print">
    <div class="bg-slate-900 border border-slate-800 w-full max-w-2xl rounded-3xl overflow-hidden shadow-2xl flex flex-col max-h-[92vh]">
        
        <!-- Modal Top Bar -->
        <div class="p-4 border-b border-slate-800 flex items-center justify-between bg-slate-950">
            <div class="flex items-center gap-2.5">
                <div class="p-2 rounded-xl bg-cyan-500/10 text-cyan-400 border border-cyan-500/20">
                    <i data-lucide="box" class="w-5 h-5"></i>
                </div>
                <div>
                    <h3 class="font-black text-white text-base" id="modalProductTitle">نئی پروڈکٹ کا اندراج</h3>
                    <p class="text-xs text-slate-400">تمام تفصیلات، خریداری و فروخت قیمتیں اور اسٹاک درج کریں</p>
                </div>
            </div>
            <button onclick="closeProductModal()" class="p-2 text-slate-400 hover:text-white rounded-xl hover:bg-slate-800 transition-colors">
                <i data-lucide="x" class="w-5 h-5"></i>
            </button>
        </div>

        <!-- Modal Form -->
        <form method="POST" id="productForm" class="p-6 overflow-y-auto space-y-4">
            <input type="hidden" name="action" value="save_product">
            <input type="hidden" name="id" id="formProductId">
            <input type="hidden" name="units_json" id="formUnitsJson" value="[]">

            <!-- Name & Category -->
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                <div class="sm:col-span-2">
                    <label class="text-[11px] font-bold text-slate-400 block mb-1">پروڈکٹ کا نام *</label>
                    <input type="text" name="name" id="formName" required placeholder="مثلاً: Samsung Galaxy A15 (6GB / 128GB) یا 33W Fast Charger" class="w-full bg-slate-950 border border-slate-700 text-white px-3.5 py-2.5 rounded-xl text-xs focus:border-cyan-500 focus:outline-none">
                </div>

                <div>
                    <label class="text-[11px] font-bold text-slate-400 block mb-1">کیٹیگری *</label>
                    <select name="category" id="formCategory" onchange="toggleCategorySpecificFields()" class="w-full bg-slate-950 border border-slate-700 text-white px-3.5 py-2.5 rounded-xl text-xs focus:border-cyan-500 focus:outline-none">
                        <?php foreach($categories as $k => $v): ?>
                            <option value="<?= $k ?>"><?= $v['name'] ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div>
                    <label class="text-[11px] font-bold text-slate-400 block mb-1">برانڈ / ماڈل</label>
                    <input type="text" name="brand_or_model" id="formBrand" placeholder="مثلاً: Samsung, Xiaomi, Infinix, Faster..." class="w-full bg-slate-950 border border-slate-700 text-white px-3.5 py-2.5 rounded-xl text-xs focus:border-cyan-500 focus:outline-none">
                </div>
            </div>

            <!-- Price & Stock Math -->
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-3 bg-slate-950/60 p-3.5 rounded-2xl border border-slate-800">
                <div>
                    <label class="text-[11px] font-bold text-slate-400 block mb-1">قیمت خرید (Rs) *</label>
                    <input type="number" step="any" name="purchase_price" id="formCost" required oninput="calcProfitPreview()" placeholder="0" class="w-full bg-slate-900 border border-slate-700 text-white px-3 py-2 rounded-xl text-xs font-mono focus:border-cyan-500 focus:outline-none">
                </div>
                <div>
                    <label class="text-[11px] font-bold text-slate-400 block mb-1">قیمت فروخت (Rs) *</label>
                    <input type="number" step="any" name="sale_price" id="formSale" required oninput="calcProfitPreview()" placeholder="0" class="w-full bg-slate-900 border border-slate-700 text-emerald-400 font-bold px-3 py-2 rounded-xl text-xs font-mono focus:border-cyan-500 focus:outline-none">
                </div>
                <div>
                    <label class="text-[11px] font-bold text-slate-400 block mb-1">اسٹاک تعداد (Stock Qty)</label>
                    <input type="number" name="stock" id="formStock" value="1" min="0" class="w-full bg-slate-900 border border-slate-700 text-cyan-400 font-bold px-3 py-2 rounded-xl text-xs font-mono focus:border-cyan-500 focus:outline-none">
                </div>
                <div class="sm:col-span-3 text-[11px] text-slate-400 flex items-center justify-between pt-1">
                    <span>منافع فی دانہ: <strong id="previewProfitText" class="text-teal-400 font-mono">Rs. 0</strong></span>
                    <span>مارجن: <strong id="previewMarginText" class="text-cyan-400 font-mono">0%</strong></span>
                </div>
            </div>

            <!-- SKU Barcode & IMEI (Standard) -->
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                <div>
                    <div class="flex justify-between items-center mb-1">
                        <label class="text-[11px] font-bold text-slate-400">بارکوڈ / SKU</label>
                        <button type="button" onclick="generateAutoSku()" class="text-[10px] text-cyan-400 hover:underline">آٹو جنریٹ</button>
                    </div>
                    <input type="text" name="sku" id="formSku" placeholder="BC-1002" class="w-full bg-slate-950 border border-slate-700 text-white px-3.5 py-2 rounded-xl text-xs font-mono focus:border-cyan-500 focus:outline-none">
                </div>
                <div>
                    <label class="text-[11px] font-bold text-slate-400 block mb-1">IMEI نمبر 1 (بنیادی)</label>
                    <input type="text" name="imei_or_serial" id="formImei" placeholder="356789..." class="w-full bg-slate-950 border border-slate-700 text-white px-3.5 py-2 rounded-xl text-xs font-mono focus:border-cyan-500 focus:outline-none">
                </div>
            </div>

            <!-- Mobile Specific Section (Only visible for MOBILES) -->
            <div id="mobileSpecificFields" class="space-y-3 bg-slate-950/80 p-4 rounded-2xl border border-cyan-900/40">
                <div class="flex items-center gap-1.5 text-cyan-400 font-bold text-xs pb-1 border-b border-slate-800">
                    <i data-lucide="smartphone" class="w-4 h-4"></i>
                    <span>موبائل فون کی مخصوص تفصیلات (Mobile Specs)</span>
                </div>

                <div class="grid grid-cols-2 sm:grid-cols-4 gap-2">
                    <div>
                        <label class="text-[10px] text-slate-400 block mb-1">ریم / میموری</label>
                        <select name="ram_storage" id="formRamStorage" class="w-full bg-slate-900 border border-slate-700 text-white px-2 py-1.5 rounded-lg text-xs">
                            <option value="4GB / 64GB">4GB / 64GB</option>
                            <option value="6GB / 128GB">6GB / 128GB</option>
                            <option value="8GB / 128GB">8GB / 128GB</option>
                            <option value="8GB / 256GB">8GB / 256GB</option>
                            <option value="12GB / 256GB">12GB / 256GB</option>
                            <option value="Keypad (32MB)">کی پیڈ فون</option>
                        </select>
                    </div>

                    <div>
                        <label class="text-[10px] text-slate-400 block mb-1">رنگ (Color)</label>
                        <input type="text" name="color" id="formColor" placeholder="Black, Blue..." class="w-full bg-slate-900 border border-slate-700 text-white px-2 py-1.5 rounded-lg text-xs">
                    </div>

                    <div>
                        <label class="text-[10px] text-slate-400 block mb-1">PTA اسٹیٹس</label>
                        <select name="pta_status" id="formPtaStatus" class="w-full bg-slate-900 border border-slate-700 text-white px-2 py-1.5 rounded-lg text-xs">
                            <option value="PTA_APPROVED">PTA Approved</option>
                            <option value="NON_PTA">Non-PTA</option>
                            <option value="JV">JV</option>
                            <option value="FACTORY_UNLOCK">Factory Unlock</option>
                        </select>
                    </div>

                    <div>
                        <label class="text-[10px] text-slate-400 block mb-1">حالت (Condition)</label>
                        <select name="condition_status" id="formConditionStatus" class="w-full bg-slate-900 border border-slate-700 text-white px-2 py-1.5 rounded-lg text-xs">
                            <option value="NEW">Box Pack (نیا)</option>
                            <option value="USED">استعمال شدہ (Used)</option>
                        </select>
                    </div>
                </div>

                <!-- Multi-IMEI Bulk Section -->
                <div class="pt-2">
                    <div class="flex justify-between items-center mb-1">
                        <label class="text-[11px] font-bold text-slate-400">متعدد IMEI نمبرز کا اندراج (Bulk IMEI List)</label>
                        <span class="text-[10px] text-slate-500">ہر لائن پر ایک IMEI لکھیں</span>
                    </div>
                    <textarea id="bulkImeiTextarea" rows="2" placeholder="862800041234567&#10;862800041234568&#10;862800041234569" class="w-full bg-slate-900 border border-slate-700 text-white p-2.5 rounded-xl text-xs font-mono focus:border-cyan-500 focus:outline-none"></textarea>
                    <button type="button" onclick="applyBulkImeis()" class="mt-1 px-3 py-1 bg-slate-800 hover:bg-slate-700 text-cyan-400 text-[10px] font-bold rounded-lg border border-slate-700">
                        + درج کردہ IMEIs شامل کریں
                    </button>
                </div>
            </div>

            <!-- Modal Actions -->
            <div class="pt-4 border-t border-slate-800 flex items-center justify-between">
                <button type="button" onclick="closeProductModal()" class="px-4 py-2.5 bg-slate-800 hover:bg-slate-700 text-slate-300 font-bold text-xs rounded-xl">
                    منسوخ کریں
                </button>
                <button type="submit" class="px-6 py-2.5 bg-emerald-600 hover:bg-emerald-500 text-white font-black text-xs rounded-xl shadow-lg flex items-center gap-1.5">
                    <i data-lucide="check" class="w-4 h-4"></i>
                    <span>اسٹاک محفوظ کریں</span>
                </button>
            </div>
        </form>

    </div>
</div>

<!-- ==========================================================
     Modal 2: Individual IMEI Units Details View
     ========================================================== -->
<div id="unitsDetailsModal" class="fixed inset-0 z-50 bg-black/80 backdrop-blur-sm hidden items-center justify-center p-4 no-print">
    <div class="bg-slate-900 border border-slate-800 w-full max-w-xl rounded-3xl overflow-hidden shadow-2xl flex flex-col max-h-[85vh]">
        
        <div class="p-4 border-b border-slate-800 flex items-center justify-between bg-slate-950">
            <div class="flex items-center gap-2.5">
                <div class="p-2 rounded-xl bg-cyan-500/10 text-cyan-400">
                    <i data-lucide="smartphone" class="w-5 h-5"></i>
                </div>
                <div>
                    <h3 class="font-black text-white text-base" id="unitsModalModelName">موبائل IMEI یونٹس</h3>
                    <p class="text-xs text-slate-400">دستیاب اور فروخت شدہ تمام انفرادی پیسز کی تفصیل</p>
                </div>
            </div>
            <button onclick="closeUnitsModal()" class="p-2 text-slate-400 hover:text-white rounded-xl hover:bg-slate-800">
                <i data-lucide="x" class="w-5 h-5"></i>
            </button>
        </div>

        <div class="p-5 overflow-y-auto space-y-3" id="unitsListContainer">
            <!-- Dynamic Units Table inserted here -->
        </div>

        <div class="p-4 border-t border-slate-800 bg-slate-950 flex justify-end">
            <button onclick="closeUnitsModal()" class="px-4 py-2 bg-slate-800 hover:bg-slate-700 text-slate-300 font-bold text-xs rounded-xl">
                بند کریں
            </button>
        </div>

    </div>
</div>

<!-- ==========================================================
     Modal 3: Bulk Stock Adjustment & Multi-IMEI Entry
     ========================================================== -->
<div id="bulkStockModal" class="fixed inset-0 z-50 bg-black/80 backdrop-blur-sm hidden items-center justify-center p-3 sm:p-4 no-print overflow-y-auto">
    <div class="bg-slate-900 border border-slate-800 w-full max-w-4xl rounded-3xl overflow-hidden shadow-2xl flex flex-col max-h-[92vh] my-auto">
        
        <!-- Modal Top Bar -->
        <div class="bg-gradient-to-r from-emerald-600 via-teal-600 to-indigo-700 p-4 sm:p-5 text-white flex items-center justify-between shadow-md">
            <div class="flex items-center gap-3">
                <div class="w-11 h-11 rounded-2xl bg-white/15 flex items-center justify-center font-bold text-white shadow-inner">
                    <i data-lucide="package-plus" class="w-6 h-6"></i>
                </div>
                <div>
                    <div class="flex items-center gap-2">
                        <h3 class="text-base sm:text-lg font-black">بلک اسٹاک ایڈجسٹمنٹ و ملٹی IMEI اینٹری</h3>
                        <span class="bg-white/20 text-white text-[10px] font-extrabold px-2 py-0.5 rounded-full">
                            10+ Phones at Once
                        </span>
                    </div>
                    <p class="text-emerald-100 text-xs mt-0.5">
                        ایک ساتھ 10 یا زیادہ فونز درج کریں — تمام معلومات مشترکہ، ہر فون کا الگ منفرد IMEI
                    </p>
                </div>
            </div>
            <button type="button" onclick="closeBulkStockModal()" class="w-8 h-8 rounded-full bg-white/10 hover:bg-white/20 flex items-center justify-center text-white transition-colors">
                <i data-lucide="x" class="w-5 h-5"></i>
            </button>
        </div>

        <!-- Modal Form -->
        <form method="POST" id="bulkStockForm" onsubmit="return handleBulkFormSubmit(event)" class="p-4 sm:p-6 overflow-y-auto space-y-4">
            <input type="hidden" name="action" value="save_bulk_stock">
            <input type="hidden" name="entry_mode" id="bulkEntryModeInput" value="existing">
            <input type="hidden" name="product_id" id="bulkProductIdInput" value="">
            <input type="hidden" name="bulk_units_json" id="bulkUnitsJsonInput" value="[]">

            <!-- Mode Switcher -->
            <div class="flex flex-col sm:flex-row items-center gap-2 p-1.5 rounded-2xl bg-slate-950 border border-slate-800">
                <button type="button" id="bulkModeExistingBtn" onclick="setBulkEntryMode('existing')" class="flex-1 py-2 px-3 rounded-xl font-bold text-xs flex items-center justify-center gap-2 transition-all bg-emerald-600 text-white shadow-md">
                    <i data-lucide="layers" class="w-4 h-4"></i>
                    <span>موجودہ ماڈل میں نیا اسٹاک شامل کریں (Add to Existing)</span>
                </button>
                <button type="button" id="bulkModeNewBtn" onclick="setBulkEntryMode('new')" class="flex-1 py-2 px-3 rounded-xl font-bold text-xs flex items-center justify-center gap-2 transition-all text-slate-400 hover:text-white">
                    <i data-lucide="plus" class="w-4 h-4"></i>
                    <span>نئے فون ماڈل کا بلک اسٹاک بنائیں (Add Brand New)</span>
                </button>
            </div>

            <!-- Existing Product Selector -->
            <div id="bulkExistingProductSection" class="p-4 rounded-2xl bg-emerald-950/20 border border-emerald-900/60 space-y-3">
                <div class="flex items-center justify-between">
                    <label class="text-xs font-black text-emerald-300 flex items-center gap-2">
                        <i data-lucide="smartphone" class="w-4 h-4 text-emerald-400"></i>
                        <span>وہ پروڈکٹ منتخب کریں جس میں نیا اسٹاک شامل کرنا ہے:</span>
                    </label>
                    <span class="text-xs text-emerald-400 font-bold" id="bulkAvailableCountText">
                        <?= count($allProducts) ?> اشیاء دستیاب
                    </span>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                    <div class="relative">
                        <input type="text" id="bulkProductSearchInput" oninput="filterBulkProductOptions()" placeholder="پروڈکٹ کا نام یا برانڈ لکھ کر تلاش کریں..." class="w-full bg-slate-950 border border-slate-700 text-white pl-3 pr-8 py-2 rounded-xl text-xs focus:border-emerald-500 focus:outline-none">
                        <i data-lucide="search" class="w-4 h-4 absolute right-2.5 top-1/2 -translate-y-1/2 text-slate-500 pointer-events-none"></i>
                    </div>

                    <select id="bulkProductSelect" onchange="handleBulkProductSelect(this.value)" class="w-full bg-slate-950 border border-slate-700 text-white px-3 py-2 rounded-xl text-xs font-bold focus:border-emerald-500 focus:outline-none">
                        <option value="">-- پروڈکٹ منتخب کریں --</option>
                        <?php foreach($allProducts as $ap): ?>
                            <option value="<?= $ap['id'] ?>" data-name="<?= htmlspecialchars($ap['name'], ENT_QUOTES) ?>" data-brand="<?= htmlspecialchars($ap['brand_or_model'] ?? '', ENT_QUOTES) ?>" data-cost="<?= $ap['purchase_price'] ?>" data-sale="<?= $ap['sale_price'] ?>" data-stock="<?= $ap['stock'] ?>" data-color="<?= htmlspecialchars($ap['color'] ?? '', ENT_QUOTES) ?>" data-ram="<?= htmlspecialchars($ap['ram_storage'] ?? '', ENT_QUOTES) ?>" data-pta="<?= htmlspecialchars($ap['pta_status'] ?? '', ENT_QUOTES) ?>" data-cond="<?= htmlspecialchars($ap['condition_status'] ?? '', ENT_QUOTES) ?>">
                                <?= htmlspecialchars($ap['name']) ?> (موجودہ اسٹاک: <?= $ap['stock'] ?>) - Rs. <?= number_format($ap['sale_price']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div id="bulkStockForecastAlert" class="hidden text-xs font-medium text-emerald-300 items-center gap-2 pt-1 border-t border-emerald-900/40">
                    <i data-lucide="check-circle-2" class="w-4 h-4 text-emerald-400 shrink-0"></i>
                    <span id="bulkStockForecastMsg"></span>
                </div>
            </div>

            <!-- Shared Specifications Card (1. مشترکہ تفصیلات) -->
            <div class="p-4 rounded-2xl bg-slate-950/60 border border-slate-800 space-y-3">
                <div class="flex items-center justify-between border-b pb-2 border-slate-800">
                    <span class="text-xs font-black text-slate-300 flex items-center gap-2">
                        <i data-lucide="sparkles" class="w-4 h-4 text-amber-400"></i>
                        <span>1. مشترکہ تفصیلات (تمام فونز کا یکساں ڈیٹا)</span>
                    </span>
                    <span class="text-[11px] font-bold text-emerald-400">ایک بار درج کریں، سب فونز پر لاگو ہوگا</span>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3">
                    <!-- Brand & Model -->
                    <div class="sm:col-span-2">
                        <label class="block text-[11px] font-bold mb-1 text-slate-400">برانڈ و ماڈل کا نام *</label>
                        <input type="text" name="brand_or_model" id="bulkBrandInput" required placeholder="مثال: Nokia 106 (2024) یا Samsung Galaxy A15" class="w-full bg-slate-900 border border-slate-700 text-white px-3 py-2 rounded-xl text-xs font-semibold focus:border-cyan-500 focus:outline-none">
                    </div>

                    <!-- Purchase Price -->
                    <div>
                        <label class="block text-[11px] font-bold mb-1 text-slate-400">خرید قیمت فی فون (Rs) *</label>
                        <input type="number" step="any" name="purchase_price" id="bulkCostInput" required oninput="calcBulkDuplicateAndMath()" placeholder="4500" class="w-full bg-slate-900 border border-slate-700 text-white px-3 py-2 rounded-xl text-xs font-bold font-mono focus:border-cyan-500 focus:outline-none">
                    </div>

                    <!-- Sale Price -->
                    <div>
                        <label class="block text-[11px] font-bold mb-1 text-slate-400">فروخت قیمت فی فون (Rs) *</label>
                        <input type="number" step="any" name="sale_price" id="bulkSaleInput" required oninput="calcBulkDuplicateAndMath()" placeholder="5200" class="w-full bg-slate-900 border border-slate-700 text-emerald-400 px-3 py-2 rounded-xl text-xs font-bold font-mono focus:border-cyan-500 focus:outline-none">
                    </div>

                    <!-- Default Color -->
                    <div>
                        <label class="block text-[11px] font-bold mb-1 text-slate-400">بنیادی رنگ (Default Color)</label>
                        <select name="default_color" id="bulkColorInput" onchange="applyDefaultColorToRows(this.value)" class="w-full bg-slate-900 border border-slate-700 text-white px-3 py-2 rounded-xl text-xs">
                            <option value="Black">Black (سیاہ)</option>
                            <option value="Blue">Blue (نیلا)</option>
                            <option value="Dark Blue">Dark Blue (گہرا نیلا)</option>
                            <option value="White">White (سفید)</option>
                            <option value="Gold">Gold (سنہری)</option>
                            <option value="Silver">Silver (سلور)</option>
                            <option value="Green">Green (سبز)</option>
                            <option value="Purple">Purple (جامنی)</option>
                            <option value="Red">Red (سرخ)</option>
                            <option value="Titanium">Titanium</option>
                            <option value="Gray">Gray (گرے)</option>
                        </select>
                    </div>

                    <!-- Condition -->
                    <div>
                        <label class="block text-[11px] font-bold mb-1 text-slate-400">فون کی حالت (Condition)</label>
                        <select name="condition_status" id="bulkConditionInput" class="w-full bg-slate-900 border border-slate-700 text-white px-3 py-2 rounded-xl text-xs">
                            <option value="NEW">Pin Pack (نیا ڈبہ بند)</option>
                            <option value="USED">Used (استعمال شدہ)</option>
                        </select>
                    </div>

                    <!-- PTA Status -->
                    <div>
                        <label class="block text-[11px] font-bold mb-1 text-slate-400">پی ٹی اے تصدیق (PTA Status)</label>
                        <select name="pta_status" id="bulkPtaInput" class="w-full bg-slate-900 border border-slate-700 text-white px-3 py-2 rounded-xl text-xs">
                            <option value="PTA_APPROVED">PTA Approved</option>
                            <option value="NON_PTA">Non-PTA</option>
                            <option value="JV">JV</option>
                            <option value="FACTORY_UNLOCK">Factory Unlock</option>
                        </select>
                    </div>

                    <!-- RAM / Storage -->
                    <div>
                        <label class="block text-[11px] font-bold mb-1 text-slate-400">ریم / میموری</label>
                        <input type="text" name="ram_storage" id="bulkRamInput" placeholder="Keypad یا 4GB / 64GB" class="w-full bg-slate-900 border border-slate-700 text-white px-3 py-2 rounded-xl text-xs font-semibold focus:border-cyan-500 focus:outline-none">
                    </div>
                </div>
            </div>

            <!-- Batch Quantity & IMEI Entry Card (2. فونز کی تعداد اور IMEIs) -->
            <div class="p-4 rounded-2xl bg-slate-950/60 border border-slate-800 space-y-3">
                <div class="flex flex-col sm:flex-row items-stretch sm:items-center justify-between gap-3 border-b pb-3 border-slate-800">
                    <div class="flex items-center gap-3">
                        <div class="w-9 h-9 rounded-xl bg-cyan-500/10 text-cyan-400 flex items-center justify-center font-bold">
                            <i data-lucide="barcode" class="w-5 h-5"></i>
                        </div>
                        <div>
                            <span class="text-xs font-black text-slate-300 block">2. فونز کی تعداد اور منفرد IMEI نمبرز</span>
                            <span class="text-[11px] text-slate-500" id="bulkImeiProgressText">0 / 10 IMEIs درج ہوچکے ہیں</span>
                        </div>
                    </div>

                    <!-- Stepper & Quick Pills -->
                    <div class="flex flex-wrap items-center gap-2">
                        <span class="text-xs font-bold text-slate-400">کل تعداد:</span>
                        <div class="flex items-center rounded-xl border border-slate-700 bg-slate-900 overflow-hidden shadow-sm">
                            <button type="button" onclick="stepBulkQty(-1)" class="px-3 py-1.5 hover:bg-slate-800 font-bold text-slate-300">-</button>
                            <input type="number" id="bulkQuantityInput" min="1" max="200" value="10" onchange="setBulkQuantity(this.value)" class="w-14 text-center font-black font-mono text-xs outline-none bg-transparent text-white">
                            <button type="button" onclick="stepBulkQty(1)" class="px-3 py-1.5 hover:bg-slate-800 font-bold text-slate-300">+</button>
                        </div>

                        <div class="flex items-center gap-1">
                            <?php foreach([5, 10, 15, 20, 50, 100] as $presetQ): ?>
                                <button type="button" onclick="setBulkQuantity(<?= $presetQ ?>)" class="bulk-preset-pill px-2 py-1 text-[11px] font-bold rounded-lg border border-slate-700 text-slate-400 hover:bg-slate-800 hover:text-white transition-all <?= $presetQ === 10 ? 'bg-cyan-600 text-white border-cyan-600' : '' ?>" data-qty="<?= $presetQ ?>">
                                    <?= $presetQ ?>
                                </button>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>

                <!-- Tabs: Row-by-Row Table vs Quick Scan/Paste -->
                <div class="flex items-center gap-2 pt-1">
                    <button type="button" id="bulkTabTableBtn" onclick="setBulkImeiMethod('table')" class="px-3 py-1.5 rounded-xl text-xs font-bold flex items-center gap-1.5 transition-all bg-cyan-600 text-white shadow-sm">
                        <i data-lucide="list-plus" class="w-3.5 h-3.5"></i>
                        <span>ہر فون کا الگ خانہ (Row Table)</span>
                    </button>
                    <button type="button" id="bulkTabPasteBtn" onclick="setBulkImeiMethod('paste')" class="px-3 py-1.5 rounded-xl text-xs font-bold flex items-center gap-1.5 transition-all bg-slate-900 text-slate-400 hover:text-white border border-slate-800">
                        <i data-lucide="copy" class="w-3.5 h-3.5"></i>
                        <span>ایک ساتھ تمام بارکوڈ اسکین / پیسٹ کریں</span>
                    </button>
                </div>

                <!-- Tab 1: Quick Scan / Bulk Paste Box -->
                <div id="bulkPasteTabContainer" class="hidden space-y-3 p-4 rounded-2xl bg-cyan-950/20 border border-cyan-900/60">
                    <div class="flex items-center justify-between">
                        <span class="text-xs font-bold text-cyan-300 flex items-center gap-1.5">
                            <i data-lucide="info" class="w-4 h-4 text-cyan-400"></i>
                            <span>بارکوڈ اسکینر سے تیزی سے اسکین کریں یا لسٹ یہاں پیسٹ کریں:</span>
                        </span>
                        <span class="text-[11px] text-cyan-400">ہر لائن پر ایک IMEI</span>
                    </div>

                    <textarea id="bulkPasteTextarea" rows="6" placeholder="862800041234567&#10;862800041234568&#10;862800041234569&#10;..." class="w-full bg-slate-950 border border-slate-700 text-white p-3 rounded-xl text-xs font-mono font-bold tracking-wider outline-none focus:border-cyan-500"></textarea>

                    <div class="flex items-center justify-between">
                        <span class="text-[11px] text-slate-400">تمام باکسز خودکار طریقے سے فِل ہو جائیں گے۔</span>
                        <button type="button" onclick="processBulkPasteText()" class="px-4 py-2 bg-cyan-600 hover:bg-cyan-500 text-white text-xs font-bold rounded-xl shadow-md transition-all flex items-center gap-1.5">
                            <i data-lucide="check" class="w-4 h-4"></i>
                            <span id="bulkApplyBtnText">تمام فونز پر لاگو کریں</span>
                        </button>
                    </div>
                </div>

                <!-- Tab 2: Row-by-Row Table -->
                <div id="bulkTableTabContainer" class="space-y-2">
                    <!-- Duplicate Alert -->
                    <div id="bulkDuplicateAlert" class="hidden p-3 rounded-xl bg-amber-500/10 border border-amber-500/30 text-amber-400 text-xs font-bold items-center gap-2">
                        <i data-lucide="alert-triangle" class="w-4 h-4 shrink-0 text-amber-500"></i>
                        <span id="bulkDuplicateAlertText"></span>
                    </div>

                    <div class="max-h-72 overflow-y-auto rounded-xl border border-slate-800 shadow-inner">
                        <table class="w-full text-right text-xs border-collapse">
                            <thead class="sticky top-0 bg-slate-950 text-slate-400 font-bold uppercase text-[10px] border-b border-slate-800">
                                <tr>
                                    <th class="py-2.5 px-3 w-14 text-center">#</th>
                                    <th class="py-2.5 px-3">IMEI 1 (بنیادی / اسکین) *</th>
                                    <th class="py-2.5 px-3">IMEI 2 (اختیاری - Dual SIM)</th>
                                    <th class="py-2.5 px-3 w-32">رنگ (Color)</th>
                                    <th class="py-2.5 px-3 w-14 text-center">اسٹیٹس</th>
                                </tr>
                            </thead>
                            <tbody id="bulkRowsTbody" class="divide-y divide-slate-800/60 bg-slate-900">
                                <!-- Generated Dynamically -->
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- Bottom Summary & Action Buttons -->
            <div class="p-4 rounded-2xl bg-slate-950/80 border border-slate-800 flex flex-col sm:flex-row items-center justify-between gap-4">
                <div class="text-xs space-y-1 text-center sm:text-right w-full sm:w-auto">
                    <div class="font-bold text-white">
                        کل شامل ہونے والے فونز: <strong id="bulkSummaryQtyText" class="text-emerald-400 text-sm font-black font-mono">10 Phones</strong>
                        <span id="bulkSummaryFilledText" class="text-slate-400 font-normal mr-1">(0 کے IMEI داخل ہوچکے ہیں)</span>
                    </div>
                    <div class="text-slate-400">
                        کل خریداری انویسٹمنٹ: <strong id="bulkSummaryCostText" class="font-mono text-slate-200">Rs. 0</strong>
                        <span class="mx-1.5 text-slate-600">|</span>
                        متوقع کل فروخت: <strong id="bulkSummarySaleText" class="font-mono text-cyan-400">Rs. 0</strong>
                        <span class="mx-1.5 text-slate-600">|</span>
                        متوقع منافع: <strong id="bulkSummaryProfitText" class="font-mono text-teal-400">Rs. 0</strong>
                    </div>
                </div>

                <div class="flex items-center gap-2.5 w-full sm:w-auto">
                    <button type="button" onclick="closeBulkStockModal()" class="flex-1 sm:flex-none px-4 py-2.5 rounded-xl bg-slate-800 hover:bg-slate-700 text-slate-300 font-bold text-xs transition-colors">
                        منسوخ کریں
                    </button>
                    <button type="submit" id="bulkSubmitBtn" class="flex-1 sm:flex-none px-6 py-2.5 rounded-xl bg-gradient-to-r from-emerald-600 to-teal-600 hover:from-emerald-500 hover:to-teal-500 text-white font-black text-xs shadow-lg shadow-emerald-900/30 transition-all flex items-center justify-center gap-2">
                        <i data-lucide="check" class="w-4 h-4"></i>
                        <span id="bulkSubmitBtnText">تصدیق کریں اور 10 فون اسٹاک میں شامل کریں</span>
                    </button>
                </div>
            </div>

        </form>
    </div>
</div>

<!-- Delete Confirmation Form -->
<form id="deleteProductForm" method="POST" class="hidden">
    <input type="hidden" name="action" value="delete_product">
    <input type="hidden" name="id" id="deleteProductIdInput">
</form>

<!-- ==========================================================
     JavaScript Logic for Modals & Multi-IMEI
     ========================================================== -->
<script>
let currentEditingProduct = null;
let currentUnits = [];

// Bulk Stock State
let bulkEntryMode = 'existing'; // 'existing' or 'new'
let bulkQuantity = 10;
let bulkImeiMethod = 'table'; // 'table' or 'paste'
let bulkUnitRows = [];
const allProductsList = <?= json_encode($allProducts, JSON_UNESCAPED_UNICODE) ?> || [];

function toggleCategorySpecificFields() {
    const cat = document.getElementById('formCategory').value;
    const mobileBox = document.getElementById('mobileSpecificFields');
    if (cat === 'MOBILES') {
        mobileBox.style.display = 'block';
    } else {
        mobileBox.style.display = 'none';
    }
}

function openProductModal() {
    currentEditingProduct = null;
    currentUnits = [];
    document.getElementById('modalProductTitle').textContent = 'نئی پروڈکٹ کا اندراج';
    document.getElementById('productForm').reset();
    document.getElementById('formProductId').value = '';
    document.getElementById('formUnitsJson').value = '[]';
    document.getElementById('bulkImeiTextarea').value = '';
    document.getElementById('previewProfitText').textContent = 'Rs. 0';
    document.getElementById('previewMarginText').textContent = '0%';
    generateAutoSku();
    toggleCategorySpecificFields();

    const m = document.getElementById('productModal');
    m.classList.remove('hidden');
    m.classList.add('flex');
}

function editProductModal(product) {
    currentEditingProduct = product;
    document.getElementById('modalProductTitle').textContent = 'پروڈکٹ ایڈٹ کریں: ' + product.name;
    document.getElementById('formProductId').value = product.id;
    document.getElementById('formName').value = product.name;
    document.getElementById('formCategory').value = product.category;
    document.getElementById('formBrand').value = product.brand_or_model || '';
    document.getElementById('formCost').value = product.purchase_price;
    document.getElementById('formSale').value = product.sale_price;
    document.getElementById('formStock').value = product.stock;
    document.getElementById('formSku').value = product.sku || '';
    document.getElementById('formImei').value = product.imei_or_serial || '';
    document.getElementById('formColor').value = product.color || '';
    document.getElementById('formRamStorage').value = product.ram_storage || '4GB / 64GB';
    document.getElementById('formPtaStatus').value = product.pta_status || 'PTA_APPROVED';
    document.getElementById('formConditionStatus').value = product.condition_status || 'NEW';

    try {
        currentUnits = JSON.parse(product.units_json || '[]');
        if (!Array.isArray(currentUnits)) currentUnits = [];
    } catch(e) {
        currentUnits = [];
    }
    document.getElementById('formUnitsJson').value = JSON.stringify(currentUnits);

    calcProfitPreview();
    toggleCategorySpecificFields();

    const m = document.getElementById('productModal');
    m.classList.remove('hidden');
    m.classList.add('flex');
}

function closeProductModal() {
    const m = document.getElementById('productModal');
    m.classList.remove('flex');
    m.classList.add('hidden');
}

function generateAutoSku() {
    const cat = document.getElementById('formCategory').value || 'GEN';
    const rand = Math.floor(1000 + Math.random() * 9000);
    document.getElementById('formSku').value = 'SKU-' + cat.substring(0, 3) + '-' + rand;
}

function calcProfitPreview() {
    const cost = parseFloat(document.getElementById('formCost').value) || 0;
    const sale = parseFloat(document.getElementById('formSale').value) || 0;
    const profit = sale - cost;
    const margin = sale > 0 ? ((profit / sale) * 100).toFixed(1) : 0;

    document.getElementById('previewProfitText').textContent = 'Rs. ' + profit.toLocaleString();
    document.getElementById('previewMarginText').textContent = margin + '%';
}

function applyBulkImeis() {
    const text = document.getElementById('bulkImeiTextarea').value.trim();
    if (!text) return;

    const lines = text.split(/[\r\n,]+/);
    const color = document.getElementById('formColor').value || 'Black';
    const ram = document.getElementById('formRamStorage').value || '4GB/64GB';
    const pta = document.getElementById('formPtaStatus').value || 'PTA_APPROVED';
    const cond = document.getElementById('formConditionStatus').value === 'NEW' ? 'Box Pack (New)' : 'Used';

    lines.forEach(line => {
        const imeiClean = line.trim();
        if (imeiClean.length >= 8) {
            currentUnits.push({
                id: 'u-' + Math.random().toString(36).substr(2, 9),
                imei1: imeiClean,
                imei2: '',
                color: color,
                storageRam: ram,
                condition: cond,
                ptaStatus: pta,
                status: 'AVAILABLE'
            });
        }
    });

    document.getElementById('formUnitsJson').value = JSON.stringify(currentUnits);
    document.getElementById('formStock').value = currentUnits.filter(u => u.status === 'AVAILABLE').length;
    document.getElementById('bulkImeiTextarea').value = '';
    alert(`${lines.length} IMEI یونٹس کامیابی سے لسٹ میں شامل ہو گئے اور اسٹاک اپڈیٹ ہو گیا!`);
}

function viewUnitsModal(product) {
    document.getElementById('unitsModalModelName').textContent = product.name;
    const container = document.getElementById('unitsListContainer');
    
    let units = [];
    try {
        units = JSON.parse(product.units_json || '[]');
        if (!Array.isArray(units)) units = [];
    } catch(e) { units = []; }

    if (units.length === 0) {
        container.innerHTML = `
            <div class="text-center py-8 text-slate-500">
                <p>اس پروڈکٹ کیلئے الگ الگ IMEI یونٹس درج نہیں کیے گئے ہیں۔</p>
            </div>
        `;
    } else {
        let rows = '';
        units.forEach((u, i) => {
            const isAvail = (u.status === 'AVAILABLE');
            rows += `
                <div class="p-3 bg-slate-950 rounded-2xl border border-slate-800 flex items-center justify-between">
                    <div>
                        <div class="font-mono text-cyan-400 font-bold text-xs flex items-center gap-1.5">
                            <span>#${i + 1}</span>
                            <span>IMEI 1: ${u.imei1 || '—'}</span>
                            ${u.imei2 ? '<span class="text-slate-500 font-normal">| IMEI 2: ' + u.imei2 + '</span>' : ''}
                        </div>
                        <div class="text-[10px] text-slate-400 mt-0.5">
                            <span>رنگ: ${u.color || '—'}</span> | 
                            <span>میموری: ${u.storageRam || '—'}</span> | 
                            <span>کنڈیشن: ${u.condition || '—'}</span>
                        </div>
                    </div>
                    <div>
                        <span class="px-2.5 py-1 rounded-full text-[10px] font-bold ${isAvail ? 'bg-emerald-500/10 text-emerald-300 border border-emerald-500/20' : 'bg-slate-800 text-slate-400 border border-slate-700'}">
                            ${isAvail ? 'دستیاب (Available)' : 'فروخت شدہ (SOLD)'}
                        </span>
                    </div>
                </div>
            `;
        });
        container.innerHTML = rows;
    }

    const m = document.getElementById('unitsDetailsModal');
    m.classList.remove('hidden');
    m.classList.add('flex');
}

function closeUnitsModal() {
    const m = document.getElementById('unitsDetailsModal');
    m.classList.remove('flex');
    m.classList.add('hidden');
}

function confirmDeleteProduct(id, name) {
    if (confirm(`کیا آپ واقعی پروڈکٹ "${name}" کو اسٹاک سے ڈیلیٹ کرنا چاہتے ہیں؟`)) {
        document.getElementById('deleteProductIdInput').value = id;
        document.getElementById('deleteProductForm').submit();
    }
}

// ==========================================================
// BULK STOCK ADJUSTMENT & MULTI-IMEI SYSTEM
// ==========================================================

function openBulkStockModal(presetProdId = null) {
    bulkQuantity = 10;
    document.getElementById('bulkQuantityInput').value = bulkQuantity;
    initBulkRows(bulkQuantity);
    
    if (presetProdId) {
        setBulkEntryMode('existing');
        document.getElementById('bulkProductSelect').value = presetProdId;
        handleBulkProductSelect(presetProdId);
    } else {
        setBulkEntryMode('existing');
        const firstProd = allProductsList.find(p => p.category === 'MOBILES') || allProductsList[0];
        if (firstProd) {
            document.getElementById('bulkProductSelect').value = firstProd.id;
            handleBulkProductSelect(firstProd.id);
        }
    }

    calcBulkDuplicateAndMath();
    setBulkImeiMethod('table');

    const m = document.getElementById('bulkStockModal');
    m.classList.remove('hidden');
    m.classList.add('flex');
    if (window.lucide) window.lucide.createIcons();
}

function openBulkForProduct(product) {
    openBulkStockModal(product.id);
}

function closeBulkStockModal() {
    const m = document.getElementById('bulkStockModal');
    m.classList.remove('flex');
    m.classList.add('hidden');
}

function setBulkEntryMode(mode) {
    bulkEntryMode = mode;
    document.getElementById('bulkEntryModeInput').value = mode;

    const existSection = document.getElementById('bulkExistingProductSection');
    const existBtn = document.getElementById('bulkModeExistingBtn');
    const newBtn = document.getElementById('bulkModeNewBtn');

    if (mode === 'existing') {
        existSection.style.display = 'block';
        existBtn.className = 'flex-1 py-2 px-3 rounded-xl font-bold text-xs flex items-center justify-center gap-2 transition-all bg-emerald-600 text-white shadow-md';
        newBtn.className = 'flex-1 py-2 px-3 rounded-xl font-bold text-xs flex items-center justify-center gap-2 transition-all text-slate-400 hover:text-white';
        
        const selId = document.getElementById('bulkProductSelect').value;
        if (selId) handleBulkProductSelect(selId);
    } else {
        existSection.style.display = 'none';
        existBtn.className = 'flex-1 py-2 px-3 rounded-xl font-bold text-xs flex items-center justify-center gap-2 transition-all text-slate-400 hover:text-white';
        newBtn.className = 'flex-1 py-2 px-3 rounded-xl font-bold text-xs flex items-center justify-center gap-2 transition-all bg-emerald-600 text-white shadow-md';
        
        document.getElementById('bulkBrandInput').value = '';
        document.getElementById('bulkCostInput').value = '';
        document.getElementById('bulkSaleInput').value = '';
        document.getElementById('bulkStockForecastAlert').classList.add('hidden');
    }
    calcBulkDuplicateAndMath();
}

function filterBulkProductOptions() {
    const term = document.getElementById('bulkProductSearchInput').value.toLowerCase().trim();
    const select = document.getElementById('bulkProductSelect');
    let visibleCount = 0;

    for (let i = 0; i < select.options.length; i++) {
        const opt = select.options[i];
        if (!opt.value) continue;
        const text = opt.text.toLowerCase();
        const brand = (opt.getAttribute('data-brand') || '').toLowerCase();
        if (text.includes(term) || brand.includes(term)) {
            opt.style.display = '';
            visibleCount++;
        } else {
            opt.style.display = 'none';
        }
    }
    document.getElementById('bulkAvailableCountText').textContent = visibleCount + ' اشیاء دستیاب';
}

function handleBulkProductSelect(prodId) {
    document.getElementById('bulkProductIdInput').value = prodId;
    if (!prodId) {
        document.getElementById('bulkStockForecastAlert').classList.add('hidden');
        return;
    }

    const prod = allProductsList.find(p => p.id === prodId);
    if (!prod) return;

    document.getElementById('bulkBrandInput').value = prod.brand_or_model || prod.name;
    document.getElementById('bulkCostInput').value = prod.purchase_price || '';
    document.getElementById('bulkSaleInput').value = prod.sale_price || '';
    if (prod.color) document.getElementById('bulkColorInput').value = prod.color;
    if (prod.ram_storage) document.getElementById('bulkRamInput').value = prod.ram_storage;
    if (prod.pta_status) document.getElementById('bulkPtaInput').value = prod.pta_status;
    if (prod.condition_status) document.getElementById('bulkConditionInput').value = prod.condition_status;

    // Forecast message
    const currentStk = parseInt(prod.stock) || 0;
    const addedStk = parseInt(bulkQuantity) || 0;
    const finalStk = currentStk + addedStk;

    const alertBox = document.getElementById('bulkStockForecastAlert');
    alertBox.classList.remove('hidden');
    alertBox.classList.add('flex');
    document.getElementById('bulkStockForecastMsg').textContent = `منتخب ماڈل کا موجودہ اسٹاک: ${currentStk} دانہ۔ نیا بلک اسٹاک شامل ہونے کے بعد کل اسٹاک ${finalStk} دانے ہو جائے گا۔`;

    calcBulkDuplicateAndMath();
}

function initBulkRows(qty) {
    const defaultCol = document.getElementById('bulkColorInput')?.value || 'Black';
    const oldRows = [...bulkUnitRows];
    bulkUnitRows = [];

    for (let i = 0; i < qty; i++) {
        const old = oldRows[i];
        bulkUnitRows.push({
            id: old ? old.id : ('u-' + Math.random().toString(36).substr(2, 9)),
            imei1: old ? old.imei1 : '',
            imei2: old ? old.imei2 : '',
            color: old ? old.color : defaultCol
        });
    }
    renderBulkRows();
}

function setBulkQuantity(newQty) {
    let q = parseInt(newQty) || 1;
    q = Math.max(1, Math.min(200, q));
    bulkQuantity = q;
    document.getElementById('bulkQuantityInput').value = q;

    // Update preset pills UI
    document.querySelectorAll('.bulk-preset-pill').forEach(btn => {
        const pillQ = parseInt(btn.getAttribute('data-qty'));
        if (pillQ === q) {
            btn.className = 'bulk-preset-pill px-2 py-1 text-[11px] font-bold rounded-lg border transition-all bg-cyan-600 text-white border-cyan-600';
        } else {
            btn.className = 'bulk-preset-pill px-2 py-1 text-[11px] font-bold rounded-lg border transition-all border-slate-700 text-slate-400 hover:bg-slate-800 hover:text-white';
        }
    });

    initBulkRows(q);

    // Update forecast message if existing product
    const selId = document.getElementById('bulkProductSelect').value;
    if (bulkEntryMode === 'existing' && selId) {
        handleBulkProductSelect(selId);
    }
}

function stepBulkQty(delta) {
    setBulkQuantity(bulkQuantity + delta);
}

function setBulkImeiMethod(method) {
    bulkImeiMethod = method;
    const tableContainer = document.getElementById('bulkTableTabContainer');
    const pasteContainer = document.getElementById('bulkPasteTabContainer');
    const tabTableBtn = document.getElementById('bulkTabTableBtn');
    const tabPasteBtn = document.getElementById('bulkTabPasteBtn');

    if (method === 'table') {
        tableContainer.classList.remove('hidden');
        pasteContainer.classList.add('hidden');
        tabTableBtn.className = 'px-3 py-1.5 rounded-xl text-xs font-bold flex items-center gap-1.5 transition-all bg-cyan-600 text-white shadow-sm';
        tabPasteBtn.className = 'px-3 py-1.5 rounded-xl text-xs font-bold flex items-center gap-1.5 transition-all bg-slate-900 text-slate-400 hover:text-white border border-slate-800';
    } else {
        tableContainer.classList.add('hidden');
        pasteContainer.classList.remove('hidden');
        tabTableBtn.className = 'px-3 py-1.5 rounded-xl text-xs font-bold flex items-center gap-1.5 transition-all bg-slate-900 text-slate-400 hover:text-white border border-slate-800';
        tabPasteBtn.className = 'px-3 py-1.5 rounded-xl text-xs font-bold flex items-center gap-1.5 transition-all bg-cyan-600 text-white shadow-sm';
    }
    if (window.lucide) window.lucide.createIcons();
}

function updateBulkUnitRow(idx, field, val) {
    if (bulkUnitRows[idx]) {
        bulkUnitRows[idx][field] = val;
    }
    calcBulkDuplicateAndMath();
}

function applyDefaultColorToRows(col) {
    bulkUnitRows.forEach(r => {
        if (!r.color || r.color === 'Black') {
            r.color = col;
        }
    });
    renderBulkRows();
}

function processBulkPasteText() {
    const text = document.getElementById('bulkPasteTextarea').value.trim();
    if (!text) return;

    const lines = text.split(/[\r\n,;\t]+/).map(s => s.trim()).filter(Boolean);
    if (lines.length === 0) return;

    const newQty = Math.max(lines.length, bulkQuantity);
    setBulkQuantity(newQty);

    const defaultCol = document.getElementById('bulkColorInput')?.value || 'Black';

    for (let i = 0; i < newQty; i++) {
        if (lines[i]) {
            bulkUnitRows[i].imei1 = lines[i];
        }
        if (!bulkUnitRows[i].color) {
            bulkUnitRows[i].color = defaultCol;
        }
    }

    renderBulkRows();
    setBulkImeiMethod('table');
    document.getElementById('bulkPasteTextarea').value = '';
    alert(`${lines.length} IMEI نمبرز کامیابی سے ٹیبل میں شامل ہو گئے!`);
}

function renderBulkRows() {
    const tbody = document.getElementById('bulkRowsTbody');
    if (!tbody) return;

    // Detect duplicates
    const counts = {};
    bulkUnitRows.forEach(r => {
        const val = r.imei1.trim();
        if (val) counts[val] = (counts[val] || 0) + 1;
    });

    let html = '';
    bulkUnitRows.forEach((row, i) => {
        const isDupe = row.imei1.trim() && counts[row.imei1.trim()] > 1;
        const isFilled = row.imei1.trim().length > 0;

        html += `
            <tr class="hover:bg-slate-800/40 transition-colors ${isDupe ? 'bg-amber-500/10' : ''}">
                <td class="py-2 px-3 text-center font-bold text-slate-500">
                    <span class="w-6 h-6 rounded-full bg-slate-950 flex items-center justify-center text-[11px] font-mono mx-auto border border-slate-800">${i + 1}</span>
                </td>
                <td class="py-2 px-3">
                    <input type="text" placeholder="فون #${i + 1} کا IMEI 1 اسکین یا درج کریں" value="${htmlspecialchars(row.imei1)}" oninput="updateBulkUnitRow(${i}, 'imei1', this.value)" class="w-full px-3 py-1.5 rounded-lg border ${isDupe ? 'border-amber-500 text-amber-300 bg-amber-950/20' : 'border-slate-700 bg-slate-950 text-white'} text-xs font-mono font-bold outline-none focus:border-cyan-500">
                </td>
                <td class="py-2 px-3">
                    <input type="text" placeholder="IMEI 2 (اختیاری)" value="${htmlspecialchars(row.imei2)}" oninput="updateBulkUnitRow(${i}, 'imei2', this.value)" class="w-full px-3 py-1.5 rounded-lg border border-slate-700 bg-slate-950 text-white text-xs font-mono outline-none focus:border-cyan-500">
                </td>
                <td class="py-2 px-3">
                    <select onchange="updateBulkUnitRow(${i}, 'color', this.value)" class="w-full px-2 py-1.5 rounded-lg border border-slate-700 bg-slate-950 text-white text-xs font-semibold outline-none">
                        <option value="Black" ${row.color === 'Black' ? 'selected' : ''}>Black</option>
                        <option value="Blue" ${row.color === 'Blue' ? 'selected' : ''}>Blue</option>
                        <option value="Dark Blue" ${row.color === 'Dark Blue' ? 'selected' : ''}>Dark Blue</option>
                        <option value="White" ${row.color === 'White' ? 'selected' : ''}>White</option>
                        <option value="Gold" ${row.color === 'Gold' ? 'selected' : ''}>Gold</option>
                        <option value="Silver" ${row.color === 'Silver' ? 'selected' : ''}>Silver</option>
                        <option value="Green" ${row.color === 'Green' ? 'selected' : ''}>Green</option>
                        <option value="Purple" ${row.color === 'Purple' ? 'selected' : ''}>Purple</option>
                        <option value="Red" ${row.color === 'Red' ? 'selected' : ''}>Red</option>
                        <option value="Titanium" ${row.color === 'Titanium' ? 'selected' : ''}>Titanium</option>
                        <option value="Gray" ${row.color === 'Gray' ? 'selected' : ''}>Gray</option>
                    </select>
                </td>
                <td class="py-2 px-3 text-center">
                    ${isFilled ? '<span class="inline-block p-1 bg-emerald-500/20 text-emerald-400 rounded-full"><i data-lucide="check" class="w-3.5 h-3.5"></i></span>' : '<span class="w-2 h-2 rounded-full bg-slate-700 inline-block"></span>'}
                </td>
            </tr>
        `;
    });

    tbody.innerHTML = html;
    if (window.lucide) window.lucide.createIcons();
    calcBulkDuplicateAndMath();
}

function calcBulkDuplicateAndMath() {
    const cost = parseFloat(document.getElementById('bulkCostInput')?.value) || 0;
    const sale = parseFloat(document.getElementById('bulkSaleInput')?.value) || 0;
    const qty = bulkQuantity;

    const filledCount = bulkUnitRows.filter(r => r.imei1.trim().length > 0).length;
    document.getElementById('bulkImeiProgressText').textContent = `${filledCount} / ${qty} IMEIs درج ہوچکے ہیں`;
    document.getElementById('bulkSummaryQtyText').textContent = `${qty} Phones`;
    document.getElementById('bulkSummaryFilledText').textContent = `(${filledCount} کے IMEI داخل ہوچکے ہیں)`;

    const totalCost = cost * qty;
    const totalSale = sale * qty;
    const totalProfit = totalSale - totalCost;

    document.getElementById('bulkSummaryCostText').textContent = 'Rs. ' + totalCost.toLocaleString();
    document.getElementById('bulkSummarySaleText').textContent = 'Rs. ' + totalSale.toLocaleString();
    document.getElementById('bulkSummaryProfitText').textContent = '+Rs. ' + totalProfit.toLocaleString();

    document.getElementById('bulkSubmitBtnText').textContent = `تصدیق کریں اور یہ ${qty} فون اسٹاک میں شامل کریں`;
    document.getElementById('bulkApplyBtnText').textContent = `تمام ${qty} فونز پر لاگو کریں`;

    // Duplicates check
    const counts = {};
    const dupes = [];
    bulkUnitRows.forEach(r => {
        const val = r.imei1.trim();
        if (val) {
            counts[val] = (counts[val] || 0) + 1;
            if (counts[val] === 2) dupes.push(val);
        }
    });

    const dupeAlert = document.getElementById('bulkDuplicateAlert');
    if (dupes.length > 0) {
        dupeAlert.classList.remove('hidden');
        dupeAlert.classList.add('flex');
        document.getElementById('bulkDuplicateAlertText').textContent = `وارننگ: درج ذیل IMEI نمبرز ایک سے زائد بار درج ہو رہے ہیں (${dupes.join(', ')})! براہ کرم درست کریں۔`;
    } else {
        dupeAlert.classList.add('hidden');
        dupeAlert.classList.remove('flex');
    }
}

function handleBulkFormSubmit(e) {
    const cost = parseFloat(document.getElementById('bulkCostInput').value) || 0;
    const sale = parseFloat(document.getElementById('bulkSaleInput').value) || 0;
    const brand = document.getElementById('bulkBrandInput').value.trim();
    const prodId = document.getElementById('bulkProductIdInput').value;

    if (cost <= 0 || sale <= 0) {
        alert('براہ کرم فی فون خرید قیمت اور فروخت قیمت درست درج کریں!');
        return false;
    }

    if (bulkEntryMode === 'existing' && !prodId) {
        alert('براہ کرم اسٹاک میں سے پروڈکٹ منتخب کریں!');
        return false;
    }

    if (bulkEntryMode === 'new' && !brand) {
        alert('براہ کرم نئے فون کا برانڈ یا ماڈل نام درج کریں!');
        return false;
    }

    // Convert rows to ProductUnitItem
    const ram = document.getElementById('bulkRamInput').value.trim() || '4GB / 64GB';
    const cond = document.getElementById('bulkConditionInput').value;
    const pta = document.getElementById('bulkPtaInput').value;
    const defCol = document.getElementById('bulkColorInput').value || 'Black';

    const preparedUnits = bulkUnitRows.map((r, idx) => ({
        id: r.id || ('u-' + Math.random().toString(36).substr(2, 9)),
        imei1: r.imei1.trim(),
        imei2: r.imei2.trim() || '',
        color: r.color || defCol,
        storageRam: ram,
        condition: cond === 'NEW' ? 'Box Pack (New)' : 'Used',
        ptaStatus: pta,
        status: 'AVAILABLE'
    }));

    document.getElementById('bulkUnitsJsonInput').value = JSON.stringify(preparedUnits);
    return true;
}

// Quick helper
function htmlspecialchars(str) {
    if (!str) return '';
    return String(str).replace(/&/g, '&amp;').replace(/"/g, '&quot;').replace(/'/g, '&#039;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
}
</script>

<?php require_once __DIR__ . '/../backend/footer.php'; ?>
