<?php
/**
 * Footer global — footer HTML + scripts
 */
$currentYear = date('Y');
?>

<!-- ══ FOOTER ════════════════════════════════════════════════ -->
<footer>
    <div class="footer-inner">
        <div class="footer-top">

            <!-- Branding -->
            <div class="footer-brand">
                <div class="footer-logo">
                    <?php $footerLogo = getLogoFile(); if ($footerLogo !== ''): ?>
                    <img class="footer-logo-image" src="<?= BASE_URL ?>/assets/img/<?= $footerLogo ?>" alt="Logo <?= e(APP_NAME) ?>">
                    <?php else: ?>
                    <div class="footer-logo-icon" aria-hidden="true">ص</div>
                    <?php endif; ?>
                    <div>
                        <h3><?= e(APP_NAME) ?></h3>
                        <span class="footer-logo-sub">Tahfidz Al-Qur'an</span>
                    </div>
                </div>
                <p>Lembaga pendidikan Islam berbasis Tahfidz Al-Qur'an yang berkomitmen mencetak generasi Qur'ani berakhlak mulia sejak 2020.</p>
                <div class="footer-arabic" aria-label="Al-Shiddiq dalam tulisan Arab">الصِّدِّيقُ</div>
            </div>

            <!-- Navigasi -->
            <div class="footer-col">
                <h4>Navigasi</h4>
                <ul>
                    <li><a href="<?= BASE_URL ?>/">Beranda</a></li>
                    <li><a href="<?= BASE_URL ?>/profil">Profil</a></li>
                    <li><a href="<?= BASE_URL ?>/sekapur-sirih">Sekapur Sirih</a></li>
                    <li><a href="<?= BASE_URL ?>/artikel">Artikel</a></li>
                    <li><a href="<?= BASE_URL ?>/psb">PSB</a></li>
                </ul>
            </div>

            <!-- Program -->
            <div class="footer-col">
                <h4>Program</h4>
                <ul>
                    <li><a href="<?= BASE_URL ?>/profil#program">Tahfidz Al-Qur'an</a></li>
                    <li><a href="<?= BASE_URL ?>/profil#program">Tahsin &amp; Tajwid</a></li>
                    <li><a href="<?= BASE_URL ?>/profil#program">Kitab Kuning</a></li>
                    <li><a href="<?= BASE_URL ?>/profil#program">SMP &amp; SMA</a></li>
                </ul>
            </div>

            <!-- Kontak -->
            <div class="footer-col footer-contact">
                <h4>Kontak</h4>
                <address>
                    <p><span aria-hidden="true">📍</span> Purworejo, Rt 03/02, Jurangjero, Ngawen, Gunungkiduk, DI. Yogyakarta, 55853</p>
                    <p><span aria-hidden="true">📞</span> <a href="tel:+6283873475302">+62 838-7347-5302</a></p>
                    <p><span aria-hidden="true">💬</span> <a href="https://wa.me/6283873475302" rel="noopener">WhatsApp Kami</a></p>
                    <p><span aria-hidden="true">✉</span> <a href="mailto:ponpesashiddiq2@gmail.com">ponpesashiddiq2@gmail.com</a></p>
                </address>
            </div>

        </div><!-- /.footer-top -->

        <div class="footer-bottom">
            <span>© <?= $currentYear ?> <?= e(APP_NAME) ?>. Hak Cipta Dilindungi.</span>
        </div>
    </div>
</footer>

<!-- ══ SCRIPTS ════════════════════════════════════════════════ -->
<script src="<?= BASE_URL ?>/assets/js/main.js?v=<?= ASSET_VERSION ?>" defer></script>
<?php if (!empty($extraScripts)) echo $extraScripts; ?>

</body>
</html>
