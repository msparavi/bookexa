<?php
declare(strict_types=1);

/**
 * Pure-PHP Portability Site Packager (.zip export) with Strict Allowlist Verification
 * Creates a downloadable ZIP containing clean application code, SQL (clean or snapshot), and /uploads.
 * Excludes /storage contents, config.php secrets, installed.lock, logs, demo_seed.sql, and .git.
 */

class BookexaMiniZip
{
    private array $entries = [];
    private string $centralDir = '';
    private int $offset = 0;
    /** @var resource */
    private $fp;

    public function __construct(string $tmpFile)
    {
        $fp = @fopen($tmpFile, 'wb');
        if (!$fp) {
            throw new RuntimeException('Cannot open temp ZIP file for writing.');
        }
        $this->fp = $fp;
    }

    public function addFromString(string $zipPath, string $data): void
    {
        $zipPath = ltrim(str_replace('\\', '/', $zipPath), '/');
        $len = strlen($data);
        $crc = crc32($data);
        $zdata = gzdeflate($data, 6);
        $compLen = strlen($zdata);

        $localHdr = pack(
            'VvvvvvVVVvv',
            0x04034b50,
            20, 0x0800, 8, 0, 0,
            $crc, $compLen, $len,
            strlen($zipPath), 0
        ) . $zipPath;

        fwrite($this->fp, $localHdr . $zdata);

        $this->centralDir .= pack(
            'VvvvvvvVVVvvvvvVV',
            0x02014b50,
            20, 20, 0x0800, 8, 0, 0,
            $crc, $compLen, $len,
            strlen($zipPath), 0, 0, 0, 0, 0,
            $this->offset
        ) . $zipPath;

        $this->offset += strlen($localHdr) + $compLen;
        $this->entries[] = $zipPath;
    }

    public function addFile(string $absPath, string $zipPath): void
    {
        $data = @file_get_contents($absPath);
        if ($data !== false) {
            $this->addFromString($zipPath, $data);
        }
    }

    public function entriesList(): array
    {
        return $this->entries;
    }

    public function finish(): void
    {
        $count = count($this->entries);
        $cdLen = strlen($this->centralDir);
        fwrite($this->fp, $this->centralDir);
        fwrite($this->fp, pack(
            'VvvvvVVv',
            0x06054b50,
            0, 0, $count, $count, $cdLen, $this->offset, 0
        ));
        fclose($this->fp);
    }
}

/**
 * Verify every ZIP entry strictly against commercial distribution allowlist & exclusions.
 * Returns ['ok' => bool, 'violations' => string[]].
 */
function packager_verify_zip_allowlist(array $entries): array
{
    $allowedRootFiles = [
        '.htaccess', 'CHANGELOG.md', 'LICENSE', 'README.md', 'SECURITY.md',
        'index.php', 'manifest.webmanifest', 'sw.js',
    ];
    $allowedTopDirs = [
        'assets', 'cdn-receiver', 'config', 'cron', 'database',
        'docs', 'includes', 'pages', 'routes', 'storage', 'tests', 'uploads',
    ];
    $forbiddenExact = [
        'config/config.php',
        'config/installed.lock',
        'database/demo_seed.sql',
        'robots.txt',
    ];

    $violations = [];
    foreach ($entries as $rawPath) {
        $norm = ltrim(str_replace('\\', '/', (string) $rawPath), '/');
        $rel = str_starts_with($norm, 'bookexa/') ? substr($norm, 8) : $norm;
        $low = strtolower($rel);

        if ($rel === '' || str_contains($rel, '..')) {
            $violations[] = "Invalid path: {$norm}";
            continue;
        }
        if (str_starts_with($low, '.git/') || $low === '.git' || str_contains($low, '/.git/')) {
            $violations[] = "Git metadata excluded: {$rel}";
            continue;
        }
        if (str_ends_with($low, '.log') || str_contains($low, '.tmp.') || str_ends_with($low, '.bak')) {
            $violations[] = "Log/temp file excluded: {$rel}";
            continue;
        }
        if (in_array($low, $forbiddenExact, true)) {
            $violations[] = "Sensitive/excluded file present: {$rel}";
            continue;
        }

        $parts = explode('/', $rel);
        $top = $parts[0] ?? '';
        if (count($parts) === 1) {
            if (!in_array($rel, $allowedRootFiles, true)) {
                $violations[] = "Non-allowlisted root file: {$rel}";
            }
            continue;
        }
        if (!in_array($top, $allowedTopDirs, true)) {
            $violations[] = "Non-allowlisted top directory: {$rel}";
            continue;
        }
        if ($top === 'storage') {
            $base = basename($rel);
            if ($base !== '.htaccess') {
                $violations[] = "Storage content excluded (only .htaccess skeleton allowed): {$rel}";
            }
        }
    }

    return ['ok' => empty($violations), 'violations' => $violations];
}

