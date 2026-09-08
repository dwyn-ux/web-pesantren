<?php
require_once __DIR__ . '/bootstrap.php';
requireAdmin();

$pdo = getDB();

// ── Proses simpan ────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    validateCsrf();

    $raw = [
        'reguler'      => $_POST['jalur_reguler'] ?? [],
        'prestasi'     => $_POST['jalur_prestasi'] ?? [],
        'tahfidz'      => $_POST['jalur_tahfidz'] ?? [],
        'kaderisasi'   => $_POST['jalur_kaderisasi'] ?? [],
        'alumni-sdmua' => $_POST['jalur_alumni_sdmua'] ?? [],
        'dhuafa'       => $_POST['jalur_dhuafa'] ?? [],
    ];

    // Kolom wakaf_khusus opsional (migration 020) — fallback kalau belum ada
    $hasWakaf = (bool) $pdo->query(
        "SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE()
         AND TABLE_NAME='jalur_potongan_admin' AND COLUMN_NAME='wakaf_khusus'"
    )->fetchColumn();
    $cols = ['jalur', 'potongan_persen', 'adm_khusus', 'spp_l_khusus', 'spp_p_khusus'];
    if ($hasWakaf) $cols[] = 'wakaf_khusus';
    $cols = array_merge($cols, [
        'admin_dhuafa_bebas',
        'prestasi_kecamatan', 'prestasi_kabkota', 'prestasi_provinsi', 'prestasi_nasional',
        'tahfidz_juz2', 'tahfidz_juz3', 'tahfidz_juz5',
    ]);
    $ph = implode(',', array_fill(0, count($cols), '?'));
    $updParts = [];
    foreach ($cols as $c) {
        if ($c === 'jalur') continue;
        $updParts[] = "$c = VALUES($c)";
    }
    $upsert = $pdo->prepare(
        'INSERT INTO jalur_potongan_admin (' . implode(',', $cols) . ')'
        . ' VALUES (' . $ph . ')'
        . ' ON DUPLICATE KEY UPDATE ' . implode(',', $updParts)
    );

    $floatOrNull = function ($v): ?float {
        return (isset($v) && $v !== '' && $v !== null)
            ? (float) $v : null;
    };

    $simpanJalur = function (string $jalur, array $val) use ($upsert, $floatOrNull, $hasWakaf): void {
        // Potongan global
        $potongan  = $floatOrNull($val['potongan'] ?? null);
        // Kaderisasi / tarif khusus
        $adm       = $floatOrNull($val['adm'] ?? null);
        $spp_l     = $floatOrNull($val['spp_l'] ?? null);
        $spp_p     = $floatOrNull($val['spp_p'] ?? null);
        $wakaf     = $floatOrNull($val['wakaf'] ?? null);
        $bebas     = (int) ($val['bebas'] ?? 0) === 1;
        // Prestasi (per detail)
        $prst      = $val['prestasi'] ?? [];
        $pra_kec   = $floatOrNull($prst['kecamatan'] ?? null);
        $pra_kab   = $floatOrNull($prst['kabkota'] ?? null);
        $pra_prov  = $floatOrNull($prst['provinsi'] ?? null);
        $pra_nas   = $floatOrNull($prst['nasional'] ?? null);
        $pra_ak1   = $floatOrNull($prst['akashi']['juara1'] ?? null);
        $pra_ak2   = $floatOrNull($prst['akashi']['juara2'] ?? null);
        $pra_ak3   = $floatOrNull($prst['akashi']['juara3'] ?? null);
        // Tahfidz (per detail)
        $thzf      = $val['tahfidz'] ?? [];
        $thz_j2    = $floatOrNull($thzf['juz-2'] ?? $thzf['juz2'] ?? null);
        $thz_j3    = $floatOrNull($thzf['juz-3'] ?? $thzf['juz3'] ?? null);
        $thz_j5    = $floatOrNull($thzf['juz-5'] ?? $thzf['juz5'] ?? null);

        $params = [$jalur, $potongan, $adm, $spp_l, $spp_p];
        if ($hasWakaf) $params[] = $wakaf;
        $params = array_merge($params, [
            $bebas ? 1 : 0,
            $pra_kec, $pra_kab, $pra_prov, $pra_nas,
            $pra_ak1, $pra_ak2, $pra_ak3,
            $thz_j2, $thz_j3, $thz_j5,
        ]);
        $upsert->execute($params);
    };

    foreach ($raw as $jalur => $v) {
        $simpanJalur($jalur, $v);
    }

    setFlash('success', 'Pengaturan potongan jalur berhasil disimpan.');
    redirect('/admin/jalur-potongan');
}

