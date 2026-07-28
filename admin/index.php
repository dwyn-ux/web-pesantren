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
$labelJenjang  = ['mts' => 'MTs', 'ma' => 'MA', 'tahfidz-intensif' => 'Tahfidz'];

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
