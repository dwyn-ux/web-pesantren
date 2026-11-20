/* admin.js — UI helpers panel admin */
(function () {
    'use strict';

    // Tutup sidebar saat klik overlay (mobile)
    document.addEventListener('click', function (e) {
        var sidebar = document.getElementById('adminSidebar');
        var toggle  = document.getElementById('sidebarToggle');
        if (!sidebar || !toggle) return;
        if (sidebar.classList.contains('open')
            && !sidebar.contains(e.target)
            && e.target !== toggle) {
            sidebar.classList.remove('open');
        }
    });

    // Konfirmasi hapus ditangani terpusat oleh notif.js (modal template)

    // Sidebar grup collapsible — ingat state buka/tutup per grup
    var GROUP_KEY = 'asq_sidebar_groups';
    function bacaGrup() {
        try { return JSON.parse(localStorage.getItem(GROUP_KEY) || '[]'); }
        catch (e) { return []; }
    }
    document.querySelectorAll('.sidebar-group').forEach(function (group) {
        var id = group.getAttribute('data-group') || '';
        // Grup berisi halaman aktif selalu terbuka
        if (group.querySelector('.sidebar-sublink.active')) {
            group.classList.add('open');
        } else if (bacaGrup().indexOf(id) !== -1) {
            group.classList.add('open');
        }
        var btn = group.querySelector('.sidebar-group-toggle');
        if (!btn) return;
        btn.setAttribute('aria-expanded', group.classList.contains('open') ? 'true' : 'false');
        btn.addEventListener('click', function () {
            var terbuka = group.classList.toggle('open');
            btn.setAttribute('aria-expanded', terbuka ? 'true' : 'false');
            try {
                var list = bacaGrup().filter(function (x) { return x !== id; });
                if (terbuka) list.push(id);
                localStorage.setItem(GROUP_KEY, JSON.stringify(list));
            } catch (e) { /* abaikan */ }
        });
    });

    // Auto-dismiss flash
    document.querySelectorAll('.flash-message').forEach(function (msg) {
        setTimeout(function () {
            msg.style.transition = 'opacity 0.5s';
            msg.style.opacity    = '0';
            setTimeout(function () { msg.remove(); }, 500);
        }, 5000);
    });

    // Auto-kompres gambar upload (foto sampul artikel, foto profil, dll.)
    function inisialisasiKompresUpload() {
        if (typeof window.kompresGambar !== 'function') return;

        var inputs = document.querySelectorAll('input[type="file"]#foto, input[type="file"][data-compress="true"]');
        inputs.forEach(function (input) {
            if (input.dataset.compressBound) return;
            input.dataset.compressBound = '1';

            var container = input.parentElement;
            if (!container) return;

            var infoBox = document.createElement('div');
            infoBox.className = 'foto-compress-info';
            infoBox.style.cssText = 'margin-top:8px;font-size:12px;display:none;align-items:center;gap:10px;padding:8px 12px;background:#f8fafc;border:1px solid #e2e8f0;border-radius:6px;';
            input.insertAdjacentElement('afterend', infoBox);

            var form = input.closest('form');
            var submitBtns = form ? form.querySelectorAll('button[type="submit"]') : [];

            input.addEventListener('change', function () {
                var file = input.files && input.files[0];
                if (!file || !/^image\//.test(file.type || '')) {
                    infoBox.style.display = 'none';
                    return;
                }

                function fmt(b) {
                    if (b < 1024) return b + ' B';
                    if (b < 1048576) return (b / 1024).toFixed(1) + ' KB';
                    return (b / 1048576).toFixed(2) + ' MB';
                }

                var origSize = file.size;
                infoBox.style.display = 'flex';
                infoBox.innerHTML = '<span style="color:#d97706;display:inline-flex;align-items:center;gap:6px;">' +
                    '⏳ Mengompres & mengoptimalkan gambar...</span>';

                submitBtns.forEach(function (btn) { btn.disabled = true; });

                // Target 800KB, max 1600px sisi terpanjang
                window.kompresGambar(file, 819200, 1600).then(function (compressed) {
                    submitBtns.forEach(function (btn) { btn.disabled = false; });

                    if (window.DataTransfer && compressed instanceof File) {
                        try {
                            var dt = new DataTransfer();
                            dt.items.add(compressed);
                            input.files = dt.files;
                        } catch (e) {
                            // Abaikan jika browser membatasi modifikasi input.files
                        }
                    }

                    var newSize = compressed.size || origSize;
                    var thumbUrl = (window.URL || window.webkitURL).createObjectURL(compressed);
                    var detail = origSize > newSize
                        ? 'Dioptimalkan: ' + fmt(origSize) + ' &rarr; <strong style="color:#0f172a;">' + fmt(newSize) + '</strong> (Hemat ' + Math.round((1 - newSize / origSize) * 100) + '%)'
                        : 'Ukuran siap upload: <strong style="color:#0f172a;">' + fmt(newSize) + '</strong>';

                    infoBox.innerHTML = '<img src="' + thumbUrl + '" style="width:48px;height:48px;object-fit:cover;border-radius:4px;border:1px solid #cbd5e1;flex-shrink:0;" alt="Preview">' +
                        '<div>' +
                        '<div style="color:#16a34a;font-weight:600;margin-bottom:2px;">✓ Gambar siap diunggah</div>' +
                        '<div style="color:#64748b;font-size:11px;">' + detail + '</div>' +
                        '</div>';
                }).catch(function () {
                    submitBtns.forEach(function (btn) { btn.disabled = false; });
                    infoBox.innerHTML = '<span style="color:#16a34a;">✓ Gambar dipilih (' + fmt(origSize) + ')</span>';
                });
            });
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', inisialisasiKompresUpload);
    } else {
        inisialisasiKompresUpload();
    }

}());
