<?php
declare(strict_types=1);

/**
 * Lazy automatic maintenance cleanup (~hourly without needing cPanel cron)
 * + Admin full MySQL .sql backup export.
 */
function expire_stale_pending_orders(?int $daysOverride = null): int
{
    $days = $daysOverride ?? max(1, (int) site_setting('pending_order_expire_days', '3'));
    $stale = db_all(
        "SELECT id FROM orders WHERE status = 'pending' AND created_at < DATE_SUB(NOW(), INTERVAL ? DAY)",
        [$days]
    );
    $expired = 0;
    foreach ($stale as $row) {
        try {
            $res = apply_order_status_transition(
                (int) $row['id'],
                'expired',
                null,
                "Auto-expired after {$days} day(s) without verification"
            );
            if (!empty($res['changed'])) {
                $expired++;
            }
        } catch (Throwable $e) {
        }
    }
    return $expired;
}

function daily_backups_dir(): string
{
    $dir = APP_ROOT . '/storage/backups';
    if (!is_dir($dir)) {
        @mkdir($dir, 0755, true);
    }
    $ht = $dir . '/.htaccess';
    if (!is_file($ht)) {
        @file_put_contents($ht, "<IfModule mod_authz_core.c>\n    Require all denied\n</IfModule>\n<IfModule !mod_authz_core.c>\n    Order allow,deny\n    Deny from all\n</IfModule>\n");
    }
    return $dir;
}

function list_daily_backups(): array
{
    $dir = daily_backups_dir();
    $out = [];
    foreach (glob($dir . '/bookexa-auto-*.sql') ?: [] as $f) {
        if (is_file($f)) {
            $out[] = [
                'name'  => basename($f),
                'path'  => $f,
                'size'  => (int) @filesize($f),
                'mtime' => (int) (@filemtime($f) ?: time()),
            ];
        }
    }
    usort($out, static fn(array $a, array $b): int => $b['mtime'] <=> $a['mtime']);
    return $out;
}

function prune_daily_backups(int $keep = 7): void
{
    $list = list_daily_backups();
    foreach (array_slice($list, max(1, $keep)) as $old) {
        @unlink($old['path']);
    }
}

function build_mysql_backup_sql_string(): string
{
    $pdo = db();
    $dbName = (string) app_config('db.name', 'bookexa');
    $out  = "-- Bookexa Database Backup\n";
    $out .= "-- Database: `{$dbName}`\n";
    $out .= "-- Generated: " . date('c') . "\n\n";
    $out .= "SET NAMES utf8mb4;\nSET FOREIGN_KEY_CHECKS = 0;\n\n";

    $tables = $pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN);
    foreach ($tables as $table) {
        $safeTable = str_replace('`', '', (string) $table);
        $createRow = $pdo->query("SHOW CREATE TABLE `{$safeTable}`")->fetch(PDO::FETCH_ASSOC);
        $createSql = $createRow['Create Table'] ?? ($createRow['Create View'] ?? '');
        if ($createSql === '') {
            continue;
        }
        $out .= "DROP TABLE IF EXISTS `{$safeTable}`;\n{$createSql};\n\n";

        $stmt = $pdo->query("SELECT * FROM `{$safeTable}`");
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $cols = array_map(static fn($c): string => '`' . str_replace('`', '', (string) $c) . '`', array_keys($row));
            $vals = array_map(static fn($v): string => $v === null ? 'NULL' : $pdo->quote((string) $v), array_values($row));
            $out .= "INSERT INTO `{$safeTable}` (" . implode(', ', $cols) . ') VALUES (' . implode(', ', $vals) . ");\n";
        }
        $out .= "\n";
    }
    $out .= "SET FOREIGN_KEY_CHECKS = 1;\n";
    return $out;
}

function run_daily_db_backup_if_due(bool $force = false): ?string
{
    $dir = daily_backups_dir();
    $todayFile = $dir . '/bookexa-auto-' . date('Ymd') . '.sql';
    if (!$force && is_file($todayFile)) {
        return null;
    }
    try {
        $target = $force ? ($dir . '/bookexa-auto-' . date('Ymd-His') . '.sql') : $todayFile;
        $sql = build_mysql_backup_sql_string();
        if (@file_put_contents($target, $sql, LOCK_EX) === false) {
            return null;
        }
        prune_daily_backups(7);
        db_exec("INSERT INTO settings (setting_key, setting_value) VALUES ('last_daily_backup_at', NOW()) ON DUPLICATE KEY UPDATE setting_value = NOW()");
        return basename($target);
    } catch (Throwable $e) {
        return null;
    }
}

function run_lazy_maintenance_cleanup(): void
{
    $stampDir = APP_ROOT . '/storage/cache';
    $stampFile = $stampDir . '/last_cleanup.txt';
    if (!is_dir($stampDir)) {
        @mkdir($stampDir, 0755, true);
    }

    $lastRun = is_file($stampFile) ? (int) @file_get_contents($stampFile) : 0;
    if ((time() - $lastRun) < 3600) {
        return;
    }

    // Write timestamp first to avoid concurrent request stamps
    @file_put_contents($stampFile, (string) time(), LOCK_EX);

    try {
        // 1. Expired or long-used reader tokens (>24h)
        db_exec("DELETE FROM reader_tokens WHERE expires_at < NOW() OR (used_at IS NOT NULL AND used_at < DATE_SUB(NOW(), INTERVAL 1 DAY))");
        // 2. Expired or used password resets (>24h)
        db_exec("DELETE FROM password_resets WHERE expires_at < NOW() OR (used_at IS NOT NULL AND used_at < DATE_SUB(NOW(), INTERVAL 1 DAY))");
        // 3. Stale rate limit windows (>24h old)
        db_exec("DELETE FROM rate_limits WHERE window_start < ?", [time() - 86400]);
        // 4. Prune old logs (>90 days)
        db_exec("DELETE FROM reading_logs WHERE created_at < DATE_SUB(NOW(), INTERVAL 90 DAY)");
        db_exec("DELETE FROM security_logs WHERE created_at < DATE_SUB(NOW(), INTERVAL 90 DAY)");
        db_exec("DELETE FROM notifications WHERE is_read = 1 AND created_at < DATE_SUB(NOW(), INTERVAL 60 DAY)");
        expire_stale_pending_orders();
        db_exec("INSERT INTO settings (setting_key, setting_value) VALUES ('last_cron_at', NOW()) ON DUPLICATE KEY UPDATE setting_value = NOW()");
    } catch (Throwable $e) {
    }

    run_daily_db_backup_if_due(false);

    // 5. Purge stale cached PDF files older than pdf_cache_ttl
    $ttl = max(3600, (int) app_config('security.pdf_cache_ttl', 21600));
    foreach (glob($stampDir . '/*.bin') ?: [] as $binFile) {
        $mtime = @filemtime($binFile);
        if ($mtime !== false && (time() - $mtime) > $ttl) {
            @unlink($binFile);
        }
    }
    foreach (['wm/*.png', 'rl/*.json'] as $subPat) {
        foreach (glob($stampDir . '/' . $subPat) ?: [] as $cf) {
            $mtime = @filemtime($cf);
            if ($mtime !== false && (time() - $mtime) > 7200) {
                @unlink($cf);
            }
        }
    }
}

function stream_mysql_backup_sql(array $admin): never
{
    $filename = 'bookexa-backup-' . date('Ymd-His') . '.sql';

    log_admin_action($admin, 'db_backup', 'system', null, 'Downloaded SQL backup');

    header('Content-Type: application/sql; charset=UTF-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

    echo build_mysql_backup_sql_string();
    exit;
}
