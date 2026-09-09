<?php
/**
 * API: Upload berkas calon-santri.
 *
 * POST multipart/form-data:
 *   - csrf_token     (required)
 *   - jenis          enum: kartu-keluarga, akta-lahir, ijazah, foto, ktp-ortu, bukti-bayar,
 *                          sertifikat-tka, sertifikat-tahfidz, surat-rekomendasi, sktm,
 *                          surat-pernyataan, mou-kaderisasi
 *   - file           (required) file upload; gambar diterima s/d 10MB lalu
 *                    dikompres server ke ≤1MB, PDF maks 5MB
 *
 * Response: JSON {success, message, file?, url?}
 */
require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/../config/session.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

/**
 * Simpan gambar ke $dest dengan target ukuran ≤ $targetBytes.
 * JPEG/WEBP: turunkan dimensi + quality bertahap.
 * PNG besar: coba kompresi dulu, kalau tetap besar konversi ke JPG.
 * File kecil (≤ target & lebar ≤1600) langsung dipindah tanpa olah ulang.
 *
 * @return array{ok:bool, error:string, ext:string, mime:string}
 */
function simpanGambarKompres(string $tmp, string $dest, string $mime, int $targetBytes): array {
    $fail = static fn(string $e) => ['ok' => false, 'error' => $e, 'ext' => '', 'mime' => $mime];
    $info = @getimagesize($tmp);
    if ($info === false) {
        if (@filesize($tmp) <= $targetBytes && @move_uploaded_file($tmp, $dest)) {
            return ['ok' => true, 'error' => '', 'ext' => 'jpg', 'mime' => $mime];
        }
        return $fail('Gambar tidak terbaca. Gunakan file JPG/PNG/WEBP yang valid.');
    }
    [$w, $h, $type] = $info;
    if ($w <= 0 || $h <= 0) {
        return $fail('Dimensi gambar tidak valid.');
    }
    // Jalur cepat: sudah kecil → pindah langsung
    if (@filesize($tmp) <= $targetBytes && $w <= 1600) {
        if (@move_uploaded_file($tmp, $dest)) {
            $ext = match ($type) {
                IMAGETYPE_JPEG => 'jpg',
                IMAGETYPE_PNG => 'png',
                IMAGETYPE_WEBP => 'webp',
                default => 'jpg',
            };
            $m = match ($type) {
                IMAGETYPE_JPEG => 'image/jpeg',
                IMAGETYPE_PNG => 'image/png',
                IMAGETYPE_WEBP => 'image/webp',
                default => $mime,
            };
            return ['ok' => true, 'error' => '', 'ext' => $ext, 'mime' => $m];
        }
        return $fail('Gagal memindahkan file.');
    }
    $src = match ($type) {
        IMAGETYPE_JPEG => (function_exists('imagecreatefromjpeg') ? @imagecreatefromjpeg($tmp) : null),
        IMAGETYPE_PNG => (function_exists('imagecreatefrompng') ? @imagecreatefrompng($tmp) : null),
        IMAGETYPE_WEBP => (function_exists('imagecreatefromwebp') ? @imagecreatefromwebp($tmp) : null),
        default => null,
    };
    if (!$src) {
        // GD tidak mendukung format ini → pindah mentah bila ≤ target
        if (@filesize($tmp) <= $targetBytes && @move_uploaded_file($tmp, $dest)) {
            $extJatuh = match ($type) {
                IMAGETYPE_JPEG => 'jpg',
                IMAGETYPE_PNG => 'png',
                IMAGETYPE_WEBP => 'webp',
                default => 'jpg',
            };
            return ['ok' => true, 'error' => '', 'ext' => $extJatuh, 'mime' => $mime];
        }
        return $fail('Gambar tidak terbaca. Gunakan file JPG/PNG/WEBP yang valid.');
    }
    $buatKanvas = static function ($srcImg, int $w, int $h, int $maxDim, bool $alpha): array {
        $skala = min(1.0, $maxDim / max($w, $h));
        $nw = max(1, (int) round($w * $skala));
        $nh = max(1, (int) round($h * $skala));
        $kanvas = imagecreatetruecolor($nw, $nh);
        if ($alpha) {
            imagealphablending($kanvas, false);
            imagesavealpha($kanvas, true);
            $trans = imagecolorallocatealpha($kanvas, 0, 0, 0, 127);
            imagefill($kanvas, 0, 0, $trans);
        }
        imagecopyresampled($kanvas, $srcImg, 0, 0, 0, 0, $nw, $nh, $w, $h);
        return [$kanvas, $nw, $nh];
    };
    // JPEG / WEBP: loop dimensi × quality
    if ($type === IMAGETYPE_JPEG || $type === IMAGETYPE_WEBP) {
        $isWebp = $type === IMAGETYPE_WEBP;
        $bisaTulis = $isWebp ? function_exists('imagewebp') : function_exists('imagejpeg');
        if (!$bisaTulis) {
            imagedestroy($src);
            if (@filesize($tmp) <= $targetBytes && @move_uploaded_file($tmp, $dest)) {
                return ['ok' => true, 'error' => '', 'ext' => $isWebp ? 'webp' : 'jpg', 'mime' => $mime];
            }
            return $fail('Server tidak mendukung kompresi format ini dan file di atas 1MB.');
        }
        foreach ([1600, 1280, 960] as $maxDim) {
            foreach ([85, 75, 65, 55] as $q) {
                [$kanvas] = $buatKanvas($src, $w, $h, $maxDim, false);
                $ok = $isWebp
                    ? (function_exists('imagewebp') && @imagewebp($kanvas, $dest, $q))
                    : @imagejpeg($kanvas, $dest, $q);
                imagedestroy($kanvas);
                if ($ok && @filesize($dest) <= $targetBytes) {
                    imagedestroy($src);
                    return ['ok' => true, 'error' => '', 'ext' => $isWebp ? 'webp' : 'jpg',
                            'mime' => $isWebp ? 'image/webp' : 'image/jpeg'];
                }
            }
        }
        imagedestroy($src);
        @unlink($dest);
        return $fail('Gambar masih di atas 1MB setelah kompresi. Gunakan foto resolusi lebih kecil.');
    }
    // PNG: coba pertahankan PNG dulu
    if (!function_exists('imagepng')) {
        imagedestroy($src);
        if (@filesize($tmp) <= $targetBytes && @move_uploaded_file($tmp, $dest)) {
            return ['ok' => true, 'error' => '', 'ext' => 'png', 'mime' => $mime];
        }
        return $fail('Server tidak mendukung kompresi PNG dan file di atas 1MB.');
    }
    foreach ([1600, 1280] as $maxDim) {
        foreach ([6, 9] as $level) {
            [$kanvas] = $buatKanvas($src, $w, $h, $maxDim, true);
            $ok = @imagepng($kanvas, $dest, $level);
            imagedestroy($kanvas);
            if ($ok && @filesize($dest) <= $targetBytes) {
                imagedestroy($src);
                return ['ok' => true, 'error' => '', 'ext' => 'png', 'mime' => 'image/png'];
            }
        }
    }
    // PNG tetap besar → konversi ke JPG latar putih
    if (!function_exists('imagejpeg')) {
        imagedestroy($src);
        @unlink($dest);
        return $fail('Gambar masih di atas 1MB setelah kompresi. Gunakan foto resolusi lebih kecil.');
    }
    foreach ([1600, 1280, 960] as $maxDim) {
        foreach ([85, 75, 65] as $q) {
            [$kanvas] = $buatKanvas($src, $w, $h, $maxDim, false);
            $putih = imagecolorallocate($kanvas, 255, 255, 255);
            imagefill($kanvas, 0, 0, $putih);
            imagecopyresampled($kanvas, $src, 0, 0, 0, 0, imagesx($kanvas), imagesy($kanvas), $w, $h);
            $ok = @imagejpeg($kanvas, $dest, $q);
            imagedestroy($kanvas);
            if ($ok && @filesize($dest) <= $targetBytes) {
                imagedestroy($src);
                return ['ok' => true, 'error' => '', 'ext' => 'jpg', 'mime' => 'image/jpeg'];
            }
        }
    }
    imagedestroy($src);
    @unlink($dest);
    return $fail('Gambar masih di atas 1MB setelah kompresi. Gunakan foto resolusi lebih kecil.');
}

