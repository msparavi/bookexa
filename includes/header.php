<?php
declare(strict_types=1);

$user = current_user();
$lang = current_lang();
$nextLang = $lang === 'bn' ? 'en' : 'bn';
$logicalRouteHdr = defined('BX_LOGICAL_ROUTE') ? BX_LOGICAL_ROUTE : '/';
$qParams = $_GET;
unset($qParams['lang']);
$qStrHdr = $qParams ? ('?' . http_build_query($qParams)) : '';
if (function_exists('is_bilingual_public_route') && is_bilingual_public_route($logicalRouteHdr)) {
    $langUrl = url_for_lang($logicalRouteHdr . $qStrHdr, $nextLang);
} else {
    $qParams['lang'] = $nextLang;
    $reqPath = safe_internal_path((string) parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH), '/');
    $langUrl = BASE_PATH . $reqPath . '?' . http_build_query($qParams);
}

$siteName = site_setting('site_name', t('site_name'));
$siteLogo = site_setting('site_logo', 'assets/img/logo.svg');
$activeNav = $activeNav ?? '';
$shopEnabled = site_setting('shop_enabled', '0') === '1';
if (!empty($fullTitleExact) && ($pageTitle ?? '') !== '') {
    $fullTitle = (string) $pageTitle;
} else {
    $fullTitle = ($pageTitle ?? '') !== '' ? ($pageTitle . ' — ' . $siteName) : ($siteName . ' — ' . t('tagline'));
}
$metaDesc = ($metaDescription ?? '') !== '' ? $metaDescription : (($metaDesc ?? '') !== '' ? $metaDesc : t('meta_default_desc'));
$canonPath = $canonicalPath ?? $logicalRouteHdr;
$isBilingualCanon = function_exists('is_bilingual_public_route') && is_bilingual_public_route($canonPath);
$canonicalUrl = ($isBilingualCanon && $lang === 'en') ? absolute_url_for_lang($canonPath, 'en') : absolute_url_for_lang($canonPath, 'bn');
$altBnUrl = $isBilingualCanon ? absolute_url_for_lang($canonPath, 'bn') : null;
$altEnUrl = $isBilingualCanon ? absolute_url_for_lang($canonPath, 'en') : null;
$hrefCodes = function_exists('seo_hreflang_codes') ? seo_hreflang_codes() : ['bn' => 'bn-BD', 'en' => 'en-BD'];
$googleVerify = trim(site_setting('seo_google_verify', ''));
$bingVerify   = trim(site_setting('seo_bing_verify', ''));
$metaRobotsContent = (string) ($robotsDirective ?? (!empty($robotsNoIndex) ? 'noindex, nofollow' : ''));
$aeoDirectives = [];
if ($metaRobotsContent === '') {
    $aeoDirectives[] = 'index, follow, max-snippet:-1, max-image-preview:large, max-video-preview:-1';
    $sendNoAi = (!empty($isPaidProtectedPage) && site_setting('aeo_send_noai_paid', '1') === '1');
    $sendNoImgAi = $sendNoAi || site_setting('aeo_global_noimageai', '0') === '1';
    if ($sendNoAi) {
        $aeoDirectives[] = 'noai';
    }
    if ($sendNoImgAi) {
        $aeoDirectives[] = 'noimageai';
    }
    $metaRobotsTag = implode(', ', $aeoDirectives);
    if ($sendNoImgAi && !headers_sent()) {
        header('X-Robots-Tag: ' . ($sendNoAi ? 'noai, noimageai' : 'noimageai'));
    }
} else {
    $metaRobotsTag = $metaRobotsContent;
}
$flashes = get_flashes();
$unreadNotifCount = $user ? unread_notifications_count((int) $user['id']) : 0;
$recentNotifs = $user
    ? db_all('SELECT id, type, title_bn, title_en, body_bn, body_en, link_url, is_read, created_at FROM notifications WHERE user_id = ? ORDER BY id DESC LIMIT 6', [(int) $user['id']])
    : [];
