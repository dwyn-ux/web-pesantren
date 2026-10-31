<?php
// Enable detailed error output for debugging (remove in production)
ini_set('display_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/../config/session.php';
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
        'has_job'   => isset($_POST['has_job']) ? '1' : '0',
        'job_title' => $_POST['job_title'] ?? '',
        'job_income'=> $_POST['job_income'] ?? '',
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
    <label><input type="checkbox" name="has_job" id="has_job_chk" <?php echo (!empty($_POST['has_job']) ? 'checked' : ''); ?>> Saya memiliki pekerjaan saat kuliah</label><br><br>
    <div id="job_fields" style="display:none;">
        <label>Nama Pekerjaan:<br>
            <input type="text" name="job_title" maxlength="100" value="<?= htmlspecialchars($_POST['job_title'] ?? '') ?>">
        </label><br><br>
        <label>Penghasilan per bulan (Rp):<br>
            <input type="number" name="job_income" min="0" value="<?= htmlspecialchars($_POST['job_income'] ?? '') ?>">
        </label><br><br>
    </div>
    <button type="submit">Simpan</button>
</form>
<script>
    const chk = document.getElementById('has_job_chk');
    const div = document.getElementById('job_fields');
    function toggle(){ div.style.display = chk.checked ? 'block' : 'none'; }
    chk.addEventListener('change', toggle);
    toggle();
</script>
</body>
</html>
?>