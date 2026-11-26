<?php
declare(strict_types=1);

const BROWSE_PER_PAGE = 24;

function browse_extract_filters(array $source): array
{
    $sort = (string) ($source['sort'] ?? 'newest');
    if (!in_array($sort, ['newest', 'popular', 'price_asc', 'price_desc', 'title'], true)) {
        $sort = 'newest';
    }
    $priceType = (string) ($source['price_type'] ?? 'all');
    if (!in_array($priceType, ['all', 'free', 'paid'], true)) {
        $priceType = 'all';
    }
    return [
        'q'          => trim((string) ($source['q'] ?? '')),
        'sort'       => $sort,
        'price_type' => $priceType,
        'author'     => trim((string) ($source['author'] ?? '')),
        'has_sample' => (($source['has_sample'] ?? '') === '1'),
        'page'       => max(1, (int) ($source['page'] ?? 1)),
    ];
}

function browse_build_url(string $basePath, array $filters, array $overrides = []): string
{
    $merged = array_merge([
        'q'          => $filters['q'] ?? '',
        'sort'       => ($filters['sort'] ?? 'newest') === 'newest' ? '' : ($filters['sort'] ?? ''),
        'price_type' => ($filters['price_type'] ?? 'all') === 'all' ? '' : ($filters['price_type'] ?? ''),
        'author'     => $filters['author'] ?? '',
        'has_sample' => !empty($filters['has_sample']) ? '1' : '',
        'page'       => ((int) ($filters['page'] ?? 1) > 1) ? (string) $filters['page'] : '',
    ], $overrides);

    if (($merged['page'] ?? '') === '1') {
        $merged['page'] = '';
    }
    $clean = array_filter($merged, static fn($v): bool => (string) $v !== '');
    return url($basePath . ($clean ? ('?' . http_build_query($clean)) : ''));
}

function fulltext_boolean_query(string $raw): ?string
{
    $norm = function_exists('content_strip_zero_width') ? content_strip_zero_width($raw) : $raw;
    $clean = preg_replace('/[^\p{L}\p{N}\s]+/u', ' ', trim($norm)) ?? '';
    $words = array_values(array_filter(preg_split('/\s+/u', $clean) ?: [], fn(string $w): bool => mb_strlen($w) >= 3));
    if (!$words) {
        return null;
    }
    $tokens = array_map(fn(string $w): string => '+' . $w . '*', array_slice($words, 0, 6));
    return implode(' ', $tokens);
}

function highlight_search_text(string $text, string $query): string
{
    $escaped = e($text);
    $cleanQ = function_exists('content_strip_zero_width') ? content_strip_zero_width($query) : $query;
    $q = trim($cleanQ);
    if ($q === '' || $escaped === '') {
        return $escaped;
    }
    $terms = array_values(array_filter(array_unique(preg_split('/\s+/u', $q) ?: []), fn(string $t): bool => mb_strlen($t) >= 2));
    if (!$terms) {
        $terms = [$q];
    }
    $pattern = '/(' . implode('|', array_map(fn(string $t): string => preg_quote(e($t), '/'), $terms)) . ')/iu';
    return preg_replace($pattern, '<mark class="bx-hl">$1</mark>', $escaped) ?? $escaped;
}

/**
 * Shared paginated & sorted item query used by folder, search, library, and wishlist pages (24/page).
 */
