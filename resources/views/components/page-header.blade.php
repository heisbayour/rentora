{{-- :breadcrumbs is an ordered [label => url] array; the last entry may have a null url. --}}
@props([
    'title',
    'description' => null,
    'breadcrumbs' => [],
])

<div {{ $attributes->class('mb-6 flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between') }}>
    <div class="min-w-0">
        @if ($breadcrumbs)
            <nav aria-label="{{ __('ui.breadcrumbs') }}" class="mb-2">
                <ol class="flex flex-wrap items-center gap-1 text-sm text-ink-muted">
                    @foreach ($breadcrumbs as $label => $url)
                        <li class="flex items-center gap-1">
                            @if (! $loop->first)
                                <x-heroicon-m-chevron-right class="h-4 w-4" aria-hidden="true" />
                            @endif
                            @if ($url && ! $loop->last)
                                <a href="{{ $url }}" class="hover:text-primary-600">{{ $label }}</a>
                            @else
                                <span @if ($loop->last) aria-current="page" @endif>{{ $label }}</span>
                            @endif
                        </li>
                    @endforeach
                </ol>
            </nav>
        @endif
        <h1 class="truncate text-xl font-semibold text-ink">{{ $title }}</h1>
        @if ($description)
            <p class="mt-1 text-sm text-ink-muted">{{ $description }}</p>
        @endif
    </div>
    @isset($actions)
        <div class="flex flex-wrap items-center gap-2">{{ $actions }}</div>
    @endisset
</div>
