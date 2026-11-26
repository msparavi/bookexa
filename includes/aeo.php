<?php
declare(strict_types=1);

/**
 * Bookexa AEO & GEO Optimization Layer
 * - Answer-first blocks (40-60 words) + SpeakableSpecification support
 * - AI Crawler Policy Manager (14 major bots) & dynamic robots.txt builder
 * - Bilingual /llms.txt & /llms-full.txt Markdown generators (cached, zero protected book text)
 * - Public machine-readable JSON feeds (/catalog.json & /guides.json)
 * - Grouped /faq topic helpers
 * - Admin AEO/GEO Audit Scorer (0-100, Top 20 pages to improve)
 */

/**
 * Metadata for major Search, Citation & AI Training Crawlers.
 */
function aeo_crawler_catalog(): array
{
    return [
        ['name' => 'Googlebot',          'vendor' => 'Google',      'role' => 'search_citation', 'default' => 'allow', 'note' => 'Core Google Web Search & Google AI Overviews indexing'],
        ['name' => 'Bingbot',            'vendor' => 'Microsoft',   'role' => 'search_citation', 'default' => 'allow', 'note' => 'Bing Search & Microsoft Copilot Search indexing'],
        ['name' => 'OAI-SearchBot',      'vendor' => 'OpenAI',      'role' => 'search_citation', 'default' => 'allow', 'note' => 'ChatGPT Search citation & live web retrieval (not model training)'],
        ['name' => 'ChatGPT-User',       'vendor' => 'OpenAI',      'role' => 'search_citation', 'default' => 'allow', 'note' => 'On-demand user link browsing from ChatGPT with citations'],
        ['name' => 'Claude-SearchBot',   'vendor' => 'Anthropic',   'role' => 'search_citation', 'default' => 'allow', 'note' => 'Anthropic Claude web search & answer citation retrieval'],
        ['name' => 'PerplexityBot',      'vendor' => 'Perplexity',  'role' => 'search_citation', 'default' => 'allow', 'note' => 'Perplexity Answer Engine search index & direct citations'],
        ['name' => 'Google-Extended',    'vendor' => 'Google',      'role' => 'training',        'default' => 'block', 'note' => 'Gemini / Vertex AI foundation model training opt-out control'],
        ['name' => 'GPTBot',             'vendor' => 'OpenAI',      'role' => 'training',        'default' => 'block', 'note' => 'OpenAI pre-training & post-training web corpus crawler'],
        ['name' => 'ClaudeBot',          'vendor' => 'Anthropic',   'role' => 'training',        'default' => 'block', 'note' => 'Anthropic AI model training dataset crawler'],
        ['name' => 'Applebot-Extended',  'vendor' => 'Apple',       'role' => 'training',        'default' => 'block', 'note' => 'Apple Intelligence generative foundation model training control'],
        ['name' => 'CCBot',              'vendor' => 'CommonCrawl', 'role' => 'training',        'default' => 'block', 'note' => 'Public web archive corpus broadly used for LLM pre-training'],
        ['name' => 'Bytespider',         'vendor' => 'ByteDance',   'role' => 'training',        'default' => 'block', 'note' => 'ByteDance / Doubao LLM data ingestion crawler'],
        ['name' => 'Amazonbot',          'vendor' => 'Amazon',      'role' => 'training',        'default' => 'block', 'note' => 'Amazon Alexa & AI assistant indexing/training crawler'],
        ['name' => 'Meta-ExternalAgent', 'vendor' => 'Meta',        'role' => 'training',        'default' => 'block', 'note' => 'Meta AI model training & external RAG fetcher'],
    ];
}

/**
 * Return associative map [botName => 'allow' | 'block'].
 */
function aeo_crawler_policies(): array
{
    $out = [];
    foreach (aeo_crawler_catalog() as $bot) {
        $out[$bot['name']] = $bot['default'];
    }
    $raw = site_setting('aeo_crawler_policy_json', '');
    if ($raw !== '') {
        $dec = json_decode($raw, true);
        if (is_array($dec)) {
            foreach ($dec as $k => $v) {
                if (isset($out[$k]) && in_array($v, ['allow', 'block'], true)) {
                    $out[$k] = $v;
                }
            }
        }
    }
    return $out;
}

/**
 * Private/paid endpoints ALWAYS blocked for every crawler.
 */
