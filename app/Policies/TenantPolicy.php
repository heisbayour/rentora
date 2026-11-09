<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\Role;
use App\Models\Tenant;
use App\Models\User;

/** Caretakers may read tenant contact details (spec 1.2); owners and managers manage tenants. */
class TenantPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isStaff();
    }

    public function view(User $user, Tenant $tenant): bool
    {
        return $user->isStaff() && $tenant->organization_id === $user->organization_id;
    }

    /** Leases, documents, money and activity on the profile. */
    public function viewDetails(User $user, Tenant $tenant): bool
    {
        return $this->update($user, $tenant);
    }

    public function create(User $user): bool
    {
        return $user->hasRole(Role::Owner, Role::Manager);
    }

    public function update(User $user, Tenant $tenant): bool
    {
        return $this->create($user) && $tenant->organization_id === $user->organization_id;
    }

    public function delete(User $user, Tenant $tenant): bool
    {
        return $this->update($user, $tenant);
    }
}
