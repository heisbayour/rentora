<?php

declare(strict_types=1);

namespace App\Actions\Billing;

use App\Enums\DepositStatus;
use App\Enums\InvoiceType;
use App\Models\Invoice;
use App\Models\Lease;
use App\Services\Billing\InvoiceGenerator;
use App\Services\Billing\InvoiceIssuer;

/**
 * Optional first invoices at activation (spec 8.5): rent (and service charge)
 * for the first period regardless of lead time, plus the deposit if unpaid.
 */
class IssueInitialInvoices
{
    public function __construct(
        private readonly InvoiceGenerator $generator,
        private readonly InvoiceIssuer $issuer,
    ) {}

    public function handle(Lease $lease): int
    {
        $lease->loadMissing('tenant');
        $created = $this->generator->forLease($lease, ignoreLeadTime: true, limit: 1);

        $hasDeposit = Invoice::query()->where('lease_id', $lease->getKey())->where('type', InvoiceType::Deposit->value)->exists();
        if ($lease->deposit_amount > 0 && $lease->deposit_status === DepositStatus::Unpaid && ! $hasDeposit) {
            $this->issuer->issue($lease->tenant, InvoiceType::Deposit, $lease->deposit_amount, $lease->start_date, [
                'lease' => $lease,
                'description' => __('invoices.descriptions.deposit'),
            ]);
            $created++;
        }

        return $created;
    }
}
