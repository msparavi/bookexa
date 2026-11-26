<?php
declare(strict_types=1);

require_once __DIR__ . '/SmsGatewayInterface.php';

final class NullSmsDriver implements SmsGatewayInterface
{
    public function getCode(): string
    {
        return 'none';
    }

    public function getName(): string
    {
        return 'Disabled (Password Login Only)';
    }

    public function isEnabled(): bool
    {
        return false;
    }

    public function sendOtp(string $phone, string $code, string $message): array
    {
        return ['ok' => false, 'message' => 'SMS OTP gateway is disabled.'];
    }
}
