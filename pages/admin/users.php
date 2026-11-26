<?php
declare(strict_types=1);

$lang    = current_lang();
$filter  = (string) ($_GET['filter'] ?? 'all');
$q       = trim((string) ($_GET['q'] ?? ''));
$page    = max(1, (int) ($_GET['page'] ?? 1));
$perPage = 25;

if (!in_array($filter, ['all', 'active', 'blocked', 'suspended', 'alerts'], true)) {
    $filter = 'all';
}

$where  = [];
$params = [];
if ($filter === 'active') {
    $where[] = 'u.is_active = 1';
} elseif ($filter === 'blocked') {
    $where[] = 'u.is_active = 0';
} elseif ($filter === 'suspended') {
    $where[] = 'u.reading_suspended_until IS NOT NULL AND u.reading_suspended_until > NOW()';
} elseif ($filter === 'alerts') {
    $where[] = "EXISTS (SELECT 1 FROM reading_logs rl WHERE rl.user_id = u.id AND rl.action IN ('fast_sequential_burst','many_sessions_anomaly','page_out_of_range','hotlink_attempt','unauthorized_stream','token_reused','reading_suspended','daily_quota_exceeded'))";
}

if ($q !== '') {
    $like = '%' . $q . '%';
    $where[] = '(u.name LIKE ? OR u.email LIKE ? OR COALESCE(u.phone, "") LIKE ?)';
    array_push($params, $like, $like, $like);
}

$whereSql = $where ? (' WHERE ' . implode(' AND ', $where)) : '';

$totalUsers = (int) (db_one("SELECT COUNT(*) AS c FROM users u{$whereSql}", $params)['c'] ?? 0);
$totalPages = max(1, (int) ceil($totalUsers / $perPage));
if ($page > $totalPages) {
    $page = $totalPages;
}
$offset = ($page - 1) * $perPage;

$users = db_all(
    "SELECT u.*,
            (SELECT COUNT(*) FROM library l WHERE l.user_id = u.id) AS lib_count,
            (SELECT COUNT(*) FROM orders o WHERE o.user_id = u.id AND o.status = 'pending') AS pend_orders,
            (SELECT COUNT(*) FROM reading_logs rl WHERE rl.user_id = u.id AND rl.action IN ('fast_sequential_burst','many_sessions_anomaly','page_out_of_range','hotlink_attempt','unauthorized_stream','token_reused','reading_suspended','daily_quota_exceeded')) AS anomaly_count
     FROM users u{$whereSql}
     ORDER BY u.id DESC
     LIMIT {$perPage} OFFSET {$offset}",
    $params
);

$viewUserId   = (int) ($_GET['view'] ?? 0);
$viewUser     = $viewUserId > 0 ? db_one('SELECT * FROM users WHERE id = ?', [$viewUserId]) : null;
$viewActivity = $viewUser ? db_all(
    'SELECT rl.*, i.title_en FROM reading_logs rl LEFT JOIN items i ON i.id = rl.item_id WHERE rl.user_id = ? ORDER BY rl.created_at DESC LIMIT 30',
    [$viewUserId]
) : [];

$buildUsersUrl = static function (array $overrides = []) use ($filter, $q, $page): string {
    $query = [
        'filter' => $filter,
        'q'      => $q,
        'page'   => $page > 1 ? (string) $page : '',
    ];
    foreach ($overrides as $k => $v) {
        $query[$k] = (string) $v;
    }
    if (($query['filter'] ?? '') === 'all') {
        $query['filter'] = '';
    }
    if (($query['page'] ?? '') === '1') {
        $query['page'] = '';
    }
    $clean = array_filter($query, static fn(string $v): bool => $v !== '');
    return url('/admin/users' . ($clean ? ('?' . http_build_query($clean)) : ''));
};

$usersReadOnly = (($admin['role'] ?? '') === 'support');
$currentListUrl = $buildUsersUrl();
$showFrom = $totalUsers > 0 ? ($offset + 1) : 0;
$showTo   = min($totalUsers, $offset + count($users));
?>
<?php if ($usersReadOnly): ?>
    <div class="bx-alert bx-alert-info" style="margin-bottom:14px;">
        <?= $lang === 'bn' ? 'সাপোর্ট রোল মোড: ব্যবহারকারীর তালিকা ও লগ শুধুমাত্র দেখার জন্য (Read-Only)।' : 'Support role: User directory and logs are view-only.' ?>
    </div>
<?php endif; ?>