?>
<!DOCTYPE html>
<html lang="<?= e($lang) ?>" data-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <meta name="bx-csrf" content="<?= e(csrf_token()) ?>">
    <meta name="bx-logged-in" content="<?= $user ? '1' : '0' ?>">
    <meta name="bx-base" content="<?= e(BASE_PATH) ?>">
    <?php $hdrCdn = cdn_config(); ?>
    <meta name="bx-cdn-base" content="<?= e(!empty($hdrCdn['enabled']) ? $hdrCdn['base_url'] : '') ?>">
    <?php $anIds = growth_analytics_ids(); ?>
    <meta name="bx-lang" content="<?= e($lang) ?>">
    <meta name="bx-route" content="<?= e($logicalRouteHdr) ?>">
    <meta name="bx-dnt" content="<?= growth_dnt_active() ? '1' : '0' ?>">
    <?php if ($anIds['ga4_id'] !== ''): ?>
        <meta name="bx-ga-id" content="<?= e($anIds['ga4_id']) ?>">
    <?php endif; ?>
    <?php if ($anIds['clarity_id'] !== ''): ?>
        <meta name="bx-clarity-id" content="<?= e($anIds['clarity_id']) ?>">
    <?php endif; ?>
    <title><?= e($fullTitle) ?></title>
    <meta name="description" content="<?= e($metaDesc) ?>">
    <meta name="theme-color" content="#2B2F77">
    <?php if ($googleVerify !== ''): ?>
        <meta name="google-site-verification" content="<?= e($googleVerify) ?>">
    <?php endif; ?>
    <?php if ($bingVerify !== ''): ?>
        <meta name="msvalidate.01" content="<?= e($bingVerify) ?>">
    <?php endif; ?>
    <?php if ($metaRobotsTag !== ''): ?>
        <meta name="robots" content="<?= e($metaRobotsTag) ?>">
    <?php endif; ?>
    <link rel="canonical" href="<?= e($canonicalUrl) ?>">
    <?php if (!empty($prevUrl)): ?>
        <link rel="prev" href="<?= e($prevUrl) ?>">
    <?php endif; ?>
    <?php if (!empty($nextUrl)): ?>
        <link rel="next" href="<?= e($nextUrl) ?>">
    <?php endif; ?>
    <?php if ($metaRobotsContent === '' && $altBnUrl && $altEnUrl): ?>
        <link rel="alternate" hreflang="<?= e($hrefCodes['bn']) ?>" href="<?= e($altBnUrl) ?>">
        <link rel="alternate" hreflang="<?= e($hrefCodes['en']) ?>" href="<?= e($altEnUrl) ?>">
        <link rel="alternate" hreflang="x-default" href="<?= e($altBnUrl) ?>">
    <?php endif; ?>
    <?php $ogImg = !empty($ogImage) ? $ogImage : absolute_asset_url(site_setting('seo_default_og_image', 'assets/img/apple-touch-icon.png')); ?>
    <meta property="og:site_name" content="<?= e($siteName) ?>">
    <meta property="og:title" content="<?= e($fullTitle) ?>">
    <meta property="og:description" content="<?= e($metaDesc) ?>">
    <meta property="og:type" content="<?= e($ogType ?? 'website') ?>">
    <meta property="og:locale" content="<?= $lang === 'en' ? 'en_US' : 'bn_BD' ?>">
    <meta property="og:url" content="<?= e($canonicalUrl) ?>">
    <meta property="og:image" content="<?= e($ogImg) ?>">
    <?php if (!empty($ogImageWidth) && !empty($ogImageHeight)): ?>
        <meta property="og:image:width" content="<?= (int) $ogImageWidth ?>">
        <meta property="og:image:height" content="<?= (int) $ogImageHeight ?>">
    <?php endif; ?>
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="<?= e($fullTitle) ?>">
    <meta name="twitter:description" content="<?= e($metaDesc) ?>">
    <meta name="twitter:image" content="<?= e($ogImg) ?>">
    <link rel="alternate" type="application/rss+xml" title="<?= e($siteName) ?> (<?= e($lang === 'bn' ? 'বাংলা গাইড' : 'Study Guides') ?>)" href="<?= e(absolute_url_for_lang('/feed.xml', $lang)) ?>">
    <link rel="manifest" href="<?= e(asset('manifest.webmanifest')) ?>">
    <link rel="icon" type="image/svg+xml" href="<?= e(asset('assets/img/favicon.svg')) ?>">
    <link rel="icon" type="image/png" sizes="32x32" href="<?= e(asset('assets/img/favicon-32x32.png')) ?>">
    <link rel="apple-touch-icon" sizes="180x180" href="<?= e(asset('assets/img/apple-touch-icon.png')) ?>">
    <?php
    $cdnOrigin = '';
    if (!empty($hdrCdn['enabled']) && !empty($hdrCdn['base_url'])) {
        $pCdn = @parse_url((string) $hdrCdn['base_url']);
        if (!empty($pCdn['scheme']) && !empty($pCdn['host'])) {
            $cdnOrigin = $pCdn['scheme'] . '://' . $pCdn['host'] . (!empty($pCdn['port']) ? (':' . $pCdn['port']) : '');
        }
    }
    if ($cdnOrigin !== ''): ?>
        <link rel="preconnect" href="<?= e($cdnOrigin) ?>" crossorigin>
        <link rel="dns-prefetch" href="<?= e($cdnOrigin) ?>">
    <?php endif; ?>
    <?php foreach (perf_active_font_preloads($lang) as $fontRel): ?>
        <link rel="preload" as="font" type="font/woff2" href="<?= e(asset($fontRel)) ?>" crossorigin>
    <?php endforeach; ?>
    <?php if (!empty($preloadHeroImage)): ?>
        <link rel="preload" as="image" href="<?= e($preloadHeroImage) ?>"<?= !empty($preloadHeroSrcset) ? ' imagesrcset="' . e($preloadHeroSrcset) . '" imagesizes="' . e($preloadHeroSizes ?? '100vw') . '"' : '' ?> fetchpriority="high">
    <?php endif; ?>
    <?php
    $critCss = perf_critical_css_inline();
    $priColor = sanitize_hex_color(site_setting('theme_primary_color', '#2B2F77'), '#2B2F77');
    $accColor = sanitize_hex_color(site_setting('theme_accent_color', '#F4A62A'), '#F4A62A');
    $fontPairKey = site_setting('theme_font_pair', 'hind_noto');
    $fontPair = theme_font_stack_pair($fontPairKey);
    ?>
    <style id="bx-critical-css"><?= $critCss ?></style>
    <?php if ($fontPairKey === 'editorial_serif' || $critCss === ''): ?>
        <link rel="stylesheet" href="<?= e(perf_dist_asset_url('fonts_css', 'assets/css/fonts.css')) ?>" media="print" data-async-css>
    <?php endif; ?>
    <link rel="stylesheet" href="<?= e(perf_dist_asset_url('app_css', 'assets/css/components.css')) ?>" media="print" data-async-css>
    <?php if ($critCss === ''): ?>
        <link rel="stylesheet" href="<?= e(asset('assets/css/tokens.css')) ?>">
        <link rel="stylesheet" href="<?= e(asset('assets/css/motion.css')) ?>">
    <?php endif; ?>
    <noscript>
        <link rel="stylesheet" href="<?= e(perf_dist_asset_url('fonts_css', 'assets/css/fonts.css')) ?>">
        <link rel="stylesheet" href="<?= e(perf_dist_asset_url('app_css', 'assets/css/components.css')) ?>">
    </noscript>
    <style id="bx-theme-overrides">:root,[data-theme="light"]{--primary:<?= $priColor ?>;--focus-ring:<?= $priColor ?>;--accent:<?= $accColor ?>;--font-sans:<?= $fontPair['sans'] ?>;--font-serif:<?= $fontPair['serif'] ?>;}[data-theme="dark"]{--accent:<?= $accColor ?>;--font-sans:<?= $fontPair['sans'] ?>;--font-serif:<?= $fontPair['serif'] ?>;}</style>
    <?php if (!empty($jsonLdSchemas) && is_array($jsonLdSchemas)): ?>
        <?php foreach ($jsonLdSchemas as $schema): ?>
            <script type="application/ld+json"><?= json_encode($schema, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_INVALID_UTF8_SUBSTITUTE) ?></script>
        <?php endforeach; ?>
    <?php endif; ?>
    <script src="<?= e(perf_dist_asset_url('theme_init_js', 'assets/js/theme-init.js')) ?>" defer></script>
