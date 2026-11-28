<?php
/**
 * Kartu Santri dengan Kop Pondok — preview HTML siap cetak + download PDF langsung.
 * Akses:
 *   - User (calon-santri): /kartu-santri (atau ?download=pdf)
 *   - Admin: /kartu-santri?id=<pendaftaran_id> (atau ?id=...&download=pdf)
 */
if (!defined('ROOT_PATH')) {
    define('ROOT_PATH', dirname(__DIR__));
    require_once ROOT_PATH . '/config/constants.php';
    require_once ROOT_PATH . '/config/session.php';
    require_once ROOT_PATH . '/config/database.php';
    require_once ROOT_PATH . '/includes/functions.php';
    require_once ROOT_PATH . '/includes/auth.php';
}

// ── Akses ────────────────────────────────────────────────────
$isAdminView = false;
if (isCalonSantri()) {
    $pendaftaran = getCurrentPendaftaran();
    if (!$pendaftaran) {
        http_response_code(403);
        exit('Akses ditolak.');
    }
    $id = (int) $pendaftaran['id'];
} elseif (isAdmin()) {
    $isAdminView = true;
    $id = sanitizeInt($_GET['id'] ?? 0);
    if ($id <= 0) {
        http_response_code(400);
        exit('Parameter id pendaftar tidak valid.');
    }
} else {
    http_response_code(403);
    exit('Silakan login terlebih dahulu.');
}

$pdo = getDB();
$stmt = $pdo->prepare(
    'SELECT p.*, u.email AS user_email, pg.label AS gelombang_label
     FROM pendaftaran p
     LEFT JOIN users u ON u.id = p.user_id
     LEFT JOIN pendaftaran_gelombang pg ON pg.id = p.gelombang_id
     WHERE p.id = ? LIMIT 1'
);
$stmt->execute([$id]);
$p = $stmt->fetch();
if (!$p) {
    http_response_code(404);
    exit('Data santri tidak ditemukan.');
}

// ── Pengaturan Kop Pondok ────────────────────────────────────
$pengaturan = [];
foreach ($pdo->query(
    "SELECT key_name, value FROM pengaturan
     WHERE key_name IN ('psb_tahun','kop_alamat','kop_telepon','kontak_alamat','kontak_telepon','kontak_whatsapp','kontak_email')"
)->fetchAll() as $row) {
    $pengaturan[$row['key_name']] = trim((string) ($row['value'] ?? ''));
}

$getSet = static fn(string $k, string $def = ''): string => !empty($pengaturan[$k]) ? $pengaturan[$k] : $def;
$tahunAjaran = $getSet('psb_tahun', date('Y') . '/' . (date('Y') + 1));
$kopAlamat = $getSet('kop_alamat', $getSet('kontak_alamat', 'Purworejo, Jurangjero, Ngawen, Gunungkidul, D.I. Yogyakarta 55853'));
$kopTelp   = $getSet('kop_telepon', $getSet('kontak_telepon', $getSet('kontak_whatsapp', '+62 838-7347-5302')));
$kopEmail  = $getSet('kontak_email', 'ponpesashiddiq2@gmail.com');

// ── Foto Santri 3x4 & Logo Kop ───────────────────────────────
$fotoPath = null;
$sf = $pdo->prepare("SELECT nama_file FROM berkas_santri WHERE pendaftaran_id = ? AND jenis = 'foto' ORDER BY id DESC LIMIT 1");
$sf->execute([$id]);
if ($fn = $sf->fetchColumn()) {
    $cand = UPLOADS_PATH . '/' . ltrim((string) $fn, '/');
    if (is_file($cand)) $fotoPath = $cand;
}

$logoPath = null;
$logoUrl = '';
$logoCandidates = ['kop-surat/logo.png', 'kop-surat/logo.jpg'];
if ($lf = getLogoFile()) $logoCandidates[] = $lf;
foreach ($logoCandidates as $rel) {
    $abs = ROOT_PATH . '/assets/img/' . $rel;
    if (is_file($abs)) {
        if ($logoUrl === '') $logoUrl = BASE_URL . '/assets/img/' . $rel;
        if ($logoPath === null && !str_ends_with(strtolower($rel), '.svg')) $logoPath = $abs;
    }
}

