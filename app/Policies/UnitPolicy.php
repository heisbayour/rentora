<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\Role;
use App\Models\Unit;
use App\Models\User;

/** Caretakers may read units (spec 1.2); owners and managers manage them. */
class UnitPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isStaff();
    }

    public function view(User $user, Unit $unit): bool
    {
        return $user->isStaff() && $unit->organization_id === $user->organization_id;
    }

    public function create(User $user): bool
    {
        return $user->hasRole(Role::Owner, Role::Manager);
    }

    public function update(User $user, Unit $unit): bool
    {
        return $this->create($user) && $unit->organization_id === $user->organization_id;
    }

    public function delete(User $user, Unit $unit): bool
    {
        return $this->update($user, $unit);
    }
}
