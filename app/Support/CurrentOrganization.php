<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\Organization;

/**
 * The organization the current request (or scheduler task) works in.
 * Read by OrganizationScope and BelongsToOrganization. Bound as a singleton.
 */
final class CurrentOrganization
{
    private ?Organization $organization = null;

    public function set(?Organization $organization): void
    {
        $this->organization = $organization;
    }

    public function get(): ?Organization
    {
        return $this->organization;
    }

    public function id(): ?int
    {
        return $this->organization?->getKey();
    }

    /**
     * Runs a callback inside another organization, restoring the previous one after.
     *
     * @template T
     *
     * @param  callable(): T  $callback
     * @return T
     */
    public function runAs(Organization $organization, callable $callback): mixed
    {
        $previous = $this->organization;
        $this->organization = $organization;
        try {
            return $callback();
        } finally {
            $this->organization = $previous;
        }
    }
}