$labelJenjang = [
    'smp' => 'SMP Muhammadiyah Unggulan Ashidiq',
    'sma' => 'SMA Pondok Pesantren Ash-Shiddiq',
];
$jkLabel = ($p['jenis_kelamin'] ?? '') === 'P' ? 'Perempuan' : 'Laki-laki';
$jalurText = jalurPendaftaran()[$p['jalur'] ?? 'reguler'] ?? ($p['jalur'] ?? 'Reguler');
$ttlText = trim(($p['tempat_lahir'] ?? '') . ', ' . (!empty($p['tanggal_lahir']) ? date('d/m/Y', strtotime($p['tanggal_lahir'])) : '-'), ', ');
$ortuText = trim(($p['nama_ayah'] ?? '') . ' / ' . ($p['nama_ibu'] ?? ''), ' /');
$statusText = statusLabel((string) ($p['status'] ?? 'pending'));
$nomorFile = preg_replace('/[^A-Za-z0-9-]/', '-', (string) $p['nomor_daftar']);

/** Konversi gambar (PNG/JPG/WebP) ke JPEG RGB di atas latar putih untuk PDF. */
$toPdfJpeg = static function (?string $path): ?array {
    if (!$path || !is_file($path) || !function_exists('imagecreatefromstring')) return null;
    $raw = @file_get_contents($path);
    if ($raw === false || $raw === '') return null;
    $im = @imagecreatefromstring($raw);
    if (!$im) return null;
    $w = imagesx($im);
    $h = imagesy($im);
    if ($w <= 0 || $h <= 0) { imagedestroy($im); return null; }
    $bg = imagecreatetruecolor($w, $h);
    imagefill($bg, 0, 0, imagecolorallocate($bg, 255, 255, 255));
    imagecopy($bg, $im, 0, 0, 0, 0, $w, $h);
    imagedestroy($im);
    ob_start();
    imagejpeg($bg, null, 90);
    $jpeg = ob_get_clean();
    imagedestroy($bg);
    return ($jpeg !== false && $jpeg !== '') ? ['data' => $jpeg, 'w' => $w, 'h' => $h] : null;
};

