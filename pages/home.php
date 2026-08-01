<?php
$activePage      = 'home';
$pageTitle       = 'Pondok Pesantren Ash-Shiddiq | Tahfidz Al-Qur\'an Ciamis';
$pageDescription = 'Pondok Pesantren Ash-Shiddiq, lembaga pendidikan Islam berbasis Tahfidz Al-Qur\'an di Ciamis, Jawa Barat. Mencetak generasi hafidz berakhlak mulia sejak 2020. Daftar PSB 2025/2026 sekarang!';
$pageCanonical   = BASE_URL . '/';
$bodyClass       = 'page-home';

$extraHead = '<link rel="stylesheet" href="' . BASE_URL . '/assets/css/home.css?v=' . ASSET_VERSION . '">';

// Ambil info status PSB dari database
$psbStatus = 'buka';
$psbTahun  = '2025/2026';
try {
    $pdo  = getDB();
    $stmt = $pdo->query("SELECT key_name, value FROM pengaturan WHERE key_name IN ('psb_status','psb_tahun')");
    foreach ($stmt->fetchAll() as $row) {
        if ($row['key_name'] === 'psb_status') $psbStatus = $row['value'];
        if ($row['key_name'] === 'psb_tahun')  $psbTahun  = $row['value'];
    }
} catch (PDOException $e) {
    // Gunakan nilai default jika DB belum siap
}
$landingAlumni = [];
$galeriDb = [];
$mapSettings = ['map_latitude'=>'-7.325','map_longitude'=>'108.350','map_zoom'=>'15','kontak_alamat'=>'Ciamis, Jawa Barat'];
try {
    $landingAlumni = $pdo->query("SELECT * FROM alumni WHERE status='verified' AND tampil_landing=1 ORDER BY updated_at DESC LIMIT 6")->fetchAll();
    $testimoniDb = $pdo->query("SELECT nama,tahun_kelulusan,aktivitas,tempat_kuliah,jurusan,tempat_bekerja,jabatan,pesan_kesan,foto,orientasi FROM alumni WHERE status='verified' AND tampil_landing=1 AND foto<>'' ORDER BY updated_at DESC LIMIT 3")->fetchAll();
    $galeriDb = $pdo->query("SELECT nama_file,judul FROM foto_galeri WHERE is_aktif=1 ORDER BY urutan ASC, created_at DESC")->fetchAll();
    $mapStmt=$pdo->query("SELECT key_name,value FROM pengaturan WHERE key_name IN ('map_latitude','map_longitude','map_zoom','kontak_alamat')");
    foreach($mapStmt->fetchAll() as $row)$mapSettings[$row['key_name']]=$row['value'];
} catch (PDOException $e) {}
?>

<!-- GATE / SPLASH -->
<div id="gate">
    <div class="gate-overlay"></div>
    <div class="gate-geo">
        <svg width="100%" height="100%" viewBox="0 0 1200 800">
            <defs>
                <pattern id="hexPattern" x="0" y="0" width="80" height="92" patternUnits="userSpaceOnUse">
                    <polygon points="40,5 75,22 75,70 40,87 5,70 5,22" fill="none" stroke="#c9a227" stroke-width="1"/>
                </pattern>
            </defs>
            <rect width="100%" height="100%" fill="url(#hexPattern)"/>
        </svg>
    </div>
    <div class="door-left"><div class="door-pattern"></div><div class="door-arch"></div></div>
    <div class="door-right"><div class="door-pattern"></div><div class="door-arch"></div></div>
    <div class="gate-content gate-content-left">
        <div class="gate-arabic">بِسْمِ اللهِ الرَّحْمَنِ الرَّحِيْمِ</div>
        <div class="gate-ornament"></div>
        <div class="gate-title"><?= e(APP_NAME) ?></div>
        <div class="gate-subtitle">Berbasis Tahfidz Al-Qur'an</div>
        <button class="gate-enter-btn" type="button">Masuk →</button>
    </div>
    <div class="gate-content gate-content-right" aria-hidden="true">
        <div class="gate-arabic">بِسْمِ اللهِ الرَّحْمَنِ الرَّحِيْمِ</div>
        <div class="gate-ornament"></div>
        <div class="gate-title"><?= e(APP_NAME) ?></div>
        <div class="gate-subtitle">Berbasis Tahfidz Al-Qur'an</div>
        <button class="gate-enter-btn" type="button" tabindex="-1">Masuk →</button>
    </div>
