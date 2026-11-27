<?php
declare(strict_types=1);

/**
 * Bookexa Indexing, Privacy-Friendly Analytics, SEO Health Dashboard & Growth Tools
 */

// ============================================================================
// 1. IndexNow (Key Hosting, Queueing, Batched Rate-Limited Pings & Logs)
// ============================================================================

function growth_indexnow_key(bool $regenerate = false): string
{
    static $memoKey = null;
    if (!$regenerate && $memoKey !== null && preg_match('/^[a-f0-9]{16,64}$/i', $memoKey)) {
        return $memoKey;
    }
    $curr = trim(site_setting('indexnow_key', '', $regenerate));
    if ($regenerate || !preg_match('/^[a-f0-9]{16,64}$/i', $curr)) {
        $curr = bin2hex(random_bytes(16));
        try {
            site_setting_set('indexnow_key', $curr);
        } catch (Throwable $e) {
        }
    }
    $memoKey = $curr;
    return $curr;
}

function growth_indexnow_key_url(): string
{
    return rtrim(BASE_URL, '/') . '/' . growth_indexnow_key() . '.txt';
}

function growth_indexnow_queue_urls(array $urls, string $entityType = 'custom', string $eventType = 'update'): int
{
    $queued = 0;
    foreach ($urls as $u) {
        $u = trim((string) $u);
        if ($u === '' || !preg_match('#^https?://#i', $u)) {
            continue;
        }
        $hash = sha1($u);
        try {
            db_exec(
                'INSERT INTO indexnow_queue (url, url_hash, entity_type, event_type) VALUES (?, ?, ?, ?)
                 ON DUPLICATE KEY UPDATE event_type = VALUES(event_type), created_at = NOW()',
                [mb_substr($u, 0, 500), $hash, mb_substr($entityType, 0, 40), mb_substr($eventType, 0, 24)]
            );
            $queued++;
        } catch (Throwable $e) {
        }
    }
    return $queued;
}

function growth_indexnow_queue_entity(string $entityType, int|string $slugOrId, string $eventType = 'update', ?string $explicitPath = null): int
{
    if (site_setting('indexnow_enabled', '1') !== '1') {
        return 0;
    }
    $path = $explicitPath;
    if ($path === null) {
        $path = match ($entityType) {
            'book'    => '/book/' . rawurlencode((string) $slugOrId),
            'guide'   => '/guides/' . rawurlencode((string) $slugOrId),
            'product' => '/shop/' . rawurlencode((string) $slugOrId),
            'folder'  => is_numeric($slugOrId) ? parse_url(folder_full_url((int) $slugOrId), PHP_URL_PATH) : '/pdf/' . rawurlencode((string) $slugOrId),
            default   => '/',
        };
        if (is_string($path) && BASE_PATH !== '' && str_starts_with($path, BASE_PATH)) {
            $path = substr($path, strlen(BASE_PATH)) ?: '/';
        }
    }
    $urls = [
        absolute_url_for_lang((string) $path, 'bn'),
        absolute_url_for_lang((string) $path, 'en'),
    ];
    return growth_indexnow_queue_urls($urls, $entityType, $eventType);
}

function growth_http_post_json(string $endpoint, array $payload, int $timeoutSec = 4): array
{
    if (function_exists('curl_init')) {
        $ch = curl_init($endpoint);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
            CURLOPT_HTTPHEADER     => ['Content-Type: application/json; charset=utf-8'],
            CURLOPT_TIMEOUT        => $timeoutSec,
            CURLOPT_CONNECTTIMEOUT => max(2, $timeoutSec - 1),
            CURLOPT_USERAGENT      => 'Bookexa-IndexNow/1.0',
        ]);
        $body = (string) curl_exec($ch);
        $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err  = curl_error($ch);
        curl_close($ch);
        return ['code' => $code, 'body' => $body, 'error' => $err];
    }
    return ['code' => 0, 'body' => '', 'error' => 'cURL extension unavailable'];
}

