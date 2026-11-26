<?php
declare(strict_types=1);

if ($route === '/theme-custom.css') {
    header('Content-Type: text/css; charset=UTF-8');
    header('Cache-Control: public, max-age=300');
    $pri = sanitize_hex_color(site_setting('theme_primary_color', '#2B2F77'), '#2B2F77');
    $acc = sanitize_hex_color(site_setting('theme_accent_color', '#F4A62A'), '#F4A62A');
    $fonts = theme_font_stack_pair();
    echo ":root {\n"
        . "    --primary: {$pri};\n"
        . "    --focus-ring: {$pri};\n"
        . "    --accent: {$acc};\n"
        . "    --font-sans: {$fonts['sans']};\n"
        . "    --font-serif: {$fonts['serif']};\n"
        . "}\n";
    exit;
}

if ($route === '/robots.txt') {
    header('Content-Type: text/plain; charset=UTF-8');
    header('Cache-Control: public, max-age=3600');
    echo aeo_build_robots_txt();
    exit;
}

// Serve IndexNow verification key file directly at /{key}.txt
$idxKey = growth_indexnow_key();
if ($idxKey !== '' && $route === '/' . $idxKey . '.txt') {
    header('Content-Type: text/plain; charset=UTF-8');
    header('Cache-Control: public, max-age=3600');
    echo $idxKey;
    exit;
}

// Serve Search Console HTML verification file when configured in Admin
$gscVerifyFile = ltrim(trim(site_setting('gsc_verify_file_name', '')), '/');
if ($gscVerifyFile !== '' && preg_match('/^[a-zA-Z0-9._-]+\.html$/', $gscVerifyFile) && $route === '/' . $gscVerifyFile) {
    $gscBody = trim(site_setting('gsc_verify_file_content', ''));
    if ($gscBody === '') {
        $gscBody = 'google-site-verification: ' . $gscVerifyFile;
    }
    header('Content-Type: text/html; charset=UTF-8');
    header('Cache-Control: public, max-age=300');
    echo $gscBody;
    exit;
}

seo_normalize_request_and_redirect();

if (preg_match('#^/og-image/(book|product|folder|guide|author)/([^/]+?)(?:\.png)?$#', $route, $ogm)) {
    seo_serve_og_image($ogm[1], rawurldecode($ogm[2]));
}


function serve_cached_sitemap_xml(string $cacheName, callable $builder): never
{
    $cacheDir = ensure_bookexa_cache_dir();
    $smFile = $cacheDir . '/' . $cacheName;
    $smTtl = 3600;
    $xml = null;

    if (is_file($smFile)) {
        $mtime = @filemtime($smFile);
        if ($mtime !== false && (time() - $mtime) < $smTtl) {
            $cachedXml = @file_get_contents($smFile);
            if ($cachedXml !== false && $cachedXml !== '') {
                $xml = $cachedXml;
            }
        }
    }
    if ($xml === null) {
        $xml = (string) $builder();
        $tmp = $smFile . '.' . bin2hex(random_bytes(6)) . '.tmp';
        if (@file_put_contents($tmp, $xml, LOCK_EX) !== false) {
            if (!@rename($tmp, $smFile)) {
                @unlink($tmp);
            }
        } else {
            @unlink($tmp);
        }
    }

    $mtime = is_file($smFile) ? (@filemtime($smFile) ?: time()) : time();
    $etag = '"' . md5($xml) . '"';
    header('Content-Type: application/xml; charset=UTF-8');
    header('Cache-Control: public, max-age=3600');
    header('Last-Modified: ' . gmdate('D, d M Y H:i:s', $mtime) . ' GMT');
    header('ETag: ' . $etag);
    $ifNone = trim((string) ($_SERVER['HTTP_IF_NONE_MATCH'] ?? ''));
    if ($ifNone === $etag) {
        http_response_code(304);
        exit;
    }
    echo $xml;
    exit;
}

function build_bilingual_urlset_xml(array $entries): string
{
    $hf = seo_hreflang_codes();
    $xml = '<?xml version="1.0" encoding="UTF-8"?>' . "\n"
        . '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9"'
        . ' xmlns:xhtml="http://www.w3.org/1999/xhtml"'
        . ' xmlns:image="http://www.google.com/schemas/sitemap-image/1.1">' . "\n";
    foreach ($entries as $entry) {
        $path    = (string) $entry['path'];
        $lastmod = (string) $entry['lastmod'];
        $imgUrl  = trim((string) ($entry['image'] ?? ''));
        $imgTit  = trim((string) ($entry['title'] ?? ''));
        $bnUrl   = absolute_url_for_lang($path, 'bn');
        $enUrl   = absolute_url_for_lang($path, 'en');
        foreach ([$bnUrl, $enUrl] as $loc) {
            $xml .= "  <url>\n"
                . '    <loc>' . htmlspecialchars($loc, ENT_XML1, 'UTF-8') . "</loc>\n"
                . '    <lastmod>' . e($lastmod) . "</lastmod>\n"
                . '    <xhtml:link rel="alternate" hreflang="' . e($hf['bn']) . '" href="' . htmlspecialchars($bnUrl, ENT_XML1, 'UTF-8') . "\"/>\n"
                . '    <xhtml:link rel="alternate" hreflang="' . e($hf['en']) . '" href="' . htmlspecialchars($enUrl, ENT_XML1, 'UTF-8') . "\"/>\n"
                . '    <xhtml:link rel="alternate" hreflang="x-default" href="' . htmlspecialchars($bnUrl, ENT_XML1, 'UTF-8') . "\"/>\n";
            if ($imgUrl !== '') {
                $xml .= "    <image:image>\n"
                    . '      <image:loc>' . htmlspecialchars($imgUrl, ENT_XML1, 'UTF-8') . "</image:loc>\n";
                if ($imgTit !== '') {
                    $xml .= '      <image:title>' . htmlspecialchars($imgTit, ENT_XML1, 'UTF-8') . "</image:title>\n";
                }
                $xml .= "    </image:image>\n";
            }
            $xml .= "  </url>\n";
        }
    }
    $xml .= '</urlset>';
    return $xml;
}

if ($route === '/sitemap.xml') {
    serve_cached_sitemap_xml('sitemap.xml', static function (): string {
        $pub = published_window_sql();
        $chunkCap = 20000; // 20,000 entries x 2 languages = 40,000 URLs (< 50k Google limit)
        seo_sync_authors_directory();

        $staticModTs = max(
            (int) @filemtime(APP_ROOT . '/pages/home.php'),
            (int) @filemtime(APP_ROOT . '/pages/about.php'),
            (int) @filemtime(APP_ROOT . '/pages/contact.php'),
            (int) @filemtime(APP_ROOT . '/pages/legal.php')
        );
        $sections = [
            'static' => [
                'count'   => 14 + (site_setting('shop_enabled', '0') === '1' ? 1 : 0),
                'lastmod' => $staticModTs > 0 ? date('Y-m-d', $staticModTs) : '2026-01-01',
            ],
            'folders' => [
                'count'   => (int) (db_one("SELECT COUNT(*) AS c FROM folders WHERE is_active = 1 AND {$pub}")['c'] ?? 0),
                'lastmod' => seo_format_lastmod(db_one("SELECT MAX(COALESCE(updated_at, created_at)) AS m FROM folders WHERE is_active = 1 AND {$pub}")['m'] ?? null),
            ],
            'books' => [
                'count'   => (int) (db_one("SELECT COUNT(*) AS c FROM items WHERE is_active = 1 AND {$pub}")['c'] ?? 0),
                'lastmod' => seo_format_lastmod(db_one("SELECT MAX(COALESCE(updated_at, created_at)) AS m FROM items WHERE is_active = 1 AND {$pub}")['m'] ?? null),
            ],
            'guides' => [
                'count'   => (int) (db_one('SELECT COUNT(*) AS c FROM guides WHERE ' . content_guides_published_sql())['c'] ?? 0),
                'lastmod' => seo_format_lastmod(db_one('SELECT MAX(COALESCE(updated_at, created_at)) AS m FROM guides WHERE ' . content_guides_published_sql())['m'] ?? null),
            ],
            'authors' => [
                'count'   => (int) (db_one('SELECT COUNT(*) AS c FROM authors WHERE is_active = 1')['c'] ?? 0),
                'lastmod' => seo_format_lastmod(db_one('SELECT MAX(COALESCE(updated_at, created_at)) AS m FROM authors WHERE is_active = 1')['m'] ?? null),
            ],
            'hubs' => [
                'count'   => (int) (db_one('SELECT COUNT(*) AS c FROM hubs WHERE is_active = 1')['c'] ?? 0),
                'lastmod' => seo_format_lastmod(db_one('SELECT MAX(COALESCE(updated_at, created_at)) AS m FROM hubs WHERE is_active = 1')['m'] ?? null),
            ],
        ];
        if (site_setting('shop_enabled', '0') === '1') {
            $sections['products'] = [
                'count'   => (int) (db_one('SELECT COUNT(*) AS c FROM products WHERE is_active = 1')['c'] ?? 0),
                'lastmod' => seo_format_lastmod(db_one('SELECT MAX(COALESCE(updated_at, created_at)) AS m FROM products WHERE is_active = 1')['m'] ?? null),
            ];
        }

        $subs = [];
        foreach ($sections as $sec => $info) {
            $chunks = max(1, (int) ceil($info['count'] / $chunkCap));
            if ($chunks === 1) {
                $subs[] = ['loc' => BASE_URL . '/sitemap-' . $sec . '.xml', 'lastmod' => $info['lastmod']];
            } else {
                for ($c = 1; $c <= $chunks; $c++) {
                    $subs[] = ['loc' => BASE_URL . '/sitemap-' . $sec . '-' . $c . '.xml', 'lastmod' => $info['lastmod']];
                }
            }
        }

        $xml = '<?xml version="1.0" encoding="UTF-8"?>' . "\n"
            . '<sitemapindex xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";
        foreach ($subs as $s) {
            $xml .= '  <sitemap><loc>' . htmlspecialchars($s['loc'], ENT_XML1, 'UTF-8') . '</loc><lastmod>' . e($s['lastmod']) . "</lastmod></sitemap>\n";
        }
        $xml .= '</sitemapindex>';
        return $xml;
    });
}

if (preg_match('#^/sitemap-(static|folders|books|products|guides|authors|hubs)(?:-(\d+))?\.xml$#', $route, $smMatch)) {
    $smSection = $smMatch[1];
    $smPart    = isset($smMatch[2]) ? max(1, (int) $smMatch[2]) : 1;
    $cacheKey  = 'sitemap-' . $smSection . (isset($smMatch[2]) ? ('-' . $smPart) : '') . '.xml';
    serve_cached_sitemap_xml($cacheKey, static function () use ($smSection, $smPart): string {
        $chunkCap = 20000;
        $all = seo_sitemap_section_entries($smSection);
        $slice = array_slice($all, ($smPart - 1) * $chunkCap, $chunkCap);
        return build_bilingual_urlset_xml($slice);
    });
}

if ($route === '/feed.xml') {
    header('Content-Type: application/rss+xml; charset=UTF-8');
    header('Cache-Control: public, max-age=1800');
    echo content_build_rss_feed(current_lang());
    exit;
}

if ($route === '/atom.xml') {
    header('Content-Type: application/atom+xml; charset=UTF-8');
    header('Cache-Control: public, max-age=1800');
    echo content_build_atom_feed(current_lang());
    exit;
}

if ($route === '/llms.txt') {
    header('Content-Type: text/plain; charset=UTF-8');
    header('Cache-Control: public, max-age=3600');
    echo aeo_build_llms_txt(current_lang(), false);
    exit;
}

if ($route === '/llms-full.txt') {
    header('Content-Type: text/plain; charset=UTF-8');
    header('Cache-Control: public, max-age=3600');
    echo aeo_build_llms_txt(current_lang(), true);
    exit;
}

if ($route === '/catalog.json') {
    header('Content-Type: application/json; charset=UTF-8');
    header('Cache-Control: public, max-age=1800');
    $cPage = max(1, (int) ($_GET['page'] ?? 1));
    $cacheDir = ensure_bookexa_cache_dir();
    $cFile = $cacheDir . "/aeo_catalog_p{$cPage}.json";
    $out = (is_file($cFile) && (time() - (@filemtime($cFile) ?: 0)) < 1800) ? @file_get_contents($cFile) : false;
    if (!is_string($out) || $out === '') {
        $out = json_encode(aeo_build_catalog_json($cPage, 100), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT) ?: '{}';
        @file_put_contents($cFile, $out, LOCK_EX);
    }
    echo $out . "\n";
    exit;
}

