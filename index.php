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

// ----------------------------------------------------------------------------
// SEO Sitemap.xml (with <lastmod>)
// ----------------------------------------------------------------------------
if ($route === '/sitemap.xml') {
    header('Content-Type: application/xml; charset=UTF-8');
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
    foreach (db_all('SELECT id, updated_at, created_at FROM folders WHERE is_active = 1') as $f) {
        $trail = folder_breadcrumbs((int) $f['id']);
        if (!empty($trail)) {
            $u = BASE_URL . '/pdf/' . implode('/', array_map(fn($c) => rawurlencode($c['slug']), $trail));
            $ts = strtotime((string) ($f['updated_at'] ?? $f['created_at'] ?? 'now')) ?: time();
            $urlMap[$u] = date('Y-m-d', $ts);
        }
    }
    foreach (db_all('SELECT slug, updated_at, created_at FROM items WHERE is_active = 1') as $it) {
        $u = BASE_URL . '/book/' . rawurlencode($it['slug']);
        $ts = strtotime((string) ($it['updated_at'] ?? $it['created_at'] ?? 'now')) ?: time();
        $urlMap[$u] = date('Y-m-d', $ts);
    }
    echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
    echo '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";
    foreach ($urlMap as $u => $lastmod) {
        echo '  <url><loc>' . htmlspecialchars($u, ENT_XML1, 'UTF-8') . '</loc><lastmod>' . e($lastmod) . "</lastmod></url>\n";
    }
    echo '</urlset>';
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
    $next = (string) ($_REQUEST['next'] ?? '');
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        verify_csrf();
        $res = attempt_user_login((string) ($_POST['identifier'] ?? ''), (string) ($_POST['password'] ?? ''));
        if ($res['ok']) {
            if ($next !== '' && str_starts_with($next, '/') && !str_starts_with($next, '//')) {
                $target = (BASE_PATH !== '' && str_starts_with($next, BASE_PATH . '/')) ? $next : url($next);
                header('Location: ' . $target);
                exit;
            }
            redirect('/library');
        }
        $error = $res['error'];
    }
    render_public('auth.php', ['authMode' => 'login', 'error' => $error, 'next' => $next, 'pageTitle' => t('nav_login'), 'activeNav' => 'account']);
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
if ($route === '/profile') {
    $user = require_login('/profile');
    $error = null;
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        verify_csrf();
        $act = (string) ($_POST['action'] ?? '');
        if ($act === 'update_profile') {
            $name = trim((string) ($_POST['name'] ?? ''));
            $phone = trim((string) ($_POST['phone'] ?? ''));
            if ($name === '') {
                $error = 'Name is required.';
            } else {
                db_exec('UPDATE users SET name = ?, phone = ? WHERE id = ?', [$name, $phone !== '' ? $phone : null, (int) $user['id']]);
                flash('success', current_lang() === 'bn' ? 'প্রোফাইল আপডেট হয়েছে।' : 'Profile updated.');
                redirect('/profile');
            }
        } elseif ($act === 'change_password') {
            $curr = (string) ($_POST['current_password'] ?? '');
            $nextPw = (string) ($_POST['new_password'] ?? '');
            $fullUser = db_one('SELECT password_hash FROM users WHERE id = ?', [(int) $user['id']]);
            if (!$fullUser || !password_verify($curr, $fullUser['password_hash'])) {
                $error = current_lang() === 'bn' ? 'বর্তমান পাসওয়ার্ড সঠিক নয়।' : 'Current password is incorrect.';
            } elseif (strlen($nextPw) < 6) {
                $error = current_lang() === 'bn' ? 'নতুন পাসওয়ার্ড কমপক্ষে ৬ অক্ষরের হতে হবে।' : 'New password must be at least 6 characters.';
            } else {
                db_exec('UPDATE users SET password_hash = ? WHERE id = ?', [password_hash($nextPw, PASSWORD_BCRYPT), (int) $user['id']]);
                flash('success', current_lang() === 'bn' ? 'পাসওয়ার্ড পরিবর্তিত হয়েছে।' : 'Password changed successfully.');
                redirect('/profile');
            }
        }
    }
    render_public('profile.php', ['user' => $user, 'error' => $error, 'pageTitle' => 'Profile', 'activeNav' => 'account']);
}

if ($route === '/library') {
    $user = require_login('/library');
    $uid = (int) $user['id'];
    $libraryItems = db_all(
        'SELECT DISTINCT i.id, i.folder_id, i.slug, i.title_bn, i.title_en, i.author_bn, i.author_en, i.cover, i.price, i.is_free, i.total_pages,
                rp.last_page, rp.total_pages AS prog_total_pages
         FROM items i
         LEFT JOIN library l ON l.item_id = i.id AND l.user_id = ?
         LEFT JOIN reading_progress rp ON rp.item_id = i.id AND rp.user_id = ?
         WHERE i.is_active = 1 AND (l.id IS NOT NULL OR i.is_free = 1 OR i.price <= 0)
         ORDER BY COALESCE(rp.updated_at, l.granted_at, i.created_at) DESC',
        [$uid, $uid]
    );
    render_public('library.php', ['user' => $user, 'libraryItems' => $libraryItems, 'pageTitle' => t('nav_library'), 'activeNav' => 'library']);
}

