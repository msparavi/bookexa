<?php
declare(strict_types=1);

define('APP_ROOT', dirname(__DIR__));
$config = require APP_ROOT . '/config/config.php';

date_default_timezone_set($config['timezone'] ?? 'Asia/Dhaka');

$logDir = APP_ROOT . '/storage/logs';
if (!is_dir($logDir)) {
    @mkdir($logDir, 0755, true);
}
$logFile = $logDir . '/php-error.log';
ini_set('log_errors', '1');
ini_set('error_log', $logFile);

if (($config['app_env'] ?? 'production') === 'local') {
    error_reporting(E_ALL);
    ini_set('display_errors', '1');
} else {
    error_reporting(E_ALL & ~E_NOTICE & ~E_DEPRECATED);
    ini_set('display_errors', '0');
}

set_exception_handler(function (Throwable $e) use ($logFile): void {
    $msg = '[' . date('c') . '] Uncaught ' . get_class($e) . ': ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine() . "\n";
    @error_log($msg, 3, $logFile);
    if (function_exists('render_error_page')) {
        render_error_page(500);
    }
    http_response_code(500);
    echo '<!DOCTYPE html><html><body style="font-family:sans-serif;padding:32px;"><h1>500 Server Error</h1><p>Something went wrong. Please try again shortly.</p></body></html>';
    exit;
});

register_shutdown_function(function () use ($logFile): void {
    $err = error_get_last();
    if ($err && in_array($err['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR], true)) {
        $msg = '[' . date('c') . '] Fatal: ' . $err['message'] . ' in ' . $err['file'] . ':' . $err['line'] . "\n";
        @error_log($msg, 3, $logFile);
        if (!headers_sent() && app_config('app_env', 'production') !== 'local') {
            if (function_exists('render_error_page')) {
                render_error_page(500);
            }
            http_response_code(500);
            echo '<!DOCTYPE html><html><body style="font-family:sans-serif;padding:32px;"><h1>500 Server Error</h1></body></html>';
        }
    }
});

// Compute BASE_PATH & BASE_URL dynamically for both XAMPP (/bookexa) and cPanel root (/)
$cfgBasePath = trim((string) ($config['base_path'] ?? ''));
if ($cfgBasePath !== '') {
    $basePath = '/' . trim(str_replace('\\', '/', $cfgBasePath), '/');
    if ($basePath === '/') {
        $basePath = '';
    }
} else {
    $scriptDir = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/index.php'));
    $basePath = ($scriptDir === '/' || $scriptDir === '.' || $scriptDir === '\\') ? '' : rtrim($scriptDir, '/');
}
define('BASE_PATH', $basePath);

$isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
    || (($_SERVER['SERVER_PORT'] ?? '') === '443')
    || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');

$cfgBaseUrl = trim((string) ($config['base_url'] ?? ''));
if ($cfgBaseUrl !== '') {
    $baseUrl = rtrim($cfgBaseUrl, '/');
} else {
    $scheme = $isHttps ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $baseUrl = $scheme . '://' . $host . $basePath;
}
define('BASE_URL', $baseUrl);

// Secure session settings
if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => BASE_PATH !== '' ? BASE_PATH : '/',
        'domain'   => '',
        'secure'   => $isHttps,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    ini_set('session.use_strict_mode', '1');
    ini_set('session.use_only_cookies', '1');
    session_name('bookexa_sid');
    session_start();
}

function app_config(?string $key = null, $default = null)
{
    static $cfg = null;
    if ($cfg === null) {
        $cfg = require APP_ROOT . '/config/config.php';
    }
    if ($key === null) {
        return $cfg;
    }
    $parts = explode('.', $key);
    $val = $cfg;
    foreach ($parts as $p) {
        if (!is_array($val) || !array_key_exists($p, $val)) {
            return $default;
        }
        $val = $val[$p];
    }
    return $val;
}

require_once APP_ROOT . '/includes/db.php';
require_once APP_ROOT . '/includes/csrf.php';
require_once APP_ROOT . '/includes/lang.php';
require_once APP_ROOT . '/includes/helpers.php';
require_once APP_ROOT . '/includes/icons.php';
require_once APP_ROOT . '/includes/illustrations.php';
require_once APP_ROOT . '/includes/auth.php';
require_once APP_ROOT . '/includes/features.php';
require_once APP_ROOT . '/includes/cdn.php';
require_once APP_ROOT . '/includes/packager.php';
require_once APP_ROOT . '/includes/maintenance.php';

handle_language_switch();

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'GET' && !str_contains($_SERVER['REQUEST_URI'] ?? '', '/api/')) {
    run_lazy_maintenance_cleanup();
}
