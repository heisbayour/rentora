<?php

declare(strict_types=1);

namespace App\Support;

/**
 * What happened to one channel (email or SMS) when a link was issued, shown
 * in the share panel: sent, queued, not set up, failed, or no address.
 */
final class Delivery
{
    public const SENT = 'sent';

    public const QUEUED = 'queued';

    public const NOT_SET_UP = 'not_set_up';

    public const FAILED = 'failed';

    public const NO_ADDRESS = 'no_address';

    public function __construct(
        public readonly string $channel,
        public readonly string $status,
        public readonly ?string $error = null,
    ) {}

    public static function sent(string $channel): self
    {
        return new self($channel, self::SENT);
    }

    public static function queued(string $channel): self
    {
        return new self($channel, self::QUEUED);
    }

    public static function notSetUp(string $channel): self
    {
        return new self($channel, self::NOT_SET_UP);
    }

    public static function failed(string $channel, string $error): self
    {
        return new self($channel, self::FAILED, mb_substr($error, 0, 200));
    }

    public static function noAddress(string $channel): self
    {
        return new self($channel, self::NO_ADDRESS);
    }

    public function ok(): bool
    {
        return $this->status === self::SENT || $this->status === self::QUEUED;
    }

    public function message(): string
    {
        return __('ui.share.delivery.'.$this->channel.'.'.$this->status, ['error' => (string) $this->error]);
    }

    /** @return array{ok: bool, message: string} */
    public function toArray(): array
    {
        return ['ok' => $this->ok(), 'message' => $this->message()];
    }
}
