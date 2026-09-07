<?php
require_once __DIR__ . '/../backend/config.php';
$pageTitle = $isUrdu ? 'بارکوڈ اسٹوڈیو و پرنٹنگ (Barcode Studio)' : 'Barcode Studio & Thermal Printing (بارکوڈ اسٹوڈیو)';
$activeMenu = 'barcode_studio';
require_once __DIR__ . '/../backend/header.php';

// Fetch all retail products
$productsStmt = $pdo->query("SELECT id, name, category, brand_or_model, cost_price, sale_price, stock_quantity, sku, imei_or_serial FROM products ORDER BY name ASC");
$products = $productsStmt->fetchAll();

// Fetch mobile inventory with IMEI for phone label generation
$mobileStmt = $pdo->query("SELECT id, mobile_name, brand, imei_1, imei_2, color, ram_rom, condition, purchase_price, expected_sale_price, status FROM mobile_purchases WHERE status = 'IN_STOCK' ORDER BY id DESC LIMIT 50");
$mobiles = $mobileStmt->fetchAll();
if (empty($mobiles)) {
    $mobiles = $pdo->query("SELECT id, mobile_name, brand, imei_1, imei_2, color, ram_rom, condition, purchase_price, expected_sale_price, status FROM mobile_purchases ORDER BY id DESC LIMIT 20")->fetchAll();
}
?>

<!-- External Barcode and QR Libraries with Offline Fallback Safety -->
<script src="https://cdn.jsdelivr.net/npm/jsbarcode@3.11.5/dist/JsBarcode.all.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/qrcodejs@1.0.0/qrcode.min.js"></script>

<style>
/* CSS for Print Modes - Calibrated for Thermal & A4 Printers */
@page {
    margin: 0mm !important;
    size: auto;
}
@media print {
    html, body {
        background: #ffffff !important;
        color: #000000 !important;
        margin: 0 !important;
        padding: 0 !important;
        width: 100% !important;
        height: auto !important;
        -webkit-print-color-adjust: exact !important;
        print-color-adjust: exact !important;
    }
    .no-print, header, aside, footer, nav, #mainSidebar {
        display: none !important;
    }
    .print-only-container {
        display: block !important;
        width: 100% !important;
        margin: 0 !important;
        padding: 0 !important;
        background: #ffffff !important;
    }

    /* 50x30mm Thermal Roll Single Column (Most Popular) */
    .print-template-50x30 .label-card {
        width: 48mm !important;
        height: 28mm !important;
        max-width: 48mm !important;
        max-height: 28mm !important;
        page-break-after: always !important;
        break-after: page !important;
        page-break-inside: avoid !important;
        break-inside: avoid !important;
        margin: 0 auto !important;
        padding: 1.2mm 1mm !important;
        box-sizing: border-box !important;
        display: flex !important;
        flex-direction: column !important;
        justify-content: space-between !important;
        align-items: center !important;
        text-align: center !important;
        border: none !important;
        background: #ffffff !important;
        color: #000000 !important;
        overflow: hidden !important;
    }

    /* 40x25mm Small Thermal Roll */
    .print-template-40x25 .label-card {
        width: 38mm !important;
        height: 23mm !important;
        max-width: 38mm !important;
        max-height: 23mm !important;
        page-break-after: always !important;
        break-after: page !important;
        page-break-inside: avoid !important;
        break-inside: avoid !important;
        margin: 0 auto !important;
        padding: 1mm 0.8mm !important;
        box-sizing: border-box !important;
        display: flex !important;
        flex-direction: column !important;
        justify-content: space-between !important;
        align-items: center !important;
        text-align: center !important;
        border: none !important;
        background: #ffffff !important;
        color: #000000 !important;
        overflow: hidden !important;
    }

    /* 35x25mm Mini Tag */
    .print-template-35x25 .label-card {
        width: 33mm !important;
        height: 23mm !important;
        max-width: 33mm !important;
        max-height: 23mm !important;
        page-break-after: always !important;
        break-after: page !important;
        page-break-inside: avoid !important;
        break-inside: avoid !important;
        margin: 0 auto !important;
        padding: 0.8mm 0.5mm !important;
        box-sizing: border-box !important;
        display: flex !important;
        flex-direction: column !important;
        justify-content: space-between !important;
        align-items: center !important;
        text-align: center !important;
        border: none !important;
        background: #ffffff !important;
        color: #000000 !important;
        overflow: hidden !important;
    }

    /* Mobile IMEI Box Sticker (70x45mm) */
    .print-template-imei_box .label-card {
        width: 68mm !important;
        height: 43mm !important;
        max-width: 68mm !important;
        max-height: 43mm !important;
        page-break-after: always !important;
        break-after: page !important;
        page-break-inside: avoid !important;
        break-inside: avoid !important;
        margin: 0 auto !important;
        padding: 1.5mm !important;
        box-sizing: border-box !important;
        border: 1pt solid #000000 !important;
        border-radius: 1.5mm !important;
        background: #ffffff !important;
        overflow: hidden !important;
    }

    /* A4 Sheet 24 Labels (3 cols x 8 rows) */
    .print-template-a4_24 {
        display: grid !important;
        grid-template-columns: repeat(3, 68mm) !important;
        gap: 2mm !important;
        width: 210mm !important;
        margin: 4mm auto !important;
        box-sizing: border-box !important;
    }
    .print-template-a4_24 .label-card {
        width: 68mm !important;
        height: 34mm !important;
        border: 0.5pt dashed #999999 !important;
        padding: 1.5mm !important;
        box-sizing: border-box !important;
        display: flex !important;
        flex-direction: column !important;
        justify-content: space-between !important;
        align-items: center !important;
        text-align: center !important;
        page-break-inside: avoid !important;
        overflow: hidden !important;
    }

    /* A4 Sheet 40 Labels (4 cols x 10 rows) */
    .print-template-a4_40 {
        display: grid !important;
        grid-template-columns: repeat(4, 50mm) !important;
        gap: 1.5mm !important;
        width: 210mm !important;
        margin: 3mm auto !important;
        box-sizing: border-box !important;
    }
    .print-template-a4_40 .label-card {
        width: 50mm !important;
        height: 27mm !important;
        border: 0.5pt dashed #999999 !important;
        padding: 1.2mm 0.8mm !important;
        box-sizing: border-box !important;
        display: flex !important;
        flex-direction: column !important;
        justify-content: space-between !important;
        align-items: center !important;
        text-align: center !important;
        page-break-inside: avoid !important;
        overflow: hidden !important;
    }

    /* Continuous 80mm/58mm POS Thermal Slip */
    .print-template-pos_slip .label-card {
        width: 72mm !important;
        margin: 0 auto 3mm auto !important;
        padding: 2mm 1mm !important;
        border-bottom: 1pt dashed #000000 !important;
        box-sizing: border-box !important;
        page-break-inside: avoid !important;
    }
}
</style>

