<?php
require_once __DIR__ . '/bootstrap.php';
requireAdmin();

$pdo = getDB();
$adminTitle = 'Kelola Gelombang PSB';
$adminNavActive = 'admin/kelola-gelombang';

$msg = '';
$msgType = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    validateCsrf();
    $act = sanitizeString($_POST['act'] ?? '');

    if ($act === 'simpan') {
        $id = sanitizeInt($_POST['id'] ?? 0);
        $slug = sanitizeString($_POST['slug'] ?? '');
        $label = sanitizeString($_POST['label'] ?? '');
        $tanggalBuka = sanitizeString($_POST['tanggal_buka'] ?? '');
        $tanggalTutup = sanitizeString($_POST['tanggal_tutup'] ?? '');
        $tanggalTes = sanitizeString($_POST['tanggal_tes'] ?? '') ?: null;
        $kuota = sanitizeInt($_POST['target_kuota'] ?? 0);
        $isActive = isset($_POST['is_active']) ? 1 : 0;
        $urutan = sanitizeInt($_POST['urutan'] ?? 0);

        if (!$slug || !$label || !$tanggalBuka || !$tanggalTutup) {
            $msg = 'Field wajib harus diisi.'; $msgType = 'error';
        } else {
            if ($id > 0) {
                $pdo->prepare(
                    "UPDATE pendaftaran_gelombang
                     SET slug=?, label=?, tanggal_buka=?, tanggal_tutup=?, tanggal_tes=?,
                         target_kuota=?, is_active=?, urutan=?
                     WHERE id=?"
                )->execute([$slug, $label, $tanggalBuka, $tanggalTutup, $tanggalTes, $kuota, $isActive, $urutan, $id]);
                $msg = 'Gelombang diperbarui.'; $msgType = 'success';
            } else {
                $pdo->prepare(
                    "INSERT INTO pendaftaran_gelombang
                     (slug,label,tanggal_buka,tanggal_tutup,tanggal_tes,target_kuota,is_active,urutan)
                     VALUES (?,?,?,?,?,?,?,?)"
                )->execute([$slug, $label, $tanggalBuka, $tanggalTutup, $tanggalTes, $kuota, $isActive, $urutan]);
                $msg = 'Gelombang ditambahkan.'; $msgType = 'success';
            }
        }
    } elseif ($act === 'hapus') {
        $id = sanitizeInt($_POST['id'] ?? 0);
        $pdo->prepare("DELETE FROM pendaftaran_gelombang WHERE id=?")->execute([$id]);
        $msg = 'Gelombang dihapus.'; $msgType = 'success';
    }
}

$rows = $pdo->query("SELECT * FROM pendaftaran_gelombang ORDER BY urutan")->fetchAll();

// Counter pendaftar per gelombang
$counter = [];
foreach ($rows as $r) {
    $c = $pdo->prepare("SELECT COUNT(*) FROM pendaftaran WHERE gelombang_id=?");
    $c->execute([$r['id']]);
    $counter[$r['id']] = (int) $c->fetchColumn();
}

include __DIR__ . '/includes/header.php';
?>

<?php if ($msg): ?>
<div class="flash-message flash-<?= e($msgType) ?>"><?= e($msg) ?>
    <button class="flash-close" onclick="this.parentElement.remove()">&times;</button>
</div>
<?php endif; ?>

<?php
$editing = null;
if (isset($_GET['edit'])) {
    foreach ($rows as $r) if ((int)$r['id'] === (int)$_GET['edit']) $editing = $r;
}
?>