function aeo_private_disallow_paths(): array
{
    return [
        '/admin', '/admin/', '/api', '/api/',
        '/reader', '/reader/', '/checkout', '/checkout/', '/cart', '/orders', '/library',
        '/shop/checkout', '/shop/order-success', '/shop/orders', '/shop/track',
        '/wishlist', '/notifications', '/profile', '/devices',
        '/login', '/register', '/forgot-password', '/reset-password',
        '/search', '/install', '/debug', '/storage/', '/uploads/pages/', '/config/', '/includes/',
    ];
}

/**
 * Build complete dynamic robots.txt incorporating AI Crawler policies + global private blocks.
 */
function aeo_build_robots_txt(): string
{
    $privatePaths = aeo_private_disallow_paths();
    $policies     = aeo_crawler_policies();
    $lines        = [];

    $lines[] = '# Bookexa Dynamic Robots & AI Crawler Policy';
    foreach ($policies as $botName => $rule) {
        $lines[] = '';
        $lines[] = 'User-agent: ' . $botName;
        if ($rule === 'block') {
            $lines[] = 'Disallow: /';
            continue;
        }
        $lines[] = 'Allow: /';
        $lines[] = 'Allow: /assets/';
        $lines[] = 'Allow: /og-image/';
        $lines[] = 'Allow: /llms.txt';
        $lines[] = 'Allow: /llms-full.txt';
        $lines[] = 'Allow: /catalog.json';
        $lines[] = 'Allow: /guides.json';
        if (BASE_PATH !== '') {
            $lines[] = 'Allow: ' . BASE_PATH . '/assets/';
        }
        foreach ($privatePaths as $dp) {
            $lines[] = 'Disallow: ' . $dp;
            if (BASE_PATH !== '') {
                $lines[] = 'Disallow: ' . BASE_PATH . $dp;
            }
            if (!in_array($dp, ['/install', '/debug', '/storage/', '/uploads/pages/', '/config/', '/includes/'], true)) {
                $lines[] = 'Disallow: /en' . $dp;
                if (BASE_PATH !== '') {
                    $lines[] = 'Disallow: ' . BASE_PATH . '/en' . $dp;
                }
            }
        }
    }

    // Global wildcard fallback rule
    $lines[] = '';
    $lines[] = 'User-agent: *';
    $lines[] = 'Allow: /';
    $lines[] = 'Allow: /assets/';
    $lines[] = 'Allow: /og-image/';
    $lines[] = 'Allow: /llms.txt';
    $lines[] = 'Allow: /llms-full.txt';
    $lines[] = 'Allow: /catalog.json';
    $lines[] = 'Allow: /guides.json';
    if (BASE_PATH !== '') {
        $lines[] = 'Allow: ' . BASE_PATH . '/assets/';
        $lines[] = 'Allow: ' . BASE_PATH . '/og-image/';
    }
    foreach ($privatePaths as $dp) {
        $lines[] = 'Disallow: ' . $dp;
        if (BASE_PATH !== '') {
            $lines[] = 'Disallow: ' . BASE_PATH . $dp;
        }
        if (!in_array($dp, ['/install', '/debug', '/storage/', '/uploads/pages/', '/config/', '/includes/'], true)) {
            $lines[] = 'Disallow: /en' . $dp;
            if (BASE_PATH !== '') {
                $lines[] = 'Disallow: ' . BASE_PATH . '/en' . $dp;
            }
        }
    }
    foreach (['q=', 'sort=', 'price_type=', 'author=', 'has_sample=', 'in_stock='] as $qp) {
        $lines[] = 'Disallow: /*?*' . $qp;
        $lines[] = 'Disallow: /*&' . $qp;
    }
    $lines[] = '';
    $lines[] = 'Sitemap: ' . BASE_URL . '/sitemap.xml';

    return implode("\n", $lines) . "\n";
}

/**
 * Resolve visible Answer-First summary (40-60 words target) for any entity row.
 */
function aeo_short_answer(array $row, string $fallbackHtml = '', int $maxWords = 60): string
{
    $explicit = trim(bi($row, 'short_answer'));
    $src = $explicit !== '' ? $explicit : trim($fallbackHtml);
    $plain = content_strip_zero_width(strip_tags(html_entity_decode($src, ENT_QUOTES | ENT_HTML5, 'UTF-8')));
    $plain = trim(preg_replace('/\s+/u', ' ', $plain) ?? '');
    if ($plain === '') {
        return '';
    }
    $words = preg_split('/\s+/u', $plain, -1, PREG_SPLIT_NO_EMPTY) ?: [];
    if (count($words) <= $maxWords) {
        return $plain;
    }
    return implode(' ', array_slice($words, 0, $maxWords)) . '…';
}

