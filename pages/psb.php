<?php
// ── Ambil pengaturan PSB dari database ───────────────────────
$pdo         = getDB();
$stmtPengat  = $pdo->query(
    "SELECT key_name, value FROM pengaturan
     WHERE key_name IN ('psb_status','psb_tahun','psb_batas_daftar','kontak_whatsapp')"
);
$pengaturan  = [];
foreach ($stmtPengat->fetchAll() as $row) {
    $pengaturan[$row['key_name']] = $row['value'];
}

$psbStatus   = $pengaturan['psb_status']       ?? 'tutup';
$psbTahun    = $pengaturan['psb_tahun']         ?? date('Y') . '/' . (date('Y') + 1);
$psbBatas    = $pengaturan['psb_batas_daftar']  ?? null;
$kontakWA    = $pengaturan['kontak_whatsapp']    ?? '6281234567890';

$psbBuka     = ($psbStatus === 'buka') && (!$psbBatas || date('Y-m-d') <= $psbBatas);

// ── Proses form POST ──────────────────────────────────────────
$errors        = [];
$successData   = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $psbBuka) {
    validateCsrf();

    // Sanitasi input
    $data = [
        'nama_lengkap'    => sanitizeString($_POST['nama_lengkap']    ?? ''),
        'tempat_lahir'    => sanitizeString($_POST['tempat_lahir']    ?? ''),
        'tanggal_lahir'   => sanitizeString($_POST['tanggal_lahir']   ?? ''),
        'jenis_kelamin'   => sanitizeString($_POST['jenis_kelamin']   ?? ''),
        'jenjang'         => sanitizeString($_POST['jenjang']         ?? ''),
        'whatsapp'        => sanitizeString($_POST['whatsapp']        ?? ''),
        'nama_ayah'       => sanitizeString($_POST['nama_ayah']       ?? ''),
        'nama_ibu'        => sanitizeString($_POST['nama_ibu']        ?? ''),
        'hp_ortu'         => sanitizeString($_POST['hp_ortu']         ?? ''),
        'pekerjaan_ortu'  => sanitizeString($_POST['pekerjaan_ortu']  ?? ''),
        'alamat'          => sanitizeString($_POST['alamat']          ?? ''),
        'asal_sekolah'    => sanitizeString($_POST['asal_sekolah']    ?? ''),
        'tahun_lulus'     => sanitizeInt($_POST['tahun_lulus']        ?? date('Y')),
        'kemampuan_quran' => sanitizeString($_POST['kemampuan_quran'] ?? ''),
        'jumlah_hafalan'  => sanitizeString($_POST['jumlah_hafalan']  ?? ''),
        'motivasi'        => sanitizeString($_POST['motivasi']        ?? ''),
        'tinggi_badan'    => sanitizeFloat($_POST['tinggi_badan']    ?? ''),
        'berat_badan'     => sanitizeFloat($_POST['berat_badan']     ?? ''),
        'portal_password' => $_POST['portal_password'] ?? '',
        'password_confirm'=> $_POST['password_confirm'] ?? '',
    ];

    // Validasi wajib
    $validasiWajib = [
        'nama_lengkap'   => 'Nama lengkap',
        'tempat_lahir'   => 'Tempat lahir',
        'tanggal_lahir'  => 'Tanggal lahir',
        'jenis_kelamin'  => 'Jenis kelamin',
        'jenjang'        => 'Jenjang',
        'whatsapp'       => 'Nomor WhatsApp',
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

    // Validasi enum
    $validJK      = ['L', 'P'];
    $validJenjang = ['mts', 'ma', 'tahfidz-intensif'];
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

    // Validasi tahun lulus
    $tahunSekarang = (int) date('Y');
    if ($data['tahun_lulus'] < 2015 || $data['tahun_lulus'] > $tahunSekarang + 1) {
        $errors['tahun_lulus'] = 'Tahun lulus tidak valid.';
    }

    // Validasi tinggi & berat badan (opsional, untuk seragam)
    if ($data['tinggi_badan'] > 0 && ($data['tinggi_badan'] < 50 || $data['tinggi_badan'] > 250)) {
        $errors['tinggi_badan'] = 'Tinggi badan tidak valid (dalam cm).';
    }
    if ($data['berat_badan'] > 0 && ($data['berat_badan'] < 5 || $data['berat_badan'] > 250)) {
        $errors['berat_badan'] = 'Berat badan tidak valid (dalam kg).';
    }
    if (strlen($data['portal_password']) < 8) {
        $errors['portal_password'] = 'Password akun minimal 8 karakter.';
    } elseif ($data['portal_password'] !== $data['password_confirm']) {
        $errors['password_confirm'] = 'Konfirmasi password tidak sama.';
    }

    // Simpan ke database jika tidak ada error
    if (empty($errors)) {
        // Generate nomor pendaftaran: ASQ-YYYY-XXXX
        $tahun        = date('Y');
        $stmtMax      = $pdo->prepare(
            "SELECT COUNT(*) FROM pendaftaran WHERE nomor_daftar LIKE ?"
        );
        $stmtMax->execute(["ASQ-$tahun-%"]);
        $urutan       = (int) $stmtMax->fetchColumn() + 1;
        $nomorDaftar  = "ASQ-$tahun-" . str_pad($urutan, 4, '0', STR_PAD_LEFT);

        $stmtInsert = $pdo->prepare(
            "INSERT INTO pendaftaran
                (nomor_daftar, portal_password, nama_lengkap, tempat_lahir, tanggal_lahir,
                 jenis_kelamin, jenjang, whatsapp,
                 nama_ayah, nama_ibu, hp_ortu, pekerjaan_ortu, alamat,
                 asal_sekolah, tahun_lulus, kemampuan_quran, jumlah_hafalan, motivasi,
                 tinggi_badan, berat_badan)
             VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)"
        );
        $stmtInsert->execute([
            $nomorDaftar,
            password_hash($data['portal_password'], PASSWORD_BCRYPT),
            $data['nama_lengkap'],
            $data['tempat_lahir'],
            $data['tanggal_lahir'],
            $data['jenis_kelamin'],
            $data['jenjang'],
            $data['whatsapp'],
            $data['nama_ayah'],
            $data['nama_ibu'],
            $data['hp_ortu'],
            $data['pekerjaan_ortu'] ?: null,
            $data['alamat'],
            $data['asal_sekolah'],
            $data['tahun_lulus'],
            $data['kemampuan_quran'],
            $data['jumlah_hafalan'] ?: null,
            $data['motivasi'] ?: null,
            $data['tinggi_badan'] > 0 ? $data['tinggi_badan'] : null,
            $data['berat_badan'] > 0 ? $data['berat_badan'] : null,
        ]);

        // Snapshot tarif pembiayaan ke tagihan santri ini
        snapshotPembiayaan($pdo, (int) $pdo->lastInsertId(), $data['jenis_kelamin']);

        // POST-Redirect-GET: simpan data sukses di session lalu redirect
        $_SESSION['psb_success'] = [
            'nomor' => $nomorDaftar,
            'nama'  => $data['nama_lengkap'],
        ];
        redirect('/psb?daftar=sukses');
    }
}

