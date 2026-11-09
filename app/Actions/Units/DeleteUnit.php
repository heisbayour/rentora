<?php

declare(strict_types=1);

namespace App\Actions\Units;

use App\Models\Unit;
use App\Services\ActivityLogger;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/** Soft deletes a unit, unless it has an active lease (spec 8.3). */
class DeleteUnit
{
    public function __construct(private readonly ActivityLogger $logger) {}

    public function handle(Unit $unit): void
    {
        DB::transaction(function () use ($unit): void {
            Unit::query()->lockForUpdate()->findOrFail($unit->getKey());
            if ($unit->hasActiveLease()) {
                throw ValidationException::withMessages(['unit' => __('units.errors.has_active_lease', ['label' => $unit->label])]);
            }
            $unit->delete();
        });

        $this->logger->log('unit.deleted', $unit, ['label' => $unit->label]);
    }
}
