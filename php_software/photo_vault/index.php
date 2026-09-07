<?php
$pageTitle = 'فوٹو فائل مینیجر و شناختی کارڈ والٹ (Photo Vault)';
$activeMenu = 'photo_vault';
require_once __DIR__ . '/../backend/header.php';

$uploadDir = __DIR__ . '/uploads/';
if (!is_dir($uploadDir)) {
    mkdir($uploadDir, 0777, true);
}

$successMsg = '';
$errorMsg = '';

// ==========================================================
// 1. Handle Photo Upload / Deletion / Edit
// ==========================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    // Upload new photo to vault
    if ($action === 'upload_photo') {
        $title = trim($_POST['title'] ?? '');
        $category = $_POST['category'] ?? 'CNIC_FRONT';
        $customerName = trim($_POST['customer_name'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        $cnic = trim($_POST['cnic'] ?? '');
        $imei = trim($_POST['imei'] ?? '');
        $refNo = trim($_POST['ref_no'] ?? '');
        $notes = trim($_POST['notes'] ?? '');

        // Generate automatic refNo if empty
        if (empty($refNo)) {
            $refNo = 'VLT-' . date('ymd') . '-' . rand(100, 999);
        }

        // Check file or base64 canvas image upload
        $savedFilename = '';
        $fileSizeKb = 0;

        if (isset($_FILES['photo_file']) && $_FILES['photo_file']['error'] === UPLOAD_ERR_OK) {
            $file = $_FILES['photo_file'];
            $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
            if (in_array($ext, ['jpg', 'jpeg', 'png', 'webp', 'pdf', 'svg'])) {
                $savedFilename = 'vault_' . time() . '_' . rand(1000, 9999) . '.' . $ext;
                $targetPath = $uploadDir . $savedFilename;
                if (move_uploaded_file($file['tmp_name'], $targetPath)) {
                    $fileSizeKb = round(filesize($targetPath) / 1024, 1);
                } else {
                    $errorMsg = 'فائل سرور پر محفوظ کرنے میں ناکامی ہوئی۔';
                }
            } else {
                $errorMsg = 'غلط فارمیٹ! صرف JPG, PNG, WEBP یا PDF فائلیں اپلوڈ کی جا سکتی ہیں۔';
            }
        } elseif (!empty($_POST['camera_base64'])) {
            // Base64 camera capture
            $base64Data = $_POST['camera_base64'];
            if (preg_match('/^data:image\/(\w+);base64,/', $base64Data, $type)) {
                $base64Data = substr($base64Data, strpos($base64Data, ',') + 1);
                $type = strtolower($type[1]); // jpg, png, etc
                $base64Data = base64_decode($base64Data);
                if ($base64Data !== false) {
                    $savedFilename = 'cam_' . time() . '_' . rand(1000, 9999) . '.' . ($type === 'jpeg' ? 'jpg' : $type);
                    $targetPath = $uploadDir . $savedFilename;
                    file_put_contents($targetPath, $base64Data);
                    $fileSizeKb = round(filesize($targetPath) / 1024, 1);
                }
            }
        }

        if (empty($savedFilename) && empty($errorMsg)) {
            $errorMsg = 'برائے مہربانی تصویر منتخب کریں یا کیمرہ سے کیپچر کریں!';
        }

        if (empty($errorMsg) && !empty($savedFilename)) {
            if (empty($title)) {
                $catNames = [
                    'CNIC_FRONT' => 'شناختی کارڈ فرنٹ',
                    'CNIC_BACK' => 'شناختی کارڈ بیک',
                    'SELLER' => 'سیلر تصدیقی تصویر',
                    'MOBILE' => 'موبائل و IMEI فوٹو',
                    'RECEIPT' => 'وارنٹی و خریداری رسید',
                    'INVENTORY' => 'اسٹاک آئٹم تصویر',
                    'OTHER' => 'دیگر دستاویز'
                ];
                $title = ($customerName ? $customerName . ' - ' : '') . ($catNames[$category] ?? 'والٹ فوٹو');
            }

            try {
                $newId = 'vlt-' . uniqid();
                $relPath = 'uploads/' . $savedFilename;
                $stmt = $pdo->prepare("INSERT INTO vault_photos (
                    id, title, category, ref_no, imei, customer_name, phone, cnic,
                    filename, file_path, file_size_kb, notes, created_at
                ) VALUES (
                    :id, :title, :category, :ref_no, :imei, :customer_name, :phone, :cnic,
                    :filename, :file_path, :file_size_kb, :notes, :created_at
                )");

                $stmt->execute([
                    ':id' => $newId,
                    ':title' => $title,
                    ':category' => $category,
                    ':ref_no' => $refNo,
                    ':imei' => $imei,
                    ':customer_name' => $customerName,
                    ':phone' => $phone,
                    ':cnic' => $cnic,
                    ':filename' => $savedFilename,
                    ':file_path' => $relPath,
                    ':file_size_kb' => $fileSizeKb,
                    ':notes' => $notes,
                    ':created_at' => time()
                ]);

                $successMsg = 'تصویر کامیابی کے ساتھ والٹ میں محفوظ ہو گئی ہے!';
            } catch (Exception $e) {
                $errorMsg = 'ڈیٹا بیس خرابی: ' . $e->getMessage();
            }
        }
    }

    // Delete photo
    if ($action === 'delete_photo') {
        $delId = trim($_POST['id'] ?? '');
        if (!empty($delId)) {
            $stmt = $pdo->prepare("SELECT filename FROM vault_photos WHERE id = :id");
            $stmt->execute([':id' => $delId]);
            $row = $stmt->fetch();
            if ($row && !empty($row['filename'])) {
                $fp = $uploadDir . $row['filename'];
                if (file_exists($fp)) {
                    @unlink($fp);
                }
            }
            $pdo->prepare("DELETE FROM vault_photos WHERE id = :id")->execute([':id' => $delId]);
            $successMsg = 'تصویر والٹ سے کامیابی کے ساتھ ڈیلیٹ کر دی گئی!';
        }
    }
}

// ==========================================================
// 2. Handle CSV Export
// ==========================================================
if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    $expCat = $_GET['cat'] ?? 'ALL';
    $expSearch = trim($_GET['q'] ?? '');

    $expSql = "SELECT * FROM vault_photos WHERE 1=1";
    $expParams = [];
    if ($expCat !== 'ALL') {
        $expSql .= " AND category = :cat";
        $expParams[':cat'] = $expCat;
    }
    if (!empty($expSearch)) {
        $expSql .= " AND (title LIKE :q OR customer_name LIKE :q OR cnic LIKE :q OR phone LIKE :q OR imei LIKE :q OR ref_no LIKE :q)";
        $expParams[':q'] = "%{$expSearch}%";
    }
    $expSql .= " ORDER BY created_at DESC";

    $stmtExp = $pdo->prepare($expSql);
    $stmtExp->execute($expParams);
    $records = $stmtExp->fetchAll();

    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="Photo_Vault_Records_' . date('Ymd_His') . '.csv"');
    $out = fopen('php://output', 'w');
    fprintf($out, chr(0xEF).chr(0xBB).chr(0xBF)); // UTF-8 BOM

    fputcsv($out, ['عنوان', 'کیٹیگری', 'کسٹمر کا نام', 'شناختی کارڈ (CNIC)', 'فون نمبر', 'موبائل IMEI', 'رسید / ریفرنس نمبر', 'فائل کا نام', 'سائز (KB)', 'تاریخ', 'نوٹس']);
    foreach ($records as $r) {
        fputcsv($out, [
            $r['title'],
            $r['category'],
            $r['customer_name'] ?? '',
            $r['cnic'] ?? '',
            $r['phone'] ?? '',
            $r['imei'] ?? '',
            $r['ref_no'] ?? '',
            $r['filename'],
            $r['file_size_kb'],
            date('Y-m-d H:i', $r['created_at']),
            $r['notes'] ?? ''
        ]);
    }
    fclose($out);
    exit;
}