</div>

<!-- HERO -->
<section id="hero" aria-label="Halaman utama">
    <div class="hero-bg<?= imgExists('hero-bg.jpg') ? ' has-img' : '' ?>"></div>
    <div class="hero-pattern"></div>
    <div class="hero-content">
        <div class="hero-arabic" aria-label="Wa rattilil-Qur'ana tartila">وَرَتِّلِ الْقُرْآنَ تَرْتِيلًا</div>
        <div class="hero-ornament" aria-hidden="true">
            <span></span>
            <div class="hero-ornament-diamond"></div>
            <span></span>
        </div>
        <h1 class="hero-title">Mencetak Hafidz<br><em>Berakhlak Mulia</em></h1>
        <p class="hero-subtitle">Tahfidz Al-Qur'an · Ilmu Agama · Akhlakul Karimah</p>
        <div class="hero-buttons">
            <a href="<?= BASE_URL ?>/psb" class="btn-primary">Daftar Santri Baru</a>
            <a href="<?= BASE_URL ?>/profil" class="btn-outline">Kenali Kami</a>
        </div>
    </div>
    <div class="hero-scroll" aria-hidden="true">
        <span>Scroll</span>
        <div class="scroll-line"></div>
    </div>
</section>

<!-- SAMBUTAN MUDIR -->
<section id="sambutan" aria-labelledby="sambutan-heading">
    <div class="sambutan-inner">
        <div class="mudir-photo-wrap reveal-left">
            <div class="mudir-photo">
                <?php
                // Mendukung mudir.png dan mudir.jpg (rasio 3:4, disarankan 600x800px)
                $mudirPhoto = imgExists('mudir.png') ? 'mudir.png' : 'mudir.jpg';
                echo imgOrPlaceholder(
                    $mudirPhoto,
                    'KH. Suroto Abu Nizam, M. Pd. — Mudir Pondok Pesantren Ash-Shiddiq',
                    '<div class="mudir-photo-placeholder">
                        <svg width="60" height="60" viewBox="0 0 60 60" fill="none" stroke="#8a8a7a" stroke-width="1.5" aria-hidden="true">
                            <circle cx="30" cy="20" r="10"/>
                            <path d="M10 50 Q10 36 30 36 Q50 36 50 50"/>
                        </svg>
                        <p>Foto Mudir Pesantren</p>
                    </div>'
                );
                ?>
            </div>
            <div class="mudir-frame" aria-hidden="true"></div>
            <div class="mudir-badge">
                <div class="mudir-badge-title">الشيخ</div>
                <div class="mudir-badge-name">KH. Suroto Abu Nizam, M. Pd.</div>
            </div>
        </div>

        <div class="sambutan-text reveal-right">
            <div class="section-tag" aria-hidden="true">
                <span></span>
                <span class="section-tag-text">Sambutan Mudir</span>
                <span></span>
            </div>
            <h2 class="section-title" id="sambutan-heading">Pesan dari<br>Pimpinan Pesantren</h2>
            <p class="arabic-verse" aria-label="Man hafizal-Qur'ana faqad istadrajannubuwwata">مَنْ حَفِظَ الْقُرْآنَ فَقَدِ اسْتَدْرَجَ النُّبُوَّةَ بَيْنَ جَنْبَيْهِ</p>
            <p class="verse-source">— Riwayat Hakim</p>
            <p class="sambutan-body">
                Bismillahirrahmanirrahim. Dengan sepenuh hati kami menyambut putra-putri terbaik bangsa untuk bersama-sama mengarungi samudra ilmu di Pondok Pesantren Ash-Shiddiq. Di sini, Al-Qur'an adalah nafas kehidupan kami.
            </p>
            <p class="sambutan-body">
                Kami hadir untuk mencetak generasi yang tidak hanya hafal 30 juz, namun juga memahami, mengamalkan, dan menyebarkan nilai-nilai Al-Qur'an dalam kehidupan sehari-hari. Mari titipkan putra-putri Anda kepada kami.
            </p>
            <div class="mudir-signature">
                <span class="mudir-sig-name">KH. Suroto Abu Nizam, M. Pd.</span>
                <span class="mudir-sig-title">Mudir Pondok Pesantren Ash-Shiddiq</span>
            </div>
        </div>
    </div>
