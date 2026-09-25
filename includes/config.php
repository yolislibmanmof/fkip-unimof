<?php
// includes/config.php - Konfigurasi Database & App
declare(strict_types=1);

// Error reporting (set ke 0 di production)
error_reporting(E_ALL);
ini_set('display_errors', 1); // Ubah ke 1 sementara untuk debugging
ini_set('log_errors', 1);

// Timezone
date_default_timezone_set('Asia/Makassar');

// Database Configuration
define('DB_HOST', 'localhost');
define('DB_NAME', 'fkip');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_CHARSET', 'utf8mb4');

// App Configuration
define('APP_NAME', 'FKIP UNIMOF');
define('APP_URL', 'http://localhost/fkip-unimof');
define('APP_VERSION', '2.0.0');
define('APP_DIR', realpath(__DIR__ . '/..'));

// Session Configuration
ini_set('session.cookie_httponly', 1);
ini_set('session.cookie_secure', 0); 
ini_set('session.use_strict_mode', 1);
ini_set('session.cookie_samesite', 'Strict');
ini_set('session.gc_maxlifetime', 3600);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Database Connection menggunakan PDO
try {
    $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;
    $options = [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
        PDO::ATTR_PERSISTENT => false,
        PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES " . DB_CHARSET
    ];
    
    $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
} catch (PDOException $e) {
    die('Koneksi database gagal: ' . $e->getMessage());
}

// Load security & functions
require_once __DIR__ . '/security.php';
require_once __DIR__ . '/functions.php';