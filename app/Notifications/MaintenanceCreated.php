<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Enums\MaintenancePriority;
use App\Models\MaintenanceRequest;
use App\Services\Messaging\NotificationMessage;
use Illuminate\Database\Eloquent\Model;

/** Tells staff about a new repair request (spec 9.3 maintenance.created); emergencies also go by SMS. */
class MaintenanceCreated extends OutboxNotification
{
    public function __construct(public readonly MaintenanceRequest $request) {}

    public function event(): string
    {
        return 'maintenance.created';
    }

    public function isEmergency(): bool
    {
        return $this->request->priority === MaintenancePriority::Emergency;
    }

    protected function defaultChannels(object $notifiable): array
    {
        return $this->isEmergency() ? ['in_app', 'email', 'sms'] : ['in_app', 'email'];
    }

    public function related(): ?Model
    {
        return $this->request;
    }

    public function message(object $notifiable): NotificationMessage
    {
        $key = $this->isEmergency() ? 'maintenance.created_mail.subject_emergency' : 'maintenance.created_mail.subject';

        return new NotificationMessage(
            title: __($key, ['number' => $this->request->number, 'title' => $this->request->title]),
            body: __('maintenance.created_mail.line', ['title' => $this->request->title, 'priority' => $this->request->priority->label()]),
            link: route('app.maintenance.show', $this->request),
            actionLabel: __('maintenance.created_mail.action'),
        );
    }
}
