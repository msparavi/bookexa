<?php
declare(strict_types=1);

$lang   = current_lang();
$filter = (string) ($_GET['filter'] ?? 'all');
$q      = trim((string) ($_GET['q'] ?? ''));
$spage  = max(1, (int) ($_GET['spage'] ?? 1));
$rpage  = max(1, (int) ($_GET['rpage'] ?? 1));
$cpage  = max(1, (int) ($_GET['cpage'] ?? 1));
$perPage = 25;

if (!in_array($filter, ['all', 'suspicious', 'failed', 'source_errors', 'anomalies', 'contact'], true)) {
    $filter = 'all';
}

// 1. Security Logs Query (25/page)
$secWhere  = [];
$secParams = [];
if ($filter === 'suspicious') {
    $secWhere[] = "(event_type LIKE '%anomaly%' OR event_type LIKE '%susp%' OR event_type LIKE '%kicked%' OR event_type LIKE '%mismatch%' OR event_type IN ('page_out_of_range','hotlink_attempt','unauthorized_stream','token_reused','token_expired','fast_sequential_burst','many_sessions_anomaly'))";
} elseif ($filter === 'failed') {
    $secWhere[] = "(event_type LIKE '%failed%' OR event_type LIKE '%rate_limit%' OR event_type LIKE '%locked%')";
} elseif ($filter === 'source_errors') {
    $secWhere[] = "(event_type LIKE '%source%' OR event_type LIKE '%drive%' OR event_type LIKE '%cdn%')";
} elseif ($filter === 'anomalies') {
    $secWhere[] = "(event_type LIKE '%anomaly%' OR event_type LIKE '%suspend%' OR event_type LIKE '%quota%')";
}
if ($q !== '') {
    $like = '%' . escape_like($q) . '%';
    $secWhere[] = "(event_type LIKE ? ESCAPE '\\\\' OR COALESCE(identifier, \"\") LIKE ? ESCAPE '\\\\' OR ip_address LIKE ? ESCAPE '\\\\' OR COALESCE(details, \"\") LIKE ? ESCAPE '\\\\')";
    array_push($secParams, $like, $like, $like, $like);
}
$secWhereSql = $secWhere ? (' WHERE ' . implode(' AND ', $secWhere)) : '';
$secTotal = (int) (db_one("SELECT COUNT(*) AS c FROM security_logs{$secWhereSql}", $secParams)['c'] ?? 0);
$secPages = max(1, (int) ceil($secTotal / $perPage));
if ($spage > $secPages) {
    $spage = $secPages;
}
$secOffset = ($spage - 1) * $perPage;
$secLogs = $filter === 'contact' ? [] : db_all(
    "SELECT * FROM security_logs{$secWhereSql} ORDER BY id DESC LIMIT {$perPage} OFFSET {$secOffset}",
    $secParams
);

// 2. Reading Logs Query (25/page)
$readWhere  = [];
$readParams = [];
if ($filter === 'suspicious') {
    $readWhere[] = "(rl.action LIKE '%anomaly%' OR rl.action LIKE '%suspend%' OR rl.action LIKE '%quota%' OR rl.action LIKE '%kicked%' OR rl.action IN ('page_out_of_range','hotlink_attempt','unauthorized_stream','token_reused','token_expired','fast_sequential_burst','many_sessions_anomaly'))";
} elseif ($filter === 'anomalies') {
    $readWhere[] = "(rl.action LIKE '%anomaly%' OR rl.action LIKE '%suspend%' OR rl.action LIKE '%quota%' OR rl.action LIKE '%kicked%')";
}
if ($q !== '') {
    $like = '%' . escape_like($q) . '%';
    $readWhere[] = "(rl.action LIKE ? ESCAPE '\\\\' OR rl.ip_address LIKE ? ESCAPE '\\\\' OR COALESCE(rl.details, \"\") LIKE ? ESCAPE '\\\\' OR COALESCE(u.name, \"\") LIKE ? ESCAPE '\\\\' OR COALESCE(u.email, \"\") LIKE ? ESCAPE '\\\\' OR COALESCE(i.title_en, \"\") LIKE ? ESCAPE '\\\\')";
    array_push($readParams, $like, $like, $like, $like, $like, $like);
}
$readWhereSql = $readWhere ? (' WHERE ' . implode(' AND ', $readWhere)) : '';
$readFrom = 'FROM reading_logs rl LEFT JOIN users u ON u.id = rl.user_id LEFT JOIN items i ON i.id = rl.item_id';
$readTotal = (int) (db_one("SELECT COUNT(*) AS c {$readFrom}{$readWhereSql}", $readParams)['c'] ?? 0);
$readPages = max(1, (int) ceil($readTotal / $perPage));
if ($rpage > $readPages) {
    $rpage = $readPages;
}
$readOffset = ($rpage - 1) * $perPage;
$readLogs = $filter === 'contact' ? [] : db_all(
    "SELECT rl.*, u.name AS user_name, u.email AS user_email, u.is_active AS user_active, i.title_en
     {$readFrom}{$readWhereSql}
     ORDER BY rl.id DESC LIMIT {$perPage} OFFSET {$readOffset}",
    $readParams
);