function browse_query_items(array $opts, array $filters): array
{
    $perPage = BROWSE_PER_PAGE;
    $page    = max(1, (int) ($filters['page'] ?? 1));
    $where   = ['i.is_active = 1', published_window_sql('i')];
    $params  = [];
    $joins   = '';
    $selectExtra = '';

    $mode = (string) ($opts['mode'] ?? 'browse');
    if ($mode === 'folder') {
        $where[]  = 'i.folder_id = ?';
        $params[] = (int) $opts['folder_id'];
    } elseif ($mode === 'library') {
        $uid = (int) $opts['user_id'];
        $joins .= ' LEFT JOIN library lib ON lib.item_id = i.id AND lib.user_id = ? LEFT JOIN reading_progress rp ON rp.item_id = i.id AND rp.user_id = ?';
        array_push($params, $uid, $uid);
        $where[] = '(lib.id IS NOT NULL OR i.is_free = 1 OR i.price <= 0)';
        $selectExtra .= ', rp.last_page, rp.total_pages AS prog_total_pages, rp.time_spent_sec, rp.is_finished, lib.granted_at';
    } elseif ($mode === 'wishlist') {
        $uid = (int) $opts['user_id'];
        $joins .= ' JOIN wishlist w ON w.item_id = i.id AND w.user_id = ?';
        $params[] = $uid;
    }

    if (($filters['price_type'] ?? 'all') === 'free') {
        $where[] = '(i.is_free = 1 OR i.price <= 0)';
    } elseif (($filters['price_type'] ?? 'all') === 'paid') {
        $where[] = '(i.is_free = 0 AND i.price > 0)';
    }

    if (!empty($filters['author'])) {
        $where[] = '(i.author_en = ? OR i.author_bn = ?)';
        array_push($params, $filters['author'], $filters['author']);
    }

    if (!empty($filters['has_sample'])) {
        $where[] = '(i.sample_pages > 0 OR COALESCE(i.sample_drive_link, "") <> "")';
    }

    $q = trim((string) ($filters['q'] ?? ''));
    $qNorm = function_exists('content_strip_zero_width') ? trim(content_strip_zero_width($q)) : $q;
    $ftScoreSelect = '';
    $ft = $qNorm !== '' ? fulltext_boolean_query($qNorm) : null;
    if ($qNorm !== '') {
        $variants = function_exists('content_bangla_search_variants') ? content_bangla_search_variants($qNorm) : [$qNorm];
        $likeClauses = [];
        $likeParams  = [];
        foreach ($variants as $vq) {
            $lk = '%' . escape_like($vq) . '%';
            $likeClauses[] = "i.title_bn LIKE ? ESCAPE '\\\\' OR i.title_en LIKE ? ESCAPE '\\\\' OR i.author_bn LIKE ? ESCAPE '\\\\' OR i.author_en LIKE ? ESCAPE '\\\\' OR COALESCE(i.tags,'') LIKE ? ESCAPE '\\\\' OR i.description_bn LIKE ? ESCAPE '\\\\' OR i.description_en LIKE ? ESCAPE '\\\\'";
            array_push($likeParams, $lk, $lk, $lk, $lk, $lk, $lk, $lk);
        }
        $likeExpr = implode(' OR ', $likeClauses);
        if ($ft !== null) {
            $ftScoreSelect = ', MATCH(i.title_bn, i.title_en, i.author_bn, i.author_en, i.description_bn, i.description_en) AGAINST(? IN BOOLEAN MODE) AS rel_score';
            $where[] = "(MATCH(i.title_bn, i.title_en, i.author_bn, i.author_en, i.description_bn, i.description_en) AGAINST(? IN BOOLEAN MODE) OR {$likeExpr})";
            $params[] = $ft;
            array_push($params, ...$likeParams);
        } else {
            $where[] = "({$likeExpr})";
            array_push($params, ...$likeParams);
        }
    }

    $whereSql = ' WHERE ' . implode(' AND ', $where);
    $total = (int) (db_one("SELECT COUNT(DISTINCT i.id) AS c FROM items i{$joins}{$whereSql}", $params)['c'] ?? 0);
    $totalPages = max(1, (int) ceil($total / $perPage));
    if ($page > $totalPages) {
        $page = $totalPages;
    }
    $offset = ($page - 1) * $perPage;

    $langCol = current_lang() === 'bn' ? 'COALESCE(NULLIF(i.title_bn,""), i.title_en)' : 'COALESCE(NULLIF(i.title_en,""), i.title_bn)';
    $orderBy = match ($filters['sort'] ?? 'newest') {
        'popular'    => '(i.read_count + i.view_count + (SELECT COUNT(*) FROM reading_logs rl WHERE rl.item_id = i.id)) DESC, i.id DESC',
        'price_asc'  => '(CASE WHEN i.is_free = 1 THEN 0 ELSE i.price END) ASC, i.id DESC',
        'price_desc' => '(CASE WHEN i.is_free = 1 THEN 0 ELSE i.price END) DESC, i.id DESC',
        'title'      => "{$langCol} ASC, i.id DESC",
        default      => $mode === 'library'
            ? 'COALESCE(rp.updated_at, lib.granted_at, i.created_at) DESC'
            : ($mode === 'folder' ? 'i.sort_order ASC, i.id DESC' : ($ft !== null ? 'rel_score DESC, i.is_featured DESC, i.id DESC' : 'i.is_featured DESC, i.id DESC')),
    };

    $selectParams = $ft !== null ? array_merge([$ft], $params) : $params;
    $rows = db_all(
        "SELECT DISTINCT i.id, i.folder_id, i.slug, i.title_bn, i.title_en, i.author_bn, i.author_en,
                i.cover, i.price, i.original_price, i.is_free, i.sample_pages, i.total_pages, i.view_count, i.read_count
                {$selectExtra}{$ftScoreSelect}
         FROM items i{$joins}{$whereSql}
         ORDER BY {$orderBy}
         LIMIT {$perPage} OFFSET {$offset}",
        $selectParams
    );

    return [
        'items'      => $rows,
        'total'      => $total,
        'page'       => $page,
        'perPage'    => $perPage,
        'totalPages' => $totalPages,
        'hasMore'    => $page < $totalPages,
    ];
}

