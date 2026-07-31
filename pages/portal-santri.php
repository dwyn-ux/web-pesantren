<?php
// ── Proteksi akses ───────────────────────────────────────────
if (empty($_SESSION['santri_id'])) {
    setFlash('info', 'Silakan login dengan akun pendaftaran.');
    redirect('/login-santri');
}

$pdo = getDB();
$id  = (int) $_SESSION['santri_id'];

$pendaftar = $pdo->prepare('SELECT * FROM pendaftaran WHERE id=?');
$pendaftar->execute([$id]);
$pendaftar = $pendaftar->fetch();
if (!$pendaftar) {
    unset($_SESSION['santri_id']);
    redirect('/login-santri');
}

// Pastikan snapshot pembiayaan sinkron dengan tarif admin (kecuali sudah terkunci)
syncPembiayaan($pdo, $id, $pendaftar['jenis_kelamin']);

$itemRows = $pdo->prepare('SELECT * FROM pembiayaan WHERE pendaftaran_id=? ORDER BY urutan, id');
$itemRows->execute([$id]);
$itemRows = $itemRows->fetchAll();

$pendaftaranItem = null;
$administrasiItems = [];
$wakafItems = [];
$syahriyahItems = [];
$laundryItem = null;
$infakItem = null;
foreach ($itemRows as $it) {
    switch ($it['jenis']) {
        case 'pendaftaran': $pendaftaranItem = $it; break;
        case 'administrasi': $administrasiItems[] = $it; break;
        case 'wakaf':        $wakafItems[] = $it; break;
        case 'syahriyah':    $syahriyahItems[] = $it; break;
        case 'laundry':      $laundryItem = $it; break;
        case 'infak':        $infakItem = $it; break;
    }
}

// Status alur
$pendaftaranSelesai = $pendaftaranItem && ($pendaftaranItem['gratis'] || in_array($pendaftaranItem['status'], ['lunas', 'gratis'], true));
$kesanggupanSelesai = (int) $pendaftar['kesanggupan_setuju'] === 1;
$berkasBuka = $pendaftaranSelesai && $kesanggupanSelesai;

// Pengaturan
$settings = [];
$q = $pdo->query("SELECT key_name, value FROM pengaturan WHERE key_name IN ('rekening_pembayaran','kontak_whatsapp')");
foreach ($q->fetchAll() as $r) $settings[$r['key_name']] = $r['value'];
$rekening = $settings['rekening_pembayaran'] ?? '';

$errors = [];

