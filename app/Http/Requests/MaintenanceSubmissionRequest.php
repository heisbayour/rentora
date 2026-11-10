<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Enums\MaintenanceCategory;
use App\Enums\MaintenancePriority;
use App\Enums\Role;
use App\Models\MaintenanceRequest;
use App\Services\Maintenance\MaintenanceWorkflow;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** A new repair request, from staff (any unit) or a tenant (their own unit, chosen for them). */
class MaintenanceSubmissionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->can('create', MaintenanceRequest::class);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $user = $this->user();
        $rules = [
            'category' => ['required', Rule::enum(MaintenanceCategory::class)],
            'title' => ['required', 'string', 'max:150'],
            'description' => ['required', 'string', 'max:5000'],
            'priority' => ['required', Rule::enum(MaintenancePriority::class)],
            'preferred_time' => ['nullable', 'string', 'max:100'],
            'access_instructions' => ['nullable', 'string', 'max:1000'],
            'photos' => ['nullable', 'array', 'max:'.MaintenanceWorkflow::MAX_PHOTOS],
            'photos.*' => ['file', 'mimes:'.config('rentora.uploads.image_mimes'), 'max:'.config('rentora.uploads.max_kb')],
        ];

        if ($user?->role !== Role::Tenant) {
            $rules['unit_id'] = ['required', 'integer', Rule::exists('units', 'id')->where('organization_id', $user?->organization_id)->whereNull('deleted_at')];
            $rules['tenant_id'] = ['nullable', 'integer', Rule::exists('tenants', 'id')->where('organization_id', $user?->organization_id)];
        }

        return $rules;
    }
}
