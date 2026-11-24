<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\MaintenanceRequest;
use App\Services\Messaging\NotificationMessage;
use Illuminate\Database\Eloquent\Model;

/** Tells the tenant their repair moved to a new status (spec 9.3 maintenance.status_changed). */
class MaintenanceStatusChanged extends OutboxNotification
{
    public function __construct(public readonly MaintenanceRequest $request) {}

    public function event(): string
    {
        return 'maintenance.status_changed';
    }

    protected function defaultChannels(object $notifiable): array
    {
        return ['in_app', 'email'];
    }

    public function related(): ?Model
    {
        return $this->request;
    }

    public function message(object $notifiable): NotificationMessage
    {
        $status = $this->request->status->label();

        return new NotificationMessage(
            title: __('maintenance.status_mail.subject', ['number' => $this->request->number, 'status' => $status]),
            body: __('maintenance.status_mail.line', ['title' => $this->request->title, 'status' => $status]),
            link: route('portal.repairs.show', $this->request),
            actionLabel: __('maintenance.status_mail.action'),
        );
    }
}
