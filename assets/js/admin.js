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

}());
