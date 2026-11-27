<?php
// Profil santri — pakai pendaftaran milik akun login (calon-santri)
requireCalonSantri();
$pdo = getDB();
$pendaftaran = getCurrentPendaftaran();
if (!$pendaftaran) redirect('/psb');
$id = (int) $pendaftaran['id'];
$userId = (int) ($_SESSION['user_id'] ?? 0);

$s = $pdo->prepare(
    'SELECT p.*, u.email AS user_email, u.password AS user_password
     FROM pendaftaran p
     JOIN users u ON u.id = p.user_id
     WHERE p.id = ? AND p.user_id = ? LIMIT 1'
);
$s->execute([$id, $userId]);
$p = $s->fetch();
if (!$p) redirect('/psb');

// Cek kolom opsional & apakah data inti (jenjang/gender) masih aman diubah
try {
    $kolomPendaftaran = array_column($pdo->query(
        "SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='pendaftaran'"
    )->fetchAll(PDO::FETCH_NUM), 0);
} catch (PDOException $e) {
    $kolomPendaftaran = [];
}

$cekPem = $pdo->prepare('SELECT COUNT(*) FROM pembiayaan WHERE pendaftaran_id = ?');
$cekPem->execute([$id]);
$hasPembiayaan = (int) $cekPem->fetchColumn() > 0;
$canChangeCore = ($p['status'] === 'pending') && !$hasPembiayaan;

$labelJenjang = ['smp' => 'SMP Muhammadiyah Unggulan Ashidiq', 'sma' => 'SMA Pondok Pesantren Ash-Shiddiq'];
$labelJenjangSingkat = ['smp' => 'SMP', 'sma' => 'SMA'];
$labelQuran = [
    'belum-bisa' => 'Belum Bisa Membaca', 'bisa-membaca' => 'Bisa Membaca',
    'tartil' => 'Tartil', 'hafal-juz-30' => 'Hafal Juz 30', 'hafal-lebih' => 'Hafal Lebih dari Juz 30',
];

