<?php
declare(strict_types=1);

$lang = current_lang();
$cdn = cdn_config();

// Trigger immediate scan so UI reflects newly dropped or existing uploads right away
media_scan_local_uploads();

$rows = db_all('SELECT * FROM media_sync ORDER BY cdn_synced ASC, id DESC LIMIT 200');
$summaryRow = db_one(
    "SELECT COUNT(*) AS total,
            SUM(CASE WHEN cdn_synced = 1 THEN 1 ELSE 0 END) AS synced,
            SUM(CASE WHEN local_exists = 1 AND cdn_synced = 0 THEN 1 ELSE 0 END) AS pending,
            SUM(CASE WHEN cdn_synced = 0 AND last_error IS NOT NULL THEN 1 ELSE 0 END) AS failed,
            COALESCE(SUM(size), 0) AS total_bytes
     FROM media_sync"
);
$totImgs   = (int) ($summaryRow['total'] ?? 0);
$syncedCnt = (int) ($summaryRow['synced'] ?? 0);
$pendCnt   = (int) ($summaryRow['pending'] ?? 0);
$failCnt   = (int) ($summaryRow['failed'] ?? 0);
$totBytes  = (int) ($summaryRow['total_bytes'] ?? 0);
$fmtBytes  = $totBytes >= 1048576
    ? number_format($totBytes / 1048576, 2) . ' MB'
    : number_format(max(1, $totBytes) / 1024, 1) . ' KB';
?>

<div class="bx-admin-stats" style="margin-bottom:20px;">
    <div class="bx-stat-card">
        <div class="bx-stat-label"><?= $lang === 'bn' ? 'মোট ছবি' : 'Total Images' ?></div>
        <div class="bx-stat-num" id="cdn-stat-total"><?= $totImgs ?></div>
    </div>
    <div class="bx-stat-card">
        <div class="bx-stat-label"><?= $lang === 'bn' ? 'CDN সিঙ্ক সম্পন্ন' : 'Synced to CDN' ?></div>
        <div class="bx-stat-num" id="cdn-stat-synced" style="color:var(--teal-text);"><?= $syncedCnt ?></div>
    </div>
    <div class="bx-stat-card">
        <div class="bx-stat-label"><?= $lang === 'bn' ? 'অপেক্ষমাণ (Pending)' : 'Pending Sync' ?></div>
        <div class="bx-stat-num" id="cdn-stat-pending" style="color:var(--gold-text);"><?= $pendCnt ?></div>
    </div>
    <div class="bx-stat-card">
        <div class="bx-stat-label"><?= $lang === 'bn' ? 'ব্যর্থ (Failed)' : 'Failed' ?></div>
        <div class="bx-stat-num" id="cdn-stat-failed" style="color:var(--rose-text);"><?= $failCnt ?></div>
    </div>
    <div class="bx-stat-card">
        <div class="bx-stat-label"><?= $lang === 'bn' ? 'মোট আকার' : 'Total Media Size' ?></div>
        <div class="bx-stat-num" style="font-size:clamp(1.1rem,2.2vw,1.45rem);"><?= e($fmtBytes) ?></div>
    </div>
    <div class="bx-stat-card">
        <div class="bx-stat-label"><?= $lang === 'bn' ? 'CDN স্ট্যাটাস / ড্রাইভার' : 'CDN Driver Status' ?></div>
        <div style="margin-top:8px;display:flex;gap:6px;flex-wrap:wrap;align-items:center;">
            <span class="bx-status-pill <?= $cdn['enabled'] ? 'st-active' : 'st-inactive' ?>">
                <?= $cdn['enabled'] ? 'ENABLED' : 'DISABLED' ?>
            </span>
            <span class="bx-badge"><?= e(strtoupper($cdn['driver'])) ?></span>
        </div>
    </div>
</div>

