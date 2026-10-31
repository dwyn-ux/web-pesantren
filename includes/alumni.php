<?php
/**
 * alumni.php – Functions to manage alumni records securely.
 *
 * All database interactions use PDO prepared statements to prevent
 * SQL injection. Input is validated and sanitized before use.
 * Output should be escaped with `htmlspecialchars` when rendered.
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/session.php';

/** Validate alumni data. Returns array of error messages (empty if OK). */
function validateAlumni(array $data): array {
    $errors = [];

    // Nama: non‑empty, max 100 chars
    $name = trim($data['name'] ?? '');
    if ($name === '' || mb_strlen($name) > 100) {
        $errors[] = 'Nama alumni tidak valid.';
    }

    // Email: valid format, max 150 chars
    $email = trim($data['email'] ?? '');
    if (!filter_var($email, FILTER_VALIDATE_EMAIL) || mb_strlen($email) > 150) {
        $errors[] = 'Email tidak valid.';
    }

    // Tahun lulus: integer antara 1900‑2100
    $year = $data['year'] ?? null;
    if (!filter_var($year, FILTER_VALIDATE_INT, ['options'=>['min_range'=>1900,'max_range'=>2100]])) {
        $errors[] = 'Tahun lulus tidak valid.';
    }

    // Jurusan: optional but max 100 chars
    $major = trim($data['major'] ?? '');
    if (mb_strlen($major) > 100) {
        $errors[] = 'Jurusan terlalu panjang.';
    }

    // ==== Job saat kuliah (opsional) ====
    $hasJob = $data['has_job'] ?? '0'; // expected '1' or '0'
    $hasJobBool = $hasJob == '1' || $hasJob === true || $hasJob === 1;
    if ($hasJobBool) {
        // job title optional, max 100 chars
        $jobTitle = trim($data['job_title'] ?? '');
        if ($jobTitle !== '' && mb_strlen($jobTitle) > 100) {
            $errors[] = 'Nama pekerjaan terlalu panjang.';
        }
        // income optional, must be positive integer if provided
        $incomeRaw = $data['job_income'] ?? '';
        if ($incomeRaw !== '') {
            if (!filter_var($incomeRaw, FILTER_VALIDATE_INT, ['options'=>['min_range'=>0]])) {
                $errors[] = 'Penghasilan harus berupa angka non‑negatif.';
            }
        }
    }

    return $errors;
}

/** Insert a new alumni record. Returns true on success. */
function addAlumni(array $data): bool {
    // Rate‑limit: max 5 submissions per IP per hour
    $rateKey = 'alumni-submit-' . ($_SERVER['REMOTE_ADDR'] ?? 'unknown');
    if (!checkLoginRateLimit($rateKey, 5, 3600)) {
        $_SESSION['alumni_form_errors'] = ['Terlalu banyak pengiriman. Coba lagi nanti.'];
        return false;
    }

    $errors = validateAlumni($data);
    if ($errors) {
        $_SESSION['alumni_form_errors'] = $errors;
        return false;
    }

    recordLoginAttempt($rateKey, false, 5, 3600);

    // Inside addAlumni function, after obtaining PDO, check for null
    $pdo = getDB();
    if ($pdo === null) {
        // Database not configured/connection failed – add friendly error
        $_SESSION['alumni_form_errors'] = ['Tidak dapat terhubung ke basis data. Silakan coba lagi nanti.'];
        return false;
    }

    $stmt = $pdo->prepare(
        'INSERT INTO alumni (name, email, graduation_year, major, has_job, job_title, job_income) '
        . 'VALUES (:name, :email, :year, :major, :has_job, :job_title, :job_income)'
    );
    $stmt->bindParam(':name', $data['name']);
    $stmt->bindParam(':email', $data['email']);
    $stmt->bindParam(':year', $data['year'], PDO::PARAM_INT);
    $stmt->bindParam(':major', $data['major']);
    $hasJob = ($data['has_job'] ?? '0') == '1' ? 1 : 0;
    $stmt->bindValue(':has_job', $hasJob, PDO::PARAM_INT);
    $stmt->bindValue(':job_title', $data['job_title'] ?? null);
    $income = $data['job_income'] ?? null;
    $stmt->bindValue(':job_income', $income !== '' ? $income : null, PDO::PARAM_INT);
    return $stmt->execute();
}

/** Update existing alumni record by ID. */
function updateAlumni(int $id, array $data): bool {
    $errors = validateAlumni($data);
    if ($errors) {
        $_SESSION['alumni_form_errors'] = $errors;
        return false;
    }
    // Inside addAlumni function, after obtaining PDO, check for null
    $pdo = getDB();
    if ($pdo === null) {
        // Database not configured/connection failed – add friendly error
        $_SESSION['alumni_form_errors'] = ['Tidak dapat terhubung ke basis data. Silakan coba lagi nanti.'];
        return false;
    }

    $stmt = $pdo->prepare(
        'UPDATE alumni SET name = :name, email = :email, graduation_year = :year, '
        . 'major = :major, has_job = :has_job, job_title = :job_title, job_income = :job_income '
        . 'WHERE id = :id'
    );
    $stmt->bindParam(':name', $data['name']);
    $stmt->bindParam(':email', $data['email']);
    $stmt->bindParam(':year', $data['year'], PDO::PARAM_INT);
    $stmt->bindParam(':major', $data['major']);
    $hasJob = ($data['has_job'] ?? '0') == '1' ? 1 : 0;
    $stmt->bindValue(':has_job', $hasJob, PDO::PARAM_INT);
    $stmt->bindValue(':job_title', $data['job_title'] ?? null);
    $income = $data['job_income'] ?? null;
    $stmt->bindValue(':job_income', $income !== '' ? $income : null, PDO::PARAM_INT);
    $stmt->bindParam(':id', $id, PDO::PARAM_INT);
    return $stmt->execute();
}

/** Delete alumni by ID. */
function deleteAlumni(int $id): bool {
    // Inside addAlumni function, after obtaining PDO, check for null
    $pdo = getDB();
    if ($pdo === null) {
        // Database not configured/connection failed – add friendly error
        $_SESSION['alumni_form_errors'] = ['Tidak dapat terhubung ke basis data. Silakan coba lagi nanti.'];
        return false;
    }

    $stmt = $pdo->prepare('DELETE FROM alumni WHERE id = :id');
    $stmt->bindParam(':id', $id, PDO::PARAM_INT);
    return $stmt->execute();
}

/** Retrieve alumni record by ID (or null). */
function getAlumni(int $id): ?array {
    // Inside addAlumni function, after obtaining PDO, check for null
    $pdo = getDB();
    if ($pdo === null) {
        // Database not configured/connection failed – add friendly error
        $_SESSION['alumni_form_errors'] = ['Tidak dapat terhubung ke basis data. Silakan coba lagi nanti.'];
        return false;
    }

    $stmt = $pdo->prepare('SELECT * FROM alumni WHERE id = :id');
    $stmt->bindParam(':id', $id, PDO::PARAM_INT);
    $stmt->execute();
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    return $row ?: null;
}
?>