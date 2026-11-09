<?php

declare(strict_types=1);

namespace App\Actions\Payments;

use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Models\Payment;
use App\Models\Tenant;
use App\Models\User;
use App\Services\ActivityLogger;
use App\Services\Payments\PaymentCodes;
use App\Support\PrivateFiles;
use Carbon\CarbonInterface;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * "I paid by transfer" (spec 8.6): the tenant uploads proof, creating a pending
 * payment; an owner or manager approves (allocation and receipt) or rejects it
 * with a reason.
 */
class TransferProofs
{
    public function __construct(
        private readonly PaymentCodes $codes,
        private readonly PrivateFiles $files,
        private readonly CompletePayment $complete,
        private readonly ActivityLogger $logger,
    ) {}

    /**
     * @param  list<int>  $invoiceIds
     */
    public function submit(Tenant $tenant, int $amount, CarbonInterface $paidAt, string $senderName, UploadedFile $proof, array $invoiceIds = []): Payment
    {
        $payment = new Payment([
            'tenant_id' => $tenant->getKey(),
            'lease_id' => $tenant->activeLease?->getKey(),
            'reference' => $this->codes->reference(),
            'verification_code' => $this->codes->verificationCode(),
            'amount' => $amount,
            'method' => PaymentMethod::BankTransfer,
            'paid_at' => $paidAt,
            'note' => $senderName,
            'proof_path' => $this->files->store($proof, 'proofs'),
        ]);
        $payment->organization_id = $tenant->organization_id;
        $payment->gateway_payload = ['requested_invoice_ids' => $invoiceIds];
        $payment->save();

        $this->logger->log('payment.proof_submitted', $payment, ['amount' => $amount]);

        return $payment;
    }

    public function approve(Payment $payment, User $approver): Payment
    {
        $this->assertPendingProof($payment);
        $requested = array_map('intval', (array) (($payment->gateway_payload ?? [])['requested_invoice_ids'] ?? []));

        return $this->complete->handle($payment, $requested, $approver, 'payment.approved');
    }

    public function reject(Payment $payment, User $approver, string $reason): Payment
    {
        DB::transaction(function () use ($payment, $approver, $reason): void {
            $locked = Payment::query()->lockForUpdate()->findOrFail($payment->getKey());
            $this->assertPendingProof($locked);
            $locked->forceFill([
                'status' => PaymentStatus::Rejected,
                'rejection_reason' => $reason,
                'approved_by' => $approver->getKey(),
                'approved_at' => now(),
            ])->save();
        });

        $this->logger->log('payment.rejected', $payment, ['reason' => $reason], $approver);

        return $payment->refresh();
    }

    private function assertPendingProof(Payment $payment): void
    {
        if ($payment->method !== PaymentMethod::BankTransfer || $payment->status !== PaymentStatus::Pending) {
            throw ValidationException::withMessages(['payment' => __('payments.errors.not_pending_proof')]);
        }
    }
}
