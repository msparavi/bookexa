<?php
declare(strict_types=1);

$lang = current_lang();
$rawDays = (int) ($_GET['days'] ?? 30);
$days = in_array($rawDays, [7, 30, 90], true) ? $rawDays : 30;

// 1. Revenue per Day (approved orders)
$revDayRows = db_all(
    "SELECT DATE(created_at) AS d, COALESCE(SUM(amount), 0) AS rev, COUNT(*) AS cnt
     FROM orders
     WHERE status = 'approved' AND created_at >= DATE_SUB(CURDATE(), INTERVAL ? DAY)
     GROUP BY DATE(created_at)",
    [$days - 1]
);
$revDayMap = [];
foreach ($revDayRows as $r) {
    $revDayMap[$r['d']] = (int) round((float) $r['rev']);
}
$revDayLabels = [];
$revDayValues = [];
for ($i = $days - 1; $i >= 0; $i--) {
    $d = date('Y-m-d', strtotime("-{$i} days"));
    $revDayLabels[] = date('d/m', strtotime($d));
    $revDayValues[] = $revDayMap[$d] ?? 0;
}

// 2. Revenue per Month (last 6 months)
$revMonthRows = db_all(
    "SELECT DATE_FORMAT(created_at, '%Y-%m') AS ym, COALESCE(SUM(amount), 0) AS rev
     FROM orders
     WHERE status = 'approved' AND created_at >= DATE_SUB(CURDATE(), INTERVAL 5 MONTH)
     GROUP BY DATE_FORMAT(created_at, '%Y-%m')"
);
$revMonthMap = [];
foreach ($revMonthRows as $r) {
    $revMonthMap[$r['ym']] = (int) round((float) $r['rev']);
}
$revMonthLabels = [];
$revMonthValues = [];
for ($m = 5; $m >= 0; $m--) {
    $ym = date('Y-m', strtotime(date('Y-m-01') . " -{$m} months"));
    $revMonthLabels[] = date('M y', strtotime($ym . '-01'));
    $revMonthValues[] = $revMonthMap[$ym] ?? 0;
}

// 3. New Users per Day
$usrDayRows = db_all(
    "SELECT DATE(created_at) AS d, COUNT(*) AS cnt
     FROM users
     WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL ? DAY)
     GROUP BY DATE(created_at)",
    [$days - 1]
);
$usrDayMap = [];
foreach ($usrDayRows as $r) {
    $usrDayMap[$r['d']] = (int) $r['cnt'];
}
$usrDayLabels = [];
$usrDayValues = [];
for ($i = $days - 1; $i >= 0; $i--) {
    $d = date('Y-m-d', strtotime("-{$i} days"));
    $usrDayLabels[] = date('d/m', strtotime($d));
    $usrDayValues[] = $usrDayMap[$d] ?? 0;
}

// 4. Top-Selling PDFs
$topSelling = db_all(
    "SELECT i.id, i.title_bn, i.title_en, COUNT(o.id) AS sales_count, COALESCE(SUM(o.amount), 0) AS revenue
     FROM items i
     LEFT JOIN orders o ON o.item_id = i.id AND o.status = 'approved'
     GROUP BY i.id
     HAVING sales_count > 0
     ORDER BY sales_count DESC, revenue DESC
     LIMIT 15"
);

// 5. Most-Read PDFs
$mostRead = db_all(
    "SELECT i.id, i.title_bn, i.title_en, COUNT(rl.id) AS total_reads, COUNT(DISTINCT rl.user_id) AS unique_readers
     FROM items i
     LEFT JOIN reading_logs rl ON rl.item_id = i.id
     GROUP BY i.id
     HAVING total_reads > 0
     ORDER BY total_reads DESC
     LIMIT 15"
);

// 6. Coupon Usage Summary
$couponStats = db_all(
    "SELECT c.code, c.discount_type, c.discount_value, c.used_count, c.usage_limit,
            COUNT(o.id) AS order_cnt,
            SUM(CASE WHEN o.status = 'approved' THEN 1 ELSE 0 END) AS approved_cnt,
            COALESCE(SUM(CASE WHEN o.status NOT IN ('rejected','cancelled','expired') THEN o.discount_amount ELSE 0 END), 0) AS total_discount,
            COALESCE(SUM(CASE WHEN o.status = 'approved' THEN o.amount ELSE 0 END), 0) AS total_revenue
     FROM coupons c
     LEFT JOIN orders o ON o.coupon_id = c.id
     GROUP BY c.id
     ORDER BY order_cnt DESC, c.id DESC"
);

