<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Property;
use App\Services\Reports\Reports;
use App\Support\Money;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/** Reports with filters and streamed CSV export (spec 8.10). Owners and managers. */
class ReportController extends Controller
{
    public function index(): View
    {
        return view('admin.reports.index', ['reports' => Reports::KEYS]);
    }

    public function show(Request $request, string $report, Reports $reports): View
    {
        abort_unless(in_array($report, Reports::KEYS, true), 404);
        $filters = $this->filters($request);

        return view('admin.reports.show', [
            'report' => $report,
            'filters' => $filters,
            'properties' => Property::query()->orderBy('name')->pluck('name', 'id'),
            'columns' => $reports->columns($report),
            'rows' => iterator_to_array($reports->rows($report, $filters), false),
            'summary' => $reports->summary($report, $filters),
        ]);
    }

    public function export(Request $request, string $report, Reports $reports): StreamedResponse
    {
        abort_unless(in_array($report, Reports::KEYS, true), 404);
        $filters = $this->filters($request);
        $filename = str_replace('_', '-', $report).'-'.now()->format('Y-m-d').'.csv';

        return response()->streamDownload(function () use ($reports, $report, $filters): void {
            $out = fopen('php://output', 'w');
            if ($out === false) {
                return;
            }
            fputcsv($out, $reports->columns($report));
            foreach ($reports->rows($report, $filters) as $row) {
                fputcsv($out, array_map(fn ($cell) => $cell instanceof Money ? $cell->toDecimalString() : (string) $cell, $row));
            }
            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /** @return array{property_id: ?int, from: CarbonImmutable, to: CarbonImmutable} */
    private function filters(Request $request): array
    {
        $data = $request->validate([
            'property_id' => ['nullable', 'integer', Rule::exists('properties', 'id')->where('organization_id', $request->user()->organization_id)],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
        ]);
        $timezone = (string) config('rentora.defaults.timezone');
        $now = CarbonImmutable::now($timezone);

        return [
            'property_id' => isset($data['property_id']) ? (int) $data['property_id'] : null,
            'from' => isset($data['from']) ? CarbonImmutable::parse($data['from'], $timezone) : $now->startOfMonth(),
            'to' => isset($data['to']) ? CarbonImmutable::parse($data['to'], $timezone) : $now,
        ];
    }
}
