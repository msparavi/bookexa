<?php
declare(strict_types=1);

/**
 * First-run Bookexa Web Installer (/install)
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

$error = null;
$done = false;
$checks = [
    'php_81'   => version_compare(PHP_VERSION, '8.1.0', '>='),
    'pdo_mysql'=> extension_loaded('pdo_mysql'),
    'curl'     => extension_loaded('curl'),
    'gd'       => extension_loaded('gd'),
    'mbstring' => extension_loaded('mbstring'),
    'fileinfo' => extension_loaded('fileinfo'),
    'config_rw'=> is_writable(APP_ROOT . '/config'),
    'storage_w'=> is_writable(APP_ROOT . '/storage') && is_writable(APP_ROOT . '/uploads'),
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $tok = (string) ($_POST['_csrf'] ?? '');
    if (!hash_equals($installCsrf, $tok)) {
        $error = 'Invalid security token. Please reload and try again.';
    } else {
        $siteName = trim((string) ($_POST['site_name'] ?? 'Bookexa')) ?: 'Bookexa';
        $appEnv   = ($_POST['app_env'] ?? 'production') === 'local' ? 'local' : 'production';
        $baseUrl  = rtrim(trim((string) ($_POST['base_url'] ?? '')), '/');
        $dbHost   = trim((string) ($_POST['db_host'] ?? 'localhost'));
        $dbPort   = trim((string) ($_POST['db_port'] ?? '3306')) ?: '3306';
        $dbName   = trim((string) ($_POST['db_name'] ?? ''));
        $dbUser   = trim((string) ($_POST['db_user'] ?? ''));
        $dbPass   = (string) ($_POST['db_pass'] ?? '');
        $admName  = trim((string) ($_POST['admin_name'] ?? ''));
        $admEmail = strtolower(trim((string) ($_POST['admin_email'] ?? '')));
        $admPw    = (string) ($_POST['admin_password'] ?? '');
        $admPw2   = (string) ($_POST['admin_password_confirm'] ?? '');

        if ($dbName === '' || $dbUser === '' || $admName === '' || !filter_var($admEmail, FILTER_VALIDATE_EMAIL)) {
            $error = 'Database name/user, admin name, and valid admin email are required.';
        } elseif (strlen($admPw) < 8) {
            $error = 'Admin password must be at least 8 characters.';
        } elseif ($admPw !== $admPw2) {
            $error = 'Admin password confirmation does not match.';
        } else {
            try {
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
                $stmts = bookexa_install_split_sql((string) file_get_contents($schemaPath));
                foreach ($stmts as $sql) {
                    $pdo->exec($sql);
                }

                // Also apply 003_media_sync migration cleanly if present and register local /uploads images
                $mig003 = APP_ROOT . '/database/migrations/003_media_sync.sql';
                if (is_file($mig003)) {
                    foreach (bookexa_install_split_sql((string) file_get_contents($mig003)) as $msql) {
                        $pdo->exec($msql);
                    }
                }
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

                // Create Admin Superadmin account (production-safe, no demo password)
                $hash = password_hash($admPw, PASSWORD_BCRYPT);
                $stAdm = $pdo->prepare(
                    "INSERT INTO admins (name, email, password_hash, role, is_active, must_change_password)
                     VALUES (?, ?, ?, 'superadmin', 1, 0)
                     ON DUPLICATE KEY UPDATE name = VALUES(name), password_hash = VALUES(password_hash), role = 'superadmin', is_active = 1, must_change_password = 0"
                );
                $stAdm->execute([$admName, $admEmail, $hash]);

                $stSet = $pdo->prepare("INSERT INTO settings (setting_key, setting_value) VALUES ('site_name', ?) ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)");
                $stSet->execute([$siteName]);

                // Generate cryptographically random app_secret
                $appSecret = 'bx_' . bin2hex(random_bytes(32));
                $cfgArr = [
                    'app_name'  => $siteName,
                    'app_env'   => $appEnv,
                    'timezone'  => 'Asia/Dhaka',
                    'base_url'  => $baseUrl,
                    'base_path' => '',
                    'db'        => [
                        'host'    => $dbHost,
                        'port'    => $dbPort,
                        'name'    => $dbName,
                        'user'    => $dbUser,
                        'pass'    => $dbPass,
                        'charset' => 'utf8mb4',
                    ],
                    'security'  => [
                        'app_secret'        => $appSecret,
                        'pdf_cache_enabled' => true,
                        'pdf_cache_ttl'     => 21600,
                    ],
                ];
                $cfgContent = "<?php\n/** Auto-generated by Bookexa Installer */\nreturn " . var_export($cfgArr, true) . ";\n";
                if (@file_put_contents(APP_ROOT . '/config/config.php', $cfgContent, LOCK_EX) === false) {
                    throw new RuntimeException('Could not write config/config.php. Check folder permissions on config/.');
                }
                @chmod(APP_ROOT . '/config/config.php', 0640);
                @file_put_contents(APP_ROOT . '/config/installed.lock', "installed_at=" . date('c') . "\nmode=" . $appEnv . "\n", LOCK_EX);
                $done = true;
            } catch (Throwable $e) {
                $error = 'Installation failed: ' . $e->getMessage();
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
    <title>Bookexa Setup &amp; Installer</title>
    <link rel="stylesheet" href="<?= htmlspecialchars($basePath . '/assets/css/fonts.css', ENT_QUOTES) ?>">
    <link rel="stylesheet" href="<?= htmlspecialchars($basePath . '/assets/css/tokens.css', ENT_QUOTES) ?>">
    <link rel="stylesheet" href="<?= htmlspecialchars($basePath . '/assets/css/components.css', ENT_QUOTES) ?>">
</head>
<body style="padding:24px 14px;background:var(--bg);color:var(--text);">
<div class="container" style="max-width:700px;">
    <div class="bx-card" style="padding:clamp(20px,4vw,34px);">
        <h1 class="bx-display" style="font-size:1.75rem;margin-bottom:6px;">Bookexa Web Installer</h1>
        <p class="bx-muted" style="margin:0 0 18px;">Initial server configuration for MySQL, cryptographic app secret, and your Super Admin account. Zero demo accounts are installed in production.</p>

        <?php if ($done): ?>
            <div class="bx-auth-alert is-success" style="margin-bottom:18px;">
                <strong>Installation Complete!</strong> Configuration written to <code>config/config.php</code> and installer locked via <code>config/installed.lock</code>.
            </div>
            <div style="display:flex;gap:12px;flex-wrap:wrap;">
                <a href="<?= htmlspecialchars($basePath . '/admin/login', ENT_QUOTES) ?>" class="bx-btn bx-btn-primary">Sign In to Admin Panel</a>
                <a href="<?= htmlspecialchars($basePath . '/', ENT_QUOTES) ?>" class="bx-btn bx-btn-outline">Visit Storefront</a>
            </div>
        <?php else: ?>
            <div style="padding:12px 14px;border-radius:12px;background:var(--bg-alt);border:1px solid var(--border);margin-bottom:18px;">
                <strong style="display:block;margin-bottom:6px;">Server Environment Pre-flight:</strong>
                <div style="display:flex;flex-wrap:wrap;gap:8px;">
                    <?php foreach ($checks as $label => $ok): ?>
                        <span class="bx-badge <?= $ok ? 'bx-badge-success' : 'bx-badge-accent' ?>"><?= htmlspecialchars($label . ': ' . ($ok ? 'OK' : 'CHECK'), ENT_QUOTES) ?></span>
                    <?php endforeach; ?>
                </div>
            </div>

            <?php if ($error): ?>
                <div class="bx-auth-alert is-danger"><?= htmlspecialchars($error, ENT_QUOTES) ?></div>
            <?php endif; ?>

            <form method="post">
                <input type="hidden" name="_csrf" value="<?= htmlspecialchars($installCsrf, ENT_QUOTES) ?>">
                <div class="bx-grid-auto-md">
                    <div class="bx-field">
                        <label>Site Name</label>
                        <input class="bx-input" name="site_name" required value="<?= htmlspecialchars((string) ($_POST['site_name'] ?? 'Bookexa'), ENT_QUOTES) ?>">
                    </div>
                    <div class="bx-field">
                        <label>Environment</label>
                        <select class="bx-select" name="app_env">
                            <option value="production" selected>Production (cPanel / Live)</option>
                            <option value="local">Local Development</option>
                        </select>
                    </div>
                </div>
                <div class="bx-field">
                    <label>Canonical Base URL (leave empty for auto-detection)</label>
                    <input class="bx-input" name="base_url" placeholder="<?= htmlspecialchars($suggestedBase, ENT_QUOTES) ?>" value="<?= htmlspecialchars((string) ($_POST['base_url'] ?? ''), ENT_QUOTES) ?>">
                </div>

                <h2 class="bx-h2" style="font-size:1.15rem;margin:18px 0 10px;">MySQL Database</h2>
                <div class="bx-grid-auto-sm">
                    <div class="bx-field"><label>DB Host</label><input class="bx-input" name="db_host" required value="<?= htmlspecialchars((string) ($_POST['db_host'] ?? 'localhost'), ENT_QUOTES) ?>"></div>
                    <div class="bx-field"><label>DB Port</label><input class="bx-input" name="db_port" required value="<?= htmlspecialchars((string) ($_POST['db_port'] ?? '3306'), ENT_QUOTES) ?>"></div>
                    <div class="bx-field"><label>DB Name *</label><input class="bx-input" name="db_name" required value="<?= htmlspecialchars((string) ($_POST['db_name'] ?? ''), ENT_QUOTES) ?>"></div>
                    <div class="bx-field"><label>DB User *</label><input class="bx-input" name="db_user" required value="<?= htmlspecialchars((string) ($_POST['db_user'] ?? ''), ENT_QUOTES) ?>"></div>
                </div>
                <div class="bx-field">
                    <label>DB Password</label>
                    <input class="bx-input" type="password" name="db_pass" autocomplete="new-password">
                </div>

                <h2 class="bx-h2" style="font-size:1.15rem;margin:18px 0 10px;">Create Super Admin Account</h2>
                <div class="bx-grid-auto-md">
                    <div class="bx-field"><label>Admin Name *</label><input class="bx-input" name="admin_name" required value="<?= htmlspecialchars((string) ($_POST['admin_name'] ?? 'Site Administrator'), ENT_QUOTES) ?>"></div>
                    <div class="bx-field"><label>Admin Email *</label><input class="bx-input" type="email" name="admin_email" required value="<?= htmlspecialchars((string) ($_POST['admin_email'] ?? ''), ENT_QUOTES) ?>"></div>
                    <div class="bx-field"><label>Admin Password (min 8) *</label><input class="bx-input" type="password" name="admin_password" required minlength="8" autocomplete="new-password"></div>
                    <div class="bx-field"><label>Confirm Password *</label><input class="bx-input" type="password" name="admin_password_confirm" required minlength="8" autocomplete="new-password"></div>
                </div>
                <button type="submit" class="bx-btn bx-btn-primary" style="width:100%;">Install Bookexa &amp; Lock Installer</button>
            </form>
        <?php endif; ?>
    </div>
</div>
</body>
</html>
