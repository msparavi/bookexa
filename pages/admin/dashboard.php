<?php
declare(strict_types=1);

$lang = current_lang();

$stats = [
    'users'   => (int) (db_one('SELECT COUNT(*) AS c FROM users')['c'] ?? 0),
    'folders' => (int) (db_one('SELECT COUNT(*) AS c FROM folders')['c'] ?? 0),
    'pdfs'    => (int) (db_one('SELECT COUNT(*) AS c FROM items')['c'] ?? 0),
    'reads'   => (int) (db_one('SELECT COUNT(*) AS c FROM reading_logs')['c'] ?? 0),
    'orders'  => (int) (db_one("SELECT COUNT(*) AS c FROM orders WHERE status = 'pending'")['c'] ?? 0),
    'revenue' => (float) (db_one("SELECT COALESCE(SUM(amount), 0) AS s FROM orders WHERE status = 'approved'")['s'] ?? 0),
];

$readsByDayRows = db_all(
    "SELECT DATE(created_at) AS d, COUNT(*) AS c
     FROM reading_logs
     WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 29 DAY)
     GROUP BY DATE(created_at)"
);
$readsByDayMap = [];
foreach ($readsByDayRows as $r) {
    $readsByDayMap[$r['d']] = (int) $r['c'];
}
$dayLabels7 = [];
$dayValues7 = [];
for ($i = 6; $i >= 0; $i--) {
    $d = date('Y-m-d', strtotime("-{$i} days"));
    $dayLabels7[] = date('d M', strtotime($d));
    $dayValues7[] = $readsByDayMap[$d] ?? 0;
}
$dayLabels30 = [];
$dayValues30 = [];
for ($i = 29; $i >= 0; $i--) {
    $d = date('Y-m-d', strtotime("-{$i} days"));
    $dayLabels30[] = date('d/m', strtotime($d));
    $dayValues30[] = $readsByDayMap[$d] ?? 0;
}

$topFolderRows = db_all(
    "SELECT f.name_en, f.name_bn, COUNT(i.id) AS item_count
     FROM folders f
     LEFT JOIN items i ON i.folder_id = f.id
     GROUP BY f.id
     ORDER BY item_count DESC, f.id ASC
     LIMIT 6"
);
$folderLabels = [];
$folderValues = [];
foreach ($topFolderRows as $fr) {
    $lbl = $lang === 'bn' ? $fr['name_bn'] : $fr['name_en'];
    $folderLabels[] = mb_substr($lbl, 0, 11);
    $folderValues[] = (int) $fr['item_count'];
}

$pendingOrders = db_all(
    "SELECT o.id, o.order_code, o.amount, o.payment_method, o.transaction_id, o.sender_number, o.created_at,
            u.name AS user_name, u.email AS user_email, i.title_en, i.title_bn
     FROM orders o
     JOIN users u ON u.id = o.user_id
     JOIN items i ON i.id = o.item_id
     WHERE o.status = 'pending'
     ORDER BY o.id DESC
     LIMIT 8"
);

$recentAdminLogs = db_all(
    "SELECT admin_name, action, target_type, target_id, details, created_at
     FROM admin_logs ORDER BY id DESC LIMIT 7"
);
$recentOrders = db_all(
    "SELECT o.order_code, o.amount, o.payment_method, o.status, o.created_at, u.name AS user_name, i.title_en, i.title_bn
     FROM orders o
     JOIN users u ON u.id = o.user_id
     JOIN items i ON i.id = o.item_id
     ORDER BY o.id DESC LIMIT 6"
);
?>

<div class="bx-admin-stats">
    <div class="bx-stat-card">
        <div class="bx-stat-label"><?= $lang === 'bn' ? 'মোট ব্যবহারকারী' : 'Total Users' ?></div>
        <div class="bx-stat-num" data-counter="<?= $stats['users'] ?>"><?= $stats['users'] ?></div>
    </div>
    <div class="bx-stat-card">
        <div class="bx-stat-label"><?= $lang === 'bn' ? 'মোট ফোল্ডার' : 'Folders' ?></div>
        <div class="bx-stat-num" data-counter="<?= $stats['folders'] ?>"><?= $stats['folders'] ?></div>
    </div>
    <div class="bx-stat-card">
        <div class="bx-stat-label"><?= $lang === 'bn' ? 'মোট PDF বই' : 'Total PDFs' ?></div>
        <div class="bx-stat-num" data-counter="<?= $stats['pdfs'] ?>"><?= $stats['pdfs'] ?></div>
    </div>
    <div class="bx-stat-card">
        <div class="bx-stat-label"><?= $lang === 'bn' ? 'পঠন কার্যক্রম' : 'Reader Events' ?></div>
        <div class="bx-stat-num" data-counter="<?= $stats['reads'] ?>"><?= $stats['reads'] ?></div>
    </div>
    <div class="bx-stat-card">
        <div class="bx-stat-label"><?= $lang === 'bn' ? 'পেন্ডিং অর্ডার' : 'Pending Orders' ?></div>
        <div class="bx-stat-num" style="color:var(--gold-text);" data-counter="<?= $stats['orders'] ?>"><?= $stats['orders'] ?></div>
    </div>
    <div class="bx-stat-card">
        <div class="bx-stat-label"><?= $lang === 'bn' ? 'অনুমোদিত আয়' : 'Approved Revenue' ?></div>
        <div class="bx-stat-num" style="color:var(--teal-text);">৳<span data-counter="<?= (int) round($stats['revenue']) ?>"><?= number_format($stats['revenue'], 0) ?></span></div>
    </div>
