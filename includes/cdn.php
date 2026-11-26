<?php
declare(strict_types=1);

/**
 * Bookexa Dual-Location Media Storage & Automatic CDN Synchronization
 * Local /uploads/ (always preserved) + Optional CDN (filesystem | ftp | http)
 */

interface BookexaCdnDriverInterface
{
    public function put(string $relPath, string $localAbsPath): array;
    public function delete(string $relPath): array;
    public function exists(string $relPath): bool;
    public function pull(string $relPath, string $localAbsPath): array;
}

function cdn_config(): array
{
    $raw = app_config('cdn', []);
    if (!is_array($raw)) {
        $raw = [];
    }
    return [
        'enabled'    => !empty($raw['enabled']),
        'driver'     => in_array(($raw['driver'] ?? 'filesystem'), ['filesystem', 'ftp', 'http'], true) ? $raw['driver'] : 'filesystem',
        'base_url'   => rtrim((string) ($raw['base_url'] ?? ''), '/'),
        'path'       => rtrim(str_replace('\\', '/', (string) ($raw['path'] ?? '')), '/'),
        'ftp'        => is_array($raw['ftp'] ?? null) ? $raw['ftp'] : [],
        'http'       => is_array($raw['http'] ?? null) ? $raw['http'] : [],
        'sync_batch' => max(1, (int) ($raw['sync_batch'] ?? 10)),
    ];
}

function media_allowed_subdirs(): array
{
    return ['folders', 'covers', 'banners', 'products', 'branding'];
}

function media_validate_rel_path(string $relPath): ?string
{
    $p = ltrim(str_replace('\\', '/', trim($relPath)), '/');
    if ($p === '' || str_contains($p, '..') || str_contains($p, "\0") || str_contains($p, '://')) {
        return null;
    }
    if (!preg_match('#^uploads/(folders|covers|banners|products|branding)/[A-Za-z0-9][A-Za-z0-9._-]*\.(jpg|jpeg|png|webp|gif|ico|svg)$#i', $p)) {
        return null;
    }
    return $p;
}

function media_sanitize_svg(string $bytes): string
{
    $bytes = preg_replace('#<script\b[^>]*>.*?</script>#is', '', $bytes) ?? '';
    $bytes = preg_replace('#<foreignObject\b[^>]*>.*?</foreignObject>#is', '', $bytes) ?? '';
    $bytes = preg_replace('/\son[a-z]+\s*=\s*(["\']).*?\1/is', '', $bytes) ?? '';
    $bytes = preg_replace('/\s(href|xlink:href)\s*=\s*(["\'])\s*(https?:|\/\/|javascript:|data:text).*?\2/is', '', $bytes) ?? '';
    return $bytes;
}

function cdn_security_htaccess_content(?string $hotlinkHost = null): string
{
    $content = "Options -Indexes\n\n"
        . "<IfModule mod_php.c>\n    php_flag engine off\n</IfModule>\n"
        . "<IfModule mod_php8.c>\n    php_flag engine off\n</IfModule>\n\n"
        . "<FilesMatch \"\\.(php|php\\d*|phtml|phar|pl|py|rb|jsp|asp|aspx|sh|cgi)$\">\n"
        . "    <IfModule mod_authz_core.c>\n        Require all denied\n    </IfModule>\n"
        . "    <IfModule !mod_authz_core.c>\n        Order allow,deny\n        Deny from all\n    </IfModule>\n"
        . "</FilesMatch>\n\n"
        . "<FilesMatch \"(?i)\\.(?!(jpe?g|png|webp|gif|svg|ico)$)[^.]+$\">\n"
        . "    <IfModule mod_authz_core.c>\n        Require all denied\n    </IfModule>\n"
        . "    <IfModule !mod_authz_core.c>\n        Order allow,deny\n        Deny from all\n    </IfModule>\n"
        . "</FilesMatch>\n\n"
        . "<IfModule mod_headers.c>\n"
        . "    Header always set X-Content-Type-Options \"nosniff\"\n"
        . "    Header always set Cache-Control \"public, max-age=31536000, immutable\"\n"
        . "</IfModule>\n\n"
        . "<FilesMatch \"(?i)\\.svg$\">\n"
        . "    <IfModule mod_headers.c>\n"
        . "        Header always set Content-Security-Policy \"sandbox\"\n"
        . "        Header always set Content-Disposition \"attachment\"\n"
        . "    </IfModule>\n"
        . "</FilesMatch>\n";

    if ($hotlinkHost !== null && $hotlinkHost !== '') {
        $hostEsc = preg_quote($hotlinkHost, '.');
        $content .= "\n<IfModule mod_rewrite.c>\n"
            . "    RewriteEngine On\n"
            . "    RewriteCond %{HTTP_REFERER} !^$\n"
            . "    RewriteCond %{HTTP_REFERER} !^https?://(www\\.)?{$hostEsc}(:|/|$) [NC]\n"
            . "    RewriteRule (?i)\\.(jpe?g|png|webp|gif|svg|ico)$ - [F,NC,L]\n"
            . "</IfModule>\n";
    } else {
        $content .= "\n# Optional Hotlink Protection (uncomment and replace domain.com)\n"
            . "# <IfModule mod_rewrite.c>\n"
            . "#     RewriteEngine On\n"
            . "#     RewriteCond %{HTTP_REFERER} !^$\n"
            . "#     RewriteCond %{HTTP_REFERER} !^https?://(www\\.)?domain\\.com(:|/|$) [NC]\n"
            . "#     RewriteRule (?i)\\.(jpe?g|png|webp|gif|svg|ico)$ - [F,NC,L]\n"
            . "# </IfModule>\n";
    }
    return $content;
}

