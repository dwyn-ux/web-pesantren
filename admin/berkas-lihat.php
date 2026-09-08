<?php
require_once __DIR__ . '/bootstrap.php';
requireAdmin();

$jenis = sanitizeString($_GET['jenis'] ?? '');
$pendaftaranId = sanitizeInt($_GET['pendaftaran_id'] ?? 0);
$idBerkas = sanitizeInt($_GET['id'] ?? 0);

$pdo = getDB();

if ($idBerkas > 0) {
    $stmt = $pdo->prepare('SELECT * FROM berkas_santri WHERE id = ?');
    $stmt->execute([$idBerkas]);
} elseif ($jenis && $pendaftaranId) {
    $stmt = $pdo->prepare(
        'SELECT * FROM berkas_santri WHERE jenis = ? AND pendaftaran_id = ? ORDER BY id DESC LIMIT 1'
    );
    $stmt->execute([$jenis, $pendaftaranId]);
} else {
    http_response_code(400);
    exit('Parameter tidak lengkap.');
}

$berkas = $stmt->fetch();
if (!$berkas) {
    http_response_code(404);
    exit('Berkas tidak ditemukan.');
}

$path = UPLOADS_PATH . '/' . $berkas['nama_file'];
if (!is_file($path)) {
    http_response_code(404);
    exit('File tidak ada di disk.');
}

while (ob_get_level()) ob_end_clean();
header('Content-Type: ' . ($berkas['mime_type'] ?: 'application/octet-stream'));
header('Content-Length: ' . filesize($path));
header('Content-Disposition: inline; filename="' . preg_replace('/[^A-Za-z0-9._-]/', '-', basename($berkas['nama_file'])) . '"');
header('X-Content-Type-Options: nosniff');
readfile($path);
exit;
