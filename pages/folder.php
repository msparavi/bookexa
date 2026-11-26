<?php
declare(strict_types=1);

/**
 * Folder browsing page with sort, filters, and 24/page Load More pagination.
 * @var ?array $currentFolder, array $breadcrumbs, array $subfolders, array $items
 * @var string $slugPrefix, array $filters, array $authors, int $page, int $totalPages, int $totalItems
 */
$lang = current_lang();
$basePath = $currentFolder ? ('/pdf/' . $slugPrefix) : '/pdf';
$actionUrl = url($basePath);
?>
<div class="container" style="padding-top:20px;">
    <nav class="bx-breadcrumb" aria-label="Breadcrumb">
        <a href="<?= e(url('/')) ?>" data-breadcrumb-back><?= e(t('nav_home')) ?></a>
        <?= icon('chevron-right') ?>
        <?php if ($currentFolder === null): ?>
            <span class="current"><?= e(t('nav_pdfs')) ?></span>
        <?php else: ?>
            <a href="<?= e(url('/pdf')) ?>" data-breadcrumb-back><?= e(t('nav_pdfs')) ?></a>
            <?php
            $accum = [];
            $lastIdx = count($breadcrumbs) - 1;
            foreach ($breadcrumbs as $idx => $crumb):
                $accum[] = rawurlencode($crumb['slug']);
            ?>
                <?= icon('chevron-right') ?>
                <?php if ($idx === $lastIdx): ?>
                    <span class="current"><?= e(bi($crumb, 'name')) ?></span>
                <?php else: ?>
                    <a href="<?= e(url('/pdf/' . implode('/', $accum))) ?>" data-breadcrumb-back><?= e(bi($crumb, 'name')) ?></a>
                <?php endif; ?>
            <?php endforeach; ?>
        <?php endif; ?>
    </nav>

    <div class="bx-section-head" style="margin-top:20px;">
        <div>
            <h1 class="bx-h2"><?= e($currentFolder ? bi($currentFolder, 'name') : t('browse_folders_title')) ?></h1>
            <p><?= e($currentFolder ? bi($currentFolder, 'description', t('featured_folders_sub')) : t('featured_folders_sub')) ?></p>
        </div>
    </div>

    <?php if ($currentFolder === null): ?>
        <form method="get" action="<?= e(url('/pdf')) ?>" style="display:flex;gap:8px;flex-wrap:wrap;margin-bottom:22px;max-width:460px;">
            <input class="bx-input" style="flex:1 1 200px;min-width:0;" type="search" name="q" value="<?= e($filters['q'] ?? '') ?>" placeholder="<?= e(t('filter_placeholder')) ?>" data-suggest-input autocomplete="off">
            <button type="submit" class="bx-btn bx-btn-primary bx-btn-sm"><?= icon('search') ?> <?= e(t('nav_search')) ?></button>
        </form>
    <?php else: ?>
        <?= render_filter_sort_bar($actionUrl, $filters, $authors) ?>
    <?php endif; ?>

    <!-- Smooth Loading Skeleton (revealed on folder navigation click) -->
    <div id="bx-folder-skeleton" class="bx-folder-grid" style="display:none;margin-bottom:32px;" aria-hidden="true">
        <?php for ($s = 0; $s < 4; $s++): ?>
            <div class="bx-card" style="padding:14px;">
                <div class="bx-skeleton" style="height:140px;margin-bottom:12px;"></div>
                <div class="bx-skeleton" style="height:18px;width:70%;margin-bottom:8px;"></div>
                <div class="bx-skeleton" style="height:14px;width:45%;"></div>
            </div>
        <?php endfor; ?>
    </div>

    <div id="bx-folder-content">
        <?php if (!empty($subfolders)): ?>
            <?php if ($currentFolder !== null): ?>
                <h2 class="bx-h2" style="font-size:1.25rem;margin-bottom:16px;"><?= e(t('subfolders')) ?></h2>
            <?php endif; ?>
            <div class="bx-folder-grid" data-stagger style="margin-bottom:36px;">
                <?php foreach ($subfolders as $sf): ?>
                    <?php
                    $href = $slugPrefix !== ''
                        ? url('/pdf/' . $slugPrefix . '/' . rawurlencode($sf['slug']))
                        : url('/pdf/' . rawurlencode($sf['slug']));
                    ?>
                    <?= render_folder_card($sf, $href) ?>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <?php if (!empty($items)): ?>
            <h2 class="bx-h2" style="font-size:1.25rem;margin-bottom:16px;">
                <?= e(t('pdfs_in_folder')) ?> (<?= e(num_localize($totalItems)) ?>)
            </h2>
            <div class="bx-book-grid" id="bx-folder-books-grid" data-stagger>
                <?php foreach ($items as $it): ?>
                    <?= render_book_card($it, ['highlightQuery' => (string) ($filters['q'] ?? '')]) ?>
                <?php endforeach; ?>
            </div>
            <?= render_browse_pagination($basePath, $filters, $page, $totalPages, $totalItems, 'bx-folder-books-grid') ?>
        <?php elseif (empty($subfolders)): ?>
            <div class="bx-empty-state">
                <?= illustration_svg('empty') ?>
                <h2 class="bx-h2" style="font-size:1.35rem;margin-bottom:6px;"><?= e(t('empty_folder_title')) ?></h2>
                <p class="bx-muted" style="margin:0 0 20px;"><?= e(t('empty_folder_desc')) ?></p>
                <a href="<?= e(url('/pdf')) ?>" class="bx-btn bx-btn-primary bx-btn-sm"><?= e(t('back_to_all_folders')) ?></a>
            </div>
        <?php endif; ?>
    </div>
</div>
