<?php
// Stream gambar tanda tangan kesanggupan (hanya untuk admin)
define('ROOT_PATH', dirname(__DIR__));
require_once ROOT_PATH . '/config/constants.php';
require_once ROOT_PATH . '/config/session.php';
require_once ROOT_PATH . '/config/database.php';
require_once ROOT_PATH . '/includes/functions.php';
require_once ROOT_PATH . '/includes/auth.php';
requireAdmin();

$id = sanitizeInt($_GET['id'] ?? 0);
$s  = getDB()->prepare('SELECT kesanggupan_sign FROM pendaftaran WHERE id=?');
$s->execute([$id]);
$sign = $s->fetchColumn();
if (!$sign) { http_response_code(404); exit('Tanda tangan belum tersedia.'); }

$path = UPLOADS_PATH . '/santri/' . basename($sign);
if (!is_file($path)) { http_response_code(404); exit('File tidak ditemukan.'); }

while (ob_get_level()) ob_end_clean();
header('Content-Type: image/png');
header('Content-Length: ' . filesize($path));
header('Content-Disposition: inline; filename="tanda-tangan-' . $id . '.png"');
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');
readfile($path);
exit;