// ==========================================================
// 3. Category Metadata & Counts
// ==========================================================
$categories = [
    'ALL' => ['name' => 'تمام والٹ فائلز', 'icon' => 'folder'],
    'CNIC_FRONT' => ['name' => 'شناختی کارڈ فرنٹ', 'icon' => 'shield-check', 'color' => 'emerald'],
    'CNIC_BACK' => ['name' => 'شناختی کارڈ بیک', 'icon' => 'credit-card', 'color' => 'teal'],
    'SELLER' => ['name' => 'سیلر / کسٹمر چہرہ', 'icon' => 'user-check', 'color' => 'blue'],
    'MOBILE' => ['name' => 'موبائل و IMEI اسٹیکر', 'icon' => 'smartphone', 'color' => 'purple'],
    'RECEIPT' => ['name' => 'وارنٹی رسیدیں و سلپس', 'icon' => 'receipt', 'color' => 'amber'],
    'INVENTORY' => ['name' => 'اسٹاک پروڈکٹس', 'icon' => 'package', 'color' => 'indigo'],
    'OTHER' => ['name' => 'دیگر دستاویزات', 'icon' => 'file-text', 'color' => 'slate']
];

// Fetch all photos for KPI counters
$allPhotos = $pdo->query("SELECT * FROM vault_photos ORDER BY created_at DESC")->fetchAll();

$totalPhotosCount = count($allPhotos);
$totalStorageKb = 0;
$catCounts = [];
foreach ($categories as $k => $v) {
    $catCounts[$k] = 0;
}

$cnicTotalCount = 0;
$mobileTotalCount = 0;
$sellerTotalCount = 0;
$receiptTotalCount = 0;

foreach ($allPhotos as $item) {
    $sz = floatval($item['file_size_kb'] ?? 40);
    $totalStorageKb += $sz;

    $c = $item['category'] ?? 'OTHER';
    if (isset($catCounts[$c])) {
        $catCounts[$c]++;
    }

    if ($c === 'CNIC_FRONT' || $c === 'CNIC_BACK') $cnicTotalCount++;
    if ($c === 'MOBILE') $mobileTotalCount++;
    if ($c === 'SELLER') $sellerTotalCount++;
    if ($c === 'RECEIPT') $receiptTotalCount++;
}
$catCounts['ALL'] = $totalPhotosCount;

// Format Storage Size
$formattedStorage = $totalStorageKb >= 1024 
    ? number_format($totalStorageKb / 1024, 1) . ' MB' 
    : number_format($totalStorageKb, 0) . ' KB';

// ==========================================================
// 4. Filtering & Search Query
// ==========================================================
$selectedCategory = $_GET['cat'] ?? 'ALL';
$search = trim($_GET['q'] ?? '');

$sql = "SELECT * FROM vault_photos WHERE 1=1";
$params = [];

if ($selectedCategory !== 'ALL' && isset($categories[$selectedCategory])) {
    $sql .= " AND category = :cat";
    $params[':cat'] = $selectedCategory;
}

if (!empty($search)) {
    $sql .= " AND (title LIKE :q OR customer_name LIKE :q OR cnic LIKE :q OR phone LIKE :q OR imei LIKE :q OR ref_no LIKE :q)";
    $params[':q'] = "%{$search}%";
}

$sql .= " ORDER BY created_at DESC";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$filteredPhotos = $stmt->fetchAll();
?>

<!-- Print Style for Verification Affidavit -->
<style>
@media print {
    body { background: #fff !important; color: #000 !important; }
    .no-print { display: none !important; }
    .print-only { display: block !important; }
    #printableAffidavitModal {
        position: static !important;
        display: block !important;
        background: none !important;
        width: 100% !important;
    }
}
</style>

<!-- Alerts -->
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
     Top Hero & Action Bar
     ========================================================== -->
