<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($adminTitle ?? 'Dashboard') ?> — Admin | <?= e(APP_NAME) ?></title>
    <meta name="robots" content="noindex, nofollow">

    <?php $logoFile = getLogoFile(); if ($logoFile !== ''): ?>
    <link rel="icon" href="<?= BASE_URL ?>/assets/img/<?= $logoFile ?>">
    <?php endif; ?>

    <!-- Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&family=Amiri:ital,wght@0,400;0,700;1,400&display=swap" rel="stylesheet">

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
        $adminIcon = static function (string $name): string {
            $paths = [
                'grid'      => '<rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="7" rx="1"/><rect x="3" y="14" width="7" height="7" rx="1"/><rect x="14" y="14" width="7" height="7" rx="1"/>',
                'cap'       => '<path d="M22 10L12 5 2 10l10 5 10-5z"/><path d="M6 12v5c0 1.7 2.7 3 6 3s6-1.3 6-3v-5"/>',
                'users'     => '<path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/>',
                'check'     => '<path d="M9 11l3 3L22 4"/><path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"/>',
                'edit'      => '<path d="M12 20h9"/><path d="M16.5 3.5a2.1 2.1 0 0 1 3 3L7 19l-4 1 1-4Z"/>',
                'wallet'    => '<rect x="2" y="5" width="20" height="14" rx="2"/><path d="M2 10h20"/>',
                'idcard'    => '<rect x="3" y="4" width="18" height="16" rx="2"/><circle cx="9" cy="10" r="2"/><path d="M15 8h2"/><path d="M15 12h2"/><path d="M7 16h10"/>',
                'sliders'   => '<line x1="4" y1="21" x2="4" y2="14"/><line x1="4" y1="10" x2="4" y2="3"/><line x1="12" y1="21" x2="12" y2="12"/><line x1="12" y1="8" x2="12" y2="3"/><line x1="20" y1="21" x2="20" y2="16"/><line x1="20" y1="12" x2="20" y2="3"/><line x1="2" y1="14" x2="6" y2="14"/><line x1="10" y1="8" x2="14" y2="8"/><line x1="18" y1="16" x2="22" y2="16"/>',
                'clock'     => '<circle cx="12" cy="12" r="9"/><polyline points="12 7 12 12 15 14"/>',
                'tag'       => '<path d="M20.59 13.41l-7.17 7.17a2 2 0 0 1-2.83 0L2 12V2h10l8.59 8.59a2 2 0 0 1 0 2.82z"/><line x1="7" y1="7" x2="7.01" y2="7"/>',
                'file'      => '<path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/>',
                'ticket'    => '<path d="M2 9a3 3 0 0 1 0 6v2a2 2 0 0 0 2 2h16a2 2 0 0 0 2-2v-2a3 3 0 0 1 0-6V7a2 2 0 0 0-2-2H4a2 2 0 0 0-2 2Z"/><path d="M13 5v2"/><path d="M13 17v2"/><path d="M13 11v2"/>',
                'award'     => '<circle cx="12" cy="8" r="6"/><path d="M15.477 12.89 17 22l-5-3-5 3 1.523-9.11"/>',
                'layers'    => '<polygon points="12 2 2 7 12 12 22 7 12 2"/><polyline points="2 17 12 22 22 17"/><polyline points="2 12 12 17 22 12"/>',
                'image'     => '<rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="8.5" cy="8.5" r="1.5"/><polyline points="21 15 16 10 5 21"/>',
                'folder'    => '<path d="M22 19a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h5l2 3h9a2 2 0 0 1 2 2z"/>',
                'quote'     => '<path d="M3 21c3 0 7-1 7-8V5c0-1.25-.756-2.017-2-2H4c-1.25 0-2 .75-2 1.972V11c0 1.25.75 2 2 2 1 0 1 0 1 1v1c0 1-1 2-2 2s-1 .008-1 1.031V20c0 1 0 1 1 1z"/><path d="M15 21c3 0 7-1 7-8V5c0-1.25-.757-2.017-2-2h-4c-1.25 0-2 .75-2 1.972V11c0 1.25.75 2 2 2h.75c0 2.25.25 4-2.75 4v3c0 1 0 1 1 1z"/>',
                'user'      => '<path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/>',
                'settings'  => '<circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1-2.83 2.83l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-4 0v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1 0-4h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 2.83-2.83l.06.06a1.65 1.65 0 0 0 1.82.33H9a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 4 0v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 0 4h-.09a1.65 1.65 0 0 0-1.51 1z"/>',
            ];
            $p = $paths[$name] ?? $paths['grid'];
            return '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round">' . $p . '</svg>';
        };

        $adminDashboard = ['key' => 'admin', 'icon' => $adminIcon('grid'), 'label' => 'Dashboard'];
        $adminNavGroups = [
            ['label' => 'Penerimaan Santri', 'icon' => $adminIcon('cap'), 'items' => [
                'admin/psb'                => ['icon' => $adminIcon('users'),  'label' => 'Data Pendaftar'],
                'admin/verifikasi-berkas'  => ['icon' => $adminIcon('check'),  'label' => 'Verifikasi Berkas'],
                'admin/input-tes'          => ['icon' => $adminIcon('edit'),   'label' => 'Input Nilai Tes'],
                'admin/cicilan-verifikasi' => ['icon' => $adminIcon('wallet'), 'label' => 'Verifikasi Pembayaran'],
                'admin/daftar-ulang'       => ['icon' => $adminIcon('idcard'), 'label' => 'Daftar Ulang'],
            ]],
            ['label' => 'Pengaturan PSB', 'icon' => $adminIcon('sliders'), 'items' => [
                'admin/kelola-gelombang' => ['icon' => $adminIcon('clock'),  'label' => 'Gelombang & Jadwal'],
                'admin/jalur-potongan'   => ['icon' => $adminIcon('tag'),    'label' => 'Potongan Jalur'],
                'admin/kelola-template'  => ['icon' => $adminIcon('file'),   'label' => 'Template Dokumen'],
                'admin/voucher-alumni'   => ['icon' => $adminIcon('ticket'), 'label' => 'Voucher Alumni'],
                'admin/voucher-akashi'   => ['icon' => $adminIcon('award'),  'label' => 'Voucher Akashi'],
            ]],
            ['label' => 'Konten Website', 'icon' => $adminIcon('layers'), 'items' => [
                'admin/artikel'     => ['icon' => $adminIcon('edit'),   'label' => 'Artikel'],
                'admin/foto'        => ['icon' => $adminIcon('image'),  'label' => 'Galeri Foto'],
                'admin/dokumentasi' => ['icon' => $adminIcon('folder'), 'label' => 'Dokumentasi'],
                'admin/testimoni'   => ['icon' => $adminIcon('quote'),  'label' => 'Testimoni'],
                'admin/alumni'      => ['icon' => $adminIcon('cap'),    'label' => 'Data Alumni'],
                'admin/pengurus'    => ['icon' => $adminIcon('user'),   'label' => 'Pengurus'],
            ]],
            ['label' => 'Sistem', 'icon' => $adminIcon('settings'), 'items' => [
                'admin/pengaturan' => ['icon' => $adminIcon('sliders'), 'label' => 'Pengaturan'],
                'admin/users'      => ['icon' => $adminIcon('users'),   'label' => 'Pengguna'],
            ]],
        ];
        $currentPage = $adminPage ?? ($adminNavActive ?? '');
        ?>
        <a href="<?= e(BASE_URL . '/' . $adminDashboard['key']) ?>"
           class="sidebar-link<?= $currentPage === $adminDashboard['key'] ? ' active' : '' ?>">
            <span class="sidebar-link-icon" aria-hidden="true"><?= $adminDashboard['icon'] ?></span>
            <span><?= e($adminDashboard['label']) ?></span>
        </a>
        <?php foreach ($adminNavGroups as $gi => $group):
            $groupOpen = in_array($currentPage, array_keys($group['items']), true);
        ?>
        <div class="sidebar-group<?= $groupOpen ? ' open' : '' ?>" data-group="<?= $gi ?>">
            <button type="button" class="sidebar-group-toggle<?= $groupOpen ? ' active-parent' : '' ?>"
                    aria-expanded="<?= $groupOpen ? 'true' : 'false' ?>">
                <span class="sidebar-link-icon" aria-hidden="true"><?= $group['icon'] ?></span>
                <span><?= e($group['label']) ?></span>
                <span class="sidebar-chevron" aria-hidden="true">▾</span>
            </button>
            <div class="sidebar-submenu">
                <?php foreach ($group['items'] as $key => $item):
                    $isActive = $currentPage === $key;
                ?>
                <a href="<?= e(BASE_URL . '/' . $key) ?>"
                   class="sidebar-link sidebar-sublink<?= $isActive ? ' active' : '' ?>">
                    <span class="sidebar-link-icon" aria-hidden="true"><?= $item['icon'] ?></span>
                    <span><?= e($item['label']) ?></span>
                </a>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endforeach; ?>
    </nav>

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
            <span class="topbar-user-avatar" aria-hidden="true"><?= $adminIcon('user') ?></span>
            <span class="topbar-user-name"><?= e(getCurrentUser()['name'] ?? '') ?></span>
            <a href="<?= e(BASE_URL . '/') ?>" class="btn-sm btn-sm-secondary" target="_blank" rel="noopener" style="margin-left:8px;text-decoration:none;">Lihat Website</a>
            <a href="<?= e(BASE_URL . '/logout') ?>" class="btn-sm btn-sm-danger" style="margin-left:6px;text-decoration:none;">Logout</a>
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