// ── Mode Download PDF ────────────────────────────────────────
if (($_GET['download'] ?? '') === 'pdf') {
    $logoJpg = $toPdfJpeg($logoPath);
    $fotoJpg = $toPdfJpeg($fotoPath);

    $win = static function (string $t): string {
        $t = str_replace(["\r", "\n"], ' ', $t);
        if (function_exists('mb_detect_encoding') && mb_detect_encoding($t, 'UTF-8', true) !== 'UTF-8') return $t;
        return function_exists('mb_convert_encoding') ? (string) mb_convert_encoding($t, 'Windows-1252', 'UTF-8') : $t;
    };
    $esc = static fn(string $s): string => strtr($s, ['\\' => '\\\\', '(' => '\(', ')' => '\)']);
    $widths = [
        278,278,355,556,556,889,667,191,333,333,389,584,278,333,278,278,
        556,556,556,556,556,556,556,556,556,556,278,278,584,584,584,556,
        1015,667,667,722,722,667,611,778,722,278,500,667,556,833,722,778,
        667,778,722,667,611,722,667,944,667,667,611,278,278,278,469,556,
        333,556,556,500,556,556,278,556,556,222,222,500,222,833,556,556,
        556,556,333,500,278,556,500,722,500,500,500,334,260,334,584,
    ];
    $textWidth = static function (string $t, float $size) use ($widths): float {
        $w = 0; $ln = strlen($t);
        for ($i = 0; $i < $ln; $i++) {
            $c = ord($t[$i]);
            $w += ($c >= 32 && $c <= 126) ? $widths[$c - 32] : 500;
        }
        return $w / 1000 * $size;
    };
    $wrapText = static function (string $t, float $size, float $maxW) use ($win, $textWidth): array {
        $t = $win($t);
        $words = explode(' ', $t);
        $lines = []; $cur = '';
        foreach ($words as $wd) {
            $cand = $cur === '' ? $wd : $cur . ' ' . $wd;
            if ($cur !== '' && $textWidth($cand, $size) > $maxW) {
                $lines[] = $cur; $cur = $wd;
            } else {
                $cur = $cand;
            }
        }
        if ($cur !== '') $lines[] = $cur;
        return $lines ?: [''];
    };

    $W = 595.0; $H = 842.0; $M = 45.0;
    $stream = '';

    // ── Kop Pondok ──
    $kopTop = $H - 42;
    if ($logoJpg) {
        $lw = 48.0;
        $lh = $lw * ($logoJpg['h'] / $logoJpg['w']);
        if ($lh > 52) { $lh = 52; $lw = $lh * ($logoJpg['w'] / $logoJpg['h']); }
        $stream .= sprintf("q %.1f 0 0 %.1f %.1f %.1f cm /ImLogo Do Q\n", $lw, $lh, $M + 4, $kopTop - $lh);
    }
    $centerTxt = static function (string $txt, float $y, float $size, bool $bold = false, string $rgb = '0 0 0') use (&$stream, $W, $win, $esc, $textWidth): void {
        $t = $win($txt);
        $tw = $textWidth($t, $size);
        $x = ($W - $tw) / 2;
        $font = $bold ? 'F2' : 'F1';
        $stream .= sprintf("%s rg BT /%s %.1f Tf 1 0 0 1 %.1f %.1f Tm (%s) Tj ET\n", $rgb, $font, $size, $x, $y, $esc($t));
    };

    $centerTxt('PONDOK PESANTREN ASH-SHIDDIQ', $kopTop - 16, 15, true, '0.051 0.478 0.290');
    $centerTxt('LEMBAGA PENDIDIKAN ISLAM & TAHFIDZ AL-QUR\'AN', $kopTop - 30, 9.5, true, '0.2 0.2 0.2');
    $centerTxt($kopAlamat, $kopTop - 43, 8.5, false, '0.35 0.35 0.35');
    $centerTxt('Telp/WA: ' . $kopTelp . '  |  Email: ' . $kopEmail, $kopTop - 54, 8.5, false, '0.35 0.35 0.35');

    // Garis ganda Kop
    $lineY = $kopTop - 64;
    $stream .= sprintf("0.051 0.478 0.290 RG 2 w %.1f %.1f m %.1f %.1f l S\n", $M, $lineY, $W - $M, $lineY);
    $stream .= sprintf("0.051 0.478 0.290 RG 0.7 w %.1f %.1f m %.1f %.1f l S\n", $M, $lineY - 3, $W - $M, $lineY - 3);

    // Judul Kartu
    $centerTxt('KARTU PENDAFTARAN SANTRI BARU', $lineY - 26, 13, true, '0 0 0');
    $centerTxt('Tahun Ajaran ' . $tahunAjaran, $lineY - 40, 10, false, '0.35 0.35 0.35');

    // Kotak Utama Kartu
    $cardTop = $lineY - 54;
    $cardW = $W - 2 * $M;
    $rows = [
        ['Nomor Pendaftaran', (string) $p['nomor_daftar']],
        ['Nama Lengkap', (string) $p['nama_lengkap']],
        ['Tempat, Tgl Lahir', $ttlText],
        ['Jenis Kelamin', $jkLabel],
        ['Jenjang', $labelJenjang[$p['jenjang']] ?? (string) $p['jenjang']],
        ['Jalur / Gelombang', $jalurText . (!empty($p['gelombang_label']) ? ' (' . $p['gelombang_label'] . ')' : '')],
        ['Orang Tua / Wali', $ortuText ?: '-'],
        ['No. WA / HP Ortu', trim(($p['whatsapp'] ?? '') . ' / ' . ($p['hp_ortu'] ?? ''), ' /')],
        ['Asal Sekolah', (string) ($p['asal_sekolah'] ?: '-')],
        ['Alamat', (string) ($p['alamat'] ?: '-')],
        ['Status Pendaftaran', $statusText],
    ];
    if (!empty($p['nomor_induk'])) {
        array_splice($rows, 1, 0, [['Nomor Induk (NIPD)', (string) $p['nomor_induk']]]);
    }

    // Hitung baris teks sisi kiri (lebar 385pt) & kotak foto di kanan (95pt)
    $leftX = $M + 14;
    $lblW = 115.0;
    $valW = 265.0;
    $yCur = $cardTop - 22;
    $rowBlocks = [];
    foreach ($rows as [$lbl, $val]) {
        $wrapped = $wrapText($val, 10, $valW);
        $rh = max(1, count($wrapped)) * 13.5 + 5;
        $rowBlocks[] = [$lbl, $wrapped, $rh];
        $yCur -= $rh;
    }
    $cardBottom = min($yCur - 14, $cardTop - 175);
    $cardH = $cardTop - $cardBottom;

    // Background header strip + border luar kartu
    $stream .= sprintf("0.969 0.988 0.976 rg %.1f %.1f %.1f %.1f re f n\n", $M, $cardBottom, $cardW, $cardH);
    $stream .= sprintf("0.051 0.478 0.290 RG 1.2 w %.1f %.1f %.1f %.1f re S n\n", $M, $cardBottom, $cardW, $cardH);

    // Tulis baris data
    $ry = $cardTop - 20;
    foreach ($rowBlocks as [$lbl, $wrapped, $rh]) {
        $stream .= sprintf("0.35 0.35 0.35 rg BT /F2 9.5 Tf 1 0 0 1 %.1f %.1f Tm (%s) Tj ET\n", $leftX, $ry, $esc($win($lbl)));
        $stream .= sprintf("0.35 0.35 0.35 rg BT /F1 9.5 Tf 1 0 0 1 %.1f %.1f Tm (:) Tj ET\n", $leftX + $lblW - 8, $ry);
        $vy = $ry;
        foreach ($wrapped as $ln) {
            $stream .= sprintf("0 0 0 rg BT /F1 10 Tf 1 0 0 1 %.1f %.1f Tm (%s) Tj ET\n", $leftX + $lblW, $vy, $esc($win($ln)));
            $vy -= 13.5;
        }
        $ry -= $rh;
    }

    // Kotak Pas Foto 3x4 di kanan atas kartu
    $fotoBoxW = 86.0; $fotoBoxH = 114.0;
    $fotoBoxX = $W - $M - 16 - $fotoBoxW;
    $fotoBoxY = $cardTop - 16 - $fotoBoxH;
    $stream .= sprintf("1 1 1 rg %.1f %.1f %.1f %.1f re f n\n", $fotoBoxX, $fotoBoxY, $fotoBoxW, $fotoBoxH);
    $stream .= sprintf("0.75 0.75 0.75 RG 0.8 w %.1f %.1f %.1f %.1f re S n\n", $fotoBoxX, $fotoBoxY, $fotoBoxW, $fotoBoxH);
    if ($fotoJpg) {
        $ratio = min($fotoBoxW / $fotoJpg['w'], $fotoBoxH / $fotoJpg['h']);
        $fw = $fotoJpg['w'] * $ratio;
        $fh = $fotoJpg['h'] * $ratio;
        $fx = $fotoBoxX + ($fotoBoxW - $fw) / 2;
        $fy = $fotoBoxY + ($fotoBoxH - $fh) / 2;
        $stream .= sprintf("q %.1f 0 0 %.1f %.1f %.1f cm /ImFoto Do Q\n", $fw, $fh, $fx, $fy);
    } else {
        $phTxt = $win('Foto 3x4');
        $ptw = $textWidth($phTxt, 9);
        $stream .= sprintf("0.55 0.55 0.55 rg BT /F1 9 Tf 1 0 0 1 %.1f %.1f Tm (%s) Tj ET\n", $fotoBoxX + ($fotoBoxW - $ptw) / 2, $fotoBoxY + $fotoBoxH / 2 - 3, $esc($phTxt));
    }

    // Area tanda tangan panitia di bawah kartu
    $sigY = $cardBottom - 26;
    $stream .= sprintf("0.4 0.4 0.4 rg BT /F1 8.5 Tf 1 0 0 1 %.1f %.1f Tm (%s) Tj ET\n", $M, $sigY, $esc($win('Tanggal cetak: ' . date('d/m/Y H:i') . ' WIB  |  Simpan kartu ini sebagai bukti pendaftaran resmi.')));
    $sigX = $W - $M - 175;
    $stream .= sprintf("0 0 0 rg BT /F1 9.5 Tf 1 0 0 1 %.1f %.1f Tm (%s) Tj ET\n", $sigX, $sigY - 22, $esc($win('Ngawen, ' . date('d/m/Y'))));
    $stream .= sprintf("0 0 0 rg BT /F2 9.5 Tf 1 0 0 1 %.1f %.1f Tm (%s) Tj ET\n", $sigX, $sigY - 35, $esc($win('Panitia PSB Ash-Shiddiq')));
    $stream .= sprintf("0.3 0.3 0.3 rg BT /F1 8.5 Tf 1 0 0 1 %.1f %.1f Tm (%s) Tj ET\n", $sigX, $sigY - 72, $esc($win('( _______________________ )')));

    // Susun objek PDF
    $objs = [];
    $objs[1] = '<< /Type /Catalog /Pages 2 0 R >>';
    $objs[2] = '<< /Type /Pages /Kids [5 0 R] /Count 1 >>';
    $objs[3] = '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>';
    $objs[4] = '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica-Bold /Encoding /WinAnsiEncoding >>';

    $xobj = [];
    $nextObj = 7;
    if ($logoJpg) {
        $logoId = $nextObj++;
        $xobj[] = '/ImLogo ' . $logoId . ' 0 R';
        $objs[$logoId] = '<< /Type /XObject /Subtype /Image /Width ' . $logoJpg['w'] . ' /Height ' . $logoJpg['h'] . ' /ColorSpace /DeviceRGB /BitsPerComponent 8 /Filter /DCTDecode /Length ' . strlen($logoJpg['data']) . " >>\nstream\n" . $logoJpg['data'] . "\nendstream";
    }
    if ($fotoJpg) {
        $fotoId = $nextObj++;
        $xobj[] = '/ImFoto ' . $fotoId . ' 0 R';
        $objs[$fotoId] = '<< /Type /XObject /Subtype /Image /Width ' . $fotoJpg['w'] . ' /Height ' . $fotoJpg['h'] . ' /ColorSpace /DeviceRGB /BitsPerComponent 8 /Filter /DCTDecode /Length ' . strlen($fotoJpg['data']) . " >>\nstream\n" . $fotoJpg['data'] . "\nendstream";
    }
    $xobjRef = $xobj ? ' /XObject << ' . implode(' ', $xobj) . ' >>' : '';
    $objs[5] = '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 595 842] /Resources << /Font << /F1 3 0 R /F2 4 0 R >>' . $xobjRef . ' >> /Contents 6 0 R >>';
    $objs[6] = '<< /Length ' . strlen($stream) . " >>\nstream\n" . $stream . 'endstream';

    $pdf = "%PDF-1.4\n";
    $offs = [];
    ksort($objs);
    foreach ($objs as $num => $body) {
        $offs[$num] = strlen($pdf);
        $pdf .= $num . " 0 obj\n" . $body . "\nendobj\n";
    }
    $xref = strlen($pdf);
    $pdf .= "xref\n0 " . (count($objs) + 1) . "\n0000000000 65535 f \n";
    for ($i = 1; $i <= count($objs); $i++) {
        $pdf .= sprintf("%010d 00000 n \n", $offs[$i]);
    }
    $pdf .= "trailer\n<< /Size " . (count($objs) + 1) . " /Root 1 0 R >>\nstartxref\n" . $xref . "\n%%EOF";

    while (ob_get_level()) ob_end_clean();
    header('Content-Type: application/pdf');
    header('Content-Disposition: attachment; filename="kartu-santri-' . $nomorFile . '.pdf"');
    header('Content-Length: ' . strlen($pdf));
    header('Cache-Control: no-store');
    echo $pdf;
    exit;
}

