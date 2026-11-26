<?php
declare(strict_types=1);

/**
 * Bookexa Technical SEO Foundation
 * - Bangla phonetic ASCII transliteration for share-safe slugs
 * - Single canonical URL normalizer & custom redirect / 410 engine
 * - Bangla-aware meta title (30-60) & description (70-155) template engine
 * - 1200x630 cached GD social card generator
 * - Schema.org JSON-LD builders + Admin validator
 * - Author directory auto-sync & chunked bilingual image sitemap generator
 */

const SEO_TITLE_MIN = 30;
const SEO_TITLE_MAX = 60;
const SEO_DESC_MIN  = 70;
const SEO_DESC_MAX  = 155;
const SEO_SITEMAP_CHUNK_SIZE = 45000; // safely below Google's 50,000 URL & 50MB limit

/**
 * Convert Bangla Unicode text + punctuation into clean ASCII phonetic text for URL slugs.
 */
function seo_transliterate_bn_to_ascii(string $text): string
{
    $text = trim($text);
    if ($text === '') {
        return '';
    }

    // Normalize decomposed nukta characters first
    $text = str_replace(
        ["\u{09A1}\u{09BC}", "\u{09A2}\u{09BC}", "\u{09AF}\u{09BC}"],
        ['ড়', 'ঢ়', 'য়'],
        $text
    );

    // High-frequency academic/book domain vocabulary -> crisp English slugs
    $wordDict = [
        'বিসিএস'         => ' bcs ',
        'প্রিলিমিনারি'    => ' preliminary ',
        'লিখিত'          => ' written ',
        'ভাইভা'          => ' viva ',
        'বাংলা'          => ' bangla ',
        'ইংরেজি'         => ' english ',
        'গণিত'           => ' math ',
        'উচ্চতর'         => ' higher ',
        'সাধারণ'         => ' general ',
        'জ্ঞান'          => ' knowledge ',
        'বিজ্ঞান'        => ' science ',
        'পদার্থবিজ্ঞান'  => ' physics ',
        'পদার্থ'         => ' physics ',
        'রসায়ন'         => ' chemistry ',
        'রসায়ন'          => ' chemistry ',
        'জীববিজ্ঞান'     => ' biology ',
        'আইসিটি'         => ' ict ',
        'কম্পিউটার'      => ' computer ',
        'ইতিহাস'         => ' history ',
        'ভূগোল'          => ' geography ',
        'অর্থনীতি'       => ' economics ',
        'পৌরনীতি'        => ' civics ',
        'হিসাববিজ্ঞান'   => ' accounting ',
        'ব্যবসায়'        => ' business ',
        'ব্যবসায়'       => ' business ',
        'ইসলাম'          => ' islam ',
        'এসএসসি'         => ' ssc ',
        'এইচএসসি'        => ' hsc ',
        'জেএসসি'         => ' jsc ',
        'অনার্স'         => ' honours ',
        'ডিগ্রি'         => ' degree ',
        'মাস্টার্স'      => ' masters ',
        'বিশ্ববিদ্যালয়' => ' university ',
        'বিশ্ববিদ্যালয়' => ' university ',
        'মেডিকেল'        => ' medical ',
        'ইঞ্জিনিয়ারিং'  => ' engineering ',
        'ইঞ্জিনিয়ারিং'  => ' engineering ',
        'ভর্তি'          => ' admission ',
        'প্রস্তুতি'      => ' preparation ',
        'ব্যাংক'         => ' bank ',
        'প্রাইমারি'      => ' primary ',
        'নিবন্ধন'        => ' registration ',
        'শিক্ষক'         => ' teacher ',
        'অধ্যায়'        => ' chapter ',
        'অধ্যায়'        => ' chapter ',
        'প্রথম'          => ' first ',
        'দ্বিতীয়'       => ' second ',
        'দ্বিতীয়'       => ' second ',
        'পত্র'           => ' paper ',
        'বই'             => ' book ',
        'পিডিএফ'         => ' pdf ',
        'গাইড'           => ' guide ',
        'নোট'            => ' note ',
        'হ্যান্ডনোট'     => ' handnote ',
        'সাজেশন'         => ' suggestion ',
        'প্রশ্ন'         => ' question ',
        'উত্তর'          => ' answer ',
        'সমাধান'         => ' solution ',
        'মডেল'           => ' model ',
        'টেস্ট'          => ' test ',
        'ব্যাকরণ'        => ' grammar ',
        'সাহিত্য'        => ' literature ',
        'রচনা'           => ' essay ',
        'ড্রয়িং'         => ' drawing ',
        'ড্রইং'          => ' drawing ',
        'উপন্যাস'        => ' novel ',
        'গল্প'           => ' story ',
        'কবিতা'          => ' poem ',
        'সকল'            => ' all ',
        'এবং'            => ' and ',
        'ও'              => ' o ',
    ];
    $text = strtr($text, $wordDict);

    // Replace Bangla digits & common punctuation
    $text = strtr($text, [
        '০' => '0', '১' => '1', '২' => '2', '৩' => '3', '৪' => '4',
        '৫' => '5', '৬' => '6', '৭' => '7', '৮' => '8', '৯' => '9',
        '।' => ' ', '৳' => ' ', '—' => '-', '–' => '-',
    ]);

    // Multi-char conjuncts before per-character syllable pass
    $conjuncts = [
        'ক্ষ' => 'ksh',
        'জ্ঞ' => 'gy',
        'ঞ্জ' => 'nj',
        'ঞ্চ' => 'nch',
        'ঙ্ক' => 'nk',
        'ঙ্গ' => 'ng',
        'ন্ত' => 'nt',
        'ন্দ' => 'nd',
        'ন্ধ' => 'ndh',
        'ম্প' => 'mp',
        'ম্ব' => 'mb',
        'ম্ভ' => 'mbh',
        'স্ত' => 'st',
        'স্থ' => 'sth',
        'স্প' => 'sp',
        'স্ক' => 'sk',
        'ষ্ট' => 'sht',
        'ষ্ঠ' => 'shth',
        'হ্ম' => 'hm',
        '্র'  => 'r',
        '্য'  => 'y',
        '্ব'  => 'w',
    ];
    $text = strtr($text, $conjuncts);

    $vowels = [
        'অ' => 'o', 'আ' => 'a', 'ই' => 'i', 'ঈ' => 'ee',
        'উ' => 'u', 'ঊ' => 'oo', 'ঋ' => 'ri', 'এ' => 'e',
        'ঐ' => 'oi', 'ও' => 'o', 'ঔ' => 'ou',
    ];
    $matras = [
        'া' => 'a', 'ি' => 'i', 'ী' => 'i', 'ু' => 'u', 'ূ' => 'u',
        'ৃ' => 'ri', 'ে' => 'e', 'ৈ' => 'oi', 'ো' => 'o', 'ৌ' => 'ou',
        'ং' => 'ng', 'ঃ' => 'h', 'ঁ' => '',
    ];
    $consonants = [
        'ক' => 'k', 'খ' => 'kh', 'গ' => 'g', 'ঘ' => 'gh', 'ঙ' => 'ng',
        'চ' => 'ch', 'ছ' => 'chh', 'জ' => 'j', 'ঝ' => 'jh', 'ঞ' => 'n',
        'ট' => 't', 'ঠ' => 'th', 'ড' => 'd', 'ঢ' => 'dh', 'ণ' => 'n',
        'ত' => 't', 'থ' => 'th', 'দ' => 'd', 'ধ' => 'dh', 'ন' => 'n',
        'প' => 'p', 'ফ' => 'ph', 'ব' => 'b', 'ভ' => 'bh', 'ম' => 'm',
        'য' => 'j', 'র' => 'r', 'ল' => 'l', 'শ' => 'sh', 'ষ' => 'sh',
        'স' => 's', 'হ' => 'h', 'ড়' => 'r', 'ঢ়' => 'rh', 'য়' => 'y',
        'ৎ' => 't', 'য়' => 'y', 'ড়' => 'r', 'ঢ়' => 'rh',
    ];

    $chars = preg_split('//u', $text, -1, PREG_SPLIT_NO_EMPTY) ?: [];
    $len = count($chars);
    $out = '';
    for ($i = 0; $i < $len; $i++) {
        $ch = $chars[$i];
        $next = $chars[$i + 1] ?? '';

        if ($ch === '্') {
            continue;
        }
        if (isset($vowels[$ch])) {
            $out .= $vowels[$ch];
            continue;
        }
        if (isset($matras[$ch])) {
            $out .= $matras[$ch];
            continue;
        }
        if (isset($consonants[$ch])) {
            $out .= $consonants[$ch];
            // Add inherent vowel 'o' when followed inside a Bangla syllable by another consonant/vowel (not halant/matra/space/end)
            if (
                $next !== ''
                && $next !== '্'
                && !isset($matras[$next])
                && (isset($consonants[$next]) || isset($vowels[$next]))
            ) {
                $out .= 'o';
            }
            continue;
        }
        // Transliterate accented Latin letters if iconv is available
        if (ord($ch) > 127 && function_exists('iconv')) {
            $tr = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $ch);
            $out .= is_string($tr) ? $tr : '';
        } else {
            $out .= $ch;
        }
    }

    return $out;
}

