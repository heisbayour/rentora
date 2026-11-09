<?php

declare(strict_types=1);

namespace App\Enums;

use App\Enums\Concerns\HasOptions;
use App\Enums\Contracts\HasPresentation;

enum UnitType: string implements HasPresentation
{
    use HasOptions;

    case Flat = 'flat';
    case SelfContain = 'self_contain';
    case Room = 'room';
    case Duplex = 'duplex';
    case Bungalow = 'bungalow';
    case Shop = 'shop';
    case Office = 'office';
    case Warehouse = 'warehouse';
    case Other = 'other';

    public function label(): string
    {
        return __('enums.unit_type.'.$this->value);
    }

    public function color(): string
    {
        return 'neutral';
    }
}
