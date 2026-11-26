<?php
declare(strict_types=1);

require_once APP_ROOT . '/includes/totp.php';

const ADMIN_SESSION_TIMEOUT    = 1800; // 30 minutes
const ADMIN_LOGIN_MAX_ATTEMPTS = 5;
const ADMIN_LOGIN_WINDOW_SEC   = 600;

const ADMIN_ROLE_PERMISSIONS = [
    'superadmin' => ['*'],
    'editor'     => ['dashboard', 'banners', 'folders', 'pdfs', 'prepare-pages', 'prepare-queue', 'import-csv', 'reviews', 'media', 'shop-categories', 'shop-products', 'guides', 'hubs', 'seo', 'aeo', 'growth', 'account-security'],
    'support'    => ['dashboard', 'orders', 'shop-orders', 'shop-packing-slip', 'inbox', 'users', 'account-security'],
    'analyst'    => ['dashboard', 'reports', 'growth', 'account-security'],
];

function admin_role_label(string $role): string
{
    return match ($role) {
        'superadmin' => 'Super Admin',
        'editor'     => 'Editor',
        'support'    => 'Support',
        'analyst'    => 'Analyst',
        default      => ucfirst($role),
    };
}

function admin_can(array $admin, string $section): bool
{
    $role = (string) ($admin['role'] ?? 'editor');
    $allowed = ADMIN_ROLE_PERMISSIONS[$role] ?? ['dashboard', 'account-security'];
    if (in_array('*', $allowed, true)) {
        return true;
    }
    return in_array($section, $allowed, true);
}

function admin_api_section(string $sub): string
{
    return match ($sub) {
        'api/folder-move', 'api/folder-rename', 'api/folder-step' => 'folders',
        'api/banner-reorder' => 'banners',
        'api/test-drive-link', 'api/stream-source', 'api/upload-page', 'api/finalize-pages',
        'api/discard-staging', 'api/upload-auto-cover', 'api/prepare-queue-next' => 'prepare-pages',
        'api/import-csv-batch' => 'import-csv',
        'api/shop-stock', 'api/shop-image-cover', 'api/shop-image-delete' => 'shop-products',
        'api/test-smtp', 'api/cdn-test', 'api/cdn-sync-batch', 'api/cdn-recheck', 'api/cdn-pull' => 'settings',
        default => 'dashboard',
    };
}

function admin_ipv4_in_cidr(string $ip, string $cidr): bool
{
    if (!str_contains($cidr, '/')) {
        return hash_equals($cidr, $ip);
    }
    [$net, $bitsRaw] = explode('/', $cidr, 2);
    $ipLong  = @ip2long($ip);
    $netLong = @ip2long($net);
    $bits    = (int) $bitsRaw;
    if ($ipLong === false || $netLong === false || $bits < 0 || $bits > 32) {
        return false;
    }
    $mask = $bits === 0 ? 0 : (~0 << (32 - $bits));
    return ($ipLong & $mask) === ($netLong & $mask);
}

function admin_ip_allowed(string $ip): bool
{
    $raw = trim(site_setting('admin_ip_allowlist', ''));
    if ($raw === '') {
        return true;
    }
    $entries = array_values(array_filter(array_map('trim', preg_split('/[\s,;]+/', $raw) ?: [])));
    if (!$entries) {
        return true;
    }
    foreach ($entries as $entry) {
        if ($entry === $ip || admin_ipv4_in_cidr($ip, $entry)) {
            return true;
        }
        // Normalize IPv6 loopback vs IPv4 loopback on local hosts
        if (($entry === '127.0.0.1' && $ip === '::1') || ($entry === '::1' && $ip === '127.0.0.1')) {
            return true;
        }
    }
    return false;
}

function list_admin_sessions(int $adminId): array
{
    return db_all(
        'SELECT id, session_token, ip_address, user_agent, last_active_at, created_at FROM admin_sessions WHERE admin_id = ? ORDER BY last_active_at DESC, id DESC',
        [$adminId]
    );
}

function revoke_all_admin_sessions(int $adminId, ?string $keepToken = null): int
{
    if ($keepToken !== null && $keepToken !== '') {
        $stmt = db()->prepare('DELETE FROM admin_sessions WHERE admin_id = ? AND session_token <> ?');
        $stmt->execute([$adminId, $keepToken]);
        return $stmt->rowCount();
    }
    $stmt = db()->prepare('DELETE FROM admin_sessions WHERE admin_id = ?');
    $stmt->execute([$adminId]);
    return $stmt->rowCount();
}

function admin_rate_limit_check(string $key, int $maxHits, int $windowSeconds): bool
{
    $now = time();
    $row = db_one('SELECT hits, window_start FROM rate_limits WHERE rate_key = ?', [$key]);
    if (!$row) {
        db_exec(
            'INSERT INTO rate_limits (rate_key, hits, window_start) VALUES (?, 1, ?) ON DUPLICATE KEY UPDATE hits = 1, window_start = VALUES(window_start)',
            [$key, $now]
        );
        return true;
    }
    $windowStart = (int) $row['window_start'];
    $hits = (int) $row['hits'];
    if (($now - $windowStart) >= $windowSeconds) {
        db_exec('UPDATE rate_limits SET hits = 1, window_start = ? WHERE rate_key = ?', [$now, $key]);
        return true;
    }
    if ($hits >= $maxHits) {
        return false;
    }
    db_exec('UPDATE rate_limits SET hits = hits + 1 WHERE rate_key = ?', [$key]);
    return true;
}

function log_admin_action(array $admin, string $action, ?string $targetType = null, ?int $targetId = null, ?string $details = null): void
{
    try {
        db_exec(
            'INSERT INTO admin_logs (admin_id, admin_name, action, target_type, target_id, details, ip_address)
             VALUES (?, ?, ?, ?, ?, ?, ?)',
            [
                (int) $admin['id'],
                substr((string) $admin['name'], 0, 120),
                substr($action, 0, 80),
                $targetType !== null ? substr($targetType, 0, 50) : null,
                $targetId,
                $details !== null ? substr($details, 0, 250) : null,
                client_ip(),
            ]
        );
    } catch (Throwable $e) {
    }
}

