<?php
/**
 * Portal Santri — wizard 4 step (Fase 1)
 * Step 3: Akademik lanjutan
 * Step 4: Pilih jalur + simulasi biaya
 * Step 5: Upload berkas wajib
 * Step 6: Upload berkas jalur (kondisional)
 */
requireCalonSantri();

$pdo = getDB();
$pendaftaran = getCurrentPendaftaran();
if (!$pendaftaran) {
    redirect('/psb');
}
$pendaftaranId = (int) $pendaftaran['id'];

// ── Cek apakah status masih izinkan edit wizard ──
$statusSekarang = $pendaftaran['status'];
$faseSelesai = in_array($statusSekarang, [
    'menunggu-verifikasi', 'tes-selesai', 'diterima', 'ditolak', 'daftar-ulang'
], true);

// ── Submit handler (semua step) ──
$errors = [];
$successMsg = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$faseSelesai) {
    validateCsrf();
    $step = sanitizeString($_POST['step'] ?? '');

    try {
        $pdo->beginTransaction();

        if ($step === 'akademik') {
            $tahunLulus = sanitizeInt($_POST['tahun_lulus'] ?? date('Y'));
            $jumlahHafalan = sanitizeString($_POST['jumlah_hafalan'] ?? '') ?: null;
            $motivasi = sanitizeString($_POST['motivasi'] ?? '') ?: null;
            $tinggiBadan = sanitizeFloat($_POST['tinggi_badan'] ?? '') ?: null;
            $beratBadan = sanitizeFloat($_POST['berat_badan'] ?? '') ?: null;

            if ($tahunLulus < 2015 || $tahunLulus > (int) date('Y') + 1) {
                $errors['tahun_lulus'] = 'Tahun lulus tidak valid.';
            }
            if ($tinggiBadan !== null && ($tinggiBadan < 50 || $tinggiBadan > 250)) {
                $errors['tinggi_badan'] = 'Tinggi badan tidak valid.';
            }
            if ($beratBadan !== null && ($beratBadan < 5 || $beratBadan > 250)) {
                $errors['berat_badan'] = 'Berat badan tidak valid.';
            }

            if (empty($errors)) {
                $upd = $pdo->prepare(
                    'UPDATE pendaftaran
                     SET tahun_lulus = ?, jumlah_hafalan = ?, motivasi = ?,
                         tinggi_badan = ?, berat_badan = ?
                     WHERE id = ?'
                );
                $upd->execute([
                    $tahunLulus, $jumlahHafalan, $motivasi,
                    $tinggiBadan, $beratBadan, $pendaftaranId
                ]);
                $successMsg = 'Data akademik tersimpan.';
            }
        }

        elseif ($step === 'jalur') {
            $jalur = sanitizeString($_POST['jalur'] ?? 'reguler');
            $jalurDetail = sanitizeString($_POST['jalur_detail'] ?? '') ?: null;
            $validJalur = array_keys(jalurPendaftaran());
            if (!in_array($jalur, $validJalur, true)) {
                $errors['jalur'] = 'Jalur tidak valid.';
            }
            // Jalur alumni hanya boleh disimpan kalau voucher sudah divalidasi
            // (kode + NISN cocok) untuk pendaftaran ini.
            if ($jalur === 'alumni-sdmua') {
                $cekV = $pdo->prepare(
                    "SELECT id FROM voucher_alumni
                      WHERE pendaftaran_id = ? AND jalur = 'alumni-sdmua' LIMIT 1"
                );
                $cekV->execute([$pendaftaranId]);
                if (!$cekV->fetch()) {
                    $errors['jalur'] = 'Isi voucher + NISN yang cocok dulu lewat kartu "+" sebelum memilih jalur Alumni.';
                }
            }
            if (in_array($jalur, ['prestasi', 'tahfidz'], true)) {
                $opts = jalurDetailOptions()[$jalur] ?? [];
                if (!$jalurDetail || !isset($opts[$jalurDetail])) {
                    $errors['jalur_detail'] = 'Pilih detail jalur.';
                }
            } else {
                $jalurDetail = null;
            }

            if (empty($errors)) {
                $jalurStatus = in_array($jalur, jalurPerluVerifikasi(), true) ? 'pending' : 'none';
                $upd = $pdo->prepare(
                    'UPDATE pendaftaran
                     SET jalur = ?, jalur_detail = ?, jalur_status = ?, jalur_potongan = NULL
                     WHERE id = ?'
                );
                $upd->execute([$jalur, $jalurDetail, $jalurStatus, $pendaftaranId]);
                // Simpan jalur non-alumni → lepas tandai voucher milik pendaftaran ini
                if ($jalur !== 'alumni-sdmua') {
                    $lepas = $pdo->prepare(
                        'UPDATE voucher_alumni SET pendaftaran_id = NULL WHERE pendaftaran_id = ?'
                    );
                    $lepas->execute([$pendaftaranId]);
                }
                $successMsg = 'Jalur pendaftaran tersimpan.';
            }
        }

        elseif ($step === 'finalisasi') {
            // Blokir kirim kalau berkas belum lengkap (4 wajib + berkas jalur)
            $cekBerkas = $pdo->prepare(
                'SELECT jenis FROM berkas_santri WHERE pendaftaran_id = ?'
            );
            $cekBerkas->execute([$pendaftaranId]);
            $adaJenis = array_column($cekBerkas->fetchAll(), 'jenis');
            $wajibKurang = [];
            foreach (['kartu-keluarga', 'akta-lahir', 'foto', 'ktp-ortu'] as $w) {
                if (!in_array($w, $adaJenis, true)) $wajibKurang[] = $w;
            }
            // Ambil jalur terkini untuk syarat berkas jalur
            $jalurNow = $pdo->prepare('SELECT jalur FROM pendaftaran WHERE id = ?');
            $jalurNow->execute([$pendaftaranId]);
            $jalurSaatIni = $jalurNow->fetchColumn() ?: 'reguler';
            $jalurKurang = [];
            foreach (jalurBerkasUntuk($jalurSaatIni) as $jb) {
                if (!in_array($jb, $adaJenis, true)) $jalurKurang[] = $jb;
            }
            if (!empty($wajibKurang) || !empty($jalurKurang)) {
                $errors['finalisasi'] = 'Berkas belum lengkap. Lengkapi dulu sebelum mengirim: '
                    . implode(', ', array_merge($wajibKurang, $jalurKurang)) . '.';
            } else {
                $upd = $pdo->prepare(
                    "UPDATE pendaftaran SET status = 'menunggu-verifikasi' WHERE id = ?"
                );
                $upd->execute([$pendaftaranId]);
                $successMsg = 'Berkas terkirim. Panitia akan memverifikasi.';
            }
        }

        $pdo->commit();

        if (empty($errors) && $successMsg) {
            $_SESSION['flash_success'] = $successMsg;
            $lanjut = ['akademik' => 'jalur', 'jalur' => 'berkas-wajib'];
            redirect('/portal-santri?step=' . ($lanjut[$step] ?? $step));
        }
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        error_log('Portal submit error: ' . $e->getMessage());
        $errors['_global'] = 'Terjadi kesalahan sistem.';
    }
}

