<?php
declare(strict_types=1);

/**
 * Bookexa Automated Plain-PHP Verification Suite (Zero Composer)
 * Usage: php tests/run.php [--filter=pattern]
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('CLI only.');
}

$filter = '';
foreach ($argv ?? [] as $arg) {
    if (str_starts_with($arg, '--filter=')) {
        $filter = strtolower(trim(substr($arg, 9)));
    }
}

require_once dirname(__DIR__) . '/includes/bootstrap.php';
require_once APP_ROOT . '/includes/admin_helpers.php';
require_once APP_ROOT . '/includes/pdf_proxy.php';
require_once APP_ROOT . '/tests/schema_check.php';

// Track clean DB state & metrics
$results = ['pass' => 0, 'fail' => 0, 'skip' => 0];
$currentTest = '';

function t_assert(bool $cond, string $message): void
{
    global $results, $currentTest;
    if ($cond) {
        $results['pass']++;
        echo "  [PASS] {$message}\n";
    } else {
        $results['fail']++;
        echo "  [FAIL] ({$currentTest}) {$message}\n";
    }
}

function t_case(string $name, callable $fn): void
{
    global $filter, $currentTest, $results;
    if ($filter !== '' && !str_contains(strtolower($name), $filter)) {
        $results['skip']++;
        return;
    }
    $currentTest = $name;
    echo "\n=== {$name} ===\n";
    try {
        $fn();
    } catch (Throwable $e) {
        $results['fail']++;
        echo "  [ERROR] Uncaught exception: {$e->getMessage()} in {$e->getFile()}:{$e->getLine()}\n";
    }
}

// ----------------------------------------------------------------------------
// 1. Safe Redirects & Open Redirect Protection
// ----------------------------------------------------------------------------
t_case('Safe Redirects', static function (): void {
    t_assert(safe_internal_path('/library') === '/library', 'Allows simple internal path /library');
    t_assert(safe_internal_path('/en/book/test?page=2#sec') === '/en/book/test?page=2#sec', 'Allows query and fragment on internal path');
    t_assert(safe_internal_path('https://evil.example/phish', '/library') === '/library', 'Blocks external https URL');
    t_assert(safe_internal_path('//evil.example', '/library') === '/library', 'Blocks protocol-relative //evil.example');
    t_assert(safe_internal_path('/\\evil.example', '/library') === '/library', 'Blocks backslash open redirect /\\evil.example');
    t_assert(safe_internal_path('/%5cevil.example', '/library') === '/library', 'Blocks URL-encoded backslash /%5cevil.example');
    t_assert(safe_internal_path("///evil.example\r\nSet-Cookie: a=1", '/library') === '/library', 'Blocks control chars / CRLF');
    t_assert(safe_internal_path('javascript:alert(1)', '/library') === '/library', 'Blocks javascript: scheme');
});

// ----------------------------------------------------------------------------
// 2. LIKE Wildcard Escaping & Search Safety
// ----------------------------------------------------------------------------
t_case('LIKE Wildcard Escaping', static function (): void {
    t_assert(escape_like('hello') === 'hello', 'Leaves plain ASCII unchanged');
    t_assert(escape_like('100%_off\\test') === '100\\%\\_off\\\\test', 'Escapes %, _, and backslash');
    $likeArg = '%' . escape_like('___%%%') . '%';
    $rows = db_all("SELECT id FROM items WHERE (title_en LIKE ? ESCAPE '\\\\' OR title_bn LIKE ? ESCAPE '\\\\') LIMIT 5", [$likeArg, $likeArg]);
    t_assert(count($rows) === 0, 'Literal ___%%% pattern does not wildcard-match arbitrary items');
});

// ----------------------------------------------------------------------------
// 3. Coupons (Digital Item + Physical Cart)
// ----------------------------------------------------------------------------
t_case('Coupon Validation Rules', static function (): void {
    $sfx = substr(bin2hex(random_bytes(3)), 0, 6);
    db_exec('INSERT INTO users (name, email, phone, password_hash, is_active) VALUES (?, ?, ?, ?, 1)', ['Coupon User', "cpn_{$sfx}@test.local", '01700' . rand(100000, 999999), password_hash('Secret#1', PASSWORD_BCRYPT)]);
    $uid = (int) db()->lastInsertId();

    db_exec('INSERT INTO folders (slug, name_bn, name_en, is_active) VALUES (?, ?, ?, 1)', ["cpn-f-{$sfx}", 'ফোল্ডার', 'Coupon Folder']);
    $fid = (int) db()->lastInsertId();
    db_exec('INSERT INTO items (folder_id, slug, title_bn, title_en, price, is_free, is_active) VALUES (?, ?, ?, ?, 500, 0, 1)', [$fid, "cpn-i-{$sfx}", 'বই', 'Coupon Book']);
    $iid = (int) db()->lastInsertId();

    $cPct = 'PCT' . strtoupper($sfx);
    $cExp = 'EXP' . strtoupper($sfx);
    $cFix = 'FIX' . strtoupper($sfx);
    db_exec('INSERT INTO coupons (code, discount_type, discount_value, min_amount, usage_limit, per_user_limit, applies_to_all, is_active) VALUES (?, "percent", 20, 300, 2, 1, 1, 1)', [$cPct]);
    $pctId = (int) db()->lastInsertId();
    db_exec('INSERT INTO coupons (code, discount_type, discount_value, min_amount, expires_at, applies_to_all, is_active) VALUES (?, "fixed", 100, 0, DATE_SUB(NOW(), INTERVAL 1 DAY), 1, 1)', [$cExp]);
    $expId = (int) db()->lastInsertId();
    db_exec('INSERT INTO coupons (code, discount_type, discount_value, min_amount, applies_to_all, is_active) VALUES (?, "fixed", 150, 700, 1, 1)', [$cFix]);
    $fixId = (int) db()->lastInsertId();

    $r1 = validate_coupon_for_checkout($cPct, $uid, $iid, 500.0);
    t_assert(!empty($r1['ok']) && (float) $r1['discount_amount'] === 100.0 && (float) $r1['final_amount'] === 400.0, 'Percent coupon calculates 20% off 500 = 400');

    $r2 = validate_coupon_for_checkout($cExp, $uid, $iid, 500.0);
    t_assert(empty($r2['ok']), 'Expired coupon rejected');

    $r3 = validate_coupon_for_checkout($cFix, $uid, $iid, 500.0);
    t_assert(empty($r3['ok']), 'Min amount threshold enforced (500 < 700)');

    $r4 = shop_validate_coupon_for_cart($cPct, $uid, 600.0);
    t_assert(!empty($r4['ok']) && (float) $r4['discount_amount'] === 120.0 && (float) $r4['net_subtotal'] === 480.0, 'Physical cart coupon validates 20% off 600 = 480');

    db_exec('DELETE FROM coupons WHERE id IN (?, ?, ?)', [$pctId, $expId, $fixId]);
    db_exec('DELETE FROM items WHERE id = ?', [$iid]);
    db_exec('DELETE FROM folders WHERE id = ?', [$fid]);
    db_exec('DELETE FROM users WHERE id = ?', [$uid]);
});

// ----------------------------------------------------------------------------
// 4. Duplicate TrxID & Normalization + Order State Transitions
// ----------------------------------------------------------------------------
t_case('Duplicate TrxID & Order State Transitions', static function (): void {
    t_assert(normalize_transaction_id(' tx - 9k 7a ') === 'TX9K7A', 'normalize_transaction_id strips whitespace/punctuation & uppercases');

    $sfx = substr(bin2hex(random_bytes(3)), 0, 6);
    db_exec('INSERT INTO users (name, email, phone, password_hash, is_active) VALUES (?, ?, ?, ?, 1)', ['Order User', "ord_{$sfx}@test.local", '01711' . rand(100000, 999999), password_hash('Secret#1', PASSWORD_BCRYPT)]);
    $uid = (int) db()->lastInsertId();
    db_exec('INSERT INTO folders (slug, name_bn, name_en, is_active) VALUES (?, ?, ?, 1)', ["ord-f-{$sfx}", 'ফ', 'Ord Folder']);
    $fid = (int) db()->lastInsertId();
    db_exec('INSERT INTO items (folder_id, slug, title_bn, title_en, price, is_free, is_active) VALUES (?, ?, ?, ?, 250, 0, 1)', [$fid, "ord-i-{$sfx}", 'বই', 'Ord Book']);
    $iid = (int) db()->lastInsertId();

    $cpn = 'ORD' . strtoupper($sfx);
    db_exec('INSERT INTO coupons (code, discount_type, discount_value, min_amount, usage_limit, used_count, applies_to_all, is_active) VALUES (?, "fixed", 50, 0, 5, 1, 1, 1)', [$cpn]);
    $cid = (int) db()->lastInsertId();

    $trx = 'TRX' . strtoupper($sfx);
    db_exec(
        'INSERT INTO orders (order_code, user_id, item_id, original_amount, coupon_id, coupon_code, discount_amount, amount, payment_method, sender_number, transaction_id, status) VALUES (?, ?, ?, 250, ?, ?, 50, 200, "bkash", "01700000000", ?, "pending")',
        ['BX-T-' . $sfx, $uid, $iid, $cid, $cpn, $trx]
    );
    $oid = (int) db()->lastInsertId();

    // Unique (payment_method, transaction_id) constraint test
    $dupThrown = false;
    try {
        db_exec(
            'INSERT INTO orders (order_code, user_id, item_id, original_amount, amount, payment_method, sender_number, transaction_id, status) VALUES (?, ?, ?, 250, 250, "bkash", "01700000001", ?, "pending")',
            ['BX-D-' . $sfx, $uid, $iid, $trx]
        );
    } catch (PDOException $e) {
        $dupThrown = true;
    }
    t_assert($dupThrown, 'Duplicate (payment_method, transaction_id) prevented cleanly at DB level');

    // Approve grants library access
    $appRes = apply_order_status_transition($oid, 'approved', null, 'Verified OK');
    t_assert(!empty($appRes['ok']) && !empty($appRes['changed']), 'Order transitions pending -> approved cleanly');
    t_assert((bool) db_one('SELECT id FROM library WHERE user_id = ? AND item_id = ?', [$uid, $iid]), 'Approved order unlocks item in user library');

    // Reject transitions status and releases coupon once (preserving prior behavior cleanly)
    $rejRes = apply_order_status_transition($oid, 'rejected', null, 'Reversed');
    t_assert(!empty($rejRes['ok']) && !empty($rejRes['changed']) && $rejRes['old_status'] === 'approved', 'Order transitions approved -> rejected cleanly');
    $usedAfter = (int) (db_one('SELECT used_count FROM coupons WHERE id = ?', [$cid])['used_count'] ?? -1);
    t_assert($usedAfter === 0, 'Coupon used_count decremented back to 0 on rejection');

    // Re-rejecting same status is idempotent
    $rejAgain = apply_order_status_transition($oid, 'rejected', null, 'Reversed');
    t_assert(!empty($rejAgain['ok']) && empty($rejAgain['changed']), 'Repeated same-status transition is idempotent');

    db_exec('DELETE FROM library WHERE order_id = ?', [$oid]);
    db_exec('DELETE FROM orders WHERE id = ?', [$oid]);
    db_exec('DELETE FROM coupons WHERE id = ?', [$cid]);
    db_exec('DELETE FROM items WHERE id = ?', [$iid]);
    db_exec('DELETE FROM folders WHERE id = ?', [$fid]);
    db_exec('DELETE FROM users WHERE id = ?', [$uid]);
});

// ----------------------------------------------------------------------------
// 5. Signed Reader Tokens
// ----------------------------------------------------------------------------
t_case('Signed Reader Tokens', static function (): void {
    $sfx = substr(bin2hex(random_bytes(3)), 0, 6);
    db_exec('INSERT INTO users (name, email, phone, password_hash, is_active) VALUES (?, ?, ?, ?, 1)', ['Token User', "tok_{$sfx}@test.local", '01722' . rand(100000, 999999), password_hash('Secret#1', PASSWORD_BCRYPT)]);
    $uid = (int) db()->lastInsertId();
    db_exec('INSERT INTO folders (slug, name_bn, name_en, is_active) VALUES (?, ?, ?, 1)', ["tok-f-{$sfx}", 'ফ', 'Tok Folder']);
    $fid = (int) db()->lastInsertId();
    db_exec('INSERT INTO items (folder_id, slug, title_bn, title_en, price, is_free, is_active) VALUES (?, ?, ?, ?, 0, 1, 1)', [$fid, "tok-i-{$sfx}", 'বই', 'Tok Book']);
    $itemId = (int) db()->lastInsertId();

    $sessTok = bin2hex(random_bytes(16));
    $tok = issue_signed_reader_token($uid, $itemId, false, $sessTok, 'pages', 300);

    $parsed = verify_signed_reader_token($tok, $uid, $itemId);
    t_assert($parsed !== null && $parsed['session_token'] === $sessTok && (int) $parsed['is_sample'] === 0, 'Valid token verifies and extracts payload');
    t_assert(verify_signed_reader_token($tok, $uid + 1, $itemId) === null, 'Token bound to user ID (wrong user rejected)');
    t_assert(verify_signed_reader_token($tok, $uid, $itemId + 1) === null, 'Token bound to item ID (wrong item rejected)');

    $tampered = substr($tok, 0, -4) . 'AAAA';
    t_assert(verify_signed_reader_token($tampered, $uid, $itemId) === null, 'Tampered HMAC signature rejected');

    db_exec('UPDATE reader_tokens SET expires_at = DATE_SUB(NOW(), INTERVAL 5 MINUTE) WHERE token_hash = ?', [hash('sha256', $tok)]);
    t_assert(verify_signed_reader_token($tok, $uid, $itemId) === null, 'Expired reader token in DB rejected');

    db_exec('DELETE FROM reader_tokens WHERE user_id = ?', [$uid]);
    db_exec('DELETE FROM items WHERE id = ?', [$itemId]);
    db_exec('DELETE FROM folders WHERE id = ?', [$fid]);
    db_exec('DELETE FROM users WHERE id = ?', [$uid]);
});

// ----------------------------------------------------------------------------
// 6. Rate Limits
// ----------------------------------------------------------------------------
t_case('Rate Limits', static function (): void {
    $rk = 'test_rl_' . bin2hex(random_bytes(4));
    $ipKey = 'test_ip_rl_' . bin2hex(random_bytes(4));

    t_assert(user_rate_limit_check($rk, 2, 60) === true, '1st DB rate-limit hit allowed');
    t_assert(user_rate_limit_check($rk, 2, 60) === true, '2nd DB rate-limit hit allowed');
    t_assert(user_rate_limit_check($rk, 2, 60) === false, '3rd DB rate-limit hit blocked');

    $p1 = public_api_ip_rate_limit($ipKey, 2, 60);
    $p2 = public_api_ip_rate_limit($ipKey, 2, 60);
    $p3 = public_api_ip_rate_limit($ipKey, 2, 60);
    t_assert($p1['allowed'] && $p2['allowed'] && !$p3['allowed'], 'File-backed IP rate limiter blocks request #3 (> limit 2)');

    db_exec('DELETE FROM rate_limits WHERE rate_key = ?', [$rk]);
    @unlink(APP_ROOT . '/storage/cache/rl/' . sha1($ipKey) . '.json');
});

// ----------------------------------------------------------------------------
// 7. Schema Validation, Migrations Idempotency & FK Compatibility Check
// ----------------------------------------------------------------------------
t_case('Migrations Runner & Schema Check', static function (): void {
    $schemaRes = bookexa_validate_schema_sql(APP_ROOT . '/database/bookexa.sql');
    t_assert(!empty($schemaRes['ok']) && (int) $schemaRes['tables'] >= 35, 'database/bookexa.sql passes duplicate-column & InnoDB check (' . (int) ($schemaRes['tables'] ?? 0) . ' tables)');

    $run = migration_run_pending();
    t_assert(!empty($run['ok']) && (int) $run['pending'] === 0, 'All migrations cleanly applied (0 pending)');
    $st = migration_list_status();
    t_assert((int) $st['pending_count'] === 0 && count($st['items']) >= 10, 'migration_list_status reports >=10 tracked migrations applied');
    $r2 = migration_run_pending();
    t_assert(!empty($r2['ok']) && empty($r2['errors']), 'Re-running migration_run_pending is idempotent');

    // Re-running migration 005 on top of an existing schema verifies parent-first + FK guard idempotency
    $m005 = migration_run_one('005_security_reliability_fixes');
    $pdo = db();
    t_assert(!empty($m005['ok']) && mig_table_exists($pdo, 'reading_daily_usage') && mig_foreign_key_exists($pdo, 'reading_daily_usage', 'fk_rdu_user') && mig_foreign_key_exists($pdo, 'reading_daily_usage', 'fk_rdu_item'), 'Migration 005 reading_daily_usage parent order & FKs verified idempotently');
});

// ----------------------------------------------------------------------------
// 8. Admin Roles & Permission Checks
// ----------------------------------------------------------------------------
t_case('Admin Permission Matrix', static function (): void {
    t_assert(admin_can(['role' => 'superadmin'], 'delivery-settings') && admin_can(['role' => 'superadmin'], 'admins'), 'Superadmin has wildcard permission');
    t_assert(admin_can(['role' => 'editor'], 'pdfs') && admin_can(['role' => 'editor'], 'shop-products') && !admin_can(['role' => 'editor'], 'orders') && !admin_can(['role' => 'editor'], 'settings'), 'Editor restricted to content & shop catalog only');
    t_assert(admin_can(['role' => 'support'], 'orders') && admin_can(['role' => 'support'], 'shop-orders') && admin_can(['role' => 'support'], 'inbox') && !admin_can(['role' => 'support'], 'pdfs'), 'Support scoped to orders, inbox & read-only users');
    t_assert(admin_can(['role' => 'analyst'], 'reports') && !admin_can(['role' => 'analyst'], 'shop-orders') && !admin_can(['role' => 'analyst'], 'updates'), 'Analyst scoped strictly to reports');
});

// ----------------------------------------------------------------------------
// 9. Refactored Services, Version & White-Label Helpers
// ----------------------------------------------------------------------------
t_case('Service Classes & White-Label Branding', static function (): void {
    t_assert(class_exists('ReaderTokens') && class_exists('DriveFetcher') && class_exists('Watermarker') && class_exists('PageServer'), 'PDF & Reader service classes loaded');
    t_assert(class_exists('OrderService') && class_exists('CouponService') && class_exists('SearchService'), 'Domain service facades loaded');
    t_assert(DriveFetcher::extractFileId('https://drive.google.com/file/d/1A2B3C4D5E6F7G8H9I0J/view') === '1A2B3C4D5E6F7G8H9I0J', 'DriveFetcher extracts file ID');
    t_assert(sanitize_hex_color('#ab12ef') === '#AB12EF' && sanitize_hex_color('red', '#2B2F77') === '#2B2F77', 'sanitize_hex_color validates 6-char hex');
    t_assert(sanitize_social_url('javascript:alert(1)') === '' && sanitize_social_url('https://facebook.com/bookexa') === 'https://facebook.com/bookexa', 'sanitize_social_url blocks unsafe schemes');
    t_assert(version_compare(bookexa_version(), '2.4.0', '>='), 'bookexa_version() reads configured release version');
    t_assert(theme_font_stack_pair('system_clean')['sans'] !== '', 'theme_font_stack_pair resolves font stack');
});

// ----------------------------------------------------------------------------
// 10. Clean Export Package Allowlist & Exclusions
// ----------------------------------------------------------------------------
t_case('Clean Export Packager Allowlist', static function (): void {
    $badCheck = packager_verify_zip_allowlist([
        'bookexa/index.php',
        'bookexa/config/config.php',
        'bookexa/config/installed.lock',
        'bookexa/.git/HEAD',
        'bookexa/storage/logs/php-error.log',
        'bookexa/storage/pages/1/page-1.webp',
        'bookexa/database/demo_seed.sql',
        'bookexa/evil.exe',
    ]);
    t_assert(!$badCheck['ok'] && count($badCheck['violations']) === 7, 'Allowlist checker flags all 7 excluded/unsafe paths cleanly');

    $built = build_site_package_zip(true);
    $entries = $built['entries'];
    $joined = "\n" . implode("\n", $entries) . "\n";
    @unlink($built['path']);

    t_assert(count($entries) > 20, 'Clean ZIP export bundles core source files (' . count($entries) . ' entries)');
    t_assert(!str_contains($joined, "bookexa/config/config.php\n") && !str_contains($joined, "bookexa/config/installed.lock\n"), 'Clean ZIP excludes config/config.php and config/installed.lock');
    t_assert(!str_contains($joined, 'bookexa/.git/') && !str_contains($joined, '.log') && !str_contains($joined, 'demo_seed.sql'), 'Clean ZIP excludes .git, .log files, and demo_seed.sql');
    t_assert(str_contains($joined, "bookexa/LICENSE\n") && str_contains($joined, "bookexa/docs/INSTALL.md\n") && str_contains($joined, "bookexa/tests/run.php\n"), 'Clean ZIP includes LICENSE, docs/, and tests/');
    $nonHtStorage = preg_grep('#^bookexa/storage/.+/(?!\.htaccess$).+$#', $entries);
    t_assert(empty($nonHtStorage) && str_contains($joined, "bookexa/storage/pages/.htaccess\n"), 'Clean ZIP excludes storage contents while keeping storage/*/.htaccess guards');
});

