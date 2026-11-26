<?php
declare(strict_types=1);

/**
 * Pure-PHP RFC 6238 TOTP (Google Authenticator compatible) + 8 Backup Codes.
 * Zero Composer dependencies, constant-time code comparison.
 */

const TOTP_BASE32_ALPHA = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';

function totp_generate_secret(int $length = 32): string
{
    $out = '';
    $bytes = random_bytes($length);
    for ($i = 0; $i < $length; $i++) {
        $out .= TOTP_BASE32_ALPHA[ord($bytes[$i]) & 31];
    }
    return $out;
}

function totp_base32_decode(string $secret): string
{
    $secret = strtoupper(preg_replace('/[^A-Z2-7]/', '', $secret) ?? '');
    if ($secret === '') {
        return '';
    }
    $bits = '';
    $len = strlen($secret);
    for ($i = 0; $i < $len; $i++) {
        $idx = strpos(TOTP_BASE32_ALPHA, $secret[$i]);
        if ($idx === false) {
            continue;
        }
        $bits .= str_pad(decbin($idx), 5, '0', STR_PAD_LEFT);
    }
    $binary = '';
    foreach (str_split($bits, 8) as $chunk) {
        if (strlen($chunk) === 8) {
            $binary .= chr((int) bindec($chunk));
        }
    }
    return $binary;
}

function totp_code_at(string $secret, int $slice, int $digits = 6): string
{
    $key = totp_base32_decode($secret);
    if ($key === '') {
        return '';
    }
    $hi = intdiv($slice, 0x100000000);
    $lo = $slice & 0xFFFFFFFF;
    $msg = pack('NN', $hi, $lo);
    $hash = hash_hmac('sha1', $msg, $key, true);
    $offset = ord($hash[19]) & 0x0F;
    $binary = ((ord($hash[$offset]) & 0x7F) << 24)
        | ((ord($hash[$offset + 1]) & 0xFF) << 16)
        | ((ord($hash[$offset + 2]) & 0xFF) << 8)
        | (ord($hash[$offset + 3]) & 0xFF);
    return str_pad((string) ($binary % (10 ** $digits)), $digits, '0', STR_PAD_LEFT);
}

function totp_crypto_key(): string
{
    $secret = function_exists('app_secret') ? app_secret() : (string) app_config('security.app_secret', '');
    if (function_exists('hash_hkdf')) {
        return hash_hkdf('sha256', $secret, 32, 'bookexa-totp-aes256gcm');
    }
    return hash_hmac('sha256', 'bookexa-totp-aes256gcm', $secret, true);
}

function totp_encrypt_secret(string $plainBase32): string
{
    $plainBase32 = trim($plainBase32);
    if ($plainBase32 === '' || str_starts_with($plainBase32, 'enc:v1:')) {
        return $plainBase32;
    }
    $iv = random_bytes(12);
    $tag = '';
    $cipher = openssl_encrypt($plainBase32, 'aes-256-gcm', totp_crypto_key(), OPENSSL_RAW_DATA, $iv, $tag, '', 16);
    if ($cipher === false || strlen($tag) !== 16) {
        throw new RuntimeException('Failed to encrypt TOTP secret.');
    }
    return 'enc:v1:' . base64_encode($iv . $tag . $cipher);
}

function totp_decrypt_secret(string $stored, ?bool &$wasLegacyPlaintext = null): ?string
{
    $wasLegacyPlaintext = false;
    $stored = trim($stored);
    if ($stored === '') {
        return null;
    }
    if (!str_starts_with($stored, 'enc:v1:')) {
        $clean = strtoupper(preg_replace('/[^A-Z2-7]/', '', $stored) ?? '');
        if (strlen($clean) < 16) {
            return null;
        }
        $wasLegacyPlaintext = true;
        return $clean;
    }
    $raw = base64_decode(substr($stored, 7), true);
    if ($raw === false || strlen($raw) <= 28) {
        return null;
    }
    $iv = substr($raw, 0, 12);
    $tag = substr($raw, 12, 16);
    $cipher = substr($raw, 28);
    $plain = openssl_decrypt($cipher, 'aes-256-gcm', totp_crypto_key(), OPENSSL_RAW_DATA, $iv, $tag, '');
    return $plain !== false ? $plain : null;
}

function totp_verify_with_step(string $secret, string $code, int $window = 1, int $period = 30, ?int $timestamp = null, ?int $lastUsedStep = null): ?int
{
    $clean = preg_replace('/\D/', '', trim($code)) ?? '';
    if (strlen($clean) !== 6) {
        return null;
    }
    $ts = $timestamp ?? time();
    $currSlice = intdiv($ts, $period);
    for ($i = -$window; $i <= $window; $i++) {
        $step = $currSlice + $i;
        if ($lastUsedStep !== null && $step <= $lastUsedStep) {
            continue;
        }
        $cand = totp_code_at($secret, $step, 6);
        if ($cand !== '' && hash_equals($cand, $clean)) {
            return $step;
        }
    }
    return null;
}

function totp_verify_code(string $secret, string $code, int $window = 1, int $period = 30, ?int $timestamp = null): bool
{
    return totp_verify_with_step($secret, $code, $window, $period, $timestamp, null) !== null;
}

function totp_provisioning_uri(string $issuer, string $accountEmail, string $secret): string
{
    $label = rawurlencode($issuer . ':' . $accountEmail);
    return 'otpauth://totp/' . $label
        . '?secret=' . rawurlencode($secret)
        . '&issuer=' . rawurlencode($issuer)
        . '&algorithm=SHA1&digits=6&period=30';
}

function totp_generate_backup_codes(int $count = 8): array
{
    $plain = [];
    $hashes = [];
    for ($i = 0; $i < $count; $i++) {
        $raw = strtoupper(bin2hex(random_bytes(4)));
        $fmt = substr($raw, 0, 4) . '-' . substr($raw, 4, 4);
        $plain[] = $fmt;
        $hashes[] = password_hash(str_replace('-', '', $fmt), PASSWORD_BCRYPT);
    }
    return ['plain' => $plain, 'hashes' => $hashes];
}

function totp_consume_backup_code(int $adminId, string $code): bool
{
    $norm = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', trim($code)) ?? '');
    if (strlen($norm) !== 8) {
        return false;
    }
    $row = db_one('SELECT backup_codes_json FROM admins WHERE id = ? LIMIT 1', [$adminId]);
    if (!$row || empty($row['backup_codes_json'])) {
        return false;
    }
    $hashes = json_decode((string) $row['backup_codes_json'], true);
    if (!is_array($hashes)) {
        return false;
    }
    foreach ($hashes as $idx => $h) {
        if (is_string($h) && password_verify($norm, $h)) {
            array_splice($hashes, (int) $idx, 1);
            db_exec(
                'UPDATE admins SET backup_codes_json = ? WHERE id = ?',
                [json_encode(array_values($hashes)), $adminId]
            );
            return true;
        }
    }
    return false;
}
