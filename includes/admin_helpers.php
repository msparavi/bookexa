<?php
declare(strict_types=1);

const ADMIN_SESSION_TIMEOUT    = 1800; // 30 minutes
const ADMIN_LOGIN_MAX_ATTEMPTS = 5;
const ADMIN_LOGIN_WINDOW_SEC   = 600;

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

    $lastActivity = (int) ($_SESSION['admin_last_activity'] ?? 0);
    if ($lastActivity > 0 && (time() - $lastActivity) > ADMIN_SESSION_TIMEOUT) {
        unset($_SESSION['admin_id'], $_SESSION['admin_last_activity']);
        session_regenerate_id(true);
        flash('warning', current_lang() === 'bn' ? 'সেশন মেয়াদ শেষ হয়েছে। আবার লগইন করুন।' : 'Session timed out due to inactivity. Please log in again.');
        redirect('/admin/login?timeout=1');
    }

    $admin = db_one('SELECT id, name, email, role, is_active, must_change_password, password_hash FROM admins WHERE id = ? LIMIT 1', [(int) $aid]);
    if (!$admin || !(int) $admin['is_active']) {
        unset($_SESSION['admin_id'], $_SESSION['admin_last_activity']);
        redirect('/admin/login');
    }
    if (app_config('app_env') === 'production' && password_verify('Admin@1234', (string) $admin['password_hash'])) {
        $admin['must_change_password'] = 1;
    }
    unset($admin['password_hash']);

    $_SESSION['admin_last_activity'] = time();
    return $admin;
}

function attempt_admin_login(string $email, string $password): array
{
    $ipKey = 'admin_login:ip:' . client_ip();
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
    db_exec('UPDATE admins SET last_login_at = NOW() WHERE id = ?', [(int) $admin['id']]);

    session_regenerate_id(true);
    $_SESSION['admin_id'] = (int) $admin['id'];
    $_SESSION['admin_last_activity'] = time();

    log_admin_action($admin, 'admin_login', 'admin', (int) $admin['id'], 'Signed into dashboard');
    return ['ok' => true, 'admin' => $admin];
}

function admin_logout(): void
{
    unset($_SESSION['admin_id'], $_SESSION['admin_last_activity']);
    session_regenerate_id(true);
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
 * Checks whether a Drive or local demo PDF link is reachable without exposing raw URLs.
 */
function test_drive_reachability(string $url): array
{
    $valid = validate_drive_url($url, true);
    if ($valid === null) {
        return ['ok' => false, 'message' => 'Invalid Google Drive URL format.'];
    }
    if (str_starts_with($valid, 'local://')) {
        $f = APP_ROOT . '/storage/demo/' . basename(substr($valid, 8));
        return is_file($f)
            ? ['ok' => true, 'message' => 'Reachable (Demo PDF: ' . number_format(filesize($f) / 1024, 1) . ' KB)']
            : ['ok' => false, 'message' => 'Demo file not found in protected storage.'];
    }

    if (!preg_match('~/file/d/([a-zA-Z0-9_-]{15,})|[?&]id=([a-zA-Z0-9_-]{15,})~', $valid, $m)) {
        return ['ok' => false, 'message' => 'Could not extract file identifier.'];
    }
    $fileId = $m[1] !== '' ? $m[1] : $m[2];
    if (!function_exists('curl_init')) {
        return ['ok' => false, 'message' => 'PHP cURL extension unavailable.'];
    }

    $probeUrl = 'https://drive.usercontent.google.com/download?id=' . rawurlencode($fileId) . '&export=download&confirm=t';
    $ch = curl_init($probeUrl);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_MAXREDIRS      => 5,
        CURLOPT_TIMEOUT        => 15,
        CURLOPT_RANGE          => '0-1023',
        CURLOPT_USERAGENT      => 'Mozilla/5.0 Chrome/124.0.0.0 Safari/537.36',
    ]);
    $chunk = (string) curl_exec($ch);
    $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($code >= 200 && $code < 400 && (str_starts_with(ltrim($chunk), '%PDF-') || str_contains(strtolower($chunk), 'confirm'))) {
        return ['ok' => true, 'message' => 'Reachable! File is accessible for server streaming.'];
    }
    return ['ok' => false, 'message' => 'Unreachable (HTTP ' . $code . '). Ensure Drive sharing is set to "Anyone with the link".'];
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

function safe_admin_image_upload(string $inputName, string $subDir, int $maxWidth = 1000, bool $allowSvgIcon = false): ?string
{
    $subDir = trim($subDir, '/');
    if (!in_array($subDir, media_allowed_subdirs(), true)) {
        throw new RuntimeException('Disallowed upload subfolder.');
    }
    if (empty($_FILES[$inputName]) || $_FILES[$inputName]['error'] === UPLOAD_ERR_NO_FILE) {
        return null;
    }
    $file = $_FILES[$inputName];
    if ($file['error'] !== UPLOAD_ERR_OK) {
        throw new RuntimeException('Upload failed (code ' . $file['error'] . ').');
    }
    if ($file['size'] > 4 * 1024 * 1024) {
        throw new RuntimeException('Image exceeds maximum 4 MB limit.');
    }
    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime = $finfo->file($file['tmp_name']);
    $allowed = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
    if ($allowSvgIcon && ($mime === 'image/x-icon' || $mime === 'image/vnd.microsoft.icon')) {
        $allowed[$mime] = 'ico';
    }
    if (!isset($allowed[$mime])) {
        throw new RuntimeException('Invalid file type. Allowed: JPG, PNG, WebP.');
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
        $info = @getimagesize($file['tmp_name']);
        if ($info && $info[0] > $maxWidth) {
            $dstW = $maxWidth;
            $dstH = max(1, (int) round($info[1] * ($dstW / $info[0])));
            $srcImg = match ($mime) {
                'image/jpeg' => @imagecreatefromjpeg($file['tmp_name']),
                'image/png'  => @imagecreatefrompng($file['tmp_name']),
                'image/webp' => function_exists('imagecreatefromwebp') ? @imagecreatefromwebp($file['tmp_name']) : false,
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
                    return $relPath;
                }
            }
        }
    }

    if (!move_uploaded_file($file['tmp_name'], $destAbs)) {
        throw new RuntimeException('Failed to save uploaded image.');
    }
    media_sync_push_one($relPath);
    return $relPath;
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
