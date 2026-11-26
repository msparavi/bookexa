<?php
declare(strict_types=1);

$rawPathCheck = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
$isInstallUri = preg_match('#/install$#', $rawPathCheck) === 1;
$hasConfig = is_file(__DIR__ . '/config/config.php');
$isLocked  = is_file(__DIR__ . '/config/installed.lock');
if (!$hasConfig || (!$isLocked && $isInstallUri)) {
    define('APP_ROOT', __DIR__);
    require APP_ROOT . '/pages/install.php';
    exit;
}

require_once __DIR__ . '/includes/bootstrap.php';
require_once APP_ROOT . '/includes/components.php';

$rawUri = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
if (BASE_PATH !== '' && str_starts_with($rawUri, BASE_PATH)) {
    $rawUri = substr($rawUri, strlen(BASE_PATH));
}
$route = '/' . trim($rawUri, '/');
if ($route === '') {
    $route = '/';
}

if ($route === '/install') {
    render_error_page(403, current_lang() === 'bn' ? 'ইনস্টলার লক করা আছে (৪০৩)' : 'Installer Locked (403)', current_lang() === 'bn' ? 'Bookexa ইতিমধ্যে ইনস্টল করা হয়েছে।' : 'This Bookexa installation is already configured and locked.');
}

function render_public(string $viewFile, array $vars = []): never
{
    extract($vars, EXTR_SKIP);
    require APP_ROOT . '/includes/header.php';
    require APP_ROOT . '/pages/' . $viewFile;
    require APP_ROOT . '/includes/footer.php';
    exit;
}

// ----------------------------------------------------------------------------
// Per-IP Rate Limit across all Public API Routes (/api/*)
// ----------------------------------------------------------------------------
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
    $ord = db_one('SELECT id, amount FROM orders WHERE order_code = ? LIMIT 1', [(string) $ver['order_code']]);
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
    $secret = site_setting('payment_gateway_webhook_secret', '') ?: (site_setting('payment_gateway_api_secret', '') ?: (string) app_config('security.app_secret', 'bookexa_gw_fallback'));
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
// SEO Sitemap.xml (1-hour file cache + ETag / Last-Modified)
// ----------------------------------------------------------------------------
if ($route === '/sitemap.xml') {
    $cacheDir = ensure_bookexa_cache_dir();
    $smFile = $cacheDir . '/sitemap.xml';
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
        $today = date('Y-m-d');
        $urlMap = [
            BASE_URL . '/'              => $today,
            BASE_URL . '/pdf'           => $today,
            BASE_URL . '/about'         => $today,
            BASE_URL . '/contact'       => $today,
            BASE_URL . '/terms'         => $today,
            BASE_URL . '/privacy'       => $today,
            BASE_URL . '/refund-policy' => $today,
            BASE_URL . '/copyright'     => $today,
        ];
        if (site_setting('shop_enabled', '0') === '1') {
            $urlMap[BASE_URL . '/shop'] = $today;
        }
        $tree = get_cached_folder_tree();
        foreach ($tree['byId'] ?? [] as $f) {
            $trail = folder_breadcrumbs((int) $f['id']);
            if (!empty($trail)) {
                $u = BASE_URL . '/pdf/' . implode('/', array_map(fn($c) => rawurlencode($c['slug']), $trail));
                $ts = strtotime((string) ($f['updated_at'] ?? $f['created_at'] ?? 'now')) ?: time();
                $urlMap[$u] = date('Y-m-d', $ts);
            }
        }
        foreach (db_all('SELECT slug, updated_at, created_at FROM items WHERE is_active = 1 AND ' . published_window_sql()) as $it) {
            $u = BASE_URL . '/book/' . rawurlencode($it['slug']);
            $ts = strtotime((string) ($it['updated_at'] ?? $it['created_at'] ?? 'now')) ?: time();
            $urlMap[$u] = date('Y-m-d', $ts);
        }
        $xml = '<?xml version="1.0" encoding="UTF-8"?>' . "\n"
            . '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";
        foreach ($urlMap as $u => $lastmod) {
            $xml .= '  <url><loc>' . htmlspecialchars($u, ENT_XML1, 'UTF-8') . '</loc><lastmod>' . e($lastmod) . "</lastmod></url>\n";
        }
        $xml .= '</urlset>';
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

// ----------------------------------------------------------------------------
// Protected Debug & Design-System Routes (Admin Only — 403 for public)
// ----------------------------------------------------------------------------
if ($route === '/debug' || str_starts_with($route, '/debug/')) {
    header('X-Robots-Tag: noindex, nofollow, noarchive');
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
    if (!current_admin()) {
        render_error_page(403);
    }
    if ($route === '/debug/overflow.html') {
        require APP_ROOT . '/pages/debug/overflow.php';
        exit;
    }
    render_error_page(404);
}

// ----------------------------------------------------------------------------
// Admin Routes
// ----------------------------------------------------------------------------
if ($route === '/admin' || str_starts_with($route, '/admin/')) {
    require_once APP_ROOT . '/includes/admin_controller.php';
    handle_admin_routes($route);
}

// Maintenance Mode Check (public storefront only; admins bypass when logged in)
if (site_setting('maintenance_mode', '0') === '1' && !current_admin()) {
    render_error_page(503);
}

// ----------------------------------------------------------------------------
// User Auth: Login, Register, Logout, Password Reset
// ----------------------------------------------------------------------------
if ($route === '/login') {
    if (is_logged_in()) {
        redirect('/library');
    }
    $error = null;
    $info = null;
    $authMode = 'login';
    $devices = [];
    $otpPhone = '';
    $otpStep = 'phone';
    $next = (string) ($_REQUEST['next'] ?? '');
    $redirectAfterLogin = static function () use ($next): void {
        if ($next !== '' && str_starts_with($next, '/') && !str_starts_with($next, '//')) {
            $target = (BASE_PATH !== '' && str_starts_with($next, BASE_PATH . '/')) ? $next : url($next);
            header('Location: ' . $target);
            exit;
        }
        redirect('/library');
    };

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        verify_csrf();
        $act = (string) ($_POST['action'] ?? 'password_login');
        if ($act === 'replace_device') {
            $pending = $_SESSION['pending_device_auth'] ?? null;
            $devId = (int) ($_POST['device_id'] ?? 0);
            if (!is_array($pending) || empty($pending['user_id']) || (int) ($pending['expires_at'] ?? 0) < time()) {
                unset($_SESSION['pending_device_auth']);
                $error = current_lang() === 'bn' ? 'সেশনের মেয়াদ শেষ। আবার লগইন করুন।' : 'Verification session expired. Please sign in again.';
            } else {
                $pendingUser = db_one('SELECT * FROM users WHERE id = ? AND is_active = 1 LIMIT 1', [(int) $pending['user_id']]);
                if (!$pendingUser || $devId <= 0) {
                    $error = current_lang() === 'bn' ? 'ডিভাইস পাওয়া যায়নি।' : 'Invalid device selection.';
                } else {
                    unset($_SESSION['pending_device_auth']);
                    login_user_session($pendingUser, $devId);
                    $redirectAfterLogin();
                }
            }
        } elseif ($act === 'send_login_otp') {
            $authMode = 'otp';
            if (!sms_gateway_enabled()) {
                $error = current_lang() === 'bn' ? 'SMS OTP লগইন বর্তমানে বন্ধ আছে।' : 'SMS OTP login is currently disabled.';
            } else {
                $rawPhone = trim((string) ($_POST['phone'] ?? ''));
                $normPhone = normalize_bd_phone($rawPhone);
                $otpPhone = $normPhone !== '' ? $normPhone : $rawPhone;
                $u = find_user_by_identifier($otpPhone);
                if (!$u || !(int) $u['is_active']) {
                    $error = current_lang() === 'bn' ? 'এই মোবাইল নম্বরে কোনো সক্রিয় অ্যাকাউন্ট পাওয়া যায়নি।' : 'No active account found with that phone number.';
                } elseif (!user_rate_limit_check('otp_req:ip:' . client_ip(), 5, 600)) {
                    $error = current_lang() === 'bn' ? 'অনেকবার OTP চাওয়া হয়েছে। ১০ মিনিট পর চেষ্টা করুন।' : 'Too many OTP requests. Please wait 10 minutes.';
                } else {
                    $otp = issue_otp_verification((int) $u['id'], 'phone_login', $otpPhone);
                    $smsRes = sms_gateway_send($otpPhone, "Your Bookexa login OTP is {$otp}. Valid for 10 minutes.");
                    if (empty($smsRes['ok'])) {
                        $error = $smsRes['error'] ?? $smsRes['message'] ?? 'Failed to send OTP.';
                    } else {
                        $otpStep = 'code';
                        $isSandbox = (site_setting('sms_gateway_sandbox', '1') === '1' || app_config('app_env') === 'local');
                        $info = current_lang() === 'bn'
                            ? 'আপনার নম্বরে ৬ অঙ্কের OTP পাঠানো হয়েছে।'
                            : 'A 6-digit OTP has been sent to your phone.';
                        if ($isSandbox) {
                            $info .= ' (Sandbox OTP: <strong>' . e($otp) . '</strong>)';
                        }
                    }
                }
            }
        } elseif ($act === 'verify_login_otp') {
            $authMode = 'otp';
            $otpStep = 'code';
            $rawPhone = trim((string) ($_POST['phone'] ?? ''));
            $normPhone = normalize_bd_phone($rawPhone);
            $otpPhone = $normPhone !== '' ? $normPhone : $rawPhone;
            $code = trim((string) ($_POST['otp_code'] ?? ''));
            $u = find_user_by_identifier($otpPhone);
            $vOtp = ($u && (int) $u['is_active']) ? verify_otp_code('phone_login', $otpPhone, $code) : ['ok' => false];
            if (!$u || !(int) $u['is_active'] || empty($vOtp['ok'])) {
                $error = (string) ($vOtp['message'] ?? (current_lang() === 'bn' ? 'OTP কোডটি সঠিক নয় বা মেয়াদ শেষ।' : 'Invalid or expired OTP code.'));
            } else {
                $limitHit = check_device_limit_or_prepare($u);
                if ($limitHit !== null) {
                    $_SESSION['pending_device_auth'] = ['user_id' => (int) $u['id'], 'expires_at' => time() + 300];
                    $authMode = 'device_limit';
                    $devices = $limitHit['devices'];
                    $error = $limitHit['error'];
                } else {
                    login_user_session($u);
                    $redirectAfterLogin();
                }
            }
        } else {
            $res = attempt_user_login((string) ($_POST['identifier'] ?? ''), (string) ($_POST['password'] ?? ''));
            if ($res['ok']) {
                $redirectAfterLogin();
            }
            if (($res['code'] ?? '') === 'DEVICE_LIMIT') {
                $_SESSION['pending_device_auth'] = ['user_id' => (int) $res['user']['id'], 'expires_at' => time() + 300];
                $authMode = 'device_limit';
                $devices = $res['devices'];
            }
            $error = $res['error'];
        }
    } elseif (isset($_GET['mode']) && $_GET['mode'] === 'otp' && sms_gateway_enabled()) {
        $authMode = 'otp';
    }
    render_public('auth.php', [
        'authMode'  => $authMode,
        'error'     => $error,
        'info'      => $info,
        'next'      => $next,
        'devices'   => $devices,
        'otpPhone'  => $otpPhone,
        'otpStep'   => $otpStep,
        'pageTitle' => t('nav_login'),
        'activeNav' => 'account',
    ]);
}

