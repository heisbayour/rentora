<x-layouts::admin :title="__('properties.title')">
    <x-page-header :title="__('properties.title')" :description="__('properties.description')">
        <x-slot:actions>
            <x-button :href="route('app.properties.create')" icon="plus">{{ __('properties.new') }}</x-button>
        </x-slot:actions>
    </x-page-header>

    <div class="mb-4 flex flex-col gap-3 sm:flex-row sm:items-center">
        <form method="GET" class="w-full sm:w-80" data-no-guard>
            @if ($type)
                <input type="hidden" name="type" value="{{ $type->value }}">
            @endif
            <x-input name="q" :value="$search" :placeholder="__('properties.search')" aria-label="{{ __('properties.search') }}" />
        </form>
        <div class="flex flex-wrap gap-2">
            <x-filter-chip :href="request()->fullUrlWithQuery(['type' => null, 'page' => null])" :active="$type === null">{{ __('properties.all_types') }}</x-filter-chip>
            @foreach ($types as $option)
                <x-filter-chip :href="request()->fullUrlWithQuery(['type' => $option->value, 'page' => null])" :active="$type === $option">{{ $option->label() }}</x-filter-chip>
            @endforeach
        </div>
    </div>

    @if ($properties->isEmpty())
        @if ($search !== '' || $type)
            <x-empty-state icon="magnifying-glass" :title="__('properties.empty_search')" />
        @else
            <x-empty-state icon="building-office-2" :title="__('properties.empty_title')" :text="__('properties.empty_text')"
                :action-label="__('properties.new')" :action-href="route('app.properties.create')" />
        @endif
    @else
        <x-table :caption="__('properties.title')">
            <x-slot:head>
                <th><x-sort-link column="name" :sort="$sort" :direction="$direction">{{ __('properties.columns.property') }}</x-sort-link></th>
                <th><x-sort-link column="code" :sort="$sort" :direction="$direction">{{ __('properties.columns.code') }}</x-sort-link></th>
                <th><x-sort-link column="city" :sort="$sort" :direction="$direction">{{ __('properties.columns.city') }}</x-sort-link></th>
                <th class="text-right">{{ __('properties.columns.units') }}</th>
                <th class="text-right">{{ __('properties.columns.occupancy') }}</th>
            </x-slot:head>
            @foreach ($properties as $property)
                <tr>
                    <td data-label="{{ __('properties.columns.property') }}">
                        <a href="{{ route('app.properties.show', $property) }}" class="font-medium text-ink hover:text-primary-600">{{ $property->name }}</a>
                        <p class="text-xs text-ink-muted">{{ $property->type->label() }}</p>
                    </td>
                    <td data-label="{{ __('properties.columns.code') }}" class="font-mono text-xs">{{ $property->code }}</td>
                    <td data-label="{{ __('properties.columns.city') }}">{{ $property->city }}</td>
                    <td data-label="{{ __('properties.columns.units') }}" class="text-right tabular-nums">{{ $property->units_count }}</td>
                    <td data-label="{{ __('properties.columns.occupancy') }}" class="text-right tabular-nums">{{ $property->occupancyPercent() }}%</td>
                </tr>
            @endforeach
        </x-table>
        <x-pagination :paginator="$properties" class="mt-4" />
    @endif
</x-layouts::admin>
