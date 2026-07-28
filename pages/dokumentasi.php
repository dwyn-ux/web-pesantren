<?php
$activePage = 'dokumentasi';
$pageTitle = 'Dokumentasi | ' . APP_NAME;
$pageDescription = 'Dokumentasi kegiatan, foto, video, dan berkas PDF Pondok Pesantren Ash-Shiddiq.';
$pageCanonical = BASE_URL . '/dokumentasi';
$items = [];
try {
    $items = getDB()->query("SELECT id,judul,deskripsi,tipe,created_at FROM dokumentasi WHERE is_published=1 AND tipe IN ('pdf','foto','video') ORDER BY created_at DESC")->fetchAll();
} catch (PDOException $e) {}
?>
<main class="page-section documentation-page">
  <div class="container">
    <div class="section-tag"><span></span><span class="section-tag-text">Arsip Pesantren</span><span></span></div>
    <h1 class="section-title">Dokumentasi</h1>
    <p class="section-desc">Klik PDF untuk membaca langsung dalam tampilan besar. Berkas hanya tersedia untuk dibaca, bukan diunduh.</p>
    <div class="documentation-grid">
    <?php if (!$items): ?><div class="empty-state">Belum ada dokumentasi yang dipublikasikan.</div><?php endif; ?>
    <?php foreach ($items as $item): ?>
      <article class="documentation-card">
        <a class="documentation-preview<?= $item['tipe']==='pdf' ? ' js-pdf-open' : '' ?>" href="<?= e(BASE_URL . '/dokumen-lihat?id=' . $item['id']) ?>" data-title="<?= e($item['judul']) ?>">
          <?php if ($item['tipe']==='foto'): ?><img src="<?= e(BASE_URL . '/dokumen-lihat?id=' . $item['id']) ?>" alt="<?= e($item['judul']) ?>" loading="lazy">
          <?php elseif ($item['tipe']==='video'): ?><video src="<?= e(BASE_URL . '/dokumen-lihat?id=' . $item['id']) ?>" controls preload="metadata"></video>
          <?php else: ?><span class="file-icon">PDF</span><span>Baca dokumen</span><?php endif; ?>
        </a>
        <div class="documentation-body"><small><?= e(strtoupper($item['tipe'])) ?> · <?= e(formatTanggal($item['created_at'])) ?></small><h2><?= e($item['judul']) ?></h2><p><?= e($item['deskripsi'] ?? '') ?></p></div>
      </article>
    <?php endforeach; ?>
    </div>
  </div>
</main>
<div class="pdf-modal" id="pdfModal" aria-hidden="true"><div class="pdf-dialog"><div class="pdf-toolbar"><strong id="pdfTitle">Dokumen</strong><button type="button" id="pdfClose" aria-label="Tutup">×</button></div><iframe id="pdfFrame" title="Pembaca PDF"></iframe></div></div>
<?php $extraScripts = '<script src="'.BASE_URL.'/assets/js/dokumentasi.js?v='.ASSET_VERSION.'"></script>'; ?>
