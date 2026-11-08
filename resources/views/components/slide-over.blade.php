{{-- Side panel. Opens and closes with the same open-modal / close-modal events as x-modal. --}}
@props([
    'name',
    'title' => null,
    'show' => false,
])

<div x-data="{ open: @js($show) }"
    x-on:open-modal.window="if ($event.detail === @js($name)) open = true"
    x-on:close-modal.window="if ($event.detail === @js($name)) open = false"
    x-on:keydown.escape.window="open = false"
    x-show="open" x-cloak class="fixed inset-0 z-50 overflow-hidden"
    role="dialog" aria-modal="true" @if ($title) aria-labelledby="slide-{{ $name }}-title" @endif>
    <div x-show="open" x-transition.opacity class="absolute inset-0 bg-ink/40" @click="open = false" aria-hidden="true"></div>
    <div x-show="open" x-trap.inert.noscroll="open"
        x-transition:enter="transform transition ease-out duration-200" x-transition:enter-start="translate-x-full" x-transition:enter-end="translate-x-0"
        x-transition:leave="transform transition ease-in duration-150" x-transition:leave-start="translate-x-0" x-transition:leave-end="translate-x-full"
        {{ $attributes->class('absolute inset-y-0 right-0 flex w-full max-w-md flex-col bg-surface shadow-card') }}>
        <div class="flex items-center justify-between gap-4 border-b border-border px-5 py-4">
            @if ($title)
                <h2 id="slide-{{ $name }}-title" class="text-base font-semibold text-ink">{{ $title }}</h2>
            @endif
            <button type="button" class="rounded-control p-1 text-ink-muted hover:bg-canvas" @click="open = false">
                <span class="sr-only">{{ __('ui.close') }}</span>
                <x-heroicon-m-x-mark class="h-5 w-5" aria-hidden="true" />
            </button>
        </div>
        <div class="flex-1 overflow-y-auto px-5 py-4 text-sm text-ink">{{ $slot }}</div>
        @isset($footer)
            <div class="flex justify-end gap-2 border-t border-border bg-canvas px-5 py-3">{{ $footer }}</div>
        @endisset
    </div>
</div>
