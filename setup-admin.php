<?php
/**
 * Setup Admin — Reset password akun admin
 * PENTING: Hapus file ini segera setelah digunakan!
 */

// Kunci akses — ganti sebelum upload jika perlu
define('SETUP_KEY', 'ashiddiq-setup-2025');

if (($_GET['key'] ?? '') !== SETUP_KEY) {
    http_response_code(403);
    die('<h2>403 Forbidden</h2><p>Tambahkan ?key=ashiddiq-setup-2025 di URL.</p>');
}

require_once __DIR__ . '/config/constants.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/functions.php';

$msg   = '';
$error = '';

// Proses form
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email    = trim($_POST['email']    ?? '');
    $password = trim($_POST['password'] ?? '');
    $name     = trim($_POST['name']     ?? 'Administrator');

    if (empty($email) || empty($password)) {
        $error = 'Email dan password wajib diisi.';
    } elseif (strlen($password) < 8) {
        $error = 'Password minimal 8 karakter.';
    } else {
        try {
            $pdo  = getDB();
            $hash = password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);

            // Cek apakah email sudah ada
            $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
            $stmt->execute([$email]);
            $existing = $stmt->fetchColumn();

            if ($existing) {
                // Update password user yang ada
                $pdo->prepare("UPDATE users SET password = ?, name = ?, role = 'admin', is_active = 1 WHERE email = ?")
                    ->execute([$hash, $name, $email]);
                $msg = "Password akun <strong>" . htmlspecialchars($email) . "</strong> berhasil direset.";
            } else {
                // Insert user baru
                $pdo->prepare("INSERT INTO users (name, email, password, role, is_active) VALUES (?, ?, ?, 'admin', 1)")
                    ->execute([$name, $email, $hash]);
                $msg = "Akun admin <strong>" . htmlspecialchars($email) . "</strong> berhasil dibuat.";
            }
        } catch (PDOException $e) {
            $error = 'Database error: ' . $e->getMessage();
        }
    }
}

// Cek koneksi DB dan tampilkan users yang ada
$users = [];
$dbOk  = false;
try {
    $pdo   = getDB();
    $dbOk  = true;
    $users = $pdo->query("SELECT id, name, email, role, is_active FROM users ORDER BY id")->fetchAll();
} catch (PDOException $e) {
    $error = 'Koneksi DB gagal: ' . $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="robots" content="noindex,nofollow">
<title>Setup Admin</title>
<style>
body { font-family: sans-serif; max-width: 600px; margin: 40px auto; padding: 0 20px; }
h1 { color: #0d7a4a; }
.box { background: #f9f9f9; border: 1px solid #ddd; border-radius: 6px; padding: 20px; margin-bottom: 20px; }
label { display: block; font-size: 13px; font-weight: 600; margin-bottom: 4px; margin-top: 12px; }
input[type=text], input[type=email], input[type=password] {
    width: 100%; padding: 9px 12px; box-sizing: border-box;
    border: 1px solid #ccc; border-radius: 4px; font-size: 14px;
}
button { margin-top: 16px; padding: 10px 24px; background: #0d7a4a; color: white;
    border: none; border-radius: 4px; font-size: 14px; cursor: pointer; }
.ok  { background: #e6f9ee; border: 1px solid #6dcf9a; border-radius: 4px; padding: 12px 16px; color: #1a6b3a; margin-bottom: 16px; }
.err { background: #fff0f0; border: 1px solid #fbb; border-radius: 4px; padding: 12px 16px; color: #c44; margin-bottom: 16px; }
.warn { background: #fff8e0; border: 1px solid #f0c060; border-radius: 4px; padding: 12px 16px; color: #7a5300; margin-bottom: 20px; font-size: 13px; }
table { width: 100%; border-collapse: collapse; font-size: 13px; }
td, th { padding: 8px 10px; border: 1px solid #ddd; }
th { background: #f0f0f0; }
</style>
</head>
<body>

<h1>Setup Admin — Ash-Shiddiq</h1>

<div class="warn">
    ⚠️ <strong>HAPUS FILE INI</strong> segera setelah setup selesai!<br>
    Path: <code>/setup-admin.php</code>
</div>

<div class="box">
    <strong>Status Koneksi Database:</strong>
    <?= $dbOk ? '<span style="color:green">✓ Terhubung</span>' : '<span style="color:red">✗ Gagal</span>' ?>
</div>

<?php if ($msg):  ?><div class="ok"><?= $msg ?></div><?php endif; ?>
<?php if ($error): ?><div class="err"><?= htmlspecialchars($error) ?></div><?php endif; ?>

<div class="box">
    <h3 style="margin-top:0">Reset / Buat Akun Admin</h3>
    <form method="POST" action="?key=<?= SETUP_KEY ?>">
        <label>Nama</label>
        <input type="text" name="name" value="Administrator" required>
        <label>Email</label>
        <input type="email" name="email" value="admin@ponpesashiddiq.or.id" required>
        <label>Password Baru</label>
        <input type="password" name="password" placeholder="Min. 8 karakter" required>
        <button type="submit">Simpan Akun Admin</button>
    </form>
</div>

<?php if ($dbOk && $users): ?>
<div class="box">
    <h3 style="margin-top:0">Users di Database</h3>
    <table>
        <tr><th>ID</th><th>Nama</th><th>Email</th><th>Role</th><th>Aktif</th></tr>
        <?php foreach ($users as $u): ?>
        <tr>
            <td><?= $u['id'] ?></td>
            <td><?= htmlspecialchars($u['name']) ?></td>
            <td><?= htmlspecialchars($u['email']) ?></td>
            <td><?= $u['role'] ?></td>
            <td><?= $u['is_active'] ? '✓' : '✗' ?></td>
        </tr>
        <?php endforeach; ?>
    </table>
</div>
<?php elseif ($dbOk): ?>
<div class="box"><em>Belum ada user di database. Gunakan form di atas untuk membuat admin.</em></div>
<?php endif; ?>

</body>
</html>