if ($route === '/register') {
    if (is_logged_in()) {
        redirect('/library');
    }
    $error = null;
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        verify_csrf();
        $res = register_user_account(
            (string) ($_POST['name'] ?? ''),
            (string) ($_POST['email'] ?? ''),
            (string) ($_POST['phone'] ?? ''),
            (string) ($_POST['password'] ?? '')
        );
        if ($res['ok']) {
            flash('success', current_lang() === 'bn' ? 'স্বাগতম! আপনার অ্যাকাউন্ট তৈরি হয়েছে।' : 'Welcome! Your Bookexa account is ready.');
            redirect('/library');
        }
        $error = $res['error'];
    }
    render_public('auth.php', ['authMode' => 'register', 'error' => $error, 'pageTitle' => 'Register', 'activeNav' => 'account']);
}

if ($route === '/logout' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    logout_user_only();
    flash('success', current_lang() === 'bn' ? 'সফলভাবে লগআউট হয়েছেন।' : 'Logged out safely.');
    redirect('/');
}

if ($route === '/forgot-password') {
    $error = null;
    $info = null;
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        verify_csrf();
        $id = trim((string) ($_POST['identifier'] ?? ''));
        $u = db_one('SELECT id, email FROM users WHERE (email = ? OR phone = ?) AND is_active = 1 LIMIT 1', [strtolower($id), $id]);
        if ($u) {
            $rawTok = bin2hex(random_bytes(32));
            $hash = hash('sha256', $rawTok);
            $exp = date('Y-m-d H:i:s', time() + 1800);
            db_exec('INSERT INTO password_resets (user_id, token_hash, expires_at) VALUES (?, ?, ?)', [(int) $u['id'], $hash, $exp]);
            $resetLink = BASE_URL . '/reset-password?token=' . $rawTok;
            send_bookexa_template_mail(
                (string) $u['email'],
                site_setting('site_name', 'Bookexa') . ' — Password Reset',
                'Reset Your Password',
                '<p>We received a request to reset your Bookexa account password. This link is valid for 30 minutes:</p>',
                $resetLink,
                'Reset Password'
            );
            if (app_config('app_env') === 'local') {
                $info = 'Reset link (local dev preview): <a href="' . e($resetLink) . '" style="text-decoration:underline;font-weight:700;">' . e($resetLink) . '</a>';
            } else {
                $info = current_lang() === 'bn' ? 'পাসওয়ার্ড রিসেট নির্দেশনা পাঠানো হয়েছে।' : 'If an account matches, reset instructions have been sent.';
            }
        } else {
            $info = current_lang() === 'bn' ? 'যদি অ্যাকাউন্ট থেকে থাকে তবে রিসেট নির্দেশনা পাঠানো হয়েছে।' : 'If an account matches, reset instructions have been sent.';
        }
    }
    render_public('auth.php', ['authMode' => 'forgot', 'error' => $error, 'info' => $info, 'pageTitle' => 'Reset Password', 'activeNav' => 'account']);
}