// ----------------------------------------------------------------------------
// 11. Technical SEO Foundation (Bangla Slugs, Meta Templates, Query Normalization)
// ----------------------------------------------------------------------------
t_case('Technical SEO Foundation', static function (): void {
    t_assert(slugify('বিসিএস প্রিলিমিনারি') === 'bcs-preliminary', 'Transliterates "বিসিএস প্রিলিমিনারি" to "bcs-preliminary"');
    t_assert(slugify('এসএসসি পদার্থবিজ্ঞান নোট ২০২৬') === 'ssc-physics-note-2026', 'Transliterates mixed academic Bangla + numerals cleanly');
    t_assert(slugify('ঢাকা বিশ্ববিদ্যালয় ভর্তি গাইড') === 'dhaka-university-admission-guide', 'Transliterates Bangla place/university words accurately');

    $m1 = seo_render_meta('book', ['title' => 'বিসিএস প্রিলিমিনারি', 'author' => 'ড. করিম', 'folder' => 'বিসিএস', 'price' => '৳২৫০'], [], 1);
    $m2 = seo_render_meta('book', ['title' => 'বিসিএস প্রিলিমিনারি', 'author' => 'ড. করিম', 'folder' => 'বিসিএস', 'price' => '৳২৫০'], [], 2);
    $tLen1 = mb_strlen($m1['title'], 'UTF-8');
    $dLen1 = mb_strlen($m1['description'], 'UTF-8');
    t_assert($tLen1 >= SEO_TITLE_MIN && $tLen1 <= SEO_TITLE_MAX, "Meta title clamped within 30-60 chars (got {$tLen1})");
    t_assert($dLen1 >= SEO_DESC_MIN && $dLen1 <= SEO_DESC_MAX, "Meta description clamped within 70-155 chars (got {$dLen1})");
    t_assert($m1['title'] !== $m2['title'] && $m1['description'] !== $m2['description'], 'Paginated page 2 gets unique title and description');

    $qsClean = seo_clean_query_string('sort=newest&utm_source=fb&sort=popular&page=2');
    t_assert($qsClean['changed'] && $qsClean['clean'] === 'sort=popular&page=2', 'Query normalizer strips utm_source and deduplicates repeated params');

    $p1Clean = seo_clean_query_string('page=1');
    t_assert($p1Clean['changed'] && $p1Clean['clean'] === '', 'Query normalizer strips redundant ?page=1');

    $href = seo_hreflang_codes();
    t_assert(str_starts_with($href['bn'], 'bn') && str_starts_with($href['en'], 'en'), 'Resolves regional hreflang codes (' . $href['bn'] . ', ' . $href['en'] . ')');
});

