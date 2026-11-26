<?php
declare(strict_types=1);

/**
 * Bookexa Service: DriveFetcher
 */
final class DriveFetcher
{
    public static function extractFileId(string $input): ?string
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

    public static function parsePhpSizeBytes(string $val): int
    {
        $val = trim($val);
        if ($val === '' || $val === '-1') {
            return -1;
        }
        $last = strtolower($val[strlen($val) - 1]);
        $num = (int) $val;
        switch ($last) {
            case 'g':
                $num *= 1024;
                // fall through
            case 'm':
                $num *= 1024;
                // fall through
            case 'k':
                $num *= 1024;
        }
        return max(0, $num);
    }

    public static function serverSafeLimits(): array
    {
        $memStr = (string) ini_get('memory_limit');
        $memBytes = DriveFetcher::parsePhpSizeBytes($memStr);
        $maxExec = (int) ini_get('max_execution_time');
        $recWarnMb = 100.0;
        if ($memBytes > 0) {
            // Streaming uses tiny RAM, but PHP/Apache reverse-proxy & browser PDF.js buffers benefit from caution above ~75% of memory_limit or 100 MB
            $memMb = $memBytes / 1048576;
            $recWarnMb = min(100.0, max(25.0, round($memMb * 0.65, 1)));
        }
        return [
            'memory_limit_raw'   => $memStr !== '' ? $memStr : '-1',
            'memory_limit_bytes' => $memBytes,
            'max_execution_time' => $maxExec,
            'recommended_warn_mb'=> $recWarnMb,
        ];
    }

    public static function driveApiKey(): string
    {
        return trim((string) site_setting('google_drive_api_key', ''));
    }

    public static function redactSecret(string $text): string
    {
        $key = DriveFetcher::driveApiKey();
        if ($key !== '') {
            $text = str_replace([$key, rawurlencode($key)], '[REDACTED]', $text);
        }
        return $text;
    }

    public static function logSourceAdminError(int $userId, int $itemId, bool $isSample, string $realReason): void
    {
        $safeReason = DriveFetcher::redactSecret($realReason);
        ReaderTokens::logReadingEvent($userId, $itemId, $isSample, 'source_fetch_error', $safeReason);
        try {
            db_exec(
                'INSERT INTO security_logs (event_type, identifier, ip_address, details) VALUES (?, ?, ?, ?)',
                ['pdf_source_error', "item#{$itemId}/user#{$userId}", client_ip(), substr($safeReason, 0, 250)]
            );
        } catch (Throwable $e) {
        }
    }

    /**
     * Counts PDF pages from disk without loading the entire PDF into PHP memory.
     */
    public static function countPagesFastFile(string $filePath): ?int
    {
        if (!is_file($filePath)) {
            return null;
        }
        $size = (int) @filesize($filePath);
        if ($size <= 0) {
            return null;
        }
        $fh = @fopen($filePath, 'rb');
        if (!$fh) {
            return null;
        }

        $maxCount = 0;
        $leafMatches = 0;
        $overlap = '';
        $chunkSize = 262144; // 256 KB streaming chunks

        // For massive files (> 180 MB), scan head + tail first for /Type /Pages /Count N before full scan
        if ($size > 180 * 1024 * 1024) {
            $head = (string) fread($fh, 524288);
            fseek($fh, max(0, $size - 524288));
            $tail = (string) fread($fh, 524288);
            fclose($fh);
            foreach ([$head, $tail] as $buf) {
                if (preg_match_all('/\/Type\s*\/Pages\b[^>]*\/Count\s+(\d+)|\/Count\s+(\d+)[^>]*\/Type\s*\/Pages\b/s', $buf, $m, PREG_SET_ORDER)) {
                    foreach ($m as $hit) {
                        $c = (int) ($hit[1] !== '' ? $hit[1] : $hit[2]);
                        if ($c > $maxCount) {
                            $maxCount = $c;
                        }
                    }
                }
            }
            return $maxCount > 0 ? $maxCount : null;
        }

        while (!feof($fh)) {
            $chunk = fread($fh, $chunkSize);
            if ($chunk === false || $chunk === '') {
                break;
            }
            $buf = $overlap . $chunk;
            if (preg_match_all('/\/Type\s*\/Pages\b[^>]{0,300}\/Count\s+(\d+)|\/Count\s+(\d+)[^>]{0,300}\/Type\s*\/Pages\b/s', $buf, $m, PREG_SET_ORDER)) {
                foreach ($m as $hit) {
                    $c = (int) ($hit[1] !== '' ? $hit[1] : $hit[2]);
                    if ($c > $maxCount) {
                        $maxCount = $c;
                    }
                }
            }
            if (preg_match_all('/\/Type\s*\/Page(?!s)\b/', $buf, $pm)) {
                $leafMatches += count($pm[0]);
            }
            $overlap = substr($buf, -512);
        }
        fclose($fh);

        if ($maxCount > 0) {
            return $maxCount;
        }
        return $leafMatches > 0 ? $leafMatches : null;
    }

