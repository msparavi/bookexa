<?php
declare(strict_types=1);

if (str_starts_with($route, '/api/')) {
    $ipLimitMap = [
        '/api/page'                  => 300,
        '/api/stream-pdf'            => 300,
        '/api/reader-heartbeat'      => 180,
        '/api/reader-progress'       => 180,
        '/api/reader-bookmarks'      => 180,
        '/api/reader-search'         => 180,
        '/api/reader-security-event' => 180,
        '/api/search-suggest'        => 120,
        '/api/items-by-ids'          => 120,
        '/api/wishlist-toggle'       => 90,
        '/api/shop-wishlist-toggle'  => 90,
        '/api/cart'                  => 120,
        '/api/notifications/read'    => 90,
        '/api/coupon-apply'          => 90,
        '/api/payment-webhook'       => 60,
        '/api/payment-sandbox'       => 90,
    ];
    $rlMax = $ipLimitMap[$route] ?? 120;
    $rl = public_api_ip_rate_limit('pubapi:' . $route, $rlMax, 60);
    header('X-RateLimit-Limit: ' . $rlMax);
    header('X-RateLimit-Remaining: ' . (int) $rl['remaining']);
    if (!$rl['allowed']) {
        http_response_code(429);
        header('Content-Type: application/json; charset=UTF-8');
        header('Retry-After: ' . (int) $rl['reset']);
        header('Cache-Control: no-store');
        echo json_encode([
            'ok'      => false,
            'error'   => 'IP_RATE_LIMITED',
            'message' => 'Too many API requests from your IP. Please slow down.',
        ]);
        exit;
    }
}

// ----------------------------------------------------------------------------
// Protected Reader & Stream APIs
// ----------------------------------------------------------------------------
if ($route === '/api/stream-pdf') {
    require_once APP_ROOT . '/includes/pdf_proxy.php';
    handle_stream_pdf();
}
if ($route === '/api/page') {
    require_once APP_ROOT . '/includes/pdf_proxy.php';
    handle_protected_page();
}
if ($route === '/api/reader-heartbeat') {
    require_once APP_ROOT . '/includes/pdf_proxy.php';
    handle_reader_heartbeat();
}
if ($route === '/api/reader-progress' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    require_once APP_ROOT . '/includes/pdf_proxy.php';
    handle_reader_progress();
}
if ($route === '/api/reader-security-event' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    require_once APP_ROOT . '/includes/pdf_proxy.php';
    handle_reader_security_event();
}
if ($route === '/api/reader-bookmarks') {
    require_once APP_ROOT . '/includes/pdf_proxy.php';
    handle_reader_bookmarks();
}
if ($route === '/api/reader-search') {
    require_once APP_ROOT . '/includes/pdf_proxy.php';
    handle_reader_search();
}
if ($route === '/api/search-suggest') {
    header('Content-Type: application/json; charset=UTF-8');
    $q = trim((string) ($_GET['q'] ?? ''));
    echo json_encode(['ok' => true] + browse_search_suggestions($q, 6), JSON_UNESCAPED_UNICODE);
    exit;
}
if ($route === '/api/items-by-ids') {
    header('Content-Type: application/json; charset=UTF-8');
    $raw = trim((string) ($_GET['ids'] ?? ''));
    $ids = array_values(array_unique(array_filter(array_map('intval', explode(',', $raw)), static fn(int $id): bool => $id > 0)));
    $ids = array_slice($ids, 0, 12);
    $html = '';
    if ($ids) {
        $ph = implode(',', array_fill(0, count($ids), '?'));
        $rows = db_all(
            "SELECT id, folder_id, slug, title_bn, title_en, author_bn, author_en, cover, price, original_price, is_free, is_featured, sample_pages, total_pages, view_count, read_count
             FROM items WHERE is_active = 1 AND " . published_window_sql() . " AND id IN ({$ph})",
            $ids
        );
        preload_items_card_meta($rows);
        $byId = [];
        foreach ($rows as $r) {
            $byId[(int) $r['id']] = $r;
        }
        ob_start();
        foreach ($ids as $id) {
            if (isset($byId[$id])) {
                echo render_book_card($byId[$id]);
            }
        }
        $html = (string) ob_get_clean();
    }
    echo json_encode(['ok' => true, 'html' => $html], JSON_UNESCAPED_UNICODE);
    exit;
}

