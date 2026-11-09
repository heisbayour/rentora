<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Models\Payment;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Payment>
 */
class PaymentFactory extends Factory
{
    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'organization_id' => fn (array $a) => Tenant::query()->withoutGlobalScopes()->find($a['tenant_id'])?->organization_id,
            'reference' => 'RNT-'.Str::ulid(),
            'verification_code' => Str::upper(Str::random(10)),
            'amount' => 10_000_000,
            'method' => PaymentMethod::Cash,
            'status' => PaymentStatus::Successful,
            'paid_at' => now(),
        ];
    }
}
