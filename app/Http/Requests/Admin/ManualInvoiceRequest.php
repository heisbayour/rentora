<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use App\Enums\InvoiceType;
use App\Models\Invoice;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ManualInvoiceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->can('create', Invoice::class);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $organizationId = $this->user()?->organization_id;

        return [
            'tenant_id' => ['required', 'integer', Rule::exists('tenants', 'id')->where('organization_id', $organizationId)->whereNull('deleted_at')],
            'lease_id' => ['nullable', 'integer', Rule::exists('leases', 'id')->where('organization_id', $organizationId)->where('tenant_id', $this->integer('tenant_id'))],
            'type' => ['required', Rule::in(array_map(fn (InvoiceType $t) => $t->value, InvoiceType::manual()))],
            'description' => ['required', 'string', 'max:255'],
            'amount' => ['required', 'integer', 'min:1', 'max:'.UnitRequest::MAX_MINOR],
            'due_date' => ['required', 'date'],
        ];
    }
}
