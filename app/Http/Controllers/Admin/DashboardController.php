<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Enums\MaintenancePriority;
use App\Enums\MaintenanceStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Http\Controllers\Controller;
use App\Models\Invoice;
use App\Models\MaintenanceRequest;
use App\Models\Payment;
use App\Services\Reports\ArrearsAging;
use App\Services\Reports\DashboardMetrics;
use App\Support\Money;
use Illuminate\Http\Request;
use Illuminate\View\View;

/** Landlord dashboard (spec 8.2). Caretakers see the maintenance widgets only. */
class DashboardController extends Controller
{
    public function __invoke(Request $request, DashboardMetrics $metrics, ArrearsAging $aging): View
    {
        $money = $request->user()->can('manage-money');
        $repairs = MaintenanceRequest::query()->with('unit.property')->latest('id')->limit(5)->get();
        $openRepairs = MaintenanceRequest::query()->whereIn('status', MaintenanceStatus::openValues());

        if (! $money) {
            return view('admin.dashboard', [
                'money' => false,
                'repairs' => $repairs,
                'openRepairs' => (clone $openRepairs)->count(),
                'emergencies' => (clone $openRepairs)->where('priority', MaintenancePriority::Emergency->value)->count(),
            ]);
        }

        $kpis = $metrics->kpis();
        $agingTotals = $aging->build()['totals'];

        return view('admin.dashboard', [
            'money' => true,
            'kpis' => $kpis,
            'format' => fn (int $minor): string => Money::forOrganization($minor)->format(),
            'collections' => $metrics->collectionsChart(),
            'aging' => [
                'labels' => array_map(fn (string $bucket) => __('reports.aging.buckets.'.$bucket), ArrearsAging::BUCKETS),
                'values' => array_values($agingTotals),
            ],
            'pendingProofs' => Payment::query()->with('tenant')
                ->where('method', PaymentMethod::BankTransfer->value)
                ->where('status', PaymentStatus::Pending->value)
                ->oldest('id')->limit(5)->get(),
            'recentPayments' => Payment::query()->with('tenant')
                ->where('status', PaymentStatus::Successful->value)
                ->latest('paid_at')->limit(5)->get(),
            'upcoming' => Invoice::query()->open()->with('tenant')
                ->whereDate('due_date', '>=', today()->toDateString())
                ->whereDate('due_date', '<=', today()->addDays(7)->toDateString())
                ->orderBy('due_date')->limit(5)->get(),
            'repairs' => $repairs,
            'openRepairs' => $kpis['open_repairs'],
            'emergencies' => $kpis['emergencies'],
        ]);
    }
}