// ----------------------------------------------------------------------------
// Storefront AJAX APIs: Wishlist, Notifications, Coupon Apply
// ----------------------------------------------------------------------------
if ($route === '/api/wishlist-toggle' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json; charset=UTF-8');
    $u = current_user();
    if (!$u) {
        http_response_code(401);
        echo json_encode(['ok' => false, 'code' => 'LOGIN_REQUIRED', 'message' => 'Login required']);
        exit;
    }
    verify_csrf();
    $itemId = (int) ($_POST['item_id'] ?? 0);
    $item = $itemId > 0 ? db_one('SELECT id FROM items WHERE id = ? AND is_active = 1 LIMIT 1', [$itemId]) : null;
    if (!$item) {
        http_response_code(404);
        echo json_encode(['ok' => false, 'message' => 'Item not found']);
        exit;
    }
    $res = toggle_wishlist_item((int) $u['id'], $itemId);
    $msg = $res['in_wishlist']
        ? (current_lang() === 'bn' ? 'পছন্দের তালিকায় যুক্ত হয়েছে।' : 'Added to wishlist.')
        : (current_lang() === 'bn' ? 'পছন্দের তালিকা থেকে সরানো হয়েছে।' : 'Removed from wishlist.');
    echo json_encode(['ok' => true, 'in_wishlist' => $res['in_wishlist'], 'count' => $res['user_wishlist_count'], 'message' => $msg]);
    exit;
}

if ($route === '/api/notifications/read' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json; charset=UTF-8');
    $u = current_user();
    if (!$u) {
        http_response_code(401);
        echo json_encode(['ok' => false, 'message' => 'Login required']);
        exit;
    }
    verify_csrf();
    $uid = (int) $u['id'];
    if (($_POST['all'] ?? '') === '1') {
        db_exec('UPDATE notifications SET is_read = 1 WHERE user_id = ? AND is_read = 0', [$uid]);
    } else {
        $nid = (int) ($_POST['id'] ?? 0);
        if ($nid > 0) {
            db_exec('UPDATE notifications SET is_read = 1 WHERE id = ? AND user_id = ?', [$nid, $uid]);
        }
    }
    echo json_encode(['ok' => true, 'unread_count' => unread_notifications_count($uid)]);
    exit;
}

if ($route === '/api/coupon-apply' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json; charset=UTF-8');
    $u = current_user();
    if (!$u) {
        http_response_code(401);
        echo json_encode(['ok' => false, 'message' => 'Login required']);
        exit;
    }
    verify_csrf();
    $itemId = (int) ($_POST['item_id'] ?? 0);
    $code = (string) ($_POST['code'] ?? '');
    $item = $itemId > 0 ? db_one('SELECT id, price, is_free FROM items WHERE id = ? AND is_active = 1 LIMIT 1', [$itemId]) : null;
    if (!$item || (int) $item['is_free'] || (float) $item['price'] <= 0) {
        http_response_code(400);
        echo json_encode(['ok' => false, 'message' => 'Invalid item for coupon']);
        exit;
    }
    $check = validate_coupon_for_checkout($code, (int) $u['id'], $itemId, (float) $item['price']);
    echo json_encode([
        'ok'              => $check['ok'],
        'code'            => $check['code'] ?? '',
        'original_amount' => $check['original_amount'] ?? (float) $item['price'],
        'discount_amount' => $check['discount_amount'] ?? 0.0,
        'final_amount'    => $check['final_amount'] ?? (float) $item['price'],
        'message'         => $check['message'] ?? '',
    ]);
    exit;
}

