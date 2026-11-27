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

<?php
$fLang = current_lang();
$customFooter = trim(site_setting($fLang === 'bn' ? 'footer_text_bn' : 'footer_text_en', ''));
$footerNote = $customFooter !== '' ? $customFooter : t('tagline');
$socialLinks = [
    'Facebook'  => site_setting('social_facebook', ''),
    'YouTube'   => site_setting('social_youtube', ''),
    'Telegram'  => site_setting('social_telegram', ''),
    'WhatsApp'  => site_setting('social_whatsapp', ''),
    'Instagram' => site_setting('social_instagram', ''),
];
$socialLinks = array_filter($socialLinks, static fn(string $u): bool => trim($u) !== '');
?>
<footer class="bx-footer">
    <div class="container" style="margin-bottom:18px;">
        <div class="bx-newsletter-box">
            <div style="flex:1 1 240px;min-width:0;">
                <strong style="display:block;font-size:0.96rem;color:var(--text);">
                    <?= $fLang === 'bn' ? 'নতুন বই ও স্টাডি গাইড আপডেট পান' : 'Get new study guides & PDF updates' ?>
                </strong>
                <span class="bx-muted" style="font-size:0.82rem;">
                    <?= $fLang === 'bn' ? 'ডাবল অপ্ট-ইন নিশ্চিতকরণসহ সাপ্তাহিক পড়াশোনার আপডেট — কোনো স্প্যাম নয়।' : 'Weekly study notes & new PDFs with double opt-in confirmation.' ?>
                </span>
            </div>
            <form class="bx-newsletter-form" data-newsletter-form action="<?= e(url('/api/newsletter/subscribe')) ?>" method="post">
                <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
                <input type="hidden" name="source_path" value="<?= e(defined('BX_LOGICAL_ROUTE') ? BX_LOGICAL_ROUTE : '/') ?>">
                <input type="email" name="email" class="bx-input" required placeholder="<?= e($fLang === 'bn' ? 'আপনার ইমেইল ঠিকানা...' : 'you@example.com') ?>" aria-label="<?= e($fLang === 'bn' ? 'নিউজলেটার ইমেইল' : 'Newsletter email') ?>">
                <button type="submit" class="bx-btn bx-btn-primary bx-btn-sm"><?= $fLang === 'bn' ? 'সাবস্ক্রাইব' : 'Subscribe' ?></button>
            </form>
        </div>
    </div>
    <div class="container bx-footer-inner">
        <div style="display:flex;align-items:center;gap:12px;flex-wrap:wrap;">
            <?= bookexa_logo_svg('bx-logo-svg') ?>
            <span class="bx-muted">— <?= e($footerNote) ?></span>
        </div>
        <div class="bx-footer-links">
            <?php foreach ($socialLinks as $sName => $sUrl): ?>
                <a href="<?= e($sUrl) ?>" target="_blank" rel="noopener noreferrer"><?= e($sName) ?></a>
            <?php endforeach; ?>
            <a href="<?= e(url('/guides')) ?>"><?= e(t('nav_guides')) ?></a>
            <a href="<?= e(url('/authors')) ?>"><?= e(t('nav_authors')) ?></a>
            <?php
            $footerHubs = [];
            try {
                $footerHubs = db_all('SELECT slug, title_bn, title_en FROM hubs WHERE is_active = 1 ORDER BY sort_order ASC, id ASC LIMIT 4');
            } catch (Throwable $e) {
            }
            foreach ($footerHubs as $fh): ?>
                <a href="<?= e(url('/hub/' . rawurlencode((string) $fh['slug']))) ?>"><?= e($fLang === 'bn' ? $fh['title_bn'] : $fh['title_en']) ?></a>
            <?php endforeach; ?>
            <a href="<?= e(url('/editorial-policy')) ?>"><?= e(t('legal_editorial')) ?></a>
            <a href="<?= e(url('/how-we-review')) ?>"><?= e(t('legal_review_policy')) ?></a>
            <a href="<?= e(url('/corrections-policy')) ?>"><?= e(t('legal_corrections')) ?></a>
            <a href="<?= e(url('/faq')) ?>"><?= e(t('nav_faq')) ?></a>
            <a href="<?= e(url('/llms.txt')) ?>">llms.txt</a>
            <a href="<?= e(url('/about')) ?>"><?= e(t('nav_about')) ?></a>
            <a href="<?= e(url('/contact')) ?>"><?= e(t('nav_contact')) ?></a>
            <?php if (site_setting('shop_enabled', '0') === '1'): ?>
                <a href="<?= e(url('/shop')) ?>"><?= e(t('nav_shop')) ?></a>
                <a href="<?= e(url('/shop/track')) ?>"><?= e(t('nav_track_order')) ?></a>
            <?php endif; ?>
            <a href="<?= e(url('/terms')) ?>"><?= e(t('legal_terms')) ?></a>
            <a href="<?= e(url('/privacy')) ?>"><?= e(t('legal_privacy')) ?></a>
            <a href="<?= e(url('/refund-policy')) ?>"><?= e(t('legal_refund')) ?></a>
            <a href="<?= e(url('/copyright')) ?>"><?= e(t('legal_copyright')) ?></a>
            <a href="<?= e(url('/sitemap.xml')) ?>">Sitemap</a>
            <?php if (site_setting('cookie_banner_enabled', '1') === '1'): ?>
                <button type="button" class="bx-btn bx-btn-ghost bx-btn-sm" id="bx-cookie-prefs-btn">
                    <?= $fLang === 'bn' ? 'কুকি সেটিংস' : 'Cookie Preferences' ?>
                </button>
            <?php endif; ?>
            <span class="bx-footer-copy">© <?= e(num_localize(date('Y'))) ?> <?= e(site_setting('site_name', t('site_name'))) ?>. <?= e(t('footer_rights')) ?>.</span>
        </div>
    </div>
