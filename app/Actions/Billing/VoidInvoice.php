<?php

declare(strict_types=1);

namespace App\Actions\Billing;

use App\Enums\InvoiceStatus;
use App\Models\Invoice;
use App\Services\ActivityLogger;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Voids an invoice that has no payments allocated (spec 8.5). An unpaid late
 * fee raised on it is voided with it.
 */
class VoidInvoice
{
    public function __construct(private readonly ActivityLogger $logger) {}

    public function handle(Invoice $invoice, string $reason): Invoice
    {
        $invoice = DB::transaction(function () use ($invoice, $reason): Invoice {
            $invoice = Invoice::query()->lockForUpdate()->findOrFail($invoice->getKey());

            if ($invoice->status === InvoiceStatus::Void) {
                throw ValidationException::withMessages(['invoice' => __('invoices.errors.already_void')]);
            }
            if ($invoice->allocations()->exists() || $invoice->amount_paid > 0) {
                throw ValidationException::withMessages(['invoice' => __('invoices.errors.has_payments')]);
            }

            $this->markVoid($invoice, $reason);

            $lateFee = Invoice::query()->where('parent_invoice_id', $invoice->getKey())->lockForUpdate()->first();
            if ($lateFee !== null && $lateFee->status !== InvoiceStatus::Void && ! $lateFee->allocations()->exists()) {
                $this->markVoid($lateFee, $reason);
            }

            return $invoice;
        });

        $this->logger->log('invoice.voided', $invoice, ['number' => $invoice->number, 'reason' => $reason]);

        return $invoice;
    }

    private function markVoid(Invoice $invoice, string $reason): void
    {
        $invoice->forceFill(['status' => InvoiceStatus::Void, 'voided_at' => now(), 'void_reason' => $reason])->save();
    }
}
