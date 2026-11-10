<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Organization;
use App\Models\Vendor;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Vendor>
 */
class VendorFactory extends Factory
{
    public function definition(): array
    {
        return [
            'organization_id' => Organization::factory(),
            'name' => fake()->name(),
            'trade' => fake()->randomElement(['Plumber', 'Electrician', 'Carpenter', 'Painter']),
            'phone' => '080'.fake()->numerify('########'),
            'email' => fake()->safeEmail(),
            'is_active' => true,
        ];
    }
}
