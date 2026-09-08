<?php
require_once __DIR__ . '/bootstrap.php';
requireAdmin();

$pdo = getDB();
$adminTitle = 'Kelola Template Dokumen';
$adminNavActive = 'admin/kelola-template';

// ── Handle POST simpan ──────────────────────────────────────
$msg = '';
$msgType = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    validateCsrf();
    $id = sanitizeInt($_POST['id'] ?? 0);
    $judul = sanitizeString($_POST['judul'] ?? '');
    $isi = $_POST['isi_html'] ?? ''; // raw HTML, akan di-render apa adanya
    $isActive = isset($_POST['is_active']) ? 1 : 0;

    if ($id > 0) {
        $upd = $pdo->prepare(
            'UPDATE template_dokumen SET judul = ?, isi_html = ?, is_active = ? WHERE id = ?'
        );
        $upd->execute([$judul, $isi, $isActive, $id]);
        $msg = 'Template berhasil diperbarui.';
        $msgType = 'success';
    }
}

include __DIR__ . '/includes/header.php';

// ── List template ─────────────────────────────────────────
$rows = $pdo->query(
    "SELECT * FROM template_dokumen ORDER BY id"
)->fetchAll();

$editing = null;
if (isset($_GET['edit'])) {
    $eid = sanitizeInt($_GET['edit']);
    foreach ($rows as $r) {
        if ((int)$r['id'] === $eid) { $editing = $r; break; }
    }
}
?>

<?php if ($msg): ?>
<div class="flash-message flash-<?= e($msgType) ?>"><?= e($msg) ?>
    <button class="flash-close" onclick="this.parentElement.remove()">&times;</button>
</div>
<?php endif; ?>

<?php if ($editing): ?>
    <div class="admin-form-card">
        <div class="admin-form-title">Edit Template: <?= e($editing['judul']) ?></div>
        <p class="muted" style="margin-bottom:20px;">Slug: <code><?= e($editing['slug']) ?></code></p>

        <form method="post" class="admin-form">
            <input type="hidden" name="csrf_token" value="<?= generateCsrfToken() ?>">
            <input type="hidden" name="id" value="<?= (int)$editing['id'] ?>">

            <div class="form-group">
                <label>Judul</label>
                <input type="text" name="judul" class="form-control" required
                       value="<?= e($editing['judul']) ?>">
            </div>

            <div class="form-group">
                <label>Isi Template (HTML)</label>
                <textarea name="isi_html" id="templateEditor" rows="20" class="form-control" required><?= e($editing['isi_html']) ?></textarea>
                <p class="muted" style="margin-top:8px;">
                    Gunakan tag <code>&lt;div&gt;</code>, <code>&lt;table&gt;</code>, <code>&lt;h2&gt;</code> dll.
                    Variabel placeholder: <code>__NAMA__</code>, <code>__NOMOR_DAFTAR__</code> (akan diganti saat generate).
                </p>
            </div>

            <div class="form-group">
                <label>
                    <input type="checkbox" name="is_active" <?= $editing['is_active'] ? 'checked' : '' ?>>
                    Aktif
                </label>
            </div>

            <div class="form-actions">
                <a href="/admin/kelola-template" class="btn-sm btn-sm-secondary" style="text-decoration:none;">Batal</a>
                <button type="submit" class="btn-sm btn-sm-primary">Simpan Template</button>
                <a href="/download-template?slug=<?= e($editing['slug']) ?>&preview=1" target="_blank" class="btn-sm btn-sm-secondary" style="text-decoration:none;">Preview &rarr;</a>
            </div>
        </form>
    </div>

    <!-- TinyMCE via CDN -->
    <script src="https://cdn.jsdelivr.net/npm/tinymce@6.7.0/tinymce.min.js"></script>
    <script>
    tinymce.init({
      selector: '#templateEditor',
      height: 500,
      menubar: false,
      plugins: 'lists table code advlist',
      toolbar: 'undo redo | styles | bold italic | alignleft aligncenter alignright | bullist numlist | table | code',
      content_style: 'body{font-family:Times New Roman,serif;font-size:14px;line-height:1.7;}'
    });
    </script>

<?php else: ?>
    <div class="admin-table-wrap">
        <div class="table-head">
            <h2>Template Dokumen PSB</h2>
        </div>
        <p class="muted" style="padding:12px 20px 0;">Template ini digunakan untuk dokumen yang harus di-download calon peserta (kaderisasi, alumni, dhuafa).</p>

        <?php if (empty($rows)): ?>
        <div class="table-empty">Belum ada template.</div>
        <?php else: ?>
        <div style="overflow-x:auto;">
        <table class="admin-table" style="min-width:520px;">
            <thead>
                <tr>
                    <th>Slug</th>
                    <th>Judul</th>
                    <th>Status</th>
                    <th style="width:150px;">Aksi</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($rows as $r): ?>
                <tr>
                    <td><code><?= e($r['slug']) ?></code></td>
                    <td><?= e($r['judul']) ?></td>
                    <td>
                        <?php if ($r['is_active']): ?>
                            <span class="badge badge-success">Aktif</span>
                        <?php else: ?>
                            <span class="badge badge-muted">Nonaktif</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <a href="?edit=<?= (int)$r['id'] ?>" class="btn-sm btn-sm-warning" style="text-decoration:none;">Edit</a>
                        <a href="/download-template?slug=<?= e($r['slug']) ?>&preview=1" target="_blank" class="btn-sm btn-sm-secondary" style="text-decoration:none;">Preview</a>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        </div>
        <?php endif; ?>
    </div>
<?php endif; ?>

<?php include __DIR__ . '/includes/footer.php'; ?>