// ----------------------------------------------------------------------------
// 12. Editorial Content Engine, Rich Sanitizer, Bangla Search & Seeded Content
// ----------------------------------------------------------------------------
t_case('Editorial Content Engine & Bangla Search', static function (): void {
    $dirty = "<h1>Stray H1</h1><h2>অধ্যায় ১</h2><script>alert(1)</script><p onclick='evil()'>বিসিএস <strong>প্রস্তুতি</strong> <a href='/pdf'>নোট</a></p><h3>উপ-অধ্যায়</h3><img src='/uploads/covers/a.png' alt='Cover'>";
    $clean = content_sanitize_rich_html($dirty);
    t_assert(!str_contains($clean, '<script') && !str_contains($clean, 'onclick') && !str_contains($clean, '<h1'), 'Rich HTML sanitizer strips scripts/events and demotes stray <h1> to <h2>');

    $tocRes = content_extract_headings_and_toc($clean);
    t_assert(count($tocRes['toc']) === 3 && $tocRes['toc'][0]['level'] === 2 && $tocRes['toc'][2]['level'] === 3, 'Auto-TOC extracts h2/h3 hierarchy with anchor IDs');

    $zw = "ব\u{200C}ই এবং পর\u{200D}ীক্ষা";
    t_assert(content_strip_zero_width($zw) === 'বই এবং পরীক্ষা', 'Strips Bangla zero-width joiners/non-joiners cleanly');
    t_assert(content_word_count($zw) === 3, 'Counts Bangla words accurately after stripping zero-width chars');
    t_assert(content_excerpt('বাংলাদেশ সরকারি কর্ম কমিশনের প্রিলিমিনারি পরীক্ষার জন্য আদর্শ রুটিন ও পরামর্শ।', 40) !== '', 'Bangla excerpt truncation works safely at word boundary');

    $variants = content_bangla_search_variants('পরিক্ষা');
    t_assert(in_array('পরীক্ষা', $variants, true), 'Generates common Bangla spelling variant (পরিক্ষা -> পরীক্ষা)');

    $seededGuide = db_one("SELECT title_bn, summary_bn, body_bn, body_en FROM guides WHERE slug = 'bcs-preliminary-complete-study-plan' LIMIT 1");
    t_assert($seededGuide !== null, 'Seeded starter guide exists in DB');
    if ($seededGuide) {
        $qcDup = content_quality_analyze([
            'title'       => (string) $seededGuide['title_bn'],
            'description' => (string) $seededGuide['summary_bn'],
            'body_bn'     => (string) $seededGuide['body_bn'],
            'body_en'     => (string) $seededGuide['body_en'],
        ], null);
        t_assert($qcDup['is_duplicate'] === true, 'Duplicate content checker detects exact duplicate guide hash');
    }

    $gCnt = (int) (db_one('SELECT COUNT(*) AS c FROM guides')['c'] ?? 0);
    $fCnt = (int) (db_one('SELECT COUNT(*) AS c FROM editorial_faqs')['c'] ?? 0);
    $hCnt = (int) (db_one('SELECT COUNT(*) AS c FROM hubs')['c'] ?? 0);
    t_assert($gCnt >= 6 && $fCnt >= 6 && $hCnt >= 3, "Seeded editorial content verified (guides={$gCnt}, faqs={$fCnt}, hubs={$hCnt})");
});

