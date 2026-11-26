<?php
declare(strict_types=1);

/**
 * Bookexa Performance Layer:
 *  1. Anonymous GET Full-Page File Cache (ETag, Last-Modified, 304 Not Modified, safe bypass)
 *  2. Server-Timing Header in Debug Mode (local / debug_timing=1)
 *  3. Built / Content-Hashed Asset Manifest Lookup + Critical CSS Inliner
 *  4. GD WebP/AVIF Responsive Variants + <picture> Helper (CDN-compatible)
 */

if (!defined('BX_REQ_START_TIME')) {
    define('BX_REQ_START_TIME', microtime(true));
}

function perf_debug_enabled(): bool
{
    static $enabled = null;
    if ($enabled !== null) {
        return $enabled;
    }
    if (PHP_SAPI === 'cli') {
        $enabled = false;
        return false;
    }
    if (app_config('app_env', 'production') === 'local') {
        $enabled = true;
        return true;
    }
    if (function_exists('site_setting') && site_setting('debug_timing', '0') === '1') {
        $enabled = true;
        return true;
    }
    $enabled = false;
    return false;
}

function perf_set_cache_status(string $status): void
{
    $GLOBALS['__bx_page_cache_status'] = $status;
}

function perf_emit_server_timing(string $cacheStatus = ''): void
{
    if (headers_sent() || !perf_debug_enabled()) {
        return;
    }
    $totalMs = round((microtime(true) - BX_REQ_START_TIME) * 1000, 2);
    $dbStats = function_exists('db_metrics') ? db_metrics() : ['queries' => 0, 'time_ms' => 0.0];
    $status = $cacheStatus !== '' ? $cacheStatus : (string) ($GLOBALS['__bx_page_cache_status'] ?? 'BYPASS');
    $header = sprintf(
        'app;dur=%.2f, db;dur=%.2f;desc="%d queries", cache;desc="%s"',
        $totalMs,
        (float) ($dbStats['time_ms'] ?? 0.0),
        (int) ($dbStats['queries'] ?? 0),
        $status
    );
    header('Server-Timing: ' . $header);
}

// ============================================================================
// 1. Anonymous Public Full-Page Cache
// ============================================================================

function perf_page_cache_dir(): string
{
    return ensure_bookexa_cache_dir('pages');
}

function perf_route_is_cacheable(string $logicalRoute): bool
{
    $exactAllowed = [
        '/', '/pdf', '/shop', '/guides', '/authors', '/faq', '/about', '/contact',
        '/terms', '/privacy', '/refund-policy', '/copyright',
        '/editorial-policy', '/how-we-review', '/corrections-policy',
    ];
    if (in_array($logicalRoute, $exactAllowed, true)) {
        return true;
    }
    foreach (['/pdf/', '/book/', '/guides/', '/authors/', '/hub/', '/shop/'] as $prefix) {
        if (str_starts_with($logicalRoute, $prefix)) {
            if (preg_match('#^/shop/(checkout|order-success|orders|track)#', $logicalRoute)) {
                return false;
            }
            if (preg_match('#/review$#', $logicalRoute)) {
                return false;
            }
            return true;
        }
    }
    return false;
}

