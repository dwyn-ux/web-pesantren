<?php
require_once __DIR__ . '/bootstrap.php';
requireAdmin();
$pdo = getDB();
$errors = [];

// Slot foto statis yang bisa diupload admin (kelompok home & profil)
$fotoSlots = [
    ['group'=>'home',   'field'=>'hero',      'dest'=>'hero-bg.jpg',                       'max'=>1920, 'label'=>'Foto Hero (background, lebar ~1920px)'],
    ['group'=>'home',   'field'=>'mudir',     'dest'=>'mudir.jpg',                         'max'=>600,  'label'=>'Foto Mudir (rasio 3:4, disarankan 600x800px)'],
    ['group'=>'profil', 'field'=>'gedung',    'dest'=>'profil/gedung-pesantren.jpg',       'max'=>1200, 'label'=>'Gedung Pesantren (rasio 4:3)'],
    ['group'=>'profil', 'field'=>'masjid',    'dest'=>'profil/masjid-pesantren.jpg',       'max'=>1200, 'label'=>'Masjid Pesantren (rasio 4:3)'],
    ['group'=>'profil', 'field'=>'asrama',    'dest'=>'profil/asrama-santri.jpg',          'max'=>1200, 'label'=>'Asrama Santri (rasio 4:3)'],
    ['group'=>'profil', 'field'=>'pengajar_1','dest'=>'pengajar/kh-ahmad-fauzi.jpg',       'max'=>400,  'label'=>'Pengajar 1 — KH. Ahmad Fauzi'],
    ['group'=>'profil', 'field'=>'pengajar_2','dest'=>'pengajar/usth-ina-rusiana.jpg',      'max'=>400,  'label'=>'Pengajar 2 — Usth. Ina Rusiana'],
    ['group'=>'profil', 'field'=>'pengajar_3','dest'=>'pengajar/ust-nurwidi-sasongko.jpg',  'max'=>400,  'label'=>'Pengajar 3 — USt. Nurwidi Sasongko'],
    ['group'=>'profil', 'field'=>'pengajar_4','dest'=>'pengajar/ust-nur-wahyudi.jpg',       'max'=>400,  'label'=>'Pengajar 4 — USt. Nur Wahyudi'],
    ['group'=>'profil', 'field'=>'fas_masjid','dest'=>'fasilitas/masjid.jpg',              'max'=>800,  'label'=>'Fasilitas — Masjid'],
    ['group'=>'profil', 'field'=>'fas_asrama','dest'=>'fasilitas/asrama.jpg',              'max'=>800,  'label'=>'Fasilitas — Asrama'],
    ['group'=>'profil', 'field'=>'fas_perpus','dest'=>'fasilitas/perpustakaan.jpg',        'max'=>800,  'label'=>'Fasilitas — Perpustakaan'],
    ['group'=>'profil', 'field'=>'fas_kelas','dest'=>'fasilitas/ruang-kelas.jpg',          'max'=>800,  'label'=>'Fasilitas — Ruang Kelas'],
    ['group'=>'profil', 'field'=>'fas_lab',   'dest'=>'fasilitas/lab-komputer.jpg',        'max'=>800,  'label'=>'Fasilitas — Lab Komputer'],
    ['group'=>'profil', 'field'=>'fas_klinik','dest'=>'fasilitas/klinik.jpg',              'max'=>800,  'label'=>'Fasilitas — Klinik Kesehatan'],
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    validateCsrf();
    $action = sanitizeString($_POST['action'] ?? 'upload');

    if ($action === 'delete') {
        // Hapus seluruh album = semua foto dengan judul yang sama
        $judul = (string) ($_POST['judul'] ?? '');
        if ($judul === '(tanpa judul)') $judul = '';

        if ($judul === '') {
            $stmt = $pdo->query("SELECT nama_file FROM foto_galeri WHERE judul IS NULL OR judul = ''");
            $files = array_column($stmt->fetchAll(), 'nama_file');
            $pdo->exec("DELETE FROM foto_galeri WHERE judul IS NULL OR judul = ''");
        } else {
            $stmt = $pdo->prepare('SELECT nama_file FROM foto_galeri WHERE judul = ?');
            $stmt->execute([$judul]);
            $files = array_column($stmt->fetchAll(), 'nama_file');
            $pdo->prepare('DELETE FROM foto_galeri WHERE judul = ?')->execute([$judul]);
        }

        foreach ($files as $f) {
            $path = UPLOADS_PATH . '/galeri/' . basename($f);
            if (is_file($path)) unlink($path);
        }
        setFlash('success', count($files) . ' foto dihapus dari galeri.');
        redirect('/admin/foto');
    }

    // ── Foto halaman depan & profil (hero, mudir, gedung, pengajar, fasilitas) ──
    if ($action === 'home' || $action === 'profil') {
        $savedHome = [];

        $saveHome = function (string $field, string $destRel, int $maxWidth) use (&$savedHome): void {
            if (empty($_FILES[$field]['name'])) return;
            $err = validateUpload($_FILES[$field], ['jpg', 'jpeg', 'png', 'webp'], ['image/jpeg', 'image/png', 'image/webp'], 10485760);
            if ($err) { $savedHome[] = basename($_FILES[$field]['name']) . ': ' . implode(' ', $err); return; }
            $dest = ROOT_PATH . '/assets/img/' . $destRel;
            @mkdir(dirname($dest), 0755, true);
            if (!resizeImage($_FILES[$field]['tmp_name'], $dest, $maxWidth, 88)) {
                $savedHome[] = basename($_FILES[$field]['name']) . ': gagal disimpan.';
            }
        };

        foreach ($fotoSlots as $slot) {
            if ($slot['group'] === 'home' && $action === 'home') $saveHome($slot['field'], $slot['dest'], $slot['max']);
            if ($slot['group'] === 'profil' && $action === 'profil') $saveHome($slot['field'], $slot['dest'], $slot['max']);
        }

        // Home page cek mudir.png dulu → hapus versi lama biar upload mudir.jpg tampil
        $oldMudirPng = ROOT_PATH . '/assets/img/mudir.png';
        if ($action === 'home' && is_file($oldMudirPng)) {
            unlink($oldMudirPng);
        }

        if ($savedHome) {
            setFlash('error', implode(' | ', $savedHome));
        } else {
            setFlash('success', 'Foto berhasil diperbarui.');
        }
        redirect('/admin/foto');
    }

    $judul = sanitizeString($_POST['judul'] ?? '');
    $files = $_FILES['foto'] ?? null;
    if (!$files || !isset($files['name']) || !is_array($files['name'])) {
        $errors[] = 'Pilih minimal satu foto.';
    } else {
        $uploaded = 0;
        foreach ($files['name'] as $i => $name) {
            if (($files['error'][$i] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) continue;
            $file = ['name'=>$name, 'type'=>$files['type'][$i] ?? '', 'tmp_name'=>$files['tmp_name'][$i] ?? '', 'error'=>$files['error'][$i] ?? UPLOAD_ERR_NO_FILE, 'size'=>$files['size'][$i] ?? 0];
            $validation = validateUpload($file, ['jpg','jpeg','png','webp'], ['image/jpeg','image/png','image/webp'], 10485760);
            if ($validation) { $errors[] = basename($name) . ': ' . implode(' ', $validation); continue; }
            $saved = saveUpload($file, UPLOADS_PATH . '/galeri');
            if (!$saved) { $errors[] = basename($name) . ': gagal disimpan.'; continue; }
            $caption = $judul !== '' ? $judul : pathinfo(basename($name), PATHINFO_FILENAME);
            $pdo->prepare('INSERT INTO foto_galeri (nama_file,judul,kategori,is_aktif) VALUES (?,?,?,1)')->execute([$saved,$caption,'kehidupan']);
            $uploaded++;
        }
        if ($uploaded && !$errors) { setFlash('success', $uploaded . ' foto berhasil ditambahkan ke galeri.'); redirect('/admin/foto'); }
    }
}

// Kelompokkan foto berdasarkan judul → satu album = satu baris
$rows = $pdo->query('SELECT nama_file, judul FROM foto_galeri ORDER BY created_at ASC, id ASC')->fetchAll();
$albums = [];
foreach ($rows as $r) {
    $key = trim((string) $r['judul']) !== '' ? $r['judul'] : '(tanpa judul)';
    if (!isset($albums[$key])) $albums[$key] = ['judul' => $key, 'cover' => $r['nama_file'], 'jumlah' => 0];
    $albums[$key]['jumlah']++;
}
$albums = array_reverse(array_values($albums)); // album terbaru di depan
$adminTitle = 'Galeri Foto'; $adminPage = 'admin/foto';
require __DIR__ . '/includes/header.php';
?>

<div class="admin-form-card">
  <h2 class="admin-form-title">Foto Halaman Depan</h2>
  <p class="muted">Klik <strong>Simpan</strong> untuk mengganti. File lama otomatis ditimpa.</p>
  <form method="post" enctype="multipart/form-data" class="admin-form" id="homePhotoForm">
    <input type="hidden" name="csrf_token" value="<?=generateCsrfToken()?>">
    <input type="hidden" name="action" value="home">
    <?php foreach (array_filter($fotoSlots, fn($s) => $s['group'] === 'home') as $slot): ?>
    <div class="form-group">
      <label><?=e($slot['label'])?></label>
      <?php if (imgExists($slot['dest'])): ?><div class="home-photo-preview"><img src="<?=e(imgUrl($slot['dest']))?>" alt="<?=e($slot['label'])?>"></div><?php endif; ?>
      <input class="form-control" type="file" name="<?=e($slot['field'])?>" accept=".jpg,.jpeg,.png,.webp">
    </div>
    <?php endforeach; ?>
    <div class="form-actions"><button class="btn-sm btn-sm-primary">Simpan Foto</button></div>
  </form>
  <p class="muted">Foto & isi testimoni halaman depan dikelola lewat menu <a href="<?= e(BASE_URL.'/admin/testimoni') ?>">Testimoni</a>.</p>
</div>

<div class="admin-form-card">
  <h2 class="admin-form-title">Foto Halaman Profil</h2>
  <p class="muted">Gedung, masjid, asrama, tim pengajar, dan fasilitas. Klik <strong>Simpan</strong> untuk mengganti.</p>
  <form method="post" enctype="multipart/form-data" class="admin-form" id="profilPhotoForm">
    <input type="hidden" name="csrf_token" value="<?=generateCsrfToken()?>">
    <input type="hidden" name="action" value="profil">
    <div class="form-row">
      <?php foreach (array_filter($fotoSlots, fn($s) => $s['group'] === 'profil') as $i => $slot): ?>
      <div class="form-group">
        <label><?=e($slot['label'])?></label>
        <?php if (imgExists($slot['dest'])): ?><div class="home-photo-preview"><img src="<?=e(imgUrl($slot['dest']))?>" alt="<?=e($slot['label'])?>"></div><?php endif; ?>
        <input class="form-control" type="file" name="<?=e($slot['field'])?>" accept=".jpg,.jpeg,.png,.webp">
      </div>
      <?php endforeach; ?>
    </div>
    <div class="form-actions"><button class="btn-sm btn-sm-primary">Simpan Foto</button></div>
  </form>
</div>

<div class="admin-form-card">
  <h2 class="admin-form-title">Upload Foto Galeri</h2>
  <?php if ($errors): ?><div class="flash-message flash-error"><?=e(implode(' ', $errors))?></div><?php endif; ?>
  <form method="post" enctype="multipart/form-data" class="admin-form">
    <input type="hidden" name="csrf_token" value="<?=generateCsrfToken()?>">
    <div class="form-row">
      <div class="form-group"><label>Nama Album *</label><input class="form-control" name="judul" placeholder="contoh: Rihlah" required><small>Foto dengan nama album yang sama akan tampil sebagai 1 kartu di halaman Galeri. Contoh: Rihlah, Wisuda, Halaqah.</small></div>
      <div class="form-group"><label>Foto *</label><input class="form-control" type="file" name="foto[]" accept=".jpg,.jpeg,.png,.webp" multiple required><small>Bisa pilih banyak foto sekaligus. Maksimal 10 MB per foto.</small></div>
    </div>
    <div class="form-actions"><button class="btn-sm btn-sm-primary">Simpan Album</button></div>
  </form>
</div>
<div class="admin-table-wrap"><div class="table-head"><h2>Daftar Album Galeri</h2></div><table class="admin-table"><thead><tr><th>Album</th><th>Jumlah Foto</th><th></th></tr></thead><tbody>
<?php if (!$albums): ?><tr><td colspan="3"><div class="table-empty">Belum ada foto galeri.</div></td></tr>
<?php else: foreach ($albums as $a): ?>
<tr>
  <td><div style="display:flex;align-items:center;gap:12px"><img src="<?=e(BASE_URL.'/uploads/galeri/'.$a['cover'])?>" alt="" style="width:90px;height:58px;object-fit:cover;border-radius:4px"><strong><?=e($a['judul'])?></strong></div></td>
  <td><?=$a['jumlah']?> foto</td>
  <td><form method="post" onsubmit="return confirm('Hapus album <?=e($a['judul'])?> beserta <?=$a['jumlah']?> fotonya?')"><input type="hidden" name="csrf_token" value="<?=generateCsrfToken()?>"><input type="hidden" name="action" value="delete"><input type="hidden" name="judul" value="<?=e($a['judul'])?>"><button class="btn-sm btn-sm-danger">Hapus Album</button></form></td>
</tr>
<?php endforeach; endif; ?>
</tbody></table></div>
<?php require __DIR__ . '/includes/footer.php'; ?>