// 7. Revenue by Payment Method
$methodStats = db_all(
    "SELECT payment_method,
            COUNT(*) AS total_orders,
            SUM(CASE WHEN status = 'approved' THEN 1 ELSE 0 END) AS approved_cnt,
            COALESCE(SUM(CASE WHEN status = 'approved' THEN amount ELSE 0 END), 0) AS revenue
     FROM orders
     GROUP BY payment_method
     ORDER BY revenue DESC, total_orders DESC"
);
$totalMethodRevenue = array_sum(array_map(fn($r) => (float) $r['revenue'], $methodStats));

// 8. Refunds, Rejections, Cancellations & Expiries
$rejectedSummary = db_all(
    "SELECT status, COUNT(*) AS cnt, COALESCE(SUM(amount), 0) AS total_amount
     FROM orders
     WHERE status IN ('rejected', 'cancelled', 'expired')
     GROUP BY status"
);
$rejectedSummaryMap = ['rejected' => ['cnt' => 0, 'amt' => 0.0], 'cancelled' => ['cnt' => 0, 'amt' => 0.0], 'expired' => ['cnt' => 0, 'amt' => 0.0]];
foreach ($rejectedSummary as $rs) {
    $rejectedSummaryMap[$rs['status']] = ['cnt' => (int) $rs['cnt'], 'amt' => (float) $rs['total_amount']];
}
$recentLostOrders = db_all(
    "SELECT o.order_code, o.status, o.amount, o.payment_method, o.admin_note, o.created_at, i.title_en
     FROM orders o
     JOIN items i ON i.id = o.item_id
     WHERE o.status IN ('rejected', 'cancelled', 'expired')
     ORDER BY o.id DESC
     LIMIT 12"
);

// 9. Reading Engagement (Avg Pages Read & Drop-off Page per Book)
$engagementStats = db_all(
    "SELECT i.id, i.title_bn, i.title_en, i.total_pages,
            COALESCE(i.read_count, 0) AS read_count,
            ROUND(COALESCE(AVG(rp.last_page), 0), 1) AS avg_pages_read,
            ROUND(COALESCE(AVG(CASE WHEN rp.is_finished = 0 THEN rp.last_page ELSE NULL END), 0), 1) AS dropoff_page,
            COUNT(rp.id) AS tracked_readers
     FROM items i
     LEFT JOIN reading_progress rp ON rp.item_id = i.id
     GROUP BY i.id
     HAVING tracked_readers > 0 OR read_count > 0
     ORDER BY tracked_readers DESC, read_count DESC
     LIMIT 15"
);

// 10. Sample-to-Purchase Conversion per Book
$sampleConvStats = db_all(
    "SELECT i.id, i.title_bn, i.title_en,
            COUNT(DISTINCT rl.user_id) AS sample_readers,
            COUNT(DISTINCT CASE WHEN o.status = 'approved' THEN rl.user_id ELSE NULL END) AS converted_buyers
     FROM reading_logs rl
     JOIN items i ON i.id = rl.item_id
     LEFT JOIN orders o ON o.user_id = rl.user_id AND o.item_id = rl.item_id AND o.status = 'approved'
     WHERE rl.is_sample = 1 AND i.is_free = 0 AND i.price > 0
     GROUP BY i.id
     ORDER BY sample_readers DESC
     LIMIT 15"
);

// 11. Revenue by Folder
$folderRevStats = db_all(
    "SELECT COALESCE(f.id, 0) AS fid, COALESCE(f.name_en, 'Unassigned') AS name_en, COALESCE(f.name_bn, 'অনির্ধারিত') AS name_bn,
            COUNT(o.id) AS approved_orders,
            COALESCE(SUM(o.amount), 0) AS revenue
     FROM orders o
     JOIN items i ON i.id = o.item_id
     LEFT JOIN folders f ON f.id = i.folder_id
     WHERE o.status = 'approved'
     GROUP BY f.id
     ORDER BY revenue DESC
     LIMIT 12"
);
$folderChartLabels = array_map(static fn(array $r): string => mb_substr((string) ($lang === 'bn' ? $r['name_bn'] : $r['name_en']), 0, 14), $folderRevStats);
$folderChartValues = array_map(static fn(array $r): int => (int) round((float) $r['revenue']), $folderRevStats);

