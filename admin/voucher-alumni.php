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
    fputcsv($out, ['Kode', 'NISN', 'Nama Siswa', 'Catatan', 'Masa Berlaku', 'Status', 'Dipakai Oleh']);
    $rows = $pdo->query(
        "SELECT v.*, p.nomor_daftar FROM voucher_alumni v
         LEFT JOIN pendaftaran p ON p.id = v.pendaftaran_id
         ORDER BY v.kode, v.id"
    )->fetchAll();
    $hasD = (bool) $pdo->query(
        "SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE()
         AND TABLE_NAME='voucher_alumni' AND COLUMN_NAME='deskripsi'"
    )->fetchColumn();
    foreach ($rows as $r) {
        fputcsv($out, [
            $r['kode'],
            $r['nisn'],
            $r['nama_siswa'] ?? '',
            ($hasD ? ($r['deskripsi'] ?? '') : ''),
            $r['expire_at'] ?? '',
            $r['pendaftaran_id'] ? 'Terpakai' : 'Belum',
            $r['nomor_daftar'] ?? '',
        ]);
    }
    fclose($out);
    exit;
}

// Cek kolom deskripsi (migration 018) — fallback kalau belum dijalankan
$hasDeskripsi = (bool) $pdo->query(
    "SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE()
     AND TABLE_NAME='voucher_alumni' AND COLUMN_NAME='deskripsi'"
)->fetchColumn();

