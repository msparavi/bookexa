<?php
declare(strict_types=1);

interface SmsGatewayInterface
{
    public function getCode(): string;

    public function getName(): string;

    public function isEnabled(): bool;

    public function sendOtp(string $phone, string $code, string $message): array;
}