// 12. Top Searches & Zero-Result Searches
$topSearches  = db_all('SELECT query_text, hits, last_searched_at FROM search_queries ORDER BY hits DESC, last_searched_at DESC LIMIT 15');
$zeroSearches = db_all('SELECT query_text, hits, last_searched_at FROM search_misses ORDER BY hits DESC, last_searched_at DESC LIMIT 15');
?>

<section class="bx-card" style="margin-bottom:20px;display:flex;justify-content:space-between;align-items:center;gap:12px;flex-wrap:wrap;">
    <div>
        <h2 style="margin:0;font-size:1.12rem;font-family:var(--font-serif);"><?= $lang === 'bn' ? 'রিপোর্ট ও বিশ্লেষণ' : 'Analytics & Financial Reports' ?></h2>
        <small style="color:var(--text-muted);"><?= $lang === 'bn' ? 'দৈনিক/মাসিক আয়, সর্বাধিক বিক্রিত ও পঠিত PDF, নতুন ব্যবহারকারী ও কুপন রিপোর্ট।' : 'Daily & monthly revenue, top-selling & most-read PDFs, signups, and coupon attribution.' ?></small>
    </div>
    <div style="display:flex;gap:8px;flex-wrap:wrap;">
        <?php foreach ([7, 30, 90] as $dOpt): ?>
            <a href="<?= e(url('/admin/reports?days=' . $dOpt)) ?>" class="bx-btn <?= $days === $dOpt ? 'bx-btn-primary' : 'bx-btn-outline' ?> bx-btn-sm">
                <?= $dOpt ?> <?= $lang === 'bn' ? 'দিন' : 'Days' ?>
            </a>
        <?php endforeach; ?>
    </div>
</section>

<!-- Vanilla JS SVG Charts -->
<div class="bx-grid-2" style="margin-bottom:20px;">
    <section class="bx-card">
        <div style="display:flex;justify-content:space-between;align-items:center;gap:8px;flex-wrap:wrap;margin-bottom:10px;">
            <div>
                <h3 style="margin:0;font-size:1rem;font-family:var(--font-serif);"><?= $lang === 'bn' ? 'দৈনিক আয় (৳)' : 'Revenue per Day (৳)' ?></h3>
                <small style="color:var(--text-muted);">৳<?= number_format(array_sum($revDayValues), 0) ?> (<?= $days ?>d)</small>
            </div>
            <a href="<?= e(url('/admin/reports?export=revenue_daily&days=' . $days)) ?>" class="bx-btn bx-btn-outline bx-btn-sm">CSV</a>
        </div>
        <div class="bx-chart-wrap">
            <svg class="bx-chart-svg" data-bar-chart data-labels='<?= e(json_encode($revDayLabels)) ?>' data-values='<?= e(json_encode($revDayValues)) ?>'></svg>
            <div class="bx-chart-empty" style="display:none;"><?= $lang === 'bn' ? 'এই সময়কালে কোনো অনুমোদিত আয় নেই।' : 'No approved revenue in this period.' ?></div>
        </div>
    </section>

    <section class="bx-card">
        <div style="display:flex;justify-content:space-between;align-items:center;gap:8px;flex-wrap:wrap;margin-bottom:10px;">
            <div>
                <h3 style="margin:0;font-size:1rem;font-family:var(--font-serif);"><?= $lang === 'bn' ? 'মাসিক আয় (৳)' : 'Revenue per Month (৳)' ?></h3>
                <small style="color:var(--text-muted);">৳<?= number_format(array_sum($revMonthValues), 0) ?> (6m)</small>
            </div>
            <a href="<?= e(url('/admin/reports?export=revenue_monthly&days=' . $days)) ?>" class="bx-btn bx-btn-outline bx-btn-sm">CSV</a>
        </div>
        <div class="bx-chart-wrap">
            <svg class="bx-chart-svg" data-bar-chart data-labels='<?= e(json_encode($revMonthLabels)) ?>' data-values='<?= e(json_encode($revMonthValues)) ?>'></svg>
            <div class="bx-chart-empty" style="display:none;"><?= $lang === 'bn' ? 'মাসিক আয়ের কোনো তথ্য নেই।' : 'No monthly revenue data yet.' ?></div>
        </div>
    </section>
</div>