</section>

<!-- KEUNGGULAN -->
<section id="keunggulan" aria-labelledby="keunggulan-heading">
    <div class="keunggulan-header reveal">
        <div class="section-tag" style="justify-content:center" aria-hidden="true">
            <span></span><span class="section-tag-text">Keunggulan Kami</span><span></span>
        </div>
        <h2 class="section-title" id="keunggulan-heading">Mengapa Ash-Shiddiq?</h2>
        <p class="section-desc" style="margin:0 auto;text-align:center">Kami hadir dengan sistem pendidikan terpadu yang mengintegrasikan hafalan Al-Qur'an dengan ilmu pengetahuan modern.</p>
    </div>
    <div class="keunggulan-grid">
        <?php
        $keunggulan = [
            ['num'=>'01','title'=>'Program Tahfidz Intensif',        'desc'=>'Metode hafalan Al-Qur\'an 30 juz dengan pendampingan ustadz hafidz berpengalaman dan sistem murajaah terstruktur setiap hari.', 'icon'=>'<path d="M12 2l2.4 7.4H22l-6.2 4.5 2.4 7.4L12 17l-6.2 4.3 2.4-7.4L2 9.4h7.6z"/>'],
            ['num'=>'02','title'=>'Pendidikan Formal Terintegrasi',  'desc'=>'Kurikulum Kemenag RI terintegrasi dalam satu lingkungan pesantren yang kondusif tanpa mengorbankan kualitas hafalan.', 'icon'=>'<path d="M22 10v6M2 10l10-5 10 5-10 5z"/><path d="M6 12v5c3 3 9 3 12 0v-5"/>'],
            ['num'=>'03','title'=>'Pembinaan Akhlak Karimah',        'desc'=>'Pembentukan karakter Islami melalui kegiatan harian yang terprogram: shalat berjamaah, kajian kitab, dan riyadhah ruhiyah.', 'icon'=>'<circle cx="12" cy="8" r="5"/><path d="M3 21v-1a9 9 0 0 1 18 0v1"/>'],
            ['num'=>'04','title'=>'Fasilitas Modern & Nyaman',       'desc'=>'Asrama bersih dan terstruktur, masjid representatif, perpustakaan lengkap, dan ruang belajar yang kondusif untuk menghafal.', 'icon'=>'<rect x="3" y="3" width="18" height="18" rx="2"/><path d="M3 9h18M9 21V9"/>'],
            ['num'=>'05','title'=>'Pengajar Bersanad & Kompeten',    'desc'=>'Tenaga pengajar bersanad Al-Qur\'an, lulusan universitas Islam dalam dan luar negeri yang berdedikasi tinggi.', 'icon'=>'<path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/>'],
            ['num'=>'06','title'=>'Lingkungan Aman & Terpantau',     'desc'=>'Pengawasan 24 jam oleh musyrif dan musyrifah berpengalaman dengan sistem keamanan pesantren yang ketat dan terukur.', 'icon'=>'<path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/>'],
        ];
        foreach ($keunggulan as $i => $item):
            $delay = 'reveal-delay-' . (($i % 3) + 1);
        ?>
        <div class="keunggulan-card reveal <?= $delay ?>">
            <div class="keunggulan-num"><?= $item['num'] ?></div>
            <div class="keunggulan-icon">
                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true"><?= $item['icon'] ?></svg>
            </div>
            <h3 class="keunggulan-title"><?= e($item['title']) ?></h3>
            <p class="keunggulan-desc"><?= e($item['desc']) ?></p>
        </div>
        <?php endforeach; ?>
    </div>
