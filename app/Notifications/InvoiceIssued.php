<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\Invoice;
use App\Services\Messaging\NotificationMessage;
use Illuminate\Database\Eloquent\Model;

/** A new invoice for the tenant (spec 9.3 invoice.issued). */
class InvoiceIssued extends OutboxNotification
{
    public function __construct(public readonly Invoice $invoice) {}

    public function event(): string
    {
        return 'invoice.issued';
    }

    protected function defaultChannels(object $notifiable): array
    {
        return ['in_app', 'email'];
    }

    public function related(): ?Model
    {
        return $this->invoice;
    }

    public function message(object $notifiable): NotificationMessage
    {
        return new NotificationMessage(
            title: __('messaging.invoice_issued.title', ['number' => $this->invoice->number]),
            body: __('messaging.invoice_issued.body', [
                'number' => $this->invoice->number,
                'amount' => $this->invoice->total()->format(),
                'due_date' => $this->invoice->due_date->format('j M Y'),
            ]),
            link: self::link('portal.invoices.show', $this->invoice),
            actionLabel: __('messaging.invoice_issued.action'),
        );
    }
}
