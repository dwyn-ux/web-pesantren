<?php
require_once __DIR__ . '/bootstrap.php';
requireAdmin();
$pdo = getDB();

// Peta gelombang untuk label + validasi tarif per tahap.
// Kunci: id gelombang, nilai: label. Tanpa ini, simpan pengaturan
// akan menghapus mapping gelombang tarif (bug: semua varian
// Indent/G1/G2/G3 masuk tagihan).
$gelombangMap = [];
foreach (getAllGelombang($pdo, false) as $g) {
    $gelombangMap[(int) $g['id']] = $g['label'];
}

// Render <select> tahap untuk satu baris tarif (pendaftaran/administrasi/wakaf).
// Nilai "" = berlaku semua tahap (baris global).
$opsiGelombang = function (string $name, $terpilih) use ($gelombangMap): string {
    $html = '<div class="form-group"><label>Tahap</label><select class="form-control" name="' . $name . '">';
    $html .= '<option value="">Semua tahap</option>';
    foreach ($gelombangMap as $gid => $glabel) {
        $sel = ((string) ($terpilih ?? '') !== '' && (int) $terpilih === $gid) ? ' selected' : '';
        $html .= '<option value="' . $gid . '"' . $sel . '>' . e($glabel) . '</option>';
    }
    return $html . '</select></div>';
};

$keys = ['rekening_pembayaran', 'telegram_chat_ids', 'kontak_alamat', 'kop_alamat', 'kontak_whatsapp', 'kontak_email', 'kontak_telepon', 'map_latitude', 'map_longitude', 'map_zoom'];

