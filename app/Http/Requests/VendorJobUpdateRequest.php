<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Enums\MaintenanceStatus;
use App\Services\Maintenance\MaintenanceWorkflow;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** What a vendor may send through their signed job link (spec 8.7). */
class VendorJobUpdateRequest extends FormRequest
{
    /** The signed URL and the controller's vendor check are the authorization. */
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'status' => ['nullable', Rule::in([MaintenanceStatus::InProgress->value, MaintenanceStatus::Resolved->value])],
            'body' => ['nullable', 'required_without_all:status,photos', 'string', 'max:5000'],
            'photos' => ['nullable', 'array', 'max:'.MaintenanceWorkflow::MAX_PHOTOS],
            'photos.*' => ['file', 'mimes:'.config('rentora.uploads.image_mimes'), 'max:'.config('rentora.uploads.max_kb')],
        ];
    }
}
