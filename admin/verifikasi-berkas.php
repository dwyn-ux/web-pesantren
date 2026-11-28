<?php
require_once __DIR__ . '/bootstrap.php';
requireAdmin();

$pdo = getDB();
$adminTitle = 'Verifikasi Berkas & Penerimaan';
$adminPage  = 'admin/verifikasi-berkas';

$msg = '';
$msgType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    validateCsrf();
    $act = sanitizeString($_POST['act'] ?? '');
    $pendaftaranId = sanitizeInt($_POST['pendaftaran_id'] ?? 0);

    if ($act === 'setujui' && $pendaftaranId) {
        $potongan = sanitizeFloat($_POST['jalur_potongan'] ?? 0) ?: null;
        $res = jalurTerapkanKeputusan($pdo, $pendaftaranId, 'disetujui', $potongan);
        $msg = $res['pesan']; $msgType = $res['ok'] ? 'success' : 'error';
        if ($res['ok']) {
            kirimTelegram('Jalur DISETUJUI' . ($potongan !== null ? " ({$potongan}%)" : '') . ': ' . telegramInfoPendaftar($pdo, $pendaftaranId), $pdo);
        }
    } elseif ($act === 'tolak' && $pendaftaranId) {
        $res = jalurTerapkanKeputusan($pdo, $pendaftaranId, 'ditolak');
        $msg = $res['pesan']; $msgType = $res['ok'] ? 'success' : 'error';
        if ($res['ok']) {
            kirimTelegram('Jalur DITOLAK (kembali reguler): ' . telegramInfoPendaftar($pdo, $pendaftaranId), $pdo);
        }
    } elseif ($act === 'snapshot' && $pendaftaranId) {
        $q = $pdo->prepare('SELECT jalur, jalur_status FROM pendaftaran WHERE id = ?');
        $q->execute([$pendaftaranId]);
        $jRow = $q->fetch();
        if ($jRow && in_array($jRow['jalur'], jalurPerluVerifikasi(), true) && $jRow['jalur_status'] !== 'disetujui') {
            $msg = 'Belum bisa diterima: pastikan jalur Alumni/Dhuafa sudah disetujui atau ditolak terlebih dahulu.';
            $msgType = 'error';
        } else {
            applyTarifToSnapshot($pdo, $pendaftaranId);
            $pdo->prepare("UPDATE pendaftaran SET status='diterima' WHERE id=?")->execute([$pendaftaranId]);
            $msg = 'Santri DITERIMA dan tagihan pendaftaran sudah dibuat.'; $msgType = 'success';
            kirimTelegram('DITERIMA: ' . telegramInfoPendaftar($pdo, $pendaftaranId), $pdo);
        }
    } elseif ($act === 'tolak_pendaftaran' && $pendaftaranId) {
        $pdo->prepare("UPDATE pendaftaran SET status='ditolak' WHERE id=?")->execute([$pendaftaranId]);
        $msg = 'Pendaftaran ditolak.'; $msgType = 'success';
        kirimTelegram('Pendaftaran DITOLAK: ' . telegramInfoPendaftar($pdo, $pendaftaranId), $pdo);
    } elseif ($act === 'wa_log' && $pendaftaranId) {
        // Catat klik tombol Kirim WA (opsi B: link wa.me dibuka di tab baru)
        $isi = sanitizeString($_POST['isi'] ?? '');
        $nomor = sanitizeString($_POST['nomor'] ?? '');
        try {
            $pdo->prepare(
                "INSERT INTO notif_log (pendaftaran_id, kanal, nomor, isi, status, oleh)
                 VALUES (?,?,?,?, 'klik-kirim', ?)"
            )->execute([$pendaftaranId, 'wa', $nomor, $isi, $_SESSION['user_id'] ?? null]);
            $msg = 'Terkirim via WA (tercatat di log).'; $msgType = 'success';
        } catch (PDOException $e) {
            error_log('WA log gagal: ' . $e->getMessage());
            $msg = 'Gagal mencatat log WA.'; $msgType = 'error';
        }
    }
}

