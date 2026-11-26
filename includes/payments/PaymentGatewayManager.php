<?php
declare(strict_types=1);

require_once __DIR__ . '/PaymentGatewayInterface.php';
require_once __DIR__ . '/ManualPaymentDriver.php';
require_once __DIR__ . '/BdGatewaySkeletonDriver.php';

/**
 * Payment gateway registry, idempotent order state machine, notifications,
 * and protected payment-proof screenshot handling.
 */
function payment_gateway_enabled(): bool
{
    return site_setting('payment_gateway_enabled', '0') === '1';
}

function payment_gateway_driver(?string $overrideDriver = null): PaymentGatewayInterface
{
    if ($overrideDriver === null && !payment_gateway_enabled()) {
        return new ManualPaymentDriver();
    }
    $code = $overrideDriver ?? site_setting('payment_gateway_driver', 'manual');
    if ($code === '' || $code === 'manual') {
        return new ManualPaymentDriver();
    }
    return new BdGatewaySkeletonDriver($code, [
        'sandbox'        => site_setting('payment_gateway_sandbox', '1'),
        'base_url'       => site_setting('payment_gateway_base_url', ''),
        'api_key'        => site_setting('payment_gateway_api_key', ''),
        'api_secret'     => site_setting('payment_gateway_api_secret', ''),
        'webhook_secret' => site_setting('payment_gateway_webhook_secret', ''),
    ]);
}

function order_status_labels(string $status): array
{
    return match ($status) {
        'approved' => ['bn' => 'অনুমোদিত', 'en' => 'Approved', 'pill' => 'st-approved', 'badge' => 'bx-badge-success'],
        'rejected' => ['bn' => 'বাতিল (রিজেক্টেড)', 'en' => 'Rejected', 'pill' => 'st-rejected', 'badge' => 'bx-badge-accent'],
        'cancelled' => ['bn' => 'বাতিলকৃত', 'en' => 'Cancelled', 'pill' => 'st-blocked', 'badge' => 'bx-badge-accent'],
        'expired'  => ['bn' => 'মেয়াদোত্তীর্ণ', 'en' => 'Expired', 'pill' => 'st-inactive', 'badge' => 'bx-badge-accent'],
        default    => ['bn' => 'যাচাইয়ের অপেক্ষায়', 'en' => 'Pending', 'pill' => 'st-pending', 'badge' => 'bx-badge-gold'],
    };
}

/**
 * Send in-site notification + transactional email whenever an order's status changes.
 */
