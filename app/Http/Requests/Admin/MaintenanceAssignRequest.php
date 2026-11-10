<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use App\Models\MaintenanceRequest;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class MaintenanceAssignRequest extends FormRequest
{
    public function authorize(): bool
    {
        $request = $this->route('maintenance');

        return $request instanceof MaintenanceRequest && (bool) $this->user()?->can('manage', $request);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $organizationId = $this->user()?->organization_id;

        return [
            'assigned_user_id' => ['nullable', 'integer', Rule::exists('users', 'id')->where('organization_id', $organizationId)->whereIn('role', ['owner', 'manager', 'caretaker'])],
            'vendor_id' => ['nullable', 'integer', Rule::exists('vendors', 'id')->where('organization_id', $organizationId)->where('is_active', true)],
        ];
    }
}