if ($route === '/orders') {
    $user = require_login('/orders');
    $orders = db_all(
        'SELECT o.*, i.title_bn, i.title_en, i.slug
         FROM orders o JOIN items i ON i.id = o.item_id
         WHERE o.user_id = ? ORDER BY o.id DESC',
        [(int) $user['id']]
    );
    render_public('orders.php', ['orders' => $orders, 'pageTitle' => 'My Orders', 'activeNav' => 'account']);
}

if ($route === '/wishlist') {
    $user = require_login('/wishlist');
    $wishlistItems = db_all(
        'SELECT i.id, i.folder_id, i.slug, i.title_bn, i.title_en, i.author_bn, i.author_en, i.cover, i.price, i.original_price, i.is_free
         FROM wishlist w
         JOIN items i ON i.id = w.item_id
         WHERE w.user_id = ? AND i.is_active = 1
         ORDER BY w.id DESC',
        [(int) $user['id']]
    );
    render_public('wishlist.php', ['wishlistItems' => $wishlistItems, 'pageTitle' => t('nav_wishlist'), 'activeNav' => 'wishlist']);
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
        $method = $_POST['payment_method'] ?? 'bkash';
        if (!in_array($method, ['bkash', 'nagad', 'rocket'], true)) {
            $method = 'bkash';
        }
        $sender = trim((string) ($_POST['sender_number'] ?? ''));
        $trxId = strtoupper(trim((string) ($_POST['transaction_id'] ?? '')));
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

        if ($error === null && !$agreeLegal) {
            $error = current_lang() === 'bn'
                ? 'অর্ডার করতে শর্তাবলি, গোপনীয়তা, রিফান্ড ও কপিরাইট নীতিতে সম্মত হতে হবে।'
                : 'You must agree to the Terms, Privacy, Refund Policy, and Copyright Notice to place an order.';
        } elseif ($error === null && $finalAmount > 0 && ($sender === '' || $trxId === '')) {
            $error = current_lang() === 'bn' ? 'প্রেরক নম্বর এবং Transaction ID অবশ্যই দিতে হবে।' : 'Sender number and Transaction ID are required.';
        } elseif ($error === null) {
            $orderCode = 'BX-' . date('Ymd') . '-' . strtoupper(bin2hex(random_bytes(2)));
            db()->beginTransaction();
            try {
                db_exec(
                    'INSERT INTO orders (order_code, user_id, item_id, original_amount, coupon_id, coupon_code, discount_amount, amount, payment_method, sender_number, transaction_id, status)
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, "pending")',
                    [
                        $orderCode,
                        (int) $user['id'],
                        (int) $item['id'],
                        $basePrice,
                        $couponId,
                        $validCouponCode,
                        $discountAmount,
                        $finalAmount,
                        $method,
                        $sender !== '' ? $sender : 'COUPON',
                        $trxId !== '' ? $trxId : 'COUPON-100',
                    ]
                );
                if ($couponId !== null) {
                    db_exec('UPDATE coupons SET used_count = used_count + 1 WHERE id = ?', [$couponId]);
                }
                db()->commit();
            } catch (Throwable $ex) {
                if (db()->inTransaction()) {
                    db()->rollBack();
                }
                throw $ex;
            }

            if (!empty($user['email'])) {
                send_bookexa_template_mail(
                    (string) $user['email'],
                    'Order Received #' . $orderCode . ' — Bookexa',
                    'Order Received #' . $orderCode,
                    '<p>Hello <strong>' . e($user['name']) . '</strong>,</p>'
                    . '<p>We received your order for <strong>' . e($item['title_en']) . '</strong> (' . e(format_bdt($finalAmount)) . ').</p>'
                    . '<p>Your payment details are pending admin verification. You will be notified as soon as it is reviewed.</p>',
                    BASE_URL . '/orders',
                    'View My Orders'
                );
            }

            flash('success', current_lang() === 'bn' ? 'আপনার অর্ডার জমা হয়েছে! অ্যাডমিন যাচাই করলে বইটি My Library-তে আনলক হবে।' : 'Order submitted! Once Admin approves, the PDF unlocks in My Library.');
            redirect('/orders');
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
    detect_many_sessions_anomaly($uid);

    $preparedCount = count_prepared_pages((int) $item['id']);
    $isPrepared = (($item['pages_status'] ?? 'unprocessed') === 'ready') && $preparedCount > 0;
    $fallbackEnabled = site_setting('free_pdfjs_fallback', '1') === '1';

    $readerMode = 'unavailable';
    $streamToken = '';
    $pageApiBase = '';
    $maxPages = 0;

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
    $filterQ = trim((string) ($_GET['q'] ?? ''));

    if (empty($segments)) {
        $currentFolder = null;
        $breadcrumbs = [];
        $sqlF = 'SELECT f.*,
                    (SELECT COUNT(*) FROM folders c WHERE c.parent_id = f.id AND c.is_active = 1) AS subfolder_count,
                    (SELECT COUNT(*) FROM items i WHERE i.folder_id = f.id AND i.is_active = 1) AS item_count
                 FROM folders f WHERE f.parent_id IS NULL AND f.is_active = 1';
        $paramsF = [];
        if ($filterQ !== '') {
            $like = '%' . $filterQ . '%';
            $sqlF .= ' AND (f.name_bn LIKE ? OR f.name_en LIKE ? OR f.description_bn LIKE ? OR f.description_en LIKE ?)';
            array_push($paramsF, $like, $like, $like, $like);
        }
        $sqlF .= ' ORDER BY f.sort_order ASC, f.id ASC';
        $subfolders = db_all($sqlF, $paramsF);
        $items = [];
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

        $sqlSub = 'SELECT f.*,
                      (SELECT COUNT(*) FROM folders c WHERE c.parent_id = f.id AND c.is_active = 1) AS subfolder_count,
                      (SELECT COUNT(*) FROM items i WHERE i.folder_id = f.id AND i.is_active = 1) AS item_count
                   FROM folders f WHERE f.parent_id = ? AND f.is_active = 1';
        $paramsSub = [$fid];
        if ($filterQ !== '') {
            $like = '%' . $filterQ . '%';
            $sqlSub .= ' AND (f.name_bn LIKE ? OR f.name_en LIKE ?)';
            array_push($paramsSub, $like, $like);
        }
        $sqlSub .= ' ORDER BY f.sort_order ASC, f.id ASC';
        $subfolders = db_all($sqlSub, $paramsSub);

        $sqlItems = 'SELECT id, folder_id, slug, title_bn, title_en, author_bn, author_en, cover, price, original_price, is_free FROM items WHERE folder_id = ? AND is_active = 1';
        $paramsItems = [$fid];
        if ($filterQ !== '') {
            $like = '%' . $filterQ . '%';
            $sqlItems .= ' AND (title_bn LIKE ? OR title_en LIKE ? OR author_bn LIKE ? OR author_en LIKE ?)';
            array_push($paramsItems, $like, $like, $like, $like);
        }
        $sqlItems .= ' ORDER BY sort_order ASC, id DESC';
        $items = db_all($sqlItems, $paramsItems);

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
         FROM items WHERE slug = ? AND is_active = 1 LIMIT 1',
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
    $q = trim((string) ($_GET['q'] ?? ''));
    $matchedFolders = [];
    $matchedItems = [];
    if ($q !== '') {
        $like = '%' . $q . '%';
        $matchedFolders = db_all(
            'SELECT f.*,
                (SELECT COUNT(*) FROM folders c WHERE c.parent_id = f.id AND c.is_active = 1) AS subfolder_count,
                (SELECT COUNT(*) FROM items i WHERE i.folder_id = f.id AND i.is_active = 1) AS item_count
             FROM folders f
             WHERE f.is_active = 1 AND (f.name_bn LIKE ? OR f.name_en LIKE ? OR f.description_bn LIKE ? OR f.description_en LIKE ?)
             ORDER BY f.sort_order ASC LIMIT 24',
            [$like, $like, $like, $like]
        );
        $matchedItems = db_all(
            'SELECT id, folder_id, slug, title_bn, title_en, author_bn, author_en, cover, price, original_price, is_free
             FROM items
             WHERE is_active = 1 AND (title_bn LIKE ? OR title_en LIKE ? OR author_bn LIKE ? OR author_en LIKE ? OR description_bn LIKE ? OR description_en LIKE ?)
             ORDER BY is_featured DESC, id DESC LIMIT 48',
            [$like, $like, $like, $like, $like, $like]
        );
    } else {
        $matchedItems = db_all(
            'SELECT id, folder_id, slug, title_bn, title_en, author_bn, author_en, cover, price, original_price, is_free
             FROM items WHERE is_active = 1 ORDER BY is_featured DESC, id DESC LIMIT 24'
        );
    }
    render_public('search.php', ['q' => $q, 'matchedFolders' => $matchedFolders, 'matchedItems' => $matchedItems, 'pageTitle' => t('search_title'), 'canonicalPath' => '/search', 'activeNav' => 'search']);
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
