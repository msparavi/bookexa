<?php
declare(strict_types=1);

require_once APP_ROOT . '/includes/maintenance.php';

$lang = current_lang();
$fmtBytes = static function (int $bytes): string {
    if ($bytes >= 1073741824) return round($bytes / 1073741824, 2) . ' GB';
    if ($bytes >= 1048576) return round($bytes / 1048576, 2) . ' MB';
    if ($bytes >= 1024) return round($bytes / 1024, 1) . ' KB';
    return $bytes . ' B';
};
$dirBytes = static function (string $dir): int {
    if (!is_dir($dir)) return 0;
    $sum = 0;
    $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS));
    foreach ($it as $f) {
        if ($f->isFile()) $sum += $f->getSize();
    }
    return $sum;
};

$extChecks = [
    'curl'          => extension_loaded('curl'),
    'gd'            => extension_loaded('gd'),
    'gd_webp'       => extension_loaded('gd') && function_exists('imagewebp'),
    'gd_freetype'   => extension_loaded('gd') && function_exists('imagettftext'),
    'mbstring'      => extension_loaded('mbstring'),
    'fileinfo'      => extension_loaded('fileinfo'),
    'openssl'       => extension_loaded('openssl'),
    'pdo_mysql'     => extension_loaded('pdo_mysql'),
];

$uploadsBytes = $dirBytes(APP_ROOT . '/uploads');
$storageBytes = $dirBytes(APP_ROOT . '/storage');
$cacheBytes   = $dirBytes(APP_ROOT . '/storage/cache');
$queueStats   = ['unprocessed' => 0, 'processing' => 0, 'ready' => 0, 'failed' => 0];
foreach (db_all('SELECT pages_status, processing_status, COUNT(*) AS c FROM items GROUP BY pages_status, processing_status') as $qr) {
    if ($qr['processing_status'] === 'failed') {
        $queueStats['failed'] += (int) $qr['c'];
    } else {
        $st = (string) $qr['pages_status'];
        if (isset($queueStats[$st])) $queueStats[$st] += (int) $qr['c'];
    }
}

$lastCronAt = site_setting('last_cron_at', '') ?: (is_file(APP_ROOT . '/storage/cache/last_cleanup.txt') ? date('Y-m-d H:i:s', (int) file_get_contents(APP_ROOT . '/storage/cache/last_cleanup.txt')) : 'Never');
$lastBackupAt = site_setting('last_daily_backup_at', '') ?: 'Never';
$backupsList = list_daily_backups();

$errLogLines = [];
$errLogPath = APP_ROOT . '/storage/logs/php-error.log';
if (is_file($errLogPath)) {
    $lines = @file($errLogPath, FILE_IGNORE_NEW_LINES) ?: [];
    $errLogLines = array_slice($lines, -50);
}
?>
<div class="bx-grid-2" style="margin-bottom:20px;">
    <!-- Runtime & Extensions -->
    <section class="bx-card">
        <h2 style="margin:0 0 12px;font-size:1.12rem;font-family:var(--font-serif);">
            <?= $lang === 'bn' ? 'PHP ও এক্সটেনশন অবস্থা' : 'PHP Runtime & Required Extensions' ?>
        </h2>
        <div style="display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:10px;margin-bottom:14px;">
            <div style="padding:10px;border-radius:10px;background:var(--bg-alt);border:1px solid var(--border);">
                <small style="color:var(--text-muted);display:block;">PHP Version</small>
                <strong><?= e(PHP_VERSION) ?></strong>
            </div>
            <div style="padding:10px;border-radius:10px;background:var(--bg-alt);border:1px solid var(--border);">
                <small style="color:var(--text-muted);display:block;">memory_limit / max_exec</small>
                <strong><?= e((string) ini_get('memory_limit')) ?> / <?= e((string) ini_get('max_execution_time')) ?>s</strong>
            </div>
        </div>
        <div style="display:flex;gap:8px;flex-wrap:wrap;">
            <?php foreach ($extChecks as $name => $ok): ?>
                <span class="bx-status-pill <?= $ok ? 'st-active' : 'st-rejected' ?>">
                    <?= $ok ? '✓' : '✕' ?> <?= e($name) ?>
                </span>
            <?php endforeach; ?>
        </div>
    </section>

    <!-- Disk, Cache, Queue & Cron -->
    <section class="bx-card">
        <div style="display:flex;justify-content:space-between;align-items:center;gap:8px;flex-wrap:wrap;margin-bottom:12px;">
            <h2 style="margin:0;font-size:1.12rem;font-family:var(--font-serif);">
                <?= $lang === 'bn' ? 'ডিস্ক, ক্যাশ ও কিউ অবস্থা' : 'Storage, Cache, Queue & Cron' ?>
            </h2>
            <form method="post" action="<?= e(url('/admin/system-health')) ?>" style="margin:0;">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="clean_cache">
                <button type="submit" class="bx-btn bx-btn-outline bx-btn-sm"><?= $lang === 'bn' ? 'ক্যাশ পরিষ্কার করুন' : 'Clean Cache' ?></button>
            </form>
        </div>
        <div style="display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:8px;margin-bottom:12px;">
            <div style="padding:10px;border-radius:10px;background:var(--bg-alt);border:1px solid var(--border);">
                <small style="color:var(--text-muted);display:block;">uploads/</small>
                <strong><?= e($fmtBytes($uploadsBytes)) ?></strong>
            </div>
            <div style="padding:10px;border-radius:10px;background:var(--bg-alt);border:1px solid var(--border);">
                <small style="color:var(--text-muted);display:block;">storage/</small>
                <strong><?= e($fmtBytes($storageBytes)) ?></strong>
            </div>
            <div style="padding:10px;border-radius:10px;background:var(--bg-alt);border:1px solid var(--border);">
                <small style="color:var(--text-muted);display:block;">cache/</small>
                <strong><?= e($fmtBytes($cacheBytes)) ?></strong>
            </div>
        </div>
        <div style="font-size:0.86rem;line-height:1.7;">
            <div><strong><?= $lang === 'bn' ? 'পেজ প্রস্তুত কিউ:' : 'Prepare Queue:' ?></strong>
                Ready: <strong><?= $queueStats['ready'] ?></strong> ·
                Unprocessed: <strong><?= $queueStats['unprocessed'] ?></strong> ·
                Processing: <strong><?= $queueStats['processing'] ?></strong> ·
                Failed: <strong><?= $queueStats['failed'] ?></strong>
            </div>
            <div><strong><?= $lang === 'bn' ? 'সর্বশেষ ক্রন / মেইনটেন্যান্স:' : 'Last Cron / Maintenance Run:' ?></strong> <code><?= e($lastCronAt) ?></code></div>
            <div><strong><?= $lang === 'bn' ? 'সর্বশেষ অটো ব্যাকআপ:' : 'Last Auto DB Backup:' ?></strong> <code><?= e($lastBackupAt) ?></code></div>
        </div>
    </section>
