<?php

declare(strict_types=1);

namespace App\Services\Payments;

use App\Services\SettingsService;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Paystack REST API through the Laravel HTTP client (so tests use Http::fake).
 * Keys come from Settings > Payments (encrypted), falling back to .env.
 */
class PaystackGateway implements PaymentGateway
{
    public function __construct(private readonly SettingsService $settings) {}

    public function initialize(string $reference, int $amountMinor, string $currency, string $email, string $callbackUrl, array $metadata = []): string
    {
        $response = $this->client()->post('/transaction/initialize', [
            'reference' => $reference,
            'amount' => $amountMinor,
            'currency' => $currency,
            'email' => $email,
            'callback_url' => $callbackUrl,
            'metadata' => $metadata,
        ]);

        $url = $response->json('data.authorization_url');
        if (! $response->successful() || ! $response->json('status') || ! is_string($url)) {
            throw new RuntimeException((string) ($response->json('message') ?? 'Paystack did not start the payment.'));
        }

        return $url;
    }

    public function verify(string $reference): GatewayVerification
    {
        try {
            $response = $this->client()->get('/transaction/verify/'.rawurlencode($reference));
        } catch (ConnectionException $e) {
            return new GatewayVerification('unknown', null, null, null, [], $e->getMessage());
        }

        if (! $response->successful()) {
            // 404 means Paystack has no such transaction (the payer never completed checkout).
            $status = $response->status() === 404 ? 'abandoned' : 'unknown';

            return new GatewayVerification($status, null, null, null, (array) $response->json(), (string) $response->json('message'));
        }

        $data = (array) $response->json('data', []);

        return new GatewayVerification(
            status: (string) ($data['status'] ?? 'unknown'),
            amountMinor: isset($data['amount']) ? (int) $data['amount'] : null,
            currency: isset($data['currency']) ? strtoupper((string) $data['currency']) : null,
            gatewayReference: isset($data['id']) ? (string) $data['id'] : null,
            payload: $data,
            error: isset($data['gateway_response']) ? (string) $data['gateway_response'] : null,
        );
    }

    public function validWebhookSignature(string $rawBody, ?string $signature): bool
    {
        $secret = $this->secretKey();
        if ($secret === '' || $signature === null || $signature === '') {
            return false;
        }

        return hash_equals(hash_hmac('sha512', $rawBody, $secret), $signature);
    }

    public function isConfigured(): bool
    {
        return $this->secretKey() !== '' && $this->publicKey() !== '';
    }

    public function testConnection(): bool
    {
        try {
            return $this->client()->get('/bank', ['perPage' => 1])->successful();
        } catch (ConnectionException) {
            return false;
        }
    }

    public function publicKey(): string
    {
        return (string) $this->settings->get('paystack.public_key', config('rentora.paystack.public_key'));
    }

    private function secretKey(): string
    {
        return (string) $this->settings->get('paystack.secret_key', config('rentora.paystack.secret_key'));
    }

    private function client(): PendingRequest
    {
        return Http::baseUrl((string) config('rentora.paystack.base_url'))
            ->withToken($this->secretKey())
            ->acceptJson()
            ->asJson()
            ->timeout(20);
    }
}
