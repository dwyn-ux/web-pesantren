<?php
require_once __DIR__ . '/bootstrap.php';
requireAdmin();
$pdo = getDB();

$keys = ['rekening_pembayaran'];

// ── Proses simpan ────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    validateCsrf();

    // ── Upload logo (opsional) → logo.png / logo.svg ─────────
    if (!empty($_FILES['logo']['name'])) {
        $logoErr = validateUpload($_FILES['logo'], ['png', 'jpg', 'jpeg', 'webp', 'svg'], ['image/png', 'image/jpeg', 'image/webp', 'image/svg+xml'], 2097152);
        if ($logoErr) {
            setFlash('error', 'Logo: ' . implode(' ', $logoErr));
            redirect('/admin/pengaturan');
        }
        $ext  = strtolower(pathinfo($_FILES['logo']['name'], PATHINFO_EXTENSION));
        $base = ROOT_PATH . '/assets/img/logo';
        if ($ext === 'svg') {
            if (!move_uploaded_file($_FILES['logo']['tmp_name'], $base . '.svg')) {
                setFlash('error', 'Gagal menyimpan logo.');
                redirect('/admin/pengaturan');
            }
        } else {
            $src = @imagecreatefromstring((string) file_get_contents($_FILES['logo']['tmp_name']));
            if (!$src) {
                setFlash('error', 'File gambar tidak valid.');
                redirect('/admin/pengaturan');
            }
            $sw = imagesx($src); $sh = imagesy($src);
            $max = 512; $w = $sw; $h = $sh;
            if ($w > $max) { $ratio = $max / $w; $h = (int) round($sh * $ratio); $w = $max; }
            $canvas = imagecreatetruecolor($w, $h);
            imagealphablending($canvas, false);
            imagesavealpha($canvas, true);
            imagecopyresampled($canvas, $src, 0, 0, 0, 0, $w, $h, $sw, $sh);
            $ok = imagepng($canvas, $base . '.png', 6);
            imagedestroy($canvas);
            imagedestroy($src);
            if (!$ok) {
                setFlash('error', 'Gagal menyimpan logo.');
                redirect('/admin/pengaturan');
            }
        }
    }

    // Simpan nilai pengaturan (rekening dll)
    foreach ($keys as $k) {
        $v = sanitizeString($_POST[$k] ?? '');
        $pdo->prepare('INSERT INTO pengaturan (key_name,value,label) VALUES (?,?,?) ON DUPLICATE KEY UPDATE value=VALUES(value)')
            ->execute([$k, $v, $k]);
    }

    // Helper: ganti seluruh baris tarif untuk satu jenis
    $replaceTarif = function (string $jenis, array $rows) use ($pdo): void {
        $pdo->prepare('DELETE FROM pembiayaan_tarif WHERE jenis=?')->execute([$jenis]);
        $ins = $pdo->prepare(
            'INSERT INTO pembiayaan_tarif (jenis, nama, harga_asli, harga_diskon, gratis, gender, urutan) VALUES (?,?,?,?,?,?,?)'
        );
        $urutan = 0;
        foreach ($rows as $r) {
            $ins->execute([
                $jenis,
                $r['nama'] ?? null,
                max(0, (float) ($r['harga_asli'] ?? 0)),
                ($r['harga_diskon'] ?? '') !== '' ? max(0, (float) $r['harga_diskon']) : null,
                !empty($r['gratis']) ? 1 : 0,
                $r['gender'] ?? 'all',
                ++$urutan,
            ]);
        }
    };

    // Pendaftaran
    $replaceTarif('pendaftaran', [[
        'nama' => null,
        'harga_asli' => $_POST['pendaftaran_harga_asli'] ?? 0,
        'harga_diskon' => $_POST['pendaftaran_harga_diskon'] ?? '',
        'gratis' => !empty($_POST['pendaftaran_gratis']),
    ]]);

    // Administrasi (beberapa model)
    $adminRows = [];
    foreach (($_POST['administrasi_nama'] ?? []) as $i => $nama) {
        if (trim($nama) === '') continue;
        $adminRows[] = [
            'nama' => sanitizeString($nama),
            'harga_asli' => $_POST['administrasi_harga_asli'][$i] ?? 0,
            'harga_diskon' => $_POST['administrasi_harga_diskon'][$i] ?? '',
        ];
    }
    $replaceTarif('administrasi', $adminRows);

    // Wakaf (beberapa pilihan)
    $wakafRows = [];
    foreach (($_POST['wakaf_nama'] ?? []) as $i => $nama) {
        if (trim($nama) === '') continue;
        $wakafRows[] = [
            'nama' => sanitizeString($nama),
            'harga_asli' => $_POST['wakaf_harga_asli'][$i] ?? 0,
            'harga_diskon' => $_POST['wakaf_harga_diskon'][$i] ?? '',
        ];
    }
    $replaceTarif('wakaf', $wakafRows);

    // Syahriyah (biaya bulanan, beberapa pilihan)
    $syahriyahRows = [];
    foreach (($_POST['syahriyah_nama'] ?? []) as $i => $nama) {
        if (trim($nama) === '') continue;
        $syahriyahRows[] = [
            'nama' => sanitizeString($nama),
            'harga_asli' => $_POST['syahriyah_harga_asli'][$i] ?? 0,
            'harga_diskon' => $_POST['syahriyah_harga_diskon'][$i] ?? '',
        ];
    }
    $replaceTarif('syahriyah', $syahriyahRows);

    // Laundry (L/P)
    $replaceTarif('laundry', [
        ['nama' => 'Laundry Santri', 'harga_asli' => $_POST['laundry_harga_l'] ?? 0, 'gratis' => false, 'gender' => 'L'],
        ['nama' => 'Laundry Santri', 'harga_asli' => $_POST['laundry_harga_p'] ?? 0, 'gratis' => false, 'gender' => 'P'],
    ]);

    // Infak wajib
    $replaceTarif('infak', [[
        'nama' => 'Infak Wajib',
        'harga_asli' => $_POST['infak_harga_asli'] ?? 0,
        'harga_diskon' => $_POST['infak_harga_diskon'] ?? '',
        'gratis' => false,
    ]]);

    setFlash('success', 'Pengaturan pembiayaan berhasil disimpan.');
    redirect('/admin/pengaturan');
}

