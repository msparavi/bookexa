<?php
declare(strict_types=1);

if ($route === '/debug' || str_starts_with($route, '/debug/')) {
    header('X-Robots-Tag: noindex, nofollow, noarchive');
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
    if (!current_admin()) {
        render_error_page(403);
    }
    if ($route === '/debug/overflow.html') {
        require APP_ROOT . '/pages/debug/overflow.php';
        exit;
    }
    render_error_page(404);
}

// ----------------------------------------------------------------------------
// Admin Routes
// ----------------------------------------------------------------------------
if ($route === '/admin' || str_starts_with($route, '/admin/')) {
    require_once APP_ROOT . '/includes/admin_controller.php';
    handle_admin_routes($route);
}

// Maintenance Mode Check (public storefront only; admins bypass when logged in)
