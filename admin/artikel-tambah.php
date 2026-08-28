<?php
require_once __DIR__ . '/bootstrap.php';
requireAdmin();

$pdo    = getDB();
$user   = getCurrentUser();
$errors = [];
$data   = [];

// ── Proses POST ───────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    validateCsrf();

    $data = [
        'judul'       => sanitizeString($_POST['judul']    ?? ''),
        'ringkasan'   => sanitizeString($_POST['ringkasan'] ?? ''),
        'isi'         => $_POST['isi'] ?? '',        // diproses terpisah
        'kategori'    => sanitizeString($_POST['kategori']  ?? ''),
        'status'      => sanitizeString($_POST['status']    ?? 'draft'),
    ];

    // Sanitasi isi artikel — strip tag berbahaya, izinkan subset aman
    $allowedTags = '<p><br><strong><em><u><h2><h3><h4><ul><ol><li><blockquote><a><img>';
    $data['isi'] = strip_tags($data['isi'], $allowedTags);

    $validKategori = ['tahfidz','akhlak','kajian','kegiatan','psb-info','alumni'];
    $validStatus   = ['draft', 'published'];

    if (empty($data['judul']))    $errors['judul']    = 'Judul wajib diisi.';
    if (empty($data['isi']))      $errors['isi']      = 'Isi artikel wajib diisi.';
    if (!in_array($data['kategori'], $validKategori, true)) $errors['kategori'] = 'Pilih kategori yang valid.';
    if (!in_array($data['status'],   $validStatus,   true)) $data['status'] = 'draft';

    // Generate slug dari judul
    $slug = slugify($data['judul']);
    // Pastikan slug unik
    $stmtSlug = $pdo->prepare("SELECT COUNT(*) FROM artikel WHERE slug = ?");
    $stmtSlug->execute([$slug]);
    if ((int)$stmtSlug->fetchColumn() > 0) {
        $slug .= '-' . time();
    }

    // Upload foto (opsional)
    $fotoFile = null;
    if (!empty($_FILES['foto']['name'])) {
        $uploadErrors = validateUpload(
            $_FILES['foto'],
            ['jpg','jpeg','png','webp'],
            ['image/jpeg','image/png','image/webp'],
            2097152 // 2 MB
        );
        if (!empty($uploadErrors)) {
            $errors['foto'] = implode(' ', $uploadErrors);
        } else {
            $destDir  = ROOT_PATH . '/uploads/artikel/';
            $fotoFile = saveUpload($_FILES['foto'], $destDir);
            if ($fotoFile) {
                // Resize
                resizeImage($destDir . $fotoFile, $destDir . $fotoFile, 1200);
            } else {
                $errors['foto'] = 'Gagal menyimpan foto.';
            }
        }
    }

    if (empty($errors)) {
        $publishedAt = $data['status'] === 'published' ? date('Y-m-d H:i:s') : null;
        $pdo->prepare(
            "INSERT INTO artikel
                (slug, judul, ringkasan, isi, foto, kategori, status, penulis_id, published_at)
             VALUES (?,?,?,?,?,?,?,?,?)"
        )->execute([
            $slug,
            $data['judul'],
            $data['ringkasan'] ?: null,
            $data['isi'],
            $fotoFile,
            $data['kategori'],
            $data['status'],
            $user['id'],
            $publishedAt,
        ]);

        setFlash('success', 'Artikel berhasil disimpan.');
        redirect('/admin/artikel');
    }
}

$adminTitle = 'Tulis Artikel Baru';
$adminPage  = 'admin/artikel';

$labelKategori = [
    'tahfidz'  => 'Tahfidz',        'akhlak'   => 'Akhlak & Adab',
    'kajian'   => 'Kajian Islam',   'kegiatan' => 'Kegiatan Pesantren',
    'psb-info' => 'PSB & Info',     'alumni'   => 'Profil Alumni',
];

require_once __DIR__ . '/includes/header.php';
?>