// ── Proses POST ──────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    validateCsrf();
    $action = sanitizeString($_POST['action'] ?? '');

    // 1) Bayar biaya pendaftaran (upload bukti transfer)
    if ($action === 'bayar_pendaftaran' && $pendaftaranItem && !$pendaftaranSelesai) {
        $up = validateUpload($_FILES['berkas'], ['pdf', 'jpg', 'jpeg', 'png'], ['application/pdf', 'image/jpeg', 'image/png'], 10485760);
        if ($up) {
            $errors = array_merge($errors, $up);
        } else {
            $file = saveUpload($_FILES['berkas'], UPLOADS_PATH . '/santri');
            $mime = detectMimeType(UPLOADS_PATH . '/santri/' . $file);
            $pdo->prepare("INSERT INTO berkas_santri (pendaftaran_id, pembiayaan_id, jenis, nama_file, nama_asli, mime_type) VALUES (?,?,'bukti-bayar',?,?,?)")
                ->execute([$id, $pendaftaranItem['id'], $file, basename($_FILES['berkas']['name']), $mime]);
            $pdo->prepare('UPDATE pembiayaan SET status=? WHERE id=?')->execute(['menunggu', $pendaftaranItem['id']]);
            setFlash('success', 'Bukti pembayaran dikirim. Tunggu verifikasi admin sebelum lanjut.');
            redirect('/portal-santri');
        }
    }

    // 2) Kesanggupan administrasi awal + tanda tangan
    if ($action === 'kesanggupan') {
        $pilihAdministrasi = sanitizeInt($_POST['pilih_administrasi'] ?? 0);
        $pilihWakaf        = sanitizeInt($_POST['pilih_wakaf'] ?? 0);
        $pilihSyahriyah    = sanitizeInt($_POST['pilih_syahriyah'] ?? 0);
        $setuju            = !empty($_POST['kesanggupan_setuju']);
        $signData          = $_POST['tanda_tangan'] ?? '';

        $adminIds = array_map(fn($i) => (int) $i['id'], $administrasiItems);
        $wakafIds = array_map(fn($i) => (int) $i['id'], $wakafItems);
        $syahriyahIds = array_map(fn($i) => (int) $i['id'], $syahriyahItems);

        if ($administrasiItems && !in_array($pilihAdministrasi, $adminIds, true)) {
            $errors[] = 'Pilih salah satu model administrasi.';
        }
        if ($wakafItems && !in_array($pilihWakaf, $wakafIds, true)) {
            $errors[] = 'Pilih salah satu pilihan wakaf.';
        }
        if ($syahriyahItems && !in_array($pilihSyahriyah, $syahriyahIds, true)) {
            $errors[] = 'Pilih salah satu pilihan syahriyah.';
        }
        if (!$setuju) {
            $errors[] = 'Centang kesanggupan membayar terlebih dahulu.';
        }
        if ($signData === '') {
            $errors[] = 'Tanda tangan wajib diisi.';
        }

        if (empty($errors)) {
            $signFile = saveSignature($signData, UPLOADS_PATH . '/santri');
            if (!$signFile) {
                $errors[] = 'Gambar tanda tangan tidak valid. Coba gambar ulang.';
            } else {
                if ($administrasiItems) {
                    $pdo->prepare('UPDATE pembiayaan SET dipilih=0, kesanggupan=0 WHERE pendaftaran_id=? AND jenis="administrasi"')->execute([$id]);
                    $pdo->prepare('UPDATE pembiayaan SET dipilih=1, kesanggupan=1 WHERE id=?')->execute([$pilihAdministrasi]);
                }
                if ($wakafItems) {
                    $pdo->prepare('UPDATE pembiayaan SET dipilih=0, kesanggupan=0 WHERE pendaftaran_id=? AND jenis="wakaf"')->execute([$id]);
                    $pdo->prepare('UPDATE pembiayaan SET dipilih=1, kesanggupan=1 WHERE id=?')->execute([$pilihWakaf]);
                }
                if ($syahriyahItems) {
                    $pdo->prepare('UPDATE pembiayaan SET dipilih=0, kesanggupan=0 WHERE pendaftaran_id=? AND jenis="syahriyah"')->execute([$id]);
                    $pdo->prepare('UPDATE pembiayaan SET dipilih=1, kesanggupan=1 WHERE id=?')->execute([$pilihSyahriyah]);
                }
                if ($laundryItem) {
                    $pdo->prepare('UPDATE pembiayaan SET kesanggupan=1 WHERE id=?')->execute([$laundryItem['id']]);
                }
                if ($infakItem) {
                    $pdo->prepare('UPDATE pembiayaan SET kesanggupan=1 WHERE id=?')->execute([$infakItem['id']]);
                }
                $pdo->prepare('UPDATE pendaftaran SET kesanggupan_setuju=1, kesanggupan_sign=?, kesanggupan_at=NOW() WHERE id=?')
                    ->execute([$signFile, $id]);
                setFlash('success', 'Kesanggupan disimpan. Silakan lengkapi berkas pendaftaran.');
                redirect('/portal-santri');
            }
        }
    }

    // 3) Upload berkas pendaftaran
    if ($action === 'upload_document') {
        if (!$berkasBuka) {
            $errors[] = 'Pemberkasan dibuka setelah pembayaran & kesanggupan selesai.';
        } else {
            $jenis = sanitizeString($_POST['jenis'] ?? '');
            $allowed = ['kartu-keluarga', 'akta-lahir', 'ktp-ortu', 'foto', 'ijazah', 'sertifikat-tka', 'lainnya'];
            if (!in_array($jenis, $allowed, true)) {
                $errors[] = 'Jenis berkas tidak valid.';
            } else {
                $up = validateUpload($_FILES['berkas'], ['pdf', 'jpg', 'jpeg', 'png'], ['application/pdf', 'image/jpeg', 'image/png'], 10485760);
                if ($up) {
                    $errors = array_merge($errors, $up);
                } else {
                    $file = saveUpload($_FILES['berkas'], UPLOADS_PATH . '/santri');
                    $mime = detectMimeType(UPLOADS_PATH . '/santri/' . $file);
                    $pdo->prepare('INSERT INTO berkas_santri (pendaftaran_id, jenis, nama_file, nama_asli, mime_type) VALUES (?,?,?,?,?)')
                        ->execute([$id, $jenis, $file, basename($_FILES['berkas']['name']), $mime]);
                    setFlash('success', 'Berkas berhasil diunggah.');
                    redirect('/portal-santri');
                }
            }
        }
    }
}

