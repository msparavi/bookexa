<?php
declare(strict_types=1);

require_once __DIR__ . '/PaymentGatewayInterface.php';

/**
 * Default Manual Payment Driver (bKash / Nagad / Rocket TrxID admin verification).
 */
final class ManualPaymentDriver implements PaymentGatewayInterface
{
    public function getCode(): string
    {
        return 'manual';
    }

    public function getName(): string
    {
        return 'Manual Verification (bKash / Nagad / Rocket)';
    }

    public function isAutomatic(): bool
    {
        return false;
    }

    public function create(array $order, array $item, array $user): array
    {
        return [
            'ok'     => true,
            'mode'   => 'manual',
            'status' => 'pending',
        ];
    }

    public function verify(array $requestData): array
    {
        return [
            'ok'      => false,
            'status'  => 'pending',
            'message' => 'Manual orders require admin review.',
        ];
    }

    public function verifyWebhook(string $rawBody, array $headers): array
    {
        return [
            'ok'      => false,
            'message' => 'Manual driver does not process automatic webhooks.',
        ];
    }
}