function browse_distinct_authors(?int $folderId = null): array
{
    $sql = 'SELECT DISTINCT COALESCE(NULLIF(author_en,""), author_bn) AS a FROM items WHERE is_active = 1 AND ' . published_window_sql() . ' AND (COALESCE(author_en,"") <> "" OR COALESCE(author_bn,"") <> "")';
    $params = [];
    if ($folderId !== null) {
        $sql .= ' AND folder_id = ?';
        $params[] = $folderId;
    }
    $sql .= ' ORDER BY a ASC LIMIT 60';
    return array_values(array_filter(array_column(db_all($sql, $params), 'a')));
}

function browse_search_folders(string $q, int $limit = 12): array
{
    $q = trim(function_exists('content_strip_zero_width') ? content_strip_zero_width($q) : $q);
    if ($q === '') {
        return [];
    }
    $variants = function_exists('content_bangla_search_variants') ? content_bangla_search_variants($q) : [$q];
    $likeClauses = [];
    $params = [];
    foreach ($variants as $vq) {
        $lk = '%' . escape_like($vq) . '%';
        $likeClauses[] = "f.name_bn LIKE ? ESCAPE '\\\\' OR f.name_en LIKE ? ESCAPE '\\\\' OR f.description_bn LIKE ? ESCAPE '\\\\' OR f.description_en LIKE ? ESCAPE '\\\\'";
        array_push($params, $lk, $lk, $lk, $lk);
    }
    $likeExpr = implode(' OR ', $likeClauses);
    $ft = fulltext_boolean_query($q);
    $pubF = published_window_sql('f');
    $sql = "SELECT f.*
            FROM folders f
            WHERE f.is_active = 1 AND {$pubF} AND ";
    if ($ft !== null) {
        $sql .= "(MATCH(f.name_bn, f.name_en, f.description_bn, f.description_en) AGAINST(? IN BOOLEAN MODE) OR {$likeExpr})
                 ORDER BY MATCH(f.name_bn, f.name_en, f.description_bn, f.description_en) AGAINST(? IN BOOLEAN MODE) DESC, f.sort_order ASC LIMIT " . (int) $limit;
        return db_all($sql, array_merge([$ft], $params, [$ft]));
    }
    $sql .= "({$likeExpr}) ORDER BY f.sort_order ASC LIMIT " . (int) $limit;
    return db_all($sql, $params);
}

function browse_search_suggestions(string $q, int $maxTotal = 6): array
{
    $q = trim($q);
    if (mb_strlen($q) < 1) {
        return ['folders' => [], 'items' => []];
    }
    $like = '%' . escape_like($q) . '%';
    $pub = published_window_sql();
    $items = db_all(
        "SELECT id, slug, title_bn, title_en, author_bn, author_en, is_free, price, cover
         FROM items
         WHERE is_active = 1 AND {$pub} AND (title_bn LIKE ? ESCAPE '\\\\' OR title_en LIKE ? ESCAPE '\\\\' OR author_bn LIKE ? ESCAPE '\\\\' OR author_en LIKE ? ESCAPE '\\\\')
         ORDER BY is_featured DESC, read_count DESC, id DESC LIMIT 4",
        [$like, $like, $like, $like]
    );
    $rem = max(1, $maxTotal - count($items));
    $folders = db_all(
        "SELECT id, slug, name_bn, name_en
         FROM folders
         WHERE is_active = 1 AND {$pub} AND (name_bn LIKE ? ESCAPE '\\\\' OR name_en LIKE ? ESCAPE '\\\\')
         ORDER BY sort_order ASC, id DESC LIMIT " . (int) $rem,
        [$like, $like]
    );
    $outItems = array_map(fn($r) => [
        'title' => bi($r, 'title'),
        'sub'   => bi($r, 'author'),
        'url'   => url('/book/' . rawurlencode($r['slug'])),
        'badge' => (int) $r['is_free'] || (float) $r['price'] <= 0 ? t('free_badge') : format_bdt((float) $r['price']),
    ], $items);
    $outFolders = array_map(fn($f) => [
        'title' => bi($f, 'name'),
        'url'   => folder_full_url((int) $f['id']),
    ], $folders);
    return ['folders' => $outFolders, 'items' => $outItems];
}

