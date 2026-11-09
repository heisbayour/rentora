<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use App\Enums\Role;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateStaffRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('manage-staff') ?? false;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'role' => ['required', Rule::in(array_map(fn (Role $role) => $role->value, Role::staff()))],
            'is_active' => ['required', 'boolean'],
        ];
    }
}
