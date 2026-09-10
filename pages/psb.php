<?php
// ── Ambil pengaturan PSB dari database ───────────────────────
$pdo = getDB();
$stmtPengat = $pdo->query(
    "SELECT key_name, value FROM pengaturan
     WHERE key_name IN ('psb_status','psb_tahun','psb_batas_daftar','kontak_whatsapp','rekening_pembayaran')"
);
$pengaturan = [];
foreach ($stmtPengat->fetchAll() as $row) {
    $pengaturan[$row['key_name']] = $row['value'];
}

$psbStatus = $pengaturan['psb_status']      ?? 'tutup';
$psbTahun  = $pengaturan['psb_tahun']       ?? date('Y') . '/' . (date('Y') + 1);
$psbBatas  = $pengaturan['psb_batas_daftar'] ?? null;
$kontakWA  = $pengaturan['kontak_whatsapp']  ?? '6281234567890';

$psbBuka   = ($psbStatus === 'buka') && (!$psbBatas || date('Y-m-d') <= $psbBatas);

// Auto-detect gelombang aktif (tanggal hari ini)
$gelombangAktif = $psbBuka ? autoDetectGelombang($pdo) : null;

// ── Proses form POST (FASE 0: Bikin Akun) ───────────────────
$errors = [];
$successData = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $psbBuka) {
    validateCsrf();

    if (!$gelombangAktif) {
        $errors['_global'] = 'Pendaftaran tidak sedang dibuka untuk gelombang manapun saat ini.';
    } else {
        $data = [
            'nama_lengkap'    => sanitizeString($_POST['nama_lengkap']    ?? ''),
            'tempat_lahir'    => sanitizeString($_POST['tempat_lahir']    ?? ''),
            'tanggal_lahir'   => sanitizeString($_POST['tanggal_lahir']   ?? ''),
            'jenis_kelamin'   => sanitizeString($_POST['jenis_kelamin']   ?? ''),
            'jenjang'         => sanitizeString($_POST['jenjang']         ?? ''),
            'whatsapp'        => sanitizeString($_POST['whatsapp']        ?? ''),
            'email'           => strtolower(trim($_POST['email']         ?? '')),
            'password'        => $_POST['password']         ?? '',
            'nama_ayah'       => sanitizeString($_POST['nama_ayah']       ?? ''),
            'nama_ibu'        => sanitizeString($_POST['nama_ibu']        ?? ''),
            'hp_ortu'         => sanitizeString($_POST['hp_ortu']         ?? ''),
            'pekerjaan_ortu'  => sanitizeString($_POST['pekerjaan_ortu']  ?? ''),
            'alamat'          => sanitizeString($_POST['alamat']          ?? ''),
            'asal_sekolah'    => sanitizeString($_POST['asal_sekolah']    ?? ''),
            'kemampuan_quran' => sanitizeString($_POST['kemampuan_quran'] ?? ''),
        ];

        // ── Validasi wajib ──
        $validasiWajib = [
            'nama_lengkap'   => 'Nama lengkap',
            'tempat_lahir'   => 'Tempat lahir',
            'tanggal_lahir'  => 'Tanggal lahir',
            'jenis_kelamin'  => 'Jenis kelamin',
            'jenjang'        => 'Jenjang',
            'whatsapp'       => 'Nomor WhatsApp',
            'email'          => 'Email',
            'password'       => 'Password',
            'nama_ayah'      => 'Nama ayah',
            'nama_ibu'       => 'Nama ibu',
            'hp_ortu'        => 'No. HP orang tua',
            'alamat'         => 'Alamat',
            'asal_sekolah'   => 'Asal sekolah',
            'kemampuan_quran'=> 'Kemampuan membaca Al-Qur\'an',
        ];
        foreach ($validasiWajib as $field => $label) {
            if (empty($data[$field])) {
                $errors[$field] = "$label wajib diisi.";
            }
        }

        // Validasi email
        if (!empty($data['email']) && !filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
            $errors['email'] = 'Format email tidak valid.';
        }

        // Validasi password (min 8 char sesuai CLAUDE.md)
        if (strlen($data['password']) < 8) {
            $errors['password'] = 'Password minimal 8 karakter.';
        }

        // Validasi enum
        $validJK      = ['L', 'P'];
        $validJenjang = ['smp', 'sma'];
        $validQuran   = ['belum-bisa', 'bisa-membaca', 'tartil', 'hafal-juz-30', 'hafal-lebih'];

        if ($data['jenis_kelamin'] && !in_array($data['jenis_kelamin'], $validJK, true)) {
            $errors['jenis_kelamin'] = 'Jenis kelamin tidak valid.';
        }
        if ($data['jenjang'] && !in_array($data['jenjang'], $validJenjang, true)) {
            $errors['jenjang'] = 'Jenjang tidak valid.';
        }
        if ($data['kemampuan_quran'] && !in_array($data['kemampuan_quran'], $validQuran, true)) {
            $errors['kemampuan_quran'] = 'Kemampuan Al-Qur\'an tidak valid.';
        }

        // Validasi tanggal
        if (!empty($data['tanggal_lahir'])) {
            $tgl = date_create($data['tanggal_lahir']);
            if (!$tgl) {
                $errors['tanggal_lahir'] = 'Format tanggal tidak valid.';
            }
        }

        // Validasi WhatsApp angka saja
        if (!empty($data['whatsapp']) && !preg_match('/^[0-9]{9,15}$/', $data['whatsapp'])) {
            $errors['whatsapp'] = 'Nomor WhatsApp tidak valid (9-15 digit angka).';
        }

        // Cek duplikat email
        if (empty($errors['email']) && !empty($data['email'])) {
            $stmtDup = $pdo->prepare("SELECT id FROM users WHERE email = ? LIMIT 1");
            $stmtDup->execute([$data['email']]);
            if ($stmtDup->fetch()) {
                $errors['email'] = 'Email sudah terdaftar. Gunakan email lain atau login ke portal.';
            }
        }

        // ── INSERT jika tidak ada error ──
        if (empty($errors)) {
            $nomorDaftar = generateNomorDaftar($pdo);
            $passwordHash = password_hash($data['password'], PASSWORD_BCRYPT);

            $pdo->beginTransaction();
            try {
                // 1. INSERT users
                $stmtUser = $pdo->prepare(
                    "INSERT INTO users (name, email, password, role, is_active)
                     VALUES (?,?,?,'calon-santri',1)"
                );
                $stmtUser->execute([
                    $data['nama_lengkap'],
                    $data['email'],
                    $passwordHash,
                ]);
                $userId = (int) $pdo->lastInsertId();

                // 2. INSERT pendaftaran
                // tahun_lulus sengaja NULL — kolom ini diisi di step
                // "Akademik" portal, bukan saat membuat akun.
                $stmtPendaftaran = $pdo->prepare(
                    "INSERT INTO pendaftaran
                        (user_id, nomor_daftar, nama_lengkap, tempat_lahir, tanggal_lahir,
                         jenis_kelamin, jenjang, gelombang_id, whatsapp,
                         nama_ayah, nama_ibu, hp_ortu, pekerjaan_ortu, alamat,
                         asal_sekolah, kemampuan_quran,
                         jalur, jalur_status, status)
                     VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?, 'reguler', 'none', 'pending')"
                );
                $stmtPendaftaran->execute([
                    $userId,
                    $nomorDaftar,
                    $data['nama_lengkap'],
                    $data['tempat_lahir'],
                    $data['tanggal_lahir'],
                    $data['jenis_kelamin'],
                    $data['jenjang'],
                    (int) $gelombangAktif['id'],
                    $data['whatsapp'],
                    $data['nama_ayah'],
                    $data['nama_ibu'],
                    $data['hp_ortu'],
                    $data['pekerjaan_ortu'] ?: null,
                    $data['alamat'],
                    $data['asal_sekolah'],
                    $data['kemampuan_quran'],
                ]);
                $pdo->commit();

                kirimTelegram(
                    "Pendaftar baru: {$data['nama_lengkap']} ({$nomorDaftar})\n"
                    . 'Jenjang: ' . strtoupper($data['jenjang']) . ' | Gelombang: ' . $gelombangAktif['label'] . "\n"
                    . "WA: {$data['whatsapp']} | Cek: " . BASE_URL . '/admin/verifikasi-berkas',
                    $pdo
                );

                // Auto-login calon
                session_regenerate_id(true);
                $_SESSION['user_id']    = $userId;
                $sid = $pdo->prepare('SELECT id FROM pendaftaran WHERE user_id = ? ORDER BY id DESC LIMIT 1');
                $sid->execute([$userId]);
                $_SESSION['santri_id']  = (int) ($sid->fetchColumn() ?: 0);
                $_SESSION['user_name']  = $data['nama_lengkap'];
                $_SESSION['user_email'] = $data['email'];
                $_SESSION['user_role']  = 'calon-santri';
                $_SESSION['login_at']   = time();

                $_SESSION['psb_success'] = [
                    'nomor'    => $nomorDaftar,
                    'nama'     => $data['nama_lengkap'],
                    'email'    => $data['email'],
                    'gelombang'=> $gelombangAktif['label'],
                ];
                redirect('/psb?daftar=sukses');
            } catch (Throwable $e) {
                if ($pdo->inTransaction()) $pdo->rollBack();
                error_log('PSB insert error: ' . $e->getMessage());
                $errors['_global'] = 'Terjadi kesalahan sistem. Silakan coba lagi.';
            }
        }
    }
}

