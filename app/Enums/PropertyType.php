<?php

declare(strict_types=1);

namespace App\Enums;

use App\Enums\Concerns\HasOptions;
use App\Enums\Contracts\HasPresentation;

enum PropertyType: string implements HasPresentation
{
    use HasOptions;

    case Residential = 'residential';
    case Commercial = 'commercial';
    case Mixed = 'mixed';

    public function label(): string
    {
        return __('enums.property_type.'.$this->value);
    }

    public function color(): string
    {
        return match ($this) {
            self::Residential => 'info',
            self::Commercial => 'warning',
            self::Mixed => 'neutral',
        };
    }
}
