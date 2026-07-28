<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($adminTitle ?? 'Dashboard') ?> — Admin | <?= e(APP_NAME) ?></title>
    <meta name="robots" content="noindex, nofollow">

    <!-- Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@400;600;700&family=Plus+Jakarta+Sans:wght@300;400;500;600&display=swap" rel="stylesheet">

    <!-- Stylesheet -->
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/custom.css?v=<?= ASSET_VERSION ?>">
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/admin.css?v=<?= ASSET_VERSION ?>">
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/theme.css?v=<?= ASSET_VERSION ?>">

    <?php if (!empty($adminExtraHead)) echo $adminExtraHead; ?>
</head>
<body class="admin-body">

<!-- ══ SIDEBAR ══════════════════��════════════════════════════ -->
<aside class="admin-sidebar" id="adminSidebar">
    <div class="sidebar-brand">
        <div class="sidebar-brand-icon" aria-hidden="true">ص</div>
        <div>
            <span class="sidebar-brand-name">Ash-Shiddiq</span>
            <span class="sidebar-brand-sub">Panel Admin</span>
        </div>
    </div>

    <nav class="sidebar-nav" aria-label="Menu admin">
        <?php
        $adminNav = [
            'admin'           => ['icon' => '⊞', 'label' => 'Dashboard'],
            'admin/artikel'   => ['icon' => '✎', 'label' => 'Artikel'],
            'admin/foto'      => ['icon' => '▧', 'label' => 'Galeri Foto'],
            'admin/dokumentasi' => ['icon' => '▣', 'label' => 'Dokumentasi'],
            'admin/alumni'    => ['icon' => '♙', 'label' => 'Data Alumni'],
            'admin/pengaturan' => ['icon' => '⚙', 'label' => 'Pengaturan'],
            'admin/psb'       => ['icon' => '◎', 'label' => 'Data PSB'],
            'admin/users'     => ['icon' => '⊙', 'label' => 'Pengguna'],
        ];
        foreach ($adminNav as $key => $item):
            $isActive = ($adminPage ?? '') === $key;
        ?>
        <a href="<?= e(BASE_URL . '/' . $key) ?>"
           class="sidebar-link<?= $isActive ? ' active' : '' ?>">
            <span class="sidebar-link-icon" aria-hidden="true"><?= $item['icon'] ?></span>
            <span><?= e($item['label']) ?></span>
        </a>
        <?php endforeach; ?>
    </nav>

    <div class="sidebar-footer">
        <a href="<?= e(BASE_URL . '/') ?>" class="sidebar-link" target="_blank">
            <span class="sidebar-link-icon" aria-hidden="true">↗</span>
            <span>Lihat Website</span>
        </a>
        <a href="<?= e(BASE_URL . '/logout') ?>" class="sidebar-link sidebar-logout">
            <span class="sidebar-link-icon" aria-hidden="true">⏻</span>
            <span>Logout</span>
        </a>
    </div>
</aside>
<button type="button" class="admin-sidebar-backdrop" id="adminSidebarBackdrop" aria-label="Tutup menu admin"></button>

<!-- ══ MAIN AREA ═════════════════════════════════════════════ -->
<div class="admin-main">

    <!-- Topbar -->
    <header class="admin-topbar">
        <button class="sidebar-toggle" id="sidebarToggle" aria-label="Buka menu admin" aria-expanded="false">
            ☰
        </button>
        <h1 class="topbar-title"><?= e($adminTitle ?? 'Dashboard') ?></h1>
        <div class="topbar-user">
            <span class="topbar-user-avatar" aria-hidden="true">👤</span>
            <span class="topbar-user-name"><?= e(getCurrentUser()['name'] ?? '') ?></span>
        </div>
    </header>

    <!-- Flash messages -->
    <?php
    $flash = getFlash();
    foreach ($flash as $type => $msg):
        $typeMap = ['success' => 'flash-success', 'error' => 'flash-error', 'info' => 'flash-info', 'warning' => 'flash-warning'];
        $cls = $typeMap[$type] ?? 'flash-info';
    ?>
    <div class="flash-message <?= $cls ?>" role="alert" style="margin:16px 24px 0;">
        <?= e($msg) ?>
        <button class="flash-close" onclick="this.parentElement.remove()" aria-label="Tutup">&times;</button>
    </div>
    <?php endforeach; ?>

    <!-- Page content start -->
    <div class="admin-content">