<!-- MAIN USER INTERFACE (Screen Only) -->
<div class="no-print space-y-6">
    
    <!-- Top Header Banner & Stats -->
    <div class="bg-slate-900 border border-slate-800 rounded-2xl p-5 shadow-lg flex flex-col md:flex-row items-start md:items-center justify-between gap-4">
        <div class="flex items-center gap-3">
            <div class="w-12 h-12 rounded-2xl bg-emerald-500/10 border border-emerald-500/30 text-emerald-400 flex items-center justify-center font-black shadow-inner">
                <i data-lucide="scan-barcode" class="w-6 h-6"></i>
            </div>
            <div>
                <div class="flex items-center gap-2">
                    <h1 class="text-xl font-black text-white">
                        <?= $isUrdu ? 'بارکوڈ اسٹوڈیو و پرنٹنگ' : 'Barcode Studio & Printing' ?>
                    </h1>
                    <span class="text-[10px] uppercase font-bold tracking-wider px-2 py-0.5 rounded-full bg-emerald-500/10 text-emerald-400 border border-emerald-500/20">
                        HD Code-128 & QR
                    </span>
                </div>
                <p class="text-xs text-slate-400 mt-0.5">
                    <?= $isUrdu 
                        ? 'تھرمل رولز (50x30mm, 40x25mm)، عام A4 اسٹیکر شیٹس اور موبائل IMEI باکس اسٹیکرز کی تیز رفتار جنریشن اور پرنٹنگ'
                        : 'Generate and print crisp barcodes for 50x30mm / 40x25mm thermal rolls, A4 sheets, and Mobile IMEI box stickers' ?>
                </p>
            </div>
        </div>

        <!-- Header Action Quick Buttons -->
        <div class="flex items-center gap-2.5 flex-wrap self-stretch md:self-auto">
            <button onclick="triggerPrint()" class="px-4 py-2.5 bg-emerald-600 hover:bg-emerald-500 text-white rounded-xl text-xs font-bold transition-all shadow-lg shadow-emerald-950/40 flex items-center gap-2 cursor-pointer">
                <i data-lucide="printer" class="w-4 h-4"></i>
                <span><?= $isUrdu ? 'پرنٹ اسٹیکرز (Print)' : 'Print Stickers (پرنٹ)' ?></span>
            </button>
            <button onclick="document.getElementById('scannerVerifyBox').scrollIntoView({behavior:'smooth'}); document.getElementById('scannerTestInput').focus();" class="px-3.5 py-2.5 bg-slate-800 hover:bg-slate-700 text-cyan-400 hover:text-white rounded-xl text-xs font-bold transition-all border border-slate-700/80 flex items-center gap-1.5 cursor-pointer">
                <i data-lucide="shield-check" class="w-4 h-4"></i>
                <span><?= $isUrdu ? 'اسکینر ٹیسٹ' : 'Scanner Test' ?></span>
            </button>
        </div>
    </div>

    <!-- Mode Navigation Tabs -->
    <div class="flex items-center gap-2 p-1.5 bg-slate-900/90 border border-slate-800 rounded-2xl overflow-x-auto text-xs font-bold">
        <button onclick="switchMode('single')" id="tab-single" class="mode-tab px-4 py-2.5 rounded-xl transition-all flex items-center gap-2 bg-emerald-600 text-white shadow">
            <i data-lucide="tag" class="w-4 h-4"></i>
            <span><?= $isUrdu ? 'سنگل آئٹم بارکوڈ' : 'Single Product Label' ?></span>
        </button>
        <button onclick="switchMode('batch')" id="tab-batch" class="mode-tab px-4 py-2.5 rounded-xl transition-all flex items-center gap-2 text-slate-400 hover:text-white hover:bg-slate-800">
            <i data-lucide="layers" class="w-4 h-4"></i>
            <span><?= $isUrdu ? 'بلک / بیچ انوینٹری شیٹ' : 'Batch Inventory Sheet' ?></span>
            <span class="text-[10px] px-1.5 py-0.2 bg-slate-800 rounded-md text-emerald-400 font-mono"><?= count($products) ?></span>
        </button>
        <button onclick="switchMode('mobile_imei')" id="tab-mobile_imei" class="mode-tab px-4 py-2.5 rounded-xl transition-all flex items-center gap-2 text-slate-400 hover:text-white hover:bg-slate-800">
            <i data-lucide="smartphone" class="w-4 h-4"></i>
            <span><?= $isUrdu ? 'موبائل IMEI باکس اسٹیکرز' : 'Mobile IMEI Box Stickers' ?></span>
            <span class="text-[10px] px-1.5 py-0.2 bg-slate-800 rounded-md text-cyan-400 font-mono"><?= count($mobiles) ?></span>
        </button>
        <button onclick="switchMode('custom')" id="tab-custom" class="mode-tab px-4 py-2.5 rounded-xl transition-all flex items-center gap-2 text-slate-400 hover:text-white hover:bg-slate-800">
            <i data-lucide="sparkles" class="w-4 h-4"></i>
            <span><?= $isUrdu ? 'کسٹم لیبل و کیو آر کوڈ (QR)' : 'Custom Barcode / QR Code' ?></span>
        </button>
    </div>

    <!-- Main Workspace (Left Form / Controls + Right Live Preview) -->
    <div class="grid grid-cols-1 lg:grid-cols-12 gap-6 items-start">
        
        <!-- LEFT PANEL: Configuration & Settings (5 Cols) -->
        <div class="lg:col-span-5 space-y-5">
            
            <!-- 1. Mode Controls Container -->
            <div class="bg-slate-900 border border-slate-800 rounded-2xl p-5 shadow-lg space-y-4">
                
                <!-- MODE 1: SINGLE PRODUCT -->
                <div id="mode-single-section" class="mode-content space-y-3.5">
                    <div class="flex items-center justify-between pb-2 border-b border-slate-800">
                        <span class="text-xs font-bold text-emerald-400 flex items-center gap-1.5">
                            <i data-lucide="box" class="w-4 h-4"></i>
                            <?= $isUrdu ? 'پروڈکٹ کا انتخاب کریں' : 'Select Product from Inventory' ?>
                        </span>
                        <span class="text-[11px] text-slate-400 font-mono"><?= count($products) ?> <?= $isUrdu ? 'دستیاب' : 'Items' ?></span>
                    </div>

                    <div>
                        <label class="text-xs text-slate-400 block mb-1"><?= $isUrdu ? 'دکان میں موجود پروڈکٹ منتخب کریں' : 'Choose Inventory Product' ?></label>
                        <select id="singleProductSelect" onchange="onSingleProductSelected()" class="w-full bg-slate-950 border border-slate-700 text-white px-3.5 py-2.5 rounded-xl text-xs focus:border-emerald-500 focus:outline-none">
                            <option value="">-- <?= $isUrdu ? 'پروڈکٹ منتخب کریں' : 'Select a Product' ?> --</option>
                            <?php foreach ($products as $p): 
                                $code = $p['sku'] ?: ($p['imei_or_serial'] ?: ('SKU-' . str_pad($p['id'], 5, '0', STR_PAD_LEFT)));
                            ?>
                                <option value="<?= htmlspecialchars(json_encode([
                                    'id' => $p['id'],
                                    'name' => $p['name'],
                                    'category' => $p['category'],
                                    'brand' => $p['brand_or_model'] ?: $p['category'],
                                    'price' => floatval($p['sale_price']),
                                    'stock' => intval($p['stock_quantity']),
                                    'code' => $code
                                ])) ?>">
                                    <?= htmlspecialchars($p['name']) ?> - Rs. <?= number_format($p['sale_price']) ?> (<?= $isUrdu ? 'اسٹاک' : 'Stock' ?>: <?= $p['stock_quantity'] ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div>
                        <label class="text-xs text-slate-400 block mb-1"><?= $isUrdu ? 'لیبل پر ظاہر ہونے والا نام' : 'Product Title on Label' ?></label>
                        <input type="text" id="singleName" value="Samsung 25W Fast Charger" oninput="renderLivePreview()" class="w-full bg-slate-950 border border-slate-700 text-white px-3 py-2 rounded-xl text-xs focus:border-emerald-500 focus:outline-none">
                    </div>

                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="text-xs text-slate-400 block mb-1"><?= $isUrdu ? 'سیل قیمت (Rs)' : 'Sale Price (Rs)' ?></label>
                            <input type="number" id="singlePrice" value="1800" oninput="renderLivePreview()" class="w-full bg-slate-950 border border-slate-700 text-white px-3 py-2 rounded-xl text-xs font-mono font-bold focus:border-emerald-500 focus:outline-none">
                        </div>
                        <div>
                            <label class="text-xs text-slate-400 block mb-1"><?= $isUrdu ? 'بارکوڈ کوڈ / SKU' : 'Barcode / SKU Code' ?></label>
                            <div class="flex items-center gap-1.5">
                                <input type="text" id="singleCode" value="89012345678" oninput="renderLivePreview()" class="w-full bg-slate-950 border border-slate-700 text-white px-3 py-2 rounded-xl text-xs font-mono font-bold focus:border-emerald-500 focus:outline-none">
                                <button type="button" onclick="generateRandomBarcode('singleCode')" class="p-2 bg-slate-800 hover:bg-slate-700 text-emerald-400 rounded-xl" title="نیا رینڈم بارکوڈ بنائیں">
                                    <i data-lucide="wand-2" class="w-3.5 h-3.5"></i>
                                </button>
                            </div>
                        </div>
                    </div>

                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="text-xs text-slate-400 block mb-1"><?= $isUrdu ? 'برانڈ یا ماڈل ٹیگ' : 'Brand / Model Tag' ?></label>
                            <input type="text" id="singleBrand" value="Samsung Original" oninput="renderLivePreview()" class="w-full bg-slate-950 border border-slate-700 text-white px-3 py-2 rounded-xl text-xs focus:border-emerald-500 focus:outline-none">
                        </div>
                        <div>
                            <label class="text-xs text-slate-400 block mb-1"><?= $isUrdu ? 'وارنٹی / اضافی ٹیگ' : 'Warranty / Batch Tag' ?></label>
                            <input type="text" id="singleBatch" value="<?= $isUrdu ? 'وارنٹی: 6 ماہ' : 'Warranty: 6 Months' ?>" oninput="renderLivePreview()" class="w-full bg-slate-950 border border-slate-700 text-white px-3 py-2 rounded-xl text-xs focus:border-emerald-500 focus:outline-none">
                        </div>
                    </div>

                    <div>
                        <label class="text-xs text-slate-400 block mb-1"><?= $isUrdu ? 'پرنٹ اسٹیکرز کی تعداد (Copies)' : 'Print Sticker Copies' ?></label>
                        <div class="flex items-center gap-2">
                            <input type="number" id="singleCopies" value="12" min="1" max="200" oninput="renderLivePreview()" class="flex-1 bg-slate-950 border border-slate-700 text-white px-3 py-2 rounded-xl text-xs font-mono font-bold focus:border-emerald-500 focus:outline-none">
                            <button type="button" onclick="setCopies(1)" class="px-2.5 py-2 rounded-lg bg-slate-800 hover:bg-slate-700 text-slate-300 text-xs font-bold">1</button>
                            <button type="button" onclick="setCopies(6)" class="px-2.5 py-2 rounded-lg bg-slate-800 hover:bg-slate-700 text-slate-300 text-xs font-bold">6</button>
                            <button type="button" onclick="setCopies(12)" class="px-2.5 py-2 rounded-lg bg-slate-800 hover:bg-slate-700 text-slate-300 text-xs font-bold">12</button>
                            <button type="button" onclick="setCopies(24)" class="px-2.5 py-2 rounded-lg bg-slate-800 hover:bg-slate-700 text-slate-300 text-xs font-bold">24</button>
                            <button type="button" onclick="setCopies(40)" class="px-2.5 py-2 rounded-lg bg-slate-800 hover:bg-slate-700 text-slate-300 text-xs font-bold">40</button>
                        </div>
                    </div>
                </div>

                <!-- MODE 2: BATCH INVENTORY -->
                <div id="mode-batch-section" class="mode-content hidden space-y-3.5">
                    <div class="flex items-center justify-between pb-2 border-b border-slate-800">
                        <span class="text-xs font-bold text-cyan-400 flex items-center gap-1.5">
                            <i data-lucide="layers" class="w-4 h-4"></i>
                            <?= $isUrdu ? 'بلک پراڈکٹس سلیکشن' : 'Bulk Product Selection' ?>
                        </span>
                        <div class="flex items-center gap-2">
                            <button type="button" onclick="selectAllBatch(true)" class="text-[11px] text-emerald-400 hover:underline"><?= $isUrdu ? 'سب منتخب' : 'Select All' ?></button>
                            <span class="text-slate-600">|</span>
                            <button type="button" onclick="selectAllBatch(false)" class="text-[11px] text-slate-400 hover:underline"><?= $isUrdu ? 'غیر منتخب' : 'Deselect' ?></button>
                        </div>
                    </div>

                    <div class="flex items-center gap-2">
                        <input type="text" id="batchSearchInput" onkeyup="filterBatchItems()" placeholder="<?= $isUrdu ? 'لسٹ میں سے تلاش کریں...' : 'Search items in list...' ?>" class="w-full bg-slate-950 border border-slate-700 text-white px-3 py-1.5 rounded-xl text-xs focus:border-cyan-500 focus:outline-none">
                        <button type="button" onclick="fillCopiesWithStock()" class="shrink-0 px-2.5 py-1.5 bg-cyan-500/10 hover:bg-cyan-500/20 text-cyan-400 border border-cyan-500/30 rounded-xl text-xs font-bold">
                            <?= $isUrdu ? 'اسٹاک تعداد بھریں' : 'Fill from Stock' ?>
                        </button>
                    </div>

                    <div class="max-h-[300px] overflow-y-auto border border-slate-800 rounded-xl divide-y divide-slate-800/60 pr-1" id="batchItemsList">
                        <?php foreach ($products as $idx => $p): 
                            $code = $p['sku'] ?: ($p['imei_or_serial'] ?: ('SKU-' . str_pad($p['id'], 5, '0', STR_PAD_LEFT)));
                            $initChecked = $idx < 4 ? 'checked' : '';
                        ?>
                            <div class="batch-row p-2.5 hover:bg-slate-800/40 flex items-center justify-between gap-3 text-xs" data-name="<?= strtolower(htmlspecialchars($p['name'])) ?>">
                                <label class="flex items-center gap-2.5 cursor-pointer flex-1 min-w-0">
                                    <input type="checkbox" class="batch-chk w-4 h-4 rounded text-cyan-600 focus:ring-cyan-500 bg-slate-900 border-slate-700 cursor-pointer" <?= $initChecked ?> onchange="renderLivePreview()">
                                    <div class="truncate">
                                        <div class="font-bold text-white truncate"><?= htmlspecialchars($p['name']) ?></div>
                                        <div class="text-[10px] text-slate-400 flex items-center gap-2">
                                            <span>Rs. <?= number_format($p['sale_price']) ?></span>
                                            <span>•</span>
                                            <span class="font-mono"><?= htmlspecialchars($code) ?></span>
                                            <span>•</span>
                                            <span class="text-emerald-400"><?= $isUrdu ? 'اسٹاک' : 'Stock' ?>: <?= $p['stock_quantity'] ?></span>
                                        </div>
                                    </div>
                                </label>
                                <div class="flex items-center gap-1.5 shrink-0">
                                    <span class="text-[10px] text-slate-500"><?= $isUrdu ? 'کاپیاں' : 'Qty' ?>:</span>
                                    <input type="number" class="batch-qty w-14 bg-slate-950 border border-slate-700 text-white px-2 py-1 rounded text-center text-xs font-bold font-mono" value="<?= $idx < 4 ? '4' : '2' ?>" min="1" max="100" data-stock="<?= $p['stock_quantity'] ?>" data-product="<?= htmlspecialchars(json_encode([
                                        'name' => $p['name'],
                                        'price' => floatval($p['sale_price']),
                                        'code' => $code,
                                        'brand' => $p['brand_or_model'] ?: $p['category']
                                    ])) ?>" onchange="renderLivePreview()">
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>

                <!-- MODE 3: MOBILE PHONES IMEI STICKER -->
                <div id="mode-mobile_imei-section" class="mode-content hidden space-y-3.5">
                    <div class="flex items-center justify-between pb-2 border-b border-slate-800">
                        <span class="text-xs font-bold text-indigo-400 flex items-center gap-1.5">
                            <i data-lucide="smartphone" class="w-4 h-4"></i>
                            <?= $isUrdu ? 'موبائل باکس کیلئے ڈوئل IMEI اسٹیکر' : 'Mobile Phone Dual IMEI Box Label' ?>
                        </span>
                        <span class="text-[11px] text-slate-400 font-mono"><?= count($mobiles) ?> <?= $isUrdu ? 'فونز' : 'Phones' ?></span>
                    </div>

                    <div>
                        <label class="text-xs text-slate-400 block mb-1"><?= $isUrdu ? 'اسٹاک میں موجود موبائل منتخب کریں' : 'Select Phone from Inventory' ?></label>
                        <select id="mobileSelect" onchange="onMobileSelected()" class="w-full bg-slate-950 border border-slate-700 text-white px-3.5 py-2 rounded-xl text-xs focus:border-indigo-500 focus:outline-none">
                            <option value="">-- <?= $isUrdu ? 'منتخب کریں' : 'Select Phone' ?> --</option>
                            <?php foreach ($mobiles as $m): ?>
                                <option value="<?= htmlspecialchars(json_encode([
                                    'name' => $m['mobile_name'],
                                    'brand' => $m['brand'],
                                    'imei1' => $m['imei_1'],
                                    'imei2' => $m['imei_2'],
                                    'ram_rom' => $m['ram_rom'],
                                    'color' => $m['color'],
                                    'condition' => $m['condition'],
                                    'price' => floatval($m['expected_sale_price'] ?: $m['purchase_price'])
                                ])) ?>">
                                    <?= htmlspecialchars($m['mobile_name']) ?> (<?= htmlspecialchars($m['color'] ?: 'Color') ?>) - IMEI: <?= substr($m['imei_1'] ?: '', -6) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div>
                        <label class="text-xs text-slate-400 block mb-1"><?= $isUrdu ? 'موبائل کا ماڈل و تفصیل' : 'Phone Model & Specs' ?></label>
                        <input type="text" id="mobileModel" value="Samsung Galaxy A15 (8GB / 128GB)" oninput="renderLivePreview()" class="w-full bg-slate-950 border border-slate-700 text-white px-3 py-2 rounded-xl text-xs focus:border-indigo-500 focus:outline-none">
                    </div>

                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="text-xs text-slate-400 block mb-1"><?= $isUrdu ? 'رنگ (Color)' : 'Color' ?></label>
                            <input type="text" id="mobileColor" value="Blue Black" oninput="renderLivePreview()" class="w-full bg-slate-950 border border-slate-700 text-white px-3 py-2 rounded-xl text-xs focus:border-indigo-500 focus:outline-none">
                        </div>
                        <div>
                            <label class="text-xs text-slate-400 block mb-1"><?= $isUrdu ? 'قیمت (Rs)' : 'Price (Rs)' ?></label>
                            <input type="number" id="mobilePrice" value="52000" oninput="renderLivePreview()" class="w-full bg-slate-950 border border-slate-700 text-white px-3 py-2 rounded-xl text-xs font-mono font-bold focus:border-indigo-500 focus:outline-none">
                        </div>
                    </div>

                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="text-xs text-slate-400 block mb-1">IMEI 1 (Primary)</label>
                            <input type="text" id="mobileImei1" value="356789123456781" oninput="renderLivePreview()" class="w-full bg-slate-950 border border-slate-700 text-white px-3 py-2 rounded-xl text-xs font-mono font-bold focus:border-indigo-500 focus:outline-none">
                        </div>
                        <div>
                            <label class="text-xs text-slate-400 block mb-1">IMEI 2 (<?= $isUrdu ? 'اختیاری' : 'Optional' ?>)</label>
                            <input type="text" id="mobileImei2" value="356789123456782" oninput="renderLivePreview()" class="w-full bg-slate-950 border border-slate-700 text-white px-3 py-2 rounded-xl text-xs font-mono font-bold focus:border-indigo-500 focus:outline-none">
                        </div>
                    </div>

                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="text-xs text-slate-400 block mb-1"><?= $isUrdu ? 'PTA اسٹیٹس' : 'PTA Approval Status' ?></label>
                            <select id="mobilePta" onchange="renderLivePreview()" class="w-full bg-slate-950 border border-slate-700 text-white px-3 py-2 rounded-xl text-xs">
                                <option value="PTA APPROVED">PTA APPROVED (تصدیق شدہ)</option>
                                <option value="NON-PTA">NON-PTA (نان پی ٹی اے)</option>
                                <option value="1 YEAR OFFICIAL WARRANTY">1 YEAR WARRANTY</option>
                            </select>
                        </div>
                        <div>
                            <label class="text-xs text-slate-400 block mb-1"><?= $isUrdu ? 'پرنٹ اسٹیکرز تعداد' : 'Print Quantity' ?></label>
                            <input type="number" id="mobileCopies" value="2" min="1" max="20" oninput="renderLivePreview()" class="w-full bg-slate-950 border border-slate-700 text-white px-3 py-2 rounded-xl text-xs font-bold font-mono">
                        </div>
                    </div>
                </div>

                <!-- MODE 4: CUSTOM & QR CODE DESIGNER -->
                <div id="mode-custom-section" class="mode-content hidden space-y-3.5">
                    <div class="flex items-center justify-between pb-2 border-b border-slate-800">
                        <span class="text-xs font-bold text-amber-400 flex items-center gap-1.5">
                            <i data-lucide="sparkles" class="w-4 h-4"></i>
                            <?= $isUrdu ? 'کسٹم ڈیزائنر و کیو آر کوڈ' : 'Custom Designer & QR Code' ?>
                        </span>
                        <span class="text-[11px] text-slate-400"><?= $isUrdu ? 'آزادانہ لیبل فارمیٹ' : 'Freeform Tag' ?></span>
                    </div>

                    <div>
                        <label class="text-xs text-slate-400 block mb-1"><?= $isUrdu ? 'ٹائٹل / آفر کا نام' : 'Label Header / Promo Title' ?></label>
                        <input type="text" id="customTitle" value="Special Discount Deal" oninput="renderLivePreview()" class="w-full bg-slate-950 border border-slate-700 text-white px-3 py-2 rounded-xl text-xs focus:border-amber-500 focus:outline-none">
                    </div>

                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="text-xs text-slate-400 block mb-1"><?= $isUrdu ? 'بارکوڈ / ٹیکسٹ / لنک' : 'Barcode Value / URL' ?></label>
                            <input type="text" id="customCode" value="OFFER-9901" oninput="renderLivePreview()" class="w-full bg-slate-950 border border-slate-700 text-white px-3 py-2 rounded-xl text-xs font-mono font-bold focus:border-amber-500 focus:outline-none">
                        </div>
                        <div>
                            <label class="text-xs text-slate-400 block mb-1"><?= $isUrdu ? 'قیمت (Rs) یا رعایت' : 'Price (Rs) / Discount' ?></label>
                            <input type="text" id="customPrice" value="Rs. 990 /-" oninput="renderLivePreview()" class="w-full bg-slate-950 border border-slate-700 text-white px-3 py-2 rounded-xl text-xs font-mono font-bold focus:border-amber-500 focus:outline-none">
                        </div>
                    </div>

                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="text-xs text-slate-400 block mb-1"><?= $isUrdu ? 'بارکوڈ فارمیٹ' : 'Symbology Format' ?></label>
                            <select id="customSymbology" onchange="renderLivePreview()" class="w-full bg-slate-950 border border-slate-700 text-white px-3 py-2 rounded-xl text-xs">
                                <option value="CODE128">Code 128 (Standard POS Barcode)</option>
                                <option value="QR">QR Code (2D Matrix)</option>
                                <option value="EAN13">EAN-13 (Retail 13-Digits)</option>
                                <option value="CODE39">Code 39</option>
                            </select>
                        </div>
                        <div>
                            <label class="text-xs text-slate-400 block mb-1"><?= $isUrdu ? 'پرنٹ کاپیاں' : 'Copies' ?></label>
                            <input type="number" id="customCopies" value="8" min="1" max="100" oninput="renderLivePreview()" class="w-full bg-slate-950 border border-slate-700 text-white px-3 py-2 rounded-xl text-xs font-bold font-mono">
                        </div>
                    </div>
                </div>

                <div class="pt-2">
                    <button type="button" onclick="renderLivePreview()" class="w-full py-2.5 bg-slate-800 hover:bg-slate-700 text-white font-bold text-xs rounded-xl border border-slate-700 flex items-center justify-center gap-2 cursor-pointer transition-all">
                        <i data-lucide="refresh-cw" class="w-4 h-4 text-emerald-400"></i>
                        <span><?= $isUrdu ? 'پیش منظر ریفریش کریں (Update Preview)' : 'Refresh Live Preview' ?></span>
                    </button>
                </div>
            </div>

            <!-- 2. Paper Template & Display Toggles -->
            <div class="bg-slate-900 border border-slate-800 rounded-2xl p-5 shadow-lg space-y-4">
                <h3 class="text-xs font-bold text-slate-200 flex items-center gap-2 pb-2 border-b border-slate-800">
                    <i data-lucide="sliders" class="w-4 h-4 text-emerald-400"></i>
                    <span><?= $isUrdu ? 'پرنٹ پیپر ٹیمپلیٹ و سائز' : 'Printer & Label Paper Template' ?></span>
                </h3>

                <div>
                    <label class="text-xs text-slate-400 block mb-1"><?= $isUrdu ? 'اسٹیکر پیپر سائز (Paper Template)' : 'Sticker Paper Size' ?></label>
                    <select id="paperTemplateSelect" onchange="onPaperTemplateChanged()" class="w-full bg-slate-950 border border-slate-700 text-white px-3.5 py-2.5 rounded-xl text-xs font-bold focus:border-emerald-500 focus:outline-none">
                        <option value="50x30">50mm × 30mm Thermal Roll (Standard Mobile & Accessory)</option>
                        <option value="40x25">40mm × 25mm Thermal Roll (Small Cable / Handsfree)</option>
                        <option value="35x25">35mm × 25mm Mini Tag (Jewel / Small Gadget)</option>
                        <option value="imei_box">70mm × 45mm Mobile Phone Box Sticker (Dual IMEI)</option>
                        <option value="a4_24">A4 Sticker Sheet (24 Labels: 3 cols × 8 rows)</option>
                        <option value="a4_40">A4 Sticker Sheet (40 Labels: 4 cols × 10 rows)</option>
                        <option value="pos_slip">80mm / 58mm POS Continuous Thermal Receipt</option>
                    </select>
                </div>

                <!-- Content Visibility Toggles -->
                <div class="grid grid-cols-2 gap-2 text-xs pt-1">
                    <label class="flex items-center gap-2 p-2 bg-slate-950 rounded-xl border border-slate-800 cursor-pointer">
                        <input type="checkbox" id="toggleStoreHeader" checked onchange="renderLivePreview()" class="rounded text-emerald-500 bg-slate-900 border-slate-700">
                        <span class="text-slate-300"><?= $isUrdu ? 'دکان کا نام' : 'Store Name' ?></span>
                    </label>
                    <label class="flex items-center gap-2 p-2 bg-slate-950 rounded-xl border border-slate-800 cursor-pointer">
                        <input type="checkbox" id="togglePrice" checked onchange="renderLivePreview()" class="rounded text-emerald-500 bg-slate-900 border-slate-700">
                        <span class="text-slate-300"><?= $isUrdu ? 'سیل قیمت (Rs)' : 'Sale Price' ?></span>
                    </label>
                    <label class="flex items-center gap-2 p-2 bg-slate-950 rounded-xl border border-slate-800 cursor-pointer">
                        <input type="checkbox" id="toggleCodeText" checked onchange="renderLivePreview()" class="rounded text-emerald-500 bg-slate-900 border-slate-700">
                        <span class="text-slate-300"><?= $isUrdu ? 'بارکوڈ ٹیکسٹ' : 'Barcode Text' ?></span>
                    </label>
                    <label class="flex items-center gap-2 p-2 bg-slate-950 rounded-xl border border-slate-800 cursor-pointer">
                        <input type="checkbox" id="toggleBrand" checked onchange="renderLivePreview()" class="rounded text-emerald-500 bg-slate-900 border-slate-700">
                        <span class="text-slate-300"><?= $isUrdu ? 'برانڈ / ٹیگ' : 'Brand Tag' ?></span>
                    </label>
                </div>

                <!-- Barcode Height Slider -->
                <div>
                    <div class="flex items-center justify-between text-xs text-slate-400 mb-1">
                        <span><?= $isUrdu ? 'بارکوڈ کی اونچائی (Height)' : 'Barcode Height' ?>:</span>
                        <span id="barHeightVal" class="font-mono text-emerald-400 font-bold">34px</span>
                    </div>
                    <input type="range" id="barHeightSlider" min="20" max="55" value="34" oninput="document.getElementById('barHeightVal').innerText = this.value + 'px'; renderLivePreview();" class="w-full accent-emerald-500 bg-slate-950 cursor-pointer">
                </div>
            </div>

            <!-- 3. Physical Barcode Scanner Verifier Test Box -->
            <div class="bg-slate-900 border border-slate-800 rounded-2xl p-5 shadow-lg space-y-3" id="scannerVerifyBox">
                <div class="flex items-center justify-between pb-2 border-b border-slate-800">
                    <span class="text-xs font-bold text-white flex items-center gap-2">
                        <i data-lucide="shield-check" class="w-4 h-4 text-cyan-400"></i>
                        <span><?= $isUrdu ? 'لائیو بارکوڈ اسکینر ٹیسٹنگ ٹول' : 'Barcode Scanner Verifier Tool' ?></span>
                    </span>
                    <span class="text-[10px] px-2 py-0.5 rounded-full bg-cyan-500/10 text-cyan-400 font-bold">
                        100% Scannable
                    </span>
                </div>
                <p class="text-[11px] text-slate-400 leading-relaxed">
                    <?= $isUrdu 
                        ? 'پرنٹ نکالنے کے بعد یا اسکرین پر موجود بارکوڈ کو اپنے فزیکل بارکوڈ اسکینر گن سے اسکین کر کے فوری چیک کریں:'
                        : 'Scan printed labels or on-screen barcodes using your USB/Wireless scanner gun to verify recognition:' ?>
                </p>

                <form onsubmit="handleScannerTest(event)" class="relative">
                    <i data-lucide="scan" class="w-4 h-4 text-cyan-400 absolute <?= $isUrdu ? 'right-3.5' : 'left-3.5' ?> top-3"></i>
                    <input type="text" id="scannerTestInput" placeholder="<?= $isUrdu ? 'یہاں بارکوڈ گن سے اسکین کریں یا نمبر لکھیں...' : 'Scan here with scanner gun or type code...' ?>" class="w-full <?= $isUrdu ? 'pr-10 pl-4' : 'pl-10 pr-4' ?> py-2.5 bg-slate-950 border border-cyan-500/40 text-white rounded-xl text-xs font-mono focus:border-cyan-400 focus:outline-none shadow-inner">
                </form>

                <div id="scannerResultArea" class="hidden p-3 rounded-xl border text-xs"></div>
            </div>
        </div>

        <!-- RIGHT PANEL: Live Interactive Preview Sheet (7 Cols) -->
        <div class="lg:col-span-7 bg-slate-900 border border-slate-800 rounded-2xl p-5 shadow-lg space-y-4">
            
            <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-3 pb-3 border-b border-slate-800">
                <div class="flex items-center gap-2">
                    <i data-lucide="eye" class="w-5 h-5 text-emerald-400"></i>
                    <h3 class="font-bold text-white text-sm"><?= $isUrdu ? 'لیبل پرنٹ پیش منظر (Live Preview)' : 'Live Print Preview Sheet' ?></h3>
                    <span id="previewCountBadge" class="text-[11px] px-2 py-0.5 rounded-full bg-slate-800 text-slate-300 font-mono font-bold">
                        12 Labels
                    </span>
                </div>

                <div class="flex items-center gap-2">
                    <button type="button" onclick="triggerPrint()" class="px-3.5 py-1.5 bg-emerald-600 hover:bg-emerald-500 text-white rounded-xl text-xs font-bold transition-all shadow-md flex items-center gap-1.5 cursor-pointer">
                        <i data-lucide="printer" class="w-4 h-4"></i>
                        <span><?= $isUrdu ? 'پرنٹ کریں' : 'Print Now' ?></span>
                    </button>
                </div>
            </div>

            <!-- Paper Background Visual Box -->
            <div class="bg-slate-950 border border-slate-800/80 rounded-xl p-4 min-h-[500px] max-h-[640px] overflow-y-auto">
                <div id="screenPreviewGrid" class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-3 gap-3.5">
                    <!-- Cards populated dynamically -->
                </div>
            </div>

            <!-- Helper Instructions -->
            <div class="p-3 bg-slate-950/60 border border-slate-800/80 rounded-xl text-[11px] text-slate-400 flex items-center justify-between gap-2">
                <div class="flex items-center gap-2">
                    <i data-lucide="info" class="w-4 h-4 text-emerald-400 shrink-0"></i>
                    <span><?= $isUrdu ? 'پرنٹ ونڈو میں "Margins" کو <strong>None</strong> پر رکھیں تاکہ اسٹیکرز پیپر پر نہ کٹیں۔' : 'In browser print dialog, set Margins to <strong>None</strong> for edge-to-edge printing.' ?></span>
                </div>
                <span class="text-slate-500 font-mono hidden sm:inline">203 / 300 DPI</span>
            </div>
        </div>
    </div>
