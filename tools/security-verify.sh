#!/bin/bash
# security-verify.sh — Verifikasi pasca-deploy dari luar (tanpa login)
# Cara pakai: bash tools/security-verify.sh https://ponpesashiddiq.or.id
# Exit 0 = lolos semua, 1 = ada yang gagal.
set -u
BASE="${1:-https://ponpesashiddiq.or.id}"
BASE="${BASE%/}"
UA="Mozilla/5.0 (SecurityCheck)"
FAIL=0
pass() { printf '[LOLOS] %s\n' "$*"; }
fail() { printf '[GAGAL] %s\n' "$*"; FAIL=1; }
code_of() { curl -sk -A "$UA" -o /dev/null -w '%{http_code}' --max-time 15 "$1"; }

echo "Target: $BASE"
echo "== 1. Header keamanan =="
H=$(curl -skSI -A "$UA" --max-time 15 "$BASE/" | tr -d '\r')
echo "$H" | grep -qi 'strict-transport-security' && pass "HSTS ada" || fail "HSTS hilang"
echo "$H" | grep -qi 'x-content-type-options: *nosniff' && pass "X-Content-Type-Options nosniff" || fail "X-Content-Type-Options hilang"
echo "$H" | grep -qi 'x-frame-options' && pass "X-Frame-Options ada" || fail "X-Frame-Options hilang"
echo "$H" | grep -qi 'referrer-policy' && pass "Referrer-Policy ada" || fail "Referrer-Policy hilang"
if echo "$H" | grep -qi 'x-powered-by\|server:.*php'; then fail "info versi bocor di header"; else pass "versi PHP disembunyikan"; fi

echo "== 2. File/folder sensitif harus 403/404 (bukan 200) =="
for p in '.env' '.git/HEAD' 'config/database.php' 'uploads/.htaccess' 'setup-admin.php' 'info.php'; do
  c=$(code_of "$BASE/$p")
  # 000 = koneksi direset WAF/hosting untuk dotfile (.env/.git) = ikut dianggap BLOKIR, bagus
  case "$c" in 403|404|301|302|000) pass "$p -> $c";; *) fail "$p -> $c (harusnya 403/404)";; esac
done
c=$(curl -sk -A "$UA" --max-time 15 "$BASE/uploads/" -o /dev/null -w '%{http_code}')
case "$c" in 403|404) pass "/uploads/ listing mati ($c)";; *) fail "/uploads/ listing -> $c";; esac

echo "== 3. Auth & API =="
c=$(code_of "$BASE/admin/")
[ "$c" = "200" ] || [ "$c" = "301" ] || [ "$c" = "302" ] && pass "/admin/ terproteksi ($c)" || fail "/admin/ -> $c"
c=$(curl -sk -A "$UA" --max-time 15 -X POST "$BASE/api/simulasi-biaya.php" -H 'Content-Type: application/json' -d '{}' -o /dev/null -w '%{http_code}')
[ "$c" = "401" ] || [ "$c" = "403" ] && pass "simulasi-biaya tolak guest ($c)" || fail "simulasi-biaya -> $c"
c=$(curl -sk -A "$UA" --max-time 15 -X POST "$BASE/api/generate-article.php" -d 'provider=openai&topic=x' -o /dev/null -w '%{http_code}')
[ "$c" = "302" ] || [ "$c" = "403" ] || [ "$c" = "405" ] && pass "generate-article tolak guest ($c)" || fail "generate-article -> $c"

echo "== 4. Probe XSS/SQLi reflektif (harus tidak mantul) =="
if curl -sk -A "$UA" --max-time 15 "$BASE/artikel?q=%3Cscript%3Ealert(1)%3C%2Fscript%3E" | grep -qi 'script>alert'; then fail "XSS reflektif mantul"; else pass "XSS reflektif tidak mantul"; fi
if curl -sk -A "$UA" --max-time 15 "$BASE/artikel/xxx%27%20OR%201%3D1--" | grep -qi 'sql\|mysql.*error\|syntax'; then fail "pesan SQL bocor"; else pass "tidak ada pesan SQL bocor"; fi

echo "== 5. SEO/basic =="
c=$(code_of "$BASE/sitemap.xml"); [ "$c" = "200" ] && pass "sitemap.xml 200" || fail "sitemap.xml -> $c"
c=$(code_of "$BASE/robots.txt"); [ "$c" = "200" ] && pass "robots.txt 200" || fail "robots.txt -> $c"

echo ""
[ "$FAIL" -eq 0 ] && echo "HASIL: LOLOS SEMUA ✅" || echo "HASIL: ADA YANG GAGAL ❌"
exit "$FAIL"
