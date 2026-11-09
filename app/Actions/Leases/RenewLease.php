<?php

declare(strict_types=1);

namespace App\Actions\Leases;

use App\Enums\LeaseStatus;
use App\Models\Lease;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Renewal (spec 8.4): a new draft lease starting the day after the current
 * one ends, with editable rent. It is activated when the current lease ends.
 */
class RenewLease
{
    public function __construct(private readonly CreateLease $create) {}

    /**
     * @param  array{end_date?: string, rent_amount?: int, service_charge_amount?: int}  $changes
     */
    public function handle(Lease $lease, array $changes = []): Lease
    {
        return DB::transaction(function () use ($lease, $changes): Lease {
            $lease = Lease::query()->with(['tenant', 'unit'])->lockForUpdate()->findOrFail($lease->getKey());

            if ($lease->status !== LeaseStatus::Active) {
                throw ValidationException::withMessages(['lease' => __('leases.errors.renew_needs_active')]);
            }
            if ($lease->renewal()->exists()) {
                throw ValidationException::withMessages(['lease' => __('leases.errors.already_renewed')]);
            }

            $start = $lease->end_date->copy()->addDay();
            $end = isset($changes['end_date'])
                ? Carbon::parse($changes['end_date'])
                : self::defaultEndDate($lease, $start);

            if ($end->lessThanOrEqualTo($start)) {
                throw ValidationException::withMessages(['end_date' => __('leases.errors.end_after_start')]);
            }

            return $this->create->handle($lease->tenant, $lease->unit, [
                'renewal_of_id' => $lease->getKey(),
                'start_date' => $start,
                'end_date' => $end,
                'rent_amount' => $changes['rent_amount'] ?? $lease->rent_amount,
                'rent_frequency' => $lease->rent_frequency,
                'service_charge_amount' => $changes['service_charge_amount'] ?? $lease->service_charge_amount,
                'deposit_amount' => 0,
                'deposit_status' => $lease->deposit_status,
                'billing_anchor_day' => $lease->billing_anchor_day,
                'invoice_lead_days' => $lease->invoice_lead_days,
                'grace_days' => $lease->grace_days,
                'late_fee_type' => $lease->late_fee_type,
                'late_fee_value' => $lease->late_fee_value,
            ]);
        });
    }

    /** Same length as the current lease, measured in whole months. */
    public static function defaultEndDate(Lease $lease, Carbon $start): Carbon
    {
        // Carbon 3: diffInMonths() returns a float.
        $months = max(1, (int) round(abs($lease->start_date->diffInMonths($lease->end_date->copy()->addDay()))));

        return $start->copy()->addMonthsNoOverflow($months)->subDay();
    }
}
