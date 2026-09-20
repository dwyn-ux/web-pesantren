#!/bin/bash
# security-scan.sh — Scanner malware/deface untuk shared hosting (Domainesia/Niagahoster)
# Cara pakai: bash tools/security-scan.sh  (jalankan dari root web, sejajar index.php)
# Mode: READ-ONLY, tidak menghapus apapun. Exit 0 = bersih, 1 = ada temuan.
set -u
ROOT="$(cd "$(dirname "$0")/.." && pwd)"
cd "$ROOT" || exit 2
FAIL=0
say() { printf '%s\n' "$*"; }
hit() { printf '  [TEMUAN] %s\n' "$*"; FAIL=1; }
ok()  { printf '  [OK] %s\n' "$*"; }

say "== 1. File backdoor di lokasi upload/public =="
# PHP/script apapun di uploads & assets/img = 99% malware (hasil upload tidak boleh ada .php)
if find uploads assets/img -type f \( -iname '*.php*' -o -iname '*.phtml' -o -iname '*.phar' -o -iname '*.pht' -o -iname '*.cgi' -o -iname '*.pl' -o -iname '*.py' -o -iname '*.sh' -o -iname '*.shtml' \) -print 2>/dev/null | grep -q .; then
  find uploads assets/img -type f \( -iname '*.php*' -o -iname '*.phtml' -o -iname '*.phar' -o -iname '*.pht' -o -iname '*.cgi' -o -iname '*.pl' -o -iname '*.py' -o -iname '*.sh' -o -iname '*.shtml' \) 2>/dev/null | while read -r f; do hit "$f"; done
else
  ok "tidak ada file script di uploads/ & assets/img/"
fi
# HTML/JS/SVG tak diundang di uploads = vektor redirect judi/XSS
if find uploads -type f \( -iname '*.html' -o -iname '*.htm' -o -iname '*.js' -o -iname '*.svg' -o -iname '*.swf' \) -print 2>/dev/null | grep -q .; then
  find uploads -type f \( -iname '*.html' -o -iname '*.htm' -o -iname '*.js' -o -iname '*.svg' -o -iname '*.swf' \) 2>/dev/null | while read -r f; do hit "file curiga: $f"; done
else
  ok "tidak ada html/js/svg di uploads/"
fi
# Double-extension klasik: shell.php.jpg
if find uploads assets -type f -iregex '.*\.\(php[0-9]*\|phtml\|phar\|cgi\|pl\|py\|sh\|html?\|js\|svg\)\..*' -print 2>/dev/null | grep -q .; then
  find uploads assets -type f -iregex '.*\.\(php[0-9]*\|phtml\|phar\|cgi\|pl\|py\|sh\|html?\|js\|svg\)\..*' 2>/dev/null | while read -r f; do hit "double-extension: $f"; done
else
  ok "tidak ada double-extension"
fi

say "== 2. Sisa backdoor & file berbahaya di root =="
for f in setup-admin.php info.php phpinfo.php test.php .env; do
  if [ -e "$f" ]; then hit "$f masih ada — hapus segera"; else ok "$f tidak ada"; fi
done
if [ -d ".git" ]; then hit "folder .git/ ikut ter-deploy (source bocor) — hapus dari production"; else ok ".git tidak ikut deploy"; fi

say "== 3. Pola malware di kode (eval/gzinflate/judi) =="
# Kecualikan scanner ini sendiri + saveSignature() yang memang pakai base64_decode
# secara aman (prefix data:image/png + cek magic bytes PNG + limit 500KB).
if grep -rIn --exclude-dir=.git --exclude-dir=node_modules --exclude='security-scan.sh' -E 'eval[[:space:]]*\(|base64_decode|gzinflate|str_rot13|assert[[:space:]]*\(|create_function|shell_exec|passthru|popen|proc_open|pcntl_exec|`\$\{|\$\{_(GET|POST|REQUEST|COOKIE)}' . 2>/dev/null | grep -v 'function saveSignature' | grep -v 'base64_decode(substr($dataUrl' | grep -q .; then
  grep -rIn --exclude-dir=.git --exclude-dir=node_modules --exclude='security-scan.sh' -E 'eval[[:space:]]*\(|base64_decode|gzinflate|str_rot13|assert[[:space:]]*\(|create_function|shell_exec|passthru|popen|proc_open|pcntl_exec' . 2>/dev/null | grep -v 'function saveSignature' | grep -v 'base64_decode(substr($dataUrl' | head -n 30 | while read -r l; do hit "$l"; done
else
  ok "tidak ada pola RCE umum"
fi
# Keyword judi/slot yang sering disuntik ke template/JS
if grep -rIin --exclude-dir=.git -E 'slot88|slot777|gacor|maxwin|togel|judi bola|sbobet|m88|cmd368' assets includes pages admin api index.php sitemap.php robots.txt 2>/dev/null | grep -q .; then
  grep -rIin --exclude-dir=.git -E 'slot88|slot777|gacor|maxwin|togel|judi bola|sbobet|m88|cmd368' assets includes pages admin api index.php 2>/dev/null | head -n 20 | while read -r l; do hit "$l"; done
else
  ok "tidak ada keyword judi"
fi
# Script eksternal tak dikenal di includes/pages (suntikan JS judi biasanya di sini)
if grep -rIn --exclude-dir=.git -E '<script[^>]+src="http' includes pages admin/index.php index.php 2>/dev/null | grep -v -E 'cdn\.jsdelivr|cdnjs|googletagmanager|google-analytics|gstatic' | grep -q .; then
  grep -rIn --exclude-dir=.git -E '<script[^>]+src="http' includes pages admin/index.php index.php 2>/dev/null | grep -v -E 'cdn\.jsdelivr|cdnjs|googletagmanager|google-analytics|gstatic' | head -n 20 | while read -r l; do hit "script eksternal: $l"; done
else
  ok "tidak ada script eksternal asing"
fi

say "== 4. File baru/diubah 14 hari terakhir (cek satu-satu) =="
find . -path ./.git -prune -o -type f -mtime -14 -print 2>/dev/null | head -n 40
say "(pastikan yang muncul hanya file patch keamanan kamu)"

say "== 5. Permission (shared hosting ideal: dir 755, file 644) =="
find uploads cache logs -type d ! -perm 755 -print 2>/dev/null | head -n 10 | while read -r d; do hit "dir bukan 755: $d"; done
find . -maxdepth 2 -name '*.php' ! -perm 644 -print 2>/dev/null | head -n 10 | while read -r f; do hit "php bukan 644: $f"; done
ok "cek permission selesai"

say "== 6. .htaccess pengaman =="
grep -q '^\s*RewriteRule \^\\.git' .htaccess 2>/dev/null && ok "root .htaccess blokir .git" || hit "root .htaccess BELUM blokir ^\\.git"
grep -q '(?i)' uploads/.htaccess 2>/dev/null && ok "uploads/.htaccess case-insensitive" || hit "uploads/.htaccess belum (?i)"
grep -q 'php_flag engine off' uploads/.htaccess 2>/dev/null && ok "uploads/.htaccess matikan php engine" || hit "uploads/.htaccess belum matikan php engine"

say ""
if [ "$FAIL" -eq 0 ]; then say "HASIL: BERSIH ✅"; else say "HASIL: ADA TEMUAN ❌ — tangani semua [TEMUAN] di atas dulu"; fi
exit "$FAIL"
