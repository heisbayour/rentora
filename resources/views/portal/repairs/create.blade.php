<x-layouts::portal :title="__('maintenance.portal.new')">
    <h1 class="mb-6 text-xl font-semibold text-ink">{{ __('maintenance.portal.new') }}</h1>

    <form method="POST" action="{{ route('portal.repairs.store') }}" enctype="multipart/form-data" class="space-y-6">
        @csrf
        <x-card :title="__('maintenance.portal.choose_category')">
            @error('category')
                <x-alert type="danger" class="mb-4">{{ $message }}</x-alert>
            @enderror
            <fieldset class="grid grid-cols-2 gap-3 sm:grid-cols-5">
                <legend class="sr-only">{{ __('maintenance.fields.category') }}</legend>
                @foreach ($categories as $category)
                    <label class="flex cursor-pointer flex-col items-center gap-2 rounded-control border border-border p-3 text-center text-sm has-[:checked]:border-primary-600 has-[:checked]:bg-primary-50">
                        <input type="radio" name="category" value="{{ $category->value }}" class="sr-only" @checked(old('category') === $category->value)>
                        <x-dynamic-component :component="'heroicon-o-'.$category->icon()" class="h-6 w-6 text-primary-600" aria-hidden="true" />
                        {{ $category->label() }}
                    </label>
                @endforeach
            </fieldset>
        </x-card>

        <x-card>
            <div class="space-y-5">
                <x-input name="title" :label="__('maintenance.fields.title')" required />
                <x-textarea name="description" :label="__('maintenance.fields.description')" rows="4" required />
                <x-select name="priority" :label="__('maintenance.fields.priority')" :options="$priorities" value="normal" />
                <x-file-dropzone name="photos[]" :label="__('maintenance.fields.photos')" multiple :max="5" accept="image/jpeg,image/png,image/webp" />
                <x-input name="preferred_time" :label="__('maintenance.fields.preferred_time')" optional />
                <x-textarea name="access_instructions" :label="__('maintenance.fields.access_instructions')" rows="2" optional />
            </div>
            <x-slot:footer>
                <div class="flex justify-end gap-2">
                    <x-button variant="secondary" :href="route('portal.repairs.index')">{{ __('ui.cancel') }}</x-button>
                    <x-button>{{ __('maintenance.portal.submit') }}</x-button>
                </div>
            </x-slot:footer>
        </x-card>
    </form>
</x-layouts::portal>
