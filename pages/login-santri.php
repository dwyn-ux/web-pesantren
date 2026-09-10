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
if (isCalonSantri() && getCurrentPendaftaran()) {
    redirect('/portal-santri');
}

// ── Login handler ──────────────────────────────────────────
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    validateCsrf();
    $email = strtolower(trim($_POST['email'] ?? ''));
    $pass  = $_POST['password'] ?? '';

    if (empty($email) || empty($pass)) {
        $error = 'Email dan password tidak boleh kosong.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Format email tidak valid.';
    } else {
        try {
            $pdo = getDB();
            $stmt = $pdo->prepare(
                "SELECT id, name, email, password, role, is_active
                 FROM users WHERE email = ? AND role = 'calon-santri' LIMIT 1"
            );
            $stmt->execute([$email]);
            $user = $stmt->fetch();

            if ($user && password_verify($pass, $user['password']) && $user['is_active']) {
                $cekPendaftaran = $pdo->prepare("SELECT id FROM pendaftaran WHERE user_id = ? LIMIT 1");
                $cekPendaftaran->execute([$user['id']]);
                $pendaftaranRow = $cekPendaftaran->fetch();
                if (!$pendaftaranRow) {
                    $error = 'Akun Anda tidak terhubung ke pendaftaran. Hubungi panitia.';
                } else {
                    session_regenerate_id(true);
                    $_SESSION['user_id']    = $user['id'];
                    $_SESSION['santri_id']  = (int) $pendaftaranRow['id'];
                    $_SESSION['user_name']  = $user['name'];
                    $_SESSION['user_email'] = $user['email'];
                    $_SESSION['user_role']  = $user['role'];
                    $_SESSION['login_at']   = time();
                    redirect('/portal-santri');
                }
            } else {
                usleep(300000);
                $error = 'Email atau password salah.';
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
      <p>Masuk dengan email dan password yang Anda buat saat pendaftaran.</p>
      <?php if ($error): ?>
        <div class="flash-message flash-error"><?= e($error) ?></div>
      <?php endif; ?>
      <div class="form-group">
        <label for="email">Email</label>
        <input class="form-control" type="email" name="email" id="email"
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