// Muat ulang data setelah POST
$pendaftar = $pdo->prepare('SELECT * FROM pendaftaran WHERE id=?');
$pendaftar->execute([$id]);
$pendaftar = $pendaftar->fetch();
$pendaftaranSelesai = $pendaftaranItem && ($pendaftaranItem['gratis'] || in_array($pendaftaranItem['status'], ['lunas', 'gratis'], true));
$kesanggupanSelesai = (int) $pendaftar['kesanggupan_setuju'] === 1;
$berkasBuka = $pendaftaranSelesai && $kesanggupanSelesai;

$berkasRows = $pdo->prepare('SELECT * FROM berkas_santri WHERE pendaftaran_id=? ORDER BY created_at DESC');
$berkasRows->execute([$id]);
$berkasAll = $berkasRows->fetchAll();
$paymentFiles = array_values(array_filter($berkasAll, fn($f) => $f['jenis'] === 'bukti-bayar'));
$documentFiles = array_values(array_filter($berkasAll, fn($f) => $f['jenis'] !== 'bukti-bayar'));

// Hitung kelengkapan berkas wajib
$wajibJenis = berkasWajib();
$wajibTerisi = [];
foreach ($berkasAll as $f) {
    if (in_array($f['jenis'], $wajibJenis, true)) $wajibTerisi[$f['jenis']] = true;
}

$activePage = 'psb';
$pageTitle  = 'Portal Santri | ' . APP_NAME;
$pageDescription = 'Pembayaran, kesanggupan, dan pemberkasan calon santri.';
$pageCanonical = BASE_URL . '/portal-santri';
?>
<main class="page-section portal-page"><div class="container"><div class="portal-head"><div><small><?= e($pendaftar['nomor_induk'] ?: $pendaftar['nomor_daftar']) ?></small><h1>Assalamu'alaikum, <?= e($pendaftar['nama_lengkap']) ?></h1></div><a class="btn-outline" href="<?= BASE_URL ?>/login-santri?logout=1">Keluar</a></div>

<?php if ($errors): ?><div class="flash-message flash-error"><?= e(implode(' ', $errors)) ?></div><?php endif; ?>

<div class="portal-progress">
    <div class="progress-step active"><span>1</span><strong>Pendaftaran</strong></div>
    <div class="progress-step <?= $pendaftaranSelesai ? 'active' : '' ?>"><span>2</span><strong>Pembiayaan</strong></div>
    <div class="progress-step <?= $berkasBuka ? 'active' : '' ?>"><span>3</span><strong>Pemberkasan</strong></div>
</div>