</section>

<!-- PROGRAM -->
<section id="program" aria-labelledby="program-heading">
    <div class="program-inner">
        <div class="program-header reveal">
            <div class="section-tag" aria-hidden="true">
                <span style="background:rgba(201,162,39,0.5)"></span>
                <span class="section-tag-text">Program Unggulan</span>
                <span style="background:rgba(201,162,39,0.5)"></span>
            </div>
            <h2 class="section-title" id="program-heading">Program Pendidikan<br>Kami</h2>
            <p class="section-desc">Dirancang untuk membentuk generasi Qur'ani yang siap menghadapi tantangan zaman.</p>
        </div>
        <div class="program-grid">
            <?php
            $programs = [
                ['title'=>'Tahfidz Al-Qur\'an (30 Juz)', 'desc'=>'Program inti menghafal seluruh Al-Qur\'an 30 juz dengan metode talaqqi dan musyafahah langsung kepada guru bersanad.', 'icon'=>'<path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20"/><path d="M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2z"/>'],
                ['title'=>'Tahsin & Ilmu Tajwid',         'desc'=>'Perbaikan bacaan Al-Qur\'an secara intensif dengan kaidah tajwid yang benar sebelum dan selama proses menghafal.', 'icon'=>'<circle cx="12" cy="12" r="10"/><path d="M12 6v6l4 2"/>'],
                ['title'=>'Kajian Kitab Kuning',           'desc'=>'Mempelajari kitab-kitab klasik Islam: Fiqih, Aqidah, Hadits, Nahwu, Sharaf, dan disiplin ilmu Islam lainnya.', 'icon'=>'<path d="M2 3h6a4 4 0 0 1 4 4v14a3 3 0 0 0-3-3H2z"/><path d="M22 3h-6a4 4 0 0 0-4 4v14a3 3 0 0 1 3-3h7z"/>'],
                ['title'=>'Pendidikan Formal (SMP/SMA)', 'desc'=>'Kurikulum pendidikan formal jenjang SMP dan SMA terakreditasi yang terintegrasi dalam sistem pesantren.', 'icon'=>'<rect x="5" y="2" width="14" height="20" rx="2"/><path d="M9 7h6M9 11h6M9 15h4"/>'],
            ];
            foreach ($programs as $i => $prog):
                $delay = 'reveal-delay-' . ($i + 1);
            ?>
            <div class="program-card reveal <?= $delay ?>">
                <div class="program-icon">
                    <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="#c9a227" stroke-width="1.5" aria-hidden="true"><?= $prog['icon'] ?></svg>
                </div>
                <div class="program-body">
                    <h3><?= e($prog['title']) ?></h3>
                    <p><?= e($prog['desc']) ?></p>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<!-- STATISTIK -->
<section id="statistik" aria-label="Statistik pesantren">
    <div class="stat-inner">
        <?php
        $stats = [
            ['num'=>500,'suffix'=>'+','label'=>'Santri Aktif'],
            ['num'=>300,'suffix'=>'+','label'=>'Alumni Hafidz'],
            ['num'=>15, 'suffix'=>'+','label'=>'Tahun Berdiri'],
            ['num'=>40, 'suffix'=>'+','label'=>'Tenaga Pengajar'],
        ];
        foreach ($stats as $i => $stat):
        ?>
        <div class="stat-item reveal reveal-delay-<?= $i+1 ?>">
            <div class="stat-num">
                <span data-counter="<?= $stat['num'] ?>" data-suffix="<?= $stat['suffix'] ?>"><?= $stat['num'] . $stat['suffix'] ?></span>
            </div>
            <div class="stat-label"><?= e($stat['label']) ?></div>
        </div>
        <?php endforeach; ?>
    </div>
