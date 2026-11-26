<?php
declare(strict_types=1);
/**
 * Bookexa Hardened In-Browser PDF Reader — Mobile & Tablet Optimized
 * Variables: $item, $user, $isSample, $maxPages, $streamToken, $watermarkText, $savedPage, $folderUrl
 */
$lang = current_lang();
$readerMode   = $readerMode ?? 'unavailable';
$streamUrl    = ($readerMode === 'pdfjs' && $streamToken !== '') ? url('/api/stream-pdf?token=' . rawurlencode($streamToken)) : '';
$pageApiBase  = ($readerMode === 'pages') ? ($pageApiBase ?? '') : '';
$heartbeatUrl = url('/api/reader-heartbeat');
$progressUrl  = url('/api/reader-progress');
$secEventUrl  = url('/api/reader-security-event');
$backUrl      = $folderUrl;
$buyUrl       = url('/checkout/' . rawurlencode($item['slug']));
$userTheme    = $userTheme ?? '';
$userZoom     = $userZoom ?? null;
$tocItems     = [];
if (!empty($item['toc_json'])) {
    $decoded = json_decode((string) $item['toc_json'], true);
    if (is_array($decoded)) {
        foreach ($decoded as $te) {
            $tp = (int) ($te['page'] ?? 0);
            if ($tp >= 1 && ($maxPages <= 0 || $tp <= $maxPages)) {
                $tocItems[] = $te;
            }
        }
    }
}
$bookmarksUrl = url('/api/reader-bookmarks');
$searchBookUrl = ($readerMode === 'pages' && $streamToken !== '')
    ? url('/api/reader-search?item=' . (int) $item['id'] . '&token=' . rawurlencode($streamToken))
    : '';
?>
<!DOCTYPE html>
<html lang="<?= e($lang) ?>" data-reader-theme="<?= e($userTheme !== '' ? $userTheme : 'dark') ?>" class="bx-reader-html">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=5.0, viewport-fit=cover">
    <meta name="robots" content="noindex, nofollow">
    <meta name="theme-color" content="#0B1020">
    <meta name="bx-reader-theme" content="<?= e($userTheme) ?>">
    <meta name="bx-reader-uid" content="<?= (int) $user['id'] ?>">
    <title><?= e(bi($item, 'title')) ?> — <?= e(site_setting('site_name', 'Bookexa')) ?></title>
    <link rel="icon" type="image/svg+xml" href="<?= e(asset('assets/img/favicon.svg')) ?>">
    <link rel="icon" type="image/png" sizes="32x32" href="<?= e(asset('assets/img/favicon-32x32.png')) ?>">
    <link rel="apple-touch-icon" sizes="180x180" href="<?= e(asset('assets/img/apple-touch-icon.png')) ?>">
    <link rel="stylesheet" href="<?= e(asset('assets/css/fonts.css')) ?>">
    <link rel="stylesheet" href="<?= e(asset('assets/css/reader.css')) ?>">
    <script src="<?= e(asset('assets/js/theme-init.js')) ?>"></script>