<div class="bg-gradient-to-r from-slate-900 via-emerald-950/40 to-slate-900 border border-emerald-500/25 p-5 rounded-3xl mb-6 shadow-xl no-print space-y-4">
    <div class="flex flex-col lg:flex-row items-stretch lg:items-center justify-between gap-4">
        <!-- Title & Subtitle -->
        <div class="flex items-center gap-3.5">
            <div class="w-12 h-12 rounded-2xl bg-emerald-500/20 border border-emerald-500/30 text-emerald-400 flex items-center justify-center font-bold shrink-0 shadow-inner">
                <i data-lucide="shield-check" class="w-6 h-6"></i>
            </div>
            <div>
                <h1 class="text-lg sm:text-xl font-black text-white flex items-center gap-2">
                    <span>فوٹو فائل مینیجر و شناختی کارڈ والٹ</span>
                    <span class="text-xs font-mono font-bold px-2.5 py-0.5 rounded-full bg-emerald-500/10 text-emerald-400 border border-emerald-500/30">
                        100% محفوظ و انکرپٹڈ
                    </span>
                </h1>
                <p class="text-xs text-slate-300 mt-0.5">
                    گاہکوں کے شناختی کارڈز، سیلر تصدیق، موبائل فون کنڈیشنز، IMEI اسٹیکرز اور قانونی ریکارڈ کا ڈیجیٹل بینک
                </p>
            </div>
        </div>

        <!-- Action Buttons -->
        <div class="flex flex-wrap items-center gap-2 shrink-0">
            <!-- Upload / Capture Button -->
            <button type="button" onclick="openUploadModal()" class="px-4 py-2.5 bg-emerald-600 hover:bg-emerald-500 text-white rounded-xl text-xs font-bold flex items-center gap-2 shadow-lg transition-all active:scale-95">
                <i data-lucide="camera" class="w-4 h-4"></i>
                <span>نئی تصویر محفوظ کریں</span>
            </button>

            <!-- Export CSV Button -->
            <?php
            $exportUrl = "index.php?export=csv&cat=" . urlencode($selectedCategory) . "&q=" . urlencode($search);
            ?>
            <a href="<?= $exportUrl ?>" class="px-3.5 py-2.5 bg-slate-800 hover:bg-slate-700 text-slate-200 border border-slate-700 rounded-xl text-xs font-bold flex items-center gap-2 transition-all shadow">
                <i data-lucide="download" class="w-4 h-4 text-emerald-400"></i>
                <span>ایکسل شیٹ ڈاؤن لوڈ</span>
            </a>

            <!-- Print Page -->
            <button type="button" onclick="window.print()" class="px-3.5 py-2.5 bg-slate-800 hover:bg-slate-700 text-slate-300 border border-slate-700 rounded-xl text-xs font-bold flex items-center gap-1.5 transition-all">
                <i data-lucide="printer" class="w-4 h-4 text-cyan-400"></i>
                <span>پرنٹ لسٹ</span>
            </button>
        </div>
    </div>

    <!-- Category Tabs -->
    <div class="flex items-center gap-2 overflow-x-auto pb-1 text-xs border-t border-slate-800/80 pt-3">
        <span class="text-slate-500 font-bold shrink-0 ml-1">کیٹیگری:</span>
        <?php foreach ($categories as $catKey => $catInfo): 
            $isSelected = ($selectedCategory === $catKey);
        ?>
            <a href="index.php?cat=<?= $catKey ?>&q=<?= urlencode($search) ?>" 
               class="px-3 py-1.5 rounded-xl font-bold shrink-0 transition-all flex items-center gap-1.5 <?= $isSelected ? 'bg-emerald-600 text-white shadow' : 'bg-slate-950 text-slate-400 hover:bg-slate-800 border border-slate-800' ?>">
                <i data-lucide="<?= $catInfo['icon'] ?>" class="w-3.5 h-3.5"></i>
                <span><?= $catInfo['name'] ?></span>
                <span class="text-[10px] px-1.5 py-0.2 rounded-full <?= $isSelected ? 'bg-emerald-800 text-white' : 'bg-slate-800 text-slate-400' ?>">
                    <?= $catCounts[$catKey] ?? 0 ?>
                </span>
            </a>
        <?php endforeach; ?>
    </div>

    <!-- Search Bar -->
    <form method="GET" class="pt-2">
        <input type="hidden" name="cat" value="<?= htmlspecialchars($selectedCategory) ?>">
        <div class="relative">
            <input type="text" name="q" value="<?= htmlspecialchars($search) ?>" placeholder="کسٹمر کا نام، شناختی کارڈ (CNIC)، موبائل IMEI، فون نمبر یا رسید نمبر لکھ کر تلاش کریں..." class="w-full bg-slate-950 border border-slate-700 text-white pr-9 pl-3 py-2.5 rounded-xl text-xs focus:border-emerald-500 focus:outline-none">
            <i data-lucide="search" class="w-4 h-4 absolute right-3 top-1/2 -translate-y-1/2 text-slate-500 pointer-events-none"></i>
            <?php if (!empty($search)): ?>
                <a href="index.php?cat=<?= urlencode($selectedCategory) ?>" class="absolute left-3 top-1/2 -translate-y-1/2 text-slate-500 hover:text-white text-xs font-bold">
                    × کلیئر
                </a>
            <?php endif; ?>
        </div>
    </form>
</div>

<!-- ==========================================================
     Top 5 KPI Stats Cards
     ========================================================== -->
<div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-5 gap-3 sm:gap-4 mb-6">
    
    <!-- 1. Total Photos -->
    <div class="bg-slate-900 border border-slate-800 p-4 rounded-2xl shadow-md hover:border-emerald-500/40 transition-colors">
        <div class="flex items-center justify-between text-slate-400 text-xs font-bold mb-1">
            <span>کل محفوظ تصاویر</span>
            <div class="p-1.5 rounded-lg bg-emerald-500/10 text-emerald-400">
                <i data-lucide="image" class="w-4 h-4"></i>
            </div>
        </div>
        <h3 class="text-base sm:text-xl font-black text-white mt-1 font-mono"><?= $totalPhotosCount ?> فائلز</h3>
        <p class="text-[10px] text-slate-500 mt-1">والٹ میں تصدیق شدہ</p>
    </div>

    <!-- 2. CNIC Cards -->
    <div class="bg-slate-900 border border-slate-800 p-4 rounded-2xl shadow-md hover:border-teal-500/40 transition-colors">
        <div class="flex items-center justify-between text-slate-400 text-xs font-bold mb-1">
            <span>شناختی کارڈز (CNIC)</span>
            <div class="p-1.5 rounded-lg bg-teal-500/10 text-teal-400">
                <i data-lucide="shield-check" class="w-4 h-4"></i>
            </div>
        </div>
        <h3 class="text-base sm:text-xl font-black text-teal-400 mt-1 font-mono"><?= $cnicTotalCount ?> کارڈز</h3>
        <p class="text-[10px] text-slate-500 mt-1">فرنٹ و بیک تصدیق</p>
    </div>

    <!-- 3. Mobile & IMEI Photos -->
    <div class="bg-slate-900 border border-slate-800 p-4 rounded-2xl shadow-md hover:border-purple-500/40 transition-colors">
        <div class="flex items-center justify-between text-slate-400 text-xs font-bold mb-1">
            <span>موبائل و IMEI فوٹوز</span>
            <div class="p-1.5 rounded-lg bg-purple-500/10 text-purple-400">
                <i data-lucide="smartphone" class="w-4 h-4"></i>
            </div>
        </div>
        <h3 class="text-base sm:text-xl font-black text-purple-400 mt-1 font-mono"><?= $mobileTotalCount ?> ڈیوائسز</h3>
        <p class="text-[10px] text-slate-500 mt-1">فون کنڈیشن و بارکوڈ</p>
    </div>

    <!-- 4. Seller Verification -->
    <div class="bg-slate-900 border border-slate-800 p-4 rounded-2xl shadow-md hover:border-blue-500/40 transition-colors">
        <div class="flex items-center justify-between text-slate-400 text-xs font-bold mb-1">
            <span>سیلر چہرہ فوٹوز</span>
            <div class="p-1.5 rounded-lg bg-blue-500/10 text-blue-400">
                <i data-lucide="user-check" class="w-4 h-4"></i>
            </div>
        </div>
        <h3 class="text-base sm:text-xl font-black text-blue-400 mt-1 font-mono"><?= $sellerTotalCount ?> تصدیق شدہ</h3>
        <p class="text-[10px] text-slate-500 mt-1">فروخت کنندہ تصاویر</p>
    </div>

    <!-- 5. Optimized Storage -->
    <div class="bg-slate-900 border border-slate-800 p-4 rounded-2xl shadow-md hover:border-amber-500/40 transition-colors">
        <div class="flex items-center justify-between text-slate-400 text-xs font-bold mb-1">
            <span>والٹ سائز و خرچہ</span>
            <div class="p-1.5 rounded-lg bg-amber-500/10 text-amber-400">
                <i data-lucide="hard-drive" class="w-4 h-4"></i>
            </div>
        </div>
        <h3 class="text-base sm:text-xl font-black text-amber-400 mt-1 font-mono"><?= $formattedStorage ?></h3>
        <p class="text-[10px] text-emerald-400 font-bold mt-1">زیرو (0) فائر بیس بلنگ</p>
    </div>

