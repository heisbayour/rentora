<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\Role;
use App\Models\Invoice;
use App\Models\User;

/** Money is owner/manager only; a tenant may see their own invoices (spec 1.2 and 11). */
class InvoicePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasRole(Role::Owner, Role::Manager);
    }

    public function view(User $user, Invoice $invoice): bool
    {
        if ($invoice->organization_id !== $user->organization_id) {
            return false;
        }

        return $this->viewAny($user)
            || ($user->role === Role::Tenant && $invoice->tenant->user_id === $user->id);
    }

    public function create(User $user): bool
    {
        return $user->hasRole(Role::Owner, Role::Manager);
    }

    public function void(User $user, Invoice $invoice): bool
    {
        return $this->create($user) && $invoice->organization_id === $user->organization_id;
    }
}
