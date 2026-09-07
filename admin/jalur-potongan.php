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

    $upsert = $pdo->prepare(
        'INSERT INTO jalur_potongan_admin
            (jalur, potongan_persen, adm_khusus, spp_l_khusus, spp_p_khusus, admin_dhuafa_bebas,
             prestasi_kecamatan, prestasi_kabkota, prestasi_provinsi, prestasi_nasional,
             tahfidz_juz2, tahfidz_juz3, tahfidz_juz5)
         VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)
         ON DUPLICATE KEY UPDATE
            potongan_persen   = VALUES(potongan_persen),
            adm_khusus       = VALUES(adm_khusus),
            spp_l_khusus     = VALUES(spp_l_khusus),
            spp_p_khusus     = VALUES(spp_p_khusus),
            admin_dhuafa_bebas = VALUES(admin_dhuafa_bebas),
            prestasi_kecamatan = VALUES(prestasi_kecamatan),
            prestasi_kabkota   = VALUES(prestasi_kabkota),
            prestasi_provinsi  = VALUES(prestasi_provinsi),
            prestasi_nasional  = VALUES(prestasi_nasional),
            tahfidz_juz2       = VALUES(tahfidz_juz2),
            tahfidz_juz3       = VALUES(tahfidz_juz3),
            tahfidz_juz5       = VALUES(tahfidz_juz5)'
    );

    $floatOrNull = function ($v): ?float {
        return (isset($v) && $v !== '' && $v !== null)
            ? (float) $v : null;
    };

    $simpanJalur = function (string $jalur, array $val) use ($upsert, $floatOrNull): void {
        // Potongan global
        $potongan  = $floatOrNull($val['potongan']);
        // Kaderisasi
        $adm       = $floatOrNull($val['adm']);
        $spp_l     = $floatOrNull($val['spp_l']);
        $spp_p     = $floatOrNull($val['spp_p']);
        $bebas     = (int) ($val['bebas'] ?? 0) === 1;
        // Prestasi (per detail)
        $prst      = $val['prestasi'] ?? [];
        $pra_kec   = $floatOrNull($prst['kecamatan'] ?? null);
        $pra_kab   = $floatOrNull($prst['kabkota'] ?? null);
        $pra_prov  = $floatOrNull($prst['provinsi'] ?? null);
        $pra_nas   = $floatOrNull($prst['nasional'] ?? null);
        // Tahfidz (per detail)
        $thzf      = $val['tahfidz'] ?? [];
        $thz_j2    = $floatOrNull($thzf['juz2'] ?? null);
        $thz_j3    = $floatOrNull($thzf['juz3'] ?? null);
        $thz_j5    = $floatOrNull($thzf['juz5'] ?? null);

        $upsert->execute([
            $jalur, $potongan, $adm, $spp_l, $spp_p, $bebas ? 1 : 0,
            $pra_kec, $pra_kab, $pra_prov, $pra_nas,
            $thz_j2, $thz_j3, $thz_j5,
        ]);
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

<div class="admin-form-card" style="max-width:880px;">
    <h2 class="admin-form-title">Pengaturan Potongan Jalur</h2>
    <p style="font-size:13px;color:var(--text-mid);margin-bottom:20px;">
        Sesuaikan potongan dan tarif khusus per jalur. Nilai ini dipakai di form PSB (estimasi live) dan saat membentuk tagihan santri.
        Jalur yang tidak diatur tetap memakai nilai juknis default.
    </p>

    <form method="post" class="admin-form">
        <input type="hidden" name="csrf_token" value="<?= generateCsrfToken() ?>">

        <!-- REGULER -->
        <fieldset style="margin-bottom:22px;border:1px solid var(--cream-dark);border-radius:10px;padding:16px 18px;">
            <legend style="font-size:14px;font-weight:600;padding:0 8px;color:var(--text-mid);">Reguler</legend>
            <div style="margin-top:10px;display:grid;grid-template-columns:1fr 180px;gap:14px;align-items:end;">
                <div class="form-group">
                    <label style="font-size:12px;">Tidak ada potongan untuk jalur reguler.</label>
                    <input type="number" min="0" max="100" step="0.5" name="jalur_reguler[potongan]"
                           value="<?= e($formatPersen($adminJalur['reguler']['potongan'] ?? null)) ?>"
                           class="form-control">
                    <small style="font-size:11px;color:var(--text-light);line-height:1.55;">
                        Jika diisi, form PSB akan memotong persentase ini dari tagihan (hanya jika dipilih).
                    </small>
                </div>
            </div>
        </fieldset>

        <!-- PRESTASI -->
        <fieldset style="margin-bottom:22px;border:1px solid var(--cream-dark);border-radius:10px;padding:16px 18px;">
            <legend style="font-size:14px;font-weight:600;padding:0 8px;color:var(--text-mid);">
                Prestasi (Akademik/Non-Akademik)
                <?php if (empty($optsJalur['prestasi'])): ?>
                    <span style="font-weight:normal;font-size:11px;color:var(--text-light);margin-left:6px;">(belum ada opsi detail)</span>
                <?php endif; ?>
            </legend>

            <div style="margin-top:14px;display:grid;grid-template-columns:1fr 180px;gap:14px;align-items:end;">
                <div class="form-group">
                    <label style="font-size:12px;">Potongan umum (persen) — jika diisi, akan mengalahkan detail tingkat prestasi.</label>
                    <input type="number" min="0" max="100" step="0.5"
                           name="jalur_prestasi[potongan]"
                           value="<?= e($formatPersen($adminJalur['prestasi']['potongan'] ?? null)) ?>"
                           class="form-control">
                    <small style="font-size:11px;color:var(--text-light);line-height:1.55;">
                        Kosongkan jika ingin memakai nilai per tingkat (kecamatan 20%, kabkota 30%, provinsi 40%, nasional 50%).
                    </small>
                </div>
            </div>

            <?php foreach ($optsJalur['prestasi'] as $val => $opt): ?>
            <div style="margin-top:12px;display:grid;grid-template-columns:1fr 140px;gap:14px;align-items:end;">
                <div class="form-group">
                    <label style="font-size:12px;"><?= e($opt['label']) ?></label>
                    <input type="number" min="0" max="100" step="0.5"
                           name="jalur_prestasi[prestasi][<?= e($val) ?>]"
                           value="<?= e($formatPersen($adminJalur['prestasi'][$val] ?? null)) ?>"
                           class="form-control">
                    <small style="font-size:11px;color:var(--text-light);line-height:1.55;">
                        Kosongkan jika ingin memakai nilai juknis (<?= (int) $opt['potongan'] ?>%).
                    </small>
                </div>
            </div>
            <?php endforeach; ?>
        </fieldset>

        <!-- TAHFIDZ -->
        <fieldset style="margin-bottom:22px;border:1px solid var(--cream-dark);border-radius:10px;padding:16px 18px;">
            <legend style="font-size:14px;font-weight:600;padding:0 8px;color:var(--text-mid);">
                Tahfidz Al-Qur'an
                <?php if (empty($optsJalur['tahfidz'])): ?>
                    <span style="font-weight:normal;font-size:11px;color:var(--text-light);margin-left:6px;">(belum ada opsi detail)</span>
                <?php endif; ?>
            </legend>

            <div style="margin-top:14px;display:grid;border-bottom:1px dashed rgba(255,255,255,0.12);padding-bottom:12px;">
                <div style="grid-column:1/3;font-weight:600;font-size:11px;color:var(--text-mid);margin-bottom:8px;">Detail potongan per kategori hafalan</div>
            </div>

            <div style="margin-top:14px;display:grid;grid-template-columns:1fr 180px;gap:14px;align-items:end;">
                <div class="form-group">
                    <label style="font-size:12px;">Potongan umum (persen) — jika diisi, akan mengalahkan detail kategori hafalan.</label>
                    <input type="number" min="0" max="100" step="0.5"
                           name="jalur_tahfidz[potongan]"
                           value="<?= e($formatPersen($adminJalur['tahfidz']['potongan'] ?? null)) ?>"
                           class="form-control">
                    <small style="font-size:11px;color:var(--text-light);line-height:1.55;">
                        Kosongkan jika ingin memakai nilai per kategori (juz 2: 20%, juz 3: 30%, juz 5: 50%).
                    </small>
                </div>
            </div>

            <?php foreach ($optsJalur['tahfidz'] as $val => $opt): ?>
            <div style="margin-top:12px;display:grid;grid-template-columns:1fr 140px;gap:14px;align-items:end;">
                <div class="form-group">
                    <label style="font-size:12px;"><?= e($opt['label']) ?></label>
                    <input type="number" min="0" max="100" step="0.5"
                           name="jalur_tahfidz[tahfidz][<?= e($val) ?>]"
                           value="<?= e($formatPersen($adminJalur['tahfidz'][$val] ?? null)) ?>"
                           class="form-control">
                    <small style="font-size:11px;color:var(--text-light);line-height:1.55;">
                        Kosongkan jika ingin memakai nilai juknis (<?= (int) $opt['potongan'] ?>%).
                    </small>
                </div>
            </div>
            <?php endforeach; ?>
        </fieldset>

        <!-- KADERISASI -->
        <fieldset style="margin-bottom:22px;border:1px solid var(--cream-dark);border-radius:10px;padding:16px 18px;">
            <legend style="font-size:14px;font-weight:600;padding:0 8px;color:var(--text-mid);">Kaderisasi (Jalur Khusus)</legend>
            <div style="margin-top:14px;display:grid;grid-template-columns:1fr 1fr 1fr;gap:14px;align-items:end;">
                <div class="form-group">
                    <label style="font-size:12px;">ADM Awal khusus (Rp)</label>
                    <input type="number" min="0" name="jalur_kaderisasi[adm]"
                           value="<?= e($formatPersen($adminJalur['kaderisasi']['adm'] ?? null)) ?>"
                           class="form-control">
                    <small style="font-size:11px;color:var(--text-light);line-height:1.55;">Default juknis: Rp 5.000.000</small>
                </div>
                <div class="form-group">
                    <label style="font-size:12px;">SPP Putra/bulan (Rp)</label>
                    <input type="number" min="0" name="jalur_kaderisasi[spp_l]"
                           value="<?= e($formatPersen($adminJalur['kaderisasi']['spp_l'] ?? null)) ?>"
                           class="form-control">
                    <small style="font-size:11px;color:var(--text-light);line-height:1.55;">Default juknis: Rp 650.000</small>
                </div>
                <div class="form-group">
                    <label style="font-size:12px;">SPP Putri/bulan (Rp)</label>
                    <input type="number" min="0" name="jalur_kaderisasi[spp_p]"
                           value="<?= e($formatPersen($adminJalur['kaderisasi']['spp_p'] ?? null)) ?>"
                           class="form-control">
                    <small style="font-size:11px;color:var(--text-light);line-height:1.55;">Default juknis: Rp 750.000</small>
                </div>
            </div>
        </fieldset>

        <!-- ALUMNI SDMUa -->
        <fieldset style="margin-bottom:22px;border:1px solid var(--cream-dark);border-radius:10px;padding:16px 18px;">
            <legend style="font-size:14px;font-weight:600;padding:0 8px;color:var(--text-mid);">Alumni SD Muhammadiyah Unggulan Ashidiq</legend>
            <div style="margin-top:14px;display:grid;grid-template-columns:1fr 180px;gap:14px;align-items:end;">
                <div class="form-group">
                    <label style="font-size:12px;">Potongan umum (persen) — jika diisi, akan memakai persen ini setelah jalur disetujui.</label>
                    <input type="number" min="0" max="100" step="0.5"
                           name="jalur_alumni_sdmua[potongan]"
                           value="<?= e($formatPersen($adminJalur['alumni-sdmua']['potongan'] ?? null)) ?>"
                           class="form-control">
                    <small style="font-size:11px;color:var(--text-light);line-height:1.55;">
                        Juknis: keringanan 25–50%. Kosongkan jika ingin menetapkan persen per-santri saat verifikasi.
                        Nilai ini dipakai sebagai estimasi awal di form PSB dan sebagai default di halaman verifikasi admin.
                    </small>
                </div>
            </div>
        </fieldset>

        <!-- DHUAFA -->
        <fieldset style="margin-bottom:22px;border:1px solid var(--cream-dark);border-radius:10px;padding:16px 18px;">
            <legend style="font-size:14px;font-weight:600;padding:0 8px;color:var(--text-mid);">Dhuafa / Beasiswa Empowerment</legend>
            <div style="margin-top:14px;display:grid;grid-template-columns:1fr 180px;gap:14px;align-items:end;">
                <div class="form-group">
                    <label style="font-size:12px;">Potongan umum (persen) — jika diisi, akan memakai persen ini setelah jalur disetujui.</label>
                    <input type="number" min="0" max="100" step="0.5"
                           name="jalur_dhuafa[potongan]"
                           value="<?= e($formatPersen($adminJalur['dhuafa']['potongan'] ?? null)) ?>"
                           class="form-control">
                    <small style="font-size:11px;color:var(--text-light);line-height:1.55;">
                        Juknis: keringanan 20–60%. Kosongkan jika ingin menetapkan persen per-santri saat verifikasi.
                        Nilai ini dipakai sebagai estimasi awal di form PSB dan sebagai default di halaman verifikasi admin.
                    </small>
                </div>
            </div>
            <div style="margin-top:12px;border-top:1px dashed rgba(255,255,255,0.12);padding-top:12px;">
                <label style="display:flex;align-items:center;gap:10px;cursor:pointer;font-size:12px;">
                    <input type="checkbox" name="jalur_dhuafa[bebas]" value="1"
                           style="width:auto;accent-color:var(--green-mid);margin-top:0;"
                           <?= !empty($adminJalur['dhuafa']['dhuafa_bebas']) ? 'checked' : '' ?>>
                    ADM Awal DHUAFA dibebaskan 100%
                </label>
                <small style="font-size:11px;color:var(--text-light);line-height:1.55;display:block;margin-top:6px;">
                    Jika dicentang, ADM awal dhuafa yang disetujui akan GRATIS.
                </small>
            </div>
        </fieldset>

        <div style="margin-top:6px;display:flex;justify-content:flex-end;">
            <button class="btn-sm btn-sm-primary">Simpan Pengaturan</button>
        </div>
    </form>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
