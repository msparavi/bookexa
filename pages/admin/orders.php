<?php
declare(strict_types=1);

$lang    = current_lang();
$flt     = admin_orders_filter_query();
$status  = $flt['status'];
$method  = $flt['method'];
$dupsOnly = $flt['dupsOnly'];
$dateFrom = $flt['dateFrom'];
$dateTo   = $flt['dateTo'];
$userId   = $flt['userId'];
$itemId   = $flt['itemId'];
$q        = $flt['q'];
$page    = max(1, (int) ($_GET['page'] ?? 1));
$perPage = 25;

// Identify duplicated TrxIDs and repeated sender numbers across all orders
$dupTrxRows = db_all(
    "SELECT transaction_id, COUNT(*) AS cnt
     FROM orders
     WHERE transaction_id IS NOT NULL AND transaction_id <> '' AND transaction_id NOT LIKE 'COUPON-%'
     GROUP BY transaction_id
     HAVING COUNT(*) > 1"
);
$dupTrxMap = [];
foreach ($dupTrxRows as $r) {
    $dupTrxMap[(string) $r['transaction_id']] = (int) $r['cnt'];
}

$dupSenderRows = db_all(
    "SELECT sender_number, COUNT(*) AS cnt
     FROM orders
     WHERE sender_number IS NOT NULL AND sender_number <> '' AND sender_number NOT LIKE 'COUPON-%'
     GROUP BY sender_number
     HAVING COUNT(*) > 1"
);
$dupSenderMap = [];
foreach ($dupSenderRows as $r) {
    $dupSenderMap[(string) $r['sender_number']] = (int) $r['cnt'];
}

$baseFrom = 'FROM orders o JOIN users u ON u.id = o.user_id JOIN items i ON i.id = o.item_id';
$totalRows  = (int) (db_one("SELECT COUNT(*) AS c {$baseFrom}{$flt['whereSql']}", $flt['params'])['c'] ?? 0);
$totalPages = max(1, (int) ceil($totalRows / $perPage));
if ($page > $totalPages) {
    $page = $totalPages;
}
$offset = ($page - 1) * $perPage;

$orders = db_all(
    "SELECT o.*, u.name AS user_name, u.email AS user_email, u.phone AS user_phone,
            i.title_bn, i.title_en, i.slug AS item_slug
     {$baseFrom}{$flt['whereSql']}
     ORDER BY (o.status = 'pending') DESC, o.created_at DESC, o.id DESC
     LIMIT {$perPage} OFFSET {$offset}",
    $flt['params']
);

$countsRow = db_one(
    "SELECT COUNT(*) AS all_cnt,
            SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) AS pend_cnt,
            SUM(CASE WHEN status = 'approved' THEN 1 ELSE 0 END) AS app_cnt,
            SUM(CASE WHEN status = 'rejected' THEN 1 ELSE 0 END) AS rej_cnt,
            SUM(CASE WHEN status = 'cancelled' THEN 1 ELSE 0 END) AS can_cnt,
            SUM(CASE WHEN status = 'expired' THEN 1 ELSE 0 END) AS exp_cnt
     FROM orders"
);
$pendingTotal = (int) ($countsRow['pend_cnt'] ?? 0);

// Select options for User & Item filters
$filterUsers = db_all('SELECT id, name, email FROM users ORDER BY id DESC LIMIT 150');
$filterItems = db_all('SELECT id, title_en, title_bn FROM items ORDER BY title_en ASC LIMIT 150');

