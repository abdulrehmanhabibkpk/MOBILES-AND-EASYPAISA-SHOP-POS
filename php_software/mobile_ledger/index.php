<?php
$pageTitle = 'موبائل و آئی ایم ای آئی لیجر (Mobile & IMEI Ledger)';
$activeMenu = 'mobile_ledger';
require_once __DIR__ . '/../backend/header.php';

$search = trim($_GET['q'] ?? '');

$sql = "SELECT * FROM products WHERE category = 'MOBILES' OR imei_or_serial IS NOT NULL AND imei_or_serial != ''";
if (!empty($search)) {
    $sql .= " AND (name LIKE :q OR imei_or_serial LIKE :q OR brand_or_model LIKE :q)";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([':q' => "%{$search}%"]);
} else {
    $sql .= " ORDER BY created_at DESC";
    $stmt = $pdo->query($sql);
}
$mobiles = $stmt->fetchAll();

// Count stats
$inStockCount = 0;
$soldCount = 0;
$totalInvested = 0;
foreach($mobiles as $m) {
    if ($m['stock'] > 0) {
        $inStockCount += $m['stock'];
        $totalInvested += ($m['purchase_price'] * $m['stock']);
    } else {
        $soldCount++;
    }
}
?>

<!-- Metric Bar -->
<div class="grid grid-cols-1 sm:grid-cols-3 gap-4 mb-6">
    <div class="bg-slate-900 border border-slate-800 p-4 rounded-xl">
        <p class="text-xs text-slate-400">موجودہ موبائل سیٹ (In Stock)</p>
        <h4 class="text-2xl font-black text-emerald-400 mt-1"><?= $inStockCount ?> سیٹس دستیاب</h4>
    </div>
    <div class="bg-slate-900 border border-slate-800 p-4 rounded-xl">
        <p class="text-xs text-slate-400">موبائلز میں انویسٹمنٹ (Investment)</p>
        <h4 class="text-2xl font-black text-white mt-1">Rs. <?= number_format($totalInvested) ?></h4>
    </div>
    <div class="bg-slate-900 border border-slate-800 p-4 rounded-xl">
        <p class="text-xs text-slate-400">فروخت شدہ سیٹس (Sold Out)</p>
        <h4 class="text-2xl font-black text-cyan-400 mt-1"><?= $soldCount ?> سیٹس بک چکے ہیں</h4>
    </div>
</div>

<!-- Search & Table -->
<div class="bg-slate-900 border border-slate-800 rounded-2xl overflow-hidden shadow-lg">
    <div class="p-4 border-b border-slate-800 flex flex-col sm:flex-row items-center justify-between gap-3 no-print">
        <h3 class="font-bold text-white text-sm flex items-center gap-2">
            <i data-lucide="binary" class="w-4 h-4 text-emerald-400"></i>
            <span>تمام موبائلز کی تفصیلی IMEI ہسٹری</span>
        </h3>
        <form method="GET" class="flex items-center gap-2">
            <input type="text" name="q" value="<?= htmlspecialchars($search) ?>" placeholder="IMEI نمبر یا ماڈل تلاش کریں..." class="bg-slate-950 border border-slate-700 text-white px-3 py-2 rounded-xl text-xs focus:border-emerald-500 focus:outline-none w-64">
            <button type="submit" class="px-4 py-2 bg-slate-800 hover:bg-slate-700 text-white font-bold text-xs rounded-xl border border-slate-700">تلاش</button>
            <button type="button" onclick="window.print()" class="px-3 py-2 bg-slate-800 text-slate-300 rounded-xl border border-slate-700 text-xs">پرنٹ</button>
        </form>
    </div>

    <div class="overflow-x-auto">
        <table class="w-full text-right text-xs">
            <thead class="bg-slate-950 text-slate-400 border-b border-slate-800">
                <tr>
                    <th class="p-3.5">ماڈل و نام</th>
                    <th class="p-3.5">IMEI نمبر</th>
                    <th class="p-3.5">کنڈیشن و PTA</th>
                    <th class="p-3.5">خریداری قیمت</th>
                    <th class="p-3.5">فروخت قیمت</th>
                    <th class="p-3.5">امکانی منافع</th>
                    <th class="p-3.5 text-center">موجودہ اسٹیٹس</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-800">
                <?php if(empty($mobiles)): ?>
                    <tr><td colspan="7" class="py-8 text-center text-slate-500">کوئی موبائل ریکارڈ موجود نہیں ہے</td></tr>
                <?php else: foreach($mobiles as $m): 
                    $profitMargin = $m['sale_price'] - $m['purchase_price'];
                    $isInStock = $m['stock'] > 0;
                ?>
                    <tr class="hover:bg-slate-800/40">
                        <td class="p-3.5 font-medium text-white">
                            <?= htmlspecialchars($m['name']) ?>
                            <span class="text-[10px] text-slate-400 block"><?= htmlspecialchars($m['ram_storage'] ?? '') ?> <?= htmlspecialchars($m['color'] ?? '') ?></span>
                        </td>
                        <td class="p-3.5 font-mono text-cyan-400 font-bold"><?= htmlspecialchars($m['imei_or_serial'] ?: 'غیر درج شدہ') ?></td>
                        <td class="p-3.5">
                            <span class="px-2 py-0.5 rounded-full text-[10px] bg-slate-800 text-slate-300 border border-slate-700">
                                <?= $m['pta_status'] === 'PTA_APPROVED' ? 'PTA اپرووڈ' : 'نان پی ٹی اے' ?>
                            </span>
                            <span class="text-[10px] text-slate-400 mr-1"><?= $m['condition_status'] === 'NEW' ? 'نیا' : 'استعمال شدہ' ?></span>
                        </td>
                        <td class="p-3.5 text-slate-400">Rs. <?= number_format($m['purchase_price']) ?></td>
                        <td class="p-3.5 text-white font-bold">Rs. <?= number_format($m['sale_price']) ?></td>
                        <td class="p-3.5 text-emerald-400 font-bold">Rs. <?= number_format($profitMargin) ?></td>
                        <td class="p-3.5 text-center">
                            <?php if($isInStock): ?>
                                <span class="px-2.5 py-1 rounded-full text-xs font-bold bg-emerald-500/10 text-emerald-300 border border-emerald-500/20">
                                    دکان پر موجود (<?= $m['stock'] ?>)
                                </span>
                            <?php else: ?>
                                <span class="px-2.5 py-1 rounded-full text-xs font-bold bg-slate-800 text-slate-400 border border-slate-700">
                                    فروخت ہو چکا ہے
                                </span>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require_once __DIR__ . '/../backend/footer.php'; ?>
