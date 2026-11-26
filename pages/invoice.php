<?php
declare(strict_types=1);

/**
 * Printable bilingual Order Invoice / Receipt (/orders/{code}/invoice)
 * Uses pure-PHP local SVG QR code (zero CDN or external scripts).
 * @var array $order
 */
require_once APP_ROOT . '/includes/qrcode.php';

$lang = current_lang();
$siteName = site_setting('site_name', 'Bookexa');
$sitePhone = site_setting('contact_phone', '');
$siteEmail = site_setting('contact_email', '');
$siteAddr = site_setting('contact_address', '');
$logoPath = site_setting('site_logo', '');
$logoUrl = $logoPath !== '' ? asset($logoPath) : asset('assets/img/logo.svg');
$faviconUrl = !empty(site_setting('site_favicon', '')) ? asset(site_setting('site_favicon', '')) : asset('assets/img/favicon.svg');

$invoiceUrl = BASE_URL . '/orders/' . rawurlencode((string) $order['order_code']) . '/invoice';
$qrSvg = render_qr_svg($invoiceUrl, 128);
$st = (string) $order['status'];
$stMeta = order_status_labels($st);
$origAmt = isset($order['original_amount']) && $order['original_amount'] !== null
    ? (float) $order['original_amount']
    : ((float) $order['amount'] + (float) ($order['discount_amount'] ?? 0));
