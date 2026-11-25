<?php
declare(strict_types=1);

/**
 * Bookexa CDN HTTP Receiver (standalone script to deploy on remote CDN host)
 * Upload into e.g. https://cdn.domain.com/bookexa/receive.php
 * Set BOOKEXA_CDN_SECRET env var or define BOOKEXA_CDN_SECRET_VALUE below matching config.php cdn.http.secret.
 */

const BOOKEXA_CDN_SECRET_VALUE = 'CHANGE_THIS_SHARED_SECRET';

header('Content-Type: application/json; charset=UTF-8');
header('X-Content-Type-Options: nosniff');
header('Cache-Control: no-store');

function receiver_fail(int $code, string $msg): never
{
    http_response_code($code);
    echo json_encode(['ok' => false, 'message' => $msg]);
    exit;
}

$secret = trim((string) (getenv('BOOKEXA_CDN_SECRET') ?: BOOKEXA_CDN_SECRET_VALUE));
if ($secret === '' || $secret === 'CHANGE_THIS_SHARED_SECRET') {
    receiver_fail(500, 'CDN receiver secret not configured.');
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    receiver_fail(405, 'Method not allowed.');
}

$ts      = (int) ($_POST['ts'] ?? $_SERVER['HTTP_X_BOOKEXA_TS'] ?? 0);
$action  = trim((string) ($_POST['action'] ?? $_SERVER['HTTP_X_BOOKEXA_ACTION'] ?? ''));
$relPath = str_replace('\\', '/', trim((string) ($_POST['rel_path'] ?? $_SERVER['HTTP_X_BOOKEXA_PATH'] ?? '')));
$sig     = strtolower(trim((string) ($_POST['sig'] ?? $_SERVER['HTTP_X_BOOKEXA_SIG'] ?? '')));
$sha1In  = strtolower(trim((string) ($_POST['sha1'] ?? '')));

if ($ts <= 0 || abs(time() - $ts) > 300) {
    receiver_fail(403, 'Request timestamp expired.');
}
if (!in_array($action, ['put', 'delete', 'exists'], true)) {
    receiver_fail(400, 'Invalid action.');
}
if (
    str_contains($relPath, '..')
    || str_contains($relPath, "\0")
    || !preg_match('#^uploads/(folders|covers|banners|products|branding)/[A-Za-z0-9][A-Za-z0-9._-]*\.(jpg|jpeg|png|webp|ico|svg)$#i', $relPath)
) {
    receiver_fail(403, 'Invalid or disallowed relative image path.');
}

$expectedSig = hash_hmac('sha256', "{$ts}|{$action}|{$relPath}|{$sha1In}", $secret);
if (!hash_equals($expectedSig, $sig)) {
    receiver_fail(403, 'Invalid HMAC signature.');
}

$absTarget = __DIR__ . '/' . $relPath;
$realRoot  = realpath(__DIR__);

if ($action === 'exists') {
    echo json_encode(['ok' => true, 'exists' => is_file($absTarget)]);
    exit;
}

if ($action === 'delete') {
    if (is_file($absTarget)) {
        @unlink($absTarget);
    }
    echo json_encode(['ok' => true, 'deleted' => true]);
    exit;
}

// PUT action: validate image upload with finfo
if (empty($_FILES['file']['tmp_name']) || !is_uploaded_file($_FILES['file']['tmp_name'])) {
    receiver_fail(400, 'Missing file upload.');
}
if ((int) $_FILES['file']['size'] > 8 * 1024 * 1024) {
    receiver_fail(413, 'File exceeds 8MB limit.');
}

$bytes = @file_get_contents($_FILES['file']['tmp_name']);
if ($bytes === false || $bytes === '') {
    receiver_fail(400, 'Empty file.');
}
if ($sha1In !== '' && !hash_equals($sha1In, sha1($bytes))) {
    receiver_fail(400, 'Checksum mismatch.');
}

$ext = strtolower(pathinfo($relPath, PATHINFO_EXTENSION));
$finfo = new finfo(FILEINFO_MIME_TYPE);
$mime = (string) $finfo->file($_FILES['file']['tmp_name']);

$allowedMimes = [
    'jpg'  => ['image/jpeg'],
    'jpeg' => ['image/jpeg'],
    'png'  => ['image/png'],
    'webp' => ['image/webp'],
    'ico'  => ['image/x-icon', 'image/vnd.microsoft.icon'],
    'svg'  => ['image/svg+xml', 'text/plain', 'text/xml', 'application/xml'],
];
if (!isset($allowedMimes[$ext]) || !in_array($mime, $allowedMimes[$ext], true)) {
    receiver_fail(415, 'Disallowed MIME type: ' . $mime);
}

if ($ext === 'svg') {
    $bytes = preg_replace('#<script\b[^>]*>.*?</script>#is', '', $bytes) ?? '';
    $bytes = preg_replace('#<foreignObject\b[^>]*>.*?</foreignObject>#is', '', $bytes) ?? '';
    $bytes = preg_replace('/\son[a-z]+\s*=\s*(["\']).*?\1/is', '', $bytes) ?? '';
    $bytes = preg_replace('/(href|xlink:href)\s*=\s*(["\'])\s*(https?:|javascript:|data:text).*?\2/is', '', $bytes) ?? '';
}

$dir = dirname($absTarget);
if (!is_dir($dir) && !@mkdir($dir, 0755, true)) {
    receiver_fail(500, 'Could not create target directory.');
}

$realDir = realpath($dir);
if ($realRoot === false || $realDir === false || !str_starts_with($realDir, $realRoot)) {
    receiver_fail(403, 'Resolved path escapes CDN root.');
}

$htaccessFile = __DIR__ . '/uploads/.htaccess';
if (!is_file($htaccessFile)) {
    @file_put_contents($htaccessFile, "Options -Indexes\n<IfModule mod_php.c>\n    php_flag engine off\n</IfModule>\n<IfModule mod_php8.c>\n    php_flag engine off\n</IfModule>\n<FilesMatch \"\\.(php|php\\d*|phtml|phar|pl|py|jsp|asp|sh|cgi)$\">\n    Require all denied\n</FilesMatch>\n<IfModule mod_headers.c>\n    Header always set X-Content-Type-Options \"nosniff\"\n    Header always set Cache-Control \"public, max-age=31536000, immutable\"\n</IfModule>\n");
}

$tmpWrite = $absTarget . '.tmp.' . bin2hex(random_bytes(4));
if (@file_put_contents($tmpWrite, $bytes, LOCK_EX) === false || !@rename($tmpWrite, $absTarget)) {
    @unlink($tmpWrite);
    receiver_fail(500, 'Failed writing target file.');
}
@chmod($absTarget, 0644);

echo json_encode(['ok' => true, 'rel_path' => $relPath, 'size' => strlen($bytes), 'sha1' => sha1($bytes)]);
