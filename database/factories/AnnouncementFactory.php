<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Announcement;
use App\Models\Organization;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Announcement> */
class AnnouncementFactory extends Factory
{
    public function definition(): array
    {
        return [
            'organization_id' => Organization::factory(),
            'title' => 'Water supply interruption',
            'body' => 'Water will be off on Saturday from 9am to 1pm for tank cleaning.',
            'audience' => 'all_tenants',
            'audience_ids' => null,
            'channels' => ['in_app'],
            'sent_at' => now(),
            'recipient_count' => 0,
        ];
    }
}
