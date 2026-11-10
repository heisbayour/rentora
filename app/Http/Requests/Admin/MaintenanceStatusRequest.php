<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use App\Enums\MaintenanceStatus;
use App\Models\MaintenanceRequest;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class MaintenanceStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        $request = $this->route('maintenance');

        return $request instanceof MaintenanceRequest && (bool) $this->user()?->can('manage', $request);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'status' => ['required', Rule::enum(MaintenanceStatus::class)],
            'note' => ['nullable', 'string', 'max:2000'],
            'scheduled_for' => ['nullable', 'required_if:status,scheduled', 'date'],
            'cost_amount' => ['nullable', 'integer', 'min:0', 'max:'.UnitRequest::MAX_MINOR],
            'record_expense' => ['nullable', 'boolean'],
        ];
    }
}
