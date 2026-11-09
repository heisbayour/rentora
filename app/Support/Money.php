<?php

declare(strict_types=1);

namespace App\Support;

use InvalidArgumentException;
use JsonSerializable;
use Stringable;

/**
 * An amount in minor units (kobo for NGN). Never a float (spec 4.1).
 */
final class Money implements JsonSerializable, Stringable
{
    private const SYMBOLS = ['NGN' => '₦', 'GHS' => 'GH₵', 'KES' => 'KSh', 'USD' => '$'];

    private function __construct(
        public readonly int $minor,
        public readonly string $currency,
    ) {}

    public static function of(int $minor, ?string $currency = null): self
    {
        return new self($minor, strtoupper($currency ?? (string) config('rentora.defaults.currency')));
    }

    /** An amount in the current organization's currency. */
    public static function forOrganization(int $minor): self
    {
        return self::of($minor, app(CurrentOrganization::class)->get()?->currency);
    }

    public static function zero(?string $currency = null): self
    {
        return self::of(0, $currency);
    }

    /**
     * Parses a major-unit string such as "1,250,000.50" without going through a float.
     */
    public static function parse(string $major, ?string $currency = null): self
    {
        $clean = str_replace([',', ' ', '_'], '', trim($major));
        if (! preg_match('/^(-)?(\d*)(?:\.(\d{0,2}))?$/', $clean, $m) || ($m[2] === '' && ($m[3] ?? '') === '')) {
            throw new InvalidArgumentException("Not a money amount: [{$major}].");
        }
        $whole = (int) ($m[2] === '' ? '0' : $m[2]);
        $fraction = (int) str_pad($m[3] ?? '', 2, '0');
        $minor = $whole * 100 + $fraction;

        return self::of($m[1] === '-' ? -$minor : $minor, $currency);
    }

    public function add(self $other): self
    {
        $this->assertSameCurrency($other);

        return new self($this->minor + $other->minor, $this->currency);
    }

    public function subtract(self $other): self
    {
        $this->assertSameCurrency($other);

        return new self($this->minor - $other->minor, $this->currency);
    }

    public function multiply(int $factor): self
    {
        return new self($this->minor * $factor, $this->currency);
    }

    /** Percentage in basis points (1250 = 12.5%), rounded half up to the nearest minor unit. */
    public function percentage(int $basisPoints): self
    {
        $raw = $this->minor * $basisPoints;

        return new self(intdiv($raw + ($raw >= 0 ? 5000 : -5000), 10000), $this->currency);
    }

    public function min(self $other): self
    {
        $this->assertSameCurrency($other);

        return $this->minor <= $other->minor ? $this : $other;
    }

    public function isZero(): bool
    {
        return $this->minor === 0;
    }

    public function isPositive(): bool
    {
        return $this->minor > 0;
    }

    public function isNegative(): bool
    {
        return $this->minor < 0;
    }

    public function greaterThan(self $other): bool
    {
        $this->assertSameCurrency($other);

        return $this->minor > $other->minor;
    }

    public function equals(self $other): bool
    {
        return $this->currency === $other->currency && $this->minor === $other->minor;
    }

    public function symbol(): string
    {
        return self::symbolFor($this->currency);
    }

    public static function symbolFor(?string $currency): string
    {
        $currency = strtoupper($currency ?? (string) config('rentora.defaults.currency'));

        return self::SYMBOLS[$currency] ?? $currency.' ';
    }

    /** "₦1,250,000.00"; negative amounts as "-₦500.00". */
    public function format(bool $withSymbol = true): string
    {
        $absolute = abs($this->minor);
        $text = number_format(intdiv($absolute, 100)).'.'.str_pad((string) ($absolute % 100), 2, '0', STR_PAD_LEFT);

        return ($this->minor < 0 ? '-' : '').($withSymbol ? $this->symbol() : '').$text;
    }

    /** Major units as a plain string for form fields, for example "1250000.50". */
    public function toDecimalString(): string
    {
        $absolute = abs($this->minor);

        return ($this->minor < 0 ? '-' : '').intdiv($absolute, 100).'.'.str_pad((string) ($absolute % 100), 2, '0', STR_PAD_LEFT);
    }

    public function __toString(): string
    {
        return $this->format();
    }

    /** @return array{minor: int, currency: string, formatted: string} */
    public function jsonSerialize(): array
    {
        return ['minor' => $this->minor, 'currency' => $this->currency, 'formatted' => $this->format()];
    }

    private function assertSameCurrency(self $other): void
    {
        if ($this->currency !== $other->currency) {
            throw new InvalidArgumentException("Currency mismatch: {$this->currency} and {$other->currency}.");
        }
    }
}
