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
    ['group'=>'profil', 'field'=>'pengajar_2','dest'=>'pengajar/ust-abdul-aziz.jpg',       'max'=>400,  'label'=>'Pengajar 2 — Ust. Abdul Aziz'],
    ['group'=>'profil', 'field'=>'pengajar_3','dest'=>'pengajar/ust-muhammad-hasan.jpg',   'max'=>400,  'label'=>'Pengajar 3 — Ust. Muhammad Hasan'],
    ['group'=>'profil', 'field'=>'pengajar_4','dest'=>'pengajar/ust-yahya-basri.jpg',      'max'=>400,  'label'=>'Pengajar 4 — Ust. Yahya Basri'],
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
        $id = sanitizeInt($_POST['id'] ?? 0);
        $stmt = $pdo->prepare('SELECT nama_file FROM foto_galeri WHERE id=?');
        $stmt->execute([$id]);
        if ($foto = $stmt->fetch()) {
            $pdo->prepare('DELETE FROM foto_galeri WHERE id=?')->execute([$id]);
            $path = UPLOADS_PATH . '/galeri/' . basename($foto['nama_file']);
            if (is_file($path)) unlink($path);
        }
        setFlash('success', 'Foto galeri dihapus.');
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
        if ($uploaded && !$errors) { setFlash('success', $uploaded . ' foto berhasil ditambahkan ke carousel.'); redirect('/admin/foto'); }
    }
}

$items = $pdo->query('SELECT * FROM foto_galeri ORDER BY urutan ASC, created_at DESC')->fetchAll();
$adminTitle = 'Galeri Foto'; $adminPage = 'admin/foto';
require __DIR__ . '/includes/header.php';
?>

<div class="admin-form-card">
  <h2 class="admin-form-title">Foto Halaman Depan</h2>
  <p style="font-size:13px;color:var(--text-mid);margin-bottom:14px;">Klik <strong>Simpan</strong> untuk mengganti. File lama otomatis ditimpa.</p>
  <form method="post" enctype="multipart/form-data" class="admin-form" id="homePhotoForm">
    <input type="hidden" name="csrf_token" value="<?=generateCsrfToken()?>">
    <input type="hidden" name="action" value="home">
    <?php foreach (array_filter($fotoSlots, fn($s) => $s['group'] === 'home') as $slot): ?>
    <div class="form-group">
      <label><?=e($slot['label'])?></label>
      <?php if (imgExists($slot['dest'])): ?><div class="home-photo-preview"><img src="<?=e(BASE_URL.'/assets/img/'.$slot['dest'])?>" alt="<?=e($slot['label'])?>"></div><?php endif; ?>
      <input class="form-control" type="file" name="<?=e($slot['field'])?>" accept=".jpg,.jpeg,.png,.webp">
    </div>
    <?php endforeach; ?>
    <div class="form-actions"><button class="btn-sm btn-sm-primary">Simpan Foto</button></div>
  </form>
  <p style="font-size:12px;color:var(--text-light);margin-top:14px;">Foto testimoni halaman depan diambil otomatis dari data alumni (foto yang diunggah lewat pendataan alumni).</p>
</div>

<div class="admin-form-card">
  <h2 class="admin-form-title">Foto Halaman Profil</h2>
  <p style="font-size:13px;color:var(--text-mid);margin-bottom:14px;">Gedung, masjid, asrama, tim pengajar, dan fasilitas. Klik <strong>Simpan</strong> untuk mengganti.</p>
  <form method="post" enctype="multipart/form-data" class="admin-form" id="profilPhotoForm">
    <input type="hidden" name="csrf_token" value="<?=generateCsrfToken()?>">
    <input type="hidden" name="action" value="profil">
    <div class="form-row">
      <?php foreach (array_filter($fotoSlots, fn($s) => $s['group'] === 'profil') as $i => $slot): ?>
      <div class="form-group">
        <label><?=e($slot['label'])?></label>
        <?php if (imgExists($slot['dest'])): ?><div class="home-photo-preview"><img src="<?=e(BASE_URL.'/assets/img/'.$slot['dest'])?>" alt="<?=e($slot['label'])?>"></div><?php endif; ?>
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
    <div class="form-grid">
      <div class="form-group"><label>Judul bersama (opsional)</label><input class="form-control" name="judul" placeholder="Kosongkan untuk memakai nama file"></div>
      <div class="form-group"><label>Foto *</label><input class="form-control" type="file" name="foto[]" accept=".jpg,.jpeg,.png,.webp" multiple required><small>Bisa pilih banyak foto sekaligus. Maksimal 10 MB per foto.</small></div>
    </div>
    <div class="form-actions"><button class="btn-sm btn-sm-primary">Upload ke Carousel</button></div>
  </form>
</div>
<div class="admin-table-card"><table class="admin-table"><thead><tr><th>Foto</th><th>Judul</th><th>Status</th><th></th></tr></thead><tbody>
<?php foreach ($items as $item): ?><tr><td><img src="<?=e(BASE_URL.'/uploads/galeri/'.$item['nama_file'])?>" alt="" style="width:90px;height:58px;object-fit:cover;border-radius:4px"></td><td><?=e($item['judul'] ?: 'Tanpa judul')?></td><td><?=$item['is_aktif']?'Aktif':'Nonaktif'?></td><td><form method="post" onsubmit="return confirm('Hapus foto ini?')"><input type="hidden" name="csrf_token" value="<?=generateCsrfToken()?>"><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?=$item['id']?>"><button class="btn-sm btn-sm-danger">Hapus</button></form></td></tr><?php endforeach; ?>
</tbody></table></div>
<?php require __DIR__ . '/includes/footer.php'; ?>
