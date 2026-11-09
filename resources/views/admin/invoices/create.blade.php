<x-layouts::admin :title="__('invoices.new')">
    <x-page-header :title="__('invoices.new')" :description="__('invoices.new_intro')"
        :breadcrumbs="[__('invoices.title') => route('app.invoices.index'), __('invoices.new') => null]" />

    <form method="POST" action="{{ route('app.invoices.store') }}" class="max-w-2xl">
        @csrf
        <x-card>
            <div class="grid gap-5 sm:grid-cols-2">
                <x-select name="tenant_id" :label="__('invoices.fields.tenant')" :options="$tenants" :value="$tenantId" :placeholder="__('ui.choose')" class="sm:col-span-2" />
                <x-select name="lease_id" :label="__('invoices.fields.lease')" :options="$leases" :placeholder="'-'" :hint="__('invoices.fields.lease_hint')" class="sm:col-span-2" />
                <x-select name="type" :label="__('invoices.fields.type')" :options="$types" value="utility" />
                <x-date-input name="due_date" :label="__('invoices.fields.due_date')" :value="today()" required />
                <x-input name="description" :label="__('invoices.fields.description')" required class="sm:col-span-2" />
                <x-money-input name="amount" :label="__('invoices.fields.amount')" :symbol="$currencySymbol" />
            </div>
            <x-slot:footer>
                <div class="flex justify-end gap-2">
                    <x-button variant="secondary" :href="route('app.invoices.index')">{{ __('ui.cancel') }}</x-button>
                    <x-button>{{ __('invoices.save') }}</x-button>
                </div>
            </x-slot:footer>
        </x-card>
    </form>
</x-layouts::admin>
