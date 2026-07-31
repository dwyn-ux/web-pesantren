<?php
$activePage      = 'sekapur-sirih';
$pageTitle       = 'Sekapur Sirih | ' . APP_NAME;
$pageDescription = 'Sambutan dan kata pengantar dari pimpinan Pondok Pesantren Ash-Shiddiq untuk para pengunjung dan calon santri. Empat pilar nilai dan harapan pesantren.';
$pageCanonical   = BASE_URL . '/sekapur-sirih';

$extraHead = <<<'CSS'
<style>
/* ── PEMBUKA ──────────────────────────────────────────────────── */
.kata-pembuka {
    max-width:780px; margin:0 auto; padding:80px 5%; text-align:center;
}
.kata-pembuka .arabic-big {
    font-family:'Noto Sans Arabic',sans-serif;
    font-size:clamp(32px,5vw,56px);
    color:var(--green-deep); line-height:1.6; margin-bottom:12px;
}
.kata-pembuka .terjemah {
    font-size:14px; color:var(--text-light);
    font-style:italic; margin-bottom:48px; letter-spacing:0.5px;
}
.ornament-divider {
    display:flex; align-items:center; gap:20px;
    justify-content:center; margin:36px 0;
}
.ornament-divider span {
    width:80px; height:1px;
    background:linear-gradient(90deg,transparent,var(--gold));
}
.ornament-divider span:last-child {
    background:linear-gradient(90deg,var(--gold),transparent);
}
.ornament-divider-diamond {
    width:10px; height:10px;
    background:var(--gold); transform:rotate(45deg);
}

/* ── SURAT MUDIR ──────────────────────────────────────────────── */
.mudir-letter-wrap { padding:0 5% 80px; background:var(--cream); }
.mudir-letter {
    background:var(--white); max-width:900px; margin:0 auto;
    padding:60px 72px; position:relative; overflow:hidden;
}
.mudir-letter::before {
    content:'"'; font-family:'Plus Jakarta Sans',sans-serif;
    font-size:220px; color:var(--cream-dark);
    position:absolute; top:-30px; left:20px;
    line-height:1; pointer-events:none;
}
.mudir-letter::after {
    content:''; position:absolute; left:0; top:0; bottom:0;
    width:5px; background:linear-gradient(to bottom,var(--gold),var(--green-mid));
}
.letter-content { position:relative; z-index:1; }
.letter-p {
    font-size:16px; line-height:2; color:var(--text-mid);
    margin-bottom:24px; text-align:justify;
}
.letter-p.arabic-inline {
    font-family:'Noto Sans Arabic',sans-serif; font-size:20px; direction:rtl;
    text-align:right; color:var(--green-deep); margin-bottom:8px; line-height:1.8;
}
.letter-p.source {
    font-size:13px; color:var(--text-light);
    text-align:right; margin-bottom:28px; font-style:italic;
}
.letter-closing { margin-top:40px; }
.letter-closing .sig-label { font-size:13px; color:var(--text-light); margin-bottom:32px; }
.sig-name { font-family:'Plus Jakarta Sans',sans-serif; font-size:22px; color:var(--green-deep); }
.sig-title { font-size:13px; color:var(--text-light); letter-spacing:1px; margin-top:4px; }
.sig-place { font-size:13px; color:var(--text-light); margin-top:2px; }

/* ── NILAI PESANTREN ──────────────────────────────────────────── */
.nilai-section { background:var(--green-deep); padding:80px 5%; }
.nilai-inner { max-width:1100px; margin:0 auto; }
.nilai-inner .section-title { color:white; }
.nilai-grid {
    display:grid; grid-template-columns:repeat(4,1fr);
    gap:1px; background:rgba(255,255,255,0.08);
    margin-top:48px; border-radius:4px; overflow:hidden;
}
.nilai-card {
    background:rgba(13,122,74,0.6); padding:40px 28px; text-align:center;
    transition:background 0.3s;
}
.nilai-card:hover { background:rgba(13,122,74,0.9); }
.nilai-arabic {
    font-family:'Noto Sans Arabic',sans-serif; font-size:36px;
    color:var(--gold); margin-bottom:12px; line-height:1.4;
}
.nilai-latin {
    font-size:11px; letter-spacing:3px; text-transform:uppercase;
    color:rgba(255,255,255,0.5); margin-bottom:14px;
}
.nilai-desc { font-size:13px; color:rgba(255,255,255,0.7); line-height:1.7; }