// Ambil data sukses dari session (setelah redirect)
if (!empty($_GET['daftar']) && $_GET['daftar'] === 'sukses' && !empty($_SESSION['psb_success'])) {
    $successData = $_SESSION['psb_success'];
    unset($_SESSION['psb_success']);
}

// ── Meta halaman ─────────────────────────────────────────────
$activePage      = 'psb';
$pageTitle       = 'Pendaftaran Santri Baru ' . $psbTahun . ' | ' . APP_NAME;
$pageDescription = 'Daftarkan putra-putri Anda ke Pondok Pesantren Ash-Shiddiq. PSB Tahun Ajaran ' . $psbTahun . ' telah dibuka. Buat akun pendaftaran sekarang.';
$pageCanonical   = BASE_URL . '/psb';
$bodyClass       = 'psb-page';

$extraHead = '<link rel="stylesheet" href="' . BASE_URL . '/assets/css/psb.css?v=' . ASSET_VERSION . '">';

$labelJenjang = [
    'smp'              => 'SMP Muhammadiyah Unggulan Ashidiq',
    'sma'              => 'SMA Pondok Pesantren Ash-Shiddiq',
];
$labelQuran = [
    'belum-bisa'   => 'Belum Bisa Membaca',
    'bisa-membaca' => 'Bisa Membaca',
    'tartil'       => 'Tartil',
    'hafal-juz-30' => 'Hafal Juz 30',
    'hafal-lebih'  => 'Hafal Lebih dari Juz 30',
];
$labelGelombang = $gelombangAktif['label'] ?? '—';
$errorLabels = [
    'nama_lengkap'   => 'Nama lengkap',
    'tempat_lahir'   => 'Tempat lahir',
    'tanggal_lahir'  => 'Tanggal lahir',
    'jenis_kelamin'  => 'Jenis kelamin',
    'jenjang'        => 'Jenjang',
    'whatsapp'       => 'Nomor WhatsApp',
    'email'          => 'Email',
    'password'       => 'Password',
    'nama_ayah'      => 'Nama ayah',
    'nama_ibu'       => 'Nama ibu',
    'hp_ortu'        => 'No. HP orang tua',
    'pekerjaan_ortu' => 'Pekerjaan orang tua',
    'alamat'         => 'Alamat',
    'asal_sekolah'   => 'Asal sekolah',
    'kemampuan_quran'=> 'Kemampuan membaca Al-Qur\'an',
];
$tahunOptions = range(date('Y') + 1, 2018);
$old = $_POST; // untuk repopulate form
?>
<?php if (!empty($errors['_global'])): ?>
<div class="alert alert-error" style="max-width:1100px;margin:20px auto;padding:12px 16px;background:#fee;color:#c33;border-radius:4px;">
  <?= e($errors['_global']) ?>
