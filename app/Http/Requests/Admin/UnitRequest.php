<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use App\Enums\RentFrequency;
use App\Enums\UnitStatus;
use App\Enums\UnitType;
use App\Models\Unit;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UnitRequest extends FormRequest
{
    /** Largest amount accepted in a money field: 100 billion naira, in kobo. */
    public const MAX_MINOR = 10_000_000_000_000;

    public function authorize(): bool
    {
        $unit = $this->route('unit');

        return $unit instanceof Unit
            ? (bool) $this->user()?->can('update', $unit)
            : (bool) $this->user()?->can('create', Unit::class);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $unit = $this->route('unit');
        $money = ['required', 'integer', 'min:0', 'max:'.self::MAX_MINOR];

        return [
            'property_id' => [
                'required', 'integer',
                Rule::exists('properties', 'id')->where('organization_id', $this->user()?->organization_id)->whereNull('deleted_at'),
            ],
            'label' => [
                'required', 'string', 'max:60',
                Rule::unique('units', 'label')
                    ->where('property_id', $this->integer('property_id'))
                    ->ignore($unit instanceof Unit ? $unit->getKey() : null),
            ],
            'type' => ['required', Rule::enum(UnitType::class)],
            'bedrooms' => ['required', 'integer', 'min:0', 'max:50'],
            'bathrooms' => ['required', 'integer', 'min:0', 'max:50'],
            'size_sqm' => ['nullable', 'numeric', 'min:0', 'max:999999'],
            'rent_amount' => $money,
            'rent_frequency' => ['required', Rule::enum(RentFrequency::class)],
            'service_charge_amount' => $money,
            'caution_deposit_amount' => $money,
            'status' => ['required', Rule::enum(UnitStatus::class)],
            'is_listed' => ['required', 'boolean'],
            'description' => ['nullable', 'string', 'max:5000'],
        ];
    }
}
