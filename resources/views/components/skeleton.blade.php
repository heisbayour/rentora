{{-- Pass :height (for example "h-64" for a chart) for a block skeleton instead of text lines. --}}
@props([
    'lines' => 3,
    'height' => null,
])

<div {{ $attributes->class('animate-pulse') }} role="status" aria-live="polite">
    <span class="sr-only">{{ __('ui.loading') }}</span>
    @if ($height)
        <div class="{{ $height }} w-full rounded-control bg-border/60"></div>
    @else
        <div class="space-y-3">
            @for ($i = 0; $i < $lines; $i++)
                <div class="h-3 rounded bg-border/60 {{ $i === $lines - 1 ? 'w-2/3' : 'w-full' }}"></div>
            @endfor
        </div>
    @endif
</div>
