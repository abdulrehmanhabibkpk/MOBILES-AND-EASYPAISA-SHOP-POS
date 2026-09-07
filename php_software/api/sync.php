<?php
if (file_exists(__DIR__ . '/../backend/config.php')) {
    require_once __DIR__ . '/../backend/config.php';
} elseif (file_exists(__DIR__ . '/../config.php')) {
    require_once __DIR__ . '/../config.php';
} else {
    http_response_code(500);
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'error' => 'Database config.php file not found.']);
    exit();
}

$method = $_SERVER['REQUEST_METHOD'];

// GET: Pull all data from MySQL
if ($method === 'GET') {
    try {
        $products = $pdo->query("SELECT * FROM products ORDER BY created_at DESC")->fetchAll(PDO::FETCH_ASSOC);
        $sales = $pdo->query("SELECT * FROM product_sales ORDER BY created_at DESC LIMIT 2000")->fetchAll(PDO::FETCH_ASSOC);
        $transactions = $pdo->query("SELECT * FROM transactions ORDER BY created_at DESC LIMIT 2000")->fetchAll(PDO::FETCH_ASSOC);
        $suppliers = $pdo->query("SELECT * FROM suppliers ORDER BY name ASC")->fetchAll(PDO::FETCH_ASSOC);
        $purchases = $pdo->query("SELECT * FROM mobile_purchases ORDER BY created_at DESC LIMIT 1000")->fetchAll(PDO::FETCH_ASSOC);
        $dailyBalances = $pdo->query("SELECT * FROM daily_balances")->fetchAll(PDO::FETCH_ASSOC);
        $settings = getShopSettings($pdo);

        echo json_encode([
            'success' => true,
            'data' => [
                'products' => $products,
                'productSales' => $sales,
                'transactions' => $transactions,
                'suppliers' => $suppliers,
                'mobilePurchases' => $purchases,
                'dailyBalances' => $dailyBalances,
                'settings' => $settings
            ]
        ], JSON_UNESCAPED_UNICODE);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
    exit();
}

// POST: Push and synchronize data into MySQL
if ($method === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true) ?: $_POST;
    try {
        $pdo->beginTransaction();

        $savedProducts = 0;
        $savedSales = 0;
        $savedTrx = 0;

        // Sync Products
        if (!empty($input['products']) && is_array($input['products'])) {
            $stmt = $pdo->prepare("REPLACE INTO products (id, name, category, purchase_price, sale_price, stock, image, brand_or_model, imei_or_serial, sku, created_at) VALUES (:id, :name, :category, :purchase_price, :sale_price, :stock, :image, :brand_or_model, :imei_or_serial, :sku, :created_at)");
            foreach ($input['products'] as $p) {
                if (empty($p['id'])) continue;
                $stmt->execute([
                    ':id' => $p['id'],
                    ':name' => $p['name'] ?? 'Product',
                    ':category' => $p['category'] ?? 'ACCESSORIES',
                    ':purchase_price' => $p['purchasePrice'] ?? $p['purchase_price'] ?? 0,
                    ':sale_price' => $p['salePrice'] ?? $p['sale_price'] ?? 0,
                    ':stock' => $p['stock'] ?? 0,
                    ':image' => $p['image'] ?? null,
                    ':brand_or_model' => $p['brandOrModel'] ?? $p['brand_or_model'] ?? null,
                    ':imei_or_serial' => $p['imeiOrSerial'] ?? $p['imei_or_serial'] ?? null,
                    ':sku' => $p['sku'] ?? null,
                    ':created_at' => $p['createdAt'] ?? $p['created_at'] ?? time()
                ]);
                $savedProducts++;
            }
        }

        // Sync Transactions
        if (!empty($input['transactions']) && is_array($input['transactions'])) {
            $stmt = $pdo->prepare("REPLACE INTO transactions (id, type, customer_name, customer_phone, easypaisa_amount, cash_amount, expense_amount, fee_profit, payment_method, notes, date, time, created_at) VALUES (:id, :type, :customer_name, :customer_phone, :easypaisa_amount, :cash_amount, :expense_amount, :fee_profit, :payment_method, :notes, :date, :time, :created_at)");
            foreach ($input['transactions'] as $t) {
                if (empty($t['id'])) continue;
                $stmt->execute([
                    ':id' => $t['id'],
                    ':type' => $t['type'] ?? 'SELL_CASH',
                    ':customer_name' => $t['customerName'] ?? $t['customer_name'] ?? '',
                    ':customer_phone' => $t['customerPhone'] ?? $t['customer_phone'] ?? '',
                    ':easypaisa_amount' => $t['easyPaisaAmount'] ?? $t['easypaisa_amount'] ?? 0,
                    ':cash_amount' => $t['cashAmount'] ?? $t['cash_amount'] ?? 0,
                    ':expense_amount' => $t['expenseAmount'] ?? $t['expense_amount'] ?? 0,
                    ':fee_profit' => $t['feeProfit'] ?? $t['fee_profit'] ?? 0,
                    ':payment_method' => $t['paymentMethod'] ?? $t['payment_method'] ?? 'CASH',
                    ':notes' => $t['notes'] ?? '',
                    ':date' => $t['date'] ?? date('Y-m-d'),
                    ':time' => $t['time'] ?? date('h:i A'),
                    ':created_at' => $t['createdAt'] ?? $t['created_at'] ?? time()
                ]);
                $savedTrx++;
            }
        }

        $pdo->commit();
        echo json_encode([
            'success' => true,
            'savedProducts' => $savedProducts,
            'savedTrx' => $savedTrx,
            'message' => 'Data successfully synchronized into MySQL database!'
        ], JSON_UNESCAPED_UNICODE);
    } catch (Exception $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
}
