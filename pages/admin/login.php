<?php
declare(strict_types=1);

$lang = current_lang();
$siteName = site_setting('site_name', 'Bookexa');
$faviconSetting = site_setting('site_favicon', '');
$faviconHref = $faviconSetting !== '' ? asset($faviconSetting) : asset('assets/img/favicon.svg');
$flashes = get_flashes();
$altLang = $lang === 'bn' ? 'en' : 'bn';
?>
<!DOCTYPE html>
<html lang="<?= e($lang) ?>" data-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <meta name="robots" content="noindex, nofollow, noarchive">
    <meta name="theme-color" content="#2B2F77">
    <title><?= $lang === 'bn' ? 'অ্যাডমিন লগইন' : 'Admin Sign In' ?> — <?= e($siteName) ?></title>
    <link rel="icon" href="<?= e($faviconHref) ?>">
    <link rel="stylesheet" href="<?= e(asset('assets/css/fonts.css')) ?>">
    <link rel="stylesheet" href="<?= e(asset('assets/css/tokens.css')) ?>">
    <link rel="stylesheet" href="<?= e(asset('assets/css/components.css')) ?>">
    <link rel="stylesheet" href="<?= e(asset('assets/css/motion.css')) ?>">
    <link rel="stylesheet" href="<?= e(asset('assets/css/admin.css')) ?>">
    <script src="<?= e(asset('assets/js/theme-init.js')) ?>"></script>
</head>
<body style="min-height:100vh;display:flex;align-items:center;justify-content:center;padding:24px;background:var(--bg);">
<div class="bx-hero-mesh" style="position:fixed;inset:0;pointer-events:none;opacity:0.45;"></div>

<div class="bx-card" style="width:100%;max-width:440px;padding:30px;position:relative;z-index:2;backdrop-filter:blur(16px);">
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:20px;">
        <a href="<?= e(url('/')) ?>" style="display:flex;align-items:center;gap:10px;text-decoration:none;color:var(--text);">
            <span style="width:38px;height:38px;border-radius:12px;background:var(--grad-indigo-teal);display:inline-flex;align-items:center;justify-content:center;color:#FBF6EC;">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M2 3h6a4 4 0 0 1 4 4v14a3 3 0 0 0-3-3H2z"/><path d="M22 3h-6a4 4 0 0 0-4 4v14a3 3 0 0 1 3-3h7z"/></svg>
            </span>
            <strong style="font-size:1.25rem;font-family:var(--font-serif);"><?= e($siteName) ?></strong>
        </a>
        <div style="display:flex;gap:6px;">
            <a href="<?= e(url('/admin/login?lang=' . $altLang)) ?>" class="bx-btn bx-btn-ghost" style="padding:6px 10px;font-size:0.82rem;"><?= $lang === 'bn' ? 'EN' : 'বাং' ?></a>
            <button type="button" class="bx-btn bx-btn-ghost" data-theme-toggle aria-label="Theme" style="padding:6px 10px;">◐</button>
        </div>
    </div>

    <h1 style="margin:0 0 6px;font-family:var(--font-serif);font-size:1.5rem;">
        <?= $lang === 'bn' ? 'অ্যাডমিন প্যানেলে প্রবেশ' : 'Admin Portal Sign In' ?>
    </h1>
    <p style="margin:0 0 20px;color:var(--text-muted);font-size:0.9rem;">
        <?= $lang === 'bn' ? 'সুরক্ষিত সেশন ও ৫-চেষ্টার রেট লিমিট সক্রিয় রয়েছে।' : 'Protected session with login attempt rate limiting.' ?>
    </p>

    <?php if (!empty($error)): ?>
        <div class="bx-alert bx-alert-danger" role="alert"><?= e($error) ?></div>
    <?php endif; ?>
    <?php foreach ($flashes as $fl): ?>
        <div class="bx-alert bx-alert-<?= e($fl['type']) ?>" role="status"><?= e($fl['message']) ?></div>
    <?php endforeach; ?>

    <form method="post" action="<?= e(url('/admin/login')) ?>">
        <?= csrf_field() ?>
        <div class="bx-field" style="margin-bottom:14px;">
            <label for="admin-email" style="display:block;font-weight:600;margin-bottom:6px;"><?= $lang === 'bn' ? 'অ্যাডমিন ইমেইল' : 'Admin Email' ?></label>
            <input type="email" id="admin-email" name="email" class="bx-input" required autocomplete="username" placeholder="admin@yourdomain.com">
        </div>
        <div class="bx-field" style="margin-bottom:18px;">
            <label for="admin-pass" style="display:block;font-weight:600;margin-bottom:6px;"><?= $lang === 'bn' ? 'পাসওয়ার্ড' : 'Password' ?></label>
            <input type="password" id="admin-pass" name="password" class="bx-input" required autocomplete="current-password" placeholder="••••••••">
        </div>
        <button type="submit" class="bx-btn bx-btn-primary" style="width:100%;justify-content:center;padding:12px;">
            <?= $lang === 'bn' ? 'লগইন করুন' : 'Sign In to Dashboard' ?>
        </button>
    </form>

    <div style="margin-top:18px;text-align:center;">
        <a href="<?= e(url('/')) ?>" style="color:var(--text-muted);font-size:0.86rem;">← <?= $lang === 'bn' ? 'হোমপেজে ফিরে যান' : 'Back to Public Storefront' ?></a>
    </div>
</div>

<script src="<?= e(asset('assets/js/app.js')) ?>"></script>
</body>
</html>
