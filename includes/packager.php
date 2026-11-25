<?php
declare(strict_types=1);

/**
 * Pure-PHP Portability Site Packager (.zip export)
 * Creates a downloadable ZIP containing code, fresh SQL (full or clean), and /uploads.
 * Excludes /storage (protected PDF pages/cache/logs), real config.php secrets, and .lock files.
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
        ? ['admins', 'users', 'orders', 'library', 'wishlist', 'reviews', 'reading_logs', 'reading_progress', 'reader_tokens', 'password_resets', 'rate_limits', 'security_logs', 'admin_logs', 'notifications', 'folder_visits']
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
            if ($cleanPackage && $safeT === 'settings' && str_starts_with((string) ($row['setting_key'] ?? ''), 'smtp_')) {
                $row['setting_value'] = in_array($row['setting_key'], ['smtp_port', 'smtp_encryption'], true) ? $row['setting_value'] : '';
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

function stream_site_package_zip(array $admin, bool $cleanPackage): never
{
    // If any local /uploads images are missing locally (CDN-only mode), pull them before packaging
    media_scan_local_uploads();

    $tmpZip = tempnam(sys_get_temp_dir(), 'bx_pkg_');
    $zip = new BookexaMiniZip($tmpZip);

    $excludedTop = ['storage', 'pc_ss', 'mbl_ss', '.git', 'node_modules'];
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
        $top = explode('/', $rel)[0] ?? '';

        if (in_array($top, $excludedTop, true)) {
            continue;
        }
        if ($rel === 'config/config.php' || $rel === 'config/installed.lock' || $rel === 'database/bookexa.sql' || $rel === 'database/demo_seed.sql') {
            continue;
        }
        if (str_contains($rel, '.tmp.')) {
            continue;
        }
        $zip->addFile($abs, 'bookexa/' . $rel);
    }

    // Replace live config.php with config.sample.php (CDN disabled, zero secrets)
    $sampleCfg = @file_get_contents(APP_ROOT . '/config/config.sample.php');
    if ($sampleCfg !== false) {
        $zip->addFromString('bookexa/config/config.sample.php', $sampleCfg);
    }
    // Include fresh database/bookexa.sql dump
    $zip->addFromString('bookexa/database/bookexa.sql', generate_sql_dump_string($cleanPackage));

    // Create empty storage skeleton dirs with .htaccess guards
    foreach (['storage/cache', 'storage/pages', 'storage/logs', 'storage/demo'] as $stDir) {
        $ht = APP_ROOT . '/' . $stDir . '/.htaccess';
        if (is_file($ht)) {
            $zip->addFile($ht, 'bookexa/' . $stDir . '/.htaccess');
        }
    }

    $zip->finish();

    $modeLbl = $cleanPackage ? 'clean' : 'snapshot';
    $downloadName = "bookexa-package-{$modeLbl}-" . date('Ymd-His') . '.zip';
    log_admin_action($admin, 'export_site_package', 'system', null, "Exported {$modeLbl} portable ZIP");

    header('Content-Type: application/zip');
    header('Content-Disposition: attachment; filename="' . $downloadName . '"');
    header('Content-Length: ' . (string) filesize($tmpZip));
    header('Cache-Control: no-store');
    readfile($tmpZip);
    @unlink($tmpZip);
    exit;
}
