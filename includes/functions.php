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
        return '<img src="' . e(BASE_URL . '/assets/img/' . $rel) . '"'
             . ' alt="' . e($alt) . '"' . $cls . $lazyAttr . '>';
    }
    return $placeholderHtml;
}

/**
 * Cek keberadaan file gambar di /assets/img/.
 * Berguna untuk menambahkan class CSS kondisional (mis. background-image).
 */
function imgExists(string $relativePath): bool {
    return is_file(ROOT_PATH . '/assets/img/' . ltrim($relativePath, '/'));
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
 * Resize gambar sebelum disimpan (optimasi performa shared hosting)
 */
function resizeImage(string $source, string $dest, int $maxWidth = 1200, int $quality = 85): bool {
    [$width, $height, $type] = getimagesize($source);

    if ($width <= $maxWidth) {
        // File upload sudah berada di tujuan; jangan menyalin file ke dirinya sendiri.
        return $source === $dest ? true : copy($source, $dest);
    }

    $ratio  = $maxWidth / $width;
    $newH   = (int) ($height * $ratio);
    $canvas = imagecreatetruecolor($maxWidth, $newH);

    $src = match ($type) {
        IMAGETYPE_JPEG => imagecreatefromjpeg($source),
        IMAGETYPE_PNG  => imagecreatefrompng($source),
        IMAGETYPE_WEBP => imagecreatefromwebp($source),
        default        => false,
    };

    if (!$src) return false;

    // Pertahankan transparansi PNG
    if ($type === IMAGETYPE_PNG) {
        imagealphablending($canvas, false);
        imagesavealpha($canvas, true);
    }

    imagecopyresampled($canvas, $src, 0, 0, 0, 0, $maxWidth, $newH, $width, $height);

    return match ($type) {
        IMAGETYPE_JPEG => imagejpeg($canvas, $dest, $quality),
        IMAGETYPE_PNG  => imagepng($canvas, $dest, 6),
        IMAGETYPE_WEBP => imagewebp($canvas, $dest, $quality),
        default        => false,
    };
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
 * Format tanggal ke format Indonesia (contoh: 26 April 2026)
 */
function formatTanggal(string $dateStr, bool $withTime = false): string {
    $bulan = [
        1  => 'Januari', 2  => 'Februari', 3  => 'Maret',
        4  => 'April',   5  => 'Mei',       6  => 'Juni',
        7  => 'Juli',    8  => 'Agustus',   9  => 'September',
        10 => 'Oktober', 11 => 'November', 12 => 'Desember',
    ];
    $ts     = strtotime($dateStr);
    $result = date('j', $ts) . ' ' . $bulan[(int) date('n', $ts)] . ' ' . date('Y', $ts);
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