</div>

<!-- Quick Actions -->
<div class="bx-card" style="margin-bottom:20px;display:flex;align-items:center;justify-content:space-between;gap:12px;flex-wrap:wrap;">
    <strong><?= $lang === 'bn' ? 'দ্রুত কাজ' : 'Quick Actions' ?></strong>
    <div style="display:flex;gap:8px;flex-wrap:wrap;">
        <a href="<?= e(url('/admin/pdfs#pdf-form')) ?>" class="bx-btn bx-btn-primary">+ <?= $lang === 'bn' ? 'নতুন PDF' : 'Add PDF' ?></a>
        <a href="<?= e(url('/admin/folders#folder-form-sheet')) ?>" class="bx-btn bx-btn-outline">+ <?= $lang === 'bn' ? 'নতুন ফোল্ডার' : 'New Folder' ?></a>
        <a href="<?= e(url('/admin/banners#banner-form')) ?>" class="bx-btn bx-btn-outline">+ <?= $lang === 'bn' ? 'ব্যানার যুক্ত করুন' : 'Banners' ?></a>
        <a href="<?= e(url('/admin/orders')) ?>" class="bx-btn bx-btn-ghost"><?= $lang === 'bn' ? 'অর্ডার' : 'Orders' ?> (<?= $stats['orders'] ?>)</a>
        <a href="<?= e(url('/admin/logs')) ?>" class="bx-btn bx-btn-ghost"><?= $lang === 'bn' ? 'লগ' : 'Logs' ?></a>
    </div>
</div>

<!-- Responsive SVG Charts (7d / 30d + Empty State) -->
<div class="bx-grid-2" style="margin-bottom:22px;">
    <section class="bx-card">
        <div style="display:flex;justify-content:space-between;align-items:center;gap:8px;margin-bottom:12px;flex-wrap:wrap;">
            <div>
                <h2 style="margin:0;font-size:1.05rem;font-family:var(--font-serif);"><?= $lang === 'bn' ? 'পাঠ কার্যক্রম' : 'Reading Activity' ?></h2>
                <small style="color:var(--text-muted);"><span data-chart-total><?= array_sum($dayValues7) ?></span> <?= $lang === 'bn' ? 'টি ইভেন্ট' : 'events' ?></small>
            </div>
            <div class="bx-chart-range-tabs" role="group" aria-label="Chart range">
                <button type="button" class="bx-chart-range-btn active" data-chart-range="7"><?= $lang === 'bn' ? '৭ দিন' : '7 Days' ?></button>
                <button type="button" class="bx-chart-range-btn" data-chart-range="30"><?= $lang === 'bn' ? '৩০ দিন' : '30 Days' ?></button>
            </div>
        </div>
        <div class="bx-chart-wrap">
            <svg class="bx-chart-svg"
                 data-bar-chart
                 data-active-range="7"
                 data-labels-7='<?= e(json_encode($dayLabels7, JSON_UNESCAPED_UNICODE)) ?>'
                 data-values-7='<?= e(json_encode($dayValues7)) ?>'
                 data-labels-30='<?= e(json_encode($dayLabels30, JSON_UNESCAPED_UNICODE)) ?>'
                 data-values-30='<?= e(json_encode($dayValues30)) ?>'
                 aria-label="Reading activity chart"></svg>
            <div class="bx-chart-empty" style="display:none;">
                <?= $lang === 'bn' ? 'এই সময়কালে কোনো পাঠ কার্যক্রম পাওয়া যায়নি।' : 'No reading activity recorded for this period.' ?>
            </div>
        </div>
    </section>

    <section class="bx-card">
        <div style="display:flex;justify-content:space-between;align-items:center;gap:8px;margin-bottom:12px;flex-wrap:wrap;">
            <h2 style="margin:0;font-size:1.05rem;font-family:var(--font-serif);"><?= $lang === 'bn' ? 'ফোল্ডার অনুযায়ী PDF সংখ্যা' : 'PDFs per Folder' ?></h2>
            <span class="bx-badge"><?= count($folderLabels) ?> <?= $lang === 'bn' ? 'টি' : 'folders' ?></span>
        </div>
        <div class="bx-chart-wrap">
            <svg class="bx-chart-svg"
                 data-bar-chart
                 data-labels='<?= e(json_encode($folderLabels, JSON_UNESCAPED_UNICODE)) ?>'
                 data-values='<?= e(json_encode($folderValues)) ?>'
                 aria-label="PDFs per folder chart"></svg>
            <div class="bx-chart-empty" style="display:none;">
                <?= $lang === 'bn' ? 'কোনো ফোল্ডারে এখনও PDF যুক্ত করা হয়নি।' : 'No PDF items distributed across folders yet.' ?>
            </div>
        </div>
    </section>
