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

    // Konfirmasi hapus
    document.addEventListener('click', function (e) {
        var btn = e.target.closest('[data-confirm]');
        if (!btn) return;
        if (!window.confirm(btn.dataset.confirm || 'Yakin ingin menghapus data ini?')) {
            e.preventDefault();
        }
    });

    // Auto-dismiss flash
    document.querySelectorAll('.flash-message').forEach(function (msg) {
        setTimeout(function () {
            msg.style.transition = 'opacity 0.5s';
            msg.style.opacity    = '0';
            setTimeout(function () { msg.remove(); }, 500);
        }, 5000);
    });

}());