/**
 * Parse guide sources JSON cleanly into [['title' => ..., 'url' => ...], ...].
 */
function aeo_parse_sources_json(?string $raw): array
{
    if ($raw === null || trim($raw) === '') {
        return [];
    }
    $dec = json_decode($raw, true);
    if (!is_array($dec)) {
        return [];
    }
    $out = [];
    foreach ($dec as $row) {
        if (!is_array($row)) continue;
        $tit = trim((string) ($row['title'] ?? ''));
        $url = trim((string) ($row['url'] ?? ''));
        if ($tit !== '' && ($url === '' || preg_match('#^https?://#i', $url) || str_starts_with($url, '/'))) {
            $out[] = ['title' => $tit, 'url' => $url];
        }
    }
    return $out;
}

/**
 * Topic labels for the grouped /faq page.
 */
function aeo_faq_topics(): array
{
    return [
        'payments'      => ['bn' => 'পেমেন্ট ও বিলিং', 'en' => 'Payments & Billing'],
        'bkash_nagad'   => ['bn' => 'বিকাশ ও নগদ পেমেন্ট', 'en' => 'bKash & Nagad Verification'],
        'reading'       => ['bn' => 'পড়া ও ফ্রি স্যাম্পল', 'en' => 'Reading & Free Samples'],
        'secure_reader' => ['bn' => 'সুরক্ষিত রিডার কীভাবে কাজ করে', 'en' => 'How the Secure Reader Works'],
        'devices'       => ['bn' => 'ডিভাইস ও লগইন সীমা', 'en' => 'Devices & Session Security'],
        'refunds'       => ['bn' => 'রিফান্ড ও সহায়তা নীতি', 'en' => 'Refunds & Support Policy'],
        'general'       => ['bn' => 'সাধারণ ও পরীক্ষার প্রস্তুতি', 'en' => 'General & Exam Preparation'],
    ];
}

/**
 * Build WebPage Schema with SpeakableSpecification pointing to visible summary box + H1.
 */
function aeo_webpage_schema(string $name, string $url, string $description, string $lang, ?string $dateModified = null): array
{
    $wp = [
        '@context'    => 'https://schema.org',
        '@type'       => 'WebPage',
        'name'        => $name,
        'url'         => $url,
        'description' => $description,
        'inLanguage'  => $lang,
        'speakable'   => [
            '@type'       => 'SpeakableSpecification',
            'cssSelector' => ['.bx-answer-box', 'h1'],
        ],
    ];
    if ($dateModified) {
        $wp['dateModified'] = $dateModified;
    }
    return $wp;
}

/**
 * Dynamically generate bilingual /llms.txt (concise) or /llms-full.txt (expanded summaries), cached 1 hr.
 * Strictly zero protected book text, zero user data, zero private URLs.
 */