</section>

<!-- KEHIDUPAN SANTRI -->
<section id="kehidupan" aria-labelledby="kehidupan-heading">
    <div class="kehidupan-header reveal">
        <div class="section-tag" style="justify-content:center" aria-hidden="true">
            <span></span><span class="section-tag-text">Kehidupan Santri</span><span></span>
        </div>
        <h2 class="section-title" id="kehidupan-heading">Sehari-hari Bersama<br>Al-Qur'an</h2>
    </div>
    <div class="kehidupan-carousel" data-carousel>
      <div class="kehidupan-track">
        <?php
        // GAMBAR: letakkan di assets/img/galeri/ — rasio 16:10 (misal 1200x750px)
        // File pertama tampil lebih besar (span 2 kolom) → pakai gambar terbaik/landscape
        $galeri = [
            ['label'=>'Halaqah Tahfidz Pagi', 'file'=>'galeri/halaqah-tahfidz.jpg', 'bg'=>'linear-gradient(135deg,#c8d8c4,#b0c4b0)'],
            ['label'=>'Shalat Berjamaah',      'file'=>'galeri/shalat-berjamaah.jpg', 'bg'=>'linear-gradient(135deg,#d4e0d0,#b8cbb8)'],
            ['label'=>'Asrama Santri',          'file'=>'galeri/asrama-santri.jpg',   'bg'=>'linear-gradient(135deg,#c8d0e4,#b0bcd4)'],
            ['label'=>'Ekstrakurikuler',        'file'=>'galeri/ekstrakurikuler.jpg', 'bg'=>'linear-gradient(135deg,#e4d8c8,#d0c0a8)'],
            ['label'=>'Wisuda Hafidz',          'file'=>'galeri/wisuda-hafidz.jpg',   'bg'=>'linear-gradient(135deg,#d8e4c8,#c0d0a8)'],
        ];
        if ($galeriDb) $galeri = array_map(fn($row) => ['label'=>$row['judul'] ?: 'Kehidupan Santri', 'url'=>BASE_URL.'/uploads/galeri/'.$row['nama_file']], $galeriDb);
        foreach ($galeri as $i => $item):
            $delay = $i > 0 ? ' reveal-delay-' . $i : '';
        ?>
        <div class="kh-card reveal<?= $delay ?>" tabindex="0" role="button" aria-label="Perbesar <?=e($item['label'])?>">
            <div class="kh-img"<?=isset($item['bg'])?' style="background:'.e($item['bg']).'"':''?>>
                <?php
                if (isset($item['url'])) echo '<img src="'.e($item['url']).'" alt="'.e($item['label']).'" loading="lazy">';
                else echo imgOrPlaceholder($item['file'], $item['label'] . ' — Pondok Pesantren Ash-Shiddiq', '<p>' . e($item['label']) . '</p>');
                ?>
            </div>
            <div class="kh-overlay">
                <span class="kh-overlay-text"><?= e($item['label']) ?></span>
            </div>
        </div>
        <?php endforeach; ?>
      </div>
      <button class="carousel-btn carousel-prev" type="button" aria-label="Foto sebelumnya">‹</button>
      <button class="carousel-btn carousel-next" type="button" aria-label="Foto berikutnya">›</button>
      <div class="carousel-dots" aria-label="Navigasi galeri"></div>
    </div>
    <div class="gallery-lightbox" id="galleryLightbox" aria-hidden="true" role="dialog" aria-modal="true" aria-label="Tampilan foto galeri">
      <button class="lightbox-close" type="button" aria-label="Tutup">×</button>
      <button class="lightbox-nav lightbox-prev" type="button" aria-label="Foto sebelumnya">‹</button>
      <figure><img src="" alt=""><figcaption></figcaption></figure>
      <button class="lightbox-nav lightbox-next" type="button" aria-label="Foto berikutnya">›</button>
    </div>
