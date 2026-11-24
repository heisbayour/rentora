<x-layouts::admin :title="__('reports.names.'.$report)">
    <x-page-header :title="__('reports.names.'.$report)" :description="__('reports.descriptions.'.$report)"
        :breadcrumbs="[__('reports.title') => route('app.reports.index'), __('reports.names.'.$report) => null]">
        <x-slot:actions>
            <x-button variant="secondary" icon="arrow-down-tray" :href="route('app.reports.export', ['report' => $report] + array_filter([
                'property_id' => $filters['property_id'],
                'from' => $report === 'collections' ? $filters['from']->toDateString() : null,
                'to' => $report === 'collections' ? $filters['to']->toDateString() : null,
            ]))">{{ __('reports.export_csv') }}</x-button>
        </x-slot:actions>
    </x-page-header>

    <form method="GET" class="mb-4 flex flex-wrap items-end gap-3" data-no-guard>
        <x-select name="property_id" :label="__('reports.filters.property')" :options="$properties" :value="$filters['property_id']" :placeholder="__('reports.filters.all_properties')" class="w-full sm:w-64" />
        @if ($report === 'collections')
            <x-date-input name="from" :label="__('reports.filters.from')" :value="$filters['from']" />
            <x-date-input name="to" :label="__('reports.filters.to')" :value="$filters['to']" />
        @endif
        <x-button variant="secondary" icon="funnel">{{ __('reports.filters.apply') }}</x-button>
    </form>

    @if ($summary)
        <div class="mb-4 grid gap-3 sm:grid-cols-3 lg:grid-cols-5">
            @foreach ($summary as $label => $amount)
                <x-stat-card :label="$label" :value="$amount->format()" />
            @endforeach
        </div>
    @endif

    @if ($rows === [])
        <x-empty-state icon="chart-bar" :title="__('reports.empty_title')" :text="__('reports.empty_text')" />
    @else
        <x-table :caption="__('reports.names.'.$report)">
            <x-slot:head>
                @foreach ($columns as $column)
                    <th>{{ $column }}</th>
                @endforeach
            </x-slot:head>
            @foreach ($rows as $row)
                <tr>
                    @foreach ($row as $i => $cell)
                        @if ($cell instanceof \App\Support\Money)
                            <td data-label="{{ $columns[$i] }}" class="text-right tabular-nums">{{ $cell->format() }}</td>
                        @else
                            <td data-label="{{ $columns[$i] }}">{{ $cell ?? '-' }}</td>
                        @endif
                    @endforeach
                </tr>
            @endforeach
        </x-table>
    @endif
</x-layouts::admin>