// Ambil data sukses dari session (setelah redirect)
$successData = null;
if (!empty($_GET['daftar']) && $_GET['daftar'] === 'sukses' && !empty($_SESSION['psb_success'])) {
    $successData = $_SESSION['psb_success'];
    unset($_SESSION['psb_success']);
}

// ── Meta halaman ─────────────────────────────────────────────
$activePage      = 'psb';
$pageTitle       = 'Pendaftaran Santri Baru ' . $psbTahun . ' | ' . APP_NAME;
$pageDescription = 'Daftarkan putra-putri Anda ke Pondok Pesantren Ash-Shiddiq. PSB Tahun Ajaran ' . $psbTahun . ' telah dibuka. Isi formulir pendaftaran online sekarang.';
$pageCanonical   = BASE_URL . '/psb';

$labelJenjang = [
    'mts'               => 'SMP (Setara MTs)',
    'ma'                => 'SMA (Setara MA)',
    'tahfidz-intensif'  => 'Tahfidz Intensif',
];
$labelQuran = [
    'belum-bisa'   => 'Belum Bisa Membaca',
    'bisa-membaca' => 'Bisa Membaca',
    'tartil'       => 'Tartil',
    'hafal-juz-30' => 'Hafal Juz 30',
    'hafal-lebih'  => 'Hafal Lebih dari Juz 30',
];
$errorLabels = [
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
    'tahun_lulus'     => 'Tahun lulus',
    'kemampuan_quran' => 'Kemampuan membaca Al-Qur\'an',
    'tinggi_badan'    => 'Tinggi badan',
    'berat_badan'     => 'Berat badan',
    'portal_password' => 'Password akun',
    'password_confirm'=> 'Konfirmasi password',
];

// Tahun lulus options
$tahunOptions = range(date('Y') + 1, 2018);

$extraHead = <<<'CSS'
<style>
/* ── WRAPPER ─────────────────────────────────────────────────── */
.psb-wrapper {
    max-width:1100px; margin:0 auto; padding:80px 5%;
    display:grid; grid-template-columns:1fr 1.1fr; gap:60px; align-items:start;
}

/* ── TIMELINE ────────────────────────────────────────────────── */
.timeline { margin:36px 0; position:relative; }
.timeline::before {
    content:''; position:absolute; left:19px; top:0; bottom:0;
    width:1px; background:var(--cream-dark);
}
.timeline-item { display:flex; gap:20px; margin-bottom:32px; position:relative; }
.timeline-dot {
    width:40px; height:40px; flex-shrink:0;
    background:var(--white); border:2px solid var(--green-mid);
    border-radius:50%; display:flex; align-items:center; justify-content:center;
    font-size:14px; font-weight:700; color:var(--green-deep);
    position:relative; z-index:1; font-family:'Plus Jakarta Sans',sans-serif;
}
.timeline-dot.done { background:var(--green-deep); color:white; border-color:var(--green-deep); }
.timeline-date {
    font-size:11px; letter-spacing:2px; text-transform:uppercase;
    color:var(--gold); font-weight:600; margin-bottom:4px;
}
.timeline-title { font-family:'Plus Jakarta Sans',sans-serif; font-size:17px; color:var(--text-dark); margin-bottom:6px; }
.timeline-desc { font-size:13px; color:var(--text-mid); line-height:1.6; }

