<?php

declare(strict_types=1);

namespace App\Services\Messaging;

use App\Models\Invoice;
use App\Models\Organization;
use App\Models\ReminderLog;
use App\Notifications\RentReminder;
use App\Support\CurrentOrganization;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;

/**
 * Daily rent reminders (spec 10.1 SendRentReminders): 7 and 3 days before the
 * due date, on the day, and 3 and 7 days overdue. Each kind is sent at most
 * once per invoice (reminder_logs). A missed day still sends the reminder
 * for the window the invoice is in, so a skipped tick loses nothing.
 */
class RentReminders
{
    public function __construct(
        private readonly CurrentOrganization $current,
        private readonly Notifier $notifier,
    ) {}

    public function run(?CarbonInterface $today = null): int
    {
        $today = CarbonImmutable::parse(($today ?? today())->toDateString());
        $sent = 0;

        foreach (Organization::query()->get() as $organization) {
            $sent += $this->current->runAs($organization, function () use ($today): int {
                $count = 0;
                Invoice::query()->open()
                    ->whereDate('due_date', '>=', $today->subDays(13)->toDateString())
                    ->whereDate('due_date', '<=', $today->addDays(7)->toDateString())
                    ->chunkById(100, function ($invoices) use ($today, &$count): void {
                        foreach ($invoices as $invoice) {
                            $count += $this->forInvoice($invoice, $today) ? 1 : 0;
                        }
                    });

                return $count;
            });
        }

        return $sent;
    }

    /** Days past the due date (negative before it) mapped to a reminder kind, or null. */
    public static function kindFor(int $daysPastDue): ?string
    {
        return match (true) {
            $daysPastDue >= -7 && $daysPastDue < -3 => 'pre_due_7',
            $daysPastDue >= -3 && $daysPastDue < 0 => 'pre_due_3',
            $daysPastDue === 0 => 'due_today',
            $daysPastDue >= 3 && $daysPastDue < 7 => 'overdue_3',
            $daysPastDue >= 7 && $daysPastDue < 14 => 'overdue_7',
            default => null,
        };
    }

    private function forInvoice(Invoice $invoice, CarbonImmutable $today): bool
    {
        $days = (int) CarbonImmutable::parse($invoice->due_date->toDateString())->diffInDays($today, false);
        $kind = self::kindFor($days);
        if ($kind === null || $invoice->outstandingMinor() <= 0 || ! ReminderLog::claim($invoice, $kind)) {
            return false;
        }

        $this->notifier->tenant($invoice->tenant_id, new RentReminder($invoice, $kind, $days));

        return true;
    }
}
