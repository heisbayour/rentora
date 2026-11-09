<?php

declare(strict_types=1);

namespace App\Services\Billing;

use App\Enums\InvoiceType;
use App\Enums\LeaseStatus;
use App\Models\Invoice;
use App\Models\Lease;
use App\Models\Organization;
use App\Support\CurrentOrganization;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

/**
 * Daily rent generation (spec 8.5): for every active lease, issues the rent
 * invoice (and a service charge invoice when it is above zero) for each period
 * whose start minus invoice_lead_days has been reached. Idempotent: the
 * (lease_id, type, period_start) unique key and an existence check under a
 * lease row lock mean running it twice creates nothing new.
 */
class InvoiceGenerator
{
    public function __construct(
        private readonly PeriodCalculator $periods,
        private readonly InvoiceIssuer $issuer,
        private readonly CurrentOrganization $current,
    ) {}

    /** Runs for every organization. Returns the number of invoices created. */
    public function run(?CarbonInterface $today = null): int
    {
        $created = 0;
        foreach (Organization::query()->get() as $organization) {
            $created += $this->current->runAs($organization, function () use ($today): int {
                $count = 0;
                Lease::query()->active()->with('tenant')->chunkById(100, function ($leases) use (&$count, $today): void {
                    foreach ($leases as $lease) {
                        $count += $this->forLease($lease, $today);
                    }
                });

                return $count;
            });
        }

        return $created;
    }

    /** Issues every due, not yet invoiced period of one lease. */
    public function forLease(Lease $lease, ?CarbonInterface $today = null, bool $ignoreLeadTime = false, ?int $limit = null): int
    {
        $today = CarbonImmutable::parse(($today ?? today())->toDateString());

        return DB::transaction(function () use ($lease, $today, $ignoreLeadTime, $limit): int {
            $lease = Lease::query()->withoutGlobalScopes()->with('tenant')->lockForUpdate()->findOrFail($lease->getKey());
            if ($lease->status !== LeaseStatus::Active) {
                return 0;
            }

            $created = 0;
            foreach ($this->periods->forLease($lease) as $index => $period) {
                if ($limit !== null && $index >= $limit) {
                    break;
                }
                if (! $ignoreLeadTime && $today->lessThan($period['start']->subDays($lease->invoice_lead_days))) {
                    break;
                }
                $created += $this->issueOnce($lease, InvoiceType::Rent, $lease->rent_amount, $period);
                if ($lease->service_charge_amount > 0) {
                    $created += $this->issueOnce($lease, InvoiceType::ServiceCharge, $lease->service_charge_amount, $period);
                }
            }

            return $created;
        });
    }

    /** @param  array{start: CarbonImmutable, end: CarbonImmutable}  $period */
    private function issueOnce(Lease $lease, InvoiceType $type, int $amount, array $period): int
    {
        $exists = Invoice::query()->withoutGlobalScopes()
            ->where('lease_id', $lease->getKey())
            ->where('type', $type->value)
            ->whereDate('period_start', $period['start']->toDateString())
            ->exists();
        if ($exists || $amount <= 0) {
            return 0;
        }

        try {
            $this->issuer->issue($lease->tenant, $type, $amount, $period['start'], [
                'lease' => $lease,
                'period_start' => $period['start'],
                'period_end' => $period['end'],
                'description' => __('invoices.descriptions.'.$type->value, [
                    'from' => $period['start']->format('j M Y'),
                    'to' => $period['end']->format('j M Y'),
                ]),
            ]);
        } catch (QueryException) {
            // The unique key rejected a duplicate written concurrently: nothing to do.
            return 0;
        }

        return 1;
    }
}