if ($route === '/api/payment-webhook' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json; charset=UTF-8');
    if (!payment_gateway_enabled()) {
        http_response_code(403);
        echo json_encode(['ok' => false, 'message' => 'Automatic payment gateway is disabled.']);
        exit;
    }
    $rawBody = (string) file_get_contents('php://input');
    $hdrs = function_exists('getallheaders') ? (getallheaders() ?: []) : [];
    foreach (['HTTP_X_BOOKEXA_SIGNATURE' => 'X-Bookexa-Signature', 'HTTP_X_SIGNATURE' => 'X-Signature'] as $sk => $hk) {
        if (empty($hdrs[$hk]) && !empty($_SERVER[$sk])) {
            $hdrs[$hk] = $_SERVER[$sk];
        }
    }
    $driver = payment_gateway_driver(!empty($_GET['driver']) ? (string) $_GET['driver'] : null);
    $ver = $driver->verifyWebhook($rawBody, $hdrs);
    if (!$ver['ok']) {
        http_response_code(403);
        echo json_encode(['ok' => false, 'message' => $ver['message'] ?? 'Invalid webhook signature']);
        exit;
    }
    $ordCode = (string) ($ver['order_code'] ?? '');
    if (str_starts_with($ordCode, 'SHP-')) {
        $sOrd = db_one('SELECT id, status FROM shop_orders WHERE order_code = ? LIMIT 1', [$ordCode]);
        if (!$sOrd) {
            http_response_code(404);
            echo json_encode(['ok' => false, 'message' => 'Order not found']);
            exit;
        }
        $gRef = $ver['gateway_ref'] ?? null;
        if (($ver['status'] ?? '') === 'approved') {
            $res = shop_transition_order_status((int) $sOrd['id'], 'confirmed', null, ['admin_note' => 'Gateway payment verified', 'internal_notes' => 'Ref: ' . (string) $gRef]);
            if ($gRef) {
                db_exec('UPDATE shop_orders SET gateway_ref = ? WHERE id = ?', [(string) $gRef, (int) $sOrd['id']]);
            }
        } else {
            shop_transition_order_status((int) $sOrd['id'], 'cancelled', null, ['admin_note' => 'Gateway reported failed/cancelled']);
        }
        echo json_encode(['ok' => true, 'order_code' => $ordCode]);
        exit;
    }

    $ord = db_one('SELECT id, amount FROM orders WHERE order_code = ? LIMIT 1', [$ordCode]);
    if (!$ord) {
        http_response_code(404);
        echo json_encode(['ok' => false, 'message' => 'Order not found']);
        exit;
    }
    $gRef = $ver['gateway_ref'] ?? null;
    if (($ver['status'] ?? '') === 'approved') {
        $res = approve_order_idempotent((int) $ord['id'], $driver->getCode(), $gRef, null);
    } else {
        $target = ($ver['status'] ?? '') === 'cancelled' ? 'cancelled' : 'rejected';
        $res = apply_order_status_transition((int) $ord['id'], $target, null, 'Gateway reported ' . $target, null, $gRef);
    }
    echo json_encode(['ok' => true, 'already_approved' => !empty($res['already_approved']), 'order_code' => $ver['order_code']]);
    exit;
}

