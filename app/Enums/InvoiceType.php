<?php

declare(strict_types=1);

namespace App\Enums;

use App\Enums\Concerns\HasOptions;
use App\Enums\Contracts\HasPresentation;

enum InvoiceType: string implements HasPresentation
{
    use HasOptions;

    case Rent = 'rent';
    case ServiceCharge = 'service_charge';
    case Deposit = 'deposit';
    case LateFee = 'late_fee';
    case Utility = 'utility';
    case Other = 'other';

    public function label(): string
    {
        return __('enums.invoice_type.'.$this->value);
    }

    public function color(): string
    {
        return match ($this) {
            self::LateFee => 'danger',
            self::Deposit => 'info',
            default => 'neutral',
        };
    }

    /** Types staff may raise by hand (spec 8.5); the rest are generated. */
    public static function manual(): array
    {
        return [self::Utility, self::Other];
    }
}
