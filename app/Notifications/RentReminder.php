<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\Invoice;
use App\Services\Messaging\NotificationMessage;
use Illuminate\Database\Eloquent\Model;

/**
 * Rent reminders before, on and after the due date (spec 9.3
 * invoice.reminder_pre_due, invoice.due_today, invoice.overdue).
 */
class RentReminder extends OutboxNotification
{
    /** Kind => days relative to the due date (negative = before). */
    public const KINDS = [
        'pre_due_7' => -7,
        'pre_due_3' => -3,
        'due_today' => 0,
        'overdue_3' => 3,
        'overdue_7' => 7,
    ];

    public function __construct(
        public readonly Invoice $invoice,
        public readonly string $kind,
        public readonly int $days,
    ) {}

    public function event(): string
    {
        return match (true) {
            str_starts_with($this->kind, 'pre_due') => 'invoice.reminder_pre_due',
            $this->kind === 'due_today' => 'invoice.due_today',
            default => 'invoice.overdue',
        };
    }

    protected function defaultChannels(object $notifiable): array
    {
        return ['in_app', 'email', 'sms'];
    }

    public function related(): ?Model
    {
        return $this->invoice;
    }

    public function message(object $notifiable): NotificationMessage
    {
        $replace = [
            'number' => $this->invoice->number,
            'amount' => $this->invoice->outstanding()->format(),
            'due_date' => $this->invoice->due_date->format('j M Y'),
            'days' => abs($this->days),
        ];
        $key = match ($this->event()) {
            'invoice.reminder_pre_due' => 'pre_due',
            'invoice.due_today' => 'due_today',
            default => 'overdue',
        };

        return new NotificationMessage(
            title: __('messaging.reminder.'.$key.'.title', $replace),
            body: __('messaging.reminder.'.$key.'.body', $replace),
            link: self::link('portal.pay'),
            actionLabel: __('messaging.reminder.action'),
            sms: __('messaging.reminder.'.$key.'.body', $replace),
        );
    }
}
