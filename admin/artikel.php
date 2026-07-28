<?php
require_once __DIR__ . '/bootstrap.php';
requireAdmin();

$pdo = getDB();

// ── Hapus artikel ─────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'hapus') {
    validateCsrf();
    $id = sanitizeInt($_POST['id'] ?? 0);
    if ($id > 0) {
        // Hapus foto jika ada
        $stmtFoto = $pdo->prepare("SELECT foto FROM artikel WHERE id = ?");
        $stmtFoto->execute([$id]);
        $fotoFile = $stmtFoto->fetchColumn();
        if ($fotoFile) {
            $fotoPath = ROOT_PATH . '/uploads/artikel/' . $fotoFile;
            if (file_exists($fotoPath)) unlink($fotoPath);
        }
        $pdo->prepare("DELETE FROM artikel WHERE id = ?")->execute([$id]);
        setFlash('success', 'Artikel berhasil dihapus.');
    }
    redirect('/admin/artikel');
}

// ── Filter & pagination ───────────────────────────────────────
$halaman    = max(1, sanitizeInt($_GET['halaman'] ?? 1));
$perHalaman = 15;
$offset     = paginationOffset($halaman, $perHalaman);
$filterKat  = sanitizeString($_GET['kategori'] ?? '');
$filterStat = sanitizeString($_GET['status'] ?? '');
$cari       = sanitizeString($_GET['cari'] ?? '');

$where  = "WHERE 1=1";
$params = [];
if ($filterKat) {
    $where .= " AND a.kategori = ?";
    $params[] = $filterKat;
}
if ($filterStat) {
    $where .= " AND a.status = ?";
    $params[] = $filterStat;
}
if ($cari) {
    $where .= " AND a.judul LIKE ?";
    $params[] = '%' . $cari . '%';
}

$stmtCount = $pdo->prepare("SELECT COUNT(*) FROM artikel a $where");
$stmtCount->execute($params);
$total = (int) $stmtCount->fetchColumn();
$totalHalaman = (int) ceil($total / $perHalaman);

$stmtData = $pdo->prepare(
    "SELECT a.id, a.slug, a.judul, a.kategori, a.status, a.views, a.published_at,
            u.name AS penulis
     FROM artikel a JOIN users u ON u.id = a.penulis_id
     $where ORDER BY a.created_at DESC
     LIMIT $perHalaman OFFSET $offset"
);
$stmtData->execute($params);
$artikelList = $stmtData->fetchAll();

$adminTitle = 'Kelola Artikel';
$adminPage  = 'admin/artikel';

$labelKategori = [
    'tahfidz'  => 'Tahfidz', 'akhlak' => 'Akhlak',
    'kajian'   => 'Kajian',  'kegiatan' => 'Kegiatan',
    'psb-info' => 'PSB',     'alumni' => 'Alumni',
];

require_once __DIR__ . '/includes/header.php';
?>

