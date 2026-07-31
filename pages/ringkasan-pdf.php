<?php
// Ringkasan pendaftaran format PDF (tabel) — di-include oleh surat-kesanggupan.php
// Butuh variabel: $p, $itemsShown, $signBytes, $ttdW, $ttdH,
// $labelJenjang, $labelQuran, $jkLabel, $nomorFile
if (!isset($p)) { http_response_code(403); exit; }

$jpegData = null; $imgW = 0; $imgH = 0;
if ($signBytes !== '') {
    $im = @imagecreatefromstring($signBytes);
    if ($im) {
        $imgW = imagesx($im);
        $imgH = imagesy($im);
        ob_start();
        imagejpeg($im, null, 88);
        $jpegData = ob_get_clean();
        imagedestroy($im);
    }
}

final class RingkasanPdf
{
    private string $cur = '';
    private array $pages = [];
    private float $y;
    private const W = 595, H = 842, MR = 50, MT = 60, MB = 50;

    public function __construct() { $this->newPage(); }

    public function newPage(): void
    {
        if ($this->cur !== '') $this->pages[] = $this->cur;
        $this->cur = '';
        $this->y = self::H - self::MT;
    }

    private function ensure(float $h): void { if ($this->y - $h < self::MB) $this->newPage(); }

    public function gap(float $h = 6): void { $this->ensure($h); $this->y -= $h; }

    public function title(string $t): void { $this->put($t, 16, true, 'center', 50, 495); $this->gap(4); }

    public function center(string $t, float $size = 11, bool $bold = false): void { $this->put($t, $size, $bold, 'center', 50, 495); }

    public function heading(string $t): void { $this->gap(10); $this->put($t, 13, true, 'left', 50, 495); $this->gap(4); }

    public function text(string $t, float $size = 11, bool $bold = false): void { $this->put($t, $size, $bold, 'left', 50, 495); }

    /**
     * Baris tabel dengan border. $cells = nilai tiap kolom,
     * $widths = lebar kolom (pt), $styles = 'label'|'' per kolom.
     */
    public function tableRow(array $cells, array $widths, array $styles = [], float $size = 11): void
    {
        $lineH = $size * 1.2;
        $pad   = 5;
        $wrapped = [];
        $maxLines = 1;
        foreach ($cells as $i => $text) {
            $lines = $this->wrap($text, $size, $widths[$i] - 2 * $pad);
            $wrapped[$i] = $lines;
            $maxLines = max($maxLines, count($lines));
        }
        $rowH = $maxLines * $lineH + 2 * $pad;
        $this->ensure($rowH + 4);

        $x = 50;
        for ($i = 0; $i < count($cells); $i++) {
            $cw   = $widths[$i];
            $topY = $this->y;
            // isi label dengan abu-abu terang, kolom lain putih
            $this->cur .= sprintf("%.1f %.1f %.1f %.1f re %s\n", $x, $topY - $rowH, $cw, $rowH, (($styles[$i] ?? '') === 'label') ? '0.93 g f' : '1 g f');
            $this->cur .= sprintf("%.1f %.1f %.1f %.1f re S\n", $x, $topY - $rowH, $cw, $rowH);
            $font = (($styles[$i] ?? '') === 'label') ? 'F2' : 'F1';
            $ty   = $topY - $pad - $lineH;
            foreach ($wrapped[$i] as $ln) {
                $this->cur .= sprintf("BT /%s %.1f Tf 1 0 0 1 %.1f %.1f Tm (%s) Tj ET\n", $font, $size, $x + $pad, $ty, self::esc(self::win($ln)));
                $ty -= $lineH;
            }
            $x += $cw;
        }
        $this->y -= $rowH;
    }

    public function image(float $w, float $h): void
    {
        $this->ensure($h + 24);
        $x = (self::W - $w) / 2;
        $this->cur .= sprintf("q %.1f 0 0 %.1f %.1f %.1f cm /Im1 Do Q\n", $w, $h, $x, $this->y - $h);
        $this->y -= ($h + 16);
    }

    private function put(string $t, float $size, bool $bold, string $align, float $x, float $maxW): void
    {
        $lines = $this->wrap($t, $size, $maxW);
        foreach ($lines as $ln) {
            $this->ensure($size * 1.5);
            $tw = $this->width($ln, $size);
            $xx = $align === 'center' ? (self::W - $tw) / 2 : ($align === 'right' ? self::W - self::MR - $tw : $x);
            $this->line($ln, $size, $bold, $xx);
        }
    }

    private function line(string $t, float $size, bool $bold, float $x): void
    {
        $font = $bold ? 'F2' : 'F1';
        $this->cur .= sprintf("BT /%s %.1f Tf 1 0 0 1 %.1f %.1f Tm (%s) Tj ET\n", $font, $size, $x, $this->y, self::esc(self::win($t)));
        $this->y -= $size * 1.5;
    }

