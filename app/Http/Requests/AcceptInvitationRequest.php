<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Actions\Fortify\PasswordValidationRules;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;

class AcceptInvitationRequest extends FormRequest
{
    use PasswordValidationRules;

    /** The route is signed; the controller checks the link fingerprint. */
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $user = $this->route('user');

        return [
            'password' => $this->passwordRules($user instanceof User ? $user : null),
        ];
    }
}
