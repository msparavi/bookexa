<?php
declare(strict_types=1);

$lang = current_lang();
$coupons = db_all(
    "SELECT c.*,
            (SELECT COUNT(*) FROM coupon_items ci WHERE ci.coupon_id = c.id) AS item_count
     FROM coupons c
     ORDER BY c.id DESC"
);
$allItems = db_all('SELECT id, title_bn, title_en, price, is_free FROM items ORDER BY title_en ASC');

$editId = !empty($_GET['edit']) ? (int) $_GET['edit'] : null;
$editCoupon = $editId ? db_one('SELECT * FROM coupons WHERE id = ?', [$editId]) : null;
$selectedItemIds = [];
if ($editCoupon) {
    foreach (db_all('SELECT item_id FROM coupon_items WHERE coupon_id = ?', [(int) $editCoupon['id']]) as $ci) {
        $selectedItemIds[(int) $ci['item_id']] = true;
    }
}
$expVal = '';
if (!empty($editCoupon['expires_at'])) {
    $ts = strtotime((string) $editCoupon['expires_at']);
    if ($ts !== false) {
        $expVal = date('Y-m-d\TH:i', $ts);
    }
}
?>

<section class="bx-card" style="margin-bottom:22px;">
    <div style="display:flex;justify-content:space-between;align-items:center;gap:10px;flex-wrap:wrap;margin-bottom:14px;">
        <h2 style="margin:0;font-size:1.1rem;font-family:var(--font-serif);"><?= $lang === 'bn' ? 'কুপন কোডসমূহ' : 'Discount Coupons' ?> (<?= count($coupons) ?>)</h2>
        <a href="<?= e(url('/admin/coupons#coupon-form')) ?>" class="bx-btn bx-btn-primary">+ <?= $lang === 'bn' ? 'নতুন কুপন' : 'New Coupon' ?></a>
    </div>

    <div class="bx-table-wrap">
        <table class="bx-table bx-mobile-cards">
            <thead>
                <tr>
                    <th><?= $lang === 'bn' ? 'কোড' : 'Code' ?></th>
                    <th><?= $lang === 'bn' ? 'ছাড়' : 'Discount' ?></th>
                    <th><?= $lang === 'bn' ? 'শর্ত / পরিধি' : 'Limits / Scope' ?></th>
                    <th><?= $lang === 'bn' ? 'ব্যবহার' : 'Usage' ?></th>
                    <th><?= $lang === 'bn' ? 'অবস্থা' : 'Status' ?></th>
                    <th><?= $lang === 'bn' ? 'অ্যাকশন' : 'Actions' ?></th>
                </tr>
            </thead>
            <tbody>
                <?php if (!$coupons): ?>
                    <tr class="bx-empty-row"><td colspan="6" style="color:var(--text-muted);padding:24px;"><?= $lang === 'bn' ? 'এখনও কোনো কুপন তৈরি হয়নি।' : 'No coupons created yet.' ?></td></tr>
                <?php else: foreach ($coupons as $c): ?>
                    <?php
                    $cid = (int) $c['id'];
                    $active = (int) $c['is_active'] === 1;
                    $expired = !empty($c['expires_at']) && strtotime((string) $c['expires_at']) < time();
                    ?>
                    <tr>
                        <td class="bx-td-primary">
                            <strong style="letter-spacing:0.04em;"><?= e($c['code']) ?></strong>
                            <?php if (!empty($c['expires_at'])): ?>
                                <div style="font-size:0.78rem;color:<?= $expired ? 'var(--rose-text)' : 'var(--text-muted)' ?>;">
                                    <?= $expired ? ($lang === 'bn' ? 'মেয়াদোত্তীর্ণ: ' : 'Expired: ') : ($lang === 'bn' ? 'মেয়াদ: ' : 'Expires: ') ?><?= e(date('d M Y H:i', strtotime((string) $c['expires_at']))) ?>
                                </div>
                            <?php endif; ?>
                        </td>
                        <td data-label="<?= e($lang === 'bn' ? 'ছাড়' : 'Discount') ?>">
                            <strong><?= $c['discount_type'] === 'percent' ? ((float) $c['discount_value'] . '%') : ('৳' . number_format((float) $c['discount_value'], 0)) ?></strong>
                            <?php if ((float) $c['min_amount'] > 0): ?>
                                <div style="font-size:0.78rem;color:var(--text-muted);">Min ৳<?= number_format((float) $c['min_amount'], 0) ?></div>
                            <?php endif; ?>
                        </td>
                        <td data-label="<?= e($lang === 'bn' ? 'পরিধি' : 'Scope') ?>">
                            <span>
                                <?= (int) $c['applies_to_all'] ? ($lang === 'bn' ? 'সব PDF' : 'All PDFs') : ((int) $c['item_count'] . ' ' . ($lang === 'bn' ? 'টি PDF' : 'PDF(s)')) ?>
                                · <?= (int) $c['per_user_limit'] ?>/user
                            </span>
                        </td>
                        <td data-label="<?= e($lang === 'bn' ? 'ব্যবহার' : 'Usage') ?>">
                            <strong><?= (int) $c['used_count'] ?></strong> / <?= $c['usage_limit'] !== null ? (int) $c['usage_limit'] : '∞' ?>
                        </td>
                        <td data-label="<?= e($lang === 'bn' ? 'অবস্থা' : 'Status') ?>">
                            <form method="post" action="<?= e(url('/admin/coupons')) ?>" style="margin:0;">
                                <?= csrf_field() ?>
                                <input type="hidden" name="action" value="toggle_coupon">
                                <input type="hidden" name="id" value="<?= $cid ?>">
                                <button type="submit" class="bx-switch-btn <?= $active ? 'is-on' : '' ?>" aria-pressed="<?= $active ? 'true' : 'false' ?>">
                                    <span class="bx-switch-dot"></span>
                                    <span><?= $active ? ($lang === 'bn' ? 'সক্রিয়' : 'Active') : ($lang === 'bn' ? 'নিষ্ক্রিয়' : 'Off') ?></span>
                                </button>
                            </form>
                        </td>
                        <td class="bx-td-actions">
                            <a href="<?= e(url('/admin/coupons?edit=' . $cid . '#coupon-form')) ?>" class="bx-btn bx-btn-outline" style="padding:8px 12px;font-size:0.84rem;"><?= $lang === 'bn' ? 'সম্পাদনা' : 'Edit' ?></a>
                            <form method="post" action="<?= e(url('/admin/coupons')) ?>" data-confirm-msg="<?= e($lang === 'bn' ? 'এই কুপনটি মুছে ফেলবেন?' : 'Delete this coupon?') ?>" style="margin:0;">
                                <?= csrf_field() ?>
                                <input type="hidden" name="action" value="delete_coupon">
                                <input type="hidden" name="id" value="<?= $cid ?>">
                                <button type="submit" class="bx-btn bx-btn-ghost" style="padding:8px 12px;font-size:0.84rem;color:var(--rose-text);"><?= $lang === 'bn' ? 'মুছুন' : 'Delete' ?></button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
