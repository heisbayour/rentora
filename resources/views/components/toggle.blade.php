{{-- Switch control. Always submits 0 or 1 for its name. --}}
@props([
    'name',
    'label',
    'checked' => false,
    'hint' => null,
    'id' => null,
])

@php
    $id = $id ?? str_replace(['[', ']', '.'], '-', $name);
    $old = old(str_replace(['[', ']'], ['.', ''], $name));
    $on = $old !== null ? (bool) $old : (bool) $checked;
@endphp

<div x-data="{ on: @js($on) }" {{ $attributes->class('flex items-start justify-between gap-4') }}>
    <div>
        <span id="{{ $id }}-label" class="block text-sm font-medium text-ink">{{ $label }}</span>
        @if ($hint)
            <span class="block text-xs text-ink-muted">{{ $hint }}</span>
        @endif
    </div>
    <input type="hidden" name="{{ $name }}" x-bind:value="on ? 1 : 0" value="{{ $on ? 1 : 0 }}">
    <button type="button" id="{{ $id }}" role="switch" aria-labelledby="{{ $id }}-label"
        x-bind:aria-checked="on.toString()" aria-checked="{{ $on ? 'true' : 'false' }}"
        @click="on = !on"
        class="relative inline-flex h-6 w-11 shrink-0 cursor-pointer rounded-full border-2 border-transparent transition-colors"
        x-bind:class="on ? 'bg-primary-600' : 'bg-border'">
        <span class="sr-only" x-text="on ? @js(__('ui.toggle_on')) : @js(__('ui.toggle_off'))"></span>
        <span aria-hidden="true" class="inline-block h-5 w-5 transform rounded-full bg-white shadow transition"
            x-bind:class="on ? 'translate-x-5' : 'translate-x-0'"></span>
    </button>
</div>
