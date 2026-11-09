<?php

declare(strict_types=1);

use App\Http\Middleware\EnsureInstalled;
use App\Http\Middleware\EnsureOnboarded;
use App\Http\Middleware\EnsureUserHasRole;
use App\Http\Middleware\EnsureUserIsActive;
use App\Http\Middleware\InstallerGuard;
use App\Http\Middleware\SetCurrentOrganization;
use App\Http\Responses\LoginResponse;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Routing\Middleware\SubstituteBindings;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->web(prepend: [EnsureInstalled::class], append: [SetCurrentOrganization::class]);
        // The organization must be known before route model binding, or bound
        // models would be resolved without the OrganizationScope.
        $middleware->prependToPriorityList(before: SubstituteBindings::class, prepend: SetCurrentOrganization::class);

        $middleware->validateCsrfTokens(except: ['webhooks/*']);

        $middleware->alias([
            'role' => EnsureUserHasRole::class,
            'active' => EnsureUserIsActive::class,
            'onboarded' => EnsureOnboarded::class,
            'installer' => InstallerGuard::class,
        ]);

        $middleware->redirectUsersTo(fn (Request $request) => LoginResponse::homeFor($request->user()));
    })
    ->withExceptions(function (Exceptions $exceptions) {
        //
    })->create();
