<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\Role;
use App\Models\User;
use App\Models\Vendor;

/** Every staff role may see vendors (caretakers assign them); owners and managers edit them. */
class VendorPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isStaff();
    }

    public function create(User $user): bool
    {
        return $user->hasRole(Role::Owner, Role::Manager);
    }

    public function update(User $user, Vendor $vendor): bool
    {
        return $this->create($user) && $vendor->organization_id === $user->organization_id;
    }
}