// ── Baca data ────────────────────────────────────────────────
$data = [];
$s = $pdo->prepare('SELECT key_name, value FROM pengaturan WHERE key_name IN (' . implode(',', array_fill(0, count($keys), '?')) . ')');
$s->execute($keys);
foreach ($s->fetchAll() as $r) $data[$r['key_name']] = $r['value'];

$tarif = getPembiayaanTarif($pdo);
$pendaftaran = $tarif['pendaftaran'][0] ?? [];
$administrasi = $tarif['administrasi'];
$wakaf = $tarif['wakaf'];
$syahriyah = $tarif['syahriyah'];
$laundry = ['L' => null, 'P' => null];
foreach ($tarif['laundry'] as $t) $laundry[$t['gender']] = $t;
$infak = $tarif['infak'][0] ?? [];

$adminTitle = 'Pengaturan Website';
$adminPage  = 'admin/pengaturan';
require __DIR__ . '/includes/header.php';
?>

<form method="post" class="admin-form" enctype="multipart/form-data">
<input type="hidden" name="csrf_token" value="<?= generateCsrfToken() ?>">

<div class="admin-form-card">
    <h2 class="admin-form-title">Logo Website &amp; Favicon</h2>
    <?php $logoPreview = getLogoFile(); if ($logoPreview !== ''): ?>
    <p style="font-size:13px;color:var(--text-mid);margin-bottom:10px;">Logo saat ini:</p>
    <img src="<?= BASE_URL ?>/assets/img/<?= $logoPreview ?>" alt="Logo" style="height:64px;width:auto;background:#fff;padding:8px;border:1px solid var(--cream-dark);border-radius:8px;margin-bottom:14px;">
    <?php endif; ?>
    <div class="form-group"><label>Upload logo baru (PNG/JPG/WEBP/SVG, maks 2 MB)</label>
        <input class="form-control" type="file" name="logo" accept=".png,.jpg,.jpeg,.webp,.svg">
    </div>
    <p style="font-size:12px;color:var(--text-light);">Logo otomatis dipakai sebagai favicon website. Disarankan rasio kotak (mis. 512x512).</p>
