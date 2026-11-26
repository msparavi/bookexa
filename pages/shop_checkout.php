<?php
declare(strict_types=1);
/** @var array $summary, ?array $user, ?string $error */
$lang = current_lang();
$items = $summary['items'] ?? [];
$delCfg = shop_delivery_config();
$del = $summary['delivery'] ?? shop_calculate_delivery(0.0, 'inside_dhaka', 'Dhaka');
$districts = shop_bd_districts();
$gwDriver = payment_gateway_driver();
$useAutoGw = $gwDriver->isAutomatic();

$postVal = static fn(string $key, string $fallback = ''): string => trim((string) ($_POST[$key] ?? $fallback));
$curArea = $postVal('delivery_area', (string) ($del['area'] ?? 'inside_dhaka'));
$curDist = $postVal('district', (string) ($del['district'] ?? 'Dhaka'));
$curPay  = $postVal('payment_method', $delCfg['cod_enabled'] ? 'cod' : ($useAutoGw ? $gwDriver->getCode() : 'bkash'));
$curCoupon = $postVal('coupon_code', (string) ($summary['coupon_code'] ?? ''));

$nameDefault = $postVal('customer_name', (string) ($user['name'] ?? ''));
$phoneDefault = $postVal('customer_phone', (string) ($user['phone'] ?? ''));
$emailDefault = $postVal('customer_email', (string) ($user['email'] ?? ''));
?>
<div class="container" style="padding-top:20px;">
    <nav class="bx-breadcrumb" aria-label="Breadcrumb">
        <a href="<?= e(url('/')) ?>"><?= e(t('nav_home')) ?></a>
        <?= icon('chevron-right') ?>
        <a href="<?= e(url('/shop')) ?>"><?= e(t('nav_shop')) ?></a>
        <?= icon('chevron-right') ?>
        <a href="<?= e(url('/cart')) ?>"><?= e(t('nav_cart')) ?></a>
        <?= icon('chevron-right') ?>
        <span class="current"><?= $lang === 'bn' ? 'চেকআউট' : 'Checkout' ?></span>
    </nav>

    <h1 class="bx-h2" style="margin-top:16px;"><?= $lang === 'bn' ? 'ডেলিভারি ও পেমেন্ট চেকআউট' : 'Shipping & Payment Checkout' ?></h1>

    <?php if (!empty($error)): ?>
        <div class="bx-alert bx-alert-danger" style="margin:14px 0;padding:12px 16px;border-radius:12px;background:var(--surface);border:1px solid var(--border);border-left:4px solid var(--rose);color:var(--rose-text);font-weight:700;">
            <?= e($error) ?>
        </div>
    <?php endif; ?>

    <form id="bx-shop-checkout-form" method="post" action="<?= e(url('/shop/checkout')) ?>" enctype="multipart/form-data">
        <?= csrf_field() ?>
        <input type="hidden" name="checkout_nonce" value="<?= e(shop_checkout_nonce()) ?>">
        <div style="position:absolute;left:-9999px;width:1px;height:1px;overflow:hidden;" aria-hidden="true">
            <label>Website<input type="text" name="website_hp" value="" tabindex="-1" autocomplete="off"></label>
        </div>

        <div class="bx-checkout-grid" style="margin-top:18px;">
            <div class="bx-card" style="padding:24px;">
                <h2 class="bx-h3" style="margin-bottom:14px;"><?= $lang === 'bn' ? '১. প্রাপকের ঠিকানা' : '1. Shipping Details' ?></h2>

                <div class="bx-grid-2" style="gap:12px;">
                    <div class="bx-field">
                        <label for="customer_name"><?= $lang === 'bn' ? 'প্রাপকের নাম *' : 'Recipient Full Name *' ?></label>
                        <input class="bx-input" id="customer_name" name="customer_name" required value="<?= e($nameDefault) ?>">
                    </div>
                    <div class="bx-field">
                        <label for="customer_phone"><?= $lang === 'bn' ? 'মোবাইল নম্বর *' : 'Mobile Phone *' ?></label>
                        <input class="bx-input" id="customer_phone" name="customer_phone" type="tel" required placeholder="01XXXXXXXXX" value="<?= e($phoneDefault) ?>">
                    </div>
                </div>

                <div class="bx-grid-2" style="gap:12px;">
                    <div class="bx-field">
                        <label for="customer_email"><?= $lang === 'bn' ? 'ইমেইল (ইনভয়েস ও ট্র্যাকিং আপডেটের জন্য)' : 'Email (Optional — for Invoice & Tracking updates)' ?></label>
                        <input class="bx-input" id="customer_email" name="customer_email" type="email" value="<?= e($emailDefault) ?>">
                    </div>
                    <div class="bx-field">
                        <label for="checkout_area"><?= $lang === 'bn' ? 'ডেলিভারি এলাকা *' : 'Delivery Zone *' ?></label>
                        <select class="bx-select" id="checkout_area" name="delivery_area" data-checkout-area>
                            <option value="inside_dhaka" <?= $curArea === 'inside_dhaka' ? 'selected' : '' ?>><?= $lang === 'bn' ? 'ঢাকার ভেতরে (৳' . num_localize(number_format($delCfg['dhaka_fee'], 0)) . ')' : 'Inside Dhaka (৳' . number_format($delCfg['dhaka_fee'], 0) . ')' ?></option>
                            <option value="outside_dhaka" <?= $curArea === 'outside_dhaka' ? 'selected' : '' ?>><?= $lang === 'bn' ? 'ঢাকার বাইরে (৳' . num_localize(number_format($delCfg['outside_fee'], 0)) . ')' : 'Outside Dhaka (৳' . number_format($delCfg['outside_fee'], 0) . ')' ?></option>
                        </select>
                    </div>
                </div>

                <div class="bx-field">
                    <label for="checkout_district"><?= $lang === 'bn' ? 'জেলা *' : 'District *' ?></label>
                    <select class="bx-select" id="checkout_district" name="district" data-checkout-district>
                        <?php foreach ($districts as $dk => $dl): ?>
                            <option value="<?= e($dk) ?>" <?= $curDist === $dk ? 'selected' : '' ?>><?= e($dl[$lang]) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="bx-field">
                    <label for="shipping_address"><?= $lang === 'bn' ? 'সম্পূর্ণ ঠিকানা (বাড়ি/রোড/থানা) *' : 'Full Delivery Address (House / Road / Area) *' ?></label>
                    <textarea class="bx-input" id="shipping_address" name="shipping_address" rows="3" required placeholder="<?= e($lang === 'bn' ? 'বাড়ি নং, রোড, এলাকা/থানা...' : 'House #, Road #, Area/Thana...') ?>"><?= e($postVal('shipping_address')) ?></textarea>
                </div>

                <hr style="border:none;border-top:1px solid var(--border);margin:20px 0;">

                <h2 class="bx-h3" style="margin-bottom:12px;"><?= $lang === 'bn' ? '২. পেমেন্ট পদ্ধতি নির্বাচন করুন' : '2. Payment Method' ?></h2>
                <div class="bx-radio-list" style="display:flex;flex-direction:column;gap:8px;margin-bottom:14px;">
                    <?php if ($delCfg['cod_enabled']): ?>
                        <label class="bx-agree-row" style="margin:0;">
                            <input type="radio" name="payment_method" value="cod" data-shop-pay-radio <?= $curPay === 'cod' ? 'checked' : '' ?>>
                            <span><strong><?= $lang === 'bn' ? 'ক্যাশ অন ডেলিভারি (COD)' : 'Cash on Delivery (COD)' ?></strong> — <small class="bx-muted"><?= $lang === 'bn' ? 'বই হাতে পেয়ে মূল্য পরিশোধ করুন' : 'Pay in cash when parcel arrives' ?></small></span>
                        </label>
                    <?php endif; ?>

                    <?php if ($useAutoGw): ?>
                        <label class="bx-agree-row" style="margin:0;">
                            <input type="radio" name="payment_method" value="<?= e($gwDriver->getCode()) ?>" data-shop-pay-radio <?= $curPay === $gwDriver->getCode() ? 'checked' : '' ?>>
                            <span><strong><?= e($gwDriver->getName()) ?></strong> — <small class="bx-muted"><?= $lang === 'bn' ? 'স্বয়ংক্রিয় অনলাইন পেমেন্ট' : 'Automatic online payment' ?></small></span>
                        </label>
                    <?php else: ?>
                        <?php foreach (['bkash' => 'bKash', 'nagad' => 'Nagad', 'rocket' => 'Rocket'] as $mk => $ml): ?>
                            <label class="bx-agree-row" style="margin:0;">
                                <input type="radio" name="payment_method" value="<?= e($mk) ?>" data-shop-pay-radio <?= $curPay === $mk ? 'checked' : '' ?>>
                                <span><strong><?= e($ml) ?> (<?= $lang === 'bn' ? 'ম্যানুয়াল' : 'Send Money' ?>)</strong></span>
                            </label>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>

                <?php if (!$useAutoGw): ?>
                    <div id="bx-shop-manual-fields" style="padding:14px;border-radius:12px;background:var(--bg-alt);border:1px solid var(--border);<?= $curPay === 'cod' ? 'display:none;' : '' ?>">
                        <div style="font-size:0.86rem;line-height:1.55;margin-bottom:12px;">
                            <div><strong>bKash:</strong> <?= e(site_setting('bkash_number')) ?></div>
                            <div><strong>Nagad:</strong> <?= e(site_setting('nagad_number')) ?></div>
                            <div><strong>Rocket:</strong> <?= e(site_setting('rocket_number')) ?></div>
                        </div>
                        <div class="bx-grid-2" style="gap:10px;">
                            <div class="bx-field">
                                <label for="shop_sender_number"><?= $lang === 'bn' ? 'আপনার প্রেরক নম্বর *' : 'Sender Number *' ?></label>
                                <input class="bx-input" id="shop_sender_number" name="sender_number" value="<?= e($postVal('sender_number')) ?>" placeholder="01XXXXXXXXX" <?= $curPay !== 'cod' ? 'required' : '' ?>>
                            </div>
                            <div class="bx-field">
                                <label for="shop_transaction_id">Transaction ID (TrxID) *</label>
                                <input class="bx-input" id="shop_transaction_id" name="transaction_id" value="<?= e($postVal('transaction_id')) ?>" placeholder="e.g. 9K7A2B1C3D" <?= $curPay !== 'cod' ? 'required' : '' ?>>
                            </div>
                        </div>
                        <div class="bx-field" style="margin:0;">
                            <label for="shop_payment_screenshot"><?= $lang === 'bn' ? 'পেমেন্ট স্ক্রিনশট (ঐচ্ছিক, max 3MB)' : 'Payment Screenshot (Optional, max 3MB)' ?></label>
                            <input class="bx-input" type="file" id="shop_payment_screenshot" name="payment_screenshot" accept="image/jpeg,image/png,image/webp">
                        </div>
                    </div>
                <?php endif; ?>

                <button type="submit" id="bx-shop-submit-btn" class="bx-btn bx-btn-accent" style="width:100%;margin-top:18px;" data-loading-label="<?= e($lang === 'bn' ? 'অর্ডার জমা হচ্ছে...' : 'Placing Order...') ?>">
                    <?= icon('check') ?> <span><?= $lang === 'bn' ? 'অর্ডার নিশ্চিত করুন' : 'Place Physical Order' ?></span>
                </button>
            </div>

            <!-- Order Summary -->
            <div class="bx-card" style="padding:22px;">
                <h2 class="bx-h3" style="margin-bottom:12px;"><?= $lang === 'bn' ? 'অর্ডার তালিকা' : 'Order Summary' ?></h2>
                <div style="display:flex;flex-direction:column;gap:10px;margin-bottom:14px;">
                    <?php foreach ($items as $it): ?>
                        <div style="display:flex;justify-content:space-between;gap:8px;font-size:0.9rem;">
                            <span><?= (int) $it['quantity'] ?>× <?= e(bi($it, 'title')) ?></span>
                            <strong><?= e(format_bdt((float) $it['line_total'])) ?></strong>
                        </div>
                    <?php endforeach; ?>
                </div>

                <div style="margin-bottom:14px;padding-top:12px;border-top:1px dashed var(--border);">
                    <label style="display:block;font-weight:600;font-size:0.84rem;margin-bottom:6px;"><?= $lang === 'bn' ? 'কুপন কোড' : 'Coupon Code' ?></label>
                    <div style="display:flex;gap:8px;">
                        <input type="text" class="bx-input" id="bx-shop-coupon-input" name="coupon_code" value="<?= e($curCoupon) ?>" placeholder="CODE">
                        <button type="button" class="bx-btn bx-btn-outline bx-btn-sm" id="bx-shop-coupon-apply"><?= $lang === 'bn' ? 'প্রয়োগ' : 'Apply' ?></button>
                    </div>
                    <div id="bx-shop-coupon-msg" style="font-size:0.82rem;margin-top:4px;"></div>
                </div>

                <div style="display:flex;flex-direction:column;gap:8px;">
                    <div style="display:flex;justify-content:space-between;"><span><?= $lang === 'bn' ? 'সাবটোটাল' : 'Subtotal' ?></span><strong id="bx-co-sub"><?= e(format_bdt((float) $summary['subtotal'])) ?></strong></div>
                    <div id="bx-co-disc-row" style="<?= (float) $summary['discount_amount'] > 0 ? 'display:flex;' : 'display:none;' ?>justify-content:space-between;color:var(--teal);">
                        <span><?= $lang === 'bn' ? 'কুপন ছাড়' : 'Discount' ?></span><strong id="bx-co-disc">-<?= e(format_bdt((float) $summary['discount_amount'])) ?></strong>
                    </div>
                    <div style="display:flex;justify-content:space-between;">
                        <span><?= $lang === 'bn' ? 'ডেলিভারি চার্জ' : 'Delivery Fee' ?></span>
                        <strong id="bx-co-del"><?= (float) $summary['delivery_fee'] <= 0 ? ($lang === 'bn' ? 'ফ্রি' : 'FREE') : e(format_bdt((float) $summary['delivery_fee'])) ?></strong>
                    </div>
                    <div style="display:flex;justify-content:space-between;padding-top:10px;border-top:2px solid var(--border);font-size:1.2rem;font-weight:800;color:var(--primary);">
                        <span><?= $lang === 'bn' ? 'মোট মূল্য' : 'Grand Total' ?></span>
                        <span id="bx-co-tot"><?= e(format_bdt((float) $summary['total_amount'])) ?></span>
                    </div>
                </div>
            </div>
        </div>
    </form>
</div>