</head>
<body>
<div class="bx-top-progress" id="bx-top-progress" aria-hidden="true"></div>
<header class="bx-header">
    <div class="container bx-header-inner">
        <div style="display:flex;align-items:center;gap:10px;">
            <button type="button" class="bx-pill-btn bx-mobile-toggle" data-drawer-open aria-label="Menu">
                <?= icon('menu') ?>
            </button>
            <a class="bx-brand" href="<?= e(url('/')) ?>">
                <?php if ($siteLogo === 'assets/img/logo.svg' || $siteLogo === ''): ?>
                    <?= bookexa_logo_svg() ?>
                <?php else: ?>
                    <img src="<?= e(asset($siteLogo)) ?>" alt="<?= e($siteName) ?>" width="36" height="36" style="height:36px;width:auto;">
                    <span><?= e($siteName) ?></span>
                <?php endif; ?>
            </a>
        </div>

        <nav class="bx-nav" aria-label="Primary">
            <a href="<?= e(url('/')) ?>" class="<?= $activeNav === 'home' ? 'active' : '' ?>"><?= e(t('nav_home')) ?></a>
            <a href="<?= e(url('/pdf')) ?>" class="<?= $activeNav === 'pdfs' ? 'active' : '' ?>"><?= e(t('nav_pdfs')) ?></a>
            <?php if ($user): ?>
                <a href="<?= e(url('/library')) ?>" class="<?= $activeNav === 'library' ? 'active' : '' ?>"><?= e(t('nav_library')) ?></a>
                <a href="<?= e(url('/wishlist')) ?>" class="<?= $activeNav === 'wishlist' ? 'active' : '' ?>"><?= e(t('nav_wishlist')) ?></a>
            <?php endif; ?>
            <?php if ($shopEnabled): ?>
                <a href="<?= e(url('/shop')) ?>" class="<?= $activeNav === 'shop' ? 'active' : '' ?>"><?= e(t('nav_shop')) ?></a>
            <?php endif; ?>
            <a href="<?= e(url('/about')) ?>" class="<?= $activeNav === 'about' ? 'active' : '' ?>"><?= e(t('nav_about')) ?></a>
            <a href="<?= e(url('/contact')) ?>" class="<?= $activeNav === 'contact' ? 'active' : '' ?>"><?= e(t('nav_contact')) ?></a>
        </nav>

        <form class="bx-search-bar" action="<?= e(url('/search')) ?>" method="get">
            <input type="search" name="q" value="<?= e($_GET['q'] ?? '') ?>" placeholder="<?= e(t('search_placeholder')) ?>" aria-label="<?= e(t('nav_search')) ?>" data-suggest-input autocomplete="off">
            <button type="submit" aria-label="<?= e(t('nav_search')) ?>"><?= icon('search') ?></button>
            <div class="bx-suggest-dropdown" hidden></div>
        </form>

        <div class="bx-header-actions">
            <?php $hdrCartCount = shop_cart_count(); if ($shopEnabled || $hdrCartCount > 0): ?>
                <button type="button" class="bx-pill-btn bx-notif-btn" id="bx-cart-toggle" aria-label="<?= e(t('nav_cart')) ?>" aria-expanded="false">
                    <?= icon('cart') ?>
                    <span class="bx-notif-badge" id="bx-hdr-cart-badge" data-cart-badge<?= $hdrCartCount > 0 ? '' : ' hidden' ?>><?= $hdrCartCount > 99 ? '99+' : (int) $hdrCartCount ?></span>
                </button>
            <?php endif; ?>
            <a class="bx-pill-btn bx-lang-pill" href="<?= e($langUrl) ?>" title="Switch Language">
                <?= icon('globe') ?> <span><?= e(t('lang_toggle_label')) ?></span>
            </a>
            <button type="button" class="bx-pill-btn" data-theme-toggle aria-label="Toggle theme">
                <span class="bx-theme-moon"><?= icon('moon') ?></span>
                <span class="bx-theme-sun"><?= icon('sun') ?></span>
            </button>
            <?php if ($user): ?>
                <div class="bx-notif-wrap">
                    <button type="button" class="bx-pill-btn bx-notif-btn" id="bx-notif-btn" aria-label="<?= e(t('nav_notifications')) ?>" aria-expanded="false">
                        <?= icon('bell') ?>
                        <span class="bx-notif-badge" id="bx-notif-badge"<?= $unreadNotifCount > 0 ? '' : ' hidden' ?>><?= $unreadNotifCount > 99 ? '99+' : (int) $unreadNotifCount ?></span>
                    </button>
                    <div class="bx-notif-menu" id="bx-notif-menu" role="region" aria-label="<?= e(t('nav_notifications')) ?>">
                        <div class="bx-notif-head">
                            <strong><?= e(t('nav_notifications')) ?></strong>
                            <button type="button" class="bx-btn bx-btn-ghost bx-btn-sm" id="bx-notif-read-all"><?= $lang === 'bn' ? 'সব পঠিত' : 'Mark all read' ?></button>
                        </div>
                        <div class="bx-notif-list" id="bx-notif-list">
                            <?php if (!$recentNotifs): ?>
                                <p class="bx-muted" style="margin:8px 0;font-size:0.86rem;"><?= $lang === 'bn' ? 'কোনো বিজ্ঞপ্তি নেই।' : 'No notifications yet.' ?></p>
                            <?php else: foreach ($recentNotifs as $nf): ?>
                                <div class="bx-notif-item <?= !(int) $nf['is_read'] ? 'is-unread' : '' ?>" data-notif-id="<?= (int) $nf['id'] ?>">
                                    <a href="<?= e(!empty($nf['link_url']) ? url((string) $nf['link_url']) : url('/notifications')) ?>" class="bx-notif-link">
                                        <strong><?= e(bi($nf, 'title')) ?></strong>
                                        <?php if (bi($nf, 'body') !== ''): ?>
                                            <span><?= e(bi($nf, 'body')) ?></span>
                                        <?php endif; ?>
                                    </a>
                                    <?php if (!(int) $nf['is_read']): ?>
                                        <button type="button" class="bx-notif-mark-one" data-notif-mark="<?= (int) $nf['id'] ?>" title="<?= e($lang === 'bn' ? 'পঠিত করুন' : 'Mark read') ?>"><?= icon('check') ?></button>
                                    <?php endif; ?>
                                </div>
                            <?php endforeach; endif; ?>
                        </div>
                        <div class="bx-notif-foot">
                            <a href="<?= e(url('/notifications')) ?>"><?= $lang === 'bn' ? 'সব বিজ্ঞপ্তি দেখুন →' : 'View all notifications →' ?></a>
                        </div>
                    </div>
                </div>
                <a class="bx-btn bx-btn-outline bx-btn-sm" href="<?= e(url('/profile')) ?>" title="<?= e($user['name']) ?>"><?= icon('user') ?> <span class="bx-header-profile-label"><?= e($user['name']) ?></span></a>
            <?php else: ?>
                <a class="bx-pill-btn bx-notif-btn" href="<?= e(url('/login?next=' . rawurlencode('/notifications'))) ?>" aria-label="<?= e(t('nav_notifications')) ?>" title="<?= e(t('nav_notifications')) ?>">
                    <?= icon('bell') ?>
                </a>
                <a class="bx-btn bx-btn-primary bx-btn-sm" href="<?= e(url('/login')) ?>"><?= icon('user') ?> <span class="bx-header-profile-label"><?= e(t('nav_login')) ?></span></a>
            <?php endif; ?>
        </div>
    </div>