if ($route === '/guides.json') {
    header('Content-Type: application/json; charset=UTF-8');
    header('Cache-Control: public, max-age=1800');
    $gPage = max(1, (int) ($_GET['page'] ?? 1));
    $cacheDir = ensure_bookexa_cache_dir();
    $gFile = $cacheDir . "/aeo_guides_p{$gPage}.json";
    $out = (is_file($gFile) && (time() - (@filemtime($gFile) ?: 0)) < 1800) ? @file_get_contents($gFile) : false;
    if (!is_string($out) || $out === '') {
        $out = json_encode(aeo_build_guides_json($gPage, 100), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT) ?: '{}';
        @file_put_contents($gFile, $out, LOCK_EX);
    }
    echo $out . "\n";
    exit;
}

if ($route === '/faq') {
    $fLang = current_lang();
    $faqRows = db_all("SELECT * FROM editorial_faqs WHERE scope_type = 'global' AND is_active = 1 ORDER BY topic ASC, sort_order ASC, id ASC");
    $faqMain = [];
    foreach ($faqRows as $fq) {
        $qt = trim((string) ($fLang === 'bn' ? ($fq['question_bn'] ?: $fq['question_en']) : ($fq['question_en'] ?: $fq['question_bn'])));
        $at = trim((string) ($fLang === 'bn' ? ($fq['answer_bn'] ?: $fq['answer_en']) : ($fq['answer_en'] ?: $fq['answer_bn'])));
        if ($qt !== '' && $at !== '') {
            $faqMain[] = [
                '@type' => 'Question',
                'name' => $qt,
                'acceptedAnswer' => ['@type' => 'Answer', 'text' => $at],
            ];
        }
    }
    $faqTitle = $fLang === 'bn' ? 'সচরাচর জিজ্ঞাসা (FAQ) — পেমেন্ট, রিডার ও বই' : 'Frequently Asked Questions — Payments, Reader & Delivery';
    $fMeta = seo_render_meta('static', ['title' => $faqTitle], ['description' => $fLang === 'bn' ? 'বুকএক্সায় বিকাশ ও নগদ পেমেন্ট, নিরাপদ পিডিএফ রিডার, ডিভাইস সীমা, রিফান্ড ও প্রিন্টেড বই ডেলিভারি সংক্রান্ত সকল প্রশ্নের সরাসরি উত্তর।' : 'Direct answers on bKash and Nagad payments, the secure watermarked PDF reader, device limits, refunds, and delivery across Bangladesh.']);
    $faqSchemas = [
        seo_breadcrumb_schema([
            ['name' => t('nav_home'), 'url' => absolute_url_for_lang('/', $fLang)],
            ['name' => t('nav_faq'), 'url' => absolute_url_for_lang('/faq', $fLang)],
        ]),
        aeo_webpage_schema($faqTitle, absolute_url_for_lang('/faq', $fLang), $fMeta['description'], $fLang, site_setting('legal_last_reviewed', date('Y-m-d'))),
    ];
    if ($faqMain) {
        $faqSchemas[] = ['@context' => 'https://schema.org', '@type' => 'FAQPage', 'mainEntity' => $faqMain];
    }
    render_public('faq.php', [
        'faqRows'         => $faqRows,
        'pageTitle'       => $fMeta['title'],
        'fullTitleExact'  => true,
        'metaDescription' => $fMeta['description'],
        'canonicalPath'   => '/faq',
        'jsonLdSchemas'   => $faqSchemas,
        'activeNav'       => 'faq',
    ]);
}

if (preg_match('#^/sample-thumb/([^/]+)/(\d+)$#', $route, $stm)) {
    $sSlug = rawurldecode($stm[1]);
    $pNo   = (int) $stm[2];
    $item  = db_one('SELECT id, sample_pages, is_active FROM items WHERE slug = ? AND is_active = 1 AND ' . published_window_sql() . ' LIMIT 1', [$sSlug]);
    if (!$item || $pNo < 1 || $pNo > min(3, (int) ($item['sample_pages'] ?? 0))) {
render_error_page(404);
    }
    $iid = (int) $item['id'];
    $srcPath = null;
    foreach (['webp', 'png', 'jpg', 'jpeg'] as $ext) {
        $cand = APP_ROOT . "/storage/pages/{$iid}/page-{$pNo}.{$ext}";
        if (is_file($cand)) {
            $srcPath = $cand;
            break;
        }
    }
    if ($srcPath === null || !function_exists('imagecreatetruecolor')) {
render_error_page(404);
    }
    $cDir = ensure_bookexa_cache_dir('sample-thumbs');
    $cPath = "{$cDir}/{$iid}_p{$pNo}.png";
    if (!is_file($cPath) || (@filemtime($cPath) ?: 0) < (@filemtime($srcPath) ?: 0)) {
        $ext = strtolower(pathinfo($srcPath, PATHINFO_EXTENSION));
        $src = match ($ext) {
            'webp' => function_exists('imagecreatefromwebp') ? @imagecreatefromwebp($srcPath) : null,
            'png'  => @imagecreatefrompng($srcPath),
            'jpg', 'jpeg' => @imagecreatefromjpeg($srcPath),
            default => null,
        };
        if (!$src) {
render_error_page(404);
        }
        $sw = imagesx($src);
        $sh = imagesy($src);
        $dw = min(340, max(120, $sw));
        $dh = (int) round(($sh / max(1, $sw)) * $dw);
        $dst = imagecreatetruecolor($dw, $dh);
        imagecopyresampled($dst, $src, 0, 0, 0, 0, $dw, $dh, $sw, $sh);
        imagedestroy($src);
        $stampCol = imagecolorallocatealpha($dst, 43, 47, 119, 45);
        imagestring($dst, 3, 12, 12, 'SAMPLE PREVIEW', $stampCol);
        imagepng($dst, $cPath, 6);
        imagedestroy($dst);
    }
    header('Content-Type: image/png');
    header('Cache-Control: public, max-age=86400');
    readfile($cPath);
    exit;
}

// ----------------------------------------------------------------------------
// Protected Debug & Design-System Routes (Admin Only — 403 for public)
// ----------------------------------------------------------------------------

if ($route === '/') {
    $homeLang = current_lang();
    $siteName = site_setting('site_name', 'Bookexa');
    $firstBanner = db_one(
        'SELECT image FROM banners
         WHERE is_active = 1
           AND (COALESCE(publish_at, starts_at) IS NULL OR COALESCE(publish_at, starts_at) <= NOW())
           AND (COALESCE(unpublish_at, ends_at) IS NULL OR COALESCE(unpublish_at, ends_at) > NOW())
         ORDER BY sort_order ASC, id DESC LIMIT 1'
    );
    $heroImgAbs = !empty($firstBanner['image']) ? absolute_asset_url((string) $firstBanner['image']) : null;
    $homeMeta = seo_render_meta('home', ['title' => $siteName]);
    $homeSchemas = [
        seo_org_schema(),
        [
            '@context'        => 'https://schema.org',
            '@type'           => 'WebSite',
            'name'            => $siteName,
            'url'             => absolute_url_for_lang('/', $homeLang),
            'inLanguage'      => $homeLang,
            'potentialAction' => [
                '@type'       => 'SearchAction',
                'target'      => absolute_url_for_lang('/search', $homeLang) . '?q={search_term_string}',
                'query-input' => 'required name=search_term_string',
            ],
        ],
        aeo_webpage_schema($homeMeta['title'], absolute_url_for_lang('/', $homeLang), $homeMeta['description'], $homeLang),
        seo_breadcrumb_schema([
            ['name' => t('nav_home'), 'url' => absolute_url_for_lang('/', $homeLang)],
        ]),
    ];
    render_public('home.php', [
        'pageTitle'        => $homeMeta['title'],
        'fullTitleExact'   => true,
        'metaDescription'  => $homeMeta['description'],
        'canonicalPath'    => '/',
        'activeNav'        => 'home',
        'ogImage'          => $heroImgAbs ?: absolute_asset_url(site_setting('seo_default_og_image', 'assets/img/apple-touch-icon.png')),
        'preloadHeroImage' => $heroImgAbs,
        'jsonLdSchemas'    => $homeSchemas,
    ]);
}

if ($route === '/demo' || $route === '/styleguide') {
    header('X-Robots-Tag: noindex, nofollow, noarchive');
    if (app_config('app_env', 'production') !== 'local' || !current_admin()) {
        render_error_page(403);
    }
    if ($route === '/demo') {
        render_public('demo.php', ['pageTitle' => 'Component Demo', 'activeNav' => '']);
    }
    render_public('styleguide.php', ['pageTitle' => 'Style Guide', 'canonicalPath' => '/styleguide', 'activeNav' => '']);
}