</footer>

<!-- Global Slide-Out Mini-Cart Drawer -->
<div class="bx-cart-drawer-wrap" id="bx-cart-drawer-wrap" aria-hidden="true">
    <div class="bx-cart-backdrop" id="bx-cart-backdrop"></div>
    <aside class="bx-cart-drawer" role="dialog" aria-label="<?= e(t('nav_cart')) ?>">
        <div class="bx-cart-drawer-head">
            <strong style="font-size:1.05rem;font-family:var(--font-serif);"><?= icon('cart') ?> <?= e(t('nav_cart')) ?></strong>
            <button type="button" class="bx-pill-btn" id="bx-cart-close" aria-label="Close"><?= icon('close') ?></button>
        </div>
        <div class="bx-cart-drawer-body" id="bx-cart-drawer-body">
            <p class="bx-muted" style="text-align:center;padding:24px 0;"><?= current_lang() === 'bn' ? 'লোড হচ্ছে...' : 'Loading cart...' ?></p>
        </div>
        <div class="bx-cart-drawer-foot" id="bx-cart-drawer-foot" hidden>
            <div style="display:flex;justify-content:space-between;font-weight:800;margin-bottom:10px;color:var(--primary);">
                <span><?= current_lang() === 'bn' ? 'সাবটোটাল' : 'Subtotal' ?></span>
                <span id="bx-mini-sub">৳0</span>
            </div>
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:8px;">
                <a href="<?= e(url('/cart')) ?>" class="bx-btn bx-btn-outline bx-btn-sm"><?= current_lang() === 'bn' ? 'কার্ট দেখুন' : 'View Cart' ?></a>
                <a href="<?= e(url('/shop/checkout')) ?>" class="bx-btn bx-btn-accent bx-btn-sm"><?= current_lang() === 'bn' ? 'চেকআউট' : 'Checkout' ?></a>
            </div>
        </div>
    </aside>
</div>

<?php if (site_setting('cookie_banner_enabled', '1') === '1'): ?>
<div class="bx-cookie-banner" id="bx-cookie-banner" role="dialog" aria-label="<?= e($fLang === 'bn' ? 'কুকি ও প্রাইভেসি সম্মতি' : 'Cookie & Privacy Preferences') ?>" hidden>
    <p style="margin:0;font-size:0.86rem;line-height:1.55;">
        <?= $fLang === 'bn'
            ? 'বুকএক্সা সাধারণ ব্রাউজিং ও কার্টের জন্য অত্যাবশ্যকীয় কুকি ব্যবহার করে। আপনার সম্মতি ছাড়া GA4 বা Clarity অ্যানালিটিক্স লোড করা হয় না।'
            : 'Bookexa uses essential storage for cart/login. Third-party analytics (GA4 / Clarity) stay off until you explicitly accept.' ?>
    </p>
    <div class="bx-cookie-actions">
        <button type="button" class="bx-btn bx-btn-outline bx-btn-sm" id="bx-cookie-essential"><?= $fLang === 'bn' ? 'শুধু অত্যাবশ্যকীয়' : 'Essential Only' ?></button>
        <button type="button" class="bx-btn bx-btn-primary bx-btn-sm" id="bx-cookie-accept"><?= $fLang === 'bn' ? 'সব গ্রহণ করুন' : 'Accept Analytics' ?></button>
    </div>
</div>
<?php endif; ?>

<script src="<?= e(perf_dist_asset_url('app_js', 'assets/js/app.js')) ?>" defer></script>
</body>
</html>
