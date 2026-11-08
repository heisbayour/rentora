@props([
    'title' => null,
    'description' => null,
    'padding' => true,
])

<section {{ $attributes->class('rounded-card border border-border bg-surface shadow-card') }}>
    @if ($title || isset($actions))
        <header class="flex flex-wrap items-start justify-between gap-3 border-b border-border px-5 py-4">
            <div>
                @if ($title)
                    <h2 class="text-base font-semibold text-ink">{{ $title }}</h2>
                @endif
                @if ($description)
                    <p class="mt-0.5 text-sm text-ink-muted">{{ $description }}</p>
                @endif
            </div>
            @isset($actions)
                <div class="flex items-center gap-2">{{ $actions }}</div>
            @endisset
        </header>
    @endif
    <div @class(['p-5' => $padding])>
        {{ $slot }}
    </div>
    @isset($footer)
        <footer class="border-t border-border px-5 py-3">{{ $footer }}</footer>
    @endisset
</section>
