<?php
/**
 * Export data PSB ke Excel (.xls) atau PDF landscape (multi-halaman).
 * Dipanggil oleh admin/psb.php setelah auth & $where/$params terbentuk.
 */
if (!defined('ROOT_PATH') || !isset($pdo, $where, $params, $exportFmt)) {
    http_response_code(403);
    exit;
}

$sqlExport = "SELECT p.*, u.email AS user_email, pg.label AS gelombang_label
              FROM pendaftaran p
              LEFT JOIN users u ON u.id = p.user_id
              LEFT JOIN pendaftaran_gelombang pg ON pg.id = p.gelombang_id
              $where
              ORDER BY FIELD(p.status, 'menunggu-verifikasi', 'tes-selesai', 'pending', 'diterima', 'daftar-ulang', 'ditolak'), p.created_at DESC";
$stExp = $pdo->prepare($sqlExport);
$stExp->execute($params);
$rows = $stExp->fetchAll();

$labelJenjangExp = ['smp' => 'SMP', 'sma' => 'SMA'];
$labelJalurExp   = jalurPendaftaran();
$labelQuranExp   = [
    'belum-bisa'   => 'Belum Bisa Membaca',
    'bisa-membaca' => 'Bisa Membaca',
    'tartil'       => 'Tartil',
    'hafal-juz-30' => 'Hafal Juz 30',
    'hafal-lebih'  => 'Hafal Lebih dari Juz 30',
];
$filterInfo = [];
if (!empty($filterStat)) $filterInfo[] = 'Status: ' . statusLabel($filterStat);
if (!empty($cari))       $filterInfo[] = 'Cari: ' . $cari;
$filterText = $filterInfo ? implode(' | ', $filterInfo) : 'Semua Pendaftar';
$dateSlug   = date('Ymd-Hi');

