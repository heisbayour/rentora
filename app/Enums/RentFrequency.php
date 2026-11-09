<?php

declare(strict_types=1);

namespace App\Enums;

use App\Enums\Concerns\HasOptions;
use App\Enums\Contracts\HasPresentation;

enum RentFrequency: string implements HasPresentation
{
    use HasOptions;

    case Monthly = 'monthly';
    case Quarterly = 'quarterly';
    case Biannual = 'biannual';
    case Annual = 'annual';

    public function label(): string
    {
        return __('enums.rent_frequency.'.$this->value);
    }

    public function color(): string
    {
        return 'neutral';
    }

    /** Length of one billing period. */
    public function months(): int
    {
        return match ($this) {
            self::Monthly => 1,
            self::Quarterly => 3,
            self::Biannual => 6,
            self::Annual => 12,
        };
    }

    /** Default days before the period starts that its invoice is issued (spec 5.3). */
    public function defaultLeadDays(): int
    {
        return match ($this) {
            self::Monthly => 7,
            self::Quarterly => 14,
            self::Biannual => 21,
            self::Annual => 30,
        };
    }
}
