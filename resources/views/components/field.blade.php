{{-- Shared label, hint and inline error wrapper used by every form control. --}}
@props([
    'id',
    'label' => null,
    'hint' => null,
    'error' => null,
    'optional' => false,
])

<div {{ $attributes->class('space-y-1.5') }}>
    @if ($label)
        <label for="{{ $id }}" class="block text-sm font-medium text-ink">
            {{ $label }}
            @if ($optional)
                <span class="font-normal text-ink-muted">({{ __('ui.optional') }})</span>
            @endif
        </label>
    @endif
    {{ $slot }}
    @if ($error)
        <p id="{{ $id }}-error" class="text-xs text-danger">{{ $error }}</p>
    @elseif ($hint)
        <p id="{{ $id }}-hint" class="text-xs text-ink-muted">{{ $hint }}</p>
    @endif
</div>