// ── Proses POST ───────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    validateCsrf();
    $act = sanitizeString($_POST['act'] ?? '');

    if ($act === 'buat-group') {
        // Buat 1 kode baru berisi banyak NISN
        $deskripsi = $hasDeskripsi ? sanitizeString($_POST['deskripsi'] ?? '') : '';
        $expire = sanitizeString($_POST['expire_at'] ?? '') ?: null;
        $text = trim($_POST['daftar'] ?? '');
        $kodeManual = strtoupper(trim($_POST['kode_manual'] ?? ''));
        $lines = preg_split('/\r\n|\r|\n/', $text);
        $dibuat = 0;
        $duplikat = 0;

        // Tentukan kode: manual (validasi) atau generate otomatis
        if ($kodeManual !== '') {
            if (!preg_match('/^[A-Z0-9-]{4,20}$/', $kodeManual)) {
                $msg = 'Kode manual tidak valid (4-20 karakter A-Z/0-9/-).';
                $msgType = 'error';
            } else {
                $cekK = $pdo->prepare('SELECT 1 FROM voucher_alumni WHERE kode = ? LIMIT 1');
                $cekK->execute([$kodeManual]);
                if ($cekK->fetch()) {
                    $msg = 'Kode sudah dipakai. Gunakan kode lain.';
                    $msgType = 'error';
                } else {
                    $kode = $kodeManual;
                }
            }
        } else {
            do {
                $kode = generateVoucherKode();
                $cekK = $pdo->prepare('SELECT id FROM voucher_alumni WHERE kode = ? LIMIT 1');
                $cekK->execute([$kode]);
            } while ($cekK->fetch());
        }

        if ($msg === '') {
            foreach ($lines as $line) {
                $line = trim($line);
                if ($line === '') continue;
                // Format tiap baris: NISN;Nama (nama opsional)
                $parts = array_map('trim', preg_split('/[;|,]/', $line, 2));
                $nisn = $parts[0] ?? '';
                $nama = $parts[1] ?? '';
                if ($nisn === '') continue;
                // Pasangan kode+NISN harus unik
                $cek = $pdo->prepare('SELECT id FROM voucher_alumni WHERE kode = ? AND nisn = ?');
                $cek->execute([$kode, $nisn]);
                if ($cek->fetch()) { $duplikat++; continue; }
                $kolom = $hasDeskripsi
                    ? 'INSERT INTO voucher_alumni (kode, nisn, nama_siswa, deskripsi, jalur, expire_at, dibuat_oleh) VALUES (?,?,?,?,?,?,?)'
                    : 'INSERT INTO voucher_alumni (kode, nisn, nama_siswa, jalur, expire_at, dibuat_oleh) VALUES (?,?,?,?,?,?)';
                $params = $hasDeskripsi
                    ? [$kode, $nisn, $nama ?: null, $deskripsi ?: null, 'alumni-sdmua', $expire, (int) ($_SESSION['user_id'] ?? 0)]
                    : [$kode, $nisn, $nama ?: null, 'alumni-sdmua', $expire, (int) ($_SESSION['user_id'] ?? 0)];
                $pdo->prepare($kolom)->execute($params);
                $baruDibuat[] = $kode . ';' . $nisn . ($nama ? ';' . $nama : '');
                $dibuat++;
            }
            $ket = $dibuat > 0 ? "$dibuat NISN ditambahkan ke kode $kode" : 'Tidak ada NISN yang ditambahkan';
            if ($duplikat > 0) $ket .= ", $duplikat dilewati (sudah ada)";
            $msg = $ket . '.';
            $msgType = $dibuat > 0 ? 'success' : 'error';
        }
    } elseif ($act === 'tambah-nisn') {
        // Tambah NISN ke kode yang sudah ada
        $kode = strtoupper(trim($_POST['kode'] ?? ''));
        $nisn = trim($_POST['nisn'] ?? '');
        $nama = sanitizeString($_POST['nama_siswa'] ?? '');
        if ($kode === '' || $nisn === '') {
            $msg = 'Kode dan NISN wajib diisi.';
            $msgType = 'error';
        } else {
            $cek = $pdo->prepare('SELECT id FROM voucher_alumni WHERE kode = ? AND nisn = ?');
            $cek->execute([$kode, $nisn]);
            if ($cek->fetch()) {
                $msg = 'NISN sudah terdaftar pada kode ini.';
                $msgType = 'error';
            } else {
                // Warisi expire_at dari baris lain di kode yang sama
                $ref = $pdo->prepare('SELECT expire_at FROM voucher_alumni WHERE kode = ? LIMIT 1');
                $ref->execute([$kode]);
                $exp = $ref->fetchColumn() ?: null;
                $pdo->prepare(
                    'INSERT INTO voucher_alumni (kode, nisn, nama_siswa, jalur, expire_at, dibuat_oleh)
                     VALUES (?,?,?,?,?,?)'
                )->execute([$kode, $nisn, $nama ?: null, 'alumni-sdmua', $exp, (int) ($_SESSION['user_id'] ?? 0)]);
                $msg = "NISN $nisn ditambahkan ke kode $kode.";
                $msgType = 'success';
            }
        }
    } elseif ($act === 'edit') {
        // Edit nama / masa berlaku seluruh baris dalam 1 kode
        $kodeLama = strtoupper(trim($_POST['kode_lama'] ?? ''));
        $expire = sanitizeString($_POST['expire_at'] ?? '') ?: null;
        $deskripsi = $hasDeskripsi ? sanitizeString($_POST['deskripsi'] ?? '') : '';
        if ($kodeLama === '') {
            $msg = 'Kode tidak valid.';
            $msgType = 'error';
        } else {
            if ($hasDeskripsi) {
                $pdo->prepare(
                    'UPDATE voucher_alumni SET expire_at = ?, deskripsi = ? WHERE kode = ?'
                )->execute([$expire, $deskripsi ?: null, $kodeLama]);
            } else {
                $pdo->prepare(
                    'UPDATE voucher_alumni SET expire_at = ? WHERE kode = ?'
                )->execute([$expire, $kodeLama]);
            }
            $msg = "Voucher $kodeLama diperbarui.";
            $msgType = 'success';
        }
    } elseif ($act === 'hapus-nisn') {
        // Hapus 1 NISN dari kode (hanya boleh kalau belum terpakai)
        $id = sanitizeInt($_POST['id'] ?? 0);
        $cek = $pdo->prepare('SELECT pendaftaran_id FROM voucher_alumni WHERE id = ?');
        $cek->execute([$id]);
        $row = $cek->fetch();
        if ($row && $row['pendaftaran_id'] !== null) {
            $msg = 'NISN yang sudah terpakai tidak bisa dihapus.';
            $msgType = 'error';
        } else {
            $pdo->prepare('DELETE FROM voucher_alumni WHERE id = ?')->execute([$id]);
            $msg = 'NISN dihapus dari voucher.';
            $msgType = 'success';
        }
    } elseif ($act === 'hapus') {
        // Hapus seluruh kode (hanya baris yang belum terpakai; baris terpakai dipertahankan)
        $kode = strtoupper(trim($_POST['kode'] ?? ''));
        $pdo->prepare('DELETE FROM voucher_alumni WHERE kode = ? AND pendaftaran_id IS NULL')->execute([$kode]);
        $msg = "Voucher $kode dihapus (data terpakai dipertahankan).";
        $msgType = 'success';
    }
}

// ── Ambil data group per kode ──────────────────────────────────
$rows = $pdo->query(
    "SELECT v.*, p.nomor_daftar, p.nama_lengkap AS pendaftar
     FROM voucher_alumni v
     LEFT JOIN pendaftaran p ON p.id = v.pendaftaran_id
     ORDER BY v.id DESC"
)->fetchAll();

