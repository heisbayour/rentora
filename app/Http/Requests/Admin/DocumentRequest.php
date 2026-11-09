<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use App\Enums\DocumentVisibility;
use App\Http\Controllers\Admin\DocumentController;
use App\Models\Document;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class DocumentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->can('create', Document::class);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'documentable_type' => ['required', Rule::in(array_keys(DocumentController::DOCUMENTABLES))],
            'documentable_id' => ['required', 'integer'],
            'file' => ['required', 'file', 'mimes:'.config('rentora.uploads.mimes'), 'max:'.config('rentora.uploads.max_kb')],
            'name' => ['nullable', 'string', 'max:150'],
            'visibility' => ['required', Rule::enum(DocumentVisibility::class)],
        ];
    }
}