// ── Baca data ────────────────────────────────────────────────
$adminJalur = getJalurPotonganAdmin($pdo);
$optsJalur  = jalurDetailOptions();

// format persen aman untuk value input
$formatPersen = function ($nilai): string {
    if ($nilai === null || $nilai === '') return '';
    $s = (string) $nilai;
    return strpos($s, '.') !== false ? rtrim(rtrim($s, '0'), '.') : $s;
};

$adminTitle = 'Potongan Jalur PSB';
$adminPage  = 'admin/jalur-potongan';
require_once __DIR__ . '/includes/header.php';
?>

<div class="admin-form-card">
    <h2 class="admin-form-title">Pengaturan Potongan Jalur</h2>
    <p class="muted">
        Sesuaikan potongan dan tarif khusus per jalur. Nilai ini dipakai di form PSB (estimasi live) dan saat membentuk tagihan santri.
        Jalur yang tidak diatur tetap memakai nilai juknis default.
    </p>

    <form method="post" class="admin-form">
        <input type="hidden" name="csrf_token" value="<?= generateCsrfToken() ?>">

        <!-- REGULER -->
        <fieldset class="jalur-fieldset">
            <legend>Reguler</legend>
            <div class="form-row">
                <div class="form-group">
                    <label>Tidak ada potongan untuk jalur reguler.</label>
                    <input type="number" min="0" max="100" step="0.5" name="jalur_reguler[potongan]"
                           value="<?= e($formatPersen($adminJalur['reguler']['potongan'] ?? null)) ?>"
                           class="form-control">
                    <p class="muted">
                        Jika diisi, form PSB akan memotong persentase ini dari tagihan (hanya jika dipilih).
                    </p>
                </div>
            </div>
        </fieldset>

        <!-- PRESTASI -->
        <fieldset class="jalur-fieldset">
            <legend>
                Prestasi (Akademik/Non-Akademik)
                <?php if (empty($optsJalur['prestasi'])): ?>
                    <span class="muted">(belum ada opsi detail)</span>
                <?php endif; ?>
            </legend>

            <div class="form-row">
                <div class="form-group">
                    <label>Potongan umum (persen) — jika diisi, akan mengalahkan detail tingkat prestasi.</label>
                    <input type="number" min="0" max="100" step="0.5"
                           name="jalur_prestasi[potongan]"
                           value="<?= e($formatPersen($adminJalur['prestasi']['potongan'] ?? null)) ?>"
                           class="form-control">
                    <p class="muted">
                        Kosongkan jika ingin memakai nilai per tingkat (kecamatan 20%, kabkota 30%, provinsi 40%, nasional 50%).
                    </p>
                </div>
            </div>

            <div class="form-row">
            <?php foreach ($optsJalur['prestasi'] as $val => $opt): ?>
                <div class="form-group">
                    <label><?= e($opt['label']) ?></label>
                    <input type="number" min="0" max="100" step="0.5"
                           name="jalur_prestasi[prestasi][<?= e($val) ?>]"
                           value="<?= e($formatPersen($adminJalur['prestasi']['prestasi'][$val] ?? null)) ?>"
                           class="form-control">
                    <p class="muted">
                        Kosongkan jika ingin memakai nilai juknis (<?= (int) $opt['potongan'] ?>%).
                    </p>
                </div>
            <?php endforeach; ?>
            </div>
        </fieldset>

        <!-- TAHFIDZ -->
        <fieldset class="jalur-fieldset">
            <legend>
                Tahfidz Al-Qur'an
                <?php if (empty($optsJalur['tahfidz'])): ?>
                    <span class="muted">(belum ada opsi detail)</span>
                <?php endif; ?>
            </legend>

            <div class="form-row">
                <div class="muted">Detail potongan per kategori hafalan</div>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label>Potongan umum (persen) — jika diisi, akan mengalahkan detail kategori hafalan.</label>
                    <input type="number" min="0" max="100" step="0.5"
                           name="jalur_tahfidz[potongan]"
                           value="<?= e($formatPersen($adminJalur['tahfidz']['potongan'] ?? null)) ?>"
                           class="form-control">
                    <p class="muted">
                        Kosongkan jika ingin memakai nilai per kategori (juz 2: 20%, juz 3: 30%, juz 5: 50%).
                    </p>
                </div>
            </div>

            <?php
            $tahfidzKey = ['juz-2' => 'juz2', 'juz-3' => 'juz3', 'juz-5' => 'juz5'];
            ?>
            <div class="form-row">
            <?php foreach ($optsJalur['tahfidz'] as $val => $opt): ?>
                <div class="form-group">
                    <label><?= e($opt['label']) ?></label>
                    <input type="number" min="0" max="100" step="0.5"
                           name="jalur_tahfidz[tahfidz][<?= e($val) ?>]"
                           value="<?= e($formatPersen($adminJalur['tahfidz']['tahfidz'][$tahfidzKey[$val] ?? $val] ?? null)) ?>"
                           class="form-control">
                    <p class="muted">
                        Kosongkan jika ingin memakai nilai juknis (<?= (int) $opt['potongan'] ?>%).
                    </p>
                </div>
            <?php endforeach; ?>
            </div>
        </fieldset>

        <!-- KADERISASI -->
        <fieldset class="jalur-fieldset">
            <legend>Kaderisasi (Jalur Khusus)</legend>
            <div class="form-row">
                <div class="form-group">
                    <label>ADM Awal khusus (Rp)</label>
                    <input type="number" min="0" name="jalur_kaderisasi[adm]"
                           value="<?= e($formatPersen($adminJalur['kaderisasi']['adm'] ?? null)) ?>"
                           class="form-control">
                    <p class="muted">Default juknis: Rp 5.000.000</p>
                </div>
                <div class="form-group">
                    <label>SPP Putra/bulan (Rp)</label>
                    <input type="number" min="0" name="jalur_kaderisasi[spp_l]"
                           value="<?= e($formatPersen($adminJalur['kaderisasi']['spp_l'] ?? null)) ?>"
                           class="form-control">
                    <p class="muted">Default juknis: Rp 650.000</p>
                </div>
                <div class="form-group">
                    <label>SPP Putri/bulan (Rp)</label>
                    <input type="number" min="0" name="jalur_kaderisasi[spp_p]"
                           value="<?= e($formatPersen($adminJalur['kaderisasi']['spp_p'] ?? null)) ?>"
                           class="form-control">
                    <p class="muted">Default juknis: Rp 750.000</p>
                </div>
                <div class="form-group">
                    <label>Wakaf khusus (Rp)</label>
                    <input type="number" min="0" name="jalur_kaderisasi[wakaf]"
                           value="<?= e($formatPersen($adminJalur['kaderisasi']['wakaf'] ?? null)) ?>"
                           class="form-control">
                    <p class="muted">Kosongkan = ikut tarif gelombang normal.</p>
                </div>
            </div>
        </fieldset>

        <!-- ALUMNI SDMUa -->
        <fieldset class="jalur-fieldset">
            <legend>Alumni SD Muhammadiyah Unggulan Ashidiq</legend>
            <div class="form-row">
                <div class="form-group">
                    <label>Potongan umum (persen) — jika diisi, akan memakai persen ini setelah jalur disetujui.</label>
                    <input type="number" min="0" max="100" step="0.5"
                           name="jalur_alumni_sdmua[potongan]"
                           value="<?= e($formatPersen($adminJalur['alumni-sdmua']['potongan'] ?? null)) ?>"
                           class="form-control">
                    <p class="muted">
                        Juknis: keringanan 25–50%. Kosongkan jika ingin menetapkan persen per-santri saat verifikasi.
                        Nilai ini dipakai sebagai estimasi awal di form PSB dan sebagai default di halaman verifikasi admin.
                    </p>
                </div>
                <div class="form-group">
                    <label>Wakaf khusus (Rp)</label>
                    <input type="number" min="0"
                           name="jalur_alumni_sdmua[wakaf]"
                           value="<?= e($formatPersen($adminJalur['alumni-sdmua']['wakaf'] ?? null)) ?>"
                           class="form-control">
                    <p class="muted">
                        Kosongkan = ikut tarif gelombang normal.
                    </p>
                </div>
            </div>
        </fieldset>

        <!-- AKASHI (Prestasi Internal) -->
        <fieldset class="jalur-fieldset">
            <legend>Akashi — Potongan ADM Awal per Juara</legend>
            <p class="muted" style="margin-bottom:14px;">
                Nominal Rp pengurang ADM awal sesuai juara voucher Akashi.
                Juknis: Juara 1 = Rp 2.000.000, Juara 2 = Rp 1.500.000, Juara 3 = Rp 1.000.000.
                Kosongkan = pakai juknis default.
            </p>
            <div class="form-row">
                <div class="form-group">
                    <label>Juara 1 (Rp)</label>
                    <input type="number" min="0" name="jalur_prestasi[akashi][juara1]"
                           value="<?= e($formatPersen($adminJalur['prestasi']['akashi']['juara1'] ?? null)) ?>"
                           class="form-control">
                </div>
                <div class="form-group">
                    <label>Juara 2 (Rp)</label>
                    <input type="number" min="0" name="jalur_prestasi[akashi][juara2]"
                           value="<?= e($formatPersen($adminJalur['prestasi']['akashi']['juara2'] ?? null)) ?>"
                           class="form-control">
                </div>
                <div class="form-group">
                    <label>Juara 3 (Rp)</label>
                    <input type="number" min="0" name="jalur_prestasi[akashi][juara3]"
                           value="<?= e($formatPersen($adminJalur['prestasi']['akashi']['juara3'] ?? null)) ?>"
                           class="form-control">
                </div>
            </div>
        </fieldset>

        <!-- DHUAFA -->
        <fieldset class="jalur-fieldset">
            <legend>Dhuafa / Beasiswa Empowerment</legend>
            <div class="form-row">
                <div class="form-group">
                    <label>Potongan umum (persen) — jika diisi, akan memakai persen ini setelah jalur disetujui.</label>
                    <input type="number" min="0" max="100" step="0.5"
                           name="jalur_dhuafa[potongan]"
                           value="<?= e($formatPersen($adminJalur['dhuafa']['potongan'] ?? null)) ?>"
                           class="form-control">
                    <p class="muted">
                        Juknis: keringanan 20–60%. Kosongkan jika ingin menetapkan persen per-santri saat verifikasi.
                        Nilai ini dipakai sebagai estimasi awal di form PSB dan sebagai default di halaman verifikasi admin.
                    </p>
                </div>
            </div>
            <div>
                <label>
                    <input type="checkbox" name="jalur_dhuafa[bebas]" value="1"                           <?= !empty($adminJalur['dhuafa']['dhuafa_bebas']) ? 'checked' : '' ?>>
                    ADM Awal DHUAFA dibebaskan 100%
                </label>
                <p class="muted">
                    Jika dicentang, ADM awal dhuafa yang disetujui akan GRATIS.
                </p>
            </div>
        </fieldset>

        <div class="form-actions">
            <button class="btn-sm btn-sm-primary">Simpan Pengaturan</button>
        </div>
    </form>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
