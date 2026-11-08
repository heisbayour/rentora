@props([
    'name',
    'label' => null,
    'value' => null,
    'hint' => null,
    'optional' => false,
    'id' => null,
])

@php
    $value = $value instanceof \DateTimeInterface ? $value->format('Y-m-d') : $value;
@endphp

<x-input type="date" :name="$name" :label="$label" :value="$value" :hint="$hint" :optional="$optional" :id="$id" {{ $attributes }} />
