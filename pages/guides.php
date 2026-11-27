<?php
declare(strict_types=1);
/** @var array $guides, array $categories, array $allTags, ?string $activeCategory, ?string $activeTag, array $globalFaqs, int $page, int $totalPages, int $totalItems */
$lang = current_lang();
$listBase = '/guides';
if (!empty($activeCategory)) {
    $listBase = '/guides/' . rawurlencode((string) $activeCategory);
} elseif (!empty($activeTag)) {
    $listBase = '/guides/tag/' . rawurlencode((string) $activeTag);
}
?>
<div class="container" style="padding-top:20px;padding-bottom:44px;">
    <nav class="bx-breadcrumb" aria-label="Breadcrumb">
        <a href="<?= e(url('/')) ?>"><?= e(t('nav_home')) ?></a>
        <?= icon('chevron-right') ?>
        <?php if (!empty($activeCategory) || !empty($activeTag)): ?>
            <a href="<?= e(url('/guides')) ?>"><?= e(t('nav_guides')) ?></a>
            <?= icon('chevron-right') ?>
            <span class="current"><?= e((string) ($headingLabel ?? $activeCategory ?? $activeTag)) ?></span>
        <?php else: ?>
            <span class="current"><?= e(t('nav_guides')) ?></span>
        <?php endif; ?>
    </nav>

    <div class="bx-page-hero is-guides" style="margin-top:16px;">
        <div class="bx-page-hero-copy">
            <span class="bx-badge bx-badge-accent" style="margin-bottom:8px;"><?= icon('book') ?> <?= $lang === 'bn' ? 'এডিটোরিয়াল গাইড' : 'Editorial Study Guides' ?></span>
            <h1 class="bx-display" style="font-size:clamp(1.5rem,3vw,2.1rem);margin:0 0 8px;">
                <?= e((string) ($headingLabel ?? ($lang === 'bn' ? 'স্টাডি গাইড ও পরীক্ষার প্রস্তুতি' : 'Study Guides & Exam Preparation'))) ?>
            </h1>
            <p style="margin:0;opacity:0.92;"><?= e($lang === 'bn' ? 'বিসিএস, এসএসসি, এইচএসসি, মেডিকেল ও বিশ্ববিদ্যালয় ভর্তি পরীক্ষার সিলেবাস, কৌশল ও নির্ভরযোগ্য বইয়ের পরামর্শ।' : 'Syllabus breakdowns, tested exam strategies, and curated reading roadmaps.') ?></p>
        </div>
        <div class="bx-page-hero-art">
            <?= illustration_svg('guides') ?>
            <a href="<?= e(url('/feed.xml')) ?>" class="bx-btn bx-btn-hero-ghost bx-btn-sm">RSS Feed</a>
        </div>
    </div>

    <?php if (!empty($categories)): ?>
        <div class="bx-guides-cat-sticky">
            <div class="bx-shop-cat-pills bx-cat-scroll" role="navigation" aria-label="Guide Categories">
                <a href="<?= e(url('/guides')) ?>" class="bx-cat-pill <?= empty($activeCategory) && empty($activeTag) ? 'active' : '' ?>">
                    <?= $lang === 'bn' ? 'সকল গাইড' : 'All Guides' ?>
                </a>
                <?php foreach ($categories as $cat): ?>
                    <a href="<?= e(url('/guides/' . rawurlencode((string) $cat['slug']))) ?>" class="bx-cat-pill <?= ($activeCategory ?? '') === $cat['slug'] ? 'active' : '' ?>">
                        <?= e($lang === 'bn' ? ($cat['bn'] ?: $cat['en']) : ($cat['en'] ?: $cat['bn'])) ?>
                    </a>
                <?php endforeach; ?>
            </div>
        </div>
    <?php endif; ?>

    <?php if (!empty($allTags)): ?>
        <div class="bx-guides-tags-bar">
            <div class="bx-chip-scroll-row" id="bx-guide-tags-row">
                <?php foreach (array_slice($allTags, 0, 16) as $tg): ?>
                    <a href="<?= e(url('/guides/tag/' . rawurlencode((string) $tg))) ?>"
                       class="bx-chip <?= ($activeTag ?? '') === $tg ? 'is-active' : '' ?>">
                        #<?= e($tg) ?>
                    </a>
                <?php endforeach; ?>
            </div>
            <?php if (count($allTags) > 4): ?>
                <button type="button"
                        class="bx-btn bx-btn-outline bx-btn-sm"
                        data-chip-more-toggle="#bx-guide-tags-row"
                        aria-expanded="false"
                        data-label-more="<?= e($lang === 'bn' ? 'আরও ট্যাগ' : 'More') ?>"
                        data-label-less="<?= e($lang === 'bn' ? 'সংক্ষিপ্ত করুন' : 'Less') ?>">
                    <?= $lang === 'bn' ? 'আরও ট্যাগ' : 'More' ?>
                </button>
            <?php endif; ?>
        </div>
    <?php endif; ?>

    <?php if (empty($guides)): ?>
        <div class="bx-empty-state">
            <?= illustration_svg('empty') ?>
            <h2 class="bx-h2" style="font-size:1.3rem;margin-bottom:6px;"><?= $lang === 'bn' ? 'এই বিভাগে কোনো গাইড পাওয়া যায়নি' : 'No Guides Found' ?></h2>
            <p class="bx-muted" style="margin:0 0 18px;"><?= $lang === 'bn' ? 'সকল স্টাডি গাইড বা পিডিএফ সংগ্রহ দেখুন।' : 'Browse all study guides or return to the PDF library.' ?></p>
            <a href="<?= e(url('/guides')) ?>" class="bx-btn bx-btn-primary bx-btn-sm"><?= e(t('nav_guides')) ?></a>
        </div>
    <?php else: ?>
        <div class="bx-guides-grid" data-stagger>
            <?php foreach ($guides as $g): ?>
                <?php
                $gUrl = url('/guides/' . rawurlencode((string) $g['slug']));
                $catLbl = $lang === 'bn' ? (string) ($g['category_bn'] ?: $g['category_en'] ?? '') : (string) ($g['category_en'] ?: $g['category_bn'] ?? '');
                $mins = max(1, (int) ($g['reading_minutes'] ?? 3));
                $excerpt = bi($g, 'summary') !== '' ? bi($g, 'summary') : content_excerpt(bi($g, 'body'), 140);
                ?>
                <article class="bx-card bx-guide-card" style="display:flex;flex-direction:column;overflow:hidden;">
                    <?php if (!empty($g['cover'])): ?>
                        <a href="<?= e($gUrl) ?>" style="display:block;aspect-ratio:16/9;overflow:hidden;">
                            <img src="<?= e(asset((string) $g['cover'])) ?>" alt="<?= e(bi($g, 'title')) ?>" width="480" height="270" loading="lazy" style="width:100%;height:100%;object-fit:cover;">
                        </a>
                    <?php endif; ?>
                    <div style="padding:18px;display:flex;flex-direction:column;gap:8px;flex:1;">
                        <div style="display:flex;gap:8px;flex-wrap:wrap;align-items:center;font-size:0.78rem;">
                            <?php if ($catLbl !== '' && !empty($g['category_slug'])): ?>
                                <a href="<?= e(url('/guides/' . rawurlencode((string) $g['category_slug']))) ?>" class="bx-badge bx-badge-teal" style="text-decoration:none;"><?= e($catLbl) ?></a>
                            <?php endif; ?>
                            <span class="bx-muted"><?= e(num_localize($mins)) ?> <?= $lang === 'bn' ? 'মিনিট পড়া' : 'min read' ?></span>
                        </div>
                        <h2 style="margin:0;font-size:1.1rem;line-height:1.4;font-family:var(--font-serif);">
                            <a href="<?= e($gUrl) ?>" style="color:var(--text);text-decoration:none;"><?= e(bi($g, 'title')) ?></a>
                        </h2>
                        <?php if ($excerpt !== ''): ?>
                            <p class="bx-muted" style="margin:0;font-size:0.92rem;line-height:1.6;"><?= e($excerpt) ?></p>
                        <?php endif; ?>
                        <div style="margin-top:auto;padding-top:10px;display:flex;justify-content:space-between;align-items:center;font-size:0.82rem;border-top:1px solid var(--border);">
                            <span class="bx-muted"><?= e(num_localize(date('d M Y', strtotime((string) ($g['updated_at'] ?? $g['created_at'] ?? 'now')) ?: time()))) ?></span>
                            <a href="<?= e($gUrl) ?>" class="bx-guide-read-link" style="font-weight:700;color:var(--primary);text-decoration:none;"><?= $lang === 'bn' ? 'বিস্তারিত পড়ুন →' : 'Read Guide →' ?></a>
                        </div>
                    </div>
                </article>
            <?php endforeach; ?>
        </div>

        <?php if ($totalPages > 1): ?>
            <nav class="bx-pagination-links" aria-label="Guides Pagination" style="margin-top:28px;">
                <?php if ($page > 1): ?>
                    <a href="<?= e(url($listBase . ($page - 1 > 1 ? '?page=' . ($page - 1) : ''))) ?>" class="bx-btn bx-btn-ghost bx-btn-sm">« <?= $lang === 'bn' ? 'আগের' : 'Prev' ?></a>
                <?php endif; ?>
                <?php for ($p = max(1, $page - 2); $p <= min($totalPages, $page + 2); $p++): ?>
                    <a href="<?= e(url($listBase . ($p > 1 ? '?page=' . $p : ''))) ?>" class="bx-btn bx-btn-sm <?= $p === $page ? 'bx-btn-primary' : 'bx-btn-outline' ?>"><?= e(num_localize($p)) ?></a>
                <?php endfor; ?>
                <?php if ($page < $totalPages): ?>
                    <a href="<?= e(url($listBase . '?page=' . ($page + 1))) ?>" class="bx-btn bx-btn-ghost bx-btn-sm"><?= $lang === 'bn' ? 'পরের' : 'Next' ?> »</a>
                <?php endif; ?>
            </nav>
        <?php endif; ?>
    <?php endif; ?>

    <?php if (!empty($globalFaqs)): ?>
        <section style="margin-top:42px;">
            <h2 class="bx-h2" style="margin-bottom:14px;"><?= $lang === 'bn' ? 'সাধারণ জিজ্ঞাসা ও উত্তর (FAQ)' : 'Frequently Asked Study Questions' ?></h2>
            <?php foreach ($globalFaqs as $fq): ?>
                <details class="bx-faq-item" open>
                    <summary><?= e($lang === 'bn' ? $fq['question_bn'] : $fq['question_en']) ?></summary>
                    <p>
                        <?= e($lang === 'bn' ? $fq['answer_bn'] : $fq['answer_en']) ?>
                        <?php if (!empty($fq['link_url'])): ?>
                            <a href="<?= e(url((string) $fq['link_url'])) ?>" style="display:inline-block;margin-left:6px;font-weight:700;color:var(--primary);"><?= $lang === 'bn' ? 'বিস্তারিত →' : 'Learn more →' ?></a>
                        <?php endif; ?>
                    </p>
                </details>
            <?php endforeach; ?>
        </section>
    <?php endif; ?>
</div>
