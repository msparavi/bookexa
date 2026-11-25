<?php
declare(strict_types=1);

$shopItems = [
    [
        'slug'     => 'hardcover-bangla-notebook',
        'title_bn' => 'বুকএক্সা প্রিমিয়াম হার্ডকভার নোটবুক',
        'title_en' => 'Bookexa Premium Hardcover Notebook',
        'cat_bn'   => 'স্টেশনারি',
        'cat_en'   => 'Stationery',
        'price'    => 280,
        'cover'    => 'uploads/covers/cover-literature.svg',
    ],
    [
        'slug'     => 'ssc-printed-formula-book',
        'title_bn' => 'এসএসসি পদার্থবিজ্ঞান প্রিন্টেড হ্যান্ডবুক',
        'title_en' => 'SSC Physics Printed Handbook',
        'cat_bn'   => 'প্রিন্টেড বই',
        'cat_en'   => 'Printed Books',
        'price'    => 320,
        'cover'    => 'uploads/covers/cover-science.svg',
    ],
    [
        'slug'     => 'artist-sketching-pencil-kit',
        'title_bn' => 'আর্টিস্ট স্কেচিং পেন্সিল ও স্কেচবুক সেট',
        'title_en' => 'Artist Sketching Pencil & Sketchbook Set',
        'cat_bn'   => 'ড্রয়িং কিট',
        'cat_en'   => 'Drawing Kits',
        'price'    => 450,
        'cover'    => 'uploads/covers/cover-hsc.svg',
    ],
    [
        'slug'     => 'bcs-printed-question-bank',
        'title_bn' => 'বিসিএস প্রিলি প্রিন্টেড প্রশ্নব্যাংক',
        'title_en' => 'BCS Preliminary Printed Question Bank',
        'cat_bn'   => 'প্রিন্টেড বই',
        'cat_en'   => 'Printed Books',
        'price'    => 490,
        'cover'    => 'uploads/covers/cover-bcs.svg',
    ],
];
?>
<div class="container" style="padding-top:24px;">
    <nav class="bx-breadcrumb" aria-label="Breadcrumb">
        <a href="<?= e(url('/')) ?>"><?= e(t('nav_home')) ?></a>
        <?= icon('chevron-right') ?>
        <span class="current"><?= e(t('nav_shop')) ?></span>
    </nav>

    <div class="bx-section-head" style="margin-top:20px;">
        <div>
            <span class="bx-badge bx-badge-accent" style="margin-bottom:8px;"><?= icon('cart') ?> <?= e(t('shop_badge')) ?></span>
            <h1 class="bx-h2"><?= e(t('shop_title')) ?></h1>
            <p><?= e(t('shop_sub')) ?></p>
        </div>
    </div>

    <div class="bx-book-grid">
        <?php foreach ($shopItems as $p): ?>
            <?php $detailUrl = url('/shop/' . rawurlencode($p['slug'])); ?>
            <div class="bx-book-3d bx-reveal">
                <article class="bx-book-card">
                    <a href="<?= e($detailUrl) ?>" class="bx-book-cover">
                        <img src="<?= e(asset($p['cover'])) ?>" alt="<?= e(bi($p, 'title')) ?>" loading="lazy">
                        <span class="bx-book-ribbon"><?= e(bi($p, 'cat')) ?></span>
                    </a>
                    <div class="bx-book-info">
                        <a href="<?= e($detailUrl) ?>">
                            <h3 class="bx-book-title"><?= e(bi($p, 'title')) ?></h3>
                        </a>
                        <p class="bx-book-author"><?= e(t('shop_badge')) ?></p>
                        <div class="bx-book-footer">
                            <span class="bx-price"><?= e(format_bdt((float) $p['price'])) ?></span>
                            <a href="<?= e($detailUrl) ?>" class="bx-btn bx-btn-primary bx-btn-sm"><?= e(t('view_details')) ?></a>
                        </div>
                    </div>
                </article>
            </div>
        <?php endforeach; ?>
    </div>
</div>
