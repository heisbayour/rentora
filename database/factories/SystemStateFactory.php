<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\SystemState;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SystemState>
 */
class SystemStateFactory extends Factory
{
    public function definition(): array
    {
        return [
            'key' => 'sample.'.fake()->unique()->word(),
            'value' => null,
        ];
    }
}
