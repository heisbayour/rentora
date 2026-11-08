{{-- Shows a formatted major amount; submits minor units (kobo) through the hidden field. --}}
@props([
    'name',
    'label' => null,
    'value' => null,
    'symbol' => '₦',
    'hint' => null,
    'optional' => false,
    'id' => null,
])

@php
    $id = $id ?? str_replace(['[', ']', '.'], '-', $name);
    $errorKey = str_replace(['[', ']'], ['.', ''], $name);
    $error = $errors->first($errorKey);
    $minor = old($errorKey, $value);
@endphp

<x-field :id="$id" :label="$label" :hint="$hint" :error="$error" :optional="$optional" :class="$attributes->get('class')">
    <div class="relative" x-data="moneyInput(@js($minor))">
        <span class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3 text-sm text-ink-muted">{{ $symbol }}</span>
        <input id="{{ $id }}" type="text" inputmode="decimal" autocomplete="off"
            x-model="display" @input="onInput" @blur="onBlur"
            @if ($error) aria-invalid="true" aria-describedby="{{ $id }}-error" @endif
            {{ $attributes->except('class')->class([
                'block h-10 w-full rounded-control border bg-surface pl-8 pr-3 text-right text-sm text-ink tabular-nums',
                'focus:border-primary-600 focus:ring-primary-600',
                'border-danger' => $error,
                'border-border' => ! $error,
            ]) }}>
        <input type="hidden" name="{{ $name }}" x-bind:value="minor" value="{{ $minor }}">
    </div>
</x-field>
