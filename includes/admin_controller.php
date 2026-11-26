<?php
declare(strict_types=1);

require_once APP_ROOT . '/includes/admin_helpers.php';

function admin_bi(string $bn, string $en): string
{
    return current_lang() === 'bn' ? $bn : $en;
}

/**
 * Resolve safe in-panel return URL so saves/actions keep the user's current filters & page.
 */
function admin_return_url(string $fallback, bool $stripFormParams = true): string
{
    $raw = trim((string) ($_POST['_return'] ?? ''));
    if ($raw === '') {
        return $fallback;
    }
    $parts = @parse_url($raw);
    if (!is_array($parts) || !empty($parts['host']) || !empty($parts['scheme'])) {
        return $fallback;
    }
    $path = (string) ($parts['path'] ?? '');
    if (BASE_PATH !== '' && str_starts_with($path, BASE_PATH . '/')) {
        $path = substr($path, strlen(BASE_PATH));
    } elseif ($path === BASE_PATH) {
        $path = '/';
    }
    if (!str_starts_with($path, '/admin') || str_starts_with($path, '/admin/login') || str_starts_with($path, '/admin/logout')) {
        return $fallback;
    }
    $qStr = (string) ($parts['query'] ?? '');
    if ($qStr === '') {
        return $path;
    }
    parse_str($qStr, $qArr);
    if ($stripFormParams) {
        unset($qArr['edit'], $qArr['add_parent']);
    }
    $rebuilt = http_build_query($qArr);
    return $rebuilt !== '' ? ($path . '?' . $rebuilt) : $path;
}

function admin_json_response(array $payload, int $status = 200): never
{
    http_response_code($status);
    header('Content-Type: application/json; charset=UTF-8');
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function admin_parse_datetime(?string $raw): ?string
{
    $raw = trim((string) $raw);
    if ($raw === '') {
        return null;
    }
    $raw = str_replace('T', ' ', $raw);
    $ts = strtotime($raw);
    if ($ts === false) {
        return null;
    }
    return date('Y-m-d H:i:s', $ts);
}

/**
 * Shared filter builder for Admin Orders table and CSV export so both always match.
 */
function admin_orders_filter_query(): array
{
    $status   = (string) ($_GET['status'] ?? 'all');
    $method   = (string) ($_GET['method'] ?? 'all');
    $dupsOnly = ($_GET['dups'] ?? '') === '1';
    $dateFrom = trim((string) ($_GET['date_from'] ?? ''));
    $dateTo   = trim((string) ($_GET['date_to'] ?? ''));
    $userId   = max(0, (int) ($_GET['user_id'] ?? 0));
    $itemId   = max(0, (int) ($_GET['item_id'] ?? 0));
    $q        = trim((string) ($_GET['q'] ?? ''));

    if (!in_array($status, ['all', 'pending', 'approved', 'rejected', 'cancelled', 'expired'], true)) {
        $status = 'all';
    }
    if (!in_array($method, ['all', 'bkash', 'nagad', 'rocket', 'free', 'bkash_tokenized', 'aamarpay', 'uddoktapay'], true)) {
        $method = 'all';
    }
    if ($dateFrom !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateFrom)) {
        $dateFrom = '';
    }
    if ($dateTo !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateTo)) {
        $dateTo = '';
    }

    $where  = [];
    $params = [];
    if ($status !== 'all') {
        $where[]  = 'o.status = ?';
        $params[] = $status;
    }
    if ($method !== 'all') {
        $where[]  = 'o.payment_method = ?';
        $params[] = $method;
    }
    if ($dateFrom !== '') {
        $where[]  = 'DATE(o.created_at) >= ?';
        $params[] = $dateFrom;
    }
    if ($dateTo !== '') {
        $where[]  = 'DATE(o.created_at) <= ?';
        $params[] = $dateTo;
    }
    if ($userId > 0) {
        $where[]  = 'o.user_id = ?';
        $params[] = $userId;
    }
    if ($itemId > 0) {
        $where[]  = 'o.item_id = ?';
        $params[] = $itemId;
    }
    if ($dupsOnly) {
        $where[] = "(
            (o.transaction_id IS NOT NULL AND o.transaction_id <> '' AND EXISTS (SELECT 1 FROM orders o2 WHERE o2.id <> o.id AND o2.transaction_id = o.transaction_id))
            OR
            (o.sender_number IS NOT NULL AND o.sender_number <> '' AND o.sender_number NOT LIKE 'COUPON-%' AND EXISTS (SELECT 1 FROM orders o3 WHERE o3.id <> o.id AND o3.sender_number = o.sender_number))
        )";
    }
    if ($q !== '') {
        $like = '%' . $q . '%';
        $where[] = '(o.order_code LIKE ? OR o.transaction_id LIKE ? OR o.sender_number LIKE ? OR COALESCE(u.phone, "") LIKE ? OR COALESCE(o.coupon_code, "") LIKE ? OR u.name LIKE ? OR u.email LIKE ? OR i.title_bn LIKE ? OR i.title_en LIKE ?)';
        array_push($params, $like, $like, $like, $like, $like, $like, $like, $like, $like);
    }

    $whereSql = $where ? (' WHERE ' . implode(' AND ', $where)) : '';
    return [
        'status'    => $status,
        'method'    => $method,
        'dupsOnly'  => $dupsOnly,
        'dateFrom'  => $dateFrom,
        'dateTo'    => $dateTo,
        'userId'    => $userId,
        'itemId'    => $itemId,
        'q'         => $q,
        'whereSql'  => $whereSql,
        'params'    => $params,
    ];
}

