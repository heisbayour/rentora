<?php

declare(strict_types=1);

namespace App\Actions\Units;

use App\Models\Unit;
use App\Services\ActivityLogger;

/** Soft deletes a unit. Phase 4 adds the active-lease guard (spec 8.3). */
class DeleteUnit
{
    public function __construct(private readonly ActivityLogger $logger) {}

    public function handle(Unit $unit): void
    {
        $unit->delete();

        $this->logger->log('unit.deleted', $unit, ['label' => $unit->label]);
    }
}
