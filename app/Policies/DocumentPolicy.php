<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\Role;
use App\Models\Document;
use App\Models\User;

/**
 * Owners and managers see every document. Tenants see only documents marked
 * "visible to tenant" that belong to them; that rule is added with tenants in Phase 4.
 */
class DocumentPolicy
{
    public function view(User $user, Document $document): bool
    {
        if ($document->organization_id !== $user->organization_id) {
            return false;
        }

        return $user->hasRole(Role::Owner, Role::Manager);
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
