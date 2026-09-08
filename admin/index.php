<?php
require_once __DIR__ . '/bootstrap.php';
requireAdmin();

$adminTitle = 'Dashboard';
$adminPage  = 'admin';

$pdo = getDB();

// ── Statistik ringkas ─────────────────────────────────────────
$stats = [];

$stmtArtikel = $pdo->query("SELECT COUNT(*) FROM artikel WHERE status = 'published'");
$stats['artikel_published'] = (int) $stmtArtikel->fetchColumn();

$stmtDraft = $pdo->query("SELECT COUNT(*) FROM artikel WHERE status = 'draft'");
$stats['artikel_draft'] = (int) $stmtDraft->fetchColumn();

$stmtPsb = $pdo->query("SELECT COUNT(*) FROM pendaftaran WHERE status = 'pending'");
$stats['psb_pending'] = (int) $stmtPsb->fetchColumn();

$stmtPsbTotal = $pdo->query("SELECT COUNT(*) FROM pendaftaran");
$stats['psb_total'] = (int) $stmtPsbTotal->fetchColumn();

// ── Pendaftaran terbaru (5) ───────────────────────────────────
$stmtTerbaru = $pdo->query(
    "SELECT nomor_daftar, nama_lengkap, jenjang, status, created_at
     FROM pendaftaran ORDER BY created_at DESC LIMIT 5"
);
$pendaftaranTerbaru = $stmtTerbaru->fetchAll();

// ── Artikel terbaru (5) ───────────────────────────────────────
$stmtArtikelBaru = $pdo->query(
    "SELECT a.id, a.judul, a.kategori, a.status, a.views, a.published_at, u.name AS penulis
     FROM artikel a JOIN users u ON u.id = a.penulis_id
     ORDER BY a.created_at DESC LIMIT 5"
);
$artikelTerbaru = $stmtArtikelBaru->fetchAll();

$labelKategori = [
    'tahfidz'  => 'Tahfidz', 'akhlak' => 'Akhlak',
    'kajian'   => 'Kajian',  'kegiatan' => 'Kegiatan',
    'psb-info' => 'PSB',     'alumni' => 'Alumni',
];
$labelJenjang  = ['smp' => 'SMP', 'sma' => 'SMA', 'tahfidz-intensif' => 'Tahfidz'];

// ── Counter per status & gelombang (untuk dashboard) ─────
$statusCounts = $pdo->query(
    "SELECT status, COUNT(*) AS n FROM pendaftaran GROUP BY status"
)->fetchAll(PDO::FETCH_KEY_PAIR);

$gelombangCounts = $pdo->query(
    "SELECT pg.label, COUNT(p.id) AS n
     FROM pendaftaran_gelombang pg
     LEFT JOIN pendaftaran p ON p.gelombang_id = pg.id
     WHERE pg.is_active = 1
     GROUP BY pg.id, pg.label, pg.urutan
     ORDER BY pg.urutan"
)->fetchAll(PDO::FETCH_ASSOC);

$cicilanPending = (int) $pdo->query(
    "SELECT COUNT(*) FROM pembiayaan_cicilan WHERE status='pending'"
)->fetchColumn();

require_once __DIR__ . '/includes/header.php';
?>

<!-- ── STAT CARDS ──────────────────────────────────────────── -->
<div class="stat-grid">
    <div class="stat-card">
        <div class="stat-icon green" aria-hidden="true">✎</div>
        <div>
            <div class="stat-num"><?= $stats['artikel_published'] ?></div>
            <div class="stat-label">Artikel Terpublish</div>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon gold" aria-hidden="true">✏</div>
        <div>
            <div class="stat-num"><?= $stats['artikel_draft'] ?></div>
            <div class="stat-label">Artikel Draft</div>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon orange" aria-hidden="true">◎</div>
        <div>
            <div class="stat-num"><?= $stats['psb_pending'] ?></div>
            <div class="stat-label">PSB Menunggu Review</div>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon blue" aria-hidden="true">⊙</div>
        <div>
            <div class="stat-num"><?= $stats['psb_total'] ?></div>
            <div class="stat-label">Total Pendaftar</div>
        </div>
    </div>
</div>