if ($route === '/pdf' || str_starts_with($route, '/pdf/')) {
    $sub = trim(substr($route, 4), '/');
    $segments = $sub === '' ? [] : array_values(array_filter(explode('/', $sub), fn($s) => $s !== ''));
    $segments = array_map('rawurldecode', $segments);
    $filters = browse_extract_filters($_GET);
    $filterQ = $filters['q'];
    $fLang = current_lang();
    $isFilteredPdf = ($filterQ !== '' || $filters['sort'] !== 'newest' || $filters['price_type'] !== 'all' || $filters['author'] !== '' || !empty($filters['has_sample']));

    if (empty($segments)) {
        $currentFolder = null;
        $breadcrumbs = [];
        if ($filterQ === '') {
            $tree = get_cached_folder_tree();
            $subfolders = [];
            foreach ($tree['children']['root'] ?? [] as $cid) {
                if (isset($tree['byId'][(string) $cid])) {
                    $subfolders[] = $tree['byId'][(string) $cid];
                }
            }
        } else {
            $pubF = published_window_sql('f');
            $like = '%' . escape_like($filterQ) . '%';
            $subfolders = db_all(
                "SELECT f.* FROM folders f
                 WHERE f.parent_id IS NULL AND f.is_active = 1 AND {$pubF}
                   AND (f.name_bn LIKE ? ESCAPE '\\\\' OR f.name_en LIKE ? ESCAPE '\\\\' OR f.description_bn LIKE ? ESCAPE '\\\\' OR f.description_en LIKE ? ESCAPE '\\\\')
                 ORDER BY f.sort_order ASC, f.id ASC",
                [$like, $like, $like, $like]
            );
        }
        $items = [];
        $authors = [];
        $page = 1;
        $totalPages = 1;
        $totalItems = 0;
        $slugPrefix = '';
        $fMeta = seo_render_meta('folder', ['title' => t('browse_folders_title')], ['description' => t('featured_folders_sub')], 1);
        $title = $fMeta['title'];
        $metaDesc = $fMeta['description'];
        $ogImgFolder = absolute_asset_url(site_setting('seo_default_og_image', 'assets/img/apple-touch-icon.png'));
        $ogW = null;
        $ogH = null;

        $colEntries = [];
        foreach ($subfolders as $sf) {
            $colEntries[] = ['name' => bi($sf, 'name'), 'url' => absolute_url_for_lang('/pdf/' . rawurlencode((string) $sf['slug']), $fLang)];
        }
        $folderSchemas = [
            seo_breadcrumb_schema([
                ['name' => t('nav_home'), 'url' => absolute_url_for_lang('/', $fLang)],
                ['name' => t('nav_pdfs'), 'url' => absolute_url_for_lang('/pdf', $fLang)],
            ]),
            seo_collection_schema(t('browse_folders_title'), absolute_url_for_lang('/pdf', $fLang), $metaDesc, $colEntries),
        ];
    } else {
        $currentFolder = resolve_folder_by_slugs($segments);
        if (!$currentFolder) {
            $oldPathKey = implode('/', $segments);
            $hist = db_one("SELECT entity_id FROM slug_history WHERE entity_type = 'folder' AND old_path = ? LIMIT 1", [$oldPathKey]);
            if ($hist) {
                $targetFolder = db_one('SELECT id FROM folders WHERE id = ? AND is_active = 1 AND ' . published_window_sql() . ' LIMIT 1', [(int) $hist['entity_id']]);
                if ($targetFolder) {
                    $qStr = (string) ($_SERVER['QUERY_STRING'] ?? '');
                    $redirUrl = folder_full_url((int) $targetFolder['id']) . ($qStr !== '' ? ('?' . $qStr) : '');
                    header('Location: ' . $redirUrl, true, 301);
                    exit;
                }
            }
render_error_page(404);
        }
        $fid = (int) $currentFolder['id'];
        if ($vUser = current_user()) {
            record_user_folder_visit((int) $vUser['id'], $fid);
        }
        $breadcrumbs = folder_breadcrumbs($fid);

        if ($filterQ === '') {
            $tree = get_cached_folder_tree();
            $subfolders = [];
            foreach ($tree['children'][(string) $fid] ?? [] as $cid) {
                if (isset($tree['byId'][(string) $cid])) {
                    $subfolders[] = $tree['byId'][(string) $cid];
                }
            }
        } else {
            $pubF = published_window_sql('f');
            $like = '%' . escape_like($filterQ) . '%';
            $subfolders = db_all(
                "SELECT f.* FROM folders f
                 WHERE f.parent_id = ? AND f.is_active = 1 AND {$pubF}
                   AND (f.name_bn LIKE ? ESCAPE '\\\\' OR f.name_en LIKE ? ESCAPE '\\\\')
                 ORDER BY f.sort_order ASC, f.id ASC",
                [$fid, $like, $like]
            );
        }

        $authors = browse_distinct_authors($fid);
        $itemRes = browse_query_items(['mode' => 'folder', 'folder_id' => $fid], $filters);
        $items = $itemRes['items'];
        preload_items_card_meta($items);
        $page = $itemRes['page'];
        $totalPages = $itemRes['totalPages'];
        $totalItems = $itemRes['total'];

        $slugPrefix = implode('/', array_map('rawurlencode', $segments));
        $seoT = bi($currentFolder, 'seo_title');
        $seoD = bi($currentFolder, 'seo_description');
        $leafFolderName = bi($currentFolder, 'name');
        $folderDisplayTitle = count($breadcrumbs) > 1
            ? ($leafFolderName . ' — ' . bi($breadcrumbs[count($breadcrumbs) - 2], 'name'))
            : $leafFolderName;
        $fMeta = seo_render_meta(
            'folder',
            ['title' => $folderDisplayTitle, 'folder' => $folderDisplayTitle],
            ['title' => $seoT, 'description' => $seoD !== '' ? $seoD : bi($currentFolder, 'description')],
            $isFilteredPdf ? 1 : $page
        );
        $title = $fMeta['title'];
        $metaDesc = $fMeta['description'];
        $ogImgFolder = seo_og_image_url('folder', (string) $currentFolder['slug'], $currentFolder['thumbnail'] ?? null);
        $ogW = function_exists('imagecreatetruecolor') ? 1200 : null;
        $ogH = function_exists('imagecreatetruecolor') ? 630 : null;

        $crumbs = [
            ['name' => t('nav_home'), 'url' => absolute_url_for_lang('/', $fLang)],
            ['name' => t('nav_pdfs'), 'url' => absolute_url_for_lang('/pdf', $fLang)],
        ];
        $acc = [];
        foreach ($breadcrumbs as $bc) {
            $acc[] = rawurlencode((string) $bc['slug']);
            $crumbs[] = ['name' => bi($bc, 'name'), 'url' => absolute_url_for_lang('/pdf/' . implode('/', $acc), $fLang)];
        }
        $colEntries = [];
        foreach ($subfolders as $sf) {
            $colEntries[] = ['name' => bi($sf, 'name'), 'url' => absolute_url_for_lang($route . '/' . rawurlencode((string) $sf['slug']), $fLang)];
        }
        foreach ($items as $vi) {
            $colEntries[] = ['name' => bi($vi, 'title'), 'url' => absolute_url_for_lang('/book/' . rawurlencode((string) $vi['slug']), $fLang)];
        }
        $folderSchemas = [
            seo_breadcrumb_schema($crumbs),
            seo_collection_schema(bi($currentFolder, 'name'), absolute_url_for_lang($route, $fLang), $metaDesc, $colEntries),
            aeo_webpage_schema($title, absolute_url_for_lang($canonRoute ?? $route, $fLang), $metaDesc, $fLang, seo_format_lastmod($currentFolder['updated_at'] ?? null, $currentFolder['created_at'] ?? null)),
        ];
        $folderFaqsForLd = content_parse_faqs_json($currentFolder['faqs_json'] ?? null);
        if ($folderFaqsForLd) {
            $faqMain = [];
            foreach ($folderFaqsForLd as $fq) {
                $qt = $fLang === 'bn' ? ($fq['q_bn'] ?: $fq['q_en']) : ($fq['q_en'] ?: $fq['q_bn']);
                $at = $fLang === 'bn' ? ($fq['a_bn'] ?: $fq['a_en']) : ($fq['a_en'] ?: $fq['a_bn']);
                if ($qt !== '' && $at !== '') {
                    $faqMain[] = ['@type' => 'Question', 'name' => $qt, 'acceptedAnswer' => ['@type' => 'Answer', 'text' => $at]];
                }
            }
            if ($faqMain) {
                $folderSchemas[] = ['@context' => 'https://schema.org', '@type' => 'FAQPage', 'mainEntity' => $faqMain];
            }
        }
        $popularInFolder = db_all(
            'SELECT id, folder_id, slug, title_bn, title_en, author_bn, author_en, cover, price, original_price, is_free, sample_pages, total_pages
             FROM items WHERE folder_id = ? AND is_active = 1 AND ' . published_window_sql() . ' ORDER BY (read_count + view_count) DESC, id DESC LIMIT 4',
            [$fid]
        );
        // Adjacent sibling folders ("previous & next" in folders)
        $prevFolderUrl = null;
        $prevFolderName = null;
        $nextFolderUrl = null;
        $nextFolderName = null;
        $parentKey = $currentFolder['parent_id'] !== null ? (string) ((int) $currentFolder['parent_id']) : 'root';
        $sibTree = get_cached_folder_tree();
        $sibIds = $sibTree['children'][$parentKey] ?? [];
        $posIdx = array_search($fid, $sibIds, true);
        if ($posIdx !== false) {
            if ($posIdx > 0 && isset($sibTree['byId'][(string) $sibIds[$posIdx - 1]])) {
                $pSib = $sibTree['byId'][(string) $sibIds[$posIdx - 1]];
                $prevFolderUrl = folder_full_url((int) $pSib['id']);
                $prevFolderName = bi($pSib, 'name');
            }
            if ($posIdx + 1 < count($sibIds) && isset($sibTree['byId'][(string) $sibIds[$posIdx + 1]])) {
                $nSib = $sibTree['byId'][(string) $sibIds[$posIdx + 1]];
                $nextFolderUrl = folder_full_url((int) $nSib['id']);
                $nextFolderName = bi($nSib, 'name');
            }
        }
    }

    $canonRoute = $route;
    $robotsDir  = '';
    $prevUrl    = null;
    $nextUrl    = null;
    if ($isFilteredPdf) {
        header('X-Robots-Tag: noindex, follow');
        $robotsDir = 'noindex, follow';
    } else {
        if ($page > 1) {
            $canonRoute = $route . '?page=' . $page;
            $prevUrl = absolute_url_for_lang($route . ($page - 1 > 1 ? '?page=' . ($page - 1) : ''), $fLang);
        }
        if ($page < $totalPages) {
            $nextUrl = absolute_url_for_lang($route . '?page=' . ($page + 1), $fLang);
        }
    }

    render_public('folder.php', [
        'currentFolder'   => $currentFolder,
        'breadcrumbs'     => $breadcrumbs,
        'subfolders'      => $subfolders,
        'items'           => $items,
        'popularInFolder' => $popularInFolder ?? [],
        'prevFolderUrl'   => $prevFolderUrl ?? null,
        'prevFolderName'  => $prevFolderName ?? null,
        'nextFolderUrl'   => $nextFolderUrl ?? null,
        'nextFolderName'  => $nextFolderName ?? null,
        'slugPrefix'      => $slugPrefix,
        'filterQ'         => $filterQ,
        'filters'         => $filters,
        'authors'         => $authors,
        'page'            => $page,
        'totalPages'      => $totalPages,
        'totalItems'      => $totalItems,
        'pageTitle'       => $title,
        'fullTitleExact'  => true,
        'metaDescription' => $metaDesc,
        'ogImage'         => $ogImgFolder,
        'ogImageWidth'    => $ogW,
        'ogImageHeight'   => $ogH,
        'robotsDirective' => $robotsDir,
        'prevUrl'         => $prevUrl,
        'nextUrl'         => $nextUrl,
        'jsonLdSchemas'   => $folderSchemas,
        'canonicalPath'   => $canonRoute,
        'activeNav'       => 'pdfs',
    ]);
}

if (preg_match('#^/book/([^/]+)/review$#', $route, $m) && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $slug = rawurldecode($m[1]);
    $user = require_login('/book/' . rawurlencode($slug));
    verify_csrf();
    $item = db_one('SELECT id, slug FROM items WHERE slug = ? AND is_active = 1 LIMIT 1', [$slug]);
    if (!$item) {
render_error_page(404);
    }
    $uid = (int) $user['id'];
    $itemId = (int) $item['id'];

    if (!user_can_review_item($uid, $itemId)) {
        flash('danger', current_lang() === 'bn' ? 'কেবল এই বইটির ক্রেতা বা পাঠক রিভিউ দিতে পারবেন।' : 'Only users who own or have read this PDF can review it.');
        redirect('/book/' . rawurlencode($slug) . '#reviews');
    }
    if (db_one('SELECT id FROM reviews WHERE user_id = ? AND item_id = ? LIMIT 1', [$uid, $itemId])) {
        flash('warning', current_lang() === 'bn' ? 'আপনি ইতিমধ্যে এই বইয়ে একটি রিভিউ দিয়েছেন।' : 'You have already submitted a review for this PDF.');
        redirect('/book/' . rawurlencode($slug) . '#reviews');
    }
    if (!user_rate_limit_check('review_cd:u:' . $uid, 1, 20) || !user_rate_limit_check('review_hr:u:' . $uid, 5, 3600)) {
        flash('danger', current_lang() === 'bn' ? 'অনেক দ্রুত রিভিউ পাঠানো হয়েছে। কিছুক্ষণ পর আবার চেষ্টা করুন।' : 'Too many review submissions too quickly. Please wait before posting again.');
        redirect('/book/' . rawurlencode($slug) . '#reviews');
    }

    $rating = (int) ($_POST['rating'] ?? 0);
    $comment = trim((string) ($_POST['comment'] ?? ''));
    if ($rating < 1 || $rating > 5 || mb_strlen($comment) < 3 || mb_strlen($comment) > 600) {
        flash('danger', current_lang() === 'bn' ? '১ থেকে ৫ স্টার এবং ৩ থেকে ৬০০ অক্ষরের মতামত দিন।' : 'Provide 1–5 stars and a review between 3 and 600 characters.');
        redirect('/book/' . rawurlencode($slug) . '#reviews');
    }

    db_exec(
        "INSERT INTO reviews (user_id, item_id, rating, comment, status) VALUES (?, ?, ?, ?, 'approved')",
        [$uid, $itemId, $rating, $comment]
    );
    flash('success', current_lang() === 'bn' ? 'আপনার রিভিউ প্রকাশ হয়েছে!' : 'Thank you! Your review has been published.');
    redirect('/book/' . rawurlencode($slug) . '#reviews');
}

