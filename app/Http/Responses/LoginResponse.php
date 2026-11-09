<?php

declare(strict_types=1);

namespace App\Http\Responses;

use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Laravel\Fortify\Contracts\LoginResponse as LoginResponseContract;
use Laravel\Fortify\Contracts\TwoFactorLoginResponse as TwoFactorLoginResponseContract;
use Symfony\Component\HttpFoundation\Response;

/**
 * After login, staff go to /app and tenants to /portal (spec 4.3). An
 * intended URL is honoured only when it belongs to the user's own area.
 */
class LoginResponse implements LoginResponseContract, TwoFactorLoginResponseContract
{
    /**
     * @param  Request  $request
     */
    public function toResponse($request): Response
    {
        if ($request->wantsJson()) {
            return new JsonResponse(['two_factor' => false]);
        }

        $user = $request->user();
        $home = self::homeFor($user);
        $intended = (string) $request->session()->pull('url.intended', '');
        $path = '/'.ltrim((string) parse_url($intended, PHP_URL_PATH), '/');

        if ($intended !== '' && str_starts_with($path.'/', rtrim((string) parse_url($home, PHP_URL_PATH), '/').'/')) {
            return new RedirectResponse($intended);
        }

        return new RedirectResponse($home);
    }

    public static function homeFor(?User $user): string
    {
        return $user !== null && ! $user->isStaff() ? route('portal.home') : route('app.dashboard');
    }
}
