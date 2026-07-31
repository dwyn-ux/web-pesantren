<?php
require_once __DIR__ . '/bootstrap.php';
requireAdmin();

$pdo = getDB();

// ── Proses update status ──────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    validateCsrf();

    if ($_POST['action'] === 'update_status') {
        $id     = sanitizeInt($_POST['id'] ?? 0);
        $status = sanitizeString($_POST['status'] ?? '');
        $catatan= sanitizeString($_POST['catatan'] ?? '');

        $validStatus = ['pending', 'diterima', 'ditolak', 'daftar-ulang'];
        if ($id > 0 && in_array($status, $validStatus, true)) {
            $pdo->prepare(
                "UPDATE pendaftaran SET status = ?, catatan_admin = ? WHERE id = ?"
            )->execute([$status, $catatan ?: null, $id]);
            setFlash('success', 'Status pendaftaran berhasil diperbarui.');
        }
    } elseif ($_POST['action'] === 'issue_portal') {
        $id=sanitizeInt($_POST['id']??0);$nomorInduk=strtoupper(sanitizeString($_POST['nomor_induk']??''));$password=$_POST['portal_password']??'';
        if($id>0 && preg_match('/^[A-Z0-9.\/-]{4,40}$/',$nomorInduk) && ($password===''||strlen($password)>=8)){
            try{if($password!=='')$pdo->prepare("UPDATE pendaftaran SET nomor_induk=?,portal_password=?,status='diterima' WHERE id=?")->execute([$nomorInduk,password_hash($password,PASSWORD_BCRYPT),$id]);else $pdo->prepare("UPDATE pendaftaran SET nomor_induk=?,status='diterima' WHERE id=?")->execute([$nomorInduk,$id]);setFlash('success','Nomor induk dan akses portal diperbarui.');}catch(PDOException $e){setFlash('error','Nomor induk sudah digunakan atau data tidak valid.');}
        } else setFlash('error','Nomor induk tidak valid atau password baru kurang dari 8 karakter.');
    } elseif ($_POST['action'] === 'update_payment') {
        $itemId=sanitizeInt($_POST['item_id']??0);$payment=sanitizeString($_POST['status_pembayaran']??'');
        if($itemId>0&&in_array($payment,['belum','menunggu','lunas','ditolak'],true)){
            $pdo->prepare('UPDATE pembiayaan SET status=? WHERE id=?')->execute([$payment,$itemId]);
            $pdo->prepare("UPDATE berkas_santri SET status=CASE WHEN ?='lunas' THEN 'verified' WHEN ?='ditolak' THEN 'rejected' ELSE status END WHERE pembiayaan_id=? AND jenis='bukti-bayar'")->execute([$payment,$payment,$itemId]);
            setFlash('success','Status pembiayaan diperbarui.');
        }
    }
    redirect('/admin/psb');
}

// ── Filter & pagination ───────────────────────────────────────
$halaman    = max(1, sanitizeInt($_GET['halaman'] ?? 1));
$perHalaman = 20;
$offset     = paginationOffset($halaman, $perHalaman);
$filterStat = sanitizeString($_GET['status'] ?? '');
$cari       = sanitizeString($_GET['cari'] ?? '');

$validStatus = ['pending', 'diterima', 'ditolak', 'daftar-ulang'];
if ($filterStat && !in_array($filterStat, $validStatus, true)) {
    $filterStat = '';
}

$where  = "WHERE 1=1";
$params = [];
if ($filterStat) {
    $where    .= " AND status = ?";
    $params[]  = $filterStat;
}
if ($cari) {
    $where    .= " AND (nama_lengkap LIKE ? OR nomor_daftar LIKE ? OR whatsapp LIKE ?)";
    $kw        = '%' . $cari . '%';
    $params[] = $kw; $params[] = $kw; $params[] = $kw;
}

