<?php
require_once __DIR__ . '/bootstrap.php';
requireAdmin();

$pdo = getDB();
$adminTitle = 'Voucher Jalur Alumni';
$adminPage  = 'admin/voucher-alumni';

$msg = '';
$msgType = '';
$baruDibuat = [];

// ── Ekspor CSV ────────────────────────────────────────────────
if (isset($_GET['export']) && $_GET['export'] === '1') {
    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="voucher-alumni.csv"');
    $out = fopen('php://output', 'w');
    fwrite($out, "\xEF\xBB\xBF"); // BOM supaya terbaca Excel
    fputcsv($out, ['Kode', 'NISN', 'Nama Siswa', 'Masa Berlaku', 'Status', 'Dipakai Oleh']);
    $rows = $pdo->query(
        "SELECT v.*, p.nomor_daftar FROM voucher_alumni v
         LEFT JOIN pendaftaran p ON p.id = v.pendaftaran_id
         ORDER BY v.id DESC"
    )->fetchAll();
    foreach ($rows as $r) {
        fputcsv($out, [
            $r['kode'],
            $r['nisn'],
            $r['nama_siswa'] ?? '',
            $r['expire_at'] ?? '',
            $r['pendaftaran_id'] ? 'Terpakai' : 'Belum',
            $r['nomor_daftar'] ?? '',
        ]);
    }
    fclose($out);
    exit;
}

// ── Proses POST ───────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    validateCsrf();
    $act = sanitizeString($_POST['act'] ?? '');

    if ($act === 'generate') {
        $text = trim($_POST['daftar'] ?? '');
        $expire = sanitizeString($_POST['expire_at'] ?? '') ?: null;
        $lines = preg_split('/\r\n|\r|\n/', $text);
        $dibuat = 0;
        $duplikat = 0;
        $barisKosong = 0;

        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '') continue;
            // Format tiap baris: NISN;Nama (nama opsional)
            $parts = array_map('trim', preg_split('/[;|,]/', $line, 2));
            $nisn = $parts[0] ?? '';
            $nama = $parts[1] ?? '';
            if ($nisn === '') { $barisKosong++; continue; }

            // Satu NISN hanya boleh punya satu voucher
            $cek = $pdo->prepare('SELECT id FROM voucher_alumni WHERE nisn = ?');
            $cek->execute([$nisn]);
            if ($cek->fetch()) { $duplikat++; continue; }

            // Generate kode unik (cek tabrakan)
            do {
                $kode = generateVoucherKode();
                $cekK = $pdo->prepare('SELECT id FROM voucher_alumni WHERE kode = ?');
                $cekK->execute([$kode]);
            } while ($cekK->fetch());

            $pdo->prepare(
                'INSERT INTO voucher_alumni (kode, nisn, nama_siswa, jalur, expire_at, dibuat_oleh)
                 VALUES (?,?,?,?,?,?)'
            )->execute([$kode, $nisn, $nama ?: null, 'alumni-sdmua', $expire, (int) ($_SESSION['user_id'] ?? 0)]);
            $baruDibuat[] = $kode . ';' . $nisn . ($nama ? ';' . $nama : '');
            $dibuat++;
        }

        $ket = [];
        if ($dibuat > 0) $ket[] = "$dibuat voucher dibuat";
        if ($duplikat > 0) $ket[] = "$duplikat dilewati (NISN sudah ada)";
        if ($barisKosong > 0) $ket[] = "$barisKosong baris diabaikan";
        $msg = $dibuat > 0
            ? implode(', ', $ket) . '.'
            : 'Tidak ada voucher yang dibuat. ' . implode(', ', $ket) . '.';
        $msgType = $dibuat > 0 ? 'success' : 'error';
    } elseif ($act === 'hapus') {
        $id = sanitizeInt($_POST['id'] ?? 0);
        $pdo->prepare('DELETE FROM voucher_alumni WHERE id = ?')->execute([$id]);
        $msg = 'Voucher dihapus.';
        $msgType = 'success';
    }
}

$vouchers = $pdo->query(
    "SELECT v.*, p.nomor_daftar, p.nama_lengkap AS pendaftar
     FROM voucher_alumni v
     LEFT JOIN pendaftaran p ON p.id = v.pendaftaran_id
     ORDER BY v.id DESC"
)->fetchAll();

$total = count($vouchers);
$terpakai = 0;
foreach ($vouchers as $v) { if ($v['pendaftaran_id']) $terpakai++; }

