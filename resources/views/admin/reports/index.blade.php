<x-layouts::admin :title="__('reports.title')">
    <x-page-header :title="__('reports.title')" :description="__('reports.description')" />

    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
        @foreach ($reports as $report)
            <a href="{{ route('app.reports.show', $report) }}" class="block rounded-card border border-border bg-surface p-5 shadow-card hover:border-primary-600">
                <h2 class="text-base font-semibold text-ink">{{ __('reports.names.'.$report) }}</h2>
                <p class="mt-1 text-sm text-ink-muted">{{ __('reports.descriptions.'.$report) }}</p>
            </a>
        @endforeach
    </div>
</x-layouts::admin>