// ----------------------------------------------------------------------------
// 13. AEO & GEO Optimization Layer (llms.txt, AI Robots, Short Answer, Feeds)
// ----------------------------------------------------------------------------
t_case('AEO & GEO Optimization Layer', static function (): void {
    $robots = aeo_build_robots_txt();
    t_assert(str_contains($robots, 'User-agent: OAI-SearchBot') && str_contains($robots, 'User-agent: GPTBot') && str_contains($robots, 'Sitemap: '), 'robots.txt contains AI crawler policies and Sitemap');

    $sa = aeo_short_answer(['short_answer_en' => str_repeat('word ', 70)], '', 50);
    t_assert(str_ends_with($sa, '…') && count(preg_split('/\s+/u', trim($sa))) <= 52, 'aeo_short_answer trims safely to word limit');

    $llmsBn = aeo_build_llms_txt('bn', false);
    $llmsEn = aeo_build_llms_txt('en', true);
    t_assert(str_contains($llmsBn, '## Key Sections') && str_contains($llmsEn, '/catalog.json'), 'llms.txt generates bilingual Markdown with feeds');
    t_assert(!str_contains($llmsEn, 'item_page_texts') && !str_contains($llmsEn, '/reader/'), 'llms.txt excludes protected reader/PDF full-text paths');

    // Self-contained fixtures so feed validation succeeds on both seeded DBs and fresh empty CI imports
    $sfx = substr(bin2hex(random_bytes(3)), 0, 6);
    $fid = 0;
    $iid = 0;
    $gid = 0;
    try {
        db_exec('INSERT INTO folders (slug, name_bn, name_en, is_active) VALUES (?, ?, ?, 1)', ["ut-feed-f-{$sfx}", 'ফিড ফোল্ডার', 'Feed Folder']);
        $fid = (int) db()->lastInsertId();
        db_exec('INSERT INTO items (folder_id, slug, title_bn, title_en, price, is_free, is_active) VALUES (?, ?, ?, ?, 120.00, 0, 1)', [$fid, "ut-feed-b-{$sfx}", 'ফিড বই', 'Feed Test Book']);
        $iid = (int) db()->lastInsertId();
        db_exec('INSERT INTO guides (slug, title_bn, title_en, summary_bn, summary_en, short_answer_bn, short_answer_en, body_bn, body_en, is_active) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 1)', ["ut-feed-g-{$sfx}", 'ফিড গাইড', 'Feed Study Guide', 'সারাংশ', 'Summary', 'সংক্ষিপ্ত উত্তর', 'Short answer text', '<p>বিস্তারিত</p>', '<p>Details</p>']);
        $gid = (int) db()->lastInsertId();

        $catFeed = aeo_build_catalog_json(1, 20);
        $gdFeed  = aeo_build_guides_json(1, 20);
        t_assert(
            !empty($catFeed['items'])
            && isset($catFeed['items'][0]['price_as_of'], $catFeed['items'][0]['url_bn'], $catFeed['items'][0]['url_en'])
            && !empty($gdFeed['guides'])
            && isset($gdFeed['guides'][0]['short_answer_en'], $gdFeed['guides'][0]['url_bn'], $gdFeed['guides'][0]['url_en']),
            'catalog.json and guides.json produce structured machine feeds'
        );
    } finally {
        if ($gid > 0) db_exec('DELETE FROM guides WHERE id = ?', [$gid]);
        if ($iid > 0) db_exec('DELETE FROM items WHERE id = ?', [$iid]);
        if ($fid > 0) db_exec('DELETE FROM folders WHERE id = ?', [$fid]);
    }

    $scores = aeo_score_all_pages();
    t_assert($scores['total_evaluated'] >= 1 && isset($scores['average_score']) && is_array($scores['top_to_improve']), 'AEO readiness scorer evaluates public pages cleanly');
});