function notify_order_status_change(int $orderId, string $newStatus, ?string $publicNote = null): void
{
    $row = db_one(
        'SELECT o.*, u.name AS user_name, u.email AS user_email, i.slug AS item_slug, i.title_bn, i.title_en
         FROM orders o
         JOIN users u ON u.id = o.user_id
         JOIN items i ON i.id = o.item_id
         WHERE o.id = ? LIMIT 1',
        [$orderId]
    );
    if (!$row) {
        return;
    }

    $uid       = (int) $row['user_id'];
    $code      = (string) $row['order_code'];
    $reason    = trim((string) ($publicNote ?? $row['admin_note'] ?? ''));
    $invoiceRel = '/orders/' . rawurlencode($code) . '/invoice';
    $invoiceAbs = BASE_URL . $invoiceRel;
    $readerAbs  = BASE_URL . '/reader/' . rawurlencode((string) $row['item_slug']);

    if ($newStatus === 'approved') {
        create_user_notification(
            $uid,
            'order_approved',
            "অর্ডার অনুমোদিত হয়েছে (#{$code})",
            "Order Approved (#{$code})",
            $row['title_bn'] . ' আপনার লাইব্রেরিতে যুক্ত হয়েছে।',
            $row['title_en'] . ' is now unlocked in My Library.',
            '/reader/' . rawurlencode((string) $row['item_slug'])
        );
        if (!empty($row['user_email'])) {
            $bodyHtml = '<p>Hello <strong>' . e($row['user_name']) . '</strong>,</p>'
                . '<p>Your order <strong>#' . e($code) . '</strong> for <strong>' . e($row['title_en']) . '</strong> has been approved and unlocked in My Library.</p>'
                . '<p><a href="' . e($invoiceAbs) . '" style="color:#14b8a6;font-weight:700;">View / Print Order Invoice (#' . e($code) . ')</a></p>';
            send_bookexa_template_mail(
                (string) $row['user_email'],
                "Order Approved #{$code} — Bookexa",
                'Your PDF Is Ready to Read!',
                $bodyHtml,
                $readerAbs,
                'Open in Reader'
            );
        }
        return;
    }

    if ($newStatus === 'rejected') {
        $reasonBn = $reason !== '' ? ('কারণ: ' . $reason) : ($row['title_bn'] . ' এর পেমেন্ট তথ্য যাচাই করা যায়নি।');
        $reasonEn = $reason !== '' ? ('Reason: ' . $reason) : ('Payment verification failed for ' . $row['title_en'] . '.');
        create_user_notification(
            $uid,
            'order_rejected',
            "অর্ডার রিজেক্ট করা হয়েছে (#{$code})",
            "Order Rejected (#{$code})",
            $reasonBn . ' (সঠিক তথ্য দিয়ে পুনরায় জমা দিন)',
            $reasonEn . ' You can fix TrxID/sender & resubmit.',
            '/orders'
        );
        if (!empty($row['user_email'])) {
            $bodyHtml = '<p>Hello <strong>' . e($row['user_name']) . '</strong>,</p>'
                . '<p>Your payment submission for order <strong>#' . e($code) . '</strong> (<strong>' . e($row['title_en']) . '</strong>) could not be approved.</p>'
                . ($reason !== '' ? '<p><strong>Reject Reason:</strong> ' . e($reason) . '</p>' : '')
                . '<p>You can edit your sender phone number / Transaction ID and resubmit directly from My Orders, or view your receipt at <a href="' . e($invoiceAbs) . '">Invoice #' . e($code) . '</a>.</p>';
            send_bookexa_template_mail(
                (string) $row['user_email'],
                "Order Rejected #{$code} — Action Required",
                "Payment Update Needed #{$code}",
                $bodyHtml,
                BASE_URL . '/orders',
                'Fix & Resubmit in My Orders'
            );
        }
        return;
    }

    if ($newStatus === 'cancelled') {
        create_user_notification(
            $uid,
            'order_cancelled',
            "অর্ডার বাতিল করা হয়েছে (#{$code})",
            "Order Cancelled (#{$code})",
            $row['title_bn'] . ' এর অর্ডারটি বাতিল করা হয়েছে।',
            'Your pending order for ' . $row['title_en'] . ' has been cancelled.',
            '/orders'
        );
        if (!empty($row['user_email'])) {
            send_bookexa_template_mail(
                (string) $row['user_email'],
                "Order Cancelled #{$code} — Bookexa",
                "Order Cancelled #{$code}",
                '<p>Hello <strong>' . e($row['user_name']) . '</strong>,</p><p>Your order <strong>#' . e($code) . '</strong> for <strong>' . e($row['title_en']) . '</strong> has been cancelled.</p>',
                BASE_URL . '/orders',
                'View My Orders'
            );
        }
        return;
    }

    if ($newStatus === 'expired') {
        create_user_notification(
            $uid,
            'order_expired',
            "অর্ডারের মেয়াদ শেষ হয়েছে (#{$code})",
            "Order Expired (#{$code})",
            $row['title_bn'] . ' এর অসম্পূর্ণ অর্ডারটির সময়সীমা শেষ হওয়ায় বন্ধ হয়েছে।',
            'Your pending order for ' . $row['title_en'] . ' expired automatically. Place a new order anytime.',
            '/book/' . rawurlencode((string) $row['item_slug'])
        );
        if (!empty($row['user_email'])) {
            send_bookexa_template_mail(
                (string) $row['user_email'],
                "Order Expired #{$code} — Bookexa",
                "Order Expired #{$code}",
                '<p>Hello <strong>' . e($row['user_name']) . '</strong>,</p><p>Your pending order <strong>#' . e($code) . '</strong> for <strong>' . e($row['title_en']) . '</strong> expired automatically because verification was not completed in time.</p>',
                BASE_URL . '/book/' . rawurlencode((string) $row['item_slug']),
                'View PDF'
            );
        }
        return;
    }

    if ($newStatus === 'pending') {
        create_user_notification(
            $uid,
            'order_pending',
            "অর্ডার পুনরায় জমা হয়েছে (#{$code})",
            "Order Resubmitted (#{$code})",
            $row['title_bn'] . ' এর সংশোধিত পেমেন্ট তথ্য যাচাইয়ের জন্য পাঠানো হয়েছে।',
            'Your updated payment details for ' . $row['title_en'] . ' are pending verification.',
            '/orders'
        );
        if (!empty($row['user_email'])) {
            send_bookexa_template_mail(
                (string) $row['user_email'],
                "Order Resubmitted #{$code} — Bookexa",
                "Payment Details Updated #{$code}",
                '<p>Hello <strong>' . e($row['user_name']) . '</strong>,</p><p>Your updated payment details for order <strong>#' . e($code) . '</strong> (<strong>' . e($row['title_en']) . '</strong>) have been resubmitted and are now pending review.</p>',
                BASE_URL . '/orders',
                'View My Orders'
            );
        }
    }
}

