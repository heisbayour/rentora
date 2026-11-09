@php
    $title = __('payments.statement.for', ['name' => $tenant->full_name]);
    $layout = $portal ? 'layouts::portal' : 'layouts::admin';
    $pdfUrl = $portal ? route('portal.statement.pdf', ['from' => $from->toDateString(), 'to' => $to->toDateString()])
        : route('app.tenants.statement.pdf', [$tenant, 'from' => $from->toDateString(), 'to' => $to->toDateString()]);
@endphp

<x-dynamic-component :component="$layout" :title="__('payments.statement.title')">
    <x-page-header :title="$title">
        <x-slot:actions>
            <x-button variant="secondary" :href="$pdfUrl" icon="arrow-down-tray">{{ __('payments.statement.download') }}</x-button>
        </x-slot:actions>
    </x-page-header>

    <form method="GET" class="mb-6 flex flex-wrap items-end gap-3" data-no-guard>
        <x-date-input name="from" :label="__('payments.statement.from')" :value="$from" />
        <x-date-input name="to" :label="__('payments.statement.to')" :value="$to" />
        <x-button variant="secondary">{{ __('payments.statement.update') }}</x-button>
    </form>

    @include('statements._body')
</x-dynamic-component>
