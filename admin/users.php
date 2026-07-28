<?php
require_once __DIR__ . '/bootstrap.php';
requireAdmin();

$pdo = getDB();
$stmt = $pdo->query("SELECT id, name, email, role, is_active, created_at FROM users ORDER BY created_at DESC");
$users = $stmt->fetchAll();

$adminTitle = 'Kelola Pengguna';
$adminPage  = 'admin/users';

require_once __DIR__ . '/includes/header.php';
?>

<div class="admin-table-wrap">
    <div class="table-head">
        <h2>Pengguna (<?= count($users) ?>)</h2>
    </div>
    <table class="admin-table">
        <thead>
            <tr>
                <th>Nama</th>
                <th>Email</th>
                <th>Role</th>
                <th>Status</th>
                <th>Bergabung</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($users as $u): ?>
            <tr>
                <td><strong><?= e($u['name']) ?></strong></td>
                <td><?= e($u['email']) ?></td>
                <td>
                    <span class="badge <?= $u['role'] === 'admin' ? 'badge-published' : 'badge-draft' ?>">
                        <?= e(ucfirst($u['role'])) ?>
                    </span>
                </td>
                <td>
                    <span class="badge <?= $u['is_active'] ? 'badge-diterima' : 'badge-ditolak' ?>">
                        <?= $u['is_active'] ? 'Aktif' : 'Nonaktif' ?>
                    </span>
                </td>
                <td style="font-size:12px;"><?= e(formatTanggal($u['created_at'])) ?></td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
