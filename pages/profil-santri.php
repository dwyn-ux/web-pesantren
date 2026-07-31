<?php
if (empty($_SESSION['santri_id'])) {
    setFlash('info', 'Silakan login dengan akun pendaftaran.');
    redirect('/login-santri');
}
$pdo = getDB();
$id  = (int) $_SESSION['santri_id'];

$s = $pdo->prepare('SELECT * FROM pendaftaran WHERE id=?');
$s->execute([$id]);
$p = $s->fetch();
if (!$p) {
    unset($_SESSION['santri_id']);
    redirect('/login-santri');
}

$sf = $pdo->prepare("SELECT nama_file FROM berkas_santri WHERE pendaftaran_id=? AND jenis='foto' ORDER BY id DESC LIMIT 1");
$sf->execute([$id]);
$fotoAda = (bool) $sf->fetchColumn();

$labelJenjang = ['mts' => 'SMP', 'ma' => 'SMA', 'tahfidz-intensif' => 'Tahfidz Intensif'];
$labelQuran = [
    'belum-bisa' => 'Belum Bisa Membaca', 'bisa-membaca' => 'Bisa Membaca',
    'tartil' => 'Tartil', 'hafal-juz-30' => 'Hafal Juz 30', 'hafal-lebih' => 'Hafal Lebih dari Juz 30',
];
$labelStatus = [
    'pending' => 'Menunggu', 'diterima' => 'Diterima', 'ditolak' => 'Ditolak', 'daftar-ulang' => 'Daftar Ulang',
];

$activePage = 'profil';
$pageTitle  = 'Profil Santri | ' . APP_NAME;
$pageDescription = 'Profil dan data pendaftaran santri.';
$pageCanonical = BASE_URL . '/profil-santri';

$row = fn(string $label, string $value): string =>
    '<div class="profile-row"><span class="profile-label">' . e($label) . '</span><span class="profile-value">' . $value . '</span></div>';
?>
<main class="page-section"><div class="container narrow-container">
    <div class="portal-head">
        <div style="display:flex;align-items:center;gap:16px;">
            <?php if ($fotoAda): ?>
            <img src="<?= BASE_URL ?>/api/foto-santri.php" alt="Foto santri" style="width:64px;height:64px;object-fit:cover;border-radius:50%;border:3px solid #fff;box-shadow:var(--shadow-sm);">
            <?php endif; ?>
            <div>
                <small><?= e($p['nomor_induk'] ?: $p['nomor_daftar']) ?></small>
                <h1 style="margin:2px 0 4px;"><?= e($p['nama_lengkap']) ?></h1>
                <p style="margin:0;">Status: <strong><?= e($labelStatus[$p['status']] ?? $p['status']) ?></strong></p>
            </div>
        </div>
        <a class="btn-outline" href="<?= BASE_URL ?>/portal-santri">Lanjut Pendaftaran</a>
    </div>

    <div class="portal-card profile-card">
        <h2>Data Calon Santri</h2>
        <?= $row('Nama Lengkap', e($p['nama_lengkap'])) ?>
        <?= $row('Tempat, Tanggal Lahir', e($p['tempat_lahir'] . ', ' . date('d/m/Y', strtotime($p['tanggal_lahir'])))) ?>
        <?= $row('Jenis Kelamin', e($p['jenis_kelamin'] === 'P' ? 'Perempuan' : 'Laki-laki')) ?>
        <?= $row('Jenjang', e($labelJenjang[$p['jenjang']] ?? $p['jenjang'])) ?>
        <?= $row('Tinggi / Berat Badan', e((!empty($p['tinggi_badan']) ? (float) $p['tinggi_badan'] . ' cm' : '-') . ' / ' . (!empty($p['berat_badan']) ? (float) $p['berat_badan'] . ' kg' : '-'))) ?>
        <?= $row('No. WhatsApp', e($p['whatsapp'])) ?>
    </div>

    <div class="portal-card profile-card">
        <h2>Data Orang Tua / Wali</h2>
        <?= $row('Nama Ayah', e($p['nama_ayah'])) ?>
        <?= $row('Nama Ibu', e($p['nama_ibu'])) ?>
        <?= $row('No. HP Orang Tua', e($p['hp_ortu'])) ?>
        <?= $row('Pekerjaan Orang Tua', e($p['pekerjaan_ortu'] ?: '-')) ?>
        <?= $row('Alamat', e($p['alamat'])) ?>
    </div>

    <div class="portal-card profile-card">
        <h2>Data Akademik</h2>
        <?= $row('Asal Sekolah', e($p['asal_sekolah'])) ?>
        <?= $row('Tahun Lulus', e($p['tahun_lulus'])) ?>
        <?= $row('Kemampuan Membaca Al-Qur\'an', e($labelQuran[$p['kemampuan_quran']] ?? $p['kemampuan_quran'])) ?>
        <?= $row('Jumlah Hafalan', e($p['jumlah_hafalan'] ?: '-')) ?>
        <?= $row('Motivasi', e($p['motivasi'] ?: '-')) ?>
    </div>

    <?php if (!empty($p['kesanggupan_at'])): ?>
    <div class="portal-card profile-card">
        <h2>Kesanggupan</h2>
        <?= $row('Kesanggupan biaya', e($p['kesanggupan_setuju'] ? 'Sudah ditandatangani' : 'Belum')) ?>
        <?= $row('Ditandatangani pada', e(date('d/m/Y H:i', strtotime($p['kesanggupan_at'])))) ?>
    </div>
    <?php endif; ?>
</div></main>

<style>
.profile-card { margin-bottom: 22px; }
.profile-row {
    display: flex; gap: 16px; padding: 8px 0;
    border-bottom: 1px solid var(--cream-dark); font-size: 14px;
}
.profile-row:last-child { border-bottom: none; }
.profile-label { flex: 0 0 220px; color: var(--text-light); font-weight: 600; }
.profile-value { color: var(--text-dark); flex: 1; }
@media (max-width: 600px) {
    .profile-row { flex-direction: column; gap: 2px; }
    .profile-label { flex: none; }
}
</style>