<div class="portal-full">

    <!-- ── 1. BIAYA PENDAFTARAN ───────────────────────────────── -->
    <section class="portal-card">
        <h2>1. Biaya Pendaftaran</h2>
        <?php if ($pendaftaranItem): ?>
            <div class="cost-option">
                <span style="flex:1"><strong>Biaya Pendaftaran</strong><br>
                    <?php if ($pendaftaranItem['gratis']): ?>
                        <small class="price-gratis">GRATIS</small>
                    <?php else: ?>
                        <?php if ($pendaftaranItem['harga_diskon'] !== null && (float) $pendaftaranItem['harga_diskon'] < (float) $pendaftaranItem['harga_asli']): ?>
                            <small><s><?= formatRupiah((float) $pendaftaranItem['harga_asli']) ?></s> &nbsp; <strong><?= formatRupiah((float) $pendaftaranItem['nominal']) ?></strong></small>
                        <?php else: ?>
                            <small><?= formatRupiah((float) $pendaftaranItem['nominal']) ?></small>
                        <?php endif; ?>
                    <?php endif; ?>
                </span>
                <span class="badge badge-<?= in_array($pendaftaranItem['status'], ['lunas', 'gratis'], true) ? 'green' : ($pendaftaranItem['status'] === 'menunggu' ? 'gold' : 'red') ?>"><?= e(pembiayaanStatusLabel($pendaftaranItem['status'])) ?></span>
            </div>

            <?php if ($pendaftaranItem['gratis']): ?>
                <p class="payment-success" style="margin:0;text-align:left;">Biaya pendaftaran dibebaskan (gratis). Tidak perlu membayar.</p>
            <?php elseif ($pendaftaranItem['status'] === 'lunas'): ?>
                <p class="payment-success" style="margin:0;text-align:left;">Pembayaran pendaftaran lunas.</p>
            <?php elseif ($pendaftaranItem['status'] === 'menunggu'): ?>
                <p>Bukti pembayaran sedang diverifikasi admin. Mohon tunggu.</p>
            <?php else: ?>
                <div class="payment-info"><strong>Tujuan pembayaran</strong><p><?= nl2br(e($rekening)) ?></p></div>
                <p>Nominal: <strong><?= formatRupiah((float) $pendaftaranItem['nominal']) ?></strong></p>
                <form method="post" enctype="multipart/form-data">
                    <input type="hidden" name="csrf_token" value="<?= generateCsrfToken() ?>">
                    <input type="hidden" name="action" value="bayar_pendaftaran">
                    <div class="form-group"><label>Bukti transfer</label><input class="form-control" type="file" name="berkas" accept=".pdf,.jpg,.jpeg,.png" required><small>Maksimal 10 MB.</small></div>
                    <button class="btn-primary">Kirim Bukti Pembayaran</button>
                </form>
                <?php foreach ($paymentFiles as $f): ?>
                    <ul class="portal-files"><li><span>Bukti terkirim — <?= e($f['nama_asli']) ?></span><strong><?= e(ucfirst($f['status'])) ?></strong></li></ul>
                <?php endforeach; ?>
            <?php endif; ?>
        <?php else: ?>
            <p>Belum ada tarif biaya pendaftaran. Hubungi panitia.</p>
        <?php endif; ?>
    </section>

    <!-- ── 2. ADMINISTRASI AWAL (KESANGGUPAN) ─────────────────── -->
    <section class="portal-card">
        <h2>2. Administrasi Awal</h2>
        <?php if (!$pendaftaranSelesai): ?>
            <div class="payment-locked">Selesaikan biaya pendaftaran terlebih dahulu.</div>
        <?php elseif ($kesanggupanSelesai): ?>
            <p class="payment-success" style="margin:0;text-align:left;">Kesanggupan & tanda tangan telah diverifikasi.</p>
        <?php else: ?>
            <p>Mendata kesanggupan saja (belum pembayaran). Pilih model & pilihan, lalu tanda tangan.</p>
            <form method="post" id="kesanggupanForm">
                <input type="hidden" name="csrf_token" value="<?= generateCsrfToken() ?>">
                <input type="hidden" name="action" value="kesanggupan">
                <input type="hidden" name="tanda_tangan" id="tandaTangan">

                <?php if ($administrasiItems): ?>
                    <div class="form-group"><label>Administrasi — pilih model</label>
                        <?php foreach ($administrasiItems as $i): ?>
                            <label class="cost-option"><input type="radio" name="pilih_administrasi" value="<?= $i['id'] ?>" required><span><strong><?= e($i['nama'] ?: 'Administrasi') ?></strong><small><?= hargaItem($i) ?></small></span></label>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>

                <?php if ($wakafItems): ?>
                    <div class="form-group"><label>Wakaf — pilih salah satu</label>
                        <?php foreach ($wakafItems as $i): ?>
                            <label class="cost-option"><input type="radio" name="pilih_wakaf" value="<?= $i['id'] ?>" required><span><strong><?= e($i['nama'] ?: 'Wakaf') ?></strong><small><?= hargaItem($i) ?></small></span></label>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>

                <?php if ($syahriyahItems): ?>
                    <div class="form-group"><label>Biaya Syahriyah (Bulanan) — pilih salah satu</label>
                        <?php foreach ($syahriyahItems as $i): ?>
                            <label class="cost-option"><input type="radio" name="pilih_syahriyah" value="<?= $i['id'] ?>" required><span><strong><?= e($i['nama'] ?: 'Syahriyah') ?></strong><small><?= hargaItem($i) ?></small></span></label>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>

                <?php if ($laundryItem): ?>
                    <div class="cost-option" style="cursor:default;"><span><strong>Laundry Santri</strong><small><?= hargaItem($laundryItem) ?></small></span></div>
                <?php endif; ?>
                <?php if ($infakItem): ?>
                    <div class="cost-option" style="cursor:default;"><span><strong>Infak Wajib</strong><small><?= hargaItem($infakItem) ?></small></span></div>
                <?php endif; ?>

                <label class="form-note" style="display:flex;align-items:center;gap:8px;margin:14px 0;cursor:pointer;">
                    <input type="checkbox" name="kesanggupan_setuju" value="1" required>
                    Saya menyatakan kesanggupan membayar seluruh biaya administrasi awal di atas.
                </label>

                <div class="form-group">
                    <label>Tanda tangan orang tua/wali</label>
                    <canvas id="ttdCanvas" width="520" height="200" style="width:100%;height:auto;border:1.5px solid var(--cream-dark);border-radius:8px;background:#fff;touch-action:none;cursor:crosshair;"></canvas>
                    <div style="display:flex;gap:8px;margin-top:8px;">
                        <button type="button" class="btn-sm btn-sm-secondary" id="ttdClear">Ulangi Tanda Tangan</button>
                    </div>
                </div>

                <button class="btn-primary">Simpan Kesanggupan</button>
            </form>
        <?php endif; ?>
    </section>
