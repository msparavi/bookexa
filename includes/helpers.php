<?php
declare(strict_types=1);

function e(?string $str): string
{
    return htmlspecialchars((string) $str, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function url(string $path = '/'): string
{
    $clean = '/' . ltrim($path, '/');
    if ($clean === '//') {
        $clean = '/';
    }
    return BASE_PATH . $clean;
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

function folder_breadcrumbs(int $folderId): array
{
    $trail = [];
    $seen = [];
    $curr = $folderId;
    while ($curr !== null && !isset($seen[$curr])) {
        $seen[$curr] = true;
        $row = db_one('SELECT id, parent_id, slug, name_bn, name_en FROM folders WHERE id = ? AND is_active = 1', [$curr]);
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
    $parentId = null;
    $current = null;
    foreach ($segments as $slug) {
        if ($parentId === null) {
            $current = db_one('SELECT * FROM folders WHERE parent_id IS NULL AND slug = ? AND is_active = 1 LIMIT 1', [$slug]);
        } else {
            $current = db_one('SELECT * FROM folders WHERE parent_id = ? AND slug = ? AND is_active = 1 LIMIT 1', [$parentId, $slug]);
        }
        if (!$current) {
            return null;
        }
        $parentId = (int) $current['id'];
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
