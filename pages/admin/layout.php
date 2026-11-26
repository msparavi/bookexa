<?php
declare(strict_types=1);

$lang = current_lang();
$siteName = site_setting('site_name', 'Bookexa');
$faviconSetting = site_setting('site_favicon', '');
$faviconHref = $faviconSetting !== '' ? asset($faviconSetting) : asset('assets/img/favicon.svg');
$flashes = get_flashes();

$navItems = [
    ['key' => 'dashboard',     'url' => '/admin',               'bn' => 'ড্যাশবোর্ড',         'en' => 'Dashboard',       'icon' => '<rect x="3" y="3" width="7" height="9" rx="1"/><rect x="14" y="3" width="7" height="5" rx="1"/><rect x="14" y="12" width="7" height="9" rx="1"/><rect x="3" y="16" width="7" height="5" rx="1"/>'],
    ['key' => 'banners',       'url' => '/admin/banners',       'bn' => 'ব্যানার',             'en' => 'Banners',         'icon' => '<rect x="2" y="5" width="20" height="14" rx="2"/><path d="M2 15l5-5 4 4 3-3 8 6"/>'],
    ['key' => 'folders',       'url' => '/admin/folders',       'bn' => 'ফোল্ডার ট্রি',        'en' => 'Folders',         'icon' => '<path d="M3 7a2 2 0 0 1 2-2h4l2 2h8a2 2 0 0 1 2 2v8a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/>'],
    ['key' => 'pdfs',          'url' => '/admin/pdfs',          'bn' => 'PDF ম্যানেজার',      'en' => 'PDF Manager',     'icon' => '<path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20"/><path d="M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2z"/>'],
    ['key' => 'import-csv',    'url' => '/admin/import-csv',    'bn' => 'CSV বাল্ক ইমপোর্ট',   'en' => 'CSV Import',      'icon' => '<path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="12" y1="18" x2="12" y2="12"/><polyline points="9 15 12 12 15 15"/>'],
    ['key' => 'prepare-queue', 'url' => '/admin/prepare-queue', 'bn' => 'প্রস্তুত কিউ (সব)',   'en' => 'Prepare Queue',   'icon' => '<polygon points="5 3 19 12 5 21 5 3"/>'],
    ['key' => 'orders',            'url' => '/admin/orders',            'bn' => 'অর্ডার (PDF)',       'en' => 'PDF Orders',        'icon' => '<rect x="2" y="4" width="20" height="16" rx="2"/><path d="M2 10h20"/>', 'badge' => $pendingOrdersCount ?? 0],
    ['key' => 'shop-categories',   'url' => '/admin/shop-categories',   'bn' => 'শপ ক্যাটাগরি',      'en' => 'Shop Categories',   'icon' => '<path d="M4 6h16M4 12h16M4 18h10"/>'],
    ['key' => 'shop-products',     'url' => '/admin/shop-products',     'bn' => 'শপ পণ্য (বই)',      'en' => 'Shop Products',     'icon' => '<circle cx="9" cy="21" r="1"/><circle cx="20" cy="21" r="1"/><path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"/>', 'badge' => $lowStockCount ?? 0],
    ['key' => 'shop-orders',       'url' => '/admin/shop-orders',       'bn' => 'শপ অর্ডার',        'en' => 'Shop Orders',       'icon' => '<rect x="1" y="3" width="15" height="13"/><polygon points="16 8 20 8 23 11 23 16 16 16 16 8"/><circle cx="5.5" cy="18.5" r="2.5"/><circle cx="18.5" cy="18.5" r="2.5"/>', 'badge' => $pendingShopOrdersCount ?? 0],
    ['key' => 'delivery-settings', 'url' => '/admin/delivery-settings', 'bn' => 'ডেলিভারি সেটিংস',   'en' => 'Delivery Settings', 'icon' => '<line x1="16.5" y1="9.4" x2="7.5" y2="4.21"/><path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"/>'],
    ['key' => 'inbox',         'url' => '/admin/inbox',         'bn' => 'সাপোর্ট ইনবক্স',     'en' => 'Support Inbox',   'icon' => '<path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/><polyline points="22,6 12,13 2,6"/>', 'badge' => $newInboxCount ?? 0],
    ['key' => 'coupons',       'url' => '/admin/coupons',       'bn' => 'কুপন কোড',          'en' => 'Coupons',         'icon' => '<path d="M20.59 13.41l-7.17 7.17a2 2 0 0 1-2.83 0L2 12V2h10l8.59 8.59a2 2 0 0 1 0 2.82z"/><line x1="7" y1="7" x2="7.01" y2="7"/>'],
    ['key' => 'reviews',       'url' => '/admin/reviews',       'bn' => 'রিভিউ মডারেশন',      'en' => 'Reviews',         'icon' => '<polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/>'],
    ['key' => 'guides',        'url' => '/admin/guides',        'bn' => 'গাইড ও লেখক',        'en' => 'Guides & Authors', 'icon' => '<path d="M12 20h9"/><path d="M16.5 3.5a2.121 2.121 0 0 1 3 3L7 19l-4 1 1-4L16.5 3.5z"/>'],
    ['key' => 'hubs',          'url' => '/admin/hubs',          'bn' => 'হাব ও লিংকিং',       'en' => 'Hubs & Links',    'icon' => '<circle cx="12" cy="12" r="3"/><path d="M12 2v4M12 18v4M4.93 4.93l2.83 2.83M16.24 16.24l2.83 2.83M2 12h4M18 12h4M4.93 19.07l2.83-2.83M16.24 7.76l2.83-2.83"/>'],
    ['key' => 'seo',           'url' => '/admin/seo',           'bn' => 'টেকনিক্যাল SEO',      'en' => 'SEO & Redirects', 'icon' => '<circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/>'],
    ['key' => 'aeo',           'url' => '/admin/aeo',           'bn' => 'AEO / GEO অপ্টিমাইজ', 'en' => 'AEO/GEO Check',   'icon' => '<path d="M12 2L2 7l10 5 10-5-10-5z"/><path d="M2 17l10 5 10-5"/><path d="M2 12l10 5 10-5"/>'],
    ['key' => 'growth',        'url' => '/admin/growth',        'bn' => 'ইনডেক্সিং ও গ্রোথ',   'en' => 'Growth & Indexing', 'badge' => (int) ($openSeoAlertsCount ?? 0), 'icon' => '<polyline points="22 12 18 12 15 21 9 3 6 12 2 12"/>'],
    ['key' => 'reports',       'url' => '/admin/reports',       'bn' => 'অ্যানালিটিক্স ও CSV', 'en' => 'Analytics & CSV', 'icon' => '<line x1="18" y1="20" x2="18" y2="10"/><line x1="12" y1="20" x2="12" y2="4"/><line x1="6" y1="20" x2="6" y2="14"/>'],
    ['key' => 'media',         'url' => '/admin/media',         'bn' => 'মিডিয়া ও CDN',       'en' => 'Media & CDN',     'icon' => '<rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="8.5" cy="8.5" r="1.5"/><polyline points="21 15 16 10 5 21"/>'],
    ['key' => 'users',         'url' => '/admin/users',         'bn' => 'ব্যবহারকারী',         'en' => 'Users',           'icon' => '<path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/>'],
    ['key' => 'logs',          'url' => '/admin/logs',          'bn' => 'সিকিউরিটি লগ',       'en' => 'Security Logs',   'icon' => '<path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/>'],
    ['key' => 'system-health', 'url' => '/admin/system-health', 'bn' => 'সিস্টেম হেলথ',       'en' => 'System Health',   'icon' => '<polyline points="22 12 18 12 15 21 9 3 6 12 2 12"/>'],
    ['key' => 'updates',       'url' => '/admin/updates',       'bn' => 'ভার্সন ও আপডেট',     'en' => 'About & Updates',  'icon' => '<ellipse cx="12" cy="5" rx="9" ry="3"/><path d="M21 12c0 1.66-4 3-9 3s-9-1.34-9-3"/><path d="M3 5v14c0 1.66 4 3 9 3s9-1.34 9-3V5"/>', 'badge' => $pendingMigrationsCount ?? 0],
    ['key' => 'settings',      'url' => '/admin/settings',      'bn' => 'সেটিংস',            'en' => 'Settings',        'icon' => '<circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 1 1-2.83 2.83l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-4 0v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 1 1-2.83-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1 0-4h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 1 1 2.83-2.83l.06.06a1.65 1.65 0 0 0 1.82.33H9a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 4 0v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 1 1 2.83 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9c.26.6.77 1 1.51 1H21a2 2 0 0 1 0 4h-.09c-.74 0-1.25.4-1.51 1z"/>'],
    ['key' => 'admins',        'url' => '/admin/admins',        'bn' => 'অ্যাডমিন ও লগ',      'en' => 'Admins & Audit',  'icon' => '<path d="M12 15a3 3 0 1 0 0-6 3 3 0 0 0 0 6z"/><path d="M2 12s3-7 10-7 10 7 10 7-3 7-10 7-10-7-10-7z"/>'],
];
$navItems = array_values(array_filter($navItems, static fn(array $it): bool => admin_can($admin, $it['key'])));

