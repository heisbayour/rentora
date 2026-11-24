<?php

declare(strict_types=1);

namespace App\Services\Scheduling;

use App\Models\SystemState;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Runs due tasks without cron (spec 10.2). One tick at a time: an atomic
 * UPDATE on system_state takes the lock, which expires after 2 minutes. The
 * tick respects a time budget and a failing task never stops the others.
 */
class LazyScheduler
{
    public const LOCK_KEY = 'scheduler_lock';

    public const LAST_TICK_KEY = 'scheduler.last_tick_at';

    public function __construct(private readonly TaskRegistry $registry) {}

    /** Lazy mode only: true when the last tick is older than the tick interval. */
    public function intervalElapsed(): bool
    {
        $last = SystemState::getValue(self::LAST_TICK_KEY);
        $minutes = (int) config('rentora.scheduler.tick_interval_minutes', 5);

        return $last === null || CarbonImmutable::parse($last)->addMinutes($minutes)->isPast();
    }

    /**
     * Runs every due task. Returns null when another tick holds the lock.
     *
     * @return array<string, array{status: string, result?: mixed, error?: string}>|null
     */
    public function tick(): ?array
    {
        $token = $this->acquireLock();
        if ($token === null) {
            return null;
        }

        $deadline = microtime(true) + (int) config('rentora.scheduler.budget_seconds', 8);
        $report = [];
        try {
            SystemState::put(self::LAST_TICK_KEY, now()->toIso8601String());
            foreach ($this->registry->tasks() as $name => $task) {
                if (! $this->isDue($name, $task)) {
                    continue;
                }
                if ($task['every'] !== 'tick' && microtime(true) >= $deadline) {
                    $report[$name] = ['status' => 'deferred'];

                    continue;
                }
                $report[$name] = $this->runTask($name, $task, $deadline);
            }
        } finally {
            $this->releaseLock($token);
        }

        return $report;
    }

    /** @param  array{every: string, after_hour?: int}  $task */
    public function isDue(string $name, array $task): bool
    {
        $last = SystemState::getValue(self::lastRunKey($name));
        $lastRun = $last === null ? null : CarbonImmutable::parse($last);
        $timezone = (string) config('rentora.defaults.timezone', 'Africa/Lagos');
        $now = CarbonImmutable::now($timezone);

        if ($task['every'] === 'tick') {
            return true;
        }
        if (str_starts_with($task['every'], 'minutes:')) {
            $minutes = (int) substr($task['every'], 8);

            return $lastRun === null || $lastRun->addMinutes($minutes)->lessThanOrEqualTo($now);
        }
        if ($task['every'] === 'weekly') {
            return $lastRun === null || $lastRun->addDays(7)->lessThanOrEqualTo($now);
        }

        // Daily: once per local calendar day, not before after_hour.
        if ($now->hour < ($task['after_hour'] ?? 0)) {
            return false;
        }

        return $lastRun === null || $lastRun->setTimezone($timezone)->toDateString() < $now->toDateString();
    }

    public static function lastRunKey(string $name): string
    {
        return 'task.'.$name.'.last_run_at';
    }

    public static function lastErrorKey(string $name): string
    {
        return 'task.'.$name.'.last_error';
    }

    /**
     * @param  array{every: string, run: callable(float): mixed}  $task
     * @return array{status: string, result?: mixed, error?: string}
     */
    private function runTask(string $name, array $task, float $deadline): array
    {
        try {
            $result = ($task['run'])($deadline);
            SystemState::put(self::lastRunKey($name), now()->toIso8601String());
            SystemState::put(self::lastErrorKey($name), null);

            return ['status' => 'ok', 'result' => $result];
        } catch (Throwable $e) {
            Log::error('Scheduler task failed', ['task' => $name, 'error' => $e->getMessage()]);
            // Record the run so a broken task does not retry on every request.
            SystemState::put(self::lastRunKey($name), now()->toIso8601String());
            SystemState::put(self::lastErrorKey($name), mb_substr($e->getMessage(), 0, 1000));

            return ['status' => 'failed', 'error' => $e->getMessage()];
        }
    }

    /** The atomic lock of spec 10.2. Returns the lock token, or null when held elsewhere. */
    private function acquireLock(): ?string
    {
        DB::table('system_state')->insertOrIgnore(['key' => self::LOCK_KEY, 'value' => null, 'updated_at' => now()]);

        $now = now()->utc()->format('Y-m-d H:i:s');
        $token = now()->utc()->addSeconds((int) config('rentora.scheduler.lock_seconds', 120))->format('Y-m-d H:i:s').'|'.bin2hex(random_bytes(4));

        $affected = DB::table('system_state')
            ->where('key', self::LOCK_KEY)
            ->where(fn ($q) => $q->whereNull('value')->orWhere('value', '<', $now))
            ->update(['value' => $token, 'updated_at' => now()]);

        return $affected === 1 ? $token : null;
    }

    private function releaseLock(string $token): void
    {
        DB::table('system_state')->where('key', self::LOCK_KEY)->where('value', $token)->update(['value' => null, 'updated_at' => now()]);
    }
}
