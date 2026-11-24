<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Inquiry;
use App\Models\Organization;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Inquiry> */
class InquiryFactory extends Factory
{
    public function definition(): array
    {
        return [
            'organization_id' => Organization::factory(),
            'name' => fake()->name(),
            'email' => fake()->safeEmail(),
            'phone' => '0803'.fake()->numerify('#######'),
            'message' => 'Is this flat still available? I would like to view it this week.',
            'status' => 'new',
        ];
    }
}
