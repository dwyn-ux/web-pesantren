<?php
require_once __DIR__ . '/bootstrap.php';
requireAdmin();
$pdo = getDB();
$errors = [];

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
