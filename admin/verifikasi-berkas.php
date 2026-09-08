<?php
require_once __DIR__ . '/bootstrap.php';
requireAdmin();

$pdo = getDB();
$adminTitle = 'Verifikasi Berkas PSB';
$adminNavActive = 'admin/verifikasi-berkas';

$msg = '';
$msgType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    validateCsrf();
    $act = sanitizeString($_POST['act'] ?? '');
    $pendaftaranId = sanitizeInt($_POST['pendaftaran_id'] ?? 0);

    if ($act === 'setujui' && $pendaftaranId) {
        $potongan = sanitizeFloat($_POST['jalur_potongan'] ?? 0) ?: null;
        $pdo->prepare(
            "UPDATE pendaftaran SET jalur_status='disetujui', jalur_potongan=? WHERE id=?"
        )->execute([$potongan, $pendaftaranId]);
        $msg = 'Jalur disetujui.'; $msgType = 'success';
    } elseif ($act === 'tolak' && $pendaftaranId) {
        $pdo->prepare(
            "UPDATE pendaftaran SET jalur_status='ditolak' WHERE id=?"
        )->execute([$pendaftaranId]);
        $msg = 'Jalur ditolak.'; $msgType = 'success';
    } elseif ($act === 'snapshot' && $pendaftaranId) {
        // Trigger snapshot final
        $ok = applyTarifToSnapshot($pdo, $pendaftaranId);
        if ($ok) {
            $pdo->prepare("UPDATE pendaftaran SET status='diterima' WHERE id=?")->execute([$pendaftaranId]);
            $msg = 'Tagihan final disnapshot. Status: DITERIMA.'; $msgType = 'success';
        } else {
            $msg = 'Gagal snapshot. Pastikan jalur sudah disetujui (jika alumni/dhuafa).';
            $msgType = 'error';
        }
    } elseif ($act === 'tolak_pendaftaran' && $pendaftaranId) {
        $pdo->prepare("UPDATE pendaftaran SET status='ditolak' WHERE id=?")->execute([$pendaftaranId]);
        $msg = 'Pendaftaran ditolak.'; $msgType = 'success';
    }
}

// Filter
$filterStatus = sanitizeString($_GET['status'] ?? 'menunggu-verifikasi');
$filterJalur  = sanitizeString($_GET['jalur'] ?? '');

$where = ['1=1'];
$params = [];
if ($filterStatus) { $where[] = 'p.status = ?'; $params[] = $filterStatus; }
if ($filterJalur)  { $where[] = 'p.jalur = ?';   $params[] = $filterJalur; }

$sql = "SELECT p.*, pg.label AS gelombang_label, pg.tanggal_tes
        FROM pendaftaran p
        LEFT JOIN pendaftaran_gelombang pg ON pg.id = p.gelombang_id
        WHERE " . implode(' AND ', $where) . "
        ORDER BY p.created_at DESC LIMIT 200";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$list = $stmt->fetchAll();

include __DIR__ . '/includes/header.php';

$labelJalur = jalurPendaftaran();
$labelStatus = [
    'pending'             => 'Baru Daftar',
    'menunggu-verifikasi' => 'Menunggu Verifikasi',
    'tes-selesai'         => 'Tes Selesai',
    'diterima'            => 'Diterima',
    'ditolak'             => 'Ditolak',
    'daftar-ulang'        => 'Daftar Ulang',
];
?>

<?php if ($msg): ?>
<div class="flash-message flash-<?= e($msgType) ?>"><?= e($msg) ?>
    <button class="flash-close" onclick="this.parentElement.remove()">&times;</button>
</div>
<?php endif; ?>

