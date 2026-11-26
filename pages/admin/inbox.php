<?php
declare(strict_types=1);

$lang    = current_lang();
$status  = (string) ($_GET['status'] ?? 'all');
$q       = trim((string) ($_GET['q'] ?? ''));
$page    = max(1, (int) ($_GET['page'] ?? 1));
$perPage = 25;

if (!in_array($status, ['all', 'new', 'read', 'replied'], true)) {
    $status = 'all';
}

$where = [];
$params = [];
if ($status !== 'all') {
    $where[] = 'cm.status = ?';
    $params[] = $status;
}
if ($q !== '') {
    $like = '%' . escape_like($q) . '%';
    $where[] = "(cm.name LIKE ? ESCAPE '\\\\' OR cm.email LIKE ? ESCAPE '\\\\' OR COALESCE(cm.phone,\"\") LIKE ? ESCAPE '\\\\' OR cm.subject LIKE ? ESCAPE '\\\\' OR cm.message LIKE ? ESCAPE '\\\\')";
    array_push($params, $like, $like, $like, $like, $like);
}
$whereSql = $where ? (' WHERE ' . implode(' AND ', $where)) : '';

$totalRows = (int) (db_one("SELECT COUNT(*) AS c FROM contact_messages cm{$whereSql}", $params)['c'] ?? 0);
$totalPages = max(1, (int) ceil($totalRows / $perPage));
if ($page > $totalPages) {
    $page = $totalPages;
}
$offset = ($page - 1) * $perPage;

$messages = db_all(
    "SELECT cm.*, a.name AS replied_admin_name
     FROM contact_messages cm
     LEFT JOIN admins a ON a.id = cm.replied_by
     {$whereSql}
     ORDER BY (cm.status = 'new') DESC, cm.id DESC
     LIMIT {$perPage} OFFSET {$offset}",
    $params
);

$counts = ['all' => 0, 'new' => 0, 'read' => 0, 'replied' => 0];
foreach (db_all('SELECT status, COUNT(*) AS c FROM contact_messages GROUP BY status') as $cr) {
    $counts[(string) $cr['status']] = (int) $cr['c'];
    $counts['all'] += (int) $cr['c'];
}

$buildInboxUrl = static function (array $ov = []) use ($status, $q, $page): string {
    $arr = array_merge(['status' => $status, 'q' => $q, 'page' => $page > 1 ? (string) $page : ''], $ov);
    if (($arr['status'] ?? '') === 'all') $arr['status'] = '';
    if (($arr['page'] ?? '') === '1') $arr['page'] = '';
    $clean = array_filter($arr, static fn($v): bool => (string) $v !== '');
    return url('/admin/inbox' . ($clean ? ('?' . http_build_query($clean)) : ''));
};
$currentUrl = $buildInboxUrl();
?>
<section class="bx-card" style="margin-bottom:18px;">
    <div style="display:flex;gap:8px;flex-wrap:wrap;margin-bottom:12px;">
        <?php foreach (['all' => 'All', 'new' => 'New', 'read' => 'Read', 'replied' => 'Replied'] as $stKey => $stLbl): ?>
            <a href="<?= e($buildInboxUrl(['status' => $stKey, 'page' => '1'])) ?>" class="bx-btn <?= $status === $stKey ? 'bx-btn-primary' : 'bx-btn-outline' ?> bx-btn-sm">
                <?= e($stLbl) ?> (<?= (int) ($counts[$stKey] ?? 0) ?>)
            </a>
        <?php endforeach; ?>
    </div>
    <form method="get" action="<?= e(url('/admin/inbox')) ?>" style="display:flex;gap:8px;flex-wrap:wrap;">
        <?php if ($status !== 'all'): ?><input type="hidden" name="status" value="<?= e($status) ?>"><?php endif; ?>
        <input type="search" name="q" value="<?= e($q) ?>" class="bx-input" style="flex:1 1 240px;min-width:0;" placeholder="<?= e($lang === 'bn' ? 'প্রেরক, ইমেইল, বিষয় বা বার্তা খুঁজুন...' : 'Search sender, email, subject, or message...') ?>">
        <button type="submit" class="bx-btn bx-btn-primary"><?= $lang === 'bn' ? 'খুঁজুন' : 'Search' ?></button>
        <?php if ($q !== '' || $status !== 'all'): ?>
            <a href="<?= e(url('/admin/inbox')) ?>" class="bx-btn bx-btn-ghost"><?= $lang === 'bn' ? 'রিসেট' : 'Reset' ?></a>
        <?php endif; ?>
    </form>
</section>

