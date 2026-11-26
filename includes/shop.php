<?php
declare(strict_types=1);

/**
 * Bookexa Physical Book Shop Module Helpers
 * Handles categories, products, multi-image galleries, stock adjustments/logs,
 * persistent guest/user carts + login merge, delivery calculation, transactional
 * checkout with FOR UPDATE stock locks, order lifecycle, returns, and reviews.
 */

const SHOP_ORDER_STATUSES = ['pending', 'confirmed', 'packed', 'shipped', 'delivered', 'cancelled', 'returned'];

/**
 * All 64 districts of Bangladesh with Dhaka first for fast selection.
 */
function shop_bd_districts(): array
{
    static $list = null;
    if ($list !== null) {
        return $list;
    }
    $list = [
        'Dhaka'         => ['bn' => 'ঢাকা', 'en' => 'Dhaka'],
        'Gazipur'       => ['bn' => 'গাজীপুর', 'en' => 'Gazipur'],
        'Narayanganj'   => ['bn' => 'নারায়ণগঞ্জ', 'en' => 'Narayanganj'],
        'Narsingdi'     => ['bn' => 'নরসিংদী', 'en' => 'Narsingdi'],
        'Munshiganj'    => ['bn' => 'মুন্সিগঞ্জ', 'en' => 'Munshiganj'],
        'Manikganj'     => ['bn' => 'মানিকগঞ্জ', 'en' => 'Manikganj'],
        'Tangail'       => ['bn' => 'টাঙ্গাইল', 'en' => 'Tangail'],
        'Kishoreganj'   => ['bn' => 'কিশোরগঞ্জ', 'en' => 'Kishoreganj'],
        'Faridpur'      => ['bn' => 'ফরিদপুর', 'en' => 'Faridpur'],
        'Gopalganj'     => ['bn' => 'গোপালগঞ্জ', 'en' => 'Gopalganj'],
        'Madaripur'     => ['bn' => 'মাদারীপুর', 'en' => 'Madaripur'],
        'Rajbari'       => ['bn' => 'রাজবাড়ী', 'en' => 'Rajbari'],
        'Shariatpur'    => ['bn' => 'শরীয়তপুর', 'en' => 'Shariatpur'],
        'Chattogram'    => ['bn' => 'চট্টগ্রাম', 'en' => 'Chattogram'],
        'Cox\'s Bazar'  => ['bn' => 'কক্সবাজার', 'en' => 'Cox\'s Bazar'],
        'Cumilla'       => ['bn' => 'কুমিল্লা', 'en' => 'Cumilla'],
        'Brahmanbaria'  => ['bn' => 'ব্রাহ্মণবাড়িয়া', 'en' => 'Brahmanbaria'],
        'Chandpur'      => ['bn' => 'চাঁদপুর', 'en' => 'Chandpur'],
        'Feni'          => ['bn' => 'ফেনী', 'en' => 'Feni'],
        'Noakhali'      => ['bn' => 'নোয়াখালী', 'en' => 'Noakhali'],
        'Lakshmipur'    => ['bn' => 'লক্ষ্মীপুর', 'en' => 'Lakshmipur'],
        'Khagrachhari'  => ['bn' => 'খাগড়াছড়ি', 'en' => 'Khagrachhari'],
        'Rangamati'     => ['bn' => 'রাঙ্গামাটি', 'en' => 'Rangamati'],
        'Bandarban'     => ['bn' => 'বান্দরবান', 'en' => 'Bandarban'],
        'Rajshahi'      => ['bn' => 'রাজশাহী', 'en' => 'Rajshahi'],
        'Bogura'        => ['bn' => 'বগুড়া', 'en' => 'Bogura'],
        'Pabna'         => ['bn' => 'পাবনা', 'en' => 'Pabna'],
        'Sirajganj'     => ['bn' => 'সিরাজগঞ্জ', 'en' => 'Sirajganj'],
        'Natore'        => ['bn' => 'নাটোর', 'en' => 'Natore'],
        'Naogaon'       => ['bn' => 'নওগাঁ', 'en' => 'Naogaon'],
        'Chapainawabganj' => ['bn' => 'চাঁপাইনবাবগঞ্জ', 'en' => 'Chapainawabganj'],
        'Joypurhat'     => ['bn' => 'জয়পুরহাট', 'en' => 'Joypurhat'],
        'Sylhet'        => ['bn' => 'সিলেট', 'en' => 'Sylhet'],
        'Moulvibazar'   => ['bn' => 'মৌলভীবাজার', 'en' => 'Moulvibazar'],
        'Habiganj'      => ['bn' => 'হবিগঞ্জ', 'en' => 'Habiganj'],
        'Sunamganj'     => ['bn' => 'সুনামগঞ্জ', 'en' => 'Sunamganj'],
        'Khulna'        => ['bn' => 'খুলনা', 'en' => 'Khulna'],
        'Jashore'       => ['bn' => 'যশোর', 'en' => 'Jashore'],
        'Kushtia'       => ['bn' => 'কুষ্টিয়া', 'en' => 'Kushtia'],
        'Satkhira'      => ['bn' => 'সাতক্ষীরা', 'en' => 'Satkhira'],
        'Bagerhat'      => ['bn' => 'বাগেরহাট', 'en' => 'Bagerhat'],
        'Jhenaidah'     => ['bn' => 'ঝিনাইদহ', 'en' => 'Jhenaidah'],
        'Chuadanga'     => ['bn' => 'চুয়াডাঙ্গা', 'en' => 'Chuadanga'],
        'Magura'        => ['bn' => 'মাগুরা', 'en' => 'Magura'],
        'Meherpur'      => ['bn' => 'মেহেরপুর', 'en' => 'Meherpur'],
        'Narail'        => ['bn' => 'নড়াইল', 'en' => 'Narail'],
        'Barishal'      => ['bn' => 'বরিশাল', 'en' => 'Barishal'],
        'Patuakhali'    => ['bn' => 'পটুয়াখালী', 'en' => 'Patuakhali'],
        'Bhola'         => ['bn' => 'ভোলা', 'en' => 'Bhola'],
        'Pirojpur'      => ['bn' => 'পিরোজপুর', 'en' => 'Pirojpur'],
        'Barguna'       => ['bn' => 'বরগুনা', 'en' => 'Barguna'],
        'Jhalokati'     => ['bn' => 'ঝালকাঠি', 'en' => 'Jhalokati'],
        'Rangpur'       => ['bn' => 'রংপুর', 'en' => 'Rangpur'],
        'Dinajpur'      => ['bn' => 'দিনাজপুর', 'en' => 'Dinajpur'],
        'Gaibandha'     => ['bn' => 'গাইবান্ধা', 'en' => 'Gaibandha'],
        'Kurigram'      => ['bn' => 'কুড়িগ্রাম', 'en' => 'Kurigram'],
        'Lalmonirhat'   => ['bn' => 'লালমনিরহাট', 'en' => 'Lalmonirhat'],
        'Nilphamari'    => ['bn' => 'নীলফামারী', 'en' => 'Nilphamari'],
        'Panchagarh'    => ['bn' => 'পঞ্চগড়', 'en' => 'Panchagarh'],
        'Thakurgaon'    => ['bn' => 'ঠাকুরগাঁও', 'en' => 'Thakurgaon'],
        'Mymensingh'    => ['bn' => 'ময়মনসিংহ', 'en' => 'Mymensingh'],
        'Jamalpur'      => ['bn' => 'জামালপুর', 'en' => 'Jamalpur'],
        'Netrokona'     => ['bn' => 'নেত্রকোণা', 'en' => 'Netrokona'],
        'Sherpur'       => ['bn' => 'শেরপুর', 'en' => 'Sherpur'],
    ];
    return $list;
}

function shop_delivery_config(): array
{
    $overridesRaw = json_decode((string) site_setting('shop_delivery_district_json', '{}'), true);
    $overrides = [];
    if (is_array($overridesRaw)) {
        foreach ($overridesRaw as $dist => $fee) {
            $d = trim((string) $dist);
            if ($d !== '' && is_numeric($fee) && (float) $fee >= 0) {
                $overrides[$d] = round((float) $fee, 2);
            }
        }
    }
    return [
        'cod_enabled'         => site_setting('shop_cod_enabled', '1') === '1',
        'dhaka_fee'           => max(0.0, (float) site_setting('shop_delivery_dhaka', '60')),
        'outside_fee'         => max(0.0, (float) site_setting('shop_delivery_outside', '120')),
        'district_overrides'  => $overrides,
        'free_delivery_above' => max(0.0, (float) site_setting('shop_free_delivery_above', '1500')),
        'est_days_dhaka'      => trim(site_setting('shop_est_days_dhaka', '1-2 days')) ?: '1-2 days',
        'est_days_outside'    => trim(site_setting('shop_est_days_outside', '3-5 days')) ?: '3-5 days',
        'return_days'         => max(1, (int) site_setting('shop_return_days', '7')),
    ];
}

function shop_calculate_delivery(float $netSubtotal, string $area, string $district = 'Dhaka'): array
{
    $cfg = shop_delivery_config();
    $area = $area === 'outside_dhaka' ? 'outside_dhaka' : 'inside_dhaka';
    $baseFee = $area === 'inside_dhaka' ? $cfg['dhaka_fee'] : $cfg['outside_fee'];
    if ($district !== '' && isset($cfg['district_overrides'][$district])) {
        $baseFee = (float) $cfg['district_overrides'][$district];
    }
    $freeAbove = (float) $cfg['free_delivery_above'];
    $isFree = ($freeAbove > 0 && $netSubtotal >= $freeAbove);
    $fee = $isFree ? 0.0 : round($baseFee, 2);
    $estDays = $area === 'inside_dhaka' ? $cfg['est_days_dhaka'] : $cfg['est_days_outside'];
    return [
        'area'       => $area,
        'district'   => $district,
        'base_fee'   => round($baseFee, 2),
        'fee'        => $fee,
        'is_free'    => $isFree,
        'free_above' => $freeAbove,
        'est_days'   => $estDays,
    ];
}

