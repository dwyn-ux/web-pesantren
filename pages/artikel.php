<?php
// ── Parameter & validasi ─────────────────────────────────────
$halaman      = max(1, sanitizeInt($_GET['halaman'] ?? 1));
$perHalaman   = 6;
$katFilter    = sanitizeString($_GET['kategori'] ?? '');
$cari         = sanitizeString($_GET['cari'] ?? '');

$validKategori = ['tahfidz', 'akhlak', 'kajian', 'kegiatan', 'psb-info', 'alumni'];
if ($katFilter && !in_array($katFilter, $validKategori, true)) {
    $katFilter = '';
}

// ── Query helper ─────────────────────────────────────────────
$pdo    = getDB();
$offset = paginationOffset($halaman, $perHalaman);

// Bangun WHERE clause
$where  = "WHERE a.status = 'published'";
$params = [];
if ($katFilter) {
    $where    .= " AND a.kategori = ?";
    $params[]  = $katFilter;
}
if ($cari) {
    $where    .= " AND (a.judul LIKE ? OR a.ringkasan LIKE ?)";
    $keyword   = '%' . $cari . '%';
    $params[]  = $keyword;
    $params[]  = $keyword;
}

// Hitung total untuk pagination
$stmtCount = $pdo->prepare("SELECT COUNT(*) FROM artikel a $where");
$stmtCount->execute($params);
$totalArtikel = (int) $stmtCount->fetchColumn();
$totalHalaman = (int) ceil($totalArtikel / $perHalaman);

// Ambil artikel (dengan join nama penulis)
$stmtArtikel = $pdo->prepare(
    "SELECT a.id, a.slug, a.judul, a.ringkasan, a.foto, a.kategori, a.views, a.isi,
            a.published_at, u.name AS penulis
     FROM artikel a
     JOIN users u ON u.id = a.penulis_id
     $where
     ORDER BY a.published_at DESC
     LIMIT $perHalaman OFFSET $offset"
);
$stmtArtikel->execute($params);
$artikelList = $stmtArtikel->fetchAll();

// Artikel featured (paling atas di halaman pertama tanpa filter)
$featured = null;
if ($halaman === 1 && !$katFilter && !$cari && !empty($artikelList)) {
    $featured    = array_shift($artikelList);
}

// Hitung per kategori untuk sidebar
$stmtKat  = $pdo->query(
    "SELECT kategori, COUNT(*) AS jml FROM artikel WHERE status = 'published' GROUP BY kategori"
);
$katCount = [];
foreach ($stmtKat->fetchAll() as $row) {
    $katCount[$row['kategori']] = $row['jml'];
}

// Artikel populer (views tertinggi)
$stmtPopuler = $pdo->query(
    "SELECT slug, judul, published_at FROM artikel
     WHERE status = 'published'
     ORDER BY views DESC LIMIT 3"
);
$artikelPopuler = $stmtPopuler->fetchAll();

// ── Label kategori ────────────────────────────────────────────
$labelKategori = [
    'tahfidz'   => 'Tahfidz',
    'akhlak'    => 'Akhlak & Adab',
    'kajian'    => 'Kajian Islam',
    'kegiatan'  => 'Kegiatan Pesantren',
    'psb-info'  => 'PSB & Info',
    'alumni'    => 'Profil Alumni',
];

// ── URL pagination helper ────────────────────────────────────
function paginasiUrl(int $hal, string $kat, string $cari): string {
    $q = ['halaman' => $hal];
    if ($kat)  $q['kategori'] = $kat;
    if ($cari) $q['cari']     = $cari;
    return BASE_URL . '/artikel?' . http_build_query($q);
}

// ── Meta halaman ─────────────────────────────────────────────
$activePage      = 'artikel';
$pageTitle       = 'Artikel & Berita | ' . APP_NAME;
$pageDescription = 'Kumpulan tulisan, kajian ilmiah, dan berita terkini seputar dunia pesantren dan Al-Qur\'an dari Pondok Pesantren Ash-Shiddiq Ciamis.';
$pageCanonical   = BASE_URL . '/artikel';
if ($halaman > 1) $pageCanonical .= '?halaman=' . $halaman;

$extraHead = <<<'CSS'
<style>
/* ── GRID UTAMA ───────────────────────────────────────────────── */
.artikel-grid {
    display:grid; grid-template-columns:2fr 1fr; gap:50px;
    max-width:1100px; margin:0 auto; padding:80px 5%;
}