if (preg_match('#^/book/([^/]+)$#', $route, $m)) {
    $slug = rawurldecode($m[1]);
    $item = db_one(
        'SELECT id, folder_id, slug, title_bn, title_en, author_bn, author_en, isbn, publisher, edition, book_language, tags, faqs_json, description_bn, description_en, what_you_learn_bn, what_you_learn_en, who_for_bn, who_for_en, toc_json, seo_title_bn, seo_title_en, seo_description_bn, seo_description_en, cover, price, original_price, is_free, sample_pages, total_pages, publish_at, created_at, updated_at
         FROM items WHERE slug = ? AND is_active = 1 AND ' . published_window_sql() . ' LIMIT 1',
        [$slug]
    );
    if (!$item) {
        $hist = db_one("SELECT entity_id FROM slug_history WHERE entity_type = 'item' AND old_path = ? LIMIT 1", [$slug]);
        if ($hist) {
            $targetItem = db_one('SELECT slug FROM items WHERE id = ? AND is_active = 1 AND ' . published_window_sql() . ' LIMIT 1', [(int) $hist['entity_id']]);
            if ($targetItem) {
                header('Location: ' . url('/book/' . rawurlencode((string) $targetItem['slug'])), true, 301);
                exit;
            }
        }
render_error_page(404);
    }
    $bLang = current_lang();
    $ogImg = seo_og_image_url('book', (string) $item['slug'], $item['cover'] ?? null);
    $bookCanonicalAbs = absolute_url_for_lang('/book/' . rawurlencode((string) $item['slug']), $bLang);
    $seoTitle = bi($item, 'seo_title');
    $seoDesc  = bi($item, 'seo_description');
    $bookTitle = bi($item, 'title');
    $bookAuthor = bi($item, 'author');
    $bCrumbsRaw = !empty($item['folder_id']) ? folder_breadcrumbs((int) $item['folder_id']) : [];
    $folderName = $bCrumbsRaw ? bi(end($bCrumbsRaw), 'name') : t('nav_pdfs');
    $priceStr = ((int) $item['is_free'] || (float) $item['price'] <= 0) ? t('free_badge') : ('৳' . number_format((float) $item['price'], 0));

    $bkMeta = seo_render_meta(
        'book',
        ['title' => $bookTitle, 'author' => $bookAuthor, 'folder' => $folderName, 'price' => $priceStr],
        ['title' => $seoTitle, 'description' => $seoDesc !== '' ? $seoDesc : bi($item, 'description')],
        1
    );

    $crumbs = [
        ['name' => t('nav_home'), 'url' => absolute_url_for_lang('/', $bLang)],
        ['name' => t('nav_pdfs'), 'url' => absolute_url_for_lang('/pdf', $bLang)],
    ];
    $acc = [];
    foreach ($bCrumbsRaw as $bc) {
        $acc[] = rawurlencode((string) $bc['slug']);
        $crumbs[] = ['name' => bi($bc, 'name'), 'url' => absolute_url_for_lang('/pdf/' . implode('/', $acc), $bLang)];
    }
    $crumbs[] = ['name' => $bookTitle, 'url' => $bookCanonicalAbs];

    $siteName = site_setting('site_name', 'Bookexa');
    $coverForSchema = !empty($item['cover']) ? absolute_asset_url((string) $item['cover']) : $ogImg;
    $bookSchema = [
        '@context'   => 'https://schema.org',
        '@type'      => 'Book',
        'name'       => $bookTitle,
        'url'        => $bookCanonicalAbs,
        'image'      => $coverForSchema,
        'bookFormat' => 'https://schema.org/EBook',
        'inLanguage' => $bLang,
        'datePublished' => seo_format_lastmod($item['publish_at'] ?? null, $item['created_at'] ?? null),
        'offers'     => [
            '@type'           => 'Offer',
            'priceCurrency'   => 'BDT',
            'price'           => ((int) $item['is_free'] || (float) $item['price'] <= 0) ? '0' : number_format((float) $item['price'], 2, '.', ''),
            'availability'    => 'https://schema.org/InStock',
            'priceValidUntil' => date('Y-m-d', strtotime('+1 year')),
            'url'             => $bookCanonicalAbs,
            'seller'          => ['@type' => 'Organization', 'name' => $siteName],
        ],
    ];
    if ($bookAuthor !== '') {
        $bookSchema['author'] = ['@type' => 'Person', 'name' => $bookAuthor];
    }
    if (!empty($item['isbn'])) {
        $bookSchema['isbn'] = (string) $item['isbn'];
    }
    if (!empty($item['publisher'])) {
        $bookSchema['publisher'] = ['@type' => 'Organization', 'name' => (string) $item['publisher']];
    }
    if (!empty($item['total_pages'])) {
        $bookSchema['numberOfPages'] = (int) $item['total_pages'];
    }
    $rSum = item_rating_summary((int) $item['id']);
    if (($rSum['count'] ?? 0) > 0) {
        $bookSchema['aggregateRating'] = [
            '@type'       => 'AggregateRating',
            'ratingValue' => (string) $rSum['avg'],
            'reviewCount' => (int) $rSum['count'],
            'bestRating'  => '5',
            'worstRating' => '1',
        ];
        $visRevs = db_all(
            "SELECT r.rating, r.comment, r.created_at, u.name AS user_name
             FROM reviews r JOIN users u ON u.id = r.user_id
             WHERE r.item_id = ? AND r.status = 'approved' ORDER BY r.id DESC LIMIT 5",
            [(int) $item['id']]
        );
        if ($visRevs) {
            $revSchemas = [];
            foreach ($visRevs as $vr) {
                $revSchemas[] = [
                    '@type'        => 'Review',
                    'author'       => ['@type' => 'Person', 'name' => (string) $vr['user_name']],
                    'datePublished'=> seo_format_lastmod($vr['created_at'] ?? null),
                    'reviewBody'   => (string) ($vr['comment'] ?? ''),
                    'reviewRating' => ['@type' => 'Rating', 'ratingValue' => (int) $vr['rating'], 'bestRating' => 5, 'worstRating' => 1],
                ];
            }
            $bookSchema['review'] = $revSchemas;
        }
    }

    $tocChapters = [];
    if (!empty($item['toc_json'])) {
        $decToc = json_decode((string) $item['toc_json'], true);
        if (is_array($decToc)) {
            $tocChapters = $decToc;
        }
    }
    $sampleThumbPages = [];
    $maxThumbs = min(3, (int) ($item['sample_pages'] ?? 0));
    if ($maxThumbs > 0) {
        $iid = (int) $item['id'];
        for ($tp = 1; $tp <= $maxThumbs; $tp++) {
            foreach (['webp', 'png', 'jpg', 'jpeg'] as $ext) {
                if (is_file(APP_ROOT . "/storage/pages/{$iid}/page-{$tp}.{$ext}")) {
                    $sampleThumbPages[] = $tp;
                    break;
                }
            }
        }
    }
    $relatedByTag = [];
    $tagList = content_parse_tags($item['tags'] ?? null);
    if ($tagList) {
        $firstTagLike = '%' . escape_like($tagList[0]) . '%';
        $relatedByTag = db_all(
            'SELECT * FROM items WHERE id != ? AND is_active = 1 AND ' . published_window_sql() . " AND COALESCE(tags,'') LIKE ? ESCAPE '\\\\' ORDER BY is_featured DESC, id DESC LIMIT 4",
            [(int) $item['id'], $firstTagLike]
        );
    }
    $bookSchemas = [
        seo_breadcrumb_schema($crumbs),
        $bookSchema,
        aeo_webpage_schema($bkMeta['title'], $bookCanonicalAbs, $bkMeta['description'], $bLang, seo_format_lastmod($item['updated_at'] ?? null, $item['created_at'] ?? null)),
    ];
    $bkFaqs = content_parse_faqs_json($item['faqs_json'] ?? null);
    if ($bkFaqs) {
        $faqMain = [];
        foreach ($bkFaqs as $fq) {
            $qt = $bLang === 'bn' ? ($fq['q_bn'] ?: $fq['q_en']) : ($fq['q_en'] ?: $fq['q_bn']);
            $at = $bLang === 'bn' ? ($fq['a_bn'] ?: $fq['a_en']) : ($fq['a_en'] ?: $fq['a_bn']);
            if ($qt !== '' && $at !== '') {
                $faqMain[] = ['@type' => 'Question', 'name' => $qt, 'acceptedAnswer' => ['@type' => 'Answer', 'text' => $at]];
            }
        }
        if ($faqMain) {
            $bookSchemas[] = ['@context' => 'https://schema.org', '@type' => 'FAQPage', 'mainEntity' => $faqMain];
        }
    }

    $bUser = current_user();
    $bIsFree = (bool) $item['is_free'] || (float) $item['price'] <= 0;
    $bIsOwned = $bUser ? user_owns_item((int) $bUser['id'], (int) $item['id']) : false;
    render_public('item_detail.php', [
        'item'                => $item,
        'tocChapters'         => $tocChapters,
        'sampleThumbPages'    => $sampleThumbPages,
        'relatedByTag'        => $relatedByTag,
        'isPaidProtectedPage' => (!$bIsFree && !$bIsOwned),
        'preloadHeroImage'    => !empty($item['cover']) ? absolute_asset_url((string) $item['cover']) : null,
        'ogImage'             => $ogImg,
        'ogImageWidth'     => function_exists('imagecreatetruecolor') ? 1200 : null,
        'ogImageHeight'    => function_exists('imagecreatetruecolor') ? 630 : null,
        'ogType'           => 'book',
        'pageTitle'        => $bkMeta['title'],
        'fullTitleExact'   => true,
        'metaDescription'  => $bkMeta['description'],
        'jsonLdSchemas'    => $bookSchemas,
        'canonicalPath'   => '/book/' . rawurlencode($item['slug']),
        'activeNav'       => 'pdfs',
    ]);
}

if ($route === '/shop') {
    $categories = shop_categories_flat_tree(true);
    $catParam = trim((string) ($_GET['cat'] ?? ''));
    $catId = 0;
    if ($catParam !== '') {
        if (ctype_digit($catParam)) {
            $catId = (int) $catParam;
        } else {
            $cRow = db_one('SELECT id FROM shop_categories WHERE slug = ? AND is_active = 1 LIMIT 1', [$catParam]);
            $catId = $cRow ? (int) $cRow['id'] : 0;
        }
    }
    $filters = [
        'category_id' => $catId,
        'q'           => trim((string) ($_GET['q'] ?? '')),
        'sort'        => (string) ($_GET['sort'] ?? 'newest'),
        'in_stock'    => ($_GET['in_stock'] ?? '') === '1',
        'page'        => max(1, (int) ($_GET['page'] ?? 1)),
        'per_page'    => 24,
    ];
    $res = shop_query_products($filters);
    $activeTrail = $catId > 0 ? shop_category_breadcrumb($catId) : [];
    $uid = current_user() ? (int) current_user()['id'] : null;

    $sLang = current_lang();
    $crumbs = [
        ['name' => t('nav_home'), 'url' => absolute_url_for_lang('/', $sLang)],
        ['name' => t('nav_shop'), 'url' => absolute_url_for_lang('/shop', $sLang)],
    ];
    foreach ($activeTrail as $tc) {
        $crumbs[] = [
            'name' => $sLang === 'bn' ? (string) $tc['name_bn'] : (string) $tc['name_en'],
            'url'  => absolute_url_for_lang('/shop?cat=' . (int) $tc['id'], $sLang),
        ];
    }

    $isShopFiltered = ($catId > 0 || $filters['q'] !== '' || $filters['sort'] !== 'newest' || !empty($filters['in_stock']));
    $sPage = (int) $res['page'];
    $sTotalPages = (int) $res['totalPages'];
    $shopMeta = seo_render_meta('folder', ['title' => t('shop_title')], ['description' => t('shop_sub')], $isShopFiltered ? 1 : $sPage);
    $shopCol = [];
    foreach ($res['products'] as $sp) {
        $shopCol[] = ['name' => bi($sp, 'title'), 'url' => absolute_url_for_lang('/shop/' . rawurlencode((string) $sp['slug']), $sLang)];
    }

    $shopCanon = '/shop';
    $shopRobots = '';
    $shopPrev = null;
    $shopNext = null;
    if ($isShopFiltered) {
        header('X-Robots-Tag: noindex, follow');
        $shopRobots = 'noindex, follow';
    } else {
        if ($sPage > 1) {
            $shopCanon = '/shop?page=' . $sPage;
            $shopPrev = absolute_url_for_lang('/shop' . ($sPage - 1 > 1 ? '?page=' . ($sPage - 1) : ''), $sLang);
        }
        if ($sPage < $sTotalPages) {
            $shopNext = absolute_url_for_lang('/shop?page=' . ($sPage + 1), $sLang);
        }
    }

    render_public('shop.php', [
        'categories'          => $categories,
        'activeCategoryTrail' => $activeTrail,
        'products'            => $res['products'],
        'filters'             => $filters,
        'page'                => $sPage,
        'totalPages'          => $sTotalPages,
        'totalItems'          => $res['total'],
        'wishProductMap'      => shop_user_wishlisted_product_ids($uid),
        'jsonLdSchemas'       => [shop_breadcrumb_schema($crumbs), seo_collection_schema(t('shop_title'), absolute_url_for_lang('/shop', $sLang), $shopMeta['description'], $shopCol)],
        'pageTitle'           => $shopMeta['title'],
        'fullTitleExact'      => true,
        'metaDescription'     => $shopMeta['description'],
        'canonicalPath'       => $shopCanon,
        'robotsDirective'     => $shopRobots,
        'prevUrl'             => $shopPrev,
        'nextUrl'             => $shopNext,
        'activeNav'           => 'shop',
    ]);
}