header('Content-Type: application/json; charset=UTF-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method tidak diizinkan.']);
    exit;
}

if (!isCalonSantri()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Silakan login terlebih dahulu.']);
    exit;
}

try {
    validateCsrf();
} catch (Throwable $e) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Token CSRF tidak valid.']);
    exit;
}

$pendaftaran = getCurrentPendaftaran();
if (!$pendaftaran) {
    http_response_code(404);
    echo json_encode(['success' => false, 'message' => 'Data pendaftaran tidak ditemukan.']);
    exit;
}

$pendaftaranId = (int) $pendaftaran['id'];
$jenis = sanitizeString($_POST['jenis'] ?? '');

$jenisValid = [
    'kartu-keluarga', 'akta-lahir', 'ijazah', 'foto', 'ktp-ortu',
    'bukti-bayar',
    'sertifikat-tka', 'sertifikat-tahfidz',
    'surat-rekomendasi', 'sktm', 'surat-pernyataan', 'mou-kaderisasi',
    'kip', 'skl',
];
if (!in_array($jenis, $jenisValid, true)) {
    echo json_encode(['success' => false, 'message' => 'Jenis berkas tidak valid.']);
    exit;
}

// Cegah upload jika status bukan editable.
// Pengecualian: berkas pelengkap (ijazah/kip/skl, opsional) boleh
// diupload setelah diterima/daftar-ulang.
$bolehPelengkap = in_array($jenis, ['ijazah', 'kip', 'skl'], true)
    && in_array($pendaftaran['status'], ['diterima', 'daftar-ulang'], true);