/**
 * Shared atomic & idempotent order status transition helper used by:
 * - Admin single & bulk order review
 * - Automatic gateway verification / webhook handler
 * - User self-cancellation of pending orders
 * - Lazy expiration of stale pending orders
 */
function apply_order_status_transition(
    int $orderId,
    string $targetStatus,
    ?int $adminId = null,
    ?string $publicNote = null,
    ?string $internalNote = null,
    ?string $gatewayRef = null
): array {
    $allowed = ['approved', 'rejected', 'cancelled', 'expired'];
    if (!in_array($targetStatus, $allowed, true)) {
        return ['ok' => false, 'message' => 'Invalid target order status.'];
    }

    db()->beginTransaction();
    try {
        $order = db_one('SELECT * FROM orders WHERE id = ? FOR UPDATE', [$orderId]);
        if (!$order) {
            db()->rollBack();
            return ['ok' => false, 'message' => 'Order not found.'];
        }

        $oldStatus = (string) $order['status'];
        if ($oldStatus === $targetStatus) {
            if ($internalNote !== null) {
                db_exec('UPDATE orders SET internal_notes = ? WHERE id = ?', [$internalNote !== '' ? $internalNote : null, $orderId]);
            }
            db()->commit();
            return ['ok' => true, 'changed' => false, 'already_in_status' => true, 'old_status' => $oldStatus, 'order' => $order];
        }

        $noteToStore = $publicNote !== null ? ($publicNote !== '' ? mb_substr($publicNote, 0, 255) : null) : $order['admin_note'];
        $intToStore  = $internalNote !== null ? ($internalNote !== '' ? $internalNote : null) : ($order['internal_notes'] ?? null);
        $gRefToStore = $gatewayRef !== null ? substr($gatewayRef, 0, 120) : ($order['gateway_ref'] ?? null);

        if ($targetStatus === 'approved') {
            db_exec(
                'UPDATE orders SET status = "approved", admin_note = ?, internal_notes = ?, gateway_ref = ?, reviewed_by = COALESCE(?, reviewed_by), reviewed_at = NOW() WHERE id = ?',
                [$noteToStore, $intToStore, $gRefToStore, $adminId, $orderId]
            );
            db_exec(
                'INSERT INTO library (user_id, item_id, order_id) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE order_id = VALUES(order_id)',
                [(int) $order['user_id'], (int) $order['item_id'], $orderId]
            );
            // If an order had previously decremented a coupon on reject/expire/cancel and was manually approved later, mark it active
            if (!empty($order['coupon_id']) && (int) ($order['coupon_decremented'] ?? 0) === 1) {
                db_exec('UPDATE orders SET coupon_decremented = 0 WHERE id = ?', [$orderId]);
                db_exec('UPDATE coupons SET used_count = used_count + 1 WHERE id = ?', [(int) $order['coupon_id']]);
            }
        } else {
            db_exec(
                'UPDATE orders SET status = ?, admin_note = ?, internal_notes = ?, gateway_ref = ?, reviewed_by = COALESCE(?, reviewed_by), reviewed_at = NOW() WHERE id = ?',
                [$targetStatus, $noteToStore, $intToStore, $gRefToStore, $adminId, $orderId]
            );
            if (!empty($order['coupon_id']) && !(int) ($order['coupon_decremented'] ?? 0)) {
                $dec = db_exec('UPDATE orders SET coupon_decremented = 1 WHERE id = ? AND coupon_decremented = 0', [$orderId]);
                if ($dec === 1) {
                    db_exec('UPDATE coupons SET used_count = GREATEST(0, used_count - 1) WHERE id = ?', [(int) $order['coupon_id']]);
                }
            }
        }

        db()->commit();
    } catch (Throwable $e) {
        if (db()->inTransaction()) {
            db()->rollBack();
        }
        throw $e;
    }

    notify_order_status_change($orderId, $targetStatus, $publicNote);
    return ['ok' => true, 'changed' => true, 'already_in_status' => false, 'old_status' => $oldStatus, 'order' => $order];
}

