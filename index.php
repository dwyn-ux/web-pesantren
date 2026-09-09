<?php
/**
 * Entry point utama — router aplikasi
 * Semua request diarahkan ke sini oleh .htaccess
 */

define('ROOT_PATH', __DIR__);

require_once ROOT_PATH . '/config/constants.php';
require_once ROOT_PATH . '/config/session.php';
require_once ROOT_PATH . '/config/database.php';
require_once ROOT_PATH . '/includes/functions.php';
require_once ROOT_PATH . '/includes/auth.php';

// ── Routing ──────────────────────────────────────────────────

// Ambil segment halaman dari URL (dibersihkan dari trailing slash)
$rawPage = trim($_GET['page'] ?? 'home', '/');
$slug    = null;

// Tangani pola URL: artikel/{slug}
if (preg_match('#^artikel/([a-z0-9\-]+)$#', $rawPage, $m)) {
    $page = 'artikel-detail';
    $slug = $m[1];
} else {
    $page = sanitizeString($rawPage);
}

// Whitelist halaman publik
$publicPages = [
    'home', 'profil', 'sekapur-sirih',
    'artikel', 'artikel-detail',
    'psb', 'login', 'login-santri', 'portal-santri', 'profil-santri', 'surat-kesanggupan', 'dokumentasi', 'alumni', 'pendataan-alumni', 'dokumen-lihat', 'berkas-santri', 'download-template', 'dokumen-santri', '404', 'galeri',
];

// Halaman yang butuh login (admin)
$authPages = [];

// Halaman admin (butuh role admin)
$adminPages = [
    'admin', 'admin/dashboard',
    'admin/artikel', 'admin/artikel-tambah', 'admin/artikel-edit',
    'admin/foto', 'admin/users', 'admin/psb', 'admin/dokumentasi', 'admin/alumni', 'admin/alumni-edit',    'admin/pengaturan',     'admin/berkas-lihat', 'admin/testimoni', 'admin/jalur-potongan', 'admin/kelola-template', 'admin/kelola-gelombang', 'admin/verifikasi-berkas', 'admin/input-tes', 'admin/cicilan-verifikasi', 'admin/daftar-ulang', 'admin/voucher-alumni', 'admin/voucher-akashi',
];

// ── Access Control ────────────────────────────────────────────

if (in_array($page, $adminPages)) {
    requireAdmin();
} elseif (in_array($page, $authPages)) {
    requireLogin();
}

// ── Map URL → file ────────────────────────────────────────────

$pageMap = [
    'home'            => ROOT_PATH . '/pages/home.php',
    'profil'          => ROOT_PATH . '/pages/profil.php',
    'sekapur-sirih'   => ROOT_PATH . '/pages/sekapur-sirih.php',
    'artikel'         => ROOT_PATH . '/pages/artikel.php',
    'artikel-detail'  => ROOT_PATH . '/pages/artikel-detail.php',
    'psb'             => ROOT_PATH . '/pages/psb.php',
    'dokumentasi'     => ROOT_PATH . '/pages/dokumentasi.php',
    'galeri'          => ROOT_PATH . '/pages/galeri.php',
    'dokumen-lihat'   => ROOT_PATH . '/pages/dokumen-lihat.php',
    'berkas-santri'   => ROOT_PATH . '/pages/berkas-santri.php',
    'alumni'          => ROOT_PATH . '/pages/alumni.php',
    'pendataan-alumni' => ROOT_PATH . '/pages/pendataan-alumni.php',
    'login'           => ROOT_PATH . '/pages/login.php',
    'login-santri'    => ROOT_PATH . '/pages/login-santri.php',
    'portal-santri'   => ROOT_PATH . '/pages/portal-santri.php',
    'surat-kesanggupan' => ROOT_PATH . '/pages/surat-kesanggupan.php',
    'logout'          => null, // ditangani di bawah
    'profil-santri'   => ROOT_PATH . '/pages/profil-santri.php',
    'admin'           => ROOT_PATH . '/admin/index.php',
    'admin/dashboard' => ROOT_PATH . '/admin/dashboard.php',
    'admin/artikel'   => ROOT_PATH . '/admin/artikel.php',
    'admin/artikel-tambah' => ROOT_PATH . '/admin/artikel-tambah.php',
    'admin/artikel-edit'   => ROOT_PATH . '/admin/artikel-edit.php',
    'admin/foto'      => ROOT_PATH . '/admin/foto.php',
    'admin/users'     => ROOT_PATH . '/admin/users.php',
    'admin/psb'       => ROOT_PATH . '/admin/psb.php',
    'admin/dokumentasi' => ROOT_PATH . '/admin/dokumentasi.php',
    'admin/alumni'    => ROOT_PATH . '/admin/alumni.php',
    'admin/alumni-edit' => ROOT_PATH . '/admin/alumni-edit.php',
    'admin/pengaturan' => ROOT_PATH . '/admin/pengaturan.php',
    'admin/berkas-lihat' => ROOT_PATH . '/admin/berkas-lihat.php',
    'admin/testimoni' => ROOT_PATH . '/admin/testimoni.php',
    'admin/jalur-potongan' => ROOT_PATH . '/admin/jalur-potongan.php',
    'admin/kelola-template' => ROOT_PATH . '/admin/kelola-template.php',
    'admin/kelola-gelombang' => ROOT_PATH . '/admin/kelola-gelombang.php',
    'admin/verifikasi-berkas' => ROOT_PATH . '/admin/verifikasi-berkas.php',
    'admin/input-tes' => ROOT_PATH . '/admin/input-tes.php',
    'admin/cicilan-verifikasi' => ROOT_PATH . '/admin/cicilan-verifikasi.php',
    'admin/daftar-ulang' => ROOT_PATH . '/admin/daftar-ulang.php',
    'admin/voucher-alumni' => ROOT_PATH . '/admin/voucher-alumni.php',
    'admin/voucher-akashi' => ROOT_PATH . '/admin/voucher-akashi.php',
    'download-template' => ROOT_PATH . '/pages/download-template.php',
    'dokumen-santri' => ROOT_PATH . '/pages/dokumen-santri.php',
];

// Tangani logout langsung
if ($page === 'logout') {
    logout(); // langsung exit di dalamnya
}

// ── Load Halaman ──────────────────────────────────────────────

$filePath = $pageMap[$page] ?? null;

// Fallback: jika page tidak ada di map tapi file-nya ada, tolak (hindari path traversal)
if ($filePath === null || !file_exists($filePath)) {
    http_response_code(404);
    $filePath = ROOT_PATH . '/pages/404.php';
    $page     = '404';
}

// Untuk halaman admin, admin punya layout sendiri
if (str_starts_with($page, 'admin')) {
    require $filePath;
    exit;
}

// Halaman publik/auth — buffer page dulu agar $pageTitle, $extraHead dll
// bisa dibaca oleh header.php
ob_start();
include $filePath;
$pageContent = ob_get_clean();

// Halaman santri — pakai header khusus (menu kebutuhan santri)
$santriPages = ['portal-santri', 'profil-santri'];

include ROOT_PATH . (in_array($page, $santriPages, true) ? '/includes/header-santri.php' : '/includes/header.php');
echo $pageContent;
include ROOT_PATH . '/includes/footer.php';
