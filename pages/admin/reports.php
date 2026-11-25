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
            COALESCE(SUM(CASE WHEN o.status != 'rejected' THEN o.discount_amount ELSE 0 END), 0) AS total_discount,
            COALESCE(SUM(CASE WHEN o.status = 'approved' THEN o.amount ELSE 0 END), 0) AS total_revenue
     FROM coupons c
     LEFT JOIN orders o ON o.coupon_id = c.id
     GROUP BY c.id
     ORDER BY order_cnt DESC, c.id DESC"
);
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

<!-- Coupon Usage Table -->
<section class="bx-card">
    <div style="display:flex;justify-content:space-between;align-items:center;gap:8px;flex-wrap:wrap;margin-bottom:12px;">
        <h3 style="margin:0;font-size:1rem;font-family:var(--font-serif);"><?= $lang === 'bn' ? 'কুপন ব্যবহারের রিপোর্ট' : 'Coupon Usage Report' ?></h3>
        <a href="<?= e(url('/admin/reports?export=coupons')) ?>" class="bx-btn bx-btn-outline bx-btn-sm">CSV</a>
    </div>
    <div class="bx-table-wrap">
        <table class="bx-table bx-mobile-cards">
            <thead>
                <tr>
                    <th><?= $lang === 'bn' ? 'কোড' : 'Code' ?></th>
                    <th><?= $lang === 'bn' ? 'ছাড়' : 'Rule' ?></th>
                    <th><?= $lang === 'bn' ? 'অর্ডার / অনুমোদিত' : 'Orders / Approved' ?></th>
                    <th><?= $lang === 'bn' ? 'মোট ছাড়' : 'Total Discount' ?></th>
                    <th><?= $lang === 'bn' ? 'নেট আয়' : 'Revenue' ?></th>
                </tr>
            </thead>
            <tbody>
                <?php if (!$couponStats): ?>
                    <tr class="bx-empty-row"><td colspan="5" style="color:var(--text-muted);"><?= $lang === 'bn' ? 'কোনো কুপন নেই।' : 'No coupons found.' ?></td></tr>
                <?php else: foreach ($couponStats as $cs): ?>
                    <tr>
                        <td class="bx-td-primary"><strong><?= e($cs['code']) ?></strong></td>
                        <td data-label="<?= e($lang === 'bn' ? 'ছাড়' : 'Rule') ?>"><?= $cs['discount_type'] === 'percent' ? ((float) $cs['discount_value'] . '%') : ('৳' . number_format((float) $cs['discount_value'], 0)) ?></td>
                        <td data-label="<?= e($lang === 'bn' ? 'ব্যবহার' : 'Uses') ?>"><?= (int) $cs['order_cnt'] ?> (<?= (int) $cs['approved_cnt'] ?> ok)</td>
                        <td data-label="<?= e($lang === 'bn' ? 'মোট ছাড়' : 'Discount') ?>">৳<?= number_format((float) $cs['total_discount'], 0) ?></td>
                        <td data-label="<?= e($lang === 'bn' ? 'আয়' : 'Revenue') ?>"><strong>৳<?= number_format((float) $cs['total_revenue'], 0) ?></strong></td>
                    </tr>
                <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
</section>
