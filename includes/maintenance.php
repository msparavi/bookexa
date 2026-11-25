<?php
declare(strict_types=1);

/**
 * Lazy automatic maintenance cleanup (~hourly without needing cPanel cron)
 * + Admin full MySQL .sql backup export.
 */
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
    } catch (Throwable $e) {
    }

    // 5. Purge stale cached PDF files older than pdf_cache_ttl
    $ttl = max(3600, (int) app_config('security.pdf_cache_ttl', 21600));
    foreach (glob($stampDir . '/*.bin') ?: [] as $binFile) {
        $mtime = @filemtime($binFile);
        if ($mtime !== false && (time() - $mtime) > $ttl) {
            @unlink($binFile);
        }
    }
}

function stream_mysql_backup_sql(array $admin): never
{
    $pdo = db();
    $dbName = (string) app_config('db.name', 'bookexa');
    $filename = 'bookexa-backup-' . date('Ymd-His') . '.sql';

    log_admin_action($admin, 'db_backup', 'system', null, 'Downloaded SQL backup');

    header('Content-Type: application/sql; charset=UTF-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

    echo "-- Bookexa Database Backup\n";
    echo "-- Database: `{$dbName}`\n";
    echo "-- Generated: " . date('c') . "\n\n";
    echo "SET NAMES utf8mb4;\n";
    echo "SET FOREIGN_KEY_CHECKS = 0;\n\n";

    $tables = $pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN);
    foreach ($tables as $table) {
        $safeTable = str_replace('`', '', (string) $table);
        $createRow = $pdo->query("SHOW CREATE TABLE `{$safeTable}`")->fetch(PDO::FETCH_ASSOC);
        $createSql = $createRow['Create Table'] ?? ($createRow['Create View'] ?? '');
        if ($createSql === '') {
            continue;
        }
        echo "DROP TABLE IF EXISTS `{$safeTable}`;\n";
        echo $createSql . ";\n\n";

        $stmt = $pdo->query("SELECT * FROM `{$safeTable}`");
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $cols = array_map(fn($c) => '`' . str_replace('`', '', (string) $c) . '`', array_keys($row));
            $vals = array_map(function ($v) use ($pdo) {
                if ($v === null) {
                    return 'NULL';
                }
                return $pdo->quote((string) $v);
            }, array_values($row));
            echo "INSERT INTO `{$safeTable}` (" . implode(', ', $cols) . ') VALUES (' . implode(', ', $vals) . ");\n";
        }
        echo "\n";
    }

    echo "SET FOREIGN_KEY_CHECKS = 1;\n";
    exit;
}
