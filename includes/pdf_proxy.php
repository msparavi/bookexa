<?php
declare(strict_types=1);

function extract_drive_file_id(string $input): ?string
{
    $input = trim($input);
    if (preg_match('~/file/d/([a-zA-Z0-9_-]{10,})~', $input, $m)) {
        return $m[1];
    }
    if (preg_match('~[?&]id=([a-zA-Z0-9_-]{10,})~', $input, $m)) {
        return $m[1];
    }
    if (preg_match('~^[a-zA-Z0-9_-]{20,}$~', $input)) {
        return $input;
    }
    return null;
}

function log_reading_event(int $userId, ?int $itemId, bool $isSample, string $action, ?string $details = null): void
{
    try {
        $safeItemId = ($itemId !== null && $itemId > 0) ? $itemId : null;
        db_exec(
            'INSERT INTO reading_logs (user_id, item_id, is_sample, action, ip_address, user_agent, details)
             VALUES (?, ?, ?, ?, ?, ?, ?)',
            [
                $userId,
                $safeItemId,
                $isSample ? 1 : 0,
                substr($action, 0, 50),
                client_ip(),
                substr((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 250),
                $details !== null ? substr($details, 0, 250) : null,
            ]
        );
    } catch (Throwable $e) {
    }
}

function b64url_encode(string $data): string
{
    return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
}

function b64url_decode(string $data): ?string
{
    $pad = strlen($data) % 4;
    if ($pad > 0) {
        $data .= str_repeat('=', 4 - $pad);
    }
    $decoded = base64_decode(strtr($data, '-_', '+/'), true);
    return $decoded !== false ? $decoded : null;
}

function page_storage_dir(int $itemId): string
{
    return APP_ROOT . '/storage/pages/' . $itemId;
}

function ensure_pages_htaccess(): void
{
    $rootDir = APP_ROOT . '/storage/pages';
    if (!is_dir($rootDir)) {
        @mkdir($rootDir, 0755, true);
    }
    $ht = $rootDir . '/.htaccess';
    if (!is_file($ht)) {
        @file_put_contents($ht, "<IfModule mod_authz_core.c>\n    Require all denied\n</IfModule>\n<IfModule !mod_authz_core.c>\n    Order allow,deny\n    Deny from all\n</IfModule>\n");
    }
}

function find_prepared_page_file(int $itemId, int $pageNum): ?string
{
    $dir = page_storage_dir($itemId);
    foreach (['webp', 'jpg'] as $ext) {
        $p = $dir . '/page-' . $pageNum . '.' . $ext;
        if (is_file($p)) {
            return $p;
        }
    }
    return null;
}

function count_prepared_pages(int $itemId): int
{
    $dir = page_storage_dir($itemId);
    if (!is_dir($dir)) {
        return 0;
    }
    $n = 0;
    while (find_prepared_page_file($itemId, $n + 1) !== null) {
        $n++;
    }
    return $n;
}

/**
 * Issues a short-lived HMAC-signed token bound to user, PHP session, active reading session, mode, and item.
 */
function issue_signed_reader_token(int $userId, int $itemId, bool $isSample, string $sessionToken, string $mode = 'pages', int $ttlSeconds = 900): string
{
    $jti = bin2hex(random_bytes(16));
    $exp = time() + max(60, $ttlSeconds);
    $payload = [
        'uid' => $userId,
        'iid' => $itemId,
        'smp' => $isSample ? 1 : 0,
        'mod' => $mode,
        'sid' => hash('sha256', session_id()),
        'stk' => hash('sha256', $sessionToken),
        'jti' => $jti,
        'exp' => $exp,
    ];
    $payloadB64 = b64url_encode((string) json_encode($payload));
    $secret = (string) app_config('security.app_secret', 'bookexa_fallback_secret');
    $sig = hash_hmac('sha256', $payloadB64, $secret);
    $fullToken = $payloadB64 . '.' . $sig;

    db_exec(
        'INSERT INTO reader_tokens (token_hash, jti, user_id, item_id, is_sample, session_id, session_token, ip_address, expires_at)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)',
        [hash('sha256', $fullToken), $jti, $userId, $itemId, $isSample ? 1 : 0, session_id(), $sessionToken, client_ip(), date('Y-m-d H:i:s', $exp)]
    );

    return $fullToken;
}

/**
 * Detect abnormal rapid multi-session switching within the last hour.
 */
function detect_many_sessions_anomaly(int $userId): void
{
    try {
        $row = db_one(
            "SELECT
                SUM(action = 'open_reader') AS opens,
                SUM(action = 'session_kicked') AS kicks
             FROM reading_logs
             WHERE user_id = ? AND created_at >= DATE_SUB(NOW(), INTERVAL 60 MINUTE)",
            [$userId]
        );
        $opens = (int) ($row['opens'] ?? 0);
        $kicks = (int) ($row['kicks'] ?? 0);
        if ($opens >= 6 || $kicks >= 3) {
            $recent = db_one(
                "SELECT id FROM reading_logs
                 WHERE user_id = ? AND action = 'many_sessions_anomaly' AND created_at >= DATE_SUB(NOW(), INTERVAL 15 MINUTE)
                 LIMIT 1",
                [$userId]
            );
            if (!$recent) {
                log_reading_event($userId, null, false, 'many_sessions_anomaly', "opens={$opens}/h, kicks={$kicks}/h");
            }
        }
    } catch (Throwable $e) {
    }
}

/**
 * Detect abnormally fast sequential page scraping bursts in session.
 */
function track_fast_sequential_burst(int $userId, int $itemId, int $pageNum): void
{
    $now = time();
    if (!isset($_SESSION['bx_page_burst']) || !is_array($_SESSION['bx_page_burst'])) {
        $_SESSION['bx_page_burst'] = [];
    }
    $bucket = $_SESSION['bx_page_burst'][$itemId] ?? ['events' => [], 'last_alert' => 0];
    $events = array_values(array_filter(
        $bucket['events'] ?? [],
        fn($ev) => is_array($ev) && (($now - (int) ($ev['t'] ?? 0)) <= 10)
    ));
    $events[] = ['p' => $pageNum, 't' => $now];
    if (count($events) > 30) {
        $events = array_slice($events, -30);
    }
    $distinctPages = count(array_unique(array_column($events, 'p')));
    $lastAlert = (int) ($bucket['last_alert'] ?? 0);
    if ($distinctPages >= 12 && ($now - $lastAlert) >= 60) {
        log_reading_event($userId, $itemId, false, 'fast_sequential_burst', "{$distinctPages} distinct pages fetched within 10s");
        $lastAlert = $now;
    }
    $_SESSION['bx_page_burst'][$itemId] = ['events' => $events, 'last_alert' => $lastAlert];
}

/**
 * Reliable Google Drive fetch with protected cache, large-file confirmation parsing, retries & friendly errors.
 * Never returns or logs raw Drive URLs or file IDs.
 */
function fetch_source_pdf_secure(string $source): array
{
    if (str_starts_with($source, 'local://')) {
        $filename = basename(substr($source, 8));
        $localPath = APP_ROOT . '/storage/demo/' . $filename;
        if (!is_file($localPath)) {
            return ['ok' => false, 'code' => 'NOT_FOUND', 'message' => 'Requested document is unavailable.'];
        }
        $data = file_get_contents($localPath);
        return $data !== false
            ? ['ok' => true, 'bytes' => $data]
            : ['ok' => false, 'code' => 'READ_ERROR', 'message' => 'Could not read document.'];
    }

    $fileId = extract_drive_file_id($source);
    if ($fileId === null) {
        return ['ok' => false, 'code' => 'INVALID_SOURCE', 'message' => 'Document source is misconfigured.'];
    }

    // Protected server-side cache (hashed filename, never exposes Drive ID)
    $cacheEnabled = (bool) app_config('security.pdf_cache_enabled', true);
    $cacheTtl     = (int) app_config('security.pdf_cache_ttl', 21600);
    $cacheDir     = APP_ROOT . '/storage/cache';
    $secret       = (string) app_config('security.app_secret', 'bookexa_cache');
    $cachePath    = $cacheDir . '/' . hash_hmac('sha256', $fileId, $secret) . '.bin';

    if ($cacheEnabled && is_file($cachePath) && (time() - filemtime($cachePath)) < $cacheTtl) {
        $cached = @file_get_contents($cachePath);
        if ($cached !== false && str_starts_with(ltrim($cached), '%PDF-')) {
            return ['ok' => true, 'bytes' => $cached];
        }
    }

    if (!function_exists('curl_init')) {
        return ['ok' => false, 'code' => 'CURL_MISSING', 'message' => 'Server cURL extension is required.'];
    }

    $lastError = ['ok' => false, 'code' => 'FETCH_FAILED', 'message' => 'Could not fetch PDF right now. Please try again shortly.'];

    for ($attempt = 1; $attempt <= 3; $attempt++) {
        if ($attempt > 1) {
            usleep(400000 * ($attempt - 1)); // 400ms, 800ms backoff
        }

        $cookieFile = tempnam(sys_get_temp_dir(), 'bx_gd_');
        $urls = [
            'https://drive.usercontent.google.com/download?id=' . rawurlencode($fileId) . '&export=download&confirm=t',
            'https://drive.google.com/uc?export=download&confirm=t&id=' . rawurlencode($fileId),
        ];

        foreach ($urls as $targetUrl) {
            $ch = curl_init($targetUrl);
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_MAXREDIRS      => 6,
                CURLOPT_TIMEOUT        => 45,
                CURLOPT_CONNECTTIMEOUT => 12,
                CURLOPT_COOKIEJAR      => $cookieFile,
                CURLOPT_COOKIEFILE     => $cookieFile,
                CURLOPT_USERAGENT      => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 Chrome/124.0.0.0 Safari/537.36',
                CURLOPT_SSL_VERIFYPEER => true,
            ]);
            $body = curl_exec($ch);
            $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $contentType = strtolower((string) curl_getinfo($ch, CURLINFO_CONTENT_TYPE));
            $curlErrNo = curl_errno($ch);
            curl_close($ch);

            if ($body === false || $curlErrNo !== 0) {
                $lastError = ['ok' => false, 'code' => 'TIMEOUT', 'message' => 'Connection timed out while loading the PDF. Please retry.'];
                continue;
            }

            // Valid PDF bytes received
            if (str_starts_with(ltrim($body), '%PDF-') || (str_contains($contentType, 'application/pdf') && strlen($body) > 100)) {
                if ($cookieFile && file_exists($cookieFile)) {
                    @unlink($cookieFile);
                }
                if ($cacheEnabled) {
                    if (!is_dir($cacheDir)) {
                        @mkdir($cacheDir, 0755, true);
                    }
                    $tmpCache = $cachePath . '.' . bin2hex(random_bytes(4));
                    if (@file_put_contents($tmpCache, $body) !== false) {
                        @rename($tmpCache, $cachePath);
                    }
                }
                return ['ok' => true, 'bytes' => $body];
            }

            // Parse Google Drive HTML responses (quota exceeded, permission denied, or large-file confirm form)
            $lowerBody = strtolower($body);
            if (str_contains($lowerBody, 'quota exceeded') || str_contains($lowerBody, 'too many users have viewed') || $httpCode === 429) {
                if ($cookieFile && file_exists($cookieFile)) {
                    @unlink($cookieFile);
                }
                return ['ok' => false, 'code' => 'DRIVE_QUOTA', 'message' => 'High demand on document storage right now. Please try again in a few minutes.'];
            }
            if ($httpCode === 401 || $httpCode === 403 || str_contains($lowerBody, 'you need access') || str_contains($lowerBody, 'accounts.google.com')) {
                if ($cookieFile && file_exists($cookieFile)) {
                    @unlink($cookieFile);
                }
                return ['ok' => false, 'code' => 'DRIVE_ACCESS', 'message' => 'This PDF is temporarily unavailable. Please contact support.'];
            }

            // Large-file virus scan confirmation HTML form parsing
            if (str_contains($contentType, 'text/html')) {
                $confirmUrl = null;
                if (preg_match('/<form[^>]+action=["\']([^"\']+)["\'][^>]*>(.*?)<\/form>/is', $body, $fm)) {
                    $actionUrl = html_entity_decode($fm[1], ENT_QUOTES);
                    $params = [];
                    if (preg_match_all('/<input[^>]+name=["\']([^"\']+)["\'][^>]+value=["\']([^"\']*)["\']/is', $fm[2], $inputs, PREG_SET_ORDER)) {
                        foreach ($inputs as $inp) {
                            $params[$inp[1]] = html_entity_decode($inp[2], ENT_QUOTES);
                        }
                    }
                    if (!empty($params)) {
                        $sep = str_contains($actionUrl, '?') ? '&' : '?';
                        $confirmUrl = $actionUrl . $sep . http_build_query($params);
                    }
                } elseif (preg_match('/confirm=([a-zA-Z0-9_-]+)/', $body, $cm)) {
                    $confirmUrl = 'https://drive.google.com/uc?export=download&confirm=' . rawurlencode($cm[1]) . '&id=' . rawurlencode($fileId);
                }

                if ($confirmUrl !== null) {
                    $ch2 = curl_init($confirmUrl);
                    curl_setopt_array($ch2, [
                        CURLOPT_RETURNTRANSFER => true,
                        CURLOPT_FOLLOWLOCATION => true,
                        CURLOPT_MAXREDIRS      => 6,
                        CURLOPT_TIMEOUT        => 60,
                        CURLOPT_COOKIEJAR      => $cookieFile,
                        CURLOPT_COOKIEFILE     => $cookieFile,
                        CURLOPT_USERAGENT      => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 Chrome/124.0.0.0 Safari/537.36',
                    ]);
                    $body2 = curl_exec($ch2);
                    curl_close($ch2);
                    if ($body2 !== false && str_starts_with(ltrim($body2), '%PDF-')) {
                        if ($cookieFile && file_exists($cookieFile)) {
                            @unlink($cookieFile);
                        }
                        if ($cacheEnabled) {
                            if (!is_dir($cacheDir)) {
                                @mkdir($cacheDir, 0755, true);
                            }
                            $tmpCache = $cachePath . '.' . bin2hex(random_bytes(4));
                            if (@file_put_contents($tmpCache, $body2) !== false) {
                                @rename($tmpCache, $cachePath);
                            }
                        }
                        return ['ok' => true, 'bytes' => $body2];
                    }
                }
            }
        }

        if ($cookieFile && file_exists($cookieFile)) {
            @unlink($cookieFile);
        }
    }

    return $lastError;
}