    /**
     * Streams a remote URL directly to a local file via CURLOPT_WRITEFUNCTION, keeping PHP memory tiny.
     */
    public static function curlStreamDownloadToFile(string $url, string $destPath, ?string $cookieFile = null, int $connectTimeout = 15): array
    {
        $fp = @fopen($destPath, 'wb');
        if (!$fp) {
            return ['ok' => false, 'http_code' => 0, 'content_type' => '', 'bytes' => 0, 'head' => '', 'errno' => -1, 'error' => 'Cannot open temp file'];
        }

        $headBuf = '';
        $written = 0;
        $ch = curl_init($url);
        $opts = [
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS      => 7,
            CURLOPT_CONNECTTIMEOUT => $connectTimeout,
            CURLOPT_TIMEOUT        => 0,
            CURLOPT_LOW_SPEED_LIMIT=> 1024,
            CURLOPT_LOW_SPEED_TIME => 60,
            CURLOPT_USERAGENT      => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 Chrome/124.0.0.0 Safari/537.36',
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_WRITEFUNCTION  => function ($chHandle, string $data) use ($fp, &$headBuf, &$written): int {
                $len = strlen($data);
                $written += $len;
                $w = @fwrite($fp, $data);
                if ($w === false) {
                    return 0;
                }
                $headCap = str_starts_with(ltrim($headBuf . $data), '%PDF-') ? 4096 : 262144;
                if (strlen($headBuf) < $headCap) {
                    $headBuf .= substr($data, 0, $headCap - strlen($headBuf));
                }
                return $len;
            },
        ];
        if ($cookieFile !== null) {
            $opts[CURLOPT_COOKIEJAR]  = $cookieFile;
            $opts[CURLOPT_COOKIEFILE] = $cookieFile;
        }
        curl_setopt_array($ch, $opts);
        $execOk = curl_exec($ch);
        $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $contentType = strtolower((string) curl_getinfo($ch, CURLINFO_CONTENT_TYPE));
        $errno = curl_errno($ch);
        $cErr = curl_error($ch);
        curl_close($ch);
        fclose($fp);

        return [
            'ok'           => ($execOk !== false && $errno === 0),
            'http_code'    => $httpCode,
            'content_type' => $contentType,
            'bytes'        => $written,
            'head'         => $headBuf,
            'errno'        => $errno,
            'error'        => $cErr,
        ];
    }

    public static function startsWithMagic(string $filePath): bool
    {
        $fh = @fopen($filePath, 'rb');
        if (!$fh) {
            return false;
        }
        $head = (string) fread($fh, 16);
        fclose($fh);
        return str_starts_with(ltrim($head), '%PDF-');
    }