function aeo_build_llms_txt(string $lang, bool $full = false): string
{
    $cacheDir = ensure_bookexa_cache_dir();
    $file = $cacheDir . '/' . ($full ? "llms_full_{$lang}.txt" : "llms_{$lang}.txt");
    if (is_file($file) && (time() - (@filemtime($file) ?: 0)) < 3600) {
        $cached = @file_get_contents($file);
        if (is_string($cached) && $cached !== '') {
            return $cached;
        }
    }

    $siteName = site_setting('site_name', 'Bookexa');
    $founded  = site_setting('site_founding_year', '2024');
    $reviewed = site_setting('legal_last_reviewed', date('Y-m-d'));
    $phone    = site_setting('contact_phone', '+880 1700-000001');
    $email    = site_setting('contact_email', 'support@bookexa.com');
    $addr     = site_setting('contact_address', 'Dhaka, Bangladesh');
    $shopOn   = site_setting('shop_enabled', '0') === '1';

    $out = [];
    $out[] = "# {$siteName}";
    $out[] = '';
    if ($lang === 'bn') {
        $out[] = "> {$siteName} বাংলাদেশের শিক্ষার্থী ও পাঠকদের জন্য নিরাপদ ডিজিটাল পিডিএফ বই, স্টাডি গাইড ও প্রিন্টেড বইয়ের প্ল্যাটফর্ম (প্রতিষ্ঠিত: {$founded}, পরিচালিত: {$addr})। ভাষা: বাংলা (ডিফল্ট) ও ইংরেজি (/en/)।";
    } else {
        $out[] = "> {$siteName} is Bangladesh's digital PDF library, exam preparation guide platform, and academic bookstore (Founded {$founded}, operating in {$addr}). Bilingual interface: Bangla at `/` and English at `/en/`. Formats: protected watermarked browser PDF reader and physical printed books.";
    }
    $out[] = '';
    $out[] = '## Key Sections';
    $out[] = '- [Home](' . absolute_url_for_lang('/', $lang) . ')';
    $out[] = '- [PDF Library & Folders](' . absolute_url_for_lang('/pdf', $lang) . '): Academic, BCS, SSC, HSC, University & Medical PDF collections';
    $out[] = '- [Study Guides](' . absolute_url_for_lang('/guides', $lang) . '): Structured preparation roadmaps with citations';
    if ($shopOn) {
        $out[] = '- [Printed Book Shop](' . absolute_url_for_lang('/shop', $lang) . '): Home delivery across Bangladesh';
    }
    $out[] = '- [Frequently Asked Questions](' . absolute_url_for_lang('/faq', $lang) . '): Payments, bKash/Nagad, devices, refunds, and how the secure reader works';
    $out[] = '- [Authors Directory](' . absolute_url_for_lang('/authors', $lang) . ')';
    $out[] = '- [About Entity & Operations](' . absolute_url_for_lang('/about', $lang) . ')';

    $hubs = db_all('SELECT slug, title_bn, title_en, short_answer_bn, short_answer_en FROM hubs WHERE is_active = 1 ORDER BY sort_order ASC, id ASC LIMIT 8');
    if ($hubs) {
        $out[] = '';
        $out[] = '## Exam & Topic Hubs';
        foreach ($hubs as $hb) {
            $t = trim((string) ($hb['title_' . $lang] ?: $hb['title_en']));
            $u = absolute_url_for_lang('/hub/' . rawurlencode((string) $hb['slug']), $lang);
            $sa = aeo_short_answer(['short_answer_bn' => $hb['short_answer_bn'] ?? '', 'short_answer_en' => $hb['short_answer_en'] ?? '']);
            $out[] = "- [{$t}]({$u})" . ($sa !== '' ? (': ' . $sa) : '');
        }
    }

    $guides = db_all(
        'SELECT slug, title_bn, title_en, summary_bn, summary_en, short_answer_bn, short_answer_en, body_bn, body_en, sources_json, updated_at
         FROM guides WHERE ' . content_guides_published_sql() . ' ORDER BY updated_at DESC LIMIT 20'
    );
    $out[] = '';
    $out[] = '## Study Guides';
    foreach ($guides as $g) {
        $t = trim((string) ($g['title_' . $lang] ?: $g['title_en']));
        $u = absolute_url_for_lang('/guides/' . rawurlencode((string) $g['slug']), $lang);
        $s = trim((string) ($g['short_answer_' . $lang] ?: $g['summary_' . $lang] ?: $g['summary_en']));
        $out[] = "- [{$t}]({$u})" . ($s !== '' ? (': ' . content_excerpt($s, $full ? 260 : 140)) : '');
        if ($full) {
            $bodyTxt = content_strip_zero_width(strip_tags((string) ($g['body_' . $lang] ?: $g['body_en'] ?: '')));
            $bodyTxt = trim(preg_replace('/\s+/u', ' ', $bodyTxt) ?? '');
            if ($bodyTxt !== '') {
                $out[] = '  Summary: ' . content_excerpt($bodyTxt, 600);
            }
            $srcs = aeo_parse_sources_json($g['sources_json'] ?? null);
            if ($srcs) {
                $srcLinks = array_map(static fn(array $r): string => $r['title'] . ($r['url'] !== '' ? " ({$r['url']})" : ''), $srcs);
                $out[] = '  Sources: ' . implode('; ', $srcLinks);
            }
            $out[] = '  Updated: ' . seo_format_lastmod($g['updated_at'] ?? null);
        }
    }

    $folders = db_all('SELECT id, slug, name_bn, name_en, description_bn, description_en, short_answer_bn, short_answer_en, intro_bn, intro_en FROM folders WHERE is_active = 1 AND ' . published_window_sql() . ' ORDER BY sort_order ASC, id ASC LIMIT 25');
    if ($folders) {
        $out[] = '';
        $out[] = '## Top Book Folders';
        foreach ($folders as $f) {
            $p = folder_path_string((int) $f['id']);
            if ($p === '') continue;
            $t = trim((string) ($f['name_' . $lang] ?: $f['name_en']));
            $u = absolute_url_for_lang('/pdf/' . $p, $lang);
            $d = trim((string) ($f['short_answer_' . $lang] ?: $f['description_' . $lang] ?: $f['intro_' . $lang] ?: $f['description_en']));
            $out[] = "- [{$t}]({$u})" . ($d !== '' ? (': ' . content_excerpt($d, $full ? 320 : 130)) : '');
        }
    }

    if ($full) {
        $faqs = db_all("SELECT topic, question_bn, answer_bn, question_en, answer_en, link_url FROM editorial_faqs WHERE scope_type = 'global' AND is_active = 1 ORDER BY topic ASC, sort_order ASC LIMIT 40");
        if ($faqs) {
            $out[] = '';
            $out[] = '## Public Frequently Asked Questions';
            foreach ($faqs as $fq) {
                $q = $lang === 'bn' ? (string) $fq['question_bn'] : (string) $fq['question_en'];
                $a = $lang === 'bn' ? (string) $fq['answer_bn'] : (string) $fq['answer_en'];
                $out[] = "### Q: {$q}";
                $out[] = $a . (!empty($fq['link_url']) ? (' -> ' . absolute_url_for_lang((string) $fq['link_url'], $lang)) : '');
            }
        }
    }

    $out[] = '';
    $out[] = '## Policies, Machine-Readable Feeds & Contact';
    $out[] = '- [Editorial Policy](' . absolute_url_for_lang('/editorial-policy', $lang) . ') (Last reviewed: ' . $reviewed . ')';
    $out[] = '- [How We Review Books](' . absolute_url_for_lang('/how-we-review', $lang) . ')';
    $out[] = '- [Corrections Policy](' . absolute_url_for_lang('/corrections-policy', $lang) . ')';
    $out[] = '- [Refund Policy](' . absolute_url_for_lang('/refund-policy', $lang) . '), [Privacy](' . absolute_url_for_lang('/privacy', $lang) . '), [Terms](' . absolute_url_for_lang('/terms', $lang) . ')';
    $out[] = '- [Public Catalog JSON](' . BASE_URL . '/catalog.json)';
    $out[] = '- [Guides JSON Feed](' . BASE_URL . '/guides.json)';
    $out[] = '- [Atom Feed](' . absolute_url_for_lang('/atom.xml', $lang) . ')';
    $out[] = "- Contact: {$email} | {$phone} | {$addr}";
    $out[] = '';
    $out[] = '# Note: Protected paid PDF pages and user data are excluded from machine feeds and crawler access.';

    $text = implode("\n", $out) . "\n";
    @file_put_contents($file, $text, LOCK_EX);
    return $text;
}

