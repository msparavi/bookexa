<?php
declare(strict_types=1);

$lang = current_lang();
$q = trim((string) ($_GET['q'] ?? ''));

$whereSql = '';
$params = [];
if ($q !== '') {
    $whereSql = 'WHERE u.name LIKE ? OR u.email LIKE ? OR u.phone LIKE ?';
    $like = '%' . $q . '%';
    $params = [$like, $like, $like];
}

$users = db_all(
    "SELECT u.id, u.name, u.email, u.phone, u.is_active, u.active_session_token, u.active_session_updated_at, u.created_at,
            (SELECT COUNT(*) FROM library l WHERE l.user_id = u.id) AS lib_count,
            (SELECT COUNT(*) FROM reading_logs rl WHERE rl.user_id = u.id) AS read_count,
            (SELECT COUNT(*) FROM wishlist w WHERE w.user_id = u.id) AS wish_count,
            (SELECT COUNT(*) FROM reading_logs rl2 WHERE rl2.user_id = u.id AND rl2.action NOT IN ('stream_pdf', 'open_reader', 'read_sample', 'sample_open', 'page_view')) AS sus_count
     FROM users u
     $whereSql
     ORDER BY u.id DESC
     LIMIT 100",
    $params
);

$activityUserId = !empty($_GET['activity_user']) ? (int) $_GET['activity_user'] : null;
$activityUser = null;
$userReads = [];
if ($activityUserId) {
    $activityUser = db_one('SELECT id, name, email, phone, active_session_token, active_session_updated_at FROM users WHERE id = ?', [$activityUserId]);
    if ($activityUser) {
        $userReads = db_all(
            "SELECT rl.*, i.title_en, i.title_bn
             FROM reading_logs rl
             LEFT JOIN items i ON i.id = rl.item_id
             WHERE rl.user_id = ?
             ORDER BY rl.id DESC LIMIT 25",
            [$activityUserId]
        );
    }
}
?>

<!-- Mobile Filter Trigger -->
<div class="bx-mobile-only-bar">
    <button type="button" class="bx-btn bx-btn-outline" data-open-sheet="user-filter-sheet">
        <?= $lang === 'bn' ? 'সার্চ ও ফিল্টার' : 'Search Users' ?>
        <?= $q !== '' ? '(1)' : '' ?>
    </button>
    <?php if ($q !== ''): ?>
        <a href="<?= e(url('/admin/users')) ?>" class="bx-btn bx-btn-ghost"><?= $lang === 'bn' ? 'রিসেট' : 'Reset' ?></a>
    <?php endif; ?>
</div>

<div id="user-filter-sheet" class="bx-filter-sheet-wrap" style="margin-bottom:18px;">
    <section class="bx-card bx-filter-sheet-card">
        <div class="bx-sheet-head">
            <strong style="font-family:var(--font-serif);"><?= $lang === 'bn' ? 'ব্যবহারকারী খুঁজুন' : 'Search Users' ?></strong>
            <button type="button" class="bx-btn bx-btn-ghost" data-close-sheet aria-label="Close" style="min-width:44px;min-height:44px;">✕</button>
        </div>
        <form method="get" action="<?= e(url('/admin/users')) ?>" style="display:flex;gap:10px;flex-wrap:wrap;align-items:center;">
            <input type="search" name="q" class="bx-input" style="flex:1 1 240px;min-width:0;" value="<?= e($q) ?>" placeholder="<?= $lang === 'bn' ? 'নাম, ইমেইল বা ফোন দিয়ে খুঁজুন...' : 'Search users by name, email or phone...' ?>">
            <button type="submit" class="bx-btn bx-btn-primary"><?= $lang === 'bn' ? 'খুঁজুন' : 'Search' ?></button>
            <?php if ($q !== ''): ?>
                <a href="<?= e(url('/admin/users')) ?>" class="bx-btn bx-btn-ghost"><?= $lang === 'bn' ? 'সব দেখুন' : 'Reset' ?></a>
            <?php endif; ?>
        </form>
    </section>
</div>

