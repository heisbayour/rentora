<?php

declare(strict_types=1);

use App\Services\Scheduling\LazyScheduler;
use Illuminate\Support\Facades\Artisan;

// Runs one scheduler tick (spec 10.2): for cron mode and local testing.
Artisan::command('rentora:tick', function (LazyScheduler $scheduler) {
    $report = $scheduler->tick();
    if ($report === null) {
        $this->warn('Another tick is running (lock held).');

        return 0;
    }
    if ($report === []) {
        $this->info('No tasks were due.');
    }
    foreach ($report as $task => $outcome) {
        $line = $task.': '.$outcome['status'].(isset($outcome['error']) ? ' ('.$outcome['error'].')' : '');
        $outcome['status'] === 'failed' ? $this->error($line) : $this->line($line);
    }

    return 0;
})->purpose('Run the Rentora scheduler tasks that are due');
