<?php

declare(strict_types=1);

namespace App\Notifications\Channels;

use App\Notifications\OutboxNotification;
use App\Services\Messaging\Outbox;

/** Renders the notification and queues it as an email in the outbox (spec 9.1). */
class OutboxEmailChannel
{
    public function __construct(private readonly Outbox $outbox) {}

    public function send(object $notifiable, OutboxNotification $notification): void
    {
        $email = OutboxNotification::emailOf($notifiable);
        if ($email === null) {
            return;
        }

        $this->outbox->queueEmail($notifiable, $email, $notification->event(), $notification->message($notifiable), $notification->related());
    }
}
