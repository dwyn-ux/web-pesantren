<?php
/**
 * Fungsi autentikasi — login, logout, cek sesi
 * Bergantung pada session yang sudah dimulai oleh config/session.php
 */

/**
 * Cek apakah user sudah login
 */
function isLoggedIn(): bool {
    return !empty($_SESSION['user_id']);
}

/**
 * Cek apakah user yang login adalah admin
 */
function isAdmin(): bool {
    return isLoggedIn() && ($_SESSION['user_role'] ?? '') === 'admin';
}

/**
 * Ambil data user yang sedang login
 * @return array<string, mixed>|null
 */
function getCurrentUser(): ?array {
    if (!isLoggedIn()) {
        return null;
    }
    return [
        'id'    => $_SESSION['user_id']   ?? null,
        'name'  => $_SESSION['user_name'] ?? '',
        'email' => $_SESSION['user_email'] ?? '',
        'role'  => $_SESSION['user_role']  ?? 'user',
    ];
}

/**
 * Proses login — validasi kredensial dan set session
 * @return array{success: bool, message: string}
 */
function attemptLogin(string $email, string $password): array {
    $email = trim(strtolower($email));

    if (empty($email) || empty($password)) {
        return ['success' => false, 'message' => 'Email dan password tidak boleh kosong.'];
    }

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return ['success' => false, 'message' => 'Format email tidak valid.'];
    }

    try {
        $pdo  = getDB();
        $stmt = $pdo->prepare("SELECT id, name, email, password, role, is_active FROM users WHERE email = ? LIMIT 1");
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        if (!$user || !password_verify($password, $user['password'])) {
            // Delay untuk mengurangi brute force
            usleep(300000); // 0.3 detik
            return ['success' => false, 'message' => 'Email atau password salah.'];
        }

        if (!$user['is_active']) {
            return ['success' => false, 'message' => 'Akun Anda belum diaktifkan. Hubungi administrator.'];
        }

        // Set session
        session_regenerate_id(true);
        $_SESSION['user_id']    = $user['id'];
        $_SESSION['user_name']  = $user['name'];
        $_SESSION['user_email'] = $user['email'];
        $_SESSION['user_role']  = $user['role'];
        $_SESSION['login_at']   = time();

        return ['success' => true, 'message' => 'Login berhasil.'];

    } catch (PDOException $e) {
        error_log('Login error: ' . $e->getMessage());
        return ['success' => false, 'message' => 'Terjadi kesalahan sistem. Silakan coba lagi.'];
    }
}

/**
 * Logout — hapus session dan redirect ke login
 */
function logout(): void {
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(
            session_name(), '', time() - 42000,
            $params['path'], $params['domain'],
            $params['secure'], $params['httponly']
        );
    }
    session_destroy();
    redirect('/login');
}

/**
 * Paksa halaman ini hanya bisa diakses user yang sudah login
 * Redirect ke login jika belum
 */
function requireLogin(): void {
    if (!isLoggedIn()) {
        setFlash('info', 'Silakan login terlebih dahulu untuk mengakses halaman tersebut.');
        redirect('/login');
    }
}

/**
 * Paksa halaman ini hanya bisa diakses admin
 */
function requireAdmin(): void {
    if (!isAdmin()) {
        http_response_code(403);
        // Jika sudah login tapi bukan admin
        if (isLoggedIn()) {
            setFlash('error', 'Anda tidak memiliki izin untuk mengakses halaman tersebut.');
            redirect('/');
        }
        redirect('/login');
    }
}

/**
 * Redirect ke URL (relatif dari BASE_URL atau absolut)
 */
function redirect(string $path): never {
    $url = str_starts_with($path, 'http') ? $path : BASE_URL . $path;
    header('Location: ' . $url);
    exit;
}
