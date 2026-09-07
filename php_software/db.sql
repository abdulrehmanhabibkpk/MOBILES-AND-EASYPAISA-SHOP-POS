-- ==========================================================
-- BALAL MOBILE SHOP & EASYPAISA - MYSQL DATABASE SCHEMA
-- Compatible with InfinityFree (cPanel) & Hostinger (hPanel)
-- Character Set: utf8mb4 (Full Urdu / Arabic / English support)
-- ==========================================================

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- ----------------------------------------------------------
-- 1. Table: products (Inventory & Mobile Stock)
-- ----------------------------------------------------------
CREATE TABLE IF NOT EXISTS `products` (
  `id` VARCHAR(100) NOT NULL,
  `name` VARCHAR(255) NOT NULL,
  `category` VARCHAR(50) NOT NULL DEFAULT 'MOBILES',
  `purchase_price` DECIMAL(12, 2) NOT NULL DEFAULT 0.00,
  `sale_price` DECIMAL(12, 2) NOT NULL DEFAULT 0.00,
  `stock` INT NOT NULL DEFAULT 0,
  `image` LONGTEXT NULL,
  `brand_or_model` VARCHAR(150) NULL,
  `imei_or_serial` VARCHAR(100) NULL,
  `sku` VARCHAR(100) NULL,
  `color` VARCHAR(50) NULL,
  `ram_storage` VARCHAR(50) NULL,
  `condition_status` VARCHAR(30) NULL DEFAULT 'NEW',
  `pta_status` VARCHAR(50) NULL DEFAULT 'PTA_APPROVED',
  `battery_health` VARCHAR(20) NULL,
  `warranty` VARCHAR(100) NULL,
  `wattage` VARCHAR(50) NULL,
  `port_type` VARCHAR(50) NULL,
  `compatible_model` VARCHAR(150) NULL,
  `protector_type` VARCHAR(50) NULL,
  `cable_type` VARCHAR(50) NULL,
  `battery_capacity` VARCHAR(50) NULL,
  `units_json` LONGTEXT NULL COMMENT 'JSON array of multi-unit IMEIs, colors, conditions',
  `created_at` BIGINT NOT NULL DEFAULT 0,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  INDEX `idx_category` (`category`),
  INDEX `idx_imei` (`imei_or_serial`),
  INDEX `idx_sku` (`sku`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------
-- 2. Table: product_sales (POS Sales & Invoices)
-- ----------------------------------------------------------
CREATE TABLE IF NOT EXISTS `product_sales` (
  `id` VARCHAR(100) NOT NULL,
  `invoice_no` VARCHAR(50) NOT NULL,
  `date` VARCHAR(20) NOT NULL,
  `time` VARCHAR(20) NOT NULL,
  `customer_name` VARCHAR(150) NOT NULL DEFAULT 'Walk-in Customer',
  `customer_phone` VARCHAR(50) NULL,
  `total_amount` DECIMAL(12, 2) NOT NULL DEFAULT 0.00,
  `discount` DECIMAL(12, 2) NOT NULL DEFAULT 0.00,
  `net_amount` DECIMAL(12, 2) NOT NULL DEFAULT 0.00,
  `total_purchase_cost` DECIMAL(12, 2) NOT NULL DEFAULT 0.00,
  `profit` DECIMAL(12, 2) NOT NULL DEFAULT 0.00,
  `payment_method` VARCHAR(50) NOT NULL DEFAULT 'CASH',
  `notes` TEXT NULL,
  `items_json` LONGTEXT NOT NULL COMMENT 'JSON array of sold line items with quantities, IMEIs',
  `created_at` BIGINT NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  UNIQUE KEY `idx_invoice_no` (`invoice_no`),
  INDEX `idx_date` (`date`),
  INDEX `idx_customer_phone` (`customer_phone`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------
-- 3. Table: transactions (Cash Register & EasyPaisa Ledger)
-- ----------------------------------------------------------
CREATE TABLE IF NOT EXISTS `transactions` (
  `id` VARCHAR(100) NOT NULL,
  `date` VARCHAR(20) NOT NULL,
  `time` VARCHAR(20) NOT NULL,
  `type` VARCHAR(50) NOT NULL COMMENT 'BUY_CASH, SELL_CASH, BUY_EASYPAISA, SELL_EASYPAISA, EXPENSE',
  `customer_name` VARCHAR(150) NOT NULL,
  `customer_phone` VARCHAR(50) NULL,
  `trx_id` VARCHAR(100) NULL,
  `easy_paisa_amount` DECIMAL(12, 2) NOT NULL DEFAULT 0.00,
  `cash_amount` DECIMAL(12, 2) NOT NULL DEFAULT 0.00,
  `fee_profit` DECIMAL(12, 2) NOT NULL DEFAULT 0.00,
  `expense_amount` DECIMAL(12, 2) NOT NULL DEFAULT 0.00,
  `payment_method` VARCHAR(50) NOT NULL DEFAULT 'CASH',
  `notes` TEXT NULL,
  `created_at` BIGINT NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  INDEX `idx_trx_date` (`date`),
  INDEX `idx_trx_type` (`type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------
-- 4. Table: suppliers (Khata & Vendor Balances)
-- ----------------------------------------------------------
CREATE TABLE IF NOT EXISTS `suppliers` (
  `id` VARCHAR(100) NOT NULL,
  `name` VARCHAR(150) NOT NULL,
  `shop_name` VARCHAR(150) NULL,
  `phone` VARCHAR(50) NOT NULL,
  `city` VARCHAR(100) NULL,
  `balance` DECIMAL(12, 2) NOT NULL DEFAULT 0.00 COMMENT 'Positive = Payable (Dena hai), Negative = Advance',
  `notes` TEXT NULL,
  `created_at` BIGINT NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  INDEX `idx_supp_phone` (`phone`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------
-- 4b. Table: supplier_transactions (Khata Entries & Ledger)
-- ----------------------------------------------------------
CREATE TABLE IF NOT EXISTS `supplier_transactions` (
  `id` VARCHAR(100) NOT NULL,
  `supplier_id` VARCHAR(100) NOT NULL,
  `date` VARCHAR(20) NOT NULL,
  `time` VARCHAR(20) NOT NULL,
  `type` VARCHAR(50) NOT NULL COMMENT 'BILL, PAYMENT, RETURN, DISCOUNT',
  `invoice_no` VARCHAR(100) NULL,
  `description` TEXT NULL,
  `amount` DECIMAL(12, 2) NOT NULL DEFAULT 0.00,
  `balance_after` DECIMAL(12, 2) NOT NULL DEFAULT 0.00,
  `payment_method` VARCHAR(50) NOT NULL DEFAULT 'CASH',
  `notes` TEXT NULL,
  `created_at` BIGINT NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  INDEX `idx_sup_trx_date` (`date`),
  INDEX `idx_sup_trx_sup` (`supplier_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------
-- 4c. Table: customers (Customer Udhar & Credit Khata)
-- ----------------------------------------------------------
CREATE TABLE IF NOT EXISTS `customers` (
  `id` VARCHAR(100) NOT NULL,
  `name` VARCHAR(150) NOT NULL,
  `phone` VARCHAR(50) NOT NULL,
  `cnic` VARCHAR(50) NULL,
  `address` TEXT NULL,
  `credit_limit` DECIMAL(12, 2) NOT NULL DEFAULT 0.00,
  `balance` DECIMAL(12, 2) NOT NULL DEFAULT 0.00 COMMENT 'Positive = Due from customer (Lena hai/Udhar), Negative = Advance',
  `notes` TEXT NULL,
  `created_at` BIGINT NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  INDEX `idx_cust_phone` (`phone`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------
-- 4d. Table: customer_transactions (Customer Khata Ledger Entries)
-- ----------------------------------------------------------
CREATE TABLE IF NOT EXISTS `customer_transactions` (
  `id` VARCHAR(100) NOT NULL,
  `customer_id` VARCHAR(100) NOT NULL,
  `date` VARCHAR(20) NOT NULL,
  `time` VARCHAR(20) NOT NULL,
  `type` VARCHAR(50) NOT NULL COMMENT 'UDHAR (Gave/Debit), VASOOLI (Received/Credit), DISCOUNT',
  `invoice_no` VARCHAR(100) NULL,
  `description` TEXT NULL,
  `amount` DECIMAL(12, 2) NOT NULL DEFAULT 0.00,
  `balance_after` DECIMAL(12, 2) NOT NULL DEFAULT 0.00,
  `payment_method` VARCHAR(50) NOT NULL DEFAULT 'CASH',
  `notes` TEXT NULL,
  `created_at` BIGINT NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  INDEX `idx_cust_trx_date` (`date`),
  INDEX `idx_cust_trx_cust` (`customer_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------
-- 5. Table: mobile_purchases (Stock Purchases & Khata Ledger)
-- ----------------------------------------------------------
CREATE TABLE IF NOT EXISTS `mobile_purchases` (
  `id` VARCHAR(100) NOT NULL,
  `receipt_no` VARCHAR(50) NOT NULL,
  `date` VARCHAR(20) NOT NULL,
  `time` VARCHAR(20) NOT NULL,
  `seller_name` VARCHAR(150) NOT NULL,
  `seller_phone` VARCHAR(50) NULL,
  `seller_cnic` VARCHAR(30) NULL,
  `seller_city` VARCHAR(100) NULL,
  `seller_type` VARCHAR(50) NULL DEFAULT 'SUPPLIER',
  `items_json` LONGTEXT NOT NULL,
  `total_amount` DECIMAL(12, 2) NOT NULL DEFAULT 0.00,
  `paid_amount` DECIMAL(12, 2) NOT NULL DEFAULT 0.00,
  `balance_due` DECIMAL(12, 2) NOT NULL DEFAULT 0.00,
  `payment_channel` VARCHAR(50) NOT NULL DEFAULT 'CASH',
  `notes` TEXT NULL,
  `created_at` BIGINT NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  INDEX `idx_purchase_date` (`date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------
-- 6. Table: daily_balances (Opening & Closing Cash Balances)
-- ----------------------------------------------------------
CREATE TABLE IF NOT EXISTS `daily_balances` (
  `date` VARCHAR(20) NOT NULL,
  `opening_cash` DECIMAL(12, 2) NOT NULL DEFAULT 0.00,
  `opening_easypaisa` DECIMAL(12, 2) NOT NULL DEFAULT 0.00,
  `notes` TEXT NULL,
  PRIMARY KEY (`date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------
-- 6b. Table: vault_photos (Photo File Manager & CNIC Vault)
-- ----------------------------------------------------------
CREATE TABLE IF NOT EXISTS `vault_photos` (
  `id` VARCHAR(100) NOT NULL,
  `title` VARCHAR(255) NOT NULL,
  `category` VARCHAR(50) NOT NULL DEFAULT 'CNIC_FRONT' COMMENT 'SELLER, CNIC_FRONT, CNIC_BACK, MOBILE, INVENTORY, RECEIPT, OTHER',
  `ref_no` VARCHAR(100) NULL COMMENT 'Receipt #, Purchase ID or SKU',
  `imei` VARCHAR(50) NULL,
  `customer_name` VARCHAR(150) NULL,
  `phone` VARCHAR(50) NULL,
  `cnic` VARCHAR(50) NULL,
  `filename` VARCHAR(255) NOT NULL,
  `file_path` VARCHAR(255) NOT NULL,
  `file_size_kb` DECIMAL(10, 2) NOT NULL DEFAULT 0.00,
  `notes` TEXT NULL,
  `created_at` BIGINT NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  INDEX `idx_vault_cat` (`category`),
  INDEX `idx_vault_imei` (`imei`),
  INDEX `idx_vault_cnic` (`cnic`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------
-- 7. Table: app_settings (Shop Name, Phone, Address, PIN)
-- ----------------------------------------------------------
CREATE TABLE IF NOT EXISTS `app_settings` (
  `setting_key` VARCHAR(100) NOT NULL,
  `setting_value` LONGTEXT NOT NULL,
  PRIMARY KEY (`setting_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------
-- 8. Table: users (Authentication, Roles & Staff Logins)
-- ----------------------------------------------------------
CREATE TABLE IF NOT EXISTS `users` (
  `id` VARCHAR(100) NOT NULL,
  `name` VARCHAR(150) NOT NULL,
  `email` VARCHAR(150) NOT NULL UNIQUE,
  `password_hash` VARCHAR(255) NOT NULL,
  `pin_code` VARCHAR(20) NOT NULL DEFAULT '6242',
  `role` VARCHAR(50) NOT NULL DEFAULT 'Owner',
  `shop_name` VARCHAR(150) NULL,
  `phone` VARCHAR(50) NULL,
  `created_at` BIGINT NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  INDEX `idx_user_email` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------
-- 9. Table: registered_shops (Multi-Shop Profiles)
-- ----------------------------------------------------------
CREATE TABLE IF NOT EXISTS `registered_shops` (
  `id` VARCHAR(100) NOT NULL,
  `shop_name` VARCHAR(150) NOT NULL,
  `owner_name` VARCHAR(150) NOT NULL,
  `phone` VARCHAR(50) NOT NULL,
  `email` VARCHAR(150) NULL,
  `city` VARCHAR(100) NULL,
  `address` TEXT NULL,
  `pin_code` VARCHAR(20) NOT NULL DEFAULT '6242',
  `thermal_size` VARCHAR(20) NOT NULL DEFAULT '80mm',
  `opening_cash` DECIMAL(12, 2) NOT NULL DEFAULT 0.00,
  `opening_easypaisa` DECIMAL(12, 2) NOT NULL DEFAULT 0.00,
  `created_at` BIGINT NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Default admin user seed
INSERT INTO `users` (`id`, `name`, `email`, `password_hash`, `pin_code`, `role`, `shop_name`, `phone`, `created_at`) VALUES
('usr-admin-01', 'بلال خان (مالک)', 'admin@balalmobile.com', '$2y$10$eW4E5y2xV0Y7j2W2vN3tCeY4qjV4Z0P9tK1rA8wM7L3gB4cE5h6I7', '6242', 'Owner', 'بلال موبائلز اینڈ ایزی پیسہ شاپ', '0300-1234567', 1788700000)
ON DUPLICATE KEY UPDATE `name`=`name`;

-- Default settings seed
INSERT INTO `app_settings` (`setting_key`, `setting_value`) VALUES
('shop_config', '{"shopName":"بلال موبائلز اینڈ ایزی پیسہ شاپ","ownerName":"بلال خان","phone":"0300-1234567","address":"مین بازار، پاکستان","pinCode":"6242","theme":"light","language":"ur","thermalSize":"80mm"}')
ON DUPLICATE KEY UPDATE `setting_key`=`setting_key`;

-- ----------------------------------------------------------
-- Seed Products for instant testing in POS and Inventory
-- ----------------------------------------------------------
INSERT INTO `products` (`id`, `name`, `category`, `purchase_price`, `sale_price`, `stock`, `brand_or_model`, `imei_or_serial`, `sku`, `color`, `ram_storage`, `units_json`, `created_at`) VALUES
('prod-samsung-a15', 'Samsung Galaxy A15 (6GB / 128GB)', 'MOBILES', 35500.00, 38500.00, 3, 'Samsung', '356789123456781', 'SAM-A15-BLK', 'Black', '6GB/128GB', '[{"id":"u1","imei1":"356789123456781","imei2":"356789123456782","color":"Black","storageRam":"6GB/128GB","condition":"Box Pack (New)","status":"AVAILABLE"},{"id":"u2","imei1":"356789123456783","imei2":"356789123456784","color":"Blue","storageRam":"6GB/128GB","condition":"Box Pack (New)","status":"AVAILABLE"},{"id":"u3","imei1":"356789123456785","imei2":"356789123456786","color":"Silver","storageRam":"6GB/128GB","condition":"Box Pack (New)","status":"AVAILABLE"}]', 1700000001),
('prod-redmi-note13', 'Redmi Note 13 (8GB / 256GB)', 'MOBILES', 41500.00, 44999.00, 2, 'Xiaomi / Redmi', '864231098765431', 'RED-N13-GRN', 'Midnight Black', '8GB/256GB', '[{"id":"u4","imei1":"864231098765431","imei2":"864231098765432","color":"Black","storageRam":"8GB/256GB","condition":"Box Pack (New)","status":"AVAILABLE"},{"id":"u5","imei1":"864231098765433","imei2":"864231098765434","color":"Green","storageRam":"8GB/256GB","condition":"Box Pack (New)","status":"AVAILABLE"}]', 1700000002),
('prod-infinix-hot40', 'Infinix Hot 40 Pro (8GB / 256GB)', 'MOBILES', 32000.00, 34999.00, 4, 'Infinix', '351234987654321', 'INF-H40-GLD', 'Gold / Black', '8GB/256GB', '[{"id":"u6","imei1":"351234987654321","imei2":"351234987654322","color":"Starlit Black","storageRam":"8GB/256GB","condition":"Box Pack (New)","status":"AVAILABLE"}]', 1700000003),
('prod-charger-25w', 'Samsung 25W Type-C Super Fast Adapter', 'CHARGERS', 850.00, 1450.00, 24, 'Samsung', '', 'CHG-25W-SAM', 'White', '', '[]', 1700000004),
('prod-charger-33w', 'Xiaomi / Realme 33W Fast Dart Charger + Cable', 'CHARGERS', 1100.00, 1850.00, 16, 'Xiaomi', '', 'CHG-33W-XIA', 'White', '', '[]', 1700000005),
('prod-ronin-r9', 'Ronin R-9 Crystal Clear Bass Handsfree', 'EARPHONES', 350.00, 650.00, 35, 'Ronin', '', 'HF-RONIN-R9', 'Black', '', '[]', 1700000006),
('prod-airbuds-tws', 'Audionic Airbud 425 Wireless Bluetooth TWS', 'EARPHONES', 2400.00, 3300.00, 8, 'Audionic', '', 'TWS-AUD-425', 'White', '', '[]', 1700000007),
('prod-cable-65w', '6A 65W Braided Fast Type-C Data Cable', 'CABLES', 180.00, 450.00, 40, 'Faster', '', 'CAB-6A-FAST', 'Black/Red', '', '[]', 1700000008),
('prod-glass-9d', '9D Curved Full Edge Matte Glass Protector', 'PROTECTORS', 90.00, 300.00, 50, 'Universal', '', 'GLS-9D-MAT', 'Clear', '', '[]', 1700000009),
('prod-cover-silicone', 'Premium Silicone Shockproof Camera Protection Cover', 'COVERS', 180.00, 450.00, 30, 'Generic', '', 'CVR-SIL-SHK', 'Assorted', '', '[]', 1700000010)
ON DUPLICATE KEY UPDATE `name`=`name`;

-- ----------------------------------------------------------
-- Seed Sample Sales for Sales & Profit Ledger
-- ----------------------------------------------------------
INSERT INTO `product_sales` (`id`, `invoice_no`, `date`, `time`, `customer_name`, `customer_phone`, `total_amount`, `discount`, `net_amount`, `total_purchase_cost`, `profit`, `payment_method`, `paid_amount`, `due_amount`, `items_json`, `notes`, `created_at`) VALUES
('sale-demo-001', 'INV-100241', '2026-09-07', '11:30 AM', 'محمد عثمان', '0301-2345678', 40000.00, 500.00, 39500.00, 35500.00, 4000.00, 'CASH', 39500.00, 0.00, '[{"productId":"prod-samsung-a15","productName":"Samsung Galaxy A15 (6GB / 128GB)","quantity":1,"unitPurchasePrice":35500,"unitSalePrice":38500,"totalSalePrice":38500,"selectedImei1":"356789123456781","selectedColor":"Black"},{"productId":"prod-glass-9d","productName":"9D Curved Full Edge Matte Glass Protector","quantity":1,"unitPurchasePrice":90,"unitSalePrice":300,"totalSalePrice":300},{"productId":"prod-cover-silicone","productName":"Premium Silicone Shockproof Camera Protection Cover","quantity":1,"unitPurchasePrice":180,"unitSalePrice":450,"totalSalePrice":450}]', 'Box pack with glass protector and back cover', 1788777000),
('sale-demo-002', 'INV-100242', '2026-09-07', '01:15 PM', 'طارق محمود', '0345-9876543', 3300.00, 100.00, 3200.00, 2400.00, 800.00, 'EASYPAISA', 3200.00, 0.00, '[{"productId":"prod-airbuds-tws","productName":"Audionic Airbud 425 Wireless Bluetooth TWS","quantity":1,"unitPurchasePrice":2400,"unitSalePrice":3300,"totalSalePrice":3300}]', 'Paid via EasyPaisa', 1788783300),
('sale-demo-003', 'INV-100243', '2026-09-06', '04:45 PM', 'حاجی رشید احمد', '0321-5551234', 45000.00, 1000.00, 44000.00, 41500.00, 2500.00, 'BANK', 44000.00, 0.00, '[{"productId":"prod-redmi-note13","productName":"Redmi Note 13 (8GB / 256GB)","quantity":1,"unitPurchasePrice":41500,"unitSalePrice":44999,"totalSalePrice":44999,"selectedImei1":"864231098765431","selectedColor":"Midnight Black"}]', 'Bank Transfer received', 1788700000),
('sale-demo-004', 'INV-100244', '2026-09-05', '06:20 PM', 'وقار علی خان', '0333-7778899', 3750.00, 150.00, 3600.00, 2130.00, 1470.00, 'CASH', 3600.00, 0.00, '[{"productId":"prod-charger-25w","productName":"Samsung 25W Type-C Super Fast Adapter","quantity":1,"unitPurchasePrice":850,"unitSalePrice":1450,"totalSalePrice":1450},{"productId":"prod-ronin-r9","productName":"Ronin R-9 Crystal Clear Bass Handsfree","quantity":1,"unitPurchasePrice":350,"unitSalePrice":650,"totalSalePrice":650},{"productId":"prod-cable-65w","productName":"6A 65W Braided Fast Type-C Data Cable","quantity":1,"unitPurchasePrice":180,"unitSalePrice":450,"totalSalePrice":450}]', 'Original fast charger combo', 1788610000)
ON DUPLICATE KEY UPDATE `invoice_no`=`invoice_no`;

-- ----------------------------------------------------------
-- Seed Sample Customers & Khata Transactions
-- ----------------------------------------------------------
INSERT INTO `customers` (`id`, `name`, `phone`, `cnic`, `address`, `credit_limit`, `balance`, `notes`, `created_at`) VALUES
('cust-1', 'محمد عثمان (Usman Khan)', '0300-7654321', '36302-1234567-1', 'محلہ عیدگاہ، ملتان', 30000.00, 8500.00, 'پرانا گاہک، ہر ماہ کی 5 تاریخ کو بقایا ادھار صاف کرتا ہے', 1700000011),
('cust-2', 'طارق محمود (Tariq Mehmood)', '0321-9876543', '36302-8877665-3', 'نزد ریلوے روڈ، خانیوال', 50000.00, 14200.00, 'ریڈمی نوٹ 13 موبائل قسط / ادھار', 1700000012),
('cust-3', 'بلال شاہ (Bilal Shah)', '0345-1234567', '36302-5432109-7', 'مین بازار، لودھراں', 20000.00, 0.00, 'تمام ادھار کلئیر ہے', 1700000013),
('cust-4', 'حمزہ علی ڈرائیور (Hamza Ali)', '0312-5556677', '36302-4433221-5', 'شجاع آباد', 15000.00, -1500.00, 'ایڈوانس رقم جمع کروائی ہوئی ہے', 1700000014)
ON DUPLICATE KEY UPDATE `name`=`name`;

INSERT INTO `customer_transactions` (`id`, `customer_id`, `date`, `time`, `type`, `invoice_no`, `description`, `amount`, `balance_after`, `payment_method`, `notes`, `created_at`) VALUES
('chk-1', 'cust-1', '2026-09-03', '11:30:00', 'UDHAR', NULL, 'سام سنگ 25 واٹ چارجر + ٹائپ سی کیبل اور نقد ادھار دیا', 12000.00, 12000.00, 'CASH', 'دوست کے حوالے سامان لیا', 1788500000),
('chk-2', 'cust-1', '2026-09-05', '16:15:00', 'VASOOLI', NULL, 'ایزی پیسہ کے ذریعے جزوی رقم ادا کی', 3500.00, 8500.00, 'EASYPAISA', 'TID: 8872194510', 1788680000),
('chk-3', 'cust-2', '2026-08-30', '14:00:00', 'UDHAR', 'INV-1092', 'ریڈمی نوٹ 13 نیا موبائل - بقایا قسط کھاتہ', 24200.00, 24200.00, 'CASH', 'کل قیمت 44000، 20000 نقد دیا تھا', 1788200000),
('chk-4', 'cust-2', '2026-09-06', '18:45:00', 'VASOOLI', NULL, 'دکان پر نقد آ کر قسط وصول کروائی', 10000.00, 14200.00, 'CASH', 'رسید جاری کی گئی', 1788770000)
ON DUPLICATE KEY UPDATE `amount`=`amount`;

SET FOREIGN_KEY_CHECKS = 1;

