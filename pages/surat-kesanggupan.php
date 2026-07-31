<?php
/**
 * Ringkasan Pendaftaran Santri Baru — output .docx (Word asli).
 * Akses: santri (session) atau admin (dengan ?id=).
 * Fallback: PDF ringan jika ekstensi ZipArchive tidak tersedia.
 */

// ── Akses ────────────────────────────────────────────────────
if (!empty($_SESSION['santri_id'])) {
    $id = (int) $_SESSION['santri_id'];
} elseif (isLoggedIn()) {
    requireAdmin();
    $id = sanitizeInt($_GET['id'] ?? 0);
} else {
    http_response_code(403);
    exit('Akses ditolak.');
}
if ($id <= 0) { http_response_code(404); exit; }

$pdo = getDB();
$s   = $pdo->prepare('SELECT * FROM pendaftaran WHERE id=?');
$s->execute([$id]);
$p = $s->fetch();
if (!$p) { http_response_code(404); exit; }

$items = $pdo->prepare('SELECT * FROM pembiayaan WHERE pendaftaran_id=? ORDER BY urutan, id');
$items->execute([$id]);
$items = $items->fetchAll();

// Hanya tampilkan item yang dipilih (administrasi/wakaf/syahriyah) + item tunggal
$itemsShown = array_values(array_filter($items, function ($it) {
    if (in_array($it['jenis'], ['administrasi', 'wakaf', 'syahriyah'], true)) {
        return (int) $it['dipilih'] === 1;
    }
    return true;
}));

// Tanda tangan (PNG mentah untuk di-embed ke docx)
$signBytes = '';
$ttdW = 170; $ttdH = 65;
if (!empty($p['kesanggupan_sign'])) {
    $signPath = UPLOADS_PATH . '/santri/' . basename($p['kesanggupan_sign']);
    if (is_file($signPath)) {
        $signBytes = (string) file_get_contents($signPath);
        $sz = @getimagesize($signPath);
        if ($sz && $sz[0] > 0) {
            $ttdH = (int) round(170 * ($sz[1] / $sz[0]));
        }
    }
}

$labelJenjang = ['mts' => 'SMP', 'ma' => 'SMA', 'tahfidz-intensif' => 'Tahfidz Intensif'];
$labelQuran = [
    'belum-bisa' => 'Belum Bisa Membaca', 'bisa-membaca' => 'Bisa Membaca',
    'tartil' => 'Tartil', 'hafal-juz-30' => 'Hafal Juz 30', 'hafal-lebih' => 'Hafal Lebih dari Juz 30',
];
$jkLabel = $p['jenis_kelamin'] === 'P' ? 'Perempuan' : 'Laki-laki';
$nomorFile = preg_replace('/[^A-Za-z0-9-]/', '-', $p['nomor_daftar']);

// ── Helper XML docx ──────────────────────────────────────────
function ringkasanXml(string $s): string {
    return htmlspecialchars($s, ENT_XML1 | ENT_QUOTES, 'UTF-8');
}
function ringkasanRun(string $text, int $sz, bool $bold = false): string {
    $b = $bold ? '<w:b/>' : '';
    return '<w:r><w:rPr><w:rFonts w:ascii="Arial" w:hAnsi="Arial"/><w:sz w:val="' . $sz . '"/><w:szCs w:val="' . $sz . '"/>' . $b . '</w:rPr><w:t xml:space="preserve">' . ringkasanXml($text) . '</w:t></w:r>';
}
function ringkasanP(string $inner, string $align = ''): string {
    $jc = $align !== '' ? '<w:pPr><w:jc w:val="' . $align . '"/></w:pPr>' : '';
    return '<w:p>' . $jc . $inner . '</w:p>';
}
function ringkasanTitle(string $t): string {
    return ringkasanP(ringkasanRun($t, 32, true), 'center');
}
function ringkasanCenter(string $t, int $sz, bool $bold = false): string {
    return ringkasanP(ringkasanRun($t, $sz, $bold), 'center');
}
function ringkasanHeading(string $t): string {
    return '<w:p><w:pPr><w:spacing w:before="200" w:after="80"/></w:pPr>' . ringkasanRun($t, 26, true) . '</w:p>';
}
function ringkasanRow(string $label, string $value): string {
    return '<w:p>' . ringkasanRun($label . ' : ', 22, true) . ringkasanRun($value, 22) . '</w:p>';
}
function ringkasanText(string $t, int $sz = 21, bool $bold = false): string {
    return ringkasanP(ringkasanRun($t, $sz, $bold));
}

