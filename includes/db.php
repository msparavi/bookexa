<?php
declare(strict_types=1);

function db(): PDO
{
    static $pdo = null;
    if ($pdo !== null) {
        return $pdo;
    }

    $cfg = app_config('db');
    $dsn = sprintf(
        'mysql:host=%s;port=%s;dbname=%s;charset=%s',
        $cfg['host'],
        $cfg['port'] ?? '3306',
        $cfg['name'],
        $cfg['charset'] ?? 'utf8mb4'
    );

    $pdo = new PDO($dsn, $cfg['user'], $cfg['pass'], [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ]);

    return $pdo;
}

function db_record_metric(float $elapsedSec): void
{
    $GLOBALS['__bx_db_queries'] = (int) ($GLOBALS['__bx_db_queries'] ?? 0) + 1;
    $GLOBALS['__bx_db_time_ms'] = (float) ($GLOBALS['__bx_db_time_ms'] ?? 0.0) + ($elapsedSec * 1000.0);
}

function db_metrics(): array
{
    return [
        'queries' => (int) ($GLOBALS['__bx_db_queries'] ?? 0),
        'time_ms' => round((float) ($GLOBALS['__bx_db_time_ms'] ?? 0.0), 2),
    ];
}

function db_one(string $sql, array $params = []): ?array
{
    $t0 = microtime(true);
    $stmt = db()->prepare($sql);
    $stmt->execute($params);
    $row = $stmt->fetch();
    db_record_metric(microtime(true) - $t0);
    return $row !== false ? $row : null;
}

function db_all(string $sql, array $params = []): array
{
    $t0 = microtime(true);
    $stmt = db()->prepare($sql);
    $stmt->execute($params);
    $rows = $stmt->fetchAll();
    db_record_metric(microtime(true) - $t0);
    return $rows;
}

function db_exec(string $sql, array $params = []): int
{
    $t0 = microtime(true);
    $stmt = db()->prepare($sql);
    $stmt->execute($params);
    $cnt = $stmt->rowCount();
    db_record_metric(microtime(true) - $t0);
    return $cnt;
}

function db_insert(string $sql, array $params = []): int
{
    $t0 = microtime(true);
    $stmt = db()->prepare($sql);
    $stmt->execute($params);
    $id = (int) db()->lastInsertId();
    db_record_metric(microtime(true) - $t0);
    return $id;
}
