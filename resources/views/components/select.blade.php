@props([
    'name',
    'label' => null,
    'options' => [],
    'value' => null,
    'placeholder' => null,
    'hint' => null,
    'optional' => false,
    'id' => null,
])

@php
    $id = $id ?? str_replace(['[', ']', '.'], '-', $name);
    $errorKey = str_replace(['[', ']'], ['.', ''], $name);
    $error = $errors->first($errorKey);
    $selected = old($errorKey, $value instanceof \BackedEnum ? $value->value : $value);
@endphp

<x-field :id="$id" :label="$label" :hint="$hint" :error="$error" :optional="$optional" :class="$attributes->get('class')">
    <select id="{{ $id }}" name="{{ $name }}"
        @if ($error) aria-invalid="true" aria-describedby="{{ $id }}-error" @endif
        {{ $attributes->except('class')->class([
            'block h-10 w-full rounded-control border bg-surface pl-3 pr-9 text-sm text-ink',
            'focus:border-primary-600 focus:ring-primary-600',
            'border-danger' => $error,
            'border-border' => ! $error,
        ]) }}>
        @if ($placeholder !== null)
            <option value="">{{ $placeholder }}</option>
        @endif
        @foreach ($options as $optionValue => $optionLabel)
            <option value="{{ $optionValue }}" @selected((string) $selected === (string) $optionValue)>{{ $optionLabel }}</option>
        @endforeach
    </select>
</x-field>
