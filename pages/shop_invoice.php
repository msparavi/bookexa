<?php
declare(strict_types=1);
/** @var array $order, array $items */
require_once APP_ROOT . '/includes/qrcode.php';
$lang = current_lang();
$siteName = site_setting('site_name', 'Bookexa');
$siteEmail = site_setting('contact_email', 'support@bookexa.com');
$sitePhone = site_setting('contact_phone', '');
$siteAddr = site_setting('contact_address', 'Dhaka, Bangladesh');
$stMeta = shop_order_status_labels((string) $order['status']);
$trackUrl = BASE_URL . '/shop/track?code=' . rawurlencode((string) $order['order_code']);
?>
<!DOCTYPE html>
<html lang="<?= e($lang) ?>" data-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow">
    <title><?= e($lang === 'bn' ? 'ইনভয়েস #' : 'Invoice #') ?><?= e($order['order_code']) ?> — <?= e($siteName) ?></title>
    <link rel="stylesheet" href="<?= e(asset('assets/css/fonts.css')) ?>">
    <link rel="stylesheet" href="<?= e(asset('assets/css/tokens.css')) ?>">
    <link rel="stylesheet" href="<?= e(asset('assets/css/components.css')) ?>">
    <style>
        body { background: #f8fafc; color: #111827; padding: 20px; }
        .bx-inv { max-width: 820px; margin: 0 auto; background: #fff; border: 1px solid #cbd5e1; border-radius: 14px; padding: 28px; }
        .bx-inv-table { width: 100%; border-collapse: collapse; margin-top: 16px; }
        .bx-inv-table th, .bx-inv-table td { border-bottom: 1px solid #e2e8f0; padding: 10px 8px; text-align: left; }
        .bx-inv-table th { background: #f8fafc; font-size: 0.84rem; color: #475569; }
        @media print {
            body { background: #fff; padding: 0; }
            .bx-no-print { display: none !important; }
            .bx-inv { border: none; padding: 0; }
        }
    </style>
</head>
<body>
<div class="bx-no-print" style="max-width:820px;margin:0 auto 14px;display:flex;justify-content:space-between;align-items:center;gap:10px;flex-wrap:wrap;">
    <a href="<?= e(url('/shop/track?code=' . rawurlencode((string) $order['order_code']))) ?>" class="bx-btn bx-btn-outline bx-btn-sm">← <?= $lang === 'bn' ? 'অর্ডার ট্র্যাক' : 'Back to Track' ?></a>
    <button type="button" class="bx-btn bx-btn-primary bx-btn-sm" data-print-btn><?= $lang === 'bn' ? 'ইনভয়েস প্রিন্ট / PDF সেভ' : 'Print Invoice / Save PDF' ?></button>
</div>

<div class="bx-inv">
    <div style="display:flex;justify-content:space-between;align-items:flex-start;gap:16px;flex-wrap:wrap;border-bottom:2px solid #e2e8f0;padding-bottom:16px;margin-bottom:18px;">
        <div>
            <h1 style="margin:0 0 6px;font-size:1.6rem;color:#232766;"><?= e($siteName) ?></h1>
            <div style="font-size:0.88rem;color:#475569;line-height:1.5;">
                <?= e($siteAddr) ?><br>
                <?= e($siteEmail) ?> <?= $sitePhone ? '• ' . e($sitePhone) : '' ?>
            </div>
        </div>
        <div style="display:flex;align-items:center;gap:14px;">
            <div style="text-align:right;">
                <div style="font-size:0.78rem;text-transform:uppercase;color:#64748b;font-weight:700;"><?= $lang === 'bn' ? 'শপ ইনভয়েস' : 'RETAIL INVOICE' ?></div>
                <strong style="font-size:1.15rem;">#<?= e($order['order_code']) ?></strong><br>
                <small style="color:#64748b;"><?= e(date('d M Y, h:i A', strtotime((string) $order['created_at']))) ?></small><br>
                <span style="display:inline-block;margin-top:4px;padding:3px 9px;border-radius:999px;background:#e0f2fe;color:#0369a1;font-size:0.78rem;font-weight:700;">
                    <?= e($lang === 'bn' ? $stMeta['bn'] : $stMeta['en']) ?>
                </span>
            </div>
            <?= render_qr_svg($trackUrl, 76) ?>
        </div>
    </div>

    <div class="bx-grid-2" style="gap:16px;margin-bottom:18px;">
        <div style="padding:12px 14px;border:1px solid #e2e8f0;border-radius:10px;">
            <div style="font-size:0.76rem;text-transform:uppercase;color:#64748b;font-weight:700;margin-bottom:4px;"><?= $lang === 'bn' ? 'ক্রেতা ও ডেলিভারি ঠিকানা' : 'BILL TO & SHIPPING' ?></div>
            <strong><?= e($order['customer_name']) ?></strong><br>
            <?= e($order['customer_phone']) ?><?= !empty($order['customer_email']) ? (' • ' . e($order['customer_email'])) : '' ?><br>
            <span style="white-space:pre-line;font-size:0.9rem;color:#334155;"><?= e($order['shipping_address']) ?></span><br>
            <strong style="font-size:0.88rem;"><?= e($order['district']) ?></strong>
        </div>
        <div style="padding:12px 14px;border:1px solid #e2e8f0;border-radius:10px;">
            <div style="font-size:0.76rem;text-transform:uppercase;color:#64748b;font-weight:700;margin-bottom:4px;"><?= $lang === 'bn' ? 'পেমেন্ট ও ডেলিভারি' : 'PAYMENT & COURIER' ?></div>
            <div>Method: <strong><?= e(strtoupper((string) $order['payment_method'])) ?></strong></div>
            <?php if (!empty($order['transaction_id'])): ?>
                <div>TrxID: <strong><?= e((string) $order['transaction_id']) ?></strong></div>
            <?php endif; ?>
            <?php if (!empty($order['courier_name']) || !empty($order['tracking_code'])): ?>
                <div>Courier: <strong><?= e((string) ($order['courier_name'] ?: '')) ?> <?= e((string) ($order['tracking_code'] ?: '')) ?></strong></div>
            <?php endif; ?>
        </div>
    </div>

    <table class="bx-inv-table">
        <thead>
            <tr>
                <th><?= $lang === 'bn' ? 'বই / পণ্য' : 'Book / Item' ?></th>
                <th style="width:90px;text-align:right;"><?= $lang === 'bn' ? 'দর' : 'Price' ?></th>
                <th style="width:70px;text-align:center;"><?= $lang === 'bn' ? 'সংখ্যা' : 'Qty' ?></th>
                <th style="width:110px;text-align:right;"><?= $lang === 'bn' ? 'মোট' : 'Total' ?></th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($items as $it): ?>
                <tr>
                    <td>
                        <strong><?= e($lang === 'bn' ? $it['title_bn'] : $it['title_en']) ?></strong>
                        <?php if (!empty($it['sku'])): ?><div style="font-size:0.78rem;color:#64748b;">SKU: <?= e($it['sku']) ?></div><?php endif; ?>
                    </td>
                    <td style="text-align:right;">৳<?= number_format((float) $it['unit_price'], 0) ?></td>
                    <td style="text-align:center;"><?= (int) $it['quantity'] ?></td>
                    <td style="text-align:right;"><strong>৳<?= number_format((float) $it['line_total'], 0) ?></strong></td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>

    <div style="max-width:300px;margin-left:auto;margin-top:16px;display:flex;flex-direction:column;gap:6px;font-size:0.92rem;">
        <div style="display:flex;justify-content:space-between;"><span><?= $lang === 'bn' ? 'সাবটোটাল' : 'Subtotal' ?></span><strong>৳<?= number_format((float) $order['subtotal_amount'], 0) ?></strong></div>
        <?php if ((float) $order['discount_amount'] > 0): ?>
            <div style="display:flex;justify-content:space-between;color:#0f766e;"><span><?= $lang === 'bn' ? 'কুপন ছাড়' : 'Discount' ?> (<?= e((string) $order['coupon_code']) ?>)</span><strong>-৳<?= number_format((float) $order['discount_amount'], 0) ?></strong></div>
        <?php endif; ?>
        <div style="display:flex;justify-content:space-between;"><span><?= $lang === 'bn' ? 'ডেলিভারি চার্জ' : 'Delivery' ?></span><strong>৳<?= number_format((float) $order['delivery_fee'], 0) ?></strong></div>
        <div style="display:flex;justify-content:space-between;padding-top:8px;border-top:2px solid #cbd5e1;font-size:1.1rem;font-weight:800;color:#232766;">
            <span><?= $lang === 'bn' ? 'সর্বমোট' : 'Grand Total' ?></span>
            <span>৳<?= number_format((float) $order['total_amount'], 0) ?></span>
        </div>
    </div>
</div>
<script src="<?= e(asset('assets/js/app.js')) ?>" defer></script>
</body>
</html>