<!-- Controls & Live Batch Sync Progress -->
<section class="bx-card" style="margin-bottom:20px;">
    <div style="display:flex;justify-content:space-between;align-items:center;gap:10px;flex-wrap:wrap;margin-bottom:12px;">
        <div>
            <h2 style="margin:0;font-size:1.12rem;font-family:var(--font-serif);"><?= $lang === 'bn' ? 'মিডিয়া ও স্বয়ংক্রিয় CDN সিঙ্ক' : 'Media & Automatic CDN Sync' ?></h2>
            <small style="color:var(--text-muted);">
                <?= $cdn['base_url'] !== '' ? ('Base URL: ' . e($cdn['base_url'])) : ($lang === 'bn' ? 'config/config.php থেকে cdn.enabled=true ও base_url সেট করুন।' : 'Configure cdn.enabled, driver, path & base_url inside config/config.php.') ?>
            </small>
        </div>
    </div>

    <div style="display:flex;gap:10px;flex-wrap:wrap;margin-bottom:14px;">
        <button type="button" id="btn-cdn-test" class="bx-btn bx-btn-outline" data-endpoint="<?= e(url('/admin/api/cdn-test')) ?>">
            <?= $lang === 'bn' ? 'CDN সংযোগ পরীক্ষা' : 'Test CDN connection' ?>
        </button>
        <button type="button" id="btn-cdn-sync-all" class="bx-btn bx-btn-primary" data-endpoint="<?= e(url('/admin/api/cdn-sync-batch')) ?>">
            <?= $lang === 'bn' ? 'সব পেন্ডিং এখনই সিঙ্ক করুন' : 'Sync all pending now' ?>
        </button>
        <button type="button" id="btn-cdn-retry-failed" class="bx-btn bx-btn-outline" data-endpoint="<?= e(url('/admin/api/cdn-sync-batch')) ?>">
            <?= $lang === 'bn' ? 'ব্যর্থগুলো পুনরায় চেষ্টা' : 'Retry failed' ?>
        </button>
        <button type="button" id="btn-cdn-recheck" class="bx-btn bx-btn-outline" data-endpoint="<?= e(url('/admin/api/cdn-recheck')) ?>">
            <?= $lang === 'bn' ? 'CDN ফাইল পুনরায় যাচাই' : 'Re-check files on CDN' ?>
        </button>
        <button type="button" id="btn-cdn-pull" class="bx-btn bx-btn-ghost" data-endpoint="<?= e(url('/admin/api/cdn-pull')) ?>">
            <?= $lang === 'bn' ? 'CDN থেকে লোকাল /uploads-এ আনুন' : 'Pull everything back from CDN to local /uploads' ?>
        </button>
    </div>

    <div style="background:var(--bg);border:1px solid var(--border);border-radius:14px;padding:12px 14px;">
        <div style="display:flex;justify-content:space-between;align-items:center;gap:8px;margin-bottom:6px;flex-wrap:wrap;">
            <strong id="cdn-sync-status" style="font-size:0.9rem;"><?= $lang === 'bn' ? 'প্রস্তুত' : 'Ready' ?></strong>
            <span id="cdn-sync-pct" style="font-weight:700;color:var(--primary);"><?= $totImgs > 0 ? round(($syncedCnt / $totImgs) * 100) : 0 ?>%</span>
        </div>
        <div style="width:100%;height:10px;border-radius:999px;background:var(--bg-alt);overflow:hidden;border:1px solid var(--border);">
            <div id="cdn-sync-progress-fill" style="width:<?= $totImgs > 0 ? round(($syncedCnt / $totImgs) * 100) : 0 ?>%;height:100%;background:var(--grad-indigo-teal);transition:width 0.25s ease;"></div>
        </div>
    </div>

    <div style="margin-top:14px;padding:12px 14px;border-radius:14px;background:var(--bg-alt);border:1px solid var(--border);display:flex;gap:12px;align-items:center;justify-content:space-between;flex-wrap:wrap;">
        <div style="flex:1 1 280px;min-width:0;">
            <strong style="display:block;font-size:0.86rem;margin-bottom:4px;"><?= $lang === 'bn' ? 'সক্রিয় ক্রন সিঙ্ক URL (রিজেনারেটযোগ্য)' : 'Active Cron Sync URL (Rotatable Secret)' ?></strong>
            <input type="text" readonly class="bx-input" style="font-family:monospace;font-size:0.8rem;" value="<?= e(url('/cron/sync_media.php?key=' . cron_sync_token())) ?>">
        </div>
        <form method="post" action="<?= e(url('/admin/media')) ?>" style="margin:0;">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="regenerate_cron_key">
            <input type="hidden" name="_return" value="/admin/media">
            <button type="submit" class="bx-btn bx-btn-outline" data-confirm="<?= e($lang === 'bn' ? 'নতুন ক্রন কী তৈরি করলে পুরনো ক্রন URL অকার্যকর হবে। চালিয়ে যাবেন?' : 'Regenerating the cron key invalidates the old key immediately. Continue?') ?>">
                <?= $lang === 'bn' ? 'ক্রন কী রিজেনারেট' : 'Regenerate cron key' ?>
            </button>
        </form>
    </div>
</section>

