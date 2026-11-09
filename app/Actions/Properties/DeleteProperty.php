<?php

declare(strict_types=1);

namespace App\Actions\Properties;

use App\Models\Property;
use App\Services\ActivityLogger;
use Illuminate\Support\Facades\DB;

/** Soft deletes a property and its units. Phase 4 adds the active-lease guard (spec 8.3). */
class DeleteProperty
{
    public function __construct(private readonly ActivityLogger $logger) {}

    public function handle(Property $property): void
    {
        DB::transaction(function () use ($property): void {
            $property->units()->get()->each->delete();
            $property->delete();
        });

        $this->logger->log('property.deleted', $property, ['name' => $property->name]);
    }
}
