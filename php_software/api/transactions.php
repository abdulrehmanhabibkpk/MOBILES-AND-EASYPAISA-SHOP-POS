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
        $trx = $pdo->query("SELECT * FROM transactions ORDER BY created_at DESC LIMIT 500")->fetchAll(PDO::FETCH_ASSOC);
        echo json_encode(['success' => true, 'data' => $trx], JSON_UNESCAPED_UNICODE);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
    exit();
}

if ($method === 'POST') {
    $t = json_decode(file_get_contents('php://input'), true) ?: $_POST;
    if (empty($t['id'])) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Transaction ID is required']);
        exit();
    }

    try {
        $stmt = $pdo->prepare("REPLACE INTO transactions (id, type, customer_name, customer_phone, trx_id, easypaisa_amount, cash_amount, expense_amount, fee_profit, payment_method, notes, date, time, created_at) VALUES (:id, :type, :customer_name, :customer_phone, :trx_id, :easypaisa_amount, :cash_amount, :expense_amount, :fee_profit, :payment_method, :notes, :date, :time, :created_at)");
        $stmt->execute([
            ':id' => $t['id'],
            ':type' => $t['type'] ?? 'SELL_CASH',
            ':customer_name' => $t['customerName'] ?? $t['customer_name'] ?? 'Customer',
            ':customer_phone' => $t['customerPhone'] ?? $t['customer_phone'] ?? '',
            ':trx_id' => $t['trxId'] ?? $t['trx_id'] ?? null,
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
        echo json_encode(['success' => true, 'id' => $t['id'], 'message' => 'Transaction saved successfully!']);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
    exit();
}
