<?php

declare(strict_types=1);

namespace App\Http\Controllers\System;

use App\Http\Controllers\Controller;
use App\Services\Scheduling\LazyScheduler;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** External cron trigger: /system/tick?key=RENTORA_TICK_KEY (spec 4.3, 10.2). */
class TickController extends Controller
{
    public function __invoke(Request $request, LazyScheduler $scheduler): JsonResponse
    {
        $expected = (string) config('rentora.scheduler.tick_key');
        $given = (string) $request->query('key', '');
        abort_if($expected === '' || ! hash_equals($expected, $given), 404);

        $report = $scheduler->tick();

        return response()->json($report === null ? ['status' => 'locked'] : ['status' => 'ok', 'tasks' => array_map(
            fn (array $task) => $task['status'],
            $report,
        )]);
    }
}
