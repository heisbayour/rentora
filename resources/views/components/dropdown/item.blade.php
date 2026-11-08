@props([
    'href' => null,
    'icon' => null,
    'danger' => false,
])

@php
    $classes = 'flex w-full items-center gap-2 px-4 py-2 text-left text-sm focus:bg-canvas focus:outline-none hover:bg-canvas '
        .($danger ? 'text-danger' : 'text-ink');
@endphp

@if ($href)
    <a href="{{ $href }}" role="menuitem" {{ $attributes->class($classes) }}>
        @if ($icon)
            <x-dynamic-component :component="'heroicon-m-'.$icon" class="h-5 w-5 text-ink-muted" aria-hidden="true" />
        @endif
        {{ $slot }}
    </a>
@else
    <button type="{{ $attributes->get('type', 'button') }}" role="menuitem" {{ $attributes->except('type')->class($classes) }}>
        @if ($icon)
            <x-dynamic-component :component="'heroicon-m-'.$icon" class="h-5 w-5 text-ink-muted" aria-hidden="true" />
        @endif
        {{ $slot }}
    </button>
@endif