</div>
<?php endif; ?>

<?php if ($successData): ?>
<div class="psb-success-overlay" id="psbSuccessOverlay" style="position:fixed;inset:0;background:rgba(0,0,0,0.6);z-index:1000;display:flex;align-items:center;justify-content:center;padding:20px;">
  <div style="background:#fff;border-radius:8px;padding:40px;max-width:520px;width:100%;text-align:center;">
    <div style="width:72px;height:72px;background:rgba(13,122,74,0.1);border-radius:50%;display:flex;align-items:center;justify-content:center;margin:0 auto 20px;font-size:30px;">&#x2705;</div>
    <h3 style="font-family:'Plus Jakarta Sans',sans-serif;font-size:22px;margin-bottom:12px;">Alhamdulillah!</h3>
    <p style="font-size:14px;color:#666;line-height:1.7;margin-bottom:20px;">
      Akun pendaftaran <strong><?= e($successData['nama']) ?></strong> berhasil dibuat.
      Anda sudah otomatis login ke portal calon peserta.
    </p>
    <div style="background:var(--cream,#f5f0e1);border-radius:4px;padding:16px;margin:20px 0;">
      <div style="font-size:11px;letter-spacing:2px;text-transform:uppercase;color:#888;margin-bottom:6px;">Nomor Pendaftaran</div>
      <div style="font-size:22px;font-weight:700;color:#0d7a4a;letter-spacing:3px;font-family:'Plus Jakarta Sans',sans-serif;">
        <?= e($successData['nomor']) ?>
      </div>
    </div>
    <div style="background:#f7fcf9;border-left:3px solid #0d7a4a;padding:12px 16px;text-align:left;margin:16px 0;font-size:13px;color:#555;">
      <strong>Langkah selanjutnya:</strong><br>
      1. Lanjutkan ke Portal &mdash; isi data akademik &amp; pilih jalur<br>
      2. Upload berkas wajib &amp; berkas jalur<br>
      3. Kirim pendaftaran &amp; tunggu verifikasi panitia
    </div>
    <a href="<?= e(portalAwalUrl()) ?>" class="btn-next" style="display:inline-block;padding:14px 28px;background:#0d7a4a;color:#fff;text-decoration:none;border-radius:3px;font-weight:600;margin-top:12px;">
      Buka Portal Santri &rarr;
    </a>
    <div style="margin-top:16px;font-size:12px;color:#999;">
      Gelombang: <strong><?= e($successData['gelombang']) ?></strong>
    </div>
  </div>