</div>

<!-- PURE PRINT-ONLY ROOT (Hidden on screen, 100% visible on print) -->
<div id="printSheetRoot" class="hidden print-only print-only-container"></div>

<!-- JAVASCRIPT LOGIC ENGINE -->
<script>
const SHOP_NAME = <?= json_encode($shopName) ?>;
let activeMode = 'single';
let currentTemplate = '50x30';

// Initialize on Load
document.addEventListener('DOMContentLoaded', () => {
    lucide.createIcons();
    renderLivePreview();
});

// Generate Random Barcode Number
function generateRandomBarcode(inputId) {
    const randomCode = '890' + Math.floor(10000000 + Math.random() * 90000000);
    const input = document.getElementById(inputId);
    if (input) {
        input.value = randomCode;
        renderLivePreview();
    }
}

// Switch Main Mode Tabs
function switchMode(mode) {
    activeMode = mode;
    document.querySelectorAll('.mode-tab').forEach(btn => {
        btn.classList.remove('bg-emerald-600', 'text-white', 'shadow');
        btn.classList.add('text-slate-400');
    });
    document.getElementById('tab-' + mode).classList.add('bg-emerald-600', 'text-white', 'shadow');
    document.getElementById('tab-' + mode).classList.remove('text-slate-400');

    document.querySelectorAll('.mode-content').forEach(sec => sec.classList.add('hidden'));
    document.getElementById('mode-' + mode + '-section').classList.remove('hidden');

    // Auto switch paper template for mobile IMEI
    if (mode === 'mobile_imei') {
        document.getElementById('paperTemplateSelect').value = 'imei_box';
        currentTemplate = 'imei_box';
    } else if (currentTemplate === 'imei_box') {
        document.getElementById('paperTemplateSelect').value = '50x30';
        currentTemplate = '50x30';
    }

    renderLivePreview();
}

