<?php
declare(strict_types=1);

require_once __DIR__ . '/SmsGatewayInterface.php';
require_once __DIR__ . '/NullSmsDriver.php';
require_once __DIR__ . '/GenericSmsDriver.php';

function sms_gateway_enabled(): bool
{
    return site_setting('sms_gateway_enabled', '0') === '1'
        && site_setting('sms_gateway_driver', 'none') !== 'none';
}

function sms_gateway_driver(): SmsGatewayInterface
{
    if (!sms_gateway_enabled()) {
        return new NullSmsDriver();
    }
    $driver = site_setting('sms_gateway_driver', 'generic');
    return new GenericSmsDriver($driver, [
        'sandbox'   => site_setting('sms_gateway_sandbox', '1'),
        'api_url'   => site_setting('sms_gateway_api_url', ''),
        'api_key'   => site_setting('sms_gateway_api_key', ''),
        'sender_id' => site_setting('sms_gateway_sender_id', 'Bookexa'),
    ]);
}

function sms_daily_budget(): int
{
    return max(0, (int) site_setting('sms_daily_budget', '200'));
}

function sms_daily_budget_remaining(?string $date = null): int
{
    $budget = sms_daily_budget();
    if ($budget <= 0) {
        return 0;
    }
    $key = 'sms_budget:' . ($date ?? date('Y-m-d'));
    $row = db_one('SELECT hits, window_start FROM rate_limits WHERE rate_key = ? LIMIT 1', [$key]);
    if (!$row || (time() - (int) $row['window_start']) >= 86400) {
        return $budget;
    }
    return max(0, $budget - (int) $row['hits']);
}

