<?php
declare(strict_types=1);

$lang = current_lang();
$flt = admin_shop_orders_filter_query();
$status = $flt['status'];
$method = $flt['method'];
$retFilter = $flt['retFilter'];
$dupsOnly = $flt['dupsOnly'];
$dateFrom = $flt['dateFrom'];
$dateTo = $flt['dateTo'];
$q = $flt['q'];
$page = max(1, (int) ($_GET['page'] ?? 1));
$perPage = 25;

$baseFrom = 'FROM shop_orders so LEFT JOIN users u ON u.id = so.user_id';
$totalRows = (int) (db_one("SELECT COUNT(*) AS c {$baseFrom}{$flt['whereSql']}", $flt['params'])['c'] ?? 0);
$totalPages = max(1, (int) ceil($totalRows / $perPage));
if ($page > $totalPages) {
    $page = $totalPages;
}
$offset = ($page - 1) * $perPage;

$orders = db_all(
    "SELECT so.*, u.name AS account_name, u.email AS account_email
     {$baseFrom}{$flt['whereSql']}
     ORDER BY (so.status = 'pending') DESC, (so.return_status = 'requested') DESC, so.id DESC
     LIMIT {$perPage} OFFSET {$offset}",
    $flt['params']
);

$orderItemsById = [];
if ($orders) {
    $oids = array_map(static fn(array $r): int => (int) $r['id'], $orders);
    $ph = implode(',', array_fill(0, count($oids), '?'));
    $itemsRows = db_all("SELECT * FROM shop_order_items WHERE shop_order_id IN ({$ph}) ORDER BY id ASC", $oids);
    foreach ($itemsRows as $ir) {
        $orderItemsById[(int) $ir['shop_order_id']][] = $ir;
    }
}
$dupTrxMap = shop_duplicate_trx_map($orders);

$counts = db_one(
    "SELECT COUNT(*) AS all_cnt,
            SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) AS pend_cnt,
            SUM(CASE WHEN status = 'confirmed' THEN 1 ELSE 0 END) AS conf_cnt,
            SUM(CASE WHEN status = 'packed' THEN 1 ELSE 0 END) AS pack_cnt,
            SUM(CASE WHEN status = 'shipped' THEN 1 ELSE 0 END) AS ship_cnt,
            SUM(CASE WHEN status = 'delivered' THEN 1 ELSE 0 END) AS del_cnt,
            SUM(CASE WHEN status = 'cancelled' THEN 1 ELSE 0 END) AS can_cnt,
            SUM(CASE WHEN status = 'returned' THEN 1 ELSE 0 END) AS ret_cnt,
            SUM(CASE WHEN return_status = 'requested' THEN 1 ELSE 0 END) AS rreq_cnt
     FROM shop_orders"
);

$currentListUrl = $_SERVER['REQUEST_URI'] ?? '/admin/shop-orders';
$buildShopOrdersUrl = static function (array $ov = []) use ($status, $method, $retFilter, $dupsOnly, $dateFrom, $dateTo, $q, $page): string {
    $query = [
        'status'    => $ov['status'] ?? $status,
        'method'    => $ov['method'] ?? $method,
        'return_st' => $ov['return_st'] ?? $retFilter,
        'dups'      => $ov['dups'] ?? ($dupsOnly ? '1' : ''),
        'date_from' => $ov['date_from'] ?? $dateFrom,
        'date_to'   => $ov['date_to'] ?? $dateTo,
        'q'         => $ov['q'] ?? $q,
        'page'      => $ov['page'] ?? ($page > 1 ? (string) $page : ''),
    ];
    if (isset($ov['export'])) {
        $query['export'] = $ov['export'];
    }
    $clean = [];
    foreach ($query as $k => $v) {
        if ($v !== '' && !($k === 'status' && $v === 'all') && !($k === 'method' && $v === 'all') && !($k === 'return_st' && $v === 'all')) {
            $clean[$k] = $v;
        }
    }
    return url('/admin/shop-orders' . ($clean ? ('?' . http_build_query($clean)) : ''));
};
?>

