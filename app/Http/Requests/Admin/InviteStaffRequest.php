<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use App\Enums\Role;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class InviteStaffRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('manage-staff') ?? false;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:150'],
            'email' => ['required', 'email', 'max:150', Rule::unique('users', 'email')],
            'phone' => ['nullable', 'string', 'max:32'],
            'role' => ['required', Rule::in(array_map(fn (Role $role) => $role->value, Role::staff()))],
        ];
    }
}
