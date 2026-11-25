</main>

<?php $navKey = $activeNav ?? ''; $navUser = current_user(); ?>
<!-- Mobile Bottom Navigation -->
<nav class="bx-mobile-bottom-nav" aria-label="Mobile Navigation">
    <a href="<?= e(url('/')) ?>" class="<?= $navKey === 'home' ? 'active' : '' ?>">
        <?= icon('home') ?>
        <span><?= e(t('nav_home')) ?></span>
    </a>
    <a href="<?= e(url('/pdf')) ?>" class="<?= $navKey === 'pdfs' ? 'active' : '' ?>">
        <?= icon('folder') ?>
        <span><?= e(t('nav_pdfs')) ?></span>
    </a>
    <a href="<?= e(url('/search')) ?>" class="<?= $navKey === 'search' ? 'active' : '' ?>">
        <?= icon('search') ?>
        <span><?= e(t('nav_search')) ?></span>
    </a>
    <?php if ($navUser): ?>
        <a href="<?= e(url('/library')) ?>" class="<?= ($navKey === 'library' || $navKey === 'account') ? 'active' : '' ?>">
            <?= icon('book') ?>
            <span><?= e(t('nav_library')) ?></span>
        </a>
    <?php else: ?>
        <a href="<?= e(url('/login')) ?>" class="<?= ($navKey === 'library' || $navKey === 'account') ? 'active' : '' ?>">
            <?= icon('user') ?>
            <span><?= e(t('nav_login')) ?></span>
        </a>
    <?php endif; ?>
</nav>

<footer class="bx-footer">
    <div class="container bx-footer-inner">
        <div style="display:flex;align-items:center;gap:12px;">
            <?= bookexa_logo_svg('bx-logo-svg') ?>
            <span class="bx-muted">— <?= e(t('tagline')) ?></span>
        </div>
        <div style="display:flex;gap:16px;flex-wrap:wrap;align-items:center;">
            <a href="<?= e(url('/about')) ?>"><?= e(t('nav_about')) ?></a>
            <a href="<?= e(url('/contact')) ?>"><?= e(t('nav_contact')) ?></a>
            <?php if (site_setting('shop_enabled', '0') === '1'): ?>
                <a href="<?= e(url('/shop')) ?>"><?= e(t('nav_shop')) ?></a>
            <?php endif; ?>
            <a href="<?= e(url('/terms')) ?>"><?= e(t('legal_terms')) ?></a>
            <a href="<?= e(url('/privacy')) ?>"><?= e(t('legal_privacy')) ?></a>
            <a href="<?= e(url('/refund-policy')) ?>"><?= e(t('legal_refund')) ?></a>
            <a href="<?= e(url('/copyright')) ?>"><?= e(t('legal_copyright')) ?></a>
            <a href="<?= e(url('/sitemap.xml')) ?>">Sitemap</a>
            <span>© <?= e(num_localize(date('Y'))) ?> <?= e(site_setting('site_name', t('site_name'))) ?>. <?= e(t('footer_rights')) ?>.</span>
        </div>
    </div>
</footer>

<script src="<?= e(asset('assets/js/app.js')) ?>" defer></script>
<script src="<?= e(asset('assets/js/motion.js')) ?>" defer></script>
</body>
</html>