if ($route === '/api/payment-sandbox') {
    $orderCode = trim((string) ($_REQUEST['order'] ?? ''));
    $driverCode = trim((string) ($_REQUEST['driver'] ?? ''));
    if (str_starts_with($orderCode, 'SHP-')) {
        $sOrder = db_one('SELECT * FROM shop_orders WHERE order_code = ? LIMIT 1', [$orderCode]);
        if (!$sOrder) {
            render_error_page(404);
        }
        $driver = payment_gateway_driver($driverCode !== '' ? $driverCode : (string) ($sOrder['gateway_driver'] ?? 'bkash_tokenized'));
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            verify_csrf();
            $simDecision = ($_POST['sim_result'] ?? '') === 'approved' ? 'confirmed' : 'cancelled';
            $ref = 'SBX-' . strtoupper(bin2hex(random_bytes(4)));
            shop_transition_order_status((int) $sOrder['id'], $simDecision, null, ['admin_note' => 'Sandbox gateway ' . $simDecision . ' (' . $ref . ')']);
            if ($simDecision === 'confirmed') {
                db_exec('UPDATE shop_orders SET gateway_ref = ? WHERE id = ?', [$ref, (int) $sOrder['id']]);
                flash('success', current_lang() === 'bn' ? 'পেমেন্ট সফল! শপ অর্ডার নিশ্চিত হয়েছে।' : 'Sandbox payment confirmed!');
                redirect('/shop/order-success/' . rawurlencode($orderCode));
            }
            flash('danger', current_lang() === 'bn' ? 'পেমেন্ট ব্যর্থ হওয়ায় অর্ডার বাতিল হয়েছে।' : 'Sandbox payment failed; order cancelled.');
            redirect('/shop/track?code=' . rawurlencode($orderCode));
        }
        $ts = (string) ($_GET['ts'] ?? '');
        $sig = (string) ($_GET['sig'] ?? '');
        $secret = site_setting('payment_gateway_webhook_secret', '') ?: (site_setting('payment_gateway_api_secret', '') ?: app_secret());
        $amtStr = number_format((float) $sOrder['total_amount'], 2, '.', '');
        $expected = hash_hmac('sha256', "{$orderCode}|{$driver->getCode()}|{$amtStr}|{$ts}", $secret);
        if (!hash_equals($expected, strtolower($sig)) || abs(time() - (int) $ts) > 1800) {
            render_error_page(403);
        }
        echo '<!DOCTYPE html><html lang="en"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Payment Sandbox — Bookexa</title><link rel="stylesheet" href="' . e(asset('assets/css/tokens.css')) . '"><link rel="stylesheet" href="' . e(asset('assets/css/components.css')) . '"></head><body style="padding:36px 14px;"><div class="bx-card" style="max-width:480px;margin:0 auto;">'
            . '<span class="bx-badge bx-badge-accent">SANDBOX SIMULATOR</span>'
            . '<h1 class="bx-h2" style="margin:10px 0 6px;">' . e($driver->getName()) . '</h1>'
            . '<p class="bx-muted">Shop Order <strong>#' . e($orderCode) . '</strong> (' . e(format_bdt((float) $sOrder['total_amount'])) . ')</p>'
            . '<form method="post" action="' . e(url('/api/payment-sandbox?order=' . rawurlencode($orderCode) . '&driver=' . rawurlencode($driver->getCode()))) . '" style="display:flex;gap:10px;flex-wrap:wrap;margin-top:18px;">'
            . csrf_field()
            . '<button type="submit" name="sim_result" value="approved" class="bx-btn bx-btn-primary">Simulate Paid (Confirm)</button>'
            . '<button type="submit" name="sim_result" value="failed" class="bx-btn bx-btn-outline">Simulate Failed (Cancel)</button>'
            . '</form></div></body></html>';
        exit;
    }

    $user = require_login('/orders');
    $orderCode = trim((string) ($_REQUEST['order'] ?? ''));
    $driverCode = trim((string) ($_REQUEST['driver'] ?? ''));
    $order = $orderCode !== '' ? db_one(
        'SELECT o.*, i.title_en, i.slug FROM orders o JOIN items i ON i.id = o.item_id WHERE o.order_code = ? AND o.user_id = ? LIMIT 1',
        [$orderCode, (int) $user['id']]
    ) : null;
    if (!$order) {
        render_error_page(404);
    }
    $driver = payment_gateway_driver($driverCode !== '' ? $driverCode : (string) ($order['gateway_driver'] ?? 'bkash_tokenized'));
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        verify_csrf();
        $simDecision = ($_POST['sim_result'] ?? '') === 'approved' ? 'approved' : 'rejected';
        $ref = 'SBX-' . strtoupper(bin2hex(random_bytes(4)));
        if ($simDecision === 'approved') {
            approve_order_idempotent((int) $order['id'], $driver->getCode(), $ref, null);
            flash('success', current_lang() === 'bn' ? 'স্যান্ডবক্স পেমেন্ট সফল! বইটি আনলক হয়েছে।' : 'Sandbox payment approved! Book unlocked in My Library.');
            redirect('/library');
        }
        apply_order_status_transition((int) $order['id'], 'rejected', null, 'Sandbox payment simulated failure', null, $ref);
        flash('danger', current_lang() === 'bn' ? 'স্যান্ডবক্স পেমেন্ট ব্যর্থ হয়েছে।' : 'Sandbox payment failed.');
        redirect('/orders');
    }
    $ts = (string) ($_GET['ts'] ?? '');
    $sig = (string) ($_GET['sig'] ?? '');
    $secret = site_setting('payment_gateway_webhook_secret', '') ?: (site_setting('payment_gateway_api_secret', '') ?: app_secret());
    $amtStr = number_format((float) $order['amount'], 2, '.', '');
    $expected = hash_hmac('sha256', "{$orderCode}|{$driver->getCode()}|{$amtStr}|{$ts}", $secret);
    if (!hash_equals($expected, strtolower($sig)) || abs(time() - (int) $ts) > 1800) {
        render_error_page(403);
    }
    echo '<!DOCTYPE html><html lang="en"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Payment Sandbox — Bookexa</title><link rel="stylesheet" href="' . e(asset('assets/css/tokens.css')) . '"><link rel="stylesheet" href="' . e(asset('assets/css/components.css')) . '"></head><body style="padding:36px 14px;"><div class="bx-card" style="max-width:480px;margin:0 auto;">'
        . '<span class="bx-badge bx-badge-accent">SANDBOX SIMULATOR</span>'
        . '<h1 class="bx-h2" style="margin:10px 0 6px;">' . e($driver->getName()) . '</h1>'
        . '<p class="bx-muted">Order <strong>#' . e($orderCode) . '</strong> — ' . e($order['title_en']) . ' (' . e(format_bdt((float) $order['amount'])) . ')</p>'
        . '<form method="post" action="' . e(url('/api/payment-sandbox?order=' . rawurlencode($orderCode) . '&driver=' . rawurlencode($driver->getCode()))) . '" style="display:flex;gap:10px;flex-wrap:wrap;margin-top:18px;">'
        . csrf_field()
        . '<button type="submit" name="sim_result" value="approved" class="bx-btn bx-btn-primary">Simulate Paid (Approve)</button>'
        . '<button type="submit" name="sim_result" value="failed" class="bx-btn bx-btn-outline">Simulate Failed (Reject)</button>'
        . '</form></div></body></html>';
    exit;
}

