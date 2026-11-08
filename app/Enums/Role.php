<?php

declare(strict_types=1);

namespace App\Enums;

use App\Enums\Contracts\HasPresentation;

enum Role: string implements HasPresentation
{
    case Owner = 'owner';
    case Manager = 'manager';
    case Caretaker = 'caretaker';
    case Tenant = 'tenant';

    public function label(): string
    {
        return __('enums.role.'.$this->value);
    }

    public function color(): string
    {
        return match ($this) {
            self::Owner => 'info',
            self::Manager => 'success',
            self::Caretaker => 'warning',
            self::Tenant => 'neutral',
        };
    }

    public function isStaff(): bool
    {
        return $this !== self::Tenant;
    }

    /** @return list<self> */
    public static function staff(): array
    {
        return [self::Owner, self::Manager, self::Caretaker];
    }
}
