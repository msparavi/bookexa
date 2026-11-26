<?php
declare(strict_types=1);

$orderId = max(0, (int) ($_GET['order_id'] ?? 0));
$order = $orderId > 0 ? db_one('SELECT * FROM shop_orders WHERE id = ? LIMIT 1', [$orderId]) : null;
if (!$order) {
    render_error_page(404);
}
$items = db_all(
    'SELECT soi.*, p.weight_grams FROM shop_order_items soi LEFT JOIN products p ON p.id = soi.product_id WHERE soi.shop_order_id = ? ORDER BY soi.id ASC',
    [$orderId]
);
$lang = current_lang();
$siteName = site_setting('site_name', 'Bookexa');
$sitePhone = site_setting('contact_phone', '');
$siteAddr = site_setting('contact_address', 'Dhaka, Bangladesh');
$trackUrl = BASE_URL . '/shop/track?code=' . rawurlencode((string) $order['order_code']);
$totalWeightG = 0;
foreach ($items as $it) {
    $totalWeightG += max(100, (int) ($it['weight_grams'] ?? 300)) * (int) $it['quantity'];
}
require_once APP_ROOT . '/includes/qrcode.php';
?>
<!DOCTYPE html>
<html lang="<?= e($lang) ?>" data-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow">
    <title>Packing Slip #<?= e($order['order_code']) ?> — <?= e($siteName) ?></title>
    <link rel="stylesheet" href="<?= e(asset('assets/css/fonts.css')) ?>">
    <link rel="stylesheet" href="<?= e(asset('assets/css/tokens.css')) ?>">
    <link rel="stylesheet" href="<?= e(asset('assets/css/components.css')) ?>">
    <style>
        body { background: #ffffff; color: #111827; padding: 20px; }
        .bx-slip { max-width: 780px; margin: 0 auto; border: 2px solid #1e293b; border-radius: 14px; padding: 24px; background: #fff; }
        .bx-slip-head { display: flex; justify-content: space-between; align-items: flex-start; gap: 16px; flex-wrap: wrap; border-bottom: 2px dashed #cbd5e1; padding-bottom: 16px; margin-bottom: 18px; }
        .bx-slip-table { width: 100%; border-collapse: collapse; margin-top: 14px; }
        .bx-slip-table th, .bx-slip-table td { border: 1px solid #cbd5e1; padding: 10px 12px; text-align: left; }
        .bx-slip-table th { background: #f8fafc; font-size: 0.85rem; }
        @media print {
            body { padding: 0; }
            .bx-no-print { display: none !important; }
            .bx-slip { border: 1px solid #111827; border-radius: 0; }
        }
    </style>
</head>
<body>
<div class="bx-no-print" style="max-width:780px;margin:0 auto 14px;display:flex;gap:10px;justify-content:space-between;align-items:center;flex-wrap:wrap;">
    <a href="<?= e(url('/admin/shop-orders')) ?>" class="bx-btn bx-btn-outline bx-btn-sm">← <?= $lang === 'bn' ? 'শপ অর্ডারে ফিরুন' : 'Back to Shop Orders' ?></a>
    <button type="button" class="bx-btn bx-btn-primary bx-btn-sm" data-print-btn><?= $lang === 'bn' ? 'প্যাকিং স্লিপ প্রিন্ট করুন' : 'Print Packing Slip' ?></button>
</div>

<div class="bx-slip">
    <div class="bx-slip-head">
        <div>
            <div style="font-size:0.78rem;text-transform:uppercase;letter-spacing:0.08em;color:#475569;font-weight:700;">PACKING SLIP &bull; DISPATCH LABEL</div>
            <h1 style="margin:4px 0 6px;font-size:1.55rem;"><?= e($siteName) ?></h1>
            <div style="font-size:0.9rem;color:#334155;">From: <?= e($siteAddr) ?> <?= $sitePhone ? '• ' . e($sitePhone) : '' ?></div>
        </div>
        <div style="display:flex;align-items:center;gap:12px;">
            <div style="text-align:right;">
                <strong style="font-size:1.05rem;">#<?= e($order['order_code']) ?></strong><br>
                <small style="color:#475569;"><?= e(date('d M Y, h:i A', strtotime((string) $order['created_at']))) ?></small><br>
                <span style="display:inline-block;margin-top:4px;padding:3px 8px;border-radius:6px;background:#0f172a;color:#fff;font-size:0.78rem;font-weight:700;">
                    <?= $order['payment_method'] === 'cod'
                        ? ('COD: Collect ৳' . number_format((float) $order['total_amount'], 0))
                        : ('PREPAID (' . strtoupper((string) $order['payment_method']) . ')') ?>
                </span>
            </div>
            <?= render_qr_svg($trackUrl, 76) ?>
        </div>
    </div>

    <div class="bx-grid-2" style="gap:16px;margin-bottom:16px;">
        <div style="border:1px solid #cbd5e1;border-radius:10px;padding:12px;">
            <div style="font-size:0.76rem;text-transform:uppercase;color:#475569;font-weight:700;margin-bottom:4px;">SHIP TO</div>
            <strong style="font-size:1.05rem;display:block;"><?= e($order['customer_name']) ?></strong>
            <div>Phone: <strong><?= e($order['customer_phone']) ?></strong></div>
            <div style="margin-top:4px;white-space:pre-line;"><?= e($order['shipping_address']) ?></div>
            <div style="margin-top:4px;font-weight:600;"><?= e($order['district']) ?> (<?= $order['delivery_area'] === 'inside_dhaka' ? 'Inside Dhaka' : 'Outside Dhaka' ?>)</div>
        </div>
        <div style="border:1px solid #cbd5e1;border-radius:10px;padding:12px;">
            <div style="font-size:0.76rem;text-transform:uppercase;color:#475569;font-weight:700;margin-bottom:4px;">LOGISTICS</div>
            <div>Courier: <strong><?= e((string) ($order['courier_name'] ?: 'Unassigned')) ?></strong></div>
            <div>Tracking Code: <strong><?= e((string) ($order['tracking_code'] ?: 'Pending')) ?></strong></div>
            <div>Parcel Weight: <strong><?= number_format($totalWeightG / 1000, 2) ?> kg</strong> (<?= (int) $totalWeightG ?> g)</div>
            <div>Total Items: <strong><?= count($items) ?></strong></div>
        </div>
    </div>

    <table class="bx-slip-table">
        <thead>
            <tr>
                <th style="width:44px;text-align:center;">Check</th>
                <th>SKU / Book Title</th>
                <th style="width:90px;text-align:center;">Qty</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($items as $it): ?>
                <tr>
                    <td style="text-align:center;font-size:1.1rem;">&#9744;</td>
                    <td>
                        <strong><?= e($lang === 'bn' ? $it['title_bn'] : $it['title_en']) ?></strong>
                        <?php if (!empty($it['sku'])): ?>
                            <div style="font-size:0.8rem;color:#475569;">SKU: <?= e($it['sku']) ?></div>
                        <?php endif; ?>
                    </td>
                    <td style="text-align:center;font-size:1.05rem;font-weight:800;"><?= (int) $it['quantity'] ?></td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>
<script src="<?= e(asset('assets/js/app.js')) ?>" defer></script>
</body>
</html>
