<?php
/**
 * Konfigurasi dan inisialisasi session yang aman
 * Harus dipanggil sebelum session_start()
 */

// Jangan mulai session dua kali
if (session_status() === PHP_SESSION_ACTIVE) {
    return;
}

// Konfigurasi cookie session yang aman
ini_set('session.cookie_httponly', '1');
ini_set('session.cookie_samesite', 'Strict');
ini_set('session.use_strict_mode', '1');
ini_set('session.use_only_cookies', '1');
ini_set('session.gc_maxlifetime', '7200'); // 2 jam

// Aktifkan secure cookie hanya jika HTTPS
if (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') {
    ini_set('session.cookie_secure', '1');
}

session_name('ASHIDDIQ_SESS');
session_start();

// Timeout absolut 2 jam + idle 30 menit
$now = time();
if (!empty($_SESSION['login_at'])) {
    if (($now - (int) $_SESSION['login_at']) > 7200
        || (!empty($_SESSION['last_activity']) && ($now - (int) $_SESSION['last_activity']) > 1800)) {
        $_SESSION = [];
        session_destroy();
        session_start();
    }
}
$_SESSION['last_activity'] = $now;

// Regenerate session ID berkala (15 menit) cegah fixation
if (empty($_SESSION['_initiated']) || empty($_SESSION['_regenerated_at'])
    || ($now - (int) $_SESSION['_regenerated_at']) > 900) {
    session_regenerate_id(true);
    $_SESSION['_initiated'] = true;
    $_SESSION['_regenerated_at'] = $now;
}

/**
 * Rate-limit login per IP+email (file-based, aman shared hosting).
 * False = masih diblokir, true = boleh coba. Panggil SEBELUM cek password.
 */
function checkLoginRateLimit(string $key, int $maxAttempts = 5, int $lockSeconds = 900): bool {
    if (!defined('CACHE_PATH')) return true;
    $now = time();
    $dir = CACHE_PATH . '/ratelimit';
    if (!is_dir($dir)) @mkdir($dir, 0755, true);
    $file = $dir . '/' . preg_replace('/[^a-z0-9_-]/i', '_', $key) . '.json';
    $data = ['count' => 0, 'locked_until' => 0];
    if (is_file($file)) {
        $raw = @file_get_contents($file);
        $j = $raw ? json_decode($raw, true) : null;
        if (is_array($j)) $data = array_merge($data, $j);
    }
    if ($data['locked_until'] > $now) return false;
    // Reset window jika lock kedaluwarsa
    if ($data['locked_until'] > 0 && $data['locked_until'] <= $now) {
        $data = ['count' => 0, 'locked_until' => 0];
        @file_put_contents($file, json_encode($data), LOCK_EX);
    }
    return true;
}

function recordLoginAttempt(string $key, bool $success, int $maxAttempts = 5, int $lockSeconds = 900): void {
    if (!defined('CACHE_PATH')) return;
    $dir = CACHE_PATH . '/ratelimit';
    if (!is_dir($dir)) @mkdir($dir, 0755, true);
    $file = $dir . '/' . preg_replace('/[^a-z0-9_-]/i', '_', $key) . '.json';
    $data = ['count' => 0, 'locked_until' => 0];
    if (is_file($file)) {
        $j = json_decode((string) @file_get_contents($file), true);
        if (is_array($j)) $data = array_merge($data, $j);
    }
    if ($success) {
        @unlink($file);
        return;
    }
    $data['count']++;
    if ($data['count'] >= $maxAttempts) {
        $data['locked_until'] = time() + $lockSeconds;
    }
    @file_put_contents($file, json_encode($data), LOCK_EX);
}

/**
 * Generate atau ambil CSRF token yang ada di session
 */
function generateCsrfToken(): string {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * Validasi CSRF token dari POST request
 * Langsung die() jika token tidak valid
 */
function validateCsrf(): void {
    $sessionToken = $_SESSION['csrf_token'] ?? '';
    $postToken    = $_POST['csrf_token']    ?? '';

    if (!hash_equals($sessionToken, $postToken)) {
        http_response_code(403);
        die('Token keamanan tidak valid. Silakan muat ulang halaman dan coba lagi.');
    }
}

/**
 * Set flash message yang ditampilkan sekali lalu hilang
 */
function setFlash(string $type, string $message): void {
    $_SESSION['flash'][$type] = $message;
}

/**
 * Ambil dan hapus flash message
 * @return array<string, string>
 */
function getFlash(): array {
    $flash = $_SESSION['flash'] ?? [];
    unset($_SESSION['flash']);
    return $flash;
}
