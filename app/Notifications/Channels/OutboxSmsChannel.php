<?php

declare(strict_types=1);

namespace App\Notifications\Channels;

use App\Notifications\OutboxNotification;
use App\Services\Messaging\Outbox;

/** Renders the notification and queues it as an SMS in the outbox (spec 9.1). */
class OutboxSmsChannel
{
    public function __construct(private readonly Outbox $outbox) {}

    public function send(object $notifiable, OutboxNotification $notification): void
    {
        $phone = OutboxNotification::phoneOf($notifiable);
        if ($phone === null) {
            return;
        }

        $this->outbox->queueSms($notifiable, $phone, $notification->event(), $notification->message($notifiable), $notification->related());
    }
}
