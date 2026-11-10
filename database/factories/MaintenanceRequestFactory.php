<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\MaintenanceCategory;
use App\Enums\MaintenancePriority;
use App\Enums\MaintenanceStatus;
use App\Models\MaintenanceRequest;
use App\Models\Unit;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MaintenanceRequest>
 */
class MaintenanceRequestFactory extends Factory
{
    public function definition(): array
    {
        return [
            'unit_id' => Unit::factory(),
            'organization_id' => fn (array $a) => Unit::query()->withoutGlobalScopes()->find($a['unit_id'])?->organization_id,
            'number' => 'MR-'.now()->format('Y').'-'.fake()->unique()->numerify('######'),
            'category' => MaintenanceCategory::Plumbing,
            'title' => 'Leaking kitchen tap',
            'description' => 'Water drips constantly from the kitchen tap.',
            'priority' => MaintenancePriority::Normal,
            'status' => MaintenanceStatus::Submitted,
        ];
    }

    public function status(MaintenanceStatus $status): static
    {
        return $this->state(fn (array $attributes) => ['status' => $status]);
    }
}
