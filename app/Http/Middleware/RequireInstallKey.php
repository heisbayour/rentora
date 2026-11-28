<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Support\Installation;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Developer-only system pages (/system/reset): the request must carry
 * RENTORA_INSTALL_KEY as ?key= or a "key" form field. Checked before any
 * validation, so a request without the key learns nothing.
 */
class RequireInstallKey
{
    public function handle(Request $request, Closure $next): Response
    {
        $key = $request->query('key', $request->input('key'));
        abort_unless(Installation::keyMatches(is_string($key) ? $key : null), 403, __('install.key_required'));

        return $next($request);
    }
}