/* ── SYARAT & BIAYA ──────────────────────────────────────────── */
.syarat-box { background:var(--white); border-radius:4px; padding:28px; margin-top:36px; }
.syarat-box h3 {
    font-family:'Plus Jakarta Sans',sans-serif; font-size:20px; color:var(--text-dark);
    margin-bottom:20px; padding-bottom:12px; border-bottom:2px solid var(--cream-dark);
    position:relative;
}
.syarat-box h3::after { content:''; position:absolute; bottom:-2px; left:0; width:36px; height:2px; background:var(--gold); }
.syarat-list { list-style:none; }
.syarat-list li {
    display:flex; gap:12px; align-items:flex-start; padding:10px 0;
    border-bottom:1px solid var(--cream-dark); font-size:14px; color:var(--text-mid); line-height:1.5;
}
.syarat-list li:last-child { border-bottom:none; }
.syarat-check {
    width:22px; height:22px; flex-shrink:0; background:var(--cream-dark);
    border-radius:50%; display:flex; align-items:center; justify-content:center;
    font-size:11px; color:var(--green-deep); margin-top:1px;
}
.biaya-box { background:var(--green-deep); border-radius:4px; padding:28px; margin-top:24px; }
.biaya-box h3 { font-family:'Plus Jakarta Sans',sans-serif; font-size:20px; color:white; margin-bottom:20px; }
.biaya-item {
    display:flex; justify-content:space-between; align-items:center;
    padding:12px 0; border-bottom:1px solid rgba(255,255,255,0.1);
}
.biaya-item:last-child { border-bottom:none; }
.biaya-label { font-size:14px; color:rgba(255,255,255,0.7); }
.biaya-value { font-family:'Plus Jakarta Sans',sans-serif; font-size:18px; color:var(--gold); }

/* ── FORM CARD ────────────────────────────────────────────────── */
.psb-form-card {
    background:var(--white); border-radius:4px; padding:40px 36px;
    box-shadow:0 8px 40px rgba(13,122,74,0.08);
}
.form-header { text-align:center; margin-bottom:32px; padding-bottom:24px; border-bottom:1px solid var(--cream-dark); }
.form-header .arabic { font-family:'Amiri',serif; font-size:24px; color:var(--green-mid); margin-bottom:8px; }
.form-header h2 { font-family:'Plus Jakarta Sans',sans-serif; font-size:24px; color:var(--text-dark); margin-bottom:6px; }
.form-header p  { font-size:13px; color:var(--text-light); }

/* Step indicator */
.form-step { display:flex; gap:0; margin-bottom:32px; }
.step-item { flex:1; text-align:center; position:relative; }
.step-item::after {
    content:''; position:absolute; top:16px; left:50%; right:-50%;
    height:1px; background:var(--cream-dark); z-index:0;
}
.step-item:last-child::after { display:none; }
.step-num {
    width:32px; height:32px; border-radius:50%;
    background:var(--cream-dark); color:var(--text-light);
    display:flex; align-items:center; justify-content:center;
    font-size:13px; font-weight:600; margin:0 auto 6px;
    position:relative; z-index:1; transition:all 0.3s;
}
.step-num.active { background:var(--green-deep); color:white; }
.step-num.done   { background:var(--gold); color:var(--text-dark); }
.step-label { font-size:11px; color:var(--text-light); }
.step-item.active .step-label { color:var(--green-deep); font-weight:600; }

