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
        // Generate N kode baru untuk 1 juara (tiap kode unik, 1x pakai)
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
        // Edit nama / nominal / masa berlaku 1 kode
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
        // Hapus 1 kode (hanya boleh kalau belum terpakai)
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
    <div class="admin-form-title">Kode yang baru dibuat — cetak & tempel di voucher hadiah</div>
    <p class="muted">Satu baris = satu kode unik. Tempel di voucher fisik pemenang.</p>
    <textarea readonly rows="<?= min(10, max(3, count($baruDibuat))) ?>" style="width:100%;font-family:monospace;font-size:13px;background:#f5f2eb;border:1px solid #d9d2c5;border-radius:4px;padding:10px;" onclick="this.select()"><?= e(implode("\n", $baruDibuat)) ?></textarea>
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
        <h2>Daftar Voucher Akashi
            <span class="badge badge-muted" style="margin-left:6px;vertical-align:middle;"><?= $totalKode ?> kode</span>
            <span class="badge badge-success" style="margin-left:4px;vertical-align:middle;"><?= $totalPakai ?> terpakai</span>
        </h2>
        <a href="?export=1" class="btn-sm btn-sm-secondary" style="text-decoration:none;">Ekspor CSV</a>
    </div>

    <?php if (empty($rows)): ?>
    <div class="table-empty">Belum ada voucher. Generate lewat form di atas.</div>
    <?php else: ?>
    <?php foreach (['juara-1', 'juara-2', 'juara-3'] as $jk): ?>
    <?php if (empty($groups[$jk])) continue; ?>
    <details <?= $jk === 'juara-1' ? 'open' : '' ?>>
        <summary>
            <strong><?= e($labelJuara[$jk]) ?></strong>
            <span class="badge badge-muted"><?= count($groups[$jk]) ?> kode</span>
            <span class="muted">Potongan Rp <?= number_format($akashiDefault[$jk], 0, ',', '.') ?></span>
        </summary>
        <div style="overflow-x:auto;margin-top:12px;">
        <table class="admin-table" style="min-width:680px;">
            <thead>
                <tr>
                    <th>Kode</th>
                    <th>Nama Pemenang</th>
                    <th>Nominal (Rp)</th>
                    <th>Berlaku s/d</th>
                    <th>Status</th>
                    <th style="width:170px;">Aksi</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($groups[$jk] as $v): ?>
            <tr>
                <td><code><?= e($v['kode']) ?></code></td>
                <td><?= e($v['nama_pemenang'] ?? '—') ?></td>
                <td>Rp <?= number_format((float) $v['nominal_potongan'], 0, ',', '.') ?></td>
                <td style="font-size:12px;"><?= e($v['expire_at'] ?? '—') ?></td>
                <td>
                    <?php if ($v['pendaftaran_id']): ?>
                        <span class="badge badge-success">Terpakai</span>
                        <div class="muted"><?= e($v['nomor_daftar']) ?><br><?= e($v['pendaftar']) ?></div>
                    <?php else: ?>
                        <span class="badge badge-muted">Belum</span>
                    <?php endif; ?>
                </td>
                <td>
                    <details>
                        <summary class="btn-sm btn-sm-warning" style="cursor:pointer;display:inline-block;">Edit</summary>
                        <form method="post" class="admin-form" style="margin-top:8px;background:#faf9f6;padding:12px;border-radius:6px;">
                            <input type="hidden" name="csrf_token" value="<?= generateCsrfToken() ?>">
                            <input type="hidden" name="act" value="edit">
                            <input type="hidden" name="id" value="<?= (int) $v['id'] ?>">
                            <div class="form-group" style="margin-bottom:8px;">
                                <label>Nama pemenang</label>
                                <input type="text" name="nama_pemenang" class="form-control" value="<?= e($v['nama_pemenang'] ?? '') ?>">
                            </div>
                            <div class="form-group" style="margin-bottom:8px;">
                                <label>Nominal (Rp)</label>
                                <input type="number" name="nominal" class="form-control" min="1" value="<?= (int) $v['nominal_potongan'] ?>">
                            </div>
                            <div class="form-group" style="margin-bottom:8px;">
                                <label>Berlaku s/d</label>
                                <input type="date" name="expire_at" class="form-control" value="<?= e($v['expire_at'] ?? '') ?>">
                            </div>
                            <button type="submit" class="btn-sm btn-sm-primary">Simpan</button>
                        </form>
                    </details>
                    <?php if (!$v['pendaftaran_id']): ?>
                    <form method="post" style="display:inline;" onsubmit="return confirm('Hapus kode <?= e($v['kode']) ?>?')">
                        <input type="hidden" name="csrf_token" value="<?= generateCsrfToken() ?>">
                        <input type="hidden" name="act" value="hapus">
                        <input type="hidden" name="id" value="<?= (int) $v['id'] ?>">
                        <button type="submit" class="btn-sm btn-sm-danger">Hapus</button>
                    </form>
                    <?php endif; ?>
                </td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        </div>
    </details>
    <?php endforeach; ?>
    <?php endif; ?>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>
