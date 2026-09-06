<?php
$activePage = 'galeri';
$pageTitle = 'Galeri Kehidupan Santri | ' . APP_NAME;
$pageDescription = 'Galeri foto kehidupan santri Pondok Pesantren Ash-Shiddiq: halaqah tahfidz, shalat berjamaah, asrama, dan kegiatan sehari-hari.';
$pageCanonical = BASE_URL . '/galeri';

// Kumpulkan foto lalu kelompokkan berdasarkan judul → satu album = satu kartu
$galeri = [];
try {
    $rows = getDB()->query(
        "SELECT nama_file, judul FROM foto_galeri WHERE is_aktif=1 ORDER BY urutan ASC, created_at ASC, id ASC"
    )->fetchAll();
} catch (PDOException $e) {
    $rows = [];
}
foreach ($rows as $r) {
    $key = trim((string) $r['judul']) !== '' ? $r['judul'] : '(tanpa judul)';
    if (!isset($galeri[$key])) {
        $galeri[$key] = ['judul' => $key, 'fotos' => []];
    }
    $galeri[$key]['fotos'][] = $r['nama_file'];
}
$galeri = array_reverse(array_values($galeri)); // album terbaru di depan
?>
<main class="page-section galeri-page">
  <div class="container">
    <div class="section-tag"><span></span><span class="section-tag-text">Galeri Pesantren</span><span></span></div>
    <h1 class="section-title">Galeri Kehidupan Santri</h1>
    <p class="section-desc">Momen sehari-hari santri bersama Al-Qur'an di Pondok Pesantren Ash-Shiddiq. Klik album untuk melihat semua fotonya.</p>
    <?php if (!$galeri): ?><div class="empty-state">Belum ada foto galeri yang dipublikasikan.</div><?php endif; ?>
    <div class="galeri-grid" id="galeriGrid">
    <?php foreach ($galeri as $i => $album):
        $jml = count($album['fotos']);
        $cover = BASE_URL . '/uploads/galeri/' . $album['fotos'][0];
        $fotosJson = htmlspecialchars(
            json_encode(array_map(fn($f) => BASE_URL . '/uploads/galeri/' . $f, $album['fotos']), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
            ENT_QUOTES, 'UTF-8'
        );
    ?>
      <figure class="galeri-item" data-photos='<?= $fotosJson ?>' tabindex="0" role="button" aria-label="Buka album <?= e($album['judul']) ?> (<?= $jml ?> foto)">
        <img src="<?= e($cover) ?>" alt="<?= e($album['judul']) ?>" loading="<?= $i > 5 ? 'lazy' : 'eager' ?>">
        <?php if ($jml > 1): ?><span class="galeri-count"><?= $jml ?> foto</span><?php endif; ?>
        <figcaption><span class="galeri-caption"><?= e($album['judul']) ?></span></figcaption>
      </figure>
    <?php endforeach; ?>
    </div>
  </div>
  <div class="gallery-lightbox" id="galleryLightbox" aria-hidden="true" role="dialog" aria-modal="true" aria-label="Tampilan foto galeri">
    <button class="lightbox-close" type="button" aria-label="Tutup">×</button>
    <span class="lightbox-counter" aria-hidden="true"></span>
    <button class="lightbox-nav lightbox-prev" type="button" aria-label="Foto sebelumnya">‹</button>
    <figure><img src="" alt=""><figcaption></figcaption></figure>
    <button class="lightbox-nav lightbox-next" type="button" aria-label="Foto berikutnya">›</button>
  </div>
</main>
<?php
$extraScripts = <<<'JS'
<style>
.galeri-grid{display:grid;grid-template-columns:repeat(3,1fr);gap:18px;margin-top:34px}
.galeri-item{position:relative;margin:0;border-radius:10px;overflow:hidden;cursor:pointer;aspect-ratio:16/10;background:var(--cream-dark)}
.galeri-item img{width:100%;height:100%;object-fit:cover;display:block;transition:transform .45s}
.galeri-item:hover img{transform:scale(1.05)}
.galeri-item figcaption{position:absolute;left:0;right:0;bottom:0;padding:26px 14px 12px;color:#fff;font-size:14px;background:linear-gradient(transparent,rgba(0,0,0,.72))}
.galeri-item:focus{outline:2px solid var(--gold);outline-offset:2px}
.galeri-count{position:absolute;top:12px;right:12px;background:rgba(0,0,0,.55);color:#fff;font-size:12px;font-weight:600;padding:4px 10px;border-radius:20px;backdrop-filter:blur(4px)}
.gallery-lightbox{position:fixed;inset:0;z-index:10050;display:none;align-items:center;justify-content:center;padding:32px 80px;background:rgba(3,20,14,.94)}
.gallery-lightbox.open{display:flex}
.gallery-lightbox figure{margin:0;max-width:min(1100px,90vw);max-height:88vh;text-align:center}
.gallery-lightbox img{display:block;max-width:100%;max-height:80vh;margin:auto;object-fit:contain;border-radius:8px;box-shadow:0 20px 70px rgba(0,0,0,.5)}
.gallery-lightbox figcaption{color:#fff;margin-top:14px;font-size:15px}
.lightbox-close,.lightbox-nav{position:absolute;border:0;color:#fff;background:rgba(255,255,255,.12);cursor:pointer;border-radius:50%}
.lightbox-close{right:24px;top:20px;width:46px;height:46px;font-size:32px}
.lightbox-nav{top:50%;transform:translateY(-50%);width:52px;height:52px;font-size:38px}
.lightbox-prev{left:20px}.lightbox-next{right:20px}
.lightbox-counter{position:absolute;top:24px;left:28px;color:#fff;background:rgba(255,255,255,.14);padding:6px 14px;border-radius:20px;font-size:14px}
@media(max-width:768px){.galeri-grid{grid-template-columns:repeat(2,1fr)}.gallery-lightbox{padding:60px 18px}.lightbox-counter{left:18px;top:18px}}
@media(max-width:480px){.galeri-grid{grid-template-columns:1fr}}
</style>
<script>
(function(){
  var items = Array.from(document.querySelectorAll('.galeri-item'));
  if (!items.length) return;
  var lightbox = document.getElementById('galleryLightbox');
  var img = lightbox.querySelector('img'), cap = lightbox.querySelector('figcaption');
  var counter = lightbox.querySelector('.lightbox-counter');
  var photos = [], cur = 0, albumTitle = '';

  function show(){
    img.src = photos[cur]; img.alt = albumTitle;
    cap.textContent = photos.length > 1 ? albumTitle + ' (' + (cur+1) + '/' + photos.length + ')' : albumTitle;
    if (counter) counter.textContent = (cur+1) + ' / ' + photos.length;
  }
  function openAlbum(it){
    photos = JSON.parse(it.getAttribute('data-photos') || '[]');
    if (!photos.length) return;
    albumTitle = (it.querySelector('.galeri-caption') || {textContent:''}).textContent;
    cur = 0; show();
    lightbox.classList.add('open'); lightbox.setAttribute('aria-hidden','false');
    document.body.style.overflow = 'hidden';
  }
  function close(){ lightbox.classList.remove('open'); lightbox.setAttribute('aria-hidden','true'); document.body.style.overflow=''; }
  function move(d){ if(!photos.length) return; cur = (cur + d + photos.length) % photos.length; show(); }

  items.forEach(function(it){
    it.addEventListener('click', function(){ openAlbum(it); });
    it.addEventListener('keydown', function(e){ if(e.key==='Enter'||e.key===' '){e.preventDefault();openAlbum(it);} });
  });
  lightbox.querySelector('.lightbox-close').addEventListener('click', close);
  lightbox.querySelector('.lightbox-prev').addEventListener('click', function(){ move(-1); });
  lightbox.querySelector('.lightbox-next').addEventListener('click', function(){ move(1); });
  lightbox.addEventListener('click', function(e){ if(e.target===lightbox) close(); });
  document.addEventListener('keydown', function(e){
    if(!lightbox.classList.contains('open')) return;
    if(e.key==='Escape') close();
    if(e.key==='ArrowLeft') move(-1);
    if(e.key==='ArrowRight') move(1);
  });
  var tx = 0;
  lightbox.addEventListener('touchstart', function(e){ tx = e.changedTouches[0].clientX; }, {passive:true});
  lightbox.addEventListener('touchend', function(e){ var d = e.changedTouches[0].clientX - tx; if(Math.abs(d)>50) move(d>0?-1:1); }, {passive:true});
})();
</script>
JS;
?>