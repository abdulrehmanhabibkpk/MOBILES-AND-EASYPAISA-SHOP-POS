<?php
if (file_exists(__DIR__ . '/../config.php')) {
    require_once __DIR__ . '/../config.php';
} elseif (file_exists(__DIR__ . '/config.php')) {
    require_once __DIR__ . '/config.php';
} else {
    http_response_code(500);
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'error' => 'Database config.php file not found.']);
    exit();
}

$method = $_SERVER['REQUEST_METHOD'];

// GET: Fetch product sales
if ($method === 'GET') {
    try {
        $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 500;
        $date = $_GET['date'] ?? '';

        $sql = "SELECT * FROM product_sales WHERE 1=1";
        $params = [];
        if (!empty($date)) {
            $sql .= " AND date = :dt";
            $params[':dt'] = $date;
        }
        $sql .= " ORDER BY created_at DESC LIMIT " . $limit;

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $raw = $stmt->fetchAll();

        $sales = array_map(function($s) {
            return [
                'id'                => $s['id'],
                'invoiceNo'         => $s['invoice_no'],
                'date'              => $s['date'],
                'time'              => $s['time'],
                'customerName'      => $s['customer_name'],
                'customerPhone'     => $s['customer_phone'],
                'totalAmount'       => (float)$s['total_amount'],
                'discount'          => (float)$s['discount'],
                'netAmount'         => (float)$s['net_amount'],
                'totalPurchaseCost' => (float)$s['total_purchase_cost'],
                'profit'            => (float)$s['profit'],
                'paymentMethod'     => $s['payment_method'],
                'notes'             => $s['notes'],
                'items'             => !empty($s['items_json']) ? json_decode($s['items_json'], true) : [],
                'createdAt'         => (int)$s['created_at']
            ];
        }, $raw);

        sendResponse($sales, 'Sales retrieved successfully');
    } catch (Exception $e) {
        sendError('Failed to fetch sales: ' . $e->getMessage(), 500);
    }
}

