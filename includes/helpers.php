<?php
declare(strict_types=1);

function e(?string $str): string
{
    return htmlspecialchars((string) $str, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
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
    $pageTitle = (string) $errCode;
    $activeNav = '';
    if ($errCode !== 500 && function_exists('current_lang') && is_file(APP_ROOT . '/includes/header.php')) {
        require APP_ROOT . '/includes/header.php';
        require APP_ROOT . '/pages/error.php';
        require APP_ROOT . '/includes/footer.php';
    } else {
        echo '<!DOCTYPE html><html lang="en" data-theme="light"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>' . $errCode . ' — Bookexa</title><link rel="stylesheet" href="' . e(asset('assets/css/tokens.css')) . '"><link rel="stylesheet" href="' . e(asset('assets/css/components.css')) . '"></head><body><main class="bx-main">';
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
    $text = strtolower(trim($text));
    $text = preg_replace('/[^a-z0-9\s-]+/', '', $text) ?? '';
    $text = preg_replace('/[\s-]+/', '-', $text) ?? '';
    $text = trim($text, '-');
    return $text !== '' ? $text : ('item-' . substr(bin2hex(random_bytes(4)), 0, 6));
}

function site_setting(string $key, string $default = ''): string
{
    static $cache = null;
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
    return ($cache[$key] ?? '') !== '' ? $cache[$key] : $default;
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
    foreach (['sitemap.xml', 'sitemap-static.xml', 'sitemap-folders.xml', 'sitemap-books.xml'] as $sm) {
        @unlink($base . '/' . $sm);
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
