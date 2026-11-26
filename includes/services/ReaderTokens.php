<?php
declare(strict_types=1);

/**
 * Bookexa Service: ReaderTokens
 */
final class ReaderTokens
{
    public static function b64urlEncode(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    public static function b64urlDecode(string $data): ?string
    {
        $pad = strlen($data) % 4;
        if ($pad > 0) {
            $data .= str_repeat('=', 4 - $pad);
        }
        $decoded = base64_decode(strtr($data, '-_', '+/'), true);
        return $decoded !== false ? $decoded : null;
    }

    /**
     * Issues a short-lived HMAC-signed token bound to user, PHP session, active reading session, mode, and item.
     */
    public static function issue(int $userId, int $itemId, bool $isSample, string $sessionToken, string $mode = 'pages', int $ttlSeconds = 900): string
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
        $payloadB64 = ReaderTokens::b64urlEncode((string) json_encode($payload));
        $secret = app_secret();
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
     * Verify HMAC signature, session binding, and DB expiry for a reader pages token.
     */
    public static function verify(string $rawToken, int $uid, int $itemId): ?array
    {
        if (!preg_match('/^([A-Za-z0-9_-]+)\.([a-f0-9]{64})$/', $rawToken, $tm)) {
            return null;
        }
        $secret = app_secret();
        $expectedSig = hash_hmac('sha256', $tm[1], $secret);
        if (!hash_equals($expectedSig, $tm[2])) {
            return null;
        }
        $payloadJson = ReaderTokens::b64urlDecode($tm[1]);
        $payload = $payloadJson !== null ? json_decode($payloadJson, true) : null;
        if (
            !is_array($payload)
            || (int) ($payload['uid'] ?? 0) !== $uid
            || (int) ($payload['iid'] ?? 0) !== $itemId
            || ($payload['mod'] ?? '') !== 'pages'
            || (int) ($payload['exp'] ?? 0) < time()
        ) {
            return null;
        }
        if (!hash_equals(hash('sha256', session_id()), (string) ($payload['sid'] ?? ''))) {
            return null;
        }
        $tok = db_one(
            'SELECT * FROM reader_tokens WHERE token_hash = ? AND user_id = ? AND item_id = ? LIMIT 1',
            [hash('sha256', $rawToken), $uid, $itemId]
        );
        if (!$tok || strtotime((string) $tok['expires_at']) < time()) {
            return null;
        }
        return $tok;
    }

    public static function logReadingEvent(int $userId, ?int $itemId, bool $isSample, string $action, ?string $details = null): void
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

    public static function userSuspensionMessage(string $untilDatetime): string
    {
        $mins = max(1, (int) ceil((strtotime($untilDatetime) - time()) / 60));
        return current_lang() === 'bn'
            ? "সন্দেহজনক কার্যকলাপের কারণে আপনার পাঠ সুবিধা সাময়িকভাবে {$mins} মিনিটের জন্য স্থগিত করা হয়েছে। অনুগ্রহ করে পরে আবার চেষ্টা করুন।"
            : "Reading access is temporarily paused for {$mins} minute(s) due to unusual activity. Please try again later.";
    }

    public static function notifyAdminSuspension(int $userId, int $anomalyCount, string $lastAction): void
    {
        $u = db_one('SELECT name, email, phone FROM users WHERE id = ? LIMIT 1', [$userId]);
        $ident = $u ? ($u['name'] . ' (' . ($u['email'] ?: ($u['phone'] ?? "ID #{$userId}")) . ')') : "User #{$userId}";
        $msg = "Auto-suspended reading for 30m after {$anomalyCount} anomalies (latest: {$lastAction})";
        try {
            db_exec(
                'INSERT INTO security_logs (event_type, identifier, ip_address, details) VALUES (?, ?, ?, ?)',
                ['reader_auto_suspended', substr($ident, 0, 190), client_ip(), substr($msg, 0, 250)]
            );
        } catch (Throwable $e) {
        }
        $adminEmail = trim((string) (site_setting('contact_email', '') ?: site_setting('smtp_from_email', '')));
        if ($adminEmail !== '' && filter_var($adminEmail, FILTER_VALIDATE_EMAIL)) {
            require_once APP_ROOT . '/includes/mailer.php';
            try {
                send_bookexa_template_mail(
                    $adminEmail,
                    'Bookexa Security Alert: Reader Auto-Suspended',
                    'Reader Reading Access Paused (30 Minutes)',
                    '<p>User <strong>' . e($ident) . '</strong> was automatically suspended from reading for 30 minutes after ' . $anomalyCount . ' anomalies in 10 minutes (latest: <code>' . e($lastAction) . '</code>).</p>',
                    BASE_URL . '/admin/logs?type=suspicious',
                    'Inspect Security Logs'
                );
            } catch (Throwable $e) {
            }
        }
    }

    public static function recordAnomaly(int $userId, ?int $itemId, bool $isSample, string $action, string $details): bool
    {
        ReaderTokens::logReadingEvent($userId, $itemId, $isSample, $action, $details);
        if (!in_array($action, ['fast_sequential_burst', 'many_sessions_anomaly', 'page_out_of_range', 'search_daily_quota_exceeded'], true)) {
            return false;
        }
        try {
            $cnt = (int) (db_one(
                "SELECT COUNT(*) AS c FROM reading_logs
                 WHERE user_id = ?
                   AND action IN ('fast_sequential_burst', 'many_sessions_anomaly', 'page_out_of_range', 'search_daily_quota_exceeded')
                   AND created_at >= DATE_SUB(NOW(), INTERVAL 10 MINUTE)",
                [$userId]
            )['c'] ?? 0);
            if ($cnt > 3) {
                $uRow = db_one('SELECT reading_suspended_until FROM users WHERE id = ? LIMIT 1', [$userId]);
                $alreadySuspended = $uRow && !empty($uRow['reading_suspended_until']) && strtotime((string) $uRow['reading_suspended_until']) > (time() + 1500);
                db_exec('UPDATE users SET reading_suspended_until = DATE_ADD(NOW(), INTERVAL 30 MINUTE) WHERE id = ?', [$userId]);
                if (!$alreadySuspended) {
                    ReaderTokens::logReadingEvent($userId, $itemId, $isSample, 'reading_suspended', "Auto-suspended 30m after {$cnt} anomalies in 10m");
                    ReaderTokens::notifyAdminSuspension($userId, $cnt, $action);
                }
                return true;
            }
        } catch (Throwable $e) {
        }
        return false;
    }

    /**
     * Detect abnormal rapid multi-session switching within the last hour.
     */
    public static function detectManySessionsAnomaly(int $userId): void
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
                    ReaderTokens::recordAnomaly($userId, null, false, 'many_sessions_anomaly', "opens={$opens}/h, kicks={$kicks}/h");
                }
            }
        } catch (Throwable $e) {
        }
    }

    /**
     * Detect abnormally fast sequential page scraping bursts in session.
     */
    public static function trackFastSequentialBurst(int $userId, int $itemId, int $pageNum): void
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
            ReaderTokens::recordAnomaly($userId, $itemId, false, 'fast_sequential_burst', "{$distinctPages} distinct pages fetched within 10s");
            $lastAlert = $now;
        }
        $_SESSION['bx_page_burst'][$itemId] = ['events' => $events, 'last_alert' => $lastAlert];
    }
}
