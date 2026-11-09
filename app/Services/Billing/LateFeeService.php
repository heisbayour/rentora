<?php

declare(strict_types=1);

namespace App\Services\Billing;

use App\Enums\InvoiceType;
use App\Enums\LateFeeType;
use App\Models\Invoice;
use App\Models\Organization;
use App\Support\CurrentOrganization;
use App\Support\Money;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

/**
 * Daily late fees (spec 8.5): once due_date + grace_days has passed and the
 * invoice is still unpaid or part paid, a single late_fee invoice is linked to
 * it by parent_invoice_id. A unique index on parent_invoice_id guarantees one.
 * Percentage fees are taken on the amount still outstanding.
 */
class LateFeeService
{
    public function __construct(
        private readonly InvoiceIssuer $issuer,
        private readonly CurrentOrganization $current,
    ) {}

    public function run(?CarbonInterface $today = null): int
    {
        $today = CarbonImmutable::parse(($today ?? today())->toDateString());
        $created = 0;

        foreach (Organization::query()->get() as $organization) {
            $created += $this->current->runAs($organization, function () use ($today): int {
                $count = 0;
                Invoice::query()->open()
                    ->whereNotIn('type', [InvoiceType::LateFee->value, InvoiceType::Deposit->value])
                    ->whereNotNull('lease_id')
                    ->whereDoesntHave('lateFee')
                    ->whereDate('due_date', '<', $today->toDateString())
                    ->with(['lease', 'tenant'])
                    ->chunkById(100, function ($invoices) use (&$count, $today): void {
                        foreach ($invoices as $invoice) {
                            $count += $this->forInvoice($invoice, $today);
                        }
                    });

                return $count;
            });
        }

        return $created;
    }

    public function forInvoice(Invoice $invoice, CarbonImmutable $today): int
    {
        $lease = $invoice->lease;
        if ($lease === null || $lease->late_fee_type === LateFeeType::None) {
            return 0;
        }
        if (! $today->greaterThan($invoice->due_date->copy()->addDays($lease->grace_days)->startOfDay())) {
            return 0;
        }

        $amount = $lease->late_fee_type === LateFeeType::Fixed
            ? $lease->late_fee_value
            : Money::of($invoice->outstandingMinor())->percentage($lease->late_fee_value)->minor;
        if ($amount <= 0 || $invoice->outstandingMinor() <= 0) {
            return 0;
        }

        try {
            DB::transaction(function () use ($invoice, $amount, $today): void {
                $locked = Invoice::query()->lockForUpdate()->findOrFail($invoice->getKey());
                if ($locked->lateFee()->exists()) {
                    return;
                }
                $this->issuer->issue($invoice->tenant, InvoiceType::LateFee, $amount, $today, [
                    'lease' => $invoice->lease,
                    'parent_invoice_id' => $invoice->getKey(),
                    'description' => __('invoices.descriptions.late_fee', ['number' => $invoice->number]),
                ]);
            });
        } catch (QueryException) {
            return 0;
        }

        return $invoice->lateFee()->exists() ? 1 : 0;
    }
}