?>
<!DOCTYPE html>
<html lang="<?= e($lang) ?>" data-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <meta name="robots" content="noindex,nofollow">
    <title><?= $lang === 'bn' ? 'রসিদ / ইনভয়েস' : 'Invoice' ?> #<?= e($order['order_code']) ?> — <?= e($siteName) ?></title>
    <link rel="icon" href="<?= e($faviconUrl) ?>">
    <link rel="stylesheet" href="<?= e(asset('assets/css/tokens.css')) ?>">
    <link rel="stylesheet" href="<?= e(asset('assets/css/components.css')) ?>">
    <style>
        body { background: #f3f4f6; color: #111827; padding: 24px 12px; }
        .bx-inv-sheet {
            max-width: 780px; margin: 0 auto; background: #ffffff; color: #111827;
            border: 1px solid #e5e7eb; border-radius: 16px; padding: 28px 30px;
            box-shadow: 0 12px 32px rgba(17, 24, 39, 0.08);
        }
        .bx-inv-actions { max-width: 780px; margin: 0 auto 14px; display: flex; justify-content: space-between; gap: 10px; flex-wrap: wrap; }
        .bx-inv-head { display: flex; justify-content: space-between; align-items: flex-start; gap: 18px; border-bottom: 2px solid #e5e7eb; padding-bottom: 18px; flex-wrap: wrap; }
        .bx-inv-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 18px; margin: 20px 0; }
        .bx-inv-box { padding: 14px; border: 1px solid #e5e7eb; border-radius: 12px; background: #f9fafb; }
        .bx-inv-table { width: 100%; border-collapse: collapse; margin: 16px 0; }
        .bx-inv-table th, .bx-inv-table td { padding: 11px 12px; border-bottom: 1px solid #e5e7eb; text-align: left; }
        .bx-inv-table th { background: #f3f4f6; font-size: 0.85rem; color: #4b5563; }
        .bx-inv-foot { display: flex; justify-content: space-between; align-items: center; gap: 18px; margin-top: 22px; padding-top: 16px; border-top: 1px dashed #d1d5db; flex-wrap: wrap; }
        @media print {
            body { background: #ffffff !important; padding: 0 !important; }
            .bx-inv-actions { display: none !important; }
            .bx-inv-sheet { box-shadow: none !important; border: none !important; max-width: 100% !important; padding: 12px !important; }
        }
    </style>
</head>
<body>
    <div class="bx-inv-actions">
        <a href="<?= e(url('/orders')) ?>" class="bx-btn bx-btn-outline">← <?= $lang === 'bn' ? 'আমার অর্ডারসমূহ' : 'Back to My Orders' ?></a>
        <div style="display:flex;gap:8px;flex-wrap:wrap;">
            <a href="<?= e(url('/orders/' . rawurlencode((string) $order['order_code']) . '/invoice?lang=' . ($lang === 'bn' ? 'en' : 'bn'))) ?>" class="bx-btn bx-btn-ghost">
                <?= $lang === 'bn' ? 'English' : 'বাংলা' ?>
            </a>
            <button type="button" data-print-btn class="bx-btn bx-btn-primary">
                <?= $lang === 'bn' ? 'প্রিন্ট / PDF সংরক্ষণ' : 'Print / Save PDF' ?>
            </button>
        </div>
    </div>

    <main class="bx-inv-sheet">
        <header class="bx-inv-head">
            <div>
                <img src="<?= e($logoUrl) ?>" alt="<?= e($siteName) ?>" style="max-height:42px;margin-bottom:8px;display:block;">
                <div style="font-weight:800;font-size:1.15rem;"><?= e($siteName) ?></div>
                <?php if ($siteAddr !== ''): ?><div style="font-size:0.86rem;color:#4b5563;"><?= e($siteAddr) ?></div><?php endif; ?>
                <div style="font-size:0.86rem;color:#4b5563;">
                    <?= e($siteEmail) ?><?= ($siteEmail !== '' && $sitePhone !== '') ? ' · ' : '' ?><?= e($sitePhone) ?>
                </div>
            </div>
            <div style="text-align:right;">
                <div style="font-size:0.8rem;text-transform:uppercase;letter-spacing:0.06em;color:#6b7280;font-weight:700;">
                    <?= $lang === 'bn' ? 'অর্ডার ইনভয়েস / রসিদ' : 'Order Invoice / Receipt' ?>
                </div>
                <div style="font-size:1.35rem;font-weight:800;margin:2px 0;">#<?= e($order['order_code']) ?></div>
                <div style="font-size:0.85rem;color:#4b5563;">
                    <?= $lang === 'bn' ? 'তারিখ:' : 'Date:' ?> <?= e(date('d M Y, h:i A', strtotime((string) $order['created_at']))) ?>
                </div>
                <div style="margin-top:6px;">
                    <span class="bx-badge <?= e($stMeta['badge']) ?>"><?= e($stMeta[$lang] ?? ucfirst($st)) ?></span>
                </div>
            </div>
        </header>

        <section class="bx-inv-grid">
            <div class="bx-inv-box">
                <div style="font-size:0.78rem;font-weight:700;text-transform:uppercase;color:#6b7280;margin-bottom:6px;">
                    <?= $lang === 'bn' ? 'ক্রেতার তথ্য (Billed To)' : 'Customer Details' ?>
                </div>
                <div><strong><?= e((string) $order['user_name']) ?></strong></div>
                <div style="font-size:0.88rem;color:#374151;"><?= e((string) $order['user_email']) ?></div>
                <?php if (!empty($order['user_phone'])): ?>
                    <div style="font-size:0.86rem;color:#374151;"><?= e((string) $order['user_phone']) ?></div>
                <?php endif; ?>
            </div>
            <div class="bx-inv-box">
                <div style="font-size:0.78rem;font-weight:700;text-transform:uppercase;color:#6b7280;margin-bottom:6px;">
                    <?= $lang === 'bn' ? 'পেমেন্ট বিবরণ' : 'Payment Details' ?>
                </div>
                <div><strong><?= $lang === 'bn' ? 'মাধ্যম:' : 'Method:' ?></strong> <?= e(strtoupper((string) $order['payment_method'])) ?></div>
                <?php if (!empty($order['transaction_id'])): ?>
                    <div style="font-size:0.88rem;"><strong>TrxID:</strong> <code><?= e((string) $order['transaction_id']) ?></code></div>
                <?php endif; ?>
                <?php if (!empty($order['sender_number'])): ?>
                    <div style="font-size:0.86rem;"><strong><?= $lang === 'bn' ? 'প্রেরক:' : 'Sender:' ?></strong> <?= e((string) $order['sender_number']) ?></div>
                <?php endif; ?>
                <?php if (!empty($order['gateway_ref'])): ?>
                    <div style="font-size:0.84rem;color:#4b5563;"><strong>Ref:</strong> <code><?= e((string) $order['gateway_ref']) ?></code></div>
                <?php endif; ?>
            </div>
        </section>

        <table class="bx-inv-table">
            <thead>
                <tr>
                    <th><?= $lang === 'bn' ? 'ডিজিটাল বই (PDF)' : 'Item Description' ?></th>
                    <th style="text-align:right;"><?= $lang === 'bn' ? 'মূল্য' : 'Amount' ?></th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td>
                        <strong><?= e($lang === 'bn' ? $order['title_bn'] : $order['title_en']) ?></strong>
                        <div style="font-size:0.82rem;color:#6b7280;"><?= e($lang === 'bn' ? $order['title_en'] : $order['title_bn']) ?></div>
                    </td>
                    <td style="text-align:right;">৳<?= number_format($origAmt, 2) ?></td>
                </tr>
                <?php if (!empty($order['coupon_code']) || (float) ($order['discount_amount'] ?? 0) > 0): ?>
                <tr>
                    <td>
                        <?= $lang === 'bn' ? 'কুপন ছাড়' : 'Coupon Discount' ?>
                        <?php if (!empty($order['coupon_code'])): ?>
                            <span class="bx-badge" style="margin-left:4px;"><?= e((string) $order['coupon_code']) ?></span>
                        <?php endif; ?>
                    </td>
                    <td style="text-align:right;color:#0f766e;">-৳<?= number_format((float) ($order['discount_amount'] ?? 0), 2) ?></td>
                </tr>
                <?php endif; ?>
                <tr>
                    <td style="font-weight:800;font-size:1.02rem;"><?= $lang === 'bn' ? 'সর্বমোট পরিশোধযোগ্য' : 'Total Paid / Payable' ?></td>
                    <td style="text-align:right;font-weight:800;font-size:1.08rem;">৳<?= number_format((float) $order['amount'], 2) ?></td>
                </tr>
            </tbody>
        </table>

        <?php if ($st === 'rejected' && !empty($order['admin_note'])): ?>
            <div style="padding:10px 14px;border-radius:10px;background:#fef2f2;border-left:4px solid #dc2626;font-size:0.88rem;margin-bottom:12px;">
                <strong><?= $lang === 'bn' ? 'নোট / কারণ:' : 'Reason / Note:' ?></strong> <?= e((string) $order['admin_note']) ?>
            </div>
        <?php endif; ?>

        <footer class="bx-inv-foot">
            <div style="font-size:0.84rem;color:#4b5563;max-width:460px;">
                <div><strong><?= $lang === 'bn' ? 'অর্ডার যাচাই লিংক:' : 'Order Verification QR:' ?></strong></div>
                <div style="word-break:break-all;margin-top:2px;"><?= e($invoiceUrl) ?></div>
                <?php if (!empty($order['reviewed_at'])): ?>
                    <div style="margin-top:4px;"><?= $lang === 'bn' ? 'সর্বশেষ আপডেট:' : 'Status Updated:' ?> <?= e(date('d M Y, h:i A', strtotime((string) $order['reviewed_at']))) ?></div>
                <?php endif; ?>
            </div>
            <div>
                <?= $qrSvg ?>
            </div>
        </footer>
    </main>
    <script src="<?= e(asset('assets/js/app.js')) ?>"></script>
</body>
</html>
