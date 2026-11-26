<?php
declare(strict_types=1);
/** @var array $summary */
$lang = current_lang();
$items = $summary['items'] ?? [];
$del = $summary['delivery'] ?? shop_calculate_delivery(0.0, 'inside_dhaka', 'Dhaka');
$districts = shop_bd_districts();
$curArea = (string) ($del['area'] ?? 'inside_dhaka');
$curDist = (string) ($del['district'] ?? 'Dhaka');
$freeAbove = (float) ($del['free_above'] ?? 0);
?>
<div class="container" style="padding-top:20px;">
    <nav class="bx-breadcrumb" aria-label="Breadcrumb">
        <a href="<?= e(url('/')) ?>"><?= e(t('nav_home')) ?></a>
        <?= icon('chevron-right') ?>
        <a href="<?= e(url('/shop')) ?>"><?= e(t('nav_shop')) ?></a>
        <?= icon('chevron-right') ?>
        <span class="current"><?= e(t('nav_cart')) ?></span>
    </nav>

    <div class="bx-section-head" style="margin-top:16px;">
        <div>
            <h1 class="bx-h2"><?= $lang === 'bn' ? 'আপনার শপিং কার্ট' : 'Your Shopping Cart' ?> (<?= e(num_localize((int) ($summary['item_count'] ?? 0))) ?>)</h1>
        </div>
        <a href="<?= e(url('/shop')) ?>" class="bx-btn bx-btn-outline bx-btn-sm">← <?= $lang === 'bn' ? 'আরও বই দেখুন' : 'Continue Shopping' ?></a>
    </div>

    <?php if (!$items): ?>
        <div class="bx-empty-state">
            <?= illustration_svg('empty') ?>
            <h2 class="bx-h2" style="margin-bottom:8px;"><?= $lang === 'bn' ? 'আপনার কার্ট এখনো খালি' : 'Your Shopping Cart Is Empty' ?></h2>
            <p class="bx-muted" style="margin:0 0 18px;"><?= $lang === 'bn' ? 'শপ থেকে পছন্দের প্রিন্টেড বই কার্টে যোগ করুন।' : 'Add printed books from our shop to get started.' ?></p>
            <a href="<?= e(url('/shop')) ?>" class="bx-btn bx-btn-primary"><?= icon('cart') ?> <?= e(t('portal_shop_cta')) ?></a>
        </div>
    <?php else: ?>
        <?php if ($freeAbove > 0): $need = max(0.0, $freeAbove - (float) $summary['net_subtotal']); ?>
            <div class="bx-shop-promo-bar" style="margin-bottom:18px;">
                <?= icon('truck') ?>
                <span>
                    <?php if ($need <= 0): ?>
                        <?= $lang === 'bn' ? 'অভিনন্দন! আপনার অর্ডারে ফ্রি ডেলিভারি আনলক হয়েছে!' : 'Congrats! You unlocked FREE delivery on this order!' ?>
                    <?php else: ?>
                        <?= $lang === 'bn'
                            ? 'আরও ৳' . num_localize(number_format($need, 0)) . ' টাকার বই যোগ করলেই পাবেন ফ্রি ডেলিভারি!'
                            : 'Add ৳' . number_format($need, 0) . ' more to unlock FREE delivery!' ?>
                    <?php endif; ?>
                </span>
            </div>
        <?php endif; ?>

        <div class="bx-checkout-grid">
            <!-- Cart Items -->
            <div class="bx-card" style="padding:20px;">
                <div style="display:flex;flex-direction:column;gap:14px;">
                    <?php foreach ($items as $it):
                        $pid = (int) $it['id'];
                        $cov = !empty($it['cover_image']) ? asset((string) $it['cover_image']) : asset('uploads/covers/cover-bangla.svg');
                        $maxStock = (int) $it['stock'];
                    ?>
                        <div class="bx-cart-row">
                            <img src="<?= e($cov) ?>" alt="<?= e(bi($it, 'title')) ?>" width="64" height="90" style="width:64px;height:90px;object-fit:cover;border-radius:10px;flex-shrink:0;">
                            <div style="flex:1 1 180px;min-width:0;">
                                <a href="<?= e(url('/shop/' . rawurlencode((string) $it['slug']))) ?>" style="font-weight:700;color:var(--primary);display:block;"><?= e(bi($it, 'title')) ?></a>
                                <div class="bx-muted" style="font-size:0.86rem;"><?= e(format_bdt((float) $it['unit_price'])) ?> × <?= (int) $it['quantity'] ?> = <strong><?= e(format_bdt((float) $it['line_total'])) ?></strong></div>
                                <small class="bx-muted"><?= $lang === 'bn' ? 'স্টক আছে: ' : 'Stock: ' ?><?= (int) $maxStock ?></small>
                            </div>
                            <div style="display:flex;align-items:center;gap:8px;flex-wrap:wrap;">
                                <form method="post" action="<?= e(url('/cart')) ?>" style="display:inline-flex;align-items:center;gap:6px;margin:0;">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="action" value="update_qty">
                                    <input type="hidden" name="product_id" value="<?= $pid ?>">
                                    <input type="number" name="quantity" value="<?= (int) $it['quantity'] ?>" min="1" max="<?= min(99, $maxStock) ?>" class="bx-input" style="width:70px;padding:6px 8px;">
                                    <button type="submit" class="bx-btn bx-btn-outline bx-btn-sm"><?= $lang === 'bn' ? 'আপডেট' : 'Update' ?></button>
                                </form>
                                <form method="post" action="<?= e(url('/cart')) ?>" style="margin:0;">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="action" value="remove_item">
                                    <input type="hidden" name="product_id" value="<?= $pid ?>">
                                    <button type="submit" class="bx-btn bx-btn-ghost bx-btn-sm" style="color:var(--rose-text);"><?= icon('trash') ?></button>
                                </form>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- Shipping & Coupon Summary -->
            <div class="bx-card" style="padding:22px;">
                <h2 class="bx-h3" style="margin-bottom:14px;"><?= $lang === 'bn' ? 'ডেলিভারি ও অর্ডার সারাংশ' : 'Delivery & Cart Summary' ?></h2>

                <form method="post" action="<?= e(url('/cart')) ?>" style="margin-bottom:14px;padding-bottom:14px;border-bottom:1px dashed var(--border);">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="update_shipping">
                    <div class="bx-field" style="margin-bottom:8px;">
                        <label><?= $lang === 'bn' ? 'ডেলিভারি এলাকা' : 'Delivery Area' ?></label>
                        <select name="delivery_area" class="bx-select">
                            <option value="inside_dhaka" <?= $curArea === 'inside_dhaka' ? 'selected' : '' ?>><?= $lang === 'bn' ? 'ঢাকার ভেতরে' : 'Inside Dhaka' ?></option>
                            <option value="outside_dhaka" <?= $curArea === 'outside_dhaka' ? 'selected' : '' ?>><?= $lang === 'bn' ? 'ঢাকার বাইরে' : 'Outside Dhaka' ?></option>
                        </select>
                    </div>
                    <div class="bx-field" style="margin-bottom:10px;">
                        <label><?= $lang === 'bn' ? 'জেলা' : 'District' ?></label>
                        <select name="district" class="bx-select">
                            <?php foreach ($districts as $dk => $dl): ?>
                                <option value="<?= e($dk) ?>" <?= $curDist === $dk ? 'selected' : '' ?>><?= e($dl[$lang]) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <button type="submit" class="bx-btn bx-btn-outline bx-btn-sm" style="width:100%;"><?= $lang === 'bn' ? 'ডেলিভারি চার্জ দেখুন' : 'Calculate Delivery' ?></button>
                </form>

                <form method="post" action="<?= e(url('/cart')) ?>" style="margin-bottom:16px;">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="apply_coupon">
                    <label style="display:block;font-weight:700;font-size:0.86rem;margin-bottom:6px;"><?= $lang === 'bn' ? 'কুপন কোড' : 'Promo / Coupon Code' ?></label>
                    <div style="display:flex;gap:8px;">
                        <input type="text" name="coupon_code" class="bx-input" placeholder="SAVE20" value="<?= e((string) ($summary['coupon_code'] ?? '')) ?>">
                        <button type="submit" class="bx-btn bx-btn-outline bx-btn-sm"><?= $lang === 'bn' ? 'প্রয়োগ' : 'Apply' ?></button>
                    </div>
                    <?php if (!empty($summary['coupon_error'])): ?>
                        <div style="font-size:0.82rem;color:var(--rose-text);margin-top:4px;"><?= e((string) $summary['coupon_error']) ?></div>
                    <?php elseif (!empty($summary['coupon']['message'])): ?>
                        <div style="font-size:0.82rem;color:var(--teal);margin-top:4px;font-weight:700;"><?= e((string) $summary['coupon']['message']) ?></div>
                    <?php endif; ?>
                </form>

                <div style="display:flex;flex-direction:column;gap:8px;margin-bottom:16px;">
                    <div style="display:flex;justify-content:space-between;"><span><?= $lang === 'bn' ? 'বইয়ের মূল্য' : 'Subtotal' ?></span><strong><?= e(format_bdt((float) $summary['subtotal'])) ?></strong></div>
                    <?php if ((float) $summary['discount_amount'] > 0): ?>
                        <div style="display:flex;justify-content:space-between;color:var(--teal);"><span><?= $lang === 'bn' ? 'কুপন ছাড়' : 'Discount' ?></span><strong>-<?= e(format_bdt((float) $summary['discount_amount'])) ?></strong></div>
                    <?php endif; ?>
                    <div style="display:flex;justify-content:space-between;">
                        <span><?= $lang === 'bn' ? 'ডেলিভারি চার্জ (' . e($del['est_days']) . ')' : 'Delivery (' . e($del['est_days']) . ')' ?></span>
                        <strong><?= (float) $summary['delivery_fee'] <= 0 ? ($lang === 'bn' ? 'ফ্রি!' : 'FREE') : e(format_bdt((float) $summary['delivery_fee'])) ?></strong>
                    </div>
                    <div style="display:flex;justify-content:space-between;padding-top:10px;border-top:2px solid var(--border);font-size:1.15rem;font-weight:800;color:var(--primary);">
                        <span><?= $lang === 'bn' ? 'মোট দেয়' : 'Total' ?></span>
                        <span><?= e(format_bdt((float) $summary['total_amount'])) ?></span>
                    </div>
                </div>

                <a href="<?= e(url('/shop/checkout')) ?>" class="bx-btn bx-btn-accent" style="width:100%;">
                    <?= icon('check') ?> <?= $lang === 'bn' ? 'অর্ডার চেকআউটে যান' : 'Proceed to Checkout' ?>
                </a>
            </div>
        </div>
    <?php endif; ?>
</div>
