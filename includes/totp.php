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

function totp_verify_code(string $secret, string $code, int $window = 1, int $period = 30, ?int $timestamp = null): bool
{
    $clean = preg_replace('/\D/', '', trim($code)) ?? '';
    if (strlen($clean) !== 6) {
        return false;
    }
    $ts = $timestamp ?? time();
    $currSlice = intdiv($ts, $period);
    for ($i = -$window; $i <= $window; $i++) {
        $cand = totp_code_at($secret, $currSlice + $i, 6);
        if ($cand !== '' && hash_equals($cand, $clean)) {
            return true;
        }
    }
    return false;
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
