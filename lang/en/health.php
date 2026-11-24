<?php

declare(strict_types=1);

return [
    'title' => 'System health',
    'description' => 'Server checks, background tasks and the message outbox.',
    'debug_on' => 'APP_DEBUG is on. Turn it off in .env on a live site: error pages can reveal secrets.',
    'version' => 'Version and database',
    'database' => 'Database',
    'ok' => 'OK',
    'problem' => 'Problem',
    'none' => 'None',
    'never' => 'Never',
    'pending_migrations' => 'Pending migrations',
    'requirements' => 'Server requirements',
    'scheduler' => 'Scheduler',
    'mode' => 'Mode',
    'last_tick' => 'Last tick',
    'task' => 'Task',
    'frequency' => 'Runs',
    'last_run' => 'Last run',
    'last_error' => 'Last error',
    'outbox' => 'Email and SMS outbox',
    'drain_now' => 'Drain now',
    'retry_failed' => 'Retry failed',
    'failed_messages' => 'Recent failed messages',
    'retried' => ':count failed messages queued again.',
    'drained' => 'Outbox drained: :sent sent, :retried to retry, :failed failed.',
];