function generate_sql_dump_string(bool $cleanPackage): string
{
    if ($cleanPackage) {
        $baseSchema = @file_get_contents(APP_ROOT . '/database/bookexa.sql');
        if ($baseSchema !== false && $baseSchema !== '') {
            return $baseSchema;
        }
    }

    $pdo = db();
    $out = "-- Bookexa Export SQL Dump (" . ($cleanPackage ? 'Clean' : 'Full Snapshot') . ")\n";
    $out .= "-- Generated: " . date('c') . "\n\nSET NAMES utf8mb4;\nSET FOREIGN_KEY_CHECKS = 0;\n\n";

    $skipDataTables = $cleanPackage
        ? [
            'admins', 'admin_sessions', 'users', 'user_devices', 'otp_verifications',
            'orders', 'shop_orders', 'shop_order_items', 'shop_carts', 'shop_cart_items',
            'library', 'wishlist', 'product_wishlist', 'reviews', 'product_reviews',
            'product_stock_logs', 'reading_logs', 'reading_progress', 'reader_tokens',
            'reader_bookmarks', 'password_resets', 'rate_limits', 'security_logs',
            'admin_logs', 'notifications', 'folder_visits', 'contact_messages',
            'reading_daily_usage', 'recently_viewed',
        ]
        : ['reader_tokens', 'password_resets', 'rate_limits'];

    $tables = $pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN);
    foreach ($tables as $t) {
        $safeT = str_replace('`', '', (string) $t);
        $create = $pdo->query("SHOW CREATE TABLE `{$safeT}`")->fetch(PDO::FETCH_ASSOC);
        $ddl = $create['Create Table'] ?? '';
        if ($ddl === '') {
            continue;
        }
        $out .= "DROP TABLE IF EXISTS `{$safeT}`;\n{$ddl};\n\n";
        if (in_array($safeT, $skipDataTables, true)) {
            continue;
        }
        $stmt = $pdo->query("SELECT * FROM `{$safeT}`");
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            if ($cleanPackage && $safeT === 'settings') {
                $k = (string) ($row['setting_key'] ?? '');
                if (str_starts_with($k, 'smtp_') || str_contains($k, '_secret') || str_contains($k, '_api_key') || $k === 'cron_sync_secret') {
                    $row['setting_value'] = in_array($k, ['smtp_port', 'smtp_encryption'], true) ? $row['setting_value'] : '';
                } elseif ($k === 'demo_mode') {
                    $row['setting_value'] = '0';
                }
            }
            $cols = array_map(fn($c) => '`' . str_replace('`', '', (string) $c) . '`', array_keys($row));
            $vals = array_map(fn($v) => $v === null ? 'NULL' : $pdo->quote((string) $v), array_values($row));
            $out .= "INSERT INTO `{$safeT}` (" . implode(', ', $cols) . ') VALUES (' . implode(', ', $vals) . ");\n";
        }
        $out .= "\n";
    }
    $out .= "SET FOREIGN_KEY_CHECKS = 1;\n";
    return $out;
}

function build_site_package_zip(bool $cleanPackage): array
{
    media_scan_local_uploads();

    $tmpZip = tempnam(sys_get_temp_dir(), 'bx_pkg_');
    $zip = new BookexaMiniZip($tmpZip);

    $allowedRootFiles = [
        '.htaccess', 'CHANGELOG.md', 'LICENSE', 'README.md', 'SECURITY.md',
        'index.php', 'manifest.webmanifest', 'sw.js',
    ];
    $allowedTopDirs = [
        'assets', 'cdn-receiver', 'config', 'cron', 'database',
        'docs', 'includes', 'pages', 'routes', 'tests', 'uploads',
    ];
    $excludedFiles = [
        'config/config.php', 'config/installed.lock',
        'database/bookexa.sql', 'database/demo_seed.sql', 'robots.txt',
    ];

    $it = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator(APP_ROOT, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::SELF_FIRST
    );

    foreach ($it as $file) {
        if (!$file->isFile()) {
            continue;
        }
        $abs = $file->getPathname();
        $rel = ltrim(str_replace('\\', '/', substr($abs, strlen(APP_ROOT))), '/');
        $parts = explode('/', $rel);
        $top = $parts[0] ?? '';

        if (count($parts) === 1) {
            if (!in_array($rel, $allowedRootFiles, true)) {
                continue;
            }
        } elseif (!in_array($top, $allowedTopDirs, true)) {
            continue;
        }
        if (in_array(strtolower($rel), $excludedFiles, true)) {
            continue;
        }
        if (str_ends_with(strtolower($rel), '.log') || str_contains($rel, '.tmp.') || str_ends_with(strtolower($rel), '.bak')) {
            continue;
        }
        $zip->addFile($abs, 'bookexa/' . $rel);
    }

    $sampleCfg = @file_get_contents(APP_ROOT . '/config/config.sample.php');
    if ($sampleCfg !== false) {
        $zip->addFromString('bookexa/config/config.sample.php', $sampleCfg);
    }
    $zip->addFromString('bookexa/database/bookexa.sql', generate_sql_dump_string($cleanPackage));

    foreach (['storage/cache', 'storage/pages', 'storage/logs', 'storage/demo', 'storage/backups', 'storage/payment_proofs'] as $stDir) {
        $ht = APP_ROOT . '/' . $stDir . '/.htaccess';
        if (is_file($ht)) {
            $zip->addFile($ht, 'bookexa/' . $stDir . '/.htaccess');
        }
    }

    // Strict allowlist verification before finalizing archive
    $check = packager_verify_zip_allowlist($zip->entriesList());
    if (empty($check['ok'])) {
        $zip->finish();
        @unlink($tmpZip);
        throw new RuntimeException('Package allowlist check failed: ' . implode('; ', $check['violations']));
    }

    $zip->finish();
    return ['path' => $tmpZip, 'entries' => $zip->entriesList()];
}

function stream_site_package_zip(array $admin, bool $cleanPackage): never
{
    $built = build_site_package_zip($cleanPackage);
    $tmpZip = $built['path'];

    $modeLbl = $cleanPackage ? 'clean' : 'snapshot';
    $downloadName = "bookexa-package-{$modeLbl}-" . date('Ymd-His') . '.zip';
    log_admin_action($admin, 'export_site_package', 'system', null, "Exported {$modeLbl} portable ZIP (" . count($built['entries']) . ' files verified)');

    header('Content-Type: application/zip');
    header('Content-Disposition: attachment; filename="' . $downloadName . '"');
    header('Content-Length: ' . (string) filesize($tmpZip));
    header('Cache-Control: no-store');
    readfile($tmpZip);
    @unlink($tmpZip);
    exit;
}