function cdn_filesystem_subprefix(array $cfg): string
{
    $baseUrlPath = trim((string) (parse_url((string) ($cfg['base_url'] ?? ''), PHP_URL_PATH) ?: ''), '/');
    if ($baseUrlPath === '') {
        return '';
    }
    $pathTail = basename((string) ($cfg['path'] ?? ''));
    if ($pathTail !== '' && ($baseUrlPath === $pathTail || str_starts_with($baseUrlPath, $pathTail . '/'))) {
        $trimmed = trim(substr($baseUrlPath, strlen($pathTail)), '/');
        return $trimmed;
    }
    if ($cfg['path'] !== '' && str_ends_with((string) $cfg['path'], '/' . $baseUrlPath)) {
        return '';
    }
    return $baseUrlPath;
}

class BookexaCdnFilesystemDriver implements BookexaCdnDriverInterface
{
    private string $rootPath;
    private string $subPrefix;

    public function __construct(array $cfg)
    {
        $this->rootPath  = rtrim(str_replace('\\', '/', (string) ($cfg['path'] ?? '')), '/');
        $this->subPrefix = cdn_filesystem_subprefix($cfg);
    }

    public function targetBaseDir(): string
    {
        if ($this->rootPath === '') {
            return '';
        }
        return $this->subPrefix !== '' ? ($this->rootPath . '/' . $this->subPrefix) : $this->rootPath;
    }

    private function absTarget(string $relPath): ?string
    {
        $base = $this->targetBaseDir();
        $clean = media_validate_rel_path($relPath);
        if ($base === '' || $clean === null) {
            return null;
        }
        return $base . '/' . $clean;
    }

    private function ensureRootSecurityFiles(): void
    {
        $base = $this->targetBaseDir();
        if ($base === '') {
            return;
        }
        $uploadsDir = $base . '/uploads';
        if (!is_dir($uploadsDir)) {
            @mkdir($uploadsDir, 0755, true);
        }
        $htFile = $uploadsDir . '/.htaccess';
        $desired = cdn_security_htaccess_content();
        if (!is_file($htFile) || !str_contains((string) @file_get_contents($htFile), 'Content-Security-Policy "sandbox"')) {
            @file_put_contents($htFile, $desired);
        }
    }

    public function put(string $relPath, string $localAbsPath): array
    {
        if (!is_file($localAbsPath)) {
            return ['ok' => false, 'error' => 'Local source file missing.'];
        }
        $dst = $this->absTarget($relPath);
        if ($dst === null) {
            return ['ok' => false, 'error' => 'CDN filesystem path not configured or invalid path.'];
        }
        $dir = dirname($dst);
        if (!is_dir($dir) && !@mkdir($dir, 0755, true)) {
            return ['ok' => false, 'error' => 'Cannot create CDN directory: ' . $dir];
        }
        $this->ensureRootSecurityFiles();

        $bytes = @file_get_contents($localAbsPath);
        if ($bytes === false) {
            return ['ok' => false, 'error' => 'Failed reading local file.'];
        }
        if (strtolower(pathinfo($dst, PATHINFO_EXTENSION)) === 'svg') {
            $bytes = media_sanitize_svg($bytes);
        }
        $tmp = $dst . '.tmp.' . bin2hex(random_bytes(4));
        if (@file_put_contents($tmp, $bytes, LOCK_EX) === false || !@rename($tmp, $dst)) {
            @unlink($tmp);
            return ['ok' => false, 'error' => 'Failed writing file to CDN path.'];
        }
        @chmod($dst, 0644);
        return ['ok' => true, 'error' => ''];
    }

