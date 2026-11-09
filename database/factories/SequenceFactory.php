<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Organization;
use App\Models\Sequence;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Sequence>
 */
class SequenceFactory extends Factory
{
    public function definition(): array
    {
        return [
            'organization_id' => Organization::factory(),
            'key' => 'invoice',
            'year' => (int) now()->format('Y'),
            'next_value' => 1,
        ];
    }
}
