<?php

declare(strict_types=1);

use App\Enums\Role;
use App\Models\User;
use App\Services\Scheduling\LazyScheduler;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Str;

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

// Makes an existing account the developer (support) account (DECISIONS 105).
// The live site has no artisan, so v1.0.4's migration does this for the original developer.
Artisan::command('rentora:make-developer {email}', function (string $email) {
    $user = User::query()->withoutGlobalScopes()->where('email', Str::lower(trim($email)))->first();
    if ($user === null) {
        $this->error('No account with that email.');

        return 1;
    }
    $user->forceFill(['role' => Role::Developer, 'is_active' => true])->save();
    $this->info($user->name.' is now a developer.');

    return 0;
})->purpose('Give an existing account the developer (support) role');
