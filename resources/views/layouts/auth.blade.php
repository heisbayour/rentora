{{-- Auth screens: a centred 400px card on the canvas, org logo above; full-width native-style on phones. --}}
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

{{--
    Phones (below 640px, also the Android app): full width on white, no card
    frame, the form near the top, 48px fields and buttons with 16px text, so it
    reads like a native screen. From 640px up: the centred 400px card.
--}}
<x-layouts::base :title="$title" :brand-color="$brandColor" body-class="bg-surface sm:bg-canvas">
    <main id="main" class="flex min-h-full flex-col items-center px-5 pb-10 pt-12 sm:justify-center sm:px-4 sm:py-12">
        <div class="mb-8 text-center sm:mb-6">
            @if ($logo)
                <img src="{{ $logo }}" alt="{{ $orgName }}" class="mx-auto h-16 w-auto sm:h-12">
            @else
                <span class="mx-auto flex h-16 w-16 items-center justify-center rounded-card bg-primary-600 text-2xl font-semibold text-white sm:h-12 sm:w-12 sm:text-lg">{{ mb_substr($orgName, 0, 1) }}</span>
                <p class="mt-3 text-xl font-semibold text-ink sm:text-base">{{ $orgName }}</p>
            @endif
        </div>
        <div @class([
            'w-full max-w-[400px] sm:rounded-card sm:border sm:border-border sm:bg-surface sm:p-8 sm:shadow-card',
            'max-sm:text-base max-sm:[&_label]:text-base max-sm:[&_a]:text-base',
            'max-sm:[&_input:not([type=checkbox]):not([type=radio])]:h-12 max-sm:[&_input]:text-base max-sm:[&_select]:h-12 max-sm:[&_select]:text-base',
            'max-sm:[&_button]:min-h-12 max-sm:[&_button]:text-base max-sm:[&_a.inline-flex]:min-h-12 max-sm:[&_a.inline-flex]:text-base',
        ])>
            @if ($heading)
                <h1 class="mb-6 text-2xl font-semibold text-ink sm:text-lg">{{ $heading }}</h1>
            @endif
            {{ $slot }}
        </div>
        @isset($below)
            <div class="mt-8 text-center text-base text-ink-muted sm:mt-6 sm:text-sm">{{ $below }}</div>
        @endisset
    </main>
</x-layouts::base>