// ----------------------------------------------------------------------------
// 14. Core Web Vitals, Page Cache, Asset Bundles, Fonts & N+1 Preloading
// ----------------------------------------------------------------------------
t_case('Core Web Vitals & Performance Layer', static function (): void {
    t_assert(perf_route_is_cacheable('/') && perf_route_is_cacheable('/pdf/academic') && perf_route_is_cacheable('/faq'), 'Public storefront routes marked cacheable');
    t_assert(!perf_route_is_cacheable('/admin') && !perf_route_is_cacheable('/checkout/foo') && !perf_route_is_cacheable('/cart') && !perf_route_is_cacheable('/api/cart'), 'Private/auth/checkout/API routes never marked cacheable');

    $mPath = APP_ROOT . '/assets/dist/manifest.json';
    t_assert(is_file($mPath), 'Built asset manifest assets/dist/manifest.json exists');
    if (is_file($mPath)) {
        $manifest = json_decode((string) file_get_contents($mPath), true);
        t_assert(!empty($manifest['files']['app_css']['path']) && is_file(APP_ROOT . '/' . $manifest['files']['app_css']['path']), 'Content-hashed public CSS bundle exists on disk');
        t_assert(!empty($manifest['files']['app_js']['path']) && is_file(APP_ROOT . '/' . $manifest['files']['app_js']['path']), 'Content-hashed public JS bundle exists on disk');
        t_assert(!empty($manifest['files']['reader_js']['path']) && !str_contains($manifest['files']['app_js']['path'], 'reader'), 'Reader JS bundle split cleanly from public JS');
        t_assert(!empty($manifest['critical_css_file']) && (int) ($manifest['critical_bytes'] ?? 0) > 500, 'Critical CSS slice extracted cleanly for inline head render');
        foreach (['app_css', 'reader_css', 'admin_css'] as $cssKey) {
            $cssPath = APP_ROOT . '/' . ($manifest['files'][$cssKey]['path'] ?? '');
            $cssRaw = is_file($cssPath) ? (string) file_get_contents($cssPath) : '';
            t_assert($cssRaw !== '' && substr_count($cssRaw, '{') === substr_count($cssRaw, '}'), "Built CSS bundle {$cssKey} has balanced curly braces");
        }
        $critRaw = is_file(APP_ROOT . '/' . ($manifest['critical_css_file'] ?? '')) ? (string) file_get_contents(APP_ROOT . '/' . $manifest['critical_css_file']) : '';
        $appCssRaw = (string) file_get_contents(APP_ROOT . '/' . $manifest['files']['app_css']['path']);
        t_assert($critRaw !== '' && substr_count($critRaw, '{') === substr_count($critRaw, '}'), 'Built critical CSS bundle has balanced curly braces');
        t_assert(str_contains($appCssRaw, '.bx-footer-links') && str_contains($appCssRaw, '.bx-pw-wrap'), 'Deferred CSS includes footer links and auth password wrapper');
    }

    t_assert(is_file(APP_ROOT . '/assets/fonts/hind-bn-400.woff2') && is_file(APP_ROOT . '/assets/fonts/hind-lat-400.woff2'), 'Subsetted WOFF2 webfonts exist on disk');
    t_assert(is_file(APP_ROOT . '/sw.js') && str_contains((string) file_get_contents(APP_ROOT . '/sw.js'), "path.includes('/api/')"), 'Service Worker exists and excludes protected API/auth HTML routes');

    $sampleItems = db_all('SELECT id FROM items WHERE is_active = 1 LIMIT 5');
    preload_items_card_meta($sampleItems);
    $store = item_rating_cache_store();
    t_assert(empty($sampleItems) || isset($store[(int) $sampleItems[0]['id']]), 'preload_items_card_meta populates static rating batch cache');
});

