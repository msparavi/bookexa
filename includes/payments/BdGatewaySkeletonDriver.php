<?php
declare(strict_types=1);

require_once __DIR__ . '/PaymentGatewayInterface.php';

/**
 * Sandbox-ready Bangladeshi automatic payment gateway adapter skeleton
 * supporting bKash Tokenized, aamarPay, or UddoktaPay style flows.
 * Disabled by default until enabled and configured inside Admin Settings.
 */
final class BdGatewaySkeletonDriver implements PaymentGatewayInterface
{
    private string $code;
    private bool $sandbox;
    private string $baseUrl;
    private string $apiKey;
    private string $apiSecret;
    private string $webhookSecret;

    public function __construct(string $code, array $config)
    {
        $this->code          = in_array($code, ['bkash_tokenized', 'aamarpay', 'uddoktapay'], true) ? $code : 'bkash_tokenized';
        $this->sandbox       = ($config['sandbox'] ?? '1') === '1';
        $this->baseUrl       = rtrim((string) ($config['base_url'] ?? ''), '/');
        $this->apiKey        = (string) ($config['api_key'] ?? '');
        $this->apiSecret     = (string) ($config['api_secret'] ?? '');
        $this->webhookSecret = (string) ($config['webhook_secret'] ?? '');
    }

    public function getCode(): string
    {
        return $this->code;
    }

    public function getName(): string
    {
        return match ($this->code) {
            'aamarpay'   => 'aamarPay Gateway Adapter',
            'uddoktapay' => 'UddoktaPay Gateway Adapter',
            default      => 'bKash Tokenized Checkout Adapter',
        };
    }

    public function isAutomatic(): bool
    {
        return true;
    }

    private function signingSecret(): string
    {
        if ($this->webhookSecret !== '') {
            return $this->webhookSecret;
        }
        if ($this->apiSecret !== '') {
            return $this->apiSecret;
        }
        return app_secret();
    }

    public function create(array $order, array $item, array $user): array
    {
        $orderCode = (string) ($order['order_code'] ?? '');
        $amount    = number_format((float) ($order['amount'] ?? 0), 2, '.', '');

        if ($this->sandbox) {
            $ts  = (string) time();
            $sig = hash_hmac('sha256', "{$orderCode}|{$this->code}|{$amount}|{$ts}", $this->signingSecret());
            return [
                'ok'           => true,
                'mode'         => 'redirect',
                'redirect_url' => url('/api/payment-sandbox?order=' . rawurlencode($orderCode) . '&driver=' . rawurlencode($this->code) . '&ts=' . $ts . '&sig=' . $sig),
                'gateway_ref'  => 'SBX-' . strtoupper(bin2hex(random_bytes(4))),
            ];
        }

        if ($this->baseUrl === '' || $this->apiKey === '' || $this->apiSecret === '') {
            return [
                'ok'      => false,
                'message' => 'Automatic payment gateway credentials are not configured.',
            ];
        }

        // Production provider API integration point:
        // Execute HTTP call against {$this->baseUrl} using $this->apiKey / $this->apiSecret,
        // passing callback URLs and signed metadata for order {$orderCode} ({$amount} BDT).
        return [
            'ok'      => false,
            'message' => 'Configure provider live API call or enable Sandbox mode in Admin Settings.',
        ];
    }

    public function verify(array $requestData): array
    {
        $orderCode  = trim((string) ($requestData['order'] ?? $requestData['order_code'] ?? ''));
        $driver     = trim((string) ($requestData['driver'] ?? $this->code));
        $status     = strtolower(trim((string) ($requestData['status'] ?? '')));
        $ts         = trim((string) ($requestData['ts'] ?? ''));
        $gatewayRef = trim((string) ($requestData['gateway_ref'] ?? ''));
        $sig        = strtolower(trim((string) ($requestData['sig'] ?? '')));

        if ($orderCode === '' || $ts === '' || $sig === '' || abs(time() - (int) $ts) > 1800) {
            return ['ok' => false, 'message' => 'Missing or expired payment verification parameters.'];
        }

        $expected = hash_hmac('sha256', "{$orderCode}|{$driver}|{$status}|{$gatewayRef}|{$ts}", $this->signingSecret());
        if (!hash_equals($expected, $sig)) {
            return ['ok' => false, 'message' => 'Invalid payment callback signature.'];
        }

        return [
            'ok'          => true,
            'order_code'  => $orderCode,
            'status'      => $status === 'approved' ? 'approved' : 'failed',
            'gateway_ref' => $gatewayRef,
        ];
    }

    public function verifyWebhook(string $rawBody, array $headers): array
    {
        if ($rawBody === '') {
            return ['ok' => false, 'message' => 'Empty webhook payload.'];
        }

        $sigHeader = '';
        foreach ($headers as $k => $v) {
            $lk = strtolower((string) $k);
            if ($lk === 'x-bookexa-signature' || $lk === 'x-signature') {
                $sigHeader = trim((string) $v);
                break;
            }
        }
        $payload = json_decode($rawBody, true);
        if (!is_array($payload)) {
            return ['ok' => false, 'message' => 'Invalid webhook JSON.'];
        }
        if ($sigHeader === '' && !empty($payload['signature'])) {
            $sigHeader = trim((string) $payload['signature']);
        }
        if ($sigHeader === '') {
            return ['ok' => false, 'message' => 'Missing webhook signature header.'];
        }

        // Support either signed raw body or canonical "order_code|status|gateway_ref|amount"
        $sigClean = strtolower(preg_replace('/^sha256=/i', '', $sigHeader) ?? '');
        $secret   = $this->signingSecret();
        $expectedRaw = hash_hmac('sha256', $rawBody, $secret);

        $orderCode  = trim((string) ($payload['order_code'] ?? ''));
        $status     = strtolower(trim((string) ($payload['status'] ?? '')));
        $gatewayRef = trim((string) ($payload['gateway_ref'] ?? $payload['trx_id'] ?? ''));
        $amount     = number_format((float) ($payload['amount'] ?? 0), 2, '.', '');
        $expectedCanonical = hash_hmac('sha256', "{$orderCode}|{$status}|{$gatewayRef}|{$amount}", $secret);

        if (!hash_equals($expectedRaw, $sigClean) && !hash_equals($expectedCanonical, $sigClean)) {
            return ['ok' => false, 'message' => 'Webhook signature mismatch.'];
        }
        if ($orderCode === '' || $status === '') {
            return ['ok' => false, 'message' => 'Webhook missing order_code or status.'];
        }

        return [
            'ok'          => true,
            'order_code'  => $orderCode,
            'status'      => in_array($status, ['approved', 'paid', 'success', 'completed'], true) ? 'approved' : (in_array($status, ['cancelled', 'canceled'], true) ? 'cancelled' : 'rejected'),
            'gateway_ref' => $gatewayRef !== '' ? $gatewayRef : null,
            'amount'      => (float) ($payload['amount'] ?? 0),
        ];
    }
}