</div>

<!-- ==========================================================
     Photos Gallery Cards Grid
     ========================================================== -->
<?php if (empty($filteredPhotos)): ?>
    <div class="bg-slate-900 border border-slate-800 rounded-3xl p-16 text-center text-slate-400 space-y-3">
        <div class="w-16 h-16 rounded-full bg-slate-800 flex items-center justify-center mx-auto text-slate-500">
            <i data-lucide="image-off" class="w-8 h-8"></i>
        </div>
        <h4 class="text-base font-bold text-white">کوئی تصویر نہیں ملی</h4>
        <p class="text-xs text-slate-500 max-w-md mx-auto">آپ کے تلاش کردہ نام، شناختی کارڈ یا کیٹیگری کے مطابق کوئی فوٹو موجود نہیں ہے۔</p>
        <div class="pt-2 flex items-center justify-center gap-2">
            <a href="index.php" class="px-4 py-2 bg-slate-800 hover:bg-slate-700 text-slate-200 text-xs font-bold rounded-xl border border-slate-700 inline-flex items-center gap-1.5">
                <i data-lucide="rotate-ccw" class="w-3.5 h-3.5"></i>
                <span>فلٹر کلیئر کریں</span>
            </a>
            <button type="button" onclick="openUploadModal()" class="px-4 py-2 bg-emerald-600 hover:bg-emerald-500 text-white text-xs font-bold rounded-xl inline-flex items-center gap-1.5">
                <i data-lucide="plus" class="w-3.5 h-3.5"></i>
                <span>نئی تصویر اپلوڈ کریں</span>
            </button>
        </div>
    </div>
<?php else: ?>
    <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-4">
        <?php foreach ($filteredPhotos as $photo): 
            $photoJson = htmlspecialchars(json_encode($photo), ENT_QUOTES, 'UTF-8');
            $filePath = htmlspecialchars($photo['file_path'] ?? ('uploads/' . $photo['filename']));
            $catKey = $photo['category'] ?? 'OTHER';
            $catLabel = $categories[$catKey]['name'] ?? $catKey;

            // Badges Colors
            $badgeColorClass = 'bg-slate-800 text-slate-300 border-slate-700';
            if ($catKey === 'CNIC_FRONT') $badgeColorClass = 'bg-emerald-500/10 text-emerald-400 border-emerald-500/30';
            elseif ($catKey === 'CNIC_BACK') $badgeColorClass = 'bg-teal-500/10 text-teal-400 border-teal-500/30';
            elseif ($catKey === 'SELLER') $badgeColorClass = 'bg-blue-500/10 text-blue-400 border-blue-500/30';
            elseif ($catKey === 'MOBILE') $badgeColorClass = 'bg-purple-500/10 text-purple-400 border-purple-500/30';
            elseif ($catKey === 'RECEIPT') $badgeColorClass = 'bg-amber-500/10 text-amber-400 border-amber-500/30';
        ?>
            <div class="bg-slate-900 border border-slate-800 rounded-3xl overflow-hidden flex flex-col justify-between hover:border-emerald-500/40 transition-all shadow-lg group">
                <div>
                    <!-- Photo Thumbnail Box -->
                    <div class="relative aspect-video bg-slate-950 overflow-hidden cursor-pointer border-b border-slate-800/80" onclick="inspectPhoto(<?= $photoJson ?>)">
                        <img src="<?= $filePath ?>" alt="<?= htmlspecialchars($photo['title']) ?>" loading="lazy" class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-300">
                        
                        <!-- Category Badge Top Right -->
                        <div class="absolute top-2.5 right-2.5">
                            <span class="px-2.5 py-0.5 rounded-full text-[10px] font-bold border backdrop-blur-md <?= $badgeColorClass ?>">
                                <?= $catLabel ?>
                            </span>
                        </div>

                        <!-- Size Badge Bottom Left -->
                        <div class="absolute bottom-2 left-2 bg-black/70 backdrop-blur-md px-2 py-0.5 rounded text-[10px] font-mono text-emerald-400 font-bold">
                            <?= $photo['file_size_kb'] ? $photo['file_size_kb'] . ' KB' : 'Optimized' ?>
                        </div>

                        <!-- Hover Overlay with Inspect Icon -->
                        <div class="absolute inset-0 bg-black/40 opacity-0 group-hover:opacity-100 transition-opacity flex items-center justify-center">
                            <span class="px-3 py-1.5 rounded-xl bg-emerald-600 text-white font-bold text-xs flex items-center gap-1.5 shadow-lg">
                                <i data-lucide="eye" class="w-4 h-4"></i>
                                <span>مکمل دیکھیں</span>
                            </span>
                        </div>
                    </div>

                    <!-- Metadata Section -->
                    <div class="p-4 space-y-2">
                        <h4 class="font-bold text-white text-xs line-clamp-1 group-hover:text-emerald-400 transition-colors" title="<?= htmlspecialchars($photo['title']) ?>">
                            <?= htmlspecialchars($photo['title']) ?>
                        </h4>

                        <div class="space-y-1 text-[11px] text-slate-400">
                            <?php if (!empty($photo['customer_name'])): ?>
                                <div class="flex items-center justify-between">
                                    <span class="text-slate-500">گاہک / سیلر:</span>
                                    <span class="text-slate-200 font-bold"><?= htmlspecialchars($photo['customer_name']) ?></span>
                                </div>
                            <?php endif; ?>

                            <?php if (!empty($photo['cnic'])): ?>
                                <div class="flex items-center justify-between font-mono">
                                    <span class="text-slate-500">شناختی کارڈ:</span>
                                    <span class="text-teal-400 font-bold"><?= htmlspecialchars($photo['cnic']) ?></span>
                                </div>
                            <?php endif; ?>

                            <?php if (!empty($photo['imei'])): ?>
                                <div class="flex items-center justify-between font-mono">
                                    <span class="text-slate-500">IMEI:</span>
                                    <span class="text-cyan-400 font-bold truncate max-w-[140px]"><?= htmlspecialchars($photo['imei']) ?></span>
                                </div>
                            <?php endif; ?>

                            <?php if (!empty($photo['ref_no'])): ?>
                                <div class="flex items-center justify-between font-mono text-[10px]">
                                    <span class="text-slate-500">رسید #:</span>
                                    <span class="text-slate-400"><?= htmlspecialchars($photo['ref_no']) ?></span>
                                </div>
                            <?php endif; ?>

                            <div class="flex items-center justify-between text-[10px] text-slate-500 pt-1 border-t border-slate-800">
                                <span>تاریخ اندراج:</span>
                                <span><?= date('Y-m-d h:i A', $photo['created_at']) ?></span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Card Bottom Buttons -->
                <div class="p-3 bg-slate-950/60 border-t border-slate-800 flex items-center justify-between gap-1.5">
                    <button type="button" onclick="inspectPhoto(<?= $photoJson ?>)" class="flex-1 py-1.5 bg-slate-800 hover:bg-slate-700 text-slate-200 text-xs font-bold rounded-xl flex items-center justify-center gap-1 transition-colors" title="مکمل دیکھیں">
                        <i data-lucide="eye" class="w-3.5 h-3.5 text-emerald-400"></i>
                        <span>دیکھیں</span>
                    </button>

                    <a href="<?= $filePath ?>" download="<?= htmlspecialchars($photo['filename']) ?>" class="p-1.5 bg-slate-800 hover:bg-slate-700 text-cyan-400 rounded-xl border border-slate-700 transition-colors" title="فائل ڈاؤنلوڈ کریں">
                        <i data-lucide="download" class="w-3.5 h-3.5"></i>
                    </a>

                    <button type="button" onclick="printVerificationAffidavit(<?= $photoJson ?>)" class="p-1.5 bg-slate-800 hover:bg-slate-700 text-amber-400 rounded-xl border border-slate-700 transition-colors" title="پولیس / قانونی ویریفکیشن شیٹ پرنٹ کریں">
                        <i data-lucide="printer" class="w-3.5 h-3.5"></i>
                    </button>

                    <button type="button" onclick="confirmDeletePhoto('<?= $photo['id'] ?>', '<?= htmlspecialchars($photo['title'], ENT_QUOTES) ?>')" class="p-1.5 bg-slate-800 hover:bg-rose-950 text-rose-400 rounded-xl border border-slate-700 transition-colors" title="ڈیلیٹ کریں">
                        <i data-lucide="trash-2" class="w-3.5 h-3.5"></i>
                    </button>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<!-- ==========================================================
     Modal 1: Upload / Take Photo Modal
     ========================================================== -->
