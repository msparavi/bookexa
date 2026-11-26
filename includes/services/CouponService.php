<?php
declare(strict_types=1);

/**
 * Facade service for Digital PDF checkout coupons & Physical Shop cart coupons.
 */
final class CouponService
{
    public static function validateForItem(string $codeRaw, int $userId, int $itemId, float $basePrice): array
    {
        return validate_coupon_for_checkout($codeRaw, $userId, $itemId, $basePrice);
    }

    public static function validateForShopCart(string $codeRaw, ?int $userId, float $subtotal): array
    {
        return shop_validate_coupon_for_cart($codeRaw, $userId, $subtotal);
    }
}
