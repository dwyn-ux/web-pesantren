/**
 * notif.js — toast + modal konfirmasi sesuai template web Ash-Shiddiq.
 * Vanilla JS, tanpa dependensi. Dimuat di header (defer) publik + admin.
 *
 * - window.asqToast(type, msg) -> toast success/error/info/warning
 * - window.asqConfirm(msg, {title, yes, danger}) -> Promise<boolean>
 * - window.alert() dialihkan ke toast agar gaya seragam (non-blocking)
 * - Inline onsubmit/onclick confirm(...) otomatis di-upgrade ke modal;
 *   elemen [data-confirm] ditangani terpusat di sini.
 */
(function () {
  'use strict';

  var ICONS = { success: '✓', error: '✕', info: 'i', warning: '!' };

  function box() {
    var b = document.getElementById('asqToasts');
    if (!b) {
      b = document.createElement('div');
      b.id = 'asqToasts';
      b.className = 'asq-toasts';
      b.setAttribute('aria-live', 'polite');
      document.body.appendChild(b);
    }
    return b;
  }

  function toast(type, msg) {
    type = ICONS[type] ? type : 'info';
    var b = box();
    while (b.children.length >= 4) b.removeChild(b.firstChild);
    var el = document.createElement('div');
    el.className = 'asq-toast asq-' + type;
    el.setAttribute('role', type === 'error' ? 'alert' : 'status');
    var ic = document.createElement('span');
    ic.className = 'asq-toast-icon';
    ic.setAttribute('aria-hidden', 'true');
    ic.textContent = ICONS[type];
    var tx = document.createElement('span');
    tx.className = 'asq-toast-text';
    tx.textContent = String(msg);
    var x = document.createElement('button');
    x.className = 'asq-toast-close';
    x.setAttribute('aria-label', 'Tutup');
    x.textContent = '×';
    x.onclick = function () { el.remove(); };
    el.appendChild(ic);
    el.appendChild(tx);
    el.appendChild(x);
    b.appendChild(el);
    setTimeout(function () {
      el.classList.add('asq-hide');
      setTimeout(function () { el.remove(); }, 400);
    }, 4500);
  }

  window.asqToast = toast;

  // ── Riwayat notifikasi (localStorage, maks 20) ──
  var RIWAYAT_KEY = 'asq_notif_riwayat';

  function bacaRiwayat() {
    try {
      var d = JSON.parse(localStorage.getItem(RIWAYAT_KEY) || '[]');
      return Array.isArray(d) ? d : [];
    } catch (e) { return []; }
  }

  function catatRiwayat(type, msg) {
    var list = bacaRiwayat();
    list.unshift({ type: type, msg: String(msg), at: Date.now(), read: false });
    try { localStorage.setItem(RIWAYAT_KEY, JSON.stringify(list.slice(0, 20))); } catch (e) {}
    renderBell();
  }

  var toastAsli = toast;
  toast = function (type, msg) {
    toastAsli(type, msg);
    catatRiwayat(type, msg);
  };
  window.asqToast = toast;

  function waktuRelatif(ts) {
    var s = Math.floor((Date.now() - ts) / 1000);
    if (s < 60) return 'baru saja';
    if (s < 3600) return Math.floor(s / 60) + ' mnt lalu';
    if (s < 86400) return Math.floor(s / 3600) + ' jam lalu';
    return new Date(ts).toLocaleDateString('id-ID', { day: 'numeric', month: 'short' });
  }

  function renderBell() {
    var bell = document.getElementById('notifBell');
    var panel = document.getElementById('notifPanel');
    if (!bell || !panel) return;
    var list = bacaRiwayat();
    var unread = list.filter(function (n) { return !n.read; }).length;
    var badge = bell.querySelector('.notif-count');
    badge.hidden = unread === 0;
    badge.textContent = unread > 9 ? '9+' : String(unread);
    if (list.length === 0) {
      panel.innerHTML = '<div class="notif-panel-head"><span>Notifikasi</span></div><div class="notif-empty">Belum ada notifikasi.</div>';
      return;
    }
    var html = '<div class="notif-panel-head"><span>Notifikasi</span>'
      + '<button type="button" id="notifClear">Tandai dibaca</button></div>';
    list.forEach(function (n) {
      html += '<div class="notif-item ' + (n.read ? 'read' : 'unread') + '">'
        + '<span class="notif-dot"></span>'
        + '<div class="notif-item-text">' + n.msg.replace(/</g, '&lt;')
        + '<span class="notif-item-time">' + waktuRelatif(n.at) + '</span></div></div>';
    });
    panel.innerHTML = html;
    var clear = document.getElementById('notifClear');
    if (clear) clear.addEventListener('click', function () {
      var l = bacaRiwayat().map(function (n) { n.read = true; return n; });
      try { localStorage.setItem(RIWAYAT_KEY, JSON.stringify(l)); } catch (e) {}
      renderBell();
    });
  }

  function pasangBell() {
    if (document.getElementById('notifBell')) return;
    var bell = document.createElement('button');
    bell.id = 'notifBell';
    bell.className = 'notif-bell';
    bell.setAttribute('aria-label', 'Riwayat notifikasi');
    bell.innerHTML = '&#128276;<span class="notif-count" hidden>0</span>';
    var panel = document.createElement('div');
    panel.id = 'notifPanel';
    panel.className = 'notif-panel';
    panel.hidden = true;
    panel.setAttribute('role', 'dialog');
    panel.setAttribute('aria-label', 'Riwayat notifikasi');
    document.body.appendChild(bell);
    document.body.appendChild(panel);
    bell.addEventListener('click', function (e) {
      e.stopPropagation();
      panel.hidden = !panel.hidden;
      if (!panel.hidden) renderBell();
    });
    document.addEventListener('click', function (e) {
      if (!panel.hidden && !panel.contains(e.target)) panel.hidden = true;
    });
    // Catat flash PHP yang tampil saat load agar bisa dicek ulang
    document.querySelectorAll('.flash-message').forEach(function (f) {
      var body = f.querySelector('.flash-body');
      if (body) catatRiwayat(f.getAttribute('data-notif-type') || 'info', body.textContent.trim());
    });
    renderBell();
  }

  document.addEventListener('DOMContentLoaded', pasangBell);
  if (document.readyState !== 'loading') pasangBell();

  window.alert = function (msg) {
    var s = String(msg == null ? '' : msg);
    if (/^(gagal|error)/i.test(s)) toast('error', s);
    else if (/pilih file|terlalu besar|maks \d/i.test(s)) toast('warning', s);
    else toast('info', s);
  };

  // ── Modal konfirmasi ──
  var overlay = null, msgEl = null, yesBtn = null, resolver = null;

  function ensureModal() {
    if (overlay) return;
    overlay = document.createElement('div');
    overlay.className = 'asq-overlay';
    overlay.hidden = true;
    overlay.innerHTML =
      '<div class="asq-modal" role="alertdialog" aria-modal="true" aria-labelledby="asqModalTitle">' +
      '<div class="asq-modal-icon" aria-hidden="true">?</div>' +
      '<h3 class="asq-modal-title" id="asqModalTitle">Konfirmasi</h3>' +
      '<p class="asq-modal-msg"></p>' +
      '<div class="asq-modal-actions">' +
      '<button type="button" class="btn-outline asq-no">Batal</button>' +
      '<button type="button" class="btn-primary asq-yes">Ya, Lanjutkan</button>' +
      '</div></div>';
    document.body.appendChild(overlay);
    msgEl = overlay.querySelector('.asq-modal-msg');
    yesBtn = overlay.querySelector('.asq-yes');
    overlay.querySelector('.asq-no').addEventListener('click', function () { tutup(false); });
    yesBtn.addEventListener('click', function () { tutup(true); });
    overlay.addEventListener('click', function (e) { if (e.target === overlay) tutup(false); });
    document.addEventListener('keydown', function (e) {
      if (e.key === 'Escape' && overlay && !overlay.hidden) tutup(false);
    });
  }

  function tutup(ok) {
    overlay.hidden = true;
    document.body.style.overflow = '';
    var r = resolver;
    resolver = null;
    if (r) r(ok);
  }

  window.asqConfirm = function (msg, opts) {
    ensureModal();
    opts = opts || {};
    if (resolver) resolver(false);
    msgEl.textContent = String(msg);
    overlay.querySelector('.asq-modal-title').textContent = opts.title || 'Konfirmasi';
    overlay.querySelector('.asq-modal-icon').textContent = opts.danger ? '!' : '?';
    yesBtn.classList.toggle('danger', !!opts.danger);
    yesBtn.textContent = opts.yes || 'Ya, Lanjutkan';
    overlay.hidden = false;
    document.body.style.overflow = 'hidden';
    yesBtn.focus();
    return new Promise(function (res) { resolver = res; });
  };

  // ── Upgrade inline confirm(...) jadi data-confirm ──
  function upgradeInline() {
    document.querySelectorAll('form[onsubmit]').forEach(function (f) {
      var m = String(f.getAttribute('onsubmit') || '').match(/confirm\s*\(\s*(['"])([\s\S]*?)\1\s*\)/);
      if (m && !f.hasAttribute('data-confirm')) {
        f.setAttribute('data-confirm', m[2]);
        f.removeAttribute('onsubmit');
        f.onsubmit = null;
      }
    });
    document.querySelectorAll('[onclick]').forEach(function (el) {
      if (el.nodeName === 'FORM') return;
      var m = String(el.getAttribute('onclick') || '').match(/return\s+confirm\s*\(\s*(['"])([\s\S]*?)\1\s*\)/);
      if (!m) return;
      var form = el.closest ? el.closest('form') : null;
      if (form && !form.hasAttribute('data-confirm')) form.setAttribute('data-confirm', m[2]);
      else if (!form && !el.hasAttribute('data-confirm')) el.setAttribute('data-confirm', m[2]);
      el.removeAttribute('onclick');
      el.onclick = null;
    });
  }

  // ── Delegasi terpusat [data-confirm] ──
  document.addEventListener('submit', function (e) {
    var f = e.target;
    if (!f || f.nodeName !== 'FORM') return;
    var msg = f.getAttribute('data-confirm');
    if (!msg) return;
    e.preventDefault();
    window.asqConfirm(msg, { danger: true }).then(function (ok) {
      if (ok) f.submit();
    });
  }, true);

  document.addEventListener('click', function (e) {
    var el = e.target && e.target.closest ? e.target.closest('[data-confirm]') : null;
    if (!el || el.nodeName === 'FORM') return;
    e.preventDefault();
    e.stopPropagation();
    var msg = el.getAttribute('data-confirm');
    window.asqConfirm(msg, { danger: true }).then(function (ok) {
      if (!ok) return;
      if (el.tagName === 'A' && el.href) window.location.href = el.href;
      else if (el.form) el.form.submit();
    });
  }, true);

  document.addEventListener('DOMContentLoaded', upgradeInline);
  if (document.readyState !== 'loading') upgradeInline();
})();
