<?php
declare(strict_types=1);

/**
 * Bookexa CLI Asset Build & Image Optimizer Script (Zero Node.js Required on Shared Hosting)
 * Usage:
 *   php cron/build_assets.php
 *   php cron/build_assets.php --images
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('CLI only.');
}

require_once dirname(__DIR__) . '/includes/bootstrap.php';
require_once APP_ROOT . '/includes/asset_builder.php';

$withImages = in_array('--images', $argv ?? [], true);
$result = bx_build_dist_assets($withImages);

echo "Built Bookexa assets into assets/dist/manifest.json\n";
echo "Critical CSS: {$result['critical_css_file']} ({$result['critical_bytes']} bytes)\n";
foreach ($result['files'] as $k => $info) {
    echo sprintf("  %-14s => %s (%d bytes)\n", $k, $info['path'], $info['bytes']);
}
if ($withImages) {
    echo "Raster images with WebP/AVIF variants generated: {$result['images_optimized']}\n";
}
