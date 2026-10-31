<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/session.php';

function validateAlumni(array $data): array {
    $errors = [];
    $activity = $data['aktivitas'] ?? '';

    if (trim($data['nama'] ?? '') === '' || mb_strlen(trim($data['nama'] ?? '')) > 120) {
        $errors[] = 'Nama alumni wajib diisi dan maksimal 120 karakter.';
    }

    $year = filter_var($data['tahun_kelulusan'] ?? null, FILTER_VALIDATE_INT);
    if ($year === false || $year < 1900 || $year > (int) date('Y')) {
        $errors[] = 'Tahun lulus tidak valid.';
    }

    $address = trim($data['alamat'] ?? '');
    if ($address === '') {
        $errors[] = 'Alamat wajib diisi.';
    } elseif (mb_strlen($address) > 2000) {
        $errors[] = 'Alamat maksimal 2000 karakter.';
    }

    if (!in_array($activity, ['kuliah', 'bekerja', 'kuliah_dan_bekerja'], true)) {
        $errors[] = 'Kesibukan tidak valid.';
    } else {
        if (in_array($activity, ['kuliah', 'kuliah_dan_bekerja'], true)) {
            if (trim($data['tempat_kuliah'] ?? '') === '') {
                $errors[] = 'Nama kampus wajib diisi.';
            }
            if (trim($data['jurusan'] ?? '') === '') {
                $errors[] = 'Program studi wajib diisi.';
            }
        }
        if (in_array($activity, ['bekerja', 'kuliah_dan_bekerja'], true)) {
            if (trim($data['tempat_bekerja'] ?? '') === '') {
                $errors[] = 'Tempat bekerja wajib diisi.';
            }
            if (trim($data['jabatan'] ?? '') === '') {
                $errors[] = 'Pekerjaan atau jabatan wajib diisi.';
            }
        }
    }

    if (mb_strlen(trim($data['tempat_kuliah'] ?? '')) > 180
        || mb_strlen(trim($data['jurusan'] ?? '')) > 180
        || mb_strlen(trim($data['tempat_bekerja'] ?? '')) > 180
        || mb_strlen(trim($data['jabatan'] ?? '')) > 180) {
        $errors[] = 'Informasi kuliah dan pekerjaan maksimal 180 karakter.';
    }

    if (mb_strlen(trim($data['pesan_kesan'] ?? '')) > 5000) {
        $errors[] = 'Pesan dan kesan maksimal 5000 karakter.';
    }

    return $errors;
}

function addAlumni(array $data, string $photoFilename): bool {
    $errors = validateAlumni($data);
    if ($errors) {
        $_SESSION['alumni_form_errors'] = $errors;
        return false;
    }

    $pdo = getDB();
    if (!$pdo) {
        $_SESSION['alumni_form_errors'] = ['Tidak dapat terhubung ke basis data. Silakan coba lagi nanti.'];
        return false;
    }

    try {
        $stmt = $pdo->prepare(
            'INSERT INTO alumni '
            . '(nama, tahun_kelulusan, alamat, aktivitas, tempat_kuliah, jurusan, tempat_bekerja, jabatan, pesan_kesan, foto) '
            . 'VALUES (:nama, :tahun, :alamat, :aktivitas, :tempat_kuliah, :jurusan, :tempat_bekerja, :jabatan, :pesan_kesan, :foto)'
        );
        $stmt->execute([
            ':nama' => trim($data['nama']),
            ':tahun' => (int) $data['tahun_kelulusan'],
            ':alamat' => trim($data['alamat']),
            ':aktivitas' => $data['aktivitas'],
            ':tempat_kuliah' => trim($data['tempat_kuliah'] ?? '') ?: null,
            ':jurusan' => trim($data['jurusan'] ?? '') ?: null,
            ':tempat_bekerja' => trim($data['tempat_bekerja'] ?? '') ?: null,
            ':jabatan' => trim($data['jabatan'] ?? '') ?: null,
            ':pesan_kesan' => trim($data['pesan_kesan'] ?? ''),
            ':foto' => $photoFilename,
        ]);
    } catch (PDOException $e) {
        error_log('Gagal menyimpan data alumni: ' . $e->getMessage());
        $_SESSION['alumni_form_errors'] = ['Data alumni belum dapat disimpan. Silakan coba lagi nanti.'];
        return false;
    }

    return true;
}
