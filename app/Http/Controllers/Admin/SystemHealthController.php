<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Enums\MessageStatus;
use App\Http\Controllers\Controller;
use App\Models\OutboundMessage;
use App\Models\SystemState;
use App\Services\ActivityLogger;
use App\Services\Messaging\Outbox;
use App\Services\Scheduling\LazyScheduler;
use App\Services\Scheduling\TaskRegistry;
use App\Support\AppVersion;
use App\Support\Requirements;
use Illuminate\Database\Migrations\Migrator;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use Throwable;

/** System health for owners (spec 8.15, FINISH_BUILD Section 6). */
class SystemHealthController extends Controller
{
    public function show(Requirements $requirements, Migrator $migrator, TaskRegistry $registry, Outbox $outbox): View
    {
        try {
            DB::connection()->getPdo();
            $database = ['ok' => true, 'detail' => DB::connection()->getDriverName().' '.DB::connection()->getDatabaseName()];
        } catch (Throwable $e) {
            $database = ['ok' => false, 'detail' => $e->getMessage()];
        }

        $tasks = [];
        foreach ($registry->tasks() as $name => $task) {
            $tasks[] = [
                'name' => $name,
                'every' => $task['every'],
                'last_run' => SystemState::getValue(LazyScheduler::lastRunKey($name)),
                'error' => SystemState::getValue(LazyScheduler::lastErrorKey($name)),
            ];
        }

        return view('admin.health', [
            'checks' => $requirements->checks(),
            'database' => $database,
            'pending' => self::pendingMigrations($migrator),
            'version' => ['version' => AppVersion::version(), 'commit' => AppVersion::commit(), 'built_at' => AppVersion::builtAt()],
            'phpVersion' => PHP_VERSION,
            'debug' => (bool) config('app.debug'),
            'schedulerMode' => (string) config('rentora.scheduler.mode'),
            'lastTick' => SystemState::getValue(LazyScheduler::LAST_TICK_KEY),
            'tasks' => $tasks,
            'outbox' => $outbox->counts(),
            'failed' => OutboundMessage::query()->where('status', MessageStatus::Failed->value)->latest('id')->limit(10)->get(),
        ]);
    }

    public function retryFailed(Outbox $outbox, ActivityLogger $logger): RedirectResponse
    {
        $count = $outbox->retryFailed();
        $logger->log('outbox.retried', null, ['count' => $count]);

        return back()->with('toast', __('health.retried', ['count' => $count]));
    }

    public function drain(Outbox $outbox): RedirectResponse
    {
        $result = $outbox->drain(microtime(true) + (int) config('rentora.scheduler.budget_seconds', 8));

        return back()->with('toast', __('health.drained', $result));
    }

    /** @return list<string> */
    public static function pendingMigrations(Migrator $migrator): array
    {
        $files = $migrator->getMigrationFiles([database_path('migrations')]);
        $repository = $migrator->getRepository();
        $ran = $repository->repositoryExists() ? $repository->getRan() : [];

        return array_values(array_diff(array_keys($files), $ran));
    }
}
