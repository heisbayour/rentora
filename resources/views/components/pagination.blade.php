@props(['paginator'])

@if ($paginator->hasPages())
    <nav {{ $attributes->class('flex flex-col items-center justify-between gap-3 sm:flex-row') }} aria-label="{{ __('ui.pagination.label') }}">
        <p class="text-sm text-ink-muted tabular-nums">
            {{ __('ui.pagination.showing', ['first' => $paginator->firstItem(), 'last' => $paginator->lastItem(), 'total' => $paginator->total()]) }}
        </p>
        <div class="flex items-center gap-1">
            @if ($paginator->onFirstPage())
                <span class="inline-flex h-9 w-9 items-center justify-center rounded-control text-ink-muted/50" aria-disabled="true">
                    <x-heroicon-m-chevron-left class="h-5 w-5" aria-hidden="true" />
                </span>
            @else
                <a href="{{ $paginator->previousPageUrl() }}" rel="prev" class="inline-flex h-9 w-9 items-center justify-center rounded-control text-ink hover:bg-canvas" aria-label="{{ __('ui.pagination.previous') }}">
                    <x-heroicon-m-chevron-left class="h-5 w-5" aria-hidden="true" />
                </a>
            @endif

            @php
                $current = $paginator->currentPage();
                $last = $paginator->lastPage();
                $pages = collect([1, $current - 1, $current, $current + 1, $last])->filter(fn ($p) => $p >= 1 && $p <= $last)->unique()->sort()->values();
            @endphp
            @foreach ($pages as $index => $page)
                @if ($index > 0 && $page - $pages[$index - 1] > 1)
                    <span class="px-1 text-ink-muted">...</span>
                @endif
                @if ($page === $current)
                    <span aria-current="page" class="inline-flex h-9 min-w-9 items-center justify-center rounded-control bg-primary-50 px-2 text-sm font-medium text-primary-700 tabular-nums">{{ $page }}</span>
                @else
                    <a href="{{ $paginator->url($page) }}" class="inline-flex h-9 min-w-9 items-center justify-center rounded-control px-2 text-sm text-ink hover:bg-canvas tabular-nums">{{ $page }}</a>
                @endif
            @endforeach

            @if ($paginator->hasMorePages())
                <a href="{{ $paginator->nextPageUrl() }}" rel="next" class="inline-flex h-9 w-9 items-center justify-center rounded-control text-ink hover:bg-canvas" aria-label="{{ __('ui.pagination.next') }}">
                    <x-heroicon-m-chevron-right class="h-5 w-5" aria-hidden="true" />
                </a>
            @else
                <span class="inline-flex h-9 w-9 items-center justify-center rounded-control text-ink-muted/50" aria-disabled="true">
                    <x-heroicon-m-chevron-right class="h-5 w-5" aria-hidden="true" />
                </span>
            @endif
        </div>
    </nav>
@endif