<?php if ($activityUser): ?>
    <section class="bx-card" id="user-activity" style="margin-bottom:20px;border-left:4px solid var(--teal);">
        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:12px;flex-wrap:wrap;gap:8px;">
            <div>
                <h2 style="margin:0;font-size:1.08rem;font-family:var(--font-serif);">
                    <?= $lang === 'bn' ? 'পঠন কার্যক্রম:' : 'Reading Activity:' ?> <?= e($activityUser['name']) ?>
                </h2>
                <small style="color:var(--text-muted);"><?= e($activityUser['email'] ?: ($activityUser['phone'] ?? '')) ?></small>
            </div>
            <a href="<?= e(url('/admin/users' . ($q !== '' ? '?q=' . urlencode($q) : ''))) ?>" class="bx-btn bx-btn-ghost"><?= $lang === 'bn' ? 'বন্ধ করুন' : 'Close' ?></a>
        </div>
        <?php if (!$userReads): ?>
            <p style="color:var(--text-muted);"><?= $lang === 'bn' ? 'এই ব্যবহারকারীর কোনো পাঠ লগ নেই।' : 'No reading activity recorded for this user.' ?></p>
        <?php else: ?>
            <div class="bx-table-wrap">
                <table class="bx-table bx-mobile-cards">
                    <thead>
                        <tr>
                            <th><?= $lang === 'bn' ? 'বই' : 'Book' ?></th>
                            <th><?= $lang === 'bn' ? 'ইভেন্ট' : 'Action' ?></th>
                            <th><?= $lang === 'bn' ? 'বিস্তারিত' : 'Details' ?></th>
                            <th>IP</th>
                            <th><?= $lang === 'bn' ? 'সময়' : 'Time' ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($userReads as $ur): ?>
                            <?php $isSus = !in_array((string) $ur['action'], ['stream_pdf', 'open_reader', 'read_sample', 'sample_open'], true); ?>
                            <tr>
                                <td class="bx-td-primary"><strong><?= e($lang === 'bn' ? ($ur['title_bn'] ?: $ur['title_en'] ?: '-') : ($ur['title_en'] ?: '-')) ?></strong></td>
                                <td data-label="<?= e($lang === 'bn' ? 'ইভেন্ট' : 'Action') ?>"><span class="bx-status-pill <?= $isSus ? 'st-blocked' : 'st-active' ?>"><?= e($ur['action']) ?></span></td>
                                <td data-label="<?= e($lang === 'bn' ? 'বিস্তারিত' : 'Details') ?>" style="font-size:0.82rem;color:var(--text-muted);"><?= e((string) ($ur['details'] ?? '—')) ?></td>
                                <td data-label="IP"><code><?= e($ur['ip_address']) ?></code></td>
                                <td data-label="<?= e($lang === 'bn' ? 'সময়' : 'Time') ?>" style="font-size:0.8rem;color:var(--text-muted);"><?= e($ur['created_at']) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </section>
<?php endif; ?>

