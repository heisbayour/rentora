<?php

declare(strict_types=1);

namespace App\Actions\Leases;

use App\Enums\DepositStatus;
use App\Enums\LateFeeType;
use App\Enums\LeaseStatus;
use App\Enums\RentFrequency;
use App\Models\Lease;
use App\Models\Tenant;
use App\Models\Unit;
use App\Services\ActivityLogger;
use Illuminate\Support\Carbon;

/** Creates a draft lease, filling the spec 5.3 defaults for anything not given. */
class CreateLease
{
    public const DEFAULT_GRACE_DAYS = 5;

    public function __construct(private readonly ActivityLogger $logger) {}

    /**
     * @param  array<string, mixed>  $terms
     */
    public function handle(Tenant $tenant, Unit $unit, array $terms): Lease
    {
        $start = Carbon::parse($terms['start_date'])->startOfDay();
        $frequency = $terms['rent_frequency'] instanceof RentFrequency
            ? $terms['rent_frequency']
            : RentFrequency::from((string) $terms['rent_frequency']);

        $lease = Lease::query()->create([
            'unit_id' => $unit->getKey(),
            'tenant_id' => $tenant->getKey(),
            'renewal_of_id' => $terms['renewal_of_id'] ?? null,
            'start_date' => $start,
            'end_date' => Carbon::parse($terms['end_date'])->startOfDay(),
            'rent_amount' => (int) $terms['rent_amount'],
            'rent_frequency' => $frequency,
            'service_charge_amount' => (int) ($terms['service_charge_amount'] ?? 0),
            'deposit_amount' => (int) ($terms['deposit_amount'] ?? 0),
            'deposit_status' => $terms['deposit_status'] ?? DepositStatus::Unpaid,
            'billing_anchor_day' => (int) ($terms['billing_anchor_day'] ?? self::anchorDayFor($start)),
            'invoice_lead_days' => (int) ($terms['invoice_lead_days'] ?? $frequency->defaultLeadDays()),
            'grace_days' => (int) ($terms['grace_days'] ?? self::DEFAULT_GRACE_DAYS),
            'late_fee_type' => $terms['late_fee_type'] ?? LateFeeType::None,
            'late_fee_value' => (int) ($terms['late_fee_value'] ?? 0),
            'status' => LeaseStatus::Draft,
        ]);

        $this->logger->log('lease.created', $lease, ['unit' => $unit->label, 'tenant' => $tenant->full_name]);

        return $lease;
    }

    /** The billing anchor is the start day, capped at 28 so every month has it (spec 5.3). */
    public static function anchorDayFor(Carbon $start): int
    {
        return min(28, (int) $start->day);
    }
}
