<?php

declare(strict_types=1);

namespace App\Actions\Payments;

use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Models\Payment;
use App\Models\User;
use App\Services\ActivityLogger;
use App\Services\Billing\AllocationService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Owner-only reversal of a successful manual payment (spec 8.6): removes its
 * allocations, restores invoice balances and marks it rejected. Paystack
 * payments are refunded in the Paystack dashboard instead.
 */
class ReversePayment
{
    public function __construct(
        private readonly AllocationService $allocations,
        private readonly ActivityLogger $logger,
    ) {}

    public function handle(Payment $payment, User $owner, string $reason): Payment
    {
        DB::transaction(function () use ($payment, $owner, $reason): void {
            $locked = Payment::query()->lockForUpdate()->findOrFail($payment->getKey());
            if ($locked->method === PaymentMethod::Paystack) {
                throw ValidationException::withMessages(['payment' => __('payments.errors.paystack_reversal')]);
            }
            if ($locked->status !== PaymentStatus::Successful) {
                throw ValidationException::withMessages(['payment' => __('payments.errors.not_successful_reversal')]);
            }

            $locked->forceFill([
                'status' => PaymentStatus::Rejected,
                'rejection_reason' => $reason,
                'approved_by' => $owner->getKey(),
                'approved_at' => now(),
            ])->save();
            $this->allocations->deallocatePayment($locked);
        });

        $this->logger->log('payment.reversed', $payment, ['reason' => $reason, 'amount' => $payment->amount], $owner);

        return $payment->refresh();
    }
}