<section class="bx-card" style="margin-bottom:18px;">
    <div style="display:flex;gap:8px;flex-wrap:wrap;margin-bottom:12px;">
        <a href="<?= e($buildUsersUrl(['filter' => 'all', 'page' => '1'])) ?>" class="bx-btn <?= $filter === 'all' ? 'bx-btn-primary' : 'bx-btn-outline' ?> bx-btn-sm"><?= $lang === 'bn' ? 'সকল' : 'All Users' ?></a>
        <a href="<?= e($buildUsersUrl(['filter' => 'active', 'page' => '1'])) ?>" class="bx-btn <?= $filter === 'active' ? 'bx-btn-primary' : 'bx-btn-outline' ?> bx-btn-sm"><?= $lang === 'bn' ? 'সক্রিয়' : 'Active' ?></a>
        <a href="<?= e($buildUsersUrl(['filter' => 'blocked', 'page' => '1'])) ?>" class="bx-btn <?= $filter === 'blocked' ? 'bx-btn-primary' : 'bx-btn-outline' ?> bx-btn-sm"><?= $lang === 'bn' ? 'ব্লকড' : 'Blocked' ?></a>
        <a href="<?= e($buildUsersUrl(['filter' => 'suspended', 'page' => '1'])) ?>" class="bx-btn <?= $filter === 'suspended' ? 'bx-btn-accent' : 'bx-btn-outline' ?> bx-btn-sm">⏸ <?= $lang === 'bn' ? 'রিডার সাসপেন্ডেড' : 'Reader Suspended' ?></a>
        <a href="<?= e($buildUsersUrl(['filter' => 'alerts', 'page' => '1'])) ?>" class="bx-btn <?= $filter === 'alerts' ? 'bx-btn-accent' : 'bx-btn-outline' ?> bx-btn-sm">⚠ <?= $lang === 'bn' ? 'সন্দেহজনক' : 'With Anomalies' ?></a>
    </div>

    <form method="get" action="<?= e(url('/admin/users')) ?>" style="display:flex;gap:10px;flex-wrap:wrap;align-items:center;">
        <?php if ($filter !== 'all'): ?><input type="hidden" name="filter" value="<?= e($filter) ?>"><?php endif; ?>
        <input type="search" name="q" value="<?= e($q) ?>" class="bx-input" style="flex:1 1 260px;min-width:0;"
               placeholder="<?= e($lang === 'bn' ? 'নাম, ইমেইল বা ফোন নম্বর দিয়ে খুঁজুন...' : 'Search user by name, email or phone...') ?>">
        <button type="submit" class="bx-btn bx-btn-primary"><?= $lang === 'bn' ? 'খুঁজুন' : 'Search' ?></button>
        <?php if ($q !== '' || $filter !== 'all'): ?>
            <a href="<?= e(url('/admin/users')) ?>" class="bx-btn bx-btn-ghost"><?= $lang === 'bn' ? 'রিসেট' : 'Reset' ?></a>
        <?php endif; ?>
    </form>
</section>

<?php if ($viewUser): ?>
<section class="bx-card" id="user-activity" style="margin-bottom:20px;border:2px solid var(--primary);">
    <div style="display:flex;justify-content:space-between;align-items:center;gap:10px;flex-wrap:wrap;margin-bottom:10px;">
        <div>
            <h3 style="margin:0;font-family:var(--font-serif);"><?= e((string) $viewUser['name']) ?> — <?= e((string) $viewUser['email']) ?></h3>
            <small style="color:var(--text-muted);">
                <?= $lang === 'bn' ? 'সাম্প্রতিক ৩০টি পাঠ/নিরাপত্তা কার্যক্রম' : 'Latest 30 reading & security logs' ?>
            </small>
        </div>
        <a href="<?= e($currentListUrl) ?>" class="bx-btn bx-btn-outline bx-btn-sm">✕ <?= $lang === 'bn' ? 'বন্ধ করুন' : 'Close' ?></a>
    </div>
    <div class="bx-table-wrap">
        <table class="bx-table bx-mobile-cards">
            <thead>
                <tr>
                    <th><?= $lang === 'bn' ? 'সময়' : 'Time' ?></th>
                    <th><?= $lang === 'bn' ? 'কার্যক্রম' : 'Action' ?></th>
                    <th><?= $lang === 'bn' ? 'বই' : 'Item' ?></th>
                    <th>IP</th>
                    <th><?= $lang === 'bn' ? 'বিস্তারিত' : 'Details' ?></th>
                </tr>
            </thead>
            <tbody>
                <?php if (!$viewActivity): ?>
                    <tr class="bx-empty-row"><td colspan="5" style="color:var(--text-muted);padding:18px;"><?= $lang === 'bn' ? 'কোনো লগ পাওয়া যায়নি।' : 'No activity logs for this user.' ?></td></tr>
                <?php else: foreach ($viewActivity as $act): ?>
                    <tr>
                        <td class="bx-td-primary"><?= e(date('d M, H:i:s', strtotime((string) $act['created_at']))) ?></td>
                        <td data-label="Action"><span class="bx-badge"><?= e((string) $act['action']) ?></span></td>
                        <td data-label="Item"><?= e((string) ($act['title_en'] ?? '—')) ?></td>
                        <td data-label="IP"><code><?= e((string) $act['ip_address']) ?></code></td>
                        <td data-label="Details" style="font-size:0.82rem;color:var(--text-muted);"><?= e((string) ($act['details'] ?? '—')) ?></td>
                    </tr>
                <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