function shop_product_effective_price(array $p): float
{
    $price = max(0.0, (float) ($p['price'] ?? 0));
    $disc  = isset($p['discount_price']) && $p['discount_price'] !== null && $p['discount_price'] !== '' ? (float) $p['discount_price'] : null;
    if ($disc !== null && $disc > 0 && $disc < $price) {
        return round($disc, 2);
    }
    return round($price, 2);
}

function shop_order_status_labels(string $status): array
{
    return match ($status) {
        'confirmed' => ['bn' => 'নিশ্চিতকৃত', 'en' => 'Confirmed', 'badge' => 'bx-badge-teal', 'pill' => 'st-approved'],
        'packed'    => ['bn' => 'প্যাকিং সম্পন্ন', 'en' => 'Packed', 'badge' => 'bx-badge-primary', 'pill' => 'st-pending'],
        'shipped'   => ['bn' => 'কুরিয়ারে পাঠানো হয়েছে', 'en' => 'Shipped', 'badge' => 'bx-badge-primary', 'pill' => 'st-pending'],
        'delivered' => ['bn' => 'ডেলিভারি সম্পন্ন', 'en' => 'Delivered', 'badge' => 'bx-badge-success', 'pill' => 'st-approved'],
        'cancelled' => ['bn' => 'বাতিলকৃত', 'en' => 'Cancelled', 'badge' => 'bx-badge-accent', 'pill' => 'st-blocked'],
        'returned'  => ['bn' => 'ফেরতকৃত', 'en' => 'Returned', 'badge' => 'bx-badge-accent', 'pill' => 'st-rejected'],
        default     => ['bn' => 'অপেক্ষমাণ', 'en' => 'Pending', 'badge' => 'bx-badge-gold', 'pill' => 'st-pending'],
    };
}

function shop_return_status_labels(string $status): array
{
    return match ($status) {
        'requested' => ['bn' => 'রিটার্ন অনুরোধকৃত', 'en' => 'Return Requested', 'badge' => 'bx-badge-gold', 'pill' => 'st-pending'],
        'approved'  => ['bn' => 'রিটার্ন অনুমোদিত', 'en' => 'Return Approved', 'badge' => 'bx-badge-success', 'pill' => 'st-approved'],
        'rejected'  => ['bn' => 'রিটার্ন বাতিল', 'en' => 'Return Rejected', 'badge' => 'bx-badge-accent', 'pill' => 'st-rejected'],
        default     => ['bn' => 'নেই', 'en' => 'None', 'badge' => 'bx-badge', 'pill' => ''],
    };
}

function shop_categories_flat_tree(bool $activeOnly = false): array
{
    $where = $activeOnly ? 'WHERE is_active = 1' : '';
    $all = db_all("SELECT * FROM shop_categories {$where} ORDER BY sort_order ASC, id ASC");
    $byParent = [];
    foreach ($all as $c) {
        $pid = (int) ($c['parent_id'] ?? 0);
        $byParent[$pid][] = $c;
    }
    $flat = [];
    $walk = static function (int $pid, int $depth, string $pathLabel) use (&$walk, &$flat, &$byParent): void {
        foreach ($byParent[$pid] ?? [] as $row) {
            $row['_depth'] = $depth;
            $label = current_lang() === 'bn' ? (string) $row['name_bn'] : (string) $row['name_en'];
            $row['_path'] = $pathLabel !== '' ? ($pathLabel . ' / ' . $label) : $label;
            $flat[] = $row;
            $walk((int) $row['id'], $depth + 1, $row['_path']);
        }
    };
    $walk(0, 0, '');
    return $flat;
}

function shop_category_descendant_ids(int $categoryId): array
{
    $all = db_all('SELECT id, parent_id FROM shop_categories WHERE is_active = 1');
    $children = [];
    foreach ($all as $row) {
        $children[(int) ($row['parent_id'] ?? 0)][] = (int) $row['id'];
    }
    $ids = [$categoryId];
    $queue = [$categoryId];
    while ($queue) {
        $cur = array_shift($queue);
        foreach ($children[$cur] ?? [] as $ch) {
            if (!in_array($ch, $ids, true)) {
                $ids[] = $ch;
                $queue[] = $ch;
            }
        }
    }
    return $ids;
}

function shop_category_breadcrumb(int $categoryId): array
{
    $trail = [];
    $guard = 0;
    $currId = $categoryId;
    while ($currId > 0 && $guard < 12) {
        $guard++;
        $cat = db_one('SELECT id, parent_id, slug, name_bn, name_en FROM shop_categories WHERE id = ? LIMIT 1', [$currId]);
        if (!$cat) {
            break;
        }
        array_unshift($trail, $cat);
        $currId = (int) ($cat['parent_id'] ?? 0);
    }
    return $trail;
}

function shop_breadcrumb_schema(array $crumbs): array
{
    $items = [];
    foreach (array_values($crumbs) as $idx => $c) {
        $items[] = [
            '@type'    => 'ListItem',
            'position' => $idx + 1,
            'name'     => (string) ($c['name'] ?? ''),
            'item'     => (string) ($c['url'] ?? ''),
        ];
    }
    return ['@context' => 'https://schema.org', '@type' => 'BreadcrumbList', 'itemListElement' => $items];
}

function shop_product_images(int $productId, ?string $fallbackCover = null): array
{
    $imgs = db_all(
        'SELECT * FROM product_images WHERE product_id = ? ORDER BY is_cover DESC, sort_order ASC, id ASC',
        [$productId]
    );
    if (!$imgs && $fallbackCover) {
        $imgs[] = ['id' => 0, 'product_id' => $productId, 'image_path' => $fallbackCover, 'sort_order' => 0, 'is_cover' => 1];
    }
    return $imgs;
}

function shop_product_rating_summary(int $productId): array
{
    $row = db_one(
        "SELECT COUNT(*) AS cnt, COALESCE(AVG(rating), 0) AS avg_rating
         FROM product_reviews WHERE product_id = ? AND status = 'approved'",
        [$productId]
    );
    return [
        'count' => (int) ($row['cnt'] ?? 0),
        'avg'   => round((float) ($row['avg_rating'] ?? 0), 1),
    ];
}

function shop_user_can_review_product(int $userId, int $productId): ?int
{
    if ($userId <= 0 || $productId <= 0) {
        return null;
    }
    $row = db_one(
        "SELECT so.id
         FROM shop_orders so
         JOIN shop_order_items soi ON soi.shop_order_id = so.id
         WHERE so.user_id = ? AND so.status = 'delivered' AND soi.product_id = ?
         ORDER BY so.id DESC LIMIT 1",
        [$userId, $productId]
    );
    return $row ? (int) $row['id'] : null;
}

function shop_user_wishlisted_product_ids(?int $userId): array
{
    if (!$userId || $userId <= 0) {
        return [];
    }
    $rows = db_all('SELECT product_id FROM product_wishlist WHERE user_id = ?', [$userId]);
    $map = [];
    foreach ($rows as $r) {
        $map[(int) $r['product_id']] = true;
    }
    return $map;
}

/**
 * Atomically adjust physical product stock with log entry.
 * Works inside or outside an active transaction.
 */
function shop_adjust_stock(int $productId, int $delta, string $reason, ?int $adminId = null, ?int $refOrderId = null): array
{
    $ownTx = !db()->inTransaction();
    if ($ownTx) {
        db()->beginTransaction();
    }
    try {
        $p = db_one('SELECT id, stock FROM products WHERE id = ? LIMIT 1 FOR UPDATE', [$productId]);
        if (!$p) {
            if ($ownTx) {
                db()->rollBack();
            }
            return ['ok' => false, 'message' => 'Product not found.'];
        }
        $before = (int) $p['stock'];
        $after = $before + $delta;
        if ($after < 0) {
            if ($ownTx) {
                db()->rollBack();
            }
            return ['ok' => false, 'message' => 'Insufficient stock available.'];
        }
        db_exec('UPDATE products SET stock = ? WHERE id = ?', [$after, $productId]);
        db_exec(
            'INSERT INTO product_stock_logs (product_id, admin_id, change_qty, stock_before, stock_after, reason, ref_order_id) VALUES (?, ?, ?, ?, ?, ?, ?)',
            [$productId, $adminId, $delta, $before, $after, mb_substr($reason, 0, 190), $refOrderId]
        );
        if ($ownTx) {
            db()->commit();
        }
        return ['ok' => true, 'before' => $before, 'after' => $after];
    } catch (Throwable $e) {
        if ($ownTx && db()->inTransaction()) {
            db()->rollBack();
        }
        throw $e;
    }
}

function shop_low_stock_products(int $limit = 50): array
{
    return db_all(
        'SELECT id, slug, sku, title_bn, title_en, stock, low_stock_threshold
         FROM products
         WHERE is_active = 1 AND stock <= low_stock_threshold
         ORDER BY stock ASC, id ASC LIMIT ' . max(1, (int) $limit)
    );
}

/**
 * Query storefront products with category descendant filter, FULLTEXT/LIKE search,
 * sort, and pagination (24 per page).
 */