</section>

<?php if ($testimoniDb): ?>
<!-- TESTIMONI -->
<section id="testimoni" aria-labelledby="testimoni-heading">
    <div class="testi-header reveal">
        <div class="section-tag" style="justify-content:center" aria-hidden="true">
            <span></span><span class="section-tag-text">Testimoni</span><span></span>
        </div>
        <h2 class="section-title" id="testimoni-heading">Kata Mereka</h2>
    </div>
    <div class="testi-grid">
        <?php foreach ($testimoniDb as $i => $t):
            $orientasi = ($t['orientasi'] ?? 'landscape') === 'portrait' ? 'portrait' : 'landscape';
            $aktivitas = $t['aktivitas'] === 'kuliah'
                ? 'Alumni · ' . e((string)$t['tahun_kelulusan']) . ' · ' . e($t['jurusan'])
                : 'Alumni · ' . e((string)$t['tahun_kelulusan']) . ' · ' . e($t['jabatan']);
        ?>
        <div class="testi-card reveal reveal-delay-<?= $i+1 ?>">
            <span class="testi-quote" aria-hidden="true">"</span>
            <p class="testi-text"><?= e($t['pesan_kesan']) ?></p>
            <div class="testi-author">
                <div class="testi-avatar <?= $orientasi ?>">
                    <img src="<?= e(BASE_URL . '/uploads/alumni/' . $t['foto']) ?>" alt="Foto <?= e($t['nama']) ?>">
                </div>
                <div>
                    <p class="testi-name"><?= e($t['nama']) ?></p>
                    <p class="testi-role"><?= $aktivitas ?></p>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
</section>
<?php endif; ?>

<?php if($landingAlumni): ?>
<section id="alumni-landing" class="alumni-landing" aria-labelledby="alumni-heading"><div class="container"><div class="section-tag" style="justify-content:center"><span></span><span class="section-tag-text">Jejak Alumni</span><span></span></div><h2 class="section-title" id="alumni-heading">Dari Ash-Shiddiq, Melangkah Lebih Jauh</h2><div class="landing-alumni-grid"><?php foreach($landingAlumni as $a):?><article class="landing-alumni-card reveal"><img src="<?=e(BASE_URL.'/uploads/alumni/'.$a['foto'])?>" alt="<?=e($a['nama'])?>" loading="lazy"><div><p class="alumni-quote">“<?=e($a['pesan_kesan'])?>”</p><h3><?=e($a['nama'])?></h3><small>Alumni <?=e((string)$a['tahun_kelulusan'])?> · <?=e($a['aktivitas']==='kuliah'?$a['tempat_kuliah'].' / '.$a['jurusan']:$a['tempat_bekerja'].' / '.$a['jabatan'])?></small></div></article><?php endforeach;?></div><div style="text-align:center;margin-top:30px"><a class="btn-outline" href="<?=BASE_URL?>/alumni">Lihat Semua Profil Alumni</a></div></div></section>
<?php endif; ?>