if ($route === '/shop/checkout') {
    header('X-Robots-Tag: noindex, nofollow');
    $user = current_user();
    $summary = shop_cart_summary($user ? (int) $user['id'] : null);
    if (empty($summary['items'])) {
        flash('warning', current_lang() === 'bn' ? 'চেকআউটের আগে কার্টে অন্তত একটি বই যোগ করুন।' : 'Please add at least one book to your cart before checkout.');
        redirect('/cart');
    }

    $error = null;
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        verify_csrf();
        $nonce = (string) ($_POST['checkout_nonce'] ?? '');
        if ($nonce === '' || !hash_equals(shop_checkout_nonce(), $nonce)) {
            $error = current_lang() === 'bn' ? 'ফর্মটি ইতিমধ্যে জমা দেওয়া হয়েছে অথবা মেয়াদ শেষ। আবার চেষ্টা করুন।' : 'Duplicate or expired checkout submission. Please refresh and try again.';
        } else {
            $res = shop_create_order($_POST, $user, true);
            if (!empty($res['ok'])) {
                if (!empty($res['redirect_url'])) {
                    header('Location: ' . $res['redirect_url']);
                    exit;
                }
                flash('success', current_lang() === 'bn' ? 'অর্ডার #' . $res['order_code'] . ' সফলভাবে জমা হয়েছে!' : 'Order #' . $res['order_code'] . ' placed successfully!');
                redirect('/shop/order-success/' . rawurlencode((string) $res['order_code']));
            }
            $error = (string) ($res['message'] ?? 'Checkout failed.');
        }
        $summary = shop_cart_summary($user ? (int) $user['id'] : null);
    }

    render_public('shop_checkout.php', [
        'summary'       => $summary,
        'user'          => $user,
        'error'         => $error,
        'pageTitle'     => current_lang() === 'bn' ? 'শপ চেকআউট' : 'Shop Checkout',
        'activeNav'     => 'shop',
        'robotsNoIndex' => true,
    ]);
}

if (preg_match('#^/shop/order-success/([^/]+)$#', $route, $m)) {
    header('X-Robots-Tag: noindex, nofollow');
    $code = rawurldecode($m[1]);
    $order = db_one('SELECT * FROM shop_orders WHERE order_code = ? LIMIT 1', [$code]);
    if (!$order) {
render_error_page(404);
    }
    $user = current_user();
    $canSee = current_admin() !== null
        || ($user && (int) ($order['user_id'] ?? 0) === (int) $user['id'])
        || (($_SESSION['last_shop_order_code'] ?? '') === $code);
    if (!$canSee) {
        redirect('/shop/track?code=' . rawurlencode($code));
    }
    $items = db_all('SELECT * FROM shop_order_items WHERE shop_order_id = ? ORDER BY id ASC', [(int) $order['id']]);
    render_public('shop_order_success.php', [
        'order'         => $order,
        'items'         => $items,
        'pageTitle'     => current_lang() === 'bn' ? 'অর্ডার সম্পন্ন' : 'Order Confirmed',
        'activeNav'     => 'shop',
        'robotsNoIndex' => true,
    ]);
}

if (preg_match('#^/shop/orders/([^/]+)/invoice$#', $route, $m)) {
    header('X-Robots-Tag: noindex, nofollow');
    $code = rawurldecode($m[1]);
    $order = db_one('SELECT * FROM shop_orders WHERE order_code = ? LIMIT 1', [$code]);
    if (!$order) {
render_error_page(404);
    }
    $user = current_user();
    $phoneCheck = preg_replace('/\D+/', '', (string) ($_GET['phone'] ?? ($_SESSION['shop_track_phone_' . $code] ?? ''))) ?? '';
    $orderDigits = preg_replace('/\D+/', '', (string) $order['customer_phone']) ?? '';
    $phoneMatches = ($phoneCheck !== '' && strlen($phoneCheck) >= 8 && $orderDigits !== '' && (str_ends_with($orderDigits, substr($phoneCheck, -10)) || str_ends_with($phoneCheck, substr($orderDigits, -10))));
    $authorized = current_admin() !== null
        || ($user && (int) ($order['user_id'] ?? 0) === (int) $user['id'])
        || (($_SESSION['last_shop_order_code'] ?? '') === $code)
        || $phoneMatches;
    if (!$authorized) {
        redirect('/shop/track?code=' . rawurlencode($code));
    }
    $items = db_all('SELECT * FROM shop_order_items WHERE shop_order_id = ? ORDER BY id ASC', [(int) $order['id']]);
    require APP_ROOT . '/pages/shop_invoice.php';
    exit;
}

if ($route === '/shop/track') {
    header('X-Robots-Tag: noindex, nofollow');
    $codeQuery = strtoupper(trim((string) ($_GET['code'] ?? '')));
    $phoneQuery = trim((string) ($_GET['phone'] ?? ''));
    $order = null;
    $items = [];
    $error = null;
    if ($codeQuery !== '') {
        $cand = db_one('SELECT * FROM shop_orders WHERE order_code = ? LIMIT 1', [$codeQuery]);
        if (!$cand) {
            $error = current_lang() === 'bn' ? 'এই অর্ডার কোডের কোনো তথ্য পাওয়া যায়নি।' : 'No order found matching that order code.';
        } else {
            $user = current_user();
            $isOwner = ($user && (int) ($cand['user_id'] ?? 0) === (int) $user['id'])
                || current_admin() !== null
                || (($_SESSION['last_shop_order_code'] ?? '') === $codeQuery);
            $phoneDigits = preg_replace('/\D+/', '', $phoneQuery) ?? '';
            $orderDigits = preg_replace('/\D+/', '', (string) $cand['customer_phone']) ?? '';
            $phoneOk = ($phoneDigits !== '' && strlen($phoneDigits) >= 8 && $orderDigits !== '' && (str_ends_with($orderDigits, substr($phoneDigits, -10)) || str_ends_with($phoneDigits, substr($orderDigits, -10))));
            if ($isOwner || $phoneOk) {
                $order = $cand;
                $_SESSION['shop_track_phone_' . $codeQuery] = $orderDigits;
                $items = db_all('SELECT * FROM shop_order_items WHERE shop_order_id = ? ORDER BY id ASC', [(int) $order['id']]);
            } elseif ($phoneQuery === '') {
                $error = current_lang() === 'bn' ? 'অর্ডার তথ্য দেখতে আপনার মোবাইল নম্বর লিখুন।' : 'Please enter the recipient phone number used on the order to view details.';
            } else {
                $error = current_lang() === 'bn' ? 'অর্ডার কোড ও মোবাইল নম্বর মিলছে না।' : 'Order code and phone number do not match.';
            }
        }
    }
    render_public('shop_track.php', [
        'order'         => $order,
        'items'         => $items,
        'codeQuery'     => $codeQuery,
        'phoneQuery'    => $phoneQuery,
        'error'         => $error,
        'pageTitle'     => t('nav_track_order'),
        'activeNav'     => 'shop',
        'robotsNoIndex' => true,
    ]);
}

if (preg_match('#^/shop/([^/]+)/review$#', $route, $m) && $_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $slug = rawurldecode($m[1]);
    $user = require_login('/shop/' . rawurlencode($slug));
    $prod = db_one('SELECT id, slug FROM products WHERE slug = ? AND is_active = 1 LIMIT 1', [$slug]);
    if (!$prod) {
render_error_page(404);
    }
    $pid = (int) $prod['id'];
    $uid = (int) $user['id'];
    $verifiedOrderId = shop_user_can_review_product($uid, $pid);
    if ($verifiedOrderId === null) {
        flash('danger', current_lang() === 'bn' ? 'শুধুমাত্র ডেলিভার্ড অর্ডারের ক্রেতারা রিভিউ দিতে পারবেন।' : 'Only verified buyers with a delivered order can submit a review.');
        redirect('/shop/' . rawurlencode((string) $prod['slug']) . '#reviews');
    }
    $rating = max(1, min(5, (int) ($_POST['rating'] ?? 5)));
    $comment = mb_substr(trim((string) ($_POST['comment'] ?? '')), 0, 1500);
    db_exec(
        "INSERT INTO product_reviews (user_id, product_id, shop_order_id, rating, comment, status)
         VALUES (?, ?, ?, ?, ?, 'approved')
         ON DUPLICATE KEY UPDATE shop_order_id = VALUES(shop_order_id), rating = VALUES(rating), comment = VALUES(comment), status = 'approved'",
        [$uid, $pid, $verifiedOrderId, $rating, $comment]
    );
    flash('success', current_lang() === 'bn' ? 'আপনার রিভিউ সংরক্ষিত হয়েছে!' : 'Thank you! Your verified review has been saved.');
    redirect('/shop/' . rawurlencode((string) $prod['slug']) . '#reviews');
}

if (preg_match('#^/shop/([^/]+)$#', $route, $m)) {
    $slug = rawurldecode($m[1]);
    $product = db_one('SELECT * FROM products WHERE slug = ? AND is_active = 1 LIMIT 1', [$slug]);
    if (!$product) {
        $hist = db_one("SELECT entity_id FROM slug_history WHERE entity_type = 'product' AND old_path = ? LIMIT 1", [$slug]);
        if ($hist) {
            $tProd = db_one('SELECT slug FROM products WHERE id = ? AND is_active = 1 LIMIT 1', [(int) $hist['entity_id']]);
            if ($tProd) {
                header('Location: ' . url('/shop/' . rawurlencode((string) $tProd['slug'])), true, 301);
                exit;
            }
        }
render_error_page(404);
    }
    $pid = (int) $product['id'];
    $galleryImages = shop_product_images($pid, $product['cover_image'] ?? null);
    $catTrail = !empty($product['category_id']) ? shop_category_breadcrumb((int) $product['category_id']) : [];
    $reviews = db_all(
        "SELECT pr.*, u.name AS user_name FROM product_reviews pr JOIN users u ON u.id = pr.user_id WHERE pr.product_id = ? AND pr.status = 'approved' ORDER BY pr.id DESC LIMIT 40",
        [$pid]
    );
    $ratingSummary = shop_product_rating_summary($pid);
    $relatedProducts = db_all(
        'SELECT * FROM products WHERE is_active = 1 AND id != ? ' . (!empty($product['category_id']) ? 'AND category_id = ? ' : '') . 'ORDER BY is_featured DESC, sales_count DESC, id DESC LIMIT 4',
        !empty($product['category_id']) ? [$pid, (int) $product['category_id']] : [$pid]
    );
    $user = current_user();
    $wishMap = shop_user_wishlisted_product_ids($user ? (int) $user['id'] : null);
    $verifiedOrderId = $user ? shop_user_can_review_product((int) $user['id'], $pid) : null;

    $lang = current_lang();
    $canonUrl = absolute_url_for_lang('/shop/' . rawurlencode((string) $product['slug']), $lang);
    $ogImg = seo_og_image_url('product', (string) $product['slug'], $product['cover_image'] ?? null);
    $effPrice = shop_product_effective_price($product);
    $siteName = site_setting('site_name', 'Bookexa');

    $crumbs = [
        ['name' => t('nav_home'), 'url' => absolute_url_for_lang('/', $lang)],
        ['name' => t('nav_shop'), 'url' => absolute_url_for_lang('/shop', $lang)],
    ];
    foreach ($catTrail as $tc) {
        $crumbs[] = [
            'name' => $lang === 'bn' ? (string) $tc['name_bn'] : (string) $tc['name_en'],
            'url'  => absolute_url_for_lang('/shop?cat=' . (int) $tc['id'], $lang),
        ];
    }
    $crumbs[] = ['name' => bi($product, 'title'), 'url' => $canonUrl];

    $prodMeta = seo_render_meta(
        'shop_product',
        ['title' => bi($product, 'title'), 'author' => bi($product, 'author'), 'price' => '৳' . number_format($effPrice, 0)],
        ['title' => bi($product, 'seo_title'), 'description' => bi($product, 'seo_description') ?: bi($product, 'description')],
        1
    );

    $prodSchema = [
        '@context' => 'https://schema.org',
        '@type'    => 'Product',
        'name'     => bi($product, 'title'),
        'url'      => $canonUrl,
        'image'    => array_values(array_map(static fn(array $im): string => absolute_asset_url((string) $im['image_path']), $galleryImages ?: [['image_path' => 'uploads/covers/cover-bangla.svg']])),
        'description' => $prodMeta['description'],
        'offers'   => [
            '@type'           => 'Offer',
            'priceCurrency'   => 'BDT',
            'price'           => number_format($effPrice, 2, '.', ''),
            'availability'    => ((int) $product['stock'] > 0) ? 'https://schema.org/InStock' : 'https://schema.org/OutOfStock',
            'priceValidUntil' => date('Y-m-d', strtotime('+1 year')),
            'url'             => $canonUrl,
            'seller'          => ['@type' => 'Organization', 'name' => $siteName],
        ],
    ];
    if (!empty($product['sku'])) {
        $prodSchema['sku'] = (string) $product['sku'];
    }
    if (!empty($product['isbn'])) {
        $prodSchema['gtin'] = (string) $product['isbn'];
    }
    if (!empty($product['publisher'])) {
        $prodSchema['brand'] = ['@type' => 'Brand', 'name' => (string) $product['publisher']];
    }
    if ((int) ($ratingSummary['count'] ?? 0) > 0 && !empty($reviews)) {
        $prodSchema['aggregateRating'] = [
            '@type'       => 'AggregateRating',
            'ratingValue' => number_format((float) $ratingSummary['avg'], 1, '.', ''),
            'reviewCount' => (int) $ratingSummary['count'],
            'bestRating'  => '5',
            'worstRating' => '1',
        ];
        $revItems = [];
        foreach (array_slice($reviews, 0, 5) as $rv) {
            $revItems[] = [
                '@type'         => 'Review',
                'author'        => ['@type' => 'Person', 'name' => (string) $rv['user_name']],
                'datePublished' => seo_format_lastmod($rv['created_at'] ?? null),
                'reviewBody'    => (string) ($rv['comment'] ?? ''),
                'reviewRating'  => ['@type' => 'Rating', 'ratingValue' => (int) $rv['rating'], 'bestRating' => 5, 'worstRating' => 1],
            ];
        }
        $prodSchema['review'] = $revItems;
    }

    render_public('shop_detail.php', [
        'product'         => $product,
        'galleryImages'   => $galleryImages,
        'catTrail'        => $catTrail,
        'reviews'         => $reviews,
        'ratingSummary'   => $ratingSummary,
        'relatedProducts' => $relatedProducts,
        'isWished'        => !empty($wishMap[$pid]),
        'verifiedOrderId' => $verifiedOrderId,
        'jsonLdSchemas'   => [shop_breadcrumb_schema($crumbs), $prodSchema],
        'ogImage'         => $ogImg,
        'ogImageWidth'    => function_exists('imagecreatetruecolor') ? 1200 : null,
        'ogImageHeight'   => function_exists('imagecreatetruecolor') ? 630 : null,
        'ogType'          => 'product',
        'pageTitle'       => $prodMeta['title'],
        'fullTitleExact'  => true,
        'metaDescription' => $prodMeta['description'],
        'canonicalPath'   => '/shop/' . rawurlencode((string) $product['slug']),
        'activeNav'       => 'shop',
    ]);
}

