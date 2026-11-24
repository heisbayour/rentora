<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\Payment;
use App\Models\Tenant;
use App\Services\Messaging\NotificationMessage;
use Illuminate\Database\Eloquent\Model;

/**
 * A successful payment (spec 9.3 payment.received). The tenant gets the
 * receipt link (critical: always by email); owners and managers get a note.
 */
class PaymentReceived extends OutboxNotification
{
    public function __construct(
        public readonly Payment $payment,
        public readonly string $tenantName,
    ) {}

    public function event(): string
    {
        return 'payment.received';
    }

    protected function defaultChannels(object $notifiable): array
    {
        return ['in_app', 'email'];
    }

    protected function critical(): bool
    {
        return true;
    }

    public function related(): ?Model
    {
        return $this->payment;
    }

    public function message(object $notifiable): NotificationMessage
    {
        $replace = [
            'amount' => $this->payment->money()->format(),
            'receipt' => (string) $this->payment->receipt_number,
            'tenant' => $this->tenantName,
        ];

        if ($notifiable instanceof Tenant) {
            return new NotificationMessage(
                title: __('messaging.payment_received.tenant_title', $replace),
                body: __('messaging.payment_received.tenant_body', $replace),
                link: route('receipts.verify', $this->payment->verification_code),
                actionLabel: __('messaging.payment_received.tenant_action'),
            );
        }

        return new NotificationMessage(
            title: __('messaging.payment_received.staff_title', $replace),
            body: __('messaging.payment_received.staff_body', $replace),
            link: route('app.payments.show', $this->payment),
            actionLabel: __('messaging.payment_received.staff_action'),
        );
    }
}
