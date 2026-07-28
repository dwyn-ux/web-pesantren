<?php
// Header 404 — pastikan HTTP status code di-set oleh caller (index.php)
// atau set di sini jika diakses langsung
if (http_response_code() !== 404) {
    http_response_code(404);
}

$activePage      = '';
$pageTitle       = '404 — Halaman Tidak Ditemukan | ' . APP_NAME;
$pageDescription = 'Halaman yang Anda cari tidak ditemukan. Kembali ke beranda Pondok Pesantren Ash-Shiddiq.';
$pageCanonical   = BASE_URL . '/404';

$extraHead = <<<'CSS'
<style>
.error-page {
    min-height:70vh; display:flex; align-items:center; justify-content:center;
    padding:80px 5%; text-align:center; flex-direction:column;
}
.error-404 {
    font-family:'Playfair Display',serif; font-size:clamp(80px,15vw,140px);
    font-style:italic; color:var(--cream-dark); line-height:1;
    margin-bottom:0;
}
.error-arabic {
    font-family:'Amiri',serif; font-size:clamp(20px,3vw,32px);
    color:var(--green-mid); margin-bottom:16px;
}
.error-title {
    font-family:'Playfair Display',serif; font-size:clamp(22px,3vw,32px);
    color:var(--text-dark); margin-bottom:16px;
}
.error-desc { font-size:16px; color:var(--text-mid); line-height:1.8; max-width:500px; margin-bottom:40px; }
.error-links { display:flex; gap:16px; flex-wrap:wrap; justify-content:center; }
</style>
CSS;
?>

<div class="error-page">
    <div class="error-404" aria-hidden="true">404</div>
    <p class="error-arabic">لَا تَيْأَسُوا مِنْ رَحْمَةِ اللهِ</p>
    <h1 class="error-title">Halaman Tidak Ditemukan</h1>
    <p class="error-desc">
        Maaf, halaman yang Anda cari tidak dapat ditemukan. Mungkin alamatnya berubah atau sudah tidak tersedia.
    </p>
    <div class="error-links">
        <a href="<?= e(BASE_URL . '/') ?>" class="btn-primary">Kembali ke Beranda</a>
        <a href="<?= e(BASE_URL . '/artikel') ?>" class="btn-outline">Baca Artikel</a>
        <a href="<?= e(BASE_URL . '/psb') ?>" class="btn-outline">Info PSB</a>
    </div>
</div>
