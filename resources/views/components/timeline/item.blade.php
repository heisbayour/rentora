@props([
    'title',
    'time' => null,
    'color' => 'neutral',
    'icon' => null,
])

@php
    $dots = [
        'success' => 'bg-success text-white',
        'warning' => 'bg-warning text-white',
        'danger' => 'bg-danger text-white',
        'info' => 'bg-info text-white',
        'neutral' => 'bg-surface text-ink-muted ring-1 ring-border',
    ];
@endphp

<li {{ $attributes->class('relative') }}>
    <span class="absolute -left-[37px] flex h-6 w-6 items-center justify-center rounded-full {{ $dots[$color] ?? $dots['neutral'] }}" aria-hidden="true">
        @if ($icon)
            <x-dynamic-component :component="'heroicon-m-'.$icon" class="h-3.5 w-3.5" />
        @else
            <span class="h-2 w-2 rounded-full bg-current"></span>
        @endif
    </span>
    <div class="flex flex-wrap items-baseline justify-between gap-x-3">
        <p class="text-sm font-medium text-ink">{{ $title }}</p>
        @if ($time)
            <time class="text-xs text-ink-muted">{{ $time }}</time>
        @endif
    </div>
    @if ($slot->isNotEmpty())
        <div class="mt-1 text-sm text-ink-muted">{{ $slot }}</div>
    @endif
</li>