$groups = [];
foreach ($rows as $r) {
    $k = $r['kode'];
    if (!isset($groups[$k])) {
        $groups[$k] = [
            'kode' => $k,
            'expire_at' => $r['expire_at'],
            'deskripsi' => $r['deskripsi'] ?? null,
            'nama_siswa' => $r['nama_siswa'],
            'list' => [],
            'terpakai' => 0,
        ];
    }
    $groups[$k]['list'][] = $r;
    if ($r['pendaftaran_id']) $groups[$k]['terpakai']++;
}

$totalKode = count($groups);
$totalNisn = count($rows);
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
<div class="admin-form-card" style="margin-bottom:28px;">
    <div class="admin-form-title">Kode yang baru dibuat — salin &amp; bagikan ke sekolah</div>
    <p class="muted" style="margin-bottom:14px;">Format: KODE;NISN;Nama — siap di-paste ke Excel/WhatsApp.</p>
    <textarea readonly rows="<?= min(10, max(3, count($baruDibuat))) ?>" style="width:100%;font-family:monospace;font-size:13px;background:#f5f2eb;border:1px solid #d9d2c5;border-radius:4px;padding:10px;" onclick="this.select()"><?= e(implode("\n", $baruDibuat)) ?></textarea>
</div>
<?php endif; ?>

<div class="admin-form-card">
    <div class="admin-form-title">Buat Voucher Baru</div>
    <p class="muted" style="margin-bottom:20px;">
        Satu kode dipakai <strong>banyak NISN</strong>. Syarat klaim di portal:
        kode cocok <strong>dan</strong> NISN terdaftar pada kode itu.
        Setiap baris: <strong>NISN;Nama</strong>. Kode bisa diisi manual atau otomatis.
    </p>
    <form method="post" class="admin-form">
        <input type="hidden" name="csrf_token" value="<?= generateCsrfToken() ?>">
        <input type="hidden" name="act" value="buat-group">
        <div class="form-group" style="max-width:280px;">
            <label>Kode manual (opsional — kosongkan = otomatis)</label>
            <input type="text" name="kode_manual" class="form-control" placeholder="cth: ASQ-2026"
                   maxlength="20" style="text-transform:uppercase;" autocomplete="off">
        </div>
        <div class="form-group">
            <label>Daftar NISN (satu per baris)</label>
            <textarea name="daftar" rows="8" class="form-control" required
                      placeholder="1234567890;Ahmad Fauzi&#10;0987654321;Siti Aminah"></textarea>
        </div>
        <div class="admin-form" style="display:flex;gap:16px;flex-wrap:wrap;">
            <div class="form-group" style="max-width:220px;flex:1;min-width:180px;">
                <label>Masa berlaku (opsional)</label>
                <input type="date" name="expire_at" class="form-control">
            </div>
            <?php if ($hasDeskripsi): ?>
            <div class="form-group" style="flex:2;min-width:220px;">
                <label>Catatan (opsional)</label>
                <input type="text" name="deskripsi" class="form-control" placeholder="cth: Angkatan 2026 SD Ashidiq" maxlength="255">
            </div>
            <?php endif; ?>
        </div>
        <div class="form-actions">
            <button type="submit" class="btn-sm btn-sm-primary">Buat Voucher</button>
        </div>
    </form>
</div>