<section id="lokasi" class="map-section"><div class="map-copy"><div class="section-tag"><span></span><span class="section-tag-text">Lokasi Pesantren</span><span></span></div><h2 class="section-title">Kunjungi Ash-Shiddiq</h2><p><?=e($mapSettings['kontak_alamat'])?></p><a class="btn-primary" target="_blank" rel="noopener" href="https://www.google.com/maps?q=<?=eUrl($mapSettings['map_latitude'].','.$mapSettings['map_longitude'])?>">Buka di Google Maps</a></div><iframe title="Peta lokasi Pondok Pesantren Ash-Shiddiq" loading="lazy" allowfullscreen referrerpolicy="no-referrer-when-downgrade" src="https://www.google.com/maps?q=<?=eUrl($mapSettings['map_latitude'].','.$mapSettings['map_longitude'])?>&amp;z=<?=e((string)(int)$mapSettings['map_zoom'])?>&amp;output=embed"></iframe></section>

<!-- CTA DAFTAR -->
<section id="daftar" aria-labelledby="daftar-heading">
    <div class="daftar-inner">
        <div class="daftar-arabic reveal" aria-label="Halumma ilal-Qur'an">هَلُمَّ إِلَى الْقُرْآنِ</div>
        <h2 class="daftar-title reveal" id="daftar-heading">Daftarkan Putra-Putri<br>Anda Sekarang</h2>
        <p class="daftar-desc reveal">
            Bergabunglah bersama ratusan santri yang telah merasakan indahnya hidup bersama Al-Qur'an.
            Penerimaan Santri Baru (PSB) tahun ajaran <?= e($psbTahun) ?>
            <?= $psbStatus === 'buka' ? 'kini <strong>telah dibuka</strong>.' : 'segera dibuka.' ?>
        </p>
        <div class="daftar-buttons reveal">
            <a href="<?= BASE_URL ?>/psb" class="btn-primary">Daftar Online Sekarang</a>
            <a href="<?= BASE_URL ?>/profil" class="btn-outline">Informasi Lengkap</a>
        </div>
        <div class="daftar-period reveal">
            <div class="period-item">
                <strong>Mei 2025</strong>
                <span>Buka Pendaftaran</span>
            </div>
            <div class="period-item">
                <strong>Juni 2025</strong>
                <span>Seleksi &amp; Tes</span>
            </div>
            <div class="period-item">
                <strong>Juli 2025</strong>
                <span>Pengumuman</span>
            </div>
        </div>
    </div>
</section>