/**
 * Pure-PHP Server-Side PDF Page Slicer for Free Sample Enforcement.
 */
function slice_pdf_first_pages(string $pdfBytes, int $maxPages): ?string
{
    if ($maxPages < 1 || preg_match('/\/Type\s*\/XRef\b/', $pdfBytes)) {
        return null;
    }
    if (!preg_match_all('/(\d+)\s+0\s+obj\b(.*?)endobj/s', $pdfBytes, $matches, PREG_SET_ORDER)) {
        return null;
    }

    $objects = [];
    foreach ($matches as $m) {
        $objects[(int) $m[1]] = trim($m[2]);
    }

    $rootId = null;
    if (preg_match('/\/Root\s+(\d+)\s+0\s+R/', $pdfBytes, $rm)) {
        $rootId = (int) $rm[1];
    }
    if ($rootId === null || !isset($objects[$rootId]) || !preg_match('/\/Pages\s+(\d+)\s+0\s+R/', $objects[$rootId], $pm)) {
        return null;
    }
    $pagesRootId = (int) $pm[1];

    $orderedLeafPages = [];
    $collectLeaves = function (int $nodeId) use (&$collectLeaves, &$orderedLeafPages, $objects): void {
        if (!isset($objects[$nodeId])) {
            return;
        }
        $body = $objects[$nodeId];
        if (preg_match('/\/Type\s*\/Pages\b/', $body) && preg_match('/\/Kids\s*\[(.*?)\]/s', $body, $km)) {
            if (preg_match_all('/(\d+)\s+0\s+R/', $km[1], $kids)) {
                foreach ($kids[1] as $kidId) {
                    $collectLeaves((int) $kidId);
                }
            }
        } elseif (preg_match('/\/Type\s*\/Page\b/', $body)) {
            $orderedLeafPages[] = $nodeId;
        }
    };
    $collectLeaves($pagesRootId);

    if (empty($orderedLeafPages)) {
        return null;
    }
    if (count($orderedLeafPages) <= $maxPages) {
        return $pdfBytes;
    }

    $keepPages = array_slice($orderedLeafPages, 0, $maxPages);
    $dropPages = array_slice($orderedLeafPages, $maxPages);

    $keptContents = [];
    foreach ($keepPages as $pid) {
        if (preg_match('/\/Contents\s+(\d+)\s+0\s+R/', $objects[$pid], $cm)) {
            $keptContents[(int) $cm[1]] = true;
        } elseif (preg_match('/\/Contents\s*\[(.*?)\]/s', $objects[$pid], $cm)) {
            if (preg_match_all('/(\d+)\s+0\s+R/', $cm[1], $cids)) {
                foreach ($cids[1] as $cid) {
                    $keptContents[(int) $cid] = true;
                }
            }
        }
    }

    $droppedContents = [];
    foreach ($dropPages as $pid) {
        if (preg_match('/\/Contents\s+(\d+)\s+0\s+R/', $objects[$pid], $cm)) {
            $cid = (int) $cm[1];
            if (!isset($keptContents[$cid])) {
                $droppedContents[$cid] = true;
            }
        } elseif (preg_match('/\/Contents\s*\[(.*?)\]/s', $objects[$pid], $cm)) {
            if (preg_match_all('/(\d+)\s+0\s+R/', $cm[1], $cids)) {
                foreach ($cids[1] as $cid) {
                    $cid = (int) $cid;
                    if (!isset($keptContents[$cid])) {
                        $droppedContents[$cid] = true;
                    }
                }
            }
        }
    }

    $kidsList = implode(' ', array_map(fn($id) => "$id 0 R", $keepPages));
    $objects[$pagesRootId] = "<< /Type /Pages /Kids [ $kidsList ] /Count " . count($keepPages) . " >>";
    foreach ($dropPages as $pid) {
        $objects[$pid] = "<< /Type /Null >>";
    }
    foreach (array_keys($droppedContents) as $cid) {
        $objects[$cid] = "<< /Length 0 >>\nstream\n\nendstream";
    }

    ksort($objects);
    $maxObjNum = max(array_keys($objects));
    $out = "%PDF-1.4\n";
    $offsets = [];
    foreach ($objects as $num => $body) {
        $offsets[$num] = strlen($out);
        $out .= "$num 0 obj\n$body\nendobj\n";
    }
    $xrefPos = strlen($out);
    $size = $maxObjNum + 1;
    $out .= "xref\n0 $size\n0000000000 65535 f \n";
    for ($i = 1; $i < $size; $i++) {
        $out .= isset($offsets[$i]) ? sprintf("%010d 00000 n \n", $offsets[$i]) : "0000000000 65535 f \n";
    }
    $out .= "trailer\n<< /Size $size /Root $rootId 0 R >>\nstartxref\n$xrefPos\n%%EOF\n";
    return $out;
}

