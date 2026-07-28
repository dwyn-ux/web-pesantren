# CLAUDE.md — Project Instructions for Dynamic Web App Conversion

> Baca file ini sebelum melakukan apapun. Ini adalah sumber kebenaran untuk seluruh proyek.

---

## 🎯 PROJECT OVERVIEW

Konversi prototype HTML statis menjadi web aplikasi dinamis yang siap production di **shared hosting Indonesia** (Niagahoster / Domainesia / Hostinger).

Prototype HTML tersedia di folder `/prototype/`. Tugas utama adalah mengubahnya menjadi aplikasi PHP yang **aman, SEO-friendly, dan maintainable**.

---

## 🏗️ TECH STACK (WAJIB IKUTI)

| Layer        | Teknologi                          |
|--------------|------------------------------------|
| Backend      | PHP 8.2+ (native, no framework)    |
| Database     | MySQL 8.0+ via PDO                 |
| Frontend     | Bootstrap 5.3 + Vanilla JS         |
| Auth         | Session-based (PHP native)         |
| Upload       | Local filesystem + validasi ketat  |
| SEO          | Meta tags + Open Graph + sitemap   |

**Jangan gunakan:** Laravel, CodeIgniter, Composer autoload yang kompleks, atau library NPM yang memerlukan build step.

---

## 📁 STRUKTUR FOLDER WAJIB

```
/
├── CLAUDE.md                  ← file ini
├── .htaccess                  ← URL rewriting + security headers
├── index.php                  ← entry point utama
├── config/
│   ├── database.php           ← koneksi PDO
│   ├── constants.php          ← konstanta global (BASE_URL, dll)
│   └── session.php            ← konfigurasi session
├── includes/
│   ├── header.php             ← <head> + navbar
│   ├── footer.php             ← footer + scripts
│   ├── auth.php               ← fungsi auth (login, logout, cek sesi)
│   └── functions.php          ← helper functions global
├── pages/
│   ├── home.php
│   ├── login.php
│   ├── register.php
│   └── [halaman lainnya].php
├── admin/
│   ├── index.php              ← dashboard admin
│   └── [halaman admin].php
├── api/
│   └── [endpoint].php         ← AJAX handlers
├── assets/
│   ├── css/custom.css
│   ├── js/main.js
│   └── img/
├── uploads/                   ← file upload (dilindungi .htaccess)
│   └── .htaccess              ← deny direct access ke uploads
├── migrations/
│   └── 001_initial.sql        ← schema database
└── .env.example               ← template environment variables
```

---

## 🔐 SECURITY — WAJIB IMPLEMENTASI SEMUA

### 1. SQL Injection Prevention
```php
// ✅ SELALU gunakan prepared statements via PDO
$stmt = $pdo->prepare("SELECT * FROM users WHERE email = ?");
$stmt->execute([$email]);

// ❌ DILARANG string concatenation
$sql = "SELECT * FROM users WHERE email = '$email'"; // JANGAN INI
```

### 2. XSS Prevention
```php
// ✅ SELALU escape output
echo htmlspecialchars($data, ENT_QUOTES, 'UTF-8');

// Buat helper function di includes/functions.php:
function e(string $str): string {
    return htmlspecialchars($str, ENT_QUOTES, 'UTF-8');
}
```

### 3. CSRF Protection
```php
// Di session.php — generate token
function generateCsrfToken(): string {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

// Di setiap form HTML
<input type="hidden" name="csrf_token" value="<?= generateCsrfToken() ?>">

// Di handler form — validasi
function validateCsrf(): void {
    if (!hash_equals($_SESSION['csrf_token'] ?? '', $_POST['csrf_token'] ?? '')) {
        http_response_code(403);
        die('Invalid CSRF token');
    }
}
```

### 4. File Upload Security
```php
// Validasi ketat untuk semua upload
function validateUpload(array $file, array $allowedTypes, int $maxSizeBytes): array {
    $errors = [];
    
    // Cek ekstensi (whitelist, bukan blacklist)
    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if (!in_array($ext, $allowedTypes)) {
        $errors[] = "Tipe file tidak diizinkan.";
    }
    
    // Cek MIME type (jangan percaya $_FILES['type'])
    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime = $finfo->file($file['tmp_name']);
    $allowedMimes = ['image/jpeg', 'image/png', 'image/webp']; // sesuaikan
    if (!in_array($mime, $allowedMimes)) {
        $errors[] = "MIME type tidak valid.";
    }
    
    // Cek ukuran
    if ($file['size'] > $maxSizeBytes) {
        $errors[] = "File terlalu besar.";
    }
    
    return $errors;
}

// Rename file — JANGAN pakai nama asli dari user
$newFilename = bin2hex(random_bytes(16)) . '.' . $ext;
```