function growth_indexnow_flush(bool $force = false, int $batchLimit = 200): array
{
    if (site_setting('indexnow_enabled', '1') !== '1' && !$force) {
        return ['ok' => false, 'status' => 'disabled', 'count' => 0];
    }
    $rows = db_all('SELECT * FROM indexnow_queue ORDER BY id ASC LIMIT ' . max(1, min(1000, $batchLimit)));
    if (!$rows) {
        return ['ok' => true, 'status' => 'empty', 'count' => 0];
    }

    $lastPing = (int) site_setting('indexnow_last_ping_at', '0');
    $now = time();
    if (!$force && ($now - $lastPing) < 60) {
        return ['ok' => true, 'status' => 'rate_limited', 'count' => count($rows)];
    }

    $urls = array_values(array_unique(array_map(static fn(array $r): string => (string) $r['url'], $rows)));
    $ids  = array_map(static fn(array $r): int => (int) $r['id'], $rows);
    $key  = growth_indexnow_key();
    $host = (string) (parse_url(BASE_URL, PHP_URL_HOST) ?: ($_SERVER['HTTP_HOST'] ?? 'localhost'));
    $isLocal = in_array(strtolower($host), ['localhost', '127.0.0.1', '::1'], true);

    $payload = [
        'host'        => $host,
        'key'         => $key,
        'keyLocation' => growth_indexnow_key_url(),
        'urlList'     => $urls,
    ];

    $httpCode = 0;
    $status   = 'queued';
    $excerpt  = '';
    if ($isLocal && !$force) {
        $status  = 'rate_limited';
        $excerpt = 'Deferred auto-ping on localhost (use manual Ping Now or live domain)';
    } else {
        $resp = growth_http_post_json('https://api.indexnow.org/indexnow', $payload, 4);
        $httpCode = (int) $resp['code'];
        if ($httpCode >= 200 && $httpCode < 300) {
            $status = 'sent';
            $excerpt = $resp['body'] !== '' ? mb_substr(trim($resp['body']), 0, 240) : 'Accepted (' . $httpCode . ')';
        } else {
            $status = 'failed';
            $excerpt = mb_substr(trim(($resp['error'] ?: $resp['body']) ?: 'No response'), 0, 240);
        }
    }

    try {
        db_exec(
            'INSERT INTO indexnow_logs (engine, action_type, url_count, urls_json, http_status, response_excerpt, status) VALUES (?, ?, ?, ?, ?, ?, ?)',
            [
                'api.indexnow.org (Bing/Yandex)',
                'url_batch',
                count($urls),
                json_encode(array_slice($urls, 0, 50), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
                $httpCode,
                $excerpt,
                $status,
            ]
        );
        db_exec(
            'INSERT INTO settings (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)',
            ['indexnow_last_ping_at', (string) $now]
        );
        if ($status === 'sent' && $ids) {
            $ph = implode(',', array_fill(0, count($ids), '?'));
            db_exec("DELETE FROM indexnow_queue WHERE id IN ({$ph})", $ids);
        } elseif ($ids) {
            $ph = implode(',', array_fill(0, count($ids), '?'));
            db_exec("UPDATE indexnow_queue SET attempts = LEAST(attempts + 1, 20) WHERE id IN ({$ph})", $ids);
            db_exec('DELETE FROM indexnow_queue WHERE attempts >= 5');
        }
    } catch (Throwable $e) {
    }

    return ['ok' => $status === 'sent', 'status' => $status, 'http_status' => $httpCode, 'count' => count($urls), 'message' => $excerpt];
}

function growth_indexnow_submit_sitemap(): array
{
    $sitemapUrl = rtrim(BASE_URL, '/') . '/sitemap.xml';
    $childUrls = [
        $sitemapUrl,
        rtrim(BASE_URL, '/') . '/sitemap-static.xml',
        rtrim(BASE_URL, '/') . '/sitemap-folders.xml',
        rtrim(BASE_URL, '/') . '/sitemap-books.xml',
        rtrim(BASE_URL, '/') . '/sitemap-guides.xml',
        rtrim(BASE_URL, '/') . '/sitemap-authors.xml',
        rtrim(BASE_URL, '/') . '/sitemap-hubs.xml',
        rtrim(BASE_URL, '/') . '/sitemap-products.xml',
    ];
    $key = growth_indexnow_key();
    $host = (string) (parse_url(BASE_URL, PHP_URL_HOST) ?: ($_SERVER['HTTP_HOST'] ?? 'localhost'));
    $resp = growth_http_post_json('https://api.indexnow.org/indexnow', [
        'host'        => $host,
        'key'         => $key,
        'keyLocation' => growth_indexnow_key_url(),
        'urlList'     => array_merge([absolute_url_for_lang('/', 'bn'), absolute_url_for_lang('/', 'en')], $childUrls),
    ], 4);
    $httpCode = (int) $resp['code'];
    $status = ($httpCode >= 200 && $httpCode < 300) ? 'sent' : 'failed';
    $excerpt = mb_substr(trim(($resp['error'] ?: $resp['body']) ?: "HTTP {$httpCode}"), 0, 240);

    try {
        db_exec(
            'INSERT INTO indexnow_logs (engine, action_type, url_count, urls_json, http_status, response_excerpt, status) VALUES (?, ?, ?, ?, ?, ?, ?)',
            ['IndexNow + Sitemap', 'sitemap_ping', count($childUrls), json_encode($childUrls, JSON_UNESCAPED_SLASHES), $httpCode, $excerpt, $status]
        );
    } catch (Throwable $e) {
    }
    return ['ok' => $status === 'sent', 'status' => $status, 'http_status' => $httpCode, 'message' => $excerpt, 'sitemap_url' => $sitemapUrl];
}

function growth_indexnow_recent_logs(int $limit = 30): array
{
    return db_all('SELECT * FROM indexnow_logs ORDER BY id DESC LIMIT ' . max(1, min(100, $limit)));
}

function growth_indexnow_queue_count(): int
{
    return (int) (db_one('SELECT COUNT(*) AS c FROM indexnow_queue')['c'] ?? 0);
}

// ============================================================================
// 2. Privacy-Friendly First-Party Analytics + Consent Helpers
// ============================================================================

function growth_dnt_active(): bool
{
    if (site_setting('analytics_dnt_honor', '1') !== '1') {
        return false;
    }
    return (($_SERVER['HTTP_DNT'] ?? '') === '1') || (($_SERVER['HTTP_SEC_GPC'] ?? '') === '1');
}

function growth_country_from_accept_lang(?string $header): string
{
    $hdr = trim((string) $header);
    if ($hdr === '') {
        return 'ZZ';
    }
    if (preg_match('/[a-z]{2,3}[-_]([A-Z]{2})\b/i', $hdr, $m)) {
        return strtoupper($m[1]);
    }
    $primary = strtolower(substr($hdr, 0, 2));
    return $primary === 'bn' ? 'BD' : 'ZZ';
}

function growth_device_class(?string $ua): string
{
    $uaLow = strtolower((string) $ua);
    if ($uaLow === '') {
        return 'desktop';
    }
    if (preg_match('/ipad|tablet|playbook|silk|(android(?!.*mobile))/i', $uaLow)) {
        return 'tablet';
    }
    if (preg_match('/mobi|iphone|ipod|android|blackberry|opera mini|iemobile/i', $uaLow)) {
        return 'mobile';
    }
    return 'desktop';
}

function growth_clean_referrer_host(?string $refHeader): string
{
    $ref = trim((string) $refHeader);
    if ($ref === '') {
        return '';
    }
    $refHost = strtolower((string) (parse_url($ref, PHP_URL_HOST) ?: ''));
    $ownHost = strtolower((string) (parse_url(BASE_URL, PHP_URL_HOST) ?: ($_SERVER['HTTP_HOST'] ?? '')));
    if ($refHost === '' || $refHost === $ownHost) {
        return '';
    }
    return mb_substr($refHost, 0, 150);
}

function growth_record_analytics_event(string $eventType, string $path, array $extra = []): bool
{
    if (growth_dnt_active()) {
        return false;
    }
    $ua = (string) ($_SERVER['HTTP_USER_AGENT'] ?? '');
    if (preg_match('/bot|crawl|spider|slurp|lighthouse|headless|curl|wget/i', $ua)) {
        return false;
    }
    $cleanPath = '/' . trim((string) parse_url($path, PHP_URL_PATH), '/');
    if ($cleanPath === '//') {
        $cleanPath = '/';
    }
    if (str_starts_with($cleanPath, '/admin') || str_starts_with($cleanPath, '/api/')) {
        return false;
    }
    $lang = in_array(($extra['lang'] ?? current_lang()), ['bn', 'en'], true) ? (string) ($extra['lang'] ?? current_lang()) : 'bn';
    $refHost = $extra['referrer_host'] ?? growth_clean_referrer_host($_SERVER['HTTP_REFERER'] ?? null);
    $country = growth_country_from_accept_lang($_SERVER['HTTP_ACCEPT_LANGUAGE'] ?? null);
    $device  = growth_device_class($ua);
    $query   = isset($extra['search_query']) ? mb_substr(trim((string) $extra['search_query']), 0, 180) : null;
    $resCnt  = array_key_exists('search_results_count', $extra) ? (int) $extra['search_results_count'] : null;
    $loadMs  = max(0, min(60000, (int) ($extra['load_ms'] ?? 0)));

    try {
        db_exec(
            'INSERT INTO analytics_events (event_type, path, lang, referrer_host, search_query, search_results_count, device_class, country_code, load_ms) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)',
            [$eventType, mb_substr($cleanPath, 0, 255), $lang, $refHost !== '' ? $refHost : null, $query !== '' ? $query : null, $resCnt, $device, $country, $loadMs]
        );
        return true;
    } catch (Throwable $e) {
        return false;
    }
}

function growth_analytics_ids(): array
{
    $ga4 = trim(site_setting('ga4_measurement_id', ''));
    if (!preg_match('/^G-[A-Z0-9]{4,20}$/i', $ga4)) {
        $ga4 = '';
    }
    $clarity = trim(site_setting('clarity_project_id', ''));
    if (!preg_match('/^[a-z0-9]{6,24}$/i', $clarity)) {
        $clarity = '';
    }
    return [
        'ga4_id'         => strtoupper($ga4),
        'clarity_id'     => $clarity,
        'cookie_banner'  => site_setting('cookie_banner_enabled', '1') === '1',
    ];
}

function growth_analytics_summary(int $days = 30): array
{
    $days = max(1, min(365, $days));
    $since = date('Y-m-d H:i:s', time() - ($days * 86400));

    $totViews = (int) (db_one("SELECT COUNT(*) AS c FROM analytics_events WHERE event_type = 'pageview' AND created_at >= ?", [$since])['c'] ?? 0);
    $totSearches = (int) (db_one("SELECT COUNT(*) AS c FROM analytics_events WHERE event_type = 'search' AND created_at >= ?", [$since])['c'] ?? 0);
    $totShares = (int) (db_one("SELECT COUNT(*) AS c FROM analytics_events WHERE event_type = 'share' AND created_at >= ?", [$since])['c'] ?? 0);
    $avgLoadMs = (int) round((float) (db_one("SELECT AVG(load_ms) AS a FROM analytics_events WHERE event_type = 'pageview' AND load_ms > 0 AND created_at >= ?", [$since])['a'] ?? 0));

    $daily = db_all(
        "SELECT DATE(created_at) AS day, COUNT(*) AS views FROM analytics_events WHERE event_type = 'pageview' AND created_at >= ? GROUP BY DATE(created_at) ORDER BY day ASC",
        [$since]
    );
    $topPages = db_all(
        "SELECT path, COUNT(*) AS views, ROUND(AVG(NULLIF(load_ms, 0))) AS avg_ms FROM analytics_events WHERE event_type = 'pageview' AND created_at >= ? GROUP BY path ORDER BY views DESC LIMIT 15",
        [$since]
    );
    $topReferrers = db_all(
        "SELECT COALESCE(NULLIF(referrer_host, ''), 'Direct / Organic') AS ref_host, COUNT(*) AS views FROM analytics_events WHERE event_type = 'pageview' AND created_at >= ? GROUP BY ref_host ORDER BY views DESC LIMIT 12",
        [$since]
    );
    $topSearches = db_all(
        "SELECT search_query, COUNT(*) AS hits, ROUND(AVG(COALESCE(search_results_count, 0))) AS avg_results FROM analytics_events WHERE event_type = 'search' AND search_query IS NOT NULL AND created_at >= ? GROUP BY search_query ORDER BY hits DESC LIMIT 15",
        [$since]
    );
    $devices = db_all(
        "SELECT device_class, COUNT(*) AS views FROM analytics_events WHERE event_type = 'pageview' AND created_at >= ? GROUP BY device_class ORDER BY views DESC",
        [$since]
    );
    $countries = db_all(
        "SELECT country_code, COUNT(*) AS views FROM analytics_events WHERE event_type = 'pageview' AND created_at >= ? GROUP BY country_code ORDER BY views DESC LIMIT 12",
        [$since]
    );

    return compact('days', 'totViews', 'totSearches', 'totShares', 'avgLoadMs', 'daily', 'topPages', 'topReferrers', 'topSearches', 'devices', 'countries');
}

// ============================================================================
// 3. SEO Dashboard Audit, Background Broken-Link Checker & Alerts
// ============================================================================

function growth_record_alert(string $type, string $severity, string $titleBn, string $titleEn, string $detail = ''): void
{
    if (!in_array($severity, ['info', 'warning', 'critical'], true)) {
        $severity = 'warning';
    }
    try {
        $existing = db_one(
            'SELECT id FROM seo_alerts WHERE alert_type = ? AND is_resolved = 0 AND created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR) LIMIT 1',
            [$type]
        );
        if ($existing) {
            db_exec('UPDATE seo_alerts SET detail = ? WHERE id = ?', [mb_substr($detail, 0, 580), (int) $existing['id']]);
            return;
        }
        db_exec(
            'INSERT INTO seo_alerts (alert_type, severity, title_bn, title_en, detail) VALUES (?, ?, ?, ?, ?)',
            [mb_substr($type, 0, 40), $severity, mb_substr($titleBn, 0, 220), mb_substr($titleEn, 0, 220), mb_substr($detail, 0, 580)]
        );
    } catch (Throwable $e) {
    }
}

function growth_indexable_urls_catalog(): array
{
    $out = [];
    $sections = ['static', 'folders', 'books', 'guides', 'authors', 'hubs', 'products'];
    foreach ($sections as $sec) {
        $rows = function_exists('seo_sitemap_section_entries') ? seo_sitemap_section_entries($sec) : [];
        foreach ($rows as $r) {
            $relPath = trim((string) ($r['path'] ?? ''));
            if ($relPath === '') {
                continue;
            }
            foreach (['bn', 'en'] as $lg) {
                $url = absolute_url_for_lang($relPath, $lg);
                $fullRel = ($lg === 'en' ? '/en' : '') . ($relPath === '/' ? '' : $relPath);
                if ($fullRel === '') {
                    $fullRel = '/';
                }
                $out[] = [
                    'section' => $sec,
                    'lang'    => $lg,
                    'url'     => $url,
                    'path'    => $fullRel,
                    'lastmod' => (string) ($r['lastmod'] ?? ''),
                ];
            }
        }
    }
    return $out;
}

function growth_internal_url_check(string $targetPath): array
{
    $rel = safe_internal_path($targetPath, '/');
    $base = rtrim(BASE_URL, '/');
    $fullUrl = $base . $rel;
    if (function_exists('curl_init') && PHP_SAPI !== 'cli') {
        $hops = 0;
        $currUrl = $fullUrl;
        $code = 0;
        for ($i = 0; $i < 5; $i++) {
            $ch = curl_init($currUrl);
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_HEADER         => true,
                CURLOPT_NOBODY         => true,
                CURLOPT_FOLLOWLOCATION => false,
                CURLOPT_TIMEOUT        => 2,
                CURLOPT_CONNECTTIMEOUT => 1,
                CURLOPT_USERAGENT      => 'Bookexa-LinkChecker/1.0',
            ]);
            curl_exec($ch);
            $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $loc  = (string) curl_getinfo($ch, CURLINFO_REDIRECT_URL);
            curl_close($ch);
            if ($code >= 300 && $code < 400 && $loc !== '') {
                $hops++;
                $currUrl = str_starts_with($loc, 'http') ? $loc : ($base . safe_internal_path($loc, '/'));
                continue;
            }
            break;
        }
        return [
            'status'            => $code,
            'is_broken'         => ($code === 0 || $code >= 400) ? 1 : 0,
            'is_redirect_chain' => $hops >= 2 ? 1 : 0,
            'redirect_hops'     => $hops,
            'final_url'         => $currUrl,
        ];
    }

    // Fallback offline router verification (zero HTTP deadlock during unit tests/CLI)
    $clean = '/' . trim((string) parse_url($rel, PHP_URL_PATH), '/');
    if (str_starts_with($clean, '/en/')) {
        $clean = '/' . ltrim(substr($clean, 3), '/');
    } elseif ($clean === '/en') {
        $clean = '/';
    }
    $ok = in_array($clean, ['/', '/pdf', '/shop', '/guides', '/authors', '/faq', '/about', '/contact', '/terms', '/privacy', '/refund-policy', '/copyright', '/editorial-policy', '/how-we-review', '/corrections-policy'], true)
        || (bool) db_one('SELECT id FROM items WHERE slug = ? AND is_active = 1 LIMIT 1', [basename($clean)])
        || (bool) db_one('SELECT id FROM guides WHERE slug = ? AND is_active = 1 LIMIT 1', [basename($clean)])
        || (bool) db_one('SELECT id FROM folders WHERE slug = ? AND is_active = 1 LIMIT 1', [basename($clean)])
        || (bool) db_one('SELECT id FROM products WHERE slug = ? AND is_active = 1 LIMIT 1', [basename($clean)])
        || (bool) db_one('SELECT id FROM authors WHERE slug = ? AND is_active = 1 LIMIT 1', [basename($clean)])
        || (bool) db_one('SELECT id FROM hubs WHERE slug = ? AND is_active = 1 LIMIT 1', [basename($clean)]);

    return [
        'status'            => $ok ? 200 : 404,
        'is_broken'         => $ok ? 0 : 1,
        'is_redirect_chain' => 0,
        'redirect_hops'     => 0,
        'final_url'         => $fullUrl,
    ];
}