// ----------------------------------------------------------------------------
// 15. Indexing, Privacy-Friendly Analytics, SEO Health & Growth Tools
// ----------------------------------------------------------------------------
t_case('Indexing, Privacy Analytics, SEO Dashboard & Growth', static function (): void {
    // Regression: fresh-DB cold key generation must persist cleanly across calls without cache-mismatch key rotation
    $origKey = db_one("SELECT setting_value FROM settings WHERE setting_key = 'indexnow_key'")['setting_value'] ?? null;
    db_exec("DELETE FROM settings WHERE setting_key = 'indexnow_key'");
    site_setting_store_ref(true);
    $freshKey = growth_indexnow_key(true);
    $freshUrl = growth_indexnow_key_url();
    t_assert(
        (bool) preg_match('/^[a-f0-9]{32,64}$/i', $freshKey)
        && str_ends_with($freshUrl, '/' . $freshKey . '.txt')
        && site_setting('indexnow_key') === $freshKey,
        'IndexNow key URL points to consistent hosted verification .txt route on fresh DB without cache drift'
    );
    if (is_string($origKey) && $origKey !== '') {
        site_setting_set('indexnow_key', $origKey);
    }
    $key = growth_indexnow_key();
    t_assert((bool) preg_match('/^[a-f0-9]{32,64}$/i', $key), 'IndexNow auto-generated verification key is 32+ hex chars');
    t_assert(str_ends_with(growth_indexnow_key_url(), '/' . $key . '.txt'), 'IndexNow key URL points to hosted verification .txt route');

    $qCnt = growth_indexnow_queue_entity('book', 'ut-indexnow-' . bin2hex(random_bytes(3)), 'update');
    t_assert($qCnt === 2, 'IndexNow queues both Bangla and English canonical URLs on book update');
    db_exec("DELETE FROM indexnow_queue WHERE url LIKE '%ut-indexnow-%'");

    // Analytics privacy & DNT honors
    t_assert(growth_country_from_accept_lang('bn-BD,bn;q=0.9,en;q=0.8') === 'BD', 'Country detection extracts region code from Accept-Language header only');
    t_assert(growth_device_class('Mozilla/5.0 (iPhone; CPU iPhone OS 17_0 like Mac OS X) Mobile/15E148') === 'mobile', 'Device class detects mobile cleanly');
    $_SERVER['HTTP_DNT'] = '1';
    t_assert(growth_dnt_active() === true && growth_record_analytics_event('pageview', '/about') === false, 'Analytics honors Do Not Track (DNT: 1) header and records zero events');
    unset($_SERVER['HTTP_DNT'], $_SERVER['HTTP_SEC_GPC']);

    $_SERVER['HTTP_ACCEPT_LANGUAGE'] = 'bn-BD,bn;q=0.9';
    $_SERVER['HTTP_USER_AGENT'] = 'Mozilla/5.0 (X11; Linux x86_64) Chrome/120.0';
    $evOk = growth_record_analytics_event('pageview', '/ut-analytics-probe', ['load_ms' => 310]);
    t_assert($evOk === true, 'Privacy-friendly first-party analytics records pageview when DNT is inactive');
    t_assert(growth_record_analytics_event('pageview', '/admin/orders') === false, 'Analytics ignores /admin routes');
    db_exec("DELETE FROM analytics_events WHERE path = '/ut-analytics-probe'");

    $auditSnap = growth_seo_audit_snapshot();
    t_assert(
        array_key_exists('missing_meta', $auditSnap)
        && array_key_exists('duplicate_titles', $auditSnap)
        && array_key_exists('thin_content', $auditSnap)
        && array_key_exists('orphan_pages', $auditSnap)
        && array_key_exists('missing_alt', $auditSnap)
        && array_key_exists('broken_links', $auditSnap)
        && array_key_exists('redirect_chains', $auditSnap)
        && array_key_exists('top_404s', $auditSnap)
        && array_key_exists('slow_pages', $auditSnap)
        && array_key_exists('content_ideas', $auditSnap),
        'SEO dashboard snapshot returns all 10 health & content-idea metrics'
    );

    $chk = growth_link_checker_tick(2);
    t_assert(isset($chk['checked']) && $chk['checked'] >= 0, 'Background link checker tick executes safely without stalling');

    $checklist = growth_search_console_checklist();
    $catalog = growth_indexable_urls_catalog();
    t_assert(count($checklist) >= 5 && count($catalog) >= 10, 'Search Console checklist and indexable URL CSV catalog populated');

    // Newsletter double opt-in lifecycle
    $testEmail = 'growth-ut-' . bin2hex(random_bytes(4)) . '@example.com';
    $subRes = growth_newsletter_subscribe($testEmail, 'bn', '/guides');
    t_assert(!empty($subRes['ok']), 'Newsletter subscribe stores pending subscriber & verification token hash');
    $subRow = db_one('SELECT * FROM newsletter_subscribers WHERE email = ? LIMIT 1', [$testEmail]);
    t_assert($subRow && $subRow['status'] === 'pending' && !empty($subRow['unsubscribe_token']), 'Pending newsletter subscriber has unsubscribe_token and pending status');
    $rawTok = '';
    if (!empty($subRes['confirm_url']) && preg_match('/token=([a-f0-9]+)/i', (string) $subRes['confirm_url'], $tm)) {
        $rawTok = $tm[1];
    }
    if ($rawTok !== '') {
        $conf = growth_newsletter_confirm($rawTok);
        t_assert(!empty($conf['ok']), 'Double opt-in confirmation activates newsletter subscriber');
    } else {
        $results['skip']++;
    }
    t_assert(growth_newsletter_unsubscribe((string) $subRow['unsubscribe_token']) === true, 'Newsletter unsubscribe token deactivates subscriber');
    db_exec('DELETE FROM newsletter_subscribers WHERE email = ?', [$testEmail]);
});