function handle_stream_pdf(): never
{
    $user = current_user();
    if (!$user) {
        http_response_code(401);
        header('Content-Type: application/json');
        echo json_encode(['error' => 'AUTH_REQUIRED']);
        exit;
    }

    $uid = (int) $user['id'];

    // Anti-hotlinking & direct browser navigation check
    if (($_SERVER['HTTP_X_BOOKEXA_READER'] ?? '') !== '1') {
        log_reading_event($uid, 0, false, 'hotlink_attempt', 'Missing X-Bookexa-Reader header');
        http_response_code(403);
        header('Content-Type: application/json');
        echo json_encode(['error' => 'DIRECT_ACCESS_BLOCKED']);
        exit;
    }
    $secFetchSite = strtolower((string) ($_SERVER['HTTP_SEC_FETCH_SITE'] ?? ''));
    if ($secFetchSite !== '' && $secFetchSite !== 'same-origin') {
        log_reading_event($uid, 0, false, 'hotlink_attempt', 'Sec-Fetch-Site=' . $secFetchSite);
        http_response_code(403);
        header('Content-Type: application/json');
        echo json_encode(['error' => 'HOTLINK_BLOCKED']);
        exit;
    }
    $referer = (string) ($_SERVER['HTTP_REFERER'] ?? '');
    if ($referer !== '') {
        $refHost = strtolower((string) parse_url($referer, PHP_URL_HOST));
        $srvHost = strtolower((string) ($_SERVER['HTTP_HOST'] ?? ''));
        $srvHostNoPort = explode(':', $srvHost)[0];
        if ($refHost !== '' && $refHost !== $srvHostNoPort) {
            log_reading_event($uid, 0, false, 'hotlink_attempt', 'Cross-origin referer');
            http_response_code(403);
            header('Content-Type: application/json');
            echo json_encode(['error' => 'HOTLINK_BLOCKED']);
            exit;
        }
    }

    // Rate limiting (track & log spikes)
    if (!user_rate_limit_check('stream:user:' . $uid, 30, 300)) {
        log_reading_event($uid, 0, false, 'rate_limit_hit', 'Exceeded 30 stream requests in 5 mins');
        http_response_code(429);
        header('Content-Type: application/json');
        echo json_encode(['error' => 'RATE_LIMITED']);
        exit;
    }

    // Verify signed token format & HMAC signature
    $rawToken = (string) ($_GET['token'] ?? '');
    if (!preg_match('/^([A-Za-z0-9_-]+)\.([a-f0-9]{64})$/', $rawToken, $tm)) {
        if (!user_rate_limit_check('tok_fail:user:' . $uid, 5, 600)) {
            log_reading_event($uid, 0, false, 'repeated_token_failures', 'Malformed token');
        }
        http_response_code(403);
        header('Content-Type: application/json');
        echo json_encode(['error' => 'INVALID_TOKEN']);
        exit;
    }

    $secret = (string) app_config('security.app_secret', 'bookexa_fallback_secret');
    $expectedSig = hash_hmac('sha256', $tm[1], $secret);
    if (!hash_equals($expectedSig, $tm[2])) {
        log_reading_event($uid, 0, false, 'repeated_token_failures', 'HMAC signature mismatch');
        http_response_code(403);
        header('Content-Type: application/json');
        echo json_encode(['error' => 'INVALID_SIGNATURE']);
        exit;
    }

    $payloadJson = b64url_decode($tm[1]);
    $payload = $payloadJson !== null ? json_decode($payloadJson, true) : null;
    if (!is_array($payload) || (int) ($payload['uid'] ?? 0) !== $uid || (int) ($payload['exp'] ?? 0) < time()) {
        http_response_code(403);
        header('Content-Type: application/json');
        echo json_encode(['error' => 'TOKEN_EXPIRED']);
        exit;
    }

    if (!hash_equals(hash('sha256', session_id()), (string) ($payload['sid'] ?? ''))) {
        log_reading_event($uid, (int) ($payload['iid'] ?? 0), false, 'hotlink_attempt', 'Session ID mismatch');
        http_response_code(403);
        header('Content-Type: application/json');
        echo json_encode(['error' => 'SESSION_MISMATCH']);
        exit;
    }

    // DB token lookup & single-use atomic consumption
    $tokenHash = hash('sha256', $rawToken);
    $tok = db_one('SELECT * FROM reader_tokens WHERE token_hash = ? AND user_id = ? LIMIT 1', [$tokenHash, $uid]);
    if (!$tok || strtotime($tok['expires_at']) < time()) {
        http_response_code(403);
        header('Content-Type: application/json');
        echo json_encode(['error' => 'TOKEN_EXPIRED']);
        exit;
    }

    if ($tok['used_at'] !== null) {
        log_reading_event($uid, (int) $tok['item_id'], (bool) $tok['is_sample'], 'token_reuse_attempt', 'Attempted reuse of JTI ' . $tok['jti']);
        http_response_code(403);
        header('Content-Type: application/json');
        echo json_encode(['error' => 'TOKEN_REUSED']);
        exit;
    }

    // Single active reading session per account
    if (empty($user['active_session_token']) || !hash_equals((string) $user['active_session_token'], (string) $tok['session_token'])) {
        log_reading_event($uid, (int) $tok['item_id'], (bool) $tok['is_sample'], 'session_kicked', 'Stream blocked by newer session');
        http_response_code(409);
        header('Content-Type: application/json');
        echo json_encode(['error' => 'SESSION_KICKED']);
        exit;
    }

    // Atomically mark token as consumed (prevents parallel reuse)
    $consumed = db_exec('UPDATE reader_tokens SET used_at = NOW() WHERE id = ? AND used_at IS NULL', [(int) $tok['id']]);
    if ($consumed !== 1) {
        log_reading_event($uid, (int) $tok['item_id'], (bool) $tok['is_sample'], 'token_reuse_attempt', 'Concurrent reuse blocked');
        http_response_code(403);
        header('Content-Type: application/json');
        echo json_encode(['error' => 'TOKEN_REUSED']);
        exit;
    }

    $item = db_one('SELECT * FROM items WHERE id = ? AND is_active = 1 LIMIT 1', [(int) $tok['item_id']]);
    if (!$item) {
        http_response_code(404);
        exit;
    }

    $isSample = (int) $tok['is_sample'] === 1;
    $isFree = (bool) $item['is_free'] || (float) $item['price'] <= 0;
    $fallbackAllowed = site_setting('free_pdfjs_fallback', '1') === '1';
    if (!$isFree || !$fallbackAllowed) {
        log_reading_event($uid, (int) $item['id'], $isSample, 'stream_blocked_paid', 'Full PDF stream denied; protected page mode enforced');
        http_response_code(403);
        header('Content-Type: application/json');
        echo json_encode(['error' => 'PROTECTED_PAGES_REQUIRED', 'message' => 'Full PDF delivery is disabled for protected items.']);
        exit;
    }
    $canReadFull = $isFree || user_owns_item($uid, (int) $item['id']);

    if (!$canReadFull && (!$isSample || (int) $item['sample_pages'] <= 0)) {
        http_response_code(403);
        exit;
    }

    log_reading_event($uid, (int) $item['id'], $isSample, 'stream_pdf', 'Token #' . $tok['id']);

    // Server-side Sample Mode Enforcement
    if ($isSample && !$canReadFull) {
        if (!empty($item['sample_drive_link'])) {
            $fetched = fetch_source_pdf_secure((string) $item['sample_drive_link']);
            $pdfBytes = $fetched['ok'] ? $fetched['bytes'] : null;
        } else {
            $fetched = fetch_source_pdf_secure((string) $item['drive_link']);
            if (!$fetched['ok']) {
                http_response_code(502);
                header('Content-Type: application/json');
                echo json_encode(['error' => $fetched['code'], 'message' => $fetched['message']]);
                exit;
            }
            $pdfBytes = slice_pdf_first_pages($fetched['bytes'], (int) $item['sample_pages']);
            if ($pdfBytes === null) {
                http_response_code(422);
                header('Content-Type: application/json');
                echo json_encode(['error' => 'SAMPLE_UNSUPPORTED', 'message' => 'Sample preview requires a dedicated preview PDF for this file.']);
                exit;
            }
        }
    } else {
        $fetched = fetch_source_pdf_secure((string) $item['drive_link']);
        if (!$fetched['ok']) {
            http_response_code(502);
            header('Content-Type: application/json');
            echo json_encode(['error' => $fetched['code'], 'message' => $fetched['message']]);
            exit;
        }
        $pdfBytes = $fetched['bytes'];
    }

    if ($pdfBytes === null) {
        http_response_code(502);
        header('Content-Type: application/json');
        echo json_encode(['error' => 'PDF_UNAVAILABLE', 'message' => 'PDF could not be loaded.']);
        exit;
    }

    header('Content-Type: application/pdf');
    header('Content-Disposition: inline; filename="bookexa-protected.pdf"');
    header('Content-Length: ' . strlen($pdfBytes));
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
    header('Pragma: no-cache');
    header('Expires: 0');
    header('X-Content-Type-Options: nosniff');
    header('X-Robots-Tag: noindex, nofollow');
    echo $pdfBytes;
    exit;
}