function notify_admin_sms_budget_exhausted(int $budget): void
{
    $ip = function_exists('client_ip') ? client_ip() : '127.0.0.1';
    try {
        db_exec(
            'INSERT INTO security_logs (event_type, identifier, ip_address, user_agent, details) VALUES (?, ?, ?, ?, ?)',
            ['sms_daily_budget_exceeded', 'sms_budget:' . date('Y-m-d'), $ip, substr((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 250), "Global daily SMS budget ({$budget}) reached"]
        );
    } catch (Throwable $e) {
    }
    if (!user_rate_limit_check('sms_budget_alert:' . date('Y-m-d'), 1, 86400)) {
        return;
    }
    $adminEmail = trim((string) (site_setting('contact_email', '') ?: site_setting('smtp_from_email', '')));
    if ($adminEmail !== '' && filter_var($adminEmail, FILTER_VALIDATE_EMAIL)) {
        require_once APP_ROOT . '/includes/mailer.php';
        try {
            send_bookexa_template_mail(
                $adminEmail,
                'Bookexa Alert: Daily SMS OTP Budget Reached',
                'Daily SMS Budget Exhausted',
                '<p>Your configured daily SMS OTP limit (<strong>' . $budget . '</strong> SMS/day) has been reached. Further OTP dispatches are blocked until tomorrow or until you raise <code>sms_daily_budget</code> in Admin Settings.</p>',
                BASE_URL . '/admin/settings',
                'Open Admin Settings'
            );
        } catch (Throwable $e) {
        }
    }
}

function sms_daily_budget_check_and_consume(): array
{
    $budget = sms_daily_budget();
    $key = 'sms_budget:' . date('Y-m-d');
    if ($budget <= 0 || !user_rate_limit_check($key, $budget, 86400)) {
        notify_admin_sms_budget_exhausted($budget);
        return ['ok' => false, 'code' => 'BUDGET_EXCEEDED', 'error' => 'Daily SMS quota exceeded.'];
    }
    return ['ok' => true];
}

function sms_otp_rate_check(string $phone, string $ip): array
{
    $bn = current_lang() === 'bn';
    $phoneHash = sha1($phone);
    $cdKey = 'otp_cd:p:' . $phoneHash;
    $cdRow = db_one('SELECT window_start FROM rate_limits WHERE rate_key = ? LIMIT 1', [$cdKey]);
    if ($cdRow) {
        $elapsed = time() - (int) $cdRow['window_start'];
        if ($elapsed >= 0 && $elapsed < 60) {
            $wait = 60 - $elapsed;
            return [
                'ok'          => false,
                'code'        => 'COOLDOWN',
                'retry_after' => $wait,
                'message'     => $bn
                    ? "পুনরায় OTP পাঠাতে {$wait} সেকেন্ড অপেক্ষা করুন।"
                    : "Please wait {$wait} second(s) before requesting another OTP.",
            ];
        }
    }

    if (!user_rate_limit_check('otp_req:ip:' . $ip, 5, 600)) {
        return [
            'ok'          => false,
            'code'        => 'IP_RATE_LIMIT',
            'retry_after' => 600,
            'message'     => $bn ? 'অনেকবার OTP চাওয়া হয়েছে। ১০ মিনিট পর চেষ্টা করুন।' : 'Too many OTP requests. Please wait 10 minutes.',
        ];
    }
    $today = date('Y-m-d');
    if (!user_rate_limit_check('otp_ip_day:' . $ip . ':' . $today, 15, 86400)) {
        return [
            'ok'          => false,
            'code'        => 'IP_DAILY_LIMIT',
            'retry_after' => 3600,
            'message'     => $bn ? 'আজকের এই IP থেকে সর্বোচ্চ OTP সীমা পূর্ণ হয়েছে।' : 'Daily OTP request limit reached from your IP address.',
        ];
    }
    if (!user_rate_limit_check('otp_hr:p:' . $phoneHash, 3, 3600)) {
        return [
            'ok'          => false,
            'code'        => 'PHONE_HOURLY_LIMIT',
            'retry_after' => 3600,
            'message'     => $bn ? 'এই নম্বরে ঘণ্টায় সর্বোচ্চ ৩ বার OTP পাঠানো যাবে। পরে চেষ্টা করুন।' : 'Too many OTP requests for this phone number (limit 3/hour).',
        ];
    }
    if (!user_rate_limit_check('otp_day:p:' . $phoneHash . ':' . $today, 5, 86400)) {
        return [
            'ok'          => false,
            'code'        => 'PHONE_DAILY_LIMIT',
            'retry_after' => 86400,
            'message'     => $bn ? 'এই নম্বরে আজকের সর্বোচ্চ ৫ বার OTP পাঠানোর সীমা পূর্ণ হয়েছে।' : 'Daily OTP limit (5/day) reached for this phone number.',
        ];
    }

    db_exec(
        'INSERT INTO rate_limits (rate_key, hits, window_start) VALUES (?, 1, ?) ON DUPLICATE KEY UPDATE hits = 1, window_start = VALUES(window_start)',
        [$cdKey, time()]
    );

    return ['ok' => true, 'retry_after' => 60];
}

function sms_gateway_send(string $to, string $message, string $code = ''): array
{
    $budgetCheck = sms_daily_budget_check_and_consume();
    if (empty($budgetCheck['ok'])) {
        return $budgetCheck;
    }
    if ($code === '' && preg_match('/\b(\d{4,8})\b/', $message, $m)) {
        $code = $m[1];
    }
    return sms_gateway_driver()->sendOtp($to, $code, $message);
}

function normalize_bd_phone(string $raw): string
{
    $digits = preg_replace('/[^0-9]/', '', trim($raw)) ?? '';
    if (str_starts_with($digits, '880') && strlen($digits) === 13) {
        $digits = '0' . substr($digits, 3);
    }
    return $digits;
}

function issue_otp_verification(?int $userId, string $purpose, string $target): string
{
    $code = str_pad((string) random_int(100000, 999999), 6, '0', STR_PAD_LEFT);
    $hash = password_hash($code, PASSWORD_BCRYPT);
    db_exec('DELETE FROM otp_verifications WHERE purpose = ? AND target = ?', [$purpose, $target]);
    db_exec(
        'INSERT INTO otp_verifications (user_id, purpose, target, code_hash, expires_at) VALUES (?, ?, ?, ?, DATE_ADD(NOW(), INTERVAL 10 MINUTE))',
        [$userId, $purpose, $target, $hash]
    );
    return $code;
}

function verify_otp_code(string $purpose, string $target, string $codeInput): array
{
    $codeInput = trim($codeInput);
    $row = db_one(
        'SELECT * FROM otp_verifications WHERE purpose = ? AND target = ? AND verified_at IS NULL AND expires_at >= NOW() ORDER BY id DESC LIMIT 1',
        [$purpose, $target]
    );
    if (!$row) {
        return ['ok' => false, 'message' => current_lang() === 'bn' ? 'কোডটি অকার্যকর বা মেয়াদোত্তীর্ণ।' : 'Verification code is invalid or expired.'];
    }
    if ((int) $row['attempts'] >= 5) {
        return ['ok' => false, 'message' => current_lang() === 'bn' ? 'অনেকবার ভুল চেষ্টা। নতুন কোড নিন।' : 'Too many failed attempts. Request a new code.'];
    }
    if (!password_verify($codeInput, (string) $row['code_hash'])) {
        db_exec('UPDATE otp_verifications SET attempts = attempts + 1 WHERE id = ?', [(int) $row['id']]);
        return ['ok' => false, 'message' => current_lang() === 'bn' ? 'ভুল যাচাইকরণ কোড।' : 'Incorrect verification code.'];
    }
    db_exec('UPDATE otp_verifications SET verified_at = NOW() WHERE id = ?', [(int) $row['id']]);
    return ['ok' => true, 'row' => $row];
}