    public function delete(string $relPath): array
    {
        $dst = $this->absTarget($relPath);
        if ($dst === null) {
            return ['ok' => false, 'error' => 'CDN filesystem path not configured.'];
        }
        if (is_file($dst) && !@unlink($dst)) {
            return ['ok' => false, 'error' => 'Could not unlink file on CDN path.'];
        }
        return ['ok' => true, 'error' => ''];
    }

    public function exists(string $relPath): bool
    {
        $dst = $this->absTarget($relPath);
        return $dst !== null && is_file($dst);
    }

    public function pull(string $relPath, string $localAbsPath): array
    {
        $src = $this->absTarget($relPath);
        if ($src === null || !is_file($src)) {
            return ['ok' => false, 'error' => 'File not found on CDN filesystem path.'];
        }
        $dir = dirname($localAbsPath);
        if (!is_dir($dir) && !@mkdir($dir, 0755, true)) {
            return ['ok' => false, 'error' => 'Cannot create local directory.'];
        }
        if (!@copy($src, $localAbsPath)) {
            return ['ok' => false, 'error' => 'Failed copying file from CDN to local /uploads.'];
        }
        return ['ok' => true, 'error' => ''];
    }
}

class BookexaCdnFtpDriver implements BookexaCdnDriverInterface
{
    private array $ftp;

    public function __construct(array $cfg)
    {
        $this->ftp = $cfg['ftp'] ?? [];
    }

    private function connect()
    {
        if (!function_exists('ftp_connect')) {
            throw new RuntimeException('PHP FTP extension is not installed.');
        }
        $host = trim((string) ($this->ftp['host'] ?? ''));
        $port = max(1, (int) ($this->ftp['port'] ?? 21));
        $user = (string) ($this->ftp['user'] ?? '');
        $pass = (string) ($this->ftp['pass'] ?? '');
        $ssl  = !empty($this->ftp['ssl']);
        if ($host === '' || $user === '') {
            throw new RuntimeException('FTP host or username missing.');
        }
        $conn = ($ssl && function_exists('ftp_ssl_connect'))
            ? @ftp_ssl_connect($host, $port, 10)
            : @ftp_connect($host, $port, 10);
        if (!$conn) {
            throw new RuntimeException("FTP connection failed to {$host}:{$port}");
        }
        if (!@ftp_login($conn, $user, $pass)) {
            @ftp_close($conn);
            throw new RuntimeException('FTP authentication failed.');
        }
        @ftp_pasv($conn, true);
        return $conn;
    }

    private function remotePath(string $relPath): string
    {
        $root = '/' . trim(str_replace('\\', '/', (string) ($this->ftp['root'] ?? '/')), '/');
        if ($root === '/') {
            return '/' . ltrim($relPath, '/');
        }
        return $root . '/' . ltrim($relPath, '/');
    }

    private function ensureRemoteDirs($conn, string $remoteDir): void
    {
        $parts = array_filter(explode('/', trim($remoteDir, '/')));
        $curr = '';
        foreach ($parts as $p) {
            $curr .= '/' . $p;
            if (@ftp_chdir($conn, $curr) === false) {
                @ftp_mkdir($conn, $curr);
            }
        }
        @ftp_chdir($conn, '/');
    }

    public function put(string $relPath, string $localAbsPath): array
    {
        $clean = media_validate_rel_path($relPath);
        if ($clean === null || !is_file($localAbsPath)) {
            return ['ok' => false, 'error' => 'Invalid source file.'];
        }
        try {
            $conn = $this->connect();
            $remote = $this->remotePath($clean);
            $this->ensureRemoteDirs($conn, dirname($remote));
            $ok = @ftp_put($conn, $remote, $localAbsPath, FTP_BINARY);
            @ftp_close($conn);
            return $ok ? ['ok' => true, 'error' => ''] : ['ok' => false, 'error' => 'ftp_put failed for ' . $clean];
        } catch (Throwable $e) {
            return ['ok' => false, 'error' => $e->getMessage()];
        }
    }

