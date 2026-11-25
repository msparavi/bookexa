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