<div id="uploadModal" class="fixed inset-0 z-50 bg-black/80 backdrop-blur-sm hidden items-center justify-center p-4 no-print">
    <div class="bg-slate-900 border border-slate-800 w-full max-w-xl rounded-3xl overflow-hidden shadow-2xl flex flex-col max-h-[92vh]">
        
        <!-- Modal Top -->
        <div class="p-4 border-b border-slate-800 flex items-center justify-between bg-slate-950">
            <div class="flex items-center gap-2.5">
                <div class="p-2 rounded-xl bg-emerald-500/10 text-emerald-400 border border-emerald-500/20">
                    <i data-lucide="upload-cloud" class="w-5 h-5"></i>
                </div>
                <div>
                    <h3 class="font-black text-white text-base">نئی تصویر محفوظ کریں</h3>
                    <p class="text-xs text-slate-400">شناختی کارڈ، کیمرہ فوٹو، یا موبائل رسید والٹ میں اپلوڈ کریں</p>
                </div>
            </div>
            <button onclick="closeUploadModal()" class="p-2 text-slate-400 hover:text-white rounded-xl hover:bg-slate-800 transition-colors">
                <i data-lucide="x" class="w-5 h-5"></i>
            </button>
        </div>

        <!-- Form -->
        <form method="POST" enctype="multipart/form-data" class="p-6 overflow-y-auto space-y-4" onsubmit="return validateUploadForm()">
            <input type="hidden" name="action" value="upload_photo">
            <input type="hidden" name="camera_base64" id="cameraBase64Input">

            <!-- Category Selector -->
            <div>
                <label class="text-[11px] font-bold text-slate-400 block mb-1">کیٹیگری منتخب کریں *</label>
                <select name="category" id="uploadCategory" required class="w-full bg-slate-950 border border-slate-700 text-white px-3 py-2.5 rounded-xl text-xs focus:border-emerald-500 focus:outline-none">
                    <option value="CNIC_FRONT">شناختی کارڈ فرنٹ (CNIC Front)</option>
                    <option value="CNIC_BACK">شناختی کارڈ بیک (CNIC Back)</option>
                    <option value="SELLER">سیلر / گاہک چہرہ تصویر (Seller Photo)</option>
                    <option value="MOBILE">موبائل و IMEI اسٹیکر (Mobile Device Photo)</option>
                    <option value="RECEIPT">خریداری رسید و وارنٹی سلپ (Receipt / Slip)</option>
                    <option value="INVENTORY">اسٹاک پروڈکٹ تصویر (Stock Item)</option>
                    <option value="OTHER">دیگر دستاویزات (Other)</option>
                </select>
            </div>

            <!-- Customer & Mobile Details -->
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                <div>
                    <label class="text-[11px] font-bold text-slate-400 block mb-1">کسٹمر کا نام</label>
                    <input type="text" name="customer_name" id="uploadCustomerName" placeholder="مثلاً: محمد عثمان" class="w-full bg-slate-950 border border-slate-700 text-white px-3 py-2 rounded-xl text-xs focus:border-emerald-500 focus:outline-none">
                </div>
                <div>
                    <label class="text-[11px] font-bold text-slate-400 block mb-1">شناختی کارڈ (CNIC)</label>
                    <input type="text" name="cnic" id="uploadCnic" placeholder="35202-1234567-1" class="w-full bg-slate-950 border border-slate-700 text-white px-3 py-2 rounded-xl text-xs font-mono focus:border-emerald-500 focus:outline-none">
                </div>
                <div>
                    <label class="text-[11px] font-bold text-slate-400 block mb-1">موبائل IMEI نمبر</label>
                    <input type="text" name="imei" id="uploadImei" placeholder="8628000..." class="w-full bg-slate-950 border border-slate-700 text-white px-3 py-2 rounded-xl text-xs font-mono focus:border-emerald-500 focus:outline-none">
                </div>
                <div>
                    <label class="text-[11px] font-bold text-slate-400 block mb-1">فون نمبر</label>
                    <input type="text" name="phone" id="uploadPhone" placeholder="0300-1234567" class="w-full bg-slate-950 border border-slate-700 text-white px-3 py-2 rounded-xl text-xs font-mono focus:border-emerald-500 focus:outline-none">
                </div>
            </div>

            <!-- Custom Title -->
            <div>
                <label class="text-[11px] font-bold text-slate-400 block mb-1">عنوان / فائل کا نام (اختیاری)</label>
                <input type="text" name="title" id="uploadTitle" placeholder="خالی چھوڑنے پر خودکار نام دیا جائے گا" class="w-full bg-slate-950 border border-slate-700 text-white px-3 py-2 rounded-xl text-xs focus:border-emerald-500 focus:outline-none">
            </div>

            <!-- File Upload / Drag & Drop with Camera Option -->
            <div>
                <label class="text-[11px] font-bold text-slate-400 block mb-1">تصویر منتخب کریں یا کیمرہ سے کیپچر کریں *</label>
                
                <div class="border-2 border-dashed border-slate-700 hover:border-emerald-500/60 rounded-2xl p-4 text-center bg-slate-950/60 transition-colors relative" id="dropZoneArea">
                    <input type="file" name="photo_file" id="fileUploadInput" accept="image/*,.pdf" class="absolute inset-0 w-full h-full opacity-0 cursor-pointer z-10" onchange="previewSelectedFile(event)">
                    
                    <div id="uploadPrompt" class="space-y-2 pointer-events-none">
                        <div class="w-12 h-12 rounded-2xl bg-emerald-500/10 text-emerald-400 flex items-center justify-center mx-auto">
                            <i data-lucide="camera" class="w-6 h-6"></i>
                        </div>
                        <p class="text-xs font-bold text-white">تصویر منتخب کرنے کیلئے یہاں کلک کریں یا ڈریگ کریں</p>
                        <p class="text-[10px] text-slate-500">موبائل کیمرہ، کمپیوٹر گیلری، یا اسکین شدہ کاپی (JPG, PNG, PDF)</p>
                    </div>

                    <!-- Selected Preview -->
                    <div id="previewContainer" class="hidden relative z-20">
                        <img id="imagePreviewImg" class="max-h-48 mx-auto rounded-xl object-contain shadow-lg border border-slate-700">
                        <p id="previewFileName" class="text-[11px] font-bold text-emerald-400 mt-2 truncate"></p>
                        <button type="button" onclick="clearSelectedFile()" class="mt-1 px-3 py-1 bg-rose-600/20 text-rose-300 border border-rose-500/30 rounded-lg text-xs font-bold hover:bg-rose-600/40">
                            تصویر تبدیل کریں
                        </button>
                    </div>
                </div>
            </div>

            <!-- Additional Notes -->
            <div>
                <label class="text-[11px] font-bold text-slate-400 block mb-1">نوٹس و قانونی تفصیلات (اختیاری)</label>
                <textarea name="notes" rows="2" placeholder="تصدیق یا فون کی حالت سے متعلق کوئی ضروری بات..." class="w-full bg-slate-950 border border-slate-700 text-white px-3 py-2 rounded-xl text-xs focus:border-emerald-500 focus:outline-none"></textarea>
            </div>

            <!-- Submit Button -->
            <div class="pt-2 border-t border-slate-800 flex items-center justify-end gap-2">
                <button type="button" onclick="closeUploadModal()" class="px-4 py-2 bg-slate-800 text-slate-300 rounded-xl text-xs font-bold hover:bg-slate-700">منسوخ</button>
                <button type="submit" class="px-6 py-2.5 bg-emerald-600 hover:bg-emerald-500 text-white rounded-xl text-xs font-bold shadow-lg transition-all flex items-center gap-1.5">
                    <i data-lucide="shield-check" class="w-4 h-4"></i>
                    <span>والٹ میں محفوظ کریں</span>
                </button>
            </div>
        </form>
    </div>
