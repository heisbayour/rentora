<?php

declare(strict_types=1);

namespace App\Services\Messaging;

use App\Models\Lease;
use App\Models\Organization;
use App\Models\ReminderLog;
use App\Notifications\LeaseExpiring;
use App\Support\CurrentOrganization;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;

/**
 * Daily lease expiry alerts at 60 and 30 days (spec 8.4, 10.1) for the tenant
 * and owners/managers, at most once per lease and window (reminder_logs).
 * Leases that already have a renewal lined up are skipped.
 */
class LeaseExpiryAlerts
{
    public function __construct(
        private readonly CurrentOrganization $current,
        private readonly Notifier $notifier,
    ) {}

    public function run(?CarbonInterface $today = null): int
    {
        $today = CarbonImmutable::parse(($today ?? today())->toDateString());
        $sent = 0;

        foreach (Organization::query()->get() as $organization) {
            $sent += $this->current->runAs($organization, function () use ($today): int {
                $count = 0;
                $leases = Lease::query()->active()
                    ->whereDate('end_date', '>=', $today->toDateString())
                    ->whereDate('end_date', '<=', $today->addDays(60)->toDateString())
                    ->whereDoesntHave('renewal')
                    ->with(['tenant', 'unit.property'])
                    ->get();

                foreach ($leases as $lease) {
                    $days = (int) $today->diffInDays(CarbonImmutable::parse($lease->end_date->toDateString()), false);
                    $kind = $days <= 30 ? 'expiring_30' : 'expiring_60';
                    if (! ReminderLog::claim($lease, $kind)) {
                        continue;
                    }
                    $notification = new LeaseExpiring($lease, $days, $lease->tenant->full_name, $lease->unit->displayName());
                    $this->notifier->tenant($lease->tenant_id, $notification);
                    $this->notifier->toStaff($lease->organization_id, $notification);
                    $count++;
                }

                return $count;
            });
        }

        return $sent;
    }
}
