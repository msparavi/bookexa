<?php
declare(strict_types=1);

$lang = current_lang();
$cfg = shop_delivery_config();
$shopEnabled = site_setting('shop_enabled', '0') === '1';
$districts = shop_bd_districts();
$overrides = $cfg['district_overrides'];
?>

<form method="post" action="<?= e(url('/admin/delivery-settings')) ?>">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="save_delivery_settings">

    <div class="bx-grid-2" style="align-items:start;margin-bottom:20px;">
        <section class="bx-card">
            <h2 style="margin:0 0 14px;font-size:1.12rem;font-family:var(--font-serif);">
                <?= $lang === 'bn' ? 'শপ ও ক্যাশ অন ডেলিভারি সেটিংস' : 'Storefront & Delivery Rules' ?>
            </h2>

            <div style="display:flex;flex-direction:column;gap:6px;margin-bottom:14px;">
                <label style="display:flex;align-items:center;gap:8px;min-height:44px;font-weight:700;color:var(--primary);">
                    <input type="checkbox" name="shop_enabled" value="1" <?= $shopEnabled ? 'checked' : '' ?>>
                    <?= $lang === 'bn' ? 'মেনু ও সাইটম্যাপে শপ মডিউল চালু রাখুন' : 'Enable Shop links in Navigation & Sitemaps' ?>
                </label>
                <label style="display:flex;align-items:center;gap:8px;min-height:44px;font-weight:700;color:var(--teal);">
                    <input type="checkbox" name="shop_cod_enabled" value="1" <?= $cfg['cod_enabled'] ? 'checked' : '' ?>>
                    <?= $lang === 'bn' ? 'ক্যাশ অন ডেলিভারি (COD) চালু রাখুন' : 'Enable Cash on Delivery (COD)' ?>
                </label>
            </div>

            <div class="bx-grid-2" style="gap:12px;">
                <div class="bx-field">
                    <label><?= $lang === 'bn' ? 'ঢাকার ভেতরে ডেলিভারি চার্জ (৳)' : 'Inside Dhaka Delivery Fee (৳)' ?></label>
                    <input type="number" step="1" min="0" name="shop_delivery_dhaka" class="bx-input" required value="<?= e((string) $cfg['dhaka_fee']) ?>">
                </div>
                <div class="bx-field">
                    <label><?= $lang === 'bn' ? 'ঢাকার বাইরে ডেলিভারি চার্জ (৳)' : 'Outside Dhaka Delivery Fee (৳)' ?></label>
                    <input type="number" step="1" min="0" name="shop_delivery_outside" class="bx-input" required value="<?= e((string) $cfg['outside_fee']) ?>">
                </div>
            </div>

            <div class="bx-grid-2" style="gap:12px;">
                <div class="bx-field">
                    <label><?= $lang === 'bn' ? 'ফ্রি ডেলিভারি ন্যূনতম অর্ডার (৳)' : 'Free Delivery Above Amount (৳)' ?></label>
                    <input type="number" step="1" min="0" name="shop_free_delivery_above" class="bx-input" value="<?= e((string) $cfg['free_delivery_above']) ?>">
                    <small style="color:var(--text-muted);"><?= $lang === 'bn' ? '০ দিলে ফ্রি ডেলিভারি বন্ধ থাকবে।' : 'Set to 0 to disable free delivery threshold.' ?></small>
                </div>
                <div class="bx-field">
                    <label><?= $lang === 'bn' ? 'রিটার্ন অনুরোধ সময়সীমা (দিন)' : 'Customer Return Window (Days)' ?></label>
                    <input type="number" min="1" max="60" name="shop_return_days" class="bx-input" required value="<?= (int) $cfg['return_days'] ?>">
                </div>
            </div>

            <div class="bx-grid-2" style="gap:12px;">
                <div class="bx-field">
                    <label><?= $lang === 'bn' ? 'সম্ভাব্য সময় (ঢাকার ভেতরে)' : 'Estimated Days (Inside Dhaka)' ?></label>
                    <input type="text" name="shop_est_days_dhaka" class="bx-input" required value="<?= e($cfg['est_days_dhaka']) ?>">
                </div>
                <div class="bx-field">
                    <label><?= $lang === 'bn' ? 'সম্ভাব্য সময় (ঢাকার বাইরে)' : 'Estimated Days (Outside Dhaka)' ?></label>
                    <input type="text" name="shop_est_days_outside" class="bx-input" required value="<?= e($cfg['est_days_outside']) ?>">
                </div>
            </div>
        </section>

        <section class="bx-card">
            <h2 style="margin:0 0 8px;font-size:1.12rem;font-family:var(--font-serif);">
                <?= $lang === 'bn' ? 'জেলা-ভিত্তিক বিশেষ চার্জ (ঐচ্ছিক)' : 'Per-District Delivery Overrides' ?>
            </h2>
            <p class="bx-muted" style="margin:0 0 12px;font-size:0.86rem;">
                <?= $lang === 'bn'
                    ? 'কোনো জেলার জন্য আলাদা ডেলিভারি ফি নির্ধারণ করতে চাইলে নিচে যুক্ত করুন।'
                    : 'Optionally set custom delivery charges for specific districts (overrides Inside/Outside Dhaka fee).' ?>
            </p>

            <div id="bx-district-overrides-list" style="display:flex;flex-direction:column;gap:8px;margin-bottom:12px;">
                <?php
                $renderRows = $overrides ?: ['' => ''];
                foreach ($renderRows as $ovDist => $ovFee):
                ?>
                    <div class="bx-district-override-row" style="display:flex;gap:8px;flex-wrap:wrap;align-items:center;">
                        <select name="override_district[]" class="bx-input" style="flex:1 1 170px;min-width:0;">
                            <option value=""><?= $lang === 'bn' ? '— জেলা নির্বাচন করুন —' : '— Select District —' ?></option>
                            <?php foreach ($districts as $dKey => $dLbl): ?>
                                <option value="<?= e($dKey) ?>" <?= $ovDist === $dKey ? 'selected' : '' ?>><?= e($dLbl[$lang] . ' (' . $dKey . ')') ?></option>
                            <?php endforeach; ?>
                        </select>
                        <input type="number" step="1" min="0" name="override_fee[]" class="bx-input" style="width:120px;flex:0 1 120px;" placeholder="Fee ৳" value="<?= $ovFee !== '' ? e((string) $ovFee) : '' ?>">
                        <button type="button" class="bx-btn bx-btn-ghost bx-btn-sm" data-remove-override-row aria-label="Remove">✕</button>
                    </div>
                <?php endforeach; ?>
            </div>

            <button type="button" class="bx-btn bx-btn-outline bx-btn-sm" id="bx-add-district-override">
                + <?= $lang === 'bn' ? 'আরও জেলা যোগ করুন' : 'Add District Rule' ?>
            </button>
        </section>
    </div>

    <div style="display:flex;justify-content:flex-end;">
        <button type="submit" class="bx-btn bx-btn-primary">
            <?= $lang === 'bn' ? 'ডেলিভারি সেটিংস সংরক্ষণ করুন' : 'Save Delivery Settings' ?>
        </button>
    </div>
</form>