function growth_link_checker_tick(int $limit = 3): array
{
    $limit = max(1, min(20, $limit));
    $targets = [];

    // Gather internal links referenced in guides & folders & hubs
    try {
        foreach (db_all('SELECT slug, body_bn, related_item_ids, related_folder_ids FROM guides WHERE is_active = 1 ORDER BY id DESC LIMIT 10') as $g) {
            $src = '/guides/' . $g['slug'];
            if (preg_match_all('/href=["\']([^"\']+)["\']/i', (string) ($g['body_bn'] ?? ''), $m)) {
                foreach ($m[1] as $href) {
                    if (str_starts_with($href, '/') && !str_starts_with($href, '//')) {
                        $targets[] = [$src, $href];
                    }
                }
            }
        }
        foreach (db_all('SELECT from_path, to_path, status_code FROM seo_redirects ORDER BY id DESC LIMIT 10') as $rd) {
            if (!empty($rd['to_path']) && str_starts_with((string) $rd['to_path'], '/')) {
                $targets[] = [$rd['from_path'], $rd['to_path']];
            }
        }
        foreach (array_slice(growth_indexable_urls_catalog(), 0, 15) as $catRow) {
            $targets[] = ['/sitemap.xml', $catRow['path']];
        }
    } catch (Throwable $e) {
    }

    $checked = 0;
    $brokenFound = 0;
    $chainsFound = 0;
    foreach ($targets as [$src, $dst]) {
        if ($checked >= $limit) {
            break;
        }
        $pairHash = sha1($src . '|' . $dst);
        $recent = db_one('SELECT id FROM seo_link_checks WHERE pair_hash = ? AND checked_at >= DATE_SUB(NOW(), INTERVAL 6 HOUR) LIMIT 1', [$pairHash]);
        if ($recent) {
            continue;
        }
        $res = growth_internal_url_check($dst);
        try {
            db_exec(
                'INSERT INTO seo_link_checks (pair_hash, source_path, target_path, http_status, is_broken, is_redirect_chain, redirect_hops, final_url, checked_at)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())
                 ON DUPLICATE KEY UPDATE http_status = VALUES(http_status), is_broken = VALUES(is_broken), is_redirect_chain = VALUES(is_redirect_chain), redirect_hops = VALUES(redirect_hops), final_url = VALUES(final_url), checked_at = NOW()',
                [$pairHash, mb_substr($src, 0, 255), mb_substr($dst, 0, 500), $res['status'], $res['is_broken'], $res['is_redirect_chain'], $res['redirect_hops'], mb_substr((string) $res['final_url'], 0, 500)]
            );
            $checked++;
            if ($res['is_broken']) $brokenFound++;
            if ($res['is_redirect_chain']) $chainsFound++;
        } catch (Throwable $e) {
        }
    }

    if ($brokenFound > 0) {
        growth_record_alert(
            'broken_links',
            'warning',
            'ভাঙা অভ্যন্তরীণ লিংক শনাক্ত হয়েছে',
            'Broken internal links detected',
            "{$brokenFound} broken link(s) detected by background checker."
        );
    }

    return ['checked' => $checked, 'broken' => $brokenFound, 'chains' => $chainsFound];
}

