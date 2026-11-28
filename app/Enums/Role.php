<?php

declare(strict_types=1);

namespace App\Enums;

use App\Enums\Contracts\HasPresentation;

enum Role: string implements HasPresentation
{
    case Developer = 'developer';
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
            self::Developer => 'neutral',
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

    /** Developers (support) hold every owner permission plus the system pages. */
    public function actsAsOwner(): bool
    {
        return $this === self::Owner || $this === self::Developer;
    }

    /** @return list<self> every console role, the developer included */
    public static function staff(): array
    {
        return [self::Developer, self::Owner, self::Manager, self::Caretaker];
    }

    /**
     * Roles the actor may give to someone: owners give owner, manager and
     * caretaker; only a developer gives the developer role.
     *
     * @return list<self>
     */
    public static function assignableBy(?self $actor): array
    {
        return $actor === self::Developer ? self::staff() : [self::Owner, self::Manager, self::Caretaker];
    }
}