// Filter
$filterStatus = sanitizeString($_GET['status'] ?? '');
$filterJalur  = sanitizeString($_GET['jalur'] ?? '');

$where = ['1=1'];
$params = [];
if ($filterStatus) { $where[] = 'p.status = ?'; $params[] = $filterStatus; }
if ($filterJalur)  { $where[] = 'p.jalur = ?';   $params[] = $filterJalur; }

$sql = "SELECT p.*, u.email, pg.label AS gelombang_label, pg.tanggal_tes
        FROM pendaftaran p
        LEFT JOIN users u ON u.id = p.user_id
        LEFT JOIN pendaftaran_gelombang pg ON pg.id = p.gelombang_id
        WHERE " . implode(' AND ', $where) . "
        ORDER BY FIELD(p.status, 'menunggu-verifikasi', 'tes-selesai', 'pending', 'diterima', 'daftar-ulang', 'ditolak'), p.created_at DESC
        LIMIT 200";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$list = $stmt->fetchAll();

include __DIR__ . '/includes/header.php';

$labelJalur = jalurPendaftaran();
$labelStatus = [
    'pending'             => 'Baru Daftar',
    'menunggu-verifikasi' => 'Menunggu Verifikasi',
    'tes-selesai'         => 'Tes Selesai',
    'diterima'            => 'Diterima',
    'ditolak'             => 'Ditolak',
    'daftar-ulang'        => 'Daftar Ulang',
];
?>

<?php if ($msg): ?>
<div class="flash-message flash-<?= e($msgType) ?>"><?= e($msg) ?>
    <button class="flash-close" onclick="this.parentElement.remove()">&times;</button>
</div>
<?php endif; ?>

<div class="admin-table-wrap">
    <div class="table-head">
        <h2>Verifikasi Pendaftaran</h2>
    </div>

    <form method="get" class="filter-toolbar" style="padding:12px 20px 0;">
        <select name="status" aria-label="Filter status">
            <option value="">— Semua Status —</option>
            <?php foreach ($labelStatus as $k => $v): ?>
                <option value="<?= $k ?>" <?= $filterStatus === $k ? 'selected' : '' ?>><?= e($v) ?></option>
            <?php endforeach; ?>
        </select>
        <select name="jalur" aria-label="Filter jalur">
            <option value="">— Semua Jalur —</option>
            <?php foreach ($labelJalur as $k => $v): ?>
                <option value="<?= $k ?>" <?= $filterJalur === $k ? 'selected' : '' ?>><?= e($v) ?></option>
            <?php endforeach; ?>
        </select>
        <button type="submit" class="btn-sm btn-sm-primary">Filter</button>
    </form>

    <?php if (empty($list)): ?>
    <div class="table-empty">Tidak ada data.</div>
    <?php else: ?>
    <div style="overflow-x:auto;">
    <table class="admin-table" style="min-width:780px;">
        <thead>
            <tr>
                <th>Nomor</th>
                <th>Nama</th>
                <th>Jenjang</th>
                <th>Jalur</th>
                <th>Status</th>
                <th>Gelombang</th>
                <th style="width:90px;">Aksi</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($list as $r): ?>
            <tr>
                <td><code><?= e($r['nomor_daftar']) ?></code></td>
                <td><?= e($r['nama_lengkap']) ?><br>
                    <small class="muted"><?= e($r['email'] ?? '-') ?></small>
                </td>
                <td><?= e($r['jenjang']) ?></td>
                <td>
                    <?= e($labelJalur[$r['jalur']] ?? $r['jalur']) ?>
                    <?php if ($r['jalur_detail']): ?>
                        <br><small class="muted"><?= e($r['jalur_detail']) ?></small>
                    <?php endif; ?>
                    <?php if (in_array($r['jalur'], ['alumni-sdmua','dhuafa'], true)): ?>
                        <br><small style="color:#c33;">Status jalur: <?= e($r['jalur_status']) ?></small>
                    <?php endif; ?>
                </td>
                <td><span class="badge badge-draft"><?= e($labelStatus[$r['status']] ?? $r['status']) ?></span></td>
                <td><?= e($r['gelombang_label'] ?? '-') ?><br>
                    <small class="muted">Tes: <?= $r['tanggal_tes'] ? date('d M Y', strtotime($r['tanggal_tes'])) : '-' ?></small>
                </td>
                <td>
                    <?php
                    $detailUrl = '?detail=' . (int)$r['id']
                        . ($filterStatus !== '' ? '&status=' . urlencode($filterStatus) : '')
                        . ($filterJalur !== '' ? '&jalur=' . urlencode($filterJalur) : '');
                    ?>
                    <a href="<?= e($detailUrl) ?>" class="btn-sm btn-sm-secondary" style="text-decoration:none;">Detail</a>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    </div>
    <?php endif; ?>