function log_security_event(string $eventType, ?string $identifier, ?string $details = null): void
{
    try {
        db_exec(
            'INSERT INTO security_logs (event_type, identifier, ip_address, user_agent, details) VALUES (?, ?, ?, ?, ?)',
            [
                substr($eventType, 0, 60),
                $identifier !== null ? substr($identifier, 0, 190) : null,
                client_ip(),
                substr((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 250),
                $details !== null ? substr($details, 0, 250) : null,
            ]
        );
    } catch (Throwable $e) {
    }
}

function require_admin(): array
{
    $aid = $_SESSION['admin_id'] ?? null;
    if (!$aid) {
        redirect('/admin/login');
    }

    $ip = client_ip();
    if (!admin_ip_allowed($ip)) {
        log_security_event('admin_ip_blocked', 'admin#' . (int) $aid, 'Blocked IP: ' . $ip);
        admin_logout();
        render_error_page(403, 'IP Access Denied', 'Your IP address is not in the configured admin allowlist.');
    }

    $lastActivity = (int) ($_SESSION['admin_last_activity'] ?? 0);
    if ($lastActivity > 0 && (time() - $lastActivity) > ADMIN_SESSION_TIMEOUT) {
        admin_logout();
        flash('warning', current_lang() === 'bn' ? 'সেশন মেয়াদ শেষ হয়েছে। আবার লগইন করুন।' : 'Session timed out due to inactivity. Please log in again.');
        redirect('/admin/login?timeout=1');
    }

    $admin = db_one('SELECT id, name, email, role, is_active, must_change_password, totp_enabled, password_hash FROM admins WHERE id = ? LIMIT 1', [(int) $aid]);
    if (!$admin || !(int) $admin['is_active']) {
        admin_logout();
        redirect('/admin/login');
    }
    $sessTok = (string) ($_SESSION['admin_session_token'] ?? '');
    if ($sessTok !== '') {
        $sRow = db_one('SELECT id FROM admin_sessions WHERE admin_id = ? AND session_token = ? LIMIT 1', [(int) $aid, $sessTok]);
        if (!$sRow) {
            unset($_SESSION['admin_id'], $_SESSION['admin_last_activity'], $_SESSION['admin_session_token'], $_SESSION['admin_session_ping']);
            flash('warning', current_lang() === 'bn' ? 'সেশন বাতিল করা হয়েছে। আবার লগইন করুন।' : 'This admin session was signed out. Please log in again.');
            redirect('/admin/login');
        }
        $lastPing = (int) ($_SESSION['admin_session_ping'] ?? 0);
        if ((time() - $lastPing) >= 60) {
            db_exec('UPDATE admin_sessions SET last_active_at = NOW(), ip_address = ? WHERE id = ?', [$ip, (int) $sRow['id']]);
            $_SESSION['admin_session_ping'] = time();
        }
    }
    if (app_config('app_env') === 'production' && password_verify('Admin@1234', (string) $admin['password_hash'])) {
        $admin['must_change_password'] = 1;
    }
    unset($admin['password_hash']);

    $_SESSION['admin_last_activity'] = time();
    return $admin;
}

function complete_admin_login(array $admin): void
{
    $aid = (int) $admin['id'];
    db_exec('UPDATE admins SET last_login_at = NOW() WHERE id = ?', [$aid]);
    if (session_status() === PHP_SESSION_ACTIVE && PHP_SAPI !== 'cli') {
        session_regenerate_id(true);
    }
    $sessTok = bin2hex(random_bytes(32));
    $ua = substr((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 250);
    db_exec(
        'INSERT INTO admin_sessions (admin_id, session_token, ip_address, user_agent, last_active_at) VALUES (?, ?, ?, ?, NOW())',
        [$aid, $sessTok, client_ip(), $ua]
    );
    $_SESSION['admin_id'] = $aid;
    $_SESSION['admin_session_token'] = $sessTok;
    $_SESSION['admin_last_activity'] = time();
    $_SESSION['admin_session_ping'] = time();
    log_admin_action($admin, 'admin_login', 'admin', $aid, 'Signed into dashboard');
}

function attempt_admin_login(string $email, string $password): array
{
    $ip = client_ip();
    if (!admin_ip_allowed($ip)) {
        log_security_event('admin_ip_blocked', $email, 'Login blocked for IP: ' . $ip);
        return ['ok' => false, 'error' => current_lang() === 'bn' ? 'আপনার IP থেকে অ্যাডমিন প্যানেলে প্রবেশ অনুমোদিত নয়।' : 'Access denied: your IP address is not in the admin allowlist.'];
    }

    $ipKey = 'admin_login:ip:' . $ip;
    if (!admin_rate_limit_check($ipKey, ADMIN_LOGIN_MAX_ATTEMPTS, ADMIN_LOGIN_WINDOW_SEC)) {
        log_security_event('failed_admin_login', $email, 'Rate limit exceeded');
        return ['ok' => false, 'error' => current_lang() === 'bn' ? 'অনেকবার ভুল চেষ্টা হয়েছে। ১০ মিনিট পর আবার চেষ্টা করুন।' : 'Too many failed login attempts. Please wait 10 minutes.'];
    }

    $email = strtolower(trim($email));
    $admin = db_one('SELECT * FROM admins WHERE email = ? LIMIT 1', [$email]);

    if (!$admin || !password_verify($password, $admin['password_hash'])) {
        log_security_event('failed_admin_login', $email, 'Invalid password or unknown email');
        return ['ok' => false, 'error' => current_lang() === 'bn' ? 'অ্যাডমিন ইমেইল বা পাসওয়ার্ড সঠিক নয়।' : 'Invalid admin email or password.'];
    }

    if (!(int) $admin['is_active']) {
        log_security_event('failed_admin_login', $email, 'Disabled admin account');
        return ['ok' => false, 'error' => 'This admin account is disabled.'];
    }

    db_exec('DELETE FROM rate_limits WHERE rate_key = ?', [$ipKey]);

    if (!empty($admin['totp_enabled']) && !empty($admin['totp_secret'])) {
        return ['ok' => false, 'code' => 'TOTP_REQUIRED', 'admin' => $admin];
    }

    complete_admin_login($admin);
    return ['ok' => true, 'admin' => $admin];
}

function verify_admin_totp_and_login(int $adminId, string $code): array
{
    $rateKey = 'admin_2fa:admin:' . $adminId;
    if (!admin_rate_limit_check($rateKey, 5, 600)) {
        log_security_event('failed_admin_2fa', 'admin#' . $adminId, '2FA rate limit exceeded (5/10m)');
        return ['ok' => false, 'error' => current_lang() === 'bn' ? 'অনেকবার ভুল 2FA কোড। ১০ মিনিট পর চেষ্টা করুন।' : 'Too many invalid 2FA attempts. Wait 10 minutes.'];
    }
    $admin = db_one('SELECT * FROM admins WHERE id = ? AND is_active = 1 LIMIT 1', [$adminId]);
    if (!$admin || empty($admin['totp_enabled']) || empty($admin['totp_secret'])) {
        return ['ok' => false, 'error' => 'Invalid 2FA session.'];
    }
    $wasPlain = false;
    $plainSecret = totp_decrypt_secret((string) $admin['totp_secret'], $wasPlain);
    if ($plainSecret === null) {
        log_security_event('failed_admin_2fa', (string) $admin['email'], 'Unable to decrypt TOTP secret');
        return ['ok' => false, 'error' => current_lang() === 'bn' ? '2FA কী পাঠোদ্ধার করা যায়নি।' : 'Invalid 2FA configuration.'];
    }
    if ($wasPlain) {
        db_exec('UPDATE admins SET totp_secret = ? WHERE id = ?', [totp_encrypt_secret($plainSecret), $adminId]);
    }
    $lastStep = isset($admin['totp_last_step']) && $admin['totp_last_step'] !== null ? (int) $admin['totp_last_step'] : null;
    $matchedStep = totp_verify_with_step($plainSecret, $code, 1, 30, null, $lastStep);
    $ok = false;
    if ($matchedStep !== null) {
        db_exec('UPDATE admins SET totp_last_step = ? WHERE id = ?', [$matchedStep, $adminId]);
        $ok = true;
    } elseif (totp_consume_backup_code($adminId, $code)) {
        $ok = true;
    }
    if (!$ok) {
        log_security_event('failed_admin_2fa', (string) $admin['email'], 'Invalid or replayed authenticator/backup code');
        return ['ok' => false, 'error' => current_lang() === 'bn' ? 'অথেনটিকেটর বা ব্যাকআপ কোড সঠিক নয় বা ইতিমধ্যে ব্যবহৃত হয়েছে।' : 'Invalid, replayed, or expired authenticator/backup code.'];
    }
    db_exec('DELETE FROM rate_limits WHERE rate_key = ?', [$rateKey]);
    log_security_event('admin_2fa_success', (string) $admin['email'], 'Passed TOTP/backup challenge');
    complete_admin_login($admin);
    return ['ok' => true, 'admin' => $admin];
}

function admin_logout(): void
{
    $aid = (int) ($_SESSION['admin_id'] ?? 0);
    $tok = (string) ($_SESSION['admin_session_token'] ?? '');
    if ($aid > 0 && $tok !== '') {
        try {
            db_exec('DELETE FROM admin_sessions WHERE admin_id = ? AND session_token = ?', [$aid, $tok]);
        } catch (Throwable $e) {
        }
    }
    unset($_SESSION['admin_id'], $_SESSION['admin_last_activity'], $_SESSION['admin_session_token'], $_SESSION['admin_session_ping']);
    if (session_status() === PHP_SESSION_ACTIVE && PHP_SAPI !== 'cli') {
        session_regenerate_id(true);
    }
}

function generate_unique_folder_slug(string $input, ?int $parentId, ?int $excludeId = null): string
{
    $base = slugify($input);
    $candidate = $base;
    $suffix = 2;
    while (true) {
        if ($parentId === null) {
            $sql = 'SELECT id FROM folders WHERE parent_id IS NULL AND slug = ?';
            $params = [$candidate];
        } else {
            $sql = 'SELECT id FROM folders WHERE parent_id = ? AND slug = ?';
            $params = [$parentId, $candidate];
        }
        if ($excludeId !== null) {
            $sql .= ' AND id != ?';
            $params[] = $excludeId;
        }
        if (!db_one($sql . ' LIMIT 1', $params)) {
            return $candidate;
        }
        $candidate = $base . '-' . $suffix;
        $suffix++;
    }
}

function generate_unique_item_slug(string $input, ?int $excludeId = null): string
{
    $base = slugify($input);
    $candidate = $base;
    $suffix = 2;
    while (true) {
        $sql = 'SELECT id FROM items WHERE slug = ?';
        $params = [$candidate];
        if ($excludeId !== null) {
            $sql .= ' AND id != ?';
            $params[] = $excludeId;
        }
        if (!db_one($sql . ' LIMIT 1', $params)) {
            return $candidate;
        }
        $candidate = $base . '-' . $suffix;
        $suffix++;
    }
}

function validate_drive_url(string $url, bool $allowLocalDemo = true): ?string
{
    $url = trim($url);
    if ($allowLocalDemo && preg_match('/^local:\/\/[a-zA-Z0-9._-]+\.pdf$/', $url)) {
        return $url;
    }
    $parts = parse_url($url);
    if (!$parts || empty($parts['scheme']) || empty($parts['host'])) {
        return null;
    }
    if (!in_array(strtolower($parts['scheme']), ['https', 'http'], true)) {
        return null;
    }
    $host = strtolower($parts['host']);
    $allowedHosts = ['drive.google.com', 'docs.google.com', 'drive.usercontent.google.com'];
    if (!in_array($host, $allowedHosts, true)) {
        return null;
    }
    if (preg_match('~/file/d/([a-zA-Z0-9_-]{15,})~', $url) || preg_match('~[?&]id=([a-zA-Z0-9_-]{15,})~', $url)) {
        return $url;
    }
    return null;
}

/**
 * Checks whether a Drive or local demo PDF link is reachable and reports detailed diagnostics:
 * size_mb, is_pdf, confirm_needed, page_count, safe_limit_warning, and exact failure reason.
 */
function parse_curl_headers_and_body(string $raw, int $headerSize): array
{
    $hdrRaw = substr($raw, 0, $headerSize);
    $body = substr($raw, $headerSize);
    $blocks = preg_split("/\r?\n\r?\n/", trim($hdrRaw)) ?: [];
    $lastBlock = end($blocks) ?: '';
    $headers = [];
    foreach (preg_split("/\r?\n/", $lastBlock) as $line) {
        if (str_contains($line, ':')) {
            [$k, $v] = explode(':', $line, 2);
            $headers[strtolower(trim($k))] = trim($v);
        }
    }
    return [$headers, $body];
}

function test_drive_reachability(string $url, ?array $testOverride = null): array
{
    require_once APP_ROOT . '/includes/pdf_proxy.php';
    $limits = server_safe_pdf_limits();

    $evaluateServerWarning = static function (?int $sizeBytes) use ($limits): array {
        $sizeMb = $sizeBytes !== null ? round($sizeBytes / 1048576, 2) : null;
        $warn = null;
        $tooLarge = false;
        if ($sizeMb !== null) {
            if ($limits['max_execution_time'] > 0 && $limits['max_execution_time'] < 45 && $sizeMb > 180) {
                $tooLarge = true;
                $warn = "File ({$sizeMb} MB) exceeds safe timeout budget for max_execution_time={$limits['max_execution_time']}s; use Prepare Protected Pages from your computer instead.";
            } elseif ($sizeMb >= $limits['recommended_warn_mb']) {
                $warn = "Large PDF ({$sizeMb} MB vs server memory_limit={$limits['memory_limit_raw']}). Streaming/Range works, and Prepare Protected Pages (or local computer upload) is strongly recommended.";
            }
        }
        return [$sizeMb, $warn, $tooLarge];
    };

    $valid = validate_drive_url($url, true);
    if ($valid === null) {
        return [
            'ok' => false, 'reachable' => false, 'is_pdf' => false, 'confirm_needed' => false,
            'size_bytes' => null, 'size_mb' => null, 'page_count' => null, 'api_used' => false,
            'safe_limit_warning' => null, 'reason_code' => 'INVALID_URL',
            'message' => 'Invalid Google Drive URL format.',
        ];
    }

    if (str_starts_with($valid, 'local://')) {
        $f = APP_ROOT . '/storage/demo/' . basename(substr($valid, 8));
        if (!is_file($f)) {
            return [
                'ok' => false, 'reachable' => false, 'is_pdf' => false, 'confirm_needed' => false,
                'size_bytes' => null, 'size_mb' => null, 'page_count' => null, 'api_used' => false,
                'safe_limit_warning' => null, 'reason_code' => 'NOT_FOUND',
                'message' => 'Demo file not found in protected storage.',
            ];
        }
        $sz = (int) filesize($f);
        $isPdf = pdf_file_starts_with_magic($f);
        $pages = count_pdf_pages_fast_file($f);
        [$sizeMb, $warn] = $evaluateServerWarning($sz);
        return [
            'ok'                 => $isPdf,
            'reachable'          => true,
            'is_pdf'             => $isPdf,
            'confirm_needed'     => false,
            'size_bytes'         => $sz,
            'size_mb'            => $sizeMb,
            'page_count'         => $pages,
            'api_used'           => false,
            'safe_limit_warning' => $warn,
            'reason_code'        => $isPdf ? 'OK' : 'NOT_A_PDF',
            'message'            => "Reachable (Local Demo PDF) • Size: {$sizeMb} MB • Valid PDF: " . ($isPdf ? 'Yes' : 'No') . ($pages ? " • Pages: {$pages}" : ''),
        ];
    }

    if (!preg_match('~/file/d/([a-zA-Z0-9_-]{15,})|[?&]id=([a-zA-Z0-9_-]{15,})~', $valid, $m)) {
        return ['ok' => false, 'reachable' => false, 'reason_code' => 'INVALID_URL', 'message' => 'Could not extract Google Drive file ID.'];
    }
    $fileId = $m[1] !== '' ? $m[1] : $m[2];
    if (!function_exists('curl_init')) {
        return ['ok' => false, 'reachable' => false, 'reason_code' => 'CURL_MISSING', 'message' => 'PHP cURL extension unavailable.'];
    }

    $sizeBytes = null;
    $isPdf = false;
    $confirmNeeded = false;
    $pageCount = null;
    $apiUsed = false;
    $exactReasonCode = null;
    $exactReasonMsg = null;

    // 1. Optional Google Drive API v3 metadata + Range probe
    $apiKey = isset($testOverride['api_key']) ? trim((string) $testOverride['api_key']) : drive_api_key();
    if ($apiKey !== '') {
        $apiBase = rtrim((string) ($testOverride['api_base'] ?? 'https://www.googleapis.com/drive/v3/files'), '/');
        $metaUrl = $apiBase . '/' . rawurlencode($fileId) . '?fields=id,name,mimeType,size&supportsAllDrives=true&key=' . rawurlencode($apiKey);
        $chMeta = curl_init($metaUrl);
        curl_setopt_array($chMeta, [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 15, CURLOPT_SSL_VERIFYPEER => true]);
        $metaRaw = (string) curl_exec($chMeta);
        $metaCode = (int) curl_getinfo($chMeta, CURLINFO_HTTP_CODE);
        curl_close($chMeta);
        $metaJson = json_decode($metaRaw, true);

        if ($metaCode === 200 && is_array($metaJson) && empty($metaJson['error'])) {
            $apiUsed = true;
            if (!empty($metaJson['size'])) {
                $sizeBytes = (int) $metaJson['size'];
            }
            $mime = strtolower((string) ($metaJson['mimeType'] ?? ''));
            if ($mime !== '' && !str_contains($mime, 'pdf')) {
                return [
                    'ok' => false, 'reachable' => true, 'is_pdf' => false, 'confirm_needed' => false,
                    'size_bytes' => $sizeBytes, 'size_mb' => $sizeBytes ? round($sizeBytes / 1048576, 2) : null,
                    'page_count' => null, 'api_used' => true, 'safe_limit_warning' => null,
                    'reason_code' => 'NOT_A_PDF',
                    'message' => 'Not a PDF: Drive file MIME type is "' . ($metaJson['mimeType'] ?? 'unknown') . '".',
                ];
            }
            // Probe first 64 KB via API alt=media
            $mediaProbe = $apiBase . '/' . rawurlencode($fileId) . '?alt=media&supportsAllDrives=true&key=' . rawurlencode($apiKey);
            $chP = curl_init($mediaProbe);
            curl_setopt_array($chP, [
                CURLOPT_RETURNTRANSFER => true, CURLOPT_HEADER => true, CURLOPT_TIMEOUT => 15,
                CURLOPT_RANGE => '0-65535', CURLOPT_SSL_VERIFYPEER => true,
            ]);
            $rawP = (string) curl_exec($chP);
            $hSizeP = (int) curl_getinfo($chP, CURLINFO_HEADER_SIZE);
            $codeP = (int) curl_getinfo($chP, CURLINFO_HTTP_CODE);
            curl_close($chP);
            [$hdrsP, $bodyP] = parse_curl_headers_and_body($rawP, $hSizeP);
            if ($codeP >= 200 && $codeP < 300) {
                $isPdf = str_starts_with(ltrim($bodyP), '%PDF-');
                if ($sizeBytes === null && !empty($hdrsP['content-range']) && preg_match('#/(\d+)$#', $hdrsP['content-range'], $crm)) {
                    $sizeBytes = (int) $crm[1];
                } elseif ($sizeBytes === null && !empty($hdrsP['content-length'])) {
                    $sizeBytes = (int) $hdrsP['content-length'];
                }
            }
        } elseif (is_array($metaJson) && !empty($metaJson['error'])) {
            $errMsg = (string) ($metaJson['error']['message'] ?? 'Drive API rejected request');
            $errReason = strtolower((string) ($metaJson['error']['errors'][0]['reason'] ?? ''));
            if (str_contains($errReason, 'quota') || str_contains($errReason, 'limit') || $metaCode === 429) {
                $exactReasonCode = 'QUOTA_EXCEEDED';
                $exactReasonMsg = 'Google Drive API quota exceeded: ' . $errMsg;
            } elseif ($metaCode === 401 || $metaCode === 403 || str_contains($errReason, 'forbidden')) {
                $exactReasonCode = 'NOT_PUBLIC';
                $exactReasonMsg = 'Drive API access denied (check API key or file sharing): ' . $errMsg;
            } elseif ($metaCode === 404) {
                $exactReasonCode = 'NOT_FOUND';
                $exactReasonMsg = 'File not found via Drive API (HTTP 404).';
            }
        }
    }

    // 2. Fallback / Public usercontent probe if API didn't verify PDF yet
    if (!$isPdf) {
        $probeUrls = !empty($testOverride['probe_urls']) && is_array($testOverride['probe_urls'])
            ? $testOverride['probe_urls']
            : ['https://drive.usercontent.google.com/download?id=' . rawurlencode($fileId) . '&export=download&confirm=t'];

        $cookieFile = tempnam(sys_get_temp_dir(), 'bx_gp_');
        foreach ($probeUrls as $probeUrl) {
            $ch = curl_init($probeUrl);
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_HEADER         => true,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_MAXREDIRS      => 6,
                CURLOPT_TIMEOUT        => 18,
                CURLOPT_RANGE          => '0-65535',
                CURLOPT_COOKIEJAR      => $cookieFile ?: null,
                CURLOPT_COOKIEFILE     => $cookieFile ?: null,
                CURLOPT_USERAGENT      => 'Mozilla/5.0 Chrome/124.0.0.0 Safari/537.36',
                CURLOPT_SSL_VERIFYPEER => true,
            ]);
            $raw = curl_exec($ch);
            $hSize = (int) curl_getinfo($ch, CURLINFO_HEADER_SIZE);
            $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $cErr = curl_error($ch);
            curl_close($ch);

            if ($raw === false) {
                $exactReasonCode = 'TIMEOUT';
                $exactReasonMsg = 'Unreachable: connection timed out (' . $cErr . ').';
                continue;
            }

            [$hdrs, $chunk] = parse_curl_headers_and_body((string) $raw, $hSize);
            $ctype = strtolower((string) ($hdrs['content-type'] ?? ''));

            if (!empty($hdrs['content-range']) && preg_match('#/(\d+)$#', $hdrs['content-range'], $crm)) {
                $sizeBytes = (int) $crm[1];
            } elseif ($sizeBytes === null && !empty($hdrs['content-length']) && str_contains($ctype, 'pdf')) {
                $sizeBytes = (int) $hdrs['content-length'];
            }

            if ($code >= 200 && $code < 300 && str_starts_with(ltrim($chunk), '%PDF-')) {
                $isPdf = true;
                break;
            }

            $lowerChunk = strtolower($chunk);
            if ($code === 429 || str_contains($lowerChunk, 'quota exceeded') || str_contains($lowerChunk, 'too many users')) {
                $exactReasonCode = 'QUOTA_EXCEEDED';
                $exactReasonMsg = 'Quota exceeded on Google Drive (HTTP ' . $code . ').';
                break;
            }
            if ($code === 401 || $code === 403 || str_contains($lowerChunk, 'you need access') || str_contains($lowerChunk, 'accounts.google.com')) {
                $exactReasonCode = 'NOT_PUBLIC';
                $exactReasonMsg = 'Not shared publicly (HTTP ' . $code . '). Set Drive sharing to "Anyone with the link".';
                break;
            }
            if ($code === 404) {
                $exactReasonCode = 'NOT_FOUND';
                $exactReasonMsg = 'File not found (HTTP 404).';
                break;
            }

            // Detect large-file confirmation HTML form and follow it
            if (str_contains($ctype, 'text/html') && (str_contains($lowerChunk, 'confirm=') || str_contains($lowerChunk, 'virus') || str_contains($lowerChunk, '<form'))) {
                $confirmNeeded = true;
                if ($sizeBytes === null && preg_match('/\(([\d.]+)\s*([KMG])\)/i', $chunk, $sm)) {
                    $mult = match (strtoupper($sm[2])) { 'G' => 1073741824, 'M' => 1048576, default => 1024 };
                    $sizeBytes = (int) round((float) $sm[1] * $mult);
                }
                $confirmUrl = null;
                if (preg_match('/<form[^>]+action=["\']([^"\']+)["\'][^>]*>(.*?)<\/form>/is', $chunk, $fm)) {
                    $act = html_entity_decode($fm[1], ENT_QUOTES);
                    if (str_starts_with($act, '/')) {
                        $pu = parse_url($probeUrl);
                        $act = ($pu['scheme'] ?? 'https') . '://' . ($pu['host'] ?? 'drive.usercontent.google.com') . (!empty($pu['port']) ? ':' . $pu['port'] : '') . $act;
                    }
                    $params = [];
                    if (preg_match_all('/<input[^>]+name=["\']([^"\']+)["\'][^>]+value=["\']([^"\']*)["\']/is', $fm[2], $inps, PREG_SET_ORDER)) {
                        foreach ($inps as $inp) {
                            $params[$inp[1]] = html_entity_decode($inp[2], ENT_QUOTES);
                        }
                    }
                    if (!empty($params)) {
                        $confirmUrl = $act . (str_contains($act, '?') ? '&' : '?') . http_build_query($params);
                    }
                }
                if ($confirmUrl !== null) {
                    $ch2 = curl_init($confirmUrl);
                    curl_setopt_array($ch2, [
                        CURLOPT_RETURNTRANSFER => true, CURLOPT_HEADER => true, CURLOPT_FOLLOWLOCATION => true,
                        CURLOPT_RANGE => '0-65535', CURLOPT_TIMEOUT => 18,
                        CURLOPT_COOKIEJAR => $cookieFile ?: null, CURLOPT_COOKIEFILE => $cookieFile ?: null,
                        CURLOPT_SSL_VERIFYPEER => true,
                    ]);
                    $raw2 = (string) curl_exec($ch2);
                    $hSize2 = (int) curl_getinfo($ch2, CURLINFO_HEADER_SIZE);
                    $code2 = (int) curl_getinfo($ch2, CURLINFO_HTTP_CODE);
                    curl_close($ch2);
                    [$hdrs2, $body2] = parse_curl_headers_and_body($raw2, $hSize2);
                    if (!empty($hdrs2['content-range']) && preg_match('#/(\d+)$#', $hdrs2['content-range'], $crm2)) {
                        $sizeBytes = (int) $crm2[1];
                    }
                    if ($code2 >= 200 && $code2 < 300 && str_starts_with(ltrim($body2), '%PDF-')) {
                        $isPdf = true;
                        break;
                    }
                }
            } else {
                $exactReasonCode = 'NOT_A_PDF';
                $exactReasonMsg = 'Not a valid PDF (HTTP ' . $code . ', MIME ' . ($ctype ?: 'unknown') . ').';
            }
        }
        if ($cookieFile && file_exists($cookieFile)) {
            @unlink($cookieFile);
        }
    }

    // Check cached file for exact page count if already cached locally
    $cachePath = APP_ROOT . '/storage/cache/' . hash_hmac('sha256', $fileId, (string) app_config('security.app_secret', 'bookexa_cache')) . '.bin';
    if ($isPdf && is_file($cachePath)) {
        $pageCount = count_pdf_pages_fast_file($cachePath);
        if ($sizeBytes === null) {
            $sizeBytes = (int) filesize($cachePath);
        }
    }

    [$sizeMb, $warn, $tooLarge] = $evaluateServerWarning($sizeBytes);

    if (!$isPdf) {
        return [
            'ok' => false, 'reachable' => ($exactReasonCode === 'NOT_A_PDF'), 'is_pdf' => false,
            'confirm_needed' => $confirmNeeded, 'size_bytes' => $sizeBytes, 'size_mb' => $sizeMb,
            'page_count' => null, 'api_used' => $apiUsed, 'safe_limit_warning' => $warn,
            'reason_code' => $exactReasonCode ?? 'UNREACHABLE',
            'message' => $exactReasonMsg ?? 'Unreachable from Drive. Ensure link is shared as "Anyone with the link".',
        ];
    }

    if ($tooLarge) {
        return [
            'ok' => false, 'reachable' => true, 'is_pdf' => true,
            'confirm_needed' => $confirmNeeded, 'size_bytes' => $sizeBytes, 'size_mb' => $sizeMb,
            'page_count' => $pageCount, 'api_used' => $apiUsed, 'safe_limit_warning' => $warn,
            'reason_code' => 'TOO_LARGE_FOR_SERVER',
            'message' => "Too large for this server ({$sizeMb} MB). {$warn}",
        ];
    }

    $parts = ['Reachable' . ($apiUsed ? ' (Official Drive API v3)' : ' (Drive Web Stream)')];
    $parts[] = 'Size: ' . ($sizeMb !== null ? "{$sizeMb} MB" : 'Unknown');
    $parts[] = 'Valid PDF: Yes';
    $parts[] = 'Large-file confirm step: ' . ($confirmNeeded ? 'Needed (handled)' : 'No');
    $parts[] = 'Pages: ' . ($pageCount !== null ? (string) $pageCount : 'Detect after first prepare/stream');
    if ($warn !== null) {
        $parts[] = 'Warning: ' . $warn;
    }

    return [
        'ok'                 => true,
        'reachable'          => true,
        'is_pdf'             => true,
        'confirm_needed'     => $confirmNeeded,
        'size_bytes'         => $sizeBytes,
        'size_mb'            => $sizeMb,
        'page_count'         => $pageCount,
        'api_used'           => $apiUsed,
        'safe_limit_warning' => $warn,
        'reason_code'        => 'OK',
        'message'            => implode(' | ', $parts),
    ];
}

