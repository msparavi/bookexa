<?php
declare(strict_types=1);

/**
 * Payment gateway adapter contract so manual verification and automatic
 * Bangladeshi gateways share a unified interface.
 */
interface PaymentGatewayInterface
{
    public function getCode(): string;

    public function getName(): string;

    public function isAutomatic(): bool;

    public function create(array $order, array $item, array $user): array;

    public function verify(array $requestData): array;

    public function verifyWebhook(string $rawBody, array $headers): array;
}
