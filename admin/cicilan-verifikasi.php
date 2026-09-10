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
        $q = $pdo->prepare(
            "SELECT pb.pendaftaran_id, c.nominal FROM pembiayaan_cicilan c
             JOIN pembiayaan pb ON pb.id = c.pembiayaan_id WHERE c.id = ?"
        );
        $q->execute([$id]);
        if ($r = $q->fetch()) {
            kirimTelegram(
                'Cicilan TERVERIFIKASI Rp ' . number_format((float) $r['nominal'], 0, ',', '.')
                . ': ' . telegramInfoPendaftar($pdo, (int) $r['pendaftaran_id']),
                $pdo
            );
        }
    } elseif ($act === 'verifikasi_batch' && $id) {
        // Verifikasi sekaligus semua baris satu batch (id = batch_id string aman via whitelist query)
        $batch = preg_replace('/[^0-9a-f]/', '', (string) ($_POST['batch'] ?? ''));
        if ($batch !== '') {
            $pdo->beginTransaction();
            try {
                $pdo->prepare(
                    "UPDATE pembiayaan_cicilan
                     SET status='verified', verified_by=?, verified_at=NOW()
                     WHERE batch_id=? AND status='pending'"
                )->execute([$_SESSION['user_id'], $batch]);
                $pdo->commit();
                $msg = 'Batch gabungan diverifikasi sekaligus.'; $msgType = 'success';
                $q = $pdo->prepare(
                    "SELECT pb.pendaftaran_id, SUM(c.nominal) AS total FROM pembiayaan_cicilan c
                     JOIN pembiayaan pb ON pb.id = c.pembiayaan_id WHERE c.batch_id = ? GROUP BY pb.pendaftaran_id LIMIT 1"
                );
                $q->execute([$batch]);
                if ($r = $q->fetch()) {
                    kirimTelegram(
                        'Batch TERVERIFIKASI Rp ' . number_format((float) $r['total'], 0, ',', '.')
                        . ': ' . telegramInfoPendaftar($pdo, (int) $r['pendaftaran_id']),
                        $pdo
                    );
                }
            } catch (Throwable $e) {
                if ($pdo->inTransaction()) $pdo->rollBack();
                error_log('Verifikasi batch gagal: ' . $e->getMessage());
                $msg = 'Gagal verifikasi batch.'; $msgType = 'error';
            }
        }
    } elseif ($act === 'tolak_batch' && $id) {
        $catatan = sanitizeString($_POST['catatan'] ?? '');
        $pdo->prepare(
            "UPDATE pembiayaan_cicilan
             SET status='rejected', verified_by=?, verified_at=NOW(), catatan=?
             WHERE id=?"
        )->execute([$_SESSION['user_id'], $catatan, $id]);
        $msg = 'Cicilan ditolak.'; $msgType = 'success';
        $q = $pdo->prepare(
            "SELECT pb.pendaftaran_id FROM pembiayaan_cicilan c
             JOIN pembiayaan pb ON pb.id = c.pembiayaan_id WHERE c.id = ?"
        );
        $q->execute([$id]);
        if ($r = $q->fetch()) {
            kirimTelegram('Cicilan DITOLAK: ' . telegramInfoPendaftar($pdo, (int) $r['pendaftaran_id']), $pdo);
        }
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

<div class="admin-table-wrap">
    <div class="table-head">
        <h2>Verifikasi Cicilan / Angsuran</h2>
    </div>

    <div class="filter-tabs" style="padding:12px 20px 0;">
        <a href="?status=pending" class="filter-tab <?= $filter === 'pending' ? 'active' : '' ?>">Menunggu</a>
        <a href="?status=verified" class="filter-tab <?= $filter === 'verified' ? 'active' : '' ?>">Diverifikasi</a>
        <a href="?status=rejected" class="filter-tab <?= $filter === 'rejected' ? 'active' : '' ?>">Ditolak</a>
        <a href="?status=" class="filter-tab <?= $filter === '' ? 'active' : '' ?>">Semua</a>
    </div>

    <?php if (empty($list)): ?>
    <div class="table-empty">Tidak ada data.</div>
    <?php else: ?>
    <div style="overflow-x:auto;">
    <table class="admin-table" style="min-width:760px;">
        <thead>
            <tr>
                <th>Pendaftar</th>
                <th>Item</th>
                <th>Nominal Cicilan</th>
                <th>Bukti</th>
                <th>Metode</th>
                <th>Tanggal</th>
                <th>Status</th>
                <th style="width:170px;">Aksi</th>
            </tr>
        </thead>
        <tbody>
            <?php
            // Kelompokkan baris se-batch agar verifikasi sekaligus
            $grup = [];
            foreach ($list as $r) {
                $k = !empty($r['batch_id']) ? 'batch:' . $r['batch_id'] : 'row:' . $r['id'];
                $grup[$k][] = $r;
            }
            foreach ($grup as $rows):
                $isBatch = count($rows) > 1 || !empty($rows[0]['batch_id']);
                $pertama = $rows[0];
                $totalGrup = array_sum(array_map(static fn($x) => (float) $x['nominal'], $rows));
                $semuaPending = array_reduce($rows, static fn($c, $x) => $c && $x['status'] === 'pending', true);
            ?>
            <tr>
                <td>
                    <strong><?= e($pertama['nama_lengkap']) ?></strong><br>
                    <code><?= e($pertama['nomor_daftar']) ?></code>
                </td>
                <td>
                    <?php if ($isBatch): ?>
                        <span class="badge badge-diterima">Gabungan (<?= count($rows) ?> item)</span><br>
                        <?php foreach ($rows as $gr): ?>
                            <?= e($gr['item_nama']) ?>: <strong>Rp <?= number_format((float)$gr['nominal'], 0, ',', '.') ?></strong><br>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <?= e($pertama['item_nama']) ?>
                        <br><small class="muted">Tagihan: Rp <?= number_format((float)$pertama['item_nominal'], 0, ',', '.') ?></small>
                    <?php endif; ?>
                </td>
                <td><strong>Rp <?= number_format($totalGrup, 0, ',', '.') ?></strong></td>
                <td>
                    <?php if (!empty($pertama['bukti_file'])): ?>
                        <a href="<?= BASE_URL ?>/admin/bukti-lihat?id=<?= (int)$pertama['id'] ?>" target="_blank" rel="noopener" class="btn-sm btn-sm-secondary" style="text-decoration:none;">🧾 Lihat</a>
                    <?php else: ?>
                        <small class="muted">—</small>
                    <?php endif; ?>
                </td>
                <td><?= e($pertama['metode']) ?></td>
                <td style="font-size:12px;"><?= e($pertama['tanggal_bayar']) ?></td>
                <td>
                    <?php if ($semuaPending): ?>
                        <span class="badge badge-pending">Menunggu</span>
                    <?php elseif (array_reduce($rows, static fn($c, $x) => $c && $x['status'] === 'verified', true)): ?>
                        <span class="badge badge-diterima">Diverifikasi</span>
                    <?php elseif (array_reduce($rows, static fn($c, $x) => $c && $x['status'] === 'rejected', true)): ?>
                        <span class="badge badge-ditolak">Ditolak</span>
                    <?php else: ?>
                        <span class="badge badge-pending">Sebagian</span>
                    <?php endif; ?>
                </td>
                <td>
                    <?php if ($semuaPending): ?>
                        <?php if ($isBatch): ?>
                            <form method="post" style="display:inline;">
                                <input type="hidden" name="csrf_token" value="<?= generateCsrfToken() ?>">
                                <input type="hidden" name="act" value="verifikasi_batch">
                                <input type="hidden" name="id" value="<?= (int)$pertama['id'] ?>">
                                <input type="hidden" name="batch" value="<?= e($pertama['batch_id']) ?>">
                                <button type="submit" class="btn-sm btn-sm-primary">✓ Verifikasi Semua</button>
                            </form>
                            <form method="post" style="display:inline;" onsubmit="return confirm('Tolak seluruh batch ini?')">
                                <input type="hidden" name="csrf_token" value="<?= generateCsrfToken() ?>">
                                <input type="hidden" name="act" value="tolak_batch">
                                <input type="hidden" name="id" value="<?= (int)$pertama['id'] ?>">
                                <input type="hidden" name="batch" value="<?= e($pertama['batch_id']) ?>">
                                <input type="hidden" name="catatan" value="Bukti tidak valid">
                                <button type="submit" class="btn-sm btn-sm-danger">✕ Tolak Semua</button>
                            </form>
                        <?php else: ?>
                        <form method="post" style="display:inline;">
                            <input type="hidden" name="csrf_token" value="<?= generateCsrfToken() ?>">
                            <input type="hidden" name="act" value="verifikasi">
                            <input type="hidden" name="id" value="<?= (int)$pertama['id'] ?>">
                            <button type="submit" class="btn-sm btn-sm-primary">✓ Verifikasi</button>
                        </form>
                        <form method="post" style="display:inline;" onsubmit="return confirm('Tolak cicilan ini?')">
                            <input type="hidden" name="csrf_token" value="<?= generateCsrfToken() ?>">
                            <input type="hidden" name="act" value="tolak">
                            <input type="hidden" name="id" value="<?= (int)$pertama['id'] ?>">
                            <input type="hidden" name="catatan" value="Bukti tidak valid">
                            <button type="submit" class="btn-sm btn-sm-danger">✕ Tolak</button>
                        </form>
                        <?php endif; ?>
                    <?php else: ?>
                        <small class="muted">Selesai</small>
                    <?php endif; ?>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    </div>
    <?php endif; ?>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>