function growth_check_alerts_on_telemetry(): void
{
    try {
        // 1. robots.txt unexpected hash drift
        if (function_exists('aeo_build_robots_txt')) {
            $robotsBody = aeo_build_robots_txt();
            $currHash = sha1($robotsBody);
            $prevHash = site_setting('robots_last_hash', '');
            if ($prevHash === '') {
                db_exec('INSERT INTO settings (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)', ['robots_last_hash', $currHash]);
            } elseif (!hash_equals($prevHash, $currHash)) {
                growth_record_alert(
                    'robots_changed',
                    'info',
                    'robots.txt পরিবর্তিত হয়েছে',
                    'robots.txt content changed',
                    'Detected robots.txt output change (old ' . substr($prevHash, 0, 8) . ' -> new ' . substr($currHash, 0, 8) . '). Confirm AI/crawler policy.'
                );
                db_exec('INSERT INTO settings (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)', ['robots_last_hash', $currHash]);
            }
        }

        // 2. 404 spike check (>= 20 hits logged in last 24h)
        $hits24 = (int) (db_one('SELECT COALESCE(SUM(hits), 0) AS s FROM seo_404_logs WHERE last_seen_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)')['s'] ?? 0);
        if ($hits24 >= 20) {
            growth_record_alert(
                '404_spike',
                'critical',
                '404 ত্রুটি বেড়েছে',
                '404 error spike detected',
                "{$hits24} 404 hits observed in the last 24 hours. Review Top 404 URLs and add 301 redirects."
            );
        }
    } catch (Throwable $e) {
    }
}

