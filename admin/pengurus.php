<?php
require_once __DIR__.'/bootstrap.php'; requireAdmin(); $pdo=getDB(); $errors=[];

$levels = ['mudir' => 'Mudir', 'wakil' => 'Wakil Mudir', 'sekretariat' => 'Sekretariat', 'unit' => 'Unit / Bidang'];
$jabatanBaku = ['Mudir / Pimpinan Pesantren', 'Wakil Mudir', 'Sekretaris', 'Bendahara', 'Kesantrian', 'Sarpras', 'Kepala SMP', 'Kepala SMA', 'Humas'];

$edit = null;
if (isset($_GET['id'])) {
    $s=$pdo->prepare('SELECT * FROM pengurus WHERE id=?'); $s->execute([sanitizeInt($_GET['id'])]); $edit=$s->fetch() ?: null;
}

if ($_SERVER['REQUEST_METHOD']==='POST') {
  validateCsrf();
  $action = sanitizeString($_POST['action'] ?? 'save');

  if ($action === 'delete') {
    $id = sanitizeInt($_POST['id'] ?? 0);
    $s  = $pdo->prepare('SELECT foto FROM pengurus WHERE id=?'); $s->execute([$id]); $t=$s->fetch();
    if ($t) {
      $pdo->prepare('DELETE FROM pengurus WHERE id=?')->execute([$id]);
      if (!empty($t['foto'])) { $p=UPLOADS_PATH.'/pengurus/'.basename($t['foto']); if (is_file($p)) unlink($p); }
    }
    setFlash('success', 'Pengurus dihapus.'); redirect('/admin/pengurus');
  }

  $id      = sanitizeInt($_POST['id'] ?? 0);
  $nama    = sanitizeString($_POST['nama'] ?? '');
  $jabatan = sanitizeString($_POST['jabatan'] ?? '');
  $level   = sanitizeString($_POST['level'] ?? 'unit');
  $urutan  = sanitizeInt($_POST['urutan'] ?? 0);
  $aktif   = isset($_POST['is_aktif']) ? 1 : 0;
  if (!isset($levels[$level])) $level = 'unit';
  if ($nama === '' || $jabatan === '') $errors[] = 'Nama dan jabatan wajib diisi.';

  $foto = null;
  if (!empty($_FILES['foto']['name'])) {
    $errors = array_merge($errors, validateUpload($_FILES['foto'], ['jpg','jpeg','png','webp'], ['image/jpeg','image/png','image/webp'], 5242880));
    if (!$errors) {
      $dir = UPLOADS_PATH.'/pengurus';
      if (!is_dir($dir)) mkdir($dir, 0755, true);
      $ext     = strtolower(pathinfo($_FILES['foto']['name'], PATHINFO_EXTENSION));
      $newFile = bin2hex(random_bytes(16)).'.'.$ext;
      if (!resizeImage($_FILES['foto']['tmp_name'], $dir.'/'.$newFile, 400, 88)) {
        $errors[] = 'Gagal menyimpan foto.'; $newFile = null;
      }
      $foto = $newFile;
    }
  }

  if (!$errors) {
    if ($id) {
      if ($foto === null) {
        $s = $pdo->prepare('SELECT foto FROM pengurus WHERE id=?'); $s->execute([$id]); $foto = $s->fetchColumn();
      } else {
        $s = $pdo->prepare('SELECT foto FROM pengurus WHERE id=?'); $s->execute([$id]); $old = $s->fetchColumn();
        if (!empty($old)) { $p = UPLOADS_PATH.'/pengurus/'.basename($old); if (is_file($p)) unlink($p); }
      }
      $pdo->prepare('UPDATE pengurus SET nama=?, jabatan=?, level=?, foto=?, urutan=?, is_aktif=? WHERE id=?')
          ->execute([$nama, $jabatan, $level, $foto, $urutan, $aktif, $id]);
      setFlash('success', 'Pengurus diperbarui.');
    } else {
      $pdo->prepare('INSERT INTO pengurus (nama,jabatan,level,foto,urutan,is_aktif) VALUES (?,?,?,?,?,?)')
          ->execute([$nama, $jabatan, $level, $foto ?? '', $urutan, $aktif]);
      setFlash('success', 'Pengurus ditambahkan.');
    }
    redirect('/admin/pengurus');
  }
  $edit = ['id'=>$id, 'nama'=>$nama, 'jabatan'=>$jabatan, 'level'=>$level, 'foto'=>$foto, 'urutan'=>$urutan, 'is_aktif'=>$aktif];
}