function onPaperTemplateChanged() {
    currentTemplate = document.getElementById('paperTemplateSelect').value;
    renderLivePreview();
}

function setCopies(num) {
    document.getElementById('singleCopies').value = num;
    renderLivePreview();
}

// Single Product Selection
function onSingleProductSelected() {
    const val = document.getElementById('singleProductSelect').value;
    if (!val) return;
    const p = JSON.parse(val);
    document.getElementById('singleName').value = p.name;
    document.getElementById('singlePrice').value = p.price;
    document.getElementById('singleCode').value = p.code;
    document.getElementById('singleBrand').value = p.brand;
    renderLivePreview();
}

// Mobile Selection
function onMobileSelected() {
    const val = document.getElementById('mobileSelect').value;
    if (!val) return;
    const m = JSON.parse(val);
    document.getElementById('mobileModel').value = m.name + (m.ram_rom ? ' (' + m.ram_rom + ')' : '');
    document.getElementById('mobileColor').value = m.color || 'Default';
    document.getElementById('mobilePrice').value = m.price;
    document.getElementById('mobileImei1').value = m.imei1 || '356789123456781';
    document.getElementById('mobileImei2').value = m.imei2 || '';
    renderLivePreview();
}

function selectAllBatch(check) {
    document.querySelectorAll('.batch-chk').forEach(chk => chk.checked = check);
    renderLivePreview();
}