function growth_seo_audit_snapshot(): array
{
    $missingMeta = [];
    $titleMap    = [];
    $thinContent = [];
    $orphanPages = [];
    $missingAlt  = [];

    // Items (Books)
    $items = db_all("SELECT i.id, i.slug, i.title_bn, i.title_en, i.description_bn, i.description_en, i.author_bn, i.author_en, i.price, i.cover, i.seo_title_bn, i.seo_description_bn, f.slug AS folder_slug, f.name_bn AS folder_bn, f.name_en AS folder_en, f.is_active AS folder_active FROM items i LEFT JOIN folders f ON f.id = i.folder_id WHERE i.is_active = 1");
    foreach ($items as $it) {
        $url = '/book/' . $it['slug'];
        $tBn = trim((string) ($it['seo_title_bn'] ?: ($it['title_bn'] . ' | Bookexa')));
        $dBn = trim((string) ($it['seo_description_bn'] ?: $it['description_bn'] ?: ''));
        $titleMap[mb_strtolower($tBn, 'UTF-8')][] = ['type' => 'book', 'url' => $url, 'title' => $tBn];
        if (trim((string) ($it['seo_title_bn'] ?? '')) === '' || trim((string) ($it['seo_description_bn'] ?? '')) === '') {
            $missingMeta[] = ['type' => 'book', 'url' => $url, 'name' => (string) $it['title_bn'], 'edit_url' => '/admin/pdfs?edit=' . (int) $it['id'], 'reason' => 'Missing custom SEO title/description'];
        }
        $words = function_exists('content_word_count') ? content_word_count((string) $it['description_bn']) : str_word_count((string) $it['description_bn']);
        if ($words < 15) {
            $thinContent[] = ['type' => 'book', 'url' => $url, 'name' => (string) $it['title_bn'], 'words' => $words, 'edit_url' => '/admin/pdfs?edit=' . (int) $it['id']];
        }
        if (empty($it['folder_slug']) || (int) ($it['folder_active'] ?? 0) !== 1) {
            $orphanPages[] = ['type' => 'book', 'url' => $url, 'name' => (string) $it['title_bn'], 'reason' => 'Missing active parent folder'];
        }
        if (trim((string) ($it['cover'] ?? '')) === '') {
            $missingAlt[] = ['type' => 'book', 'url' => $url, 'issue' => 'Missing cover thumbnail'];
        }
    }

    // Folders
    $folders = db_all("SELECT id, parent_id, slug, name_bn, description_bn, intro_bn, thumbnail, item_count, subfolder_count, seo_title_bn, seo_description_bn FROM folders WHERE is_active = 1");
    foreach ($folders as $f) {
        $url = '/pdf/' . $f['slug'];
        $tBn = trim((string) ($f['seo_title_bn'] ?: $f['name_bn']));
        $titleMap[mb_strtolower($tBn, 'UTF-8')][] = ['type' => 'folder', 'url' => $url, 'title' => $tBn];
        if (trim((string) ($f['seo_title_bn'] ?? '')) === '' || trim((string) ($f['seo_description_bn'] ?? '')) === '') {
            $missingMeta[] = ['type' => 'folder', 'url' => $url, 'name' => (string) $f['name_bn'], 'edit_url' => '/admin/folders?edit=' . (int) $f['id'], 'reason' => 'Missing custom SEO title/description'];
        }
        $words = function_exists('content_word_count') ? content_word_count(strip_tags((string) ($f['intro_bn'] ?: $f['description_bn']))) : 0;
        if ($words < 20) {
            $thinContent[] = ['type' => 'folder', 'url' => $url, 'name' => (string) $f['name_bn'], 'words' => $words, 'edit_url' => '/admin/folders?edit=' . (int) $f['id']];
        }
        if ((int) $f['item_count'] === 0 && (int) $f['subfolder_count'] === 0) {
            $orphanPages[] = ['type' => 'folder', 'url' => $url, 'name' => (string) $f['name_bn'], 'reason' => 'Empty folder (0 PDFs & 0 subfolders)'];
        }
        if (trim((string) ($f['thumbnail'] ?? '')) === '') {
            $missingAlt[] = ['type' => 'folder', 'url' => $url, 'issue' => 'Missing folder thumbnail'];
        }
    }

    // Guides
    $guides = db_all("SELECT id, slug, title_bn, body_bn, summary_bn, cover, seo_title_bn, seo_description_bn, related_item_ids, related_folder_ids FROM guides WHERE is_active = 1");
    foreach ($guides as $g) {
        $url = '/guides/' . $g['slug'];
        $tBn = trim((string) ($g['seo_title_bn'] ?: $g['title_bn']));
        $titleMap[mb_strtolower($tBn, 'UTF-8')][] = ['type' => 'guide', 'url' => $url, 'title' => $tBn];
        if (trim((string) ($g['seo_title_bn'] ?? '')) === '' || trim((string) ($g['seo_description_bn'] ?? '')) === '') {
            $missingMeta[] = ['type' => 'guide', 'url' => $url, 'name' => (string) $g['title_bn'], 'edit_url' => '/admin/guides?edit=' . (int) $g['id'], 'reason' => 'Missing custom SEO override'];
        }
        $words = function_exists('content_word_count') ? content_word_count((string) ($g['body_bn'] ?? '')) : 0;
        if ($words < 80) {
            $thinContent[] = ['type' => 'guide', 'url' => $url, 'name' => (string) $g['title_bn'], 'words' => $words, 'edit_url' => '/admin/guides?edit=' . (int) $g['id']];
        }
        if (preg_match_all('/<img\b([^>]*)>/i', (string) ($g['body_bn'] ?? ''), $imgTags)) {
            foreach ($imgTags[1] as $attrs) {
                if (!preg_match('/\balt\s*=\s*["\'][^"\']+["\']/i', $attrs)) {
                    $missingAlt[] = ['type' => 'guide', 'url' => $url, 'issue' => 'Inline body <img> missing descriptive alt attribute'];
                    break;
                }
            }
        }
        if (trim((string) ($g['related_item_ids'] ?? '')) === '' && trim((string) ($g['related_folder_ids'] ?? '')) === '') {
            $orphanPages[] = ['type' => 'guide', 'url' => $url, 'name' => (string) $g['title_bn'], 'reason' => 'Guide has no related books or folders linked'];
        }
    }

    $duplicateTitles = [];
    foreach ($titleMap as $lowKey => $list) {
        if (count($list) > 1) {
            $duplicateTitles[] = ['title' => $list[0]['title'], 'count' => count($list), 'pages' => $list];
        }
    }

    $brokenLinks    = db_all('SELECT * FROM seo_link_checks WHERE is_broken = 1 ORDER BY checked_at DESC LIMIT 50');
    $redirectChains = db_all('SELECT * FROM seo_link_checks WHERE is_redirect_chain = 1 ORDER BY redirect_hops DESC, checked_at DESC LIMIT 50');
    $top404s        = db_all('SELECT * FROM seo_404_logs ORDER BY hits DESC, last_seen_at DESC LIMIT 25');
    $slowPages      = db_all("SELECT path, COUNT(*) AS samples, ROUND(AVG(load_ms)) AS avg_load_ms, MAX(load_ms) AS max_load_ms FROM analytics_events WHERE event_type = 'pageview' AND load_ms > 0 GROUP BY path HAVING avg_load_ms >= 900 ORDER BY avg_load_ms DESC LIMIT 20");
    $searchMisses   = db_all('SELECT id, query_text, lang, hits, last_searched_at FROM search_misses ORDER BY hits DESC, last_searched_at DESC LIMIT 25');
    $openAlerts     = db_all('SELECT * FROM seo_alerts WHERE is_resolved = 0 ORDER BY id DESC LIMIT 30');
    $allAlerts      = db_all('SELECT * FROM seo_alerts ORDER BY id DESC LIMIT 50');

    return [
        'missing_meta'     => $missingMeta,
        'duplicate_titles' => $duplicateTitles,
        'thin_content'     => $thinContent,
        'orphan_pages'     => $orphanPages,
        'missing_alt'      => $missingAlt,
        'broken_links'     => $brokenLinks,
        'redirect_chains'  => $redirectChains,
        'top_404s'         => $top404s,
        'slow_pages'       => $slowPages,
        'content_ideas'    => $searchMisses,
        'open_alerts'      => $openAlerts,
        'all_alerts'       => $allAlerts,
    ];
}

