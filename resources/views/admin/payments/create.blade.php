<x-layouts::admin :title="__('payments.record')">
    <x-page-header :title="__('payments.record')" :description="__('payments.record_intro')"
        :breadcrumbs="[__('payments.title') => route('app.payments.index'), __('payments.record') => null]" />

    <form method="POST" action="{{ route('app.payments.store') }}" enctype="multipart/form-data" class="max-w-2xl">
        @csrf
        <x-card>
            <div class="grid gap-5 sm:grid-cols-2">
                <x-select name="tenant_id" :label="__('payments.fields.tenant')" :options="$tenants" :value="$tenantId" :placeholder="__('ui.choose')"
                    class="sm:col-span-2" onchange="window.location = '{{ route('app.payments.create') }}?tenant=' + this.value" />
                <x-select name="method" :label="__('payments.fields.method')" :options="$methods" value="cash" />
                <x-date-input name="paid_at" :label="__('payments.fields.paid_at')" :value="today()" required />
                <x-money-input name="amount" :label="__('payments.fields.amount')" :symbol="$currencySymbol" />
                <x-input name="note" :label="__('payments.fields.note')" optional />
                @if ($openInvoices->isNotEmpty())
                    <fieldset class="sm:col-span-2">
                        <legend class="text-sm font-medium text-ink">{{ __('payments.fields.invoices') }}</legend>
                        <p class="mb-2 text-xs text-ink-muted">{{ __('payments.fields.invoices_hint') }}</p>
                        <div class="space-y-2">
                            @foreach ($openInvoices as $invoice)
                                <x-checkbox name="invoice_ids[]" :value="$invoice->id"
                                    :label="$invoice->number.' · '.$invoice->type->label().' · '.$invoice->outstanding()->format().' · '.$invoice->due_date->format('j M Y')" />
                            @endforeach
                        </div>
                    </fieldset>
                @endif
                <x-file-dropzone name="proof" :label="__('payments.fields.proof')" class="sm:col-span-2" />
            </div>
            <x-slot:footer>
                <div class="flex justify-end gap-2">
                    <x-button variant="secondary" :href="route('app.payments.index')">{{ __('ui.cancel') }}</x-button>
                    <x-button>{{ __('payments.save') }}</x-button>
                </div>
            </x-slot:footer>
        </x-card>
    </form>
</x-layouts::admin>
