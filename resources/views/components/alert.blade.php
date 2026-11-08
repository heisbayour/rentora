@props([
    'type' => 'info',
    'title' => null,
])

@php
    $styles = [
        'success' => ['bg-success-bg text-success', 'check-circle'],
        'warning' => ['bg-warning-bg text-warning', 'exclamation-triangle'],
        'danger' => ['bg-danger-bg text-danger', 'x-circle'],
        'info' => ['bg-info-bg text-info', 'information-circle'],
    ];
    [$palette, $icon] = $styles[$type] ?? $styles['info'];
@endphp

<div role="{{ $type === 'danger' ? 'alert' : 'status' }}" {{ $attributes->class('flex gap-3 rounded-control px-4 py-3 '.$palette) }}>
    <x-dynamic-component :component="'heroicon-m-'.$icon" class="mt-0.5 h-5 w-5 shrink-0" aria-hidden="true" />
    <div class="text-sm">
        @if ($title)
            <p class="font-medium">{{ $title }}</p>
        @endif
        <div @class(['text-ink' => true, 'mt-0.5' => $title])>{{ $slot }}</div>
    </div>
</div>
