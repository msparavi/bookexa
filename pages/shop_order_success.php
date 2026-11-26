<?php
declare(strict_types=1);
/** @var array $order, array $items */
$lang = current_lang();
$code = (string) $order['order_code'];
$stMeta = shop_order_status_labels((string) $order['status']);
?>
<div class="container" style="padding-top:24px;max-width:780px;">
    <div class="bx-card" style="padding:28px;text-align:center;">
        <div style="width:62px;height:62px;margin:0 auto 14px;border-radius:999px;background:rgba(20,184,166,0.16);color:var(--teal);display:flex;align-items:center;justify-content:center;">
            <?= icon('check', 'bx-icon-lg') ?>
        </div>
        <h1 class="bx-display" style="font-size:1.75rem;margin-bottom:8px;">
            <?= $lang === 'bn' ? 'ধন্যবাদ! আপনার অর্ডার গৃহীত হয়েছে' : 'Thank You! Your Order Is Confirmed' ?>
        </h1>
        <p class="bx-muted" style="margin:0 0 18px;">
            <?= $lang === 'bn' ? 'অর্ডার কোড সংরক্ষণ করুন — এর মাধ্যমে অর্ডার ট্র্যাক করতে পারবেন।' : 'Save your order code to track delivery progress anytime.' ?>
        </p>

        <div style="display:inline-flex;gap:10px;align-items:center;padding:10px 18px;border-radius:12px;background:var(--bg-alt);border:1px solid var(--border);margin-bottom:20px;">
            <span class="bx-muted"><?= $lang === 'bn' ? 'অর্ডার কোড:' : 'Order Code:' ?></span>
            <strong style="font-size:1.15rem;color:var(--primary);">#<?= e($code) ?></strong>
            <span class="bx-badge <?= e($stMeta['badge']) ?>"><?= e($lang === 'bn' ? $stMeta['bn'] : $stMeta['en']) ?></span>
        </div>

        <div style="text-align:left;padding:16px;border-radius:12px;background:var(--bg-alt);border:1px solid var(--border);margin-bottom:20px;">
            <div style="font-weight:700;margin-bottom:8px;"><?= $lang === 'bn' ? 'অর্ডার বিবরণ' : 'Order Summary' ?></div>
            <?php foreach ($items as $it): ?>
                <div style="display:flex;justify-content:space-between;gap:8px;margin-bottom:4px;font-size:0.92rem;">
                    <span><?= (int) $it['quantity'] ?>× <?= e(bi($it, 'title')) ?></span>
                    <strong><?= e(format_bdt((float) $it['line_total'])) ?></strong>
                </div>
            <?php endforeach; ?>
            <div style="display:flex;justify-content:space-between;padding-top:8px;margin-top:8px;border-top:1px dashed var(--border);font-weight:800;color:var(--primary);">
                <span><?= $lang === 'bn' ? 'মোট (ডেলিভারি সহ)' : 'Total Payable' ?> (<?= e(strtoupper((string) $order['payment_method'])) ?>)</span>
                <span><?= e(format_bdt((float) $order['total_amount'])) ?></span>
            </div>
        </div>

        <div style="display:flex;justify-content:center;gap:10px;flex-wrap:wrap;">
            <a href="<?= e(url('/shop/track?code=' . rawurlencode($code))) ?>" class="bx-btn bx-btn-primary"><?= icon('truck') ?> <?= $lang === 'bn' ? 'অর্ডার ট্র্যাক করুন' : 'Track Order' ?></a>
            <a href="<?= e(url('/shop/orders/' . rawurlencode($code) . '/invoice')) ?>" target="_blank" rel="noopener" class="bx-btn bx-btn-outline"><?= icon('printer') ?> <?= $lang === 'bn' ? 'ইনভয়েস প্রিন্ট' : 'Print Invoice' ?></a>
            <a href="<?= e(url('/shop')) ?>" class="bx-btn bx-btn-ghost"><?= $lang === 'bn' ? 'আরও বই কিনুন' : 'Continue Shopping' ?></a>
        </div>
    </div>
</div>
