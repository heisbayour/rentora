<?php

declare(strict_types=1);

namespace App\Actions\Leases;

use App\Enums\LeaseStatus;
use App\Models\Lease;
use App\Services\ActivityLogger;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Ends a lease on (or after) its end date (spec 8.4). If a renewal was
 * prepared, it is activated straight away so the unit stays occupied.
 * Also used by the EndExpiredLeases scheduler task.
 */
class EndLease
{
    public function __construct(
        private readonly ReleaseLeaseOccupancy $release,
        private readonly ActivateLease $activate,
        private readonly ActivityLogger $logger,
    ) {}

    public function handle(Lease $lease): ?Lease
    {
        $renewal = DB::transaction(function () use ($lease): ?Lease {
            $lease = Lease::query()->lockForUpdate()->findOrFail($lease->getKey());

            if ($lease->status !== LeaseStatus::Active) {
                throw ValidationException::withMessages(['lease' => __('leases.errors.not_active')]);
            }
            if ($lease->end_date->isAfter(today())) {
                throw ValidationException::withMessages(['lease' => __('leases.errors.not_yet_ended', ['date' => $lease->end_date->format('j M Y')])]);
            }

            $lease->forceFill(['status' => LeaseStatus::Ended])->save();
            $this->release->handle($lease);
            $this->logger->log('lease.ended', $lease);

            return Lease::query()
                ->where('renewal_of_id', $lease->getKey())
                ->where('status', LeaseStatus::Draft->value)
                ->first();
        });

        return $renewal !== null ? $this->activate->handle($renewal) : null;
    }
}