// ── Susun isi dokumen ────────────────────────────────────────
$body = '';
$body .= ringkasanTitle('RINGKASAN PENDAFTARAN SANTRI BARU');
$body .= ringkasanCenter('Pondok Pesantren Ash-Shiddiq', 21);
$body .= ringkasanCenter(($p['nomor_induk'] ? 'Nomor Induk: ' . $p['nomor_induk'] : 'Nomor Pendaftaran: ' . $p['nomor_daftar']), 24, true);
$body .= ringkasanCenter('Tanggal daftar: ' . date('d/m/Y H:i', strtotime($p['created_at'])) . '  ·  Status: ' . $p['status'], 19);

$body .= ringkasanHeading('A. Data Calon Santri');
$body .= ringkasanRow('Nama Lengkap', $p['nama_lengkap']);
$body .= ringkasanRow('Tempat, Tanggal Lahir', $p['tempat_lahir'] . ', ' . date('d/m/Y', strtotime($p['tanggal_lahir'])));
$body .= ringkasanRow('Jenis Kelamin', $jkLabel);
$body .= ringkasanRow('Jenjang', $labelJenjang[$p['jenjang']] ?? $p['jenjang']);
$body .= ringkasanRow('Tinggi / Berat Badan', (!empty($p['tinggi_badan']) ? (float) $p['tinggi_badan'] . ' cm' : '-') . ' / ' . (!empty($p['berat_badan']) ? (float) $p['berat_badan'] . ' kg' : '-'));
$body .= ringkasanRow('No. WhatsApp', $p['whatsapp']);

$body .= ringkasanHeading('B. Data Orang Tua / Wali');
$body .= ringkasanRow('Nama Ayah', $p['nama_ayah']);
$body .= ringkasanRow('Nama Ibu', $p['nama_ibu']);
$body .= ringkasanRow('No. HP Orang Tua', $p['hp_ortu']);
$body .= ringkasanRow('Pekerjaan Orang Tua', $p['pekerjaan_ortu'] ?: '-');
$body .= ringkasanRow('Alamat', $p['alamat']);

$body .= ringkasanHeading('C. Data Akademik');
$body .= ringkasanRow('Asal Sekolah', $p['asal_sekolah']);
$body .= ringkasanRow('Tahun Lulus', $p['tahun_lulus']);
$body .= ringkasanRow('Kemampuan Membaca Al-Qur\'an', $labelQuran[$p['kemampuan_quran']] ?? $p['kemampuan_quran']);
$body .= ringkasanRow('Jumlah Hafalan', $p['jumlah_hafalan'] ?: '-');
$body .= ringkasanRow('Motivasi', $p['motivasi'] ?: '-');

$body .= ringkasanHeading('D. Rincian Pembiayaan & Kesanggupan');
foreach ($itemsShown as $it) {
    $ket = $it['nama'] ? $it['nama'] . ' — ' : '';
    $nom = $it['gratis'] ? 'GRATIS' : 'Rp ' . number_format((float) $it['nominal'], 0, ',', '.');
    $st  = pembiayaanStatusLabel($it['status']) . ($it['kesanggupan'] ? ' (disanggupi)' : '');
    $body .= ringkasanRow(pembiayaanLabel($it['jenis']), $ket . $nom . ' — ' . $st);
}

