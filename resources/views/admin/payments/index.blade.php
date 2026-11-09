<x-layouts::admin :title="__('payments.title')">
    <x-page-header :title="__('payments.title')" :description="__('payments.description')">
        <x-slot:actions>
            <x-button :href="route('app.payments.create')" icon="plus">{{ __('payments.record') }}</x-button>
        </x-slot:actions>
    </x-page-header>

    <div class="mb-4 flex flex-col gap-3 sm:flex-row sm:items-center">
        <form method="GET" class="w-full sm:w-80" data-no-guard>
            @if ($status)
                <input type="hidden" name="status" value="{{ $status->value }}">
            @endif
            <x-input name="q" :value="$search" :placeholder="__('payments.search')" aria-label="{{ __('payments.search') }}" />
        </form>
        <div class="flex flex-wrap gap-2">
            <x-filter-chip :href="request()->fullUrlWithQuery(['status' => null, 'proofs' => null, 'page' => null])" :active="! $status && ! $proofs">{{ __('payments.all') }}</x-filter-chip>
            <x-filter-chip :href="request()->fullUrlWithQuery(['status' => null, 'proofs' => '1', 'page' => null])" :active="$proofs">{{ __('payments.pending_proofs', ['count' => $pendingProofs]) }}</x-filter-chip>
            @foreach ($statuses as $option)
                <x-filter-chip :href="request()->fullUrlWithQuery(['status' => $option->value, 'proofs' => null, 'page' => null])" :active="$status === $option">{{ $option->label() }}</x-filter-chip>
            @endforeach
        </div>
    </div>

    @if ($payments->isEmpty() && ($search !== '' || $status || $proofs))
        <x-empty-state icon="magnifying-glass" :title="__('payments.empty_search')" />
    @else
        @include('admin.payments._table', ['payments' => $payments, 'showTenant' => true])
        <x-pagination :paginator="$payments" class="mt-4" />
    @endif
</x-layouts::admin>
