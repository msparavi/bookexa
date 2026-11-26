<?php
declare(strict_types=1);

function current_user(bool $reset = false): ?array
{
    static $cached = false;
    if ($reset) {
        $cached = false;
    }
    if ($cached !== false) {
        return $cached;
    }

    $uid = $_SESSION['user_id'] ?? null;
    if (!$uid) {
        $cached = null;
        return null;
    }

    $user = db_one(
        'SELECT id, name, email, phone, is_active, active_session_token, reader_theme, reader_zoom, reading_suspended_until FROM users WHERE id = ? LIMIT 1',
        [(int) $uid]
    );
    if (!$user || !(int) $user['is_active']) {
        unset($_SESSION['user_id'], $_SESSION['active_session_token'], $_SESSION['device_token']);
        $cached = null;
        return null;
    }

    $devTok = (string) ($_SESSION['device_token'] ?? '');
    if ($devTok !== '') {
        $dev = db_one('SELECT id FROM user_devices WHERE user_id = ? AND device_token = ? LIMIT 1', [(int) $uid, $devTok]);
        if (!$dev) {
            unset($_SESSION['user_id'], $_SESSION['active_session_token'], $_SESSION['device_token']);
            $cached = null;
            return null;
        }
        $lastPing = (int) ($_SESSION['device_ping_at'] ?? 0);
        if ((time() - $lastPing) >= 60) {
            db_exec('UPDATE user_devices SET last_active_at = NOW(), ip_address = ? WHERE id = ?', [client_ip(), (int) $dev['id']]);
            $_SESSION['device_ping_at'] = time();
        }
    }

    $cached = $user;
    return $cached;
}

function current_admin(): ?array
{
    $aid = $_SESSION['admin_id'] ?? null;
    if (!$aid) {
        return null;
    }
    $admin = db_one('SELECT id, name, email, role, is_active FROM admins WHERE id = ? LIMIT 1', [(int) $aid]);
    if (!$admin || !(int) $admin['is_active']) {
        unset($_SESSION['admin_id']);
        return null;
    }
    return $admin;
}

function is_logged_in(): bool
{
    return current_user() !== null;
}

function require_login(?string $next = null): array
{
    $u = current_user();
    if ($u === null) {
        $target = '/login' . ($next ? '?next=' . rawurlencode($next) : '');
        flash('warning', current_lang() === 'bn' ? 'অনুগ্রহ করে আগে লগইন করুন।' : 'Please log in first to continue.');
        redirect($target);
    }
    return $u;
}

function user_rate_limit_check(string $key, int $maxHits, int $windowSeconds): bool
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

