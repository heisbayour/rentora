<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\MaintenanceRequest;
use App\Models\MaintenanceUpdate;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MaintenanceUpdate>
 */
class MaintenanceUpdateFactory extends Factory
{
    public function definition(): array
    {
        return [
            'maintenance_request_id' => MaintenanceRequest::factory(),
            'type' => MaintenanceUpdate::COMMENT,
            'body' => fake()->sentence(),
            'is_internal' => false,
        ];
    }
}
