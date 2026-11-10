<?php

declare(strict_types=1);

namespace App\Enums;

use App\Enums\Concerns\HasOptions;
use App\Enums\Contracts\HasPresentation;

enum MaintenanceCategory: string implements HasPresentation
{
    use HasOptions;

    case Plumbing = 'plumbing';
    case Electrical = 'electrical';
    case Carpentry = 'carpentry';
    case Painting = 'painting';
    case Appliance = 'appliance';
    case Roofing = 'roofing';
    case Security = 'security';
    case PestControl = 'pest_control';
    case Cleaning = 'cleaning';
    case Other = 'other';

    public function label(): string
    {
        return __('enums.maintenance_category.'.$this->value);
    }

    public function color(): string
    {
        return 'neutral';
    }

    /** Heroicon used in the tenant's category picker (spec 6.5.3). */
    public function icon(): string
    {
        return match ($this) {
            self::Plumbing => 'beaker',
            self::Electrical => 'bolt',
            self::Carpentry => 'wrench',
            self::Painting => 'paint-brush',
            self::Appliance => 'tv',
            self::Roofing => 'home',
            self::Security => 'lock-closed',
            self::PestControl => 'bug-ant',
            self::Cleaning => 'sparkles',
            self::Other => 'question-mark-circle',
        };
    }
}
