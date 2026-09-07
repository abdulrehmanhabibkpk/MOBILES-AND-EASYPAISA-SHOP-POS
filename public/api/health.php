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

// Quick health check to test connectivity from browser or React app
try {
    $stmt = $pdo->query("SELECT 
        (SELECT COUNT(*) FROM products) AS total_products,
        (SELECT COUNT(*) FROM product_sales) AS total_sales,
        (SELECT COUNT(*) FROM transactions) AS total_transactions,
        (SELECT COUNT(*) FROM suppliers) AS total_suppliers
    ");
    $stats = $stmt->fetch();

    sendResponse([
        'status'    => 'HEALTHY',
        'database'  => $DB_NAME,
        'host'      => $DB_HOST,
        'stats'     => $stats,
        'server_time' => date('Y-m-d H:i:s'),
        'php_version' => phpversion()
    ], 'Balal Mobile PHP & MySQL Backend is Running Perfectly!');
} catch (Exception $e) {
    sendError('Health check query failed: ' . $e->getMessage(), 500);
}