include __DIR__ . '/includes/header.php';
?>

<?php if ($msg): ?>
<div class="flash-message flash-<?= e($msgType) ?>"><?= e($msg) ?>
    <button class="flash-close" onclick="this.parentElement.remove()">&times;</button>
</div>
<?php endif; ?>

<?php if (!empty($baruDibuat)): ?>
<div class="card" style="margin-bottom:20px;">
    <h3>Kode yang baru dibuat — salin & bagikan ke sekolah</h3>
    <p class="muted">Format: KODE;NISN;Nama — siap di-paste ke Excel/WhatsApp.</p>
    <textarea readonly rows="<?= min(10, max(3, count($baruDibuat))) ?>" style="width:100%;font-family:monospace;font-size:13px;background:#f5f2eb;border:1px solid #d9d2c5;border-radius:4px;padding:10px;" onclick="this.select()"><?= e(implode("\n", $baruDibuat)) ?></textarea>
</div>
<?php endif; ?>

<div class="card" style="margin-bottom:20px;">
    <h3>Buat Voucher Jalur Alumni (SD Muhammadiyah Unggulan Ashidiq)</h3>
    <p class="muted">
        Voucher diberikan khusus siswa SD Ashidiq (satu yayasan). Setiap baris: <strong>NISN;Nama</strong>.
        Satu NISN = satu voucher sekali pakai. Setelah dicetak, calon memasukkan kode + NISN di portal untuk mengklaim jalur Alumni.
    </p>
    <form method="post">
        <input type="hidden" name="csrf_token" value="<?= generateCsrfToken() ?>">
        <input type="hidden" name="act" value="generate">
        <div class="form-group">
            <label>Daftar NISN (satu per baris)</label>
            <textarea name="daftar" rows="8" class="form-control" required
                      placeholder="1234567890;Ahmad Fauzi&#10;0987654321;Siti Aminah"></textarea>
        </div>
        <div class="form-group" style="max-width:220px;">
            <label>Masa berlaku (opsional)</label>
            <input type="date" name="expire_at" class="form-control">
        </div>
        <button type="submit" class="btn-primary">Generate Voucher</button>
    </form>
</div>

<div class="card">
    <div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:12px;margin-bottom:16px;">
        <h3 style="margin:0;">Daftar Voucher
            <span class="badge"><?= $total ?></span>
            <span class="badge badge-success"><?= $terpakai ?> terpakai</span>
            <span class="badge badge-muted"><?= $total - $terpakai ?> sisa</span>
        </h3>
        <a href="?export=1" class="btn-outline">⬇ Ekspor CSV</a>
    </div>

    <?php if (empty($vouchers)): ?>
    <p class="muted">Belum ada voucher. Buat lewat form di atas.</p>
    <?php else: ?>
    <table class="admin-table" style="font-size:13px;">
        <tr>
            <th>Kode</th>
            <th>NISN</th>
            <th>Nama</th>
            <th>Masa Berlaku</th>
            <th>Status</th>
            <th></th>
        </tr>
        <?php foreach ($vouchers as $v): ?>
        <tr>
            <td><code><?= e($v['kode']) ?></code></td>
            <td><?= e($v['nisn']) ?></td>
            <td><?= e($v['nama_siswa'] ?? '—') ?></td>
            <td><?= e($v['expire_at'] ?? '—') ?></td>
            <td>
                <?php if ($v['pendaftaran_id']): ?>
                    <span class="badge badge-success">Terpakai</span>
                    <small class="muted"><?= e($v['nomor_daftar']) ?><br><?= e($v['pendaftar']) ?></small>
                <?php else: ?>
                    <span class="badge badge-muted">Belum</span>
                <?php endif; ?>
            </td>
            <td>
                <?php if (!$v['pendaftaran_id']): ?>
                <form method="post" style="margin:0;" onsubmit="return confirm('Hapus voucher ini?')">
                    <input type="hidden" name="csrf_token" value="<?= generateCsrfToken() ?>">
                    <input type="hidden" name="act" value="hapus">
                    <input type="hidden" name="id" value="<?= (int) $v['id'] ?>">
                    <button class="btn-sm btn-sm-secondary" style="font-size:11px;">Hapus</button>
                </form>
                <?php endif; ?>
            </td>
        </tr>
        <?php endforeach; ?>
    </table>
    <?php endif; ?>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>