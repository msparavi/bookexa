<?php
declare(strict_types=1);

function e(?string $str): string
{
    return htmlspecialchars((string) $str, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function json_response(array $payload, int $status = 200): never
{
    http_response_code($status);
    header('Content-Type: application/json; charset=UTF-8');
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function render_public(string $viewFile, array $vars = []): never
{
    if (!empty($vars['robotsNoIndex'])) {
        header('X-Robots-Tag: noindex, nofollow');
    }
    extract($vars, EXTR_SKIP);
    require APP_ROOT . '/includes/header.php';
    require APP_ROOT . '/pages/' . $viewFile;
    require APP_ROOT . '/includes/footer.php';
    exit;
}

function bookexa_version(): string
{
    return (string) app_config('app_version', '2.4.0');
}

function is_demo_mode(): bool
{
    if (!empty(app_config('demo_mode', false))) {
        return true;
    }
    return site_setting('demo_mode', '0') === '1';
}

function sanitize_hex_color(string $raw, string $default = '#2B2F77'): string
{
    $raw = strtoupper(trim($raw));
    if (preg_match('/^#[0-9A-F]{6}$/', $raw)) {
        return $raw;
    }
    return $default;
}

function sanitize_social_url(string $raw): string
{
    $raw = trim($raw);
    if ($raw === '') {
        return '';
    }
    if (!preg_match('#^https?://#i', $raw)) {
        return '';
    }
    $valid = filter_var($raw, FILTER_VALIDATE_URL);
    return $valid !== false ? $valid : '';
}

function theme_font_stack_pair(?string $pair = null): array
{
    $pair = $pair ?? site_setting('theme_font_pair', 'hind_noto');
    return match ($pair) {
        'system_clean' => [
            'label' => 'Clean System Sans (Zero Webfonts)',
            'sans'  => "system-ui, -apple-system, 'Segoe UI', Roboto, sans-serif",
            'serif' => "Georgia, 'Noto Serif Bengali Fallback', serif",
        ],
        'editorial_serif' => [
            'label' => 'Editorial Serif Dominant',
            'sans'  => "'Hind Siliguri', 'Hind Siliguri Fallback', system-ui, sans-serif",
            'serif' => "'Noto Serif Bengali', 'Noto Serif Bengali Fallback', Georgia, 'Times New Roman', serif",
        ],
        'bengali_modern' => [
            'label' => 'Bengali Modern (Hind Siliguri Everywhere)',
            'sans'  => "'Hind Siliguri', 'Hind Siliguri Fallback', system-ui, -apple-system, sans-serif",
            'serif' => "'Hind Siliguri', 'Hind Siliguri Fallback', Georgia, sans-serif",
        ],
        default => [
            'label' => 'Bookexa Classic (Hind Siliguri Display & Body)',
            'sans'  => "'Hind Siliguri', 'Hind Siliguri Fallback', system-ui, -apple-system, sans-serif",
            'serif' => "'Hind Siliguri', 'Hind Siliguri Fallback', 'Noto Serif Bengali Fallback', Georgia, serif",
        ],
    };
}

function theme_css_version_hash(): string
{
    return substr(md5(
        site_setting('theme_primary_color', '#2B2F77')
        . '|' . site_setting('theme_accent_color', '#F4A62A')
        . '|' . site_setting('theme_font_pair', 'hind_noto')
    ), 0, 8);
}

function app_secret(): string
{
    static $cachedSecret = null;
    if ($cachedSecret !== null) {
        return $cachedSecret;
    }
    $secret = trim((string) app_config('security.app_secret', ''));
    if ($secret === '' || strlen($secret) < 32 || $secret === 'CHANGE_ME_ON_INSTALL') {
        $msg = '[' . date('c') . '] CRITICAL: Missing or weak security.app_secret in config/config.php (must be >= 32 chars and not CHANGE_ME_ON_INSTALL).';
        @error_log($msg . "\n");
        if (!headers_sent()) {
            http_response_code(500);
            header('Content-Type: text/html; charset=UTF-8');
        }
        echo '<!DOCTYPE html><html lang="en"><head><meta charset="UTF-8"><title>500 Configuration Error</title></head><body style="font-family:sans-serif;padding:36px;max-width:600px;margin:0 auto;"><h1>500 Internal Server Error</h1><p>Application security key is missing or misconfigured. Please contact the site administrator.</p></body></html>';
        exit(1);
    }
    $cachedSecret = $secret;
    return $cachedSecret;
}

function safe_internal_path(?string $raw, string $fallback = '/library'): string
{
    if ($raw === null) {
        return $fallback;
    }
    $raw = trim($raw);
    if ($raw === '') {
        return $fallback;
    }
    // Check raw string plus multiple URL-decoding passes to defeat encoded traversal/protocol tricks
    $probe = $raw;
    for ($i = 0; $i < 3; $i++) {
        if (preg_match('/[\x00-\x1F\x7F\\\\]/', $probe) || str_contains($probe, '\\')) {
            return $fallback;
        }
        if (str_starts_with($probe, '//')) {
            return $fallback;
        }
        $beforeQuery = strstr($probe, '?', true);
        $pathOnlyCheck = $beforeQuery !== false ? $beforeQuery : $probe;
        if (preg_match('/^[a-zA-Z][a-zA-Z0-9+.-]*:/', $pathOnlyCheck) || str_contains($pathOnlyCheck, '://')) {
            return $fallback;
        }
        if (!str_starts_with($probe, '/')) {
            return $fallback;
        }
        $decoded = rawurldecode($probe);
        if ($decoded === $probe) {
            break;
        }
        $probe = $decoded;
    }

    $parts = @parse_url($raw);
    if (
        !is_array($parts)
        || !empty($parts['scheme'])
        || !empty($parts['host'])
        || !empty($parts['user'])
        || !empty($parts['pass'])
    ) {
        return $fallback;
    }

    $path = (string) ($parts['path'] ?? '');
    if ($path === '' || $path[0] !== '/' || str_starts_with($path, '//') || str_contains($path, '\\')) {
        return $fallback;
    }

    if (defined('BASE_PATH') && BASE_PATH !== '') {
        if ($path === BASE_PATH) {
            $path = '/';
        } elseif (str_starts_with($path, BASE_PATH . '/')) {
            $path = substr($path, strlen(BASE_PATH));
        }
    }

    $result = $path;
    if (!empty($parts['query'])) {
        $result .= '?' . $parts['query'];
    }
    if (!empty($parts['fragment'])) {
        $result .= '#' . $parts['fragment'];
    }
    return $result;
}

function escape_like(string $str): string
{
    return strtr($str, ['\\' => '\\\\', '%' => '\\%', '_' => '\\_']);
}

function split_path_and_query(string $path): array
{
    $qPos = strpos($path, '?');
    $cleanPath = $qPos === false ? $path : substr($path, 0, $qPos);
    $queryPart = $qPos === false ? '' : substr($path, $qPos);
    $norm = '/' . ltrim($cleanPath, '/');
    if ($norm === '/en' || str_starts_with($norm, '/en/')) {
        $norm = '/' . ltrim(substr($norm, 3), '/');
    }
    if ($norm === '' || $norm === '//') {
        $norm = '/';
    }
    return [$norm, $queryPart];
}

function url_for_lang(string $path, string $targetLang): string
{
    [$norm, $query] = split_path_and_query($path);
    if ($targetLang === 'en' && function_exists('is_bilingual_public_route') && is_bilingual_public_route($norm)) {
        $enPath = '/en' . ($norm === '/' ? '' : $norm);
        return BASE_PATH . $enPath . $query;
    }
    return BASE_PATH . $norm . $query;
}

function absolute_url_for_lang(string $path, string $targetLang): string
{
    [$norm, $query] = split_path_and_query($path);
    if ($targetLang === 'en' && function_exists('is_bilingual_public_route') && is_bilingual_public_route($norm)) {
        return BASE_URL . '/en' . ($norm === '/' ? '' : $norm) . $query;
    }
    return BASE_URL . $norm . $query;
}

function url(string $path = '/', ?string $forceLang = null): string
{
    [$norm, $query] = split_path_and_query($path);
    if ($forceLang !== null) {
        return url_for_lang($path, $forceLang);
    }
    if (function_exists('is_bilingual_public_route') && is_bilingual_public_route($norm) && function_exists('current_lang') && current_lang() === 'en') {
        return url_for_lang($path, 'en');
    }
    return BASE_PATH . $norm . $query;
}

function absolute_asset_url(string $path): string
{
    $resolved = asset($path);
    if (str_starts_with($resolved, 'http://') || str_starts_with($resolved, 'https://')) {
        return $resolved;
    }
    if (BASE_PATH !== '' && str_starts_with($resolved, BASE_PATH . '/')) {
        $resolved = substr($resolved, strlen(BASE_PATH));
    }
    return BASE_URL . '/' . ltrim($resolved, '/');
}

function asset(string $path): string
{
    $clean = ltrim($path, '/');
    if (str_starts_with($clean, 'uploads/') && function_exists('cdn_config') && function_exists('media_is_cdn_synced')) {
        $cdn = cdn_config();
        if (!empty($cdn['enabled']) && !empty($cdn['base_url']) && media_is_cdn_synced($clean)) {
            return rtrim((string) $cdn['base_url'], '/') . '/' . $clean;
        }
    }
    $base = BASE_PATH . '/' . $clean;
    if (preg_match('/\.(css|js|webmanifest|svg)$/i', $clean)) {
        $abs = APP_ROOT . '/' . $clean;
        if (is_file($abs)) {
            $ver = @filemtime($abs);
            if ($ver !== false) {
                return $base . '?v=' . $ver;
            }
        }
    }
    return $base;
}

function render_error_page(int $errCode, ?string $errTitle = null, ?string $errDesc = null): never
{
    http_response_code($errCode);
    if ($errCode === 503) {
        header('Retry-After: 3600');
        header('X-Robots-Tag: noindex, nofollow');
    } elseif ($errCode === 404 || $errCode === 410) {
        header('X-Robots-Tag: noindex, nofollow');
        if ($errCode === 404 && function_exists('seo_log_404')) {
            seo_log_404((string) ($_SERVER['REQUEST_URI'] ?? '/'));
        }
    }
    $pageTitle = (string) $errCode;
    $robotsNoIndex = true;
    $activeNav = '';
    if ($errCode !== 500 && function_exists('current_lang') && is_file(APP_ROOT . '/includes/header.php')) {
        require APP_ROOT . '/includes/header.php';
        require APP_ROOT . '/pages/error.php';
        require APP_ROOT . '/includes/footer.php';
    } else {
        echo '<!DOCTYPE html><html lang="en" data-theme="light"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="robots" content="noindex, nofollow"><title>' . $errCode . ' — Bookexa</title><link rel="stylesheet" href="' . e(asset('assets/css/tokens.css')) . '"><link rel="stylesheet" href="' . e(asset('assets/css/components.css')) . '"></head><body><main class="bx-main">';
        require APP_ROOT . '/pages/error.php';
        echo '</main></body></html>';
    }
    exit;
}

function redirect(string $path): never
{
    $target = (str_starts_with($path, 'http://') || str_starts_with($path, 'https://')) ? $path : url($path);
    header('Location: ' . $target);
    exit;
}

function flash(string $type, string $message): void
{
    $_SESSION['_flashes'][] = ['type' => $type, 'message' => $message];
}

function get_flashes(): array
{
    $flashes = $_SESSION['_flashes'] ?? [];
    unset($_SESSION['_flashes']);
    return $flashes;
}

function client_ip(): string
{
    return substr((string) ($_SERVER['REMOTE_ADDR'] ?? '127.0.0.1'), 0, 45);
}

function slugify(string $text): string
{
    if (function_exists('seo_transliterate_bn_to_ascii')) {
        $text = seo_transliterate_bn_to_ascii($text);
    }
    $text = strtolower(trim($text));
    $text = preg_replace('/[^a-z0-9\s-]+/', '', $text) ?? '';
    $text = preg_replace('/[\s-]+/', '-', $text) ?? '';
    $text = trim($text, '-');
    return $text !== '' ? $text : ('item-' . substr(bin2hex(random_bytes(4)), 0, 6));
}

function site_setting_store_ref(bool $reset = false): array
{
    static $cache = null;
    if ($reset) {
        $cache = null;
        return [];
    }
    if ($cache === null) {
        $cache = [];
        try {
            $rows = db_all('SELECT setting_key, setting_value FROM settings');
            foreach ($rows as $r) {
                $cache[$r['setting_key']] = (string) $r['setting_value'];
            }
        } catch (Throwable $e) {
        }
    }
    return $cache;
}

function site_setting(string $key, string $default = '', bool $refresh = false): string
{
    if ($refresh) {
        site_setting_store_ref(true);
    }
    $cache = site_setting_store_ref(false);
    return ($cache[$key] ?? '') !== '' ? $cache[$key] : $default;
}

function site_setting_set(string $key, string $value): void
{
    db_exec(
        'INSERT INTO settings (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)',
        [$key, $value]
    );
    site_setting_store_ref(true);
}

function published_window_sql(string $alias = ''): string
{
    $p = $alias !== '' ? ($alias . '.') : '';
    return "({$p}publish_at IS NULL OR {$p}publish_at <= NOW()) AND ({$p}unpublish_at IS NULL OR {$p}unpublish_at > NOW())";
}

function ensure_bookexa_cache_dir(string $sub = ''): string
{
    $dir = APP_ROOT . '/storage/cache' . ($sub !== '' ? ('/' . trim($sub, '/')) : '');
    if (!is_dir($dir)) {
        @mkdir($dir, 0755, true);
    }
    $ht = $dir . '/.htaccess';
    if (!is_file($ht)) {
        @file_put_contents($ht, "<IfModule mod_authz_core.c>\n    Require all denied\n</IfModule>\n<IfModule !mod_authz_core.c>\n    Order allow,deny\n    Deny from all\n</IfModule>\n");
    }
    return $dir;
}

/**
 * Recompute stored subfolder_count and item_count on folders table in 1 aggregate query.
 */
function sync_folder_stored_counts(): void
{
    try {
        $pubC = published_window_sql();
        db_exec(
            "UPDATE folders f
             LEFT JOIN (
                 SELECT parent_id AS fid, COUNT(*) AS cnt
                 FROM folders
                 WHERE is_active = 1 AND parent_id IS NOT NULL AND {$pubC}
                 GROUP BY parent_id
             ) sc ON sc.fid = f.id
             LEFT JOIN (
                 SELECT folder_id AS fid, COUNT(*) AS cnt
                 FROM items
                 WHERE is_active = 1 AND folder_id IS NOT NULL AND {$pubC}
                 GROUP BY folder_id
             ) ic ON ic.fid = f.id
             SET f.subfolder_count = COALESCE(sc.cnt, 0),
                 f.item_count = COALESCE(ic.cnt, 0)"
        );
    } catch (Throwable $e) {
    }
}

function invalidate_folder_and_sitemap_cache(bool $recomputeCounts = true): void
{
    if ($recomputeCounts) {
        sync_folder_stored_counts();
    }
    $base = APP_ROOT . '/storage/cache';
    @unlink($base . '/folder_tree.json');
    foreach (glob($base . '/sitemap*.xml') ?: [] as $smFile) {
        @unlink($smFile);
    }
    foreach (glob($base . '/og/*.png') ?: [] as $ogFile) {
        @unlink($ogFile);
    }
    foreach (glob($base . '/llms*.txt') ?: [] as $llmsFile) {
        @unlink($llmsFile);
    }
    foreach (glob($base . '/aeo_*.json') ?: [] as $aeoCache) {
        @unlink($aeoCache);
    }
    if (function_exists('perf_clear_page_cache')) {
        perf_clear_page_cache();
    }
}

function folder_path_string(int $folderId): string
{
    $trail = [];
    $seen = [];
    $curr = $folderId;
    while ($curr !== null && !isset($seen[$curr])) {
        $seen[$curr] = true;
        $row = db_one('SELECT id, parent_id, slug FROM folders WHERE id = ? LIMIT 1', [$curr]);
        if (!$row) {
            break;
        }
        array_unshift($trail, (string) $row['slug']);
        $curr = $row['parent_id'] !== null ? (int) $row['parent_id'] : null;
    }
    return implode('/', $trail);
}

function record_slug_history(string $entityType, int $entityId, string $oldPath): void
{
    $clean = trim($oldPath, '/');
    if ($entityId <= 0 || $clean === '' || !in_array($entityType, ['folder', 'item'], true)) {
        return;
    }
    try {
        db_exec(
            'INSERT INTO slug_history (entity_type, entity_id, old_path) VALUES (?, ?, ?)
             ON DUPLICATE KEY UPDATE entity_id = VALUES(entity_id), created_at = NOW()',
            [$entityType, $entityId, $clean]
        );
    } catch (Throwable $e) {
    }
}

function prune_self_slug_history(string $entityType, string $currentPath): void
{
    $clean = trim($currentPath, '/');
    if ($clean === '' || !in_array($entityType, ['folder', 'item'], true)) {
        return;
    }
    try {
        db_exec('DELETE FROM slug_history WHERE entity_type = ? AND old_path = ?', [$entityType, $clean]);
    } catch (Throwable $e) {
    }
}

function folder_full_url_lang(int $folderId, string $targetLang): string
{
    $trail = folder_breadcrumbs($folderId);
    if (empty($trail)) {
        return url_for_lang('/pdf', $targetLang);
    }
    $slugs = array_map(fn($f) => rawurlencode($f['slug']), $trail);
    return url_for_lang('/pdf/' . implode('/', $slugs), $targetLang);
}

/**
 * Load cached active published folder tree + stored counts (5-minute TTL, invalidated on admin changes).
 */
function get_cached_folder_tree(bool $forceRefresh = false): array
{
    $cacheDir = ensure_bookexa_cache_dir();
    $cacheFile = $cacheDir . '/folder_tree.json';
    $ttl = 300; // 5 minutes

    if (!$forceRefresh && is_file($cacheFile)) {
        $mtime = @filemtime($cacheFile);
        if ($mtime !== false && (time() - $mtime) < $ttl) {
            $raw = @file_get_contents($cacheFile);
            $decoded = $raw ? json_decode($raw, true) : null;
            if (is_array($decoded) && isset($decoded['byId'], $decoded['children'])) {
                return $decoded;
            }
        }
    }

    $pubF = published_window_sql('f');
    $pubRaw = published_window_sql();
    $folders = db_all(
        "SELECT f.*
         FROM folders f
         WHERE f.is_active = 1 AND {$pubF}
         ORDER BY f.sort_order ASC, f.id ASC"
    );
    $itemCounts = [];
    foreach (db_all("SELECT folder_id, COUNT(*) AS c FROM items WHERE is_active = 1 AND folder_id IS NOT NULL AND {$pubRaw} GROUP BY folder_id") as $ic) {
        $itemCounts[(int) $ic['folder_id']] = (int) $ic['c'];
    }
    $subCounts = [];
    foreach ($folders as $f) {
        if ($f['parent_id'] !== null) {
            $pid = (int) $f['parent_id'];
            $subCounts[$pid] = ($subCounts[$pid] ?? 0) + 1;
        }
    }

    $byId = [];
    $children = ['root' => []];
    foreach ($folders as $f) {
        $fid = (int) $f['id'];
        $f['subfolder_count'] = $subCounts[$fid] ?? 0;
        $f['item_count'] = $itemCounts[$fid] ?? 0;
        $byId[(string) $fid] = $f;
        $pk = $f['parent_id'] === null ? 'root' : (string) ((int) $f['parent_id']);
        $children[$pk][] = $fid;
    }

    $payload = [
        'generated_at' => time(),
        'total_active' => count($folders),
        'total_items'  => array_sum($itemCounts),
        'byId'         => $byId,
        'children'     => $children,
    ];
    $tmp = $cacheFile . '.' . bin2hex(random_bytes(6)) . '.tmp';
    if (@file_put_contents($tmp, (string) json_encode($payload, JSON_UNESCAPED_UNICODE), LOCK_EX) !== false) {
        if (!@rename($tmp, $cacheFile)) {
            @unlink($tmp);
        }
    } else {
        @unlink($tmp);
    }
    return $payload;
}

/**
 * Lightweight flock-backed per-IP fixed-window rate limiter for public API routes (zero MySQL writes).
 */
function public_api_ip_rate_limit(string $bucket, int $maxHits, int $windowSec = 60): array
{
    $rlDir = ensure_bookexa_cache_dir('rl');
    $path = $rlDir . '/' . hash('sha256', $bucket . '|' . client_ip()) . '.json';
    $now = time();
    $fh = @fopen($path, 'c+b');
    if (!$fh) {
        return ['allowed' => true, 'remaining' => $maxHits, 'reset' => $now + $windowSec];
    }
    if (!@flock($fh, LOCK_EX)) {
        @fclose($fh);
        return ['allowed' => true, 'remaining' => $maxHits, 'reset' => $now + $windowSec];
    }
    $raw = stream_get_contents($fh);
    $data = $raw ? json_decode($raw, true) : null;
    $ws = is_array($data) ? (int) ($data['w'] ?? 0) : 0;
    $hits = is_array($data) ? (int) ($data['h'] ?? 0) : 0;
    if ($ws <= 0 || ($now - $ws) >= $windowSec) {
        $ws = $now;
        $hits = 0;
    }
    $hits++;
    $allowed = $hits <= $maxHits;
    if ($allowed) {
        rewind($fh);
        ftruncate($fh, 0);
        fwrite($fh, (string) json_encode(['w' => $ws, 'h' => $hits]));
        fflush($fh);
    }
    @flock($fh, LOCK_UN);
    @fclose($fh);

    return [
        'allowed'   => $allowed,
        'remaining' => max(0, $maxHits - $hits),
        'reset'     => max(1, ($ws + $windowSec) - $now),
    ];
}

function folder_breadcrumbs(int $folderId): array
{
    $tree = get_cached_folder_tree();
    $byId = $tree['byId'] ?? [];
    if (isset($byId[(string) $folderId])) {
        $trail = [];
        $seen = [];
        $curr = $folderId;
        while ($curr !== null && !isset($seen[$curr])) {
            $seen[$curr] = true;
            $row = $byId[(string) $curr] ?? null;
            if (!$row) {
                break;
            }
            array_unshift($trail, $row);
            $curr = $row['parent_id'] !== null ? (int) $row['parent_id'] : null;
        }
        return $trail;
    }

    $trail = [];
    $seen = [];
    $curr = $folderId;
    while ($curr !== null && !isset($seen[$curr])) {
        $seen[$curr] = true;
        $row = db_one('SELECT id, parent_id, slug, name_bn, name_en FROM folders WHERE id = ? AND is_active = 1 AND ' . published_window_sql(), [$curr]);
        if (!$row) {
            break;
        }
        array_unshift($trail, $row);
        $curr = $row['parent_id'] !== null ? (int) $row['parent_id'] : null;
    }
    return $trail;
}

function folder_full_url(int $folderId): string
{
    $trail = folder_breadcrumbs($folderId);
    if (empty($trail)) {
        return url('/pdf');
    }
    $slugs = array_map(fn($f) => rawurlencode($f['slug']), $trail);
    return url('/pdf/' . implode('/', $slugs));
}

function resolve_folder_by_slugs(array $segments): ?array
{
    if (empty($segments)) {
        return null;
    }
    $tree = get_cached_folder_tree();
    $byId = $tree['byId'] ?? [];
    $children = $tree['children'] ?? [];
    $parentKey = 'root';
    $cachedMatch = null;
    foreach ($segments as $slug) {
        $found = null;
        foreach ($children[$parentKey] ?? [] as $cid) {
            $cand = $byId[(string) $cid] ?? null;
            if ($cand && (string) ($cand['slug'] ?? '') === $slug) {
                $found = $cand;
                break;
            }
        }
        if (!$found) {
            $cachedMatch = null;
            break;
        }
        $cachedMatch = $found;
        $parentKey = (string) ((int) $found['id']);
    }
    if ($cachedMatch !== null) {
        return $cachedMatch;
    }

    $parentId = null;
    $current = null;
    $pub = published_window_sql();
    foreach ($segments as $slug) {
        if ($parentId === null) {
            $current = db_one("SELECT * FROM folders WHERE parent_id IS NULL AND slug = ? AND is_active = 1 AND {$pub} LIMIT 1", [$slug]);
        } else {
            $current = db_one("SELECT * FROM folders WHERE parent_id = ? AND slug = ? AND is_active = 1 AND {$pub} LIMIT 1", [$parentId, $slug]);
        }
        if (!$current) {
            return null;
        }
        $parentId = (int) $current['id'];
    }
    if ($current) {
        get_cached_folder_tree(true);
    }
    return $current;
}


function user_owns_item(int $userId, int $itemId): bool
{
    return db_one('SELECT id FROM library WHERE user_id = ? AND item_id = ? LIMIT 1', [$userId, $itemId]) !== null;
}

function user_pending_order(int $userId, int $itemId): ?array
{
    return db_one(
        "SELECT id, order_code, status FROM orders WHERE user_id = ? AND item_id = ? AND status = 'pending' ORDER BY id DESC LIMIT 1",
        [$userId, $itemId]
    );
}