$errors = [];
$isEdit = isset($_GET['edit']) && $_GET['edit'] === '1';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    validateCsrf();
    $isEdit = true;

    $waRaw = sanitizeString($_POST['whatsapp'] ?? '');
    $data = [
        'nama_lengkap'    => sanitizeString($_POST['nama_lengkap']    ?? ''),
        'tempat_lahir'    => sanitizeString($_POST['tempat_lahir']    ?? ''),
        'tanggal_lahir'   => sanitizeString($_POST['tanggal_lahir']   ?? ''),
        'jenis_kelamin'   => $canChangeCore ? sanitizeString($_POST['jenis_kelamin'] ?? '') : ($p['jenis_kelamin'] ?? ''),
        'agama'           => sanitizeString($_POST['agama']           ?? ''),
        'jenjang'         => $canChangeCore ? sanitizeString($_POST['jenjang'] ?? '') : ($p['jenjang'] ?? ''),
        'tinggi_badan'    => trim((string) ($_POST['tinggi_badan']   ?? '')),
        'berat_badan'     => trim((string) ($_POST['berat_badan']    ?? '')),
        'whatsapp'        => preg_replace('/[\s\-\.\(\)]+/', '', ltrim($waRaw, '+')),
        'nama_ayah'       => sanitizeString($_POST['nama_ayah']       ?? ''),
        'nama_ibu'        => sanitizeString($_POST['nama_ibu']        ?? ''),
        'hp_ortu'         => sanitizeString($_POST['hp_ortu']         ?? ''),
        'pekerjaan_ortu'  => sanitizeString($_POST['pekerjaan_ortu']  ?? ''),
        'alamat'          => sanitizeString($_POST['alamat']          ?? ''),
        'asal_sekolah'    => sanitizeString($_POST['asal_sekolah']    ?? ''),
        'tahun_lulus'     => trim((string) ($_POST['tahun_lulus']    ?? '')),
        'kemampuan_quran' => sanitizeString($_POST['kemampuan_quran'] ?? ''),
        'jumlah_hafalan'  => sanitizeString($_POST['jumlah_hafalan']  ?? ''),
        'motivasi'        => sanitizeString($_POST['motivasi']        ?? ''),
        'email'           => strtolower(trim((string) ($_POST['email'] ?? ''))),
    ];
    $passLama = (string) ($_POST['password_lama']       ?? '');
    $passBaru = (string) ($_POST['password_baru']       ?? '');
    $passKonf = (string) ($_POST['password_konfirmasi'] ?? '');

    $wajib = [
        'nama_lengkap'    => 'Nama lengkap',
        'tempat_lahir'    => 'Tempat lahir',
        'tanggal_lahir'   => 'Tanggal lahir',
        'jenis_kelamin'   => 'Jenis kelamin',
        'jenjang'         => 'Jenjang',
        'whatsapp'        => 'Nomor WhatsApp',
        'nama_ayah'       => 'Nama ayah',
        'nama_ibu'        => 'Nama ibu',
        'hp_ortu'         => 'No. HP orang tua',
        'alamat'          => 'Alamat',
        'asal_sekolah'    => 'Asal sekolah',
        'kemampuan_quran' => 'Kemampuan membaca Al-Qur\'an',
        'email'           => 'Email akun',
    ];
    foreach ($wajib as $field => $label) {
        if ($data[$field] === '') {
            $errors[$field] = "$label wajib diisi.";
        }
    }

    if ($data['tanggal_lahir'] !== '' && !date_create($data['tanggal_lahir'])) {
        $errors['tanggal_lahir'] = 'Format tanggal lahir tidak valid.';
    }
    if ($canChangeCore) {
        if ($data['jenis_kelamin'] !== '' && !in_array($data['jenis_kelamin'], ['L', 'P'], true)) {
            $errors['jenis_kelamin'] = 'Jenis kelamin tidak valid.';
        }
        if ($data['jenjang'] !== '' && !in_array($data['jenjang'], ['smp', 'sma'], true)) {
            $errors['jenjang'] = 'Jenjang tidak valid.';
        }
    }
    if ($data['agama'] !== '' && !in_array($data['agama'], agamaOptions(), true)) {
        $errors['agama'] = 'Pilihan agama tidak valid.';
    }
    if ($data['whatsapp'] !== '' && !preg_match('/^[0-9]{9,15}$/', $data['whatsapp'])) {
        $errors['whatsapp'] = 'Nomor WhatsApp tidak valid (9-15 digit angka).';
    }
    if ($data['kemampuan_quran'] !== '' && !array_key_exists($data['kemampuan_quran'], $labelQuran)) {
        $errors['kemampuan_quran'] = 'Kemampuan Al-Qur\'an tidak valid.';
    }

    $tinggiVal = null;
    if ($data['tinggi_badan'] !== '') {
        $tinggiVal = sanitizeFloat($data['tinggi_badan']);
        if ($tinggiVal < 50 || $tinggiVal > 250) {
            $errors['tinggi_badan'] = 'Tinggi badan harus antara 50–250 cm.';
        }
    }
    $beratVal = null;
    if ($data['berat_badan'] !== '') {
        $beratVal = sanitizeFloat($data['berat_badan']);
        if ($beratVal < 5 || $beratVal > 250) {
            $errors['berat_badan'] = 'Berat badan harus antara 5–250 kg.';
        }
    }
    $tahunLulusVal = null;
    if ($data['tahun_lulus'] !== '') {
        $tl = sanitizeInt($data['tahun_lulus']);
        if ($tl < 2015 || $tl > (int) date('Y') + 1) {
            $errors['tahun_lulus'] = 'Tahun lulus tidak valid.';
        } else {
            $tahunLulusVal = $tl;
        }
    } elseif (!empty($p['tahun_lulus'])) {
        $errors['tahun_lulus'] = 'Tahun lulus wajib dipilih.';
    }

    if ($data['email'] !== '' && !filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
        $errors['email'] = 'Format email tidak valid.';
    } elseif ($data['email'] !== '') {
        $dup = $pdo->prepare('SELECT id FROM users WHERE email = ? AND id <> ? LIMIT 1');
        $dup->execute([$data['email'], $userId]);
        if ($dup->fetch()) {
            $errors['email'] = 'Email sudah digunakan akun lain.';
        }
    }

    $ubahPassword = ($passBaru !== '' || $passKonf !== '');
    if ($ubahPassword) {
        if ($passLama === '' || !password_verify($passLama, (string) $p['user_password'])) {
            $errors['password_lama'] = 'Password saat ini tidak sesuai.';
        }
        if (strlen($passBaru) < 8) {
            $errors['password_baru'] = 'Password baru minimal 8 karakter.';
        }
        if ($passBaru !== $passKonf) {
            $errors['password_konfirmasi'] = 'Konfirmasi password baru tidak cocok.';
        }
    }

    if (empty($errors)) {
        try {
            $pdo->beginTransaction();

            $userSql = 'UPDATE users SET name = ?, email = ?';
            $userParams = [$data['nama_lengkap'], $data['email']];
            if ($ubahPassword) {
                $userSql .= ', password = ?';
                $userParams[] = password_hash($passBaru, PASSWORD_BCRYPT);
            }
            $userSql .= ' WHERE id = ?';
            $userParams[] = $userId;
            $pdo->prepare($userSql)->execute($userParams);

            $setCols = [
                'nama_lengkap = ?', 'tempat_lahir = ?', 'tanggal_lahir = ?',
                'whatsapp = ?', 'nama_ayah = ?', 'nama_ibu = ?',
                'hp_ortu = ?', 'pekerjaan_ortu = ?', 'alamat = ?',
                'asal_sekolah = ?', 'tahun_lulus = ?', 'kemampuan_quran = ?',
                'jumlah_hafalan = ?', 'motivasi = ?',
            ];
            $setParams = [
                $data['nama_lengkap'], $data['tempat_lahir'], $data['tanggal_lahir'],
                $data['whatsapp'], $data['nama_ayah'], $data['nama_ibu'],
                $data['hp_ortu'], $data['pekerjaan_ortu'] ?: null, $data['alamat'],
                $data['asal_sekolah'], $tahunLulusVal, $data['kemampuan_quran'],
                $data['jumlah_hafalan'] ?: null, $data['motivasi'] ?: null,
            ];
            if ($canChangeCore) {
                $setCols[] = 'jenis_kelamin = ?';
                $setParams[] = $data['jenis_kelamin'];
                $setCols[] = 'jenjang = ?';
                $setParams[] = $data['jenjang'];
            }
            if (in_array('agama', $kolomPendaftaran, true)) {
                $setCols[] = 'agama = ?';
                $setParams[] = $data['agama'] ?: null;
            }
            if (in_array('tinggi_badan', $kolomPendaftaran, true)) {
                $setCols[] = 'tinggi_badan = ?';
                $setParams[] = $tinggiVal;
            }
            if (in_array('berat_badan', $kolomPendaftaran, true)) {
                $setCols[] = 'berat_badan = ?';
                $setParams[] = $beratVal;
            }
            $setParams[] = $id;
            $pdo->prepare('UPDATE pendaftaran SET ' . implode(', ', $setCols) . ' WHERE id = ?')->execute($setParams);

            $pdo->commit();

            $_SESSION['user_name'] = $data['nama_lengkap'];
            $_SESSION['user_email'] = $data['email'];
            setFlash('success', 'Profil berhasil diperbarui.');
            redirect('/profil-santri');
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            error_log('Update profil santri error: ' . $e->getMessage());
            $errors['_global'] = 'Gagal menyimpan perubahan. Silakan coba lagi.';
        }
    }
}

