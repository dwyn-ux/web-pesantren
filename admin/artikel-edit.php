<?php
require_once __DIR__ . '/bootstrap.php';
requireAdmin();

$pdo = getDB();
$id  = sanitizeInt($_GET['id'] ?? 0);

if ($id <= 0) {
    setFlash('error', 'Artikel tidak ditemukan.');
    redirect('/admin/artikel');
}

$stmtArtikel = $pdo->prepare("SELECT * FROM artikel WHERE id = ?");
$stmtArtikel->execute([$id]);
$artikel = $stmtArtikel->fetch();

if (!$artikel) {
    setFlash('error', 'Artikel tidak ditemukan.');
    redirect('/admin/artikel');
}

$errors = [];
$data   = $artikel; // Isi default dari DB

// ── Proses POST ───────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    validateCsrf();

    $data = [
        'judul'     => sanitizeString($_POST['judul']    ?? ''),
        'ringkasan' => sanitizeString($_POST['ringkasan'] ?? ''),
        'isi'       => $_POST['isi'] ?? '',
        'kategori'  => sanitizeString($_POST['kategori']  ?? ''),
        'status'    => sanitizeString($_POST['status']    ?? 'draft'),
    ];

    $allowedTags = '<p><br><strong><em><u><h2><h3><h4><ul><ol><li><blockquote><a><img>';
    $data['isi'] = strip_tags($data['isi'], $allowedTags);

    $validKategori = ['tahfidz','akhlak','kajian','kegiatan','psb-info','alumni'];
    $validStatus   = ['draft','published'];

    if (empty($data['judul'])) $errors['judul'] = 'Judul wajib diisi.';
    if (empty($data['isi']))   $errors['isi']   = 'Isi artikel wajib diisi.';
    if (!in_array($data['kategori'], $validKategori, true)) $errors['kategori'] = 'Pilih kategori valid.';
    if (!in_array($data['status'],   $validStatus,   true)) $data['status'] = 'draft';

    // Foto baru (opsional)
    $fotoFile = $artikel['foto']; // default: foto lama
    if (!empty($_FILES['foto']['name'])) {
        $uploadErrors = validateUpload(
            $_FILES['foto'],
            ['jpg','jpeg','png','webp'],
            ['image/jpeg','image/png','image/webp'],
            2097152
        );
        if (!empty($uploadErrors)) {
            $errors['foto'] = implode(' ', $uploadErrors);
        } else {
            $destDir   = ROOT_PATH . '/uploads/artikel/';
            $newFile   = saveUpload($_FILES['foto'], $destDir);
            if ($newFile) {
                resizeImage($destDir . $newFile, $destDir . $newFile, 1200);
                // Hapus foto lama
                if ($artikel['foto'] && file_exists($destDir . $artikel['foto'])) {
                    unlink($destDir . $artikel['foto']);
                }
                $fotoFile = $newFile;
            } else {
                $errors['foto'] = 'Gagal menyimpan foto.';
            }
        }
    }

    // Hapus foto (jika dicentang)
    if (!empty($_POST['hapus_foto']) && $artikel['foto']) {
        $fotoPath = ROOT_PATH . '/uploads/artikel/' . $artikel['foto'];
        if (file_exists($fotoPath)) unlink($fotoPath);
        $fotoFile = null;
    }

    if (empty($errors)) {
        // Tentukan published_at
        $publishedAt = $artikel['published_at'];
        if ($data['status'] === 'published' && empty($publishedAt)) {
            $publishedAt = date('Y-m-d H:i:s');
        }

        $pdo->prepare(
            "UPDATE artikel SET judul=?, ringkasan=?, isi=?, foto=?, kategori=?, status=?, published_at=?
             WHERE id=?"
        )->execute([
            $data['judul'],
            $data['ringkasan'] ?: null,
            $data['isi'],
            $fotoFile,
            $data['kategori'],
            $data['status'],
            $publishedAt,
            $id,
        ]);

        setFlash('success', 'Artikel berhasil diperbarui.');
        redirect('/admin/artikel');
    }
}

$adminTitle = 'Edit Artikel';
$adminPage  = 'admin/artikel';

$labelKategori = [
    'tahfidz'  => 'Tahfidz',       'akhlak'   => 'Akhlak & Adab',
    'kajian'   => 'Kajian Islam',  'kegiatan' => 'Kegiatan Pesantren',
    'psb-info' => 'PSB & Info',    'alumni'   => 'Profil Alumni',
];

require_once __DIR__ . '/includes/header.php';
?>

<?php if (!empty($errors)): ?>
<div class="flash-message flash-error" role="alert" style="margin-bottom:16px;">
    Terdapat <?= count($errors) ?> kesalahan. Periksa kembali formulir.
</div>
<?php endif; ?>