<section class="bx-card" style="margin-bottom:20px;">
    <div style="display:flex;justify-content:space-between;align-items:center;gap:8px;flex-wrap:wrap;margin-bottom:10px;">
        <div>
            <h3 style="margin:0;font-size:1rem;font-family:var(--font-serif);"><?= $lang === 'bn' ? 'দৈনিক নতুন ব্যবহারকারী' : 'New Users per Day' ?></h3>
            <small style="color:var(--text-muted);"><?= array_sum($usrDayValues) ?> <?= $lang === 'bn' ? 'জন নতুন ব্যবহারকারী' : 'registrations' ?> (<?= $days ?>d)</small>
        </div>
        <a href="<?= e(url('/admin/reports?export=new_users&days=' . $days)) ?>" class="bx-btn bx-btn-outline bx-btn-sm">CSV</a>
    </div>
    <div class="bx-chart-wrap">
        <svg class="bx-chart-svg" data-bar-chart data-labels='<?= e(json_encode($usrDayLabels)) ?>' data-values='<?= e(json_encode($usrDayValues)) ?>'></svg>
        <div class="bx-chart-empty" style="display:none;"><?= $lang === 'bn' ? 'এই সময়কালে নতুন কোনো নিবন্ধন হয়নি।' : 'No new user signups in this period.' ?></div>
    </div>
</section>

<div class="bx-grid-2" style="margin-bottom:20px;">
    <!-- Top Selling PDFs -->
    <section class="bx-card">
        <div style="display:flex;justify-content:space-between;align-items:center;gap:8px;flex-wrap:wrap;margin-bottom:12px;">
            <h3 style="margin:0;font-size:1rem;font-family:var(--font-serif);"><?= $lang === 'bn' ? 'সর্বাধিক বিক্রিত PDF' : 'Top-Selling PDFs' ?></h3>
            <a href="<?= e(url('/admin/reports?export=top_selling')) ?>" class="bx-btn bx-btn-outline bx-btn-sm">CSV</a>
        </div>
        <div class="bx-table-wrap">
            <table class="bx-table bx-mobile-cards">
                <thead><tr><th><?= $lang === 'bn' ? 'বই' : 'Book' ?></th><th><?= $lang === 'bn' ? 'বিক্রি' : 'Sales' ?></th><th><?= $lang === 'bn' ? 'আয়' : 'Revenue' ?></th></tr></thead>
                <tbody>
                    <?php if (!$topSelling): ?>
                        <tr class="bx-empty-row"><td colspan="3" style="color:var(--text-muted);"><?= $lang === 'bn' ? 'এখনও বিক্রি হয়নি।' : 'No approved sales yet.' ?></td></tr>
                    <?php else: foreach ($topSelling as $ts): ?>
                        <tr>
                            <td class="bx-td-primary"><strong><?= e($lang === 'bn' ? $ts['title_bn'] : $ts['title_en']) ?></strong></td>
                            <td data-label="<?= e($lang === 'bn' ? 'বিক্রি' : 'Sales') ?>"><?= (int) $ts['sales_count'] ?></td>
                            <td data-label="<?= e($lang === 'bn' ? 'আয়' : 'Revenue') ?>"><strong>৳<?= number_format((float) $ts['revenue'], 0) ?></strong></td>
                        </tr>
                    <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>
    </section>

    <!-- Most Read PDFs -->
    <section class="bx-card">
        <div style="display:flex;justify-content:space-between;align-items:center;gap:8px;flex-wrap:wrap;margin-bottom:12px;">
            <h3 style="margin:0;font-size:1rem;font-family:var(--font-serif);"><?= $lang === 'bn' ? 'সর্বাধিক পঠিত PDF' : 'Most-Read PDFs' ?></h3>
            <a href="<?= e(url('/admin/reports?export=most_read')) ?>" class="bx-btn bx-btn-outline bx-btn-sm">CSV</a>
        </div>
        <div class="bx-table-wrap">
            <table class="bx-table bx-mobile-cards">
                <thead><tr><th><?= $lang === 'bn' ? 'বই' : 'Book' ?></th><th><?= $lang === 'bn' ? 'মোট পঠন' : 'Events' ?></th><th><?= $lang === 'bn' ? 'পাঠক' : 'Readers' ?></th></tr></thead>
                <tbody>
                    <?php if (!$mostRead): ?>
                        <tr class="bx-empty-row"><td colspan="3" style="color:var(--text-muted);"><?= $lang === 'bn' ? 'এখনও কোনো পাঠ লগ নেই।' : 'No reading activity yet.' ?></td></tr>
                    <?php else: foreach ($mostRead as $mr): ?>
                        <tr>
                            <td class="bx-td-primary"><strong><?= e($lang === 'bn' ? $mr['title_bn'] : $mr['title_en']) ?></strong></td>
                            <td data-label="<?= e($lang === 'bn' ? 'মোট পঠন' : 'Events') ?>"><?= (int) $mr['total_reads'] ?></td>
                            <td data-label="<?= e($lang === 'bn' ? 'পাঠক' : 'Readers') ?>"><?= (int) $mr['unique_readers'] ?></td>
                        </tr>
                    <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>
    </section>