    public function delete(string $relPath): array
    {
        $clean = media_validate_rel_path($relPath);
        if ($clean === null) {
            return ['ok' => false, 'error' => 'Invalid path.'];
        }
        try {
            $conn = $this->connect();
            $remote = $this->remotePath($clean);
            if (@ftp_size($conn, $remote) >= 0) {
                @ftp_delete($conn, $remote);
            }
            @ftp_close($conn);
            return ['ok' => true, 'error' => ''];
        } catch (Throwable $e) {
            return ['ok' => false, 'error' => $e->getMessage()];
        }
    }

    public function exists(string $relPath): bool
    {
        $clean = media_validate_rel_path($relPath);
        if ($clean === null) {
            return false;
        }
        try {
            $conn = $this->connect();
            $size = @ftp_size($conn, $this->remotePath($clean));
            @ftp_close($conn);
            return $size >= 0;
        } catch (Throwable $e) {
            return false;
        }
    }

    public function pull(string $relPath, string $localAbsPath): array
    {
        $clean = media_validate_rel_path($relPath);
        if ($clean === null) {
            return ['ok' => false, 'error' => 'Invalid path.'];
        }
        $dir = dirname($localAbsPath);
        if (!is_dir($dir)) {
            @mkdir($dir, 0755, true);
        }
        try {
            $conn = $this->connect();
            $ok = @ftp_get($conn, $localAbsPath, $this->remotePath($clean), FTP_BINARY);
            @ftp_close($conn);
            return $ok ? ['ok' => true, 'error' => ''] : ['ok' => false, 'error' => 'ftp_get failed for ' . $clean];
        } catch (Throwable $e) {
            return ['ok' => false, 'error' => $e->getMessage()];
        }
    }
}

class BookexaCdnHttpDriver implements BookexaCdnDriverInterface
{
    private string $endpoint;
    private string $secret;
    private string $baseUrl;

    public function __construct(array $cfg)
    {
        $this->endpoint = trim((string) ($cfg['http']['endpoint'] ?? ''));
        $this->secret   = (string) ($cfg['http']['secret'] ?? '');
        $this->baseUrl  = rtrim((string) ($cfg['base_url'] ?? ''), '/');
    }

    private function postSigned(string $action, string $relPath, ?string $localAbsPath = null): array
    {
        if ($this->endpoint === '' || $this->secret === '') {
            return ['ok' => false, 'error' => 'HTTP CDN endpoint or secret missing.'];
        }
        if (!function_exists('curl_init')) {
            return ['ok' => false, 'error' => 'PHP cURL extension missing.'];
        }
        $ts = time();
        $nonce = bin2hex(random_bytes(16));
        $sha1 = ($localAbsPath && is_file($localAbsPath)) ? (string) sha1_file($localAbsPath) : '';
        $sig = hash_hmac('sha256', "{$ts}|{$nonce}|{$action}|{$relPath}|{$sha1}", $this->secret);

        $fields = ['ts' => $ts, 'nonce' => $nonce, 'action' => $action, 'rel_path' => $relPath, 'sha1' => $sha1, 'sig' => $sig];
        if ($action === 'put' && $localAbsPath !== null) {
            $fields['file'] = new CURLFile($localAbsPath);
        }

        $ch = curl_init($this->endpoint);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $fields,
            CURLOPT_TIMEOUT        => 20,
        ]);
        $raw = (string) curl_exec($ch);
        $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err  = curl_error($ch);
        curl_close($ch);

        if ($err !== '') {
            return ['ok' => false, 'error' => 'HTTP CDN error: ' . $err];
        }
        $json = json_decode($raw, true);
        if ($code >= 200 && $code < 300 && !empty($json['ok'])) {
            return ['ok' => true, 'exists' => !empty($json['exists']), 'error' => ''];
        }
        return ['ok' => false, 'error' => ($json['message'] ?? "HTTP {$code}")];
    }

    public function put(string $relPath, string $localAbsPath): array
    {
        $clean = media_validate_rel_path($relPath);
        return $clean ? $this->postSigned('put', $clean, $localAbsPath) : ['ok' => false, 'error' => 'Invalid path.'];
    }

    public function delete(string $relPath): array
    {
        $clean = media_validate_rel_path($relPath);
        return $clean ? $this->postSigned('delete', $clean) : ['ok' => false, 'error' => 'Invalid path.'];
    }

    public function exists(string $relPath): bool
    {
        $clean = media_validate_rel_path($relPath);
        if ($clean === null) {
            return false;
        }
        $res = $this->postSigned('exists', $clean);
        if ($res['ok']) {
            return !empty($res['exists']);
        }
        if ($this->baseUrl !== '' && function_exists('curl_init')) {
            $ch = curl_init($this->baseUrl . '/' . $clean);
            curl_setopt_array($ch, [CURLOPT_NOBODY => true, CURLOPT_TIMEOUT => 8, CURLOPT_RETURNTRANSFER => true]);
            curl_exec($ch);
            $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            return $code >= 200 && $code < 300;
        }
        return false;
    }

    public function pull(string $relPath, string $localAbsPath): array
    {
        $clean = media_validate_rel_path($relPath);
        if ($clean === null || $this->baseUrl === '' || !function_exists('curl_init')) {
            return ['ok' => false, 'error' => 'Cannot pull: invalid CDN URL or missing cURL.'];
        }
        $ch = curl_init($this->baseUrl . '/' . $clean);
        curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 20, CURLOPT_FOLLOWLOCATION => true]);
        $bytes = curl_exec($ch);
        $code  = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        if ($code < 200 || $code >= 300 || !is_string($bytes) || $bytes === '') {
            return ['ok' => false, 'error' => "CDN fetch failed (HTTP {$code})."];
        }
        $dir = dirname($localAbsPath);
        if (!is_dir($dir)) {
            @mkdir($dir, 0755, true);
        }
        return @file_put_contents($localAbsPath, $bytes, LOCK_EX) !== false
            ? ['ok' => true, 'error' => '']
            : ['ok' => false, 'error' => 'Failed saving pulled file locally.'];
    }
}

