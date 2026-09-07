<?php
require_once __DIR__ . '/../config.php';

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
