<?php
declare(strict_types=1);

/**
 * Minimal pure-PHP SMTP client (no Composer/external dependencies).
 * Supports plain TCP ('none'), STARTTLS ('tls'), and SMTPS ('ssl').
 */
function smtp_read_response($fp): array
{
    $lines = '';
    $code = 0;
    while (!feof($fp)) {
        $line = fgets($fp, 1024);
        if ($line === false) {
            break;
        }
        $lines .= $line;
        if (strlen($line) >= 3 && ctype_digit(substr($line, 0, 3))) {
            $code = (int) substr($line, 0, 3);
            if (($line[3] ?? ' ') !== '-') {
                break;
            }
        } else {
            break;
        }
    }
    return ['code' => $code, 'raw' => trim($lines)];
}

function smtp_command($fp, string $cmd, array $expectedCodes): array
{
    fwrite($fp, $cmd . "\r\n");
    $resp = smtp_read_response($fp);
    if (!in_array($resp['code'], $expectedCodes, true)) {
        return ['ok' => false, 'code' => $resp['code'], 'error' => "SMTP error ({$resp['code']}): {$resp['raw']}"];
    }
    return ['ok' => true, 'code' => $resp['code'], 'raw' => $resp['raw']];
}

function bookexa_smtp_config(): array
{
    $cfg = [
        'host'       => '',
        'port'       => 587,
        'user'       => '',
        'pass'       => '',
        'from_name'  => 'Bookexa',
        'from_email' => 'no-reply@bookexa.com',
        'encryption' => 'tls',
    ];
    try {
        $rows = db_all("SELECT setting_key, setting_value FROM settings WHERE setting_key IN ('smtp_host','smtp_port','smtp_user','smtp_pass','smtp_from_name','smtp_from_email','smtp_encryption','site_name','contact_email')");
        $map = [];
        foreach ($rows as $r) {
            $map[$r['setting_key']] = (string) $r['setting_value'];
        }
        $cfg['host']       = trim($map['smtp_host'] ?? '');
        $cfg['port']       = max(1, (int) ($map['smtp_port'] ?? 587));
        $cfg['user']       = trim($map['smtp_user'] ?? '');
        $cfg['pass']       = (string) ($map['smtp_pass'] ?? '');
        $cfg['from_name']  = trim($map['smtp_from_name'] ?? '') ?: ($map['site_name'] ?? 'Bookexa');
        $cfg['from_email'] = trim($map['smtp_from_email'] ?? '') ?: ($map['contact_email'] ?? 'no-reply@bookexa.com');
        $enc = strtolower(trim($map['smtp_encryption'] ?? 'tls'));
        $cfg['encryption'] = in_array($enc, ['none', 'tls', 'ssl'], true) ? $enc : 'tls';
    } catch (Throwable $e) {
    }
    return $cfg;
}