/**
 * Collapse duplicate query parameters cleanly while keeping first-seen key order and last value.
 * Strips empty tracking noise (utm_*, fbclid, gclid).
 */
function seo_clean_query_string(string $rawQs): array
{
    if ($rawQs === '') {
        return ['clean' => '', 'changed' => false];
    }
    $pairs = explode('&', $rawQs);
    $map = [];
    $seenKeys = [];
    $changed = false;
    $trackingStrip = ['fbclid', 'gclid', 'mc_cid', 'mc_eid'];

    foreach ($pairs as $pair) {
        if ($pair === '') {
            $changed = true;
            continue;
        }
        $eq = strpos($pair, '=');
        $rawKey = $eq === false ? $pair : substr($pair, 0, $eq);
        $rawVal = $eq === false ? '' : substr($pair, $eq + 1);
        $key = rawurldecode($rawKey);
        $keyLow = strtolower($key);
        if (str_starts_with($keyLow, 'utm_') || in_array($keyLow, $trackingStrip, true)) {
            $changed = true;
            continue;
        }
        if (isset($seenKeys[$key])) {
            $changed = true;
        }
        $seenKeys[$key] = true;
        $map[$key] = rawurldecode($rawVal);
    }

    // Strip redundant page=1 when browsing pure paginated archives
    if (isset($map['page']) && $map['page'] === '1' && count($map) === 1) {
        unset($map['page']);
        $changed = true;
    }

    $rebuilt = http_build_query($map, '', '&', PHP_QUERY_RFC3986);
    if (!$changed && $rebuilt !== $rawQs) {
        // Also normalize raw encoding only when duplicate/trailing issues were found; keep safe raw matches
    }
    return ['clean' => $rebuilt, 'changed' => $changed];
}

/**
 * Enforce:
 * 1. Custom Admin Redirect / 410 Gone rules (seo_redirects)
 * 2. Single Canonical URL Form:
 *    - Collapse multiple slashes // -> /
 *    - Lowercase path (ASCII uppercase -> 301 lowercase)
 *    - Consistent no-trailing-slash policy on non-root paths -> 301
 *    - Deduplicated query parameters -> 301
 */
function seo_normalize_request_and_redirect(): void
{
    $method = strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'));
    if ($method !== 'GET' && $method !== 'HEAD') {
        return;
    }

    $reqUri = (string) ($_SERVER['REQUEST_URI'] ?? '/');
    $qPos = strpos($reqUri, '?');
    $rawPath = $qPos === false ? $reqUri : substr($reqUri, 0, $qPos);
    $rawQs   = $qPos === false ? '' : substr($reqUri, $qPos + 1);

    if (BASE_PATH !== '' && str_starts_with($rawPath, BASE_PATH)) {
        $relPath = substr($rawPath, strlen(BASE_PATH));
    } else {
        $relPath = $rawPath;
    }
    if ($relPath === '') {
        $relPath = '/';
    }

    // Skip technical endpoints, admin panel, assets, XML/txt endpoints
    foreach (['/api/', '/admin', '/reader', '/storage/', '/assets/', '/uploads/', '/og-image/', '/download/', '/debug'] as $skipPrefix) {
        if ($relPath === rtrim($skipPrefix, '/') || str_starts_with($relPath, $skipPrefix)) {
            return;
        }
    }
    if (preg_match('/\.(xml|txt|json|css|js|png|jpe?g|webp|svg|ico|woff2|webmanifest)$/i', $relPath)) {
        return;
    }

    // Check custom redirects FIRST (normalized & exact match)
    $lookupClean = '/' . trim($relPath, '/');
    if ($lookupClean === '//') {
        $lookupClean = '/';
    }
    $lookupLower = function_exists('mb_strtolower') ? mb_strtolower($lookupClean, 'UTF-8') : strtolower($lookupClean);
    try {
        $rule = db_one(
            'SELECT * FROM seo_redirects WHERE from_path = ? OR from_path = ? LIMIT 1',
            [$lookupClean, $lookupLower]
        );
        if ($rule) {
            db_exec('UPDATE seo_redirects SET hits = hits + 1 WHERE id = ?', [(int) $rule['id']]);
            $code = (int) $rule['status_code'];
            if ($code === 410) {
                header('X-Robots-Tag: noindex, nofollow');
                render_error_page(410);
            }
            $dest = trim((string) ($rule['to_path'] ?? ''));
            if ($dest !== '') {
                $targetUrl = preg_match('#^https?://#i', $dest)
                    ? $dest
                    : (BASE_URL . safe_internal_path($dest, '/'));
                if ($rawQs !== '' && !str_contains($targetUrl, '?')) {
                    $targetUrl .= '?' . $rawQs;
                }
                header('Location: ' . $targetUrl, true, $code === 302 ? 302 : 301);
                exit;
            }
        }
    } catch (Throwable $e) {
    }

    // Build canonical normalized path (collapse //, strip trailing / except /, lowercase path)
    $collapsed = preg_replace('#/+#', '/', $relPath) ?: '/';
    $normPath = strlen($collapsed) > 1 ? rtrim($collapsed, '/') : $collapsed;
    $normLower = function_exists('mb_strtolower') ? mb_strtolower($normPath, 'UTF-8') : strtolower($normPath);
    if ($normLower === '') {
        $normLower = '/';
    }

    $qsInfo = seo_clean_query_string($rawQs);
    $pathChanged = ($relPath !== $normLower);
    if ($pathChanged || $qsInfo['changed']) {
        $target = BASE_URL . $normLower . ($qsInfo['clean'] !== '' ? ('?' . $qsInfo['clean']) : '');
        header('Location: ' . $target, true, 301);
        exit;
    }
}

