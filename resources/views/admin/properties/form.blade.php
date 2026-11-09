@php
    $editing = $property->exists;
    $title = $editing ? __('properties.edit') : __('properties.new');
    $breadcrumbs = [__('properties.title') => route('app.properties.index')];
    if ($editing) {
        $breadcrumbs[$property->name] = route('app.properties.show', $property);
    }
    $breadcrumbs[$title] = null;
@endphp

<x-layouts::admin :title="$title">
    <x-page-header :title="$title" :breadcrumbs="$breadcrumbs" />

    <form method="POST" enctype="multipart/form-data"
        action="{{ $editing ? route('app.properties.update', $property) : route('app.properties.store') }}" class="max-w-3xl">
        @csrf
        @if ($editing)
            @method('PUT')
        @endif
        <x-card>
            <div class="grid gap-5 sm:grid-cols-2">
                <x-input name="name" :label="__('properties.fields.name')" :value="$property->name" required />
                <x-input name="code" :label="__('properties.fields.code')" :value="$property->code" :hint="__('properties.fields.code_hint')" required class="[&_input]:uppercase" />
                <x-select name="type" :label="__('properties.fields.type')" :options="\App\Enums\PropertyType::options()" :value="$property->type" />
                <x-input name="country" :label="__('properties.fields.country')" :value="$property->country" required />
                <x-input name="address" :label="__('properties.fields.address')" :value="$property->address" required class="sm:col-span-2" />
                <x-input name="city" :label="__('properties.fields.city')" :value="$property->city" optional />
                <x-input name="state" :label="__('properties.fields.state')" :value="$property->state" optional />
                <x-textarea name="description" :label="__('properties.fields.description')" :value="$property->description" optional class="sm:col-span-2" />
                <x-toggle name="is_listed" :label="__('properties.fields.is_listed')" :hint="__('properties.fields.is_listed_hint')" :checked="$property->is_listed" class="sm:col-span-2" />
                <div class="sm:col-span-2">
                    @if ($property->coverUrl())
                        <img src="{{ $property->coverUrl() }}" alt="" class="mb-3 h-32 w-full max-w-xs rounded-control object-cover">
                        <x-checkbox name="remove_cover" :label="__('properties.fields.remove_cover')" class="mb-3" />
                    @endif
                    <x-file-dropzone name="cover_image" :label="__('properties.fields.cover_image')" accept="image/jpeg,image/png,image/webp" />
                </div>
            </div>
            <x-slot:footer>
                <div class="flex justify-end gap-2">
                    <x-button variant="secondary" :href="$editing ? route('app.properties.show', $property) : route('app.properties.index')">{{ __('ui.cancel') }}</x-button>
                    <x-button>{{ __('properties.save') }}</x-button>
                </div>
            </x-slot:footer>
        </x-card>
    </form>
</x-layouts::admin>
