<?php
/**
 * Controller untuk view/download berkas calon-santri.
 * GET ?jenis=... menampilkan file inline.
 * Akses hanya untuk admin atau pemilik berkas.
 */
require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/../config/session.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

$jenis = sanitizeString($_GET['jenis'] ?? '');
$jenisValid = [
    'kartu-keluarga', 'akta-lahir', 'ijazah', 'foto', 'ktp-ortu',
    'bukti-bayar',
    'sertifikat-tka', 'sertifikat-tahfidz',
    'surat-rekomendasi', 'sktm', 'surat-pernyataan', 'mou-kaderisasi',
    'kip', 'skl',
];
if (!in_array($jenis, $jenisValid, true)) {
    http_response_code(400);
    exit('Jenis berkas tidak valid.');
}

$pdo = getDB();

// Pendaftar hanya boleh melihat miliknya sendiri — filter ganda
// (pendaftaran_id + jenis) agar tak salah ambil milik orang lain.
if (isCalonSantri()) {
    $pendaftaran = getCurrentPendaftaran();
    if (!$pendaftaran) {
        http_response_code(404);
        exit('Data pendaftaran tidak ditemukan.');
    }
    $stmt = $pdo->prepare(
        'SELECT bs.* FROM berkas_santri bs
         WHERE bs.jenis = ? AND bs.pendaftaran_id = ?
         ORDER BY bs.id DESC LIMIT 1'
    );
    $stmt->execute([$jenis, (int) $pendaftaran['id']]);
} else {
    $stmt = $pdo->prepare(
        'SELECT bs.*, p.user_id FROM berkas_santri bs
         JOIN pendaftaran p ON p.id = bs.pendaftaran_id
         WHERE bs.jenis = ? ORDER BY bs.id DESC LIMIT 1'
    );
    $stmt->execute([$jenis]);
}
$berkas = $stmt->fetch();

if (!$berkas) {
    http_response_code(404);
    exit('Berkas tidak ditemukan.');
}

// Access control: admin ATAU pemilik
if (isCalonSantri()) {
    $isOwner = true; // sudah difilter ke pendaftaran milik sendiri
} else {
    $isOwner = false;
}
if (!$isOwner && !isAdmin()) {
    http_response_code(403);
    exit('Anda tidak memiliki akses.');
}

$path = __DIR__ . '/../uploads/' . $berkas['nama_file'];
if (!is_file($path)) {
    http_response_code(404);
    exit('File tidak ada di disk.');
}

while (ob_get_level()) ob_end_clean();
header('Content-Type: ' . ($berkas['mime_type'] ?: 'application/octet-stream'));
header('Content-Length: ' . filesize($path));
header('Content-Disposition: inline; filename="' . basename($berkas['nama_file']) . '"');
header('Cache-Control: private, no-store');
header('Content-Security-Policy: default-src \'none\'; sandbox');
header('X-Content-Type-Options: nosniff');
readfile($path);
exit;
