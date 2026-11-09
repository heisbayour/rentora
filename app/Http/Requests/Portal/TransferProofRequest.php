<?php

declare(strict_types=1);

namespace App\Http\Requests\Portal;

use App\Http\Requests\Admin\UnitRequest;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class TransferProofRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->tenant !== null;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'amount' => ['required', 'integer', 'min:1', 'max:'.UnitRequest::MAX_MINOR],
            'paid_on' => ['required', 'date', 'before_or_equal:today'],
            'sender_name' => ['required', 'string', 'max:150'],
            'proof' => ['required', 'file', 'mimes:'.config('rentora.uploads.mimes'), 'max:'.config('rentora.uploads.max_kb')],
            'invoice_ids' => ['nullable', 'array'],
            'invoice_ids.*' => ['integer', Rule::exists('invoices', 'id')->where('tenant_id', $this->user()?->tenant?->id)],
        ];
    }
}