if ($route === '/reset-password') {
    $token = trim((string) ($_REQUEST['token'] ?? ''));
    $error = null;
    $row = ($token !== '' && preg_match('/^[a-f0-9]{64}$/', $token))
        ? db_one('SELECT * FROM password_resets WHERE token_hash = ? AND used_at IS NULL LIMIT 1', [hash('sha256', $token)])
        : null;

    if (!$row || strtotime($row['expires_at']) < time()) {
        $error = current_lang() === 'bn' ? 'রিসেট লিংকটি অবৈধ অথবা মেয়াদ শেষ হয়েছে।' : 'This reset link is invalid or expired.';
        render_public('auth.php', ['authMode' => 'forgot', 'error' => $error, 'info' => null, 'pageTitle' => 'Reset Password']);
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        verify_csrf();
        $newPw = (string) ($_POST['password'] ?? '');
        if (strlen($newPw) < 6) {
            $error = current_lang() === 'bn' ? 'পাসওয়ার্ড কমপক্ষে ৬ অক্ষরের হতে হবে।' : 'Password must be at least 6 characters.';
        } else {
            $hash = password_hash($newPw, PASSWORD_BCRYPT);
            db_exec('UPDATE users SET password_hash = ?, active_session_token = NULL WHERE id = ?', [$hash, (int) $row['user_id']]);
            db_exec('UPDATE password_resets SET used_at = NOW() WHERE id = ?', [(int) $row['id']]);
            flash('success', current_lang() === 'bn' ? 'পাসওয়ার্ড আপডেট হয়েছে। এখন লগইন করুন।' : 'Password updated! Please log in.');
            redirect('/login');
        }
    }
    render_public('auth.php', ['authMode' => 'reset', 'error' => $error, 'resetToken' => $token, 'pageTitle' => 'Set New Password', 'activeNav' => 'account']);
}

// ----------------------------------------------------------------------------
// User Area: Profile, My Library, My Orders
// ----------------------------------------------------------------------------
if ($route === '/devices') {
    redirect('/profile#my-devices');
}

