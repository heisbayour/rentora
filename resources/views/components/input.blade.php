@props([
    'name',
    'label' => null,
    'type' => 'text',
    'value' => null,
    'hint' => null,
    'optional' => false,
    'id' => null,
])

@php
    $id = $id ?? str_replace(['[', ']', '.'], '-', $name);
    $errorKey = str_replace(['[', ']'], ['.', ''], $name);
    $error = $errors->first($errorKey);
    $value = $type === 'password' ? null : old($errorKey, $value);
@endphp

<x-field :id="$id" :label="$label" :hint="$hint" :error="$error" :optional="$optional" :class="$attributes->get('class')">
    <input id="{{ $id }}" name="{{ $name }}" type="{{ $type }}" value="{{ $value }}"
        @if ($error) aria-invalid="true" aria-describedby="{{ $id }}-error" @elseif ($hint) aria-describedby="{{ $id }}-hint" @endif
        {{ $attributes->except('class')->class([
            'block h-10 w-full rounded-control border bg-surface px-3 text-sm text-ink placeholder:text-ink-muted/70',
            'focus:border-primary-600 focus:ring-primary-600',
            'border-danger' => $error,
            'border-border' => ! $error,
        ]) }}>
</x-field>
