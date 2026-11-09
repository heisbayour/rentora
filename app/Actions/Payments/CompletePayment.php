<?php

declare(strict_types=1);

namespace App\Actions\Payments;

use App\Enums\PaymentStatus;
use App\Models\Payment;
use App\Models\User;
use App\Services\ActivityLogger;
use App\Services\Billing\AllocationService;
use App\Services\SequenceService;
use Illuminate\Support\Facades\DB;

/**
 * Marks a payment successful exactly once: receipt number, allocation to
 * invoices (chosen ones first, then oldest), credit for any remainder.
 * Safe to call twice: a payment that is already successful is left alone.
 */
class CompletePayment
{
    public function __construct(
        private readonly SequenceService $sequences,
        private readonly AllocationService $allocations,
        private readonly ActivityLogger $logger,
    ) {}

    /**
     * @param  list<int>  $preferredInvoiceIds
     */
    public function handle(Payment $payment, array $preferredInvoiceIds = [], ?User $approvedBy = null, string $action = 'payment.received'): Payment
    {
        $completed = DB::transaction(function () use ($payment, $preferredInvoiceIds, $approvedBy): ?Payment {
            $payment = Payment::query()->withoutGlobalScopes()->lockForUpdate()->findOrFail($payment->getKey());
            if ($payment->status === PaymentStatus::Successful) {
                return null;
            }

            $payment->status = PaymentStatus::Successful;
            $payment->paid_at ??= now();
            $payment->receipt_number = $this->sequences->next(SequenceService::RECEIPT, $payment->paid_at, $payment->organization_id);
            if ($approvedBy !== null) {
                $payment->approved_by = $approvedBy->getKey();
                $payment->approved_at = now();
            }
            $payment->save();

            $this->allocations->allocatePayment($payment, $preferredInvoiceIds);

            return $payment;
        });

        if ($completed === null) {
            return $payment->refresh();
        }

        $this->logger->log($action, $completed, [
            'reference' => $completed->reference,
            'receipt' => $completed->receipt_number,
            'amount' => $completed->amount,
            'method' => $completed->method->value,
        ], $approvedBy);

        return $completed->refresh();
    }
}
