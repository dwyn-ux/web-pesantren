<?php
require_once __DIR__ . '/bootstrap.php';
requireAdmin();

$pdo = getDB();
$adminTitle = 'Voucher Akashi (Prestasi Internal)';
$adminPage  = 'admin/voucher-akashi';

$msg = '';
$msgType = '';
$baruDibuat = [];

// ── Ekspor CSV ────────────────────────────────────────────────
if (isset($_GET['export']) && $_GET['export'] === '1') {
    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="voucher-akashi.csv"');
    $out = fopen('php://output', 'w');
    fwrite($out, "\xEF\xBB\xBF"); // BOM supaya terbaca Excel
    fputcsv($out, ['Kode', 'Juara', 'Nominal (Rp)', 'Nama Pemenang', 'Masa Berlaku', 'Status', 'Dipakai Oleh']);
    $rows = $pdo->query(
        "SELECT v.*, p.nomor_daftar FROM voucher_akashi v
         LEFT JOIN pendaftaran p ON p.id = v.pendaftaran_id
         ORDER BY v.juara, v.id"
    )->fetchAll();
    $labelJuara = ['juara-1' => 'Juara 1', 'juara-2' => 'Juara 2', 'juara-3' => 'Juara 3'];
    foreach ($rows as $r) {
        fputcsv($out, [
            $r['kode'],
            $labelJuara[$r['juara']] ?? $r['juara'],
            $r['nominal_potongan'],
            $r['nama_pemenang'] ?? '',
            $r['expire_at'] ?? '',
            $r['pendaftaran_id'] ? 'Terpakai' : 'Belum',
            $r['nomor_daftar'] ?? '',
        ]);
    }
    fclose($out);
    exit;
}

// Nominal default per juara (Rp) — sinkron dengan getPotonganAkashi()
$akashiDefault = ['juara-1' => 2000000, 'juara-2' => 1500000, 'juara-3' => 1000000];
$labelJuara = ['juara-1' => 'Juara 1', 'juara-2' => 'Juara 2', 'juara-3' => 'Juara 3'];

// ── Proses POST ───────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    validateCsrf();
    $act = sanitizeString($_POST['act'] ?? '');

    if ($act === 'generate') {
        $juara = sanitizeString($_POST['juara'] ?? 'juara-3');
        if (!isset($akashiDefault[$juara])) $juara = 'juara-3';
        $jumlah = min(100, max(1, sanitizeInt($_POST['jumlah'] ?? 1)));
        $namaBatch = sanitizeString($_POST['nama_batch'] ?? '');
        $expire = sanitizeString($_POST['expire_at'] ?? '') ?: date('Y-m-d', strtotime('+3 years'));
        $dibuat = 0;
        for ($i = 0; $i < $jumlah; $i++) {
            do {
                $kode = generateAkashiKode();
                $cekK = $pdo->prepare('SELECT id FROM voucher_akashi WHERE kode = ? LIMIT 1');
                $cekK->execute([$kode]);
            } while ($cekK->fetch());
            $nominal = $akashiDefault[$juara];
            $pdo->prepare(
                'INSERT INTO voucher_akashi (kode, juara, nominal_potongan, nama_pemenang, jalur, jalur_detail, expire_at, dibuat_oleh)
                 VALUES (?,?,?,?,?,?,?,?)'
            )->execute([$kode, $juara, $nominal, $namaBatch ?: null, 'prestasi', 'internal', $expire, (int) ($_SESSION['user_id'] ?? 0)]);
            $baruDibuat[] = $kode;
            $dibuat++;
        }
        $msg = "$dibuat kode {$labelJuara[$juara]} dibuat (potongan Rp " . number_format($akashiDefault[$juara], 0, ',', '.') . ', berlaku s/d ' . $expire . ').';
        $msgType = 'success';
    } elseif ($act === 'edit') {
        $id = sanitizeInt($_POST['id'] ?? 0);
        $nama = sanitizeString($_POST['nama_pemenang'] ?? '') ?: null;
        $nominal = sanitizeFloat($_POST['nominal'] ?? 0);
        $expire = sanitizeString($_POST['expire_at'] ?? '') ?: null;
        if ($id && $nominal > 0) {
            $pdo->prepare(
                'UPDATE voucher_akashi SET nama_pemenang = ?, nominal_potongan = ?, expire_at = ? WHERE id = ?'
            )->execute([$nama, $nominal, $expire, $id]);
            $msg = 'Voucher diperbarui.';
            $msgType = 'success';
        } else {
            $msg = 'Data tidak valid (nominal harus > 0).';
            $msgType = 'error';
        }
    } elseif ($act === 'hapus') {
        $id = sanitizeInt($_POST['id'] ?? 0);
        $cek = $pdo->prepare('SELECT pendaftaran_id FROM voucher_akashi WHERE id = ?');
        $cek->execute([$id]);
        $row = $cek->fetch();
        if ($row && $row['pendaftaran_id'] !== null) {
            $msg = 'Kode yang sudah terpakai tidak bisa dihapus.';
            $msgType = 'error';
        } else {
            $pdo->prepare('DELETE FROM voucher_akashi WHERE id = ?')->execute([$id]);
            $msg = 'Voucher dihapus.';
            $msgType = 'success';
        }
    }
}

