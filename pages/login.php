<?php
// Jika sudah login, redirect ke admin
if (isLoggedIn()) {
    redirect(isAdmin() ? '/admin' : '/');
}

// ── Proses form POST ──────────────────────────────────────────
$loginError = '';
$emailVal   = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    validateCsrf();

    $email    = sanitizeEmail($_POST['email']    ?? '');
    $password = $_POST['password'] ?? '';
    $emailVal = $email;

    $result = attemptLogin($email, $password);

    if ($result['success']) {
        $redirect = sanitizeString($_GET['redirect'] ?? '');
        // Validasi redirect: hanya izinkan path relatif (cegah open redirect)
        if ($redirect && str_starts_with($redirect, '/') && !str_starts_with($redirect, '//')) {
            redirect($redirect);
        }
        redirect(isAdmin() ? '/admin' : '/');
    } else {
        $loginError = $result['message'];
    }
}

// ── Meta halaman ─────────────────────────────────────────────
$activePage      = '';
$pageTitle       = 'Login Admin | ' . APP_NAME;
$pageDescription = 'Halaman login untuk administrator Pondok Pesantren Ash-Shiddiq.';
$pageCanonical   = BASE_URL . '/login';
$bodyClass       = 'login-page';

$extraHead = <<<'CSS'
<style>
.login-page { background:var(--green-deep); min-height:100vh; display:flex; flex-direction:column; }
.login-page #mainNav { background:rgba(0,0,0,0.2); }
.login-page #mainNav.scrolled { background:rgba(0,0,0,0.4); }

.login-wrap {
    flex:1; display:flex; align-items:center; justify-content:center;
    padding:80px 5% 60px;
}
.login-card {
    background:var(--white); border-radius:4px; padding:48px 40px;
    width:100%; max-width:440px;
    box-shadow:0 20px 60px rgba(0,0,0,0.25);
}
.login-logo {
    text-align:center; margin-bottom:32px;
    padding-bottom:28px; border-bottom:1px solid var(--cream-dark);
}
.login-logo-icon {
    width:64px; height:64px; border-radius:50%;
    background:var(--green-deep); color:var(--gold);
    font-family:'Noto Sans Arabic',sans-serif; font-size:28px;
    display:flex; align-items:center; justify-content:center;
    margin:0 auto 14px;
}
.login-logo h1 {
    font-family:'Plus Jakarta Sans',sans-serif; font-size:22px;
    color:var(--text-dark); margin-bottom:4px;
}
.login-logo p { font-size:13px; color:var(--text-light); }

.login-form .form-group { margin-bottom:20px; }
.login-form label {
    display:block; font-size:12px; font-weight:600; color:var(--text-dark);
    margin-bottom:7px; letter-spacing:0.5px;
}
.login-form .form-control {
    width:100%; padding:12px 14px; box-sizing:border-box;
    border:1.5px solid var(--cream-dark); border-radius:3px;
    font-family:'Plus Jakarta Sans',sans-serif; font-size:15px;
    color:var(--text-dark); background:var(--cream); outline:none;
    transition:border-color 0.3s, background 0.3s;
}
.login-form .form-control:focus { border-color:var(--green-mid); background:white; }
.login-form .form-control.is-error { border-color:#e55; }

.login-error {
    background:#fff0f0; border:1px solid #fcc; border-radius:3px;
    padding:12px 16px; font-size:13px; color:#c44; margin-bottom:20px;
}

.btn-login {
    width:100%; padding:14px; background:var(--green-deep); color:white;
    border:none; border-radius:3px; font-size:15px; font-weight:600;
    cursor:pointer; font-family:'Plus Jakarta Sans',sans-serif;
    transition:all 0.3s;
}
.btn-login:hover { background:var(--green-mid); transform:translateY(-1px); box-shadow:0 6px 20px rgba(13,122,74,0.3); }

.login-footer {
    text-align:center; margin-top:24px; font-size:13px; color:var(--text-light);
}
.login-footer a { color:var(--green-mid); text-decoration:none; }
.login-footer a:hover { text-decoration:underline; }

.password-toggle {
    position:relative;
}
.password-toggle .form-control { padding-right:44px; }
.password-toggle .toggle-btn {
    position:absolute; right:12px; top:50%; transform:translateY(-50%);
    background:none; border:none; cursor:pointer; color:var(--text-light);
    font-size:16px; padding:4px; line-height:1;
}
</style>
CSS;
?>

<div class="login-wrap">
    <div class="login-card">

        <div class="login-logo">
            <div class="login-logo-icon" aria-hidden="true">ص</div>
            <h1><?= e(APP_NAME) ?></h1>
            <p>Panel Administrasi</p>
        </div>

        <?php if ($loginError): ?>
        <div class="login-error" role="alert"><?= e($loginError) ?></div>
        <?php endif; ?>

        <form method="POST" action="<?= e(BASE_URL . '/login') ?>" class="login-form" novalidate>
            <input type="hidden" name="csrf_token" value="<?= generateCsrfToken() ?>">

            <div class="form-group">
                <label for="email">Email</label>
                <input type="email" id="email" name="email" class="form-control"
                       placeholder="admin@ponpesashiddiq.or.id"
                       value="<?= e($emailVal) ?>"
                       autocomplete="email" required autofocus maxlength="150">
            </div>

            <div class="form-group">
                <label for="password">Password</label>
                <div class="password-toggle">
                    <input type="password" id="password" name="password"
                           class="form-control" placeholder="Password Anda"
                           autocomplete="current-password" required maxlength="255">
                    <button type="button" class="toggle-btn"
                            onclick="togglePassword()"
                            aria-label="Tampilkan/sembunyikan password">
                        <span id="toggleIcon">👁</span>
                    </button>
                </div>
            </div>

            <button type="submit" class="btn-login">Masuk</button>
        </form>

        <div class="login-footer">
            <a href="<?= e(BASE_URL . '/') ?>">&larr; Kembali ke Beranda</a>
        </div>
    </div>
</div>

<script>
function togglePassword() {
    var input = document.getElementById('password');
    var icon  = document.getElementById('toggleIcon');
    if (input.type === 'password') {
        input.type = 'text';
        icon.textContent = '🙈';
    } else {
        input.type = 'password';
        icon.textContent = '👁';
    }
}
</script>
