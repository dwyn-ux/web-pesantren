<?php
// ── Logout handler ─────────────────────────────────────────
if (isset($_GET['logout'])) {
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $p = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
    }
    session_destroy();
    redirect('/login-santri');
}

// Kalau sudah login sebagai calon-santri, langsung ke portal
// (ke step pertama yang belum lengkap kalau wizard belum tuntas)
if (isCalonSantri() && getCurrentPendaftaran()) {
    redirect(portalAwalUrl());
}

// ── Login handler ──────────────────────────────────────────
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    validateCsrf();
    $login = strtolower(trim($_POST['email'] ?? ''));
    $pass  = $_POST['password'] ?? '';

    if (empty($login) || empty($pass)) {
        $error = 'Email/nomor induk dan password tidak boleh kosong.';
    } elseif (strlen($login) > 150) {
        $error = 'Format email/nomor induk tidak valid.';
    } else {
        $rateKey = 'login-santri-' . md5(($_SERVER['REMOTE_ADDR'] ?? 'x') . '|' . $login);
        if (function_exists('checkLoginRateLimit') && !checkLoginRateLimit($rateKey)) {
            $error = 'Terlalu banyak percobaan. Coba lagi 15 menit.';
        } else try {
            $pdo = getDB();
            // Login memakai email, nomor induk, atau nomor pendaftaran.
            // Ketiganya sudah unique di DB (uk_email, uk_nomor_induk, nomor_daftar).
            $stmt = $pdo->prepare(
                "SELECT u.id, u.name, u.email, u.password, u.role, u.is_active, p.id AS pendaftaran_id
                 FROM users u
                 LEFT JOIN pendaftaran p ON p.user_id = u.id
                 WHERE u.role = 'calon-santri'
                   AND (u.email = ? OR p.nomor_induk = ? OR p.nomor_daftar = ?)
                 LIMIT 1"
            );
            $stmt->execute([$login, $login, $login]);
            $user = $stmt->fetch();

            $ok = $user && $user['is_active'] && password_verify($pass, $user['password']);
            if (function_exists('recordLoginAttempt')) recordLoginAttempt($rateKey, (bool) $ok);
            if ($ok) {
                $pendaftaranId = (int) ($user['pendaftaran_id'] ?? 0);
                if ($pendaftaranId <= 0) {
                    $error = 'Akun Anda tidak terhubung ke pendaftaran. Hubungi panitia.';
                } else {
                    session_regenerate_id(true);
                    $_SESSION['user_id']    = $user['id'];
                    $_SESSION['santri_id']  = $pendaftaranId;
                    $_SESSION['user_name']  = $user['name'];
                    $_SESSION['user_email'] = $user['email'];
                    $_SESSION['user_role']  = $user['role'];
                    $_SESSION['login_at']   = time();
                    redirect(portalAwalUrl());
                }
            } else {
                usleep(300000);
                $error = 'Email/nomor induk atau password salah.';
            }
        } catch (PDOException $e) {
            error_log('Login-santri error: ' . $e->getMessage());
            $error = 'Terjadi kesalahan sistem. Silakan coba lagi.';
        }
    }
}

$activePage      = 'psb';
$pageTitle       = 'Login Portal Santri | ' . APP_NAME;
$pageDescription = 'Login ke Portal Santri Pondok Pesantren Ash-Shiddiq untuk melengkapi data dan mengunggah berkas.';
$pageCanonical   = BASE_URL . '/login-santri';
$bodyClass       = 'login-santri-page';
?>
<main class="page-section">
  <div class="container narrow-container">
    <form method="post" class="public-form portal-login">
      <input type="hidden" name="csrf_token" value="<?= generateCsrfToken() ?>">
      <h1 class="section-title">Portal Santri</h1>
      <p>Masuk dengan email, nomor induk, atau nomor pendaftaran dan password yang Anda buat saat pendaftaran.</p>
      <?php if ($error): ?>
        <div class="flash-message flash-error"><?= e($error) ?></div>
      <?php endif; ?>
      <div class="form-group">
        <label for="email">Email / Nomor Induk / Nomor Pendaftaran</label>
        <input class="form-control" type="text" name="email" id="email"
               required autocomplete="username" autofocus
               value="<?= e($_POST['email'] ?? '') ?>">
      </div>
      <div class="form-group">
        <label for="password">Password</label>
        <input class="form-control" type="password" name="password" id="password"
               required autocomplete="current-password">
      </div>
      <label class="form-note" style="display:flex;align-items:center;gap:6px;cursor:pointer;margin-bottom:16px;">
        <input type="checkbox" id="lihatPassword" style="width:auto;"> Lihat password
      </label>
      <button class="btn-primary">Masuk ke Portal</button>
      <div class="login-register-prompt">
        <span>Belum memiliki akun?</span>
        <a href="<?= BASE_URL ?>/psb">Daftar sebagai calon santri</a>
      </div>
    </form>
  </div>
</main>
<script>
(function () {
  var lihatPassword = document.getElementById('lihatPassword');
  if (lihatPassword) {
    lihatPassword.addEventListener('change', function () {
      document.getElementById('password').type = this.checked ? 'text' : 'password';
    });
  }
}());
</script>