function growth_search_console_checklist(): array
{
    $smEntries = function_exists('seo_sitemap_section_entries') ? count(seo_sitemap_section_entries('folders')) + count(seo_sitemap_section_entries('books')) + count(seo_sitemap_section_entries('guides')) + count(seo_sitemap_section_entries('static')) : 0;
    if ($smEntries === 0) {
        growth_record_alert('sitemap_error', 'critical', 'সাইটম্যাপে কোনো URL পাওয়া যায়নি', 'Sitemap generated 0 URLs', 'Check active folders/books/guides in DB.');
    }
    $robotsTxt = function_exists('aeo_build_robots_txt') ? aeo_build_robots_txt() : '';
    $robotsOk = str_contains($robotsTxt, 'Sitemap:') && str_contains($robotsTxt, '/en/admin');

    $canonSample = absolute_url_for_lang('/about', 'bn');
    $canonEnSample = absolute_url_for_lang('/about', 'en');
    $canonOk = ($canonSample !== $canonEnSample) && str_contains($canonEnSample, '/en/about');
    if (!$canonOk) {
        growth_record_alert('canonical_error', 'critical', 'ক্যানোনিকাল/hreflang রুট ত্রুটি', 'Canonical/hreflang mismatch detected', "Expected /about != /en/about; got {$canonSample} and {$canonEnSample}");
    }

    $gMeta = trim(site_setting('seo_google_verify', ''));
    $bMeta = trim(site_setting('seo_bing_verify', ''));
    $fileName = trim(site_setting('gsc_verify_file_name', ''));
    $fileContent = trim(site_setting('gsc_verify_file_content', ''));

    return [
        [
            'key'    => 'sitemap',
            'label'  => 'XML Sitemap Index & Child Sitemaps (/sitemap.xml)',
            'ok'     => $smEntries > 0,
            'detail' => "{$smEntries} bilingual route pairs indexed",
        ],
        [
            'key'    => 'robots',
            'label'  => 'Dynamic robots.txt (/en/ Disallow + Sitemap pointer)',
            'ok'     => $robotsOk,
            'detail' => $robotsOk ? 'Valid Disallow + Sitemap header present' : 'Missing expected directives',
        ],
        [
            'key'    => 'canonical',
            'label'  => 'Single Language-Specific Canonical & Trailing-Slash 301',
            'ok'     => $canonOk,
            'detail' => "{$canonSample} | {$canonEnSample}",
        ],
        [
            'key'    => 'hreflang',
            'label'  => 'Bilingual hreflang Alternates (bn-BD, en-BD, x-default)',
            'ok'     => !empty(($hfCodes = function_exists('seo_hreflang_codes') ? seo_hreflang_codes() : [])['bn']) && !empty($hfCodes['en']),
            'detail' => implode(', ', array_filter([$hfCodes['bn'] ?? '', $hfCodes['en'] ?? '', 'x-default'])),
        ],
        [
            'key'    => 'gsc_verify',
            'label'  => 'Google / Bing Verification (Meta Tag or HTML File)',
            'ok'     => $gMeta !== '' || $bMeta !== '' || ($fileName !== '' && $fileContent !== ''),
            'detail' => ($gMeta !== '' ? 'Google Meta OK' : 'No Google Meta') . ' · ' . ($fileName !== '' ? "HTML File: /{$fileName}" : 'No HTML File') . ' · ' . ($bMeta !== '' ? 'Bing Meta OK' : 'No Bing Meta'),
        ],
        [
            'key'    => 'indexnow',
            'label'  => 'IndexNow Host Key File (/' . growth_indexnow_key() . '.txt)',
            'ok'     => site_setting('indexnow_enabled', '1') === '1',
            'detail' => growth_indexnow_key_url(),
        ],
    ];
}

