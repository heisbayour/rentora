<?php

declare(strict_types=1);

namespace App\Http\Requests\System;

use Illuminate\Foundation\Http\FormRequest;

class ResetRequest extends FormRequest
{
    /** Access is enforced by the route middleware (owner plus install key). */
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'confirm' => ['required', 'string', 'in:RESET'],
            'password' => ['required', 'string', 'current_password:web'],
            'new_owner_name' => ['nullable', 'required_with:new_owner_email', 'string', 'max:150'],
            'new_owner_email' => ['nullable', 'required_with:new_owner_name', 'email', 'max:150', 'unique:users,email'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'confirm.in' => __('reset.confirm_mismatch'),
            'password.current_password' => __('reset.wrong_password'),
        ];
    }
}