function cdn_driver(?array $overrideCfg = null): BookexaCdnDriverInterface
{
    $cfg = $overrideCfg ?? cdn_config();
    return match ($cfg['driver'] ?? 'filesystem') {
        'ftp'   => new BookexaCdnFtpDriver($cfg),
        'http'  => new BookexaCdnHttpDriver($cfg),
        default => new BookexaCdnFilesystemDriver($cfg),
    };
}

// ============================================================================
// Sync Map Cache (fast lookup for asset() — 1 file / query per request)
// ============================================================================
function media_cache_file(): string
{
    return APP_ROOT . '/storage/cache/media_sync_map.php';
}

function media_invalidate_map_cache(): void
{
    @unlink(media_cache_file());
    $GLOBALS['__bx_media_sync_map'] = null;
}

function media_sync_map(): array
{
    if (isset($GLOBALS['__bx_media_sync_map']) && is_array($GLOBALS['__bx_media_sync_map'])) {
        return $GLOBALS['__bx_media_sync_map'];
    }
    $cachePath = media_cache_file();
    if (is_file($cachePath) && (time() - (int) @filemtime($cachePath)) < 300) {
        $loaded = @include $cachePath;
        if (is_array($loaded)) {
            return $GLOBALS['__bx_media_sync_map'] = $loaded;
        }
    }
    $map = [];
    try {
        foreach (db_all('SELECT rel_path FROM media_sync WHERE cdn_synced = 1') as $row) {
            $map[(string) $row['rel_path']] = true;
        }
        $dir = dirname($cachePath);
        if (!is_dir($dir)) {
            @mkdir($dir, 0755, true);
        }
        @file_put_contents($cachePath, "<?php\nreturn " . var_export($map, true) . ";\n", LOCK_EX);
    } catch (Throwable $e) {
    }
    return $GLOBALS['__bx_media_sync_map'] = $map;
}

function media_is_cdn_synced(string $relPath): bool
{
    $clean = media_validate_rel_path($relPath);
    if ($clean === null) {
        return false;
    }
    $map = media_sync_map();
    return isset($map[$clean]);
}

