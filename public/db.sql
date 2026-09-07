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
-- 7. Table: app_settings (Shop Name, Phone, Address, PIN)
-- ----------------------------------------------------------
CREATE TABLE IF NOT EXISTS `app_settings` (
  `setting_key` VARCHAR(100) NOT NULL,
  `setting_value` LONGTEXT NOT NULL,
  PRIMARY KEY (`setting_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Default settings seed
INSERT INTO `app_settings` (`setting_key`, `setting_value`) VALUES
('shop_config', '{"shopName":"Balal Mobile & EasyPaisa","ownerName":"Bilal Khan","phone":"0300-1234567","address":"Main Bazar, Pakistan","pinCode":"6242","theme":"dark","language":"roman"}')
ON DUPLICATE KEY UPDATE `setting_key`=`setting_key`;

SET FOREIGN_KEY_CHECKS = 1;