$sf = $pdo->prepare("SELECT nama_file FROM berkas_santri WHERE pendaftaran_id=? AND jenis='foto' ORDER BY id DESC LIMIT 1");
$sf->execute([$id]);
$fotoAda = (bool) $sf->fetchColumn();

// Semua berkas milik santri untuk daftar + link Lihat
$bs = $pdo->prepare('SELECT jenis, created_at FROM berkas_santri WHERE pendaftaran_id=? ORDER BY id');
$bs->execute([$id]);
$semuaBerkas = $bs->fetchAll();

// Nilai form saat edit (POST gagal pakai input user, selain itu dari DB)
$form = $_SERVER['REQUEST_METHOD'] === 'POST' ? $data : [
    'nama_lengkap'    => $p['nama_lengkap'] ?? '',
    'tempat_lahir'    => $p['tempat_lahir'] ?? '',
    'tanggal_lahir'   => $p['tanggal_lahir'] ?? '',
    'jenis_kelamin'   => $p['jenis_kelamin'] ?? '',
    'agama'           => $p['agama'] ?? '',
    'jenjang'         => $p['jenjang'] ?? '',
    'tinggi_badan'    => $p['tinggi_badan'] ?? '',
    'berat_badan'     => $p['berat_badan'] ?? '',
    'whatsapp'        => $p['whatsapp'] ?? '',
    'nama_ayah'       => $p['nama_ayah'] ?? '',
    'nama_ibu'        => $p['nama_ibu'] ?? '',
    'hp_ortu'         => $p['hp_ortu'] ?? '',
    'pekerjaan_ortu'  => $p['pekerjaan_ortu'] ?? '',
    'alamat'          => $p['alamat'] ?? '',
    'asal_sekolah'    => $p['asal_sekolah'] ?? '',
    'tahun_lulus'     => $p['tahun_lulus'] ?? '',
    'kemampuan_quran' => $p['kemampuan_quran'] ?? '',
    'jumlah_hafalan'  => $p['jumlah_hafalan'] ?? '',
    'motivasi'        => $p['motivasi'] ?? '',
    'email'           => $p['user_email'] ?? '',
];

