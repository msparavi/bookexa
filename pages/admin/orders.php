<?php
declare(strict_types=1);

$lang = current_lang();
$statusFilter = (string) ($_GET['status'] ?? '');
$sql = 'SELECT o.*, u.name AS user_name, u.email AS user_email, i.title_en, i.title_bn
        FROM orders o
        JOIN users u ON u.id = o.user_id
        JOIN items i ON i.id = o.item_id';
$params = [];
if (in_array($statusFilter, ['pending', 'approved', 'rejected'], true)) {
    $sql .= ' WHERE o.status = ?';
    $params[] = $statusFilter;
}
$sql .= ' ORDER BY o.id DESC LIMIT 100';
$orders = db_all($sql, $params);
?>

<div class="bx-mobile-only-bar">
    <button type="button" class="bx-btn bx-btn-outline" data-open-sheet="order-filter-sheet">
        <?= $lang === 'bn' ? 'স্ট্যাটাস ফিল্টার' : 'Filter Status' ?>
        <?= $statusFilter !== '' ? '(' . ucfirst($statusFilter) . ')' : '' ?>
    </button>
</div>

<div id="order-filter-sheet" class="bx-filter-sheet-wrap" style="margin-bottom:18px;">
    <section class="bx-card bx-filter-sheet-card" style="padding:14px 18px;">
        <div class="bx-sheet-head">
            <strong style="font-family:var(--font-serif);"><?= $lang === 'bn' ? 'অর্ডার ফিল্টার' : 'Filter Orders' ?></strong>
            <button type="button" class="bx-btn bx-btn-ghost" data-close-sheet aria-label="Close" style="min-width:44px;min-height:44px;">✕</button>
        </div>
        <div style="display:flex;gap:8px;flex-wrap:wrap;">
            <a href="<?= e(url('/admin/orders')) ?>" class="bx-btn <?= $statusFilter === '' ? 'bx-btn-primary' : 'bx-btn-outline' ?>"><?= $lang === 'bn' ? 'সব' : 'All' ?></a>
            <a href="<?= e(url('/admin/orders?status=pending')) ?>" class="bx-btn <?= $statusFilter === 'pending' ? 'bx-btn-primary' : 'bx-btn-outline' ?>"><?= $lang === 'bn' ? 'পেন্ডিং' : 'Pending' ?></a>
            <a href="<?= e(url('/admin/orders?status=approved')) ?>" class="bx-btn <?= $statusFilter === 'approved' ? 'bx-btn-primary' : 'bx-btn-outline' ?>"><?= $lang === 'bn' ? 'অনুমোদিত' : 'Approved' ?></a>
            <a href="<?= e(url('/admin/orders?status=rejected')) ?>" class="bx-btn <?= $statusFilter === 'rejected' ? 'bx-btn-primary' : 'bx-btn-outline' ?>"><?= $lang === 'bn' ? 'বাতিল' : 'Rejected' ?></a>
        </div>
    </section>
</div>

<section class="bx-card">
    <h2 style="margin:0 0 14px;font-size:1.1rem;font-family:var(--font-serif);"><?= $lang === 'bn' ? 'অর্ডারসমূহ' : 'Orders' ?> (<?= count($orders) ?>)</h2>
    <div class="bx-table-wrap">
        <table class="bx-table bx-mobile-cards">
            <thead>
                <tr>
                    <th>Order #</th>
                    <th><?= $lang === 'bn' ? 'ক্রেতা' : 'Buyer' ?></th>
                    <th><?= $lang === 'bn' ? 'বই' : 'PDF Item' ?></th>
                    <th>Method / TrxID</th>
                    <th><?= $lang === 'bn' ? 'মূল্য' : 'Amount' ?></th>
                    <th><?= $lang === 'bn' ? 'অবস্থা' : 'Status' ?></th>
                    <th><?= $lang === 'bn' ? 'অ্যাকশন' : 'Action' ?></th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($orders)): ?>
                    <tr class="bx-empty-row"><td colspan="7" style="color:var(--text-muted);padding:24px;"><?= $lang === 'bn' ? 'কোনো অর্ডার পাওয়া যায়নি।' : 'No orders found.' ?></td></tr>
                <?php else: foreach ($orders as $o): ?>
                    <tr>
                        <td class="bx-td-primary">
                            <strong>#<?= e($o['order_code']) ?></strong>
                            <div style="font-size:0.78rem;color:var(--text-muted);"><?= e($o['created_at']) ?></div>
                        </td>
                        <td data-label="<?= e($lang === 'bn' ? 'ক্রেতা' : 'Buyer') ?>">
                            <div>
                                <div><?= e($o['user_name']) ?></div>
                                <small class="bx-ellipsis" style="color:var(--text-muted);"><?= e($o['user_email']) ?></small>
                            </div>
                        </td>
                        <td data-label="<?= e($lang === 'bn' ? 'বই' : 'PDF') ?>"><?= e($lang === 'bn' ? $o['title_bn'] : $o['title_en']) ?></td>
                        <td data-label="TrxID">
                            <span><?= e(strtoupper($o['payment_method'])) ?> • <code><?= e($o['transaction_id'] ?: '-') ?></code> <small style="color:var(--text-muted);"><?= e($o['sender_number']) ?></small></span>
                        </td>
                        <td data-label="<?= e($lang === 'bn' ? 'মূল্য' : 'Amount') ?>">
                            <strong>৳<?= number_format((float) $o['amount'], 0) ?></strong>
                            <?php if (!empty($o['coupon_code'])): ?>
                                <div style="font-size:0.78rem;color:var(--teal-text);"><?= e($o['coupon_code']) ?> (-৳<?= number_format((float) ($o['discount_amount'] ?? 0), 0) ?>)</div>
                            <?php endif; ?>
                        </td>
                        <td data-label="<?= e($lang === 'bn' ? 'অবস্থা' : 'Status') ?>"><span class="bx-status-pill st-<?= e($o['status']) ?>"><?= e(ucfirst($o['status'])) ?></span></td>
                        <td class="bx-td-actions">
                            <?php $orderRet = $_SERVER['REQUEST_URI'] ?? '/admin/orders'; ?>
                            <?php if ($o['status'] !== 'approved'): ?>
                                <form method="post" action="<?= e(url('/admin/orders')) ?>" style="margin:0;">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="action" value="review_order">
                                    <input type="hidden" name="order_id" value="<?= (int) $o['id'] ?>">
                                    <input type="hidden" name="decision" value="approved">
                                    <input type="hidden" name="_return" value="<?= e($orderRet) ?>">
                                    <button class="bx-btn bx-btn-primary" style="padding:8px 14px;font-size:0.84rem;" type="submit"><?= $lang === 'bn' ? 'অনুমোদন' : 'Approve' ?></button>
                                </form>
                            <?php endif; ?>
                            <?php if ($o['status'] === 'pending'): ?>
                                <form method="post" action="<?= e(url('/admin/orders')) ?>" data-confirm-msg="<?= e($lang === 'bn' ? 'এই অর্ডারটি বাতিল করবেন?' : 'Reject this payment order?') ?>" style="margin:0;">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="action" value="review_order">
                                    <input type="hidden" name="_return" value="<?= e($orderRet) ?>">
                                    <input type="hidden" name="order_id" value="<?= (int) $o['id'] ?>">
                                    <input type="hidden" name="decision" value="rejected">
                                    <button class="bx-btn bx-btn-outline" style="padding:8px 14px;font-size:0.84rem;color:var(--rose);" type="submit"><?= $lang === 'bn' ? 'বাতিল' : 'Reject' ?></button>
                                </form>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
</section>
