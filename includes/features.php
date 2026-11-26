<?php
declare(strict_types=1);

require_once APP_ROOT . '/includes/mailer.php';

// ============================================================================
// 1. REAL WISHLIST
// ============================================================================
function user_wishlist_map(int $userId): array
{
    static $maps = [];
    if ($userId <= 0) {
        return [];
    }
    if (!isset($maps[$userId])) {
        $maps[$userId] = [];
        try {
            foreach (db_all('SELECT item_id FROM wishlist WHERE user_id = ?', [$userId]) as $r) {
                $maps[$userId][(int) $r['item_id']] = true;
            }
        } catch (Throwable $e) {
        }
    }
    return $maps[$userId];
}

function user_has_wishlisted(int $userId, int $itemId): bool
{
    $map = user_wishlist_map($userId);
    return isset($map[$itemId]);
}

function toggle_wishlist_item(int $userId, int $itemId): array
{
    $existing = db_one('SELECT id FROM wishlist WHERE user_id = ? AND item_id = ?', [$userId, $itemId]);
    if ($existing) {
        db_exec('DELETE FROM wishlist WHERE id = ?', [(int) $existing['id']]);
        $inWishlist = false;
    } else {
        db_exec('INSERT INTO wishlist (user_id, item_id) VALUES (?, ?)', [$userId, $itemId]);
        $inWishlist = true;
    }
    $cnt = (int) (db_one('SELECT COUNT(*) AS c FROM wishlist WHERE user_id = ?', [$userId])['c'] ?? 0);
    return ['in_wishlist' => $inWishlist, 'user_wishlist_count' => $cnt];
}

function user_wishlist_count(int $userId): int
{
    if ($userId <= 0) {
        return 0;
    }
    try {
        return (int) (db_one('SELECT COUNT(*) AS c FROM wishlist w JOIN items i ON i.id = w.item_id WHERE w.user_id = ? AND i.is_active = 1', [$userId])['c'] ?? 0);
    } catch (Throwable $e) {
        return 0;
    }
}

// ============================================================================
// 2. REVIEWS & RATINGS
// ============================================================================
function user_can_review_item(int $userId, int $itemId): bool
{
    if ($userId <= 0 || $itemId <= 0) {
        return false;
    }
    if (user_owns_item($userId, $itemId)) {
        return true;
    }
    if (db_one('SELECT id FROM reading_progress WHERE user_id = ? AND item_id = ? LIMIT 1', [$userId, $itemId])) {
        return true;
    }
    if (db_one('SELECT id FROM reading_logs WHERE user_id = ? AND item_id = ? LIMIT 1', [$userId, $itemId])) {
        return true;
    }
    return false;
}

function &item_rating_cache_store(): array
{
    static $store = [];
    return $store;
}

/**
 * Batch-preload approved review ratings (+ optional user wishlist map) in 1 query to avoid N+1 on grids.
 */
function preload_items_card_meta(array $items): void
{
    $ids = [];
    foreach ($items as $it) {
        $id = is_array($it) ? (int) ($it['id'] ?? 0) : (int) $it;
        if ($id > 0) {
            $ids[$id] = true;
        }
    }
    if (!$ids) {
        return;
    }
    $idList = array_keys($ids);
    $store = &item_rating_cache_store();
    $missing = array_values(array_filter($idList, static fn(int $id): bool => !isset($store[$id])));
    if ($missing) {
        foreach ($missing as $mid) {
            $store[$mid] = ['count' => 0, 'avg' => 0.0];
        }
        try {
            $ph = implode(',', array_fill(0, count($missing), '?'));
            $rows = db_all(
                "SELECT item_id, COUNT(*) AS cnt, COALESCE(AVG(rating), 0) AS avg_rating
                 FROM reviews
                 WHERE item_id IN ({$ph}) AND status = 'approved'
                 GROUP BY item_id",
                $missing
            );
            foreach ($rows as $r) {
                $iid = (int) $r['item_id'];
                $store[$iid] = [
                    'count' => (int) ($r['cnt'] ?? 0),
                    'avg'   => round((float) ($r['avg_rating'] ?? 0), 1),
                ];
            }
        } catch (Throwable $e) {
        }
    }
    $u = function_exists('current_user') ? current_user() : null;
    if ($u) {
        user_wishlist_map((int) $u['id']);
    }
}

