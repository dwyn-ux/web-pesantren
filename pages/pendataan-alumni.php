<?php
require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/../config/session.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/csrf.php';
require_once __DIR__ . '/../includes/alumni.php';

// Halaman ini publik, tapi pengguna dapat mengisi data alumni.
// Jika ingin hanya admin, tambahkan requireLogin() atau requireAdmin().

ensureCsrfToken();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrfToken($_POST['csrf_token'] ?? '');
    $alumniData = [
        'name'      => $_POST['name'] ?? '',
        'email'     => $_POST['email'] ?? '',
        'year'      => $_POST['year'] ?? '',
        'major'     => $_POST['major'] ?? '',
    ];
    if (addAlumni($alumniData)) {
        $_SESSION['flash_success'] = 'Data alumni berhasil disimpan.';
        redirect('/pendataan-alumni');
    }
    // errors stored in session
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Pendataan Alumni</title>
    <style>
        .error { color:#c00; }
        .success { color:#090; }
    </style>
</head>
<body>
<h1>Pendataan Alumni</h1>
<?php
if (!empty($_SESSION['flash_success'])) {
    echo '<p class="success">' . htmlspecialchars($_SESSION['flash_success']) . '</p>';
    unset($_SESSION['flash_success']);
}
if (!empty($_SESSION['alumni_form_errors'])) {
    echo '<ul class="error">';
    foreach ($_SESSION['alumni_form_errors'] as $e) {
        echo '<li>' . htmlspecialchars($e) . '</li>';
    }
    echo '</ul>';    
    unset($_SESSION['alumni_form_errors']);
}
?>
<form method="post" action="">
    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
    <label>Nama:<br>
        <input type="text" name="name" maxlength="100" required value="<?= htmlspecialchars($_POST['name'] ?? '') ?>">
    </label><br><br>
    <label>Email:<br>
        <input type="email" name="email" maxlength="150" required value="<?= htmlspecialchars($_POST['email'] ?? '') ?>">
    </label><br><br>
    <label>Tahun Lulus:<br>
        <input type="number" name="year" min="1900" max="2100" required value="<?= htmlspecialchars($_POST['year'] ?? '') ?>">
    </label><br><br>
    <label>Jurusan (opsional):<br>
        <input type="text" name="major" maxlength="100" value="<?= htmlspecialchars($_POST['major'] ?? '') ?>">
    </label><br><br>
    <button type="submit">Simpan</button>
</form>
</body>
</html>