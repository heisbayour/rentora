<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Actions\Payments\RecordManualPayment;
use App\Actions\Payments\ReversePayment;
use App\Actions\Payments\TransferProofs;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Http\Controllers\Concerns\SortsLists;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\PaymentReasonRequest;
use App\Http\Requests\Admin\RecordPaymentRequest;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Tenant;
use App\Services\Payments\PaymentVerifier;
use App\Services\Payments\ReceiptRenderer;
use App\Support\Money;
use App\Support\PrivateFiles;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;

class PaymentController extends Controller
{
    use SortsLists;

    public function index(Request $request): View
    {
        $this->authorize('viewAny', Payment::class);

        $search = trim((string) $request->query('q', ''));
        $status = PaymentStatus::tryFrom((string) $request->query('status'));
        $method = PaymentMethod::tryFrom((string) $request->query('method'));
        $proofs = $request->query('proofs') === '1';

        $query = Payment::query()->with('tenant')
            ->when($search !== '', fn (Builder $q) => $q->where(fn (Builder $w) => $w
                ->where('reference', 'like', "%{$search}%")
                ->orWhere('receipt_number', 'like', "%{$search}%")
                ->orWhereHas('tenant', fn (Builder $t) => $t->where('full_name', 'like', "%{$search}%"))))
            ->when($status !== null, fn (Builder $q) => $q->where('status', $status->value))
            ->when($method !== null, fn (Builder $q) => $q->where('method', $method->value))
            ->when($proofs, fn (Builder $q) => $q->where('method', PaymentMethod::BankTransfer->value)->where('status', PaymentStatus::Pending->value));
        [$query, $sort, $direction] = $this->applySort($query, $request, ['date' => 'created_at', 'amount' => 'amount'], 'date', 'desc');

        return view('admin.payments.index', [
            'payments' => $query->paginate(config('rentora.per_page'))->withQueryString(),
            'search' => $search,
            'status' => $status,
            'proofs' => $proofs,
            'statuses' => PaymentStatus::cases(),
            'sort' => $sort,
            'direction' => $direction,
            'pendingProofs' => Payment::query()->where('method', PaymentMethod::BankTransfer->value)->where('status', PaymentStatus::Pending->value)->count(),
        ]);
    }

    public function create(Request $request): View
    {
        $this->authorize('create', Payment::class);
        $tenantId = $request->integer('tenant') ?: null;

        return view('admin.payments.create', [
            'tenants' => Tenant::query()->orderBy('full_name')->pluck('full_name', 'id')->all(),
            'tenantId' => $tenantId,
            'openInvoices' => $tenantId !== null
                ? Invoice::query()->where('tenant_id', $tenantId)->open()->orderBy('due_date')->get()
                : collect(),
            'methods' => collect(PaymentMethod::cases())->reject(fn (PaymentMethod $m) => $m === PaymentMethod::Paystack)
                ->mapWithKeys(fn (PaymentMethod $m) => [$m->value => $m->label()])->all(),
            'currencySymbol' => Money::forOrganization(0)->symbol(),
        ]);
    }

    public function store(RecordPaymentRequest $request, RecordManualPayment $record): RedirectResponse
    {
        $payment = $record->handle(
            Tenant::query()->findOrFail($request->integer('tenant_id')),
            $request->user(),
            PaymentMethod::from((string) $request->validated('method')),
            (int) $request->validated('amount'),
            Carbon::parse((string) $request->validated('paid_at')),
            $request->validated('note'),
            $request->file('proof'),
            array_map('intval', (array) $request->validated('invoice_ids', [])),
        );

        return redirect()->route('app.payments.show', $payment)->with('toast', __('payments.recorded', ['receipt' => $payment->receipt_number]));
    }

    public function show(Payment $payment): View
    {
        $this->authorize('view', $payment);

        $payment->load(['tenant', 'lease.unit', 'allocations.invoice']);

        return view('admin.payments.show', ['payment' => $payment]);
    }

    public function approve(Request $request, Payment $payment, TransferProofs $proofs): RedirectResponse
    {
        $this->authorize('approve', $payment);

        $proofs->approve($payment, $request->user());

        return back()->with('toast', __('payments.approved'));
    }

    public function reject(PaymentReasonRequest $request, Payment $payment, TransferProofs $proofs): RedirectResponse
    {
        $proofs->reject($payment, $request->user(), (string) $request->validated('reason'));

        return back()->with('toast', __('payments.rejected'));
    }

    public function reverse(PaymentReasonRequest $request, Payment $payment, ReversePayment $reverse): RedirectResponse
    {
        $reverse->handle($payment, $request->user(), (string) $request->validated('reason'));

        return redirect()->route('app.payments.show', $payment)->with('toast', __('payments.reversed'));
    }

    public function check(Payment $payment, PaymentVerifier $verifier): RedirectResponse
    {
        $this->authorize('view', $payment);
        abort_unless($payment->method === PaymentMethod::Paystack, 404);

        $payment = $verifier->verify($payment);

        return back()->with('toast', __('payments.status_now', ['status' => $payment->status->label()]));
    }

    public function receipt(Payment $payment, ReceiptRenderer $receipts): Response
    {
        $this->authorize('view', $payment);

        return $receipts->download($payment);
    }

    public function proof(Payment $payment): Response
    {
        $this->authorize('view', $payment);
        $disk = Storage::disk(PrivateFiles::DISK);
        abort_unless($payment->proof_path !== null && $disk->exists($payment->proof_path), 404);

        return $disk->download($payment->proof_path, 'proof-'.$payment->reference.'.'.pathinfo($payment->proof_path, PATHINFO_EXTENSION), [
            'Cache-Control' => 'private, no-store',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
