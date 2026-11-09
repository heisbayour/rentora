{{-- Column header link that toggles ?sort=column&dir=asc|desc while keeping other query values. --}}
@props([
    'column',
    'sort',
    'direction' => 'asc',
])

@php
    $active = $sort === $column;
    $next = $active && $direction === 'asc' ? 'desc' : 'asc';
    $href = request()->fullUrlWithQuery(['sort' => $column, 'dir' => $next, 'page' => null]);
@endphp

<a href="{{ $href }}" {{ $attributes->class('inline-flex items-center gap-1 hover:text-ink') }}
    @if ($active) aria-sort="{{ $direction === 'asc' ? 'ascending' : 'descending' }}" @endif>
    {{ $slot }}
    @if ($active)
        <x-dynamic-component :component="$direction === 'asc' ? 'heroicon-m-chevron-up' : 'heroicon-m-chevron-down'" class="h-4 w-4" aria-hidden="true" />
    @endif
</a>
