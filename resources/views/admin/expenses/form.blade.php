@php($editing = $expense->exists)
<x-layouts::admin :title="$editing ? __('expenses.edit') : __('expenses.new')">
    <x-page-header :title="$editing ? __('expenses.edit') : __('expenses.new')"
        :breadcrumbs="[__('expenses.title') => route('app.expenses.index'), ($editing ? __('expenses.edit') : __('expenses.new')) => null]" />

    <form method="POST" action="{{ $editing ? route('app.expenses.update', $expense) : route('app.expenses.store') }}" enctype="multipart/form-data" class="max-w-2xl">
        @csrf
        @if ($editing) @method('PUT') @endif
        <x-card>
            <div class="grid gap-5 sm:grid-cols-2">
                <x-select name="property_id" :label="__('expenses.fields.property')" :options="$properties" :value="$expense->property_id" :placeholder="__('ui.choose')" />
                <x-select name="unit_id" :label="__('expenses.fields.unit')" :options="$units" :value="$expense->unit_id" placeholder="-" optional />
                <x-select name="category" :label="__('expenses.fields.category')" :options="$categories" :value="$expense->category" />
                <x-date-input name="incurred_on" :label="__('expenses.fields.incurred_on')" :value="$expense->incurred_on" required />
                <x-input name="description" :label="__('expenses.fields.description')" :value="$expense->description" required class="sm:col-span-2" />
                <x-money-input name="amount" :label="__('expenses.fields.amount')" :value="$expense->amount" :symbol="$currencySymbol" />
                <x-file-dropzone name="receipt" :label="__('expenses.fields.receipt')" class="sm:col-span-2" />
            </div>
            <x-slot:footer>
                <div class="flex justify-between gap-2">
                    <div>
                        @if ($editing)
                            <x-button type="button" variant="ghost" class="text-danger" x-data x-on:click="$dispatch('open-modal', 'delete-expense')">{{ __('ui.delete') }}</x-button>
                        @endif
                    </div>
                    <div class="flex gap-2">
                        <x-button variant="secondary" :href="route('app.expenses.index')">{{ __('ui.cancel') }}</x-button>
                        <x-button>{{ __('expenses.save') }}</x-button>
                    </div>
                </div>
            </x-slot:footer>
        </x-card>
    </form>

    @if ($editing)
        <x-confirm name="delete-expense" :action="route('app.expenses.destroy', $expense)" :title="__('expenses.delete_title')" :consequences="__('expenses.delete_consequences')" :confirm-label="__('ui.delete')" />
    @endif
</x-layouts::admin>
