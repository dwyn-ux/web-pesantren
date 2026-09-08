<?php
/**
 * API: Upload berkas calon-santri.
 *
 * POST multipart/form-data:
 *   - csrf_token     (required)
 *   - jenis          enum: kartu-keluarga, akta-lahir, ijazah, foto, ktp-ortu, bukti-bayar,
 *                          sertifikat-tka, surat-rekomendasi, sktm, surat-pernyataan, mou-kaderisasi
 *   - file           (required) file upload
 *
 * Response: JSON {success, message, file?, url?}
 */
require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/../config/session.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

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
    'sertifikat-tka', 'surat-rekomendasi', 'sktm', 'surat-pernyataan', 'mou-kaderisasi',
];
if (!in_array($jenis, $jenisValid, true)) {
    echo json_encode(['success' => false, 'message' => 'Jenis berkas tidak valid.']);
    exit;
}

// Cegah upload jika status bukan editable
if (!isBerkasEditable($pendaftaran['status']) && $jenis !== 'bukti-bayar') {
    http_response_code(403);
    echo json_encode([
        'success' => false,
        'message' => 'Berkas tidak dapat diunggah pada status "' . statusLabel($pendaftaran['status']) . '".',
    ]);
    exit;
}

if (empty($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
    echo json_encode(['success' => false, 'message' => 'File tidak diterima.']);
    exit;
}

$file = $_FILES['file'];

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

// Max size: 5 MB (foto bukti bayar boleh 2MB, lainnya 5MB)
$maxSize = $jenis === 'foto' ? 2 * 1024 * 1024 : 5 * 1024 * 1024;
if ($file['size'] > $maxSize) {
    echo json_encode([
        'success' => false,
        'message' => 'Ukuran file terlalu besar (maks ' . ($maxSize / 1024 / 1024) . ' MB).',
    ]);
    exit;
}

$ext = $mimeAllowed[$mime];
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

// ── Resize gambar (kecuali PDF) ──
if (str_starts_with($mime, 'image/') && $mime !== 'image/webp') {
    $info = getimagesize($file['tmp_name']);
    if ($info !== false) {
        [$w, $h, $type] = $info;
        $maxW = 1200;
        if ($w > $maxW) {
            $ratio = $maxW / $w;
            $newW = $maxW;
            $newH = (int) ($h * $ratio);
            $src = match ($type) {
                IMAGETYPE_JPEG => imagecreatefromjpeg($file['tmp_name']),
                IMAGETYPE_PNG  => imagecreatefrompng($file['tmp_name']),
                default        => null,
            };
            if ($src) {
                $canvas = imagecreatetruecolor($newW, $newH);
                if ($type === IMAGETYPE_PNG) {
                    imagealphablending($canvas, false);
                    imagesavealpha($canvas, true);
                }
                imagecopyresampled($canvas, $src, 0, 0, 0, 0, $newW, $newH, $w, $h);
                $ok = match ($type) {
                    IMAGETYPE_JPEG => imagejpeg($canvas, $destPath, 85),
                    IMAGETYPE_PNG  => imagepng($canvas, $destPath, 6),
                    default        => false,
                };
                imagedestroy($src);
                imagedestroy($canvas);
                if (!$ok) {
                    echo json_encode(['success' => false, 'message' => 'Gagal resize gambar.']);
                    exit;
                }
            } else {
                if (!move_uploaded_file($file['tmp_name'], $destPath)) {
                    echo json_encode(['success' => false, 'message' => 'Gagal memindahkan file.']);
                    exit;
                }
            }
        } else {
            if (!move_uploaded_file($file['tmp_name'], $destPath)) {
                echo json_encode(['success' => false, 'message' => 'Gagal memindahkan file.']);
                exit;
            }
        }
    }
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
$cek = $pdo->prepare('SELECT id, nama_file FROM berkas_santri WHERE pendaftaran_id = ? AND jenis = ? LIMIT 1');
$cek->execute([$pendaftaranId, $jenis]);
$existing = $cek->fetch();

if ($existing) {
    // Hapus file lama
    $oldPath = __DIR__ . '/../uploads/' . $existing['nama_file'];
    if (is_file($oldPath)) @unlink($oldPath);
    $upd = $pdo->prepare('UPDATE berkas_santri SET nama_file = ?, mime_type = ?, created_at = NOW() WHERE id = ?');
    $upd->execute([$dbPath, $mime, $existing['id']]);
} else {
    $ins = $pdo->prepare(
        'INSERT INTO berkas_santri (pendaftaran_id, jenis, nama_file, mime_type)
         VALUES (?,?,?,?)'
    );
    $ins->execute([$pendaftaranId, $jenis, $dbPath, $mime]);
}

echo json_encode([
    'success' => true,
    'message' => 'Berkas berhasil diunggah.',
    'jenis'   => $jenis,
    'file'    => $newName,
    'url'     => BASE_URL . '/berkas-santri?jenis=' . $jenis,
]);
