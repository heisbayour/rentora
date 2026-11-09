<?php

declare(strict_types=1);

namespace App\Services\Billing;

use App\Enums\RentFrequency;
use App\Models\Lease;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;

/**
 * Billing periods (spec 8.5). Period k starts on A + k * months, where A is
 * the billing anchor day on or before the lease start. Anchor days are 1 to 28,
 * so every month has them and month ends and leap years never shift a period.
 * The first period starts on the lease start date; the last stops at the lease end.
 */
class PeriodCalculator
{
    /**
     * @return list<array{start: CarbonImmutable, end: CarbonImmutable}>
     */
    public function periods(CarbonInterface $leaseStart, CarbonInterface $leaseEnd, RentFrequency $frequency, int $anchorDay): array
    {
        $start = CarbonImmutable::parse($leaseStart->toDateString());
        $end = CarbonImmutable::parse($leaseEnd->toDateString());
        $anchor = $this->anchorOnOrBefore($start, $anchorDay);
        $months = $frequency->months();

        $periods = [];
        for ($k = 0; ; $k++) {
            $periodStart = $anchor->addMonthsNoOverflow($k * $months);
            if ($periodStart->greaterThan($end)) {
                break;
            }
            $nextStart = $anchor->addMonthsNoOverflow(($k + 1) * $months);
            $effectiveStart = $periodStart->lessThan($start) ? $start : $periodStart;
            $effectiveEnd = $nextStart->subDay()->greaterThan($end) ? $end : $nextStart->subDay();
            if ($effectiveStart->lessThanOrEqualTo($effectiveEnd)) {
                $periods[] = ['start' => $effectiveStart, 'end' => $effectiveEnd];
            }
        }

        return $periods;
    }

    /** @return list<array{start: CarbonImmutable, end: CarbonImmutable}> */
    public function forLease(Lease $lease): array
    {
        return $this->periods($lease->start_date, $lease->end_date, $lease->rent_frequency, $lease->billing_anchor_day);
    }

    public function anchorOnOrBefore(CarbonImmutable $date, int $anchorDay): CarbonImmutable
    {
        $anchorDay = max(1, min(28, $anchorDay));
        $candidate = $date->setDay($anchorDay);

        return $candidate->greaterThan($date) ? $candidate->subMonthNoOverflow() : $candidate;
    }
}
