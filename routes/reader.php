<?php
declare(strict_types=1);

if (preg_match('#^/reader/([^/]+)$#', $route, $m)) {
    header('X-Robots-Tag: noindex, nofollow');
    $slug = rawurldecode($m[1]);
    $wantSample = isset($_GET['sample']) && $_GET['sample'] === '1';
    $user = require_login('/reader/' . rawurlencode($slug) . ($wantSample ? '?sample=1' : ''));
    $item = db_one('SELECT * FROM items WHERE slug = ? AND is_active = 1 LIMIT 1', [$slug]);
    if (!$item) {
        redirect('/pdf');
    }

    $uid = (int) $user['id'];
    $isFree = (bool) $item['is_free'] || (float) $item['price'] <= 0;
    $canReadFull = $isFree || user_owns_item($uid, (int) $item['id']);
    $isSample = !$canReadFull;

    if ($isSample && (int) $item['sample_pages'] <= 0) {
        redirect('/checkout/' . rawurlencode($item['slug']));
    }

    // Enforce single active reading session per account
    $newSessionTok = bin2hex(random_bytes(32));
    db_exec('UPDATE users SET active_session_token = ?, active_session_updated_at = NOW() WHERE id = ?', [$newSessionTok, $uid]);
    $_SESSION['active_session_token'] = $newSessionTok;

    require_once APP_ROOT . '/includes/pdf_proxy.php';

    $readerMode = 'unavailable';
    $streamToken = '';
    $pageApiBase = '';
    $maxPages = 0;
    $unavailableCustomMsg = null;

    if (!empty($user['reading_suspended_until']) && strtotime((string) $user['reading_suspended_until']) > time()) {
        $unavailableCustomMsg = user_reading_suspension_message((string) $user['reading_suspended_until']);
        log_reading_event($uid, (int) $item['id'], $isSample, 'blocked_suspended_open', 'Attempted reader open during 30m abuse pause');
    } else {
        db_exec('UPDATE items SET read_count = read_count + 1 WHERE id = ?', [(int) $item['id']]);
        detect_many_sessions_anomaly($uid);
        $preparedCount = count_prepared_pages((int) $item['id']);
        $isPrepared = (($item['pages_status'] ?? 'unprocessed') === 'ready') && $preparedCount > 0;
        $fallbackEnabled = site_setting('free_pdfjs_fallback', '1') === '1';

        if ($isPrepared) {
            $readerMode = 'pages';
            $streamToken = issue_signed_reader_token($uid, (int) $item['id'], $isSample, $newSessionTok, 'pages', 900);
            $maxPages = $isSample ? min($preparedCount, (int) $item['sample_pages']) : $preparedCount;
            $pageApiBase = url('/api/page?item=' . (int) $item['id'] . '&token=' . rawurlencode($streamToken));
            log_reading_event($uid, (int) $item['id'], $isSample, 'open_reader', $isSample ? 'Protected pages (sample)' : 'Protected pages (full)');
        } elseif ($isFree && $fallbackEnabled && trim((string) ($item['drive_link'] ?? '')) !== '') {
            $readerMode = 'pdfjs';
            $streamToken = issue_signed_reader_token($uid, (int) $item['id'], $isSample, $newSessionTok, 'stream', 180);
            $maxPages = $isSample ? (int) $item['sample_pages'] : 0;
            log_reading_event($uid, (int) $item['id'], $isSample, 'open_reader', 'Free PDF.js fallback');
        } else {
            log_reading_event($uid, (int) $item['id'], $isSample, 'open_reader', 'Protected pages pending preparation');
        }
    }

    $savedPage = 1;
    if (!$isSample) {
        $prog = db_one('SELECT last_page FROM reading_progress WHERE user_id = ? AND item_id = ?', [$uid, (int) $item['id']]);
        if ($prog && (int) $prog['last_page'] > 0) {
            $savedPage = (int) $prog['last_page'];
        }
    }

    $userTheme = in_array((string) ($user['reader_theme'] ?? ''), ['dark', 'sepia', 'light'], true) ? (string) $user['reader_theme'] : '';
    $userZoom  = isset($user['reader_zoom']) && (float) $user['reader_zoom'] > 0 ? (float) $user['reader_zoom'] : null;

    $contact = !empty($user['phone']) ? $user['phone'] : $user['email'];
    $watermarkText = $user['name'] . ' • ' . $contact . ' • ID #' . $uid;
    $folderUrl = $item['folder_id'] ? folder_full_url((int) $item['folder_id']) : url('/pdf');

    require APP_ROOT . '/pages/reader.php';
    exit;
}

// ----------------------------------------------------------------------------
// Public Storefront Pages
// ----------------------------------------------------------------------------
