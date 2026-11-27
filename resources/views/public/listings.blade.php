<x-layouts::public :title="__('public.listings_title')" :description="__('public.listings_intro', ['org' => $organization->name])"
    :org-name="$organization->name" :logo="$organization->logoUrl()" :contact="$contact">
    <section class="mx-auto max-w-6xl px-4 py-10 sm:px-6">
        <h1 class="text-2xl font-semibold text-ink">{{ __('public.listings_title') }}</h1>
        <p class="mt-1 text-sm text-ink-muted">{{ __('public.listings_intro', ['org' => $organization->name]) }}</p>

        <form method="GET" class="mt-6 grid grid-cols-1 gap-3 rounded-card border border-border bg-canvas p-4 sm:grid-cols-5 sm:items-end" data-no-guard>
            <x-select name="property_id" :label="__('public.filters.property')" :options="$properties" :value="$filters['property_id'] ?? null" :placeholder="__('public.filters.any')" />
            <x-select name="type" :label="__('public.filters.type')" :options="$types" :value="$filters['type'] ?? null" :placeholder="__('public.filters.any')" />
            <x-select name="bedrooms" :label="__('public.filters.bedrooms')" :options="[1 => '1+', 2 => '2+', 3 => '3+', 4 => '4+']" :value="$filters['bedrooms'] ?? null" :placeholder="__('public.filters.any')" />
            <x-input name="max_rent" type="number" min="0" step="1000" :label="__('public.filters.max_rent')" :value="$filters['max_rent'] ?? null" />
            <x-button icon="funnel">{{ __('public.filters.apply') }}</x-button>
        </form>

        <div class="mt-8">
            @if ($units->isEmpty())
                <x-empty-state icon="home" :title="__('public.no_results_title')" :text="__('public.no_results_text')" />
            @else
                <div class="grid grid-cols-1 gap-6 sm:grid-cols-2 lg:grid-cols-3">
                    @foreach ($units as $unit)
                        @include('public._unit-card', ['unit' => $unit])
                    @endforeach
                </div>
                <x-pagination :paginator="$units" class="mt-6" />
            @endif
        </div>
    </section>
</x-layouts::public>
