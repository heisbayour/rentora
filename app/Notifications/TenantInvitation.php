<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\Tenant;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Portal invitation for a tenant. Email now; Phase 8 adds SMS through the
 * outbox so tenants without email can be invited by SMS (spec 8.4).
 */
class TenantInvitation extends Notification
{
    public function __construct(public readonly string $link) {}

    /** @return list<string> */
    public function via(Tenant $notifiable): array
    {
        return $notifiable->email ? ['mail'] : [];
    }

    public function toMail(Tenant $notifiable): MailMessage
    {
        $organization = $notifiable->organization()->value('name') ?? config('rentora.name');

        return (new MailMessage)
            ->subject(__('tenants.invite_mail.subject', ['org' => $organization]))
            ->greeting(__('tenants.invite_mail.greeting', ['name' => $notifiable->full_name]))
            ->line(__('tenants.invite_mail.line', ['org' => $organization]))
            ->action(__('tenants.invite_mail.action'), $this->link)
            ->line(__('tenants.invite_mail.expiry', ['days' => config('rentora.links.invite_days')]));
    }
}