<div class="admin-table-wrap">
    <div class="table-head">
        <h2>Daftar Voucher
            <span class="badge" style="margin-left:6px;vertical-align:middle;"><?= $totalKode ?> kode</span>
            <span class="badge badge-muted" style="margin-left:4px;vertical-align:middle;"><?= $totalNisn ?> NISN</span>
            <span class="badge badge-success" style="margin-left:4px;vertical-align:middle;"><?= $totalPakai ?> terpakai</span>
        </h2>
        <a href="?export=1" class="btn-sm btn-sm-secondary">⬇ Ekspor CSV</a>
    </div>

    <?php if (empty($groups)): ?>
    <div class="table-empty">Belum ada voucher. Buat lewat form di atas.</div>
    <?php else: ?>
    <?php foreach ($groups as $g): ?>
    <details style="border-bottom:1px solid #f0ede6;padding:14px 20px;" <?= count($groups) === 1 ? 'open' : '' ?>>
        <summary style="cursor:pointer;list-style:none;display:flex;align-items:center;gap:10px;flex-wrap:wrap;">
            <code style="font-size:15px;font-weight:700;background:#f5f2eb;padding:4px 10px;border-radius:4px;"><?= e($g['kode']) ?></code>
            <span class="badge"><?= count($g['list']) ?> NISN</span>
            <?php if ($g['terpakai'] > 0): ?>
            <span class="badge badge-success"><?= $g['terpakai'] ?> terpakai</span>
            <?php endif; ?>
            <span class="muted" style="font-size:12px;">Berlaku s/d <?= e($g['expire_at'] ?? '—') ?></span>
            <?php if (!empty($g['deskripsi'])): ?>
            <span class="muted" style="font-size:12px;">— <?= e($g['deskripsi']) ?></span>
            <?php endif; ?>
            <span style="flex:1;"></span>
            <span onclick="event.preventDefault();if(confirm('Hapus seluruh kode <?= e($g['kode']) ?>? Data terpakai dipertahankan.')){document.getElementById('del-<?= (int) $g['list'][0]['id'] ?>').submit();}"
                  class="btn-sm btn-sm-danger" style="cursor:pointer;">Hapus Kode</span>
        </summary>
        <form method="post" id="del-<?= (int) $g['list'][0]['id'] ?>" style="display:none;"
              onsubmit="return confirm('Hapus seluruh kode <?= e($g['kode']) ?>? Data terpakai dipertahankan.')">
            <input type="hidden" name="csrf_token" value="<?= generateCsrfToken() ?>">
            <input type="hidden" name="act" value="hapus">
            <input type="hidden" name="kode" value="<?= e($g['kode']) ?>">
        </form>

        <div style="margin-top:14px;display:flex;gap:16px;flex-wrap:wrap;">
            <form method="post" class="admin-form" style="flex:1;min-width:240px;max-width:360px;background:#faf9f6;padding:14px;border-radius:6px;">
                <input type="hidden" name="csrf_token" value="<?= generateCsrfToken() ?>">
                <input type="hidden" name="act" value="edit">
                <input type="hidden" name="kode_lama" value="<?= e($g['kode']) ?>">
                <div class="form-group" style="margin-bottom:10px;">
                    <label>Masa berlaku</label>
                    <input type="date" name="expire_at" class="form-control" value="<?= e($g['expire_at'] ?? '') ?>">
                </div>
                <?php if ($hasDeskripsi): ?>
                <div class="form-group" style="margin-bottom:10px;">
                    <label>Catatan</label>
                    <input type="text" name="deskripsi" class="form-control" value="<?= e($g['deskripsi'] ?? '') ?>" maxlength="255">
                </div>
                <?php endif; ?>
                <button type="submit" class="btn-sm btn-sm-primary">Simpan</button>
            </form>

            <form method="post" class="admin-form" style="flex:1;min-width:240px;max-width:360px;background:#faf9f6;padding:14px;border-radius:6px;">
                <input type="hidden" name="csrf_token" value="<?= generateCsrfToken() ?>">
                <input type="hidden" name="act" value="tambah-nisn">
                <input type="hidden" name="kode" value="<?= e($g['kode']) ?>">
                <div class="form-group" style="margin-bottom:10px;">
                    <label>Tambah NISN</label>
                    <input type="text" name="nisn" class="form-control" placeholder="NISN" required autocomplete="off" inputmode="numeric">
                </div>
                <div class="form-group" style="margin-bottom:10px;">
                    <label>Nama (opsional)</label>
                    <input type="text" name="nama_siswa" class="form-control" placeholder="Nama siswa">
                </div>
                <button type="submit" class="btn-sm btn-sm-secondary">+ Tambah</button>
            </form>
        </div>

        <div style="overflow-x:auto;margin-top:12px;">
        <table class="admin-table" style="min-width:560px;">
            <thead>
                <tr>
                    <th>NISN</th>
                    <th>Nama</th>
                    <th>Status</th>
                    <th style="width:80px;">Aksi</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($g['list'] as $v): ?>
            <tr>
                <td><code><?= e($v['nisn']) ?></code></td>
                <td><?= e($v['nama_siswa'] ?? '—') ?></td>
                <td>
                    <?php if ($v['pendaftaran_id']): ?>
                        <span class="badge badge-success">Terpakai</span>
                        <div class="muted" style="font-size:11px;margin-top:4px;line-height:1.5;">
                            <?= e($v['nomor_daftar']) ?><br><?= e($v['pendaftar']) ?>
                        </div>
                    <?php else: ?>
                        <span class="badge badge-muted">Belum</span>
                    <?php endif; ?>
                </td>
                <td>
                    <?php if (!$v['pendaftaran_id']): ?>
                    <form method="post" onsubmit="return confirm('Hapus NISN <?= e($v['nisn']) ?> dari kode ini?')">
                        <input type="hidden" name="csrf_token" value="<?= generateCsrfToken() ?>">
                        <input type="hidden" name="act" value="hapus-nisn">
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