<?php

declare(strict_types=1);

namespace App\Services\Messaging;

use App\Enums\AnnouncementAudience;
use App\Enums\LeaseStatus;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Builder;

/**
 * Who receives an announcement (spec 8.8): every tenant with an active lease,
 * tenants with an active lease in chosen properties, or chosen tenants.
 * Always inside the current organization (OrganizationScope).
 */
class AnnouncementAudienceResolver
{
    /**
     * @param  list<int>  $ids
     * @return Builder<Tenant>
     */
    public function query(AnnouncementAudience $audience, array $ids = []): Builder
    {
        $activeLease = fn (Builder $lease) => $lease->where('status', LeaseStatus::Active->value);

        return match ($audience) {
            AnnouncementAudience::AllTenants => Tenant::query()->whereHas('leases', $activeLease),
            AnnouncementAudience::Property => Tenant::query()->whereHas('leases', fn (Builder $lease) => $activeLease($lease)
                ->whereHas('unit', fn (Builder $unit) => $unit->whereIn('property_id', $ids))),
            AnnouncementAudience::Tenants => Tenant::query()->whereIn('id', $ids),
        };
    }

    /** @param  list<int>  $ids */
    public function count(AnnouncementAudience $audience, array $ids = []): int
    {
        return $this->query($audience, $ids)->count();
    }
}
