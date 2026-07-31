<?php
// ── Ambil slug dari router ────────────────────────────────────
// index.php meneruskan $slug saat page = 'artikel/{slug}'
$artikel = null;

if (!empty($slug)) {
    $pdo  = getDB();
    $stmt = $pdo->prepare(
        "SELECT a.*, u.name AS penulis
         FROM artikel a
         JOIN users u ON u.id = a.penulis_id
         WHERE a.slug = ? AND a.status = 'published'
         LIMIT 1"
    );
    $stmt->execute([$slug]);
    $artikel = $stmt->fetch() ?: null;
}

if (!$artikel) {
    http_response_code(404);
    $activePage      = 'artikel';
    $pageTitle       = 'Artikel Tidak Ditemukan | ' . APP_NAME;
    $pageDescription = 'Artikel yang Anda cari tidak ditemukan.';
    $pageCanonical   = BASE_URL . '/artikel';
    ?>
    <div style="max-width:700px;margin:120px auto;text-align:center;padding:0 5%;">
        <p style="font-size:80px;margin-bottom:0;">📄</p>
        <h1 style="font-family:'Plus Jakarta Sans',sans-serif;font-size:32px;color:var(--green-deep);margin-bottom:16px;">Artikel Tidak Ditemukan</h1>
        <p style="color:var(--text-mid);margin-bottom:32px;">Artikel yang Anda cari tidak tersedia atau telah dihapus.</p>
        <a href="<?= e(BASE_URL . '/artikel') ?>" class="btn-primary">Kembali ke Daftar Artikel</a>
    </div>
    <?php
    return;
}

// Tambah view count (fire-and-forget)
$pdo->prepare("UPDATE artikel SET views = views + 1 WHERE id = ?")->execute([$artikel['id']]);

// ── Artikel terkait (same kategori, exclude current) ─────────
$stmtTerkait = $pdo->prepare(
    "SELECT slug, judul, foto, published_at, isi
     FROM artikel
     WHERE kategori = ? AND status = 'published' AND id != ?
     ORDER BY published_at DESC LIMIT 3"
);
$stmtTerkait->execute([$artikel['kategori'], $artikel['id']]);
$artikelTerkait = $stmtTerkait->fetchAll();

// ── Navigasi prev/next ────────────────────────────────────────
$stmtNext = $pdo->prepare(
    "SELECT slug, judul FROM artikel
     WHERE status = 'published' AND published_at > ?
     ORDER BY published_at ASC LIMIT 1"
);
$stmtNext->execute([$artikel['published_at']]);
$nextArtikel = $stmtNext->fetch();

$stmtPrev = $pdo->prepare(
    "SELECT slug, judul FROM artikel
     WHERE status = 'published' AND published_at < ?
     ORDER BY published_at DESC LIMIT 1"
);
$stmtPrev->execute([$artikel['published_at']]);
$prevArtikel = $stmtPrev->fetch();

// ── Label kategori ────────────────────────────────────────────
$labelKategori = [
    'tahfidz'   => 'Tahfidz',
    'akhlak'    => 'Akhlak & Adab',
    'kajian'    => 'Kajian Islam',
    'kegiatan'  => 'Kegiatan Pesantren',
    'psb-info'  => 'PSB & Info',
    'alumni'    => 'Profil Alumni',
];

// ── Meta halaman ─────────────────────────────────────────────
$activePage      = 'artikel';
$pageTitle       = e($artikel['judul']) . ' | ' . APP_NAME;
$pageDescription = $artikel['ringkasan']
    ? truncate($artikel['ringkasan'], 160)
    : truncate(strip_tags($artikel['isi']), 160);
$pageCanonical   = BASE_URL . '/artikel/' . $artikel['slug'];
$pageOgImage     = $artikel['foto']
    ? BASE_URL . '/uploads/artikel/' . $artikel['foto']
    : BASE_URL . '/assets/img/og-default.jpg';

$extraHead = <<<'CSS'
<style>
/* ── LAYOUT ARTIKEL DETAIL ────────────────────────────────────── */
.detail-wrap {
    display:grid; grid-template-columns:1fr 320px; gap:50px;
    max-width:1100px; margin:0 auto; padding:60px 5% 80px;
}
.artikel-body { min-width:0; }

