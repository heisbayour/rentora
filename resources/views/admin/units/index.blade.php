<x-layouts::admin :title="__('units.title')">
    <x-page-header :title="__('units.title')" :description="__('units.description')">
        @if ($canManage)
            <x-slot:actions>
                <x-button :href="route('app.units.create')" icon="plus">{{ __('units.new') }}</x-button>
            </x-slot:actions>
        @endif
    </x-page-header>

    <div class="mb-4 flex flex-col gap-3 lg:flex-row lg:items-center">
        <form method="GET" class="flex w-full flex-col gap-3 sm:flex-row lg:w-auto" data-no-guard>
            @if ($status)
                <input type="hidden" name="status" value="{{ $status->value }}">
            @endif
            <div class="sm:w-72">
                <x-input name="q" :value="$search" :placeholder="__('units.search')" aria-label="{{ __('units.search') }}" />
            </div>
            <div class="sm:w-56">
                <x-select name="property" :options="$properties" :value="$propertyId" :placeholder="__('units.all_properties')"
                    aria-label="{{ __('units.fields.property') }}" onchange="this.form.submit()" />
            </div>
        </form>
        <div class="flex flex-wrap gap-2">
            <x-filter-chip :href="request()->fullUrlWithQuery(['status' => null, 'page' => null])" :active="$status === null">{{ __('units.all_statuses') }}</x-filter-chip>
            @foreach ($statuses as $option)
                <x-filter-chip :href="request()->fullUrlWithQuery(['status' => $option->value, 'page' => null])" :active="$status === $option">{{ $option->label() }}</x-filter-chip>
            @endforeach
        </div>
    </div>

    @if ($units->isEmpty())
        @if ($search !== '' || $status || $propertyId)
            <x-empty-state icon="magnifying-glass" :title="__('units.empty_search')" />
        @else
            <x-empty-state icon="squares-2x2" :title="__('units.empty_title')" :text="__('units.empty_text')"
                :action-label="$canManage ? __('units.new') : null" :action-href="$canManage ? route('app.units.create') : null" />
        @endif
    @else
        @include('admin.units._table', ['units' => $units, 'showProperty' => true])
        <x-pagination :paginator="$units" class="mt-4" />
    @endif
</x-layouts::admin>
