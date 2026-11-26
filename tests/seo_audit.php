<?php
declare(strict_types=1);

/**
 * Bookexa Technical SEO Live Crawler & Audit Report
 * Usage: php tests/seo_audit.php [base_url]
 * Crawls /sitemap.xml -> child sitemaps -> every discovered live URL and checks:
 *  - HTTP status codes (expects 200)
 *  - Non-empty, unique <title> (30-60 target warning / >0 required) & <meta description>
 *  - Canonical link consistency & single <h1>
 *  - All <img> tags have alt attribute
 *  - Zero broken internal <a href> links
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('CLI only.');
}

require_once dirname(__DIR__) . '/includes/bootstrap.php';

$base = rtrim($argv[1] ?? 'http://localhost/bookexa', '/');
$failCount = 0;
$warnCount = 0;
$passCount = 0;

function crawl_http(string $url): array
{
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HEADER         => true,
        CURLOPT_FOLLOWLOCATION => false,
        CURLOPT_TIMEOUT        => 20,
        CURLOPT_USERAGENT      => 'Bookexa-SEO-Crawler/1.0',
    ]);
    $raw = (string) curl_exec($ch);
    $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $hlen = (int) curl_getinfo($ch, CURLINFO_HEADER_SIZE);
    $err = curl_error($ch);
    curl_close($ch);
    return ['code' => $code, 'headers' => substr($raw, 0, $hlen), 'body' => substr($raw, $hlen), 'err' => $err];
}

function audit_ok(string $msg): void
{
    global $passCount;
    $passCount++;
    echo "  [OK] {$msg}\n";
}

function audit_warn(string $msg): void
{
    global $warnCount;
    $warnCount++;
    echo "  [WARN] {$msg}\n";
}

function audit_fail(string $msg): void
{
    global $failCount;
    $failCount++;
    echo "  [FAIL] {$msg}\n";
}

echo "=== Bookexa Technical SEO Audit: {$base} ===\n";

// 0. Check IndexNow verification host key file
$inKey = function_exists('growth_indexnow_key') ? growth_indexnow_key() : '';
if ($inKey !== '') {
    $inResp = crawl_http("{$base}/{$inKey}.txt");
    if ($inResp['code'] === 200 && trim($inResp['body']) === $inKey) {
        audit_ok("/{$inKey}.txt returns 200 and matches IndexNow host verification key");
    } else {
        audit_fail("/{$inKey}.txt verification route failed (code={$inResp['code']})");
    }
}

// 1. Check robots.txt
$robots = crawl_http("{$base}/robots.txt");
if ($robots['code'] === 200 && str_contains($robots['body'], 'Sitemap: ') && str_contains($robots['body'], 'Disallow: /en/admin')) {
    audit_ok('/robots.txt returns 200 with bilingual Disallow & real Sitemap URL');
} else {
    audit_fail('/robots.txt failed check (code=' . $robots['code'] . ')');
}

// 2. Fetch Sitemap Index & discover child sitemaps + all URLs
$smIdx = crawl_http("{$base}/sitemap.xml");
if ($smIdx['code'] !== 200 || !str_contains($smIdx['body'], '<sitemapindex')) {
    audit_fail('/sitemap.xml failed or invalid sitemapindex (code=' . $smIdx['code'] . ')');
    exit(1);
}

$idxXml = @simplexml_load_string($smIdx['body']);
$childSitemaps = [];
if ($idxXml) {
    foreach ($idxXml->sitemap ?? [] as $sm) {
        $childSitemaps[] = (string) $sm->loc;
    }
}
echo "Discovered " . count($childSitemaps) . " child sitemap(s).\n";

$urlsToCrawl = [];
foreach ($childSitemaps as $smUrl) {
    $smResp = crawl_http($smUrl);
    if ($smResp['code'] !== 200 || !str_contains($smResp['body'], '<urlset')) {
        audit_fail("Child sitemap failed: {$smUrl} (code={$smResp['code']})");
        continue;
    }
    $uXml = @simplexml_load_string($smResp['body']);
    $countHere = 0;
    if ($uXml) {
        foreach ($uXml->url ?? [] as $uNode) {
            $loc = (string) $uNode->loc;
            if ($loc !== '') {
                $urlsToCrawl[$loc] = true;
                $countHere++;
            }
        }
    }
    audit_ok("Child sitemap {$smUrl} valid ({$countHere} URL(s))");
}

// Cap live audit crawl at 80 URLs in case DB is huge; always inspect all if <= 80
$allUrls = array_keys($urlsToCrawl);
$crawlSample = array_slice($allUrls, 0, 80);
echo "Crawling " . count($crawlSample) . " URL(s) from sitemaps...\n";

$seenTitles = [];
$seenDescs  = [];
$duplicateTitleCount = 0;
$missingTitleCount = 0;
$missingDescCount = 0;
$schemaValidCount = 0;
$schemaInvalidCount = 0;
$schemaTypesSeen = [];
$mobileFriendlyOk = 0;
$internalLinksChecked = [];
$brokenLinks = [];

foreach ($crawlSample as $pageUrl) {
    $resp = crawl_http($pageUrl);
    if ($resp['code'] !== 200) {
        audit_fail("{$pageUrl} returned HTTP {$resp['code']}");
        continue;
    }
    $html = $resp['body'];

    // Title
    if (!preg_match('/<title[^>]*>(.*?)<\/title>/is', $html, $tm) || trim($tm[1]) === '') {
        $missingTitleCount++;
        audit_fail("{$pageUrl} missing <title>");
    } else {
        $tVal = html_entity_decode(trim($tm[1]), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $tLen = mb_strlen($tVal, 'UTF-8');
        if (isset($seenTitles[$tVal])) {
            $duplicateTitleCount++;
            audit_fail("{$pageUrl} duplicate <title>: '{$tVal}' (also on {$seenTitles[$tVal]})");
        }
        $seenTitles[$tVal] = $pageUrl;
        if ($tLen > SEO_TITLE_MAX) {
            audit_warn("{$pageUrl} <title> is {$tLen} chars (> 60): '{$tVal}'");
        }
    }

    // Meta Description
    if (!preg_match('/<meta\s+name="description"\s+content="([^"]*)"/i', $html, $dm) || trim($dm[1]) === '') {
        $missingDescCount++;
        audit_fail("{$pageUrl} missing meta description");
    } else {
        $dVal = html_entity_decode(trim($dm[1]), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $dLen = mb_strlen($dVal, 'UTF-8');
        if (isset($seenDescs[$dVal])) {
            audit_fail("{$pageUrl} duplicate meta description (also on {$seenDescs[$dVal]})");
        }
        $seenDescs[$dVal] = $pageUrl;
        if ($dLen > SEO_DESC_MAX) {
            audit_warn("{$pageUrl} meta description is {$dLen} chars (> 155)");
        }
    }

    // Canonical
    if (!preg_match('/<link\s+rel="canonical"\s+href="([^"]+)"/i', $html, $cm)) {
        audit_fail("{$pageUrl} missing canonical tag");
    } elseif ($cm[1] !== $pageUrl) {
        audit_fail("{$pageUrl} canonical mismatch: got {$cm[1]}");
    }

    // H1 count
    preg_match_all('/<h1\b[^>]*>.*?<\/h1>/is', $html, $h1m);
    $h1Count = count($h1m[0] ?? []);
    if ($h1Count !== 1) {
        audit_fail("{$pageUrl} has {$h1Count} <h1> tag(s) (expected exactly 1)");
    }

    // Images missing alt attribute
    preg_match_all('/<img\b[^>]*>/is', $html, $imMatches);
    foreach ($imMatches[0] ?? [] as $imgTag) {
        if (!preg_match('/\balt=/i', $imgTag)) {
            audit_fail("{$pageUrl} <img> missing alt attribute: " . substr($imgTag, 0, 80));
            break;
        }
    }

    // JSON-LD schema validation
    if (preg_match_all('/<script\s+type="application\/ld\+json"[^>]*>(.*?)<\/script>/is', $html, $ldMatches)) {
        foreach ($ldMatches[1] as $rawJson) {
            $dec = json_decode(trim($rawJson), true);
            if (!is_array($dec) || json_last_error() !== JSON_ERROR_NONE) {
                $schemaInvalidCount++;
                audit_fail("{$pageUrl} invalid JSON-LD block: " . json_last_error_msg());
            } else {
                $schemaValidCount++;
                $graph = isset($dec['@graph']) && is_array($dec['@graph']) ? $dec['@graph'] : [$dec];
                foreach ($graph as $node) {
                    if (is_array($node) && !empty($node['@type'])) {
                        foreach ((array) $node['@type'] as $tp) {
                            $schemaTypesSeen[(string) $tp] = ($schemaTypesSeen[(string) $tp] ?? 0) + 1;
                        }
                    }
                }
            }
        }
    }

    // Mobile-friendliness basics: viewport width=device-width + no non-responsive fixed 4-digit layout widths
    $hasViewport = (bool) preg_match('/<meta\s+name="viewport"[^>]*content="[^"]*width=device-width/i', $html);
    $hasWideAttr = (bool) preg_match('/<(?:table|div|section|iframe)\b[^>]*\bwidth=["\']?\d{4,}["\']?/i', $html)
        || (bool) preg_match('/\bmin-width\s*:\s*\d{4,}px/i', $html);
    if ($hasViewport && !$hasWideAttr) {
        $mobileFriendlyOk++;
    } else {
        audit_fail("{$pageUrl} failed mobile-friendly viewport/width check");
    }

    // Collect internal links to check for 404s
    preg_match_all('/<a\b[^>]*href="([^"#?][^"]*)"/is', $html, $am);
    $baseParts = parse_url($base);
    $basePathPrefix = $baseParts['path'] ?? '';
    foreach ($am[1] ?? [] as $href) {
        if (str_starts_with($href, 'mailto:') || str_starts_with($href, 'tel:') || str_starts_with($href, 'javascript:')) {
            continue;
        }
        if (str_starts_with($href, 'http://') || str_starts_with($href, 'https://')) {
            if (!str_starts_with($href, $base)) {
                continue; // external link
            }
            $checkUrl = $href;
        } elseif ($basePathPrefix !== '' && str_starts_with($href, $basePathPrefix . '/')) {
            $checkUrl = $baseParts['scheme'] . '://' . $baseParts['host'] . (isset($baseParts['port']) ? ':' . $baseParts['port'] : '') . $href;
        } elseif (str_starts_with($href, '/')) {
            $checkUrl = $base . $href;
        } else {
            continue;
        }
        $internalLinksChecked[$checkUrl] = $pageUrl;
    }
}

echo "Verifying " . min(100, count($internalLinksChecked)) . " unique internal <a href> target(s)...\n";
$checkedN = 0;
foreach (array_slice($internalLinksChecked, 0, 100, true) as $target => $foundOn) {
    $checkedN++;
    $lr = crawl_http($target);
    if ($lr['code'] >= 400 || $lr['code'] === 0) {
        $brokenLinks[] = "{$target} (HTTP {$lr['code']} linked from {$foundOn})";
    }
}
if ($brokenLinks) {
    foreach ($brokenLinks as $bl) {
        audit_fail("Broken internal link: {$bl}");
    }
} else {
    audit_ok("All {$checkedN} checked internal links returned 2xx/3xx (0 broken)");
}

// Check Canonical 301 normalization + 404/410 + Bangla transliteration live
$canonCase = crawl_http("{$base}/about/");
if ($canonCase['code'] === 301 && !str_contains($canonCase['headers'], '/about/')) {
    audit_ok('Trailing-slash normalization (/about/ -> 301 /about)');
} else {
    audit_fail('Trailing-slash normalization failed on /about/ (code=' . $canonCase['code'] . ')');
}

$dupParamCase = crawl_http("{$base}/pdf?sort=newest&sort=popular");
if ($dupParamCase['code'] === 301 && str_contains($dupParamCase['headers'], 'sort=popular')) {
    audit_ok('Duplicate query parameter normalization returns 301 canonical URL');
} else {
    audit_fail('Duplicate query parameter normalization failed (code=' . $dupParamCase['code'] . ')');
}

$notFoundCase = crawl_http("{$base}/non-existent-seo-probe-" . bin2hex(random_bytes(3)));
if ($notFoundCase['code'] === 404 && str_contains($notFoundCase['body'], 'name="q"')) {
    audit_ok('Unknown route returns real HTTP 404 with search box & popular folders');
} else {
    audit_fail('404 probe failed (code=' . $notFoundCase['code'] . ')');
}

if ($schemaInvalidCount === 0 && $schemaValidCount > 0) {
    audit_ok("JSON-LD schema valid across {$schemaValidCount} block(s); types: " . implode(', ', array_keys($schemaTypesSeen)));
} else {
    audit_fail("JSON-LD validation failed ({$schemaInvalidCount} invalid, {$schemaValidCount} valid)");
}
audit_ok("Mobile-friendly viewport & width basics passed on {$mobileFriendlyOk}/" . count($crawlSample) . " crawled page(s)");

echo "\n--- INDEXABLE & SEARCH CONSOLE FINAL REPORT ---\n";
echo "  Indexable Sitemap URLs : " . count($allUrls) . "\n";
echo "  Duplicate Titles Found : {$duplicateTitleCount}\n";
echo "  Missing Titles / Descs : {$missingTitleCount} / {$missingDescCount}\n";
echo "  Valid JSON-LD Blocks   : {$schemaValidCount} (Invalid: {$schemaInvalidCount})\n";
if (function_exists('growth_search_console_checklist')) {
    echo "  Search Console Checklist:\n";
    foreach (growth_search_console_checklist() as $chk) {
        $mark = $chk['ok'] ? 'PASS' : 'TODO';
        echo "    [{$mark}] {$chk['label']} — {$chk['detail']}\n";
    }
}

echo "\nAUDIT SUMMARY: {$passCount} passed, {$warnCount} warning(s), {$failCount} failed.\n";
exit($failCount === 0 ? 0 : 1);
