<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\Payment;
use App\Services\Messaging\NotificationMessage;
use Illuminate\Database\Eloquent\Model;

/** The landlord rejected a transfer proof (spec 9.3 payment.proof_rejected). */
class ProofRejected extends OutboxNotification
{
    public function __construct(public readonly Payment $payment) {}

    public function event(): string
    {
        return 'payment.proof_rejected';
    }

    protected function defaultChannels(object $notifiable): array
    {
        return ['in_app', 'email', 'sms'];
    }

    public function related(): ?Model
    {
        return $this->payment;
    }

    public function message(object $notifiable): NotificationMessage
    {
        $replace = ['amount' => $this->payment->money()->format(), 'reason' => (string) $this->payment->rejection_reason];

        return new NotificationMessage(
            title: __('messaging.proof_rejected.title', $replace),
            body: __('messaging.proof_rejected.body', $replace),
            link: self::link('portal.pay'),
            actionLabel: __('messaging.proof_rejected.action'),
        );
    }
}
