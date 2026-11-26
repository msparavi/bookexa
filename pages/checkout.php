<?php
declare(strict_types=1);
/** @var array $item, ?string $error, ?array $appliedCoupon */
$lang = current_lang();
$bkash  = site_setting('bkash_number', '01711-000111');
$nagad  = site_setting('nagad_number', '01811-000222');
$rocket = site_setting('rocket_number', '01911-000333');
$instr  = $lang === 'bn' ? site_setting('payment_instructions_bn') : site_setting('payment_instructions_en');
$copyrightNotice = strip_tags(
    $lang === 'bn' ? site_setting('legal_copyright_bn') : site_setting('legal_copyright_en'),
    '<p><strong><em><br>'
);
$basePrice = (float) $item['price'];
$discAmt = (float) ($appliedCoupon['discount_amount'] ?? 0);
$finalAmt = (float) ($appliedCoupon['final_amount'] ?? $basePrice);
$appliedCode = (string) ($appliedCoupon['code'] ?? ($_POST['coupon_code'] ?? ''));
?>
<div class="container" style="padding-top:24px;max-width:740px;">
    <nav class="bx-breadcrumb" aria-label="Breadcrumb">
        <a href="<?= e(url('/')) ?>"><?= e(t('nav_home')) ?></a>
        <?= icon('chevron-right') ?>
        <a href="<?= e(url('/book/' . rawurlencode($item['slug']))) ?>"><?= e(bi($item, 'title')) ?></a>
        <?= icon('chevron-right') ?>
        <span class="current"><?= $lang === 'bn' ? 'অর্ডার ও পেমেন্ট' : 'Order & Payment' ?></span>
    </nav>

    <div class="bx-card" style="margin-top:20px;">
        <h1 class="bx-h2" style="margin-bottom:6px;"><?= $lang === 'bn' ? 'ম্যানুয়াল পেমেন্ট ও অর্ডার' : 'Order & Manual Payment' ?></h1>
        <p class="bx-muted" style="margin:0 0 18px;"><?= e(bi($item, 'title')) ?> — <strong style="color:var(--gold-text);"><?= e(format_bdt($basePrice)) ?></strong></p>

        <?php if (!empty($error)): ?><div style="margin-bottom:14px;padding:10px 14px;border-radius:10px;border-left:4px solid var(--danger);background:var(--bg-alt);"><?= e($error) ?></div><?php endif; ?>

        <div style="padding:16px;border-radius:14px;background:var(--bg-alt);border:1px solid var(--border);margin-bottom:18px;">
            <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(min(100%,180px),1fr));gap:10px;margin-bottom:10px;">
                <div><strong>bKash:</strong> <?= e($bkash) ?></div>
                <div><strong>Nagad:</strong> <?= e($nagad) ?></div>
                <div><strong>Rocket:</strong> <?= e($rocket) ?></div>
            </div>
            <p class="bx-muted" style="margin:0;font-size:0.9rem;"><?= e($instr) ?></p>
        </div>

        <!-- Live Coupon Box + Price Summary -->
        <div class="bx-coupon-box" id="bx-checkout-coupon-box" data-item-id="<?= (int) $item['id'] ?>" data-base-price="<?= e((string) $basePrice) ?>">
            <label for="coupon_input" style="display:block;font-weight:700;margin-bottom:6px;"><?= $lang === 'bn' ? 'কুপন কোড (ঐচ্ছিক)' : 'Coupon Code (Optional)' ?></label>
            <div class="bx-coupon-row">
                <input type="text" class="bx-input" id="coupon_input" value="<?= e($appliedCode) ?>" placeholder="<?= $lang === 'bn' ? 'যেমন: BOOK20' : 'e.g. BOOK20' ?>" autocomplete="off">
                <button type="button" class="bx-btn bx-btn-outline" id="btn-apply-coupon"><?= $lang === 'bn' ? 'কুপন প্রয়োগ' : 'Apply coupon' ?></button>
            </div>
            <div id="coupon-feedback" style="margin-top:6px;font-size:0.88rem;font-weight:600;"><?= !empty($appliedCoupon['message']) ? e($appliedCoupon['message']) : '' ?></div>

            <div class="bx-price-summary">
                <div>
                    <span><?= $lang === 'bn' ? 'মূল মূল্য' : 'Original Price' ?></span>
                    <strong id="sum-base"><?= e(format_bdt($basePrice)) ?></strong>
                </div>
                <div id="sum-disc-row"<?= $discAmt > 0 ? '' : ' style="display:none;"' ?>>
                    <span><?= $lang === 'bn' ? 'কুপন ছাড়' : 'Coupon Discount' ?></span>
                    <strong id="sum-disc" style="color:var(--teal-text);">-<?= e(format_bdt($discAmt)) ?></strong>
                </div>
                <div class="bx-summary-total">
                    <span><?= $lang === 'bn' ? 'পরিশোধযোগ্য মোট' : 'Payable Total' ?></span>
                    <strong id="sum-total" style="color:var(--gold-text);"><?= e(format_bdt($finalAmt)) ?></strong>
                </div>
            </div>
        </div>

        <form method="post" action="<?= e(url('/checkout/' . rawurlencode($item['slug']))) ?>" enctype="multipart/form-data" style="margin-top:18px;" data-loading-form>
            <?= csrf_field() ?>
            <input type="hidden" name="coupon_code" id="checkout_coupon_hidden" value="<?= e($appliedCode) ?>">
            <?php
            $gwDriver = payment_gateway_driver();
            $useAutoGw = $gwDriver->isAutomatic();
            ?>
            <?php if ($useAutoGw): ?>
                <div id="bx-manual-pay-fields" style="<?= $finalAmt <= 0 ? 'display:none;' : '' ?>padding:14px;border-radius:12px;background:var(--bg-alt);border:1px solid var(--border);margin-bottom:14px;">
                    <strong><?= e($gwDriver->getName()) ?></strong>
                    <p class="bx-muted" style="margin:6px 0 0;font-size:0.88rem;">
                        <?= $lang === 'bn' ? 'অর্ডার জমা দেওয়ার পর আপনি নিরাপদ অনলাইন পেমেন্ট পেজে চলে যাবেন।' : 'After submitting, you will be redirected to complete payment securely online.' ?>
                    </p>
                </div>
            <?php else: ?>
                <div id="bx-manual-pay-fields" style="<?= $finalAmt <= 0 ? 'display:none;' : '' ?>">
                    <div class="bx-field">
                        <label for="payment_method"><?= $lang === 'bn' ? 'পেমেন্ট মাধ্যম *' : 'Payment Method *' ?></label>
                        <select class="bx-select" id="payment_method" name="payment_method" <?= $finalAmt > 0 ? 'required' : '' ?>>
                            <option value="bkash" <?= ($_POST['payment_method'] ?? '') === 'bkash' ? 'selected' : '' ?>>bKash</option>
                            <option value="nagad" <?= ($_POST['payment_method'] ?? '') === 'nagad' ? 'selected' : '' ?>>Nagad</option>
                            <option value="rocket" <?= ($_POST['payment_method'] ?? '') === 'rocket' ? 'selected' : '' ?>>Rocket</option>
                        </select>
                    </div>
                    <div class="bx-field">
                        <label for="sender_number"><?= $lang === 'bn' ? 'আপনার প্রেরক নম্বর *' : 'Sender Phone Number *' ?></label>
                        <input class="bx-input" id="sender_number" name="sender_number" <?= $finalAmt > 0 ? 'required' : '' ?> placeholder="01XXXXXXXXX" value="<?= e($_POST['sender_number'] ?? '') ?>">
                    </div>
                    <div class="bx-field">
                        <label for="transaction_id">Transaction ID (TrxID) *</label>
                        <input class="bx-input" id="transaction_id" name="transaction_id" <?= $finalAmt > 0 ? 'required' : '' ?> placeholder="e.g. 9K7A2B1C3D" value="<?= e($_POST['transaction_id'] ?? '') ?>">
                    </div>
                    <div class="bx-field">
                        <label for="payment_screenshot"><?= $lang === 'bn' ? 'পেমেন্ট স্ক্রিনশট (ঐচ্ছিক — JPG/PNG/WebP, সর্বোচ্চ ৩ MB)' : 'Payment Screenshot (Optional — JPG/PNG/WebP, max 3 MB)' ?></label>
                        <input class="bx-input" type="file" id="payment_screenshot" name="payment_screenshot" accept="image/jpeg,image/png,image/webp">
                    </div>
                </div>
            <?php endif; ?>

            <div class="bx-security-box" style="margin:14px 0;">
                <?= icon('shield', 'bx-icon-lg') ?>
                <div style="font-size:0.88rem;line-height:1.55;">
                    <?= $copyrightNotice ?>
                </div>
            </div>

            <label class="bx-agree-row">
                <input type="checkbox" name="agree_legal" value="1" required <?= !empty($_POST['agree_legal']) ? 'checked' : '' ?>>
                <span>
                    <?= $lang === 'bn' ? 'আমি সম্মত আছি (I agree): ' : 'I agree to the ' ?>
                    <a href="<?= e(url('/terms')) ?>" target="_blank" rel="noopener"><?= e(t('legal_terms')) ?></a>,
                    <a href="<?= e(url('/privacy')) ?>" target="_blank" rel="noopener"><?= e(t('legal_privacy')) ?></a>,
                    <a href="<?= e(url('/refund-policy')) ?>" target="_blank" rel="noopener"><?= e(t('legal_refund')) ?></a>
                    <?= $lang === 'bn' ? 'এবং ' : 'and ' ?>
                    <a href="<?= e(url('/copyright')) ?>" target="_blank" rel="noopener"><?= e(t('legal_copyright')) ?></a>.
                </span>
            </label>

            <button type="submit" id="checkout-submit-btn" class="bx-btn bx-btn-accent" style="width:100%;" data-free-label="<?= e($lang === 'bn' ? 'ফ্রি আনলক করুন (১০০% ছাড়)' : 'Unlock Now (Free with Coupon)') ?>" data-paid-label="<?= e($lang === 'bn' ? 'পেমেন্ট তথ্য জমা দিন' : 'Submit Order for Verification') ?>" data-loading-label="<?= e($lang === 'bn' ? 'জমা হচ্ছে...' : 'Submitting...') ?>">
                <?= icon('check') ?> <span><?= $finalAmt <= 0 ? ($lang === 'bn' ? 'ফ্রি আনলক করুন (১০০% ছাড়)' : 'Unlock Now (Free with Coupon)') : ($lang === 'bn' ? 'পেমেন্ট তথ্য জমা দিন' : 'Submit Order for Verification') ?></span>
            </button>
        </form>
    </div>
</div>