// ----------------------------------------------------------------------------
// Dynamic robots.txt & SEO Sitemap Index / Child Sitemaps
// ----------------------------------------------------------------------------

if ($route === '/api/cart') {
    $buildCartJson = static function (?array $actionResult = null): array {
        $sum = shop_cart_summary();
        $outItems = [];
        foreach ($sum['items'] as $it) {
            $outItems[] = [
                'id'         => (int) $it['id'],
                'slug'       => (string) $it['slug'],
                'title'      => bi($it, 'title'),
                'unit_price' => (float) $it['unit_price'],
                'quantity'   => (int) $it['quantity'],
                'line_total' => (float) $it['line_total'],
                'stock'      => (int) $it['stock'],
                'url'        => url('/shop/' . rawurlencode((string) $it['slug'])),
                'cover_url'  => !empty($it['cover_image']) ? asset((string) $it['cover_image']) : asset('uploads/covers/cover-bangla.svg'),
            ];
        }
        return [
            'ok'              => $actionResult === null ? true : !empty($actionResult['ok']),
            'message'         => $actionResult['message'] ?? '',
            'coupon_ok'       => !empty($sum['coupon']['ok']),
            'coupon_message'  => $sum['coupon']['message'] ?? ($sum['coupon_error'] ?? ($actionResult['message'] ?? '')),
            'items'           => $outItems,
            'item_count'      => (int) $sum['item_count'],
            'subtotal'        => (float) $sum['subtotal'],
            'discount_amount' => (float) $sum['discount_amount'],
            'delivery_fee'    => (float) $sum['delivery_fee'],
            'total_amount'    => (float) $sum['total_amount'],
            'coupon_code'     => (string) $sum['coupon_code'],
        ];
    };
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        verify_csrf();
        $act = (string) ($_POST['action'] ?? '');
        $res = ['ok' => false, 'message' => 'Unknown action'];
        if ($act === 'add') {
            $res = shop_cart_add((int) ($_POST['product_id'] ?? 0), max(1, (int) ($_POST['quantity'] ?? 1)));
        } elseif ($act === 'update' || $act === 'remove') {
            $qty = $act === 'remove' ? 0 : (int) ($_POST['quantity'] ?? 0);
            $res = shop_cart_set_qty((int) ($_POST['product_id'] ?? 0), $qty);
        } elseif ($act === 'shipping') {
            shop_cart_set_shipping((string) ($_POST['delivery_area'] ?? 'inside_dhaka'), (string) ($_POST['district'] ?? 'Dhaka'));
            $res = ['ok' => true, 'message' => 'Shipping updated'];
        } elseif ($act === 'coupon') {
            $res = shop_cart_apply_coupon((string) ($_POST['code'] ?? ''));
        }
        json_response($buildCartJson($res), !empty($res['ok']) ? 200 : 400);
    }
    json_response($buildCartJson(null));
}