if ($route === '/profile') {
    $user = require_login('/profile');
    $uid = (int) $user['id'];
    $error = null;
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        verify_csrf();
        $act = (string) ($_POST['action'] ?? '');
        if ($act === 'update_profile') {
            $name = trim((string) ($_POST['name'] ?? ''));
            if ($name === '') {
                $error = current_lang() === 'bn' ? 'নাম দেওয়া আবশ্যক।' : 'Name is required.';
            } else {
                db_exec('UPDATE users SET name = ? WHERE id = ?', [$name, $uid]);
                flash('success', current_lang() === 'bn' ? 'প্রোফাইল নাম আপডেট হয়েছে।' : 'Profile name updated.');
                redirect('/profile');
            }
        } elseif ($act === 'request_contact_verify') {
            $kind = ($_POST['contact_type'] ?? 'email') === 'phone' ? 'phone' : 'email';
            $rawVal = trim((string) ($_POST['new_value'] ?? ''));
            if (!user_rate_limit_check('otp_prof:u:' . $uid, 5, 600)) {
                $error = current_lang() === 'bn' ? 'অনেক দ্রুত চেষ্টা করছেন। কিছুক্ষণ পর আবার চেষ্টা করুন।' : 'Too many verification requests. Please wait 10 minutes.';
            } elseif ($kind === 'email') {
                $val = strtolower($rawVal);
                if (!filter_var($val, FILTER_VALIDATE_EMAIL)) {
                    $error = current_lang() === 'bn' ? 'সঠিক ইমেইল ঠিকানা দিন।' : 'Enter a valid email address.';
                } elseif (db_one('SELECT id FROM users WHERE email = ? AND id <> ? LIMIT 1', [$val, $uid])) {
                    $error = current_lang() === 'bn' ? 'এই ইমেইলটি অন্য অ্যাকাউন্টে ব্যবহৃত হচ্ছে।' : 'That email is already in use.';
                } else {
                    $otp = issue_otp_verification($uid, 'change_email', $val);
                    send_bookexa_template_mail(
                        $val,
                        site_setting('site_name', 'Bookexa') . ' — Verify Your New Email',
                        'Email Change Verification',
                        '<p>Use the 6-digit verification code below to confirm changing your account email (valid 10 minutes):</p>'
                        . '<p style="font-size:24px;font-weight:700;letter-spacing:4px;">' . e($otp) . '</p>'
                    );
                    $_SESSION['pending_contact_change'] = ['type' => 'email', 'target' => $val, 'exp' => time() + 600];
                    $msg = current_lang() === 'bn' ? 'আপনার নতুন ইমেইলে ৬ অঙ্কের ভেরিফিকেশন কোড পাঠানো হয়েছে।' : 'Verification code sent to your new email.';
                    if (app_config('app_env') === 'local' && empty(site_setting('smtp_host', ''))) {
                        $msg .= ' (Local OTP: ' . $otp . ')';
                    }
                    flash('info', $msg);
                    redirect('/profile#contact-verify');
                }
            } else {
                $norm = normalize_bd_phone($rawVal);
                $val = $norm !== '' ? $norm : $rawVal;
                if ($val === '' || strlen(preg_replace('/\D/', '', $val)) < 8) {
                    $error = current_lang() === 'bn' ? 'সঠিক মোবাইল নম্বর দিন।' : 'Enter a valid phone number.';
                } elseif (db_one('SELECT id FROM users WHERE (phone = ? OR phone = ?) AND id <> ? LIMIT 1', [$val, $rawVal, $uid])) {
                    $error = current_lang() === 'bn' ? 'এই মোবাইল নম্বরটি অন্য অ্যাকাউন্টে ব্যবহৃত হচ্ছে।' : 'That phone number is already in use.';
                } else {
                    $otp = issue_otp_verification($uid, 'change_phone', $val);
                    if (sms_gateway_enabled()) {
                        sms_gateway_send($val, "Bookexa phone verification OTP: {$otp}");
                    }
                    $_SESSION['pending_contact_change'] = ['type' => 'phone', 'target' => $val, 'exp' => time() + 600];
                    $msg = current_lang() === 'bn' ? 'ভেরিফিকেশন কোড তৈরি হয়েছে। নিচে কোডটি লিখুন।' : 'Phone verification code generated. Enter it below to confirm.';
                    if (app_config('app_env') === 'local' || !sms_gateway_enabled() || site_setting('sms_gateway_sandbox', '1') === '1') {
                        $msg .= ' (OTP: ' . $otp . ')';
                    }
                    flash('info', $msg);
                    redirect('/profile#contact-verify');
                }
            }
        } elseif ($act === 'confirm_contact_verify') {
            $pending = $_SESSION['pending_contact_change'] ?? null;
            $code = trim((string) ($_POST['otp_code'] ?? ''));
            if (!is_array($pending) || (int) ($pending['exp'] ?? 0) < time()) {
                unset($_SESSION['pending_contact_change']);
                $error = current_lang() === 'bn' ? 'ভেরিফিকেশন মেয়াদ শেষ। আবার কোড পাঠান।' : 'Verification request expired. Please request a new code.';
            } else {
                $kind = $pending['type'] === 'phone' ? 'change_phone' : 'change_email';
                $target = (string) $pending['target'];
                $vRes = verify_otp_code($kind, $target, $code);
                if (empty($vRes['ok'])) {
                    $error = (string) ($vRes['message'] ?? (current_lang() === 'bn' ? 'ভেরিফিকেশন কোড সঠিক নয়।' : 'Invalid verification code.'));
                } else {
                    if ($pending['type'] === 'email') {
                        db_exec('UPDATE users SET email = ? WHERE id = ?', [$target, $uid]);
                        flash('success', current_lang() === 'bn' ? 'ইমেইল সফলভাবে যাচাই ও আপডেট হয়েছে।' : 'Email verified and updated.');
                    } else {
                        db_exec('UPDATE users SET phone = ? WHERE id = ?', [$target, $uid]);
                        flash('success', current_lang() === 'bn' ? 'মোবাইল নম্বর সফলভাবে যাচাই ও আপডেট হয়েছে।' : 'Phone number verified and updated.');
                    }
                    unset($_SESSION['pending_contact_change']);
                    redirect('/profile#contact-verify');
                }
            }
        } elseif ($act === 'revoke_device') {
            $devId = (int) ($_POST['device_id'] ?? 0);
            if ($devId > 0 && revoke_user_device($uid, $devId)) {
                flash('success', current_lang() === 'bn' ? 'ডিভাইসটি সাইন আউট করা হয়েছে।' : 'Device signed out successfully.');
            }
            redirect('/profile#my-devices');
        } elseif ($act === 'download_my_data') {
            $export = [
                'exported_at'      => date('c'),
                'profile'          => db_one('SELECT id, name, email, phone, reader_theme, reader_zoom, created_at FROM users WHERE id = ?', [$uid]),
                'devices'          => db_all('SELECT device_label, ip_address, last_active_at, created_at FROM user_devices WHERE user_id = ? ORDER BY last_active_at DESC', [$uid]),
                'library'          => db_all('SELECT i.slug, i.title_en, i.title_bn, l.granted_at FROM library l JOIN items i ON i.id = l.item_id WHERE l.user_id = ?', [$uid]),
                'wishlist'         => db_all('SELECT i.slug, i.title_en, i.title_bn, w.created_at FROM wishlist w JOIN items i ON i.id = w.item_id WHERE w.user_id = ?', [$uid]),
                'orders'           => db_all('SELECT order_code, amount, discount_amount, coupon_code, payment_method, status, created_at FROM orders WHERE user_id = ? ORDER BY id DESC', [$uid]),
                'reviews'          => db_all('SELECT i.slug, r.rating, r.comment, r.status, r.created_at FROM reviews r JOIN items i ON i.id = r.item_id WHERE r.user_id = ?', [$uid]),
                'reading_progress' => db_all('SELECT i.slug, rp.last_page, rp.total_pages, rp.time_spent_sec, rp.is_finished, rp.updated_at FROM reading_progress rp JOIN items i ON i.id = rp.item_id WHERE rp.user_id = ?', [$uid]),
                'bookmarks'        => db_all('SELECT i.slug, b.page_num, b.label, b.created_at FROM reader_bookmarks b JOIN items i ON i.id = b.item_id WHERE b.user_id = ? ORDER BY b.id DESC', [$uid]),
            ];
            header('Content-Type: application/json; charset=UTF-8');
            header('Content-Disposition: attachment; filename="bookexa-data-user-' . $uid . '-' . date('Ymd') . '.json"');
            echo json_encode($export, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
            exit;
        } elseif ($act === 'delete_account') {
            $curr = (string) ($_POST['current_password'] ?? '');
            $confirmWord = strtoupper(trim((string) ($_POST['confirm_delete'] ?? '')));
            $fullUser = db_one('SELECT password_hash FROM users WHERE id = ?', [$uid]);
            if (!$fullUser || !password_verify($curr, $fullUser['password_hash'])) {
                $error = current_lang() === 'bn' ? 'সঠিক বর্তমান পাসওয়ার্ড দিন।' : 'Current password is incorrect.';
            } elseif ($confirmWord !== 'DELETE') {
                $error = current_lang() === 'bn' ? 'নিশ্চিত করতে DELETE লিখুন।' : 'Please type DELETE to confirm account deletion.';
            } else {
                $pdo = db();
                $pdo->beginTransaction();
                try {
                    foreach ([
                        'DELETE FROM user_devices WHERE user_id = ?',
                        'DELETE FROM reader_bookmarks WHERE user_id = ?',
                        'DELETE FROM recently_viewed WHERE user_id = ?',
                        'DELETE FROM reading_progress WHERE user_id = ?',
                        'DELETE FROM library WHERE user_id = ?',
                        'DELETE FROM wishlist WHERE user_id = ?',
                        'DELETE FROM reviews WHERE user_id = ?',
                        'DELETE FROM notifications WHERE user_id = ?',
                        'DELETE FROM otp_verifications WHERE user_id = ?',
                        'DELETE FROM password_resets WHERE user_id = ?',
                        'DELETE FROM orders WHERE user_id = ?',
                        'DELETE FROM users WHERE id = ?',
                    ] as $delSql) {
                        db_exec($delSql, [$uid]);
                    }
                    $pdo->commit();
                } catch (Throwable $e) {
                    if ($pdo->inTransaction()) {
                        $pdo->rollBack();
                    }
                    throw $e;
                }
                $_SESSION = [];
                if (session_status() === PHP_SESSION_ACTIVE && PHP_SAPI !== 'cli') {
                    session_destroy();
                    session_start();
                }
                flash('info', current_lang() === 'bn' ? 'আপনার অ্যাকাউন্ট স্থায়ীভাবে মুছে ফেলা হয়েছে।' : 'Your account and personal data have been permanently deleted.');
                redirect('/');
            }
        } elseif ($act === 'change_password') {
            $curr = (string) ($_POST['current_password'] ?? '');
            $nextPw = (string) ($_POST['new_password'] ?? '');
            $fullUser = db_one('SELECT password_hash FROM users WHERE id = ?', [$uid]);
            if (!$fullUser || !password_verify($curr, $fullUser['password_hash'])) {
                $error = current_lang() === 'bn' ? 'বর্তমান পাসওয়ার্ড সঠিক নয়।' : 'Current password is incorrect.';
            } elseif (strlen($nextPw) < 6) {
                $error = current_lang() === 'bn' ? 'নতুন পাসওয়ার্ড কমপক্ষে ৬ অক্ষরের হতে হবে।' : 'New password must be at least 6 characters.';
            } else {
                db_exec('UPDATE users SET password_hash = ? WHERE id = ?', [password_hash($nextPw, PASSWORD_BCRYPT), $uid]);
                flash('success', current_lang() === 'bn' ? 'পাসওয়ার্ড পরিবর্তিত হয়েছে।' : 'Password changed successfully.');
                redirect('/profile');
            }
        }
    }
    $devices = list_user_devices($uid);
    render_public('profile.php', [
        'user'             => $user,
        'error'            => $error,
        'devices'          => $devices,
        'currentDevToken'  => (string) ($_SESSION['device_token'] ?? ''),
        'pendingContact'   => $_SESSION['pending_contact_change'] ?? null,
        'pageTitle'        => 'Profile',
        'activeNav'        => 'account',
    ]);
}

if ($route === '/library') {
    $user = require_login('/library');
    $uid = (int) $user['id'];
    require_once APP_ROOT . '/includes/pdf_proxy.php';
    $filters = browse_extract_filters($_GET);
    $res = browse_query_items(['mode' => 'library', 'user_id' => $uid], $filters);
    $readingStats = user_reading_stats($uid);
    render_public('library.php', [
        'user'         => $user,
        'libraryItems' => $res['items'],
        'filters'      => $filters,
        'page'         => $res['page'],
        'totalPages'   => $res['totalPages'],
        'totalItems'   => $res['total'],
        'readingStats' => $readingStats,
        'pageTitle'    => t('nav_library'),
        'activeNav'    => 'library',
    ]);
}

if (preg_match('#^/orders/([^/]+)/invoice$#', $route, $m)) {
    $orderCode = rawurldecode($m[1]);
    $adm = current_admin();
    $usr = $adm ? null : require_login('/orders/' . rawurlencode($orderCode) . '/invoice');
    $order = db_one(
        'SELECT o.*, u.name AS user_name, u.email AS user_email, u.phone AS user_phone,
                i.title_bn, i.title_en, i.slug
         FROM orders o
         JOIN users u ON u.id = o.user_id
         JOIN items i ON i.id = o.item_id
         WHERE o.order_code = ? LIMIT 1',
        [$orderCode]
    );
    if (!$order) {
        render_error_page(404);
    }
    if (!$adm && (int) $order['user_id'] !== (int) $usr['id']) {
        render_error_page(403);
    }
    header('X-Robots-Tag: noindex, nofollow');
    require APP_ROOT . '/pages/invoice.php';
    exit;
}

