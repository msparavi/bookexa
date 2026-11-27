<?php
declare(strict_types=1);
/**
 * @var array $categories, array $products, array $filters, int $page, int $totalPages, int $totalItems, array $wishProductMap
 */
$lang = current_lang();
$selectedCat = (int) ($filters['category_id'] ?? 0);
$q = (string) ($filters['q'] ?? '');
$sort = (string) ($filters['sort'] ?? 'newest');
$inStock = !empty($filters['in_stock']);
$delCfg = shop_delivery_config();

$buildShopUrl = static function (array $ov = []) use ($selectedCat, $q, $sort, $inStock, $page): string {
    $params = [
        'cat'      => $ov['cat'] ?? ($selectedCat > 0 ? (string) $selectedCat : ''),
        'q'        => $ov['q'] ?? $q,
        'sort'     => $ov['sort'] ?? $sort,
        'in_stock' => $ov['in_stock'] ?? ($inStock ? '1' : ''),
        'page'     => $ov['page'] ?? ($page > 1 ? (string) $page : ''),
    ];
    $clean = [];
    foreach ($params as $k => $v) {
        if ($v !== '' && !($k === 'sort' && $v === 'newest') && !($k === 'page' && $v === '1')) {
            $clean[$k] = $v;
        }
    }
    return url('/shop' . ($clean ? ('?' . http_build_query($clean)) : ''));
};
?>
<div class="container" style="padding-top:20px;">
    <nav class="bx-breadcrumb" aria-label="Breadcrumb">
        <a href="<?= e(url('/')) ?>"><?= e(t('nav_home')) ?></a>
        <?= icon('chevron-right') ?>
        <a href="<?= e(url('/shop')) ?>"><?= e(t('nav_shop')) ?></a>
        <?php if (!empty($activeCategoryTrail)): foreach ($activeCategoryTrail as $tc): ?>
            <?= icon('chevron-right') ?>
            <a href="<?= e($buildShopUrl(['cat' => (string) $tc['id'], 'page' => '1'])) ?>"><?= e($lang === 'bn' ? $tc['name_bn'] : $tc['name_en']) ?></a>
        <?php endforeach; endif; ?>
    </nav>

    <div class="bx-page-hero is-shop" style="margin-top:16px;">
        <div class="bx-page-hero-copy">
            <span class="bx-badge bx-badge-gold" style="margin-bottom:8px;"><?= icon('truck') ?> <?= e(t('shop_badge')) ?></span>
            <h1 class="bx-display" style="font-size:clamp(1.5rem,3vw,2.15rem);margin:0 0 8px;"><?= e(t('shop_title')) ?></h1>
            <p style="margin:0 0 14px;opacity:0.92;"><?= e(t('shop_sub')) ?></p>
            <div style="display:flex;gap:10px;flex-wrap:wrap;align-items:center;">
                <a href="<?= e(url('/shop/track')) ?>" class="bx-btn bx-btn-hero-ghost bx-btn-sm">
                    <?= icon('package') ?> <?= e(t('nav_track_order')) ?>
                </a>
                <a href="<?= e(url('/cart')) ?>" class="bx-btn bx-btn-accent bx-btn-sm">
                    <?= icon('cart') ?> <?= e(t('nav_cart')) ?> (<span data-cart-badge><?= (int) shop_cart_count() ?></span>)
                </a>
            </div>
        </div>
        <div class="bx-page-hero-art">
            <?= illustration_svg('shop') ?>
        </div>
    </div>

    <?php if ($delCfg['free_delivery_above'] > 0): ?>
        <div class="bx-shop-promo-bar">
            <span><?= icon('truck') ?></span>
            <span>
                <?= $lang === 'bn'
                    ? 'সারা দেশে ডেলিভারি: ঢাকা ৳' . num_localize(number_format($delCfg['dhaka_fee'], 0)) . ' • ঢাকার বাইরে ৳' . num_localize(number_format($delCfg['outside_fee'], 0)) . ' • ৳' . num_localize(number_format($delCfg['free_delivery_above'], 0)) . '+ অর্ডারে ফ্রি ডেলিভারি!'
                    : 'Delivery across BD: Dhaka ৳' . number_format($delCfg['dhaka_fee'], 0) . ' • Outside Dhaka ৳' . number_format($delCfg['outside_fee'], 0) . ' • FREE delivery on ৳' . number_format($delCfg['free_delivery_above'], 0) . '+!' ?>
            </span>
        </div>
    <?php endif; ?>

    <?php if ($categories): ?>
        <div class="bx-shop-cat-pills" role="navigation" aria-label="<?= e($lang === 'bn' ? 'শপ ক্যাটাগরি' : 'Shop Categories') ?>">
            <a href="<?= e($buildShopUrl(['cat' => '', 'page' => '1'])) ?>" class="bx-cat-pill <?= $selectedCat === 0 ? 'active' : '' ?>">
                <?= $lang === 'bn' ? 'সব বই' : 'All Books' ?>
            </a>
            <?php foreach ($categories as $c): $cid = (int) $c['id']; ?>
                <a href="<?= e($buildShopUrl(['cat' => (string) $cid, 'page' => '1'])) ?>" class="bx-cat-pill <?= $selectedCat === $cid ? 'active' : '' ?>">
                    <?php if (!empty($c['thumbnail'])): ?>
                        <img src="<?= e(asset((string) $c['thumbnail'])) ?>" alt="" width="20" height="20" style="width:20px;height:20px;border-radius:999px;object-fit:cover;">
                    <?php endif; ?>
                    <span><?= e(str_repeat('› ', (int) $c['_depth']) . ($lang === 'bn' ? $c['name_bn'] : $c['name_en'])) ?></span>
                </a>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <!-- Search / Sort / Filter Bar -->
    <form class="bx-browse-bar" method="get" action="<?= e(url('/shop')) ?>">
        <?php if ($selectedCat > 0): ?>
            <input type="hidden" name="cat" value="<?= $selectedCat ?>">
        <?php endif; ?>
        <div class="bx-browse-search">
            <input type="search" name="q" class="bx-input" value="<?= e($q) ?>" placeholder="<?= e($lang === 'bn' ? 'প্রিন্টেড বই, লেখক, ISBN খুঁজুন...' : 'Search printed books, author, ISBN...') ?>">
        </div>
        <select name="sort" class="bx-select bx-browse-ctl" aria-label="Sort">
            <option value="newest" <?= $sort === 'newest' ? 'selected' : '' ?>><?= $lang === 'bn' ? 'সর্বশেষ / প্রাসঙ্গিক' : 'Newest / Featured' ?></option>
            <option value="popular" <?= $sort === 'popular' ? 'selected' : '' ?>><?= $lang === 'bn' ? 'সবচেয়ে জনপ্রিয়' : 'Most Popular' ?></option>
            <option value="price_asc" <?= $sort === 'price_asc' ? 'selected' : '' ?>><?= $lang === 'bn' ? 'কম থেকে বেশি দাম' : 'Price: Low to High' ?></option>
            <option value="price_desc" <?= $sort === 'price_desc' ? 'selected' : '' ?>><?= $lang === 'bn' ? 'বেশি থেকে কম দাম' : 'Price: High to Low' ?></option>
            <option value="title" <?= $sort === 'title' ? 'selected' : '' ?>><?= $lang === 'bn' ? 'নাম অনুযায়ী (ক-হ)' : 'Title (A–Z)' ?></option>
        </select>
        <label class="bx-browse-check">
            <input type="checkbox" name="in_stock" value="1" <?= $inStock ? 'checked' : '' ?>>
            <span><?= $lang === 'bn' ? 'শুধু স্টকে আছে' : 'In Stock Only' ?></span>
        </label>
        <button type="submit" class="bx-btn bx-btn-primary bx-btn-sm"><?= icon('search') ?> <?= $lang === 'bn' ? 'খুঁজুন' : 'Apply' ?></button>
        <?php if ($q !== '' || $selectedCat > 0 || $sort !== 'newest' || $inStock): ?>
            <a href="<?= e(url('/shop')) ?>" class="bx-btn bx-btn-ghost bx-btn-sm"><?= $lang === 'bn' ? 'রিসেট' : 'Reset' ?></a>
        <?php endif; ?>
    </form>

    <?php if (!$products): ?>
        <div class="bx-empty-state">
            <?= illustration_svg('empty') ?>
            <h2 class="bx-h2" style="margin-bottom:8px;"><?= $lang === 'bn' ? 'কোনো বই পাওয়া যায়নি' : 'No Physical Books Found' ?></h2>
            <p class="bx-muted" style="margin:0 0 18px;"><?= $lang === 'bn' ? 'অন্য কোনো শব্দ বা ক্যাটাগরি দিয়ে খুঁজে দেখুন।' : 'Try clearing filters or browsing another category.' ?></p>
            <a href="<?= e(url('/shop')) ?>" class="bx-btn bx-btn-primary"><?= $lang === 'bn' ? 'সব বই দেখুন' : 'Browse All Books' ?></a>
        </div>
    <?php else: ?>
        <div class="bx-book-grid" id="bx-shop-grid">
            <?php foreach ($products as $pIdx => $p):
                $pid = (int) $p['id'];
                $eff = shop_product_effective_price($p);
                $reg = (float) $p['price'];
                $stock = (int) $p['stock'];
                $coverRel = !empty($p['cover_image']) ? (string) $p['cover_image'] : 'uploads/covers/cover-bangla.svg';
                $prodUrl = url('/shop/' . rawurlencode((string) $p['slug']));
                $wished = !empty($wishProductMap[$pid]);
                $discPct = ($eff < $reg && $reg > 0) ? (int) round((($reg - $eff) / $reg) * 100) : 0;
            ?>
                <div class="bx-book-3d">
                    <article class="bx-book-card">
                        <a href="<?= e($prodUrl) ?>" class="bx-book-cover">
                            <?= render_responsive_picture($coverRel, bi($p, 'title'), [
                                'width'  => 320,
                                'height' => 480,
                                'sizes'  => '(max-width: 600px) 46vw, 240px',
                                'is_lcp' => ($pIdx < 2),
                            ]) ?>
                            <?php if ($stock <= 0): ?>
                                <span class="bx-book-ribbon" style="background:var(--rose);"><?= $lang === 'bn' ? 'স্টক শেষ' : 'Out of Stock' ?></span>
                            <?php elseif ($discPct > 0): ?>
                                <span class="bx-book-ribbon free">-<?= e(num_localize($discPct)) ?>%</span>
                            <?php elseif (!empty($p['is_featured'])): ?>
                                <span class="bx-book-ribbon"><?= $lang === 'bn' ? 'জনপ্রিয়' : 'Featured' ?></span>
                            <?php endif; ?>
                        </a>
                        <button type="button" class="bx-wish-btn <?= $wished ? 'is-wished' : '' ?>" data-shop-wish="<?= $pid ?>" aria-label="<?= e($lang === 'bn' ? 'পছন্দে রাখুন' : 'Wishlist') ?>">
                            <?= icon('heart') ?>
                        </button>
                        <div class="bx-book-body">
                            <a href="<?= e($prodUrl) ?>" class="bx-book-title bx-book-title-link"><?= e(bi($p, 'title')) ?></a>
                            <?php $author = bi($p, 'author'); if ($author !== ''): ?>
                                <div class="bx-book-author"><?= e($author) ?></div>
                            <?php endif; ?>
                            <?php if ((int) ($p['rating_count'] ?? 0) > 0): ?>
                                <div class="bx-card-rating" style="margin-left:0;margin-top:4px;">
                                    ★ <?= number_format((float) $p['rating_avg'], 1) ?>
                                    <span class="bx-muted">(<?= e(num_localize((int) $p['rating_count'])) ?>)</span>
                                </div>
                            <?php endif; ?>
                            <div class="bx-book-foot">
                                <div class="bx-price">
                                    <?= e(format_bdt($eff)) ?>
                                    <?php if ($eff < $reg): ?>
                                        <span class="bx-price-old"><?= e(format_bdt($reg)) ?></span>
                                    <?php endif; ?>
                                </div>
                                <?php if ($stock > 0): ?>
                                    <button type="button" class="bx-btn bx-btn-primary bx-btn-sm" data-add-cart="<?= $pid ?>">
                                        <?= icon('cart') ?> <?= $lang === 'bn' ? 'কার্টে নিন' : 'Add' ?>
                                    </button>
                                <?php else: ?>
                                    <a href="<?= e($prodUrl) ?>" class="bx-btn bx-btn-ghost bx-btn-sm"><?= e(t('view_details')) ?></a>
                                <?php endif; ?>
                            </div>
                        </div>
                    </article>
                </div>
            <?php endforeach; ?>
        </div>

        <?php if ($totalPages > 1): ?>
            <div class="bx-loadmore-wrap">
                <?php if ($page < $totalPages): ?>
                    <a href="<?= e($buildShopUrl(['page' => (string) ($page + 1)])) ?>" class="bx-btn bx-btn-outline">
                        <?= $lang === 'bn' ? 'আরও দেখুন (' . num_localize($page) . '/' . num_localize($totalPages) . ')' : 'Load More (' . $page . '/' . $totalPages . ')' ?>
                    </a>
                <?php endif; ?>
                <nav class="bx-pagination-links" aria-label="Shop Pagination">
                    <?php if ($page > 1): ?>
                        <a href="<?= e($buildShopUrl(['page' => (string) ($page - 1)])) ?>" class="bx-btn bx-btn-ghost bx-btn-sm">« <?= $lang === 'bn' ? 'আগের' : 'Prev' ?></a>
                    <?php endif; ?>
                    <?php for ($pNo = max(1, $page - 2); $pNo <= min($totalPages, $page + 2); $pNo++): ?>
                        <a href="<?= e($buildShopUrl(['page' => (string) $pNo])) ?>" class="bx-btn bx-btn-sm <?= $pNo === $page ? 'bx-btn-primary' : 'bx-btn-outline' ?>"><?= e(num_localize($pNo)) ?></a>
                    <?php endfor; ?>
                    <?php if ($page < $totalPages): ?>
                        <a href="<?= e($buildShopUrl(['page' => (string) ($page + 1)])) ?>" class="bx-btn bx-btn-ghost bx-btn-sm"><?= $lang === 'bn' ? 'পরের' : 'Next' ?> »</a>
                    <?php endif; ?>
                </nav>
            </div>
        <?php endif; ?>
    <?php endif; ?>
</div>
