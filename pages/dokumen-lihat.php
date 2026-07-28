<?php
$id = sanitizeInt($_GET['id'] ?? 0);
try {
    $stmt = getDB()->prepare("SELECT nama_file,mime_type,tipe,is_published FROM dokumentasi WHERE id=?");
    $stmt->execute([$id]); $doc = $stmt->fetch();
} catch (PDOException $e) { $doc = false; }
if (!$doc || (!$doc['is_published'] && !isAdmin()) || $doc['tipe']==='word') { http_response_code(404); exit('Dokumen tidak tersedia.'); }
$path = UPLOADS_PATH . '/dokumentasi/' . basename($doc['nama_file']);
if (!is_file($path)) { http_response_code(404); exit('File tidak ditemukan.'); }
while (ob_get_level()) ob_end_clean();
header('Content-Type: '.$doc['mime_type']);
header('Content-Length: '.filesize($path));
header('Content-Disposition: inline; filename="dokumen.' . pathinfo($path, PATHINFO_EXTENSION) . '"');
header('Cache-Control: private, no-store');
header("Content-Security-Policy: default-src 'none'; style-src 'unsafe-inline'; sandbox");
header('X-Content-Type-Options: nosniff');
readfile($path); exit;
