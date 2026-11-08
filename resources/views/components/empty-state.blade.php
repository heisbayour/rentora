@props([
    'icon' => 'inbox',
    'title',
    'text' => null,
    'actionLabel' => null,
    'actionHref' => null,
])

<div {{ $attributes->class('flex flex-col items-center justify-center rounded-card border border-dashed border-border bg-surface px-6 py-12 text-center') }}>
    <span class="flex h-12 w-12 items-center justify-center rounded-full bg-primary-50 text-primary-600">
        <x-dynamic-component :component="'heroicon-o-'.$icon" class="h-6 w-6" aria-hidden="true" />
    </span>
    <h3 class="mt-4 text-base font-semibold text-ink">{{ $title }}</h3>
    @if ($text)
        <p class="mt-1 max-w-sm text-sm text-ink-muted">{{ $text }}</p>
    @endif
    @if ($actionLabel && $actionHref)
        <x-button :href="$actionHref" icon="plus" class="mt-5">{{ $actionLabel }}</x-button>
    @endif
    @if ($slot->isNotEmpty())
        <div class="mt-5">{{ $slot }}</div>
    @endif
</div>
