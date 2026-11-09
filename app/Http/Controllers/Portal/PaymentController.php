<?php

declare(strict_types=1);

namespace App\Http\Controllers\Portal;

use App\Actions\Payments\StartOnlinePayment;
use App\Actions\Payments\TransferProofs;
use App\Enums\PaymentStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Portal\StartPaymentRequest;
use App\Http\Requests\Portal\TransferProofRequest;
use App\Models\Payment;
use App\Services\Payments\PaymentVerifier;
use App\Services\Payments\ReceiptRenderer;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Symfony\Component\HttpFoundation\Response;

/** Tenant-side payment endpoints (spec 8.6). The pay wizard screens arrive in Phase 9. */
class PaymentController extends Controller
{
    public function start(StartPaymentRequest $request, StartOnlinePayment $start): RedirectResponse
    {
        $result = $start->handle(
            $request->user()->tenant ?? abort(404),
            (int) $request->validated('amount'),
            array_map('intval', (array) $request->validated('invoice_ids', [])),
        );

        return redirect()->away($result['url']);
    }

    /** Paystack sends the payer back here; confirm server-side before trusting anything. */
    public function callback(Request $request, PaymentVerifier $verifier): RedirectResponse
    {
        $reference = (string) $request->query('reference', $request->query('trxref', ''));
        $payment = Payment::query()->where('reference', $reference)->first();
        abort_if($payment === null || $payment->tenant->user_id !== $request->user()->id, 404);

        $payment = $verifier->verify($payment);

        return redirect()->route('portal.home')->with('toast', match ($payment->status) {
            PaymentStatus::Successful => ['message' => __('payments.portal.success', ['receipt' => $payment->receipt_number]), 'type' => 'success'],
            PaymentStatus::Pending => ['message' => __('payments.portal.pending'), 'type' => 'info'],
            default => ['message' => __('payments.portal.failed'), 'type' => 'danger'],
        });
    }

    public function submitProof(TransferProofRequest $request, TransferProofs $proofs): RedirectResponse
    {
        $proofs->submit(
            $request->user()->tenant ?? abort(404),
            (int) $request->validated('amount'),
            Carbon::parse((string) $request->validated('paid_on')),
            (string) $request->validated('sender_name'),
            $request->file('proof'),
            array_map('intval', (array) $request->validated('invoice_ids', [])),
        );

        return redirect()->route('portal.home')->with('toast', __('payments.portal.proof_submitted'));
    }

    public function check(Payment $payment, PaymentVerifier $verifier): RedirectResponse
    {
        $this->authorize('view', $payment);

        $payment = $verifier->verify($payment);

        return back()->with('toast', __('payments.status_now', ['status' => $payment->status->label()]));
    }

    public function receipt(Payment $payment, ReceiptRenderer $receipts): Response
    {
        $this->authorize('view', $payment);

        return $receipts->download($payment);
    }
}
