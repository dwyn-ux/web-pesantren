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

// Regenerate session ID secara berkala untuk mencegah session fixation
if (!isset($_SESSION['_initiated'])) {
    session_regenerate_id(true);
    $_SESSION['_initiated'] = true;
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
