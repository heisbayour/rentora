<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Unit;
use App\Models\UnitPhoto;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<UnitPhoto>
 */
class UnitPhotoFactory extends Factory
{
    public function definition(): array
    {
        return [
            'unit_id' => Unit::factory(),
            'path' => 'units/'.Str::uuid()->toString().'.jpg',
            'sort_order' => 0,
        ];
    }
}