<div class="admin-table-wrap">
    <div class="table-head">
        <h2>Verifikasi Pendaftaran</h2>
    </div>

    <form method="get" class="filter-toolbar" style="padding:12px 20px 0;">
        <select name="status" aria-label="Filter status">
            <option value="">— Semua Status —</option>
            <?php foreach ($labelStatus as $k => $v): ?>
                <option value="<?= $k ?>" <?= $filterStatus === $k ? 'selected' : '' ?>><?= e($v) ?></option>
            <?php endforeach; ?>
        </select>
        <select name="jalur" aria-label="Filter jalur">
            <option value="">— Semua Jalur —</option>
            <?php foreach ($labelJalur as $k => $v): ?>
                <option value="<?= $k ?>" <?= $filterJalur === $k ? 'selected' : '' ?>><?= e($v) ?></option>
            <?php endforeach; ?>
        </select>
        <button type="submit" class="btn-sm btn-sm-primary">Filter</button>
    </form>

    <?php if (empty($list)): ?>
    <div class="table-empty">Tidak ada data.</div>
    <?php else: ?>
    <div style="overflow-x:auto;">
    <table class="admin-table" style="min-width:780px;">
        <thead>
            <tr>
                <th>Nomor</th>
                <th>Nama</th>
                <th>Jenjang</th>
                <th>Jalur</th>
                <th>Status</th>
                <th>Gelombang</th>
                <th style="width:90px;">Aksi</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($list as $r): ?>
            <tr>
                <td><code><?= e($r['nomor_daftar']) ?></code></td>
                <td><?= e($r['nama_lengkap']) ?><br>
                    <small class="muted"><?= e($r['email'] ?? '-') ?></small>
                </td>
                <td><?= e($r['jenjang']) ?></td>
                <td>
                    <?= e($labelJalur[$r['jalur']] ?? $r['jalur']) ?>
                    <?php if ($r['jalur_detail']): ?>
                        <br><small class="muted"><?= e($r['jalur_detail']) ?></small>
                    <?php endif; ?>
                    <?php if (in_array($r['jalur'], ['alumni-sdmua','dhuafa'], true)): ?>
                        <br><small style="color:#c33;">Status jalur: <?= e($r['jalur_status']) ?></small>
                    <?php endif; ?>
                </td>
                <td><span class="badge badge-draft"><?= e($labelStatus[$r['status']] ?? $r['status']) ?></span></td>
                <td><?= e($r['gelombang_label'] ?? '-') ?><br>
                    <small class="muted">Tes: <?= $r['tanggal_tes'] ? date('d M Y', strtotime($r['tanggal_tes'])) : '-' ?></small>
                </td>
                <td>
                    <a href="?detail=<?= (int)$r['id'] ?>" class="btn-sm btn-sm-secondary" style="text-decoration:none;">Detail</a>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    </div>
    <?php endif; ?>
