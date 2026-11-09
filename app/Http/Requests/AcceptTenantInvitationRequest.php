<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\Validator;

class AcceptTenantInvitationRequest extends FormRequest
{
    /** The route is signed; the controller checks the link fingerprint. */
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $tenant = $this->route('tenant');
        $hasEmail = $tenant instanceof Tenant && ($tenant->email ?? '') !== '';

        return [
            // Tenants invited by SMS choose the email they will log in with.
            'email' => $hasEmail ? ['prohibited'] : ['required', 'email', 'max:150', Rule::unique('users', 'email')],
            'password' => ['required', 'string', Password::min(8), 'confirmed'],
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $tenant = $this->route('tenant');
                if ($tenant instanceof Tenant && ($tenant->email ?? '') !== ''
                    && User::query()->withoutGlobalScopes()->where('email', Str::lower($tenant->email))->exists()) {
                    $validator->errors()->add('password', __('tenants.errors.email_in_use'));
                }
            },
        ];
    }

    public function loginEmail(): string
    {
        $tenant = $this->route('tenant');

        return $tenant instanceof Tenant && ($tenant->email ?? '') !== '' ? $tenant->email : (string) $this->validated('email');
    }
}