if (!isBerkasEditable($pendaftaran['status']) && $jenis !== 'bukti-bayar' && !$bolehPelengkap) {
    http_response_code(403);
    echo json_encode([
        'success' => false,
        'message' => 'Berkas tidak dapat diunggah pada status "' . statusLabel($pendaftaran['status']) . '".',
    ]);
    exit;
}

if (empty($_FILES['file'])) {
    echo json_encode(['success' => false, 'message' => 'File tidak diterima.']);
    exit;
}

$file = $_FILES['file'];

// ── Petakan kode error upload ke pesan yang jelas ──
if ($file['error'] !== UPLOAD_ERR_OK) {
    $pesan = match ($file['error']) {
        UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE =>
            'File terlalu besar untuk server (maks upload PHP). Kecilkan di bawah 10 MB lalu coba lagi.',
        UPLOAD_ERR_PARTIAL => 'Upload terputus. Silakan coba lagi.',
        UPLOAD_ERR_NO_FILE => 'File tidak diterima.',
        default => 'File tidak diterima (kode ' . (int) $file['error'] . ').',
    };
    echo json_encode(['success' => false, 'message' => $pesan]);
    exit;
}

$namaAsli = substr(basename($file['name'] ?? 'file'), 0, 255);

// ── Validasi MIME via finfo (jangan percaya $_FILES['type']) ──
$finfo = new finfo(FILEINFO_MIME_TYPE);
$mime = $finfo->file($file['tmp_name']);

// Whitelist: gambar + PDF
$mimeAllowed = [
    'image/jpeg' => 'jpg',
    'image/png'  => 'png',
    'image/webp' => 'webp',
    'application/pdf' => 'pdf',
];
if (!isset($mimeAllowed[$mime])) {
    echo json_encode(['success' => false, 'message' => 'Tipe file tidak diizinkan (' . $mime . '). Hanya JPG, PNG, WEBP, PDF.']);
    exit;
}

// Gambar diterima s/d 10MB lalu dikompres server ke ≤1MB.
// PDF tetap maks 5MB tanpa kompresi.
$isGambar = str_starts_with($mime, 'image/');
$maxSize = $isGambar ? 10 * 1024 * 1024 : 5 * 1024 * 1024;
if ($file['size'] > $maxSize) {
    echo json_encode([
        'success' => false,
        'message' => 'Ukuran file terlalu besar (maks ' . ($maxSize / 1024 / 1024) . ' MB).',
    ]);
    exit;
}

$ext = $mimeAllowed[$mime];
$finalMime = $mime;
$newName = bin2hex(random_bytes(16)) . '.' . $ext;

// ── Siapkan folder tujuan ──
$uploadDir = __DIR__ . '/../uploads/santri/' . $pendaftaranId;
if (!is_dir($uploadDir)) {
    if (!mkdir($uploadDir, 0755, true) && !is_dir($uploadDir)) {
        echo json_encode(['success' => false, 'message' => 'Gagal membuat folder upload.']);
        exit;
    }
}

