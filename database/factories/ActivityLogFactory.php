<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\ActivityLog;
use App\Models\Organization;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ActivityLog>
 */
class ActivityLogFactory extends Factory
{
    public function definition(): array
    {
        return [
            'organization_id' => Organization::factory(),
            'user_id' => null,
            'action' => 'auth.login',
            'properties' => [],
            'ip' => '127.0.0.1',
            'user_agent' => 'Pest',
        ];
    }
}
