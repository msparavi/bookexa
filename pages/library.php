<?php
declare(strict_types=1);
/**
 * My Library with reading stats (pages read, books finished, time spent) and 24/page Load More.
 * @var array $user, array $libraryItems, array $filters, int $page, int $totalPages, int $totalItems, array $readingStats
 */
$lang = current_lang();
$minsTot = (int) floor(((int) ($readingStats['time_spent_sec'] ?? 0)) / 60);
$hrs = intdiv($minsTot, 60);
$mins = $minsTot % 60;
$timeFmt = $hrs > 0
    ? ($lang === 'bn' ? num_localize($hrs) . ' ঘঃ ' . num_localize($mins) . ' মিঃ' : "{$hrs}h {$mins}m")
    : ($lang === 'bn' ? num_localize($mins) . ' মিনিট' : "{$mins}m");
?>
<div class="container" style="padding-top:24px;">
    <nav class="bx-breadcrumb" aria-label="Breadcrumb">
        <a href="<?= e(url('/')) ?>"><?= e(t('nav_home')) ?></a>
        <?= icon('chevron-right') ?>
        <span class="current"><?= e(t('nav_library')) ?></span>
    </nav>

    <div class="bx-section-head" style="margin-top:18px;">
        <div>
            <h1 class="bx-h2"><?= e(t('nav_library')) ?></h1>
            <p><?= $lang === 'bn' ? 'আপনার কেনা এবং সকল ফ্রি পিডিএফ একসাথে এখানে পাবেন।' : 'All your purchased and free PDFs in one place.' ?></p>
        </div>
        <a href="<?= e(url('/orders')) ?>" class="bx-btn bx-btn-outline bx-btn-sm"><?= icon('cart') ?> <?= $lang === 'bn' ? 'আমার অর্ডার' : 'My Orders' ?></a>
    </div>

    <!-- Reading Stats Summary -->
    <div class="bx-stats-grid" aria-label="Reading Statistics">
        <div class="bx-stat-tile">
            <small><?= $lang === 'bn' ? 'মোট পঠিত পৃষ্ঠা' : 'Pages Read' ?></small>
            <strong><?= e(num_localize((int) ($readingStats['pages_read'] ?? 0))) ?></strong>
        </div>
        <div class="bx-stat-tile">
            <small><?= $lang === 'bn' ? 'সমাপ্ত বই' : 'Books Finished' ?></small>
            <strong style="color:var(--teal-text);"><?= e(num_localize((int) ($readingStats['books_finished'] ?? 0))) ?></strong>
        </div>
        <div class="bx-stat-tile">
            <small><?= $lang === 'bn' ? 'পড়ার সময়' : 'Time Spent' ?></small>
            <strong style="color:var(--gold-text);"><?= e($timeFmt) ?></strong>
        </div>
    </div>

    <?php if (empty($libraryItems)): ?>
        <div class="bx-empty-state">
            <?= illustration_svg('empty') ?>
            <h2 class="bx-h2" style="margin-bottom:8px;"><?= e(t('empty_folder_title')) ?></h2>
            <p class="bx-muted" style="margin:0 0 18px;"><?= $lang === 'bn' ? 'আপনার লাইব্রেরিতে এখনো কোনো বই নেই।' : 'Your library has no items yet.' ?></p>
            <a href="<?= e(url('/pdf')) ?>" class="bx-btn bx-btn-primary bx-btn-sm"><?= e(t('portal_pdf_cta')) ?></a>
        </div>
    <?php else: ?>
        <div class="bx-book-grid" id="bx-library-grid">
            <?php foreach ($libraryItems as $item): ?>
                <?php
                $isFree = (bool) $item['is_free'] || (float) $item['price'] <= 0;
                $cover = !empty($item['cover']) ? asset($item['cover']) : asset('uploads/covers/cover-bangla.svg');
                $readUrl = url('/reader/' . rawurlencode($item['slug']));
                $lastPage = (int) ($item['last_page'] ?? 0);
                $totPages = (int) ($item['prog_total_pages'] ?? $item['total_pages'] ?? 0);
                ?>
                <div class="bx-book-3d bx-reveal">
                    <article class="bx-book-card">
                        <a href="<?= e($readUrl) ?>" class="bx-book-cover">
                            <img src="<?= e($cover) ?>" alt="<?= e(bi($item, 'title')) ?>" loading="lazy">
                            <span class="bx-book-ribbon <?= $isFree ? 'free' : '' ?>">
                                <?= $isFree ? e(t('free_badge')) : ($lang === 'bn' ? 'আনলকড' : 'Unlocked') ?>
                            </span>
                        </a>
                        <div class="bx-book-info">
                            <a href="<?= e($readUrl) ?>" class="bx-book-title-link"><h2 class="bx-book-title"><?= e(bi($item, 'title')) ?></h2></a>
                            <?php if ($lastPage > 0): ?>
                                <p class="bx-book-author"><?= $lang === 'bn' ? 'সর্বশেষ পড়া: পৃষ্ঠা ' . num_localize($lastPage) . ($totPages ? '/' . num_localize($totPages) : '') : 'Progress: Page ' . $lastPage . ($totPages ? '/' . $totPages : '') ?></p>
                            <?php else: ?>
                                <p class="bx-book-author"><?= e(bi($item, 'author')) ?></p>
                            <?php endif; ?>
                            <div class="bx-book-footer">
                                <a class="bx-btn bx-btn-primary bx-btn-sm" style="width:100%;" href="<?= e($readUrl) ?>">
                                    <?= icon('book') ?> <?= e(t('read_now_btn')) ?>
                                </a>
                            </div>
                        </div>
                    </article>
                </div>
            <?php endforeach; ?>
        </div>
        <?= render_browse_pagination('/library', $filters, $page, $totalPages, $totalItems, 'bx-library-grid') ?>
    <?php endif; ?>
</div>
