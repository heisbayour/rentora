<?php

declare(strict_types=1);

namespace App\Services\Scheduling;

use App\Actions\Leases\EndLease;
use App\Models\Lease;
use App\Models\Organization;
use App\Models\OutboundMessage;
use App\Services\Billing\InvoiceGenerator;
use App\Services\Billing\LateFeeService;
use App\Services\Maintenance\MaintenanceWorkflow;
use App\Services\Messaging\LeaseExpiryAlerts;
use App\Services\Messaging\Outbox;
use App\Services\Messaging\RentReminders;
use App\Services\Payments\PaymentReconciler;
use App\Support\CurrentOrganization;
use Illuminate\Contracts\Container\Container;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

/**
 * The scheduler's tasks and how often each runs (spec 10.1). Frequencies:
 * "tick" (every tick), "minutes:N", "daily" (optionally after an hour of the
 * day in the organization timezone) and "weekly".
 */
class TaskRegistry
{
    public function __construct(private readonly Container $container) {}

    /** @return array<string, array{every: string, after_hour?: int, run: callable(float): mixed}> */
    public function tasks(): array
    {
        $c = $this->container;

        return [
            'generate_invoices' => ['every' => 'daily', 'after_hour' => 6, 'run' => fn () => $c->make(InvoiceGenerator::class)->run()],
            'apply_late_fees' => ['every' => 'daily', 'run' => fn () => $c->make(LateFeeService::class)->run()],
            'send_rent_reminders' => ['every' => 'daily', 'after_hour' => 9, 'run' => fn () => $c->make(RentReminders::class)->run()],
            'lease_expiry_alerts' => ['every' => 'daily', 'run' => fn () => $c->make(LeaseExpiryAlerts::class)->run()],
            'end_expired_leases' => ['every' => 'daily', 'run' => fn () => $this->endExpiredLeases()],
            'auto_close_repairs' => ['every' => 'daily', 'run' => fn () => $c->make(MaintenanceWorkflow::class)->autoCloseResolved()],
            'reconcile_payments' => ['every' => 'minutes:10', 'run' => fn () => $c->make(PaymentReconciler::class)->run()],
            'prune_old_logs' => ['every' => 'weekly', 'run' => fn () => $this->pruneOldLogs()],
            // Last, so it gets whatever time budget is left.
            'drain_outbox' => ['every' => 'tick', 'run' => fn (float $deadline) => $c->make(Outbox::class)->drain($deadline)],
        ];
    }

    /** Active leases past their end date become ended; a lined-up renewal is activated (spec 10.1). */
    private function endExpiredLeases(): int
    {
        $current = $this->container->make(CurrentOrganization::class);
        $end = $this->container->make(EndLease::class);
        $ended = 0;

        foreach (Organization::query()->get() as $organization) {
            $current->runAs($organization, function () use ($end, &$ended): void {
                foreach (Lease::query()->active()->whereDate('end_date', '<', today()->toDateString())->get() as $lease) {
                    try {
                        $end->handle($lease);
                        $ended++;
                    } catch (ValidationException $e) {
                        Log::warning('Could not end expired lease', ['lease' => $lease->getKey(), 'errors' => $e->errors()]);
                    }
                }
            });
        }

        return $ended;
    }

    /** Sent messages older than 90 days are removed (spec 10.1 PruneOldLogs). */
    private function pruneOldLogs(): int
    {
        return OutboundMessage::query()->withoutGlobalScopes()
            ->where('status', 'sent')
            ->where('sent_at', '<', now()->subDays(90))
            ->delete();
    }
}
