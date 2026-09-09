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