$stmtCount = $pdo->prepare("SELECT COUNT(*) FROM pendaftaran $where");
$stmtCount->execute($params);
$total = (int) $stmtCount->fetchColumn();
$totalHalaman = (int) ceil($total / $perHalaman);

$stmtData = $pdo->prepare(
    "SELECT * FROM pendaftaran $where
     ORDER BY created_at DESC LIMIT $perHalaman OFFSET $offset"
);
$stmtData->execute($params);
$pendaftaran = $stmtData->fetchAll();

$adminTitle = 'Data PSB';
$adminPage  = 'admin/psb';

$labelJenjang = [
    'mts'              => 'SMP',
    'ma'               => 'SMA',
    'tahfidz-intensif' => 'Tahfidz',
];
$labelStatus = [
    'pending'      => 'Menunggu',
    'diterima'     => 'Diterima',
    'ditolak'      => 'Ditolak',
    'daftar-ulang' => 'Daftar Ulang',
];

require_once __DIR__ . '/includes/header.php';
?>

<!-- Filter & search -->
<div class="admin-table-wrap" style="margin-bottom:20px;">
    <div class="table-head">
        <h2>Data Pendaftar (<?= $total ?>)</h2>
        <form method="GET" action="<?= e(BASE_URL . '/admin/psb') ?>" class="filter-toolbar">
            <input type="search" name="cari" placeholder="Cari nama / nomor..."
                   value="<?= e($cari) ?>" maxlength="100">
            <select name="status" onchange="this.form.submit()">
                <option value="">Semua Status</option>
                <?php foreach ($labelStatus as $val => $lbl): ?>
                <option value="<?= e($val) ?>" <?= $filterStat === $val ? 'selected' : '' ?>>
                    <?= e($lbl) ?>
                </option>
                <?php endforeach; ?>
            </select>
            <button type="submit" class="btn-sm btn-sm-primary">Cari</button>
            <?php if ($cari || $filterStat): ?>
            <a href="<?= e(BASE_URL . '/admin/psb') ?>" class="btn-sm btn-sm-secondary">Reset</a>
            <?php endif; ?>
        </form>
    </div>

    <table class="admin-table">
        <thead>
            <tr>
                <th>Nomor</th>
                <th>Nama Calon Santri</th>
                <th>Jenjang</th>
                <th>WhatsApp</th>
                <th>Tanggal Daftar</th>
                <th>Status</th>
                <th>Aksi</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($pendaftaran)): ?>
            <tr><td colspan="7" class="table-empty">Tidak ada data pendaftaran.</td></tr>
            <?php else: ?>
            <?php foreach ($pendaftaran as $p): ?>
            <tr>
                <td><strong style="font-size:12px;"><?= e($p['nomor_daftar']) ?></strong></td>
                <td>
                    <strong><?= e($p['nama_lengkap']) ?></strong><br>
                    <span style="font-size:11px;color:var(--text-light);">
                        <?= e($p['tempat_lahir']) ?>, <?= $p['tanggal_lahir'] ? e(formatTanggal($p['tanggal_lahir'])) : '-' ?>
                    </span>
                </td>
                <td><?= e($labelJenjang[$p['jenjang']] ?? $p['jenjang']) ?></td>
                <td>
                    <a href="https://wa.me/<?= e(preg_replace('/^0/', '62', preg_replace('/\D/', '', $p['whatsapp']))) ?>"
                       target="_blank" rel="noopener noreferrer"
                       style="color:var(--green-mid);text-decoration:none;font-size:13px;">
                        <?= e($p['whatsapp']) ?>
                    </a>
                </td>
                <td style="white-space:nowrap;"><?= e(formatTanggal($p['created_at'])) ?></td>
                <td>
                    <span class="badge badge-<?= e($p['status']) ?>">
                        <?= e($labelStatus[$p['status']] ?? $p['status']) ?>
                    </span>
                </td>
                <td>
                    <button type="button" class="btn-sm btn-sm-warning"
                            onclick="openPsbModal(<?= $p['id'] ?>, '<?= e(addslashes($p['nama_lengkap'])) ?>', '<?= e($p['status']) ?>', '<?= e(addslashes($p['catatan_admin'] ?? '')) ?>')">
                        Update
                    </button>
                    <button type="button" class="btn-sm btn-sm-secondary"
                            onclick="openPsbDetail(<?= $p['id'] ?>)" style="margin-left:4px;">
                        Detail
                    </button>
                </td>
            </tr>
            <!-- Detail row (hidden) -->
            <tr id="detail-<?= $p['id'] ?>" style="display:none;">
                <td colspan="7" style="background:#f8faf9;padding:20px;">
                    <div style="display:grid;grid-template-columns:repeat(3,1fr);gap:16px;font-size:13px;">
                        <div>
                            <strong>Orang Tua</strong><br>
                            Ayah: <?= e($p['nama_ayah']) ?><br>
                            Ibu: <?= e($p['nama_ibu']) ?><br>
                            HP: <?= e($p['hp_ortu']) ?><br>
                            Pekerjaan: <?= e($p['pekerjaan_ortu'] ?? '-') ?>
                        </div>
                        <div>
                            <strong>Akademik</strong><br>
                            Asal: <?= e($p['asal_sekolah']) ?><br>
                            Lulus: <?= e($p['tahun_lulus']) ?><br>
                            Kemampuan Qur'an: <?= e(str_replace('-', ' ', $p['kemampuan_quran'])) ?><br>
                            Hafalan: <?= e($p['jumlah_hafalan'] ?? '-') ?>
                            <?php if (!empty($p['tinggi_badan']) || !empty($p['berat_badan'])): ?><br>
                            Seragam: <?= !empty($p['tinggi_badan']) ? e((float)$p['tinggi_badan']) . ' cm' : '-' ?> / <?= !empty($p['berat_badan']) ? e((float)$p['berat_badan']) . ' kg' : '-' ?>
                            <?php endif; ?>
                            <br><br><strong>Pembiayaan:</strong><br>
                            <?php syncPembiayaan($pdo,(int)$p['id'],$p['jenis_kelamin']);
                            $bi=$pdo->prepare('SELECT * FROM pembiayaan WHERE pendaftaran_id=? ORDER BY urutan,id');$bi->execute([$p['id']]);$biayaP=$bi->fetchAll(); ?>
                            <table class="admin-table" style="font-size:12px;margin-top:6px;">
                                <tr><th>Item</th><th>Nominal</th><th>Status</th><th></th></tr>
                                <?php foreach($biayaP as $biaya): ?>
                                <tr>
                                    <td><?=e(pembiayaanLabel($biaya['jenis']))?><?=$biaya['nama']?'<br><em>'.e($biaya['nama']).'</em>':''?><?=$biaya['dipilih']?'<br><span class="badge badge-diterima">dipilih</span>':''?></td>
                                    <td>
                                        <?php if($biaya['gratis']):?>GRATIS
                                        <?php else:?><?=($biaya['harga_diskon']!==null && (float)$biaya['harga_diskon']<(float)$biaya['harga_asli'])?'<s>'.formatRupiah((float)$biaya['harga_asli']).'</s> ':''?><?=formatRupiah((float)$biaya['nominal'])?>
                                        <?php endif;?>
                                    </td>
                                    <td>
                                        <span class="badge badge-<?=$biaya['status']==='lunas'||$biaya['status']==='gratis'?'diterima':($biaya['status']==='menunggu'?'pending':'ditolak')?>"><?=e(pembiayaanStatusLabel($biaya['status']))?></span>
                                        <?=$biaya['kesanggupan']?'<br><small>disanggupi</small>':''?>
                                    </td>
                                    <td>
                                        <?php if($biaya['gratis']):?>
                                        <em style="font-size:11px;">otomatis</em>
                                        <?php else:?>
                                        <form method="post" style="display:flex;gap:4px;margin:0">
                                            <input type="hidden" name="csrf_token" value="<?=generateCsrfToken()?>">
                                            <input type="hidden" name="action" value="update_payment">
                                            <input type="hidden" name="item_id" value="<?=$biaya['id']?>">
                                            <select name="status_pembayaran" style="padding:4px;font-size:12px;">
                                                <option value="belum" <?=($biaya['status']??'belum')==='belum'?'selected':''?>>Belum</option>
                                                <option value="menunggu" <?=($biaya['status']??'')==='menunggu'?'selected':''?>>Menunggu</option>
                                                <option value="lunas" <?=($biaya['status']??'')==='lunas'?'selected':''?>>Lunas</option>
                                                <option value="ditolak" <?=($biaya['status']??'')==='ditolak'?'selected':''?>>Ditolak</option>
                                            </select>
                                            <button class="btn-sm btn-sm-primary">Simpan</button>
                                        </form>
                                        <?php endif;?>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </table>
                            <?php if(!empty($p['kesanggupan_at'])): ?>
                            <br><strong>Kesanggupan:</strong> tanda tangan <a href="<?=BASE_URL?>/api/signature.php?id=<?=$p['id']?>" target="_blank" rel="noopener">lihat</a> · <?=e($p['kesanggupan_at'])?>
                            <?php endif; ?>
                            <?php $bf=$pdo->prepare('SELECT id,jenis,nama_asli,status FROM berkas_santri WHERE pendaftaran_id=? ORDER BY created_at DESC');$bf->execute([$p['id']]);$berkasP=$bf->fetchAll(); ?>
                            <br><br><strong>Berkas Masuk (<?=count($berkasP)?>):</strong><br>
                            <?php foreach($berkasP as $file): ?><a target="_blank" href="<?=BASE_URL?>/admin/berkas-lihat?id=<?=$file['id']?>"><?=e(ucwords(str_replace('-',' ',$file['jenis'])))?> — <?=e($file['nama_asli'])?></a> (<?=e($file['status'])?>)<br><?php endforeach; ?>
                        </div>
                        <div>
                            <strong>Alamat</strong><br>
                            <?= e($p['alamat']) ?><br>
                            <?php if ($p['motivasi']): ?>
                            <br><strong>Motivasi:</strong><br>
                            <em style="color:var(--text-light);"><?= e($p['motivasi']) ?></em>
                            <?php endif; ?>
                            <?php if ($p['catatan_admin']): ?>
                            <br><strong>Catatan Admin:</strong><br>
                            <?= e($p['catatan_admin']) ?>
                            <?php endif; ?>
                            <br><br><strong>Portal Santri</strong><br>
                            <?php if(!empty($p['nomor_induk'])):?><span class="badge badge-diterima">Aktif: <?=e($p['nomor_induk'])?></span><?php endif;?>
                            <form method="post" style="margin-top:8px;display:flex;gap:6px;flex-wrap:wrap"><input type="hidden" name="csrf_token" value="<?=generateCsrfToken()?>"><input type="hidden" name="action" value="issue_portal"><input type="hidden" name="id" value="<?=$p['id']?>"><input name="nomor_induk" placeholder="Nomor induk" value="<?=e($p['nomor_induk']??'')?>" required><input type="password" name="portal_password" placeholder="Reset password (opsional)"><button class="btn-sm btn-sm-primary">Simpan Nomor Induk</button></form>
                        </div>
                    </div>
                </td>
            </tr>
            <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>

    <!-- Pagination -->
    <?php if ($totalHalaman > 1): ?>
    <div class="admin-pagination">
        <span>Halaman <?= $halaman ?> dari <?= $totalHalaman ?> (<?= $total ?> data)</span>
        <ul>
            <?php if ($halaman > 1): ?>
            <li><a href="?halaman=<?= $halaman - 1 ?><?= $filterStat ? '&status=' . urlencode($filterStat) : '' ?><?= $cari ? '&cari=' . urlencode($cari) : '' ?>">&laquo;</a></li>
            <?php endif; ?>
            <?php for ($p = max(1, $halaman - 2); $p <= min($totalHalaman, $halaman + 2); $p++): ?>
            <li>
                <?php if ($p === $halaman): ?>
                <span aria-current="page"><?= $p ?></span>
                <?php else: ?>
                <a href="?halaman=<?= $p ?><?= $filterStat ? '&status=' . urlencode($filterStat) : '' ?><?= $cari ? '&cari=' . urlencode($cari) : '' ?>"><?= $p ?></a>
                <?php endif; ?>
            </li>
            <?php endfor; ?>
            <?php if ($halaman < $totalHalaman): ?>
            <li><a href="?halaman=<?= $halaman + 1 ?><?= $filterStat ? '&status=' . urlencode($filterStat) : '' ?><?= $cari ? '&cari=' . urlencode($cari) : '' ?>">&raquo;</a></li>
            <?php endif; ?>
        </ul>
    </div>
    <?php endif; ?>
