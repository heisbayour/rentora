<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\User;
use App\Notifications\Channels\DirectMailChannel;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Invitation for a new staff member to set a password, sent at once through
 * the Settings SMTP server (DECISIONS 70). InviteStaff only sends it when
 * email is set up and catches any failure (DECISIONS 104).
 */
class StaffInvitation extends Notification
{
    public function __construct(public readonly string $link) {}

    /** @return list<string> */
    public function via(User $notifiable): array
    {
        return [DirectMailChannel::class];
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