</div>

<!-- Daily Auto DB Backups (Last 7 Kept) -->
<section class="bx-card" style="margin-bottom:20px;">
    <div style="display:flex;justify-content:space-between;align-items:center;gap:10px;flex-wrap:wrap;margin-bottom:12px;">
        <div>
            <h2 style="margin:0;font-size:1.12rem;font-family:var(--font-serif);"><?= $lang === 'bn' ? 'দৈনিক স্বয়ংক্রিয় ডেটাবেস ব্যাকআপ (শেষ ৭টি সংরক্ষিত)' : 'Daily Automatic DB Backups (Last 7 Kept)' ?></h2>
            <small style="color:var(--text-muted);"><?= $lang === 'bn' ? 'সুরক্ষিত storage/backups ফোল্ডারে সংরক্ষিত।' : 'Stored safely inside protected storage/backups directory.' ?></small>
        </div>
        <div style="display:flex;gap:8px;flex-wrap:wrap;">
            <form method="post" action="<?= e(url('/admin/system-health')) ?>" style="margin:0;">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="run_daily_backup_now">
                <button type="submit" class="bx-btn bx-btn-outline bx-btn-sm"><?= $lang === 'bn' ? 'এখনই স্ন্যাপশট সংরক্ষণ' : 'Run Auto-Backup Now' ?></button>
            </form>
            <form method="post" action="<?= e(url('/admin/system-health')) ?>" style="margin:0;">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="download_db_backup">
                <button type="submit" class="bx-btn bx-btn-primary bx-btn-sm"><?= $lang === 'bn' ? 'ব্যাকআপ ডাউনলোড (.sql)' : 'Download Backup (.sql)' ?></button>
            </form>
        </div>
    </div>
    <div class="bx-table-wrap">
        <table class="bx-table bx-mobile-cards">
            <thead><tr><th>Filename</th><th>Size</th><th>Created</th><th>Action</th></tr></thead>
            <tbody>
                <?php if (!$backupsList): ?>
                    <tr class="bx-empty-row"><td colspan="4" style="color:var(--text-muted);"><?= $lang === 'bn' ? 'এখনো কোনো অটো ব্যাকআপ ফাইল তৈরি হয়নি।' : 'No daily backup files generated yet.' ?></td></tr>
                <?php else: foreach ($backupsList as $bf): ?>
                    <tr>
                        <td class="bx-td-primary"><code><?= e($bf['name']) ?></code></td>
                        <td data-label="Size"><?= e($fmtBytes((int) $bf['size'])) ?></td>
                        <td data-label="Created"><?= e(date('d M Y, H:i:s', (int) $bf['mtime'])) ?></td>
                        <td class="bx-td-actions">
                            <a href="<?= e(url('/admin/system-health?download_backup=' . rawurlencode($bf['name']))) ?>" class="bx-btn bx-btn-outline bx-btn-sm">Download</a>
                        </td>
                    </tr>
                <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
</section>

<!-- Error Log (Last 50 Lines) -->
<section class="bx-card">
    <h2 style="margin:0 0 10px;font-size:1.12rem;font-family:var(--font-serif);">
        <?= $lang === 'bn' ? 'এরর লগ (সর্বশেষ ৫০ লাইন)' : 'PHP Error Log (Last 50 Lines)' ?>
    </h2>
    <?php if (!$errLogLines): ?>
        <p style="color:var(--text-muted);margin:0;"><?= $lang === 'bn' ? 'কোনো এরর লগ পাওয়া যায়নি।' : 'Error log is clean (empty or no entries).' ?></p>
    <?php else: ?>
        <pre style="background:var(--bg-alt);border:1px solid var(--border);border-radius:12px;padding:12px;max-height:320px;overflow:auto;font-size:0.78rem;line-height:1.5;margin:0;white-space:pre-wrap;"><?= e(implode("\n", $errLogLines)) ?></pre>
    <?php endif; ?>
</section>
