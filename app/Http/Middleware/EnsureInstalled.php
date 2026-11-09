<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Support\Installation;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** Until the installer has run, every page except the installer shows a "not installed" notice. */
class EnsureInstalled
{
    public function handle(Request $request, Closure $next): Response
    {
        if (Installation::isInstalled() || $request->routeIs('install.*') || $request->is('up')) {
            return $next($request);
        }

        return response()->view('install.required', [], 503);
    }
}
