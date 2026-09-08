<?php
/**
 * View/download template dokumen PSB.
 * GET ?slug=xxx&preview=1 untuk preview (admin only)
 * GET ?slug=xxx&asNip=ASQ-2026-0001 untuk download (calon yang sesuai)
 */
require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/../config/session.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

$slug = sanitizeString($_GET['slug'] ?? '');
$preview = !empty($_GET['preview']);
$asNomor = sanitizeString($_GET['as'] ?? '');

$validSlugs = [
    'rekomendasi-kaderisasi', 'mou-kaderisasi',
    'rekomendasi-alumni', 'rekomendasi-dhuafa', 'pernyataan-dhuafa',
];
if (!in_array($slug, $validSlugs, true)) {
    http_response_code(400);
    exit('Template tidak valid.');
}

$pdo = getDB();
$stmt = $pdo->prepare('SELECT * FROM template_dokumen WHERE slug = ? AND is_active = 1 LIMIT 1');
$stmt->execute([$slug]);
$tpl = $stmt->fetch();

if (!$tpl) {
    http_response_code(404);
    exit('Template tidak ditemukan atau belum aktif.');
}

// Access control
if ($preview) {
    if (!isAdmin()) {
        http_response_code(403);
        exit('Hanya admin yang boleh preview template.');
    }
} else {
    // Calon yang download: cek jalur & status
    if (!isCalonSantri()) {
        http_response_code(401);
        exit('Silakan login.');
    }
    $pendaftaran = getCurrentPendaftaran();
    if (!$pendaftaran) {
        http_response_code(403);
        exit('Akses ditolak.');
    }
    $allowedBerkas = jalurBerkasUntuk($pendaftaran['jalur']);
    $slugMap = [
        'rekomendasi-kaderisasi' => 'surat-rekomendasi',
        'mou-kaderisasi'         => 'mou-kaderisasi',
        'rekomendasi-alumni'     => 'surat-rekomendasi',
        'rekomendasi-dhuafa'     => 'surat-rekomendasi',
        'pernyataan-dhuafa'      => 'surat-pernyataan',
    ];
    if (!in_array($slugMap[$slug], $allowedBerkas, true)) {
        http_response_code(403);
        exit('Template ini tidak diperlukan untuk jalur Anda.');
    }
    $asNomor = $pendaftaran['nomor_daftar'];
}

// Replace placeholder
$html = $tpl['isi_html'];
$nomor = $asNomor ?: 'ASQ-PREVIEW';
$html = str_replace('__NOMOR_DAFTAR__', $nomor, $html);

// Inject logo + kop
$kopAlamat = $pdo->query("SELECT value FROM pengaturan WHERE key_name='kop_alamat'")->fetchColumn() ?: 'Jl. Pesantren No. 1, Kab. Ciamis, Jawa Barat';
$kopTelp    = $pdo->query("SELECT value FROM pengaturan WHERE key_name='kop_telepon'")->fetchColumn() ?: '(0265) 123-4567';

// Ganti placeholder logo dengan <img onerror hide>
$html = str_replace(
    '__KOP_LOGO_LEFT__',
    '<img src="' . BASE_URL . '/assets/img/kop-surat/logo.png" alt="Logo" style="width:80px;height:80px;margin-right:20px;" onerror="this.style.display=\'none\'">',
    $html
);
$html = str_replace(
    '__KOP_LOGO_RIGHT__',
    '<img src="' . BASE_URL . '/assets/img/kop-surat/logo-muhammadiyah.png" alt="Logo Muhammadiyah" style="width:70px;height:70px;margin-left:20px;" onerror="this.style.display=\'none\'">',
    $html
);

header('Content-Type: text/html; charset=UTF-8');
echo '<!DOCTYPE html><html lang="id"><head><meta charset="UTF-8"><title>' . e($tpl['judul']) . '</title>';
echo '<style>body{font-family:Times New Roman,serif;padding:20px;background:#f5f5f5;} .doc{background:#fff;padding:40px;max-width:800px;margin:auto;box-shadow:0 2px 20px rgba(0,0,0,0.1);} @media print{body{background:#fff;padding:0;}.doc{box-shadow:none;max-width:none;}}</style>';
echo '</head><body><div class="doc">' . $html . '</div></body></html>';