$activeTitle = 'Admin';
foreach ($navItems as $ni) {
    if ($ni['key'] === $section) {
        $activeTitle = $lang === 'bn' ? $ni['bn'] : $ni['en'];
        break;
    }
}

$altLang = $lang === 'bn' ? 'en' : 'bn';
$currQuery = $_GET;
$currQuery['lang'] = $altLang;
$langSwitchHref = strtok($_SERVER['REQUEST_URI'] ?? '', '?') . '?' . http_build_query($currQuery);
?>
<!DOCTYPE html>
<html lang="<?= e($lang) ?>" data-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <meta name="robots" content="noindex, nofollow, noarchive">
    <meta name="theme-color" content="#2B2F77">
    <meta name="bx-csrf" content="<?= e(csrf_token()) ?>">
    <meta name="bx-base" content="<?= e(BASE_PATH) ?>">
    <?php $admCdn = cdn_config(); ?>
    <meta name="bx-cdn-base" content="<?= e(!empty($admCdn['enabled']) ? $admCdn['base_url'] : '') ?>">
    <title><?= e($activeTitle) ?> — <?= e($siteName) ?> Admin</title>
    <link rel="manifest" href="<?= e(asset('manifest.webmanifest')) ?>">
    <link rel="icon" href="<?= e($faviconHref) ?>">
    <link rel="stylesheet" href="<?= e(perf_dist_asset_url('fonts_css', 'assets/css/fonts.css')) ?>">
    <link rel="stylesheet" href="<?= e(perf_dist_asset_url('app_css', 'assets/css/components.css')) ?>">
    <link rel="stylesheet" href="<?= e(perf_dist_asset_url('admin_css', 'assets/css/admin.css')) ?>">
    <link rel="stylesheet" href="<?= e(url('/theme-custom.css?v=' . theme_css_version_hash())) ?>">
    <script src="<?= e(perf_dist_asset_url('theme_init_js', 'assets/js/theme-init.js')) ?>" defer></script>
