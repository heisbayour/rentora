<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\Inquiry;
use App\Services\Messaging\NotificationMessage;
use Illuminate\Database\Eloquent\Model;

/** A visitor sent an inquiry from the public website (spec 9.3 inquiry.received). */
class InquiryReceived extends OutboxNotification
{
    public function __construct(
        public readonly Inquiry $inquiry,
        public readonly ?string $unitName,
    ) {}

    public function event(): string
    {
        return 'inquiry.received';
    }

    protected function defaultChannels(object $notifiable): array
    {
        return ['in_app', 'email'];
    }

    public function related(): ?Model
    {
        return $this->inquiry;
    }

    public function message(object $notifiable): NotificationMessage
    {
        return new NotificationMessage(
            title: __('inquiries.notification.title', ['name' => $this->inquiry->name]),
            body: __('inquiries.notification.body', [
                'name' => $this->inquiry->name,
                'unit' => $this->unitName ?? __('inquiries.general'),
                'contact' => trim(($this->inquiry->phone ?? '').' '.($this->inquiry->email ?? '')),
                'message' => $this->inquiry->message,
            ]),
            link: route('app.inquiries.index'),
            actionLabel: __('inquiries.notification.action'),
        );
    }
}