<div class="admin-table-wrap">
    <div class="table-head">
        <h2>Gelombang Pendaftaran PSB</h2>
    </div>
    <p class="muted" style="padding:12px 20px 0;">Sesuaikan jadwal, kuota, dan urutan gelombang. Data dipakai untuk auto-detect saat calon daftar dan untuk laporan admin.</p>

    <?php if (empty($rows)): ?>
    <div class="table-empty">Belum ada gelombang. Tambah lewat form di bawah.</div>
    <?php else: ?>
    <div style="overflow-x:auto;">
    <table class="admin-table" style="min-width:760px;">
        <thead>
            <tr>
                <th>Urutan</th>
                <th>Slug</th>
                <th>Label</th>
                <th>Tanggal Buka</th>
                <th>Tanggal Tutup</th>
                <th>Tes</th>
                <th>Kuota</th>
                <th>Pendaftar</th>
                <th>Aktif</th>
                <th style="width:130px;">Aksi</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($rows as $r): ?>
            <tr>
                <td><?= (int)$r['urutan'] ?></td>
                <td><code><?= e($r['slug']) ?></code></td>
                <td><?= e($r['label']) ?></td>
                <td style="font-size:12px;"><?= e($r['tanggal_buka']) ?></td>
                <td style="font-size:12px;"><?= e($r['tanggal_tutup']) ?></td>
                <td style="font-size:12px;"><?= e($r['tanggal_tes'] ?? '-') ?></td>
                <td><?= (int)$r['target_kuota'] ?></td>
                <td><strong><?= $counter[$r['id']] ?? 0 ?></strong></td>
                <td>
                    <?php if ($r['is_active']): ?>
                        <span class="badge badge-success">Ya</span>
                    <?php else: ?>
                        <span class="badge badge-muted">Tidak</span>
                    <?php endif; ?>
                </td>
                <td>
                    <a href="?edit=<?= (int)$r['id'] ?>" class="btn-sm btn-sm-warning" style="text-decoration:none;">Edit</a>
                    <form method="post" style="display:inline;" onsubmit="return confirm('Hapus gelombang ini?')">
                        <input type="hidden" name="csrf_token" value="<?= generateCsrfToken() ?>">
                        <input type="hidden" name="act" value="hapus">
                        <input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
                        <button type="submit" class="btn-sm btn-sm-danger">Hapus</button>
                    </form>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    </div>
    <?php endif; ?>
</div>

<div class="admin-form-card">
    <div class="admin-form-title"><?= $editing ? 'Edit Gelombang' : 'Tambah Gelombang' ?></div>
    <form method="post" class="admin-form">
        <input type="hidden" name="csrf_token" value="<?= generateCsrfToken() ?>">
        <input type="hidden" name="act" value="simpan">
        <input type="hidden" name="id" value="<?= (int)($editing['id'] ?? 0) ?>">

        <div class="form-row">
            <div class="form-group">
                <label>Slug *</label>
                <input type="text" name="slug" class="form-control" required
                       placeholder="indent|gelombang-1|gelombang-2|gelombang-3"
                       value="<?= e($editing['slug'] ?? '') ?>">
            </div>
            <div class="form-group">
                <label>Label *</label>
                <input type="text" name="label" class="form-control" required
                       value="<?= e($editing['label'] ?? '') ?>">
            </div>
        </div>

        <div class="form-row">
            <div class="form-group">
                <label>Tanggal Buka *</label>
                <input type="date" name="tanggal_buka" class="form-control" required
                       value="<?= e($editing['tanggal_buka'] ?? '') ?>">
            </div>
            <div class="form-group">
                <label>Tanggal Tutup *</label>
                <input type="date" name="tanggal_tutup" class="form-control" required
                       value="<?= e($editing['tanggal_tutup'] ?? '') ?>">
            </div>
            <div class="form-group">
                <label>Tanggal Tes</label>
                <input type="date" name="tanggal_tes" class="form-control"
                       value="<?= e($editing['tanggal_tes'] ?? '') ?>">
            </div>
        </div>

        <div class="form-row">
            <div class="form-group">
                <label>Target Kuota (admin-only, tidak tampil publik)</label>
                <input type="number" name="target_kuota" class="form-control" min="0"
                       value="<?= (int)($editing['target_kuota'] ?? 0) ?>">
            </div>
            <div class="form-group">
                <label>Urutan</label>
                <input type="number" name="urutan" class="form-control" min="0"
                       value="<?= (int)($editing['urutan'] ?? 0) ?>">
            </div>
            <div class="form-group">
                <label>
                    <input type="checkbox" name="is_active" <?= !$editing || $editing['is_active'] ? 'checked' : '' ?>>
                    Aktif
                </label>
            </div>
        </div>

        <div class="form-actions">
            <?php if ($editing): ?>
                <a href="/admin/kelola-gelombang" class="btn-sm btn-sm-secondary" style="text-decoration:none;">Batal</a>
            <?php endif; ?>
            <button type="submit" class="btn-sm btn-sm-primary"><?= $editing ? 'Simpan' : 'Tambah' ?></button>
        </div>
    </form>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>
