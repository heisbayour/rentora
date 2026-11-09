<?php

declare(strict_types=1);

namespace App\Enums;

use App\Enums\Concerns\HasOptions;
use App\Enums\Contracts\HasPresentation;

enum DepositStatus: string implements HasPresentation
{
    use HasOptions;

    case Unpaid = 'unpaid';
    case Held = 'held';
    case Refunded = 'refunded';
    case Forfeited = 'forfeited';

    public function label(): string
    {
        return __('enums.deposit_status.'.$this->value);
    }

    public function color(): string
    {
        return match ($this) {
            self::Unpaid => 'warning',
            self::Held => 'info',
            self::Refunded => 'success',
            self::Forfeited => 'danger',
        };
    }
}
