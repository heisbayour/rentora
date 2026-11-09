<?php

declare(strict_types=1);

namespace App\Enums;

use App\Enums\Concerns\HasOptions;
use App\Enums\Contracts\HasPresentation;

enum UnitStatus: string implements HasPresentation
{
    use HasOptions;

    case Vacant = 'vacant';
    case Occupied = 'occupied';
    case Reserved = 'reserved';
    case UnderMaintenance = 'under_maintenance';

    public function label(): string
    {
        return __('enums.unit_status.'.$this->value);
    }

    public function color(): string
    {
        return match ($this) {
            self::Vacant => 'warning',
            self::Occupied => 'success',
            self::Reserved => 'info',
            self::UnderMaintenance => 'danger',
        };
    }

    /** Statuses a public listing may show (spec 8.11). */
    public function isListable(): bool
    {
        return $this === self::Vacant || $this === self::Reserved;
    }
}