function handle_reader_heartbeat(): never
{
    header('Content-Type: application/json');
    header('Cache-Control: no-store');

    $user = current_user();
    if (!$user) {
        http_response_code(401);
        echo json_encode(['ok' => false, 'error' => 'AUTH_REQUIRED']);
        exit;
    }

    $sessionTok = (string) ($_SESSION['active_session_token'] ?? '');
    if (empty($user['active_session_token']) || $sessionTok === '' || !hash_equals((string) $user['active_session_token'], $sessionTok)) {
        http_response_code(409);
        echo json_encode(['ok' => false, 'error' => 'SESSION_KICKED']);
        exit;
    }

    echo json_encode(['ok' => true, 'ts' => time()]);
    exit;
}

function handle_reader_progress(): never
{
    header('Content-Type: application/json');
    $user = current_user();
    if (!$user) {
        http_response_code(401);
        echo json_encode(['ok' => false]);
        exit;
    }
    verify_csrf();
    $uid = (int) $user['id'];
    $itemId = (int) ($_POST['item_id'] ?? 0);
    $isSample = !empty($_POST['is_sample']) && $_POST['is_sample'] !== '0';

    $theme = (string) ($_POST['reader_theme'] ?? '');
    if (in_array($theme, ['dark', 'sepia', 'light'], true)) {
        db_exec('UPDATE users SET reader_theme = ? WHERE id = ?', [$theme, $uid]);
    }
    if (isset($_POST['reader_zoom']) && is_numeric($_POST['reader_zoom'])) {
        $zoom = round(max(0.50, min(3.00, (float) $_POST['reader_zoom'])), 2);
        db_exec('UPDATE users SET reader_zoom = ? WHERE id = ?', [$zoom, $uid]);
    }

    if ($itemId > 0 && !$isSample && isset($_POST['last_page'])) {
        $lastPage = max(1, (int) $_POST['last_page']);
        $totalPages = max(1, (int) ($_POST['total_pages'] ?? 1));
        db_exec(
            'INSERT INTO reading_progress (user_id, item_id, last_page, total_pages) VALUES (?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE last_page = VALUES(last_page), total_pages = VALUES(total_pages)',
            [$uid, $itemId, $lastPage, $totalPages]
        );
    }
    echo json_encode(['ok' => true]);
    exit;
}

