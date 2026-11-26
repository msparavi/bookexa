<?php
declare(strict_types=1);

/**
 * Guided Bookexa Setup Wizard (/install)
 * Disabled automatically once config/installed.lock exists.
 */
function bookexa_install_split_sql(string $sql): array
{
    $lines = preg_split("/\r\n|\n|\r/", $sql) ?: [];
    $clean = [];
    foreach ($lines as $ln) {
        $trimmed = ltrim($ln);
        if (str_starts_with($trimmed, '--') || $trimmed === '') {
            continue;
        }
        $clean[] = $ln;
    }
    $joined = implode("\n", $clean);
    $parts = explode(";\n", $joined);
    $stmts = [];
    foreach ($parts as $p) {
        $p = trim($p);
        if ($p !== '' && $p !== ';') {
            $stmts[] = rtrim($p, ';');
        }
    }
    return $stmts;
}

function bookexa_install_parse_bytes(string $val): int
{
    $val = trim($val);
    if ($val === '' || $val === '-1') {
        return -1;
    }
    $last = strtolower($val[strlen($val) - 1]);
    $num = (int) $val;
    return match ($last) {
        'g' => $num * 1024 * 1024 * 1024,
        'm' => $num * 1024 * 1024,
        'k' => $num * 1024,
        default => $num,
    };
}

if (session_status() === PHP_SESSION_NONE) {
    $isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (($_SERVER['SERVER_PORT'] ?? '') === '443');
    session_set_cookie_params(['lifetime' => 0, 'path' => '/', 'secure' => $isHttps, 'httponly' => true, 'samesite' => 'Lax']);
    session_name('bookexa_install_sid');
    session_start();
}

if (empty($_SESSION['_install_csrf'])) {
    $_SESSION['_install_csrf'] = bin2hex(random_bytes(32));
}
$installCsrf = $_SESSION['_install_csrf'];

$scriptDir = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/index.php'));
$basePath = ($scriptDir === '/' || $scriptDir === '.' || $scriptDir === '\\') ? '' : rtrim($scriptDir, '/');
$scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$suggestedBase = $scheme . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost') . $basePath;

// Environment checks
$memRaw = (string) ini_get('memory_limit');
$memBytes = bookexa_install_parse_bytes($memRaw);
$memOk = ($memBytes === -1 || $memBytes >= 128 * 1024 * 1024);
$modRewriteStatus = 'OK (Unknown under CGI/FPM — verify /pdf works)';
$modRewriteOk = true;
if (function_exists('apache_get_modules')) {
    $mods = apache_get_modules();
    $modRewriteOk = in_array('mod_rewrite', $mods, true);
    $modRewriteStatus = $modRewriteOk ? 'Enabled (Apache mod_rewrite)' : 'MISSING (Enable mod_rewrite)';
}

$writableDirs = ['config', 'storage', 'storage/cache', 'storage/pages', 'storage/logs', 'uploads'];
$dirsWritable = true;
foreach ($writableDirs as $wd) {
    $p = APP_ROOT . '/' . $wd;
    if (!is_dir($p)) {
        @mkdir($p, 0755, true);
    }
    if (!is_writable($p)) {
        $dirsWritable = false;
    }
}

$gdInfo = function_exists('gd_info') ? gd_info() : [];
$checks = [
    'PHP >= 8.1 (' . PHP_VERSION . ')'     => version_compare(PHP_VERSION, '8.1.0', '>='),
    'pdo_mysql'                             => extension_loaded('pdo_mysql'),
    'curl'                                  => extension_loaded('curl'),
    'gd + WebP/FreeType'                    => extension_loaded('gd') && !empty($gdInfo['FreeType Support']),
    'mbstring'                              => extension_loaded('mbstring'),
    'fileinfo'                              => extension_loaded('fileinfo'),
    'openssl'                               => extension_loaded('openssl'),
    'zlib'                                  => extension_loaded('zlib'),
    'Writable Folders (config/storage/uploads)' => $dirsWritable,
    'memory_limit (' . ($memRaw ?: 'N/A') . ')' => $memOk,
    'mod_rewrite: ' . $modRewriteStatus     => $modRewriteOk,
];

