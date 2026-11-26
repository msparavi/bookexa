<?php
declare(strict_types=1);

/**
 * Backwards-compatible procedural facade delegating to ReaderTokens, DriveFetcher, Watermarker, and PageServer.
 */
require_once __DIR__ . '/services/ReaderTokens.php';
require_once __DIR__ . '/services/DriveFetcher.php';
require_once __DIR__ . '/services/Watermarker.php';
require_once __DIR__ . '/services/PageServer.php';

function b64url_encode(string $data): string
{
    return ReaderTokens::b64urlEncode($data);
}
function b64url_decode(string $data): ?string
{
    return ReaderTokens::b64urlDecode($data);
}
function issue_signed_reader_token(int $userId, int $itemId, bool $isSample, string $sessionToken, string $mode = 'pages', int $ttlSeconds = 900): string
{
    return ReaderTokens::issue($userId, $itemId, $isSample, $sessionToken, $mode, $ttlSeconds);
}
function verify_signed_reader_token(string $rawToken, int $uid, int $itemId): ?array
{
    return ReaderTokens::verify($rawToken, $uid, $itemId);
}
function log_reading_event(int $userId, ?int $itemId, bool $isSample, string $action, ?string $details = null): void
{
    ReaderTokens::logReadingEvent($userId, $itemId, $isSample, $action, $details);
}
function user_reading_suspension_message(string $untilDatetime): string
{
    return ReaderTokens::userSuspensionMessage($untilDatetime);
}
function notify_admin_reader_suspension(int $userId, int $anomalyCount, string $lastAction): void
{
    ReaderTokens::notifyAdminSuspension($userId, $anomalyCount, $lastAction);
}
function record_reader_anomaly(int $userId, ?int $itemId, bool $isSample, string $action, string $details): bool
{
    return ReaderTokens::recordAnomaly($userId, $itemId, $isSample, $action, $details);
}
function detect_many_sessions_anomaly(int $userId): void
{
    ReaderTokens::detectManySessionsAnomaly($userId);
}
function track_fast_sequential_burst(int $userId, int $itemId, int $pageNum): void
{
    ReaderTokens::trackFastSequentialBurst($userId, $itemId, $pageNum);
}
function extract_drive_file_id(string $input): ?string
{
    return DriveFetcher::extractFileId($input);
}
function parse_php_size_bytes(string $val): int
{
    return DriveFetcher::parsePhpSizeBytes($val);
}
function server_safe_pdf_limits(): array
{
    return DriveFetcher::serverSafeLimits();
}
function drive_api_key(): string
{
    return DriveFetcher::driveApiKey();
}
function drive_redact_secret(string $text): string
{
    return DriveFetcher::redactSecret($text);
}
function log_pdf_source_admin_error(int $userId, int $itemId, bool $isSample, string $realReason): void
{
    DriveFetcher::logSourceAdminError($userId, $itemId, $isSample, $realReason);
}
function count_pdf_pages_fast_file(string $filePath): ?int
{
    return DriveFetcher::countPagesFastFile($filePath);
}
function curl_stream_download_to_file(string $url, string $destPath, ?string $cookieFile = null, int $connectTimeout = 15): array
{
    return DriveFetcher::curlStreamDownloadToFile($url, $destPath, $cookieFile, $connectTimeout);
}
function pdf_file_starts_with_magic(string $filePath): bool
{
    return DriveFetcher::startsWithMagic($filePath);
}
function classify_drive_html_failure(int $httpCode, string $contentType, string $headBuf): array
{
    return DriveFetcher::classifyHtmlFailure($httpCode, $contentType, $headBuf);
}
function fetch_source_pdf_to_file(string $source, ?array $testOverride = null): array
{
    return DriveFetcher::fetchToFile($source, $testOverride);
}
function find_watermark_ttf_font(): ?string
{
    return Watermarker::findTtfFont();
}
function ensure_protected_cache_subdir(string $sub): string
{
    return Watermarker::ensureCacheSubdir($sub);
}
function draw_tiled_watermark_on_canvas($canvas, int $w, int $h, string $stamp): void
{
    Watermarker::drawTiledOnCanvas($canvas, $w, $h, $stamp);
}
function get_cached_watermark_overlay(int $userId, string $userName, string $contact, int $w, int $h, string $watermarkBase)
{
    return Watermarker::getCachedOverlay($userId, $userName, $contact, $w, $h, $watermarkBase);
}
function client_prefers_webp(): bool
{
    return Watermarker::clientPrefersWebp();
}
function output_watermarked_page_image(string $srcPath, string $watermarkBase, array $opts = []): bool
{
    return Watermarker::outputWatermarkedImage($srcPath, $watermarkBase, $opts);
}
function page_storage_dir(int $itemId): string
{
    return PageServer::storageDir($itemId);
}
function page_staging_dir(int $itemId): string
{
    return PageServer::stagingDir($itemId);
}
function rrmdir_recursive(string $dir): void
{
    PageServer::rrmdirRecursive($dir);
}
function find_staging_page_file(int $itemId, int $pageNum): ?string
{
    return PageServer::findStagingPageFile($itemId, $pageNum);
}
function atomic_swap_prepared_pages(int $itemId): void
{
    PageServer::atomicSwapPreparedPages($itemId);
}
function ensure_pages_htaccess(): void
{
    PageServer::ensurePagesHtaccess();
}
function find_prepared_page_file(int $itemId, int $pageNum): ?string
{
    return PageServer::findPreparedPageFile($itemId, $pageNum);
}
function count_prepared_pages(int $itemId): int
{
    return PageServer::countPreparedPages($itemId);
}
function acquire_page_render_slot(?int $limitOverride = null): ?string
{
    return PageServer::acquireRenderSlot($limitOverride);
}
function release_page_render_slot(?string $token): void
{
    PageServer::releaseRenderSlot($token);
}
function resolve_page_variant_source(string $srcPath, int $itemId, int $pageNum, int $maxWidth): string
{
    return PageServer::resolveVariantSource($srcPath, $itemId, $pageNum, $maxWidth);
}
function stream_pdf_file_response(string $filePath, bool $unlinkAfter = false): never
{
    PageServer::streamPdfFileResponse($filePath, $unlinkAfter);
}
function slice_pdf_first_pages(string $pdfBytes, int $maxPages): ?string
{
    return PageServer::slicePdfFirstPages($pdfBytes, $maxPages);
}
function user_reading_stats(int $userId): array
{
    return PageServer::userReadingStats($userId);
}
function handle_stream_pdf(): never
{
    PageServer::handleStreamPdf();
}
function handle_reader_heartbeat(): never
{
    PageServer::handleHeartbeat();
}
function handle_reader_progress(): never
{
    PageServer::handleProgress();
}
function handle_protected_page(): never
{
    PageServer::handleProtectedPage();
}
function handle_reader_security_event(): never
{
    PageServer::handleSecurityEvent();
}
function handle_reader_bookmarks(): never
{
    PageServer::handleBookmarks();
}
function handle_reader_search(): never
{
    PageServer::handleSearch();
}
