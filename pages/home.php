<?php
declare(strict_types=1);

$pubRaw = published_window_sql();
$pubF   = published_window_sql('f');
$pubI   = published_window_sql('i');

$banners = db_all(
    "SELECT * FROM banners
     WHERE is_active = 1
       AND (COALESCE(publish_at, starts_at) IS NULL OR COALESCE(publish_at, starts_at) <= NOW())
       AND (COALESCE(unpublish_at, ends_at) IS NULL OR COALESCE(unpublish_at, ends_at) > NOW())
     ORDER BY sort_order ASC, id DESC"
);

$folderTree = get_cached_folder_tree();
$featuredFolders = [];
foreach (array_slice($folderTree['children']['root'] ?? [], 0, 6) as $rootFid) {
    if (isset($folderTree['byId'][(string) $rootFid])) {
        $featuredFolders[] = $folderTree['byId'][(string) $rootFid];
    }
}

$newestItems = db_all(
    "SELECT * FROM items WHERE is_active = 1 AND {$pubRaw} ORDER BY id DESC LIMIT 8"
);
$popularItems = db_all(
    "SELECT *, (read_count + view_count) AS pop_score FROM items WHERE is_active = 1 AND {$pubRaw} ORDER BY pop_score DESC, is_featured DESC, id DESC LIMIT 8"
);
$freeItems = db_all(
    "SELECT * FROM items WHERE is_active = 1 AND {$pubRaw} AND (is_free = 1 OR price <= 0) ORDER BY is_featured DESC, id DESC LIMIT 8"
);

$homeUser = current_user();
$continueReading = [];
$recentlyViewed  = [];
if ($homeUser) {
    $hUid = (int) $homeUser['id'];
    $continueReading = db_all(
        "SELECT i.*, rp.last_page, rp.total_pages AS prog_total_pages, rp.updated_at AS read_at
         FROM reading_progress rp
         JOIN items i ON i.id = rp.item_id
         WHERE rp.user_id = ? AND i.is_active = 1 AND {$pubI}
         ORDER BY rp.updated_at DESC
         LIMIT 4",
        [$hUid]
    );
    $recentlyViewed = db_all(
        "SELECT i.*
         FROM recently_viewed rv
         JOIN items i ON i.id = rv.item_id
         WHERE rv.user_id = ? AND i.is_active = 1 AND {$pubI}
         ORDER BY rv.viewed_at DESC
         LIMIT 8",
        [$hUid]
    );
}

$totalFoldersStat = (int) ($folderTree['total_active'] ?? 0);
$totalItemsStat   = (int) (db_one("SELECT COUNT(*) AS c FROM items WHERE is_active = 1 AND {$pubRaw}")['c'] ?? 0);
$lang = current_lang();
?>