/**
 * Build paginated public catalog.json (metadata only — never protected text).
 */
function aeo_build_catalog_json(int $page = 1, int $perPage = 100): array
{
    $page = max(1, $page);
    $perPage = max(10, min(200, $perPage));
    $pub = published_window_sql('i');
    $total = (int) (db_one("SELECT COUNT(*) AS c FROM items i WHERE i.is_active = 1 AND {$pub}")['c'] ?? 0);
    $totalPages = max(1, (int) ceil($total / $perPage));
    $page = min($page, $totalPages);
    $offset = ($page - 1) * $perPage;

    $rows = db_all(
        "SELECT i.slug, i.title_bn, i.title_en, i.author_bn, i.author_en, i.book_language, i.edition, i.total_pages, i.cover, i.price, i.is_free, i.tags, i.updated_at, f.name_bn AS folder_bn, f.name_en AS folder_en
         FROM items i
         LEFT JOIN folders f ON f.id = i.folder_id
         WHERE i.is_active = 1 AND {$pub}
         ORDER BY i.updated_at DESC, i.id DESC
         LIMIT {$perPage} OFFSET {$offset}"
    );

    $today = date('Y-m-d');
    $itemsOut = [];
    foreach ($rows as $r) {
        $itemsOut[] = [
            'title_bn'        => (string) $r['title_bn'],
            'title_en'        => (string) $r['title_en'],
            'author'          => (string) ($r['author_en'] ?: $r['author_bn'] ?: ''),
            'folder'          => (string) ($r['folder_en'] ?: $r['folder_bn'] ?: ''),
            'language'        => (string) ($r['book_language'] ?: 'Bangla / English'),
            'edition'         => (string) ($r['edition'] ?: ''),
            'total_pages'     => $r['total_pages'] !== null ? (int) $r['total_pages'] : null,
            'url_bn'          => absolute_url_for_lang('/book/' . rawurlencode((string) $r['slug']), 'bn'),
            'url_en'          => absolute_url_for_lang('/book/' . rawurlencode((string) $r['slug']), 'en'),
            'cover'           => !empty($r['cover']) ? absolute_asset_url((string) $r['cover']) : absolute_asset_url(site_setting('seo_default_og_image', 'assets/img/apple-touch-icon.png')),
            'price'           => ((int) $r['is_free'] || (float) $r['price'] <= 0) ? 0.0 : (float) $r['price'],
            'currency'        => 'BDT',
            'price_as_of'     => $today,
            'tags'            => content_parse_tags($r['tags'] ?? null),
            'updated_at'      => date('c', strtotime((string) ($r['updated_at'] ?? 'now')) ?: time()),
        ];
    }

    return [
        'site'        => site_setting('site_name', 'Bookexa'),
        'feed'        => BASE_URL . '/catalog.json',
        'generated'   => date('c'),
        'page'        => $page,
        'per_page'    => $perPage,
        'total_items' => $total,
        'total_pages' => $totalPages,
        'next'        => $page < $totalPages ? (BASE_URL . '/catalog.json?page=' . ($page + 1)) : null,
        'items'       => $itemsOut,
    ];
}

