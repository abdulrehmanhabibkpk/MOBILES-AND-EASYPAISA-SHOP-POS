import JSZip from 'jszip';
import { saveAs } from 'file-saver';

/**
 * Downloads a complete, production-ready deployable ZIP containing all compiled 
 * frontend SPA assets, PHP APIs, db.sql, and .htaccess ready to be dropped into 
 * InfinityFree (htdocs) or Hostinger (public_html).
 */
export async function downloadHostingDeploymentZip(): Promise<boolean> {
  try {
    // Strategy 1: Try server-side pre-packaged complete bundle endpoint
    try {
      const serverRes = await fetch('/api/download-hosting-zip');
      if (serverRes.ok) {
        const blob = await serverRes.blob();
        if (blob && blob.size > 10000) {
          saveAs(blob, 'balal_pos_hosting_ready.zip');
          return true;
        }
      }
    } catch (e) {
      console.warn('Server zip endpoint fallback:', e);
    }

    // Strategy 2: Client-side dynamic package with explicit asset list
    const zip = new JSZip();

    // 1. Fetch index.html
    const indexHtmlRes = await fetch('/index.html?t=' + Date.now());
    let indexHtml = await indexHtmlRes.text();
    zip.file('index.html', indexHtml);

    // 2. Fetch manifest & icons
    try {
      const manifestRes = await fetch('/manifest.webmanifest');
      if (manifestRes.ok) zip.file('manifest.webmanifest', await manifestRes.text());
      
      const iconRes = await fetch('/icon.svg');
      if (iconRes.ok) zip.file('icon.svg', await iconRes.blob());

      const pwa192 = await fetch('/pwa-192x192.svg');
      if (pwa192.ok) zip.file('pwa-192x192.svg', await pwa192.blob());

      const pwa512 = await fetch('/pwa-512x512.svg');
      if (pwa512.ok) zip.file('pwa-512x512.svg', await pwa512.blob());
    } catch (e) {
      console.warn('Icon fetch warning:', e);
    }

    // 3. Known & extracted JS and CSS asset files
    const assetsFolder = zip.folder('assets');
    const assetUrls = new Set<string>([
      'assets/index-40fIVVrD.js',
      'assets/index-Vc1UaFh8.css',
      'assets/html2canvas.esm-QH1iLAAe.js',
      'assets/index.es-BFdyEuBc.js',
      'assets/purify.es-Jn2rvFN8.js',
      'assets/workbox-window.prod.es5-BBnX5xw4.js'
    ]);

    const assetMatches = indexHtml.matchAll(/(?:src|href)=["'](?:\.\/|\/)?(assets\/[^"'\s]+)["']/g);
    for (const match of assetMatches) {
      if (match[1]) {
        assetUrls.add(match[1]);
      }
    }

    for (const assetPath of assetUrls) {
      try {
        const urlToFetch = assetPath.startsWith('/') ? assetPath : '/' + assetPath;
        const res = await fetch(urlToFetch);
        if (res.ok) {
          const fileName = assetPath.replace(/^assets\//, '').replace(/^\/assets\//, '');
          const blob = await res.blob();
          assetsFolder?.file(fileName, blob);
        }
      } catch (err) {
        console.warn('Could not bundle asset:', assetPath, err);
      }
    }

    // 4. Add PHP Backend files
    const configPhpContent = `<?php
/**
 * Balal Mobile & EasyPaisa POS - Database Configuration
 * Compatible with InfinityFree & Hostinger
 */

header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With");
header("Content-Type: application/json; charset=UTF-8");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// ⚠️ CHANGE THESE 4 VALUES TO MATCH YOUR HOSTING MYSQL DATABASE:
$DB_HOST = 'localhost';          // For InfinityFree: sqlXXX.epizy.com | For Hostinger: localhost
$DB_NAME = 'balal_mobile_db';    // Your Database Name
$DB_USER = 'root';               // Your Database Username
$DB_PASS = '';                   // Your Database Password
$DB_PORT = 3306;

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
`;
    zip.file('config.php', configPhpContent);

    // .htaccess for Apache / cPanel / hPanel
    const htaccessContent = `<IfModule mod_rewrite.c>
    RewriteEngine On
    RewriteCond %{HTTP:Authorization} .
    RewriteRule .* - [E=HTTP_AUTHORIZATION:%{HTTP:Authorization}]

    RewriteCond %{REQUEST_FILENAME} !-f
    RewriteCond %{REQUEST_FILENAME} !-d
    RewriteCond %{REQUEST_URI} !^/api/
    RewriteCond %{REQUEST_URI} !^.*\\.php$
    RewriteRule ^ index.html [L]

    Options -Indexes
</IfModule>

<IfModule mod_headers.c>
    Header set Access-Control-Allow-Origin "*"
    Header set Access-Control-Allow-Methods "GET, POST, PUT, DELETE, OPTIONS"
    Header set Access-Control-Allow-Headers "Content-Type, Authorization, X-Requested-With"
</IfModule>

AddDefaultCharset UTF-8
`;
    zip.file('.htaccess', htaccessContent);

    // API endpoints
    const apiFolder = zip.folder('api');
    
    // health.php
    apiFolder?.file('health.php', `<?php
if (file_exists(__DIR__ . '/../config.php')) {
    require_once __DIR__ . '/../config.php';
} elseif (file_exists(__DIR__ . '/config.php')) {
    require_once __DIR__ . '/config.php';
} else {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'config.php not found']);
    exit();
}

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
        'server_time' => date('Y-m-d H:i:s')
    ], 'Balal Mobile POS Backend is connected to MySQL!');
} catch (Exception $e) {
    sendError('Database connection error: ' . $e->getMessage(), 500);
}
`);

    // Fetch api files from public/api
    try {
      const pRes = await fetch('/api/products.php');
      if (pRes.ok) apiFolder?.file('products.php', await pRes.text());
      const sRes = await fetch('/api/sales.php');
      if (sRes.ok) apiFolder?.file('sales.php', await sRes.text());
      const syRes = await fetch('/api/sync.php');
      if (syRes.ok) apiFolder?.file('sync.php', await syRes.text());
      const tRes = await fetch('/api/transactions.php');
      if (tRes.ok) apiFolder?.file('transactions.php', await tRes.text());
    } catch (e) {
      console.warn('API fetch warning:', e);
    }

    // Add MySQL database schema
    try {
      const sqlRes = await fetch('/db.sql');
      if (sqlRes.ok) zip.file('db.sql', await sqlRes.text());
    } catch (e) {
      console.warn('SQL fetch warning:', e);
    }

    // Urdu Instructions file
    const urduReadme = `# بلال موبائلز اینڈ ایزی پیسہ شاپ - ہوسٹنگ انسٹالیشن گائیڈ

1. اپنے cPanel یا hPanel میں نیا MySQL ڈیٹا بیس بنائیں۔
2. phpMyAdmin میں جا کر db.sql فائل کو Import کریں۔
3. config.php فائل کو ایڈٹ کر کے اپنے ڈیٹا بیس کا نام، یوزر اور پاس ورڈ درج کریں۔
4. اس زپ فائل کی تمام فائلیں (بشمول assets فولڈر) اپنے htdocs (InfinityFree) یا public_html (Hostinger) میں اپلوڈ کر دیں۔
5. اب اپنا ڈومین براؤزر میں کھولیں، آپ کا پورا سوفٹ ویئر 100% لائیو چل پڑے گا!
`;
    zip.file('README_URDU.txt', urduReadme);

    // 5. Generate and download ZIP
    const content = await zip.generateAsync({ type: 'blob' });
    saveAs(content, 'balal_pos_hosting_ready.zip');
    return true;
  } catch (error) {
    console.error('Error creating deployable ZIP:', error);
    return false;
  }
}