function find_did_you_mean(string $q): ?string
{
    $qLow = mb_strtolower(trim($q));
    if (mb_strlen($qLow) < 3) {
        return null;
    }
    $candidates = [];
    foreach (db_all('SELECT title_en, title_bn, author_en FROM items WHERE is_active = 1 LIMIT 200') as $r) {
        foreach ([$r['title_en'], $r['title_bn'], $r['author_en']] as $c) {
            $c = trim((string) $c);
            if ($c !== '') {
                $candidates[] = $c;
            }
        }
    }
    foreach (db_all('SELECT name_en, name_bn FROM folders WHERE is_active = 1 LIMIT 100') as $f) {
        foreach ([$f['name_en'], $f['name_bn']] as $c) {
            $c = trim((string) $c);
            if ($c !== '') {
                $candidates[] = $c;
            }
        }
    }

    $best = null;
    $bestScore = 0.0;
    foreach (array_unique($candidates) as $cand) {
        $candLow = mb_strtolower($cand);
        // Compare against full candidate AND individual words
        similar_text($qLow, $candLow, $pctFull);
        if ($pctFull > $bestScore) {
            $bestScore = $pctFull;
            $best = $cand;
        }
        foreach (preg_split('/\s+/u', $candLow) ?: [] as $w) {
            if (mb_strlen($w) < 3) {
                continue;
            }
            similar_text($qLow, $w, $pctWord);
            $levOk = strlen($qLow) <= 255 && strlen($w) <= 255 && levenshtein($qLow, $w) <= 2;
            if ($levOk || $pctWord >= 60) {
                $score = $levOk ? max($pctWord, 72.0) : $pctWord;
                if ($score > $bestScore) {
                    $bestScore = $score;
                    $best = $cand;
                }
            }
        }
    }
    return $bestScore >= 52.0 ? $best : null;
}

function log_search_miss(string $q): void
{
    $clean = mb_substr(trim($q), 0, 190);
    if (mb_strlen($clean) < 2) {
        return;
    }
    try {
        db_exec(
            'INSERT INTO search_misses (query_text, lang, ip_address, hits) VALUES (?, ?, ?, 1)
             ON DUPLICATE KEY UPDATE hits = hits + 1, lang = VALUES(lang), ip_address = VALUES(ip_address), last_searched_at = NOW()',
            [$clean, current_lang(), client_ip()]
        );
    } catch (Throwable $e) {
    }
}

function log_search_query(string $q): void
{
    $clean = mb_substr(trim($q), 0, 190);
    if (mb_strlen($clean) < 2) {
        return;
    }
    try {
        db_exec(
            'INSERT INTO search_queries (query_text, lang, hits) VALUES (?, ?, 1)
             ON DUPLICATE KEY UPDATE hits = hits + 1, lang = VALUES(lang), last_searched_at = NOW()',
            [$clean, current_lang()]
        );
    } catch (Throwable $e) {
    }
}