// Ambil data pendaftaran terbaru
$s = $pdo->prepare(
    "SELECT p.*, pg.label AS gelombang_label, pg.tanggal_tes AS tanggal_tes_gelombang
     FROM pendaftaran p LEFT JOIN pendaftaran_gelombang pg ON pg.id = p.gelombang_id
     WHERE p.id = ? LIMIT 1"
);
$s->execute([$pendaftaranId]);
$pendaftaran = $s->fetch();

// Ambil daftar berkas yang sudah di-upload
$berkasStmt = $pdo->prepare(
    'SELECT id, jenis, nama_file, mime_type, created_at AS uploaded_at FROM berkas_santri
     WHERE pendaftaran_id = ? ORDER BY id'
);
$berkasStmt->execute([$pendaftaranId]);
$berkasRows = $berkasStmt->fetchAll();
$berkasByJenis = [];
foreach ($berkasRows as $b) {
    $berkasByJenis[$b['jenis']] = $b;
}

$berkasWajibList = [
    'kartu-keluarga' => 'Kartu Keluarga (KK)',
    'akta-lahir'     => 'Akta Kelahiran',
    'foto'           => 'Pas Foto 3x4',
    'ktp-ortu'       => 'KTP Orang Tua / KIA',
];

// Berkas pelengkap — dilengkapi setelah diterima (SKL/Ijazah menyusul kelulusan)
$berkasPelengkapList = [
    'ijazah' => 'Ijazah / SKL (menyusul setelah lulus)',
];

$labelJenjang = [
    'smp'              => 'SMP Muhammadiyah Unggulan Ashidiq',
    'sma'              => 'SMA Pondok Pesantren Ash-Shiddiq',
    'tahfidz-intensif' => 'Tahfidz Intensif',
];

$labelJalur = jalurPendaftaran();
$optsJalur = jalurDetailOptions();
$jalurBerkas = jalurBerkasUntuk($pendaftaran['jalur'] ?? 'reguler');

// Simulasi biaya
$simulasi = ($pendaftaran['gelombang_id'] && !empty($pendaftaran['jalur']))
    ? getSimulasiBiaya($pdo, $pendaftaran['jalur'], $pendaftaran['jalur_detail'],
                       (int) $pendaftaran['gelombang_id'], $pendaftaran['jenis_kelamin'])
    : null;

$stepSekarang = sanitizeString($_GET['step'] ?? 'akademik');
$stepValid = ['akademik', 'jalur', 'berkas-wajib', 'berkas-jalur', 'finalisasi', 'pembayaran', 'berkas-pelengkap'];
if (!in_array($stepSekarang, $stepValid, true)) $stepSekarang = 'akademik';

// ── Klaim jalur alumni (voucher + NISN) ────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['step'] ?? '') === 'alumni-klaim' && !$faseSelesai) {
    validateCsrf();
    $kodeV = sanitizeString($_POST['kode_voucher'] ?? '');
    $nisnV = sanitizeString($_POST['nisn'] ?? '');

    // Rate limit sederhana: maks 10 percobaan per jam per sesi
    $attempts = (int) ($_SESSION['alumni_klaim_attempts'] ?? 0);
    $attemptsAt = (int) ($_SESSION['alumni_klaim_at'] ?? 0);
    if (time() - $attemptsAt > 3600) $attempts = 0;

    if ($attempts >= 10) {
        $errors['alumni_klaim'] = 'Terlalu banyak percobaan. Silakan coba lagi 1 jam lagi.';
    } elseif ($kodeV === '' || $nisnV === '') {
        $errors['alumni_klaim'] = 'Kode undangan dan NISN wajib diisi.';
    } else {
        usleep(600000);
        $res = klaimJalurAlumni($pdo, $pendaftaranId, $kodeV, $nisnV);
        if ($res['ok']) {
            $_SESSION['alumni_klaim_attempts'] = 0;
            $_SESSION['flash_success'] = $res['pesan'] . ' Pilih kartu "Alumni SD Ashidiq" di bawah lalu Simpan Jalur.';
            redirect('/portal-santri?step=jalur');
        }
        $_SESSION['alumni_klaim_attempts'] = $attempts + 1;
        $_SESSION['alumni_klaim_at'] = time();
        $errors['alumni_klaim'] = $res['pesan'];
    }
}