</div>

<!-- Actionable Pending Orders Widget -->
<section class="bx-card" style="margin-bottom:22px;">
    <div style="display:flex;justify-content:space-between;align-items:center;gap:10px;flex-wrap:wrap;margin-bottom:12px;">
        <div style="display:flex;align-items:center;gap:8px;flex-wrap:wrap;">
            <h2 style="margin:0;font-size:1.08rem;font-family:var(--font-serif);"><?= $lang === 'bn' ? 'অপেক্ষমাণ অর্ডার (দ্রুত যাচাই)' : 'Pending Orders Queue' ?></h2>
            <span class="bx-status-pill st-pending"><?= count($pendingOrders) ?></span>
        </div>
        <a href="<?= e(url('/admin/orders?status=pending')) ?>" style="font-size:0.86rem;color:var(--primary);font-weight:700;"><?= $lang === 'bn' ? 'সব পেন্ডিং অর্ডার →' : 'View Queue →' ?></a>
    </div>
    <?php if (!$pendingOrders): ?>
        <div class="bx-chart-empty" style="min-height:92px;">
            <?= $lang === 'bn' ? 'কোনো পেন্ডিং অর্ডার অপেক্ষমাণ নেই।' : 'All caught up — zero pending orders awaiting approval.' ?>
        </div>
    <?php else: ?>
        <div class="bx-table-wrap">
            <table class="bx-table bx-mobile-cards">
                <thead>
                    <tr>
                        <th>Order #</th>
                        <th><?= $lang === 'bn' ? 'ক্রেতা' : 'Buyer' ?></th>
                        <th><?= $lang === 'bn' ? 'বই' : 'PDF Item' ?></th>
                        <th>TrxID / <?= $lang === 'bn' ? 'মূল্য' : 'Amount' ?></th>
                        <th><?= $lang === 'bn' ? 'সিদ্ধান্ত' : 'Action' ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($pendingOrders as $po): ?>
                        <tr>
                            <td class="bx-td-primary">
                                <strong>#<?= e($po['order_code']) ?></strong>
                                <div style="font-size:0.78rem;color:var(--text-muted);"><?= e(date('d M H:i', strtotime($po['created_at']))) ?></div>
                            </td>
                            <td data-label="<?= e($lang === 'bn' ? 'ক্রেতা' : 'Buyer') ?>"><?= e($po['user_name']) ?></td>
                            <td data-label="<?= e($lang === 'bn' ? 'বই' : 'PDF') ?>"><?= e($lang === 'bn' ? $po['title_bn'] : $po['title_en']) ?></td>
                            <td data-label="TrxID">
                                <strong>৳<?= number_format((float) $po['amount'], 0) ?></strong> · <?= e(strtoupper($po['payment_method'])) ?>
                                <code><?= e($po['transaction_id'] ?: '-') ?></code>
                            </td>
                            <td class="bx-td-actions">
                                <form method="post" action="<?= e(url('/admin/orders')) ?>" style="margin:0;">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="action" value="review_order">
                                    <input type="hidden" name="order_id" value="<?= (int) $po['id'] ?>">
                                    <input type="hidden" name="decision" value="approved">
                                    <input type="hidden" name="_return" value="/admin">
                                    <button type="submit" class="bx-btn bx-btn-primary bx-btn-sm"><?= $lang === 'bn' ? 'অনুমোদন' : 'Approve' ?></button>
                                </form>
                                <form method="post" action="<?= e(url('/admin/orders')) ?>" data-confirm-msg="<?= e($lang === 'bn' ? 'এই অর্ডারটি বাতিল করবেন?' : 'Reject this pending order?') ?>" style="margin:0;">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="action" value="review_order">
                                    <input type="hidden" name="order_id" value="<?= (int) $po['id'] ?>">
                                    <input type="hidden" name="decision" value="rejected">
                                    <input type="hidden" name="_return" value="/admin">
                                    <button type="submit" class="bx-btn bx-btn-outline bx-btn-sm" style="color:var(--rose-text);border-color:var(--rose-text);"><?= $lang === 'bn' ? 'বাতিল' : 'Reject' ?></button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</section>

