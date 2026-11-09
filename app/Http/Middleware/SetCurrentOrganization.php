<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\Organization;
use App\Support\CurrentOrganization;
use App\Support\Installation;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Signed-in users work in their own organization. Guests (public site, login
 * pages) use the only organization in single-organization mode.
 */
class SetCurrentOrganization
{
    public function __construct(private readonly CurrentOrganization $current) {}

    public function handle(Request $request, Closure $next): Response
    {
        if (! Installation::isInstalled()) {
            return $next($request);
        }

        $user = $request->user();
        if ($user !== null) {
            $this->current->set(Organization::query()->find($user->organization_id));
        } elseif (! config('rentora.multi_org')) {
            $this->current->set(Organization::query()->oldest('id')->first());
        }

        return $next($request);
    }
}
