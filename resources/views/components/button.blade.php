@props([
    'variant' => 'primary',
    'size' => 'md',
    'type' => 'submit',
    'href' => null,
    'loading' => false,
    'icon' => null,
])

@php
    $variants = [
        'primary' => 'bg-primary-600 text-white hover:bg-primary-700 border-transparent',
        'secondary' => 'bg-surface text-primary-600 border-border hover:bg-primary-50',
        'ghost' => 'bg-transparent text-ink border-transparent hover:bg-canvas',
        'danger' => 'bg-danger text-white border-transparent hover:brightness-95',
    ];
    $sizes = [
        'sm' => 'h-8 px-3 text-sm gap-1.5',
        'md' => 'h-10 px-4 text-sm gap-2',
    ];
    $classes = 'rt-btn inline-flex items-center justify-center rounded-control border font-medium transition-colors '
        .'disabled:cursor-not-allowed disabled:opacity-60 aria-disabled:pointer-events-none aria-disabled:opacity-60 '
        .($variants[$variant] ?? $variants['primary']).' '.($sizes[$size] ?? $sizes['md']);
@endphp

@if ($href)
    <a href="{{ $href }}" {{ $attributes->class($classes) }}>
        @if ($icon)
            <x-dynamic-component :component="'heroicon-m-'.$icon" class="h-5 w-5 shrink-0" aria-hidden="true" />
        @endif
        {{ $slot }}
    </a>
@else
    <button type="{{ $type }}"
        @if ($loading) data-loading="true" disabled aria-busy="true" @endif
        {{ $attributes->class($classes) }}>
        <svg class="rt-btn-spinner hidden h-4 w-4 shrink-0 animate-spin" viewBox="0 0 24 24" fill="none" aria-hidden="true">
            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z"></path>
        </svg>
        @if ($icon)
            <x-dynamic-component :component="'heroicon-m-'.$icon" class="h-5 w-5 shrink-0" aria-hidden="true" />
        @endif
        {{ $slot }}
    </button>
@endif
