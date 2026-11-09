<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Models\User;
use App\Services\ActivityLogger;
use Illuminate\Auth\Events\Login;

class RecordSuccessfulLogin
{
    public function __construct(private readonly ActivityLogger $logger) {}

    public function handle(Login $event): void
    {
        $user = $event->user;
        if (! $user instanceof User) {
            return;
        }

        $user->forceFill(['last_login_at' => now()])->saveQuietly();
        $this->logger->log('auth.login', $user, ['remember' => $event->remember], $user);
    }
}
