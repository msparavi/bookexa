<?php
declare(strict_types=1);
/** @var array $author, array $guides, array $items, array $products */
$lang = current_lang();
?>
<div class="container" style="padding-top:20px;padding-bottom:44px;">
    <nav class="bx-breadcrumb" aria-label="Breadcrumb">
        <a href="<?= e(url('/')) ?>"><?= e(t('nav_home')) ?></a>
        <?= icon('chevron-right') ?>
        <a href="<?= e(url('/authors')) ?>"><?= e(t('nav_authors')) ?></a>
        <?= icon('chevron-right') ?>
        <span class="current"><?= e(bi($author, 'name')) ?></span>
    </nav>

    <section class="bx-card" style="padding:24px;margin:16px 0 28px;display:flex;align-items:flex-start;gap:20px;flex-wrap:wrap;">
        <?php if (!empty($author['photo'])): ?>
            <img src="<?= e(asset((string) $author['photo'])) ?>" alt="<?= e(bi($author, 'name')) ?>" width="96" height="96" style="width:96px;height:96px;border-radius:50%;object-fit:cover;flex-shrink:0;">
        <?php else: ?>
            <span style="width:92px;height:92px;border-radius:50%;background:var(--primary);color:#fff;display:inline-flex;align-items:center;justify-content:center;font-weight:800;font-size:2rem;flex-shrink:0;">
                <?= e(mb_substr(bi($author, 'name'), 0, 1)) ?>
            </span>
        <?php endif; ?>
        <div style="flex:1 1 280px;">
            <div style="display:flex;gap:10px;align-items:center;flex-wrap:wrap;margin-bottom:6px;">
                <h1 class="bx-display" style="font-size:clamp(1.4rem,2.8vw,2rem);margin:0;"><?= e(bi($author, 'name')) ?></h1>
                <?php if (bi($author, 'expertise') !== ''): ?>
                    <span class="bx-badge bx-badge-teal"><?= e(bi($author, 'expertise')) ?></span>
                <?php endif; ?>
            </div>
            <?php if (bi($author, 'bio') !== ''): ?>
                <p class="bx-muted" style="margin:0 0 12px;line-height:1.7;max-width:72ch;"><?= e(bi($author, 'bio')) ?></p>
            <?php else: ?>
                <p class="bx-muted" style="margin:0 0 12px;">
                    <?= $lang === 'bn' ? 'এই লেখক ও গবেষকের প্রকাশিত সকল স্টাডি গাইড, ডিজিটাল পিডিএফ ও প্রিন্টেড বই।' : 'Published study guides, digital PDFs, and printed editions by this author.' ?>
                </p>
            <?php endif; ?>

            <div style="display:flex;gap:8px;flex-wrap:wrap;align-items:center;">
                <?php foreach (['Facebook' => $author['social_facebook'] ?? '', 'LinkedIn' => $author['social_linkedin'] ?? '', 'Website' => $author['social_website'] ?? ''] as $sn => $su): ?>
                    <?php if (trim((string) $su) !== ''): ?>
                        <a href="<?= e((string) $su) ?>" target="_blank" rel="noopener noreferrer" class="bx-btn bx-btn-outline bx-btn-sm"><?= e($sn) ?></a>
                    <?php endif; ?>
                <?php endforeach; ?>
                <a href="<?= e(url('/editorial-policy')) ?>" class="bx-btn bx-btn-ghost bx-btn-sm"><?= e(t('legal_editorial')) ?></a>
            </div>
        </div>
    </section>

    <?php if (!empty($guides)): ?>
        <h2 class="bx-h2" style="font-size:1.28rem;margin-bottom:16px;"><?= $lang === 'bn' ? 'প্রকাশিত স্টাডি গাইডসমূহ' : 'Published Study Guides' ?> (<?= e(num_localize(count($guides))) ?>)</h2>
        <div class="bx-folder-grid" style="margin-bottom:34px;">
            <?php foreach ($guides as $gd): ?>
                <a href="<?= e(url('/guides/' . rawurlencode((string) $gd['slug']))) ?>" class="bx-card" style="padding:16px;text-decoration:none;color:var(--text);">
                    <strong style="display:block;font-size:1.02rem;margin-bottom:6px;"><?= e(bi($gd, 'title')) ?></strong>
                    <span class="bx-muted" style="font-size:0.88rem;line-height:1.5;"><?= e(content_excerpt(bi($gd, 'summary'), 110)) ?></span>
                </a>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <?php if (!empty($items)): ?>
        <h2 class="bx-h2" style="font-size:1.25rem;margin-bottom:16px;"><?= e($lang === 'bn' ? 'ডিজিটাল পিডিএফ বই' : 'Digital PDF Editions') ?> (<?= e(num_localize(count($items))) ?>)</h2>
        <div class="bx-book-grid" data-stagger style="margin-bottom:34px;">
            <?php foreach ($items as $it): ?>
                <?= render_book_card($it) ?>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <?php if (!empty($products) && site_setting('shop_enabled', '0') === '1'): ?>
        <h2 class="bx-h2" style="font-size:1.25rem;margin-bottom:16px;"><?= e($lang === 'bn' ? 'প্রিন্টেড বই (শপ)' : 'Printed Books (Shop)') ?> (<?= e(num_localize(count($products))) ?>)</h2>
        <div class="bx-book-grid" data-stagger>
            <?php foreach ($products as $pr): ?>
                <?php
                $pUrl = url('/shop/' . rawurlencode((string) $pr['slug']));
                $pCov = !empty($pr['cover_image']) ? asset((string) $pr['cover_image']) : asset('uploads/covers/cover-bangla.svg');
                $eff = shop_product_effective_price($pr);
                ?>
                <article class="bx-book-card">
                    <a href="<?= e($pUrl) ?>" class="bx-book-cover-wrap">
                        <img src="<?= e($pCov) ?>" alt="<?= e(bi($pr, 'title')) ?>" width="300" height="400" loading="lazy">
                    </a>
                    <div class="bx-book-body">
                        <a href="<?= e($pUrl) ?>" class="bx-book-title"><?= e(bi($pr, 'title')) ?></a>
                        <div class="bx-book-foot">
                            <span class="bx-price"><?= e(format_bdt($eff)) ?></span>
                            <a href="<?= e($pUrl) ?>" class="bx-btn bx-btn-outline bx-btn-sm"><?= e(t('view_details')) ?></a>
                        </div>
                    </div>
                </article>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <?php if (empty($guides) && empty($items) && empty($products)): ?>
        <div class="bx-empty-state">
            <?= illustration_svg('empty') ?>
            <p class="bx-muted" style="margin:0 0 16px;"><?= $lang === 'bn' ? 'এই লেখকের কোনো সক্রিয় প্রকাশনা এখনো যুক্ত হয়নি।' : 'No active publications found for this author yet.' ?></p>
            <a href="<?= e(url('/guides')) ?>" class="bx-btn bx-btn-primary bx-btn-sm"><?= e(t('nav_guides')) ?></a>
        </div>
    <?php endif; ?>
</div>