</head>
<body class="bx-reader-body">
<div class="bx-reader-shell" id="reader-shell">

    <!-- Compact Sticky Top Bar -->
    <header class="bx-reader-topbar" id="reader-topbar">
        <div class="bx-reader-title">
            <a href="<?= e($backUrl) ?>" class="bx-rbtn" title="<?= e($lang === 'bn' ? 'ফোল্ডারে ফিরুন' : 'Back to Folder') ?>" aria-label="<?= e($lang === 'bn' ? 'ফোল্ডারে ফিরুন' : 'Back to Folder') ?>">
                <?= icon('chevron-right', 'bx-icon bx-rot-180') ?>
                <span class="bx-hide-sm"><?= e($lang === 'bn' ? 'ফোল্ডার' : 'Folder') ?></span>
            </a>
            <span class="bx-title-text"><?= e(bi($item, 'title')) ?></span>
        </div>

        <div class="bx-reader-top-actions">
            <button type="button" class="bx-rbtn" id="btn-bookmark-quick" title="<?= e($lang === 'bn' ? 'পৃষ্ঠা বুকমার্ক করুন' : 'Bookmark Page') ?>" aria-label="<?= e($lang === 'bn' ? 'পৃষ্ঠা বুকমার্ক করুন' : 'Bookmark Page') ?>">
                <?= icon('heart', 'bx-icon') ?>
            </button>
            <button type="button" class="bx-rbtn" id="btn-thumbs" title="<?= e($lang === 'bn' ? 'পৃষ্ঠা, সূচিপত্র, বুকমার্ক ও খুঁজুন' : 'Pages, TOC, Bookmarks & Search') ?>" aria-label="<?= e($lang === 'bn' ? 'রিডার টুলস' : 'Reader Drawer') ?>">
                <?= icon('layers', 'bx-icon') ?>
                <span class="bx-hide-sm"><?= e($lang === 'bn' ? 'সূচি ও টুলস' : 'Tools') ?></span>
            </button>
            <button type="button" class="bx-rbtn" id="btn-open-settings" title="<?= e($lang === 'bn' ? 'রিডার সেটিংস ও থিম' : 'Reader Settings') ?>" aria-label="<?= e($lang === 'bn' ? 'রিডার সেটিংস ও থিম' : 'Reader Settings') ?>">
                <?= icon('settings', 'bx-icon') ?>
                <span class="bx-hide-sm"><?= e($lang === 'bn' ? 'সেটিংস' : 'Settings') ?></span>
            </button>
            <button type="button" class="bx-rbtn bx-hide-sm" id="btn-reader-theme" title="<?= e($lang === 'bn' ? 'পড়ার থিম বদলান (ডার্ক / সেপিয়া / লাইট)' : 'Switch Theme (Dark / Sepia / Light)') ?>" aria-label="<?= e($lang === 'bn' ? 'পড়ার থিম বদলান' : 'Switch Reading Theme') ?>">
                <span class="bx-rtheme-moon"><?= icon('moon', 'bx-icon') ?></span>
                <span class="bx-rtheme-sepia"><?= icon('sepia', 'bx-icon') ?></span>
                <span class="bx-rtheme-sun"><?= icon('sun', 'bx-icon') ?></span>
            </button>
            <button type="button" class="bx-rbtn bx-hide-sm" id="btn-fullscreen" title="<?= e($lang === 'bn' ? 'ফুলস্ক্রিন' : 'Toggle Fullscreen') ?>" aria-label="<?= e($lang === 'bn' ? 'ফুলস্ক্রিন' : 'Toggle Fullscreen') ?>">
                <?= icon('fullscreen', 'bx-icon') ?>
            </button>
        </div>
    </header>

    <?php if ($isSample): ?>
        <div class="bx-sample-banner" id="reader-sample-banner">
            <span class="bx-sample-text"><?= e($lang === 'bn' ? 'ফ্রি স্যাম্পল (প্রথম ' . num_localize($maxPages) . ' পৃষ্ঠা — সার্ভার সীমাবদ্ধ)' : 'Free Sample (First ' . $maxPages . ' pages — server enforced)') ?></span>
            <a href="<?= e($buyUrl) ?>" class="bx-rbtn bx-rbtn-accent"><?= e(t('buy_now_btn')) ?> — <?= e(format_bdt((float) $item['price'])) ?></a>
        </div>
    <?php endif; ?>

    <div class="bx-reader-workspace">
        <!-- Reader Drawer: Pages / TOC / Bookmarks / Search Inside Book -->
        <aside class="bx-thumb-sidebar" id="thumb-sidebar" aria-label="Reader Drawer">
            <div class="bx-thumb-head">
                <strong><?= $lang === 'bn' ? 'রিডার টুলস' : 'Reader Drawer' ?></strong>
                <button type="button" class="bx-rbtn" id="btn-thumbs-close" title="<?= e($lang === 'bn' ? 'বন্ধ করুন' : 'Close') ?>" aria-label="<?= e($lang === 'bn' ? 'বন্ধ করুন' : 'Close Drawer') ?>"><?= icon('close', 'bx-icon') ?></button>
            </div>
            <div class="bx-drawer-tabs" role="tablist">
                <button type="button" class="bx-drawer-tab active" data-drawer-tab="pages" role="tab"><?= $lang === 'bn' ? 'পৃষ্ঠা' : 'Pages' ?></button>
                <button type="button" class="bx-drawer-tab" data-drawer-tab="toc" role="tab"><?= $lang === 'bn' ? 'সূচিপত্র' : 'TOC' ?></button>
                <button type="button" class="bx-drawer-tab" data-drawer-tab="bookmarks" role="tab"><?= $lang === 'bn' ? 'বুকমার্ক' : 'Saved' ?></button>
                <button type="button" class="bx-drawer-tab" data-drawer-tab="search" role="tab"><?= $lang === 'bn' ? 'খুঁজুন' : 'Search' ?></button>
            </div>
            <div class="bx-drawer-pane active" id="pane-pages">
                <div class="bx-thumb-list" id="thumb-list" style="padding:0;"></div>
            </div>
            <div class="bx-drawer-pane" id="pane-toc">
                <div id="toc-list" style="display:flex;flex-direction:column;gap:6px;"></div>
            </div>
            <div class="bx-drawer-pane" id="pane-bookmarks">
                <form id="bm-add-form" style="display:flex;gap:6px;flex-wrap:wrap;margin-bottom:4px;">
                    <input type="text" id="bm-label-input" maxlength="120" class="bx-jump-input" style="flex:1 1 120px;width:auto;text-align:left;padding:6px 10px;font-size:0.86rem;" placeholder="<?= e($lang === 'bn' ? 'বুকমার্ক নাম (ঐচ্ছিক)' : 'Bookmark label...') ?>">
                    <button type="submit" class="bx-rbtn bx-rbtn-accent" style="min-height:40px;padding:4px 10px;"><?= $lang === 'bn' ? 'সংরক্ষণ' : 'Add' ?></button>
                </form>
                <div id="bm-list" style="display:flex;flex-direction:column;gap:8px;"></div>
            </div>
            <div class="bx-drawer-pane" id="pane-search">
                <form id="rsearch-form" style="display:flex;gap:6px;margin-bottom:4px;">
                    <input type="search" id="rsearch-input" minlength="2" class="bx-jump-input" style="flex:1;width:auto;text-align:left;padding:6px 10px;font-size:0.86rem;" placeholder="<?= e($lang === 'bn' ? 'বইয়ের ভেতর খুঁজুন...' : 'Search inside book...') ?>">
                    <button type="submit" class="bx-rbtn bx-rbtn-accent" style="min-height:40px;padding:4px 10px;"><?= icon('search', 'bx-icon') ?></button>
                </form>
                <div id="rsearch-results" style="display:flex;flex-direction:column;gap:8px;user-select:none;"></div>
            </div>
        </aside>

        <!-- Main Touch/Canvas Stage -->
        <main class="bx-reader-stage" id="reader-stage">
            <div id="reader-loader" class="bx-page-skeleton">
                <div class="bx-skel-shimmer"></div>
                <div id="loader-status" class="bx-loader-msg">
                    <p style="margin:0 0 10px;"><?= $lang === 'bn' ? 'সুরক্ষিত পিডিএফ লোড হচ্ছে...' : 'Loading protected PDF...' ?></p>
                </div>
            </div>

            <div id="page-render-skel" class="bx-page-skeleton bx-render-skel" style="display:none;" aria-hidden="true">
                <div class="bx-skel-shimmer"></div>
            </div>

            <div class="bx-spread" id="page-spread" style="display:none;">
                <div class="bx-page-card" id="page-card-1">
                    <canvas id="pdf-canvas-1"></canvas>
                </div>
                <div class="bx-page-card" id="page-card-2" style="display:none;">
                    <canvas id="pdf-canvas-2"></canvas>
                </div>
            </div>
        </main>
    </div>

    <!-- Bottom Progress & Page Controls Bar -->
    <footer class="bx-reader-bottombar" id="reader-bottombar">
        <div class="bx-progress-track" aria-hidden="true">
            <div class="bx-progress-fill" id="bx-read-progress-fill"></div>
        </div>
        <div class="bx-bottombar-inner">
            <button type="button" class="bx-rbtn" id="btn-prev" disabled title="<?= e($lang === 'bn' ? 'আগের পৃষ্ঠা' : 'Previous Page') ?>" aria-label="<?= e($lang === 'bn' ? 'আগের পৃষ্ঠা' : 'Previous Page') ?>">
                <?= icon('chevron-right', 'bx-icon bx-rot-180') ?>
                <span class="bx-hide-sm"><?= e($lang === 'bn' ? 'আগের' : 'Prev') ?></span>
            </button>

            <span class="bx-page-counter">
                <input type="number" id="page-jump" min="1" inputmode="numeric" value="<?= (int) $savedPage ?>" class="bx-jump-input" title="<?= e($lang === 'bn' ? 'পৃষ্ঠা নম্বর লিখুন' : 'Jump to page') ?>" aria-label="Jump to page">
                <span>/ <span id="page-count">—</span></span>
            </span>

            <div class="bx-zoom-group bx-hide-sm">
                <button type="button" class="bx-rbtn" id="btn-zoom-out" title="<?= e($lang === 'bn' ? 'ছোট করুন' : 'Zoom Out') ?>" aria-label="<?= e($lang === 'bn' ? 'ছোট করুন' : 'Zoom Out') ?>"><?= icon('zoom-out', 'bx-icon') ?></button>
                <button type="button" class="bx-rbtn" id="btn-fit-width" title="<?= e($lang === 'bn' ? 'প্রস্থ অনুযায়ী ফিট' : 'Fit to Width') ?>" aria-label="<?= e($lang === 'bn' ? 'প্রস্থ অনুযায়ী ফিট' : 'Fit to Width') ?>"><?= icon('fit-width', 'bx-icon') ?></button>
                <button type="button" class="bx-rbtn" id="btn-zoom-in" title="<?= e($lang === 'bn' ? 'বড় করুন' : 'Zoom In') ?>" aria-label="<?= e($lang === 'bn' ? 'বড় করুন' : 'Zoom In') ?>"><?= icon('zoom-in', 'bx-icon') ?></button>
                <button type="button" class="bx-rbtn bx-hide-tablet" id="btn-spread" title="<?= e($lang === 'bn' ? 'এক / দুই পৃষ্ঠা লেআউট' : 'Single / Double Page Layout') ?>" aria-label="<?= e($lang === 'bn' ? 'এক / দুই পৃষ্ঠা লেআউট' : 'Single / Double Page Layout') ?>">
                    <span class="bx-spread-single"><?= icon('single-page', 'bx-icon') ?></span>
                    <span class="bx-spread-double"><?= icon('double-page', 'bx-icon') ?></span>
                </button>
            </div>

            <button type="button" class="bx-rbtn" id="btn-next" disabled title="<?= e($lang === 'bn' ? 'পরবর্তী পৃষ্ঠা' : 'Next Page') ?>" aria-label="<?= e($lang === 'bn' ? 'পরবর্তী পৃষ্ঠা' : 'Next Page') ?>">
                <span class="bx-hide-sm"><?= e($lang === 'bn' ? 'পরবর্তী' : 'Next') ?></span>
                <?= icon('chevron-right', 'bx-icon') ?>
            </button>
        </div>
    </footer>
