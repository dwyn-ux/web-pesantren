<?php
/**
 * Header global — <head> + navbar
 * Setiap halaman set variabel ini SEBELUM include file ini:
 *   $pageTitle       — judul tab browser
 *   $pageDescription — meta description (150-160 karakter)
 *   $pageKeywords    — meta keywords (opsional)
 *   $pageCanonical   — URL kanonik halaman
 *   $pageOgImage     — URL gambar Open Graph
 *   $bodyClass       — class tambahan pada <body> (opsional)
 *   $activePage      — nama halaman aktif untuk highlight nav
 */

// Nilai default jika halaman tidak menyetnya
$pageTitle       ??= APP_NAME . ' | Pesantren Tahfidz Al-Qur\'an Ciamis';
$pageDescription ??= 'Pondok Pesantren Ash-Shiddiq adalah lembaga pendidikan Islam berbasis Tahfidz Al-Qur\'an di Ciamis, Jawa Barat. Mencetak generasi Qur\'ani berakhlak mulia sejak 2020.';
$pageKeywords    ??= 'pondok pesantren, tahfidz quran, ciamis, jawa barat, pendaftaran pesantren, PSB, pesantren ash-shiddiq';
$pageCanonical   ??= BASE_URL;
$pageOgImage     ??= imgExists('og-default.jpg') ? BASE_URL . '/assets/img/og-default.jpg' : '';
$bodyClass       ??= '';
$activePage      ??= 'home';

$logoFile = getLogoFile();

// Daftar nav links
$navLinks = [
    'home'          => ['url' => BASE_URL . '/',                    'label' => 'Beranda'],
    'profil'        => ['url' => BASE_URL . '/profil',              'label' => 'Profil'],
    'sekapur-sirih' => ['url' => BASE_URL . '/sekapur-sirih',       'label' => 'Sekapur Sirih'],
    'artikel'       => ['url' => BASE_URL . '/artikel',             'label' => 'Artikel'],
    'galeri'        => ['url' => BASE_URL . '/#kehidupan',          'label' => 'Galeri'],
    'dokumentasi'   => ['url' => BASE_URL . '/dokumentasi',         'label' => 'Dokumentasi'],
    'alumni'        => ['url' => BASE_URL . '/alumni',              'label' => 'Alumni'],
    'psb'           => ['url' => BASE_URL . '/psb',                 'label' => 'PSB'],
];
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">

    <!-- Primary Meta Tags -->
    <title><?= e($pageTitle) ?></title>
    <meta name="description" content="<?= e($pageDescription) ?>">
    <?php if (!empty($pageKeywords)): ?>
    <meta name="keywords" content="<?= e($pageKeywords) ?>">
    <?php endif; ?>
    <meta name="author" content="<?= e(APP_NAME) ?>">
    <link rel="canonical" href="<?= e($pageCanonical) ?>">

    <!-- Open Graph / Facebook -->
    <meta property="og:type"        content="website">
    <meta property="og:url"         content="<?= e($pageCanonical) ?>">
    <meta property="og:title"       content="<?= e($pageTitle) ?>">
    <meta property="og:description" content="<?= e($pageDescription) ?>">
    <meta property="og:image"       content="<?= e($pageOgImage) ?>">
    <meta property="og:locale"      content="id_ID">
    <meta property="og:site_name"   content="<?= e(APP_NAME) ?>">

    <!-- Twitter Card -->
    <meta name="twitter:card"        content="summary_large_image">
    <meta name="twitter:title"       content="<?= e($pageTitle) ?>">
    <meta name="twitter:description" content="<?= e($pageDescription) ?>">
    <meta name="twitter:image"       content="<?= e($pageOgImage) ?>">

    <!-- Favicon -->
    <?php if (imgExists('favicon.svg')): ?>
    <link rel="icon" type="image/svg+xml" href="<?= BASE_URL ?>/assets/img/favicon.svg">
    <?php elseif ($logoFile !== ''): ?>
    <link rel="icon" href="<?= BASE_URL ?>/assets/img/<?= $logoFile ?>">
    <?php endif; ?>
    <?php if (imgExists('apple-touch-icon.png')): ?>
    <link rel="apple-touch-icon" href="<?= BASE_URL ?>/assets/img/apple-touch-icon.png">
    <?php endif; ?>

    <!-- Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&family=Amiri:ital,wght@0,400;0,700;1,400&display=swap" rel="stylesheet">

    <!-- Stylesheet -->
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/custom.css?v=<?= ASSET_VERSION ?>">

    <?php if (!empty($extraHead)) echo $extraHead; ?>
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/theme.css?v=<?= ASSET_VERSION ?>">
</head>
<body class="<?= e($bodyClass) ?>">