// ── Submit cicilan (handler inline) ─────────────────────────
$msgCicilan = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['step'] ?? '') === 'cicilan') {
    validateCsrf();
    $pembiayaanId = sanitizeInt($_POST['pembiayaan_id'] ?? 0);
    $nominal = sanitizeFloat($_POST['nominal'] ?? 0);
    $tanggal = sanitizeString($_POST['tanggal_bayar'] ?? date('Y-m-d'));
    $metode = sanitizeString($_POST['metode'] ?? 'transfer');
    $metodeValid = ['tunai', 'transfer', 'virtual-account'];
    if (!in_array($metode, $metodeValid, true)) $metode = 'transfer';

    if ($pembiayaanId && $nominal > 0) {
        // Validasi: pembiayaan milik pendaftar yang login
        $cek = $pdo->prepare("SELECT id, nominal FROM pembiayaan WHERE id=? AND pendaftaran_id=?");
        $cek->execute([$pembiayaanId, $pendaftaranId]);
        $pem = $cek->fetch();
        if ($pem) {
            // Hitung sisa
            $sumCicilan = $pdo->prepare("SELECT COALESCE(SUM(nominal),0) FROM pembiayaan_cicilan WHERE pembiayaan_id=? AND status='verified'");
            $sumCicilan->execute([$pembiayaanId]);
            $sisa = (float)$pem['nominal'] - (float)$sumCicilan->fetchColumn();
            if ($nominal > $sisa) {
                $msgCicilan = 'Nominal melebihi sisa tagihan (Rp ' . number_format($sisa, 0, ',', '.') . ').';
            } else {
                // Hitung angsuran ke
                $ke = $pdo->prepare("SELECT COALESCE(MAX(angsuran_ke),0)+1 FROM pembiayaan_cicilan WHERE pembiayaan_id=?");
                $ke->execute([$pembiayaanId]);
                $angsuranKe = (int)$ke->fetchColumn();

                $ins = $pdo->prepare(
                    "INSERT INTO pembiayaan_cicilan
                     (pembiayaan_id, angsuran_ke, nominal, tanggal_bayar, metode, status)
                     VALUES (?,?,?,?,?,'pending')"
                );
                $ins->execute([$pembiayaanId, $angsuranKe, $nominal, $tanggal, $metode]);
                $msgCicilan = 'Cicilan dikirim. Menunggu verifikasi admin.';
            }
        } else {
            $msgCicilan = 'Item tagihan tidak valid.';
        }
    } else {
        $msgCicilan = 'Nominal dan item tagihan wajib diisi.';
    }
    if ($msgCicilan) {
        $_SESSION['flash_success'] = $msgCicilan;
        redirect('/portal-santri?step=pembayaran');
    }
}

// Status badge
$statusLabel = statusLabel($pendaftaran['status']);
$statusClass = match($pendaftaran['status']) {
    'pending'             => 'status-info',
    'menunggu-verifikasi' => 'status-warning',
    'tes-selesai'         => 'status-warning',
    'diterima'            => 'status-success',
    'ditolak'             => 'status-error',
    'daftar-ulang'        => 'status-success',
    default               => 'status-info',
};

$activePage      = 'psb';
$pageTitle       = 'Portal Santri — ' . APP_NAME;
$pageDescription = 'Portal calon peserta PSB Pondok Pesantren Ash-Shiddiq.';
$pageCanonical   = BASE_URL . '/portal-santri';
$bodyClass       = 'portal-santri-page';