</div>

<!-- Reader Settings Bottom Sheet (Themes, Zoom, Fit, Mode) -->
<div class="bx-rsheet-backdrop" id="settings-sheet" role="dialog" aria-modal="true" aria-labelledby="rsheet-title">
    <div class="bx-rsheet">
        <div class="bx-rsheet-head">
            <strong id="rsheet-title"><?= e($lang === 'bn' ? 'রিডার সেটিংস ও থিম' : 'Reader Settings & Theme') ?></strong>
            <button type="button" class="bx-rbtn" id="btn-close-settings" title="<?= e($lang === 'bn' ? 'বন্ধ করুন' : 'Close Settings') ?>" aria-label="<?= e($lang === 'bn' ? 'বন্ধ করুন' : 'Close Settings') ?>"><?= icon('close', 'bx-icon') ?></button>
        </div>
        <div class="bx-rsheet-body">
            <div class="bx-rsheet-section">
                <label class="bx-rsheet-label"><?= e($lang === 'bn' ? 'পড়ার থিম' : 'Reading Theme') ?></label>
                <div class="bx-theme-pills">
                    <button type="button" class="bx-rbtn" data-set-theme="dark" title="Dark Theme"><?= icon('moon', 'bx-icon') ?> <span><?= e($lang === 'bn' ? 'ডার্ক' : 'Dark') ?></span></button>
                    <button type="button" class="bx-rbtn" data-set-theme="sepia" title="Sepia Theme"><?= icon('sepia', 'bx-icon') ?> <span><?= e($lang === 'bn' ? 'সেপিয়া' : 'Sepia') ?></span></button>
                    <button type="button" class="bx-rbtn" data-set-theme="light" title="Light Theme"><?= icon('sun', 'bx-icon') ?> <span><?= e($lang === 'bn' ? 'লাইট' : 'Light') ?></span></button>
                </div>
            </div>
            <div class="bx-rsheet-section">
                <label class="bx-rsheet-label"><?= e($lang === 'bn' ? 'জুম ও ফিট' : 'Zoom & Page Fit') ?></label>
                <div class="bx-theme-pills">
                    <button type="button" class="bx-rbtn" id="sheet-zoom-out" title="<?= e($lang === 'bn' ? 'ছোট' : 'Zoom Out') ?>"><?= icon('zoom-out', 'bx-icon') ?> <span><?= e($lang === 'bn' ? 'ছোট' : 'Out') ?></span></button>
                    <button type="button" class="bx-rbtn" id="sheet-fit-width" title="<?= e($lang === 'bn' ? 'ফিট' : 'Fit Width') ?>"><?= icon('fit-width', 'bx-icon') ?> <span><?= e($lang === 'bn' ? 'ফিট' : 'Fit') ?></span></button>
                    <button type="button" class="bx-rbtn" id="sheet-zoom-in" title="<?= e($lang === 'bn' ? 'বড়' : 'Zoom In') ?>"><?= icon('zoom-in', 'bx-icon') ?> <span><?= e($lang === 'bn' ? 'বড়' : 'In') ?></span></button>
                </div>
            </div>
            <div class="bx-rsheet-section bx-show-wide">
                <label class="bx-rsheet-label"><?= e($lang === 'bn' ? 'পৃষ্ঠা লেআউট (বড় স্ক্রিন)' : 'Spread Layout (Wide Screens)') ?></label>
                <button type="button" class="bx-rbtn" id="sheet-toggle-spread"><?= icon('double-page', 'bx-icon') ?> <span><?= e($lang === 'bn' ? '১ পৃষ্ঠা / ২ পৃষ্ঠা বদলান' : 'Toggle Single / Double Page') ?></span></button>
            </div>
            <?php if ($readerMode === 'pages'): ?>
            <div class="bx-rsheet-section">
                <label class="bx-rsheet-label"><?= e($lang === 'bn' ? 'মোবাইল ডাটা সাশ্রয়' : 'Mobile Image Mode') ?></label>
                <button type="button" class="bx-rbtn" id="sheet-toggle-lightweight" aria-pressed="true"><?= icon('sparkles', 'bx-icon') ?> <span id="sheet-lw-label"><?= e($lang === 'bn' ? 'ফোনে হালকা ছবি (১০০০px): চালু' : 'Lightweight Phone Size (1000px): ON') ?></span></button>
            </div>
            <?php endif; ?>
            <p class="bx-rsheet-hint">
                <?= e($lang === 'bn' ? 'টিপস: ডাবল-ট্যাপ বা পিঞ্চ করে জুম করুন, ডানে-বামে সোয়াইপ করে পৃষ্ঠা বদলান, মাঝে ট্যাপ করে বার লুকান।' : 'Tip: Pinch or double-tap to zoom, swipe left/right to turn pages, tap center to hide bars.') ?>
            </p>
        </div>
    </div>
