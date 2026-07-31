<?php
$activePage      = 'profil';
$pageTitle       = 'Profil Pesantren | ' . APP_NAME;
$pageDescription = 'Profil lengkap Pondok Pesantren Ash-Shiddiq: identitas, sejarah sejak 2020, visi misi, struktur organisasi, tenaga pengajar, dan fasilitas pesantren di Ciamis, Jawa Barat.';
$pageCanonical   = BASE_URL . '/profil';

$extraHead = <<<'CSS'
<style>
.identitas-grid { max-width:1100px; margin:0 auto; padding:80px 5%; display:grid; grid-template-columns:1fr 1fr; gap:60px; align-items:start; }
.identitas-table { width:100%; border-collapse:collapse; margin-top:28px; }
.identitas-table tr { border-bottom:1px solid var(--cream-dark); }
.identitas-table tr:last-child { border-bottom:none; }
.identitas-table td { padding:13px 0; font-size:14px; vertical-align:top; }
.identitas-table td:first-child { color:var(--text-light); width:45%; padding-right:16px; }
.identitas-table td:last-child  { color:var(--text-dark); font-weight:500; }
.profil-photo { width:100%; aspect-ratio:4/3; background:linear-gradient(135deg,#c8ead8,#a0d4b8); border-radius:4px; display:flex; flex-direction:column; align-items:center; justify-content:center; gap:12px; position:relative; overflow:hidden; }
.profil-photo > img { width:100%; height:100%; object-fit:cover; display:block; position:relative; z-index:0; }
.profil-photo::before { content:''; position:absolute; inset:0; background:linear-gradient(to top,rgba(13,122,74,0.3),transparent); }
.profil-photo p { font-family:'Courier New',monospace; font-size:11px; color:rgba(0,0,0,0.25); letter-spacing:1px; position:relative; z-index:1; }
.profil-photo-caption { position:absolute; bottom:16px; left:16px; right:16px; font-size:13px; color:white; font-style:italic; text-align:center; opacity:0.8; z-index:1; }
.visi-section { background:var(--green-deep); padding:80px 5%; }
.visi-inner { max-width:1100px; margin:0 auto; }
.visi-inner .section-title { color:white; }
.visi-card { background:rgba(255,255,255,0.07); border:1px solid rgba(255,255,255,0.12); border-radius:4px; padding:36px 32px; margin-bottom:24px; position:relative; overflow:hidden; }
.visi-card::before { content:''; position:absolute; left:0; top:0; bottom:0; width:4px; background:var(--gold); }
.visi-card h3 { font-family:'Plus Jakarta Sans',sans-serif; font-size:22px; color:var(--gold); margin-bottom:14px; }
.visi-card p  { font-size:15px; line-height:1.8; color:rgba(255,255,255,0.75); }
.misi-list { list-style:none; margin-top:24px; }
.misi-list li { display:flex; gap:16px; padding:14px 0; border-bottom:1px solid rgba(255,255,255,0.08); align-items:flex-start; }
.misi-list li:last-child { border-bottom:none; }
.misi-num { width:32px; height:32px; flex-shrink:0; background:rgba(232,168,32,0.15); border:1px solid var(--gold); border-radius:50%; display:flex; align-items:center; justify-content:center; font-family:'Plus Jakarta Sans',sans-serif; font-size:13px; color:var(--gold); }
.misi-text { font-size:14px; line-height:1.7; color:rgba(255,255,255,0.7); padding-top:6px; }
.struktur-section { padding:80px 5%; max-width:1100px; margin:0 auto; }
.org-chart { display:flex; flex-direction:column; align-items:center; margin-top:48px; }
.org-level { display:flex; gap:20px; justify-content:center; }
.org-connector-v { width:1px; height:32px; background:var(--cream-dark); margin:0 auto; }
.org-card { background:var(--white); border-radius:4px; padding:18px 20px; text-align:center; min-width:160px; border-top:3px solid var(--green-mid); box-shadow:0 4px 16px rgba(0,0,0,0.06); }
.org-card.head { border-top-color:var(--gold); min-width:220px; }
.org-name { font-family:'Plus Jakarta Sans',sans-serif; font-size:15px; color:var(--text-dark); margin-bottom:4px; }
.org-card.head .org-name { font-size:17px; }
.org-pos  { font-size:11px; color:var(--text-light); letter-spacing:1px; text-transform:uppercase; }
.org-avatar { width:52px; height:52px; border-radius:50%; background:var(--cream-dark); margin:0 auto 12px; display:flex; align-items:center; justify-content:center; font-family:'Plus Jakarta Sans',sans-serif; font-size:20px; color:var(--green-deep); overflow:hidden; }
.org-avatar > img { width:100%; height:100%; object-fit:cover; display:block; border-radius:50%; }
.org-card.head .org-avatar { width:64px; height:64px; font-size:24px; background:rgba(232,168,32,0.15); color:var(--gold); }
.pengajar-section { background:var(--cream); padding:80px 5%; }
.pengajar-inner { max-width:1100px; margin:0 auto; }
.pengajar-grid { display:grid; grid-template-columns:repeat(4,1fr); gap:20px; margin-top:48px; }
.pengajar-card { background:var(--white); border-radius:4px; padding:28px 20px; text-align:center; transition:transform 0.3s,box-shadow 0.3s; }
.pengajar-card:hover { transform:translateY(-6px); box-shadow:0 16px 40px rgba(13,122,74,0.1); }
.pengajar-photo { width:80px; height:80px; border-radius:50%; background:linear-gradient(135deg,#c8ead8,#90cca8); margin:0 auto 16px; display:flex; align-items:center; justify-content:center; font-family:'Plus Jakarta Sans',sans-serif; font-size:28px; color:var(--green-deep); border:3px solid var(--cream-dark); overflow:hidden; }
.pengajar-photo > img { width:100%; height:100%; object-fit:cover; display:block; border-radius:50%; }
.pengajar-name  { font-family:'Plus Jakarta Sans',sans-serif; font-size:16px; margin-bottom:6px; }
.pengajar-title { font-size:12px; color:var(--green-mid); font-weight:600; letter-spacing:0.5px; margin-bottom:4px; }
.pengajar-desc  { font-size:12px; color:var(--text-light); line-height:1.5; }
.fasilitas-section { padding:80px 5%; max-width:1100px; margin:0 auto; }
.fasilitas-grid { display:grid; grid-template-columns:repeat(3,1fr); gap:16px; margin-top:48px; }
.fas-card { background:var(--white); border-radius:4px; overflow:hidden; transition:transform 0.3s; }
.fas-card:hover { transform:translateY(-4px); box-shadow:0 12px 32px rgba(13,122,74,0.1); }
.fas-img  { height:160px; background:linear-gradient(135deg,#d4f0e0,#b0dcc0); display:flex; align-items:center; justify-content:center; flex-direction:column; gap:8px; overflow:hidden; }
.fas-img > img { width:100%; height:100%; object-fit:cover; display:block; }
.fas-img p { font-family:'Courier New',monospace; font-size:10px; color:rgba(0,0,0,0.25); letter-spacing:1px; }
.fas-body { padding:18px 20px; }
.fas-name { font-family:'Plus Jakarta Sans',sans-serif; font-size:16px; margin-bottom:6px; }
.fas-desc { font-size:13px; color:var(--text-mid); line-height:1.6; }
.cta-strip { background:var(--gold); padding:50px 5%; text-align:center; }
.cta-strip h2 { font-family:'Plus Jakarta Sans',sans-serif; font-size:28px; color:var(--text-dark); margin-bottom:8px; }
.cta-strip p  { font-size:15px; color:rgba(0,0,0,0.6); margin-bottom:24px; }
.sejarah-timeline { position:relative; padding-left:28px; border-left:2px solid var(--cream-dark); }
.sejarah-item { margin-bottom:32px; position:relative; }
.sejarah-dot { position:absolute; left:-37px; top:4px; width:16px; height:16px; border-radius:50%; border:3px solid white; }
.sejarah-year { font-size:11px; font-weight:700; letter-spacing:2px; margin-bottom:6px; }
.sejarah-judul { font-weight:600; color:var(--text-dark); margin-bottom:6px; }
.sejarah-desc { font-size:14px; color:var(--text-mid); line-height:1.7; }
@media(max-width:900px){ .identitas-grid{grid-template-columns:1fr} .pengajar-grid{grid-template-columns:repeat(2,1fr)} .fasilitas-grid{grid-template-columns:repeat(2,1fr)} }
@media(max-width:600px){ .fasilitas-grid{grid-template-columns:1fr} .org-level{flex-direction:column;align-items:center} .visi-inner>div{grid-template-columns:1fr!important} }
</style>
CSS;
?>

<!-- PAGE HERO -->
<div class="page-hero" role="banner">
    <div class="page-hero-pattern" aria-hidden="true"></div>
    <div class="page-hero-content">
        <div class="page-hero-tag" aria-hidden="true"><span></span>Tentang Kami<span></span></div>
        <h1>Profil Pesantren</h1>
        <p>Mengenal lebih dalam Pondok Pesantren Ash-Shiddiq — sejarah, visi, misi, dan keluarga besar kami.</p>
    </div>
</div>

<!-- IDENTITAS -->
<div class="identitas-grid" id="identitas">
    <div class="reveal-left">
        <div class="section-tag" aria-hidden="true"><span></span><span class="section-tag-text">Identitas</span><span></span></div>
        <h2 class="section-title">Identitas<br>Pesantren</h2>
        <p class="section-desc">Berdiri di atas nilai-nilai kejujuran dan keikhlasan seperti yang dicontohkan oleh Ash-Shiddiq, sahabat mulia Rasulullah SAW.</p>
        <table class="identitas-table">
            <tbody>
                <tr><td>Nama Resmi</td><td><?= e(APP_NAME) ?></td></tr>
                <tr><td>Nomor Statistik</td><td>510032080019</td></tr>
                <tr><td>Status</td><td>Terdaftar – Kemenag RI</td></tr>
                <tr><td>Tahun Berdiri</td><td>2020</td></tr>
                <tr><td>Jenjang</td><td>SMP &amp; SMA</td></tr>
                <tr><td>Program Unggulan</td><td>Tahfidz Al-Qur'an 30 Juz</td></tr>
                <tr><td>Akreditasi</td><td>A (SMP) &middot; B (SMA)</td></tr>
                <tr><td>Alamat</td><td>Jl. Pesantren No. 1, Kab. Ciamis, Jawa Barat 46261</td></tr>
                <tr><td>Website</td><td><a href="https://ponpesashiddiq.or.id">ponpesashiddiq.or.id</a></td></tr>
                <tr><td>Email</td><td><a href="mailto:info@ponpesashiddiq.or.id">info@ponpesashiddiq.or.id</a></td></tr>
            </tbody>
        </table>
    </div>
    <div class="reveal-right">
        <div class="profil-photo">
            <?php
            // GAMBAR: assets/img/profil/gedung-pesantren.jpg (rasio 4:3, ~1200x900)
            echo imgOrPlaceholder(
                'profil/gedung-pesantren.jpg',
                'Gedung Pondok Pesantren Ash-Shiddiq',
                '<svg width="64" height="64" viewBox="0 0 64 64" fill="none" opacity="0.3" aria-hidden="true"><rect x="8" y="16" width="48" height="38" rx="2" stroke="#0d7a4a" stroke-width="1.5"/><path d="M20 28 Q32 20 44 28" stroke="#0d7a4a" stroke-width="1.5" fill="none"/><circle cx="32" cy="35" r="8" stroke="#0d7a4a" stroke-width="1.5"/></svg>
                <p>Foto Gedung Pesantren</p>
                <div class="profil-photo-caption">"Rumah para penghafal Al-Qur\'an"</div>'
            );
            ?>
        </div>
        <div style="margin-top:16px;display:grid;grid-template-columns:1fr 1fr;gap:12px;">
            <div class="profil-photo" style="aspect-ratio:4/3">
                <?php
                // GAMBAR: assets/img/profil/masjid-pesantren.jpg
                echo imgOrPlaceholder(
                    'profil/masjid-pesantren.jpg',
                    'Masjid Ash-Shiddiq',
                    '<p style="font-size:10px">Masjid Pesantren</p>'
                );
                ?>
            </div>
            <div class="profil-photo" style="aspect-ratio:4/3;background:linear-gradient(135deg,#d4e8f0,#b0c8d8)">
                <?php
                // GAMBAR: assets/img/profil/asrama-santri.jpg
                echo imgOrPlaceholder(
                    'profil/asrama-santri.jpg',
                    'Asrama Santri Pondok Pesantren Ash-Shiddiq',
                    '<p style="font-size:10px">Asrama Santri</p>'
                );
                ?>
            </div>
        </div>
    </div>
</div>

<!-- SEJARAH -->
<div style="background:var(--white);padding:80px 5%;" id="sejarah">
    <div style="max-width:1100px;margin:0 auto;display:grid;grid-template-columns:1fr 2fr;gap:60px;align-items:center;">
        <div class="reveal-left">
            <div class="section-tag" aria-hidden="true"><span></span><span class="section-tag-text">Sejarah</span><span></span></div>
            <h2 class="section-title">Perjalanan<br>Kami</h2>
            <p class="section-desc" style="font-family:'Noto Sans Arabic',sans-serif;font-size:22px;color:var(--green-mid);direction:rtl;margin-top:16px;line-height:1.8;" aria-label="Innallaha ma'ash-saabirin">إِنَّ اللهَ مَعَ الصَّابِرِينَ</p>
        </div>
        <div class="reveal-right">
            <div class="sejarah-timeline">
                <?php
                $sejarah = [
                    ['year'=>'2020','color'=>'var(--gold)',      'judul'=>'Pendirian Pesantren',        'desc'=>'KH. Ahmad Fauzi mendirikan Pondok Pesantren Ash-Shiddiq dengan 12 santri perdana di sebuah bangunan sederhana. Modal utama hanya keyakinan dan doa.'],
                    ['year'=>'2013','color'=>'var(--green-mid)', 'judul'=>'Pengembangan SMP Resmi',      'desc'=>'Membuka jenjang SMP resmi ber-izin operasional Kemenag, dengan total 85 santri dan gedung baru yang lebih representatif.'],
                    ['year'=>'2017','color'=>'var(--green-mid)', 'judul'=>'Wisuda Hafidz Perdana',       'desc'=>'Momen bersejarah: 8 santri angkatan pertama berhasil menyelesaikan hafalan 30 juz dan diwisuda dengan penuh haru.'],
                    ['year'=>'2025','color'=>'var(--green-deep)','judul'=>'Kini: 500+ Santri Aktif',    'desc'=>'Dengan lebih dari 500 santri aktif, 300+ alumni hafidz, dan fasilitas modern, Ash-Shiddiq terus tumbuh menjadi pesantren terkemuka di Jawa Barat.'],
                ];
                foreach ($sejarah as $s):
                ?>
                <div class="sejarah-item">
                    <div class="sejarah-dot" style="background:<?= $s['color'] ?>;box-shadow:0 0 0 2px <?= $s['color'] ?>;"></div>
                    <div class="sejarah-year" style="color:<?= $s['color'] ?>"><?= $s['year'] ?></div>
                    <p class="sejarah-judul"><?= e($s['judul']) ?></p>
                    <p class="sejarah-desc"><?= e($s['desc']) ?></p>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
</div>

<!-- VISI MISI -->
<div class="visi-section" id="visi-misi">
    <div class="visi-inner">
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:48px;">
            <div class="reveal-left">
                <div class="section-tag" aria-hidden="true"><span style="background:rgba(232,168,32,0.5)"></span><span class="section-tag-text">Visi</span><span style="background:rgba(232,168,32,0.5)"></span></div>
                <h2 class="section-title">Visi Kami</h2>
                <div class="visi-card reveal">
                    <h3>Visi</h3>
                    <p>Menjadi pusat pendidikan Islam berbasis Al-Qur'an yang unggul, membentuk generasi hafidz yang berakhlak mulia, berwawasan luas, dan siap mengabdi kepada agama, bangsa, dan masyarakat.</p>
                </div>
            </div>
            <div class="reveal-right">
                <div class="section-tag" aria-hidden="true"><span style="background:rgba(232,168,32,0.5)"></span><span class="section-tag-text">Misi</span><span style="background:rgba(232,168,32,0.5)"></span></div>
                <h2 class="section-title">Misi Kami</h2>
                <ul class="misi-list">
                    <?php
                    $misi = [
                        'Menyelenggarakan pendidikan tahfidz Al-Qur\'an 30 juz dengan metode talaqqi yang bersanad.',
                        'Mengintegrasikan ilmu agama dan pendidikan formal dalam satu sistem pesantren yang terpadu.',
                        'Membentuk karakter santri yang sidiq, amanah, tabligh, dan fathanah dalam kehidupan sehari-hari.',
                        'Menyediakan lingkungan pendidikan yang aman, nyaman, dan kondusif untuk pertumbuhan spiritual dan intelektual santri.',
                        'Membangun jaringan alumni yang produktif dan berkontribusi bagi masyarakat luas.',
                    ];
                    foreach ($misi as $i => $m):
                    ?>
                    <li><div class="misi-num"><?= $i+1 ?></div><p class="misi-text"><?= e($m) ?></p></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        </div>
    </div>
</div>

<!-- STRUKTUR ORGANISASI -->
<div class="struktur-section" id="struktur">
    <div class="section-tag" aria-hidden="true"><span></span><span class="section-tag-text">Organisasi</span><span></span></div>
    <h2 class="section-title">Struktur Organisasi</h2>
    <div class="org-chart reveal" role="img" aria-label="Bagan struktur organisasi pesantren">
        <div class="org-level">
            <div class="org-card head">
                <div class="org-avatar">
                    <?= imgOrPlaceholder('pengajar/kh-ahmad-fauzi.jpg', 'KH. Suroto Abu Nizam, M. Pd.', 'ف') ?>
                </div>
                <div class="org-name">KH. Suroto Abu Nizam, M. Pd.</div>
                <div class="org-pos">Mudir / Pimpinan Pesantren</div>
            </div>
        </div>
        <div class="org-connector-v" aria-hidden="true"></div>
        <div class="org-level" style="width:100%;justify-content:center;gap:60px;">
            <?php
            $level2 = [
                ['init'=>'ع','file'=>'pengajar/ust-abdul-aziz.jpg',     'name'=>'Ust. Abdul Aziz, S.Pd.I','pos'=>'Kepala SMP'],
                ['init'=>'م','file'=>'pengajar/ust-muhammad-hasan.jpg', 'name'=>'Ust. Muhammad Hasan',     'pos'=>'Koordinator Tahfidz'],
                ['init'=>'ي','file'=>'pengajar/ust-yahya-basri.jpg',    'name'=>'Ust. Yahya Basri, M.Ag',  'pos'=>'Kepala SMA'],
            ];
            foreach ($level2 as $l):
            ?>
            <div class="org-card">
                <div class="org-avatar">
                    <?= imgOrPlaceholder($l['file'], $l['name'], e($l['init'])) ?>
                </div>
                <div class="org-name"><?= e($l['name']) ?></div>
                <div class="org-pos"><?= e($l['pos']) ?></div>
            </div>
            <?php endforeach; ?>
        </div>
        <div class="org-connector-v" aria-hidden="true"></div>
        <div class="org-level" style="gap:24px;flex-wrap:wrap;">
            <?php foreach (['Kesiswaan','Kurikulum','Sarana & Prasarana','Humas & PSB'] as $bid): ?>
            <div class="org-card" style="min-width:140px;border-top-color:var(--cream-dark)">
                <div class="org-pos" style="margin-bottom:4px">Bidang</div>
                <div class="org-name" style="font-size:14px"><?= e($bid) ?></div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>

<!-- TIM PENGAJAR -->
<div class="pengajar-section" id="pengajar">
    <div class="pengajar-inner">
        <div class="section-tag" aria-hidden="true"><span></span><span class="section-tag-text">Tim Pengajar</span><span></span></div>
        <h2 class="section-title">Para Masyayikh &amp; Ustadz</h2>
        <div class="pengajar-grid">
            <?php
            // GAMBAR: assets/img/pengajar/{file}.jpg — rasio 1:1 (kotak), ~400x400px
            $pengajar = [
                ['init'=>'ف','file'=>'pengajar/kh-ahmad-fauzi.jpg',  'name'=>'KH. Suroto Abu Nizam, M. Pd.',  'title'=>'Mudir & Pengasuh',     'desc'=>'Alumni Al-Azhar Mesir, hafidz 30 juz bersanad',        'bg'=>'linear-gradient(135deg,#c8ead8,#90cca8)'],
                ['init'=>'ع','file'=>'pengajar/ust-abdul-aziz.jpg', 'name'=>'Ust. Abdul Aziz, S.Pd.I','title'=>'Kepala SMP',          'desc'=>'Lulusan PTIQ Jakarta, spesialis Qira\'at Sab\'ah',     'bg'=>'linear-gradient(135deg,#d4e8d0,#a8d0a8)'],
                ['init'=>'م','file'=>'pengajar/ust-muhammad-hasan.jpg','name'=>'Ust. Muhammad Hasan', 'title'=>'Koordinator Tahfidz', 'desc'=>'Hafidz 30 juz, pengalaman mengajar 12 tahun',          'bg'=>'linear-gradient(135deg,#e4d8f0,#c4b0d8)'],
                ['init'=>'ي','file'=>'pengajar/ust-yahya-basri.jpg', 'name'=>'Ust. Yahya Basri, M.Ag','title'=>'Kepala SMA & Fiqih',  'desc'=>'Lulusan UIN Bandung, spesialis Fiqih Mu\'amalah',      'bg'=>'linear-gradient(135deg,#f0e4d0,#d4c0a0)'],
            ];
            foreach ($pengajar as $i => $p):
            ?>
            <div class="pengajar-card reveal reveal-delay-<?= $i+1 ?>">
                <div class="pengajar-photo" style="background:<?= $p['bg'] ?>">
                    <?= imgOrPlaceholder($p['file'], $p['name'] . ' — ' . $p['title'], e($p['init'])) ?>
                </div>
                <div class="pengajar-name"><?= e($p['name']) ?></div>
                <div class="pengajar-title"><?= e($p['title']) ?></div>
                <div class="pengajar-desc"><?= e($p['desc']) ?></div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>

<!-- FASILITAS -->
<div class="fasilitas-section" id="fasilitas">
    <div class="section-tag" aria-hidden="true"><span></span><span class="section-tag-text">Fasilitas</span><span></span></div>
    <h2 class="section-title">Fasilitas Pesantren</h2>
    <div class="fasilitas-grid">
        <?php
        // GAMBAR: assets/img/fasilitas/{file}.jpg — rasio landscape (~800x480)
        $fasilitas = [
            ['file'=>'fasilitas/masjid.jpg',        'name'=>'Masjid Ash-Shiddiq',  'desc'=>'Masjid utama kapasitas 500 jamaah, dilengkapi sound system dan AC.',                          'bg'=>'linear-gradient(135deg,#d4f0e0,#b0dcc0)'],
            ['file'=>'fasilitas/asrama.jpg',        'name'=>'Asrama Santri',        'desc'=>'Asrama putra dan putri terpisah, berkapasitas 600 santri dengan fasilitas lengkap.',           'bg'=>'linear-gradient(135deg,#d4e0f0,#b0c4e0)'],
            ['file'=>'fasilitas/perpustakaan.jpg',  'name'=>'Perpustakaan',         'desc'=>'Koleksi 5.000+ buku; kitab klasik, ilmu modern, dan referensi Al-Qur\'an.',                    'bg'=>'linear-gradient(135deg,#f0f0d4,#e0e0b0)'],
            ['file'=>'fasilitas/ruang-kelas.jpg',   'name'=>'Ruang Kelas Modern',   'desc'=>'20 ruang kelas ber-AC dengan proyektor dan papan tulis digital interaktif.',                   'bg'=>'linear-gradient(135deg,#f0d4d4,#e0b0b0)'],
            ['file'=>'fasilitas/lab-komputer.jpg',  'name'=>'Lab Komputer',         'desc'=>'Laboratorium komputer 40 unit dengan akses internet terbatas dan terkontrol.',                 'bg'=>'linear-gradient(135deg,#d4f0d4,#a0d8a0)'],
            ['file'=>'fasilitas/klinik.jpg',        'name'=>'Klinik Kesehatan',     'desc'=>'Layanan kesehatan dasar dengan tenaga medis yang siap setiap hari.',                           'bg'=>'linear-gradient(135deg,#d4ecf0,#a0d0d8)'],
        ];
        foreach ($fasilitas as $i => $fas):
            $delay = 'reveal-delay-' . (($i % 3) + 1);
        ?>
        <div class="fas-card reveal <?= $delay ?>">
            <div class="fas-img" style="background:<?= $fas['bg'] ?>">
                <?= imgOrPlaceholder($fas['file'], $fas['name'] . ' — Fasilitas Pondok Pesantren Ash-Shiddiq', '<p>' . e($fas['name']) . '</p>') ?>
            </div>
            <div class="fas-body">
                <div class="fas-name"><?= e($fas['name']) ?></div>
                <div class="fas-desc"><?= e($fas['desc']) ?></div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
</div>

<!-- CTA -->
<div class="cta-strip">
    <h2>Siap Bergabung Bersama Kami?</h2>
    <p>Daftarkan putra-putri Anda dan jadilah bagian dari keluarga besar Ash-Shiddiq.</p>
    <a href="<?= BASE_URL ?>/psb" class="btn-primary" style="background:var(--green-deep);color:white;">Daftar Santri Baru &rarr;</a>
</div>
