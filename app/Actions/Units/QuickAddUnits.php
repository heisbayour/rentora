<?php

declare(strict_types=1);

namespace App\Actions\Units;

use App\Enums\RentFrequency;
use App\Enums\UnitStatus;
use App\Enums\UnitType;
use App\Models\Property;
use App\Models\Unit;
use Illuminate\Support\Facades\DB;

/** Adds up to 50 units to a property in one go (spec 8.3). All or nothing. */
class QuickAddUnits
{
    public const MAX_ROWS = 50;

    /**
     * @param  list<array{label: string, type: string, rent_amount: int|string, rent_frequency: string}>  $rows
     * @return int number of units created
     */
    public function handle(Property $property, array $rows): int
    {
        return DB::transaction(function () use ($property, $rows): int {
            foreach ($rows as $row) {
                Unit::query()->create([
                    'property_id' => $property->getKey(),
                    'label' => trim($row['label']),
                    'type' => UnitType::from($row['type']),
                    'rent_amount' => (int) $row['rent_amount'],
                    'rent_frequency' => RentFrequency::from($row['rent_frequency']),
                    'status' => UnitStatus::Vacant,
                ]);
            }

            return count($rows);
        });
    }

    /**
     * Drops rows the user left completely blank, so a half-filled table can be submitted.
     *
     * @param  array<int, array<string, mixed>>  $rows
     * @return list<array<string, mixed>>
     */
    public static function filledRows(array $rows): array
    {
        return array_values(array_filter($rows, fn (array $row) => trim((string) ($row['label'] ?? '')) !== ''));
    }
}