function shop_query_products(array $params = []): array
{
    $q        = trim((string) ($params['q'] ?? ''));
    $catId    = (int) ($params['category_id'] ?? 0);
    $sort     = (string) ($params['sort'] ?? 'newest');
    $inStock  = !empty($params['in_stock']);
    $featured = !empty($params['featured']);
    $page     = max(1, (int) ($params['page'] ?? 1));
    $perPage  = max(1, min(48, (int) ($params['per_page'] ?? 24)));

    $where = ['p.is_active = 1'];
    $bind  = [];

    if ($catId > 0) {
        $catIds = shop_category_descendant_ids($catId);
        $ph = implode(',', array_fill(0, count($catIds), '?'));
        $where[] = "p.category_id IN ({$ph})";
        foreach ($catIds as $cid) {
            $bind[] = $cid;
        }
    }
    if ($inStock) {
        $where[] = 'p.stock > 0';
    }
    if ($featured) {
        $where[] = 'p.is_featured = 1';
    }

    $relevanceExpr = '0';
    if ($q !== '') {
        $useFulltext = browse_table_has_fulltext('products', 'ft_products_search') && mb_strlen($q) >= 3;
        $like = '%' . escape_like($q) . '%';
        if ($useFulltext) {
            $where[] = "(MATCH(p.title_bn, p.title_en, p.author_bn, p.author_en, p.publisher, p.isbn, p.description_bn, p.description_en) AGAINST (? IN NATURAL LANGUAGE MODE) OR p.title_bn LIKE ? OR p.title_en LIKE ? OR p.author_bn LIKE ? OR p.author_en LIKE ? OR p.sku LIKE ? OR p.isbn LIKE ?)";
            $relevanceExpr = 'MATCH(p.title_bn, p.title_en, p.author_bn, p.author_en, p.publisher, p.isbn, p.description_bn, p.description_en) AGAINST (? IN NATURAL LANGUAGE MODE)';
            array_push($bind, $q, $like, $like, $like, $like, $like, $like);
        } else {
            $where[] = '(p.title_bn LIKE ? OR p.title_en LIKE ? OR p.author_bn LIKE ? OR p.author_en LIKE ? OR p.publisher LIKE ? OR p.sku LIKE ? OR p.isbn LIKE ?)';
            array_push($bind, $like, $like, $like, $like, $like, $like, $like);
        }
    }

    $whereSql = implode(' AND ', $where);
    $orderSql = match ($sort) {
        'popular'    => 'p.sales_count DESC, p.id DESC',
        'price_asc'  => 'COALESCE(NULLIF(p.discount_price, 0), p.price) ASC, p.id DESC',
        'price_desc' => 'COALESCE(NULLIF(p.discount_price, 0), p.price) DESC, p.id DESC',
        'title'      => (current_lang() === 'bn' ? 'p.title_bn ASC' : 'p.title_en ASC'),
        default      => 'p.is_featured DESC, p.id DESC',
    };
    if ($q !== '' && $sort === 'newest') {
        $orderSql = 'rel_score DESC, p.is_featured DESC, p.id DESC';
    }

    $total = (int) (db_one("SELECT COUNT(*) AS c FROM products p WHERE {$whereSql}", $bind)['c'] ?? 0);
    $totalPages = max(1, (int) ceil($total / $perPage));
    $page = min($page, $totalPages);
    $offset = ($page - 1) * $perPage;

    $selectBind = $bind;
    if ($relevanceExpr !== '0') {
        array_unshift($selectBind, $q);
    }

    $rows = db_all(
        "SELECT p.*, {$relevanceExpr} AS rel_score,
                sc.name_bn AS cat_name_bn, sc.name_en AS cat_name_en, sc.slug AS cat_slug,
                COALESCE(pr.rating_avg, 0) AS rating_avg,
                COALESCE(pr.rating_count, 0) AS rating_count
         FROM products p
         LEFT JOIN shop_categories sc ON sc.id = p.category_id
         LEFT JOIN (
             SELECT product_id, AVG(rating) AS rating_avg, COUNT(*) AS rating_count
             FROM product_reviews
             WHERE status = 'approved'
             GROUP BY product_id
         ) pr ON pr.product_id = p.id
         WHERE {$whereSql}
         ORDER BY {$orderSql}
         LIMIT {$perPage} OFFSET {$offset}",
        $selectBind
    );

    return [
        'products'   => $rows,
        'total'      => $total,
        'page'       => $page,
        'perPage'    => $perPage,
        'totalPages' => $totalPages,
    ];
}

// ============================================================================
// Persistent Cart (Guest Token Cookie + Logged-in User Auto-Merge)
// ============================================================================

