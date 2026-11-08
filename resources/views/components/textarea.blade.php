@props([
    'name',
    'label' => null,
    'value' => null,
    'rows' => 4,
    'hint' => null,
    'optional' => false,
    'id' => null,
])

@php
    $id = $id ?? str_replace(['[', ']', '.'], '-', $name);
    $errorKey = str_replace(['[', ']'], ['.', ''], $name);
    $error = $errors->first($errorKey);
@endphp

<x-field :id="$id" :label="$label" :hint="$hint" :error="$error" :optional="$optional" :class="$attributes->get('class')">
    <textarea id="{{ $id }}" name="{{ $name }}" rows="{{ $rows }}"
        @if ($error) aria-invalid="true" aria-describedby="{{ $id }}-error" @elseif ($hint) aria-describedby="{{ $id }}-hint" @endif
        {{ $attributes->except('class')->class([
            'block w-full rounded-control border bg-surface px-3 py-2 text-sm text-ink placeholder:text-ink-muted/70',
            'focus:border-primary-600 focus:ring-primary-600',
            'border-danger' => $error,
            'border-border' => ! $error,
        ]) }}>{{ old($errorKey, $value) }}</textarea>
</x-field>
