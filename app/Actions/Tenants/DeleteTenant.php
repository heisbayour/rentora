<?php

declare(strict_types=1);

namespace App\Actions\Tenants;

use App\Enums\LeaseStatus;
use App\Models\Tenant;
use App\Services\ActivityLogger;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/** Soft deletes a tenant with no active lease and deactivates their portal login. History is kept. */
class DeleteTenant
{
    public function __construct(private readonly ActivityLogger $logger) {}

    public function handle(Tenant $tenant): void
    {
        DB::transaction(function () use ($tenant): void {
            if ($tenant->leases()->where('status', LeaseStatus::Active->value)->exists()) {
                throw ValidationException::withMessages(['tenant' => __('tenants.errors.has_active_lease')]);
            }
            $tenant->user?->forceFill(['is_active' => false])->save();
            $tenant->delete();
        });

        $this->logger->log('tenant.deleted', $tenant, ['name' => $tenant->full_name]);
    }
}
