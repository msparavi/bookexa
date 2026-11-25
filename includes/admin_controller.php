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

function handle_admin_routes(string $route): never
{
    header('X-Robots-Tag: noindex, nofollow, noarchive');
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
    header('Pragma: no-cache');

    $sub = trim(substr($route, 6), '/'); // strips '/admin'
    if ($sub === '') {
        $sub = 'dashboard';
    }

    // 1. Admin Login
    if ($sub === 'login') {
        if (!empty($_SESSION['admin_id'])) {
            redirect('/admin');
        }
        $error = null;
        if (isset($_GET['timeout'])) {
            $error = admin_bi(
                'নিষ্ক্রিয়তার কারণে অ্যাডমিন সেশন শেষ হয়েছে। অনুগ্রহ করে আবার লগইন করুন।',
                'Your admin session timed out after inactivity. Please log in again.'
            );
        }
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            verify_csrf();
            $res = attempt_admin_login((string) ($_POST['email'] ?? ''), (string) ($_POST['password'] ?? ''));
            if ($res['ok']) {
                flash('success', admin_bi('স্বাগতম, ', 'Welcome back, ') . $res['admin']['name'] . '!');
                redirect('/admin');
            }
            $error = $res['error'];
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

    // 4. AJAX / Streaming API Endpoints (/admin/api/*)
    if (str_starts_with($sub, 'api/')) {
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
            require_once APP_ROOT . '/includes/pdf_proxy.php';
            $fetched = fetch_source_pdf_secure((string) $item['drive_link']);
            if (!$fetched['ok']) {
                admin_json_response(['ok' => false, 'message' => $fetched['message'] ?? 'Fetch failed'], 502);
            }
            log_admin_action($admin, 'admin_stream_source', 'item', $itemId, 'Prepared PDF source stream for page export');
            header('Content-Type: application/pdf');
            header('Content-Length: ' . strlen($fetched['bytes']));
            header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
            header('X-Content-Type-Options: nosniff');
            echo $fetched['bytes'];
            exit;
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
                $dir = page_storage_dir($itemId);
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
                db_exec("UPDATE items SET pages_status = 'processing' WHERE id = ?", [$itemId]);
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
                    if (find_prepared_page_file($itemId, $p) === null) {
                        throw new RuntimeException("Missing rendered page {$p} of {$totalPages}. Resume preparation to retry.");
                    }
                }
                // Remove stale leftover higher page numbers from older PDF versions
                $dir = page_storage_dir($itemId);
                foreach (glob($dir . '/page-*.*') ?: [] as $fPath) {
                    if (preg_match('/page-(\d+)\.(webp|jpg)$/', basename($fPath), $pm) && (int) $pm[1] > $totalPages) {
                        @unlink($fPath);
                    }
                }
                db_exec("UPDATE items SET total_pages = ?, pages_status = 'ready', processed_at = NOW() WHERE id = ?", [$totalPages, $itemId]);
                log_admin_action($admin, 'pdf_prepare_pages', 'item', $itemId, "Prepared {$totalPages} protected pages");
                admin_json_response(['ok' => true, 'total_pages' => $totalPages, 'message' => admin_bi('সব সুরক্ষিত পৃষ্ঠা প্রস্তুত হয়েছে!', 'Protected pages ready!')]);
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

            admin_json_response(['ok' => false, 'message' => 'Unknown API endpoint'], 404);
        } catch (Throwable $e) {
            admin_json_response(['ok' => false, 'message' => $e->getMessage()], 400);
        }
    }

    // 5. Section Routing & Role Enforcement
    $allowedSections = ['dashboard', 'banners', 'folders', 'pdfs', 'prepare-pages', 'orders', 'coupons', 'reviews', 'reports', 'users', 'logs', 'media', 'settings', 'admins', 'account-security'];
    $section = in_array($sub, $allowedSections, true) ? $sub : 'dashboard';
    $viewMap = ['prepare-pages' => 'prepare_pages', 'account-security' => 'account_security'];
    $sectionFile = $viewMap[$section] ?? $section;

    if (!empty($admin['must_change_password']) && $section !== 'account-security') {
        flash('warning', admin_bi('নিরাপত্তার জন্য প্রথমে আপনার অ্যাডমিন পাসওয়ার্ড পরিবর্তন করুন।', 'For security, please change your initial password first.'));
        redirect('/admin/account-security');
    }

    if ($section === 'admins' && $admin['role'] !== 'superadmin') {
        flash('danger', admin_bi('এই বিভাগে শুধুমাত্র সুপার অ্যাডমিনের অনুমতি রয়েছে।', 'Only Super Admins may manage admin accounts.'));
        redirect('/admin');
    }

    // 5b. CSV Export Stream for Reports (before any HTML layout output)
    if ($section === 'reports' && !empty($_GET['export'])) {
        $expType = (string) $_GET['export'];
        $days = in_array((int) ($_GET['days'] ?? 30), [7, 30, 90], true) ? (int) $_GET['days'] : 30;
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
            $rows = db_all("SELECT c.code, c.discount_type, c.discount_value, COUNT(o.id) AS ocnt, SUM(CASE WHEN o.status = 'approved' THEN 1 ELSE 0 END) AS acnt, COALESCE(SUM(CASE WHEN o.status != 'rejected' THEN o.discount_amount ELSE 0 END), 0) AS tdisc, COALESCE(SUM(CASE WHEN o.status = 'approved' THEN o.amount ELSE 0 END), 0) AS trev FROM coupons c LEFT JOIN orders o ON o.coupon_id = c.id GROUP BY c.id ORDER BY ocnt DESC");
            foreach ($rows as $r) {
                fputcsv($out, [$r['code'], $r['discount_type'], $r['discount_value'], (int) $r['ocnt'], (int) $r['acnt'], number_format((float) $r['tdisc'], 2, '.', ''), number_format((float) $r['trev'], 2, '.', '')]);
            }
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
                $startsAt = admin_parse_datetime($_POST['starts_at'] ?? null);
                $endsAt = admin_parse_datetime($_POST['ends_at'] ?? null);
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
                        'INSERT INTO banners (title_bn, title_en, subtitle_bn, subtitle_en, cta_bn, cta_en, link_url, image, mobile_image, sort_order, starts_at, ends_at, is_active)
                         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
                        [$titleBn, $titleEn, $subBn, $subEn, $ctaBn, $ctaEn, $linkUrl, $imgPath, $uploadedMobile, $sortOrder, $startsAt, $endsAt, $isActive]
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
                        'UPDATE banners SET title_bn=?, title_en=?, subtitle_bn=?, subtitle_en=?, cta_bn=?, cta_en=?, link_url=?, image=?, mobile_image=?, sort_order=?, starts_at=?, ends_at=?, is_active=? WHERE id=?',
                        [$titleBn, $titleEn, $subBn, $subEn, $ctaBn, $ctaEn, $linkUrl, $imgPath, $mobPath, $sortOrder, $startsAt, $endsAt, $isActive, $id]
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
                $isActive = isset($_POST['is_active']) ? 1 : 0;

                if ($nameBn === '' || $nameEn === '') {
                    throw new RuntimeException(admin_bi('বাংলা ও ইংরেজি উভয় ফোল্ডার নাম আবশ্যক।', 'Both Bangla and English folder names are required.'));
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
                        'INSERT INTO folders (parent_id, slug, name_bn, name_en, description_bn, description_en, thumbnail, accent_color, sort_order, is_active)
                         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
                        [$parentId, $slug, $nameBn, $nameEn, $descBn, $descEn, $thumb, $accent, $sortOrder, $isActive]
                    );
                    $newId = (int) db()->lastInsertId();
                    log_admin_action($admin, 'folder_create', 'folder', $newId, $nameEn . ' (/' . $slug . ')');
                    flash('success', admin_bi('ফোল্ডার তৈরি হয়েছে: /', 'Folder created with slug /') . $slug);
                } else {
                    $existing = db_one('SELECT thumbnail FROM folders WHERE id = ?', [$id]);
                    $oldThumb = $existing['thumbnail'] ?? null;
                    $thumb = $uploadedThumb ?: ($oldThumb ?: 'uploads/folders/academic.svg');
                    db_exec(
                        'UPDATE folders SET parent_id=?, slug=?, name_bn=?, name_en=?, description_bn=?, description_en=?, thumbnail=?, accent_color=?, sort_order=?, is_active=? WHERE id=?',
                        [$parentId, $slug, $nameBn, $nameEn, $descBn, $descEn, $thumb, $accent, $sortOrder, $isActive, $id]
                    );
                    if ($uploadedThumb && $oldThumb && $oldThumb !== $thumb) {
                        media_try_delete_unused_image((string) $oldThumb);
                    }
                    log_admin_action($admin, 'folder_update', 'folder', $id, $nameEn . ' (/' . $slug . ')');
                    flash('success', admin_bi('ফোল্ডার হালনাগাদ হয়েছে।', 'Folder updated.'));
                }
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
                    log_admin_action($admin, 'folder_move', 'folder', $id, $curr['name_en'] . ' -> parent #' . ($newParentId ?? 0));
                    flash('success', admin_bi('ফোল্ডার সরানো হয়েছে।', 'Folder moved.'));
                }
                redirect(admin_return_url('/admin/folders'));
            }

            if ($action === 'toggle_folder') {
                $id = (int) ($_POST['id'] ?? 0);
                db_exec('UPDATE folders SET is_active = 1 - is_active WHERE id = ?', [$id]);
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

                if ($titleBn === '' || $titleEn === '') {
                    throw new RuntimeException(admin_bi('বাংলা ও ইংরেজি উভয় PDF শিরোনাম আবশ্যক।', 'Both Bangla and English PDF titles are required.'));
                }

                $allowLocal = ($id !== null && str_starts_with($driveInput, 'local://'));
                $validDrive = validate_drive_url($driveInput, $allowLocal);
                if ($validDrive === null) {
                    throw new RuntimeException(admin_bi(
                        'সঠিক Google Drive URL দিন। উদাহরণ: https://drive.google.com/file/d/FILE_ID/view?usp=sharing',
                        'Invalid Google Drive URL. Example: https://drive.google.com/file/d/FILE_ID/view?usp=sharing'
                    ));
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
                        'INSERT INTO items (folder_id, slug, title_bn, title_en, author_bn, author_en, description_bn, description_en, cover, drive_link, sample_drive_link, price, original_price, is_free, sample_pages, total_pages, is_featured, is_active)
                         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
                        [$folderId, $slug, $titleBn, $titleEn, $authorBn, $authorEn, $descBn, $descEn, $cover, $validDrive, $validSampleDrive, $price, $origPrice, $isFree, $samplePages, $totalPages, $isFeatured, $isActive]
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
                        'UPDATE items SET folder_id=?, slug=?, title_bn=?, title_en=?, author_bn=?, author_en=?, description_bn=?, description_en=?, cover=?, drive_link=?, sample_drive_link=?, price=?, original_price=?, is_free=?, sample_pages=?, total_pages=?, is_featured=?, is_active=? WHERE id=?',
                        [$folderId, $slug, $titleBn, $titleEn, $authorBn, $authorEn, $descBn, $descEn, $cover, $validDrive, $validSampleDrive, $price, $origPrice, $isFree, $samplePages, $totalPages, $isFeatured, $isActive, $id]
                    );
                    if ($uploadedCover && $oldCover && $oldCover !== $cover) {
                        media_try_delete_unused_image((string) $oldCover);
                    }
                    log_admin_action($admin, 'pdf_update', 'item', $id, $titleEn);
                    flash('success', admin_bi('PDF তথ্য হালনাগাদ হয়েছে।', 'PDF item updated.'));
                }
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
                    'INSERT INTO items (folder_id, slug, title_bn, title_en, author_bn, author_en, description_bn, description_en, cover, drive_link, sample_drive_link, price, original_price, is_free, sample_pages, total_pages, is_featured, is_active, sort_order)
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 0, 0, ?)',
                    [
                        $src['folder_id'], $newSlug, $copyBn, $copyEn,
                        $src['author_bn'], $src['author_en'], $src['description_bn'], $src['description_en'],
                        $src['cover'], $src['drive_link'], $src['sample_drive_link'],
                        $src['price'], $src['original_price'], (int) $src['is_free'],
                        (int) $src['sample_pages'], $src['total_pages'], (int) $src['sort_order'],
                    ]
                );
                $newId = (int) db()->lastInsertId();
                log_admin_action($admin, 'pdf_duplicate', 'item', $newId, 'Duplicated from #' . $id);
                flash('success', admin_bi('PDF-টির কপি তৈরি হয়েছে (খসড়া হিসেবে)।', 'PDF duplicated (saved hidden as draft).'));
                redirect(admin_return_url('/admin/pdfs'));
            }

            if ($action === 'toggle_pdf') {
                $id = (int) ($_POST['id'] ?? 0);
                db_exec('UPDATE items SET is_active = 1 - is_active WHERE id = ?', [$id]);
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

            // --- Orders ---
            if ($action === 'review_order') {
                $orderId = (int) ($_POST['order_id'] ?? 0);
                $decision = (string) ($_POST['decision'] ?? '');
                $adminNote = trim((string) ($_POST['admin_note'] ?? ''));

                if (!in_array($decision, ['approved', 'rejected'], true)) {
                    throw new RuntimeException(admin_bi('অর্ডার সিদ্ধান্ত সঠিক নয়।', 'Invalid order decision.'));
                }

                $order = db_one('SELECT * FROM orders WHERE id = ?', [$orderId]);
                if (!$order) {
                    throw new RuntimeException(admin_bi('অর্ডার খুঁজে পাওয়া যায়নি।', 'Order not found.'));
                }

                db()->beginTransaction();
                db_exec(
                    'UPDATE orders SET status=?, admin_note=?, reviewed_by=?, reviewed_at=NOW() WHERE id=?',
                    [$decision, $adminNote !== '' ? $adminNote : null, (int) $admin['id'], $orderId]
                );
                if ($decision === 'approved') {
                    db_exec(
                        'INSERT INTO library (user_id, item_id, order_id) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE order_id = VALUES(order_id)',
                        [(int) $order['user_id'], (int) $order['item_id'], $orderId]
                    );
                }
                db()->commit();

                $orderUser = db_one('SELECT id, name, email FROM users WHERE id = ?', [(int) $order['user_id']]);
                $orderItem = db_one('SELECT id, slug, title_bn, title_en FROM items WHERE id = ?', [(int) $order['item_id']]);
                if ($orderUser && $orderItem) {
                    if ($decision === 'approved') {
                        create_user_notification(
                            (int) $orderUser['id'],
                            'order_approved',
                            'অর্ডার অনুমোদিত হয়েছে (#' . $order['order_code'] . ')',
                            'Order Approved (#' . $order['order_code'] . ')',
                            $orderItem['title_bn'] . ' আপনার লাইব্রেরিতে যুক্ত হয়েছে।',
                            $orderItem['title_en'] . ' is now unlocked in My Library.',
                            '/reader/' . rawurlencode((string) $orderItem['slug'])
                        );
                        if (!empty($orderUser['email'])) {
                            send_bookexa_template_mail(
                                (string) $orderUser['email'],
                                'Order Approved #' . $order['order_code'] . ' — Bookexa',
                                'Your PDF Is Ready to Read!',
                                '<p>Hello <strong>' . e($orderUser['name']) . '</strong>,</p><p>Your order <strong>#' . e($order['order_code']) . '</strong> for <strong>' . e($orderItem['title_en']) . '</strong> has been approved and unlocked in My Library.</p>',
                                BASE_URL . '/reader/' . rawurlencode((string) $orderItem['slug']),
                                'Open in Reader'
                            );
                        }
                    } else {
                        create_user_notification(
                            (int) $orderUser['id'],
                            'order_rejected',
                            'অর্ডার বাতিল করা হয়েছে (#' . $order['order_code'] . ')',
                            'Order Rejected (#' . $order['order_code'] . ')',
                            $adminNote !== '' ? $adminNote : ($orderItem['title_bn'] . ' এর পেমেন্ট যাচাই করা যায়নি।'),
                            $adminNote !== '' ? $adminNote : ('Payment verification failed for ' . $orderItem['title_en'] . '.'),
                            '/orders'
                        );
                        if (!empty($orderUser['email'])) {
                            send_bookexa_template_mail(
                                (string) $orderUser['email'],
                                'Order Update #' . $order['order_code'] . ' — Bookexa',
                                'Order Not Approved #' . $order['order_code'],
                                '<p>Hello <strong>' . e($orderUser['name']) . '</strong>,</p><p>Your payment verification for <strong>' . e($orderItem['title_en']) . '</strong> could not be approved.</p>' . ($adminNote !== '' ? '<p><strong>Note:</strong> ' . e($adminNote) . '</p>' : ''),
                                BASE_URL . '/orders',
                                'View My Orders'
                            );
                        }
                    }
                }

                log_admin_action($admin, 'order_' . $decision, 'order', $orderId, 'Order ' . $order['order_code']);
                flash('success', admin_bi('অর্ডার #', 'Order #') . $order['order_code'] . ' -> ' . $decision);
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
                $smtpEnc = in_array((string) ($_POST['smtp_encryption'] ?? 'tls'), ['tls', 'ssl', 'none'], true)
                    ? (string) $_POST['smtp_encryption']
                    : 'tls';
                db_exec('INSERT INTO settings (setting_key, setting_value) VALUES ("smtp_port", ?) ON DUPLICATE KEY UPDATE setting_value = VALUES(smtp_port)', [$smtpPort]);
                db_exec('INSERT INTO settings (setting_key, setting_value) VALUES ("smtp_encryption", ?) ON DUPLICATE KEY UPDATE setting_value = VALUES(smtp_encryption)', [$smtpEnc]);
                $smtpPassNew = (string) ($_POST['smtp_pass'] ?? '');
                if ($smtpPassNew !== '') {
                    db_exec('INSERT INTO settings (setting_key, setting_value) VALUES ("smtp_pass", ?) ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)', [$smtpPassNew]);
                }
                $defLang = ($_POST['default_language'] ?? 'bn') === 'en' ? 'en' : 'bn';
                $maint = isset($_POST['maintenance_mode']) ? '1' : '0';
                $shopEnabled = isset($_POST['shop_enabled']) ? '1' : '0';
                $freeFallback = isset($_POST['free_pdfjs_fallback']) ? '1' : '0';
                foreach (['default_language' => $defLang, 'maintenance_mode' => $maint, 'shop_enabled' => $shopEnabled, 'free_pdfjs_fallback' => $freeFallback] as $k => $v) {
                    db_exec(
                        'INSERT INTO settings (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)',
                        [$k, $v]
                    );
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
                log_admin_action($admin, 'settings_update', 'settings', null, 'Updated site/payment/maintenance settings');
                flash('success', admin_bi('সাইট ও পেমেন্ট সেটিংস সংরক্ষিত হয়েছে।', 'Site & payment settings saved.'));
                redirect('/admin/settings');
            }

            // --- Account Security (Required First-Run / Self Password Change) ---
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
                flash('success', admin_bi('পাসওয়ার্ড আপডেট হয়েছে।', 'Your password has been updated.'));
                redirect('/admin');
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
                    $role = ($_POST['role'] ?? 'editor') === 'superadmin' ? 'superadmin' : 'editor';
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
    ob_start();
    require APP_ROOT . '/pages/admin/' . $sectionFile . '.php';
    $adminContentHtml = ob_get_clean();
    require APP_ROOT . '/pages/admin/layout.php';
    exit;
}
