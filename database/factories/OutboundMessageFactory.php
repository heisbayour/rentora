<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Organization;
use App\Models\OutboundMessage;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<OutboundMessage> */
class OutboundMessageFactory extends Factory
{
    public function definition(): array
    {
        return [
            'organization_id' => Organization::factory(),
            'channel' => OutboundMessage::EMAIL,
            'event' => 'test',
            'recipient' => fake()->safeEmail(),
            'recipient_name' => fake()->name(),
            'subject' => 'Hello',
            'body' => 'Test message',
            'status' => 'queued',
            'send_after' => now()->subMinute(),
        ];
    }

    public function sms(): static
    {
        return $this->state(['channel' => OutboundMessage::SMS, 'recipient' => '+2348031234567', 'subject' => null]);
    }
}
