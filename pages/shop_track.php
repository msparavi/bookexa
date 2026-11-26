<?php
declare(strict_types=1);
/** @var ?array $order, array $items, string $codeQuery, string $phoneQuery, ?string $error */
$lang = current_lang();
$steps = ['pending', 'confirmed', 'packed', 'shipped', 'delivered'];
?>
<div class="container" style="padding-top:24px;max-width:820px;">
    <nav class="bx-breadcrumb" aria-label="Breadcrumb">
        <a href="<?= e(url('/')) ?>"><?= e(t('nav_home')) ?></a>
        <?= icon('chevron-right') ?>
        <a href="<?= e(url('/shop')) ?>"><?= e(t('nav_shop')) ?></a>
        <?= icon('chevron-right') ?>
        <span class="current"><?= e(t('nav_track_order')) ?></span>
    </nav>

    <div class="bx-card" style="padding:24px;margin-top:18px;">
        <h1 class="bx-h2" style="margin-bottom:6px;"><?= $lang === 'bn' ? 'শপ অর্ডার ট্র্যাক করুন' : 'Track Your Book Order' ?></h1>
        <p class="bx-muted" style="margin:0 0 16px;"><?= $lang === 'bn' ? 'আপনার অর্ডার কোড এবং মোবাইল নম্বর দিয়ে বর্তমান অবস্থা দেখুন।' : 'Enter your order code and phone number to view live delivery status.' ?></p>

        <form method="get" action="<?= e(url('/shop/track')) ?>" style="display:grid;grid-template-columns:repeat(auto-fit,minmax(min(100%,180px),1fr));gap:10px;align-items:end;">
            <div>
                <label style="display:block;font-weight:600;font-size:0.84rem;margin-bottom:4px;"><?= $lang === 'bn' ? 'অর্ডার কোড *' : 'Order Code *' ?></label>
                <input type="text" name="code" class="bx-input" required placeholder="SHP-2026...-XXXX" value="<?= e($codeQuery) ?>">
            </div>
            <div>
                <label style="display:block;font-weight:600;font-size:0.84rem;margin-bottom:4px;"><?= $lang === 'bn' ? 'ফোন নম্বর' : 'Phone Number' ?></label>
                <input type="tel" name="phone" class="bx-input" placeholder="01XXXXXXXXX" value="<?= e($phoneQuery) ?>">
            </div>
            <button type="submit" class="bx-btn bx-btn-primary"><?= icon('search') ?> <?= $lang === 'bn' ? 'ট্র্যাক করুন' : 'Track Order' ?></button>
        </form>

        <?php if (!empty($error)): ?>
            <div class="bx-alert bx-alert-danger" style="margin-top:16px;padding:12px 14px;border-radius:12px;background:var(--bg-alt);border-left:4px solid var(--rose);color:var(--rose-text);font-weight:600;">
                <?= e($error) ?>
            </div>
        <?php endif; ?>
    </div>

    <?php if ($order):
        $curSt = (string) $order['status'];
        $curIdx = array_search($curSt, $steps, true);
        $stMeta = shop_order_status_labels($curSt);
    ?>
        <div class="bx-card" style="padding:24px;margin-top:18px;">
            <div style="display:flex;justify-content:space-between;align-items:center;gap:10px;flex-wrap:wrap;margin-bottom:18px;">
                <div>
                    <strong style="font-size:1.15rem;">#<?= e($order['order_code']) ?></strong>
                    <div class="bx-muted" style="font-size:0.85rem;"><?= e(date('d M Y, h:i A', strtotime((string) $order['created_at']))) ?></div>
                </div>
                <div style="display:flex;gap:8px;align-items:center;flex-wrap:wrap;">
                    <span class="bx-badge <?= e($stMeta['badge']) ?>"><?= e($lang === 'bn' ? $stMeta['bn'] : $stMeta['en']) ?></span>
                    <a href="<?= e(url('/shop/orders/' . rawurlencode((string) $order['order_code']) . '/invoice')) ?>" target="_blank" rel="noopener" class="bx-btn bx-btn-outline bx-btn-sm">
                        <?= icon('printer') ?> <?= $lang === 'bn' ? 'ইনভয়েস' : 'Invoice' ?>
                    </a>
                </div>
            </div>

            <?php if ($curIdx !== false): ?>
                <div class="bx-track-steps">
                    <?php foreach ($steps as $idx => $sKey): $sLbl = shop_order_status_labels($sKey); $done = ($idx <= $curIdx); ?>
                        <div class="bx-track-step <?= $done ? 'is-done' : '' ?>">
                            <div class="bx-track-dot"><?= $done ? '✓' : ($idx + 1) ?></div>
                            <span><?= e($lang === 'bn' ? $sLbl['bn'] : $sLbl['en']) ?></span>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <?php if (!empty($order['courier_name']) || !empty($order['tracking_code'])): ?>
                <div style="margin-top:16px;padding:12px 14px;border-radius:12px;background:var(--bg-alt);border:1px solid var(--border);">
                    <strong><?= $lang === 'bn' ? 'কুরিয়ার ও ট্র্যাকিং:' : 'Courier & Tracking:' ?></strong>
                    <?= e((string) ($order['courier_name'] ?? 'Courier')) ?>
                    <?php if (!empty($order['tracking_code'])): ?>
                        • <strong><?= e((string) $order['tracking_code']) ?></strong>
                    <?php endif; ?>
                </div>
            <?php endif; ?>

            <?php if (!empty($order['admin_note'])): ?>
                <div style="margin-top:10px;font-size:0.9rem;"><strong><?= $lang === 'bn' ? 'বার্তা:' : 'Note:' ?></strong> <?= e((string) $order['admin_note']) ?></div>
            <?php endif; ?>

            <div style="margin-top:18px;padding-top:14px;border-top:1px solid var(--border);">
                <div style="font-weight:700;margin-bottom:8px;"><?= $lang === 'bn' ? 'অর্ডারকৃত বইসমূহ' : 'Order Items' ?></div>
                <?php foreach ($items as $it): ?>
                    <div style="display:flex;justify-content:space-between;gap:8px;margin-bottom:4px;">
                        <span><?= (int) $it['quantity'] ?>× <?= e(bi($it, 'title')) ?></span>
                        <strong><?= e(format_bdt((float) $it['line_total'])) ?></strong>
                    </div>
                <?php endforeach; ?>
                <div style="display:flex;justify-content:space-between;padding-top:8px;margin-top:8px;border-top:1px dashed var(--border);font-weight:800;color:var(--primary);">
                    <span><?= $lang === 'bn' ? 'মোট মূল্য' : 'Total' ?></span>
                    <span><?= e(format_bdt((float) $order['total_amount'])) ?></span>
                </div>
            </div>
        </div>
    <?php endif; ?>
</div>
