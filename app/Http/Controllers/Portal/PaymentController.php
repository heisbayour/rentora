<?php

declare(strict_types=1);

namespace App\Http\Controllers\Portal;

use App\Actions\Payments\StartOnlinePayment;
use App\Actions\Payments\TransferProofs;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Portal\StartPaymentRequest;
use App\Http\Requests\Portal\TransferProofRequest;
use App\Models\Payment;
use App\Services\Payments\PaymentGateway;
use App\Services\Payments\PaymentVerifier;
use App\Services\Payments\ReceiptRenderer;
use App\Services\SettingsService;
use App\Support\Money;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;

/** Tenant-side payments (spec 8.6, 8.13): Pay screen, history, online checkout and transfer proofs. */
class PaymentController extends Controller
{
    /** Pay screen: open invoices (oldest preselected), card when Paystack is set up, otherwise bank transfer (DECISIONS 63). */
    public function pay(Request $request, PaymentGateway $gateway, SettingsService $settings): View
    {
        $tenant = $request->user()->tenant ?? abort(404);

        return view('portal.pay', [
            'tenant' => $tenant,
            'invoices' => $tenant->invoices()->open()->orderBy('due_date')->orderBy('id')->get(),
            'outstanding' => $tenant->outstanding(),
            'cardEnabled' => $gateway->isConfigured(),
            'bank' => [
                'name' => $settings->get('billing.bank_name'),
                'account_name' => $settings->get('billing.bank_account_name'),
                'account_number' => $settings->get('billing.bank_account_number'),
            ],
            'pendingProofs' => $tenant->payments()->where('method', PaymentMethod::BankTransfer->value)
                ->whereIn('status', [PaymentStatus::Pending->value, PaymentStatus::Rejected->value])
                ->latest('id')->limit(5)->get(),
            'currencySymbol' => Money::forOrganization(0)->symbol(),
        ]);
    }

    public function index(Request $request): View
    {
        $tenant = $request->user()->tenant ?? abort(404);

        return view('portal.payments', [
            'payments' => $tenant->payments()->latest('id')->paginate((int) config('rentora.per_page')),
        ]);
    }

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

        return redirect()->route('portal.payments.index')->with('toast', match ($payment->status) {
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

        return redirect()->route('portal.pay')->with('toast', __('payments.portal.proof_submitted'));
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