function render_filter_sort_bar(string $actionUrl, array $filters, array $authors, string $searchInputName = 'q'): string
{
    $lang = current_lang();
    ob_start(); ?>
    <form method="get" action="<?= e($actionUrl) ?>" class="bx-browse-bar">
        <div class="bx-browse-search">
            <input class="bx-input" type="search" name="<?= e($searchInputName) ?>" value="<?= e($filters['q'] ?? '') ?>"
                   placeholder="<?= e($lang === 'bn' ? 'শিরোনাম বা লেখক খুঁজুন...' : 'Filter or search books...') ?>" data-suggest-input autocomplete="off">
            <div class="bx-suggest-dropdown" hidden></div>
        </div>
        <select name="sort" class="bx-select bx-browse-ctl" aria-label="Sort">
            <option value="newest" <?= ($filters['sort'] ?? 'newest') === 'newest' ? 'selected' : '' ?>><?= $lang === 'bn' ? 'নতুন আগে' : 'Newest' ?></option>
            <option value="popular" <?= ($filters['sort'] ?? '') === 'popular' ? 'selected' : '' ?>><?= $lang === 'bn' ? 'জনপ্রিয়' : 'Popular (Most Read)' ?></option>
            <option value="price_asc" <?= ($filters['sort'] ?? '') === 'price_asc' ? 'selected' : '' ?>><?= $lang === 'bn' ? 'কম মূল্য আগে' : 'Price: Low to High' ?></option>
            <option value="price_desc" <?= ($filters['sort'] ?? '') === 'price_desc' ? 'selected' : '' ?>><?= $lang === 'bn' ? 'বেশি মূল্য আগে' : 'Price: High to Low' ?></option>
            <option value="title" <?= ($filters['sort'] ?? '') === 'title' ? 'selected' : '' ?>><?= $lang === 'bn' ? 'শিরোনাম (A-Z)' : 'Title (A-Z)' ?></option>
        </select>
        <select name="price_type" class="bx-select bx-browse-ctl" aria-label="Price filter">
            <option value="all" <?= ($filters['price_type'] ?? 'all') === 'all' ? 'selected' : '' ?>><?= $lang === 'bn' ? 'সব মূল্য' : 'All Prices' ?></option>
            <option value="free" <?= ($filters['price_type'] ?? '') === 'free' ? 'selected' : '' ?>><?= $lang === 'bn' ? 'শুধু ফ্রি' : 'Free Only' ?></option>
            <option value="paid" <?= ($filters['price_type'] ?? '') === 'paid' ? 'selected' : '' ?>><?= $lang === 'bn' ? 'পেইড' : 'Paid Only' ?></option>
        </select>
        <?php if (!empty($authors)): ?>
            <select name="author" class="bx-select bx-browse-ctl" aria-label="Author filter">
                <option value=""><?= $lang === 'bn' ? 'সব লেখক' : 'All Authors' ?></option>
                <?php foreach ($authors as $au): ?>
                    <option value="<?= e($au) ?>" <?= ($filters['author'] ?? '') === $au ? 'selected' : '' ?>><?= e($au) ?></option>
                <?php endforeach; ?>
            </select>
        <?php endif; ?>
        <label class="bx-browse-check">
            <input type="checkbox" name="has_sample" value="1" <?= !empty($filters['has_sample']) ? 'checked' : '' ?>>
            <span><?= $lang === 'bn' ? 'ফ্রি স্যাম্পল আছে' : 'Has Sample' ?></span>
        </label>
        <button type="submit" class="bx-btn bx-btn-primary bx-btn-sm"><?= icon('search') ?> <?= $lang === 'bn' ? 'প্রয়োগ' : 'Apply' ?></button>
        <?php if (($filters['q'] ?? '') !== '' || ($filters['sort'] ?? 'newest') !== 'newest' || ($filters['price_type'] ?? 'all') !== 'all' || ($filters['author'] ?? '') !== '' || !empty($filters['has_sample'])): ?>
            <a href="<?= e($actionUrl) ?>" class="bx-btn bx-btn-ghost bx-btn-sm"><?= $lang === 'bn' ? 'রিসেট' : 'Reset' ?></a>
        <?php endif; ?>
    </form>
    <?php
    return ob_get_clean();
}

function render_browse_pagination(string $basePath, array $filters, int $page, int $totalPages, int $totalRows, string $gridId): string
{
    if ($totalPages <= 1 && $totalRows <= BROWSE_PER_PAGE) {
        return '';
    }
    $lang = current_lang();
    ob_start(); ?>
    <div class="bx-loadmore-wrap">
        <?php if ($page < $totalPages): ?>
            <button type="button"
                    class="bx-btn bx-btn-outline"
                    data-load-more
                    data-target-grid="#<?= e($gridId) ?>"
                    data-next-url="<?= e(browse_build_url($basePath, $filters, ['page' => (string) ($page + 1)])) ?>"
                    data-next-page="<?= $page + 1 ?>"
                    data-total-pages="<?= $totalPages ?>">
                <?= $lang === 'bn' ? 'আরো দেখুন' : 'Load more' ?> (<?= $page ?> / <?= $totalPages ?>)
            </button>
        <?php endif; ?>
        <nav class="bx-pagination-links" aria-label="Pagination">
            <?php if ($page > 1): ?>
                <a href="<?= e(browse_build_url($basePath, $filters, ['page' => (string) ($page - 1)])) ?>" class="bx-btn bx-btn-ghost bx-btn-sm">« <?= $lang === 'bn' ? 'আগের' : 'Prev' ?></a>
            <?php endif; ?>
            <span class="bx-muted" style="font-size:0.88rem;">
                <?= $lang === 'bn' ? 'পৃষ্ঠা ' . num_localize($page) . ' / ' . num_localize($totalPages) : "Page {$page} of {$totalPages}" ?>
            </span>
            <?php if ($page < $totalPages): ?>
                <a href="<?= e(browse_build_url($basePath, $filters, ['page' => (string) ($page + 1)])) ?>" class="bx-btn bx-btn-ghost bx-btn-sm"><?= $lang === 'bn' ? 'পরের' : 'Next' ?> »</a>
            <?php endif; ?>
        </nav>
    </div>
    <?php
    return ob_get_clean();
}