<section class="bx-card">
    <h2 style="margin:0 0 14px;font-size:1.1rem;font-family:var(--font-serif);"><?= $lang === 'bn' ? 'নিবন্ধিত ব্যবহারকারী' : 'Registered Readers' ?> (<?= count($users) ?>)</h2>
    <div class="bx-table-wrap">
        <table class="bx-table bx-mobile-cards">
            <thead>
                <tr>
                    <th>ID</th>
                    <th><?= $lang === 'bn' ? 'নাম ও যোগাযোগ' : 'User' ?></th>
                    <th><?= $lang === 'bn' ? 'সেশন ও নিরাপত্তা' : 'Session / Security' ?></th>
                    <th><?= $lang === 'bn' ? 'লাইব্রেরি / পাঠ' : 'Library / Reads' ?></th>
                    <th><?= $lang === 'bn' ? 'অ্যাকশন' : 'Actions' ?></th>
                </tr>
            </thead>
            <tbody>
                <?php if (!$users): ?>
                    <tr class="bx-empty-row"><td colspan="5" style="color:var(--text-muted);padding:24px;"><?= $lang === 'bn' ? 'কোনো ব্যবহারকারী পাওয়া যায়নি।' : 'No users found.' ?></td></tr>
                <?php else: ?>
                    <?php foreach ($users as $u): ?>
                        <?php $uid = (int) $u['id']; $hasSess = !empty($u['active_session_token']); $sus = (int) $u['sus_count']; ?>
                        <tr>
                            <td data-label="ID">#<?= $uid ?></td>
                            <td class="bx-td-primary">
                                <strong><?= e($u['name']) ?></strong>
                                <span class="bx-ellipsis" style="font-size:0.8rem;color:var(--text-muted);"><?= e($u['email'] ?: ($u['phone'] ?? '')) ?></span>
                            </td>
                            <td data-label="<?= e($lang === 'bn' ? 'অবস্থা' : 'Security') ?>">
                                <div style="display:flex;gap:6px;flex-wrap:wrap;justify-content:flex-end;">
                                    <span class="bx-status-pill <?= (int) $u['is_active'] ? 'st-active' : 'st-blocked' ?>">
                                        <?= (int) $u['is_active'] ? ($lang === 'bn' ? 'সক্রিয়' : 'Active') : ($lang === 'bn' ? 'ব্লকড' : 'Blocked') ?>
                                    </span>
                                    <span class="bx-status-pill <?= $hasSess ? 'st-pending' : '' ?>" style="<?= $hasSess ? '' : 'background:var(--bg-alt);color:var(--text-muted);' ?>">
                                        <?= $hasSess ? ($lang === 'bn' ? 'সেশন চালু' : 'Active Session') : ($lang === 'bn' ? 'নিষ্ক্রিয়' : 'Offline') ?>
                                    </span>
                                    <?php if ($sus > 0): ?>
                                        <span class="bx-status-pill st-blocked"><?= $sus ?> alert(s)</span>
                                    <?php endif; ?>
                                </div>
                            </td>
                            <td data-label="<?= e($lang === 'bn' ? 'পাঠ ও বই' : 'Stats') ?>"><?= (int) $u['lib_count'] ?> <?= $lang === 'bn' ? 'বই' : 'books' ?> · <?= (int) $u['read_count'] ?> <?= $lang === 'bn' ? 'বার' : 'reads' ?> · ♥ <?= (int) ($u['wish_count'] ?? 0) ?></td>
                            <td class="bx-td-actions">
                                <a href="<?= e(url('/admin/users?activity_user=' . $uid . ($q !== '' ? '&q=' . urlencode($q) : '') . '#user-activity')) ?>" class="bx-btn bx-btn-outline" style="padding:8px 12px;font-size:0.84rem;">
                                    <?= $lang === 'bn' ? 'কার্যক্রম' : 'Activity' ?>
                                </a>
                                <div class="bx-kebab-wrap">
                                    <button type="button" class="bx-kebab-btn" data-kebab-toggle aria-label="User Actions">⋮</button>
                                    <div class="bx-kebab-menu">
                                        <?php if ($hasSess): ?>
                                            <form method="post" action="<?= e(url('/admin/users')) ?>" data-confirm-msg="<?= e($lang === 'bn' ? 'এই ব্যবহারকারীর চলমান রিডার সেশন বন্ধ করবেন?' : 'Force logout active session for this user?') ?>">
                                                <?= csrf_field() ?>
                                                <input type="hidden" name="action" value="force_logout_user">
                                                <input type="hidden" name="user_id" value="<?= $uid ?>">
                                                <input type="hidden" name="_return" value="<?= e($_SERVER['REQUEST_URI'] ?? '/admin/users') ?>">
                                                <button type="submit" class="bx-btn bx-btn-ghost"><?= $lang === 'bn' ? 'ফোর্স লগআউট' : 'Force Logout' ?></button>
                                            </form>
                                        <?php endif; ?>
                                        <form method="post" action="<?= e(url('/admin/users')) ?>" <?= (int) $u['is_active'] ? 'data-confirm-msg="' . e($lang === 'bn' ? 'এই ব্যবহারকারীকে ব্লক করবেন?' : 'Block this user account?') . '"' : '' ?>>
                                            <?= csrf_field() ?>
                                            <input type="hidden" name="action" value="toggle_user_block">
                                            <input type="hidden" name="user_id" value="<?= $uid ?>">
                                            <input type="hidden" name="_return" value="<?= e($_SERVER['REQUEST_URI'] ?? '/admin/users') ?>">
                                            <button type="submit" class="bx-btn bx-btn-ghost" style="color:<?= (int) $u['is_active'] ? 'var(--rose-text)' : 'var(--teal-text)' ?>;">
                                                <?= (int) $u['is_active'] ? ($lang === 'bn' ? 'ব্লক করুন' : 'Block User') : ($lang === 'bn' ? 'আনব্লক করুন' : 'Unblock User') ?>
                                            </button>
                                        </form>
                                    </div>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</section>
