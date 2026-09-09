<?php
/**
 * Lihat bukti transfer cicilan (admin only).
 * GET ?id=xxx → stream file dari uploads/bukti/.
 */
require_once __DIR__ . '/bootstrap.php';
requireAdmin();

$id = sanitizeInt($_GET['id'] ?? 0);
if ($id <= 0) {
    http_response_code(400);
    exit('Parameter tidak lengkap.');
}

$pdo = getDB();
$stmt = $pdo->prepare('SELECT bukti_file FROM pembiayaan_cicilan WHERE id = ? LIMIT 1');
$stmt->execute([$id]);
$rel = (string) ($stmt->fetchColumn() ?: '');

// Whitelist path: harus di dalam uploads/bukti/
if ($rel === '' || !str_starts_with($rel, 'bukti/') || str_contains($rel, '..')) {
    http_response_code(404);
    exit('Bukti tidak ditemukan.');
}
$path = UPLOADS_PATH . '/' . $rel;
if (!is_file($path)) {
    http_response_code(404);
    exit('File tidak ada di disk.');
}

$mime = detectMimeType($path);
while (ob_get_level()) ob_end_clean();
header('Content-Type: ' . $mime);
header('Content-Length: ' . filesize($path));
header('Content-Disposition: inline; filename="bukti-' . $id . '.' . pathinfo($path, PATHINFO_EXTENSION) . '"');
header('Cache-Control: private, no-store');
header('X-Content-Type-Options: nosniff');
readfile($path);
exit;