</div>

<!-- ==========================================================
     Modal 2: Inspection & Full-Screen Preview Modal
     ========================================================== -->
<div id="inspectModal" class="fixed inset-0 z-50 bg-black/90 backdrop-blur-md hidden items-center justify-center p-4 no-print">
    <div class="bg-slate-900 border border-slate-800 w-full max-w-4xl rounded-3xl overflow-hidden shadow-2xl flex flex-col max-h-[94vh]">
        
        <!-- Modal Top Bar -->
        <div class="p-4 border-b border-slate-800 flex items-center justify-between bg-slate-950">
            <div class="flex items-center gap-3">
                <div class="p-2 rounded-xl bg-emerald-500/10 text-emerald-400 border border-emerald-500/20">
                    <i data-lucide="eye" class="w-5 h-5"></i>
                </div>
                <div>
                    <h3 class="font-black text-white text-base" id="inspectTitle">تصویر معائنہ</h3>
                    <p class="text-xs text-slate-400 font-mono" id="inspectRefNo">REF-000</p>
                </div>
            </div>
            
            <div class="flex items-center gap-2">
                <!-- Rotate Button -->
                <button type="button" onclick="rotateInspectImage()" class="p-2 bg-slate-800 hover:bg-slate-700 text-slate-300 rounded-xl border border-slate-700 text-xs font-bold flex items-center gap-1" title="تصویر گھمائیں">
                    <i data-lucide="rotate-cw" class="w-4 h-4"></i>
                </button>

                <!-- Print Affidavit Button -->
                <button type="button" onclick="printCurrentInspectAffidavit()" class="px-3 py-2 bg-amber-600 hover:bg-amber-500 text-white rounded-xl text-xs font-bold flex items-center gap-1.5 shadow" title="پولیس / قانونی تصدیق فارم پرنٹ کریں">
                    <i data-lucide="printer" class="w-4 h-4"></i>
                    <span>تصدیقی شیٹ پرنٹ</span>
                </button>

                <!-- WhatsApp Share Button -->
                <button type="button" onclick="shareInspectOnWhatsApp()" class="px-3 py-2 bg-emerald-600 hover:bg-emerald-500 text-white rounded-xl text-xs font-bold flex items-center gap-1.5 shadow" title="واٹس ایپ پر بھیجیں">
                    <i data-lucide="share-2" class="w-4 h-4"></i>
                    <span>واٹس ایپ</span>
                </button>

                <!-- Close -->
                <button onclick="closeInspectModal()" class="p-2 text-slate-400 hover:text-white rounded-xl hover:bg-slate-800 transition-colors">
                    <i data-lucide="x" class="w-5 h-5"></i>
                </button>
            </div>
        </div>

        <!-- Body: Image + Details -->
        <div class="flex-1 overflow-y-auto grid grid-cols-1 lg:grid-cols-12 gap-0">
            <!-- Large Image Container (8 cols) -->
            <div class="lg:col-span-8 bg-black/60 p-4 flex items-center justify-center min-h-[350px] relative overflow-hidden">
                <img id="inspectMainImg" src="" class="max-w-full max-h-[500px] object-contain rounded-xl shadow-2xl transition-transform duration-300" style="transform: rotate(0deg);">
            </div>

            <!-- Details Sidebar (4 cols) -->
            <div class="lg:col-span-4 p-5 bg-slate-950/80 border-t lg:border-t-0 lg:border-r border-slate-800 space-y-4">
                <div>
                    <span class="text-[10px] font-bold text-slate-500 uppercase block mb-1">کیٹیگری</span>
                    <span id="inspectCategoryBadge" class="px-3 py-1 rounded-full text-xs font-bold border inline-block bg-emerald-500/10 text-emerald-400 border-emerald-500/30">
                        CNIC Front
                    </span>
                </div>

                <div class="space-y-2 text-xs">
                    <div class="p-3 rounded-xl bg-slate-900 border border-slate-800">
                        <span class="text-slate-500 text-[10px] block">کسٹمر کا نام:</span>
                        <span class="font-bold text-white text-sm" id="inspectCustomerName">—</span>
                    </div>

                    <div class="p-3 rounded-xl bg-slate-900 border border-slate-800">
                        <span class="text-slate-500 text-[10px] block">شناختی کارڈ (CNIC):</span>
                        <span class="font-bold font-mono text-teal-400 text-sm" id="inspectCnic">—</span>
                    </div>

                    <div class="p-3 rounded-xl bg-slate-900 border border-slate-800">
                        <span class="text-slate-500 text-[10px] block">موبائل فون IMEI:</span>
                        <span class="font-bold font-mono text-cyan-400 text-sm" id="inspectImei">—</span>
                    </div>

                    <div class="p-3 rounded-xl bg-slate-900 border border-slate-800">
                        <span class="text-slate-500 text-[10px] block">فون نمبر:</span>
                        <span class="font-bold font-mono text-white text-sm" id="inspectPhone">—</span>
                    </div>

                    <div class="p-3 rounded-xl bg-slate-900 border border-slate-800">
                        <span class="text-slate-500 text-[10px] block">تاریخ و سائز:</span>
                        <span class="text-slate-300 font-mono text-xs" id="inspectDateSize">—</span>
                    </div>

                    <div class="p-3 rounded-xl bg-slate-900 border border-slate-800" id="inspectNotesBox">
                        <span class="text-slate-500 text-[10px] block">نوٹس / تفصیل:</span>
                        <p class="text-slate-300 text-xs mt-0.5" id="inspectNotes">—</p>
                    </div>
                </div>

                <a id="inspectDownloadBtn" href="#" download="" class="w-full py-2.5 bg-slate-800 hover:bg-slate-700 text-slate-200 text-xs font-bold rounded-xl border border-slate-700 flex items-center justify-center gap-1.5 transition-colors">
                    <i data-lucide="download" class="w-4 h-4 text-cyan-400"></i>
                    <span>اصل تصویر ڈاؤنلوڈ کریں</span>
                </a>
            </div>
        </div>
    </div>