<!-- ── RINGKASAN PSB ────────────────────────────────────────── -->
<div class="admin-table-wrap">
    <div class="table-head">
        <h2>Ringkasan Pendaftaran (5 Fase)</h2>
        <a href="<?= e(BASE_URL . '/admin/verifikasi-berkas') ?>" class="btn-sm btn-sm-primary">Verifikasi</a>
    </div>
    <div class="stat-grid" style="margin-bottom:20px;">
        <?php
        $faseList = [
            'pending' => ['label' => 'Baru Daftar', 'color' => 'blue'],
            'menunggu-verifikasi' => ['label' => 'Menunggu Verifikasi', 'color' => 'orange'],
            'tes-selesai' => ['label' => 'Tes Selesai', 'color' => 'gold'],
            'diterima' => ['label' => 'Diterima', 'color' => 'green'],
            'ditolak' => ['label' => 'Ditolak', 'color' => 'red'],
            'daftar-ulang' => ['label' => 'Daftar Ulang', 'color' => 'green'],
        ];
        foreach ($faseList as $k => $f):
        ?>
        <div class="stat-card stat-mini">
            <div class="stat-num"><?= $statusCounts[$k] ?? 0 ?></div>
            <div class="stat-label"><?= e($f['label']) ?></div>
        </div>
        <?php endforeach; ?>
    </div>
    <table class="admin-table">
        <thead><tr><th>Gelombang</th><th>Jumlah Pendaftar</th><th>Sisa Kuota</th></tr></thead>
        <tbody>
            <?php foreach ($gelombangCounts as $g):
                $stmtK = $pdo->prepare("SELECT target_kuota FROM pendaftaran_gelombang WHERE label=?");
                $stmtK->execute([$g['label']]);
                $kuota = (int) $stmtK->fetchColumn();
                $sisa = max(0, $kuota - (int) $g['n']);
            ?>
            <tr>
                <td><?= e($g['label']) ?></td>
                <td><strong><?= (int)$g['n'] ?></strong></td>
                <td><?= $kuota > 0 ? $sisa . ' / ' . $kuota : '—' ?></td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    <?php if ($cicilanPending > 0): ?>
        <p style="margin-top:12px;padding:10px 14px;background:#fff8e8;border-left:3px solid #c9a227;border-radius:3px;font-size:13px;">
            ⏳ Ada <strong><?= $cicilanPending ?></strong> cicilan menunggu verifikasi.
            <a href="<?= e(BASE_URL . '/admin/cicilan-verifikasi') ?>">Verifikasi sekarang →</a>
        </p>
    <?php endif; ?>
</div>

<!-- ── PENDAFTARAN TERBARU ──────────────────────────────────── -->
<div class="admin-table-wrap">
    <div class="table-head">
        <h2>Pendaftaran Terbaru</h2>
        <a href="<?= e(BASE_URL . '/admin/psb') ?>" class="btn-sm btn-sm-secondary">Lihat Semua</a>
    </div>
    <table class="admin-table">
        <thead>
            <tr>
                <th>Nomor Daftar</th>
                <th>Nama</th>
                <th>Jenjang</th>
                <th>Status</th>
                <th>Tanggal</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($pendaftaranTerbaru)): ?>
            <tr><td colspan="5" class="table-empty">Belum ada data pendaftaran.</td></tr>
            <?php else: ?>
            <?php foreach ($pendaftaranTerbaru as $row): ?>
            <tr>
                <td><strong><?= e($row['nomor_daftar']) ?></strong></td>
                <td><?= e($row['nama_lengkap']) ?></td>
                <td><?= e($labelJenjang[$row['jenjang']] ?? $row['jenjang']) ?></td>
                <td>
                    <span class="badge badge-<?= e($row['status']) ?>">
                        <?= e(ucfirst(str_replace('-', ' ', $row['status']))) ?>
                    </span>
                </td>
                <td><?= e(formatTanggal($row['created_at'])) ?></td>
            </tr>
            <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<!-- ── ARTIKEL TERBARU ───────────────────────────────────────── -->
<div class="admin-table-wrap">
    <div class="table-head">
        <h2>Artikel Terbaru</h2>
        <a href="<?= e(BASE_URL . '/admin/artikel-tambah') ?>" class="btn-sm btn-sm-primary">+ Tulis Artikel</a>
    </div>
    <table class="admin-table">
        <thead>
            <tr>
                <th>Judul</th>
                <th>Kategori</th>
                <th>Status</th>
                <th>Views</th>
                <th>Aksi</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($artikelTerbaru)): ?>
            <tr><td colspan="5" class="table-empty">Belum ada artikel.</td></tr>
            <?php else: ?>
            <?php foreach ($artikelTerbaru as $art): ?>
            <tr>
                <td style="max-width:300px;">
                    <a href="<?= e(BASE_URL . '/admin/artikel-edit?id=' . $art['id']) ?>"
                       style="color:var(--green-deep);text-decoration:none;font-weight:500;">
                        <?= e(truncate($art['judul'], 60)) ?>
                    </a>
                    <br>
                    <span style="font-size:11px;color:var(--text-light);"><?= e($art['penulis']) ?></span>
                </td>
                <td><?= e($labelKategori[$art['kategori']] ?? $art['kategori']) ?></td>
                <td>
                    <span class="badge badge-<?= e($art['status']) ?>">
                        <?= $art['status'] === 'published' ? 'Terpublish' : 'Draft' ?>
                    </span>
                </td>
                <td><?= number_format($art['views']) ?></td>
                <td>
                    <a href="<?= e(BASE_URL . '/admin/artikel-edit?id=' . $art['id']) ?>"
                       class="btn-sm btn-sm-warning">Edit</a>
                </td>
            </tr>
            <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
