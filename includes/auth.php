<?php
declare(strict_types=1);

function current_user(): ?array
{
    static $cached = false;
    if ($cached !== false) {
        return $cached;
    }

    $uid = $_SESSION['user_id'] ?? null;
    if (!$uid) {
        $cached = null;
        return null;
    }

    $user = db_one(
        'SELECT id, name, email, phone, is_active, active_session_token FROM users WHERE id = ? LIMIT 1',
        [(int) $uid]
    );
    if (!$user || !(int) $user['is_active']) {
        unset($_SESSION['user_id'], $_SESSION['active_session_token']);
        $cached = null;
        return null;
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

function login_user_session(array $user): void
{
    session_regenerate_id(true);
    $token = bin2hex(random_bytes(32));
    db_exec('UPDATE users SET active_session_token = ?, active_session_updated_at = NOW() WHERE id = ?', [$token, (int) $user['id']]);
    $_SESSION['user_id'] = (int) $user['id'];
    $_SESSION['active_session_token'] = $token;
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
    $user = db_one('SELECT * FROM users WHERE (email = ? OR phone = ?) LIMIT 1', [strtolower($identifier), $identifier]);

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

    db_exec('DELETE FROM rate_limits WHERE rate_key = ?', [$ipKey]);
    login_user_session($user);
    return ['ok' => true, 'user' => $user];
}

function register_user_account(string $name, string $email, string $phone, string $password): array
{
    $name = trim($name);
    $email = strtolower(trim($email));
    $phone = trim($phone);

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
    if ($phone !== '' && db_one('SELECT id FROM users WHERE phone = ? LIMIT 1', [$phone])) {
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
    if ($u) {
        db_exec('UPDATE users SET active_session_token = NULL WHERE id = ?', [(int) $u['id']]);
    }
    unset($_SESSION['user_id'], $_SESSION['active_session_token']);
    session_regenerate_id(true);
}
