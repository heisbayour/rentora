<?php

declare(strict_types=1);

namespace App\Enums;

use App\Enums\Concerns\HasOptions;
use App\Enums\Contracts\HasPresentation;

enum LateFeeType: string implements HasPresentation
{
    use HasOptions;

    case None = 'none';
    case Fixed = 'fixed';
    case Percent = 'percent';

    public function label(): string
    {
        return __('enums.late_fee_type.'.$this->value);
    }

    public function color(): string
    {
        return 'neutral';
    }
}