$destPath = $uploadDir . '/' . $newName;
$dbPath = 'santri/' . $pendaftaranId . '/' . $newName;

// ── Simpan gambar dengan kompresi target ≤1MB (kecuali PDF) ──
if ($isGambar) {
    $simpan = simpanGambarKompres($file['tmp_name'], $destPath, $mime, 1024 * 1024);
    if (!$simpan['ok']) {
        echo json_encode(['success' => false, 'message' => $simpan['error']]);
        exit;
    }
    // PNG besar dikonversi ke JPG — selaraskan nama & mime
    if ($simpan['ext'] !== $ext) {
        $extLama = $uploadDir . '/' . $newName;
        $newName = bin2hex(random_bytes(16)) . '.' . $simpan['ext'];
        $destBaru = $uploadDir . '/' . $newName;
        rename($extLama, $destBaru);
        $destPath = $destBaru;
        $dbPath = 'santri/' . $pendaftaranId . '/' . $newName;
    }
    $finalMime = $simpan['mime'];
} else {
    if (!move_uploaded_file($file['tmp_name'], $destPath)) {
        echo json_encode(['success' => false, 'message' => 'Gagal memindahkan file.']);
        exit;
    }
}

chmod($destPath, 0644);

// ── Cek apakah sudah ada row berkas utk jenis ini ──
// (UPDATE) atau INSERT baru
$pdo = getDB();
try {
    $cek = $pdo->prepare('SELECT id, nama_file FROM berkas_santri WHERE pendaftaran_id = ? AND jenis = ? LIMIT 1');
    $cek->execute([$pendaftaranId, $jenis]);
    $existing = $cek->fetch();
} catch (PDOException $e) {
    // Kemungkinan ENUM jenis belum mencakup jenis baru (migrasi 016 belum jalan)
    @unlink($destPath);
    error_log('Upload berkas SELECT gagal: ' . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Jenis berkas belum didukung database. Hubungi admin (migrasi 016).']);
    exit;
}

// Kolom nama_asli opsional — ada di install baru, mungkin absen di DB lama
$kolomAsli = null;
try {
    $kolomAsli = $pdo->query(
        "SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE()
         AND TABLE_NAME='berkas_santri' AND COLUMN_NAME='nama_asli'"
    )->fetchColumn() ? true : false;
} catch (PDOException $e) {
    $kolomAsli = false;
}

try {
    if ($existing) {
        // Hapus file lama
        $oldPath = __DIR__ . '/../uploads/' . $existing['nama_file'];
        if (is_file($oldPath)) @unlink($oldPath);
        if ($kolomAsli) {
            $upd = $pdo->prepare('UPDATE berkas_santri SET nama_file = ?, nama_asli = ?, mime_type = ?, created_at = NOW() WHERE id = ?');
            $upd->execute([$dbPath, $namaAsli, $finalMime, $existing['id']]);
        } else {
            $upd = $pdo->prepare('UPDATE berkas_santri SET nama_file = ?, mime_type = ?, created_at = NOW() WHERE id = ?');
            $upd->execute([$dbPath, $finalMime, $existing['id']]);
        }
    } else {
        if ($kolomAsli) {
            $ins = $pdo->prepare(
                'INSERT INTO berkas_santri (pendaftaran_id, jenis, nama_file, nama_asli, mime_type)
                 VALUES (?,?,?,?,?)'
            );
            $ins->execute([$pendaftaranId, $jenis, $dbPath, $namaAsli, $finalMime]);
        } else {
            $ins = $pdo->prepare(
                'INSERT INTO berkas_santri (pendaftaran_id, jenis, nama_file, mime_type)
                 VALUES (?,?,?,?)'
            );
            $ins->execute([$pendaftaranId, $jenis, $dbPath, $finalMime]);
        }
    }
} catch (PDOException $e) {
    // Jangan tinggalkan file orphan di disk
    @unlink($destPath);
    error_log('Upload berkas INSERT gagal: ' . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Gagal menyimpan ke database. Hubungi admin.']);
    exit;
}

echo json_encode([
    'success' => true,
    'message' => 'Berkas berhasil diunggah.',
    'jenis'   => $jenis,
    'file'    => $newName,
    'url'     => BASE_URL . '/berkas-santri?jenis=' . $jenis,
]);