<section class="bx-card">
    <h2 style="margin:0 0 14px;font-size:1.12rem;font-family:var(--font-serif);">
        <?= $lang === 'bn' ? 'সাপোর্ট ইনবক্স' : 'Support Inbox' ?> (<?= $totalRows ?>)
    </h2>

    <?php if (!$messages): ?>
        <p style="color:var(--text-muted);"><?= $lang === 'bn' ? 'কোনো বার্তা পাওয়া যায়নি।' : 'No messages match your filter.' ?></p>
    <?php else: ?>
        <div style="display:flex;flex-direction:column;gap:14px;">
            <?php foreach ($messages as $m): ?>
                <?php $st = (string) $m['status']; $mid = (int) $m['id']; ?>
                <article style="padding:16px;border-radius:14px;border:1px solid var(--border);background:var(--bg-alt);">
                    <div style="display:flex;justify-content:space-between;align-items:flex-start;gap:10px;flex-wrap:wrap;margin-bottom:8px;">
                        <div>
                            <strong style="font-size:1.02rem;"><?= e((string) ($m['subject'] ?: '(No Subject)')) ?></strong>
                            <div style="font-size:0.84rem;color:var(--text-muted);margin-top:2px;">
                                <?= e((string) $m['name']) ?> &lt;<?= e((string) $m['email']) ?>&gt;
                                <?= !empty($m['phone']) ? ' · ' . e((string) $m['phone']) : '' ?>
                                · <?= e(date('d M Y, H:i', strtotime((string) $m['created_at']))) ?>
                            </div>
                        </div>
                        <span class="bx-status-pill <?= $st === 'replied' ? 'st-approved' : ($st === 'read' ? 'st-active' : 'st-pending') ?>">
                            <?= e(ucfirst($st)) ?>
                        </span>
                    </div>

                    <p style="margin:0 0 12px;white-space:pre-line;line-height:1.6;"><?= e((string) $m['message']) ?></p>

                    <?php if (!empty($m['reply_message'])): ?>
                        <div style="padding:10px 12px;border-radius:10px;background:var(--surface);border-left:4px solid var(--teal);margin-bottom:12px;">
                            <small style="display:block;color:var(--teal-text);font-weight:700;margin-bottom:4px;">
                                <?= $lang === 'bn' ? 'উত্তর পাঠানো হয়েছে' : 'Reply Sent' ?>
                                <?= !empty($m['replied_admin_name']) ? ' by ' . e((string) $m['replied_admin_name']) : '' ?>
                                <?= !empty($m['replied_at']) ? ' (' . e(date('d M Y, H:i', strtotime((string) $m['replied_at']))) . ')' : '' ?>
                            </small>
                            <div style="font-size:0.88rem;white-space:pre-line;"><?= e((string) $m['reply_message']) ?></div>
                        </div>
                    <?php endif; ?>

                    <div style="display:flex;gap:8px;flex-wrap:wrap;align-items:center;">
                        <?php if ($st === 'new'): ?>
                            <form method="post" action="<?= e(url('/admin/inbox')) ?>" style="margin:0;">
                                <?= csrf_field() ?>
                                <input type="hidden" name="action" value="contact_status">
                                <input type="hidden" name="contact_id" value="<?= $mid ?>">
                                <input type="hidden" name="status" value="read">
                                <input type="hidden" name="_return" value="<?= e($currentUrl) ?>">
                                <button type="submit" class="bx-btn bx-btn-outline bx-btn-sm"><?= $lang === 'bn' ? 'পঠিত চিহ্নিত করুন' : 'Mark Read' ?></button>
                            </form>
                        <?php endif; ?>
                        <form method="post" action="<?= e(url('/admin/inbox')) ?>" style="margin:0;" data-confirm-msg="<?= e($lang === 'bn' ? 'বার্তাটি মুছে ফেলবেন?' : 'Delete this support message?') ?>">
                            <?= csrf_field() ?>
                            <input type="hidden" name="action" value="inbox_delete">
                            <input type="hidden" name="contact_id" value="<?= $mid ?>">
                            <input type="hidden" name="_return" value="<?= e($currentUrl) ?>">
                            <button type="submit" class="bx-btn bx-btn-ghost bx-btn-sm" style="color:var(--rose-text);"><?= $lang === 'bn' ? 'মুছুন' : 'Delete' ?></button>
                        </form>
                    </div>

                    <details style="margin-top:10px;border-top:1px solid var(--border);padding-top:10px;">
                        <summary style="cursor:pointer;font-weight:700;color:var(--primary);min-height:40px;display:flex;align-items:center;">
                            ✉ <?= $lang === 'bn' ? 'ইমেইলে উত্তর পাঠান (SMTP)' : 'Reply via Email (SMTP)' ?>
                        </summary>
                        <form method="post" action="<?= e(url('/admin/inbox')) ?>" style="margin-top:10px;">
                            <?= csrf_field() ?>
                            <input type="hidden" name="action" value="inbox_reply">
                            <input type="hidden" name="contact_id" value="<?= $mid ?>">
                            <input type="hidden" name="_return" value="<?= e($currentUrl) ?>">
                            <div class="bx-field">
                                <label><?= $lang === 'bn' ? 'বিষয়' : 'Subject' ?></label>
                                <input type="text" name="reply_subject" class="bx-input" required value="<?= e('Re: ' . (string) ($m['subject'] ?: 'Your Bookexa Support Request')) ?>">
                            </div>
                            <div class="bx-field">
                                <label><?= $lang === 'bn' ? 'উত্তর বার্তা *' : 'Reply Message *' ?></label>
                                <textarea name="reply_message" class="bx-input" rows="3" required placeholder="<?= e($lang === 'bn' ? 'এখানে আপনার উত্তর লিখুন...' : 'Write your response...') ?>"></textarea>
                            </div>
                            <button type="submit" class="bx-btn bx-btn-primary bx-btn-sm"><?= $lang === 'bn' ? 'উত্তর পাঠান ও সংরক্ষণ করুন' : 'Send Email & Save Reply' ?></button>
                        </form>
                    </details>
                </article>
            <?php endforeach; ?>
        </div>

        <?php if ($totalPages > 1): ?>
            <nav class="bx-pagination" style="margin-top:16px;">
                <?php if ($page > 1): ?><a href="<?= e($buildInboxUrl(['page' => (string) ($page - 1)])) ?>" class="bx-btn bx-btn-outline bx-btn-sm">« Prev</a><?php endif; ?>
                <span style="font-size:0.86rem;color:var(--text-muted);">Page <?= $page ?> of <?= $totalPages ?></span>
                <?php if ($page < $totalPages): ?><a href="<?= e($buildInboxUrl(['page' => (string) ($page + 1)])) ?>" class="bx-btn bx-btn-outline bx-btn-sm">Next »</a><?php endif; ?>
            </nav>
        <?php endif; ?>
    <?php endif; ?>
</section>
