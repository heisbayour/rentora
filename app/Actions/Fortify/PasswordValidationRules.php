<?php

declare(strict_types=1);

namespace App\Actions\Fortify;

use App\Models\User;
use Illuminate\Contracts\Validation\Rule;
use Illuminate\Validation\Rules\Password;

trait PasswordValidationRules
{
    /**
     * Get the validation rules used to validate passwords. Staff passwords
     * need mixed case and numbers; everyone needs at least 8 characters (spec 11).
     *
     * @return array<int, Rule|array<mixed>|string>
     */
    protected function passwordRules(?User $user = null): array
    {
        return ['required', 'string', self::passwordRuleFor($user), 'confirmed'];
    }

    public static function passwordRuleFor(?User $user): Password
    {
        return $user !== null && $user->isStaff()
            ? Password::min(8)->mixedCase()->numbers()
            : Password::min(8);
    }
}