<section class="bx-card" style="margin-bottom:18px;padding:14px 18px;">
    <div style="display:flex;justify-content:space-between;align-items:center;gap:10px;flex-wrap:wrap;margin-bottom:12px;">
        <div style="display:flex;gap:8px;flex-wrap:wrap;">
            <?php
            $statusTabs = [
                'all'       => ['bn' => 'সবগুলো', 'en' => 'All', 'cnt' => (int) ($counts['all_cnt'] ?? 0)],
                'pending'   => ['bn' => 'অপেক্ষমাণ', 'en' => 'Pending', 'cnt' => (int) ($counts['pend_cnt'] ?? 0)],
                'confirmed' => ['bn' => 'নিশ্চিত', 'en' => 'Confirmed', 'cnt' => (int) ($counts['conf_cnt'] ?? 0)],
                'packed'    => ['bn' => 'প্যাকিং', 'en' => 'Packed', 'cnt' => (int) ($counts['pack_cnt'] ?? 0)],
                'shipped'   => ['bn' => 'শিপড', 'en' => 'Shipped', 'cnt' => (int) ($counts['ship_cnt'] ?? 0)],
                'delivered' => ['bn' => 'ডেলিভার্ড', 'en' => 'Delivered', 'cnt' => (int) ($counts['del_cnt'] ?? 0)],
                'cancelled' => ['bn' => 'বাতিল', 'en' => 'Cancelled', 'cnt' => (int) ($counts['can_cnt'] ?? 0)],
                'returned'  => ['bn' => 'রিটার্ন', 'en' => 'Returned', 'cnt' => (int) ($counts['ret_cnt'] ?? 0)],
            ];
            foreach ($statusTabs as $sk => $sm): ?>
                <a href="<?= e($buildShopOrdersUrl(['status' => $sk, 'return_st' => 'all', 'page' => '1'])) ?>" class="bx-btn bx-btn-sm <?= ($status === $sk && $retFilter === 'all') ? 'bx-btn-primary' : 'bx-btn-outline' ?>">
                    <?= e($lang === 'bn' ? $sm['bn'] : $sm['en']) ?> (<?= $sm['cnt'] ?>)
                </a>
            <?php endforeach; ?>
            <a href="<?= e($buildShopOrdersUrl(['status' => 'all', 'return_st' => 'requested', 'page' => '1'])) ?>" class="bx-btn bx-btn-sm <?= $retFilter === 'requested' ? 'bx-btn-accent' : 'bx-btn-outline' ?>">
                <?= $lang === 'bn' ? 'রিটার্ন অনুরোধ' : 'Return Requests' ?> (<?= (int) ($counts['rreq_cnt'] ?? 0) ?>)
            </a>
        </div>
        <a href="<?= e($buildShopOrdersUrl(['export' => 'csv'])) ?>" class="bx-btn bx-btn-outline bx-btn-sm">↓ <?= $lang === 'bn' ? 'CSV এক্সপোর্ট' : 'Export CSV' ?></a>
    </div>

    <form method="get" action="<?= e(url('/admin/shop-orders')) ?>" style="display:grid;grid-template-columns:repeat(auto-fit,minmax(min(100%,155px),1fr));gap:10px;align-items:end;">
        <input type="hidden" name="status" value="<?= e($status) ?>">
        <input type="hidden" name="return_st" value="<?= e($retFilter) ?>">
        <div>
            <label style="display:block;font-size:0.78rem;font-weight:600;margin-bottom:3px;"><?= $lang === 'bn' ? 'খুঁজুন (কোড, ফোন, TrxID)' : 'Search (Code, Phone, TrxID)' ?></label>
            <input type="search" name="q" class="bx-input" value="<?= e($q) ?>" placeholder="SHP-..., 017..., TrxID...">
        </div>
        <div>
            <label style="display:block;font-size:0.78rem;font-weight:600;margin-bottom:3px;"><?= $lang === 'bn' ? 'পেমেন্ট' : 'Method' ?></label>
            <select name="method" class="bx-input">
                <option value="all"><?= $lang === 'bn' ? 'সবগুলো' : 'All' ?></option>
                <?php foreach (['cod', 'bkash', 'nagad', 'rocket'] as $mKey): ?>
                    <option value="<?= e($mKey) ?>" <?= $method === $mKey ? 'selected' : '' ?>><?= e(strtoupper($mKey)) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div>
            <label style="display:block;font-size:0.78rem;font-weight:600;margin-bottom:3px;"><?= $lang === 'bn' ? 'শুরু' : 'From' ?></label>
            <input type="date" name="date_from" class="bx-input" value="<?= e($dateFrom) ?>">
        </div>
        <div>
            <label style="display:block;font-size:0.78rem;font-weight:600;margin-bottom:3px;"><?= $lang === 'bn' ? 'শেষ' : 'To' ?></label>
            <input type="date" name="date_to" class="bx-input" value="<?= e($dateTo) ?>">
        </div>
        <div style="display:flex;gap:8px;">
            <button type="submit" class="bx-btn bx-btn-primary" style="flex:1;"><?= $lang === 'bn' ? 'ফিল্টার' : 'Filter' ?></button>
            <?php if ($q !== '' || $method !== 'all' || $status !== 'all' || $retFilter !== 'all' || $dateFrom !== '' || $dateTo !== '' || $dupsOnly): ?>
                <a href="<?= e(url('/admin/shop-orders')) ?>" class="bx-btn bx-btn-ghost"><?= $lang === 'bn' ? 'রিসেট' : 'Reset' ?></a>
            <?php endif; ?>
        </div>
    </form>
