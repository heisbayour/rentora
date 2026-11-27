<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Security headers on every web response (spec 11). Runs before the session
 * starts so the session cookie is marked secure whenever the request is
 * HTTPS, without a host-specific .env flag.
 */
class SecurityHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        // When the site is served over HTTPS (APP_URL) but the host's proxy
        // hands PHP a plain http request without forwarding headers, treat
        // the request as HTTPS so request URLs match the https links the app
        // signed. Otherwise every signed link fails with "Invalid signature".
        if (! $request->isSecure() && str_starts_with((string) config('app.url'), 'https://')) {
            $request->server->set('HTTPS', 'on');
        }

        if ($request->isSecure() && config('session.secure') === null) {
            config(['session.secure' => true]);
        }

        $response = $next($request);

        $headers = $response->headers;
        $headers->set('X-Frame-Options', 'SAMEORIGIN', false);
        $headers->set('X-Content-Type-Options', 'nosniff', false);
        $headers->set('Referrer-Policy', 'strict-origin-when-cross-origin', false);
        $headers->set('Permissions-Policy', 'camera=(), microphone=(), geolocation=()', false);
        if ($request->isSecure() && app()->environment('production')) {
            $headers->set('Strict-Transport-Security', 'max-age=31536000; includeSubDomains', false);
        }

        return $response;
    }
}
