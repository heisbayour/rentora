<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\DepositStatus;
use App\Enums\LateFeeType;
use App\Enums\LeaseStatus;
use App\Enums\RentFrequency;
use App\Models\Lease;
use App\Models\Tenant;
use App\Models\Unit;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Lease>
 */
class LeaseFactory extends Factory
{
    public function definition(): array
    {
        $start = today()->startOfMonth();

        return [
            // unit_id first: the other closures read the created unit.
            'unit_id' => Unit::factory(),
            'organization_id' => fn (array $a) => Unit::query()->withoutGlobalScopes()->find($a['unit_id'])?->organization_id,
            'tenant_id' => fn (array $a) => Tenant::factory()->create(['organization_id' => $a['organization_id']])->id,
            'start_date' => $start,
            'end_date' => $start->copy()->addYear()->subDay(),
            'rent_amount' => 120_000_000,
            'rent_frequency' => RentFrequency::Annual,
            'service_charge_amount' => 0,
            'deposit_amount' => 0,
            'deposit_status' => DepositStatus::Unpaid,
            'billing_anchor_day' => 1,
            'invoice_lead_days' => 30,
            'grace_days' => 5,
            'late_fee_type' => LateFeeType::None,
            'late_fee_value' => 0,
            'status' => LeaseStatus::Draft,
        ];
    }

    public function active(): static
    {
        return $this->state(fn (array $attributes) => ['status' => LeaseStatus::Active]);
    }
}
