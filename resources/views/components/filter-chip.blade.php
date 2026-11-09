@props([
    'href',
    'active' => false,
])

<a href="{{ $href }}" @if ($active) aria-current="true" @endif
    {{ $attributes->class([
        'inline-flex items-center rounded-full px-3 py-1.5 text-sm font-medium transition-colors',
        'bg-primary-50 text-primary-700 ring-1 ring-inset ring-primary-600/30' => $active,
        'bg-surface text-ink-muted ring-1 ring-inset ring-border hover:text-ink' => ! $active,
    ]) }}>{{ $slot }}</a>