function item_rating_summary(int $itemId): array
{
    $store = &item_rating_cache_store();
    if (isset($store[$itemId])) {
        return $store[$itemId];
    }
    try {
        $row = db_one(
            "SELECT COUNT(*) AS cnt, COALESCE(AVG(rating), 0) AS avg_rating
             FROM reviews
             WHERE item_id = ? AND status = 'approved'",
            [$itemId]
        );
        $res = [
            'count' => (int) ($row['cnt'] ?? 0),
            'avg'   => round((float) ($row['avg_rating'] ?? 0), 1),
        ];
        $store[$itemId] = $res;
        return $res;
    } catch (Throwable $e) {
        return ['count' => 0, 'avg' => 0.0];
    }
}

function render_stars_html(float $rating): string
{
    $full = (int) round($rating);
    $html = '<span class="bx-stars" aria-label="' . e((string) $rating) . ' / 5">';
    for ($i = 1; $i <= 5; $i++) {
        $html .= '<span class="bx-star ' . ($i <= $full ? 'filled' : '') . '">★</span>';
    }
    $html .= '</span>';
    return $html;
}

// ============================================================================
// 3. COUPONS & DISCOUNTS
// ============================================================================
function normalize_transaction_id(string $raw): string
{
    return strtoupper(preg_replace('/[^A-Za-z0-9]/', '', trim($raw)) ?? '');
}

function validate_coupon_for_checkout(string $rawCode, int $userId, int $itemId, float $basePrice): array
{
    $code = strtoupper(trim($rawCode));
    if ($code === '') {
        return ['ok' => false, 'message' => current_lang() === 'bn' ? 'কুপন কোড লিখুন।' : 'Enter a coupon code.'];
    }
    $c = db_one('SELECT * FROM coupons WHERE code = ? LIMIT 1', [$code]);
    if (!$c || !(int) $c['is_active']) {
        return ['ok' => false, 'message' => current_lang() === 'bn' ? 'অবৈধ বা নিষ্ক্রিয় কুপন কোড।' : 'Invalid or inactive coupon code.'];
    }
    if (!empty($c['expires_at']) && strtotime((string) $c['expires_at']) < time()) {
        return ['ok' => false, 'message' => current_lang() === 'bn' ? 'এই কুপনের মেয়াদ শেষ হয়ে গেছে।' : 'This coupon has expired.'];
    }
    if ($c['usage_limit'] !== null && (int) $c['used_count'] >= (int) $c['usage_limit']) {
        return ['ok' => false, 'message' => current_lang() === 'bn' ? 'এই কুপনের ব্যবহারের সীমা পূর্ণ হয়েছে।' : 'This coupon usage limit has been reached.'];
    }
    $minAmount = (float) ($c['min_amount'] ?? 0);
    if ($minAmount > 0 && $basePrice < $minAmount) {
        return ['ok' => false, 'message' => current_lang() === 'bn'
            ? 'এই কুপন ব্যবহারের জন্য ন্যূনতম ' . format_bdt($minAmount) . ' প্রয়োজন।'
            : 'Minimum order amount ' . format_bdt($minAmount) . ' is required for this coupon.'];
    }
    $perUserLimit = max(1, (int) ($c['per_user_limit'] ?? 1));
    $userUsed = (int) (db_one(
        "SELECT COUNT(*) AS c FROM orders WHERE user_id = ? AND coupon_id = ? AND status != 'rejected'",
        [$userId, (int) $c['id']]
    )['c'] ?? 0);
    if ($userUsed >= $perUserLimit) {
        return ['ok' => false, 'message' => current_lang() === 'bn' ? 'আপনি ইতিমধ্যে এই কুপনটি সর্বোচ্চ সংখ্যকবার ব্যবহার করেছেন।' : 'You have already reached the per-user limit for this coupon.'];
    }
    if (!(int) $c['applies_to_all']) {
        $match = db_one('SELECT 1 FROM coupon_items WHERE coupon_id = ? AND item_id = ? LIMIT 1', [(int) $c['id'], $itemId]);
        if (!$match) {
            return ['ok' => false, 'message' => current_lang() === 'bn' ? 'এই বইয়ের জন্য কুপনটি প্রযোজ্য নয়।' : 'This coupon is not valid for this PDF.'];
        }
    }

    $val = (float) $c['discount_value'];
    if ($c['discount_type'] === 'percent') {
        $discount = round($basePrice * (min(100.0, max(0.0, $val)) / 100.0), 2);
    } else {
        $discount = min($basePrice, round(max(0.0, $val), 2));
    }
    $discount = min($basePrice, max(0.0, $discount));
    $finalAmount = max(0.0, round($basePrice - $discount, 2));

    return [
        'ok'              => true,
        'coupon'          => $c,
        'coupon_id'       => (int) $c['id'],
        'code'            => $c['code'],
        'original_amount' => $basePrice,
        'discount_amount' => $discount,
        'final_amount'    => $finalAmount,
        'message'         => current_lang() === 'bn'
            ? "কুপন {$c['code']} প্রয়োগ হয়েছে! ছাড়: " . format_bdt($discount)
            : "Coupon {$c['code']} applied! Saved " . format_bdt($discount),
    ];
}

