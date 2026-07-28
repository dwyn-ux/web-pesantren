/**
 * main.js — Pondok Pesantren Ash-Shiddiq
 * Vanilla JS untuk: navbar scroll, mobile nav, scroll reveal, counter
 */

(function () {
  'use strict';

  // ── Navbar scroll effect ────────────────────────────────────
  const nav = document.getElementById('mainNav');
  if (nav) {
    window.addEventListener('scroll', function () {
      nav.classList.toggle('scrolled', window.scrollY > 30);
    }, { passive: true });
  }

  // ── Mobile nav ──────────────────────────────────────────────
  const hamburger   = document.getElementById('navHamburger');
  const mobileNav   = document.getElementById('mobileNav');
  const mobileClose = document.getElementById('mobileNavClose');

  function openMobileNav() {
    if (!mobileNav) return;
    mobileNav.classList.add('open');
    mobileNav.setAttribute('aria-hidden', 'false');
    document.body.style.overflow = 'hidden';
    if (hamburger) hamburger.setAttribute('aria-expanded', 'true');
  }

  function closeMobileNav() {
    if (!mobileNav) return;
    mobileNav.classList.remove('open');
    mobileNav.setAttribute('aria-hidden', 'true');
    document.body.style.overflow = '';
    if (hamburger) hamburger.setAttribute('aria-expanded', 'false');
  }

  if (hamburger)   hamburger.addEventListener('click', openMobileNav);
  if (mobileClose) mobileClose.addEventListener('click', closeMobileNav);

  // Tutup saat klik link di mobile nav
  if (mobileNav) {
    mobileNav.querySelectorAll('a').forEach(function (link) {
      link.addEventListener('click', closeMobileNav);
    });
  }

  // Tutup saat tekan Escape
  document.addEventListener('keydown', function (e) {
    if (e.key === 'Escape') closeMobileNav();
  });

  // ── Scroll Reveal (IntersectionObserver) ───────────────────
  const revealEls = document.querySelectorAll('.reveal, .reveal-left, .reveal-right');
  if (revealEls.length > 0 && 'IntersectionObserver' in window) {
    const observer = new IntersectionObserver(function (entries) {
      entries.forEach(function (entry) {
        if (entry.isIntersecting) {
          entry.target.classList.add('visible');
          observer.unobserve(entry.target);
        }
      });
    }, { threshold: 0.12 });

    revealEls.forEach(function (el) { observer.observe(el); });
  } else {
    // Fallback untuk browser lama — langsung tampilkan
    revealEls.forEach(function (el) { el.classList.add('visible'); });
  }

  // ── Animated Counter ────────────────────────────────────────
  function animateCounter(el, target, duration) {
    var start = 0;
    var step  = target / (duration / 16);
    var timer = setInterval(function () {
      start += step;
      if (start >= target) {
        el.textContent = target.toLocaleString('id-ID') + (el.dataset.suffix || '');
        clearInterval(timer);
      } else {
        el.textContent = Math.floor(start).toLocaleString('id-ID') + (el.dataset.suffix || '');
      }
    }, 16);
  }

  var counterEls = document.querySelectorAll('[data-counter]');
  if (counterEls.length > 0 && 'IntersectionObserver' in window) {
    var counterObserver = new IntersectionObserver(function (entries) {
      entries.forEach(function (entry) {
        if (entry.isIntersecting) {
          var el     = entry.target;
          var target = parseInt(el.dataset.counter, 10);
          animateCounter(el, target, 1500);
          counterObserver.unobserve(el);
        }
      });
    }, { threshold: 0.5 });

    counterEls.forEach(function (el) { counterObserver.observe(el); });
  }

  // ── Auto-dismiss flash messages ─────────────────────────────
  document.querySelectorAll('.flash-message').forEach(function (msg) {
    setTimeout(function () {
      msg.style.transition = 'opacity 0.5s';
      msg.style.opacity    = '0';
      setTimeout(function () { msg.remove(); }, 500);
    }, 5000);
  });

  // ── Konfirmasi hapus (tombol dengan data-confirm) ───────────
  document.addEventListener('click', function (e) {
    var btn = e.target.closest('[data-confirm]');
    if (!btn) return;
    if (!window.confirm(btn.dataset.confirm || 'Apakah Anda yakin?')) {
      e.preventDefault();
    }
  });

  // ── Aktifkan tab / accordion sederhana ─────────────────────
  document.querySelectorAll('[data-tab]').forEach(function (btn) {
    btn.addEventListener('click', function () {
      var group  = btn.dataset.tabGroup;
      var target = btn.dataset.tab;

      // Nonaktifkan semua dalam grup
      document.querySelectorAll('[data-tab-group="' + group + '"]').forEach(function (el) {
        el.classList.remove('active');
      });
      document.querySelectorAll('[data-tab-content="' + group + '"]').forEach(function (panel) {
        panel.classList.remove('active');
        panel.hidden = true;
      });

      // Aktifkan yang dipilih
      btn.classList.add('active');
      var panel = document.querySelector('[data-tab-content="' + group + '"][data-tab-id="' + target + '"]');
      if (panel) { panel.classList.add('active'); panel.hidden = false; }
    });
  });

})();