// ── EXPORT EXCEL (.xls HTML table — aman leading zero & semua locale Excel) ──
if ($exportFmt === 'excel') {
    $safeCell = static function (mixed $v): string {
        $s = (string) ($v ?? '');
        if ($s !== '' && in_array($s[0], ['=', '+', '-', '@', "\t", "\r"], true)) {
            $s = "'" . $s;
        }
        return htmlspecialchars($s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    };

    while (ob_get_level()) ob_end_clean();
    header('Content-Type: application/vnd.ms-excel; charset=UTF-8');
    header('Content-Disposition: attachment; filename="data-psb-' . $dateSlug . '.xls"');
    header('Cache-Control: no-store');

    echo "\xEF\xBB\xBF";
    echo '<html xmlns:o="urn:schemas-microsoft-com:office:office" xmlns:x="urn:schemas-microsoft-com:office:excel">';
    echo '<head><meta charset="UTF-8"><style>';
    echo 'table{border-collapse:collapse;font-family:Calibri,Arial,sans-serif;font-size:11pt;}';
    echo 'th,td{border:1px solid #cbd5e1;padding:6px 8px;vertical-align:top;}';
    echo 'th{background:#0d7a4a;color:#ffffff;font-weight:bold;text-align:left;}';
    echo '.txt{mso-number-format:"\\@";}';
    echo '</style></head><body>';
    echo '<h3>REKAP DATA PENDAFTAR PSB — ' . $safeCell(APP_NAME) . '</h3>';
    echo '<p>Filter: ' . $safeCell($filterText) . ' &middot; Total: ' . count($rows) . ' pendaftar &middot; Dicetak: ' . date('d/m/Y H:i') . ' WIB</p>';
    echo '<table><thead><tr>';
    $headers = [
        'No', 'Nomor Pendaftaran', 'Nomor Induk (NIPD)', 'Nama Lengkap', 'Tempat Lahir',
        'Tanggal Lahir', 'Jenis Kelamin', 'Jenjang', 'Jalur', 'Gelombang',
        'No. WhatsApp', 'Email Akun', 'Nama Ayah', 'Nama Ibu', 'HP Orang Tua',
        'Pekerjaan Orang Tua', 'Alamat', 'Asal Sekolah', 'Tahun Lulus',
        'Kemampuan Al-Qur\'an', 'Jumlah Hafalan', 'Status', 'Tanggal Daftar',
    ];
    foreach ($headers as $h) echo '<th>' . $safeCell($h) . '</th>';
    echo '</tr></thead><tbody>';

    foreach ($rows as $i => $r) {
        $jk  = ($r['jenis_kelamin'] ?? '') === 'P' ? 'Perempuan' : 'Laki-laki';
        $tglLahir = !empty($r['tanggal_lahir']) ? date('d/m/Y', strtotime($r['tanggal_lahir'])) : '-';
        $tglDaftar = !empty($r['created_at']) ? date('d/m/Y H:i', strtotime($r['created_at'])) : '-';
        $jalurTxt = $labelJalurExp[$r['jalur'] ?? 'reguler'] ?? ($r['jalur'] ?? '-');
        if (!empty($r['jalur_detail'])) $jalurTxt .= ' (' . $r['jalur_detail'] . ')';

        echo '<tr>';
        echo '<td>' . ($i + 1) . '</td>';
        echo '<td class="txt">' . $safeCell($r['nomor_daftar']) . '</td>';
        echo '<td class="txt">' . $safeCell($r['nomor_induk'] ?: '-') . '</td>';
        echo '<td>' . $safeCell($r['nama_lengkap']) . '</td>';
        echo '<td>' . $safeCell($r['tempat_lahir']) . '</td>';
        echo '<td class="txt">' . $safeCell($tglLahir) . '</td>';
        echo '<td>' . $safeCell($jk) . '</td>';
        echo '<td>' . $safeCell($labelJenjangExp[$r['jenjang']] ?? $r['jenjang']) . '</td>';
        echo '<td>' . $safeCell($jalurTxt) . '</td>';
        echo '<td>' . $safeCell($r['gelombang_label'] ?: '-') . '</td>';
        echo '<td class="txt">' . $safeCell($r['whatsapp']) . '</td>';
        echo '<td>' . $safeCell($r['user_email'] ?: '-') . '</td>';
        echo '<td>' . $safeCell($r['nama_ayah']) . '</td>';
        echo '<td>' . $safeCell($r['nama_ibu']) . '</td>';
        echo '<td class="txt">' . $safeCell($r['hp_ortu']) . '</td>';
        echo '<td>' . $safeCell($r['pekerjaan_ortu'] ?: '-') . '</td>';
        echo '<td>' . $safeCell($r['alamat']) . '</td>';
        echo '<td>' . $safeCell($r['asal_sekolah']) . '</td>';
        echo '<td>' . $safeCell($r['tahun_lulus'] ?: '-') . '</td>';
        echo '<td>' . $safeCell($labelQuranExp[$r['kemampuan_quran']] ?? $r['kemampuan_quran']) . '</td>';
        echo '<td>' . $safeCell($r['jumlah_hafalan'] ?: '-') . '</td>';
        echo '<td>' . $safeCell(statusLabel((string) $r['status'])) . '</td>';
        echo '<td class="txt">' . $safeCell($tglDaftar) . '</td>';
        echo '</tr>';
    }
    if (empty($rows)) {
        echo '<tr><td colspan="' . count($headers) . '">Tidak ada data pendaftaran.</td></tr>';
    }
    echo '</tbody></table></body></html>';
    exit;
}

// ── EXPORT PDF (A4 Landscape, multi-halaman) ──────────────────
$W = 842.0; $H = 595.0; $M = 30.0; $MB = 36.0;
$colHeaders = ['No', 'Nomor', 'Nama Santri', 'JK', 'Jenjang', 'Jalur', 'Gelombang', 'WhatsApp', 'Asal Sekolah', 'Status', 'Tgl Daftar'];
$colWidths  = [26, 68, 130, 24, 46, 78, 74, 82, 116, 78, 60]; // total = 782pt

$widthsTbl = [
    278,278,355,556,556,889,667,191,333,333,389,584,278,333,278,278,
    556,556,556,556,556,556,556,556,556,556,278,278,584,584,584,556,
    1015,667,667,722,722,667,611,778,722,278,500,667,556,833,722,778,
    667,778,722,667,611,722,667,944,667,667,611,278,278,278,469,556,
    333,556,556,500,556,556,278,556,556,222,222,500,222,833,556,556,
    556,556,333,500,278,556,500,722,500,500,500,334,260,334,584,
];
$win = static function (string $t): string {
    $t = str_replace(["\r", "\n"], ' ', $t);
    if (function_exists('mb_detect_encoding') && mb_detect_encoding($t, 'UTF-8', true) !== 'UTF-8') return $t;
    return function_exists('mb_convert_encoding') ? (string) mb_convert_encoding($t, 'Windows-1252', 'UTF-8') : $t;
};
$esc = static fn(string $s): string => strtr($s, ['\\' => '\\\\', '(' => '\(', ')' => '\)']);
$txtW = static function (string $t, float $size) use ($widthsTbl): float {
    $w = 0; $ln = strlen($t);
    for ($i = 0; $i < $ln; $i++) {
        $c = ord($t[$i]);
        $w += ($c >= 32 && $c <= 126) ? $widthsTbl[$c - 32] : 500;
    }
    return $w / 1000 * $size;
};
$wrap = static function (string $t, float $size, float $maxW) use ($win, $txtW): array {
    $t = $win($t);
    $words = explode(' ', $t);
    $lines = []; $cur = '';
    foreach ($words as $wd) {
        $cand = $cur === '' ? $wd : $cur . ' ' . $wd;
        if ($cur !== '' && $txtW($cand, $size) > $maxW) {
            $lines[] = $cur; $cur = $wd;
        } else {
            $cur = $cand;
        }
    }
    if ($cur !== '') $lines[] = $cur;
    return $lines ?: [''];
};

$pages = [];
$cur = '';
$y = $H - 36;

$drawRow = static function (array $cells, bool $isHead = false) use (&$cur, &$y, $M, $colWidths, $wrap, $esc): float {
    $size = $isHead ? 8.5 : 8.0;
    $lineH = $size * 1.25;
    $pad = 4.0;
    $wrapped = [];
    $maxL = 1;
    foreach ($cells as $i => $val) {
        $lns = $wrap((string) $val, $size, $colWidths[$i] - 2 * $pad);
        $wrapped[$i] = $lns;
        $maxL = max($maxL, count($lns));
    }
    $rowH = $maxL * $lineH + 2 * $pad;
    $x = $M;
    $fill = $isHead ? '0.051 0.478 0.290 rg' : '1 1 1 rg';
    $txtRgb = $isHead ? '1 1 1 rg' : '0 0 0 rg';
    $font = $isHead ? 'F2' : 'F1';

    for ($i = 0; $i < count($cells); $i++) {
        $cw = $colWidths[$i];
        $cur .= sprintf("%s %.1f %.1f %.1f %.1f re f n\n", $fill, $x, $y - $rowH, $cw, $rowH);
        $cur .= sprintf("0.80 0.84 0.82 RG 0.5 w %.1f %.1f %.1f %.1f re S n\n", $x, $y - $rowH, $cw, $rowH);
        $ty = $y - $pad - $size;
        foreach ($wrapped[$i] as $ln) {
            $cur .= sprintf("%s BT /%s %.1f Tf 1 0 0 1 %.1f %.1f Tm (%s) Tj ET\n", $txtRgb, $font, $size, $x + $pad, $ty, $esc($ln));
            $ty -= $lineH;
        }
        $x += $cw;
    }
    $y -= $rowH;
    return $rowH;
};

$startPage = static function (bool $first = false) use (&$pages, &$cur, &$y, $H, $M, $W, $win, $esc, $filterText, $rows, $colHeaders, $drawRow): void {
    if ($cur !== '') $pages[] = $cur;
    $cur = '';
    $y = $H - 34;
    if ($first) {
        $cur .= sprintf("0.051 0.478 0.290 rg BT /F2 13 Tf 1 0 0 1 %.1f %.1f Tm (%s) Tj ET\n", $M, $y, $esc($win('REKAP DATA PENDAFTAR PSB — PONDOK PESANTREN ASH-SHIDDIQ')));
        $y -= 15;
        $meta = 'Filter: ' . $filterText . '  |  Total: ' . count($rows) . ' pendaftar  |  Dicetak: ' . date('d/m/Y H:i') . ' WIB';
        $cur .= sprintf("0.35 0.35 0.35 rg BT /F1 8.5 Tf 1 0 0 1 %.1f %.1f Tm (%s) Tj ET\n", $M, $y, $esc($win($meta)));
        $y -= 14;
    }
    $drawRow($colHeaders, true);
};

$startPage(true);
if (empty($rows)) {
    $cur .= sprintf("0.4 0.4 0.4 rg BT /F1 9.5 Tf 1 0 0 1 %.1f %.1f Tm (%s) Tj ET\n", $M, $y - 16, $esc($win('Tidak ada data pendaftaran sesuai filter.')));
} else {
    foreach ($rows as $i => $r) {
        $jalurTxt = $labelJalurExp[$r['jalur'] ?? 'reguler'] ?? ($r['jalur'] ?? '-');
        $cells = [
            (string) ($i + 1),
            (string) $r['nomor_daftar'],
            (string) $r['nama_lengkap'],
            (string) ($r['jenis_kelamin'] ?? '-'),
            (string) ($labelJenjangExp[$r['jenjang']] ?? $r['jenjang']),
            (string) $jalurTxt,
            (string) ($r['gelombang_label'] ?: '-'),
            (string) $r['whatsapp'],
            (string) ($r['asal_sekolah'] ?: '-'),
            statusLabel((string) $r['status']),
            !empty($r['created_at']) ? date('d/m/Y', strtotime($r['created_at'])) : '-',
        ];
        // Estimasi tinggi baris sebelum gambar agar pindah halaman rapi
        $estLines = 1;
        foreach ($cells as $ci => $cv) {
            $estLines = max($estLines, count($wrap($cv, 8.0, $colWidths[$ci] - 8)));
        }
        $estH = $estLines * 10.0 + 8.0;
        if ($y - $estH < $MB) {
            $startPage(false);
        }
        $drawRow($cells, false);
    }
}
if ($cur !== '') $pages[] = $cur;

$np = count($pages);
for ($pi = 0; $pi < $np; $pi++) {
    $pages[$pi] .= sprintf("0.45 0.45 0.45 rg BT /F1 8 Tf 1 0 0 1 %.1f 18 Tm (%s) Tj ET\n", $W - $M - 68, $esc($win('Halaman ' . ($pi + 1) . ' / ' . $np)));
}

$firstPg = 5;
$firstSt = $firstPg + $np;
$objs = [];
$objs[1] = '<< /Type /Catalog /Pages 2 0 R >>';
$kids = [];
for ($i = 0; $i < $np; $i++) $kids[] = ($firstPg + $i) . ' 0 R';
$objs[2] = '<< /Type /Pages /Kids [' . implode(' ', $kids) . '] /Count ' . $np . ' >>';
$objs[3] = '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>';
$objs[4] = '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica-Bold /Encoding /WinAnsiEncoding >>';
for ($i = 0; $i < $np; $i++) {
    $objs[$firstPg + $i] = '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 842 595] /Resources << /Font << /F1 3 0 R /F2 4 0 R >> >> /Contents ' . ($firstSt + $i) . ' 0 R >>';
    $objs[$firstSt + $i] = '<< /Length ' . strlen($pages[$i]) . " >>\nstream\n" . $pages[$i] . 'endstream';
}

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
header('Content-Disposition: attachment; filename="data-psb-' . $dateSlug . '.pdf"');
header('Content-Length: ' . strlen($pdf));
header('Cache-Control: no-store');
echo $pdf;
exit;
