<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Support\Installation;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * The installer exists only before the lock file is written, and only for
 * someone holding RENTORA_INSTALL_KEY (?key= or a key form field).
 */
class InstallerGuard
{
    public function handle(Request $request, Closure $next): Response
    {
        abort_if(Installation::isInstalled(), 404);
        abort_unless(Installation::keyMatches($this->key($request)), 403, __('install.key_required'));

        return $next($request);
    }

    private function key(Request $request): ?string
    {
        $key = $request->query('key', $request->input('key'));

        return is_string($key) ? $key : null;
    }
}
