<?php

declare(strict_types=1);

namespace App\Enums;

use App\Enums\Concerns\HasOptions;
use App\Enums\Contracts\HasPresentation;

enum LeaseStatus: string implements HasPresentation
{
    use HasOptions;

    case Draft = 'draft';
    case Active = 'active';
    case Ended = 'ended';
    case Terminated = 'terminated';

    public function label(): string
    {
        return __('enums.lease_status.'.$this->value);
    }

    public function color(): string
    {
        return match ($this) {
            self::Draft => 'info',
            self::Active => 'success',
            self::Ended => 'neutral',
            self::Terminated => 'danger',
        };
    }
}