</section>

<section class="bx-card">
    <form id="bx-bulk-shop-orders-form" method="post" action="<?= e(url('/admin/shop-orders')) ?>" style="display:flex;gap:8px;flex-wrap:wrap;align-items:center;margin-bottom:14px;padding:10px 12px;border-radius:12px;background:var(--bg-alt);border:1px solid var(--border);">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="shop_bulk_orders">
        <input type="hidden" name="_return" value="<?= e($currentListUrl) ?>">
        <label style="display:inline-flex;align-items:center;gap:6px;font-weight:600;font-size:0.86rem;">
            <input type="checkbox" class="bx-select-all-targets" data-checkbox-target=".bx-shop-order-check" style="width:18px;height:18px;">
            <span><?= $lang === 'bn' ? 'সব নির্বাচন' : 'Select All' ?></span>
        </label>
        <select name="bulk_status" class="bx-input" style="width:auto;min-width:155px;padding:6px 10px;">
            <?php foreach (SHOP_ORDER_STATUSES as $stOpt): ?>
                <option value="<?= e($stOpt) ?>"><?= e(ucfirst($stOpt)) ?></option>
            <?php endforeach; ?>
        </select>
        <input type="text" name="bulk_note" class="bx-input" style="flex:1 1 200px;min-width:0;padding:6px 10px;" placeholder="<?= e($lang === 'bn' ? 'বাল্ক কুরিয়ার / গ্রাহক নোট...' : 'Optional public note / reason...') ?>">
        <button type="submit" class="bx-btn bx-btn-primary bx-btn-sm" data-confirm="<?= e($lang === 'bn' ? 'নির্বাচিত অর্ডারগুলোর অবস্থা পরিবর্তন করবেন?' : 'Update status for all selected shop orders?') ?>">
            <?= $lang === 'bn' ? 'বাল্ক প্রয়োগ' : 'Apply Bulk' ?>
        </button>
    </form>

    <div class="bx-table-wrap">
        <table class="bx-table bx-mobile-cards">
            <thead>
                <tr>
                    <th style="width:36px;">✓</th>
                    <th><?= $lang === 'bn' ? 'অর্ডার ও প্রিন্ট' : 'Order & Print' ?></th>
                    <th><?= $lang === 'bn' ? 'গ্রাহক ও ঠিকানা' : 'Customer & Address' ?></th>
                    <th><?= $lang === 'bn' ? 'আইটেম ও হিসাব' : 'Items & Total' ?></th>
                    <th><?= $lang === 'bn' ? 'পেমেন্ট' : 'Payment' ?></th>
                    <th><?= $lang === 'bn' ? 'স্ট্যাটাস, কুরিয়ার ও রিটার্ন' : 'Fulfillment & Actions' ?></th>
                </tr>
            </thead>
            <tbody>
                <?php if (!$orders): ?>
                    <tr class="bx-empty-row"><td colspan="6" style="padding:24px;color:var(--text-muted);"><?= $lang === 'bn' ? 'কোনো শপ অর্ডার পাওয়া যায়নি।' : 'No shop orders found.' ?></td></tr>
                <?php else: foreach ($orders as $o):
                    $oid = (int) $o['id'];
                    $st = (string) $o['status'];
                    $stMeta = shop_order_status_labels($st);
                    $trx = strtoupper(trim((string) ($o['transaction_id'] ?? '')));
                    $dupCnt = $trx !== '' ? ($dupTrxMap[$trx] ?? 0) : 0;
                    $lineItems = $orderItemsById[$oid] ?? [];
                    $retSt = (string) ($o['return_status'] ?? 'none');
                ?>
                    <tr>
                        <td style="text-align:center;">
                            <input type="checkbox" form="bx-bulk-shop-orders-form" name="ids[]" value="<?= $oid ?>" class="bx-shop-order-check" style="width:18px;height:18px;">
                        </td>
                        <td class="bx-td-primary">
                            <strong style="display:block;">#<?= e($o['order_code']) ?></strong>
                            <small style="color:var(--text-muted);display:block;margin-bottom:6px;"><?= e(date('d M Y, h:i A', strtotime((string) $o['created_at']))) ?></small>
                            <div style="display:flex;gap:6px;flex-wrap:wrap;">
                                <a href="<?= e(url('/shop/orders/' . rawurlencode((string) $o['order_code']) . '/invoice')) ?>" target="_blank" rel="noopener" class="bx-btn bx-btn-outline bx-btn-sm">
                                    <?= $lang === 'bn' ? 'ইনভয়েস' : 'Invoice' ?>
                                </a>
                                <a href="<?= e(url('/admin/shop-packing-slip?order_id=' . $oid)) ?>" target="_blank" rel="noopener" class="bx-btn bx-btn-ghost bx-btn-sm">
                                    <?= $lang === 'bn' ? 'প্যাকিং স্লিপ' : 'Packing Slip' ?>
                                </a>
                            </div>
                        </td>
                        <td data-label="<?= e($lang === 'bn' ? 'গ্রাহক' : 'Customer') ?>">
                            <strong><?= e($o['customer_name']) ?></strong>
                            <div><?= e($o['customer_phone']) ?></div>
                            <?php if (!empty($o['customer_email'])): ?>
                                <small class="bx-ellipsis" style="color:var(--text-muted);display:block;"><?= e($o['customer_email']) ?></small>
                            <?php endif; ?>
                            <div style="font-size:0.84rem;margin-top:4px;max-width:220px;overflow-wrap:anywhere;">
                                <?= e($o['shipping_address']) ?><br>
                                <strong><?= e($o['district']) ?></strong> (<?= $o['delivery_area'] === 'inside_dhaka' ? 'Inside Dhaka' : 'Outside Dhaka' ?>)
                            </div>
                        </td>
                        <td data-label="<?= e($lang === 'bn' ? 'আইটেম' : 'Items') ?>">
                            <div style="font-size:0.85rem;display:flex;flex-direction:column;gap:3px;">
                                <?php foreach ($lineItems as $li): ?>
                                    <div><?= (int) $li['quantity'] ?>× <?= e($lang === 'bn' ? $li['title_bn'] : $li['title_en']) ?></div>
                                <?php endforeach; ?>
                            </div>
                            <div style="margin-top:6px;font-size:0.84rem;border-top:1px dashed var(--border);padding-top:4px;">
                                Subtotal: ৳<?= number_format((float) $o['subtotal_amount'], 0) ?>
                                <?php if ((float) $o['discount_amount'] > 0): ?>
                                    • Disc: -৳<?= number_format((float) $o['discount_amount'], 0) ?> (<?= e((string) $o['coupon_code']) ?>)
                                <?php endif; ?>
                                • Del: ৳<?= number_format((float) $o['delivery_fee'], 0) ?>
                                <div style="font-weight:800;color:var(--primary);">Total: ৳<?= number_format((float) $o['total_amount'], 0) ?></div>
                            </div>
                        </td>
                        <td data-label="<?= e($lang === 'bn' ? 'পেমেন্ট' : 'Payment') ?>">
                            <span class="bx-badge"><?= e(strtoupper((string) $o['payment_method'])) ?></span>
                            <?php if (!empty($o['sender_number'])): ?>
                                <div style="font-size:0.84rem;margin-top:4px;">Sender: <?= e($o['sender_number']) ?></div>
                            <?php endif; ?>
                            <?php if ($trx !== ''): ?>
                                <div style="font-size:0.84rem;">TrxID: <strong><?= e($trx) ?></strong></div>
                            <?php endif; ?>
                            <?php if (!empty($o['payment_screenshot'])): ?>
                                <div style="margin-top:4px;">
                                    <a href="<?= e(url('/admin/shop-payment-proof?order_id=' . $oid)) ?>" target="_blank" rel="noopener" style="font-size:0.82rem;font-weight:700;color:var(--teal);">
                                        <?= $lang === 'bn' ? 'স্ক্রিনশট দেখুন' : 'View Proof' ?>
                                    </a>
                                </div>
                            <?php endif; ?>
                            <?php if ($dupCnt > 1): ?>
                                <div style="margin-top:4px;">
                                    <span class="bx-status-pill st-rejected">⚠ Duplicate TrxID (<?= $dupCnt ?>)</span>
                                </div>
                            <?php endif; ?>
                        </td>
                        <td class="bx-td-actions">
                            <div style="display:flex;flex-direction:column;gap:8px;width:100%;">
                                <span class="bx-status-pill <?= e($stMeta['pill']) ?>" style="align-self:flex-start;">
                                    <?= e($lang === 'bn' ? $stMeta['bn'] : $stMeta['en']) ?>
                                </span>

                                <form method="post" action="<?= e(url('/admin/shop-orders')) ?>" style="display:flex;flex-direction:column;gap:6px;">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="action" value="shop_order_update">
                                    <input type="hidden" name="order_id" value="<?= $oid ?>">
                                    <input type="hidden" name="_return" value="<?= e($currentListUrl) ?>">
                                    <select name="status" class="bx-input">
                                        <?php foreach (SHOP_ORDER_STATUSES as $stOpt): $sLbl = shop_order_status_labels($stOpt); ?>
                                            <option value="<?= e($stOpt) ?>" <?= $st === $stOpt ? 'selected' : '' ?>><?= e($lang === 'bn' ? $sLbl['bn'] : $sLbl['en']) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                    <div style="display:flex;gap:6px;flex-wrap:wrap;">
                                        <input type="text" name="courier_name" class="bx-input" style="flex:1 1 90px;min-width:0;" value="<?= e((string) ($o['courier_name'] ?? '')) ?>" placeholder="<?= e($lang === 'bn' ? 'কুরিয়ার' : 'Courier') ?>">
                                        <input type="text" name="tracking_code" class="bx-input" style="flex:1 1 90px;min-width:0;" value="<?= e((string) ($o['tracking_code'] ?? '')) ?>" placeholder="<?= e($lang === 'bn' ? 'ট্র্যাকিং কোড' : 'Tracking #') ?>">
                                    </div>
                                    <input type="text" name="admin_note" class="bx-input" value="<?= e((string) ($o['admin_note'] ?? '')) ?>" placeholder="<?= e($lang === 'bn' ? 'গ্রাহকের জন্য নোট' : 'Public note to customer') ?>">
                                    <input type="text" name="internal_notes" class="bx-input" value="<?= e((string) ($o['internal_notes'] ?? '')) ?>" placeholder="<?= e($lang === 'bn' ? 'অভ্যন্তরীণ স্টাফ নোট' : 'Internal staff note') ?>">
                                    <button type="submit" class="bx-btn bx-btn-primary bx-btn-sm"><?= $lang === 'bn' ? 'সংরক্ষণ ও নোটিফাই' : 'Update & Notify' ?></button>
                                </form>

                                <?php if ($retSt !== 'none'): $rMeta = shop_return_status_labels($retSt); ?>
                                    <div style="padding:8px 10px;border-radius:10px;background:var(--bg-alt);border:1px solid var(--border);font-size:0.84rem;">
                                        <span class="bx-status-pill <?= e($rMeta['pill']) ?>"><?= e($lang === 'bn' ? $rMeta['bn'] : $rMeta['en']) ?></span>
                                        <div style="margin-top:4px;"><strong>Reason:</strong> <?= e((string) ($o['return_reason'] ?? '')) ?></div>
                                        <?php if ($retSt === 'requested'): ?>
                                            <form method="post" action="<?= e(url('/admin/shop-orders')) ?>" style="margin-top:6px;display:flex;flex-direction:column;gap:6px;">
                                                <?= csrf_field() ?>
                                                <input type="hidden" name="action" value="shop_handle_return">
                                                <input type="hidden" name="order_id" value="<?= $oid ?>">
                                                <input type="hidden" name="_return" value="<?= e($currentListUrl) ?>">
                                                <label style="display:flex;align-items:center;gap:6px;font-size:0.8rem;">
                                                    <input type="checkbox" name="restock" value="1" checked>
                                                    <?= $lang === 'bn' ? 'স্টক ফেরত দিন' : 'Restore stock on return approval' ?>
                                                </label>
                                                <input type="text" name="return_note" class="bx-input" placeholder="<?= e($lang === 'bn' ? 'রিটার্ন মন্তব্য...' : 'Return resolution note...') ?>">
                                                <div style="display:flex;gap:6px;flex-wrap:wrap;">
                                                    <button type="submit" name="decision" value="approved" class="bx-btn bx-btn-primary bx-btn-sm"><?= $lang === 'bn' ? 'রিটার্ন অনুমোদন' : 'Approve Return' ?></button>
                                                    <button type="submit" name="decision" value="rejected" class="bx-btn bx-btn-outline bx-btn-sm"><?= $lang === 'bn' ? 'রিজেক্ট' : 'Reject' ?></button>
                                                </div>
                                            </form>
                                        <?php endif; ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>

    <?php if ($totalPages > 1): ?>
        <nav class="bx-pagination" aria-label="Shop Orders Pagination">
            <span style="font-size:0.85rem;color:var(--text-muted);">Page <?= $page ?> of <?= $totalPages ?></span>
            <div style="display:flex;gap:6px;flex-wrap:wrap;">
                <?php if ($page > 1): ?>
                    <a href="<?= e($buildShopOrdersUrl(['page' => (string) ($page - 1)])) ?>">« Prev</a>
                <?php endif; ?>
                <?php for ($p = max(1, $page - 2); $p <= min($totalPages, $page + 2); $p++): ?>
                    <a href="<?= e($buildShopOrdersUrl(['page' => (string) $p])) ?>" class="<?= $p === $page ? 'active' : '' ?>"><?= $p ?></a>
                <?php endfor; ?>
                <?php if ($page < $totalPages): ?>
                    <a href="<?= e($buildShopOrdersUrl(['page' => (string) ($page + 1)])) ?>">Next »</a>
                <?php endif; ?>
            </div>
        </nav>
    <?php endif; ?>
</section>
