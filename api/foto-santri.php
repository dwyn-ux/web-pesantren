<?php
// Stream foto 3x4 milik santri yang sedang login (untuk avatar di navbar)
define('ROOT_PATH', dirname(__DIR__));
require_once ROOT_PATH . '/config/constants.php';
require_once ROOT_PATH . '/config/session.php';
require_once ROOT_PATH . '/config/database.php';
require_once ROOT_PATH . '/includes/functions.php';
require_once ROOT_PATH . '/includes/auth.php';

if (empty($_SESSION['santri_id'])) { http_response_code(403); exit('Akses ditolak.'); }

$id  = (int) $_SESSION['santri_id'];
$st  = getDB()->prepare("SELECT nama_file, mime_type FROM berkas_santri WHERE pendaftaran_id=? AND jenis='foto' ORDER BY id DESC LIMIT 1");
$st->execute([$id]);
$f  = $st->fetch();
if (!$f) { http_response_code(404); exit; }

$path = UPLOADS_PATH . '/santri/' . basename($f['nama_file']);
if (!is_file($path)) { http_response_code(404); exit; }

while (ob_get_level()) ob_end_clean();
header('Content-Type: ' . $f['mime_type']);
header('Content-Length: ' . filesize($path));
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');
readfile($path);
exit;