// ── Proses simpan ────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    validateCsrf();

    // ── Tes notifikasi Telegram ──
    if (($_POST['aksi'] ?? '') === 'tes_telegram') {
        $ok = kirimTelegram('Tes notifikasi bot PSB Ash-Shiddiq. Bot terhubung.', $pdo);
        setFlash($ok ? 'success' : 'error', $ok
            ? 'Tes terkirim. Cek grup/chat Telegram.'
            : 'Tes gagal. Pastikan token di .env dan chat ID terisi, lalu bot sudah di-chat/diundang ke grup.');
        redirect('/admin/pengaturan');
    }

    // ── Upload logo (opsional) → logo.png (SVG ditolak: vektor inline XSS) ──
    if (!empty($_FILES['logo']['name'])) {
        $logoErr = validateUpload($_FILES['logo'], ['png', 'jpg', 'jpeg', 'webp'], ['image/png', 'image/jpeg', 'image/webp'], 2097152);
        if ($logoErr) {
            setFlash('error', 'Logo: ' . implode(' ', $logoErr));
            redirect('/admin/pengaturan');
        }
        $base = ROOT_PATH . '/assets/img/logo';
        // Hapus logo.svg lama agar tidak jadi vektor XSS persisten
        if (is_file($base . '.svg')) @unlink($base . '.svg');
        {
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
        if ($k === 'telegram_chat_ids') {
            // Hanya digit/negatif/koma/spasi yang lolos
            $parts = array_filter(array_map('trim', explode(',', $v)));
            $valid = [];
            foreach ($parts as $p) {
                if (preg_match('/^-?\d+$/', $p)) $valid[] = $p;
            }
            $v = implode(',', array_values(array_unique($valid)));
        }
        if (in_array($k, ['map_latitude', 'map_longitude', 'map_zoom'], true)) {
            $v = preg_replace('/[^0-9.\-]/', '', $v) ?? '';
        }
        $pdo->prepare('INSERT INTO pengaturan (key_name,value,label) VALUES (?,?,?) ON DUPLICATE KEY UPDATE value=VALUES(value)')
            ->execute([$k, $v, $k]);
    }

    // Helper: ganti seluruh baris tarif untuk satu jenis.
    // gelombang_id dipertahankan (NULL = berlaku semua tahap) agar
    // tarif per gelombang (Indent/G1/G2/G3) tidak hilang saat simpan.
    $replaceTarif = function (string $jenis, array $rows) use ($pdo, $gelombangMap): void {
        $pdo->prepare('DELETE FROM pembiayaan_tarif WHERE jenis=?')->execute([$jenis]);
        $ins = $pdo->prepare(
            'INSERT INTO pembiayaan_tarif (jenis, gelombang_id, nama, harga_asli, harga_diskon, gratis, gender, urutan) VALUES (?,?,?,?,?,?,?,?)'
        );
        $urutan = 0;
        foreach ($rows as $r) {
            $gid = isset($r['gelombang_id']) && (string) $r['gelombang_id'] !== '' ? (int) $r['gelombang_id'] : null;
            // Tolak id gelombang yang tidak dikenal (cegah baris yatim).
            if ($gid !== null && !isset($gelombangMap[$gid])) $gid = null;
            $ins->execute([
                $jenis,
                $gid,
                $r['nama'] ?? null,
                max(0, (float) ($r['harga_asli'] ?? 0)),
                ($r['harga_diskon'] ?? '') !== '' ? max(0, (float) $r['harga_diskon']) : null,
                !empty($r['gratis']) ? 1 : 0,
                $r['gender'] ?? 'all',
                ++$urutan,
            ]);
        }
    };

    // Pendaftaran (per gelombang: Indent/G1/G2/G3 bisa beda nominal)
    $pendaftaranRows = [];
    foreach (($_POST['pendaftaran_nama'] ?? []) as $i => $nama) {
        $harga = trim((string) ($_POST['pendaftaran_harga_asli'][$i] ?? ''));
        if (trim((string) $nama) === '' && $harga === '') continue;
        $pendaftaranRows[] = [
            'nama' => trim((string) $nama) !== '' ? sanitizeString((string) $nama) : null,
            'harga_asli' => $harga,
            'harga_diskon' => $_POST['pendaftaran_harga_diskon'][$i] ?? '',
            'gratis' => !empty($_POST['pendaftaran_gratis'][$i]),
            'gelombang_id' => $_POST['pendaftaran_gelombang_id'][$i] ?? null,
        ];
    }
    $replaceTarif('pendaftaran', $pendaftaranRows);

    // Administrasi (beberapa model)
    $adminRows = [];
    foreach (($_POST['administrasi_nama'] ?? []) as $i => $nama) {
        if (trim($nama) === '') continue;
        $adminRows[] = [
            'nama' => sanitizeString($nama),
            'harga_asli' => $_POST['administrasi_harga_asli'][$i] ?? 0,
            'harga_diskon' => $_POST['administrasi_harga_diskon'][$i] ?? '',
            'gelombang_id' => $_POST['administrasi_gelombang_id'][$i] ?? null,
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
            'gelombang_id' => $_POST['wakaf_gelombang_id'][$i] ?? null,
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
$pendaftaranRows = $tarif['pendaftaran'];
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
    <p class="muted">Logo saat ini:</p>
    <img src="<?= BASE_URL ?>/assets/img/<?= $logoPreview ?>" alt="Logo" style="height:64px;width:auto;background:#fff;padding:8px;border:1px solid var(--cream-dark);border-radius:8px;margin-bottom:14px;">
    <?php endif; ?>
    <div class="form-group"><label>Upload logo baru (PNG/JPG/WEBP, maks 2 MB)</label>
        <input class="form-control" type="file" name="logo" accept=".png,.jpg,.jpeg,.webp">
    </div>
    <p class="muted">Logo otomatis dipakai sebagai favicon website. Disarankan rasio kotak (mis. 512x512).</p>
</div>

<div class="admin-form-card">
    <h2 class="admin-form-title">Pembiayaan — Biaya Pendaftaran</h2>
    <p class="muted">Satu baris per tahap (Indent/G1/G2/G3) — pilih Tahap tiap baris. Baris "Semua tahap" berlaku untuk semua gelombang.</p>
    <div id="pendaftaran-rows">
        <?php foreach ($pendaftaranRows as $t): ?>
            <div class="form-row">
                <?= $opsiGelombang('pendaftaran_gelombang_id[]', $t['gelombang_id'] ?? null) ?>
                <div class="form-group"><label>Nama</label><input class="form-control" name="pendaftaran_nama[]" placeholder="Nama biaya" value="<?= e($t['nama'] ?? '') ?>"></div>
                <div class="form-group"><label>Harga asli</label><input class="form-control" type="number" min="0" name="pendaftaran_harga_asli[]" value="<?= e($t['harga_asli'] ?? '0') ?>"></div>
                <div class="form-group"><label>Harga diskon (opsional)</label><input class="form-control" type="number" min="0" name="pendaftaran_harga_diskon[]" value="<?= isset($t['harga_diskon']) ? e($t['harga_diskon']) : '' ?>" placeholder="Kosongkan jika tanpa diskon"></div>
                <div class="form-group"><label>Gratis?</label><select class="form-control" name="pendaftaran_gratis[]"><option value="0">Bayar</option><option value="1" <?= !empty($t['gratis']) ? 'selected' : '' ?>>Gratis</option></select></div>
                <button type="button" class="btn-sm btn-sm-danger" onclick="this.parentElement.remove()">Hapus</button>
            </div>
        <?php endforeach; ?>
    </div>
    <button type="button" class="btn-sm btn-sm-secondary" onclick="addRow('pendaftaran')">+ Tambah baris</button>
    <p class="muted">Jika gratis dicentang, santri baru otomatis tercentang tanpa membayar.</p>
</div>

<div class="admin-form-card">
    <h2 class="admin-form-title">Pembiayaan — Administrasi Awal (Kesanggupan)</h2>
    <p class="muted">Satu baris per tahap (Indent/G1/G2/G3) — pilih Tahap tiap baris agar tagihan sesuai gelombang pendaftar. Baris "Semua tahap" berlaku untuk semua gelombang.</p>

    <h3>Administrasi (beberapa model)</h3>
    <div id="administrasi-rows">
        <?php foreach ($administrasi as $t): ?>
            <div class="form-row">
                <?= $opsiGelombang('administrasi_gelombang_id[]', $t['gelombang_id'] ?? null) ?>
                <div class="form-group"><input class="form-control" name="administrasi_nama[]" placeholder="Nama model" value="<?= e($t['nama']) ?>" required></div>
                <div class="form-group"><input class="form-control" type="number" min="0" name="administrasi_harga_asli[]" placeholder="Harga asli" value="<?= e($t['harga_asli']) ?>" required></div>
                <div class="form-group"><input class="form-control" type="number" min="0" name="administrasi_harga_diskon[]" placeholder="Harga diskon (opsional)" value="<?= isset($t['harga_diskon']) ? e($t['harga_diskon']) : '' ?>"></div>
                <button type="button" class="btn-sm btn-sm-danger" onclick="this.parentElement.remove()">Hapus</button>
            </div>
        <?php endforeach; ?>
    </div>
    <button type="button" class="btn-sm btn-sm-secondary" onclick="addRow('administrasi')">+ Tambah model</button>

    <h3>Wakaf (beberapa pilihan)</h3>
    <div id="wakaf-rows">
        <?php foreach ($wakaf as $t): ?>
            <div class="form-row">
                <?= $opsiGelombang('wakaf_gelombang_id[]', $t['gelombang_id'] ?? null) ?>
                <div class="form-group"><input class="form-control" name="wakaf_nama[]" placeholder="Nama pilihan" value="<?= e($t['nama']) ?>" required></div>
                <div class="form-group"><input class="form-control" type="number" min="0" name="wakaf_harga_asli[]" placeholder="Harga asli" value="<?= e($t['harga_asli']) ?>" required></div>
                <div class="form-group"><input class="form-control" type="number" min="0" name="wakaf_harga_diskon[]" placeholder="Harga diskon (opsional)" value="<?= isset($t['harga_diskon']) ? e($t['harga_diskon']) : '' ?>"></div>
                <button type="button" class="btn-sm btn-sm-danger" onclick="this.parentElement.remove()">Hapus</button>
            </div>
        <?php endforeach; ?>
    </div>
    <button type="button" class="btn-sm btn-sm-secondary" onclick="addRow('wakaf')">+ Tambah pilihan</button>

    <h3>Biaya Syahriyah (Bulanan) — beberapa pilihan</h3>
    <div id="syahriyah-rows">
        <?php foreach ($syahriyah as $t): ?>
            <div class="form-row">
                <div class="form-group"><input class="form-control" name="syahriyah_nama[]" placeholder="Nama pilihan" value="<?= e($t['nama']) ?>" required></div>
                <div class="form-group"><input class="form-control" type="number" min="0" name="syahriyah_harga_asli[]" placeholder="Harga asli" value="<?= e($t['harga_asli']) ?>" required></div>
                <div class="form-group"><input class="form-control" type="number" min="0" name="syahriyah_harga_diskon[]" placeholder="Harga diskon (opsional)" value="<?= isset($t['harga_diskon']) ? e($t['harga_diskon']) : '' ?>"></div>
                <button type="button" class="btn-sm btn-sm-danger" onclick="this.parentElement.remove()">Hapus</button>
            </div>
        <?php endforeach; ?>
    </div>
    <button type="button" class="btn-sm btn-sm-secondary" onclick="addRow('syahriyah')">+ Tambah pilihan</button>

    <h3>Laundry (beda laki-laki/perempuan)</h3>
    <div class="form-row">
        <div class="form-group"><label>Laki-laki</label><input class="form-control" type="number" min="0" name="laundry_harga_l" value="<?= e($laundry['L']['harga_asli'] ?? '') ?>"></div>
        <div class="form-group"><label>Perempuan</label><input class="form-control" type="number" min="0" name="laundry_harga_p" value="<?= e($laundry['P']['harga_asli'] ?? '') ?>"></div>
    </div>

    <h3>Infak Wajib</h3>
    <div class="form-row">
        <div class="form-group"><label>Harga asli</label><input class="form-control" type="number" min="0" name="infak_harga_asli" value="<?= e($infak['harga_asli'] ?? '') ?>"></div>
        <div class="form-group"><label>Harga diskon (opsional)</label><input class="form-control" type="number" min="0" name="infak_harga_diskon" value="<?= isset($infak['harga_diskon']) ? e($infak['harga_diskon']) : '' ?>"></div>
    </div>
</div>

<div class="admin-form-card">
    <h2 class="admin-form-title">Rekening Pembayaran</h2>
    <div class="form-group"><label>Rekening tujuan transfer</label><textarea class="form-control" name="rekening_pembayaran"><?= e($data['rekening_pembayaran'] ?? '') ?></textarea></div>
</div>

<div class="admin-form-card">
    <h2 class="admin-form-title">Kontak & Alamat</h2>
    <div class="form-group"><label>Nomor WhatsApp</label><input class="form-control" name="kontak_whatsapp" placeholder="6281234567890" value="<?= e($data['kontak_whatsapp'] ?? '') ?>" /></div>
    <div class="form-group"><label>Email</label><input class="form-control" type="email" name="kontak_email" value="<?= e($data['kontak_email'] ?? '') ?>" /></div>
    <div class="form-group"><label>Nomor Telepon</label><input class="form-control" name="kontak_telepon" value="<?= e($data['kontak_telepon'] ?? '') ?>" /></div>
    <div class="form-group"><label>Addr Lengkap (tampil di Beranda + Kop Surat)</label><textarea class="form-control" name="kontak_alamat" rows="2" placeholder="Jl. Pesantren No. 1, Kab. Ciamis, Jawa Barat" value="<?= e($data['kontak_alamat'] ?? '') ?>"
    ></textarea></div>
    <div class="form-group"><label>Kop Surat (alamat di kertas surat)</label><textarea class="form-control" name="kop_alamat" rows="2" placeholder="Jl. Pesantren No. 1, Kab. Ciamis, Jawa Barat 46271" value="<?= e($data['kop_alamat'] ?? '') ?>"
    ></textarea></div>
    <div class="form-group"><label>Peta (latitude, longitude, zoom)</label>
    <div class="form-row">
        <div class="form-group"><input class="form-control" name="map_latitude" placeholder="latitude" value="<?= e($data['map_latitude'] ?? '') ?>" /></div>
        <div class="form-group"><input class="form-control" name="map_longitude" placeholder="longitude" value="<?= e($data['map_longitude'] ?? '') ?>" /></div>
        <div class="form-group"><input class="form-control" name="map_zoom" placeholder="zoom" value="<?= e($data['map_zoom'] ?? '') ?>" /></div>
    </div></div>
</div>

<div class="admin-form-card">
    <h2 class="admin-form-title">Notifikasi Telegram</h2>
    <div class="form-group"><label>Chat ID tujuan (grup + pribadi, pisahkan koma)</label><input class="form-control" name="telegram_chat_ids" placeholder="cth: -1001234567890, 123456789" value="<?= e($data['telegram_chat_ids'] ?? '') ?>">
    <p class="muted">Cara isi: chat bot 1x, undang bot ke grup panitia sebagai admin, lalu lihat chat ID via tombol Tes di bawah atau getUpdates. Token bot disimpan di .env (TELEGRAM_BOT_TOKEN).</p>
</div>

<div class="form-actions">
<button class="btn-sm btn-sm-primary">Simpan Pengaturan</button>
</div>
</form>

<form method="post" style="margin-top:16px;">
<input type="hidden" name="csrf_token" value="<?= generateCsrfToken() ?>">
<input type="hidden" name="aksi" value="tes_telegram">
<button class="btn-sm btn-sm-secondary">Kirim Tes Telegram</button>
</form>

<script>
var GELOMBANG_MAP = <?= json_encode($gelombangMap, JSON_UNESCAPED_UNICODE) ?>;
function escHtml(s) {
    return String(s).replace(/[&<>"']/g, function (c) {
        return {'&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;'}[c];
    });
}
function gelombangSelectHtml(kind) {
    if (kind !== 'pendaftaran' && kind !== 'administrasi' && kind !== 'wakaf') return '';
    var html = '<div class="form-group"><label>Tahap</label><select class="form-control" name="' + kind + '_gelombang_id[]">';
    html += '<option value="">Semua tahap</option>';
    for (var gid in GELOMBANG_MAP) {
        if (Object.prototype.hasOwnProperty.call(GELOMBANG_MAP, gid)) {
            html += '<option value="' + gid + '">' + escHtml(GELOMBANG_MAP[gid]) + '</option>';
        }
    }
    return html + '</select></div>';
}
function addRow(kind) {
    var wrap = document.getElementById(kind + '-rows');
    var div = document.createElement('div');
    div.className = 'form-row';
    div.innerHTML = ''
        + gelombangSelectHtml(kind)
        + '<div class="form-group"><input class="form-control" name="' + kind + '_nama[]" placeholder="Nama model" required></div>'
        + '<div class="form-group"><input class="form-control" type="number" min="0" name="' + kind + '_harga_asli[]" placeholder="Harga asli" required></div>'
        + '<div class="form-group"><input class="form-control" type="number" min="0" name="' + kind + '_harga_diskon[]" placeholder="Harga diskon (opsional)"></div>'
        + (kind === 'pendaftaran' ? '<div class="form-group"><label>Gratis?</label><select class="form-control" name="pendaftaran_gratis[]"><option value="0">Bayar</option><option value="1">Gratis</option></select></div>' : '')
        + '<button type="button" class="btn-sm btn-sm-danger" onclick="this.parentElement.remove()">Hapus</button>';
    wrap.appendChild(div);
}
</script>

<?php require __DIR__ . '/includes/footer.php'; ?>
