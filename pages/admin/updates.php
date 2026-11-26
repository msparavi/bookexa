<?php
declare(strict_types=1);

$lang = current_lang();
$status = migration_list_status();
$items = $status['items'];
$pendingCount = (int) $status['pending_count'];

require_once APP_ROOT . '/includes/maintenance.php';
$backups = list_daily_backups();
$latestBackup = $backups[0] ?? null;
$dbVer = (string) (db_one('SELECT VERSION() AS v')['v'] ?? 'MySQL / MariaDB');
?>

<!-- About Bookexa & Safe Backup Card -->
<div class="bx-grid-2" style="align-items:start;margin-bottom:18px;">
    <section class="bx-card">
        <span class="bx-badge bx-badge-teal" style="margin-bottom:8px;">Bookexa v<?= e(bookexa_version()) ?></span>
        <h2 style="margin:0 0 6px;font-family:var(--font-serif);font-size:1.25rem;">
            <?= $lang === 'bn' ? 'Bookexa সম্পর্কে ও সিস্টেম ভার্সন' : 'About Bookexa & Version Info' ?>
        </h2>
        <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(160px,1fr));gap:10px;margin-top:12px;">
            <div style="padding:10px 12px;border-radius:10px;background:var(--bg-alt);border:1px solid var(--border);">
                <small class="bx-muted">Software Version</small>
                <strong style="display:block;font-size:1.05rem;">v<?= e(bookexa_version()) ?></strong>
            </div>
            <div style="padding:10px 12px;border-radius:10px;background:var(--bg-alt);border:1px solid var(--border);">
                <small class="bx-muted">PHP Runtime</small>
                <strong style="display:block;font-size:1.05rem;">PHP <?= e(PHP_VERSION) ?></strong>
            </div>
            <div style="padding:10px 12px;border-radius:10px;background:var(--bg-alt);border:1px solid var(--border);">
                <small class="bx-muted">Database Engine</small>
                <strong style="display:block;font-size:0.95rem;overflow:hidden;text-overflow:ellipsis;"><?= e($dbVer) ?></strong>
            </div>
            <div style="padding:10px 12px;border-radius:10px;background:var(--bg-alt);border:1px solid var(--border);">
                <small class="bx-muted">Operating Mode</small>
                <strong style="display:block;font-size:1.05rem;"><?= is_demo_mode() ? 'Demo Mode' : e(ucfirst((string) app_config('app_env', 'production'))) ?></strong>
            </div>
        </div>
    </section>

    <section class="bx-card">
        <h2 style="margin:0 0 6px;font-family:var(--font-serif);font-size:1.18rem;">
            <?= $lang === 'bn' ? 'আপডেটের আগে নিরাপদ ব্যাকআপ' : 'Safe Backup Before Updating' ?>
        </h2>
        <p class="bx-muted" style="margin:0 0 12px;font-size:0.88rem;">
            <?= $lang === 'bn'
                ? 'কোনো স্কিমা বা ফাইল আপডেট চালানোর আগে সম্পূর্ণ ডাটাবেজের .sql স্ন্যাপশট সংরক্ষণ করুন।'
                : 'Create a timestamped SQL snapshot in protected storage/backups/ before running database migrations or uploading new release files.' ?>
        </p>
        <?php if ($latestBackup): ?>
            <div style="font-size:0.84rem;margin-bottom:12px;padding:8px 10px;border-radius:10px;background:var(--bg-alt);border:1px solid var(--border);">
                <strong><?= $lang === 'bn' ? 'সর্বশেষ ব্যাকআপ:' : 'Latest Backup:' ?></strong>
                <code><?= e($latestBackup['name']) ?></code> (<?= number_format($latestBackup['size'] / 1024, 1) ?> KB)
            </div>
        <?php endif; ?>
        <div style="display:flex;gap:10px;flex-wrap:wrap;align-items:center;">
            <form method="post" action="<?= e(url('/admin/updates')) ?>" style="margin:0;">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="backup_before_update">
                <button type="submit" class="bx-btn bx-btn-outline">
                    <?= $lang === 'bn' ? 'এখনই নিরাপদ ব্যাকআপ নিন' : 'Create Backup Before Update' ?>
                </button>
            </form>
            <?php if (admin_can($admin, 'system-health')): ?>
                <a href="<?= e(url('/admin/system-health')) ?>" class="bx-btn bx-btn-ghost bx-btn-sm">
                    <?= $lang === 'bn' ? 'ব্যাকআপ ডাউনলোড / হেলথ →' : 'Manage Backups →' ?>
                </a>
            <?php endif; ?>
        </div>
    </section>
