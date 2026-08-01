<?php
require_once __DIR__.'/bootstrap.php'; requireAdmin(); $pdo=getDB(); $errors=[];

$edit = null;
if (isset($_GET['id'])) {
    $s=$pdo->prepare('SELECT * FROM testimoni WHERE id=?'); $s->execute([sanitizeInt($_GET['id'])]); $edit=$s->fetch() ?: null;
}

if ($_SERVER['REQUEST_METHOD']==='POST') {
  validateCsrf();
  $action = sanitizeString($_POST['action'] ?? 'save');

  if ($action === 'delete') {
    $id = sanitizeInt($_POST['id'] ?? 0);
    $s  = $pdo->prepare('SELECT foto FROM testimoni WHERE id=?'); $s->execute([$id]); $t=$s->fetch();
    if ($t) {
      $pdo->prepare('DELETE FROM testimoni WHERE id=?')->execute([$id]);
      if (!empty($t['foto'])) { $p=UPLOADS_PATH.'/testimoni/'.basename($t['foto']); if (is_file($p)) unlink($p); }
    }
    setFlash('success', 'Testimoni dihapus.'); redirect('/admin/testimoni');
  }

  $id     = sanitizeInt($_POST['id'] ?? 0);
  $nama   = sanitizeString($_POST['nama'] ?? '');
  $role   = sanitizeString($_POST['role'] ?? '');
  $isi    = trim((string) ($_POST['isi'] ?? ''));
  $urutan = sanitizeInt($_POST['urutan'] ?? 0);
  $aktif  = isset($_POST['is_aktif']) ? 1 : 0;
  if ($nama === '' || $isi === '') $errors[] = 'Nama dan isi testimoni wajib diisi.';

  $foto = null;
  if (!empty($_FILES['foto']['name'])) {
    $errors = array_merge($errors, validateUpload($_FILES['foto'], ['jpg','jpeg','png','webp'], ['image/jpeg','image/png','image/webp'], 5242880));
    if (!$errors) {
      $dir = UPLOADS_PATH.'/testimoni';
      if (!is_dir($dir)) mkdir($dir, 0755, true);
      $ext     = strtolower(pathinfo($_FILES['foto']['name'], PATHINFO_EXTENSION));
      $newFile = bin2hex(random_bytes(16)).'.'.$ext;
      if (!resizeImage($_FILES['foto']['tmp_name'], $dir.'/'.$newFile, 300, 88)) {
        $errors[] = 'Gagal menyimpan foto.'; $newFile = null;
      }
      $foto = $newFile;
    }
  }

  if (!$errors) {
    if ($id) {
      if ($foto === null) {
        $s = $pdo->prepare('SELECT foto FROM testimoni WHERE id=?'); $s->execute([$id]); $foto = $s->fetchColumn();
      } else {
        $s = $pdo->prepare('SELECT foto FROM testimoni WHERE id=?'); $s->execute([$id]); $old = $s->fetchColumn();
        if (!empty($old)) { $p = UPLOADS_PATH.'/testimoni/'.basename($old); if (is_file($p)) unlink($p); }
      }
      $pdo->prepare('UPDATE testimoni SET nama=?, role=?, isi=?, foto=?, urutan=?, is_aktif=? WHERE id=?')
          ->execute([$nama, $role, $isi, $foto, $urutan, $aktif, $id]);
      setFlash('success', 'Testimoni diperbarui.');
    } else {
      $pdo->prepare('INSERT INTO testimoni (nama,role,isi,foto,urutan,is_aktif) VALUES (?,?,?,?,?,?)')
          ->execute([$nama, $role, $isi, $foto ?? '', $urutan, $aktif]);
      setFlash('success', 'Testimoni ditambahkan.');
    }
    redirect('/admin/testimoni');
  }
  $edit = ['id'=>$id, 'nama'=>$nama, 'role'=>$role, 'isi'=>$isi, 'foto'=>$foto, 'urutan'=>$urutan, 'is_aktif'=>$aktif];
}

