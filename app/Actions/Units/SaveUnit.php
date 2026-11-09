<?php

declare(strict_types=1);

namespace App\Actions\Units;

use App\Enums\UnitStatus;
use App\Models\Unit;

class SaveUnit
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function handle(?Unit $unit, array $data): Unit
    {
        $unit ??= new Unit;
        unset($data['photos']);
        $unit->fill($data);

        // A unit can only be listed publicly while it is vacant or reserved (spec 8.11).
        $unit->status ??= UnitStatus::Vacant;
        if (! $unit->status->isListable()) {
            $unit->is_listed = false;
        }
        $unit->save();

        return $unit;
    }
}
