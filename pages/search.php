<?php
declare(strict_types=1);
/** @var string $q, array $matchedFolders, array $matchedItems */
?>
<div class="container" style="padding-top:24px;">
    <nav class="bx-breadcrumb" aria-label="Breadcrumb">
        <a href="<?= e(url('/')) ?>"><?= e(t('nav_home')) ?></a>
        <?= icon('chevron-right') ?>
        <span class="current"><?= e(t('search_title')) ?></span>
    </nav>

    <div class="bx-card" style="margin:20px 0 28px;">
        <h1 class="bx-h2" style="margin-bottom:14px;"><?= e(t('search_title')) ?></h1>
        <form method="get" action="<?= e(url('/search')) ?>" style="display:flex;gap:10px;flex-wrap:wrap;">
            <input class="bx-input" style="flex:1 1 200px;min-width:0;" type="search" name="q" value="<?= e($q) ?>" placeholder="<?= e(t('search_placeholder')) ?>">
            <button type="submit" class="bx-btn bx-btn-primary"><?= icon('search') ?> <?= e(t('nav_search')) ?></button>
        </form>
    </div>

    <?php if (empty($matchedFolders) && empty($matchedItems)): ?>
        <div class="bx-empty-state">
            <?= illustration_svg('empty') ?>
            <h2 class="bx-h2" style="margin-bottom:8px;"><?= e(t('empty_folder_title')) ?></h2>
            <p class="bx-muted" style="margin:0 0 18px;"><?= e(t('search_empty')) ?></p>
            <a href="<?= e(url('/pdf')) ?>" class="bx-btn bx-btn-primary bx-btn-sm"><?= e(t('back_to_all_folders')) ?></a>
        </div>
    <?php else: ?>
        <?php if (!empty($matchedFolders)): ?>
            <h2 class="bx-h2" style="font-size:1.25rem;margin-bottom:16px;"><?= e(t('featured_folders')) ?> (<?= e(num_localize(count($matchedFolders))) ?>)</h2>
            <div class="bx-folder-grid" style="margin-bottom:34px;">
                <?php foreach ($matchedFolders as $mf): ?>
                    <?= render_folder_card($mf, folder_full_url((int) $mf['id'])) ?>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <?php if (!empty($matchedItems)): ?>
            <h2 class="bx-h2" style="font-size:1.25rem;margin-bottom:16px;"><?= e(t('newest_pdfs')) ?> (<?= e(num_localize(count($matchedItems))) ?>)</h2>
            <div class="bx-book-grid">
                <?php foreach ($matchedItems as $mi): ?>
                    <?= render_book_card($mi) ?>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    <?php endif; ?>
</div>