if ($route === '/orders') {
    $user = require_login('/orders');
    $uid  = (int) $user['id'];

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        verify_csrf();
        $act = (string) ($_POST['action'] ?? '');
        $oid = (int) ($_POST['order_id'] ?? 0);
        $ownOrder = $oid > 0 ? db_one('SELECT * FROM orders WHERE id = ? AND user_id = ? LIMIT 1', [$oid, $uid]) : null;

        if (!$ownOrder) {
            flash('danger', current_lang() === 'bn' ? 'অর্ডার খুঁজে পাওয়া যায়নি।' : 'Order not found.');
            redirect('/orders');
        }

        if ($act === 'cancel_order') {
            if ($ownOrder['status'] !== 'pending') {
                flash('warning', current_lang() === 'bn' ? 'শুধুমাত্র অপেক্ষমাণ অর্ডার বাতিল করা যায়।' : 'Only pending orders can be cancelled.');
                redirect('/orders');
            }
            apply_order_status_transition($oid, 'cancelled', null, 'Cancelled by customer');
            flash('success', current_lang() === 'bn' ? 'অর্ডার #' . $ownOrder['order_code'] . ' বাতিল হয়েছে।' : 'Order #' . $ownOrder['order_code'] . ' cancelled.');
            redirect('/orders');
        }

        if ($act === 'resubmit_order') {
            if ($ownOrder['status'] !== 'rejected' || (float) $ownOrder['amount'] <= 0) {
                flash('danger', current_lang() === 'bn' ? 'এই অর্ডারটি পুনরায় জমা দেওয়ার উপযুক্ত নয়।' : 'Only rejected paid orders can be fixed and resubmitted.');
                redirect('/orders');
            }
            $method = (string) ($_POST['payment_method'] ?? 'bkash');
            if (!in_array($method, ['bkash', 'nagad', 'rocket'], true)) {
                $method = 'bkash';
            }
            $sender = trim((string) ($_POST['sender_number'] ?? ''));
            $trxId  = normalize_transaction_id((string) ($_POST['transaction_id'] ?? ''));
            if ($sender === '' || $trxId === '' || str_starts_with($trxId, 'COUPON')) {
                flash('danger', current_lang() === 'bn' ? 'সঠিক প্রেরক নম্বর এবং Transaction ID দিন।' : 'Valid sender number and Transaction ID are required.');
                redirect('/orders');
            }

            $newProof = null;
            try {
                $newProof = save_payment_screenshot_upload('payment_screenshot');
            } catch (Throwable $upEx) {
                flash('danger', $upEx->getMessage());
                redirect('/orders');
            }

            try {
                db()->beginTransaction();
                $locked = db_one('SELECT * FROM orders WHERE id = ? AND user_id = ? FOR UPDATE', [$oid, $uid]);
                if (!$locked || $locked['status'] !== 'rejected') {
                    db()->rollBack();
                    flash('danger', current_lang() === 'bn' ? 'অর্ডারের অবস্থা পরিবর্তিত হয়েছে।' : 'Order state changed.');
                    redirect('/orders');
                }
                $dupTrx = db_one(
                    'SELECT id FROM orders WHERE payment_method = ? AND transaction_id = ? AND id <> ? LIMIT 1',
                    [$method, substr($trxId, 0, 80), $oid]
                );
                if ($dupTrx) {
                    db()->rollBack();
                    flash('danger', current_lang() === 'bn' ? 'এই Transaction ID ইতিমধ্যে অন্য অর্ডারে ব্যবহৃত হয়েছে।' : 'This Transaction ID is already used by another order.');
                    redirect('/orders');
                }
                if (!empty($locked['coupon_id']) && (int) ($locked['coupon_decremented'] ?? 0) === 1) {
                    $resUsed = db_exec(
                        'UPDATE coupons SET used_count = used_count + 1 WHERE id = ? AND is_active = 1 AND (expires_at IS NULL OR expires_at >= NOW()) AND (usage_limit IS NULL OR used_count < usage_limit)',
                        [(int) $locked['coupon_id']]
                    );
                    if ($resUsed !== 1) {
                        db()->rollBack();
                        flash('danger', current_lang() === 'bn' ? 'পূর্ববর্তী কুপনের মেয়াদ বা সীমা শেষ হয়ে গেছে — অনুগ্রহ করে নতুন অর্ডার করুন।' : 'The coupon used on this order is no longer available — please place a new order.');
                        redirect('/orders');
                    }
                }
                db_exec(
                    'UPDATE orders
                     SET payment_method = ?, sender_number = ?, transaction_id = ?, payment_screenshot = COALESCE(?, payment_screenshot),
                         status = "pending", coupon_decremented = 0, admin_note = NULL, reviewed_by = NULL, reviewed_at = NULL
                     WHERE id = ?',
                    [$method, mb_substr($sender, 0, 40), substr($trxId, 0, 80), $newProof, $oid]
                );
                db()->commit();
            } catch (PDOException $pe) {
                if (db()->inTransaction()) {
                    db()->rollBack();
                }
                flash('danger', current_lang() === 'bn' ? 'Transaction ID ডুপ্লিকেট হয়েছে।' : 'Duplicate Transaction ID detected.');
                redirect('/orders');
            }

            notify_order_status_change($oid, 'pending');
            flash('success', current_lang() === 'bn' ? 'সংশোধিত পেমেন্ট তথ্য জমা হয়েছে! যাচাইয়ের জন্য অপেক্ষা করুন।' : 'Updated payment details resubmitted for admin review.');
            redirect('/orders');
        }
    }

    $orders = db_all(
        'SELECT o.*, i.title_bn, i.title_en, i.slug
         FROM orders o JOIN items i ON i.id = o.item_id
         WHERE o.user_id = ? ORDER BY o.id DESC',
        [$uid]
    );
    render_public('orders.php', ['orders' => $orders, 'pageTitle' => 'My Orders', 'activeNav' => 'account']);
}

if ($route === '/wishlist') {
    $user = require_login('/wishlist');
    $filters = browse_extract_filters($_GET);
    $res = browse_query_items(['mode' => 'wishlist', 'user_id' => (int) $user['id']], $filters);
    render_public('wishlist.php', [
        'wishlistItems' => $res['items'],
        'filters'       => $filters,
        'page'          => $res['page'],
        'totalPages'    => $res['totalPages'],
        'totalItems'    => $res['total'],
        'pageTitle'     => t('nav_wishlist'),
        'activeNav'     => 'wishlist',
    ]);
}

if ($route === '/notifications') {
    $user = require_login('/notifications');
    $uid = (int) $user['id'];
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        verify_csrf();
        $act = (string) ($_POST['action'] ?? '');
        if ($act === 'mark_all_read') {
            db_exec('UPDATE notifications SET is_read = 1 WHERE user_id = ? AND is_read = 0', [$uid]);
            flash('success', current_lang() === 'bn' ? 'সব বিজ্ঞপ্তি পঠিত হয়েছে।' : 'All notifications marked as read.');
        } elseif ($act === 'mark_read') {
            $nid = (int) ($_POST['id'] ?? 0);
            if ($nid > 0) {
                db_exec('UPDATE notifications SET is_read = 1 WHERE id = ? AND user_id = ?', [$nid, $uid]);
            }
        }
        redirect('/notifications');
    }
    $notifications = db_all(
        'SELECT * FROM notifications WHERE user_id = ? ORDER BY id DESC LIMIT 60',
        [$uid]
    );
    render_public('notifications.php', ['notifications' => $notifications, 'pageTitle' => t('nav_notifications'), 'activeNav' => 'notifications']);
}