function filterBatchItems() {
    const q = document.getElementById('batchSearchInput').value.toLowerCase().trim();
    document.querySelectorAll('.batch-row').forEach(row => {
        const name = row.getAttribute('data-name') || '';
        if (name.includes(q)) {
            row.style.display = 'flex';
        } else {
            row.style.display = 'none';
        }
    });
}

function fillCopiesWithStock() {
    document.querySelectorAll('.batch-qty').forEach(input => {
        const s = parseInt(input.getAttribute('data-stock')) || 1;
        input.value = Math.max(1, Math.min(s, 50));
    });
    renderLivePreview();
}

// Build Items Array based on Active Mode
function getActiveItems() {
    let items = [];

    if (activeMode === 'single') {
        const name = document.getElementById('singleName').value || 'Product Title';
        const price = parseFloat(document.getElementById('singlePrice').value) || 0;
        const code = document.getElementById('singleCode').value || '10001';
        const brand = document.getElementById('singleBrand').value || '';
        const batch = document.getElementById('singleBatch').value || '';
        const copies = parseInt(document.getElementById('singleCopies').value) || 6;

        for (let i = 0; i < copies; i++) {
            items.push({
                type: 'standard',
                title: name,
                price: price,
                code: code,
                brand: brand,
                batch: batch,
                symbology: 'CODE128'
            });
        }
    } else if (activeMode === 'batch') {
        document.querySelectorAll('.batch-chk:checked').forEach(chk => {
            const row = chk.closest('.batch-row');
            const qtyInput = row.querySelector('.batch-qty');
            const qty = parseInt(qtyInput.value) || 1;
            const p = JSON.parse(qtyInput.getAttribute('data-product'));

            for (let i = 0; i < qty; i++) {
                items.push({
                    type: 'standard',
                    title: p.name,
                    price: p.price,
                    code: p.code,
                    brand: p.brand,
                    symbology: 'CODE128'
                });
            }
        });
    } else if (activeMode === 'mobile_imei') {
        const model = document.getElementById('mobileModel').value || 'Mobile Phone';
        const color = document.getElementById('mobileColor').value || '';
        const price = parseFloat(document.getElementById('mobilePrice').value) || 0;
        const imei1 = document.getElementById('mobileImei1').value || '356789123456781';
        const imei2 = document.getElementById('mobileImei2').value || '';
        const pta = document.getElementById('mobilePta').value || 'PTA APPROVED';
        const copies = parseInt(document.getElementById('mobileCopies').value) || 1;

        for (let i = 0; i < copies; i++) {
            items.push({
                type: 'mobile_imei',
                title: model,
                color: color,
                price: price,
                imei1: imei1,
                imei2: imei2,
                pta: pta,
                brand: SHOP_NAME
            });
        }
    } else if (activeMode === 'custom') {
        const title = document.getElementById('customTitle').value || 'Special Offer';
        const code = document.getElementById('customCode').value || '889012';
        const price = document.getElementById('customPrice').value || '';
        const symbology = document.getElementById('customSymbology').value || 'CODE128';
        const copies = parseInt(document.getElementById('customCopies').value) || 6;

        for (let i = 0; i < copies; i++) {
            items.push({
                type: 'custom',
                title: title,
                code: code,
                priceText: price,
                symbology: symbology,
                brand: SHOP_NAME
            });
        }
    }

    return items;
}

