<x-layouts::public :title="$organization->name" :description="$organization->website_headline ?? __('public.default_headline')"
    :org-name="$organization->name" :logo="$organization->logoUrl()" :contact="$contact">
    <section class="bg-canvas">
        <div class="mx-auto max-w-6xl px-4 py-16 sm:px-6 sm:py-20">
            <h1 class="max-w-2xl text-3xl font-semibold text-ink sm:text-4xl">{{ $organization->website_headline ?? __('public.default_headline') }}</h1>
            <p class="mt-4 max-w-2xl whitespace-pre-line text-base text-ink-muted">{{ $organization->website_about ?? __('public.default_about') }}</p>
            <div class="mt-8 flex flex-wrap gap-3">
                <x-button :href="route('public.listings')" icon="magnifying-glass">{{ __('public.browse') }}</x-button>
                <x-button variant="secondary" :href="route('login')">{{ __('nav.public.tenant_login') }}</x-button>
            </div>
        </div>
    </section>

    <section class="mx-auto max-w-6xl px-4 py-12 sm:px-6">
        <div class="mb-6 flex items-end justify-between gap-3">
            <h2 class="text-xl font-semibold text-ink">{{ __('public.featured') }}</h2>
            <a href="{{ route('public.listings') }}" class="text-sm font-medium text-primary-600 hover:underline">{{ __('public.view_all') }}</a>
        </div>
        @if ($featured->isEmpty())
            <x-empty-state icon="home" :title="__('public.empty_title')" :text="__('public.empty_text')" />
        @else
            <div class="grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
                @foreach ($featured as $unit)
                    @include('public._unit-card', ['unit' => $unit])
                @endforeach
            </div>
        @endif
    </section>
</x-layouts::public>
