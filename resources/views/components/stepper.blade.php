{{-- Wizard progress. :steps is a list of labels, :current the 1-based active step. --}}
@props([
    'steps' => [],
    'current' => 1,
])

@php
    $total = count($steps);
@endphp

<nav {{ $attributes->class('w-full') }} aria-label="{{ __('ui.step_of', ['current' => $current, 'total' => $total]) }}">
    <p class="mb-2 text-xs font-medium text-ink-muted sm:hidden">
        {{ __('ui.step_of', ['current' => $current, 'total' => $total]) }}: {{ $steps[$current - 1] ?? '' }}
    </p>
    <ol class="flex items-center gap-2">
        @foreach ($steps as $index => $label)
            @php
                $number = $index + 1;
                $state = $number < $current ? 'done' : ($number === $current ? 'current' : 'upcoming');
            @endphp
            <li class="flex flex-1 items-center gap-2" @if ($state === 'current') aria-current="step" @endif>
                <span @class([
                    'flex h-7 w-7 shrink-0 items-center justify-center rounded-full text-xs font-semibold',
                    'bg-primary-600 text-white' => $state !== 'upcoming',
                    'border border-border bg-surface text-ink-muted' => $state === 'upcoming',
                ])>
                    @if ($state === 'done')
                        <x-heroicon-m-check class="h-4 w-4" aria-hidden="true" />
                    @else
                        {{ $number }}
                    @endif
                </span>
                <span @class([
                    'hidden truncate text-sm sm:block',
                    'font-medium text-ink' => $state === 'current',
                    'text-ink-muted' => $state !== 'current',
                ])>{{ $label }}</span>
                @if (! $loop->last)
                    <span @class(['h-px flex-1', 'bg-primary-600' => $state === 'done', 'bg-border' => $state !== 'done']) aria-hidden="true"></span>
                @endif
            </li>
        @endforeach
    </ol>
</nav>
