<x-layouts::admin :title="$property->name">
    <x-page-header :title="$property->name" :description="$property->fullAddress()"
        :breadcrumbs="[__('properties.title') => route('app.properties.index'), $property->name => null]">
        <x-slot:actions>
            <x-button variant="secondary" :href="route('app.properties.quick-add', $property)" icon="squares-plus">{{ __('properties.quick_add') }}</x-button>
            <x-button variant="secondary" :href="route('app.units.create', ['property' => $property->id])" icon="plus">{{ __('properties.add_unit') }}</x-button>
            <x-dropdown>
                <x-slot:trigger class="h-10 border border-border px-3 text-sm font-medium text-ink hover:bg-canvas" aria-label="{{ __('ui.account_menu') }}">
                    <x-heroicon-m-ellipsis-horizontal class="h-5 w-5" aria-hidden="true" />
                </x-slot:trigger>
                <x-dropdown.item :href="route('app.properties.edit', $property)" icon="pencil-square">{{ __('properties.edit') }}</x-dropdown.item>
                <x-dropdown.item icon="trash" danger x-on:click="$dispatch('open-modal', 'delete-property')">{{ __('properties.delete') }}</x-dropdown.item>
            </x-dropdown>
        </x-slot:actions>
    </x-page-header>

    @error('property')
        <x-alert type="danger" class="mb-4">{{ $message }}</x-alert>
    @enderror

    <div class="mb-6 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
        <x-stat-card :label="__('properties.stats.income')" :value="$incomeThisYear->format()" icon="banknotes" />
        <x-stat-card :label="__('properties.stats.units')" :value="$property->units_count" icon="squares-2x2" />
        <x-stat-card :label="__('properties.stats.occupancy')" :value="$property->occupancyPercent().'%'"
            :delta="__('properties.stats.occupied_of', ['occupied' => $property->occupied_units_count, 'total' => $property->units_count])" icon="home" />
        <x-stat-card :label="__('properties.stats.open_repairs')" :value="$openRepairs" icon="wrench-screwdriver" :href="route('app.maintenance.index')" />
    </div>

    <x-tabs :tabs="['units' => __('properties.tabs.units'), 'documents' => __('properties.tabs.documents')]" :active="$tab"
        :links="['units' => route('app.properties.show', [$property, 'tab' => 'units']), 'documents' => route('app.properties.show', [$property, 'tab' => 'documents'])]">
        @if ($tab === 'units')
            @if ($units->isEmpty())
                <x-empty-state icon="squares-2x2" :title="__('properties.no_units_title')" :text="__('properties.no_units_text')"
                    :action-label="__('properties.quick_add')" :action-href="route('app.properties.quick-add', $property)" />
            @else
                @include('admin.units._table', ['units' => $units, 'showProperty' => false])
            @endif
        @else
            <x-documents :documentable="$property" :documents="$documents" :visibilities="$visibilities" :can-manage="true" />
        @endif
    </x-tabs>

    <x-confirm name="delete-property" :action="route('app.properties.destroy', $property)" word="DELETE"
        :title="__('properties.delete_title', ['name' => $property->name])"
        :consequences="__('properties.delete_consequences', ['count' => $property->units_count])"
        :confirm-label="__('properties.delete')" />
</x-layouts::admin>
