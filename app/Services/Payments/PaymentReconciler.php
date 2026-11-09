<?php

declare(strict_types=1);

namespace App\Services\Payments;

use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Models\Payment;
use App\Services\ActivityLogger;

/**
 * Every 10 minutes (spec 8.6 step 7): re-verifies pending Paystack payments
 * between 10 minutes and 48 hours old; after 48 hours a payment still pending
 * is marked failed.
 */
class PaymentReconciler
{
    public function __construct(
        private readonly PaymentVerifier $verifier,
        private readonly ActivityLogger $logger,
    ) {}

    /** @return array{verified: int, expired: int} */
    public function run(): array
    {
        $after = (int) config('rentora.paystack.reconcile_after_minutes');
        $giveUp = (int) config('rentora.paystack.reconcile_give_up_hours');
        $base = fn () => Payment::query()->withoutGlobalScopes()
            ->where('method', PaymentMethod::Paystack->value)
            ->where('status', PaymentStatus::Pending->value);

        $expired = 0;
        foreach ($base()->where('created_at', '<', now()->subHours($giveUp))->get() as $payment) {
            $payment->forceFill(['status' => PaymentStatus::Failed, 'rejection_reason' => __('payments.errors.expired')])->save();
            $this->logger->log('payment.failed', $payment, ['reason' => 'expired']);
            $expired++;
        }

        $verified = 0;
        $due = $base()->whereBetween('created_at', [now()->subHours($giveUp), now()->subMinutes($after)])->limit(50)->get();
        foreach ($due as $payment) {
            $this->verifier->verify($payment);
            $verified++;
        }

        return ['verified' => $verified, 'expired' => $expired];
    }
}