<div class="admin-form-card ai-writer-card">
  <h2 class="admin-form-title">Tulis dengan AI</h2>
  <p class="ai-note">Hasil AI masuk ke editor sebagai draft. Tetap cek fakta dan edit sebelum dipublikasikan.</p>
  <div class="ai-writer-row"><select id="aiProvider" class="form-control"><option value="gemini">Gemini</option><option value="deepseek">DeepSeek</option><option value="openai">OpenAI</option><option value="openrouter">OpenRouter</option><option value="custom">Custom Endpoint</option></select><input id="aiTopic" class="form-control" placeholder="Topik artikel, misalnya: manfaat murajaah harian"><select id="aiTone" class="form-control"><option value="informatif dan hangat">Informatif</option><option value="inspiratif dan menyentuh">Inspiratif</option><option value="formal dan edukatif">Formal</option></select><button type="button" id="aiGenerate" class="btn-sm btn-sm-primary">Generate</button></div><div id="aiCustomFields" style="display:none;margin-top:10px;"><div class="ai-writer-row" style="flex-wrap:wrap;gap:8px;"><input id="aiCustomUrl" class="form-control" placeholder="API URL (contoh: https://api.groq.com/openai/v1/chat/completions)" style="flex:1 1 100%;"><input id="aiCustomKey" class="form-control" placeholder="API Key" type="password" style="flex:1 1 48%;"><input id="aiCustomModel" class="form-control" placeholder="Model (contoh: llama-3.1-70b-versatile)" style="flex:1 1 48%;"></div></div><p id="aiStatus" class="ai-status" role="status"></p>
</div>

<?php if (!empty($errors)): ?>
<div class="flash-message flash-error" role="alert" style="margin-bottom:16px;">
    Terdapat <?= count($errors) ?> kesalahan. Periksa kembali formulir.
</div>
<?php endif; ?>

<form method="POST" action="<?= e(BASE_URL . '/admin/artikel-tambah') ?>"
      enctype="multipart/form-data" class="admin-form">
    <input type="hidden" name="csrf_token" value="<?= generateCsrfToken() ?>">

    <div style="display:grid;grid-template-columns:1fr 300px;gap:24px;align-items:start;">

        <!-- Konten utama -->
        <div>
            <div class="admin-form-card">
                <h2 class="admin-form-title">Konten Artikel</h2>

                <div class="form-group">
                    <label for="judul">Judul <span class="req">*</span></label>
                    <input type="text" id="judul" name="judul"
                           class="form-control<?= isset($errors['judul']) ? ' is-error' : '' ?>"
                           value="<?= e($data['judul'] ?? '') ?>" maxlength="300" required autofocus>
                    <?php if (isset($errors['judul'])): ?>
                    <p class="field-error"><?= e($errors['judul']) ?></p>
                    <?php endif; ?>
                </div>

                <div class="form-group">
                    <label for="ringkasan">Ringkasan / Excerpt</label>
                    <textarea id="ringkasan" name="ringkasan" rows="3"
                              class="form-control"
                              placeholder="Deskripsi singkat artikel (tampil di list dan SEO meta description)"
                              maxlength="500"><?= e($data['ringkasan'] ?? '') ?></textarea>
                    <p style="font-size:11px;color:var(--text-light);margin-top:4px;">Maks. 500 karakter. Jika kosong, otomatis diambil dari isi artikel.</p>
                </div>

                <div class="form-group">
                    <label for="isi">Isi Artikel <span class="req">*</span></label>
                    <?php include __DIR__ . '/includes/article-toolbar.php'; ?>
                    <textarea id="isi" name="isi" rows="20"
                              placeholder="Tulis konten artikel di sini..."
                              class="form-control article-source<?= isset($errors['isi']) ? ' is-error' : '' ?>"><?= e($data['isi'] ?? '') ?></textarea>
                    <p style="font-size:11px;color:var(--text-light);margin-top:4px;">
                        Gunakan toolbar untuk mengatur format tulisan. Konten tetap dapat ditinjau sebelum dipublikasikan.
                    </p>
                    <?php if (isset($errors['isi'])): ?>
                    <p class="field-error"><?= e($errors['isi']) ?></p>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Sidebar kanan -->
        <div>
            <div class="admin-form-card">
                <h2 class="admin-form-title">Publikasi</h2>

                <div class="form-group">
                    <label for="status">Status</label>
                    <select id="status" name="status" class="form-control">
                        <option value="draft"     <?= ($data['status'] ?? 'draft') === 'draft'     ? 'selected' : '' ?>>Draft</option>
                        <option value="published" <?= ($data['status'] ?? '') === 'published' ? 'selected' : '' ?>>Published</option>
                    </select>
                </div>

                <div class="form-group">
                    <label for="kategori">Kategori <span class="req">*</span></label>
                    <select id="kategori" name="kategori"
                            class="form-control<?= isset($errors['kategori']) ? ' is-error' : '' ?>" required>
                        <option value="">-- Pilih --</option>
                        <?php foreach ($labelKategori as $val => $lbl): ?>
                        <option value="<?= e($val) ?>"
                            <?= ($data['kategori'] ?? '') === $val ? 'selected' : '' ?>>
                            <?= e($lbl) ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                    <?php if (isset($errors['kategori'])): ?>
                    <p class="field-error"><?= e($errors['kategori']) ?></p>
                    <?php endif; ?>
                </div>

                <div class="form-group">
                    <label for="foto">Foto Sampul</label>
                    <input type="file" id="foto" name="foto" accept="image/jpeg,image/png,image/webp"
                           class="form-control<?= isset($errors['foto']) ? ' is-error' : '' ?>">
                    <p style="font-size:11px;color:var(--text-light);margin-top:4px;">JPG/PNG/WebP, maks. 2MB. Akan di-resize otomatis ke 1200px.</p>
                    <?php if (isset($errors['foto'])): ?>
                    <p class="field-error"><?= e($errors['foto']) ?></p>
                    <?php endif; ?>
                </div>

                <div class="form-actions" style="border-top:none;padding-top:0;flex-direction:column;">
                    <button type="submit" name="status" value="published" class="btn-sm btn-sm-primary"
                            style="padding:12px;font-size:14px;width:100%;">
                        Publish Sekarang
                    </button>
                    <button type="submit" name="status" value="draft" class="btn-sm btn-sm-secondary"
                            style="padding:12px;font-size:14px;width:100%;margin-top:8px;">
                        Simpan sebagai Draft
                    </button>
                    <a href="<?= e(BASE_URL . '/admin/artikel') ?>" class="btn-sm btn-sm-secondary"
                       style="padding:12px;font-size:14px;width:100%;margin-top:8px;text-align:center;">
                        Batal
                    </a>
                </div>
            </div>
        </div>
    </div>
