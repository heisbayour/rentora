<?php

declare(strict_types=1);

namespace App\Services\Reports;

use App\Enums\InvoiceStatus;
use App\Enums\LeaseStatus;
use App\Enums\MaintenancePriority;
use App\Enums\MaintenanceStatus;
use App\Enums\PaymentStatus;
use App\Enums\UnitStatus;
use App\Models\Invoice;
use App\Models\Lease;
use App\Models\MaintenanceRequest;
use App\Models\Payment;
use App\Models\Unit;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;

/**
 * Landlord dashboard numbers (spec 8.2) for the current organization. Months
 * follow the organization timezone; amounts are minor units.
 */
class DashboardMetrics
{
    public function __construct(private readonly ArrearsAging $aging) {}

    /**
     * @return array{expected: int, collected: int, collection_rate: ?int, arrears: int, units: int, occupied: int, vacant: int, occupancy: ?int, open_repairs: int, emergencies: int, expiring: int}
     */
    public function kpis(?CarbonInterface $now = null): array
    {
        $now = self::localNow($now);
        [$monthStart, $monthEnd] = [$now->startOfMonth(), $now->endOfMonth()];

        $expected = $this->expectedBetween($monthStart, $monthEnd);
        $collected = $this->collectedBetween($monthStart, $monthEnd);

        $units = Unit::query()->count();
        $occupied = Unit::query()->where('status', UnitStatus::Occupied->value)->count();
        $vacant = Unit::query()->where('status', UnitStatus::Vacant->value)->count();

        $open = MaintenanceRequest::query()->whereIn('status', MaintenanceStatus::openValues());

        return [
            'expected' => $expected,
            'collected' => $collected,
            'collection_rate' => $expected > 0 ? (int) round($collected * 100 / $expected) : null,
            'arrears' => $this->aging->build($now)['total'],
            'units' => $units,
            'occupied' => $occupied,
            'vacant' => $vacant,
            'occupancy' => $units > 0 ? (int) round($occupied * 100 / $units) : null,
            'open_repairs' => (clone $open)->count(),
            'emergencies' => (clone $open)->where('priority', MaintenancePriority::Emergency->value)->count(),
            'expiring' => Lease::query()->where('status', LeaseStatus::Active->value)
                ->whereDate('end_date', '>=', $now->toDateString())
                ->whereDate('end_date', '<=', $now->addDays(60)->toDateString())
                ->count(),
        ];
    }

    /**
     * Expected (invoiced) and collected per month for the last 12 months, oldest first.
     *
     * @return array{labels: list<string>, expected: list<int>, collected: list<int>}
     */
    public function collectionsChart(?CarbonInterface $now = null): array
    {
        $now = self::localNow($now);
        $chart = ['labels' => [], 'expected' => [], 'collected' => []];
        for ($i = 11; $i >= 0; $i--) {
            $month = $now->startOfMonth()->subMonthsNoOverflow($i);
            $chart['labels'][] = $month->format('M Y');
            $chart['expected'][] = $this->expectedBetween($month, $month->endOfMonth());
            $chart['collected'][] = $this->collectedBetween($month, $month->endOfMonth());
        }

        return $chart;
    }

    /** Invoiced amounts (not void) due in the period. */
    public function expectedBetween(CarbonImmutable $from, CarbonImmutable $to): int
    {
        return (int) Invoice::query()
            ->where('status', '!=', InvoiceStatus::Void->value)
            ->whereDate('due_date', '>=', $from->toDateString())
            ->whereDate('due_date', '<=', $to->toDateString())
            ->sum('amount');
    }

    /** Successful payments received in the period (local days, compared in UTC). */
    public function collectedBetween(CarbonImmutable $from, CarbonImmutable $to): int
    {
        return (int) Payment::query()
            ->where('status', PaymentStatus::Successful->value)
            ->whereBetween('paid_at', [$from->startOfDay()->utc(), $to->endOfDay()->utc()])
            ->sum('amount');
    }

    public static function localNow(?CarbonInterface $now = null): CarbonImmutable
    {
        $timezone = (string) config('rentora.defaults.timezone', 'Africa/Lagos');

        return CarbonImmutable::parse($now ?? now())->setTimezone($timezone);
    }
}
