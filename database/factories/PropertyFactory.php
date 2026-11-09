<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\PropertyType;
use App\Models\Organization;
use App\Models\Property;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Property>
 */
class PropertyFactory extends Factory
{
    public function definition(): array
    {
        return [
            'organization_id' => Organization::factory(),
            'name' => fake()->unique()->lastName().' '.fake()->randomElement(['Gardens', 'Court', 'Estate', 'Towers', 'Plaza']),
            'code' => strtoupper(fake()->unique()->bothify('???##')),
            'type' => PropertyType::Residential,
            'address' => fake()->streetAddress(),
            'city' => fake()->randomElement(['Lagos', 'Abuja', 'Minna']),
            'state' => fake()->randomElement(['Lagos', 'FCT', 'Niger']),
            'country' => 'Nigeria',
            'is_listed' => false,
        ];
    }
}
