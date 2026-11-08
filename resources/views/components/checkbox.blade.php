@props([
    'name',
    'label',
    'checked' => false,
    'value' => '1',
    'hint' => null,
    'id' => null,
])

@php
    $id = $id ?? str_replace(['[', ']', '.'], '-', $name).(str_ends_with($name, '[]') ? '-'.$value : '');
    $errorKey = str_replace(['[', ']'], ['.', ''], $name);
    $old = old(rtrim($errorKey, '.'));
    $isChecked = $old !== null ? (is_array($old) ? in_array((string) $value, array_map('strval', $old), true) : (bool) $old) : $checked;
    $error = $errors->first(rtrim($errorKey, '.'));
@endphp

<div {{ $attributes->only('class')->class('flex items-start gap-3') }}>
    <input id="{{ $id }}" name="{{ $name }}" type="checkbox" value="{{ $value }}" @checked($isChecked)
        {{ $attributes->except('class')->class('mt-0.5 h-4 w-4 rounded border-border text-primary-600 focus:ring-primary-600') }}>
    <div>
        <label for="{{ $id }}" class="text-sm text-ink">{{ $label }}</label>
        @if ($error)
            <p class="text-xs text-danger">{{ $error }}</p>
        @elseif ($hint)
            <p class="text-xs text-ink-muted">{{ $hint }}</p>
        @endif
    </div>
</div>
