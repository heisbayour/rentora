<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\MaintenanceAttachment;
use App\Models\MaintenanceRequest;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<MaintenanceAttachment>
 */
class MaintenanceAttachmentFactory extends Factory
{
    public function definition(): array
    {
        return [
            'maintenance_request_id' => MaintenanceRequest::factory(),
            'path' => 'maintenance/'.Str::uuid()->toString().'.jpg',
            'uploaded_by_vendor' => false,
        ];
    }
}