/* Hero gambar */
.detail-hero-img {
    width:100%; aspect-ratio:16/7; border-radius:4px; overflow:hidden;
    background:linear-gradient(135deg,#c8ead8,#a0d4b8);
    display:flex; align-items:center; justify-content:center;
    margin-bottom:36px;
}
.detail-hero-img img { width:100%; height:100%; object-fit:cover; display:block; }
.detail-hero-img.image-missing::before,.terkait-img.image-missing::before { content:'Foto belum tersedia'; color:rgba(13,122,74,.55); font-size:12px; }
.detail-hero-img-placeholder p { font-family:'Courier New',monospace; font-size:11px; color:rgba(0,0,0,0.3); letter-spacing:1px; }

/* Meta */
.detail-meta {
    display:flex; flex-wrap:wrap; gap:16px; align-items:center;
    font-size:13px; color:var(--text-light); margin-bottom:20px;
}
.detail-kat {
    background:var(--gold); color:var(--text-dark);
    font-size:10px; font-weight:700; letter-spacing:2px;
    text-transform:uppercase; padding:4px 12px; border-radius:2px;
}
.detail-title {
    font-family:'Plus Jakarta Sans',sans-serif;
    font-size:clamp(26px,4vw,40px); line-height:1.3;
    color:var(--text-dark); margin-bottom:12px;
}
.detail-ringkasan {
    font-size:17px; line-height:1.8; color:var(--text-mid);
    border-left:4px solid var(--gold); padding-left:20px;
    margin-bottom:36px; font-style:italic;
}

/* Konten artikel */
.detail-content {
    font-size:16px; line-height:1.9; color:var(--text-mid);
}
.detail-content h2, .detail-content h3 {
    font-family:'Plus Jakarta Sans',sans-serif; color:var(--text-dark);
    margin:32px 0 16px;
}
.detail-content h2 { font-size:24px; }
.detail-content h3 { font-size:20px; }
.detail-content p  { margin-bottom:20px; }
.detail-content ul, .detail-content ol { margin:0 0 20px 24px; }
.detail-content li { margin-bottom:8px; }
.detail-content blockquote {
    border-left:4px solid var(--gold); padding:16px 20px;
    background:var(--cream); margin:24px 0;
    font-style:italic; color:var(--text-mid);
}
.detail-content img {
    max-width:100%; border-radius:4px; margin:24px 0; display:block;
}
.detail-content a { color:var(--green-mid); }
.detail-content strong { color:var(--text-dark); }

/* Navigasi prev/next */
.artikel-nav {
    display:grid; grid-template-columns:1fr 1fr; gap:20px;
    margin-top:48px; padding-top:32px; border-top:1px solid var(--cream-dark);
}
.artikel-nav a {
    background:var(--white); border-radius:4px; padding:20px;
    text-decoration:none; color:inherit; transition:box-shadow 0.2s;
    display:block;
}
.artikel-nav a:hover { box-shadow:0 4px 20px rgba(13,122,74,0.1); }
.nav-label { font-size:11px; color:var(--text-light); letter-spacing:1px; text-transform:uppercase; margin-bottom:8px; }
.nav-judul { font-family:'Plus Jakarta Sans',sans-serif; font-size:15px; line-height:1.4; color:var(--text-dark); }
.artikel-nav .next-link { text-align:right; }

/* Artikel terkait */
.terkait-section { margin-top:48px; padding-top:32px; border-top:1px solid var(--cream-dark); }
.terkait-title { font-family:'Plus Jakarta Sans',sans-serif; font-size:22px; color:var(--text-dark); margin-bottom:24px; }
.terkait-grid { display:grid; grid-template-columns:repeat(3,1fr); gap:16px; }
.terkait-card {
    background:var(--white); border-radius:4px; overflow:hidden;
    text-decoration:none; color:inherit; transition:box-shadow 0.2s;
    display:block;
}
.terkait-card:hover { box-shadow:0 8px 24px rgba(13,122,74,0.1); }
.terkait-img {
    height:130px; background:linear-gradient(135deg,#d8f0e4,#b8dfc8);
    display:flex; align-items:center; justify-content:center; overflow:hidden;
}
.terkait-img img { width:100%; height:100%; object-fit:cover; display:block; }
.terkait-body { padding:16px; }
.terkait-judul { font-family:'Plus Jakarta Sans',sans-serif; font-size:14px; line-height:1.4; color:var(--text-dark); margin-bottom:6px; }
.terkait-meta  { font-size:11px; color:var(--text-light); }

/* Sidebar detail */
.detail-sidebar { position:sticky; top:90px; height:fit-content; }
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
.share-btn {
    display:flex; align-items:center; gap:12px; width:100%;
    padding:11px 16px; border-radius:3px; border:none; cursor:pointer;
    font-family:'Plus Jakarta Sans',sans-serif; font-size:13px;
    font-weight:500; transition:opacity 0.2s; text-decoration:none; margin-bottom:10px;
}
.share-btn:hover { opacity:0.85; }
.share-wa  { background:#25d366; color:white; }
.share-fb  { background:#1877f2; color:white; }
.share-cp  { background:var(--cream-dark); color:var(--text-dark); }

@media (max-width:900px) {
    .detail-wrap { grid-template-columns:1fr; }
    .detail-sidebar { position:static; }
    .terkait-grid { grid-template-columns:1fr 1fr; }
}
@media (max-width:600px) {
    .artikel-nav { grid-template-columns:1fr; }
    .terkait-grid { grid-template-columns:1fr; }
}
</style>
CSS;

?>

<!-- ══ BREADCRUMB ════════════════════════════════════════════════ -->
<div style="background:var(--cream);padding:16px 5%;font-size:13px;color:var(--text-light);">
    <div style="max-width:1100px;margin:0 auto;">
        <a href="<?= e(BASE_URL . '/') ?>" style="color:var(--text-light);text-decoration:none;">Beranda</a>
        &rsaquo;
        <a href="<?= e(BASE_URL . '/artikel') ?>" style="color:var(--text-light);text-decoration:none;">Artikel</a>
        &rsaquo;
        <a href="<?= e(BASE_URL . '/artikel?kategori=' . $artikel['kategori']) ?>"
           style="color:var(--text-light);text-decoration:none;">
            <?= e($labelKategori[$artikel['kategori']] ?? $artikel['kategori']) ?>
        </a>
        &rsaquo;
        <span style="color:var(--text-dark);"><?= e(truncate($artikel['judul'], 60)) ?></span>
    </div>
</div>

<!-- ══ KONTEN ═══════════════════════════════════════════════════ -->
<div class="detail-wrap">

    <!-- ── ARTIKEL UTAMA ──────────────────────────────────────── -->
    <article class="artikel-body">

        <!-- Gambar hero -->
        <div class="detail-hero-img">
            <?php if ($artikel['foto']): ?>
            <img src="<?= e(BASE_URL . '/uploads/artikel/' . basename($artikel['foto'])) ?>"
                 alt="<?= e($artikel['judul']) ?>" onerror="this.hidden=true;this.parentElement.classList.add('image-missing')">
            <?php else: ?>
            <div class="detail-hero-img-placeholder">
                <svg width="52" height="52" viewBox="0 0 52 52" fill="none" opacity="0.4" aria-hidden="true">
                    <rect x="6" y="8" width="40" height="36" rx="2" stroke="#0d7a4a" stroke-width="1.5"/>
                    <path d="M14 18h24M14 24h18M14 30h12" stroke="#0d7a4a" stroke-width="1.5"/>
                </svg>
                <p>foto artikel</p>
            </div>
            <?php endif; ?>
        </div>

        <!-- Meta -->
        <div class="detail-meta">
            <span class="detail-kat"><?= e($labelKategori[$artikel['kategori']] ?? $artikel['kategori']) ?></span>
            <?php if ($artikel['published_at']): ?>
            <span><?= e(formatTanggal($artikel['published_at'])) ?></span>
            <span>&middot;</span>
            <?php endif; ?>
            <span><?= readingTime($artikel['isi']) ?> menit baca</span>
            <span>&middot;</span>
            <span><?= (int)$artikel['views'] ?> x dibaca</span>
        </div>

        <h1 class="detail-title"><?= e($artikel['judul']) ?></h1>

        <?php if ($artikel['ringkasan']): ?>
        <p class="detail-ringkasan"><?= e($artikel['ringkasan']) ?></p>
        <?php endif; ?>

        <!-- Isi artikel — konten HTML dari admin WYSIWYG -->
        <div class="detail-content">
            <?= $artikel['isi'] /* isi sudah di-sanitasi saat disimpan admin */ ?>
        </div>

        <!-- Info penulis -->
        <div style="margin-top:40px;padding:20px;background:var(--cream);border-radius:4px;display:flex;gap:16px;align-items:center;">
            <div style="width:48px;height:48px;border-radius:50%;background:var(--green-deep);display:flex;align-items:center;justify-content:center;color:white;font-size:20px;flex-shrink:0;" aria-hidden="true">
                &#128100;
            </div>
            <div>
                <p style="font-size:12px;color:var(--text-light);margin-bottom:2px;">Ditulis oleh</p>
                <p style="font-weight:600;color:var(--text-dark);"><?= e($artikel['penulis']) ?></p>
                <p style="font-size:12px;color:var(--text-light);">Pondok Pesantren Ash-Shiddiq</p>
            </div>
        </div>

        <!-- Navigasi prev/next -->
        <?php if ($prevArtikel || $nextArtikel): ?>
        <nav class="artikel-nav" aria-label="Artikel lainnya">
            <?php if ($prevArtikel): ?>
            <a href="<?= e(BASE_URL . '/artikel/' . $prevArtikel['slug']) ?>">
                <p class="nav-label">&larr; Artikel Sebelumnya</p>
                <p class="nav-judul"><?= e(truncate($prevArtikel['judul'], 80)) ?></p>
            </a>
            <?php else: ?><div></div><?php endif; ?>

            <?php if ($nextArtikel): ?>
            <a href="<?= e(BASE_URL . '/artikel/' . $nextArtikel['slug']) ?>" class="next-link">
                <p class="nav-label">Artikel Berikutnya &rarr;</p>
                <p class="nav-judul"><?= e(truncate($nextArtikel['judul'], 80)) ?></p>
            </a>
            <?php else: ?><div></div><?php endif; ?>
        </nav>
        <?php endif; ?>

        <!-- Artikel terkait -->
        <?php if (!empty($artikelTerkait)): ?>
        <div class="terkait-section">
            <h2 class="terkait-title">Artikel Terkait</h2>
            <div class="terkait-grid">
                <?php foreach ($artikelTerkait as $t): ?>
                <a href="<?= e(BASE_URL . '/artikel/' . $t['slug']) ?>" class="terkait-card">
                    <div class="terkait-img">
                        <?php if ($t['foto']): ?>
                        <img src="<?= e(BASE_URL . '/uploads/artikel/' . basename($t['foto'])) ?>"
                             alt="<?= e($t['judul']) ?>" loading="lazy" onerror="this.hidden=true;this.parentElement.classList.add('image-missing')">
                        <?php else: ?>
                        <svg width="32" height="32" viewBox="0 0 52 52" fill="none" opacity="0.3" aria-hidden="true">
                            <rect x="6" y="8" width="40" height="36" rx="2" stroke="#0d7a4a" stroke-width="1.5"/>
                            <path d="M14 18h24M14 24h18M14 30h12" stroke="#0d7a4a" stroke-width="1.5"/>
                        </svg>
                        <?php endif; ?>
                    </div>
                    <div class="terkait-body">
                        <p class="terkait-judul"><?= e(truncate($t['judul'], 70)) ?></p>
                        <p class="terkait-meta"><?= $t['published_at'] ? e(formatTanggal($t['published_at'])) : '' ?></p>
                    </div>
                </a>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>

    </article>

    <!-- ── SIDEBAR DETAIL ─────────────────────────────────────── -->
    <aside class="detail-sidebar" aria-label="Sidebar artikel">

        <!-- Bagikan -->
        <div class="sidebar-widget reveal">
            <p class="sidebar-title">Bagikan Artikel</p>
            <a href="https://wa.me/?text=<?= rawurlencode($artikel['judul'] . ' - ' . $pageCanonical) ?>"
               class="share-btn share-wa" target="_blank" rel="noopener noreferrer" aria-label="Bagikan ke WhatsApp">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M17.47 14.47c-.28-.14-1.66-.82-1.92-.91-.26-.1-.45-.14-.64.14-.19.28-.74.91-.9 1.1-.17.19-.33.2-.61.07-.28-.14-1.18-.44-2.25-1.4-.83-.74-1.39-1.66-1.55-1.94-.17-.28-.02-.43.12-.57.13-.13.28-.34.42-.51.14-.17.19-.28.28-.47.1-.19.05-.36-.02-.5-.07-.14-.64-1.53-.87-2.1-.23-.55-.47-.48-.64-.49h-.55c-.19 0-.5.07-.76.36-.26.28-1 1-1 2.43s1.02 2.82 1.16 3.01c.14.19 2 3.05 4.85 4.28.68.29 1.21.47 1.62.6.68.21 1.3.18 1.79.11.55-.08 1.66-.68 1.9-1.33.23-.65.23-1.2.16-1.32-.07-.12-.26-.19-.54-.33z"/><path d="M12 2C6.48 2 2 6.48 2 12c0 1.85.5 3.58 1.37 5.07L2 22l5.09-1.34C8.48 21.54 10.2 22 12 22c5.52 0 10-4.48 10-10S17.52 2 12 2zm0 18c-1.66 0-3.22-.44-4.57-1.2l-.33-.19-3.02.8.81-2.95-.21-.35C3.48 14.96 3 13.54 3 12c0-4.96 4.04-9 9-9s9 4.04 9 9-4.04 9-9 9z"/></svg>
                WhatsApp
            </a>
            <a href="https://www.facebook.com/sharer/sharer.php?u=<?= rawurlencode($pageCanonical) ?>"
               class="share-btn share-fb" target="_blank" rel="noopener noreferrer" aria-label="Bagikan ke Facebook">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M18 2h-3a5 5 0 0 0-5 5v3H7v4h3v8h4v-8h3l1-4h-4V7a1 1 0 0 1 1-1h3z"/></svg>
                Facebook
            </a>
            <button class="share-btn share-cp" onclick="navigator.clipboard.writeText('<?= e($pageCanonical) ?>').then(()=>alert('Link disalin!'))" aria-label="Salin tautan">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><rect x="9" y="9" width="13" height="13" rx="2"/><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"/></svg>
                Salin Tautan
            </button>
        </div>

        <!-- Info artikel -->
        <div class="sidebar-widget reveal">
            <p class="sidebar-title">Info Artikel</p>
            <table style="width:100%;font-size:13px;border-collapse:collapse;">
                <tr style="border-bottom:1px solid var(--cream-dark);">
                    <td style="padding:10px 0;color:var(--text-light);">Kategori</td>
                    <td style="padding:10px 0;text-align:right;">
                        <a href="<?= e(BASE_URL . '/artikel?kategori=' . $artikel['kategori']) ?>"
                           style="color:var(--green-mid);text-decoration:none;">
                            <?= e($labelKategori[$artikel['kategori']] ?? $artikel['kategori']) ?>
                        </a>
                    </td>
                </tr>
                <?php if ($artikel['published_at']): ?>
                <tr style="border-bottom:1px solid var(--cream-dark);">
                    <td style="padding:10px 0;color:var(--text-light);">Tanggal</td>
                    <td style="padding:10px 0;text-align:right;"><?= e(formatTanggal($artikel['published_at'])) ?></td>
                </tr>
                <?php endif; ?>
                <tr style="border-bottom:1px solid var(--cream-dark);">
                    <td style="padding:10px 0;color:var(--text-light);">Waktu baca</td>
                    <td style="padding:10px 0;text-align:right;"><?= readingTime($artikel['isi']) ?> menit</td>
                </tr>
                <tr>
                    <td style="padding:10px 0;color:var(--text-light);">Dilihat</td>
                    <td style="padding:10px 0;text-align:right;"><?= (int)$artikel['views'] ?> kali</td>
                </tr>
            </table>
        </div>

        <!-- CTA PSB -->
        <div class="sidebar-widget reveal" style="background:var(--green-deep);text-align:center;padding:32px 24px;">
            <p style="font-family:'Amiri',serif;font-size:22px;color:var(--gold);margin-bottom:12px;">هَلُمَّ إِلَى الْقُرْآنِ</p>
            <p style="font-size:14px;color:rgba(255,255,255,0.7);line-height:1.7;margin-bottom:20px;">
                Daftarkan putra-putri Anda sekarang. PSB 2025/2026 telah dibuka!
            </p>
            <a href="<?= e(BASE_URL . '/psb') ?>" class="btn-primary"
               style="width:100%;display:block;text-align:center;">Daftar Sekarang</a>
        </div>

    </aside>
</div>
