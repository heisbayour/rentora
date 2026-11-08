@props([
    'name' => '',
    'src' => null,
    'size' => 'md',
])

@php
    $initials = collect(preg_split('/\s+/', trim($name)) ?: [])
        ->filter()
        ->take(2)
        ->map(fn (string $part) => mb_strtoupper(mb_substr($part, 0, 1)))
        ->implode('');
    $sizes = ['sm' => 'h-8 w-8 text-xs', 'md' => 'h-10 w-10 text-sm', 'lg' => 'h-14 w-14 text-base'];
@endphp

@if ($src)
    <img src="{{ $src }}" alt="{{ $name }}" {{ $attributes->class('shrink-0 rounded-full object-cover '.($sizes[$size] ?? $sizes['md'])) }}>
@else
    <span role="img" aria-label="{{ $name }}"
        {{ $attributes->class('inline-flex shrink-0 items-center justify-center rounded-full bg-primary-50 font-semibold text-primary-700 '.($sizes[$size] ?? $sizes['md'])) }}>
        {{ $initials !== '' ? $initials : '?' }}
    </span>
@endif
