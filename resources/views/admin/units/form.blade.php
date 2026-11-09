@php
    $editing = $unit->exists;
    $title = $editing ? __('units.edit') : __('units.new');
    $breadcrumbs = [__('units.title') => route('app.units.index')];
    if ($editing) {
        $breadcrumbs[$unit->label] = route('app.units.show', $unit);
    }
    $breadcrumbs[$title] = null;
@endphp

<x-layouts::admin :title="$title">
    <x-page-header :title="$title" :breadcrumbs="$breadcrumbs" />

    <form method="POST" action="{{ $editing ? route('app.units.update', $unit) : route('app.units.store') }}" class="max-w-3xl space-y-6">
        @csrf
        @if ($editing)
            @method('PUT')
        @endif
        <x-card :title="__('units.details')">
            <div class="grid gap-5 sm:grid-cols-2">
                <x-select name="property_id" :label="__('units.fields.property')" :options="$properties" :value="$unit->property_id" :placeholder="__('ui.choose')" required />
                <x-input name="label" :label="__('units.fields.label')" :value="$unit->label" :hint="__('units.fields.label_hint')" required />
                <x-select name="type" :label="__('units.fields.type')" :options="$types" :value="$unit->type" />
                <x-select name="status" :label="__('units.fields.status')" :options="$statuses" :value="$unit->status" />
                <x-input name="bedrooms" type="number" min="0" max="50" :label="__('units.fields.bedrooms')" :value="$unit->bedrooms" required />
                <x-input name="bathrooms" type="number" min="0" max="50" :label="__('units.fields.bathrooms')" :value="$unit->bathrooms" required />
                <x-input name="size_sqm" type="number" step="0.01" min="0" :label="__('units.fields.size_sqm')" :value="$unit->size_sqm" optional />
                <x-textarea name="description" :label="__('units.fields.description')" :value="$unit->description" rows="3" optional class="sm:col-span-2" />
            </div>
        </x-card>

        <x-card :title="__('units.rent_terms')">
            <div class="grid gap-5 sm:grid-cols-2">
                <x-money-input name="rent_amount" :label="__('units.fields.rent_amount')" :value="$unit->rent_amount ?? 0" :symbol="$currencySymbol" />
                <x-select name="rent_frequency" :label="__('units.fields.rent_frequency')" :options="$frequencies" :value="$unit->rent_frequency" />
                <x-money-input name="service_charge_amount" :label="__('units.fields.service_charge_amount')" :value="$unit->service_charge_amount ?? 0" :symbol="$currencySymbol" />
                <x-money-input name="caution_deposit_amount" :label="__('units.fields.caution_deposit_amount')" :value="$unit->caution_deposit_amount ?? 0" :symbol="$currencySymbol" />
                <x-toggle name="is_listed" :label="__('units.fields.is_listed')" :hint="__('units.fields.is_listed_hint')" :checked="$unit->is_listed" class="sm:col-span-2" />
            </div>
            <x-slot:footer>
                <div class="flex justify-end gap-2">
                    <x-button variant="secondary" :href="$editing ? route('app.units.show', $unit) : route('app.units.index')">{{ __('ui.cancel') }}</x-button>
                    <x-button>{{ __('units.save') }}</x-button>
                </div>
            </x-slot:footer>
        </x-card>
    </form>
</x-layouts::admin>
