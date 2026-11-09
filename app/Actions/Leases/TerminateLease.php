<?php

declare(strict_types=1);

namespace App\Actions\Leases;

use App\Enums\LeaseStatus;
use App\Models\Lease;
use App\Services\ActivityLogger;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Ends an active lease early with a reason (spec 8.4). Any prepared renewal is
 * discarded. The optional final invoice is issued by the billing module (Phase 5).
 */
class TerminateLease
{
    public function __construct(
        private readonly ReleaseLeaseOccupancy $release,
        private readonly ActivityLogger $logger,
    ) {}

    public function handle(Lease $lease, string $reason): Lease
    {
        return DB::transaction(function () use ($lease, $reason): Lease {
            $lease = Lease::query()->lockForUpdate()->findOrFail($lease->getKey());

            if ($lease->status !== LeaseStatus::Active) {
                throw ValidationException::withMessages(['lease' => __('leases.errors.not_active')]);
            }

            $lease->forceFill([
                'status' => LeaseStatus::Terminated,
                'terminated_at' => now(),
                'termination_reason' => $reason,
            ])->save();

            $lease->renewal()->where('status', LeaseStatus::Draft->value)->delete();
            $this->release->handle($lease);
            $this->logger->log('lease.terminated', $lease, ['reason' => $reason]);

            return $lease;
        });
    }
}
