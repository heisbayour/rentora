<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Services\Scheduling\LazyScheduler;
use App\Support\Installation;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

/**
 * Lazy scheduler mode (spec 10.2): after the response has been sent, run a
 * tick when the last one is older than the tick interval. Never breaks the
 * user's request.
 */
class RunLazyScheduler
{
    public function __construct(private readonly LazyScheduler $scheduler) {}

    public function handle(Request $request, Closure $next): Response
    {
        return $next($request);
    }

    public function terminate(Request $request, Response $response): void
    {
        if (config('rentora.scheduler.mode') !== 'lazy' || ! Installation::isInstalled()) {
            return;
        }

        try {
            // Response::send() has already flushed the page to the browser (fastcgi_finish_request).
            if ($this->scheduler->intervalElapsed()) {
                $this->scheduler->tick();
            }
        } catch (Throwable $e) {
            Log::error('Lazy scheduler tick failed', ['error' => $e->getMessage()]);
        }
    }
}
