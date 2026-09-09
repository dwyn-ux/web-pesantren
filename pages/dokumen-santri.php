<?php
/**
 * Dokumen kelulusan santri (cetak/print 3 surat + stream TTD).
 * GET ?tipe=kesanggupan-biaya|pernyataan-santri|pernyataan-wali → HTML siap print
 * GET ?tipe=ttd&f=sign-xxx.png → stream gambar TTD milik sendiri
 * Akses santri: status diterima/daftar-ulang. Admin: ?id= + preview template.
 */
require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/../config/session.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

$tipe = sanitizeString($_GET['tipe'] ?? '');
$pdo = getDB();

// ── Tentukan pendaftaran ──
if (isCalonSantri()) {
    $pendaftaran = getCurrentPendaftaran();
    if (!$pendaftaran) {
        http_response_code(403);
        exit('Akses ditolak.');
    }
    if (!in_array($pendaftaran['status'], ['diterima', 'daftar-ulang'], true)) {
        http_response_code(403);
        exit('Dokumen tersedia setelah Anda dinyatakan diterima.');
    }
    $id = (int) $pendaftaran['id'];
} elseif (isAdmin()) {
    $id = sanitizeInt($_GET['id'] ?? 0);
    if ($id <= 0) {
        http_response_code(400);
        exit('Parameter id tidak lengkap.');
    }
} else {
    http_response_code(403);
    exit('Akses ditolak.');
}

$s = $pdo->prepare('SELECT * FROM pendaftaran WHERE id = ? LIMIT 1');
$s->execute([$id]);
$p = $s->fetch();
if (!$p) {
    http_response_code(404);
    exit('Data pendaftaran tidak ditemukan.');
}

// ── Stream TTD milik sendiri ──
if ($tipe === 'ttd') {
    $f = basename(sanitizeString($_GET['f'] ?? ''));
    if (!preg_match('/^sign-[0-9a-f]{32}\.png$/', $f)) {
        http_response_code(400);
        exit('File tidak valid.');
    }
    if ($f !== ($p['ttd_santri'] ?? '') && $f !== ($p['ttd_wali'] ?? '')) {
        http_response_code(403);
        exit('Akses ditolak.');
    }
    $path = UPLOADS_PATH . '/santri/' . $id . '/' . $f;
    if (!is_file($path)) {
        http_response_code(404);
        exit('File tidak ditemukan.');
    }
    while (ob_get_level()) ob_end_clean();
    header('Content-Type: image/png');
    header('Content-Length: ' . filesize($path));
    header('Content-Disposition: inline; filename="' . $f . '"');
    header('Cache-Control: private, no-store');
    header('X-Content-Type-Options: nosniff');
    readfile($path);
    exit;
}

// ── Petakan tipe → slug template ──
$slug = match ($tipe) {
    'kesanggupan-biaya' => ($p['jenjang'] === 'sma' ? 'kesanggupan-biaya-sma' : 'kesanggupan-biaya-smp'),
    'kesanggupan-biaya-smp', 'kesanggupan-biaya-sma' => $tipe, // slug langsung dari tombol portal/profil
    'pernyataan-santri' => 'pernyataan-santri',
    'pernyataan-wali' => 'pernyataan-wali',
    default => '',
};
if ($slug === '') {
    http_response_code(400);
    exit('Tipe dokumen tidak valid.');
}

$stmt = $pdo->prepare('SELECT * FROM template_dokumen WHERE slug = ? AND is_active = 1 LIMIT 1');
$stmt->execute([$slug]);
$tpl = $stmt->fetch();
if (!$tpl) {
    http_response_code(404);
    exit('Template belum tersedia. Hubungi panitia.');
}

$html = renderDokumenKelulusan($pdo, $p, $tpl['isi_html']);

header('Content-Type: text/html; charset=UTF-8');
echo '<!DOCTYPE html><html lang="id"><head><meta charset="UTF-8"><title>' . e($tpl['judul']) . ' — ' . e($p['nomor_daftar']) . '</title>';
echo '<style>body{font-family:Times New Roman,serif;padding:20px;background:#f5f5f5;} .doc{background:#fff;padding:40px;max-width:800px;margin:16px auto;box-shadow:0 2px 20px rgba(0,0,0,0.1);} .toolbar{position:sticky;top:12px;z-index:80;max-width:800px;margin:0 auto;display:flex;gap:8px;background:#0d7a4a;padding:10px 12px;border-radius:8px;box-shadow:0 2px 12px rgba(0,0,0,.2);} .toolbar button{padding:8px 16px;border:none;border-radius:4px;background:#fff;color:#0d7a4a;cursor:pointer;font-size:14px;font-weight:700;} .toolbar button:hover{background:#f0f0f0;} @media print{body{background:#fff;padding:0;} .doc{box-shadow:none;max-width:none;margin:0;} .toolbar{display:none;}}</style>';
echo '</head><body>';
echo '<div class="toolbar"><button onclick="window.print()">🖨 Print</button><button onclick="downloadDoc()">⬇ Download</button></div>';
echo '<div class="doc">' . $html . '</div>';
echo '<script>function downloadDoc(){const blob=new Blob([document.querySelector(".doc").outerHTML],{type:"text/html"});const a=document.createElement("a");a.href=URL.createObjectURL(blob);a.download=' . json_encode(preg_replace('/[^a-z0-9]+/i', '-', strtolower($tpl['judul'] . '-' . $p['nomor_daftar'])) . '.html') . ';a.click();setTimeout(()=>URL.revokeObjectURL(a.href),5000);}</script>';
echo '</body></html>';
exit;
