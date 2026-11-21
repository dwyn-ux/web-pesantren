<?php
/**
 * Modal Pop-up Detail Alumni — Reusable (Web SMP Style)
 * Digunakan oleh Beranda (home.php) dan Halaman Alumni (pages/alumni.php).
 */
?>
<!-- Pop up detail alumni -->
<div id="alumni-modal" class="alumni-modal-root" role="dialog" aria-modal="true" aria-labelledby="alumni-modal-name" aria-hidden="true">
    <div id="alumni-modal-backdrop" class="alumni-backdrop"></div>

    <div class="alumni-dialog" id="alumni-modal-dialog">
        <button type="button" id="alumni-modal-close" class="alumni-modal-close" aria-label="Tutup detail alumni">
            <svg class="alumni-icon" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" d="M6 6l12 12M18 6L6 18"/>
            </svg>
        </button>

        <!-- Foto Alumni -->
        <div class="alumni-modal-photo-col">
            <img id="alumni-modal-photo-bg" src="" alt="" aria-hidden="true" class="alumni-modal-photo-bg">
            <img id="alumni-modal-photo" src="" alt="" class="alumni-modal-photo">
        </div>

        <!-- Detail Konten -->
        <div class="alumni-modal-content-col">
            <div id="alumni-modal-badges" class="alumni-modal-badges"></div>
            <h2 id="alumni-modal-name" class="alumni-modal-name"></h2>
            <div class="alumni-modal-divider"></div>

            <div class="alumni-modal-details">
                <!-- Kampus / Perguruan Tinggi -->
                <div id="alumni-modal-campus-wrap" class="alumni-detail-item">
                    <span class="alumni-detail-icon bg-green-soft">
                        <svg class="alumni-icon" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 14l9-5-9-5-9 5 9 5z"/>
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 14l6.16-3.422a12.083 12.083 0 01.665 6.479A11.952 11.952 0 0012 20.055a11.952 11.952 0 00-6.824-2.998 12.078 12.078 0 01.665-6.479L12 14z"/>
                        </svg>
                    </span>
                    <div class="alumni-detail-text">
                        <p class="alumni-detail-label">Pendidikan / Kampus saat ini</p>
                        <p id="alumni-modal-campus" class="alumni-detail-val"></p>
                    </div>
                </div>

                <!-- Pekerjaan / Karir -->
                <div id="alumni-modal-work-wrap" class="alumni-detail-item">
                    <span class="alumni-detail-icon bg-gold-soft">
                        <svg class="alumni-icon" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M21 13.255A23.931 23.931 0 0112 15c-3.183 0-6.22-.62-9-1.745M16 6V4a2 2 0 00-2-2h-4a2 2 0 00-2 2v2m4 6h.01M5 20h14a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/>
                        </svg>
                    </span>
                    <div class="alumni-detail-text">
                        <p class="alumni-detail-label">Pekerjaan / Instansi saat ini</p>
                        <p id="alumni-modal-work" class="alumni-detail-val"></p>
                    </div>
                </div>

                <!-- Alamat / Kota Asal -->
                <div id="alumni-modal-address-wrap" class="alumni-detail-item">
                    <span class="alumni-detail-icon bg-blue-soft">
                        <svg class="alumni-icon" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/>
                            <path stroke-linecap="round" stroke-linejoin="round" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/>
                        </svg>
                    </span>
                    <div class="alumni-detail-text">
                        <p class="alumni-detail-label">Alamat / Asal</p>
                        <p id="alumni-modal-address" class="alumni-detail-val"></p>
                    </div>
                </div>
            </div>

            <!-- Kesan & Pesan -->
            <div id="alumni-modal-message-wrap" class="alumni-modal-message-wrap">
                <span class="alumni-modal-quote-badge" aria-hidden="true">&ldquo;</span>
                <p class="alumni-modal-message-title">Kesan &amp; pesan</p>
                <p id="alumni-modal-message" class="alumni-modal-message-body"></p>
            </div>
        </div>
    </div>
</div>