// ----------------------------------------------------------------------------
// Manual Purchase Checkout: /checkout/{slug}
// ----------------------------------------------------------------------------
if (preg_match('#^/checkout/([^/]+)$#', $route, $m)) {
    $slug = rawurldecode($m[1]);
    $user = require_login('/checkout/' . rawurlencode($slug));
    $item = db_one('SELECT * FROM items WHERE slug = ? AND is_active = 1 LIMIT 1', [$slug]);
    if (!$item) {
        redirect('/pdf');
    }
    if ((bool) $item['is_free'] || (float) $item['price'] <= 0 || user_owns_item((int) $user['id'], (int) $item['id'])) {
        redirect('/reader/' . rawurlencode($item['slug']));
    }
    if (user_pending_order((int) $user['id'], (int) $item['id'])) {
        flash('warning', current_lang() === 'bn' ? 'এই বইয়ের জন্য আপনার একটি অর্ডার যাচাইয়ের অপেক্ষায় আছে।' : 'You already have a pending order for this PDF.');
        redirect('/orders');
    }

    $error = null;
    $appliedCoupon = null;
    $basePrice = (float) $item['price'];

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        verify_csrf();
        $gwDriver = payment_gateway_driver();
        $useAutoGw = $gwDriver->isAutomatic();

        $method = $_POST['payment_method'] ?? 'bkash';
        if (!in_array($method, ['bkash', 'nagad', 'rocket'], true)) {
            $method = 'bkash';
        }
        $sender = trim((string) ($_POST['sender_number'] ?? ''));
        $trxId = normalize_transaction_id((string) ($_POST['transaction_id'] ?? ''));
        $couponCode = strtoupper(trim((string) ($_POST['coupon_code'] ?? '')));
        $agreeLegal = ($_POST['agree_legal'] ?? '') === '1';

        $finalAmount = $basePrice;
        $discountAmount = 0.0;
        $couponId = null;
        $validCouponCode = null;

        if ($couponCode !== '') {
            $chk = validate_coupon_for_checkout($couponCode, (int) $user['id'], (int) $item['id'], $basePrice);
            if (!$chk['ok']) {
                $error = $chk['message'];
            } else {
                $appliedCoupon = $chk;
                $finalAmount = (float) $chk['final_amount'];
                $discountAmount = (float) $chk['discount_amount'];
                $couponId = (int) $chk['coupon_id'];
                $validCouponCode = (string) $chk['code'];
            }
        }

        $isZeroAutoApprove = $finalAmount <= 0;
        $uploadedProof = null;
        if ($error === null && !$isZeroAutoApprove && !$useAutoGw) {
            try {
                $uploadedProof = save_payment_screenshot_upload('payment_screenshot');
            } catch (Throwable $upEx) {
                $error = $upEx->getMessage();
            }
        }

        if ($error === null && !$agreeLegal) {
            $error = current_lang() === 'bn'
                ? 'অর্ডার করতে শর্তাবলি, গোপনীয়তা, রিফান্ড ও কপিরাইট নীতিতে সম্মত হতে হবে।'
                : 'You must agree to the Terms, Privacy, Refund Policy, and Copyright Notice to place an order.';
        } elseif ($error === null && !$isZeroAutoApprove && !$useAutoGw && ($sender === '' || $trxId === '' || str_starts_with($trxId, 'COUPON'))) {
            $error = current_lang() === 'bn'
                ? 'সঠিক প্রেরক নম্বর এবং পেমেন্ট Transaction ID অবশ্যই দিতে হবে।'
                : 'Valid sender number and payment Transaction ID are required.';
        } elseif ($error === null) {
            $dbMethod = $isZeroAutoApprove ? 'free' : ($useAutoGw ? $gwDriver->getCode() : $method);
            $dbGwDrv  = (!$isZeroAutoApprove && $useAutoGw) ? $gwDriver->getCode() : null;
            $dbSender = ($isZeroAutoApprove || $useAutoGw) ? null : mb_substr($sender, 0, 40);
            $dbTrx    = ($isZeroAutoApprove || $useAutoGw) ? null : substr($trxId, 0, 80);
            $orderStatus = $isZeroAutoApprove ? 'approved' : 'pending';
            $orderCode = 'BX-' . date('Ymd') . '-' . strtoupper(bin2hex(random_bytes(2)));
            $orderId = 0;

            db()->beginTransaction();
            try {
                if ($dbTrx !== null) {
                    $dupTrx = db_one(
                        'SELECT id FROM orders WHERE payment_method = ? AND transaction_id = ? LIMIT 1',
                        [$dbMethod, $dbTrx]
                    );
                    if ($dupTrx) {
                        db()->rollBack();
                        $error = current_lang() === 'bn'
                            ? 'এই Transaction ID-টি ইতিমধ্যে আরেকটি অর্ডারে ব্যবহৃত হয়েছে। অনুগ্রহ করে সঠিক TrxID দিন বা সাপোর্টে যোগাযোগ করুন।'
                            : 'This Transaction ID has already been used for another order. Please double-check or contact support.';
                    }
                }

                if ($error === null && $couponId !== null) {
                    $cRow = db_one('SELECT per_user_limit FROM coupons WHERE id = ? LIMIT 1 FOR UPDATE', [$couponId]);
                    $perUser = max(1, (int) ($cRow['per_user_limit'] ?? 1));
                    $uUsed = (int) (db_one(
                        "SELECT COUNT(*) AS c FROM orders WHERE user_id = ? AND coupon_id = ? AND status NOT IN ('rejected', 'cancelled', 'expired')",
                        [(int) $user['id'], $couponId]
                    )['c'] ?? 0);
                    if ($uUsed >= $perUser) {
                        db()->rollBack();
                        $error = current_lang() === 'bn'
                            ? 'আপনি ইতিমধ্যে এই কুপনটি সর্বোচ্চ সংখ্যকবার ব্যবহার করেছেন।'
                            : 'You have already reached the per-user limit for this coupon.';
                    } else {
                        $affected = db_exec(
                            'UPDATE coupons SET used_count = used_count + 1 WHERE id = ? AND is_active = 1 AND (expires_at IS NULL OR expires_at >= NOW()) AND (usage_limit IS NULL OR used_count < usage_limit)',
                            [$couponId]
                        );
                        if ($affected !== 1) {
                            db()->rollBack();
                            $error = current_lang() === 'bn'
                                ? 'দুঃখিত, এই কুপনের ব্যবহারের সীমা পূর্ণ হয়েছে বা মেয়াদ শেষ হয়েছে।'
                                : 'Sorry, this coupon usage limit has just been reached or expired.';
                        }
                    }
                }

                if ($error === null) {
                    db_exec(
                        'INSERT INTO orders (order_code, user_id, item_id, original_amount, coupon_id, coupon_code, discount_amount, amount, payment_method, gateway_driver, sender_number, transaction_id, payment_screenshot, status, reviewed_at)
                         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
                        [
                            $orderCode,
                            (int) $user['id'],
                            (int) $item['id'],
                            $basePrice,
                            $couponId,
                            $validCouponCode,
                            $discountAmount,
                            $finalAmount,
                            $dbMethod,
                            $dbGwDrv,
                            $dbSender,
                            $dbTrx,
                            $uploadedProof,
                            $orderStatus,
                            $isZeroAutoApprove ? date('Y-m-d H:i:s') : null,
                        ]
                    );
                    $orderId = (int) db()->lastInsertId();
                    if ($isZeroAutoApprove) {
                        db_exec(
                            'INSERT INTO library (user_id, item_id, order_id) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE order_id = VALUES(order_id)',
                            [(int) $user['id'], (int) $item['id'], $orderId]
                        );
                    }
                    db()->commit();
                }
            } catch (PDOException $pdoEx) {
                if (db()->inTransaction()) {
                    db()->rollBack();
                }
                if ((string) $pdoEx->getCode() === '23000') {
                    $error = current_lang() === 'bn'
                        ? 'এই Transaction ID-টি ইতিমধ্যে আরেকটি অর্ডারে ব্যবহৃত হয়েছে।'
                        : 'This Transaction ID has already been submitted on another order.';
                } else {
                    throw $pdoEx;
                }
            } catch (Throwable $ex) {
                if (db()->inTransaction()) {
                    db()->rollBack();
                }
                throw $ex;
            }

            if ($error === null) {
                if ($isZeroAutoApprove) {
                    notify_order_status_change($orderId, 'approved');
                    flash('success', current_lang() === 'bn' ? '১০০% ছাড়ে অর্ডার স্বয়ংক্রিয়ভাবে অনুমোদিত হয়েছে! বইটি আপনার লাইব্রেরিতে যুক্ত হয়েছে।' : '100% discount applied — Order auto-approved & unlocked in My Library!');
                    redirect('/library');
                }

                if ($useAutoGw) {
                    $gwRes = $gwDriver->create(['id' => $orderId, 'order_code' => $orderCode, 'amount' => $finalAmount], $item, $user);
                    if (!empty($gwRes['gateway_ref'])) {
                        db_exec('UPDATE orders SET gateway_ref = ? WHERE id = ?', [$gwRes['gateway_ref'], $orderId]);
                    }
                    if (!empty($gwRes['ok']) && !empty($gwRes['redirect_url'])) {
                        header('Location: ' . $gwRes['redirect_url']);
                        exit;
                    }
                    flash('danger', (string) ($gwRes['message'] ?? 'Payment gateway initialization failed.'));
                    redirect('/orders');
                }

                $invoiceAbs = BASE_URL . '/orders/' . rawurlencode($orderCode) . '/invoice';
                if (!empty($user['email'])) {
                    send_bookexa_template_mail(
                        (string) $user['email'],
                        'Order Received #' . $orderCode . ' — Bookexa',
                        'Order Received #' . $orderCode,
                        '<p>Hello <strong>' . e($user['name']) . '</strong>,</p>'
                        . '<p>We received your order for <strong>' . e($item['title_en']) . '</strong> (' . e(format_bdt($finalAmount)) . ').</p>'
                        . '<p>Your payment details are pending admin verification. <a href="' . e($invoiceAbs) . '">View Invoice #' . e($orderCode) . '</a></p>',
                        BASE_URL . '/orders',
                        'View My Orders'
                    );
                }

                flash('success', current_lang() === 'bn' ? 'আপনার অর্ডার জমা হয়েছে! অ্যাডমিন যাচাই করলে বইটি My Library-তে আনলক হবে।' : 'Order submitted! Once Admin approves, the PDF unlocks in My Library.');
                redirect('/orders');
            }
        }
    }

    render_public('checkout.php', ['item' => $item, 'error' => $error, 'appliedCoupon' => $appliedCoupon, 'pageTitle' => t('buy_now_btn'), 'activeNav' => 'pdfs']);
}

