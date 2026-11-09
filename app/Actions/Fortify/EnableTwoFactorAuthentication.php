<?php

declare(strict_types=1);

namespace App\Actions\Fortify;

use Laravel\Fortify\Actions\EnableTwoFactorAuthentication as FortifyEnableTwoFactorAuthentication;

/** TOTP two-factor authentication is offered to owners and managers only (spec 8.1). */
class EnableTwoFactorAuthentication extends FortifyEnableTwoFactorAuthentication
{
    /**
     * @param  mixed  $user
     * @param  bool  $force
     */
    public function __invoke($user, $force = false)
    {
        abort_unless($user->canUseTwoFactor(), 403);

        parent::__invoke($user, $force);
    }
}