function shop_set_guest_cart_cookie(string $token, int $ttl = 2592000): void
{
    $_SESSION['shop_cart_token'] = $token;
    if (headers_sent()) {
        return;
    }
    $isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (($_SERVER['SERVER_PORT'] ?? '') === '443')
        || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
    setcookie('bx_cart_tok', $token, [
        'expires'  => $ttl > 0 ? (time() + $ttl) : (time() - 3600),
        'path'     => BASE_PATH !== '' ? BASE_PATH : '/',
        'secure'   => $isHttps,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    if ($ttl > 0) {
        $_COOKIE['bx_cart_tok'] = $token;
    } else {
        unset($_COOKIE['bx_cart_tok'], $_SESSION['shop_cart_token']);
    }
}

function shop_current_guest_token(): ?string
{
    $tok = (string) ($_SESSION['shop_cart_token'] ?? $_COOKIE['bx_cart_tok'] ?? '');
    if (preg_match('/^[a-f0-9]{64}$/', $tok)) {
        return $tok;
    }
    return null;
}

function shop_resolve_cart(bool $createIfMissing = false, ?int $forceUserId = null): ?array
{
    $uid = $forceUserId ?? (function_exists('current_user') && current_user() ? (int) current_user()['id'] : 0);
    if ($uid > 0) {
        $row = db_one('SELECT * FROM shop_carts WHERE user_id = ? LIMIT 1', [$uid]);
        if ($row || !$createIfMissing) {
            return $row;
        }
        db_exec('INSERT INTO shop_carts (user_id) VALUES (?)', [$uid]);
        return db_one('SELECT * FROM shop_carts WHERE user_id = ? LIMIT 1', [$uid]);
    }

    $tok = shop_current_guest_token();
    if ($tok !== null) {
        $row = db_one('SELECT * FROM shop_carts WHERE cart_token = ? LIMIT 1', [$tok]);
        if ($row) {
            return $row;
        }
    }
    if (!$createIfMissing) {
        return null;
    }
    $newTok = bin2hex(random_bytes(32));
    db_exec('INSERT INTO shop_carts (cart_token) VALUES (?)', [$newTok]);
    shop_set_guest_cart_cookie($newTok);
    return db_one('SELECT * FROM shop_carts WHERE cart_token = ? LIMIT 1', [$newTok]);
}

function shop_merge_guest_cart(int $userId): void
{
    if ($userId <= 0) {
        return;
    }
    $tok = shop_current_guest_token();
    if ($tok === null) {
        return;
    }
    $guestCart = db_one('SELECT * FROM shop_carts WHERE cart_token = ? LIMIT 1', [$tok]);
    if (!$guestCart) {
        shop_set_guest_cart_cookie('', -1);
        return;
    }

    $guestItems = db_all(
        'SELECT sci.product_id, sci.quantity, p.stock, p.is_active
         FROM shop_cart_items sci
         JOIN products p ON p.id = sci.product_id
         WHERE sci.cart_id = ?',
        [(int) $guestCart['id']]
    );

    if ($guestItems) {
        $userCart = shop_resolve_cart(true, $userId);
        if ($userCart) {
            $ucId = (int) $userCart['id'];
            foreach ($guestItems as $gi) {
                if ((int) $gi['is_active'] !== 1 || (int) $gi['stock'] <= 0) {
                    continue;
                }
                $pid = (int) $gi['product_id'];
                $addQty = (int) $gi['quantity'];
                $maxQty = min(99, (int) $gi['stock']);
                $existing = db_one('SELECT id, quantity FROM shop_cart_items WHERE cart_id = ? AND product_id = ? LIMIT 1', [$ucId, $pid]);
                if ($existing) {
                    $newQty = min($maxQty, (int) $existing['quantity'] + $addQty);
                    db_exec('UPDATE shop_cart_items SET quantity = ? WHERE id = ?', [$newQty, (int) $existing['id']]);
                } else {
                    db_exec('INSERT INTO shop_cart_items (cart_id, product_id, quantity) VALUES (?, ?, ?)', [$ucId, $pid, min($maxQty, $addQty)]);
                }
            }
            if (empty($userCart['coupon_code']) && !empty($guestCart['coupon_code'])) {
                db_exec('UPDATE shop_carts SET coupon_code = ? WHERE id = ?', [$guestCart['coupon_code'], $ucId]);
            }
            if (!empty($guestCart['district']) && $userCart['district'] === 'Dhaka' && $guestCart['district'] !== 'Dhaka') {
                db_exec(
                    'UPDATE shop_carts SET delivery_area = ?, district = ? WHERE id = ?',
                    [$guestCart['delivery_area'], $guestCart['district'], $ucId]
                );
            }
        }
    }

    db_exec('DELETE FROM shop_carts WHERE id = ?', [(int) $guestCart['id']]);
    shop_set_guest_cart_cookie('', -1);
}

function shop_validate_coupon_for_cart(string $codeRaw, ?int $userId, float $subtotal): array
{
    $code = strtoupper(trim($codeRaw));
    if ($code === '') {
        return ['ok' => false, 'message' => current_lang() === 'bn' ? 'কুপন কোড লিখুন।' : 'Enter a coupon code.'];
    }
    $c = db_one('SELECT * FROM coupons WHERE code = ? AND is_active = 1 LIMIT 1', [$code]);
    if (!$c) {
        return ['ok' => false, 'message' => current_lang() === 'bn' ? 'কুপন কোডটি সঠিক নয় বা নিষ্ক্রিয়।' : 'Invalid or inactive coupon code.'];
    }
    if (!empty($c['expires_at']) && strtotime((string) $c['expires_at']) < time()) {
        return ['ok' => false, 'message' => current_lang() === 'bn' ? 'কুপনটির মেয়াদ শেষ হয়ে গেছে।' : 'This coupon has expired.'];
    }
    if ($c['usage_limit'] !== null && (int) $c['used_count'] >= (int) $c['usage_limit']) {
        return ['ok' => false, 'message' => current_lang() === 'bn' ? 'কুপন ব্যবহারের সীমা শেষ।' : 'Coupon usage limit reached.'];
    }
    if ($subtotal < (float) $c['min_amount']) {
        $minFmt = num_localize(number_format((float) $c['min_amount'], 0));
        return [
            'ok'      => false,
            'message' => current_lang() === 'bn'
                ? "এই কুপনের জন্য ন্যূনতম ৳{$minFmt} টাকার বই লাগবে।"
                : 'Minimum cart subtotal ৳' . number_format((float) $c['min_amount'], 0) . ' required.',
        ];
    }
    if ((int) ($c['applies_to_all'] ?? 1) !== 1) {
        return [
            'ok'      => false,
            'message' => current_lang() === 'bn'
                ? 'এই কুপনটি শুধুমাত্র নির্দিষ্ট ডিজিটাল পিডিএফের জন্য প্রযোজ্য।'
                : 'This coupon is restricted to specific digital PDFs only.',
        ];
    }
    if ($userId && $userId > 0) {
        $perUser = max(1, (int) ($c['per_user_limit'] ?? 1));
        $uDigital = (int) (db_one(
            "SELECT COUNT(*) AS cnt FROM orders WHERE user_id = ? AND coupon_id = ? AND status NOT IN ('rejected', 'cancelled', 'expired')",
            [$userId, (int) $c['id']]
        )['cnt'] ?? 0);
        $uShop = (int) (db_one(
            "SELECT COUNT(*) AS cnt FROM shop_orders WHERE user_id = ? AND coupon_id = ? AND status NOT IN ('cancelled', 'returned')",
            [$userId, (int) $c['id']]
        )['cnt'] ?? 0);
        if (($uDigital + $uShop) >= $perUser) {
            return ['ok' => false, 'message' => current_lang() === 'bn' ? 'আপনি ইতিমধ্যে এই কুপনটি সর্বোচ্চ সংখ্যকবার ব্যবহার করেছেন।' : 'You have already used this coupon.'];
        }
    }

    $disc = $c['discount_type'] === 'fixed'
        ? min($subtotal, (float) $c['discount_value'])
        : min($subtotal, round($subtotal * ((float) $c['discount_value'] / 100), 2));
    $disc = round(max(0.0, $disc), 2);
    $net  = round(max(0.0, $subtotal - $disc), 2);

    return [
        'ok'              => true,
        'coupon_id'       => (int) $c['id'],
        'code'            => (string) $c['code'],
        'discount_amount' => $disc,
        'net_subtotal'    => $net,
        'message'         => current_lang() === 'bn'
            ? 'কুপন প্রযোজ্য হয়েছে — ছাড় ৳' . num_localize(number_format($disc, 0))
            : 'Coupon applied — saved ৳' . number_format($disc, 0),
    ];
}

function shop_cart_summary(?int $forceUserId = null): array
{
    $uid = $forceUserId ?? (function_exists('current_user') && current_user() ? (int) current_user()['id'] : 0);
    $cart = shop_resolve_cart(false, $uid > 0 ? $uid : null);
    $area = ($cart['delivery_area'] ?? 'inside_dhaka') === 'outside_dhaka' ? 'outside_dhaka' : 'inside_dhaka';
    $district = (string) ($cart['district'] ?? 'Dhaka');

    if (!$cart) {
        $del = shop_calculate_delivery(0.0, $area, $district);
        return [
            'cart_id'         => 0,
            'items'           => [],
            'item_count'      => 0,
            'total_weight_g'  => 0,
            'subtotal'        => 0.0,
            'coupon'          => null,
            'coupon_code'     => '',
            'coupon_error'    => null,
            'discount_amount' => 0.0,
            'net_subtotal'    => 0.0,
            'delivery'        => $del,
            'delivery_fee'    => 0.0,
            'total_amount'    => 0.0,
        ];
    }

    $rows = db_all(
        'SELECT sci.id AS cart_item_id, sci.quantity, p.*
         FROM shop_cart_items sci
         JOIN products p ON p.id = sci.product_id
         WHERE sci.cart_id = ?
         ORDER BY sci.id ASC',
        [(int) $cart['id']]
    );

    $items = [];
    $subtotal = 0.0;
    $itemCount = 0;
    $totalWeight = 0;

    foreach ($rows as $r) {
        if ((int) $r['is_active'] !== 1 || (int) $r['stock'] <= 0) {
            continue;
        }
        $qty = min((int) $r['quantity'], (int) $r['stock'], 99);
        if ($qty !== (int) $r['quantity']) {
            db_exec('UPDATE shop_cart_items SET quantity = ? WHERE id = ?', [$qty, (int) $r['cart_item_id']]);
        }
        $unitPrice = shop_product_effective_price($r);
        $lineTotal = round($unitPrice * $qty, 2);
        $subtotal += $lineTotal;
        $itemCount += $qty;
        $totalWeight += ((int) $r['weight_grams']) * $qty;

        $r['quantity']   = $qty;
        $r['unit_price'] = $unitPrice;
        $r['line_total'] = $lineTotal;
        $items[] = $r;
    }

    $subtotal = round($subtotal, 2);
    $couponCode = trim((string) ($cart['coupon_code'] ?? ''));
    $couponInfo = null;
    $couponError = null;
    $discount = 0.0;

    if ($couponCode !== '' && $items) {
        $chk = shop_validate_coupon_for_cart($couponCode, $uid > 0 ? $uid : null, $subtotal);
        if ($chk['ok']) {
            $couponInfo = $chk;
            $discount = (float) $chk['discount_amount'];
        } else {
            $couponError = $chk['message'];
        }
    }

    $netSubtotal = round(max(0.0, $subtotal - $discount), 2);
    $del = $items ? shop_calculate_delivery($netSubtotal, $area, $district) : shop_calculate_delivery(0.0, $area, $district);
    $deliveryFee = $items ? (float) $del['fee'] : 0.0;
    $totalAmount = round($netSubtotal + $deliveryFee, 2);

    return [
        'cart_id'         => (int) $cart['id'],
        'items'           => $items,
        'item_count'      => $itemCount,
        'total_weight_g'  => $totalWeight,
        'subtotal'        => $subtotal,
        'coupon'          => $couponInfo,
        'coupon_code'     => $couponInfo ? $couponInfo['code'] : ($couponError ? $couponCode : ''),
        'coupon_error'    => $couponError,
        'discount_amount' => $discount,
        'net_subtotal'    => $netSubtotal,
        'delivery'        => $del,
        'delivery_fee'    => $deliveryFee,
        'total_amount'    => $totalAmount,
    ];
}

function shop_cart_count(): int
{
    $uid = function_exists('current_user') && current_user() ? (int) current_user()['id'] : 0;
    $cart = shop_resolve_cart(false, $uid > 0 ? $uid : null);
    if (!$cart) {
        return 0;
    }
    $row = db_one(
        'SELECT COALESCE(SUM(LEAST(sci.quantity, p.stock)), 0) AS c
         FROM shop_cart_items sci
         JOIN products p ON p.id = sci.product_id
         WHERE sci.cart_id = ? AND p.is_active = 1 AND p.stock > 0',
        [(int) $cart['id']]
    );
    return (int) ($row['c'] ?? 0);
}

function shop_cart_add(int $productId, int $qty = 1): array
{
    $qty = max(1, min(99, $qty));
    $p = db_one('SELECT id, title_bn, title_en, stock, is_active FROM products WHERE id = ? LIMIT 1', [$productId]);
    if (!$p || (int) $p['is_active'] !== 1) {
        return ['ok' => false, 'message' => current_lang() === 'bn' ? 'বইটি পাওয়া যায়নি।' : 'Product not found.'];
    }
    if ((int) $p['stock'] <= 0) {
        return ['ok' => false, 'message' => current_lang() === 'bn' ? 'দুঃখিত, বইটি স্টক শেষ।' : 'Sorry, this product is out of stock.'];
    }
    $cart = shop_resolve_cart(true);
    $cid = (int) $cart['id'];
    $existing = db_one('SELECT id, quantity FROM shop_cart_items WHERE cart_id = ? AND product_id = ? LIMIT 1', [$cid, $productId]);
    $maxAllowed = min(99, (int) $p['stock']);
    $desired = ($existing ? (int) $existing['quantity'] : 0) + $qty;
    $finalQty = min($maxAllowed, $desired);
    if ($existing) {
        db_exec('UPDATE shop_cart_items SET quantity = ? WHERE id = ?', [$finalQty, (int) $existing['id']]);
    } else {
        db_exec('INSERT INTO shop_cart_items (cart_id, product_id, quantity) VALUES (?, ?, ?)', [$cid, $productId, $finalQty]);
    }
    $capped = $desired > $maxAllowed;
    return [
        'ok'      => true,
        'capped'  => $capped,
        'qty'     => $finalQty,
        'message' => $capped
            ? (current_lang() === 'bn' ? 'সর্বোচ্চ স্টক অনুযায়ী পরিমাণ যুক্ত হয়েছে।' : 'Added up to maximum available stock.')
            : (current_lang() === 'bn' ? 'কার্টে যোগ করা হয়েছে।' : 'Added to cart.'),
    ];
}

function shop_cart_set_qty(int $productId, int $qty): array
{
    $cart = shop_resolve_cart(false);
    if (!$cart) {
        return ['ok' => false, 'message' => 'Cart not found.'];
    }
    $cid = (int) $cart['id'];
    if ($qty <= 0) {
        db_exec('DELETE FROM shop_cart_items WHERE cart_id = ? AND product_id = ?', [$cid, $productId]);
        return ['ok' => true, 'qty' => 0, 'message' => current_lang() === 'bn' ? 'আইটেম সরানো হয়েছে।' : 'Item removed.'];
    }
    $p = db_one('SELECT id, stock, is_active FROM products WHERE id = ? LIMIT 1', [$productId]);
    if (!$p || (int) $p['is_active'] !== 1 || (int) $p['stock'] <= 0) {
        db_exec('DELETE FROM shop_cart_items WHERE cart_id = ? AND product_id = ?', [$cid, $productId]);
        return ['ok' => false, 'message' => current_lang() === 'bn' ? 'বইটি বর্তমানে স্টকে নেই।' : 'Product is out of stock.'];
    }
    $finalQty = min(99, (int) $p['stock'], $qty);
    $existing = db_one('SELECT id FROM shop_cart_items WHERE cart_id = ? AND product_id = ? LIMIT 1', [$cid, $productId]);
    if ($existing) {
        db_exec('UPDATE shop_cart_items SET quantity = ? WHERE id = ?', [$finalQty, (int) $existing['id']]);
    } else {
        db_exec('INSERT INTO shop_cart_items (cart_id, product_id, quantity) VALUES (?, ?, ?)', [$cid, $productId, $finalQty]);
    }
    return ['ok' => true, 'qty' => $finalQty, 'capped' => $qty > $finalQty];
}

function shop_cart_set_shipping(string $area, string $district): void
{
    $cart = shop_resolve_cart(true);
    if (!$cart) {
        return;
    }
    $area = $area === 'outside_dhaka' ? 'outside_dhaka' : 'inside_dhaka';
    $districts = shop_bd_districts();
    if (!isset($districts[$district])) {
        $district = $area === 'inside_dhaka' ? 'Dhaka' : 'Chattogram';
    }
    db_exec('UPDATE shop_carts SET delivery_area = ?, district = ? WHERE id = ?', [$area, $district, (int) $cart['id']]);
}

function shop_cart_apply_coupon(string $codeRaw, ?int $forceUserId = null): array
{
    $cart = shop_resolve_cart(true, $forceUserId);
    if (!$cart) {
        return ['ok' => false, 'message' => 'Cart empty.'];
    }
    $code = strtoupper(trim($codeRaw));
    if ($code === '') {
        db_exec('UPDATE shop_carts SET coupon_code = NULL WHERE id = ?', [(int) $cart['id']]);
        return ['ok' => true, 'message' => current_lang() === 'bn' ? 'কুপন সরানো হয়েছে।' : 'Coupon removed.'];
    }
    $sum = shop_cart_summary($forceUserId);
    if (empty($sum['items'])) {
        return ['ok' => false, 'message' => current_lang() === 'bn' ? 'আপনার কার্ট খালি।' : 'Your cart is empty.'];
    }
    $uid = $forceUserId ?? (function_exists('current_user') && current_user() ? (int) current_user()['id'] : null);
    $chk = shop_validate_coupon_for_cart($code, $uid, (float) $sum['subtotal']);
    if (!$chk['ok']) {
        return $chk;
    }
    db_exec('UPDATE shop_carts SET coupon_code = ? WHERE id = ?', [$chk['code'], (int) $cart['id']]);
    return $chk;
}

// ============================================================================
// Transactional Checkout & Order State Machine
// ============================================================================

function shop_checkout_nonce(): string
{
    if (empty($_SESSION['shop_checkout_nonce'])) {
        $_SESSION['shop_checkout_nonce'] = bin2hex(random_bytes(16));
    }
    return (string) $_SESSION['shop_checkout_nonce'];
}

function shop_create_order(array $input, ?array $user = null, bool $checkRateLimit = true): array
{
    // 1. Honeypot check against bots
    if (trim((string) ($input['website_hp'] ?? '')) !== '') {
        return ['ok' => false, 'message' => 'Submission rejected.'];
    }

    // 2. Basic field validation
    $name     = trim((string) ($input['customer_name'] ?? ''));
    $email    = strtolower(trim((string) ($input['customer_email'] ?? ($user['email'] ?? ''))));
    $phoneRaw = trim((string) ($input['customer_phone'] ?? ''));
    $phoneDigits = preg_replace('/\D+/', '', $phoneRaw) ?? '';
    $address  = trim((string) ($input['shipping_address'] ?? ''));
    $district = trim((string) ($input['district'] ?? 'Dhaka'));
    $area     = (($input['delivery_area'] ?? 'inside_dhaka') === 'outside_dhaka') ? 'outside_dhaka' : 'inside_dhaka';
    $districts = shop_bd_districts();
    if (!isset($districts[$district])) {
        $district = $area === 'inside_dhaka' ? 'Dhaka' : 'Chattogram';
    }

    if (mb_strlen($name) < 2) {
        return ['ok' => false, 'message' => current_lang() === 'bn' ? 'প্রাপকের নাম লিখুন।' : 'Recipient full name is required.'];
    }
    if (strlen($phoneDigits) < 10 || strlen($phoneDigits) > 15) {
        return ['ok' => false, 'message' => current_lang() === 'bn' ? 'সঠিক মোবাইল নম্বর দিন (যেমন 01XXXXXXXXX)।' : 'Please enter a valid mobile number (e.g. 01XXXXXXXXX).'];
    }
    if (mb_strlen($address) < 8) {
        return ['ok' => false, 'message' => current_lang() === 'bn' ? 'সম্পূর্ণ ডেলিভারি ঠিকানা লিখুন।' : 'Please enter a complete delivery address.'];
    }
    if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return ['ok' => false, 'message' => current_lang() === 'bn' ? 'সঠিক ইমেইল দিন বা খালি রাখুন।' : 'Please enter a valid email address or leave blank.'];
    }

    // 3. Guest checkout IP + phone rate limit
    if (!$user && $checkRateLimit && function_exists('rate_limit_check')) {
        if (!rate_limit_check('shop_checkout_ip', client_ip(), 10, 900)) {
            return ['ok' => false, 'message' => current_lang() === 'bn' ? 'অল্প সময়ে অনেকবার চেষ্টা করা হয়েছে। একটু পর চেষ্টা করুন।' : 'Too many checkout attempts from this IP. Please try again shortly.'];
        }
        if (!rate_limit_check('shop_checkout_phone', $phoneDigits, 6, 900)) {
            return ['ok' => false, 'message' => current_lang() === 'bn' ? 'এই নম্বর থেকে অনেকগুলো অর্ডার চেষ্টা করা হয়েছে।' : 'Too many checkout attempts for this phone number. Please try again later.'];
        }
    }

    $delCfg = shop_delivery_config();
    $gwDriver = payment_gateway_driver();
    $useAutoGw = $gwDriver->isAutomatic();
    $method = strtolower(trim((string) ($input['payment_method'] ?? 'cod')));
    if ($method === 'cod' && !$delCfg['cod_enabled']) {
        $method = 'bkash';
    }
    $allowedMethods = ['bkash', 'nagad', 'rocket'];
    if ($delCfg['cod_enabled']) {
        array_unshift($allowedMethods, 'cod');
    }
    if ($useAutoGw) {
        $allowedMethods[] = $gwDriver->getCode();
    }
    if (!in_array($method, $allowedMethods, true)) {
        return ['ok' => false, 'message' => current_lang() === 'bn' ? 'অবৈধ পেমেন্ট পদ্ধতি নির্বাচিত হয়েছে।' : 'Selected payment method is invalid or unavailable.'];
    }

    $sender = trim((string) ($input['sender_number'] ?? ''));
    $trxId  = normalize_transaction_id((string) ($input['transaction_id'] ?? ''));
    $couponCodeInput = strtoupper(trim((string) ($input['coupon_code'] ?? '')));

    $isCod = ($method === 'cod');
    $isAutoMethod = (!$isCod && $useAutoGw);
    if (!$isCod && !$isAutoMethod) {
        if ($sender === '' || $trxId === '') {
            return ['ok' => false, 'message' => current_lang() === 'bn' ? 'প্রেরক নম্বর এবং পেমেন্ট TrxID দিন।' : 'Sender number and Transaction ID (TrxID) are required.'];
        }
    }

    $uploadedProof = null;
    if (!$isCod && !$isAutoMethod && !empty($_FILES['payment_screenshot']) && (int) ($_FILES['payment_screenshot']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
        try {
            $uploadedProof = save_payment_screenshot_upload('payment_screenshot');
        } catch (Throwable $upEx) {
            return ['ok' => false, 'message' => $upEx->getMessage()];
        }
    }

    $uid = $user ? (int) $user['id'] : 0;
    $cart = shop_resolve_cart(false, $uid > 0 ? $uid : null);
    if (!$cart) {
        if ($uploadedProof) {
            @unlink(APP_ROOT . '/storage/' . $uploadedProof);
        }
        return ['ok' => false, 'message' => current_lang() === 'bn' ? 'আপনার কার্ট খালি।' : 'Your shopping cart is empty.'];
    }
    $cartId = (int) $cart['id'];
    if ($couponCodeInput === '' && !empty($cart['coupon_code'])) {
        $couponCodeInput = strtoupper(trim((string) $cart['coupon_code']));
    }

    // 4. Transactional stock reservation with deterministic FOR UPDATE ordering
    $pdo = db();
    $orderId = 0;
    $orderCode = 'SHP-' . date('Ymd') . '-' . strtoupper(bin2hex(random_bytes(2)));

    $pdo->beginTransaction();
    try {
        $cartRows = db_all(
            'SELECT product_id, quantity FROM shop_cart_items WHERE cart_id = ? ORDER BY product_id ASC',
            [$cartId]
        );
        if (!$cartRows) {
            $pdo->rollBack();
            if ($uploadedProof) {
                @unlink(APP_ROOT . '/storage/' . $uploadedProof);
            }
            return ['ok' => false, 'message' => current_lang() === 'bn' ? 'আপনার কার্ট খালি।' : 'Your shopping cart is empty.'];
        }

        $productIds = array_map(static fn(array $r): int => (int) $r['product_id'], $cartRows);
        $ph = implode(',', array_fill(0, count($productIds), '?'));
        $lockedProductsRaw = db_all(
            "SELECT id, title_bn, title_en, sku, price, discount_price, stock, is_active
             FROM products WHERE id IN ({$ph}) ORDER BY id ASC FOR UPDATE",
            $productIds
        );
        $lockedById = [];
        foreach ($lockedProductsRaw as $lp) {
            $lockedById[(int) $lp['id']] = $lp;
        }

        $lineItems = [];
        $subtotal = 0.0;
        foreach ($cartRows as $cr) {
            $pid = (int) $cr['product_id'];
            $qty = max(1, (int) $cr['quantity']);
            $lp = $lockedById[$pid] ?? null;
            if (!$lp || (int) $lp['is_active'] !== 1) {
                $pdo->rollBack();
                if ($uploadedProof) {
                    @unlink(APP_ROOT . '/storage/' . $uploadedProof);
                }
                return ['ok' => false, 'message' => current_lang() === 'bn' ? 'কার্টের একটি বই আর পাওয়া যাচ্ছে না।' : 'One of the products in your cart is no longer available.'];
            }
            if ((int) $lp['stock'] < $qty) {
                $pdo->rollBack();
                if ($uploadedProof) {
                    @unlink(APP_ROOT . '/storage/' . $uploadedProof);
                }
                $tName = current_lang() === 'bn' ? (string) $lp['title_bn'] : (string) $lp['title_en'];
                return [
                    'ok'      => false,
                    'message' => current_lang() === 'bn'
                        ? "দুঃখিত, \"{$tName}\" এর পর্যাপ্ত স্টক নেই (অবশিষ্ট: " . (int) $lp['stock'] . ')।'
                        : "Sorry, insufficient stock for \"{$tName}\" (remaining: " . (int) $lp['stock'] . ').',
                ];
            }
            $unit = shop_product_effective_price($lp);
            $line = round($unit * $qty, 2);
            $subtotal += $line;
            $lineItems[] = [
                'product_id'   => $pid,
                'title_bn'     => (string) $lp['title_bn'],
                'title_en'     => (string) $lp['title_en'],
                'sku'          => $lp['sku'] !== null ? (string) $lp['sku'] : null,
                'stock_before' => (int) $lp['stock'],
                'quantity'     => $qty,
                'unit_price'   => $unit,
                'line_total'   => $line,
            ];
        }
        $subtotal = round($subtotal, 2);

        // Lock and claim coupon atomically if present
        $couponId = null;
        $validCouponCode = null;
        $discountAmount = 0.0;
        if ($couponCodeInput !== '') {
            $cRow = db_one('SELECT * FROM coupons WHERE code = ? AND is_active = 1 LIMIT 1 FOR UPDATE', [$couponCodeInput]);
            if (!$cRow || (!empty($cRow['expires_at']) && strtotime((string) $cRow['expires_at']) < time())) {
                $pdo->rollBack();
                if ($uploadedProof) {
                    @unlink(APP_ROOT . '/storage/' . $uploadedProof);
                }
                return ['ok' => false, 'message' => current_lang() === 'bn' ? 'কুপন কোডটি অকার্যকর বা মেয়াদোত্তীর্ণ।' : 'Coupon is invalid or expired.'];
            }
            if ($subtotal < (float) $cRow['min_amount'] || (int) ($cRow['applies_to_all'] ?? 1) !== 1) {
                $pdo->rollBack();
                if ($uploadedProof) {
                    @unlink(APP_ROOT . '/storage/' . $uploadedProof);
                }
                return ['ok' => false, 'message' => current_lang() === 'bn' ? 'কুপনের শর্ত পূরণ হয়নি।' : 'Coupon requirements are not met for this cart.'];
            }
            if ($uid > 0) {
                $perUser = max(1, (int) ($cRow['per_user_limit'] ?? 1));
                $usedDigital = (int) (db_one("SELECT COUNT(*) AS cnt FROM orders WHERE user_id = ? AND coupon_id = ? AND status NOT IN ('rejected','cancelled','expired')", [$uid, (int) $cRow['id']])['cnt'] ?? 0);
                $usedShop = (int) (db_one("SELECT COUNT(*) AS cnt FROM shop_orders WHERE user_id = ? AND coupon_id = ? AND status NOT IN ('cancelled','returned')", [$uid, (int) $cRow['id']])['cnt'] ?? 0);
                if (($usedDigital + $usedShop) >= $perUser) {
                    $pdo->rollBack();
                    if ($uploadedProof) {
                        @unlink(APP_ROOT . '/storage/' . $uploadedProof);
                    }
                    return ['ok' => false, 'message' => current_lang() === 'bn' ? 'আপনি ইতিমধ্যে এই কুপনটি ব্যবহার করেছেন।' : 'Coupon per-user limit reached.'];
                }
            }
            $cStmt = $pdo->prepare('UPDATE coupons SET used_count = used_count + 1 WHERE id = ? AND (usage_limit IS NULL OR used_count < usage_limit)');
            $cStmt->execute([(int) $cRow['id']]);
            if ($cStmt->rowCount() !== 1) {
                $pdo->rollBack();
                if ($uploadedProof) {
                    @unlink(APP_ROOT . '/storage/' . $uploadedProof);
                }
                return ['ok' => false, 'message' => current_lang() === 'bn' ? 'কুপনটির ব্যবহারের সীমা শেষ।' : 'Coupon usage limit has just been reached.'];
            }
            $couponId = (int) $cRow['id'];
            $validCouponCode = (string) $cRow['code'];
            $discountAmount = $cRow['discount_type'] === 'fixed'
                ? min($subtotal, (float) $cRow['discount_value'])
                : min($subtotal, round($subtotal * ((float) $cRow['discount_value'] / 100), 2));
            $discountAmount = round(max(0.0, $discountAmount), 2);
        }

        $netSubtotal = round(max(0.0, $subtotal - $discountAmount), 2);
        $delInfo = shop_calculate_delivery($netSubtotal, $area, $district);
        $deliveryFee = (float) $delInfo['fee'];
        $totalAmount = round($netSubtotal + $deliveryFee, 2);

        $dbSender = (!$isCod && !$isAutoMethod) ? mb_substr($sender, 0, 40) : null;
        $dbTrx    = (!$isCod && !$isAutoMethod) ? substr($trxId, 0, 80) : null;
        if ($dbTrx !== null) {
            $dup = db_one('SELECT id FROM shop_orders WHERE payment_method = ? AND transaction_id = ? LIMIT 1', [$method, $dbTrx]);
            if ($dup) {
                $pdo->rollBack();
                if ($uploadedProof) {
                    @unlink(APP_ROOT . '/storage/' . $uploadedProof);
                }
                return ['ok' => false, 'message' => current_lang() === 'bn' ? 'এই Transaction ID ইতিমধ্যে ব্যবহৃত হয়েছে।' : 'This Transaction ID has already been used.'];
            }
        }

        $gwCode = $isAutoMethod ? $gwDriver->getCode() : null;
        db_exec(
            'INSERT INTO shop_orders
                (order_code, user_id, customer_name, customer_email, customer_phone, shipping_address, district, delivery_area,
                 subtotal_amount, coupon_id, coupon_code, discount_amount, delivery_fee, total_amount,
                 payment_method, gateway_driver, sender_number, transaction_id, payment_screenshot, status)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, "pending")',
            [
                $orderCode,
                $uid > 0 ? $uid : null,
                mb_substr($name, 0, 120),
                $email !== '' ? mb_substr($email, 0, 190) : null,
                mb_substr($phoneRaw, 0, 30),
                $address,
                $district,
                $area,
                $subtotal,
                $couponId,
                $validCouponCode,
                $discountAmount,
                $deliveryFee,
                $totalAmount,
                $method,
                $gwCode,
                $dbSender,
                $dbTrx,
                $uploadedProof,
            ]
        );
        $orderId = (int) $pdo->lastInsertId();

        $deductStmt = $pdo->prepare('UPDATE products SET stock = stock - ?, sales_count = sales_count + ? WHERE id = ? AND stock >= ?');
        foreach ($lineItems as $li) {
            $deductStmt->execute([$li['quantity'], $li['quantity'], $li['product_id'], $li['quantity']]);
            if ($deductStmt->rowCount() !== 1) {
                throw new RuntimeException('Concurrent stock exhaustion during order placement.');
            }
            db_exec(
                'INSERT INTO shop_order_items (shop_order_id, product_id, title_bn, title_en, sku, unit_price, quantity, line_total)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?)',
                [$orderId, $li['product_id'], $li['title_bn'], $li['title_en'], $li['sku'], $li['unit_price'], $li['quantity'], $li['line_total']]
            );
            db_exec(
                'INSERT INTO product_stock_logs (product_id, admin_id, change_qty, stock_before, stock_after, reason, ref_order_id)
                 VALUES (?, NULL, ?, ?, ?, ?, ?)',
                [$li['product_id'], -$li['quantity'], $li['stock_before'], $li['stock_before'] - $li['quantity'], 'Shop Order #' . $orderCode, $orderId]
            );
        }

        // Clear cart items and reset coupon on cart
        db_exec('DELETE FROM shop_cart_items WHERE cart_id = ?', [$cartId]);
        db_exec('UPDATE shop_carts SET coupon_code = NULL WHERE id = ?', [$cartId]);

        $pdo->commit();
    } catch (PDOException $pe) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        if ($uploadedProof) {
            @unlink(APP_ROOT . '/storage/' . $uploadedProof);
        }
        $msg = current_lang() === 'bn'
            ? 'এই Transaction ID ইতিমধ্যে ব্যবহৃত হয়েছে অথবা অর্ডার সম্পন্ন করা যায়নি।'
            : 'Duplicate Transaction ID or order conflict. Please verify and try again.';
        return ['ok' => false, 'message' => $msg];
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        if ($uploadedProof) {
            @unlink(APP_ROOT . '/storage/' . $uploadedProof);
        }
        return ['ok' => false, 'message' => $e->getMessage()];
    }

    $_SESSION['shop_checkout_nonce'] = bin2hex(random_bytes(16));
    $_SESSION['last_shop_order_code'] = $orderCode;

    // Optional automatic gateway payment session trigger if non-COD automatic gateway selected
    if ($isAutoMethod) {
        $ordRow = db_one('SELECT * FROM shop_orders WHERE id = ?', [$orderId]);
        $userCtx = $user ?: ['id' => 0, 'name' => $name, 'email' => $email, 'phone' => $phoneRaw];
        $itemCtx = ['title_en' => 'Physical Order #' . $orderCode, 'title_bn' => 'শপ অর্ডার #' . $orderCode];
        $gwRes = $gwDriver->create($ordRow ?: ['id' => $orderId, 'order_code' => $orderCode, 'total_amount' => $totalAmount, 'final_amount' => $totalAmount], $itemCtx, $userCtx);
        if (!empty($gwRes['ok']) && !empty($gwRes['redirect_url'])) {
            if (!empty($gwRes['gateway_ref'])) {
                db_exec('UPDATE shop_orders SET gateway_ref = ? WHERE id = ?', [(string) $gwRes['gateway_ref'], $orderId]);
            }
            return [
                'ok'           => true,
                'order_id'     => $orderId,
                'order_code'   => $orderCode,
                'redirect_url' => (string) $gwRes['redirect_url'],
            ];
        }
    }

    notify_shop_order_status_change($orderId, 'pending');
    return ['ok' => true, 'order_id' => $orderId, 'order_code' => $orderCode];
}

