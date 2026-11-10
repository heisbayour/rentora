<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\ExpenseCategory;
use App\Models\Expense;
use App\Models\Property;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Expense>
 */
class ExpenseFactory extends Factory
{
    public function definition(): array
    {
        return [
            'property_id' => Property::factory(),
            'organization_id' => fn (array $a) => Property::query()->withoutGlobalScopes()->find($a['property_id'])?->organization_id,
            'category' => ExpenseCategory::Repairs,
            'amount' => 2_500_000,
            'incurred_on' => today(),
            'description' => 'Replaced kitchen tap',
        ];
    }
}