<section class="bx-hero-wrap">
    <div class="container">
        <?php if (!empty($banners)): ?>
            <div class="bx-slider bx-hero-animated-bg" data-hero-slider>
                <div class="bx-hero-gradient-orb" style="right:0;top:0;"></div>
                <?php foreach ($banners as $i => $bn): ?>
                    <div class="bx-slide <?= $i === 0 ? 'active' : '' ?>">
                        <picture>
                            <?php if (!empty($bn['mobile_image'])): ?>
                                <source media="(max-width: 720px)" srcset="<?= e(asset($bn['mobile_image'])) ?>">
                            <?php endif; ?>
                            <img class="bx-slide-bg" src="<?= e(asset($bn['image'])) ?>" alt="<?= e(bi($bn, 'title')) ?>" width="1200" height="450" <?= $i === 0 ? 'fetchpriority="high"' : 'loading="lazy"' ?> decoding="async">
                        </picture>
                        <div class="bx-slide-overlay"></div>
                        <div class="bx-slide-content">
                            <span class="bx-badge" style="background:rgba(255,255,255,0.16);color:#FAF7F2;margin-bottom:14px;">
                                <?= icon('shield') ?> <?= e(t('hero_badge')) ?>
                            </span>
                            <h1 class="bx-hero-reveal"><?= e(bi($bn, 'title')) ?></h1>
                            <?php if (bi($bn, 'subtitle') !== ''): ?>
                                <p><?= e(bi($bn, 'subtitle')) ?></p>
                            <?php endif; ?>
                            <a href="<?= e(url($bn['link_url'] ?: '/pdf')) ?>" class="bx-btn bx-btn-accent">
                                <?= e(bi($bn, 'cta', t('portal_pdf_cta'))) ?> <?= icon('chevron-right') ?>
                            </a>
                        </div>
                        <div class="bx-hero-float-stage bx-hide-sm" style="position:relative;z-index:2;">
                            <div class="bx-float-obj obj-book-1">
                                <?= icon('book', 'bx-icon-lg') ?>
                                <small style="font-weight:700;">Academic<br>PDFs</small>
                            </div>
                            <div class="bx-float-obj obj-book-2">
                                <?= icon('sparkles', 'bx-icon-lg') ?>
                                <small style="font-weight:700;">BCS &amp;<br>Notes</small>
                            </div>
                            <div class="bx-float-obj obj-folder">
                                <?= icon('folder', 'bx-icon-lg') ?>
                                <small style="font-weight:700;">Folders</small>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>

                <?php if (count($banners) > 1): ?>
                    <button type="button" class="bx-slider-arrow prev" data-slider-prev aria-label="Previous slide">
                        <span style="transform:rotate(180deg);display:inline-flex;"><?= icon('chevron-right') ?></span>
                    </button>
                    <button type="button" class="bx-slider-arrow next" data-slider-next aria-label="Next slide">
                        <?= icon('chevron-right') ?>
                    </button>
                    <div class="bx-slider-dots">
                        <?php foreach ($banners as $i => $_): ?>
                            <button type="button" class="bx-slider-dot <?= $i === 0 ? 'active' : '' ?>" aria-label="Slide <?= $i + 1 ?>"></button>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        <?php else: ?>
            <?php $shopEnabled = site_setting('shop_enabled', '0') === '1'; ?>
            <!-- Fallback when no active banner exists: 3D Portal Cards -->
            <div class="bx-portal-grid" style="<?= $shopEnabled ? '' : 'grid-template-columns: minmax(0, 1fr);' ?>">
                <a href="<?= e(url('/pdf')) ?>" class="bx-portal-3d">
                    <div class="bx-portal-card pdf-portal">
                        <div>
                            <span class="bx-badge" style="background:rgba(255,255,255,0.16);color:#FAF7F2;">
                                <?= icon('book') ?> <?= e(t('hero_badge')) ?>
                            </span>
                            <h2><?= e(t('portal_pdf_title')) ?></h2>
                            <p><?= e(t('portal_pdf_desc')) ?></p>
                        </div>
                        <div>
                            <span class="bx-btn bx-btn-accent"><?= e(t('portal_pdf_cta')) ?> <?= icon('chevron-right') ?></span>
                        </div>
                    </div>
                </a>

                <?php if ($shopEnabled): ?>
                <a href="<?= e(url('/shop')) ?>" class="bx-portal-3d">
                    <div class="bx-portal-card shop-portal">
                        <div>
                            <span class="bx-badge" style="background:rgba(255,255,255,0.16);color:#FAF7F2;">
                                <?= icon('cart') ?> <?= e(t('shop_badge')) ?>
                            </span>
                            <h2><?= e(t('portal_shop_title')) ?></h2>
                            <p><?= e(t('portal_shop_desc')) ?></p>
                        </div>
                        <div>
                            <span class="bx-btn bx-btn-primary"><?= e(t('portal_shop_cta')) ?> <?= icon('chevron-right') ?></span>
                        </div>
                    </div>
                </a>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>
</section>

