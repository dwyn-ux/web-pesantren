/**
 * alumni-modal.js — Pop-up detail alumni interaktif (Web SMP Style)
 * Mendukung pembukaan detail, scroll lock, keyboard Escape, focus management,
 * dan animasi transisi halus.
 */

(function () {
  'use strict';

  function initAlumniModal() {
    var modal = document.getElementById('alumni-modal');
    if (!modal) return;

    var photo = document.getElementById('alumni-modal-photo');
    var photoBg = document.getElementById('alumni-modal-photo-bg');
    var nameEl = document.getElementById('alumni-modal-name');
    var badgesEl = document.getElementById('alumni-modal-badges');
    var campusWrap = document.getElementById('alumni-modal-campus-wrap');
    var campusEl = document.getElementById('alumni-modal-campus');
    var workWrap = document.getElementById('alumni-modal-work-wrap');
    var workEl = document.getElementById('alumni-modal-work');
    var addrWrap = document.getElementById('alumni-modal-address-wrap');
    var addrEl = document.getElementById('alumni-modal-address');
    var msgWrap = document.getElementById('alumni-modal-message-wrap');
    var msgEl = document.getElementById('alumni-modal-message');
    var closeBtn = document.getElementById('alumni-modal-close');
    var backdrop = document.getElementById('alumni-modal-backdrop');
    var lastFocus = null;

    function addBadge(text, cls) {
      if (!text || !badgesEl) return;
      var span = document.createElement('span');
      span.className = 'alumni-pill-badge ' + (cls || '');
      span.textContent = text;
      badgesEl.appendChild(span);
    }

    function openModal(card) {
      var d = card.dataset;
      lastFocus = card;

      var photoSrc = d.photo || '';
      if (photo) {
        photo.src = photoSrc;
        photo.alt = 'Foto ' + (d.name || 'Alumni');
      }
      if (photoBg) {
        photoBg.src = photoSrc;
      }
      if (nameEl) {
        nameEl.textContent = d.name || '';
      }

      if (badgesEl) {
        badgesEl.innerHTML = '';
        if (d.year) {
          addBadge('Angkatan ' + d.year, 'badge-gold');
        }
        if (d.activityLabel) {
          addBadge(d.activityLabel, 'badge-emerald');
        }
      }

      // Kampus / Program Studi
      var campusParts = [];
      if (d.campus && d.campus.trim() !== '') campusParts.push(d.campus.trim());
      if (d.major && d.major.trim() !== '') campusParts.push(d.major.trim());
      var campusText = campusParts.join(' — ');
      if (campusWrap && campusEl) {
        if (campusText !== '') {
          campusEl.textContent = campusText;
          campusWrap.classList.remove('hidden');
        } else {
          campusWrap.classList.add('hidden');
        }
      }

      // Tempat Kerja / Jabatan
      var workParts = [];
      if (d.job && d.job.trim() !== '') workParts.push(d.job.trim());
      if (d.company && d.company.trim() !== '') workParts.push('di ' + d.company.trim());
      var workText = workParts.join(' ');
      if (workWrap && workEl) {
        if (workText !== '') {
          workEl.textContent = workText;
          workWrap.classList.remove('hidden');
        } else {
          workWrap.classList.add('hidden');
        }
      }

      // Alamat / Kota Asal
      if (addrWrap && addrEl) {
        var addrText = (d.address || '').trim();
        if (addrText !== '') {
          addrEl.textContent = addrText;
          addrWrap.classList.remove('hidden');
        } else {
          addrWrap.classList.add('hidden');
        }
      }

      // Pesan & Kesan
      if (msgWrap && msgEl) {
        var msgText = (d.message || '').trim();
        if (msgText !== '') {
          msgEl.textContent = msgText;
          msgWrap.classList.remove('hidden');
        } else {
          msgWrap.classList.add('hidden');
        }
      }

      modal.classList.add('open');
      modal.setAttribute('aria-hidden', 'false');
      document.body.style.overflow = 'hidden';

      if (closeBtn) {
        setTimeout(function () {
          closeBtn.focus();
        }, 50);
      }
    }

    function closeModal() {
      modal.classList.remove('open');
      modal.setAttribute('aria-hidden', 'true');
      document.body.style.overflow = '';
      if (photo) photo.src = '';
      if (photoBg) photoBg.src = '';
      if (lastFocus && typeof lastFocus.focus === 'function') {
        try { lastFocus.focus(); } catch (e) {}
      }
    }

    // Pasang listener pada semua kartu alumni
    document.querySelectorAll('.alumni-card, .home-alumni-card').forEach(function (card) {
      card.addEventListener('click', function () {
        openModal(card);
      });
    });

    if (closeBtn) closeBtn.addEventListener('click', closeModal);
    if (backdrop) backdrop.addEventListener('click', closeModal);
    modal.addEventListener('click', function (e) {
      if (e.target === modal) closeModal();
    });

    document.addEventListener('keydown', function (e) {
      if (e.key === 'Escape' && modal.classList.contains('open')) {
        closeModal();
      }
    });
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initAlumniModal);
  } else {
    initAlumniModal();
  }
})();