if ($route === '/guides' || preg_match('#^/guides/tag/([^/]+)$#', $route, $tm) || preg_match('#^/guides/([^/]+)$#', $route, $gm)) {
    $gLang = current_lang();
    $pubG  = content_guides_published_sql();

    // Check if /guides/{slug} is an individual guide article first (unless it matches a category)
    $catSlug = null;
    $tagSlug = null;
    $singleGuide = null;
    if (isset($tm[1])) {
        $tagSlug = rawurldecode($tm[1]);
    } elseif (isset($gm[1])) {
        $seg = rawurldecode($gm[1]);
        $isCat = (bool) db_one("SELECT 1 FROM guides WHERE category_slug = ? AND {$pubG} LIMIT 1", [$seg]);
        if ($isCat) {
            $catSlug = $seg;
        } else {
            $singleGuide = db_one("SELECT * FROM guides WHERE slug = ? AND {$pubG} LIMIT 1", [$seg]);
            if (!$singleGuide) {
                $hist = db_one("SELECT entity_id FROM slug_history WHERE entity_type = 'guide' AND old_path = ? LIMIT 1", [$seg]);
                if ($hist) {
                    $tGd = db_one("SELECT slug FROM guides WHERE id = ? AND {$pubG} LIMIT 1", [(int) $hist['entity_id']]);
                    if ($tGd) {
                        header('Location: ' . url('/guides/' . rawurlencode((string) $tGd['slug'])), true, 301);
                        exit;
                    }
                }
render_error_page(404);
            }
        }
    }

    if ($singleGuide !== null) {
        $relatedGuides = db_all("SELECT id, slug, title_bn, title_en, summary_bn, summary_en FROM guides WHERE {$pubG} AND id != ? ORDER BY id DESC LIMIT 4", [(int) $singleGuide['id']]);
        $authorProfile = !empty($singleGuide['author_id']) ? db_one('SELECT * FROM authors WHERE id = ? AND is_active = 1 LIMIT 1', [(int) $singleGuide['author_id']]) : null;
        $relBookIds = content_parse_id_list($singleGuide['related_item_ids'] ?? null);
        $relatedBooks = [];
        if ($relBookIds) {
            $ph = implode(',', array_fill(0, count($relBookIds), '?'));
            $relatedBooks = db_all("SELECT * FROM items WHERE id IN ({$ph}) AND is_active = 1 AND " . published_window_sql(), $relBookIds);
        } elseif (!empty($singleGuide['category_slug'])) {
            $lk = '%' . escape_like((string) $singleGuide['category_slug']) . '%';
            $relatedBooks = db_all('SELECT * FROM items WHERE is_active = 1 AND ' . published_window_sql() . " AND (title_en LIKE ? ESCAPE '\\\\' OR COALESCE(tags,'') LIKE ? ESCAPE '\\\\') ORDER BY is_featured DESC, id DESC LIMIT 4", [$lk, $lk]);
        }
        $relFolderIds = content_parse_id_list($singleGuide['related_folder_ids'] ?? null);
        $relatedFolders = [];
        if ($relFolderIds) {
            $phF = implode(',', array_fill(0, count($relFolderIds), '?'));
            $relatedFolders = db_all("SELECT * FROM folders WHERE id IN ({$phF}) AND is_active = 1", $relFolderIds);
        }

        $bodySan = content_sanitize_rich_html(bi($singleGuide, 'body') ?: bi($singleGuide, 'summary'));
        $tocData = content_extract_headings_and_toc($bodySan);

        $gdMeta = seo_render_meta(
            'guide',
            ['title' => bi($singleGuide, 'title')],
            ['title' => bi($singleGuide, 'seo_title'), 'description' => bi($singleGuide, 'seo_description') ?: bi($singleGuide, 'summary')],
            1
        );
        $canonAbs = absolute_url_for_lang('/guides/' . rawurlencode((string) $singleGuide['slug']), $gLang);
        $ogImg = seo_og_image_url('guide', (string) $singleGuide['slug'], $singleGuide['cover'] ?? null);
        $authorSchema = $authorProfile
            ? ['@type' => 'Person', 'name' => bi($authorProfile, 'name'), 'url' => absolute_url_for_lang('/authors/' . rawurlencode((string) $authorProfile['slug']), $gLang)]
            : ['@type' => 'Person', 'name' => (string) ($singleGuide['author_name'] ?: site_setting('site_name', 'Bookexa'))];
        $artSchema = [
            '@context'         => 'https://schema.org',
            '@type'            => 'Article',
            'headline'         => bi($singleGuide, 'title'),
            'description'      => $gdMeta['description'],
            'image'            => !empty($singleGuide['cover']) ? absolute_asset_url((string) $singleGuide['cover']) : $ogImg,
            'datePublished'    => seo_format_lastmod($singleGuide['publish_at'] ?? null, $singleGuide['created_at'] ?? null),
            'dateModified'     => seo_format_lastmod($singleGuide['updated_at'] ?? null, $singleGuide['created_at'] ?? null),
            'author'           => $authorSchema,
            'publisher'        => ['@type' => 'Organization', 'name' => site_setting('site_name', 'Bookexa')],
            'mainEntityOfPage' => ['@type' => 'WebPage', '@id' => $canonAbs],
            'speakable'        => ['@type' => 'SpeakableSpecification', 'cssSelector' => ['.bx-answer-box', 'h1']],
        ];
        $crumbs = [
            ['name' => t('nav_home'), 'url' => absolute_url_for_lang('/', $gLang)],
            ['name' => t('nav_guides'), 'url' => absolute_url_for_lang('/guides', $gLang)],
        ];
        if (!empty($singleGuide['category_slug']) && !empty($singleGuide['category_' . $gLang])) {
            $crumbs[] = ['name' => (string) $singleGuide['category_' . $gLang], 'url' => absolute_url_for_lang('/guides/' . rawurlencode((string) $singleGuide['category_slug']), $gLang)];
        }
        $crumbs[] = ['name' => bi($singleGuide, 'title'), 'url' => $canonAbs];
        $guideSchemas = [
            seo_breadcrumb_schema($crumbs),
            $artSchema,
            aeo_webpage_schema(bi($singleGuide, 'title'), $canonAbs, $gdMeta['description'], $gLang, seo_format_lastmod($singleGuide['updated_at'] ?? null, $singleGuide['created_at'] ?? null)),
        ];
        $gFaqsLd = content_parse_faqs_json($singleGuide['faqs_json'] ?? null);
        if ($gFaqsLd) {
            $faqMain = [];
            foreach ($gFaqsLd as $fq) {
                $qt = $gLang === 'bn' ? ($fq['q_bn'] ?: $fq['q_en']) : ($fq['q_en'] ?: $fq['q_bn']);
                $at = $gLang === 'bn' ? ($fq['a_bn'] ?: $fq['a_en']) : ($fq['a_en'] ?: $fq['a_bn']);
                if ($qt !== '' && $at !== '') {
                    $faqMain[] = ['@type' => 'Question', 'name' => $qt, 'acceptedAnswer' => ['@type' => 'Answer', 'text' => $at]];
                }
            }
            if ($faqMain) {
                $guideSchemas[] = ['@context' => 'https://schema.org', '@type' => 'FAQPage', 'mainEntity' => $faqMain];
            }
        }

        render_public('guide_detail.php', [
            'guide'            => $singleGuide,
            'authorProfile'    => $authorProfile,
            'renderedBodyHtml' => $tocData['html'],
            'toc'              => $tocData['toc'],
            'relatedGuides'    => $relatedGuides,
            'relatedBooks'     => $relatedBooks,
            'relatedFolders'   => $relatedFolders,
            'pageTitle'        => $gdMeta['title'],
            'fullTitleExact'   => true,
            'metaDescription'  => $gdMeta['description'],
            'preloadHeroImage' => !empty($singleGuide['cover']) ? absolute_asset_url((string) $singleGuide['cover']) : null,
            'ogType'           => 'article',
            'ogImage'          => $ogImg,
            'ogImageWidth'     => function_exists('imagecreatetruecolor') ? 1200 : null,
            'ogImageHeight'    => function_exists('imagecreatetruecolor') ? 630 : null,
            'canonicalPath'    => '/guides/' . rawurlencode((string) $singleGuide['slug']),
            'jsonLdSchemas'    => $guideSchemas,
            'activeNav'        => 'guides',
        ]);
    }

    // List view: All Guides, Category Archive, or Tag Archive
    $whereG = [$pubG];
    $paramsG = [];
    $headingLabel = null;
    if ($catSlug !== null) {
        $whereG[] = 'category_slug = ?';
        $paramsG[] = $catSlug;
        $catRow = db_one("SELECT category_bn, category_en FROM guides WHERE category_slug = ? AND {$pubG} LIMIT 1", [$catSlug]);
        $headingLabel = $catRow ? (string) ($gLang === 'bn' ? ($catRow['category_bn'] ?: $catRow['category_en']) : ($catRow['category_en'] ?: $catRow['category_bn'])) : ucfirst($catSlug);
    } elseif ($tagSlug !== null) {
        $whereG[] = "COALESCE(tags,'') LIKE ? ESCAPE '\\\\'";
        $paramsG[] = '%' . escape_like($tagSlug) . '%';
        $headingLabel = '#' . $tagSlug;
    }
    $whereGSql = ' WHERE ' . implode(' AND ', $whereG);
    $gPage = max(1, (int) ($_GET['page'] ?? 1));
    $perPage = 12;
    $total = (int) (db_one("SELECT COUNT(*) AS c FROM guides{$whereGSql}", $paramsG)['c'] ?? 0);
    $totalPages = max(1, (int) ceil($total / $perPage));
    $gPage = min($gPage, $totalPages);
    $offset = ($gPage - 1) * $perPage;
    $guides = db_all("SELECT * FROM guides{$whereGSql} ORDER BY COALESCE(publish_at, created_at) DESC, id DESC LIMIT {$perPage} OFFSET {$offset}", $paramsG);

    // Distinct categories & tags
    $catRows = db_all("SELECT DISTINCT category_slug AS slug, category_bn AS bn, category_en AS en FROM guides WHERE {$pubG} AND COALESCE(category_slug,'') <> '' ORDER BY category_en ASC");
    $tagRows = db_all("SELECT tags FROM guides WHERE {$pubG} AND COALESCE(tags,'') <> ''");
    $allTags = [];
    foreach ($tagRows as $tr) {
        foreach (content_parse_tags($tr['tags']) as $tg) {
            if (!in_array($tg, $allTags, true)) {
                $allTags[] = $tg;
            }
        }
    }
    $globalFaqs = db_all("SELECT * FROM editorial_faqs WHERE scope_type = 'global' AND is_active = 1 ORDER BY sort_order ASC, id ASC");

    $baseList = $catSlug ? ('/guides/' . rawurlencode($catSlug)) : ($tagSlug ? ('/guides/tag/' . rawurlencode($tagSlug)) : '/guides');
    $gMeta = seo_render_meta('guide', ['title' => $headingLabel ?: t('nav_guides')], [], $gPage);
    $gCol = [];
    foreach ($guides as $gd) {
        $gCol[] = ['name' => bi($gd, 'title'), 'url' => absolute_url_for_lang('/guides/' . rawurlencode((string) $gd['slug']), $gLang)];
    }
    $gCanon = $gPage > 1 ? ($baseList . '?page=' . $gPage) : $baseList;
    $gPrev = $gPage > 1 ? absolute_url_for_lang($baseList . ($gPage - 1 > 1 ? '?page=' . ($gPage - 1) : ''), $gLang) : null;
    $gNext = $gPage < $totalPages ? absolute_url_for_lang($baseList . '?page=' . ($gPage + 1), $gLang) : null;

    render_public('guides.php', [
        'guides'          => $guides,
        'categories'      => $catRows,
        'allTags'         => $allTags,
        'activeCategory'  => $catSlug,
        'activeTag'       => $tagSlug,
        'headingLabel'    => $headingLabel,
        'globalFaqs'      => $globalFaqs,
        'page'            => $gPage,
        'totalPages'      => $totalPages,
        'totalItems'      => $total,
        'pageTitle'       => $gMeta['title'],
        'fullTitleExact'  => true,
        'metaDescription' => $gMeta['description'],
        'canonicalPath'   => $gCanon,
        'prevUrl'         => $gPrev,
        'nextUrl'         => $gNext,
        'jsonLdSchemas'   => [
            seo_breadcrumb_schema([
                ['name' => t('nav_home'), 'url' => absolute_url_for_lang('/', $gLang)],
                ['name' => t('nav_guides'), 'url' => absolute_url_for_lang('/guides', $gLang)],
            ]),
            seo_collection_schema($headingLabel ?: t('nav_guides'), absolute_url_for_lang($baseList, $gLang), $gMeta['description'], $gCol),
            aeo_webpage_schema($gMeta['title'], absolute_url_for_lang($gCanon, $gLang), $gMeta['description'], $gLang),
        ],
        'activeNav'       => 'guides',
    ]);
}