/**
 * Resolve TTF font supporting Unicode (Bangla + Latin) or standard Latin for GD watermarking.
 */
function find_watermark_ttf_font(): ?string
{
    $candidates = [
        APP_ROOT . '/assets/fonts/watermark.ttf',
        'C:/Windows/Fonts/Nirmala.ttf',
        'C:/Windows/Fonts/arial.ttf',
        '/usr/share/fonts/truetype/noto/NotoSansBengali-Regular.ttf',
        '/usr/share/fonts/truetype/dejavu/DejaVuSans-Bold.ttf',
        '/usr/share/fonts/truetype/liberation/LiberationSans-Bold.ttf',
    ];
    foreach ($candidates as $path) {
        if (is_file($path)) {
            return $path;
        }
    }
    return null;
}

/**
 * Burn a repeated diagonal per-user watermark into a rendered page image via PHP GD and output WebP/JPEG bytes.
 */
function output_watermarked_page_image(string $srcPath, string $watermarkBase): bool
{
    if (!extension_loaded('gd')) {
        return false;
    }
    $bytes = @file_get_contents($srcPath);
    if ($bytes === false || $bytes === '') {
        return false;
    }
    $im = @imagecreatefromstring($bytes);
    if (!$im) {
        return false;
    }
    imagealphablending($im, true);
    imagesavealpha($im, false);

    $w = imagesx($im);
    $h = imagesy($im);
    $stamp = $watermarkBase . ' • ' . date('Y-m-d H:i');
    $color = imagecolorallocatealpha($im, 180, 42, 32, 102); // ~20% opacity diagonal crimson-rust
    $fontPath = find_watermark_ttf_font();

    if ($fontPath !== null && function_exists('imagettftext')) {
        $fontSize = max(13, min(26, (int) round($w * 0.0155)));
        $stepX = max(320, (int) round($w * 0.42));
        $stepY = max(170, (int) round($h * 0.19));
        for ($y = (int) round($stepY * 0.4); $y <= $h + $stepY; $y += $stepY) {
            $offsetX = ((int) floor($y / $stepY) % 2 === 1) ? (int) round($stepX * 0.35) : 20;
            for ($x = $offsetX; $x <= $w + 200; $x += $stepX) {
                @imagettftext($im, $fontSize, 26, $x, $y, $color, $fontPath, $stamp);
            }
        }
    } else {
        // Fallback rotated bitmap text if server has no TTF files installed
        $asciiStamp = preg_replace('/[^\x20-\x7E]/', '?', $stamp) ?: 'Bookexa Protected';
        $tw = max(180, strlen($asciiStamp) * 9 + 16);
        $th = 24;
        $tile = imagecreatetruecolor($tw, $th);
        imagealphablending($tile, false);
        $trans = imagecolorallocatealpha($tile, 255, 255, 255, 127);
        imagefill($tile, 0, 0, $trans);
        imagesavealpha($tile, true);
        $tc = imagecolorallocatealpha($tile, 180, 42, 32, 92);
        imagestring($tile, 4, 8, 4, $asciiStamp, $tc);
        $rot = @imagerotate($tile, 26, $trans);
        imagedestroy($tile);
        if ($rot) {
            $rw = imagesx($rot);
            $rh = imagesy($rot);
            $stepX = max(260, (int) round($w * 0.40));
            $stepY = max(160, (int) round($h * 0.18));
            for ($y = 20; $y < $h; $y += $stepY) {
                for ($x = 20; $x < $w; $x += $stepX) {
                    imagecopy($im, $rot, $x, $y, 0, 0, $rw, $rh);
                }
            }
            imagedestroy($rot);
        }
    }

    if (function_exists('imagewebp')) {
        header('Content-Type: image/webp');
        header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
        header('Pragma: no-cache');
        header('Expires: 0');
        header('X-Content-Type-Options: nosniff');
        header('X-Robots-Tag: noindex, nofollow');
        $ok = imagewebp($im, null, 82);
    } else {
        header('Content-Type: image/jpeg');
        header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
        header('Pragma: no-cache');
        header('Expires: 0');
        header('X-Content-Type-Options: nosniff');
        header('X-Robots-Tag: noindex, nofollow');
        $ok = imagejpeg($im, null, 84);
    }
    imagedestroy($im);
    return $ok;
}