$rows = $pdo->query(
    "SELECT v.*, p.nomor_daftar, p.nama_lengkap AS pendaftar
     FROM voucher_akashi v
     LEFT JOIN pendaftaran p ON p.id = v.pendaftaran_id
     ORDER BY v.juara, v.id DESC"
)->fetchAll();

$groups = [];
foreach ($rows as $r) {
    $groups[$r['juara']][] = $r;
}
$totalKode = count($rows);
$totalPakai = 0;
foreach ($rows as $r) { if ($r['pendaftaran_id']) $totalPakai++; }

include __DIR__ . '/includes/header.php';
?>

<?php if ($msg): ?>
<div class="flash-message flash-<?= e($msgType) ?>"><?= e($msg) ?>
    <button class="flash-close" onclick="this.parentElement.remove()">&times;</button>
</div>
<?php endif; ?>

<?php if (!empty($baruDibuat)): ?>
<div class="admin-form-card">
    <div class="admin-form-title">Kode yang baru dibuat — cetak &amp; tempel di voucher hadiah</div>
    <p class="muted">Satu baris = satu kode unik. Tempel di voucher fisik pemenang.</p>
    <textarea readonly rows="<?= min(10, max(3, count($baruDibuat))) ?>" class="akashi-code-box" onclick="this.select()"><?= e(implode("\n", $baruDibuat)) ?></textarea>
</div>
<?php endif; ?>

<div class="admin-form-card">
    <div class="admin-form-title">Generate Voucher Akashi</div>
    <p class="muted">
        Tiap kode unik untuk 1 pemenang (klaim kode saja, tanpa NISN).
        Potongan mengurangi <strong>ADM awal</strong>. Masa berlaku default 3 tahun.
    </p>
    <form method="post" class="admin-form">
        <input type="hidden" name="csrf_token" value="<?= generateCsrfToken() ?>">
        <input type="hidden" name="act" value="generate">
        <div class="form-row">
            <div class="form-group">
                <label>Juara</label>
                <select name="juara" class="form-control">
                    <option value="juara-1">Juara 1 — Rp 2.000.000</option>
                    <option value="juara-2">Juara 2 — Rp 1.500.000</option>
                    <option value="juara-3" selected>Juara 3 — Rp 1.000.000</option>
                </select>
            </div>
            <div class="form-group">
                <label>Jumlah kode</label>
                <input type="number" name="jumlah" class="form-control" min="1" max="100" value="1">
            </div>
            <div class="form-group">
                <label>Masa berlaku</label>
                <input type="date" name="expire_at" class="form-control" value="<?= date('Y-m-d', strtotime('+3 years')) ?>">
            </div>
        </div>
        <div class="form-group">
            <label>Catatan batch (opsional)</label>
            <input type="text" name="nama_batch" class="form-control" placeholder="cth: Akashi 2026 SD ...">
        </div>
        <div class="form-actions">
            <button type="submit" class="btn-sm btn-sm-primary">Generate</button>
        </div>
    </form>
</div>

