<?php
/**
 * Helper functions global — tersedia di seluruh aplikasi
 */

// ── Output Escaping ──────────────────────────────────────────

/**
 * Escape string untuk output HTML (cegah XSS)
 */
function e(string $str): string {
    return htmlspecialchars($str, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/**
 * Escape untuk output dalam atribut URL
 */
function eUrl(string $url): string {
    return htmlspecialchars(filter_var($url, FILTER_SANITIZE_URL), ENT_QUOTES, 'UTF-8');
}

// ── Input Sanitasi ───────────────────────────────────────────

function sanitizeString(string $input): string {
    return trim(strip_tags($input));
}

function sanitizeEmail(string $email): string {
    return strtolower(trim(filter_var($email, FILTER_SANITIZE_EMAIL)));
}

function validateEmail(string $email): bool {
    return (bool) filter_var($email, FILTER_VALIDATE_EMAIL);
}

function sanitizeInt(mixed $value): int {
    return (int) filter_var($value, FILTER_SANITIZE_NUMBER_INT);
}

function sanitizeFloat(mixed $value): float {
    return (float) preg_replace('/[^0-9.]/', '', (string) $value);
}

// ── Static Image Helper ──────────────────────────────────────

/**
 * Render <img> jika file gambar ada di /assets/img/{relativePath},
 * fallback ke $placeholderHtml (biasanya gradient div) jika belum ada.
 *
 * Pakai untuk semua foto statis (mudir, galeri, pengajar, fasilitas, dll).
 * Dengan cara ini user tinggal meletakkan file dengan nama yang sudah
 * ditetapkan — tidak perlu mengedit kode lagi.
 *
 * @param string $relativePath Path relatif terhadap /assets/img/ (contoh: "galeri/halaqah.jpg")
 * @param string $alt          Teks alt untuk SEO & accessibility
 * @param string $placeholderHtml HTML fallback saat file belum ada
 * @param string $imgClass     Class CSS opsional untuk tag <img>
 * @param bool   $lazy         Aktifkan loading="lazy"
 */
function imgOrPlaceholder(string $relativePath, string $alt, string $placeholderHtml, string $imgClass = '', bool $lazy = true): string {
    $rel      = ltrim($relativePath, '/');
    $fullPath = ROOT_PATH . '/assets/img/' . $rel;

    if (is_file($fullPath)) {
        $lazyAttr = $lazy ? ' loading="lazy"' : '';
        $cls      = $imgClass !== '' ? ' class="' . e($imgClass) . '"' : '';
        return '<img src="' . e(imgUrl($rel)) . '"'
             . ' alt="' . e($alt) . '"' . $cls . $lazyAttr . '>';
    }
    return $placeholderHtml;
}

/**
 * URL gambar di /assets/img/ dengan versi cache-busting (?v=waktu modifikasi).
 * Saat admin mengunggah ulang (menimpa file), browser langsung memuat versi baru
 * karena URL-nya berubah — tidak lagi menampilkan cache gambar lama.
 */
function imgUrl(string $relativePath): string {
    $rel      = ltrim($relativePath, '/');
    $fullPath = ROOT_PATH . '/assets/img/' . $rel;
    $ver      = is_file($fullPath) ? (int) @filemtime($fullPath) : 0;
    return BASE_URL . '/assets/img/' . $rel . ($ver ? '?v=' . $ver : '');
}

/**
 * Cek keberadaan file gambar di /assets/img/.
 * Berguna untuk menambahkan class CSS kondisional (mis. background-image).
 */
function imgExists(string $relativePath): bool {
    return is_file(ROOT_PATH . '/assets/img/' . ltrim($relativePath, '/'));
}

/**
 * Nama file logo website yang aktif (logo.svg / logo.png) atau '' jika belum ada.
 */
function getLogoFile(): string {
    foreach (['logo.svg', 'logo.png'] as $f) {
        if (is_file(ROOT_PATH . '/assets/img/' . $f)) return $f;
    }
    return '';
}

// ── File Upload ──────────────────────────────────────────────

/** Deteksi MIME dari isi file, termasuk pada hosting tanpa ekstensi fileinfo. */
function detectMimeType(string $path): string {
    if (class_exists('finfo')) {
        $mime = (new finfo(FILEINFO_MIME_TYPE))->file($path);
        if (is_string($mime) && $mime !== '') return $mime;
    }
    if (function_exists('mime_content_type')) {
        $mime = mime_content_type($path);
        if (is_string($mime) && $mime !== '') return $mime;
    }
    $handle = @fopen($path, 'rb');
    $header = $handle ? (string) fread($handle, 32) : '';
    if ($handle) fclose($handle);
    if (str_starts_with($header, '%PDF-')) return 'application/pdf';
    if (str_starts_with($header, "\xFF\xD8\xFF")) return 'image/jpeg';
    if (str_starts_with($header, "\x89PNG\x0D\x0A\x1A\x0A")) return 'image/png';
    if (substr($header, 0, 4) === 'RIFF' && substr($header, 8, 4) === 'WEBP') return 'image/webp';
    if (str_starts_with($header, "\xD0\xCF\x11\xE0\xA1\xB1\x1A\xE1")) return 'application/msword';
    if (str_starts_with($header, "PK\x03\x04")) return 'application/vnd.openxmlformats-officedocument.wordprocessingml.document';
    if (substr($header, 4, 4) === 'ftyp') return 'video/mp4';
    if (str_starts_with($header, "\x1A\x45\xDF\xA3")) return 'video/webm';
    return 'application/octet-stream';
}

/**
 * Validasi file upload dan kembalikan array error (kosong = valid)
 * @param array<string, mixed> $file       — elemen dari $_FILES
 * @param string[]             $allowedExt — ekstensi yang diizinkan (whitelist)
 * @param string[]             $allowedMime
 * @param int                  $maxBytes
 * @return string[]
 */
function validateUpload(array $file, array $allowedExt, array $allowedMime, int $maxBytes = 2097152): array {
    $errors = [];

    if (!isset($file['error']) || $file['error'] !== UPLOAD_ERR_OK) {
        $errors[] = 'Gagal mengunggah file. Kode error: ' . ($file['error'] ?? 'unknown');
        return $errors;
    }

    // Cek ekstensi (whitelist)
    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if (!in_array($ext, $allowedExt, true)) {
        $errors[] = 'Tipe file tidak diizinkan. Ekstensi yang diperbolehkan: ' . implode(', ', $allowedExt);
    }

    // Cek MIME type dari isi file (bukan dari header kiriman)
    $mime = detectMimeType($file['tmp_name']);
    if (!in_array($mime, $allowedMime, true)) {
        $errors[] = 'Tipe MIME file tidak valid (' . e($mime) . ').';
    }

    // Cek ukuran
    if ($file['size'] > $maxBytes) {
        $errors[] = 'Ukuran file terlalu besar. Maksimal ' . formatBytes($maxBytes) . '.';
    }

    return $errors;
}

/**
 * Simpan file upload yang sudah divalidasi
 * Kembalikan nama file baru (random) atau false jika gagal
 */
function saveUpload(array $file, string $destDir): string|false {
    $ext         = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    $newFilename = bin2hex(random_bytes(16)) . '.' . $ext;
    $destPath    = rtrim($destDir, '/\\') . DIRECTORY_SEPARATOR . $newFilename;

    if (!is_dir($destDir)) {
        mkdir($destDir, 0755, true);
    }

    if (!move_uploaded_file($file['tmp_name'], $destPath)) {
        return false;
    }

    return $newFilename;
}

/**
 * Resize gambar sebelum disimpan (optimasi performa shared hosting).
 * Jika GD tidak punya handler untuk format tsb (mis. server tanpa JPEG),
 * fallback menyalin file asli agar upload tetap berhasil.
 */
function resizeImage(string $source, string $dest, int $maxWidth = 1200, int $quality = 85): bool {
    $info = @getimagesize($source);
    if ($info === false) return false;
    [$width, $height, $type] = $info;

    if ($width <= $maxWidth) {
        return $source === $dest ? true : copy($source, $dest);
    }

    $loader = match ($type) {
        IMAGETYPE_JPEG => 'imagecreatefromjpeg',
        IMAGETYPE_PNG  => 'imagecreatefrompng',
        IMAGETYPE_WEBP => 'imagecreatefromwebp',
        default        => null,
    };
    $saver = match ($type) {
        IMAGETYPE_JPEG => 'imagejpeg',
        IMAGETYPE_PNG  => 'imagepng',
        IMAGETYPE_WEBP => 'imagewebp',
        default        => null,
    };

    // GD tidak punya fungsi untuk format ini → simpan asli tanpa resize
    if ($loader === null || $saver === null || !function_exists($loader) || !function_exists($saver)) {
        return copy($source, $dest);
    }

    $ratio  = $maxWidth / $width;
    $newH   = (int) ($height * $ratio);
    $canvas = imagecreatetruecolor($maxWidth, $newH);

    $src = $loader($source);
    if (!$src) return false;

    // Pertahankan transparansi PNG
    if ($type === IMAGETYPE_PNG) {
        imagealphablending($canvas, false);
        imagesavealpha($canvas, true);
    }

    imagecopyresampled($canvas, $src, 0, 0, 0, 0, $maxWidth, $newH, $width, $height);

    $ok = $type === IMAGETYPE_PNG
        ? $saver($canvas, $dest, 6)
        : $saver($canvas, $dest, $quality);
    imagedestroy($canvas);
    imagedestroy($src);
    return $ok;
}

// ── Formatting ───────────────────────────────────────────────

function formatBytes(int $bytes, int $precision = 1): string {
    $units = ['B', 'KB', 'MB', 'GB'];
    $i     = 0;
    while ($bytes >= 1024 && $i < count($units) - 1) {
        $bytes /= 1024;
        $i++;
    }
    return round($bytes, $precision) . ' ' . $units[$i];
}

/**
 * Format tanggal ke format dd/mm/yyyy (contoh: 26/04/2026)
 */
function formatTanggal(string $dateStr, bool $withTime = false): string {
    $ts     = strtotime($dateStr);
    $result = date('d/m/Y', $ts);
    if ($withTime) {
        $result .= ' · ' . date('H:i', $ts);
    }
    return $result;
}

/**
 * Buat slug URL-friendly dari string
 */
function slugify(string $text): string {
    $text = mb_strtolower($text, 'UTF-8');
    // Transliterasi karakter umum Indonesia/Arab
    $replace = ['  ' => ' ', ' ' => '-', '--' => '-'];
    $text    = strtr($text, $replace);
    $text    = preg_replace('/[^a-z0-9\-]/', '', $text);
    $text    = preg_replace('/-{2,}/', '-', $text);
    return trim($text, '-');
}

/**
 * Potong teks ke panjang tertentu dengan ellipsis
 */
function truncate(string $text, int $maxLength = 160, string $ellipsis = '...'): string {
    $text = strip_tags($text);
    if (mb_strlen($text) <= $maxLength) {
        return $text;
    }
    return mb_substr($text, 0, $maxLength - mb_strlen($ellipsis)) . $ellipsis;
}

/**
 * Estimasi waktu baca artikel (menit)
 */
function readingTime(string $content): int {
    $wordCount = str_word_count(strip_tags($content));
    return max(1, (int) ceil($wordCount / 200)); // asumsi 200 kata/menit
}

// ── Simple Output Cache ──────────────────────────────────────

/**
 * Ambil cache HTML jika masih valid
 */
function cacheGet(string $key, int $ttlSeconds = 3600): string|false {
    if (!defined('CACHE_PATH')) return false;
    $file = CACHE_PATH . '/' . md5($key) . '.html';
    if (file_exists($file) && (time() - filemtime($file)) < $ttlSeconds) {
        return file_get_contents($file);
    }
    return false;
}

/**
 * Simpan output HTML ke cache
 */
function cacheSet(string $key, string $content): void {
    if (!defined('CACHE_PATH')) return;
    if (!is_dir(CACHE_PATH)) mkdir(CACHE_PATH, 0755, true);
    file_put_contents(CACHE_PATH . '/' . md5($key) . '.html', $content);
}

// ── Pagination ───────────────────────────────────────────────

/**
 * Hitung offset untuk query LIMIT/OFFSET
 */
function paginationOffset(int $page, int $perPage): int {
    return max(0, ($page - 1)) * $perPage;
}

/**
 * Render HTML pagination sederhana
 */
function renderPagination(int $currentPage, int $totalPages, string $baseUrl): string {
    if ($totalPages <= 1) return '';

    $html = '<nav class="pagination" aria-label="Navigasi halaman"><ul>';

    // Tombol sebelumnya
    if ($currentPage > 1) {
        $html .= '<li><a href="' . e($baseUrl) . '?halaman=' . ($currentPage - 1) . '" aria-label="Halaman sebelumnya">&laquo;</a></li>';
    }

    // Nomor halaman
    $range = 2;
    for ($i = max(1, $currentPage - $range); $i <= min($totalPages, $currentPage + $range); $i++) {
        $active = $i === $currentPage ? ' class="active" aria-current="page"' : '';
        $html  .= '<li><a href="' . e($baseUrl) . '?halaman=' . $i . '"' . $active . '>' . $i . '</a></li>';
    }

    // Tombol berikutnya
    if ($currentPage < $totalPages) {
        $html .= '<li><a href="' . e($baseUrl) . '?halaman=' . ($currentPage + 1) . '" aria-label="Halaman berikutnya">&raquo;</a></li>';
    }

    $html .= '</ul></nav>';
    return $html;
}

// ── Response Helper ──────────────────────────────────────────

/**
 * Kirim response JSON (untuk endpoint API/AJAX)
 * @param mixed $data
 */
function jsonResponse(mixed $data, int $statusCode = 200): never {
    http_response_code($statusCode);
    header('Content-Type: application/json; charset=UTF-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

// ── Pembiayaan ───────────────────────────────────────────────

function pembiayaanLabel(string $jenis): string {
    return match ($jenis) {
        'pendaftaran' => 'Biaya Pendaftaran',
        'administrasi' => 'Administrasi Awal',
        'wakaf'        => 'Wakaf',
        'laundry'      => 'Laundry',
        'infak'        => 'Infak Wajib',
        'syahriyah'    => 'Biaya Syahriyah (Bulanan)',
        default        => $jenis,
    };
}

function pembiayaanStatusLabel(string $status): string {
    return match ($status) {
        'belum'   => 'Belum dibayar',
        'menunggu'=> 'Menunggu verifikasi',
        'lunas'   => 'Lunas',
        'gratis'  => 'Gratis',
        'ditolak' => 'Bukti ditolak',
        default   => $status,
    };
}

function berkasLabel(string $jenis): string {
    return match ($jenis) {
        'kartu-keluarga'     => 'Scan KK',
        'akta-lahir'         => 'Scan Akta Lahir',
        'ktp-ortu'           => 'Scan KTP Orang Tua',
        'foto'               => 'Foto 3x4',
        'ijazah'             => 'Scan SKL/Ijazah',
        'sertifikat-tka'     => 'Sertifikat / Piagam Prestasi',
        'sertifikat-tahfidz' => 'Sertifikat Tahfidz',
        'mou-kaderisasi'     => 'MOU Kaderisasi (sudah ditandatangani)',
        'surat-rekomendasi'  => 'Surat Rekomendasi',
        'sktm'               => 'SKTM (Ket. Tidak Mampu)',
        'surat-pernyataan'   => 'Surat Pernyataan',
        'lainnya'            => 'Lainnya',
        default              => $jenis,
    };
}

// ── Jalur Pendaftaran PSB (Juknis TA 2027/2028) ──────────────

/** Daftar jalur pendaftaran + label tampilan. */
function jalurPendaftaran(): array {
    return [
        'reguler'      => 'Reguler',
        'prestasi'     => 'Prestasi (Akademik/Non-Akademik)',
        'tahfidz'      => 'Tahfidz Al-Qur\'an',
        'kaderisasi'   => 'Kaderisasi (Jalur Khusus)',
        'alumni-sdmua' => 'Alumni SD Muhammadiyah Unggulan Ashidiq',
        'dhuafa'       => 'Dhuafa / Beasiswa Empowerment',
    ];
}

/** Jalur yang butuh verifikasi berkas oleh admin sebelum potongan aktif. */
function jalurPerluVerifikasi(): array {
    return ['alumni-sdmua', 'dhuafa'];
}

/**
 * Detail pilihan per jalur (radio form) + potongan otomatis.
 * alumni-sdmua & dhuafa tidak di sini — potongannya ditetapkan admin.
 */
function jalurDetailOptions(): array {
    return [
        'prestasi' => [
            'kecamatan' => ['label' => 'Tingkat Kecamatan', 'potongan' => 20],
            'kabkota'   => ['label' => 'Tingkat Kabupaten/Kota', 'potongan' => 30],
            'provinsi'  => ['label' => 'Tingkat Provinsi', 'potongan' => 40],
            'nasional'  => ['label' => 'Tingkat Nasional/Internasional', 'potongan' => 50],
        ],
        'tahfidz' => [
            'juz-2' => ['label' => 'Hafalan lebih dari 2 Juz', 'potongan' => 20],
            'juz-3' => ['label' => 'Hafalan lebih dari 3 Juz', 'potongan' => 30],
            'juz-5' => ['label' => 'Hafalan lebih dari 5 Juz', 'potongan' => 50],
        ],
    ];
}

/**
 * Ambil pengaturan potongan jalur dari tabel jalur_potongan_admin.
 * Kalau admin belum mengatur, fallback ke nilai juknis (default seed).
 *
 * Kembalikan array berindeks jalur (reguler/prestasi/tahfidz/kaderisasi/alumni-sdmua/dhuafa)
 * berisi:
 *   potongan           => persen global per jalur (null jika tidak ada)
 *   adm                => tarif ADM khusus (null jika tidak ada)
 *   spp_l              => tarif SPP putra khusus (null jika tidak ada)
 *   spp_p              => tarif SPP putri khusus (null jika tidak ada)
 *   dhuafa_bebas       => bool, apakah ADM awal dhuafa dibebaskan 100%
 *   prestasi           => array berisi potongan per detail (kecamatan, kabkota, provinsi, nasional)
 *                        masing-masing null jika admin belum mengatur.
 *   tahfidz            => array berisi potongan per detail (juz2, juz3, juz5)
 *                        masing-masing null jika admin belum mengatur.
 *
 * Catatan: fungsi ini hanya pembaca global; keputusan per-santri tetap
 * di tabel pendaftaran (jalur_status, jalur_potongan) — terpisah.
 */
function getJalurPotonganAdmin(PDO $pdo): array {
    $rows = $pdo->query(
        "SELECT jalur, potongan_persen, adm_khusus, spp_l_khusus, spp_p_khusus, admin_dhuafa_bebas,
                prestasi_kecamatan, prestasi_kabkota, prestasi_provinsi, prestasi_nasional,
                tahfidz_juz2, tahfidz_juz3, tahfidz_juz5
         FROM jalur_potongan_admin"
    )->fetchAll();
    $out = [];
    foreach ($rows as $r) {
        $out[$r['jalur']] = [
            'potongan'   => isset($r['potongan_persen']) && $r['potongan_persen'] !== null
                ? (float) $r['potongan_persen'] : null,
            'adm'        => isset($r['adm_khusus']) && $r['adm_khusus'] !== null
                ? (float) $r['adm_khusus'] : null,
            'spp_l'      => isset($r['spp_l_khusus']) && $r['spp_l_khusus'] !== null
                ? (float) $r['spp_l_khusus'] : null,
            'spp_p'      => isset($r['spp_p_khusus']) && $r['spp_p_khusus'] !== null
                ? (float) $r['spp_p_khusus'] : null,
            'dhuafa_bebas' => (int) ($r['admin_dhuafa_bebas'] ?? 0) === 1,
            'prestasi'   => [
                'kecamatan' => isset($r['prestasi_kecamatan']) && $r['prestasi_kecamatan'] !== null
                    ? (float) $r['prestasi_kecamatan'] : null,
                'kabkota'  => isset($r['prestasi_kabkota']) && $r['prestasi_kabkota'] !== null
                    ? (float) $r['prestasi_kabkota'] : null,
                'provinsi' => isset($r['prestasi_provinsi']) && $r['prestasi_provinsi'] !== null
                    ? (float) $r['prestasi_provinsi'] : null,
                'nasional' => isset($r['prestasi_nasional']) && $r['prestasi_nasional'] !== null
                    ? (float) $r['prestasi_nasional'] : null,
            ],
            'tahfidz'    => [
                'juz2' => isset($r['tahfidz_juz2']) && $r['tahfidz_juz2'] !== null
                    ? (float) $r['tahfidz_juz2'] : null,
                'juz3' => isset($r['tahfidz_juz3']) && $r['tahfidz_juz3'] !== null
                    ? (float) $r['tahfidz_juz3'] : null,
                'juz5' => isset($r['tahfidz_juz5']) && $r['tahfidz_juz5'] !== null
                    ? (float) $r['tahfidz_juz5'] : null,
            ],
        ];
    }
    return $out;
}

/**
 * Hitung persen potongan otomatis berdasar jalur & detail.
 * Prioritas:
 *   1. Potongan per detail yang diatur admin (jika ada)
 *   2. Potongan global per jalur yang diatur admin (jika ada)
 *   3. Nilai juknis dari jalurDetailOptions() (fallback)
 *
 * Khusus kaderisasi: potongan berupa tarif tetap, ditangani terpisah
 * di snapshotPembiayaan(). Alumni & dhuafa ditetapkan admin (bukan di sini).
 */
function jalurPotonganOtomatis(string $jalur, ?string $detail, ?array $adminJalur = null): float {
    if ($detail === null) return 0.0;
    if ($adminJalur && isset($adminJalur[$jalur])) {
        // 1. Potongan per detail (admin)
        $detailAdmin = $adminJalur[$jalur];
        if ($jalur === 'prestasi' && isset($detailAdmin['prestasi'])) {
            $map = ['kecamatan' => 'kecamatan', 'kabkota' => 'kabkota',
                    'provinsi' => 'provinsi', 'nasional' => 'nasional'];
            $key = $map[$detail];
            if ($key && isset($detailAdmin['prestasi'][$key])) {
                return $detailAdmin['prestasi'][$key];
            }
        }
        if ($jalur === 'tahfidz' && isset($detailAdmin['tahfidz'])) {
            $map = ['juz-2' => 'juz2', 'juz-3' => 'juz3', 'juz-5' => 'juz5'];
            $key = $map[$detail];
            if ($key && isset($detailAdmin['tahfidz'][$key])) {
                return $detailAdmin['tahfidz'][$key];
            }
        }
        // 2. Potongan global (admin)
        if (isset($detailAdmin['potongan'])) {
            return $detailAdmin['potongan'];
        }
    }

    // 3. Fallback juknis
    $opt = jalurDetailOptions()[$jalur][$detail] ?? null;
    return $opt ? (float) $opt['potongan'] : 0.0;
}

/** Berkas yang wajib dilengkapi (selain bisa menyusul). */
function berkasWajib(): array {
    return ['kartu-keluarga', 'akta-lahir', 'ktp-ortu', 'foto'];
}

/**
 * Jalur yang disembunyikan dari pilihan calon santri di portal.
 * Jalur ini hanya bisa ditetapkan oleh panitia/admin.
 *
 * @return list<string>
 */
function jalurTersembunyi(): array {
    return ['alumni-sdmua', 'dhuafa'];
}

/**
 * Berkas tambahan per jalur pendaftaran.
 * Slot ini muncul kondisional di portal santri sesuai jalur pendaftar.
 * Kembalikan [] untuk jalur tanpa syarat berkas khusus.
 *
 * - reguler   : tidak ada berkas tambahan
 * - prestasi  : sertifikat / piagam tingkat tertinggi
 * - tahfidz   : sertifikat tahfidz (akan diuji tes hafalan)
 * - kaderisasi: MOU kaderisasi (download template, tanda tangan, upload ulang)
 * - alumni-sdmua & dhuafa: dikelola panitia (jalur tersembunyi)
 *
 * @return array<string, list<string>>
 */
function jalurBerkasSyarat(): array {
    return [
        'prestasi'    => ['sertifikat-tka'],
        'tahfidz'     => ['sertifikat-tahfidz'],
        'kaderisasi'  => ['mou-kaderisasi'],
        'alumni-sdmua' => ['surat-rekomendasi'],
        'dhuafa'       => ['sktm', 'surat-rekomendasi', 'surat-pernyataan'],
    ];
}

/**
 * Berkas tambahan yang berlaku untuk satu pendaftar tertentu.
 * Jalur yang ditolak (kembali reguler) otomatis kehilangan slot tambahan.
 *
 * @return list<string>
 */
function jalurBerkasUntuk(string $jalur): array {
    return jalurBerkasSyarat()[$jalur] ?? [];
}

/**
 * Ambil tarif pembiayaan aktif dari pembiayaan_tarif.
 * Kembalikan array berkelompok per jenis.
 */
function getPembiayaanTarif(PDO $pdo): array {
    $rows = $pdo->query(
        "SELECT * FROM pembiayaan_tarif WHERE is_active = 1 ORDER BY urutan, id"
    )->fetchAll();
    $tarif = ['pendaftaran' => [], 'administrasi' => [], 'wakaf' => [], 'laundry' => [], 'infak' => [], 'syahriyah' => []];
    foreach ($rows as $r) {
        $tarif[$r['jenis']][] = $r;
    }
    return $tarif;
}

/**
 * Buat snapshot pembiayaan per-santri dari tarif global.
 * Dipanggil saat santri mendaftar atau otomatis di portal/admin
 * jika snapshot belum ada (santri lama).
 *
 * $jalur/$jalurDetail/$jalurPotonganAdmin dipakai untuk menerapkan
 * potongan jalur PSB: potongan otomatis (prestasi/tahfidz) langsung
 * aktif, kaderisasi memakai tarif khusus, alumni/dhuafa memakai
 * persen yang ditetapkan admin.
 */
function snapshotPembiayaan(PDO $pdo, int $pendaftaranId, string $gender): void {
    $chk = $pdo->prepare('SELECT COUNT(*) FROM pembiayaan WHERE pendaftaran_id = ?');
    $chk->execute([$pendaftaranId]);
    if ((int) $chk->fetchColumn() > 0) return;

    // Baca jalur pendaftar (reguler/prestasi/tahfidz/kaderisasi/alumni-sdmua/dhuafa)
    $s = $pdo->prepare('SELECT jalur, jalur_detail, jalur_status, jalur_potongan FROM pendaftaran WHERE id = ?');
    $s->execute([$pendaftaranId]);
    $jRow = $s->fetch() ?: [];
    $jalur        = $jRow['jalur'] ?? 'reguler';
    $jalurDetail  = $jRow['jalur_detail'] ?? null;
    $jalurSetujui = ($jRow['jalur_status'] ?? 'none') === 'disetujui';

    $tarif = getPembiayaanTarif($pdo);
    $adminJalur = getJalurPotonganAdmin($pdo);

    // Tarif jalur kaderisasi: prioritaskan jalur_potongan_admin, fallback ke pengaturan/or seed.
    $kader = $adminJalur['kaderisasi'] ?? [];
    $kaderTarif = [
        'adm'  => $kader['adm']  ?? 5000000.0,
        'spp_l'=> $kader['spp_l'] ?? 650000.0,
        'spp_p'=> $kader['spp_p'] ?? 750000.0,
    ];

    $ins   = $pdo->prepare(
        "INSERT INTO pembiayaan
            (pendaftaran_id, jenis, nama, harga_asli, harga_diskon, gratis, nominal, status, urutan)
         VALUES (?,?,?,?,?,?,?,?,?)"
    );
    $urutan = 0;

    // Persen potongan aktif:
    // - prestasi/tahfidz : apabila admin mengatur potongan_persen, pakai nilai itu (langsung aktif).
    //   Kalau tidak, pakai juknis via jalurDetailOptions (jalurPotonganOtomatis).
    // - alumni-sdmua     : persen dari admin, aktif setelah jalur disetujui.
    // - dhuafa           : persen keringanan SPP dari admin, setelah disetujui.
    $persenPotongan = 0.0;
    if ($jalur === 'prestasi' || $jalur === 'tahfidz') {
        $persenPotongan = jalurPotonganOtomatis($jalur, $jalurDetail, $adminJalur);
    } elseif (in_array($jalur, ['alumni-sdmua', 'dhuafa'], true) && $jalurSetujui) {
        $persenPotongan = (float) ($jRow['jalur_potongan'] ?? 0);
    }

    $add = function (array $t, ?float $nominalOverride = null) use ($pdo, $ins, $pendaftaranId, &$urutan): void {
        $nominal = $t['gratis'] ? 0 : (float) ($t['harga_diskon'] ?? $t['harga_asli']);
        if ($nominalOverride !== null) $nominal = $nominalOverride;
        $status  = ($t['gratis'] || $nominal <= 0) ? 'gratis' : 'belum';
        $ins->execute([
            $pendaftaranId, $t['jenis'], $t['nama'], $t['harga_asli'],
            $t['harga_diskon'], $t['gratis'], $nominal, $status, ++$urutan,
        ]);
    };

    foreach (['pendaftaran', 'infak'] as $jenis) {
        foreach ($tarif[$jenis] as $t) $add($t);
    }

    foreach (['administrasi', 'wakaf'] as $jenis) {
        foreach ($tarif[$jenis] as $t) {
            if (!$t['gratis'] && $jenis === 'administrasi') {
                if ($jalur === 'kaderisasi') {
                    // ADM Awal khusus kader: tarif tetap dari jalur_potongan_admin (atau fallback seed).
                    $t['harga_diskon'] = $kaderTarif['adm'];
                } elseif ($jalur === 'dhuafa' && $jalurSetujui) {
                    // Dhuafa disetujui: kalau admin mengatur admin_dhuafa_bebas=1, ADM bebas 100%.
                    $adh = $adminJalur['dhuafa'] ?? [];
                    if (!empty($adh['dhuafa_bebas'])) {
                        $t['gratis'] = 1;
                    } else {
                        // Jika tidak bebas, pakai persen potongan per santri.
                        if ($persenPotongan > 0) {
                            $dasar  = (float) $t['harga_asli'];
                            $potong = round($dasar * $persenPotongan / 100);
                            $t['harga_diskon'] = max(0, $dasar - $potong);
                        }
                    }
                } elseif ($persenPotongan > 0) {
                    // Jalur potongan ADM Awal: nominal dipotong
                    // (jadikan harga_diskon agar tampil coret di UI).
                    $dasar   = (float) $t['harga_asli'];
                    $potong  = round($dasar * $persenPotongan / 100);
                    $t['harga_diskon'] = max(0, $dasar - $potong);
                }
            }
            $add($t);
        }
    }

    foreach ($tarif['syahriyah'] as $t) {
        if ($jalur === 'kaderisasi') {
            // SPP khusus kader (Juknis VIII): tarif tetap per gender dari jalur_potongan_admin.
            $kader             = $gender === 'P' ? $kaderTarif['spp_p'] : $kaderTarif['spp_l'];
            $t['nama']         = trim((string) ($t['nama'] ?: 'Syahriyah')) . ' (Jalur Kaderisasi)';
            $t['harga_diskon'] = $kader;
        } elseif ($jalur === 'dhuafa' && $jalurSetujui && $persenPotongan > 0) {
            // Keringanan SPP dhuafa sesuai keputusan admin (20-60%).
            $dasar  = (float) ($t['harga_diskon'] ?? $t['harga_asli']);
            $t['harga_diskon'] = max(0, round($dasar * (1 - $persenPotongan / 100)));
        }
        $add($t);
    }

    foreach ($tarif['laundry'] as $t) {
        if ($t['gender'] === 'all' || $t['gender'] === $gender) $add($t);
    }
}

/**
 * Sinkronkan snapshot pembiayaan santri dengan tarif admin terbaru.
 * Snapshot dibuat ulang dari pembiayaan_tarif selama santri belum
 * mengunci: biaya pendaftaran masih 'belum' DAN kesanggupan belum
 * ditandatangani. Setelah bayar/ttd, tagihan membeku.
 */
function syncPembiayaan(PDO $pdo, int $pendaftaranId, string $gender): void {
    snapshotPembiayaan($pdo, $pendaftaranId, $gender);

    $s = $pdo->prepare('SELECT kesanggupan_setuju FROM pendaftaran WHERE id=?');
    $s->execute([$pendaftaranId]);
    if ((int) $s->fetchColumn() === 1) return; // sudah ttd → terkunci

    $s = $pdo->prepare("SELECT status FROM pembiayaan WHERE pendaftaran_id=? AND jenis='pendaftaran' LIMIT 1");
    $s->execute([$pendaftaranId]);
    $pStatus = $s->fetchColumn();
    // Sudah bayar/menunggu/gratis → terkunci. 'belum' & 'ditolak' → ikut tarif terbaru.
    if ($pStatus === 'menunggu' || $pStatus === 'lunas' || $pStatus === 'gratis') return;

    $pdo->prepare('DELETE FROM pembiayaan WHERE pendaftaran_id=?')->execute([$pendaftaranId]);
    snapshotPembiayaan($pdo, $pendaftaranId, $gender);
}

/**
 * Format angka jadi Rupiah (Rp 1.250.000).
 */
function formatRupiah(float $nominal): string {
    return 'Rp ' . number_format($nominal, 0, ',', '.');
}

/**
 * Render harga item pembiayaan (gratis / coret diskon / normal).
 * @param array<string, mixed> $item
 */
function hargaItem(array $item): string {
    if (!empty($item['gratis'])) {
        return '<span class="price-gratis">GRATIS</span>';
    }
    $asli  = formatRupiah((float) $item['harga_asli']);
    $bayar = formatRupiah((float) $item['nominal']);
    if ($item['harga_diskon'] !== null && (float) $item['harga_diskon'] < (float) $item['harga_asli']) {
        return '<s>' . $asli . '</s> <strong>' . $bayar . '</strong>';
    }
    return $bayar;
}

/**
 * Simpan gambar tanda tangan (data URL PNG) dari canvas.
 * Kembalikan nama file atau false jika tidak valid.
 */
function saveSignature(string $dataUrl, string $destDir): string|false {
    if (!preg_match('#^data:image/png;base64,#i', $dataUrl, $m)) {
        return false;
    }
    $bin = base64_decode(substr($dataUrl, strlen($m[0])), true);
    if ($bin === false || strlen($bin) < 8 || strlen($bin) > 500000) {
        return false;
    }
    // Cek magic bytes PNG: 89 50 4E 47 0D 0A 1A 0A
    if (substr($bin, 0, 8) !== "\x89PNG\x0D\x0A\x1A\x0A") {
        return false;
    }
    if (!is_dir($destDir)) mkdir($destDir, 0755, true);
    $file = 'sign-' . bin2hex(random_bytes(16)) . '.png';
    if (file_put_contents($destDir . DIRECTORY_SEPARATOR . $file, $bin) === false) {
        return false;
    }
    return $file;
}

// ── Verifikasi Jalur PSB (alumni-sdmua & dhuafa) ─────────────

/**
 * Terapkan keputusan admin untuk jalur yang butuh verifikasi.
 * - disetujui : jalur_potongan = persen (alumni 25-50, dhuafa 20-60),
 *               snapshot pembiayaan di-rebuild agar potongan aktif.
 * - ditolak   : jalur dikembalikan ke reguler, snapshot di-rebuild.
 *
 * Aman dipanggil kapan pun: snapshot hanya di-rebuild jika tagihan
 * belum terkunci (belum bayar pendaftaran & belum tanda tangan).
 *
 * @return array{ok: bool, pesan: string}
 */
function jalurTerapkanKeputusan(PDO $pdo, int $pendaftaranId, string $keputusan, ?float $potongan = null): array {
    $s = $pdo->prepare('SELECT jenis_kelamin, jalur, jalur_status FROM pendaftaran WHERE id = ?');
    $s->execute([$pendaftaranId]);
    $p = $s->fetch();
    if (!$p) return ['ok' => false, 'pesan' => 'Data pendaftaran tidak ditemukan.'];

    $jalurLama = $p['jalur'];

    if ($keputusan === 'disetujui') {
        if (!in_array($jalurLama, jalurPerluVerifikasi(), true)) {
            return ['ok' => false, 'pesan' => 'Jalur ini tidak memerlukan verifikasi.'];
        }
        // Batas persen: alumni 25-50 (Juknis VII.C), dhuafa 20-60 (kebijakan).
        $min = $jalurLama === 'alumni-sdmua' ? 25.0 : 20.0;
        $max = $jalurLama === 'alumni-sdmua' ? 50.0 : 60.0;
        if ($potongan === null || $potongan < $min || $potongan > $max) {
            return ['ok' => false, 'pesan' => "Persen potongan harus antara " . (int) $min . "%–" . (int) $max . "% untuk jalur ini."];
        }
        $pdo->prepare("UPDATE pendaftaran SET jalur_status='disetujui', jalur_potongan=? WHERE id=?")
            ->execute([$potongan, $pendaftaranId]);
    } elseif ($keputusan === 'ditolak') {
        $pdo->prepare("UPDATE pendaftaran SET jalur='reguler', jalur_detail=NULL, jalur_status='none', jalur_potongan=NULL WHERE id=?")
            ->execute([$pendaftaranId]);
    } else {
        return ['ok' => false, 'pesan' => 'Keputusan tidak valid.'];
    }

    // Rebuild snapshot HANYA jika belum terkunci (belum bayar & belum ttd).
    // Jika sudah terkunci, keputusan tetap tersimpan di pendaftaran;
    // penyesuaian nominal tagihan dilakukan manual oleh admin.
    $s = $pdo->prepare('SELECT kesanggupan_setuju FROM pendaftaran WHERE id=?');
    $s->execute([$pendaftaranId]);
    $ttd = (int) $s->fetchColumn() === 1;
    $s   = $pdo->prepare("SELECT status FROM pembiayaan WHERE pendaftaran_id=? AND jenis='pendaftaran' LIMIT 1");
    $s->execute([$pendaftaranId]);
    $bayar = in_array($s->fetchColumn(), ['menunggu', 'lunas', 'gratis'], true);
    if ($ttd || $bayar) {
        return ['ok' => true, 'pesan' => 'Keputusan jalur tersimpan. Tagihan sudah terkunci — sesuaikan nominal manual bila perlu.'];
    }

    $pdo->prepare('DELETE FROM pembiayaan WHERE pendaftaran_id=?')->execute([$pendaftaranId]);
    snapshotPembiayaan($pdo, $pendaftaranId, $p['jenis_kelamin']);

    return ['ok' => true, 'pesan' => 'Keputusan jalur diterapkan. Tagihan santri diperbarui otomatis.'];
}

/** Label ringkas status verifikasi jalur untuk tabel admin. */
function jalurStatusLabel(string $status): string {
    return match ($status) {
        'none'      => '—',
        'pending'   => 'Menunggu Verifikasi',
        'disetujui' => 'Disetujui',
        'ditolak'   => 'Ditolak',
        default     => $status,
    };
}

// ── JUKNIS PSB TA 2027/2028 — Gelombang & Tarif ─────────────

/** Generate password awal 6 digit angka. */
function generatePasswordAwal(): string {
    return (string) random_int(100000, 999999);
}

/** Generate nomor pendaftaran ASQ-YYYY-XXXX (per tahun). */
function generateNomorDaftar(PDO $pdo): string {
    $tahun = date('Y');
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM pendaftaran WHERE nomor_daftar LIKE ?");
    $stmt->execute(["ASQ-$tahun-%"]);
    $urutan = (int) $stmt->fetchColumn() + 1;
    return "ASQ-$tahun-" . str_pad((string) $urutan, 4, '0', STR_PAD_LEFT);
}

/** Gelombang aktif auto-detect dari tanggal hari ini. NULL jika di luar semua tahap. */
function autoDetectGelombang(PDO $pdo): ?array {
    $today = date('Y-m-d');
    $stmt = $pdo->prepare(
        "SELECT * FROM pendaftaran_gelombang
         WHERE is_active=1 AND ? BETWEEN tanggal_buka AND tanggal_tutup
         ORDER BY urutan LIMIT 1"
    );
    $stmt->execute([$today]);
    $row = $stmt->fetch();
    return $row ?: null;
}

/** Ambil semua gelombang (untuk pilihan admin). */
function getAllGelombang(PDO $pdo, bool $onlyActive = true): array {
    $sql = "SELECT * FROM pendaftaran_gelombang";
    if ($onlyActive) $sql .= " WHERE is_active=1";
    $sql .= " ORDER BY urutan";
    return $pdo->query($sql)->fetchAll();
}

/** Ambil tarif aktif untuk snapshot, filter per gelombang + jenis + gender. */
function getTarifByGelombang(PDO $pdo, int $gelombangId, ?string $gender = null): array {
    $sql = "SELECT * FROM pembiayaan_tarif
            WHERE is_active=1 AND (gelombang_id = :g OR gelombang_id IS NULL)";
    $params = [':g' => $gelombangId];
    if ($gender) {
        $sql .= " AND (gender = 'all' OR gender = :gd)";
        $params[':gd'] = $gender;
    }
    $sql .= " ORDER BY jenis, urutan";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll();
}

/** Hitung simulasi biaya (untuk AJAX + JS live). */
function getSimulasiBiaya(PDO $pdo, string $jalur, ?string $jalurDetail,
                          int $gelombangId, string $gender): array {
    $tarif = getTarifByGelombang($pdo, $gelombangId, $gender);
    $adminJalur = getJalurPotonganAdmin($pdo);
    $kader = $adminJalur['kaderisasi'] ?? [];

    $result = [
        'pendaftaran' => 0, 'administrasi_asli' => 0, 'administrasi' => 0,
        'wakaf' => 0, 'syahriyah' => 0,
        'potongan' => 0, 'potongan_label' => '',
        'total' => 0, 'detail' => [],
    ];

    foreach ($tarif as $t) {
        if ($t['jenis'] !== 'pendaftaran') continue;
        $result['pendaftaran'] = (float) ((int) $t['gratis'] ? 0 : $t['harga_asli']);
        $result['detail'][] = ['label' => $t['nama'], 'nominal' => $result['pendaftaran']];
        break;
    }

    foreach ($tarif as $t) {
        if ($t['jenis'] !== 'administrasi') continue;
        $harga_asli = (float) $t['harga_asli'];
        $harga_setelah = $harga_asli;

        if ($jalur === 'kaderisasi') {
            $harga_setelah = (float) ($kader['adm'] ?? 5000000);
            $result['potongan_label'] = 'Tarif khusus kaderisasi';
        } elseif (in_array($jalur, ['prestasi', 'tahfidz'], true) && $jalurDetail) {
            $potonganPct = jalurPotonganOtomatis($jalur, $jalurDetail, $adminJalur);
            $harga_setelah = $harga_asli * (1 - $potonganPct / 100);
            $result['potongan_label'] = "Potongan otomatis ({$potonganPct}%)";
        } elseif ($jalur === 'dhuafa') {
            $bebas = !empty($adminJalur['dhuafa']['dhuafa_bebas']);
            $harga_setelah = $bebas ? 0.0 : $harga_asli;
            $result['potongan_label'] = $bebas ? 'ADM Awal dibebaskan (Dhuafa)' : '';
        }

        if ($t['harga_diskon'] !== null && (float) $t['harga_diskon'] < $harga_setelah) {
            $harga_setelah = (float) $t['harga_diskon'];
            if (!$result['potongan_label']) {
                $result['potongan_label'] = 'Potongan tahap Indent';
            }
        }

        $result['administrasi_asli'] = $harga_asli;
        $result['administrasi'] = $harga_setelah;
        $result['potongan'] = $harga_asli - $harga_setelah;
        $result['detail'][] = [
            'label' => $t['nama'], 'nominal' => $harga_asli,
            'setelah_potongan' => $harga_setelah,
        ];
        break;
    }

    foreach ($tarif as $t) {
        if ($t['jenis'] !== 'wakaf') continue;
        $result['wakaf'] = (float) $t['harga_asli'];
        $result['detail'][] = ['label' => $t['nama'], 'nominal' => $result['wakaf']];
        break;
    }

    if ($jalur === 'kaderisasi') {
        $result['syahriyah'] = $gender === 'P'
            ? (float) ($kader['spp_p'] ?? 750000)
            : (float) ($kader['spp_l'] ?? 650000);
        $result['detail'][] = [
            'label' => 'SPP Kaderisasi (termasuk laundry + infak)',
            'nominal' => $result['syahriyah'], 'per_bulan' => true,
        ];
    } else {
        foreach ($tarif as $t) {
            if ($t['jenis'] !== 'syahriyah') continue;
            $result['syahriyah'] = (float) $t['harga_asli'];
            $result['detail'][] = [
                'label' => $t['nama'] ?? 'SPP Bulanan',
                'nominal' => $result['syahriyah'], 'per_bulan' => true,
            ];
            break;
        }
    }

    $result['total'] = $result['pendaftaran'] + $result['administrasi'] + $result['wakaf'];
    return $result;
}

/** Cek apakah pendaftaran sudah punya snapshot final. */
function isSnapshotFinal(int $pendaftaranId, PDO $pdo): bool {
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM pembiayaan WHERE pendaftaran_id = ?");
    $stmt->execute([$pendaftaranId]);
    return (int) $stmt->fetchColumn() > 0;
}

/** Apply snapshot final ke pembiayaan saat status='diterima'. Idempotent. */
function applyTarifToSnapshot(PDO $pdo, int $pendaftaranId): bool {
    if (isSnapshotFinal($pendaftaranId, $pdo)) return false;

    $s = $pdo->prepare(
        'SELECT jalur, jalur_detail, jalur_status, jalur_potongan,
                jenis_kelamin, gelombang_id
         FROM pendaftaran WHERE id = ?'
    );
    $s->execute([$pendaftaranId]);
    $row = $s->fetch();
    if (!$row || !$row['gelombang_id']) return false;

    if (in_array($row['jalur'], ['alumni-sdmua', 'dhuafa'], true)
        && $row['jalur_status'] !== 'disetujui') {
        return false;
    }

    $simulasi = getSimulasiBiaya(
        $pdo, $row['jalur'], $row['jalur_detail'],
        (int) $row['gelombang_id'], $row['jenis_kelamin']
    );

    // Override dengan potongan admin (jika diset utk alumni/dhuafa)
    if (in_array($row['jalur'], ['alumni-sdmua', 'dhuafa'], true)
        && $row['jalur_potongan'] !== null) {
        $tarifRaw = getTarifByGelombang($pdo, (int) $row['gelombang_id'], $row['jenis_kelamin']);
        foreach ($tarifRaw as $t) {
            if ($t['jenis'] === 'administrasi') {
                $harga_asli = (float) $t['harga_asli'];
                $simulasi['administrasi_asli'] = $harga_asli;
                $simulasi['administrasi'] = $harga_asli * (1 - (float) $row['jalur_potongan'] / 100);
                $simulasi['potongan'] = $harga_asli - $simulasi['administrasi'];
                $simulasi['potongan_label'] = "Potongan admin ({$row['jalur_potongan']}%)";
            }
        }
    }

    $pdo->beginTransaction();
    try {
        $ins = $pdo->prepare(
            'INSERT INTO pembiayaan
                (pendaftaran_id, jenis, nama, harga_asli, harga_diskon, gratis,
                 nominal, status, urutan)
             VALUES (?,?,?,?,?,?,?,?,?)'
        );
        $ins->execute([
            $pendaftaranId, 'pendaftaran', 'Biaya Pendaftaran',
            $simulasi['pendaftaran'], null,
            $simulasi['pendaftaran'] === 0.0 ? 1 : 0,
            $simulasi['pendaftaran'], 'belum', 1,
        ]);
        $ins->execute([
            $pendaftaranId, 'administrasi', 'ADM Awal',
            $simulasi['administrasi_asli'],
            $simulasi['potongan'] > 0 ? $simulasi['potongan'] : null,
            0, $simulasi['administrasi'], 'belum', 2,
        ]);
        $ins->execute([
            $pendaftaranId, 'wakaf', 'Wakaf Pembangunan',
            $simulasi['wakaf'], null, 0, $simulasi['wakaf'], 'belum', 3,
        ]);
        if ($row['jalur'] === 'kaderisasi' && $simulasi['syahriyah'] > 0) {
            $ins->execute([
                $pendaftaranId, 'syahriyah', 'SPP Kaderisasi',
                $simulasi['syahriyah'], null, 0, $simulasi['syahriyah'], 'belum', 4,
            ]);
        }
        $pdo->commit();
        return true;
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        error_log('Snapshot gagal: ' . $e->getMessage());
        return false;
    }
}

/** Label status pendaftaran (untuk badge UI). */
function statusLabel(string $status): string {
    return match ($status) {
        'pending'             => 'Baru Daftar',
        'menunggu-verifikasi' => 'Menunggu Verifikasi',
        'tes-selesai'         => 'Tes Selesai',
        'diterima'            => 'Diterima',
        'ditolak'             => 'Ditolak',
        'daftar-ulang'        => 'Daftar Ulang',
        default               => $status,
    };
}

/** Cek apakah status mengizinkan upload berkas. */
function isBerkasEditable(string $status): bool {
    return in_array($status, ['pending', 'menunggu-verifikasi'], true);
}
