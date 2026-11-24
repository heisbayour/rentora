<?php

declare(strict_types=1);

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Models\Invoice;
use App\Support\Pdf;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;

/** The tenant's own invoices (spec 8.13). */
class InvoiceController extends Controller
{
    public function index(Request $request): View
    {
        $tenant = $request->user()->tenant ?? abort(404);

        return view('portal.invoices.index', [
            'invoices' => $tenant->invoices()->latest('due_date')->latest('id')->paginate((int) config('rentora.per_page')),
        ]);
    }

    public function show(Invoice $invoice): View
    {
        $this->authorize('view', $invoice);
        $invoice->load(['unit.property', 'allocations.payment']);

        return view('portal.invoices.show', ['invoice' => $invoice]);
    }

    public function pdf(Invoice $invoice, Pdf $pdf): Response
    {
        $this->authorize('view', $invoice);
        $invoice->load(['tenant', 'unit.property', 'allocations.payment']);

        return $pdf->download('pdf.invoice', ['invoice' => $invoice], $invoice->number.'.pdf');
    }
}