</div>
<?php endif; ?>

<div class="page-hero">
  <div class="page-hero-pattern"></div>
  <div class="page-hero-content">
    <div class="page-hero-tag"><span></span>Penerimaan Santri Baru<span></span></div>
    <h1>Buat Akun Pendaftaran</h1>
    <p>Tahun Ajaran <?= e($psbTahun) ?><?php if ($gelombangAktif): ?> &middot; <?= e($gelombangAktif['label']) ?><?php endif; ?></p>
  </div>
</div>

<?php if (!$psbBuka): ?>
<div class="psb-closed" style="max-width:680px;margin:60px auto;padding:40px;background:#fff;border-radius:6px;text-align:center;">
  <h2 style="color:#888;margin-bottom:12px;">Pendaftaran Sedang Tutup</h2>
  <p style="color:#666;">Pendaftaran belum dibuka atau sudah ditutup. Silakan cek kembali di lain waktu.</p>
  <p style="margin-top:20px;font-size:13px;color:#999;">Tahun Ajaran <?= e($psbTahun) ?></p>
</div>
<?php elseif (!$gelombangAktif): ?>
<div class="psb-closed" style="max-width:680px;margin:60px auto;padding:40px;background:#fff;border-radius:6px;text-align:center;">
  <h2 style="color:#888;margin-bottom:12px;">Tidak Ada Gelombang Aktif</h2>
  <p style="color:#666;">Saat ini tidak ada gelombang pendaftaran yang sedang buka. Hubungi panitia untuk informasi lebih lanjut.</p>
  <p style="margin-top:20px;font-size:13px;color:#999;">
    <a href="https://wa.me/<?= e($kontakWA) ?>" style="color:#0d7a4a;">Hubungi Panitia via WhatsApp</a>
  </p>