</div>

    <?php
    if (isset($_GET['detail'])) {
        $did = sanitizeInt($_GET['detail']);
        $dstmt = $pdo->prepare("SELECT p.*, pg.label AS gelombang_label FROM pendaftaran p
                                LEFT JOIN pendaftaran_gelombang pg ON pg.id=p.gelombang_id
                                WHERE p.id=?");
        $dstmt->execute([$did]);
        $d = $dstmt->fetch();
        if ($d):
            // Ambil berkas
            $bs = $pdo->prepare("SELECT * FROM berkas_santri WHERE pendaftaran_id=?");
            $bs->execute([$did]);
            $berkasList = $bs->fetchAll();
            $byJenis = [];
            foreach ($berkasList as $b) $byJenis[$b['jenis']] = $b;
            $wajibList = ['kartu-keluarga','akta-lahir','foto','ktp-ortu'];
            $jalurBerkas = jalurBerkasUntuk($d['jalur']);
    ?>
    <div class="admin-form-card">
        <div class="admin-form-title">Detail: <?= e($d['nama_lengkap']) ?> (<?= e($d['nomor_daftar']) ?>)</div>

        <div class="detail-grid">
            <div>
                <h4>Identitas</h4>
                <p>TTL: <?= e($d['tempat_lahir']) ?>, <?= e($d['tanggal_lahir']) ?><br>
                   JK: <?= e($d['jenis_kelamin']) ?><br>
                   Jenjang: <?= e($d['jenjang']) ?><br>
                   WhatsApp: <?= e($d['whatsapp']) ?></p>

                <h4>Orang Tua</h4>
                <p>Ayah: <?= e($d['nama_ayah']) ?><br>
                   Ibu: <?= e($d['nama_ibu']) ?><br>
                   HP: <?= e($d['hp_ortu']) ?><br>
                   Alamat: <?= e($d['alamat']) ?></p>
            </div>
            <div>
                <h4>Jalur</h4>
                <p><?= e($labelJalur[$d['jalur']] ?? $d['jalur']) ?>
                   <?= $d['jalur_detail'] ? '(' . e($d['jalur_detail']) . ')' : '' ?><br>
                   Status jalur: <strong><?= e($d['jalur_status']) ?></strong>
                   <?= $d['jalur_potongan'] !== null ? '<br>Potongan: ' . (float)$d['jalur_potongan'] . '%' : '' ?>
                </p>

                <h4>Gelombang</h4>
                <p><?= e($d['gelombang_label']) ?><br>
                   Status: <strong><?= e($labelStatus[$d['status']] ?? $d['status']) ?></strong></p>
            </div>
        </div>

        <h4>Berkas Wajib</h4>
        <table class="admin-table">
            <thead><tr><th>Jenis</th><th>Status Upload</th><th>Aksi</th></tr></thead>
            <tbody>
                <?php foreach ($wajibList as $j): ?>
                <tr>
                    <td><?= e($j) ?></td>
                    <td>
                        <?php if (isset($byJenis[$j])): ?>
                            <span class="badge badge-success">✓ Ada</span>
                        <?php else: ?>
                            <span class="badge badge-muted">Belum</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php if (isset($byJenis[$j])): ?>
                            <a href="/admin/berkas-lihat?jenis=<?= e($j) ?>&pendaftaran_id=<?= (int)$did ?>" target="_blank" class="btn-sm btn-sm-secondary" style="text-decoration:none;">Lihat</a>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <?php if (!empty($jalurBerkas)): ?>
        <h4>Berkas Jalur</h4>
        <table class="admin-table">
            <thead><tr><th>Jenis</th><th>Status Upload</th><th>Aksi</th></tr></thead>
            <tbody>
                <?php foreach ($jalurBerkas as $j): ?>
                <tr>
                    <td><?= e($j) ?></td>
                    <td>
                        <?php if (isset($byJenis[$j])): ?>
                            <span class="badge badge-success">✓ Ada</span>
                        <?php else: ?>
                            <span class="badge badge-muted">Belum</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php if (isset($byJenis[$j])): ?>
                            <a href="/admin/berkas-lihat?jenis=<?= e($j) ?>&pendaftaran_id=<?= (int)$did ?>" target="_blank" class="btn-sm btn-sm-secondary" style="text-decoration:none;">Lihat</a>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php endif; ?>

        <h4>Aksi Verifikasi</h4>
        <div class="form-actions">
            <?php if (in_array($d['jalur'], ['alumni-sdmua','dhuafa'], true) && $d['jalur_status'] === 'pending'): ?>
                <form method="post" style="display:flex;gap:8px;align-items:center;flex-wrap:wrap;">
                    <input type="hidden" name="csrf_token" value="<?= generateCsrfToken() ?>">
                    <input type="hidden" name="act" value="setujui">
                    <input type="hidden" name="pendaftaran_id" value="<?= (int)$did ?>">
                    <input type="number" name="jalur_potongan" placeholder="Potongan %" min="0" max="100" step="0.01" class="form-control" style="width:120px;">
                    <button type="submit" class="btn-sm btn-sm-primary">Setujui + Potongan</button>
                </form>
                <form method="post">
                    <input type="hidden" name="csrf_token" value="<?= generateCsrfToken() ?>">
                    <input type="hidden" name="act" value="tolak">
                    <input type="hidden" name="pendaftaran_id" value="<?= (int)$did ?>">
                    <button type="submit" class="btn-sm btn-sm-secondary">Tolak Jalur</button>
                </form>
            <?php endif; ?>

            <?php if (in_array($d['status'], ['menunggu-verifikasi','tes-selesai'], true)): ?>
                <form method="post" onsubmit="return confirm('Snapshot tagihan final & set DITERIMA?')">
                    <input type="hidden" name="csrf_token" value="<?= generateCsrfToken() ?>">
                    <input type="hidden" name="act" value="snapshot">
                    <input type="hidden" name="pendaftaran_id" value="<?= (int)$did ?>">
                    <button type="submit" class="btn-sm btn-sm-primary">Snapshot Tagihan &amp; Terima</button>
                </form>
            <?php endif; ?>

            <?php if (!in_array($d['status'], ['diterima','daftar-ulang','ditolak'], true)): ?>
                <form method="post" onsubmit="return confirm('Tolak pendaftaran ini?')">
                    <input type="hidden" name="csrf_token" value="<?= generateCsrfToken() ?>">
                    <input type="hidden" name="act" value="tolak_pendaftaran">
                    <input type="hidden" name="pendaftaran_id" value="<?= (int)$did ?>">
                    <button type="submit" class="btn-sm btn-sm-danger">Tolak Pendaftaran</button>
                </form>
            <?php endif; ?>

            <?php if (isSnapshotFinal((int)$did, $pdo)): ?>
                <span class="badge badge-success">✓ Snapshot Final Ada</span>
            <?php endif; ?>
        </div>
    </div>
    <?php endif; } ?>

<?php include __DIR__ . '/includes/footer.php'; ?>
