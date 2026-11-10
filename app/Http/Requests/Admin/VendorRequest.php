<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use App\Models\Vendor;
use Illuminate\Foundation\Http\FormRequest;

class VendorRequest extends FormRequest
{
    public function authorize(): bool
    {
        $vendor = $this->route('vendor');

        return $vendor instanceof Vendor
            ? (bool) $this->user()?->can('update', $vendor)
            : (bool) $this->user()?->can('create', Vendor::class);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:150'],
            'trade' => ['nullable', 'string', 'max:100'],
            'phone' => ['nullable', 'required_without:email', 'string', 'max:32'],
            'email' => ['nullable', 'required_without:phone', 'email', 'max:150'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'is_active' => ['required', 'boolean'],
        ];
    }
}
