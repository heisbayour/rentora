{{-- Auth screens: a centred 400px card on the canvas, org logo above. --}}
@props([
    'title' => null,
    'heading' => null,
    'brandColor' => null,
    'logo' => null,
    'orgName' => null,
])

@php
    $organization = app(\App\Support\CurrentOrganization::class)->get();
    $orgName = $orgName ?? $organization?->name ?? config('rentora.name');
    $logo = $logo ?? $organization?->logoUrl();
@endphp

<x-layouts::base :title="$title" :brand-color="$brandColor" body-class="bg-canvas">
    <main id="main" class="flex min-h-full flex-col items-center justify-center px-4 py-12">
        <div class="mb-6 text-center">
            @if ($logo)
                <img src="{{ $logo }}" alt="{{ $orgName }}" class="mx-auto h-12 w-auto">
            @else
                <span class="mx-auto flex h-12 w-12 items-center justify-center rounded-card bg-primary-600 text-lg font-semibold text-white">{{ mb_substr($orgName, 0, 1) }}</span>
                <p class="mt-3 text-base font-semibold text-ink">{{ $orgName }}</p>
            @endif
        </div>
        <div class="w-full max-w-[400px] rounded-card border border-border bg-surface p-6 shadow-card sm:p-8">
            @if ($heading)
                <h1 class="mb-6 text-lg font-semibold text-ink">{{ $heading }}</h1>
            @endif
            {{ $slot }}
        </div>
        @isset($below)
            <div class="mt-6 text-center text-sm text-ink-muted">{{ $below }}</div>
        @endisset
    </main>
</x-layouts::base>
