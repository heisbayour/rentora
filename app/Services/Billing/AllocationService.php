<?php

declare(strict_types=1);

namespace App\Services\Billing;

use App\Enums\PaymentStatus;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\PaymentAllocation;
use App\Models\Tenant;
use Illuminate\Support\Facades\DB;

/**
 * Applies money to invoices (spec 8.6). Default order is oldest due invoice
 * first; chosen invoices go first when given. Whatever is left becomes the
 * tenant's credit_balance, which is always the unallocated part of their
 * successful payments, so it can be recomputed at any time.
 */
class AllocationService
{
    /**
     * Allocates a successful payment. Returns the minor units left over as credit.
     *
     * @param  list<int>  $preferredInvoiceIds
     */
    public function allocatePayment(Payment $payment, array $preferredInvoiceIds = []): int
    {
        return DB::transaction(function () use ($payment, $preferredInvoiceIds): int {
            $payment = Payment::query()->withoutGlobalScopes()->lockForUpdate()->findOrFail($payment->getKey());
            $remaining = $payment->amount - (int) $payment->allocations()->sum('amount');

            foreach ($this->openInvoices($payment->tenant_id, $preferredInvoiceIds) as $invoice) {
                if ($remaining <= 0) {
                    break;
                }
                $remaining -= $this->allocate($payment, $invoice, $remaining);
            }

            $this->recalculateCredit($payment->tenant_id);

            return max(0, $remaining);
        });
    }

    /** Uses the tenant's credit (unallocated payments, oldest first) on a new invoice. */
    public function applyCredit(Invoice $invoice): int
    {
        return DB::transaction(function () use ($invoice): int {
            Tenant::query()->withoutGlobalScopes()->lockForUpdate()->findOrFail($invoice->tenant_id);
            $invoice = Invoice::query()->withoutGlobalScopes()->lockForUpdate()->findOrFail($invoice->getKey());
            $applied = 0;

            $payments = Payment::query()->withoutGlobalScopes()
                ->where('tenant_id', $invoice->tenant_id)
                ->where('status', PaymentStatus::Successful->value)
                ->withSum('allocations as allocated_sum', 'amount')
                ->orderBy('paid_at')->orderBy('id')
                ->lockForUpdate()
                ->get();

            foreach ($payments as $payment) {
                $available = $payment->unallocatedMinor();
                if ($available <= 0) {
                    continue;
                }
                if ($invoice->outstandingMinor() <= 0) {
                    break;
                }
                $applied += $this->allocate($payment, $invoice, $available);
            }

            $this->recalculateCredit($invoice->tenant_id);

            return $applied;
        });
    }

    /** Removes every allocation of a payment and restores the invoices (used by reversal). */
    public function deallocatePayment(Payment $payment): void
    {
        DB::transaction(function () use ($payment): void {
            $allocations = PaymentAllocation::query()->where('payment_id', $payment->getKey())->lockForUpdate()->get();
            foreach ($allocations as $allocation) {
                $invoice = Invoice::query()->withoutGlobalScopes()->lockForUpdate()->findOrFail($allocation->invoice_id);
                $invoice->amount_paid = max(0, $invoice->amount_paid - $allocation->amount);
                $invoice->refreshStatus();
                $invoice->save();
                $allocation->delete();
            }
            $this->recalculateCredit($payment->tenant_id);
        });
    }

    public function recalculateCredit(int $tenantId): int
    {
        $paid = (int) Payment::query()->withoutGlobalScopes()
            ->where('tenant_id', $tenantId)->where('status', PaymentStatus::Successful->value)->sum('amount');
        $allocated = (int) PaymentAllocation::query()
            ->whereIn('payment_id', Payment::query()->withoutGlobalScopes()
                ->where('tenant_id', $tenantId)->where('status', PaymentStatus::Successful->value)->select('id'))
            ->sum('amount');
        $credit = max(0, $paid - $allocated);

        Tenant::query()->withoutGlobalScopes()->whereKey($tenantId)->update(['credit_balance' => $credit]);

        return $credit;
    }

    /** Allocates up to $available to one invoice; returns the amount used. Caller holds the locks. */
    private function allocate(Payment $payment, Invoice $invoice, int $available): int
    {
        $amount = min($available, $invoice->outstandingMinor());
        if ($amount <= 0) {
            return 0;
        }

        $existing = PaymentAllocation::query()->where('payment_id', $payment->getKey())->where('invoice_id', $invoice->getKey())->first();
        if ($existing !== null) {
            $existing->increment('amount', $amount);
        } else {
            PaymentAllocation::query()->create(['payment_id' => $payment->getKey(), 'invoice_id' => $invoice->getKey(), 'amount' => $amount]);
        }

        $invoice->amount_paid += $amount;
        $invoice->refreshStatus();
        $invoice->save();

        return $amount;
    }

    /**
     * @param  list<int>  $preferredIds
     * @return list<Invoice>
     */
    private function openInvoices(int $tenantId, array $preferredIds): array
    {
        $invoices = Invoice::query()->withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->open()
            ->orderBy('due_date')->orderBy('id')
            ->lockForUpdate()
            ->get();

        $preferred = $invoices->filter(fn (Invoice $i) => in_array($i->id, $preferredIds, true));
        $rest = $invoices->reject(fn (Invoice $i) => in_array($i->id, $preferredIds, true));

        return [...$preferred->values()->all(), ...$rest->values()->all()];
    }
}
