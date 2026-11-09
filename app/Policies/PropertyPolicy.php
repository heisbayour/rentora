<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\Role;
use App\Models\Property;
use App\Models\User;

/** Properties are managed by owners and managers (spec 1.2). */
class PropertyPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasRole(Role::Owner, Role::Manager);
    }

    public function view(User $user, Property $property): bool
    {
        return $this->viewAny($user) && $property->organization_id === $user->organization_id;
    }

    public function create(User $user): bool
    {
        return $user->hasRole(Role::Owner, Role::Manager);
    }

    public function update(User $user, Property $property): bool
    {
        return $this->create($user) && $property->organization_id === $user->organization_id;
    }

    public function delete(User $user, Property $property): bool
    {
        return $this->update($user, $property);
    }
}
