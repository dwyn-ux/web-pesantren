<?php
/**
 * API: Hitung simulasi biaya (server-side fallback untuk JS off).
 *
 * POST JSON atau form:
 *   - csrf_token
 *   - jalur           (required)
 *   - jalur_detail    (optional)
 *
 * Response: JSON {success, simulasi: {...}}
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
    echo json_encode(['success' => false, 'message' => 'Silakan login.']);
    exit;
}

try { validateCsrf(); }
catch (Throwable $e) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'CSRF tidak valid.']);
    exit;
}

$pendaftaran = getCurrentPendaftaran();
if (!$pendaftaran || !$pendaftaran['gelombang_id']) {
    echo json_encode(['success' => false, 'message' => 'Gelombang tidak terdeteksi.']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true) ?: $_POST;
$jalur = sanitizeString($input['jalur'] ?? 'reguler');
$jalurDetail = sanitizeString($input['jalur_detail'] ?? '') ?: null;

$validJalur = array_keys(jalurPendaftaran());
if (!in_array($jalur, $validJalur, true)) {
    echo json_encode(['success' => false, 'message' => 'Jalur tidak valid.']);
    exit;
}

// Baca voucher Akashi yang valid menempel pada pendaftaran ini (jika ada)
$akashiJuara = null;
if ($jalur === 'prestasi' && $jalurDetail === 'internal') {
    $ak = getDB()->prepare("SELECT juara FROM voucher_akashi WHERE pendaftaran_id = ? LIMIT 1");
    $ak->execute([(int) $pendaftaran['id']]);
    $akashiJuara = $ak->fetchColumn() ?: null;
}

$simulasi = getSimulasiBiaya(
    getDB(),
    $jalur,
    $jalurDetail,
    (int) $pendaftaran['gelombang_id'],
    $pendaftaran['jenis_kelamin'],
    $akashiJuara
);

echo json_encode(['success' => true, 'simulasi' => $simulasi]);