// ============================================================================
// 4. Growth Hooks: Double Opt-In Newsletter & Folder Subscriptions
// ============================================================================

function growth_newsletter_subscribe(string $email, string $lang = 'bn', string $sourcePath = '/'): array
{
    $email = strtolower(trim($email));
    if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return [
            'ok'      => false,
            'message' => $lang === 'bn' ? 'সঠিক ইমেইল ঠিকানা লিখুন।' : 'Please enter a valid email address.',
        ];
    }

    $existing = db_one('SELECT * FROM newsletter_subscribers WHERE email = ? LIMIT 1', [$email]);
    if ($existing && $existing['status'] === 'active') {
        return [
            'ok'      => true,
            'already' => true,
            'message' => $lang === 'bn' ? 'আপনি ইতিমধ্যে নিউজলেটারে যুক্ত আছেন।' : 'You are already confirmed on our newsletter!',
        ];
    }

    $rawToken = bin2hex(random_bytes(20));
    $hash = hash('sha256', $rawToken);
    $unsubTok = $existing && !empty($existing['unsubscribe_token']) ? (string) $existing['unsubscribe_token'] : bin2hex(random_bytes(16));
    $src = mb_substr(safe_internal_path($sourcePath, '/'), 0, 255);
    $lang = in_array($lang, ['bn', 'en'], true) ? $lang : 'bn';

    db_exec(
        "INSERT INTO newsletter_subscribers (email, lang, status, verify_token_hash, unsubscribe_token, source_path)
         VALUES (?, ?, 'pending', ?, ?, ?)
         ON DUPLICATE KEY UPDATE lang = VALUES(lang), status = 'pending', verify_token_hash = VALUES(verify_token_hash), source_path = VALUES(source_path), unsubscribed_at = NULL",
        [$email, $lang, $hash, $unsubTok, $src]
    );

    $confirmUrl = rtrim(BASE_URL, '/') . ($lang === 'en' ? '/en' : '') . '/newsletter/confirm?token=' . rawurlencode($rawToken);
    $siteName = site_setting('site_name', 'Bookexa');
    $subj = $lang === 'bn' ? "{$siteName} নিউজলেটার নিশ্চিত করুন" : "Confirm your {$siteName} newsletter subscription";
    $html = $lang === 'bn'
        ? "<p><strong>{$siteName}</strong> নিউজলেটারে সাইনআপ করার জন্য ধন্যবাদ! সাবস্ক্রিপশন নিশ্চিত করতে নিচের লিংকে ক্লিক করুন:</p><p><a href=\"{$confirmUrl}\">{$confirmUrl}</a></p>"
        : "<p>Thanks for subscribing to <strong>{$siteName}</strong>! Click the link below to confirm your subscription:</p><p><a href=\"{$confirmUrl}\">{$confirmUrl}</a></p>";

    $mailRes = smtp_send_mail($email, $subj, $html, $confirmUrl);
    $sent = !empty($mailRes['ok']);
    growth_record_analytics_event('subscribe', $src, ['lang' => $lang]);

    $msg = $lang === 'bn'
        ? 'আপনার ইমেইলে নিশ্চিতকরণ লিংক পাঠানো হয়েছে। ইনবক্স বা স্প্যাম ফোল্ডার দেখুন।'
        : 'Verification link sent! Please check your inbox to confirm your subscription.';
    if (!$sent) {
        $msg = $lang === 'bn'
            ? 'সাবস্ক্রিপশন অনুরোধ সংরক্ষিত হয়েছে (নিশ্চিতকরণ: ' . $confirmUrl . ')'
            : 'Subscription pending confirmation (SMTP not active in local mode).';
    }
    return [
        'ok'          => true,
        'sent'        => $sent,
        'confirm_url' => $sent ? null : $confirmUrl,
        'message'     => $msg,
    ];
}