// ============================================================================
// 4. NOTIFICATIONS
// ============================================================================
function create_user_notification(int $userId, string $type, string $titleBn, string $titleEn, string $bodyBn = '', string $bodyEn = '', string $linkUrl = ''): void
{
    if ($userId <= 0) {
        return;
    }
    try {
        db_exec(
            'INSERT INTO notifications (user_id, type, title_bn, title_en, body_bn, body_en, link_url) VALUES (?, ?, ?, ?, ?, ?, ?)',
            [
                $userId,
                substr($type, 0, 40),
                mb_substr($titleBn, 0, 175),
                mb_substr($titleEn, 0, 175),
                $bodyBn !== '' ? mb_substr($bodyBn, 0, 250) : null,
                $bodyEn !== '' ? mb_substr($bodyEn, 0, 250) : null,
                $linkUrl !== '' ? substr($linkUrl, 0, 250) : null,
            ]
        );
    } catch (Throwable $e) {
    }
}

function unread_notifications_count(int $userId): int
{
    if ($userId <= 0) {
        return 0;
    }
    try {
        return (int) (db_one('SELECT COUNT(*) AS c FROM notifications WHERE user_id = ? AND is_read = 0', [$userId])['c'] ?? 0);
    } catch (Throwable $e) {
        return 0;
    }
}

function record_user_folder_visit(int $userId, int $folderId): void
{
    if ($userId <= 0 || $folderId <= 0) {
        return;
    }
    try {
        db_exec(
            'INSERT INTO folder_visits (user_id, folder_id) VALUES (?, ?) ON DUPLICATE KEY UPDATE last_visited_at = NOW()',
            [$userId, $folderId]
        );
    } catch (Throwable $e) {
    }
}

function notify_folder_visitors_new_pdf(int $folderId, string $slug, string $titleBn, string $titleEn): int
{
    if ($folderId <= 0) {
        return 0;
    }
    $folder = db_one('SELECT name_bn, name_en FROM folders WHERE id = ? LIMIT 1', [$folderId]);
    if (!$folder) {
        return 0;
    }
    $visitors = db_all('SELECT user_id FROM folder_visits WHERE folder_id = ?', [$folderId]);
    if (!$visitors) {
        return 0;
    }
    $link = '/book/' . rawurlencode($slug);
    $count = 0;
    foreach ($visitors as $v) {
        create_user_notification(
            (int) $v['user_id'],
            'new_pdf_in_folder',
            'নতুন PDF যুক্ত হয়েছে: ' . $titleBn,
            'New PDF added: ' . $titleEn,
            $folder['name_bn'] . ' ফোল্ডারে নতুন বই যুক্ত হয়েছে।',
            'A new book was published inside ' . $folder['name_en'] . '.',
            $link
        );
        $count++;
    }
    return $count;
}