// 3. Contact Messages Query (25/page)
$conWhere  = [];
$conParams = [];
if ($q !== '') {
    $like = '%' . escape_like($q) . '%';
    $conWhere[] = "(name LIKE ? ESCAPE '\\\\' OR email LIKE ? ESCAPE '\\\\' OR COALESCE(phone, \"\") LIKE ? ESCAPE '\\\\' OR subject LIKE ? ESCAPE '\\\\' OR message LIKE ? ESCAPE '\\\\' OR ip LIKE ? ESCAPE '\\\\')";
    array_push($conParams, $like, $like, $like, $like, $like, $like);
}
$conWhereSql = $conWhere ? (' WHERE ' . implode(' AND ', $conWhere)) : '';
$conTotal = (int) (db_one("SELECT COUNT(*) AS c FROM contact_messages{$conWhereSql}", $conParams)['c'] ?? 0);
$conPages = max(1, (int) ceil($conTotal / $perPage));
if ($cpage > $conPages) {
    $cpage = $conPages;
}
$conOffset = ($cpage - 1) * $perPage;
$contactMsgs = db_all(
    "SELECT * FROM contact_messages{$conWhereSql} ORDER BY (status = 'new') DESC, id DESC LIMIT {$perPage} OFFSET {$conOffset}",
    $conParams
);
$newContactCount = (int) (db_one("SELECT COUNT(*) AS c FROM contact_messages WHERE status = 'new'")['c'] ?? 0);

// 4. Zero-Result Search Queries (search_misses)
$missSql = 'SELECT * FROM search_misses';
$missParams = [];
if ($q !== '') {
    $missSql .= " WHERE query_text LIKE ? ESCAPE '\\\\'";
    $missParams[] = '%' . escape_like($q) . '%';
}
$missSql .= ' ORDER BY hits DESC, last_searched_at DESC LIMIT 25';
$searchMisses = db_all($missSql, $missParams);

$buildLogsUrl = static function (array $overrides = []) use ($filter, $q, $spage, $rpage, $cpage): string {
    $query = [
        'filter' => $filter,
        'q'      => $q,
        'spage'  => $spage > 1 ? (string) $spage : '',
        'rpage'  => $rpage > 1 ? (string) $rpage : '',
        'cpage'  => $cpage > 1 ? (string) $cpage : '',
    ];
    foreach ($overrides as $k => $v) {
        $query[$k] = (string) $v;
    }
    if (($query['filter'] ?? '') === 'all') {
        $query['filter'] = '';
    }
    foreach (['spage', 'rpage', 'cpage'] as $pk) {
        if (($query[$pk] ?? '') === '1') {
            $query[$pk] = '';
        }
    }
    $clean = array_filter($query, static fn(string $v): bool => $v !== '');
    return url('/admin/logs' . ($clean ? ('?' . http_build_query($clean)) : ''));
};

$currentListUrl = $buildLogsUrl();
?>