// ── Mode Preview HTML Siap Cetak ─────────────────────────────
$fotoDataUri = '';
if ($fotoPath) {
    $mime = detectMimeType($fotoPath);
    $raw = @file_get_contents($fotoPath);
    if ($raw !== false && str_starts_with($mime, 'image/')) {
        $fotoDataUri = 'data:' . $mime . ';base64,' . base64_encode($raw);
    }
}
$pdfUrl = BASE_URL . '/kartu-santri?' . ($isAdminView ? ('id=' . $id . '&') : '') . 'download=pdf';
$backUrl = $isAdminView ? (BASE_URL . '/admin/psb') : (BASE_URL . '/profil-santri');

header('Content-Type: text/html; charset=UTF-8');
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="robots" content="noindex, nofollow">
<title>Kartu Santri — <?= e($p['nomor_daftar']) ?> | <?= e(APP_NAME) ?></title>
<style>
* { box-sizing: border-box; margin: 0; padding: 0; }
body { font-family: 'Plus Jakarta Sans', Arial, sans-serif; background: #eef2ef; color: #1b2820; padding: 20px; }
.toolbar {
    max-width: 820px; margin: 0 auto 16px; display: flex; gap: 10px; flex-wrap: wrap; align-items: center;
    background: #0d7a4a; padding: 12px 16px; border-radius: 8px; box-shadow: 0 2px 12px rgba(0,0,0,0.12);
}
.toolbar a, .toolbar button {
    padding: 9px 16px; border: none; border-radius: 5px; font-size: 13px; font-weight: 700;
    cursor: pointer; text-decoration: none; display: inline-flex; align-items: center; gap: 6px;
}
.btn-dl { background: #fff; color: #0d7a4a; }
.btn-dl:hover { background: #f3f7f4; }
.btn-back { background: rgba(255,255,255,0.16); color: #fff; margin-left: auto; }
.btn-back:hover { background: rgba(255,255,255,0.26); }

.sheet {
    max-width: 820px; margin: 0 auto; background: #fff; padding: 34px 40px;
    border-radius: 8px; box-shadow: 0 4px 24px rgba(0,0,0,0.08);
}
.kop {
    display: flex; align-items: center; gap: 18px; padding-bottom: 14px;
    border-bottom: 3px double #0d7a4a; margin-bottom: 20px;
}
.kop img { width: 72px; height: 72px; object-fit: contain; flex-shrink: 0; }
.kop-text { flex: 1; text-align: center; }
.kop-text h1 { font-size: 20px; color: #0d7a4a; letter-spacing: 0.6px; margin-bottom: 3px; }
.kop-text .sub { font-size: 12px; font-weight: 700; color: #334; letter-spacing: 1px; margin-bottom: 4px; }
.kop-text p { font-size: 12px; color: #555; line-height: 1.5; }

.card-title { text-align: center; margin-bottom: 18px; }
.card-title h2 { font-size: 16px; letter-spacing: 0.5px; color: #111; }
.card-title p { font-size: 12px; color: #666; margin-top: 2px; }

.card-box {
    border: 1.5px solid #0d7a4a; border-radius: 8px; padding: 22px 24px;
    background: linear-gradient(180deg, #f9fcfa 0%, #fff 100%);
    display: grid; grid-template-columns: 1fr 124px; gap: 22px; align-items: start;
}
.data-table { width: 100%; border-collapse: collapse; font-size: 13px; }
.data-table th, .data-table td { padding: 6px 4px; vertical-align: top; text-align: left; }
.data-table th { width: 155px; color: #555; font-weight: 600; }
.data-table td.sep { width: 14px; color: #666; }
.data-table td.val { color: #111; font-weight: 500; }
.nomor-hl { font-size: 16px; font-weight: 800; color: #0d7a4a; letter-spacing: 1px; }
.status-pill {
    display: inline-block; padding: 2px 10px; border-radius: 999px;
    font-size: 11px; font-weight: 700; background: #e8f5ee; color: #0d7a4a;
}

.foto-box {
    width: 124px; height: 162px; border: 1px solid #cbd5cf; border-radius: 4px;
    background: #fff; display: flex; align-items: center; justify-content: center; overflow: hidden;
}
.foto-box img { width: 100%; height: 100%; object-fit: cover; }
.foto-box span { font-size: 12px; color: #888; }

.card-footer {
    margin-top: 22px; display: flex; justify-content: space-between; align-items: flex-end;
    gap: 20px; font-size: 12px; color: #444;
}
.sig-box { text-align: center; min-width: 210px; }
.sig-box .space { height: 52px; }

@media (max-width: 640px) {
    .sheet { padding: 20px 16px; }
    .kop { flex-direction: column; gap: 8px; }
    .card-box { grid-template-columns: 1fr; }
    .foto-box { margin: 0 auto; }
    .card-footer { flex-direction: column; align-items: flex-start; }
}
@media print {
    body { background: #fff; padding: 0; }
    .toolbar { display: none; }
    .sheet { box-shadow: none; max-width: none; padding: 12px 18px; }
}
</style>
</head>
<body>
<div class="toolbar">
    <a href="<?= e($pdfUrl) ?>" class="btn-dl">Download PDF</a>
    <button type="button" onclick="window.print()" class="btn-dl">Cetak</button>
    <a href="<?= e($backUrl) ?>" class="btn-back">&larr; Kembali</a>
</div>

<div class="sheet">
    <div class="kop">
        <?php if ($logoUrl !== ''): ?>
            <img src="<?= e($logoUrl) ?>" alt="Logo <?= e(APP_NAME) ?>">
        <?php endif; ?>
        <div class="kop-text">
            <h1>PONDOK PESANTREN ASH-SHIDDIQ</h1>
            <div class="sub">LEMBAGA PENDIDIKAN ISLAM &amp; TAHFIDZ AL-QUR'AN</div>
            <p><?= e($kopAlamat) ?><br>Telp/WA: <?= e($kopTelp) ?> &middot; Email: <?= e($kopEmail) ?></p>
        </div>
    </div>

    <div class="card-title">
        <h2>KARTU PENDAFTARAN SANTRI BARU</h2>
        <p>Tahun Ajaran <?= e($tahunAjaran) ?></p>
    </div>

    <div class="card-box">
        <table class="data-table">
            <tr><th>Nomor Pendaftaran</th><td class="sep">:</td><td class="val nomor-hl"><?= e($p['nomor_daftar']) ?></td></tr>
            <?php if (!empty($p['nomor_induk'])): ?>
            <tr><th>Nomor Induk (NIPD)</th><td class="sep">:</td><td class="val"><strong><?= e($p['nomor_induk']) ?></strong></td></tr>
            <?php endif; ?>
            <tr><th>Nama Lengkap</th><td class="sep">:</td><td class="val"><strong><?= e($p['nama_lengkap']) ?></strong></td></tr>
            <tr><th>Tempat, Tgl Lahir</th><td class="sep">:</td><td class="val"><?= e($ttlText) ?></td></tr>
            <tr><th>Jenis Kelamin</th><td class="sep">:</td><td class="val"><?= e($jkLabel) ?></td></tr>
            <tr><th>Jenjang</th><td class="sep">:</td><td class="val"><?= e($labelJenjang[$p['jenjang']] ?? $p['jenjang']) ?></td></tr>
            <tr><th>Jalur / Gelombang</th><td class="sep">:</td><td class="val"><?= e($jalurText . (!empty($p['gelombang_label']) ? ' (' . $p['gelombang_label'] . ')' : '')) ?></td></tr>
            <tr><th>Orang Tua / Wali</th><td class="sep">:</td><td class="val"><?= e($ortuText ?: '-') ?></td></tr>
            <tr><th>No. WA / HP Ortu</th><td class="sep">:</td><td class="val"><?= e(trim(($p['whatsapp'] ?? '') . ' / ' . ($p['hp_ortu'] ?? ''), ' /')) ?></td></tr>
            <tr><th>Asal Sekolah</th><td class="sep">:</td><td class="val"><?= e($p['asal_sekolah'] ?: '-') ?></td></tr>
            <tr><th>Alamat</th><td class="sep">:</td><td class="val"><?= e($p['alamat'] ?: '-') ?></td></tr>
            <tr><th>Status Pendaftaran</th><td class="sep">:</td><td class="val"><span class="status-pill"><?= e($statusText) ?></span></td></tr>
        </table>

        <div class="foto-box">
            <?php if ($fotoDataUri !== ''): ?>
                <img src="<?= e($fotoDataUri) ?>" alt="Pas Foto <?= e($p['nama_lengkap']) ?>">
            <?php else: ?>
                <span>Foto 3x4</span>
            <?php endif; ?>
        </div>
    </div>

    <div class="card-footer">
        <div>
            <div>Tanggal cetak: <?= date('d/m/Y H:i') ?> WIB</div>
            <div>Simpan kartu ini sebagai bukti pendaftaran resmi.</div>
        </div>
        <div class="sig-box">
            <div>Ngawen, <?= date('d/m/Y') ?></div>
            <div><strong>Panitia PSB Ash-Shiddiq</strong></div>
            <div class="space"></div>
            <div>( _______________________ )</div>
        </div>
    </div>
</div>
</body>
</html>
<?php exit;