</section>
<?php endif; ?>

<section class="bx-card">
    <div style="display:flex;justify-content:space-between;align-items:center;gap:10px;flex-wrap:wrap;margin-bottom:12px;">
        <h2 style="margin:0;font-size:1.12rem;font-family:var(--font-serif);">
            <?= $lang === 'bn' ? 'ব্যবহারকারী তালিকা' : 'Users' ?> (<?= $totalUsers ?>)
        </h2>
        <small style="color:var(--text-muted);">
            <?= $totalUsers > 0 ? "Showing {$showFrom}–{$showTo} of {$totalUsers}" : '0 users' ?>
        </small>
    </div>

    <div class="bx-scroll-hint"><?= $lang === 'bn' ? 'বিস্তারিত দেখতে ডানে স্ক্রল করুন →' : 'Scroll horizontally if needed →' ?></div>
    <div class="bx-table-wrap">
        <table class="bx-table bx-mobile-cards">
            <thead>
                <tr>
                    <th><?= $lang === 'bn' ? 'ব্যবহারকারী' : 'User' ?></th>
                    <th><?= $lang === 'bn' ? 'লাইব্রেরি / অর্ডার' : 'Library / Orders' ?></th>
                    <th><?= $lang === 'bn' ? 'সতর্কতা' : 'Alerts' ?></th>
                    <th><?= $lang === 'bn' ? 'অবস্থা' : 'Status' ?></th>
                    <th><?= $lang === 'bn' ? 'অ্যাকশন' : 'Actions' ?></th>
                </tr>
            </thead>
            <tbody>
                <?php if (!$users): ?>
                    <tr class="bx-empty-row">
                        <td colspan="5" style="padding:26px;color:var(--text-muted);">
                            <?= $lang === 'bn' ? 'কোনো ব্যবহারকারী পাওয়া যায়নি।' : 'No matching users found.' ?>
                        </td>
                    </tr>
                <?php else: foreach ($users as $u): ?>
                    <?php
                    $uid = (int) $u['id'];
                    $isActive = (int) $u['is_active'] === 1;
                    $suspUntil = !empty($u['reading_suspended_until']) ? strtotime((string) $u['reading_suspended_until']) : 0;
                    $isSuspended = $suspUntil > time();
                    ?>
                    <tr>
                        <td class="bx-td-primary">
                            <strong><?= e((string) $u['name']) ?></strong>
                            <div style="font-size:0.82rem;color:var(--text-muted);"><?= e((string) $u['email']) ?></div>
                            <?php if (!empty($u['phone'])): ?>
                                <div style="font-size:0.78rem;color:var(--text-muted);"><?= e((string) $u['phone']) ?></div>
                            <?php endif; ?>
                        </td>
                        <td data-label="<?= e($lang === 'bn' ? 'লাইব্রেরি' : 'Library') ?>">
                            <span class="bx-badge"><?= (int) $u['lib_count'] ?> <?= $lang === 'bn' ? 'টি বই' : 'books' ?></span>
                            <?php if ((int) $u['pend_orders'] > 0): ?>
                                <span class="bx-status-pill st-pending"><?= (int) $u['pend_orders'] ?> pending</span>
                            <?php endif; ?>
                        </td>
                        <td data-label="<?= e($lang === 'bn' ? 'সতর্কতা' : 'Alerts') ?>">
                            <?php if ((int) $u['anomaly_count'] > 0): ?>
                                <span class="bx-status-pill st-rejected"><?= (int) $u['anomaly_count'] ?> alert(s)</span>
                            <?php else: ?>
                                <span style="color:var(--text-muted);font-size:0.84rem;">0</span>
                            <?php endif; ?>
                            <?php if ($isSuspended): ?>
                                <div style="margin-top:4px;">
                                    <span class="bx-status-pill st-pending">
                                        ⏸ <?= $lang === 'bn' ? 'সাসপেন্ডেড' : 'Suspended until ' . date('H:i', $suspUntil) ?>
                                    </span>
                                </div>
                            <?php endif; ?>
                        </td>
                        <td data-label="<?= e($lang === 'bn' ? 'অবস্থা' : 'Status') ?>">
                            <span class="bx-status-pill <?= $isActive ? 'st-active' : 'st-blocked' ?>">
                                <?= $isActive ? ($lang === 'bn' ? 'সক্রিয়' : 'Active') : ($lang === 'bn' ? 'ব্লকড' : 'Blocked') ?>
                            </span>
                        </td>
                        <td class="bx-td-actions">
                            <div style="display:flex;gap:6px;flex-wrap:wrap;justify-content:flex-end;align-items:center;">
                                <a href="<?= e($buildUsersUrl(['view' => (string) $uid])) ?>#user-activity" class="bx-btn bx-btn-outline bx-btn-sm">
                                    <?= $lang === 'bn' ? 'লগ' : 'Logs' ?>
                                </a>
                                <?php if (!$usersReadOnly): ?>
                                    <?php if ($isSuspended): ?>
                                        <form method="post" action="<?= e(url('/admin/users')) ?>" style="margin:0;">
                                            <?= csrf_field() ?>
                                            <input type="hidden" name="action" value="unsuspend_user">
                                            <input type="hidden" name="user_id" value="<?= $uid ?>">
                                            <input type="hidden" name="_return" value="<?= e($currentListUrl) ?>">
                                            <button type="submit" class="bx-btn bx-btn-accent bx-btn-sm">
                                                <?= $lang === 'bn' ? 'সাসপেনশন তুলুন' : 'Unsuspend' ?>
                                            </button>
                                        </form>
                                    <?php endif; ?>
                                    <form method="post" action="<?= e(url('/admin/users')) ?>" style="margin:0;">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="action" value="force_logout_user">
                                        <input type="hidden" name="user_id" value="<?= $uid ?>">
                                        <input type="hidden" name="_return" value="<?= e($currentListUrl) ?>">
                                        <button type="submit" class="bx-btn bx-btn-ghost bx-btn-sm">
                                            <?= $lang === 'bn' ? 'লগআউট' : 'Kick Session' ?>
                                        </button>
                                    </form>
                                    <form method="post" action="<?= e(url('/admin/users')) ?>" style="margin:0;">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="action" value="toggle_user_block">
                                        <input type="hidden" name="user_id" value="<?= $uid ?>">
                                        <input type="hidden" name="_return" value="<?= e($currentListUrl) ?>">
                                        <button type="submit" class="bx-btn <?= $isActive ? 'bx-btn-outline' : 'bx-btn-primary' ?> bx-btn-sm"
                                                data-confirm="<?= e($isActive ? ($lang === 'bn' ? 'ব্যবহারকারীকে ব্লক করবেন?' : 'Block this user?') : ($lang === 'bn' ? 'ব্যবহারকারীকে আনব্লক করবেন?' : 'Unblock this user?')) ?>">
                                            <?= $isActive ? ($lang === 'bn' ? 'ব্লক' : 'Block') : ($lang === 'bn' ? 'আনব্লক' : 'Unblock') ?>
                                        </button>
                                    </form>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>

    <?php if ($totalPages > 1): ?>
        <nav class="bx-pagination" aria-label="Users Pagination">
            <span style="font-size:0.85rem;color:var(--text-muted);">
                Page <?= $page ?> of <?= $totalPages ?> (<?= $totalUsers ?> total)
            </span>
            <div style="display:flex;gap:6px;flex-wrap:wrap;">
                <?php if ($page > 1): ?>
                    <a href="<?= e($buildUsersUrl(['page' => (string) ($page - 1)])) ?>">« <?= $lang === 'bn' ? 'আগের' : 'Prev' ?></a>
                <?php endif; ?>
                <?php
                $winStart = max(1, $page - 2);
                $winEnd   = min($totalPages, $page + 2);
                for ($p = $winStart; $p <= $winEnd; $p++):
                ?>
                    <a href="<?= e($buildUsersUrl(['page' => (string) $p])) ?>" class="<?= $p === $page ? 'active' : '' ?>"><?= $p ?></a>
                <?php endfor; ?>
                <?php if ($page < $totalPages): ?>
                    <a href="<?= e($buildUsersUrl(['page' => (string) ($page + 1)])) ?>"><?= $lang === 'bn' ? 'পরের' : 'Next' ?> »</a>
                <?php endif; ?>
            </div>
        </nav>
    <?php endif; ?>
</section>
