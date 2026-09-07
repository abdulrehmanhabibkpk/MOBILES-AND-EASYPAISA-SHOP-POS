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

// Quick health check to test connectivity
try {
    $stmt = $pdo->query("SELECT 
        (SELECT COUNT(*) FROM products) AS total_products,
        (SELECT COUNT(*) FROM product_sales) AS total_sales,
        (SELECT COUNT(*) FROM transactions) AS total_transactions,
        (SELECT COUNT(*) FROM suppliers) AS total_suppliers
    ");
    $stats = $stmt->fetch(PDO::FETCH_ASSOC);

    echo json_encode([
        'success' => true,
        'status' => 'HEALTHY',
        'database' => $DB_NAME ?? 'balal_db',
        'host' => $DB_HOST ?? 'localhost',
        'stats' => $stats,
        'server_time' => date('Y-m-d H:i:s'),
        'php_version' => phpversion()
    ], JSON_UNESCAPED_UNICODE);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Health check query failed: ' . $e->getMessage()]);
}