<?php
$extraScripts = <<<'JS'
<script>
(function(){
  // Gate
  var gate    = document.getElementById('gate');
  var gateBtns = gate.querySelectorAll('.gate-enter-btn');
  if (!gate) return;

  document.body.style.overflow = 'hidden';

  var gateIsOpening = false;
  function openGate() {
    if (gateIsOpening) return;
    gateIsOpening = true;
    gate.classList.add('leaving');
    setTimeout(function(){ gate.classList.add('opening'); }, 520);
    setTimeout(function(){ gate.classList.add('gone'); document.body.style.overflow=''; }, 1950);
  }

  gateBtns.forEach(function(gateBtn){
    gateBtn.addEventListener('click', function(){ clearTimeout(autoTimer); openGate(); });
  });
  var autoTimer = setTimeout(openGate, 4000);

  // 3D tilt cards
  function initTilt(sel) {
    document.querySelectorAll(sel).forEach(function(card){
      card.classList.add('tilt-card');
      card.addEventListener('mousemove', function(e){
        var r = card.getBoundingClientRect();
        var x = (e.clientX - r.left) / r.width  - 0.5;
        var y = (e.clientY - r.top)  / r.height - 0.5;
        card.style.transform   = 'perspective(700px) rotateY('+(x*12)+'deg) rotateX('+(-y*10)+'deg) translateZ(8px)';
        card.style.boxShadow   = (-x*14)+'px '+(y*14)+'px 36px rgba(13,122,74,0.14)';
      });
      card.addEventListener('mouseleave', function(){
        card.style.transform = '';
        card.style.boxShadow = '';
      });
    });
  }
  initTilt('.keunggulan-card');
  initTilt('.stat-item');
  initTilt('.program-card');

  // Carousel galeri kehidupan santri
  document.querySelectorAll('[data-carousel]').forEach(function(carousel){
    var track = carousel.querySelector('.kehidupan-track');
    var cards = Array.from(track.querySelectorAll('.kh-card'));
    var dots = carousel.querySelector('.carousel-dots');
    if (!cards.length) return;
    cards.forEach(function(card, i){
      var dot = document.createElement('button'); dot.type='button'; dot.setAttribute('aria-label','Lihat foto '+(i+1));
      dot.addEventListener('click', function(){ card.scrollIntoView({behavior:'smooth',block:'nearest',inline:'start'}); }); dots.appendChild(dot);
    });
    function step(direction){ track.scrollBy({left: direction * Math.max(280, track.clientWidth * .82), behavior:'smooth'}); }
    carousel.querySelector('.carousel-prev').addEventListener('click', function(){ step(-1); });
    carousel.querySelector('.carousel-next').addEventListener('click', function(){ step(1); });
    function updateDots(){
      var closest=0, distance=Infinity;
      cards.forEach(function(card,i){ var d=Math.abs(card.offsetLeft-track.scrollLeft); if(d<distance){distance=d;closest=i;} });
      dots.querySelectorAll('button').forEach(function(dot,i){ dot.classList.toggle('active',i===closest); });
    }
    track.addEventListener('scroll', updateDots, {passive:true}); updateDots();
    if (cards.length > 1 && !window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
      setInterval(function(){ var atEnd=track.scrollLeft+track.clientWidth>=track.scrollWidth-5; atEnd?track.scrollTo({left:0,behavior:'smooth'}):step(1); }, 5000);
    }
    var lightbox=document.getElementById('galleryLightbox'), lightImg=lightbox.querySelector('img'), caption=lightbox.querySelector('figcaption'), current=0;
    function show(index){
      var img=cards[index].querySelector('.kh-img img'); if(!img)return;
      current=index; lightImg.src=img.src; lightImg.alt=img.alt; caption.textContent=cards[index].querySelector('.kh-overlay-text').textContent;
      lightbox.classList.add('open'); lightbox.setAttribute('aria-hidden','false'); document.body.style.overflow='hidden';
    }
    function close(){ lightbox.classList.remove('open'); lightbox.setAttribute('aria-hidden','true'); document.body.style.overflow=''; }
    function move(dir){ show((current+dir+cards.length)%cards.length); }
    cards.forEach(function(card,i){ card.addEventListener('click',function(){show(i)}); card.addEventListener('keydown',function(e){if(e.key==='Enter'||e.key===' '){e.preventDefault();show(i)}}); });
    lightbox.querySelector('.lightbox-close').addEventListener('click',close);
    lightbox.querySelector('.lightbox-prev').addEventListener('click',function(){move(-1)});
    lightbox.querySelector('.lightbox-next').addEventListener('click',function(){move(1)});
    lightbox.addEventListener('click',function(e){if(e.target===lightbox)close()});
    var touchX=0; lightbox.addEventListener('touchstart',function(e){touchX=e.changedTouches[0].clientX},{passive:true});
    lightbox.addEventListener('touchend',function(e){var d=e.changedTouches[0].clientX-touchX;if(Math.abs(d)>50)move(d>0?-1:1)},{passive:true});
    document.addEventListener('keydown',function(e){if(!lightbox.classList.contains('open'))return;if(e.key==='Escape')close();if(e.key==='ArrowLeft')move(-1);if(e.key==='ArrowRight')move(1)});
  });

  // Parallax hero
  var heroBg = document.querySelector('.hero-bg');
  var heroPat = document.querySelector('.hero-pattern');
  window.addEventListener('scroll', function(){
    var s = window.scrollY;
    if (heroBg)  heroBg.style.transform  = 'translateY('+(s*0.35)+'px)';
    if (heroPat) heroPat.style.transform = 'translateY('+(s*0.18)+'px)';
  }, {passive:true});

})();
</script>
JS;
?>
