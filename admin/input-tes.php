<?php
require_once __DIR__ . '/bootstrap.php';
requireAdmin();

$pdo = getDB();
$adminTitle = 'Input Nilai Tes';
$adminNavActive = 'admin/input-tes';

$msg = '';
$msgType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    validateCsrf();
    $act = sanitizeString($_POST['act'] ?? '');
    $pendaftaranId = sanitizeInt($_POST['pendaftaran_id'] ?? 0);

    if ($act === 'simpan_pemetaan' && $pendaftaranId) {
        $tgl = sanitizeString($_POST['tanggal_tes'] ?? date('Y-m-d'));
        $nilai = sanitizeFloat($_POST['nilai_total'] ?? '') ?: null;
        $status = sanitizeString($_POST['status_lulus'] ?? 'mengulang');
        $catatan = sanitizeString($_POST['catatan_panitia'] ?? '') ?: null;

        $pdo->prepare(
            "INSERT INTO tes_pemetaan (pendaftaran_id, tanggal_tes, nilai_total, status_lulus, catatan_panitia, diinput_oleh)
             VALUES (?,?,?,?,?,?)
             ON DUPLICATE KEY UPDATE
               tanggal_tes=VALUES(tanggal_tes),
               nilai_total=VALUES(nilai_total),
               status_lulus=VALUES(status_lulus),
               catatan_panitia=VALUES(catatan_panitia),
               diinput_oleh=VALUES(diinput_oleh)"
        )->execute([$pendaftaranId, $tgl, $nilai, $status, $catatan, $_SESSION['user_id']]);

        // Set status pendaftar ke tes-selesai
        $pdo->prepare("UPDATE pendaftaran SET status='tes-selesai' WHERE id=? AND status='menunggu-verifikasi'")->execute([$pendaftaranId]);
        $msg = 'Nilai tes pemetaan tersimpan.'; $msgType = 'success';
        kirimTelegram('Tes selesai (' . $status . '): ' . telegramInfoPendaftar($pdo, $pendaftaranId), $pdo);
    } elseif ($act === 'simpan_hafalan' && $pendaftaranId) {
        $tgl = sanitizeString($_POST['tanggal_tes'] ?? date('Y-m-d'));
        $juz = sanitizeInt($_POST['juz_dinilai'] ?? 0);
        $status = sanitizeString($_POST['status_lulus'] ?? 'mengulang');
        $penguji = sanitizeString($_POST['penguji'] ?? '') ?: null;
        $catatan = sanitizeString($_POST['catatan'] ?? '') ?: null;

        if ($juz < 1 || $juz > 30) {
            $msg = 'Juz harus 1-30.'; $msgType = 'error';
        } else {
            $pdo->prepare(
                "INSERT INTO tes_hafalan (pendaftaran_id, tanggal_tes, juz_dinilai, status_lulus, penguji, catatan, diinput_oleh)
                 VALUES (?,?,?,?,?,?,?)"
            )->execute([$pendaftaranId, $tgl, $juz, $status, $penguji, $catatan, $_SESSION['user_id']]);
            $msg = 'Nilai tes hafalan tersimpan.'; $msgType = 'success';
            kirimTelegram('Tes hafalan (' . $status . '): ' . telegramInfoPendaftar($pdo, $pendaftaranId), $pdo);
        }
    }
}

// List pendaftar yang perlu tes (status menunggu-verifikasi atau tes-selesai)
$list = $pdo->query(
    "SELECT p.id, p.nomor_daftar, p.nama_lengkap, p.jalur, p.jenjang,
            tp.tanggal_tes, tp.nilai_total, tp.status_lulus AS nilai_status,
            pg.label AS gelombang_label, pg.tanggal_tes AS tanggal_tes_gelombang
     FROM pendaftaran p
     LEFT JOIN tes_pemetaan tp ON tp.pendaftaran_id = p.id
     LEFT JOIN pendaftaran_gelombang pg ON pg.id = p.gelombang_id
     WHERE p.status IN ('menunggu-verifikasi','tes-selesai')
     ORDER BY p.gelombang_id, p.nomor_daftar
     LIMIT 500"
)->fetchAll();

include __DIR__ . '/includes/header.php';
?>

<?php if ($msg): ?>
<div class="flash-message flash-<?= e($msgType) ?>"><?= e($msg) ?>
    <button class="flash-close" onclick="this.parentElement.remove()">&times;</button>
</div>
<?php endif; ?>

<div class="admin-table-wrap">
    <div class="table-head">
        <h2>Input Nilai Tes</h2>
    </div>
    <p class="muted" style="padding:12px 20px 0;">Input nilai Tes Pemetaan (semua jalur) dan Tes Hafalan (khusus jalur tahfidz). Status pendaftar otomatis berubah ke <strong>tes-selesai</strong>.</p>

    <?php if (empty($list)): ?>
    <div class="table-empty">Tidak ada pendaftar yang perlu tes.</div>
    <?php else: ?>
    <div style="overflow-x:auto;">
    <table class="admin-table" style="min-width:720px;">
        <thead>
            <tr>
                <th>Nomor</th>
                <th>Nama</th>
                <th>Jalur</th>
                <th>Tes Pemetaan</th>
                <th>Tes Hafalan</th>
                <th style="width:150px;">Aksi</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($list as $r): ?>
            <tr>
                <td><code><?= e($r['nomor_daftar']) ?></code></td>
                <td><?= e($r['nama_lengkap']) ?></td>
                <td><?= e($r['jalur']) ?></td>
                <td>
                    <?php if ($r['nilai_status']): ?>
                        <?= e($r['tanggal_tes']) ?> &mdash; <strong><?= e($r['nilai_total']) ?></strong> (<?= e($r['nilai_status']) ?>)
                    <?php else: ?>
                        <span class="muted">Belum</span>
                    <?php endif; ?>
                </td>
                <td>
                    <?php if ($r['jalur'] === 'tahfidz'): ?>
                        <a href="?hafalan=<?= (int)$r['id'] ?>" class="btn-sm btn-sm-secondary" style="text-decoration:none;">Input Hafalan</a>
                    <?php else: ?>
                        <span class="muted">—</span>
                    <?php endif; ?>
                </td>
                <td>
                    <a href="?pemetaan=<?= (int)$r['id'] ?>" class="btn-sm btn-sm-warning" style="text-decoration:none;">Input Pemetaan</a>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    </div>
    <?php endif; ?>
