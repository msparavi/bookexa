<?php
declare(strict_types=1);

/**
 * Bookexa Service: Watermarker
 */
final class Watermarker
{
    /**
     * Resolve TTF font supporting Unicode (Bangla + Latin) or standard Latin for GD watermarking.
     */
    public static function findTtfFont(): ?string
    {
        $candidates = [
            APP_ROOT . '/assets/fonts/watermark.ttf',
            'C:/Windows/Fonts/Nirmala.ttf',
            'C:/Windows/Fonts/arial.ttf',
            '/usr/share/fonts/truetype/noto/NotoSansBengali-Regular.ttf',
            '/usr/share/fonts/truetype/dejavu/DejaVuSans-Bold.ttf',
            '/usr/share/fonts/truetype/liberation/LiberationSans-Bold.ttf',
        ];
        foreach ($candidates as $path) {
            if (is_file($path)) {
                return $path;
            }
        }
        return null;
    }

    /**
     * Ensure a cache subdirectory under storage/cache/ exists with an Apache Deny-all .htaccess.
     */
    public static function ensureCacheSubdir(string $sub): string
    {
        $dir = APP_ROOT . '/storage/cache/' . trim($sub, '/');
        if (!is_dir($dir)) {
            @mkdir($dir, 0755, true);
        }
        $ht = $dir . '/.htaccess';
        if (!is_file($ht)) {
            @file_put_contents($ht, "<IfModule mod_authz_core.c>\n    Require all denied\n</IfModule>\n<IfModule !mod_authz_core.c>\n    Order allow,deny\n    Deny from all\n</IfModule>\n");
        }
        return $dir;
    }

    /**
     * Draw tiled diagonal watermark text onto a transparent or target GD canvas.
     */
    public static function drawTiledOnCanvas($canvas, int $w, int $h, string $stamp): void
    {
        $color = imagecolorallocatealpha($canvas, 180, 42, 32, 102); // ~20% opacity diagonal crimson-rust
        $fontPath = Watermarker::findTtfFont();

        if ($fontPath !== null && function_exists('imagettftext')) {
            $fontSize = max(12, min(26, (int) round($w * 0.0155)));
            $stepX = max(300, (int) round($w * 0.42));
            $stepY = max(160, (int) round($h * 0.19));
            for ($y = (int) round($stepY * 0.4); $y <= $h + $stepY; $y += $stepY) {
                $offsetX = ((int) floor($y / $stepY) % 2 === 1) ? (int) round($stepX * 0.35) : 20;
                for ($x = $offsetX; $x <= $w + 200; $x += $stepX) {
                    @imagettftext($canvas, $fontSize, 26, $x, $y, $color, $fontPath, $stamp);
                }
            }
            return;
        }

        $asciiStamp = preg_replace('/[^\x20-\x7E]/', '?', $stamp) ?: 'Bookexa Protected';
        $tw = max(180, strlen($asciiStamp) * 9 + 16);
        $th = 24;
        $tile = imagecreatetruecolor($tw, $th);
        imagealphablending($tile, false);
        $trans = imagecolorallocatealpha($tile, 255, 255, 255, 127);
        imagefill($tile, 0, 0, $trans);
        imagesavealpha($tile, true);
        $tc = imagecolorallocatealpha($tile, 180, 42, 32, 92);
        imagestring($tile, 4, 8, 4, $asciiStamp, $tc);
        $rot = @imagerotate($tile, 26, $trans);
        imagedestroy($tile);
        if ($rot) {
            $rw = imagesx($rot);
            $rh = imagesy($rot);
            $stepX = max(250, (int) round($w * 0.40));
            $stepY = max(150, (int) round($h * 0.18));
            for ($y = 20; $y < $h; $y += $stepY) {
                for ($x = 20; $x < $w; $x += $stepX) {
                    imagecopy($canvas, $rot, $x, $y, 0, 0, $rw, $rh);
                }
            }
            imagedestroy($rot);
        }
    }

    /**
     * Load or build a 1-hour cached transparent PNG watermark overlay keyed by user ID, name, contact, and page dimensions.
     */
    public static function getCachedOverlay(int $userId, string $userName, string $contact, int $w, int $h, string $watermarkBase)
    {
        $wmDir = Watermarker::ensureCacheSubdir('wm');
        $cacheKey = hash('sha256', $userId . '|' . $userName . '|' . $contact . '|' . $w . 'x' . $h);
        $wmPath = $wmDir . '/' . $cacheKey . '.png';

        if (is_file($wmPath) && (time() - (int) @filemtime($wmPath)) < 3600) {
            $cached = @imagecreatefrompng($wmPath);
            if ($cached && imagesx($cached) === $w && imagesy($cached) === $h) {
                return $cached;
            }
            if ($cached) {
                imagedestroy($cached);
            }
        }

        $overlay = imagecreatetruecolor($w, $h);
        if (!$overlay) {
            return null;
        }
        imagealphablending($overlay, false);
        $trans = imagecolorallocatealpha($overlay, 255, 255, 255, 127);
        imagefill($overlay, 0, 0, $trans);
        imagealphablending($overlay, true);
        imagesavealpha($overlay, true);

        $stamp = $watermarkBase . ' • ' . date('Y-m-d H:i');
        Watermarker::drawTiledOnCanvas($overlay, $w, $h, $stamp);

        $tmpPath = $wmPath . '.' . bin2hex(random_bytes(6)) . '.tmp';
        if (@imagepng($overlay, $tmpPath, 1, PNG_NO_FILTER)) {
            if (!@rename($tmpPath, $wmPath)) {
                @unlink($tmpPath);
            }
        } else {
            @unlink($tmpPath);
        }
        return $overlay;
    }