/**
 * Notify registered customer (in-site notification) and send email on every shop order status change.
 */
function notify_shop_order_status_change(int $orderId, string $newStatus, ?string $publicNote = null): void
{
    $row = db_one(
        'SELECT so.*, u.email AS account_email, u.name AS account_name
         FROM shop_orders so
         LEFT JOIN users u ON u.id = so.user_id
         WHERE so.id = ? LIMIT 1',
        [$orderId]
    );
    if (!$row) {
        return;
    }

    $uid = (int) ($row['user_id'] ?? 0);
    $code = (string) $row['order_code'];
    $lbl = shop_order_status_labels($newStatus);
    $trackUrl = '/shop/track?code=' . rawurlencode($code);
    $invoiceAbs = BASE_URL . '/shop/orders/' . rawurlencode($code) . '/invoice';
    $note = trim((string) ($publicNote ?? $row['admin_note'] ?? ''));

    $courierLine = '';
    if (!empty($row['courier_name']) || !empty($row['tracking_code'])) {
        $cName = trim((string) ($row['courier_name'] ?? ''));
        $tCode = trim((string) ($row['tracking_code'] ?? ''));
        $courierLine = ($cName !== '' ? $cName : 'Courier') . ($tCode !== '' ? (' — Tracking: ' . $tCode) : '');
    }

    if ($uid > 0) {
        $titleBn = "শপ অর্ডার #{$code}: {$lbl['bn']}";
        $titleEn = "Shop Order #{$code}: {$lbl['en']}";
        $bodyPartsBn = ["স্ট্যাটাস: {$lbl['bn']}"];
        $bodyPartsEn = ["Status: {$lbl['en']}"];
        if ($courierLine !== '') {
            $bodyPartsBn[] = $courierLine;
            $bodyPartsEn[] = $courierLine;
        }
        if ($note !== '') {
            $bodyPartsBn[] = $note;
            $bodyPartsEn[] = $note;
        }
        create_user_notification(
            $uid,
            'shop_order_' . $newStatus,
            $titleBn,
            $titleEn,
            implode(' | ', $bodyPartsBn),
            implode(' | ', $bodyPartsEn),
            $trackUrl
        );
    }

    $email = trim((string) ($row['customer_email'] ?? $row['account_email'] ?? ''));
    if ($email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $custName = (string) ($row['customer_name'] ?? $row['account_name'] ?? 'Customer');
        $bodyHtml = '<p>Hello <strong>' . e($custName) . '</strong>,</p>'
            . '<p>Your Bookexa physical book order <strong>#' . e($code) . '</strong> status is now: <strong>' . e($lbl['en']) . ' (' . e($lbl['bn']) . ')</strong>.</p>'
            . '<p>Total Amount: <strong>৳' . number_format((float) $row['total_amount'], 0) . '</strong> (' . e(strtoupper((string) $row['payment_method'])) . ')</p>';
        if ($courierLine !== '') {
            $bodyHtml .= '<p>Courier / Tracking: <strong>' . e($courierLine) . '</strong></p>';
        }
        if ($note !== '') {
            $bodyHtml .= '<p>Note: <em>' . e($note) . '</em></p>';
        }
        $bodyHtml .= '<p><a href="' . e($invoiceAbs) . '" style="color:#14b8a6;font-weight:700;">View / Print Order Invoice</a></p>';

        send_bookexa_template_mail(
            $email,
            "Order #{$code} — {$lbl['en']} | Bookexa",
            "Shop Order #{$code}: {$lbl['en']}",
            $bodyHtml,
            BASE_URL . $trackUrl,
            'Track Order'
        );
    }
}