</div>

<!-- Revenue by Payment Method & Refunds/Rejections -->
<div class="bx-grid-2" style="margin-bottom:20px;">
    <section class="bx-card">
        <div style="display:flex;justify-content:space-between;align-items:center;gap:8px;flex-wrap:wrap;margin-bottom:12px;">
            <h3 style="margin:0;font-size:1rem;font-family:var(--font-serif);"><?= $lang === 'bn' ? 'পেমেন্ট মাধ্যম অনুযায়ী আয়' : 'Revenue by Payment Method' ?></h3>
            <a href="<?= e(url('/admin/reports?export=payment_methods')) ?>" class="bx-btn bx-btn-outline bx-btn-sm">CSV</a>
        </div>
        <div class="bx-table-wrap">
            <table class="bx-table bx-mobile-cards">
                <thead>
                    <tr>
                        <th><?= $lang === 'bn' ? 'মাধ্যম' : 'Method' ?></th>
                        <th><?= $lang === 'bn' ? 'অর্ডার / অনুমোদিত' : 'Orders / Approved' ?></th>
                        <th><?= $lang === 'bn' ? 'আয় ও অংশ' : 'Revenue & Share' ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!$methodStats): ?>
                        <tr class="bx-empty-row"><td colspan="3" style="color:var(--text-muted);"><?= $lang === 'bn' ? 'কোনো অর্ডার নেই।' : 'No payment method data yet.' ?></td></tr>
                    <?php else: foreach ($methodStats as $ms): ?>
                        <?php $rev = (float) $ms['revenue']; $share = $totalMethodRevenue > 0 ? round(($rev / $totalMethodRevenue) * 100, 1) : 0; ?>
                        <tr>
                            <td class="bx-td-primary"><span class="bx-badge"><?= e(strtoupper((string) $ms['payment_method'])) ?></span></td>
                            <td data-label="Orders"><?= (int) $ms['total_orders'] ?> (<?= (int) $ms['approved_cnt'] ?> approved)</td>
                            <td data-label="Revenue"><strong>৳<?= number_format($rev, 0) ?></strong> <small style="color:var(--text-muted);">(<?= $share ?>%)</small></td>
                        </tr>
                    <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>
    </section>

    <section class="bx-card">
        <div style="display:flex;justify-content:space-between;align-items:center;gap:8px;flex-wrap:wrap;margin-bottom:12px;">
            <h3 style="margin:0;font-size:1rem;font-family:var(--font-serif);"><?= $lang === 'bn' ? 'বাতিল, রিজেক্টেড ও মেয়াদোত্তীর্ণ' : 'Rejections, Cancellations & Expiries' ?></h3>
            <a href="<?= e(url('/admin/reports?export=rejections_refunds')) ?>" class="bx-btn bx-btn-outline bx-btn-sm">CSV</a>
        </div>
        <div style="display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:8px;margin-bottom:12px;">
            <div style="padding:10px;border-radius:10px;background:var(--bg-alt);border:1px solid var(--border);">
                <small style="color:var(--text-muted);display:block;">Rejected</small>
                <strong><?= $rejectedSummaryMap['rejected']['cnt'] ?></strong>
                <div style="font-size:0.78rem;color:var(--text-muted);">৳<?= number_format($rejectedSummaryMap['rejected']['amt'], 0) ?></div>
            </div>
            <div style="padding:10px;border-radius:10px;background:var(--bg-alt);border:1px solid var(--border);">
                <small style="color:var(--text-muted);display:block;">Cancelled</small>
                <strong><?= $rejectedSummaryMap['cancelled']['cnt'] ?></strong>
                <div style="font-size:0.78rem;color:var(--text-muted);">৳<?= number_format($rejectedSummaryMap['cancelled']['amt'], 0) ?></div>
            </div>
            <div style="padding:10px;border-radius:10px;background:var(--bg-alt);border:1px solid var(--border);">
                <small style="color:var(--text-muted);display:block;">Expired</small>
                <strong><?= $rejectedSummaryMap['expired']['cnt'] ?></strong>
                <div style="font-size:0.78rem;color:var(--text-muted);">৳<?= number_format($rejectedSummaryMap['expired']['amt'], 0) ?></div>
            </div>
        </div>
        <div class="bx-table-wrap">
            <table class="bx-table bx-mobile-cards">
                <thead><tr><th>Order</th><th>Status</th><th>Reason / Amount</th></tr></thead>
                <tbody>
                    <?php if (!$recentLostOrders): ?>
                        <tr class="bx-empty-row"><td colspan="3" style="color:var(--text-muted);"><?= $lang === 'bn' ? 'কোনো রিজেক্ট বা বাতিল অর্ডার নেই।' : 'No rejections, cancellations, or expiries.' ?></td></tr>
                    <?php else: foreach ($recentLostOrders as $rlo): ?>
                        <tr>
                            <td class="bx-td-primary"><strong>#<?= e($rlo['order_code']) ?></strong><div style="font-size:0.78rem;color:var(--text-muted);"><?= e($rlo['title_en']) ?></div></td>
                            <td data-label="Status"><span class="bx-status-pill st-rejected"><?= e(ucfirst($rlo['status'])) ?></span></td>
                            <td data-label="Reason">৳<?= number_format((float) $rlo['amount'], 0) ?><?= !empty($rlo['admin_note']) ? ' · ' . e((string) $rlo['admin_note']) : '' ?></td>
                        </tr>
                    <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>
    </section>
