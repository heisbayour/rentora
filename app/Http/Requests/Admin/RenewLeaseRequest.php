<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use App\Models\Lease;
use Illuminate\Foundation\Http\FormRequest;

class RenewLeaseRequest extends FormRequest
{
    public function authorize(): bool
    {
        $lease = $this->route('lease');

        return $lease instanceof Lease && (bool) $this->user()?->can('update', $lease);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'end_date' => ['required', 'date'],
            'rent_amount' => ['required', 'integer', 'min:0', 'max:'.UnitRequest::MAX_MINOR],
            'service_charge_amount' => ['required', 'integer', 'min:0', 'max:'.UnitRequest::MAX_MINOR],
        ];
    }
}
