<?php

declare(strict_types=1);

namespace App\Notifications\Channels;

use App\Services\Messaging\DirectMail;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/** Sends a notification's toMail() at once through DirectMail (the Settings SMTP server). */
class DirectMailChannel
{
    public function __construct(private readonly DirectMail $mail) {}

    public function send(object $notifiable, Notification $notification): void
    {
        $email = $notifiable->email ?? null;
        if (! is_string($email) || $email === '' || ! method_exists($notification, 'toMail')) {
            return;
        }

        $message = $notification->toMail($notifiable);
        if ($message instanceof MailMessage) {
            $this->mail->send($email, is_string($notifiable->name ?? null) ? $notifiable->name : null, $message);
        }
    }
}
