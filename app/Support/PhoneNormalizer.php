<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Phone numbers to E.164 (spec 9.2). The default country is Nigeria (+234):
 * "0803 123 4567" becomes "+2348031234567". Returns null for anything that
 * cannot be a valid number, so the caller can fail the message immediately.
 */
final class PhoneNormalizer
{
    public static function toE164(?string $phone, ?string $countryCode = null): ?string
    {
        $countryCode ??= (string) config('rentora.defaults.phone_country_code', '234');
        $phone = trim((string) $phone);
        if ($phone === '') {
            return null;
        }

        $hasPlus = str_starts_with($phone, '+');
        $digits = preg_replace('/\D+/', '', $phone) ?? '';
        if ($digits === '') {
            return null;
        }

        if (! $hasPlus) {
            if (str_starts_with($digits, '00')) {
                $digits = substr($digits, 2);
            } elseif (str_starts_with($digits, '0')) {
                $digits = $countryCode.substr($digits, 1);
            } elseif (! str_starts_with($digits, $countryCode)) {
                $digits = $countryCode.$digits;
            }
        }

        // Nigerian numbers: 234 followed by exactly 10 digits, starting 7, 8 or 9.
        if (str_starts_with($digits, '234')) {
            return preg_match('/^234[789]\d{9}$/', $digits) === 1 ? '+'.$digits : null;
        }

        return preg_match('/^[1-9]\d{7,14}$/', $digits) === 1 ? '+'.$digits : null;
    }

    /** Digits only (no plus), as wa.me links expect. */
    public static function forWhatsApp(?string $phone): ?string
    {
        $e164 = self::toE164($phone);

        return $e164 === null ? null : ltrim($e164, '+');
    }

    /** A wa.me click-to-send link with a prefilled message (spec 8.8). */
    public static function whatsAppLink(?string $phone, string $message = ''): ?string
    {
        $digits = self::forWhatsApp($phone);
        if ($digits === null) {
            return null;
        }

        return 'https://wa.me/'.$digits.($message !== '' ? '?text='.rawurlencode($message) : '');
    }
}
