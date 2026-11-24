<x-layouts::admin :title="__('expenses.title')">
    <x-page-header :title="__('expenses.title')" :description="__('expenses.description')">
        <x-slot:actions>
            <x-button :href="route('app.expenses.create')" icon="plus">{{ __('expenses.new') }}</x-button>
        </x-slot:actions>
    </x-page-header>

    <form method="GET" class="mb-4 flex flex-wrap items-end gap-3" data-no-guard>
        <x-input name="q" :value="$search" :placeholder="__('expenses.search')" aria-label="{{ __('expenses.search') }}" class="w-full sm:w-64" />
        <x-select name="property_id" :options="$properties" :value="$propertyId" :placeholder="__('reports.filters.all_properties')" aria-label="{{ __('reports.filters.property') }}" class="w-full sm:w-56" />
        <x-select name="category" :options="$categories" :value="$category" :placeholder="__('expenses.all_categories')" aria-label="{{ __('expenses.fields.category') }}" class="w-full sm:w-48" />
        <x-button variant="secondary" icon="funnel">{{ __('reports.filters.apply') }}</x-button>
    </form>

    @if ($expenses->isEmpty())
        <x-empty-state icon="receipt-percent" :title="__('expenses.empty_title')" :text="__('expenses.empty_text')"
            :action-label="__('expenses.new')" :action-href="route('app.expenses.create')" />
    @else
        <p class="mb-2 text-sm text-ink-muted">{{ __('expenses.total', ['amount' => $total->format()]) }}</p>
        <x-table :caption="__('expenses.title')">
            <x-slot:head>
                <th>{{ __('expenses.fields.incurred_on') }}</th>
                <th>{{ __('expenses.fields.description') }}</th>
                <th>{{ __('expenses.fields.property') }}</th>
                <th>{{ __('expenses.fields.category') }}</th>
                <th class="text-right">{{ __('expenses.fields.amount') }}</th>
                <th><span class="sr-only">{{ __('ui.actions') }}</span></th>
            </x-slot:head>
            @foreach ($expenses as $expense)
                <tr>
                    <td data-label="{{ __('expenses.fields.incurred_on') }}">{{ $expense->incurred_on->format('j M Y') }}</td>
                    <td data-label="{{ __('expenses.fields.description') }}">{{ $expense->description }}</td>
                    <td data-label="{{ __('expenses.fields.property') }}">{{ $expense->property->name }}@if ($expense->unit)<span class="block text-xs text-ink-muted">{{ $expense->unit->label }}</span>@endif</td>
                    <td data-label="{{ __('expenses.fields.category') }}">{{ $expense->category->label() }}</td>
                    <td data-label="{{ __('expenses.fields.amount') }}" class="text-right tabular-nums">{{ \App\Support\Money::forOrganization($expense->amount)->format() }}</td>
                    <td data-label="" class="whitespace-nowrap text-right">
                        @if ($expense->receipt_path)
                            <x-button size="sm" variant="ghost" :href="route('app.expenses.receipt', $expense)" icon="paper-clip"><span class="sr-only">{{ __('expenses.receipt') }}</span></x-button>
                        @endif
                        <x-button size="sm" variant="ghost" :href="route('app.expenses.edit', $expense)">{{ __('ui.edit') }}</x-button>
                    </td>
                </tr>
            @endforeach
        </x-table>
        <x-pagination :paginator="$expenses" class="mt-4" />
    @endif
</x-layouts::admin>