    public static function classifyHtmlFailure(int $httpCode, string $contentType, string $headBuf): array
    {
        $lower = strtolower($headBuf);
        if ($httpCode === 429 || str_contains($lower, 'quota exceeded') || str_contains($lower, 'too many users have viewed') || str_contains($lower, 'download quota')) {
            return [
                'ok'           => false,
                'code'         => 'PDF_TEMP_BUSY',
                'reason_code'  => 'QUOTA_EXCEEDED',
                'message'      => 'This document is temporarily busy. Please try again in a few minutes.',
                'admin_reason' => 'Google Drive download quota exceeded (HTTP ' . $httpCode . ')',
                'http_code'    => $httpCode,
            ];
        }
        if ($httpCode === 401 || $httpCode === 403 || str_contains($lower, 'you need access') || str_contains($lower, 'request access') || str_contains($lower, 'accounts.google.com')) {
            return [
                'ok'           => false,
                'code'         => 'PDF_UNAVAILABLE',
                'reason_code'  => 'NOT_PUBLIC',
                'message'      => 'This document is temporarily unavailable. Please contact support.',
                'admin_reason' => 'Google Drive file not shared publicly / access denied (HTTP ' . $httpCode . ')',
                'http_code'    => $httpCode,
            ];
        }
        if ($httpCode === 404) {
            return [
                'ok'           => false,
                'code'         => 'PDF_UNAVAILABLE',
                'reason_code'  => 'NOT_FOUND',
                'message'      => 'Requested document could not be found right now.',
                'admin_reason' => 'Google Drive file not found or removed (HTTP 404)',
                'http_code'    => 404,
            ];
        }
        return [
            'ok'           => false,
            'code'         => 'PDF_INVALID',
            'reason_code'  => 'NOT_A_PDF',
            'message'      => 'Could not open this document right now. Please try again shortly.',
            'admin_reason' => 'Downloaded content is not a valid PDF (HTTP ' . $httpCode . ', MIME ' . ($contentType ?: 'unknown') . ')',
            'http_code'    => $httpCode,
        ];
    }

