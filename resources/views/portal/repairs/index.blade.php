<x-layouts::portal :title="__('maintenance.portal.title')">
    <div class="mb-6 flex items-center justify-between gap-3">
        <h1 class="text-xl font-semibold text-ink">{{ __('maintenance.portal.title') }}</h1>
        <x-button :href="route('portal.repairs.create')" icon="plus" size="sm">{{ __('maintenance.portal.new') }}</x-button>
    </div>

    @if ($requests->isEmpty())
        <x-empty-state icon="wrench-screwdriver" :title="__('maintenance.portal.empty_title')" :text="__('maintenance.portal.empty_text')"
            :action-label="__('maintenance.portal.new')" :action-href="route('portal.repairs.create')" />
    @else
        <ul class="space-y-3">
            @foreach ($requests as $item)
                <li>
                    <a href="{{ route('portal.repairs.show', $item) }}" class="block rounded-card border border-border bg-surface p-4 shadow-card hover:border-primary-600">
                        <div class="flex items-start justify-between gap-3">
                            <div>
                                <p class="font-medium text-ink">{{ $item->title }}</p>
                                <p class="text-xs text-ink-muted">{{ $item->number }} · {{ $item->created_at->format('j M Y') }}</p>
                            </div>
                            <x-status-chip :status="$item->status" />
                        </div>
                    </a>
                </li>
            @endforeach
        </ul>
        <x-pagination :paginator="$requests" class="mt-4" />
    @endif
</x-layouts::portal>
