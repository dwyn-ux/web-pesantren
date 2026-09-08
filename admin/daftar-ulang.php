<?php
require_once __DIR__ . '/bootstrap.php';
requireAdmin();

$pdo = getDB();
$adminTitle = 'Daftar Ulang';
$adminNavActive = 'admin/daftar-ulang';

$msg = '';
$msgType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    validateCsrf();
    $act = sanitizeString($_POST['act'] ?? '');
    $id = sanitizeInt($_POST['pendaftaran_id'] ?? 0);

    if ($act === 'daftar_ulang' && $id) {
        // Cek tagihan minimal ADM sudah ada cicilan verified
        $adm = $pdo->prepare(
            "SELECT pb.id, pb.nominal,
                    (SELECT COALESCE(SUM(nominal),0) FROM pembiayaan_cicilan
                     WHERE pembiayaan_id=pb.id AND status='verified') AS terbayar
             FROM pembiayaan pb
             WHERE pb.pendaftaran_id=? AND pb.jenis='administrasi' LIMIT 1"
        );
        $adm->execute([$id]);
        $admRow = $adm->fetch();
        if (!$admRow || (float)$admRow['terbayar'] <= 0) {
            $msg = 'Calon belum melakukan pembayaran ADM Awal. Tidak bisa daftar ulang.';
            $msgType = 'error';
        } else {
            $pdo->prepare("UPDATE pendaftaran SET status='daftar-ulang' WHERE id=?")->execute([$id]);
            $msg = 'Status diubah ke Daftar Ulang. Generate surat di menu Surat Kesanggupan.';
            $msgType = 'success';
        }
    } elseif ($act === 'batal' && $id) {
        $pdo->prepare("UPDATE pendaftaran SET status='diterima' WHERE id=?")->execute([$id]);
        $msg = 'Dibatalkan. Status kembali ke Diterima.';
        $msgType = 'success';
    }
}

$list = $pdo->query(
    "SELECT p.id, p.nomor_daftar, p.nama_lengkap, p.jenjang, p.jalur, p.status, p.updated_at,
            pg.label AS gelombang_label,
            (SELECT COALESCE(SUM(pc.nominal),0) FROM pembiayaan_cicilan pc
             JOIN pembiayaan pb ON pb.id=pc.pembiayaan_id
             WHERE pb.pendaftaran_id=p.id AND pc.status='verified' AND pb.jenis='administrasi') AS adm_terbayar
     FROM pendaftaran p
     LEFT JOIN pendaftaran_gelombang pg ON pg.id=p.gelombang_id
     WHERE p.status IN ('diterima','daftar-ulang')
     ORDER BY p.status, p.nomor_daftar
     LIMIT 500"
)->fetchAll();

include __DIR__ . '/includes/header.php';
?>

<?php if ($msg): ?>
<div class="flash-message flash-<?= e($msgType) ?>"><?= e($msg) ?>
    <button class="flash-close" onclick="this.parentElement.remove()">&times;</button>
</div>
<?php endif; ?>

<div class="admin-content">
    <div class="card">
        <h2>Daftar Ulang</h2>
        <p class="muted">Calon yang sudah Diterima dapat diaktifkan status Daftar Ulangnya setelah melakukan pembayaran ADM Awal (boleh diangsur, minimal sudah ada cicilan terverifikasi).</p>

        <table class="admin-table">
            <thead>
                <tr>
                    <th>Nomor</th>
                    <th>Nama</th>
                    <th>Jenjang</th>
                    <th>Jalur</th>
                    <th>Status</th>
                    <th>ADM Terbayar</th>
                    <th>Aksi</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($list as $r): ?>
                <tr>
                    <td><code><?= e($r['nomor_daftar']) ?></code></td>
                    <td><?= e($r['nama_lengkap']) ?></td>
                    <td><?= e($r['jenjang']) ?></td>
                    <td><?= e($r['jalur']) ?></td>
                    <td>
                        <?php if ($r['status'] === 'daftar-ulang'): ?>
                            <span class="badge badge-success">Daftar Ulang</span>
                        <?php else: ?>
                            <span class="badge badge-info">Diterima</span>
                        <?php endif; ?>
                    </td>
                    <td>Rp <?= number_format((float)$r['adm_terbayar'], 0, ',', '.') ?></td>
                    <td>
                        <?php if ($r['status'] === 'diterima'): ?>
                            <form method="post" style="display:inline" onsubmit="return confirm('Aktifkan daftar ulang?')">
                                <input type="hidden" name="csrf_token" value="<?= generateCsrfToken() ?>">
                                <input type="hidden" name="act" value="daftar_ulang">
                                <input type="hidden" name="pendaftaran_id" value="<?= (int)$r['id'] ?>">
                                <button type="submit" class="btn-link">✓ Aktifkan Daftar Ulang</button>
                            </form>
                        <?php else: ?>
                            <a href="/surat-kesanggupan?id=<?= (int)$r['id'] ?>" target="_blank" class="btn-link">📄 Cetak Surat</a>
                            <form method="post" style="display:inline" onsubmit="return confirm('Batalkan daftar ulang?')">
                                <input type="hidden" name="csrf_token" value="<?= generateCsrfToken() ?>">
                                <input type="hidden" name="act" value="batal">
                                <input type="hidden" name="pendaftaran_id" value="<?= (int)$r['id'] ?>">
                                <button type="submit" class="btn-link" style="color:#c33;">✕ Batalkan</button>
                            </form>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php if (empty($list)): ?>
                <tr><td colspan="7" style="text-align:center;padding:24px;color:#999;">Tidak ada calon yang sudah diterima.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>
