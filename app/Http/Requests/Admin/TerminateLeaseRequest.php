<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use App\Models\Lease;
use Illuminate\Foundation\Http\FormRequest;

class TerminateLeaseRequest extends FormRequest
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
            'reason' => ['required', 'string', 'min:3', 'max:1000'],
            'final_invoice' => ['nullable', 'boolean'],
        ];
    }
}
