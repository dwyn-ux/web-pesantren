<?php
require_once __DIR__ . '/bootstrap.php';
requireAdmin();

$pdo = getDB();
$adminTitle = 'Verifikasi Cicilan';
$adminNavActive = 'admin/cicilan-verifikasi';

$msg = '';
$msgType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    validateCsrf();
    $act = sanitizeString($_POST['act'] ?? '');
    $id = sanitizeInt($_POST['id'] ?? 0);

    if ($act === 'verifikasi' && $id) {
        $pdo->prepare(
            "UPDATE pembiayaan_cicilan
             SET status='verified', verified_by=?, verified_at=NOW()
             WHERE id=?"
        )->execute([$_SESSION['user_id'], $id]);
        $msg = 'Cicilan diverifikasi.'; $msgType = 'success';
    } elseif ($act === 'tolak' && $id) {
        $catatan = sanitizeString($_POST['catatan'] ?? '');
        $pdo->prepare(
            "UPDATE pembiayaan_cicilan
             SET status='rejected', verified_by=?, verified_at=NOW(), catatan=?
             WHERE id=?"
        )->execute([$_SESSION['user_id'], $catatan, $id]);
        $msg = 'Cicilan ditolak.'; $msgType = 'success';
    }
}

$filter = sanitizeString($_GET['status'] ?? 'pending');
$where = '1=1';
$params = [];
if ($filter === 'pending' || $filter === 'verified' || $filter === 'rejected') {
    $where = 'c.status = ?';
    $params[] = $filter;
}

$sql = "SELECT c.*, p.nama_lengkap, p.nomor_daftar, p.jenis_kelamin,
               pb.nama AS item_nama, pb.nominal AS item_nominal
        FROM pembiayaan_cicilan c
        JOIN pembiayaan pb ON pb.id = c.pembiayaan_id
        JOIN pendaftaran p ON p.id = pb.pendaftaran_id
        WHERE $where
        ORDER BY c.created_at DESC LIMIT 500";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$list = $stmt->fetchAll();

include __DIR__ . '/includes/header.php';
?>

<?php if ($msg): ?>
<div class="flash-message flash-<?= e($msgType) ?>"><?= e($msg) ?>
    <button class="flash-close" onclick="this.parentElement.remove()">&times;</button>
</div>
<?php endif; ?>

<div class="admin-content">
    <div class="card">
        <h2>Verifikasi Cicilan / Angsuran</h2>

        <div class="filter-tabs">
            <a href="?status=pending" class="filter-tab <?= $filter === 'pending' ? 'active' : '' ?>">Menunggu</a>
            <a href="?status=verified" class="filter-tab <?= $filter === 'verified' ? 'active' : '' ?>">Diverifikasi</a>
            <a href="?status=rejected" class="filter-tab <?= $filter === 'rejected' ? 'active' : '' ?>">Ditolak</a>
            <a href="?status=" class="filter-tab <?= $filter === '' ? 'active' : '' ?>">Semua</a>
        </div>

        <table class="admin-table">
            <thead>
                <tr>
                    <th>Pendaftar</th>
                    <th>Item</th>
                    <th>Nominal Cicilan</th>
                    <th>Metode</th>
                    <th>Tanggal</th>
                    <th>Status</th>
                    <th>Aksi</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($list as $r): ?>
                <tr>
                    <td>
                        <strong><?= e($r['nama_lengkap']) ?></strong><br>
                        <code><?= e($r['nomor_daftar']) ?></code>
                    </td>
                    <td>
                        <?= e($r['item_nama']) ?>
                        <br><small style="color:#888;">Tagihan: Rp <?= number_format((float)$r['item_nominal'], 0, ',', '.') ?></small>
                    </td>
                    <td><strong>Rp <?= number_format((float)$r['nominal'], 0, ',', '.') ?></strong></td>
                    <td><?= e($r['metode']) ?></td>
                    <td><?= e($r['tanggal_bayar']) ?></td>
                    <td>
                        <?php if ($r['status'] === 'verified'): ?>
                            <span class="badge badge-success">Diverifikasi</span>
                        <?php elseif ($r['status'] === 'rejected'): ?>
                            <span class="badge badge-error">Ditolak</span>
                        <?php else: ?>
                            <span class="badge badge-warning">Menunggu</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php if ($r['status'] === 'pending'): ?>
                            <form method="post" style="display:inline">
                                <input type="hidden" name="csrf_token" value="<?= generateCsrfToken() ?>">
                                <input type="hidden" name="act" value="verifikasi">
                                <input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
                                <button type="submit" class="btn-link" style="color:#0a0;">✓ Verifikasi</button>
                            </form>
                            <form method="post" style="display:inline" onsubmit="return confirm('Tolak cicilan ini?')">
                                <input type="hidden" name="csrf_token" value="<?= generateCsrfToken() ?>">
                                <input type="hidden" name="act" value="tolak">
                                <input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
                                <input type="hidden" name="catatan" value="Bukti tidak valid">
                                <button type="submit" class="btn-link" style="color:#c33;">✕ Tolak</button>
                            </form>
                        <?php else: ?>
                            <small style="color:#999;">Selesai</small>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php if (empty($list)): ?>
                <tr><td colspan="7" style="text-align:center;padding:24px;color:#999;">Tidak ada data.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>
