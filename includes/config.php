<?php
// includes/config.php - Konfigurasi aplikasi dan koneksi database
declare(strict_types=1);

/**
 * Muat nilai KEY=VALUE dari file .env tanpa menimpa environment server.
 * Format mendukung baris komentar dan nilai diapit tanda kutip.
 */
function app_load_env_file(string $path): void
{
    if (!is_file($path) || !is_readable($path)) {
        return;
    }

    $lines = file($path, FILE_IGNORE_NEW_LINES);
    if ($lines === false) {
        error_log('Unable to read application environment file.');
        return;
    }

    foreach ($lines as $line) {
        $line = trim($line);

        if ($line === '' || str_starts_with($line, '#')) {
            continue;
        }

        if (str_starts_with($line, 'export ')) {
            $line = trim(substr($line, 7));
        }

        $separator = strpos($line, '=');
        if ($separator === false) {
            continue;
        }

        $key = trim(substr($line, 0, $separator));
        $value = trim(substr($line, $separator + 1));

        if (!preg_match('/^[A-Z_][A-Z0-9_]*$/i', $key)) {
            continue;
        }

        if (
            strlen($value) >= 2
            && (($value[0] === '"' && substr($value, -1) === '"')
                || ($value[0] === "'" && substr($value, -1) === "'"))
        ) {
            $value = substr($value, 1, -1);
        }

        if (getenv($key) === false) {
            putenv($key . '=' . $value);
            $_ENV[$key] = $value;
        }
    }
}

function app_env(string $key, ?string $default = null): ?string
{
    $value = getenv($key);
    if ($value === false) {
        return $default;
    }

    return $value;
}

app_load_env_file(dirname(__DIR__) . '/.env');

$appEnv = strtolower((string) app_env('APP_ENV', 'local'));
$isProduction = $appEnv === 'production';

error_reporting(E_ALL);
ini_set('display_errors', $isProduction ? '0' : '1');
ini_set('log_errors', '1');

date_default_timezone_set(app_env('APP_TIMEZONE', 'Asia/Makassar') ?? 'Asia/Makassar');

if ($isProduction) {
    $requiredEnvironment = ['APP_URL', 'DB_HOST', 'DB_NAME', 'DB_USER', 'DB_PASS'];
    foreach ($requiredEnvironment as $name) {
        if (app_env($name) === null || app_env($name) === '') {
            error_log('Required production environment variable is missing: ' . $name);
            http_response_code(500);
            exit('Konfigurasi server belum lengkap.');
        }
    }
}

$appUrl = rtrim(
    (string) app_env('APP_URL', 'http://localhost/fkip-unimof'),
    '/'
);

$urlParts = parse_url($appUrl);
if (
    $appUrl === ''
    || $urlParts === false
    || !isset($urlParts['scheme'], $urlParts['host'])
    || !in_array(strtolower($urlParts['scheme']), ['http', 'https'], true)
) {
    error_log('Invalid APP_URL configuration.');
    http_response_code(500);
    exit('Konfigurasi aplikasi tidak valid.');
}

$appDir = realpath(dirname(__DIR__));
if ($appDir === false) {
    error_log('Unable to resolve application directory.');
    http_response_code(500);
    exit('Konfigurasi aplikasi tidak valid.');
}

define('DB_HOST', app_env('DB_HOST', 'localhost'));
define('DB_NAME', app_env('DB_NAME', 'fkip'));
define('DB_USER', app_env('DB_USER', 'root'));
define('DB_PASS', app_env('DB_PASS', ''));
define('DB_CHARSET', app_env('DB_CHARSET', 'utf8mb4'));

define('APP_NAME', app_env('APP_NAME', 'FKIP UNIMOF'));
define('APP_URL', $appUrl);
define('APP_VERSION', app_env('APP_VERSION', '2.0.0'));
define('APP_DIR', $appDir);

$httpsRequest = isset($_SERVER['HTTPS'])
    && $_SERVER['HTTPS'] !== ''
    && strtolower((string) $_SERVER['HTTPS']) !== 'off';

$secureCookieSetting = app_env('SESSION_COOKIE_SECURE');
$secureCookie = $secureCookieSetting === null
    ? $httpsRequest
    : filter_var($secureCookieSetting, FILTER_VALIDATE_BOOLEAN);

ini_set('session.use_only_cookies', '1');
ini_set('session.use_strict_mode', '1');
ini_set('session.gc_maxlifetime', '3600');

if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'secure' => $secureCookie,
        'httponly' => true,
        'samesite' => 'Strict',
    ]);

    session_start();
}

try {
    $dsn = sprintf(
        'mysql:host=%s;dbname=%s;charset=%s',
        DB_HOST,
        DB_NAME,
        DB_CHARSET
    );

    $pdo = new PDO($dsn, DB_USER, DB_PASS, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
        PDO::ATTR_PERSISTENT => false,
    ]);
} catch (PDOException $exception) {
    error_log('Database connection failed: ' . $exception->getMessage());
    http_response_code(500);
    exit('Layanan sedang mengalami gangguan. Silakan coba kembali nanti.');
}

require_once __DIR__ . '/security.php';
require_once __DIR__ . '/functions.php';