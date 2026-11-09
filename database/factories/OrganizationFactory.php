<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Organization;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Organization>
 */
class OrganizationFactory extends Factory
{
    public function definition(): array
    {
        $name = fake()->unique()->company();

        return [
            'name' => $name,
            'slug' => Str::slug($name).'-'.Str::lower(Str::random(4)),
            'primary_color' => '#1A73E8',
            'currency' => 'NGN',
            'timezone' => 'Africa/Lagos',
            'email' => fake()->companyEmail(),
            'phone' => '0803'.fake()->numerify('#######'),
            'address' => fake()->address(),
        ];
    }
}