$buildOrdersUrl = static function (array $overrides = []) use ($status, $method, $dupsOnly, $dateFrom, $dateTo, $userId, $itemId, $q, $page): string {
    $query = [
        'status'    => $status,
        'method'    => $method,
        'dups'      => $dupsOnly ? '1' : '',
        'date_from' => $dateFrom,
        'date_to'   => $dateTo,
        'user_id'   => $userId > 0 ? (string) $userId : '',
        'item_id'   => $itemId > 0 ? (string) $itemId : '',
        'q'         => $q,
        'page'      => $page > 1 ? (string) $page : '',
    ];
    foreach ($overrides as $k => $v) {
        $query[$k] = (string) $v;
    }
    if (($query['status'] ?? '') === 'all') {
        $query['status'] = '';
    }
    if (($query['method'] ?? '') === 'all') {
        $query['method'] = '';
    }
    if (($query['page'] ?? '') === '1') {
        $query['page'] = '';
    }
    $clean = array_filter($query, static fn(string $v): bool => $v !== '');
    return url('/admin/orders' . ($clean ? ('?' . http_build_query($clean)) : ''));
};

$currentListUrl = $buildOrdersUrl();
$csvExportUrl   = $buildOrdersUrl(['export' => 'csv', 'page' => '']);
$showFrom = $totalRows > 0 ? ($offset + 1) : 0;
$showTo   = min($totalRows, $offset + count($orders));
?>