</div>

<!-- ── MODAL UPDATE STATUS ──────────────────────────────────── -->
<div id="psbModal" style="position:fixed;inset:0;background:rgba(0,0,0,0.5);z-index:999;align-items:center;justify-content:center;padding:20px;" hidden>
    <div style="background:white;border-radius:8px;padding:28px;width:100%;max-width:440px;">
        <h3 style="font-family:'Plus Jakarta Sans',sans-serif;font-size:18px;margin-bottom:20px;">
            Update Status — <span id="modalNama"></span>
        </h3>
        <form method="POST" action="<?= e(BASE_URL . '/admin/psb') ?>">
            <input type="hidden" name="csrf_token" value="<?= generateCsrfToken() ?>">
            <input type="hidden" name="action" value="update_status">
            <input type="hidden" name="id" id="modalId">

            <div style="margin-bottom:16px;">
                <label style="display:block;font-size:12px;font-weight:600;margin-bottom:6px;">Status</label>
                <select name="status" id="modalStatus" style="width:100%;padding:10px;border:1.5px solid var(--cream-dark);border-radius:4px;font-size:14px;">
                    <option value="pending">Menunggu</option>
                    <option value="diterima">Diterima</option>
                    <option value="ditolak">Ditolak</option>
                    <option value="daftar-ulang">Daftar Ulang</option>
                </select>
            </div>
            <div style="margin-bottom:20px;">
                <label style="display:block;font-size:12px;font-weight:600;margin-bottom:6px;">Catatan (opsional)</label>
                <textarea name="catatan" id="modalCatatan" rows="3"
                    style="width:100%;padding:10px;border:1.5px solid var(--cream-dark);border-radius:4px;font-size:14px;resize:vertical;"></textarea>
            </div>
            <div style="display:flex;gap:12px;">
                <button type="submit" class="btn-sm btn-sm-primary" style="padding:10px 24px;font-size:14px;">Simpan</button>
                <button type="button" class="btn-sm btn-sm-secondary" onclick="closePsbModal()" style="padding:10px 20px;font-size:14px;">Batal</button>
            </div>
        </form>
    </div>
</div>

<script>
function openPsbModal(id, nama, status, catatan) {
    document.getElementById('modalId').value = id;
    document.getElementById('modalNama').textContent = nama;
    document.getElementById('modalStatus').value = status;
    document.getElementById('modalCatatan').value = catatan;
    var modal = document.getElementById('psbModal');
    modal.removeAttribute('hidden');
    modal.style.display = 'flex';
}
function closePsbModal() {
    var modal = document.getElementById('psbModal');
    modal.setAttribute('hidden', '');
    modal.style.display = 'none';
}
function openPsbDetail(id) {
    var row = document.getElementById('detail-' + id);
    if (row) row.style.display = row.style.display === 'none' ? 'table-row' : 'none';
}
document.getElementById('psbModal').addEventListener('click', function(e) {
    if (e.target === this) closePsbModal();
});
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