<!-- Recent Activity Feeds -->
<div class="bx-grid-2">
    <section class="bx-card">
        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:12px;">
            <h2 style="margin:0;font-size:1.05rem;font-family:var(--font-serif);"><?= $lang === 'bn' ? 'সাম্প্রতিক অ্যাডমিন কার্যক্রম' : 'Recent Admin Activity' ?></h2>
            <?php if ($admin['role'] === 'superadmin'): ?>
                <a href="<?= e(url('/admin/admins')) ?>" style="font-size:0.84rem;color:var(--primary);"><?= $lang === 'bn' ? 'সব দেখুন →' : 'Audit Log →' ?></a>
            <?php endif; ?>
        </div>
        <?php if (!$recentAdminLogs): ?>
            <p style="color:var(--text-muted);"><?= $lang === 'bn' ? 'এখনও কোনো অ্যাডমিন অ্যাকশন রেকর্ড হয়নি।' : 'No admin actions logged yet.' ?></p>
        <?php else: ?>
            <div class="bx-table-wrap">
                <table class="bx-table bx-mobile-cards">
                    <thead>
                        <tr>
                            <th><?= $lang === 'bn' ? 'অ্যাডমিন' : 'Admin' ?></th>
                            <th><?= $lang === 'bn' ? 'কাজ' : 'Action' ?></th>
                            <th><?= $lang === 'bn' ? 'সময়' : 'When' ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($recentAdminLogs as $al): ?>
                            <tr>
                                <td class="bx-td-primary"><strong><?= e($al['admin_name']) ?></strong></td>
                                <td data-label="<?= e($lang === 'bn' ? 'কাজ' : 'Action') ?>">
                                    <div>
                                        <span class="bx-badge"><?= e($al['action']) ?></span>
                                        <div style="font-size:0.8rem;color:var(--text-muted);"><?= e((string) ($al['details'] ?? '')) ?></div>
                                    </div>
                                </td>
                                <td data-label="<?= e($lang === 'bn' ? 'সময়' : 'When') ?>" style="font-size:0.8rem;color:var(--text-muted);"><?= e(date('d M H:i', strtotime($al['created_at']))) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </section>

    <section class="bx-card">
        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:12px;">
            <h2 style="margin:0;font-size:1.05rem;font-family:var(--font-serif);"><?= $lang === 'bn' ? 'সাম্প্রতিক অর্ডার' : 'Recent Orders' ?></h2>
            <a href="<?= e(url('/admin/orders')) ?>" style="font-size:0.84rem;color:var(--primary);"><?= $lang === 'bn' ? 'সব অর্ডার →' : 'All Orders →' ?></a>
        </div>
        <?php if (!$recentOrders): ?>
            <p style="color:var(--text-muted);"><?= $lang === 'bn' ? 'কোনো অর্ডার নেই।' : 'No orders yet.' ?></p>
        <?php else: ?>
            <div class="bx-table-wrap">
                <table class="bx-table bx-mobile-cards">
                    <thead>
                        <tr>
                            <th><?= $lang === 'bn' ? 'কোড' : 'Order' ?></th>
                            <th><?= $lang === 'bn' ? 'ব্যবহারকারী' : 'User' ?></th>
                            <th><?= $lang === 'bn' ? 'অবস্থা' : 'Status' ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($recentOrders as $ro): ?>
                            <tr>
                                <td class="bx-td-primary">
                                    <strong>#<?= e($ro['order_code']) ?></strong>
                                    <div style="font-size:0.78rem;color:var(--text-muted);">৳<?= number_format((float) $ro['amount'], 0) ?> • <?= e(strtoupper($ro['payment_method'])) ?></div>
                                </td>
                                <td data-label="<?= e($lang === 'bn' ? 'ব্যবহারকারী' : 'User') ?>"><?= e($ro['user_name']) ?></td>
                                <td data-label="<?= e($lang === 'bn' ? 'অবস্থা' : 'Status') ?>"><span class="bx-status-pill st-<?= e($ro['status']) ?>"><?= e(ucfirst($ro['status'])) ?></span></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </section>
</div>
