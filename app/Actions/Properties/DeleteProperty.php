<?php

declare(strict_types=1);

namespace App\Actions\Properties;

use App\Models\Lease;
use App\Models\Property;
use App\Services\ActivityLogger;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/** Soft deletes a property and its units, unless any unit has an active lease (spec 8.3). */
class DeleteProperty
{
    public function __construct(private readonly ActivityLogger $logger) {}

    public function handle(Property $property): void
    {
        DB::transaction(function () use ($property): void {
            $leased = Lease::query()->active()
                ->whereIn('unit_id', $property->units()->select('id'))
                ->lockForUpdate()
                ->exists();
            if ($leased) {
                throw ValidationException::withMessages(['property' => __('properties.errors.has_active_lease', ['name' => $property->name])]);
            }
            $property->units()->get()->each->delete();
            $property->delete();
        });

        $this->logger->log('property.deleted', $property, ['name' => $property->name]);
    }
}
