<?php
declare(strict_types=1);

$lang = current_lang();
$filter = (string) ($_GET['type'] ?? $_GET['filter'] ?? 'all');

$securityLogs = db_all('SELECT * FROM security_logs ORDER BY id DESC LIMIT 80');

$benignActions = ['stream_pdf', 'open_reader', 'read_sample', 'sample_open', 'page_view'];
$readWhere = $filter === 'suspicious'
    ? "WHERE rl.action NOT IN ('stream_pdf', 'open_reader', 'read_sample', 'sample_open', 'page_view')"
    : '';

$readingLogs = db_all(
    "SELECT rl.*, u.name AS user_name, u.email AS user_email, u.is_active AS user_active, i.title_en, i.title_bn
     FROM reading_logs rl
     LEFT JOIN users u ON u.id = rl.user_id
     LEFT JOIN items i ON i.id = rl.item_id
     $readWhere
     ORDER BY rl.id DESC LIMIT 120"
);
?>

<div class="bx-mobile-only-bar">
    <button type="button" class="bx-btn bx-btn-outline" data-open-sheet="logs-filter-sheet">
        <?= $lang === 'bn' ? 'লগ ফিল্টার' : 'Filter Logs' ?> (<?= e(ucfirst($filter)) ?>)
    </button>
</div>

<div id="logs-filter-sheet" class="bx-filter-sheet-wrap" style="margin-bottom:18px;">
    <section class="bx-card bx-filter-sheet-card" style="padding:14px 18px;">
        <div class="bx-sheet-head">
            <strong style="font-family:var(--font-serif);"><?= $lang === 'bn' ? 'সিকিউরিটি লগ ফিল্টার' : 'Filter Security Logs' ?></strong>
            <button type="button" class="bx-btn bx-btn-ghost" data-close-sheet aria-label="Close" style="min-width:44px;min-height:44px;">✕</button>
        </div>
        <div style="display:flex;gap:8px;flex-wrap:wrap;">
            <a href="<?= e(url('/admin/logs')) ?>" class="bx-btn <?= $filter === 'all' ? 'bx-btn-primary' : 'bx-btn-outline' ?>"><?= $lang === 'bn' ? 'সব নিরাপত্তা লগ' : 'All Security Logs' ?></a>
            <a href="<?= e(url('/admin/logs?type=suspicious')) ?>" class="bx-btn <?= $filter === 'suspicious' ? 'bx-btn-primary' : 'bx-btn-outline' ?>"><?= $lang === 'bn' ? 'সন্দেহজনক পাঠ প্রচেষ্টা' : 'Suspicious Reading Events' ?></a>
            <a href="<?= e(url('/admin/logs?type=failed')) ?>" class="bx-btn <?= $filter === 'failed' ? 'bx-btn-primary' : 'bx-btn-outline' ?>"><?= $lang === 'bn' ? 'ব্যর্থ লগইন' : 'Failed Logins' ?></a>
        </div>
    </section>
</div>