// ----------------------------------------------------------------------------
// 16. In-Book Reader Search (/api/reader-search): Min Length 2 & LIKE Escaping
// ----------------------------------------------------------------------------
t_case('In-Book Search (/api/reader-search) Escaping & Min Length', static function (): void {
    $sfx = substr(bin2hex(random_bytes(3)), 0, 6);
    $fid = 0;
    $iid = 0;
    try {
        db_exec('INSERT INTO folders (slug, name_bn, name_en, is_active) VALUES (?, ?, ?, 1)', ["ut-rs-f-{$sfx}", 'রিডার ফোল্ডার', 'Reader Search Folder']);
        $fid = (int) db()->lastInsertId();
        db_exec('INSERT INTO items (folder_id, slug, title_bn, title_en, price, is_free, is_active) VALUES (?, ?, ?, ?, 0, 1, 1)', [$fid, "ut-rs-b-{$sfx}", 'সার্চ বই', 'Search Test Book']);
        $iid = (int) db()->lastInsertId();

        db_exec('INSERT INTO item_page_texts (item_id, page_num, is_staging, norm_text) VALUES (?, 1, 0, ?)', [$iid, '100% pure ai physics note ch_1 আর বই আছে']);
        db_exec('INSERT INTO item_page_texts (item_id, page_num, is_staging, norm_text) VALUES (?, 2, 0, ?)', [$iid, 'ordinary syllabus overview chapter two']);

        t_assert(PageServer::searchPages($iid, 'a') === [] && PageServer::searchPages($iid, 'ব') === [], 'Minimum query length (<2 chars) returns empty results for English and Bangla');
        t_assert(PageServer::searchPages($iid, '%') === [] && PageServer::searchPages($iid, '_') === [], 'Single wildcard char (<2 chars) rejected');

        $enTwo = PageServer::searchPages($iid, 'ai');
        $bnTwo = PageServer::searchPages($iid, 'বই');
        t_assert(count($enTwo) === 1 && (int) $enTwo[0]['page'] === 1, '2-char English query ("ai") succeeds and finds matching page');
        t_assert(count($bnTwo) === 1 && (int) $bnTwo[0]['page'] === 1, '2-char Bangla query ("বই") succeeds and finds matching page');

        $pctExact = PageServer::searchPages($iid, '100%');
        $pctWild  = PageServer::searchPages($iid, '%%');
        $undExact = PageServer::searchPages($iid, 'ch_1');
        $undMiss  = PageServer::searchPages($iid, 'ch_9');
        $undWild  = PageServer::searchPages($iid, '__');
        t_assert(count($pctExact) === 1 && (int) $pctExact[0]['page'] === 1 && $pctWild === [], 'Percent (%) wildcards are escaped in reader search');
        t_assert(count($undExact) === 1 && (int) $undExact[0]['page'] === 1 && $undMiss === [] && $undWild === [], 'Underscore (_) wildcards are escaped in reader search');
    } finally {
        if ($iid > 0) {
            db_exec('DELETE FROM item_page_texts WHERE item_id = ?', [$iid]);
            db_exec('DELETE FROM items WHERE id = ?', [$iid]);
        }
        if ($fid > 0) db_exec('DELETE FROM folders WHERE id = ?', [$fid]);
    }
});

// ----------------------------------------------------------------------------
// Summary
// ----------------------------------------------------------------------------
echo "\n==================================================\n";
echo "TEST SUMMARY: {$results['pass']} passed, {$results['fail']} failed, {$results['skip']} skipped.\n";
exit($results['fail'] > 0 ? 1 : 0);