/* ── FEATURED ─────────────────────────────────────────────────── */
.artikel-featured {
    background:var(--white); border-radius:4px; overflow:hidden;
    margin-bottom:32px; display:flex; flex-direction:column;
    text-decoration:none; color:inherit;
    transition:transform 0.3s, box-shadow 0.3s;
}
.artikel-featured:hover { transform:translateY(-4px); box-shadow:0 16px 48px rgba(13,122,74,0.12); }
.featured-img {
    width:100%; height:280px;
    background:linear-gradient(135deg,#c8ead8 0%,#a0d4b8 100%);
    display:flex; align-items:center; justify-content:center;
    flex-direction:column; gap:10px; position:relative; overflow:hidden;
}
.featured-img img { width:100%; height:100%; object-fit:cover; display:block; }
.featured-img.image-missing::before,.card-img.image-missing::before { content:'Foto belum tersedia'; color:rgba(13,122,74,.55); font-size:11px; text-align:center; padding:10px; }
.featured-img::after {
    content:''; position:absolute; inset:0;
    background:linear-gradient(to top,rgba(13,122,74,0.5),transparent 50%);
}
.featured-img-placeholder p { font-family:'Courier New',monospace; font-size:11px; color:rgba(0,0,0,0.3); letter-spacing:1px; }
.featured-cat {
    position:absolute; bottom:20px; left:20px; z-index:1;
    background:var(--gold); color:var(--text-dark);
    font-size:10px; font-weight:700; letter-spacing:2px;
    text-transform:uppercase; padding:5px 12px; border-radius:2px;
}
.featured-body { padding:28px 32px 32px; }
.featured-meta { font-size:12px; color:var(--text-light); margin-bottom:12px; display:flex; gap:16px; }
.featured-title { font-family:'Plus Jakarta Sans',sans-serif; font-size:26px; line-height:1.35; margin-bottom:14px; color:var(--text-dark); }
.featured-excerpt { font-size:15px; line-height:1.75; color:var(--text-mid); margin-bottom:20px; }
.featured-link { font-size:13px; font-weight:600; color:var(--green-deep); letter-spacing:0.5px; display:inline-flex; align-items:center; gap:6px; border-bottom:1px solid var(--green-mid); padding-bottom:2px; }

/* ── ARTIKEL LIST ──────────────────────────────────────────────── */
.artikel-list { display:flex; flex-direction:column; gap:20px; }
.artikel-card {
    background:var(--white); border-radius:4px; overflow:hidden;
    display:flex; gap:0; text-decoration:none; color:inherit;
    transition:transform 0.3s, box-shadow 0.3s;
}
.artikel-card:hover { transform:translateY(-3px); box-shadow:0 12px 36px rgba(13,122,74,0.1); }
.card-img {
    width:120px; flex-shrink:0;
    background:linear-gradient(135deg,#d8f0e4,#b8dfc8);
    display:flex; align-items:center; justify-content:center; overflow:hidden;
}
.card-img img { width:100%; height:100%; object-fit:cover; display:block; }
.card-img-placeholder p { font-family:'Courier New',monospace; font-size:9px; color:rgba(0,0,0,0.25); letter-spacing:1px; writing-mode:vertical-rl; text-align:center; }
.card-body { padding:20px 22px; }
.card-cat { font-size:10px; font-weight:700; letter-spacing:2px; color:var(--green-mid); text-transform:uppercase; margin-bottom:8px; }
.card-title { font-family:'Plus Jakarta Sans',sans-serif; font-size:17px; line-height:1.4; margin-bottom:8px; color:var(--text-dark); }
.card-meta { font-size:11px; color:var(--text-light); }

/* ── EMPTY STATE ───────────────────────────────────────────────── */
.artikel-empty {
    background:var(--white); border-radius:4px; padding:60px 32px;
    text-align:center; color:var(--text-light);
}
.artikel-empty p { font-size:15px; margin-top:12px; }

/* ── FILTER BAR ────────────────────────────────────────────────── */
.filter-bar {
    display:flex; flex-wrap:wrap; gap:8px; margin-bottom:28px;
}
.filter-btn {
    padding:6px 14px; border-radius:20px; font-size:12px; font-weight:500;
    letter-spacing:0.5px; border:1.5px solid var(--cream-dark);
    background:var(--white); color:var(--text-mid); text-decoration:none;
    transition:all 0.2s; cursor:pointer;
}
.filter-btn:hover,
.filter-btn.active { background:var(--green-deep); color:white; border-color:var(--green-deep); }

/* ── PAGINATION ────────────────────────────────────────────────── */
.pagination { display:flex; justify-content:center; gap:8px; margin-top:40px; list-style:none; }
.pagination a, .pagination span {
    width:40px; height:40px; display:flex; align-items:center; justify-content:center;
    border:1.5px solid var(--cream-dark); background:var(--white);
    font-size:14px; color:var(--text-mid); border-radius:3px;
    text-decoration:none; transition:all 0.2s;
    font-family:'Plus Jakarta Sans',sans-serif;
}
.pagination a:hover, .pagination a[aria-current] {
    background:var(--green-deep); color:white; border-color:var(--green-deep);
}

/* ── SIDEBAR ───────────────────────────────────────────────────── */
.sidebar { position:sticky; top:90px; height:fit-content; }
.sidebar-widget { background:var(--white); border-radius:4px; padding:28px 24px; margin-bottom:24px; }
.sidebar-title {
    font-family:'Plus Jakarta Sans',sans-serif; font-size:18px; color:var(--text-dark);
    margin-bottom:20px; padding-bottom:12px;
    border-bottom:2px solid var(--cream-dark); position:relative;
}
.sidebar-title::after {
    content:''; position:absolute; bottom:-2px; left:0;
    width:40px; height:2px; background:var(--gold);
}
.search-box { display:flex; border:1.5px solid var(--cream-dark); border-radius:3px; overflow:hidden; }
.search-box input {
    flex:1; padding:11px 14px; border:none; outline:none;
    font-family:'Plus Jakarta Sans',sans-serif; font-size:14px;
    color:var(--text-dark); background:transparent;
}
.search-box button {
    padding:11px 16px; background:var(--green-deep); border:none;
    cursor:pointer; color:white; font-size:14px; transition:background 0.3s;
}
.search-box button:hover { background:var(--green-mid); }
.kategori-list { list-style:none; }
.kategori-list li { display:flex; justify-content:space-between; align-items:center; padding:10px 0; border-bottom:1px solid var(--cream-dark); }
.kategori-list li:last-child { border-bottom:none; }
.kategori-list a { font-size:13px; color:var(--text-mid); text-decoration:none; transition:color 0.2s; }
.kategori-list a:hover, .kategori-list a.active { color:var(--green-deep); font-weight:600; }
.kat-count { background:var(--cream); padding:2px 10px; border-radius:20px; font-size:11px; color:var(--text-light); }
.populer-item { display:flex; gap:14px; padding:12px 0; border-bottom:1px solid var(--cream-dark); }
.populer-item:last-child { border-bottom:none; }
.populer-item a { display:flex; gap:14px; text-decoration:none; color:inherit; width:100%; transition:opacity 0.2s; }
.populer-item a:hover { opacity:0.75; }
.populer-num { font-family:'Plus Jakarta Sans',sans-serif; font-size:24px; color:var(--cream-dark); font-style:italic; flex-shrink:0; line-height:1; }
.populer-title { font-size:13px; line-height:1.5; color:var(--text-dark); font-weight:500; }
.populer-date { font-size:11px; color:var(--text-light); margin-top:4px; }

@media (max-width:900px) {
    .artikel-grid { grid-template-columns:1fr; }
    .sidebar { position:static; }
}
@media (max-width:600px) {
    .artikel-grid { padding:50px 5%; }
    .card-img { width:90px; }
}
</style>
CSS;

?>

<!-- ══ PAGE HERO ════════════════════════════════════════════════ -->
<div class="page-hero">
    <div class="page-hero-pattern"></div>
    <div class="page-hero-content">
        <div class="page-hero-tag"><span></span>Kajian &amp; Informasi<span></span></div>
        <h1>Artikel &amp; Berita</h1>
        <p>Kumpulan tulisan, kajian ilmiah, dan berita terkini seputar dunia pesantren dan Al-Qur'an.</p>
    </div>
</div>

<!-- ══ KONTEN ═══════════════════════════════════════════════════ -->
<div class="artikel-grid">
    <!-- ── MAIN ──────────────────────────────────────────────── -->
    <main>

        <!-- Filter bar kategori -->
        <div class="filter-bar reveal">
            <a href="<?= e(BASE_URL . '/artikel') ?>"
               class="filter-btn<?= !$katFilter ? ' active' : '' ?>">Semua</a>
            <?php foreach ($labelKategori as $slug => $label): ?>
            <a href="<?= e(BASE_URL . '/artikel?kategori=' . $slug) ?>"
               class="filter-btn<?= $katFilter === $slug ? ' active' : '' ?>">
                <?= e($label) ?>
                <?php if (isset($katCount[$slug])): ?>
                    <span style="opacity:0.6;font-size:10px;"> (<?= $katCount[$slug] ?>)</span>
                <?php endif; ?>
            </a>
            <?php endforeach; ?>
        </div>

        <?php if ($cari): ?>
        <p class="reveal" style="font-size:14px;color:var(--text-light);margin-bottom:20px;">
            Hasil pencarian untuk: <strong><?= e($cari) ?></strong>
            (<?= $totalArtikel ?> artikel ditemukan)
            — <a href="<?= e(BASE_URL . '/artikel') ?>" style="color:var(--green-mid);">Hapus pencarian</a>
        </p>
        <?php endif; ?>

        <?php if ($featured): ?>
        <!-- Featured artikel -->
        <a href="<?= e(BASE_URL . '/artikel/' . $featured['slug']) ?>" class="artikel-featured reveal">
            <div class="featured-img">
                <?php if ($featured['foto']): ?>
                <img src="<?= e(BASE_URL . '/uploads/artikel/' . basename($featured['foto'])) ?>"
                     alt="<?= e($featured['judul']) ?>" loading="lazy" onerror="this.hidden=true;this.parentElement.classList.add('image-missing')">
                <?php else: ?>
                <div class="featured-img-placeholder">
                    <svg width="52" height="52" viewBox="0 0 52 52" fill="none" opacity="0.4" aria-hidden="true">
                        <rect x="6" y="8" width="40" height="36" rx="2" stroke="#0d7a4a" stroke-width="1.5"/>
                        <path d="M14 18h24M14 24h18M14 30h12" stroke="#0d7a4a" stroke-width="1.5"/>
                    </svg>
                    <p>foto artikel utama</p>
                </div>
                <?php endif; ?>
                <div class="featured-cat"><?= e($labelKategori[$featured['kategori']] ?? $featured['kategori']) ?></div>
            </div>
            <div class="featured-body">
                <div class="featured-meta">
                    <span><?= $featured['published_at'] ? e(formatTanggal($featured['published_at'])) : '' ?></span>
                    <span>·</span>
                    <span><?= readingTime($featured['isi']) ?> menit baca</span>
                </div>
                <h2 class="featured-title"><?= e($featured['judul']) ?></h2>
                <?php if ($featured['ringkasan']): ?>
                <p class="featured-excerpt"><?= e(truncate($featured['ringkasan'], 220)) ?></p>
                <?php endif; ?>
                <span class="featured-link">Baca Selengkapnya &rarr;</span>
            </div>
        </a>
        <?php endif; ?>

        <!-- Artikel list -->
        <?php if (!empty($artikelList)): ?>
        <div class="artikel-list">
            <?php foreach ($artikelList as $artikel): ?>
            <a href="<?= e(BASE_URL . '/artikel/' . $artikel['slug']) ?>" class="artikel-card reveal">
                <div class="card-img">
                    <?php if ($artikel['foto']): ?>
                    <img src="<?= e(BASE_URL . '/uploads/artikel/' . basename($artikel['foto'])) ?>"
                         alt="<?= e($artikel['judul']) ?>" loading="lazy" onerror="this.hidden=true;this.parentElement.classList.add('image-missing')">
                    <?php else: ?>
                    <div class="card-img-placeholder">
                        <p>foto artikel</p>
                    </div>
                    <?php endif; ?>
                </div>
                <div class="card-body">
                    <div class="card-cat"><?= e($labelKategori[$artikel['kategori']] ?? $artikel['kategori']) ?></div>
                    <h3 class="card-title"><?= e($artikel['judul']) ?></h3>
                    <div class="card-meta">
                        <?= $artikel['published_at'] ? e(formatTanggal($artikel['published_at'])) : '' ?>
                        · <?= readingTime($artikel['isi']) ?> menit baca
                    </div>
                </div>
            </a>
            <?php endforeach; ?>
        </div>
        <?php elseif (!$featured): ?>
        <div class="artikel-empty reveal">
            <svg width="48" height="48" viewBox="0 0 48 48" fill="none" opacity="0.3" aria-hidden="true">
                <rect x="6" y="6" width="36" height="36" rx="4" stroke="#0d7a4a" stroke-width="1.5"/>
                <path d="M14 18h20M14 24h14M14 30h10" stroke="#0d7a4a" stroke-width="1.5"/>
            </svg>
            <p>
                <?= $cari ? 'Tidak ada artikel yang cocok dengan pencarian Anda.' : 'Belum ada artikel di kategori ini.' ?>
            </p>
        </div>
        <?php endif; ?>

        <!-- Pagination -->
        <?php if ($totalHalaman > 1): ?>
        <ul class="pagination reveal" aria-label="Navigasi halaman">
            <?php if ($halaman > 1): ?>
            <li><a href="<?= e(paginasiUrl($halaman - 1, $katFilter, $cari)) ?>" aria-label="Halaman sebelumnya">&laquo;</a></li>
            <?php endif; ?>

            <?php
            $rangeStart = max(1, $halaman - 2);
            $rangeEnd   = min($totalHalaman, $halaman + 2);
            for ($p = $rangeStart; $p <= $rangeEnd; $p++):
            ?>
            <li>
                <?php if ($p === $halaman): ?>
                <span aria-current="page"><?= $p ?></span>
                <?php else: ?>
                <a href="<?= e(paginasiUrl($p, $katFilter, $cari)) ?>"><?= $p ?></a>
                <?php endif; ?>
            </li>
            <?php endfor; ?>

            <?php if ($halaman < $totalHalaman): ?>
            <li><a href="<?= e(paginasiUrl($halaman + 1, $katFilter, $cari)) ?>" aria-label="Halaman berikutnya">&raquo;</a></li>
            <?php endif; ?>
        </ul>
        <?php endif; ?>

    </main>

    <!-- ── SIDEBAR ────────────────────────────────────────────── -->
    <aside class="sidebar">

        <!-- Search -->
        <div class="sidebar-widget reveal">
            <p class="sidebar-title">Cari Artikel</p>
            <form method="GET" action="<?= e(BASE_URL . '/artikel') ?>" role="search">
                <?php if ($katFilter): ?>
                <input type="hidden" name="kategori" value="<?= e($katFilter) ?>">
                <?php endif; ?>
                <div class="search-box">
                    <input type="search" name="cari" placeholder="Kata kunci..."
                           value="<?= e($cari) ?>" aria-label="Cari artikel" maxlength="100">
                    <button type="submit" aria-label="Cari">&#128269;</button>
                </div>
            </form>
        </div>

        <!-- Kategori -->
        <div class="sidebar-widget reveal">
            <p class="sidebar-title">Kategori</p>
            <ul class="kategori-list">
                <?php foreach ($labelKategori as $slug => $label): ?>
                <li>
                    <a href="<?= e(BASE_URL . '/artikel?kategori=' . $slug) ?>"
                       class="<?= $katFilter === $slug ? 'active' : '' ?>">
                        <?= e($label) ?>
                    </a>
                    <span class="kat-count"><?= $katCount[$slug] ?? 0 ?></span>
                </li>
                <?php endforeach; ?>
            </ul>
        </div>

        <!-- Artikel Populer -->
        <?php if (!empty($artikelPopuler)): ?>
        <div class="sidebar-widget reveal">
            <p class="sidebar-title">Artikel Populer</p>
            <?php foreach ($artikelPopuler as $i => $pop): ?>
            <div class="populer-item">
                <a href="<?= e(BASE_URL . '/artikel/' . $pop['slug']) ?>">
                    <span class="populer-num"><?= str_pad($i + 1, 2, '0', STR_PAD_LEFT) ?></span>
                    <div>
                        <p class="populer-title"><?= e(truncate($pop['judul'], 70)) ?></p>
                        <p class="populer-date"><?= $pop['published_at'] ? e(formatTanggal($pop['published_at'])) : '' ?></p>
                    </div>
                </a>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>

        <!-- CTA PSB -->
        <div class="sidebar-widget reveal" style="background:var(--green-deep);text-align:center;padding:32px 24px;">
            <p style="font-family:'Noto Sans Arabic',sans-serif;font-size:22px;color:var(--gold);margin-bottom:12px;">هَلُمَّ إِلَى الْقُرْآنِ</p>
            <p style="font-size:14px;color:rgba(255,255,255,0.7);line-height:1.7;margin-bottom:20px;">
                Daftarkan putra-putri Anda sekarang. PSB 2025/2026 telah dibuka!
            </p>
            <a href="<?= e(BASE_URL . '/psb') ?>" class="btn-primary"
               style="width:100%;display:block;text-align:center;">Daftar Sekarang</a>
        </div>

    </aside>
</div>
