<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\Role;
use App\Models\Payment;
use App\Models\User;

/** Owners and managers handle payments; only owners reverse; tenants see their own (spec 1.2, 8.6, 11). */
class PaymentPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasRole(Role::Owner, Role::Manager);
    }

    public function view(User $user, Payment $payment): bool
    {
        if ($payment->organization_id !== $user->organization_id) {
            return false;
        }

        return $this->viewAny($user)
            || ($user->role === Role::Tenant && $payment->tenant->user_id === $user->id);
    }

    public function create(User $user): bool
    {
        return $user->hasRole(Role::Owner, Role::Manager);
    }

    public function approve(User $user, Payment $payment): bool
    {
        return $this->create($user) && $payment->organization_id === $user->organization_id;
    }

    public function reverse(User $user, Payment $payment): bool
    {
        return $user->hasRole(Role::Owner) && $payment->organization_id === $user->organization_id;
    }
}
