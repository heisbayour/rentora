<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Enums\Role;
use App\Http\Responses\LoginResponse;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Usage: ->middleware('role:owner,manager').
 *
 * A signed-in user who opens a page of the other area (staff on /portal, a
 * tenant on /app) is sent to their own home instead of a bare 403, so an
 * old bookmark or the Android app's start page never strands them. Any
 * other role mismatch, and every non-GET request, is still a 403.
 */
class EnsureUserHasRole
{
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $user = $request->user();
        $role = $user?->role;

        if ($role instanceof Role && in_array($role->value, $roles, true)) {
            return $next($request);
        }

        if ($role instanceof Role && $this->isOtherArea($request, $role)) {
            return redirect()->to(LoginResponse::homeFor($user));
        }

        abort(403);
    }

    private function isOtherArea(Request $request, Role $role): bool
    {
        if (! $request->isMethod('GET') && ! $request->isMethod('HEAD')) {
            return false;
        }
        if ($request->expectsJson()) {
            return false;
        }

        $isTenant = $role === Role::Tenant;

        return ($isTenant && $request->is('app', 'app/*')) || (! $isTenant && $request->is('portal', 'portal/*'));
    }
}