<section class="bx-card" style="margin-bottom:18px;">
    <div style="display:flex;gap:8px;flex-wrap:wrap;margin-bottom:12px;">
        <a href="<?= e($buildLogsUrl(['filter' => 'all', 'spage' => '1', 'rpage' => '1'])) ?>" class="bx-btn <?= $filter === 'all' ? 'bx-btn-primary' : 'bx-btn-outline' ?> bx-btn-sm"><?= $lang === 'bn' ? 'সকল লগ' : 'All Logs' ?></a>
        <a href="<?= e($buildLogsUrl(['filter' => 'suspicious', 'spage' => '1', 'rpage' => '1'])) ?>" class="bx-btn <?= $filter === 'suspicious' ? 'bx-btn-accent' : 'bx-btn-outline' ?> bx-btn-sm">⚠ <?= $lang === 'bn' ? 'সন্দেহজনক' : 'Suspicious' ?></a>
        <a href="<?= e($buildLogsUrl(['filter' => 'anomalies', 'spage' => '1', 'rpage' => '1'])) ?>" class="bx-btn <?= $filter === 'anomalies' ? 'bx-btn-accent' : 'bx-btn-outline' ?> bx-btn-sm">🛡 <?= $lang === 'bn' ? 'রিডার অ্যানোমালি ও সাসপেনশন' : 'Anomalies & Suspensions' ?></a>
        <a href="<?= e($buildLogsUrl(['filter' => 'failed', 'spage' => '1', 'rpage' => '1'])) ?>" class="bx-btn <?= $filter === 'failed' ? 'bx-btn-primary' : 'bx-btn-outline' ?> bx-btn-sm"><?= $lang === 'bn' ? 'ব্যর্থ লগইন/লিমিট' : 'Failed / Rate-Limited' ?></a>
        <a href="<?= e($buildLogsUrl(['filter' => 'source_errors', 'spage' => '1', 'rpage' => '1'])) ?>" class="bx-btn <?= $filter === 'source_errors' ? 'bx-btn-primary' : 'bx-btn-outline' ?> bx-btn-sm"><?= $lang === 'bn' ? 'সোর্স/ড্রাইভ/CDN ত্রুটি' : 'Source / Drive / CDN' ?></a>
        <a href="<?= e($buildLogsUrl(['filter' => 'contact', 'cpage' => '1'])) ?>" class="bx-btn <?= $filter === 'contact' ? 'bx-btn-primary' : 'bx-btn-outline' ?> bx-btn-sm">
            ✉ <?= $lang === 'bn' ? 'যোগাযোগ বার্তা' : 'Contact Inbox' ?>
            <?php if ($newContactCount > 0): ?><span class="bx-badge bx-badge-accent" style="margin-left:4px;"><?= $newContactCount ?></span><?php endif; ?>
        </a>
    </div>

    <form method="get" action="<?= e(url('/admin/logs')) ?>" style="display:flex;gap:10px;flex-wrap:wrap;align-items:center;">
        <?php if ($filter !== 'all'): ?><input type="hidden" name="filter" value="<?= e($filter) ?>"><?php endif; ?>
        <input type="search" name="q" value="<?= e($q) ?>" class="bx-input" style="flex:1 1 260px;min-width:0;"
               placeholder="<?= e($lang === 'bn' ? 'IP, ইমেইল, ইভেন্ট বা বার্তায় খুঁজুন...' : 'Search IP, email, event type, details or contact messages...') ?>">
        <button type="submit" class="bx-btn bx-btn-primary"><?= $lang === 'bn' ? 'খুঁজুন' : 'Search' ?></button>
        <?php if ($q !== '' || $filter !== 'all'): ?>
            <a href="<?= e(url('/admin/logs')) ?>" class="bx-btn bx-btn-ghost"><?= $lang === 'bn' ? 'রিসেট' : 'Reset' ?></a>
        <?php endif; ?>
    </form>
</section>

