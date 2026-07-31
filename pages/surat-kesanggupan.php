<?php
/**
 * Ringkasan Pendaftaran Santri Baru — output PDF.
 * Akses: santri (session) atau admin (dengan ?id=).
 */

// ── Akses ────────────────────────────────────────────────────
if (!empty($_SESSION['santri_id'])) {
    $id = (int) $_SESSION['santri_id'];
} elseif (isLoggedIn()) {
    requireAdmin();
    $id = sanitizeInt($_GET['id'] ?? 0);
} else {
    http_response_code(403);
    exit('Akses ditolak.');
}
if ($id <= 0) { http_response_code(404); exit; }

$pdo = getDB();
$s   = $pdo->prepare('SELECT * FROM pendaftaran WHERE id=?');
$s->execute([$id]);
$p = $s->fetch();
if (!$p) { http_response_code(404); exit; }

$items = $pdo->prepare('SELECT * FROM pembiayaan WHERE pendaftaran_id=? ORDER BY urutan, id');
$items->execute([$id]);
$items = $items->fetchAll();

// Hanya tampilkan item yang dipilih (administrasi/wakaf/syahriyah) + item tunggal
$itemsShown = array_values(array_filter($items, function ($it) {
    if (in_array($it['jenis'], ['administrasi', 'wakaf', 'syahriyah'], true)) {
        return (int) $it['dipilih'] === 1;
    }
    return true;
}));

// Tanda tangan (PNG mentah)
$signBytes = '';
$ttdW = 170; $ttdH = 65;
if (!empty($p['kesanggupan_sign'])) {
    $signPath = UPLOADS_PATH . '/santri/' . basename($p['kesanggupan_sign']);
    if (is_file($signPath)) {
        $signBytes = (string) file_get_contents($signPath);
        $sz = @getimagesize($signPath);
        if ($sz && $sz[0] > 0) {
            $ttdH = (int) round(170 * ($sz[1] / $sz[0]));
        }
    }
}

$labelJenjang = ['mts' => 'SMP', 'ma' => 'SMA', 'tahfidz-intensif' => 'Tahfidz Intensif'];
$labelQuran = [
    'belum-bisa' => 'Belum Bisa Membaca', 'bisa-membaca' => 'Bisa Membaca',
    'tartil' => 'Tartil', 'hafal-juz-30' => 'Hafal Juz 30', 'hafal-lebih' => 'Hafal Lebih dari Juz 30',
];
$jkLabel = $p['jenis_kelamin'] === 'P' ? 'Perempuan' : 'Laki-laki';
$nomorFile = preg_replace('/[^A-Za-z0-9-]/', '-', $p['nomor_daftar']);

// Bangun PDF (format tabel)
include __DIR__ . '/ringkasan-pdf.php';