function save_cropped_base64_image(string $dataUri, string $subDir): ?string
{
    $subDir = trim($subDir, '/');
    if (!in_array($subDir, media_allowed_subdirs(), true)) {
        throw new RuntimeException('Disallowed upload subfolder.');
    }
    if (!preg_match('/^data:image\/(jpeg|png|webp);base64,(.+)$/', $dataUri, $m)) {
        return null;
    }
    $raw = base64_decode($m[2], true);
    if ($raw === false || strlen($raw) > 4 * 1024 * 1024) {
        throw new RuntimeException('Cropped image is invalid or exceeds 4 MB.');
    }
    $ext = $m[1] === 'jpeg' ? 'jpg' : $m[1];
    $relDir = 'uploads/' . $subDir;
    $absDir = APP_ROOT . '/' . $relDir;
    if (!is_dir($absDir)) {
        mkdir($absDir, 0755, true);
    }
    $filename = date('Ymd_His') . '_' . bin2hex(random_bytes(8)) . '.' . $ext;
    $relPath = $relDir . '/' . $filename;
    file_put_contents(APP_ROOT . '/' . $relPath, $raw);
    media_sync_push_one($relPath);
    return $relPath;
}

function safe_admin_upload_file_item(array $file, string $subDir, int $maxWidth = 1000, bool $allowIco = false): ?string
{
    $subDir = trim($subDir, '/');
    if (!in_array($subDir, media_allowed_subdirs(), true)) {
        throw new RuntimeException('Disallowed upload subfolder.');
    }
    if ((int) ($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
        return null;
    }
    if ((int) $file['error'] !== UPLOAD_ERR_OK) {
        throw new RuntimeException('Upload failed (code ' . $file['error'] . ').');
    }
    if ((int) $file['size'] > 4 * 1024 * 1024) {
        throw new RuntimeException('Image exceeds maximum 4 MB limit.');
    }
    $origExt = strtolower((string) pathinfo((string) ($file['name'] ?? ''), PATHINFO_EXTENSION));
    if ($origExt === 'svg' || $origExt === 'svgz') {
        throw new RuntimeException('SVG uploads are disabled for security. Allowed formats: JPG, PNG, WebP.');
    }
    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime = $finfo->file((string) $file['tmp_name']);
    $allowed = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
    if ($allowIco && ($mime === 'image/x-icon' || $mime === 'image/vnd.microsoft.icon')) {
        $allowed[$mime] = 'ico';
    }
    if (!isset($allowed[$mime])) {
        throw new RuntimeException('Invalid file type. Allowed raster formats: JPG, PNG, WebP' . ($allowIco ? ', ICO' : '') . ' (SVG blocked).');
    }
    $ext = $allowed[$mime];
    $relDir = 'uploads/' . $subDir;
    $absDir = APP_ROOT . '/' . $relDir;
    if (!is_dir($absDir)) {
        mkdir($absDir, 0755, true);
    }
    $filename = date('Ymd_His') . '_' . bin2hex(random_bytes(8)) . '.' . $ext;
    $relPath = $relDir . '/' . $filename;
    $destAbs = APP_ROOT . '/' . $relPath;

    if ($ext !== 'ico' && extension_loaded('gd')) {
        $info = @getimagesize((string) $file['tmp_name']);
        if ($info && $info[0] > $maxWidth) {
            $dstW = $maxWidth;
            $dstH = max(1, (int) round($info[1] * ($dstW / $info[0])));
            $srcImg = match ($mime) {
                'image/jpeg' => @imagecreatefromjpeg((string) $file['tmp_name']),
                'image/png'  => @imagecreatefrompng((string) $file['tmp_name']),
                'image/webp' => function_exists('imagecreatefromwebp') ? @imagecreatefromwebp((string) $file['tmp_name']) : false,
                default      => false,
            };
            if ($srcImg) {
                $dstImg = imagecreatetruecolor($dstW, $dstH);
                if ($mime === 'image/png' || $mime === 'image/webp') {
                    imagealphablending($dstImg, false);
                    imagesavealpha($dstImg, true);
                }
                imagecopyresampled($dstImg, $srcImg, 0, 0, 0, 0, $dstW, $dstH, $info[0], $info[1]);
                $saved = match ($mime) {
                    'image/jpeg' => imagejpeg($dstImg, $destAbs, 86),
                    'image/png'  => imagepng($dstImg, $destAbs, 6),
                    'image/webp' => function_exists('imagewebp') ? imagewebp($dstImg, $destAbs, 85) : false,
                    default      => false,
                };
                imagedestroy($srcImg);
                imagedestroy($dstImg);
                if ($saved) {
                    media_sync_push_one($relPath);
                    if (function_exists('perf_optimize_image_variants')) {
                        perf_optimize_image_variants($relPath, true);
                    }
                    return $relPath;
                }
            }
        }
    }

    if (!move_uploaded_file((string) $file['tmp_name'], $destAbs)) {
        throw new RuntimeException('Failed to save uploaded image.');
    }
    media_sync_push_one($relPath);
    if ($ext !== 'ico' && function_exists('perf_optimize_image_variants')) {
        perf_optimize_image_variants($relPath, true);
    }
    return $relPath;
}

function safe_admin_image_upload(string $inputName, string $subDir, int $maxWidth = 1000, bool $allowIco = false): ?string
{
    if (empty($_FILES[$inputName]) || $_FILES[$inputName]['error'] === UPLOAD_ERR_NO_FILE) {
        return null;
    }
    return safe_admin_upload_file_item($_FILES[$inputName], $subDir, $maxWidth, $allowIco);
}

function safe_admin_multi_image_upload(string $inputName, string $subDir, int $maxWidth = 1000): array
{
    if (empty($_FILES[$inputName]) || !is_array($_FILES[$inputName]['name'] ?? null)) {
        return [];
    }
    $uploaded = [];
    $f = $_FILES[$inputName];
    $count = count($f['name']);
    for ($i = 0; $i < $count; $i++) {
        if ((int) ($f['error'][$i] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
            continue;
        }
        $item = [
            'name'     => $f['name'][$i],
            'type'     => $f['type'][$i] ?? '',
            'tmp_name' => $f['tmp_name'][$i],
            'error'    => $f['error'][$i],
            'size'     => $f['size'][$i] ?? 0,
        ];
        $path = safe_admin_upload_file_item($item, $subDir, $maxWidth, false);
        if ($path !== null) {
            $uploaded[] = $path;
        }
    }
    return $uploaded;
}

function is_folder_descendant(int $candidateParentId, int $folderId): bool
{
    $curr = $candidateParentId;
    $guard = 0;
    while ($curr !== null && $guard++ < 60) {
        if ($curr === $folderId) {
            return true;
        }
        $row = db_one('SELECT parent_id FROM folders WHERE id = ?', [$curr]);
        if (!$row || $row['parent_id'] === null) {
            return false;
        }
        $curr = (int) $row['parent_id'];
    }
    return false;
}

function folder_breadcrumb_string(int $folderId): string
{
    $trail = [];
    $seen = [];
    $curr = $folderId;
    while ($curr !== null && !isset($seen[$curr])) {
        $seen[$curr] = true;
        $row = db_one('SELECT parent_id, name_en FROM folders WHERE id = ?', [$curr]);
        if (!$row) {
            break;
        }
        array_unshift($trail, $row['name_en']);
        $curr = $row['parent_id'] !== null ? (int) $row['parent_id'] : null;
    }
    return implode(' › ', $trail);
}

function build_folder_options(?int $parentId = null, int $depth = 0, ?int $excludeId = null): array
{
    $sql = $parentId === null
        ? 'SELECT id, name_bn, name_en FROM folders WHERE parent_id IS NULL ORDER BY sort_order ASC, id ASC'
        : 'SELECT id, name_bn, name_en FROM folders WHERE parent_id = ? ORDER BY sort_order ASC, id ASC';
    $rows = $parentId === null ? db_all($sql) : db_all($sql, [$parentId]);
    $out = [];
    foreach ($rows as $r) {
        if ($excludeId !== null && (int) $r['id'] === $excludeId) {
            continue;
        }
        $prefix = str_repeat('— ', $depth);
        $out[] = [
            'id'    => (int) $r['id'],
            'label' => $prefix . $r['name_en'] . ' (' . $r['name_bn'] . ')',
            'depth' => $depth,
        ];
        $out = array_merge($out, build_folder_options((int) $r['id'], $depth + 1, $excludeId));
    }
    return $out;
}

function store_uploaded_bytes_as_cover(string $rawBytes): string
{
    $info = @getimagesizefromstring($rawBytes);
    if (!$info || !in_array($info[2], [IMAGETYPE_JPEG, IMAGETYPE_PNG, IMAGETYPE_WEBP], true)) {
        throw new RuntimeException('Invalid cover image payload.');
    }
    $ext = $info[2] === IMAGETYPE_WEBP ? 'webp' : ($info[2] === IMAGETYPE_PNG ? 'png' : 'jpg');
    $relDir = 'uploads/covers';
    $absDir = APP_ROOT . '/' . $relDir;
    if (!is_dir($absDir)) {
        @mkdir($absDir, 0755, true);
    }
    $relPath = $relDir . '/auto_' . date('Ymd_His') . '_' . bin2hex(random_bytes(6)) . '.' . $ext;
    $destAbs = APP_ROOT . '/' . $relPath;
    if (@file_put_contents($destAbs, $rawBytes) === false) {
        throw new RuntimeException('Failed to write cover file.');
    }
    media_sync_push_one($relPath);
    return $relPath;
}

function admin_find_or_create_folder_path(string $folderPath, bool $autoCreate): array
{
    $segments = array_values(array_filter(array_map('trim', explode('/', str_replace('\\', '/', $folderPath))), static fn(string $s): bool => $s !== ''));
    if (!$segments) {
        return ['ok' => true, 'folder_id' => null, 'created' => false];
    }
    $parentId = null;
    $missing = false;
    $createdAny = false;
    foreach ($segments as $seg) {
        $segLow = mb_strtolower($seg);
        $segSlug = slugify($seg);
        if ($parentId === null) {
            $row = db_one(
                'SELECT id FROM folders WHERE parent_id IS NULL AND (LOWER(name_en) = ? OR LOWER(name_bn) = ? OR slug = ?) LIMIT 1',
                [$segLow, $segLow, $segSlug]
            );
        } else {
            $row = db_one(
                'SELECT id FROM folders WHERE parent_id = ? AND (LOWER(name_en) = ? OR LOWER(name_bn) = ? OR slug = ?) LIMIT 1',
                [$parentId, $segLow, $segLow, $segSlug]
            );
        }
        if ($row) {
            $parentId = (int) $row['id'];
        } else {
            if (!$autoCreate) {
                $missing = true;
                break;
            }
            $newSlug = generate_unique_folder_slug($seg, $parentId);
            $parentId = (int) db_insert(
                'INSERT INTO folders (parent_id, slug, name_bn, name_en, is_active, sort_order) VALUES (?, ?, ?, ?, 1, 0)',
                [$parentId, $newSlug, $seg, $seg]
            );
            $createdAny = true;
        }
    }
    return ['ok' => !$missing, 'folder_id' => $missing ? null : $parentId, 'created' => $createdAny];
}

function admin_parse_csv_preview(string $tmpFile, bool $autoCreateFolders): array
{
    $fh = @fopen($tmpFile, 'rb');
    if (!$fh) {
        throw new RuntimeException('Cannot open uploaded CSV file.');
    }
    $rawHeader = fgetcsv($fh);
    if (!is_array($rawHeader) || !$rawHeader) {
        fclose($fh);
        throw new RuntimeException('CSV file appears empty or missing header row.');
    }
    $rawHeader[0] = preg_replace('/^\xEF\xBB\xBF/', '', (string) $rawHeader[0]);
    $headers = array_map(static fn($h): string => strtolower(trim((string) $h)), $rawHeader);

    $rows = [];
    $seenSlugs = [];
    $summary = ['total' => 0, 'valid' => 0, 'errors' => 0, 'new_folders' => 0];
    $lineNo = 1;
    while (($csvRow = fgetcsv($fh)) !== false && $lineNo <= 1000) {
        $lineNo++;
        if (count(array_filter($csvRow, static fn($v): bool => trim((string) $v) !== '')) === 0) {
            continue;
        }
        $assoc = [];
        foreach ($headers as $idx => $key) {
            if ($key !== '') {
                $assoc[$key] = trim((string) ($csvRow[$idx] ?? ''));
            }
        }
        $folderPath  = (string) ($assoc['folder_path'] ?? '');
        $titleBn     = (string) ($assoc['title_bn'] ?? '');
        $titleEn     = (string) ($assoc['title_en'] ?? '');
        $authorEn    = (string) ($assoc['author_en'] ?? ($assoc['author'] ?? ''));
        $authorBn    = (string) ($assoc['author_bn'] ?? $authorEn);
        $descEn      = (string) ($assoc['description_en'] ?? ($assoc['description'] ?? ''));
        $descBn      = (string) ($assoc['description_bn'] ?? $descEn);
        $price       = max(0.0, (float) ($assoc['price'] ?? 0));
        $origPrice   = trim((string) ($assoc['original_price'] ?? '')) !== '' ? max(0.0, (float) $assoc['original_price']) : null;
        $isFreeRaw   = strtolower((string) ($assoc['is_free'] ?? '0'));
        $isFree      = in_array($isFreeRaw, ['1', 'true', 'yes', 'free'], true) || $price <= 0;
        $samplePages = max(0, (int) ($assoc['sample_pages'] ?? 4));
        $driveLink   = (string) ($assoc['drive_link'] ?? '');
        $slugRaw     = (string) ($assoc['slug'] ?? '');
        $slug        = slugify($slugRaw !== '' ? $slugRaw : ($titleEn !== '' ? $titleEn : $titleBn));

        $errors = [];
        $folderNote = '';
        if ($titleEn === '' && $titleBn === '') {
            $errors[] = 'Missing title';
        }
        if ($titleBn === '' && $titleEn !== '') {
            $titleBn = $titleEn;
        }
        if ($titleEn === '' && $titleBn !== '') {
            $titleEn = $titleBn;
        }
        if ($driveLink !== '' && validate_drive_url($driveLink, true) === null) {
            $errors[] = 'Invalid Google Drive link';
        }
        if (isset($seenSlugs[$slug]) || db_one('SELECT id FROM items WHERE slug = ? LIMIT 1', [$slug])) {
            $errors[] = 'Duplicate slug: ' . $slug;
        }
        $seenSlugs[$slug] = true;

        $fLookup = $folderPath !== '' ? admin_check_folder_path_exists($folderPath) : ['exists' => true];
        if (!$fLookup['exists']) {
            if ($autoCreateFolders) {
                $folderNote = 'Missing folder — will auto-create';
                $summary['new_folders']++;
            } else {
                $errors[] = 'Missing folder: ' . $folderPath;
                $summary['new_folders']++;
            }
        }

        $summary['total']++;
        if ($errors) {
            $summary['errors']++;
        } else {
            $summary['valid']++;
        }
        $rows[] = [
            'line'           => $lineNo,
            'folder_path'    => $folderPath,
            'folder_note'    => $folderNote,
            'title_bn'       => $titleBn,
            'title_en'       => $titleEn,
            'author_bn'      => $authorBn,
            'author_en'      => $authorEn,
            'description_bn' => $descBn,
            'description_en' => $descEn,
            'price'          => $isFree ? 0.0 : $price,
            'original_price' => $origPrice,
            'is_free'        => $isFree ? 1 : 0,
            'sample_pages'   => $samplePages,
            'drive_link'     => $driveLink,
            'slug'           => $slug,
            'errors'         => $errors,
        ];
    }
    fclose($fh);
    return ['rows' => $rows, 'summary' => $summary];
}

function admin_check_folder_path_exists(string $folderPath): array
{
    $segments = array_values(array_filter(array_map('trim', explode('/', str_replace('\\', '/', $folderPath))), static fn(string $s): bool => $s !== ''));
    $parentId = null;
    foreach ($segments as $seg) {
        $segLow = mb_strtolower($seg);
        $segSlug = slugify($seg);
        $row = $parentId === null
            ? db_one('SELECT id FROM folders WHERE parent_id IS NULL AND (LOWER(name_en) = ? OR LOWER(name_bn) = ? OR slug = ?) LIMIT 1', [$segLow, $segLow, $segSlug])
            : db_one('SELECT id FROM folders WHERE parent_id = ? AND (LOWER(name_en) = ? OR LOWER(name_bn) = ? OR slug = ?) LIMIT 1', [$parentId, $segLow, $segLow, $segSlug]);
        if (!$row) {
            return ['exists' => false, 'folder_id' => null];
        }
        $parentId = (int) $row['id'];
    }
    return ['exists' => true, 'folder_id' => $parentId];
}