$error = null;
$infoMsg = null;
$done = false;
$installedSummary = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $tok = (string) ($_POST['_csrf'] ?? '');
    $wizAction = (string) ($_POST['wizard_action'] ?? 'install');
    if (!hash_equals($installCsrf, $tok)) {
        $error = 'Invalid security token. Please reload and try again.';
    } else {
        $dbHost   = trim((string) ($_POST['db_host'] ?? 'localhost'));
        $dbPort   = trim((string) ($_POST['db_port'] ?? '3306')) ?: '3306';
        $dbName   = trim((string) ($_POST['db_name'] ?? ''));
        $dbUser   = trim((string) ($_POST['db_user'] ?? ''));
        $dbPass   = (string) ($_POST['db_pass'] ?? '');
        $createDb = !empty($_POST['create_db']);

        // Step 2 action: Test DB connection & optionally create DB
        if ($wizAction === 'test_db') {
            if ($dbName === '' || $dbUser === '') {
                $error = 'Enter Database Name and Database User before testing.';
            } else {
                try {
                    $srvDsn = "mysql:host={$dbHost};port={$dbPort};charset=utf8mb4";
                    $srvPdo = new PDO($srvDsn, $dbUser, $dbPass, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
                    if ($createDb) {
                        $safeDb = preg_replace('/[^a-zA-Z0-9_$-]/', '', $dbName) ?: 'bookexa';
                        $srvPdo->exec("CREATE DATABASE IF NOT EXISTS `{$safeDb}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
                        $dbName = $safeDb;
                    }
                    $srvPdo->exec("USE `{$dbName}`");
                    $infoMsg = "Database connection succeeded (`{$dbName}` ready on {$dbHost}:{$dbPort}).";
                } catch (Throwable $e) {
                    $error = 'Database test failed: ' . $e->getMessage();
                }
            }
        } elseif ($wizAction === 'test_smtp') {
            require_once APP_ROOT . '/includes/mailer.php';
            $smtpTo = trim((string) ($_POST['smtp_test_to'] ?? ''));
            $sHost  = trim((string) ($_POST['smtp_host'] ?? ''));
            $sPort  = max(1, (int) ($_POST['smtp_port'] ?? 587));
            $sEnc   = in_array((string) ($_POST['smtp_encryption'] ?? 'tls'), ['tls', 'ssl', 'none'], true) ? (string) $_POST['smtp_encryption'] : 'tls';
            $sUser  = trim((string) ($_POST['smtp_user'] ?? ''));
            $sPass  = (string) ($_POST['smtp_pass'] ?? '');
            $sFrom  = trim((string) ($_POST['smtp_from_email'] ?? '')) ?: 'no-reply@bookexa.com';
            $sName  = trim((string) ($_POST['site_name'] ?? 'Bookexa')) ?: 'Bookexa';

            if ($sHost === '' || !filter_var($smtpTo, FILTER_VALIDATE_EMAIL)) {
                $error = 'Enter valid SMTP Host and Test Recipient Email first.';
            } else {
                $mailRes = smtp_send_mail(
                    $smtpTo,
                    'Bookexa Installer SMTP Verification',
                    '<p>Your Bookexa SMTP mail transport test succeeded!</p>',
                    'Your Bookexa SMTP mail transport test succeeded!',
                    ['host' => $sHost, 'port' => $sPort, 'encryption' => $sEnc, 'user' => $sUser, 'pass' => $sPass, 'from_name' => $sName, 'from_email' => $sFrom]
                );
                if (!empty($mailRes['ok'])) {
                    $infoMsg = "Test email sent cleanly to {$smtpTo} via {$sHost}:{$sPort}.";
                } else {
                    $error = 'SMTP test failed: ' . ($mailRes['error'] ?? 'Unknown error');
                }
            }
        } else {
            // Final Install & Lock
            $siteName = trim((string) ($_POST['site_name'] ?? 'Bookexa')) ?: 'Bookexa';
            $appEnv   = ($_POST['app_env'] ?? 'production') === 'local' ? 'local' : 'production';
            $baseUrl  = rtrim(trim((string) ($_POST['base_url'] ?? '')), '/');
            $admName  = trim((string) ($_POST['admin_name'] ?? ''));
            $admEmail = strtolower(trim((string) ($_POST['admin_email'] ?? '')));
            $admPw    = (string) ($_POST['admin_password'] ?? '');
            $admPw2   = (string) ($_POST['admin_password_confirm'] ?? '');
            $demoMode = !empty($_POST['demo_mode']);
            $seedDemo = !empty($_POST['seed_demo']);
            $cdnOn    = !empty($_POST['cdn_enabled']);
            $cdnDrv   = in_array((string) ($_POST['cdn_driver'] ?? 'filesystem'), ['filesystem', 'ftp', 'http'], true) ? (string) $_POST['cdn_driver'] : 'filesystem';
            $cdnUrl   = rtrim(trim((string) ($_POST['cdn_base_url'] ?? '')), '/');
            $cdnPath  = rtrim(str_replace('\\', '/', trim((string) ($_POST['cdn_path'] ?? ''))), '/');

            if ($dbName === '' || $dbUser === '' || $admName === '' || !filter_var($admEmail, FILTER_VALIDATE_EMAIL)) {
                $error = 'Database name/user, admin name, and valid admin email are required.';
            } elseif (strlen($admPw) < 8) {
                $error = 'Admin password must be at least 8 characters.';
            } elseif ($admPw !== $admPw2) {
                $error = 'Admin password confirmation does not match.';
            } else {
                try {
                    $srvDsn = "mysql:host={$dbHost};port={$dbPort};charset=utf8mb4";
                    $srvPdo = new PDO($srvDsn, $dbUser, $dbPass, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
                    if ($createDb) {
                        $safeDb = preg_replace('/[^a-zA-Z0-9_$-]/', '', $dbName) ?: 'bookexa';
                        $srvPdo->exec("CREATE DATABASE IF NOT EXISTS `{$safeDb}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
                        $dbName = $safeDb;
                    }

                    $dsn = "mysql:host={$dbHost};port={$dbPort};dbname={$dbName};charset=utf8mb4";
                    $pdo = new PDO($dsn, $dbUser, $dbPass, [
                        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                        PDO::ATTR_EMULATE_PREPARES   => false,
                    ]);

                    $schemaPath = APP_ROOT . '/database/bookexa.sql';
                    if (!is_file($schemaPath)) {
                        throw new RuntimeException('Missing database/bookexa.sql schema file.');
                    }
                    foreach (bookexa_install_split_sql((string) file_get_contents($schemaPath)) as $sql) {
                        $pdo->exec($sql);
                    }

                    if ($seedDemo && is_file(APP_ROOT . '/database/demo_seed.sql')) {
                        foreach (bookexa_install_split_sql((string) file_get_contents(APP_ROOT . '/database/demo_seed.sql')) as $dsql) {
                            $pdo->exec($dsql);
                        }
                    }

                    require_once APP_ROOT . '/includes/migrations.php';
                    migration_mark_all_applied($pdo);

                    foreach (['folders', 'covers', 'banners', 'products', 'branding'] as $sub) {
                        $uDir = APP_ROOT . '/uploads/' . $sub;
                        if (!is_dir($uDir)) {
                            continue;
                        }
                        foreach (scandir($uDir) ?: [] as $fName) {
                            $rel = 'uploads/' . $sub . '/' . $fName;
                            if (is_file(APP_ROOT . '/' . $rel) && preg_match('#^uploads/(folders|covers|banners|products|branding)/[A-Za-z0-9][A-Za-z0-9._-]*\.(jpg|jpeg|png|webp|gif|ico|svg)$#i', $rel)) {
                                $stMedia = $pdo->prepare('INSERT INTO media_sync (rel_path, local_exists, cdn_synced, size, sha1) VALUES (?, 1, 0, ?, ?) ON DUPLICATE KEY UPDATE local_exists = 1, size = VALUES(size), sha1 = VALUES(sha1)');
                                $stMedia->execute([$rel, (int) @filesize(APP_ROOT . '/' . $rel), (string) @sha1_file(APP_ROOT . '/' . $rel)]);
                            }
                        }
                    }

                    $hash = password_hash($admPw, PASSWORD_BCRYPT);
                    $stAdm = $pdo->prepare(
                        "INSERT INTO admins (name, email, password_hash, role, is_active, must_change_password)
                         VALUES (?, ?, ?, 'superadmin', 1, 0)
                         ON DUPLICATE KEY UPDATE name = VALUES(name), password_hash = VALUES(password_hash), role = 'superadmin', is_active = 1, must_change_password = 0"
                    );
                    $stAdm->execute([$admName, $admEmail, $hash]);

                    $settingPairs = [
                        'site_name' => $siteName,
                        'demo_mode' => $demoMode ? '1' : '0',
                    ];
                    if (trim((string) ($_POST['smtp_host'] ?? '')) !== '') {
                        $settingPairs['smtp_host'] = trim((string) $_POST['smtp_host']);
                        $settingPairs['smtp_port'] = (string) max(1, (int) ($_POST['smtp_port'] ?? 587));
                        $settingPairs['smtp_encryption'] = in_array((string) ($_POST['smtp_encryption'] ?? 'tls'), ['tls', 'ssl', 'none'], true) ? (string) $_POST['smtp_encryption'] : 'tls';
                        $settingPairs['smtp_user'] = trim((string) ($_POST['smtp_user'] ?? ''));
                        $settingPairs['smtp_pass'] = (string) ($_POST['smtp_pass'] ?? '');
                        $settingPairs['smtp_from_name'] = $siteName;
                        $settingPairs['smtp_from_email'] = trim((string) ($_POST['smtp_from_email'] ?? '')) ?: 'no-reply@bookexa.com';
                    }
                    $stSet = $pdo->prepare('INSERT INTO settings (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)');
                    foreach ($settingPairs as $sk => $sv) {
                        $stSet->execute([$sk, $sv]);
                    }

                    $appSecret = 'bx_' . bin2hex(random_bytes(32));
                    $cfgArr = [
                        'app_name'    => $siteName,
                        'app_version' => '2.4.0',
                        'app_env'     => $appEnv,
                        'demo_mode'   => $demoMode,
                        'timezone'    => 'Asia/Dhaka',
                        'base_url'    => $baseUrl,
                        'base_path'   => '',
                        'db'          => [
                            'host'    => $dbHost,
                            'port'    => $dbPort,
                            'name'    => $dbName,
                            'user'    => $dbUser,
                            'pass'    => $dbPass,
                            'charset' => 'utf8mb4',
                        ],
                        'security'    => [
                            'app_secret'        => $appSecret,
                            'pdf_cache_enabled' => true,
                            'pdf_cache_ttl'     => 21600,
                        ],
                        'cdn'         => [
                            'enabled'    => $cdnOn,
                            'driver'     => $cdnDrv,
                            'base_url'   => $cdnUrl,
                            'path'       => $cdnPath,
                            'ftp'        => ['host' => '', 'port' => 21, 'user' => '', 'pass' => '', 'root' => '/', 'ssl' => true],
                            'http'       => ['endpoint' => '', 'secret' => ''],
                            'sync_batch' => 10,
                        ],
                    ];
                    $cfgContent = "<?php\n/** Auto-generated by Bookexa Setup Wizard */\nreturn " . var_export($cfgArr, true) . ";\n";
                    if (@file_put_contents(APP_ROOT . '/config/config.php', $cfgContent, LOCK_EX) === false) {
                        throw new RuntimeException('Could not write config/config.php. Check folder permissions on config/.');
                    }
                    @chmod(APP_ROOT . '/config/config.php', 0640);
                    @file_put_contents(APP_ROOT . '/config/installed.lock', 'installed_at=' . date('c') . "\nmode=" . $appEnv . "\nversion=2.4.0\n", LOCK_EX);

                    $installedSummary = [
                        'site_name' => $siteName,
                        'admin_email' => $admEmail,
                        'smtp_set' => !empty($settingPairs['smtp_host']),
                        'cdn_set' => $cdnOn,
                        'https' => $scheme === 'https',
                        'demo_mode' => $demoMode,
                    ];
                    $done = true;
                } catch (Throwable $e) {
                    $error = 'Installation failed: ' . $e->getMessage();
                }
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en" data-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow, noarchive">
    <title>Bookexa v2.4.0 — Setup Wizard</title>
    <link rel="stylesheet" href="<?= htmlspecialchars($basePath . '/assets/css/fonts.css', ENT_QUOTES) ?>">
    <link rel="stylesheet" href="<?= htmlspecialchars($basePath . '/assets/css/tokens.css', ENT_QUOTES) ?>">
    <link rel="stylesheet" href="<?= htmlspecialchars($basePath . '/assets/css/components.css', ENT_QUOTES) ?>">
</head>
<body style="padding:24px 14px;background:var(--bg);color:var(--text);">
<div class="container" style="max-width:780px;">
    <div class="bx-card" style="padding:clamp(20px,4vw,34px);">
        <div style="display:flex;justify-content:space-between;align-items:center;gap:10px;flex-wrap:wrap;margin-bottom:8px;">
            <span class="bx-badge bx-badge-teal">Bookexa v2.4.0 Setup Wizard</span>
            <span class="bx-muted" style="font-size:0.84rem;">5-Step Guided Installer</span>
        </div>
        <h1 class="bx-display" style="font-size:1.75rem;margin-bottom:6px;">Install &amp; Configure Bookexa</h1>
        <p class="bx-muted" style="margin:0 0 18px;">Checks server extensions, verifies/creates MySQL schema, generates a unique cryptographic secret, tests SMTP, and secures your Admin account.</p>

        <?php if ($done): ?>
            <div class="bx-auth-alert is-success" style="margin-bottom:18px;">
                <strong>Installation Complete!</strong> Written to <code>config/config.php</code> and locked via <code>config/installed.lock</code> (<code>/install</code> now returns <code>403 Forbidden</code>).
            </div>

            <div style="padding:16px;border-radius:14px;background:var(--bg-alt);border:1px solid var(--border);margin-bottom:20px;">
                <h2 class="bx-h3" style="margin-bottom:10px;">Final "Go Live" Checklist</h2>
                <ul style="margin:0;padding-left:20px;line-height:1.85;font-size:0.92rem;">
                    <li><strong>Installer Lock:</strong> <code>config/installed.lock</code> created (✓)</li>
                    <li><strong>Config Permissions:</strong> <code>config/config.php</code> set to read/owner mode <code>0640</code> (✓)</li>
                    <li><strong>HTTPS / TLS:</strong> <?= !empty($installedSummary['https']) ? 'Active (✓)' : 'Enable AutoSSL/HTTPS in cPanel before accepting live payments' ?></li>
                    <li><strong>Email (SMTP):</strong> <?= !empty($installedSummary['smtp_set']) ? 'Saved cleanly — verify order emails in Admin Settings' : 'Configure SMTP credentials in Admin → Settings for password resets & order emails' ?></li>
                    <li><strong>White-Label &amp; Payments:</strong> Set your bKash/Nagad/Rocket numbers, colors/logo, and Bangla/English legal notices in Admin → Settings.</li>
                    <li><strong>CDN Sync:</strong> <?= !empty($installedSummary['cdn_set']) ? 'Enabled — sync existing assets via Admin → Media &amp; CDN' : 'Optional — running on local <code>/uploads/</code> (enable anytime without migrating DB)' ?></li>
                </ul>
            </div>

            <div style="display:flex;gap:12px;flex-wrap:wrap;">
                <a href="<?= htmlspecialchars($basePath . '/admin/login', ENT_QUOTES) ?>" class="bx-btn bx-btn-primary">Sign In to Admin Panel</a>
                <a href="<?= htmlspecialchars($basePath . '/', ENT_QUOTES) ?>" class="bx-btn bx-btn-outline">Visit Storefront</a>
            </div>
        <?php else: ?>
            <!-- Step 1: Environment Check -->
            <div style="padding:14px;border-radius:12px;background:var(--bg-alt);border:1px solid var(--border);margin-bottom:18px;">
                <strong style="display:block;margin-bottom:8px;">Step 1 — Server Environment Pre-Flight:</strong>
                <div style="display:flex;flex-wrap:wrap;gap:8px;">
                    <?php foreach ($checks as $label => $ok): ?>
                        <span class="bx-badge <?= $ok ? 'bx-badge-success' : 'bx-badge-accent' ?>"><?= htmlspecialchars($label . ': ' . ($ok ? 'OK' : 'CHECK'), ENT_QUOTES) ?></span>
                    <?php endforeach; ?>
                </div>
            </div>

            <?php if ($infoMsg): ?>
                <div class="bx-auth-alert is-success" style="margin-bottom:14px;"><?= htmlspecialchars($infoMsg, ENT_QUOTES) ?></div>
            <?php endif; ?>
            <?php if ($error): ?>
                <div class="bx-auth-alert is-danger" style="margin-bottom:14px;"><?= htmlspecialchars($error, ENT_QUOTES) ?></div>
            <?php endif; ?>

            <form method="post">
                <input type="hidden" name="_csrf" value="<?= htmlspecialchars($installCsrf, ENT_QUOTES) ?>">

                <!-- Step 2: Database Setup & Test -->
                <h2 class="bx-h2" style="font-size:1.12rem;margin:14px 0 8px;">Step 2 — MySQL / MariaDB Connection</h2>
                <div class="bx-grid-auto-sm">
                    <div class="bx-field"><label>DB Host</label><input class="bx-input" name="db_host" required value="<?= htmlspecialchars((string) ($_POST['db_host'] ?? 'localhost'), ENT_QUOTES) ?>"></div>
                    <div class="bx-field"><label>DB Port</label><input class="bx-input" name="db_port" required value="<?= htmlspecialchars((string) ($_POST['db_port'] ?? '3306'), ENT_QUOTES) ?>"></div>
                    <div class="bx-field"><label>DB Name *</label><input class="bx-input" name="db_name" required value="<?= htmlspecialchars((string) ($_POST['db_name'] ?? 'bookexa'), ENT_QUOTES) ?>"></div>
                    <div class="bx-field"><label>DB User *</label><input class="bx-input" name="db_user" required value="<?= htmlspecialchars((string) ($_POST['db_user'] ?? 'root'), ENT_QUOTES) ?>"></div>
                </div>
                <div style="display:flex;gap:12px;flex-wrap:wrap;align-items:flex-end;margin-bottom:14px;">
                    <div class="bx-field" style="flex:1 1 200px;margin:0;">
                        <label>DB Password</label>
                        <input class="bx-input" type="password" name="db_pass" value="<?= htmlspecialchars((string) ($_POST['db_pass'] ?? ''), ENT_QUOTES) ?>" autocomplete="new-password">
                    </div>
                    <label style="display:inline-flex;align-items:center;gap:6px;min-height:44px;font-weight:600;font-size:0.86rem;">
                        <input type="checkbox" name="create_db" value="1" <?= !empty($_POST['create_db']) ? 'checked' : '' ?>>
                        Create DB if missing
                    </label>
                    <button type="submit" name="wizard_action" value="test_db" formnovalidate class="bx-btn bx-btn-outline bx-btn-sm">Test Database</button>
                </div>

                <!-- Step 3: Site, Admin, Demo Mode & Optional CDN -->
                <h2 class="bx-h2" style="font-size:1.12rem;margin:18px 0 8px;">Step 3 — Site, Super Admin &amp; Optional CDN</h2>
                <div class="bx-grid-auto-md">
                    <div class="bx-field">
                        <label>Site Name</label>
                        <input class="bx-input" name="site_name" required value="<?= htmlspecialchars((string) ($_POST['site_name'] ?? 'Bookexa'), ENT_QUOTES) ?>">
                    </div>
                    <div class="bx-field">
                        <label>Environment</label>
                        <select class="bx-select" name="app_env">
                            <option value="production" <?= ($_POST['app_env'] ?? 'production') === 'production' ? 'selected' : '' ?>>Production (cPanel / Live)</option>
                            <option value="local" <?= ($_POST['app_env'] ?? '') === 'local' ? 'selected' : '' ?>>Local Development</option>
                        </select>
                    </div>
                </div>
                <div class="bx-field">
                    <label>Canonical Base URL (leave empty for auto-detection)</label>
                    <input class="bx-input" name="base_url" placeholder="<?= htmlspecialchars($suggestedBase, ENT_QUOTES) ?>" value="<?= htmlspecialchars((string) ($_POST['base_url'] ?? ''), ENT_QUOTES) ?>">
                </div>

                <div class="bx-grid-auto-md">
                    <div class="bx-field"><label>Admin Name *</label><input class="bx-input" name="admin_name" required value="<?= htmlspecialchars((string) ($_POST['admin_name'] ?? 'Site Administrator'), ENT_QUOTES) ?>"></div>
                    <div class="bx-field"><label>Admin Email *</label><input class="bx-input" type="email" name="admin_email" required value="<?= htmlspecialchars((string) ($_POST['admin_email'] ?? ''), ENT_QUOTES) ?>"></div>
                    <div class="bx-field"><label>Admin Password (min 8) *</label><input class="bx-input" type="password" name="admin_password" required minlength="8" autocomplete="new-password"></div>
                    <div class="bx-field"><label>Confirm Password *</label><input class="bx-input" type="password" name="admin_password_confirm" required minlength="8" autocomplete="new-password"></div>
                </div>

                <div style="display:flex;gap:16px;flex-wrap:wrap;margin-bottom:12px;">
                    <label style="display:inline-flex;align-items:center;gap:6px;min-height:40px;font-weight:600;">
                        <input type="checkbox" name="seed_demo" value="1" <?= !empty($_POST['seed_demo']) ? 'checked' : '' ?>>
                        Seed sample catalog &amp; demo books
                    </label>
                    <label style="display:inline-flex;align-items:center;gap:6px;min-height:40px;font-weight:600;">
                        <input type="checkbox" name="demo_mode" value="1" <?= !empty($_POST['demo_mode']) ? 'checked' : '' ?>>
                        Enable Demo Mode (disables destructive actions)
                    </label>
                </div>

                <details style="margin-bottom:16px;padding:10px 12px;border-radius:10px;background:var(--bg-alt);border:1px solid var(--border);">
                    <summary style="cursor:pointer;font-weight:700;">Optional — Configure Separate Media CDN</summary>
                    <label style="display:inline-flex;align-items:center;gap:6px;margin-top:10px;font-weight:600;">
                        <input type="checkbox" name="cdn_enabled" value="1" <?= !empty($_POST['cdn_enabled']) ? 'checked' : '' ?>> Enable CDN Image Serving
                    </label>
                    <div class="bx-grid-auto-sm" style="margin-top:8px;">
                        <div class="bx-field"><label>Driver</label><select name="cdn_driver" class="bx-select"><option value="filesystem">Filesystem (Same Server Subdomain)</option><option value="ftp">FTP / FTPS</option><option value="http">HTTP Push Receiver</option></select></div>
                        <div class="bx-field"><label>CDN Base URL</label><input class="bx-input" name="cdn_base_url" placeholder="https://cdn.example.com/bookexa" value="<?= htmlspecialchars((string) ($_POST['cdn_base_url'] ?? ''), ENT_QUOTES) ?>"></div>
                        <div class="bx-field"><label>CDN Local Path</label><input class="bx-input" name="cdn_path" placeholder="/home/USER/cdn.example.com" value="<?= htmlspecialchars((string) ($_POST['cdn_path'] ?? ''), ENT_QUOTES) ?>"></div>
                    </div>
                </details>

                <!-- Step 4: Optional SMTP Test -->
                <details style="margin-bottom:18px;padding:10px 12px;border-radius:10px;background:var(--bg-alt);border:1px solid var(--border);">
                    <summary style="cursor:pointer;font-weight:700;">Step 4 (Optional) — SMTP Mail Settings &amp; Live Test</summary>
                    <div class="bx-grid-auto-sm" style="margin-top:10px;">
                        <div class="bx-field"><label>SMTP Host</label><input class="bx-input" name="smtp_host" placeholder="smtp.example.com" value="<?= htmlspecialchars((string) ($_POST['smtp_host'] ?? ''), ENT_QUOTES) ?>"></div>
                        <div class="bx-field"><label>Port</label><input class="bx-input" type="number" name="smtp_port" value="<?= htmlspecialchars((string) ($_POST['smtp_port'] ?? '587'), ENT_QUOTES) ?>"></div>
                        <div class="bx-field"><label>Encryption</label><select class="bx-select" name="smtp_encryption"><option value="tls">STARTTLS (587)</option><option value="ssl">SSL (465)</option><option value="none">None</option></select></div>
                    </div>
                    <div class="bx-grid-auto-sm">
                        <div class="bx-field"><label>SMTP User</label><input class="bx-input" name="smtp_user" value="<?= htmlspecialchars((string) ($_POST['smtp_user'] ?? ''), ENT_QUOTES) ?>"></div>
                        <div class="bx-field"><label>SMTP Pass</label><input class="bx-input" type="password" name="smtp_pass" value="<?= htmlspecialchars((string) ($_POST['smtp_pass'] ?? ''), ENT_QUOTES) ?>" autocomplete="new-password"></div>
                        <div class="bx-field"><label>From Email</label><input class="bx-input" type="email" name="smtp_from_email" value="<?= htmlspecialchars((string) ($_POST['smtp_from_email'] ?? ''), ENT_QUOTES) ?>"></div>
                    </div>
                    <div style="display:flex;gap:8px;flex-wrap:wrap;align-items:center;">
                        <input class="bx-input" type="email" name="smtp_test_to" placeholder="test-recipient@example.com" value="<?= htmlspecialchars((string) ($_POST['smtp_test_to'] ?? ''), ENT_QUOTES) ?>" style="flex:1 1 200px;">
                        <button type="submit" name="wizard_action" value="test_smtp" formnovalidate class="bx-btn bx-btn-outline bx-btn-sm">Send Test Email</button>
                    </div>
                </details>

                <!-- Step 5: Install -->
                <button type="submit" name="wizard_action" value="install" class="bx-btn bx-btn-primary" style="width:100%;">
                    Step 5 — Install Bookexa &amp; Show Go-Live Checklist
                </button>
            </form>
        <?php endif; ?>
    </div>
</div>
</body>
</html>