$items = $pdo->query('SELECT * FROM pengurus ORDER BY urutan ASC, id ASC')->fetchAll();
$adminTitle = 'Struktur Pengurus'; $adminPage = 'admin/pengurus';
require __DIR__.'/includes/header.php';
?>
<div class="admin-form-card">
  <div class="admin-form-title"><?= $edit ? 'Edit Pengurus' : 'Tambah Pengurus' ?></div>
  <?php if ($errors): ?><div class="flash-message flash-error"><?= e(implode(' ', $errors)) ?></div><?php endif; ?>
  <form method="post" enctype="multipart/form-data" class="admin-form">
    <input type="hidden" name="csrf_token" value="<?= generateCsrfToken() ?>">
    <input type="hidden" name="id" value="<?= (int)($edit['id'] ?? 0) ?>">
    <div class="form-row">
      <div class="form-group"><label>Nama *</label><input class="form-control" name="nama" value="<?= e($edit['nama'] ?? '') ?>" placeholder="cth: Ahmad Nurdin Kholilis, S.Th.I., M.Pd." required></div>
      <div class="form-group"><label>Jabatan *</label><input class="form-control" name="jabatan" list="jabatanBaku" value="<?= e($edit['jabatan'] ?? '') ?>" placeholder="cth: Kepala SMA" required>
        <datalist id="jabatanBaku"><?php foreach ($jabatanBaku as $j): ?><option value="<?= e($j) ?>"><?php endforeach; ?></datalist>
      </div>
    </div>
    <div class="form-row">
      <div class="form-group"><label>Tingkat *</label>
        <select class="form-control" name="level">
          <?php foreach ($levels as $k => $label): ?>
          <option value="<?= e($k) ?>"<?= (($edit['level'] ?? 'unit') === $k) ? ' selected' : '' ?>><?= e($label) ?></option>
          <?php endforeach; ?>
        </select>
        <span class="muted">Mudir = puncak · Wakil = lapis 2 · Sekretariat = lapis 3 · Unit = lapis 4.</span>
      </div>
      <div class="form-group"><label>Urutan</label><input class="form-control" type="number" name="urutan" value="<?= (int)($edit['urutan'] ?? 0) ?>"><span class="muted">Semakin kecil, semakin depan.</span></div>
    </div>
    <div class="form-group">
      <label>Foto (opsional)</label>
      <input class="form-control" type="file" name="foto" accept=".jpg,.jpeg,.png,.webp">
      <span class="muted">Maksimal 5 MB, otomatis diperkecil. Kosongkan jika tidak diganti.</span>
      <?php if (!empty($edit['foto'])): ?><div class="home-photo-preview"><img src="<?= e(BASE_URL.'/uploads/pengurus/'.$edit['foto']) ?>" alt=""></div><?php endif; ?>
    </div>
    <div class="form-group"><label style="display:flex;align-items:center;gap:8px;"><input type="checkbox" name="is_aktif" <?= ($edit && !$edit['is_aktif']) ? '' : 'checked' ?>> Tampilkan di halaman profil</label></div>
    <div class="form-actions"><button class="btn-sm btn-sm-primary"><?= $edit ? 'Simpan Perubahan' : 'Tambah Pengurus' ?></button></div>
  </form>
</div>

<div class="admin-table-wrap">
  <div class="table-head"><h2>Daftar Pengurus</h2></div>
  <table class="admin-table"><thead><tr><th>Nama</th><th>Jabatan</th><th>Tingkat</th><th>Status</th><th></th></tr></thead><tbody>
  <?php if (empty($items)): ?>
  <tr><td colspan="5" class="table-empty">Belum ada pengurus.</td></tr>
  <?php else: ?>
  <?php foreach ($items as $t): ?>
  <tr>
    <td><strong><?= e($t['nama']) ?></strong></td>
    <td><?= e($t['jabatan']) ?></td>
    <td><?= e($levels[$t['level']] ?? $t['level']) ?></td>
    <td><?= $t['is_aktif'] ? '<span class="badge badge-published">Aktif</span>' : '<span class="badge badge-draft">Nonaktif</span>' ?></td>
    <td>
      <a class="btn-sm btn-sm-warning" style="text-decoration:none;" href="<?= e(BASE_URL.'/admin/pengurus?id='.$t['id']) ?>">Edit</a>
      <form method="post" onsubmit="return confirm('Hapus pengurus ini?')">
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

<?php include __DIR__.'/includes/footer.php'; ?>