// ----------------------------------------------------------------------------
// Protected In-Browser PDF Reader: /reader/{slug}
// ----------------------------------------------------------------------------
if (preg_match('#^/reader/([^/]+)$#', $route, $m)) {
    $slug = rawurldecode($m[1]);
    $wantSample = isset($_GET['sample']) && $_GET['sample'] === '1';
    $user = require_login('/reader/' . rawurlencode($slug) . ($wantSample ? '?sample=1' : ''));
    $item = db_one('SELECT * FROM items WHERE slug = ? AND is_active = 1 LIMIT 1', [$slug]);
    if (!$item) {
        redirect('/pdf');
    }

    $uid = (int) $user['id'];
    $isFree = (bool) $item['is_free'] || (float) $item['price'] <= 0;
    $canReadFull = $isFree || user_owns_item($uid, (int) $item['id']);
    $isSample = !$canReadFull;

    if ($isSample && (int) $item['sample_pages'] <= 0) {
        redirect('/checkout/' . rawurlencode($item['slug']));
    }

    // Enforce single active reading session per account
    $newSessionTok = bin2hex(random_bytes(32));
    db_exec('UPDATE users SET active_session_token = ?, active_session_updated_at = NOW() WHERE id = ?', [$newSessionTok, $uid]);
    $_SESSION['active_session_token'] = $newSessionTok;

    require_once APP_ROOT . '/includes/pdf_proxy.php';

    $readerMode = 'unavailable';
    $streamToken = '';
    $pageApiBase = '';
    $maxPages = 0;
    $unavailableCustomMsg = null;

    if (!empty($user['reading_suspended_until']) && strtotime((string) $user['reading_suspended_until']) > time()) {
        $unavailableCustomMsg = user_reading_suspension_message((string) $user['reading_suspended_until']);
        log_reading_event($uid, (int) $item['id'], $isSample, 'blocked_suspended_open', 'Attempted reader open during 30m abuse pause');
    } else {
        db_exec('UPDATE items SET read_count = read_count + 1 WHERE id = ?', [(int) $item['id']]);
        detect_many_sessions_anomaly($uid);
        $preparedCount = count_prepared_pages((int) $item['id']);
        $isPrepared = (($item['pages_status'] ?? 'unprocessed') === 'ready') && $preparedCount > 0;
        $fallbackEnabled = site_setting('free_pdfjs_fallback', '1') === '1';

        if ($isPrepared) {
            $readerMode = 'pages';
            $streamToken = issue_signed_reader_token($uid, (int) $item['id'], $isSample, $newSessionTok, 'pages', 900);
            $maxPages = $isSample ? min($preparedCount, (int) $item['sample_pages']) : $preparedCount;
            $pageApiBase = url('/api/page?item=' . (int) $item['id'] . '&token=' . rawurlencode($streamToken));
            log_reading_event($uid, (int) $item['id'], $isSample, 'open_reader', $isSample ? 'Protected pages (sample)' : 'Protected pages (full)');
        } elseif ($isFree && $fallbackEnabled && trim((string) ($item['drive_link'] ?? '')) !== '') {
            $readerMode = 'pdfjs';
            $streamToken = issue_signed_reader_token($uid, (int) $item['id'], $isSample, $newSessionTok, 'stream', 180);
            $maxPages = $isSample ? (int) $item['sample_pages'] : 0;
            log_reading_event($uid, (int) $item['id'], $isSample, 'open_reader', 'Free PDF.js fallback');
        } else {
            log_reading_event($uid, (int) $item['id'], $isSample, 'open_reader', 'Protected pages pending preparation');
        }
    }

    $savedPage = 1;
    if (!$isSample) {
        $prog = db_one('SELECT last_page FROM reading_progress WHERE user_id = ? AND item_id = ?', [$uid, (int) $item['id']]);
        if ($prog && (int) $prog['last_page'] > 0) {
            $savedPage = (int) $prog['last_page'];
        }
    }

    $userTheme = in_array((string) ($user['reader_theme'] ?? ''), ['dark', 'sepia', 'light'], true) ? (string) $user['reader_theme'] : '';
    $userZoom  = isset($user['reader_zoom']) && (float) $user['reader_zoom'] > 0 ? (float) $user['reader_zoom'] : null;

    $contact = !empty($user['phone']) ? $user['phone'] : $user['email'];
    $watermarkText = $user['name'] . ' • ' . $contact . ' • ID #' . $uid;
    $folderUrl = $item['folder_id'] ? folder_full_url((int) $item['folder_id']) : url('/pdf');

    require APP_ROOT . '/pages/reader.php';
    exit;
}

