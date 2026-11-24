<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use App\Enums\ExpenseCategory;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ExpenseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->can('manage-money');
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $organizationId = $this->user()?->organization_id;

        return [
            'property_id' => ['required', 'integer', Rule::exists('properties', 'id')->where('organization_id', $organizationId)->whereNull('deleted_at')],
            'unit_id' => ['nullable', 'integer', Rule::exists('units', 'id')->where('organization_id', $organizationId)->where('property_id', $this->integer('property_id'))],
            'category' => ['required', Rule::in(ExpenseCategory::values())],
            'amount' => ['required', 'integer', 'min:1', 'max:'.UnitRequest::MAX_MINOR],
            'incurred_on' => ['required', 'date', 'before_or_equal:today'],
            'description' => ['required', 'string', 'max:255'],
            'receipt' => ['nullable', 'file', 'mimes:'.config('rentora.uploads.mimes'), 'max:'.config('rentora.uploads.max_kb')],
        ];
    }
}