<?php if ($filter === 'all' || $filter === 'failed'): ?>
<section class="bx-card" style="margin-bottom:22px;">
    <h2 style="margin:0 0 12px;font-size:1.08rem;font-family:var(--font-serif);">
        <?= $lang === 'bn' ? 'ব্যর্থ লগইন ও নিরাপত্তা সতর্কতা' : 'Failed Logins & Security Alerts' ?> (<?= count($securityLogs) ?>)
    </h2>
    <div class="bx-table-wrap">
        <table class="bx-table bx-mobile-cards">
            <thead>
                <tr>
                    <th><?= $lang === 'bn' ? 'ইভেন্ট' : 'Event' ?></th>
                    <th><?= $lang === 'bn' ? 'ইমেইল / ফোন' : 'Identifier' ?></th>
                    <th>IP</th>
                    <th><?= $lang === 'bn' ? 'বিস্তারিত' : 'Details' ?></th>
                    <th><?= $lang === 'bn' ? 'সময়' : 'Timestamp' ?></th>
                </tr>
            </thead>
            <tbody>
                <?php if (!$securityLogs): ?>
                    <tr class="bx-empty-row"><td colspan="5" style="color:var(--text-muted);padding:20px;"><?= $lang === 'bn' ? 'কোনো নিরাপত্তা সতর্কতা নেই।' : 'No failed logins or security alerts recorded.' ?></td></tr>
                <?php else: ?>
                    <?php foreach ($securityLogs as $sl): ?>
                        <tr>
                            <td class="bx-td-primary"><span class="bx-status-pill st-blocked"><?= e($sl['event_type']) ?></span></td>
                            <td data-label="<?= e($lang === 'bn' ? 'ব্যবহারকারী' : 'Identifier') ?>"><code><?= e((string) ($sl['identifier'] ?? '—')) ?></code></td>
                            <td data-label="IP"><code><?= e($sl['ip_address']) ?></code></td>
                            <td data-label="<?= e($lang === 'bn' ? 'বিস্তারিত' : 'Details') ?>" style="font-size:0.84rem;"><?= e((string) ($sl['details'] ?? '')) ?></td>
                            <td data-label="<?= e($lang === 'bn' ? 'সময়' : 'Time') ?>" style="font-size:0.8rem;color:var(--text-muted);"><?= e($sl['created_at']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</section>
<?php endif; ?>

<?php if ($filter !== 'failed'): ?>
<section class="bx-card">
    <h2 style="margin:0 0 12px;font-size:1.08rem;font-family:var(--font-serif);">
        <?= $lang === 'bn' ? 'রিডার অ্যাক্সেস ও পঠন লগ' : 'Reader Stream & Access Logs' ?> (<?= count($readingLogs) ?>)
    </h2>
    <div class="bx-table-wrap">
        <table class="bx-table bx-mobile-cards">
            <thead>
                <tr>
                    <th><?= $lang === 'bn' ? 'ব্যবহারকারী' : 'User' ?></th>
                    <th><?= $lang === 'bn' ? 'বই' : 'Item' ?></th>
                    <th><?= $lang === 'bn' ? 'অ্যাকশন ও বিবরণ' : 'Action & Details' ?></th>
                    <th>IP</th>
                    <th><?= $lang === 'bn' ? 'সময়' : 'When' ?></th>
                    <th><?= $lang === 'bn' ? 'নিয়ন্ত্রণ' : 'Control' ?></th>
                </tr>
            </thead>
            <tbody>
                <?php if (!$readingLogs): ?>
                    <tr class="bx-empty-row"><td colspan="6" style="color:var(--text-muted);padding:20px;"><?= $lang === 'bn' ? 'কোনো রিডিং লগ পাওয়া যায়নি।' : 'No reading events match this filter.' ?></td></tr>
                <?php else: ?>
                    <?php foreach ($readingLogs as $rl): ?>
                        <?php $isSus = !in_array((string) $rl['action'], $benignActions, true); ?>
                        <tr>
                            <td class="bx-td-primary">
                                <strong><?= e((string) ($rl['user_name'] ?? 'Guest')) ?></strong>
                                <?php if (!empty($rl['user_email'])): ?>
                                    <span class="bx-ellipsis" style="font-size:0.78rem;color:var(--text-muted);"><?= e($rl['user_email']) ?></span>
                                <?php endif; ?>
                            </td>
                            <td data-label="<?= e($lang === 'bn' ? 'বই' : 'Item') ?>">
                                <span><?= e($lang === 'bn' ? ($rl['title_bn'] ?: $rl['title_en'] ?: '-') : ($rl['title_en'] ?: '-')) ?>
                                <?php if (!empty($rl['is_sample'])): ?><span class="bx-badge">Sample</span><?php endif; ?></span>
                            </td>
                            <td data-label="<?= e($lang === 'bn' ? 'ইভেন্ট' : 'Action') ?>">
                                <div>
                                    <span class="bx-status-pill <?= $isSus ? 'st-blocked' : 'st-active' ?>"><?= e($rl['action']) ?></span>
                                    <?php if (!empty($rl['details'])): ?>
                                        <div style="font-size:0.78rem;color:var(--text-muted);margin-top:2px;"><?= e($rl['details']) ?></div>
                                    <?php endif; ?>
                                </div>
                            </td>
                            <td data-label="IP"><code><?= e($rl['ip_address']) ?></code></td>
                            <td data-label="<?= e($lang === 'bn' ? 'সময়' : 'When') ?>" style="font-size:0.8rem;color:var(--text-muted);"><?= e($rl['created_at']) ?></td>
                            <td class="bx-td-actions">
                                <?php if (!empty($rl['user_id'])): ?>
                                    <form method="post" action="<?= e(url('/admin/users')) ?>" <?= !empty($rl['user_active']) ? 'data-confirm-msg="' . e($lang === 'bn' ? 'এই ব্যবহারকারীকে ব্লক করবেন?' : 'Block this user?') . '"' : '' ?>>
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="action" value="toggle_user_block">
                                        <input type="hidden" name="user_id" value="<?= (int) $rl['user_id'] ?>">
                                        <input type="hidden" name="return_to" value="logs">
                                        <button type="submit" class="bx-btn bx-btn-outline" style="padding:8px 12px;font-size:0.82rem;color:<?= !empty($rl['user_active']) ? 'var(--rose)' : 'var(--teal)' ?>;">
                                            <?= !empty($rl['user_active']) ? ($lang === 'bn' ? 'ব্লক' : 'Block') : ($lang === 'bn' ? 'আনব্লক' : 'Unblock') ?>
                                        </button>
                                    </form>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</section>
<?php endif; ?>
