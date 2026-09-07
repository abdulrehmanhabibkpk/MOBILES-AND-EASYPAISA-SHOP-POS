# Balal Mobile Shop - PHP & MySQL REST Backend

A standalone, high-performance PHP/MySQL backend for Balal Mobile Shop & EasyPaisa POS system.
Designed specifically for shared hosting like **InfinityFree (cPanel)** and **Hostinger (hPanel)**.

## Directory Structure
```
php-backend/
├── db.sql                     # Full MySQL schema (UTF-8 mb4)
├── config.php                 # Database credentials & PDO connection with CORS
├── .htaccess                  # Apache rewrite & headers configuration
├── INSTRUCTIONS_URDU.md       # Complete step-by-step setup guide in Urdu
├── README.md                  # Technical overview and endpoint specs
└── api/
    ├── health.php             # GET - Check server & DB health
    ├── sync.php               # GET/POST - Complete two-way batch sync
    ├── products.php           # GET/POST/DELETE - Manage inventory & IMEIs
    ├── sales.php              # GET/POST - POS sales & automatic stock reduction
    └── transactions.php       # GET/POST/DELETE - Cash ledger & EasyPaisa records
```

## Endpoints

### 1. Health Check
- **URL**: `GET /api/health.php`
- **Description**: Returns database connection status, counts of products, sales, and transactions.

### 2. Full Sync
- **URL**: `GET /api/sync.php` (Pulls all database records)
- **URL**: `POST /api/sync.php` (Pushes all products, sales, transactions into MySQL with `ON DUPLICATE KEY UPDATE`)

### 3. Products
- **URL**: `GET /api/products.php?q={search}&category={cat}`
- **URL**: `POST /api/products.php` (Insert or update product)
- **URL**: `DELETE /api/products.php?id={id}`

### 4. Sales (POS)
- **URL**: `GET /api/sales.php?date=YYYY-MM-DD`
- **URL**: `POST /api/sales.php` (Inserts sale, reduces product stock, updates IMEI status to SOLD, and records cash ledger transaction)

### 5. Transactions
- **URL**: `GET /api/transactions.php?date=YYYY-MM-DD`
- **URL**: `POST /api/transactions.php`
- **URL**: `DELETE /api/transactions.php?id={id}`
