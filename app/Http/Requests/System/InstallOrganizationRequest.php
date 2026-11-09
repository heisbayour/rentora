<?php

declare(strict_types=1);

namespace App\Http\Requests\System;

use App\Actions\Fortify\PasswordValidationRules;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

class InstallOrganizationRequest extends FormRequest
{
    use PasswordValidationRules;

    /** Access is enforced by the installer middleware (lock file and install key). */
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'organization_name' => ['required', 'string', 'max:150'],
            'name' => ['required', 'string', 'max:150'],
            'email' => ['required', 'email', 'max:150'],
            'phone' => ['nullable', 'string', 'max:32'],
            'password' => ['required', 'string', Password::min(8)->mixedCase()->numbers(), 'confirmed'],
        ];
    }
}