if ($route === '/api/shop-wishlist-toggle') {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        json_response(['ok' => false, 'message' => 'Method not allowed'], 405);
    }
    verify_csrf();
    $u = current_user();
    if (!$u) {
        json_response(['ok' => false, 'message' => current_lang() === 'bn' ? 'অনুগ্রহ করে প্রথমে লগইন করুন।' : 'Please log in first.'], 401);
    }
    $pid = max(0, (int) ($_POST['product_id'] ?? 0));
    $p = $pid > 0 ? db_one('SELECT id FROM products WHERE id = ? AND is_active = 1 LIMIT 1', [$pid]) : null;
    if (!$p) {
        json_response(['ok' => false, 'message' => 'Product not found.'], 404);
    }
    $uid = (int) $u['id'];
    $exists = db_one('SELECT id FROM product_wishlist WHERE user_id = ? AND product_id = ? LIMIT 1', [$uid, $pid]);
    if ($exists) {
        db_exec('DELETE FROM product_wishlist WHERE id = ?', [(int) $exists['id']]);
        json_response(['ok' => true, 'wished' => false, 'message' => current_lang() === 'bn' ? 'পছন্দের তালিকা থেকে সরানো হয়েছে।' : 'Removed from wishlist.']);
    }
    db_exec('INSERT INTO product_wishlist (user_id, product_id) VALUES (?, ?)', [$uid, $pid]);
    json_response(['ok' => true, 'wished' => true, 'message' => current_lang() === 'bn' ? 'পছন্দের তালিকায় যুক্ত হয়েছে!' : 'Added to wishlist!']);
}

// ----------------------------------------------------------------------------
// Privacy-Friendly First-Party Analytics Ping, Double Opt-In Newsletter & Folder Bell
// ----------------------------------------------------------------------------
if ($route === '/api/analytics-ping' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $raw = file_get_contents('php://input') ?: '';
    $payload = $_POST;
    if (!$payload && $raw !== '') {
        $dec = json_decode($raw, true);
        if (is_array($dec)) {
            $payload = $dec;
        }
    }
    $path = (string) ($payload['path'] ?? '/');
    $evType = (string) ($payload['event'] ?? 'pageview');
    if (!in_array($evType, ['pageview', 'share'], true)) {
        $evType = 'pageview';
    }
    $refHost = '';
    if (!empty($payload['ref'])) {
        $refHost = growth_clean_referrer_host((string) $payload['ref']);
    }
    $ok = growth_record_analytics_event($evType, $path, [
        'lang'          => (string) ($payload['lang'] ?? current_lang()),
        'referrer_host' => $refHost,
        'load_ms'       => (int) ($payload['load_ms'] ?? 0),
    ]);
    header('Content-Type: application/json; charset=UTF-8');
    echo json_encode(['ok' => $ok]);
    exit;
}

if ($route === '/api/newsletter/subscribe' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $email = (string) ($_POST['email'] ?? '');
    $srcPath = (string) ($_POST['source_path'] ?? '/');
    $res = growth_newsletter_subscribe($email, current_lang(), $srcPath);
    json_response($res, !empty($res['ok']) ? 200 : 400);
}

if ($route === '/api/folder-subscribe' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $u = current_user();
    if (!$u) {
        json_response(['ok' => false, 'message' => current_lang() === 'bn' ? 'অনুগ্রহ করে প্রথমে লগইন করুন।' : 'Please login first.'], 401);
    }
    $folderId = max(0, (int) ($_POST['folder_id'] ?? 0));
    $folder = $folderId > 0 ? db_one('SELECT id FROM folders WHERE id = ? AND is_active = 1 LIMIT 1', [$folderId]) : null;
    if (!$folder) {
        json_response(['ok' => false, 'message' => 'Folder not found.'], 404);
    }
    $res = growth_folder_subscription_toggle((int) $u['id'], $folderId);
    $res['message'] = $res['subscribed']
        ? (current_lang() === 'bn' ? 'এই ফোল্ডারে নতুন বই যোগ হলে আপনাকে জানানো হবে।' : 'Subscribed! You will be notified of new books in this folder.')
        : (current_lang() === 'bn' ? 'এই ফোল্ডারের নোটিফিকেশন বন্ধ করা হয়েছে।' : 'Unsubscribed from this folder.');
    json_response($res);
}


