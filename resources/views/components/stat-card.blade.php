{{-- :trend is "up", "down" or null and only colours the delta line. --}}
@props([
    'label',
    'value',
    'delta' => null,
    'trend' => null,
    'icon' => null,
    'href' => null,
])

@php
    $tag = $href ? 'a' : 'div';
@endphp

<{{ $tag }} @if ($href) href="{{ $href }}" @endif
    {{ $attributes->class([
        'block rounded-card border border-border bg-surface p-5 shadow-card',
        'transition-colors hover:border-primary-600' => $href,
    ]) }}>
    <div class="flex items-start justify-between gap-3">
        <p class="text-sm font-medium text-ink-muted">{{ $label }}</p>
        @if ($icon)
            <span class="flex h-9 w-9 items-center justify-center rounded-full bg-primary-50 text-primary-600">
                <x-dynamic-component :component="'heroicon-o-'.$icon" class="h-5 w-5" aria-hidden="true" />
            </span>
        @endif
    </div>
    <p class="mt-2 text-xl font-semibold text-ink tabular-nums">{{ $value }}</p>
    @if ($delta !== null)
        <p @class([
            'mt-1 text-xs font-medium',
            'text-success' => $trend === 'up',
            'text-danger' => $trend === 'down',
            'text-ink-muted' => $trend === null,
        ])>{{ $delta }}</p>
    @endif
</{{ $tag }}>