/**
 * Serve a single dynamically watermarked protected page image (/api/page?item=ID&page=N&token=...).
 */
function handle_protected_page(): never
{
    $user = current_user();
    if (!$user) {
        http_response_code(401);
        header('Content-Type: application/json');
        echo json_encode(['error' => 'AUTH_REQUIRED']);
        exit;
    }

    $uid = (int) $user['id'];
    if ((int) ($user['is_active'] ?? 1) !== 1) {
        http_response_code(403);
        header('Content-Type: application/json');
        echo json_encode(['error' => 'USER_BLOCKED']);
        exit;
    }

    $itemId  = (int) ($_GET['item'] ?? 0);
    $pageNum = (int) ($_GET['page'] ?? 0);

    // Anti-hotlinking & direct browser navigation enforcement
    if (($_SERVER['HTTP_X_BOOKEXA_READER'] ?? '') !== '1') {
        log_reading_event($uid, $itemId, false, 'hotlink_attempt', 'Missing X-Bookexa-Reader header on /api/page');
        http_response_code(403);
        header('Content-Type: application/json');
        echo json_encode(['error' => 'DIRECT_ACCESS_BLOCKED']);
        exit;
    }
    $secFetchSite = strtolower((string) ($_SERVER['HTTP_SEC_FETCH_SITE'] ?? ''));
    if ($secFetchSite !== '' && $secFetchSite !== 'same-origin') {
        log_reading_event($uid, $itemId, false, 'hotlink_attempt', 'Sec-Fetch-Site=' . $secFetchSite);
        http_response_code(403);
        header('Content-Type: application/json');
        echo json_encode(['error' => 'HOTLINK_BLOCKED']);
        exit;
    }
    $referer = (string) ($_SERVER['HTTP_REFERER'] ?? '');
    if ($referer !== '') {
        $refHost = strtolower((string) parse_url($referer, PHP_URL_HOST));
        $srvHost = strtolower((string) ($_SERVER['HTTP_HOST'] ?? ''));
        $srvHostNoPort = explode(':', $srvHost)[0];
        if ($refHost !== '' && $refHost !== $srvHostNoPort) {
            log_reading_event($uid, $itemId, false, 'hotlink_attempt', 'Cross-origin referer on /api/page');
            http_response_code(403);
            header('Content-Type: application/json');
            echo json_encode(['error' => 'HOTLINK_BLOCKED']);
            exit;
        }
    }

    // Strict per-minute page rate limit (max 60 pages / minute)
    if (!user_rate_limit_check('page:user:' . $uid, 60, 60)) {
        log_reading_event($uid, $itemId, false, 'rate_limit_hit', 'Exceeded 60 page requests in 1 minute');
        http_response_code(429);
        header('Content-Type: application/json');
        echo json_encode(['error' => 'RATE_LIMITED', 'message' => 'Too many page requests. Please pause briefly.']);
        exit;
    }

    // Signed token verification
    $rawToken = (string) ($_GET['token'] ?? '');
    if (!preg_match('/^([A-Za-z0-9_-]+)\.([a-f0-9]{64})$/', $rawToken, $tm)) {
        log_reading_event($uid, $itemId, false, 'repeated_token_failures', 'Malformed page token');
        http_response_code(403);
        header('Content-Type: application/json');
        echo json_encode(['error' => 'INVALID_TOKEN']);
        exit;
    }
    $secret = (string) app_config('security.app_secret', 'bookexa_fallback_secret');
    $expectedSig = hash_hmac('sha256', $tm[1], $secret);
    if (!hash_equals($expectedSig, $tm[2])) {
        log_reading_event($uid, $itemId, false, 'repeated_token_failures', 'HMAC signature mismatch on /api/page');
        http_response_code(403);
        header('Content-Type: application/json');
        echo json_encode(['error' => 'INVALID_SIGNATURE']);
        exit;
    }

    $payloadJson = b64url_decode($tm[1]);
    $payload = $payloadJson !== null ? json_decode($payloadJson, true) : null;
    if (
        !is_array($payload)
        || (int) ($payload['uid'] ?? 0) !== $uid
        || (int) ($payload['iid'] ?? 0) !== $itemId
        || ($payload['mod'] ?? '') !== 'pages'
        || (int) ($payload['exp'] ?? 0) < time()
    ) {
        http_response_code(403);
        header('Content-Type: application/json');
        echo json_encode(['error' => 'TOKEN_EXPIRED']);
        exit;
    }
    if (!hash_equals(hash('sha256', session_id()), (string) ($payload['sid'] ?? ''))) {
        log_reading_event($uid, $itemId, false, 'hotlink_attempt', 'Session ID mismatch on /api/page');
        http_response_code(403);
        header('Content-Type: application/json');
        echo json_encode(['error' => 'SESSION_MISMATCH']);
        exit;
    }

    $tokenHash = hash('sha256', $rawToken);
    $tok = db_one('SELECT * FROM reader_tokens WHERE token_hash = ? AND user_id = ? AND item_id = ? LIMIT 1', [$tokenHash, $uid, $itemId]);
    if (!$tok || strtotime($tok['expires_at']) < time()) {
        http_response_code(403);
        header('Content-Type: application/json');
        echo json_encode(['error' => 'TOKEN_EXPIRED']);
        exit;
    }

    // Single active reading session per account
    if (empty($user['active_session_token']) || !hash_equals((string) $user['active_session_token'], (string) $tok['session_token'])) {
        log_reading_event($uid, $itemId, (bool) $tok['is_sample'], 'session_kicked', 'Page access blocked by newer session');
        http_response_code(409);
        header('Content-Type: application/json');
        echo json_encode(['error' => 'SESSION_KICKED']);
        exit;
    }

    $item = db_one('SELECT * FROM items WHERE id = ? AND is_active = 1 LIMIT 1', [$itemId]);
    if (!$item || ($item['pages_status'] ?? 'unprocessed') !== 'ready') {
        http_response_code(404);
        header('Content-Type: application/json');
        echo json_encode(['error' => 'PAGES_NOT_READY']);
        exit;
    }

    $isSample = (int) $tok['is_sample'] === 1;
    $isFree = (bool) $item['is_free'] || (float) $item['price'] <= 0;
    $canReadFull = $isFree || user_owns_item($uid, $itemId);

    if (!$canReadFull && (!$isSample || (int) $item['sample_pages'] <= 0)) {
        http_response_code(403);
        header('Content-Type: application/json');
        echo json_encode(['error' => 'ACCESS_DENIED']);
        exit;
    }

    $preparedCount = count_prepared_pages($itemId);
    $maxAllowed = (!$canReadFull && $isSample)
        ? min($preparedCount, (int) $item['sample_pages'])
        : $preparedCount;

    if ($pageNum < 1 || $pageNum > $maxAllowed) {
        log_reading_event($uid, $itemId, $isSample, 'page_out_of_range', "Requested page={$pageNum} (maxAllowed={$maxAllowed})");
        http_response_code(403);
        header('Content-Type: application/json');
        echo json_encode(['error' => 'PAGE_OUT_OF_RANGE']);
        exit;
    }

    $srcFile = find_prepared_page_file($itemId, $pageNum);
    if ($srcFile === null) {
        http_response_code(404);
        header('Content-Type: application/json');
        echo json_encode(['error' => 'PAGE_NOT_FOUND']);
        exit;
    }

    // Track abnormally fast sequential page bursts
    track_fast_sequential_burst($uid, $itemId, $pageNum);

    $contact = !empty($user['phone']) ? $user['phone'] : $user['email'];
    $watermarkBase = $user['name'] . ' • ' . $contact . ' • ID #' . $uid;

    if (!output_watermarked_page_image($srcFile, $watermarkBase)) {
        http_response_code(500);
        header('Content-Type: application/json');
        echo json_encode(['error' => 'WATERMARK_RENDER_ERROR', 'message' => 'Server image rendering failed (PHP GD required).']);
    }
    exit;
}

function handle_reader_security_event(): never
{
    header('Content-Type: application/json');
    $user = current_user();
    if (!$user) {
        http_response_code(401);
        exit;
    }
    verify_csrf();
    $itemId = (int) ($_POST['item_id'] ?? 0);
    $event  = trim((string) ($_POST['event'] ?? 'suspicious_client'));
    $allowed = ['devtools_resize', 'print_attempt', 'dom_tamper', 'suspicious_client'];
    if (!in_array($event, $allowed, true)) {
        $event = 'suspicious_client';
    }
    log_reading_event((int) $user['id'], $itemId, false, $event, 'Client deterrence trigger');
    echo json_encode(['ok' => true]);
    exit;
}
