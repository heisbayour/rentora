<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\Role;
use App\Models\Lease;
use App\Models\User;

class LeasePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasRole(Role::Owner, Role::Manager);
    }

    public function view(User $user, Lease $lease): bool
    {
        return $lease->organization_id === $user->organization_id
            && ($this->viewAny($user) || ($user->role === Role::Tenant && $lease->tenant->user_id === $user->id));
    }

    public function create(User $user): bool
    {
        return $user->hasRole(Role::Owner, Role::Manager);
    }

    public function update(User $user, Lease $lease): bool
    {
        return $this->create($user) && $lease->organization_id === $user->organization_id;
    }
}
