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

// =========================================================================
// GET: Pull all data from MySQL into the client
// =========================================================================
if ($method === 'GET') {
    try {
        // 1. Products
        $stmt = $pdo->query("SELECT * FROM products ORDER BY created_at DESC");
        $rawProducts = $stmt->fetchAll();
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
        }, $rawProducts);

        // 2. Product Sales
        $stmt = $pdo->query("SELECT * FROM product_sales ORDER BY created_at DESC LIMIT 2000");
        $rawSales = $stmt->fetchAll();
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
        }, $rawSales);

        // 3. Transactions
        $stmt = $pdo->query("SELECT * FROM transactions ORDER BY created_at DESC LIMIT 2000");
        $rawTrx = $stmt->fetchAll();
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
        }, $rawTrx);

        // 4. Suppliers
        $stmt = $pdo->query("SELECT * FROM suppliers ORDER BY name ASC");
        $rawSuppliers = $stmt->fetchAll();
        $suppliers = array_map(function($sp) {
            return [
                'id'        => $sp['id'],
                'name'      => $sp['name'],
                'shopName'  => $sp['shop_name'],
                'phone'     => $sp['phone'],
                'city'      => $sp['city'],
                'balance'   => (float)$sp['balance'],
                'notes'     => $sp['notes'],
                'createdAt' => (int)$sp['created_at']
            ];
        }, $rawSuppliers);

        // 5. Mobile Purchases
        $stmt = $pdo->query("SELECT * FROM mobile_purchases ORDER BY created_at DESC LIMIT 1000");
        $rawPurchases = $stmt->fetchAll();
        $purchases = array_map(function($mp) {
            return [
                'id'             => $mp['id'],
                'receiptNo'      => $mp['receipt_no'],
                'date'           => $mp['date'],
                'time'           => $mp['time'],
                'sellerName'     => $mp['seller_name'],
                'sellerPhone'    => $mp['seller_phone'],
                'sellerCnic'     => $mp['seller_cnic'],
                'sellerCity'     => $mp['seller_city'],
                'sellerType'     => $mp['seller_type'],
                'items'          => !empty($mp['items_json']) ? json_decode($mp['items_json'], true) : [],
                'totalAmount'    => (float)$mp['total_amount'],
                'paidAmount'     => (float)$mp['paid_amount'],
                'balanceDue'     => (float)$mp['balance_due'],
                'paymentChannel' => $mp['payment_channel'],
                'notes'          => $mp['notes'],
                'createdAt'      => (int)$mp['created_at']
            ];
        }, $rawPurchases);

        // 6. Daily Balances
        $stmt = $pdo->query("SELECT * FROM daily_balances");
        $rawBalances = $stmt->fetchAll();
        $dailyBalances = [];
        foreach ($rawBalances as $b) {
            $dailyBalances[$b['date']] = [
                'date'             => $b['date'],
                'openingCash'      => (float)$b['opening_cash'],
                'openingEasyPaisa' => (float)$b['opening_easypaisa'],
                'notes'            => $b['notes']
            ];
        }

        // 7. Settings
        $stmt = $pdo->query("SELECT setting_value FROM app_settings WHERE setting_key = 'shop_config'");
        $settingRow = $stmt->fetch();
        $settings = $settingRow ? json_decode($settingRow['setting_value'], true) : null;

        sendResponse([
            'products'       => $products,
            'productSales'   => $sales,
            'transactions'   => $transactions,
            'suppliers'      => $suppliers,
            'mobilePurchases'=> $purchases,
            'dailyBalances'  => $dailyBalances,
            'settings'       => $settings
        ], 'All data fetched from MySQL successfully');

    } catch (Exception $e) {
        sendError('Failed to pull data from MySQL: ' . $e->getMessage(), 500);
    }
}