</section>

<section class="bx-card" id="coupon-form">
    <h2 style="margin:0 0 14px;font-size:1.12rem;font-family:var(--font-serif);">
        <?= $editCoupon ? ($lang === 'bn' ? 'কুপন সম্পাদনা' : 'Edit Coupon') : ($lang === 'bn' ? 'নতুন কুপন তৈরি করুন' : 'Create Coupon') ?>
    </h2>
    <form method="post" action="<?= e(url('/admin/coupons')) ?>" class="bx-track-dirty">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="save_coupon">
        <?php if ($editCoupon): ?>
            <input type="hidden" name="id" value="<?= (int) $editCoupon['id'] ?>">
        <?php endif; ?>

        <div class="bx-grid-3" style="margin-bottom:12px;">
            <div>
                <label style="display:block;font-weight:600;margin-bottom:4px;"><?= $lang === 'bn' ? 'কুপন কোড *' : 'Coupon Code *' ?></label>
                <input type="text" name="code" class="bx-input" required style="text-transform:uppercase;" placeholder="BOOK20" value="<?= e((string) ($editCoupon['code'] ?? '')) ?>">
            </div>
            <div>
                <label style="display:block;font-weight:600;margin-bottom:4px;"><?= $lang === 'bn' ? 'ছাড়ের ধরন *' : 'Discount Type *' ?></label>
                <select name="discount_type" class="bx-input">
                    <option value="percent" <?= ($editCoupon['discount_type'] ?? 'percent') === 'percent' ? 'selected' : '' ?>><?= $lang === 'bn' ? 'শতাংশ (%)' : 'Percent (%)' ?></option>
                    <option value="fixed" <?= ($editCoupon['discount_type'] ?? '') === 'fixed' ? 'selected' : '' ?>><?= $lang === 'bn' ? 'নির্দিষ্ট পরিমাণ (৳)' : 'Fixed Amount (৳)' ?></option>
                </select>
            </div>
            <div>
                <label style="display:block;font-weight:600;margin-bottom:4px;"><?= $lang === 'bn' ? 'ছাড়ের পরিমাণ *' : 'Discount Value *' ?></label>
                <input type="number" step="0.01" min="0.01" name="discount_value" class="bx-input" required value="<?= e((string) ($editCoupon['discount_value'] ?? '20')) ?>">
            </div>
        </div>

        <div class="bx-grid-2" style="margin-bottom:12px;">
            <div>
                <label style="display:block;font-weight:600;margin-bottom:4px;"><?= $lang === 'bn' ? 'ন্যূনতম অর্ডার (৳)' : 'Minimum Order Amount (৳)' ?></label>
                <input type="number" step="0.01" min="0" name="min_amount" class="bx-input" value="<?= e((string) ($editCoupon['min_amount'] ?? '0')) ?>">
            </div>
            <div>
                <label style="display:block;font-weight:600;margin-bottom:4px;"><?= $lang === 'bn' ? 'মেয়াদ শেষের তারিখ' : 'Expiry Date (Optional)' ?></label>
                <input type="datetime-local" name="expires_at" class="bx-input" value="<?= e($expVal) ?>">
            </div>
        </div>

        <div class="bx-grid-2" style="margin-bottom:12px;">
            <div>
                <label style="display:block;font-weight:600;margin-bottom:4px;"><?= $lang === 'bn' ? 'মোট ব্যবহার সীমা (খালি = সীমাহীন)' : 'Total Usage Limit (Blank = Unlimited)' ?></label>
                <input type="number" min="1" name="usage_limit" class="bx-input" value="<?= e((string) ($editCoupon['usage_limit'] ?? '')) ?>">
            </div>
            <div>
                <label style="display:block;font-weight:600;margin-bottom:4px;"><?= $lang === 'bn' ? 'প্রতি ব্যবহারকারী সীমা *' : 'Per-User Limit *' ?></label>
                <input type="number" min="1" name="per_user_limit" class="bx-input" required value="<?= e((string) ($editCoupon['per_user_limit'] ?? '1')) ?>">
            </div>
        </div>

        <div style="display:flex;gap:18px;flex-wrap:wrap;margin-bottom:12px;">
            <label style="display:flex;align-items:center;gap:8px;min-height:44px;font-weight:600;">
                <input type="checkbox" id="bx-coupon-all-pdfs" name="applies_to_all" value="1" <?= !$editCoupon || (int) $editCoupon['applies_to_all'] ? 'checked' : '' ?>>
                <?= $lang === 'bn' ? 'সকল PDF বইয়ে প্রযোজ্য' : 'Apply to all PDFs' ?>
            </label>
            <label style="display:flex;align-items:center;gap:8px;min-height:44px;font-weight:600;">
                <input type="checkbox" name="is_active" value="1" <?= !$editCoupon || (int) $editCoupon['is_active'] ? 'checked' : '' ?>>
                <?= $lang === 'bn' ? 'সক্রিয় কুপন' : 'Active' ?>
            </label>
        </div>

        <div id="bx-coupon-items-box" style="margin-bottom:16px;padding:12px;border-radius:12px;background:var(--bg-alt);border:1px solid var(--border);<?= !$editCoupon || (int) $editCoupon['applies_to_all'] ? 'display:none;' : '' ?>">
            <strong style="display:block;margin-bottom:8px;font-size:0.9rem;"><?= $lang === 'bn' ? 'নির্দিষ্ট PDF নির্বাচন করুন:' : 'Select Specific Applicable PDFs:' ?></strong>
            <div class="bx-grid-2" style="gap:8px;">
                <?php foreach ($allItems as $it): ?>
                    <label style="display:flex;align-items:center;gap:8px;min-height:40px;font-size:0.88rem;">
                        <input type="checkbox" name="item_ids[]" value="<?= (int) $it['id'] ?>" <?= isset($selectedItemIds[(int) $it['id']]) ? 'checked' : '' ?>>
                        <span><?= e($lang === 'bn' ? $it['title_bn'] : $it['title_en']) ?> (<?= (int) $it['is_free'] ? 'Free' : '৳' . number_format((float) $it['price'], 0) ?>)</span>
                    </label>
                <?php endforeach; ?>
            </div>
        </div>

        <div class="bx-sticky-form-actions">
            <button type="submit" class="bx-btn bx-btn-primary"><?= $editCoupon ? ($lang === 'bn' ? 'কুপন আপডেট করুন' : 'Update Coupon') : ($lang === 'bn' ? 'কুপন সংরক্ষণ করুন' : 'Save Coupon') ?></button>
            <?php if ($editCoupon): ?>
                <a href="<?= e(url('/admin/coupons')) ?>" class="bx-btn bx-btn-ghost"><?= $lang === 'bn' ? 'বাতিল' : 'Cancel' ?></a>
            <?php endif; ?>
        </div>
    </form>
</section>