    /**
     * Reliable streaming source PDF resolver: saves or resolves directly to a local file without full-file PHP buffering.
     * Tries Official Drive API v3 (if key configured) before falling back to usercontent + large-file confirm form parsing.
     */
    public static function fetchToFile(string $source, ?array $testOverride = null): array
    {
        @set_time_limit(0);

        if (str_starts_with($source, 'local://')) {
            $filename = basename(substr($source, 8));
            $localPath = APP_ROOT . '/storage/demo/' . $filename;
            if (!is_file($localPath)) {
                return [
                    'ok'           => false,
                    'code'         => 'NOT_FOUND',
                    'reason_code'  => 'NOT_FOUND',
                    'message'      => 'Requested document is unavailable.',
                    'admin_reason' => 'Local demo file missing: ' . $filename,
                ];
            }
            return [
                'ok'             => true,
                'path'           => $localPath,
                'size'           => (int) filesize($localPath),
                'method'         => 'local',
                'confirm_needed' => false,
                'unlink_after'   => false,
            ];
        }

        $fileId = DriveFetcher::extractFileId($source);
        if ($fileId === null) {
            return [
                'ok'           => false,
                'code'         => 'INVALID_SOURCE',
                'reason_code'  => 'INVALID_URL',
                'message'      => 'This document is temporarily unavailable.',
                'admin_reason' => 'Invalid or empty document source URL',
            ];
        }

        $cacheEnabled = (bool) app_config('security.pdf_cache_enabled', true);
        $cacheTtl     = (int) app_config('security.pdf_cache_ttl', 21600);
        $cacheDir     = APP_ROOT . '/storage/cache';
        if (!is_dir($cacheDir)) {
            @mkdir($cacheDir, 0755, true);
        }
        $secret    = (string) app_config('security.app_secret', 'bookexa_cache');
        $cachePath = $cacheDir . '/' . hash_hmac('sha256', $fileId, $secret) . '.bin';

        if (empty($testOverride['bypass_cache']) && $cacheEnabled && is_file($cachePath) && (time() - (int) @filemtime($cachePath)) < $cacheTtl) {
            if (DriveFetcher::startsWithMagic($cachePath)) {
                return [
                    'ok'             => true,
                    'path'           => $cachePath,
                    'size'           => (int) filesize($cachePath),
                    'method'         => 'cache',
                    'confirm_needed' => false,
                    'unlink_after'   => false,
                ];
            }
        }

        if (!function_exists('curl_init')) {
            return [
                'ok'           => false,
                'code'         => 'SERVER_ERROR',
                'reason_code'  => 'CURL_MISSING',
                'message'      => 'Document service temporarily unavailable.',
                'admin_reason' => 'PHP cURL extension is disabled on this server',
            ];
        }

        $finalizeSuccess = static function (string $tmpPart, string $method, bool $confirmNeeded) use ($cacheEnabled, $cachePath): array {
            $size = (int) @filesize($tmpPart);
            if ($cacheEnabled) {
                if (@rename($tmpPart, $cachePath)) {
                    return [
                        'ok'             => true,
                        'path'           => $cachePath,
                        'size'           => $size,
                        'method'         => $method,
                        'confirm_needed' => $confirmNeeded,
                        'unlink_after'   => false,
                    ];
                }
            }
            return [
                'ok'             => true,
                'path'           => $tmpPart,
                'size'           => $size,
                'method'         => $method,
                'confirm_needed' => $confirmNeeded,
                'unlink_after'   => true,
            ];
        };

        $apiFailNote = '';
        // 1. Official Google Drive API v3 mode (when optional API key is set)
        $apiKey = isset($testOverride['api_key']) ? trim((string) $testOverride['api_key']) : DriveFetcher::driveApiKey();
        if ($apiKey !== '') {
            $apiBase = rtrim((string) ($testOverride['api_base'] ?? 'https://www.googleapis.com/drive/v3/files'), '/');
            $apiUrl = $apiBase . '/' . rawurlencode($fileId) . '?alt=media&supportsAllDrives=true&key=' . rawurlencode($apiKey);
            $tmpPart = $cacheDir . '/gd_api_' . bin2hex(random_bytes(8)) . '.part';
            $dl = DriveFetcher::curlStreamDownloadToFile($apiUrl, $tmpPart);

            $isPdf = $dl['ok'] && ($dl['http_code'] >= 200 && $dl['http_code'] < 300)
                && (str_starts_with(ltrim($dl['head']), '%PDF-') || (str_contains($dl['content_type'], 'application/pdf') && $dl['bytes'] > 100));
            if ($isPdf) {
                return $finalizeSuccess($tmpPart, 'api', false);
            }

            $apiJson = json_decode($dl['head'], true);
            $apiMsg = (string) ($apiJson['error']['message'] ?? '');
            $apiFailNote = 'Drive API HTTP ' . $dl['http_code'] . ($apiMsg !== '' ? " ({$apiMsg})" : ($dl['error'] !== '' ? ' (' . $dl['error'] . ')' : ''));
            @unlink($tmpPart);
        }

        // 2. Fallback: Drive usercontent / uc direct + large-file confirmation streaming
        $lastError = [
            'ok'           => false,
            'code'         => 'FETCH_FAILED',
            'reason_code'  => 'UNREACHABLE',
            'message'      => 'Could not load this document right now. Please try again shortly.',
            'admin_reason' => $apiFailNote !== '' ? ($apiFailNote . '; fallback also failed') : 'Remote download failed after retries',
        ];

        $defaultUrls = [
            'https://drive.usercontent.google.com/download?id=' . rawurlencode($fileId) . '&export=download&confirm=t',
            'https://drive.google.com/uc?export=download&confirm=t&id=' . rawurlencode($fileId),
        ];
        $urls = !empty($testOverride['download_urls']) && is_array($testOverride['download_urls'])
            ? $testOverride['download_urls']
            : $defaultUrls;

        for ($attempt = 1; $attempt <= 2; $attempt++) {
            if ($attempt > 1) {
                usleep(450000);
            }
            $cookieFile = tempnam(sys_get_temp_dir(), 'bx_gd_');

            foreach ($urls as $targetUrl) {
                $tmpPart = $cacheDir . '/gd_dl_' . bin2hex(random_bytes(8)) . '.part';
                $dl = DriveFetcher::curlStreamDownloadToFile($targetUrl, $tmpPart, $cookieFile ?: null);

                if (!$dl['ok']) {
                    @unlink($tmpPart);
                    $lastError = [
                        'ok'           => false,
                        'code'         => 'TIMEOUT',
                        'reason_code'  => 'TIMEOUT',
                        'message'      => 'Connection timed out while preparing document pages. Please retry.',
                        'admin_reason' => 'cURL transfer error (' . $dl['errno'] . '): ' . $dl['error'],
                    ];
                    continue;
                }

                if (str_starts_with(ltrim($dl['head']), '%PDF-') || (str_contains($dl['content_type'], 'application/pdf') && $dl['bytes'] > 100)) {
                    if ($cookieFile && file_exists($cookieFile)) {
                        @unlink($cookieFile);
                    }
                    return $finalizeSuccess($tmpPart, 'usercontent', false);
                }

                // Inspect small HTML body header before discarding temp file
                $headBuf = $dl['head'];
                @unlink($tmpPart);

                $lowerBody = strtolower($headBuf);
                if ($dl['http_code'] === 429 || $dl['http_code'] === 401 || $dl['http_code'] === 403 || $dl['http_code'] === 404
                    || str_contains($lowerBody, 'quota exceeded') || str_contains($lowerBody, 'too many users have viewed')
                    || str_contains($lowerBody, 'you need access') || str_contains($lowerBody, 'accounts.google.com')) {
                    $classified = DriveFetcher::classifyHtmlFailure($dl['http_code'], $dl['content_type'], $headBuf);
                    if ($apiFailNote !== '') {
                        $classified['admin_reason'] = $apiFailNote . '; ' . $classified['admin_reason'];
                    }
                    $lastError = $classified;
                    if (in_array($classified['reason_code'], ['QUOTA_EXCEEDED', 'NOT_PUBLIC', 'NOT_FOUND'], true)) {
                        if ($cookieFile && file_exists($cookieFile)) {
                            @unlink($cookieFile);
                        }
                        return $lastError;
                    }
                    continue;
                }

                // Large-file virus scan confirmation HTML form parsing
                if (str_contains($dl['content_type'], 'text/html') || str_contains($lowerBody, '<form')) {
                    $confirmUrl = null;
                    if (preg_match('/<form[^>]+action=["\']([^"\']+)["\'][^>]*>(.*?)<\/form>/is', $headBuf, $fm)) {
                        $actionUrl = html_entity_decode($fm[1], ENT_QUOTES);
                        if (str_starts_with($actionUrl, '/')) {
                            $pu = parse_url($targetUrl);
                            $actionUrl = ($pu['scheme'] ?? 'https') . '://' . ($pu['host'] ?? 'drive.usercontent.google.com') . (!empty($pu['port']) ? ':' . $pu['port'] : '') . $actionUrl;
                        }
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
                    } elseif (preg_match('/confirm=([a-zA-Z0-9_-]+)/', $headBuf, $cm)) {
                        $confirmUrl = 'https://drive.google.com/uc?export=download&confirm=' . rawurlencode($cm[1]) . '&id=' . rawurlencode($fileId);
                    }

                    if ($confirmUrl !== null) {
                        $tmpConfirm = $cacheDir . '/gd_cf_' . bin2hex(random_bytes(8)) . '.part';
                        $dl2 = DriveFetcher::curlStreamDownloadToFile($confirmUrl, $tmpConfirm, $cookieFile ?: null);
                        $isPdf2 = $dl2['ok'] && (str_starts_with(ltrim($dl2['head']), '%PDF-') || (str_contains($dl2['content_type'], 'application/pdf') && $dl2['bytes'] > 100));
                        if ($isPdf2) {
                            if ($cookieFile && file_exists($cookieFile)) {
                                @unlink($cookieFile);
                            }
                            return $finalizeSuccess($tmpConfirm, 'confirm', true);
                        }
                        $head2 = $dl2['head'];
                        @unlink($tmpConfirm);
                        $lastError = DriveFetcher::classifyHtmlFailure($dl2['http_code'], $dl2['content_type'], $head2);
                    } else {
                        $lastError = DriveFetcher::classifyHtmlFailure($dl['http_code'], $dl['content_type'], $headBuf);
                    }
                }
            }

            if ($cookieFile && file_exists($cookieFile)) {
                @unlink($cookieFile);
            }
        }

        if ($apiFailNote !== '' && !str_contains($lastError['admin_reason'] ?? '', $apiFailNote)) {
            $lastError['admin_reason'] = $apiFailNote . '; ' . ($lastError['admin_reason'] ?? 'Fallback failed');
        }
        return $lastError;
    }
}