</div>

<!-- Coupon Usage Table -->
<section class="bx-card">
    <div style="display:flex;justify-content:space-between;align-items:center;gap:8px;flex-wrap:wrap;margin-bottom:12px;">
        <h3 style="margin:0;font-size:1rem;font-family:var(--font-serif);"><?= $lang === 'bn' ? 'কুপন পারফরম্যান্স রিপোর্ট' : 'Coupon Performance Report' ?></h3>
        <a href="<?= e(url('/admin/reports?export=coupons')) ?>" class="bx-btn bx-btn-outline bx-btn-sm">CSV</a>
    </div>
    <div class="bx-table-wrap">
        <table class="bx-table bx-mobile-cards">
            <thead>
                <tr>
                    <th><?= $lang === 'bn' ? 'কোড' : 'Code' ?></th>
                    <th><?= $lang === 'bn' ? 'ছাড়' : 'Rule' ?></th>
                    <th><?= $lang === 'bn' ? 'অর্ডার / রূপান্তর' : 'Orders / Conv %' ?></th>
                    <th><?= $lang === 'bn' ? 'মোট ছাড়' : 'Total Discount' ?></th>
                    <th><?= $lang === 'bn' ? 'নেট আয়' : 'Revenue' ?></th>
                </tr>
            </thead>
            <tbody>
                <?php if (!$couponStats): ?>
                    <tr class="bx-empty-row"><td colspan="5" style="color:var(--text-muted);"><?= $lang === 'bn' ? 'কোনো কুপন নেই।' : 'No coupons found.' ?></td></tr>
                <?php else: foreach ($couponStats as $cs): ?>
                    <?php $oc = (int) $cs['order_cnt']; $ac = (int) $cs['approved_cnt']; $conv = $oc > 0 ? round(($ac / $oc) * 100) : 0; ?>
                    <tr>
                        <td class="bx-td-primary"><strong><?= e($cs['code']) ?></strong><div style="font-size:0.78rem;color:var(--text-muted);"><?= (int) $cs['used_count'] ?><?= $cs['usage_limit'] ? (' / ' . (int) $cs['usage_limit'] . ' limit') : ' used' ?></div></td>
                        <td data-label="<?= e($lang === 'bn' ? 'ছাড়' : 'Rule') ?>"><?= $cs['discount_type'] === 'percent' ? ((float) $cs['discount_value'] . '%') : ('৳' . number_format((float) $cs['discount_value'], 0)) ?></td>
                        <td data-label="<?= e($lang === 'bn' ? 'ব্যবহার' : 'Uses') ?>"><?= $oc ?> (<?= $ac ?> ok · <?= $conv ?>%)</td>
                        <td data-label="<?= e($lang === 'bn' ? 'মোট ছাড়' : 'Discount') ?>">৳<?= number_format((float) $cs['total_discount'], 0) ?></td>
                        <td data-label="<?= e($lang === 'bn' ? 'আয়' : 'Revenue') ?>"><strong>৳<?= number_format((float) $cs['total_revenue'], 0) ?></strong></td>
                    </tr>
                <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
