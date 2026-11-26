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
                            <th style="padding:10px 12px;"><?= $lang === 'bn' ? 'অবস্থা ও কারণ' : 'Status & Details' ?></th>
                            <th style="padding:10px 12px;"><?= $lang === 'bn' ? 'অ্যাকশন' : 'Actions' ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($orders as $o): ?>
                            <?php
                            $st = (string) $o['status'];
                            $stMeta = order_status_labels($st);
                            $invoiceUrl = url('/orders/' . rawurlencode((string) $o['order_code']) . '/invoice');
                            ?>
                            <tr style="border-bottom:1px solid var(--border);vertical-align:top;">
                                <td style="padding:12px;white-space:nowrap;">
                                    <strong><?= e($o['order_code']) ?></strong>
                                    <div style="font-size:0.78rem;color:var(--text-muted);"><?= e(date('d M Y', strtotime((string) $o['created_at']))) ?></div>
                                </td>
                                <td class="bx-col-wrap" style="padding:12px;">
                                    <a href="<?= e(url('/book/' . rawurlencode($o['slug']))) ?>" style="font-weight:600;"><?= e(bi($o, 'title')) ?></a>
                                </td>
                                <td style="padding:12px;">
                                    <div><span class="bx-badge"><?= e(strtoupper((string) $o['payment_method'])) ?></span></div>
                                    <?php if (!empty($o['transaction_id'])): ?>
                                        <div style="font-size:0.82rem;margin-top:4px;">TrxID: <code><?= e($o['transaction_id']) ?></code></div>
                                    <?php endif; ?>
                                    <?php if (!empty($o['sender_number'])): ?>
                                        <div style="font-size:0.78rem;color:var(--text-muted);">From: <?= e($o['sender_number']) ?></div>
                                    <?php endif; ?>
                                </td>
                                <td style="padding:12px;white-space:nowrap;">
                                    <strong><?= e(format_bdt((float) $o['amount'])) ?></strong>
                                    <?php if (!empty($o['coupon_code'])): ?>
                                        <div style="font-size:0.78rem;color:var(--teal-text);">
                                            <?= e($o['coupon_code']) ?> (-<?= e(format_bdt((float) ($o['discount_amount'] ?? 0))) ?>)
                                        </div>
                                    <?php endif; ?>
                                </td>
                                <td style="padding:12px;">
                                    <span class="bx-badge <?= e($stMeta['badge']) ?>"><?= e($stMeta[$lang] ?? ucfirst($st)) ?></span>
                                    <?php if ($st === 'rejected' && !empty($o['admin_note'])): ?>
                                        <div style="margin-top:6px;padding:8px 10px;border-radius:8px;background:rgba(229,107,138,0.10);border-left:3px solid var(--danger);font-size:0.82rem;max-width:280px;overflow-wrap:anywhere;">
                                            <strong><?= $lang === 'bn' ? 'কারণ:' : 'Reason:' ?></strong> <?= e((string) $o['admin_note']) ?>
                                        </div>
                                    <?php elseif (!empty($o['admin_note']) && $st !== 'approved'): ?>
                                        <div style="font-size:0.8rem;color:var(--text-muted);margin-top:4px;"><?= e((string) $o['admin_note']) ?></div>
                                    <?php endif; ?>
                                </td>
                                <td style="padding:12px;">
                                    <div style="display:flex;gap:8px;flex-wrap:wrap;align-items:center;">
                                        <a href="<?= e($invoiceUrl) ?>" class="bx-btn bx-btn-outline bx-btn-sm">
                                            <?= $lang === 'bn' ? 'ইনভয়েস' : 'Invoice' ?>
                                        </a>
                                        <?php if ($st === 'approved'): ?>
                                            <a href="<?= e(url('/reader/' . rawurlencode($o['slug']))) ?>" class="bx-btn bx-btn-primary bx-btn-sm"><?= e(t('read_now_btn')) ?></a>
                                        <?php elseif ($st === 'pending'): ?>
                                            <form method="post" action="<?= e(url('/orders')) ?>" style="margin:0;">
                                                <?= csrf_field() ?>
                                                <input type="hidden" name="action" value="cancel_order">
                                                <input type="hidden" name="order_id" value="<?= (int) $o['id'] ?>">
                                                <button type="submit" class="bx-btn bx-btn-ghost bx-btn-sm"
                                                        data-confirm="<?= e($lang === 'bn' ? 'আপনি কি নিশ্চিতভাবে এই অর্ডারটি বাতিল করতে চান?' : 'Are you sure you want to cancel this pending order?') ?>">
                                                    <?= $lang === 'bn' ? 'বাতিল করুন' : 'Cancel Order' ?>
                                                </button>
                                            </form>
                                        <?php endif; ?>
                                    </div>

                                    <?php if ($st === 'rejected' && (float) $o['amount'] > 0): ?>
                                        <details style="margin-top:8px;">
                                            <summary class="bx-btn bx-btn-accent bx-btn-sm" style="cursor:pointer;list-style:none;">
                                                <?= $lang === 'bn' ? 'সংশোধন করে পুনরায় জমা দিন' : 'Fix and Resubmit' ?>
                                            </summary>
                                            <form method="post" action="<?= e(url('/orders')) ?>" enctype="multipart/form-data" style="margin-top:10px;padding:12px;border-radius:12px;background:var(--bg-alt);border:1px solid var(--border);min-width:230px;">
                                                <?= csrf_field() ?>
                                                <input type="hidden" name="action" value="resubmit_order">
                                                <input type="hidden" name="order_id" value="<?= (int) $o['id'] ?>">
                                                <div class="bx-field" style="margin-bottom:8px;">
                                                    <label style="font-size:0.8rem;"><?= $lang === 'bn' ? 'পেমেন্ট মাধ্যম' : 'Method' ?></label>
                                                    <select class="bx-select" name="payment_method">
                                                        <?php $pm = in_array($o['payment_method'], ['bkash', 'nagad', 'rocket'], true) ? $o['payment_method'] : 'bkash'; ?>
                                                        <option value="bkash" <?= $pm === 'bkash' ? 'selected' : '' ?>>bKash</option>
                                                        <option value="nagad" <?= $pm === 'nagad' ? 'selected' : '' ?>>Nagad</option>
                                                        <option value="rocket" <?= $pm === 'rocket' ? 'selected' : '' ?>>Rocket</option>
                                                    </select>
                                                </div>
                                                <div class="bx-field" style="margin-bottom:8px;">
                                                    <label style="font-size:0.8rem;"><?= $lang === 'bn' ? 'প্রেরক নম্বর *' : 'Sender Number *' ?></label>
                                                    <input class="bx-input" name="sender_number" required value="<?= e((string) ($o['sender_number'] ?? '')) ?>">
                                                </div>
                                                <div class="bx-field" style="margin-bottom:8px;">
                                                    <label style="font-size:0.8rem;">Transaction ID (TrxID) *</label>
                                                    <input class="bx-input" name="transaction_id" required value="<?= e((string) ($o['transaction_id'] ?? '')) ?>">
                                                </div>
                                                <div class="bx-field" style="margin-bottom:10px;">
                                                    <label style="font-size:0.8rem;"><?= $lang === 'bn' ? 'নতুন স্ক্রিনশট (ঐচ্ছিক)' : 'New Screenshot (Optional)' ?></label>
                                                    <input class="bx-input" type="file" name="payment_screenshot" accept="image/jpeg,image/png,image/webp">
                                                </div>
                                                <button type="submit" class="bx-btn bx-btn-primary bx-btn-sm" style="width:100%;">
                                                    <?= $lang === 'bn' ? 'পুনরায় জমা দিন' : 'Submit Fixed Details' ?>
                                                </button>
                                            </form>
                                        </details>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>

    <!-- Physical Book Orders Section -->
    <div class="bx-card" style="padding:22px;margin-top:24px;" id="physical-orders">
        <div style="display:flex;justify-content:space-between;align-items:center;gap:12px;flex-wrap:wrap;margin-bottom:16px;">
            <h2 class="bx-h3" style="margin:0;"><?= $lang === 'bn' ? 'প্রিন্টেড বইয়ের অর্ডারসমূহ' : 'Physical Book Orders' ?></h2>
            <a href="<?= e(url('/shop')) ?>" class="bx-btn bx-btn-outline bx-btn-sm"><?= icon('cart') ?> <?= e(t('portal_shop_cta')) ?></a>
        </div>

        <?php if (empty($shopOrders)): ?>
            <p class="bx-muted" style="margin:0;"><?= $lang === 'bn' ? 'এখনো কোনো প্রিন্টেড বইয়ের অর্ডার করা হয়নি।' : 'You have not placed any physical book orders yet.' ?></p>
        <?php else: ?>
            <div style="display:flex;flex-direction:column;gap:14px;">
                <?php foreach ($shopOrders as $so):
                    $soId = (int) $so['id'];
                    $soCode = (string) $so['order_code'];
                    $st = (string) $so['status'];
                    $stMeta = shop_order_status_labels($st);
                    $soItems = $shopOrderItemsById[$soId] ?? [];
                    $retSt = (string) ($so['return_status'] ?? 'none');
                ?>
                    <div style="padding:16px;border-radius:14px;background:var(--bg-alt);border:1px solid var(--border);">
                        <div style="display:flex;justify-content:space-between;align-items:flex-start;gap:10px;flex-wrap:wrap;margin-bottom:10px;">
                            <div>
                                <strong style="font-size:1.05rem;">#<?= e($soCode) ?></strong>
                                <div class="bx-muted" style="font-size:0.84rem;"><?= e(date('d M Y, h:i A', strtotime((string) $so['created_at']))) ?> • <?= e(strtoupper((string) $so['payment_method'])) ?></div>
                            </div>
                            <div style="display:flex;gap:8px;align-items:center;flex-wrap:wrap;">
                                <span class="bx-badge <?= e($stMeta['badge']) ?>"><?= e($lang === 'bn' ? $stMeta['bn'] : $stMeta['en']) ?></span>
                                <?php if ($retSt !== 'none'): $rMeta = shop_return_status_labels($retSt); ?>
                                    <span class="bx-badge <?= e($rMeta['badge']) ?>"><?= e($lang === 'bn' ? $rMeta['bn'] : $rMeta['en']) ?></span>
                                <?php endif; ?>
                            </div>
                        </div>

                        <div style="font-size:0.9rem;margin-bottom:8px;">
                            <?php foreach ($soItems as $sli): ?>
                                <div><?= (int) $sli['quantity'] ?>× <?= e($lang === 'bn' ? $sli['title_bn'] : $sli['title_en']) ?> — <strong><?= e(format_bdt((float) $sli['line_total'])) ?></strong></div>
                            <?php endforeach; ?>
                        </div>

                        <div style="font-size:0.86rem;color:var(--text-muted);margin-bottom:10px;">
                            <strong><?= $lang === 'bn' ? 'মোট:' : 'Total:' ?></strong> <?= e(format_bdt((float) $so['total_amount'])) ?>
                            <?php if (!empty($so['courier_name']) || !empty($so['tracking_code'])): ?>
                                • <strong><?= $lang === 'bn' ? 'ট্র্যাকিং:' : 'Tracking:' ?></strong> <?= e((string) ($so['courier_name'] ?: '')) ?> <?= e((string) ($so['tracking_code'] ?: '')) ?>
                            <?php endif; ?>
                            <?php if (!empty($so['admin_note'])): ?>
                                <div style="margin-top:4px;color:var(--primary);">Note: <?= e((string) $so['admin_note']) ?></div>
                            <?php endif; ?>
                        </div>

                        <div style="display:flex;gap:8px;flex-wrap:wrap;align-items:center;">
                            <a href="<?= e(url('/shop/track?code=' . rawurlencode($soCode))) ?>" class="bx-btn bx-btn-outline bx-btn-sm"><?= icon('truck') ?> <?= $lang === 'bn' ? 'ট্র্যাক' : 'Track' ?></a>
                            <a href="<?= e(url('/shop/orders/' . rawurlencode($soCode) . '/invoice')) ?>" target="_blank" rel="noopener" class="bx-btn bx-btn-ghost bx-btn-sm"><?= icon('printer') ?> <?= $lang === 'bn' ? 'ইনভয়েস' : 'Invoice' ?></a>

                            <form method="post" action="<?= e(url('/orders')) ?>" style="margin:0;">
                                <?= csrf_field() ?>
                                <input type="hidden" name="action" value="shop_reorder">
                                <input type="hidden" name="shop_order_id" value="<?= $soId ?>">
                                <button type="submit" class="bx-btn bx-btn-primary bx-btn-sm"><?= icon('rotate') ?> <?= $lang === 'bn' ? 'পুনরায় অর্ডার' : 'Reorder' ?></button>
                            </form>

                            <?php if ($st === 'pending'): ?>
                                <form method="post" action="<?= e(url('/orders')) ?>" style="margin:0;">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="action" value="shop_cancel_order">
                                    <input type="hidden" name="shop_order_id" value="<?= $soId ?>">
                                    <button type="submit" class="bx-btn bx-btn-ghost bx-btn-sm" style="color:var(--rose-text);"><?= $lang === 'bn' ? 'বাতিল করুন' : 'Cancel' ?></button>
                                </form>
                            <?php endif; ?>
                        </div>

                        <?php if (shop_can_request_return($so)): ?>
                            <details style="margin-top:10px;">
                                <summary class="bx-btn bx-btn-outline bx-btn-sm" style="cursor:pointer;list-style:none;display:inline-flex;">
                                    <?= $lang === 'bn' ? 'রিটার্ন অনুরোধ করুন' : 'Request Return' ?>
                                </summary>
                                <form method="post" action="<?= e(url('/orders')) ?>" style="margin-top:8px;padding:12px;border-radius:10px;background:var(--surface);border:1px solid var(--border);">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="action" value="shop_request_return">
                                    <input type="hidden" name="shop_order_id" value="<?= $soId ?>">
                                    <div class="bx-field" style="margin-bottom:8px;">
                                        <label style="font-size:0.84rem;"><?= $lang === 'bn' ? 'রিটার্নের কারণ *' : 'Reason for Return *' ?></label>
                                        <textarea name="return_reason" rows="2" class="bx-input" required placeholder="<?= e($lang === 'bn' ? 'ক্ষতিগ্রস্ত বই বা ভুল পণ্য...' : 'Describe damaged book or wrong item...') ?>"></textarea>
                                    </div>
                                    <button type="submit" class="bx-btn bx-btn-accent bx-btn-sm"><?= $lang === 'bn' ? 'রিটার্ন জমা দিন' : 'Submit Return Request' ?></button>
                                </form>
                            </details>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</div>
