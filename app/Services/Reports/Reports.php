<?php

declare(strict_types=1);

namespace App\Services\Reports;

use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Unit;
use App\Support\Money;
use Carbon\CarbonImmutable;
use Generator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

/**
 * The v1 reports (spec 8.10, FINISH_BUILD Section 4): rent roll, arrears
 * aging and collections. Rows are generators so CSV exports stream without
 * loading everything at once. Money cells are Money objects; the screen
 * formats them and the CSV writes plain decimals.
 */
class Reports
{
    public const KEYS = ['rent_roll', 'arrears', 'collections'];

    public function __construct(private readonly ArrearsAging $aging) {}

    /** @return list<string> */
    public function columns(string $report): array
    {
        $keys = match ($report) {
            'rent_roll' => ['property', 'unit', 'status', 'tenant', 'rent', 'frequency', 'lease_end', 'balance'],
            'arrears' => ['tenant', 'unit', 'b0_30', 'b31_60', 'b61_90', 'b90_plus', 'total'],
            'collections' => ['date', 'receipt', 'tenant', 'property', 'method', 'amount'],
            default => abort(404),
        };

        return array_map(fn (string $key) => __('reports.columns.'.$key), $keys);
    }

    /**
     * @param  array{property_id: ?int, from: CarbonImmutable, to: CarbonImmutable}  $filters
     * @return Generator<int, list<string|Money|null>>
     */
    public function rows(string $report, array $filters): Generator
    {
        return match ($report) {
            'rent_roll' => $this->rentRoll($filters),
            'arrears' => $this->arrears($filters),
            'collections' => $this->collections($filters),
            default => abort(404),
        };
    }

    /**
     * Footer totals for the screen.
     *
     * @param  array{property_id: ?int, from: CarbonImmutable, to: CarbonImmutable}  $filters
     * @return array<string, Money>
     */
    public function summary(string $report, array $filters): array
    {
        if ($report === 'arrears') {
            $aging = $this->aging->build(null, $filters['property_id']);
            $summary = [];
            foreach ($aging['totals'] as $bucket => $amount) {
                $summary[__('reports.aging.buckets.'.$bucket)] = Money::forOrganization($amount);
            }
            $summary[__('reports.columns.total')] = Money::forOrganization($aging['total']);

            return $summary;
        }

        if ($report === 'collections') {
            $summary = [];
            $byMethod = $this->collectionsQuery($filters)->select('method', DB::raw('sum(amount) as total'))->groupBy('method')->pluck('total', 'method');
            foreach ($byMethod as $method => $total) {
                $summary[PaymentMethod::from((string) $method)->label()] = Money::forOrganization((int) $total);
            }
            $summary[__('reports.columns.total')] = Money::forOrganization((int) $byMethod->sum());

            return $summary;
        }

        return [];
    }

    /** @param  array{property_id: ?int}  $filters */
    private function rentRoll(array $filters): Generator
    {
        $units = Unit::query()
            ->with(['property', 'activeLease.tenant'])
            ->when($filters['property_id'] !== null, fn (Builder $q) => $q->where('property_id', $filters['property_id']))
            ->orderBy('property_id')->orderBy('label');

        foreach ($units->lazy(200) as $unit) {
            $lease = $unit->activeLease;
            $balance = $lease === null ? null : Money::forOrganization((int) Invoice::query()->open()
                ->where('tenant_id', $lease->tenant_id)->sum(DB::raw('amount - amount_paid')));

            yield [
                $unit->property->name,
                $unit->label,
                $unit->status->label(),
                $lease?->tenant->full_name,
                $lease?->rent() ?? $unit->rent(),
                ($lease->rent_frequency ?? $unit->rent_frequency)->label(),
                $lease?->end_date->format('j M Y'),
                $balance,
            ];
        }
    }

    /** @param  array{property_id: ?int}  $filters */
    private function arrears(array $filters): Generator
    {
        foreach ($this->aging->build(null, $filters['property_id'])['rows'] as $row) {
            yield [
                $row['tenant'],
                $row['unit'],
                ...array_map(fn (int $amount) => Money::forOrganization($amount), array_values($row['buckets'])),
                Money::forOrganization($row['total']),
            ];
        }
    }

    /** @param  array{property_id: ?int, from: CarbonImmutable, to: CarbonImmutable}  $filters */
    private function collections(array $filters): Generator
    {
        $payments = $this->collectionsQuery($filters)->with(['tenant', 'lease.unit.property'])->orderBy('paid_at');

        foreach ($payments->lazy(200) as $payment) {
            yield [
                $payment->paid_at?->format('j M Y'),
                $payment->receipt_number,
                $payment->tenant->full_name,
                $payment->lease?->unit->property->name,
                $payment->method->label(),
                $payment->money(),
            ];
        }
    }

    /**
     * @param  array{property_id: ?int, from: CarbonImmutable, to: CarbonImmutable}  $filters
     * @return Builder<Payment>
     */
    private function collectionsQuery(array $filters): Builder
    {
        return Payment::query()
            ->where('status', PaymentStatus::Successful->value)
            ->whereBetween('paid_at', [$filters['from']->startOfDay()->utc(), $filters['to']->endOfDay()->utc()])
            ->when($filters['property_id'] !== null, fn (Builder $q) => $q->whereHas('lease.unit', fn (Builder $u) => $u->where('property_id', $filters['property_id'])));
    }
}
