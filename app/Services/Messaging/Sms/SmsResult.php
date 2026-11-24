<?php

declare(strict_types=1);

namespace App\Services\Messaging\Sms;

final class SmsResult
{
    private function __construct(
        public readonly bool $ok,
        public readonly ?string $providerId = null,
        public readonly ?string $error = null,
    ) {}

    public static function sent(?string $providerId = null): self
    {
        return new self(true, $providerId);
    }

    public static function failed(string $error): self
    {
        return new self(false, null, $error);
    }
}