    private function wrap(string $t, float $size, float $maxW): array
    {
        $t     = self::win($t);
        $words = explode(' ', $t);
        $lines = [];
        $cur   = '';
        foreach ($words as $wd) {
            $cand = $cur === '' ? $wd : $cur . ' ' . $wd;
            if ($cur !== '' && $this->width($cand, $size) > $maxW) {
                $lines[] = $cur;
                $cur     = $wd;
            } else {
                $cur = $cand;
            }
        }
        if ($cur !== '') $lines[] = $cur;
        return $lines === [] ? [''] : $lines;
    }

    private function width(string $t, float $size): float
    {
        $w  = 0;
        $ln = strlen($t);
        for ($i = 0; $i < $ln; $i++) {
            $c = ord($t[$i]);
            $w += ($c >= 32 && $c <= 126) ? self::$WIDTHS[$c - 32] : 500;
        }
        return $w / 1000 * $size;
    }

    private static function win(string $t): string
    {
        $t = str_replace(["\r", "\n"], ' ', $t);
        return function_exists('mb_convert_encoding')
            ? mb_convert_encoding($t, 'Windows-1252', 'UTF-8')
            : $t;
    }

    private static function esc(string $s): string
    {
        return strtr($s, ['\\' => '\\\\', '(' => '\(', ')' => '\)']);
    }

    private static array $WIDTHS = [
        278,278,355,556,556,889,667,191,333,333,389,584,278,333,278,278,
        556,556,556,556,556,556,556,556,556,556,278,278,584,584,584,556,
        1015,667,667,722,722,667,611,778,722,278,500,667,556,833,722,778,
        667,778,722,667,611,722,667,944,667,667,611,278,278,278,469,556,
        333,556,556,500,556,556,278,556,556,222,222,500,222,833,556,556,
        556,556,333,500,278,556,500,722,500,500,500,334,260,334,584,
    ];

    public function output(?string $jpeg, int $imgW, int $imgH): string
    {
        if ($this->cur !== '') $this->pages[] = $this->cur;
        $np      = count($this->pages);
        $firstPg = 5;
        $firstSt = $firstPg + $np;
        $imgObj  = $jpeg !== null ? $firstSt + $np : 0;

        $objs = [];
        $objs[1] = '<< /Type /Catalog /Pages 2 0 R >>';
        $kids = [];
        for ($i = 0; $i < $np; $i++) $kids[] = ($firstPg + $i) . ' 0 R';
        $objs[2] = '<< /Type /Pages /Kids [' . implode(' ', $kids) . '] /Count ' . $np . ' >>';
        $objs[3] = '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>';
        $objs[4] = '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica-Bold /Encoding /WinAnsiEncoding >>';

        $imgRef = $imgObj ? ' /XObject << /Im1 ' . $imgObj . ' 0 R >>' : '';
        for ($i = 0; $i < $np; $i++) {
            $res = '<< /Font << /F1 3 0 R /F2 4 0 R >>' . $imgRef . ' >>';
            $objs[$firstPg + $i] = '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 ' . self::W . ' ' . self::H . '] /Resources ' . $res . ' /Contents ' . ($firstSt + $i) . ' 0 R >>';
        }
        for ($i = 0; $i < $np; $i++) {
            $stream = $this->pages[$i];
            $objs[$firstSt + $i] = '<< /Length ' . strlen($stream) . ' >>' . "\nstream\n" . $stream . 'endstream';
        }
        if ($imgObj) {
            $objs[$imgObj] = '<< /Type /XObject /Subtype /Image /Width ' . $imgW . ' /Height ' . $imgH . ' /ColorSpace /DeviceRGB /BitsPerComponent 8 /Filter /DCTDecode /Length ' . strlen((string) $jpeg) . ' >>' . "\nstream\n" . $jpeg . "\nendstream";
        }

        $pdf  = "%PDF-1.4\n";
        $offs = [];
        ksort($objs);
        foreach ($objs as $num => $body) {
            $offs[$num] = strlen($pdf);
            $pdf .= $num . " 0 obj\n" . $body . "\nendobj\n";
        }
        $xref = strlen($pdf);
        $pdf .= "xref\n0 " . (count($objs) + 1) . "\n";
        $pdf .= "0000000000 65535 f \n";
        for ($i = 1; $i <= count($objs); $i++) {
            $pdf .= sprintf("%010d 00000 n \n", $offs[$i]);
        }
        $pdf .= "trailer\n<< /Size " . (count($objs) + 1) . " /Root 1 0 R >>\nstartxref\n" . $xref . "\n%%EOF";
        return $pdf;
    }
}

