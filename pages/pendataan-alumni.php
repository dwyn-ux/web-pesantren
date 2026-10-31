<?php
require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/../config/session.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/csrf.php';
require_once __DIR__ . '/../includes/alumni.php';

$activePage = 'alumni';
$pageTitle = 'Pendataan Alumni | ' . APP_NAME;
$pageDescription = 'Lengkapi pendataan alumni Pondok Pesantren Ash-Shiddiq agar tetap terhubung dengan keluarga besar pesantren.';
$pageCanonical = BASE_URL . '/pendataan-alumni';

ensureCsrfToken();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrfToken($_POST['csrf_token'] ?? '');
    $alumniData = [
        'name'       => $_POST['name'] ?? '',
        'email'      => $_POST['email'] ?? '',
        'year'       => $_POST['year'] ?? '',
        'major'      => $_POST['major'] ?? '',
        'has_job'    => isset($_POST['has_job']) ? '1' : '0',
        'job_title'  => $_POST['job_title'] ?? '',
        'job_income' => $_POST['job_income'] ?? '',
    ];

    if (addAlumni($alumniData)) {
        $_SESSION['flash_success'] = 'Data alumni berhasil dikirim dan menunggu verifikasi.';
        redirect('/pendataan-alumni');
    }

    ensureCsrfToken();
}

$successMessage = $_SESSION['flash_success'] ?? '';
unset($_SESSION['flash_success']);
$formErrors = $_SESSION['alumni_form_errors'] ?? [];
unset($_SESSION['alumni_form_errors']);
?>
<main class="page-section alumni-entry-page">
  <div class="container alumni-entry-container">
    <header class="alumni-entry-intro">
      <div class="section-tag">
        <span></span><span class="section-tag-text">Keluarga Ash-Shiddiq</span><span></span>
      </div>
      <h1 class="section-title">Pendataan Alumni</h1>
      <p class="section-desc">Mari tetap terhubung. Lengkapi data singkat berikut agar perjalanan dan kabar alumni Ash-Shiddiq dapat tercatat dengan baik.</p>
    </header>

    <?php if ($successMessage !== ''): ?>
      <div class="alumni-form-alert alumni-form-alert-success" role="status"><?= e($successMessage) ?></div>
    <?php endif; ?>

    <?php if ($formErrors): ?>
      <div class="alumni-form-alert alumni-form-alert-error" role="alert">
        <strong>Data belum dapat dikirim.</strong>
        <ul>
          <?php foreach ($formErrors as $formError): ?>
            <li><?= e($formError) ?></li>
          <?php endforeach; ?>
        </ul>
      </div>
    <?php endif; ?>

    <form method="post" class="public-form alumni-entry-form">
      <input type="hidden" name="csrf_token" value="<?= e(generateCsrfToken()) ?>">

      <div class="form-grid">
        <div class="form-group">
          <label for="alumni-name">Nama lengkap</label>
          <input class="form-control" type="text" id="alumni-name" name="name" maxlength="100" autocomplete="name" placeholder="Nama sesuai identitas" required value="<?= e((string) ($_POST['name'] ?? '')) ?>">
        </div>
        <div class="form-group">
          <label for="alumni-email">Email</label>
          <input class="form-control" type="email" id="alumni-email" name="email" maxlength="150" autocomplete="email" placeholder="nama@email.com" required value="<?= e((string) ($_POST['email'] ?? '')) ?>">
        </div>
        <div class="form-group">
          <label for="alumni-year">Tahun lulus</label>
          <input class="form-control" type="number" id="alumni-year" name="year" min="1900" max="2100" inputmode="numeric" placeholder="Contoh: 2022" required value="<?= e((string) ($_POST['year'] ?? '')) ?>">
        </div>
        <div class="form-group">
          <label for="alumni-major">Jurusan <span class="form-optional">(opsional)</span></label>
          <input class="form-control" type="text" id="alumni-major" name="major" maxlength="100" placeholder="Jurusan atau bidang yang ditekuni" value="<?= e((string) ($_POST['major'] ?? '')) ?>">
        </div>
      </div>

      <label class="alumni-job-toggle" for="has_job_chk">
        <input type="checkbox" name="has_job" id="has_job_chk" value="1" <?= !empty($_POST['has_job']) ? 'checked' : '' ?>>
        <span>
          <strong>Saya memiliki pekerjaan</strong>
          <small>Isi informasi pekerjaan secara opsional.</small>
        </span>
      </label>

      <div id="job_fields" class="form-grid alumni-job-fields" hidden>
        <div class="form-group">
          <label for="job_title">Nama pekerjaan <span class="form-optional">(opsional)</span></label>
          <input class="form-control" type="text" name="job_title" id="job_title" maxlength="100" placeholder="Contoh: Guru" value="<?= e((string) ($_POST['job_title'] ?? '')) ?>">
        </div>
        <div class="form-group">
          <label for="job_income">Penghasilan per bulan <span class="form-optional">(opsional)</span></label>
          <div class="alumni-income-input">
            <span>Rp</span>
            <input class="form-control" type="number" name="job_income" id="job_income" min="0" inputmode="numeric" placeholder="0" value="<?= e((string) ($_POST['job_income'] ?? '')) ?>">
          </div>
        </div>
      </div>

      <div class="alumni-form-actions">
        <p>Data akan ditinjau sebelum ditampilkan.</p>
        <button class="btn-primary" type="submit">Kirim Data Alumni</button>
      </div>
    </form>
  </div>
</main>
