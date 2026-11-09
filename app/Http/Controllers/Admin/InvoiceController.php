<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Actions\Billing\VoidInvoice;
use App\Enums\InvoiceStatus;
use App\Enums\InvoiceType;
use App\Http\Controllers\Concerns\SortsLists;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ManualInvoiceRequest;
use App\Http\Requests\Admin\VoidInvoiceRequest;
use App\Models\Invoice;
use App\Models\Lease;
use App\Models\Tenant;
use App\Services\Billing\InvoiceIssuer;
use App\Support\Money;
use App\Support\Pdf;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;

class InvoiceController extends Controller
{
    use SortsLists;

    public function index(Request $request): View
    {
        $this->authorize('viewAny', Invoice::class);

        $search = trim((string) $request->query('q', ''));
        $status = InvoiceStatus::tryFrom((string) $request->query('status'));
        $type = InvoiceType::tryFrom((string) $request->query('type'));
        $overdue = $request->query('overdue') === '1';

        $query = Invoice::query()->with(['tenant', 'unit'])
            ->when($search !== '', fn (Builder $q) => $q->where(fn (Builder $w) => $w
                ->where('number', 'like', "%{$search}%")
                ->orWhereHas('tenant', fn (Builder $t) => $t->where('full_name', 'like', "%{$search}%"))))
            ->when($status !== null, fn (Builder $q) => $q->where('status', $status->value))
            ->when($type !== null, fn (Builder $q) => $q->where('type', $type->value))
            ->when($overdue, fn (Builder $q) => $q->open()->whereDate('due_date', '<', today()));
        [$query, $sort, $direction] = $this->applySort($query, $request, ['number' => 'number', 'due' => 'due_date', 'amount' => 'amount'], 'due', 'desc');

        return view('admin.invoices.index', [
            'invoices' => $query->paginate(config('rentora.per_page'))->withQueryString(),
            'search' => $search,
            'status' => $status,
            'type' => $type,
            'overdue' => $overdue,
            'statuses' => InvoiceStatus::cases(),
            'sort' => $sort,
            'direction' => $direction,
        ]);
    }

    public function create(Request $request): View
    {
        $this->authorize('create', Invoice::class);

        return view('admin.invoices.create', [
            'tenants' => Tenant::query()->orderBy('full_name')->pluck('full_name', 'id')->all(),
            'leases' => Lease::query()->with('unit')->whereIn('status', ['active', 'draft'])->get()
                ->mapWithKeys(fn (Lease $l) => [$l->id => $l->unit->label.' ('.$l->status->label().')'])->all(),
            'types' => collect(InvoiceType::manual())->mapWithKeys(fn (InvoiceType $t) => [$t->value => $t->label()])->all(),
            'tenantId' => $request->integer('tenant') ?: null,
            'currencySymbol' => Money::forOrganization(0)->symbol(),
        ]);
    }

    public function store(ManualInvoiceRequest $request, InvoiceIssuer $issuer): RedirectResponse
    {
        $tenant = Tenant::query()->findOrFail($request->integer('tenant_id'));
        $lease = $request->filled('lease_id') ? Lease::query()->findOrFail($request->integer('lease_id')) : null;

        $invoice = $issuer->issue($tenant, InvoiceType::from((string) $request->validated('type')), (int) $request->validated('amount'),
            Carbon::parse((string) $request->validated('due_date')), [
                'lease' => $lease,
                'description' => (string) $request->validated('description'),
            ]);

        return redirect()->route('app.invoices.show', $invoice)->with('toast', __('invoices.created', ['number' => $invoice->number]));
    }

    public function show(Invoice $invoice): View
    {
        $this->authorize('view', $invoice);

        $invoice->load(['tenant', 'unit.property', 'lease', 'parent', 'lateFee', 'allocations.payment']);

        return view('admin.invoices.show', ['invoice' => $invoice]);
    }

    public function void(VoidInvoiceRequest $request, Invoice $invoice, VoidInvoice $void): RedirectResponse
    {
        $void->handle($invoice, (string) $request->validated('reason'));

        return redirect()->route('app.invoices.show', $invoice)->with('toast', __('invoices.voided', ['number' => $invoice->number]));
    }

    public function pdf(Invoice $invoice, Pdf $pdf): Response
    {
        $this->authorize('view', $invoice);

        $invoice->load(['tenant', 'unit.property', 'allocations.payment']);

        return $pdf->download('pdf.invoice', ['invoice' => $invoice], $invoice->number.'.pdf');
    }
}
