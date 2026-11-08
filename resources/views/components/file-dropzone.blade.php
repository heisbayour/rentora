{{-- Drag and drop upload with previews. Images are compressed in the browser to 1600px JPEG 0.8 before upload. --}}
@props([
    'name',
    'label' => null,
    'multiple' => false,
    'max' => null,
    'accept' => 'image/jpeg,image/png,image/webp,application/pdf',
    'hint' => null,
    'id' => null,
])

@php
    $id = $id ?? str_replace(['[', ']', '.'], '-', $name);
    $max = $max ?? ($multiple ? 5 : 1);
    $errorKey = rtrim(str_replace(['[', ']'], ['.', ''], $name), '.');
    $error = $errors->first($errorKey) ?: $errors->first($errorKey.'.*');
    $hint = $hint ?? __('ui.dropzone.hint', ['size' => (int) (config('rentora.uploads.max_kb') / 1024)]);
@endphp

<x-field :id="$id" :label="$label" :hint="$hint" :error="$error" :class="$attributes->get('class')">
    <div x-data="fileDropzone({ max: {{ (int) $max }}, tooManyMessage: @js(__('ui.dropzone.too_many', ['max' => $max])) })"
        @dragover.prevent="dragging = true" @dragleave.prevent="dragging = false" @drop.prevent="onDrop($event)">
        <label for="{{ $id }}"
            class="flex cursor-pointer flex-col items-center justify-center gap-2 rounded-card border-2 border-dashed px-4 py-8 text-center transition-colors"
            x-bind:class="dragging ? 'border-primary-600 bg-primary-50' : 'border-border bg-surface hover:bg-canvas'">
            <x-heroicon-o-arrow-up-tray class="h-6 w-6 text-ink-muted" aria-hidden="true" />
            <span class="text-sm text-ink">
                {{ __('ui.dropzone.prompt') }} <span class="font-medium text-primary-600">{{ __('ui.dropzone.browse') }}</span>
            </span>
            <span class="text-xs text-ink-muted" x-show="busy" x-cloak>{{ __('ui.dropzone.compressing') }}</span>
        </label>
        <input id="{{ $id }}" x-ref="input" type="file" name="{{ $name }}" accept="{{ $accept }}" class="sr-only"
            @if ($multiple) multiple @endif @change="onPick($event)"
            {{ $attributes->except('class') }}>
        <p class="mt-2 text-xs text-danger" x-show="error" x-text="error" x-cloak></p>
        <ul class="mt-3 grid grid-cols-2 gap-3 sm:grid-cols-3" x-show="files.length" x-cloak>
            <template x-for="(entry, index) in files" :key="entry.name + index">
                <li class="relative overflow-hidden rounded-control border border-border bg-surface">
                    <template x-if="entry.preview">
                        <img :src="entry.preview" alt="" class="h-24 w-full object-cover">
                    </template>
                    <template x-if="!entry.preview">
                        <div class="flex h-24 items-center justify-center bg-canvas">
                            <x-heroicon-o-document class="h-8 w-8 text-ink-muted" aria-hidden="true" />
                        </div>
                    </template>
                    <div class="flex items-center justify-between gap-2 px-2 py-1.5">
                        <span class="truncate text-xs text-ink" x-text="entry.name"></span>
                        <button type="button" class="text-xs font-medium text-danger" @click="remove(index)">{{ __('ui.dropzone.remove') }}</button>
                    </div>
                    <span class="block px-2 pb-1.5 text-xs text-ink-muted" x-text="humanSize(entry.size)"></span>
                </li>
            </template>
        </ul>
    </div>
</x-field>
