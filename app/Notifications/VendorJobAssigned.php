<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\MaintenanceRequest;
use App\Services\Messaging\NotificationMessage;
use Illuminate\Database\Eloquent\Model;

/** Sends the assigned vendor their signed job link (spec 9.3 maintenance.vendor_assigned). */
class VendorJobAssigned extends OutboxNotification
{
    public function __construct(
        public readonly MaintenanceRequest $request,
        public readonly string $link,
    ) {}

    public function event(): string
    {
        return 'maintenance.vendor_assigned';
    }

    protected function defaultChannels(object $notifiable): array
    {
        return ['sms', 'email'];
    }

    public function related(): ?Model
    {
        return $this->request;
    }

    public function message(object $notifiable): NotificationMessage
    {
        return new NotificationMessage(
            title: __('maintenance.vendor_mail.subject', ['number' => $this->request->number]),
            body: __('maintenance.vendor_mail.line', ['title' => $this->request->title]).' '.__('maintenance.vendor_mail.expiry', ['days' => config('rentora.links.vendor_days')]),
            link: $this->link,
            actionLabel: __('maintenance.vendor_mail.action'),
            sms: __('messaging.sms.vendor_job', ['number' => $this->request->number, 'title' => $this->request->title]),
        );
    }
}
