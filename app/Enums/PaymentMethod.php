<?php

declare(strict_types=1);

namespace App\Enums;

use App\Enums\Concerns\HasOptions;
use App\Enums\Contracts\HasPresentation;

enum PaymentMethod: string implements HasPresentation
{
    use HasOptions;

    case Paystack = 'paystack';
    case BankTransfer = 'bank_transfer';
    case Cash = 'cash';
    case Pos = 'pos';
    case Cheque = 'cheque';
    case Other = 'other';

    public function label(): string
    {
        return __('enums.payment_method.'.$this->value);
    }

    public function color(): string
    {
        return $this === self::Paystack ? 'info' : 'neutral';
    }
}