/**
 * Build paginated public guides.json (metadata & short summaries — never protected text).
 */
function aeo_build_guides_json(int $page = 1, int $perPage = 100): array
{
    $page = max(1, $page);
    $perPage = max(10, min(200, $perPage));
    $pubG = content_guides_published_sql();
    $total = (int) (db_one("SELECT COUNT(*) AS c FROM guides WHERE {$pubG}")['c'] ?? 0);
    $totalPages = max(1, (int) ceil($total / $perPage));
    $page = min($page, $totalPages);
    $offset = ($page - 1) * $perPage;

    $rows = db_all(
        "SELECT slug, category_bn, category_en, tags, title_bn, title_en, summary_bn, summary_en, short_answer_bn, short_answer_en, author_name, reading_minutes, cover, publish_at, created_at, updated_at
         FROM guides WHERE {$pubG}
         ORDER BY updated_at DESC, id DESC
         LIMIT {$perPage} OFFSET {$offset}"
    );

    $guidesOut = [];
    foreach ($rows as $r) {
        $guidesOut[] = [
            'title_bn'        => (string) $r['title_bn'],
            'title_en'        => (string) $r['title_en'],
            'short_answer_bn' => (string) ($r['short_answer_bn'] ?: $r['summary_bn'] ?: ''),
            'short_answer_en' => (string) ($r['short_answer_en'] ?: $r['summary_en'] ?: ''),
            'category'        => (string) ($r['category_en'] ?: $r['category_bn'] ?: ''),
            'tags'            => content_parse_tags($r['tags'] ?? null),
            'author'          => (string) ($r['author_name'] ?: site_setting('site_name', 'Bookexa')),
            'reading_minutes' => (int) ($r['reading_minutes'] ?: 3),
            'url_bn'          => absolute_url_for_lang('/guides/' . rawurlencode((string) $r['slug']), 'bn'),
            'url_en'          => absolute_url_for_lang('/guides/' . rawurlencode((string) $r['slug']), 'en'),
            'cover'           => !empty($r['cover']) ? absolute_asset_url((string) $r['cover']) : null,
            'published_at'    => date('c', strtotime((string) ($r['publish_at'] ?: $r['created_at'])) ?: time()),
            'updated_at'      => date('c', strtotime((string) ($r['updated_at'] ?: $r['created_at'])) ?: time()),
        ];
    }

    return [
        'site'        => site_setting('site_name', 'Bookexa'),
        'feed'        => BASE_URL . '/guides.json',
        'generated'   => date('c'),
        'page'        => $page,
        'per_page'    => $perPage,
        'total_items' => $total,
        'total_pages' => $totalPages,
        'next'        => $page < $totalPages ? (BASE_URL . '/guides.json?page=' . ($page + 1)) : null,
        'guides'      => $guidesOut,
    ];
}

/**
 * Evaluate public pages for AEO/GEO readiness (0-100 score + Top 20 pages needing work).
 */