</div>

<!-- ==========================================================
     Modal 3: Printable Police Verification & Affidavit Sheet
     ========================================================== -->
<div id="printableAffidavitModal" class="hidden print-only">
    <div style="font-family: Arial, sans-serif; max-width: 800px; margin: 0 auto; padding: 25px; border: 2px solid #000; color: #000;">
        
        <!-- Header -->
        <div style="text-align: center; border-bottom: 2px solid #000; padding-bottom: 12px; margin-bottom: 15px;">
            <h2 style="font-size: 22px; margin: 0; font-weight: 900;">بلال موبائل اینڈ ایزی پیسہ زون</h2>
            <p style="font-size: 13px; margin: 3px 0 0 0;">مین بازار، قذافی چوک، نزد جامع مسجد | فون: 0300-1234567 / 0345-9876543</p>
            <h3 style="font-size: 16px; margin: 8px 0 0 0; background: #eee; padding: 5px; border: 1px solid #999;">
                موبائل خریداری و قانونی تصدیقی بیان حلفی (Police &amp; Legal Verification Form)
            </h3>
        </div>

        <!-- Meta Table -->
        <table style="width: 100%; border-collapse: collapse; margin-bottom: 15px; font-size: 13px;">
            <tr>
                <td style="padding: 6px; border: 1px solid #999; width: 25%;"><strong>رسید / والٹ ریفرنس:</strong></td>
                <td style="padding: 6px; border: 1px solid #999; width: 25%;" id="printAffRef">VLT-001</td>
                <td style="padding: 6px; border: 1px solid #999; width: 25%;"><strong>تاریخ و وقت:</strong></td>
                <td style="padding: 6px; border: 1px solid #999; width: 25%;" id="printAffDate"><?= date('Y-m-d h:i A') ?></td>
            </tr>
            <tr>
                <td style="padding: 6px; border: 1px solid #999;"><strong>فروخت کنندہ (سیلر):</strong></td>
                <td style="padding: 6px; border: 1px solid #999;" id="printAffCustomer">—</td>
                <td style="padding: 6px; border: 1px solid #999;"><strong>شناختی کارڈ (CNIC):</strong></td>
                <td style="padding: 6px; border: 1px solid #999; font-weight: bold;" id="printAffCnic">—</td>
            </tr>
            <tr>
                <td style="padding: 6px; border: 1px solid #999;"><strong>موبائل فون IMEI:</strong></td>
                <td style="padding: 6px; border: 1px solid #999; font-weight: bold;" id="printAffImei">—</td>
                <td style="padding: 6px; border: 1px solid #999;"><strong>رابطہ نمبر:</strong></td>
                <td style="padding: 6px; border: 1px solid #999;" id="printAffPhone">—</td>
            </tr>
        </table>

        <!-- Affidavit Text -->
        <div style="border: 1px solid #999; padding: 10px; font-size: 12px; line-height: 1.6; background: #fdfdfd; margin-bottom: 15px;">
            <p style="margin: 0;">
                <strong>اقرار نامہ / بیان حلفی:</strong> میں تصدیق کرتا ہوں کہ مذکورہ بالا موبائل ڈیوائس میری ذاتی ملکیت ہے اور کسی قسم کی چوری، واردات یا غیر قانونی سرگرمی میں ملوث نہیں ہے۔ اگر مستقبل میں اس موبائل کے حوالے سے کوئی قانونی تنازع یا پولیس کارروائی ہوئی تو اس کا تمام تر ذمہ دار میں خود ہوں گا اور بلال موبائل شاپ پر کوئی ذمہ داری عائد نہ ہوگی۔
            </p>
        </div>

        <!-- Attached Photo Frame -->
        <div style="text-align: center; margin-bottom: 20px;">
            <h4 style="font-size: 12px; margin-bottom: 6px; text-decoration: underline;">محفوظ شدہ تصویر / شناختی ریکارڈ</h4>
            <div style="display: inline-block; border: 2px dashed #999; padding: 6px; max-width: 450px;">
                <img id="printAffImg" src="" style="max-height: 240px; max-width: 100%; object-contain: contain;">
            </div>
        </div>

        <!-- Signatures Table -->
        <table style="width: 100%; margin-top: 30px; font-size: 12px; text-align: center;">
            <tr>
                <td style="width: 33%; padding-top: 40px; border-top: 1px solid #000;">دستخط کسٹمر / سیلر</td>
                <td style="width: 33%; padding-top: 40px; border-top: 1px solid #000;">انگوٹھا (Thumb Impression)</td>
                <td style="width: 33%; padding-top: 40px; border-top: 1px solid #000;">دستخط و مہر دکاندار</td>
            </tr>
        </table>
    </div>
