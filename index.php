<?php
declare(strict_types=1);

$rawPathCheck = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
$isInstallUri = preg_match('#/install$#', $rawPathCheck) === 1;
$hasConfig = is_file(__DIR__ . '/config/config.php');
$isLocked  = is_file(__DIR__ . '/config/installed.lock');
if (!$hasConfig || (!$isLocked && $isInstallUri)) {
    define('APP_ROOT', __DIR__);
    require APP_ROOT . '/pages/install.php';
    exit;
}

require_once __DIR__ . '/includes/bootstrap.php';
require_once APP_ROOT . '/includes/components.php';

$rawUri = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
if (BASE_PATH !== '' && str_starts_with($rawUri, BASE_PATH)) {
    $rawUri = substr($rawUri, strlen(BASE_PATH));
}
$literalRoute = '/' . trim($rawUri, '/');
if ($literalRoute === '') {
    $literalRoute = '/';
}
$route = defined('BX_LOGICAL_ROUTE') ? BX_LOGICAL_ROUTE : $literalRoute;
if (defined('BX_URL_HAS_EN') && BX_URL_HAS_EN && !is_bilingual_public_route($route)) {
    $route = $literalRoute;
}

if ($route === '/install') {
    render_error_page(
        403,
        current_lang() === 'bn' ? 'ইনস্টলার লক করা আছে (৪০৩)' : 'Installer Locked (403)',
        current_lang() === 'bn' ? 'Bookexa ইতিমধ্যে ইনস্টল করা হয়েছে।' : 'This Bookexa installation is already configured and locked.'
    );
}

require APP_ROOT . '/routes/api.php';
require APP_ROOT . '/routes/admin.php';

// Maintenance Mode Check (storefront & user routes; admins bypass when logged in)
if (site_setting('maintenance_mode', '0') === '1' && !current_admin()) {
    render_error_page(503);
}

// Serve full-page cache for anonymous public GET visitors (ETag, Last-Modified, 304)
perf_page_cache_try_serve($route);

require APP_ROOT . '/routes/user.php';
require APP_ROOT . '/routes/reader.php';
require APP_ROOT . '/routes/public.php';
