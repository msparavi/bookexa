<?php
declare(strict_types=1);
/**
 * @var array $wishlistItems, array $filters, int $page, int $totalPages, int $totalItems
 */
$lang = current_lang();
?>
<div class="container" style="padding-top:24px;">
    <nav class="bx-breadcrumb" aria-label="Breadcrumb">
        <a href="<?= e(url('/')) ?>"><?= e(t('nav_home')) ?></a>
        <?= icon('chevron-right') ?>
        <span class="current"><?= e(t('nav_wishlist')) ?></span>
    </nav>

    <div class="bx-section-head" style="margin-top:20px;">
        <div>
            <h1 class="bx-h2"><?= e(t('nav_wishlist')) ?> (<?= e(num_localize($totalItems)) ?>)</h1>
            <p><?= $lang === 'bn' ? 'আপনার সংরক্ষিত পছন্দের পিডিএফ বইসমূহ।' : 'PDFs saved to your personal wishlist.' ?></p>
        </div>
        <a href="<?= e(url('/pdf')) ?>" class="bx-btn bx-btn-outline"><?= icon('folder') ?> <?= e(t('portal_pdf_cta')) ?></a>
    </div>

    <?php if (empty($wishlistItems)): ?>
        <div class="bx-empty-state">
            <?= illustration_svg('empty') ?>
            <h2 class="bx-h2" style="margin-bottom:8px;"><?= $lang === 'bn' ? 'পছন্দের তালিকা এখনো খালি' : 'Your Wishlist Is Empty' ?></h2>
            <p class="bx-muted" style="margin:0 0 18px;"><?= $lang === 'bn' ? 'যেকোনো বইয়ের হৃদয় আইকনে চাপ দিয়ে সংরক্ষণ করুন।' : 'Tap the heart button on any PDF card to save it here.' ?></p>
            <a href="<?= e(url('/pdf')) ?>" class="bx-btn bx-btn-primary"><?= e(t('portal_pdf_cta')) ?></a>
        </div>
    <?php else: ?>
        <div class="bx-book-grid" id="bx-wishlist-grid">
            <?php foreach ($wishlistItems as $item): ?>
                <?= render_book_card($item) ?>
            <?php endforeach; ?>
        </div>
        <?= render_browse_pagination('/wishlist', $filters, $page, $totalPages, $totalItems, 'bx-wishlist-grid') ?>
    <?php endif; ?>
</div>
