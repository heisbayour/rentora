<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\DocumentVisibility;
use App\Enums\Role;
use App\Models\Document;
use App\Models\Lease;
use App\Models\Tenant;
use App\Models\User;

/**
 * Owners and managers see every document in their organization. A tenant sees
 * only documents marked "visible to tenant" on their own tenant record or
 * their own leases (spec 8.4, 8.13 and 11).
 */
class DocumentPolicy
{
    public function view(User $user, Document $document): bool
    {
        if ($document->organization_id !== $user->organization_id) {
            return false;
        }
        if ($user->hasRole(Role::Owner, Role::Manager)) {
            return true;
        }
        if ($user->role !== Role::Tenant || $document->visibility !== DocumentVisibility::Tenant) {
            return false;
        }

        $owner = $document->documentable;

        return match (true) {
            $owner instanceof Tenant => $owner->user_id === $user->id,
            $owner instanceof Lease => $owner->tenant->user_id === $user->id,
            default => false,
        };
    }

    public function create(User $user): bool
    {
        return $user->hasRole(Role::Owner, Role::Manager);
    }

    public function delete(User $user, Document $document): bool
    {
        return $this->create($user) && $document->organization_id === $user->organization_id;
    }
}
