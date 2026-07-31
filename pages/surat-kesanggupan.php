<?php
// Akses: santri (session) atau admin (dengan ?id=)
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

// Tanda tangan kesanggupan (ukuran konsisten sesuai rasio asli)
$signB64 = ''; $ttdW = 170; $ttdH = 65;
if (!empty($p['kesanggupan_sign'])) {
    $signPath = UPLOADS_PATH . '/santri/' . basename($p['kesanggupan_sign']);
    if (is_file($signPath)) {
        $signB64 = base64_encode((string) file_get_contents($signPath));
        $sz = @getimagesize($signPath);
        if ($sz && $sz[0] > 0) {
            $ttdW = 170;
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

ob_start();
?>
<html xmlns:o="urn:schemas-microsoft-com:office:office" xmlns:w="urn:schemas-microsoft-com:office:word">
<head><meta charset="UTF-8"><title>Ringkasan Pendaftaran</title>
<style>
body { font-family: Arial, sans-serif; font-size: 12px; line-height: 1.5; margin: 40px; color: #222; }
h1 { text-align: center; font-size: 18px; margin: 0 0 4px; }
.subtitle { text-align: center; font-size: 12px; color: #555; margin-bottom: 18px; }
h2 { font-size: 13px; border-bottom: 1px solid #999; padding-bottom: 4px; margin: 20px 0 10px; }
table { width: 100%; border-collapse: collapse; }
td { padding: 5px 8px; border: 1px solid #bbb; vertical-align: top; }
td.k { width: 38%; background: #f5f5f5; font-weight: bold; }
.nomor { text-align: center; font-size: 14px; font-weight: bold; letter-spacing: 1px; margin: 8px 0 0; }
.ket { font-size: 11px; color: #777; margin-top: 10px; }
.sig-wrap { text-align: center; margin: 16px 0 4px; }
.sig-wrap img { width: <?= $ttdW ?>px; height: <?= $ttdH ?>px; }
.sig-name { text-align: center; margin-top: 6px; }
</style>
</head>
<body>

<h1>RINGKASAN PENDAFTARAN SANTRI BARU</h1>
<p class="subtitle">Pondok Pesantren Ash-Shiddiq</p>
<p class="nomor"><?= e($p['nomor_induk'] ? 'Nomor Induk: ' . $p['nomor_induk'] : 'Nomor Pendaftaran: ' . $p['nomor_daftar']) ?></p>
<p class="subtitle">Tanggal daftar: <?= e(date('d/m/Y H:i', strtotime($p['created_at']))) ?> · Status: <?= e($p['status']) ?></p>

<h2>A. Data Calon Santri</h2>
<table>
<tr><td class="k">Nama Lengkap</td><td><?= e($p['nama_lengkap']) ?></td></tr>
<tr><td class="k">Tempat, Tanggal Lahir</td><td><?= e($p['tempat_lahir']) ?>, <?= e(date('d/m/Y', strtotime($p['tanggal_lahir']))) ?></td></tr>
<tr><td class="k">Jenis Kelamin</td><td><?= e($jkLabel) ?></td></tr>
<tr><td class="k">Jenjang</td><td><?= e($labelJenjang[$p['jenjang']] ?? $p['jenjang']) ?></td></tr>
<tr><td class="k">Tinggi / Berat Badan</td><td><?= !empty($p['tinggi_badan']) ? e((float) $p['tinggi_badan']) . ' cm' : '-' ?> / <?= !empty($p['berat_badan']) ? e((float) $p['berat_badan']) . ' kg' : '-' ?></td></tr>
<tr><td class="k">No. WhatsApp</td><td><?= e($p['whatsapp']) ?></td></tr>
</table>

<h2>B. Data Orang Tua / Wali</h2>
<table>
<tr><td class="k">Nama Ayah</td><td><?= e($p['nama_ayah']) ?></td></tr>
<tr><td class="k">Nama Ibu</td><td><?= e($p['nama_ibu']) ?></td></tr>
<tr><td class="k">No. HP Orang Tua</td><td><?= e($p['hp_ortu']) ?></td></tr>
<tr><td class="k">Pekerjaan Orang Tua</td><td><?= e($p['pekerjaan_ortu'] ?: '-') ?></td></tr>
<tr><td class="k">Alamat</td><td><?= e($p['alamat']) ?></td></tr>
</table>

<h2>C. Data Akademik</h2>
<table>
<tr><td class="k">Asal Sekolah</td><td><?= e($p['asal_sekolah']) ?></td></tr>
<tr><td class="k">Tahun Lulus</td><td><?= e($p['tahun_lulus']) ?></td></tr>
<tr><td class="k">Kemampuan Membaca Al-Qur'an</td><td><?= e($labelQuran[$p['kemampuan_quran']] ?? $p['kemampuan_quran']) ?></td></tr>
<tr><td class="k">Jumlah Hafalan</td><td><?= e($p['jumlah_hafalan'] ?: '-') ?></td></tr>
<tr><td class="k">Motivasi</td><td><?= e($p['motivasi'] ?: '-') ?></td></tr>
</table>

<h2>D. Rincian Pembiayaan &amp; Kesanggupan</h2>
<table>
<tr><td class="k">Item</td><td class="k">Keterangan</td><td class="k">Nominal</td><td class="k">Status</td></tr>
<?php foreach ($itemsShown as $it): ?>
<tr>
    <td><?= e(pembiayaanLabel($it['jenis'])) ?></td>
    <td><?= e($it['nama'] ?: '-') ?></td>
    <td><?= $it['gratis'] ? 'GRATIS' : 'Rp ' . number_format((float) $it['nominal'], 0, ',', '.') ?></td>
    <td><?= e(pembiayaanStatusLabel($it['status'])) ?><?= $it['kesanggupan'] ? ' (disanggupi)' : '' ?></td>
</tr>
<?php endforeach; ?>
</table>

<?php if (!empty($p['kesanggupan_at'])): ?>
<h2>E. Pernyataan Kesanggupan</h2>
<p>Saya yang bertanda tangan di bawah ini selaku orang tua/wali menyatakan kesanggupan membayar seluruh biaya administrasi awal sesuai rincian di atas.</p>
<p>Ditandatangani pada: <strong><?= e(date('d/m/Y H:i', strtotime($p['kesanggupan_at']))) ?></strong></p>
<?php if ($signB64 !== ''): ?>
<div class="sig-wrap"><img src="signature.png"></div>
<div class="sig-name"><em>Tanda tangan orang tua / wali</em></div>
<?php else: ?>
<p class="ket">Tanda tangan belum tersedia.</p>
<?php endif; ?>
<?php endif; ?>

<p class="ket">Dokumen ini dibuat otomatis oleh sistem. Untuk konfirmasi, hubungi panitia melalui WhatsApp.</p>

</body>
</html>
<?php
$htmlDoc = ob_get_clean();

while (ob_get_level()) ob_end_clean();

if ($signB64 !== '') {
    // MHT (Word terbuka dengan gambar) — andal untuk tanda tangan
    $boundary = '----=_NextPart_' . md5(uniqid('', true));
    header('Content-Type: multipart/related; boundary="' . $boundary . '"; type="text/html"');
    header('Content-Disposition: attachment; filename="ringkasan-pendaftaran-' . $nomorFile . '.mht"');
    echo "MIME-Version: 1.0\r\n";
    echo "Content-Type: multipart/related; boundary=\"$boundary\"\r\n\r\n";
    echo "--$boundary\r\n";
    echo "Content-Type: text/html; charset=\"utf-8\"\r\n";
    echo "Content-Transfer-Encoding: base64\r\n";
    echo "Content-Location: ringkasan.html\r\n\r\n";
    echo chunk_split(base64_encode($htmlDoc), 76, "\r\n") . "\r\n";
    echo "--$boundary\r\n";
    echo "Content-Type: image/png\r\n";
    echo "Content-Transfer-Encoding: base64\r\n";
    echo "Content-Location: signature.png\r\n";
    echo "Content-ID: <signature.png>\r\n\r\n";
    echo chunk_split($signB64, 76, "\r\n") . "\r\n";
    echo "--$boundary--\r\n";
} else {
    header('Content-Type: text/html; charset=UTF-8');
    header('Content-Disposition: attachment; filename="ringkasan-pendaftaran-' . $nomorFile . '.html"');
    echo $htmlDoc;
}
exit;
