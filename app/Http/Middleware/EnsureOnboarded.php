<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Services\Onboarding;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** Sends the owner to the first-run wizard until it is finished (spec 6.5.1). */
class EnsureOnboarded
{
    public function __construct(private readonly Onboarding $onboarding) {}

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        if ($user !== null && $user->isOwner() && $request->isMethod('GET')
            && ! $request->routeIs('app.onboarding.*', 'app.styleguide') && ! $this->onboarding->isComplete()) {
            return redirect()->route('app.onboarding.show', $this->onboarding->currentStep());
        }

        return $next($request);
    }
}
