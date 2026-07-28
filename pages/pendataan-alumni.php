<?php
$activePage='alumni'; $pageTitle='Pendataan Alumni | '.APP_NAME; $pageDescription='Form pendataan alumni Pondok Pesantren Ash-Shiddiq.'; $pageCanonical=BASE_URL.'/pendataan-alumni';
$errors=[]; $data=[];
if ($_SERVER['REQUEST_METHOD']==='POST') {
  validateCsrf();
  foreach (['nama','alamat','aktivitas','tempat_kuliah','jurusan','tempat_bekerja','jabatan','pesan_kesan','saran'] as $key) $data[$key]=sanitizeString($_POST[$key]??'');
  $data['tahun_kelulusan']=sanitizeInt($_POST['tahun_kelulusan']??0); $now=(int)date('Y');
  if ($data['nama']==='') $errors['nama']='Nama wajib diisi.';
  if ($data['tahun_kelulusan']<1950 || $data['tahun_kelulusan']>$now) $errors['tahun_kelulusan']='Tahun kelulusan tidak valid.';
  if ($data['alamat']==='') $errors['alamat']='Alamat wajib diisi.';
  if (!in_array($data['aktivitas'],['kuliah','bekerja'],true)) $errors['aktivitas']='Pilih aktivitas saat ini.';
  if ($data['aktivitas']==='kuliah' && ($data['tempat_kuliah']==='' || $data['jurusan']==='')) $errors['detail']='Tempat kuliah dan jurusan wajib diisi.';
  if ($data['aktivitas']==='bekerja' && ($data['tempat_bekerja']==='' || $data['jabatan']==='')) $errors['detail']='Tempat bekerja dan posisi wajib diisi.';
  if ($data['pesan_kesan']==='') $errors['pesan_kesan']='Pesan dan kesan wajib diisi.';
  $foto=null;
  if (empty($_FILES['foto']['name'])) $errors['foto']='Foto terbaik wajib diunggah.';
  else { $up=validateUpload($_FILES['foto'],['jpg','jpeg','png','webp'],['image/jpeg','image/png','image/webp'],5242880); if($up)$errors['foto']=implode(' ',$up);else{$foto=saveUpload($_FILES['foto'],UPLOADS_PATH.'/alumni');if($foto){[$w,$h]=getimagesize(UPLOADS_PATH.'/alumni/'.$foto);$orientasi=$w>$h?'landscape':'portrait';}} }
  if (!$errors) {
    getDB()->prepare("INSERT INTO alumni (nama,tahun_kelulusan,alamat,aktivitas,tempat_kuliah,jurusan,tempat_bekerja,jabatan,pesan_kesan,saran,foto,orientasi) VALUES (?,?,?,?,?,?,?,?,?,?,?,?)")->execute([$data['nama'],$data['tahun_kelulusan'],$data['alamat'],$data['aktivitas'],$data['aktivitas']==='kuliah'?$data['tempat_kuliah']:null,$data['aktivitas']==='kuliah'?$data['jurusan']:null,$data['aktivitas']==='bekerja'?$data['tempat_bekerja']:null,$data['aktivitas']==='bekerja'?$data['jabatan']:null,$data['pesan_kesan'],$data['saran']?:null,$foto,$orientasi??'landscape']);
    setFlash('success','Terima kasih. Data alumni berhasil dikirim dan menunggu verifikasi admin.'); redirect('/pendataan-alumni');
  }
}
?>
<main class="page-section"><div class="container narrow-container"><div class="section-tag"><span></span><span class="section-tag-text">Jejak Alumni</span><span></span></div><h1 class="section-title">Pendataan Alumni</h1><p class="section-desc">Isi data terbaru dengan benar. Foto sebaiknya foto sendiri, wajah terlihat jelas, dan bukan foto berkelompok.</p>
<?php if($errors): ?><div class="flash-message flash-error">Periksa kembali: <?= e(implode(' ',array_values($errors))) ?></div><?php endif; ?>
<form method="post" enctype="multipart/form-data" class="public-form alumni-form"><input type="hidden" name="csrf_token" value="<?= generateCsrfToken() ?>">
<div class="form-grid"><div class="form-group"><label for="nama">Nama lengkap *</label><input class="form-control" id="nama" name="nama" maxlength="120" required value="<?= e($data['nama']??'') ?>"></div><div class="form-group"><label for="tahun_kelulusan">Tahun kelulusan *</label><input class="form-control" type="number" id="tahun_kelulusan" name="tahun_kelulusan" min="1950" max="<?= date('Y') ?>" required value="<?= e((string)($data['tahun_kelulusan']??'')) ?>"></div></div>
<div class="form-group"><label for="alamat">Alamat saat ini *</label><textarea class="form-control" id="alamat" name="alamat" required><?= e($data['alamat']??'') ?></textarea></div>
<div class="form-group"><label for="aktivitas">Aktivitas saat ini *</label><select class="form-control" id="aktivitas" name="aktivitas" required><option value="">Pilih</option><option value="kuliah" <?= ($data['aktivitas']??'')==='kuliah'?'selected':'' ?>>Kuliah</option><option value="bekerja" <?= ($data['aktivitas']??'')==='bekerja'?'selected':'' ?>>Bekerja</option></select></div>
<div id="kuliahFields" class="conditional-fields"><div class="form-grid"><div class="form-group"><label>Tempat kuliah *</label><input class="form-control" name="tempat_kuliah" value="<?= e($data['tempat_kuliah']??'') ?>"></div><div class="form-group"><label>Jurusan *</label><input class="form-control" name="jurusan" value="<?= e($data['jurusan']??'') ?>"></div></div></div>
<div id="kerjaFields" class="conditional-fields"><div class="form-grid"><div class="form-group"><label>Tempat bekerja *</label><input class="form-control" name="tempat_bekerja" value="<?= e($data['tempat_bekerja']??'') ?>"></div><div class="form-group"><label>Bekerja sebagai *</label><input class="form-control" name="jabatan" value="<?= e($data['jabatan']??'') ?>"></div></div></div>
<div class="form-group"><label>Pesan dan kesan *</label><textarea class="form-control" name="pesan_kesan" required><?= e($data['pesan_kesan']??'') ?></textarea></div><div class="form-group"><label>Saran</label><textarea class="form-control" name="saran"><?= e($data['saran']??'') ?></textarea></div>
<div class="form-group"><label>Foto terbaik (sendiri dan terlihat jelas) *</label><input class="form-control" type="file" name="foto" accept="image/jpeg,image/png,image/webp" required><small>JPG, PNG, atau WebP. Maksimal 5 MB.</small></div><button class="btn-primary" type="submit">Kirim Data Alumni</button></form></div></main>
<?php $extraScripts='<script>(function(){var s=document.getElementById("aktivitas"),k=document.getElementById("kuliahFields"),b=document.getElementById("kerjaFields");function x(){k.hidden=s.value!=="kuliah";b.hidden=s.value!=="bekerja";}s.addEventListener("change",x);x();})();</script>'; ?>