### 5. Password Hashing
```php
// ✅ Selalu gunakan password_hash
$hash = password_hash($password, PASSWORD_BCRYPT);

// ✅ Verifikasi
if (password_verify($inputPassword, $hash)) { /* ok */ }
```

### 6. Session Security
```php
// Di config/session.php — set ini SEBELUM session_start()
ini_set('session.cookie_httponly', 1);
ini_set('session.cookie_samesite', 'Strict');
ini_set('session.use_strict_mode', 1);
if (isset($_SERVER['HTTPS'])) {
    ini_set('session.cookie_secure', 1);
}
session_start();

// Regenerate session ID setelah login
session_regenerate_id(true);
```

### 7. .htaccess Security Headers
```apache
# Selalu sertakan ini di root .htaccess
Header set X-Content-Type-Options "nosniff"
Header set X-Frame-Options "SAMEORIGIN"
Header set X-XSS-Protection "1; mode=block"
Header set Referrer-Policy "strict-origin-when-cross-origin"
Header set Content-Security-Policy "default-src 'self'; script-src 'self' https://cdn.jsdelivr.net; style-src 'self' 'unsafe-inline' https://cdn.jsdelivr.net; img-src 'self' data: https:;"

# Sembunyikan versi PHP
Header unset X-Powered-By
ServerSignature Off

# Protect sensitive files
<FilesMatch "\.(env|sql|md|log|sh|git)$">
    Require all denied
</FilesMatch>

# Protect config folder
<IfModule mod_rewrite.c>
    RewriteEngine On
    RewriteRule ^config/ - [F,L]
    RewriteRule ^includes/ - [F,L]
    RewriteRule ^migrations/ - [F,L]
</IfModule>
```

### 8. Input Validation
```php
// Selalu sanitasi input sebelum diproses
function sanitizeString(string $input): string {
    return trim(strip_tags($input));
}

function sanitizeEmail(string $email): string|false {
    return filter_var(trim($email), FILTER_SANITIZE_EMAIL);
}

function validateEmail(string $email): bool {
    return (bool) filter_var($email, FILTER_VALIDATE_EMAIL);
}
```

---

## 🔍 SEO — IMPLEMENTASI STANDAR

### Meta Tags Dinamis
Setiap halaman HARUS punya meta tags dinamis. Buat sistem di `includes/header.php`:

```php
// Di setiap page file, set variabel ini SEBELUM include header
$pageTitle = "Judul Halaman | Nama Situs";
$pageDescription = "Deskripsi halaman 150-160 karakter.";
$pageKeywords = "kata kunci, relevan, halaman ini";
$pageCanonical = BASE_URL . "/path-halaman";
$pageOgImage = BASE_URL . "/assets/img/og-default.jpg";

// Di includes/header.php
<title><?= e($pageTitle ?? 'Default Title | Nama Situs') ?></title>
<meta name="description" content="<?= e($pageDescription ?? 'Default description') ?>">
<link rel="canonical" href="<?= e($pageCanonical ?? BASE_URL) ?>">

<!-- Open Graph -->
<meta property="og:title" content="<?= e($pageTitle ?? '') ?>">
<meta property="og:description" content="<?= e($pageDescription ?? '') ?>">
<meta property="og:image" content="<?= e($pageOgImage ?? '') ?>">
<meta property="og:url" content="<?= e($pageCanonical ?? '') ?>">
<meta property="og:type" content="website">
<meta property="og:locale" content="id_ID">

<!-- Twitter Card -->
<meta name="twitter:card" content="summary_large_image">
<meta name="twitter:title" content="<?= e($pageTitle ?? '') ?>">
<meta name="twitter:description" content="<?= e($pageDescription ?? '') ?>">
```

### URL Bersih via .htaccess
```apache
RewriteEngine On
RewriteCond %{REQUEST_FILENAME} !-f
RewriteCond %{REQUEST_FILENAME} !-d
RewriteRule ^(.*)$ index.php?page=$1 [QSA,L]
```

