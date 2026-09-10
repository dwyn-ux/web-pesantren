<?php
/**
 * Upload Berkas Tambahan — SKL, KIP/PIP, Ijazah.
 * Halaman mandiri di luar wizard portal (bisa diakses kapan saja).
 * Upload via AJAX ke /api/upload-berkas.php memakai kompresi client-side.
 */
requireCalonSantri();

$pdo = getDB();
$pendaftaran = getCurrentPendaftaran();
if (!$pendaftaran) {
    redirect('/psb');
}
$pendaftaranId = (int) $pendaftaran['id'];

$berkasStmt = $pdo->prepare(
    'SELECT id, jenis, nama_file, mime_type, created_at AS uploaded_at FROM berkas_santri
     WHERE pendaftaran_id = ? ORDER BY id'
);
$berkasStmt->execute([$pendaftaranId]);
$berkasByJenis = [];
foreach ($berkasStmt->fetchAll() as $b) {
    $berkasByJenis[$b['jenis']] = $b;
}

$berkasTambahanList = [
    'skl'    => 'SKL / Surat Keterangan Lulus',
    'kip'    => 'KIP / PIP / Kartu Indonesia Pintar (bila ada)',
    'ijazah' => 'Ijazah (boleh menyusul setelah lulus)',
];

$bolehUpload = in_array($pendaftaran['status'], ['pending', 'menunggu-verifikasi', 'diterima', 'daftar-ulang'], true);

$activePage      = 'berkas-santri';
$pageTitle       = 'Upload Berkas Tambahan — ' . APP_NAME;
$pageDescription = 'Upload berkas tambahan: SKL, KIP/PIP, dan Ijazah.';
$pageCanonical   = BASE_URL . '/upload-berkas';
$bodyClass       = 'portal-santri-page';
$extraHead = '<link rel="stylesheet" href="' . BASE_URL . '/assets/css/portal.css?v=' . ASSET_VERSION . '">'
    . '<script src="' . BASE_URL . '/assets/js/kompres-gambar.js?v=' . ASSET_VERSION . '" defer></script>';
?>
<main class="page-section">
<div class="container portal-container">

  <header class="portal-header">
    <div>
      <h1 class="portal-title">Upload Berkas Tambahan</h1>
      <p class="portal-subtitle">
        <?= e($pendaftaran['nama_lengkap']) ?>
        &middot; <strong><?= e($pendaftaran['nomor_daftar']) ?></strong>
      </p>
    </div>
  </header>

  <section class="portal-card">
    <p class="portal-note">Gambar (JPG/PNG/WEBP) otomatis dikompres ke ≤1MB; PDF maks 5MB.</p>
    <input type="hidden" id="csrfGlobal" value="<?= generateCsrfToken() ?>">

    <?php if (!$bolehUpload): ?>
    <div class="portal-info-box">
      <h2>Upload Ditutup</h2>
      <p>Status pendaftaran Anda (<strong><?= e(statusLabel($pendaftaran['status'])) ?></strong>) tidak mengizinkan upload saat ini.</p>
    </div>
    <?php endif; ?>

    <div class="berkas-list">
      <?php foreach ($berkasTambahanList as $jenis => $label): ?>
        <?php $exists = $berkasByJenis[$jenis] ?? null; ?>
        <div class="berkas-item" data-jenis="<?= e($jenis) ?>">
          <div class="berkas-info">
            <div class="berkas-label"><?= e($label) ?></div>
            <?php if ($exists): ?>
              <div class="berkas-status success">✓ Sudah diupload</div>
            <?php else: ?>
              <div class="berkas-status pending">Belum diupload</div>
            <?php endif; ?>
          </div>
          <div class="berkas-action">
            <?php if ($bolehUpload): ?>
            <input type="file" accept="image/jpeg,image/png,image/webp,application/pdf"
                   data-jenis="<?= e($jenis) ?>" class="berkas-input">
            <button type="button" class="btn-outline btn-upload" data-jenis="<?= e($jenis) ?>">Upload</button>
            <?php endif; ?>
            <?php if ($exists): ?>
              <a href="/berkas-santri?jenis=<?= e($jenis) ?>" target="_blank" class="btn-link">Lihat</a>
            <?php endif; ?>
          </div>
        </div>
      <?php endforeach; ?>
    </div>

    <div class="portal-actions">
      <a href="<?= BASE_URL ?>/portal-santri" class="btn-outline">&larr; Kembali ke Portal</a>
    </div>
  </section>

</div>
</main>
