<?php
declare(strict_types=1);

/**
 * Bookexa Service: PageServer
 */
final class PageServer
{
    public static function storageDir(int $itemId): string
    {
        return APP_ROOT . '/storage/pages/' . $itemId;
    }

    public static function stagingDir(int $itemId): string
    {
        return APP_ROOT . '/storage/pages/' . $itemId . '_new';
    }

    public static function rrmdirRecursive(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }
        $it = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST
        );
        foreach ($it as $entry) {
            $entry->isDir() ? @rmdir($entry->getPathname()) : @unlink($entry->getPathname());
        }
        @rmdir($dir);
    }

    public static function findStagingPageFile(int $itemId, int $pageNum): ?string
    {
        $dir = PageServer::stagingDir($itemId);
        foreach (['webp', 'jpg'] as $ext) {
            $p = $dir . '/page-' . $pageNum . '.' . $ext;
            if (is_file($p)) {
                return $p;
            }
        }
        return null;
    }

    public static function atomicSwapPreparedPages(int $itemId): void
    {
        $stagingDir = PageServer::stagingDir($itemId);
        $liveDir    = PageServer::storageDir($itemId);
        $oldDir     = APP_ROOT . '/storage/pages/' . $itemId . '_old_' . bin2hex(random_bytes(3));
        if (!is_dir($stagingDir)) {
            throw new RuntimeException('Staging directory missing.');
        }
        $hadLive = is_dir($liveDir);
        if ($hadLive && !@rename($liveDir, $oldDir)) {
            throw new RuntimeException('Could not prepare live folder swap.');
        }
        if (!@rename($stagingDir, $liveDir)) {
            if ($hadLive && is_dir($oldDir)) {
                @rename($oldDir, $liveDir);
            }
            throw new RuntimeException('Atomic page directory swap failed.');
        }
        if ($hadLive && is_dir($oldDir)) {
            PageServer::rrmdirRecursive($oldDir);
        }
    }

    public static function ensurePagesHtaccess(): void
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

    public static function findPreparedPageFile(int $itemId, int $pageNum): ?string
    {
        $dir = PageServer::storageDir($itemId);
        foreach (['webp', 'jpg'] as $ext) {
            $p = $dir . '/page-' . $pageNum . '.' . $ext;
            if (is_file($p)) {
                return $p;
            }
        }
        return null;
    }

    public static function countPreparedPages(int $itemId): int
    {
        $dir = PageServer::storageDir($itemId);
        if (!is_dir($dir)) {
            return 0;
        }
        $n = 0;
        while (PageServer::findPreparedPageFile($itemId, $n + 1) !== null) {
            $n++;
        }
        return $n;
    }

    /**
     * Lock-file counter guard limiting concurrent /api/page GD renders across shared-hosting workers.
     */
    public static function acquireRenderSlot(?int $limitOverride = null): ?string
    {
        $limit = $limitOverride ?? max(1, min(64, (int) site_setting('max_concurrent_page_renders', '8')));
        $lockDir = Watermarker::ensureCacheSubdir('locks');
        $lockPath = $lockDir . '/render_busy.lock';
        $fh = @fopen($lockPath, 'c+b');
        if (!$fh) {
            return 'unmanaged';
        }
        if (!@flock($fh, LOCK_EX)) {
            @fclose($fh);
            return 'unmanaged';
        }

        $raw = stream_get_contents($fh);
        $active = $raw ? json_decode($raw, true) : [];
        if (!is_array($active)) {
            $active = [];
        }
        $now = microtime(true);
        // Prune stale slots > 25s old in case a PHP worker crashed unexpectedly
        foreach ($active as $tok => $ts) {
            if (!is_numeric($ts) || ($now - (float) $ts) > 25.0) {
                unset($active[$tok]);
            }
        }
        if (count($active) >= $limit) {
            @flock($fh, LOCK_UN);
            @fclose($fh);
            return null;
        }

        $token = bin2hex(random_bytes(8));
        $active[$token] = $now;
        rewind($fh);
        ftruncate($fh, 0);
        fwrite($fh, (string) json_encode($active));
        fflush($fh);
        @flock($fh, LOCK_UN);
        @fclose($fh);

        return $token;
    }

    public static function releaseRenderSlot(?string $token): void
    {
        if ($token === null || $token === '' || $token === 'unmanaged') {
            return;
        }
        $lockPath = APP_ROOT . '/storage/cache/locks/render_busy.lock';
        $fh = @fopen($lockPath, 'c+b');
        if (!$fh) {
            return;
        }
        if (@flock($fh, LOCK_EX)) {
            $raw = stream_get_contents($fh);
            $active = $raw ? json_decode($raw, true) : [];
            if (is_array($active) && isset($active[$token])) {
                unset($active[$token]);
                rewind($fh);
                ftruncate($fh, 0);
                fwrite($fh, (string) json_encode($active));
                fflush($fh);
            }
            @flock($fh, LOCK_UN);
        }
        @fclose($fh);
    }

    /**
     * Resolve optional pre-scaled <=1000px cached clean page variant for small phone screens.
     */
    public static function resolveVariantSource(string $srcPath, int $itemId, int $pageNum, int $maxWidth): string
    {
        if ($maxWidth <= 0 || $maxWidth > 1000 || $itemId <= 0 || $pageNum <= 0 || !extension_loaded('gd')) {
            return $srcPath;
        }
        $info = @getimagesize($srcPath);
        if (!$info || (int) $info[0] <= $maxWidth) {
            return $srcPath;
        }
        $varDir = Watermarker::ensureCacheSubdir('variants/' . $itemId);
        $ext = function_exists('imagewebp') ? 'webp' : 'jpg';
        $varPath = $varDir . '/p' . $pageNum . '_w' . $maxWidth . '.' . $ext;
        if (is_file($varPath) && (@filemtime($varPath) ?: 0) >= (@filemtime($srcPath) ?: 0) && (@filesize($varPath) ?: 0) > 128) {
            return $varPath;
        }

        $bytes = @file_get_contents($srcPath);
        $im = ($bytes !== false && $bytes !== '') ? @imagecreatefromstring($bytes) : false;
        if (!$im) {
            return $srcPath;
        }
        $scaled = @imagescale($im, $maxWidth, -1, IMG_BILINEAR_FIXED);
        imagedestroy($im);
        if (!$scaled) {
            return $srcPath;
        }
        $tmp = $varPath . '.' . bin2hex(random_bytes(6)) . '.tmp';
        $saved = $ext === 'webp' ? @imagewebp($scaled, $tmp, 82) : @imagejpeg($scaled, $tmp, 85);
        imagedestroy($scaled);
        if ($saved && @rename($tmp, $varPath)) {
            return $varPath;
        }
        @unlink($tmp);
        return (is_file($varPath) && (@filesize($varPath) ?: 0) > 128) ? $varPath : $srcPath;
    }

    /**
     * Streams a local PDF file to the client in 64 KB chunks with HTTP Range (206 Partial Content) support.
     */
    public static function streamPdfFileResponse(string $filePath, bool $unlinkAfter = false): never
    {
        @set_time_limit(0);
        while (ob_get_level() > 0) {
            @ob_end_clean();
        }

        $size = (int) @filesize($filePath);
        $start = 0;
        $end = max(0, $size - 1);
        $isPartial = false;

        $rangeHdr = trim((string) ($_SERVER['HTTP_RANGE'] ?? ''));
        if ($rangeHdr !== '' && $size > 0) {
            if (!preg_match('/^bytes=(\d*)-(\d*)$/i', $rangeHdr, $rm)) {
                http_response_code(416);
                header('Content-Range: bytes */' . $size);
                if ($unlinkAfter) {
                    @unlink($filePath);
                }
                exit;
            }
            if ($rm[1] === '' && $rm[2] === '') {
                http_response_code(416);
                header('Content-Range: bytes */' . $size);
                if ($unlinkAfter) {
                    @unlink($filePath);
                }
                exit;
            }
            if ($rm[1] === '') {
                // Suffix range: bytes=-N
                $suffix = (int) $rm[2];
                $start = max(0, $size - $suffix);
                $end = $size - 1;
            } else {
                $start = (int) $rm[1];
                $end = ($rm[2] !== '') ? min($size - 1, (int) $rm[2]) : ($size - 1);
            }
            if ($start > $end || $start >= $size || $end < 0) {
                http_response_code(416);
                header('Content-Range: bytes */' . $size);
                if ($unlinkAfter) {
                    @unlink($filePath);
                }
                exit;
            }
            $isPartial = true;
        }

        $length = $size > 0 ? ($end - $start + 1) : 0;

        if ($isPartial) {
            http_response_code(206);
            header('Content-Range: bytes ' . $start . '-' . $end . '/' . $size);
        } else {
            http_response_code(200);
        }

        header('Content-Type: application/pdf');
        header('Content-Disposition: inline; filename="bookexa-protected.pdf"');
        header('Accept-Ranges: bytes');
        header('Content-Length: ' . $length);
        header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
        header('Pragma: no-cache');
        header('Expires: 0');
        header('X-Content-Type-Options: nosniff');
        header('X-Robots-Tag: noindex, nofollow');

        $fp = @fopen($filePath, 'rb');
        if ($fp) {
            if ($start > 0) {
                fseek($fp, $start);
            }
            $remaining = $length;
            $chunkSize = 65536; // 64 KB chunks — constant low PHP memory
            while ($remaining > 0 && !feof($fp)) {
                $toRead = min($chunkSize, $remaining);
                $buf = fread($fp, $toRead);
                if ($buf === false || $buf === '') {
                    break;
                }
                echo $buf;
                $remaining -= strlen($buf);
                flush();
                if (connection_aborted() !== 0) {
                    break;
                }
            }
            fclose($fp);
        }

        if ($unlinkAfter) {
            @unlink($filePath);
        }
        exit;
    }

    /**
     * Pure-PHP Server-Side PDF Page Slicer for Free Sample Enforcement.
     */
    public static function slicePdfFirstPages(string $pdfBytes, int $maxPages): ?string
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

    public static function userReadingStats(int $userId): array
    {
        if ($userId <= 0) {
            return ['pages_read' => 0, 'books_finished' => 0, 'time_spent_sec' => 0];
        }
        $row = db_one(
            'SELECT COALESCE(SUM(last_page), 0) AS pages_read,
                    COALESCE(SUM(CASE WHEN is_finished = 1 OR (total_pages > 0 AND last_page >= total_pages) THEN 1 ELSE 0 END), 0) AS books_finished,
                    COALESCE(SUM(time_spent_sec), 0) AS time_spent_sec
             FROM reading_progress
             WHERE user_id = ?',
            [$userId]
        );
        return [
            'pages_read'     => (int) ($row['pages_read'] ?? 0),
            'books_finished' => (int) ($row['books_finished'] ?? 0),
            'time_spent_sec' => (int) ($row['time_spent_sec'] ?? 0),
        ];
    }

    public static function handleStreamPdf(): never
    {
        $user = current_user();
        if (!$user) {
            http_response_code(401);
            header('Content-Type: application/json');
            echo json_encode(['error' => 'AUTH_REQUIRED']);
            exit;
        }

        $uid = (int) $user['id'];
        if (!empty($user['reading_suspended_until']) && strtotime((string) $user['reading_suspended_until']) > time()) {
            http_response_code(403);
            header('Content-Type: application/json');
            echo json_encode(['error' => 'READING_SUSPENDED', 'message' => ReaderTokens::userSuspensionMessage((string) $user['reading_suspended_until'])]);
            exit;
        }

        // Anti-hotlinking & direct browser navigation check
        if (($_SERVER['HTTP_X_BOOKEXA_READER'] ?? '') !== '1') {
            ReaderTokens::logReadingEvent($uid, 0, false, 'hotlink_attempt', 'Missing X-Bookexa-Reader header');
            http_response_code(403);
            header('Content-Type: application/json');
            echo json_encode(['error' => 'DIRECT_ACCESS_BLOCKED']);
            exit;
        }
        $secFetchSite = strtolower((string) ($_SERVER['HTTP_SEC_FETCH_SITE'] ?? ''));
        if ($secFetchSite !== '' && $secFetchSite !== 'same-origin') {
            ReaderTokens::logReadingEvent($uid, 0, false, 'hotlink_attempt', 'Sec-Fetch-Site=' . $secFetchSite);
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
                ReaderTokens::logReadingEvent($uid, 0, false, 'hotlink_attempt', 'Cross-origin referer');
                http_response_code(403);
                header('Content-Type: application/json');
                echo json_encode(['error' => 'HOTLINK_BLOCKED']);
                exit;
            }
        }

        // Rate limiting (track & log spikes)
        if (!user_rate_limit_check('stream:user:' . $uid, 30, 300)) {
            ReaderTokens::logReadingEvent($uid, 0, false, 'rate_limit_hit', 'Exceeded 30 stream requests in 5 mins');
            http_response_code(429);
            header('Content-Type: application/json');
            echo json_encode(['error' => 'RATE_LIMITED']);
            exit;
        }

        // Verify signed token format & HMAC signature
        $rawToken = (string) ($_GET['token'] ?? '');
        if (!preg_match('/^([A-Za-z0-9_-]+)\.([a-f0-9]{64})$/', $rawToken, $tm)) {
            if (!user_rate_limit_check('tok_fail:user:' . $uid, 5, 600)) {
                ReaderTokens::logReadingEvent($uid, 0, false, 'repeated_token_failures', 'Malformed token');
            }
            http_response_code(403);
            header('Content-Type: application/json');
            echo json_encode(['error' => 'INVALID_TOKEN']);
            exit;
        }

        $secret = app_secret();
        $expectedSig = hash_hmac('sha256', $tm[1], $secret);
        if (!hash_equals($expectedSig, $tm[2])) {
            ReaderTokens::logReadingEvent($uid, 0, false, 'repeated_token_failures', 'HMAC signature mismatch');
            http_response_code(403);
            header('Content-Type: application/json');
            echo json_encode(['error' => 'INVALID_SIGNATURE']);
            exit;
        }

        $payloadJson = ReaderTokens::b64urlDecode($tm[1]);
        $payload = $payloadJson !== null ? json_decode($payloadJson, true) : null;
        if (!is_array($payload) || (int) ($payload['uid'] ?? 0) !== $uid || (int) ($payload['exp'] ?? 0) < time()) {
            http_response_code(403);
            header('Content-Type: application/json');
            echo json_encode(['error' => 'TOKEN_EXPIRED']);
            exit;
        }

        if (!hash_equals(hash('sha256', session_id()), (string) ($payload['sid'] ?? ''))) {
            ReaderTokens::logReadingEvent($uid, (int) ($payload['iid'] ?? 0), false, 'hotlink_attempt', 'Session ID mismatch');
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

        $rangeHdr = trim((string) ($_SERVER['HTTP_RANGE'] ?? ''));
        $isRangeReq = $rangeHdr !== '';
        $isRangeSession = $isRangeReq && !empty($_SESSION['bx_range_tokens'][$tokenHash]);

        if ($tok['used_at'] !== null && !$isRangeSession) {
            ReaderTokens::logReadingEvent($uid, (int) $tok['item_id'], (bool) $tok['is_sample'], 'token_reuse_attempt', 'Attempted reuse of JTI ' . $tok['jti']);
            http_response_code(403);
            header('Content-Type: application/json');
            echo json_encode(['error' => 'TOKEN_REUSED']);
            exit;
        }

        // Single active reading session per account
        if (empty($user['active_session_token']) || !hash_equals((string) $user['active_session_token'], (string) $tok['session_token'])) {
            ReaderTokens::logReadingEvent($uid, (int) $tok['item_id'], (bool) $tok['is_sample'], 'session_kicked', 'Stream blocked by newer session');
            http_response_code(409);
            header('Content-Type: application/json');
            echo json_encode(['error' => 'SESSION_KICKED']);
            exit;
        }

        // Atomically mark token as consumed (non-Range requests are strictly single-use; Range chunks share the session window)
        if ($tok['used_at'] === null) {
            $consumed = db_exec('UPDATE reader_tokens SET used_at = NOW() WHERE id = ? AND used_at IS NULL', [(int) $tok['id']]);
            if ($consumed !== 1 && !$isRangeSession) {
                ReaderTokens::logReadingEvent($uid, (int) $tok['item_id'], (bool) $tok['is_sample'], 'token_reuse_attempt', 'Concurrent reuse blocked');
                http_response_code(403);
                header('Content-Type: application/json');
                echo json_encode(['error' => 'TOKEN_REUSED']);
                exit;
            }
            if ($isRangeReq) {
                $_SESSION['bx_range_tokens'][$tokenHash] = time();
            }
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
            ReaderTokens::logReadingEvent($uid, (int) $item['id'], $isSample, 'stream_blocked_paid', 'Full PDF stream denied; protected page mode enforced');
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

        ReaderTokens::logReadingEvent($uid, (int) $item['id'], $isSample, 'stream_pdf', 'Token #' . $tok['id']);

        // Server-side Sample Mode Enforcement (never loads full huge files into RAM)
        $srcPath = null;
        $unlinkAfter = false;
        if ($isSample && !$canReadFull) {
            if (!empty($item['sample_drive_link'])) {
                $fetched = DriveFetcher::fetchToFile((string) $item['sample_drive_link']);
                if (!$fetched['ok']) {
                    DriveFetcher::logSourceAdminError($uid, (int) $item['id'], $isSample, (string) ($fetched['admin_reason'] ?? $fetched['message'] ?? 'Sample source error'));
                    http_response_code(502);
                    header('Content-Type: application/json');
                    echo json_encode(['error' => $fetched['code'] ?? 'PDF_UNAVAILABLE', 'message' => $fetched['message'] ?? 'Could not load document preview right now.']);
                    exit;
                }
                $srcPath = $fetched['path'];
                $unlinkAfter = !empty($fetched['unlink_after']);
            } else {
                $fetched = DriveFetcher::fetchToFile((string) ($item['drive_link'] ?? ''));
                if (!$fetched['ok']) {
                    DriveFetcher::logSourceAdminError($uid, (int) $item['id'], $isSample, (string) ($fetched['admin_reason'] ?? $fetched['message'] ?? 'Source fetch error'));
                    http_response_code(502);
                    header('Content-Type: application/json');
                    echo json_encode(['error' => $fetched['code'] ?? 'PDF_UNAVAILABLE', 'message' => $fetched['message'] ?? 'Could not load document preview right now.']);
                    exit;
                }
                $fileSize = (int) ($fetched['size'] ?? @filesize($fetched['path']));
                if ($fileSize > 24 * 1024 * 1024) {
                    if (!empty($fetched['unlink_after'])) {
                        @unlink($fetched['path']);
                    }
                    DriveFetcher::logSourceAdminError($uid, (int) $item['id'], $isSample, 'Sample slice skipped cleanly: source file exceeds 24 MB memory-safe slice cap (' . round($fileSize / 1048576, 1) . ' MB)');
                    http_response_code(422);
                    header('Content-Type: application/json');
                    echo json_encode(['error' => 'SAMPLE_UNSUPPORTED', 'message' => 'Sample preview requires a dedicated preview PDF for this file.']);
                    exit;
                }
                $rawBytes = @file_get_contents($fetched['path']);
                if (!empty($fetched['unlink_after'])) {
                    @unlink($fetched['path']);
                }
                $pdfBytes = $rawBytes !== false ? PageServer::slicePdfFirstPages($rawBytes, (int) $item['sample_pages']) : null;
                if ($pdfBytes === null) {
                    http_response_code(422);
                    header('Content-Type: application/json');
                    echo json_encode(['error' => 'SAMPLE_UNSUPPORTED', 'message' => 'Sample preview requires a dedicated preview PDF for this file.']);
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
        } else {
            $fetched = DriveFetcher::fetchToFile((string) ($item['drive_link'] ?? ''));
            if (!$fetched['ok']) {
                DriveFetcher::logSourceAdminError($uid, (int) $item['id'], $isSample, (string) ($fetched['admin_reason'] ?? $fetched['message'] ?? 'Source fetch error'));
                http_response_code(502);
                header('Content-Type: application/json');
                echo json_encode(['error' => $fetched['code'] ?? 'PDF_UNAVAILABLE', 'message' => $fetched['message'] ?? 'Could not open this document right now. Please try again shortly.']);
                exit;
            }
            $srcPath = $fetched['path'];
            $unlinkAfter = !empty($fetched['unlink_after']);
        }

        if (!$srcPath || !is_file($srcPath)) {
            http_response_code(502);
            header('Content-Type: application/json');
            echo json_encode(['error' => 'PDF_UNAVAILABLE', 'message' => 'This document is temporarily unavailable.']);
            exit;
        }

        PageServer::streamPdfFileResponse($srcPath, $unlinkAfter);
    }

    public static function handleHeartbeat(): never
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

    public static function handleProgress(): never
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

        if ($itemId > 0 && !$isSample) {
            $deltaSec = min(120, max(0, (int) ($_POST['delta_sec'] ?? 0)));
            if (isset($_POST['last_page']) || isset($_POST['page'])) {
                $lastPage   = max(1, (int) ($_POST['last_page'] ?? $_POST['page']));
                $totalPages = max(1, (int) ($_POST['total_pages'] ?? 1));
                $isFin      = ($lastPage >= $totalPages) ? 1 : 0;
                db_exec(
                    'INSERT INTO reading_progress (user_id, item_id, last_page, total_pages, time_spent_sec, is_finished)
                     VALUES (?, ?, ?, ?, ?, ?)
                     ON DUPLICATE KEY UPDATE
                         last_page = VALUES(last_page),
                         total_pages = VALUES(total_pages),
                         time_spent_sec = time_spent_sec + VALUES(time_spent_sec),
                         is_finished = GREATEST(is_finished, VALUES(is_finished))',
                    [$uid, $itemId, $lastPage, $totalPages, $deltaSec, $isFin]
                );
            } elseif ($deltaSec > 0) {
                db_exec(
                    'UPDATE reading_progress SET time_spent_sec = time_spent_sec + ? WHERE user_id = ? AND item_id = ?',
                    [$deltaSec, $uid, $itemId]
                );
            }
        }
        echo json_encode(['ok' => true]);
        exit;
    }

    /**
     * Serve a single dynamically watermarked protected page image (/api/page?item=ID&page=N&token=...).
     */
    public static function handleProtectedPage(): never
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
        if (!empty($user['reading_suspended_until']) && strtotime((string) $user['reading_suspended_until']) > time()) {
            http_response_code(403);
            header('Content-Type: application/json');
            echo json_encode(['error' => 'READING_SUSPENDED', 'message' => ReaderTokens::userSuspensionMessage((string) $user['reading_suspended_until'])]);
            exit;
        }

        $itemId  = (int) ($_GET['item'] ?? 0);
        $pageNum = (int) ($_GET['page'] ?? 0);

        // Anti-hotlinking & direct browser navigation enforcement
        if (($_SERVER['HTTP_X_BOOKEXA_READER'] ?? '') !== '1') {
            ReaderTokens::logReadingEvent($uid, $itemId, false, 'hotlink_attempt', 'Missing X-Bookexa-Reader header on /api/page');
            http_response_code(403);
            header('Content-Type: application/json');
            echo json_encode(['error' => 'DIRECT_ACCESS_BLOCKED']);
            exit;
        }
        $secFetchSite = strtolower((string) ($_SERVER['HTTP_SEC_FETCH_SITE'] ?? ''));
        if ($secFetchSite !== '' && $secFetchSite !== 'same-origin') {
            ReaderTokens::logReadingEvent($uid, $itemId, false, 'hotlink_attempt', 'Sec-Fetch-Site=' . $secFetchSite);
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
                ReaderTokens::logReadingEvent($uid, $itemId, false, 'hotlink_attempt', 'Cross-origin referer on /api/page');
                http_response_code(403);
                header('Content-Type: application/json');
                echo json_encode(['error' => 'HOTLINK_BLOCKED']);
                exit;
            }
        }

        // Strict per-minute page rate limit (max 60 pages / minute)
        if (!user_rate_limit_check('page:user:' . $uid, 60, 60)) {
            ReaderTokens::logReadingEvent($uid, $itemId, false, 'rate_limit_hit', 'Exceeded 60 page requests in 1 minute');
            http_response_code(429);
            header('Content-Type: application/json');
            echo json_encode(['error' => 'RATE_LIMITED', 'message' => 'Too many page requests. Please pause briefly.']);
            exit;
        }

        // Signed token verification
        $rawToken = (string) ($_GET['token'] ?? '');
        if (!preg_match('/^([A-Za-z0-9_-]+)\.([a-f0-9]{64})$/', $rawToken, $tm)) {
            ReaderTokens::logReadingEvent($uid, $itemId, false, 'repeated_token_failures', 'Malformed page token');
            http_response_code(403);
            header('Content-Type: application/json');
            echo json_encode(['error' => 'INVALID_TOKEN']);
            exit;
        }
        $secret = app_secret();
        $expectedSig = hash_hmac('sha256', $tm[1], $secret);
        if (!hash_equals($expectedSig, $tm[2])) {
            ReaderTokens::logReadingEvent($uid, $itemId, false, 'repeated_token_failures', 'HMAC signature mismatch on /api/page');
            http_response_code(403);
            header('Content-Type: application/json');
            echo json_encode(['error' => 'INVALID_SIGNATURE']);
            exit;
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
            http_response_code(403);
            header('Content-Type: application/json');
            echo json_encode(['error' => 'TOKEN_EXPIRED']);
            exit;
        }
        if (!hash_equals(hash('sha256', session_id()), (string) ($payload['sid'] ?? ''))) {
            ReaderTokens::logReadingEvent($uid, $itemId, false, 'hotlink_attempt', 'Session ID mismatch on /api/page');
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
            ReaderTokens::logReadingEvent($uid, $itemId, (bool) $tok['is_sample'], 'session_kicked', 'Page access blocked by newer session');
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

        $preparedCount = PageServer::countPreparedPages($itemId);
        $maxAllowed = (!$canReadFull && $isSample)
            ? min($preparedCount, (int) $item['sample_pages'])
            : $preparedCount;

        if ($pageNum < 1 || $pageNum > $maxAllowed) {
            $suspendedNow = ReaderTokens::recordAnomaly($uid, $itemId, $isSample, 'page_out_of_range', "Requested page={$pageNum} (maxAllowed={$maxAllowed})");
            http_response_code(403);
            header('Content-Type: application/json');
            if ($suspendedNow) {
                $freshU = db_one('SELECT reading_suspended_until FROM users WHERE id = ?', [$uid]);
                echo json_encode(['error' => 'READING_SUSPENDED', 'message' => ReaderTokens::userSuspensionMessage((string) ($freshU['reading_suspended_until'] ?? date('Y-m-d H:i:s', time() + 1800)))]);
            } else {
                echo json_encode(['error' => 'PAGE_OUT_OF_RANGE']);
            }
            exit;
        }

        // Configurable daily page quota per item per user (default 3x total pages)
        $quotaMult = max(0, (int) site_setting('reader_daily_quota_mult', '3'));
        if ($quotaMult > 0) {
            $bookTotal = max(1, (int) ($item['total_pages'] ?: $preparedCount));
            $dailyCap = $bookTotal * $quotaMult;
            $usageRow = db_one(
                'SELECT page_hits FROM reading_daily_usage WHERE user_id = ? AND item_id = ? AND usage_date = CURDATE() LIMIT 1',
                [$uid, $itemId]
            );
            $usedToday = (int) ($usageRow['page_hits'] ?? 0);
            if ($usedToday >= $dailyCap) {
                $recentQ = db_one(
                    "SELECT id FROM reading_logs WHERE user_id = ? AND item_id = ? AND action = 'daily_quota_exceeded' AND created_at >= DATE_SUB(NOW(), INTERVAL 30 MINUTE) LIMIT 1",
                    [$uid, $itemId]
                );
                if (!$recentQ) {
                    ReaderTokens::logReadingEvent($uid, $itemId, $isSample, 'daily_quota_exceeded', "Daily limit {$usedToday}/{$dailyCap} reached");
                }
                http_response_code(429);
                header('Content-Type: application/json');
                echo json_encode([
                    'error'   => 'DAILY_QUOTA_EXCEEDED',
                    'message' => current_lang() === 'bn'
                        ? "এই বইয়ের আজকের দৈনিক পঠন সীমা ({$dailyCap} পৃষ্ঠা) পূর্ণ হয়েছে। আগামীকাল আবার পড়তে পারবেন।"
                        : "Daily page reading limit ({$dailyCap} pages) reached for this book. Please continue tomorrow.",
                ]);
                exit;
            }
        }

        $srcFile = PageServer::findPreparedPageFile($itemId, $pageNum);
        if ($srcFile === null) {
            http_response_code(404);
            header('Content-Type: application/json');
            echo json_encode(['error' => 'PAGE_NOT_FOUND']);
            exit;
        }

        if ($quotaMult > 0) {
            db_exec(
                'INSERT INTO reading_daily_usage (user_id, item_id, usage_date, page_hits) VALUES (?, ?, CURDATE(), 1)
                 ON DUPLICATE KEY UPDATE page_hits = page_hits + 1',
                [$uid, $itemId]
            );
        }

        // Track abnormally fast sequential page bursts
        ReaderTokens::trackFastSequentialBurst($uid, $itemId, $pageNum);

        $contact = !empty($user['phone']) ? $user['phone'] : $user['email'];
        $watermarkBase = $user['name'] . ' • ' . $contact . ' • ID #' . $uid;

        $reqMaxW = (int) ($_GET['maxw'] ?? ($_SERVER['HTTP_X_BOOKEXA_MAX_WIDTH'] ?? 0));
        if ($reqMaxW > 0 && $reqMaxW <= 1000) {
            $maxWidth = 1000;
        } else {
            $maxWidth = 0;
        }

        if (!extension_loaded('gd') || !function_exists('imagecreatefromstring')) {
            http_response_code(500);
            header('Content-Type: application/json; charset=UTF-8');
            echo json_encode([
                'error'   => 'WATERMARK_RENDER_ERROR',
                'message' => 'Server image rendering failed (PHP GD required).',
            ]);
            exit;
        }

        $slotToken = PageServer::acquireRenderSlot();
        if ($slotToken === null) {
            http_response_code(503);
            header('Content-Type: application/json; charset=UTF-8');
            header('Retry-After: 2');
            header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
            echo json_encode([
                'error'   => 'SERVER_BUSY',
                'message' => current_lang() === 'bn'
                    ? 'সার্ভার এই মুহূর্তে অনেক পৃষ্ঠা প্রসেস করছে। ২ সেকেন্ড পর আবার চেষ্টা করা হচ্ছে।'
                    : 'Server is temporarily busy rendering pages. Retrying shortly.',
            ]);
            exit;
        }
        register_shutdown_function(static function () use ($slotToken): void {
            PageServer::releaseRenderSlot($slotToken);
        });

        $rendered = Watermarker::outputWatermarkedImage($srcFile, $watermarkBase, [
            'user_id'   => $uid,
            'user_name' => (string) $user['name'],
            'contact'   => (string) $contact,
            'item_id'   => $itemId,
            'page_num'  => $pageNum,
            'max_width' => $maxWidth,
        ]);
        PageServer::releaseRenderSlot($slotToken);
        if (!$rendered) {
            error_log("Bookexa PageServer: failed to decode/watermark item={$itemId} page={$pageNum} src={$srcFile}");
            http_response_code(500);
            header('Content-Type: application/json; charset=UTF-8');
            echo json_encode([
                'error'   => 'PAGE_IMAGE_CORRUPT',
                'message' => current_lang() === 'bn'
                    ? 'সার্ভারে এই পৃষ্ঠার ছবিটি অসম্পূর্ণ বা নষ্ট হয়েছে। অনুগ্রহ করে অ্যাডমিন প্যানেল থেকে PDF-টির পৃষ্ঠা পুনরায় প্রস্তুত করুন।'
                    : 'Prepared page image on the server is unreadable or corrupted. Please re-prepare pages for this PDF in Admin.',
            ]);
        }
        exit;
    }

    public static function handleSecurityEvent(): never
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
        ReaderTokens::logReadingEvent((int) $user['id'], $itemId, false, $event, 'Client deterrence trigger');
        echo json_encode(['ok' => true]);
        exit;
    }

    public static function handleBookmarks(): never
    {
        header('Content-Type: application/json; charset=UTF-8');
        header('Cache-Control: no-store');
        $user = current_user();
        if (!$user) {
            http_response_code(401);
            echo json_encode(['ok' => false, 'error' => 'AUTH_REQUIRED']);
            exit;
        }
        $uid = (int) $user['id'];

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            verify_csrf();
            $act    = (string) ($_POST['action'] ?? 'save');
            $itemId = (int) ($_POST['item_id'] ?? 0);
            $page   = max(1, (int) ($_POST['page_num'] ?? 1));
            if ($itemId <= 0 || !db_one('SELECT id FROM items WHERE id = ? AND is_active = 1 LIMIT 1', [$itemId])) {
                http_response_code(404);
                echo json_encode(['ok' => false, 'message' => 'Book not found']);
                exit;
            }
            if ($act === 'delete') {
                db_exec('DELETE FROM reader_bookmarks WHERE user_id = ? AND item_id = ? AND page_num = ?', [$uid, $itemId, $page]);
            } else {
                $label = trim((string) ($_POST['label'] ?? ''));
                if ($label === '') {
                    $label = (current_lang() === 'bn' ? 'পৃষ্ঠা ' : 'Page ') . $page;
                }
                $label = mb_substr($label, 0, 120);
                db_exec(
                    'INSERT INTO reader_bookmarks (user_id, item_id, page_num, label) VALUES (?, ?, ?, ?)
                     ON DUPLICATE KEY UPDATE label = VALUES(label)',
                    [$uid, $itemId, $page, $label]
                );
            }
            $list = db_all('SELECT id, page_num, label, created_at FROM reader_bookmarks WHERE user_id = ? AND item_id = ? ORDER BY page_num ASC', [$uid, $itemId]);
            echo json_encode(['ok' => true, 'bookmarks' => $list]);
            exit;
        }

        $itemId = (int) ($_GET['item_id'] ?? 0);
        $list = $itemId > 0
            ? db_all('SELECT id, page_num, label, created_at FROM reader_bookmarks WHERE user_id = ? AND item_id = ? ORDER BY page_num ASC', [$uid, $itemId])
            : [];
        echo json_encode(['ok' => true, 'bookmarks' => $list]);
        exit;
    }

    /**
     * Search indexed page text inside a protected book.
     * Returns only page_num + short <=80 char non-selectable snippets, enforces sample boundary & rate limit.
     */
    public static function handleSearch(): never
    {
        header('Content-Type: application/json; charset=UTF-8');
        header('Cache-Control: no-store');

        $user = current_user();
        if (!$user) {
            http_response_code(401);
            echo json_encode(['ok' => false, 'error' => 'AUTH_REQUIRED']);
            exit;
        }

        $itemId   = (int) ($_GET['item'] ?? 0);
        $tokenStr = (string) ($_GET['token'] ?? '');
        $q        = trim((string) ($_GET['q'] ?? ''));

        $tok = ReaderTokens::verify($tokenStr, (int) $user['id'], $itemId);
        if (!$tok) {
            http_response_code(403);
            echo json_encode(['ok' => false, 'error' => 'INVALID_OR_EXPIRED_TOKEN']);
            exit;
        }

        $uid = (int) $user['id'];
        if (empty($user['active_session_token']) || !hash_equals((string) $user['active_session_token'], (string) $tok['session_token'])) {
            http_response_code(409);
            echo json_encode(['ok' => false, 'error' => 'SESSION_KICKED']);
            exit;
        }

        if (!user_rate_limit_check("rsearch:u:{$uid}", 15, 60)) {
            http_response_code(429);
            echo json_encode([
                'ok'      => false,
                'error'   => 'RATE_LIMITED',
                'message' => current_lang() === 'bn' ? 'অনেক দ্রুত খোঁজা হয়েছে। ১ মিনিট পর আবার চেষ্টা করুন।' : 'Search rate limit reached. Please wait a minute.',
            ]);
            exit;
        }

        $dayKey = "rsearch_day:u:{$uid}:i:{$itemId}:" . date('Y-m-d');
        if (!user_rate_limit_check($dayKey, 100, 86400)) {
            $isSampleFlag = (int) $tok['is_sample'] === 1;
            ReaderTokens::recordAnomaly($uid, $itemId, $isSampleFlag, 'search_daily_quota_exceeded', 'Exceeded 100 searches/day on item #' . $itemId);
            http_response_code(429);
            echo json_encode([
                'ok'      => false,
                'error'   => 'SEARCH_DAILY_QUOTA_EXCEEDED',
                'message' => current_lang() === 'bn' ? 'আজকের বইয়ের ভেতর অনুসন্ধানের সীমা (১০০ বার) শেষ হয়েছে।' : 'Daily in-book search limit (100 per book) reached for today.',
            ]);
            exit;
        }

        $minLen = 2;
        if (mb_strlen($q, 'UTF-8') < $minLen) {
            echo json_encode(['ok' => true, 'results' => []]);
            exit;
        }

        $item = db_one('SELECT id, price, is_free, sample_pages, total_pages FROM items WHERE id = ? AND is_active = 1 LIMIT 1', [$itemId]);
        if (!$item) {
            http_response_code(404);
            echo json_encode(['ok' => false, 'error' => 'ITEM_NOT_FOUND']);
            exit;
        }

        $isSample = (int) $tok['is_sample'] === 1;
        $isFree = (bool) $item['is_free'] || (float) $item['price'] <= 0;
        $canReadFull = $isFree || user_owns_item($uid, $itemId);
        if (!$canReadFull && (!$isSample || (int) $item['sample_pages'] <= 0)) {
            http_response_code(403);
            echo json_encode(['ok' => false, 'error' => 'ACCESS_DENIED']);
            exit;
        }

        $preparedCount = PageServer::countPreparedPages($itemId);
        $totalFallback = max($preparedCount, (int) ($item['total_pages'] ?? 0));
        $maxAllowed = (!$canReadFull && $isSample) ? min($totalFallback, (int) $item['sample_pages']) : $totalFallback;

        $results = self::searchPages($itemId, $q, max(1, $maxAllowed));
        echo json_encode(['ok' => true, 'results' => $results], JSON_UNESCAPED_UNICODE);
        exit;
    }

    /**
     * Execute escaped LIKE search inside a book's normalized page text index (min length: 2 chars).
     */
    public static function searchPages(int $itemId, string $query, int $maxAllowed = 99999): array
    {
        $q = trim($query);
        if ($itemId <= 0 || mb_strlen($q, 'UTF-8') < 2) {
            return [];
        }

        $normQ = preg_replace('/\s+/u', ' ', $q) ?? $q;
        $like = '%' . escape_like($normQ) . '%';
        $rows = db_all(
            'SELECT page_num, norm_text
             FROM item_page_texts
             WHERE item_id = ? AND is_staging = 0 AND page_num <= ? AND norm_text LIKE ? ESCAPE \'\\\\\'
             ORDER BY page_num ASC
             LIMIT 10',
            [$itemId, max(1, $maxAllowed), $like]
        );

        $results = [];
        foreach ($rows as $r) {
            $text = (string) $r['norm_text'];
            $pos = mb_stripos($text, $normQ, 0, 'UTF-8');
            $start = ($pos !== false) ? max(0, $pos - 18) : 0;
            $snip = mb_substr($text, $start, 60, 'UTF-8');
            if ($start > 0) {
                $snip = '…' . ltrim(mb_substr($snip, 0, 59, 'UTF-8'));
            }
            if (mb_strlen($snip, 'UTF-8') > 60) {
                $snip = mb_substr($snip, 0, 60, 'UTF-8');
            }
            $results[] = [
                'page'    => (int) $r['page_num'],
                'snippet' => $snip,
            ];
        }
        return $results;
    }
}