$activePage = 'profil-santri';
$pageTitle  = ($isEdit ? 'Edit Profil Santri | ' : 'Profil Santri | ') . APP_NAME;
$pageDescription = 'Profil dan data pendaftaran santri.';
$pageCanonical = BASE_URL . '/profil-santri';

$row = fn(string $label, string $value): string =>
    '<div class="profile-row"><span class="profile-label">' . e($label) . '</span><span class="profile-value">' . $value . '</span></div>';
?>
<main class="page-section"><div class="container narrow-container">
    <div class="portal-head">
        <div style="display:flex;align-items:center;gap:16px;">
            <?php if ($fotoAda): ?>
            <img src="<?= BASE_URL ?>/api/foto-santri.php" alt="Foto santri" style="width:64px;height:64px;object-fit:cover;border-radius:50%;border:3px solid #fff;box-shadow:var(--shadow-sm);">
            <?php endif; ?>
            <div>
                <small><?= e($p['nomor_induk'] ?: $p['nomor_daftar']) ?></small>
                <h1 style="margin:2px 0 4px;"><?= e($p['nama_lengkap']) ?></h1>
                <p style="margin:0;">Status: <strong><?= e(statusLabel($p['status'])) ?></strong></p>
            </div>
        </div>
        <div class="portal-head-actions">
            <?php if ($isEdit): ?>
                <a class="btn-outline btn-head-light" href="<?= BASE_URL ?>/profil-santri">Batal</a>
            <?php else: ?>
                <a class="btn-outline btn-head-light" href="<?= BASE_URL ?>/profil-santri?edit=1">Edit Profil</a>
                <a class="btn-outline btn-head-light" href="<?= BASE_URL ?>/portal-santri"><?= $p['status'] === 'pending' ? 'Lanjut Pendaftaran' : 'Portal Santri' ?></a>
            <?php endif; ?>
        </div>
    </div>