/**
 * Idempotent state machine for physical orders with automatic stock restoration
 * and coupon release on cancelled/returned transitions.
 */
function shop_transition_order_status(int $orderId, string $targetStatus, ?int $adminId = null, array $meta = []): array
{
    if (!in_array($targetStatus, SHOP_ORDER_STATUSES, true)) {
        return ['ok' => false, 'changed' => false, 'message' => 'Invalid target status.'];
    }

    $restockOnReturn = array_key_exists('restock', $meta) ? (bool) $meta['restock'] : true;
    $pdo = db();
    $pdo->beginTransaction();
    try {
        $order = db_one('SELECT * FROM shop_orders WHERE id = ? LIMIT 1 FOR UPDATE', [$orderId]);
        if (!$order) {
            $pdo->rollBack();
            return ['ok' => false, 'changed' => false, 'message' => 'Order not found.'];
        }

        $oldStatus = (string) $order['status'];
        $changed = ($oldStatus !== $targetStatus);
        $stockRestored = (int) ($order['stock_restored'] ?? 0);
        $couponDecremented = (int) ($order['coupon_decremented'] ?? 0);
        $isReleaseStatus = in_array($targetStatus, ['cancelled', 'returned'], true);

        if ($isReleaseStatus && $stockRestored === 0 && $restockOnReturn) {
            $items = db_all('SELECT product_id, quantity FROM shop_order_items WHERE shop_order_id = ? ORDER BY product_id ASC', [$orderId]);
            foreach ($items as $it) {
                $pid = (int) ($it['product_id'] ?? 0);
                $qty = (int) $it['quantity'];
                if ($pid <= 0 || $qty <= 0) {
                    continue;
                }
                $p = db_one('SELECT id, stock FROM products WHERE id = ? LIMIT 1 FOR UPDATE', [$pid]);
                if ($p) {
                    $before = (int) $p['stock'];
                    $after = $before + $qty;
                    db_exec('UPDATE products SET stock = ?, sales_count = GREATEST(0, sales_count - ?) WHERE id = ?', [$after, $qty, $pid]);
                    db_exec(
                        'INSERT INTO product_stock_logs (product_id, admin_id, change_qty, stock_before, stock_after, reason, ref_order_id)
                         VALUES (?, ?, ?, ?, ?, ?, ?)',
                        [$pid, $adminId, $qty, $before, $after, ucfirst($targetStatus) . ' Order #' . $order['order_code'], $orderId]
                    );
                }
            }
            $stockRestored = 1;
        } elseif (!$isReleaseStatus && $stockRestored === 1) {
            // Re-reserve stock if moving back out of cancelled/returned
            $items = db_all('SELECT product_id, quantity FROM shop_order_items WHERE shop_order_id = ? ORDER BY product_id ASC', [$orderId]);
            foreach ($items as $it) {
                $pid = (int) ($it['product_id'] ?? 0);
                $qty = (int) $it['quantity'];
                if ($pid <= 0 || $qty <= 0) {
                    continue;
                }
                $p = db_one('SELECT id, stock FROM products WHERE id = ? LIMIT 1 FOR UPDATE', [$pid]);
                if (!$p || (int) $p['stock'] < $qty) {
                    $pdo->rollBack();
                    return ['ok' => false, 'changed' => false, 'message' => 'Cannot reactivate order: product out of stock.'];
                }
                $before = (int) $p['stock'];
                $after = $before - $qty;
                db_exec('UPDATE products SET stock = ?, sales_count = sales_count + ? WHERE id = ?', [$after, $qty, $pid]);
                db_exec(
                    'INSERT INTO product_stock_logs (product_id, admin_id, change_qty, stock_before, stock_after, reason, ref_order_id)
                     VALUES (?, ?, ?, ?, ?, ?, ?)',
                    [$pid, $adminId, -$qty, $before, $after, 'Re-activated Order #' . $order['order_code'], $orderId]
                );
            }
            $stockRestored = 0;
        }

        if ($isReleaseStatus && !empty($order['coupon_id']) && $couponDecremented === 0) {
            db_exec('UPDATE coupons SET used_count = GREATEST(0, used_count - 1) WHERE id = ?', [(int) $order['coupon_id']]);
            $couponDecremented = 1;
        } elseif (!$isReleaseStatus && !empty($order['coupon_id']) && $couponDecremented === 1) {
            db_exec('UPDATE coupons SET used_count = used_count + 1 WHERE id = ?', [(int) $order['coupon_id']]);
            $couponDecremented = 0;
        }

        $courier = array_key_exists('courier_name', $meta) ? ($meta['courier_name'] !== '' ? trim((string) $meta['courier_name']) : null) : $order['courier_name'];
        $tracking = array_key_exists('tracking_code', $meta) ? ($meta['tracking_code'] !== '' ? trim((string) $meta['tracking_code']) : null) : $order['tracking_code'];
        $adminNote = array_key_exists('admin_note', $meta) ? ($meta['admin_note'] !== '' ? trim((string) $meta['admin_note']) : null) : $order['admin_note'];
        $internalNotes = array_key_exists('internal_notes', $meta) ? ($meta['internal_notes'] !== '' ? trim((string) $meta['internal_notes']) : null) : $order['internal_notes'];
        $deliveredAtSql = $targetStatus === 'delivered' ? 'COALESCE(delivered_at, NOW())' : 'delivered_at';

        db_exec(
            "UPDATE shop_orders
             SET status = ?, stock_restored = ?, coupon_decremented = ?, courier_name = ?, tracking_code = ?,
                 admin_note = ?, internal_notes = ?, reviewed_by = COALESCE(?, reviewed_by), delivered_at = {$deliveredAtSql}
             WHERE id = ?",
            [$targetStatus, $stockRestored, $couponDecremented, $courier, $tracking, $adminNote, $internalNotes, $adminId, $orderId]
        );

        $updated = db_one('SELECT * FROM shop_orders WHERE id = ?', [$orderId]);
        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }

    if ($changed) {
        notify_shop_order_status_change($orderId, $targetStatus, $meta['admin_note'] ?? null);
    }
    return ['ok' => true, 'changed' => $changed, 'old_status' => $oldStatus, 'order' => $updated];
}