<?php if ($filter !== 'contact'): ?>
<!-- Section 1: Security & Audit Events -->
<section class="bx-card" style="margin-bottom:20px;">
    <div style="display:flex;justify-content:space-between;align-items:center;gap:10px;flex-wrap:wrap;margin-bottom:12px;">
        <h2 style="margin:0;font-size:1.1rem;font-family:var(--font-serif);">
            <?= $lang === 'bn' ? 'নিরাপত্তা লগ (Security Logs)' : 'Security Logs' ?> (<?= $secTotal ?>)
        </h2>
        <small style="color:var(--text-muted);">25/page · Page <?= $spage ?>/<?= $secPages ?></small>
    </div>
    <div class="bx-table-wrap">
        <table class="bx-table bx-mobile-cards">
            <thead>
                <tr>
                    <th><?= $lang === 'bn' ? 'সময়' : 'Time' ?></th>
                    <th><?= $lang === 'bn' ? 'ইভেন্ট' : 'Event' ?></th>
                    <th><?= $lang === 'bn' ? 'পরিচয় / টার্গেট' : 'Identifier' ?></th>
                    <th>IP</th>
                    <th><?= $lang === 'bn' ? 'বিস্তারিত' : 'Details' ?></th>
                </tr>
            </thead>
            <tbody>
                <?php if (!$secLogs): ?>
                    <tr class="bx-empty-row"><td colspan="5" style="padding:22px;color:var(--text-muted);"><?= $lang === 'bn' ? 'কোনো নিরাপত্তা লগ নেই।' : 'No matching security events.' ?></td></tr>
                <?php else: foreach ($secLogs as $sl): ?>
                    <?php
                    $et = (string) $sl['event_type'];
                    $isAlert = str_contains($et, 'anomaly') || str_contains($et, 'suspend') || str_contains($et, 'failed') || str_contains($et, 'kicked');
                    ?>
                    <tr>
                        <td class="bx-td-primary"><?= e(date('d M Y, H:i:s', strtotime((string) $sl['created_at']))) ?></td>
                        <td data-label="Event">
                            <span class="bx-status-pill <?= $isAlert ? 'st-rejected' : 'st-pending' ?>"><?= e($et) ?></span>
                        </td>
                        <td data-label="Identifier" style="font-size:0.84rem;"><?= e((string) ($sl['identifier'] ?? '—')) ?></td>
                        <td data-label="IP"><code><?= e((string) $sl['ip_address']) ?></code></td>
                        <td data-label="Details" style="font-size:0.82rem;color:var(--text-muted);max-width:340px;overflow-wrap:anywhere;">
                            <?= e((string) ($sl['details'] ?? '—')) ?>
                        </td>
                    </tr>
                <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
    <?php if ($secPages > 1): ?>
        <nav class="bx-pagination" aria-label="Security Logs Pagination">
            <span style="font-size:0.84rem;color:var(--text-muted);">Page <?= $spage ?> of <?= $secPages ?></span>
            <div style="display:flex;gap:6px;flex-wrap:wrap;">
                <?php if ($spage > 1): ?><a href="<?= e($buildLogsUrl(['spage' => (string) ($spage - 1)])) ?>">« Prev</a><?php endif; ?>
                <?php for ($p = max(1, $spage - 2); $p <= min($secPages, $spage + 2); $p++): ?>
                    <a href="<?= e($buildLogsUrl(['spage' => (string) $p])) ?>" class="<?= $p === $spage ? 'active' : '' ?>"><?= $p ?></a>
                <?php endfor; ?>
                <?php if ($spage < $secPages): ?><a href="<?= e($buildLogsUrl(['spage' => (string) ($spage + 1)])) ?>">Next »</a><?php endif; ?>
            </div>
        </nav>
    <?php endif; ?>
</section>

