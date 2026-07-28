<?php
if(isset($_GET['logout'])){unset($_SESSION['santri_id'],$_SESSION['santri_nip'],$_SESSION['santri_name']);redirect('/login-santri');}
$error='';
if($_SERVER['REQUEST_METHOD']==='POST'){
    validateCsrf();$login=strtoupper(sanitizeString($_POST['nomor_induk']??''));$pass=$_POST['password']??'';
    $s=getDB()->prepare("SELECT id,nomor_daftar,nomor_induk,nama_lengkap,portal_password,status FROM pendaftaran WHERE nomor_daftar=? OR nomor_induk=? LIMIT 1");$s->execute([$login,$login]);$p=$s->fetch();
    if($p&&$p['portal_password']&&password_verify($pass,$p['portal_password'])&&$p['status']!=='ditolak'){
        session_regenerate_id(true);$_SESSION['santri_id']=$p['id'];$_SESSION['santri_nip']=$p['nomor_induk']?:$p['nomor_daftar'];$_SESSION['santri_name']=$p['nama_lengkap'];redirect('/portal-santri');
    }$error='Nomor pendaftaran/induk atau password salah.';
}
$activePage='psb';$pageTitle='Login Portal Santri | '.APP_NAME;$pageDescription='Portal calon santri Pondok Pesantren Ash-Shiddiq.';$pageCanonical=BASE_URL.'/login-santri';?>
<main class="page-section"><div class="container narrow-container"><form method="post" class="public-form portal-login"><input type="hidden" name="csrf_token" value="<?=generateCsrfToken()?>"><h1 class="section-title">Portal Santri</h1><p>Gunakan nomor pendaftaran atau nomor induk dan password yang dibuat saat mendaftar.</p><?php if($error):?><div class="flash-message flash-error"><?=e($error)?></div><?php endif;?><div class="form-group"><label>Nomor pendaftaran / nomor induk</label><input class="form-control" name="nomor_induk" required autocomplete="username"></div><div class="form-group"><label>Password</label><input class="form-control" type="password" name="password" required autocomplete="current-password"></div><button class="btn-primary">Masuk ke Portal</button><div class="login-register-prompt"><span>Belum memiliki akun?</span><a href="<?=BASE_URL?>/psb">Daftar sebagai calon santri</a></div></form></div></main>
