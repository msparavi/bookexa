<?php
declare(strict_types=1);

/**
 * Facade service unifying Digital PDF Orders & Physical Shop Orders lifecycle operations.
 */
final class OrderService
{
    public static function applyDigitalTransition(int $orderId, string $targetStatus, ?int $adminId = null, ?string $publicNote = null, ?string $internalNote = null, ?string $gatewayRef = null): array
    {
        return apply_order_status_transition($orderId, $targetStatus, $adminId, $publicNote, $internalNote, $gatewayRef);
    }

    public static function approveDigitalIdempotent(int $orderId, string $source = 'gateway', ?string $gatewayRef = null, ?int $adminId = null): array
    {
        return approve_order_idempotent($orderId, $source, $gatewayRef, $adminId);
    }

    public static function createShopOrder(array $input, ?array $user = null, bool $checkRateLimit = true): array
    {
        return shop_create_order($input, $user, $checkRateLimit);
    }

    public static function transitionShopOrder(int $orderId, string $targetStatus, ?int $adminId = null, array $meta = []): array
    {
        return shop_transition_order_status($orderId, $targetStatus, $adminId, $meta);
    }

    public static function requestShopReturn(int $orderId, int $userId, string $reason): array
    {
        return shop_request_return($orderId, $userId, $reason);
    }

    public static function handleShopReturn(int $orderId, string $decision, int $adminId, bool $restock = true, ?string $note = null): array
    {
        return shop_handle_return($orderId, $decision, $adminId, $restock, $note);
    }
}