<div class="admin-table-wrap">
    <div class="table-head">
        <h2>Daftar Voucher
            <span class="badge badge-muted"><?= $totalKode ?> kode</span>
            <span class="badge badge-success"><?= $totalPakai ?> terpakai</span>
        </h2>
        <button type="button" class="btn-sm btn-sm-secondary" onclick="window.location='?export=1'">Ekspor CSV</button>
    </div>

    <?php if (empty($rows)): ?>
    <div class="table-empty">Belum ada voucher. Generate lewat form di atas.</div>
    <?php else: ?>
    <?php foreach (['juara-1', 'juara-2', 'juara-3'] as $jk): ?>
    <?php if (empty($groups[$jk])) continue; ?>
    <div class="akashi-group-header">
        <strong><?= e($labelJuara[$jk]) ?></strong>
        <span class="badge badge-muted"><?= count($groups[$jk]) ?> kode</span>
        <span class="muted">Potongan Rp <?= number_format($akashiDefault[$jk], 0, ',', '.') ?></span>
    </div>
    <table class="admin-table">
        <thead>
            <tr>
                <th>Kode</th>
                <th>Nama Pemenang</th>
                <th>Nominal</th>
                <th>Berlaku s/d</th>
                <th>Status</th>
                <th class="col-aksi">Aksi</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($groups[$jk] as $v): ?>
            <tr>
                <td><code><?= e($v['kode']) ?></code></td>
                <td><?= e($v['nama_pemenang'] ?: '—') ?></td>
                <td>Rp <?= number_format((float) $v['nominal_potongan'], 0, ',', '.') ?></td>
                <td><?= e($v['expire_at'] ?: '—') ?></td>
                <td>
                    <?php if ($v['pendaftaran_id']): ?>
                        <span class="badge badge-success">Terpakai</span>
                        <span class="muted" style="display:block;margin-top:3px;font-size:11px;"><?= e($v['nomor_daftar']) ?> &middot; <?= e($v['pendaftar']) ?></span>
                    <?php else: ?>
                        <span class="badge badge-muted">Belum</span>
                    <?php endif; ?>
                </td>
                <td class="col-aksi">
                    <div class="aksi-group">
                        <button type="button" class="btn-sm btn-sm-warning" onclick="openEditAkashi(<?= (int)$v['id'] ?>, '<?= e(addslashes($v['kode'])) ?>', '<?= e(addslashes($v['nama_pemenang'] ?? '')) ?>', <?= (int)$v['nominal_potongan'] ?>, '<?= e(addslashes($v['expire_at'] ?? '')) ?>')">Edit</button>
                        <?php if (!$v['pendaftaran_id']): ?>
                        <form method="post" class="inline" onsubmit="return confirm('Hapus kode <?= e($v['kode']) ?>?')">
                            <input type="hidden" name="csrf_token" value="<?= generateCsrfToken() ?>">
                            <input type="hidden" name="act" value="hapus">
                            <input type="hidden" name="id" value="<?= (int) $v['id'] ?>">
                            <button type="submit" class="btn-sm btn-sm-danger">Hapus</button>
                        </form>
                        <?php endif; ?>
                    </div>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    <?php endforeach; ?>
    <?php endif; ?>
</div>

<!-- ── MODAL EDIT VOUCHER ─────────────────────────────────────── -->
<div id="editAkashiModal" class="modal-backdrop" hidden>
    <div class="modal-card admin-form-card">
        <div class="admin-form-title modal-title-bar">
            <span>Edit Voucher — <code id="modalKode"></code></span>
            <button type="button" class="btn-sm btn-sm-secondary" onclick="closeEditAkashi()" aria-label="Tutup">&times;</button>
        </div>
        <form method="POST" class="admin-form">
            <input type="hidden" name="csrf_token" value="<?= generateCsrfToken() ?>">
            <input type="hidden" name="act" value="edit">
            <input type="hidden" name="id" id="modalId">
            <div class="form-group">
                <label>Nama Pemenang</label>
                <input type="text" name="nama_pemenang" id="modalNama" class="form-control" placeholder="cth: Ahmad Fauzi">
            </div>
            <div class="form-group">
                <label>Nominal Potongan (Rp)</label>
                <input type="number" name="nominal" id="modalNominal" class="form-control" min="1" step="1000" required>
            </div>
            <div class="form-group">
                <label>Masa Berlaku s/d</label>
                <input type="date" name="expire_at" id="modalExpire" class="form-control">
            </div>
            <div class="form-actions">
                <button type="submit" class="btn-sm btn-sm-primary">Simpan</button>
                <button type="button" class="btn-sm btn-sm-secondary" onclick="closeEditAkashi()">Batal</button>
            </div>
        </form>
    </div>
</div>

<script>
function openEditAkashi(id, kode, nama, nominal, expire) {
    document.getElementById('modalId').value = id;
    document.getElementById('modalKode').textContent = kode;
    document.getElementById('modalNama').value = nama;
    document.getElementById('modalNominal').value = nominal;
    document.getElementById('modalExpire').value = expire || '';
    var modal = document.getElementById('editAkashiModal');
    modal.removeAttribute('hidden');
    modal.style.display = 'flex';
}
function closeEditAkashi() {
    var modal = document.getElementById('editAkashiModal');
    modal.setAttribute('hidden', '');
    modal.style.display = 'none';
}
document.getElementById('editAkashiModal').addEventListener('click', function(e) {
    if (e.target === this) closeEditAkashi();
});
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') closeEditAkashi();
});
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>