</div>

<div class="bx-card" style="margin-bottom:18px;">
    <div style="display:flex;justify-content:space-between;align-items:center;gap:12px;flex-wrap:wrap;">
        <div>
            <h2 style="margin:0 0 4px;font-family:var(--font-serif);font-size:1.25rem;">
                <?= $lang === 'bn' ? 'ডাটাবেজ আপডেট ও মাইগ্রেশন' : 'Database Schema Migrations' ?>
            </h2>
            <p style="margin:0;color:var(--text-muted);font-size:0.9rem;">
                <?= $lang === 'bn'
                    ? 'সব স্কিমা পরিবর্তন idempotent এবং আগেই থাকা কলাম/ইনডেক্সে কখনো থামবে না।'
                    : 'All migrations verify information_schema before altering tables, columns, or indexes so upgrades are safely repeatable.' ?>
            </p>
        </div>
        <div style="display:flex;align-items:center;gap:10px;flex-wrap:wrap;">
            <?php if ($pendingCount > 0): ?>
                <span class="bx-status-pill st-pending">
                    <?= $lang === 'bn' ? "বাকি আপডেট: {$pendingCount}টি" : "Pending: {$pendingCount}" ?>
                </span>
                <form method="post" action="<?= e(url('/admin/updates')) ?>" style="margin:0;">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="run_all_migrations">
                    <button type="submit" class="bx-btn bx-btn-primary">
                        <?= $lang === 'bn' ? 'সব বাকি আপডেট চালান' : 'Run All Pending Updates' ?>
                    </button>
                </form>
            <?php else: ?>
                <span class="bx-status-pill st-active">
                    <?= $lang === 'bn' ? 'ডাটাবেজ সম্পূর্ণ হালনাগাদ' : 'Up to date (0 pending)' ?>
                </span>
            <?php endif; ?>
        </div>
    </div>
</div>

<div class="bx-card">
    <div class="bx-table-wrap">
        <table class="bx-table bx-mobile-cards">
            <thead>
                <tr>
                    <th><?= $lang === 'bn' ? 'ভার্সন' : 'Migration Version' ?></th>
                    <th><?= $lang === 'bn' ? 'বিবরণ' : 'Description' ?></th>
                    <th><?= $lang === 'bn' ? 'অবস্থা' : 'Status' ?></th>
                    <th><?= $lang === 'bn' ? 'প্রয়োগের সময়' : 'Applied At' ?></th>
                    <th style="text-align:right;"><?= $lang === 'bn' ? 'অ্যাকশন' : 'Action' ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($items as $mig): ?>
                    <tr>
                        <td data-label="Version"><code><?= e($mig['version']) ?></code></td>
                        <td data-label="Description"><?= e($mig['description']) ?></td>
                        <td data-label="Status">
                            <?php if ($mig['applied']): ?>
                                <span class="bx-status-pill st-active"><?= $lang === 'bn' ? 'সম্পন্ন' : 'Applied' ?></span>
                            <?php else: ?>
                                <span class="bx-status-pill st-pending"><?= $lang === 'bn' ? 'অপেক্ষমাণ' : 'Pending' ?></span>
                            <?php endif; ?>
                        </td>
                        <td data-label="Applied At">
                            <?= $mig['applied_at'] ? e(date('d M Y, H:i', strtotime((string) $mig['applied_at']))) : '—' ?>
                        </td>
                        <td data-label="Action" style="text-align:right;">
                            <?php if (!$mig['applied']): ?>
                                <form method="post" action="<?= e(url('/admin/updates')) ?>" style="display:inline;">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="action" value="run_migration">
                                    <input type="hidden" name="version" value="<?= e($mig['version']) ?>">
                                    <button type="submit" class="bx-btn bx-btn-primary bx-btn-sm">
                                        <?= $lang === 'bn' ? 'চালান' : 'Run Update' ?>
                                    </button>
                                </form>
                            <?php else: ?>
                                <form method="post" action="<?= e(url('/admin/updates')) ?>" style="display:inline;" data-confirm-msg="<?= e($lang === 'bn' ? 'এই idempotent মাইগ্রেশনটি পুনরায় যাচাই/চালু করবেন?' : 'Re-verify this idempotent migration?') ?>">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="action" value="run_migration">
                                    <input type="hidden" name="version" value="<?= e($mig['version']) ?>">
                                    <button type="submit" class="bx-btn bx-btn-ghost bx-btn-sm">
                                        <?= $lang === 'bn' ? 'পুনঃযাচাই' : 'Re-verify' ?>
                                    </button>
                                </form>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