// ── Susun dokumen (format tabel) ─────────────────────────────
$pdf = new RingkasanPdf();
$pdf->title('RINGKASAN PENDAFTARAN SANTRI BARU');
$pdf->center('Pondok Pesantren Ash-Shiddiq', 10.5);
$pdf->gap(2);
$pdf->center(($p['nomor_induk'] ? 'Nomor Induk: ' . $p['nomor_induk'] : 'Nomor Pendaftaran: ' . $p['nomor_daftar']), 12, true);
$pdf->center('Tanggal daftar: ' . date('d/m/Y H:i', strtotime($p['created_at'])) . '  ·  Status: ' . $p['status'], 9.5);
$pdf->gap(8);

$w2 = [188, 307]; // 2 kolom: label 38%, nilai 62%

$pdf->heading('A. Data Calon Santri');
$pdf->tableRow(['Nama Lengkap', $p['nama_lengkap']], $w2, ['label', '']);
$pdf->tableRow(['Tempat, Tanggal Lahir', $p['tempat_lahir'] . ', ' . date('d/m/Y', strtotime($p['tanggal_lahir']))], $w2, ['label', '']);
$pdf->tableRow(['Jenis Kelamin', $jkLabel], $w2, ['label', '']);
$pdf->tableRow(['Jenjang', $labelJenjang[$p['jenjang']] ?? $p['jenjang']], $w2, ['label', '']);
$pdf->tableRow(['Tinggi / Berat Badan', (!empty($p['tinggi_badan']) ? (float) $p['tinggi_badan'] . ' cm' : '-') . ' / ' . (!empty($p['berat_badan']) ? (float) $p['berat_badan'] . ' kg' : '-')], $w2, ['label', '']);
$pdf->tableRow(['No. WhatsApp', $p['whatsapp']], $w2, ['label', '']);

$pdf->heading('B. Data Orang Tua / Wali');
$pdf->tableRow(['Nama Ayah', $p['nama_ayah']], $w2, ['label', '']);
$pdf->tableRow(['Nama Ibu', $p['nama_ibu']], $w2, ['label', '']);
$pdf->tableRow(['No. HP Orang Tua', $p['hp_ortu']], $w2, ['label', '']);
$pdf->tableRow(['Pekerjaan Orang Tua', $p['pekerjaan_ortu'] ?: '-'], $w2, ['label', '']);
$pdf->tableRow(['Alamat', $p['alamat']], $w2, ['label', '']);

$pdf->heading('C. Data Akademik');
$pdf->tableRow(['Asal Sekolah', $p['asal_sekolah']], $w2, ['label', '']);
$pdf->tableRow(['Tahun Lulus', $p['tahun_lulus']], $w2, ['label', '']);
$pdf->tableRow(['Kemampuan Membaca Al-Qur\'an', $labelQuran[$p['kemampuan_quran']] ?? $p['kemampuan_quran']], $w2, ['label', '']);
$pdf->tableRow(['Jumlah Hafalan', $p['jumlah_hafalan'] ?: '-'], $w2, ['label', '']);
$pdf->tableRow(['Motivasi', $p['motivasi'] ?: '-'], $w2, ['label', '']);

$pdf->heading('D. Rincian Pembiayaan & Kesanggupan');
$w4 = [135, 150, 100, 110];
$pdf->tableRow(['Item', 'Keterangan', 'Nominal', 'Status'], $w4, ['label', 'label', 'label', 'label']);
foreach ($itemsShown as $it) {
    $ket = $it['nama'] ?: '-';
    $nom = $it['gratis'] ? 'GRATIS' : 'Rp ' . number_format((float) $it['nominal'], 0, ',', '.');
    $st  = pembiayaanStatusLabel($it['status']) . ($it['kesanggupan'] ? ' (disanggupi)' : '');
    $pdf->tableRow([pembiayaanLabel($it['jenis']), $ket, $nom, $st], $w4);
}

if (!empty($p['kesanggupan_at'])) {
    $pdf->heading('E. Pernyataan Kesanggupan');
    $pdf->text('Saya yang bertanda tangan di bawah ini selaku orang tua/wali menyatakan kesanggupan membayar seluruh biaya administrasi awal sesuai rincian di atas.', 10.5);
    $pdf->gap(2);
    $pdf->text('Ditandatangani pada: ' . date('d/m/Y H:i', strtotime($p['kesanggupan_at'])), 11, true);
    $pdf->gap(10);
    if ($jpegData !== null) {
        $pdf->image($ttdW, $ttdH);
        $pdf->center('Tanda tangan orang tua / wali', 10);
    } else {
        $pdf->text('Tanda tangan belum tersedia.', 10.5);
    }
}

$pdf->gap(10);
$pdf->text('Dokumen ini dibuat otomatis oleh sistem. Untuk konfirmasi, hubungi panitia melalui WhatsApp.', 9);

$out = $pdf->output($jpegData, $imgW, $imgH);

while (ob_get_level()) ob_end_clean();
header('Content-Type: application/pdf');
header('Content-Disposition: attachment; filename="ringkasan-pendaftaran-' . $nomorFile . '.pdf"');
header('Content-Length: ' . strlen($out));
header('Cache-Control: no-store');
echo $out;
exit;
