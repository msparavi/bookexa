<?php
declare(strict_types=1);

/**
 * Bookexa Idempotent PHP Database Migration Runner & INFORMATION_SCHEMA Helpers
 */

function mig_pdo(?PDO $pdo = null): PDO
{
    return $pdo ?? db();
}

function migration_ensure_tracking_table(?PDO $pdo = null): void
{
    $conn = mig_pdo($pdo);
    $conn->exec(
        "CREATE TABLE IF NOT EXISTS `schema_migrations` (
            `version` VARCHAR(80) NOT NULL,
            `applied_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (`version`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    );
}

function mig_table_exists(PDO $pdo, string $table): bool
{
    $st = $pdo->prepare(
        'SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? LIMIT 1'
    );
    $st->execute([$table]);
    return (bool) $st->fetchColumn();
}

function mig_column_exists(PDO $pdo, string $table, string $column): bool
{
    $st = $pdo->prepare(
        'SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ? LIMIT 1'
    );
    $st->execute([$table, $column]);
    return (bool) $st->fetchColumn();
}

function mig_index_exists(PDO $pdo, string $table, string $indexName): bool
{
    $st = $pdo->prepare(
        'SELECT 1 FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND INDEX_NAME = ? LIMIT 1'
    );
    $st->execute([$table, $indexName]);
    return (bool) $st->fetchColumn();
}

function mig_create_table_if_missing(PDO $pdo, string $table, string $createSql): bool
{
    if (mig_table_exists($pdo, $table)) {
        return false;
    }
    $pdo->exec($createSql);
    return true;
}

function mig_add_column_if_missing(PDO $pdo, string $table, string $column, string $definitionAndPos): bool
{
    if (!mig_table_exists($pdo, $table) || mig_column_exists($pdo, $table, $column)) {
        return false;
    }
    $pdo->exec("ALTER TABLE `{$table}` ADD COLUMN `{$column}` {$definitionAndPos}");
    return true;
}

function mig_modify_column_if_exists(PDO $pdo, string $table, string $column, string $definition): bool
{
    if (!mig_table_exists($pdo, $table) || !mig_column_exists($pdo, $table, $column)) {
        return false;
    }
    $pdo->exec("ALTER TABLE `{$table}` MODIFY COLUMN `{$column}` {$definition}");
    return true;
}

function mig_add_index_if_missing(PDO $pdo, string $table, string $indexName, string $indexClause): bool
{
    if (!mig_table_exists($pdo, $table) || mig_index_exists($pdo, $table, $indexName)) {
        return false;
    }
    $pdo->exec("ALTER TABLE `{$table}` ADD {$indexClause}");
    return true;
}

function mig_insert_setting_if_missing(PDO $pdo, string $key, string $defaultValue): bool
{
    if (!mig_table_exists($pdo, 'settings')) {
        return false;
    }
    $st = $pdo->prepare('SELECT 1 FROM `settings` WHERE `setting_key` = ? LIMIT 1');
    $st->execute([$key]);
    if ($st->fetchColumn()) {
        return false;
    }
    $ins = $pdo->prepare('INSERT INTO `settings` (`setting_key`, `setting_value`) VALUES (?, ?)');
    $ins->execute([$key, $defaultValue]);
    return true;
}

function migration_discover_files(): array
{
    $dir = APP_ROOT . '/database/migrations';
    if (!is_dir($dir)) {
        return [];
    }
    $files = glob($dir . '/*.php') ?: [];
    sort($files, SORT_NATURAL | SORT_FLAG_CASE);
    $list = [];
    foreach ($files as $path) {
        $version = basename($path, '.php');
        if (!preg_match('/^\d{3}_[A-Za-z0-9_]+$/', $version)) {
            continue;
        }
        $meta = require $path;
        if (is_callable($meta)) {
            $list[$version] = [
                'version'     => $version,
                'path'        => $path,
                'description' => str_replace('_', ' ', $version),
                'up'          => $meta,
            ];
        } elseif (is_array($meta) && isset($meta['up']) && is_callable($meta['up'])) {
            $list[$version] = [
                'version'     => $version,
                'path'        => $path,
                'description' => (string) ($meta['description'] ?? str_replace('_', ' ', $version)),
                'up'          => $meta['up'],
            ];
        }
    }
    return $list;
}

function migration_applied_map(?PDO $pdo = null): array
{
    $conn = mig_pdo($pdo);
    migration_ensure_tracking_table($conn);
    $rows = $conn->query('SELECT version, applied_at FROM `schema_migrations` ORDER BY version ASC')->fetchAll(PDO::FETCH_ASSOC) ?: [];
    $map = [];
    foreach ($rows as $r) {
        $map[(string) $r['version']] = (string) $r['applied_at'];
    }
    return $map;
}

function migration_list_status(?PDO $pdo = null): array
{
    $conn = mig_pdo($pdo);
    $all = migration_discover_files();
    $appliedMap = migration_applied_map($conn);
    $items = [];
    $pending = 0;
    foreach ($all as $ver => $meta) {
        $isApplied = isset($appliedMap[$ver]);
        if (!$isApplied) {
            $pending++;
        }
        $items[] = [
            'version'     => $ver,
            'description' => $meta['description'],
            'applied'     => $isApplied,
            'applied_at'  => $appliedMap[$ver] ?? null,
        ];
    }
    return [
        'items'         => $items,
        'pending_count' => $pending,
        'total_count'   => count($items),
    ];
}

function migration_pending_count(?PDO $pdo = null): int
{
    return (int) migration_list_status($pdo)['pending_count'];
}

function migration_record_applied(PDO $pdo, string $version): void
{
    migration_ensure_tracking_table($pdo);
    $st = $pdo->prepare('INSERT INTO `schema_migrations` (`version`) VALUES (?) ON DUPLICATE KEY UPDATE `applied_at` = `applied_at`');
    $st->execute([$version]);
}

function migration_mark_all_applied(?PDO $pdo = null): int
{
    $conn = mig_pdo($pdo);
    migration_ensure_tracking_table($conn);
    $count = 0;
    foreach (migration_discover_files() as $ver => $_) {
        migration_record_applied($conn, $ver);
        $count++;
    }
    return $count;
}

function migration_run_one(string $version, ?PDO $pdo = null): array
{
    $conn = mig_pdo($pdo);
    migration_ensure_tracking_table($conn);
    $all = migration_discover_files();
    if (!isset($all[$version])) {
        return ['ok' => false, 'version' => $version, 'error' => 'Migration not found: ' . $version];
    }
    $up = $all[$version]['up'];
    try {
        $up($conn);
        migration_record_applied($conn, $version);
        return ['ok' => true, 'version' => $version];
    } catch (Throwable $e) {
        @error_log('[' . date('c') . '] Migration ' . $version . ' failed: ' . $e->getMessage() . "\n");
        return ['ok' => false, 'version' => $version, 'error' => $e->getMessage()];
    }
}

function migration_run_pending(?PDO $pdo = null): array
{
    $conn = mig_pdo($pdo);
    $status = migration_list_status($conn);
    $applied = [];
    $errors = [];
    foreach ($status['items'] as $row) {
        if ($row['applied']) {
            continue;
        }
        $res = migration_run_one((string) $row['version'], $conn);
        if (!empty($res['ok'])) {
            $applied[] = (string) $row['version'];
        } else {
            $errors[] = (string) $row['version'] . ': ' . ($res['error'] ?? 'Unknown error');
            break;
        }
    }
    return [
        'ok'      => empty($errors),
        'applied' => $applied,
        'errors'  => $errors,
        'pending' => migration_pending_count($conn),
    ];
}

function migration_signature_hash(): string
{
    $files = array_keys(migration_discover_files());
    return sha1(implode('|', $files));
}

function migration_invalidate_flag(): void
{
    @unlink(APP_ROOT . '/storage/cache/migrations_done.flag');
}

/**
 * Lazy auto-runner invoked on admin requests once per request until all pending migrations are done.
 */
function migration_lazy_tick(bool $allowAutoApply = true): int
{
    static $ranThisRequest = false;
    if ($ranThisRequest) {
        return 0;
    }
    $ranThisRequest = true;

    $flagPath = APP_ROOT . '/storage/cache/migrations_done.flag';
    $sig = migration_signature_hash();
    if (is_file($flagPath) && trim((string) @file_get_contents($flagPath)) === $sig) {
        return 0;
    }

    try {
        $pending = migration_pending_count();
        if ($pending === 0) {
            ensure_bookexa_cache_dir();
            @file_put_contents($flagPath, $sig, LOCK_EX);
            return 0;
        }
        if ($allowAutoApply) {
            // Run at most one pending batch automatically per request safely behind a lock
            $lockFile = APP_ROOT . '/storage/cache/migrations.lock';
            $fp = @fopen($lockFile, 'c');
            if ($fp && @flock($fp, LOCK_EX | LOCK_NB)) {
                try {
                    migration_run_pending();
                } finally {
                    @flock($fp, LOCK_UN);
                }
            }
            if ($fp) {
                @fclose($fp);
            }
            $pending = migration_pending_count();
            if ($pending === 0) {
                ensure_bookexa_cache_dir();
                @file_put_contents($flagPath, $sig, LOCK_EX);
            }
        }
        return $pending;
    } catch (Throwable $e) {
        return 1;
    }
}