function smtp_send_mail(string $toEmail, string $subject, string $htmlBody, string $textBody = '', ?array $overrideCfg = null): array
{
    $toEmail = trim($toEmail);
    if ($toEmail === '' || !filter_var($toEmail, FILTER_VALIDATE_EMAIL) || str_ends_with(strtolower($toEmail), '.local')) {
        return ['ok' => false, 'skipped' => true, 'error' => 'Invalid or local placeholder recipient address.'];
    }

    $cfg = $overrideCfg ? array_merge(bookexa_smtp_config(), $overrideCfg) : bookexa_smtp_config();
    $host = trim((string) $cfg['host']);
    $port = (int) $cfg['port'];
    $enc  = strtolower((string) $cfg['encryption']);

    if ($host === '') {
        return ['ok' => false, 'skipped' => true, 'error' => 'SMTP host is not configured in Admin Settings.'];
    }

    $fromEmail = filter_var($cfg['from_email'], FILTER_VALIDATE_EMAIL) ? $cfg['from_email'] : 'no-reply@bookexa.com';
    $fromName  = str_replace(["\r", "\n"], '', (string) $cfg['from_name']) ?: 'Bookexa';
    $cleanSubj = str_replace(["\r", "\n"], '', $subject);

    $remote = ($enc === 'ssl' ? 'ssl://' : 'tcp://') . $host . ':' . $port;
    $ctx = stream_context_create([
        'ssl' => [
            'verify_peer'      => false,
            'verify_peer_name' => false,
            'allow_self_signed'=> true,
        ],
    ]);

    $errno = 0;
    $errstr = '';
    $fp = @stream_socket_client($remote, $errno, $errstr, 10, STREAM_CLIENT_CONNECT, $ctx);
    if (!$fp) {
        return ['ok' => false, 'error' => "Could not connect to SMTP {$host}:{$port} — {$errstr} ({$errno})"];
    }
    stream_set_timeout($fp, 12);

    try {
        $greet = smtp_read_response($fp);
        if ($greet['code'] !== 220) {
            throw new RuntimeException('SMTP greeting failed: ' . $greet['raw']);
        }

        $ehloHost = preg_replace('/[^a-zA-Z0-9.-]/', '', (string) ($_SERVER['HTTP_HOST'] ?? 'localhost')) ?: 'localhost';
        $res = smtp_command($fp, 'EHLO ' . $ehloHost, [250]);
        if (!$res['ok']) {
            $res = smtp_command($fp, 'HELO ' . $ehloHost, [250]);
            if (!$res['ok']) {
                throw new RuntimeException($res['error']);
            }
        }

        if ($enc === 'tls') {
            $st = smtp_command($fp, 'STARTTLS', [220]);
            if (!$st['ok']) {
                throw new RuntimeException($st['error']);
            }
            $cryptoOk = @stream_socket_enable_crypto($fp, true, STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT | STREAM_CRYPTO_METHOD_TLSv1_3_CLIENT);
            if (!$cryptoOk) {
                throw new RuntimeException('STARTTLS negotiation failed.');
            }
            $res = smtp_command($fp, 'EHLO ' . $ehloHost, [250]);
            if (!$res['ok']) {
                throw new RuntimeException($res['error']);
            }
        }

        if ($cfg['user'] !== '') {
            $auth = smtp_command($fp, 'AUTH LOGIN', [334]);
            if (!$auth['ok']) {
                throw new RuntimeException($auth['error']);
            }
            $uRes = smtp_command($fp, base64_encode((string) $cfg['user']), [334]);
            if (!$uRes['ok']) {
                throw new RuntimeException('SMTP username rejected: ' . $uRes['error']);
            }
            $pRes = smtp_command($fp, base64_encode((string) $cfg['pass']), [235]);
            if (!$pRes['ok']) {
                throw new RuntimeException('SMTP authentication failed: ' . $pRes['error']);
            }
        }

        $mf = smtp_command($fp, 'MAIL FROM:<' . $fromEmail . '>', [250]);
        if (!$mf['ok']) {
            throw new RuntimeException($mf['error']);
        }
        $rt = smtp_command($fp, 'RCPT TO:<' . $toEmail . '>', [250, 251]);
        if (!$rt['ok']) {
            throw new RuntimeException($rt['error']);
        }
        $dt = smtp_command($fp, 'DATA', [354]);
        if (!$dt['ok']) {
            throw new RuntimeException($dt['error']);
        }

        $boundary = 'bx_' . bin2hex(random_bytes(10));
        $plain = $textBody !== '' ? $textBody : trim(strip_tags(str_replace(['<br>', '<br/>', '</p>'], "\n", $htmlBody)));
        $encSubj = '=?UTF-8?B?' . base64_encode($cleanSubj) . '?=';
        $encFrom = '=?UTF-8?B?' . base64_encode($fromName) . '?= <' . $fromEmail . '>';

        $headers = [
            'Date: ' . date('r'),
            'From: ' . $encFrom,
            'To: <' . $toEmail . '>',
            'Subject: ' . $encSubj,
            'MIME-Version: 1.0',
            'Content-Type: multipart/alternative; boundary="' . $boundary . '"',
        ];

        $bodyLines = [
            '--' . $boundary,
            'Content-Type: text/plain; charset=UTF-8',
            'Content-Transfer-Encoding: base64',
            '',
            chunk_split(base64_encode($plain)),
            '--' . $boundary,
            'Content-Type: text/html; charset=UTF-8',
            'Content-Transfer-Encoding: base64',
            '',
            chunk_split(base64_encode($htmlBody)),
            '--' . $boundary . '--',
            '',
        ];

        $payload = implode("\r\n", $headers) . "\r\n\r\n" . implode("\r\n", $bodyLines);
        $payload = str_replace("\r\n.", "\r\n..", $payload);

        fwrite($fp, $payload . "\r\n.\r\n");
        $done = smtp_read_response($fp);
        if ($done['code'] !== 250) {
            throw new RuntimeException('SMTP message rejected: ' . $done['raw']);
        }
        @fwrite($fp, "QUIT\r\n");
        @fclose($fp);
        return ['ok' => true, 'error' => ''];
    } catch (Throwable $e) {
        @fclose($fp);
        try {
            db_exec(
                'INSERT INTO security_logs (event_type, identifier, ip_address, details) VALUES ("smtp_error", ?, ?, ?)',
                [substr($toEmail, 0, 190), client_ip(), substr($e->getMessage(), 0, 250)]
            );
        } catch (Throwable $ignore) {
        }
        return ['ok' => false, 'error' => $e->getMessage()];
    }
}

function send_bookexa_template_mail(string $toEmail, string $subject, string $headline, string $bodyHtml, ?string $ctaUrl = null, ?string $ctaLabel = null): array
{
    $siteName = site_setting('site_name', 'Bookexa');
    $safeSite = e($siteName);
    $safeHead = e($headline);
    $btnHtml  = ($ctaUrl && $ctaLabel)
        ? '<p style="margin:22px 0;"><a href="' . e($ctaUrl) . '" style="display:inline-block;padding:12px 22px;background:#2B2F77;color:#FFFFFF;text-decoration:none;border-radius:10px;font-weight:700;">' . e($ctaLabel) . '</a></p>'
        : '';

    $html = '<!DOCTYPE html><html><body style="margin:0;padding:24px;background:#F4EFE6;font-family:Arial,sans-serif;color:#14213D;">'
        . '<div style="max-width:560px;margin:0 auto;background:#FFFFFF;border-radius:16px;padding:26px;border:1px solid #E5DDD0;">'
        . '<div style="font-weight:800;font-size:20px;color:#2B2F77;margin-bottom:14px;">' . $safeSite . '</div>'
        . '<h2 style="margin:0 0 12px;font-size:18px;color:#14213D;">' . $safeHead . '</h2>'
        . '<div style="font-size:14px;line-height:1.65;color:#2D3856;">' . $bodyHtml . '</div>'
        . $btnHtml
        . '<hr style="border:none;border-top:1px solid #EAE2D6;margin:22px 0 12px;">'
        . '<p style="margin:0;font-size:12px;color:#5D6985;">' . $safeSite . ' • Protected Digital Library</p>'
        . '</div></body></html>';

    return smtp_send_mail($toEmail, $subject, $html);
}