</div>
<?php else: ?>

<div class="psb-wrapper">
  <div class="psb-info">
    <div class="section-tag">
      <span></span><span class="section-tag-text">Gelombang Aktif</span><span></span>
    </div>
    <h2 class="section-title"><?= e($gelombangAktif['label']) ?></h2>
    <p class="section-desc">
      Pendaftaran: <?= date('d M Y', strtotime($gelombangAktif['tanggal_buka'])) ?>
      s/d <?= date('d M Y', strtotime($gelombangAktif['tanggal_tutup'])) ?>
    </p>
    <p class="section-desc" style="margin-top:8px;">
      Tes Pemetaan: <strong><?= date('d M Y', strtotime($gelombangAktif['tanggal_tes'])) ?></strong>
    </p>

    <div class="syarat-box reveal" style="margin-top:36px;">
      <h3>Alur Pendaftaran</h3>
      <ul class="syarat-list">
        <li><div class="syarat-check">1</div> <strong>Buat Akun</strong> &mdash; Isi formulir di samping, dapatkan nomor pendaftaran.</li>
        <li><div class="syarat-check">2</div> <strong>Lengkapi Berkas</strong> &mdash; Login ke portal, pilih jalur, upload dokumen.</li>
        <li><div class="syarat-check">3</div> <strong>Tes Pemetaan</strong> &mdash; Ikuti tes sesuai jadwal gelombang.</li>
        <li><div class="syarat-check">4</div> <strong>Verifikasi</strong> &mdash; Panitia review berkas &amp; nilai tes.</li>
        <li><div class="syarat-check">5</div> <strong>Diterima &amp; Daftar Ulang</strong> &mdash; Pembayaran, generate surat kesanggupan.</li>
      </ul>
    </div>

    <div class="biaya-box reveal" style="margin-top:24px;">
      <h3>Estimasi Biaya</h3>
      <div class="biaya-item">
        <span class="biaya-label">Pendaftaran</span>
        <span class="biaya-value"><?= ($gelombangAktif['slug'] === 'indent') ? 'GRATIS' : '' ?></span>
      </div>
      <div class="biaya-item">
        <span class="biaya-label">ADM Awal</span>
        <span class="biaya-value">Rp 7.500.000</span>
      </div>
      <div class="biaya-item">
        <span class="biaya-label">Wakaf</span>
        <span class="biaya-value">Rp <?= number_format(
            match($gelombangAktif['slug']) {
              'indent' => 1500000,
              'gelombang-1' => 2000000,
              'gelombang-2' => 3000000,
              default => 3500000,
            }, 0, ',', '.'
        ) ?></span>
      </div>
      <div class="biaya-item">
        <span class="biaya-label">SPP / Bulan</span>
        <span class="biaya-value">Rp 800.000</span>
      </div>
      <p style="font-size:12px;color:rgba(255,255,255,0.45);margin-top:16px;line-height:1.6;">
        * Tersedia potongan untuk jalur prestasi, tahfidz, kaderisasi, alumni, dan dhuafa.
        Detail di portal setelah login.
      </p>
    </div>
  </div>

  <div class="psb-form-card reveal-right">
    <div class="form-header">
      <div class="arabic">&#x0628;&#x0650;&#x0633;&#x0650;&#x0645;&#x0650; &#x0627;&#x0644;&#x0644;&#x0651;&#x0647;&#x0650;</div>
      <h2>Formulir Akun Pendaftaran</h2>
      <p>Isi data dengan lengkap dan benar</p>
    </div>

    <div class="form-step" aria-label="Langkah pendaftaran">
      <div class="step-item active" id="stepInd1">
        <div class="step-num active" id="sNum1">1</div>
        <div class="step-label">Data Calon</div>
      </div>
      <div class="step-item" id="stepInd2">
        <div class="step-num" id="sNum2">2</div>
        <div class="step-label">Data Orang Tua &amp; Akun</div>
      </div>
    </div>

    <form method="post" id="psbForm" novalidate>
      <input type="hidden" name="csrf_token" value="<?= generateCsrfToken() ?>">

      <div class="form-page active" id="page1">
        <div class="form-group">
          <label>Nama Lengkap Calon Santri <span class="req">*</span></label>
          <input type="text" name="nama_lengkap" class="form-control<?= isset($errors['nama_lengkap']) ? ' is-error' : '' ?>"
                 placeholder="Nama sesuai akta kelahiran"
                 value="<?= e($old['nama_lengkap'] ?? '') ?>" required>
          <?php if (isset($errors['nama_lengkap'])): ?><p class="field-error"><?= e($errors['nama_lengkap']) ?></p><?php endif; ?>
        </div>

        <div class="form-row">
          <div class="form-group">
            <label>Tempat Lahir <span class="req">*</span></label>
            <input type="text" name="tempat_lahir" class="form-control<?= isset($errors['tempat_lahir']) ? ' is-error' : '' ?>"
                   placeholder="Kota lahir"
                   value="<?= e($old['tempat_lahir'] ?? '') ?>" required>
            <?php if (isset($errors['tempat_lahir'])): ?><p class="field-error"><?= e($errors['tempat_lahir']) ?></p><?php endif; ?>
          </div>
          <div class="form-group">
            <label>Tanggal Lahir <span class="req">*</span></label>
            <input type="date" name="tanggal_lahir" class="form-control<?= isset($errors['tanggal_lahir']) ? ' is-error' : '' ?>"
                   value="<?= e($old['tanggal_lahir'] ?? '') ?>" required>
            <?php if (isset($errors['tanggal_lahir'])): ?><p class="field-error"><?= e($errors['tanggal_lahir']) ?></p><?php endif; ?>
          </div>
        </div>

        <div class="form-group">
          <label>Jenis Kelamin <span class="req">*</span></label>
          <div class="radio-group">
            <label class="radio-item">
              <input type="radio" name="jenis_kelamin" value="L" <?= ($old['jenis_kelamin'] ?? '') === 'L' ? 'checked' : '' ?> required>
              Laki-laki
            </label>
            <label class="radio-item">
              <input type="radio" name="jenis_kelamin" value="P" <?= ($old['jenis_kelamin'] ?? '') === 'P' ? 'checked' : '' ?>>
              Perempuan
            </label>
          </div>
          <?php if (isset($errors['jenis_kelamin'])): ?><p class="field-error"><?= e($errors['jenis_kelamin']) ?></p><?php endif; ?>
        </div>

        <div class="form-group">
          <label>Jenjang yang Dituju <span class="req">*</span></label>
          <select name="jenjang" class="form-control<?= isset($errors['jenjang']) ? ' is-error' : '' ?>" required>
            <option value="">-- Pilih Jenjang --</option>
            <?php foreach ($labelJenjang as $val => $lbl): ?>
              <option value="<?= e($val) ?>" <?= ($old['jenjang'] ?? '') === $val ? 'selected' : '' ?>><?= e($lbl) ?></option>
            <?php endforeach; ?>
          </select>
          <?php if (isset($errors['jenjang'])): ?><p class="field-error"><?= e($errors['jenjang']) ?></p><?php endif; ?>
        </div>

        <div class="form-group">
          <label>Nomor WhatsApp Aktif <span class="req">*</span></label>
          <input type="tel" name="whatsapp" class="form-control<?= isset($errors['whatsapp']) ? ' is-error' : '' ?>"
                 placeholder="08xxxxxxxxxx"
                 value="<?= e($old['whatsapp'] ?? '') ?>" required>
          <p class="form-note">Contoh: 081234567890 (tanpa +62)</p>
          <?php if (isset($errors['whatsapp'])): ?><p class="field-error"><?= e($errors['whatsapp']) ?></p><?php endif; ?>
        </div>

        <div class="form-group">
          <label>Asal Sekolah <span class="req">*</span></label>
          <input type="text" name="asal_sekolah" class="form-control<?= isset($errors['asal_sekolah']) ? ' is-error' : '' ?>"
                 placeholder="Nama sekolah / madrasah asal"
                 value="<?= e($old['asal_sekolah'] ?? '') ?>" required>
          <?php if (isset($errors['asal_sekolah'])): ?><p class="field-error"><?= e($errors['asal_sekolah']) ?></p><?php endif; ?>
        </div>

        <div class="form-group">
          <label>Kemampuan Membaca Al-Qur'an <span class="req">*</span></label>
          <select name="kemampuan_quran" class="form-control<?= isset($errors['kemampuan_quran']) ? ' is-error' : '' ?>" required>
            <option value="">-- Pilih Kemampuan --</option>
            <?php foreach ($labelQuran as $val => $lbl): ?>
              <option value="<?= e($val) ?>" <?= ($old['kemampuan_quran'] ?? '') === $val ? 'selected' : '' ?>><?= e($lbl) ?></option>
            <?php endforeach; ?>
          </select>
          <?php if (isset($errors['kemampuan_quran'])): ?><p class="field-error"><?= e($errors['kemampuan_quran']) ?></p><?php endif; ?>
        </div>

        <div class="form-nav">
          <button type="button" class="btn-next" onclick="psbGoTo(2)">Lanjut &rarr;</button>
        </div>
      </div>

      <div class="form-page" id="page2">
        <div class="form-group">
          <label>Nama Ayah <span class="req">*</span></label>
          <input type="text" name="nama_ayah" class="form-control<?= isset($errors['nama_ayah']) ? ' is-error' : '' ?>"
                 placeholder="Nama lengkap ayah"
                 value="<?= e($old['nama_ayah'] ?? '') ?>" required>
          <?php if (isset($errors['nama_ayah'])): ?><p class="field-error"><?= e($errors['nama_ayah']) ?></p><?php endif; ?>
        </div>

        <div class="form-group">
          <label>Nama Ibu <span class="req">*</span></label>
          <input type="text" name="nama_ibu" class="form-control<?= isset($errors['nama_ibu']) ? ' is-error' : '' ?>"
                 placeholder="Nama lengkap ibu"
                 value="<?= e($old['nama_ibu'] ?? '') ?>" required>
          <?php if (isset($errors['nama_ibu'])): ?><p class="field-error"><?= e($errors['nama_ibu']) ?></p><?php endif; ?>
        </div>

        <div class="form-group">
          <label>No. HP Orang Tua / Wali <span class="req">*</span></label>
          <input type="tel" name="hp_ortu" class="form-control<?= isset($errors['hp_ortu']) ? ' is-error' : '' ?>"
                 placeholder="08xxxxxxxxxx"
                 value="<?= e($old['hp_ortu'] ?? '') ?>" required>
          <?php if (isset($errors['hp_ortu'])): ?><p class="field-error"><?= e($errors['hp_ortu']) ?></p><?php endif; ?>
        </div>

        <div class="form-group">
          <label>Pekerjaan Orang Tua</label>
          <input type="text" name="pekerjaan_ortu" class="form-control"
                 placeholder="Pekerjaan ayah / ibu"
                 value="<?= e($old['pekerjaan_ortu'] ?? '') ?>">
        </div>

        <div class="form-group">
          <label>Alamat Lengkap <span class="req">*</span></label>
          <textarea name="alamat" rows="3" class="form-control<?= isset($errors['alamat']) ? ' is-error' : '' ?>"
                    placeholder="Jalan, RT/RW, Kelurahan, Kecamatan, Kabupaten/Kota, Provinsi" required><?= e($old['alamat'] ?? '') ?></textarea>
          <?php if (isset($errors['alamat'])): ?><p class="field-error"><?= e($errors['alamat']) ?></p><?php endif; ?>
        </div>

        <hr style="margin:24px 0;border:none;border-top:1px solid var(--cream-dark);">
        <h4 style="font-size:14px;margin-bottom:12px;color:var(--text-dark);">Akun Portal</h4>
        <p style="font-size:12px;color:var(--text-light);margin-bottom:16px;line-height:1.6;">
          Akun ini digunakan untuk login ke Portal Santri guna melengkapi data &amp; upload berkas.
        </p>

        <div class="form-group">
          <label>Email <span class="req">*</span></label>
          <input type="email" name="email" class="form-control<?= isset($errors['email']) ? ' is-error' : '' ?>"
                 placeholder="email@contoh.com"
                 value="<?= e($old['email'] ?? '') ?>" required>
          <?php if (isset($errors['email'])): ?><p class="field-error"><?= e($errors['email']) ?></p><?php endif; ?>
        </div>

        <div class="form-group">
          <label>Password <span class="req">*</span></label>
          <input type="password" name="password" class="form-control<?= isset($errors['password']) ? ' is-error' : '' ?>"
                 placeholder="Minimal 8 karakter" required>
          <p class="form-note">Gunakan kombinasi huruf &amp; angka. Tidak wajib diganti setelah login.</p>
          <?php if (isset($errors['password'])): ?><p class="field-error"><?= e($errors['password']) ?></p><?php endif; ?>
        </div>

        <p class="form-note" style="margin-top:8px;">
          Dengan mendaftar, Anda menyetujui syarat dan ketentuan yang berlaku
          di Pondok Pesantren Ash-Shiddiq.
        </p>

        <div class="form-nav">
          <button type="button" class="btn-prev" onclick="psbGoTo(1)">&larr; Kembali</button>
          <button type="submit" class="btn-next">Buat Akun &amp; Login</button>
        </div>
      </div>
    </form>
  </div>
</div>

<script>
let currentPage = 1;
function psbGoTo(page) {
  document.getElementById('page' + currentPage).classList.remove('active');
  document.getElementById('page' + page).classList.add('active');
  for (let i = 1; i <= 2; i++) {
    const num = document.getElementById('sNum' + i);
    const ind = document.getElementById('stepInd' + i);
    num.classList.remove('active', 'done');
    if (i < page) { num.classList.add('done'); num.textContent = '\u2713'; }
    else if (i === page) { num.classList.add('active'); num.textContent = i; ind.classList.add('active'); }
    else { num.textContent = i; ind.classList.remove('active'); }
  }
  currentPage = page;
  document.querySelector('.psb-form-card').scrollIntoView({ block: 'start', behavior: 'smooth' });
}
</script>
<?php endif; ?>
