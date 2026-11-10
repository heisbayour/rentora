<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Models\MaintenanceRequest;
use App\Services\Maintenance\MaintenanceWorkflow;
use Illuminate\Foundation\Http\FormRequest;

/** A comment on the thread, optionally with photos. "internal" is honoured for staff only. */
class MaintenanceCommentRequest extends FormRequest
{
    public function authorize(): bool
    {
        $request = $this->route('maintenance');

        return $request instanceof MaintenanceRequest && (bool) $this->user()?->can('comment', $request);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'body' => ['required', 'string', 'max:5000'],
            'is_internal' => ['nullable', 'boolean'],
            'photos' => ['nullable', 'array', 'max:'.MaintenanceWorkflow::MAX_PHOTOS],
            'photos.*' => ['file', 'mimes:'.config('rentora.uploads.image_mimes'), 'max:'.config('rentora.uploads.max_kb')],
        ];
    }
}