<!-- Continue Reading (Logged-in users with progress) -->
<?php if (!empty($continueReading)): ?>
<section class="bx-section">
    <div class="container">
        <div class="bx-section-head">
            <div>
                <h2 class="bx-h2"><?= $lang === 'bn' ? 'যেখান থেকে পড়া শেষ করেছিলেন' : 'Continue Reading' ?></h2>
                <p><?= $lang === 'bn' ? 'আপনার সর্বশেষ পড়া পৃষ্ঠা থেকে আবার শুরু করুন।' : 'Jump right back in where you left off.' ?></p>
            </div>
            <a href="<?= e(url('/library')) ?>" class="bx-btn bx-btn-outline bx-btn-sm">
                <?= e(t('nav_library')) ?> <?= icon('chevron-right') ?>
            </a>
        </div>
        <div class="bx-book-grid" data-stagger>
            <?php foreach ($continueReading as $cr): ?>
                <?= render_book_card($cr) ?>
            <?php endforeach; ?>
        </div>
    </div>
</section>
<?php endif; ?>

<!-- Featured Folders -->
<section class="bx-section">
    <div class="container">
        <div class="bx-section-head">
            <div>
                <h2 class="bx-h2"><?= e(t('featured_folders')) ?></h2>
                <p><?= e(t('featured_folders_sub')) ?></p>
            </div>
            <a href="<?= e(url('/pdf')) ?>" class="bx-btn bx-btn-outline bx-btn-sm">
                <?= e(t('view_all')) ?> <?= icon('chevron-right') ?>
            </a>
        </div>

        <div class="bx-folder-grid" data-stagger>
            <?php foreach ($featuredFolders as $folder): ?>
                <?= render_folder_card($folder, url('/pdf/' . rawurlencode($folder['slug']))) ?>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<!-- Newest PDFs (Recently Added) -->
<section class="bx-section">
    <div class="container">
        <div class="bx-section-head">
            <div>
                <h2 class="bx-h2"><?= $lang === 'bn' ? 'সদ্য যুক্ত বই' : 'Recently Added' ?></h2>
                <p><?= e(t('newest_pdfs_sub')) ?></p>
            </div>
            <a href="<?= e(url('/search?sort=newest')) ?>" class="bx-btn bx-btn-outline bx-btn-sm">
                <?= e(t('view_all')) ?> <?= icon('chevron-right') ?>
            </a>
        </div>

        <div class="bx-book-grid" data-stagger>
            <?php foreach ($newestItems as $item): ?>
                <?= render_book_card($item) ?>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<!-- Popular PDFs -->
<?php if (!empty($popularItems)): ?>
<section class="bx-section">
    <div class="container">
        <div class="bx-section-head">
            <div>
                <h2 class="bx-h2"><?= $lang === 'bn' ? 'জনপ্রিয় বই' : 'Popular Books' ?></h2>
                <p><?= $lang === 'bn' ? 'সবচেয়ে বেশি পঠিত ও প্রশংসিত পিডিএফ।' : 'Most read and viewed digital books on Bookexa.' ?></p>
            </div>
            <a href="<?= e(url('/search?sort=popular')) ?>" class="bx-btn bx-btn-outline bx-btn-sm">
                <?= e(t('view_all')) ?> <?= icon('chevron-right') ?>
            </a>
        </div>
        <div class="bx-book-grid" data-stagger>
            <?php foreach ($popularItems as $item): ?>
                <?= render_book_card($item) ?>
            <?php endforeach; ?>
        </div>
    </div>
</section>
<?php endif; ?>

<!-- Free to Read -->
<?php if (!empty($freeItems)): ?>
<section class="bx-section">
    <div class="container">
        <div class="bx-section-head">
            <div>
                <h2 class="bx-h2"><?= $lang === 'bn' ? 'বিনামূল্যে পড়ুন' : 'Free to Read' ?></h2>
                <p><?= $lang === 'bn' ? 'কোনো পেমেন্ট ছাড়াই সরাসরি পড়তে পারবেন।' : 'Open and read immediately at zero cost.' ?></p>
            </div>
            <a href="<?= e(url('/search?price_type=free')) ?>" class="bx-btn bx-btn-outline bx-btn-sm">
                <?= e(t('view_all')) ?> <?= icon('chevron-right') ?>
            </a>
        </div>
        <div class="bx-book-grid" data-stagger>
            <?php foreach ($freeItems as $item): ?>
                <?= render_book_card($item) ?>
            <?php endforeach; ?>
        </div>
    </div>
