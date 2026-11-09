<x-layouts::admin :title="__('listings.title')">
    <x-page-header :title="__('listings.title')" :description="__('listings.description')" />

    <div class="mb-4 flex flex-wrap gap-2">
        <x-filter-chip :href="route('app.listings.index')" :active="! $listedOnly">{{ __('listings.filter_all') }}</x-filter-chip>
        <x-filter-chip :href="route('app.listings.index', ['show' => 'listed'])" :active="$listedOnly">{{ __('listings.filter_listed') }}</x-filter-chip>
    </div>

    @error('unit')
        <x-alert type="danger" class="mb-4">{{ $message }}</x-alert>
    @enderror

    @if ($units->isEmpty())
        <x-empty-state icon="globe-alt" :title="__('listings.empty_title')" :text="__('listings.empty_text')" />
    @else
        <ul class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
            @foreach ($units as $unit)
                <li class="overflow-hidden rounded-card border border-border bg-surface shadow-card">
                    @if ($photo = $unit->photos->first())
                        <img src="{{ $photo->url() }}" alt="{{ $unit->label }}" class="h-40 w-full object-cover" loading="lazy">
                    @else
                        <div class="flex h-40 items-center justify-center bg-canvas text-sm text-ink-muted">{{ __('listings.no_photo') }}</div>
                    @endif
                    <div class="p-4">
                        <div class="flex items-start justify-between gap-3">
                            <div>
                                <a href="{{ route('app.units.show', $unit) }}" class="font-medium text-ink hover:text-primary-600">{{ $unit->label }}</a>
                                <p class="text-xs text-ink-muted">{{ $unit->property->name }}</p>
                            </div>
                            <x-status-chip :status="$unit->status" />
                        </div>
                        <p class="mt-2 text-sm font-semibold text-ink tabular-nums">{{ $unit->rent()->format() }}
                            <span class="font-normal text-ink-muted">{{ $unit->rent_frequency->label() }}</span></p>
                        <form method="POST" action="{{ route('app.listings.toggle', $unit) }}" class="mt-4">
                            @csrf @method('PATCH')
                            <x-button size="sm" :variant="$unit->is_listed ? 'secondary' : 'primary'" class="w-full">
                                {{ $unit->is_listed ? __('listings.unlist') : __('listings.list') }}
                            </x-button>
                        </form>
                    </div>
                </li>
            @endforeach
        </ul>
        <x-pagination :paginator="$units" class="mt-4" />
    @endif
</x-layouts::admin>