function growth_newsletter_confirm(string $rawToken): array
{
    $rawToken = trim($rawToken);
    if ($rawToken === '' || strlen($rawToken) < 16) {
        return ['ok' => false, 'reason' => 'invalid'];
    }
    $hash = hash('sha256', $rawToken);
    $row = db_one('SELECT * FROM newsletter_subscribers WHERE verify_token_hash = ? LIMIT 1', [$hash]);
    if (!$row) {
        return ['ok' => false, 'reason' => 'not_found'];
    }
    db_exec(
        "UPDATE newsletter_subscribers SET status = 'active', verified_at = NOW(), verify_token_hash = NULL WHERE id = ?",
        [(int) $row['id']]
    );
    return ['ok' => true, 'email' => (string) $row['email'], 'lang' => (string) $row['lang']];
}

function growth_newsletter_unsubscribe(string $unsubToken): bool
{
    $tok = trim($unsubToken);
    if ($tok === '') {
        return false;
    }
    $row = db_one('SELECT id FROM newsletter_subscribers WHERE unsubscribe_token = ? LIMIT 1', [$tok]);
    if (!$row) {
        return false;
    }
    db_exec("UPDATE newsletter_subscribers SET status = 'unsubscribed', unsubscribed_at = NOW() WHERE id = ?", [(int) $row['id']]);
    return true;
}

function growth_user_subscribed_to_folder(int $userId, int $folderId): bool
{
    if ($userId <= 0 || $folderId <= 0) {
        return false;
    }
    return (bool) db_one('SELECT id FROM folder_subscriptions WHERE user_id = ? AND folder_id = ? LIMIT 1', [$userId, $folderId]);
}

function growth_folder_subscription_toggle(int $userId, int $folderId): array
{
    if ($userId <= 0 || $folderId <= 0) {
        return ['ok' => false, 'subscribed' => false];
    }
    $exists = growth_user_subscribed_to_folder($userId, $folderId);
    if ($exists) {
        db_exec('DELETE FROM folder_subscriptions WHERE user_id = ? AND folder_id = ?', [$userId, $folderId]);
        return ['ok' => true, 'subscribed' => false];
    }
    db_exec('INSERT IGNORE INTO folder_subscriptions (user_id, folder_id) VALUES (?, ?)', [$userId, $folderId]);
    return ['ok' => true, 'subscribed' => true];
}

function growth_notify_folder_subscribers(int $folderId, array $item): int
{
    if ($folderId <= 0) {
        return 0;
    }
    $folderIds = [$folderId];
    foreach (folder_breadcrumbs($folderId) as $bc) {
        $folderIds[] = (int) $bc['id'];
    }
    $folderIds = array_values(array_unique(array_filter($folderIds)));
    if (!$folderIds) {
        return 0;
    }
    $ph = implode(',', array_fill(0, count($folderIds), '?'));
    $subs = db_all("SELECT DISTINCT fs.user_id, u.email, u.name FROM folder_subscriptions fs JOIN users u ON u.id = fs.user_id WHERE fs.folder_id IN ({$ph}) AND u.is_active = 1", $folderIds);
    $notified = 0;
    $linkUrl = '/book/' . rawurlencode((string) ($item['slug'] ?? ''));
    $titleBn = 'নতুন বই যুক্ত হয়েছে: ' . (string) ($item['title_bn'] ?? '');
    $titleEn = 'New book added: ' . (string) ($item['title_en'] ?? $item['title_bn'] ?? '');
    foreach ($subs as $s) {
        create_notification((int) $s['user_id'], 'folder_new', $titleBn, $titleEn, 'আপনার ফলো করা ফোল্ডারে নতুন পিডিএফ প্রকাশিত হয়েছে।', 'A new PDF has been published in a folder you follow.', $linkUrl);
        $notified++;
    }
    return $notified;
}

// ============================================================================
// 5. Bangladesh Social Share Bar (Facebook, WhatsApp, Messenger, Telegram, Copy)
// ============================================================================

function render_social_share_bar(string $absUrl, string $title, string $label = '', bool $compact = false): string
{
    $lang = current_lang();
    $lbl = $label !== '' ? $label : ($lang === 'bn' ? 'শেয়ার করুন:' : 'Share:');
    $fbUrl  = 'https://www.facebook.com/sharer/sharer.php?u=' . rawurlencode($absUrl);
    $waUrl  = 'https://wa.me/?text=' . rawurlencode($title . ' — ' . $absUrl);
    $msgUrl = 'fb-messenger://share/?link=' . rawurlencode($absUrl);
    $tgUrl  = 'https://t.me/share/url?url=' . rawurlencode($absUrl) . '&text=' . rawurlencode($title);
    $copiedMsg = $lang === 'bn' ? 'লিংক কপি হয়েছে!' : 'Link copied!';
    $cls = $compact ? 'bx-share-bar is-compact' : 'bx-share-bar';

    ob_start(); ?>
    <div class="<?= $cls ?>" aria-label="<?= e($lang === 'bn' ? 'সোশ্যাল শেয়ার' : 'Social share') ?>">
        <span class="bx-share-label"><?= e($lbl) ?></span>
        <a href="<?= e($fbUrl) ?>" target="_blank" rel="noopener noreferrer" class="bx-btn bx-btn-ghost bx-btn-sm" data-share-channel="facebook">Facebook</a>
        <a href="<?= e($waUrl) ?>" target="_blank" rel="noopener noreferrer" class="bx-btn bx-btn-ghost bx-btn-sm" data-share-channel="whatsapp">WhatsApp</a>
        <a href="<?= e($msgUrl) ?>" class="bx-btn bx-btn-ghost bx-btn-sm" data-messenger-share data-share-url="<?= e($absUrl) ?>" data-share-title="<?= e($title) ?>" data-copied-label="<?= e($copiedMsg) ?>">Messenger</a>
        <a href="<?= e($tgUrl) ?>" target="_blank" rel="noopener noreferrer" class="bx-btn bx-btn-ghost bx-btn-sm" data-share-channel="telegram">Telegram</a>
        <button type="button" class="bx-btn bx-btn-ghost bx-btn-sm" data-copy-link="<?= e($absUrl) ?>" data-copied-label="<?= e($copiedMsg) ?>">
            <?= $lang === 'bn' ? 'লিংক কপি' : 'Copy Link' ?>
        </button>
    </div>
    <?php
    return (string) ob_get_clean();
}
