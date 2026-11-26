<?php
declare(strict_types=1);

$user = current_user();
$lang = current_lang();
$nextLang = $lang === 'bn' ? 'en' : 'bn';
$reqPath = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
$qParams = $_GET;
$qParams['lang'] = $nextLang;
$langUrl = $reqPath . '?' . http_build_query($qParams);

$siteName = site_setting('site_name', t('site_name'));
$siteLogo = site_setting('site_logo', 'assets/img/logo.svg');
$activeNav = $activeNav ?? '';
$shopEnabled = site_setting('shop_enabled', '0') === '1';
$fullTitle = ($pageTitle ?? '') !== '' ? ($pageTitle . ' — ' . $siteName) : ($siteName . ' — ' . t('tagline'));
$metaDesc = ($metaDescription ?? '') !== '' ? $metaDescription : t('meta_default_desc');
$canonicalUrl = BASE_URL . ($canonicalPath ?? '/');
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
    <title><?= e($fullTitle) ?></title>
    <meta name="description" content="<?= e($metaDesc) ?>">
    <meta name="theme-color" content="#2B2F77">
    <link rel="canonical" href="<?= e($canonicalUrl) ?>">
    <?php $ogImg = !empty($ogImage) ? $ogImage : (BASE_URL . '/assets/img/apple-touch-icon.png'); ?>
    <meta property="og:title" content="<?= e($fullTitle) ?>">
    <meta property="og:description" content="<?= e($metaDesc) ?>">
    <meta property="og:type" content="website">
    <meta property="og:url" content="<?= e($canonicalUrl) ?>">
    <meta property="og:image" content="<?= e($ogImg) ?>">
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="<?= e($fullTitle) ?>">
    <meta name="twitter:description" content="<?= e($metaDesc) ?>">
    <meta name="twitter:image" content="<?= e($ogImg) ?>">
    <link rel="manifest" href="<?= e(asset('manifest.webmanifest')) ?>">
    <link rel="icon" type="image/svg+xml" href="<?= e(asset('assets/img/favicon.svg')) ?>">
    <link rel="icon" type="image/png" sizes="32x32" href="<?= e(asset('assets/img/favicon-32x32.png')) ?>">
    <link rel="apple-touch-icon" sizes="180x180" href="<?= e(asset('assets/img/apple-touch-icon.png')) ?>">
    <link rel="stylesheet" href="<?= e(asset('assets/css/fonts.css')) ?>">
    <link rel="stylesheet" href="<?= e(asset('assets/css/tokens.css')) ?>">
    <link rel="stylesheet" href="<?= e(asset('assets/css/components.css')) ?>">
    <link rel="stylesheet" href="<?= e(asset('assets/css/motion.css')) ?>">
    <script src="<?= e(asset('assets/js/theme-init.js')) ?>"></script>
</head>
<body>
<div class="bx-top-progress" id="bx-top-progress" aria-hidden="true"></div>
<div class="bx-first-loader" id="bx-first-loader" aria-hidden="true">
    <div class="bx-loader-book">
        <div class="bx-loader-page left"></div>
        <div class="bx-loader-page right"></div>
    </div>
    <?= bookexa_logo_svg('bx-logo-svg') ?>
</div>
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
                    <img src="<?= e(asset($siteLogo)) ?>" alt="<?= e($siteName) ?>" style="height:36px;width:auto;">
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
                    <img src="<?= e(asset($siteLogo)) ?>" alt="<?= e($siteName) ?>" style="height:32px;width:auto;">
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
