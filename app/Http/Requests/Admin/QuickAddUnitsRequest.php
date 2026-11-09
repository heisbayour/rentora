<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use App\Actions\Units\QuickAddUnits;
use App\Enums\RentFrequency;
use App\Enums\UnitType;
use App\Models\Property;
use App\Models\Unit;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class QuickAddUnitsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->can('create', Unit::class);
    }

    /** Blank rows are dropped before validation so a partly filled table submits cleanly. */
    protected function prepareForValidation(): void
    {
        $rows = $this->input('units');
        $this->merge(['units' => QuickAddUnits::filledRows(is_array($rows) ? $rows : [])]);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $property = $this->route('property');
        $propertyId = $property instanceof Property ? $property->getKey() : 0;

        return [
            'units' => ['required', 'array', 'min:1', 'max:'.QuickAddUnits::MAX_ROWS],
            'units.*.label' => [
                'required', 'string', 'max:60', 'distinct:ignore_case',
                Rule::unique('units', 'label')->where('property_id', $propertyId),
            ],
            'units.*.type' => ['required', Rule::enum(UnitType::class)],
            'units.*.rent_amount' => ['required', 'integer', 'min:0', 'max:'.UnitRequest::MAX_MINOR],
            'units.*.rent_frequency' => ['required', Rule::enum(RentFrequency::class)],
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($validator->errors()->has('units.*')) {
                    $validator->errors()->add('units', __('units.quick_add.row_errors'));
                }
            },
        ];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return [
            'units.*.label' => __('units.fields.label'),
            'units.*.type' => __('units.fields.type'),
            'units.*.rent_amount' => __('units.fields.rent_amount'),
            'units.*.rent_frequency' => __('units.fields.rent_frequency'),
        ];
    }
}
