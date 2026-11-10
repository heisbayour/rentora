<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\MaintenanceRequest;
use App\Models\User;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Tells the tenant their repair moved to a new status (spec 9.3
 * maintenance.status_changed). Email now; Phase 8 adds the in-app inbox and
 * routes it through the outbox.
 */
class MaintenanceStatusChanged extends Notification
{
    public function __construct(public readonly MaintenanceRequest $request) {}

    /** @return list<string> */
    public function via(User $notifiable): array
    {
        return $notifiable->email ? ['mail'] : [];
    }

    public function toMail(User $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject(__('maintenance.status_mail.subject', ['number' => $this->request->number, 'status' => $this->request->status->label()]))
            ->greeting(__('maintenance.vendor_mail.greeting', ['name' => $notifiable->name]))
            ->line(__('maintenance.status_mail.line', ['title' => $this->request->title, 'status' => $this->request->status->label()]))
            ->action(__('maintenance.status_mail.action'), route('portal.repairs.show', $this->request));
    }
}