// Render Live Preview on Screen
function renderLivePreview() {
    const items = getActiveItems();
    const grid = document.getElementById('screenPreviewGrid');
    const countBadge = document.getElementById('previewCountBadge');
    grid.innerHTML = '';
    countBadge.innerText = items.length + ' <?= $isUrdu ? "لیبلز" : "Labels" ?>';

    if (items.length === 0) {
        grid.innerHTML = `<div class="col-span-full py-16 text-center text-slate-500 space-y-2">
            <i data-lucide="tag" class="w-8 h-8 mx-auto text-slate-600"></i>
            <p class="text-xs"><?= $isUrdu ? 'کوئی لیبل منتخب نہیں کیا گیا ہے۔' : 'No labels selected for preview.' ?></p>
        </div>`;
        lucide.createIcons();
        return;
    }

    const showStore = document.getElementById('toggleStoreHeader').checked;
    const showPrice = document.getElementById('togglePrice').checked;
    const showCode = document.getElementById('toggleCodeText').checked;
    const showBrand = document.getElementById('toggleBrand').checked;
    const barH = parseInt(document.getElementById('barHeightSlider').value) || 34;

    items.forEach((item, idx) => {
        const card = document.createElement('div');
        card.className = 'bg-white text-black p-3 rounded-xl shadow-md border border-slate-300 flex flex-col justify-between items-center text-center select-none relative';

        if (item.type === 'mobile_imei') {
            card.innerHTML = `
                <div class="w-full flex items-center justify-between border-b border-black pb-1 mb-1">
                    <span class="text-[9px] font-black uppercase text-slate-800">${SHOP_NAME}</span>
                    <span class="text-[8px] font-bold px-1.5 py-0.2 bg-black text-white rounded">${item.pta}</span>
                </div>
                <div class="text-[11px] font-black text-black truncate w-full">${item.title}</div>
                <div class="text-[9px] text-slate-700 w-full mb-1">Color: <strong>${item.color || 'Standard'}</strong></div>
                
                <div class="w-full my-0.5">
                    <div class="text-[8px] font-bold text-left font-mono">IMEI 1: ${item.imei1}</div>
                    <svg id="preview-imei1-${idx}" class="w-full max-h-7"></svg>
                </div>

                ${item.imei2 ? `
                <div class="w-full my-0.5">
                    <div class="text-[8px] font-bold text-left font-mono">IMEI 2: ${item.imei2}</div>
                    <svg id="preview-imei2-${idx}" class="w-full max-h-7"></svg>
                </div>` : ''}

                <div class="w-full flex items-center justify-between border-t border-black pt-1 mt-1 text-[9px] font-black">
                    <span>GENUINE DEVICE</span>
                    <span class="text-emerald-700 font-mono text-[10px]">Rs. ${Number(item.price).toLocaleString()}</span>
                </div>
            `;
            grid.appendChild(card);

            setTimeout(() => {
                try {
                    if (window.JsBarcode) {
                        JsBarcode(`#preview-imei1-${idx}`, item.imei1, { format: "CODE128", width: 1.1, height: 24, displayValue: false, margin: 0 });
                        if (item.imei2) {
                            JsBarcode(`#preview-imei2-${idx}`, item.imei2, { format: "CODE128", width: 1.1, height: 24, displayValue: false, margin: 0 });
                        }
                    }
                } catch(e) {}
            }, 10);

        } else if (item.symbology === 'QR') {
            card.innerHTML = `
                ${showStore ? `<div class="text-[9px] font-black uppercase text-slate-800 truncate w-full border-b border-slate-200 pb-0.5 mb-1">${SHOP_NAME}</div>` : ''}
                <div class="text-[11px] font-bold text-slate-900 truncate w-full">${item.title}</div>
                <div id="preview-qr-${idx}" class="my-2 flex justify-center"></div>
                ${showCode ? `<div class="text-[9px] font-mono font-bold text-slate-700">${item.code}</div>` : ''}
                ${showPrice && (item.price || item.priceText) ? `<div class="text-xs font-black text-emerald-700 mt-1">${item.priceText || ('Rs. ' + Number(item.price).toLocaleString())}</div>` : ''}
            `;
            grid.appendChild(card);

            setTimeout(() => {
                const qrContainer = document.getElementById(`preview-qr-${idx}`);
                if (qrContainer && window.QRCode) {
                    qrContainer.innerHTML = '';
                    new QRCode(qrContainer, {
                        text: item.code,
                        width: 70,
                        height: 70,
                        colorDark : "#000000",
                        colorLight : "#ffffff",
                        correctLevel : QRCode.CorrectLevel.M
                    });
                }
            }, 10);

        } else {
            card.innerHTML = `
                ${showStore ? `<div class="text-[9px] font-black uppercase text-slate-800 truncate w-full border-b border-slate-200 pb-0.5 mb-0.5">${SHOP_NAME}</div>` : ''}
                <div class="text-[11px] font-bold text-slate-900 truncate w-full">${item.title}</div>
                <div class="w-full my-1 flex justify-center">
                    <svg id="preview-svg-${idx}" class="max-w-full" style="height: ${barH}px;"></svg>
                </div>
                <div class="w-full flex items-center justify-between border-t border-slate-200 pt-1 text-[10px]">
                    ${showBrand && item.brand ? `<span class="text-slate-600 font-semibold truncate max-w-[50%]">${item.brand}</span>` : '<span></span>'}
                    ${showPrice ? `<span class="font-black text-emerald-700 font-mono text-xs ml-auto">${item.priceText || ('Rs. ' + Number(item.price).toLocaleString())}</span>` : ''}
                </div>
            `;
            grid.appendChild(card);

            setTimeout(() => {
                try {
                    if (window.JsBarcode) {
                        JsBarcode(`#preview-svg-${idx}`, item.code, {
                            format: item.symbology || "CODE128",
                            width: 1.2,
                            height: barH,
                            displayValue: showCode,
                            fontSize: 10,
                            textMargin: 1,
                            margin: 0
                        });
                    }
                } catch(e) {
                    try {
                        JsBarcode(`#preview-svg-${idx}`, item.code, { format: "CODE128", width: 1.2, height: barH, displayValue: showCode, fontSize: 10, margin: 0 });
                    } catch(err) {}
                }
            }, 10);
        }
    });

    lucide.createIcons();
}

