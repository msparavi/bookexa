<?php
declare(strict_types=1);

/**
 * Optional Cron Media Sync Worker (cron/sync_media.php)
 * CLI usage:  php cron/sync_media.php
 * HTTP usage: curl "https://domain.com/cron/sync_media.php?key=<hmac>"
 */
require_once dirname(__DIR__) . '/includes/bootstrap.php';

$isCli = (PHP_SAPI === 'cli');
if (!$isCli) {
    $expectedKey = cron_sync_token();
    $given = (string) ($_GET['key'] ?? '');
    if ($expectedKey === '' || !hash_equals($expectedKey, $given)) {
        http_response_code(403);
        header('Content-Type: application/json');
        echo json_encode(['ok' => false, 'message' => 'Forbidden']);
        exit;
    }
    header('Content-Type: application/json');
}

$added = media_scan_local_uploads();
$batch = cdn_config()['sync_batch'];
$res = media_sync_pending_batch(max(10, $batch), true);
try {
    db_exec("INSERT INTO settings (setting_key, setting_value) VALUES ('last_cron_at', NOW()) ON DUPLICATE KEY UPDATE setting_value = NOW()");
} catch (Throwable $e) {
}
$payload = ['ok' => true, 'scanned_new' => $added, 'sync' => $res];

echo json_encode($payload, JSON_PRETTY_PRINT) . "\n";