### Sitemap XML
Buat file `sitemap.php` yang generate XML dinamis dari database:
```php
<?php
header('Content-Type: application/xml; charset=UTF-8');
echo '<?xml version="1.0" encoding="UTF-8"?>';
?>
<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">
  <url>
    <loc><?= BASE_URL ?></loc>
    <changefreq>weekly</changefreq>
    <priority>1.0</priority>
  </url>
  <!-- Loop dari database untuk halaman dinamis -->
</urlset>
```

### robots.txt
```
User-agent: *
Allow: /
Disallow: /admin/
Disallow: /api/
Disallow: /config/
Disallow: /uploads/
Sitemap: https://yourdomain.com/sitemap.xml
```

---

## 🗄️ DATABASE

### Konvensi Penamaan
- Tabel: `snake_case` plural (e.g., `users`, `blog_posts`)
- Kolom: `snake_case` (e.g., `created_at`, `user_id`)
- Primary key: selalu `id` INT UNSIGNED AUTO_INCREMENT
- Timestamps: selalu sertakan `created_at` dan `updated_at`

### Template Tabel Wajib
```sql
-- users table (wajib ada)
CREATE TABLE `users` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `name` VARCHAR(100) NOT NULL,
  `email` VARCHAR(150) NOT NULL UNIQUE,
  `password` VARCHAR(255) NOT NULL,
  `role` ENUM('user','admin') NOT NULL DEFAULT 'user',
  `is_active` TINYINT(1) NOT NULL DEFAULT 1,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  INDEX `idx_email` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

### PDO Connection (config/database.php)
```php
<?php
function getDB(): PDO {
    static $pdo = null;
    if ($pdo === null) {
        $host = $_ENV['DB_HOST'] ?? 'localhost';
        $db   = $_ENV['DB_NAME'] ?? '';
        $user = $_ENV['DB_USER'] ?? '';
        $pass = $_ENV['DB_PASS'] ?? '';
        
        try {
            $pdo = new PDO(
                "mysql:host=$host;dbname=$db;charset=utf8mb4",
                $user, $pass,
                [
                    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES   => false,
                ]
            );
        } catch (PDOException $e) {
            error_log("DB Connection failed: " . $e->getMessage());
            die("Database connection error. Please try again later.");
        }
    }
    return $pdo;
}
```

---

## ⚙️ ENVIRONMENT & KONFIGURASI

Gunakan file `.env` (simpan di luar public root jika memungkinkan, atau lindungi via .htaccess):

```env
# .env
APP_NAME=NamaAplikasi
APP_URL=https://domain.com
APP_ENV=production
APP_DEBUG=false

DB_HOST=localhost
DB_NAME=nama_database
DB_USER=user_db
DB_PASS=password_db
```

Load di `config/constants.php`:
```php
<?php
// Load .env sederhana tanpa library
if (file_exists(__DIR__ . '/../.env')) {
    $lines = file(__DIR__ . '/../.env', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (str_starts_with(trim($line), '#')) continue;
        [$key, $value] = explode('=', $line, 2);
        $_ENV[trim($key)] = trim($value);
    }
}

define('BASE_URL', rtrim($_ENV['APP_URL'] ?? '', '/'));
define('APP_NAME', $_ENV['APP_NAME'] ?? 'My App');
define('IS_DEBUG', ($_ENV['APP_DEBUG'] ?? 'false') === 'true');

// Error reporting
if (IS_DEBUG) {
    error_reporting(E_ALL);
    ini_set('display_errors', 1);
} else {
    error_reporting(0);
    ini_set('display_errors', 0);
    ini_set('log_errors', 1);
}
```

---

## 📋 CARA KERJA ROUTING (index.php)

```php
<?php
require_once __DIR__ . '/config/constants.php';
require_once __DIR__ . '/config/session.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/auth.php';

$page = $_GET['page'] ?? 'home';

// Whitelist halaman yang diizinkan
$publicPages = ['home', 'login', 'register', 'about', 'contact'];
$authPages   = ['dashboard', 'profile', 'upload'];
$adminPages  = ['admin/dashboard', 'admin/users', 'admin/posts'];

// Cek akses
if (in_array($page, $adminPages) && !isAdmin()) {
    redirect('/login');
}
if (in_array($page, $authPages) && !isLoggedIn()) {
    redirect('/login');
}

// Load halaman
$filePath = __DIR__ . '/pages/' . $page . '.php';
if (!file_exists($filePath) || !in_array($page, array_merge($publicPages, $authPages, $adminPages))) {
    $filePath = __DIR__ . '/pages/404.php';
    http_response_code(404);
}