function aeo_score_all_pages(): array
{
    $scored = [];

    // 1. Score active published Guides
    foreach (db_all('SELECT * FROM guides WHERE is_active = 1 ORDER BY id DESC LIMIT 100') as $g) {
        $hasShort = trim((string) ($g['short_answer_bn'] ?: $g['short_answer_en'])) !== '';
        $hasFaq   = !empty(content_parse_faqs_json($g['faqs_json'] ?? null));
        $hasAuth  = (!empty($g['author_name']) || !empty($g['author_id'])) && !empty($g['updated_at']);
        $body     = (string) ($g['body_bn'] ?: $g['body_en']);
        $hasH2    = preg_match('/<h2\b/i', $body) === 1 && preg_match('/<h1\b/i', $body) === 0;
        $noHidden = trim(strip_tags($body)) !== '';
        $schemaOk = !empty($g['title_bn']) && !empty($g['title_en']);
        $score    = ($hasShort ? 20 : 0) + ($hasFaq ? 20 : 0) + ($hasAuth ? 15 : 0) + ($schemaOk ? 15 : 0) + ($noHidden ? 15 : 0) + ($hasH2 ? 15 : 0);

        $missing = [];
        if (!$hasShort) $missing[] = 'Add Short Answer (40-60w)';
        if (!$hasFaq) $missing[] = 'Add FAQ items';
        if (!$hasAuth) $missing[] = 'Set Author & Date';
        if (!$hasH2) $missing[] = 'Structure with H2/H3 (no body H1)';

        $scored[] = [
            'type'       => 'Guide',
            'title'      => (string) $g['title_en'],
            'url'        => '/guides/' . rawurlencode((string) $g['slug']),
            'edit_url'   => '/admin/guides?edit_guide=' . (int) $g['id'],
            'score'      => $score,
            'has_short'  => $hasShort,
            'has_faq'    => $hasFaq,
            'has_author' => $hasAuth,
            'schema_ok'  => $schemaOk,
            'headings'   => $hasH2,
            'missing'    => $missing,
        ];
    }

    // 2. Score active Folders
    foreach (db_all('SELECT * FROM folders WHERE is_active = 1 AND ' . published_window_sql() . ' ORDER BY id DESC LIMIT 100') as $f) {
        $p = folder_path_string((int) $f['id']);
        if ($p === '') continue;
        $hasShort = trim((string) ($f['short_answer_bn'] ?: $f['short_answer_en'])) !== '';
        $hasFaq   = !empty(content_parse_faqs_json($f['faqs_json'] ?? null));
        $hasAuth  = !empty($f['updated_at']);
        $hasIntro = content_word_count((string) ($f['intro_bn'] ?: $f['intro_en'] ?: $f['description_bn'] ?: $f['description_en'])) >= 100;
        $schemaOk = true;
        $score    = ($hasShort ? 20 : 0) + ($hasFaq ? 20 : 0) + ($hasAuth ? 15 : 0) + ($schemaOk ? 15 : 0) + 15 + ($hasIntro ? 15 : 5);

        $missing = [];
        if (!$hasShort) $missing[] = 'Add Short Answer';
        if (!$hasFaq) $missing[] = 'Add Folder FAQs';
        if (!$hasIntro) $missing[] = 'Expand Intro (>=100w)';

        $scored[] = [
            'type'       => 'Folder',
            'title'      => (string) $f['name_en'],
            'url'        => '/pdf/' . $p,
            'edit_url'   => '/admin/folders?edit=' . (int) $f['id'] . '#folder-form-sheet',
            'score'      => $score,
            'has_short'  => $hasShort,
            'has_faq'    => $hasFaq,
            'has_author' => $hasAuth,
            'schema_ok'  => $schemaOk,
            'headings'   => $hasIntro,
            'missing'    => $missing,
        ];
    }

    // 3. Score active Digital Books
    foreach (db_all('SELECT * FROM items WHERE is_active = 1 AND ' . published_window_sql() . ' ORDER BY id DESC LIMIT 100') as $it) {
        $hasShort = trim((string) ($it['short_answer_bn'] ?: $it['short_answer_en'])) !== '';
        $hasFaq   = !empty(content_parse_faqs_json($it['faqs_json'] ?? null));
        $hasAuth  = (!empty($it['author_en']) || !empty($it['author_bn'])) && !empty($it['updated_at']);
        $hasLearn = trim((string) ($it['what_you_learn_bn'] ?: $it['what_you_learn_en'] ?: $it['description_en'])) !== '';
        $score    = ($hasShort ? 20 : 0) + ($hasFaq ? 20 : 0) + ($hasAuth ? 15 : 0) + 15 + 15 + ($hasLearn ? 15 : 5);

        $missing = [];
        if (!$hasShort) $missing[] = 'Add Short Answer';
        if (!$hasFaq) $missing[] = 'Add Book FAQs';
        if (!$hasAuth) $missing[] = 'Add Author name';
        if (!$hasLearn) $missing[] = 'Add What You Learn';

        $scored[] = [
            'type'       => 'Book',
            'title'      => (string) $it['title_en'],
            'url'        => '/book/' . rawurlencode((string) $it['slug']),
            'edit_url'   => '/admin/pdfs?edit=' . (int) $it['id'] . '#pdf-form',
            'score'      => $score,
            'has_short'  => $hasShort,
            'has_faq'    => $hasFaq,
            'has_author' => $hasAuth,
            'schema_ok'  => true,
            'headings'   => $hasLearn,
            'missing'    => $missing,
        ];
    }

    // 4. Score Hubs
    foreach (db_all('SELECT * FROM hubs WHERE is_active = 1 ORDER BY id ASC LIMIT 50') as $hb) {
        $hasShort = trim((string) ($hb['short_answer_bn'] ?: $hb['short_answer_en'])) !== '';
        $hasFaq   = !empty(content_parse_faqs_json($hb['faqs_json'] ?? null));
        $score    = ($hasShort ? 20 : 0) + ($hasFaq ? 20 : 0) + 15 + 15 + 15 + 15;
        $missing  = [];
        if (!$hasShort) $missing[] = 'Add Hub Short Answer';
        if (!$hasFaq) $missing[] = 'Add Hub FAQs';

        $scored[] = [
            'type'       => 'Hub',
            'title'      => (string) $hb['title_en'],
            'url'        => '/hub/' . rawurlencode((string) $hb['slug']),
            'edit_url'   => '/admin/hubs?edit_hub=' . (int) $hb['id'],
            'score'      => $score,
            'has_short'  => $hasShort,
            'has_faq'    => $hasFaq,
            'has_author' => true,
            'schema_ok'  => true,
            'headings'   => true,
            'missing'    => $missing,
        ];
    }

    usort($scored, static fn(array $a, array $b): int => $a['score'] <=> $b['score']);
    $avg = $scored ? (int) round(array_sum(array_column($scored, 'score')) / count($scored)) : 100;

    return [
        'total_evaluated' => count($scored),
        'average_score'   => $avg,
        'top_to_improve'  => array_slice($scored, 0, 20),
        'all'             => $scored,
    ];
}

