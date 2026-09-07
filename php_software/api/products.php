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

// GET: Fetch products with optional search / filter
if ($method === 'GET') {
    try {
        $search = $_GET['q'] ?? '';
        $category = $_GET['category'] ?? '';

        $sql = "SELECT * FROM products WHERE 1=1";
        $params = [];

        if (!empty($category)) {
            $sql .= " AND category = :cat";
            $params[':cat'] = $category;
        }

        if (!empty($search)) {
            $sql .= " AND (name LIKE :q OR imei_or_serial LIKE :q OR sku LIKE :q OR brand_or_model LIKE :q)";
            $params[':q'] = "%{$search}%";
        }

        $sql .= " ORDER BY created_at DESC";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $raw = $stmt->fetchAll(PDO::FETCH_ASSOC);

        echo json_encode(['success' => true, 'data' => $raw], JSON_UNESCAPED_UNICODE);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
    exit();
}

// POST: Save or update product
if ($method === 'POST') {
    $p = json_decode(file_get_contents('php://input'), true) ?: $_POST;
    if (empty($p['id']) || empty($p['name'])) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Product id and name are required']);
        exit();
    }

    try {
        $stmt = $pdo->prepare("REPLACE INTO products (id, name, category, purchase_price, sale_price, stock, image, brand_or_model, imei_or_serial, sku, created_at) VALUES (:id, :name, :category, :purchase_price, :sale_price, :stock, :image, :brand_or_model, :imei_or_serial, :sku, :created_at)");
        $stmt->execute([
            ':id' => $p['id'],
            ':name' => $p['name'],
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
        echo json_encode(['success' => true, 'id' => $p['id'], 'message' => 'Product saved successfully!']);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
    exit();
}
