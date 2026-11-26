<?php
declare(strict_types=1);
/**
 * Upgraded Search Page: FULLTEXT + LIKE fallback, match highlighting, "did you mean",
 * sort/filter toolbar, and 24/page Load More pagination.
 * @var string $q, array $filters, array $authors, array $matchedFolders, array $matchedItems
 * @var int $page, int $totalPages, int $totalItems, ?string $didYouMean
 */
$lang = current_lang();
?>
<div class="container" style="padding-top:24px;">
    <nav class="bx-breadcrumb" aria-label="Breadcrumb">
        <a href="<?= e(url('/')) ?>"><?= e(t('nav_home')) ?></a>
        <?= icon('chevron-right') ?>
        <span class="current"><?= e(t('search_title')) ?></span>
    </nav>

    <div style="margin:20px 0 10px;">
        <h1 class="bx-h2" style="margin-bottom:14px;"><?= e(t('search_title')) ?></h1>
        <?= render_filter_sort_bar(url('/search'), $filters, $authors) ?>
    </div>

    <?php if (empty($matchedFolders) && empty($matchedItems)): ?>
        <div class="bx-empty-state">
            <?= illustration_svg('empty') ?>
            <h2 class="bx-h2" style="margin-bottom:8px;"><?= e(t('empty_folder_title')) ?></h2>
            <p class="bx-muted" style="margin:0 0 14px;"><?= e(t('search_empty')) ?></p>
            <?php if (!empty($didYouMean)): ?>
                <p style="margin:0 0 18px;font-size:0.98rem;">
                    <?= $lang === 'bn' ? 'আপনি কি খুঁজছিলেন:' : 'Did you mean:' ?>
                    <a href="<?= e(url('/search?q=' . rawurlencode($didYouMean))) ?>" style="font-weight:700;color:var(--primary);text-decoration:underline;">
                        <?= e($didYouMean) ?>
                    </a>?
                </p>
            <?php endif; ?>
            <a href="<?= e(url('/pdf')) ?>" class="bx-btn bx-btn-primary bx-btn-sm"><?= e(t('back_to_all_folders')) ?></a>
        </div>
    <?php else: ?>
        <?php if (!empty($matchedFolders)): ?>
            <h2 class="bx-h2" style="font-size:1.25rem;margin-bottom:16px;"><?= e(t('featured_folders')) ?> (<?= e(num_localize(count($matchedFolders))) ?>)</h2>
            <div class="bx-folder-grid" style="margin-bottom:34px;">
                <?php foreach ($matchedFolders as $mf): ?>
                    <?= render_folder_card($mf, folder_full_url((int) $mf['id']), ['highlightQuery' => $q]) ?>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <?php if (!empty($matchedItems)): ?>
            <h2 class="bx-h2" style="font-size:1.25rem;margin-bottom:16px;">
                <?= $lang === 'bn' ? 'বইসমূহ' : 'PDFs' ?> (<?= e(num_localize($totalItems)) ?>)
            </h2>
            <div class="bx-book-grid" id="bx-search-books-grid">
                <?php foreach ($matchedItems as $mi): ?>
                    <?= render_book_card($mi, ['highlightQuery' => $q]) ?>
                <?php endforeach; ?>
            </div>
            <?= render_browse_pagination('/search', $filters, $page, $totalPages, $totalItems, 'bx-search-books-grid') ?>
        <?php endif; ?>
    <?php endif; ?>
</div>