$extraHead = '<link rel="stylesheet" href="' . BASE_URL . '/assets/css/portal.css?v=' . ASSET_VERSION . '">';
?>
<main class="page-section">
<div class="container portal-container">

  <header class="portal-header">
    <div>
      <h1 class="portal-title">Portal Santri</h1>
      <p class="portal-subtitle">
        <?= e($pendaftaran['nama_lengkap']) ?>
        &middot; <strong><?= e($pendaftaran['nomor_daftar']) ?></strong>
      </p>
    </div>
    <div class="status-badge <?= e($statusClass) ?>"><?= e($statusLabel) ?></div>
  </header>

  <?php if (!empty($_SESSION['flash_success'])): ?>
    <div class="flash-message flash-success"><?= e($_SESSION['flash_success']) ?>
      <button class="flash-close" onclick="this.parentElement.remove()">&times;</button>
    </div>
    <?php unset($_SESSION['flash_success']); ?>
  <?php endif; ?>

  <?php if (!empty($errors['_global'])): ?>
    <div class="flash-message flash-error"><?= e($errors['_global']) ?>
      <button class="flash-close" onclick="this.parentElement.remove()">&times;</button>
    </div>
  <?php endif; ?>

  <?php if ($faseSelesai): ?>
    <div class="portal-info-box">
      <h2>Berkas Terkirim</h2>
      <p>Seluruh tahapan formulir telah selesai. Panitia akan memverifikasi dan menghubungi Anda.</p>
      <p>Status saat ini: <strong><?= e($statusLabel) ?></strong></p>
      <?php if ($pendaftaran['gelombang_tanggal_tes'] ?? false): ?>
        <p>Jadwal Tes Pemetaan: <strong><?= date('d M Y', strtotime($pendaftaran['tanggal_tes_gelombang'])) ?></strong></p>
      <?php endif; ?>
    </div>
  <?php else: ?>

  <nav class="portal-steps" aria-label="Langkah portal">
    <a href="?step=akademik" class="portal-step <?= $stepSekarang === 'akademik' ? 'active' : '' ?>">
      <span class="num">3</span><span class="lbl">Akademik</span>
    </a>
    <a href="?step=jalur" class="portal-step <?= $stepSekarang === 'jalur' ? 'active' : '' ?>">
      <span class="num">4</span><span class="lbl">Pilih Jalur</span>
    </a>
    <a href="?step=berkas-wajib" class="portal-step <?= $stepSekarang === 'berkas-wajib' ? 'active' : '' ?>">
      <span class="num">5</span><span class="lbl">Berkas Wajib</span>
    </a>
    <a href="?step=berkas-jalur" class="portal-step <?= $stepSekarang === 'berkas-jalur' ? 'active' : '' ?>">
      <span class="num">6</span><span class="lbl">Berkas Jalur</span>
    </a>
    <a href="?step=finalisasi" class="portal-step <?= $stepSekarang === 'finalisasi' ? 'active' : '' ?>">
      <span class="num">✓</span><span class="lbl">Kirim</span>
    </a>
    <?php if (in_array($pendaftaran['status'], ['diterima','daftar-ulang'], true)): ?>
    <a href="?step=pembayaran" class="portal-step <?= $stepSekarang === 'pembayaran' ? 'active' : '' ?>">
      <span class="num">💰</span><span class="lbl">Pembayaran</span>
    </a>
    <a href="?step=berkas-pelengkap" class="portal-step <?= $stepSekarang === 'berkas-pelengkap' ? 'active' : '' ?>">
      <span class="num">📄</span><span class="lbl">Berkas Pelengkap</span>
    </a>
    <?php endif; ?>
  </nav>

  <?php if ($stepSekarang === 'akademik'): ?>
    <section class="portal-card">
      <h2>Data Akademik</h2>
      <form method="post" novalidate>
        <input type="hidden" name="csrf_token" value="<?= generateCsrfToken() ?>">
        <input type="hidden" name="step" value="akademik">

        <div class="form-row">
          <div class="form-group">
            <label>Tahun Lulus</label>
            <select name="tahun_lulus" class="form-control">
              <?php for ($y = (int) date('Y') + 1; $y >= 2018; $y--): ?>
                <option value="<?= $y ?>" <?= (int)($pendaftaran['tahun_lulus'] ?? 0) === $y ? 'selected' : '' ?>>
                  <?= $y ?>
                </option>
              <?php endfor; ?>
            </select>
          </div>
          <div class="form-group">
            <label>Jumlah Hafalan</label>
            <input type="text" name="jumlah_hafalan" class="form-control"
                   placeholder="Contoh: 5 Juz, atau belum hafal"
                   value="<?= e($pendaftaran['jumlah_hafalan'] ?? '') ?>">
          </div>
        </div>

        <div class="form-row">
          <div class="form-group">
            <label>Tinggi Badan (cm)</label>
            <input type="number" name="tinggi_badan" class="form-control" min="50" max="250" step="0.1"
                   value="<?= e($pendaftaran['tinggi_badan'] ?? '') ?>">
          </div>
          <div class="form-group">
            <label>Berat Badan (kg)</label>
            <input type="number" name="berat_badan" class="form-control" min="5" max="250" step="0.1"
                   value="<?= e($pendaftaran['berat_badan'] ?? '') ?>">
          </div>
        </div>

        <div class="form-group">
          <label>Motivasi</label>
          <textarea name="motivasi" rows="4" class="form-control"
                    placeholder="Ceritakan motivasi Anda masuk pesantren..."><?= e($pendaftaran['motivasi'] ?? '') ?></textarea>
        </div>

        <div class="portal-actions">
          <button type="submit" class="btn-primary">Simpan &amp; Lanjut</button>
        </div>
      </form>
    </section>

  <?php elseif ($stepSekarang === 'jalur'): ?>
    <section class="portal-card">
      <h2>Pilih Jalur Pendaftaran</h2>
      <p class="portal-note">Simulasi biaya muncul di sebelah kanan setelah Anda memilih jalur.</p>
      <form method="post" id="formJalur" novalidate>
        <input type="hidden" name="csrf_token" value="<?= generateCsrfToken() ?>">
        <input type="hidden" name="step" value="jalur">

        <div class="jalur-grid">
          <?php foreach ($labelJalur as $val => $lbl): ?>
            <?php if (in_array($val, jalurTersembunyi(), true)) continue; ?>
            <label class="jalur-radio-item">
              <input type="radio" name="jalur" value="<?= e($val) ?>"
                     <?= ($pendaftaran['jalur'] ?? 'reguler') === $val ? 'checked' : '' ?>
                     onchange="psbSyncJalur()">
              <span class="jalur-radio-label"><?= e($lbl) ?>
                <?php if (in_array($val, jalurPerluVerifikasi(), true)): ?>
                  <small style="color:var(--text-light);">— verifikasi berkas</small>
                <?php endif; ?>
              </span>
            </label>
          <?php endforeach; ?>
          <?php
          // Kartu alumni terbuka kalau voucher sudah divalidasi (kode+NISN cocok)
          // ATAU jalur tersimpan sudah alumni. Selama belum valid, tampil kartu "+" misteri.
          $cekVoucher = $pdo->prepare(
              "SELECT kode FROM voucher_alumni WHERE pendaftaran_id = ? AND jalur = 'alumni-sdmua' LIMIT 1"
          );
          $cekVoucher->execute([$pendaftaranId]);
          $voucherValid = $cekVoucher->fetchColumn() ?: null;
          $alumniTerbuka = (bool) $voucherValid;
          ?>
          <?php if ($alumniTerbuka): ?>
          <label class="jalur-radio-item">
            <input type="radio" name="jalur" value="alumni-sdmua"
                   <?= ($pendaftaran['jalur'] ?? '') === 'alumni-sdmua' ? 'checked' : '' ?> onchange="psbSyncJalur()">
            <span class="jalur-radio-label">Alumni SD Ashidiq
              <small style="color:var(--text-light);">— verifikasi berkas</small>
            </span>
          </label>
          <?php else: ?>
          <button type="button" id="jalurMystery" class="jalur-radio-item" aria-expanded="<?= !empty($errors['alumni_klaim']) ? 'true' : 'false' ?>" aria-controls="klaimWrap"
                  style="justify-content:center;cursor:pointer;background:#fff;" title="Punya kode khusus?">
            <span class="jalur-radio-label" style="font-size:28px;font-weight:700;line-height:1;">+</span>
          </button>
          <?php endif; ?>
        </div>

        <?php if (($pendaftaran['jalur'] ?? '') === 'dhuafa'): ?>
        <p class="portal-note">
          Jalur Anda (<strong><?= e($labelJalur[$pendaftaran['jalur']] ?? $pendaftaran['jalur']) ?></strong>)
          ditetapkan oleh panitia dan tidak dapat diubah lewat portal. Hubungi panitia untuk informasi lebih lanjut.
        </p>
        <?php endif; ?>

        <?php foreach ($optsJalur as $jalurKey => $details): ?>
          <div class="jalur-detail-wrap" id="detail-<?= e($jalurKey) ?>" style="display:none;">
            <h4>Pilih <?= $jalurKey === 'prestasi' ? 'Tingkat Prestasi' : 'Kategori Hafalan' ?>:</h4>
            <?php foreach ($details as $val => $opt): ?>
              <label class="radio-inline">
                <input type="radio" name="jalur_detail" value="<?= e($val) ?>"
                       <?= ($pendaftaran['jalur_detail'] ?? '') === $val ? 'checked' : '' ?>>
                <?= e($opt['label']) ?> &mdash; potongan <?= (int)$opt['potongan'] ?>%
              </label>
            <?php endforeach; ?>
          </div>
        <?php endforeach; ?>

        <div id="simulasiBox" style="display:none;">
          <h4>Simulasi Biaya</h4>
          <div id="simulasiContent"></div>
        </div>

        <div class="portal-actions">
          <button type="submit" class="btn-primary">Simpan Jalur &amp; Lanjut</button>
        </div>
      </form>

      <?php if (!$alumniTerbuka): ?>
      <div id="klaimWrap" <?= !empty($errors['alumni_klaim']) ? '' : 'hidden' ?> style="margin-top:20px;">
        <div class="portal-info-box">
          <form method="post" style="max-width:420px;">
            <input type="hidden" name="csrf_token" value="<?= generateCsrfToken() ?>">
            <input type="hidden" name="step" value="alumni-klaim">
            <div class="form-group">
              <label>Voucher</label>
              <input type="text" name="kode_voucher" class="form-control" placeholder="ASQ-XXXXXXXXXX"
                     required autocomplete="off" style="text-transform:uppercase;">
            </div>
            <div class="form-group">
              <label>NISN</label>
              <input type="text" name="nisn" class="form-control" placeholder="NISN"
                     required autocomplete="off" inputmode="numeric">
            </div>
            <?php if (!empty($errors['alumni_klaim'])): ?><p class="field-error"><?= e($errors['alumni_klaim']) ?></p><?php endif; ?>
            <button type="submit" class="btn-primary">Gunakan Kode</button>
          </form>
        </div>
      </div>
      <script>
      (function () {
        const btn = document.getElementById('jalurMystery');
        if (!btn) return;
        btn.addEventListener('click', function () {
          const wrap = document.getElementById('klaimWrap');
          const open = wrap.hasAttribute('hidden');
          if (open) {
            wrap.removeAttribute('hidden');
            this.setAttribute('aria-expanded', 'true');
            wrap.scrollIntoView({ block: 'nearest', behavior: 'smooth' });
            const inp = wrap.querySelector('input[name="kode_voucher"]');
            if (inp) inp.focus();
          } else {
            wrap.setAttribute('hidden', '');
            this.setAttribute('aria-expanded', 'false');
          }
        });
      })();
      </script>
      <?php endif; ?>
    </section>

    <script>
    const JALUR_OPTS = <?= json_encode($optsJalur, JSON_UNESCAPED_UNICODE) ?>;
    const SIMULASI_DATA = <?= json_encode($simulasi, JSON_UNESCAPED_UNICODE) ?>;
    const FORMAT_RUPIAH = (n) => 'Rp ' + Math.round(n).toString().replace(/\B(?=(\d{3})+(?!\d))/g, '.');

    function psbSyncJalur() {
      const checked = document.querySelector('input[name="jalur"]:checked');
      const jalur = checked ? checked.value : 'reguler';
      // Hide all detail blocks
      document.querySelectorAll('.jalur-detail-wrap').forEach(el => el.style.display = 'none');
      // Show relevant
      const wrap = document.getElementById('detail-' + jalur);
      if (wrap) {
        wrap.style.display = 'block';
        wrap.querySelectorAll('input[name="jalur_detail"]').forEach(r => { r.disabled = false; });
      }
      // Reset unrelated
      document.querySelectorAll('.jalur-detail-wrap').forEach(el => {
        if (el.id !== 'detail-' + jalur) {
          el.querySelectorAll('input[name="jalur_detail"]').forEach(r => { r.checked = false; r.disabled = true; });
        }
      });
      // Show simulasi
      hitungSimulasi(jalur);
    }

    function hitungSimulasi(jalur) {
      const detailEl = document.querySelector('input[name="jalur_detail"]:checked');
      const detail = detailEl ? detailEl.value : null;
      const fd = new FormData();
      fd.append('csrf_token', document.querySelector('input[name="csrf_token"]').value);
      fd.append('jalur', jalur);
      if (detail) fd.append('jalur_detail', detail);
      fetch('/api/simulasi-biaya.php', { method: 'POST', body: fd, credentials: 'same-origin' })
        .then(r => r.json())
        .then(data => {
          if (!data.success) { document.getElementById('simulasiBox').style.display = 'none'; return; }
          const s = data.simulasi;
          let html = '<table class="simulasi-table">';
          html += '<tr><td>Pendaftaran</td><td class="text-right">' + (s.pendaftaran === 0 ? '<strong>GRATIS</strong>' : FORMAT_RUPIAH(s.pendaftaran)) + '</td></tr>';
          if (s.potongan > 0) {
            html += '<tr><td>ADM Awal</td><td class="text-right"><s style="opacity:0.5;">' + FORMAT_RUPIAH(s.administrasi_asli) + '</s> <strong>' + FORMAT_RUPIAH(s.administrasi) + '</strong></td></tr>';
            html += '<tr class="simulasi-potongan"><td>Potongan</td><td class="text-right">&minus; ' + FORMAT_RUPIAH(s.potongan) + ' <small>(' + (s.potongan_label || '') + ')</small></td></tr>';
          } else {
            html += '<tr><td>ADM Awal</td><td class="text-right">' + FORMAT_RUPIAH(s.administrasi) + '</td></tr>';
          }
          html += '<tr><td>Wakaf</td><td class="text-right">' + FORMAT_RUPIAH(s.wakaf) + '</td></tr>';
          html += '<tr class="simulasi-total"><td><strong>Total Tagihan</strong></td><td class="text-right"><strong>' + FORMAT_RUPIAH(s.total) + '</strong></td></tr>';
          if (s.syahriyah > 0) {
            html += '<tr class="simulasi-spp"><td>SPP / Bulan</td><td class="text-right">' + FORMAT_RUPIAH(s.syahriyah) + ' <small>(belum ditagih di PSB)</small></td></tr>';
          }
          html += '</table>';
          document.getElementById('simulasiContent').innerHTML = html;
          document.getElementById('simulasiBox').style.display = 'block';
        })
        .catch(() => { document.getElementById('simulasiBox').style.display = 'none'; });
    }
    // Init on load
    document.addEventListener('DOMContentLoaded', function () {
      psbSyncJalur();
      document.querySelectorAll('input[name="jalur_detail"]').forEach(r => {
        r.addEventListener('change', function () {
          const checked = document.querySelector('input[name="jalur"]:checked');
          if (checked) hitungSimulasi(checked.value);
        });
      });
    });
    </script>

  <?php elseif ($stepSekarang === 'berkas-wajib'): ?>
    <section class="portal-card">
      <h2>Upload Berkas Wajib</h2>
      <p class="portal-note">Upload 4 berkas di bawah ini. Format: JPG/PNG/WEBP/PDF, maks 5MB (foto maks 2MB). Ijazah/SKL dilengkapi setelah diterima.</p>

      <div class="berkas-list">
        <?php foreach ($berkasWajibList as $jenis => $label): ?>
          <?php $exists = $berkasByJenis[$jenis] ?? null; ?>
          <div class="berkas-item" data-jenis="<?= e($jenis) ?>">
            <div class="berkas-info">
              <div class="berkas-label"><?= e($label) ?></div>
              <?php if ($exists): ?>
                <div class="berkas-status success">✓ Sudah diupload</div>
              <?php else: ?>
                <div class="berkas-status pending">Belum diupload</div>
              <?php endif; ?>
            </div>
            <div class="berkas-action">
              <input type="file" accept="image/jpeg,image/png,image/webp,application/pdf"
                     data-jenis="<?= e($jenis) ?>" class="berkas-input">
              <button type="button" class="btn-outline btn-upload" data-jenis="<?= e($jenis) ?>">Upload</button>
              <?php if ($exists): ?>
                <a href="/berkas-santri?jenis=<?= e($jenis) ?>" target="_blank" class="btn-link">Lihat</a>
              <?php endif; ?>
            </div>
          </div>
        <?php endforeach; ?>
      </div>

      <div class="portal-actions">
        <a href="?step=akademik" class="btn-outline">&larr; Kembali</a>
        <a href="?step=berkas-jalur" class="btn-primary">Lanjut: Berkas Jalur &rarr;</a>
      </div>
    </section>

    <script>
    document.querySelectorAll('.btn-upload').forEach(btn => {
      btn.addEventListener('click', function () {
        const jenis = this.dataset.jenis;
        const input = document.querySelector('.berkas-input[data-jenis="' + jenis + '"]');
        if (!input.files || !input.files[0]) {
          alert('Pilih file terlebih dahulu.');
          return;
        }
        const fd = new FormData();
        fd.append('csrf_token', '<?= generateCsrfToken() ?>');
        fd.append('jenis', jenis);
        fd.append('file', input.files[0]);
        this.disabled = true;
        this.textContent = 'Uploading...';
        fetch('/api/upload-berkas.php', { method: 'POST', body: fd, credentials: 'same-origin' })
          .then(r => r.json())
          .then(data => {
            if (data.success) {
              location.reload();
            } else {
              alert('Gagal: ' + (data.message || 'Unknown error'));
              this.disabled = false;
              this.textContent = 'Upload';
            }
          })
          .catch(e => {
            alert('Error: ' + e.message);
            this.disabled = false;
            this.textContent = 'Upload';
          });
      });
    });
    </script>

  <?php elseif ($stepSekarang === 'berkas-jalur'): ?>
    <section class="portal-card">
      <h2>Upload Berkas Jalur</h2>
      <p class="portal-note">
        Jalur Anda saat ini: <strong><?= e($labelJalur[$pendaftaran['jalur']] ?? '—') ?></strong>
      </p>

      <?php if ($pendaftaran['jalur'] === 'kaderisasi'): ?>
      <div class="portal-info-box" style="margin-bottom:16px;">
        <h2>Langkah Jalur Kaderisasi</h2>
        <p>1. <a href="<?= BASE_URL ?>/download-template?slug=mou-kaderisasi" target="_blank" class="btn-link">Download template MOU Kaderisasi</a></p>
        <p>2. Isi, tanda tangan, lalu upload ulang pada slot di bawah ini.</p>
        <p>3. Wajib mengikuti <strong>Tes Pemetaan</strong> sesuai jadwal gelombang.</p>
      </div>
      <?php elseif ($pendaftaran['jalur'] === 'tahfidz'): ?>
      <div class="portal-info-box" style="margin-bottom:16px;">
        <h2>Jalur Tahfidz</h2>
        <p>Upload sertifikat tahfidz Anda. Calon jalur tahfidz akan diuji melalui <strong>Tes Hafalan</strong> oleh penguji panitia.</p>
      </div>
      <?php elseif ($pendaftaran['jalur'] === 'prestasi'): ?>
      <div class="portal-info-box" style="margin-bottom:16px;">
        <h2>Jalur Prestasi</h2>
        <p>Upload sertifikat / piagam prestasi <strong>tingkat tertinggi</strong> yang pernah diraih.</p>
      </div>
      <?php endif; ?>

      <?php if (empty($jalurBerkas)): ?>
        <p>Tidak ada berkas tambahan yang diperlukan untuk jalur ini. Silakan lanjut ke finalisasi.</p>
      <?php else: ?>
        <div class="berkas-list">
          <?php foreach ($jalurBerkas as $jenis): ?>
            <?php
              $labelMap = [
                'sertifikat-tka'     => 'Sertifikat / Piagam Prestasi (tingkat tertinggi)',
                'sertifikat-tahfidz' => 'Sertifikat Tahfidz (jumlah juz)',
                'mou-kaderisasi'     => 'MOU Kaderisasi (sudah ditandatangani)',
                'surat-rekomendasi'  => 'Surat Rekomendasi',
                'sktm'               => 'SKTM (Surat Keterangan Tidak Mampu)',
                'surat-pernyataan'   => 'Surat Pernyataan',
              ];
              $exists = $berkasByJenis[$jenis] ?? null;
            ?>
            <div class="berkas-item" data-jenis="<?= e($jenis) ?>">
              <div class="berkas-info">
                <div class="berkas-label"><?= e($labelMap[$jenis] ?? $jenis) ?></div>
                <?php if ($exists): ?>
                  <div class="berkas-status success">✓ Sudah diupload</div>
                <?php else: ?>
                  <div class="berkas-status pending">Belum diupload</div>
                <?php endif; ?>
              </div>
              <div class="berkas-action">
                <input type="file" accept="image/jpeg,image/png,image/webp,application/pdf"
                       data-jenis="<?= e($jenis) ?>" class="berkas-input">
                <button type="button" class="btn-outline btn-upload" data-jenis="<?= e($jenis) ?>">Upload</button>
                <?php if ($exists): ?>
                  <a href="/berkas-santri?jenis=<?= e($jenis) ?>" target="_blank" class="btn-link">Lihat</a>
                <?php endif; ?>
              </div>
            </div>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>

      <div class="portal-actions">
        <a href="?step=berkas-wajib" class="btn-outline">&larr; Kembali</a>
        <a href="?step=finalisasi" class="btn-primary">Lanjut: Finalisasi &rarr;</a>
      </div>
    </section>

    <script>
    document.querySelectorAll('.btn-upload').forEach(btn => {
      btn.addEventListener('click', function () {
        const jenis = this.dataset.jenis;
        const input = document.querySelector('.berkas-input[data-jenis="' + jenis + '"]');
        if (!input.files || !input.files[0]) { alert('Pilih file terlebih dahulu.'); return; }
        const fd = new FormData();
        fd.append('csrf_token', '<?= generateCsrfToken() ?>');
        fd.append('jenis', jenis);
        fd.append('file', input.files[0]);
        this.disabled = true;
        this.textContent = 'Uploading...';
        fetch('/api/upload-berkas.php', { method: 'POST', body: fd, credentials: 'same-origin' })
          .then(r => r.json())
          .then(data => { data.success ? location.reload() : (alert('Gagal: ' + (data.message || '')), this.disabled = false, this.textContent = 'Upload'); })
          .catch(e => { alert('Error: ' + e.message); this.disabled = false; this.textContent = 'Upload'; });
      });
    });
    </script>

  <?php elseif ($stepSekarang === 'finalisasi'): ?>
    <section class="portal-card">
      <h2>Kirim Berkas Pendaftaran</h2>
      <p>Periksa kembali data Anda. Setelah dikirim, status pendaftaran akan berubah menjadi
        <strong>Menunggu Verifikasi</strong> dan Panitia akan mereview berkas Anda.</p>

      <div class="ringkasan-box">
        <h4>Ringkasan</h4>
        <ul>
          <li>Nama: <strong><?= e($pendaftaran['nama_lengkap']) ?></strong></li>
          <li>Nomor: <strong><?= e($pendaftaran['nomor_daftar']) ?></strong></li>
          <li>Jenjang: <strong><?= e($labelJenjang[$pendaftaran['jenjang']] ?? $pendaftaran['jenjang']) ?></strong></li>
          <li>Jalur: <strong><?= e($labelJalur[$pendaftaran['jalur']] ?? $pendaftaran['jalur']) ?></strong>
            <?php if ($pendaftaran['jalur_detail']): ?>
              (<?= e(($optsJalur[$pendaftaran['jalur']][$pendaftaran['jalur_detail']]['label'] ?? $pendaftaran['jalur_detail'])) ?>)
            <?php endif; ?>
          </li>
          <li>Gelombang: <strong><?= e($pendaftaran['gelombang_label']) ?></strong></li>
          <li>Berkas wajib diupload: <strong><?= count(array_intersect_key($berkasByJenis, $berkasWajibList)) ?> / <?= count($berkasWajibList) ?></strong></li>
          <?php if (!empty($jalurBerkas)): ?>
            <li>Berkas jalur diupload: <strong><?= count(array_intersect_key($berkasByJenis, array_flip($jalurBerkas))) ?> / <?= count($jalurBerkas) ?></strong></li>
          <?php endif; ?>
        </ul>
      </div>

      <?php
      $wajibKurangView = array_diff(array_keys($berkasWajibList), array_keys($berkasByJenis));
      $jalurKurangView = array_diff($jalurBerkas, array_keys($berkasByJenis));
      ?>
      <?php if (!empty($wajibKurangView) || !empty($jalurKurangView)): ?>
      <div class="portal-info-box" style="border-left-color:#c33;">
        <h2>Belum bisa dikirim</h2>
        <p>Lengkapi dulu berkas berikut:</p>
        <ul>
          <?php foreach ($wajibKurangView as $k): ?>
          <li><a href="?step=berkas-wajib" class="btn-link"><?= e($berkasWajibList[$k]) ?></a></li>
          <?php endforeach; ?>
          <?php foreach ($jalurKurangView as $k): ?>
          <li><a href="?step=berkas-jalur" class="btn-link"><?= e($k) ?></a></li>
          <?php endforeach; ?>
        </ul>
      </div>
      <?php endif; ?>
      <?php if (!empty($errors['finalisasi'])): ?><p class="field-error"><?= e($errors['finalisasi']) ?></p><?php endif; ?>

      <form method="post">
        <input type="hidden" name="csrf_token" value="<?= generateCsrfToken() ?>">
        <input type="hidden" name="step" value="finalisasi">
        <div class="portal-actions">
          <a href="?step=berkas-jalur" class="btn-outline">&larr; Kembali</a>
          <button type="submit" class="btn-primary"
                  onclick="return confirm('Kirim seluruh berkas untuk verifikasi Panitia?')">
            Kirim Pendaftaran
          </button>
        </div>
      </form>
    </section>

  <?php endif; ?>

  <?php endif; // faseSelesai ?>

  <?php if ($stepSekarang === 'pembayaran' && in_array($pendaftaran['status'], ['diterima','daftar-ulang'], true)): ?>
    <section class="portal-card">
      <h2>Pembayaran Tagihan</h2>
      <p>Berikut adalah tagihan final Anda. Silakan lakukan pembayaran dan upload bukti cicilan.</p>

      <?php
      $tagihan = $pdo->prepare(
        "SELECT id, jenis, nama, harga_asli, harga_diskon, nominal, status
         FROM pembiayaan WHERE pendaftaran_id = ? ORDER BY urutan"
      );
      $tagihan->execute([$pendaftaranId]);
      $itemList = $tagihan->fetchAll();

      $sumVerified = [];
      foreach ($itemList as $it) {
          $sv = $pdo->prepare("SELECT COALESCE(SUM(nominal),0) FROM pembiayaan_cicilan WHERE pembiayaan_id=? AND status='verified'");
          $sv->execute([$it['id']]);
          $sumVerified[$it['id']] = (float) $sv->fetchColumn();
      }
      ?>

      <?php if (empty($itemList)): ?>
        <p style="color:#999;">Belum ada tagihan. Tagihan akan muncul setelah Panitia melakukan snapshot final.</p>
      <?php else: ?>
        <?php foreach ($itemList as $it):
            $sisa = (float)$it['nominal'] - ($sumVerified[$it['id']] ?? 0);
            $lunas = $sisa <= 0;
            $cicilanList = $pdo->prepare("SELECT * FROM pembiayaan_cicilan WHERE pembiayaan_id=? ORDER BY id DESC");
            $cicilanList->execute([$it['id']]);
            $cicilanAll = $cicilanList->fetchAll();
        ?>
        <div class="tagihan-item">
          <div class="tagihan-head">
            <div>
              <h4><?= e($it['nama']) ?> <small>(<?= e($it['jenis']) ?>)</small></h4>
              <div style="font-size:13px;color:#888;">
                <?php if ((float)$it['harga_diskon'] > 0): ?>
                  <s>Rp <?= number_format((float)$it['harga_asli'], 0, ',', '.') ?></s>
                <?php endif; ?>
                <strong style="color:#0d7a4a;font-size:15px;">Rp <?= number_format((float)$it['nominal'], 0, ',', '.') ?></strong>
                <?php if ($lunas): ?>
                  <span class="badge badge-success">LUNAS</span>
                <?php else: ?>
                  <span class="badge badge-warning">Sisa Rp <?= number_format($sisa, 0, ',', '.') ?></span>
                <?php endif; ?>
              </div>
            </div>
          </div>

          <?php if (!empty($cicilanAll)): ?>
            <table class="cicilan-table">
              <thead><tr><th>#</th><th>Nominal</th><th>Tanggal</th><th>Metode</th><th>Status</th></tr></thead>
              <tbody>
                <?php foreach ($cicilanAll as $c): ?>
                <tr>
                  <td><?= (int)$c['angsuran_ke'] ?></td>
                  <td>Rp <?= number_format((float)$c['nominal'], 0, ',', '.') ?></td>
                  <td><?= e($c['tanggal_bayar']) ?></td>
                  <td><?= e($c['metode']) ?></td>
                  <td>
                    <?php if ($c['status'] === 'verified'): ?>
                      <span class="badge badge-success">✓ Diverifikasi</span>
                    <?php elseif ($c['status'] === 'rejected'): ?>
                      <span class="badge badge-error">Ditolak</span>
                    <?php else: ?>
                      <span class="badge badge-muted">Menunggu</span>
                    <?php endif; ?>
                  </td>
                </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          <?php endif; ?>

          <?php if (!$lunas): ?>
            <form method="post" class="cicilan-form" enctype="multipart/form-data">
              <input type="hidden" name="csrf_token" value="<?= generateCsrfToken() ?>">
              <input type="hidden" name="step" value="cicilan">
              <input type="hidden" name="pembiayaan_id" value="<?= (int)$it['id'] ?>">
              <div class="form-row">
                <div class="form-group">
                  <label>Nominal (Rp)</label>
                  <input type="number" name="nominal" min="1" max="<?= (int)$sisa ?>" required
                         class="form-control" placeholder="Maks Rp <?= number_format($sisa, 0, ',', '.') ?>">
                </div>
                <div class="form-group">
                  <label>Tanggal Bayar</label>
                  <input type="date" name="tanggal_bayar" required class="form-control" value="<?= date('Y-m-d') ?>">
                </div>
                <div class="form-group">
                  <label>Metode</label>
                  <select name="metode" class="form-control">
                    <option value="transfer">Transfer Bank</option>
                    <option value="virtual-account">Virtual Account</option>
                    <option value="tunai">Tunai (ke Panitia)</option>
                  </select>
                </div>
                <div class="form-group">
                  <label>&nbsp;</label>
                  <button type="submit" class="btn-primary">Bayar / Cicil</button>
                </div>
              </div>
              <p class="form-note">Rekening tujuan: <strong><?= e($pdo->query("SELECT value FROM pengaturan WHERE key_name='rekening_pondok'")->fetchColumn() ?: 'BCA 123-456-7890 a.n. Pondok Pesantren Ash-Shiddiq') ?></strong></p>
            </form>
          <?php endif; ?>
        </div>
        <?php endforeach; ?>
      <?php endif; ?>
    </section>
  <?php endif; ?>

  <?php if ($stepSekarang === 'berkas-pelengkap' && in_array($pendaftaran['status'], ['diterima','daftar-ulang'], true)): ?>
    <section class="portal-card">
      <h2>Berkas Pelengkap</h2>
      <p class="portal-note">Dilengkapi setelah diterima. Ijazah/SKL boleh menyusul setelah lulus. Format: JPG/PNG/WEBP/PDF, maks 5MB.</p>

      <div class="berkas-list">
        <?php foreach ($berkasPelengkapList as $jenis => $label): ?>
          <?php $exists = $berkasByJenis[$jenis] ?? null; ?>
          <div class="berkas-item" data-jenis="<?= e($jenis) ?>">
            <div class="berkas-info">
              <div class="berkas-label"><?= e($label) ?></div>
              <?php if ($exists): ?>
                <div class="berkas-status success">✓ Sudah diupload</div>
              <?php else: ?>
                <div class="berkas-status pending">Belum diupload</div>
              <?php endif; ?>
            </div>
            <div class="berkas-action">
              <input type="file" accept="image/jpeg,image/png,image/webp,application/pdf"
                     data-jenis="<?= e($jenis) ?>" class="berkas-input">
              <button type="button" class="btn-outline btn-upload-pelengkap" data-jenis="<?= e($jenis) ?>">Upload</button>
              <?php if ($exists): ?>
                <a href="/berkas-santri?jenis=<?= e($jenis) ?>" target="_blank" class="btn-link">Lihat</a>
              <?php endif; ?>
            </div>
          </div>
        <?php endforeach; ?>
      </div>

      <div class="portal-actions">
        <a href="?step=pembayaran" class="btn-outline">&larr; Pembayaran</a>
      </div>
    </section>

    <script>
    document.querySelectorAll('.btn-upload-pelengkap').forEach(btn => {
      btn.addEventListener('click', function () {
        const jenis = this.dataset.jenis;
        const input = document.querySelector('.berkas-input[data-jenis="' + jenis + '"]');
        if (!input.files || !input.files[0]) { alert('Pilih file terlebih dahulu.'); return; }
        const fd = new FormData();
        fd.append('csrf_token', '<?= generateCsrfToken() ?>');
        fd.append('jenis', jenis);
        fd.append('file', input.files[0]);
        this.disabled = true;
        this.textContent = 'Uploading...';
        fetch('/api/upload-berkas.php', { method: 'POST', body: fd, credentials: 'same-origin' })
          .then(r => r.json())
          .then(data => { data.success ? location.reload() : (alert('Gagal: ' + (data.message || '')), this.disabled = false, this.textContent = 'Upload'); })
          .catch(e => { alert('Error: ' + e.message); this.disabled = false; this.textContent = 'Upload'; });
      });
    });
    </script>
  <?php endif; ?>
</div>
</main>