function shop_can_request_return(array $order): bool
{
    if (($order['status'] ?? '') !== 'delivered' || ($order['return_status'] ?? 'none') !== 'none') {
        return false;
    }
    $days = shop_delivery_config()['return_days'];
    $baseTime = strtotime((string) ($order['delivered_at'] ?: $order['updated_at'] ?: 'now'));
    return $baseTime !== false && (time() - $baseTime) <= ($days * 86400);
}

function shop_request_return(int $orderId, int $userId, string $reason): array
{
    $reason = trim($reason);
    if (mb_strlen($reason) < 5) {
        return ['ok' => false, 'message' => current_lang() === 'bn' ? 'রিটার্নের কারণ বিস্তারিত লিখুন (কমপক্ষে ৫ অক্ষর)।' : 'Please describe the return reason (at least 5 characters).'];
    }
    $order = db_one('SELECT * FROM shop_orders WHERE id = ? AND user_id = ? LIMIT 1', [$orderId, $userId]);
    if (!$order) {
        return ['ok' => false, 'message' => 'Order not found.'];
    }
    if (!shop_can_request_return($order)) {
        return ['ok' => false, 'message' => current_lang() === 'bn' ? 'এই অর্ডারের জন্য রিটার্ন সময়সীমা বা শর্ত পূরণ হয়নি।' : 'Order is no longer eligible for a return request.'];
    }
    db_exec(
        "UPDATE shop_orders SET return_status = 'requested', return_reason = ?, return_requested_at = NOW() WHERE id = ?",
        [mb_substr($reason, 0, 1000), $orderId]
    );
    return ['ok' => true, 'message' => current_lang() === 'bn' ? 'রিটার্ন অনুরোধ জমা হয়েছে। অ্যাডমিন শীঘ্রই যাচাই করবেন।' : 'Return request submitted. Our support team will review it shortly.'];
}

