<x-layouts::admin :title="__('units.quick_add.title')">
    <x-page-header :title="__('units.quick_add.title')" :description="__('units.quick_add.intro', ['max' => $max, 'property' => $property->name])"
        :breadcrumbs="[__('properties.title') => route('app.properties.index'), $property->name => route('app.properties.show', $property), __('units.quick_add.title') => null]" />

    @include('admin.units._quick-add-form')
</x-layouts::admin>