<!-- Section 2: Reading Logs & Abuse Events -->
<section class="bx-card" style="margin-bottom:20px;">
    <div style="display:flex;justify-content:space-between;align-items:center;gap:10px;flex-wrap:wrap;margin-bottom:12px;">
        <h2 style="margin:0;font-size:1.1rem;font-family:var(--font-serif);">
            <?= $lang === 'bn' ? 'রিডিং কার্যক্রম লগ (Reading Logs)' : 'Reading & Anomaly Logs' ?> (<?= $readTotal ?>)
        </h2>
        <small style="color:var(--text-muted);">25/page · Page <?= $rpage ?>/<?= $readPages ?></small>
    </div>
    <div class="bx-table-wrap">
        <table class="bx-table bx-mobile-cards">
            <thead>
                <tr>
                    <th><?= $lang === 'bn' ? 'সময়' : 'Time' ?></th>
                    <th><?= $lang === 'bn' ? 'ব্যবহারকারী' : 'User' ?></th>
                    <th><?= $lang === 'bn' ? 'বই ও অ্যাকশন' : 'Book & Action' ?></th>
                    <th>IP / <?= $lang === 'bn' ? 'বিস্তারিত' : 'Details' ?></th>
                    <th><?= $lang === 'bn' ? 'অ্যাকশন' : 'Control' ?></th>
                </tr>
            </thead>
            <tbody>
                <?php if (!$readLogs): ?>
                    <tr class="bx-empty-row"><td colspan="5" style="padding:22px;color:var(--text-muted);"><?= $lang === 'bn' ? 'কোনো রিডিং লগ নেই।' : 'No matching reading logs.' ?></td></tr>
                <?php else: foreach ($readLogs as $rl): ?>
                    <?php
                    $act = (string) $rl['action'];
                    $isAnom = str_contains($act, 'anomaly') || str_contains($act, 'suspend') || str_contains($act, 'quota') || str_contains($act, 'kicked');
                    ?>
                    <tr>
                        <td class="bx-td-primary"><?= e(date('d M Y, H:i:s', strtotime((string) $rl['created_at']))) ?></td>
                        <td data-label="User">
                            <div><strong><?= e((string) ($rl['user_name'] ?: ('User #' . (int) $rl['user_id']))) ?></strong></div>
                            <?php if (!empty($rl['user_email'])): ?>
                                <div style="font-size:0.78rem;color:var(--text-muted);"><?= e((string) $rl['user_email']) ?></div>
                            <?php endif; ?>
                        </td>
                        <td data-label="Action">
                            <div style="font-weight:600;font-size:0.86rem;"><?= e((string) ($rl['title_en'] ?? '—')) ?></div>
                            <span class="bx-status-pill <?= $isAnom ? 'st-rejected' : 'st-active' ?>"><?= e($act) ?></span>
                        </td>
                        <td data-label="Details" style="font-size:0.82rem;color:var(--text-muted);max-width:290px;overflow-wrap:anywhere;">
                            <code><?= e((string) $rl['ip_address']) ?></code>
                            <?php if (!empty($rl['details'])): ?> · <?= e((string) $rl['details']) ?><?php endif; ?>
                        </td>
                        <td class="bx-td-actions">
                            <?php if (!empty($rl['user_id'])): ?>
                                <form method="post" action="<?= e(url('/admin/users')) ?>" style="margin:0;">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="action" value="toggle_user_block">
                                    <input type="hidden" name="user_id" value="<?= (int) $rl['user_id'] ?>">
                                    <input type="hidden" name="return_to" value="logs">
                                    <button type="submit" class="bx-btn <?= (int) ($rl['user_active'] ?? 1) ? 'bx-btn-outline' : 'bx-btn-primary' ?> bx-btn-sm">
                                        <?= (int) ($rl['user_active'] ?? 1) ? ($lang === 'bn' ? 'ব্লক' : 'Block') : ($lang === 'bn' ? 'আনব্লক' : 'Unblock') ?>
                                    </button>
                                </form>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
    <?php if ($readPages > 1): ?>
        <nav class="bx-pagination" aria-label="Reading Logs Pagination">
            <span style="font-size:0.84rem;color:var(--text-muted);">Page <?= $rpage ?> of <?= $readPages ?></span>
            <div style="display:flex;gap:6px;flex-wrap:wrap;">
                <?php if ($rpage > 1): ?><a href="<?= e($buildLogsUrl(['rpage' => (string) ($rpage - 1)])) ?>">« Prev</a><?php endif; ?>
                <?php for ($p = max(1, $rpage - 2); $p <= min($readPages, $rpage + 2); $p++): ?>
                    <a href="<?= e($buildLogsUrl(['rpage' => (string) $p])) ?>" class="<?= $p === $rpage ? 'active' : '' ?>"><?= $p ?></a>
                <?php endfor; ?>
                <?php if ($rpage < $readPages): ?><a href="<?= e($buildLogsUrl(['rpage' => (string) ($rpage + 1)])) ?>">Next »</a><?php endif; ?>
            </div>
        </nav>
    <?php endif; ?>