</div>

    <?php
    if (isset($_GET['detail'])) {
        $did = sanitizeInt($_GET['detail']);
        $dstmt = $pdo->prepare("SELECT p.*, pg.label AS gelombang_label FROM pendaftaran p
                                LEFT JOIN pendaftaran_gelombang pg ON pg.id=p.gelombang_id
                                WHERE p.id=?");
        $dstmt->execute([$did]);
        $d = $dstmt->fetch();
        if ($d):
            // Ambil berkas
            $bs = $pdo->prepare("SELECT * FROM berkas_santri WHERE pendaftaran_id=?");
            $bs->execute([$did]);
            $berkasList = $bs->fetchAll();
            $byJenis = [];
            foreach ($berkasList as $b) $byJenis[$b['jenis']] = $b;
            $wajibList = ['kartu-keluarga','akta-lahir','foto','ktp-ortu'];
            $jalurBerkas = jalurBerkasUntuk($d['jalur']);
    ?>
    <div id="verifDetailModal" role="dialog" aria-modal="true" aria-labelledby="verifModalTitle"
         style="position:fixed;inset:0;background:rgba(15,35,24,0.55);z-index:999;display:flex;align-items:center;justify-content:center;padding:20px;">
    <div class="admin-form-card" style="width:100%;max-width:860px;max-height:90vh;overflow-y:auto;margin-bottom:0;position:relative;">
        <div style="display:flex;align-items:center;justify-content:space-between;gap:12px;margin-bottom:18px;padding-bottom:12px;border-bottom:1px solid var(--cream-dark);">
            <div class="admin-form-title" id="verifModalTitle" style="margin-bottom:0;border-bottom:none;padding-bottom:0;">
                Detail: <?= e($d['nama_lengkap']) ?> (<?= e($d['nomor_daftar']) ?>)
            </div>
            <button type="button" id="closeVerifModal" class="btn-sm btn-sm-secondary" aria-label="Tutup">&times; Tutup</button>
        </div>

        <div class="detail-grid">
            <div>
                <h4>Identitas</h4>
                <p>TTL: <?= e($d['tempat_lahir']) ?>, <?= e($d['tanggal_lahir']) ?><br>
                   JK: <?= e($d['jenis_kelamin']) ?><br>
                   Jenjang: <?= e($d['jenjang']) ?><br>
                   WhatsApp: <?= e($d['whatsapp']) ?></p>

                <h4>Orang Tua</h4>
                <p>Ayah: <?= e($d['nama_ayah']) ?><br>
                   Ibu: <?= e($d['nama_ibu']) ?><br>
                   HP: <?= e($d['hp_ortu']) ?><br>
                   Alamat: <?= e($d['alamat']) ?></p>
            </div>
            <div>
                <h4>Jalur</h4>
                <p><?= e($labelJalur[$d['jalur']] ?? $d['jalur']) ?>
                   <?= $d['jalur_detail'] ? '(' . e($d['jalur_detail']) . ')' : '' ?><br>
                   Status jalur: <strong><?= e($d['jalur_status']) ?></strong>
                   <?= $d['jalur_potongan'] !== null ? '<br>Potongan: ' . (float)$d['jalur_potongan'] . '%' : '' ?>
                </p>

                <h4>Gelombang</h4>
                <p><?= e($d['gelombang_label']) ?><br>
                   Status: <strong><?= e($labelStatus[$d['status']] ?? $d['status']) ?></strong></p>
            </div>
        </div>

        <h4>Berkas Wajib</h4>
        <table class="admin-table">
            <thead><tr><th>Jenis</th><th>Status Upload</th><th>Aksi</th></tr></thead>
            <tbody>
                <?php foreach ($wajibList as $j): ?>
                <tr>
                    <td><?= e(berkasLabel($j)) ?></td>
                    <td>
                        <?php if (isset($byJenis[$j])): ?>
                            <span class="badge badge-success">✓ Ada</span>
                        <?php else: ?>
                            <span class="badge badge-muted">Belum</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php if (isset($byJenis[$j])): ?>
                            <a href="/admin/berkas-lihat?jenis=<?= e($j) ?>&pendaftaran_id=<?= (int)$did ?>" target="_blank" class="btn-sm btn-sm-secondary" style="text-decoration:none;">Lihat</a>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <?php if (!empty($jalurBerkas)): ?>
        <h4>Berkas Jalur</h4>
        <table class="admin-table">
            <thead><tr><th>Jenis</th><th>Status Upload</th><th>Aksi</th></tr></thead>
            <tbody>
                <?php foreach ($jalurBerkas as $j): ?>
                <tr>
                    <td><?= e(berkasLabel($j)) ?></td>
                    <td>
                        <?php if (isset($byJenis[$j])): ?>
                            <span class="badge badge-success">✓ Ada</span>
                        <?php else: ?>
                            <span class="badge badge-muted">Belum</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php if (isset($byJenis[$j])): ?>
                            <a href="/admin/berkas-lihat?jenis=<?= e($j) ?>&pendaftaran_id=<?= (int)$did ?>" target="_blank" class="btn-sm btn-sm-secondary" style="text-decoration:none;">Lihat</a>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php endif; ?>

        <h4>Aksi Verifikasi</h4>
        <div class="form-actions">
            <?php
            // WA follow-up dinamis: berkas kurang utk status pending
            $kurangWa = [];
            if ($d['status'] === 'pending') {
                $cekW = $pdo->prepare('SELECT jenis FROM berkas_santri WHERE pendaftaran_id = ?');
                $cekW->execute([(int) $did]);
                $adaW = array_column($cekW->fetchAll(), 'jenis');
                foreach (berkasWajib() as $w) {
                    if (!in_array($w, $adaW, true)) $kurangWa[] = berkasLabel($w);
                }
                foreach (jalurBerkasUntuk($d['jalur'] ?? 'reguler') as $jb) {
                    if (!in_array($jb, $adaW, true)) $kurangWa[] = berkasLabel($jb);
                }
            }
            $pesanWa = templateWaFollowup($pdo, $d, $kurangWa);
            ?>
            <a href="<?= e(BASE_URL . '/kartu-santri?id=' . (int)$did) ?>" target="_blank" rel="noopener"
               class="btn-sm btn-sm-secondary" style="text-decoration:none;">Kartu Santri</a>
            <a href="<?= e(BASE_URL . '/kartu-santri?id=' . (int)$did . '&download=pdf') ?>"
               class="btn-sm btn-sm-secondary" style="text-decoration:none;">Download Kartu (PDF)</a>
            <a href="<?= e(linkWa($d['whatsapp'] ?? $d['hp_ortu'] ?? '', $pesanWa)) ?>" target="_blank" rel="noopener"
               class="btn-sm btn-sm-primary" style="text-decoration:none;" id="btnKirimWa"><?= e(labelWaFollowup($d['status'])) ?> (WA)</a>
            <script>
            document.getElementById('btnKirimWa').addEventListener('click', function () {
              var fd = new FormData();
              fd.append('csrf_token', '<?= generateCsrfToken() ?>');
              fd.append('act', 'wa_log');
              fd.append('pendaftaran_id', '<?= (int)$did ?>');
              fd.append('nomor', '<?= e(nomorWa($d['whatsapp'] ?? $d['hp_ortu'] ?? '')) ?>');
              fd.append('isi', <?= json_encode($pesanWa, JSON_UNESCAPED_UNICODE) ?>);
              fetch(location.pathname + '?detail=<?= (int)$did ?>', { method: 'POST', body: fd, credentials: 'same-origin' });
            });
            </script>
            <?php if (in_array($d['jalur'], ['alumni-sdmua','dhuafa'], true) && $d['jalur_status'] === 'pending'): ?>
                <form method="post" style="display:flex;gap:8px;align-items:center;flex-wrap:wrap;">
                    <input type="hidden" name="csrf_token" value="<?= generateCsrfToken() ?>">
                    <input type="hidden" name="act" value="setujui">
                    <input type="hidden" name="pendaftaran_id" value="<?= (int)$did ?>">
                    <input type="number" name="jalur_potongan" placeholder="Potongan %" min="0" max="100" step="0.01" class="form-control" style="width:120px;">
                    <button type="submit" class="btn-sm btn-sm-primary">Setujui + Potongan</button>
                </form>
                <form method="post">
                    <input type="hidden" name="csrf_token" value="<?= generateCsrfToken() ?>">
                    <input type="hidden" name="act" value="tolak">
                    <input type="hidden" name="pendaftaran_id" value="<?= (int)$did ?>">
                    <button type="submit" class="btn-sm btn-sm-secondary">Tolak Jalur</button>
                </form>
            <?php endif; ?>

            <?php if (in_array($d['status'], ['pending', 'menunggu-verifikasi', 'tes-selesai'], true)): ?>
                <form method="post" onsubmit="return confirm('Terima santri ini dan buat tagihan pendaftaran?')">
                    <input type="hidden" name="csrf_token" value="<?= generateCsrfToken() ?>">
                    <input type="hidden" name="act" value="snapshot">
                    <input type="hidden" name="pendaftaran_id" value="<?= (int)$did ?>">
                    <button type="submit" class="btn-sm btn-sm-primary">✓ Terima Santri</button>
                </form>
            <?php endif; ?>

            <?php if (!in_array($d['status'], ['diterima','daftar-ulang','ditolak'], true)): ?>
                <form method="post" onsubmit="return confirm('Tolak pendaftaran ini?')">
                    <input type="hidden" name="csrf_token" value="<?= generateCsrfToken() ?>">
                    <input type="hidden" name="act" value="tolak_pendaftaran">
                    <input type="hidden" name="pendaftaran_id" value="<?= (int)$did ?>">
                    <button type="submit" class="btn-sm btn-sm-danger">Tolak Pendaftaran</button>
                </form>
            <?php endif; ?>

            <?php if (isSnapshotFinal((int)$did, $pdo)): ?>
                <span class="badge badge-success">✓ Tagihan Resmi Sudah Dibuat</span>
            <?php endif; ?>
        </div>
    </div>
    </div>
    <script>
    (function () {
      var modal = document.getElementById('verifDetailModal');
      var closeBtn = document.getElementById('closeVerifModal');
      if (!modal) return;
      function closeVerif() {
        modal.style.display = 'none';
        var u = new URL(window.location.href);
        u.searchParams.delete('detail');
        window.history.replaceState({}, '', u.pathname + (u.searchParams.toString() ? '?' + u.searchParams.toString() : ''));
      }
      if (closeBtn) closeBtn.addEventListener('click', closeVerif);
      modal.addEventListener('click', function (e) { if (e.target === modal) closeVerif(); });
      document.addEventListener('keydown', function (e) { if (e.key === 'Escape' && modal.style.display !== 'none') closeVerif(); });
    }());
    </script>
    <?php endif; } ?>

<?php include __DIR__ . '/includes/footer.php'; ?>
