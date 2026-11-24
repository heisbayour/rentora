<?php

declare(strict_types=1);

namespace App\Services\Reports;

use App\Models\Invoice;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;

/**
 * Arrears aging (spec 8.10 report 2, dashboard donut): what is still owed on
 * invoices past their due date, by days overdue: 0 to 30, 31 to 60, 61 to 90
 * and over 90. Amounts are minor units.
 */
class ArrearsAging
{
    public const BUCKETS = ['0_30', '31_60', '61_90', '90_plus'];

    public static function bucketFor(int $daysOverdue): string
    {
        return match (true) {
            $daysOverdue <= 30 => '0_30',
            $daysOverdue <= 60 => '31_60',
            $daysOverdue <= 90 => '61_90',
            default => '90_plus',
        };
    }

    /**
     * @return array{rows: list<array{tenant_id: int, tenant: string, unit: string, buckets: array<string, int>, total: int}>, totals: array<string, int>, total: int}
     */
    public function build(?CarbonInterface $today = null, ?int $propertyId = null): array
    {
        $today = CarbonImmutable::parse(($today ?? today())->toDateString());
        $empty = array_fill_keys(self::BUCKETS, 0);
        $rows = [];

        $invoices = Invoice::query()->open()
            ->whereDate('due_date', '<', $today->toDateString())
            ->when($propertyId !== null, fn (Builder $q) => $q->whereHas('unit', fn (Builder $u) => $u->where('property_id', $propertyId)))
            ->with(['tenant', 'unit.property'])
            ->orderBy('tenant_id')
            ->get();

        foreach ($invoices as $invoice) {
            $owed = $invoice->outstandingMinor();
            if ($owed <= 0) {
                continue;
            }
            $days = (int) CarbonImmutable::parse($invoice->due_date->toDateString())->diffInDays($today);
            $bucket = self::bucketFor($days);
            $key = $invoice->tenant_id;
            $rows[$key] ??= [
                'tenant_id' => $invoice->tenant_id,
                'tenant' => $invoice->tenant->full_name,
                'unit' => $invoice->unit?->displayName() ?? '',
                'buckets' => $empty,
                'total' => 0,
            ];
            $rows[$key]['buckets'][$bucket] += $owed;
            $rows[$key]['total'] += $owed;
        }

        $rows = array_values($rows);
        usort($rows, fn (array $a, array $b) => $b['total'] <=> $a['total']);

        $totals = $empty;
        foreach ($rows as $row) {
            foreach ($row['buckets'] as $bucket => $amount) {
                $totals[$bucket] += $amount;
            }
        }

        return ['rows' => $rows, 'totals' => $totals, 'total' => array_sum($totals)];
    }
}
