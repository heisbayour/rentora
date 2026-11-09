<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\RentFrequency;
use App\Enums\UnitStatus;
use App\Enums\UnitType;
use App\Models\Property;
use App\Models\Unit;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Unit>
 */
class UnitFactory extends Factory
{
    public function definition(): array
    {
        return [
            // property_id first: the organization_id closure reads the created property.
            'property_id' => Property::factory(),
            'organization_id' => fn (array $attributes) => Property::query()->withoutGlobalScopes()->find($attributes['property_id'])?->organization_id,
            'label' => 'Flat '.fake()->unique()->bothify('#?'),
            'type' => UnitType::Flat,
            'bedrooms' => 2,
            'bathrooms' => 2,
            'rent_amount' => 120_000_000,
            'rent_frequency' => RentFrequency::Annual,
            'service_charge_amount' => 0,
            'caution_deposit_amount' => 0,
            'status' => UnitStatus::Vacant,
            'is_listed' => false,
        ];
    }

    public function occupied(): static
    {
        return $this->state(fn (array $attributes) => ['status' => UnitStatus::Occupied]);
    }

    public function listed(): static
    {
        return $this->state(fn (array $attributes) => ['is_listed' => true]);
    }
}