function handle_admin_routes(string $route): never
{
    header('X-Robots-Tag: noindex, nofollow, noarchive');
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
    header('Pragma: no-cache');

    $sub = trim(substr($route, 6), '/'); // strips '/admin'
    if ($sub === '') {
        $sub = 'dashboard';
    }

    // 1. Admin Login (with optional 2FA & IP Allowlist)
    if ($sub === 'login') {
        if (!empty($_SESSION['admin_id'])) {
            redirect('/admin');
        }
        $error = null;
        if (isset($_GET['reset_2fa'])) {
            unset($_SESSION['pending_admin_2fa']);
            redirect('/admin/login');
        }
        if (isset($_GET['timeout'])) {
            $error = admin_bi(
                'নিষ্ক্রিয়তার কারণে অ্যাডমিন সেশন শেষ হয়েছে। অনুগ্রহ করে আবার লগইন করুন।',
                'Your admin session timed out after inactivity. Please log in again.'
            );
        }
        $pending2fa = $_SESSION['pending_admin_2fa'] ?? null;
        $totpRequired = is_array($pending2fa) && !empty($pending2fa['admin_id']) && (int) ($pending2fa['exp'] ?? 0) >= time();
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            verify_csrf();
            $loginAction = (string) ($_POST['action'] ?? '');
            if ($loginAction === 'verify_2fa') {
                if (!$totpRequired) {
                    unset($_SESSION['pending_admin_2fa']);
                    $error = admin_bi('2FA সেশনের মেয়াদ শেষ। আবার লগইন করুন।', '2FA verification session expired. Please sign in again.');
                } else {
                    $vRes = verify_admin_totp_and_login((int) $pending2fa['admin_id'], (string) ($_POST['totp_code'] ?? ''));
                    if ($vRes['ok']) {
                        unset($_SESSION['pending_admin_2fa']);
                        flash('success', admin_bi('স্বাগতম, ', 'Welcome back, ') . $vRes['admin']['name'] . '!');
                        redirect('/admin');
                    }
                    $error = $vRes['error'];
                }
            } else {
                $res = attempt_admin_login((string) ($_POST['email'] ?? ''), (string) ($_POST['password'] ?? ''));
                if ($res['ok']) {
                    unset($_SESSION['pending_admin_2fa']);
                    flash('success', admin_bi('স্বাগতম, ', 'Welcome back, ') . $res['admin']['name'] . '!');
                    redirect('/admin');
                }
                if (($res['code'] ?? '') === 'TOTP_REQUIRED') {
                    $_SESSION['pending_admin_2fa'] = ['admin_id' => (int) $res['admin']['id'], 'exp' => time() + 300];
                    $totpRequired = true;
                } else {
                    $error = $res['error'];
                }
            }
        }
        require APP_ROOT . '/pages/admin/login.php';
        exit;
    }

    // 2. Admin Logout (POST + CSRF)
    if ($sub === 'logout') {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            verify_csrf();
            if (!empty($_SESSION['admin_id'])) {
                $currAdmin = db_one('SELECT id, name FROM admins WHERE id = ?', [(int) $_SESSION['admin_id']]);
                if ($currAdmin) {
                    log_admin_action($currAdmin, 'admin_logout', 'admin', (int) $currAdmin['id'], 'Signed out');
                }
            }
            admin_logout();
            flash('success', admin_bi('নিরাপদে লগআউট সম্পন্ন হয়েছে।', 'Logged out safely.'));
        }
        redirect('/admin/login');
    }

    // 3. Authenticated Admin Guard
    $admin = require_admin();

    // 3b. Protected Payment Screenshot Viewer (Orders Permission Only)
    if ($sub === 'payment-proof' || $sub === 'api/payment-proof') {
        if (!admin_can($admin, 'orders')) {
            log_security_event('admin_perm_denied', (string) $admin['email'], 'Denied payment-proof for role ' . $admin['role']);
            render_error_page(403);
        }
        $orderId = (int) ($_GET['order_id'] ?? 0);
        $ord = $orderId > 0 ? db_one('SELECT payment_screenshot FROM orders WHERE id = ? LIMIT 1', [$orderId]) : null;
        $absPath = $ord ? resolve_payment_screenshot_path((string) ($ord['payment_screenshot'] ?? '')) : null;
        if (!$absPath) {
            render_error_page(404);
        }
        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $mime = (string) $finfo->file($absPath);
        if (!in_array($mime, ['image/jpeg', 'image/png', 'image/webp'], true)) {
            $mime = 'application/octet-stream';
        }
        header('Content-Type: ' . $mime);
        header('X-Content-Type-Options: nosniff');
        header('Content-Disposition: inline; filename="payment-proof-' . $orderId . '"');
        header('Cache-Control: private, no-store, max-age=0');
        readfile($absPath);
        exit;
    }

    // 4. AJAX / Streaming API Endpoints (/admin/api/*)
    if (str_starts_with($sub, 'api/')) {
        if (!admin_can($admin, admin_api_section($sub))) {
            log_security_event('admin_perm_denied', (string) $admin['email'], 'Denied API ' . $sub . ' for role ' . $admin['role']);
            admin_json_response(['ok' => false, 'message' => 'Forbidden for your admin role.'], 403);
        }
        if ($sub === 'api/stream-source') {
            $csrfParam = (string) ($_GET['_csrf'] ?? $_POST['_csrf'] ?? '');
            if (!hash_equals(csrf_token(), $csrfParam)) {
                admin_json_response(['ok' => false, 'message' => 'Invalid CSRF token'], 403);
            }
            $itemId = (int) ($_GET['item_id'] ?? $_POST['item_id'] ?? 0);
            $item = $itemId > 0 ? db_one('SELECT id, drive_link, title_en FROM items WHERE id = ?', [$itemId]) : null;
            if (!$item) {
                admin_json_response(['ok' => false, 'message' => 'PDF item not found'], 404);
            }
            if (empty(trim((string) ($item['drive_link'] ?? '')))) {
                admin_json_response(['ok' => false, 'message' => 'No Google Drive URL set for this item. Pick a PDF from your computer below instead.'], 422);
            }
            require_once APP_ROOT . '/includes/pdf_proxy.php';
            $fetched = fetch_source_pdf_to_file((string) $item['drive_link']);
            if (!$fetched['ok']) {
                $admMsg = drive_redact_secret((string) ($fetched['admin_reason'] ?? $fetched['message'] ?? 'Fetch failed'));
                log_admin_action($admin, 'admin_stream_source_error', 'item', $itemId, $admMsg);
                admin_json_response(['ok' => false, 'message' => $admMsg], 502);
            }
            log_admin_action($admin, 'admin_stream_source', 'item', $itemId, 'Streamed PDF source (' . ($fetched['method'] ?? 'remote') . ') for page export');
            stream_pdf_file_response((string) $fetched['path'], !empty($fetched['unlink_after']));
        }

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            admin_json_response(['ok' => false, 'message' => 'Method not allowed'], 405);
        }
        verify_csrf();

        try {
            if ($sub === 'api/folder-move') {
                $id = (int) ($_POST['id'] ?? 0);
                $newParentId = !empty($_POST['parent_id']) ? (int) $_POST['parent_id'] : null;
                if ($id <= 0) {
                    throw new RuntimeException(admin_bi('সঠিক ফোল্ডার পাওয়া যায়নি।', 'Invalid folder ID.'));
                }
                if ($newParentId !== null && is_folder_descendant($newParentId, $id)) {
                    throw new RuntimeException(admin_bi(
                        'একটি ফোল্ডারকে নিজের বা নিজ সাব-ফোল্ডারের ভেতরে নেওয়া যাবে না।',
                        'Cannot move a folder inside itself or its own subfolder.'
                    ));
                }
                $curr = db_one('SELECT slug, name_en FROM folders WHERE id = ?', [$id]);
                if (!$curr) {
                    throw new RuntimeException(admin_bi('ফোল্ডার খুঁজে পাওয়া যায়নি।', 'Folder not found.'));
                }
                $uniqueSlug = generate_unique_folder_slug($curr['slug'], $newParentId, $id);
                db_exec('UPDATE folders SET parent_id = ?, slug = ? WHERE id = ?', [$newParentId, $uniqueSlug, $id]);
                invalidate_folder_and_sitemap_cache();
                log_admin_action($admin, 'folder_move', 'folder', $id, $curr['name_en'] . ' -> parent #' . ($newParentId ?? 0));
                admin_json_response(['ok' => true, 'message' => admin_bi('ফোল্ডার সরানো হয়েছে।', 'Folder moved.')]);
            }

            if ($sub === 'api/folder-rename') {
                $id = (int) ($_POST['id'] ?? 0);
                $nameEn = trim((string) ($_POST['name_en'] ?? ''));
                $nameBn = trim((string) ($_POST['name_bn'] ?? ''));
                if ($id <= 0 || $nameEn === '') {
                    throw new RuntimeException(admin_bi('ফোল্ডারের নাম খালি রাখা যাবে না।', 'Folder name cannot be empty.'));
                }
                $curr = db_one('SELECT name_bn, name_en FROM folders WHERE id = ?', [$id]);
                if (!$curr) {
                    throw new RuntimeException(admin_bi('ফোল্ডার খুঁজে পাওয়া যায়নি।', 'Folder not found.'));
                }
                if ($nameBn !== '') {
                    db_exec('UPDATE folders SET name_en = ?, name_bn = ? WHERE id = ?', [$nameEn, $nameBn, $id]);
                } else {
                    db_exec('UPDATE folders SET name_en = ? WHERE id = ?', [$nameEn, $id]);
                }
                invalidate_folder_and_sitemap_cache(false);
                log_admin_action($admin, 'folder_rename', 'folder', $id, $curr['name_en'] . ' -> ' . $nameEn);
                admin_json_response(['ok' => true, 'message' => admin_bi('নাম হালনাগাদ হয়েছে।', 'Folder renamed.')]);
            }

            if ($sub === 'api/test-drive-link') {
                $driveLink = trim((string) ($_POST['drive_link'] ?? ''));
                $res = test_drive_reachability($driveLink);
                admin_json_response($res, 200);
            }

            if ($sub === 'api/banner-reorder') {
                $rawOrder = trim((string) ($_POST['order'] ?? ''));
                $ids = array_values(array_filter(array_map('intval', explode(',', $rawOrder)), fn(int $v): bool => $v > 0));
                if (!$ids) {
                    throw new RuntimeException('No banner IDs provided.');
                }
                foreach ($ids as $idx => $bid) {
                    db_exec('UPDATE banners SET sort_order = ? WHERE id = ?', [($idx + 1) * 10, $bid]);
                }
                log_admin_action($admin, 'banner_reorder', 'banner', null, 'Order: ' . implode(',', $ids));
                admin_json_response(['ok' => true, 'message' => admin_bi('ব্যানারের ক্রম সংরক্ষিত হয়েছে।', 'Banner order saved.')]);
            }

            if ($sub === 'api/folder-step') {
                $id = (int) ($_POST['id'] ?? 0);
                $dir = (string) ($_POST['dir'] ?? '');
                if ($id <= 0 || !in_array($dir, ['up', 'down'], true)) {
                    throw new RuntimeException('Invalid reorder request.');
                }
                $curr = db_one('SELECT id, parent_id FROM folders WHERE id = ?', [$id]);
                if (!$curr) {
                    throw new RuntimeException('Folder not found.');
                }
                $pid = $curr['parent_id'];
                $siblings = $pid === null
                    ? db_all('SELECT id FROM folders WHERE parent_id IS NULL ORDER BY sort_order ASC, id ASC')
                    : db_all('SELECT id FROM folders WHERE parent_id = ? ORDER BY sort_order ASC, id ASC', [(int) $pid]);
                $ids = array_map(fn($r) => (int) $r['id'], $siblings);
                $pos = array_search($id, $ids, true);
                if ($pos !== false) {
                    $swapWith = $dir === 'up' ? $pos - 1 : $pos + 1;
                    if (isset($ids[$swapWith])) {
                        $tmp = $ids[$pos];
                        $ids[$pos] = $ids[$swapWith];
                        $ids[$swapWith] = $tmp;
                    }
                    foreach ($ids as $idx => $fid) {
                        db_exec('UPDATE folders SET sort_order = ? WHERE id = ?', [($idx + 1) * 10, $fid]);
                    }
                    invalidate_folder_and_sitemap_cache(false);
                    log_admin_action($admin, 'folder_reorder_' . $dir, 'folder', $id, 'Sibling order updated');
                }
                admin_json_response(['ok' => true, 'message' => admin_bi('ফোল্ডারের ক্রম হালনাগাদ হয়েছে।', 'Folder order updated.')]);
            }

            if ($sub === 'api/upload-page') {
                $itemId  = (int) ($_POST['item_id'] ?? 0);
                $pageNum = (int) ($_POST['page_num'] ?? 0);
                if ($itemId <= 0 || $pageNum < 1 || $pageNum > 5000) {
                    throw new RuntimeException('Invalid item or page number.');
                }
                if (!db_one('SELECT id FROM items WHERE id = ?', [$itemId])) {
                    throw new RuntimeException('PDF item not found.');
                }
                if (empty($_FILES['page_blob']['tmp_name']) || !is_uploaded_file($_FILES['page_blob']['tmp_name'])) {
                    throw new RuntimeException('Missing uploaded page image.');
                }
                if ((int) $_FILES['page_blob']['size'] > 8 * 1024 * 1024) {
                    throw new RuntimeException('Rendered page image exceeds 8MB limit.');
                }
                $rawBytes = @file_get_contents($_FILES['page_blob']['tmp_name']);
                $info = $rawBytes ? @getimagesizefromstring($rawBytes) : false;
                if (!$info || !in_array($info[2], [IMAGETYPE_WEBP, IMAGETYPE_JPEG, IMAGETYPE_PNG], true)) {
                    throw new RuntimeException('Invalid rendered image format.');
                }

                require_once APP_ROOT . '/includes/pdf_proxy.php';
                ensure_pages_htaccess();
                $dir = page_staging_dir($itemId);
                if (!empty($_POST['reset_staging']) && is_dir($dir)) {
                    rrmdir_recursive($dir);
                }
                if (!is_dir($dir)) {
                    @mkdir($dir, 0755, true);
                }

                $targetWebp = $dir . '/page-' . $pageNum . '.webp';
                $targetJpg  = $dir . '/page-' . $pageNum . '.jpg';
                $saved = false;
                if ($info[2] === IMAGETYPE_WEBP) {
                    $saved = @file_put_contents($targetWebp, $rawBytes) !== false;
                    if ($saved && is_file($targetJpg)) {
                        @unlink($targetJpg);
                    }
                } elseif (extension_loaded('gd') && function_exists('imagewebp')) {
                    $im = @imagecreatefromstring($rawBytes);
                    if ($im) {
                        $saved = @imagewebp($im, $targetWebp, 80);
                        imagedestroy($im);
                        if ($saved && is_file($targetJpg)) {
                            @unlink($targetJpg);
                        }
                    }
                } elseif ($info[2] === IMAGETYPE_JPEG) {
                    $saved = @file_put_contents($targetJpg, $rawBytes) !== false;
                }
                if (!$saved) {
                    throw new RuntimeException('Could not save page image to protected storage.');
                }
                if (!empty($_POST['reset_staging'])) {
                    db_exec('DELETE FROM item_page_texts WHERE item_id = ? AND is_staging = 1', [$itemId]);
                }
                $pageText = trim((string) (preg_replace('/\s+/u', ' ', (string) ($_POST['page_text'] ?? '')) ?? ''));
                if ($pageText !== '') {
                    db_exec(
                        'INSERT INTO item_page_texts (item_id, page_num, is_staging, norm_text) VALUES (?, ?, 1, ?)
                         ON DUPLICATE KEY UPDATE norm_text = VALUES(norm_text)',
                        [$itemId, $pageNum, $pageText]
                    );
                }
                db_exec("UPDATE items SET processing_status = 'processing' WHERE id = ?", [$itemId]);
                admin_json_response(['ok' => true, 'page' => $pageNum]);
            }

            if ($sub === 'api/finalize-pages') {
                $itemId     = (int) ($_POST['item_id'] ?? 0);
                $totalPages = (int) ($_POST['total_pages'] ?? 0);
                if ($itemId <= 0 || $totalPages < 1) {
                    throw new RuntimeException('Invalid item or page count.');
                }
                require_once APP_ROOT . '/includes/pdf_proxy.php';
                for ($p = 1; $p <= $totalPages; $p++) {
                    if (find_staging_page_file($itemId, $p) === null) {
                        rrmdir_recursive(page_staging_dir($itemId));
                        db_exec('DELETE FROM item_page_texts WHERE item_id = ? AND is_staging = 1', [$itemId]);
                        db_exec("UPDATE items SET processing_status = 'failed' WHERE id = ?", [$itemId]);
                        throw new RuntimeException("Missing rendered page {$p} of {$totalPages}. Resume preparation to retry.");
                    }
                }
                atomic_swap_prepared_pages($itemId);
                // Remove stale leftover higher page numbers from older PDF versions
                $dir = page_storage_dir($itemId);
                foreach (glob($dir . '/page-*.*') ?: [] as $fPath) {
                    if (preg_match('/page-(\d+)\.(webp|jpg)$/', basename($fPath), $pm) && (int) $pm[1] > $totalPages) {
                        @unlink($fPath);
                    }
                }
                $tocJsonVal = null;
                if (!empty($_POST['toc_json'])) {
                    $decodedToc = json_decode((string) $_POST['toc_json'], true);
                    if (is_array($decodedToc)) {
                        $cleanToc = [];
                        foreach (array_slice($decodedToc, 0, 400) as $entry) {
                            if (!is_array($entry)) {
                                continue;
                            }
                            $tTitle = mb_substr(trim((string) ($entry['title'] ?? '')), 0, 180);
                            $tPage  = (int) ($entry['page'] ?? 0);
                            $tLvl   = max(0, min(4, (int) ($entry['level'] ?? 0)));
                            if ($tTitle !== '' && $tPage >= 1 && $tPage <= $totalPages) {
                                $cleanToc[] = ['title' => $tTitle, 'page' => $tPage, 'level' => $tLvl];
                            }
                        }
                        if ($cleanToc) {
                            $tocJsonVal = json_encode($cleanToc, JSON_UNESCAPED_UNICODE);
                        }
                    }
                }
                db_exec('DELETE FROM item_page_texts WHERE item_id = ? AND is_staging = 0', [$itemId]);
                db_exec('UPDATE item_page_texts SET is_staging = 0 WHERE item_id = ? AND is_staging = 1 AND page_num <= ?', [$itemId, $totalPages]);
                db_exec('DELETE FROM item_page_texts WHERE item_id = ? AND is_staging = 1', [$itemId]);
                db_exec("UPDATE items SET total_pages = ?, toc_json = COALESCE(?, toc_json), pages_status = 'ready', processing_status = 'idle', processed_at = NOW() WHERE id = ?", [$totalPages, $tocJsonVal, $itemId]);
                log_admin_action($admin, 'pdf_prepare_pages', 'item', $itemId, "Prepared {$totalPages} protected pages");
                admin_json_response(['ok' => true, 'total_pages' => $totalPages, 'message' => admin_bi('সব সুরক্ষিত পৃষ্ঠা প্রস্তুত হয়েছে!', 'Protected pages ready!')]);
            }

            if ($sub === 'api/discard-staging') {
                $itemId = (int) ($_POST['item_id'] ?? 0);
                if ($itemId > 0) {
                    require_once APP_ROOT . '/includes/pdf_proxy.php';
                    rrmdir_recursive(page_staging_dir($itemId));
                    db_exec('DELETE FROM item_page_texts WHERE item_id = ? AND is_staging = 1', [$itemId]);
                    db_exec("UPDATE items SET processing_status = 'failed' WHERE id = ?", [$itemId]);
                }
                admin_json_response(['ok' => true]);
            }

            if ($sub === 'api/test-smtp') {
                $to = trim((string) ($_POST['to'] ?? ''));
                if (!filter_var($to, FILTER_VALIDATE_EMAIL)) {
                    throw new RuntimeException(admin_bi('সঠিক প্রাপক ইমেইল দিন।', 'Enter a valid recipient email address.'));
                }
                $saved = bookexa_smtp_config();
                $passIn = (string) ($_POST['smtp_pass'] ?? '');
                $override = [
                    'host'       => trim((string) ($_POST['smtp_host'] ?? $saved['host'])),
                    'port'       => max(1, (int) ($_POST['smtp_port'] ?? $saved['port'])),
                    'encryption' => trim((string) ($_POST['smtp_encryption'] ?? $saved['encryption'])),
                    'user'       => trim((string) ($_POST['smtp_user'] ?? $saved['user'])),
                    'pass'       => $passIn !== '' ? $passIn : $saved['pass'],
                    'from_name'  => trim((string) ($_POST['smtp_from_name'] ?? $saved['from_name'])),
                    'from_email' => trim((string) ($_POST['smtp_from_email'] ?? $saved['from_email'])),
                ];
                $res = send_bookexa_template_mail(
                    $to,
                    'Bookexa SMTP Test Email',
                    'SMTP Connection Verified',
                    '<p>This is a test email from your <strong>Bookexa</strong> Admin Settings panel sent via the built-in PHP SMTP client.</p>'
                );
                if (!$res['ok']) {
                    // Try again cleanly with override config directly if unsaved form fields differ
                    $res = smtp_send_mail(
                        $to,
                        'Bookexa SMTP Test Email',
                        '<p>This is a test email from your <strong>Bookexa</strong> Admin Settings panel.</p>',
                        '',
                        $override
                    );
                }
                if (!$res['ok']) {
                    admin_json_response(['ok' => false, 'message' => $res['error'] ?: 'SMTP send failed'], 200);
                }
                log_admin_action($admin, 'smtp_test', 'settings', null, 'Sent test email to ' . $to);
                admin_json_response(['ok' => true, 'message' => admin_bi('টেস্ট ইমেইল সফলভাবে পাঠানো হয়েছে!', 'Test email sent successfully to ' . $to)]);
            }

            if ($sub === 'api/cdn-test') {
                $res = media_test_cdn_connection();
                admin_json_response($res, 200);
            }

            if ($sub === 'api/cdn-sync-batch') {
                $retryFailed = !empty($_POST['retry_failed']);
                $cfg = cdn_config();
                $res = media_sync_pending_batch((int) ($cfg['sync_batch'] ?? 10), $retryFailed);
                if (!empty($res['synced'])) {
                    log_admin_action($admin, 'cdn_batch_sync', 'media', null, 'Synced ' . (int) $res['synced'] . ' media file(s)');
                }
                admin_json_response($res, 200);
            }

            if ($sub === 'api/cdn-recheck') {
                $res = media_recheck_cdn_all();
                if ($res['ok']) {
                    log_admin_action($admin, 'cdn_recheck', 'media', null, "Present: {$res['present']}, Missing: {$res['missing']}");
                    $res['message'] = admin_bi(
                        "যাচাই সম্পন্ন: CDN-এ পাওয়া গেছে {$res['present']}টি, অনুপস্থিত {$res['missing']}টি।",
                        "Recheck complete: {$res['present']} present on CDN, {$res['missing']} missing."
                    );
                }
                admin_json_response($res, 200);
            }

            if ($sub === 'api/cdn-pull') {
                $res = media_pull_all_from_cdn();
                if ($res['ok']) {
                    log_admin_action($admin, 'cdn_pull', 'media', null, "Pulled: {$res['pulled']}, Failed: {$res['failed']}");
                    $res['message'] = admin_bi(
                        "CDN থেকে লোকাল /uploads-এ আনা হয়েছে: {$res['pulled']}টি (ব্যর্থ {$res['failed']}টি)।",
                        "Pulled {$res['pulled']} file(s) from CDN to local /uploads ({$res['failed']} failed)."
                    );
                }
                admin_json_response($res, 200);
            }

            if ($sub === 'api/upload-auto-cover') {
                $itemId    = (int) ($_POST['item_id'] ?? 0);
                $overwrite = !empty($_POST['overwrite']) && $_POST['overwrite'] !== '0';
                $item = $itemId > 0 ? db_one('SELECT id, cover FROM items WHERE id = ?', [$itemId]) : null;
                if (!$item) {
                    throw new RuntimeException('Book item not found.');
                }
                if (!isset($_FILES['cover']) || ($_FILES['cover']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
                    throw new RuntimeException('Missing auto-cover upload payload.');
                }
                $oldCover = trim((string) ($item['cover'] ?? ''));
                $hasCustom = $oldCover !== '' && !str_ends_with($oldCover, 'cover-bangla.svg');
                if ($hasCustom && !$overwrite) {
                    admin_json_response([
                        'ok'        => true,
                        'skipped'   => true,
                        'cover'     => $oldCover,
                        'cover_url' => asset($oldCover),
                        'message'   => admin_bi('বইয়ের বিদ্যমান কভার রাখা হয়েছে।', 'Existing book cover preserved.'),
                    ]);
                }
                $rawBytes = (string) file_get_contents($_FILES['cover']['tmp_name']);
                $relCover = store_uploaded_bytes_as_cover($rawBytes);
                db_exec('UPDATE items SET cover = ? WHERE id = ?', [$relCover, $itemId]);
                if ($oldCover !== '' && $oldCover !== $relCover && !str_ends_with($oldCover, 'cover-bangla.svg')) {
                    media_try_delete_unused_image($oldCover);
                }
                log_admin_action($admin, 'pdf_auto_cover', 'item', $itemId, 'Saved Page 1 auto cover: ' . $relCover);
                admin_json_response([
                    'ok'        => true,
                    'cover'     => $relCover,
                    'cover_url' => asset($relCover),
                    'message'   => admin_bi('পৃষ্ঠা ১ থেকে ৩:৪ কভার তৈরি ও CDN-এ সিঙ্ক হয়েছে।', '3:4 cover rendered from Page 1 and synced to CDN.'),
                ]);
            }

            if ($sub === 'api/import-csv-batch') {
                $preview = $_SESSION['csv_import_preview'] ?? null;
                $limit = max(1, min(50, (int) ($_POST['limit'] ?? 15)));
                if (!is_array($preview) || empty($preview['file']) || !is_file((string) $preview['file'])) {
                    throw new RuntimeException('No active CSV import preview session.');
                }
                $rawJson = (string) file_get_contents((string) $preview['file']);
                $decoded = json_decode($rawJson, true);
                if (!is_array($decoded)) {
                    throw new RuntimeException('Invalid staged CSV data.');
                }
                $rows = $decoded['rows'] ?? [];
                $autoCreate = !empty($preview['auto_create']);
                $processed = 0;
                $imported = 0;
                $nextRows = [];
                foreach ($rows as $row) {
                    if (!empty($row['errors'])) {
                        continue;
                    }
                    if ($processed >= $limit) {
                        $nextRows[] = $row;
                        continue;
                    }
                    $processed++;
                    $fRes = admin_find_or_create_folder_path((string) ($row['folder_path'] ?? ''), $autoCreate);
                    if (!$fRes['ok']) {
                        continue;
                    }
                    $slug = generate_unique_item_slug((string) ($row['slug'] ?: $row['title_en']));
                    $validDrive = ($row['drive_link'] ?? '') !== '' ? validate_drive_url((string) $row['drive_link'], true) : null;
                    db_exec(
                        'INSERT INTO items (folder_id, slug, title_bn, title_en, author_bn, author_en, description_bn, description_en, cover, drive_link, price, original_price, is_free, sample_pages, is_active)
                         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1)',
                        [
                            $fRes['folder_id'], $slug, $row['title_bn'], $row['title_en'],
                            $row['author_bn'], $row['author_en'], $row['description_bn'], $row['description_en'],
                            'uploads/covers/cover-bangla.svg', $validDrive,
                            (float) $row['price'], $row['original_price'], (int) $row['is_free'], (int) $row['sample_pages'],
                        ]
                    );
                    $imported++;
                }
                $decoded['rows'] = $nextRows;
                @file_put_contents((string) $preview['file'], json_encode($decoded, JSON_UNESCAPED_UNICODE), LOCK_EX);
                if ($imported > 0) {
                    invalidate_folder_and_sitemap_cache();
                }
                if (empty($nextRows)) {
                    @unlink((string) $preview['file']);
                    unset($_SESSION['csv_import_preview']);
                    log_admin_action($admin, 'csv_bulk_import', 'item', null, 'Completed batch CSV import');
                }
                admin_json_response([
                    'ok'        => true,
                    'processed' => $processed,
                    'imported'  => $imported,
                    'remaining' => count($nextRows),
                    'message'   => empty($nextRows)
                        ? admin_bi('সব বই সফলভাবে ইমপোর্ট হয়েছে!', 'CSV batch import complete!')
                        : "Imported {$imported} item(s)...",
                ]);
            }

            admin_json_response(['ok' => false, 'message' => 'Unknown API endpoint'], 404);
        } catch (Throwable $e) {
            admin_json_response(['ok' => false, 'message' => $e->getMessage()], 400);
        }
    }

    // 5. Section Routing & Role Enforcement
    $allowedSections = ['dashboard', 'banners', 'folders', 'pdfs', 'import-csv', 'prepare-pages', 'prepare-queue', 'orders', 'inbox', 'coupons', 'reviews', 'reports', 'users', 'logs', 'media', 'system-health', 'settings', 'admins', 'account-security'];
    $section = in_array($sub, $allowedSections, true) ? $sub : 'dashboard';
    $viewMap = [
        'prepare-pages'    => 'prepare_pages',
        'prepare-queue'    => 'prepare_queue',
        'import-csv'       => 'import_csv',
        'system-health'    => 'system_health',
        'account-security' => 'account_security',
    ];
    $sectionFile = $viewMap[$section] ?? $section;

    if (!empty($admin['must_change_password']) && $section !== 'account-security') {
        flash('warning', admin_bi('নিরাপত্তার জন্য প্রথমে আপনার অ্যাডমিন পাসওয়ার্ড পরিবর্তন করুন।', 'For security, please change your initial password first.'));
        redirect('/admin/account-security');
    }

    if (!admin_can($admin, $section)) {
        log_security_event('admin_perm_denied', (string) $admin['email'], 'Denied access to /admin/' . $section . ' for role ' . $admin['role']);
        flash('danger', admin_bi('আপনার ভূমিকার জন্য এই বিভাগটি অনুমোদিত নয়।', 'Access denied: your admin role does not have permission for this section.'));
        redirect('/admin');
    }

    // 5a. Sample CSV Download (/admin/import-csv?sample=1)
    if ($section === 'import-csv' && ($_GET['sample'] ?? '') === '1') {
        header('Content-Type: text/csv; charset=UTF-8');
        header('Content-Disposition: attachment; filename="bookexa-sample-import.csv"');
        $out = fopen('php://output', 'w');
        fwrite($out, "\xEF\xBB\xBF");
        fputcsv($out, ['folder_path', 'title_bn', 'title_en', 'author', 'price', 'is_free', 'sample_pages', 'drive_link', 'description']);
        fputcsv($out, ['Academic/SSC/Bangla', 'এসএসসি বাংলা ১ম পত্র গাইড', 'SSC Bangla 1st Paper Guide', 'Prof. Karim', '120', '0', '5', 'https://drive.google.com/file/d/1A2B3C4D5E6F7G8H9I0J/view?usp=sharing', 'Complete board exam preparation guide.']);
        fputcsv($out, ['Academic/Admission', 'ফ্রি সাধারণ জ্ঞান নোট', 'Free General Knowledge Digest', 'Bookexa Editorial', '0', '1', '0', '', 'Free revision summary notes.']);
        fclose($out);
        exit;
    }

    // 5aa. Download Stored Auto Backup (.sql) for Super Admins
    if ($section === 'system-health' && !empty($_GET['download_backup'])) {
        if (($admin['role'] ?? '') !== 'superadmin') {
            flash('danger', 'Forbidden: Super Admin required.');
            redirect('/admin');
        }
        require_once APP_ROOT . '/includes/maintenance.php';
        $fname = basename((string) $_GET['download_backup']);
        if (!preg_match('/^bookexa-auto-[0-9-]+\.sql$/', $fname)) {
            render_error_page(404);
        }
        $fpath = daily_backups_dir() . '/' . $fname;
        if (!is_file($fpath)) {
            render_error_page(404);
        }
        log_admin_action($admin, 'db_backup_download', 'system', null, 'Downloaded auto backup ' . $fname);
        header('Content-Type: application/sql; charset=UTF-8');
        header('Content-Disposition: attachment; filename="' . $fname . '"');
        readfile($fpath);
        exit;
    }

    // 5b. CSV Export Stream for Reports (before any HTML layout output)
    if ($section === 'reports' && !empty($_GET['export'])) {
        $expType = (string) $_GET['export'];
        $rawDays = (int) ($_GET['days'] ?? 30);
        $days = in_array($rawDays, [7, 30, 90], true) ? $rawDays : 30;
        $filename = 'bookexa-' . preg_replace('/[^a-z0-9_-]/', '', $expType) . '-' . date('Ymd') . '.csv';
        header('Content-Type: text/csv; charset=UTF-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        $out = fopen('php://output', 'w');
        fwrite($out, "\xEF\xBB\xBF");

        if ($expType === 'revenue_daily') {
            fputcsv($out, ['Date', 'Approved Orders', 'Revenue (BDT)']);
            $rows = db_all("SELECT DATE(created_at) AS d, COUNT(*) AS cnt, COALESCE(SUM(amount), 0) AS rev FROM orders WHERE status = 'approved' AND created_at >= DATE_SUB(CURDATE(), INTERVAL ? DAY) GROUP BY DATE(created_at) ORDER BY d ASC", [$days - 1]);
            foreach ($rows as $r) {
                fputcsv($out, [$r['d'], (int) $r['cnt'], number_format((float) $r['rev'], 2, '.', '')]);
            }
        } elseif ($expType === 'revenue_monthly') {
            fputcsv($out, ['Month', 'Approved Orders', 'Revenue (BDT)']);
            $rows = db_all("SELECT DATE_FORMAT(created_at, '%Y-%m') AS ym, COUNT(*) AS cnt, COALESCE(SUM(amount), 0) AS rev FROM orders WHERE status = 'approved' AND created_at >= DATE_SUB(CURDATE(), INTERVAL 11 MONTH) GROUP BY DATE_FORMAT(created_at, '%Y-%m') ORDER BY ym ASC");
            foreach ($rows as $r) {
                fputcsv($out, [$r['ym'], (int) $r['cnt'], number_format((float) $r['rev'], 2, '.', '')]);
            }
        } elseif ($expType === 'top_selling') {
            fputcsv($out, ['Item ID', 'Title (EN)', 'Title (BN)', 'Approved Sales', 'Revenue (BDT)']);
            $rows = db_all("SELECT i.id, i.title_en, i.title_bn, COUNT(o.id) AS sales_count, COALESCE(SUM(o.amount), 0) AS rev FROM items i LEFT JOIN orders o ON o.item_id = i.id AND o.status = 'approved' GROUP BY i.id HAVING sales_count > 0 ORDER BY sales_count DESC, rev DESC");
            foreach ($rows as $r) {
                fputcsv($out, [(int) $r['id'], $r['title_en'], $r['title_bn'], (int) $r['sales_count'], number_format((float) $r['rev'], 2, '.', '')]);
            }
        } elseif ($expType === 'most_read') {
            fputcsv($out, ['Item ID', 'Title (EN)', 'Title (BN)', 'Total Reader Events', 'Unique Readers']);
            $rows = db_all("SELECT i.id, i.title_en, i.title_bn, COUNT(rl.id) AS reads_cnt, COUNT(DISTINCT rl.user_id) AS uniq_cnt FROM items i LEFT JOIN reading_logs rl ON rl.item_id = i.id GROUP BY i.id HAVING reads_cnt > 0 ORDER BY reads_cnt DESC");
            foreach ($rows as $r) {
                fputcsv($out, [(int) $r['id'], $r['title_en'], $r['title_bn'], (int) $r['reads_cnt'], (int) $r['uniq_cnt']]);
            }
        } elseif ($expType === 'new_users') {
            fputcsv($out, ['Date', 'New Users']);
            $rows = db_all("SELECT DATE(created_at) AS d, COUNT(*) AS cnt FROM users WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL ? DAY) GROUP BY DATE(created_at) ORDER BY d ASC", [$days - 1]);
            foreach ($rows as $r) {
                fputcsv($out, [$r['d'], (int) $r['cnt']]);
            }
        } elseif ($expType === 'coupons') {
            fputcsv($out, ['Code', 'Type', 'Value', 'Total Orders', 'Approved Orders', 'Discount Given (BDT)', 'Revenue (BDT)']);
            $rows = db_all("SELECT c.code, c.discount_type, c.discount_value, COUNT(o.id) AS ocnt, SUM(CASE WHEN o.status = 'approved' THEN 1 ELSE 0 END) AS acnt, COALESCE(SUM(CASE WHEN o.status NOT IN ('rejected','cancelled','expired') THEN o.discount_amount ELSE 0 END), 0) AS tdisc, COALESCE(SUM(CASE WHEN o.status = 'approved' THEN o.amount ELSE 0 END), 0) AS trev FROM coupons c LEFT JOIN orders o ON o.coupon_id = c.id GROUP BY c.id ORDER BY ocnt DESC");
            foreach ($rows as $r) {
                fputcsv($out, [$r['code'], $r['discount_type'], $r['discount_value'], (int) $r['ocnt'], (int) $r['acnt'], number_format((float) $r['tdisc'], 2, '.', ''), number_format((float) $r['trev'], 2, '.', '')]);
            }
        } elseif ($expType === 'payment_methods') {
            fputcsv($out, ['Payment Method', 'Total Orders', 'Approved Orders', 'Approved Revenue (BDT)']);
            $rows = db_all("SELECT payment_method, COUNT(*) AS total_cnt, SUM(CASE WHEN status = 'approved' THEN 1 ELSE 0 END) AS app_cnt, COALESCE(SUM(CASE WHEN status = 'approved' THEN amount ELSE 0 END), 0) AS rev FROM orders GROUP BY payment_method ORDER BY rev DESC");
            foreach ($rows as $r) {
                fputcsv($out, [strtoupper((string) $r['payment_method']), (int) $r['total_cnt'], (int) $r['app_cnt'], number_format((float) $r['rev'], 2, '.', '')]);
            }
        } elseif ($expType === 'rejections_refunds') {
            fputcsv($out, ['Order Code', 'Date', 'Customer', 'Item', 'Method', 'Amount (BDT)', 'Status', 'Reason']);
            $rows = db_all("SELECT o.order_code, o.created_at, u.email, i.title_en, o.payment_method, o.amount, o.status, o.admin_note FROM orders o JOIN users u ON u.id = o.user_id JOIN items i ON i.id = o.item_id WHERE o.status IN ('rejected', 'cancelled', 'expired') ORDER BY o.id DESC");
            foreach ($rows as $r) {
                fputcsv($out, [$r['order_code'], $r['created_at'], $r['email'], $r['title_en'], $r['payment_method'], number_format((float) $r['amount'], 2, '.', ''), $r['status'], $r['admin_note']]);
            }
        } elseif ($expType === 'reading_engagement') {
            fputcsv($out, ['Item ID', 'Title (EN)', 'Title (BN)', 'Total Pages', 'Reads', 'Tracked Readers', 'Avg Pages Read', 'Drop-off Page']);
            $rows = db_all("SELECT i.id, i.title_en, i.title_bn, i.total_pages, COALESCE(i.read_count,0) AS read_count, COUNT(rp.id) AS tracked, ROUND(COALESCE(AVG(rp.last_page),0),1) AS avg_p, ROUND(COALESCE(AVG(CASE WHEN rp.is_finished=0 THEN rp.last_page ELSE NULL END),0),1) AS drop_p FROM items i LEFT JOIN reading_progress rp ON rp.item_id=i.id GROUP BY i.id HAVING tracked>0 OR read_count>0 ORDER BY tracked DESC, read_count DESC");
            foreach ($rows as $r) {
                fputcsv($out, [(int) $r['id'], $r['title_en'], $r['title_bn'], $r['total_pages'], (int) $r['read_count'], (int) $r['tracked'], $r['avg_p'], $r['drop_p']]);
            }
        } elseif ($expType === 'sample_conversion') {
            fputcsv($out, ['Item ID', 'Title (EN)', 'Sample Readers', 'Converted Buyers', 'Conversion Rate (%)']);
            $rows = db_all("SELECT i.id, i.title_en, COUNT(DISTINCT rl.user_id) AS sr, COUNT(DISTINCT CASE WHEN o.status='approved' THEN rl.user_id ELSE NULL END) AS cb FROM reading_logs rl JOIN items i ON i.id=rl.item_id LEFT JOIN orders o ON o.user_id=rl.user_id AND o.item_id=rl.item_id AND o.status='approved' WHERE rl.is_sample=1 AND i.is_free=0 AND i.price>0 GROUP BY i.id ORDER BY sr DESC");
            foreach ($rows as $r) {
                $rate = (int) $r['sr'] > 0 ? round(((int) $r['cb'] / (int) $r['sr']) * 100, 1) : 0;
                fputcsv($out, [(int) $r['id'], $r['title_en'], (int) $r['sr'], (int) $r['cb'], $rate]);
            }
        } elseif ($expType === 'revenue_by_folder') {
            fputcsv($out, ['Folder (EN)', 'Folder (BN)', 'Approved Orders', 'Revenue (BDT)']);
            $rows = db_all("SELECT COALESCE(f.name_en,'Unassigned') AS ne, COALESCE(f.name_bn,'অনির্ধারিত') AS nb, COUNT(o.id) AS oc, COALESCE(SUM(o.amount),0) AS rev FROM orders o JOIN items i ON i.id=o.item_id LEFT JOIN folders f ON f.id=i.folder_id WHERE o.status='approved' GROUP BY f.id ORDER BY rev DESC");
            foreach ($rows as $r) {
                fputcsv($out, [$r['ne'], $r['nb'], (int) $r['oc'], number_format((float) $r['rev'], 2, '.', '')]);
            }
        } elseif ($expType === 'top_searches') {
            fputcsv($out, ['Query', 'Hits', 'Last Searched']);
            foreach (db_all('SELECT query_text, hits, last_searched_at FROM search_queries ORDER BY hits DESC LIMIT 200') as $r) {
                fputcsv($out, [$r['query_text'], (int) $r['hits'], $r['last_searched_at']]);
            }
        } elseif ($expType === 'zero_searches') {
            fputcsv($out, ['Query', 'Hits', 'Last Searched']);
            foreach (db_all('SELECT query_text, hits, last_searched_at FROM search_misses ORDER BY hits DESC LIMIT 200') as $r) {
                fputcsv($out, [$r['query_text'], (int) $r['hits'], $r['last_searched_at']]);
            }
        }
        fclose($out);
        exit;
    }

    // 5c. Filtered Orders CSV Export
    if ($section === 'orders' && ($_GET['export'] ?? '') === 'csv') {
        $flt = admin_orders_filter_query();
        $rows = db_all(
            "SELECT o.*, u.name AS user_name, u.email AS user_email, u.phone AS user_phone, i.title_en
             FROM orders o
             JOIN users u ON u.id = o.user_id
             JOIN items i ON i.id = o.item_id
             {$flt['whereSql']}
             ORDER BY o.id DESC",
            $flt['params']
        );
        header('Content-Type: text/csv; charset=UTF-8');
        header('Content-Disposition: attachment; filename="bookexa-orders-' . date('Ymd-His') . '.csv"');
        $out = fopen('php://output', 'w');
        fwrite($out, "\xEF\xBB\xBF");
        fputcsv($out, ['Order Code', 'Created At', 'Customer Name', 'Customer Email', 'Customer Phone', 'Book Title', 'Original Price', 'Coupon', 'Discount', 'Final Amount', 'Payment Method', 'Sender Number', 'TrxID', 'Gateway Ref', 'Status', 'Public Note / Reason', 'Internal Note']);
        foreach ($rows as $r) {
            fputcsv($out, [
                $r['order_code'], $r['created_at'], $r['user_name'], $r['user_email'], $r['user_phone'], $r['title_en'],
                $r['original_amount'], $r['coupon_code'], $r['discount_amount'], $r['amount'],
                $r['payment_method'], $r['sender_number'], $r['transaction_id'], $r['gateway_ref'],
                $r['status'], $r['admin_note'], $r['internal_notes'],
            ]);
        }
        fclose($out);
        exit;
    }

    // Lazy background media queue sync on admin GET traffic
    if ($_SERVER['REQUEST_METHOD'] === 'GET' && empty($_GET['export'])) {
        media_lazy_sync_tick();
    }

    // 6. Handle POST CRUD Actions
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        verify_csrf();
        $action = (string) ($_POST['action'] ?? '');
        $actionPermMap = [
            'save_banner' => 'banners', 'toggle_banner' => 'banners', 'delete_banner' => 'banners',
            'save_folder' => 'folders', 'move_folder' => 'folders', 'toggle_folder' => 'folders', 'delete_folder' => 'folders',
            'save_pdf' => 'pdfs', 'duplicate_pdf' => 'pdfs', 'toggle_pdf' => 'pdfs', 'delete_pdf' => 'pdfs', 'bulk_pdfs' => 'pdfs',
            'csv_preview' => 'import-csv', 'csv_commit_all' => 'import-csv', 'csv_clear_preview' => 'import-csv',
            'toggle_user_block' => 'users', 'force_logout_user' => 'users', 'unsuspend_user' => 'users',
            'contact_status' => 'inbox', 'inbox_reply' => 'inbox', 'inbox_delete' => 'inbox',
            'review_order' => 'orders', 'bulk_orders' => 'orders', 'save_order_note' => 'orders',
            'save_coupon' => 'coupons', 'toggle_coupon' => 'coupons', 'delete_coupon' => 'coupons',
            'review_moderate' => 'reviews', 'review_delete' => 'reviews',
            'save_settings' => 'settings', 'regenerate_cron_key' => 'settings',
            'clean_cache' => 'system-health', 'run_daily_backup_now' => 'system-health',
            'download_db_backup' => 'system-health', 'export_site_package' => 'admins',
            'cdn_single_sync' => 'media',
            'save_admin' => 'admins', 'toggle_admin' => 'admins', 'save_ip_allowlist' => 'admins',
            'change_my_password' => 'account-security', 'start_totp_setup' => 'account-security',
            'confirm_totp_setup' => 'account-security', 'regen_backup_codes' => 'account-security',
            'disable_totp' => 'account-security', 'signout_other_sessions' => 'account-security', 'signout_all_sessions' => 'account-security',
        ];
        $reqPerm = $actionPermMap[$action] ?? $section;
        if (!admin_can($admin, $reqPerm)) {
            log_security_event('admin_perm_denied', (string) $admin['email'], 'Denied POST action ' . $action . ' for role ' . $admin['role']);
            flash('danger', admin_bi('আপনার ভূমিকার জন্য এই অ্যাকশন অনুমোদিত নয়।', 'Forbidden: your role lacks permission for this action.'));
            redirect('/admin');
        }
        if (($admin['role'] ?? '') === 'support' && in_array($action, ['toggle_user_block', 'force_logout_user', 'unsuspend_user'], true)) {
            log_security_event('admin_perm_denied', (string) $admin['email'], 'Support role attempted mutating user action: ' . $action);
            flash('danger', admin_bi('সাপোর্ট রোলের জন্য ব্যবহারকারী পরিবর্তন অনুমোদিত নয় (শুধুমাত্র দেখুন)।', 'Support role has read-only access to users.'));
            redirect('/admin/users');
        }

        try {
            // --- Banners ---
            if ($action === 'save_banner') {
                $id = !empty($_POST['id']) ? (int) $_POST['id'] : null;
                $titleBn = trim((string) ($_POST['title_bn'] ?? ''));
                $titleEn = trim((string) ($_POST['title_en'] ?? ''));
                $subBn = trim((string) ($_POST['subtitle_bn'] ?? ''));
                $subEn = trim((string) ($_POST['subtitle_en'] ?? ''));
                $ctaBn = trim((string) ($_POST['cta_bn'] ?? 'এখনই দেখুন'));
                $ctaEn = trim((string) ($_POST['cta_en'] ?? 'Explore Now'));
                $linkUrl = trim((string) ($_POST['link_url'] ?? '/pdf'));
                $sortOrder = (int) ($_POST['sort_order'] ?? 0);
                $publishAt = admin_parse_datetime($_POST['publish_at'] ?? $_POST['starts_at'] ?? null);
                $unpublishAt = admin_parse_datetime($_POST['unpublish_at'] ?? $_POST['ends_at'] ?? null);
                $startsAt = $publishAt;
                $endsAt = $unpublishAt;
                $isActive = isset($_POST['is_active']) ? 1 : 0;

                if ($titleBn === '' || $titleEn === '') {
                    throw new RuntimeException(admin_bi('বাংলা ও ইংরেজি উভয় শিরোনাম আবশ্যক।', 'Both Bangla and English banner titles are required.'));
                }
                if ($startsAt !== null && $endsAt !== null && strtotime($endsAt) < strtotime($startsAt)) {
                    throw new RuntimeException(admin_bi('শেষের তারিখ শুরুর তারিখের আগে হতে পারবে না।', 'End date must be after start date.'));
                }

                $uploadedImg = safe_admin_image_upload('image', 'banners', 1400);
                $uploadedMobile = safe_admin_image_upload('mobile_image', 'banners', 900);

                if ($id === null) {
                    $imgPath = $uploadedImg ?: 'uploads/banners/banner-1.svg';
                    db_exec(
                        'INSERT INTO banners (title_bn, title_en, subtitle_bn, subtitle_en, cta_bn, cta_en, link_url, image, mobile_image, sort_order, starts_at, ends_at, publish_at, unpublish_at, is_active)
                         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
                        [$titleBn, $titleEn, $subBn, $subEn, $ctaBn, $ctaEn, $linkUrl, $imgPath, $uploadedMobile, $sortOrder, $startsAt, $endsAt, $publishAt, $unpublishAt, $isActive]
                    );
                    $newId = (int) db()->lastInsertId();
                    log_admin_action($admin, 'banner_create', 'banner', $newId, $titleEn);
                    flash('success', admin_bi('ব্যানার তৈরি হয়েছে।', 'Banner created.'));
                } else {
                    $existing = db_one('SELECT image, mobile_image FROM banners WHERE id = ?', [$id]);
                    $oldImg = $existing['image'] ?? null;
                    $oldMob = $existing['mobile_image'] ?? null;
                    $imgPath = $uploadedImg ?: ($oldImg ?: 'uploads/banners/banner-1.svg');
                    $mobPath = $uploadedMobile ?: $oldMob;
                    $clearedMob = !empty($_POST['clear_mobile_image']);
                    if ($clearedMob) {
                        $mobPath = null;
                    }
                    db_exec(
                        'UPDATE banners SET title_bn=?, title_en=?, subtitle_bn=?, subtitle_en=?, cta_bn=?, cta_en=?, link_url=?, image=?, mobile_image=?, sort_order=?, starts_at=?, ends_at=?, publish_at=?, unpublish_at=?, is_active=? WHERE id=?',
                        [$titleBn, $titleEn, $subBn, $subEn, $ctaBn, $ctaEn, $linkUrl, $imgPath, $mobPath, $sortOrder, $startsAt, $endsAt, $publishAt, $unpublishAt, $isActive, $id]
                    );
                    if ($uploadedImg && $oldImg && $oldImg !== $imgPath) {
                        media_try_delete_unused_image((string) $oldImg);
                    }
                    if (($uploadedMobile || $clearedMob) && $oldMob && $oldMob !== $mobPath) {
                        media_try_delete_unused_image((string) $oldMob);
                    }
                    log_admin_action($admin, 'banner_update', 'banner', $id, $titleEn);
                    flash('success', admin_bi('ব্যানার হালনাগাদ হয়েছে।', 'Banner updated.'));
                }
                redirect(admin_return_url('/admin/banners'));
            }

            if ($action === 'toggle_banner') {
                $id = (int) ($_POST['id'] ?? 0);
                db_exec('UPDATE banners SET is_active = 1 - is_active WHERE id = ?', [$id]);
                log_admin_action($admin, 'banner_toggle', 'banner', $id, 'Toggled active state');
                flash('success', admin_bi('ব্যানারের অবস্থা পরিবর্তন হয়েছে।', 'Banner status updated.'));
                redirect(admin_return_url('/admin/banners'));
            }

            if ($action === 'delete_banner') {
                $id = (int) ($_POST['id'] ?? 0);
                $existing = db_one('SELECT image, mobile_image FROM banners WHERE id = ?', [$id]);
                db_exec('DELETE FROM banners WHERE id = ?', [$id]);
                if ($existing) {
                    media_try_delete_unused_image($existing['image'] ?? null);
                    media_try_delete_unused_image($existing['mobile_image'] ?? null);
                }
                log_admin_action($admin, 'banner_delete', 'banner', $id, 'Deleted banner #' . $id);
                flash('success', admin_bi('ব্যানার মুছে ফেলা হয়েছে।', 'Banner deleted.'));
                redirect(admin_return_url('/admin/banners'));
            }

            // --- Folders ---
            if ($action === 'save_folder') {
                $id = !empty($_POST['id']) ? (int) $_POST['id'] : null;
                $parentId = !empty($_POST['parent_id']) ? (int) $_POST['parent_id'] : null;
                $nameBn = trim((string) ($_POST['name_bn'] ?? ''));
                $nameEn = trim((string) ($_POST['name_en'] ?? ''));
                $slugInput = trim((string) ($_POST['slug'] ?? ''));
                $descBn = trim((string) ($_POST['description_bn'] ?? ''));
                $descEn = trim((string) ($_POST['description_en'] ?? ''));
                $accent = trim((string) ($_POST['accent_color'] ?? '#232766'));
                $sortOrder = (int) ($_POST['sort_order'] ?? 0);
                $publishAt = admin_parse_datetime($_POST['publish_at'] ?? null);
                $unpublishAt = admin_parse_datetime($_POST['unpublish_at'] ?? null);
                $isActive = isset($_POST['is_active']) ? 1 : 0;

                if ($nameBn === '' || $nameEn === '') {
                    throw new RuntimeException(admin_bi('বাংলা ও ইংরেজি উভয় ফোল্ডার নাম আবশ্যক।', 'Both Bangla and English folder names are required.'));
                }
                if ($publishAt !== null && $unpublishAt !== null && strtotime($unpublishAt) < strtotime($publishAt)) {
                    throw new RuntimeException(admin_bi('অপ্রকাশের সময় প্রকাশের সময়ের পর হতে হবে।', 'Unpublish time must be after publish time.'));
                }
                if ($id !== null && $parentId !== null && is_folder_descendant($parentId, $id)) {
                    throw new RuntimeException(admin_bi(
                        'একটি ফোল্ডারকে নিজের বা নিজ সাব-ফোল্ডারের ভেতরে রাখা যাবে না।',
                        'Cannot move a folder inside itself or its own subfolder.'
                    ));
                }

                $slug = generate_unique_folder_slug($slugInput !== '' ? $slugInput : $nameEn, $parentId, $id);
                $croppedRaw = trim((string) ($_POST['cropped_thumbnail'] ?? ''));
                $uploadedThumb = $croppedRaw !== ''
                    ? save_cropped_base64_image($croppedRaw, 'folders')
                    : safe_admin_image_upload('thumbnail', 'folders', 800);

                if ($id === null) {
                    $thumb = $uploadedThumb ?: 'uploads/folders/academic.svg';
                    db_exec(
                        'INSERT INTO folders (parent_id, slug, name_bn, name_en, description_bn, description_en, thumbnail, accent_color, sort_order, is_active, publish_at, unpublish_at)
                         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
                        [$parentId, $slug, $nameBn, $nameEn, $descBn, $descEn, $thumb, $accent, $sortOrder, $isActive, $publishAt, $unpublishAt]
                    );
                    $newId = (int) db()->lastInsertId();
                    log_admin_action($admin, 'folder_create', 'folder', $newId, $nameEn . ' (/' . $slug . ')');
                    flash('success', admin_bi('ফোল্ডার তৈরি হয়েছে: /', 'Folder created with slug /') . $slug);
                } else {
                    $existing = db_one('SELECT thumbnail FROM folders WHERE id = ?', [$id]);
                    $oldThumb = $existing['thumbnail'] ?? null;
                    $thumb = $uploadedThumb ?: ($oldThumb ?: 'uploads/folders/academic.svg');
                    db_exec(
                        'UPDATE folders SET parent_id=?, slug=?, name_bn=?, name_en=?, description_bn=?, description_en=?, thumbnail=?, accent_color=?, sort_order=?, is_active=?, publish_at=?, unpublish_at=? WHERE id=?',
                        [$parentId, $slug, $nameBn, $nameEn, $descBn, $descEn, $thumb, $accent, $sortOrder, $isActive, $publishAt, $unpublishAt, $id]
                    );
                    if ($uploadedThumb && $oldThumb && $oldThumb !== $thumb) {
                        media_try_delete_unused_image((string) $oldThumb);
                    }
                    log_admin_action($admin, 'folder_update', 'folder', $id, $nameEn . ' (/' . $slug . ')');
                    flash('success', admin_bi('ফোল্ডার হালনাগাদ হয়েছে।', 'Folder updated.'));
                }
                invalidate_folder_and_sitemap_cache();
                redirect(admin_return_url('/admin/folders'));
            }

            if ($action === 'move_folder') {
                $id = (int) ($_POST['id'] ?? 0);
                $newParentId = !empty($_POST['parent_id']) ? (int) $_POST['parent_id'] : null;
                if ($newParentId !== null && is_folder_descendant($newParentId, $id)) {
                    throw new RuntimeException(admin_bi(
                        'অবৈধ স্থানান্তর: নির্বাচিত অভিভাবক ফোল্ডারটি এই ফোল্ডারের ভেতরেই আছে।',
                        'Invalid move: target parent is inside this folder.'
                    ));
                }
                $curr = db_one('SELECT slug, name_en FROM folders WHERE id = ?', [$id]);
                if ($curr) {
                    $uniqueSlug = generate_unique_folder_slug($curr['slug'], $newParentId, $id);
                    db_exec('UPDATE folders SET parent_id = ?, slug = ? WHERE id = ?', [$newParentId, $uniqueSlug, $id]);
                    invalidate_folder_and_sitemap_cache();
                    log_admin_action($admin, 'folder_move', 'folder', $id, $curr['name_en'] . ' -> parent #' . ($newParentId ?? 0));
                    flash('success', admin_bi('ফোল্ডার সরানো হয়েছে।', 'Folder moved.'));
                }
                redirect(admin_return_url('/admin/folders'));
            }

            if ($action === 'toggle_folder') {
                $id = (int) ($_POST['id'] ?? 0);
                db_exec('UPDATE folders SET is_active = 1 - is_active WHERE id = ?', [$id]);
                invalidate_folder_and_sitemap_cache();
                log_admin_action($admin, 'folder_toggle', 'folder', $id, 'Toggled visibility');
                flash('success', admin_bi('ফোল্ডারের দৃশ্যমানতা পরিবর্তন হয়েছে।', 'Folder visibility updated.'));
                redirect(admin_return_url('/admin/folders'));
            }

            if ($action === 'delete_folder') {
                $id = (int) ($_POST['id'] ?? 0);
                $allFolders = db_all('SELECT id, parent_id, thumbnail FROM folders');
                $thumbsToCheck = [];
                $queue = [$id];
                while ($queue) {
                    $fid = array_shift($queue);
                    foreach ($allFolders as $fr) {
                        if ((int) $fr['id'] === $fid && !empty($fr['thumbnail'])) {
                            $thumbsToCheck[] = (string) $fr['thumbnail'];
                        }
                        if ($fr['parent_id'] !== null && (int) $fr['parent_id'] === $fid) {
                            $queue[] = (int) $fr['id'];
                        }
                    }
                }
                db_exec('DELETE FROM folders WHERE id = ?', [$id]);
                foreach (array_unique($thumbsToCheck) as $oldT) {
                    media_try_delete_unused_image($oldT);
                }
                invalidate_folder_and_sitemap_cache();
                log_admin_action($admin, 'folder_delete', 'folder', $id, 'Deleted folder #' . $id);
                flash('success', admin_bi('ফোল্ডার মুছে ফেলা হয়েছে।', 'Folder deleted.'));
                redirect(admin_return_url('/admin/folders'));
            }

            // --- PDFs / Items ---
            if ($action === 'save_pdf') {
                $id = !empty($_POST['id']) ? (int) $_POST['id'] : null;
                $folderId = !empty($_POST['folder_id']) ? (int) $_POST['folder_id'] : null;
                $titleBn = trim((string) ($_POST['title_bn'] ?? ''));
                $titleEn = trim((string) ($_POST['title_en'] ?? ''));
                $slugInput = trim((string) ($_POST['slug'] ?? ''));
                $authorBn = trim((string) ($_POST['author_bn'] ?? ''));
                $authorEn = trim((string) ($_POST['author_en'] ?? ''));
                $descBn = trim((string) ($_POST['description_bn'] ?? ''));
                $descEn = trim((string) ($_POST['description_en'] ?? ''));
                $driveInput = trim((string) ($_POST['drive_link'] ?? ''));
                $sampleDriveInput = trim((string) ($_POST['sample_drive_link'] ?? ''));
                $isFree = isset($_POST['is_free']) ? 1 : 0;
                $price = $isFree ? 0.00 : max(0.00, (float) ($_POST['price'] ?? 0));
                $origPrice = !empty($_POST['original_price']) ? (float) $_POST['original_price'] : null;
                $samplePages = max(0, (int) ($_POST['sample_pages'] ?? 4));
                $totalPages = !empty($_POST['total_pages']) ? max(1, (int) $_POST['total_pages']) : null;
                $isFeatured = isset($_POST['is_featured']) ? 1 : 0;
                $isActive = isset($_POST['is_active']) ? 1 : 0;
                $publishAt = admin_parse_datetime($_POST['publish_at'] ?? null);
                $unpublishAt = admin_parse_datetime($_POST['unpublish_at'] ?? null);
                if ($publishAt !== null && $unpublishAt !== null && strtotime($unpublishAt) < strtotime($publishAt)) {
                    throw new RuntimeException(admin_bi('অপ্রকাশের সময় প্রকাশের সময়ের পর হতে হবে।', 'Unpublish time must be after publish time.'));
                }

                if ($titleBn === '' || $titleEn === '') {
                    throw new RuntimeException(admin_bi('বাংলা ও ইংরেজি উভয় PDF শিরোনাম আবশ্যক।', 'Both Bangla and English PDF titles are required.'));
                }

                $allowLocal = ($id !== null && str_starts_with($driveInput, 'local://'));
                $validDrive = null;
                if ($driveInput !== '') {
                    $validDrive = validate_drive_url($driveInput, $allowLocal);
                    if ($validDrive === null) {
                        throw new RuntimeException(admin_bi(
                            'সঠিক Google Drive URL দিন বা খালি রেখে স্থানীয় কম্পিউটার থেকে পৃষ্ঠা প্রস্তুত করুন।',
                            'Invalid Google Drive URL. Leave blank if preparing pages directly from your computer.'
                        ));
                    }
                }

                $validSampleDrive = null;
                if ($sampleDriveInput !== '') {
                    $validSampleDrive = validate_drive_url($sampleDriveInput, false);
                    if ($validSampleDrive === null) {
                        throw new RuntimeException(admin_bi('নমুনা Google Drive URL সঠিক নয়।', 'Invalid Sample Google Drive URL.'));
                    }
                }

                $slug = generate_unique_item_slug($slugInput !== '' ? $slugInput : $titleEn, $id);
                $uploadedCover = safe_admin_image_upload('cover', 'covers', 800);

                if ($id === null) {
                    $cover = $uploadedCover ?: 'uploads/covers/cover-bangla.svg';
                    db_exec(
                        'INSERT INTO items (folder_id, slug, title_bn, title_en, author_bn, author_en, description_bn, description_en, cover, drive_link, sample_drive_link, price, original_price, is_free, sample_pages, total_pages, is_featured, is_active, publish_at, unpublish_at)
                         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
                        [$folderId, $slug, $titleBn, $titleEn, $authorBn, $authorEn, $descBn, $descEn, $cover, $validDrive, $validSampleDrive, $price, $origPrice, $isFree, $samplePages, $totalPages, $isFeatured, $isActive, $publishAt, $unpublishAt]
                    );
                    $newId = (int) db()->lastInsertId();
                    if ($isActive === 1 && $folderId !== null) {
                        notify_folder_visitors_new_pdf((int) $folderId, $slug, $titleBn, $titleEn);
                    }
                    log_admin_action($admin, 'pdf_create', 'item', $newId, $titleEn);
                    flash('success', admin_bi('PDF যুক্ত হয়েছে (Drive লিংক সুরক্ষিতভাবে সংরক্ষিত)।', 'PDF item added (Drive link securely stored).'));
                } else {
                    $existing = db_one('SELECT cover FROM items WHERE id = ?', [$id]);
                    $oldCover = $existing['cover'] ?? null;
                    $cover = $uploadedCover ?: ($oldCover ?: 'uploads/covers/cover-bangla.svg');
                    db_exec(
                        'UPDATE items SET folder_id=?, slug=?, title_bn=?, title_en=?, author_bn=?, author_en=?, description_bn=?, description_en=?, cover=?, drive_link=?, sample_drive_link=?, price=?, original_price=?, is_free=?, sample_pages=?, total_pages=?, is_featured=?, is_active=?, publish_at=?, unpublish_at=? WHERE id=?',
                        [$folderId, $slug, $titleBn, $titleEn, $authorBn, $authorEn, $descBn, $descEn, $cover, $validDrive, $validSampleDrive, $price, $origPrice, $isFree, $samplePages, $totalPages, $isFeatured, $isActive, $publishAt, $unpublishAt, $id]
                    );
                    if ($uploadedCover && $oldCover && $oldCover !== $cover) {
                        media_try_delete_unused_image((string) $oldCover);
                    }
                    log_admin_action($admin, 'pdf_update', 'item', $id, $titleEn);
                    flash('success', admin_bi('PDF তথ্য হালনাগাদ হয়েছে।', 'PDF item updated.'));
                }
                invalidate_folder_and_sitemap_cache();
                redirect(admin_return_url('/admin/pdfs'));
            }

            if ($action === 'duplicate_pdf') {
                $id = (int) ($_POST['id'] ?? 0);
                $src = db_one('SELECT * FROM items WHERE id = ?', [$id]);
                if (!$src) {
                    throw new RuntimeException(admin_bi('মূল PDF পাওয়া যায়নি।', 'Source PDF item not found.'));
                }
                $newSlug = generate_unique_item_slug(((string) $src['slug']) . '-copy');
                $copyBn = trim((string) $src['title_bn']) . ' (কপি)';
                $copyEn = trim((string) $src['title_en']) . ' (Copy)';
                db_exec(
                    'INSERT INTO items (folder_id, slug, title_bn, title_en, author_bn, author_en, description_bn, description_en, cover, drive_link, sample_drive_link, price, original_price, is_free, sample_pages, total_pages, is_featured, is_active, publish_at, unpublish_at, sort_order)
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 0, 0, ?, ?, ?)',
                    [
                        $src['folder_id'], $newSlug, $copyBn, $copyEn,
                        $src['author_bn'], $src['author_en'], $src['description_bn'], $src['description_en'],
                        $src['cover'], $src['drive_link'], $src['sample_drive_link'],
                        $src['price'], $src['original_price'], (int) $src['is_free'],
                        (int) $src['sample_pages'], $src['total_pages'],
                        $src['publish_at'] ?? null, $src['unpublish_at'] ?? null, (int) $src['sort_order'],
                    ]
                );
                $newId = (int) db()->lastInsertId();
                invalidate_folder_and_sitemap_cache();
                log_admin_action($admin, 'pdf_duplicate', 'item', $newId, 'Duplicated from #' . $id);
                flash('success', admin_bi('PDF-টির কপি তৈরি হয়েছে (খসড়া হিসেবে)।', 'PDF duplicated (saved hidden as draft).'));
                redirect(admin_return_url('/admin/pdfs'));
            }

            if ($action === 'toggle_pdf') {
                $id = (int) ($_POST['id'] ?? 0);
                db_exec('UPDATE items SET is_active = 1 - is_active WHERE id = ?', [$id]);
                invalidate_folder_and_sitemap_cache();
                log_admin_action($admin, 'pdf_toggle', 'item', $id, 'Toggled PDF active state');
                flash('success', admin_bi('PDF অবস্থা পরিবর্তন হয়েছে।', 'PDF active status updated.'));
                redirect(admin_return_url('/admin/pdfs'));
            }

            if ($action === 'delete_pdf') {
                $id = (int) ($_POST['id'] ?? 0);
                $existing = db_one('SELECT cover FROM items WHERE id = ?', [$id]);
                db_exec('DELETE FROM items WHERE id = ?', [$id]);
                if ($existing && !empty($existing['cover'])) {
                    media_try_delete_unused_image((string) $existing['cover']);
                }
                invalidate_folder_and_sitemap_cache();
                log_admin_action($admin, 'pdf_delete', 'item', $id, 'Deleted PDF #' . $id);
                flash('success', admin_bi('PDF মুছে ফেলা হয়েছে।', 'PDF deleted.'));
                redirect(admin_return_url('/admin/pdfs'));
            }

            if ($action === 'bulk_pdfs') {
                $bulkAction = (string) ($_POST['bulk_action'] ?? '');
                $rawIds = $_POST['ids'] ?? [];
                $ids = is_array($rawIds) ? array_values(array_filter(array_map('intval', $rawIds), fn(int $v): bool => $v > 0)) : [];
                if (!$ids) {
                    throw new RuntimeException(admin_bi('অন্তত একটি PDF নির্বাচন করুন।', 'Select at least one PDF item first.'));
                }
                $ph = implode(',', array_fill(0, count($ids), '?'));

                if ($bulkAction === 'activate') {
                    db_exec("UPDATE items SET is_active = 1 WHERE id IN ($ph)", $ids);
                    flash('success', admin_bi('নির্বাচিত PDF সক্রিয় করা হয়েছে।', count($ids) . ' PDF(s) activated.'));
                } elseif ($bulkAction === 'deactivate') {
                    db_exec("UPDATE items SET is_active = 0 WHERE id IN ($ph)", $ids);
                    flash('success', admin_bi('নির্বাচিত PDF নিষ্ক্রিয় করা হয়েছে।', count($ids) . ' PDF(s) deactivated.'));
                } elseif ($bulkAction === 'move') {
                    $targetFolder = !empty($_POST['target_folder_id']) ? (int) $_POST['target_folder_id'] : null;
                    db_exec("UPDATE items SET folder_id = ? WHERE id IN ($ph)", array_merge([$targetFolder], $ids));
                    flash('success', admin_bi('নির্বাচিত PDF নতুন ফোল্ডারে সরানো হয়েছে।', count($ids) . ' PDF(s) moved.'));
                } elseif (in_array($bulkAction, ['price_set', 'price_pct', 'price_sub'], true)) {
                    $val = max(0.0, (float) ($_POST['bulk_price_val'] ?? 0));
                    if ($bulkAction === 'price_set') {
                        db_exec("UPDATE items SET price = ?, is_free = ? WHERE id IN ($ph)", array_merge([round($val, 2), $val <= 0 ? 1 : 0], $ids));
                    } elseif ($bulkAction === 'price_pct') {
                        $pct = min(100.0, $val);
                        $mult = max(0.0, (100.0 - $pct) / 100.0);
                        db_exec("UPDATE items SET original_price = COALESCE(original_price, price), price = ROUND(price * ?, 2) WHERE is_free = 0 AND id IN ($ph)", array_merge([$mult], $ids));
                    } else {
                        db_exec("UPDATE items SET original_price = COALESCE(original_price, price), price = GREATEST(0, ROUND(price - ?, 2)) WHERE is_free = 0 AND id IN ($ph)", array_merge([$val], $ids));
                    }
                    log_security_event('admin_price_bulk', (string) $admin['email'], "Action {$bulkAction} ({$val}) on IDs: " . implode(',', $ids));
                    flash('success', admin_bi('নির্বাচিত বইগুলোর মূল্য হালনাগাদ হয়েছে।', count($ids) . ' item price(s) updated.'));
                } elseif ($bulkAction === 'delete') {
                    $oldCovers = array_column(db_all("SELECT cover FROM items WHERE id IN ($ph)", $ids), 'cover');
                    db_exec("DELETE FROM items WHERE id IN ($ph)", $ids);
                    foreach (array_unique(array_filter($oldCovers)) as $oldC) {
                        media_try_delete_unused_image((string) $oldC);
                    }
                    flash('success', admin_bi('নির্বাচিত PDF মুছে ফেলা হয়েছে।', count($ids) . ' PDF(s) deleted.'));
                } else {
                    throw new RuntimeException(admin_bi('সঠিক বাল্ক অ্যাকশন নির্বাচন করুন।', 'Please select a valid bulk action.'));
                }
                invalidate_folder_and_sitemap_cache();
                log_admin_action($admin, 'pdf_bulk_' . $bulkAction, 'item', null, 'IDs: ' . implode(',', $ids));
                redirect(admin_return_url('/admin/pdfs'));
            }

            // --- Users ---
            if ($action === 'toggle_user_block') {
                $uid = (int) ($_POST['user_id'] ?? 0);
                db_exec('UPDATE users SET is_active = 1 - is_active, active_session_token = NULL WHERE id = ?', [$uid]);
                log_admin_action($admin, 'user_block_toggle', 'user', $uid, 'Toggled block status & revoked session');
                flash('success', admin_bi('ব্যবহারকারীর অবস্থা পরিবর্তন হয়েছে।', 'User account status updated.'));
                $back = !empty($_POST['return_to']) && $_POST['return_to'] === 'logs' ? '/admin/logs' : admin_return_url('/admin/users');
                redirect($back);
            }

            if ($action === 'force_logout_user') {
                $uid = (int) ($_POST['user_id'] ?? 0);
                db_exec('UPDATE users SET active_session_token = NULL WHERE id = ?', [$uid]);
                log_admin_action($admin, 'user_force_logout', 'user', $uid, 'Cleared active reading/account session');
                flash('success', admin_bi('ব্যবহারকারীর সক্রিয় সেশন বন্ধ করা হয়েছে।', 'Active user session terminated.'));
                redirect(admin_return_url('/admin/users'));
            }

            if ($action === 'unsuspend_user') {
                $uid = (int) ($_POST['user_id'] ?? 0);
                db_exec('UPDATE users SET reading_suspended_until = NULL WHERE id = ?', [$uid]);
                log_admin_action($admin, 'user_unsuspend_reader', 'user', $uid, 'Cleared temporary reader suspension');
                flash('success', admin_bi('রিডার সাময়িক নিষেধাজ্ঞা তুলে নেওয়া হয়েছে।', 'Reader suspension lifted.'));
                redirect(admin_return_url('/admin/users'));
            }

            // --- Bulk CSV Import Actions ---
            if ($action === 'csv_preview') {
                if (!isset($_FILES['csv_file']) || ($_FILES['csv_file']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
                    throw new RuntimeException(admin_bi('দয়া করে একটি CSV ফাইল আপলোড করুন।', 'Please choose a valid .csv file.'));
                }
                $autoCreate = !empty($_POST['auto_create_folders']);
                $parsed = admin_parse_csv_preview($_FILES['csv_file']['tmp_name'], $autoCreate);
                $stageDir = APP_ROOT . '/storage/cache';
                if (!is_dir($stageDir)) {
                    @mkdir($stageDir, 0755, true);
                }
                $stageFile = $stageDir . '/csv_import_' . (int) $admin['id'] . '_' . bin2hex(random_bytes(6)) . '.json';
                @file_put_contents($stageFile, json_encode($parsed, JSON_UNESCAPED_UNICODE), LOCK_EX);
                $_SESSION['csv_import_preview'] = ['file' => $stageFile, 'auto_create' => $autoCreate];
                redirect('/admin/import-csv');
            }

            if ($action === 'csv_clear_preview') {
                $prev = $_SESSION['csv_import_preview'] ?? null;
                if (is_array($prev) && !empty($prev['file']) && is_file((string) $prev['file'])) {
                    @unlink((string) $prev['file']);
                }
                unset($_SESSION['csv_import_preview']);
                redirect('/admin/import-csv');
            }

            if ($action === 'csv_commit_all') {
                $prev = $_SESSION['csv_import_preview'] ?? null;
                if (!is_array($prev) || empty($prev['file']) || !is_file((string) $prev['file'])) {
                    throw new RuntimeException('No CSV preview found.');
                }
                $decoded = json_decode((string) file_get_contents((string) $prev['file']), true);
                $rows = is_array($decoded) ? ($decoded['rows'] ?? []) : [];
                $autoCreate = !empty($prev['auto_create']);
                $imported = 0;
                foreach ($rows as $row) {
                    if (!empty($row['errors'])) continue;
                    $fRes = admin_find_or_create_folder_path((string) ($row['folder_path'] ?? ''), $autoCreate);
                    if (!$fRes['ok']) continue;
                    $slug = generate_unique_item_slug((string) ($row['slug'] ?: $row['title_en']));
                    $validDrive = ($row['drive_link'] ?? '') !== '' ? validate_drive_url((string) $row['drive_link'], true) : null;
                    db_exec(
                        'INSERT INTO items (folder_id, slug, title_bn, title_en, author_bn, author_en, description_bn, description_en, cover, drive_link, price, original_price, is_free, sample_pages, is_active)
                         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1)',
                        [$fRes['folder_id'], $slug, $row['title_bn'], $row['title_en'], $row['author_bn'], $row['author_en'], $row['description_bn'], $row['description_en'], 'uploads/covers/cover-bangla.svg', $validDrive, (float) $row['price'], $row['original_price'], (int) $row['is_free'], (int) $row['sample_pages']]
                    );
                    $imported++;
                }
                @unlink((string) $prev['file']);
                unset($_SESSION['csv_import_preview']);
                if ($imported > 0) {
                    invalidate_folder_and_sitemap_cache();
                }
                log_admin_action($admin, 'csv_bulk_import', 'item', null, "Imported {$imported} PDFs from CSV");
                flash('success', admin_bi("{$imported}টি বই CSV থেকে ইমপোর্ট হয়েছে।", "Imported {$imported} book(s) from CSV."));
                redirect('/admin/pdfs');
            }

            if ($action === 'contact_status') {
                $cid = (int) ($_POST['contact_id'] ?? 0);
                $st  = (string) ($_POST['status'] ?? 'read');
                if (!in_array($st, ['new', 'read', 'replied'], true)) {
                    throw new RuntimeException('Invalid contact message status.');
                }
                db_exec('UPDATE contact_messages SET status = ? WHERE id = ?', [$st, $cid]);
                log_admin_action($admin, 'contact_status_' . $st, 'contact', $cid, 'Updated contact message status');
                flash('success', admin_bi('যোগাযোগ বার্তার অবস্থা আপডেট হয়েছে।', 'Contact message status updated.'));
                redirect(admin_return_url('/admin/inbox'));
            }

            if ($action === 'inbox_reply') {
                $cid     = (int) ($_POST['contact_id'] ?? 0);
                $subj    = trim((string) ($_POST['reply_subject'] ?? 'Re: Bookexa Support'));
                $reply   = trim((string) ($_POST['reply_message'] ?? ''));
                $msgRow  = $cid > 0 ? db_one('SELECT * FROM contact_messages WHERE id = ?', [$cid]) : null;
                if (!$msgRow || $reply === '') {
                    throw new RuntimeException(admin_bi('বার্তা বা উত্তর খুঁজে পাওয়া যায়নি।', 'Message not found or reply text empty.'));
                }
                send_bookexa_template_mail(
                    (string) $msgRow['email'],
                    $subj,
                    'Response from ' . site_setting('site_name', 'Bookexa') . ' Support',
                    '<p>' . nl2br(e($reply)) . '</p><hr><p style="font-size:12px;color:#64748b;">Original message:<br>' . nl2br(e((string) $msgRow['message'])) . '</p>'
                );
                db_exec(
                    "UPDATE contact_messages SET status = 'replied', reply_message = ?, replied_by = ?, replied_at = NOW() WHERE id = ?",
                    [$reply, (int) $admin['id'], $cid]
                );
                log_admin_action($admin, 'inbox_reply', 'contact', $cid, 'Replied to ' . $msgRow['email']);
                flash('success', admin_bi('উত্তর ইমেইলে পাঠানো ও সংরক্ষণ করা হয়েছে।', 'Reply sent via SMTP and saved in inbox.'));
                redirect(admin_return_url('/admin/inbox'));
            }

            if ($action === 'inbox_delete') {
                $cid = (int) ($_POST['contact_id'] ?? 0);
                db_exec('DELETE FROM contact_messages WHERE id = ?', [$cid]);
                log_admin_action($admin, 'inbox_delete', 'contact', $cid, 'Deleted contact message #' . $cid);
                flash('success', admin_bi('বার্তা মুছে ফেলা হয়েছে।', 'Message deleted.'));
                redirect(admin_return_url('/admin/inbox'));
            }

            // --- Orders ---
            if ($action === 'review_order') {
                $orderId      = (int) ($_POST['order_id'] ?? 0);
                $decision     = (string) ($_POST['decision'] ?? '');
                $adminNote    = trim((string) ($_POST['admin_note'] ?? ''));
                $internalNote = array_key_exists('internal_notes', $_POST) ? trim((string) $_POST['internal_notes']) : null;

                if (!in_array($decision, ['approved', 'rejected'], true)) {
                    throw new RuntimeException(admin_bi('অর্ডার সিদ্ধান্ত সঠিক নয়।', 'Invalid order decision.'));
                }

                $res = apply_order_status_transition(
                    $orderId,
                    $decision,
                    (int) $admin['id'],
                    $adminNote !== '' ? $adminNote : null,
                    $internalNote
                );
                if (!$res['ok']) {
                    throw new RuntimeException((string) ($res['message'] ?? 'Order not found.'));
                }

                log_admin_action($admin, 'order_' . $decision, 'order', $orderId, 'Order ' . ($res['order']['order_code'] ?? $orderId));
                flash('success', admin_bi('অর্ডার #', 'Order #') . ($res['order']['order_code'] ?? $orderId) . ' -> ' . $decision);
                redirect(admin_return_url('/admin/orders'));
            }

            if ($action === 'bulk_orders') {
                $decision = (string) ($_POST['bulk_decision'] ?? '');
                $reason   = trim((string) ($_POST['bulk_reason'] ?? ''));
                $rawIds   = $_POST['ids'] ?? [];
                $ids = is_array($rawIds) ? array_values(array_unique(array_filter(array_map('intval', $rawIds), fn(int $v): bool => $v > 0))) : [];
                if (!$ids || !in_array($decision, ['approved', 'rejected'], true)) {
                    throw new RuntimeException(admin_bi('অন্তত একটি অর্ডার ও সঠিক অ্যাকশন নির্বাচন করুন।', 'Select at least one order and a valid bulk decision.'));
                }
                $changed = 0;
                foreach ($ids as $oid) {
                    $r = apply_order_status_transition($oid, $decision, (int) $admin['id'], $reason !== '' ? $reason : null);
                    if (!empty($r['ok']) && !empty($r['changed'])) {
                        $changed++;
                    }
                }
                log_admin_action($admin, 'order_bulk_' . $decision, 'order', null, "Updated {$changed} of " . count($ids) . ' selected orders');
                flash('success', admin_bi("নির্বাচিত {$changed}টি অর্ডার হালনাগাদ হয়েছে।", "Bulk action complete: {$changed} order(s) updated to {$decision}."));
                redirect(admin_return_url('/admin/orders'));
            }

            if ($action === 'save_order_note') {
                $orderId      = (int) ($_POST['order_id'] ?? 0);
                $internalNote = trim((string) ($_POST['internal_notes'] ?? ''));
                if ($orderId <= 0 || !db_one('SELECT id FROM orders WHERE id = ?', [$orderId])) {
                    throw new RuntimeException(admin_bi('অর্ডার খুঁজে পাওয়া যায়নি।', 'Order not found.'));
                }
                db_exec('UPDATE orders SET internal_notes = ? WHERE id = ?', [$internalNote !== '' ? $internalNote : null, $orderId]);
                log_admin_action($admin, 'order_internal_note', 'order', $orderId, 'Updated staff internal notes');
                flash('success', admin_bi('অভ্যন্তরীণ নোট সংরক্ষিত হয়েছে।', 'Internal order note saved.'));
                redirect(admin_return_url('/admin/orders'));
            }

            // --- Coupons ---
            if ($action === 'save_coupon') {
                $id = !empty($_POST['id']) ? (int) $_POST['id'] : null;
                $code = strtoupper(preg_replace('/[^A-Za-z0-9_-]/', '', trim((string) ($_POST['code'] ?? ''))) ?? '');
                $type = ($_POST['discount_type'] ?? 'percent') === 'fixed' ? 'fixed' : 'percent';
                $val = round(max(0.01, (float) ($_POST['discount_value'] ?? 0)), 2);
                if ($type === 'percent') {
                    $val = min(100.0, $val);
                }
                $minAmt = round(max(0.0, (float) ($_POST['min_amount'] ?? 0)), 2);
                $usageLimit = trim((string) ($_POST['usage_limit'] ?? '')) !== '' ? max(1, (int) $_POST['usage_limit']) : null;
                $perUserLimit = max(1, (int) ($_POST['per_user_limit'] ?? 1));
                $appliesToAll = isset($_POST['applies_to_all']) ? 1 : 0;
                $expiresAt = admin_parse_datetime($_POST['expires_at'] ?? null);
                $isActive = isset($_POST['is_active']) ? 1 : 0;
                $itemIds = is_array($_POST['item_ids'] ?? null)
                    ? array_values(array_unique(array_filter(array_map('intval', $_POST['item_ids']), fn($v) => $v > 0)))
                    : [];

                if ($code === '' || strlen($code) < 2) {
                    throw new RuntimeException(admin_bi('সঠিক কুপন কোড লিখুন (কমপক্ষে ২ অক্ষর)।', 'Valid coupon code (min 2 chars) is required.'));
                }
                $dup = $id
                    ? db_one('SELECT id FROM coupons WHERE code = ? AND id != ? LIMIT 1', [$code, $id])
                    : db_one('SELECT id FROM coupons WHERE code = ? LIMIT 1', [$code]);
                if ($dup) {
                    throw new RuntimeException(admin_bi('এই কুপন কোড ইতিমধ্যে ব্যবহৃত হয়েছে।', 'A coupon with this code already exists.'));
                }

                if ($id === null) {
                    db_exec(
                        'INSERT INTO coupons (code, discount_type, discount_value, min_amount, usage_limit, per_user_limit, applies_to_all, expires_at, is_active) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)',
                        [$code, $type, $val, $minAmt, $usageLimit, $perUserLimit, $appliesToAll, $expiresAt, $isActive]
                    );
                    $cid = (int) db()->lastInsertId();
                    log_admin_action($admin, 'coupon_create', 'coupon', $cid, $code);
                    flash('success', admin_bi('কুপন তৈরি হয়েছে।', 'Coupon created.'));
                } else {
                    db_exec(
                        'UPDATE coupons SET code=?, discount_type=?, discount_value=?, min_amount=?, usage_limit=?, per_user_limit=?, applies_to_all=?, expires_at=?, is_active=? WHERE id=?',
                        [$code, $type, $val, $minAmt, $usageLimit, $perUserLimit, $appliesToAll, $expiresAt, $isActive, $id]
                    );
                    $cid = $id;
                    db_exec('DELETE FROM coupon_items WHERE coupon_id = ?', [$cid]);
                    log_admin_action($admin, 'coupon_update', 'coupon', $cid, $code);
                    flash('success', admin_bi('কুপন হালনাগাদ হয়েছে।', 'Coupon updated.'));
                }

                if (!$appliesToAll && $itemIds) {
                    foreach ($itemIds as $iid) {
                        db_exec('INSERT IGNORE INTO coupon_items (coupon_id, item_id) VALUES (?, ?)', [$cid, $iid]);
                    }
                }
                redirect('/admin/coupons');
            }

            if ($action === 'toggle_coupon') {
                $id = (int) ($_POST['id'] ?? 0);
                db_exec('UPDATE coupons SET is_active = 1 - is_active WHERE id = ?', [$id]);
                log_admin_action($admin, 'coupon_toggle', 'coupon', $id, 'Toggled coupon status');
                flash('success', admin_bi('কুপনের অবস্থা পরিবর্তন হয়েছে।', 'Coupon status updated.'));
                redirect('/admin/coupons');
            }

            if ($action === 'delete_coupon') {
                $id = (int) ($_POST['id'] ?? 0);
                db_exec('DELETE FROM coupons WHERE id = ?', [$id]);
                log_admin_action($admin, 'coupon_delete', 'coupon', $id, 'Deleted coupon #' . $id);
                flash('success', admin_bi('কুপন মুছে ফেলা হয়েছে।', 'Coupon deleted.'));
                redirect('/admin/coupons');
            }

            // --- Reviews Moderation ---
            if ($action === 'review_moderate') {
                $id = (int) ($_POST['id'] ?? 0);
                $status = (string) ($_POST['status'] ?? '');
                if (!in_array($status, ['approved', 'pending', 'hidden'], true)) {
                    throw new RuntimeException('Invalid review status.');
                }
                db_exec('UPDATE reviews SET status = ? WHERE id = ?', [$status, $id]);
                log_admin_action($admin, 'review_' . $status, 'review', $id, 'Set status ' . $status);
                flash('success', admin_bi('রিভিউয়ের অবস্থা পরিবর্তিত হয়েছে।', 'Review status updated.'));
                redirect(admin_return_url('/admin/reviews'));
            }

            if ($action === 'review_delete') {
                $id = (int) ($_POST['id'] ?? 0);
                db_exec('DELETE FROM reviews WHERE id = ?', [$id]);
                log_admin_action($admin, 'review_delete', 'review', $id, 'Deleted review #' . $id);
                flash('success', admin_bi('রিভিউ মুছে ফেলা হয়েছে।', 'Review deleted.'));
                redirect(admin_return_url('/admin/reviews'));
            }

            // --- Settings ---
            if ($action === 'save_settings') {
                $fields = [
                    'site_name', 'contact_phone', 'contact_email', 'contact_address',
                    'bkash_number', 'nagad_number', 'rocket_number',
                    'payment_instructions_bn', 'payment_instructions_en',
                    'smtp_host', 'smtp_user', 'smtp_from_name', 'smtp_from_email',
                    'legal_terms_bn', 'legal_terms_en', 'legal_privacy_bn', 'legal_privacy_en',
                    'legal_refund_bn', 'legal_refund_en', 'legal_copyright_bn', 'legal_copyright_en',
                ];
                foreach ($fields as $k) {
                    $val = trim((string) ($_POST[$k] ?? ''));
                    db_exec(
                        'INSERT INTO settings (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)',
                        [$k, $val]
                    );
                }
                $smtpPort = (string) max(1, (int) ($_POST['smtp_port'] ?? 587));
                $rawSmtpEnc = (string) ($_POST['smtp_encryption'] ?? 'tls');
                $smtpEnc = in_array($rawSmtpEnc, ['tls', 'ssl', 'none'], true) ? $rawSmtpEnc : 'tls';
                db_exec('INSERT INTO settings (setting_key, setting_value) VALUES ("smtp_port", ?) ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)', [$smtpPort]);
                db_exec('INSERT INTO settings (setting_key, setting_value) VALUES ("smtp_encryption", ?) ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)', [$smtpEnc]);
                $smtpPassNew = (string) ($_POST['smtp_pass'] ?? '');
                if ($smtpPassNew !== '') {
                    db_exec('INSERT INTO settings (setting_key, setting_value) VALUES ("smtp_pass", ?) ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)', [$smtpPassNew]);
                }
                if (!empty($_POST['clear_google_drive_api_key'])) {
                    db_exec('DELETE FROM settings WHERE setting_key = "google_drive_api_key"');
                } else {
                    $gdKeyNew = trim((string) ($_POST['google_drive_api_key'] ?? ''));
                    if ($gdKeyNew !== '') {
                        db_exec('INSERT INTO settings (setting_key, setting_value) VALUES ("google_drive_api_key", ?) ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)', [$gdKeyNew]);
                    }
                }
                $defLang = ($_POST['default_language'] ?? 'bn') === 'en' ? 'en' : 'bn';
                $maint = isset($_POST['maintenance_mode']) ? '1' : '0';
                $shopEnabled = isset($_POST['shop_enabled']) ? '1' : '0';
                $freeFallback = isset($_POST['free_pdfjs_fallback']) ? '1' : '0';
                $quotaMult = (string) max(0, min(100, (int) ($_POST['reader_daily_quota_mult'] ?? 3)));
                $maxDevices = (string) max(1, min(10, (int) ($_POST['max_devices_per_account'] ?? 2)));
                $expireDays = (string) max(1, min(365, (int) ($_POST['pending_order_expire_days'] ?? 3)));
                $mobLightweight = isset($_POST['reader_mobile_lightweight']) ? '1' : '0';
                $maxConcurrentRenders = (string) max(1, min(64, (int) ($_POST['max_concurrent_page_renders'] ?? 8)));

                $smsEnabled = isset($_POST['sms_gateway_enabled']) ? '1' : '0';
                $rawSmsDriver = (string) ($_POST['sms_gateway_driver'] ?? 'none');
                $smsDriver = in_array($rawSmsDriver, ['none', 'generic'], true) ? $rawSmsDriver : 'none';
                $smsSandbox = isset($_POST['sms_gateway_sandbox']) ? '1' : '0';
                $smsApiUrl  = trim((string) ($_POST['sms_gateway_api_url'] ?? ''));
                $smsSender  = trim((string) ($_POST['sms_gateway_sender_id'] ?? 'Bookexa'));
                $smsApiKey  = trim((string) ($_POST['sms_gateway_api_key'] ?? ''));

                $gwEnabled = isset($_POST['payment_gateway_enabled']) ? '1' : '0';
                $rawGwDriver = (string) ($_POST['payment_gateway_driver'] ?? 'manual');
                $gwDriver = in_array($rawGwDriver, ['manual', 'bkash_tokenized', 'aamarpay', 'uddoktapay'], true)
                    ? $rawGwDriver
                    : 'manual';
                $gwSandbox = isset($_POST['payment_gateway_sandbox']) ? '1' : '0';
                $gwBaseUrl = trim((string) ($_POST['payment_gateway_base_url'] ?? ''));
                $gwApiKey  = trim((string) ($_POST['payment_gateway_api_key'] ?? ''));
                $gwApiSec  = trim((string) ($_POST['payment_gateway_api_secret'] ?? ''));
                $gwWhSec   = trim((string) ($_POST['payment_gateway_webhook_secret'] ?? ''));

                foreach ([
                    'default_language'          => $defLang,
                    'maintenance_mode'          => $maint,
                    'shop_enabled'              => $shopEnabled,
                    'free_pdfjs_fallback'       => $freeFallback,
                    'reader_daily_quota_mult'   => $quotaMult,
                    'max_devices_per_account'   => $maxDevices,
                    'pending_order_expire_days' => $expireDays,
                    'reader_mobile_lightweight' => $mobLightweight,
                    'max_concurrent_page_renders' => $maxConcurrentRenders,
                    'sms_gateway_enabled'       => $smsEnabled,
                    'sms_gateway_driver'        => $smsDriver,
                    'sms_gateway_sandbox'       => $smsSandbox,
                    'sms_gateway_api_url'       => $smsApiUrl,
                    'sms_gateway_sender_id'     => $smsSender,
                    'payment_gateway_enabled'   => $gwEnabled,
                    'payment_gateway_driver'    => $gwDriver,
                    'payment_gateway_sandbox'   => $gwSandbox,
                    'payment_gateway_base_url'  => $gwBaseUrl,
                    'payment_gateway_api_key'   => $gwApiKey,
                ] as $k => $v) {
                    db_exec(
                        'INSERT INTO settings (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)',
                        [$k, $v]
                    );
                }
                if ($smsApiKey !== '') {
                    db_exec('INSERT INTO settings (setting_key, setting_value) VALUES ("sms_gateway_api_key", ?) ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)', [$smsApiKey]);
                }
                if ($gwApiSec !== '') {
                    db_exec('INSERT INTO settings (setting_key, setting_value) VALUES ("payment_gateway_api_secret", ?) ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)', [$gwApiSec]);
                }
                if ($gwWhSec !== '') {
                    db_exec('INSERT INTO settings (setting_key, setting_value) VALUES ("payment_gateway_webhook_secret", ?) ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)', [$gwWhSec]);
                }
                $prevLogo = db_one("SELECT setting_value FROM settings WHERE setting_key = 'site_logo'")['setting_value'] ?? null;
                $uploadedLogo = safe_admin_image_upload('site_logo', 'branding', 400);
                if ($uploadedLogo !== null) {
                    db_exec(
                        'INSERT INTO settings (setting_key, setting_value) VALUES ("site_logo", ?) ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)',
                        [$uploadedLogo]
                    );
                    if ($prevLogo && $prevLogo !== $uploadedLogo) {
                        media_try_delete_unused_image((string) $prevLogo);
                    }
                }
                $prevFav = db_one("SELECT setting_value FROM settings WHERE setting_key = 'site_favicon'")['setting_value'] ?? null;
                $uploadedFavicon = safe_admin_image_upload('site_favicon', 'branding', 256, true);
                if ($uploadedFavicon !== null) {
                    db_exec(
                        'INSERT INTO settings (setting_key, setting_value) VALUES ("site_favicon", ?) ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)',
                        [$uploadedFavicon]
                    );
                    if ($prevFav && $prevFav !== $uploadedFavicon) {
                        media_try_delete_unused_image((string) $prevFav);
                    }
                }
                invalidate_folder_and_sitemap_cache(false);
                log_admin_action($admin, 'settings_update', 'settings', null, 'Updated site/payment/maintenance settings');
                flash('success', admin_bi('সাইট ও পেমেন্ট সেটিংস সংরক্ষিত হয়েছে।', 'Site & payment settings saved.'));
                redirect('/admin/settings');
            }

            if ($action === 'regenerate_cron_key') {
                regenerate_cron_sync_secret();
                log_admin_action($admin, 'cron_key_regenerate', 'settings', null, 'Rotated CDN cron sync secret key');
                flash('success', admin_bi('নতুন ক্রন সিঙ্ক কী তৈরি হয়েছে। পুরনো কী এখন অকার্যকর।', 'New CDN cron key generated. The previous key is now invalid.'));
                redirect(admin_return_url('/admin/settings'));
            }

            // --- Account Security (Password, TOTP 2FA, Sessions & IP Allowlist) ---
            if ($action === 'change_my_password') {
                $currPw = (string) ($_POST['current_password'] ?? '');
                $newPw  = (string) ($_POST['new_password'] ?? '');
                $confPw = (string) ($_POST['confirm_password'] ?? '');
                $selfRow = db_one('SELECT id, password_hash FROM admins WHERE id = ?', [(int) $admin['id']]);
                if (!$selfRow || !password_verify($currPw, (string) $selfRow['password_hash'])) {
                    throw new RuntimeException(admin_bi('বর্তমান পাসওয়ার্ড সঠিক নয়।', 'Current password is incorrect.'));
                }
                if (strlen($newPw) < 8) {
                    throw new RuntimeException(admin_bi('নতুন পাসওয়ার্ড কমপক্ষে ৮ অক্ষরের হতে হবে।', 'New password must be at least 8 characters.'));
                }
                if ($newPw !== $confPw) {
                    throw new RuntimeException(admin_bi('নতুন পাসওয়ার্ড দুটি মিলছে না।', 'Password confirmation does not match.'));
                }
                if (password_verify($newPw, (string) $selfRow['password_hash'])) {
                    throw new RuntimeException(admin_bi('নতুন পাসওয়ার্ড অবশ্যই পূর্বের পাসওয়ার্ড থেকে ভিন্ন হতে হবে।', 'Please choose a password different from your current one.'));
                }
                db_exec('UPDATE admins SET password_hash = ?, must_change_password = 0 WHERE id = ?', [password_hash($newPw, PASSWORD_BCRYPT), (int) $admin['id']]);
                log_admin_action($admin, 'admin_password_change', 'admin', (int) $admin['id'], 'Updated own password');
                log_security_event('admin_password_change', (string) $admin['email'], 'Updated password');
                flash('success', admin_bi('পাসওয়ার্ড আপডেট হয়েছে।', 'Your password has been updated.'));
                redirect('/admin');
            }

            if ($action === 'start_totp_setup') {
                $_SESSION['admin_totp_setup_secret'] = totp_generate_secret(32);
                redirect('/admin/account-security#admin-2fa');
            }

            if ($action === 'confirm_totp_setup') {
                $secret = (string) ($_SESSION['admin_totp_setup_secret'] ?? '');
                $code = (string) ($_POST['totp_code'] ?? '');
                if ($secret === '' || !totp_verify_code($secret, $code, 1)) {
                    throw new RuntimeException(admin_bi('৬ অঙ্কের অথেনটিকেটর কোড সঠিক নয়।', 'Invalid 6-digit verification code.'));
                }
                $bc = totp_generate_backup_codes(8);
                db_exec(
                    'UPDATE admins SET totp_secret = ?, totp_enabled = 1, backup_codes_json = ? WHERE id = ?',
                    [$secret, json_encode($bc['hashes']), (int) $admin['id']]
                );
                unset($_SESSION['admin_totp_setup_secret']);
                $_SESSION['admin_fresh_backup_codes'] = $bc['plain'];
                log_admin_action($admin, 'admin_2fa_enabled', 'admin', (int) $admin['id'], 'Enabled TOTP 2FA with 8 backup codes');
                log_security_event('admin_2fa_enabled', (string) $admin['email'], 'Enabled TOTP 2FA');
                flash('success', admin_bi('2FA চালু হয়েছে! নিচের ৮টি ব্যাকআপ কোড সংরক্ষণ করুন।', 'TOTP 2FA enabled! Save your 8 backup codes below.'));
                redirect('/admin/account-security#admin-2fa');
            }

            if ($action === 'regen_backup_codes') {
                $bc = totp_generate_backup_codes(8);
                db_exec('UPDATE admins SET backup_codes_json = ? WHERE id = ? AND totp_enabled = 1', [json_encode($bc['hashes']), (int) $admin['id']]);
                $_SESSION['admin_fresh_backup_codes'] = $bc['plain'];
                log_admin_action($admin, 'admin_2fa_backup_regen', 'admin', (int) $admin['id'], 'Regenerated 8 backup codes');
                log_security_event('admin_2fa_backup_regen', (string) $admin['email'], 'Regenerated backup codes');
                flash('success', admin_bi('নতুন ৮টি ব্যাকআপ কোড তৈরি হয়েছে।', 'New 8 backup codes generated.'));
                redirect('/admin/account-security#admin-2fa');
            }

            if ($action === 'disable_totp') {
                $currPw = (string) ($_POST['current_password'] ?? '');
                $selfRow = db_one('SELECT password_hash FROM admins WHERE id = ?', [(int) $admin['id']]);
                if (!$selfRow || !password_verify($currPw, (string) $selfRow['password_hash'])) {
                    throw new RuntimeException(admin_bi('পাসওয়ার্ড সঠিক নয়।', 'Current password is incorrect.'));
                }
                db_exec('UPDATE admins SET totp_secret = NULL, totp_enabled = 0, backup_codes_json = NULL WHERE id = ?', [(int) $admin['id']]);
                log_admin_action($admin, 'admin_2fa_disabled', 'admin', (int) $admin['id'], 'Disabled TOTP 2FA');
                log_security_event('admin_2fa_disabled', (string) $admin['email'], 'Disabled TOTP 2FA');
                flash('success', admin_bi('2FA নিষ্ক্রিয় করা হয়েছে।', 'TOTP 2FA has been disabled.'));
                redirect('/admin/account-security#admin-2fa');
            }

            if ($action === 'signout_other_sessions') {
                $cnt = revoke_all_admin_sessions((int) $admin['id'], (string) ($_SESSION['admin_session_token'] ?? ''));
                log_admin_action($admin, 'admin_sessions_revoke_others', 'admin', (int) $admin['id'], "Revoked {$cnt} other session(s)");
                log_security_event('admin_sessions_revoked', (string) $admin['email'], "Revoked {$cnt} other sessions");
                flash('success', admin_bi("অন্য {$cnt}টি সেশন সাইন আউট করা হয়েছে।", "Signed out {$cnt} other admin session(s)."));
                redirect('/admin/account-security#admin-sessions');
            }

            if ($action === 'signout_all_sessions') {
                $cnt = revoke_all_admin_sessions((int) $admin['id'], null);
                log_admin_action($admin, 'admin_sessions_revoke_all', 'admin', (int) $admin['id'], "Revoked all {$cnt} sessions");
                log_security_event('admin_sessions_revoked', (string) $admin['email'], 'Signed out ALL admin sessions');
                admin_logout();
                flash('info', admin_bi('সব সেশন সাইন আউট করা হয়েছে।', 'All admin sessions signed out.'));
                redirect('/admin/login');
            }

            if ($action === 'save_ip_allowlist') {
                $rawList = trim((string) ($_POST['admin_ip_allowlist'] ?? ''));
                db_exec("INSERT INTO settings (setting_key, setting_value) VALUES ('admin_ip_allowlist', ?) ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)", [$rawList]);
                log_admin_action($admin, 'admin_ip_allowlist_update', 'settings', null, $rawList !== '' ? 'Updated allowlist' : 'Cleared allowlist');
                log_security_event('admin_ip_allowlist_update', (string) $admin['email'], $rawList !== '' ? 'Configured IP allowlist' : 'Cleared IP allowlist');
                flash('success', admin_bi('অ্যাডমিন IP Allowlist সংরক্ষিত হয়েছে।', 'Admin IP allowlist saved.'));
                redirect('/admin/account-security#admin-ip-allowlist');
            }

            if ($action === 'clean_cache') {
                $cacheDir = APP_ROOT . '/storage/cache';
                $removed = 0;
                foreach (glob($cacheDir . '/*') ?: [] as $cf) {
                    if (is_file($cf) && basename($cf) !== '.htaccess') {
                        if (@unlink($cf)) $removed++;
                    }
                }
                log_admin_action($admin, 'system_clean_cache', 'system', null, "Removed {$removed} cached file(s)");
                flash('success', admin_bi("ক্যাশ পরিষ্কার হয়েছে ({$removed}টি ফাইল)।", "Cleaned {$removed} cached file(s)."));
                redirect('/admin/system-health');
            }

            if ($action === 'run_daily_backup_now') {
                require_once APP_ROOT . '/includes/maintenance.php';
                $fname = run_daily_db_backup_if_due(true);
                if (!$fname) {
                    throw new RuntimeException('Failed to create DB backup snapshot.');
                }
                log_admin_action($admin, 'db_auto_backup_manual', 'system', null, 'Created ' . $fname);
                flash('success', admin_bi('ব্যাকআপ তৈরি হয়েছে: ', 'Saved backup: ') . $fname);
                redirect('/admin/system-health');
            }

            // --- Database SQL Backup & Media / Site Package ---
            if ($action === 'download_db_backup') {
                if ($admin['role'] !== 'superadmin') {
                    throw new RuntimeException(admin_bi('অনুমতি নেই: সুপার অ্যাডমিন প্রয়োজন।', 'Forbidden: Super Admin access required.'));
                }
                require_once APP_ROOT . '/includes/maintenance.php';
                stream_mysql_backup_sql($admin);
            }

            if ($action === 'cdn_single_sync') {
                $relPath = (string) ($_POST['rel_path'] ?? '');
                $res = media_sync_push_one($relPath);
                if ($res['ok']) {
                    log_admin_action($admin, 'cdn_single_sync', 'media', null, $relPath);
                    flash('success', admin_bi('ফাইলটি CDN-এ সিঙ্ক হয়েছে: ', 'Synced to CDN: ') . $relPath);
                } else {
                    flash('danger', admin_bi('CDN সিঙ্ক ব্যর্থ: ', 'CDN sync failed: ') . ($res['error'] ?: 'Error'));
                }
                redirect('/admin/media');
            }

            if ($action === 'export_site_package') {
                if ($admin['role'] !== 'superadmin') {
                    throw new RuntimeException(admin_bi('অনুমতি নেই: সুপার অ্যাডমিন প্রয়োজন।', 'Forbidden: Super Admin access required.'));
                }
                $mode = (string) ($_POST['package_mode'] ?? 'clean');
                stream_site_package_zip($admin, $mode !== 'snapshot');
            }

            // --- Admin Accounts (Super Admin Only) ---
            if ($action === 'save_admin' || $action === 'toggle_admin') {
                if ($admin['role'] !== 'superadmin') {
                    throw new RuntimeException(admin_bi('অনুমতি নেই: সুপার অ্যাডমিন প্রয়োজন।', 'Forbidden: Super Admin access required.'));
                }
                if ($action === 'save_admin') {
                    $aid = !empty($_POST['id']) ? (int) $_POST['id'] : null;
                    $name = trim((string) ($_POST['name'] ?? ''));
                    $email = strtolower(trim((string) ($_POST['email'] ?? '')));
                    $rawRole = (string) ($_POST['role'] ?? 'editor');
                    $role = in_array($rawRole, ['superadmin', 'editor', 'support', 'analyst'], true) ? $rawRole : 'editor';
                    $password = (string) ($_POST['password'] ?? '');
                    $isActive = isset($_POST['is_active']) ? 1 : 0;
                    $mustChangePw = isset($_POST['must_change_password']) ? 1 : 0;

                    if ($name === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                        throw new RuntimeException(admin_bi('সঠিক নাম ও ইমেইল দিন।', 'Valid admin name and email are required.'));
                    }
                    $dupSql = $aid ? 'SELECT id FROM admins WHERE email = ? AND id != ? LIMIT 1' : 'SELECT id FROM admins WHERE email = ? LIMIT 1';
                    $dupParams = $aid ? [$email, $aid] : [$email];
                    if (db_one($dupSql, $dupParams)) {
                        throw new RuntimeException(admin_bi('এই ইমেইল দিয়ে ইতিমধ্যে অ্যাডমিন রয়েছে।', 'Another admin already uses that email.'));
                    }
                    if ($aid === null) {
                        if (strlen($password) < 8) {
                            throw new RuntimeException(admin_bi('পাসওয়ার্ড কমপক্ষে ৮ অক্ষরের হতে হবে।', 'Password must be at least 8 characters.'));
                        }
                        $hash = password_hash($password, PASSWORD_BCRYPT);
                        db_exec(
                            'INSERT INTO admins (name, email, password_hash, role, is_active, must_change_password) VALUES (?, ?, ?, ?, ?, ?)',
                            [$name, $email, $hash, $role, $isActive, $mustChangePw]
                        );
                        $newId = (int) db()->lastInsertId();
                        log_admin_action($admin, 'admin_create', 'admin', $newId, $email . ' (' . $role . ')');
                        log_security_event('admin_role_change', $email, 'Created admin with role ' . $role . ' by ' . $admin['email']);
                        flash('success', admin_bi('নতুন অ্যাডমিন অ্যাকাউন্ট তৈরি হয়েছে।', 'Admin account created.'));
                    } else {
                        if ($aid === (int) $admin['id'] && !$isActive) {
                            throw new RuntimeException(admin_bi('নিজের অ্যাকাউন্ট নিষ্ক্রিয় করা যাবে না।', 'You cannot deactivate your own admin account.'));
                        }
                        if ($password !== '') {
                            if (strlen($password) < 8) {
                                throw new RuntimeException(admin_bi('নতুন পাসওয়ার্ড কমপক্ষে ৮ অক্ষরের হতে হবে।', 'New password must be at least 8 characters.'));
                            }
                            $hash = password_hash($password, PASSWORD_BCRYPT);
                            db_exec(
                                'UPDATE admins SET name=?, email=?, password_hash=?, role=?, is_active=?, must_change_password=? WHERE id=?',
                                [$name, $email, $hash, $role, $isActive, $mustChangePw, $aid]
                            );
                        } else {
                            db_exec(
                                'UPDATE admins SET name=?, email=?, role=?, is_active=?, must_change_password=? WHERE id=?',
                                [$name, $email, $role, $isActive, $mustChangePw, $aid]
                            );
                        }
                        log_admin_action($admin, 'admin_update', 'admin', $aid, $email . ' (' . $role . ')');
                        log_security_event('admin_role_change', $email, 'Updated admin role/status to ' . $role . ' by ' . $admin['email']);
                        flash('success', admin_bi('অ্যাডমিন অ্যাকাউন্ট হালনাগাদ হয়েছে।', 'Admin account updated.'));
                    }
                } else {
                    $aid = (int) ($_POST['admin_id'] ?? 0);
                    if ($aid === (int) $admin['id']) {
                        throw new RuntimeException(admin_bi('নিজের অ্যাকাউন্ট নিষ্ক্রিয় করা যাবে না।', 'You cannot disable your own account.'));
                    }
                    db_exec('UPDATE admins SET is_active = 1 - is_active WHERE id = ?', [$aid]);
                    log_admin_action($admin, 'admin_toggle', 'admin', $aid, 'Toggled admin active status');
                    flash('success', admin_bi('অ্যাডমিন অ্যাকাউন্টের অবস্থা পরিবর্তিত হয়েছে।', 'Admin account status updated.'));
                }
                redirect('/admin/admins');
            }
        } catch (Throwable $ex) {
            if (db()->inTransaction()) {
                db()->rollBack();
            }
            flash('danger', $ex->getMessage());
            redirect(admin_return_url('/admin/' . ($section === 'dashboard' ? '' : $section), false));
        }
    }

    // 7. Render Admin Section
    $pendingOrdersCount = (int) (db_one("SELECT COUNT(*) AS c FROM orders WHERE status = 'pending'")['c'] ?? 0);
    $newInboxCount = (int) (db_one("SELECT COUNT(*) AS c FROM contact_messages WHERE status = 'new'")['c'] ?? 0);
    ob_start();
    require APP_ROOT . '/pages/admin/' . $sectionFile . '.php';
    $adminContentHtml = ob_get_clean();
    require APP_ROOT . '/pages/admin/layout.php';
    exit;
}