if (preg_match('#^/guide/([^/]+)$#', $route, $m)) {
    header('Location: ' . url('/guides/' . rawurlencode(rawurldecode($m[1]))), true, 301);
    exit;
}

if ($route === '/authors') {
    seo_sync_authors_directory();
    $aLang = current_lang();
    $aPage = max(1, (int) ($_GET['page'] ?? 1));
    $perPage = 24;
    $total = (int) (db_one('SELECT COUNT(*) AS c FROM authors WHERE is_active = 1')['c'] ?? 0);
    $totalPages = max(1, (int) ceil($total / $perPage));
    $aPage = min($aPage, $totalPages);
    $offset = ($aPage - 1) * $perPage;
    $authors = db_all("SELECT * FROM authors WHERE is_active = 1 ORDER BY id DESC LIMIT {$perPage} OFFSET {$offset}");
    foreach ($authors as &$auRow) {
        $nBn = trim((string) $auRow['name_bn']);
        $nEn = trim((string) $auRow['name_en']);
        $auRow['books_count'] = (int) (db_one(
            'SELECT COUNT(*) AS c FROM items WHERE is_active = 1 AND (author_bn = ? OR author_en = ? OR author_bn = ? OR author_en = ?)',
            [$nBn, $nEn, $nEn, $nBn]
        )['c'] ?? 0);
    }
    unset($auRow);

    $aMeta = seo_render_meta('author', ['author' => t('nav_authors'), 'title' => t('nav_authors')], [], $aPage);
    $aCol = [];
    foreach ($authors as $au) {
        $aCol[] = ['name' => bi($au, 'name'), 'url' => absolute_url_for_lang('/authors/' . rawurlencode((string) $au['slug']), $aLang)];
    }
    $aCanon = $aPage > 1 ? ('/authors?page=' . $aPage) : '/authors';
    $aPrev = $aPage > 1 ? absolute_url_for_lang('/authors' . ($aPage - 1 > 1 ? '?page=' . ($aPage - 1) : ''), $aLang) : null;
    $aNext = $aPage < $totalPages ? absolute_url_for_lang('/authors?page=' . ($aPage + 1), $aLang) : null;

    render_public('authors.php', [
        'authors'         => $authors,
        'page'            => $aPage,
        'totalPages'      => $totalPages,
        'pageTitle'       => $aMeta['title'],
        'fullTitleExact'  => true,
        'metaDescription' => $aMeta['description'],
        'canonicalPath'   => $aCanon,
        'prevUrl'         => $aPrev,
        'nextUrl'         => $aNext,
        'jsonLdSchemas'   => [
            seo_breadcrumb_schema([
                ['name' => t('nav_home'), 'url' => absolute_url_for_lang('/', $aLang)],
                ['name' => t('nav_authors'), 'url' => absolute_url_for_lang('/authors', $aLang)],
            ]),
            seo_collection_schema(t('nav_authors'), absolute_url_for_lang('/authors', $aLang), $aMeta['description'], $aCol),
        ],
        'activeNav'       => 'authors',
    ]);
}

if (preg_match('#^/author/([^/]+)$#', $route, $m)) {
    header('Location: ' . url('/authors/' . rawurlencode(rawurldecode($m[1]))), true, 301);
    exit;
}

if (preg_match('#^/authors/([^/]+)$#', $route, $m)) {
    seo_sync_authors_directory();
    $slug = rawurldecode($m[1]);
    $author = db_one('SELECT * FROM authors WHERE slug = ? AND is_active = 1 LIMIT 1', [$slug]);
    if (!$author) {
        $hist = db_one("SELECT entity_id FROM slug_history WHERE entity_type = 'author' AND old_path = ? LIMIT 1", [$slug]);
        if ($hist) {
            $tAu = db_one('SELECT slug FROM authors WHERE id = ? AND is_active = 1 LIMIT 1', [(int) $hist['entity_id']]);
            if ($tAu) {
                header('Location: ' . url('/authors/' . rawurlencode((string) $tAu['slug'])), true, 301);
                exit;
            }
        }
render_error_page(404);
    }
    $nBn = trim((string) $author['name_bn']);
    $nEn = trim((string) $author['name_en']);
    $pub = published_window_sql();
    $aItems = db_all(
        "SELECT * FROM items WHERE is_active = 1 AND {$pub} AND (author_bn = ? OR author_en = ? OR author_bn = ? OR author_en = ?) ORDER BY id DESC LIMIT 48",
        [$nBn, $nEn, $nEn, $nBn]
    );
    $aProducts = site_setting('shop_enabled', '0') === '1'
        ? db_all("SELECT * FROM products WHERE is_active = 1 AND (author_bn = ? OR author_en = ? OR author_bn = ? OR author_en = ?) ORDER BY id DESC LIMIT 24", [$nBn, $nEn, $nEn, $nBn])
        : [];
    $aGuides = db_all(
        'SELECT id, slug, title_bn, title_en, summary_bn, summary_en FROM guides WHERE ' . content_guides_published_sql() . ' AND (author_id = ? OR author_name = ? OR author_name = ?) ORDER BY id DESC LIMIT 24',
        [(int) $author['id'], $nBn, $nEn]
    );
    $aLang = current_lang();
    $aName = bi($author, 'name');
    $auMeta = seo_render_meta(
        'author',
        ['author' => $aName, 'title' => $aName],
        ['title' => bi($author, 'seo_title'), 'description' => bi($author, 'seo_description') ?: bi($author, 'bio')],
        1
    );
    $canonAbs = absolute_url_for_lang('/authors/' . rawurlencode((string) $author['slug']), $aLang);
    $auCol = [];
    foreach ($aGuides as $ag) {
        $auCol[] = ['name' => bi($ag, 'title'), 'url' => absolute_url_for_lang('/guides/' . rawurlencode((string) $ag['slug']), $aLang)];
    }
    foreach ($aItems as $ai) {
        $auCol[] = ['name' => bi($ai, 'title'), 'url' => absolute_url_for_lang('/book/' . rawurlencode((string) $ai['slug']), $aLang)];
    }
    foreach ($aProducts as $ap) {
        $auCol[] = ['name' => bi($ap, 'title'), 'url' => absolute_url_for_lang('/shop/' . rawurlencode((string) $ap['slug']), $aLang)];
    }
    $ogImg = seo_og_image_url('author', (string) $author['slug'], $author['photo'] ?? null);
    $personNode = [
        '@type'       => 'Person',
        'name'        => $aName,
        'description' => $auMeta['description'],
        'url'         => $canonAbs,
    ];
    if (bi($author, 'expertise') !== '') {
        $personNode['jobTitle'] = bi($author, 'expertise');
    }
    if (!empty($author['photo'])) {
        $personNode['image'] = absolute_asset_url((string) $author['photo']);
    }
    $auSameAs = array_values(array_filter([
        sanitize_social_url((string) ($author['social_facebook'] ?? '')),
        sanitize_social_url((string) ($author['social_linkedin'] ?? '')),
        sanitize_social_url((string) ($author['social_website'] ?? '')),
    ]));
    if ($auSameAs) {
        $personNode['sameAs'] = $auSameAs;
    }

    render_public('author_detail.php', [
        'author'          => $author,
        'guides'          => $aGuides,
        'items'           => $aItems,
        'products'        => $aProducts,
        'pageTitle'       => $auMeta['title'],
        'fullTitleExact'  => true,
        'metaDescription' => $auMeta['description'],
        'ogImage'         => $ogImg,
        'ogImageWidth'    => function_exists('imagecreatetruecolor') ? 1200 : null,
        'ogImageHeight'   => function_exists('imagecreatetruecolor') ? 630 : null,
        'canonicalPath'   => '/authors/' . rawurlencode((string) $author['slug']),
        'jsonLdSchemas'   => [
            seo_breadcrumb_schema([
                ['name' => t('nav_home'), 'url' => absolute_url_for_lang('/', $aLang)],
                ['name' => t('nav_authors'), 'url' => absolute_url_for_lang('/authors', $aLang)],
                ['name' => $aName, 'url' => $canonAbs],
            ]),
            array_merge(['@context' => 'https://schema.org'], $personNode),
            ['@context' => 'https://schema.org', '@type' => 'ProfilePage', 'mainEntity' => $personNode],
            seo_collection_schema($aName, $canonAbs, $auMeta['description'], $auCol),
        ],
        'activeNav'       => 'authors',
    ]);
}

