<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Services\Messaging\MessagingConfig;
use Illuminate\Contracts\Support\Responsable;
use Illuminate\Validation\ValidationException;
use Laravel\Fortify\Fortify;
use Laravel\Fortify\Http\Controllers\PasswordResetLinkController as FortifyPasswordResetLinkController;
use Laravel\Fortify\Http\Requests\SendPasswordResetLinkRequest;
use Throwable;

/**
 * "Forgot password" that never fails with a server error (DECISIONS 104).
 * With email off it says so and points to the owner, who can issue a reset
 * link from the console; a failing SMTP server gets the same kind of answer.
 * Bound over Fortify's controller in FortifyServiceProvider.
 */
class PasswordResetLinkController extends FortifyPasswordResetLinkController
{
    public function store(SendPasswordResetLinkRequest $request): Responsable
    {
        if (! app(MessagingConfig::class)->emailConfigured()) {
            throw ValidationException::withMessages([Fortify::email() => __('auth.forgot.email_off')]);
        }

        try {
            return parent::store($request);
        } catch (Throwable $e) {
            if ($e instanceof ValidationException) {
                throw $e;
            }
            report($e);

            throw ValidationException::withMessages([Fortify::email() => __('auth.forgot.email_failed')]);
        }
    }
}