<?php if ($isEdit): ?>
    <?php if (!empty($errors)): ?>
    <div class="alert alert-error" id="profilErrorBox" role="alert" style="margin-bottom:20px;padding:12px 16px;background:#fef2f2;color:#b91c1c;border:1px solid #fecaca;border-radius:8px;font-size:13px;line-height:1.6;">
        <strong>Perubahan belum tersimpan:</strong>
        <ul style="margin:6px 0 0 18px;padding:0;">
            <?php foreach ($errors as $msg): ?>
                <li><?= e($msg) ?></li>
            <?php endforeach; ?>
        </ul>
    </div>
    <?php endif; ?>

    <form method="post" action="<?= BASE_URL ?>/profil-santri?edit=1" novalidate>
        <input type="hidden" name="csrf_token" value="<?= generateCsrfToken() ?>">

        <div class="portal-card profile-card">
            <h2>Data Calon Santri</h2>
            <div class="form-group">
                <label for="nama_lengkap">Nama Lengkap <span class="req">*</span></label>
                <input type="text" id="nama_lengkap" name="nama_lengkap" class="form-control<?= isset($errors['nama_lengkap']) ? ' is-error' : '' ?>" value="<?= e((string) $form['nama_lengkap']) ?>" required>
                <?php if (isset($errors['nama_lengkap'])): ?><p class="field-error"><?= e($errors['nama_lengkap']) ?></p><?php endif; ?>
            </div>
            <div class="form-grid">
                <div class="form-group">
                    <label for="tempat_lahir">Tempat Lahir <span class="req">*</span></label>
                    <input type="text" id="tempat_lahir" name="tempat_lahir" class="form-control<?= isset($errors['tempat_lahir']) ? ' is-error' : '' ?>" value="<?= e((string) $form['tempat_lahir']) ?>" required>
                    <?php if (isset($errors['tempat_lahir'])): ?><p class="field-error"><?= e($errors['tempat_lahir']) ?></p><?php endif; ?>
                </div>
                <div class="form-group">
                    <label for="tanggal_lahir">Tanggal Lahir <span class="req">*</span></label>
                    <input type="date" id="tanggal_lahir" name="tanggal_lahir" class="form-control<?= isset($errors['tanggal_lahir']) ? ' is-error' : '' ?>" value="<?= e((string) $form['tanggal_lahir']) ?>" required>
                    <?php if (isset($errors['tanggal_lahir'])): ?><p class="field-error"><?= e($errors['tanggal_lahir']) ?></p><?php endif; ?>
                </div>
            </div>
            <div class="form-grid">
                <div class="form-group">
                    <label for="jenis_kelamin">Jenis Kelamin <span class="req">*</span></label>
                    <?php if ($canChangeCore): ?>
                        <select id="jenis_kelamin" name="jenis_kelamin" class="form-control<?= isset($errors['jenis_kelamin']) ? ' is-error' : '' ?>" required>
                            <option value="L" <?= $form['jenis_kelamin'] === 'L' ? 'selected' : '' ?>>Laki-laki</option>
                            <option value="P" <?= $form['jenis_kelamin'] === 'P' ? 'selected' : '' ?>>Perempuan</option>
                        </select>
                        <?php if (isset($errors['jenis_kelamin'])): ?><p class="field-error"><?= e($errors['jenis_kelamin']) ?></p><?php endif; ?>
                    <?php else: ?>
                        <input type="text" class="form-control" value="<?= e($form['jenis_kelamin'] === 'P' ? 'Perempuan' : 'Laki-laki') ?>" disabled>
                        <p class="form-hint">Hubungi panitia jika perlu mengubah jenis kelamin.</p>
                    <?php endif; ?>
                </div>
                <div class="form-group">
                    <label for="jenjang">Jenjang <span class="req">*</span></label>
                    <?php if ($canChangeCore): ?>
                        <select id="jenjang" name="jenjang" class="form-control<?= isset($errors['jenjang']) ? ' is-error' : '' ?>" required>
                            <?php foreach ($labelJenjang as $val => $lbl): ?>
                                <option value="<?= e($val) ?>" <?= $form['jenjang'] === $val ? 'selected' : '' ?>><?= e($lbl) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <?php if (isset($errors['jenjang'])): ?><p class="field-error"><?= e($errors['jenjang']) ?></p><?php endif; ?>
                    <?php else: ?>
                        <input type="text" class="form-control" value="<?= e($labelJenjangSingkat[$form['jenjang']] ?? $form['jenjang']) ?>" disabled>
                        <p class="form-hint">Hubungi panitia jika perlu mengubah jenjang.</p>
                    <?php endif; ?>
                </div>
            </div>
            <div class="form-grid">
                <?php if (in_array('agama', $kolomPendaftaran, true)): ?>
                <div class="form-group">
                    <label for="agama">Agama</label>
                    <select id="agama" name="agama" class="form-control<?= isset($errors['agama']) ? ' is-error' : '' ?>">
                        <option value="">-- Pilih Agama --</option>
                        <?php foreach (agamaOptions() as $ag): ?>
                            <option value="<?= e($ag) ?>" <?= $form['agama'] === $ag ? 'selected' : '' ?>><?= e($ag) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <?php if (isset($errors['agama'])): ?><p class="field-error"><?= e($errors['agama']) ?></p><?php endif; ?>
                </div>
                <?php endif; ?>
                <div class="form-group">
                    <label for="whatsapp">No. WhatsApp <span class="req">*</span></label>
                    <input type="tel" id="whatsapp" name="whatsapp" class="form-control<?= isset($errors['whatsapp']) ? ' is-error' : '' ?>" value="<?= e((string) $form['whatsapp']) ?>" required>
                    <?php if (isset($errors['whatsapp'])): ?><p class="field-error"><?= e($errors['whatsapp']) ?></p><?php endif; ?>
                </div>
            </div>
            <?php if (in_array('tinggi_badan', $kolomPendaftaran, true) || in_array('berat_badan', $kolomPendaftaran, true)): ?>
            <div class="form-grid">
                <div class="form-group">
                    <label for="tinggi_badan">Tinggi Badan (cm)</label>
                    <input type="number" id="tinggi_badan" name="tinggi_badan" class="form-control<?= isset($errors['tinggi_badan']) ? ' is-error' : '' ?>" min="50" max="250" step="0.1" value="<?= e((string) $form['tinggi_badan']) ?>">
                    <?php if (isset($errors['tinggi_badan'])): ?><p class="field-error"><?= e($errors['tinggi_badan']) ?></p><?php endif; ?>
                </div>
                <div class="form-group">
                    <label for="berat_badan">Berat Badan (kg)</label>
                    <input type="number" id="berat_badan" name="berat_badan" class="form-control<?= isset($errors['berat_badan']) ? ' is-error' : '' ?>" min="5" max="250" step="0.1" value="<?= e((string) $form['berat_badan']) ?>">
                    <?php if (isset($errors['berat_badan'])): ?><p class="field-error"><?= e($errors['berat_badan']) ?></p><?php endif; ?>
                </div>
            </div>
            <?php endif; ?>
        </div>

        <div class="portal-card profile-card">
            <h2>Data Orang Tua / Wali</h2>
            <div class="form-grid">
                <div class="form-group">
                    <label for="nama_ayah">Nama Ayah <span class="req">*</span></label>
                    <input type="text" id="nama_ayah" name="nama_ayah" class="form-control<?= isset($errors['nama_ayah']) ? ' is-error' : '' ?>" value="<?= e((string) $form['nama_ayah']) ?>" required>
                    <?php if (isset($errors['nama_ayah'])): ?><p class="field-error"><?= e($errors['nama_ayah']) ?></p><?php endif; ?>
                </div>
                <div class="form-group">
                    <label for="nama_ibu">Nama Ibu <span class="req">*</span></label>
                    <input type="text" id="nama_ibu" name="nama_ibu" class="form-control<?= isset($errors['nama_ibu']) ? ' is-error' : '' ?>" value="<?= e((string) $form['nama_ibu']) ?>" required>
                    <?php if (isset($errors['nama_ibu'])): ?><p class="field-error"><?= e($errors['nama_ibu']) ?></p><?php endif; ?>
                </div>
            </div>
            <div class="form-grid">
                <div class="form-group">
                    <label for="hp_ortu">No. HP Orang Tua <span class="req">*</span></label>
                    <input type="tel" id="hp_ortu" name="hp_ortu" class="form-control<?= isset($errors['hp_ortu']) ? ' is-error' : '' ?>" value="<?= e((string) $form['hp_ortu']) ?>" required>
                    <?php if (isset($errors['hp_ortu'])): ?><p class="field-error"><?= e($errors['hp_ortu']) ?></p><?php endif; ?>
                </div>
                <div class="form-group">
                    <label for="pekerjaan_ortu">Pekerjaan Orang Tua</label>
                    <input type="text" id="pekerjaan_ortu" name="pekerjaan_ortu" class="form-control" value="<?= e((string) $form['pekerjaan_ortu']) ?>">
                </div>
            </div>
            <div class="form-group">
                <label for="alamat">Alamat Lengkap <span class="req">*</span></label>
                <textarea id="alamat" name="alamat" rows="3" class="form-control<?= isset($errors['alamat']) ? ' is-error' : '' ?>" required><?= e((string) $form['alamat']) ?></textarea>
                <?php if (isset($errors['alamat'])): ?><p class="field-error"><?= e($errors['alamat']) ?></p><?php endif; ?>
            </div>
        </div>

        <div class="portal-card profile-card">
            <h2>Data Akademik</h2>
            <div class="form-grid">
                <div class="form-group">
                    <label for="asal_sekolah">Asal Sekolah <span class="req">*</span></label>
                    <input type="text" id="asal_sekolah" name="asal_sekolah" class="form-control<?= isset($errors['asal_sekolah']) ? ' is-error' : '' ?>" value="<?= e((string) $form['asal_sekolah']) ?>" required>
                    <?php if (isset($errors['asal_sekolah'])): ?><p class="field-error"><?= e($errors['asal_sekolah']) ?></p><?php endif; ?>
                </div>
                <div class="form-group">
                    <label for="tahun_lulus">Tahun Lulus</label>
                    <select id="tahun_lulus" name="tahun_lulus" class="form-control<?= isset($errors['tahun_lulus']) ? ' is-error' : '' ?>">
                        <option value="">-- Belum diisi --</option>
                        <?php for ($y = (int) date('Y') + 1; $y >= 2015; $y--): ?>
                            <option value="<?= $y ?>" <?= (int) $form['tahun_lulus'] === $y ? 'selected' : '' ?>><?= $y ?></option>
                        <?php endfor; ?>
                    </select>
                    <?php if (isset($errors['tahun_lulus'])): ?><p class="field-error"><?= e($errors['tahun_lulus']) ?></p><?php endif; ?>
                </div>
            </div>
            <div class="form-grid">
                <div class="form-group">
                    <label for="kemampuan_quran">Kemampuan Membaca Al-Qur'an <span class="req">*</span></label>
                    <select id="kemampuan_quran" name="kemampuan_quran" class="form-control<?= isset($errors['kemampuan_quran']) ? ' is-error' : '' ?>" required>
                        <?php foreach ($labelQuran as $val => $lbl): ?>
                            <option value="<?= e($val) ?>" <?= $form['kemampuan_quran'] === $val ? 'selected' : '' ?>><?= e($lbl) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <?php if (isset($errors['kemampuan_quran'])): ?><p class="field-error"><?= e($errors['kemampuan_quran']) ?></p><?php endif; ?>
                </div>
                <div class="form-group">
                    <label for="jumlah_hafalan">Jumlah Hafalan</label>
                    <input type="text" id="jumlah_hafalan" name="jumlah_hafalan" class="form-control" value="<?= e((string) $form['jumlah_hafalan']) ?>" placeholder="Contoh: 2 Juz">
                </div>
            </div>
            <div class="form-group">
                <label for="motivasi">Motivasi</label>
                <textarea id="motivasi" name="motivasi" rows="3" class="form-control"><?= e((string) $form['motivasi']) ?></textarea>
            </div>
        </div>

        <div class="portal-card profile-card">
            <h2>Akun &amp; Keamanan</h2>
            <div class="form-group">
                <label for="email">Email Akun <span class="req">*</span></label>
                <input type="email" id="email" name="email" class="form-control<?= isset($errors['email']) ? ' is-error' : '' ?>" value="<?= e((string) $form['email']) ?>" required>
                <?php if (isset($errors['email'])): ?><p class="field-error"><?= e($errors['email']) ?></p><?php endif; ?>
            </div>
            <hr style="margin:18px 0;border:none;border-top:1px solid var(--cream-dark);">
            <p class="form-hint" style="margin-bottom:14px;">Ganti Password (opsional — kosongkan jika tidak ingin mengubah password)</p>
            <div class="form-group">
                <label for="password_lama">Password Saat Ini</label>
                <input type="password" id="password_lama" name="password_lama" class="form-control<?= isset($errors['password_lama']) ? ' is-error' : '' ?>" autocomplete="current-password">
                <?php if (isset($errors['password_lama'])): ?><p class="field-error"><?= e($errors['password_lama']) ?></p><?php endif; ?>
            </div>
            <div class="form-grid">
                <div class="form-group">
                    <label for="password_baru">Password Baru</label>
                    <input type="password" id="password_baru" name="password_baru" class="form-control<?= isset($errors['password_baru']) ? ' is-error' : '' ?>" autocomplete="new-password" placeholder="Minimal 8 karakter">
                    <?php if (isset($errors['password_baru'])): ?><p class="field-error"><?= e($errors['password_baru']) ?></p><?php endif; ?>
                </div>
                <div class="form-group">
                    <label for="password_konfirmasi">Konfirmasi Password Baru</label>
                    <input type="password" id="password_konfirmasi" name="password_konfirmasi" class="form-control<?= isset($errors['password_konfirmasi']) ? ' is-error' : '' ?>" autocomplete="new-password">
                    <?php if (isset($errors['password_konfirmasi'])): ?><p class="field-error"><?= e($errors['password_konfirmasi']) ?></p><?php endif; ?>
                </div>
            </div>
        </div>

        <div class="profile-form-actions">
            <a class="btn-outline" href="<?= BASE_URL ?>/profil-santri">Batal</a>
            <button type="submit" class="btn-primary">Simpan Perubahan</button>
        </div>
    </form>