<!-- Site Portability Package Export -->
<?php if (($admin['role'] ?? '') === 'superadmin'): ?>
<section class="bx-card" style="margin-bottom:20px;">
    <div style="display:flex;justify-content:space-between;align-items:center;gap:14px;flex-wrap:wrap;">
        <div>
            <h2 style="margin:0 0 4px;font-size:1.1rem;font-family:var(--font-serif);"><?= $lang === 'bn' ? 'পোর্টেবল সাইট প্যাকেজ এক্সপোর্ট (.zip)' : 'Export Portable Site Package (.zip)' ?></h2>
            <small style="color:var(--text-muted);">
                <?= $lang === 'bn'
                    ? 'কোড, নতুন database/bookexa.sql এবং সম্পূর্ণ /uploads ফোল্ডারসহ ZIP ডাউনলোড করুন (config.php বাদ দিয়ে config.sample.php ও protected storage/ ছাড়া)।'
                    : 'Downloads full codebase + database/bookexa.sql + /uploads with config.sample.php (CDN disabled), excluding storage/, logs, and secrets.' ?>
            </small>
        </div>
        <div style="display:flex;gap:10px;flex-wrap:wrap;">
            <form method="post" action="<?= e(url('/admin/media')) ?>" style="margin:0;">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="export_site_package">
                <input type="hidden" name="package_mode" value="clean">
                <button type="submit" class="bx-btn bx-btn-primary"><?= $lang === 'bn' ? 'ক্লিন প্যাকেজ এক্সপোর্ট' : 'Export site package (Clean)' ?></button>
            </form>
            <form method="post" action="<?= e(url('/admin/media')) ?>" style="margin:0;">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="export_site_package">
                <input type="hidden" name="package_mode" value="snapshot">
                <button type="submit" class="bx-btn bx-btn-outline"><?= $lang === 'bn' ? 'পূর্ণ ডেটাসহ এক্সপোর্ট' : 'Export site package (Snapshot)' ?></button>
            </form>
        </div>
    </div>
</section>
<?php endif; ?>

<!-- Tracked Files Table -->
<section class="bx-card">
    <h2 style="margin:0 0 14px;font-size:1.1rem;font-family:var(--font-serif);"><?= $lang === 'bn' ? 'নিবন্ধিত মিডিয়া তালিকা' : 'Tracked Public Media Files' ?> (<?= count($rows) ?>)</h2>
    <div class="bx-table-wrap">
        <table class="bx-table bx-mobile-cards">
            <thead>
                <tr>
                    <th><?= $lang === 'bn' ? 'ফাইল' : 'Media File' ?></th>
                    <th><?= $lang === 'bn' ? 'আকার' : 'Size' ?></th>
                    <th><?= $lang === 'bn' ? 'লোকাল' : 'Local' ?></th>
                    <th><?= $lang === 'bn' ? 'CDN সিঙ্ক' : 'CDN Sync' ?></th>
                    <th><?= $lang === 'bn' ? 'তথ্য / ত্রুটি' : 'Attempts / Error' ?></th>
                    <th><?= $lang === 'bn' ? 'অ্যাকশন' : 'Action' ?></th>
                </tr>
            </thead>
            <tbody>
                <?php if (!$rows): ?>
                    <tr class="bx-empty-row"><td colspan="6" style="color:var(--text-muted);padding:24px;"><?= $lang === 'bn' ? 'এখনও কোনো ছবি নিবন্ধিত হয়নি।' : 'No public media files registered yet.' ?></td></tr>
                <?php else: foreach ($rows as $m): ?>
                    <?php $isSynced = (int) $m['cdn_synced'] === 1; $hasLocal = (int) $m['local_exists'] === 1; ?>
                    <tr>
                        <td class="bx-td-primary">
                            <div style="display:flex;align-items:center;gap:10px;min-width:0;">
                                <img src="<?= e(asset((string) $m['rel_path'])) ?>" alt="" class="bx-thumb-preview" style="width:44px;height:44px;flex-shrink:0;">
                                <code class="bx-ellipsis" style="font-size:0.8rem;"><?= e($m['rel_path']) ?></code>
                            </div>
                        </td>
                        <td data-label="<?= e($lang === 'bn' ? 'আকার' : 'Size') ?>"><?= number_format(((int) $m['size']) / 1024, 1) ?> KB</td>
                        <td data-label="<?= e($lang === 'bn' ? 'লোকাল' : 'Local') ?>">
                            <span class="bx-status-pill <?= $hasLocal ? 'st-active' : 'st-blocked' ?>"><?= $hasLocal ? 'OK' : 'Missing' ?></span>
                        </td>
                        <td data-label="CDN">
                            <span class="bx-status-pill <?= $isSynced ? 'st-approved' : ($m['last_error'] ? 'st-rejected' : 'st-pending') ?>">
                                <?= $isSynced ? 'Synced' : ($m['last_error'] ? 'Failed' : 'Pending') ?>
                            </span>
                        </td>
                        <td data-label="Info" style="font-size:0.8rem;color:var(--text-muted);">
                            #<?= (int) $m['attempts'] ?><?= !empty($m['last_error']) ? ' · ' . e($m['last_error']) : '' ?>
                        </td>
                        <td class="bx-td-actions">
                            <form method="post" action="<?= e(url('/admin/media')) ?>" style="margin:0;">
                                <?= csrf_field() ?>
                                <input type="hidden" name="action" value="cdn_single_sync">
                                <input type="hidden" name="rel_path" value="<?= e($m['rel_path']) ?>">
                                <button type="submit" class="bx-btn bx-btn-outline" style="padding:8px 12px;font-size:0.82rem;"><?= $lang === 'bn' ? 'সিঙ্ক' : 'Sync Now' ?></button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
</section>