</section>

<!-- Revenue by Folder (SVG Chart) + Sample Conversion -->
<div class="bx-grid-2" style="margin-top:20px;margin-bottom:20px;">
    <section class="bx-card">
        <div style="display:flex;justify-content:space-between;align-items:center;gap:8px;flex-wrap:wrap;margin-bottom:10px;">
            <h3 style="margin:0;font-size:1rem;font-family:var(--font-serif);"><?= $lang === 'bn' ? 'ফোল্ডার অনুযায়ী আয় (৳)' : 'Revenue by Folder (৳)' ?></h3>
            <a href="<?= e(url('/admin/reports?export=revenue_by_folder')) ?>" class="bx-btn bx-btn-outline bx-btn-sm">CSV</a>
        </div>
        <div class="bx-chart-wrap">
            <svg class="bx-chart-svg" data-bar-chart data-labels='<?= e(json_encode($folderChartLabels, JSON_UNESCAPED_UNICODE)) ?>' data-values='<?= e(json_encode($folderChartValues)) ?>'></svg>
            <div class="bx-chart-empty" style="display:none;"><?= $lang === 'bn' ? 'ফোল্ডারভিত্তিক আয় এখনো নেই।' : 'No folder revenue recorded yet.' ?></div>
        </div>
    </section>

    <section class="bx-card">
        <div style="display:flex;justify-content:space-between;align-items:center;gap:8px;flex-wrap:wrap;margin-bottom:12px;">
            <h3 style="margin:0;font-size:1rem;font-family:var(--font-serif);"><?= $lang === 'bn' ? 'স্যাম্পল থেকে ক্রয় রূপান্তর' : 'Sample-to-Purchase Conversion' ?></h3>
            <a href="<?= e(url('/admin/reports?export=sample_conversion')) ?>" class="bx-btn bx-btn-outline bx-btn-sm">CSV</a>
        </div>
        <div class="bx-table-wrap">
            <table class="bx-table bx-mobile-cards">
                <thead><tr><th><?= $lang === 'bn' ? 'বই' : 'Book' ?></th><th>Sample Readers</th><th>Converted</th><th>Rate</th></tr></thead>
                <tbody>
                    <?php if (!$sampleConvStats): ?>
                        <tr class="bx-empty-row"><td colspan="4" style="color:var(--text-muted);"><?= $lang === 'bn' ? 'এখনো স্যাম্পল ডেটা নেই।' : 'No sample reader data yet.' ?></td></tr>
                    <?php else: foreach ($sampleConvStats as $sc): ?>
                        <?php $sr = (int) $sc['sample_readers']; $cb = (int) $sc['converted_buyers']; $pct = $sr > 0 ? round(($cb / $sr) * 100, 1) : 0; ?>
                        <tr>
                            <td class="bx-td-primary"><strong><?= e($lang === 'bn' ? $sc['title_bn'] : $sc['title_en']) ?></strong></td>
                            <td data-label="Samples"><?= $sr ?></td>
                            <td data-label="Buyers"><?= $cb ?></td>
                            <td data-label="Rate"><span class="bx-badge bx-badge-teal"><?= $pct ?>%</span></td>
                        </tr>
                    <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>
    </section>
</div>

