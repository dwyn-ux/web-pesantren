<?php
require_once __DIR__ . '/bootstrap.php';
requireAdmin();

$pdo = getDB();
$id = sanitizeInt($_GET['id'] ?? 0);
$stmt = $pdo->prepare('SELECT * FROM alumni WHERE id = ?');
$stmt->execute([$id]);
$alumni = $stmt->fetch();
if (!$alumni) {
    setFlash('error', 'Data tidak ditemukan.');
    redirect('/admin/alumni');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    validateCsrf();

    $fields = [];
    foreach (['nama', 'alamat', 'aktivitas', 'tempat_kuliah', 'jurusan', 'tempat_bekerja', 'jabatan', 'pesan_kesan', 'saran'] as $field) {
        $fields[$field] = sanitizeString(is_string($_POST[$field] ?? null) ? $_POST[$field] : '');
    }
    $year = filter_var($_POST['tahun_kelulusan'] ?? null, FILTER_VALIDATE_INT);
    $errors = [];

    if ($fields['nama'] === '') $errors[] = 'Nama wajib diisi.';
    if ($fields['alamat'] === '') $errors[] = 'Alamat wajib diisi.';
    if ($year === false || $year < 1900 || $year > (int) date('Y')) $errors[] = 'Tahun lulus tidak valid.';
    if (!in_array($fields['aktivitas'], ['kuliah', 'bekerja', 'kuliah_dan_bekerja'], true)) {
        $errors[] = 'Kesibukan tidak valid.';
    } else {
        if (in_array($fields['aktivitas'], ['kuliah', 'kuliah_dan_bekerja'], true)) {
            if ($fields['tempat_kuliah'] === '') $errors[] = 'Nama kampus wajib diisi.';
            if ($fields['jurusan'] === '') $errors[] = 'Program studi wajib diisi.';
        }
        if (in_array($fields['aktivitas'], ['bekerja', 'kuliah_dan_bekerja'], true)) {
            if ($fields['tempat_bekerja'] === '') $errors[] = 'Tempat bekerja wajib diisi.';
            if ($fields['jabatan'] === '') $errors[] = 'Pekerjaan atau jabatan wajib diisi.';
        }
    }

    if ($errors) {
        setFlash('error', implode(' ', $errors));
    } else {
        $isStudying = in_array($fields['aktivitas'], ['kuliah', 'kuliah_dan_bekerja'], true);
        $isWorking = in_array($fields['aktivitas'], ['bekerja', 'kuliah_dan_bekerja'], true);
        $stmt = $pdo->prepare(
            'UPDATE alumni SET nama=?, tahun_kelulusan=?, alamat=?, aktivitas=?, tempat_kuliah=?, jurusan=?, '
            . 'tempat_bekerja=?, jabatan=?, pesan_kesan=?, saran=? WHERE id=?'
        );
        $stmt->execute([
            $fields['nama'],
            $year,
            $fields['alamat'],
            $fields['aktivitas'],
            $isStudying ? ($fields['tempat_kuliah'] ?: null) : null,
            $isStudying ? ($fields['jurusan'] ?: null) : null,
            $isWorking ? ($fields['tempat_bekerja'] ?: null) : null,
            $isWorking ? ($fields['jabatan'] ?: null) : null,
            $fields['pesan_kesan'],
            $fields['saran'] ?: null,
            $id,
        ]);
        setFlash('success', 'Data alumni diperbarui.');
        redirect('/admin/alumni');
    }
}

$adminTitle = 'Edit Alumni';
$adminPage = 'admin/alumni';
require __DIR__ . '/includes/header.php';
?>
<form method="post" class="admin-form admin-form-card">
  <input type="hidden" name="csrf_token" value="<?= generateCsrfToken() ?>">
  <div class="form-row">
    <div class="form-group">
      <label>Nama</label>
      <input class="form-control" name="nama" required value="<?= e($alumni['nama']) ?>">
    </div>
    <div class="form-group">
      <label>Tahun kelulusan</label>
      <input type="number" class="form-control" name="tahun_kelulusan" min="1900" max="<?= e(date('Y')) ?>" required value="<?= e((string) $alumni['tahun_kelulusan']) ?>">
    </div>
  </div>
  <div class="form-group">
    <label>Alamat</label>
    <textarea class="form-control" name="alamat" required><?= e($alumni['alamat']) ?></textarea>
  </div>
  <div class="form-group">
    <label>Kesibukan</label>
    <select class="form-control" name="aktivitas" required>
      <option value="kuliah" <?= $alumni['aktivitas'] === 'kuliah' ? 'selected' : '' ?>>Kuliah</option>
      <option value="bekerja" <?= $alumni['aktivitas'] === 'bekerja' ? 'selected' : '' ?>>Bekerja</option>
      <option value="kuliah_dan_bekerja" <?= $alumni['aktivitas'] === 'kuliah_dan_bekerja' ? 'selected' : '' ?>>Kuliah dan bekerja</option>
    </select>
  </div>
  <div class="form-row">
    <div class="form-group">
      <label>Tempat kuliah</label>
      <input class="form-control" name="tempat_kuliah" maxlength="180" value="<?= e($alumni['tempat_kuliah'] ?? '') ?>">
    </div>
    <div class="form-group">
      <label>Program studi</label>
      <input class="form-control" name="jurusan" maxlength="180" value="<?= e($alumni['jurusan'] ?? '') ?>">
    </div>
    <div class="form-group">
      <label>Tempat bekerja</label>
      <input class="form-control" name="tempat_bekerja" maxlength="180" value="<?= e($alumni['tempat_bekerja'] ?? '') ?>">
    </div>
    <div class="form-group">
      <label>Pekerjaan atau jabatan</label>
      <input class="form-control" name="jabatan" maxlength="180" value="<?= e($alumni['jabatan'] ?? '') ?>">
    </div>
  </div>
  <div class="form-group">
    <label>Pesan dan kesan</label>
    <textarea class="form-control" name="pesan_kesan"><?= e($alumni['pesan_kesan'] ?? '') ?></textarea>
  </div>
  <div class="form-group">
    <label>Saran</label>
    <textarea class="form-control" name="saran"><?= e($alumni['saran'] ?? '') ?></textarea>
  </div>
  <div class="form-actions">
    <button class="btn-sm btn-sm-primary">Simpan Perubahan</button>
    <a class="btn-sm btn-sm-secondary" href="<?= BASE_URL ?>/admin/alumni">Batal</a>
  </div>
</form>
<?php require __DIR__ . '/includes/footer.php'; ?>