// ============================================================================
// Registration, Single Push, Lazy Queue & Bulk Operations
// ============================================================================
function media_register_local_file(string $relPath): ?array
{
    $clean = media_validate_rel_path($relPath);
    if ($clean === null) {
        return null;
    }
    $abs = APP_ROOT . '/' . $clean;
    if (!is_file($abs)) {
        return null;
    }
    $size = (int) @filesize($abs);
    $sha1 = (string) (@sha1_file($abs) ?: '');

    $existing = db_one('SELECT id, sha1, cdn_synced FROM media_sync WHERE rel_path = ? LIMIT 1', [$clean]);
    if (!$existing) {
        db_exec(
            'INSERT INTO media_sync (rel_path, local_exists, cdn_synced, attempts, size, sha1) VALUES (?, 1, 0, 0, ?, ?)',
            [$clean, $size, $sha1]
        );
    } else {
        $changed = ($existing['sha1'] ?? '') !== $sha1;
        db_exec(
            'UPDATE media_sync SET local_exists = 1, size = ?, sha1 = ?' . ($changed ? ', cdn_synced = 0, cdn_synced_at = NULL' : '') . ' WHERE id = ?',
            [$size, $sha1, (int) $existing['id']]
        );
    }
    media_invalidate_map_cache();
    return db_one('SELECT * FROM media_sync WHERE rel_path = ?', [$clean]);
}

function media_sync_push_one(string $relPath): array
{
    $clean = media_validate_rel_path($relPath);
    if ($clean === null) {
        return ['ok' => false, 'error' => 'Invalid path.'];
    }
    $abs = APP_ROOT . '/' . $clean;
    if (!is_file($abs)) {
        db_exec('UPDATE media_sync SET local_exists = 0 WHERE rel_path = ?', [$clean]);
        media_invalidate_map_cache();
        return ['ok' => false, 'error' => 'Local file missing.'];
    }

    media_register_local_file($clean);
    $cfg = cdn_config();
    if (!$cfg['enabled']) {
        return ['ok' => false, 'skipped' => true, 'error' => 'CDN is disabled in config/config.php.'];
    }

    $res = cdn_driver($cfg)->put($clean, $abs);
    if ($res['ok']) {
        db_exec(
            'UPDATE media_sync SET local_exists = 1, cdn_synced = 1, cdn_synced_at = NOW(), last_error = NULL, attempts = attempts + 1 WHERE rel_path = ?',
            [$clean]
        );
        media_invalidate_map_cache();
        return ['ok' => true, 'error' => ''];
    }

    db_exec(
        'UPDATE media_sync SET cdn_synced = 0, last_error = ?, attempts = attempts + 1 WHERE rel_path = ?',
        [substr((string) ($res['error'] ?? 'Push failed'), 0, 250), $clean]
    );
    media_invalidate_map_cache();
    return ['ok' => false, 'error' => (string) ($res['error'] ?? 'Push failed')];
}

function media_scan_local_uploads(): int
{
    $added = 0;
    foreach (media_allowed_subdirs() as $sub) {
        $dir = APP_ROOT . '/uploads/' . $sub;
        if (!is_dir($dir)) {
            continue;
        }
        foreach (scandir($dir) ?: [] as $f) {
            if ($f === '.' || $f === '..' || str_contains($f, '.tmp.')) {
                continue;
            }
            $rel = 'uploads/' . $sub . '/' . $f;
            if (media_validate_rel_path($rel) === null) {
                continue;
            }
            $existed = db_one('SELECT id FROM media_sync WHERE rel_path = ? LIMIT 1', [$rel]);
            if (media_register_local_file($rel) !== null && !$existed) {
                $added++;
            }
        }
    }

    // Mark rows whose local file disappeared
    foreach (db_all('SELECT id, rel_path FROM media_sync WHERE local_exists = 1') as $row) {
        if (!is_file(APP_ROOT . '/' . $row['rel_path'])) {
            db_exec('UPDATE media_sync SET local_exists = 0 WHERE id = ?', [(int) $row['id']]);
        }
    }
    return $added;
}

function media_sync_pending_batch(int $limit = 10, bool $retryFailed = false): array
{
    $cfg = cdn_config();
    if (!$cfg['enabled']) {
        return ['ok' => false, 'processed' => 0, 'synced' => 0, 'failed' => 0, 'remaining' => 0, 'message' => 'CDN is currently disabled.'];
    }
    $sql = "SELECT rel_path FROM media_sync WHERE local_exists = 1 AND cdn_synced = 0";
    if ($retryFailed) {
        $sql .= " AND last_error IS NOT NULL";
    }
    $sql .= " ORDER BY attempts ASC, id ASC LIMIT " . max(1, min(100, $limit));
    $rows = db_all($sql);

    $synced = 0;
    $failed = 0;
    foreach ($rows as $r) {
        $res = media_sync_push_one((string) $r['rel_path']);
        if ($res['ok']) {
            $synced++;
        } else {
            $failed++;
        }
    }
    $remaining = (int) (db_one('SELECT COUNT(*) AS c FROM media_sync WHERE local_exists = 1 AND cdn_synced = 0')['c'] ?? 0);
    return ['ok' => true, 'processed' => count($rows), 'synced' => $synced, 'failed' => $failed, 'remaining' => $remaining];
}

