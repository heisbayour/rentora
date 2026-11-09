<x-layouts::admin :title="__('invoices.title')">
    <x-page-header :title="__('invoices.title')" :description="__('invoices.description')">
        <x-slot:actions>
            <x-button :href="route('app.invoices.create')" icon="plus">{{ __('invoices.new') }}</x-button>
        </x-slot:actions>
    </x-page-header>

    <div class="mb-4 flex flex-col gap-3 sm:flex-row sm:items-center">
        <form method="GET" class="w-full sm:w-80" data-no-guard>
            @foreach (['status' => $status?->value, 'type' => $type?->value, 'overdue' => $overdue ? '1' : null] as $key => $value)
                @if ($value)
                    <input type="hidden" name="{{ $key }}" value="{{ $value }}">
                @endif
            @endforeach
            <x-input name="q" :value="$search" :placeholder="__('invoices.search')" aria-label="{{ __('invoices.search') }}" />
        </form>
        <div class="flex flex-wrap gap-2">
            <x-filter-chip :href="request()->fullUrlWithQuery(['status' => null, 'overdue' => null, 'page' => null])" :active="! $status && ! $overdue">{{ __('invoices.all') }}</x-filter-chip>
            <x-filter-chip :href="request()->fullUrlWithQuery(['status' => null, 'overdue' => '1', 'page' => null])" :active="$overdue">{{ __('invoices.overdue') }}</x-filter-chip>
            @foreach ($statuses as $option)
                <x-filter-chip :href="request()->fullUrlWithQuery(['status' => $option->value, 'overdue' => null, 'page' => null])" :active="$status === $option">{{ $option->label() }}</x-filter-chip>
            @endforeach
        </div>
    </div>

    @if ($invoices->isEmpty() && ($search !== '' || $status || $type || $overdue))
        <x-empty-state icon="magnifying-glass" :title="__('invoices.empty_search')" />
    @else
        @include('admin.invoices._table', ['invoices' => $invoices, 'showTenant' => true])
        <x-pagination :paginator="$invoices" class="mt-4" />
    @endif
</x-layouts::admin>