if (preg_match('#^/hub/([^/]+)$#', $route, $m)) {
    $hSlug = rawurldecode($m[1]);
    $hub = db_one('SELECT * FROM hubs WHERE slug = ? AND is_active = 1 LIMIT 1', [$hSlug]);
    if (!$hub) {
        $hist = db_one("SELECT entity_id FROM slug_history WHERE entity_type = 'hub' AND old_path = ? LIMIT 1", [$hSlug]);
        if ($hist) {
            $tHb = db_one('SELECT slug FROM hubs WHERE id = ? AND is_active = 1 LIMIT 1', [(int) $hist['entity_id']]);
            if ($tHb) {
                header('Location: ' . url('/hub/' . rawurlencode((string) $tHb['slug'])), true, 301);
                exit;
            }
        }
render_error_page(404);
    }
    $hLang = current_lang();
    $primaryGuide = !empty($hub['guide_id']) ? db_one('SELECT * FROM guides WHERE id = ? AND ' . content_guides_published_sql(), [(int) $hub['guide_id']]) : null;
    $bIds = content_parse_id_list($hub['featured_item_ids'] ?? null);
    $featuredBooks = $bIds
        ? db_all('SELECT * FROM items WHERE id IN (' . implode(',', array_fill(0, count($bIds), '?')) . ') AND is_active = 1 AND ' . published_window_sql(), $bIds)
        : db_all('SELECT * FROM items WHERE is_active = 1 AND ' . published_window_sql() . ' ORDER BY is_featured DESC, read_count DESC LIMIT 4');
    $fIds = content_parse_id_list($hub['featured_folder_ids'] ?? null);
    $featuredFolders = $fIds
        ? db_all('SELECT * FROM folders WHERE id IN (' . implode(',', array_fill(0, count($fIds), '?')) . ') AND is_active = 1', $fIds)
        : db_all('SELECT * FROM folders WHERE parent_id IS NULL AND is_active = 1 ORDER BY sort_order ASC LIMIT 4');
    $hubFaqs = content_parse_faqs_json($hub['faqs_json'] ?? null);
    if (!$hubFaqs) {
        foreach (db_all("SELECT * FROM editorial_faqs WHERE scope_type = 'global' AND is_active = 1 ORDER BY sort_order ASC LIMIT 4") as $gf) {
            $hubFaqs[] = ['q_bn' => $gf['question_bn'], 'a_bn' => $gf['answer_bn'], 'q_en' => $gf['question_en'], 'a_en' => $gf['answer_en']];
        }
    }
    $otherHubs = db_all('SELECT slug, title_bn, title_en FROM hubs WHERE is_active = 1 AND id != ? ORDER BY sort_order ASC LIMIT 6', [(int) $hub['id']]);
    $hMeta = seo_render_meta('guide', ['title' => bi($hub, 'title')], ['title' => bi($hub, 'seo_title'), 'description' => bi($hub, 'seo_description') ?: bi($hub, 'intro')], 1);
    $hCanon = absolute_url_for_lang('/hub/' . rawurlencode((string) $hub['slug']), $hLang);
    $hCol = [];
    foreach ($featuredBooks as $fb) {
        $hCol[] = ['name' => bi($fb, 'title'), 'url' => absolute_url_for_lang('/book/' . rawurlencode((string) $fb['slug']), $hLang)];
    }
    $hSchemas = [
        seo_breadcrumb_schema([
            ['name' => t('nav_home'), 'url' => absolute_url_for_lang('/', $hLang)],
            ['name' => t('nav_guides'), 'url' => absolute_url_for_lang('/guides', $hLang)],
            ['name' => bi($hub, 'title'), 'url' => $hCanon],
        ]),
        seo_collection_schema(bi($hub, 'title'), $hCanon, $hMeta['description'], $hCol),
        aeo_webpage_schema($hMeta['title'], $hCanon, $hMeta['description'], $hLang, seo_format_lastmod($hub['updated_at'] ?? null)),
    ];
    if ($hubFaqs) {
        $faqMain = [];
        foreach ($hubFaqs as $fq) {
            $qt = $hLang === 'bn' ? ($fq['q_bn'] ?: $fq['q_en']) : ($fq['q_en'] ?: $fq['q_bn']);
            $at = $hLang === 'bn' ? ($fq['a_bn'] ?: $fq['a_en']) : ($fq['a_en'] ?: $fq['a_bn']);
            if ($qt !== '' && $at !== '') {
                $faqMain[] = ['@type' => 'Question', 'name' => $qt, 'acceptedAnswer' => ['@type' => 'Answer', 'text' => $at]];
            }
        }
        if ($faqMain) {
            $hSchemas[] = ['@context' => 'https://schema.org', '@type' => 'FAQPage', 'mainEntity' => $faqMain];
        }
    }
    render_public('hub.php', [
        'hub'             => $hub,
        'primaryGuide'    => $primaryGuide,
        'featuredBooks'   => $featuredBooks,
        'featuredFolders' => $featuredFolders,
        'hubFaqs'         => $hubFaqs,
        'otherHubs'       => $otherHubs,
        'pageTitle'       => $hMeta['title'],
        'fullTitleExact'  => true,
        'metaDescription' => $hMeta['description'],
        'canonicalPath'   => '/hub/' . rawurlencode((string) $hub['slug']),
        'jsonLdSchemas'   => $hSchemas,
        'activeNav'       => 'guides',
    ]);
}

if ($route === '/search') {
    header('X-Robots-Tag: noindex, nofollow');
    $filters = browse_extract_filters($_GET);
    $q = $filters['q'];
    $matchedFolders = $q !== '' ? browse_search_folders($q, 12) : [];
    $res = browse_query_items(['mode' => 'search'], $filters);
    preload_items_card_meta($res['items']);
    $authors = browse_distinct_authors();
    $didYouMean = null;
    if ($q !== '' && empty($matchedFolders) && empty($res['items'])) {
        $didYouMean = find_did_you_mean($q);
        log_search_miss($q);
    } elseif ($q !== '') {
        log_search_query($q);
    }
    if ($q !== '') {
        growth_record_analytics_event('search', '/search', [
            'search_query'         => $q,
            'search_results_count' => (int) ($res['total'] ?? 0) + count($matchedFolders),
        ]);
    }
    $sMeta = seo_render_meta('search', ['title' => $q !== '' ? $q : t('search_title')], [], (int) $res['page']);
    render_public('search.php', [
        'q'               => $q,
        'filters'         => $filters,
        'authors'         => $authors,
        'matchedFolders'  => $matchedFolders,
        'matchedItems'    => $res['items'],
        'page'            => $res['page'],
        'totalPages'      => $res['totalPages'],
        'totalItems'      => $res['total'],
        'didYouMean'      => $didYouMean,
        'pageTitle'       => $sMeta['title'],
        'fullTitleExact'  => true,
        'metaDescription' => $sMeta['description'],
        'canonicalPath'   => '/search',
        'activeNav'       => 'search',
        'robotsNoIndex'   => true,
    ]);
}

if ($route === '/about') {
    $stMeta = seo_render_meta('static', ['title' => t('nav_about')]);
    $stLang = current_lang();
    $aboutDesc = $stMeta['description'];
    $aboutAbs = absolute_url_for_lang('/about', $stLang);
    render_public('about.php', [
        'pageTitle'       => $stMeta['title'],
        'fullTitleExact'  => true,
        'metaDescription' => $aboutDesc,
        'canonicalPath'   => '/about',
        'jsonLdSchemas'   => [
            seo_org_schema(),
            seo_breadcrumb_schema([
                ['name' => t('nav_home'), 'url' => absolute_url_for_lang('/', $stLang)],
                ['name' => t('nav_about'), 'url' => $aboutAbs],
            ]),
            aeo_webpage_schema($stMeta['title'], $aboutAbs, $aboutDesc, $stLang, site_setting('legal_last_reviewed', date('Y-m-d'))),
            [
                '@context'    => 'https://schema.org',
                '@type'       => 'DataFeed',
                'name'        => site_setting('site_name', 'Bookexa') . ' Public Book & Study Guide Feeds',
                'dataFeedElement' => [
                    ['@type' => 'DataFeedItem', 'item' => ['@type' => 'Dataset', 'name' => 'Public Book Catalog JSON', 'url' => BASE_URL . '/catalog.json']],
                    ['@type' => 'DataFeedItem', 'item' => ['@type' => 'Dataset', 'name' => 'Study Guides JSON Feed', 'url' => BASE_URL . '/guides.json']],
                ],
            ],
        ],
        'activeNav'       => 'about',
    ]);
}

if ($route === '/contact') {
    $stMeta = seo_render_meta('static', ['title' => t('nav_contact')]);
    $stLang = current_lang();
    render_public('contact.php', [
        'pageTitle'       => $stMeta['title'],
        'fullTitleExact'  => true,
        'metaDescription' => $stMeta['description'],
        'canonicalPath'   => '/contact',
        'jsonLdSchemas'   => [seo_breadcrumb_schema([
            ['name' => t('nav_home'), 'url' => absolute_url_for_lang('/', $stLang)],
            ['name' => t('nav_contact'), 'url' => absolute_url_for_lang('/contact', $stLang)],
        ])],
        'activeNav'       => 'contact',
    ]);
}

$legalRouteMap = [
    '/terms'              => ['key' => 'terms',         'setting' => 'legal_terms',         'title' => t('legal_terms')],
    '/privacy'            => ['key' => 'privacy',       'setting' => 'legal_privacy',       'title' => t('legal_privacy')],
    '/refund-policy'      => ['key' => 'refund',        'setting' => 'legal_refund',        'title' => t('legal_refund')],
    '/copyright'          => ['key' => 'copyright',     'setting' => 'legal_copyright',     'title' => t('legal_copyright')],
    '/editorial-policy'   => ['key' => 'editorial',     'setting' => 'legal_editorial',     'title' => t('legal_editorial')],
    '/how-we-review'      => ['key' => 'review_policy', 'setting' => 'legal_review_policy', 'title' => t('legal_review_policy')],
    '/corrections-policy' => ['key' => 'corrections',   'setting' => 'legal_corrections',   'title' => t('legal_corrections')],
];
if (isset($legalRouteMap[$route])) {
    $lMeta = $legalRouteMap[$route];
    $lLang = current_lang();
    $lSuffix = $lLang === 'bn' ? '_bn' : '_en';
    $stMeta = seo_render_meta('static', ['title' => $lMeta['title']]);
    render_public('legal.php', [
        'legalKey'        => $lMeta['key'],
        'legalTitle'      => $lMeta['title'],
        'legalHtml'       => site_setting($lMeta['setting'] . $lSuffix, ''),
        'pageTitle'       => $stMeta['title'],
        'fullTitleExact'  => true,
        'metaDescription' => $stMeta['description'],
        'canonicalPath'   => $route,
        'jsonLdSchemas'   => [
            seo_breadcrumb_schema([
                ['name' => t('nav_home'), 'url' => absolute_url_for_lang('/', $lLang)],
                ['name' => $lMeta['title'], 'url' => absolute_url_for_lang($route, $lLang)],
            ]),
            aeo_webpage_schema($stMeta['title'], absolute_url_for_lang($route, $lLang), $stMeta['description'], $lLang, site_setting('legal_last_reviewed', date('Y-m-d'))),
        ],
        'activeNav'       => '',
    ]);
}

if ($route === '/newsletter/confirm') {
    header('X-Robots-Tag: noindex, nofollow');
    $res = growth_newsletter_confirm((string) ($_GET['token'] ?? ''));
    $nLang = current_lang();
    if (!empty($res['ok'])) {
        flash('success', $nLang === 'bn' ? 'আপনার নিউজলেটার সাবস্ক্রিপশন নিশ্চিত হয়েছে! ধন্যবাদ।' : 'Your newsletter subscription is now confirmed!');
    } else {
        flash('danger', $nLang === 'bn' ? 'নিশ্চিতকরণ লিংকটি সঠিক নয় বা মেয়াদ শেষ হয়েছে।' : 'Invalid or expired confirmation token.');
    }
    redirect('/');
}

if ($route === '/newsletter/unsubscribe') {
    header('X-Robots-Tag: noindex, nofollow');
    $ok = growth_newsletter_unsubscribe((string) ($_GET['token'] ?? ''));
    $nLang = current_lang();
    flash($ok ? 'info' : 'warning', $ok
        ? ($nLang === 'bn' ? 'আপনাকে নিউজলেটার তালিকা থেকে সফলভাবে সরানো হয়েছে।' : 'You have been unsubscribed from the Bookexa newsletter.')
        : ($nLang === 'bn' ? 'আনসাবস্ক্রাইব লিংকটি পাওয়া যায়নি।' : 'Unsubscribe token not found.'));
    redirect('/');
}

render_error_page(404);