</section>
<?php endif; ?>

<!-- Recently Viewed (Per User in MySQL, or localStorage for guests) -->
<?php if (!empty($recentlyViewed)): ?>
<section class="bx-section">
    <div class="container">
        <div class="bx-section-head">
            <div>
                <h2 class="bx-h2"><?= $lang === 'bn' ? 'সম্প্রতি দেখা বই' : 'Recently Viewed' ?></h2>
                <p><?= $lang === 'bn' ? 'আপনার সাম্প্রতিক পরিদর্শিত বইসমূহ।' : 'PDFs you checked out recently.' ?></p>
            </div>
        </div>
        <div class="bx-book-grid" data-stagger>
            <?php foreach ($recentlyViewed as $item): ?>
                <?= render_book_card($item) ?>
            <?php endforeach; ?>
        </div>
    </div>
</section>
<?php elseif (!$homeUser): ?>
<section class="bx-section" id="bx-guest-recent-sec" hidden>
    <div class="container">
        <div class="bx-section-head">
            <div>
                <h2 class="bx-h2"><?= $lang === 'bn' ? 'সম্প্রতি দেখা বই' : 'Recently Viewed' ?></h2>
                <p><?= $lang === 'bn' ? 'আপনার সাম্প্রতিক পরিদর্শিত বইসমূহ।' : 'PDFs you checked out recently.' ?></p>
            </div>
        </div>
        <div class="bx-book-grid" id="bx-guest-recent-grid"></div>
    </div>
</section>
<?php endif; ?>

<!-- Short About Bookexa Section with Animated Counters -->
<section class="bx-section bx-reveal">
    <div class="container">
        <div class="bx-card" style="display:flex;align-items:center;justify-content:space-between;gap:24px;flex-wrap:wrap;background:linear-gradient(120deg, var(--surface) 0%, var(--bg-alt) 100%);">
            <div style="max-width:600px;">
                <span class="bx-badge bx-badge-primary" style="margin-bottom:10px;"><?= icon('sparkles') ?> <?= e(site_setting('site_name', 'Bookexa')) ?></span>
                <h2 class="bx-h2" style="margin-bottom:8px;"><?= e(t('about_short_title')) ?></h2>
                <p class="bx-muted" style="margin:0 0 14px;"><?= e(t('about_short_desc')) ?></p>
                <div style="display:flex;gap:22px;flex-wrap:wrap;">
                    <div><strong style="font-size:1.35rem;font-family:var(--font-serif);color:var(--primary);" data-counter="<?= $totalFoldersStat ?>" data-counter-suffix="+"><?= e(num_localize($totalFoldersStat)) ?>+</strong> <small class="bx-muted"><?= e(t('folders_count')) ?></small></div>
                    <div><strong style="font-size:1.35rem;font-family:var(--font-serif);color:var(--teal-text);" data-counter="<?= $totalItemsStat ?>" data-counter-suffix="+"><?= e(num_localize($totalItemsStat)) ?>+</strong> <small class="bx-muted"><?= e(t('books_count')) ?></small></div>
                    <div><strong style="font-size:1.35rem;font-family:var(--font-serif);color:var(--gold-text);" data-counter="100" data-counter-suffix="%">100%</strong> <small class="bx-muted">Protected</small></div>
                </div>
            </div>
            <div style="display:flex;gap:10px;flex-wrap:wrap;">
                <a href="<?= e(url('/about')) ?>" class="bx-btn bx-btn-primary"><?= e(t('learn_more')) ?></a>
                <a href="<?= e(url('/contact')) ?>" class="bx-btn bx-btn-outline"><?= e(t('nav_contact')) ?></a>
            </div>
        </div>
    </div>
</section>
