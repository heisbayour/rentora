<?php

declare(strict_types=1);

namespace App\Services\Billing;

use App\Enums\InvoiceType;
use App\Models\Invoice;
use App\Models\Lease;
use App\Models\Tenant;
use App\Services\ActivityLogger;
use App\Services\SequenceService;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;

/**
 * The one place invoices are created: numbers them, then applies the
 * tenant's credit balance (spec 8.5 "credit balance auto-applied").
 */
class InvoiceIssuer
{
    public function __construct(
        private readonly SequenceService $sequences,
        private readonly AllocationService $allocations,
        private readonly ActivityLogger $logger,
    ) {}

    /**
     * @param  array{description?: ?string, period_start?: ?CarbonInterface, period_end?: ?CarbonInterface, parent_invoice_id?: ?int, lease?: ?Lease}  $details
     */
    public function issue(Tenant $tenant, InvoiceType $type, int $amount, CarbonInterface $dueDate, array $details = []): Invoice
    {
        $lease = $details['lease'] ?? null;

        $invoice = DB::transaction(function () use ($tenant, $type, $amount, $dueDate, $details, $lease): Invoice {
            $invoice = new Invoice([
                'number' => $this->sequences->next(SequenceService::INVOICE, now(), $tenant->organization_id),
                'lease_id' => $lease?->getKey(),
                'tenant_id' => $tenant->getKey(),
                'unit_id' => $lease?->unit_id,
                'type' => $type,
                'description' => $details['description'] ?? null,
                'period_start' => $details['period_start'] ?? null,
                'period_end' => $details['period_end'] ?? null,
                'amount' => $amount,
                'due_date' => $dueDate,
                'parent_invoice_id' => $details['parent_invoice_id'] ?? null,
                'issued_at' => now(),
            ]);
            $invoice->organization_id = $tenant->organization_id;
            $invoice->save();

            return $invoice;
        });

        $this->allocations->applyCredit($invoice);
        $this->logger->log('invoice.issued', $invoice, ['number' => $invoice->number, 'amount' => $amount]);

        return $invoice->refresh();
    }
}