/* ── HARAPAN ──────────────────────────────────────────────────── */
.harapan-section { max-width:900px; margin:0 auto; padding:80px 5%; }
.harapan-card {
    background:var(--white); border-radius:4px; padding:48px 56px;
    border-top:4px solid var(--gold); margin-bottom:24px; position:relative;
}
.harapan-num {
    font-family:'Plus Jakarta Sans',sans-serif; font-size:64px; font-style:italic;
    color:var(--cream-dark); position:absolute; top:16px; right:24px; line-height:1;
}
.harapan-title {
    font-family:'Plus Jakarta Sans',sans-serif; font-size:22px;
    color:var(--green-deep); margin-bottom:12px;
}
.harapan-text { font-size:15px; line-height:1.9; color:var(--text-mid); }

/* ── DO'A PENUTUP ─────────────────────────────────────────────── */
.doa-section {
    background:linear-gradient(135deg,#0a5c38 0%,#084d2f 100%);
    padding:80px 5%; text-align:center; position:relative; overflow:hidden;
}
.doa-section::before {
    content:''; position:absolute; inset:0;
    background-image:url("data:image/svg+xml,%3Csvg width='80' height='80' viewBox='0 0 80 80' xmlns='http://www.w3.org/2000/svg'%3E%3Cg fill='none' stroke='%23e8a820' stroke-width='0.5'%3E%3Cpolygon points='40,5 75,22.5 75,57.5 40,75 5,57.5 5,22.5'/%3E%3C/g%3E%3C/svg%3E");
    opacity:0.04;
}
.doa-inner { position:relative; z-index:1; max-width:680px; margin:0 auto; }
.doa-arabic {
    font-family:'Noto Sans Arabic',sans-serif; font-size:clamp(22px,4vw,40px);
    color:var(--gold); line-height:1.8; margin-bottom:16px; direction:rtl;
}
.doa-terjemah {
    font-size:15px; color:rgba(255,255,255,0.6);
    font-style:italic; line-height:1.7; margin-bottom:40px;
}
.doa-ttd { margin-top:40px; padding-top:32px; border-top:1px solid rgba(255,255,255,0.1); }
.doa-ttd p { color:rgba(255,255,255,0.5); font-size:13px; margin-bottom:6px; }
.doa-ttd strong {
    font-family:'Plus Jakarta Sans',sans-serif;
    font-size:22px; color:white; display:block; margin-bottom:4px;
}
.doa-ttd span { font-size:13px; color:rgba(255,255,255,0.5); }

/* ── KONTAK ───────────────────────────────────────────────────── */
.kontak-section {
    max-width:1100px; margin:0 auto; padding:80px 5%;
    display:grid; grid-template-columns:1fr 1fr; gap:48px; align-items:start;
}
.kontak-card {
    background:var(--white); border-radius:4px; padding:36px 32px;
    display:flex; gap:20px; align-items:flex-start;
    transition:transform 0.3s, box-shadow 0.3s;
}
.kontak-card:hover { transform:translateY(-4px); box-shadow:0 12px 36px rgba(13,122,74,0.1); }
.kontak-icon {
    width:48px; height:48px; flex-shrink:0;
    background:var(--cream-dark); border-radius:50%;
    display:flex; align-items:center; justify-content:center; font-size:20px;
}
.kontak-body h4 { font-family:'Plus Jakarta Sans',sans-serif; font-size:17px; margin-bottom:6px; }
.kontak-body p  { font-size:14px; color:var(--text-mid); line-height:1.6; }
.kontak-body a  { color:var(--green-mid); text-decoration:none; }
.kontak-body a:hover { text-decoration:underline; }
.map-embed { border-radius:4px; overflow:hidden; height:300px; }
.map-embed iframe { width:100%; height:100%; border:0; display:block; }
.map-placeholder {
    background:linear-gradient(135deg,#c8e8d8,#a8d4c0);
    border-radius:4px; height:300px;
    display:flex; align-items:center; justify-content:center;
    flex-direction:column; gap:12px;
}
.map-placeholder p { font-family:'Courier New',monospace; font-size:11px; color:rgba(0,0,0,0.3); letter-spacing:1px; }
.kontak-actions { margin-top:20px; text-align:center; }
.kontak-actions .btn-primary,
.kontak-actions .btn-outline { display:inline-block; }

@media (max-width:900px) {
    .mudir-letter { padding:40px 32px; }
    .nilai-grid   { grid-template-columns:repeat(2,1fr); }
    .kontak-section { grid-template-columns:1fr; }
    .harapan-card { padding:32px 28px; }
}
@media (max-width:600px) {
    .mudir-letter { padding:32px 20px; }
    .harapan-card { padding:24px 20px; }
}
</style>
CSS;

?>

<!-- ══ PAGE HERO ════════════════════════════════════════════════ -->
<div class="page-hero">
    <div class="page-hero-pattern"></div>
    <div class="page-hero-content">
        <div class="page-hero-tag"><span></span>Kata Pengantar<span></span></div>
        <h1>Sekapur Sirih</h1>
        <p>Sambutan dan kata hati dari pimpinan Pondok Pesantren Ash-Shiddiq untuk para pengunjung dan calon santri.</p>
    </div>
</div>

<!-- ══ PEMBUKA ══════════════════════════════════════════════════ -->
<div class="kata-pembuka reveal">
    <div class="arabic-big">بِسْمِ اللهِ الرَّحْمَنِ الرَّحِيْمِ</div>
    <div class="terjemah">"Dengan Nama Allah Yang Maha Pengasih lagi Maha Penyayang"</div>
    <div class="ornament-divider">
        <span></span>
        <div class="ornament-divider-diamond"></div>
        <span></span>
    </div>
    <p style="font-size:16px;line-height:1.9;color:var(--text-mid);">
        Selamat datang di halaman resmi <strong>Pondok Pesantren Ash-Shiddiq</strong>. Izinkan kami menyapa Anda dengan penuh ketulusan dan kehangatan, sebagaimana seorang tuan rumah yang rindu akan kedatangan tamu-tamunya yang mulia.
    </p>
</div>

<!-- ══ SURAT MUDIR ══════════════════════════════════════════════ -->
<div class="mudir-letter-wrap">
    <div class="mudir-letter reveal">
        <div class="letter-content">
            <p class="letter-p arabic-inline">السَّلَامُ عَلَيْكُمْ وَرَحْمَةُ اللهِ وَبَرَكَاتُهُ</p>

            <p class="letter-p">Alhamdulillah, segala puji hanya milik Allah SWT yang telah memberikan kami kesempatan dan kekuatan untuk terus istiqomah dalam jalan dakwah dan pendidikan Islam. Shalawat dan salam semoga senantiasa tercurah kepada baginda Nabi Muhammad SAW, keluarga, sahabat, dan para penerus perjuangan beliau hingga hari kiamat.</p>

            <p class="letter-p arabic-inline">خَيْرُكُمْ مَنْ تَعَلَّمَ الْقُرْآنَ وَعَلَّمَهُ</p>
            <p class="letter-p source">— HR. Bukhari, dari Utsman bin Affan radhiyallahu 'anhu</p>

            <p class="letter-p">"Sebaik-baik kalian adalah orang yang mempelajari Al-Qur'an dan mengajarkannya." Hadits mulia ini menjadi fondasi dan ruh dari seluruh ikhtiar kami di Pondok Pesantren Ash-Shiddiq. Sejak berdiri pada tahun 2020, kami tidak pernah berhenti bermimpi tentang generasi Islam yang tidak hanya hafal Al-Qur'an dalam dada, namun juga menghidupkannya dalam setiap langkah kehidupan mereka.</p>

            <p class="letter-p">Pondok Pesantren Ash-Shiddiq bukan sekadar lembaga pendidikan. Ia adalah rumah. Rumah bagi para santri yang datang dari berbagai penjuru negeri dengan satu tekad: menjadi hamba Allah yang lebih baik melalui kecintaan kepada Al-Qur'an. Di sinilah mereka belajar bukan hanya tentang ilmu, tetapi juga tentang adab, persaudaraan, keikhlasan, dan keteguhan hati.</p>

            <p class="letter-p">Nama "Ash-Shiddiq" kami ambil dari gelar mulia yang disandang oleh sahabat terdekat Rasulullah SAW, Abu Bakar Ash-Shiddiq radhiyallahu 'anhu. Beliau yang dijuluki <em>ash-shiddiq</em> — yang selalu membenarkan — adalah teladan kejujuran, kesetiaan, dan keberanian yang kami harapkan menjadi karakter setiap lulusan kami.</p>

            <p class="letter-p">Kepada para orang tua yang tengah mempertimbangkan untuk menitipkan buah hati Anda kepada kami: percayakanlah kepada kami dengan sepenuh hati. Kami berkomitmen menjadi pengemban amanah yang bertanggung jawab — mendidik, menjaga, dan mengarahkan putra-putri Anda di atas jalan kebenaran. Kami tidak hanya mengajar hafalan, kami mendidik jiwa.</p>

            <p class="letter-p">Kepada para santri dan calon santri kami: ketahuilah bahwa setiap ayat yang kalian hafal adalah cahaya yang akan menerangi kalian di dunia dan di akhirat. Jangan pernah menyerah. Hafalan yang berat hari ini adalah mahkota yang akan kalian kenakan esok hari.</p>

            <div class="letter-closing">
                <p class="sig-label">Wassalamu'alaikum Warahmatullahi Wabarakatuh,<br>Ciamis, Januari 2025</p>
                <div class="sig-name">KH. Suroto Abu Nizam, M. Pd.</div>
                <div class="sig-title">Mudir Pondok Pesantren Ash-Shiddiq</div>
                <div class="sig-place">Ciamis, Jawa Barat</div>
            </div>
        </div>
    </div>
</div>

<!-- ══ NILAI-NILAI ═══════════════════════════════════════════════ -->
<div class="nilai-section">
    <div class="nilai-inner">
        <div class="section-tag reveal" style="justify-content:center;">
            <span style="background:rgba(232,168,32,0.5)"></span>
            <span class="section-tag-text">Nilai Kami</span>
            <span style="background:rgba(232,168,32,0.5)"></span>
        </div>
        <h2 class="section-title reveal" style="text-align:center;">Empat Pilar Ash-Shiddiq</h2>
        <p class="section-desc reveal" style="color:rgba(255,255,255,0.6);text-align:center;max-width:600px;margin:0 auto 0;">Nilai-nilai yang kami tanamkan kepada setiap santri sebagai bekal kehidupan.</p>

        <div class="nilai-grid">
            <div class="nilai-card reveal">
                <div class="nilai-arabic">الصِّدْقُ</div>
                <div class="nilai-latin">Ash-Shidqu</div>
                <div class="nilai-desc">Kejujuran dalam setiap kata, perbuatan, dan niat. Fondasi dari semua kebaikan.</div>
            </div>
            <div class="nilai-card reveal">
                <div class="nilai-arabic">الْأَمَانَةُ</div>
                <div class="nilai-latin">Al-Amanah</div>
                <div class="nilai-desc">Kepercayaan dan tanggung jawab atas setiap amanah yang diberikan Allah dan manusia.</div>
            </div>
            <div class="nilai-card reveal">
                <div class="nilai-arabic">الْإِخْلَاصُ</div>
                <div class="nilai-latin">Al-Ikhlas</div>
                <div class="nilai-desc">Ketulusan dalam beribadah dan beramal semata-mata mengharap ridha Allah SWT.</div>
            </div>
            <div class="nilai-card reveal">
                <div class="nilai-arabic">الْاِسْتِقَامَةُ</div>
                <div class="nilai-latin">Al-Istiqamah</div>
                <div class="nilai-desc">Keteguhan dan konsistensi di atas kebenaran dalam setiap kondisi dan tantangan.</div>
            </div>
        </div>
    </div>
</div>

<!-- ══ HARAPAN ══════════════════════════════════════════════════ -->
<div class="harapan-section">
    <div style="text-align:center;margin-bottom:56px;" class="reveal">
        <div class="section-tag" style="justify-content:center;">
            <span></span>
            <span class="section-tag-text">Harapan Kami</span>
            <span></span>
        </div>
        <h2 class="section-title">Yang Kami Cita-citakan</h2>
    </div>

    <div class="harapan-card reveal">
        <div class="harapan-num">01</div>
        <h3 class="harapan-title">Lahirnya Generasi Qur'ani</h3>
        <p class="harapan-text">Kami bermimpi akan datangnya hari di mana setiap sudut negeri ini diterangi oleh cahaya Al-Qur'an dari para hafidz yang kami didik. Bukan hafidz yang sekadar menyimpan ayat dalam ingatan, melainkan hafidz yang hidup bersama Al-Qur'an — setiap nafasnya, setiap langkahnya, setiap keputusannya.</p>
    </div>
    <div class="harapan-card reveal">
        <div class="harapan-num">02</div>
        <h3 class="harapan-title">Ulama Penerus Peradaban</h3>
        <p class="harapan-text">Islam membutuhkan ulama yang tidak hanya menguasai ilmu agama, tetapi juga mampu menjawab tantangan zaman dengan bijaksana. Kami berharap setiap alumni Ash-Shiddiq menjadi pelita di tengah kegelapan — pemandu, penyelesai masalah, dan penjaga aqidah umat.</p>
    </div>
    <div class="harapan-card reveal">
        <div class="harapan-num">03</div>
        <h3 class="harapan-title">Keluarga Besar yang Bersatu</h3>
        <p class="harapan-text">Pesantren adalah keluarga. Kami bercita-cita membangun jaringan alumni Ash-Shiddiq yang solid, saling mendukung, dan terus berkolaborasi dalam kebaikan — dari Sabang hingga Merauke, bahkan hingga ke mancanegara. Ukhuwah Islamiyah yang tulus dan abadi.</p>
    </div>
</div>

<!-- ══ DO'A PENUTUP ═════════════════════════════════════════════ -->
<div class="doa-section">
    <div class="doa-inner reveal">
        <div class="section-tag" style="justify-content:center;margin-bottom:32px;">
            <span style="background:rgba(232,168,32,0.4)"></span>
            <span class="section-tag-text">Do'a Penutup</span>
            <span style="background:rgba(232,168,32,0.4)"></span>
        </div>
        <div class="doa-arabic">
            رَبَّنَا هَبْ لَنَا مِنْ أَزْوَاجِنَا وَذُرِّيَّاتِنَا قُرَّةَ أَعْيُنٍ وَاجْعَلْنَا لِلْمُتَّقِينَ إِمَامًا
        </div>
        <p class="doa-terjemah">
            "Ya Tuhan kami, anugerahkanlah kepada kami pasangan kami dan keturunan kami sebagai penyenang hati (kami), dan jadikanlah kami pemimpin bagi orang-orang yang bertakwa."<br>
            <em>— QS. Al-Furqan: 74</em>
        </p>
        <div class="ornament-divider" style="justify-content:center;">
            <span style="background:linear-gradient(90deg,transparent,rgba(232,168,32,0.5))"></span>
            <div class="ornament-divider-diamond"></div>
            <span style="background:linear-gradient(90deg,rgba(232,168,32,0.5),transparent)"></span>
        </div>
        <div class="doa-ttd">
            <p>Hormat kami,</p>
            <strong>Pondok Pesantren Ash-Shiddiq</strong>
            <span>Ciamis, Jawa Barat · ponpesashiddiq.or.id</span>
        </div>
    </div>
</div>

<!-- ══ KONTAK ════════════════════════════════════════════════════ -->
<div class="kontak-section">
    <div>
        <div class="section-tag reveal">
            <span></span>
            <span class="section-tag-text">Hubungi Kami</span>
            <span></span>
        </div>
        <h2 class="section-title reveal">Ada Pertanyaan?<br>Kami Siap Membantu</h2>
        <p class="section-desc reveal" style="margin-bottom:32px;">
            Jangan ragu untuk menghubungi kami. Tim kami siap menjawab setiap pertanyaan Anda dengan sepenuh hati.
        </p>

        <div class="kontak-card reveal">
            <div class="kontak-icon" aria-hidden="true">📍</div>
            <div class="kontak-body">
                <h4>Alamat</h4>
                <p>Jl. Pesantren No. 1, Desa Panjalu, Kec. Panjalu,<br>Kab. Ciamis, Jawa Barat 46261</p>
            </div>
        </div>
        <div style="height:12px"></div>
        <div class="kontak-card reveal">
            <div class="kontak-icon" aria-hidden="true">📞</div>
            <div class="kontak-body">
                <h4>Telepon &amp; WhatsApp</h4>
                <p>
                    <a href="tel:+62265123456">(0265) 123-4567</a><br>
                    <a href="https://wa.me/6281234567890" rel="noopener noreferrer" target="_blank">WhatsApp: 0812-3456-7890</a>
                </p>
            </div>
        </div>
        <div style="height:12px"></div>
        <div class="kontak-card reveal">
            <div class="kontak-icon" aria-hidden="true">✉</div>
            <div class="kontak-body">
                <h4>Email</h4>
                <p>
                    <a href="mailto:info@ponpesashiddiq.or.id">info@ponpesashiddiq.or.id</a><br>
                    Jam kerja: Senin–Sabtu, 08.00–16.00 WIB
                </p>
            </div>
        </div>
    </div>

    <div class="reveal-right">
        <div class="map-placeholder" role="img" aria-label="Peta lokasi pesantren">
            <svg width="52" height="52" viewBox="0 0 52 52" fill="none" opacity="0.4" aria-hidden="true">
                <path d="M26 4a16 16 0 0 1 16 16c0 12-16 28-16 28S10 32 10 20A16 16 0 0 1 26 4z" stroke="#0d7a4a" stroke-width="1.5"/>
                <circle cx="26" cy="20" r="6" stroke="#0d7a4a" stroke-width="1.5"/>
            </svg>
            <p>peta lokasi pesantren</p>
            <p style="font-size:9px;margin-top:4px;">Kab. Ciamis, Jawa Barat</p>
        </div>
        <div class="kontak-actions">
            <a href="<?= BASE_URL ?>/psb" class="btn-primary" style="margin-right:12px;">Daftar Sekarang</a>
            <a href="<?= BASE_URL ?>/profil" class="btn-outline">Lihat Profil</a>
        </div>
    </div>
</div>
