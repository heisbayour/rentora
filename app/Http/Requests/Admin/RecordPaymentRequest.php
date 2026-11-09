<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use App\Enums\PaymentMethod;
use App\Models\Payment;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class RecordPaymentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->can('create', Payment::class);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $organizationId = $this->user()?->organization_id;
        $manual = array_map(fn (PaymentMethod $m) => $m->value, array_filter(PaymentMethod::cases(), fn (PaymentMethod $m) => $m !== PaymentMethod::Paystack));

        return [
            'tenant_id' => ['required', 'integer', Rule::exists('tenants', 'id')->where('organization_id', $organizationId)],
            'method' => ['required', Rule::in($manual)],
            'amount' => ['required', 'integer', 'min:1', 'max:'.UnitRequest::MAX_MINOR],
            'paid_at' => ['required', 'date', 'before_or_equal:today'],
            'note' => ['nullable', 'string', 'max:500'],
            'proof' => ['nullable', 'file', 'mimes:'.config('rentora.uploads.mimes'), 'max:'.config('rentora.uploads.max_kb')],
            'invoice_ids' => ['nullable', 'array'],
            'invoice_ids.*' => ['integer', Rule::exists('invoices', 'id')->where('organization_id', $organizationId)->where('tenant_id', $this->integer('tenant_id'))],
        ];
    }
}
