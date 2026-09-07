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

if ($method === 'GET') {
    try {
        $sales = $pdo->query("SELECT * FROM product_sales ORDER BY created_at DESC LIMIT 500")->fetchAll(PDO::FETCH_ASSOC);
        echo json_encode(['success' => true, 'data' => $sales], JSON_UNESCAPED_UNICODE);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
    exit();
}

if ($method === 'POST') {
    $s = json_decode(file_get_contents('php://input'), true) ?: $_POST;
    if (empty($s['id'])) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Sale ID is required']);
        exit();
    }

    try {
        $stmt = $pdo->prepare("REPLACE INTO product_sales (id, invoice_no, date, time, customer_name, customer_phone, total_amount, discount, net_amount, total_purchase_cost, profit, payment_method, notes, items_json, created_at) VALUES (:id, :invoice_no, :date, :time, :customer_name, :customer_phone, :total_amount, :discount, :net_amount, :total_purchase_cost, :profit, :payment_method, :notes, :items_json, :created_at)");
        $stmt->execute([
            ':id' => $s['id'],
            ':invoice_no' => $s['invoiceNo'] ?? $s['invoice_no'] ?? ('INV-' . time()),
            ':date' => $s['date'] ?? date('Y-m-d'),
            ':time' => $s['time'] ?? date('H:i:s'),
            ':customer_name' => $s['customerName'] ?? $s['customer_name'] ?? 'Walk-in Customer',
            ':customer_phone' => $s['customerPhone'] ?? $s['customer_phone'] ?? null,
            ':total_amount' => $s['totalAmount'] ?? $s['total_amount'] ?? 0,
            ':discount' => $s['discount'] ?? 0,
            ':net_amount' => $s['netAmount'] ?? $s['net_amount'] ?? 0,
            ':total_purchase_cost' => $s['totalPurchaseCost'] ?? $s['total_purchase_cost'] ?? 0,
            ':profit' => $s['profit'] ?? 0,
            ':payment_method' => $s['paymentMethod'] ?? $s['payment_method'] ?? 'CASH',
            ':notes' => $s['notes'] ?? null,
            ':items_json' => !empty($s['items']) ? json_encode($s['items'], JSON_UNESCAPED_UNICODE) : '[]',
            ':created_at' => $s['createdAt'] ?? $s['created_at'] ?? time()
        ]);
        echo json_encode(['success' => true, 'id' => $s['id'], 'message' => 'Sale saved successfully!']);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
    exit();
}