    public static function clientPrefersWebp(): bool
    {
        if (!function_exists('imagewebp')) {
            return false;
        }
        $accept = strtolower((string) ($_SERVER['HTTP_ACCEPT'] ?? ''));
        if ($accept !== '' && !str_contains($accept, 'image/webp') && !str_contains($accept, '*/*') && (str_contains($accept, 'image/jpeg') || str_contains($accept, 'image/png'))) {
            return false;
        }
        return true;
    }

    /**
     * Composite cached per-user transparent PNG watermark (or fallback TTF draw) and emit WebP q=78 / JPEG.
     */
    public static function outputWatermarkedImage(string $srcPath, string $watermarkBase, array $opts = []): bool
    {
        if (!extension_loaded('gd')) {
            return false;
        }
        $origSrc  = $srcPath;
        $itemId   = (int) ($opts['item_id'] ?? 0);
        $pageNum  = (int) ($opts['page_num'] ?? 0);
        $maxWidth = (int) ($opts['max_width'] ?? 0);
        if ($maxWidth > 0 && $maxWidth <= 1000 && $itemId > 0 && $pageNum > 0) {
            $srcPath = PageServer::resolveVariantSource($origSrc, $itemId, $pageNum, $maxWidth);
        }

        $bytes = @file_get_contents($srcPath);
        $im = ($bytes !== false && $bytes !== '') ? @imagecreatefromstring($bytes) : false;
        if (!$im && $srcPath !== $origSrc) {
            $bytes = @file_get_contents($origSrc);
            $im = ($bytes !== false && $bytes !== '') ? @imagecreatefromstring($bytes) : false;
        }
        if (!$im) {
            return false;
        }

        $w = imagesx($im);
        $h = imagesy($im);
        if ($maxWidth > 0 && $maxWidth <= 1000 && $w > $maxWidth) {
            $scaled = @imagescale($im, $maxWidth, -1, IMG_BILINEAR_FIXED);
            if ($scaled) {
                imagedestroy($im);
                $im = $scaled;
                $w = imagesx($im);
                $h = imagesy($im);
            }
        }

        imagealphablending($im, true);
        imagesavealpha($im, false);

        $useWmCache = !array_key_exists('use_wm_cache', $opts) || !empty($opts['use_wm_cache']);
        $userId     = (int) ($opts['user_id'] ?? 0);
        $userName   = (string) ($opts['user_name'] ?? '');
        $contact    = (string) ($opts['contact'] ?? '');

        if ($useWmCache && $userId > 0) {
            $wmOverlay = Watermarker::getCachedOverlay($userId, $userName, $contact, $w, $h, $watermarkBase);
            if ($wmOverlay) {
                imagecopy($im, $wmOverlay, 0, 0, 0, 0, $w, $h);
                imagedestroy($wmOverlay);
            } else {
                Watermarker::drawTiledOnCanvas($im, $w, $h, $watermarkBase . ' • ' . date('Y-m-d H:i'));
            }
        } else {
            Watermarker::drawTiledOnCanvas($im, $w, $h, $watermarkBase . ' • ' . date('Y-m-d H:i'));
        }

        $useWebp = array_key_exists('webp_supported', $opts)
            ? ($opts['webp_supported'] && function_exists('imagewebp'))
            : Watermarker::clientPrefersWebp();

        if (!empty($opts['return_bytes'])) {
            ob_start();
            $ok = $useWebp ? imagewebp($im, null, 78) : imagejpeg($im, null, 80);
            $rawOut = (string) ob_get_clean();
            imagedestroy($im);
            return $ok && $rawOut !== '';
        }

        header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
        header('Pragma: no-cache');
        header('Expires: 0');
        header('X-Content-Type-Options: nosniff');
        header('X-Robots-Tag: noindex, nofollow');
        header('Vary: Accept');
        if ($useWebp) {
            header('Content-Type: image/webp');
            $ok = imagewebp($im, null, 78);
        } else {
            header('Content-Type: image/jpeg');
            $ok = imagejpeg($im, null, 80);
        }
        imagedestroy($im);
        return $ok;
    }
}