<?php else: ?>

    <div class="portal-card profile-card">
        <h2>Data Calon Santri</h2>
        <?= $row('Nama Lengkap', e($p['nama_lengkap'])) ?>
        <?= $row('Tempat, Tanggal Lahir', e($p['tempat_lahir'] . ', ' . date('d/m/Y', strtotime($p['tanggal_lahir'])))) ?>
        <?= $row('Jenis Kelamin', e($p['jenis_kelamin'] === 'P' ? 'Perempuan' : 'Laki-laki')) ?>
        <?= $row('Agama', e($p['agama'] ?? '-')) ?>
        <?= $row('Jenjang', e($labelJenjangSingkat[$p['jenjang']] ?? $p['jenjang'])) ?>
        <?= $row('Kelas', e($p['kelas'] ?? '-')) ?>
        <?= $row('Tinggi / Berat Badan', e((!empty($p['tinggi_badan']) ? (float) $p['tinggi_badan'] . ' cm' : '-') . ' / ' . (!empty($p['berat_badan']) ? (float) $p['berat_badan'] . ' kg' : '-'))) ?>
        <?= $row('No. WhatsApp', e($p['whatsapp'])) ?>
        <?= $row('Email Akun', e($p['user_email'] ?? '-')) ?>
    </div>

    <div class="portal-card profile-card">
        <h2>Data Orang Tua / Wali</h2>
        <?= $row('Nama Ayah', e($p['nama_ayah'])) ?>
        <?= $row('Nama Ibu', e($p['nama_ibu'])) ?>
        <?= $row('No. HP Orang Tua', e($p['hp_ortu'])) ?>
        <?= $row('Pekerjaan Orang Tua', e($p['pekerjaan_ortu'] ?: '-')) ?>
        <?= $row('Alamat', e($p['alamat'])) ?>
    </div>

    <div class="portal-card profile-card">
        <h2>Data Akademik</h2>
        <?= $row('Asal Sekolah', e($p['asal_sekolah'])) ?>
        <?= $row('Tahun Lulus', e($p['tahun_lulus'] ?: '-')) ?>
        <?= $row('Kemampuan Membaca Al-Qur\'an', e($labelQuran[$p['kemampuan_quran']] ?? $p['kemampuan_quran'])) ?>
        <?= $row('Jumlah Hafalan', e($p['jumlah_hafalan'] ?: '-')) ?>
        <?= $row('Motivasi', e($p['motivasi'] ?: '-')) ?>
    </div>

    <?php if (!empty($p['kesanggupan_at'])): ?>
    <div class="portal-card profile-card">
        <h2>Kesanggupan</h2>
        <?= $row('Kesanggupan biaya', e($p['kesanggupan_setuju'] ? 'Sudah ditandatangani' : 'Belum')) ?>
        <?= $row('Ditandatangani pada', e(date('d/m/Y H:i', strtotime($p['kesanggupan_at'])))) ?>
    </div>
    <?php endif; ?>

    <div class="portal-card profile-card">
        <h2>Berkas Terupload (<?= count($semuaBerkas) ?>)</h2>
        <?php if (empty($semuaBerkas)): ?>
            <p style="color:#999;font-size:14px;">Belum ada berkas. <a href="<?= BASE_URL ?>/portal-santri">Lengkapi di portal</a>.</p>
        <?php else: ?>
            <?php foreach ($semuaBerkas as $b): ?>
                <div class="profile-row">
                    <span class="profile-label"><?= e(ucwords(str_replace('-', ' ', $b['jenis']))) ?></span>
                    <span class="profile-value">✓ <?= e(date('d/m/Y', strtotime($b['created_at']))) ?>
                        — <a href="<?= BASE_URL ?>/berkas-santri?jenis=<?= e($b['jenis']) ?>" target="_blank" rel="noopener">Lihat</a>
                    </span>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

    <?php if (in_array($p['status'], ['diterima', 'daftar-ulang'], true)): ?>
    <div class="portal-card profile-card">
        <h2>Dokumen Kelulusan</h2>
        <?php foreach (dokumenKelulusanList($p['jenjang'] ?? 'smp') as $tipe => $label): ?>
            <div class="profile-row">
                <span class="profile-label"><?= e($label) ?></span>
                <span class="profile-value"><a href="<?= BASE_URL ?>/dokumen-santri?tipe=<?= e($tipe) ?>" target="_blank" rel="noopener">🖨 Cetak</a></span>
            </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>
<?php endif; ?>
</div></main>

<style>
.portal-head-actions { display: flex; gap: 10px; flex-wrap: wrap; }
.btn-head-light { color: #fff; border-color: rgba(255,255,255,0.55); padding: 10px 20px; }
.btn-head-light:hover { background: #fff; color: var(--green-deep); border-color: #fff; }
.profile-card { margin-bottom: 22px; }
.profile-card .req { color: #dc2626; }
.profile-card .is-error { border-color: #dc2626; }
.profile-card .field-error { font-size: 12px; color: #dc2626; margin: 6px 0 0; }
.profile-form-actions { display: flex; gap: 12px; justify-content: flex-end; flex-wrap: wrap; margin-bottom: 24px; }
.profile-row {
    display: flex; gap: 16px; padding: 8px 0;
    border-bottom: 1px solid var(--cream-dark); font-size: 14px;
}
.profile-row:last-child { border-bottom: none; }
.profile-label { flex: 0 0 220px; color: var(--text-light); font-weight: 600; }
.profile-value { color: var(--text-dark); flex: 1; }
@media (max-width: 600px) {
    .profile-row { flex-direction: column; gap: 2px; }
    .profile-label { flex: none; }
}
</style>
