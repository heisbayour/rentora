<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\User;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Invitation for a new staff member to set a password. Sent by mail for now;
 * Phase 8 moves every outbound message onto the outbox channels.
 */
class StaffInvitation extends Notification
{
    public function __construct(public readonly string $link) {}

    /** @return list<string> */
    public function via(User $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(User $notifiable): MailMessage
    {
        $organization = $notifiable->organization()->value('name') ?? config('rentora.name');

        return (new MailMessage)
            ->subject(__('staff.invite_mail.subject', ['org' => $organization]))
            ->greeting(__('staff.invite_mail.greeting', ['name' => $notifiable->name]))
            ->line(__('staff.invite_mail.line', ['org' => $organization, 'role' => $notifiable->role->label()]))
            ->action(__('staff.invite_mail.action'), $this->link)
            ->line(__('staff.invite_mail.expiry', ['days' => config('rentora.links.invite_days')]));
    }
}