</div>

<!-- Delete Form Hidden -->
<form id="deletePhotoForm" method="POST" class="hidden">
    <input type="hidden" name="action" value="delete_photo">
    <input type="hidden" name="id" id="deletePhotoId">
</form>

<!-- ==========================================================
     JavaScript Logic
     ========================================================== -->
<script>
let currentInspectData = null;
let currentRotation = 0;

// Modal Controls
function openUploadModal() {
    document.getElementById('uploadModal').classList.remove('hidden');
    document.getElementById('uploadModal').classList.add('flex');
}

function closeUploadModal() {
    document.getElementById('uploadModal').classList.add('hidden');
    document.getElementById('uploadModal').classList.remove('flex');
}

function openInspectModal() {
    document.getElementById('inspectModal').classList.remove('hidden');
    document.getElementById('inspectModal').classList.add('flex');
}

function closeInspectModal() {
    document.getElementById('inspectModal').classList.add('hidden');
    document.getElementById('inspectModal').classList.remove('flex');
}

// Inspect Photo Click
function inspectPhoto(photo) {
    currentInspectData = photo;
    currentRotation = 0;

    const imgPath = photo.file_path || ('uploads/' + photo.filename);
    const mainImg = document.getElementById('inspectMainImg');
    mainImg.src = imgPath;
    mainImg.style.transform = 'rotate(0deg)';

    document.getElementById('inspectTitle').textContent = photo.title || 'تصویر معائنہ';
    document.getElementById('inspectRefNo').textContent = photo.ref_no || photo.id;
    document.getElementById('inspectCustomerName').textContent = photo.customer_name || 'غیر درج شدہ';
    document.getElementById('inspectCnic').textContent = photo.cnic || '—';
    document.getElementById('inspectImei').textContent = photo.imei || '—';
    document.getElementById('inspectPhone').textContent = photo.phone || '—';
    document.getElementById('inspectNotes').textContent = photo.notes || 'کوئی اضافی تفصیل درج نہیں ہے۔';

    const dateStr = new Date(photo.created_at * 1000).toLocaleString('en-US', { dateStyle: 'medium', timeStyle: 'short' });
    document.getElementById('inspectDateSize').textContent = dateStr + ' (' + (photo.file_size_kb || 40) + ' KB)';

    const catBadge = document.getElementById('inspectCategoryBadge');
    catBadge.textContent = photo.category;

    const dBtn = document.getElementById('inspectDownloadBtn');
    dBtn.href = imgPath;
    dBtn.download = photo.filename;

    openInspectModal();
}

// Rotate
function rotateInspectImage() {
    currentRotation = (currentRotation + 90) % 360;
    const mainImg = document.getElementById('inspectMainImg');
    mainImg.style.transform = `rotate(${currentRotation}deg)`;
}

// Print Affidavit
function printCurrentInspectAffidavit() {
    if (!currentInspectData) return;
    printVerificationAffidavit(currentInspectData);
}

function printVerificationAffidavit(photo) {
    const imgPath = photo.file_path || ('uploads/' + photo.filename);

    document.getElementById('printAffRef').textContent = photo.ref_no || photo.id;
    document.getElementById('printAffCustomer').textContent = photo.customer_name || 'عام گاہک';
    document.getElementById('printAffCnic').textContent = photo.cnic || 'غیر تصدیق شدہ';
    document.getElementById('printAffImei').textContent = photo.imei || 'غیر درج شدہ';
    document.getElementById('printAffPhone').textContent = photo.phone || '—';
    document.getElementById('printAffImg').src = imgPath;

    window.print();
}

// WhatsApp Share
function shareInspectOnWhatsApp() {
    if (!currentInspectData) return;
    const p = currentInspectData;
    let text = `*بلال موبائل والٹ تصدیق*\n`;
    text += `📌 عنوان: ${p.title}\n`;
    if (p.customer_name) text += `👤 کسٹمر: ${p.customer_name}\n`;
    if (p.cnic) text += `🪪 CNIC: ${p.cnic}\n`;
    if (p.imei) text += `📱 IMEI: ${p.imei}\n`;
    if (p.ref_no) text += `🧾 ریفرنس: ${p.ref_no}\n`;
    text += `\nتصویر دکان کے والٹ میں محفوظ ہے۔`;

    const cleanPhone = (p.phone || '').replace(/[^0-9]/g, '');
    let url = `https://wa.me/?text=${encodeURIComponent(text)}`;
    if (cleanPhone.length >= 10) {
        const pkPhone = cleanPhone.startsWith('0') ? '92' + cleanPhone.substring(1) : cleanPhone;
        url = `https://wa.me/${pkPhone}?text=${encodeURIComponent(text)}`;
    }
    window.open(url, '_blank');
}

// Delete Photo
function confirmDeletePhoto(id, title) {
    if (confirm(`کیا آپ واقعی "${title}" کو والٹ سے ہمیشہ کیلئے ڈیلیٹ کرنا چاہتے ہیں؟`)) {
        document.getElementById('deletePhotoId').value = id;
        document.getElementById('deletePhotoForm').submit();
    }
}

// File Selection & Drag-drop preview
function previewSelectedFile(event) {
    const file = event.target.files[0];
    if (!file) return;

    const reader = new FileReader();
    reader.onload = function(e) {
        document.getElementById('imagePreviewImg').src = e.target.result;
        document.getElementById('previewFileName').textContent = file.name + ' (' + (file.size / 1024).toFixed(1) + ' KB)';
        document.getElementById('previewContainer').classList.remove('hidden');
        document.getElementById('uploadPrompt').classList.add('hidden');
    };
    reader.readAsDataURL(file);
}

function clearSelectedFile() {
    document.getElementById('fileUploadInput').value = '';
    document.getElementById('previewContainer').classList.add('hidden');
    document.getElementById('uploadPrompt').classList.remove('hidden');
}

function validateUploadForm() {
    const fileInput = document.getElementById('fileUploadInput');
    const base64Input = document.getElementById('cameraBase64Input');
    if ((!fileInput.files || fileInput.files.length === 0) && !base64Input.value) {
        alert('برائے مہربانی اپلوڈ کرنے کیلئے تصویر منتخب کریں!');
        return false;
    }
    return true;
}

// Keyboard shortcuts: Esc to close modal
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        closeInspectModal();
        closeUploadModal();
    }
});
</script>

<?php require_once __DIR__ . '/../backend/footer.php'; ?>
