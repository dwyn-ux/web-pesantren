<?php
$activePage      = 'alumni';
$pageTitle       = 'Alumni Terdaftar | ' . APP_NAME;
$pageDescription = 'Berikut adalah alumni Pondok Pesantren Ash-Shiddiq yang telah disetujui dan ditampilkan di halaman utama.';
$pageCanonical   = BASE_URL . '/alumni';
$extraHead       = '<link rel="stylesheet" href="' . BASE_URL . '/assets/css/alumni.css?v=' . ASSET_VERSION . '">';
$extraScripts    = '<script src="' . BASE_URL . '/assets/js/alumni-modal.js?v=' . ASSET_VERSION . '" defer></script>';

$alumni = [];
try {
    $pdo = getDB();
    if ($pdo instanceof PDO) {
        $stmt = $pdo->query("SELECT id, nama, tahun_kelulusan, alamat, aktivitas, tempat_kuliah, jurusan, tempat_bekerja, jabatan, pesan_kesan, foto, orientasi FROM alumni WHERE status='verified' ORDER BY tahun_kelulusan DESC, nama ASC");
        $alumni = $stmt ? $stmt->fetchAll() : [];
    }
} catch (PDOException $e) {
    error_log('Gagal memuat data alumni: ' . $e->getMessage());
}

$totalAlumni = count($alumni);
?>

<main class="alumni-page-section">
    <div class="container">
        <div class="alumni-page-wrapper">
            <!-- Header Hero (Web SMP Style) -->
            <div class="alumni-page-hero">
                <h1>Alumni Terdaftar</h1>
                <p>Berikut adalah alumni yang telah disetujui dan ditampilkan di halaman utama.</p>
            </div>

            <!-- Content Body -->
            <div class="alumni-page-body">
                <div class="alumni-toolbar">
                    <p class="alumni-toolbar-count">
                        Total alumni: <strong><?= (int)$totalAlumni ?></strong>
                    </p>
                    <a href="<?= BASE_URL ?>/pendataan-alumni" class="alumni-btn-cta">
                        <span>Isi Pendataan Alumni</span>
                        <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M14 5l7 7m0 0l-7 7m7-7H3"/>
                        </svg>
                    </a>
                </div>

                <?php if (!empty($alumni)): ?>
                    <div class="alumni-cards-grid">
                        <?php foreach ($alumni as $idx => $item):
                            $photoUrl = alumniPhotoUrl($item['foto'] ?? '');
                            $activityLabel = alumniActivityLabel($item['aktivitas'] ?? '');
                            $subText = '';
                            if (($item['aktivitas'] ?? '') === 'kuliah') {
                                $subParts = array_filter([$item['tempat_kuliah'] ?? '', $item['jurusan'] ?? '']);
                                $subText = implode(' — ', $subParts);
                            } elseif (($item['aktivitas'] ?? '') === 'bekerja') {
                                $subParts = array_filter([$item['jabatan'] ?? '', $item['tempat_bekerja'] ?? '']);
                                $subText = implode(' di ', $subParts);
                            } else {
                                $subText = trim((string)($item['tempat_kuliah'] ?? '')) ?: trim((string)($item['tempat_bekerja'] ?? ''));
                            }
                            if ($subText === '') $subText = $activityLabel;
                        ?>
                        <button type="button"
                            style="animation-delay: <?= $idx * 60 ?>ms;"
                            class="alumni-card"
                            data-name="<?= e($item['nama']) ?>"
                            data-year="<?= e((string)$item['tahun_kelulusan']) ?>"
                            data-activity="<?= e($item['aktivitas'] ?? '') ?>"
                            data-activity-label="<?= e($activityLabel) ?>"
                            data-campus="<?= e((string)($item['tempat_kuliah'] ?? '')) ?>"
                            data-major="<?= e((string)($item['jurusan'] ?? '')) ?>"
                            data-company="<?= e((string)($item['tempat_bekerja'] ?? '')) ?>"
                            data-job="<?= e((string)($item['jabatan'] ?? '')) ?>"
                            data-address="<?= e((string)($item['alamat'] ?? '')) ?>"
                            data-message="<?= e((string)($item['pesan_kesan'] ?? '')) ?>"
                            data-photo="<?= e($photoUrl) ?>">
                            <div class="alumni-card-media">
                                <img src="<?= e($photoUrl) ?>" alt="Foto <?= e($item['nama']) ?>" loading="lazy" class="alumni-card-img">
                                <div class="alumni-card-shade"></div>

                                <?php if (!empty($item['tahun_kelulusan'])): ?>
                                    <span class="alumni-card-badge">Angkatan <?= e((string)$item['tahun_kelulusan']) ?></span>
                                <?php endif; ?>

                                <div class="alumni-card-info">
                                    <h2 class="alumni-card-name"><?= e($item['nama']) ?></h2>
                                    <div class="alumni-card-sub">
                                        <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 14l9-5-9-5-9 5 9 5z"/>
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 14l6.16-3.422a12.083 12.083 0 01.665 6.479A11.952 11.952 0 0012 20.055a11.952 11.952 0 00-6.824-2.998 12.078 12.078 0 01.665-6.479L12 14z"/>
                                        </svg>
                                        <span><?= e($subText) ?></span>
                                    </div>
                                </div>
                            </div>
                        </button>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div class="empty-state" style="margin: 20px 0; text-align: center; padding: 48px 20px; background: var(--cream, #f6f2e9); border-radius: 18px; color: var(--text-light, #7a9988);">
                        <p style="font-size: 16px; font-weight: 700; margin-bottom: 8px; color: var(--text-dark, #102f26);">Belum ada data alumni yang disetujui.</p>
                        <p style="font-size: 14px; margin-bottom: 22px; color: var(--text-mid, #3a5444);">Jadilah yang pertama mencatatkan jejak langkah dan profil Anda.</p>
                        <a href="<?= BASE_URL ?>/pendataan-alumni" class="alumni-btn-cta">
                            <span>Isi Pendataan Alumni</span>
                            <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M14 5l7 7m0 0l-7 7m7-7H3"/>
                            </svg>
                        </a>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</main>

<?php require ROOT_PATH . '/includes/alumni-modal.php'; ?>
