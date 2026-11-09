<?php

declare(strict_types=1);

namespace App\Http\Requests\Portal;

use App\Http\Requests\Admin\UnitRequest;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StartPaymentRequest extends FormRequest
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
            'invoice_ids' => ['nullable', 'array'],
            // Only the signed-in tenant's own invoices.
            'invoice_ids.*' => ['integer', Rule::exists('invoices', 'id')->where('tenant_id', $this->user()?->tenant?->id)],
        ];
    }
}