/**
 * Persist AEO extra columns (short_answer_bn/en, sources_json, faqs_json) after folder/book/guide/hub save.
 */
function aeo_save_entity_extra(string $table, int $id, array $post): void
{
    if ($id <= 0 || !in_array($table, ['folders', 'items', 'guides', 'hubs'], true)) {
        return;
    }
    $saBn = mb_substr(trim((string) ($post['short_answer_bn'] ?? '')), 0, 600) ?: null;
    $saEn = mb_substr(trim((string) ($post['short_answer_en'] ?? '')), 0, 600) ?: null;
    if ($table === 'guides') {
        $rawSrc = trim((string) ($post['sources_raw'] ?? ''));
        $srcRows = [];
        if ($rawSrc !== '') {
            foreach (preg_split('/\R/u', $rawSrc) ?: [] as $line) {
                $line = trim($line);
                if ($line === '') continue;
                $parts = explode('|', $line, 2);
                $title = trim($parts[0]);
                $url = isset($parts[1]) ? trim($parts[1]) : '';
                if ($title !== '' && ($url === '' || preg_match('#^https?://#i', $url) || str_starts_with($url, '/'))) {
                    $srcRows[] = ['title' => $title, 'url' => $url];
                }
            }
        }
        $srcJson = $srcRows ? json_encode($srcRows, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : null;
        $gFaqsJson = content_serialize_faqs_post($post);
        db_exec(
            'UPDATE guides SET short_answer_bn = ?, short_answer_en = ?, sources_json = ?, faqs_json = ? WHERE id = ?',
            [$saBn, $saEn, $srcJson, $gFaqsJson, $id]
        );
        return;
    }
    db_exec("UPDATE `{$table}` SET short_answer_bn = ?, short_answer_en = ? WHERE id = ?", [$saBn, $saEn, $id]);
}