// Trigger Print Sheet Generation
function triggerPrint() {
    const items = getActiveItems();
    if (items.length === 0) {
        alert('<?= $isUrdu ? "براہ کرم پرنٹ کرنے کے لیے کم از کم ایک لیبل منتخب کریں!" : "Please select at least one item to print!" ?>');
        return;
    }

    const printRoot = document.getElementById('printSheetRoot');
    printRoot.innerHTML = '';
    printRoot.className = 'print-only-container print-template-' + currentTemplate;

    const showStore = document.getElementById('toggleStoreHeader').checked;
    const showPrice = document.getElementById('togglePrice').checked;
    const showCode = document.getElementById('toggleCodeText').checked;
    const showBrand = document.getElementById('toggleBrand').checked;
    const barH = parseInt(document.getElementById('barHeightSlider').value) || 30;

    items.forEach((item, idx) => {
        const card = document.createElement('div');
        card.className = 'label-card';

        if (item.type === 'mobile_imei') {
            card.innerHTML = `
                <div style="width: 100%; display: flex; justify-content: space-between; align-items: center; border-bottom: 0.5pt solid #000; padding-bottom: 0.5mm; font-size: 6.5pt; font-weight: bold;">
                    <span>${SHOP_NAME}</span>
                    <span style="border: 0.5pt solid #000; padding: 0 1mm; border-radius: 0.5mm;">${item.pta}</span>
                </div>
                <div style="font-size: 8pt; font-weight: bold; width: 100%; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; margin-top: 0.5mm;">
                    ${item.title}
                </div>
                <div style="font-size: 6.5pt; width: 100%; text-align: left; color: #333;">Color: <strong>${item.color || 'Standard'}</strong></div>
                
                <div style="width: 100%; margin: 0.5mm 0;">
                    <div style="font-size: 6pt; font-family: monospace; text-align: left;">IMEI 1: ${item.imei1}</div>
                    <svg id="print-imei1-${idx}" style="width: 100%; max-height: 22px;"></svg>
                </div>

                ${item.imei2 ? `
                <div style="width: 100%; margin: 0.5mm 0;">
                    <div style="font-size: 6pt; font-family: monospace; text-align: left;">IMEI 2: ${item.imei2}</div>
                    <svg id="print-imei2-${idx}" style="width: 100%; max-height: 22px;"></svg>
                </div>` : ''}

                <div style="width: 100%; display: flex; justify-content: space-between; border-top: 0.5pt solid #000; padding-top: 0.5mm; font-size: 7pt; font-weight: bold;">
                    <span>GENUINE DEVICE</span>
                    <span>Rs. ${Number(item.price).toLocaleString()}</span>
                </div>
            `;
            printRoot.appendChild(card);

            setTimeout(() => {
                try {
                    if (window.JsBarcode) {
                        JsBarcode(`#print-imei1-${idx}`, item.imei1, { format: "CODE128", width: 1.0, height: 20, displayValue: false, margin: 0 });
                        if (item.imei2) {
                            JsBarcode(`#print-imei2-${idx}`, item.imei2, { format: "CODE128", width: 1.0, height: 20, displayValue: false, margin: 0 });
                        }
                    }
                } catch(e) {}
            }, 10);

        } else if (item.symbology === 'QR') {
            card.innerHTML = `
                ${showStore ? `<div style="font-size: 6.5pt; font-weight: bold; width: 100%; text-transform: uppercase; border-bottom: 0.5pt solid #000; padding-bottom: 0.3mm;">${SHOP_NAME}</div>` : ''}
                <div style="font-size: 7.5pt; font-weight: bold; width: 100%; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; margin: 0.5mm 0;">${item.title}</div>
                <div id="print-qr-${idx}" style="margin: 1mm auto; display: flex; justify-content: center;"></div>
                ${showCode ? `<div style="font-size: 6pt; font-family: monospace; font-weight: bold;">${item.code}</div>` : ''}
                ${showPrice ? `<div style="font-size: 7.5pt; font-weight: 900; margin-top: 0.5mm;">${item.priceText || ('Rs. ' + Number(item.price).toLocaleString())}</div>` : ''}
            `;
            printRoot.appendChild(card);

            setTimeout(() => {
                const c = document.getElementById(`print-qr-${idx}`);
                if (c && window.QRCode) {
                    new QRCode(c, {
                        text: item.code,
                        width: 55,
                        height: 55,
                        colorDark: "#000000",
                        colorLight: "#ffffff",
                        correctLevel: QRCode.CorrectLevel.M
                    });
                }
            }, 10);

        } else {
            card.innerHTML = `
                ${showStore ? `<div style="font-size: 6.5pt; font-weight: 900; width: 100%; text-transform: uppercase; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; border-bottom: 0.5pt solid #000; padding-bottom: 0.3mm;">${SHOP_NAME}</div>` : ''}
                <div style="font-size: 7pt; font-weight: 700; width: 100%; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; margin-top: 0.5mm;">${item.title}</div>
                <div style="width: 100%; margin: 0.5mm auto; display: flex; justify-content: center;">
                    <svg id="print-svg-${idx}" style="max-width: 96%; height: ${Math.min(barH, 30)}px;"></svg>
                </div>
                <div style="width: 100%; display: flex; justify-content: space-between; align-items: center; border-top: 0.5pt solid #000; padding-top: 0.5mm; font-size: 6.5pt; font-weight: bold;">
                    ${showBrand && item.brand ? `<span style="max-width: 50%; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;">${item.brand}</span>` : '<span></span>'}
                    ${showPrice ? `<span style="font-size: 7.5pt; font-weight: 900; margin-left: auto;">${item.priceText || ('Rs. ' + Number(item.price).toLocaleString())}</span>` : ''}
                </div>
            `;
            printRoot.appendChild(card);

            setTimeout(() => {
                try {
                    if (window.JsBarcode) {
                        JsBarcode(`#print-svg-${idx}`, item.code, {
                            format: item.symbology || "CODE128",
                            width: 1.1,
                            height: Math.min(barH, 30),
                            displayValue: showCode,
                            fontSize: 9,
                            textMargin: 1,
                            margin: 0
                        });
                    }
                } catch(e) {
                    try {
                        JsBarcode(`#print-svg-${idx}`, item.code, { format: "CODE128", width: 1.1, height: 26, displayValue: showCode, fontSize: 9, margin: 0 });
                    } catch(err) {}
                }
            }, 10);
        }
    });

    // Short timeout to ensure all SVG barcodes finish rasterization
    setTimeout(() => {
        window.print();
    }, 250);
}