</div>

<!-- ── 3. PEMBERKASAN ───────────────────────────────────────── -->
<?php if (!$berkasBuka): ?>
    <div class="documents-locked"><strong>Pemberkasan belum dibuka</strong>
        <p>Lunasi biaya pendaftaran dan isi kesanggupan administrasi awal untuk membuka menu berkas.</p>
    </div>
<?php else: ?>
    <div class="payment-success"><strong>Pemberkasan terbuka</strong>
        <p>Lengkapi berkas berikut. Yang bertanda <strong>Wajib</strong> harus dilengkapi, yang lain boleh menyusul.</p>
    </div>
    <section class="portal-card">
        <h2>3. Pemberkasan</h2>
        <p>Kelengkapan wajib: <?= count($wajibTerisi) ?>/<?= count($wajibJenis) ?></p>
        <div class="berkas-grid">
            <?php
            $berkasJenis = [
                'kartu-keluarga' => true, 'akta-lahir' => true, 'ktp-ortu' => true,
                'foto' => true, 'ijazah' => false, 'sertifikat-tka' => false, 'lainnya' => false,
            ];
            foreach ($berkasJenis as $j => $wajib):
                $filesJ = array_values(array_filter($documentFiles, fn($f) => $f['jenis'] === $j));
            ?>
            <div class="berkas-item">
                <strong><?= e(berkasLabel($j)) ?></strong>
                <?php if ($wajib): ?>
                    <span class="badge badge-red">Wajib</span>
                <?php else: ?>
                    <span class="badge badge-gray">bisa menyusul</span>
                <?php endif; ?>
                <?php foreach ($filesJ as $f): ?>
                    <p style="margin:8px 0 0;font-size:12px;color:var(--text-mid);line-height:1.5;">
                        ✓ <?= e($f['nama_asli']) ?> — <strong><?= e(ucfirst($f['status'])) ?></strong>
                    </p>
                <?php endforeach; ?>
                <form method="post" enctype="multipart/form-data" style="margin-top:12px;">
                    <input type="hidden" name="csrf_token" value="<?= generateCsrfToken() ?>">
                    <input type="hidden" name="action" value="upload_document">
                    <input type="hidden" name="jenis" value="<?= $j ?>">
                    <div style="display:flex;gap:8px;">
                        <input class="form-control" type="file" name="berkas" accept=".pdf,.jpg,.jpeg,.png" required>
                        <button class="btn-sm btn-sm-primary" style="white-space:nowrap;">Upload</button>
                    </div>
                </form>
            </div>
            <?php endforeach; ?>
        </div>
        <p style="margin-top:20px;">Unduh ringkasan pendaftaran (berisi seluruh data, rincian biaya &amp; tanda tangan kesanggupan) sebagai arsip.</p>
        <a class="btn-primary" href="<?= BASE_URL ?>/surat-kesanggupan">Download Ringkasan Pendaftaran</a>
    </section>