function shop_handle_return(int $orderId, string $decision, int $adminId, bool $restock = true, ?string $note = null): array
{
    if (!in_array($decision, ['approved', 'rejected'], true)) {
        return ['ok' => false, 'message' => 'Invalid return decision.'];
    }
    $order = db_one('SELECT * FROM shop_orders WHERE id = ? LIMIT 1', [$orderId]);
    if (!$order || ($order['return_status'] ?? 'none') === 'none') {
        return ['ok' => false, 'message' => 'No return request found on this order.'];
    }
    db_exec(
        'UPDATE shop_orders SET return_status = ?, return_note = ? WHERE id = ?',
        [$decision, $note !== null && $note !== '' ? mb_substr($note, 0, 1000) : null, $orderId]
    );

    if ($decision === 'approved') {
        return shop_transition_order_status($orderId, 'returned', $adminId, [
            'restock'    => $restock,
            'admin_note' => $note ?: 'Return request approved',
        ]);
    }

    $uid = (int) ($order['user_id'] ?? 0);
    $code = (string) $order['order_code'];
    if ($uid > 0) {
        create_user_notification(
            $uid,
            'shop_return_rejected',
            "রিটার্ন অনুরোধ বাতিল (#{$code})",
            "Return Request Declined (#{$code})",
            $note ?: 'রিটার্ন শর্ত পূরণ না হওয়ায় অনুরোধটি অনুমোদিত হয়নি।',
            $note ?: 'Your return request could not be approved.',
            '/orders'
        );
    }
    $email = trim((string) ($order['customer_email'] ?? ''));
    if ($email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL)) {
        send_bookexa_template_mail(
            $email,
            "Return Update #{$code} — Bookexa",
            "Return Request Update for #{$code}",
            '<p>Your return request for order <strong>#' . e($code) . '</strong> was declined.</p>' . ($note ? '<p>Note: ' . e($note) . '</p>' : ''),
            BASE_URL . '/orders',
            'View Orders'
        );
    }
    return ['ok' => true, 'changed' => true, 'order' => db_one('SELECT * FROM shop_orders WHERE id = ?', [$orderId])];
}

function shop_reorder_to_cart(int $orderId, int $userId): array
{
    $order = db_one('SELECT id FROM shop_orders WHERE id = ? AND user_id = ? LIMIT 1', [$orderId, $userId]);
    if (!$order) {
        return ['ok' => false, 'message' => 'Order not found.'];
    }
    $items = db_all('SELECT product_id, quantity FROM shop_order_items WHERE shop_order_id = ?', [$orderId]);
    $added = 0;
    $skipped = 0;
    foreach ($items as $it) {
        if (empty($it['product_id'])) {
            $skipped++;
            continue;
        }
        $r = shop_cart_add((int) $it['product_id'], (int) $it['quantity']);
        if (!empty($r['ok'])) {
            $added++;
        } else {
            $skipped++;
        }
    }
    if ($added === 0) {
        return ['ok' => false, 'message' => current_lang() === 'bn' ? 'এই অর্ডারের বইগুলো বর্তমানে স্টকে নেই।' : 'None of the items in that order are currently in stock.'];
    }
    return [
        'ok'      => true,
        'added'   => $added,
        'skipped' => $skipped,
        'message' => $skipped > 0
            ? (current_lang() === 'bn' ? "{$added}টি বই কার্টে যোগ হয়েছে ({$skipped}টি স্টক শেষ)।" : "{$added} item(s) added to cart ({$skipped} currently out of stock).")
            : (current_lang() === 'bn' ? 'অর্ডারের সকল বই কার্টে যোগ হয়েছে।' : 'All items from your order were added to cart.'),
    ];
}

function shop_duplicate_trx_map(array $orders): array
{
    $trxCounts = [];
    foreach ($orders as $o) {
        $trx = strtoupper(trim((string) ($o['transaction_id'] ?? '')));
        if ($trx !== '') {
            $trxCounts[$trx] = ($trxCounts[$trx] ?? 0) + 1;
        }
    }
    if (!$trxCounts) {
        return [];
    }
    $dups = [];
    foreach ($trxCounts as $trx => $cnt) {
        $dbCnt = (int) (db_one('SELECT COUNT(*) AS c FROM shop_orders WHERE transaction_id = ?', [$trx])['c'] ?? 0)
            + (int) (db_one('SELECT COUNT(*) AS c FROM orders WHERE transaction_id = ?', [$trx])['c'] ?? 0);
        if ($cnt > 1 || $dbCnt > 1) {
            $dups[$trx] = max($cnt, $dbCnt);
        }
    }
    return $dups;
}

// ============================================================================
// Admin Product CSV Parse / Import
// ============================================================================

function shop_parse_products_csv(string $csvPath): array
{
    $fh = @fopen($csvPath, 'rb');
    if (!$fh) {
        throw new RuntimeException('Unable to read uploaded CSV file.');
    }
    $rawHeader = fgetcsv($fh);
    if (!$rawHeader) {
        fclose($fh);
        throw new RuntimeException('CSV file appears empty.');
    }
    $rawHeader[0] = preg_replace('/^\xEF\xBB\xBF/', '', (string) $rawHeader[0]) ?? '';
    $headers = array_map(static fn($h): string => strtolower(trim((string) $h)), $rawHeader);

    $rows = [];
    $seenSlugs = [];
    $seenSkus  = [];
    $summary = ['total' => 0, 'valid' => 0, 'errors' => 0];
    $lineNo = 1;

    while (($cols = fgetcsv($fh)) !== false) {
        $lineNo++;
        if (count($cols) === 1 && trim((string) $cols[0]) === '') {
            continue;
        }
        $assoc = [];
        foreach ($headers as $idx => $key) {
            if ($key !== '') {
                $assoc[$key] = trim((string) ($cols[$idx] ?? ''));
            }
        }
        $catSlug  = (string) ($assoc['category_slug'] ?? ($assoc['category'] ?? ''));
        $titleEn  = (string) ($assoc['title_en'] ?? ($assoc['title'] ?? ''));
        $titleBn  = (string) ($assoc['title_bn'] ?? $titleEn);
        $authorEn = (string) ($assoc['author_en'] ?? ($assoc['author'] ?? ''));
        $authorBn = (string) ($assoc['author_bn'] ?? $authorEn);
        $publisher = (string) ($assoc['publisher'] ?? '');
        $isbn     = (string) ($assoc['isbn'] ?? '');
        $sku      = trim((string) ($assoc['sku'] ?? ''));
        $price    = max(0.0, (float) ($assoc['price'] ?? 0));
        $discRaw  = trim((string) ($assoc['discount_price'] ?? ''));
        $disc     = $discRaw !== '' ? max(0.0, (float) $discRaw) : null;
        $stock    = max(0, (int) ($assoc['stock'] ?? 0));
        $weight   = max(50, (int) ($assoc['weight_grams'] ?? 300));
        $descEn   = (string) ($assoc['description_en'] ?? ($assoc['description'] ?? ''));
        $descBn   = (string) ($assoc['description_bn'] ?? $descEn);
        $featured = in_array(strtolower((string) ($assoc['is_featured'] ?? '0')), ['1', 'true', 'yes'], true) ? 1 : 0;
        $active   = in_array(strtolower((string) ($assoc['is_active'] ?? '1')), ['0', 'false', 'no'], true) ? 0 : 1;
        $slug     = slugify((string) ($assoc['slug'] ?? '') ?: ($titleEn !== '' ? $titleEn : $titleBn));

        $errors = [];
        if ($titleEn === '' && $titleBn === '') {
            $errors[] = 'Missing title';
        }
        if ($titleBn === '') {
            $titleBn = $titleEn;
        }
        if ($titleEn === '') {
            $titleEn = $titleBn;
        }
        if ($price <= 0) {
            $errors[] = 'Price must be greater than 0';
        }
        if (isset($seenSlugs[$slug]) || db_one('SELECT id FROM products WHERE slug = ? LIMIT 1', [$slug])) {
            $errors[] = 'Duplicate slug: ' . $slug;
        }
        $seenSlugs[$slug] = true;
        if ($sku !== '') {
            if (isset($seenSkus[$sku]) || db_one('SELECT id FROM products WHERE sku = ? LIMIT 1', [$sku])) {
                $errors[] = 'Duplicate SKU: ' . $sku;
            }
            $seenSkus[$sku] = true;
        }

        $summary['total']++;
        if ($errors) {
            $summary['errors']++;
        } else {
            $summary['valid']++;
        }
        $rows[] = [
            'line'           => $lineNo,
            'category_slug'  => $catSlug,
            'slug'           => $slug,
            'sku'            => $sku !== '' ? $sku : null,
            'title_bn'       => $titleBn,
            'title_en'       => $titleEn,
            'author_bn'      => $authorBn,
            'author_en'      => $authorEn,
            'publisher'      => $publisher,
            'isbn'           => $isbn,
            'price'          => $price,
            'discount_price' => ($disc !== null && $disc > 0 && $disc < $price) ? $disc : null,
            'stock'          => $stock,
            'weight_grams'   => $weight,
            'description_bn' => $descBn,
            'description_en' => $descEn,
            'is_featured'    => $featured,
            'is_active'      => $active,
            'errors'         => $errors,
        ];
    }
    fclose($fh);
    return ['rows' => $rows, 'summary' => $summary];
}