// ----------------------------------------------------------------------------
// Public Storefront Pages
// ----------------------------------------------------------------------------
if ($route === '/') {
    render_public('home.php', ['pageTitle' => '', 'metaDescription' => t('meta_default_desc'), 'canonicalPath' => '/', 'activeNav' => 'home']);
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
            $like = '%' . $filterQ . '%';
            $subfolders = db_all(
                "SELECT f.* FROM folders f
                 WHERE f.parent_id IS NULL AND f.is_active = 1 AND {$pubF}
                   AND (f.name_bn LIKE ? OR f.name_en LIKE ? OR f.description_bn LIKE ? OR f.description_en LIKE ?)
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
        $title = t('browse_folders_title');
        $metaDesc = t('featured_folders_sub');
    } else {
        $currentFolder = resolve_folder_by_slugs($segments);
        if (!$currentFolder) {
            http_response_code(404);
            render_public('404.php', ['pageTitle' => '404', 'activeNav' => 'pdfs']);
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
            $like = '%' . $filterQ . '%';
            $subfolders = db_all(
                "SELECT f.* FROM folders f
                 WHERE f.parent_id = ? AND f.is_active = 1 AND {$pubF}
                   AND (f.name_bn LIKE ? OR f.name_en LIKE ?)
                 ORDER BY f.sort_order ASC, f.id ASC",
                [$fid, $like, $like]
            );
        }

        $authors = browse_distinct_authors($fid);
        $itemRes = browse_query_items(['mode' => 'folder', 'folder_id' => $fid], $filters);
        $items = $itemRes['items'];
        $page = $itemRes['page'];
        $totalPages = $itemRes['totalPages'];
        $totalItems = $itemRes['total'];

        $slugPrefix = implode('/', array_map('rawurlencode', $segments));
        $title = bi($currentFolder, 'name');
        $metaDesc = bi($currentFolder, 'description', t('featured_folders_sub'));
    }

    render_public('folder.php', [
        'currentFolder'   => $currentFolder,
        'breadcrumbs'     => $breadcrumbs,
        'subfolders'      => $subfolders,
        'items'           => $items,
        'slugPrefix'      => $slugPrefix,
        'filterQ'         => $filterQ,
        'filters'         => $filters,
        'authors'         => $authors,
        'page'            => $page,
        'totalPages'      => $totalPages,
        'totalItems'      => $totalItems,
        'pageTitle'       => $title,
        'metaDescription' => $metaDesc,
        'canonicalPath'   => $route,
        'activeNav'       => 'pdfs',
    ]);
}

if (preg_match('#^/book/([^/]+)/review$#', $route, $m) && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $slug = rawurldecode($m[1]);
    $user = require_login('/book/' . rawurlencode($slug));
    verify_csrf();
    $item = db_one('SELECT id, slug FROM items WHERE slug = ? AND is_active = 1 LIMIT 1', [$slug]);
    if (!$item) {
        http_response_code(404);
        render_public('404.php', ['pageTitle' => '404', 'activeNav' => 'pdfs']);
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
        'SELECT id, folder_id, slug, title_bn, title_en, author_bn, author_en, description_bn, description_en, cover, price, original_price, is_free, sample_pages, total_pages
         FROM items WHERE slug = ? AND is_active = 1 AND ' . published_window_sql() . ' LIMIT 1',
        [$slug]
    );
    if (!$item) {
        render_error_page(404);
    }
    $ogImg = !empty($item['cover']) ? (BASE_URL . '/' . ltrim((string) $item['cover'], '/')) : (BASE_URL . '/assets/img/apple-touch-icon.png');
    render_public('item_detail.php', [
        'item'            => $item,
        'ogImage'         => $ogImg,
        'pageTitle'       => bi($item, 'title'),
        'metaDescription' => mb_substr(strip_tags(bi($item, 'description')), 0, 155),
        'canonicalPath'   => '/book/' . rawurlencode($item['slug']),
        'activeNav'       => 'pdfs',
    ]);
}

if ($route === '/shop') {
    render_public('shop.php', ['pageTitle' => t('nav_shop'), 'canonicalPath' => '/shop', 'activeNav' => 'shop']);
}

if (preg_match('#^/shop/([^/]+)$#', $route, $m)) {
    render_public('shop_detail.php', ['shopSlug' => rawurldecode($m[1]), 'pageTitle' => t('nav_shop'), 'canonicalPath' => $route, 'activeNav' => 'shop']);
}

if ($route === '/search') {
    $filters = browse_extract_filters($_GET);
    $q = $filters['q'];
    $matchedFolders = $q !== '' ? browse_search_folders($q, 12) : [];
    $res = browse_query_items(['mode' => 'search'], $filters);
    $authors = browse_distinct_authors();
    $didYouMean = null;
    if ($q !== '' && empty($matchedFolders) && empty($res['items'])) {
        $didYouMean = find_did_you_mean($q);
        log_search_miss($q);
    } elseif ($q !== '') {
        log_search_query($q);
    }
    render_public('search.php', [
        'q'              => $q,
        'filters'        => $filters,
        'authors'        => $authors,
        'matchedFolders' => $matchedFolders,
        'matchedItems'   => $res['items'],
        'page'           => $res['page'],
        'totalPages'     => $res['totalPages'],
        'totalItems'     => $res['total'],
        'didYouMean'     => $didYouMean,
        'pageTitle'      => t('search_title'),
        'canonicalPath'  => '/search',
        'activeNav'      => 'search',
    ]);
}

if ($route === '/about') {
    render_public('about.php', ['pageTitle' => t('nav_about'), 'canonicalPath' => '/about', 'activeNav' => 'about']);
}

if ($route === '/contact') {
    render_public('contact.php', ['pageTitle' => t('nav_contact'), 'canonicalPath' => '/contact', 'activeNav' => 'contact']);
}

$legalRouteMap = [
    '/terms'         => ['key' => 'terms',     'setting' => 'legal_terms',     'title' => t('legal_terms')],
    '/privacy'       => ['key' => 'privacy',   'setting' => 'legal_privacy',   'title' => t('legal_privacy')],
    '/refund-policy' => ['key' => 'refund',    'setting' => 'legal_refund',    'title' => t('legal_refund')],
    '/copyright'     => ['key' => 'copyright', 'setting' => 'legal_copyright', 'title' => t('legal_copyright')],
];
if (isset($legalRouteMap[$route])) {
    $lMeta = $legalRouteMap[$route];
    $lSuffix = current_lang() === 'bn' ? '_bn' : '_en';
    render_public('legal.php', [
        'legalKey'      => $lMeta['key'],
        'legalTitle'    => $lMeta['title'],
        'legalHtml'     => site_setting($lMeta['setting'] . $lSuffix, ''),
        'pageTitle'     => $lMeta['title'],
        'canonicalPath' => $route,
        'activeNav'     => '',
    ]);
}

render_error_page(404);