/**
 * Log 404 misses cleanly (ignores automated probes for binaries/sourcemaps/assets).
 */
function seo_log_404(string $path): void
{
    $clean = substr(trim($path), 0, 250);
    if ($clean === '' || preg_match('/\.(map|ico|png|jpe?g|css|js|env|php)$/i', $clean)) {
        return;
    }
    $ref = substr((string) ($_SERVER['HTTP_REFERER'] ?? ''), 0, 250);
    try {
        db_exec(
            'INSERT INTO seo_404_logs (path, referrer, hits, last_seen_at) VALUES (?, ?, 1, NOW())
             ON DUPLICATE KEY UPDATE hits = hits + 1, referrer = COALESCE(NULLIF(?, \'\'), referrer), last_seen_at = NOW()',
            [$clean, $ref !== '' ? $ref : null, $ref]
        );
    } catch (Throwable $e) {
    }
}

/**
 * Word-boundary UTF-8 clamping (Bangla-safe) keeping text strictly <= $maxLen chars.
 */
function seo_clamp_text(string $text, int $minLen, int $maxLen, string $padTail = ''): string
{
    $clean = trim(preg_replace('/\s+/u', ' ', strip_tags(html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8'))) ?? '');
    if ($clean === '') {
        return '';
    }
    if (mb_strlen($clean, 'UTF-8') < $minLen && $padTail !== '' && !str_contains($clean, $padTail)) {
        $candidate = rtrim($clean, " .-—|,:") . ' — ' . trim($padTail);
        $clean = $candidate;
    }
    if (mb_strlen($clean, 'UTF-8') <= $maxLen) {
        return $clean;
    }
    $slice = mb_substr($clean, 0, $maxLen, 'UTF-8');
    $lastSpace = mb_strrpos($slice, ' ', 0, 'UTF-8');
    if ($lastSpace !== false && $lastSpace >= (int) floor($maxLen * 0.6)) {
        $slice = mb_substr($slice, 0, $lastSpace, 'UTF-8');
    }
    return preg_replace('/[\s,;:\-|—–।]+$/u', '', $slice) ?? $slice;
}

/**
 * Return configured hreflang codes respecting seo_default_region (e.g. bn-BD / en-BD + bn / en).
 */
function seo_hreflang_codes(): array
{
    $reg = strtoupper(preg_replace('/[^A-Za-z]/', '', site_setting('seo_default_region', 'BD')) ?? 'BD');
    if ($reg === '') {
        return ['bn' => 'bn', 'en' => 'en', 'region' => ''];
    }
    return ['bn' => 'bn-' . $reg, 'en' => 'en-' . $reg, 'region' => $reg];
}

/**
 * Default per-page-type templates when DB settings are empty.
 */
function seo_default_templates(): array
{
    return [
        'home' => [
            'title_bn' => '{site} — নিরাপদ ডিজিটাল বই ও একাডেমিক পিডিএফ',
            'title_en' => '{site} — Digital Books & Academic PDF Library',
            'desc_bn'  => 'বিসিএস, ভর্তি, এসএসসি ও এইচএসসি পিডিএফ বই ও স্টাডি গাইড নিরাপদে পড়ুন {site}-এ।',
            'desc_en'  => 'Read protected academic PDFs, BCS prep books, and order printed books online across Bangladesh on {site}.',
        ],
        'folder' => [
            'title_bn' => '{title} — পিডিএফ বই ও নোট | {site}',
            'title_en' => '{title} — Academic PDFs & Notes | {site}',
            'desc_bn'  => '{title} ক্যাটাগরির সেরা একাডেমিক পিডিএফ বই ও স্টাডি নোট নিরাপদে পড়ুন ও সংগ্রহ করুন {site}-এ।',
            'desc_en'  => 'Explore {title} study PDFs, notes, and guides. Preview sample pages securely online on {site}.',
        ],
        'book' => [
            'title_bn' => '{title} — {author} | {site}',
            'title_en' => '{title} by {author} | {site}',
            'desc_bn'  => '{title} ({author}) — {folder}। মূল্য {price}। ফ্রি স্যাম্পল পেজ পড়ুন ও আনলক করুন {site}-এ।',
            'desc_en'  => 'Read {title} by {author} in {folder} ({price}). Preview sample pages securely online on {site}.',
        ],
        'shop_product' => [
            'title_bn' => '{title} — {price} | {site}',
            'title_en' => '{title} ({price}) | {site}',
            'desc_bn'  => '{title} ({author}) প্রিন্টেড বই অর্ডার করুন {price}-এ। সারা বাংলাদেশে দ্রুত হোম ডেলিভারি {site}-এ।',
            'desc_en'  => 'Order printed copy of {title} by {author} for {price} with fast home delivery across Bangladesh on {site}.',
        ],
        'guide' => [
            'title_bn' => '{title} — স্টাডি গাইড | {site}',
            'title_en' => '{title} — Study Guide | {site}',
            'desc_bn'  => '{title} সম্পর্কে বিস্তারিত প্রস্তুতি কৌশল, স্টাডি গাইড ও প্রয়োজনীয় বইয়ের তালিকা পড়ুন {site}-এ।',
            'desc_en'  => 'Read {title}: detailed preparation guide, curated reading tips, and study resources on {site}.',
        ],
        'author' => [
            'title_bn' => '{author} — সকল বই ও প্রকাশনা | {site}',
            'title_en' => '{author} — Books & Publications | {site}',
            'desc_bn'  => '{author}-এর সকল একাডেমিক পিডিএফ ও প্রিন্টেড বইয়ের সম্পূর্ণ তালিকা দেখুন ও পড়ুন {site}-এ।',
            'desc_en'  => 'Browse all digital PDFs and printed books written by {author} available on {site}.',
        ],
        'search' => [
            'title_bn' => 'খুঁজুন: {title} | {site}',
            'title_en' => 'Search: {title} | {site}',
            'desc_bn'  => '{site}-এ একাডেমিক পিডিএফ বই, লেখক ও স্টাডি গাইড মুহূর্তেই খুঁজে নিন এবং অনলাইনে নিরাপদে পড়া শুরু করুন।',
            'desc_en'  => 'Search academic PDFs, books, authors, and study notes quickly on {site} across Bangladesh.',
        ],
        'static' => [
            'title_bn' => '{title} | {site}',
            'title_en' => '{title} | {site}',
            'desc_bn'  => '{title} সম্পর্কিত বিস্তারিত তথ্য, নীতিমালা এবং পাঠক সহায়তা নির্দেশিকা দেখুন {site} প্ল্যাটফর্মে।',
            'desc_en'  => 'Read important information, platform policies, and reader support details for {title} on {site}.',
        ],
    ];
}

/**
 * Substitute template variables safely and clean empty punctuation artifacts.
 */
function seo_apply_template(string $tpl, array $vars): string
{
    $rep = [
        '{title}'  => trim((string) ($vars['title'] ?? '')),
        '{author}' => trim((string) ($vars['author'] ?? '')),
        '{folder}' => trim((string) ($vars['folder'] ?? '')),
        '{price}'  => trim((string) ($vars['price'] ?? '')),
        '{site}'   => trim((string) ($vars['site'] ?? site_setting('site_name', 'Bookexa'))),
    ];
    $out = strtr($tpl, $rep);
    // Clean missing optional variable wrappers like " ()", "by  |", "—  |"
    $out = preg_replace('/\s*\(\s*\)/u', '', $out) ?? $out;
    $out = preg_replace('/\bby\s*(?=[|—\-]|$)/iu', '', $out) ?? $out;
    $out = preg_replace('/([—\-|])\s*\1+/u', '$1', $out) ?? $out;
    $out = preg_replace('/\s+[—\-|]\s*$/u', '', $out) ?? $out;
    $out = preg_replace('/^\s*[—\-|]\s+/u', '', $out) ?? $out;
    $out = preg_replace('/\s+/u', ' ', $out) ?? $out;
    return trim($out);
}

/**
 * Render unique, length-bounded Title (30-60) & Meta Description (70-155) for any page type.
 */
function seo_render_meta(string $pageType, array $vars = [], array $overrides = [], int $page = 1): array
{
    $lang = current_lang();
    $siteName = site_setting('site_name', 'Bookexa');
    $vars['site'] = $vars['site'] ?? $siteName;

    $defs = seo_default_templates();
    $def = $defs[$pageType] ?? $defs['static'];

    $tplTitle = site_setting("seo_tpl_{$pageType}_title_{$lang}", $def['title_' . $lang]);
    $tplDesc  = site_setting("seo_tpl_{$pageType}_desc_{$lang}", $def['desc_' . $lang]);

    $overrideTitle = trim((string) ($overrides['title'] ?? ''));
    $overrideDesc  = trim((string) ($overrides['description'] ?? ''));

    $pageSuffix = $page > 1
        ? ($lang === 'bn' ? (' — পৃষ্ঠা ' . num_localize($page)) : (' — Page ' . $page))
        : '';

    if ($overrideTitle !== '') {
        $rawTitle = $overrideTitle . $pageSuffix;
        if (!str_contains(mb_strtolower($rawTitle, 'UTF-8'), mb_strtolower($siteName, 'UTF-8'))) {
            $rawTitle .= ' — ' . $siteName;
        }
        $title = seo_clamp_text($rawTitle, 0, SEO_TITLE_MAX);
    } else {
        $rawTitle = seo_apply_template($tplTitle, $vars);
        if ($pageSuffix !== '') {
            if (str_contains($rawTitle, ' | ' . $siteName)) {
                $rawTitle = str_replace(' | ' . $siteName, $pageSuffix . ' | ' . $siteName, $rawTitle);
            } else {
                $rawTitle .= $pageSuffix;
            }
        }
        $padTitle = $lang === 'bn'
            ? ($siteName . ' ডিজিটাল লাইব্রেরি')
            : ($siteName . ' Online Platform');
        $title = seo_clamp_text($rawTitle, SEO_TITLE_MIN, SEO_TITLE_MAX, $padTitle);
    }

    if ($overrideDesc !== '') {
        $rawDesc = $overrideDesc . ($page > 1 ? ($lang === 'bn' ? (' (পৃষ্ঠা ' . num_localize($page) . ')') : (' (Page ' . $page . ')')) : '');
        $descTail = $lang === 'bn'
            ? ($siteName . ' প্ল্যাটফর্মে নিরাপদে পড়ুন ও বিস্তারিত দেখুন।')
            : ('Explore full details, preview samples, and read securely on ' . $siteName . '.');
        $desc = seo_clamp_text($rawDesc, SEO_DESC_MIN, SEO_DESC_MAX, $descTail);
    } else {
        $rawDesc = seo_apply_template($tplDesc, $vars);
        if ($page > 1) {
            $rawDesc = rtrim($rawDesc, '.।') . ($lang === 'bn' ? (' — পৃষ্ঠা ' . num_localize($page) . '।') : (' — Page ' . $page . '.'));
        }
        $descTail = $lang === 'bn'
            ? ('বাংলাদেশের শিক্ষার্থীদের জন্য বিশ্বস্ত ডিজিটাল লাইব্রেরি ' . $siteName . '।')
            : ('Trusted digital reading and study platform across Bangladesh on ' . $siteName . '.');
        $desc = seo_clamp_text($rawDesc, SEO_DESC_MIN, SEO_DESC_MAX, $descTail);
    }

    return ['title' => $title, 'description' => $desc];
}

/**
 * Determine whether current font supports Bangla Unicode glyphs.
 */
function seo_font_supports_bangla(?string $fontPath): bool
{
    if ($fontPath === null) {
        return false;
    }
    $base = strtolower(basename($fontPath));
    foreach (['nirmala', 'bengali', 'solaiman', 'kalpurush', 'nikosh', 'noto', 'siyam'] as $hint) {
        if (str_contains($base, $hint)) {
            return true;
        }
    }
    return false;
}

/**
 * Return cached or generated 1200x630 social card URL for an entity.
 */
function seo_og_image_url(string $type, string $slug, ?string $fallbackRel = null): string
{
    if ($slug !== '' && function_exists('imagecreatetruecolor')) {
        return BASE_URL . '/og-image/' . rawurlencode($type) . '/' . rawurlencode($slug) . '.png';
    }
    return absolute_asset_url($fallbackRel ?: site_setting('seo_default_og_image', 'assets/img/apple-touch-icon.png'));
}

/**
 * Serve dynamic 1200x630 GD social card image (/og-image/{type}/{slug}.png), cached on disk.
 */
function seo_serve_og_image(string $type, string $slug): never
{
    if (!function_exists('imagecreatetruecolor')) {
        $fallback = APP_ROOT . '/' . ltrim(site_setting('seo_default_og_image', 'assets/img/apple-touch-icon.png'), '/');
        if (is_file($fallback)) {
            header('Content-Type: image/png');
            readfile($fallback);
            exit;
        }
        render_error_page(404);
    }

    $titleBn = '';
    $titleEn = '';
    $subEn   = '';
    $badge   = strtoupper($type);
    $coverRel = '';
    $stamp = date('Y-m-d');

    if ($type === 'book') {
        $row = db_one('SELECT title_bn, title_en, author_bn, author_en, cover, price, is_free, updated_at FROM items WHERE slug = ? AND is_active = 1 LIMIT 1', [$slug]);
        if ($row) {
            $titleBn = (string) $row['title_bn'];
            $titleEn = (string) $row['title_en'];
            $author  = (string) ($row['author_en'] ?: $row['author_bn'] ?: '');
            $price   = ((int) $row['is_free'] || (float) $row['price'] <= 0) ? 'FREE PDF' : ('BDT ' . number_format((float) $row['price'], 0));
            $subEn   = trim($author . ($author !== '' ? '  •  ' : '') . $price);
            $badge   = 'DIGITAL BOOK';
            $coverRel = (string) ($row['cover'] ?? '');
            $stamp   = (string) ($row['updated_at'] ?? $stamp);
        }
    } elseif ($type === 'product') {
        $row = db_one('SELECT title_bn, title_en, author_bn, author_en, cover_image, price, discount_price, updated_at FROM products WHERE slug = ? AND is_active = 1 LIMIT 1', [$slug]);
        if ($row) {
            $titleBn = (string) $row['title_bn'];
            $titleEn = (string) $row['title_en'];
            $eff     = (!empty($row['discount_price']) && (float) $row['discount_price'] > 0 && (float) $row['discount_price'] < (float) $row['price'])
                ? (float) $row['discount_price'] : (float) $row['price'];
            $author  = (string) ($row['author_en'] ?: $row['author_bn'] ?: '');
            $subEn   = trim($author . ($author !== '' ? '  •  ' : '') . 'BDT ' . number_format($eff, 0));
            $badge   = 'PRINTED BOOK';
            $coverRel = (string) ($row['cover_image'] ?? '');
            $stamp   = (string) ($row['updated_at'] ?? $stamp);
        }
    } elseif ($type === 'folder') {
        $row = db_one('SELECT name_bn, name_en, thumbnail, item_count, updated_at FROM folders WHERE slug = ? AND is_active = 1 LIMIT 1', [$slug]);
        if ($row) {
            $titleBn = (string) $row['name_bn'];
            $titleEn = (string) $row['name_en'];
            $subEn   = (int) ($row['item_count'] ?? 0) . ' PDF Resources';
            $badge   = 'STUDY COLLECTION';
            $coverRel = (string) ($row['thumbnail'] ?? '');
            $stamp   = (string) ($row['updated_at'] ?? $stamp);
        }
    } elseif ($type === 'guide') {
        $row = db_one('SELECT title_bn, title_en, author_name, cover, updated_at FROM guides WHERE slug = ? AND is_active = 1 LIMIT 1', [$slug]);
        if ($row) {
            $titleBn = (string) $row['title_bn'];
            $titleEn = (string) $row['title_en'];
            $subEn   = (string) ($row['author_name'] ?: 'Editorial Study Guide');
            $badge   = 'STUDY GUIDE';
            $coverRel = (string) ($row['cover'] ?? '');
            $stamp   = (string) ($row['updated_at'] ?? $stamp);
        }
    } elseif ($type === 'author') {
        $row = db_one('SELECT name_bn, name_en, photo, updated_at FROM authors WHERE slug = ? AND is_active = 1 LIMIT 1', [$slug]);
        if ($row) {
            $titleBn = (string) $row['name_bn'];
            $titleEn = (string) $row['name_en'];
            $subEn   = 'Author Profile & Publications';
            $badge   = 'AUTHOR';
            $coverRel = (string) ($row['photo'] ?? '');
            $stamp   = (string) ($row['updated_at'] ?? $stamp);
        }
    }

    if ($titleEn === '' && $titleBn === '') {
        $titleEn = site_setting('site_name', 'Bookexa');
        $subEn   = 'Digital Books & Academic PDF Library';
        $badge   = 'BOOKEXA';
        $coverRel = site_setting('seo_default_og_image', 'assets/img/apple-touch-icon.png');
    }

    $siteName = site_setting('site_name', 'Bookexa');
    $primaryHex = sanitize_hex_color(site_setting('theme_primary_color', '#2B2F77'), '#2B2F77');
    $accentHex  = sanitize_hex_color(site_setting('theme_accent_color', '#F4A62A'), '#F4A62A');

    $cacheDir = ensure_bookexa_cache_dir('og');
    $hash = md5($type . '|' . $slug . '|' . $stamp . '|' . $siteName . '|' . $primaryHex . '|' . $accentHex . '|' . $coverRel);
    $cachePath = $cacheDir . '/' . preg_replace('/[^a-z0-9_-]/i', '_', $type . '_' . $slug) . '_' . $hash . '.png';

    if (is_file($cachePath) && (time() - (@filemtime($cachePath) ?: 0)) < 604800) {
        $etag = '"' . $hash . '"';
        header('Content-Type: image/png');
        header('Cache-Control: public, max-age=604800');
        header('ETag: ' . $etag);
        if (trim((string) ($_SERVER['HTTP_IF_NONE_MATCH'] ?? '')) === $etag) {
            http_response_code(304);
            exit;
        }
        readfile($cachePath);
        exit;
    }

    $w = 1200;
    $h = 630;
    $im = imagecreatetruecolor($w, $h);
    imagealphablending($im, true);

    [$pr, $pg, $pb] = sscanf($primaryHex, '#%02x%02x%02x') ?: [43, 47, 119];
    [$ar, $ag, $ab] = sscanf($accentHex, '#%02x%02x%02x') ?: [244, 166, 42];

    // Deep atmospheric indigo gradient background
    for ($y = 0; $y < $h; $y++) {
        $ratio = $y / $h;
        $r = max(12, (int) round($pr * (0.78 - 0.25 * $ratio)));
        $g = max(14, (int) round($pg * (0.78 - 0.22 * $ratio)));
        $b = max(28, (int) round($pb * (0.85 - 0.18 * $ratio)));
        $col = imagecolorallocate($im, $r, $g, $b);
        imageline($im, 0, $y, $w, $y, $col);
    }

    $cream  = imagecolorallocate($im, 251, 246, 236);
    $muted  = imagecolorallocate($im, 206, 214, 235);
    $accent = imagecolorallocate($im, $ar, $ag, $ab);
    $cardBg = imagecolorallocatealpha($im, 255, 255, 255, 114);
    $spine  = imagecolorallocatealpha($im, $ar, $ag, $ab, 35);

    // Subtle elevated glass container
    imagefilledrectangle($im, 56, 52, $w - 56, $h - 52, $cardBg);
    imagefilledrectangle($im, 56, 52, 70, $h - 52, $accent);

    // Left cover frame (310 x 435)
    $covX = 110;
    $covY = 98;
    $covW = 310;
    $covH = 435;
    $covLoaded = false;
    if ($coverRel !== '') {
        $absCov = APP_ROOT . '/' . ltrim($coverRel, '/');
        if (is_file($absCov)) {
            $ext = strtolower(pathinfo($absCov, PATHINFO_EXTENSION));
            $src = match ($ext) {
                'png'  => @imagecreatefrompng($absCov),
                'jpg', 'jpeg' => @imagecreatefromjpeg($absCov),
                'webp' => function_exists('imagecreatefromwebp') ? @imagecreatefromwebp($absCov) : null,
                default => null,
            };
            if ($src) {
                $sw = imagesx($src);
                $sh = imagesy($src);
                if ($sw > 0 && $sh > 0) {
                    $scale = min($covW / $sw, $covH / $sh);
                    $dw = (int) round($sw * $scale);
                    $dh = (int) round($sh * $scale);
                    $dx = $covX + (int) floor(($covW - $dw) / 2);
                    $dy = $covY + (int) floor(($covH - $dh) / 2);
                    imagefilledrectangle($im, $covX - 6, $covY - 6, $covX + $covW + 6, $covY + $covH + 6, $spine);
                    imagecopyresampled($im, $src, $dx, $dy, 0, 0, $dw, $dh, $sw, $sh);
                    $covLoaded = true;
                }
                imagedestroy($src);
            }
        }
    }
    if (!$covLoaded) {
        $bookGrad = imagecolorallocate($im, min(255, $pr + 25), min(255, $pg + 28), min(255, $pb + 35));
        imagefilledrectangle($im, $covX, $covY, $covX + $covW, $covY + $covH, $bookGrad);
        imagefilledrectangle($im, $covX, $covY, $covX + 20, $covY + $covH, $accent);
    }

    // Text column (Right side: X=468..1110)
    $textX = 468;
    $maxTextW = 635;
    require_once APP_ROOT . '/includes/services/Watermarker.php';
    $fontPath = Watermarker::findTtfFont();
    $displayTitle = seo_font_supports_bangla($fontPath)
        ? ($titleBn !== '' ? $titleBn : $titleEn)
        : ($titleEn !== '' ? $titleEn : seo_transliterate_bn_to_ascii($titleBn));

    if ($fontPath !== null && function_exists('imagettftext')) {
        // Category pill
        imagettftext($im, 15, 0, $textX, 136, $accent, $fontPath, $badge);

        // Wrapped Title (2-4 lines)
        $words = preg_split('/\s+/u', trim($displayTitle)) ?: [];
        $lines = [];
        $curr = '';
        foreach ($words as $wd) {
            $try = $curr === '' ? $wd : ($curr . ' ' . $wd);
            $box = @imagettfbbox(32, 0, $fontPath, $try);
            $bw = $box ? abs($box[2] - $box[0]) : strlen($try) * 18;
            if ($bw > $maxTextW && $curr !== '') {
                $lines[] = $curr;
                $curr = $wd;
                if (count($lines) >= 3) {
                    break;
                }
            } else {
                $curr = $try;
            }
        }
        if ($curr !== '' && count($lines) < 4) {
            $lines[] = $curr;
        }
        $ty = 205;
        foreach ($lines as $ln) {
            imagettftext($im, 32, 0, $textX, $ty, $cream, $fontPath, $ln);
            $ty += 52;
        }

        if ($subEn !== '') {
            imagettftext($im, 20, 0, $textX, max(350, $ty + 22), $muted, $fontPath, mb_substr($subEn, 0, 56));
        }

        // Bottom Brand Footer
        imagettftext($im, 22, 0, $textX, 515, $accent, $fontPath, $siteName);
        imagettftext($im, 14, 0, $textX, 548, $muted, $fontPath, preg_replace('#^https?://#', '', BASE_URL) ?? BASE_URL);
    } else {
        imagestring($im, 5, $textX, 130, $badge, $accent);
        imagestring($im, 5, $textX, 185, substr(seo_transliterate_bn_to_ascii($displayTitle), 0, 48), $cream);
        if ($subEn !== '') {
            imagestring($im, 4, $textX, 240, substr($subEn, 0, 56), $muted);
        }
        imagestring($im, 5, $textX, 500, $siteName, $accent);
    }

    $tmp = $cachePath . '.tmp_' . bin2hex(random_bytes(4));
    imagepng($im, $tmp, 6);
    imagedestroy($im);
    if (is_file($tmp)) {
        @rename($tmp, $cachePath);
    }

    $etag = '"' . $hash . '"';
    header('Content-Type: image/png');
    header('Cache-Control: public, max-age=604800');
    header('ETag: ' . $etag);
    if (is_file($cachePath)) {
        readfile($cachePath);
    }
    exit;
}

/**
 * Sync distinct author names from active items & products into the `authors` directory table.
 */
function seo_sync_authors_directory(): void
{
    static $synced = false;
    if ($synced) {
        return;
    }
    $synced = true;
    try {
        $seen = [];
        $existingRows = db_all('SELECT id, slug, name_bn, name_en FROM authors');
        $byNameKey = [];
        $bySlug = [];
        foreach ($existingRows as $er) {
            $bySlug[(string) $er['slug']] = true;
            foreach ([(string) $er['name_bn'], (string) $er['name_en']] as $nm) {
                $k = mb_strtolower(trim($nm), 'UTF-8');
                if ($k !== '') {
                    $byNameKey[$k] = true;
                }
            }
        }

        $sources = array_merge(
            db_all('SELECT DISTINCT author_bn, author_en FROM items WHERE is_active = 1 AND (COALESCE(author_bn, "") != "" OR COALESCE(author_en, "") != "")'),
            db_all('SELECT DISTINCT author_bn, author_en FROM products WHERE is_active = 1 AND (COALESCE(author_bn, "") != "" OR COALESCE(author_en, "") != "")')
        );
        foreach ($sources as $s) {
            $bn = trim((string) ($s['author_bn'] ?? ''));
            $en = trim((string) ($s['author_en'] ?? ''));
            if ($bn === '' && $en === '') {
                continue;
            }
            $kBn = $bn !== '' ? mb_strtolower($bn, 'UTF-8') : '';
            $kEn = $en !== '' ? mb_strtolower($en, 'UTF-8') : '';
            if (($kBn !== '' && isset($byNameKey[$kBn])) || ($kEn !== '' && isset($byNameKey[$kEn]))) {
                continue;
            }
            $baseSlug = slugify($en !== '' ? $en : $bn);
            $slug = $baseSlug;
            $n = 2;
            while (isset($bySlug[$slug])) {
                $slug = $baseSlug . '-' . $n++;
            }
            $bySlug[$slug] = true;
            if ($kBn !== '') $byNameKey[$kBn] = true;
            if ($kEn !== '') $byNameKey[$kEn] = true;
            db_exec(
                'INSERT IGNORE INTO authors (slug, name_bn, name_en, is_active) VALUES (?, ?, ?, 1)',
                [$slug, $bn !== '' ? $bn : $en, $en !== '' ? $en : $bn]
            );
        }
    } catch (Throwable $e) {
    }
}

/**
 * Organization JSON-LD schema with logo, social sameAs, and contactPoint.
 */
function seo_org_schema(): array
{
    $siteName = site_setting('site_name', 'Bookexa');
    $logoAbs  = absolute_asset_url(site_setting('site_logo', 'assets/img/logo.svg'));
    $sameAs = [];
    foreach (['social_facebook', 'social_youtube', 'social_telegram', 'social_instagram'] as $sk) {
        $u = sanitize_social_url(site_setting($sk, ''));
        if ($u !== '') {
            $sameAs[] = $u;
        }
    }
    $extraSame = trim(site_setting('seo_sameas_extra', ''));
    if ($extraSame !== '') {
        foreach (preg_split('/[\s,]+/', $extraSame) ?: [] as $eu) {
            $cleanEu = sanitize_social_url($eu);
            if ($cleanEu !== '' && !in_array($cleanEu, $sameAs, true)) {
                $sameAs[] = $cleanEu;
            }
        }
    }
    $founded = preg_replace('/[^0-9]/', '', site_setting('site_founding_year', '2024'));
    $org = [
        '@context'      => 'https://schema.org',
        '@type'         => 'Organization',
        'name'          => $siteName,
        'description'   => t('about_short_desc'),
        'url'           => BASE_URL . '/',
        'logo'          => $logoAbs,
        'areaServed'    => strtoupper(site_setting('seo_default_region', 'BD')),
        'knowsLanguage' => ['bn', 'en'],
    ];
    if ($founded !== '') {
        $org['foundingDate'] = $founded;
    }
    if ($sameAs) {
        $org['sameAs'] = $sameAs;
    }
    $phone = trim(site_setting('contact_phone', ''));
    $email = trim(site_setting('contact_email', ''));
    if ($phone !== '' || $email !== '') {
        $cp = [
            '@type'       => 'ContactPoint',
            'contactType' => 'customer support',
            'areaServed'  => strtoupper(site_setting('seo_default_region', 'BD')),
        ];
        if ($phone !== '') $cp['telephone'] = $phone;
        if ($email !== '') $cp['email'] = $email;
        $org['contactPoint'] = $cp;
    }
    return $org;
}

/**
 * BreadcrumbList JSON-LD builder from [['name' => ..., 'url' => ...], ...].
 */
function seo_breadcrumb_schema(array $crumbs): array
{
    $list = [];
    $pos = 1;
    foreach ($crumbs as $c) {
        $list[] = [
            '@type'    => 'ListItem',
            'position' => $pos++,
            'name'     => (string) $c['name'],
            'item'     => (string) $c['url'],
        ];
    }
    return [
        '@context'        => 'https://schema.org',
        '@type'           => 'BreadcrumbList',
        'itemListElement' => $list,
    ];
}

/**
 * CollectionPage + ItemList schema for folder, shop archive, guide index & author pages.
 */
function seo_collection_schema(string $name, string $url, string $description, array $listUrlsAndNames): array
{
    $elems = [];
    $pos = 1;
    foreach ($listUrlsAndNames as $entry) {
        $elems[] = [
            '@type'    => 'ListItem',
            'position' => $pos++,
            'name'     => (string) $entry['name'],
            'url'      => (string) $entry['url'],
        ];
    }
    return [
        '@context'    => 'https://schema.org',
        '@type'       => 'CollectionPage',
        'name'        => $name,
        'url'         => $url,
        'description' => $description,
        'mainEntity'  => [
            '@type'           => 'ItemList',
            'numberOfItems'   => count($elems),
            'itemListElement' => $elems,
        ],
    ];
}

/**
 * Resolve true content lastmod YYYY-MM-DD strictly from DB timestamps (fallback only to unix epoch start or provided default).
 */
function seo_format_lastmod(?string $updatedAt, ?string $createdAt = null, string $fallback = '2026-01-01'): string
{
    $raw = $updatedAt ?: $createdAt;
    if ($raw) {
        $ts = strtotime($raw);
        if ($ts !== false && $ts > 0) {
            return date('Y-m-d', $ts);
        }
    }
    return $fallback;
}

/**
 * Gather full bilingual entries for a sitemap section, supporting optional chunk slicing.
 */
function seo_sitemap_section_entries(string $section): array
{
    $entries = [];
    if ($section === 'static') {
        $stTs = max(
            (int) @filemtime(APP_ROOT . '/pages/home.php'),
            (int) @filemtime(APP_ROOT . '/pages/about.php'),
            (int) @filemtime(APP_ROOT . '/pages/contact.php'),
            (int) @filemtime(APP_ROOT . '/pages/legal.php')
        );
        $stMod = $stTs > 0 ? date('Y-m-d', $stTs) : '2026-01-01';
        $paths = ['/', '/pdf', '/guides', '/authors', '/faq', '/about', '/contact', '/editorial-policy', '/how-we-review', '/corrections-policy', '/terms', '/privacy', '/refund-policy', '/copyright'];
        if (site_setting('shop_enabled', '0') === '1') {
            $paths[] = '/shop';
        }
        $defOg = absolute_asset_url(site_setting('seo_default_og_image', 'assets/img/apple-touch-icon.png'));
        foreach ($paths as $p) {
            $entries[] = ['path' => $p, 'lastmod' => $stMod, 'image' => $defOg, 'title' => site_setting('site_name', 'Bookexa')];
        }
        return $entries;
    }

    if ($section === 'folders') {
        $tree = get_cached_folder_tree();
        foreach ($tree['byId'] ?? [] as $f) {
            $trail = folder_breadcrumbs((int) $f['id']);
            if (!$trail) continue;
            $path = '/pdf/' . implode('/', array_map(static fn($c): string => rawurlencode((string) $c['slug']), $trail));
            $entries[] = [
                'path'    => $path,
                'lastmod' => seo_format_lastmod($f['updated_at'] ?? null, $f['created_at'] ?? null),
                'image'   => !empty($f['thumbnail']) ? absolute_asset_url((string) $f['thumbnail']) : '',
                'title'   => (string) ($f['name_en'] ?: $f['name_bn']),
            ];
        }
        return $entries;
    }

    if ($section === 'books') {
        $rows = db_all('SELECT slug, title_bn, title_en, cover, updated_at, created_at FROM items WHERE is_active = 1 AND ' . published_window_sql() . ' ORDER BY id DESC');
        foreach ($rows as $r) {
            $entries[] = [
                'path'    => '/book/' . rawurlencode((string) $r['slug']),
                'lastmod' => seo_format_lastmod($r['updated_at'] ?? null, $r['created_at'] ?? null),
                'image'   => !empty($r['cover']) ? absolute_asset_url((string) $r['cover']) : '',
                'title'   => (string) ($r['title_en'] ?: $r['title_bn']),
            ];
        }
        return $entries;
    }

    if ($section === 'products') {
        if (site_setting('shop_enabled', '0') !== '1') {
            return [];
        }
        $rows = db_all('SELECT slug, title_bn, title_en, cover_image, updated_at, created_at FROM products WHERE is_active = 1 ORDER BY id DESC');
        foreach ($rows as $r) {
            $entries[] = [
                'path'    => '/shop/' . rawurlencode((string) $r['slug']),
                'lastmod' => seo_format_lastmod($r['updated_at'] ?? null, $r['created_at'] ?? null),
                'image'   => !empty($r['cover_image']) ? absolute_asset_url((string) $r['cover_image']) : '',
                'title'   => (string) ($r['title_en'] ?: $r['title_bn']),
            ];
        }
        return $entries;
    }

    if ($section === 'guides') {
        $rows = db_all('SELECT slug, title_bn, title_en, cover, updated_at, created_at FROM guides WHERE ' . content_guides_published_sql() . ' ORDER BY id DESC');
        foreach ($rows as $r) {
            $entries[] = [
                'path'    => '/guides/' . rawurlencode((string) $r['slug']),
                'lastmod' => seo_format_lastmod($r['updated_at'] ?? null, $r['created_at'] ?? null),
                'image'   => !empty($r['cover']) ? absolute_asset_url((string) $r['cover']) : '',
                'title'   => (string) ($r['title_en'] ?: $r['title_bn']),
            ];
        }
        return $entries;
    }

    if ($section === 'authors') {
        seo_sync_authors_directory();
        $rows = db_all('SELECT slug, name_bn, name_en, photo, updated_at, created_at FROM authors WHERE is_active = 1 ORDER BY id DESC');
        foreach ($rows as $r) {
            $entries[] = [
                'path'    => '/authors/' . rawurlencode((string) $r['slug']),
                'lastmod' => seo_format_lastmod($r['updated_at'] ?? null, $r['created_at'] ?? null),
                'image'   => !empty($r['photo']) ? absolute_asset_url((string) $r['photo']) : '',
                'title'   => (string) ($r['name_en'] ?: $r['name_bn']),
            ];
        }
        return $entries;
    }

    if ($section === 'hubs') {
        $rows = db_all('SELECT slug, title_bn, title_en, updated_at, created_at FROM hubs WHERE is_active = 1 ORDER BY sort_order ASC, id DESC');
        $defOg = absolute_asset_url(site_setting('seo_default_og_image', 'assets/img/apple-touch-icon.png'));
        foreach ($rows as $r) {
            $entries[] = [
                'path'    => '/hub/' . rawurlencode((string) $r['slug']),
                'lastmod' => seo_format_lastmod($r['updated_at'] ?? null, $r['created_at'] ?? null),
                'image'   => $defOg,
                'title'   => (string) ($r['title_en'] ?: $r['title_bn']),
            ];
        }
        return $entries;
    }

    return [];
}

/**
 * Inspect live HTML of an internal route and validate JSON-LD structured data blocks + SEO head signals.
 */
function seo_structured_data_validate(string $path): array
{
    $cleanPath = safe_internal_path($path, '/');
    $targetUrl = BASE_URL . $cleanPath;

    $ch = curl_init($targetUrl);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => false,
        CURLOPT_TIMEOUT        => 12,
        CURLOPT_USERAGENT      => 'Bookexa-SEO-Inspector/1.0',
    ]);
    $html = (string) curl_exec($ch);
    $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err = curl_error($ch);
    curl_close($ch);

    if ($err !== '' || $html === '') {
        return ['ok' => false, 'http_code' => $httpCode, 'url' => $targetUrl, 'error' => $err ?: 'Empty HTTP response', 'schemas' => []];
    }

    $title = '';
    if (preg_match('/<title[^>]*>(.*?)<\/title>/is', $html, $tm)) {
        $title = html_entity_decode(trim($tm[1]), ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }
    $desc = '';
    if (preg_match('/<meta\s+name="description"\s+content="([^"]*)"/i', $html, $dm)) {
        $desc = html_entity_decode($dm[1], ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }
    $canon = '';
    if (preg_match('/<link\s+rel="canonical"\s+href="([^"]*)"/i', $html, $cm)) {
        $canon = $cm[1];
    }

    preg_match_all('/<script[^>]*type="application\/ld\+json"[^>]*>(.*?)<\/script>/is', $html, $m);
    $schemas = [];
    foreach ($m[1] ?? [] as $idx => $rawJson) {
        $rawTrim = trim($rawJson);
        $decoded = json_decode($rawTrim, true);
        if (!is_array($decoded)) {
            $schemas[] = ['index' => $idx + 1, 'type' => 'INVALID_JSON', 'valid' => false, 'issues' => ['Malformed JSON-LD syntax'], 'data' => []];
            continue;
        }
        $type = (string) ($decoded['@type'] ?? 'Unknown');
        $issues = [];
        if (($decoded['@context'] ?? '') !== 'https://schema.org') {
            $issues[] = 'Missing @context=https://schema.org';
        }
        if ($type === 'Organization' && (empty($decoded['name']) || empty($decoded['url']))) {
            $issues[] = 'Organization requires name and url';
        } elseif ($type === 'WebSite' && empty($decoded['potentialAction'])) {
            $issues[] = 'WebSite missing SearchAction';
        } elseif ($type === 'BreadcrumbList' && empty($decoded['itemListElement'])) {
            $issues[] = 'BreadcrumbList missing itemListElement';
        } elseif (($type === 'Book' || $type === 'Product') && (empty($decoded['name']) || empty($decoded['offers']['priceCurrency']))) {
            $issues[] = "{$type} requires name and offers with priceCurrency";
        } elseif ($type === 'CollectionPage' && empty($decoded['mainEntity']['itemListElement'])) {
            $issues[] = 'CollectionPage has empty ItemList';
        }
        $schemas[] = [
            'index'  => $idx + 1,
            'type'   => $type,
            'valid'  => empty($issues),
            'issues' => $issues,
            'data'   => $decoded,
        ];
    }

    return [
        'ok'         => $httpCode === 200,
        'http_code'  => $httpCode,
        'url'        => $targetUrl,
        'title'      => $title,
        'title_len'  => mb_strlen($title, 'UTF-8'),
        'desc'       => $desc,
        'desc_len'   => mb_strlen($desc, 'UTF-8'),
        'canonical'  => $canon,
        'schemas'    => $schemas,
    ];
}