</header>

<!-- Mobile Slide Drawer -->
<div class="bx-drawer" id="bx-drawer">
    <div class="bx-drawer-panel">
        <div style="display:flex;align-items:center;justify-content:space-between;">
            <span class="bx-brand">
                <?php if ($siteLogo === 'assets/img/logo.svg' || $siteLogo === ''): ?>
                    <?= bookexa_logo_svg('bx-logo-svg') ?>
                <?php else: ?>
                    <img src="<?= e(asset($siteLogo)) ?>" alt="<?= e($siteName) ?>" width="32" height="32" style="height:32px;width:auto;">
                    <span><?= e($siteName) ?></span>
                <?php endif; ?>
            </span>
            <button type="button" class="bx-pill-btn" data-drawer-close aria-label="Close"><?= icon('close') ?></button>
        </div>
        <div class="bx-drawer-lang-bar">
            <span class="bx-muted" style="display:inline-flex;align-items:center;gap:6px;font-size:0.86rem;font-weight:600;"><?= icon('globe') ?> <?= e($lang === 'bn' ? 'ভাষা' : 'Language') ?></span>
            <a href="<?= e($langUrl) ?>" class="bx-pill-btn bx-drawer-lang-btn"><?= e($lang === 'bn' ? 'English' : 'বাংলা') ?></a>
        </div>
        <form class="bx-search-bar" style="display:flex;max-width:none;" action="<?= e(url('/search')) ?>" method="get">
            <input type="search" name="q" value="<?= e($_GET['q'] ?? '') ?>" placeholder="<?= e(t('search_placeholder')) ?>" data-suggest-input autocomplete="off">
            <button type="submit"><?= icon('search') ?></button>
            <div class="bx-suggest-dropdown" hidden></div>
        </form>
        <div class="bx-drawer-links">
            <a href="<?= e(url('/')) ?>" class="<?= $activeNav === 'home' ? 'active' : '' ?>"><?= e(t('nav_home')) ?></a>
            <a href="<?= e(url('/pdf')) ?>" class="<?= $activeNav === 'pdfs' ? 'active' : '' ?>"><?= e(t('nav_pdfs')) ?></a>
            <?php if ($user): ?>
                <a href="<?= e(url('/library')) ?>" class="<?= $activeNav === 'library' ? 'active' : '' ?>"><?= e(t('nav_library')) ?></a>
                <a href="<?= e(url('/wishlist')) ?>" class="<?= $activeNav === 'wishlist' ? 'active' : '' ?>"><?= e(t('nav_wishlist')) ?></a>
                <a href="<?= e(url('/notifications')) ?>" class="<?= $activeNav === 'notifications' ? 'active' : '' ?>"><?= e(t('nav_notifications')) ?> <?= $unreadNotifCount > 0 ? '(' . (int) $unreadNotifCount . ')' : '' ?></a>
                <a href="<?= e(url('/profile')) ?>" class="<?= $activeNav === 'account' ? 'active' : '' ?>"><?= e(current_lang() === 'bn' ? 'প্রোফাইল' : 'Profile') ?></a>
            <?php else: ?>
                <a href="<?= e(url('/login')) ?>" class="<?= $activeNav === 'account' ? 'active' : '' ?>"><?= e(t('nav_login')) ?></a>
            <?php endif; ?>
            <?php if ($shopEnabled): ?>
                <a href="<?= e(url('/shop')) ?>" class="<?= $activeNav === 'shop' ? 'active' : '' ?>"><?= e(t('nav_shop')) ?></a>
                <a href="<?= e(url('/cart')) ?>" class="<?= $activeNav === 'cart' ? 'active' : '' ?>"><?= e(t('nav_cart')) ?> (<span data-cart-badge><?= (int) shop_cart_count() ?></span>)</a>
                <a href="<?= e(url('/shop/track')) ?>"><?= e(t('nav_track_order')) ?></a>
            <?php endif; ?>
            <a href="<?= e(url('/about')) ?>" class="<?= $activeNav === 'about' ? 'active' : '' ?>"><?= e(t('nav_about')) ?></a>
            <a href="<?= e(url('/contact')) ?>" class="<?= $activeNav === 'contact' ? 'active' : '' ?>"><?= e(t('nav_contact')) ?></a>
        </div>
    </div>
</div>

<main class="bx-main">
    <?php if ($flashes): ?>
        <div class="container" style="padding-top:16px;">
            <?php foreach ($flashes as $fl): ?>
                <div class="bx-alert bx-alert-<?= e($fl['type']) ?>" style="margin-bottom:10px;padding:12px 16px;border-radius:12px;background:var(--surface);border:1px solid var(--border);border-left:4px solid var(--<?= e($fl['type'] === 'danger' ? 'danger' : ($fl['type'] === 'warning' ? 'warning' : 'success')) ?>);">
                    <?= e($fl['message']) ?>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
