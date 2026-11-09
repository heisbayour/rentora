<?php

declare(strict_types=1);

namespace App\Services\Payments;

use App\Models\Payment;
use Illuminate\Support\Str;

/** Payment references (RNT-{ulid}) and 10-character receipt verification codes. */
class PaymentCodes
{
    /** No 0/O or 1/I/L, so codes typed from a printed receipt are unambiguous. */
    private const ALPHABET = 'ABCDEFGHJKMNPQRSTUVWXYZ23456789';

    public function reference(): string
    {
        return 'RNT-'.Str::ulid()->toBase32();
    }

    public function verificationCode(): string
    {
        do {
            $code = '';
            for ($i = 0; $i < 10; $i++) {
                $code .= self::ALPHABET[random_int(0, strlen(self::ALPHABET) - 1)];
            }
        } while (Payment::query()->withoutGlobalScopes()->where('verification_code', $code)->exists());

        return $code;
    }
}
