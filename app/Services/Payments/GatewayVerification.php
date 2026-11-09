<?php

declare(strict_types=1);

namespace App\Services\Payments;

/** The provider's answer about one transaction. */
final class GatewayVerification
{
    /**
     * @param  array<string, mixed>  $payload
     */
    public function __construct(
        public readonly string $status,
        public readonly ?int $amountMinor,
        public readonly ?string $currency,
        public readonly ?string $gatewayReference,
        public readonly array $payload = [],
        public readonly ?string $error = null,
    ) {}

    public function succeeded(): bool
    {
        return $this->status === 'success';
    }

    /** Still waiting on the payer or bank; neither success nor a final failure. */
    public function isPending(): bool
    {
        return in_array($this->status, ['pending', 'ongoing', 'processing', 'queued', 'unknown'], true);
    }
}
