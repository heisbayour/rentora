<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Services\Messaging\NotificationMessage;

/** Portal invitation for a tenant by email and SMS (spec 8.4, 9.3 tenant.invited). Critical. */
class TenantInvitation extends OutboxNotification
{
    public function __construct(public readonly string $link) {}

    public function event(): string
    {
        return 'tenant.invited';
    }

    protected function defaultChannels(object $notifiable): array
    {
        return ['email', 'sms'];
    }

    protected function critical(): bool
    {
        return true;
    }

    public function message(object $notifiable): NotificationMessage
    {
        $org = self::organizationName($notifiable);

        return new NotificationMessage(
            title: __('tenants.invite_mail.subject', ['org' => $org]),
            body: __('tenants.invite_mail.line', ['org' => $org]).' '.__('tenants.invite_mail.expiry', ['days' => config('rentora.links.invite_days')]),
            link: $this->link,
            actionLabel: __('tenants.invite_mail.action'),
            sms: __('messaging.sms.invite'),
        );
    }
}
