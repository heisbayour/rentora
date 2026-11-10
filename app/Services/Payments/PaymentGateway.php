<?php

declare(strict_types=1);

namespace App\Services\Payments;

/** An online payment provider (spec 3: Paystack behind this interface). */
interface PaymentGateway
{
    /**
     * Starts a hosted checkout and returns the URL to redirect the payer to.
     *
     * @param  array<string, mixed>  $metadata
     */
    public function initialize(string $reference, int $amountMinor, string $currency, string $email, string $callbackUrl, array $metadata = []): string;

    /** Asks the provider what happened to a transaction. */
    public function verify(string $reference): GatewayVerification;

    /** Checks a webhook signature against the raw request body. */
    public function validWebhookSignature(string $rawBody, ?string $signature): bool;

    /** Whether keys are set, so online payment can be offered at all (otherwise tenants pay by transfer). */
    public function isConfigured(): bool;

    /** Whether the configured secret key is accepted by the provider. */
    public function testConnection(): bool;
}
