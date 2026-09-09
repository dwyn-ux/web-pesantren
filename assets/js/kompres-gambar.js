/**
 * kompres-gambar.js — kompres gambar client-side ke ≤1MB sebelum upload.
 * Vanilla JS, tanpa dependensi. Dipakai form upload berkas portal santri.
 *
 * window.kompresGambar(file) -> Promise<File>
 * - Non-gambar / gambar ≤ target: dikembalikan apa adanya.
 * - Gambar besar: resize (maks 1600px sisi terpanjang) + loop quality JPEG
 *   sampai ≤ target. Output selalu JPEG latar putih (aman untuk foto & scan).
 * - Gagal baca (mis. HEIC): kembalikan file asli, server yang menolak
 *   dengan pesan jelas.
 */
(function () {
  'use strict';

  window.kompresGambar = function (file, targetBytes, maxDim) {
    targetBytes = targetBytes || 1048576; // 1MB
    maxDim = maxDim || 1600;
    return new Promise(function (resolve) {
      if (!file || !/^image\//.test(file.type || '') || file.size <= targetBytes) {
        resolve(file);
        return;
      }
      var url = (window.URL || window.webkitURL).createObjectURL(file);
      var img = new Image();
      img.onload = function () {
        (window.URL || window.webkitURL).revokeObjectURL(url);
        var w = img.naturalWidth || img.width;
        var h = img.naturalHeight || img.height;
        if (!w || !h) { resolve(file); return; }
        var skala = Math.min(1, maxDim / Math.max(w, h));
        var nw = Math.max(1, Math.round(w * skala));
        var nh = Math.max(1, Math.round(h * skala));
        var kanvas = document.createElement('canvas');
        kanvas.width = nw;
        kanvas.height = nh;
        var ctx = kanvas.getContext('2d');
        ctx.fillStyle = '#ffffff';
        ctx.fillRect(0, 0, nw, nh);
        ctx.drawImage(img, 0, 0, nw, nh);
        var qualities = [0.85, 0.75, 0.65, 0.55];
        var i = 0;
        (function coba() {
          try {
            kanvas.toBlob(function (blob) {
              if (blob && (blob.size <= targetBytes || i >= qualities.length - 1)) {
                var nama = String(file.name || 'foto').replace(/\.\w+$/, '') + '.jpg';
                try {
                  resolve(new File([blob], nama, { type: 'image/jpeg' }));
                } catch (e) {
                  resolve(blob);
                }
              } else {
                i++;
                coba();
              }
            }, 'image/jpeg', qualities[i]);
          } catch (e) {
            resolve(file);
          }
        })();
      };
      img.onerror = function () {
        (window.URL || window.webkitURL).revokeObjectURL(url);
        resolve(file);
      };
      img.src = url;
    });
  };

  function setStatus(item, cls, txt) {
    if (!item) return;
    var st = item.querySelector('.berkas-status');
    if (st) { st.className = 'berkas-status ' + cls; st.textContent = txt; }
  }

  window.initUploadBerkas = function () {
    var csrfEl = document.getElementById('csrfGlobal') || document.querySelector('input[name="csrf_token"]');
    var csrf = csrfEl ? csrfEl.value : '';
    document.querySelectorAll('.btn-upload, .btn-upload-pelengkap').forEach(function (btn) {
      if (btn.dataset.bound) return;
      btn.dataset.bound = '1';
      btn.addEventListener('click', function () {
        var jenis = btn.dataset.jenis;
        var item = btn.closest('.berkas-item');
        var input = item ? item.querySelector('.berkas-input')
                         : document.querySelector('.berkas-input[data-jenis="' + jenis + '"]');
        if (!input || !input.files || !input.files[0]) { alert('Pilih file terlebih dahulu.'); return; }
        var file = input.files[0];
        var isImg = /^image\//.test(file.type || '');
        if (!isImg && file.size > 5 * 1048576) { alert('File terlalu besar (maks 5MB untuk PDF).'); return; }
        if (isImg && file.size > 10 * 1048576) { alert('Gambar terlalu besar (maks 10MB).'); return; }
        btn.disabled = true;
        function kirim(f, info) {
          setStatus(item, 'pending', info || 'Uploading...');
          btn.textContent = 'Uploading...';
          var fd = new FormData();
          fd.append('csrf_token', csrf);
          fd.append('jenis', jenis);
          fd.append('file', f, f.name || 'file');
          fetch('/api/upload-berkas.php', { method: 'POST', body: fd, credentials: 'same-origin' })
            .then(function (r) {
              return r.text().then(function (t) {
                var d = null;
                try { d = JSON.parse(t); } catch (e) { /* 500 HTML */ }
                return { status: r.status, json: d };
              });
            })
            .then(function (res) {
              if (res.json && res.json.success) {
                setStatus(item, 'success', '✓ Sudah diupload');
                input.value = '';
                var action = item ? item.querySelector('.berkas-action') : null;
                if (action && !action.querySelector('a.btn-link')) {
                  var a = document.createElement('a');
                  a.href = '/berkas-santri?jenis=' + encodeURIComponent(jenis);
                  a.target = '_blank';
                  a.className = 'btn-link';
                  a.textContent = 'Lihat';
                  action.appendChild(a);
                }
              } else {
                var msg = (res.json && res.json.message) || ('Server error (' + res.status + ')');
                setStatus(item, 'error', 'Gagal: ' + msg);
                alert('Gagal: ' + msg);
              }
              btn.disabled = false;
              btn.textContent = 'Upload';
            })
            .catch(function (e) {
              setStatus(item, 'error', 'Jaringan gagal');
              alert('Error: ' + e.message);
              btn.disabled = false;
              btn.textContent = 'Upload';
            });
        }
        if (isImg && file.size > 1048576) {
          setStatus(item, 'pending', 'Mengompres...');
          btn.textContent = 'Mengompres...';
          window.kompresGambar(file).then(function (f2) {
            kirim(f2, 'Uploading... (' + Math.round(file.size / 1024) + 'KB→' + Math.round((f2.size || file.size) / 1024) + 'KB)');
          });
        } else {
          kirim(file, null);
        }
      });
    });
  };

  document.addEventListener('DOMContentLoaded', function () {
    if (document.querySelector('.btn-upload, .btn-upload-pelengkap')) window.initUploadBerkas();
  });
})();
