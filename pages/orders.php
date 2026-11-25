<?php
declare(strict_types=1);
/** @var array $orders */
$lang = current_lang();
?>
<div class="container" style="padding-top:24px;">
    <nav class="bx-breadcrumb" aria-label="Breadcrumb">
        <a href="<?= e(url('/')) ?>"><?= e(t('nav_home')) ?></a>
        <?= icon('chevron-right') ?>
        <a href="<?= e(url('/library')) ?>"><?= e(t('nav_library')) ?></a>
        <?= icon('chevron-right') ?>
        <span class="current"><?= $lang === 'bn' ? 'আমার অর্ডার' : 'My Orders' ?></span>
    </nav>

    <div class="bx-card" style="margin-top:20px;">
        <h1 class="bx-h2" style="margin-bottom:16px;"><?= $lang === 'bn' ? 'আমার অর্ডারসমূহ' : 'My Orders' ?></h1>
        <?php if (empty($orders)): ?>
            <p class="bx-muted" style="margin:0;"><?= $lang === 'bn' ? 'আপনার এখনো কোনো অর্ডার নেই।' : 'You have no orders yet.' ?></p>
        <?php else: ?>
            <div class="bx-table-scroll">
            <table style="width:100%;border-collapse:collapse;font-size:0.92rem;">
                <thead>
                    <tr style="background:var(--bg-alt);text-align:left;">
                        <th style="padding:10px 12px;">Order #</th>
                        <th style="padding:10px 12px;"><?= $lang === 'bn' ? 'বই' : 'PDF' ?></th>
                        <th style="padding:10px 12px;">Method / TrxID</th>
                        <th style="padding:10px 12px;"><?= $lang === 'bn' ? 'মূল্য' : 'Amount' ?></th>
                        <th style="padding:10px 12px;"><?= $lang === 'bn' ? 'অবস্থা' : 'Status' ?></th>
                        <th style="padding:10px 12px;"></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($orders as $o): ?>
                        <tr style="border-bottom:1px solid var(--border);">
                            <td style="padding:10px 12px;white-space:nowrap;"><strong><?= e($o['order_code']) ?></strong></td>
                            <td class="bx-col-wrap" style="padding:10px 12px;"><a href="<?= e(url('/book/' . rawurlencode($o['slug']))) ?>"><?= e(bi($o, 'title')) ?></a></td>
                            <td style="padding:10px 12px;"><?= e(strtoupper($o['payment_method'])) ?> • <code><?= e($o['transaction_id'] ?: '-') ?></code></td>
                            <td style="padding:10px 12px;">
                                <?= e(format_bdt((float) $o['amount'])) ?>
                                <?php if (!empty($o['coupon_code'])): ?>
                                    <div style="font-size:0.78rem;color:var(--teal-text);">
                                        <?= e($o['coupon_code']) ?> (-<?= e(format_bdt((float) ($o['discount_amount'] ?? 0))) ?>)
                                    </div>
                                <?php endif; ?>
                            </td>
                            <td style="padding:10px 12px;"><span class="bx-badge <?= $o['status'] === 'approved' ? 'bx-badge-success' : ($o['status'] === 'pending' ? 'bx-badge-gold' : 'bx-badge-accent') ?>"><?= e(ucfirst($o['status'])) ?></span></td>
                            <td style="padding:10px 12px;">
                                <?php if ($o['status'] === 'approved'): ?>
                                    <a href="<?= e(url('/reader/' . rawurlencode($o['slug']))) ?>" class="bx-btn bx-btn-primary bx-btn-sm"><?= e(t('read_now_btn')) ?></a>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            </div>
        <?php endif; ?>
    </div>
</div>
