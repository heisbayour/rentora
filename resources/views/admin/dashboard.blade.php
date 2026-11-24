<x-layouts::admin :title="__('dashboard.title')">
    <x-page-header :title="__('dashboard.greeting', ['name' => auth()->user()->name])" :description="__('dashboard.intro')" />

    @if ($money)
        <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
            <x-stat-card :label="__('dashboard.kpi.expected')" :value="$format($kpis['expected'])" icon="document-currency-dollar" :href="route('app.invoices.index')" />
            <x-stat-card :label="__('dashboard.kpi.collected')" :value="$format($kpis['collected'])" icon="banknotes" :href="route('app.payments.index')" />
            <x-stat-card :label="__('dashboard.kpi.collection_rate')" :value="$kpis['collection_rate'] === null ? '-' : $kpis['collection_rate'].'%'" icon="chart-pie" />
            <x-stat-card :label="__('dashboard.kpi.arrears')" :value="$format($kpis['arrears'])" icon="exclamation-triangle" :href="route('app.reports.show', 'arrears')" />
            <x-stat-card :label="__('dashboard.kpi.occupancy')" :value="$kpis['occupancy'] === null ? '-' : $kpis['occupancy'].'%'" :delta="__('dashboard.kpi.occupied_of', ['occupied' => $kpis['occupied'], 'total' => $kpis['units']])" icon="building-office-2" />
            <x-stat-card :label="__('dashboard.kpi.vacant')" :value="(string) $kpis['vacant']" icon="key" :href="route('app.units.index', ['status' => 'vacant'])" />
            <x-stat-card :label="__('dashboard.kpi.open_repairs')" :value="(string) $kpis['open_repairs']" :delta="$kpis['emergencies'] > 0 ? __('dashboard.kpi.emergencies', ['count' => $kpis['emergencies']]) : null" :trend="$kpis['emergencies'] > 0 ? 'down' : null" icon="wrench-screwdriver" :href="route('app.maintenance.index')" />
            <x-stat-card :label="__('dashboard.kpi.expiring')" :value="(string) $kpis['expiring']" icon="calendar-days" :href="route('app.leases.index')" />
        </div>

        <div class="mt-6 grid gap-6 lg:grid-cols-3">
            <x-card :title="__('dashboard.charts.collections')" class="lg:col-span-2">
                <div class="h-64" x-data="dashboardChart('bar', @js([
                    'labels' => $collections['labels'],
                    'datasets' => [
                        ['label' => __('dashboard.charts.expected'), 'data' => array_map(fn ($v) => $v / 100, $collections['expected'])],
                        ['label' => __('dashboard.charts.collected'), 'data' => array_map(fn ($v) => $v / 100, $collections['collected'])],
                    ],
                ]))">
                    <canvas x-ref="canvas" role="img" aria-label="{{ __('dashboard.charts.collections') }}"></canvas>
                </div>
            </x-card>
            <x-card :title="__('dashboard.charts.aging')">
                <div class="h-64" x-data="dashboardChart('doughnut', @js([
                    'labels' => $aging['labels'],
                    'datasets' => [['label' => __('dashboard.charts.aging'), 'data' => array_map(fn ($v) => $v / 100, $aging['values'])]],
                ]))">
                    <canvas x-ref="canvas" role="img" aria-label="{{ __('dashboard.charts.aging') }}"></canvas>
                </div>
            </x-card>
        </div>

        <div class="mt-6 grid gap-6 lg:grid-cols-2">
            <x-card :title="__('dashboard.lists.pending_proofs')">
                @forelse ($pendingProofs as $proof)
                    <div class="flex flex-wrap items-center justify-between gap-3 border-b border-border py-3 last:border-0">
                        <a href="{{ route('app.payments.show', $proof) }}" class="min-w-0 hover:text-primary-600">
                            <p class="truncate text-sm font-medium">{{ $proof->tenant->full_name }}</p>
                            <p class="text-xs text-ink-muted tabular-nums">{{ $proof->money()->format() }} · {{ $proof->paid_at?->format('j M Y') }}</p>
                        </a>
                        <div class="flex gap-2">
                            <form method="POST" action="{{ route('app.payments.approve', $proof) }}">@csrf<x-button size="sm" icon="check">{{ __('payments.actions.approve') }}</x-button></form>
                            <x-button size="sm" type="button" variant="secondary" x-data x-on:click="$dispatch('open-modal', 'reject-{{ $proof->id }}')">{{ __('payments.actions.reject') }}</x-button>
                        </div>
                    </div>
                @empty
                    <p class="text-sm text-ink-muted">{{ __('dashboard.lists.no_proofs') }}</p>
                @endforelse
            </x-card>

            <x-card :title="__('dashboard.lists.recent_payments')">
                @forelse ($recentPayments as $payment)
                    <a href="{{ route('app.payments.show', $payment) }}" class="flex items-center justify-between gap-3 border-b border-border py-3 text-sm last:border-0 hover:text-primary-600">
                        <span class="min-w-0"><span class="block truncate font-medium">{{ $payment->tenant->full_name }}</span><span class="text-xs text-ink-muted">{{ $payment->paid_at?->format('j M Y') }} · {{ $payment->method->label() }}</span></span>
                        <span class="font-medium tabular-nums">{{ $payment->money()->format() }}</span>
                    </a>
                @empty
                    <p class="text-sm text-ink-muted">{{ __('dashboard.lists.no_payments') }}</p>
                @endforelse
            </x-card>

            <x-card :title="__('dashboard.lists.upcoming')">
                @forelse ($upcoming as $invoice)
                    <a href="{{ route('app.invoices.show', $invoice) }}" class="flex items-center justify-between gap-3 border-b border-border py-3 text-sm last:border-0 hover:text-primary-600">
                        <span class="min-w-0"><span class="block truncate font-medium">{{ $invoice->tenant->full_name }}</span><span class="text-xs text-ink-muted">{{ $invoice->number }} · {{ $invoice->due_date->format('j M Y') }}</span></span>
                        <span class="font-medium tabular-nums">{{ $invoice->outstanding()->format() }}</span>
                    </a>
                @empty
                    <p class="text-sm text-ink-muted">{{ __('dashboard.lists.no_upcoming') }}</p>
                @endforelse
            </x-card>

            @include('admin.dashboard._repairs')
        </div>

        @foreach ($pendingProofs as $proof)
            <x-confirm :name="'reject-'.$proof->id" method="POST" :action="route('app.payments.reject', $proof)"
                :title="__('payments.reject_title')" :consequences="__('payments.reject_consequences')" :confirm-label="__('payments.actions.reject')">
                <div class="mt-4"><x-textarea name="reason" :id="'reason-'.$proof->id" :label="__('payments.fields.reason')" rows="2" required /></div>
            </x-confirm>
        @endforeach
    @else
        <div class="grid gap-4 sm:grid-cols-2">
            <x-stat-card :label="__('dashboard.kpi.open_repairs')" :value="(string) $openRepairs" icon="wrench-screwdriver" :href="route('app.maintenance.index')" />
            <x-stat-card :label="__('dashboard.kpi.emergency_repairs')" :value="(string) $emergencies" icon="exclamation-triangle" :href="route('app.maintenance.index')" />
        </div>
        <div class="mt-6 max-w-2xl">
            @include('admin.dashboard._repairs')
        </div>
    @endif
</x-layouts::admin>
