<?php

declare(strict_types=1);

namespace App\Enums;

use App\Enums\Concerns\HasOptions;
use App\Enums\Contracts\HasPresentation;

enum TenantStatus: string implements HasPresentation
{
    use HasOptions;

    case PendingInvite = 'pending_invite';
    case Active = 'active';
    case Former = 'former';

    public function label(): string
    {
        return __('enums.tenant_status.'.$this->value);
    }

    public function color(): string
    {
        return match ($this) {
            self::PendingInvite => 'warning',
            self::Active => 'success',
            self::Former => 'neutral',
        };
    }
}
