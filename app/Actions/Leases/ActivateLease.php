<?php

declare(strict_types=1);

namespace App\Actions\Leases;

use App\Enums\LeaseStatus;
use App\Enums\TenantStatus;
use App\Enums\UnitStatus;
use App\Models\Lease;
use App\Models\Tenant;
use App\Models\Unit;
use App\Services\ActivityLogger;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Activates a draft lease. At most one active lease per unit (spec 5.3): the
 * unit row is locked so two activations cannot both pass the check. The unit
 * becomes occupied and is taken off the public website.
 */
class ActivateLease
{
    public function __construct(private readonly ActivityLogger $logger) {}

    public function handle(Lease $lease): Lease
    {
        return DB::transaction(function () use ($lease): Lease {
            /** @var Unit $unit */
            $unit = Unit::query()->withTrashed()->lockForUpdate()->findOrFail($lease->unit_id);
            $lease = Lease::query()->lockForUpdate()->findOrFail($lease->getKey());

            if ($lease->status !== LeaseStatus::Draft) {
                throw ValidationException::withMessages(['lease' => __('leases.errors.not_draft')]);
            }
            if ($unit->trashed()) {
                throw ValidationException::withMessages(['lease' => __('leases.errors.unit_deleted')]);
            }
            if ($unit->leases()->where('status', LeaseStatus::Active->value)->exists()) {
                throw ValidationException::withMessages(['lease' => __('leases.errors.unit_has_active_lease', ['unit' => $unit->label])]);
            }

            $lease->forceFill(['status' => LeaseStatus::Active])->save();
            $unit->forceFill(['status' => UnitStatus::Occupied, 'is_listed' => false])->save();

            /** @var Tenant $tenant */
            $tenant = Tenant::query()->withTrashed()->lockForUpdate()->findOrFail($lease->tenant_id);
            $tenant->forceFill(['status' => $tenant->user_id !== null ? TenantStatus::Active : TenantStatus::PendingInvite])->save();

            $this->logger->log('lease.activated', $lease, ['unit' => $unit->label]);

            return $lease;
        });
    }
}
