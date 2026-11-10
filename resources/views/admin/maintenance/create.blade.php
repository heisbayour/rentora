<x-layouts::admin :title="__('maintenance.new')">
    <x-page-header :title="__('maintenance.new')" :breadcrumbs="[__('maintenance.title') => route('app.maintenance.index'), __('maintenance.new') => null]" />

    <form method="POST" action="{{ route('app.maintenance.store') }}" enctype="multipart/form-data" class="max-w-2xl">
        @csrf
        <x-card>
            <div class="grid gap-5 sm:grid-cols-2">
                <x-select name="unit_id" :label="__('maintenance.fields.unit')" :options="$units" :value="$unitId" :placeholder="__('ui.choose')" class="sm:col-span-2" />
                <x-select name="tenant_id" :label="__('maintenance.fields.tenant')" :options="$tenants" placeholder="-" :hint="__('maintenance.fields.tenant_hint')" class="sm:col-span-2" />
                <x-select name="category" :label="__('maintenance.fields.category')" :options="$categories" value="plumbing" />
                <x-select name="priority" :label="__('maintenance.fields.priority')" :options="$priorities" value="normal" />
                <x-input name="title" :label="__('maintenance.fields.title')" required class="sm:col-span-2" />
                <x-textarea name="description" :label="__('maintenance.fields.description')" rows="4" required class="sm:col-span-2" />
                <x-input name="preferred_time" :label="__('maintenance.fields.preferred_time')" optional />
                <x-input name="access_instructions" :label="__('maintenance.fields.access_instructions')" optional />
                <x-file-dropzone name="photos[]" :label="__('maintenance.fields.photos')" multiple :max="5" accept="image/jpeg,image/png,image/webp" class="sm:col-span-2" />
            </div>
            <x-slot:footer>
                <div class="flex justify-end gap-2">
                    <x-button variant="secondary" :href="route('app.maintenance.index')">{{ __('ui.cancel') }}</x-button>
                    <x-button>{{ __('maintenance.new') }}</x-button>
                </div>
            </x-slot:footer>
        </x-card>
    </form>
</x-layouts::admin>