<!-- Reading Engagement & Drop-Off Page -->
<section class="bx-card" style="margin-bottom:20px;">
    <div style="display:flex;justify-content:space-between;align-items:center;gap:8px;flex-wrap:wrap;margin-bottom:12px;">
        <h3 style="margin:0;font-size:1rem;font-family:var(--font-serif);"><?= $lang === 'bn' ? 'গড় পঠিত পৃষ্ঠা ও ড্রপ-অফ পৃষ্ঠা' : 'Reading Engagement & Drop-Off Page per Book' ?></h3>
        <a href="<?= e(url('/admin/reports?export=reading_engagement')) ?>" class="bx-btn bx-btn-outline bx-btn-sm">CSV</a>
    </div>
    <div class="bx-table-wrap">
        <table class="bx-table bx-mobile-cards">
            <thead>
                <tr>
                    <th><?= $lang === 'bn' ? 'বই' : 'Book' ?></th>
                    <th><?= $lang === 'bn' ? 'পঠন সংখ্যা' : 'Reads' ?></th>
                    <th><?= $lang === 'bn' ? 'গড় পঠিত পৃষ্ঠা' : 'Avg Pages Read' ?></th>
                    <th><?= $lang === 'bn' ? 'গড় ড্রপ-অফ পৃষ্ঠা' : 'Typical Drop-off Page' ?></th>
                </tr>
            </thead>
            <tbody>
                <?php if (!$engagementStats): ?>
                    <tr class="bx-empty-row"><td colspan="4" style="color:var(--text-muted);"><?= $lang === 'bn' ? 'এখনো রিডিং প্রগ্রেস ডেটা নেই।' : 'No reading engagement data yet.' ?></td></tr>
                <?php else: foreach ($engagementStats as $es): ?>
                    <tr>
                        <td class="bx-td-primary"><strong><?= e($lang === 'bn' ? $es['title_bn'] : $es['title_en']) ?></strong></td>
                        <td data-label="Reads"><?= (int) $es['read_count'] ?> (<?= (int) $es['tracked_readers'] ?> tracked)</td>
                        <td data-label="Avg Read"><?= e((string) $es['avg_pages_read']) ?><?= !empty($es['total_pages']) ? (' / ' . (int) $es['total_pages']) : '' ?></td>
                        <td data-label="Drop-off"><?= (float) $es['dropoff_page'] > 0 ? ('Page ' . e((string) $es['dropoff_page'])) : '—' ?></td>
                    </tr>
                <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
</section>

<!-- Top Searches & Zero-Result Searches -->
<div class="bx-grid-2">
    <section class="bx-card">
        <div style="display:flex;justify-content:space-between;align-items:center;gap:8px;flex-wrap:wrap;margin-bottom:12px;">
            <h3 style="margin:0;font-size:1rem;font-family:var(--font-serif);"><?= $lang === 'bn' ? 'শীর্ষ অনুসন্ধান' : 'Top Searches' ?></h3>
            <a href="<?= e(url('/admin/reports?export=top_searches')) ?>" class="bx-btn bx-btn-outline bx-btn-sm">CSV</a>
        </div>
        <div class="bx-table-wrap">
            <table class="bx-table bx-mobile-cards">
                <thead><tr><th>Query</th><th>Hits</th></tr></thead>
                <tbody>
                    <?php if (!$topSearches): ?>
                        <tr class="bx-empty-row"><td colspan="2" style="color:var(--text-muted);">No search queries recorded yet.</td></tr>
                    <?php else: foreach ($topSearches as $tq): ?>
                        <tr>
                            <td class="bx-td-primary"><strong><?= e((string) $tq['query_text']) ?></strong></td>
                            <td data-label="Hits"><span class="bx-badge"><?= (int) $tq['hits'] ?></span></td>
                        </tr>
                    <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>
    </section>

    <section class="bx-card">
        <div style="display:flex;justify-content:space-between;align-items:center;gap:8px;flex-wrap:wrap;margin-bottom:12px;">
            <h3 style="margin:0;font-size:1rem;font-family:var(--font-serif);"><?= $lang === 'bn' ? 'ফলাফলবিহীন অনুসন্ধান' : 'Searches With No Results' ?></h3>
            <a href="<?= e(url('/admin/reports?export=zero_searches')) ?>" class="bx-btn bx-btn-outline bx-btn-sm">CSV</a>
        </div>
        <div class="bx-table-wrap">
            <table class="bx-table bx-mobile-cards">
                <thead><tr><th>Query</th><th>Hits</th></tr></thead>
                <tbody>
                    <?php if (!$zeroSearches): ?>
                        <tr class="bx-empty-row"><td colspan="2" style="color:var(--text-muted);">No empty-result searches recorded yet.</td></tr>
                    <?php else: foreach ($zeroSearches as $zq): ?>
                        <tr>
                            <td class="bx-td-primary"><strong><?= e((string) $zq['query_text']) ?></strong></td>
                            <td data-label="Hits"><span class="bx-badge bx-badge-teal"><?= (int) $zq['hits'] ?></span></td>
                        </tr>
                    <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>
    </section>
</div>