</div>

<div class="admin-form-card">
    <h2 class="admin-form-title">Pembiayaan — Biaya Pendaftaran</h2>
    <div class="form-row">
        <div class="form-group"><label>Harga asli</label><input class="form-control" type="number" min="0" name="pendaftaran_harga_asli" value="<?= e($pendaftaran['harga_asli'] ?? '2500000') ?>"></div>
        <div class="form-group"><label>Harga diskon (opsional, tampil coret)</label><input class="form-control" type="number" min="0" name="pendaftaran_harga_diskon" value="<?= isset($pendaftaran['harga_diskon']) ? e($pendaftaran['harga_diskon']) : '' ?>" placeholder="Kosongkan jika tanpa diskon"></div>
        <div class="form-group"><label><input type="checkbox" name="pendaftaran_gratis" value="1" style="width:auto;margin-right:6px;" <?= !empty($pendaftaran['gratis']) ? 'checked' : '' ?>>Gratiskan (tidak perlu bayar)</label></div>
    </div>
    <p style="font-size:12px;color:var(--text-light);">Jika gratis dicentang, santri baru otomatis tercentang tanpa membayar.</p>
</div>

<div class="admin-form-card">
    <h2 class="admin-form-title">Pembiayaan — Administrasi Awal (Kesanggupan)</h2>
    <p style="font-size:13px;color:var(--text-mid);margin-bottom:14px;">Hanya mencatat kesanggupan santri, bukan pembayaran. Santri memilih satu model administrasi dan satu pilihan wakaf.</p>

    <h3 style="font-size:14px;font-weight:600;margin:0 0 10px;">Administrasi (beberapa model)</h3>
    <div id="administrasi-rows">
        <?php foreach ($administrasi as $t): ?>
            <div class="form-row">
                <div class="form-group"><input class="form-control" name="administrasi_nama[]" placeholder="Nama model" value="<?= e($t['nama']) ?>" required></div>
                <div class="form-group"><input class="form-control" type="number" min="0" name="administrasi_harga_asli[]" placeholder="Harga asli" value="<?= e($t['harga_asli']) ?>" required></div>
                <div class="form-group"><input class="form-control" type="number" min="0" name="administrasi_harga_diskon[]" placeholder="Harga diskon (opsional)" value="<?= isset($t['harga_diskon']) ? e($t['harga_diskon']) : '' ?>"></div>
                <button type="button" class="btn-sm btn-sm-secondary" onclick="this.parentElement.remove()">Hapus</button>
            </div>
        <?php endforeach; ?>
    </div>
    <button type="button" class="btn-sm btn-sm-secondary" onclick="addRow('administrasi')">+ Tambah model</button>

    <h3 style="font-size:14px;font-weight:600;margin:22px 0 10px;">Wakaf (beberapa pilihan)</h3>
    <div id="wakaf-rows">
        <?php foreach ($wakaf as $t): ?>
            <div class="form-row">
                <div class="form-group"><input class="form-control" name="wakaf_nama[]" placeholder="Nama pilihan" value="<?= e($t['nama']) ?>" required></div>
                <div class="form-group"><input class="form-control" type="number" min="0" name="wakaf_harga_asli[]" placeholder="Harga asli" value="<?= e($t['harga_asli']) ?>" required></div>
                <div class="form-group"><input class="form-control" type="number" min="0" name="wakaf_harga_diskon[]" placeholder="Harga diskon (opsional)" value="<?= isset($t['harga_diskon']) ? e($t['harga_diskon']) : '' ?>"></div>
                <button type="button" class="btn-sm btn-sm-secondary" onclick="this.parentElement.remove()">Hapus</button>
            </div>
        <?php endforeach; ?>
    </div>
    <button type="button" class="btn-sm btn-sm-secondary" onclick="addRow('wakaf')">+ Tambah pilihan</button>

    <h3 style="font-size:14px;font-weight:600;margin:22px 0 10px;">Biaya Syahriyah (Bulanan) — beberapa pilihan</h3>
    <div id="syahriyah-rows">
        <?php foreach ($syahriyah as $t): ?>
            <div class="form-row">
                <div class="form-group"><input class="form-control" name="syahriyah_nama[]" placeholder="Nama pilihan" value="<?= e($t['nama']) ?>" required></div>
                <div class="form-group"><input class="form-control" type="number" min="0" name="syahriyah_harga_asli[]" placeholder="Harga asli" value="<?= e($t['harga_asli']) ?>" required></div>
                <div class="form-group"><input class="form-control" type="number" min="0" name="syahriyah_harga_diskon[]" placeholder="Harga diskon (opsional)" value="<?= isset($t['harga_diskon']) ? e($t['harga_diskon']) : '' ?>"></div>
                <button type="button" class="btn-sm btn-sm-secondary" onclick="this.parentElement.remove()">Hapus</button>
            </div>
        <?php endforeach; ?>
    </div>
    <button type="button" class="btn-sm btn-sm-secondary" onclick="addRow('syahriyah')">+ Tambah pilihan</button>

    <h3 style="font-size:14px;font-weight:600;margin:22px 0 10px;">Laundry (beda laki-laki/perempuan)</h3>
    <div class="form-row">
        <div class="form-group"><label>Laki-laki</label><input class="form-control" type="number" min="0" name="laundry_harga_l" value="<?= e($laundry['L']['harga_asli'] ?? '') ?>"></div>
        <div class="form-group"><label>Perempuan</label><input class="form-control" type="number" min="0" name="laundry_harga_p" value="<?= e($laundry['P']['harga_asli'] ?? '') ?>"></div>
    </div>

    <h3 style="font-size:14px;font-weight:600;margin:22px 0 10px;">Infak Wajib</h3>
    <div class="form-row">
        <div class="form-group"><label>Harga asli</label><input class="form-control" type="number" min="0" name="infak_harga_asli" value="<?= e($infak['harga_asli'] ?? '') ?>"></div>
        <div class="form-group"><label>Harga diskon (opsional)</label><input class="form-control" type="number" min="0" name="infak_harga_diskon" value="<?= isset($infak['harga_diskon']) ? e($infak['harga_diskon']) : '' ?>"></div>
    </div>
</div>

<div class="admin-form-card">
    <h2 class="admin-form-title">Rekening Pembayaran</h2>
    <div class="form-group"><label>Rekening tujuan transfer</label><textarea class="form-control" name="rekening_pembayaran"><?= e($data['rekening_pembayaran'] ?? '') ?></textarea></div>
</div>

<button class="btn-sm btn-sm-primary">Simpan Pengaturan</button>
</form>

<script>
function addRow(kind) {
    var wrap = document.getElementById(kind + '-rows');
    var div = document.createElement('div');
    div.className = 'form-row';
    div.innerHTML = ''
        + '<div class="form-group"><input class="form-control" name="' + kind + '_nama[]" placeholder="Nama model" required></div>'
        + '<div class="form-group"><input class="form-control" type="number" min="0" name="' + kind + '_harga_asli[]" placeholder="Harga asli" required></div>'
        + '<div class="form-group"><input class="form-control" type="number" min="0" name="' + kind + '_harga_diskon[]" placeholder="Harga diskon (opsional)"></div>'
        + '<button type="button" class="btn-sm btn-sm-secondary" onclick="this.parentElement.remove()">Hapus</button>';
    wrap.appendChild(div);
}
</script>

<?php require __DIR__ . '/includes/footer.php'; ?>
