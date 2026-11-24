<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\Payment;
use App\Services\Messaging\NotificationMessage;
use Illuminate\Database\Eloquent\Model;

/** A tenant uploaded a transfer proof that needs approval (spec 9.3 payment.proof_submitted). */
class ProofSubmitted extends OutboxNotification
{
    public function __construct(
        public readonly Payment $payment,
        public readonly string $tenantName,
    ) {}

    public function event(): string
    {
        return 'payment.proof_submitted';
    }

    protected function defaultChannels(object $notifiable): array
    {
        return ['in_app', 'email'];
    }

    public function related(): ?Model
    {
        return $this->payment;
    }

    public function message(object $notifiable): NotificationMessage
    {
        $replace = ['amount' => $this->payment->money()->format(), 'tenant' => $this->tenantName];

        return new NotificationMessage(
            title: __('messaging.proof_submitted.title', $replace),
            body: __('messaging.proof_submitted.body', $replace),
            link: route('app.payments.show', $this->payment),
            actionLabel: __('messaging.proof_submitted.action'),
        );
    }
}
