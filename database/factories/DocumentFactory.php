<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\DocumentVisibility;
use App\Models\Document;
use App\Models\Property;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Document>
 */
class DocumentFactory extends Factory
{
    public function definition(): array
    {
        return [
            'documentable_type' => 'property',
            'documentable_id' => Property::factory(),
            'organization_id' => fn (array $attributes) => Property::query()->withoutGlobalScopes()->find($attributes['documentable_id'])?->organization_id,
            'name' => 'Survey plan.pdf',
            'path' => 'documents/'.Str::uuid()->toString().'.pdf',
            'mime' => 'application/pdf',
            'size' => 120_000,
            'visibility' => DocumentVisibility::Internal,
        ];
    }
}
