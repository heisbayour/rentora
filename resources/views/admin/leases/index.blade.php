<x-layouts::admin :title="__('leases.title')">
    <x-page-header :title="__('leases.title')" :description="__('leases.description')">
        <x-slot:actions>
            <x-button :href="route('app.leases.wizard.start')" icon="plus">{{ __('leases.new') }}</x-button>
        </x-slot:actions>
    </x-page-header>

    <div class="mb-4 flex flex-col gap-3 sm:flex-row sm:items-center">
        <form method="GET" class="w-full sm:w-80" data-no-guard>
            @if ($status)
                <input type="hidden" name="status" value="{{ $status->value }}">
            @endif
            <x-input name="q" :value="$search" :placeholder="__('leases.search')" aria-label="{{ __('leases.search') }}" />
        </form>
        <div class="flex flex-wrap gap-2">
            <x-filter-chip :href="request()->fullUrlWithQuery(['status' => null, 'expiring' => null, 'page' => null])" :active="$status === null && ! $expiring">{{ __('leases.all_statuses') }}</x-filter-chip>
            @foreach ($statuses as $option)
                <x-filter-chip :href="request()->fullUrlWithQuery(['status' => $option->value, 'expiring' => null, 'page' => null])" :active="$status === $option">{{ $option->label() }}</x-filter-chip>
            @endforeach
            <x-filter-chip :href="request()->fullUrlWithQuery(['status' => null, 'expiring' => '60', 'page' => null])" :active="$expiring">{{ __('leases.expiring_60') }}</x-filter-chip>
        </div>
    </div>

    @if ($leases->isEmpty() && ($search !== '' || $status || $expiring))
        <x-empty-state icon="magnifying-glass" :title="__('leases.empty_search')" />
    @else
        @include('admin.leases._table', ['leases' => $leases, 'showTenant' => true])
        <x-pagination :paginator="$leases" class="mt-4" />
    @endif
</x-layouts::admin>