</section>
<?php endif; ?>

<!-- Section 3: Stored Contact Messages -->
<section class="bx-card">
    <div style="display:flex;justify-content:space-between;align-items:center;gap:10px;flex-wrap:wrap;margin-bottom:12px;">
        <h2 style="margin:0;font-size:1.1rem;font-family:var(--font-serif);">
            <?= $lang === 'bn' ? 'যোগাযোগ ফর্ম বার্তা (Contact Messages)' : 'Contact Form Messages' ?> (<?= $conTotal ?>)
        </h2>
        <small style="color:var(--text-muted);">25/page · Page <?= $cpage ?>/<?= $conPages ?></small>
    </div>
    <div class="bx-table-wrap">
        <table class="bx-table bx-mobile-cards">
            <thead>
                <tr>
                    <th><?= $lang === 'bn' ? 'প্রেরক' : 'Sender' ?></th>
                    <th><?= $lang === 'bn' ? 'বিষয় ও বার্তা' : 'Subject & Message' ?></th>
                    <th>IP / <?= $lang === 'bn' ? 'সময়' : 'Time' ?></th>
                    <th><?= $lang === 'bn' ? 'অবস্থা' : 'Status' ?></th>
                    <th><?= $lang === 'bn' ? 'অ্যাকশন' : 'Actions' ?></th>
                </tr>
            </thead>
            <tbody>
                <?php if (!$contactMsgs): ?>
                    <tr class="bx-empty-row"><td colspan="5" style="padding:22px;color:var(--text-muted);"><?= $lang === 'bn' ? 'কোনো যোগাযোগ বার্তা পাওয়া যায়নি।' : 'No contact messages found.' ?></td></tr>
                <?php else: foreach ($contactMsgs as $cm): ?>
                    <?php $cst = (string) $cm['status']; ?>
                    <tr>
                        <td class="bx-td-primary">
                            <strong><?= e((string) $cm['name']) ?></strong>
                            <div style="font-size:0.82rem;color:var(--text-muted);"><?= e((string) $cm['email']) ?></div>
                            <?php if (!empty($cm['phone'])): ?>
                                <div style="font-size:0.78rem;color:var(--text-muted);"><?= e((string) $cm['phone']) ?></div>
                            <?php endif; ?>
                        </td>
                        <td data-label="Message" style="max-width:360px;overflow-wrap:anywhere;">
                            <strong><?= e((string) $cm['subject']) ?></strong>
                            <div style="font-size:0.84rem;color:var(--text-muted);margin-top:3px;white-space:pre-line;"><?= e((string) $cm['message']) ?></div>
                        </td>
                        <td data-label="IP / Time" style="font-size:0.82rem;color:var(--text-muted);">
                            <code><?= e((string) $cm['ip']) ?></code>
                            <div><?= e(date('d M Y, H:i', strtotime((string) $cm['created_at']))) ?></div>
                        </td>
                        <td data-label="Status">
                            <span class="bx-status-pill <?= $cst === 'replied' ? 'st-approved' : ($cst === 'read' ? 'st-active' : 'st-pending') ?>">
                                <?= e(ucfirst($cst)) ?>
                            </span>
                        </td>
                        <td class="bx-td-actions">
                            <div style="display:flex;gap:6px;flex-wrap:wrap;justify-content:flex-end;">
                                <?php if ($cst === 'new'): ?>
                                    <form method="post" action="<?= e(url('/admin/logs')) ?>" style="margin:0;">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="action" value="contact_status">
                                        <input type="hidden" name="contact_id" value="<?= (int) $cm['id'] ?>">
                                        <input type="hidden" name="status" value="read">
                                        <input type="hidden" name="_return" value="<?= e($currentListUrl) ?>">
                                        <button type="submit" class="bx-btn bx-btn-outline bx-btn-sm"><?= $lang === 'bn' ? 'পঠিত' : 'Mark Read' ?></button>
                                    </form>
                                <?php endif; ?>
                                <?php if ($cst !== 'replied'): ?>
                                    <form method="post" action="<?= e(url('/admin/logs')) ?>" style="margin:0;">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="action" value="contact_status">
                                        <input type="hidden" name="contact_id" value="<?= (int) $cm['id'] ?>">
                                        <input type="hidden" name="status" value="replied">
                                        <input type="hidden" name="_return" value="<?= e($currentListUrl) ?>">
                                        <button type="submit" class="bx-btn bx-btn-primary bx-btn-sm"><?= $lang === 'bn' ? 'উত্তর দেওয়া হয়েছে' : 'Mark Replied' ?></button>
                                    </form>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
    <?php if ($conPages > 1): ?>
        <nav class="bx-pagination" aria-label="Contact Messages Pagination">
            <span style="font-size:0.84rem;color:var(--text-muted);">Page <?= $cpage ?> of <?= $conPages ?></span>
            <div style="display:flex;gap:6px;flex-wrap:wrap;">
                <?php if ($cpage > 1): ?><a href="<?= e($buildLogsUrl(['cpage' => (string) ($cpage - 1)])) ?>">« Prev</a><?php endif; ?>
                <?php for ($p = max(1, $cpage - 2); $p <= min($conPages, $cpage + 2); $p++): ?>
                    <a href="<?= e($buildLogsUrl(['cpage' => (string) $p])) ?>" class="<?= $p === $cpage ? 'active' : '' ?>"><?= $p ?></a>
                <?php endfor; ?>
                <?php if ($cpage < $conPages): ?><a href="<?= e($buildLogsUrl(['cpage' => (string) ($cpage + 1)])) ?>">Next »</a><?php endif; ?>
            </div>
        </nav>
    <?php endif; ?>