/* Form controls */
.form-page { display:none; }
.form-page.active { display:block; }
.form-group { margin-bottom:20px; }
.form-group label {
    display:block; font-size:12px; font-weight:600; color:var(--text-dark);
    margin-bottom:7px; letter-spacing:0.5px;
}
.form-group label .req { color:#e55; margin-left:2px; }
.form-control {
    width:100%; padding:11px 14px; border:1.5px solid var(--cream-dark); border-radius:3px;
    font-family:'Plus Jakarta Sans',sans-serif; font-size:14px; color:var(--text-dark);
    background:var(--cream); outline:none; transition:border-color 0.3s, background 0.3s;
    box-sizing:border-box;
}
.form-control:focus { border-color:var(--green-mid); background:white; }
.form-control.is-error { border-color:#e55; }
.form-control::placeholder { color:var(--text-light); }
select.form-control { cursor:pointer; }
.form-row { display:grid; grid-template-columns:1fr 1fr; gap:14px; }
.radio-group { display:flex; gap:10px; flex-wrap:wrap; }
.radio-item {
    flex:1; min-width:80px; border:1.5px solid var(--cream-dark); border-radius:3px;
    padding:10px 12px; cursor:pointer; transition:all 0.2s; text-align:center;
    font-size:13px; color:var(--text-mid);
}
.radio-item:has(input:checked) {
    border-color:var(--green-mid); background:rgba(13,122,74,0.06);
    color:var(--green-deep); font-weight:600;
}
.radio-item input { display:none; }
.field-error { font-size:11px; color:#e55; margin-top:4px; }
.form-note { font-size:12px; color:var(--text-light); margin-top:6px; line-height:1.6; }
.form-nav { display:flex; gap:12px; margin-top:28px; }
.btn-next {
    flex:1; padding:14px; background:var(--green-deep); color:white; border:none;
    border-radius:3px; font-size:15px; font-weight:600; cursor:pointer; transition:all 0.3s;
    font-family:'Plus Jakarta Sans',sans-serif;
}
.btn-next:hover { background:var(--green-mid); transform:translateY(-1px); box-shadow:0 6px 20px rgba(13,122,74,0.25); }
.btn-prev {
    padding:14px 20px; background:transparent; color:var(--text-mid);
    border:1.5px solid var(--cream-dark); border-radius:3px; font-size:14px;
    cursor:pointer; transition:all 0.2s; font-family:'Plus Jakarta Sans',sans-serif;
}
.btn-prev:hover { border-color:var(--text-mid); }

/* Success state */
.success-state { text-align:center; padding:20px 0; }
.success-icon {
    width:72px; height:72px; background:rgba(13,122,74,0.1); border-radius:50%;
    display:flex; align-items:center; justify-content:center; margin:0 auto 20px; font-size:30px;
}
.success-state h3 { font-family:'Plus Jakarta Sans',sans-serif; font-size:24px; margin-bottom:12px; }
.success-state p { font-size:14px; color:var(--text-mid); line-height:1.7; }
.nomor-pendaftaran {
    margin:20px 0; background:var(--cream); border-radius:3px; padding:16px;
    font-size:22px; font-weight:700; color:var(--green-deep);
    letter-spacing:4px; font-family:'Plus Jakarta Sans',sans-serif;
}

/* PSB tutup notice */
.psb-tutup {
    background:var(--white); border-radius:4px; padding:48px 40px;
    text-align:center; border-top:4px solid var(--gold);
}
.psb-tutup h3 { font-family:'Plus Jakarta Sans',sans-serif; font-size:26px; color:var(--text-dark); margin-bottom:16px; }
.psb-tutup p  { font-size:15px; color:var(--text-mid); line-height:1.8; }

@media (max-width:900px) {
    .psb-wrapper { grid-template-columns:1fr; }
    .psb-form-card { position:static; }
}
@media (max-width:480px) {
    .psb-form-card { padding:28px 20px; }
    .form-row { grid-template-columns:1fr; }
}
</style>
CSS;
?>

<!-- ══ PAGE HERO ════════════════════════════════════════════════ -->
<div class="page-hero">
    <div class="page-hero-pattern"></div>
    <div class="page-hero-content">
        <div class="page-hero-tag"><span></span>Penerimaan Santri Baru<span></span></div>
        <h1>Daftar Sekarang</h1>
        <p>Tahun Ajaran <?= e($psbTahun) ?> telah dibuka. Jadilah bagian dari keluarga besar Ash-Shiddiq.</p>
    </div>
</div>

<!-- ══ KONTEN ═══════════════════════════════════════════════════ -->
<div style="text-align:center;padding:24px 20px 0"><span>Sudah menerima nomor induk pesantren?</span> <a class="btn-primary" href="<?=BASE_URL?>/login-santri" style="display:inline-block;margin-left:10px">Login Portal Santri</a></div>
<div class="psb-wrapper">

    <!-- ── INFO SISI KIRI ──────────────────────────────────────── -->
    <div class="psb-info">
        <div class="section-tag reveal">
            <span></span><span class="section-tag-text">Jadwal PSB</span><span></span>
        </div>
        <h2 class="section-title reveal">Alur Penerimaan<br>Santri Baru</h2>
        <p class="section-desc reveal">Ikuti tahapan seleksi berikut untuk bergabung bersama kami.</p>

        <div class="timeline">
            <div class="timeline-item reveal">
                <div class="timeline-dot done">✓</div>
                <div class="timeline-body">
                    <div class="timeline-date">1 Mei – 31 Mei <?= date('Y') ?></div>
                    <div class="timeline-title">Pendaftaran Online</div>
                    <div class="timeline-desc">Mengisi formulir pendaftaran secara online dan mengunggah berkas persyaratan yang ditentukan.</div>
                </div>
            </div>
            <div class="timeline-item reveal">
                <div class="timeline-dot done">✓</div>
                <div class="timeline-body">
                    <div class="timeline-date">5 – 10 Juni <?= date('Y') ?></div>
                    <div class="timeline-title">Tes Seleksi &amp; Wawancara</div>
                    <div class="timeline-desc">Tes kemampuan membaca Al-Qur'an, wawancara motivasi, dan pemeriksaan kesehatan dasar.</div>
                </div>
            </div>
            <div class="timeline-item reveal">
                <div class="timeline-dot">3</div>
                <div class="timeline-body">
                    <div class="timeline-date">15 Juni <?= date('Y') ?></div>
                    <div class="timeline-title">Pengumuman Hasil</div>
                    <div class="timeline-desc">Pengumuman hasil seleksi melalui website dan akan dihubungi langsung oleh panitia.</div>
                </div>
            </div>
            <div class="timeline-item reveal">
                <div class="timeline-dot">4</div>
                <div class="timeline-body">
                    <div class="timeline-date">1 – 14 Juli <?= date('Y') ?></div>
                    <div class="timeline-title">Daftar Ulang &amp; Orientasi</div>
                    <div class="timeline-desc">Pelunasan administrasi, penyerahan berkas asli, dan masa orientasi santri baru.</div>
                </div>
            </div>
        </div>

        <!-- Syarat -->
        <div class="syarat-box reveal">
            <h3>Syarat Pendaftaran</h3>
            <ul class="syarat-list">
                <li><div class="syarat-check" aria-hidden="true">✓</div> Lulusan SD/MI (untuk jenjang SMP) atau SMP/MTs (untuk jenjang SMA)</li>
                <li><div class="syarat-check" aria-hidden="true">✓</div> Usia maksimal 15 tahun (SMP) atau 18 tahun (SMA)</li>
                <li><div class="syarat-check" aria-hidden="true">✓</div> Mampu membaca Al-Qur'an (tidak wajib hafal)</li>
                <li><div class="syarat-check" aria-hidden="true">✓</div> Surat keterangan sehat dari dokter</li>
                <li><div class="syarat-check" aria-hidden="true">✓</div> Foto copy ijazah dan raport terakhir (3 lembar)</li>
                <li><div class="syarat-check" aria-hidden="true">✓</div> Pas foto 3×4 latar biru (4 lembar)</li>
                <li><div class="syarat-check" aria-hidden="true">✓</div> Surat rekomendasi dari kepala sekolah/ustadz</li>
                <li><div class="syarat-check" aria-hidden="true">✓</div> Akta kelahiran (foto copy 2 lembar)</li>
            </ul>
        </div>

        <!-- Biaya -->
        <?php
        $tarifBiaya = getPembiayaanTarif($pdo);
        $biayaTampil = [];
        $nilai = fn(array $r): float => (float) ($r['harga_diskon'] ?? $r['harga_asli']);
        $minOpt = function (array $rows) use ($nilai): float {
            return min(array_map($nilai, $rows));
        };
        if (!empty($tarifBiaya['pendaftaran'])) {
            $t = $tarifBiaya['pendaftaran'][0];
            $biayaTampil[] = ['Biaya Pendaftaran', !empty($t['gratis']) ? 'GRATIS' : formatRupiah($nilai($t))];
        }
        if (!empty($tarifBiaya['administrasi'])) {
            $biayaTampil[] = ['Administrasi Awal', 'mulai ' . formatRupiah($minOpt($tarifBiaya['administrasi']))];
        }
        if (!empty($tarifBiaya['wakaf'])) {
            $biayaTampil[] = ['Wakaf', 'mulai ' . formatRupiah($minOpt($tarifBiaya['wakaf']))];
        }
        if (!empty($tarifBiaya['syahriyah'])) {
            $biayaTampil[] = ['Syahriyah / Bulan', 'mulai ' . formatRupiah($minOpt($tarifBiaya['syahriyah']))];
        }
        $laundryL = $laundryP = null;
        foreach ($tarifBiaya['laundry'] as $t) {
            if ($t['gender'] === 'L') $laundryL = $nilai($t);
            if ($t['gender'] === 'P') $laundryP = $nilai($t);
        }
        if ($laundryL !== null || $laundryP !== null) {
            $biayaTampil[] = ['Laundry (L/P)', ($laundryL !== null ? formatRupiah($laundryL) : '-') . ' / ' . ($laundryP !== null ? formatRupiah($laundryP) : '-')];
        }
        if (!empty($tarifBiaya['infak'])) {
            $biayaTampil[] = ['Infak Wajib', formatRupiah($nilai($tarifBiaya['infak'][0]))];
        }
        ?>
        <div class="biaya-box reveal">
            <h3>Estimasi Biaya Awal</h3>
            <?php foreach ($biayaTampil as [$biayaLabel, $biayaValue]): ?>
            <div class="biaya-item">
                <span class="biaya-label"><?= e($biayaLabel) ?></span>
                <span class="biaya-value"><?= e($biayaValue) ?></span>
            </div>
            <?php endforeach; ?>
            <p style="font-size:12px;color:rgba(255,255,255,0.45);margin-top:16px;line-height:1.6;">
                * Tarif final sesuai kesanggupan orang tua. Tersedia diskon dan jalur gratis bagi yang memenuhi syarat. Hubungi panitia untuk informasi lebih lanjut.
            </p>
        </div>
    </div>

    <!-- ── FORM SISI KANAN ─────────────────────────────────────── -->
    <div class="psb-form-card reveal-right">

        <?php if ($successData): ?>
        <!-- ─── SUCCESS STATE ─────────────────────────────────── -->
        <div class="success-state">
            <div class="success-icon" aria-hidden="true">✅</div>
            <h3>Alhamdulillah!</h3>
            <p>Pendaftaran <strong><?= e($successData['nama']) ?></strong> telah berhasil dikirim. Panitia akan menghubungi Anda melalui WhatsApp dalam 1×24 jam.</p>
            <div class="nomor-pendaftaran"><?= e($successData['nomor']) ?></div>
            <p style="font-size:12px;color:var(--text-light);">Simpan nomor pendaftaran ini sebagai bukti.</p>
            <p style="font-size:13px;color:var(--text-mid);">Nomor pendaftaran tersebut sudah bisa digunakan untuk login dan menyelesaikan pembayaran.</p>
            <a href="<?= e(BASE_URL . '/login-santri') ?>" class="btn-primary" style="display:inline-block;margin:12px 0;">Login &amp; Lanjut Pembayaran</a><br>
            <br>
            <a href="https://wa.me/<?= e($kontakWA) ?>?text=<?= rawurlencode('Assalamu\'alaikum, saya ingin konfirmasi pendaftaran dengan nomor ' . $successData['nomor'] . ' atas nama ' . $successData['nama']) ?>"
               class="btn-primary" style="display:inline-block;margin-bottom:12px;"
               target="_blank" rel="noopener noreferrer">
                Konfirmasi via WhatsApp
            </a>
            <br>
            <a href="<?= e(BASE_URL . '/') ?>" class="btn-outline" style="display:inline-block;">← Kembali ke Beranda</a>
        </div>

        <?php elseif (!$psbBuka): ?>
        <!-- ─── PSB TUTUP ──────────────────────────────────────── -->
        <div class="psb-tutup">
            <p style="font-size:48px;margin-bottom:16px;" aria-hidden="true">📋</p>
            <h3>Pendaftaran Belum/Sudah Dibuka</h3>
            <p>
                Mohon maaf, pendaftaran santri baru saat ini sedang ditutup.
                Untuk informasi lebih lanjut, silakan hubungi kami melalui WhatsApp.
            </p>
            <br>
            <a href="https://wa.me/<?= e($kontakWA) ?>" class="btn-primary"
               target="_blank" rel="noopener noreferrer" style="display:inline-block;">
                Hubungi via WhatsApp
            </a>
        </div>

        <?php else: ?>
        <!-- ─── FORM PENDAFTARAN ───────────────────────────────── -->
        <div class="form-header">
            <div class="arabic" aria-hidden="true">بِسْمِ اللهِ</div>
            <h2>Formulir Pendaftaran</h2>
            <p>Isi data dengan lengkap dan benar — Tahun Ajaran <?= e($psbTahun) ?></p>
        </div>

        <?php if (!empty($errors)): ?>
        <div class="flash-message flash-error" role="alert" style="margin-bottom:16px;">
            <strong>Mohon periksa kembali isian formulir. Terdapat <?= count($errors) ?> kesalahan:</strong>
            <ul style="margin:8px 0 0;padding-left:18px;">
                <?php foreach ($errors as $field => $msg): ?>
                <li style="margin-bottom:2px;"><?= e(($errorLabels[$field] ?? $field) . ': ' . $msg) ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
        <?php endif; ?>

        <!-- Step indicator -->
        <div class="form-step" id="formStep" aria-label="Langkah pendaftaran">
            <div class="step-item active" id="stepInd1">
                <div class="step-num active" id="sNum1">1</div>
                <div class="step-label">Data Calon</div>
            </div>
            <div class="step-item" id="stepInd2">
                <div class="step-num" id="sNum2">2</div>
                <div class="step-label">Orang Tua</div>
            </div>
            <div class="step-item" id="stepInd3">
                <div class="step-num" id="sNum3">3</div>
                <div class="step-label">Akademik</div>
            </div>
        </div>

        <form method="POST" action="<?= e(BASE_URL . '/psb') ?>" id="psbForm" novalidate>
            <input type="hidden" name="csrf_token" value="<?= generateCsrfToken() ?>">

            <!-- ── STEP 1: Data Calon Santri ─────────────────────── -->
            <div class="form-page active" id="page1">
                <div class="form-group">
                    <label for="nama_lengkap">Nama Lengkap Calon Santri <span class="req">*</span></label>
                    <input type="text" id="nama_lengkap" name="nama_lengkap" class="form-control<?= isset($errors['nama_lengkap']) ? ' is-error' : '' ?>"
                           placeholder="Nama sesuai akta kelahiran" maxlength="100"
                           value="<?= isset($data['nama_lengkap']) ? e($data['nama_lengkap']) : '' ?>" required>
                    <?php if (isset($errors['nama_lengkap'])): ?>
                    <p class="field-error"><?= e($errors['nama_lengkap']) ?></p>
                    <?php endif; ?>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label for="tempat_lahir">Tempat Lahir <span class="req">*</span></label>
                        <input type="text" id="tempat_lahir" name="tempat_lahir"
                               class="form-control<?= isset($errors['tempat_lahir']) ? ' is-error' : '' ?>"
                               placeholder="Kota lahir" maxlength="100"
                               value="<?= isset($data['tempat_lahir']) ? e($data['tempat_lahir']) : '' ?>" required>
                        <?php if (isset($errors['tempat_lahir'])): ?>
                        <p class="field-error"><?= e($errors['tempat_lahir']) ?></p>
                        <?php endif; ?>
                    </div>
                    <div class="form-group">
                        <label for="tanggal_lahir">Tanggal Lahir <span class="req">*</span></label>
                        <input type="date" id="tanggal_lahir" name="tanggal_lahir"
                               class="form-control<?= isset($errors['tanggal_lahir']) ? ' is-error' : '' ?>"
                               value="<?= isset($data['tanggal_lahir']) ? e($data['tanggal_lahir']) : '' ?>" required>
                        <?php if (isset($errors['tanggal_lahir'])): ?>
                        <p class="field-error"><?= e($errors['tanggal_lahir']) ?></p>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="form-group">
                    <label>Jenis Kelamin <span class="req">*</span></label>
                    <div class="radio-group">
                        <label class="radio-item">
                            <input type="radio" name="jenis_kelamin" value="L"
                                   <?= (!isset($data['jenis_kelamin']) || $data['jenis_kelamin'] === 'L') ? 'checked' : '' ?>>
                            Laki-laki
                        </label>
                        <label class="radio-item">
                            <input type="radio" name="jenis_kelamin" value="P"
                                   <?= (isset($data['jenis_kelamin']) && $data['jenis_kelamin'] === 'P') ? 'checked' : '' ?>>
                            Perempuan
                        </label>
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label for="tinggi_badan">Tinggi Badan (cm)</label>
                        <input type="number" id="tinggi_badan" name="tinggi_badan" min="50" max="250" step="0.1"
                               class="form-control<?= isset($errors['tinggi_badan']) ? ' is-error' : '' ?>"
                               placeholder="Contoh: 145" maxlength="5"
                               value="<?= isset($data['tinggi_badan']) && $data['tinggi_badan'] > 0 ? e($data['tinggi_badan']) : '' ?>">
                        <?php if (isset($errors['tinggi_badan'])): ?>
                        <p class="field-error"><?= e($errors['tinggi_badan']) ?></p>
                        <?php endif; ?>
                        <p class="form-note">Untuk persiapan seragam santri.</p>
                    </div>
                    <div class="form-group">
                        <label for="berat_badan">Berat Badan (kg)</label>
                        <input type="number" id="berat_badan" name="berat_badan" min="5" max="250" step="0.1"
                               class="form-control<?= isset($errors['berat_badan']) ? ' is-error' : '' ?>"
                               placeholder="Contoh: 38" maxlength="5"
                               value="<?= isset($data['berat_badan']) && $data['berat_badan'] > 0 ? e($data['berat_badan']) : '' ?>">
                        <?php if (isset($errors['berat_badan'])): ?>
                        <p class="field-error"><?= e($errors['berat_badan']) ?></p>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="form-group">
                    <label for="jenjang">Jenjang yang Dituju <span class="req">*</span></label>
                    <select id="jenjang" name="jenjang"
                            class="form-control<?= isset($errors['jenjang']) ? ' is-error' : '' ?>" required>
                        <option value="">-- Pilih Jenjang --</option>
                        <?php foreach ($labelJenjang as $val => $lbl): ?>
                        <option value="<?= e($val) ?>" <?= (isset($data['jenjang']) && $data['jenjang'] === $val) ? 'selected' : '' ?>>
                            <?= e($lbl) ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                    <?php if (isset($errors['jenjang'])): ?>
                    <p class="field-error"><?= e($errors['jenjang']) ?></p>
                    <?php endif; ?>
                </div>
                <div class="form-group">
                    <label for="whatsapp">Nomor WhatsApp Aktif <span class="req">*</span></label>
                    <input type="tel" id="whatsapp" name="whatsapp"
                           class="form-control<?= isset($errors['whatsapp']) ? ' is-error' : '' ?>"
                           placeholder="08xxxxxxxxxx" maxlength="20"
                           value="<?= isset($data['whatsapp']) ? e($data['whatsapp']) : '' ?>" required>
                    <?php if (isset($errors['whatsapp'])): ?>
                    <p class="field-error"><?= e($errors['whatsapp']) ?></p>
                    <?php endif; ?>
                </div>
                <div class="form-nav">
                    <button type="button" class="btn-next" onclick="psbGoTo(2)">Lanjut &rarr;</button>
                </div>
            </div>

            <!-- ── STEP 2: Data Orang Tua ─────────────────────────── -->
            <div class="form-page" id="page2">
                <div class="form-group">
                    <label for="nama_ayah">Nama Ayah <span class="req">*</span></label>
                    <input type="text" id="nama_ayah" name="nama_ayah"
                           class="form-control<?= isset($errors['nama_ayah']) ? ' is-error' : '' ?>"
                           placeholder="Nama lengkap ayah" maxlength="100"
                           value="<?= isset($data['nama_ayah']) ? e($data['nama_ayah']) : '' ?>" required>
                    <?php if (isset($errors['nama_ayah'])): ?>
                    <p class="field-error"><?= e($errors['nama_ayah']) ?></p>
                    <?php endif; ?>
                </div>
                <div class="form-group">
                    <label for="nama_ibu">Nama Ibu <span class="req">*</span></label>
                    <input type="text" id="nama_ibu" name="nama_ibu"
                           class="form-control<?= isset($errors['nama_ibu']) ? ' is-error' : '' ?>"
                           placeholder="Nama lengkap ibu" maxlength="100"
                           value="<?= isset($data['nama_ibu']) ? e($data['nama_ibu']) : '' ?>" required>
                    <?php if (isset($errors['nama_ibu'])): ?>
                    <p class="field-error"><?= e($errors['nama_ibu']) ?></p>
                    <?php endif; ?>
                </div>
                <div class="form-group">
                    <label for="hp_ortu">No. HP Orang Tua / Wali <span class="req">*</span></label>
                    <input type="tel" id="hp_ortu" name="hp_ortu"
                           class="form-control<?= isset($errors['hp_ortu']) ? ' is-error' : '' ?>"
                           placeholder="08xxxxxxxxxx" maxlength="20"
                           value="<?= isset($data['hp_ortu']) ? e($data['hp_ortu']) : '' ?>" required>
                    <?php if (isset($errors['hp_ortu'])): ?>
                    <p class="field-error"><?= e($errors['hp_ortu']) ?></p>
                    <?php endif; ?>
                </div>
                <div class="form-group">
                    <label for="pekerjaan_ortu">Pekerjaan Orang Tua</label>
                    <input type="text" id="pekerjaan_ortu" name="pekerjaan_ortu"
                           class="form-control" placeholder="Pekerjaan ayah / ibu" maxlength="100"
                           value="<?= isset($data['pekerjaan_ortu']) ? e($data['pekerjaan_ortu']) : '' ?>">
                </div>
                <div class="form-group">
                    <label for="alamat">Alamat Lengkap <span class="req">*</span></label>
                    <textarea id="alamat" name="alamat" rows="3"
                              class="form-control<?= isset($errors['alamat']) ? ' is-error' : '' ?>"
                              placeholder="Jalan, RT/RW, Kelurahan, Kecamatan, Kabupaten/Kota, Provinsi"
                              style="resize:vertical;" required><?= isset($data['alamat']) ? e($data['alamat']) : '' ?></textarea>
                    <?php if (isset($errors['alamat'])): ?>
                    <p class="field-error"><?= e($errors['alamat']) ?></p>
                    <?php endif; ?>
                </div>
                <div class="form-nav">
                    <button type="button" class="btn-prev" onclick="psbGoTo(1)">&larr; Kembali</button>
                    <button type="button" class="btn-next" onclick="psbGoTo(3)">Lanjut &rarr;</button>
                </div>
            </div>

            <!-- ── STEP 3: Data Akademik ──────────────────────────── -->
            <div class="form-page" id="page3">
                <div class="form-group">
                    <label for="asal_sekolah">Asal Sekolah <span class="req">*</span></label>
                    <input type="text" id="asal_sekolah" name="asal_sekolah"
                           class="form-control<?= isset($errors['asal_sekolah']) ? ' is-error' : '' ?>"
                           placeholder="Nama sekolah / madrasah asal" maxlength="150"
                           value="<?= isset($data['asal_sekolah']) ? e($data['asal_sekolah']) : '' ?>" required>
                    <?php if (isset($errors['asal_sekolah'])): ?>
                    <p class="field-error"><?= e($errors['asal_sekolah']) ?></p>
                    <?php endif; ?>
                </div>
                <div class="form-group">
                    <label for="tahun_lulus">Tahun Lulus</label>
                    <select id="tahun_lulus" name="tahun_lulus" class="form-control">
                        <?php foreach ($tahunOptions as $thn): ?>
                        <option value="<?= $thn ?>"
                            <?= (isset($data['tahun_lulus']) && (int)$data['tahun_lulus'] === $thn) ? 'selected' : '' ?>>
                            <?= $thn ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label>Kemampuan Membaca Al-Qur'an <span class="req">*</span></label>
                    <div class="radio-group" style="flex-direction:column;gap:8px;">
                        <?php foreach ($labelQuran as $val => $lbl): ?>
                        <label class="radio-item" style="min-width:auto;text-align:left;flex:none;">
                            <input type="radio" name="kemampuan_quran" value="<?= e($val) ?>"
                                   <?= (!isset($data['kemampuan_quran']) && $val === 'bisa-membaca') ? 'checked' : '' ?>
                                   <?= (isset($data['kemampuan_quran']) && $data['kemampuan_quran'] === $val) ? 'checked' : '' ?>>
                            <?= e($lbl) ?>
                        </label>
                        <?php endforeach; ?>
                    </div>
                    <?php if (isset($errors['kemampuan_quran'])): ?>
                    <p class="field-error"><?= e($errors['kemampuan_quran']) ?></p>
                    <?php endif; ?>
                </div>
                <div class="form-group">
                    <label for="jumlah_hafalan">Jumlah Hafalan Saat Ini</label>
                    <input type="text" id="jumlah_hafalan" name="jumlah_hafalan" class="form-control"
                           placeholder="Contoh: Juz 30, atau belum hafal" maxlength="50"
                           value="<?= isset($data['jumlah_hafalan']) ? e($data['jumlah_hafalan']) : '' ?>">
                </div>
                <div class="form-group">
                    <label for="motivasi">Motivasi Masuk Pesantren</label>
                    <textarea id="motivasi" name="motivasi" rows="3" class="form-control"
                              placeholder="Ceritakan motivasi Anda masuk pesantren..."
                              style="resize:vertical;"><?= isset($data['motivasi']) ? e($data['motivasi']) : '' ?></textarea>
                </div>
                <div class="account-fields">
                    <h3>Buat Akun Portal Santri</h3>
                    <p>Password digunakan bersama nomor pendaftaran untuk login setelah formulir dikirim.</p>
                    <div class="form-row">
                        <div class="form-group"><label for="portal_password">Password <span class="req">*</span></label><input type="password" id="portal_password" name="portal_password" class="form-control<?= isset($errors['portal_password']) ? ' is-error' : '' ?>" minlength="8" autocomplete="new-password" required><small>Minimal 8 karakter.</small></div>
                        <div class="form-group"><label for="password_confirm">Ulangi Password <span class="req">*</span></label><input type="password" id="password_confirm" name="password_confirm" class="form-control<?= isset($errors['password_confirm']) ? ' is-error' : '' ?>" minlength="8" autocomplete="new-password" required></div>
                    </div>
                    <label class="form-note" style="display:flex;align-items:center;gap:6px;cursor:pointer;">
                        <input type="checkbox" id="lihatPassword" style="width:auto;"> Lihat password
                    </label>
                </div>

                <p class="form-note">
                    Dengan mendaftar, Anda menyetujui syarat dan ketentuan yang berlaku di Pondok Pesantren Ash-Shiddiq.
                </p>
                <div class="form-nav">
                    <button type="button" class="btn-prev" onclick="psbGoTo(2)">&larr; Kembali</button>
                    <button type="submit" class="btn-next">Kirim Pendaftaran</button>
                </div>
            </div>

        </form>

        <?php endif; ?>
    </div><!-- /.psb-form-card -->
</div><!-- /.psb-wrapper -->

<script>
(function () {
    var currentStep = 1;

    window.psbGoTo = function (step) {
        // Sembunyikan semua page
        document.querySelectorAll('.form-page').forEach(function(p){ p.classList.remove('active'); });
        var target = document.getElementById('page' + step);
        if (target) target.classList.add('active');

        // Update step indicators
        for (var i = 1; i <= 3; i++) {
            var num = document.getElementById('sNum' + i);
            var ind = document.getElementById('stepInd' + i);
            if (!num || !ind) continue;
            num.classList.remove('active','done');
            ind.classList.remove('active');
            if (i < step)      { num.classList.add('done');   num.textContent = '✓'; }
            else if (i === step){ num.classList.add('active'); num.textContent = i; ind.classList.add('active'); }
            else               { num.textContent = i; }
        }
        currentStep = step;
        var card = document.querySelector('.psb-form-card');
        if (card) card.scrollIntoView({ block: 'start', behavior: 'smooth' });
    };

    // Jika ada error, tampilkan step yang relevan lalu sorot field pertama yang salah
    <?php if (!empty($errors)): ?>
    var step2Fields = ['nama_ayah','nama_ibu','hp_ortu','alamat'];
    var step3Fields = ['asal_sekolah','tahun_lulus','kemampuan_quran','portal_password','password_confirm'];
    var errorKeys   = <?= json_encode(array_keys($errors)) ?>;
    if (errorKeys.some(function(k){ return step3Fields.indexOf(k) !== -1; })) {
        currentStep = 3;
    } else if (errorKeys.some(function(k){ return step2Fields.indexOf(k) !== -1; })) {
        currentStep = 2;
    }
    psbGoTo(currentStep);
    var firstError = document.querySelector('.form-page.active .is-error');
    if (firstError) firstError.scrollIntoView({ block: 'center', behavior: 'smooth' });
    <?php endif; ?>

    var lihatPassword = document.getElementById('lihatPassword');
    if (lihatPassword) {
        lihatPassword.addEventListener('change', function () {
            var type = this.checked ? 'text' : 'password';
            document.getElementById('portal_password').type = type;
            document.getElementById('password_confirm').type = type;
        });
    }
}());
</script>