<form method="POST" action="<?= e(BASE_URL . '/admin/artikel-edit?id=' . $id) ?>"
      enctype="multipart/form-data" class="admin-form">
    <input type="hidden" name="csrf_token" value="<?= generateCsrfToken() ?>">

    <div style="display:grid;grid-template-columns:1fr 300px;gap:24px;align-items:start;">

        <!-- Konten utama -->
        <div>
            <div class="admin-form-card">
                <h2 class="admin-form-title">Edit Konten Artikel</h2>

                <div class="form-group">
                    <label for="judul">Judul <span class="req">*</span></label>
                    <input type="text" id="judul" name="judul"
                           class="form-control<?= isset($errors['judul']) ? ' is-error' : '' ?>"
                           value="<?= e($data['judul'] ?? '') ?>" maxlength="300" required>
                    <?php if (isset($errors['judul'])): ?>
                    <p class="field-error"><?= e($errors['judul']) ?></p>
                    <?php endif; ?>
                    <p style="font-size:11px;color:var(--text-light);margin-top:4px;">
                        Slug: <code><?= e($artikel['slug']) ?></code>
                        <a href="<?= e(BASE_URL . '/artikel/' . $artikel['slug']) ?>"
                           target="_blank" rel="noopener noreferrer"
                           style="color:var(--green-mid);margin-left:8px;">↗ Lihat</a>
                    </p>
                </div>

                <div class="form-group">
                    <label for="ringkasan">Ringkasan / Excerpt</label>
                    <textarea id="ringkasan" name="ringkasan" rows="3"
                              class="form-control"
                              maxlength="500"><?= e($data['ringkasan'] ?? '') ?></textarea>
                </div>

                <div class="form-group">
                    <label for="isi">Isi Artikel <span class="req">*</span></label>
                    <?php include __DIR__ . '/includes/article-toolbar.php'; ?>
                    <textarea id="isi" name="isi" rows="20"
                              class="form-control article-source<?= isset($errors['isi']) ? ' is-error' : '' ?>"><?= e($data['isi'] ?? '') ?></textarea>
                    <?php if (isset($errors['isi'])): ?>
                    <p class="field-error"><?= e($errors['isi']) ?></p>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Sidebar kanan -->
        <div>
            <div class="admin-form-card">
                <h2 class="admin-form-title">Publikasi</h2>

                <div class="form-group">
                    <label for="status">Status</label>
                    <select id="status" name="status" class="form-control">
                        <option value="draft"     <?= ($data['status'] ?? '') === 'draft'     ? 'selected' : '' ?>>Draft</option>
                        <option value="published" <?= ($data['status'] ?? '') === 'published' ? 'selected' : '' ?>>Published</option>
                    </select>
                </div>

                <div class="form-group">
                    <label for="kategori">Kategori <span class="req">*</span></label>
                    <select id="kategori" name="kategori"
                            class="form-control<?= isset($errors['kategori']) ? ' is-error' : '' ?>" required>
                        <option value="">-- Pilih --</option>
                        <?php foreach ($labelKategori as $val => $lbl): ?>
                        <option value="<?= e($val) ?>"
                            <?= ($data['kategori'] ?? '') === $val ? 'selected' : '' ?>>
                            <?= e($lbl) ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                    <?php if (isset($errors['kategori'])): ?>
                    <p class="field-error"><?= e($errors['kategori']) ?></p>
                    <?php endif; ?>
                </div>

                <div class="form-group">
                    <label for="foto">Foto Sampul</label>
                    <?php if ($artikel['foto']): ?>
                    <div style="margin-bottom:10px;">
                        <img src="<?= e(BASE_URL . '/uploads/artikel/' . basename($artikel['foto'])) ?>"
                             alt="Foto saat ini" style="width:100%;height:120px;object-fit:cover;border-radius:4px;" onerror="this.hidden=true">
                        <label style="display:flex;align-items:center;gap:8px;font-size:12px;margin-top:6px;font-weight:400;cursor:pointer;">
                            <input type="checkbox" name="hapus_foto" value="1">
                            Hapus foto saat ini
                        </label>
                    </div>
                    <?php endif; ?>
                    <input type="file" id="foto" name="foto" accept="image/jpeg,image/png,image/webp"
                           class="form-control<?= isset($errors['foto']) ? ' is-error' : '' ?>">
                    <p style="font-size:11px;color:var(--text-light);margin-top:4px;">Upload foto baru untuk mengganti.</p>
                    <?php if (isset($errors['foto'])): ?>
                    <p class="field-error"><?= e($errors['foto']) ?></p>
                    <?php endif; ?>
                </div>

                <div class="form-actions" style="border-top:none;padding-top:0;flex-direction:column;">
                    <button type="submit" class="btn-sm btn-sm-primary"
                            style="padding:12px;font-size:14px;width:100%;">
                        Simpan Perubahan
                    </button>
                    <a href="<?= e(BASE_URL . '/admin/artikel') ?>" class="btn-sm btn-sm-secondary"
                       style="padding:12px;font-size:14px;width:100%;margin-top:8px;text-align:center;">
                        Batal
                    </a>
                </div>
            </div>
        </div>
    </div>
</form>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
