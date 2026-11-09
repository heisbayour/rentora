<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\InvoiceType;
use App\Models\Invoice;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Invoice>
 */
class InvoiceFactory extends Factory
{
    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'organization_id' => fn (array $a) => Tenant::query()->withoutGlobalScopes()->find($a['tenant_id'])?->organization_id,
            'number' => 'INV-'.now()->format('Y').'-'.fake()->unique()->numerify('######'),
            'type' => InvoiceType::Other,
            'description' => 'Sundry charge',
            'amount' => 10_000_000,
            'amount_paid' => 0,
            'due_date' => today(),
            'status' => 'unpaid',
            'issued_at' => now(),
        ];
    }
}