<section class="bx-card" style="margin-bottom:18px;">
    <div style="display:flex;gap:8px;flex-wrap:wrap;align-items:center;justify-content:space-between;margin-bottom:12px;">
        <div style="display:flex;gap:8px;flex-wrap:wrap;align-items:center;">
            <a href="<?= e($buildOrdersUrl(['status' => 'pending', 'page' => '1'])) ?>"
               class="bx-btn <?= $status === 'pending' ? 'bx-btn-primary' : 'bx-btn-outline' ?> bx-btn-sm">
                ⏳ <?= $lang === 'bn' ? 'অপেক্ষমাণ (Pending)' : 'Pending' ?>
                <span class="bx-badge" style="margin-left:4px;"><?= $pendingTotal ?></span>
            </a>
            <a href="<?= e($buildOrdersUrl(['status' => 'all', 'page' => '1'])) ?>"
               class="bx-btn <?= $status === 'all' ? 'bx-btn-primary' : 'bx-btn-outline' ?> bx-btn-sm">
                <?= $lang === 'bn' ? 'সব' : 'All' ?> (<?= (int) ($countsRow['all_cnt'] ?? 0) ?>)
            </a>
            <a href="<?= e($buildOrdersUrl(['status' => 'approved', 'page' => '1'])) ?>"
               class="bx-btn <?= $status === 'approved' ? 'bx-btn-primary' : 'bx-btn-outline' ?> bx-btn-sm">
                <?= $lang === 'bn' ? 'অনুমোদিত' : 'Approved' ?> (<?= (int) ($countsRow['app_cnt'] ?? 0) ?>)
            </a>
            <a href="<?= e($buildOrdersUrl(['status' => 'rejected', 'page' => '1'])) ?>"
               class="bx-btn <?= $status === 'rejected' ? 'bx-btn-primary' : 'bx-btn-outline' ?> bx-btn-sm">
                <?= $lang === 'bn' ? 'রিজেক্টেড' : 'Rejected' ?> (<?= (int) ($countsRow['rej_cnt'] ?? 0) ?>)
            </a>
            <a href="<?= e($buildOrdersUrl(['status' => 'cancelled', 'page' => '1'])) ?>"
               class="bx-btn <?= $status === 'cancelled' ? 'bx-btn-primary' : 'bx-btn-outline' ?> bx-btn-sm">
                <?= $lang === 'bn' ? 'বাতিলকৃত' : 'Cancelled' ?> (<?= (int) ($countsRow['can_cnt'] ?? 0) ?>)
            </a>
            <a href="<?= e($buildOrdersUrl(['status' => 'expired', 'page' => '1'])) ?>"
               class="bx-btn <?= $status === 'expired' ? 'bx-btn-primary' : 'bx-btn-outline' ?> bx-btn-sm">
                <?= $lang === 'bn' ? 'মেয়াদোত্তীর্ণ' : 'Expired' ?> (<?= (int) ($countsRow['exp_cnt'] ?? 0) ?>)
            </a>
            <a href="<?= e($buildOrdersUrl(['dups' => $dupsOnly ? '' : '1', 'page' => '1'])) ?>"
               class="bx-btn <?= $dupsOnly ? 'bx-btn-accent' : 'bx-btn-ghost' ?> bx-btn-sm">
                ⚠ <?= $lang === 'bn' ? 'ডুপ্লিকেট TrxID/নম্বর' : 'Duplicates' ?>
            </a>
        </div>
        <a href="<?= e($csvExportUrl) ?>" class="bx-btn bx-btn-outline bx-btn-sm">
            ⬇ <?= $lang === 'bn' ? 'CSV ডাউনলোড' : 'Export CSV' ?>
        </a>
    </div>

    <form method="get" action="<?= e(url('/admin/orders')) ?>" style="display:grid;grid-template-columns:repeat(auto-fit,minmax(min(100%,170px),1fr));gap:10px;align-items:end;">
        <?php if ($status !== 'all'): ?><input type="hidden" name="status" value="<?= e($status) ?>"><?php endif; ?>
        <?php if ($dupsOnly): ?><input type="hidden" name="dups" value="1"><?php endif; ?>
        <div>
            <label style="display:block;font-size:0.78rem;font-weight:600;margin-bottom:3px;"><?= $lang === 'bn' ? 'খুঁজুন (অর্ডার / TrxID / ফোন)' : 'Search (Code / TrxID / Phone / Buyer)' ?></label>
            <input type="search" name="q" value="<?= e($q) ?>" class="bx-input" style="min-width:0;width:100%;" placeholder="BX-..., TrxID, 017..., email">
        </div>
        <div>
            <label style="display:block;font-size:0.78rem;font-weight:600;margin-bottom:3px;"><?= $lang === 'bn' ? 'মাধ্যম' : 'Method' ?></label>
            <select name="method" class="bx-input">
                <option value="all" <?= $method === 'all' ? 'selected' : '' ?>>All Methods</option>
                <option value="bkash" <?= $method === 'bkash' ? 'selected' : '' ?>>bKash</option>
                <option value="nagad" <?= $method === 'nagad' ? 'selected' : '' ?>>Nagad</option>
                <option value="rocket" <?= $method === 'rocket' ? 'selected' : '' ?>>Rocket</option>
                <option value="free" <?= $method === 'free' ? 'selected' : '' ?>>Free (Coupon)</option>
                <option value="bkash_tokenized" <?= $method === 'bkash_tokenized' ? 'selected' : '' ?>>bKash Tokenized</option>
                <option value="aamarpay" <?= $method === 'aamarpay' ? 'selected' : '' ?>>aamarPay</option>
                <option value="uddoktapay" <?= $method === 'uddoktapay' ? 'selected' : '' ?>>UddoktaPay</option>
            </select>
        </div>
        <div>
            <label style="display:block;font-size:0.78rem;font-weight:600;margin-bottom:3px;"><?= $lang === 'bn' ? 'শুরু তারিখ' : 'From Date' ?></label>
            <input type="date" name="date_from" value="<?= e($dateFrom) ?>" class="bx-input">
        </div>
        <div>
            <label style="display:block;font-size:0.78rem;font-weight:600;margin-bottom:3px;"><?= $lang === 'bn' ? 'শেষ তারিখ' : 'To Date' ?></label>
            <input type="date" name="date_to" value="<?= e($dateTo) ?>" class="bx-input">
        </div>
        <div>
            <label style="display:block;font-size:0.78rem;font-weight:600;margin-bottom:3px;"><?= $lang === 'bn' ? 'ক্রেতা' : 'Customer' ?></label>
            <select name="user_id" class="bx-input">
                <option value=""><?= $lang === 'bn' ? 'সব ক্রেতা' : 'All Customers' ?></option>
                <?php foreach ($filterUsers as $fu): ?>
                    <option value="<?= (int) $fu['id'] ?>" <?= $userId === (int) $fu['id'] ? 'selected' : '' ?>><?= e($fu['name'] . ' (' . $fu['email'] . ')') ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div>
            <label style="display:block;font-size:0.78rem;font-weight:600;margin-bottom:3px;"><?= $lang === 'bn' ? 'বই' : 'Book (PDF)' ?></label>
            <select name="item_id" class="bx-input">
                <option value=""><?= $lang === 'bn' ? 'সব বই' : 'All Books' ?></option>
                <?php foreach ($filterItems as $fi): ?>
                    <option value="<?= (int) $fi['id'] ?>" <?= $itemId === (int) $fi['id'] ? 'selected' : '' ?>><?= e($lang === 'bn' ? $fi['title_bn'] : $fi['title_en']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div style="display:flex;gap:8px;">
            <button type="submit" class="bx-btn bx-btn-primary" style="flex:1;"><?= $lang === 'bn' ? 'ফিল্টার' : 'Filter' ?></button>
            <?php if ($q !== '' || $method !== 'all' || $status !== 'all' || $dupsOnly || $dateFrom !== '' || $dateTo !== '' || $userId > 0 || $itemId > 0): ?>
                <a href="<?= e(url('/admin/orders')) ?>" class="bx-btn bx-btn-ghost"><?= $lang === 'bn' ? 'রিসেট' : 'Reset' ?></a>
            <?php endif; ?>
        </div>
    </form>
</section>

<section class="bx-card">
    <div style="display:flex;justify-content:space-between;align-items:center;gap:10px;flex-wrap:wrap;margin-bottom:12px;">
        <h2 style="margin:0;font-size:1.12rem;font-family:var(--font-serif);">
            <?= $lang === 'bn' ? 'অর্ডার তালিকা' : 'Orders' ?> (<?= $totalRows ?>)
        </h2>
        <small style="color:var(--text-muted);">
            <?= $totalRows > 0 ? "Showing {$showFrom}–{$showTo} of {$totalRows}" : '0 orders' ?>
        </small>
    </div>

    <!-- Bulk Approve / Reject Bar -->
    <form id="bx-bulk-orders-form" method="post" action="<?= e(url('/admin/orders')) ?>" style="display:flex;gap:8px;flex-wrap:wrap;align-items:center;margin-bottom:12px;padding:10px 12px;border-radius:12px;background:var(--bg-alt);border:1px solid var(--border);">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="bulk_orders">
        <input type="hidden" name="_return" value="<?= e($currentListUrl) ?>">
        <label style="display:inline-flex;align-items:center;gap:6px;font-weight:600;font-size:0.86rem;">
            <input type="checkbox" id="bx-bulk-select-all" style="width:18px;height:18px;">
            <span><?= $lang === 'bn' ? 'সব নির্বাচন' : 'Select All' ?></span>
        </label>
        <select name="bulk_decision" class="bx-input" style="width:auto;min-width:155px;padding:6px 10px;">
            <option value="approved"><?= $lang === 'bn' ? 'নির্বাচিত অনুমোদন করুন' : 'Approve Selected' ?></option>
            <option value="rejected"><?= $lang === 'bn' ? 'নির্বাচিত রিজেক্ট করুন' : 'Reject Selected' ?></option>
        </select>
        <input type="text" name="bulk_reason" class="bx-input" style="flex:1 1 200px;min-width:0;padding:6px 10px;" placeholder="<?= e($lang === 'bn' ? 'বাল্ক কারণ / নোট (রিজেক্টের জন্য)...' : 'Optional reason / public note (shown if rejected)...') ?>">
        <button type="submit" class="bx-btn bx-btn-primary bx-btn-sm" data-confirm="<?= e($lang === 'bn' ? 'নির্বাচিত অর্ডারগুলোতে এই বাল্ক অ্যাকশন চালাবেন?' : 'Apply bulk decision to selected orders?') ?>">
            <?= $lang === 'bn' ? 'বাল্ক প্রয়োগ' : 'Apply Bulk' ?>
        </button>
    </form>

    <div class="bx-scroll-hint"><?= $lang === 'bn' ? 'বিস্তারিত দেখতে ডানে স্ক্রল করুন →' : 'Scroll horizontally if needed →' ?></div>
    <div class="bx-table-wrap">
        <table class="bx-table bx-mobile-cards">
            <thead>
                <tr>
                    <th style="width:36px;">✓</th>
                    <th><?= $lang === 'bn' ? 'অর্ডার ও সময়' : 'Order' ?></th>
                    <th><?= $lang === 'bn' ? 'ক্রেতা' : 'Customer' ?></th>
                    <th><?= $lang === 'bn' ? 'বই ও পরিমাণ' : 'Book & Amount' ?></th>
                    <th><?= $lang === 'bn' ? 'পেমেন্ট ও TrxID' : 'Payment & Proof' ?></th>
                    <th><?= $lang === 'bn' ? 'অবস্থা ও নোট' : 'Status & Notes' ?></th>
                    <th><?= $lang === 'bn' ? 'পর্যালোচনা' : 'Actions' ?></th>
                </tr>
            </thead>
            <tbody>
                <?php if (!$orders): ?>
                    <tr class="bx-empty-row">
                        <td colspan="7" style="padding:26px;color:var(--text-muted);">
                            <?= $lang === 'bn' ? 'কোনো অর্ডার পাওয়া যায়নি।' : 'No matching orders found.' ?>
                        </td>
                    </tr>
                <?php else: foreach ($orders as $o): ?>
                    <?php
                    $oid = (int) $o['id'];
                    $st  = (string) $o['status'];
                    $stMeta = order_status_labels($st);
                    $trx = trim((string) ($o['transaction_id'] ?? ''));
                    $snd = trim((string) ($o['sender_number'] ?? ''));
                    $trxDupCnt = ($trx !== '' && isset($dupTrxMap[$trx])) ? $dupTrxMap[$trx] : 0;
                    $sndDupCnt = ($snd !== '' && isset($dupSenderMap[$snd])) ? $dupSenderMap[$snd] : 0;
                    $rowHasDup = ($trxDupCnt > 1 || $sndDupCnt > 1);
                    $hasProof  = !empty($o['payment_screenshot']) && resolve_payment_screenshot_path((string) $o['payment_screenshot']) !== null;
                    ?>
                    <tr style="<?= $rowHasDup ? 'background:rgba(229,107,138,0.06);' : '' ?>">
                        <td data-label="Select">
                            <input type="checkbox" class="bx-bulk-cb" form="bx-bulk-orders-form" name="ids[]" value="<?= $oid ?>" style="width:18px;height:18px;">
                        </td>
                        <td class="bx-td-primary">
                            <strong>#<?= e((string) $o['order_code']) ?></strong>
                            <div style="font-size:0.78rem;color:var(--text-muted);"><?= e(date('d M Y, h:i A', strtotime((string) $o['created_at']))) ?></div>
                            <div style="margin-top:4px;">
                                <a href="<?= e(url('/orders/' . rawurlencode((string) $o['order_code']) . '/invoice')) ?>" target="_blank" class="bx-btn bx-btn-ghost bx-btn-sm">
                                    🧾 Invoice
                                </a>
                            </div>
                        </td>
                        <td data-label="<?= e($lang === 'bn' ? 'ক্রেতা' : 'Customer') ?>">
                            <div><strong><?= e((string) $o['user_name']) ?></strong></div>
                            <div style="font-size:0.8rem;color:var(--text-muted);"><?= e((string) $o['user_email']) ?></div>
                            <?php if (!empty($o['user_phone'])): ?>
                                <div style="font-size:0.78rem;color:var(--text-muted);"><?= e((string) $o['user_phone']) ?></div>
                            <?php endif; ?>
                        </td>
                        <td data-label="<?= e($lang === 'bn' ? 'বই ও পরিমাণ' : 'Book & Amount') ?>">
                            <div style="font-weight:600;"><?= e($lang === 'bn' ? $o['title_bn'] : $o['title_en']) ?></div>
                            <div>
                                <strong>৳<?= number_format((float) $o['amount'], 0) ?></strong>
                                <?php if (!empty($o['coupon_code'])): ?>
                                    <span class="bx-badge bx-badge-accent" style="margin-left:4px;">
                                        <?= e((string) $o['coupon_code']) ?> (-৳<?= number_format((float) ($o['discount_amount'] ?? 0), 0) ?>)
                                    </span>
                                <?php endif; ?>
                            </div>
                        </td>
                        <td data-label="<?= e($lang === 'bn' ? 'পেমেন্ট ও TrxID' : 'Payment & Proof') ?>">
                            <div>
                                <span class="bx-badge"><?= e(strtoupper((string) $o['payment_method'])) ?></span>
                            </div>
                            <?php if ($trx !== ''): ?>
                                <div style="margin-top:4px;font-family:monospace;font-size:0.85rem;">TrxID: <strong><?= e($trx) ?></strong></div>
                            <?php endif; ?>
                            <?php if ($snd !== ''): ?>
                                <div style="font-size:0.82rem;color:var(--text-muted);">Sender: <strong><?= e($snd) ?></strong></div>
                            <?php endif; ?>
                            <?php if (!empty($o['gateway_ref'])): ?>
                                <div style="font-size:0.78rem;color:var(--text-muted);">Ref: <code><?= e((string) $o['gateway_ref']) ?></code></div>
                            <?php endif; ?>
                            <?php if ($hasProof): ?>
                                <div style="margin-top:5px;">
                                    <a href="<?= e(url('/admin/payment-proof?order_id=' . $oid)) ?>" target="_blank" class="bx-btn bx-btn-outline bx-btn-sm">
                                        🖼 <?= $lang === 'bn' ? 'স্ক্রিনশট দেখুন' : 'View Screenshot' ?>
                                    </a>
                                </div>
                            <?php endif; ?>
                            <?php if ($trxDupCnt > 1): ?>
                                <div style="margin-top:4px;">
                                    <a href="<?= e($buildOrdersUrl(['status' => 'all', 'method' => 'all', 'dups' => '', 'q' => $trx, 'page' => '1'])) ?>"
                                       class="bx-status-pill st-rejected" style="text-decoration:none;">
                                        ⚠ <?= $lang === 'bn' ? "ডুপ্লিকেট TrxID ({$trxDupCnt}টি)" : "Duplicate TrxID ({$trxDupCnt} orders)" ?>
                                    </a>
                                </div>
                            <?php endif; ?>
                            <?php if ($sndDupCnt > 1): ?>
                                <div style="margin-top:4px;">
                                    <a href="<?= e($buildOrdersUrl(['status' => 'all', 'method' => 'all', 'dups' => '', 'q' => $snd, 'page' => '1'])) ?>"
                                       class="bx-status-pill st-rejected" style="text-decoration:none;">
                                        ⚠ <?= $lang === 'bn' ? "ডুপ্লিকেট প্রেরক নম্বর ({$sndDupCnt}টি)" : "Duplicate Sender ({$sndDupCnt} orders)" ?>
                                    </a>
                                </div>
                            <?php endif; ?>
                        </td>
                        <td data-label="<?= e($lang === 'bn' ? 'অবস্থা' : 'Status') ?>">
                            <span class="bx-status-pill <?= e($stMeta['pill']) ?>"><?= e(ucfirst($st)) ?></span>
                            <?php if (!empty($o['admin_note'])): ?>
                                <div style="font-size:0.78rem;color:var(--text-muted);margin-top:4px;max-width:200px;overflow-wrap:anywhere;">
                                    <strong>Public:</strong> <?= e((string) $o['admin_note']) ?>
                                </div>
                            <?php endif; ?>
                            <form method="post" action="<?= e(url('/admin/orders')) ?>" style="margin-top:6px;display:flex;gap:6px;align-items:center;flex-wrap:wrap;">
                                <?= csrf_field() ?>
                                <input type="hidden" name="action" value="save_order_note">
                                <input type="hidden" name="order_id" value="<?= $oid ?>">
                                <input type="hidden" name="_return" value="<?= e($currentListUrl) ?>">
                                <input type="text" name="internal_notes" class="bx-input" style="flex:1 1 110px;min-width:0;" value="<?= e((string) ($o['internal_notes'] ?? '')) ?>" placeholder="<?= e($lang === 'bn' ? 'স্টাফ নোট...' : 'Internal staff note...') ?>">
                                <button type="submit" class="bx-btn bx-btn-ghost bx-btn-sm"><?= $lang === 'bn' ? 'সেভ' : 'Save' ?></button>
                            </form>
                        </td>
                        <td class="bx-td-actions">
                            <form method="post" action="<?= e(url('/admin/orders')) ?>" style="display:flex;flex-direction:column;gap:6px;min-width:0;width:100%;">
                                <?= csrf_field() ?>
                                <input type="hidden" name="action" value="review_order">
                                <input type="hidden" name="order_id" value="<?= $oid ?>">
                                <input type="hidden" name="_return" value="<?= e($currentListUrl) ?>">
                                <input type="text" name="admin_note" class="bx-input" style="min-width:0;width:100%;"
                                       value="<?= e((string) ($o['admin_note'] ?? '')) ?>"
                                       placeholder="<?= e($lang === 'bn' ? 'গ্রাহকের জন্য কারণ/নোট' : 'Reject reason / public note') ?>">
                                <div style="display:flex;gap:6px;flex-wrap:wrap;">
                                    <?php if ($st !== 'approved'): ?>
                                        <button type="submit" name="decision" value="approved" class="bx-btn bx-btn-primary bx-btn-sm">
                                            <?= $lang === 'bn' ? 'অনুমোদন' : 'Approve' ?>
                                        </button>
                                    <?php endif; ?>
                                    <?php if ($st !== 'rejected'): ?>
                                        <button type="submit" name="decision" value="rejected" class="bx-btn bx-btn-outline bx-btn-sm"
                                                data-confirm="<?= e($lang === 'bn' ? 'এই অর্ডারটি রিজেক্ট করতে চান?' : 'Reject this order? Associated coupon usage (if any) will be released.') ?>">
                                            <?= $lang === 'bn' ? 'রিজেক্ট' : 'Reject' ?>
                                        </button>
                                    <?php endif; ?>
                                </div>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>

    <?php if ($totalPages > 1): ?>
        <nav class="bx-pagination" aria-label="Orders Pagination">
            <span style="font-size:0.85rem;color:var(--text-muted);">
                Page <?= $page ?> of <?= $totalPages ?> (<?= $totalRows ?> total)
            </span>
            <div style="display:flex;gap:6px;flex-wrap:wrap;">
                <?php if ($page > 1): ?>
                    <a href="<?= e($buildOrdersUrl(['page' => (string) ($page - 1)])) ?>">« <?= $lang === 'bn' ? 'আগের' : 'Prev' ?></a>
                <?php endif; ?>
                <?php
                $winStart = max(1, $page - 2);
                $winEnd   = min($totalPages, $page + 2);
                for ($p = $winStart; $p <= $winEnd; $p++):
                ?>
                    <a href="<?= e($buildOrdersUrl(['page' => (string) $p])) ?>" class="<?= $p === $page ? 'active' : '' ?>"><?= $p ?></a>
                <?php endfor; ?>
                <?php if ($page < $totalPages): ?>
                    <a href="<?= e($buildOrdersUrl(['page' => (string) ($page + 1)])) ?>"><?= $lang === 'bn' ? 'পরের' : 'Next' ?> »</a>
                <?php endif; ?>
            </div>
        </nav>
    <?php endif; ?>
</section>
