<?php
/**
 * ============================================================================
 * Balal Mobile Shop & EasyPaisa - Database & API Configuration
 * Compatible with InfinityFree cPanel & Hostinger hPanel
 * ============================================================================
 */

// 1. CORS Headers (Allows your React Web App to communicate with this PHP API)
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With, X-Shop-Secret");
header("Content-Type: application/json; charset=UTF-8");

// Handle preflight OPTIONS request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// 2. Database Credentials
// NOTE FOR INFINITYFREE:
// - Host is usually something like: sql123.infinityfree.com or sql205.epizy.com
// - User is usually: epiz_XXXXXXX
// - Password is your vPanel account password
// - Database Name is: epiz_XXXXXXX_balal_db
//
// NOTE FOR HOSTINGER:
// - Host is usually: localhost (or 127.0.0.1)
// - User is: uXXXXXXX_balal
// - Password is what you set in hPanel
// - Database Name is: uXXXXXXX_balal_db

$DB_HOST = 'localhost';          // Change to your MySQL Host (e.g. sql123.infinityfree.com or localhost)
$DB_NAME = 'balal_mobile_db';    // Change to your MySQL Database Name
$DB_USER = 'root';               // Change to your MySQL Username
$DB_PASS = '';                   // Change to your MySQL Password
$DB_PORT = 3306;                 // Default MySQL port is 3306

// Optional Security Secret Token (Leave empty to disable or set a secret string)
$API_SECRET = '';

try {
    $dsn = "mysql:host={$DB_HOST};port={$DB_PORT};dbname={$DB_NAME};charset=utf8mb4";
    $pdo = new PDO($dsn, $DB_USER, $DB_PASS, [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
        PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci"
    ]);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error'   => 'Database Connection Failed: ' . $e->getMessage(),
        'hint'    => 'Please check $DB_HOST, $DB_NAME, $DB_USER, and $DB_PASS in config.php'
    ], JSON_UNESCAPED_UNICODE);
    exit();
}

// 3. Helper Functions
function getJsonInput() {
    $raw = file_get_contents('php://input');
    if (!$raw) return [];
    $data = json_decode($raw, true);
    return is_array($data) ? $data : [];
}

function sendResponse($data = [], $message = 'Success', $code = 200) {
    http_response_code($code);
    echo json_encode([
        'success' => true,
        'message' => $message,
        'data'    => $data,
        'time'    => date('Y-m-d H:i:s')
    ], JSON_UNESCAPED_UNICODE);
    exit();
}

function sendError($message = 'An error occurred', $code = 400, $details = null) {
    http_response_code($code);
    echo json_encode([
        'success' => false,
        'error'   => $message,
        'details' => $details,
        'time'    => date('Y-m-d H:i:s')
    ], JSON_UNESCAPED_UNICODE);
    exit();
}
