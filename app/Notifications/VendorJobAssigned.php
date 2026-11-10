<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\MaintenanceRequest;
use App\Models\Vendor;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Sends a vendor the signed job link (spec 8.7). Email now; Phase 8 adds SMS
 * through the outbox.
 */
class VendorJobAssigned extends Notification
{
    public function __construct(
        public readonly MaintenanceRequest $request,
        public readonly string $link,
    ) {}

    /** @return list<string> */
    public function via(Vendor $notifiable): array
    {
        return $notifiable->email ? ['mail'] : [];
    }

    public function toMail(Vendor $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject(__('maintenance.vendor_mail.subject', ['number' => $this->request->number]))
            ->greeting(__('maintenance.vendor_mail.greeting', ['name' => $notifiable->name]))
            ->line(__('maintenance.vendor_mail.line', ['title' => $this->request->title]))
            ->action(__('maintenance.vendor_mail.action'), $this->link)
            ->line(__('maintenance.vendor_mail.expiry', ['days' => config('rentora.links.vendor_days')]));
    }
}
