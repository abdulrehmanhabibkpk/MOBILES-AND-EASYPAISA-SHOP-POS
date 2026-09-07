<?php
require_once __DIR__ . '/../config.php';

$method = $_SERVER['REQUEST_METHOD'];

// GET: Fetch transactions
if ($method === 'GET') {
    try {
        $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 1000;
        $date = $_GET['date'] ?? '';

        $sql = "SELECT * FROM transactions WHERE 1=1";
        $params = [];
        if (!empty($date)) {
            $sql .= " AND date = :dt";
            $params[':dt'] = $date;
        }
        $sql .= " ORDER BY created_at DESC LIMIT " . $limit;

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $raw = $stmt->fetchAll();

        $transactions = array_map(function($t) {
            return [
                'id'              => $t['id'],
                'date'            => $t['date'],
                'time'            => $t['time'],
                'type'            => $t['type'],
                'customerName'    => $t['customer_name'],
                'customerPhone'   => $t['customer_phone'],
                'trxId'           => $t['trx_id'],
                'easyPaisaAmount' => (float)$t['easy_paisa_amount'],
                'cashAmount'      => (float)$t['cash_amount'],
                'feeProfit'       => (float)$t['fee_profit'],
                'expenseAmount'   => (float)$t['expense_amount'],
                'paymentMethod'   => $t['payment_method'],
                'notes'           => $t['notes'],
                'createdAt'       => (int)$t['created_at']
            ];
        }, $raw);

        sendResponse($transactions, 'Transactions retrieved successfully');
    } catch (Exception $e) {
        sendError('Failed to fetch transactions: ' . $e->getMessage(), 500);
    }
}

// POST: Save or update transaction
if ($method === 'POST') {
    $t = getJsonInput();
    if (empty($t['id'])) {
        sendError('Transaction id is required', 400);
    }

    try {
        $sql = "INSERT INTO transactions (
            id, date, time, type, customer_name, customer_phone, trx_id,
            easy_paisa_amount, cash_amount, fee_profit, expense_amount,
            payment_method, notes, created_at
        ) VALUES (
            :id, :date, :time, :type, :customer_name, :customer_phone, :trx_id,
            :easy_paisa_amount, :cash_amount, :fee_profit, :expense_amount,
            :payment_method, :notes, :created_at
        ) ON DUPLICATE KEY UPDATE
            customer_name = VALUES(customer_name),
            customer_phone = VALUES(customer_phone),
            trx_id = VALUES(trx_id),
            easy_paisa_amount = VALUES(easy_paisa_amount),
            cash_amount = VALUES(cash_amount),
            fee_profit = VALUES(fee_profit),
            expense_amount = VALUES(expense_amount),
            payment_method = VALUES(payment_method),
            notes = VALUES(notes)";

        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            ':id'               => $t['id'],
            ':date'             => $t['date'] ?? date('Y-m-d'),
            ':time'             => $t['time'] ?? date('H:i:s'),
            ':type'             => $t['type'] ?? 'SELL_CASH',
            ':customer_name'    => $t['customerName'] ?? 'Customer',
            ':customer_phone'   => $t['customerPhone'] ?? null,
            ':trx_id'           => $t['trxId'] ?? null,
            ':easy_paisa_amount'=> $t['easyPaisaAmount'] ?? 0,
            ':cash_amount'      => $t['cashAmount'] ?? 0,
            ':fee_profit'       => $t['feeProfit'] ?? 0,
            ':expense_amount'   => $t['expenseAmount'] ?? 0,
            ':payment_method'   => $t['paymentMethod'] ?? 'CASH',
            ':notes'            => $t['notes'] ?? null,
            ':created_at'       => $t['createdAt'] ?? time() * 1000
        ]);

        sendResponse(['id' => $t['id']], 'Transaction saved successfully');
    } catch (Exception $e) {
        sendError('Failed to save transaction: ' . $e->getMessage(), 500);
    }
}

// DELETE: Delete transaction
if ($method === 'DELETE') {
    $id = $_GET['id'] ?? '';
    if (empty($id)) {
        $body = getJsonInput();
        $id = $body['id'] ?? '';
    }
    if (empty($id)) {
        sendError('Transaction id is required for deletion', 400);
    }

    try {
        $stmt = $pdo->prepare("DELETE FROM transactions WHERE id = :id");
        $stmt->execute([':id' => $id]);
        sendResponse(['id' => $id], 'Transaction deleted successfully');
    } catch (Exception $e) {
        sendError('Failed to delete transaction: ' . $e->getMessage(), 500);
    }
}
