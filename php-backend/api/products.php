<?php
require_once __DIR__ . '/../config.php';

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
        $raw = $stmt->fetchAll();

        $products = array_map(function($p) {
            $units = !empty($p['units_json']) ? json_decode($p['units_json'], true) : [];
            return [
                'id'             => $p['id'],
                'name'           => $p['name'],
                'category'       => $p['category'],
                'purchasePrice'  => (float)$p['purchase_price'],
                'salePrice'      => (float)$p['sale_price'],
                'stock'          => (int)$p['stock'],
                'image'          => $p['image'],
                'brandOrModel'   => $p['brand_or_model'],
                'imeiOrSerial'   => $p['imei_or_serial'],
                'sku'            => $p['sku'],
                'color'          => $p['color'],
                'ramStorage'     => $p['ram_storage'],
                'condition'      => $p['condition_status'],
                'ptaStatus'      => $p['pta_status'],
                'batteryHealth'  => $p['battery_health'],
                'warranty'       => $p['warranty'],
                'wattage'        => $p['wattage'],
                'portType'       => $p['port_type'],
                'compatibleModel'=> $p['compatible_model'],
                'protectorType'  => $p['protector_type'],
                'cableType'      => $p['cable_type'],
                'batteryCapacity'=> $p['battery_capacity'],
                'units'          => $units,
                'createdAt'      => (int)$p['created_at']
            ];
        }, $raw);

        sendResponse($products, 'Products retrieved successfully');
    } catch (Exception $e) {
        sendError('Failed to fetch products: ' . $e->getMessage(), 500);
    }
}

// POST: Save or update product
if ($method === 'POST') {
    $p = getJsonInput();
    if (empty($p['id']) || empty($p['name'])) {
        sendError('Product id and name are required', 400);
    }

    try {
        $sql = "INSERT INTO products (
            id, name, category, purchase_price, sale_price, stock, image,
            brand_or_model, imei_or_serial, sku, color, ram_storage,
            condition_status, pta_status, battery_health, warranty,
            wattage, port_type, compatible_model, protector_type, cable_type,
            battery_capacity, units_json, created_at
        ) VALUES (
            :id, :name, :category, :purchase_price, :sale_price, :stock, :image,
            :brand_or_model, :imei_or_serial, :sku, :color, :ram_storage,
            :condition_status, :pta_status, :battery_health, :warranty,
            :wattage, :port_type, :compatible_model, :protector_type, :cable_type,
            :battery_capacity, :units_json, :created_at
        ) ON DUPLICATE KEY UPDATE
            name = VALUES(name),
            category = VALUES(category),
            purchase_price = VALUES(purchase_price),
            sale_price = VALUES(sale_price),
            stock = VALUES(stock),
            image = VALUES(image),
            brand_or_model = VALUES(brand_or_model),
            imei_or_serial = VALUES(imei_or_serial),
            sku = VALUES(sku),
            color = VALUES(color),
            ram_storage = VALUES(ram_storage),
            condition_status = VALUES(condition_status),
            pta_status = VALUES(pta_status),
            battery_health = VALUES(battery_health),
            warranty = VALUES(warranty),
            wattage = VALUES(wattage),
            port_type = VALUES(port_type),
            compatible_model = VALUES(compatible_model),
            protector_type = VALUES(protector_type),
            cable_type = VALUES(cable_type),
            battery_capacity = VALUES(battery_capacity),
            units_json = VALUES(units_json)";

        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            ':id'               => $p['id'],
            ':name'             => $p['name'],
            ':category'         => $p['category'] ?? 'MOBILES',
            ':purchase_price'   => $p['purchasePrice'] ?? 0,
            ':sale_price'       => $p['salePrice'] ?? 0,
            ':stock'            => $p['stock'] ?? 0,
            ':image'            => $p['image'] ?? null,
            ':brand_or_model'   => $p['brandOrModel'] ?? null,
            ':imei_or_serial'   => $p['imeiOrSerial'] ?? null,
            ':sku'              => $p['sku'] ?? null,
            ':color'            => $p['color'] ?? null,
            ':ram_storage'      => $p['ramStorage'] ?? null,
            ':condition_status' => $p['condition'] ?? 'NEW',
            ':pta_status'       => $p['ptaStatus'] ?? 'PTA_APPROVED',
            ':battery_health'   => $p['batteryHealth'] ?? null,
            ':warranty'         => $p['warranty'] ?? null,
            ':wattage'          => $p['wattage'] ?? null,
            ':port_type'        => $p['portType'] ?? null,
            ':compatible_model' => $p['compatibleModel'] ?? null,
            ':protector_type'   => $p['protectorType'] ?? null,
            ':cable_type'       => $p['cableType'] ?? null,
            ':battery_capacity' => $p['batteryCapacity'] ?? null,
            ':units_json'       => !empty($p['units']) ? json_encode($p['units'], JSON_UNESCAPED_UNICODE) : null,
            ':created_at'       => $p['createdAt'] ?? time() * 1000
        ]);

        sendResponse(['id' => $p['id']], 'Product saved successfully');
    } catch (Exception $e) {
        sendError('Failed to save product: ' . $e->getMessage(), 500);
    }
}

// DELETE: Remove product
if ($method === 'DELETE') {
    $id = $_GET['id'] ?? '';
    if (empty($id)) {
        $body = getJsonInput();
        $id = $body['id'] ?? '';
    }
    if (empty($id)) {
        sendError('Product id is required for deletion', 400);
    }

    try {
        $stmt = $pdo->prepare("DELETE FROM products WHERE id = :id");
        $stmt->execute([':id' => $id]);
        sendResponse(['id' => $id], 'Product deleted successfully');
    } catch (Exception $e) {
        sendError('Failed to delete product: ' . $e->getMessage(), 500);
    }
}