</div>

<!-- Anti-Piracy & Tab Blur / Session Kick Shield -->
<div class="bx-reader-shield" id="reader-shield">
    <div class="bx-reader-shield-card">
        <div style="margin-bottom:10px;color:#E86A4D;"><?= icon('shield', 'bx-icon-lg') ?></div>
        <p id="shield-message" style="margin:0 0 18px;font-size:1rem;">
            <?= e($lang === 'bn' ? 'সুরক্ষার জন্য পাতাটি আড়াল করা হয়েছে। পড়া চালিয়ে যেতে ক্লিক করুন।' : 'Content hidden for protection. Click to resume reading.') ?>
        </p>
        <button type="button" class="bx-rbtn bx-rbtn-accent" id="btn-resume">
            <?= e($lang === 'bn' ? 'পড়া চালিয়ে যান' : 'Resume Reading') ?>
        </button>
    </div>
</div>

<?php if ($readerMode === 'pdfjs'): ?>
<script src="<?= e(asset('assets/vendor/pdfjs/pdf.min.js')) ?>"></script>
<?php endif; ?>
<script id="bx-reader-config" type="application/json"><?= json_encode([
    'mode'          => $readerMode,
    'userId'        => (int) $user['id'],
    'workerUrl'     => asset('assets/vendor/pdfjs/pdf.worker.min.js'),
    'pageApiBase'   => $pageApiBase,
    'streamUrl'     => $streamUrl,
    'heartbeatUrl'  => $heartbeatUrl,
    'progressUrl'   => $progressUrl,
    'bookmarksUrl'  => $bookmarksUrl,
    'searchUrl'     => $searchBookUrl,
    'secEventUrl'   => $secEventUrl,
    'tocItems'      => $tocItems,
    'csrfToken'     => csrf_token(),
    'itemId'        => (int) $item['id'],
    'initialPage'   => (int) $savedPage,
    'initialTheme'  => $userTheme,
    'initialZoom'   => $userZoom,
    'backUrl'       => $backUrl,
    'isSample'      => (bool) $isSample,
    'maxPages'      => (int) $maxPages,
    'lightweightMobileDefault' => site_setting('reader_mobile_lightweight', '1') === '1',
    'lightweightOnLabel'  => $lang === 'bn' ? 'ফোনে হালকা ছবি (১০০০px): চালু' : 'Lightweight Phone Size (1000px): ON',
    'lightweightOffLabel' => $lang === 'bn' ? 'ফোনে হালকা ছবি (১০০০px): বন্ধ' : 'Lightweight Phone Size (1000px): OFF',
    'watermarkText' => $watermarkText,
    'kickedMessage' => $lang === 'bn' ? 'অন্য ডিভাইস বা ট্যাবে আপনার অ্যাকাউন্ট দিয়ে রিডার চালু হয়েছে। নিরাপত্তার জন্য এই সেশনটি বন্ধ করা হয়েছে।' : 'Another device or tab started reading with this account. This session has been closed.',
    'blurMessage'   => $lang === 'bn' ? 'সুরক্ষার জন্য পাতাটি আড়াল করা হয়েছে। পড়া চালিয়ে যেতে ক্লিক করুন।' : 'Content hidden for protection. Click to resume reading.',
    'resizeMessage' => $lang === 'bn' ? 'সন্দেহজনক উইন্ডো পরিবর্তন শনাক্ত হয়েছে। পড়া চালিয়ে যেতে ক্লিক করুন।' : 'Suspicious window resize detected. Click to resume reading.',
    'loadErrorMsg'  => $lang === 'bn' ? 'পৃষ্ঠা লোড করা যায়নি।' : 'Could not load document pages.',
    'unavailableMsg'=> !empty($unavailableCustomMsg)
        ? (string) $unavailableCustomMsg
        : ($lang === 'bn' ? 'এই বইয়ের সুরক্ষিত পৃষ্ঠাগুলো এখনও প্রস্তুত হয়নি। অনুগ্রহ করে অ্যাডমিনের সাথে যোগাযোগ করুন।' : 'Protected pages for this book are still being prepared. Please check back shortly.'),
    'retryLabel'    => $lang === 'bn' ? 'আবার চেষ্টা করুন' : 'Retry',
    'reloadLabel'   => $lang === 'bn' ? 'রিডার রিলোড করুন' : 'Reload Session',
], JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) ?></script>
<script src="<?= e(asset('assets/js/reader.js')) ?>"></script>
</body>
</html>