// POST: Record a new POS Sale
if ($method === 'POST') {
    $s = getJsonInput();
    if (empty($s['id']) || empty($s['items'])) {
        sendError('Sale id and items array are required', 400);
    }

    try {
        $pdo->beginTransaction();

        // 1. Insert/Update into product_sales
        $sql = "INSERT INTO product_sales (
            id, invoice_no, date, time, customer_name, customer_phone,
            total_amount, discount, net_amount, total_purchase_cost,
            profit, payment_method, notes, items_json, created_at
        ) VALUES (
            :id, :invoice_no, :date, :time, :customer_name, :customer_phone,
            :total_amount, :discount, :net_amount, :total_purchase_cost,
            :profit, :payment_method, :notes, :items_json, :created_at
        ) ON DUPLICATE KEY UPDATE
            customer_name = VALUES(customer_name),
            customer_phone = VALUES(customer_phone),
            total_amount = VALUES(total_amount),
            discount = VALUES(discount),
            net_amount = VALUES(net_amount),
            total_purchase_cost = VALUES(total_purchase_cost),
            profit = VALUES(profit),
            payment_method = VALUES(payment_method),
            notes = VALUES(notes),
            items_json = VALUES(items_json)";

        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            ':id'                => $s['id'],
            ':invoice_no'        => $s['invoiceNo'] ?? 'INV-' . time(),
            ':date'              => $s['date'] ?? date('Y-m-d'),
            ':time'              => $s['time'] ?? date('H:i:s'),
            ':customer_name'     => $s['customerName'] ?? 'Walk-in Customer',
            ':customer_phone'    => $s['customerPhone'] ?? null,
            ':total_amount'      => $s['totalAmount'] ?? 0,
            ':discount'          => $s['discount'] ?? 0,
            ':net_amount'        => $s['netAmount'] ?? 0,
            ':total_purchase_cost' => $s['totalPurchaseCost'] ?? 0,
            ':profit'            => $s['profit'] ?? 0,
            ':payment_method'    => $s['paymentMethod'] ?? 'CASH',
            ':notes'             => $s['notes'] ?? null,
            ':items_json'        => !empty($s['items']) ? json_encode($s['items'], JSON_UNESCAPED_UNICODE) : '[]',
            ':created_at'        => $s['createdAt'] ?? time() * 1000
        ]);

        // 2. Adjust product stock & units in products table
        if (!empty($s['items']) && is_array($s['items'])) {
            $updateStockStmt = $pdo->prepare("UPDATE products SET stock = GREATEST(0, stock - :qty) WHERE id = :id");
            $getProductStmt = $pdo->prepare("SELECT units_json FROM products WHERE id = :id");
            $updateUnitsStmt = $pdo->prepare("UPDATE products SET units_json = :units WHERE id = :id");

            foreach ($s['items'] as $item) {
                $pId = $item['productId'] ?? '';
                $qty = (int)($item['quantity'] ?? 1);
                if (empty($pId)) continue;

                $updateStockStmt->execute([':qty' => $qty, ':id' => $pId]);

                // If a specific IMEI/Unit was sold, mark it as SOLD in units_json
                if (!empty($item['selectedUnitId'])) {
                    $getProductStmt->execute([':id' => $pId]);
                    $prod = $getProductStmt->fetch();
                    if ($prod && !empty($prod['units_json'])) {
                        $units = json_decode($prod['units_json'], true);
                        if (is_array($units)) {
                            foreach ($units as &$u) {
                                if (($u['id'] ?? '') === $item['selectedUnitId']) {
                                    $u['status'] = 'SOLD';
                                    $u['soldInvoiceNo'] = $s['invoiceNo'] ?? '';
                                    $u['soldDate'] = $s['date'] ?? date('Y-m-d');
                                }
                            }
                            $updateUnitsStmt->execute([
                                ':units' => json_encode($units, JSON_UNESCAPED_UNICODE),
                                ':id'    => $pId
                            ]);
                        }
                    }
                }
            }
        }

        // 3. Record in transactions ledger
        $isDigital = in_array($s['paymentMethod'] ?? '', ['EASYPAISA', 'JAZZCASH', 'BANK']);
        $trxId = 'trx-pos-' . $s['id'];
        $trxSql = "INSERT INTO transactions (
            id, date, time, type, customer_name, customer_phone,
            easy_paisa_amount, cash_amount, fee_profit, expense_amount,
            payment_method, notes, created_at
        ) VALUES (
            :id, :date, :time, 'SELL_CASH', :customer_name, :customer_phone,
            :ep_amt, :cash_amt, :fee_profit, 0,
            :payment_method, :notes, :created_at
        ) ON DUPLICATE KEY UPDATE
            customer_name = VALUES(customer_name),
            cash_amount = VALUES(cash_amount),
            easy_paisa_amount = VALUES(easy_paisa_amount),
            fee_profit = VALUES(fee_profit)";

        $stmtTrx = $pdo->prepare($trxSql);
        $stmtTrx->execute([
            ':id'             => $trxId,
            ':date'           => $s['date'] ?? date('Y-m-d'),
            ':time'           => $s['time'] ?? date('H:i:s'),
            ':customer_name'  => $s['customerName'] ?? 'Walk-in Customer (POS)',
            ':customer_phone' => $s['customerPhone'] ?? null,
            ':ep_amt'         => $isDigital ? ($s['netAmount'] ?? 0) : 0,
            ':cash_amt'       => !$isDigital ? ($s['netAmount'] ?? 0) : 0,
            ':fee_profit'     => $s['profit'] ?? 0,
            ':payment_method' => $s['paymentMethod'] ?? 'CASH',
            ':notes'          => "POS Bill #{$s['invoiceNo']}",
            ':created_at'     => $s['createdAt'] ?? time() * 1000
        ]);

        $pdo->commit();
        sendResponse(['id' => $s['id'], 'invoiceNo' => $s['invoiceNo']], 'Sale recorded and inventory adjusted successfully');
    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        sendError('Failed to record sale: ' . $e->getMessage(), 500);
    }
}
