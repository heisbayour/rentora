<?php

declare(strict_types=1);

namespace App\Services\Payments;

use App\Actions\Payments\CompletePayment;
use App\Enums\PaymentStatus;
use App\Models\Organization;
use App\Models\Payment;
use App\Services\ActivityLogger;
use Illuminate\Support\Facades\DB;

/**
 * The single place online payments are confirmed (spec 8.6): used by the
 * redirect callback, the webhook, "Check status" and reconciliation. Marks
 * success only when the provider says success AND the amount and currency
 * match exactly. Idempotent: the payment row is locked and a successful
 * payment is never processed again.
 */
class PaymentVerifier
{
    public function __construct(
        private readonly PaymentGateway $gateway,
        private readonly CompletePayment $complete,
        private readonly ActivityLogger $logger,
    ) {}

    public function verify(Payment $payment): Payment
    {
        if ($payment->status === PaymentStatus::Successful) {
            return $payment;
        }

        return $this->process($payment, $this->gateway->verify($payment->reference));
    }

    public function process(Payment $payment, GatewayVerification $result): Payment
    {
        $outcome = DB::transaction(function () use ($payment, $result): string {
            $payment = Payment::query()->withoutGlobalScopes()->lockForUpdate()->findOrFail($payment->getKey());
            if ($payment->status !== PaymentStatus::Pending) {
                return 'unchanged';
            }

            $payment->last_verified_at = now();
            $payment->gateway_reference = $result->gatewayReference ?? $payment->gateway_reference;
            $currency = Organization::query()->whereKey($payment->organization_id)->value('currency') ?? config('rentora.defaults.currency');

            if ($result->succeeded()) {
                $mismatch = match (true) {
                    $result->amountMinor !== $payment->amount => __('payments.errors.amount_mismatch'),
                    $result->currency !== strtoupper((string) $currency) => __('payments.errors.currency_mismatch'),
                    default => null,
                };
                if ($mismatch === null) {
                    $payment->save();

                    return 'success';
                }
                $this->fail($payment, $mismatch, $result);

                return 'failed';
            }

            if ($result->isPending()) {
                $payment->save();

                return 'pending';
            }

            $this->fail($payment, $result->error ?: __('payments.errors.not_successful', ['status' => $result->status]), $result);

            return 'failed';
        });

        if ($outcome === 'success') {
            $requested = (array) (($payment->refresh()->gateway_payload ?? [])['requested_invoice_ids'] ?? []);

            return $this->complete->handle($payment, array_map('intval', $requested));
        }
        if ($outcome === 'failed') {
            $this->logger->log('payment.failed', $payment, ['reference' => $payment->reference]);
        }

        return $payment->refresh();
    }

    private function fail(Payment $payment, string $reason, GatewayVerification $result): void
    {
        $payment->status = PaymentStatus::Failed;
        $payment->rejection_reason = $reason;
        $payment->gateway_payload = array_merge($payment->gateway_payload ?? [], ['verification' => $result->payload]);
        $payment->save();
    }
}