</form>

<script>
(function(){var b=document.getElementById('aiGenerate'),sel=document.getElementById('aiProvider'),fields=document.getElementById('aiCustomFields');if(!b||!sel)return;
// Toggle custom fields
function toggleCustom(){fields.style.display=sel.value==='custom'?'block':'none';}
sel.addEventListener('change',toggleCustom);toggleCustom();
b.addEventListener('click',function(){var topic=document.getElementById('aiTopic').value.trim(),status=document.getElementById('aiStatus');if(!topic){status.textContent='Topik wajib diisi.';return;}var provider=sel.value;if(provider==='custom'){var cu=document.getElementById('aiCustomUrl').value.trim(),ck=document.getElementById('aiCustomKey').value.trim();if(!cu||!ck){status.textContent='Custom endpoint: URL dan API Key wajib diisi.';return;}}b.disabled=true;status.textContent='AI sedang menulis...';var body=new FormData();body.append('csrf_token','<?= generateCsrfToken() ?>');body.append('provider',provider);body.append('topic',topic);body.append('tone',document.getElementById('aiTone').value);if(provider==='custom'){body.append('custom_url',document.getElementById('aiCustomUrl').value.trim());body.append('custom_key',document.getElementById('aiCustomKey').value.trim());body.append('custom_model',document.getElementById('aiCustomModel').value.trim());}fetch('<?= BASE_URL ?>/api/generate-article.php',{method:'POST',body:body,credentials:'same-origin'}).then(function(r){return r.json().then(function(j){if(!r.ok)throw new Error(j.error||'Gagal');return j;});}).then(function(j){document.getElementById('judul').value=j.article.judul;document.getElementById('ringkasan').value=j.article.ringkasan;var isi=document.getElementById('isi');isi.value=j.article.isi;isi.dispatchEvent(new Event('input'));status.textContent='Draft AI siap. Silakan tinjau dan edit.';}).catch(function(e){status.textContent=e.message;}).finally(function(){b.disabled=false;});});})();
</script>
<?php require_once __DIR__ . '/includes/footer.php'; ?>
