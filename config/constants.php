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
// BASE_URL dikunci ke APP_URL (cegah Host header injection).
// Host request hanya diizinkan jika masuk whitelist eksplisit.
function detectBaseUrl(): string {
    $configured = rtrim($_ENV['APP_URL'] ?? 'https://ponpesashiddiq.or.id', '/');
    if ($configured === '') $configured = 'https://ponpesashiddiq.or.id';
    $cfgHost = strtolower((string) parse_url($configured, PHP_URL_HOST));
    $host = strtolower(trim($_SERVER['HTTP_HOST'] ?? ''));
    $hostBare = preg_replace('/:\d+$/', '', $host);
    $allowed = array_filter([$cfgHost, 'localhost', '127.0.0.1']);
    if ($hostBare !== '' && in_array($hostBare, $allowed, true)
        && preg_match('/^[a-z0-9.-]+(?::\d+)?$/', $host)) {
        if ($hostBare === $cfgHost) return $configured;
        $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        return $scheme . '://' . $host;
    }
    return $configured;
}
define('BASE_URL', detectBaseUrl());
define('APP_ENV',   $_ENV['APP_ENV']   ?? 'production');
define('IS_DEBUG',  ($_ENV['APP_DEBUG'] ?? 'false') === 'true');

// Path constants
define('UPLOADS_PATH', ROOT_PATH . '/uploads');
define('UPLOADS_URL',  BASE_URL . '/uploads');
define('CACHE_PATH',   ROOT_PATH . '/cache');

// Pastikan folder logs & cache ada (hindari 500 saat error_log / ratelimit tulis file)
foreach ([ROOT_PATH . '/logs', ROOT_PATH . '/cache'] as $needDir) {
    if (!is_dir($needDir)) @mkdir($needDir, 0755, true);
}

// Polyfill PHP 8 untuk hosting yang masih PHP 7.4 (hindari fatal undefined function → 500)
if (!function_exists('str_starts_with')) {
    function str_starts_with(string $haystack, string $needle): bool {
        return $needle === '' || strncmp($haystack, $needle, strlen($needle)) === 0;
    }
}
if (!function_exists('str_ends_with')) {
    function str_ends_with(string $haystack, string $needle): bool {
        if ($needle === '') return true;
        $len = strlen($needle);
        return substr($haystack, -$len) === $needle;
    }
}
if (!function_exists('str_contains')) {
    function str_contains(string $haystack, string $needle): bool {
        return $needle === '' || strpos($haystack, $needle) !== false;
    }
}

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
define('ASSET_VERSION', '1.7.1');

// Notifikasi Telegram (token rahasia — hanya di .env, jangan commit)
define('TELEGRAM_BOT_TOKEN', $_ENV['TELEGRAM_BOT_TOKEN'] ?? '');
