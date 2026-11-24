<x-layouts::portal :title="__('announcements.notices.title')">
    <h1 class="mb-4 text-xl font-semibold text-ink">{{ __('announcements.notices.title') }}</h1>

    @if ($notices->isEmpty())
        <x-empty-state icon="megaphone" :title="__('announcements.notices.empty_title')" :text="__('announcements.notices.empty_text')" />
    @else
        <ul class="space-y-3">
            @foreach ($notices as $notice)
                <li>
                    <x-card>
                        <p class="text-xs text-ink-muted">{{ $notice->sent_at?->timezone(config('rentora.defaults.timezone'))->format('j M Y') }}</p>
                        <h2 class="mt-1 font-semibold text-ink">{{ $notice->title }}</h2>
                        <p class="mt-2 whitespace-pre-line text-sm text-ink">{{ $notice->body }}</p>
                    </x-card>
                </li>
            @endforeach
        </ul>
        <x-pagination :paginator="$notices" class="mt-4" />
    @endif
</x-layouts::portal>