// =========================================================================
// POST: Push and synchronize data into MySQL
// =========================================================================
if ($method === 'POST') {
    $input = getJsonInput();

    try {
        $pdo->beginTransaction();

        $savedProducts = 0;
        $savedSales = 0;
        $savedTrx = 0;

        // 1. Sync Products
        if (!empty($input['products']) && is_array($input['products'])) {
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
            foreach ($input['products'] as $p) {
                if (empty($p['id'])) continue;
                $stmt->execute([
                    ':id'               => $p['id'],
                    ':name'             => $p['name'] ?? 'Product',
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
                $savedProducts++;
            }
        }

        // 2. Sync Sales
        if (!empty($input['productSales']) && is_array($input['productSales'])) {
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
            foreach ($input['productSales'] as $s) {
                if (empty($s['id'])) continue;
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
                $savedSales++;
            }
        }

        // 3. Sync Transactions
        if (!empty($input['transactions']) && is_array($input['transactions'])) {
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
            foreach ($input['transactions'] as $t) {
                if (empty($t['id'])) continue;
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
                $savedTrx++;
            }
        }

        // 4. Sync Suppliers
        if (!empty($input['suppliers']) && is_array($input['suppliers'])) {
            $sql = "INSERT INTO suppliers (id, name, shop_name, phone, city, balance, notes, created_at)
                    VALUES (:id, :name, :shop_name, :phone, :city, :balance, :notes, :created_at)
                    ON DUPLICATE KEY UPDATE
                        name = VALUES(name),
                        shop_name = VALUES(shop_name),
                        phone = VALUES(phone),
                        city = VALUES(city),
                        balance = VALUES(balance),
                        notes = VALUES(notes)";
            $stmt = $pdo->prepare($sql);
            foreach ($input['suppliers'] as $sp) {
                if (empty($sp['id'])) continue;
                $stmt->execute([
                    ':id'        => $sp['id'],
                    ':name'      => $sp['name'] ?? 'Supplier',
                    ':shop_name' => $sp['shopName'] ?? null,
                    ':phone'     => $sp['phone'] ?? '',
                    ':city'      => $sp['city'] ?? null,
                    ':balance'   => $sp['balance'] ?? 0,
                    ':notes'     => $sp['notes'] ?? null,
                    ':created_at'=> $sp['createdAt'] ?? time() * 1000
                ]);
            }
        }

        // 5. Sync Mobile Purchases
        if (!empty($input['mobilePurchases']) && is_array($input['mobilePurchases'])) {
            $sql = "INSERT INTO mobile_purchases (
                id, receipt_no, date, time, seller_name, seller_phone, seller_cnic,
                seller_city, seller_type, items_json, total_amount, paid_amount,
                balance_due, payment_channel, notes, created_at
            ) VALUES (
                :id, :receipt_no, :date, :time, :seller_name, :seller_phone, :seller_cnic,
                :seller_city, :seller_type, :items_json, :total_amount, :paid_amount,
                :balance_due, :payment_channel, :notes, :created_at
            ) ON DUPLICATE KEY UPDATE
                seller_name = VALUES(seller_name),
                seller_phone = VALUES(seller_phone),
                items_json = VALUES(items_json),
                total_amount = VALUES(total_amount),
                paid_amount = VALUES(paid_amount),
                balance_due = VALUES(balance_due),
                payment_channel = VALUES(payment_channel),
                notes = VALUES(notes)";
            $stmt = $pdo->prepare($sql);
            foreach ($input['mobilePurchases'] as $mp) {
                if (empty($mp['id'])) continue;
                $stmt->execute([
                    ':id'             => $mp['id'],
                    ':receipt_no'      => $mp['receiptNo'] ?? 'PUR-' . time(),
                    ':date'           => $mp['date'] ?? date('Y-m-d'),
                    ':time'           => $mp['time'] ?? date('H:i:s'),
                    ':seller_name'     => $mp['sellerName'] ?? 'Supplier',
                    ':seller_phone'    => $mp['sellerPhone'] ?? null,
                    ':seller_cnic'     => $mp['sellerCnic'] ?? null,
                    ':seller_city'     => $mp['sellerCity'] ?? null,
                    ':seller_type'     => $mp['sellerType'] ?? 'SUPPLIER',
                    ':items_json'      => !empty($mp['items']) ? json_encode($mp['items'], JSON_UNESCAPED_UNICODE) : '[]',
                    ':total_amount'    => $mp['totalAmount'] ?? 0,
                    ':paid_amount'     => $mp['paidAmount'] ?? 0,
                    ':balance_due'     => $mp['balanceDue'] ?? 0,
                    ':payment_channel' => $mp['paymentChannel'] ?? 'CASH',
                    ':notes'          => $mp['notes'] ?? null,
                    ':created_at'      => $mp['createdAt'] ?? time() * 1000
                ]);
            }
        }

        // 6. Sync Settings
        if (!empty($input['settings'])) {
            $sql = "INSERT INTO app_settings (setting_key, setting_value) VALUES ('shop_config', :val)
                    ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([':val' => json_encode($input['settings'], JSON_UNESCAPED_UNICODE)]);
        }

        $pdo->commit();

        sendResponse([
            'savedProducts' => $savedProducts,
            'savedSales'    => $savedSales,
            'savedTrx'      => $savedTrx
        ], 'Data successfully synchronized into MySQL database!');

    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        sendError('Failed to synchronize data into MySQL: ' . $e->getMessage(), 500);
    }
}
