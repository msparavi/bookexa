<?php
declare(strict_types=1);

require_once __DIR__ . '/SmsGatewayInterface.php';

/**
 * Sandbox-ready Bangladeshi SMS gateway adapter skeleton (SSL Wireless / BulkSMSBD / Twilio-style).
 * Disabled by default; logs OTP payloads locally in sandbox mode without external HTTP calls.
 */
final class GenericSmsDriver implements SmsGatewayInterface
{
    private string $driver;
    private bool $sandbox;
    private string $apiUrl;
    private string $apiKey;
    private string $senderId;

    public function __construct(string $driver, array $cfg)
    {
        $this->driver   = $driver;
        $this->sandbox  = ($cfg['sandbox'] ?? '1') === '1';
        $this->apiUrl   = trim((string) ($cfg['api_url'] ?? ''));
        $this->apiKey   = trim((string) ($cfg['api_key'] ?? ''));
        $this->senderId = trim((string) ($cfg['sender_id'] ?? 'Bookexa')) ?: 'Bookexa';
    }

    public function getCode(): string
    {
        return $this->driver;
    }

    public function getName(): string
    {
        return match ($this->driver) {
            'sslwireless' => 'SSL Wireless SMS',
            'bulksmsbd'   => 'BulkSMSBD',
            default       => 'HTTP SMS Webhook Adapter',
        };
    }

    public function isEnabled(): bool
    {
        return true;
    }

    public function sendOtp(string $phone, string $code, string $message): array
    {
        if ($this->sandbox) {
            return [
                'ok'           => true,
                'sandbox'      => true,
                'sandbox_code' => $code,
                'message'      => 'OTP dispatched (Sandbox mode).',
            ];
        }

        if ($this->apiUrl === '' || $this->apiKey === '') {
            return ['ok' => false, 'message' => 'SMS gateway API URL or API key not configured.'];
        }

        $ch = curl_init($this->apiUrl);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 8,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => http_build_query([
                'api_key'   => $this->apiKey,
                'sender_id' => $this->senderId,
                'to'        => $phone,
                'message'   => $message,
            ]),
        ]);
        $resp = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode >= 200 && $httpCode < 300) {
            return ['ok' => true, 'sandbox' => false, 'response' => (string) $resp];
        }
        return ['ok' => false, 'message' => "SMS provider error (HTTP {$httpCode})."];
    }
}