if (!empty($p['kesanggupan_at'])) {
    $body .= ringkasanHeading('E. Pernyataan Kesanggupan');
    $body .= ringkasanText('Saya yang bertanda tangan di bawah ini selaku orang tua/wali menyatakan kesanggupan membayar seluruh biaya administrasi awal sesuai rincian di atas.', 21);
    $body .= ringkasanText('Ditandatangani pada: ' . date('d/m/Y H:i', strtotime($p['kesanggupan_at'])), 22, true);
    if ($signBytes !== '') {
        $ttdWEmu = 170 * 12700;
        $ttdHEmu = $ttdH * 12700;
        $drawing = '<w:p><w:pPr><w:jc w:val="center"/><w:spacing w:before="240" w:after="60"/></w:pPr>'
            . '<w:r><w:drawing><wp:inline distT="0" distB="0" distL="0" distR="0">'
            . '<wp:extent cx="' . $ttdWEmu . '" cy="' . $ttdHEmu . '"/>'
            . '<wp:docPr id="1" name="ttd"/>'
            . '<a:graphic><a:graphicData uri="http://schemas.openxmlformats.org/drawingml/2006/picture">'
            . '<pic:pic><pic:nvPicPr><pic:cNvPr id="0" name="ttd"/><pic:cNvPicPr/></pic:nvPicPr>'
            . '<pic:blipFill><a:blip r:embed="rIdImg1"/><a:stretch><a:fillRect/></a:stretch></pic:blipFill>'
            . '<pic:spPr><a:xfrm><a:off x="0" y="0"/><a:ext cx="' . $ttdWEmu . '" cy="' . $ttdHEmu . '"/></a:xfrm>'
            . '<a:prstGeom prst="rect"><a:avLst/></a:prstGeom></pic:spPr></pic:pic>'
            . '</a:graphicData></a:graphic></wp:inline></w:drawing></w:r></w:p>';
        $body .= $drawing;
        $body .= ringkasanCenter('Tanda tangan orang tua / wali', 20);
    } else {
        $body .= ringkasanText('Tanda tangan belum tersedia.', 21);
    }
}

$body .= ringkasanText('Dokumen ini dibuat otomatis oleh sistem. Untuk konfirmasi, hubungi panitia melalui WhatsApp.', 18);

$documentXml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
    . '<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main"'
    . ' xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"'
    . ' xmlns:wp="http://schemas.openxmlformats.org/drawingml/2006/wordprocessingDrawing"'
    . ' xmlns:a="http://schemas.openxmlformats.org/drawingml/2006/main"'
    . ' xmlns:pic="http://schemas.openxmlformats.org/drawingml/2006/picture">'
    . '<w:body>' . $body
    . '<w:sectPr><w:pgSz w:w="11906" w:h="16838"/><w:pgMar w:top="1134" w:right="1134" w:bottom="1134" w:left="1134" w:header="720" w:footer="720" w:gutter="0"/></w:sectPr>'
    . '</w:body></w:document>';

$contentTypes = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
    . '<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
    . '<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
    . '<Default Extension="xml" ContentType="application/xml"/>'
    . ($signBytes !== '' ? '<Default Extension="png" ContentType="image/png"/>' : '')
    . '<Override PartName="/word/document.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml"/>'
    . '</Types>';

$rels = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
    . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
    . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="word/document.xml"/>'
    . '</Relationships>';

$docRels = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
    . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
    . ($signBytes !== '' ? '<Relationship Id="rIdImg1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/image" Target="media/image1.png"/>' : '')
    . '</Relationships>';

// ── Output ───────────────────────────────────────────────────
while (ob_get_level()) ob_end_clean();

if (class_exists('ZipArchive')) {
    $tmp = tempnam(sys_get_temp_dir(), 'rkp');
    $zip = new ZipArchive();
    if ($zip->open($tmp, ZipArchive::OVERWRITE) === true) {
        $zip->addFromString('[Content_Types].xml', $contentTypes);
        $zip->addFromString('_rels/.rels', $rels);
        $zip->addFromString('word/document.xml', $documentXml);
        $zip->addFromString('word/_rels/document.xml.rels', $docRels);
        if ($signBytes !== '') {
            $zip->addFromString('word/media/image1.png', $signBytes);
        }
        $zip->close();
        $out = (string) file_get_contents($tmp);
        @unlink($tmp);
        header('Content-Type: application/vnd.openxmlformats-officedocument.wordprocessingml.document');
        header('Content-Disposition: attachment; filename="ringkasan-pendaftaran-' . $nomorFile . '.docx"');
        header('Content-Length: ' . strlen($out));
        header('Cache-Control: no-store');
        echo $out;
        exit;
    }
    @unlink($tmp);
}

// Fallback: PDF jika ZipArchive tidak tersedia
include __DIR__ . '/ringkasan-pdf.php';