$items = $pdo->query('SELECT * FROM testimoni ORDER BY urutan ASC, id DESC')->fetchAll();
$adminTitle = 'Testimoni'; $adminPage = 'admin/testimoni';
require __DIR__.'/includes/header.php';
?>
<div class="admin-form-card">
  <h2 class="admin-form-title"><?= $edit ? 'Edit Testimoni' : 'Tambah Testimoni' ?></h2>
  <?php if ($errors): ?><div class="flash-message flash-error"><?= e(implode(' ', $errors)) ?></div><?php endif; ?>
  <form method="post" enctype="multipart/form-data" class="admin-form">
    <input type="hidden" name="csrf_token" value="<?= generateCsrfToken() ?>">
    <input type="hidden" name="id" value="<?= (int)($edit['id'] ?? 0) ?>">
    <div class="form-grid">
      <div class="form-group"><label>Nama *</label><input class="form-control" name="nama" value="<?= e($edit['nama'] ?? '') ?>" required></div>
      <div class="form-group"><label>Peran *</label><input class="form-control" name="role" value="<?= e($edit['role'] ?? '') ?>" placeholder="Wali Santri — Angkatan 2023" required></div>
    </div>
    <div class="form-group"><label>Isi Testimoni *</label><textarea class="form-control" name="isi" rows="3" required><?= e($edit['isi'] ?? '') ?></textarea></div>
    <div class="form-grid">
      <div class="form-group">
        <label>Foto (opsional)</label>
        <input class="form-control" type="file" name="foto" accept=".jpg,.jpeg,.png,.webp">
        <small>Maksimal 5 MB, otomatis diperkecil. Kosongkan jika tidak diganti.</small>
        <?php if (!empty($edit['foto'])): ?><div class="home-photo-preview" style="margin-top:8px"><img src="<?= e(BASE_URL.'/uploads/testimoni/'.$edit['foto']) ?>" alt=""></div><?php endif; ?>
      </div>
      <div class="form-group"><label>Urutan</label><input class="form-control" type="number" name="urutan" value="<?= (int)($edit['urutan'] ?? 0) ?>"><small>Semakin kecil, semakin depan.</small></div>
    </div>
    <label style="display:flex;align-items:center;gap:8px;font-size:13px"><input type="checkbox" name="is_aktif" <?= ($edit && !$edit['is_aktif']) ? '' : 'checked' ?>> Tampilkan di halaman depan</label>
    <div class="form-actions"><button class="btn-sm btn-sm-primary"><?= $edit ? 'Simpan Perubahan' : 'Tambah Testimoni' ?></button></div>
  </form>
</div>

<div class="admin-table-card">
  <div class="table-head"><h2>Daftar Testimoni</h2></div>
  <table class="admin-table"><thead><tr><th>Nama</th><th>Peran</th><th>Isi</th><th>Status</th><th></th></tr></thead><tbody>
  <?php if (empty($items)): ?>
  <tr><td colspan="5" class="table-empty">Belum ada testimoni.</td></tr>
  <?php else: ?>
  <?php foreach ($items as $t): ?>
  <tr>
    <td><strong><?= e($t['nama']) ?></strong></td>
    <td><?= e($t['role']) ?></td>
    <td style="max-width:320px"><?= e(truncate($t['isi'], 80)) ?></td>
    <td><?= $t['is_aktif'] ? '<span class="badge badge-published">Aktif</span>' : '<span class="badge badge-draft">Nonaktif</span>' ?></td>
    <td style="white-space:nowrap">
      <a class="btn-sm btn-sm-warning" href="<?= e(BASE_URL.'/admin/testimoni?id='.$t['id']) ?>">Edit</a>
      <form method="post" onsubmit="return confirm('Hapus testimoni ini?')" style="display:inline">
        <input type="hidden" name="csrf_token" value="<?= generateCsrfToken() ?>">
        <input type="hidden" name="action" value="delete">
        <input type="hidden" name="id" value="<?= (int)$t['id'] ?>">
        <button class="btn-sm btn-sm-danger">Hapus</button>
      </form>
    </td>
  </tr>
  <?php endforeach; ?>
  <?php endif; ?>
  </tbody></table>
</div>
<?php require __DIR__.'/includes/footer.php';