</section>

<!-- Section 4: Zero-Result Searches (Search Misses) -->
<section class="bx-card" style="margin-top:20px;">
    <div style="display:flex;justify-content:space-between;align-items:center;gap:10px;flex-wrap:wrap;margin-bottom:12px;">
        <h2 style="margin:0;font-size:1.1rem;font-family:var(--font-serif);">
            <?= $lang === 'bn' ? 'খালি অনুসন্ধান লগ (Zero-Result Searches)' : 'Zero-Result Search Queries (Top 25)' ?>
        </h2>
        <small style="color:var(--text-muted);"><?= $lang === 'bn' ? 'যে শব্দগুলোতে কোনো বই পাওয়া যায়নি' : 'Queries returning 0 results — useful for new catalog requests' ?></small>
    </div>
    <div class="bx-table-wrap">
        <table class="bx-table bx-mobile-cards">
            <thead>
                <tr>
                    <th><?= $lang === 'bn' ? 'অনুসন্ধান শব্দ' : 'Query' ?></th>
                    <th><?= $lang === 'bn' ? 'মোট সংখ্যা' : 'Search Count' ?></th>
                    <th><?= $lang === 'bn' ? 'সর্বশেষ সময়' : 'Last Searched' ?></th>
                </tr>
            </thead>
            <tbody>
                <?php if (!$searchMisses): ?>
                    <tr class="bx-empty-row"><td colspan="3" style="padding:18px;color:var(--text-muted);"><?= $lang === 'bn' ? 'এখনো কোনো খালি অনুসন্ধান লগ নেই।' : 'No empty-result search queries logged yet.' ?></td></tr>
                <?php else: foreach ($searchMisses as $sm): ?>
                    <tr>
                        <td class="bx-td-primary"><strong><?= e((string) $sm['query_text']) ?></strong></td>
                        <td data-label="Hits"><span class="bx-badge bx-badge-teal"><?= (int) $sm['hits'] ?></span></td>
                        <td data-label="Last Searched" style="font-size:0.84rem;color:var(--text-muted);"><?= e(date('d M Y, H:i', strtotime((string) $sm['last_searched_at']))) ?></td>
                    </tr>
                <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
</section>
