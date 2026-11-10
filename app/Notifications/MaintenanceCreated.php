<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Enums\MaintenancePriority;
use App\Models\MaintenanceRequest;
use App\Models\User;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Tells staff about a new repair request (spec 9.3 maintenance.created).
 * Email now; Phase 8 adds the in-app inbox and the immediate SMS for
 * emergencies through the outbox.
 */
class MaintenanceCreated extends Notification
{
    public function __construct(public readonly MaintenanceRequest $request) {}

    /** @return list<string> */
    public function via(User $notifiable): array
    {
        return $notifiable->email ? ['mail'] : [];
    }

    public function isEmergency(): bool
    {
        return $this->request->priority === MaintenancePriority::Emergency;
    }

    public function toMail(User $notifiable): MailMessage
    {
        $key = $this->isEmergency() ? 'maintenance.created_mail.subject_emergency' : 'maintenance.created_mail.subject';

        return (new MailMessage)
            ->subject(__($key, ['number' => $this->request->number, 'title' => $this->request->title]))
            ->greeting(__('maintenance.vendor_mail.greeting', ['name' => $notifiable->name]))
            ->line(__('maintenance.created_mail.line', ['title' => $this->request->title, 'priority' => $this->request->priority->label()]))
            ->action(__('maintenance.created_mail.action'), route('app.maintenance.show', $this->request));
    }
}
