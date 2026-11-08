{{-- Public website: marketing header, content, contact footer. --}}
@props([
    'title' => null,
    'description' => null,
    'brandColor' => null,
    'orgName' => null,
    'logo' => null,
    'contact' => [],
])

@php
    $orgName = $orgName ?? config('rentora.name');
@endphp

<x-layouts::base :title="$title" :description="$description" :brand-color="$brandColor" body-class="bg-surface">
    @push('head')
        @isset($meta)
            {{ $meta }}
        @endisset
    @endpush
    <header class="border-b border-border bg-surface">
        <div class="mx-auto flex h-16 max-w-6xl items-center gap-6 px-4 sm:px-6">
            <a href="{{ url('/') }}" class="flex items-center gap-3">
                @if ($logo)
                    <img src="{{ $logo }}" alt="{{ $orgName }}" class="h-9 w-auto">
                @else
                    <span class="text-lg font-semibold text-ink">{{ $orgName }}</span>
                @endif
            </a>
            <nav class="ml-auto flex items-center gap-2">
                @if (Route::has('public.listings'))
                    <a href="{{ route('public.listings') }}" class="hidden rounded-control px-3 py-2 text-sm font-medium text-ink-muted hover:text-ink sm:block">{{ __('nav.public.listings') }}</a>
                @endif
                <a href="#contact" class="hidden rounded-control px-3 py-2 text-sm font-medium text-ink-muted hover:text-ink sm:block">{{ __('nav.public.contact') }}</a>
                @if (Route::has('login'))
                    <x-button :href="route('login')" size="sm">{{ __('nav.public.tenant_login') }}</x-button>
                @endif
            </nav>
        </div>
    </header>

    <main id="main">
        {{ $slot }}
    </main>

    <footer id="contact" class="border-t border-border bg-canvas">
        <div class="mx-auto grid max-w-6xl gap-6 px-4 py-10 text-sm text-ink-muted sm:grid-cols-2 sm:px-6">
            <div>
                <p class="text-base font-semibold text-ink">{{ $orgName }}</p>
                @foreach (array_filter($contact) as $line)
                    <p class="mt-1">{{ $line }}</p>
                @endforeach
            </div>
            <div class="sm:text-right">
                <p>&copy; {{ now()->year }} {{ $orgName }}. {{ __('ui.all_rights') }}</p>
                <p class="mt-1">{{ __('ui.powered_by', ['name' => config('rentora.name')]) }}</p>
            </div>
        </div>
    </footer>
</x-layouts::base>
