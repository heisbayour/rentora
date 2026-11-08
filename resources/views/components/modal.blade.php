{{--
    Open with: $dispatch('open-modal', 'name'). Close with: $dispatch('close-modal', 'name') or Escape.
    Set :show="true" to open on load (for example after a validation error).
--}}
@props([
    'name',
    'title' => null,
    'show' => false,
    'maxWidth' => 'md',
])

@php
    $widths = ['sm' => 'sm:max-w-sm', 'md' => 'sm:max-w-lg', 'lg' => 'sm:max-w-2xl', 'xl' => 'sm:max-w-4xl'];
@endphp

<div x-data="{ open: @js($show) }"
    x-on:open-modal.window="if ($event.detail === @js($name)) open = true"
    x-on:close-modal.window="if ($event.detail === @js($name)) open = false"
    x-on:keydown.escape.window="open = false"
    x-show="open" x-cloak
    class="fixed inset-0 z-50 flex items-end justify-center px-4 pb-4 sm:items-center sm:p-6"
    role="dialog" aria-modal="true" @if ($title) aria-labelledby="modal-{{ $name }}-title" @endif>
    <div x-show="open" x-transition.opacity class="fixed inset-0 bg-ink/40" @click="open = false" aria-hidden="true"></div>
    <div x-show="open" x-trap.inert.noscroll="open"
        x-transition:enter="ease-out duration-150" x-transition:enter-start="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95"
        x-transition:enter-end="opacity-100 translate-y-0 sm:scale-100"
        {{ $attributes->class('relative w-full rounded-card bg-surface shadow-card '.($widths[$maxWidth] ?? $widths['md'])) }}>
        <div class="flex items-start justify-between gap-4 px-5 pt-5">
            @if ($title)
                <h2 id="modal-{{ $name }}-title" class="text-base font-semibold text-ink">{{ $title }}</h2>
            @endif
            <button type="button" class="-mr-1 -mt-1 rounded-control p-1 text-ink-muted hover:bg-canvas" @click="open = false">
                <span class="sr-only">{{ __('ui.close') }}</span>
                <x-heroicon-m-x-mark class="h-5 w-5" aria-hidden="true" />
            </button>
        </div>
        <div class="px-5 pb-5 pt-3 text-sm text-ink">{{ $slot }}</div>
        @isset($footer)
            <div class="flex flex-col-reverse gap-2 rounded-b-card border-t border-border bg-canvas px-5 py-3 sm:flex-row sm:justify-end">
                {{ $footer }}
            </div>
        @endisset
    </div>
</div>
