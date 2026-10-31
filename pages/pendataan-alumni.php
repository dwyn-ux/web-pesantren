<?php
require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/../config/session.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/csrf.php';
require_once __DIR__ . '/../includes/alumni.php';

$activePage = 'alumni';
$pageTitle = 'Pendataan Alumni | ' . APP_NAME;
$pageDescription = 'Lengkapi pendataan alumni Pondok Pesantren Ash-Shiddiq.';
$pageCanonical = BASE_URL . '/pendataan-alumni';
$alumniData = [
    'nama' => '',
    'tahun_kelulusan' => '',
    'alamat' => '',
    'aktivitas' => '',
    'tempat_kuliah' => '',
    'jurusan' => '',
    'tempat_bekerja' => '',
    'jabatan' => '',
    'pesan_kesan' => '',
];
$formErrors = [];
$successMessage = $_SESSION['flash_success'] ?? '';
unset($_SESSION['flash_success']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrfToken(is_string($_POST['csrf_token'] ?? null) ? $_POST['csrf_token'] : '');

    $rateKey = 'alumni-submit-' . ($_SERVER['REMOTE_ADDR'] ?? 'unknown');
    $rateLimited = !checkLoginRateLimit($rateKey, 5, 3600);
    if (!$rateLimited) {
        recordLoginAttempt($rateKey, false, 5, 3600);
    }

    foreach ($alumniData as $field => $_) {
        $value = $_POST[$field] ?? '';
        $alumniData[$field] = is_string($value) ? sanitizeString($value) : '';
    }

    $formErrors = $rateLimited
        ? ['Terlalu banyak pengiriman. Coba lagi satu jam lagi.']
        : validateAlumni($alumniData);
    $photo = $_FILES['foto'] ?? [];
    if (!$rateLimited) {
        $uploadError = $photo['error'] ?? UPLOAD_ERR_NO_FILE;
        if ($uploadError === UPLOAD_ERR_NO_FILE) {
            $photoErrors = ['Foto alumni wajib diunggah.'];
        } elseif (in_array($uploadError, [UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE], true)) {
            $photoErrors = ['Ukuran foto maksimal 5 MB.'];
        } else {
            $photoErrors = validateUpload(
                $photo,
                ['jpg', 'jpeg', 'png', 'webp'],
                ['image/jpeg', 'image/png', 'image/webp'],
                5242880
            );
        }
        $formErrors = array_merge($formErrors, $photoErrors);
    }

    if (!$formErrors && !is_uploaded_file($photo['tmp_name'])) {
        $formErrors[] = 'File foto tidak valid.';
    }

    if (!$formErrors) {
        $imageInfo = getimagesize($photo['tmp_name']);
        if ($imageInfo === false || $imageInfo[0] * $imageInfo[1] > 20000000) {
            $formErrors[] = 'Ukuran dimensi foto terlalu besar. Silakan pilih foto lain.';
        } else {
            $extension = match (detectMimeType($photo['tmp_name'])) {
                'image/jpeg' => 'jpg',
                'image/png' => 'png',
                'image/webp' => 'webp',
                default => '',
            };
            $uploadDir = UPLOADS_PATH . '/alumni';
            if ($extension === '') {
                $formErrors[] = 'Format foto tidak didukung.';
            } elseif (!is_dir($uploadDir) && !@mkdir($uploadDir, 0755, true) && !is_dir($uploadDir)) {
                error_log('Tidak dapat membuat direktori upload alumni: ' . $uploadDir);
                $formErrors[] = 'Foto belum dapat disimpan. Silakan coba lagi nanti.';
            } else {
                $photoFilename = bin2hex(random_bytes(16)) . '.' . $extension;
                $photoPath = $uploadDir . DIRECTORY_SEPARATOR . $photoFilename;
                if (!resizeImage($photo['tmp_name'], $photoPath, 1200, 85)) {
                    if (is_file($photoPath)) unlink($photoPath);
                    error_log('Gagal memproses foto alumni yang diunggah.');
                    $formErrors[] = 'Foto tidak dapat diproses oleh server. Silakan gunakan JPG, PNG, atau WebP.';
                } elseif (addAlumni($alumniData, $photoFilename)) {
                    $_SESSION['flash_success'] = 'Data alumni berhasil dikirim dan menunggu verifikasi.';
                    redirect('/pendataan-alumni');
                } else {
                    unlink($photoPath);
                    $formErrors = array_merge($formErrors, $_SESSION['alumni_form_errors'] ?? []);
                    unset($_SESSION['alumni_form_errors']);
                }
            }
        }
    }

    if ($formErrors) {
        $_SESSION['alumni_form_errors'] = $formErrors;
    }
    ensureCsrfToken();
} else {
    $formErrors = $_SESSION['alumni_form_errors'] ?? [];
    unset($_SESSION['alumni_form_errors']);
}
?>
<main class="page-section alumni-entry-page">
  <div class="container alumni-entry-container">
    <header class="alumni-entry-intro">
      <div class="section-tag"><span></span><span class="section-tag-text">Keluarga Ash-Shiddiq</span><span></span></div>
      <h1 class="section-title">Pendataan Alumni</h1>
      <p class="section-desc">Mari tetap terhubung. Lengkapi data dan kabar terbaru Anda sebagai bagian dari keluarga besar Ash-Shiddiq.</p>
    </header>

    <?php if ($successMessage !== ''): ?>
      <div class="alumni-form-alert alumni-form-alert-success" role="status"><?= e($successMessage) ?></div>
    <?php endif; ?>
    <?php if ($formErrors): ?>
      <div class="alumni-form-alert alumni-form-alert-error" role="alert">
        <strong>Data belum dapat dikirim.</strong>
        <ul><?php foreach ($formErrors as $formError): ?><li><?= e($formError) ?></li><?php endforeach; ?></ul>
      </div>
    <?php endif; ?>

    <form method="post" class="public-form alumni-entry-form<?= $formErrors ? ' was-validated' : '' ?>" enctype="multipart/form-data">
      <input type="hidden" name="csrf_token" value="<?= e(generateCsrfToken()) ?>">
      <div class="form-grid">
        <div class="form-group">
          <label for="alumni-name">Nama lengkap</label>
          <input class="form-control" type="text" id="alumni-name" name="nama" maxlength="120" autocomplete="name" required value="<?= e($alumniData['nama']) ?>">
        </div>
        <div class="form-group">
          <label for="alumni-year">Tahun lulus</label>
          <input class="form-control" type="number" id="alumni-year" name="tahun_kelulusan" min="1900" max="<?= e(date('Y')) ?>" inputmode="numeric" required value="<?= e($alumniData['tahun_kelulusan']) ?>">
        </div>
        <div class="form-group alumni-form-full">
          <label for="alumni-address">Alamat</label>
          <textarea class="form-control" id="alumni-address" name="alamat" rows="3" required><?= e($alumniData['alamat']) ?></textarea>
        </div>
        <div class="form-group alumni-form-full">
          <label for="alumni-activity">Kesibukan saat ini</label>
          <select class="form-control" id="alumni-activity" name="aktivitas" required>
            <option value="">Pilih kesibukan</option>
            <option value="kuliah" <?= $alumniData['aktivitas'] === 'kuliah' ? 'selected' : '' ?>>Kuliah</option>
            <option value="bekerja" <?= $alumniData['aktivitas'] === 'bekerja' ? 'selected' : '' ?>>Bekerja</option>
            <option value="kuliah_dan_bekerja" <?= $alumniData['aktivitas'] === 'kuliah_dan_bekerja' ? 'selected' : '' ?>>Kuliah dan bekerja</option>
          </select>
        </div>
        <div class="form-group alumni-form-activity" data-alumni-activity-fields="kuliah,kuliah_dan_bekerja" hidden>
          <label for="alumni-campus">Kuliah di mana?</label>
          <input class="form-control" type="text" id="alumni-campus" name="tempat_kuliah" maxlength="180" data-alumni-required value="<?= e($alumniData['tempat_kuliah']) ?>">
        </div>
        <div class="form-group alumni-form-activity" data-alumni-activity-fields="kuliah,kuliah_dan_bekerja" hidden>
          <label for="alumni-major">Program studi</label>
          <input class="form-control" type="text" id="alumni-major" name="jurusan" maxlength="180" data-alumni-required value="<?= e($alumniData['jurusan']) ?>">
        </div>
        <div class="form-group alumni-form-activity" data-alumni-activity-fields="bekerja,kuliah_dan_bekerja" hidden>
          <label for="alumni-job-title">Bekerja sebagai apa?</label>
          <input class="form-control" type="text" id="alumni-job-title" name="jabatan" maxlength="180" data-alumni-required value="<?= e($alumniData['jabatan']) ?>">
        </div>
        <div class="form-group alumni-form-activity" data-alumni-activity-fields="bekerja,kuliah_dan_bekerja" hidden>
          <label for="alumni-workplace">Bekerja di mana?</label>
          <input class="form-control" type="text" id="alumni-workplace" name="tempat_bekerja" maxlength="180" data-alumni-required value="<?= e($alumniData['tempat_bekerja']) ?>">
        </div>
        <div class="form-group alumni-form-full">
          <label for="alumni-photo">Foto terbaik Anda</label>
          <input class="form-control alumni-file-input" type="file" id="alumni-photo" name="foto" accept=".jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp" required>
          <p class="form-hint">JPG, PNG, atau WebP; maksimal 5 MB. Foto akan ditinjau sebelum ditampilkan.</p>
        </div>
        <div class="form-group alumni-form-full">
          <label for="alumni-message">Pesan dan kesan <span class="form-optional">(opsional)</span></label>
          <textarea class="form-control" id="alumni-message" name="pesan_kesan" rows="3" maxlength="5000"><?= e($alumniData['pesan_kesan']) ?></textarea>
        </div>
      </div>
      <div class="alumni-form-actions">
        <p>Data dan foto ditampilkan setelah diverifikasi oleh pengurus.</p>
        <button class="btn-primary" type="submit">Kirim Data Alumni</button>
      </div>
    </form>
  </div>
</main>
