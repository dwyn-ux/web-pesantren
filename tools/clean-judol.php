<?php
/**
 * clean-judol.php — Pembersih folder judol bajakan di production.
 *
 * Latar: hacker mengunggah folder fisik /artikel/, /galeri/, /dokumentasi/,
 * /alumni/, /profil/ berisi HTML judol statis (±365KB, brand MAHJONG333 dkk).
 * Karena .htaccess meloloskan direktori fisik (-d), folder itu membayangi router.
 *
 * Pakai:  php tools/clean-judol.php            (dry-run, hanya lapor)
 *         php tools/clean-judol.php --fix      (pindahkan rogue ke quarantine/)
 *
 * Aman: tidak menghapus — mode --fix MEMINDAHKAN ke quarantine/YYYYmmdd-HHMMSS/
 * agar bisa di-restore. Direktori legit (admin/api/assets/uploads/dll) tidak disentuh.
 */

$FIX = in_array('--fix', $argv ?? [], true);
$ROOT = dirname(__DIR__);
$QUAR = $ROOT . '/quarantine/' . date('Ymd-His');

$LEGIT_DIRS = ['admin', 'api', 'assets', 'uploads', 'cache', 'logs', 'config', 'includes', 'pages', 'migrations', 'tools', 'quarantine'];
// Slug halaman aplikasi — TIDAK BOLEH ada sebagai direktori fisik di production.
$APP_SLUGS = ['artikel', 'galeri', 'dokumentasi', 'alumni', 'profil', 'sekapur-sirih', 'psb', 'login', 'login-santri', 'portal-santri', 'profil-santri', 'surat-kesanggupan', 'pendataan-alumni', 'dokumen-lihat', 'berkas-santri', 'upload-berkas', 'download-template', 'dokumen-santri'];

$JUDOL_RE = '/mahjong|slot88|slot777|gacor|maxwin|togel|sultankoin|musangwin|dewitogel|ratutogel|qris\s*5\.?000|situs\s+fenomenal|link\s+resmi|shop\s+all\s+designs|design\s+id:/i';

$rogues = [];
$notes = [];

// 1. Direktori fisik yang membayangi slug aplikasi = 100% rogue (app tidak punya dir fisik ini)
foreach ($APP_SLUGS as $slug) {
    $dir = $ROOT . '/' . $slug;
    if (is_dir($dir)) {
        $rogues[] = $slug . '/';
        $notes[] = "DIR BAYANGAN: /$slug/ ada fisik (harusnya hanya route index.php)";
    }
}

// 2. Direktori tak dikenal di root (bukan legit, bukan file) — periksa isi judol
foreach (scandir($ROOT) as $entry) {
    if ($entry === '.' || $entry === '..') continue;
    $path = $ROOT . '/' . $entry;
    if (!is_dir($path) || in_array($entry, $LEGIT_DIRS, true)) continue;
    if (str_starts_with($entry, '.')) continue; // .git dll — tangani terpisah
    // Cek index file ber-keyword judol
    $judol = false;
    foreach (['index.html', 'index.htm', 'index.php', 'default.html'] as $idx) {
        $f = $path . '/' . $idx;
        if (is_file($f) && filesize($f) > 0 && filesize($f) < 5 * 1024 * 1024) {
            $head = file_get_contents($f, false, null, 0, 200000);
            if ($head !== false && preg_match($JUDOL_RE, $head)) { $judol = true; break; }
        }
    }
    if ($judol) {
        $rogues[] = $entry . '/';
        $notes[] = "DIR JUDOL: /$entry/ berisi keyword judol";
    } else {
        $notes[] = "DIR ASING (cek manual): /$entry/";
    }
}

// 3. File script/doorway di uploads & assets/img
$susFiles = [];
$it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($ROOT . '/uploads', FilesystemIterator::SKIP_DOTS));
foreach ($it as $f) {
    $n = strtolower($f->getFilename());
    if (preg_match('/\.(php\d*|phtml|phar|pht|cgi|pl|py|sh|shtml|html?|js|svg|swf)$/', $n)
        || preg_match('/\.(php\d*|phtml|phar|cgi|pl|py|sh|html?|js|svg)\./', $n . '.')) {
        $susFiles[] = substr($f->getPathname(), strlen($ROOT) + 1);
    }
}

echo "== SCAN JUDOL " . date('Y-m-d H:i:s') . " ==\n";
echo "Mode: " . ($FIX ? "--fix (pindahkan ke quarantine)" : "dry-run (tambah --fix untuk karantina)") . "\n\n";

foreach ($notes as $n) echo ($n[0] === 'D' ? "[TEMUAN] " : "[INFO] ") . $n . "\n";
foreach ($susFiles as $s) echo "[TEMUAN] FILE: $s\n";

if (!$rogues && !$susFiles) {
    echo "\nHASIL: BERSIH ✅\n";
    exit(0);
}

echo "\nRogue dirs: " . (int)count($rogues) . ", file curiga: " . (int)count($susFiles) . "\n";

if (!$FIX) {
    echo "\nJalankan: php tools/clean-judol.php --fix  (untuk karantina)\n";
    echo "Lalu deploy .htaccess guard terbaru + request reindex di Search Console.\n";
    exit(1);
}

@mkdir($QUAR, 0755, true);
foreach ($rogues as $r) {
    $src = $ROOT . '/' . rtrim($r, '/');
    $dst = $QUAR . '/' . rtrim($r, '/');
    if (@rename($src, $dst)) {
        echo "[KARANTINA] $r -> quarantine/" . basename($QUAR) . "/$r\n";
    } else {
        echo "[GAGAL] tidak bisa memindahkan $r (cek permission)\n";
    }
}
foreach ($susFiles as $s) {
    $src = $ROOT . '/' . $s;
    $dst = $QUAR . '/' . str_replace('/', '__', $s);
    if (@rename($src, $dst)) {
        echo "[KARANTINA] $s\n";
    } else {
        echo "[GAGAL] tidak bisa memindahkan $s\n";
    }
}
echo "\nSELESAI. Verifikasi: curl -sI https://domain/artikel/ harus 200 dari aplikasi (title pesantren), bukan MAHJONG.\n";
exit(0);