</head>
<body>
<div class="bx-admin-wrap" id="bx-admin-wrap">
    <div class="bx-admin-backdrop" id="bx-admin-backdrop" aria-hidden="true"></div>

    <aside class="bx-admin-sidebar" id="bx-admin-sidebar" aria-label="Admin Navigation">
        <div style="display:flex;align-items:center;justify-content:space-between;gap:8px;padding:4px 4px;">
            <a href="<?= e(url('/admin')) ?>" style="display:flex;align-items:center;gap:10px;text-decoration:none;color:var(--text);min-width:0;">
                <span style="width:36px;height:36px;border-radius:11px;background:var(--grad-indigo-teal);display:inline-flex;align-items:center;justify-content:center;color:#FBF6EC;font-weight:800;flex-shrink:0;">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M2 3h6a4 4 0 0 1 4 4v14a3 3 0 0 0-3-3H2z"/><path d="M22 3h-6a4 4 0 0 0-4 4v14a3 3 0 0 1 3-3h7z"/></svg>
                </span>
                <span class="bx-admin-brand-text">
                    <strong style="display:block;font-size:1.02rem;font-family:var(--font-serif);line-height:1.1;"><?= e($siteName) ?></strong>
                    <small style="color:var(--text-muted);font-size:0.75rem;"><?= $lang === 'bn' ? 'অ্যাডমিন প্যানেল' : 'Control Panel' ?></small>
                </span>
            </a>
            <button type="button" class="bx-btn bx-btn-ghost bx-admin-drawer-close" data-admin-menu-close aria-label="Close drawer" style="padding:8px;">✕</button>
        </div>

        <nav class="bx-admin-nav">
            <?php foreach ($navItems as $item): ?>
                <a href="<?= e(url($item['url'])) ?>" class="<?= $section === $item['key'] ? 'active' : '' ?>" title="<?= e($lang === 'bn' ? $item['bn'] : $item['en']) ?>">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="flex-shrink:0;"><?= $item['icon'] ?></svg>
                    <span class="bx-nav-label"><?= e($lang === 'bn' ? $item['bn'] : $item['en']) ?></span>
                    <?php if (!empty($item['badge'])): ?>
                        <span class="bx-admin-nav-badge bx-nav-label"><?= (int) $item['badge'] ?></span>
                    <?php endif; ?>
                </a>
            <?php endforeach; ?>
        </nav>

        <div style="margin-top:auto;padding-top:14px;border-top:1px solid var(--border);display:flex;flex-direction:column;gap:8px;">
            <a href="<?= e(url('/')) ?>" target="_blank" rel="noopener" class="bx-btn bx-btn-ghost" style="justify-content:flex-start;padding:9px 12px;">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"/><polyline points="15 3 21 3 21 9"/><line x1="10" y1="14" x2="21" y2="3"/></svg>
                <span class="bx-nav-label"><?= $lang === 'bn' ? 'মূল সাইট দেখুন' : 'Visit Storefront' ?></span>
            </a>
            <div class="bx-admin-user-meta" style="font-size:0.78rem;color:var(--text-muted);padding:4px 8px;">
                <?= $lang === 'bn' ? 'রোল:' : 'Role:' ?>
                <span class="bx-badge"><?= e(strtoupper((string) $admin['role'])) ?></span>
            </div>
        </div>
    </aside>

    <div class="bx-admin-main">
        <header class="bx-admin-topbar">
            <div class="bx-admin-title-bar">
                <button type="button" class="bx-btn bx-btn-ghost bx-admin-mobile-toggle" data-admin-menu-open aria-expanded="false" aria-label="Menu" style="padding:8px 10px;">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="3" y1="6" x2="21" y2="6"/><line x1="3" y1="12" x2="21" y2="12"/><line x1="3" y1="18" x2="21" y2="18"/></svg>
                </button>
                <button type="button" class="bx-btn bx-btn-ghost bx-collapse-desktop" data-sidebar-collapse aria-label="Toggle sidebar" style="padding:8px 10px;">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="18" height="18" rx="2"/><line x1="9" y1="3" x2="9" y2="21"/></svg>
                </button>
                <h1><?= e($activeTitle) ?></h1>
            </div>

            <?php if (admin_can($admin, 'pdfs')): ?>
            <form class="bx-admin-search" id="bx-admin-search-form" action="<?= e(url('/admin/pdfs')) ?>" method="get">
                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="color:var(--text-muted);flex-shrink:0;"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
                <input type="search" id="bx-admin-global-search" name="q" value="<?= e((string) ($_GET['q'] ?? '')) ?>" placeholder="<?= $lang === 'bn' ? 'PDF খুঁজুন... (Ctrl+K)' : 'Search PDFs... (Ctrl+K)' ?>">
            </form>
            <?php endif; ?>

            <div class="bx-admin-topbar-actions">
                <button type="button" class="bx-btn bx-btn-ghost bx-mobile-search-btn" data-topbar-search-toggle aria-label="Search" style="padding:8px;">
                    <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
                </button>
                <?php if (admin_can($admin, 'system-health') || ($admin['role'] ?? '') === 'superadmin'): ?>
                <form method="post" action="<?= e(url('/admin/system-health')) ?>" style="margin:0;display:inline-flex;">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="clean_cache">
                    <input type="hidden" name="_return" value="<?= e((string) ($_SERVER['REQUEST_URI'] ?? '/admin')) ?>">
                    <button type="submit" class="bx-btn bx-btn-outline" style="padding:6px 10px;font-size:0.8rem;min-height:36px;" title="<?= e($lang === 'bn' ? 'পেজ ও সাইটম্যাপ ক্যাশ পরিষ্কার করুন' : 'Clear full-page & sitemap cache') ?>">
                        <?= $lang === 'bn' ? 'ক্যাশ মুছুন' : 'Clear cache' ?>
                    </button>
                </form>
                <?php endif; ?>
                <a href="<?= e($langSwitchHref) ?>" class="bx-btn bx-btn-ghost" style="padding:8px 10px;font-size:0.84rem;">
                    <?= $lang === 'bn' ? 'EN' : 'বাং' ?>
                </a>
                <button type="button" class="bx-btn bx-btn-ghost" data-theme-toggle aria-label="Toggle theme" style="padding:8px 10px;">
                    <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="5"/><line x1="12" y1="1" x2="12" y2="3"/><line x1="12" y1="21" x2="12" y2="23"/><line x1="4.22" y1="4.22" x2="5.64" y2="5.64"/><line x1="18.36" y1="18.36" x2="19.78" y2="19.78"/><line x1="1" y1="12" x2="3" y2="12"/><line x1="21" y1="12" x2="23" y2="12"/></svg>
                </button>

                <div class="bx-profile-wrap">
                    <button type="button" id="bx-profile-btn" class="bx-btn bx-btn-ghost" style="padding:6px 10px;gap:8px;">
                        <span style="width:28px;height:28px;border-radius:999px;background:var(--grad-indigo-teal);color:#FBF6EC;display:inline-flex;align-items:center;justify-content:center;font-weight:700;font-size:0.8rem;flex-shrink:0;">
                            <?= e(strtoupper(substr((string) $admin['name'], 0, 1))) ?>
                        </span>
                        <span class="bx-profile-name-sm" style="font-size:0.86rem;font-weight:600;"><?= e($admin['name']) ?></span>
                    </button>
                    <div id="bx-profile-menu" class="bx-profile-menu">
                        <div style="padding-bottom:8px;margin-bottom:8px;border-bottom:1px solid var(--border);">
                            <strong style="display:block;font-size:0.9rem;"><?= e($admin['name']) ?></strong>
                            <small style="display:block;color:var(--text-muted);font-size:0.78rem;word-break:break-all;"><?= e($admin['email']) ?></small>
                            <span class="bx-status-pill st-active" style="margin-top:6px;"><?= e($admin['role']) ?></span>
                        </div>
                        <a href="<?= e(url('/admin/account-security')) ?>" class="bx-btn bx-btn-ghost" style="width:100%;justify-content:flex-start;padding:7px 10px;font-size:0.86rem;">
                            <?= $lang === 'bn' ? 'পাসওয়ার্ড ও ২এফএ নিরাপত্তা' : 'Security & 2FA' ?>
                        </a>
                        <?php if (admin_can($admin, 'settings')): ?>
                            <a href="<?= e(url('/admin/settings')) ?>" class="bx-btn bx-btn-ghost" style="width:100%;justify-content:flex-start;padding:7px 10px;font-size:0.86rem;">
                                <?= $lang === 'bn' ? 'সাইট সেটিংস' : 'Site Settings' ?>
                            </a>
                        <?php endif; ?>
                        <?php if (admin_can($admin, 'admins')): ?>
                            <a href="<?= e(url('/admin/admins')) ?>" class="bx-btn bx-btn-ghost" style="width:100%;justify-content:flex-start;padding:7px 10px;font-size:0.86rem;">
                                <?= $lang === 'bn' ? 'অ্যাডমিন অ্যাকাউন্ট' : 'Admin Accounts' ?>
                            </a>
                        <?php endif; ?>
                        <form action="<?= e(url('/admin/logout')) ?>" method="post" style="margin-top:6px;">
                            <?= csrf_field() ?>
                            <button type="submit" class="bx-btn bx-btn-outline" style="width:100%;padding:7px 10px;font-size:0.86rem;color:var(--rose-text);border-color:var(--rose-text);">
                                <?= $lang === 'bn' ? 'লগআউট' : 'Sign Out' ?>
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </header>

        <main class="bx-admin-content">
            <?php if (is_demo_mode()): ?>
                <div class="bx-alert bx-alert-warning" role="status" style="display:flex;justify-content:space-between;align-items:center;gap:10px;flex-wrap:wrap;margin-bottom:14px;">
                    <strong>
                        <?= $lang === 'bn'
                            ? 'ডেমো মোড সক্রিয় — ধ্বংসাত্মক পরিবর্তন, ডিলিট এবং সিকিউরিটি/ব্যাকআপ অ্যাকশন নিষ্ক্রিয় আছে।'
                            : 'Demo Mode Active — Destructive edits, deletions, backups, and security mutations are disabled.' ?>
                    </strong>
                    <span class="bx-badge bx-badge-gold">DEMO MODE</span>
                </div>
            <?php endif; ?>
            <?php if (($pendingMigrationsCount ?? 0) > 0): ?>
                <div class="bx-alert bx-alert-danger" role="alert" style="display:flex;justify-content:space-between;align-items:center;gap:12px;flex-wrap:wrap;margin-bottom:16px;">
                    <strong>
                        <?= $lang === 'bn'
                            ? "সতর্কতা: {$pendingMigrationsCount}টি ডাটাবেজ আপডেট অপেক্ষমাণ রয়েছে!"
                            : "Database updates pending: {$pendingMigrationsCount} schema migration(s) have not been applied yet." ?>
                    </strong>
                    <a href="<?= e(url('/admin/updates')) ?>" class="bx-btn bx-btn-primary bx-btn-sm">
                        <?= $lang === 'bn' ? 'ডাটাবেজ আপডেট দেখুন' : 'Open Database Updates' ?>
                    </a>
                </div>
            <?php endif; ?>
            <?php if (($openSeoAlertsCount ?? 0) > 0 && ($adminSection ?? '') !== 'growth'): ?>
                <div class="bx-alert bx-alert-warning" role="status" style="display:flex;justify-content:space-between;align-items:center;gap:12px;flex-wrap:wrap;margin-bottom:16px;">
                    <strong>
                        <?= $lang === 'bn'
                            ? "SEO সতর্কতা: {$openSeoAlertsCount}টি ইনডেক্সিং/লিংক সমস্যা পাওয়া গেছে।"
                            : "SEO Alerts: {$openSeoAlertsCount} active indexing or link health issue(s) need review." ?>
                    </strong>
                    <a href="<?= e(url('/admin/growth#seo-alerts')) ?>" class="bx-btn bx-btn-outline bx-btn-sm">
                        <?= $lang === 'bn' ? 'বিস্তারিত দেখুন' : 'Review Alerts' ?>
                    </a>
                </div>
            <?php endif; ?>
            <?php foreach ($flashes as $fl): ?>
                <div class="bx-alert bx-alert-<?= e($fl['type']) ?>" role="status">
                    <?= e($fl['message']) ?>
                </div>
            <?php endforeach; ?>

            <?= $adminContentHtml ?>
        </main>
    </div>
