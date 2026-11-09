<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use App\Enums\PropertyType;
use App\Models\Property;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PropertyRequest extends FormRequest
{
    public function authorize(): bool
    {
        $property = $this->route('property');

        return $property instanceof Property
            ? (bool) $this->user()?->can('update', $property)
            : (bool) $this->user()?->can('create', Property::class);
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['code' => strtoupper(trim((string) $this->input('code')))]);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $property = $this->route('property');

        return [
            'name' => ['required', 'string', 'max:150'],
            'code' => [
                'required', 'string', 'max:20', 'alpha_dash',
                Rule::unique('properties', 'code')
                    ->where('organization_id', $this->user()?->organization_id)
                    ->ignore($property instanceof Property ? $property->getKey() : null),
            ],
            'type' => ['required', Rule::enum(PropertyType::class)],
            'address' => ['required', 'string', 'max:255'],
            'city' => ['nullable', 'string', 'max:100'],
            'state' => ['nullable', 'string', 'max:100'],
            'country' => ['required', 'string', 'max:100'],
            'description' => ['nullable', 'string', 'max:5000'],
            'is_listed' => ['required', 'boolean'],
            'cover_image' => ['nullable', 'file', 'mimes:'.config('rentora.uploads.image_mimes'), 'max:'.config('rentora.uploads.max_kb')],
            'remove_cover' => ['nullable', 'boolean'],
        ];
    }

    /** @return array<string, mixed> */
    public function propertyData(): array
    {
        return $this->safe()->except(['cover_image', 'remove_cover']);
    }
}
