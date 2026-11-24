<x-layouts::portal :title="__('portal.documents.title')">
    <h1 class="mb-4 text-xl font-semibold text-ink">{{ __('portal.documents.title') }}</h1>

    @if ($documents->isEmpty())
        <x-empty-state icon="folder" :title="__('portal.documents.empty_title')" :text="__('portal.documents.empty_text')" />
    @else
        <ul class="divide-y divide-border overflow-hidden rounded-card border border-border bg-surface shadow-card">
            @foreach ($documents as $document)
                <li class="flex items-center justify-between gap-3 px-4 py-3">
                    <div class="min-w-0">
                        <p class="truncate text-sm font-medium text-ink">{{ $document->name }}</p>
                        <p class="text-xs text-ink-muted">{{ $document->created_at?->format('j M Y') }}</p>
                    </div>
                    <x-button size="sm" variant="ghost" :href="route('files.documents', $document)" icon="arrow-down-tray">{{ __('documents.download') }}</x-button>
                </li>
            @endforeach
        </ul>
    @endif
</x-layouts::portal>
