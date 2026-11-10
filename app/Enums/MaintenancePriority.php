<?php

declare(strict_types=1);

namespace App\Enums;

use App\Enums\Concerns\HasOptions;
use App\Enums\Contracts\HasPresentation;

enum MaintenancePriority: string implements HasPresentation
{
    use HasOptions;

    case Low = 'low';
    case Normal = 'normal';
    case High = 'high';
    case Emergency = 'emergency';

    public function label(): string
    {
        return __('enums.maintenance_priority.'.$this->value);
    }

    public function color(): string
    {
        return match ($this) {
            self::Low => 'neutral',
            self::Normal => 'info',
            self::High => 'warning',
            self::Emergency => 'danger',
        };
    }
}
