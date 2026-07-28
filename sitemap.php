<?php
/**
 * Sitemap XML dinamis — diakses via /sitemap.xml (di-rewrite oleh .htaccess)
 */

define('ROOT_PATH', __DIR__);
require_once ROOT_PATH . '/config/constants.php';
require_once ROOT_PATH . '/config/database.php';
require_once ROOT_PATH . '/includes/functions.php';

header('Content-Type: application/xml; charset=UTF-8');
header('X-Robots-Tag: noindex');

$today = date('Y-m-d');

// Artikel yang sudah dipublish
$artikels = [];
try {
    $pdo      = getDB();
    $stmt     = $pdo->query("SELECT slug, updated_at FROM artikel WHERE status = 'published' ORDER BY published_at DESC");
    $artikels = $stmt->fetchAll();
} catch (PDOException $e) {
    // Jika database belum siap, sitemap tetap tampil dengan halaman statis
    error_log('Sitemap DB error: ' . $e->getMessage());
}

echo '<?xml version="1.0" encoding="UTF-8"?>';
?>

<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9"
        xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
        xsi:schemaLocation="http://www.sitemaps.org/schemas/sitemap/0.9
            http://www.sitemaps.org/schemas/sitemap/0.9/sitemap.xsd">

    <!-- Halaman Utama -->
    <url>
        <loc><?= BASE_URL ?>/</loc>
        <changefreq>weekly</changefreq>
        <priority>1.0</priority>
        <lastmod><?= $today ?></lastmod>
    </url>

    <!-- Halaman Statis -->
    <?php
    $staticPages = [
        ['url' => '/profil',        'freq' => 'monthly', 'priority' => '0.8'],
        ['url' => '/sekapur-sirih', 'freq' => 'monthly', 'priority' => '0.7'],
        ['url' => '/artikel',       'freq' => 'daily',   'priority' => '0.8'],
        ['url' => '/psb',           'freq' => 'weekly',  'priority' => '0.9'],
    ];
    foreach ($staticPages as $p):
    ?>
    <url>
        <loc><?= BASE_URL . e($p['url']) ?></loc>
        <changefreq><?= $p['freq'] ?></changefreq>
        <priority><?= $p['priority'] ?></priority>
        <lastmod><?= $today ?></lastmod>
    </url>
    <?php endforeach; ?>

    <!-- Artikel -->
    <?php foreach ($artikels as $artikel): ?>
    <url>
        <loc><?= BASE_URL ?>/artikel-detail?slug=<?= e($artikel['slug']) ?></loc>
        <changefreq>monthly</changefreq>
        <priority>0.6</priority>
        <lastmod><?= date('Y-m-d', strtotime($artikel['updated_at'])) ?></lastmod>
    </url>
    <?php endforeach; ?>

</urlset>