// Physical Scanner Verifier with Sound
function handleScannerTest(e) {
    e.preventDefault();
    const input = document.getElementById('scannerTestInput');
    const term = input.value.trim().toLowerCase();
    if (!term) return;

    // Audio chime
    try {
        const audioCtx = new (window.AudioContext || window.webkitAudioContext)();
        const osc = audioCtx.createOscillator();
        const gain = audioCtx.createGain();
        osc.type = 'sine';
        osc.frequency.setValueAtTime(880, audioCtx.currentTime); // A5 chime
        gain.gain.setValueAtTime(0.2, audioCtx.currentTime);
        gain.gain.exponentialRampToValueAtTime(0.001, audioCtx.currentTime + 0.2);
        osc.connect(gain);
        gain.connect(audioCtx.destination);
        osc.start();
        osc.stop(audioCtx.currentTime + 0.2);
    } catch(err) {}

    const resultBox = document.getElementById('scannerResultArea');
    resultBox.classList.remove('hidden');

    // Search against products
    const productsData = <?= json_encode($products) ?>;
    const mobilesData = <?= json_encode($mobiles) ?>;

    const matchedProd = productsData.find(p => 
        (p.sku && p.sku.toLowerCase() === term) ||
        (p.imei_or_serial && p.imei_or_serial.toLowerCase() === term) ||
        ('sku-' + p.id.toString().padStart(5, '0')).toLowerCase() === term ||
        p.id.toString() === term
    );

    const matchedMobile = mobilesData.find(m => 
        (m.imei_1 && m.imei_1.toLowerCase() === term) ||
        (m.imei_2 && m.imei_2.toLowerCase() === term)
    );

    if (matchedProd) {
        resultBox.className = 'p-3 rounded-xl border bg-emerald-500/10 border-emerald-500/30 text-emerald-300 text-xs space-y-1';
        resultBox.innerHTML = `
            <div class="flex items-center justify-between font-bold">
                <span class="flex items-center gap-1.5 text-emerald-400">
                    <i data-lucide="check-circle" class="w-4 h-4"></i>
                    <?= $isUrdu ? 'بارکوڈ تصدیق کامیاب! (Product Matched)' : 'Barcode Verified! (Product Found)' ?>
                </span>
                <span class="font-mono text-white text-sm">Rs. ${Number(matchedProd.sale_price).toLocaleString()}</span>
            </div>
            <div class="text-white font-bold text-sm">${matchedProd.name}</div>
            <div class="text-slate-400 flex items-center gap-3">
                <span><?= $isUrdu ? 'اسٹاک' : 'Stock' ?>: <strong class="text-emerald-400">${matchedProd.stock_quantity}</strong></span>
                <span><?= $isUrdu ? 'کوڈ' : 'Code' ?>: <strong class="font-mono text-cyan-300">${term.toUpperCase()}</strong></span>
            </div>
        `;
    } else if (matchedMobile) {
        resultBox.className = 'p-3 rounded-xl border bg-cyan-500/10 border-cyan-500/30 text-cyan-300 text-xs space-y-1';
        resultBox.innerHTML = `
            <div class="flex items-center justify-between font-bold">
                <span class="flex items-center gap-1.5 text-cyan-400">
                    <i data-lucide="smartphone" class="w-4 h-4"></i>
                    <?= $isUrdu ? 'موبائل IMEI میچ ہو گیا!' : 'Mobile IMEI Matched!' ?>
                </span>
                <span class="font-mono text-white text-sm">Rs. ${Number(matchedMobile.expected_sale_price || matchedMobile.purchase_price).toLocaleString()}</span>
            </div>
            <div class="text-white font-bold text-sm">${matchedMobile.mobile_name}</div>
            <div class="text-slate-400">Color: ${matchedMobile.color || 'Standard'} | Specs: ${matchedMobile.ram_rom || ''}</div>
        `;
    } else {
        resultBox.className = 'p-3 rounded-xl border bg-amber-500/10 border-amber-500/30 text-amber-300 text-xs space-y-1';
        resultBox.innerHTML = `
            <div class="flex items-center gap-1.5 font-bold text-amber-400">
                <i data-lucide="alert-circle" class="w-4 h-4"></i>
                <?= $isUrdu ? 'بارکوڈ اسکین ہو گیا مگر پروڈکٹ ڈیٹابیس میں نہیں ملی' : 'Barcode Scanned, but no matching product found in database' ?>
            </div>
            <div class="text-slate-400"><?= $isUrdu ? 'اسکین شدہ کوڈ' : 'Scanned Code' ?>: <span class="font-mono text-white font-bold">${term}</span></div>
        `;
    }

    lucide.createIcons();
    input.select();
}
</script>

<?php require_once __DIR__ . '/../backend/footer.php'; ?>
