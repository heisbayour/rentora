<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\MaintenanceStatus;
use App\Enums\Role;
use App\Models\MaintenanceRequest;
use App\Models\User;

/** All staff (caretakers included) work on repairs; tenants see and act on their own only (spec 8.7). */
class MaintenanceRequestPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isStaff();
    }

    public function view(User $user, MaintenanceRequest $request): bool
    {
        if ($request->organization_id !== $user->organization_id) {
            return false;
        }

        return $user->isStaff() || $this->ownsAsTenant($user, $request);
    }

    public function create(User $user): bool
    {
        return $user->isStaff() || ($user->role === Role::Tenant && $user->tenant !== null);
    }

    /** Status changes, assignment, internal notes. */
    public function manage(User $user, MaintenanceRequest $request): bool
    {
        return $user->isStaff() && $request->organization_id === $user->organization_id;
    }

    public function comment(User $user, MaintenanceRequest $request): bool
    {
        return $this->view($user, $request) && $request->status !== MaintenanceStatus::Cancelled;
    }

    /** A tenant may cancel only while the request is still submitted. */
    public function cancel(User $user, MaintenanceRequest $request): bool
    {
        return $this->ownsAsTenant($user, $request) && $request->status === MaintenanceStatus::Submitted;
    }

    /** A tenant confirms a resolved request and rates it. */
    public function rate(User $user, MaintenanceRequest $request): bool
    {
        return $this->ownsAsTenant($user, $request) && $request->status === MaintenanceStatus::Resolved;
    }

    private function ownsAsTenant(User $user, MaintenanceRequest $request): bool
    {
        return $user->role === Role::Tenant
            && $request->organization_id === $user->organization_id
            && $request->tenant_id !== null
            && $user->tenant?->id === $request->tenant_id;
    }
}
