<?php

declare(strict_types=1);

namespace App\Actions\Leases;

use App\Enums\LeaseStatus;
use App\Enums\TenantStatus;
use App\Enums\UnitStatus;
use App\Models\Lease;
use App\Models\Tenant;
use App\Models\Unit;

/**
 * After a lease ends or is terminated: the unit becomes vacant and the tenant
 * former, unless another active lease still covers them (spec 8.3 and 8.4).
 * Call inside the caller's transaction.
 */
class ReleaseLeaseOccupancy
{
    public function handle(Lease $lease): void
    {
        $unit = Unit::query()->withTrashed()->lockForUpdate()->find($lease->unit_id);
        if ($unit !== null && ! $unit->leases()->where('status', LeaseStatus::Active->value)->exists()) {
            $unit->forceFill(['status' => UnitStatus::Vacant])->save();
        }

        $tenant = Tenant::query()->withTrashed()->lockForUpdate()->find($lease->tenant_id);
        if ($tenant !== null && ! $tenant->leases()->where('status', LeaseStatus::Active->value)->exists()) {
            $tenant->forceFill(['status' => TenantStatus::Former])->save();
        }
    }
}