<!-- ══ NAVBAR ════════════════════════════════════════════════ -->
<nav id="mainNav" aria-label="Navigasi utama">
    <!-- Logo -->
    <a href="<?= BASE_URL ?>/" class="nav-logo" aria-label="<?= e(APP_NAME) ?> — Beranda">
        <?php if ($logoFile !== ''): ?>
        <img class="nav-logo-image" src="<?= BASE_URL ?>/assets/img/<?= $logoFile ?>" alt="Logo <?= e(APP_NAME) ?>">
        <?php else: ?>
        <div class="nav-logo-icon" aria-hidden="true">ص</div>
        <?php endif; ?>
        <div class="nav-logo-text">
            <span class="nav-logo-main">Ash-Shiddiq</span>
            <span class="nav-logo-sub">Pondok Pesantren</span>
        </div>
    </a>

    <!-- Desktop Nav -->
    <ul class="nav-links" role="list">
        <?php foreach ($navLinks as $key => $link): ?>
        <li>
            <a href="<?= e($link['url']) ?>"
               <?= $activePage === $key ? 'class="active" aria-current="page"' : '' ?>>
                <?= e($link['label']) ?>
            </a>
        </li>
        <?php endforeach; ?>
        <li>
            <a href="<?= BASE_URL ?>/psb" class="nav-cta">Daftar Sekarang</a>
        </li>
        <li><a href="<?= BASE_URL ?>/login-santri" class="nav-login">Login</a></li>
        <?php if (isLoggedIn()): ?>
        <li>
            <a href="<?= BASE_URL ?>/admin" style="font-size:11px;opacity:0.55;letter-spacing:1px;" title="Panel Admin">⚙ Admin</a>
        </li>
        <?php endif; ?>
    </ul>

    <!-- Hamburger (mobile) -->
    <button class="nav-hamburger" id="navHamburger" aria-label="Buka menu navigasi" aria-expanded="false" aria-controls="mobileNav">
        <span></span><span></span><span></span>
    </button>
</nav>

<!-- ══ MOBILE NAV ════════════════════════════════════════════ -->
<div class="mobile-nav" id="mobileNav" role="dialog" aria-label="Menu navigasi mobile" aria-hidden="true">
    <button class="mobile-nav-close" id="mobileNavClose" aria-label="Tutup menu">✕</button>

    <?php foreach ($navLinks as $key => $link): ?>
    <a href="<?= e($link['url']) ?>" <?= $activePage === $key ? 'class="active"' : '' ?>>
        <?= e($link['label']) ?>
    </a>
    <?php endforeach; ?>

    <a href="<?= BASE_URL ?>/psb" class="btn-primary" style="margin-top:8px;text-align:center;">
        Daftar Sekarang
    </a>
    <a href="<?= BASE_URL ?>/login-santri" class="btn-outline" style="text-align:center;">Login Santri</a>

    <?php if (isLoggedIn()): ?>
    <a href="<?= BASE_URL ?>/admin" style="font-size:12px;opacity:0.6;margin-top:8px;">⚙ Panel Admin</a>
    <?php endif; ?>
</div>

<!-- ══ FLASH MESSAGE ════════════════════════════════════════ -->
<?php
$flash = getFlash();
if (!empty($flash)):
    $typeMap = ['success' => 'flash-success', 'error' => 'flash-error', 'info' => 'flash-info', 'warning' => 'flash-warning'];
    foreach ($flash as $type => $msg):
        $cls = $typeMap[$type] ?? 'flash-info';
?>
<div class="flash-message <?= $cls ?>" role="alert">
    <?= e($msg) ?>
    <button class="flash-close" onclick="this.parentElement.remove()" aria-label="Tutup">&times;</button>
</div>
<?php
    endforeach;
endif;
?>