</div>

    <?php
    if (isset($_GET['pemetaan'])) {
        $id = sanitizeInt($_GET['pemetaan']);
        $s = $pdo->prepare("SELECT p.*, tp.* FROM pendaftaran p
                            LEFT JOIN tes_pemetaan tp ON tp.pendaftaran_id=p.id
                            WHERE p.id=?");
        $s->execute([$id]);
        $r = $s->fetch();
        if ($r):
    ?>
    <div class="admin-form-card">
        <div class="admin-form-title">Input Tes Pemetaan: <?= e($r['nama_lengkap']) ?> (<?= e($r['nomor_daftar']) ?>)</div>
        <form method="post" class="admin-form">
            <input type="hidden" name="csrf_token" value="<?= generateCsrfToken() ?>">
            <input type="hidden" name="act" value="simpan_pemetaan">
            <input type="hidden" name="pendaftaran_id" value="<?= (int)$id ?>">

            <div class="form-row">
                <div class="form-group">
                    <label>Tanggal Tes</label>
                    <input type="date" name="tanggal_tes" class="form-control" required
                           value="<?= e($r['tanggal_tes'] ?? date('Y-m-d')) ?>">
                </div>
                <div class="form-group">
                    <label>Nilai (0-100)</label>
                    <input type="number" name="nilai_total" class="form-control" min="0" max="100" step="0.01"
                           value="<?= e($r['nilai_total'] ?? '') ?>">
                </div>
                <div class="form-group">
                    <label>Status</label>
                    <select name="status_lulus" class="form-control">
                        <option value="mengulang" <?= ($r['nilai_status'] ?? '') === 'mengulang' ? 'selected' : '' ?>>Mengulang</option>
                        <option value="lulus" <?= ($r['nilai_status'] ?? '') === 'lulus' ? 'selected' : '' ?>>Lulus</option>
                        <option value="tidak-lulus" <?= ($r['nilai_status'] ?? '') === 'tidak-lulus' ? 'selected' : '' ?>>Tidak Lulus</option>
                    </select>
                </div>
            </div>
            <div class="form-group">
                <label>Catatan Panitia</label>
                <textarea name="catatan_panitia" class="form-control" rows="3"><?= e($r['catatan_panitia'] ?? '') ?></textarea>
            </div>
            <div class="form-actions">
                <a href="/admin/input-tes" class="btn-sm btn-sm-secondary" style="text-decoration:none;">Batal</a>
                <button type="submit" class="btn-sm btn-sm-primary">Simpan</button>
            </div>
        </form>
    </div>
    <?php endif; } ?>

    <?php
    if (isset($_GET['hafalan'])) {
        $id = sanitizeInt($_GET['hafalan']);
        $s = $pdo->prepare("SELECT * FROM pendaftaran WHERE id=?");
        $s->execute([$id]);
        $r = $s->fetch();
        if ($r):
    ?>
    <div class="admin-form-card">
        <div class="admin-form-title">Input Tes Hafalan: <?= e($r['nama_lengkap']) ?> (<?= e($r['nomor_daftar']) ?>)</div>
        <p class="muted" style="margin-bottom:20px;">Hasil tes hafalan yang sahih adalah hasil dari tes Pondok, BUKAN sertifikat yang di-upload.</p>
        <form method="post" class="admin-form">
            <input type="hidden" name="csrf_token" value="<?= generateCsrfToken() ?>">
            <input type="hidden" name="act" value="simpan_hafalan">
            <input type="hidden" name="pendaftaran_id" value="<?= (int)$id ?>">

            <div class="form-row">
                <div class="form-group">
                    <label>Tanggal Tes</label>
                    <input type="date" name="tanggal_tes" class="form-control" required value="<?= date('Y-m-d') ?>">
                </div>
                <div class="form-group">
                    <label>Juz Dinilai (1-30)</label>
                    <input type="number" name="juz_dinilai" class="form-control" min="1" max="30" required>
                </div>
                <div class="form-group">
                    <label>Status</label>
                    <select name="status_lulus" class="form-control">
                        <option value="lulus">Lulus</option>
                        <option value="tidak-lulus">Tidak Lulus</option>
                        <option value="mengulang">Mengulang</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Penguji</label>
                    <input type="text" name="penguji" class="form-control">
                </div>
            </div>
            <div class="form-group">
                <label>Catatan</label>
                <textarea name="catatan" class="form-control" rows="3"></textarea>
            </div>
            <div class="form-actions">
                <a href="/admin/input-tes" class="btn-sm btn-sm-secondary" style="text-decoration:none;">Batal</a>
                <button type="submit" class="btn-sm btn-sm-primary">Simpan</button>
            </div>
        </form>
    </div>
    <?php endif; } ?>

<?php include __DIR__ . '/includes/footer.php'; ?>