function perf_can_use_page_cache(string $logicalRoute): bool
{
    if (PHP_SAPI === 'cli') {
        return false;
    }
    $method = strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'));
    if ($method !== 'GET' && $method !== 'HEAD') {
        return false;
    }
    if (isset($_GET['nocache'])) {
        return false;
    }
    if (!perf_route_is_cacheable($logicalRoute)) {
        return false;
    }
    // Never short-circuit non-canonical URLs (trailing slash, uppercase path, tracking/redundant params):
    // let routes/public.php emit its clean 301 canonical redirect first.
    $rawPath = (string) (parse_url((string) ($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH) ?: '/');
    if (BASE_PATH !== '' && str_starts_with($rawPath, BASE_PATH)) {
        $rawPath = substr($rawPath, strlen(BASE_PATH)) ?: '/';
    }
    if ($rawPath !== '/' && (str_ends_with($rawPath, '/') || preg_match('/[A-Z]/', $rawPath) === 1)) {
        return false;
    }
    if (isset($_GET['page']) && (string) $_GET['page'] === '1') {
        return false;
    }
    foreach (['utm_source', 'utm_medium', 'utm_campaign', 'utm_term', 'utm_content', 'fbclid', 'gclid'] as $trk) {
        if (isset($_GET[$trk])) {
            return false;
        }
    }
    if (!empty($_SESSION['user_id']) || !empty($_SESSION['admin_id'])) {
        return false;
    }
    if (!empty($_SESSION['_flashes'])) {
        return false;
    }
    if (!empty($_SESSION['shop_cart_token']) || !empty($_COOKIE['bx_cart_tok'])) {
        return false;
    }
    if (function_exists('is_demo_mode') && is_demo_mode()) {
        return false;
    }
    if (site_setting('maintenance_mode', '0') === '1') {
        return false;
    }
    return true;
}

function perf_page_cache_key(string $logicalRoute): string
{
    $lang = function_exists('current_lang') ? current_lang() : 'bn';
    $q = $_GET;
    unset($q['lang'], $q['utm_source'], $q['utm_medium'], $q['utm_campaign'], $q['fbclid'], $q['gclid']);
    ksort($q);
    $normQ = http_build_query($q);
    return hash('sha256', $lang . '|' . $logicalRoute . '|' . $normQ);
}

function perf_client_accepts_gzip(): bool
{
    $enc = strtolower((string) ($_SERVER['HTTP_ACCEPT_ENCODING'] ?? ''));
    return str_contains($enc, 'gzip') && function_exists('gzencode');
}

function perf_send_cached_file_response(string $htmlFile, string $metaFile, bool $isHead = false): never
{
    $meta = json_decode((string) @file_get_contents($metaFile), true);
    $mtime = is_array($meta) ? (int) ($meta['mtime'] ?? (@filemtime($htmlFile) ?: time())) : (@filemtime($htmlFile) ?: time());
    $etag = is_array($meta) && !empty($meta['etag']) ? (string) $meta['etag'] : ('"' . md5_file($htmlFile) . '"');
    $lastMod = gmdate('D, d M Y H:i:s', $mtime) . ' GMT';

    header('Content-Type: text/html; charset=UTF-8');
    header('Cache-Control: public, max-age=120, stale-while-revalidate=60');
    header('ETag: ' . $etag);
    header('Last-Modified: ' . $lastMod);
    header('Vary: Accept-Encoding, Cookie');
    header('X-Bookexa-Cache: HIT');
    if (is_array($meta) && !empty($meta['x_robots_tag'])) {
        header('X-Robots-Tag: ' . (string) $meta['x_robots_tag']);
    }

    perf_set_cache_status('HIT');
    perf_emit_server_timing('HIT');

    $ifNoneMatch = trim((string) ($_SERVER['HTTP_IF_NONE_MATCH'] ?? ''));
    if ($ifNoneMatch !== '' && ($ifNoneMatch === $etag || $ifNoneMatch === 'W/' . $etag)) {
        http_response_code(304);
        exit;
    }
    $ifModSince = trim((string) ($_SERVER['HTTP_IF_MODIFIED_SINCE'] ?? ''));
    if ($ifModSince !== '' && ($sinceTs = strtotime($ifModSince)) !== false && $sinceTs >= $mtime) {
        http_response_code(304);
        exit;
    }

    if (!$isHead) {
        $gzFile = $htmlFile . '.gz';
        if (perf_client_accepts_gzip() && is_file($gzFile) && !ini_get('zlib.output_compression')) {
            header('Content-Encoding: gzip');
            header('Content-Length: ' . (string) filesize($gzFile));
            readfile($gzFile);
        } else {
            readfile($htmlFile);
        }
    }
    exit;
}

function perf_page_cache_try_serve(string $logicalRoute): bool
{
    if (!perf_can_use_page_cache($logicalRoute)) {
        perf_set_cache_status('BYPASS');
        return false;
    }
    $dir = perf_page_cache_dir();
    $key = perf_page_cache_key($logicalRoute);
    $htmlFile = $dir . '/' . $key . '.html';
    $metaFile = $dir . '/' . $key . '.json';
    $ttl = 300; // 5 min TTL, immediately cleared on any admin mutation

    if (is_file($htmlFile) && is_file($metaFile)) {
        $age = time() - (@filemtime($htmlFile) ?: 0);
        if ($age >= 0 && $age < $ttl) {
            $isHead = strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET')) === 'HEAD';
            perf_send_cached_file_response($htmlFile, $metaFile, $isHead);
        }
    }

    perf_set_cache_status('MISS');
    perf_page_cache_start_capture($htmlFile, $metaFile);
    return false;
}

function perf_page_cache_start_capture(string $htmlFile, string $metaFile): void
{
    static $started = false;
    if ($started) {
        return;
    }
    $started = true;

    header_register_callback(static function (): void {
        if (headers_sent()) {
            return;
        }
        perf_emit_server_timing((string) ($GLOBALS['__bx_page_cache_status'] ?? 'MISS'));
    });

    ob_start();
    register_shutdown_function(static function () use ($htmlFile, $metaFile): void {
        if (ob_get_level() < 1) {
            return;
        }
        $html = ob_get_contents();
        if (!is_string($html) || $html === '' || http_response_code() !== 200) {
            return;
        }
        // Double-check state did not become user-specific during request
        if (!empty($_SESSION['user_id']) || !empty($_SESSION['admin_id']) || !empty($_SESSION['_flashes'])) {
            return;
        }
        // Only cache complete HTML responses
        if (!str_contains($html, '</html>')) {
            return;
        }

        $etag = '"' . md5($html) . '"';
        $now = time();
        $xRobots = '';
        foreach (headers_list() as $hLine) {
            if (stripos($hLine, 'X-Robots-Tag:') === 0) {
                $xRobots = trim(substr($hLine, 13));
            }
        }
        $meta = [
            'etag'         => $etag,
            'mtime'        => $now,
            'x_robots_tag' => $xRobots,
        ];

        $tmpHtml = $htmlFile . '.' . bin2hex(random_bytes(4)) . '.tmp';
        $tmpMeta = $metaFile . '.' . bin2hex(random_bytes(4)) . '.tmp';
        if (@file_put_contents($tmpHtml, $html, LOCK_EX) !== false) {
            @rename($tmpHtml, $htmlFile);
            if (function_exists('gzencode')) {
                $gzData = @gzencode($html, 6);
                if (is_string($gzData) && $gzData !== '') {
                    @file_put_contents($htmlFile . '.gz', $gzData, LOCK_EX);
                }
            }
        } else {
            @unlink($tmpHtml);
        }
        if (@file_put_contents($tmpMeta, json_encode($meta, JSON_UNESCAPED_SLASHES), LOCK_EX) !== false) {
            @rename($tmpMeta, $metaFile);
        } else {
            @unlink($tmpMeta);
        }
    });
}

function perf_clear_page_cache(): int
{
    $dir = APP_ROOT . '/storage/cache/pages';
    if (!is_dir($dir)) {
        return 0;
    }
    $removed = 0;
    foreach (glob($dir . '/*') ?: [] as $f) {
        if (is_file($f) && basename($f) !== '.htaccess') {
            if (@unlink($f)) {
                $removed++;
            }
        }
    }
    return $removed;
}

// ============================================================================
// 2. Built Assets Manifest & Inlined Critical CSS
// ============================================================================

function perf_dist_manifest(): array
{
    static $manifest = null;
    if ($manifest !== null) {
        return $manifest;
    }
    $file = APP_ROOT . '/assets/dist/manifest.json';
    if (is_file($file)) {
        $dec = json_decode((string) @file_get_contents($file), true);
        if (is_array($dec)) {
            $manifest = $dec;
            return $manifest;
        }
    }
    $manifest = [];
    return $manifest;
}

function perf_dist_asset_url(string $bundleKey, string $fallbackRelPath): string
{
    $m = perf_dist_manifest();
    if (!empty($m['files'][$bundleKey]['path'])) {
        return BASE_PATH . '/' . ltrim((string) $m['files'][$bundleKey]['path'], '/');
    }
    return asset($fallbackRelPath);
}

function perf_critical_css_inline(): string
{
    $m = perf_dist_manifest();
    if (!empty($m['critical_css_file'])) {
        $abs = APP_ROOT . '/' . ltrim((string) $m['critical_css_file'], '/');
        if (is_file($abs)) {
            $css = @file_get_contents($abs);
            if (is_string($css) && $css !== '') {
                $fontsBase = (defined('BASE_PATH') ? rtrim((string) BASE_PATH, '/') : '') . '/assets/fonts/';
                return str_replace('__BX_FONTS_BASE__/', $fontsBase, $css);
            }
        }
    }
    return '';
}

function perf_active_font_preloads(string $lang): array
{
    $m = perf_dist_manifest();
    if (!empty($m['font_preloads'][$lang]) && is_array($m['font_preloads'][$lang])) {
        return $m['font_preloads'][$lang];
    }
    return $lang === 'bn'
        ? ['assets/fonts/font-1.woff2', 'assets/fonts/font-3.woff2']
        : ['assets/fonts/font-3.woff2'];
}

// ============================================================================
// 3. Responsive GD Images (WebP & AVIF srcset / sizes) + CDN compatibility
// ============================================================================

function perf_image_widths_for_path(string $relPath): array
{
    if (str_contains($relPath, '/banners/')) {
        return [480, 900, 1200];
    }
    return [320, 640];
}

function perf_variant_rel_path(string $relPath, int $width, string $format): string
{
    $dot = strrpos($relPath, '.');
    $stem = $dot !== false ? substr($relPath, 0, $dot) : $relPath;
    return $stem . "-{$width}w.{$format}";
}

function perf_optimize_image_variants(string $relPath, bool $pushCdn = true): array
{
    $relPath = ltrim(str_replace('\\', '/', $relPath), '/');
    $ext = strtolower(pathinfo($relPath, PATHINFO_EXTENSION));
    if (!in_array($ext, ['jpg', 'jpeg', 'png', 'webp'], true) || !extension_loaded('gd')) {
        return [];
    }
    $abs = APP_ROOT . '/' . $relPath;
    if (!is_file($abs)) {
        return [];
    }
    $info = @getimagesize($abs);
    if (!$info || empty($info[0]) || empty($info[1])) {
        return [];
    }
    $origW = (int) $info[0];
    $origH = (int) $info[1];
    $mime = (string) ($info['mime'] ?? '');
    $srcImg = match ($mime) {
        'image/jpeg' => @imagecreatefromjpeg($abs),
        'image/png'  => @imagecreatefrompng($abs),
        'image/webp' => function_exists('imagecreatefromwebp') ? @imagecreatefromwebp($abs) : false,
        default      => false,
    };
    if (!$srcImg) {
        return [];
    }

    $created = [];
    $targets = perf_image_widths_for_path($relPath);
    foreach ($targets as $tw) {
        $dw = min($origW, $tw);
        if ($dw <= 0) {
            continue;
        }
        $dh = max(1, (int) round($origH * ($dw / $origW)));
        $dst = imagecreatetruecolor($dw, $dh);
        if ($mime === 'image/png' || $mime === 'image/webp') {
            imagealphablending($dst, false);
            imagesavealpha($dst, true);
        }
        imagecopyresampled($dst, $srcImg, 0, 0, 0, 0, $dw, $dh, $origW, $origH);

        if (function_exists('imagewebp')) {
            $webpRel = perf_variant_rel_path($relPath, $tw, 'webp');
            $webpAbs = APP_ROOT . '/' . $webpRel;
            if (@imagewebp($dst, $webpAbs, 82)) {
                $created[] = $webpRel;
                if ($pushCdn && function_exists('media_sync_push_one')) {
                    media_sync_push_one($webpRel);
                }
            }
        }
        if (function_exists('imageavif')) {
            $avifRel = perf_variant_rel_path($relPath, $tw, 'avif');
            $avifAbs = APP_ROOT . '/' . $avifRel;
            if (@imageavif($dst, $avifAbs, 56)) {
                $created[] = $avifRel;
                if ($pushCdn && function_exists('media_sync_push_one')) {
                    media_sync_push_one($avifRel);
                }
            }
        }
        imagedestroy($dst);
    }
    imagedestroy($srcImg);
    return array_values(array_unique($created));
}

function perf_responsive_sources(string $relPath): array
{
    static $cache = [];
    $relPath = ltrim(str_replace('\\', '/', $relPath), '/');
    if (isset($cache[$relPath])) {
        return $cache[$relPath];
    }
    $ext = strtolower(pathinfo($relPath, PATHINFO_EXTENSION));
    if (!in_array($ext, ['jpg', 'jpeg', 'png', 'webp'], true)) {
        $cache[$relPath] = ['avif' => [], 'webp' => []];
        return $cache[$relPath];
    }
    $widths = perf_image_widths_for_path($relPath);
    $avif = [];
    $webp = [];
    foreach ($widths as $w) {
        $aRel = perf_variant_rel_path($relPath, $w, 'avif');
        if (is_file(APP_ROOT . '/' . $aRel)) {
            $avif[] = asset($aRel) . " {$w}w";
        }
        $wRel = perf_variant_rel_path($relPath, $w, 'webp');
        if (is_file(APP_ROOT . '/' . $wRel)) {
            $webp[] = asset($wRel) . " {$w}w";
        }
    }
    $cache[$relPath] = ['avif' => $avif, 'webp' => $webp];
    return $cache[$relPath];
}

function render_responsive_picture(string $relPath, string $alt, array $opts = []): string
{
    $relPath = ltrim(str_replace('\\', '/', $relPath), '/');
    $w = (int) ($opts['width'] ?? 400);
    $h = (int) ($opts['height'] ?? 600);
    $sizes = (string) ($opts['sizes'] ?? '(max-width: 600px) 46vw, 240px');
    $cls = trim((string) ($opts['class'] ?? ''));
    $style = trim((string) ($opts['style'] ?? ''));
    $isLcp = !empty($opts['is_lcp']);
    $loading = $isLcp ? '' : (string) ($opts['loading'] ?? 'lazy');
    $extraAttrs = '';
    if ($cls !== '') {
        $extraAttrs .= ' class="' . e($cls) . '"';
    }
    if (!empty($opts['id'])) {
        $extraAttrs .= ' id="' . e((string) $opts['id']) . '"';
    }
    if ($style !== '') {
        $extraAttrs .= ' style="' . e($style) . '"';
    }
    if ($loading !== '' && !$isLcp) {
        $extraAttrs .= ' loading="' . e($loading) . '"';
    }
    if ($isLcp) {
        $extraAttrs .= ' fetchpriority="high"';
    }
    $extraAttrs .= ' decoding="async"';

    $fallbackUrl = asset($relPath);
    $imgTag = '<img src="' . e($fallbackUrl) . '" alt="' . e($alt) . '" width="' . $w . '" height="' . $h . '"' . $extraAttrs . '>';
    $sources = perf_responsive_sources($relPath);
    if (empty($sources['avif']) && empty($sources['webp'])) {
        return $imgTag;
    }

    $out = '<picture>';
    if (!empty($sources['avif'])) {
        $out .= '<source type="image/avif" srcset="' . e(implode(', ', $sources['avif'])) . '" sizes="' . e($sizes) . '">';
    }
    if (!empty($sources['webp'])) {
        $out .= '<source type="image/webp" srcset="' . e(implode(', ', $sources['webp'])) . '" sizes="' . e($sizes) . '">';
    }
    $out .= $imgTag . '</picture>';
    return $out;
}
