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
            // Prestasi internal (Akashi) hanya boleh disimpan kalau voucher
            // Akashi valid menempel pada pendaftaran ini.
            if ($jalur === 'prestasi' && $jalurDetail === 'internal') {
                $cekA = $pdo->prepare(
                    "SELECT id FROM voucher_akashi WHERE pendaftaran_id = ? LIMIT 1"
                );
                $cekA->execute([$pendaftaranId]);
                if (!$cekA->fetch()) {
                    $errors['jalur'] = 'Isi kode voucher Akashi yang valid dulu sebelum memilih Tingkat Internal.';
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
                // Simpan jalur selain prestasi-internal → lepas voucher Akashi
                if (!($jalur === 'prestasi' && $jalurDetail === 'internal')) {
                    $lepasA = $pdo->prepare(
                        'UPDATE voucher_akashi SET pendaftaran_id = NULL WHERE pendaftaran_id = ?'
                    );
                    $lepasA->execute([$pendaftaranId]);
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

// Berkas pelengkap — dilengkapi setelah diterima (opsional, tidak blokir finalisasi)
$berkasPelengkapList = [
    'ijazah' => 'Ijazah (menyusul setelah lulus, opsional)',
    'skl'    => 'SKL / Surat Keterangan Lulus (opsional)',
    'kip'    => 'KIP / Kartu Indonesia Pintar (opsional, bila ada)',
];

$labelJenjang = [
    'smp'              => 'SMP Muhammadiyah Unggulan Ashidiq',
    'sma'              => 'SMA Pondok Pesantren Ash-Shiddiq',
];

$labelJalur = jalurPendaftaran();
$optsJalur = jalurDetailOptions();
$jalurBerkas = jalurBerkasUntuk($pendaftaran['jalur'] ?? 'reguler');

// Simulasi biaya
$akashiJuara = null;
if (($pendaftaran['jalur'] ?? '') === 'prestasi' && ($pendaftaran['jalur_detail'] ?? '') === 'internal') {
    $ak = $pdo->prepare("SELECT juara FROM voucher_akashi WHERE pendaftaran_id = ? LIMIT 1");
    $ak->execute([$pendaftaranId]);
    $akashiJuara = $ak->fetchColumn() ?: null;
}
$simulasi = ($pendaftaran['gelombang_id'] && !empty($pendaftaran['jalur']))
    ? getSimulasiBiaya($pdo, $pendaftaran['jalur'], $pendaftaran['jalur_detail'],
                       (int) $pendaftaran['gelombang_id'], $pendaftaran['jenis_kelamin'],
                       $akashiJuara)
    : null;

$stepSekarang = sanitizeString($_GET['step'] ?? 'akademik');
$stepValid = ['akademik', 'jalur', 'berkas-wajib', 'berkas-jalur', 'finalisasi', 'pembayaran', 'surat-ttd', 'berkas-pelengkap'];
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

// ── Klaim voucher Akashi (prestasi internal, kode saja) ────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['step'] ?? '') === 'akashi-klaim' && !$faseSelesai) {
    validateCsrf();
    $kodeA = sanitizeString($_POST['kode_voucher'] ?? '');

    $attempts = (int) ($_SESSION['akashi_klaim_attempts'] ?? 0);
    $attemptsAt = (int) ($_SESSION['akashi_klaim_at'] ?? 0);
    if (time() - $attemptsAt > 3600) $attempts = 0;

    if ($attempts >= 10) {
        $errors['akashi_klaim'] = 'Terlalu banyak percobaan. Silakan coba lagi 1 jam lagi.';
    } elseif ($kodeA === '') {
        $errors['akashi_klaim'] = 'Kode voucher wajib diisi.';
    } else {
        usleep(600000);
        $res = klaimVoucherAkashi($pdo, $pendaftaranId, $kodeA);
        if ($res['ok']) {
            $_SESSION['akashi_klaim_attempts'] = 0;
            $_SESSION['flash_success'] = $res['pesan'];
            redirect('/portal-santri?step=jalur');
        }
        $_SESSION['akashi_klaim_attempts'] = $attempts + 1;
        $_SESSION['akashi_klaim_at'] = time();
        $errors['akashi_klaim'] = $res['pesan'];
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

// ── Submit cicilan gabungan: satu nominal untuk beberapa item ─
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['step'] ?? '') === 'cicilan-gabungan'
    && in_array($pendaftaran['status'], ['diterima', 'daftar-ulang'], true)) {
    validateCsrf();
    $ids = array_values(array_unique(array_map('intval', (array) ($_POST['ids'] ?? []))));
    $nominalGab = sanitizeFloat($_POST['nominal'] ?? 0);
    $tanggalGab = sanitizeString($_POST['tanggal_bayar'] ?? date('Y-m-d'));
    $metodeGab = sanitizeString($_POST['metode'] ?? 'transfer');
    if (!in_array($metodeGab, ['tunai', 'transfer', 'virtual-account'], true)) $metodeGab = 'transfer';

    if (count($ids) < 1) {
        $errors['gabungan'] = 'Centang minimal satu tagihan.';
    } elseif ($nominalGab <= 0) {
        $errors['gabungan'] = 'Nominal harus lebih dari 0.';
    } else {
        // Ambil item milik pendaftar + sisa per item
        $sisaPerItem = [];
        $totalSisa = 0.0;
        foreach ($ids as $pid) {
            $cek = $pdo->prepare('SELECT id, nominal FROM pembiayaan WHERE id = ? AND pendaftaran_id = ?');
            $cek->execute([$pid, $pendaftaranId]);
            $row = $cek->fetch();
            if (!$row) {
                $errors['gabungan'] = 'Item tagihan tidak valid.';
                break;
            }
            $sum = $pdo->prepare("SELECT COALESCE(SUM(nominal),0) FROM pembiayaan_cicilan WHERE pembiayaan_id = ? AND status = 'verified'");
            $sum->execute([$pid]);
            $sisa = (float) $row['nominal'] - (float) $sum->fetchColumn();
            if ($sisa <= 0) continue; // sudah lunas → lewati
            $sisaPerItem[$pid] = $sisa;
            $totalSisa += $sisa;
        }
        if (empty($errors)) {
            if (empty($sisaPerItem)) {
                $errors['gabungan'] = 'Semua item tercentang sudah lunas.';
            } elseif ($nominalGab > $totalSisa) {
                $errors['gabungan'] = 'Nominal melebihi total sisa (Rp ' . number_format($totalSisa, 0, ',', '.') . ').';
            } else {
                // Distribusi berurutan, dalam satu transaksi + satu batch_id
                $batchId = bin2hex(random_bytes(16));
                try {
                    $pdo->beginTransaction();
                    $sisaNominal = $nominalGab;
                    foreach ($sisaPerItem as $pid => $sisa) {
                        if ($sisaNominal <= 0) break;
                        $bagi = min($sisa, $sisaNominal);
                        $ke = $pdo->prepare('SELECT COALESCE(MAX(angsuran_ke),0)+1 FROM pembiayaan_cicilan WHERE pembiayaan_id = ?');
                        $ke->execute([$pid]);
                        $ins = $pdo->prepare(
                            'INSERT INTO pembiayaan_cicilan
                             (pembiayaan_id, batch_id, angsuran_ke, nominal, tanggal_bayar, metode, status)
                             VALUES (?,?,?,?,?,?,\'pending\')'
                        );
                        $ins->execute([$pid, $batchId, (int) $ke->fetchColumn(), $bagi, $tanggalGab, $metodeGab]);
                        $sisaNominal -= $bagi;
                    }
                    $pdo->commit();
                    $_SESSION['flash_success'] = 'Pembayaran gabungan Rp ' . number_format($nominalGab, 0, ',', '.') . ' dikirim. Menunggu verifikasi admin.';
                    redirect('/portal-santri?step=pembayaran');
                } catch (Throwable $e) {
                    if ($pdo->inTransaction()) $pdo->rollBack();
                    error_log('Cicilan gabungan error: ' . $e->getMessage());
                    $errors['gabungan'] = 'Terjadi kesalahan sistem.';
                }
            }
        }
    }
}

// ── Submit surat & TTD (status diterima/daftar-ulang) ──────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['step'] ?? '') === 'surat-ttd'
    && in_array($pendaftaran['status'], ['diterima', 'daftar-ulang'], true)) {
    validateCsrf();
    $agama = sanitizeString($_POST['agama'] ?? '');
    $kelas = sanitizeString($_POST['kelas'] ?? '');
    $jenjangNow = $pendaftaran['jenjang'] ?? 'smp';

    if (!in_array($agama, agamaOptions(), true)) {
        $errors['agama'] = 'Pilih agama.';
    }
    if (!in_array($kelas, kelasOptions($jenjangNow), true)) {
        $errors['kelas'] = 'Pilih kelas sesuai jenjang (' . ($jenjangNow === 'sma' ? '10-12' : '7-9') . ').';
    }

    // Simpan TTD canvas bila diisi (boleh kosong → surat tampil garis titik-titik)
    $ttdDir = UPLOADS_PATH . '/santri/' . $pendaftaranId;
    $ttdSantriFile = $pendaftaran['ttd_santri'] ?? null;
    $ttdWaliFile = $pendaftaran['ttd_wali'] ?? null;
    if (empty($errors)) {
        $dataSantri = trim($_POST['ttd_santri'] ?? '');
        $dataWali = trim($_POST['ttd_wali'] ?? '');
        if ($dataSantri !== '' && $dataSantri !== 'kosong') {
            $baru = saveSignature($dataSantri, $ttdDir);
            if ($baru === false) {
                $errors['ttd_santri'] = 'Tanda tangan santri tidak valid. Ulangi di kanvas.';
            } else {
                if ($ttdSantriFile) @unlink($ttdDir . '/' . basename($ttdSantriFile));
                $ttdSantriFile = $baru;
            }
        }
        if ($dataWali !== '' && $dataWali !== 'kosong') {
            $baru = saveSignature($dataWali, $ttdDir);
            if ($baru === false) {
                $errors['ttd_wali'] = 'Tanda tangan wali tidak valid. Ulangi di kanvas.';
            } else {
                if ($ttdWaliFile) @unlink($ttdDir . '/' . basename($ttdWaliFile));
                $ttdWaliFile = $baru;
            }
        }
    }

    if (empty($errors)) {
        // Kolom agama/kelas/ttd mungkin belum ada bila migrasi 022 belum jalan
        $kolom = [];
        try {
            $kolom = array_column($pdo->query(
                "SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='pendaftaran'"
            )->fetchAll(PDO::FETCH_NUM), 0);
        } catch (PDOException $e) {
            $kolom = [];
        }
        $set = [];
        $par = [];
        if (in_array('agama', $kolom, true)) { $set[] = 'agama = ?'; $par[] = $agama; }
        if (in_array('kelas', $kolom, true)) { $set[] = 'kelas = ?'; $par[] = $kelas; }
        if (in_array('ttd_santri', $kolom, true)) { $set[] = 'ttd_santri = ?'; $par[] = $ttdSantriFile; }
        if (in_array('ttd_wali', $kolom, true)) { $set[] = 'ttd_wali = ?'; $par[] = $ttdWaliFile; }
        if (!empty($set)) {
            $par[] = $pendaftaranId;
            $pdo->prepare('UPDATE pendaftaran SET ' . implode(', ', $set) . ' WHERE id = ?')->execute($par);
            $pendaftaran['agama'] = $agama;
            $pendaftaran['kelas'] = $kelas;
            $pendaftaran['ttd_santri'] = $ttdSantriFile;
            $pendaftaran['ttd_wali'] = $ttdWaliFile;
        }
        $_SESSION['flash_success'] = 'Data surat & tanda tangan tersimpan.';
        redirect('/portal-santri?step=surat-ttd');
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

$activePage      = match($stepSekarang) {
    'pembayaran' => 'pembayaran',
    'surat-ttd'  => 'dokumen-santri',
    'berkas-wajib', 'berkas-jalur', 'berkas-pelengkap' => 'berkas-santri',
    default      => 'portal-santri',
};
$pageTitle       = 'Portal Santri — ' . APP_NAME;
$pageDescription = 'Portal calon peserta PSB Pondok Pesantren Ash-Shiddiq.';
$pageCanonical   = BASE_URL . '/portal-santri';
$bodyClass       = 'portal-santri-page';

$extraHead = '<link rel="stylesheet" href="' . BASE_URL . '/assets/css/portal.css?v=' . ASSET_VERSION . '">'
    . '<script src="' . BASE_URL . '/assets/js/kompres-gambar.js?v=' . ASSET_VERSION . '" defer></script>';
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
    <?php if (in_array($pendaftaran['status'], ['diterima', 'daftar-ulang'], true)): ?>
      <?php $kontakPanitia = $pdo->query("SELECT value FROM pengaturan WHERE key_name='kontak_whatsapp'")->fetchColumn() ?: '6281234567890'; ?>
      <div class="portal-info-box" style="border-left:4px solid var(--green-deep);">
        <h2>🎉 Selamat, Anda DITERIMA!</h2>
        <p>Ananda <strong><?= e($pendaftaran['nama_lengkap']) ?></strong> (<?= e($pendaftaran['nomor_daftar']) ?>) diterima di <?= e($labelJenjang[$pendaftaran['jenjang']] ?? $pendaftaran['jenjang']) ?>.</p>
        <p><strong>Langkah selanjutnya:</strong></p>
        <ol style="margin:8px 0 8px 20px;font-size:14px;line-height:1.8;">
          <li><a href="?step=pembayaran" class="btn-link">Bayar / cicil tagihan</a></li>
          <li><a href="?step=surat-ttd" class="btn-link">Isi agama + kelas, bubuhkan TTD, cetak 3 surat</a></li>
          <li><a href="?step=berkas-pelengkap" class="btn-link">Lengkapi berkas susulan (opsional)</a></li>
          <li>Hubungi panitia untuk jadwal daftar ulang</li>
        </ol>
        <p>
          <a href="https://wa.me/<?= e(preg_replace('/\D/', '', $kontakPanitia)) ?>?text=<?= rawurlencode('Assalamualaikum, saya ' . ($pendaftaran['nama_lengkap'] ?? '') . ' (' . ($pendaftaran['nomor_daftar'] ?? '') . '). Saya sudah DITERIMA dan ingin info daftar ulang.') ?>" target="_blank" rel="noopener" class="btn-primary" style="display:inline-block;padding:10px 22px;font-size:13px;text-decoration:none;">💬 Hubungi Panitia via WA</a>
          <a href="<?= BASE_URL ?>/profil-santri" class="btn-outline" style="display:inline-block;padding:10px 22px;font-size:13px;text-decoration:none;margin-left:8px;">Lihat Profil</a>
        </p>
      </div>
    <?php else: ?>
    <div class="portal-info-box">
      <h2>Berkas Terkirim</h2>
      <p>Seluruh tahapan formulir telah selesai. Panitia akan memverifikasi dan menghubungi Anda.</p>
      <p>Status saat ini: <strong><?= e($statusLabel) ?></strong></p>
      <?php if ($pendaftaran['gelombang_tanggal_tes'] ?? false): ?>
        <p>Jadwal Tes Pemetaan: <strong><?= date('d M Y', strtotime($pendaftaran['tanggal_tes_gelombang'])) ?></strong></p>
      <?php endif; ?>
    </div>
    <?php endif; ?>
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

        <?php
      // Voucher Akashi valid yang menempel pada pendaftaran ini
      $cekAk = $pdo->prepare("SELECT juara, nominal_potongan FROM voucher_akashi WHERE pendaftaran_id = ? LIMIT 1");
      $cekAk->execute([$pendaftaranId]);
      $akVoucher = $cekAk->fetch();
      $akashiTerbuka = (bool) $akVoucher;
      $akashiCfg = getPotonganAkashi(getJalurPotonganAdmin($pdo));
      $akashiLabel = ['juara-1' => 'Juara 1', 'juara-2' => 'Juara 2', 'juara-3' => 'Juara 3'];
      ?>
        <div class="jalur-grid">
          <?php foreach ($labelJalur as $val => $lbl): ?>
            <?php if (in_array($val, jalurTersembunyi(), true)) continue; ?>
            <label class="jalur-radio-item">
              <input type="radio" name="jalur" value="<?= e($val) ?>"
                     <?= ($akashiTerbuka ? $val === 'prestasi' : (($pendaftaran['jalur'] ?? 'reguler') === $val)) ? 'checked' : '' ?>
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
                       <?= (($pendaftaran['jalur_detail'] ?? '') === $val || ($jalurKey === 'prestasi' && $val === 'internal' && $akashiTerbuka)) ? 'checked' : '' ?>>
                <?php if ($jalurKey === 'prestasi' && $val === 'internal'): ?>
                  <?= e($opt['label']) ?>
                  <?php if (!$akashiTerbuka): ?>
                    <small style="color:var(--text-light);">*perlu kode voucher</small>
                  <?php endif; ?>
                <?php else: ?>
                  <?= e($opt['label']) ?> &mdash; potongan <?= (int)$opt['potongan'] ?>%
                <?php endif; ?>
              </label>
            <?php endforeach; ?>
            <?php if ($jalurKey === 'prestasi' && $akashiTerbuka): ?>
              <p class="portal-note" style="margin-top:8px;">
                Voucher <strong><?= e($akVoucher['juara'] ? ($akashiLabel[$akVoucher['juara']] ?? $akVoucher['juara']) : '') ?></strong>
                — potongan ADM awal <strong>Rp <?= number_format((float) $akVoucher['nominal_potongan'], 0, ',', '.') ?></strong>.
              </p>
            <?php endif; ?>
            <?php if ($jalurKey === 'prestasi' && !$akashiTerbuka): ?>
              <div id="akashiClaimBox" <?= !empty($errors['akashi_klaim']) ? '' : 'hidden' ?> style="margin-top:12px;padding:14px;background:#faf9f6;border:1px dashed #d9d2c5;border-radius:8px;">
                <p class="portal-note" style="margin-bottom:8px;">Masukkan kode voucher juara Akashi (tanpa NISN):</p>
                <div style="display:flex;gap:8px;flex-wrap:wrap;">
                  <input type="text" id="akashiKodeInput" class="form-control" placeholder="AKS-XXXXXXXXXX"
                         style="flex:1;min-width:200px;text-transform:uppercase;" autocomplete="off">
                  <button type="button" id="akashiGunakanBtn" class="btn-primary">Gunakan Kode</button>
                </div>
                <p class="field-error" id="akashiClaimError" <?= !empty($errors['akashi_klaim']) ? '' : 'hidden' ?>><?= e($errors['akashi_klaim'] ?? '') ?></p>
              </div>
            <?php endif; ?>
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
      // Disable radio di wrap yang bukan active (jangan uncheck — browser handle otomatis)
      document.querySelectorAll('.jalur-detail-wrap').forEach(el => {
        if (el.id !== 'detail-' + jalur) {
          el.querySelectorAll('input[name="jalur_detail"]').forEach(r => { r.disabled = true; });
        }
      });
      // Tampilkan kotak klaim Akashi kalau "Tingkat Internal (Lomba Akashi)" dipilih
      toggleAkashiClaim();
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
    function toggleAkashiClaim() {
      const internal = document.querySelector('input[name="jalur_detail"][value="internal"]');
      const box = document.getElementById('akashiClaimBox');
      if (!internal || !box) return;
      box.hidden = !internal.checked;
    }

    // Init on load
    document.addEventListener('DOMContentLoaded', function () {
      psbSyncJalur();
      document.querySelectorAll('input[name="jalur_detail"]').forEach(r => {
        r.addEventListener('change', function () {
          toggleAkashiClaim();
          const checked = document.querySelector('input[name="jalur"]:checked');
          if (checked) hitungSimulasi(checked.value);
        });
      });
      // Kalau klaim Akashi sebelumnya gagal, buka lagi kartu Prestasi + Tingkat Internal
      const akErr = document.getElementById('akashiClaimError');
      if (akErr && !akErr.hidden) {
        const prestasi = document.querySelector('input[name="jalur"][value="prestasi"]');
        const internal = document.querySelector('input[name="jalur_detail"][value="internal"]');
        if (prestasi && internal) {
          prestasi.checked = true;
          internal.checked = true;
          psbSyncJalur();
        }
      }
      // Klaim voucher Akashi via AJAX (tanpa submit ulang form jalur)
      const akBtn = document.getElementById('akashiGunakanBtn');
      if (akBtn) {
        akBtn.addEventListener('click', function () {
          const inp = document.getElementById('akashiKodeInput');
          const err = document.getElementById('akashiClaimError');
          const kode = (inp.value || '').trim().toUpperCase();
          inp.value = kode;
          if (!kode) {
            err.textContent = 'Kode voucher wajib diisi.';
            err.hidden = false;
            inp.focus();
            return;
          }
          err.hidden = true;
          const fd = new FormData();
          fd.append('csrf_token', document.querySelector('input[name="csrf_token"]').value);
          fd.append('step', 'akashi-klaim');
          fd.append('kode_voucher', kode);
          akBtn.disabled = true;
          akBtn.textContent = 'Memeriksa...';
          fetch(location.pathname + location.search, { method: 'POST', body: fd, credentials: 'same-origin' })
            .then(function (resp) {
              if (resp.redirected) { location.reload(); return null; }
              return resp.text();
            })
            .then(function (html) {
              if (html === null) return;
              const doc = new DOMParser().parseFromString(html, 'text/html');
              const el = doc.getElementById('akashiClaimError');
              err.textContent = el && el.textContent.trim() ? el.textContent.trim() : 'Kode voucher tidak valid.';
              err.hidden = false;
              akBtn.disabled = false;
              akBtn.textContent = 'Gunakan Kode';
              inp.focus();
            })
            .catch(function () {
              err.textContent = 'Terjadi kesalahan jaringan. Silakan coba lagi.';
              err.hidden = false;
              akBtn.disabled = false;
              akBtn.textContent = 'Gunakan Kode';
            });
        });
      }
    });
    </script>

  <?php elseif ($stepSekarang === 'berkas-wajib'): ?>
    <section class="portal-card">
      <h2>Upload Berkas Wajib</h2>
      <p class="portal-note">Upload 4 berkas di bawah ini. Gambar (JPG/PNG/WEBP) otomatis dikompres ke ≤1MB; PDF maks 5MB. Ijazah/SKL dilengkapi setelah diterima.</p>
      <input type="hidden" id="csrfGlobal" value="<?= generateCsrfToken() ?>">

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

  <?php elseif ($stepSekarang === 'berkas-jalur'): ?>
    <section class="portal-card">
      <h2>Upload Berkas Jalur</h2>
      <p class="portal-note">
        Jalur Anda saat ini: <strong><?= e($labelJalur[$pendaftaran['jalur']] ?? '—') ?></strong>.
        Gambar otomatis dikompres ke ≤1MB; PDF maks 5MB.
      </p>
      <input type="hidden" id="csrfGlobal" value="<?= generateCsrfToken() ?>">

      <?php
      // Template dokumen yang bisa di-download per jalur
      $templatePerJalur = [
        'kaderisasi'   => [
          ['slug' => 'mou-kaderisasi', 'label' => 'MOU Kaderisasi'],
          ['slug' => 'rekomendasi-kaderisasi', 'label' => 'Surat Rekomendasi Kaderisasi'],
        ],
        'alumni-sdmua' => [
          ['slug' => 'rekomendasi-alumni', 'label' => 'Surat Rekomendasi Alumni'],
        ],
        'dhuafa'       => [
          ['slug' => 'rekomendasi-dhuafa', 'label' => 'Surat Rekomendasi Dhuafa'],
          ['slug' => 'pernyataan-dhuafa', 'label' => 'Surat Pernyataan Dhuafa'],
        ],
      ];
      $templates = $templatePerJalur[$pendaftaran['jalur']] ?? [];
      ?>
      <?php if ($pendaftaran['jalur'] === 'kaderisasi'): ?>
      <div class="portal-info-box" style="margin-bottom:16px;">
        <h2>Langkah Jalur Kaderisasi</h2>
        <p>1. Download template di bawah, isi, tanda tangan, lalu upload ulang pada slot di bawah ini.</p>
        <p>2. Wajib mengikuti <strong>Tes Pemetaan</strong> sesuai jadwal gelombang.</p>
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

      <?php if (!empty($templates)): ?>
      <div class="portal-info-box" style="margin-bottom:16px;">
        <h2>Template Dokumen</h2>
        <p>
          <?php foreach ($templates as $i => $tpl): ?>
            <?= $i > 0 ? '<br>' : '' ?>
            <a href="<?= BASE_URL ?>/download-template?slug=<?= e($tpl['slug']) ?>" target="_blank" rel="noopener" class="btn-link">Download <?= e($tpl['label']) ?></a>
          <?php endforeach; ?>
        </p>
        <p class="portal-note">Setelah terbuka, gunakan tombol Download / Print di halaman dokumen.</p>
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
      <p>Centang beberapa tagihan lalu bayar sekaligus, atau bayar per item di bawah.</p>

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

      <?php
      $rekeningTujuan = $pdo->query("SELECT value FROM pengaturan WHERE key_name='rekening_pondok'")->fetchColumn()
          ?: 'BCA 123-456-7890 a.n. Pondok Pesantren Ash-Shiddiq';
      ?>
      <?php if (empty($itemList)): ?>
        <p style="color:#999;">Belum ada tagihan. Tagihan akan muncul setelah Panitia melakukan snapshot final.</p>
      <?php else: ?>
        <form method="post" id="formGabungan" class="tagihan-item" style="border-color:var(--green-deep);">
          <input type="hidden" name="csrf_token" value="<?= generateCsrfToken() ?>">
          <input type="hidden" name="step" value="cicilan-gabungan">
          <h4 style="font-size:15px;margin-bottom:4px;">Bayar Gabungan</h4>
          <p class="portal-note" style="margin-bottom:12px;">Centang tagihan di bawah, total sisa muncul otomatis. Nominal boleh kurang dari total (cicilan).</p>
          <?php if (!empty($errors['gabungan'])): ?><p class="field-error"><?= e($errors['gabungan']) ?></p><?php endif; ?>
          <div class="form-row">
            <div class="form-group">
              <label>Total Sisa Tercentang</label>
              <div id="gabTotal" style="font-size:18px;font-weight:800;color:var(--green-deep);">Rp 0</div>
            </div>
            <div class="form-group">
              <label>Nominal Bayar (Rp)</label>
              <input type="number" name="nominal" id="gabNominal" min="1" required class="form-control" placeholder="Ketik nominal">
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
              <button type="submit" class="btn-primary">Bayar Gabungan</button>
            </div>
          </div>
          <p class="form-note">Rekening tujuan: <strong><?= e($rekeningTujuan) ?></strong></p>
        </form>
        <script>
        (function () {
          var totalEl = document.getElementById('gabTotal');
          var nomEl = document.getElementById('gabNominal');
          if (!totalEl || !nomEl) return;
          function rp(n) { return 'Rp ' + Math.round(n).toString().replace(/\B(?=(\d{3})+(?!\d))/g, '.'); }
          function hitung() {
            var t = 0;
            document.querySelectorAll('.cek-item:checked').forEach(function (c) {
              t += parseFloat(c.dataset.sisa || '0');
            });
            totalEl.textContent = rp(t);
            nomEl.max = Math.floor(t);
            nomEl.placeholder = t > 0 ? 'Maks ' + rp(t) : 'Ketik nominal';
          }
          document.querySelectorAll('.cek-item').forEach(function (c) {
            c.addEventListener('change', hitung);
          });
          hitung();
        })();
        </script>
        <?php foreach ($itemList as $it):
            $sisa = (float)$it['nominal'] - ($sumVerified[$it['id']] ?? 0);
            $lunas = $sisa <= 0;
            $cicilanList = $pdo->prepare("SELECT * FROM pembiayaan_cicilan WHERE pembiayaan_id=? ORDER BY id DESC");
            $cicilanList->execute([$it['id']]);
            $cicilanAll = $cicilanList->fetchAll();
        ?>
        <div class="tagihan-item">
          <div class="tagihan-head">
            <div style="display:flex;gap:10px;align-items:flex-start;">
              <?php if (!$lunas): ?>
              <input type="checkbox" class="cek-item" form="formGabungan" name="ids[]" value="<?= (int)$it['id'] ?>"
                     data-sisa="<?= (float)$sisa ?>" style="width:18px;height:18px;margin-top:4px;" aria-label="Pilih <?= e($it['nama']) ?>">
              <?php endif; ?>
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
          </div>

          <?php if (!empty($cicilanAll)): ?>
            <table class="cicilan-table">
              <thead><tr><th>#</th><th>Nominal</th><th>Tanggal</th><th>Metode</th><th>Status</th></tr></thead>
              <tbody>
                <?php foreach ($cicilanAll as $c): ?>
                <tr>
                  <td><?= (int)$c['angsuran_ke'] ?></td>
                  <td>Rp <?= number_format((float)$c['nominal'], 0, ',', '.') ?>
                    <?php if (!empty($c['batch_id'])): ?><small style="color:#0d7a4a;"> (gabungan)</small><?php endif; ?>
                  </td>
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

  <?php if ($stepSekarang === 'surat-ttd' && in_array($pendaftaran['status'], ['diterima','daftar-ulang'], true)): ?>
    <?php
    $daftarSurat = dokumenKelulusanList($pendaftaran['jenjang'] ?? 'smp');
    $opsiKelas = kelasOptions($pendaftaran['jenjang'] ?? 'smp');
    ?>
    <section class="portal-card">
      <h2>Surat &amp; Tanda Tangan</h2>
      <p class="portal-note">Lengkapi agama + kelas, bubuhkan 2 tanda tangan digital, lalu cetak 3 surat. Tahun ajaran terisi otomatis.</p>

      <form method="post" id="formSuratTtd" novalidate>
        <input type="hidden" name="csrf_token" value="<?= generateCsrfToken() ?>">
        <input type="hidden" name="step" value="surat-ttd">
        <div class="form-row">
          <div class="form-group">
            <label>Agama</label>
            <select name="agama" class="form-control" id="agamaSelect" required>
              <option value="">-- Pilih Agama --</option>
              <?php foreach (agamaOptions() as $ag): ?>
                <option value="<?= e($ag) ?>" <?= ($pendaftaran['agama'] ?? '') === $ag ? 'selected' : '' ?>><?= e($ag) ?></option>
              <?php endforeach; ?>
            </select>
            <?php if (!empty($errors['agama'])): ?><p class="field-error"><?= e($errors['agama']) ?></p><?php endif; ?>
          </div>
          <div class="form-group">
            <label>Kelas (<?= ($pendaftaran['jenjang'] ?? '') === 'sma' ? 'SMA: 10-12' : 'SMP: 7-9' ?>)</label>
            <select name="kelas" class="form-control" required>
              <option value="">-- Pilih Kelas --</option>
              <?php foreach ($opsiKelas as $kl): ?>
                <option value="<?= e($kl) ?>" <?= ($pendaftaran['kelas'] ?? '') === $kl ? 'selected' : '' ?>><?= e($kl) ?></option>
              <?php endforeach; ?>
            </select>
            <?php if (!empty($errors['kelas'])): ?><p class="field-error"><?= e($errors['kelas']) ?></p><?php endif; ?>
          </div>
        </div>

        <div class="form-row">
          <div class="form-group">
            <label>Tanda Tangan Santri</label>
            <canvas id="ttdSantri" width="400" height="150" style="border:1.5px dashed #ccc;border-radius:6px;width:100%;touch-action:none;background:#fff;"></canvas>
            <input type="hidden" name="ttd_santri" id="ttdSantriData" value="kosong">
            <div style="display:flex;gap:8px;margin-top:8px;">
              <button type="button" class="btn-outline" style="padding:6px 14px;font-size:12px;" onclick="bersihTtd('ttdSantri','ttdSantriData')">Hapus</button>
              <?php if (!empty($pendaftaran['ttd_santri'])): ?><span class="berkas-status success">✓ Tersimpan</span><?php endif; ?>
            </div>
            <?php if (!empty($errors['ttd_santri'])): ?><p class="field-error"><?= e($errors['ttd_santri']) ?></p><?php endif; ?>
          </div>
          <div class="form-group">
            <label>Tanda Tangan Wali</label>
            <canvas id="ttdWali" width="400" height="150" style="border:1.5px dashed #ccc;border-radius:6px;width:100%;touch-action:none;background:#fff;"></canvas>
            <input type="hidden" name="ttd_wali" id="ttdWaliData" value="kosong">
            <div style="display:flex;gap:8px;margin-top:8px;">
              <button type="button" class="btn-outline" style="padding:6px 14px;font-size:12px;" onclick="bersihTtd('ttdWali','ttdWaliData')">Hapus</button>
              <?php if (!empty($pendaftaran['ttd_wali'])): ?><span class="berkas-status success">✓ Tersimpan</span><?php endif; ?>
            </div>
            <?php if (!empty($errors['ttd_wali'])): ?><p class="field-error"><?= e($errors['ttd_wali']) ?></p><?php endif; ?>
          </div>
        </div>

        <div class="portal-actions">
          <a href="?step=pembayaran" class="btn-outline">&larr; Pembayaran</a>
          <button type="submit" class="btn-primary">Simpan Data &amp; TTD</button>
        </div>
      </form>

      <script>
      function pasangTtd(idCanvas, idInput) {
        var c = document.getElementById(idCanvas);
        if (!c) return;
        var ctx = c.getContext('2d');
        var gambar = false;
        function pos(e) {
          var r = c.getBoundingClientRect();
          var t = (e.touches && e.touches[0]) || e;
          return [(t.clientX - r.left) * (c.width / r.width), (t.clientY - r.top) * (c.height / r.height)];
        }
        var jalan = false, px = 0, py = 0;
        function mulai(e) { e.preventDefault(); jalan = true; var p = pos(e); px = p[0]; py = p[1]; }
        function gerak(e) {
          if (!jalan) return;
          e.preventDefault();
          var p = pos(e);
          ctx.strokeStyle = '#0f2318'; ctx.lineWidth = 2; ctx.lineCap = 'round';
          ctx.beginPath(); ctx.moveTo(px, py); ctx.lineTo(p[0], p[1]); ctx.stroke();
          px = p[0]; py = p[1]; gambar = true;
        }
        function selesai() { jalan = false; }
        c.addEventListener('mousedown', mulai);
        c.addEventListener('mousemove', gerak);
        document.addEventListener('mouseup', selesai);
        c.addEventListener('touchstart', mulai, { passive: false });
        c.addEventListener('touchmove', gerak, { passive: false });
        c.addEventListener('touchend', selesai);
        document.getElementById('formSuratTtd').addEventListener('submit', function () {
          document.getElementById(idInput).value = gambar ? c.toDataURL('image/png') : 'kosong';
        });
      }
      function bersihTtd(idCanvas, idInput) {
        var c = document.getElementById(idCanvas);
        c.getContext('2d').clearRect(0, 0, c.width, c.height);
        document.getElementById(idInput).value = 'kosong';
      }
      pasangTtd('ttdSantri', 'ttdSantriData');
      pasangTtd('ttdWali', 'ttdWaliData');
      </script>

      <hr style="margin:24px 0;border:none;border-top:1px solid var(--cream-dark);">
      <h3 style="font-size:15px;margin-bottom:12px;">Cetak Dokumen</h3>
      <div style="display:flex;gap:10px;flex-wrap:wrap;">
        <?php foreach ($daftarSurat as $tipe => $label): ?>
          <a href="<?= BASE_URL ?>/dokumen-santri?tipe=<?= e($tipe) ?>" target="_blank" rel="noopener" class="btn-outline" style="padding:10px 18px;font-size:13px;">🖨 <?= e($label) ?></a>
        <?php endforeach; ?>
      </div>
      <p class="portal-note" style="margin-top:10px;">TTD yang sudah disimpan otomatis tampil di surat. Kosong = garis titik-titik.</p>

      <div class="portal-actions">
        <a href="?step=berkas-pelengkap" class="btn-primary">Lanjut: Berkas Pelengkap &rarr;</a>
      </div>
    </section>

  <?php endif; ?>

  <?php if ($stepSekarang === 'berkas-pelengkap' && in_array($pendaftaran['status'], ['diterima','daftar-ulang'], true)): ?>
    <section class="portal-card">
      <h2>Berkas Pelengkap</h2>
      <p class="portal-note">Dilengkapi setelah diterima. Ijazah/SKL boleh menyusul setelah lulus. Format: JPG/PNG/WEBP/PDF, maks 5MB.</p>
      <input type="hidden" id="csrfGlobal" value="<?= generateCsrfToken() ?>">

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

  <?php endif; ?>
</div>
</main>