function media_lazy_sync_tick(): void
{
    $stampFile = APP_ROOT . '/storage/cache/media_lazy_tick.txt';
    $last = is_file($stampFile) ? (int) @file_get_contents($stampFile) : 0;
    if ((time() - $last) < 20) {
        return;
    }
    @file_put_contents($stampFile, (string) time(), LOCK_EX);

    try {
        media_scan_local_uploads();
        $cfg = cdn_config();
        if ($cfg['enabled']) {
            media_sync_pending_batch($cfg['sync_batch']);
        }
    } catch (Throwable $e) {
    }
}

function media_test_cdn_connection(): array
{
    $cfg = cdn_config();
    if (!$cfg['enabled']) {
        return ['ok' => false, 'message' => 'Enable CDN in config/config.php first before testing.'];
    }
    $probeRel = 'uploads/branding/cdn-probe-' . bin2hex(random_bytes(4)) . '.png';
    $tmpLocal = APP_ROOT . '/storage/cache/probe_' . bin2hex(random_bytes(4)) . '.png';
    if (!is_dir(dirname($tmpLocal))) {
        @mkdir(dirname($tmpLocal), 0755, true);
    }
    // 1x1 transparent valid PNG
    $png1x1 = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==');
    file_put_contents($tmpLocal, $png1x1);

    $driver = cdn_driver($cfg);
    $putRes = $driver->put($probeRel, $tmpLocal);
    @unlink($tmpLocal);
    if (!$putRes['ok']) {
        return ['ok' => false, 'message' => 'Write failed: ' . ($putRes['error'] ?: 'Unknown error')];
    }
    $exists = $driver->exists($probeRel);
    $delRes = $driver->delete($probeRel);
    if (!$exists) {
        return ['ok' => false, 'message' => 'Uploaded probe could not be verified on CDN.'];
    }
    if (!$delRes['ok']) {
        return ['ok' => false, 'message' => 'Cleanup delete failed: ' . $delRes['error']];
    }
    return ['ok' => true, 'message' => "CDN driver ({$cfg['driver']}) write, verify, and delete succeeded!"];
}

function media_recheck_cdn_all(): array
{
    $cfg = cdn_config();
    if (!$cfg['enabled']) {
        return ['ok' => false, 'message' => 'CDN is disabled.'];
    }
    $driver = cdn_driver($cfg);
    $rows = db_all('SELECT id, rel_path, cdn_synced FROM media_sync');
    $present = 0;
    $missing = 0;
    foreach ($rows as $r) {
        $exists = $driver->exists((string) $r['rel_path']);
        if ($exists) {
            $present++;
            if (!(int) $r['cdn_synced']) {
                db_exec('UPDATE media_sync SET cdn_synced = 1, cdn_synced_at = NOW(), last_error = NULL WHERE id = ?', [(int) $r['id']]);
            }
        } else {
            $missing++;
            if ((int) $r['cdn_synced']) {
                db_exec("UPDATE media_sync SET cdn_synced = 0, last_error = 'Missing on CDN during recheck' WHERE id = ?", [(int) $r['id']]);
            }
        }
    }
    media_invalidate_map_cache();
    return ['ok' => true, 'present' => $present, 'missing' => $missing];
}

