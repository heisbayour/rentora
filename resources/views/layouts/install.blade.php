{{-- Installer and upgrader: a wider centred card with a step indicator. --}}
@props([
    'title' => null,
    'steps' => [],
    'current' => 1,
])

<x-layouts::base :title="$title" body-class="bg-canvas">
    <main id="main" class="mx-auto flex min-h-full max-w-2xl flex-col justify-center px-4 py-12">
        <p class="mb-6 text-center text-lg font-semibold text-ink">{{ config('rentora.name') }} {{ config('rentora.version') }}</p>
        @if ($steps)
            <x-stepper :steps="$steps" :current="$current" class="mb-6" />
        @endif
        <div class="rounded-card border border-border bg-surface p-6 shadow-card sm:p-8">
            {{ $slot }}
        </div>
    </main>
</x-layouts::base>