<?php endif; ?>

</div></main>

<style>
.price-gratis { color: var(--green-deep); font-weight: 700; }
#kesanggupanForm .cost-option input { flex-shrink: 0; }
.portal-full { display: block; }
.berkas-grid { display: grid; grid-template-columns: repeat(2, 1fr); gap: 18px; }
.berkas-item {
    border: 1px solid var(--cream-dark); border-radius: 10px; padding: 16px;
    background: var(--cream);
}
.berkas-item .form-control { background: #fff; }
@media (max-width: 700px) { .berkas-grid { grid-template-columns: 1fr; } }
</style>

<script>
(function () {
    // ── Tanda tangan canvas ──
    var canvas = document.getElementById('ttdCanvas');
    if (canvas) {
        var ctx = canvas.getContext('2d');
        var drawing = false, drawn = false;
        ctx.lineWidth = 2.5; ctx.lineCap = 'round'; ctx.lineJoin = 'round';
        ctx.strokeStyle = '#123';

        function pos(e) {
            var r = canvas.getBoundingClientRect();
            var p = e.touches ? e.touches[0] : e;
            return {
                x: (p.clientX - r.left) * (canvas.width / r.width),
                y: (p.clientY - r.top) * (canvas.height / r.height)
            };
        }
        function start(e) { e.preventDefault(); drawing = true; drawn = true; var p = pos(e); ctx.beginPath(); ctx.moveTo(p.x, p.y); }
        function move(e) { if (!drawing) return; e.preventDefault(); var p = pos(e); ctx.lineTo(p.x, p.y); ctx.stroke(); }
        function stop() { drawing = false; }
        canvas.addEventListener('mousedown', start);
        canvas.addEventListener('mousemove', move);
        canvas.addEventListener('mouseup', stop);
        canvas.addEventListener('mouseleave', stop);
        canvas.addEventListener('touchstart', start);
        canvas.addEventListener('touchmove', move);
        canvas.addEventListener('touchend', stop);

        var clearBtn = document.getElementById('ttdClear');
        if (clearBtn) clearBtn.addEventListener('click', function () {
            ctx.clearRect(0, 0, canvas.width, canvas.height);
            drawn = false;
        });

        var form = document.getElementById('kesanggupanForm');
        if (form) form.addEventListener('submit', function (e) {
            if (!drawn) { e.preventDefault(); alert('Silakan gambar tanda tangan terlebih dahulu.'); return; }
            // Simpan dengan latar putih opak agar tidak bergantung pada alpha browser
            var saveCanvas = document.createElement('canvas');
            saveCanvas.width = canvas.width;
            saveCanvas.height = canvas.height;
            var sctx = saveCanvas.getContext('2d');
            sctx.fillStyle = '#ffffff';
            sctx.fillRect(0, 0, saveCanvas.width, saveCanvas.height);
            sctx.drawImage(canvas, 0, 0);
            document.getElementById('tandaTangan').value = saveCanvas.toDataURL('image/png');
        });
    }
}());
</script>
