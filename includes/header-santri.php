<?php
/**
 * Header khusus santri — <head> + navbar kebutuhan santri.
 * Dipakai halaman: portal-santri, profil-santri, berkas-santri,
 * dokumen-santri, surat-kesanggupan, download-template.
 * Halaman set variabel yang sama seperti header.php global.
 */

$pageTitle       ??= 'Portal Santri | ' . APP_NAME;
$pageDescription ??= 'Portal santri Pondok Pesantren Ash-Shiddiq: lengkapi data, upload berkas, dan cetak dokumen.';
$pageKeywords    ??= '';
$pageCanonical   ??= BASE_URL . '/portal-santri';
$pageOgImage     ??= '';
$bodyClass       ??= '';
$activePage      ??= 'portal-santri';

// Nav kebutuhan santri — kunci = nilai $activePage halaman terkait.
// Dinamis per status: wizard hanya saat 'pending', menu lulus hanya
// saat 'diterima'/'daftar-ulang'. Status verifikasi/tes/ditolak:
// hanya Portal + Profil (tidak bisa ubah apa pun).
$santriStatusNav = null;
if (isCalonSantri()) {
    $santriStatusNav = getCurrentPendaftaran()['status'] ?? null;
}
$santriNavLinks = [
    'portal-santri'   => ['url' => BASE_URL . '/portal-santri',   'label' => 'Portal'],
    'profil-santri'   => ['url' => BASE_URL . '/profil-santri',   'label' => 'Profil'],
];
if ($santriStatusNav === 'pending') {
    $santriNavLinks['berkas-santri'] = ['url' => BASE_URL . '/upload-berkas', 'label' => 'Upload Berkas'];
} elseif (in_array($santriStatusNav, ['diterima', 'daftar-ulang'], true)) {
    $santriNavLinks['dokumen-santri'] = ['url' => BASE_URL . '/portal-santri?step=surat-ttd', 'label' => 'Surat & TTD'];
    $santriNavLinks['berkas-santri']  = ['url' => BASE_URL . '/upload-berkas', 'label' => 'Upload Berkas'];
    $santriNavLinks['pembayaran']     = ['url' => BASE_URL . '/portal-santri?step=pembayaran', 'label' => 'Pembayaran'];
}

$logoFile = getLogoFile();
$santriNavName = $_SESSION['user_name'] ?? 'Santri';
$santriInitial = function_exists('mb_substr')
    ? mb_strtoupper(mb_substr($santriNavName, 0, 1))
    : strtoupper(substr($santriNavName, 0, 1));
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="robots" content="noindex, nofollow">

    <title><?= e($pageTitle) ?></title>
    <meta name="description" content="<?= e($pageDescription) ?>">
    <?php if (!empty($pageKeywords)): ?>
    <meta name="keywords" content="<?= e($pageKeywords) ?>">
    <?php endif; ?>
    <meta name="author" content="<?= e(APP_NAME) ?>">
    <link rel="canonical" href="<?= e($pageCanonical) ?>">

    <?php if (imgExists('favicon.svg')): ?>
    <link rel="icon" type="image/svg+xml" href="<?= BASE_URL ?>/assets/img/favicon.svg">
    <?php elseif ($logoFile !== ''): ?>
    <link rel="icon" href="<?= BASE_URL ?>/assets/img/<?= $logoFile ?>">
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

<!-- ══ NAVBAR SANTRI ═══════════════════════════════════════════ -->
<nav id="mainNav" aria-label="Navigasi santri">
    <a href="<?= BASE_URL ?>/portal-santri" class="nav-logo" aria-label="Portal Santri — Beranda">
        <?php if ($logoFile !== ''): ?>
        <img class="nav-logo-image" src="<?= BASE_URL ?>/assets/img/<?= $logoFile ?>" alt="Logo <?= e(APP_NAME) ?>">
        <?php else: ?>
        <div class="nav-logo-icon" aria-hidden="true">ص</div>
        <?php endif; ?>
        <div class="nav-logo-text">
            <span class="nav-logo-main">Portal Santri</span>
            <span class="nav-logo-sub">Ash-Shiddiq</span>
        </div>
    </a>

    <!-- Desktop Nav -->
    <ul class="nav-links" role="list">
        <?php foreach ($santriNavLinks as $key => $link): ?>
        <li>
            <a href="<?= e($link['url']) ?>"
               <?= $activePage === $key ? 'class="active" aria-current="page"' : '' ?>>
                <?= e($link['label']) ?>
            </a>
        </li>
        <?php endforeach; ?>
        <li class="nav-profile">
            <button class="nav-profile-btn" id="santriMenuBtn" aria-haspopup="true" aria-expanded="false" aria-label="Menu santri">
                <span class="nav-profile-avatar"><?= e($santriInitial) ?></span>
            </button>
            <div class="nav-profile-menu" id="santriMenu">
                <a href="<?= BASE_URL ?>/profil-santri">Profil Saya</a>
                <a href="<?= BASE_URL ?>/login-santri?logout=1">Logout</a>
            </div>
        </li>
    </ul>

    <!-- Hamburger (mobile) -->
    <button class="nav-hamburger" id="navHamburger" aria-label="Buka menu navigasi" aria-expanded="false" aria-controls="mobileNav">
        <span></span><span></span><span></span>
    </button>
</nav>

<!-- ══ MOBILE NAV SANTRI ══════════════════════════════════════ -->
<div class="mobile-nav" id="mobileNav" role="dialog" aria-label="Menu navigasi santri" aria-hidden="true">
    <button class="mobile-nav-close" id="mobileNavClose" aria-label="Tutup menu">✕</button>

    <?php foreach ($santriNavLinks as $key => $link): ?>
    <a href="<?= e($link['url']) ?>" <?= $activePage === $key ? 'class="active"' : '' ?>>
        <?= e($link['label']) ?>
    </a>
    <?php endforeach; ?>

    <a href="<?= BASE_URL ?>/profil-santri" class="btn-outline" style="text-align:center;">Profil Saya</a>
    <a href="<?= BASE_URL ?>/login-santri?logout=1" class="btn-outline" style="text-align:center;">Logout</a>

</div>

<!-- ══ FLASH MESSAGE → dirender ulang oleh notif.js sebagai toast ══ -->
<?php
$flash = getFlash();
if (!empty($flash)):
    $flashData = [];
    foreach ($flash as $type => $msg) {
        $tipe = in_array($type, ['success', 'error', 'info', 'warning'], true) ? $type : 'info';
        $flashData[] = ['type' => $tipe, 'msg' => $msg];
    }
?>
<script id="flashData" type="application/json"><?= json_encode($flashData, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?></script>
<?php endif; ?>

<!-- ══ DROPDOWN MENU SANTRI ══════════════════════════════════ -->
<script>
(function () {
    var btn = document.getElementById('santriMenuBtn');
    var menu = document.getElementById('santriMenu');
    if (!btn || !menu) return;
    btn.addEventListener('click', function (e) {
        e.stopPropagation();
        var open = menu.classList.toggle('open');
        btn.setAttribute('aria-expanded', open ? 'true' : 'false');
    });
    document.addEventListener('click', function (e) {
        if (!menu.contains(e.target)) {
            menu.classList.remove('open');
            btn.setAttribute('aria-expanded', 'false');
        }
    });
}());
</script>