</div>

<?php if (!empty($flashes)): ?>
<script id="bx-flash-toasts" type="application/json"><?= json_encode($flashes, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) ?></script>
<?php endif; ?>

<!-- Custom Confirmation Modal -->
<div class="bx-modal-backdrop" id="bx-confirm-modal" role="dialog" aria-modal="true" aria-labelledby="bx-confirm-title">
    <div class="bx-modal" style="max-width:440px;">
        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:8px;">
            <h3 id="bx-confirm-title" style="margin:0;font-family:var(--font-serif);"><?= $lang === 'bn' ? 'নিশ্চিত করুন' : 'Confirm Action' ?></h3>
            <button type="button" class="bx-btn bx-btn-ghost" data-modal-close aria-label="Close" style="min-width:44px;min-height:44px;padding:6px;">✕</button>
        </div>
        <p id="bx-confirm-msg" style="color:var(--text-muted);margin:8px 0 18px;"></p>
        <div style="display:flex;justify-content:flex-end;gap:10px;flex-wrap:wrap;">
            <button type="button" class="bx-btn bx-btn-ghost" data-modal-close><?= $lang === 'bn' ? 'বাতিল' : 'Cancel' ?></button>
            <button type="button" id="bx-confirm-ok" class="bx-btn bx-btn-primary" style="background:var(--rose);border-color:var(--rose);"><?= $lang === 'bn' ? 'হ্যাঁ, এগিয়ে যান' : 'Yes, Proceed' ?></button>
        </div>
    </div>
</div>

<script src="<?= e(perf_dist_asset_url('app_js', 'assets/js/app.js')) ?>" defer></script>
<script src="<?= e(perf_dist_asset_url('admin_js', 'assets/js/admin.js')) ?>" defer></script>
<script src="<?= e(asset('assets/js/editor.js')) ?>" defer></script>
</body>
</html>