function media_pull_all_from_cdn(): array
{
    $cfg = cdn_config();
    if (!$cfg['enabled']) {
        return ['ok' => false, 'message' => 'CDN is disabled.'];
    }
    $driver = cdn_driver($cfg);

    // If filesystem driver, discover files present on CDN even if DB doesn't list them yet
    if ($driver instanceof BookexaCdnFilesystemDriver) {
        $base = $driver->targetBaseDir();
        if ($base !== '') {
            foreach (media_allowed_subdirs() as $sub) {
                $cdnDir = $base . '/uploads/' . $sub;
                if (!is_dir($cdnDir)) {
                    continue;
                }
                foreach (scandir($cdnDir) ?: [] as $f) {
                    if ($f === '.' || $f === '..') {
                        continue;
                    }
                    $rel = 'uploads/' . $sub . '/' . $f;
                    if (media_validate_rel_path($rel) !== null) {
                        db_exec(
                            'INSERT INTO media_sync (rel_path, local_exists, cdn_synced, cdn_synced_at) VALUES (?, 0, 1, NOW()) ON DUPLICATE KEY UPDATE cdn_synced = 1',
                            [$rel]
                        );
                    }
                }
            }
        }
    }

    $pulled = 0;
    $failed = 0;
    foreach (db_all('SELECT rel_path FROM media_sync WHERE local_exists = 0 OR cdn_synced = 1') as $r) {
        $rel = (string) $r['rel_path'];
        $localAbs = APP_ROOT . '/' . $rel;
        if (is_file($localAbs)) {
            continue;
        }
        $res = $driver->pull($rel, $localAbs);
        if ($res['ok']) {
            media_register_local_file($rel);
            db_exec('UPDATE media_sync SET local_exists = 1, cdn_synced = 1 WHERE rel_path = ?', [$rel]);
            $pulled++;
        } else {
            $failed++;
        }
    }
    media_invalidate_map_cache();
    return ['ok' => true, 'pulled' => $pulled, 'failed' => $failed];
}

// ============================================================================
// Reference-Counted Safe Delete (Local + CDN) on Delete or Replace
// ============================================================================
function media_count_db_references(string $relPath): int
{
    $clean = media_validate_rel_path($relPath);
    if ($clean === null) {
        return 1;
    }
    $cnt = 0;
    $cnt += (int) (db_one('SELECT COUNT(*) AS c FROM banners WHERE image = ? OR mobile_image = ?', [$clean, $clean])['c'] ?? 0);
    $cnt += (int) (db_one('SELECT COUNT(*) AS c FROM folders WHERE thumbnail = ?', [$clean])['c'] ?? 0);
    $cnt += (int) (db_one('SELECT COUNT(*) AS c FROM items WHERE cover = ?', [$clean])['c'] ?? 0);
    $cnt += (int) (db_one("SELECT COUNT(*) AS c FROM settings WHERE setting_key IN ('site_logo','site_favicon') AND setting_value = ?", [$clean])['c'] ?? 0);
    return $cnt;
}

function media_try_delete_unused_image(?string $oldRelPath): void
{
    if ($oldRelPath === null || trim($oldRelPath) === '') {
        return;
    }
    $clean = media_validate_rel_path($oldRelPath);
    if ($clean === null) {
        return;
    }
    if (media_count_db_references($clean) > 0) {
        return; // Still referenced by another record (e.g. duplicated PDF or seeded cover)
    }

    $localAbs = APP_ROOT . '/' . $clean;
    if (is_file($localAbs)) {
        @unlink($localAbs);
    }

    $cfg = cdn_config();
    if ($cfg['enabled']) {
        cdn_driver($cfg)->delete($clean);
    }

    db_exec('DELETE FROM media_sync WHERE rel_path = ?', [$clean]);
    media_invalidate_map_cache();
}

// ============================================================================
// Rotating Admin-Set Cron Key (Media Sync Authentication)
// ============================================================================
function cron_sync_secret(): string
{
    try {
        $row = db_one("SELECT setting_value FROM settings WHERE setting_key = 'cron_sync_secret' LIMIT 1");
        $val = trim((string) ($row['setting_value'] ?? ''));
        if ($val !== '') {
            return $val;
        }
        $fresh = bin2hex(random_bytes(24));
        db_exec(
            'INSERT INTO settings (setting_key, setting_value) VALUES ("cron_sync_secret", ?) ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)',
            [$fresh]
        );
        return $fresh;
    } catch (Throwable $e) {
        return '';
    }
}

function regenerate_cron_sync_secret(): string
{
    $fresh = bin2hex(random_bytes(24));
    db_exec(
        'INSERT INTO settings (setting_key, setting_value) VALUES ("cron_sync_secret", ?) ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)',
        [$fresh]
    );
    return $fresh;
}

function cron_sync_token(): string
{
    $appSecret = (string) app_config('security.app_secret', '');
    $rotSecret = cron_sync_secret();
    return hash_hmac('sha256', 'cron_sync_media|' . $rotSecret, $appSecret);
}
