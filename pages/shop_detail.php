<?php
declare(strict_types=1);
/**
 * @var array $product, array $galleryImages, array $catTrail, array $reviews, array $ratingSummary, array $relatedProducts, bool $isWished, ?int $verifiedOrderId
 */
$lang = current_lang();
$pid = (int) $product['id'];
$eff = shop_product_effective_price($product);
$reg = (float) $product['price'];
$stock = (int) $product['stock'];
$lowThresh = (int) $product['low_stock_threshold'];
$delCfg = shop_delivery_config();
$mainCoverRel = !empty($galleryImages[0]['image_path'])
    ? (string) $galleryImages[0]['image_path']
    : (!empty($product['cover_image']) ? (string) $product['cover_image'] : 'uploads/covers/cover-bangla.svg');
$canonicalUrl = absolute_url_for_lang('/shop/' . rawurlencode((string) $product['slug']), $lang);
?>
<div class="container" style="padding-top:20px;">
    <nav class="bx-breadcrumb" aria-label="Breadcrumb">
        <a href="<?= e(url('/')) ?>"><?= e(t('nav_home')) ?></a>
        <?= icon('chevron-right') ?>
        <a href="<?= e(url('/shop')) ?>"><?= e(t('nav_shop')) ?></a>
        <?php foreach ($catTrail as $tc): ?>
            <?= icon('chevron-right') ?>
            <a href="<?= e(url('/shop?cat=' . (int) $tc['id'])) ?>"><?= e($lang === 'bn' ? $tc['name_bn'] : $tc['name_en']) ?></a>
        <?php endforeach; ?>
        <?= icon('chevron-right') ?>
        <span class="current"><?= e(bi($product, 'title')) ?></span>
    </nav>

    <div class="bx-detail-grid" style="margin-top:22px;">
        <!-- Gallery Stage -->
        <div>
            <div class="bx-detail-stage">
                <div class="bx-detail-book-3d">
                    <?= render_responsive_picture($mainCoverRel, bi($product, 'title'), [
                        'id'     => 'bx-product-main-img',
                        'width'  => 400,
                        'height' => 600,
                        'sizes'  => '(max-width: 768px) 72vw, 320px',
                        'is_lcp' => true,
                    ]) ?>
                </div>
            </div>
            <?php if (count($galleryImages) > 1): ?>
                <div class="bx-gallery-thumbs" role="list">
                    <?php foreach ($galleryImages as $idx => $img): $src = asset((string) $img['image_path']); ?>
                        <button type="button" class="bx-gallery-thumb <?= $idx === 0 ? 'active' : '' ?>" data-gallery-thumb="<?= e($src) ?>" aria-label="Photo <?= $idx + 1 ?>">
                            <img src="<?= e($src) ?>" alt="" width="72" height="96" loading="lazy">
                        </button>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

        <!-- Product Info & Purchase Box -->
        <div class="bx-card" style="padding:24px;">
            <div style="display:flex;justify-content:space-between;align-items:flex-start;gap:12px;">
                <div style="display:flex;gap:8px;flex-wrap:wrap;margin-bottom:10px;">
                    <?php if ($stock <= 0): ?>
                        <span class="bx-badge bx-badge-accent"><?= $lang === 'bn' ? 'স্টক শেষ' : 'Out of Stock' ?></span>
                    <?php elseif ($stock <= $lowThresh): ?>
                        <span class="bx-badge bx-badge-gold"><?= $lang === 'bn' ? 'মাত্র ' . num_localize($stock) . 'টি বাকি!' : "Low Stock: Only {$stock} left!" ?></span>
                    <?php else: ?>
                        <span class="bx-badge bx-badge-teal"><?= icon('check') ?> <?= $lang === 'bn' ? 'স্টকে আছে (' . num_localize($stock) . ')' : "In Stock ({$stock})" ?></span>
                    <?php endif; ?>
                    <?php if ($delCfg['cod_enabled']): ?>
                        <span class="bx-badge"><?= icon('truck') ?> <?= $lang === 'bn' ? 'ক্যাশ অন ডেলিভারি' : 'Cash on Delivery' ?></span>
                    <?php endif; ?>
                </div>
                <button type="button" class="bx-wish-btn <?= $isWished ? 'is-wished' : '' ?>" style="position:static;" data-shop-wish="<?= $pid ?>" aria-label="<?= e($lang === 'bn' ? 'পছন্দে রাখুন' : 'Toggle wishlist') ?>">
                    <?= icon('heart') ?>
                </button>
            </div>

            <h1 class="bx-display" style="font-size:clamp(1.45rem,2.6vw,2rem);margin-bottom:8px;"><?= e(bi($product, 'title')) ?></h1>

            <?php if ((int) ($ratingSummary['count'] ?? 0) > 0): ?>
                <div style="display:flex;align-items:center;gap:8px;margin-bottom:12px;">
                    <?= render_stars_html((float) $ratingSummary['avg']) ?>
                    <span class="bx-muted" style="font-size:0.9rem;">
                        <?= number_format((float) $ratingSummary['avg'], 1) ?> / 5 (<?= e(num_localize((int) $ratingSummary['count'])) ?> <?= $lang === 'bn' ? 'রিভিউ' : 'reviews' ?>)
                    </span>
                </div>
            <?php endif; ?>

            <div class="bx-price" style="font-size:1.75rem;margin-bottom:14px;">
                <?= e(format_bdt($eff)) ?>
                <?php if ($eff < $reg): ?>
                    <span class="bx-price-old" style="font-size:1.1rem;"><?= e(format_bdt($reg)) ?></span>
                <?php endif; ?>
            </div>

            <!-- Metadata Pills -->
            <div class="bx-meta-pills" style="margin-bottom:18px;">
                <?php $author = bi($product, 'author'); if ($author !== ''): ?>
                    <span class="bx-meta-pill"><strong><?= $lang === 'bn' ? 'লেখক:' : 'Author:' ?></strong> <?= e($author) ?></span>
                <?php endif; ?>
                <?php if (!empty($product['publisher'])): ?>
                    <span class="bx-meta-pill"><strong><?= $lang === 'bn' ? 'প্রকাশক:' : 'Publisher:' ?></strong> <?= e((string) $product['publisher']) ?></span>
                <?php endif; ?>
                <?php if (!empty($product['isbn'])): ?>
                    <span class="bx-meta-pill"><strong>ISBN:</strong> <?= e((string) $product['isbn']) ?></span>
                <?php endif; ?>
                <?php if (!empty($product['sku'])): ?>
                    <span class="bx-meta-pill"><strong>SKU:</strong> <?= e((string) $product['sku']) ?></span>
                <?php endif; ?>
                <span class="bx-meta-pill"><strong><?= $lang === 'bn' ? 'ওজন:' : 'Weight:' ?></strong> <?= e(num_localize((int) $product['weight_grams'])) ?>g</span>
            </div>

            <!-- Qty & Cart Actions -->
            <?php if ($stock > 0): ?>
                <div style="display:flex;gap:10px;flex-wrap:wrap;align-items:center;margin-bottom:16px;">
                    <div class="bx-qty-control">
                        <button type="button" class="bx-qty-step" data-qty-step="-1" aria-label="Decrease">−</button>
                        <input type="number" id="bx-detail-qty" value="1" min="1" max="<?= min(99, $stock) ?>" aria-label="Quantity">
                        <button type="button" class="bx-qty-step" data-qty-step="1" aria-label="Increase">+</button>
                    </div>
                    <button type="button" class="bx-btn bx-btn-primary" data-add-cart-detail="<?= $pid ?>" data-qty-input="#bx-detail-qty">
                        <?= icon('cart') ?> <?= $lang === 'bn' ? 'কার্টে যোগ করুন' : 'Add to Cart' ?>
                    </button>
                    <button type="button" class="bx-btn bx-btn-accent" data-add-cart-detail="<?= $pid ?>" data-qty-input="#bx-detail-qty" data-go-checkout="1">
                        <?= icon('check') ?> <?= $lang === 'bn' ? 'এখনই কিনুন' : 'Buy Now' ?>
                    </button>
                </div>
            <?php else: ?>
                <div class="bx-alert bx-alert-danger" style="padding:12px 14px;border-radius:12px;background:var(--bg-alt);border:1px solid var(--border);margin-bottom:16px;font-weight:700;color:var(--rose-text);">
                    <?= $lang === 'bn' ? 'এই প্রিন্টেড বইটি বর্তমানে স্টকে নেই। দ্রুত পুনরায় স্টক করা হবে।' : 'This printed edition is currently out of stock.' ?>
                </div>
            <?php endif; ?>

            <!-- Delivery Estimate Info Box -->
            <div class="bx-security-box" style="margin-bottom:16px;">
                <?= icon('truck', 'bx-icon-lg') ?>
                <div style="font-size:0.88rem;line-height:1.5;">
                    <strong><?= $lang === 'bn' ? 'ডেলিভারি তথ্য:' : 'Delivery Info:' ?></strong>
                    <?= $lang === 'bn'
                        ? 'ঢাকা ৳' . num_localize(number_format($delCfg['dhaka_fee'], 0)) . ' (' . e($delCfg['est_days_dhaka']) . ') • ঢাকার বাইরে ৳' . num_localize(number_format($delCfg['outside_fee'], 0)) . ' (' . e($delCfg['est_days_outside']) . ')'
                        : 'Inside Dhaka ৳' . number_format($delCfg['dhaka_fee'], 0) . ' (' . e($delCfg['est_days_dhaka']) . ') • Outside Dhaka ৳' . number_format($delCfg['outside_fee'], 0) . ' (' . e($delCfg['est_days_outside']) . ')' ?>
                    <?php if ($delCfg['free_delivery_above'] > 0): ?>
                        <div><?= $lang === 'bn'
                            ? '৳' . num_localize(number_format($delCfg['free_delivery_above'], 0)) . '+ অর্ডারে সম্পূর্ণ ফ্রি ডেলিভারি!'
                            : 'FREE delivery on orders of ৳' . number_format($delCfg['free_delivery_above'], 0) . ' or more!' ?></div>
                    <?php endif; ?>
                </div>
            </div>

            <?php $desc = bi($product, 'description'); if ($desc !== ''): ?>
                <div style="margin-top:16px;padding-top:14px;border-top:1px solid var(--border);line-height:1.7;">
                    <h2 class="bx-h3" style="margin-bottom:6px;font-size:1rem;"><?= $lang === 'bn' ? 'বই পরিচিতি' : 'Description' ?></h2>
                    <div class="bx-muted"><?= nl2br(e($desc)) ?></div>
                </div>
            <?php endif; ?>

            <!-- Share Bar -->
            <div style="margin-top:18px;">
                <?= render_social_share_bar($canonicalUrl, bi($product, 'title')) ?>
            </div>
        </div>
    </div>

    <!-- Verified Buyer Reviews -->
    <section class="bx-card" style="margin-top:28px;padding:24px;" id="reviews">
        <div style="display:flex;justify-content:space-between;align-items:center;gap:12px;flex-wrap:wrap;margin-bottom:16px;">
            <h2 class="bx-h2" style="margin:0;"><?= $lang === 'bn' ? 'যাচাইকৃত ক্রেতাদের রিভিউ' : 'Verified Buyer Reviews' ?> (<?= e(num_localize((int) ($ratingSummary['count'] ?? 0))) ?>)</h2>
        </div>

        <?php if ($verifiedOrderId !== null): ?>
            <form method="post" action="<?= e(url('/shop/' . rawurlencode((string) $product['slug']) . '/review')) ?>" style="margin-bottom:20px;padding:16px;border-radius:14px;background:var(--bg-alt);border:1px solid var(--border);">
                <?= csrf_field() ?>
                <div style="display:flex;gap:12px;flex-wrap:wrap;align-items:center;margin-bottom:10px;">
                    <strong style="font-size:0.94rem;"><?= $lang === 'bn' ? 'আপনার রেটিং দিন:' : 'Rate this printed book:' ?></strong>
                    <select name="rating" class="bx-select" style="width:auto;" required>
                        <option value="5">★★★★★ (5)</option>
                        <option value="4">★★★★☆ (4)</option>
                        <option value="3">★★★☆☆ (3)</option>
                        <option value="2">★★☆☆☆ (2)</option>
                        <option value="1">★☆☆☆☆ (1)</option>
                    </select>
                </div>
                <textarea name="comment" rows="3" class="bx-input" placeholder="<?= e($lang === 'bn' ? 'বইটির কাগজের মান, প্রিন্টিং ও ডেলিভারি অভিজ্ঞতা লিখুন...' : 'Share print quality & delivery experience...') ?>"></textarea>
                <button type="submit" class="bx-btn bx-btn-primary bx-btn-sm" style="margin-top:10px;"><?= $lang === 'bn' ? 'রিভিউ জমা দিন' : 'Submit Review' ?></button>
            </form>
        <?php else: ?>
            <p class="bx-muted" style="margin:0 0 16px;font-size:0.9rem;">
                <?= $lang === 'bn'
                    ? 'শুধুমাত্র ডেলিভারি সম্পন্ন হওয়া যাচাইকৃত ক্রেতারা এই বইটিতে রিভিউ লিখতে পারবেন।'
                    : 'Only verified buyers whose order has been delivered can leave a review for this book.' ?>
            </p>
        <?php endif; ?>

        <?php if (!$reviews): ?>
            <p class="bx-muted" style="margin:0;"><?= $lang === 'bn' ? 'এখনো কোনো রিভিউ জমা হয়নি।' : 'No reviews yet.' ?></p>
        <?php else: ?>
            <div style="display:flex;flex-direction:column;gap:12px;">
                <?php foreach ($reviews as $rv): ?>
                    <div style="padding:14px;border-radius:12px;background:var(--bg-alt);border:1px solid var(--border);">
                        <div style="display:flex;justify-content:space-between;align-items:center;gap:8px;flex-wrap:wrap;">
                            <div>
                                <strong><?= e((string) $rv['user_name']) ?></strong>
                                <span class="bx-badge bx-badge-teal" style="margin-left:6px;font-size:0.72rem;"><?= icon('check') ?> Verified Buyer</span>
                            </div>
                            <div><?= render_stars_html((float) $rv['rating']) ?></div>
                        </div>
                        <?php if (!empty($rv['comment'])): ?>
                            <p style="margin:8px 0 4px;line-height:1.55;"><?= e((string) $rv['comment']) ?></p>
                        <?php endif; ?>
                        <small class="bx-muted"><?= e(date('d M Y', strtotime((string) $rv['created_at']))) ?></small>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </section>

    <!-- Related Books -->
    <?php if (!empty($relatedProducts)): ?>
        <section style="margin-top:34px;">
            <div class="bx-section-head">
                <h2 class="bx-h2"><?= $lang === 'bn' ? 'আরও প্রিন্টেড বই' : 'Related Books' ?></h2>
                <a href="<?= e(url('/shop')) ?>" class="bx-btn bx-btn-ghost bx-btn-sm"><?= e(t('view_all')) ?> →</a>
            </div>
            <div class="bx-book-grid">
                <?php foreach ($relatedProducts as $rp):
                    $rpid = (int) $rp['id'];
                    $reff = shop_product_effective_price($rp);
                    $rreg = (float) $rp['price'];
                    $rcov = !empty($rp['cover_image']) ? asset((string) $rp['cover_image']) : asset('uploads/covers/cover-bangla.svg');
                    $rurl = url('/shop/' . rawurlencode((string) $rp['slug']));
                ?>
                    <div class="bx-book-3d">
                        <article class="bx-book-card">
                            <a href="<?= e($rurl) ?>" class="bx-book-cover">
                                <img src="<?= e($rcov) ?>" alt="<?= e(bi($rp, 'title')) ?>" width="300" height="450" loading="lazy">
                            </a>
                            <div class="bx-book-body">
                                <a href="<?= e($rurl) ?>" class="bx-book-title bx-book-title-link"><?= e(bi($rp, 'title')) ?></a>
                                <div class="bx-book-foot">
                                    <span class="bx-price"><?= e(format_bdt($reff)) ?></span>
                                    <?php if ((int) $rp['stock'] > 0): ?>
                                        <button type="button" class="bx-btn bx-btn-primary bx-btn-sm" data-add-cart="<?= $rpid ?>"><?= icon('cart') ?> <?= $lang === 'bn' ? 'কার্ট' : 'Add' ?></button>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </article>
                    </div>
                <?php endforeach; ?>
            </div>
        </section>
    <?php endif; ?>
</div>