include __DIR__ . '/includes/header.php';
include $filePath;
include __DIR__ . '/includes/footer.php';
```

---

## 🚀 PERFORMA & SHARED HOSTING

### Caching sederhana
```php
// Cache output HTML untuk halaman statis (opsional)
function cacheGet(string $key): string|false {
    $file = __DIR__ . '/../cache/' . md5($key) . '.html';
    if (file_exists($file) && (time() - filemtime($file)) < 3600) {
        return file_get_contents($file);
    }
    return false;
}
```

### Optimasi Gambar Upload
```php
// Resize gambar upload agar tidak membebani server
function resizeImage(string $source, string $dest, int $maxWidth = 1200): bool {
    [$width, $height, $type] = getimagesize($source);
    if ($width <= $maxWidth) {
        return copy($source, $dest);
    }
    $ratio = $maxWidth / $width;
    $newH  = (int)($height * $ratio);
    $canvas = imagecreatetruecolor($maxWidth, $newH);
    $src = match($type) {
        IMAGETYPE_JPEG => imagecreatefromjpeg($source),
        IMAGETYPE_PNG  => imagecreatefrompng($source),
        IMAGETYPE_WEBP => imagecreatefromwebp($source),
        default        => false
    };
    if (!$src) return false;
    imagecopyresampled($canvas, $src, 0, 0, 0, 0, $maxWidth, $newH, $width, $height);
    return match($type) {
        IMAGETYPE_JPEG => imagejpeg($canvas, $dest, 85),
        IMAGETYPE_PNG  => imagepng($canvas, $dest, 6),
        IMAGETYPE_WEBP => imagewebp($canvas, $dest, 85),
        default        => false
    };
}
```

---

## ✅ CHECKLIST SEBELUM DEPLOY

Sebelum push ke production, verifikasi semua poin ini:

**Security**
- [ ] Semua query pakai prepared statements PDO
- [ ] Semua output di-escape dengan `e()` atau `htmlspecialchars()`
- [ ] CSRF token ada di semua form POST
- [ ] Upload divalidasi (ekstensi, MIME, ukuran)
- [ ] Password di-hash dengan `password_hash()`
- [ ] Session dikonfigurasi dengan benar
- [ ] `.htaccess` melindungi folder sensitif
- [ ] `APP_DEBUG=false` di production
- [ ] File `.env` dilindungi / tidak bisa diakses publik

**SEO**
- [ ] Setiap halaman punya `<title>` dan `<meta description>` unik
- [ ] Canonical URL terpasang
- [ ] Open Graph tags lengkap
- [ ] `sitemap.xml` bisa diakses
- [ ] `robots.txt` sudah benar
- [ ] URL bersih (tanpa `.php` di URL)
- [ ] Gambar punya atribut `alt`

**Performa**
- [ ] Gambar upload di-resize sebelum disimpan
- [ ] CSS/JS dari CDN (Bootstrap, dll) pakai integrity hash
- [ ] Tidak ada query N+1 di loop

---

## 🤖 INSTRUKSI UNTUK CLAUDE CODE

Ketika membantu proyek ini:

1. **Selalu ikuti struktur folder** yang sudah didefinisikan di atas
2. **Jangan pernah skip security** — setiap form, query, dan upload WAJIB divalidasi
3. **Konversi HTML ke PHP** — wrap konten dalam sistem header/footer yang konsisten
4. **Satu file, satu tanggung jawab** — jangan campur logika bisnis dengan tampilan
5. **Komentar dalam Bahasa Indonesia** — sesuai konteks proyek lokal
6. **Saat membuat migration SQL** — selalu sertakan `IF NOT EXISTS` dan charset `utf8mb4`
7. **Saat ada pilihan library** — pilih yang bisa diload via CDN tanpa build step
8. **Error handling** — selalu tangkap exception dan log, jangan tampilkan error mentah ke user

### Urutan Pengerjaan yang Disarankan
```
1. Setup struktur folder & file konfigurasi
2. Buat migration SQL (schema database)
3. Buat sistem routing (index.php + .htaccess)
4. Buat includes/ (header, footer, auth, functions)
5. Konversi halaman HTML → PHP (mulai dari publik, lalu auth)
6. Implementasi auth (login, register, logout)
7. Implementasi CRUD sesuai kebutuhan
8. Implementasi upload file
9. SEO meta tags & sitemap
10. Testing & checklist deploy
```
