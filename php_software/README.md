# LimoMobile POS & EasyPaisa Shop - Full PHP & MySQL Software

This directory contains the standalone PHP & MySQL web software for Balal Mobiles & EasyPaisa Shop.

## Features
- **POS & Billing**: Ultra-fast thermal receipt printing (80mm, 58mm, A4).
- **IMEI Tracking**: Device IMEI logging for warranty and purchase verification.
- **Used Mobile Purchases**: Purchase contract generation with CNIC and device photos.
- **Khata Management**: Customer credit ledger with WhatsApp invoice dispatching.
- **EasyPaisa & Cash Drawer**: Cash In, Cash Out, and commission tracking.
- **Google Drive Cloud**: Direct backup sync and restore from Google Drive.
- **Role-Based Permissions**: Staff PIN management and protected modules.

## How to Deploy
1. Upload all files from this directory to your web server (`public_html` or `htdocs`).
2. Import `db.sql` into your MySQL database using phpMyAdmin.
3. Update database credentials in `backend/config.php`.
4. Open the application in your browser and log in with default PIN `6242`.