function bookexa_device_fingerprint(bool $createIfMissing = true): string
{
    $raw = (string) ($_COOKIE['bx_did'] ?? '');
    if (preg_match('/^[a-f0-9]{16,64}$/', $raw)) {
        return $raw;
    }
    if (!$createIfMissing) {
        return '';
    }
    $fp = bin2hex(random_bytes(16));
    $_COOKIE['bx_did'] = $fp;
    if (PHP_SAPI !== 'cli' && !headers_sent()) {
        $isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
            || (($_SERVER['SERVER_PORT'] ?? '') === '443')
            || (strtolower((string) ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '')) === 'https');
        setcookie('bx_did', $fp, [
            'expires'  => time() + (365 * 86400),
            'path'     => BASE_PATH !== '' ? BASE_PATH . '/' : '/',
            'secure'   => $isHttps,
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
    }
    return $fp;
}

function bookexa_device_label(): string
{
    $ua = (string) ($_SERVER['HTTP_USER_AGENT'] ?? '');
    if ($ua === '') {
        return PHP_SAPI === 'cli' ? 'CLI Session' : 'Web Browser';
    }
    $browser = 'Browser';
    if (stripos($ua, 'Edg') !== false) {
        $browser = 'Edge';
    } elseif (stripos($ua, 'OPR') !== false || stripos($ua, 'Opera') !== false) {
        $browser = 'Opera';
    } elseif (stripos($ua, 'Firefox') !== false) {
        $browser = 'Firefox';
    } elseif (stripos($ua, 'Chrome') !== false) {
        $browser = 'Chrome';
    } elseif (stripos($ua, 'Safari') !== false) {
        $browser = 'Safari';
    }
    $os = 'Desktop';
    if (stripos($ua, 'Android') !== false) {
        $os = 'Android';
    } elseif (stripos($ua, 'iPhone') !== false || stripos($ua, 'iPad') !== false) {
        $os = 'iOS';
    } elseif (stripos($ua, 'Windows') !== false) {
        $os = 'Windows';
    } elseif (stripos($ua, 'Mac OS') !== false || stripos($ua, 'Macintosh') !== false) {
        $os = 'macOS';
    } elseif (stripos($ua, 'Linux') !== false) {
        $os = 'Linux';
    }
    return $browser . ' · ' . $os;
}

function list_user_devices(int $userId): array
{
    return db_all('SELECT * FROM user_devices WHERE user_id = ? ORDER BY last_active_at DESC, id DESC', [$userId]);
}

function revoke_user_device(int $userId, int $deviceId): bool
{
    $row = db_one('SELECT id, device_token FROM user_devices WHERE id = ? AND user_id = ? LIMIT 1', [$deviceId, $userId]);
    if (!$row) {
        return false;
    }
    db_exec('DELETE FROM user_devices WHERE id = ? AND user_id = ?', [$deviceId, $userId]);
    if (!empty($_SESSION['device_token']) && hash_equals((string) $_SESSION['device_token'], (string) $row['device_token'])) {
        unset($_SESSION['user_id'], $_SESSION['active_session_token'], $_SESSION['device_token'], $_SESSION['device_ping_at']);
    }
    return true;
}

function check_device_limit_or_prepare(array $user): ?array
{
    $uid = (int) $user['id'];
    $maxDevices = max(1, (int) site_setting('max_devices_per_account', '2'));
    $fp = bookexa_device_fingerprint(false);
    $devices = list_user_devices($uid);
    if ($fp !== '') {
        foreach ($devices as $d) {
            if ((string) $d['device_fingerprint'] === $fp) {
                return null;
            }
        }
    }
    if (count($devices) < $maxDevices) {
        return null;
    }
    return [
        'ok'      => false,
        'code'    => 'DEVICE_LIMIT',
        'user'    => $user,
        'devices' => $devices,
        'max'     => $maxDevices,
        'error'   => current_lang() === 'bn'
            ? "প্রতি অ্যাকাউন্টে সর্বোচ্চ {$maxDevices}টি ডিভাইস লগইন অনুমোদিত। চালিয়ে যেতে একটি পুরোনো ডিভাইস সাইন আউট করুন।"
            : "Maximum {$maxDevices} active devices allowed per account. Sign out an existing device to log in here.",
    ];
}

function login_user_session(array $user, ?int $replaceDeviceId = null): void
{
    if (session_status() === PHP_SESSION_ACTIVE && PHP_SAPI !== 'cli') {
        session_regenerate_id(true);
    }
    $uid = (int) $user['id'];
    $token = bin2hex(random_bytes(32));
    db_exec('UPDATE users SET active_session_token = ?, active_session_updated_at = NOW() WHERE id = ?', [$token, $uid]);

    if ($replaceDeviceId !== null && $replaceDeviceId > 0) {
        db_exec('DELETE FROM user_devices WHERE id = ? AND user_id = ?', [$replaceDeviceId, $uid]);
    }

    $fp = bookexa_device_fingerprint(true);
    $devToken = bin2hex(random_bytes(32));
    $label = bookexa_device_label();
    $ua = substr((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 250);
    $existing = db_one('SELECT id FROM user_devices WHERE user_id = ? AND device_fingerprint = ? LIMIT 1', [$uid, $fp]);
    if ($existing) {
        db_exec(
            'UPDATE user_devices SET device_token = ?, device_label = ?, ip_address = ?, user_agent = ?, last_active_at = NOW() WHERE id = ?',
            [$devToken, $label, client_ip(), $ua, (int) $existing['id']]
        );
    } else {
        db_exec(
            'INSERT INTO user_devices (user_id, device_token, device_fingerprint, device_label, ip_address, user_agent, last_active_at) VALUES (?, ?, ?, ?, ?, ?, NOW())',
            [$uid, $devToken, $fp, $label, client_ip(), $ua]
        );
    }

    $_SESSION['user_id'] = $uid;
    $_SESSION['active_session_token'] = $token;
    $_SESSION['device_token'] = $devToken;
    $_SESSION['device_ping_at'] = time();
    current_user(true);
    if (function_exists('shop_merge_guest_cart')) {
        shop_merge_guest_cart($uid);
    }
}

function find_user_by_identifier(string $identifier): ?array
{
    $identifier = trim($identifier);
    if ($identifier === '') {
        return null;
    }
    $normPhone = function_exists('normalize_bd_phone') ? normalize_bd_phone($identifier) : '';
    if ($normPhone !== '') {
        return db_one(
            'SELECT * FROM users WHERE (email = ? OR phone = ? OR phone = ?) LIMIT 1',
            [strtolower($identifier), $identifier, $normPhone]
        );
    }
    return db_one('SELECT * FROM users WHERE (email = ? OR phone = ?) LIMIT 1', [strtolower($identifier), $identifier]);
}

function attempt_user_login(string $identifier, string $password): array
{
    $ipKey = 'user_login:ip:' . client_ip();
    if (!user_rate_limit_check($ipKey, 10, 600)) {
        return [
            'ok' => false,
            'error' => current_lang() === 'bn'
                ? 'অনেকবার ভুল চেষ্টা করা হয়েছে। ১০ মিনিট পর আবার চেষ্টা করুন।'
                : 'Too many login attempts. Please wait 10 minutes.',
        ];
    }

    $identifier = trim($identifier);
    $user = find_user_by_identifier($identifier);

    if (!$user || !password_verify($password, $user['password_hash'])) {
        try {
            db_exec(
                'INSERT INTO security_logs (event_type, identifier, ip_address, user_agent, details) VALUES ("failed_user_login", ?, ?, ?, "Invalid credentials")',
                [substr($identifier, 0, 190), client_ip(), substr((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 250)]
            );
        } catch (Throwable $e) {
        }
        return [
            'ok' => false,
            'error' => current_lang() === 'bn'
                ? 'ইমেইল/ফোন বা পাসওয়ার্ড সঠিক নয়।'
                : 'Invalid email/phone or password.',
        ];
    }

    if (!(int) $user['is_active']) {
        return [
            'ok' => false,
            'error' => current_lang() === 'bn'
                ? 'আপনার অ্যাকাউন্টটি ব্লক করা হয়েছে।'
                : 'Your account has been blocked by admin.',
        ];
    }

    $limitHit = check_device_limit_or_prepare($user);
    if ($limitHit !== null) {
        return $limitHit;
    }

    db_exec('DELETE FROM rate_limits WHERE rate_key = ?', [$ipKey]);
    login_user_session($user);
    return ['ok' => true, 'user' => $user];
}

function register_user_account(string $name, string $email, string $phone, string $password): array
{
    $name = trim($name);
    $email = strtolower(trim($email));
    $phoneRaw = trim($phone);
    $normPhone = ($phoneRaw !== '' && function_exists('normalize_bd_phone')) ? normalize_bd_phone($phoneRaw) : '';
    $phone = $normPhone !== '' ? $normPhone : $phoneRaw;

    if ($name === '') {
        return ['ok' => false, 'error' => current_lang() === 'bn' ? 'আপনার নাম দিন।' : 'Please enter your name.'];
    }
    if ($email === '' && $phone === '') {
        return ['ok' => false, 'error' => current_lang() === 'bn' ? 'ইমেইল অথবা মোবাইল নম্বর দিন।' : 'Please enter an email or phone number.'];
    }
    if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return ['ok' => false, 'error' => current_lang() === 'bn' ? 'সঠিক ইমেইল ঠিকানা দিন।' : 'Please enter a valid email address.'];
    }
    if (strlen($password) < 6) {
        return ['ok' => false, 'error' => current_lang() === 'bn' ? 'পাসওয়ার্ড কমপক্ষে ৬ অক্ষরের হতে হবে।' : 'Password must be at least 6 characters.'];
    }

    // If user registered with phone only, synthesize a unique placeholder email for the NOT NULL email column
    $effectiveEmail = $email !== '' ? $email : (preg_replace('/[^0-9]/', '', $phone) . '@phone.bookexa.local');

    if (db_one('SELECT id FROM users WHERE email = ? LIMIT 1', [$effectiveEmail])) {
        return ['ok' => false, 'error' => current_lang() === 'bn' ? 'এই ইমেইল দিয়ে আগেই অ্যাকাউন্ট খোলা হয়েছে।' : 'An account with this email already exists.'];
    }
    if ($phone !== '' && db_one('SELECT id FROM users WHERE phone = ? OR phone = ? LIMIT 1', [$phone, $phoneRaw])) {
        return ['ok' => false, 'error' => current_lang() === 'bn' ? 'এই মোবাইল নম্বর দিয়ে আগেই অ্যাকাউন্ট খোলা হয়েছে।' : 'An account with this phone number already exists.'];
    }

    $hash = password_hash($password, PASSWORD_BCRYPT);
    $id = db_insert(
        'INSERT INTO users (name, email, phone, password_hash, is_active) VALUES (?, ?, ?, ?, 1)',
        [$name, $effectiveEmail, $phone !== '' ? $phone : null, $hash]
    );
    $user = db_one('SELECT * FROM users WHERE id = ?', [$id]);
    login_user_session($user);
    return ['ok' => true, 'user' => $user];
}

function logout_user_only(): void
{
    $u = current_user();
    $devTok = (string) ($_SESSION['device_token'] ?? '');
    if ($u && $devTok !== '') {
        db_exec('DELETE FROM user_devices WHERE user_id = ? AND device_token = ?', [(int) $u['id'], $devTok]);
    } elseif ($u) {
        db_exec('UPDATE users SET active_session_token = NULL WHERE id = ?', [(int) $u['id']]);
    }
    unset($_SESSION['user_id'], $_SESSION['active_session_token'], $_SESSION['device_token'], $_SESSION['device_ping_at']);
    if (session_status() === PHP_SESSION_ACTIVE && PHP_SAPI !== 'cli') {
        session_regenerate_id(true);
    }
}
