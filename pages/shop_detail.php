<?php
declare(strict_types=1);
/** @var string $shopSlug */
$catalog = [
    'hardcover-bangla-notebook' => ['title_bn' => 'বুকএক্সা প্রিমিয়াম হার্ডকভার নোটবুক', 'title_en' => 'Bookexa Premium Hardcover Notebook', 'price' => 280, 'cover' => 'uploads/covers/cover-literature.svg'],
    'ssc-printed-formula-book' => ['title_bn' => 'এসএসসি পদার্থবিজ্ঞান প্রিন্টেড হ্যান্ডবুক', 'title_en' => 'SSC Physics Printed Handbook', 'price' => 320, 'cover' => 'uploads/covers/cover-science.svg'],
    'artist-sketching-pencil-kit' => ['title_bn' => 'আর্টিস্ট স্কেচিং পেন্সিল ও স্কেচবুক সেট', 'title_en' => 'Artist Sketching Pencil & Sketchbook Set', 'price' => 450, 'cover' => 'uploads/covers/cover-hsc.svg'],
    'bcs-printed-question-bank' => ['title_bn' => 'বিসিএস প্রিলি প্রিন্টেড প্রশ্নব্যাংক', 'title_en' => 'BCS Preliminary Printed Question Bank', 'price' => 490, 'cover' => 'uploads/covers/cover-bcs.svg'],
];
$product = $catalog[$shopSlug] ?? reset($catalog);
?>
<div class="container" style="padding-top:20px;">
    <nav class="bx-breadcrumb" aria-label="Breadcrumb">
        <a href="<?= e(url('/')) ?>"><?= e(t('nav_home')) ?></a>
        <?= icon('chevron-right') ?>
        <a href="<?= e(url('/shop')) ?>"><?= e(t('nav_shop')) ?></a>
        <?= icon('chevron-right') ?>
        <span class="current"><?= e(bi($product, 'title')) ?></span>
    </nav>

    <div class="bx-detail-grid">
        <div class="bx-detail-stage">
            <div class="bx-detail-book-3d">
                <img src="<?= e(asset($product['cover'])) ?>" alt="<?= e(bi($product, 'title')) ?>" width="400" height="600" decoding="async">
            </div>
        </div>
        <div class="bx-card">
            <span class="bx-badge bx-badge-accent" style="margin-bottom:10px;"><?= e(t('shop_badge')) ?></span>
            <h1 class="bx-display" style="font-size:1.8rem;margin-bottom:10px;"><?= e(bi($product, 'title')) ?></h1>
            <div class="bx-price" style="font-size:1.4rem;margin-bottom:14px;"><?= e(format_bdt((float) $product['price'])) ?></div>
            <p class="bx-muted" style="margin:0 0 20px;"><?= e(t('shop_sub')) ?></p>
            <button type="button" class="bx-btn bx-btn-accent" data-toast-trigger="<?= e(current_lang() === 'bn' ? 'শপ অর্ডার সিস্টেম শীঘ্রই যুক্ত হবে!' : 'Shop ordering will be enabled soon!') ?>">
                <?= icon('cart') ?> <?= e(t('shop_badge')) ?>
            </button>
        </div>
    </div>
</div>
