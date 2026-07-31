<?php
/**
 * Konstanta global dan loader environment variables
 * Dipanggil pertama kali oleh index.php
 */

// Cegah akses langsung
if (!defined('ROOT_PATH')) {
    define('ROOT_PATH', dirname(__DIR__));
}

// Load .env jika ada
$envFile = ROOT_PATH . '/.env';
if (file_exists($envFile)) {
    $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '' || str_starts_with($line, '#')) continue;
        if (!str_contains($line, '=')) continue;
        [$key, $value] = explode('=', $line, 2);
        $_ENV[trim($key)] = trim($value);
    }
}

// Konstanta aplikasi
define('APP_NAME',  $_ENV['APP_NAME']  ?? 'Pondok Pesantren Ash-Shiddiq');
define('BASE_URL',  rtrim($_ENV['APP_URL'] ?? 'https://ponpesashiddiq.or.id', '/'));
define('APP_ENV',   $_ENV['APP_ENV']   ?? 'production');
define('IS_DEBUG',  ($_ENV['APP_DEBUG'] ?? 'false') === 'true');

// Path constants
define('UPLOADS_PATH', ROOT_PATH . '/uploads');
define('UPLOADS_URL',  BASE_URL . '/uploads');
define('CACHE_PATH',   ROOT_PATH . '/cache');

// Konfigurasi error reporting
if (IS_DEBUG) {
    error_reporting(E_ALL);
    ini_set('display_errors', '1');
} else {
    error_reporting(0);
    ini_set('display_errors', '0');
    ini_set('log_errors', '1');
    ini_set('error_log', ROOT_PATH . '/logs/error.log');
}

// Timezone Indonesia
date_default_timezone_set('Asia/Jakarta');

// Versi asset untuk cache busting (update saat deploy)
define('ASSET_VERSION', '1.6.0');
