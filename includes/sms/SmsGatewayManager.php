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

function sms_gateway_send(string $to, string $message): array
{
    return sms_gateway_driver()->send($to, $message);
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
