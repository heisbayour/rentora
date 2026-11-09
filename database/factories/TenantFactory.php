<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\TenantStatus;
use App\Models\Organization;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Tenant>
 */
class TenantFactory extends Factory
{
    public function definition(): array
    {
        return [
            'organization_id' => Organization::factory(),
            'full_name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'phone' => '080'.fake()->unique()->numerify('########'),
            'occupation' => fake()->jobTitle(),
            'status' => TenantStatus::PendingInvite,
            'notification_prefs' => Tenant::DEFAULT_PREFS,
            'credit_balance' => 0,
        ];
    }

    public function withoutEmail(): static
    {
        return $this->state(fn (array $attributes) => ['email' => null]);
    }
}