function approve_order_idempotent(int $orderId, string $source = 'gateway', ?string $gatewayRef = null, ?int $adminId = null): array
{
    $res = apply_order_status_transition($orderId, 'approved', $adminId, null, null, $gatewayRef);
    $res['already_approved'] = !empty($res['already_in_status']);
    return $res;
}

function ensure_payment_proofs_htaccess(): string
{
    $dir = APP_ROOT . '/storage/payment_proofs';
    if (!is_dir($dir)) {
        @mkdir($dir, 0755, true);
    }
    $ht = $dir . '/.htaccess';
    if (!is_file($ht)) {
        @file_put_contents($ht, "<IfModule mod_authz_core.c>\n    Require all denied\n</IfModule>\n<IfModule !mod_authz_core.c>\n    Order allow,deny\n    Deny from all\n</IfModule>\n");
    }
    return $dir;
}

function save_payment_screenshot_upload(string $fieldName = 'payment_screenshot'): ?string
{
    if (empty($_FILES[$fieldName]) || (int) ($_FILES[$fieldName]['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
        return null;
    }
    $file = $_FILES[$fieldName];
    if ((int) $file['error'] !== UPLOAD_ERR_OK) {
        throw new RuntimeException(current_lang() === 'bn' ? 'পেমেন্ট স্ক্রিনশট আপলোড ব্যর্থ হয়েছে।' : 'Payment screenshot upload failed.');
    }
    if ((int) $file['size'] > 3 * 1024 * 1024) {
        throw new RuntimeException(current_lang() === 'bn' ? 'স্ক্রিনশট সর্বোচ্চ ৩ মেগাবাইট (3 MB) হতে পারবে।' : 'Payment screenshot must be 3 MB or smaller.');
    }
    if (!is_uploaded_file((string) $file['tmp_name'])) {
        throw new RuntimeException('Invalid uploaded payment proof.');
    }

    $ext = strtolower(pathinfo((string) $file['name'], PATHINFO_EXTENSION));
    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime = (string) $finfo->file((string) $file['tmp_name']);
    $allowedMimes = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
    if ($ext === 'svg' || $ext === 'svgz' || str_contains($mime, 'svg') || !isset($allowedMimes[$mime])) {
        throw new RuntimeException(current_lang() === 'bn' ? 'শুধুমাত্র JPG, PNG অথবা WebP স্ক্রিনশট অনুমোদিত।' : 'Only JPG, PNG, or WebP payment screenshots are allowed.');
    }

    $src = match ($mime) {
        'image/jpeg' => @imagecreatefromjpeg((string) $file['tmp_name']),
        'image/png'  => @imagecreatefrompng((string) $file['tmp_name']),
        'image/webp' => function_exists('imagecreatefromwebp') ? @imagecreatefromwebp((string) $file['tmp_name']) : false,
        default      => false,
    };
    if (!$src) {
        throw new RuntimeException(current_lang() === 'bn' ? 'ছবিটি পড়া যায়নি। সঠিক JPG/PNG/WebP দিন।' : 'Could not decode image file. Upload a valid JPG, PNG, or WebP screenshot.');
    }

    // Re-encode safely (strips EXIF payloads) & cap width at 1600px
    $w = imagesx($src);
    $h = imagesy($src);
    if ($w > 1600) {
        $nw = 1600;
        $nh = max(1, (int) round($h * (1600 / $w)));
        $dst = imagecreatetruecolor($nw, $nh);
        imagecopyresampled($dst, $src, 0, 0, 0, 0, $nw, $nh, $w, $h);
        imagedestroy($src);
        $src = $dst;
    }

    $dir = ensure_payment_proofs_htaccess();
    $useWebp = function_exists('imagewebp');
    $outExt  = $useWebp ? 'webp' : 'jpg';
    $fname   = 'pay-' . bin2hex(random_bytes(10)) . '.' . $outExt;
    $dest    = $dir . '/' . $fname;

    $ok = $useWebp ? @imagewebp($src, $dest, 84) : @imagejpeg($src, $dest, 86);
    imagedestroy($src);
    if (!$ok || !is_file($dest)) {
        throw new RuntimeException('Failed to store sanitized payment screenshot.');
    }

    return 'payment_proofs/' . $fname;
}

function resolve_payment_screenshot_path(?string $storedRel): ?string
{
    $storedRel = trim((string) $storedRel);
    if ($storedRel === '' || !preg_match('#^payment_proofs/[a-zA-Z0-9._-]+$#', $storedRel)) {
        return null;
    }
    $abs = APP_ROOT . '/storage/' . $storedRel;
    return is_file($abs) ? $abs : null;
}
