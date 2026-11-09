<?php

declare(strict_types=1);

namespace App\Services\Billing;

use App\Enums\InvoiceStatus;
use App\Enums\PaymentStatus;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Tenant;
use Carbon\CarbonInterface;

/**
 * Tenant statement for a date range (spec 8.6): opening balance, invoices
 * (charges) and successful payments in date order with a running balance,
 * closing balance. Invoices count on their issue date, payments on paid_at.
 * A positive balance is owed by the tenant; negative is credit.
 */
class StatementBuilder
{
    /**
     * @return array{opening: int, closing: int, charges: int, payments: int, lines: list<array{date: CarbonInterface, description: string, reference: string, charge: int, payment: int, balance: int}>}
     */
    public function build(Tenant $tenant, CarbonInterface $from, CarbonInterface $to): array
    {
        $from = $from->copy()->startOfDay();
        $to = $to->copy()->endOfDay();

        $invoices = Invoice::query()->where('tenant_id', $tenant->getKey())
            ->where('status', '!=', InvoiceStatus::Void->value);
        $payments = Payment::query()->where('tenant_id', $tenant->getKey())
            ->where('status', PaymentStatus::Successful->value);

        $opening = (int) (clone $invoices)->where('issued_at', '<', $from)->sum('amount')
            - (int) (clone $payments)->where('paid_at', '<', $from)->sum('amount');

        // Each entry: [sort key, charge, payment, date, description, reference]. Charges sort before payments on the same day.
        $entries = [];
        foreach ((clone $invoices)->whereBetween('issued_at', [$from, $to])->get() as $invoice) {
            $date = $invoice->issued_at ?? $invoice->created_at ?? $from;
            $entries[] = [$date->getTimestamp() * 2, $invoice->amount, 0, $date, $invoice->type->label().': '.$invoice->description, $invoice->number];
        }
        foreach ((clone $payments)->whereBetween('paid_at', [$from, $to])->get() as $payment) {
            $date = $payment->paid_at ?? $from;
            $entries[] = [$date->getTimestamp() * 2 + 1, 0, $payment->amount, $date, $payment->method->label(), $payment->receipt_number ?? $payment->reference];
        }
        usort($entries, fn (array $a, array $b) => $a[0] <=> $b[0]);

        $balance = $opening;
        $charges = 0;
        $paid = 0;
        $lines = [];
        foreach ($entries as [, $charge, $payment, $date, $description, $reference]) {
            $balance += $charge - $payment;
            $charges += $charge;
            $paid += $payment;
            $lines[] = ['date' => $date, 'description' => $description, 'reference' => $reference, 'charge' => $charge, 'payment' => $payment, 'balance' => $balance];
        }

        return ['opening' => $opening, 'closing' => $balance, 'charges' => $charges, 'payments' => $paid, 'lines' => $lines];
    }
}