<div class="admin-table-wrap">
    <div class="table-head">
        <h2>Artikel (<?= $total ?>)</h2>
        <div style="display:flex;gap:12px;align-items:center;flex-wrap:wrap;">
            <form method="GET" action="<?= e(BASE_URL . '/admin/artikel') ?>" class="filter-toolbar">
                <input type="search" name="cari" placeholder="Cari judul..." value="<?= e($cari) ?>" maxlength="200">
                <select name="kategori" onchange="this.form.submit()">
                    <option value="">Semua Kategori</option>
                    <?php foreach ($labelKategori as $val => $lbl): ?>
                    <option value="<?= e($val) ?>" <?= $filterKat === $val ? 'selected' : '' ?>><?= e($lbl) ?></option>
                    <?php endforeach; ?>
                </select>
                <select name="status" onchange="this.form.submit()">
                    <option value="">Semua Status</option>
                    <option value="published" <?= $filterStat === 'published' ? 'selected' : '' ?>>Published</option>
                    <option value="draft"     <?= $filterStat === 'draft'     ? 'selected' : '' ?>>Draft</option>
                </select>
                <button type="submit" class="btn-sm btn-sm-primary">Cari</button>
                <?php if ($cari || $filterKat || $filterStat): ?>
                <a href="<?= e(BASE_URL . '/admin/artikel') ?>" class="btn-sm btn-sm-secondary">Reset</a>
                <?php endif; ?>
            </form>
            <a href="<?= e(BASE_URL . '/admin/artikel-tambah') ?>" class="btn-sm btn-sm-primary">+ Tulis Artikel</a>
        </div>
    </div>

    <table class="admin-table">
        <thead>
            <tr>
                <th>Judul</th>
                <th>Kategori</th>
                <th>Penulis</th>
                <th>Status</th>
                <th>Views</th>
                <th>Tanggal</th>
                <th>Aksi</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($artikelList)): ?>
            <tr><td colspan="7" class="table-empty">Belum ada artikel.</td></tr>
            <?php else: ?>
            <?php foreach ($artikelList as $art): ?>
            <tr>
                <td style="max-width:280px;">
                    <a href="<?= e(BASE_URL . '/artikel/' . $art['slug']) ?>"
                       target="_blank" rel="noopener noreferrer"
                       style="color:var(--green-deep);text-decoration:none;font-weight:500;">
                        <?= e(truncate($art['judul'], 55)) ?>
                    </a>
                </td>
                <td><?= e($labelKategori[$art['kategori']] ?? $art['kategori']) ?></td>
                <td style="font-size:12px;"><?= e($art['penulis']) ?></td>
                <td>
                    <span class="badge badge-<?= e($art['status']) ?>">
                        <?= $art['status'] === 'published' ? 'Published' : 'Draft' ?>
                    </span>
                </td>
                <td><?= number_format((int)$art['views']) ?></td>
                <td style="white-space:nowrap;font-size:12px;">
                    <?= $art['published_at'] ? e(formatTanggal($art['published_at'])) : '-' ?>
                </td>
                <td style="white-space:nowrap;">
                    <a href="<?= e(BASE_URL . '/admin/artikel-edit?id=' . $art['id']) ?>"
                       class="btn-sm btn-sm-warning">Edit</a>
                    <form method="POST" action="<?= e(BASE_URL . '/admin/artikel') ?>"
                          style="display:inline;"
                          onsubmit="return confirm('Hapus artikel ini? Tindakan tidak bisa dibatalkan.')">
                        <input type="hidden" name="csrf_token" value="<?= generateCsrfToken() ?>">
                        <input type="hidden" name="action" value="hapus">
                        <input type="hidden" name="id" value="<?= $art['id'] ?>">
                        <button type="submit" class="btn-sm btn-sm-danger" style="margin-left:4px;">Hapus</button>
                    </form>
                </td>
            </tr>
            <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>

    <!-- Pagination -->
    <?php if ($totalHalaman > 1): ?>
    <div class="admin-pagination">
        <span>Halaman <?= $halaman ?> dari <?= $totalHalaman ?></span>
        <ul>
            <?php if ($halaman > 1): ?>
            <li><a href="?halaman=<?= $halaman - 1 ?>">&laquo;</a></li>
            <?php endif; ?>
            <?php for ($p = max(1, $halaman - 2); $p <= min($totalHalaman, $halaman + 2); $p++): ?>
            <li>
                <?php if ($p === $halaman): ?>
                <span aria-current="page"><?= $p ?></span>
                <?php else: ?>
                <a href="?halaman=<?= $p ?>"><?= $p ?></a>
                <?php endif; ?>
            </li>
            <?php endfor; ?>
            <?php if ($halaman < $totalHalaman): ?>
            <li><a href="?halaman=<?= $halaman + 1 ?>">&raquo;</a></li>
            <?php endif; ?>
        </ul>
    </div>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
